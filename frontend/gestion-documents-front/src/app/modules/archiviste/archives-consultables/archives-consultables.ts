// src/app/modules/archiviste/archives-consultables/archives-consultables.ts
// Classement des archives : par service → par email citoyen (nom citoyen)
// Les fichiers d'un même citoyen (même email) sont groupés dans le même dossier.

import { Component, signal, computed, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DossiersService, DossierArchive } from '../../../core/services/dossier';

/** Représente un groupe citoyen dans un service */
interface GroupeCitoyen {
  emailCitoyen: string;
  nomCitoyen:   string;
  archives:     DossierArchive[];
  expanded:     boolean;
}

/** Représente un groupe service */
interface GroupeService {
  nomService:   string;
  citoyens:     GroupeCitoyen[];
  expanded:     boolean;
}

@Component({
  selector: 'app-archives-consultables',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './archives-consultables.html',
  styleUrl: './archives-consultables.css'
})
export class ArchivesConsultables implements OnInit {
  private archService = inject(DossiersService);

  recherche      = signal('');
  filtreAnnee    = signal('');
  filtreService  = signal('');
  vueDetail      = signal<DossierArchive | null>(null);
  chargement     = signal(false);
  erreur         = signal('');

  annees   = ['2026', '2025', '2024', '2023'];
  archives = signal<DossierArchive[]>([]);
  totalArchives = signal(0);
  page = 1;
  size = 100; // charger plus pour avoir un groupement côté client correct

  // ── Groupement : Service → Citoyen → Archives ──────────────────────────
  groupesServices = computed<GroupeService[]>(() => {
    let liste = this.archives();

    // Filtre texte
    const q = this.recherche().toLowerCase();
    if (q) {
      liste = liste.filter(a =>
        a.numero?.toLowerCase().includes(q) ||
        a.citoyen?.toLowerCase().includes(q) ||
        a.titre?.toLowerCase().includes(q)
      );
    }

    // Filtre service local
    const svc = this.filtreService().toLowerCase();
    if (svc) {
      liste = liste.filter(a => a.service?.toLowerCase().includes(svc));
    }

    // Grouper par service
    const byService = new Map<string, DossierArchive[]>();
    for (const arc of liste) {
      const key = arc.service || 'Sans service';
      if (!byService.has(key)) byService.set(key, []);
      byService.get(key)!.push(arc);
    }

    const groupes: GroupeService[] = [];
    for (const [nomService, archivesService] of byService) {
      // Grouper par email citoyen dans ce service
      const byCitoyen = new Map<string, DossierArchive[]>();
      for (const arc of archivesService) {
        const email = arc.emailCitoyen || arc.citoyen || 'Inconnu';
        if (!byCitoyen.has(email)) byCitoyen.set(email, []);
        byCitoyen.get(email)!.push(arc);
      }

      const citoyens: GroupeCitoyen[] = [];
      for (const [email, arcsCitoyen] of byCitoyen) {
        citoyens.push({
          emailCitoyen: email,
          nomCitoyen:   arcsCitoyen[0]?.citoyen || email,
          archives:     arcsCitoyen,
          expanded:     false
        });
      }

      groupes.push({ nomService, citoyens, expanded: true });
    }

    return groupes.sort((a, b) => a.nomService.localeCompare(b.nomService));
  });

  totalFiltrees = computed(() =>
    this.groupesServices().reduce(
      (acc, g) => acc + g.citoyens.reduce((a, c) => a + c.archives.length, 0), 0
    )
  );

  ngOnInit() { this.chargerArchives(); }

  chargerArchives() {
    this.chargement.set(true);
    this.erreur.set('');

    let dateDebut: string | undefined;
    let dateFin:   string | undefined;
    const annee = this.filtreAnnee();
    if (annee) { dateDebut = `${annee}-01-01`; dateFin = `${annee}-12-31`; }

    this.archService.rechercherArchives({
      numero: this.recherche() || undefined,
      dateDebut,
      dateFin,
      page: this.page,
      size: this.size
    }).subscribe({
      next: (res) => {
        const archivesAvecMiniature = res.data.map(arc => ({
          ...arc,
          miniature: this.getMiniature(arc)
        }));
        this.archives.set(archivesAvecMiniature);
        this.totalArchives.set(res.total);
        this.chargement.set(false);
      },
      error: () => {
        this.erreur.set('Erreur de chargement des archives.');
        this.chargement.set(false);
      }
    });
  }

  private getMiniature(archive: DossierArchive): string {
    const service = archive.service?.toLowerCase() || '';
    if (service.includes('direction')) return '🏛️';
    if (service.includes('administratif')) return '📋';
    if (service.includes('technique')) return '⚙️';
    if (service.includes('archives')) return '🗄️';
    if (service.includes('affaires')) return '⚖️';
    if (service.includes('famille')) return '👨‍👩‍👧';
    if (service.includes('pénal') || service.includes('penal')) return '🔒';
    if (service.includes('immobilier')) return '🏠';
    return archive.titre?.charAt(0)?.toUpperCase() || '📄';
  }

  /** Nom de dossier physique attendu : Archive_Service_<NomService> */
  nomDossierService(nomService: string): string {
    return 'Archive_Service_' + nomService.replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_]/g, '');
  }

  setRecherche(v: string) { this.recherche.set(v); }
  setFiltreAnnee(v: string) { this.filtreAnnee.set(v); this.page = 1; this.chargerArchives(); }
  setFiltreService(v: string) { this.filtreService.set(v); }

  toggleService(groupe: GroupeService)   { groupe.expanded = !groupe.expanded; }
  toggleCitoyen(citoyen: GroupeCitoyen)  { citoyen.expanded = !citoyen.expanded; }

  consulterArchive(arc: DossierArchive)  { this.vueDetail.set(arc); }
  fermerDetail()                          { this.vueDetail.set(null); }
}