<?php
/**
 * scripts/alerte_echeances.php
 * ------------------------------------------------------------------
 * Déclencheur : une police active arrive à échéance dans les
 * SEUIL_JOURS_ALERTE_ECHEANCE prochains jours (7 par défaut).
 *
 * A exécuter une fois par jour via une tâche planifiée (cron sous
 * Linux/Mac, Planificateur de tâches sous Windows) :
 *
 *   Linux/Mac (crontab -e) — tous les jours à 7h00 :
 *     0 7 * * * /usr/bin/php /chemin/vers/actuariat_assurance/scripts/alerte_echeances.php
 *
 *   Windows (Planificateur de tâches) :
 *     Programme : C:\xampp\php\php.exe
 *     Arguments : C:\xampp\htdocs\actuariat_assurance\scripts\alerte_echeances.php
 *     Déclencheur : quotidien, 07:00
 *
 * Le script ne renvoie jamais deux fois la même alerte pour la même
 * police (colonne alerte_echeance_envoyee).
 * ------------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$db = Database::getConnexion();

// --- Recherche des polices actives arrivant à échéance sous le seuil, pas encore notifiées ---
$stmt = $db->prepare(
    "SELECT p.id_police, p.numero_police, p.nom_assure, p.date_expiration, b.libelle AS branche
     FROM polices p
     JOIN branches b ON b.id_branche = p.id_branche
     WHERE p.statut = 'actif'
       AND p.alerte_echeance_envoyee = 0
       AND p.date_expiration BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :seuil DAY)
     ORDER BY p.date_expiration ASC"
);
$stmt->bindValue(':seuil', SEUIL_JOURS_ALERTE_ECHEANCE, PDO::PARAM_INT);
$stmt->execute();
$policesAEcheance = $stmt->fetchAll();

if (empty($policesAEcheance)) {
    echo "Aucune police à échéance sous " . SEUIL_JOURS_ALERTE_ECHEANCE . " jours. Rien à envoyer.\n";
    exit(0);
}

// --- Construction du corps de l'email (HTML) ---
$lignes = '';
foreach ($policesAEcheance as $p) {
    $lignes .= sprintf(
        '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
        htmlspecialchars($p['numero_police']),
        htmlspecialchars($p['nom_assure']),
        htmlspecialchars($p['branche']),
        date('d/m/Y', strtotime($p['date_expiration']))
    );
}

$corpsHtml = "
<h2>Polices arrivant à échéance sous " . SEUIL_JOURS_ALERTE_ECHEANCE . " jours</h2>
<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-family:sans-serif;font-size:13px;'>
<tr style='background:#0d6efd;color:#fff;'><th>N° Police</th><th>Assuré</th><th>Branche</th><th>Date d'expiration</th></tr>
$lignes
</table>
<p style='font-family:sans-serif;font-size:12px;color:#666;'>Email généré automatiquement par " . NOM_APPLICATION . ".</p>
";

// --- Envoi via PHPMailer ---
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOTE;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_UTILISATEUR;
    $mail->Password   = SMTP_MOT_DE_PASSE;
    $mail->SMTPSecure = SMTP_CHIFFREMENT;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(MAIL_EXPEDITEUR, MAIL_EXPEDITEUR_NOM);
    foreach (MAIL_DESTINATAIRES_ALERTES as $destinataire) {
        $mail->addAddress($destinataire);
    }

    $mail->isHTML(true);
    $mail->Subject = '[Actuariat Assurance] ' . count($policesAEcheance) . ' police(s) à échéance sous ' . SEUIL_JOURS_ALERTE_ECHEANCE . ' jours';
    $mail->Body    = $corpsHtml;

    $mail->send();

    // Marque les polices comme notifiées pour ne pas renvoyer l'alerte demain
    $idsNotifies = array_column($policesAEcheance, 'id_police');
    $placeholders = implode(',', array_fill(0, count($idsNotifies), '?'));
    $db->prepare("UPDATE polices SET alerte_echeance_envoyee = 1 WHERE id_police IN ($placeholders)")
       ->execute($idsNotifies);

    journaliser(null, 'EMAIL_ALERTE_ECHEANCE', 'polices', null,
        count($policesAEcheance) . ' police(s) notifiée(s) : ' . implode(', ', array_column($policesAEcheance, 'numero_police')));

    echo count($policesAEcheance) . " police(s) notifiée(s) par email avec succès.\n";
} catch (PHPMailerException $e) {
    error_log('Erreur envoi email alerte échéances : ' . $mail->ErrorInfo);
    echo "Échec de l'envoi : " . $mail->ErrorInfo . "\n";
    exit(1);
}
