import { TestBed } from '@angular/core/testing';

import { Statistique } from './statistique';

describe('Statistique', () => {
  let service: Statistique;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(Statistique);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
