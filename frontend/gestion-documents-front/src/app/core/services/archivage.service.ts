// src/app/core/services/archivage.service.ts
import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface KpiArchiviste {
  aArchiver: number;
  archivesCeMois: number;
  totalArchives: number;
}

export interface DossierAArchiver {
  id: string;
  numero: string;
  titre: string;
  citoyen: string;
  email: string;
  dateFin: Date;
  service: string;
  nbDocuments: number;
}

export interface DossierArchive {
  id: string;
  numero: string;
  titre: string;
  citoyen: string;
  dateArchivage: Date;
  service: string;
  nbDocuments: number;
  description: string;
  miniature?: string; 
}

export interface ServiceOption {
  id: number;
  nom: string;
  description?: string;
}

export interface VersionDocument {
  id: string;
  dossierId: string;
  numero: number;
  nomFichier: string;
  tailleFichier: number;
  typeFichier: string;
  dateCreation: Date;
  auteur: string;
  estActive: boolean;
  commentaire?: string;
}

@Injectable({ providedIn: 'root' })
export class ArchivageService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/api/archivage`;
  private versionsUrl = `${environment.apiUrl}/api/versions`;
  private servicesUrl = `${environment.apiUrl}/api/services`;

  getKpi(): Observable<KpiArchiviste> {
    return this.http.get<KpiArchiviste>(`${this.apiUrl}/kpi`);
  }

  getDossiersAArchiver(): Observable<DossierAArchiver[]> {
    return this.http.get<DossierAArchiver[]>(`${this.apiUrl}/a-archiver`);
  }

  getServices(): Observable<ServiceOption[]> {
    return this.http.get<ServiceOption[]>(this.servicesUrl);
  }

  archiverDossier(dossierId: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.apiUrl}/${dossierId}`, {});
  }

  rechercherArchives(filtres: {
    numero?: string;
    serviceId?: number;
    dateDebut?: Date;
    dateFin?: Date;
    page?: number;
    size?: number;
  }): Observable<{ data: DossierArchive[]; total: number }> {
    let params = new HttpParams();
    if (filtres.numero) params = params.set('numero', filtres.numero);
    if (filtres.serviceId) params = params.set('serviceId', filtres.serviceId);
    if (filtres.dateDebut) params = params.set('dateDebut', filtres.dateDebut.toISOString());
    if (filtres.dateFin) params = params.set('dateFin', filtres.dateFin.toISOString());
    if (filtres.page) params = params.set('page', filtres.page);
    if (filtres.size) params = params.set('size', filtres.size);
    return this.http.get<{ data: DossierArchive[]; total: number }>(`${this.apiUrl}/archives`, { params });
  }

  getVersions(dossierId: string): Observable<VersionDocument[]> {
    return this.http.get<VersionDocument[]>(`${this.versionsUrl}/${dossierId}`);
  }

  restaurerVersion(versionId: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.versionsUrl}/${versionId}/restaurer`, {});
  }
}