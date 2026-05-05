// src/app/modules/agent/notifications/notifications.ts
import { Component, OnInit, OnDestroy, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import {
  NotificationService, NotifSysteme, EmailTransactionnel
} from '../../../core/services/notification.service';

type Onglet = 'toutes' | 'non-lues' | 'rappels' | 'emails';

@Component({
  selector: 'app-notifications',
  standalone: true,
  imports: [CommonModule, RouterModule, DatePipe],
  templateUrl: './notifications.html',
  styleUrl: './notifications.css'
})
export class NotificationsPage implements OnInit, OnDestroy {
  private svc    = inject(NotificationService);
  private router = inject(Router);
  private cdr    = inject(ChangeDetectorRef);

  onglet: Onglet = 'toutes';
  notifs:  NotifSysteme[]        = [];
  emails:  EmailTransactionnel[] = [];
  total   = 0;
  nonLues = 0;
  rappels = 0;
  chargement  = true;
  erreur = '';
  enCours = new Set<number>();
  private pollTimer: any;

  ngOnInit() {
    this.charger();
    this.pollTimer = setInterval(() => {
      if (this.onglet !== 'emails') this.charger(true);
    }, 30000);
  }

  ngOnDestroy() { clearInterval(this.pollTimer); }

  charger(silencieux = false) {
    if (!silencieux) this.chargement = true;
    this.erreur = '';

    if (this.onglet === 'emails') {
      this.svc.getEmails().subscribe({
        next: e => {
          this.emails = e;
          this.chargement = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.emails = this.emailsDemo();
          this.chargement = false;
          this.cdr.markForCheck();
        }
      });
      return;
    }

    this.svc.getAll(this.onglet as 'toutes' | 'non-lues' | 'rappels').subscribe({
      next: r => {
        this.notifs  = r.data;
        this.total   = r.total;
        this.nonLues = r.nonLues;
        this.rappels = r.rappels;
        this.chargement = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.notifs  = this.notifsDemo();
        this.total   = this.notifs.length;
        this.nonLues = this.notifs.filter(n => !n.estLue).length;
        this.rappels = this.notifs.filter(n => n.type === 'RAPPEL').length;
        this.chargement = false;
        this.cdr.markForCheck();
      }
    });
  }

  changerOnglet(o: Onglet) {
    this.onglet = o;
    this.charger();
    this.cdr.markForCheck();
  }

  marquerLue(n: NotifSysteme) {
    if (n.estLue || this.enCours.has(n.id)) return;
    this.enCours.add(n.id);
    this.svc.marquerLue(n.id).subscribe({
      next: () => {
        n.estLue = true;
        this.nonLues = Math.max(0, this.nonLues - 1);
        this.enCours.delete(n.id);
        this.cdr.markForCheck();
      },
      error: () => {
        n.estLue = true;
        this.nonLues = Math.max(0, this.nonLues - 1);
        this.enCours.delete(n.id);
        this.cdr.markForCheck();
      }
    });
  }

  marquerToutesLues() {
    this.svc.marquerToutesLues().subscribe({
      next: () => {
        this.notifs.forEach(n => n.estLue = true);
        this.nonLues = 0;
        this.cdr.markForCheck();
      },
      error: () => {
        this.notifs.forEach(n => n.estLue = true);
        this.nonLues = 0;
        this.cdr.markForCheck();
      }
    });
  }

  supprimer(n: NotifSysteme) {
    this.svc.supprimer(n.id).subscribe({
      next: () => {
        this.notifs = this.notifs.filter(x => x.id !== n.id);
        this.cdr.markForCheck();
      },
      error: () => {
        this.notifs = this.notifs.filter(x => x.id !== n.id);
        this.cdr.markForCheck();
      }
    });
  }

  voirDossier(n: NotifSysteme) {
    if (n.dossierId) this.router.navigate(['/agent/dossiers', n.dossierId]);
  }

  reessayer(e: EmailTransactionnel) {
    this.svc.reessayerEmail(e.id).subscribe({
      next: () => {
        e.statut = 'EN_ATTENTE';
        this.cdr.markForCheck();
      },
      error: () => {
        e.statut = 'EN_ATTENTE';
        this.cdr.markForCheck();
      }
    });
  }

  getTypeConfig(type: string): { bordure: string; fond: string; icone: string; couleur: string } {
    const map: Record<string, { bordure: string; fond: string; icone: string; couleur: string }> = {
      STATUT:  { bordure: '#1976D2', fond: '#E3F2FD', icone: '↻', couleur: '#1976D2' },
      REJETE:  { bordure: '#C62828', fond: '#FFEBEE', icone: '✕', couleur: '#C62828' },
      RAPPEL:  { bordure: '#FF8F00', fond: '#FFF8E1', icone: '🔔', couleur: '#FF8F00' },
      TERMINE: { bordure: '#00897B', fond: '#E0F2F1', icone: '✓', couleur: '#00897B' },
      INFO:    { bordure: '#546E7A', fond: '#ECEFF1', icone: 'ℹ', couleur: '#546E7A' },
    };
    return map[type] ?? map['INFO'];
  }

  tempsRelatif(dateStr: string): string {
    const diff = Date.now() - new Date(dateStr).getTime();
    const min  = Math.floor(diff / 60000);
    if (min < 1)   return 'À l\'instant';
    if (min < 60)  return `il y a ${min} min`;
    const h = Math.floor(min / 60);
    if (h < 24)    return `il y a ${h}h`;
    const j = Math.floor(h / 24);
    return `il y a ${j} jour${j > 1 ? 's' : ''}`;
  }

  getEmailStatutCls(s: string): string {
    if (s === 'ENVOYE')    return 'email-envoye';
    if (s === 'EN_ATTENTE') return 'email-attente';
    return 'email-echec';
  }

  getEmailStatutLabel(s: string): string {
    return s === 'ENVOYE' ? 'Envoyé' : s === 'EN_ATTENTE' ? 'En attente' : 'Échec';
  }

  get notifsAffiches(): NotifSysteme[] {
    if (this.onglet === 'non-lues') return this.notifs.filter(n => !n.estLue);
    if (this.onglet === 'rappels')  return this.notifs.filter(n => n.type === 'RAPPEL');
    return this.notifs;
  }

  private notifsDemo(): NotifSysteme[] {
    const now = new Date();
    const dt  = (h: number) => new Date(now.getTime() - h * 3600000).toISOString();
    return [
      { id:1, titre:'Dossier DOS-2026-00045 terminé',      description:'Votre dossier a été traité avec succès.',             type:'TERMINE', dossierId:'abc1', numeroDossier:'DOS-2026-00045', estLue:false, dateCreation:dt(0.3) },
      { id:2, titre:'Dossier DOS-2026-00040 rejeté',       description:'Motif : Documents incomplets. Veuillez re-soumettre.', type:'REJETE',  dossierId:'abc2', numeroDossier:'DOS-2026-00040', estLue:false, dateCreation:dt(2)   },
      { id:3, titre:'Rappel : dossier en attente',         description:'Le dossier DOS-2026-00038 attend une action depuis 5 jours.',        type:'RAPPEL',  dossierId:'abc3', numeroDossier:'DOS-2026-00038', estLue:false, dateCreation:dt(5)   },
      { id:4, titre:'Changement de statut',                description:'DOS-2026-00036 est passé à EN_COURS.',                type:'STATUT',  dossierId:'abc4', numeroDossier:'DOS-2026-00036', estLue:true,  dateCreation:dt(24)  },
      { id:5, titre:'Nouveau dossier reçu',                description:'DOS-2026-00048 a été déposé dans votre service.',     type:'INFO',    dossierId:'abc5', numeroDossier:'DOS-2026-00048', estLue:true,  dateCreation:dt(48)  },
      { id:6, titre:'Rappel : dossier expirant bientôt',   description:'DOS-2026-00032 expire dans 2 jours.',                 type:'RAPPEL',  dossierId:'abc6', numeroDossier:'DOS-2026-00032', estLue:false, dateCreation:dt(72)  },
    ];
  }

  private emailsDemo(): EmailTransactionnel[] {
    const now = new Date();
    const dt  = (h: number) => new Date(now.getTime() - h * 3600000).toISOString();
    return [
      { id:1, destinataire:'citoyen1@mail.cm', objet:'Votre dossier DOS-2026-00045 est terminé',   type:'TERMINE',  statut:'ENVOYE',     dateEnvoi:dt(0.5), tentatives:1  },
      { id:2, destinataire:'citoyen2@mail.cm', objet:'Dossier DOS-2026-00040 rejeté',              type:'REJETE',   statut:'ENVOYE',     dateEnvoi:dt(2.2), tentatives:1  },
      { id:3, destinataire:'citoyen3@mail.cm', objet:'Rappel automatique — dossier en attente',    type:'RAPPEL',   statut:'ECHEC',      dateEnvoi:dt(5),   tentatives:3, erreur:'SMTP timeout' },
      { id:4, destinataire:'citoyen4@mail.cm', objet:'Confirmation de dépôt de dossier',           type:'DEPOT',    statut:'EN_ATTENTE', dateEnvoi:dt(8),   tentatives:0  },
      { id:5, destinataire:'citoyen5@mail.cm', objet:'Votre dossier DOS-2026-00036 est en cours',  type:'STATUT',   statut:'ENVOYE',     dateEnvoi:dt(26),  tentatives:1  },
    ];
  }
}