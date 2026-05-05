import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Depot } from './depot';

describe('Depot', () => {
  let component: Depot;
  let fixture: ComponentFixture<Depot>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Depot],
    }).compileComponents();

    fixture = TestBed.createComponent(Depot);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
