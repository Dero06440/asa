<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/envelopes.php';

requireRole('editeur');

$db = getDB();
if (!tableExists($db, 'destinataires')) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'La table destinataires est absente. Appliquez migrate_destinataires.sql.';
    exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
$categorie = trim((string) ($_GET['categorie'] ?? ''));
$params = [];
$where = [
    "nom <> ''",
    "adresse_1 IS NOT NULL AND TRIM(adresse_1) <> ''",
    "code_postal IS NOT NULL AND TRIM(code_postal) <> ''",
    "ville IS NOT NULL AND TRIM(ville) <> ''",
];

if ($search !== '') {
    $where[] = '(nom LIKE ? OR adresse_1 LIKE ? OR adresse_2 LIKE ? OR ville LIKE ? OR email LIKE ? OR telephone LIKE ?)';
    for ($i = 0; $i < 6; $i++) {
        $params[] = '%' . $search . '%';
    }
}
if ($categorie !== '') {
    $where[] = 'categorie = ?';
    $params[] = $categorie;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);
$stmt = $db->prepare("SELECT * FROM destinataires $whereSql ORDER BY nom ASC, ville ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Aucun destinataire avec adresse complete pour ces filtres.';
    exit;
}

$pdf = new SimplePdfDocument(220, 110);

foreach ($rows as $row) {
    $lines = envelopeAddressLines($row, [
        'adresse_1' => 'adresse_1',
        'adresse_2' => 'adresse_2',
    ]);
    if (empty($lines)) {
        continue;
    }
    renderEnvelopePage($pdf, $lines);
}

$detail = count($rows) . ' enveloppes destinataires generees';
if ($categorie !== '') {
    $detail .= ' - categorie: ' . $categorie;
}
if ($search !== '') {
    $detail .= ' - recherche: ' . $search;
}
logAction('print_destinataires_envelopes', $detail);
$pdf->output('enveloppes_destinataires_' . date('Ymd') . '.pdf');
