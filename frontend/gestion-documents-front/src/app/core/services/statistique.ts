// src/app/core/services/statistique.ts
import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface DashboardStats {
  totalDossiers: number;
  dossiersParStatut: { statut: string; count: number }[];
  totalUtilisateurs: number;
  utilisateursActifs: number;
  utilisateursListeNoire: number;
}

export interface StatistiquesFiltre {
  periode: '7j' | '30j' | '90j' | 'custom';
  dateDebut?: string;
  dateFin?: string;
  serviceId?: number;
}

export interface StatsDossiers {
  totalDossiers: number;
  tauxTraitement: number;
  delaiMoyen: number;
  tauxRejet: number;
  tendanceTotalDossiers: number;
  tendanceTauxTraitement: number;
  tendanceDelaiMoyen: number;
  tendanceTauxRejet: number;
  dossiersParStatut: { statut: string; code: string; count: number }[];
  repartitionParService: { service: string; count: number }[];
  delaiParMois: { mois: string; delai: number }[];
  evolutionMensuelle: { mois: string; recu: number; traite: number; rejete: number }[];
}

export interface StatsArchiviste {
  totalArchivesPeriode: number;
  totalArchivesGlobal: number;
  restaurationsPeriode: number;
  evolution: { mois: string; archives: number }[];
  parService: { service: string; count: number }[];
}

@Injectable({ providedIn: 'root' })
export class StatistiqueService {
  private http = inject(HttpClient);
  private api  = `${environment.apiUrl}/api/statistiques`;

  getDashboard(jours = 30): Observable<DashboardStats> {
    const params = new HttpParams().set('jours', jours.toString());
    return this.http.get<DashboardStats>(`${this.api}/dashboard`, { params });
  }

  getStatsDossiers(filtre: StatistiquesFiltre): Observable<StatsDossiers> {
    let params = new HttpParams().set('periode', filtre.periode);
    if (filtre.dateDebut) params = params.set('dateDebut', filtre.dateDebut);
    if (filtre.dateFin)   params = params.set('dateFin',   filtre.dateFin);
    if (filtre.serviceId) params = params.set('serviceId', filtre.serviceId.toString());
    return this.http.get<StatsDossiers>(`${this.api}/dossiers`, { params });
  }

  getStatsArchiviste(filtre: StatistiquesFiltre): Observable<StatsArchiviste> {
    let params = new HttpParams().set('periode', filtre.periode);
    if (filtre.dateDebut) params = params.set('dateDebut', filtre.dateDebut);
    if (filtre.dateFin)   params = params.set('dateFin',   filtre.dateFin);
    return this.http.get<StatsArchiviste>(`${this.api}/archiviste`, { params });
  }

  /** Export PDF — vrai fichier application/pdf généré par le backend. */
  exporterPdf(filtre: StatistiquesFiltre, contexte: 'documentaire' | 'archivage' = 'documentaire'): Observable<Blob> {
    let params = new HttpParams().set('periode', filtre.periode).set('contexte', contexte);
    if (filtre.dateDebut) params = params.set('dateDebut', filtre.dateDebut);
    if (filtre.dateFin) params = params.set('dateFin', filtre.dateFin);
    if (filtre.serviceId) params = params.set('serviceId', filtre.serviceId.toString());
    return this.http.get(`${this.api}/export/pdf`, { params, responseType: 'blob' });
  }

  exporterExcel(filtre: StatistiquesFiltre): Observable<Blob> {
    let params = new HttpParams().set('periode', filtre.periode);
    if (filtre.serviceId) params = params.set('serviceId', filtre.serviceId.toString());
    return this.http.get(`${this.api}/export/excel`, { params, responseType: 'blob' });
  }
}