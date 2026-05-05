// src/app/modules/agent/dossiers/liste-dossiers.ts
// MODIFICATION : statut ARCHIVE supprimé de la liste des filtres
// Les dossiers archivés sont déjà exclus par le backend (.Where(d => d.Statut.Code != "ARCHIVE"))
import { environment } from '../../../../environments/environment';
import { Component, OnInit, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { DossiersService, DossierListe } from '../../../core/services/dossier';
import { Capacitor } from '@capacitor/core';
import { Browser } from '@capacitor/browser';

@Component({
  selector: 'app-liste-dossiers',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './liste-dossiers.html',
  styleUrls: ['./liste-dossiers.css']
})
export class ListeDossiers implements OnInit {
  private svc    = inject(DossiersService);
  private router = inject(Router);

  dossiers   = signal<DossierListe[]>([]);
  total      = signal(0);
  chargement = signal(false);
  erreur     = signal('');

  filtreStatut    = '';
  filtreRecherche = '';
  filtreDateDebut = '';
  filtreDateFin   = '';

  page        = 1;
  readonly taille = 10;

  // ── ARCHIVE retiré — l'espace Agent ne gère pas les archives ──
  readonly statuts = [
    { code: '',          label: 'Tous les statuts' },
    { code: 'RECU',      label: 'Reçu'             },
    { code: 'EN_COURS',  label: 'En cours'          },
    { code: 'TRANSFERE', label: 'Transféré'         },
    { code: 'REJETE',    label: 'Rejeté'            },
    { code: 'TERMINE',   label: 'Terminé'           }
    // ARCHIVE intentionnellement absent
  ];

  ngOnInit() { this.charger(); }

  charger() {
    this.chargement.set(true);
    this.svc.getMesDossiers(
      this.filtreStatut   || undefined,
      this.filtreRecherche || undefined,
      this.page,
      this.taille,
      undefined,
      this.filtreDateDebut || undefined,
      this.filtreDateFin   || undefined
    ).subscribe({
      next: p => {
        this.dossiers.set(p.dossiers);
        this.total.set(p.total);
        this.chargement.set(false);
      },
      error: () => {
        this.erreur.set('Erreur lors du chargement des dossiers.');
        this.chargement.set(false);
      }
    });
  }

  rechercher()    { this.page = 1; this.charger(); }
  reinitialiser() {
    this.filtreStatut = ''; this.filtreRecherche = '';
    this.filtreDateDebut = ''; this.filtreDateFin = '';
    this.page = 1; this.charger();
  }

  voirDossier(id: string) { this.router.navigate(['/agent/dossiers', id]); }

  get totalPages(): number  { return Math.ceil(this.total() / this.taille); }
  get pages(): number[]     { return Array.from({ length: this.totalPages }, (_, i) => i + 1); }
  allerPage(p: number)      { if (p >= 1 && p <= this.totalPages) { this.page = p; this.charger(); } }

  // ── ARCHIVE retiré du mapping des classes CSS ──
  getClassStatut(code: string): string {
    const map: Record<string, string> = {
      RECU:      'badge-recu',
      EN_COURS:  'badge-encours',
      TRANSFERE: 'badge-transfere',
      REJETE:    'badge-rejete',
      TERMINE:   'badge-termine'
    };
    return map[code] ?? 'badge-default';
  }

  formatDate(d: string): string {
    if (!d) return '—';
    try { return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }); }
    catch { return '—'; }
  }

  joursDepuis(dateStr: string): number {
    return Math.floor((Date.now() - new Date(dateStr).getTime()) / (1000 * 60 * 60 * 24));
  }

  minVal(a: number, b: number): number { return Math.min(a, b); }

  exporterCsv() {
    const token = localStorage.getItem('token');
    if (!token) { this.erreur.set('Session expirée.'); return; }
    if (Capacitor.isNativePlatform()) {
      let url = `${environment.apiUrl}/api/dossiers/export-csv?`;
      if (this.filtreStatut)    url += `statut=${encodeURIComponent(this.filtreStatut)}&`;
      if (this.filtreRecherche) url += `recherche=${encodeURIComponent(this.filtreRecherche)}&`;
      url = url.replace(/&$/, '') + `&token=${encodeURIComponent(token)}`;
      Browser.open({ url, presentationStyle: 'popover' });
    } else {
      this.svc.exportCsv(
        this.filtreStatut    || undefined,
        this.filtreRecherche || undefined,
        undefined,
        this.filtreDateDebut || undefined,
        this.filtreDateFin   || undefined
      ).subscribe({
        next: blob => {
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url; a.download = `dossiers_${Date.now()}.csv`;
          document.body.appendChild(a); a.click();
          document.body.removeChild(a); URL.revokeObjectURL(url);
        },
        error: err => this.erreur.set('Échec export CSV : ' + (err.message || 'erreur'))
      });
    }
  }
}