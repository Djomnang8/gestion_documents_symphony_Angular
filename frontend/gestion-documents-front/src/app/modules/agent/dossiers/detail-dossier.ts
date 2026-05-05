// src/app/modules/agent/dossiers/detail-dossier.ts
// VERSION COMPLÈTE : fusion du fichier existant (document index 27)
// + ajout des méthodes manquantes (imprimerFiche, ouvrirFichier, telechargerFichier,
//   iconeFichier, estImage, getClassStatut, formatDate)
// + suppression du statut ARCHIVE dans les transitions

import { Component, OnInit, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, ActivatedRoute } from '@angular/router';
import { ReactiveFormsModule, FormsModule, FormBuilder, Validators } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Capacitor } from '@capacitor/core';
import { Browser } from '@capacitor/browser';
import { DossiersService, DossierDetail } from '../../../core/services/dossier';
import { environment } from '../../../../environments/environment';

@Component({
  selector: 'app-detail-dossier',
  standalone: true,
  imports: [CommonModule, RouterModule, ReactiveFormsModule, FormsModule],
  templateUrl: './detail-dossier.html',
  styleUrls: ['./detail-dossier.css']
})
export class DetailDossier implements OnInit {
  private route = inject(ActivatedRoute);
  private svc   = inject(DossiersService);
  private fb    = inject(FormBuilder);
  private http  = inject(HttpClient);

  dossier = signal<DossierDetail | null>(null);
  chargement = signal(true);
  erreur     = signal('');
  succes     = signal('');
  enCours    = signal(false);
  showModalStatut = signal(false);
  onglet = signal<'info' | 'historique' | 'documents'>('info');

  // Transfert
  serviceCible = signal<number | null>(null);
  servicesDisponibles = signal<any[]>([]);

  formStatut = this.fb.group({
    nouveauStatutCode: ['', Validators.required],
    commentaire: ['']
  });

  // ── Transitions autorisées — ARCHIVE supprimé de l'espace Agent ──
  readonly transitionsAutorisees: Record<string, { code: string; label: string; danger?: boolean; couleur: string }[]> = {
    RECU: [
      { code: 'EN_COURS', label: 'Prendre en charge', couleur: '#E67E22' },
      { code: 'REJETE',   label: 'Rejeter',           danger: true, couleur: '#E74C3C' }
    ],
    EN_COURS: [
      { code: 'TERMINE',  label: 'Marquer Terminé',   couleur: '#27AE60' },
      { code: 'TRANSFERE',label: 'Transférer',         couleur: '#8E44AD' },
      { code: 'REJETE',   label: 'Rejeter',            danger: true, couleur: '#E74C3C' }
    ],
    TRANSFERE: [
    { code: 'EN_COURS', label: 'Reprendre', couleur: '#E67E22' },
    { code: 'TERMINE',  label: 'Marquer Terminé', couleur: '#27AE60' }
],
    REJETE:  [],
    TERMINE: [],
    // Pas d'entrée ARCHIVE — les dossiers archivés n'apparaissent pas dans l'espace Agent
  };

  ngOnInit() {
    const id = this.route.snapshot.paramMap.get('id');
    if (id) this.chargerDossier(id);

    this.http.get<any[]>(`${environment.apiUrl}/api/services`).subscribe({
      next: s => this.servicesDisponibles.set(s),
      error: () => {}
    });
  }

  chargerDossier(id: string) {
    this.chargement.set(true);
    this.svc.getDossier(id).subscribe({
      next: d => { this.dossier.set(d); this.chargement.set(false); },
      error: err => { this.erreur.set(err.error?.message || 'Erreur chargement'); this.chargement.set(false); }
    });
  }

  ouvrirModalStatut(code: string) {
    this.erreur.set('');
    if (code === 'TRANSFERE') this.serviceCible.set(null);
    this.formStatut.patchValue({ nouveauStatutCode: code, commentaire: '' });
    this.showModalStatut.set(true);
  }

  fermerModal() {
    this.showModalStatut.set(false);
    this.formStatut.reset();
    this.serviceCible.set(null);
  }

  get isDanger() { return this.formStatut.value.nouveauStatutCode === 'REJETE'; }

  get labelNouveauStatut(): string {
    const code = this.formStatut.value.nouveauStatutCode as string;
    if (!code) return 'Changer le statut';
    const labels: Record<string, string> = {
      EN_COURS:  'Prendre en charge',
      TERMINE:   'Marquer comme Terminé',
      REJETE:    'Rejeter le dossier',
      TRANSFERE: 'Transférer le dossier'
    };
    return labels[code] || 'Changer le statut';
  }

  confirmerChangementStatut() {
    const dossierId = this.dossier()?.id;
    const code      = this.formStatut.value.nouveauStatutCode;
    const commentaire = this.formStatut.value.commentaire || '';
    if (!dossierId || !code) return;

    if (code === 'REJETE' && !commentaire.trim()) {
      this.erreur.set('Le motif de rejet est obligatoire.');
      return;
    }

    this.enCours.set(true);
    this.erreur.set('');

    if (code === 'TRANSFERE') {
      if (!this.serviceCible()) {
        this.erreur.set('Veuillez choisir un service de destination.');
        this.enCours.set(false);
        return;
      }
      this.svc.transfererDossier(dossierId, this.serviceCible()!, commentaire).subscribe({
        next: () => this.finaliserAction('Dossier transféré avec succès'),
        error: err => { this.erreur.set(err.error?.message || 'Erreur transfert'); this.enCours.set(false); }
      });
    } else {
      this.svc.changerStatut(dossierId, code, commentaire).subscribe({
        next: () => this.finaliserAction('Statut mis à jour'),
        error: err => { this.erreur.set(err.error?.message || 'Erreur mise à jour'); this.enCours.set(false); }
      });
    }
  }

  private finaliserAction(message: string) {
    this.succes.set(message);
    this.enCours.set(false);
    this.showModalStatut.set(false);
    this.chargerDossier(this.dossier()!.id);
    setTimeout(() => this.succes.set(''), 4000);
  }

  get transitions() {
    const code = this.dossier()?.statutCode ?? '';
    return this.transitionsAutorisees[code] ?? [];
  }

  // ── MÉTHODES MANQUANTES (ne figuraient pas dans le fichier existant) ──

  /** Imprimer la fiche du dossier */
  imprimerFiche(): void {
    const ongletActuel = this.onglet();
    this.onglet.set('info');
    setTimeout(() => {
      window.print();
      setTimeout(() => this.onglet.set(ongletActuel), 500);
    }, 100);
  }

  /** Ouvrir un fichier dans un nouvel onglet */
  ouvrirFichier(chemin: string): void {
    if (!chemin) return;
    const url = this.getFileUrl(chemin);
    if (Capacitor.isNativePlatform()) {
      Browser.open({ url, presentationStyle: 'popover' }).catch(() =>
        alert('Impossible d\'afficher le fichier.'));
    } else {
      this.http.get(url, { responseType: 'blob' }).subscribe({
        next: blob => {
          const blobUrl = window.URL.createObjectURL(blob);
          window.open(blobUrl, '_blank');
          setTimeout(() => window.URL.revokeObjectURL(blobUrl), 1000);
        },
        error: () => alert('Impossible d\'afficher le fichier.')
      });
    }
  }

  /** Télécharger un fichier */
  telechargerFichier(chemin: string, nomFichier: string): void {
    if (!chemin) return;
    const url = this.getFileUrl(chemin);
    if (Capacitor.isNativePlatform()) {
      Browser.open({ url, presentationStyle: 'popover' });
    } else {
      this.http.get(url, { responseType: 'blob' }).subscribe({
        next: blob => {
          const blobUrl = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = blobUrl; a.download = nomFichier;
          document.body.appendChild(a); a.click();
          document.body.removeChild(a);
          window.URL.revokeObjectURL(blobUrl);
        },
        error: () => alert('Impossible de télécharger le fichier.')
      });
    }
  }

  private getFileUrl(chemin: string): string {
    const token = localStorage.getItem('jwt') ?? localStorage.getItem('token') ?? '';
    const base = `${environment.apiUrl}/api/fichiers/download?chemin=${encodeURIComponent(chemin)}`;
    return token ? `${base}&token=${encodeURIComponent(token)}` : base;
  }

  /** Icône emoji selon l'extension */
  iconeFichier(nomFichier: string): string {
    const ext = nomFichier.split('.').pop()?.toLowerCase() ?? '';
    if (ext === 'pdf') return '📕';
    if (['doc', 'docx'].includes(ext)) return '📘';
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return '🖼';
    if (['xls', 'xlsx'].includes(ext)) return '📗';
    return '📄';
  }

  estImage(nomFichier: string): boolean {
    const ext = nomFichier.split('.').pop()?.toLowerCase() ?? '';
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
  }

  getClassStatut(code: string): string {
    const map: Record<string, string> = {
      RECU:      'badge-recu',
      EN_COURS:  'badge-encours',
      TRANSFERE: 'badge-transfere',
      REJETE:    'badge-rejete',
      TERMINE:   'badge-termine'
      // ARCHIVE intentionnellement absent de l'espace Agent
    };
    return map[code] ?? 'badge-default';
  }

  formatDate(d: string): string {
    return new Date(d).toLocaleDateString('fr-FR', {
      day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
  }

  formatTaille(bytes: number): string {
    if (!bytes || bytes === 0) return '—';
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
  }
}