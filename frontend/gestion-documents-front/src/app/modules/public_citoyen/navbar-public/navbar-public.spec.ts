import { ComponentFixture, TestBed } from '@angular/core/testing';

import { NavbarPublic } from './navbar-public';

describe('NavbarPublic', () => {
  let component: NavbarPublic;
  let fixture: ComponentFixture<NavbarPublic>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NavbarPublic],
    }).compileComponents();

    fixture = TestBed.createComponent(NavbarPublic);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
