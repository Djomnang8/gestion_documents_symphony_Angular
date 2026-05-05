// src/app/modules/agent/statistiques/statistiques.ts
// Correction : exporterPdf() utilise maintenant HttpClient (avec JWT header)
// au lieu de window.open() qui ne peut pas envoyer le header Authorization.
import {
  Component, OnInit, OnDestroy, signal, inject, AfterViewInit,
  ElementRef, ViewChildren, QueryList
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { StatistiqueService, StatsDossiers, StatistiquesFiltre } from '../../../core/services/statistique';

@Component({
  selector: 'app-statistiques',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './statistiques.html',
  styleUrl: './statistiques.css'
})
export class StatistiquesComponent implements OnInit, OnDestroy, AfterViewInit {
  private svc = inject(StatistiqueService);

  stats      = signal<StatsDossiers | null>(null);
  chargement = signal(true);
  erreur     = signal('');

  periode: '7j' | '30j' | '90j' | 'custom' = '30j';
  dateDebut = '';
  dateFin   = '';
  serviceId: number | undefined = undefined;

  periodes = [
    { label: '7 jours',      value: '7j'     as const },
    { label: '30 jours',     value: '30j'    as const },
    { label: '90 jours',     value: '90j'    as const },
    { label: 'Personnalisé', value: 'custom' as const }
  ];

  @ViewChildren('canvasBarres,canvasCamembert,canvasCourbe,canvasGauge,canvasAire')
  canvases!: QueryList<ElementRef<HTMLCanvasElement>>;

  private canvasReady = false;
  private dataReady   = false;

  ngOnInit()        { this.charger(); }
  ngAfterViewInit() { this.canvasReady = true; if (this.dataReady) this.dessinerGraphiques(); }
  ngOnDestroy()     {}

  charger() {
    this.chargement.set(true); this.erreur.set('');
    const filtre: StatistiquesFiltre = {
      periode:   this.periode,
      dateDebut: this.periode === 'custom' ? this.dateDebut : undefined,
      dateFin:   this.periode === 'custom' ? this.dateFin   : undefined,
      serviceId: this.serviceId
    };
    this.svc.getStatsDossiers(filtre).subscribe({
      next: s => {
        this.stats.set(s); this.chargement.set(false);
        this.dataReady = true;
        if (this.canvasReady) setTimeout(() => this.dessinerGraphiques(), 50);
      },
      error: () => {
        this.stats.set(this.demo()); this.chargement.set(false);
        this.dataReady = true;
        if (this.canvasReady) setTimeout(() => this.dessinerGraphiques(), 50);
      }
    });
  }

  appliquerFiltres() { this.charger(); }

  labelPeriode(): string {
    const m: Record<string, string> = {
      '7j':'7 derniers jours','30j':'30 derniers jours',
      '90j':'90 derniers jours','custom':'Période personnalisée'
    };
    return m[this.periode] ?? '30 derniers jours';
  }

  // ── Export PDF via HttpClient (le JWT header est envoyé automatiquement par l'interceptor)
  exporterPdf() {
    const filtre: StatistiquesFiltre = { periode: this.periode, serviceId: this.serviceId };
    this.svc.exporterPdf(filtre).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a   = document.createElement('a');
        a.href    = url;
        a.download = `rapport_statistiques_${new Date().toISOString().slice(0,10)}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
      },
      error: (err) => {
        console.error('Erreur export PDF', err);
        alert(`Erreur export PDF : ${err.status} — vérifiez que l'API est démarrée.`);
      }
    });
  }

  exporterExcel() {
    const filtre: StatistiquesFiltre = { periode: this.periode, serviceId: this.serviceId };
    this.svc.exporterExcel(filtre).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a   = document.createElement('a');
        a.href    = url;
        a.download = `statistiques_${new Date().toISOString().slice(0,10)}.xlsx`;
        a.click();
        URL.revokeObjectURL(url);
      },
      error: () => alert('Erreur export Excel.')
    });
  }

  getTendanceClass(val: number): string {
    return val > 0 ? 'tendance-up' : val < 0 ? 'tendance-down' : 'tendance-flat';
  }
  getTendanceIcon(val: number): string { return val > 0 ? '↑' : val < 0 ? '↓' : '→'; }
  formatTendance(val: number): string  { return `${this.getTendanceIcon(val)} ${Math.abs(val)}%`; }

  // ── Graphiques Canvas natifs ─────────────────────────────
  private dessinerGraphiques() {
    const s = this.stats(); if (!s) return;
    const els = this.canvases.toArray(); if (els.length < 5) return;
    this.dessinerBarres    (els[0].nativeElement, s);
    this.dessinerCamembert (els[1].nativeElement, s);
    this.dessinerCourbe    (els[2].nativeElement, s);
    this.dessinerGauge     (els[3].nativeElement, s);
    this.dessinerAire      (els[4].nativeElement, s);
  }

  private dessinerBarres(canvas: HTMLCanvasElement, s: StatsDossiers) {
    const ctx = canvas.getContext('2d')!;
    canvas.width = canvas.parentElement!.clientWidth; canvas.height = 220;
    const W = canvas.width, H = canvas.height;
    ctx.clearRect(0,0,W,H);
    const data = s.dossiersParStatut; if (!data.length) return;
    const colors: Record<string,string> = { RECU:'#1565C0',TERMINE:'#43A047',REJETE:'#E53935',EN_COURS:'#5E35B1',TRANSFERE:'#FF8F00' };
    const pad = { l:44,r:16,t:20,b:36 };
    const maxV = Math.max(...data.map(d => d.count), 1);
    const bW = Math.min(44, (W-pad.l-pad.r)/data.length - 8);
    for(let i=0;i<=4;i++) {
      const y = pad.t + ((H-pad.t-pad.b)/4)*i;
      ctx.strokeStyle='#E0E0E0'; ctx.beginPath(); ctx.moveTo(pad.l,y); ctx.lineTo(W-pad.r,y); ctx.stroke();
      ctx.fillStyle='#9E9E9E'; ctx.font='10px sans-serif'; ctx.textAlign='right';
      ctx.fillText(String(Math.round(maxV-(maxV/4)*i)), pad.l-4, y+4);
    }
    data.forEach((d,i) => {
      const x = pad.l + i*(W-pad.l-pad.r)/data.length + ((W-pad.l-pad.r)/data.length-bW)/2;
      const bH = ((H-pad.t-pad.b)/maxV)*d.count;
      const y  = H-pad.b-bH;
      ctx.fillStyle = colors[d.code] ?? '#78909C';
      ctx.beginPath(); ctx.roundRect(x,y,bW,bH,4); ctx.fill();
      ctx.fillStyle='#424242'; ctx.font='bold 11px sans-serif'; ctx.textAlign='center';
      ctx.fillText(String(d.count), x+bW/2, y-5);
      ctx.fillStyle='#757575'; ctx.font='9px sans-serif';
      ctx.fillText(d.statut.substring(0,8), x+bW/2, H-pad.b+14);
    });
  }

  private dessinerCamembert(canvas: HTMLCanvasElement, s: StatsDossiers) {
    const ctx = canvas.getContext('2d')!;
    canvas.width = canvas.parentElement!.clientWidth; canvas.height = 220;
    const W = canvas.width, H = canvas.height;
    ctx.clearRect(0,0,W,H);
    const data  = s.repartitionParService; if (!data.length) return;
    const cols  = ['#1565C0','#43A047','#E53935','#5E35B1','#FFB300','#00897B'];
    const total = data.reduce((s,d) => s+d.count, 0);
    const cx = W*0.42, cy = H/2, r = Math.min(cx, H/2)-18;
    let angle = -Math.PI/2;
    data.forEach((d,i) => {
      const slice = (d.count/total)*Math.PI*2;
      ctx.beginPath(); ctx.moveTo(cx,cy); ctx.arc(cx,cy,r,angle,angle+slice); ctx.closePath();
      ctx.fillStyle = cols[i%cols.length]; ctx.fill();
      ctx.strokeStyle='#fff'; ctx.lineWidth=2; ctx.stroke();
      angle += slice;
    });
    let ly = 28;
    data.slice(0,6).forEach((d,i) => {
      const lx = W*0.88-110;
      ctx.fillStyle = cols[i%cols.length]; ctx.fillRect(lx,ly-8,12,12);
      ctx.fillStyle='#424242'; ctx.font='10px sans-serif'; ctx.textAlign='left';
      const pct = Math.round(d.count/total*100);
      ctx.fillText(`${d.service.substring(0,14)} (${pct}%)`, lx+16, ly+2);
      ly += 22;
    });
  }

  private dessinerCourbe(canvas: HTMLCanvasElement, s: StatsDossiers) {
    const ctx = canvas.getContext('2d')!;
    canvas.width = canvas.parentElement!.clientWidth; canvas.height = 220;
    const W = canvas.width, H = canvas.height;
    ctx.clearRect(0,0,W,H);
    const data = s.delaiParMois; if (!data.length) return;
    const pad = { l:44,r:16,t:24,b:36 };
    const maxV = Math.max(...data.map(d => d.delai), 10);
    for(let i=0;i<=4;i++){
      const y=pad.t+((H-pad.t-pad.b)/4)*i;
      ctx.strokeStyle='#E0E0E0'; ctx.beginPath(); ctx.moveTo(pad.l,y); ctx.lineTo(W-pad.r,y); ctx.stroke();
      ctx.fillStyle='#9E9E9E'; ctx.font='10px sans-serif'; ctx.textAlign='right';
      ctx.fillText(Math.round(maxV-(maxV/4)*i)+'j', pad.l-4, y+4);
    }
    const seuilY = pad.t+((maxV-7)/maxV)*(H-pad.t-pad.b);
    ctx.setLineDash([5,4]); ctx.strokeStyle='#E53935'; ctx.lineWidth=1.5;
    ctx.beginPath(); ctx.moveTo(pad.l,seuilY); ctx.lineTo(W-pad.r,seuilY); ctx.stroke();
    ctx.setLineDash([]);
    ctx.fillStyle='#E53935'; ctx.font='9px sans-serif'; ctx.textAlign='left';
    ctx.fillText('Seuil 7j', W-pad.r-36, seuilY-3);
    const pts = data.map((d,i) => ({ x: pad.l+i*(W-pad.l-pad.r)/(data.length-1), y: pad.t+((maxV-d.delai)/maxV)*(H-pad.t-pad.b) }));
    const g = ctx.createLinearGradient(0,pad.t,0,H-pad.b);
    g.addColorStop(0,'#1565C044'); g.addColorStop(1,'#1565C008');
    ctx.beginPath(); ctx.moveTo(pts[0].x, H-pad.b);
    pts.forEach(p => ctx.lineTo(p.x,p.y));
    ctx.lineTo(pts[pts.length-1].x, H-pad.b); ctx.closePath(); ctx.fillStyle=g; ctx.fill();
    ctx.beginPath(); ctx.moveTo(pts[0].x, pts[0].y);
    pts.forEach(p => ctx.lineTo(p.x,p.y));
    ctx.strokeStyle='#1565C0'; ctx.lineWidth=2.5; ctx.stroke();
    pts.forEach((p,i) => {
      ctx.beginPath(); ctx.arc(p.x,p.y,4,0,Math.PI*2);
      ctx.fillStyle='#fff'; ctx.fill(); ctx.strokeStyle='#1565C0'; ctx.lineWidth=2; ctx.stroke();
      if (i%2===0) { ctx.fillStyle='#9E9E9E'; ctx.font='9px sans-serif'; ctx.textAlign='center'; ctx.fillText(data[i].mois, p.x, H-pad.b+14); }
    });
  }

  private dessinerGauge(canvas: HTMLCanvasElement, s: StatsDossiers) {
    const ctx = canvas.getContext('2d')!;
    canvas.width = canvas.parentElement!.clientWidth; canvas.height = 220;
    const W = canvas.width, H = canvas.height;
    ctx.clearRect(0,0,W,H);
    const pct = Math.min(s.tauxRejet, 100);
    const cx = W/2, cy = H/2+20, r = Math.min(W,H)*0.35;
    const sA = Math.PI*0.8, eA = Math.PI*2.2;
    const fA = sA + (eA-sA)*(pct/100);
    const col = pct > 20 ? '#E53935' : pct > 10 ? '#FFB300' : '#43A047';
    ctx.beginPath(); ctx.arc(cx,cy,r,sA,eA); ctx.strokeStyle='#E0E0E0'; ctx.lineWidth=14; ctx.lineCap='round'; ctx.stroke();
    ctx.beginPath(); ctx.arc(cx,cy,r,sA,fA); ctx.strokeStyle=col; ctx.lineWidth=14; ctx.lineCap='round'; ctx.stroke();
    ctx.fillStyle=col; ctx.font=`bold ${Math.round(r*0.45)}px sans-serif`;
    ctx.textAlign='center'; ctx.textBaseline='middle';
    ctx.fillText(`${pct}%`, cx, cy-8);
    ctx.fillStyle='#757575'; ctx.font='12px sans-serif'; ctx.fillText('Taux de rejet', cx, cy+r*0.55);
    ctx.fillStyle=col; ctx.font='bold 11px sans-serif';
    ctx.fillText(pct>20?'⚠ Élevé':pct>10?'▲ Modéré':'✓ Normal', cx, cy+r*0.85);
  }

  private dessinerAire(canvas: HTMLCanvasElement, s: StatsDossiers) {
    const ctx = canvas.getContext('2d')!;
    canvas.width = canvas.parentElement!.clientWidth; canvas.height = 200;
    const W = canvas.width, H = canvas.height;
    ctx.clearRect(0,0,W,H);
    const data = s.evolutionMensuelle; if (!data.length) return;
    const pad = { l:44,r:120,t:20,b:36 };
    const series = [
      { key:'recu'   as const, label:'Reçus',   col:'#1565C0' },
      { key:'traite' as const, label:'Traités',  col:'#43A047' },
      { key:'rejete' as const, label:'Rejetés',  col:'#E53935' }
    ];
    const maxV = Math.max(...data.flatMap(d => [d.recu,d.traite,d.rejete]), 1);
    for(let i=0;i<=4;i++){
      const y=pad.t+((H-pad.t-pad.b)/4)*i;
      ctx.strokeStyle='#E0E0E0'; ctx.beginPath(); ctx.moveTo(pad.l,y); ctx.lineTo(W-pad.r,y); ctx.stroke();
      ctx.fillStyle='#9E9E9E'; ctx.font='10px sans-serif'; ctx.textAlign='right';
      ctx.fillText(String(Math.round(maxV-(maxV/4)*i)), pad.l-4, y+4);
    }
    series.forEach(serie => {
      const pts = data.map((d,i) => ({ x: pad.l+i*(W-pad.l-pad.r)/(data.length-1), y: pad.t+((maxV-d[serie.key])/maxV)*(H-pad.t-pad.b) }));
      const g = ctx.createLinearGradient(0,pad.t,0,H-pad.b);
      g.addColorStop(0,serie.col+'33'); g.addColorStop(1,serie.col+'05');
      ctx.beginPath(); ctx.moveTo(pts[0].x, H-pad.b);
      pts.forEach(p => ctx.lineTo(p.x,p.y));
      ctx.lineTo(pts[pts.length-1].x, H-pad.b); ctx.closePath(); ctx.fillStyle=g; ctx.fill();
      ctx.beginPath(); ctx.moveTo(pts[0].x,pts[0].y);
      pts.forEach(p => ctx.lineTo(p.x,p.y));
      ctx.strokeStyle=serie.col; ctx.lineWidth=2.5; ctx.stroke();
    });
    data.forEach((d,i) => {
      const x = pad.l+i*(W-pad.l-pad.r)/(data.length-1);
      if(i%2===0){ ctx.fillStyle='#9E9E9E'; ctx.font='9px sans-serif'; ctx.textAlign='center'; ctx.fillText(d.mois,x,H-pad.b+14); }
    });
    series.forEach((s,i) => {
      const lx = W-pad.r+10, ly = 40+i*28;
      ctx.fillStyle=s.col; ctx.fillRect(lx,ly-7,14,14);
      ctx.fillStyle='#424242'; ctx.font='11px sans-serif'; ctx.textAlign='left'; ctx.fillText(s.label, lx+18, ly+4);
    });
  }

  private demo(): StatsDossiers {
    return {
      totalDossiers:248, tauxTraitement:71.4, delaiMoyen:4.8, tauxRejet:8.5,
      tendanceTotalDossiers:12.3, tendanceTauxTraitement:5.2, tendanceDelaiMoyen:-1.4, tendanceTauxRejet:-2.1,
      dossiersParStatut:[
        {statut:'Reçu',code:'RECU',count:42},{statut:'En cours',code:'EN_COURS',count:28},
        {statut:'Terminé',code:'TERMINE',count:155},{statut:'Rejeté',code:'REJETE',count:15},{statut:'Transféré',code:'TRANSFERE',count:8}
      ],
      repartitionParService:[{service:'État Civil',count:88},{service:'Urbanisme',count:62},{service:'Foncier',count:54},{service:'Fiscal',count:44}],
      delaiParMois:[{mois:'Mai 24',delai:8.2},{mois:'Juin 24',delai:7.4},{mois:'Juil 24',delai:6.8},{mois:'Aoû 24',delai:5.9},{mois:'Sep 24',delai:6.1},{mois:'Oct 24',delai:5.4},{mois:'Nov 24',delai:4.8},{mois:'Déc 24',delai:5.2},{mois:'Jan 25',delai:4.6},{mois:'Fév 25',delai:4.1},{mois:'Mar 25',delai:4.4},{mois:'Avr 25',delai:4.8}],
      evolutionMensuelle:[
        {mois:'Mai 24',recu:18,traite:12,rejete:2},{mois:'Juin 24',recu:22,traite:16,rejete:3},
        {mois:'Juil 24',recu:15,traite:13,rejete:1},{mois:'Aoû 24',recu:12,traite:10,rejete:1},
        {mois:'Sep 24',recu:28,traite:20,rejete:3},{mois:'Oct 24',recu:31,traite:25,rejete:4},
        {mois:'Nov 24',recu:26,traite:22,rejete:2},{mois:'Déc 24',recu:18,traite:15,rejete:1},
        {mois:'Jan 25',recu:24,traite:19,rejete:2},{mois:'Fév 25',recu:27,traite:23,rejete:3},
        {mois:'Mar 25',recu:32,traite:28,rejete:2},{mois:'Avr 25',recu:29,traite:21,rejete:3}
      ]
    };
  }
}