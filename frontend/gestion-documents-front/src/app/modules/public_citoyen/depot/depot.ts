// src/app/modules/public_citoyen/depot/depot.ts
import { Component, OnInit, ChangeDetectorRef, NgZone } from '@angular/core';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule, ActivatedRoute } from '@angular/router';
import { CommonModule } from '@angular/common';
import { NavbarPublic } from '../navbar-public/navbar-public';
import { environment } from '../../../../environments/environment';
import { finalize } from 'rxjs/operators';
import { TranslatePipe } from '../../../core/pipes/translate.pipe';

interface DepotResponse {
  numeroDossier: string;
  numerosDossiers?: string[];
  message: string;
}

const EXTENSIONS_AUTORISEES = ['.pdf', '.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp'];
const MAX_FICHIERS = 4;
const MAX_TAILLE_OCTET = 10 * 1024 * 1024;

@Component({
  selector: 'app-depot',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterModule, NavbarPublic, TranslatePipe],
  templateUrl: './depot.html',
  styleUrls: ['./depot.css']
})
export class Depot implements OnInit {
  formulaire!: FormGroup;
  fichiersSelectionnes: File[] = [];
  services: any[] = [];
  dragActif = false;
  enCours = false;
  succes = false;
  numeroDossier = '';
  erreurMessage = '';
  servicePredefini = false;

  constructor(
    private fb: FormBuilder,
    private http: HttpClient,
    private route: ActivatedRoute,
    private cdr: ChangeDetectorRef,
    private ngZone: NgZone
  ) {}

  ngOnInit() {
    this.formulaire = this.fb.group({
      nomCitoyen:       ['', [Validators.required, Validators.minLength(3)]],
      emailCitoyen:     ['', [Validators.required, Validators.email]],
      telephoneCitoyen: ['', [Validators.pattern(/^[0-9+\s]{8,15}$/)]],
      titre:            ['', [Validators.required, Validators.minLength(5)]],
      description:      ['', [Validators.required, Validators.minLength(5)]],
      serviceId:        [null, Validators.required]
    });

    // ✅ CORRECTION : /api/public/services (avec /public/)
    this.http.get<any[]>(`${environment.apiUrl}/api/public/services`).subscribe({
      next: (data) => {
        this.services = data;
        const serviceIdParam = this.route.snapshot.queryParamMap.get('serviceId');
        if (serviceIdParam) {
          const id = parseInt(serviceIdParam, 10);
          if (!isNaN(id) && this.services.some(s => s.id === id)) {
            this.formulaire.patchValue({ serviceId: id });
            this.servicePredefini = true;
            this.formulaire.get('serviceId')?.disable();
          }
        }
        this.cdr.detectChanges();
      },
      error: () => { this.erreurMessage = 'Impossible de charger la liste des services.'; this.cdr.detectChanges(); }
    });
  }

  onDragOver(event: DragEvent) { event.preventDefault(); this.dragActif = true; }
  onDragLeave() { this.dragActif = false; }

  onDrop(event: DragEvent) {
    event.preventDefault(); this.dragActif = false;
    if (event.dataTransfer?.files) this.ajouterFichiers(Array.from(event.dataTransfer.files));
  }

  onFichierChoisi(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files) this.ajouterFichiers(Array.from(input.files));
    input.value = '';
  }

  ajouterFichiers(nouveauxFichiers: File[]) {
    for (const f of nouveauxFichiers) {
      const ext = '.' + f.name.split('.').pop()?.toLowerCase();
      if (!EXTENSIONS_AUTORISEES.includes(ext)) { alert(`Format non autorisé : ${f.name}.`); continue; }
      if (f.size > MAX_TAILLE_OCTET) { alert(`"${f.name}" dépasse la limite de 10 Mo.`); continue; }
      if (this.fichiersSelectionnes.some(ex => ex.name === f.name && ex.size === f.size)) { alert(`"${f.name}" a déjà été ajouté.`); continue; }
      if (this.fichiersSelectionnes.length >= MAX_FICHIERS) { alert(`Maximum ${MAX_FICHIERS} fichiers autorisés.`); break; }
      this.fichiersSelectionnes.push(f);
      this.cdr.detectChanges();
    }
  }

  supprimerFichier(index: number) { this.fichiersSelectionnes.splice(index, 1); this.cdr.detectChanges(); }

  formaterTaille(octets: number): string {
    if (octets < 1024) return octets + ' o';
    if (octets < 1024 * 1024) return (octets / 1024).toFixed(1) + ' Ko';
    return (octets / (1024 * 1024)).toFixed(1) + ' Mo';
  }

  estChampInvalide(champ: string): boolean {
    const ctrl = this.formulaire.get(champ);
    return !!(ctrl && ctrl.invalid && (ctrl.dirty || ctrl.touched));
  }

  soumettre() {
    const serviceId = this.servicePredefini
      ? this.formulaire.get('serviceId')?.value
      : this.formulaire.value.serviceId;

    if (this.formulaire.invalid || this.fichiersSelectionnes.length === 0) {
      this.formulaire.markAllAsTouched();
      alert('Veuillez remplir tous les champs et joindre au moins un fichier PDF ou image.');
      return;
    }

    this.ngZone.run(() => { this.enCours = true; this.cdr.detectChanges(); });

    const formData = new FormData();
    const val = this.formulaire.value;
    formData.append('nomCitoyen',       val.nomCitoyen);
    formData.append('emailCitoyen',     val.emailCitoyen);
    formData.append('telephoneCitoyen', val.telephoneCitoyen || '');
    formData.append('titre',            val.titre);
    formData.append('description',      val.description);
    formData.append('serviceId',        serviceId.toString());
    for (let i = 0; i < this.fichiersSelectionnes.length; i++) {
      formData.append('fichiers[]', this.fichiersSelectionnes[i]);
    }

    
    this.http.post<DepotResponse>(`${environment.apiUrl}/api/public/depot`, formData)
      .pipe(finalize(() => this.ngZone.run(() => { this.enCours = false; this.cdr.detectChanges(); })))
      .subscribe({
        next:  (res) => this.ngZone.run(() => { this.succes = true; this.numeroDossier = (res.numerosDossiers?.join(', ') || res.numeroDossier); this.cdr.detectChanges(); }),
        error: (err) => alert(err.error?.message || 'Erreur lors du dépôt. Veuillez réessayer.')
      });
  }

  copierNumero() { navigator.clipboard.writeText(this.numeroDossier); }

  nouveauDepot() {
    this.ngZone.run(() => {
      this.succes = false; this.numeroDossier = '';
      this.formulaire.reset({ serviceId: null });
      if (this.servicePredefini) { this.formulaire.get('serviceId')?.enable(); this.servicePredefini = false; }
      this.fichiersSelectionnes = [];
      this.cdr.detectChanges();
    });
  }

  get acceptFichiers(): string {
    return '.pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,image/*,application/pdf';
  }
}
