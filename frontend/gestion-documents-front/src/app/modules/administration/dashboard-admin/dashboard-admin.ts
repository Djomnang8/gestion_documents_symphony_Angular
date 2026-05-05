// src/app/modules/administration/dashboard-admin/dashboard-admin.ts
import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { StatistiqueService, DashboardStats } from '../../../core/services/statistique';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-dashboard-admin',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './dashboard-admin.html',
  styleUrl: './dashboard-admin.css'
})
export class DashboardAdminComponent implements OnInit {
  private statSvc = inject(StatistiqueService);
  private cdr = inject(ChangeDetectorRef);

  stats: DashboardStats | null = null;
  loading = true;

  ngOnInit() {
    this.statSvc.getDashboard(30).subscribe({
      next: (d: DashboardStats) => {
        this.stats = d;
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.loading = false;
        this.cdr.markForCheck();
      }
    });
  }

  getStatutColor(code: string) {
    const statutColors: Record<string, string> = {
      RECU: '#3498DB', EN_COURS: '#9B59B6', TERMINE: '#17A589',
      REJETE: '#E74C3C', ARCHIVE: '#95A5A6', TRANSFERE: '#E67E22'
    };
    return statutColors[code] ?? '#95A5A6';
  }

  getNiveau(id: number) {
    const niveaux: Record<number, { label: string; color: string }> = {
      1: { label: 'INFO', color: '#78909C' },
      2: { label: 'AVERTISSEMENT', color: '#F4D03F' },
      3: { label: 'ERREUR', color: '#FF6B6B' },
      4: { label: 'CRITIQUE', color: '#8B0000' }
    };
    return niveaux[id] ?? { label: 'INFO', color: '#78909C' };
  }
}