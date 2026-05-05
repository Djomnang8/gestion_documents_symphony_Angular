import { Component, OnInit, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { DossiersService } from '../../../core/services/dossier';
import { environment } from '../../../../environments/environment';

@Component({
  selector: 'app-nouveau-dossier',
  standalone: true,
  imports: [CommonModule, RouterModule, ReactiveFormsModule],
  templateUrl: './nouveau-dossier.html',
  styleUrls: ['./nouveau-dossier.css']
})
export class NouveauDossier implements OnInit {
  private fb = inject(FormBuilder);
  private svc = inject(DossiersService);
  private router = inject(Router);
  private http = inject(HttpClient);

  services = signal<{ id: number; nom: string }[]>([]);
  enCours = signal(false);
  erreur = signal('');
  fichierSelectionne: File | null = null;

  formulaire = this.fb.group({
    titre: ['', [Validators.required, Validators.minLength(5), Validators.maxLength(200)]],
    description: ['', Validators.maxLength(2000)],
    nomCitoyen: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(160)]],
    emailCitoyen: ['', [Validators.email, Validators.maxLength(150)]],
    telephoneCitoyen: ['', Validators.maxLength(20)],
    serviceId: [null as number | null, Validators.required]
  });

  ngOnInit() {
    this.http.get<any[]>(`${environment.apiUrl}/api/services`).subscribe({
      next: (s) => this.services.set(s),
      error: () => this.services.set([
        { id: 1, nom: 'Direction Générale' },
        { id: 2, nom: 'Service Administratif' },
        { id: 3, nom: 'Service Technique' },
        { id: 4, nom: 'Archives Centrales' }
      ])
    });
  }

  estInvalide(champ: string): boolean {
    const c = this.formulaire.get(champ);
    return !!(c && c.invalid && (c.dirty || c.touched));
  }

  onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length) {
      this.fichierSelectionne = input.files[0];
    }
  }

  soumettre() {
    if (this.formulaire.invalid) { this.formulaire.markAllAsTouched(); return; }
    this.enCours.set(true);
    this.erreur.set('');

    const v = this.formulaire.value;
    this.svc.creerDossier({
      titre: v.titre!,
      description: v.description || undefined,
      nomCitoyen: v.nomCitoyen!,
      emailCitoyen: v.emailCitoyen || undefined, 
      telephoneCitoyen: v.telephoneCitoyen || undefined,
      serviceId: v.serviceId!
    }).subscribe({
      next: (res) => {
        const dossierId = res.id;
        if (this.fichierSelectionne) {
          this.svc.uploadDocument(dossierId, this.fichierSelectionne).subscribe({
            next: () => {
              this.enCours.set(false);
              this.router.navigate(['/agent/dossiers', dossierId]);
            },
            error: (err) => {
              this.enCours.set(false);
              this.erreur.set(err.error?.message ?? 'Dossier créé mais erreur lors de l\'upload du fichier.');
              // On navigue quand même vers le dossier
              this.router.navigate(['/agent/dossiers', dossierId]);
            }
          });
        } else {
          this.enCours.set(false);
          this.router.navigate(['/agent/dossiers', dossierId]);
        }
      },
      error: (err) => {
        this.enCours.set(false);
        this.erreur.set(err.error?.message ?? 'Erreur lors de la création du dossier.');
      }
    });
  }
}