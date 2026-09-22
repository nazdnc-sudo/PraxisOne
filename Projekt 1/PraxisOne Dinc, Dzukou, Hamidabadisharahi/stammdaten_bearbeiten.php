<?php
// Stammdaten bearbeiten – nur für Patienten (eigene Daten)
session_start();
require_once "Includes/auth.php";
require_once "Includes/functions.php";
require_once "Config/db.php";

login_pruefen();
rolle_pruefen('Pat');

$user = aktueller_user();
$pid  = $user['id'];

$patient = $pdo->prepare("SELECT * FROM patienten WHERE PID = :id");
$patient->execute([':id' => $pid]);
$patient = $patient->fetch();

$stmt_b = $pdo->prepare("SELECT Email, Telefon FROM benutzer WHERE ID = :id");
$stmt_b->execute([':id' => $pid]);
$benutz = $stmt_b->fetch();

$fehler = []; $erfolg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();

    $strasse    = trim($_POST['strasse']    ?? '');
    $hausnummer = trim($_POST['hausnummer'] ?? '');
    $plz        = trim($_POST['plz']        ?? '');
    $ort        = trim($_POST['ort']        ?? '');
    $groesse    = trim($_POST['groesse']    ?? '');
    $email      = trim($_POST['email']      ?? '');
    $telefon    = trim($_POST['telefon']    ?? '');

    if (empty($strasse))   $fehler[] = "Straße darf nicht leer sein.";
    if (empty($plz))       $fehler[] = "PLZ darf nicht leer sein.";
    if (empty($ort))       $fehler[] = "Ort darf nicht leer sein.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $fehler[] = "Ungültige E-Mail-Adresse.";
    if (empty($telefon))   $fehler[] = "Telefonnummer darf nicht leer sein.";
    if ($groesse !== '' && (!is_numeric($groesse) || $groesse < 100 || $groesse > 250))
        $fehler[] = "Körpergröße muss zwischen 100 und 250 cm liegen.";

    if (empty($fehler)) {
        $pdo->prepare("UPDATE patienten SET Strasse=:s, Hausnummer=:hn, PLZ=:plz, Ort=:o, Groesse=:g WHERE PID=:pid")
            ->execute([':s'=>$strasse,':hn'=>$hausnummer,':plz'=>$plz,':o'=>$ort,':g'=>$groesse?:null,':pid'=>$pid]);
        $pdo->prepare("UPDATE benutzer SET Email=:e, Telefon=:t WHERE ID=:id")
            ->execute([':e'=>$email,':t'=>$telefon,':id'=>$pid]);
        $erfolg = "Stammdaten erfolgreich aktualisiert.";
        $patient = $pdo->prepare("SELECT * FROM patienten WHERE PID=:id");
        $patient->execute([':id'=>$pid]); $patient = $patient->fetch();
        $stmt_b = $pdo->prepare("SELECT Email,Telefon FROM benutzer WHERE ID=:id");
        $stmt_b->execute([':id'=>$pid]); $benutz = $stmt_b->fetch();
    }
}

$seiten_titel = "Mein Profil";
$aktive_seite = "stammdaten";
require_once "Includes/header.php";
?>

<div class="container-md" style="margin:0 auto">
<a class="link-zurueck" href="dashboard_patient.php">← Zurück zum Dashboard</a>

<div class="card">
    <div class="card-header"><h2>✏️ Stammdaten bearbeiten</h2></div>

    <?php if (!empty($fehler)): ?>
        <div class="alert alert-error"><strong>Bitte korrigieren:</strong><ul>
            <?php foreach ($fehler as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
    <?php if ($erfolg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($erfolg) ?></div><?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <p class="section-title">Nicht änderbar</p>
        <div class="form-row">
            <div class="form-group">
                <label>Name</label>
                <input type="text" value="<?= htmlspecialchars($patient['Vorname'] . ' ' . $patient['Name']) ?>" readonly>
                <div class="field-hint">Änderung nur durch Praxispersonal möglich.</div>
            </div>
            <div class="form-group">
                <label>Geburtsdatum</label>
                <input type="text" value="<?= datum_formatieren($patient['Geburtsdatum'] ?? null) ?>" readonly>
            </div>
        </div>

        <hr class="trenner">
        <p class="section-title">Adresse</p>

        <div class="form-row">
            <div class="form-group" style="flex:3">
                <label for="strasse">Straße <span class="req">*</span></label>
                <input type="text" id="strasse" name="strasse" maxlength="100"
                       value="<?= htmlspecialchars($_POST['strasse'] ?? $patient['Strasse'] ?? '') ?>"
                       placeholder="z.B. Hauptstraße" required>
            </div>
            <div class="form-group" style="flex:1">
                <label for="hausnummer">Nr.</label>
                <input type="text" id="hausnummer" name="hausnummer" maxlength="10"
                       value="<?= htmlspecialchars($_POST['hausnummer'] ?? $patient['Hausnummer'] ?? '') ?>"
                       placeholder="z.B. 12a">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:1">
                <label for="plz">PLZ <span class="req">*</span></label>
                <input type="text" id="plz" name="plz" maxlength="10"
                       value="<?= htmlspecialchars($_POST['plz'] ?? $patient['PLZ'] ?? '') ?>"
                       placeholder="89073" required>
            </div>
            <div class="form-group" style="flex:3">
                <label for="ort">Ort <span class="req">*</span></label>
                <input type="text" id="ort" name="ort" maxlength="100"
                       value="<?= htmlspecialchars($_POST['ort'] ?? $patient['Ort'] ?? '') ?>"
                       placeholder="Ulm" required>
            </div>
        </div>

        <hr class="trenner">
        <p class="section-title">Kontakt & Gesundheit</p>

        <div class="form-row">
            <div class="form-group">
                <label for="email">E-Mail <span class="req">*</span></label>
                <input type="email" id="email" name="email" maxlength="100"
                       value="<?= htmlspecialchars($_POST['email'] ?? $benutz['Email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="telefon">Telefon <span class="req">*</span></label>
                <input type="tel" id="telefon" name="telefon" maxlength="20"
                       value="<?= htmlspecialchars($_POST['telefon'] ?? $benutz['Telefon'] ?? '') ?>"
                       placeholder="+49 731 123456" required>
            </div>
        </div>

        <div class="form-group" style="max-width:200px">
            <label for="groesse">Körpergröße (cm)</label>
            <input type="number" id="groesse" name="groesse" min="100" max="250" step="0.1"
                   value="<?= htmlspecialchars($_POST['groesse'] ?? $patient['Groesse'] ?? '') ?>"
                   placeholder="z.B. 175">
            <div class="field-hint">Für BMI-Berechnung</div>
        </div>

        <button type="submit" class="btn">Änderungen speichern</button>
    </form>
</div>
</div>

<?php require_once "Includes/footer.php"; ?>
