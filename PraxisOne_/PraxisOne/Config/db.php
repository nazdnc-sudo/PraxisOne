<?php
// ============================================================
// Datenbankverbindung – PraxisOne
// ============================================================
//
// LOKALE ENTWICKLUNG (XAMPP/WAMP/MAMP):
//   DB_USER = 'root'
//   DB_PASS = ''   (leeres Passwort)
//
// PRODUKTIVSERVER:
//   Eigenen DB-Benutzer anlegen (kein root!):
//   CREATE USER 'praxisone_user'@'localhost' IDENTIFIED BY 'sicheres_passwort';
//   GRANT ALL PRIVILEGES ON praxisone.* TO 'praxisone_user'@'localhost';
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'praxisone');
define('DB_USER',    'root');      // Für XAMPP/WAMP: root
define('DB_PASS',    '');          // Für XAMPP/WAMP: leer lassen
define('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log("DB-Fehler: " . $e->getMessage());
    die("Datenbankverbindung fehlgeschlagen. Bitte prüfen Sie die Zugangsdaten in Config/db.php.");
}
