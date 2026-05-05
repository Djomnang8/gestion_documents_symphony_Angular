// src/app/core/guards/role.guard.ts
import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const roleGuard = (allowedRoles: string[]): CanActivateFn => {
  return () => {
    const auth = inject(AuthService);
    const router = inject(Router);

    if (!auth.isLoggedIn()) {
      router.navigate(['/auth/login']);
      return false;
    }

    const userRole = auth.user()?.role;
    if (userRole && allowedRoles.includes(userRole)) {
      return true;
    }

    // Si connecté mais rôle non autorisé
    router.navigate(['/acces-refuse']);
    return false;
  };
};