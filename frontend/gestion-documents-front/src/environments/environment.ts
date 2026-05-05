//frontend/gestion-documents-front/src/environments/environment.ts
/*export const environment = {
  production: false,
  apiUrl: 'http://localhost:5252'  // ← port vu dans dotnet run
};*/
// src/environments/environment.ts
// CORRECTIF EMULATEUR ANDROID :
// - Sur l'émulateur Android, "localhost" ou "127.0.0.1" pointe vers l'émulateur lui-même,
//   pas vers le PC hôte. Il faut utiliser 10.0.2.2 pour atteindre le PC hôte.
// - Sur un vrai appareil physique connecté en Wi-Fi, utiliser l'IP du PC (ex: 192.168.1.187).
// - Pour le navigateur web (ng serve), utiliser localhost.

/*export const environment = {
  production: false,
  // Pour tester dans le navigateur (ng serve) :
  apiUrl: 'http://localhost:5252'

  // Pour l'émulateur Android Studio (10.0.2.2 = alias de localhost du PC hôte) :
  //apiUrl: 'http://10.0.2.2:5252'

  // Pour un appareil physique (remplacer par l'IP de votre PC sur le réseau Wi-Fi) :
  //apiUrl: 'http://192.168.1.187:5252'
};*/

export const environment = {
  production: false,
  apiUrl: 'http://localhost:8001'
  //apiUrl: 'http://localhost:5252'
};