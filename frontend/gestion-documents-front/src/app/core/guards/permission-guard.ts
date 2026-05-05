/*import { CanActivateFn } from '@angular/router';

export const permissionGuard: CanActivateFn = (route, state) => {
  return true;
};*/

import { CanActivateFn } from '@angular/router';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../services/auth.service'; // à décommenter quand AuthService sera prêt

export const permissionGuard = (permission: string): CanActivateFn => {
  return () => {
    const auth = inject(AuthService);
    if (auth.hasPermission(permission)) return true;
    inject(Router).navigate(['/acces-refuse']);
    return false;
    //return true; // temporaire jusqu'à la Maquette 2
  };
};