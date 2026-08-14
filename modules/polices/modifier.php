<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

exigerRole(['admin', 'utilisateur_avance', 'standard']);

$db = Database::getConnexion();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'texte' => 'Police invalide.'];
    header('Location: liste.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM polices WHERE id_police = :id");
$stmt->execute([':id' => $id]);
$police = $stmt->fetch();

if (!$police) {
    $_SESSION['flash'] = ['type' => 'danger', 'texte' => 'Police introuvable.'];
    header('Location: liste.php');
    exit;
}

$erreurs = [];
$valeurs = $police;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierJetonCsrf($_POST['csrf'] ?? '')) {
        $erreurs[] = "Session expirée, merci de réessayer.";
    } else {
        $valeurs['numero_police']    = nettoyer($_POST['numero_police'] ?? '');
        $valeurs['id_branche']       = (int)($_POST['id_branche'] ?? 0);
        $valeurs['nom_assure']       = nettoyer($_POST['nom_assure'] ?? '');
        $valeurs['telephone_assure'] = nettoyer($_POST['telephone_assure'] ?? '');
        $valeurs['date_effet']       = nettoyer($_POST['date_effet'] ?? '');
        $valeurs['date_expiration']  = nettoyer($_POST['date_expiration'] ?? '');
        $valeurs['prime_pure']       = str_replace(',', '.', nettoyer($_POST['prime_pure'] ?? ''));
        $valeurs['chargement_pct']   = str_replace(',', '.', nettoyer($_POST['chargement_pct'] ?? ''));
        $valeurs['statut']           = nettoyer($_POST['statut'] ?? 'actif');

        if ($valeurs['numero_police'] === '') $erreurs[] = "Le numéro de police est obligatoire.";
        if ($valeurs['id_branche'] <= 0) $erreurs[] = "Merci de choisir une branche.";
        if ($valeurs['nom_assure'] === '') $erreurs[] = "Le nom de l'assuré est obligatoire.";
        if (!strtotime($valeurs['date_effet'])) $erreurs[] = "La date d'effet est invalide.";
        if (!strtotime($valeurs['date_expiration'])) $erreurs[] = "La date d'expiration est invalide.";
        if (strtotime($valeurs['date_effet']) && strtotime($valeurs['date_expiration'])
            && strtotime($valeurs['date_expiration']) <= strtotime($valeurs['date_effet'])) {
            $erreurs[] = "La date d'expiration doit être postérieure à la date d'effet.";
        }
        if (!is_numeric($valeurs['prime_pure']) || (float)$valeurs['prime_pure'] < 0) {
            $erreurs[] = "La prime pure doit être un nombre positif.";
        }
        if (!is_numeric($valeurs['chargement_pct']) || (float)$valeurs['chargement_pct'] < 0) {
            $erreurs[] = "Le taux de chargement doit être un nombre positif.";
        }
        if (!in_array($valeurs['statut'], ['actif', 'suspendu', 'resilie', 'expire'], true)) {
            $erreurs[] = "Statut invalide.";
        }

        // Unicité du numéro (hors cette police)
        if (empty($erreurs)) {
            $stmtVerif = $db->prepare("SELECT COUNT(*) FROM polices WHERE numero_police = :numero AND id_police != :id");
            $stmtVerif->execute([':numero' => $valeurs['numero_police'], ':id' => $id]);
            if ($stmtVerif->fetchColumn() > 0) {
                $erreurs[] = "Ce numéro de police est déjà utilisé par une autre police.";
            }
        }

        if (empty($erreurs)) {
            $primePure  = (float)$valeurs['prime_pure'];
            $chargement = (float)$valeurs['chargement_pct'];
            $primeCommerciale = round($primePure * (1 + $chargement / 100), 2);

            $stmtMaj = $db->prepare(
                "UPDATE polices SET
                    numero_police = :numero_police,
                    id_branche = :id_branche,
                    nom_assure = :nom_assure,
                    telephone_assure = :telephone_assure,
                    date_effet = :date_effet,
                    date_expiration = :date_expiration,
                    prime_pure = :prime_pure,
                    chargement_pct = :chargement_pct,
                    prime_commerciale = :prime_commerciale,
                    statut = :statut
                 WHERE id_police = :id"
            );
            $stmtMaj->execute([
                ':numero_police'    => $valeurs['numero_police'],
                ':id_branche'       => $valeurs['id_branche'],
                ':nom_assure'       => $valeurs['nom_assure'],
                ':telephone_assure' => $valeurs['telephone_assure'] ?: null,
                ':date_effet'       => $valeurs['date_effet'],
                ':date_expiration'  => $valeurs['date_expiration'],
                ':prime_pure'       => $primePure,
                ':chargement_pct'   => $chargement,
                ':prime_commerciale'=> $primeCommerciale,
                ':statut'           => $valeurs['statut'],
                ':id'               => $id,
            ]);

            journaliser($_SESSION['id_utilisateur'], 'UPDATE', 'polices', $id,
                'Modification police ' . $valeurs['numero_police']);

            $_SESSION['flash'] = ['type' => 'success', 'texte' => 'Police modifiée avec succès.'];
            header('Location: liste.php');
            exit;
        }
    }
}

$branches = $db->query("SELECT id_branche, libelle FROM branches ORDER BY libelle")->fetchAll();
$csrf = genererJetonCsrf();

$titrePage = 'Modifier la police';
require_once __DIR__ . '/../../includes/header.php';
?>

<h2 class="mb-4"><i class="bi bi-pencil-square"></i> Modifier la police <?= e($police['numero_police']) ?></h2>

<?php if ($erreurs): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($erreurs as $e2): ?><li><?= e($e2) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="modifier.php?id=<?= $id ?>" id="formPolice" novalidate>
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">N° de police *</label>
                    <input type="text" name="numero_police" class="form-control" required
                           value="<?= e($valeurs['numero_police']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Branche *</label>
                    <select name="id_branche" class="form-select" required>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int)$b['id_branche'] ?>" <?= (int)$valeurs['id_branche'] === (int)$b['id_branche'] ? 'selected' : '' ?>>
                                <?= e($b['libelle']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Statut *</label>
                    <select name="statut" class="form-select" required>
                        <?php foreach (['actif', 'suspendu', 'resilie', 'expire'] as $s): ?>
                            <option value="<?= e($s) ?>" <?= $valeurs['statut'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nom de l'assuré *</label>
                    <input type="text" name="nom_assure" class="form-control" required
                           value="<?= e($valeurs['nom_assure']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone_assure" class="form-control"
                           value="<?= e($valeurs['telephone_assure']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Date d'effet *</label>
                    <input type="date" name="date_effet" class="form-control" required
                           value="<?= e(substr($valeurs['date_effet'], 0, 10)) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date d'expiration *</label>
                    <input type="date" name="date_expiration" class="form-control" required
                           value="<?= e(substr($valeurs['date_expiration'], 0, 10)) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Prime pure (FCFA) *</label>
                    <input type="number" step="0.01" min="0" name="prime_pure" class="form-control" required
                           value="<?= e($valeurs['prime_pure']) ?>" id="primePure">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Taux de chargement (%) *</label>
                    <input type="number" step="0.01" min="0" name="chargement_pct" class="form-control" required
                           value="<?= e($valeurs['chargement_pct']) ?>" id="chargementPct">
                </div>

                <div class="col-12">
                    <div class="alert alert-secondary mb-0">
                        Prime commerciale estimée : <strong id="primeEstimee">0 FCFA</strong>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Mettre à jour</button>
                <a href="liste.php" class="btn btn-outline-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('formPolice');
    form.addEventListener('submit', (event) => {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });

    const primePureInput = document.getElementById('primePure');
    const chargementInput = document.getElementById('chargementPct');
    const affichage = document.getElementById('primeEstimee');

    function recalculer() {
        const pp = parseFloat(primePureInput.value) || 0;
        const ch = parseFloat(chargementInput.value) || 0;
        affichage.textContent = (pp * (1 + ch / 100)).toLocaleString('fr-FR', { maximumFractionDigits: 0 }) + ' FCFA';
    }
    primePureInput.addEventListener('input', recalculer);
    chargementInput.addEventListener('input', recalculer);
    recalculer();
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
