// src/app/modules/public_citoyen/acces-refuse/acces-refuse.ts
import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-acces-refuse',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="container text-center mt-5">
      <h1>⛔ Accès refusé</h1>
      <p>Vous n’avez pas les droits nécessaires pour accéder à cette page.</p>
      <a routerLink="/public" class="btn btn-primary">Retour à l’accueil</a>
    </div>
  `,
  styles: [`
    .container { padding: 2rem; }
    h1 { font-size: 2rem; margin-bottom: 1rem; }
    .btn { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; border-radius: 4px; text-decoration: none; }
  `]
})
export class AccesRefuseComponent {}