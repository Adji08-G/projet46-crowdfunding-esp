<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

exigerConnexion();

$db = Database::getConnexion();

// =====================================================================
// KPI — toutes les valeurs viennent de requêtes SQL réelles, aucune
// donnée codée en dur.
// =====================================================================

// 1. Polices actives
$nbPolicesActives = (int)$db->query(
    "SELECT COUNT(*) FROM polices WHERE statut = 'actif'"
)->fetchColumn();

// 2. Primes commerciales en cours (portefeuille actif)
$primeTotale = (float)$db->query(
    "SELECT COALESCE(SUM(prime_commerciale),0) FROM polices WHERE statut = 'actif'"
)->fetchColumn();

// 3. Taux de sinistralité S/P = (montants payés + réservés) / primes commerciales des polices actives
$totalSinistres = (float)$db->query(
    "SELECT COALESCE(SUM(montant_paye + montant_reserve),0) FROM sinistres WHERE statut != 'rejete'"
)->fetchColumn();
$tauxSinistralite = $primeTotale > 0 ? ($totalSinistres / $primeTotale) * 100 : 0.0;

// 4. Sinistres ouverts
$nbSinistresOuverts = (int)$db->query(
    "SELECT COUNT(*) FROM sinistres WHERE statut = 'ouvert'"
)->fetchColumn();

// 5. Réserves sinistres (PSAP) des dossiers ouverts
$totalReserves = (float)$db->query(
    "SELECT COALESCE(SUM(montant_reserve),0) FROM sinistres WHERE statut = 'ouvert'"
)->fetchColumn();

// 6. Polices actives arrivant à échéance dans les 30 prochains jours
$nbEcheancesProches = (int)$db->query(
    "SELECT COUNT(*) FROM polices
     WHERE statut = 'actif'
       AND date_expiration BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
)->fetchColumn();

$indicateurs = [
    'nb_polices_actives'   => $nbPolicesActives,
    'prime_totale'         => $primeTotale,
    'taux_sinistralite'    => $tauxSinistralite,
    'nb_sinistres_ouverts' => $nbSinistresOuverts,
    'total_reserves'       => $totalReserves,
    'nb_echeances_proches' => $nbEcheancesProches,
];

// --- Moteur de règles : commentaires automatiques (bonus) ---
$commentaires = genererCommentaires($indicateurs);

// --- Répartition des primes commerciales par branche (pour le graphique) ---
$stmt = $db->query(
    "SELECT b.libelle, COALESCE(SUM(p.prime_commerciale), 0) AS total_primes
     FROM branches b
     LEFT JOIN polices p ON p.id_branche = b.id_branche AND p.statut = 'actif'
     GROUP BY b.id_branche, b.libelle
     ORDER BY total_primes DESC"
);
$repartitionBranches = $stmt->fetchAll();

$titrePage = 'Tableau de bord';
require_once __DIR__ . '/../includes/header.php';

// Icône + couleur Bootstrap selon le niveau de commentaire
$styleNiveau = [
    'alerte'    => ['icone' => 'bi-exclamation-triangle-fill', 'classe' => 'danger'],
    'attention' => ['icone' => 'bi-exclamation-circle-fill',   'classe' => 'warning'],
    'positif'   => ['icone' => 'bi-check-circle-fill',         'classe' => 'success'],
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-speedometer2"></i> Tableau de bord</h2>
    <div class="d-flex gap-2">
        <a href="../modules/polices/export_pdf.php" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-file-earmark-pdf"></i> Export PDF
        </a>
        <a href="../modules/polices/export_excel.php" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel"></i> Export Excel
        </a>
    </div>
</div>

<!-- Cartes KPI -->
<div class="row g-3 mb-4">
    <div class="col-md-4 col-lg-2">
        <div class="card text-bg-primary shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= $nbPolicesActives ?></div>
                <div class="small">Polices actives</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="card text-bg-success shadow-sm h-100">
            <div class="card-body">
                <div class="fs-5 fw-bold"><?= formaterMontant($primeTotale) ?></div>
                <div class="small">Primes en cours</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="card <?= $tauxSinistralite >= 85 ? 'text-bg-danger' : ($tauxSinistralite >= 60 ? 'text-bg-warning' : 'text-bg-success') ?> shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= number_format($tauxSinistralite, 1) ?> %</div>
                <div class="small">Taux de sinistralité (S/P)</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="card text-bg-warning shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= $nbSinistresOuverts ?></div>
                <div class="small">Sinistres ouverts</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="card text-bg-danger shadow-sm h-100">
            <div class="card-body">
                <div class="fs-5 fw-bold"><?= formaterMontant($totalReserves) ?></div>
                <div class="small">Réserves (PSAP)</div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="card <?= $nbEcheancesProches > 0 ? 'text-bg-warning' : 'text-bg-secondary' ?> shadow-sm h-100">
            <div class="card-body">
                <div class="fs-2 fw-bold"><?= $nbEcheancesProches ?></div>
                <div class="small">Échéances &lt; 30 jours</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header">Primes commerciales par branche (portefeuille actif)</div>
            <div class="card-body">
                <canvas id="graphBranches" height="220"></canvas>
            </div>
        </div>
    </div>

    <!-- Bonus : commentaires automatiques générés par le moteur de règles -->
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header"><i class="bi bi-robot"></i> Analyse automatique</div>
            <div class="card-body">
                <?php foreach ($commentaires as $c): $s = $styleNiveau[$c['niveau']]; ?>
                    <div class="alert alert-<?= $s['classe'] ?> d-flex align-items-start gap-2 py-2 mb-2">
                        <i class="bi <?= $s['icone'] ?> mt-1"></i>
                        <div class="small mb-0"><?= e($c['message']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header">Accès rapide</div>
            <div class="card-body d-flex gap-2 flex-wrap">
                <a href="../modules/polices/liste.php" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-text"></i> Gérer les polices
                </a>
                <a href="../modules/polices/ajouter.php" class="btn btn-outline-success">
                    <i class="bi bi-plus-circle"></i> Nouvelle police
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
// Le graphique est alimenté par $repartitionBranches, calculé plus haut
// via une vraie requête SQL (JOIN branches / polices), pas de données en dur.
const ctx = document.getElementById('graphBranches');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($repartitionBranches, 'libelle')) ?>,
        datasets: [{
            label: 'Primes commerciales (FCFA)',
            data: <?= json_encode(array_map('floatval', array_column($repartitionBranches, 'total_primes'))) ?>,
            backgroundColor: '#0d6efd'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: (v) => v.toLocaleString('fr-FR') } } }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
