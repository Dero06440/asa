<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('editeur');

$db = getDB();

$stmt = $db->query(
    "SELECT
        civilite,
        nom,
        rue,
        adresse2,
        code_postal,
        ville,
        quartier,
        parcelles,
        puisant,
        surface_m2,
        calcul_cotisation_v2(surface_m2, puisant) AS cotisation,
        calcul_cotisation_simul_v2(surface_m2, puisant) AS cotisation_simul
     FROM arrosants
     WHERE actif = 1
     ORDER BY nom ASC"
);

header('Content-Type: text/csv; charset=utf-8');
$filename = 'arrosants_export_' . date('Ymd') . '.csv';
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['civilite', 'nom', 'rue', 'adresse2', 'code_postal', 'ville', 'quartier', 'parcelles', 'puisant', 'surface_m2', 'cotisation', 'cotisation_simul'], ';');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, $row, ';');
}
fclose($out);
exit;
