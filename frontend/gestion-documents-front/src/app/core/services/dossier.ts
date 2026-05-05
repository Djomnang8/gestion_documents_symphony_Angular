// src/app/core/services/dossier.ts
import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

// ──────────────────────────────────────────────────────────────
// 1. TYPES POUR LA GESTION DES DOSSIERS (agent)
// ──────────────────────────────────────────────────────────────

export interface DossierListe {
  id: string;
  numero: string;
  titre: string;
  nomCitoyen: string;
  emailCitoyen?: string;
  telephoneCitoyen?: string;
  statutCode: string;
  statutLibelle: string;
  dateDepot: string;
  dateMiseAJourStatut: string;
}

export interface PageDossiers {
  total: number;
  page: number;
  taille: number;
  dossiers: DossierListe[];
}

export interface DossierDetail {
  id: string;
  numero: string;
  titre: string;
  description?: string;
  nomCitoyen: string;
  emailCitoyen?: string;
  telephoneCitoyen?: string;
  motifRejet?: string;
  statutCode: string;
  statutLibelle: string;
  serviceNom: string;
  dateDepot: string;
  dateMiseAJourStatut: string;
  historique: HistoriqueStatut[];
  documents: Document[];
}

export interface HistoriqueStatut {
  ancienStatut: string;
  nouveauStatut: string;
  commentaire?: string;
  dateChangement: string;
}

export interface Document {
  id: string;
  nomFichier: string;
  cheminFichier: string;
  typeFichier: string;
  tailleFichier: number;
  numeroVersion: number;
  dateCreation: string;
}

// ──────────────────────────────────────────────────────────────
// 2. TYPES POUR LES STATISTIQUES AGENT
// ──────────────────────────────────────────────────────────────

export interface StatsDossiers {
  total: number;
  recusAujourdhui: number;
  traitesSemine: number;
  enRetard: number;
  recu: number;
  enCours: number;
  transfere: number;
  rejete: number;
  termine: number;
  archive: number;
}

export interface DossierEnRetard {
  id: string;
  numero: string;
  titre: string;
  nomCitoyen: string;
  statutCode: string;
  statutLibelle: string;
  dateDepot: string;
  dateMiseAJourStatut: string;
  joursEnRetard: number;
}

// ──────────────────────────────────────────────────────────────
// 3. TYPES POUR L’ARCHIVAGE
// ──────────────────────────────────────────────────────────────

export interface ArchivageKpi {
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
  emailCitoyen: string;
  service: string;
  dateArchivage: string;
  nbDocuments: number;
  miniature: string;
}

export interface ArchiveSearchParams {
  numero?: string;
  dateDebut?: string;
  dateFin?: string;
  page?: number;
  size?: number;
}

// ──────────────────────────────────────────────────────────────
// 4. TYPES POUR LE VERSIONNEMENT
// ──────────────────────────────────────────────────────────────

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

// ──────────────────────────────────────────────────────────────
// 5. SERVICE UNIFIÉ
// ──────────────────────────────────────────────────────────────

@Injectable({ providedIn: 'root' })
export class DossiersService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/api/dossiers`;
  private archivageUrl = `${environment.apiUrl}/api/archivage`;
  private versionsUrl = `${environment.apiUrl}/api/versions`;

  // ─── GESTION DES DOSSIERS (agent) ─────────────────────────
  getMesDossiers(
    statut?: string,
    recherche?: string,
    page = 1,
    taille = 10,
    serviceId?: number,
    dateDebut?: string,
    dateFin?: string
  ): Observable<PageDossiers> {
    let params = new HttpParams()
      .set('page', page)
      .set('taille', taille);
    if (statut) params = params.set('statut', statut);
    if (recherche) params = params.set('recherche', recherche);
    if (serviceId) params = params.set('serviceId', serviceId);
    if (dateDebut) params = params.set('dateDebut', dateDebut);
    if (dateFin) params = params.set('dateFin', dateFin);
    return this.http.get<PageDossiers>(this.apiUrl, { params });
  }

  getDossier(id: string): Observable<DossierDetail> {
    return this.http.get<DossierDetail>(`${this.apiUrl}/${id}`);
  }

  creerDossier(dto: CreerDossierRequest): Observable<{ id: string; numero: string }> {
    return this.http.post<{ id: string; numero: string }>(this.apiUrl, dto);
  }

  uploadDocument(dossierId: string, fichier: File): Observable<{ id: number; nomFichier: string }> {
    const formData = new FormData();
    formData.append('fichier', fichier);
    return this.http.post<{ id: number; nomFichier: string }>(
      `${this.apiUrl}/${dossierId}/documents`,
      formData
    );
  }

  changerStatut(dossierId: string, nouveauStatutCode: string, commentaire?: string): Observable<{ message: string; statut: string }> {
    return this.http.patch<{ message: string; statut: string }>(
      `${this.apiUrl}/${dossierId}/statut`,
      { nouveauStatutCode, commentaire }
    );
  }

  transfererDossier(dossierId: string, serviceId: number, commentaire?: string): Observable<{ message: string }> {
    return this.http.patch<{ message: string }>(`${this.apiUrl}/${dossierId}/transferer`, { serviceId, commentaire });
  }

  exportCsv(
    statut?: string,
    recherche?: string,
    serviceId?: number,
    dateDebut?: string,
    dateFin?: string
  ): Observable<Blob> {
    let params = new HttpParams();
    if (statut) params = params.set('statut', statut);
    if (recherche) params = params.set('recherche', recherche);
    if (serviceId) params = params.set('serviceId', serviceId);
    if (dateDebut) params = params.set('dateDebut', dateDebut);
    if (dateFin) params = params.set('dateFin', dateFin);
    return this.http.get(`${this.apiUrl}/export-csv`, { params, responseType: 'blob' });
  }

  // ─── STATISTIQUES AGENT ─────────────────────────────────────
  getStats(): Observable<StatsDossiers> {
    return this.http.get<StatsDossiers>(`${this.apiUrl}/stats`);
  }

  getEnRetard(): Observable<DossierEnRetard[]> {
    return this.http.get<DossierEnRetard[]>(`${this.apiUrl}/en-retard`);
  }

  // ─── ARCHIVAGE ──────────────────────────────────────────────
  getArchivageKpi(): Observable<ArchivageKpi> {
    return this.http.get<ArchivageKpi>(`${this.archivageUrl}/kpi`);
  }

  getDossiersAAArchiver(): Observable<DossierAArchiver[]> {
    return this.http.get<DossierAArchiver[]>(`${this.archivageUrl}/a-archiver`);
  }

  archiverDossier(dossierId: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.archivageUrl}/${dossierId}`, {});
  }

  rechercherArchives(filtres: ArchiveSearchParams): Observable<{ total: number; page: number; size: number; data: DossierArchive[] }> {
    let params = new HttpParams()
      .set('page', filtres.page ?? 1)
      .set('size', filtres.size ?? 12);
    if (filtres.numero) params = params.set('numero', filtres.numero);
    if (filtres.dateDebut) params = params.set('dateDebut', filtres.dateDebut);
    if (filtres.dateFin) params = params.set('dateFin', filtres.dateFin);
    return this.http.get<{ total: number; page: number; size: number; data: DossierArchive[] }>(
      `${this.apiUrl}/archives`,
      { params }
    );
  }

  // ─── VERSIONNEMENT ─────────────────────────────────────────
  getVersions(dossierId: string): Observable<VersionDocument[]> {
    return this.http.get<VersionDocument[]>(`${this.versionsUrl}/${dossierId}`);
  }

  restaurerVersion(versionId: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.versionsUrl}/${versionId}/restaurer`, {});
  }
}

// ──────────────────────────────────────────────────────────────
// DTOs utilisés par le service
// ──────────────────────────────────────────────────────────────

export interface CreerDossierRequest {
  titre: string;
  description?: string;
  nomCitoyen: string;
  emailCitoyen?: string;
  telephoneCitoyen?: string;
  serviceId: number;
}