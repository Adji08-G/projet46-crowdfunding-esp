<?php
/**
 * config/mail.php
 * Paramètres SMTP pour l'envoi d'emails via PHPMailer.
 * Adapte ces constantes à ton fournisseur (Gmail, Outlook, un serveur
 * SMTP de l'établissement, Mailtrap pour les tests, etc.).
 */

define('SMTP_HOTE', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_UTILISATEUR', 'ton.compte@gmail.com');
define('SMTP_MOT_DE_PASSE', 'mot_de_passe_application'); // utilise un "mot de passe d'application", jamais ton mot de passe principal
define('SMTP_CHIFFREMENT', 'tls'); // 'tls' ou 'ssl'

define('MAIL_EXPEDITEUR', 'no-reply@espdakar.sn');
define('MAIL_EXPEDITEUR_NOM', "Plateforme d'Actuariat - Assurance");

// Destinataires des alertes d'échéance (peut être plusieurs adresses)
define('MAIL_DESTINATAIRES_ALERTES', ['admin@espdakar.sn']);

// Nombre de jours avant expiration déclenchant l'alerte
define('SEUIL_JOURS_ALERTE_ECHEANCE', 7);
