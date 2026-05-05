-- ============================================================
-- MIGRATION INITIALE — Gestion Documents (Symfony 7.4 / MySQL)
-- Exécutez ce script après la création de votre base de données
-- puis lancez : php bin/console doctrine:migrations:migrate
-- ============================================================

-- Suppression dans l'ordre inverse des dépendances
DROP TABLE IF EXISTS `rappels`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `journaux`;
DROP TABLE IF EXISTS `versions_document`;
DROP TABLE IF EXISTS `historique_statuts`;
DROP TABLE IF EXISTS `dossiers`;
DROP TABLE IF EXISTS `roles_permissions`;
DROP TABLE IF EXISTS `utilisateurs_roles`;
DROP TABLE IF EXISTS `utilisateurs`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `statuts_dossier`;
DROP TABLE IF EXISTS `services`;

-- SERVICES
CREATE TABLE `services` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `nom`         VARCHAR(150) NOT NULL,
    `description` TEXT         NULL,
    `est_actif`   TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- STATUTS DOSSIER
CREATE TABLE `statuts_dossier` (
    `id`      INT AUTO_INCREMENT PRIMARY KEY,
    `code`    VARCHAR(50)  NOT NULL,
    `libelle` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PERMISSIONS
CREATE TABLE `permissions` (
    `id`  INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ROLES
CREATE TABLE `roles` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `nom`         VARCHAR(50) NOT NULL,
    `description` TEXT        NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ROLES ↔ PERMISSIONS
CREATE TABLE `roles_permissions` (
    `role_id`       INT NOT NULL,
    `permission_id` INT NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`)       REFERENCES `roles`(`id`)       ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- UTILISATEURS
CREATE TABLE `utilisateurs` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `nom`                 VARCHAR(100) NOT NULL,
    `prenom`              VARCHAR(100) NOT NULL,
    `email`               VARCHAR(180) NOT NULL UNIQUE,
    `telephone`           VARCHAR(30)  NULL,
    `mot_de_passe_hash`   TEXT         NOT NULL,
    `est_actif`           TINYINT(1)   NOT NULL DEFAULT 1,
    `est_liste_noire`     TINYINT(1)   NOT NULL DEFAULT 0,
    `motif_liste_noire`   TEXT         NULL,
    `derniere_connexion`  DATETIME     NULL,
    `type_utilisateur`    VARCHAR(50)  NOT NULL DEFAULT 'Agent',
    `est_supprime`        TINYINT(1)   NOT NULL DEFAULT 0,
    `service_id`          INT          NULL,
    FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- UTILISATEURS ↔ ROLES
CREATE TABLE `utilisateurs_roles` (
    `utilisateur_id` INT NOT NULL,
    `role_id`        INT NOT NULL,
    PRIMARY KEY (`utilisateur_id`, `role_id`),
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`role_id`)        REFERENCES `roles`(`id`)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DOSSIERS
CREATE TABLE `dossiers` (
    `id`                     VARCHAR(36)  NOT NULL PRIMARY KEY,
    `numero`                 VARCHAR(50)  NOT NULL UNIQUE,
    `titre`                  VARCHAR(255) NOT NULL,
    `description`            TEXT         NULL,
    `nom_citoyen`            VARCHAR(255) NOT NULL,
    `email_citoyen`          VARCHAR(180) NULL,
    `telephone_citoyen`      VARCHAR(30)  NULL,
    `motif_rejet`            TEXT         NULL,
    `date_depot`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_mise_a_jour_statut` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_archivage`         DATETIME     NULL,
    `statut_id`              INT          NOT NULL,
    `service_id`             INT          NOT NULL,
    `agent_id`               INT          NULL,
    FOREIGN KEY (`statut_id`)  REFERENCES `statuts_dossier`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`service_id`) REFERENCES `services`(`id`)        ON DELETE CASCADE,
    FOREIGN KEY (`agent_id`)   REFERENCES `utilisateurs`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- HISTORIQUE STATUTS
CREATE TABLE `historique_statuts` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `dossier_id`        VARCHAR(36) NOT NULL,
    `ancien_statut_id`  INT         NULL,
    `nouveau_statut_id` INT         NOT NULL,
    `commentaire`       TEXT        NULL,
    `date_changement`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `agent_id`          INT         NULL,
    FOREIGN KEY (`dossier_id`)        REFERENCES `dossiers`(`id`)        ON DELETE CASCADE,
    FOREIGN KEY (`ancien_statut_id`)  REFERENCES `statuts_dossier`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`nouveau_statut_id`) REFERENCES `statuts_dossier`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`agent_id`)          REFERENCES `utilisateurs`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VERSIONS DOCUMENT
CREATE TABLE `versions_document` (
    `id`              VARCHAR(36)  NOT NULL PRIMARY KEY,
    `dossier_id`      VARCHAR(36)  NOT NULL,
    `numero_version`  INT          NOT NULL DEFAULT 1,
    `nom_fichier`     VARCHAR(255) NOT NULL,
    `chemin_fichier`  VARCHAR(500) NOT NULL,
    `type_fichier`    VARCHAR(100) NULL,
    `taille_fichier`  INT          NULL,
    `empreinte_hash`  VARCHAR(255) NULL,
    `date_creation`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `est_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `utilisateur_id`  INT          NULL,
    `commentaire`     TEXT         NULL,
    FOREIGN KEY (`dossier_id`)     REFERENCES `dossiers`(`id`)     ON DELETE CASCADE,
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- JOURNAUX
CREATE TABLE `journaux` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id` INT         NULL,
    `module`         VARCHAR(100) NOT NULL,
    `action`         VARCHAR(100) NOT NULL,
    `details`        TEXT         NULL,
    `niveau_id`      INT          NOT NULL DEFAULT 1,
    `date_action`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `entite_id`      VARCHAR(36)  NULL,
    `adresse_ip`     VARCHAR(45)  NULL,
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTIFICATIONS
CREATE TABLE `notifications` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id`  INT          NULL,
    `titre`           VARCHAR(255) NOT NULL DEFAULT '',
    `message`         TEXT         NOT NULL,
    `type`            VARCHAR(20)  NOT NULL DEFAULT 'INFO',
    `dossier_id`      VARCHAR(36)  NULL,
    `numero_dossier`  VARCHAR(50)  NULL,
    `est_lue`         TINYINT(1)   NOT NULL DEFAULT 0,
    `est_supprimee`   TINYINT(1)   NOT NULL DEFAULT 0,
    `date_creation`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RAPPELS
CREATE TABLE `rappels` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id`  INT          NULL,
    `dossier_id`      VARCHAR(36)  NULL,
    `titre`           VARCHAR(255) NOT NULL,
    `objet`           VARCHAR(255) NULL,
    `description`     TEXT         NULL,
    `type`            VARCHAR(20)  NOT NULL DEFAULT 'RAPPEL',
    `statut`          VARCHAR(20)  NOT NULL DEFAULT 'ENVOYE',
    `date_rappel`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_envoi`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `est_effectue`    TINYINT(1)   NOT NULL DEFAULT 0,
    `tentatives`      INT          NOT NULL DEFAULT 0,
    `erreur`          TEXT         NULL,
    FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`dossier_id`)     REFERENCES `dossiers`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- DONNÉES INITIALES
-- ============================================================

-- Statuts
INSERT INTO `statuts_dossier` (`code`, `libelle`) VALUES
  ('RECU',      'Reçu'),
  ('EN_COURS',  'En cours de traitement'),
  ('TRANSFERE', 'Transféré'),
  ('REJETE',    'Rejeté'),
  ('TERMINE',   'Terminé'),
  ('ARCHIVE',   'Archivé');

-- Services
INSERT INTO `services` (`nom`, `description`, `est_actif`) VALUES
    ('Droit des Affaires',  'Contrats commerciaux, fusions-acquisitions, droit des sociétés', 1),
    ('Droit de la Famille', 'Divorce, succession, garde d''enfants, adoption',               1),
    ('Droit Pénal',         'Défense pénale, assistance aux victimes, procédures pénales',    1),
    ('Droit Immobilier',    'Transactions immobilières, litiges fonciers, baux commerciaux',  1);


-- Permissions
INSERT INTO `permissions` (`nom`) VALUES
  ('dossiers.voir'), ('dossiers.creer'), ('dossiers.modifier'), ('dossiers.supprimer'),
  ('dossiers.valider'), ('dossiers.rejeter'), ('archivage.archiver'), ('archivage.restaurer'),
  ('utilisateurs.voir'), ('utilisateurs.gerer'),
  ('statistiques.voir'), ('journaux.voir'), ('notifications.voir');

-- Rôles
INSERT INTO `roles` (`nom`, `description`) VALUES
  ('Administrateur', 'Accès complet au système'),
  ('Agent',          'Agent de traitement des dossiers'),
  ('Archiviste',     'Gestion des archives');

-- Permissions rôle Administrateur (toutes)
INSERT INTO `roles_permissions` (`role_id`, `permission_id`)
  SELECT 1, id FROM `permissions`;

-- Permissions rôle Agent
INSERT INTO `roles_permissions` (`role_id`, `permission_id`)
  SELECT 2, id FROM `permissions`
  WHERE `nom` IN ('dossiers.voir','dossiers.creer','dossiers.modifier','dossiers.valider','dossiers.rejeter','statistiques.voir','notifications.voir');

-- Permissions rôle Archiviste
INSERT INTO `roles_permissions` (`role_id`, `permission_id`)
  SELECT 3, id FROM `permissions`
  WHERE `nom` IN ('dossiers.voir','archivage.archiver','archivage.restaurer','statistiques.voir','notifications.voir');

-- ============================================================
-- UTILISATEURS PAR DÉFAUT (mots de passe hashés bcrypt)
-- admin@example.cm   / Admin1234!
-- agent@example.cm   / Agent1234!
-- archiviste@example.cm / Archiv1234!
-- ⚠️  Recréez les hashs via php bin/console doctrine:fixtures:load
-- ============================================================


/*
Patrick
mbogo@gmail.com
677000001
$2y$13$J4j5nQHqwISg0fCwunqHDOjDTG0M6daD6vusidjH1yWtImALU3TE.
1
0
NULL
2026-04-30 16:27:48
Administrateur
0
NULL*/

/*DUPONT
Jean
jean.dupont@ged.local
677000002
$2y$13$rGT60bZ.MjIGxXFWlqhcH.4SSlbtvYL5rWIJj4S9BfSW040Akj7he
1
0
NULL
2026-04-29 14:57:31
Agent
0
2*/

/*ATANGANA
Paul
atangana@gmail.com
677000003
$2y$13$oij99IyWtb68.NE9CwsEJOXd.CBwOmzprjsPhmhSjBmZel5I7kubu
1
0
NULL
2026-04-29 21:30:40
Agent
0
3*/

/*MARTIN
Sophie
martin@gmail.com
677000004
$2y$13$zzXll2nfe4X3WqgcZY5ppu/oaO92CdliQ7pWIXy4O1o8bj4nGU5hu
1
0
NULL
2026-04-30 14:25:57
Archiviste
0
1*/


