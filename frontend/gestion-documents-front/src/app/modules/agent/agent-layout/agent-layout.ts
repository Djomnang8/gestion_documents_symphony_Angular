import {
  Component, HostListener, inject, AfterViewInit, ChangeDetectorRef
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-agent-layout',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './agent-layout.html',
  styleUrl: './agent-layout.css'
})
export class AgentLayout implements AfterViewInit {
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
  voirProfil()  { this.router.navigate(['/agent/profil']); }

  get user() { return this.auth.user?.(); }

  // FIX : lit serviceNom depuis le payload JWT (ajouté par JwtCreatedListener)
  // → remplace "Cabinet Juridique" par le vrai nom du service de l'agent
  get serviceNom(): string {
    return this.auth.user?.()?.serviceNom ?? 'Cabinet Juridique';
  }

  get initiales(): string {
    const u = this.user;
    if (!u) return 'A';
    return `${u.prenom?.charAt(0) ?? ''}${u.nom?.charAt(0) ?? ''}`.toUpperCase() || 'A';
  }
}