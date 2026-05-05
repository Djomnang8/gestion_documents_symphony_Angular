// src/app/app.config.server.ts
import { mergeApplicationConfig, ApplicationConfig } from '@angular/core';
import { provideServerRendering, withRoutes } from '@angular/ssr';
import { appConfig } from './app.config';
import { serverRouteConfig } from './app.routes';

const serverConfig: ApplicationConfig = {
  providers: [
    // CORRECTIF : withRoutes(serverRouteConfig) déclare toutes les routes en RenderMode.Client
    // Cela permet le build --outputMode=static compatible avec Capacitor/Android
    provideServerRendering(withRoutes(serverRouteConfig))
  ]
};

export const config = mergeApplicationConfig(appConfig, serverConfig);