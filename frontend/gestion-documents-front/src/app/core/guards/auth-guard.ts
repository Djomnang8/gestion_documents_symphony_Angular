// src/app/core/guards/auth-guard.ts
import { inject }       from '@angular/core';
import { Router }       from '@angular/router';
import { CanActivateFn } from '@angular/router';
import { AuthService }  from '../services/auth.service';

export const authGuard: CanActivateFn = () => {
  const auth   = inject(AuthService);
  const router = inject(Router);
  if (auth.estConnecte()) return true;
  router.navigate(['/auth/login']);
  return false;
};

/**
 * agentGuard : Agent + Administrateur
 * (l'Administrateur peut accéder à l'espace Agent)
 */
export const agentGuard: CanActivateFn = () => {
  const auth   = inject(AuthService);
  const router = inject(Router);
  if (!auth.estConnecte()) { router.navigate(['/auth/login']); return false; }
  const role = auth.user?.()?.role ?? '';
  const ok   = ['Agent', 'Administrateur'].includes(role);
  if (!ok) router.navigate(['/acces-refuse']);
  return ok;
};