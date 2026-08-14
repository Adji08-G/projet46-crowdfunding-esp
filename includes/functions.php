<?php
/**
 * includes/functions.php
 * Fonctions utilitaires réutilisables dans tous les modules.
 */

/**
 * Echappe une chaîne pour affichage HTML (protection XSS).
 */
function e(?string $valeur): string
{
    return htmlspecialchars($valeur ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Nettoie une entrée texte (trim + suppression des tags).
 */
function nettoyer(?string $valeur): string
{
    return trim(strip_tags($valeur ?? ''));
}

/**
 * Formate un montant en Francs CFA.
 */
function formaterMontant($montant): string
{
    return number_format((float)$montant, 0, ',', ' ') . ' FCFA';
}

/**
 * Formate une date au format français jj/mm/aaaa.
 */
function formaterDate(?string $date): string
{
    if (empty($date)) {
        return '-';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : '-';
}

/**
 * Calcule les paramètres de pagination à partir du nombre total de lignes.
 * Retourne un tableau : page courante, nb de pages, offset SQL.
 */
function calculerPagination(int $totalLignes, int $itemsParPage = ITEMS_PAR_PAGE): array
{
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $nbPages = max(1, (int)ceil($totalLignes / $itemsParPage));
    $page = min($page, $nbPages);
    $offset = ($page - 1) * $itemsParPage;

    return [
        'page'      => $page,
        'nb_pages'  => $nbPages,
        'offset'    => $offset,
        'par_page'  => $itemsParPage,
    ];
}

/**
 * Génère un badge Bootstrap coloré selon le statut d'une police.
 */
function badgeStatutPolice(string $statut): string
{
    $classes = [
        'actif'    => 'success',
        'suspendu' => 'warning',
        'resilie'  => 'danger',
        'expire'   => 'secondary',
    ];
    $classe = $classes[$statut] ?? 'secondary';
    return '<span class="badge bg-' . $classe . '">' . e(ucfirst($statut)) . '</span>';
}

/**
 * Moteur de règles simple : à partir des indicateurs calculés du dashboard,
 * génère une liste de commentaires automatiques classés par niveau de
 * sévérité (alerte / attention / positif), avec un message explicatif.
 * Chaque règle est indépendante : facile d'en ajouter/retirer.
 */
function genererCommentaires(array $indicateurs): array
{
    $commentaires = [];

    // --- Règle 1 : taux de sinistralité (montant sinistres / primes commerciales) ---
    $taux = $indicateurs['taux_sinistralite'];
    if ($taux >= 85) {
        $commentaires[] = ['niveau' => 'alerte', 'message' =>
            "Taux de sinistralité critique (" . number_format($taux, 1) . " %) : la charge sinistres dépasse largement les primes encaissées."];
    } elseif ($taux >= 60) {
        $commentaires[] = ['niveau' => 'attention', 'message' =>
            "Taux de sinistralité élevé (" . number_format($taux, 1) . " %) : à surveiller de près sur les prochains exercices."];
    } else {
        $commentaires[] = ['niveau' => 'positif', 'message' =>
            "Taux de sinistralité maîtrisé (" . number_format($taux, 1) . " %), sous le seuil d'alerte de 60 %."];
    }

    // --- Règle 2 : sinistres ouverts non traités ---
    if ($indicateurs['nb_sinistres_ouverts'] >= 5) {
        $commentaires[] = ['niveau' => 'attention', 'message' =>
            $indicateurs['nb_sinistres_ouverts'] . " sinistres restent ouverts : pense à relancer leur instruction."];
    } elseif ($indicateurs['nb_sinistres_ouverts'] === 0) {
        $commentaires[] = ['niveau' => 'positif', 'message' => "Aucun sinistre ouvert en attente de traitement."];
    }

    // --- Règle 3 : échéances proches ---
    if ($indicateurs['nb_echeances_proches'] > 0) {
        $commentaires[] = ['niveau' => 'attention', 'message' =>
            $indicateurs['nb_echeances_proches'] . " police(s) arrivent à échéance dans les 30 prochains jours : anticiper le renouvellement."];
    } else {
        $commentaires[] = ['niveau' => 'positif', 'message' => "Aucune échéance de police dans les 30 prochains jours."];
    }

    // --- Règle 4 : réserves (PSAP) au-delà d'un seuil vs primes en cours ---
    if ($indicateurs['prime_totale'] > 0) {
        $ratioReserves = ($indicateurs['total_reserves'] / $indicateurs['prime_totale']) * 100;
        if ($ratioReserves >= 25) {
            $commentaires[] = ['niveau' => 'alerte', 'message' =>
                "Les réserves sinistres (PSAP) représentent " . number_format($ratioReserves, 1) . " % des primes en cours : provisionnement à revoir."];
        }
    }

    return $commentaires;
}

/**
 * Génère un jeton CSRF et le stocke en session.
 */
function genererJetonCsrf(): string
{
    if (empty($_SESSION['jeton_csrf'])) {
        $_SESSION['jeton_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['jeton_csrf'];
}

/**
 * Vérifie le jeton CSRF envoyé dans un formulaire.
 */
function verifierJetonCsrf(?string $jeton): bool
{
    return !empty($jeton) && !empty($_SESSION['jeton_csrf']) && hash_equals($_SESSION['jeton_csrf'], $jeton);
}
