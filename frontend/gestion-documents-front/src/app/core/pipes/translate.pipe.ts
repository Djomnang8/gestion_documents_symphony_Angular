// src/app/core/pipes/translate.pipe.ts
import { Pipe, PipeTransform, inject, ChangeDetectorRef, OnDestroy, effect } from '@angular/core';
import { TranslationService } from '../services/translation.service';

@Pipe({ name: 'translate', standalone: true, pure: false })
export class TranslatePipe implements PipeTransform, OnDestroy {
  private svc = inject(TranslationService);
  private cdr = inject(ChangeDetectorRef);

  private effectRef = effect(() => {
    this.svc.currentLang();   // signal tracké
    this.svc.translations();  // signal tracké
    this.cdr.markForCheck();  // forcer le re-rendu
  });

  transform(key: string, params?: Record<string, any>): string {
    return this.svc.translate(key, params);
  }

  ngOnDestroy(): void { this.effectRef.destroy(); }
}