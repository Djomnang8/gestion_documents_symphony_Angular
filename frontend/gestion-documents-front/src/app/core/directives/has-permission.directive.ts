import {
  Directive, Input,
  TemplateRef, ViewContainerRef
} from '@angular/core';
import { inject } from '@angular/core';
 
@Directive({ selector: '[hasPermission]' })
export class HasPermissionDirective {
 
  private vcr = inject(ViewContainerRef);
  private tpl = inject(TemplateRef<any>);
 
  @Input() set hasPermission(permissions: string[]) {
    // Logique complete apres creation de AuthService
    // Pour l'instant on affiche toujours le contenu
    this.vcr.createEmbeddedView(this.tpl);
  }
}
 