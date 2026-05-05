import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.gestion.documents',
  appName: 'Gestion Documents',
  webDir: 'dist/gestion-documents-front/browser',
  android: {
    // Permet le trafic HTTP non chiffré vers l'API locale
    allowMixedContent: true,
  },
  plugins: {
    // CORRECTIF NAVBAR MOBILE :
    // EdgeToEdge permet de déclarer que l'appli gère elle-même
    // les safe areas (encoche, barre de statut Android).
    // Capacitor transmet ensuite env(safe-area-inset-top) au CSS.
    EdgeToEdge: {
      backgroundColor: '#ffffff',
    },
  },
};

export default config;