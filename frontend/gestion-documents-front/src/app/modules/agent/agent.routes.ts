// src/app/modules/agent/agent.routes.ts
import { Routes } from '@angular/router';
import { AgentLayout } from './agent-layout/agent-layout';

export const agentRoutes: Routes = [
  {
    path: '',
    component: AgentLayout,
    children: [
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./dashboard/dashboard').then(m => m.Dashboard)
      },
      {
        path: 'dossiers',
        loadComponent: () =>
          import('./dossiers/liste-dossiers').then(m => m.ListeDossiers)
      },
      {
        path: 'dossiers/nouveau',
        loadComponent: () =>
          import('./dossiers/nouveau-dossier').then(m => m.NouveauDossier)
      },
      {
        path: 'dossiers/:id',
        loadComponent: () =>
          import('./dossiers/detail-dossier').then(m => m.DetailDossier)
      },
      {
        path: 'statistiques',
        loadComponent: () =>
          import('./statistiques/statistiques').then(m => m.StatistiquesComponent)
      },
      {
        path: 'notifications',
        loadComponent: () =>
          import('./notifications/notifications').then(m => m.NotificationsPage)
      },
      // ── Page Profil (accessible depuis le user-bloc) ──
      {
        path: 'profil',
        loadComponent: () =>
          import('../../modules/profil/profil').then(m => m.ProfilPage)
      }
    ]
  }
];