<?php
session_start();
require_once "Includes/auth.php";
require_once "Includes/functions.php";
require_once "Config/db.php";

login_pruefen();
rolle_pruefen('Arzt');

$user = aktueller_user();
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { header("Location: dashboard_arzt.php"); exit(); }
$pid = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM patienten WHERE PID=:pid");
$stmt->execute([':pid'=>$pid]); $patient = $stmt->fetch();
if (!$patient) { header("Location: dashboard_arzt.php"); exit(); }

$stmt_b = $pdo->prepare("SELECT Email, Telefon FROM benutzer WHERE ID=:id");
$stmt_b->execute([':id'=>$pid]); $benutz = $stmt_b->fetch();

$messungen = $pdo->prepare("SELECT * FROM messungen WHERE PID=:pid ORDER BY Datum DESC");
$messungen->execute([':pid'=>$pid]); $messungen = $messungen->fetchAll();

$diagnosen = $pdo->prepare("SELECT d.*, i.Text AS bezeichnung FROM diag_pro_pat d
    JOIN icd_codes i ON i.Code = d.ICD WHERE d.Pat_ID=:pid ORDER BY d.DPID DESC");
$diagnosen->execute([':pid'=>$pid]); $diagnosen = $diagnosen->fetchAll();

$icd_liste = $pdo->query("SELECT * FROM icd_codes ORDER BY Code")->fetchAll();
$groesse   = $patient['Groesse'] ?: null;
$alter     = alter_berechnen($patient['Geburtsdatum'] ?? null);

// Letzte Messung für kompakte Anzeige
$letzte = $messungen[0] ?? null;
$letzter_bmi = $letzte ? bmi_berechnen($letzte['Gewicht'], $groesse) : null;

$erfolg = $fehler = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['icd_code'])) {
    csrf_pruefen();
    $icd = trim($_POST['icd_code']);
    if (empty($icd)) { $fehler = "Bitte einen ICD-10 Code auswählen."; }
    else {
        $s = $pdo->prepare("INSERT INTO diag_pro_pat (Pat_ID,Arzt_ID,ICD) VALUES(:pid,:aid,:icd)");
        $s->execute([':pid'=>$pid,':aid'=>$user['id'],':icd'=>$icd]);
        header("Location: patient_detail.php?id=$pid&ok=1"); exit();
    }
}
if (isset($_GET['ok'])) $erfolg = "Diagnose gespeichert.";

$seiten_titel = "Patientenakte";
$aktive_seite = "dashboard";
require_once "Includes/header.php";
?>

<a class="link-zurueck" href="dashboard_arzt.php">← Zurück zur Übersicht</a>

<!-- ═══ KOPFBEREICH: Name + kompakte Stammdaten + Aktionen (eine Zeile) ═══ -->
<div class="card" style="margin-bottom:16px; padding:16px 20px;">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">

        <!-- Name + kompakte Infos -->
        <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
            <div>
                <div style="font-size:18px; font-weight:700; color:var(--blau-dunkel)">
                    <?= htmlspecialchars($patient['Vorname'] . ' ' . $patient['Name']) ?>
                </div>
                <div style="font-size:12px; color:var(--text-hell); margin-top:2px;">
                    <?= $alter ? $alter . ' Jahre' : '' ?>
                    <?= ($alter && $patient['Geburtsdatum']) ? ' · ' : '' ?>
                    <?= $patient['Geburtsdatum'] ? datum_formatieren($patient['Geburtsdatum']) : '' ?>
                    <?= $groesse ? ' · ' . $groesse . ' cm' : '' ?>
                </div>
            </div>

            <!-- Stammdaten-Zeile: Adresse + Kontakt kompakt -->
            <div style="display:flex; gap:20px; flex-wrap:wrap; font-size:13px; color:var(--text-mittel);">
                <?php if ($patient['Strasse'] ?? ''): ?>
                <span>📍 <?= htmlspecialchars(adresse_formatieren($patient)) ?></span>
                <?php endif; ?>
                <?php if (!empty($benutz['Email'])): ?>
                <a href="mailto:<?= htmlspecialchars($benutz['Email']) ?>" style="color:var(--blau-mittel);text-decoration:none;">
                    ✉ <?= htmlspecialchars($benutz['Email']) ?>
                </a>
                <?php endif; ?>
                <?php if (!empty($benutz['Telefon'])): ?>
                <a href="tel:<?= htmlspecialchars($benutz['Telefon']) ?>" style="color:var(--blau-mittel);text-decoration:none;">
                    ☎ <?= htmlspecialchars($benutz['Telefon']) ?>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Aktions-Buttons -->
        <div style="display:flex; gap:8px; flex-wrap:wrap; flex-shrink:0;">
            <a class="btn btn-sm btn-secondary" href="messung.php?pid=<?= $pid ?>">+ Messung</a>
            <a class="btn btn-sm btn-outline"   href="verlauf.php?id=<?= $pid ?>">📊 Verlauf</a>
            <a class="btn btn-sm btn-outline"   href="stammdaten_fachkraft.php?pid=<?= $pid ?>">✏️ Stammdaten</a>
        </div>
    </div>
    <?php if (!$groesse): ?>
    <div class="alert alert-warn" style="margin-top:10px; margin-bottom:0; padding:8px 12px; font-size:12px;">
        ⚠️ Keine Körpergröße hinterlegt – BMI-Berechnung nicht möglich.
    </div>
    <?php endif; ?>
</div>


<!-- ═══ ZWEISPALTEN-LAYOUT: Diagnosen (links/groß) | Messverlauf-Vorschau (rechts) ═══ -->
<div style="display:grid; grid-template-columns:1fr 340px; gap:16px; align-items:start;">

    <!-- ── DIAGNOSEN (links, oben) ── -->
    <div class="card" style="margin-bottom:0;">
        <div class="card-header" style="margin-bottom:14px;">
            <h2 style="font-size:15px;">🩺 Diagnosen</h2>
        </div>

        <?php if ($erfolg): ?><div class="alert alert-success" style="margin-bottom:12px;"><?= $erfolg ?></div><?php endif; ?>
        <?php if ($fehler): ?><div class="alert alert-error"   style="margin-bottom:12px;"><?= htmlspecialchars($fehler) ?></div><?php endif; ?>

        <!-- Bestehende Diagnosen -->
        <?php if (empty($diagnosen)): ?>
            <p style="color:var(--text-hell); font-size:13px; margin-bottom:16px;">Noch keine Diagnosen eingetragen.</p>
        <?php else: ?>
            <div style="margin-bottom:16px;">
                <?php foreach ($diagnosen as $d): ?>
                <div class="diagnose-box" style="padding:9px 13px; margin-bottom:7px; font-size:13px;">
                    <strong style="color:var(--blau-dunkel);"><?= htmlspecialchars($d['ICD']) ?></strong>
                    <span style="color:var(--text-mittel)"> – <?= htmlspecialchars($d['bezeichnung']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Neue Diagnose -->
        <hr class="trenner" style="margin:12px 0;">
        <div class="section-title">Neue Diagnose hinzufügen</div>
        <form method="POST" action="" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group" style="flex:1; min-width:220px; margin-bottom:0;">
                <select id="icd_code" name="icd_code" required>
                    <option value="">– ICD-10 Code wählen –</option>
                    <?php foreach ($icd_liste as $icd): ?>
                    <option value="<?= htmlspecialchars($icd['Code']) ?>">
                        <?= htmlspecialchars($icd['Code']) ?> – <?= htmlspecialchars($icd['Text']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm" style="margin-bottom:0;">Speichern</button>
        </form>
    </div>

    <!-- ── MESSVERLAUF-VORSCHAU (rechts) ── -->
    <div class="card" style="margin-bottom:0;">
        <div class="card-header" style="margin-bottom:14px;">
            <h2 style="font-size:15px;">📊 Messungen</h2>
            <a class="btn btn-sm btn-outline" href="verlauf.php?id=<?= $pid ?>">Als Verlauf ansehen →</a>
        </div>

        <?php if (empty($messungen)): ?>
            <p style="color:var(--text-hell); font-size:13px; text-align:center; padding:20px 0;">Noch keine Einträge.</p>
        <?php else: ?>

            <!-- Aktuelle Werte (aus letzter Messung) kompakt -->
            <?php if ($letzte):
                $rr_w = rr_status($letzte['RR']); ?>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:14px;">
                <div style="background:var(--grau-bg); border-radius:var(--radius); padding:10px 12px; font-size:13px;">
                    <div style="font-size:11px; color:var(--text-hell); margin-bottom:3px;">Blutdruck</div>
                    <strong class="<?= $rr_w === 'kritisch' ? 'warn-kritisch' : ($rr_w === 'erhoeht' ? 'warn-erhoeht' : '') ?>">
                        <?= htmlspecialchars($letzte['RR'] ?? '–') ?>
                    </strong>
                </div>
                <div style="background:var(--grau-bg); border-radius:var(--radius); padding:10px 12px; font-size:13px;">
                    <div style="font-size:11px; color:var(--text-hell); margin-bottom:3px;">Puls</div>
                    <strong><?= $letzte['P'] ? $letzte['P'] . ' bpm' : '–' ?></strong>
                </div>
                <div style="background:var(--grau-bg); border-radius:var(--radius); padding:10px 12px; font-size:13px;">
                    <div style="font-size:11px; color:var(--text-hell); margin-bottom:3px;">Gewicht / BMI</div>
                    <strong>
                        <?= $letzte['Gewicht'] ? $letzte['Gewicht'] . ' kg' : '–' ?>
                        <?php if ($letzter_bmi): ?>
                            <span class="bmi-pill" style="background:<?= bmi_farbe($letzter_bmi) ?>; font-size:11px;">
                                <?= $letzter_bmi ?>
                            </span>
                        <?php endif; ?>
                    </strong>
                </div>
                <div style="background:var(--grau-bg); border-radius:var(--radius); padding:10px 12px; font-size:13px;">
                    <div style="font-size:11px; color:var(--text-hell); margin-bottom:3px;">Befinden</div>
                    <strong><?= befinden_text((int)$letzte['Befinden']) ?></strong>
                </div>
            </div>
            <div style="font-size:11px; color:var(--text-hell); text-align:right; margin-bottom:12px;">
                Letzte Messung: <?= datum_formatieren($letzte['Datum'], true) ?>
            </div>
            <?php endif; ?>

            <!-- Mini-Verlauf: letzte 4 Einträge -->
            <hr class="trenner" style="margin:10px 0 12px 0;">
            <div class="section-title">Letzte Einträge</div>
            <table style="font-size:12px; width:100%;">
                <thead>
                    <tr>
                        <th style="background:var(--blau-dunkel); color:white; padding:6px 8px; font-size:11px; font-weight:600; border-radius:4px 0 0 0;">Datum</th>
                        <th style="background:var(--blau-dunkel); color:white; padding:6px 8px; font-size:11px; font-weight:600;">RR</th>
                        <th style="background:var(--blau-dunkel); color:white; padding:6px 8px; font-size:11px; font-weight:600;">Kg</th>
                        <th style="background:var(--blau-dunkel); color:white; padding:6px 8px; font-size:11px; font-weight:600; border-radius:0 4px 0 0;">Bef.</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($messungen, 0, 4) as $m):
                    $rr_w = rr_status($m['RR']); ?>
                <tr>
                    <td style="padding:6px 8px; border-bottom:1px solid #f0f0f0; color:var(--text-mittel);">
                        <?= datum_formatieren($m['Datum']) ?>
                    </td>
                    <td style="padding:6px 8px; border-bottom:1px solid #f0f0f0;"
                        class="<?= $rr_w === 'kritisch' ? 'warn-kritisch' : ($rr_w === 'erhoeht' ? 'warn-erhoeht' : '') ?>">
                        <?= htmlspecialchars($m['RR'] ?? '–') ?>
                    </td>
                    <td style="padding:6px 8px; border-bottom:1px solid #f0f0f0;">
                        <?= $m['Gewicht'] ? $m['Gewicht'] : '–' ?>
                    </td>
                    <td style="padding:6px 8px; border-bottom:1px solid #f0f0f0;">
                        <?= befinden_text((int)$m['Befinden']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (count($messungen) > 4): ?>
            <div style="text-align:center; margin-top:12px;">
                <a href="verlauf.php?id=<?= $pid ?>" style="font-size:12px; color:var(--blau-mittel);">
                    + <?= count($messungen) - 4 ?> weitere Einträge ansehen →
                </a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div><!-- /grid -->

<?php require_once "Includes/footer.php"; ?>
