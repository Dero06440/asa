<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/simple_pdf.php';

requireRole('editeur');

function envelopeRecipientLines(array $row): array
{
    $lines = [];

    $name = trim(implode(' ', array_filter([
        trim((string) ($row['civilite'] ?? '')),
        trim((string) ($row['nom'] ?? '')),
    ])));
    if ($name !== '') {
        $lines[] = $name;
    }

    $rue = trim((string) ($row['rue'] ?? ''));
    if ($rue !== '') {
        $lines[] = $rue;
    }

    $adresse2 = trim((string) ($row['adresse2'] ?? ''));
    if ($adresse2 !== '') {
        $lines[] = $adresse2;
    }

    $cityLine = trim(
        trim((string) ($row['code_postal'] ?? '')) . ' ' . trim((string) ($row['ville'] ?? ''))
    );
    if ($cityLine !== '') {
        $lines[] = strtoupper($cityLine);
    }

    return $lines;
}

function envelopeFontSizeForLines(array $lines, float $targetWidthMm): float
{
    $maxChars = 1;
    foreach ($lines as $line) {
        $length = mb_strlen((string) $line, 'UTF-8');
        if ($length > $maxChars) {
            $maxChars = $length;
        }
    }

    $targetWidthPt = $targetWidthMm * 72 / 25.4;
    $estimatedPt = $targetWidthPt / max($maxChars * 0.52, 1);

    return max(10.0, min(14.0, $estimatedPt));
}

function envelopeSenderLines(): array
{
    return [
        'A.S.A.',
        'Arrosants et Riverains du Paillon',
        '672 Avenue Hôtel de Ville',
        '06440 PEILLON',
    ];
}

$db = getDB();
$stmt = $db->query(
    "SELECT civilite, nom, rue, adresse2, code_postal, ville
     FROM arrosants
     WHERE actif = 1
       AND nom <> ''
       AND rue IS NOT NULL AND TRIM(rue) <> ''
       AND code_postal IS NOT NULL AND TRIM(code_postal) <> ''
       AND ville IS NOT NULL AND TRIM(ville) <> ''
     ORDER BY nom ASC"
);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Aucun arrosant actif avec adresse complete.";
    exit;
}

$pdf = new SimplePdfDocument(220, 110);

foreach ($rows as $row) {
    $lines = envelopeRecipientLines($row);
    if (empty($lines)) {
        continue;
    }

    $senderLines = envelopeSenderLines();
    $fontSize = envelopeFontSizeForLines($lines, 65);
    $lineHeight = 10 * 72 / 25.4;

    $pdf->addPage();
    $pdf->setFontSize(9, 4.8 * 72 / 25.4);
    $pdf->textBlock(34, 15, $senderLines);
    $pdf->setFontSize($fontSize, $lineHeight);
    $pdf->textBlock(130, 65, $lines);
}

logAction('print_envelopes', count($rows) . ' enveloppes generees');
$pdf->output('enveloppes_arrosants_' . date('Ymd') . '.pdf');
