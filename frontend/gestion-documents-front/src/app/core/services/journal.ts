// src/app/core/services/journal.ts
// dateAction aligné avec le DTO backend (JournalDto.DateAction)
import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface JournalEntry {
  id:          number;
  utilisateur: string;
  module:      string;
  action:      string;
  details?:    string;
  niveauId:    number;
  dateAction:  string;   // ← dateAction (sérialisé depuis DateAction C#)
  adresseIp?:  string;
}

export interface JournalPage {
  total:    number;
  page:     number;
  pageSize: number;
  data:     JournalEntry[];
}

export interface JournalFiltres {
  utilisateurId?: string;
  module?:        string;
  action?:        string;
  niveauId?:      number;
  dateDebut?:     string;
  dateFin?:       string;
  page?:          number;
  pageSize?:      number;
}

@Injectable({ providedIn: 'root' })
export class JournalService {
  private http   = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/api/journaux`;

  getAll(filtres: JournalFiltres = {}): Observable<JournalPage> {
    let params = new HttpParams()
      .set('page',     filtres.page     ?? 1)
      .set('pageSize', filtres.pageSize ?? 30);

    if (filtres.utilisateurId) params = params.set('utilisateurId', filtres.utilisateurId);
    if (filtres.module)        params = params.set('module',        filtres.module);
    if (filtres.action)        params = params.set('action',        filtres.action);
    if (filtres.niveauId)      params = params.set('niveauId',      filtres.niveauId);
    if (filtres.dateDebut)     params = params.set('dateDebut',     filtres.dateDebut);
    if (filtres.dateFin)       params = params.set('dateFin',       filtres.dateFin);

    return this.http.get<JournalPage>(this.apiUrl, { params });
  }

  getMesActivites(filtres: JournalFiltres = {}): Observable<JournalPage> {
    let params = new HttpParams()
      .set('page',     filtres.page     ?? 1)
      .set('pageSize', filtres.pageSize ?? 20);

    if (filtres.utilisateurId) params = params.set('utilisateurId', filtres.utilisateurId);
    if (filtres.dateDebut)     params = params.set('dateDebut',     filtres.dateDebut);
    if (filtres.dateFin)       params = params.set('dateFin',       filtres.dateFin);

    return this.http.get<JournalPage>(`${this.apiUrl}/mes-activites`, { params });
  }

  getModules(): Observable<string[]> {
    return this.http.get<string[]>(`${this.apiUrl}/modules`);
  }

  getExportUrl(filtres: JournalFiltres): string {
    let params = new HttpParams();
    if (filtres.utilisateurId) params = params.set('utilisateurId', filtres.utilisateurId);
    if (filtres.module)        params = params.set('module',        filtres.module);
    if (filtres.niveauId)      params = params.set('niveauId',      filtres.niveauId);
    if (filtres.dateDebut)     params = params.set('dateDebut',     filtres.dateDebut);
    if (filtres.dateFin)       params = params.set('dateFin',       filtres.dateFin);
    return `${this.apiUrl}/export?${params.toString()}`;
  }
}