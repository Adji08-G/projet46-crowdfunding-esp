<?php
/**
 * modules/polices/export_pdf.php
 * Export PDF de la liste des polices (respecte les mêmes filtres q / statut /
 * branche que liste.php), généré avec DOMPDF.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

exigerConnexion();

$db = Database::getConnexion();

// --- Mêmes filtres que liste.php, pour exporter exactement ce que l'utilisateur consulte ---
$recherche     = nettoyer($_GET['q'] ?? '');
$statutFiltre  = nettoyer($_GET['statut'] ?? '');
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

$stmt = $db->prepare(
    "SELECT p.numero_police, p.nom_assure, b.libelle AS branche, p.date_effet, p.date_expiration,
            p.prime_commerciale, p.statut
     FROM polices p
     JOIN branches b ON b.id_branche = p.id_branche
     $whereSql
     ORDER BY p.date_creation DESC"
);
$stmt->execute($parametres);
$polices = $stmt->fetchAll();

$totalPrimes = array_sum(array_column($polices, 'prime_commerciale'));

// --- Construction du HTML qui sera converti en PDF ---
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #212529; }
    h1 { font-size: 18px; margin-bottom: 0; }
    p.sous-titre { color: #6c757d; margin-top: 4px; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { border: 1px solid #dee2e6; padding: 6px 8px; text-align: left; }
    th { background-color: #0d6efd; color: #fff; }
    tr:nth-child(even) { background-color: #f8f9fa; }
    .text-end { text-align: right; }
    .total-row td { font-weight: bold; background-color: #e9ecef; }
    footer { position: fixed; bottom: -20px; font-size: 9px; color: #6c757d; }
</style>
</head>
<body>
    <h1>Liste des polices d'assurance</h1>
    <p class="sous-titre">
        Généré le <?= date('d/m/Y à H:i') ?>
        <?php if ($recherche || $statutFiltre || $brancheFiltre): ?>
            — Filtres : <?= e($recherche ?: '—') ?> / <?= e($statutFiltre ?: 'tous statuts') ?>
        <?php endif; ?>
        — <?= NOM_APPLICATION ?>
    </p>

    <table>
        <thead>
            <tr>
                <th>N° Police</th>
                <th>Assuré</th>
                <th>Branche</th>
                <th>Effet</th>
                <th>Expiration</th>
                <th class="text-end">Prime commerciale</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($polices as $p): ?>
            <tr>
                <td><?= e($p['numero_police']) ?></td>
                <td><?= e($p['nom_assure']) ?></td>
                <td><?= e($p['branche']) ?></td>
                <td><?= formaterDate($p['date_effet']) ?></td>
                <td><?= formaterDate($p['date_expiration']) ?></td>
                <td class="text-end"><?= formaterMontant($p['prime_commerciale']) ?></td>
                <td><?= e(ucfirst($p['statut'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($polices)): ?>
                <tr><td colspan="7">Aucune police ne correspond aux filtres sélectionnés.</td></tr>
            <?php endif; ?>
        </tbody>
        <?php if ($polices): ?>
        <tfoot>
            <tr class="total-row">
                <td colspan="5">Total (<?= count($polices) ?> police(s))</td>
                <td class="text-end"><?= formaterMontant($totalPrimes) ?></td>
                <td></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

// --- Génération du PDF ---
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

journaliser($_SESSION['id_utilisateur'], 'EXPORT_PDF', 'polices', null, 'Export PDF liste des polices');

$dompdf->stream('polices_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
exit;
