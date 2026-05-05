import { Routes } from '@angular/router';

export const publicRoutes: Routes = [
  { path: '', redirectTo: 'accueil', pathMatch: 'full' },
  {
    path: 'accueil',
    loadComponent: () =>
      import('./accueil/accueil').then(m => m.Accueil)
  },
  {
    path: 'depot',
    loadComponent: () =>
      import('./depot/depot').then(m => m.Depot)
  },
  {
    path: 'suivi',
    loadComponent: () =>
      import('./suivi/suivi').then(m => m.Suivi)
  }
];