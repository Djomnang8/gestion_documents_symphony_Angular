// src/app/modules/public_citoyen/navbar-public/navbar-public.ts
import { Component } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { CommonModule } from '@angular/common';
import { TranslatePipe } from '../../../core/pipes/translate.pipe';
import { LanguageSwitcherComponent } from '../../../shared/language-switcher/language-switcher';

@Component({
  selector: 'app-navbar-public',
  standalone: true,
  imports: [CommonModule, RouterModule, TranslatePipe, LanguageSwitcherComponent],
  templateUrl: './navbar-public.html',
  styleUrls: ['./navbar-public.css']
})
export class NavbarPublic {
  menuOuvert = false;

  constructor(private router: Router) {}

  allerVersDepot()     { this.router.navigate(['/public/depot']); }
  allerVersSuivi()     { this.router.navigate(['/public/suivi']); }
  allerVersConnexion() { this.router.navigate(['/auth/login']); }
  toggleMenu()         { this.menuOuvert = !this.menuOuvert; }
}