<?php
// Attend que $titrePage soit défini par la page appelante
$titrePage = $titrePage ?? NOM_APPLICATION;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titrePage) ?> | <?= e(NOM_APPLICATION) ?></title>
    <link rel="icon" href="<?= ROOT_URL ?>assets/img/logo.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ROOT_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>dashboard.php">
        <img src="<?= ROOT_URL ?>assets/img/logo.svg" alt="Logo" height="32">
        Actuariat Assurance
    </a>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>dashboard.php">Tableau de bord</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= ROOT_URL ?>modules/polices/liste.php">Polices</a></li>
        </ul>
    </div>
    <?php if (!empty($_SESSION['nom_complet'])): ?>
    <div class="d-flex align-items-center text-light">
        <span class="me-3">
            <i class="bi bi-person-circle"></i>
            <?= e($_SESSION['nom_complet']) ?>
            <span class="badge bg-info text-dark ms-1"><?= e(libelleRole($_SESSION['role'])) ?></span>
        </span>
        <a href="<?= BASE_URL ?>logout.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-box-arrow-right"></i> Déconnexion
        </a>
    </div>
    <?php endif; ?>
</nav>
<main class="container-fluid py-4">
