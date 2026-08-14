<?php
/**
 * sql/seed_utilisateurs.php
 * ------------------------------------------------------------------
 * A exécuter UNE SEULE FOIS après avoir importé sql/schema.sql, pour
 * créer les 3 comptes de démonstration (1 par rôle) avec un mot de
 * passe haché par PHP (password_hash), garanti compatible avec
 * password_verify() utilisé dans includes/auth.php.
 *
 * Utilisation :
 *   - Via navigateur : http://localhost/actuariat_assurance/sql/seed_utilisateurs.php
 *   - Via CLI        : php sql/seed_utilisateurs.php
 *
 * Comptes créés :
 *   admin@espdakar.sn    / Admin@2026     -> rôle admin
 *   avance@espdakar.sn   / Avance@2026    -> rôle utilisateur_avance
 *   standard@espdakar.sn / Standard@2026  -> rôle standard
 * ------------------------------------------------------------------
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getConnexion();

$comptes = [
    ['Admin',    'Système',  'admin@espdakar.sn',    'Admin@2026',    'admin'],
    ['Diallo',   'Aïssatou', 'avance@espdakar.sn',   'Avance@2026',   'utilisateur_avance'],
    ['Sow',      'Babacar',  'standard@espdakar.sn', 'Standard@2026', 'standard'],
];

$sqlCheck  = "SELECT id_utilisateur FROM utilisateurs WHERE email = :email";
$sqlInsert = "INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, actif)
              VALUES (:nom, :prenom, :email, :mdp, :role, 1)";

$stmtCheck  = $db->prepare($sqlCheck);
$stmtInsert = $db->prepare($sqlInsert);

$creesIds = [];
foreach ($comptes as [$nom, $prenom, $email, $motDePasse, $role]) {
    $stmtCheck->execute([':email' => $email]);
    if ($stmtCheck->fetch()) {
        echo "- Compte déjà existant : $email\n";
        continue;
    }
    $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
    $stmtInsert->execute([
        ':nom'    => $nom,
        ':prenom' => $prenom,
        ':email'  => $email,
        ':mdp'    => $hash,
        ':role'   => $role,
    ]);
    $creesIds[$role] = $db->lastInsertId();
    echo "- Compte créé : $email (rôle $role)\n";
}

// Rattache les polices de démo au compte admin, si celui-ci vient d'être créé
if (!empty($creesIds['admin'])) {
    $db->prepare("UPDATE polices SET id_utilisateur_creation = :id WHERE id_utilisateur_creation IS NULL")
       ->execute([':id' => $creesIds['admin']]);
}

echo "\nTerminé. Tu peux maintenant te connecter sur public/login.php.\n";
