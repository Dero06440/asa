<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/envelopes.php';

requireRole('editeur');

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
    $lines = envelopeAddressLines($row);
    if (empty($lines)) {
        continue;
    }

    renderEnvelopePage($pdf, $lines);
}

logAction('print_envelopes', count($rows) . ' enveloppes generees');
$pdf->output('enveloppes_arrosants_' . date('Ymd') . '.pdf');
