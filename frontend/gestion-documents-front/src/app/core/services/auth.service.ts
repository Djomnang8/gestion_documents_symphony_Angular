import { Injectable, signal, computed, PLATFORM_ID, inject } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { tap } from 'rxjs/operators';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface UserInfo {
  id: number;
  nom: string;
  prenom: string;
  role: string;
  permissions: string[];
  email: string;
  serviceId?: number | null;
  serviceNom?: string;
}

export interface LoginResponse {
  token: string;
  user: UserInfo;
}

export interface LoginRequest {
  email: string;
  motDePasse: string;
}

@Injectable({ providedIn: 'root' })
export class AuthService {

  private platformId = inject(PLATFORM_ID);
  private isBrowser = isPlatformBrowser(this.platformId);

  private _user = signal<UserInfo | null>(null);
  readonly user = this._user.asReadonly();
  readonly isLoggedIn = computed(() => this._user() !== null);

  constructor(private http: HttpClient, private router: Router) {
    // localStorage n'existe que dans le navigateur, pas en SSR
    if (this.isBrowser) {
      const token = localStorage.getItem('token');
      if (token) {
        try {
          const payload = this.decodeJwt(token);
          this._user.set(payload);
        } catch {
          localStorage.removeItem('token');
        }
      }
    }
  }

  /*login(email: string, motDePasse: string): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(
      `${environment.apiUrl}/api/auth/login`,
      { email, motDePasse } as LoginRequest
    ).pipe(
      tap((res: LoginResponse) => {
        if (this.isBrowser) {
          localStorage.setItem('token', res.token);
        }
        this._user.set(res.user);
      })
    );
  }*/

    login(email: string, motDePasse: string): Observable<LoginResponse> {
  return this.http.post<LoginResponse>(
    `${environment.apiUrl}/api/auth/login`,
    { email, motDePasse } as LoginRequest
  ).pipe(
    tap((res: LoginResponse) => {
      console.log('🔐 Réponse login :', res);
      if (this.isBrowser) {
        localStorage.setItem('token', res.token);
      }

      // ✅ Si le backend renvoie res.user → on l'utilise
      // ✅ Sinon on décode le JWT directement
      const userInfo = res.user ?? this.decodeJwt(res.token);
      this._user.set(userInfo);

      console.log('👤 Utilisateur stocké :', this._user());
    })
  );
}

 // src/app/core/services/auth.service.ts (ajouter cette méthode)
estConnecte(): boolean {
  return this.isLoggedIn();
}

  
  logout(): void {
    if (this.isBrowser) {
      localStorage.removeItem('token');
    }
    this._user.set(null);
    this.router.navigate(['/auth/login']);
  }

  getToken(): string | null {
    return this.isBrowser ? localStorage.getItem('token') : null;
  }

  hasPermission(permission: string): boolean {
    return this._user()?.permissions?.includes(permission) ?? false;
  }

  hasRole(role: string): boolean {
    return this._user()?.role === role;
  }

    private decodeJwt(token: string): UserInfo {
  const payload = JSON.parse(atob(token.split('.')[1]));
  console.log('📦 JWT Payload:', payload);

  // Cherche le rôle dans les champs personnalisés OU dans roles[]
  let role = payload.role ?? '';
  if (!role && payload.roles) {
    // Convertit ROLE_ADMINISTRATEUR → Administrateur
    const r = (payload.roles as string[]).find(r => r !== 'ROLE_USER') ?? '';
    role = r.replace('ROLE_', '').charAt(0).toUpperCase()
         + r.replace('ROLE_', '').slice(1).toLowerCase();
    // Cas spéciaux
    if (role === 'Administrateur') role = 'Administrateur';
    if (role === 'Agent')          role = 'Agent';
    if (role === 'Archiviste')     role = 'Archiviste';
  }

  return {
    id:          payload.id       ?? payload.sub ?? 0,
    nom:         payload.nom      ?? '',
    prenom:      payload.prenom   ?? '',
    email:       payload.email    ?? payload.username ?? '',
    role:        role,
    permissions: payload.permissions ?? [],
    serviceId:   payload.serviceId  ?? null,
    serviceNom:  payload.serviceNom ?? '',
  };
}
}