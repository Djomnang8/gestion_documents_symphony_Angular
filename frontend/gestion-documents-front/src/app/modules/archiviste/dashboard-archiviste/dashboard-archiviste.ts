import { Component, signal, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { DossiersService, DossierAArchiver, ArchivageKpi } from '../../../core/services/dossier';

interface KpiCard {
  titre: string;
  valeur: number | string;
  icone: string;
  couleur: 'violet' | 'or' | 'gris';
  sous_titre: string;
}

@Component({
  selector: 'app-dashboard-archiviste',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './dashboard-archiviste.html',
  styleUrl: './dashboard-archiviste.css'
})
export class DashboardArchiviste implements OnInit {
  private dossierService = inject(DossiersService);

  // Signaux pour les données
  kpis = signal<KpiCard[]>([]);
  dossiersAttente = signal<DossierAArchiver[]>([]);

  // États de chargement / erreur
  loadingKpis = signal(true);
  loadingListe = signal(true);
  errorMessage = signal('');

  // Modale confirmation archivage
  modalOuvert = signal(false);
  dossierSelectionne = signal<DossierAArchiver | null>(null);
  archivageEnCours = signal(false);
  messageSucces = signal('');

  ngOnInit() {
    this.chargerKpis();
    this.chargerListeArchivage();
  }

  chargerKpis() {
    this.loadingKpis.set(true);
    this.dossierService.getArchivageKpi().subscribe({
      next: (data: ArchivageKpi) => {
        this.kpis.set([
          {
            titre: 'Dossiers TERMINÉS en attente d\'archivage',
            valeur: data.aArchiver,
            icone: 'sablier',
            couleur: 'violet',
            sous_titre: 'À traiter en priorité'
          },
          {
            titre: 'Archivés ce mois',
            valeur: data.archivesCeMois,
            icone: 'coffre',
            couleur: 'or',
            sous_titre: `Mois de ${new Date().toLocaleString('fr-FR', { month: 'long', year: 'numeric' })}`
          },
          {
            titre: 'Total Archives',
            valeur: data.totalArchives,
            icone: 'bibliotheque',
            couleur: 'gris',
            sous_titre: 'Depuis l\'ouverture du système'
          }
        ]);
        this.loadingKpis.set(false);
      },
      error: (err: any) => {
        console.error('Erreur chargement KPIs', err);
        this.errorMessage.set('Impossible de charger les indicateurs.');
        this.loadingKpis.set(false);
      }
    });
  }

  chargerListeArchivage() {
    this.loadingListe.set(true);
    this.dossierService.getDossiersAAArchiver().subscribe({
      next: (data: DossierAArchiver[]) => {
        this.dossiersAttente.set(data);
        this.loadingListe.set(false);
      },
      error: (err: any) => {
        console.error('Erreur chargement liste', err);
        this.errorMessage.set('Impossible de charger la liste des dossiers à archiver.');
        this.loadingListe.set(false);
      }
    });
  }

  ouvrirModalArchivage(dossier: DossierAArchiver) {
    this.dossierSelectionne.set(dossier);
    this.modalOuvert.set(true);
    this.messageSucces.set('');
  }

  fermerModal() {
    this.modalOuvert.set(false);
    this.dossierSelectionne.set(null);
  }

  confirmerArchivage() {
    const dossier = this.dossierSelectionne();
    if (!dossier) return;

    this.archivageEnCours.set(true);
    this.dossierService.archiverDossier(dossier.id).subscribe({
      next: () => {
        // Mise à jour locale : retirer le dossier de la liste
        this.dossiersAttente.update(liste => liste.filter(d => d.id !== dossier.id));
        // Recharger les KPIs pour mettre à jour les compteurs
        this.chargerKpis();

        this.archivageEnCours.set(false);
        this.modalOuvert.set(false);
        this.messageSucces.set(`Dossier ${dossier.numero} archivé avec succès.`);
        setTimeout(() => this.messageSucces.set(''), 4000);
      },
      error: (err: any) => {
        console.error('Erreur archivage', err);
        this.archivageEnCours.set(false);
        this.messageSucces.set(`Erreur lors de l'archivage du dossier ${dossier.numero}.`);
        setTimeout(() => this.messageSucces.set(''), 4000);
        this.fermerModal(); // Ferme la modale en cas d'erreur
      }
    });
  }
}