<?php
// XML-Import – NUR für Fachkraft (Ass)
session_start();
require_once "Includes/auth.php";
require_once "Includes/functions.php";
require_once "Config/db.php";

login_pruefen();
rolle_pruefen('Ass');

$user = aktueller_user();
$fehler = []; $erfolg = ""; $importiert = 0; $uebersprungen = 0;
$vorschau = [];

// XML-Beispieldatei herunterladen
if (isset($_GET['download'])) {
    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="patienten_vorlage.xml"');
    echo xml_beispiel_generieren();
    exit();
}

// ============================================================
// Hilfsfunktion: Feldwert flexibel aus XML-Knoten lesen
// Unterstützt beide Formate (Moodle-Format + eigenes Format)
// ============================================================
function xml_feld(SimpleXMLElement $node, array $namen): string {
    foreach ($namen as $n) {
        // Groß/Kleinschreibung ignorieren durch alle Kinder durchsuchen
        foreach ($node->children() as $kind) {
            if (strtolower($kind->getName()) === strtolower($n)) {
                return trim((string)$kind);
            }
        }
    }
    return '';
}

// ============================================================
// XML parsen – robust gegen Groß/Kleinschreibung bei Tags
// ============================================================
function xml_robust_laden(string $pfad): ?SimpleXMLElement {
    $inhalt = file_get_contents($pfad);
    if ($inhalt === false) return null;

    // Schließende Tags groß→klein normalisieren: </Patient> → </patient>
    $inhalt = preg_replace_callback('/<\/([A-Za-z][A-Za-z0-9_]*)>/', function($m) {
        return '</' . strtolower($m[1]) . '>';
    }, $inhalt);
    // Öffnende Tags ebenfalls normalisieren: <Patient> → <patient>
    $inhalt = preg_replace_callback('/<([A-Za-z][A-Za-z0-9_]*)(\s|>|\/)/', function($m) {
        return '<' . strtolower($m[1]) . $m[2];
    }, $inhalt);

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($inhalt);
    return $xml ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml_datei'])) {
    csrf_pruefen();
    $file = $_FILES['xml_datei'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $fehler[] = "Upload-Fehler (Code " . $file['error'] . ").";
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $fehler[] = "Datei zu groß (max. 2 MB).";
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'xml') {
        $fehler[] = "Nur XML-Dateien erlaubt (.xml).";
    } else {
        $xml = xml_robust_laden($file['tmp_name']);

        if ($xml === null) {
            $fehler[] = "Ungültige XML-Datei – Bitte Datei prüfen.";
        } else {
            // Wurzelelement akzeptieren: <patienten> ODER <liste>
            $wurzel = strtolower($xml->getName());
            if (!in_array($wurzel, ['patienten', 'liste'])) {
                $fehler[] = "Unbekanntes Wurzelelement &lt;$wurzel&gt; – erwartet: &lt;patienten&gt; oder &lt;liste&gt;.";
            } else {
                foreach ($xml->patient as $p) {

                    // Feldnamen flexibel: beide Formate unterstützt
                    $vorname    = xml_feld($p, ['vorname']);
                    $nachname   = xml_feld($p, ['nachname', 'name']);       // <nachname> oder <name>
                    $geb        = xml_feld($p, ['geburtsdatum']);
                    $strasse    = xml_feld($p, ['strasse']);
                    $hausnummer = xml_feld($p, ['hausnummer']);
                    $plz        = xml_feld($p, ['plz']);
                    $ort        = xml_feld($p, ['ort']);
                    $groesse    = xml_feld($p, ['groesse']);
                    $email      = xml_feld($p, ['email', 'emailadresse']);  // <email> oder <emailadresse>
                    $telefon    = xml_feld($p, ['telefon', 'telefonnummer']); // <telefon> oder <telefonnummer>

                    // Pflichtfeld: Nachname muss vorhanden sein
                    if (empty($nachname)) {
                        $fehler[] = "Übersprungen: Nachname/Name fehlt.";
                        $uebersprungen++;
                        continue;
                    }

                    // Vorname fehlt → als Hinweis, aber trotzdem importieren
                    $hinweis = "";
                    if (empty($vorname)) {
                        $hinweis = " (kein Vorname angegeben)";
                        $vorname = "–";
                    }

                    // Geburtsdatum validieren
                    $geb_wert = null;
                    if ($geb) {
                        $d = DateTime::createFromFormat('Y-m-d', $geb);
                        if ($d && $d <= new DateTime()) $geb_wert = $geb;
                    }

                    // Größe validieren
                    $groesse_wert = ($groesse !== '' && is_numeric($groesse) && $groesse >= 100 && $groesse <= 250)
                        ? $groesse : null;

                    // E-Mail-Duplikat prüfen
                    if ($email) {
                        $dup = $pdo->prepare("SELECT COUNT(*) FROM benutzer WHERE Email=:e");
                        $dup->execute([':e' => $email]);
                        if ($dup->fetchColumn() > 0) {
                            $fehler[] = "Übersprungen '$vorname $nachname': E-Mail '$email' existiert bereits.";
                            $uebersprungen++;
                            continue;
                        }
                    }

                    try {
                        $pdo->beginTransaction();

                        // Eindeutigen Benutzernamen generieren
                        $basis = preg_replace('/[^a-z0-9.]/', '',
                            iconv('UTF-8', 'ASCII//TRANSLIT', strtolower("$vorname.$nachname")));
                        $basis = trim($basis, '.');
                        if (empty($basis)) $basis = 'patient';
                        $uname = $basis; $c = 1;
                        $ck = $pdo->prepare("SELECT COUNT(*) FROM benutzer WHERE username=:u");
                        $ck->execute([':u' => $uname]);
                        while ($ck->fetchColumn() > 0) {
                            $uname = $basis . $c++;
                            $ck->execute([':u' => $uname]);
                        }

                        $pdo->prepare("INSERT INTO benutzer (username,PW_Hash,Rolle,Email,Telefon)
                            VALUES(:u,:pw,'Pat',:e,:t)")
                            ->execute([
                                ':u'  => $uname,
                                ':pw' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                                ':e'  => $email ?: null,
                                ':t'  => $telefon ?: null,
                            ]);
                        $neue_id = $pdo->lastInsertId();

                        $pdo->prepare("INSERT INTO patienten
                            (PID,Name,Vorname,Geburtsdatum,Strasse,Hausnummer,PLZ,Ort,Groesse)
                            VALUES(:pid,:n,:v,:g,:s,:hn,:plz,:o,:gr)")
                            ->execute([
                                ':pid' => $neue_id,
                                ':n'   => $nachname,
                                ':v'   => $vorname,
                                ':g'   => $geb_wert,
                                ':s'   => $strasse ?: null,
                                ':hn'  => $hausnummer ?: null,
                                ':plz' => $plz ?: null,
                                ':o'   => $ort ?: null,
                                ':gr'  => $groesse_wert,
                            ]);

                        $pdo->commit();
                        $importiert++;
                        $vorschau[] = [
                            'name'     => $vorname . ' ' . $nachname . $hinweis,
                            'username' => $uname,
                            'email'    => $email ?: '–',
                            'telefon'  => $telefon ?: '–',
                        ];

                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $fehler[] = "DB-Fehler bei '$vorname $nachname'.";
                        $uebersprungen++;
                    }
                }

                if ($importiert > 0) {
                    $erfolg = "$importiert Patient(en) erfolgreich importiert."
                        . ($uebersprungen ? " $uebersprungen übersprungen." : "");
                } elseif (empty($fehler)) {
                    $fehler[] = "Keine Patienten in der Datei gefunden.";
                }
            }
        }
    }
}

$seiten_titel = "XML-Import";
$aktive_seite = "import";
require_once "Includes/header.php";
?>

<div class="container-md" style="margin:0 auto">
<a class="link-zurueck" href="dashboard_fachkraft.php">← Zurück zur Übersicht</a>

<div class="card">
    <div class="card-header">
        <h2>Patientenstammdaten importieren (XML)</h2>
        <a class="btn btn-sm btn-outline" href="xml_import.php?download=1">⬇ Vorlage herunterladen</a>
    </div>

    <?php if (!empty($fehler)): ?>
        <div class="alert alert-error"><strong>Hinweise:</strong><ul>
            <?php foreach ($fehler as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <?php if ($erfolg): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($erfolg) ?></div>

        <?php if (!empty($vorschau)): ?>
        <div style="margin-top:12px">
            <p style="font-size:13px;font-weight:600;color:var(--text-mittel);margin-bottom:8px">Importierte Patienten:</p>
            <div class="table-wrapper">
            <table>
                <thead><tr><th>Name</th><th>Benutzername</th><th>E-Mail</th><th>Telefon</th></tr></thead>
                <tbody>
                <?php foreach ($vorschau as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v['name']) ?></td>
                    <td><code><?= htmlspecialchars($v['username']) ?></code></td>
                    <td><?= htmlspecialchars($v['email']) ?></td>
                    <td><?= htmlspecialchars($v['telefon']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <p style="color:var(--text-mittel);font-size:14px;margin-bottom:16px;margin-top:<?= $erfolg ? '16px' : '0' ?>">
        XML-Datei hochladen. Beide Formate werden unterstützt (Moodle-Format und eigenes Format).
    </p>

    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
            <label for="xml_datei">XML-Datei auswählen (.xml, max. 2 MB)</label>
            <input type="file" id="xml_datei" name="xml_datei" accept=".xml" required
                   style="border:2px dashed var(--grau-rand);background:#fafafa;padding:12px">
        </div>
        <button type="submit" class="btn">Importieren starten</button>
    </form>

    <div class="alert alert-warn" style="margin-top:16px;margin-bottom:0">
        ⚠️ Importierte Patienten erhalten ein zufälliges Passwort. Bitte informieren Sie die Patienten über ihren Benutzernamen.
    </div>
</div>

</div>

<?php require_once "Includes/footer.php"; ?>
