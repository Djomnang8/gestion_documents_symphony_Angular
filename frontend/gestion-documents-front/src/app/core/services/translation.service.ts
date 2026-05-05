// src/app/core/services/translation.service.ts
// ══════════════════════════════════════════════════════════════
// SOLUTION DÉFINITIVE — Angular 21 + SSR activé
//
// Problème racine : le serveur SSR intercepte TOUTES les requêtes
// (y compris /assets/i18n/fr.json) et redirige vers le router HTML.
// Ni HttpClient ni fetch() natif ne fonctionnent.
//
// Solution : importer les traductions comme modules TypeScript.
// Zéro HTTP, 100% synchrone, compatible SSR.
//
// PROPAGATION AUTOMATIQUE vers les espaces employés :
// Ce service est providedIn:'root' (singleton).
// La langue choisie sur la page Login est sauvegardée dans
// localStorage. Au démarrage de n'importe quelle page suivante,
// le constructeur lit localStorage et restaure la langue.
// Résultat : si l'employé choisit EN sur Login, toutes ses pages
// (Agent / Archiviste / Admin) s'affichent automatiquement en EN.
// ══════════════════════════════════════════════════════════════

import { Injectable, signal, effect } from '@angular/core';
import { FR } from './i18n/fr';
import { EN } from './i18n/en';

export type Lang = 'fr' | 'en';

const DICTIONARIES: Record<Lang, Record<string, string>> = { fr: FR, en: EN };

@Injectable({ providedIn: 'root' })
export class TranslationService {

  /** Signal de la langue active */
  currentLang = signal<Lang>('fr');

  /** Signal du dictionnaire courant — le pipe s'y abonne */
  private dictSignal = signal<Record<string, string>>(FR);
  readonly translations = this.dictSignal.asReadonly();

  constructor() {
    // Restaurer la langue sauvegardée dans localStorage
    // (try/catch car SSR n'a pas accès à localStorage)
    try {
      const saved = localStorage.getItem('lang') as Lang | null;
      if (saved === 'fr' || saved === 'en') {
        this.currentLang.set(saved);
        this.dictSignal.set(DICTIONARIES[saved]);
      }
    } catch { /* SSR — ignorer */ }

    // Synchroniser dictionnaire + localStorage à chaque changement de langue
    effect(() => {
      const lang = this.currentLang();
      this.dictSignal.set(DICTIONARIES[lang]);
      try { localStorage.setItem('lang', lang); } catch { /* SSR */ }
    });
  }

  translate(key: string, params?: Record<string, any>): string {
    let text = this.dictSignal()[key];
    if (text === undefined) return key; // clé manquante → retourner la clé brute (utile en dev)
    if (params) {
      Object.entries(params).forEach(([k, v]) => {
        text = text.replace(new RegExp(`{{${k}}}`, 'g'), String(v));
      });
    }
    return text;
  }

  setLanguage(lang: Lang): void { this.currentLang.set(lang); }
  toggle(): void { this.setLanguage(this.currentLang() === 'fr' ? 'en' : 'fr'); }
}