// src/app/modules/profil/profil.ts
// Utilise a.dateAction (aligné avec JournalEntry.dateAction)
import { Component, OnInit, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../core/services/auth.service';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-profil',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './profil.html',
  styleUrl: './profil.css'
})
export class ProfilPage implements OnInit {
  private auth = inject(AuthService);
  private http = inject(HttpClient);

  onglet: 'infos' | 'securite' | 'activites' = 'infos';

  user        = this.auth.user?.();
  prenomEdit  = '';
  nomEdit     = '';
  emailEdit   = '';
  telEdit     = '';
  enCoursEdit = false;
  msgEdit     = '';

  ancienMdp   = '';
  nouveauMdp  = '';
  confirmMdp  = '';
  enCoursMdp  = false;
  msgMdp      = '';
  showAncien  = false;
  showNouveau = false;

  activites        = signal<any[]>([]);
  chargActiv       = signal(true);
  totalActivites   = 0;
  pageActivites    = 1;
  pageSizeActivites = 15;
  filtreDateDebut  = '';
  filtreDateFin    = '';

  ngOnInit() {
    const u = this.user;
    if (u) {
      this.prenomEdit = u.prenom ?? '';
      this.nomEdit    = u.nom    ?? '';
      this.emailEdit  = u.email  ?? '';
    }
    this.chargerActivites();
  }

  chargerActivites() {
    this.chargActiv.set(true);
    const params: any = {
      utilisateurId: this.user?.id ?? '',
      page:          this.pageActivites.toString(),
      pageSize:      this.pageSizeActivites.toString()
    };
    if (this.filtreDateDebut) params['dateDebut'] = this.filtreDateDebut;
    if (this.filtreDateFin)   params['dateFin']   = this.filtreDateFin;

    this.http.get<{ total: number; data: any[] }>(
      `${environment.apiUrl}/api/journaux/mes-activites`, { params }
    ).subscribe({
      next: r => {
        this.activites.set(r.data);
        this.totalActivites = r.total;
        this.chargActiv.set(false);
      },
      error: () => { this.activites.set([]); this.chargActiv.set(false); }
    });
  }

  appliquerFiltresDate() { this.pageActivites = 1; this.chargerActivites(); }
  reinitialiserFiltres() { this.filtreDateDebut = ''; this.filtreDateFin = ''; this.pageActivites = 1; this.chargerActivites(); }
  pagePrecedente() { if (this.pageActivites > 1) { this.pageActivites--; this.chargerActivites(); } }
  pageSuivante()   { if (this.pageActivites * this.pageSizeActivites < this.totalActivites) { this.pageActivites++; this.chargerActivites(); } }
  get totalPages() { return Math.ceil(this.totalActivites / this.pageSizeActivites); }

  sauvegarderProfil() {
    const u = this.user; if (!u) return;
    this.enCoursEdit = true; this.msgEdit = '';
    this.http.put<{ message: string }>(
      `${environment.apiUrl}/api/utilisateurs/${u.id}`,
      { nom: this.nomEdit, prenom: this.prenomEdit, email: this.emailEdit, telephone: this.telEdit }
    ).subscribe({
      next:  r   => { this.msgEdit = '✅ ' + r.message; this.enCoursEdit = false; },
      error: err => { this.msgEdit = '❌ ' + (err.error?.message ?? 'Erreur.'); this.enCoursEdit = false; }
    });
  }

    changerMotDePasse() {
  if (this.nouveauMdp !== this.confirmMdp) { 
    this.msgMdp = '❌ Les mots de passe ne correspondent pas.'; 
    return; 
  }
  if (this.nouveauMdp.length < 6) { 
    this.msgMdp = '❌ Minimum 6 caractères.'; 
    return; 
  }
  // ✅ Relire l'user au moment de l'action (pas au moment du chargement)
  const u = this.auth.user();
  if (!u?.id) { this.msgMdp = '❌ Session expirée.'; return; }
  
  this.enCoursMdp = true; this.msgMdp = '';
  this.http.put<{ message: string }>(
    `${environment.apiUrl}/api/utilisateurs/${u.id}/mot-de-passe`,
    { ancienMotDePasse: this.ancienMdp, nouveauMotDePasse: this.nouveauMdp }
  ).subscribe({
    next: r => { 
      this.msgMdp = '✅ ' + r.message; 
      this.enCoursMdp = false; 
      this.ancienMdp = ''; this.nouveauMdp = ''; this.confirmMdp = ''; 
    },
    error: err => { 
      this.msgMdp = '❌ ' + (err.error?.message ?? 'Erreur.'); 
      this.enCoursMdp = false; 
    }
  });
}

  iconModule(module: string): string {
    const m: Record<string, string> = { Dossiers:'📁', Archivage:'🗄', Utilisateurs:'👤', Auth:'🔐', Système:'⚙️' };
    return m[module] ?? '📋';
  }

  couleurNiveau(n: number): string { return n >= 3 ? '#E53935' : n === 2 ? '#FF8F00' : '#43A047'; }
  labelNiveau(n: number):  string  { return n >= 3 ? 'Critique' : n === 2 ? 'Avertissement' : 'Info'; }

  formatDate(d: string): string {
    return new Date(d).toLocaleDateString('fr-FR', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
  }

  get initiales(): string {
    const u = this.user;
    return u ? `${u.prenom?.charAt(0) ?? ''}${u.nom?.charAt(0) ?? ''}`.toUpperCase() || 'U' : 'U';
  }
}