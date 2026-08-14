<?php
/**
 * modules/polices/export_excel.php
 * Export Excel (.xlsx) de la liste des polices (mêmes filtres que liste.php),
 * généré avec PhpSpreadsheet.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

exigerConnexion();

$db = Database::getConnexion();

// --- Mêmes filtres que liste.php ---
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
    "SELECT p.numero_police, p.nom_assure, p.telephone_assure, b.libelle AS branche,
            p.date_effet, p.date_expiration, p.prime_pure, p.chargement_pct, p.prime_commerciale, p.statut
     FROM polices p
     JOIN branches b ON b.id_branche = p.id_branche
     $whereSql
     ORDER BY p.date_creation DESC"
);
$stmt->execute($parametres);
$polices = $stmt->fetchAll();

// --- Construction du classeur ---
$spreadsheet = new Spreadsheet();
$feuille = $spreadsheet->getActiveSheet();
$feuille->setTitle('Polices');

$entetes = ['N° Police', 'Assuré', 'Téléphone', 'Branche', 'Date effet', 'Date expiration',
            'Prime pure', 'Chargement (%)', 'Prime commerciale', 'Statut'];
$feuille->fromArray($entetes, null, 'A1');

$feuille->getStyle('A1:J1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$feuille->getStyle('A1:J1')->getFill()
    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D6EFD');
$feuille->getStyle('A1:J1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$ligne = 2;
foreach ($polices as $p) {
    $feuille->fromArray([
        $p['numero_police'],
        $p['nom_assure'],
        $p['telephone_assure'],
        $p['branche'],
        $p['date_effet'],
        $p['date_expiration'],
        (float)$p['prime_pure'],
        (float)$p['chargement_pct'],
        (float)$p['prime_commerciale'],
        ucfirst($p['statut']),
    ], null, 'A' . $ligne);
    $ligne++;
}

// Formats : dates + montants (nombre, séparateur milliers)
$derniereLigne = $ligne - 1;
if ($derniereLigne >= 2) {
    $feuille->getStyle("E2:F$derniereLigne")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
    $feuille->getStyle("G2:G$derniereLigne")->getNumberFormat()->setFormatCode('#,##0.00');
    $feuille->getStyle("I2:I$derniereLigne")->getNumberFormat()->setFormatCode('#,##0.00');
}

foreach (range('A', 'J') as $colonne) {
    $feuille->getColumnDimension($colonne)->setAutoSize(true);
}

journaliser($_SESSION['id_utilisateur'], 'EXPORT_EXCEL', 'polices', null, 'Export Excel liste des polices');

// --- Envoi du fichier au navigateur ---
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="polices_' . date('Ymd_His') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
