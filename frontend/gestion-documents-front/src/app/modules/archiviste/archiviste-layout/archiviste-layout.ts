// src/app/modules/archiviste/archiviste-layout/archiviste-layout.ts
// CORRECTION : LanguageSwitcherComponent RETIRÉ.
// La langue est propagée automatiquement depuis le login via
// TranslationService singleton + localStorage.
import {
  Component, HostListener, inject, AfterViewInit, ChangeDetectorRef
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-archiviste-layout',
  standalone: true,
  // LanguageSwitcherComponent RETIRÉ intentionnellement
  imports: [CommonModule, RouterModule],
  templateUrl: './archiviste-layout.html',
  styleUrl: './archiviste-layout.css'
})
export class ArchivisteLayout implements AfterViewInit {
  private router = inject(Router);
  private cdr    = inject(ChangeDetectorRef);
  auth           = inject(AuthService);

  sidebarCollapsed = false;

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
  voirProfil()  { this.router.navigate(['/archiviste/profil']); }

  get user() { return this.auth.user?.(); }
  get initiales() {
    const u = this.user;
    if (!u) return 'A';
    return `${u.prenom?.charAt(0) ?? ''}${u.nom?.charAt(0) ?? ''}`.toUpperCase() || 'A';
  }
}