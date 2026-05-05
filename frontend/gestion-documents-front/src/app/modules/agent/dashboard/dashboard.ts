// src/app/modules/agent/dashboard/dashboard.ts
import { Component, OnInit, OnDestroy, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { DossiersService, StatsDossiers, DossierEnRetard } from '../../../core/services/dossier';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './dashboard.html',
  styleUrls: ['./dashboard.css']
})
export class Dashboard implements OnInit, OnDestroy {
  private svc    = inject(DossiersService);
  private router = inject(Router);
  auth           = inject(AuthService);   // ← injecté correctement
  private timer: any;

  stats           = signal<StatsDossiers | null>(null);
  dossiersRecents = signal<any[]>([]);
  chargement      = signal(true);
  erreur          = signal('');

  // Modal dossiers en retard
  showRetard       = signal(false);
  dossiersEnRetard = signal<DossierEnRetard[]>([]);
  chargementRetard = signal(false);

  heure = '';
  today = '';

  ngOnInit() {
    this.mettreAJourHeure();
    this.timer = setInterval(() => this.mettreAJourHeure(), 1000);
    this.charger();
  }

  ngOnDestroy() { if (this.timer) clearInterval(this.timer); }

  mettreAJourHeure() {
    const now  = new Date();
    this.heure = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    this.today = now.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    this.today = this.today.charAt(0).toUpperCase() + this.today.slice(1);
  }

  charger() {
    this.chargement.set(true);
    this.svc.getStats().subscribe({
      next:  s => { this.stats.set(s); this.chargement.set(false); },
      error: () => { this.erreur.set('Erreur de chargement.'); this.chargement.set(false); }
    });

    // Exclure les dossiers ARCHIVÉS dès le chargement
    this.svc.getMesDossiers(undefined, undefined, 1, 10).subscribe({
      next:  p => {
        // Filtrer côté front au cas où le back renverrait quand même des ARCHIVE
        const filtres = p.dossiers.filter((d: any) => d.statutCode !== 'ARCHIVE');
        this.dossiersRecents.set(filtres);
      },
      error: () => {}
    });
  }

  ouvrirEnRetard() {
    this.showRetard.set(true);
    this.chargementRetard.set(true);
    this.svc.getEnRetard().subscribe({
      next:  d => { this.dossiersEnRetard.set(d); this.chargementRetard.set(false); },
      error: () => { this.chargementRetard.set(false); }
    });
  }

  fermerRetard() { this.showRetard.set(false); }

  voirDossier(id: string) { this.router.navigate(['/agent/dossiers', id]); }

  /** Seuls les statuts visibles côté Agent (pas ARCHIVE) */
  getClassStatut(code: string): string {
    const map: Record<string, string> = {
      RECU:      'badge-recu',
      EN_COURS:  'badge-encours',
      TRANSFERE: 'badge-transfere',
      REJETE:    'badge-rejete',
      TERMINE:   'badge-termine'
      // ARCHIVE délibérément absent
    };
    return map[code] ?? 'badge-default';
  }

  formatDate(d: string): string {
    return new Date(d).toLocaleDateString('fr-FR', { day:'2-digit', month:'short', year:'numeric' });
  }

  joursDepuis(dateStr: string): number {
    return Math.floor((Date.now() - new Date(dateStr).getTime()) / (1000 * 60 * 60 * 24));
  }

  get prenomAgent(): string {
    return this.auth.user?.()?.prenom ?? 'Agent';
  }
}