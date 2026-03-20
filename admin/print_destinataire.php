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

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $db->prepare('SELECT * FROM destinataires WHERE id = ?');
$stmt->execute([$id]);
$destinataire = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$destinataire) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Destinataire introuvable.';
    exit;
}

$lines = envelopeAddressLines($destinataire, [
    'adresse_1' => 'adresse_1',
    'adresse_2' => 'adresse_2',
]);

if (count($lines) < 3) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Adresse incomplete pour impression.';
    exit;
}

$pdf = new SimplePdfDocument(220, 110);
renderEnvelopePage($pdf, $lines);

logAction('print_destinataire_envelope', 'Enveloppe destinataire : ' . $destinataire['nom'] . ' (id ' . $id . ')');
$pdf->output('enveloppe_destinataire_' . $id . '_' . date('Ymd') . '.pdf');
