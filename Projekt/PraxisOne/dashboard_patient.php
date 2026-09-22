<?php
session_start();
require_once "Includes/auth.php";
require_once "Includes/functions.php";
require_once "Config/db.php";

login_pruefen();
rolle_pruefen('Pat');

$user = aktueller_user();
$pid  = $user['id'];

$patient = $pdo->prepare("SELECT * FROM patienten WHERE PID=:id");
$patient->execute([':id'=>$pid]); $patient = $patient->fetch();

$stmt_b = $pdo->prepare("SELECT Email,Telefon FROM benutzer WHERE ID=:id");
$stmt_b->execute([':id'=>$pid]); $benutz = $stmt_b->fetch();

$stmt_m = $pdo->prepare("SELECT * FROM messungen WHERE PID=:id ORDER BY Datum DESC LIMIT 10");
$stmt_m->execute([':id'=>$pid]); $messungen = $stmt_m->fetchAll();

$stmt_d = $pdo->prepare("SELECT d.*,i.Text AS bezeichnung FROM diag_pro_pat d JOIN icd_codes i ON i.Code=d.ICD WHERE d.Pat_ID=:id ORDER BY d.DPID DESC");
$stmt_d->execute([':id'=>$pid]); $diagnosen = $stmt_d->fetchAll();

$letzte  = $messungen[0] ?? null;
$groesse = $patient['Groesse'] ?: null;
$bmi     = bmi_berechnen($letzte['Gewicht'] ?? null, $groesse);
$alter   = alter_berechnen($patient['Geburtsdatum'] ?? null);

$seiten_titel = "Mein Dashboard";
$aktive_seite = "dashboard";
require_once "Includes/header.php";
?>

<!-- Stammdaten -->
<div class="card">
    <div class="card-header">
        <h2>👤 Meine Stammdaten</h2>
        <a class="btn btn-sm btn-outline" href="stammdaten_bearbeiten.php">✏️ Bearbeiten</a>
    </div>
    <div class="info-grid">
        <div class="info-item"><span class="label">Name</span><?= htmlspecialchars($patient['Vorname'] . ' ' . $patient['Name']) ?></div>
        <?php if ($alter): ?><div class="info-item"><span class="label">Alter</span><?= $alter ?> Jahre (<?= datum_formatieren($patient['Geburtsdatum']) ?>)</div><?php endif; ?>
        <?php if ($patient['Strasse'] ?? ''): ?><div class="info-item"><span class="label">Adresse</span><?= htmlspecialchars(adresse_formatieren($patient)) ?></div><?php endif; ?>
        <?php if ($groesse): ?><div class="info-item"><span class="label">Körpergröße</span><?= $groesse ?> cm</div><?php endif; ?>
        <?php if (!empty($benutz['Email'])): ?><div class="info-item"><span class="label">E-Mail</span><?= htmlspecialchars($benutz['Email']) ?></div><?php endif; ?>
        <?php if (!empty($benutz['Telefon'])): ?><div class="info-item"><span class="label">Telefon</span><?= htmlspecialchars($benutz['Telefon']) ?></div><?php endif; ?>
    </div>
    <?php if ($bmi): ?>
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid #f0f0f0">
        <span class="label">Aktueller BMI</span>
        <span class="bmi-pill" style="background:<?= bmi_farbe($bmi) ?>;font-size:14px;padding:4px 14px;margin-top:4px;display:inline-block">
            <?= $bmi ?> – <?= bmi_label($bmi) ?>
        </span>
    </div>
    <?php elseif (!$groesse): ?>
    <div class="alert alert-warn" style="margin-top:14px;margin-bottom:0">
        ⚠️ Keine Körpergröße hinterlegt – BMI kann nicht berechnet werden.
        <a href="stammdaten_bearbeiten.php" style="color:var(--blau-dunkel);font-weight:bold"> Jetzt ergänzen →</a>
    </div>
    <?php endif; ?>
</div>

<!-- Messungen: eine einzige Tabelle mit Emoji+Text -->
<div class="card">
    <div class="card-header">
        <h2>📊 Meine Messungen</h2>
        <div style="display:flex;gap:8px">
            <a class="btn btn-sm" href="messung.php">+ Neue Messung</a>
            <a class="btn btn-sm btn-outline" href="verlauf.php">Alle anzeigen</a>
        </div>
    </div>
    <?php if (empty($messungen)): ?>
        <p style="color:var(--text-hell);text-align:center;padding:20px 0">Noch keine Messungen vorhanden.</p>
    <?php else: ?>
    <div class="table-wrapper">
    <table>
        <thead><tr><th>Datum</th><th>Blutdruck</th><th>Puls</th><th>Gewicht</th><th>BMI</th><th>Befinden</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($messungen, 0, 3) as $m):
            $bmi_m = bmi_berechnen($m['Gewicht'], $groesse);
            $rr_w  = rr_status($m['RR']);
        ?>
        <tr>
            <td><?= datum_formatieren($m['Datum'], true) ?></td>
            <td class="<?= $rr_w === 'kritisch' ? 'warn-kritisch' : ($rr_w === 'erhoeht' ? 'warn-erhoeht' : '') ?>">
                <?= htmlspecialchars($m['RR'] ?? '–') ?>
            </td>
            <td><?= $m['P'] ? $m['P'] . ' /min' : '–' ?></td>
            <td><?= $m['Gewicht'] ? $m['Gewicht'] . ' kg' : '–' ?></td>
            <td><?php if ($bmi_m): ?><span class="bmi-pill" style="background:<?= bmi_farbe($bmi_m) ?>"><?= $bmi_m ?> – <?= bmi_label($bmi_m) ?></span><?php else: ?>–<?php endif; ?></td>
            <td><?= befinden_text((int)$m['Befinden']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if (count($messungen) > 3): ?>
    <p style="text-align:center;margin-top:10px;font-size:13px;color:var(--text-hell)">
        Zeigt die letzten 3 von <?= count($messungen) ?> Messungen.
    </p>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Diagnosen -->
<div class="card">
    <div class="card-header"><h2>🩺 Meine Diagnosen</h2></div>
    <?php if (empty($diagnosen)): ?>
        <p style="color:var(--text-hell);text-align:center;padding:20px 0">Noch keine Diagnosen vorhanden.</p>
    <?php else: ?>
        <?php foreach ($diagnosen as $d): ?>
        <div class="diagnose-box"><strong><?= htmlspecialchars($d['ICD']) ?></strong> – <?= htmlspecialchars($d['bezeichnung']) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once "Includes/footer.php"; ?>
