// src/app/modules/public_citoyen/accueil/accueil.ts
import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { NavbarPublic } from '../navbar-public/navbar-public';
import { TranslatePipe } from '../../../core/pipes/translate.pipe';

@Component({
  selector: 'app-accueil',
  standalone: true,
  imports: [CommonModule, RouterModule, NavbarPublic, TranslatePipe],
  templateUrl: './accueil.html',
  styleUrls: ['./accueil.css']
})
export class Accueil implements OnInit {
  services: { id: number; nom: string }[] = [];
  currentYear: number = new Date().getFullYear();

  constructor(private router: Router, private http: HttpClient) {}

  ngOnInit() {
    // ✅ CORRECTION : /api/public/services (avec /public/)
    this.http.get<{ id: number; nom: string }[]>(`${environment.apiUrl}/api/public/services`).subscribe({
      next: (data) => this.services = data,
      error: () => {}
    });
  }

  allerVersDepot() { this.router.navigate(['/public/depot']); }
  allerVersSuivi() { this.router.navigate(['/public/suivi']); }
  allerAuDepot(serviceId: number) {
    this.router.navigate(['/public/depot'], { queryParams: { serviceId } });
  }
}