<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

exigerConnexion(); // les 3 rôles (admin, utilisateur_avance, standard) peuvent consulter

$db = Database::getConnexion();

// --- Filtres de recherche ---
$recherche   = nettoyer($_GET['q'] ?? '');
$statutFiltre = nettoyer($_GET['statut'] ?? '');
$brancheFiltre = (int)($_GET['branche'] ?? 0);

$conditions = [];
$parametres = [];

if ($recherche !== '') {
    $conditions[] = "(p.numero_police LIKE :recherche OR p.nom_assure LIKE :recherche)";
    $parametres[':recherche'] = '%' . $recherche . '%';
}
if ($statutFiltre !== '' && in_array($statutFiltre, ['actif', 'suspendu', 'resilie', 'expire'], true)) {
    $conditions[] = "p.statut = :statut";
    $parametres[':statut'] = $statutFiltre;
}
if ($brancheFiltre > 0) {
    $conditions[] = "p.id_branche = :branche";
    $parametres[':branche'] = $brancheFiltre;
}

$whereSql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

// --- Comptage total pour la pagination ---
$stmtCount = $db->prepare("SELECT COUNT(*) FROM polices p $whereSql");
$stmtCount->execute($parametres);
$totalLignes = (int)$stmtCount->fetchColumn();

$pagination = calculerPagination($totalLignes);

// --- Export CSV (avant tout affichage HTML) ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmtExport = $db->prepare(
        "SELECT p.numero_police, p.nom_assure, b.libelle AS branche, p.date_effet, p.date_expiration,
                p.prime_commerciale, p.statut
         FROM polices p
         JOIN branches b ON b.id_branche = p.id_branche
         $whereSql
         ORDER BY p.date_creation DESC"
    );
    $stmtExport->execute($parametres);
    $lignes = $stmtExport->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="polices_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM pour Excel
    fputcsv($out, ['N° Police', 'Assuré', 'Branche', 'Date effet', 'Date expiration', 'Prime commerciale', 'Statut'], ';');
    foreach ($lignes as $l) {
        fputcsv($out, [
            $l['numero_police'], $l['nom_assure'], $l['branche'],
            formaterDate($l['date_effet']), formaterDate($l['date_expiration']),
            $l['prime_commerciale'], $l['statut'],
        ], ';');
    }
    fclose($out);
    journaliser($_SESSION['id_utilisateur'], 'EXPORT_CSV', 'polices', null, 'Export CSV liste des polices');
    exit;
}

// --- Requête paginée pour l'affichage ---
$sql = "SELECT p.id_police, p.numero_police, p.nom_assure, p.telephone_assure, b.libelle AS branche,
               p.date_effet, p.date_expiration, p.prime_commerciale, p.statut
        FROM polices p
        JOIN branches b ON b.id_branche = p.id_branche
        $whereSql
        ORDER BY p.date_creation DESC
        LIMIT :limite OFFSET :offset";

$stmt = $db->prepare($sql);
foreach ($parametres as $cle => $valeur) {
    $stmt->bindValue($cle, $valeur);
}
$stmt->bindValue(':limite', $pagination['par_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$polices = $stmt->fetchAll();

// Liste des branches pour le filtre
$branches = $db->query("SELECT id_branche, libelle FROM branches ORDER BY libelle")->fetchAll();

// Message flash après création/modification/suppression
$messageFlash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$titrePage = 'Gestion des polices';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="bi bi-file-earmark-text"></i> Polices d'assurance</h2>
    <?php if (in_array($_SESSION['role'], ['admin', 'utilisateur_avance'], true)): ?>
    <a href="ajouter.php" class="btn btn-success">
        <i class="bi bi-plus-circle"></i> Nouvelle police
    </a>
    <?php endif; ?>
</div>

<?php if ($messageFlash): ?>
    <div class="alert alert-<?= e($messageFlash['type']) ?> alert-dismissible fade show">
        <?= e($messageFlash['texte']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small">Recherche (n° police / assuré)</label>
                <input type="text" name="q" class="form-control" value="<?= e($recherche) ?>" placeholder="Ex : POL-2026-0001 ou Diop">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Statut</label>
                <select name="statut" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach (['actif', 'suspendu', 'resilie', 'expire'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $statutFiltre === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Branche</label>
                <select name="branche" class="form-select">
                    <option value="0">Toutes</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id_branche'] ?>" <?= $brancheFiltre === (int)$b['id_branche'] ? 'selected' : '' ?>>
                            <?= e($b['libelle']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button>
                <a href="?export=csv&q=<?= urlencode($recherche) ?>&statut=<?= urlencode($statutFiltre) ?>&branche=<?= $brancheFiltre ?>"
                   class="btn btn-outline-secondary" title="Exporter en CSV">
                    <i class="bi bi-filetype-csv"></i>
                </a>
                <a href="export_pdf.php?q=<?= urlencode($recherche) ?>&statut=<?= urlencode($statutFiltre) ?>&branche=<?= $brancheFiltre ?>"
                   class="btn btn-outline-danger" title="Exporter en PDF">
                    <i class="bi bi-file-earmark-pdf"></i>
                </a>
                <a href="export_excel.php?q=<?= urlencode($recherche) ?>&statut=<?= urlencode($statutFiltre) ?>&branche=<?= $brancheFiltre ?>"
                   class="btn btn-outline-success" title="Exporter en Excel">
                    <i class="bi bi-file-earmark-excel"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>N° Police</th>
                    <th>Assuré</th>
                    <th>Branche</th>
                    <th>Effet</th>
                    <th>Expiration</th>
                    <th class="text-end">Prime commerciale</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($polices)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Aucune police trouvée.</td></tr>
                <?php endif; ?>
                <?php foreach ($polices as $p): ?>
                <tr>
                    <td class="fw-semibold"><?= e($p['numero_police']) ?></td>
                    <td><?= e($p['nom_assure']) ?></td>
                    <td><?= e($p['branche']) ?></td>
                    <td><?= formaterDate($p['date_effet']) ?></td>
                    <td><?= formaterDate($p['date_expiration']) ?></td>
                    <td class="text-end"><?= formaterMontant($p['prime_commerciale']) ?></td>
                    <td><?= badgeStatutPolice($p['statut']) ?></td>
                    <td class="text-end">
                        <a href="details.php?id=<?= (int)$p['id_police'] ?>" class="btn btn-sm btn-outline-info" title="Voir">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="modifier.php?id=<?= (int)$p['id_police'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="supprimer.php?id=<?= (int)$p['id_police'] ?>" class="btn btn-sm btn-outline-danger"
                           title="Supprimer" onclick="return confirm('Confirmer la suppression de cette police ?');">
                            <i class="bi bi-trash"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($pagination['nb_pages'] > 1): ?>
<nav class="mt-3">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $pagination['nb_pages']; $i++): ?>
            <li class="page-item <?= $i === $pagination['page'] ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($recherche) ?>&statut=<?= urlencode($statutFiltre) ?>&branche=<?= $brancheFiltre ?>">
                    <?= $i ?>
                </a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<p class="text-muted small mt-2"><?= $totalLignes ?> police(s) au total.</p>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
