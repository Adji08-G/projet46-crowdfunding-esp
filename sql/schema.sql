-- =====================================================================
-- Projet n°46 : Plateforme web d'actuariat pour compagnie d'assurance
-- Master CCA - ESP Dakar
-- Script de création de la base de données + données de démonstration
-- Stack : MySQL / MariaDB (XAMPP)
-- =====================================================================

-- -----------------------------------------------------
-- 1. CREATION DE LA BASE
-- -----------------------------------------------------
DROP DATABASE IF EXISTS actuariat_assurance;
CREATE DATABASE actuariat_assurance
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE actuariat_assurance;

-- -----------------------------------------------------
-- 2. TABLE utilisateurs
-- Gère l'authentification et les 3 profils exigés par le cahier des
-- charges : administrateur, actuaire (utilisateur avancé), agent (standard)
-- -----------------------------------------------------
CREATE TABLE utilisateurs (
    id_utilisateur      INT AUTO_INCREMENT PRIMARY KEY,
    nom                 VARCHAR(80)  NOT NULL,
    prenom              VARCHAR(80)  NOT NULL,
    email               VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe        VARCHAR(255) NOT NULL,          -- password_hash() PHP
    role                ENUM('admin','utilisateur_avance','standard') NOT NULL DEFAULT 'standard',
    actif               TINYINT(1) NOT NULL DEFAULT 1,
    derniere_connexion  DATETIME NULL,
    date_creation       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 3. TABLE branches
-- Branches d'assurance (auto, habitation, vie, santé, ...)
-- -----------------------------------------------------
CREATE TABLE branches (
    id_branche      INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(10)  NOT NULL UNIQUE,
    libelle         VARCHAR(100) NOT NULL,
    type_branche    ENUM('IARD','VIE') NOT NULL DEFAULT 'IARD',
    date_creation   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 4. TABLE polices (contrats d'assurance) — ENTITÉ PRINCIPALE
-- -----------------------------------------------------
CREATE TABLE polices (
    id_police           INT AUTO_INCREMENT PRIMARY KEY,
    numero_police        VARCHAR(30)  NOT NULL UNIQUE,
    id_branche           INT NOT NULL,
    nom_assure            VARCHAR(150) NOT NULL,
    telephone_assure      VARCHAR(30)  NULL,
    date_effet            DATE NOT NULL,
    date_expiration       DATE NOT NULL,
    prime_pure            DECIMAL(14,2) NOT NULL DEFAULT 0,
    chargement_pct        DECIMAL(5,2)  NOT NULL DEFAULT 25.00, -- % chargements (sécurité, frais, commissions)
    prime_commerciale     DECIMAL(14,2) NOT NULL DEFAULT 0,
    statut                ENUM('actif','suspendu','resilie','expire') NOT NULL DEFAULT 'actif',
    alerte_echeance_envoyee TINYINT(1) NOT NULL DEFAULT 0, -- évite de renvoyer l'email d'alerte plusieurs fois
    id_utilisateur_creation INT NULL,
    date_creation          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_maj               DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_polices_branche
        FOREIGN KEY (id_branche) REFERENCES branches(id_branche)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_polices_utilisateur
        FOREIGN KEY (id_utilisateur_creation) REFERENCES utilisateurs(id_utilisateur)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 5. TABLE sinistres
-- Historique des sinistres, base du calcul fréquence-coût
-- -----------------------------------------------------
CREATE TABLE sinistres (
    id_sinistre         INT AUTO_INCREMENT PRIMARY KEY,
    id_police            INT NOT NULL,
    numero_sinistre       VARCHAR(30) NOT NULL UNIQUE,
    date_survenance        DATE NOT NULL,
    date_declaration        DATE NOT NULL,
    montant_paye           DECIMAL(14,2) NOT NULL DEFAULT 0,
    montant_reserve         DECIMAL(14,2) NOT NULL DEFAULT 0, -- PSAP par sinistre
    statut                  ENUM('ouvert','clos','rejete') NOT NULL DEFAULT 'ouvert',
    date_creation            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_sinistres_police
        FOREIGN KEY (id_police) REFERENCES polices(id_police)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 6. TABLE provisions_techniques
-- Résultats des calculs actuariels (PPNA, PRC, PSAP, PM) par branche/exercice
-- -----------------------------------------------------
CREATE TABLE provisions_techniques (
    id_provision        INT AUTO_INCREMENT PRIMARY KEY,
    id_branche            INT NOT NULL,
    exercice               YEAR NOT NULL,
    type_provision          ENUM('PPNA','PRC','PSAP','PM') NOT NULL,
    methode                 VARCHAR(50) NULL,           -- Chain Ladder, Bornhuetter-Ferguson, etc.
    montant                 DECIMAL(16,2) NOT NULL DEFAULT 0,
    id_utilisateur          INT NULL,
    date_calcul               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_provisions_branche
        FOREIGN KEY (id_branche) REFERENCES branches(id_branche)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_provisions_utilisateur
        FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id_utilisateur)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 7. TABLE traites_reassurance
-- -----------------------------------------------------
CREATE TABLE traites_reassurance (
    id_traite           INT AUTO_INCREMENT PRIMARY KEY,
    id_branche            INT NOT NULL,
    type_traite            ENUM('quote_part','excedent_plein','excedent_perte') NOT NULL,
    taux_cession_pct        DECIMAL(5,2) NULL,
    plafond                  DECIMAL(16,2) NULL,
    date_debut                DATE NOT NULL,
    date_fin                   DATE NOT NULL,

    CONSTRAINT fk_traites_branche
        FOREIGN KEY (id_branche) REFERENCES branches(id_branche)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 8. TABLE journal_audit
-- Journal d'audit horodaté des actions sensibles (exigence du cahier des charges)
-- -----------------------------------------------------
CREATE TABLE journal_audit (
    id_audit         INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur      INT NULL,
    action                VARCHAR(50) NOT NULL,      -- CREATE, UPDATE, DELETE, LOGIN, LOGOUT
    table_cible            VARCHAR(50) NOT NULL,
    id_enregistrement       INT NULL,
    details                  TEXT NULL,
    adresse_ip                VARCHAR(45) NULL,
    date_action                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_audit_utilisateur
        FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id_utilisateur)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- Index utiles pour recherche / pagination / filtres
CREATE INDEX idx_polices_statut ON polices(statut);
CREATE INDEX idx_polices_branche ON polices(id_branche);
CREATE INDEX idx_polices_nom_assure ON polices(nom_assure);
CREATE INDEX idx_sinistres_police ON sinistres(id_police);
CREATE INDEX idx_sinistres_statut ON sinistres(statut);
CREATE INDEX idx_provisions_branche_exercice ON provisions_techniques(id_branche, exercice);

-- =====================================================================
-- DONNEES DE DEMONSTRATION
-- =====================================================================

-- NOTE IMPORTANTE SUR LES COMPTES DE TEST :
-- Les mots de passe hachés (password_hash PHP) ne sont PAS insérés ici
-- en dur, car un hash bcrypt écrit "à la main" dans un script SQL est une
-- mauvaise pratique (le coût de hachage doit être généré par PHP lui-même
-- pour être garanti compatible avec password_verify()).
-- => Après avoir importé ce fichier, exécute une seule fois le script
--    sql/seed_utilisateurs.php (voir README) pour créer les 3 comptes
--    de test avec un hash correct :
--      admin@espdakar.sn     / Admin@2026     (rôle admin)
--      avance@espdakar.sn    / Avance@2026    (rôle utilisateur_avance)
--      standard@espdakar.sn  / Standard@2026  (rôle standard)

-- Branches
INSERT INTO branches (code, libelle, type_branche) VALUES
('AUTO', 'Automobile', 'IARD'),
('HABIT', 'Habitation / Multirisque habitation', 'IARD'),
('SANTE', 'Santé / Maladie', 'IARD'),
('VIE', 'Assurance Vie', 'VIE'),
('TRANSP', 'Transport de marchandises', 'IARD');

-- Polices (l'id_utilisateur_creation sera mis à jour après création des comptes)
INSERT INTO polices (numero_police, id_branche, nom_assure, telephone_assure, date_effet, date_expiration, prime_pure, chargement_pct, prime_commerciale, statut) VALUES
('POL-2026-0001', 1, 'Moussa Diop',        '77 123 45 67', '2026-01-01', '2026-12-31', 85000.00, 30.00, 110500.00, 'actif'),
('POL-2026-0002', 1, 'Aminata Sarr',       '76 234 56 78', '2026-02-15', '2027-02-14', 92000.00, 30.00, 119600.00, 'actif'),
('POL-2026-0003', 2, 'Cheikh Fall',        '70 345 67 89', '2026-03-01', '2027-02-28', 45000.00, 25.00, 56250.00,  'actif'),
('POL-2026-0004', 3, 'Fatou Ndiaye',       '78 456 78 90', '2026-01-10', '2026-12-31', 120000.00, 20.00, 144000.00,'actif'),
('POL-2026-0005', 4, 'Ibrahima Ba',        '77 567 89 01', '2025-06-01', '2045-06-01', 250000.00, 15.00, 287500.00,'actif'),
('POL-2026-0006', 1, 'Sokhna Mbaye',       '76 678 90 12', '2025-05-01', '2026-04-30', 88000.00, 30.00, 114400.00, 'resilie'),
('POL-2026-0007', 5, 'Transit SARL',       '33 889 90 12', '2026-04-01', '2027-03-31', 310000.00, 22.00, 378200.00,'actif'),
('POL-2026-0008', 2, 'Modou Gueye',        '70 789 01 23', '2026-05-01', '2027-04-30', 38000.00, 25.00, 47500.00,  'suspendu');

-- Sinistres
INSERT INTO sinistres (id_police, numero_sinistre, date_survenance, date_declaration, montant_paye, montant_reserve, statut) VALUES
(1, 'SIN-2026-001', '2026-03-12', '2026-03-14', 450000.00, 0.00,     'clos'),
(1, 'SIN-2026-002', '2026-07-02', '2026-07-04', 0.00,      180000.00,'ouvert'),
(2, 'SIN-2026-003', '2026-04-20', '2026-04-22', 620000.00, 0.00,     'clos'),
(4, 'SIN-2026-004', '2026-02-18', '2026-02-20', 95000.00,  0.00,     'clos'),
(4, 'SIN-2026-005', '2026-06-10', '2026-06-12', 0.00,      210000.00,'ouvert'),
(7, 'SIN-2026-006', '2026-05-05', '2026-05-08', 1200000.00,0.00,     'clos'),
(6, 'SIN-2025-014', '2025-11-11', '2025-11-13', 300000.00, 0.00,     'rejete');

-- Provisions techniques
INSERT INTO provisions_techniques (id_branche, exercice, type_provision, methode, montant) VALUES
(1, 2025, 'PSAP', 'Chain Ladder', 4500000.00),
(1, 2025, 'PPNA', 'Prorata temporis', 2100000.00),
(2, 2025, 'PSAP', 'Bornhuetter-Ferguson', 980000.00),
(4, 2025, 'PM', 'Méthode prospective', 15600000.00),
(3, 2025, 'PSAP', 'Chain Ladder', 1750000.00);

-- Traités de réassurance
INSERT INTO traites_reassurance (id_branche, type_traite, taux_cession_pct, plafond, date_debut, date_fin) VALUES
(1, 'quote_part',      40.00, NULL,        '2026-01-01', '2026-12-31'),
(4, 'excedent_plein',  NULL,  50000000.00, '2026-01-01', '2026-12-31'),
(5, 'excedent_perte',  NULL,  20000000.00, '2026-01-01', '2026-12-31');
