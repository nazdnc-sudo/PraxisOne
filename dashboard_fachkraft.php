<?php
session_start();
require_once "Includes/auth.php";
require_once "Includes/functions.php";
require_once "Config/db.php";

login_pruefen();
rolle_pruefen('Ass');

$user = aktueller_user();

// Überfällige zuerst
$sql = "SELECT p.PID, p.Vorname, p.Name,
               COUNT(m.MID) AS anzahl,
               MAX(m.Datum) AS letzter_eintrag,
               DATEDIFF(NOW(), MAX(m.Datum)) AS tage_seit,
               b.Email, b.Telefon
        FROM patienten p
        LEFT JOIN messungen m ON m.PID = p.PID
        LEFT JOIN benutzer  b ON b.ID  = p.PID
        GROUP BY p.PID
        ORDER BY
            CASE WHEN MAX(m.Datum) IS NULL OR DATEDIFF(NOW(), MAX(m.Datum)) > 7 THEN 0 ELSE 1 END ASC,
            DATEDIFF(NOW(), MAX(m.Datum)) DESC,
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
                <th>Name</th>
                <th>Messungen</th>
                <th>Letzter Eintrag</th>
                <th>Status</th>
                <th>Kontakt</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($patienten as $p):
            $tage = $p['letzter_eintrag'] ? (int)$p['tage_seit'] : null;
            $ueberfaellig = ($p['anzahl'] == 0 || $tage === null || $tage > 7);
        ?>
        <tr style="<?= $ueberfaellig ? 'background:rgba(163,45,45,0.06)' : '' ?>">
            <td style="font-weight:600"><?= htmlspecialchars($p['Vorname'] . ' ' . $p['Name']) ?></td>
            <td><?= $p['anzahl'] ?></td>
            <td style="<?= $ueberfaellig ? 'color:var(--rot);font-weight:600' : '' ?>">
                <?= datum_formatieren($p['letzter_eintrag']) ?>
                <?php if ($tage !== null && $ueberfaellig && $p['anzahl'] > 0): ?>
                    <small style="display:block;font-size:11px;color:var(--rot)"><?= $tage ?> Tage</small>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($p['anzahl'] == 0): ?>
                    <span class="badge badge-keine">Keine Messungen</span>
                <?php elseif ($ueberfaellig): ?>
                    <span class="badge badge-ueberfaellig">⚠️ Überfällig</span>
                <?php else: ?>
                    <span class="badge badge-aktiv">✓ Aktiv</span>
                <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
                <?php if (!empty($p['Email'])): ?>
                    <a class="btn btn-sm btn-light" href="mailto:<?= htmlspecialchars($p['Email']) ?>">✉ E-Mail</a>
                <?php endif; ?>
                <?php if (!empty($p['Telefon'])): ?>
                    <a class="btn btn-sm btn-light" href="tel:<?= htmlspecialchars($p['Telefon']) ?>" style="margin-left:4px">☎ <?= htmlspecialchars($p['Telefon']) ?></a>
                <?php endif; ?>
                <?php if (empty($p['Email']) && empty($p['Telefon'])): ?>
                    <span style="color:var(--text-hell);font-size:12px">Keine Kontaktdaten</span>
                <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
                <a class="btn btn-sm btn-secondary" href="messung.php?pid=<?= $p['PID'] ?>">+ Messung</a>
                <a class="btn btn-sm" href="verlauf.php?id=<?= $p['PID'] ?>" style="margin-left:4px">Verlauf</a>
                <a class="btn btn-sm btn-outline" href="stammdaten_fachkraft.php?pid=<?= $p['PID'] ?>" style="margin-left:4px">Stammdaten</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php require_once "Includes/footer.php"; ?>
