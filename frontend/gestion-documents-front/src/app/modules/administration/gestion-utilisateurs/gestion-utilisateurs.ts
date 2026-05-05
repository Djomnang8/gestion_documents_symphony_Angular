// src/app/modules/administration/gestion-utilisateurs/gestion-utilisateurs.ts
import { Component, OnInit, inject, signal, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  UtilisateurService, UtilisateurListe,
  CreateUtilisateurDto, RoleDto, ServiceDto
} from '../../../core/services/utilisateur';

@Component({
  selector: 'app-gestion-utilisateurs',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './gestion-utilisateurs.html',
  styleUrl: './gestion-utilisateurs.css'
})
export class GestionUtilisateursComponent implements OnInit {
  private svc = inject(UtilisateurService);
  private cdr = inject(ChangeDetectorRef);

  utilisateurs = signal<UtilisateurListe[]>([]);
  roles        = signal<RoleDto[]>([]);
  services     = signal<ServiceDto[]>([]);

  filtreStatut: 'actif' | 'inactif' | 'listenoire' | '' = '';
  loading      = false;
  message      = '';
  messageType  = 'success';

  showModal     = false;
  nouveau: CreateUtilisateurDto = this.initNouveau();

  showListeNoireModal = false;
  motifListeNoire     = '';
  idEnCours: string | null = null;

  ngOnInit() {
    this.charger();
    this.svc.getRoles().subscribe(r => {
      this.roles.set(r);
      this.cdr.markForCheck();
    });
    this.svc.getServices().subscribe(s => {
      this.services.set(s);
      this.cdr.markForCheck();
    });
  }

  charger() {
    this.loading = true;
    const statut = this.filtreStatut === '' ? undefined : this.filtreStatut;
    this.svc.getAll(statut).subscribe({
      next: d => {
        this.utilisateurs.set(d);
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.loading = false;
        this.cdr.markForCheck();
      }
    });
  }

  activer(id: string) {
    this.svc.activer(id).subscribe({
      next: () => {
        this.notify('Compte activé.', 'success');
        this.charger();
      },
      error: () => this.notify('Erreur lors de l\'activation.', 'error')
    });
  }

  desactiver(id: string) {
    this.svc.desactiver(id).subscribe({
      next: () => {
        this.notify('Compte désactivé.', 'success');
        this.charger();
      },
      error: () => this.notify('Erreur lors de la désactivation.', 'error')
    });
  }

  ouvrirListeNoire(id: string) {
    this.idEnCours = id;
    this.motifListeNoire = '';
    this.showListeNoireModal = true;
    this.cdr.markForCheck();
  }

  confirmerListeNoire() {
    if (!this.idEnCours || !this.motifListeNoire.trim()) return;
    this.svc.mettreListeNoire(this.idEnCours, this.motifListeNoire).subscribe({
      next: () => {
        this.showListeNoireModal = false;
        this.notify('Utilisateur mis en liste noire.', 'success');
        this.charger();
        this.cdr.markForCheck();
      },
      error: () => this.notify('Erreur.', 'error')
    });
  }

  creer() {
    this.svc.create(this.nouveau).subscribe({
      next: () => {
        this.showModal = false;
        this.nouveau = this.initNouveau();
        this.notify('Utilisateur créé avec succès.', 'success');
        this.charger();
        this.cdr.markForCheck();
      },
      error: (e) => this.notify(e?.error?.message ?? 'Erreur lors de la création.', 'error')
    });
  }

  private initNouveau(): CreateUtilisateurDto {
    return {
      nom: '',
      prenom: '',
      email: '',
      telephone: '',
      motDePasse: '',
      role: '',
      serviceId: undefined
    };
  }

  private notify(msg: string, type: string) {
    this.message = msg;
    this.messageType = type;
    setTimeout(() => {
      this.message = '';
      this.cdr.markForCheck();
    }, 4000);
    this.cdr.markForCheck();
  }

  statutLabel(u: UtilisateurListe): string {
    if (u.estListeNoire) return 'Liste noire';
    return u.estActif ? 'Actif' : 'Inactif';
  }

  statutClass(u: UtilisateurListe): string {
    if (u.estListeNoire) return 'chip-listenoire';
    return u.estActif ? 'chip-actif' : 'chip-inactif';
  }
}