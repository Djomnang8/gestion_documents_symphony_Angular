// src/app/shared/language-switcher/language-switcher.ts
import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TranslationService } from '../../core/services/translation.service';

@Component({
  selector: 'app-language-switcher',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './language-switcher.html',
  styleUrls: ['./language-switcher.css']
})
export class LanguageSwitcherComponent {
  private svc = inject(TranslationService);
  currentLang = this.svc.currentLang; // signal direct

  setLang(lang: string): void {
    if (lang === 'fr' || lang === 'en') this.svc.setLanguage(lang);
  }
}