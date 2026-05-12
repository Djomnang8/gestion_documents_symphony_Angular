// src/app/modules/archiviste/dossiers-a-archiver/dossiers-a-archiver.ts
import { Component, signal, computed, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ArchivageService, DossierAArchiver, ServiceOption } from '../../../core/services/archivage.service';
import { finalize } from 'rxjs';

@Component({
  selector: 'app-dossiers-a-archiver',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './dossiers-a-archiver.html',
  styleUrl: './dossiers-a-archiver.css'
})
export class DossiersAArchiver implements OnInit {
  private archService = inject(ArchivageService);

  recherche = signal('');
  filtreService = signal('');
  modalOuvert = signal(false);
  dossierSelectionne = signal<DossierAArchiver | null>(null);
  archivageEnCours = signal(false);
  messageSucces = signal('');
  chargement = signal(false);
  erreur = signal('');

  services = signal<ServiceOption[]>([]);

  tousLesDossiers = signal<DossierAArchiver[]>([]);

  ngOnInit() {
    this.chargerServices();
    this.chargerDossiers();
  }

  chargerServices() {
    this.archService.getServices().subscribe({
      next: (services) => this.services.set(services),
      error: () => this.erreur.set('Impossible de charger la liste des services.')
    });
  }

  chargerDossiers() {
    this.chargement.set(true);
    this.archService.getDossiersAArchiver().subscribe({
      next: (data) => {
        this.tousLesDossiers.set(data);
        this.chargement.set(false);
      },
      error: (err) => {
        this.erreur.set('Erreur de chargement : ' + err.message);
        this.chargement.set(false);
      }
    });
  }

  dossiersFiltres = computed(() => {
    const q = this.recherche().toLowerCase();
    const s = this.filtreService();
    return this.tousLesDossiers().filter(d => {
      const matchQ = !q || d.numero.toLowerCase().includes(q) || d.citoyen.toLowerCase().includes(q) || d.titre.toLowerCase().includes(q);
      const matchS = !s || d.service === s;
      return matchQ && matchS;
    });
  });

  setRecherche(v: string) { this.recherche.set(v); }
  setFiltreService(v: string) { this.filtreService.set(v); }

  ouvrirModal(dossier: DossierAArchiver) {
    this.dossierSelectionne.set(dossier);
    this.modalOuvert.set(true);
  }

  fermerModal() {
    this.modalOuvert.set(false);
    this.dossierSelectionne.set(null);
  }

  confirmerArchivage() {
    const d = this.dossierSelectionne();
    if (!d) return;
    this.archivageEnCours.set(true);
    this.archService.archiverDossier(d.id)
      .pipe(finalize(() => this.archivageEnCours.set(false)))
      .subscribe({
        next: () => {
          this.tousLesDossiers.update(liste => liste.filter(x => x.id !== d.id));
          this.modalOuvert.set(false);
          this.messageSucces.set(`Dossier ${d.numero} archivé avec succès.`);
          setTimeout(() => this.messageSucces.set(''), 4000);
        },
        error: (err) => {
          this.erreur.set(`Erreur lors de l'archivage : ${err.error?.error || err.message}`);
        }
      });
  }
}