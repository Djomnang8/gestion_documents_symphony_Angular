// src/app/modules/administration/admin-layout/admin-layout.ts
// CORRECTION : LanguageSwitcherComponent RETIRÉ.
// La langue est propagée automatiquement depuis le login via
// TranslationService singleton + localStorage.
import {
  Component, HostListener, inject, AfterViewInit, ChangeDetectorRef
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router, NavigationEnd } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { filter } from 'rxjs/operators';

@Component({
  selector: 'app-admin-layout',
  standalone: true,
  // LanguageSwitcherComponent RETIRÉ intentionnellement
  imports: [CommonModule, RouterModule],
  templateUrl: './admin-layout.html',
  styleUrl: './admin-layout.css'
})
export class AdminLayout implements AfterViewInit {
  private router = inject(Router);
  private cdr    = inject(ChangeDetectorRef);
  auth           = inject(AuthService);

  sidebarCollapsed = false;

  constructor() {
    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe(() => this.cdr.detectChanges());
  }

  ngAfterViewInit() {
    this.sidebarCollapsed = window.innerWidth <= 768;
    this.cdr.detectChanges();
  }

  toggleSidebar() { this.sidebarCollapsed = !this.sidebarCollapsed; }
  fermerSidebar() { this.sidebarCollapsed = true; }

  @HostListener('document:keydown.escape')
  onEscape() { if (!this.sidebarCollapsed) this.sidebarCollapsed = true; }

  @HostListener('window:resize')
  onResize() { if (window.innerWidth <= 768) this.sidebarCollapsed = true; }

  deconnecter() { this.auth.logout(); this.router.navigate(['/auth/login']); }
  voirProfil()  { this.router.navigate(['/administration/profil']); }

  get user() { return this.auth.user?.(); }
  get initiales() {
    const u = this.user;
    if (!u) return 'A';
    return `${u.prenom?.charAt(0) ?? ''}${u.nom?.charAt(0) ?? ''}`.toUpperCase() || 'A';
  }
}