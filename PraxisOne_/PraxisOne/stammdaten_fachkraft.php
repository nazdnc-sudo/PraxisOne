<?php
// Stammdaten bearbeiten – für Fachkraft und Arzt
session_start();
require_once "Includes/auth.php";
require_once "Includes/functions.php";
require_once "Config/db.php";

login_pruefen();
if (!in_array($_SESSION['rolle'], ['Ass','Arzt'])) { header("Location: login.php"); exit(); }

$user = aktueller_user();

if (!isset($_GET['pid']) || !is_numeric($_GET['pid'])) {
    header("Location: " . ($user['rolle'] === 'Arzt' ? 'dashboard_arzt.php' : 'dashboard_fachkraft.php'));
    exit();
}
$pid = (int)$_GET['pid'];

$patient = $pdo->prepare("SELECT * FROM patienten WHERE PID=:id");
$patient->execute([':id'=>$pid]); $patient = $patient->fetch();
if (!$patient) { header("Location: " . ($user['rolle'] === 'Arzt' ? 'dashboard_arzt.php' : 'dashboard_fachkraft.php')); exit(); }

$stmt_b = $pdo->prepare("SELECT Email, Telefon FROM benutzer WHERE ID=:id");
$stmt_b->execute([':id'=>$pid]); $benutz = $stmt_b->fetch();

$fehler = []; $erfolg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();

    $vorname    = trim($_POST['vorname']    ?? '');
    $name       = trim($_POST['name']       ?? '');
    $geburt     = trim($_POST['geburt']     ?? '');
    $strasse    = trim($_POST['strasse']    ?? '');
    $hausnummer = trim($_POST['hausnummer'] ?? '');
    $plz        = trim($_POST['plz']        ?? '');
    $ort        = trim($_POST['ort']        ?? '');
    $groesse    = trim($_POST['groesse']    ?? '');
    $email      = trim($_POST['email']      ?? '');
    $telefon    = trim($_POST['telefon']    ?? '');

    if (empty($vorname)) $fehler[] = "Vorname darf nicht leer sein.";
    if (empty($name))    $fehler[] = "Nachname darf nicht leer sein.";
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $fehler[] = "Ungültige E-Mail-Adresse.";
    if ($groesse !== '' && (!is_numeric($groesse) || $groesse < 100 || $groesse > 250))
        $fehler[] = "Körpergröße muss zwischen 100 und 250 cm liegen.";

    if (empty($fehler)) {
        $geb_val = null;
        if ($geburt) { $d = DateTime::createFromFormat('Y-m-d', $geburt); if ($d) $geb_val = $geburt; }
        $pdo->prepare("UPDATE patienten SET Vorname=:v, Name=:n, Geburtsdatum=:g, Strasse=:s, Hausnummer=:hn, PLZ=:plz, Ort=:o, Groesse=:gr WHERE PID=:pid")
            ->execute([':v'=>$vorname,':n'=>$name,':g'=>$geb_val,':s'=>$strasse,':hn'=>$hausnummer,':plz'=>$plz,':o'=>$ort,':gr'=>$groesse?:null,':pid'=>$pid]);
        $pdo->prepare("UPDATE benutzer SET Email=:e, Telefon=:t WHERE ID=:id")
            ->execute([':e'=>$email?:null,':t'=>$telefon?:null,':id'=>$pid]);
        $erfolg = "Stammdaten erfolgreich gespeichert.";
        $patient = $pdo->prepare("SELECT * FROM patienten WHERE PID=:id");
        $patient->execute([':id'=>$pid]); $patient = $patient->fetch();
        $stmt_b = $pdo->prepare("SELECT Email,Telefon FROM benutzer WHERE ID=:id");
        $stmt_b->execute([':id'=>$pid]); $benutz = $stmt_b->fetch();
    }
}

$zurueck = $user['rolle'] === 'Arzt' ? "patient_detail.php?id=$pid" : "dashboard_fachkraft.php";
$seiten_titel = "Stammdaten bearbeiten";
$aktive_seite = "dashboard";
require_once "Includes/header.php";
?>

<div class="container-md" style="margin:0 auto">
<a class="link-zurueck" href="<?= $zurueck ?>">← Zurück</a>

<div class="card">
    <div class="card-header">
        <h2>✏️ Stammdaten: <?= htmlspecialchars($patient['Vorname'] . ' ' . $patient['Name']) ?></h2>
        <span class="badge <?= $user['rolle'] === 'Arzt' ? 'badge-aktiv' : 'badge-keine' ?>">
            <?= $user['rolle'] === 'Arzt' ? 'Arzt' : 'Fachkraft' ?>
        </span>
    </div>

    <?php if (!empty($fehler)): ?>
        <div class="alert alert-error"><strong>Bitte korrigieren:</strong><ul>
            <?php foreach ($fehler as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
    <?php if ($erfolg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($erfolg) ?></div><?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <p class="section-title">Person</p>
        <div class="form-row">
            <div class="form-group">
                <label for="vorname">Vorname <span class="req">*</span></label>
                <input type="text" id="vorname" name="vorname"
                       value="<?= htmlspecialchars($_POST['vorname'] ?? $patient['Vorname'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="name">Nachname <span class="req">*</span></label>
                <input type="text" id="name" name="name"
                       value="<?= htmlspecialchars($_POST['name'] ?? $patient['Name'] ?? '') ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="geburt">Geburtsdatum</label>
                <input type="date" id="geburt" name="geburt"
                       value="<?= htmlspecialchars($_POST['geburt'] ?? $patient['Geburtsdatum'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="groesse">Körpergröße (cm)</label>
                <input type="number" id="groesse" name="groesse" min="100" max="250" step="0.1"
                       value="<?= htmlspecialchars($_POST['groesse'] ?? $patient['Groesse'] ?? '') ?>"
                       placeholder="z.B. 175">
            </div>
        </div>

        <hr class="trenner">
        <p class="section-title">Adresse</p>

        <div class="form-row">
            <div class="form-group" style="flex:3">
                <label for="strasse">Straße</label>
                <input type="text" id="strasse" name="strasse" maxlength="100"
                       value="<?= htmlspecialchars($_POST['strasse'] ?? $patient['Strasse'] ?? '') ?>"
                       placeholder="z.B. Hauptstraße">
            </div>
            <div class="form-group" style="flex:1">
                <label for="hausnummer">Nr.</label>
                <input type="text" id="hausnummer" name="hausnummer" maxlength="10"
                       value="<?= htmlspecialchars($_POST['hausnummer'] ?? $patient['Hausnummer'] ?? '') ?>"
                       placeholder="12a">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:1">
                <label for="plz">PLZ</label>
                <input type="text" id="plz" name="plz" maxlength="10"
                       value="<?= htmlspecialchars($_POST['plz'] ?? $patient['PLZ'] ?? '') ?>"
                       placeholder="89073">
            </div>
            <div class="form-group" style="flex:3">
                <label for="ort">Ort</label>
                <input type="text" id="ort" name="ort" maxlength="100"
                       value="<?= htmlspecialchars($_POST['ort'] ?? $patient['Ort'] ?? '') ?>"
                       placeholder="Ulm">
            </div>
        </div>

        <hr class="trenner">
        <p class="section-title">Kontakt</p>
        <div class="form-row">
            <div class="form-group">
                <label for="email">E-Mail</label>
                <input type="email" id="email" name="email" maxlength="100"
                       value="<?= htmlspecialchars($_POST['email'] ?? $benutz['Email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="telefon">Telefon</label>
                <input type="tel" id="telefon" name="telefon" maxlength="20"
                       value="<?= htmlspecialchars($_POST['telefon'] ?? $benutz['Telefon'] ?? '') ?>"
                       placeholder="+49 731 123456">
            </div>
        </div>

        <button type="submit" class="btn">Stammdaten speichern</button>
    </form>
</div>
</div>

<?php require_once "Includes/footer.php"; ?>
