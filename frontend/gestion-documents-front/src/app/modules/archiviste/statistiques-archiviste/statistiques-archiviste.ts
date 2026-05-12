// src/app/modules/archiviste/statistiques-archiviste/statistiques-archiviste.ts
import {
  Component, OnInit, OnDestroy, signal, inject, AfterViewInit,
  ElementRef, ViewChildren, QueryList
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { StatistiqueService, StatsArchiviste, StatistiquesFiltre } from '../../../core/services/statistique';

@Component({
  selector: 'app-statistiques-archiviste',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './statistiques-archiviste.html',
  styleUrl:    './statistiques-archiviste.css'
})
export class StatistiquesArchivistePage implements OnInit, AfterViewInit {
  private svc = inject(StatistiqueService);

  stats      = signal<StatsArchiviste | null>(null);
  chargement = signal(true);

  periode: '7j' | '30j' | '90j' | 'custom' = '30j';
  dateDebut = '';
  dateFin   = '';

  periodes = [
    { label:'7 jours',      value:'7j'     as const },
    { label:'30 jours',     value:'30j'    as const },
    { label:'90 jours',     value:'90j'    as const },
    { label:'Personnalisé', value:'custom' as const }
  ];

  @ViewChildren('canvasEvol,canvasServices')
  canvases!: QueryList<ElementRef<HTMLCanvasElement>>;

  private canvasReady = false;
  private dataReady   = false;

  ngOnInit()        { this.charger(); }
  ngAfterViewInit() { this.canvasReady = true; if (this.dataReady) this.dessiner(); }

  charger() {
    this.chargement.set(true);
    const f: StatistiquesFiltre = {
      periode: this.periode,
      dateDebut: this.periode === 'custom' ? this.dateDebut : undefined,
      dateFin:   this.periode === 'custom' ? this.dateFin   : undefined
    };
    this.svc.getStatsArchiviste(f).subscribe({
      next: s => {
        this.stats.set(s); this.chargement.set(false);
        this.dataReady = true;
        if (this.canvasReady) setTimeout(() => this.dessiner(), 50);
      },
      error: () => {
        this.stats.set(this.demo()); this.chargement.set(false);
        this.dataReady = true;
        if (this.canvasReady) setTimeout(() => this.dessiner(), 50);
      }
    });
  }

  appliquer() { this.charger(); }

  exporterPdf() {
    const filtre: StatistiquesFiltre = {
      periode: this.periode,
      dateDebut: this.periode === 'custom' ? this.dateDebut : undefined,
      dateFin: this.periode === 'custom' ? this.dateFin : undefined
    };
    this.svc.exporterPdf(filtre, 'archivage').subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `rapport_archivage_${new Date().toISOString().slice(0,10)}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
      },
      error: () => alert('Erreur export PDF archivage.')
    });
  }

  labelPeriode(): string {
    const m: Record<string,string> = { '7j':'7 derniers jours','30j':'30 derniers jours','90j':'90 derniers jours','custom':'Personnalisée' };
    return m[this.periode] ?? '30 derniers jours';
  }

  private dessiner() {
    const s = this.stats(); if (!s) return;
    const els = this.canvases.toArray(); if (els.length < 2) return;
    this.dessinerEvolution(els[0].nativeElement, s);
    this.dessinerServices (els[1].nativeElement, s);
  }

  private dessinerEvolution(canvas: HTMLCanvasElement, s: StatsArchiviste) {
    const ctx = canvas.getContext('2d')!;
    canvas.width = canvas.parentElement!.clientWidth; canvas.height = 220;
    const W = canvas.width, H = canvas.height;
    ctx.clearRect(0,0,W,H);
    const data = s.evolution; if (!data.length) return;
    const pad  = { l:44, r:16, t:24, b:36 };
    const maxV = Math.max(...data.map(d => d.archives), 1);

    for(let i=0;i<=4;i++){
      const y = pad.t+((H-pad.t-pad.b)/4)*i;
      ctx.strokeStyle='#E0E0E0'; ctx.beginPath(); ctx.moveTo(pad.l,y); ctx.lineTo(W-pad.r,y); ctx.stroke();
      ctx.fillStyle='#9E9E9E'; ctx.font='10px sans-serif'; ctx.textAlign='right';
      ctx.fillText(String(Math.round(maxV-(maxV/4)*i)), pad.l-4, y+4);
    }

    const pts = data.map((d,i) => ({
      x: pad.l + i*(W-pad.l-pad.r)/(data.length-1),
      y: pad.t + ((maxV - d.archives)/maxV)*(H-pad.t-pad.b)
    }));

    // Zone remplie
    const g = ctx.createLinearGradient(0,pad.t,0,H-pad.b);
    g.addColorStop(0,'#00897B44'); g.addColorStop(1,'#00897B05');
    ctx.beginPath(); ctx.moveTo(pts[0].x, H-pad.b);
    pts.forEach(p => ctx.lineTo(p.x,p.y));
    ctx.lineTo(pts[pts.length-1].x, H-pad.b); ctx.closePath(); ctx.fillStyle=g; ctx.fill();

    // Ligne
    ctx.beginPath(); ctx.moveTo(pts[0].x,pts[0].y);
    pts.forEach(p => ctx.lineTo(p.x,p.y));
    ctx.strokeStyle='#00897B'; ctx.lineWidth=2.5; ctx.stroke();

    pts.forEach((p,i) => {
      ctx.beginPath(); ctx.arc(p.x,p.y,4,0,Math.PI*2);
      ctx.fillStyle='#fff'; ctx.fill(); ctx.strokeStyle='#00897B'; ctx.lineWidth=2; ctx.stroke();
      if(i%2===0){
        ctx.fillStyle='#9E9E9E'; ctx.font='9px sans-serif'; ctx.textAlign='center';
        ctx.fillText(data[i].mois, p.x, H-pad.b+14);
      }
    });
  }

  private dessinerServices(canvas: HTMLCanvasElement, s: StatsArchiviste) {
    const ctx = canvas.getContext('2d')!;
    canvas.width = canvas.parentElement!.clientWidth; canvas.height = 200;
    const W = canvas.width, H = canvas.height;
    ctx.clearRect(0,0,W,H);
    const data = s.parService.slice(0,6); if (!data.length) return;
    const cols = ['#00897B','#1565C0','#5E35B1','#FFB300','#E53935','#546E7A'];
    const pad  = { l:20, r:20, t:20, b:36 };
    const maxV = Math.max(...data.map(d => d.count), 1);
    const bW   = Math.min(50, (W-pad.l-pad.r)/data.length - 10);

    data.forEach((d,i) => {
      const x  = pad.l + i*(W-pad.l-pad.r)/data.length + ((W-pad.l-pad.r)/data.length - bW)/2;
      const bH = ((H-pad.t-pad.b)/maxV)*d.count;
      const y  = H-pad.b-bH;
      ctx.fillStyle = cols[i%cols.length];
      ctx.beginPath(); ctx.roundRect(x,y,bW,bH,4); ctx.fill();
      ctx.fillStyle='#424242'; ctx.font='bold 11px sans-serif'; ctx.textAlign='center';
      ctx.fillText(String(d.count), x+bW/2, y-5);
      ctx.fillStyle='#757575'; ctx.font='9px sans-serif';
      const label = d.service.length > 10 ? d.service.substring(0,10)+'…' : d.service;
      ctx.fillText(label, x+bW/2, H-pad.b+14);
    });
  }

  private demo(): StatsArchiviste {
    return {
      totalArchivesPeriode: 18,
      totalArchivesGlobal:  142,
      restaurationsPeriode: 3,
      evolution: [
        {mois:'Mai 24',archives:8},{mois:'Juin 24',archives:11},{mois:'Juil 24',archives:6},
        {mois:'Aoû 24',archives:4},{mois:'Sep 24',archives:14},{mois:'Oct 24',archives:17},
        {mois:'Nov 24',archives:12},{mois:'Déc 24',archives:7},{mois:'Jan 25',archives:9},
        {mois:'Fév 25',archives:13},{mois:'Mar 25',archives:19},{mois:'Avr 25',archives:18}
      ],
      parService:[
        {service:'État Civil',count:45},{service:'Urbanisme',count:38},
        {service:'Foncier',count:32},{service:'Fiscal',count:27}
      ]
    };
  }
}