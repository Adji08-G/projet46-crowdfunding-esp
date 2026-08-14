<?php
require_once __DIR__ . '/../config/config.php';

// Point d'entrée de l'application :
// - visiteur non connecté -> page de garde (accueil.php)
// - utilisateur déjà connecté -> tableau de bord
if (!empty($_SESSION['id_utilisateur'])) {
    header('Location: dashboard.php');
} else {
    header('Location: accueil.php');
}
exit;
