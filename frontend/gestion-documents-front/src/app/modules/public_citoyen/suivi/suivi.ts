// src/app/modules/public_citoyen/suivi/suivi.ts
import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { NavbarPublic } from '../navbar-public/navbar-public';
import { environment } from '../../../../environments/environment';
import { TranslatePipe } from '../../../core/pipes/translate.pipe';

interface HistoriqueStatut {
  ancienStatut: string;
  nouveauStatut: string;
  commentaire: string;
  dateChangement: string;
  agentNom: string;
}

interface SuiviDossier {
  numero: string;
  titre: string;
  statut: string;
  nomCitoyen: string;
  dateDepot: string;
  dateMiseAJourStatut: string;
  motifRejet?: string;
  historique: HistoriqueStatut[];
}

@Component({
  selector: 'app-suivi',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, NavbarPublic, TranslatePipe],
  templateUrl: './suivi.html',
  styleUrls: ['./suivi.css']
})
export class Suivi implements OnInit {
  numeroDossier = '';
  enCours = false;
  dossier: SuiviDossier | null = null;
  erreur = '';

  statutsOrdonnes = ['RECU', 'EN_COURS', 'TERMINE'];

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit() {
    const num = this.route.snapshot.paramMap.get('numero');
    if (num) { this.numeroDossier = num; this.rechercher(); }
  }

  onNumeroDossierChange(valeur: string) {
    this.numeroDossier = valeur;
    if (!valeur.trim()) { this.dossier = null; this.erreur = ''; this.cdr.detectChanges(); }
  }

  rechercher() {
    const numero = this.numeroDossier.trim();
    if (!numero) return;
    this.enCours = true; this.dossier = null; this.erreur = '';
    this.cdr.detectChanges();

    // ✅ CORRECTION : /api/public/suivi/{numero} (avec /public/)
    this.http.get<SuiviDossier>(
      `${environment.apiUrl}/api/public/suivi/${encodeURIComponent(numero)}`
    ).subscribe({
      next: (res) => { this.dossier = res; this.enCours = false; this.cdr.detectChanges(); },
      error: () => {
        this.erreur = 'Aucun dossier trouvé avec ce numéro. Vérifiez et réessayez.';
        this.enCours = false; this.cdr.detectChanges();
      }
    });
  }

  getStatutConfig(statut: string) {
    const configs: Record<string, { label: string; couleur: string; icone: string }> = {
      RECU:      { label: 'Reçu',      couleur: '#95A5A6', icone: '📥' },
      EN_COURS:  { label: 'En cours',  couleur: '#3498DB', icone: '⚙️' },
      TRANSFERE: { label: 'Transféré', couleur: '#E67E22', icone: '🔄' },
      REJETE:    { label: 'Rejeté',    couleur: '#E74C3C', icone: '❌' },
      TERMINE:   { label: 'Terminé',   couleur: '#2ECC71', icone: '✅' },
      ARCHIVE:   { label: 'Archivé',   couleur: '#9B59B6', icone: '📦' }
    };
    return configs[statut] || { label: statut, couleur: '#95A5A6', icone: '❓' };
  }

  getProgressPourcentage(): number {
    if (!this.dossier) return 0;
    if (this.dossier.statut === 'REJETE') return 100;
    const idx = this.statutsOrdonnes.indexOf(this.dossier.statut);
    return idx >= 0 ? Math.round((idx / (this.statutsOrdonnes.length - 1)) * 100) : 100;
  }

  getHistoriqueFiltre(): HistoriqueStatut[] {
    if (!this.dossier?.historique) return [];
    return this.dossier.historique.filter(
      h => h.nouveauStatut === 'EN_COURS' || h.nouveauStatut === 'TERMINE'
    );
  }

  formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('fr-FR', {
      day: '2-digit', month: 'long', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  }
}