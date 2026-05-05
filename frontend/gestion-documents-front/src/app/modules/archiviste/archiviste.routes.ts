// src/app/modules/archiviste/archiviste.routes.ts
import { Routes } from '@angular/router';
import { ArchivisteLayout } from './archiviste-layout/archiviste-layout';

export const archivisteRoutes: Routes = [
  {
    path: '',
    component: ArchivisteLayout,
    children: [
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./dashboard-archiviste/dashboard-archiviste')
            .then(m => m.DashboardArchiviste)
      },
      {
        path: 'dossiers',
        loadComponent: () =>
          import('./dossiers-a-archiver/dossiers-a-archiver')
            .then(m => m.DossiersAArchiver)
      },
      {
        path: 'archives',
        loadComponent: () =>
          import('./archives-consultables/archives-consultables')
            .then(m => m.ArchivesConsultables)
      },
      {
        path: 'historique/:dossierId',
        loadComponent: () =>
          import('./historique-versions/historique-versions')
            .then(m => m.HistoriqueVersions)
      },
      {
        path: 'statistiques',
        loadComponent: () =>
          import('./statistiques-archiviste/statistiques-archiviste')
            .then(m => m.StatistiquesArchivistePage)
      },
      {
        path: 'notifications',
        loadComponent: () =>
          import('../agent/notifications/notifications')
            .then(m => m.NotificationsPage)
      },
      // ── Page Profil ──
      {
        path: 'profil',
        loadComponent: () =>
          import('../../modules/profil/profil').then(m => m.ProfilPage)
      }
    ]
  }
];