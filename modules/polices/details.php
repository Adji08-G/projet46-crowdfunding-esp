<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

exigerConnexion();

$db = Database::getConnexion();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare(
    "SELECT p.*, b.libelle AS branche
     FROM polices p
     JOIN branches b ON b.id_branche = p.id_branche
     WHERE p.id_police = :id"
);
$stmt->execute([':id' => $id]);
$police = $stmt->fetch();

if (!$police) {
    $_SESSION['flash'] = ['type' => 'danger', 'texte' => 'Police introuvable.'];
    header('Location: liste.php');
    exit;
}

$stmtSinistres = $db->prepare(
    "SELECT * FROM sinistres WHERE id_police = :id ORDER BY date_survenance DESC"
);
$stmtSinistres->execute([':id' => $id]);
$sinistres = $stmtSinistres->fetchAll();

$titrePage = 'Détail police ' . $police['numero_police'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-file-earmark-text"></i> Police <?= e($police['numero_police']) ?></h2>
    <a href="liste.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">Informations générales</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Assuré</th><td><?= e($police['nom_assure']) ?></td></tr>
                    <tr><th>Téléphone</th><td><?= e($police['telephone_assure'] ?: '-') ?></td></tr>
                    <tr><th>Branche</th><td><?= e($police['branche']) ?></td></tr>
                    <tr><th>Date d'effet</th><td><?= formaterDate($police['date_effet']) ?></td></tr>
                    <tr><th>Date d'expiration</th><td><?= formaterDate($police['date_expiration']) ?></td></tr>
                    <tr><th>Statut</th><td><?= badgeStatutPolice($police['statut']) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">Tarification</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Prime pure</th><td><?= formaterMontant($police['prime_pure']) ?></td></tr>
                    <tr><th>Taux de chargement</th><td><?= e($police['chargement_pct']) ?> %</td></tr>
                    <tr><th>Prime commerciale</th><td class="fw-bold"><?= formaterMontant($police['prime_commerciale']) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-3">
    <div class="card-header">Sinistres liés à cette police (<?= count($sinistres) ?>)</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light">
                <tr><th>N° Sinistre</th><th>Survenance</th><th>Déclaration</th><th class="text-end">Payé</th><th class="text-end">Réserve</th><th>Statut</th></tr>
            </thead>
            <tbody>
                <?php if (empty($sinistres)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">Aucun sinistre enregistré pour cette police.</td></tr>
                <?php endif; ?>
                <?php foreach ($sinistres as $s): ?>
                <tr>
                    <td><?= e($s['numero_sinistre']) ?></td>
                    <td><?= formaterDate($s['date_survenance']) ?></td>
                    <td><?= formaterDate($s['date_declaration']) ?></td>
                    <td class="text-end"><?= formaterMontant($s['montant_paye']) ?></td>
                    <td class="text-end"><?= formaterMontant($s['montant_reserve']) ?></td>
                    <td><span class="badge bg-<?= $s['statut'] === 'clos' ? 'success' : ($s['statut'] === 'ouvert' ? 'warning' : 'secondary') ?>">
                        <?= e(ucfirst($s['statut'])) ?>
                    </span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    <a href="modifier.php?id=<?= (int)$police['id_police'] ?>" class="btn btn-primary">
        <i class="bi bi-pencil"></i> Modifier cette police
    </a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
