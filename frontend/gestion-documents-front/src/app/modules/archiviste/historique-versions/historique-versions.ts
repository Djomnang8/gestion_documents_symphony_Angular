// src/app/modules/archiviste/historique-versions/historique-versions.ts
import { Component, signal, computed, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DossiersService, DossierArchive, VersionDocument } from '../../../core/services/dossier';

interface DossierAvecVersions {
  id: string;
  numero: string;
  titre: string;
  citoyen: string;
  versions: VersionDocument[];
}

@Component({
  selector: 'app-historique-versions',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './historique-versions.html',
  styleUrl: './historique-versions.css'
})
export class HistoriqueVersions implements OnInit {
  private dossierService = inject(DossiersService);

  recherche = signal('');
  pageCourante = signal(1);
  taillePage = 10;
  totalDossiers = signal(0);

  dossierSelectionne = signal<DossierAvecVersions | null>(null);
  comparaisonActive = signal(false);
  versionGauche = signal<VersionDocument | null>(null);
  versionDroite = signal<VersionDocument | null>(null);
  modalRestauration = signal(false);
  versionARestaurer = signal<VersionDocument | null>(null);
  restaurationEnCours = signal(false);
  messageSucces = signal('');
  chargement = signal(false);
  erreur = signal('');

  dossiers = signal<DossierAvecVersions[]>([]);

  ngOnInit() {
    this.chargerDossiers();
  }

  chargerDossiers() {
    this.chargement.set(true);
    this.erreur.set('');
    this.dossierService.rechercherArchives({ size: 100 }).subscribe({
      next: (result) => {
        const dossiers: DossierAvecVersions[] = result.data.map(arch => ({
          id: arch.id,
          numero: arch.numero,
          titre: arch.titre,
          citoyen: arch.citoyen,
          versions: []
        }));
        this.dossiers.set(dossiers);
        this.totalDossiers.set(result.total);
        this.chargement.set(false);
      },
      error: () => {
        this.erreur.set('Erreur de chargement des dossiers archivés.');
        this.chargement.set(false);
      }
    });
  }

  dossiersFiltres = computed(() => {
    const q = this.recherche().toLowerCase();
    return this.dossiers().filter(d =>
      d.numero.toLowerCase().includes(q) ||
      d.titre.toLowerCase().includes(q) ||
      d.citoyen.toLowerCase().includes(q)
    );
  });

  dossiersPagination = computed(() => {
    const start = (this.pageCourante() - 1) * this.taillePage;
    return this.dossiersFiltres().slice(start, start + this.taillePage);
  });

  totalPages = computed(() => Math.ceil(this.dossiersFiltres().length / this.taillePage));

  changerPage(page: number) { this.pageCourante.set(page); }

  setRecherche(v: string) {
    this.recherche.set(v);
    this.pageCourante.set(1);
  }

  selectionnerDossier(d: DossierAvecVersions) {
    this.chargement.set(true);
    this.erreur.set('');
    this.dossierService.getVersions(d.id).subscribe({
      next: (versions) => {
        d.versions = versions;
        this.dossierSelectionne.set({ ...d });
        this.comparaisonActive.set(false);
        this.versionGauche.set(null);
        this.versionDroite.set(null);
        this.chargement.set(false);
      },
      error: () => {
        this.erreur.set('Erreur lors du chargement des versions. Vérifiez que la migration SQL a été exécutée.');
        this.chargement.set(false);
      }
    });
  }

  retourListe() {
    this.dossierSelectionne.set(null);
    this.comparaisonActive.set(false);
    this.erreur.set('');
  }

  activerComparaison() {
    const d = this.dossierSelectionne();
    if (!d || d.versions.length < 2) return;
    this.comparaisonActive.set(true);
    this.versionGauche.set(d.versions[0]);
    this.versionDroite.set(d.versions[d.versions.length - 1]);
  }

  setVersionGauche(v: VersionDocument) { this.versionGauche.set(v); }
  setVersionDroite(v: VersionDocument) { this.versionDroite.set(v); }

  ouvrirModalRestauration(v: VersionDocument) {
    this.versionARestaurer.set(v);
    this.modalRestauration.set(true);
  }

  fermerModal() {
    this.modalRestauration.set(false);
    this.versionARestaurer.set(null);
  }

  confirmerRestauration() {
    const v = this.versionARestaurer();
    const d = this.dossierSelectionne();
    if (!v || !d) return;

    this.restaurationEnCours.set(true);
    this.dossierService.restaurerVersion(v.id).subscribe({
      next: () => {
        const updated = { ...d };
        updated.versions = updated.versions.map(ver => ({
          ...ver,
          estActive: ver.id === v.id
        }));
        this.dossierSelectionne.set(updated);
        this.modalRestauration.set(false);
        this.messageSucces.set(`Version ${v.numero} restaurée avec succès.`);
        setTimeout(() => this.messageSucces.set(''), 4000);
        this.restaurationEnCours.set(false);
      },
      error: () => {
        this.erreur.set('Erreur lors de la restauration. Veuillez réessayer.');
        this.restaurationEnCours.set(false);
      }
    });
  }

  formatTaille(bytes: number): string {
    if (!bytes || bytes === 0) return '—';
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
  }
}