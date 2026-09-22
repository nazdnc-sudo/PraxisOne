<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $r = $_SESSION['rolle'];
    header("Location: " . match($r) {
        'Pat'  => 'dashboard_patient.php',
        'Ass'  => 'dashboard_fachkraft.php',
        'Arzt' => 'dashboard_arzt.php',
        default => 'login.php'
    });
    exit();
}
require_once "Config/db.php";
require_once "Includes/auth.php";

$fehler = "";
$username_eingabe = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_eingabe = trim($_POST['username'] ?? '');
    $passwort         = $_POST['passwort'] ?? '';

    if (empty($username_eingabe) || empty($passwort)) {
        $fehler = "Bitte Benutzername und Passwort eingeben.";
    } else {
        $sql = "SELECT b.ID, b.username, b.PW_Hash, b.Rolle,
                       COALESCE(a.Vorname, p.Vorname, '') AS vorname,
                       COALESCE(a.Nachname, p.Name, '')   AS nachname
                FROM benutzer b
                LEFT JOIN arzt      a ON a.AID = b.ID
                LEFT JOIN patienten p ON p.PID = b.ID
                WHERE b.username = :username";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':username' => $username_eingabe]);
        $user = $stmt->fetch();

        if ($user && password_verify($passwort, $user['PW_Hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']           = $user['ID'];
            $_SESSION['username']          = $user['username'];
            $_SESSION['rolle']             = $user['Rolle'];
            $_SESSION['realname']          = trim($user['vorname'] . ' ' . $user['nachname']) ?: $user['username'];
            $_SESSION['letzte_aktivitaet'] = time();
            $_SESSION['csrf_token']        = bin2hex(random_bytes(32));
            header("Location: " . match($user['Rolle']) {
                'Pat'  => 'dashboard_patient.php',
                'Ass'  => 'dashboard_fachkraft.php',
                'Arzt' => 'dashboard_arzt.php',
                default => 'login.php'
            });
            exit();
        } else {
            $fehler = "Benutzername oder Passwort falsch.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PraxisOne – Anmelden</title>
    <link rel="stylesheet" href="Assets/css/style.css">
    <style>
        .logo-link-top { text-decoration:none; display:flex; align-items:center; gap:12px; margin-bottom:4px; transition:transform 0.2s; }
        .logo-link-top:hover { transform:translateY(-2px); }
        .logo-text-top { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; font-size:20px; font-weight:400; color:#0f2d59; letter-spacing:-0.5px; }
        .logo-text-top strong { font-weight:800; color:#2563eb; }

    </style>
</head>
<body class="auth-page">

<a href="index.html" class="logo-link-top">
    <svg width="44" height="44" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M6 12C6 7.5781 9.5781 4 14 4H34C38.4219 4 42 7.5781 42 12V28C42 36.5 34.5 42 24 44C13.5 42 6 36.5 6 28V12Z" fill="#0f2d59"/>
        <path d="M15 24H21L23 17L26 29L28 22H33" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M24 13V19" stroke="#ffffff" stroke-width="3" stroke-linecap="round"/>
        <path d="M21 16H27" stroke="#ffffff" stroke-width="3" stroke-linecap="round"/>
    </svg>
    <div class="logo-text-top">Patienten<strong>Portal</strong></div>
</a>

<div class="auth-box">
    <h2 class="auth-title">Willkommen</h2>
    <p class="auth-subtitle">Bitte melden Sie sich an</p>

    <?php if (isset($_GET['timeout'])): ?>
        <div class="alert alert-warn">⏱️ Sitzung abgelaufen – bitte erneut anmelden.</div>
    <?php endif; ?>

    <?php if ($fehler): ?>
        <div class="alert alert-error">
            <strong>Anmeldung fehlgeschlagen</strong><br>
            <?= htmlspecialchars($fehler) ?><br>
            <small style="color:inherit;opacity:0.8;margin-top:4px;display:block">
                Bitte prüfen Sie Ihren Benutzernamen und Ihr Passwort.<br>
                Bei Problemen wenden Sie sich an die Praxis: <strong>0731 123456</strong>
            </small>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="username">Benutzername</label>
            <input type="text" id="username" name="username"
                   value="<?= htmlspecialchars($username_eingabe) ?>"
                   maxlength="50" required autofocus autocomplete="username"
                   <?= $fehler ? 'style="border-color:#a32d2d"' : '' ?>>
        </div>
        <div class="form-group">
            <label for="passwort">Passwort</label>
            <input type="password" id="passwort" name="passwort"
                   required autocomplete="current-password"
                   <?= $fehler ? 'style="border-color:#a32d2d"' : '' ?>>
        </div>
        <button type="submit" class="btn btn-full" style="margin-top:8px;">Anmelden</button>
    </form>

    <div class="hinweis-kasten">
        <div class="hinweis-inhalt">
            <span class="hinweis-icon">ℹ️</span>
            <div>
                Noch kein Zugang? Bitte wenden Sie sich an Ihre Arztpraxis.
                <div class="hinweis-praxis">PraxisOne · Tel. 0731 123456</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
