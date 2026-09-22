<?php
session_start();
require_once "Includes/auth.php";
require_once "Includes/functions.php";
require_once "Config/db.php";

login_pruefen();
rolle_pruefen('Arzt');

$user = aktueller_user();

// Sortierung: Überfällige (> 7 Tage) zuerst, dann alphabetisch
$sql = "SELECT p.PID, p.Vorname, p.Name, p.Geburtsdatum, p.Groesse,
               m.Datum, m.RR, m.P, m.Gewicht, m.Befinden,
               DATEDIFF(NOW(), m.Datum) AS tage_seit_eintrag
        FROM patienten p
        LEFT JOIN messungen m ON m.MID = (
            SELECT MID FROM messungen WHERE PID = p.PID ORDER BY Datum DESC LIMIT 1
        )
        ORDER BY
            CASE WHEN m.Datum IS NULL OR DATEDIFF(NOW(), m.Datum) > 7 THEN 0 ELSE 1 END ASC,
            DATEDIFF(NOW(), m.Datum) DESC,
            p.Name ASC";
$patienten = $pdo->query($sql)->fetchAll();

$seiten_titel = "Patientenübersicht";
$aktive_seite = "dashboard";
require_once "Includes/header.php";
?>

<div class="card">
    <div class="card-header">
        <h2>Patientenübersicht</h2>
        <span style="font-size:13px; color:var(--text-hell)"><?= count($patienten) ?> Patienten</span>
    </div>

    <div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Name</th><th>Alter</th><th>Letzter Eintrag</th>
                <th>Blutdruck</th><th>Puls</th><th>Gewicht</th>
                <th>BMI</th><th>Befinden</th><th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($patienten as $p):
            $alter    = alter_berechnen($p['Geburtsdatum']);
            $bmi      = bmi_berechnen($p['Gewicht'], $p['Groesse']);
            $rr_warn  = rr_status($p['RR']);
            $ueberfaellig = ($p['Datum'] === null || (int)$p['tage_seit_eintrag'] > 7);
        ?>
        <tr style="<?= $ueberfaellig ? 'background:rgba(163,45,45,0.06)' : '' ?>">
            <td>
                <a href="patient_detail.php?id=<?= $p['PID'] ?>" style="color:var(--blau-dunkel);font-weight:600;text-decoration:none;">
                    <?= htmlspecialchars($p['Vorname'] . ' ' . $p['Name']) ?>
                </a>
                <?php if ($ueberfaellig): ?>
                    <span class="badge badge-ueberfaellig" style="margin-left:6px;font-size:11px">⚠️ Überfällig</span>
                <?php endif; ?>
            </td>
            <td><?= $alter ? $alter . ' J.' : '–' ?></td>
            <td style="<?= $ueberfaellig ? 'color:var(--rot);font-weight:600' : '' ?>">
                <?= datum_formatieren($p['Datum']) ?>
                <?php if ($p['Datum'] && $ueberfaellig): ?>
                    <small style="display:block;font-size:11px;color:var(--rot)"><?= (int)$p['tage_seit_eintrag'] ?> Tage</small>
                <?php endif; ?>
            </td>
            <td class="<?= $rr_warn === 'kritisch' ? 'warn-kritisch' : ($rr_warn === 'erhoeht' ? 'warn-erhoeht' : '') ?>">
                <?= htmlspecialchars($p['RR'] ?? '–') ?>
            </td>
            <td><?= $p['P'] ? $p['P'] . ' bpm' : '–' ?></td>
            <td><?= $p['Gewicht'] ? $p['Gewicht'] . ' kg' : '–' ?></td>
            <td>
                <span class="bmi-pill" style="background:<?= bmi_farbe($bmi) ?>">
                    <?= $bmi ? $bmi . ' – ' . bmi_label($bmi) : ($p['Gewicht'] && !$p['Groesse'] ? '? Größe fehlt' : '–') ?>
                </span>
            </td>
            <td><?= $p['Befinden'] ? befinden_text((int)$p['Befinden']) : '–' ?></td>
            <td style="white-space:nowrap">
                <a class="btn btn-sm" href="patient_detail.php?id=<?= $p['PID'] ?>">Akte</a>
                <a class="btn btn-sm btn-secondary" href="verlauf.php?id=<?= $p['PID'] ?>" style="margin-left:4px">Verlauf</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require_once "Includes/footer.php"; ?>
