<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Seul l'administrateur peut supprimer définitivement une police
exigerRole(['admin']);

$db = Database::getConnexion();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'texte' => 'Police invalide.'];
    header('Location: liste.php');
    exit;
}

$stmt = $db->prepare("SELECT numero_police FROM polices WHERE id_police = :id");
$stmt->execute([':id' => $id]);
$police = $stmt->fetch();

if (!$police) {
    $_SESSION['flash'] = ['type' => 'danger', 'texte' => 'Police introuvable.'];
    header('Location: liste.php');
    exit;
}

try {
    $stmtDelete = $db->prepare("DELETE FROM polices WHERE id_police = :id");
    $stmtDelete->execute([':id' => $id]);

    journaliser($_SESSION['id_utilisateur'], 'DELETE', 'polices', $id,
        'Suppression police ' . $police['numero_police']);

    $_SESSION['flash'] = ['type' => 'success', 'texte' => 'Police "' . $police['numero_police'] . '" supprimée.'];
} catch (PDOException $e) {
    error_log('Erreur suppression police : ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'danger', 'texte' => 'Suppression impossible : des sinistres sont probablement liés à cette police.'];
}

header('Location: liste.php');
exit;
