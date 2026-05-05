# Backend Symfony — Gestion Documents

## Versions requises
- PHP 8.2+
- Symfony CLI 5.x
- Composer 2.x
- MySQL / MariaDB 10.4+

---

## Installation complète

### 1. Installer les dépendances
```bash
cd backend
composer install
```

### 2. Configurer l'environnement
Éditez `.env` :
```
DATABASE_URL="mysql://root:@127.0.0.1:3306/gestion_documents?serverVersion=10.4.32-MariaDB&charset=utf8mb4"
MAILER_DSN="smtp://votre-email%40gmail.com:votre-mot-de-passe-app@smtp.gmail.com:587?encryption=tls"
JWT_PASSPHRASE=gestion_docs_jwt_passphrase
```

### 3. Créer la base de données
```bash
php bin/console doctrine:database:create
```

### 4. Créer les tables (Option A : via le SQL fourni)
```bash
mysql -u root gestion_documents < migrations/schema_initial.sql
```

### 4. Créer les tables (Option B : via Doctrine)
```bash
php bin/console doctrine:schema:create
```

### 5. Générer les clés JWT
```bash
php bin/console lexik:jwt:generate-keypair
```

### 6. Charger les données initiales (utilisateurs et données de base)
```bash
php bin/console doctrine:fixtures:load --no-interaction
```
> Cela crée les comptes : admin@symphony.cm / Admin1234! | agent@symphony.cm / Agent1234! | archiviste@symphony.cm / Archiv1234!

### 7. Lancer le serveur
```bash
symfony serve --no-tls --port=8000
```
ou
```bash
php -S localhost:8000 -t public
```

---

## API Routes

### Auth
| Méthode | Route | Accès |
|---------|-------|-------|
| POST | `/api/auth/login` | Public |

### Dossiers
| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/dossiers` | Liste paginée |
| GET | `/api/dossiers/stats` | Statistiques agent |
| GET | `/api/dossiers/en-retard` | Dossiers en retard |
| GET | `/api/dossiers/export-csv` | Export CSV |
| GET | `/api/dossiers/archives` | Archives |
| GET | `/api/dossiers/suivi/{numero}` | Suivi citoyen (public) |
| GET | `/api/dossiers/{id}` | Détail dossier |
| POST | `/api/dossiers` | Créer dossier |
| POST | `/api/dossiers/public/depot` | Dépôt public citoyen |
| PATCH | `/api/dossiers/{id}/statut` | Changer statut |
| PATCH | `/api/dossiers/{id}/transferer` | Transférer |
| POST | `/api/dossiers/{id}/archiver` | Archiver |
| POST | `/api/dossiers/{id}/upload` | Uploader document |

### Archivage
| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/archivage/kpi` | KPIs archiviste |
| GET | `/api/archivage/a-archiver` | Dossiers à archiver |
| POST | `/api/archivage/{dossierId}` | Archiver avec fusion |
| GET | `/api/archivage/archives` | Rechercher archives |

### Utilisateurs
| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/utilisateurs` | Liste |
| GET | `/api/utilisateurs/roles` | Rôles disponibles |
| GET | `/api/utilisateurs/services` | Services disponibles |
| GET | `/api/utilisateurs/{id}` | Détail |
| POST | `/api/utilisateurs` | Créer |
| PUT | `/api/utilisateurs/{id}` | Modifier |
| DELETE | `/api/utilisateurs/{id}` | Supprimer (soft) |
| PUT | `/api/utilisateurs/{id}/activer` | Activer |
| PUT | `/api/utilisateurs/{id}/desactiver` | Désactiver |
| PUT | `/api/utilisateurs/{id}/listenoire` | Liste noire |
| PUT | `/api/utilisateurs/{id}/role` | Changer rôle |
| PUT | `/api/utilisateurs/{id}/mot-de-passe` | Changer mdp |

### Notifications
| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/notifications?onglet=toutes\|non-lues\|rappels` | Liste + compteurs |
| PUT | `/api/notifications/{id}/lue` | Marquer lue |
| PUT | `/api/notifications/tout-lire` | Tout marquer lu |
| DELETE | `/api/notifications/{id}` | Supprimer |
| GET | `/api/notifications/emails` | Historique emails |
| POST | `/api/notifications/emails/{id}/retry` | Renvoyer email |

### Journaux
| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/journaux` | Liste filtrée + paginée |
| GET | `/api/journaux/mes-activites` | Activités de l'agent |
| GET | `/api/journaux/modules` | Liste des modules |
| GET | `/api/journaux/export` | Export CSV |

### Statistiques
| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/statistiques/dashboard` | Dashboard admin |
| GET | `/api/statistiques/dossiers` | Stats dossiers agent |
| GET | `/api/statistiques/archiviste` | Stats archiviste |
| GET | `/api/statistiques/export/pdf` | Export PDF |
| GET | `/api/statistiques/export/excel` | Export Excel |

### Fichiers
| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/fichiers/download?chemin=...` | Télécharger fichier |
| GET | `/api/fichiers/preview/{dossierId}` | Prévisualiser |

### Versions
| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/versions/{dossierId}` | Liste des versions |
| POST | `/api/versions/{id}/restaurer` | Restaurer une version |

### Public (sans authentification)
| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/public/services` | Liste services |
| GET | `/api/services` | Liste services (alias) |
| GET | `/api/public/suivi/{numero}` | Suivi dossier citoyen |
| GET | `/api/dossiers/suivi/{numero}` | Suivi dossier citoyen (alias C#) |
| POST | `/api/dossiers/public/depot` | Dépôt public |

### Profil
| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/profil` | Mon profil |
| PUT | `/api/profil` | Modifier mon profil |

---

## Export PDF / Excel
Pour activer les exports enrichis, installez les bibliothèques optionnelles :
```bash
# PDF
composer require dompdf/dompdf

# Excel
composer require phpoffice/phpspreadsheet
```
Sans ces bibliothèques, les endpoints retournent respectivement du HTML et du CSV.

---

## CORS
La configuration CORS est dans `config/packages/nelmio_cors.yaml`.
Adaptez `CORS_ALLOW_ORIGIN` dans `.env` selon votre frontend Angular.
