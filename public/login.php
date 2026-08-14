<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Déjà connecté -> direct au dashboard
if (!empty($_SESSION['id_utilisateur'])) {
    header('Location: dashboard.php');
    exit;
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierJetonCsrf($_POST['csrf'] ?? '')) {
        $erreur = "Session expirée, merci de réessayer.";
    } else {
        $email = filter_var(nettoyer($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $motDePasse = $_POST['mot_de_passe'] ?? '';

        if ($email === '' || $motDePasse === '') {
            $erreur = "Merci de renseigner l'email et le mot de passe.";
        } elseif (tenterConnexion($email, $motDePasse)) {
            header('Location: dashboard.php');
            exit;
        } else {
            $erreur = "Email ou mot de passe incorrect.";
        }
    }
}
$csrf = genererJetonCsrf();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | <?= e(NOM_APPLICATION) ?></title>
    <link rel="icon" href="../assets/img/logo.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center" style="min-height:100vh;">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-lg border-0">
                <div class="card-body p-4">
                    <div class="text-center mb-1">
                        <a href="accueil.php"><img src="../assets/img/logo.svg" alt="Logo" height="64"></a>
                    </div>
                    <h3 class="text-center mb-1">Actuariat Assurance</h3>
                    <p class="text-center text-muted mb-4">Master CCA — ESP Dakar</p>

                    <?php if ($erreur): ?>
                        <div class="alert alert-danger"><?= e($erreur) ?></div>
                    <?php endif; ?>

                    <?php if (isset($_GET['expire'])): ?>
                        <div class="alert alert-warning">Votre session a expiré, veuillez vous reconnecter.</div>
                    <?php endif; ?>

                    <form method="post" action="login.php" novalidate>
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <div class="mb-3">
                            <label class="form-label">Adresse email</label>
                            <input type="email" name="email" class="form-control" required autofocus
                                   value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mot de passe</label>
                            <input type="password" name="mot_de_passe" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-box-arrow-in-right"></i> Se connecter
                        </button>
                    </form>

                    <hr>
                    <p class="small text-muted mb-1">Comptes de démonstration :</p>
                    <ul class="small text-muted mb-0">
                        <li>admin@espdakar.sn / Admin@2026 (Administrateur)</li>
                        <li>avance@espdakar.sn / Avance@2026 (Utilisateur avancé)</li>
                        <li>standard@espdakar.sn / Standard@2026 (Standard)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
