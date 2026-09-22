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
} else {
    $pid = isset($_GET['pid']) && is_numeric($_GET['pid']) ? (int)$_GET['pid'] : null;
    if (!$pid) {
        $alle = $pdo->query("SELECT PID, Name, Vorname FROM patienten ORDER BY Name")->fetchAll();
        $seiten_titel = "Patient wählen"; $aktive_seite = "messung";
        require_once "Includes/header.php";
        echo '<div class="card"><div class="card-header"><h2>Patient auswählen</h2></div>';
        echo '<div class="table-wrapper"><table><thead><tr><th>Name</th><th>Aktion</th></tr></thead><tbody>';
        foreach ($alle as $p) {
            echo '<tr><td>' . htmlspecialchars($p['Vorname'] . ' ' . $p['Name']) . '</td>';
            echo '<td><a class="btn btn-sm" href="messung.php?pid=' . $p['PID'] . '">Messung eintragen</a></td></tr>';
        }
        echo '</tbody></table></div></div>';
        require_once "Includes/footer.php";
        exit();
    }
}

$stmt = $pdo->prepare("SELECT * FROM patienten WHERE PID=:pid");
$stmt->execute([':pid'=>$pid]); $patient = $stmt->fetch();
if (!$patient) { header("Location: login.php"); exit(); }

$groesse = $patient['Groesse'] ?: null;
$fehler = []; $erfolg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $datum    = trim($_POST['datum']    ?? '');
    $gewicht  = trim($_POST['gewicht']  ?? '');
    $rr_sys   = trim($_POST['rr_sys']   ?? '');
    $rr_dia   = trim($_POST['rr_dia']   ?? '');
    $puls     = trim($_POST['puls']     ?? '');
    $befinden = trim($_POST['befinden'] ?? '');

    if (empty($datum)) { $fehler[] = "Bitte ein Datum eingeben."; }
    else { $d = DateTime::createFromFormat('Y-m-d\TH:i', $datum); if (!$d || $d > new DateTime()) $fehler[] = "Datum darf nicht in der Zukunft liegen."; }
    if ($gewicht !== '' && (!is_numeric($gewicht) || $gewicht <= 0 || $gewicht > 300)) $fehler[] = "Gewicht ungültig (1–300 kg).";
    if ($rr_sys !== '' && (!ctype_digit($rr_sys) || (int)$rr_sys < 50 || (int)$rr_sys > 300)) $fehler[] = "Systolischer Blutdruck ungültig (50–300).";
    if ($rr_dia !== '' && (!ctype_digit($rr_dia) || (int)$rr_dia < 30 || (int)$rr_dia > 200)) $fehler[] = "Diastolischer Blutdruck ungültig (30–200).";
    if ($puls !== '' && (!ctype_digit($puls) || (int)$puls < 30 || (int)$puls > 250)) $fehler[] = "Puls ungültig (30–250).";
    if (!in_array($befinden, ['1','2','3','4','5'])) $fehler[] = "Bitte Befinden auswählen.";

    if (empty($fehler)) {
        $stmt = $pdo->prepare("INSERT INTO messungen (PID,Datum,Gewicht,RR,P,Befinden) VALUES(:pid,:datum,:gew,:rr,:puls,:bef)");
        $stmt->execute([':pid'=>$pid,':datum'=>(new DateTime($datum))->format('Y-m-d H:i:s'),':gew'=>$gewicht?:null,':rr'=>($rr_sys!==''&&$rr_dia!==''?"$rr_sys/$rr_dia":null),':puls'=>$puls?:null,':bef'=>$befinden]);
        $erfolg = "Messung erfolgreich gespeichert!";
        $_POST = [];
    }
}

$messungen = $pdo->prepare("SELECT * FROM messungen WHERE PID=:pid ORDER BY Datum DESC");
$messungen->execute([':pid'=>$pid]); $messungen = $messungen->fetchAll();

$seiten_titel = "Messung eintragen";
$aktive_seite = "messung";
require_once "Includes/header.php";
?>

<a class="link-zurueck" href="<?= $rolle === 'Pat' ? 'dashboard_patient.php' : 'patient_detail.php?id=' . $pid ?>">← Zurück</a>

<div class="card">
    <div class="card-header">
        <h2>Neue Messung</h2>
        <span style="font-size:13px;color:var(--text-hell)"><?= htmlspecialchars($patient['Vorname'] . ' ' . $patient['Name']) ?></span>
    </div>

    <?php if (!empty($fehler)): ?>
        <div class="alert alert-error"><strong>Bitte korrigieren:</strong><ul>
            <?php foreach ($fehler as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if ($erfolg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($erfolg) ?></div><?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label for="datum">Datum und Uhrzeit <span class="req">*</span></label>
            <input type="datetime-local" id="datum" name="datum"
                   value="<?= htmlspecialchars($_POST['datum'] ?? date('Y-m-d\TH:i')) ?>"
                   max="<?= date('Y-m-d\TH:i') ?>" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="gewicht">Gewicht</label>
                <input type="number" id="gewicht" name="gewicht" step="0.1" min="1" max="300"
                       value="<?= htmlspecialchars($_POST['gewicht'] ?? '') ?>" placeholder="z.B. 75.5">
                <div class="field-hint">kg</div>
            </div>
            <div class="form-group">
                <label for="puls">Puls</label>
                <input type="number" id="puls" name="puls" min="30" max="250"
                       value="<?= htmlspecialchars($_POST['puls'] ?? '') ?>" placeholder="z.B. 72">
                <div class="field-hint">Schläge/min</div>
            </div>
        </div>
        <div class="form-group">
            <label>Blutdruck (RR)</label>
            <div class="rr-gruppe">
                <input type="number" name="rr_sys" min="50" max="300" value="<?= htmlspecialchars($_POST['rr_sys'] ?? '') ?>" placeholder="Systolisch (120)">
                <div class="rr-trenner">/</div>
                <input type="number" name="rr_dia" min="30" max="200" value="<?= htmlspecialchars($_POST['rr_dia'] ?? '') ?>" placeholder="Diastolisch (80)">
            </div>
            <div class="field-hint">mmHg – leer lassen wenn nicht gemessen</div>
        </div>
        <div class="form-group">
            <label for="befinden">Befinden <span class="req">*</span></label>
            <select id="befinden" name="befinden" required>
                <option value="">– Bitte wählen –</option>
                <?php foreach ([1=>'😞 Sehr schlecht',2=>'😕 Schlecht',3=>'😐 Mittel',4=>'🙂 Gut',5=>'😄 Sehr gut'] as $v => $t): ?>
                <option value="<?= $v ?>" <?= ($_POST['befinden'] ?? '') == $v ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn">Messung speichern</button>
    </form>
</div>

<!-- Bisherige Messungen – EINE Tabelle, Befinden als Emoji+Text, keine rohen Zahlen -->
<div class="card">
    <div class="card-header"><h2>Bisherige Messungen</h2></div>
    <?php if (empty($messungen)): ?>
        <p style="text-align:center;color:var(--text-hell);padding:20px 0">Noch keine Messungen vorhanden.</p>
    <?php else: ?>
    <div class="table-wrapper">
    <table>
        <thead>
            <tr><th>Datum</th><th>Blutdruck</th><th>Puls</th><th>Gewicht</th><th>BMI</th><th>Befinden</th></tr>
        </thead>
        <tbody>
        <?php foreach ($messungen as $m):
            $bmi  = bmi_berechnen($m['Gewicht'], $groesse);
            $rr_w = rr_status($m['RR']);
        ?>
        <tr>
            <td><?= datum_formatieren($m['Datum'], true) ?></td>
            <td class="<?= $rr_w === 'kritisch' ? 'warn-kritisch' : ($rr_w === 'erhoeht' ? 'warn-erhoeht' : '') ?>">
                <?= htmlspecialchars($m['RR'] ?? '–') ?>
            </td>
            <td><?= $m['P'] ? $m['P'] . ' /min' : '–' ?></td>
            <td><?= $m['Gewicht'] ? $m['Gewicht'] . ' kg' : '–' ?></td>
            <td>
                <?php if ($bmi): ?>
                    <span class="bmi-pill" style="background:<?= bmi_farbe($bmi) ?>"><?= $bmi ?> – <?= bmi_label($bmi) ?></span>
                <?php elseif (!$groesse): ?>
                    <span style="color:var(--text-hell);font-size:12px">Größe fehlt</span>
                <?php else: ?>–<?php endif; ?>
            </td>
            <td><?= befinden_text((int)$m['Befinden']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once "Includes/footer.php"; ?>
