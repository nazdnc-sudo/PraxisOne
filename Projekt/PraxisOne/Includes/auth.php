<?php
// ============================================================
// Authentifizierung & Session-Management
// ============================================================

function session_sichern(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => false,   // Auf true setzen wenn HTTPS!
            'httponly' => true,    // JS kann nicht auf Cookie zugreifen
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}

// Eingeloggt? + Timeout nach 30 Minuten Inaktivität
function login_pruefen(): void {
    session_sichern();
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    if (isset($_SESSION['letzte_aktivitaet']) && (time() - $_SESSION['letzte_aktivitaet']) > 1800) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }
    $_SESSION['letzte_aktivitaet'] = time();
}

// Rollenprüfung – leitet bei falscher Rolle weiter
function rolle_pruefen(string $erlaubte_rolle): void {
    session_sichern();
    if (($_SESSION['rolle'] ?? '') !== $erlaubte_rolle) {
        header("Location: login.php");
        exit();
    }
}

// Aktuellen Benutzer als Array zurückgeben
function aktueller_user(): array {
    session_sichern();
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['realname'] ?? $_SESSION['username'],
        'rolle' => $_SESSION['rolle'],
    ];
}

// CSRF-Token generieren oder vorhandenes zurückgeben
function csrf_token(): string {
    session_sichern();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF-Token validieren
function csrf_pruefen(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die("Ungültige Anfrage (CSRF-Schutz). Bitte Seite neu laden.");
    }
}
