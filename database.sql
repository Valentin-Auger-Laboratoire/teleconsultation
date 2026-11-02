-- SantéConnect : structure de la base + comptes de démonstration fictifs
-- À importer dans une base VIDE (ex. "santeconnect") via phpMyAdmin > Importer,
-- ou : mysql -u root santeconnect < database.sql

SET NAMES utf8mb4;

CREATE TABLE utilisateurs (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prenom            VARCHAR(100) NOT NULL,
    nom               VARCHAR(100) NOT NULL,
    email             VARCHAR(190) NOT NULL UNIQUE,
    password          VARCHAR(255) NOT NULL,                -- hash password_hash()
    role              ENUM('patient', 'medecin') NOT NULL,
    specialite        VARCHAR(100) NULL,
    code_verification CHAR(6) NULL,
    code_expiration   DATETIME NULL,                        -- code valable 15 minutes
    tentatives_code   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    est_verifie       TINYINT(1) NOT NULL DEFAULT 0,
    date_creation     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE creneaux (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_medecin  INT UNSIGNED NOT NULL,
    date_heure  DATETIME NOT NULL,
    disponible  TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY creneau_unique (id_medecin, date_heure),
    CONSTRAINT fk_creneau_medecin FOREIGN KEY (id_medecin) REFERENCES utilisateurs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un rendez-vous confirmé est aussi la consultation (visio, chat, facture)
CREATE TABLE rendez_vous (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_patient      INT UNSIGNED NOT NULL,
    id_medecin      INT UNSIGNED NOT NULL,
    id_creneau      INT UNSIGNED NULL UNIQUE,
    date_rdv        DATETIME NOT NULL,
    motif           VARCHAR(500) NOT NULL DEFAULT 'Non précisé',
    statut          ENUM('En attente', 'Confirmé', 'Terminé') NOT NULL DEFAULT 'En attente',
    cle_visio       CHAR(32) NOT NULL,                      -- partie aléatoire des identifiants PeerJS
    montant         DECIMAL(8,2) NOT NULL DEFAULT 0,
    statut_paiement TINYINT(1) NOT NULL DEFAULT 0,          -- 0 = à régler, 1 = réglé (simulé)
    date_creation   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rdv_patient FOREIGN KEY (id_patient) REFERENCES utilisateurs (id) ON DELETE CASCADE,
    CONSTRAINT fk_rdv_medecin FOREIGN KEY (id_medecin) REFERENCES utilisateurs (id) ON DELETE CASCADE,
    CONSTRAINT fk_rdv_creneau FOREIGN KEY (id_creneau) REFERENCES creneaux (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_consultation INT UNSIGNED NOT NULL,                  -- = rendez_vous.id
    id_expediteur   INT UNSIGNED NOT NULL,
    contenu         TEXT NOT NULL,
    date_envoi      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_messages_consultation (id_consultation, date_envoi),
    CONSTRAINT fk_message_rdv FOREIGN KEY (id_consultation) REFERENCES rendez_vous (id) ON DELETE CASCADE,
    CONSTRAINT fk_message_expediteur FOREIGN KEY (id_expediteur) REFERENCES utilisateurs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ordonnances rédigées par le médecin (contenu) et fichiers envoyés par le patient (nom_fichier)
CREATE TABLE documents (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_patient    INT UNSIGNED NOT NULL,
    id_medecin    INT UNSIGNED NULL,
    id_rdv        INT UNSIGNED NULL,
    depose_par    ENUM('patient', 'medecin') NOT NULL,
    type_doc      VARCHAR(50) NOT NULL,
    contenu       TEXT NULL,
    nom_fichier   VARCHAR(64) NULL,                         -- nom aléatoire dans uploads/
    nom_original  VARCHAR(255) NULL,
    type_mime     VARCHAR(50) NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_doc_patient FOREIGN KEY (id_patient) REFERENCES utilisateurs (id) ON DELETE CASCADE,
    CONSTRAINT fk_doc_medecin FOREIGN KEY (id_medecin) REFERENCES utilisateurs (id) ON DELETE SET NULL,
    CONSTRAINT fk_doc_rdv FOREIGN KEY (id_rdv) REFERENCES rendez_vous (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Comptes de démonstration (personnes fictives, comptes déjà vérifiés)
--   Médecin : medecin.demo@exemple.com / Medecin-Demo-2026
--   Patient : patient.demo@exemple.com / Patient-Demo-2026
-- ------------------------------------------------------------
INSERT INTO utilisateurs (id, prenom, nom, email, password, role, specialite, est_verifie) VALUES
(1, 'Claire', 'Martin', 'medecin.demo@exemple.com', '$2y$10$7g5EdYeh75lWG1YmdY/uA.GmO7hCsUG5RUdlmo4Wfu574cWxHPtgi', 'medecin', 'Généraliste', 1),
(2, 'Lucas', 'Bernard', 'patient.demo@exemple.com', '$2y$10$LhkIsAq8z8J8TbNkAGk50ef95LA/MueYIHZrcxMxt9WPY/ZB4LWpu', 'patient', NULL, 1);

-- Quelques créneaux libres dans les prochains jours
INSERT INTO creneaux (id_medecin, date_heure, disponible) VALUES
(1, DATE_ADD(CURDATE(), INTERVAL '1 09:00' DAY_MINUTE), 1),
(1, DATE_ADD(CURDATE(), INTERVAL '1 14:30' DAY_MINUTE), 1),
(1, DATE_ADD(CURDATE(), INTERVAL '2 10:00' DAY_MINUTE), 1),
(1, DATE_ADD(CURDATE(), INTERVAL '3 16:00' DAY_MINUTE), 1);
