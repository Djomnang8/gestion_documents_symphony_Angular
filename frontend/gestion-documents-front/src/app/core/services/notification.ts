// src/app/core/services/notification.ts
import { Injectable } from '@angular/core';
import { Subject } from 'rxjs';

export interface NotificationMessage {
  type: 'success' | 'error' | 'warning' | 'info';
  title?: string;
  content: string;
  duration?: number;
}

@Injectable({ providedIn: 'root' })
export class Notification {
  private messageSource = new Subject<NotificationMessage>();
  public messages$ = this.messageSource.asObservable();

  success(content: string, title = 'Succès', duration = 3000): void {
    this.show({ type: 'success', title, content, duration });
  }

  error(content: string, title = 'Erreur', duration = 5000): void {
    this.show({ type: 'error', title, content, duration });
  }

  warning(content: string, title = 'Attention', duration = 4000): void {
    this.show({ type: 'warning', title, content, duration });
  }

  info(content: string, title = 'Information', duration = 3000): void {
    this.show({ type: 'info', title, content, duration });
  }

  private show(message: NotificationMessage): void {
    this.messageSource.next(message);
  }
}