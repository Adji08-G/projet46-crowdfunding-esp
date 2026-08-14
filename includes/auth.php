<?php
/**
 * includes/auth.php
 * Fonctions liées à l'authentification, aux rôles et au journal d'audit.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Tente de connecter un utilisateur. Retourne true/false.
 */
function tenterConnexion(string $email, string $motDePasse): bool
{
    $db = Database::getConnexion();

    $stmt = $db->prepare(
        "SELECT id_utilisateur, nom, prenom, email, mot_de_passe, role, actif
         FROM utilisateurs
         WHERE email = :email
         LIMIT 1"
    );
    $stmt->execute([':email' => $email]);
    $utilisateur = $stmt->fetch();

    if (!$utilisateur) {
        journaliser(null, 'LOGIN_ECHEC', 'utilisateurs', null, "Email inconnu : $email");
        return false;
    }

    if ((int)$utilisateur['actif'] !== 1) {
        journaliser($utilisateur['id_utilisateur'], 'LOGIN_ECHEC', 'utilisateurs', null, 'Compte désactivé');
        return false;
    }

    if (!password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
        journaliser($utilisateur['id_utilisateur'], 'LOGIN_ECHEC', 'utilisateurs', null, 'Mot de passe invalide');
        return false;
    }

    // Régénère l'ID de session à chaque connexion (anti session fixation)
    session_regenerate_id(true);

    $_SESSION['id_utilisateur'] = $utilisateur['id_utilisateur'];
    $_SESSION['nom_complet']    = $utilisateur['prenom'] . ' ' . $utilisateur['nom'];
    $_SESSION['email']          = $utilisateur['email'];
    $_SESSION['role']           = $utilisateur['role'];
    $_SESSION['derniere_activite'] = time();

    $db->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id_utilisateur = :id")
       ->execute([':id' => $utilisateur['id_utilisateur']]);

    journaliser($utilisateur['id_utilisateur'], 'LOGIN_SUCCES', 'utilisateurs', $utilisateur['id_utilisateur'], 'Connexion réussie');

    return true;
}

/**
 * Déconnecte l'utilisateur courant.
 */
function deconnecter(): void
{
    if (isset($_SESSION['id_utilisateur'])) {
        journaliser($_SESSION['id_utilisateur'], 'LOGOUT', 'utilisateurs', $_SESSION['id_utilisateur'], 'Déconnexion');
    }
    $_SESSION = [];
    session_unset();
    session_destroy();
}

/**
 * Vérifie que l'utilisateur est connecté, sinon redirige vers login.php.
 * A appeler en haut de toute page protégée.
 */
function exigerConnexion(): void
{
    if (empty($_SESSION['id_utilisateur'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

/**
 * Vérifie que l'utilisateur a l'un des rôles autorisés.
 * Exemple : exigerRole(['admin', 'utilisateur_avance']);
 */
function exigerRole(array $rolesAutorises): void
{
    exigerConnexion();
    if (!in_array($_SESSION['role'], $rolesAutorises, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:2rem;">
                <h2>Accès refusé</h2>
                <p>Votre rôle ("' . htmlspecialchars($_SESSION['role']) . '") ne permet pas d\'accéder à cette page.</p>
                <a href="' . BASE_URL . 'dashboard.php">Retour au tableau de bord</a>
             </div>');
    }
}

/**
 * Retourne le libellé lisible d'un rôle (ex: 'utilisateur_avance' -> 'Utilisateur avancé').
 */
function libelleRole(string $role): string
{
    return LIBELLES_ROLES[$role] ?? ucfirst($role);
}

/**
 * Enregistre une action dans le journal d'audit.
 */
function journaliser(?int $idUtilisateur, string $action, string $tableCible, ?int $idEnregistrement, string $details = ''): void
{
    try {
        $db = Database::getConnexion();
        $stmt = $db->prepare(
            "INSERT INTO journal_audit (id_utilisateur, action, table_cible, id_enregistrement, details, adresse_ip)
             VALUES (:id_utilisateur, :action, :table_cible, :id_enregistrement, :details, :ip)"
        );
        $stmt->execute([
            ':id_utilisateur'    => $idUtilisateur,
            ':action'            => $action,
            ':table_cible'       => $tableCible,
            ':id_enregistrement' => $idEnregistrement,
            ':details'           => $details,
            ':ip'                => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('Erreur journalisation audit : ' . $e->getMessage());
    }
}
