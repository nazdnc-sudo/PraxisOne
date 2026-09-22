<?php
require_once __DIR__ . "/../Includes/auth.php";
$__user  = isset($user) ? $user : (isset($_SESSION['user_id']) ? aktueller_user() : null);
$__rolle = $_SESSION['rolle'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PraxisOne – <?= htmlspecialchars($seiten_titel ?? 'Portal') ?></title>
    <link rel="stylesheet" href="Assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="<?= $__rolle === 'Pat' ? 'dashboard_patient.php' : ($__rolle === 'Arzt' ? 'dashboard_arzt.php' : 'dashboard_fachkraft.php') ?>" class="navbar-brand" style="text-decoration:none;display:flex;align-items:center;gap:10px;">
        <svg width="32" height="32" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M6 12C6 7.5781 9.5781 4 14 4H34C38.4219 4 42 7.5781 42 12V28C42 36.5 34.5 42 24 44C13.5 42 6 36.5 6 28V12Z" fill="white" fill-opacity="0.9"/>
            <path d="M15 24H21L23 17L26 29L28 22H33" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M24 13V19" stroke="#0f2d59" stroke-width="3" stroke-linecap="round"/>
            <path d="M21 16H27" stroke="#0f2d59" stroke-width="3" stroke-linecap="round"/>
        </svg>
        <span style="font-weight:400;letter-spacing:-0.3px;">Patienten<strong style="font-weight:800;">Portal</strong></span>
    </a>

    <div class="navbar-links">
        <?php if ($__rolle === 'Arzt'): ?>
            <a href="dashboard_arzt.php"    class="<?= ($aktive_seite ?? '') === 'dashboard'  ? 'aktiv' : '' ?>">Übersicht</a>
        <?php elseif ($__rolle === 'Ass'): ?>
            <a href="dashboard_fachkraft.php" class="<?= ($aktive_seite ?? '') === 'dashboard' ? 'aktiv' : '' ?>">Übersicht</a>
            <a href="xml_import.php"          class="<?= ($aktive_seite ?? '') === 'import'    ? 'aktiv' : '' ?>">XML-Import</a>
        <?php elseif ($__rolle === 'Pat'): ?>
            <a href="dashboard_patient.php"     class="<?= ($aktive_seite ?? '') === 'dashboard'   ? 'aktiv' : '' ?>">Dashboard</a>
            <a href="messung.php"               class="<?= ($aktive_seite ?? '') === 'messung'     ? 'aktiv' : '' ?>">Messung</a>
            <a href="verlauf.php"               class="<?= ($aktive_seite ?? '') === 'verlauf'     ? 'aktiv' : '' ?>">Verlauf</a>
            <a href="stammdaten_bearbeiten.php" class="<?= ($aktive_seite ?? '') === 'stammdaten'  ? 'aktiv' : '' ?>">Mein Profil</a>
        <?php endif; ?>
    </div>

    <div class="navbar-user">
        <?php if ($__user): ?>
            <span class="navbar-name">
                <?= $__rolle === 'Arzt' ? 'Dr. ' : '' ?><?= htmlspecialchars($__user['name']) ?>
            </span>
            <span class="navbar-rolle navbar-rolle-<?= strtolower($__rolle) ?>">
                <?= match($__rolle) { 'Arzt' => 'Arzt', 'Ass' => 'Fachkraft', 'Pat' => 'Patient', default => '' } ?>
            </span>
            <a href="logout.php" class="navbar-logout">Abmelden</a>
        <?php endif; ?>
    </div>
</nav>

<?php if (isset($_GET['timeout'])): ?>
<div class="alert alert-warn" style="text-align:center;border-radius:0;margin:0;">
    ⏱️ Sie wurden nach 30 Minuten Inaktivität automatisch abgemeldet.
</div>
<?php endif; ?>

<div class="container">
