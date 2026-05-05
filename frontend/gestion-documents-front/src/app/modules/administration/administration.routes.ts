// src/app/modules/administration/administration.routes.ts
import { Routes } from '@angular/router';


export const administrationRoutes: Routes = [
  {
    path: '',
    loadComponent: () =>
      import('./admin-layout/admin-layout').then(m => m.AdminLayout),
    children: [
      {
        path: '',
        redirectTo: 'dashboard',
        pathMatch: 'full'
      },
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./dashboard-admin/dashboard-admin').then(m => m.DashboardAdminComponent)
      },
      {
        path: 'utilisateurs',
        loadComponent: () =>
          import('./gestion-utilisateurs/gestion-utilisateurs').then(m => m.GestionUtilisateursComponent)
      },
      {
        path: 'journaux',
        loadComponent: () =>
          import('./journaux-systeme/journaux-systeme').then(m => m.JournauxSysteme)
      },
      /*{
        path: 'roles',
        loadComponent: () =>
          import('./roles-permissions/roles-permissions').then(m => m.RolesPermissionsComponent)
      },*/{
      
        path: 'profil',
        loadComponent: () =>
          import('../../modules/profil/profil').then(m => m.ProfilPage)
      }
    ]
  }
];