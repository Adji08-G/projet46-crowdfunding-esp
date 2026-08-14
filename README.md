# Projet n°46 — Plateforme web d'actuariat pour compagnie d'assurance
Master CCA — École Supérieure Polytechnique de Dakar

## Stack technique
- Apache + MySQL/MariaDB + PHP 8+ (XAMPP)
- PHP orienté objet, PDO avec requêtes préparées
- Bootstrap 5, Chart.js, Bootstrap Icons

## État actuel du livrable (première itération)
Ce ZIP contient la **fondation complète et fonctionnelle** du projet :
- Base de données (7 tables liées par clés étrangères)
- Authentification sécurisée avec 3 rôles (admin, actuaire, agent)
- Tableau de bord avec KPI et graphique dynamique (Chart.js)
- **Module Polices** entièrement fonctionnel : liste (recherche, filtres,
  pagination, export CSV), création, modification, suppression, détail
- Journal d'audit horodaté des actions sensibles

Les modules suivants (Sinistres, Provisions techniques ORSA, Réassurance,
tarification actuarielle, export PDF) seront ajoutés dans une itération
suivante, une fois que ce premier module aura été testé avec succès sur ton
poste XAMPP — c'est la méthode recommandée pour éviter de propager un même
bug dans dix modules.

## Installation

### Dépendances PHP (Composer)

Ce projet utilise trois bibliothèques externes : DOMPDF (export PDF),
PhpSpreadsheet (export Excel) et PHPMailer (envoi d'emails). Depuis la
racine du projet :

```bash
composer install
```

Cela lit `composer.json` et installe tout dans `vendor/`. Si tu n'as pas
Composer : https://getcomposer.org/download/

### Alerte email automatique (échéances de police)

`scripts/alerte_echeances.php` envoie un email quand une police active
arrive à échéance sous 7 jours (seuil réglable dans `config/mail.php`).
Configure d'abord tes identifiants SMTP dans `config/mail.php`, puis
planifie le script en tâche quotidienne (voir l'en-tête du fichier pour
la commande cron / Planificateur de tâches Windows). Tu peux aussi le
lancer manuellement pour tester :

```bash
php scripts/alerte_echeances.php
```


### 1. Copier le projet
Place le dossier `actuariat_assurance/` dans `C:\xampp\htdocs\` (Windows)
ou `/Applications/XAMPP/htdocs/` (Mac).

### 2. Créer la base de données
Ouvre phpMyAdmin (`http://localhost/phpmyadmin`), onglet **SQL**, puis
importe le fichier :
```
sql/schema.sql
```
Cela crée la base `actuariat_assurance` avec ses 7 tables et des données
de démonstration (branches, polices, sinistres, provisions...).

### 3. Créer les comptes de test
Les mots de passe ne sont **jamais** stockés en clair ni codés en dur dans
le SQL. Exécute une seule fois, dans ton navigateur :
```
http://localhost/actuariat_assurance/sql/seed_utilisateurs.php
```
Cela crée 3 comptes avec mot de passe correctement haché (`password_hash`) :

| Rôle      | Email                 | Mot de passe   |
|-----------|------------------------|----------------|
| admin     | admin@espdakar.sn      | Admin@2026     |
| actuaire  | actuaire@espdakar.sn   | Actuaire@2026  |
| agent     | agent@espdakar.sn      | Agent@2026     |

### 4. Vérifier la configuration
Le fichier `config/database.php` utilise par défaut :
- hôte : `127.0.0.1`
- utilisateur : `root`
- mot de passe : `` (vide, valeur par défaut XAMPP)

Adapte ces constantes si ta configuration XAMPP diffère.

### 5. Lancer l'application
```
http://localhost/actuariat_assurance/public/login.php
```

## Arborescence
```
actuariat_assurance/
├── config/
│   ├── database.php      -> connexion PDO (singleton)
│   └── config.php        -> session, constantes, mode debug
├── includes/
│   ├── auth.php           -> connexion, rôles, journal d'audit
│   ├── functions.php      -> helpers (échappement, pagination, formats)
│   ├── header.php / footer.php
├── public/                -> pages accessibles directement dans le navigateur
│   ├── index.php, login.php, logout.php, dashboard.php
├── modules/
│   └── polices/           -> CRUD complet de l'entité principale
│       ├── liste.php, ajouter.php, modifier.php, supprimer.php, details.php
├── assets/
│   ├── css/style.css
│   └── js/main.js
├── sql/
│   ├── schema.sql          -> structure + données de démo
│   └── seed_utilisateurs.php -> création des comptes de test
└── README.md
```

## Modèle Conceptuel de Données (résumé)

**Entités et attributs principaux**
- **Utilisateurs** (id, nom, prénom, email, mot_de_passe, rôle, actif)
- **Branches** (id, code, libellé, type)
- **Polices** (id, numéro, branche, assuré, dates, prime pure, chargement,
  prime commerciale, statut) — *entité principale*
- **Sinistres** (id, police, numéro, dates, montant payé, réserve, statut)
- **ProvisionsTechniques** (id, branche, exercice, type PPNA/PRC/PSAP/PM,
  méthode, montant)
- **TraitesReassurance** (id, branche, type de traité, taux de cession,
  plafond, dates)
- **JournalAudit** (id, utilisateur, action, table cible, détails, date)

**Relations**
- Une Branche possède plusieurs Polices (1,N)
- Une Police possède plusieurs Sinistres (1,N)
- Une Branche possède plusieurs ProvisionsTechniques (1,N)
- Une Branche possède plusieurs TraitesReassurance (1,N)
- Un Utilisateur crée plusieurs Polices (1,N)
- Un Utilisateur calcule plusieurs ProvisionsTechniques (1,N)
- Un Utilisateur génère plusieurs entrées du JournalAudit (1,N)

## Sécurité mise en œuvre
- Mots de passe hachés (`password_hash` / `password_verify`)
- Requêtes 100 % préparées PDO (`PDO::ATTR_EMULATE_PREPARES = false`)
- Échappement systématique en sortie (`htmlspecialchars`) contre le XSS
- Jeton CSRF sur tous les formulaires POST
- Session sécurisée (httponly, régénération d'ID à la connexion, expiration
  après 30 min d'inactivité)
- Contrôle des rôles par page (`exigerRole()`)
- Journal d'audit horodaté (connexions, créations, modifications,
  suppressions, exports)

## Prochaine étape suggérée
Une fois ce ZIP testé sur ton XAMPP (import SQL + seed + connexion + CRUD
Polices), on pourra générer le module **Sinistres**, puis le module
**Provisions techniques** avec les calculs actuariels (Chain Ladder,
Bornhuetter-Ferguson), l'export PDF (TCPDF) et l'envoi d'email — un module
à la fois.
