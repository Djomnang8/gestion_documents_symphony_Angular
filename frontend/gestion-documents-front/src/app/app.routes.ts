// src/app/app.routes.ts
import { Routes } from '@angular/router';
import { RenderMode, ServerRoute } from '@angular/ssr';
import { authGuard, agentGuard } from './core/guards/auth-guard';
import { publicRoutes }  from './modules/public_citoyen/public.routes';
import { authRoutes }    from './modules/auth/auth.routes';
import { roleGuard }     from './core/guards/role.guard';

export const routes: Routes = [
  { path: '', redirectTo: 'public', pathMatch: 'full' },

  { path: 'public', children: publicRoutes },
  { path: 'auth',   children: authRoutes   },

  // Agent — Agent ET Administrateur (agentGuard inclut les deux)
  {
    path: 'agent',
    canActivate: [agentGuard],
    loadChildren: () =>
      import('./modules/agent/agent.routes').then(m => m.agentRoutes)
  },

  // Archiviste — Archiviste ET Administrateur
  {
    path: 'archiviste',
    canActivate: [roleGuard(['Archiviste', 'Administrateur'])],
    loadChildren: () =>
      import('./modules/archiviste/archiviste.routes').then(m => m.archivisteRoutes)
  },

  // Administration — Administrateur uniquement
  {
    path: 'administration',
    canActivate: [roleGuard(['Administrateur'])],
    loadChildren: () =>
      import('./modules/administration/administration.routes')
        .then(m => m.administrationRoutes)
  },

  { path: 'acces-refuse', loadComponent: () =>
      import('./modules/public_citoyen/acces-refuse').then(m => m.AccesRefuseComponent) },

  { path: '**', redirectTo: 'public' }
];

export const serverRouteConfig: ServerRoute[] = [
  { path: '**', renderMode: RenderMode.Client }
];