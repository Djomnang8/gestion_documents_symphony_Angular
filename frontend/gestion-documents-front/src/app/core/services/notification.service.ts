// src/app/core/services/notification.service.ts
import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, Subject } from 'rxjs';
import { environment } from '../../../environments/environment';

// ── Toast (notifications UI locales — inchangé)
export interface NotificationMessage {
  type: 'success' | 'error' | 'warning' | 'info';
  title?: string;
  content: string;
  duration?: number;
}

// ── Notification système (stockée en base)
export interface NotifSysteme {
  id: number;
  titre: string;
  description: string;
  type: 'STATUT' | 'REJETE' | 'RAPPEL' | 'TERMINE' | 'INFO';
  dossierId?: string;
  numeroDossier?: string;
  estLue: boolean;
  dateCreation: string;
}

// ── Email transactionnel
export interface EmailTransactionnel {
  id: number;
  destinataire: string;
  objet: string;
  type: string;
  statut: 'ENVOYE' | 'EN_ATTENTE' | 'ECHEC';
  dateEnvoi: string;
  tentatives: number;
  erreur?: string;
}

export interface NotifPage {
  total: number;
  nonLues: number;
  rappels: number;
  data: NotifSysteme[];
}

@Injectable({ providedIn: 'root' })
export class NotificationService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/api/notifications`;

  // ── Toast subjects (pour le composant toast global)
  private toastSource = new Subject<NotificationMessage>();
  toast$ = this.toastSource.asObservable();

  success(content: string, title = 'Succès',      duration = 3000) { this.toastSource.next({ type:'success', title, content, duration }); }
  error  (content: string, title = 'Erreur',      duration = 5000) { this.toastSource.next({ type:'error',   title, content, duration }); }
  warning(content: string, title = 'Attention',   duration = 4000) { this.toastSource.next({ type:'warning', title, content, duration }); }
  info   (content: string, title = 'Information', duration = 3000) { this.toastSource.next({ type:'info',    title, content, duration }); }

  // ── API notifications système
  getAll(onglet: 'toutes' | 'non-lues' | 'rappels' = 'toutes'): Observable<NotifPage> {
    const params = new HttpParams().set('onglet', onglet);
    return this.http.get<NotifPage>(this.apiUrl, { params });
  }

  marquerLue(id: number): Observable<void> {
    return this.http.put<void>(`${this.apiUrl}/${id}/lue`, {});
  }

  marquerToutesLues(): Observable<void> {
    return this.http.put<void>(`${this.apiUrl}/tout-lire`, {});
  }

  supprimer(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${id}`);
  }

  // ── Emails transactionnels
  getEmails(): Observable<EmailTransactionnel[]> {
    return this.http.get<EmailTransactionnel[]>(`${this.apiUrl}/emails`);
  }

  reessayerEmail(id: number): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.apiUrl}/emails/${id}/retry`, {});
  }
}