// src/app/modules/administration/journaux-systeme/journaux-systeme.ts
import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { JournalService, JournalEntry, JournalFiltres } from '../../../core/services/journal';


@Component({
  selector: 'app-journaux-systeme',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './journaux-systeme.html',
  styleUrl: './journaux-systeme.css'
})
export class JournauxSysteme implements OnInit {
  private svc = inject(JournalService);

  journaux  = signal<JournalEntry[]>([]);
  total     = signal(0);
  chargement = signal(false);
  modules   = signal<string[]>([]);
  readonly Math = Math;


  // Filtres
  filtreModule    = '';
  filtreAction    = '';
  filtreNiveau    = '';
  filtreDateDebut = '';
  filtreDateFin   = '';

  page     = 1;
  pageSize = 30;

  readonly niveaux = [
    { id: '',  label: 'Tous les niveaux' },
    { id: '1', label: '1 — Info'         },
    { id: '2', label: '2 — Avertissement'},
    { id: '3', label: '3 — Erreur'       },
  ];

  readonly niveauIcons: Record<number, string> = {
    1: '💡',
    2: '⚠️',
    3: '🔴',
  };

  ngOnInit() {
    this.svc.getModules().subscribe(m => this.modules.set(m));
    this.charger();
  }

  charger() {
    this.chargement.set(true);
    const filtres: JournalFiltres = {
      page:      this.page,
      pageSize:  this.pageSize,
      module:    this.filtreModule    || undefined,
      action:    this.filtreAction    || undefined,
      niveauId:  this.filtreNiveau    ? +this.filtreNiveau : undefined,
      dateDebut: this.filtreDateDebut || undefined,
      dateFin:   this.filtreDateFin   || undefined,
    };
    this.svc.getAll(filtres).subscribe({
      next: res => {
        this.journaux.set(res.data);
        this.total.set(res.total);
        this.chargement.set(false);
      },
      error: () => this.chargement.set(false)
    });
  }

  filtrer()       { this.page = 1; this.charger(); }
  reinitialiser() {
    this.filtreModule = ''; this.filtreAction = ''; this.filtreNiveau = '';
    this.filtreDateDebut = ''; this.filtreDateFin = '';
    this.page = 1; this.charger();
  }

  get totalPages(): number { return Math.ceil(this.total() / this.pageSize); }
  get pages(): number[]    { return Array.from({ length: this.totalPages }, (_, i) => i + 1); }
  allerPage(p: number)     { if (p >= 1 && p <= this.totalPages) { this.page = p; this.charger(); } }

  exporter() {
    const url = this.svc.getExportUrl({
      module:    this.filtreModule    || undefined,
      niveauId:  this.filtreNiveau    ? +this.filtreNiveau : undefined,
      dateDebut: this.filtreDateDebut || undefined,
      dateFin:   this.filtreDateFin   || undefined,
    });
    window.open(url + `&token=${localStorage.getItem('token')}`, '_blank');
  }

  niveauIcon(id: number): string { return this.niveauIcons[id] ?? '📋'; }
  niveauLabel(id: number): string {
    return ['', 'Info', 'Avertissement', 'Erreur'][id] ?? 'Inconnu';
  }
  niveauClass(id: number): string {
    return ['', 'niv-info', 'niv-warn', 'niv-erreur'][id] ?? '';
  }

  formatDate(d: string): string {
    if (!d) return '—';
    try { return new Date(d).toLocaleString('fr-FR'); } catch { return '—'; }
  }
}