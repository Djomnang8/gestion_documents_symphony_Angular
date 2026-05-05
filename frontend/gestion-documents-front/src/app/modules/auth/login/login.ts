// src/app/modules/auth/login/login.ts
// Le sélecteur de langue ici est le SEUL point de contrôle pour les employés.
// La langue choisie est sauvegardée dans localStorage par TranslationService.
// Toutes les pages Agent/Archiviste/Admin lisent automatiquement cette valeur
// au démarrage grâce au singleton TranslationService (providedIn:'root').
import { Component, signal } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { AuthService } from '../../../core/services/auth.service';
import { LanguageSwitcherComponent } from '../../../shared/language-switcher/language-switcher';
import { TranslatePipe } from '../../../core/pipes/translate.pipe';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterModule, LanguageSwitcherComponent, TranslatePipe],
  templateUrl: './login.html',
  styleUrls: ['./login.css']
})
export class Login {
  formulaire: FormGroup;
  enCours = signal(false);
  erreur  = signal('');
  motDePasseVisible = signal(false);

  constructor(
    private fb: FormBuilder,
    private auth: AuthService,
    private router: Router
  ) {
    this.formulaire = this.fb.group({
      email:      ['', [Validators.required, Validators.minLength(3)]],
      motDePasse: ['', [Validators.required, Validators.minLength(6)]]
    });
  }

  toggleMotDePasse() { this.motDePasseVisible.update(v => !v); }

  estChampInvalide(champ: string): boolean {
    const ctrl = this.formulaire.get(champ);
    return !!(ctrl && ctrl.invalid && (ctrl.dirty || ctrl.touched));
  }

  seConnecter() {
    if (this.formulaire.invalid) { this.formulaire.markAllAsTouched(); return; }
    this.enCours.set(true);
    this.erreur.set('');
    const { email, motDePasse } = this.formulaire.value;
    this.auth.login(email, motDePasse).subscribe({
      next: () => {
        this.enCours.set(false);
        const user = this.auth.user();
        if (user?.role === 'Administrateur') {
          this.router.navigate(['/administration/dashboard']);
        } else if (user?.role === 'Archiviste') {
          this.router.navigate(['/archiviste/dashboard']);
        } else {
          this.router.navigate(['/agent/dashboard']);
        }
      },
      error: (err) => {
        this.enCours.set(false);
        if (err.status === 401) {
          this.erreur.set('Email ou mot de passe incorrect.');
        } else if (err.status === 0) {
          this.erreur.set('Impossible de joindre le serveur.');
        } else {
          this.erreur.set('Une erreur est survenue. Veuillez réessayer.');
        }
      }
    });
  }
}