// src/app/core/services/utilisateur.ts
import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface UtilisateurListe {
  id: string; nom: string; prenom: string; email: string;
  telephone?: string; role: string; serviceId?: number;
  estActif: boolean; estListeNoire: boolean; motifListeNoire?: string;
  derniereConnexion?: string; typeUtilisateur: string;
}
export interface CreateUtilisateurDto {
  nom: string; prenom: string; email: string; telephone?: string;
  motDePasse: string; role: string; serviceId?: number;
}
export interface ModifierUtilisateurDto {
  nom: string; prenom: string; email: string;
  telephone?: string; role?: string; serviceId?: number;
}
export interface RoleDto {
  id: number; nom: string; description?: string; permissions: string[];
}
export interface ServiceDto { id: number; nom: string; description?: string; }

@Injectable({ providedIn: 'root' })
export class UtilisateurService {
  private http   = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/api/utilisateurs`;

  getAll(statut?: 'actif'|'inactif'|'listenoire'): Observable<UtilisateurListe[]> {
    let params = new HttpParams();
    if (statut) params = params.set('statut', statut);
    return this.http.get<UtilisateurListe[]>(this.apiUrl, { params });
  }

  getById(id: string): Observable<UtilisateurListe> {
    return this.http.get<UtilisateurListe>(`${this.apiUrl}/${id}`);
  }

  create(dto: CreateUtilisateurDto): Observable<{ message: string; id: string }> {
    return this.http.post<{ message: string; id: string }>(this.apiUrl, dto);
  }

  /** Modifier les informations d'un utilisateur */
  modifier(id: string, dto: ModifierUtilisateurDto): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`${this.apiUrl}/${id}`, dto);
  }

  activer(id: string): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`${this.apiUrl}/${id}/activer`, {});
  }
  desactiver(id: string): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`${this.apiUrl}/${id}/desactiver`, {});
  }
  mettreListeNoire(id: string, motif: string): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`${this.apiUrl}/${id}/listenoire`, { motif });
  }
  changerRole(id: string, role: string): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`${this.apiUrl}/${id}/role`, { role });
  }
  getRoles(): Observable<RoleDto[]> {
    return this.http.get<RoleDto[]>(`${this.apiUrl}/roles`);
  }
  getServices(): Observable<ServiceDto[]> {
    return this.http.get<ServiceDto[]>(`${this.apiUrl}/services`);
  }
}