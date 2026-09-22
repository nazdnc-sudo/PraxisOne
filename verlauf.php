<?php
session_start();
require_once "Includes/auth.php";
require_once "Includes/functions.php";
require_once "Config/db.php";

login_pruefen();
$user  = aktueller_user();
$rolle = $user['rolle'];

if ($rolle === 'Pat') {
    $pid = $user['id'];
} elseif (in_array($rolle, ['Arzt','Ass']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $pid = (int)$_GET['id'];
} else { header("Location: login.php"); exit(); }

$stmt = $pdo->prepare("SELECT * FROM patienten WHERE PID = :pid");
$stmt->execute([':pid' => $pid]);
$patient = $stmt->fetch();
if (!$patient) { header("Location: login.php"); exit(); }

$stmt2 = $pdo->prepare("SELECT * FROM messungen WHERE PID = :pid ORDER BY Datum DESC");
$stmt2->execute([':pid' => $pid]);
$messungen = $stmt2->fetchAll();

$zurueck = match($rolle) { 'Arzt' => "patient_detail.php?id=$pid", 'Ass' => "dashboard_fachkraft.php", default => "dashboard_patient.php" };
$groesse  = $patient['Groesse'] ?: null;

$seiten_titel = "Verlauf";
$aktive_seite = "verlauf";
require_once "Includes/header.php";
?>

<a class="link-zurueck" href="<?= $zurueck ?>">← Zurück</a>

<div class="card">
    <div class="card-header">
        <h2>Verlauf: <?= htmlspecialchars($patient['Vorname'] . ' ' . $patient['Name']) ?></h2>
        <?php if ($groesse): ?>
            <span style="font-size:13px; color:var(--text-hell)">Größe: <?= $groesse ?> cm</span>
        <?php else: ?>
            <span style="font-size:13px; color:var(--rot)">⚠️ Keine Körpergröße – BMI nicht berechenbar</span>
        <?php endif; ?>
    </div>

    <?php if (empty($messungen)): ?>
        <p style="text-align:center; color:var(--text-hell); padding:20px 0">Noch keine Messungen vorhanden.</p>
    <?php else: ?>
    <div class="table-wrapper">
    <table>
        <thead>
            <tr><th>Datum</th><th>Blutdruck</th><th>Puls</th><th>Gewicht</th><th>BMI</th><th>Befinden</th></tr>
        </thead>
        <tbody>
        <?php foreach ($messungen as $m):
            $bmi = bmi_berechnen($m['Gewicht'], $groesse);
            $rr_w = rr_status($m['RR']);
        ?>
        <tr>
            <td><?= datum_formatieren($m['Datum'], true) ?></td>
            <td class="<?= $rr_w === 'kritisch' ? 'warn-kritisch' : ($rr_w === 'erhoeht' ? 'warn-erhoeht' : '') ?>">
                <?= htmlspecialchars($m['RR'] ?? '–') ?>
            </td>
            <td><?= $m['P'] ? $m['P'] . ' bpm' : '–' ?></td>
            <td><?= $m['Gewicht'] ? $m['Gewicht'] . ' kg' : '–' ?></td>
            <td><span class="bmi-pill" style="background:<?= bmi_farbe($bmi) ?>">
                <?= $bmi ? $bmi . ' – ' . bmi_label($bmi) : '–' ?>
            </span></td>
            <td><?= befinden_text((int)$m['Befinden']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once "Includes/footer.php"; ?>
