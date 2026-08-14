<?php
/**
 * config/database.php
 * Connexion PDO à la base MySQL via un pattern Singleton.
 * Toutes les requêtes de l'application passent par cette classe.
 */

class Database
{
    private static ?PDO $connexion = null;

    // Adapte ces constantes à ton environnement XAMPP si besoin
    private const HOTE       = '127.0.0.1';
    private const PORT       = '3306';
    private const BASE       = 'actuariat_assurance';
    private const UTILISATEUR = 'root';
    private const MOT_DE_PASSE = ''; // vide par défaut sous XAMPP
    private const CHARSET    = 'utf8mb4';

    private function __construct()
    {
        // Empêche l'instanciation directe
    }

    public static function getConnexion(): PDO
    {
        if (self::$connexion === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                self::HOTE,
                self::PORT,
                self::BASE,
                self::CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // vraies requêtes préparées
            ];

            try {
                self::$connexion = new PDO($dsn, self::UTILISATEUR, self::MOT_DE_PASSE, $options);
            } catch (PDOException $e) {
                // On ne remonte jamais le message brut de PDO à l'écran (sécurité)
                error_log('Erreur connexion BDD : ' . $e->getMessage());
                die('Erreur de connexion à la base de données. Contactez l\'administrateur.');
            }
        }

        return self::$connexion;
    }
}
