<?php
/**
 * config/config.php
 * Paramètres globaux : session sécurisée, constantes, gestion des erreurs.
 * A inclure en tout premier sur chaque page.
 */

// --- Session sécurisée ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,   // inaccessible en JS -> limite le XSS
        'samesite' => 'Lax',
    ]);
    session_start();
}

// --- Constantes applicatives (définies avant tout usage, notamment ROOT_URL/BASE_URL
// utilisées dans les redirections et les liens du menu ci-dessous) ---
define('NOM_APPLICATION', "Plateforme d'Actuariat - Assurance");
define('ROOT_URL', '/actuariat_assurance/');       // racine du projet (public/ ET modules/ en dépendent)
define('BASE_URL', ROOT_URL . 'public/');
define('ITEMS_PAR_PAGE', 10);

// Expiration automatique de session après 20 minutes d'inactivité
define('DUREE_MAX_INACTIVITE', 1200);
if (isset($_SESSION['derniere_activite']) && (time() - $_SESSION['derniere_activite'] > DUREE_MAX_INACTIVITE)) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php?expire=1');
    exit;
}
$_SESSION['derniere_activite'] = time();

// --- Environnement ---
define('MODE_DEBUG', true); // à mettre à false en production

if (MODE_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// --- Rôles autorisés ---
// admin              : accès total (création, modification, suppression, gestion utilisateurs)
// utilisateur_avance : création et modification, pas de suppression ni gestion utilisateurs
// standard           : consultation seule
define('ROLES_VALIDES', ['admin', 'utilisateur_avance', 'standard']);

// Libellés d'affichage pour chaque rôle (utilisé dans l'interface)
define('LIBELLES_ROLES', [
    'admin'              => 'Administrateur',
    'utilisateur_avance' => 'Utilisateur avancé',
    'standard'           => 'Standard',
]);

date_default_timezone_set('Africa/Dakar');
