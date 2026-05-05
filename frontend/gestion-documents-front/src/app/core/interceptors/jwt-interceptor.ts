// src/app/core/interceptors/jwt-interceptor.ts

import { HttpInterceptorFn } from '@angular/common/http';

export const jwtInterceptor: HttpInterceptorFn = (req, next) => {
  // Liste des endpoints publics à exclure
  const publicEndpoints = ['/api/dossiers/suivi', '/api/dossiers/public/depot'];
  const isPublic = publicEndpoints.some(url => req.url.includes(url));
  if (isPublic) {
    return next(req);
  }

  const token = localStorage.getItem('jwt');
  if (token) {
    req = req.clone({
      setHeaders: { Authorization: `Bearer ${token}` }
    });
  }
  return next(req);
};