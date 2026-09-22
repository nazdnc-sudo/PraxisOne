<?php
// ============================================================
// passwort_setup.php – Einmalig ausführen um echte Passwort-
// Hashes in die Datenbank einzutragen.
// DANACH DIESE DATEI SOFORT LÖSCHEN!
// ============================================================

require_once "Config/db.php";

$benutzer = [
    // [ID, username, Passwort, Rolle]
    [1, 'max.mustermann',  'Pat123!',  'Pat'],
    [2, 'anna.mueller',    'Pat123!',  'Pat'],
    [3, 'klaus.schmidt',   'Pat123!',  'Pat'],
    [4, 'maria.weber',     'Pat123!',  'Pat'],
    [5, 'thomas.fischer',  'Pat123!',  'Pat'],
    [6, 'dr.schneider',    'Arzt123!', 'Arzt'],
    [7, 'dr.hoffmann',     'Arzt123!', 'Arzt'],
    [8, 'lisa.klein',      'Ass123!',  'Ass'],
    [9, 'marco.braun',     'Ass123!',  'Ass'],
];

$aktualisiert = 0;
foreach ($benutzer as [$id, $username, $passwort, $rolle]) {
    $hash = password_hash($passwort, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE benutzer SET PW_Hash = :hash WHERE ID = :id");
    $stmt->execute([':hash' => $hash, ':id' => $id]);
    $aktualisiert++;
}

echo "<!DOCTYPE html><html lang='de'><head><meta charset='UTF-8'>
<style>body{font-family:Arial;background:#f0f2f5;padding:40px;} .box{background:white;max-width:600px;margin:0 auto;padding:32px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.1);}
table{width:100%;border-collapse:collapse;margin-top:16px;} th{background:#1F4E79;color:white;padding:10px;text-align:left;} td{padding:10px;border-bottom:1px solid #eee;}
.warn{background:#fde8e8;color:#a32d2d;padding:14px;border-radius:6px;margin-top:20px;font-weight:bold;}
.ok{background:#e6f4ea;color:#2d6a3f;padding:14px;border-radius:6px;margin-bottom:20px;}
</style></head><body><div class='box'>
<h2 style='color:#1F4E79'>✅ Passwörter gesetzt</h2>
<div class='ok'>$aktualisiert Benutzer erfolgreich aktualisiert.</div>
<table>
<tr><th>Benutzername</th><th>Passwort</th><th>Rolle</th></tr>";

foreach ($benutzer as [$id, $username, $passwort, $rolle]) {
    echo "<tr><td>$username</td><td><strong>$passwort</strong></td><td>$rolle</td></tr>";
}

echo "</table>
<div class='warn'>⚠️ WICHTIG: Löschen Sie diese Datei jetzt sofort vom Server!<br>
<code>passwort_setup.php</code> darf nicht öffentlich zugänglich bleiben.</div>
<p style='margin-top:16px'><a href='login.php' style='color:#1F4E79;font-weight:bold'>→ Zur Login-Seite</a></p>
</div></body></html>";
