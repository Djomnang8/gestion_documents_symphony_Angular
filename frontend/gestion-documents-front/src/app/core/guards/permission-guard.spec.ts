// permission-guard.spec.ts
import { TestBed } from '@angular/core/testing';
import { CanActivateFn } from '@angular/router';
import { permissionGuard } from './permission-guard';

describe('permissionGuard', () => {
  // On appelle la factory avec un argument pour obtenir le vrai CanActivateFn
  const executeGuard: CanActivateFn = (...guardParameters) =>
    TestBed.runInInjectionContext(() =>
      permissionGuard('test-permission')(...guardParameters)  // ← appel factory
    );

  beforeEach(() => {
    TestBed.configureTestingModule({});
  });

  it('should be created', () => {
    expect(executeGuard).toBeTruthy();
  });
});