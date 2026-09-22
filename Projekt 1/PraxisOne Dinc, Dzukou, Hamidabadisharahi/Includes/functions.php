<?php
// ============================================================
// Globale Hilfsfunktionen
// ============================================================

// BMI berechnen und klassifizieren
function bmi_berechnen(?float $gewicht, ?float $groesse_cm): ?float {
    if (!$gewicht || !$groesse_cm || $groesse_cm <= 0) return null;
    $g = $groesse_cm / 100;
    return round($gewicht / ($g * $g), 1);
}

function bmi_klasse(?float $bmi): string {
    if ($bmi === null) return '';
    if ($bmi < 18.5)  return 'untergewicht';
    if ($bmi <= 24.9) return 'normal';
    if ($bmi <= 29.9) return 'uebergewicht';
    return 'adipositas';
}

function bmi_label(?float $bmi): string {
    return match(bmi_klasse($bmi)) {
        'untergewicht' => 'Untergewicht',
        'normal'       => 'Normalgewicht',
        'uebergewicht' => 'Übergewicht',
        'adipositas'   => 'Adipositas',
        default        => '–'
    };
}

function bmi_farbe(?float $bmi): string {
    return match(bmi_klasse($bmi)) {
        'untergewicht' => '#b3d4f5',
        'normal'       => '#c8e6c9',
        'uebergewicht' => '#fff9c4',
        'adipositas'   => '#ffcdd2',
        default        => '#e0e0e0'
    };
}

// Blutdruck-Warnstufe
function rr_status(?string $rr): string {
    if (!$rr || !str_contains($rr, '/')) return '';
    [$sys, $dia] = explode('/', $rr);
    if ((int)$sys >= 180 || (int)$dia >= 110) return 'kritisch';
    if ((int)$sys >= 140 || (int)$dia >= 90)  return 'erhoeht';
    return 'normal';
}

// Befinden als Text
function befinden_text(int $wert): string {
    return match($wert) {
        1 => '😞 Sehr schlecht', 2 => '😕 Schlecht', 3 => '😐 Mittel',
        4 => '🙂 Gut',           5 => '😄 Sehr gut', default => '–'
    };
}

// Datum formatieren
function datum_formatieren(?string $datum, bool $uhrzeit = false): string {
    if (!$datum) return '–';
    return date($uhrzeit ? 'd.m.Y H:i' : 'd.m.Y', strtotime($datum));
}

// Alter aus Geburtsdatum
function alter_berechnen(?string $geburtsdatum): ?int {
    if (!$geburtsdatum) return null;
    return (new DateTime())->diff(new DateTime($geburtsdatum))->y;
}

// Formularwert sicher ausgeben
function val(string $key, array $daten, string $fallback = ''): string {
    return htmlspecialchars($daten[$key] ?? $fallback);
}

// Adresse aus getrennten Feldern zusammenbauen
function adresse_formatieren(array $patient): string {
    $teile = [];
    if (!empty($patient['Strasse']))    $teile[] = $patient['Strasse'] . ' ' . ($patient['Hausnummer'] ?? '');
    if (!empty($patient['PLZ']))        $teile[] = $patient['PLZ'] . ' ' . ($patient['Ort'] ?? '');
    return implode(', ', array_filter($teile)) ?: '–';
}

// XML-Beispieldatei generieren
function xml_beispiel_generieren(): string {
    return '<?xml version="1.0" encoding="UTF-8"?>
<patienten>
  <patient>
    <vorname>Max</vorname>
    <nachname>Mustermann</nachname>
    <geburtsdatum>1980-03-15</geburtsdatum>
    <strasse>Hauptstraße</strasse>
    <hausnummer>1</hausnummer>
    <plz>89073</plz>
    <ort>Ulm</ort>
    <groesse>178</groesse>
    <email>max@example.com</email>
    <telefon>0731 111111</telefon>
  </patient>
  <patient>
    <vorname>Anna</vorname>
    <nachname>Müller</nachname>
    <geburtsdatum>1975-07-22</geburtsdatum>
    <strasse>Gartenweg</strasse>
    <hausnummer>5</hausnummer>
    <plz>89075</plz>
    <ort>Ulm</ort>
    <groesse>165</groesse>
    <email>anna@example.com</email>
    <telefon>0731 222222</telefon>
  </patient>
</patienten>';
}
