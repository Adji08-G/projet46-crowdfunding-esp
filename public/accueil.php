<?php
require_once __DIR__ . '/../config/config.php';

// Si déjà connecté, on va directement au tableau de bord
if (!empty($_SESSION['id_utilisateur'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plateforme d'Actuariat Assurance | Master CCA — ESP Dakar</title>
    <link rel="icon" href="../assets/img/logo.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --bleu-fonce: #0d2b45;
            --bleu: #1F4E79;
            --or: #F2B705;
        }
        body {
            background: linear-gradient(160deg, var(--bleu-fonce) 0%, var(--bleu) 55%, #2c6ca3 100%);
            min-height: 100vh;
            color: #fff;
        }
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .logo-accueil {
            width: 110px;
            height: 110px;
            filter: drop-shadow(0 6px 14px rgba(0,0,0,.35));
        }
        .carte-fonctionnalite {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 1rem;
            backdrop-filter: blur(4px);
            transition: transform .2s ease, background .2s ease;
        }
        .carte-fonctionnalite:hover {
            transform: translateY(-4px);
            background: rgba(255,255,255,0.12);
        }
        .icone-or { color: var(--or); }
        .btn-connexion {
            background-color: var(--or);
            border-color: var(--or);
            color: var(--bleu-fonce);
            font-weight: 600;
        }
        .btn-connexion:hover {
            background-color: #d9a500;
            border-color: #d9a500;
            color: var(--bleu-fonce);
        }
        .bandeau-esp {
            letter-spacing: .5px;
        }
    </style>
</head>
<body>

<section class="hero">
    <div class="container text-center">

        <img src="../assets/img/logo.svg" alt="Logo Plateforme d'Actuariat Assurance" class="logo-accueil mb-4">

        <p class="bandeau-esp text-uppercase small mb-1" style="color:#cfe0f2;">
            École Supérieure Polytechnique de Dakar — Master CCA
        </p>
        <h1 class="display-5 fw-bold mb-2">Plateforme d'Actuariat Assurance</h1>
        <p class="lead mb-5" style="color:#dbe7f4;">
            Tarification, provisionnement technique (PPNA, PRC, PSAP, PM) et pilotage ORSA
            <br class="d-none d-md-block">
            pour compagnies d'assurance — conforme au Code CIMA
        </p>

        <div class="row g-3 justify-content-center mb-5">
            <div class="col-6 col-md-3">
                <div class="carte-fonctionnalite p-3 h-100">
                    <i class="bi bi-shield-lock icone-or fs-3"></i>
                    <div class="small mt-2">Accès sécurisé<br>par rôle</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="carte-fonctionnalite p-3 h-100">
                    <i class="bi bi-graph-up-arrow icone-or fs-3"></i>
                    <div class="small mt-2">Tarification<br>fréquence-coût</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="carte-fonctionnalite p-3 h-100">
                    <i class="bi bi-clipboard-data icone-or fs-3"></i>
                    <div class="small mt-2">Provisions<br>techniques</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="carte-fonctionnalite p-3 h-100">
                    <i class="bi bi-file-earmark-bar-graph icone-or fs-3"></i>
                    <div class="small mt-2">Reporting<br>ORSA</div>
                </div>
            </div>
        </div>

        <a href="login.php" class="btn btn-connexion btn-lg px-5 py-2 rounded-pill shadow">
            <i class="bi bi-box-arrow-in-right"></i> Se connecter
        </a>

        <p class="small mt-4" style="color:#9fb8d1;">
            Projet n°46 — M. Ousmane LY, Enseignant-Chercheur — Année universitaire 2025–2026
        </p>
    </div>
</section>

</body>
</html>
