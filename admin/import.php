<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$db      = getDB();
$message = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvfile'])) {
    $file = $_FILES['csvfile'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Erreur lors de l\'upload du fichier.';
    } elseif (!in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['csv', 'txt'])) {
        $message = 'Seuls les fichiers CSV sont acceptes.';
    } else {
        $handle    = fopen($file['tmp_name'], 'r');
        $delimiter = $_POST['delimiter'] ?? ';';
        $skipFirst = isset($_POST['skip_header']);
        $lineNum   = 0;
        $imported  = 0;
        $errors    = [];

        $insertSQL = 'INSERT INTO arrosants
                      (annee, civilite, nom, rue, adresse2, quartier, code_postal, ville,
                       parcelles, puisant, surface_m2, taxe_annuelle, date_maj)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $db->prepare($insertSQL);

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNum++;
            if ($skipFirst && $lineNum === 1) {
                continue;
            }
            if (count($row) < 4) {
                $errors[] = "Ligne $lineNum : trop peu de colonnes.";
                continue;
            }

            $hasLegacyRef = count($row) >= 12;
            $offset = $hasLegacyRef ? 1 : 0;
            $nom = isset($row[1]) ? trim($row[1]) : '';

            if (!$nom) {
                $errors[] = "Ligne $lineNum : nom manquant.";
                continue;
            }

            $hasAdresse2 = count($row) >= (12 + $offset);
            $hasPuisant = count($row) >= (13 + $offset);
            $surfaceIndex = 8 + $offset + ($hasAdresse2 ? 1 : 0) + ($hasPuisant ? 1 : 0);
            $taxeIndex = 9 + $offset + ($hasAdresse2 ? 1 : 0) + ($hasPuisant ? 1 : 0);
            $dateIndex = 10 + $offset + ($hasAdresse2 ? 1 : 0) + ($hasPuisant ? 1 : 0);
            $dateMaj = null;
            if (!empty($row[$dateIndex])) {
                $d = DateTime::createFromFormat('d/m/Y', trim($row[$dateIndex]))
                  ?: DateTime::createFromFormat('Y-m-d', trim($row[$dateIndex]));
                if ($d) {
                    $dateMaj = $d->format('Y-m-d');
                }
            }

            $puisantIndex = 8 + $offset + ($hasAdresse2 ? 1 : 0);
            $puisant = 0;
            if ($hasPuisant && isset($row[$puisantIndex])) {
                $rawPuisant = strtolower(trim((string) $row[$puisantIndex]));
                $puisant = in_array($rawPuisant, ['1', 'oui', 'true', 'x'], true) ? 1 : 0;
            }

            try {
                $stmt->execute([
                    isset($row[2 + $offset]) ? (int) $row[2 + $offset] : 2025,
                    isset($row[0]) ? trim($row[0]) : null,
                    $nom,
                    isset($row[3 + $offset]) ? trim($row[3 + $offset]) : null,
                    $hasAdresse2 && isset($row[4 + $offset]) ? trim($row[4 + $offset]) : null,
                    isset($row[4 + $offset + ($hasAdresse2 ? 1 : 0)]) ? trim($row[4 + $offset + ($hasAdresse2 ? 1 : 0)]) : null,
                    isset($row[5 + $offset + ($hasAdresse2 ? 1 : 0)]) ? trim($row[5 + $offset + ($hasAdresse2 ? 1 : 0)]) : null,
                    isset($row[6 + $offset + ($hasAdresse2 ? 1 : 0)]) ? trim($row[6 + $offset + ($hasAdresse2 ? 1 : 0)]) : null,
                    isset($row[7 + $offset + ($hasAdresse2 ? 1 : 0)]) ? trim($row[7 + $offset + ($hasAdresse2 ? 1 : 0)]) : null,
                    $puisant,
                    isset($row[$surfaceIndex]) && $row[$surfaceIndex] !== '' ? (float) str_replace(',', '.', $row[$surfaceIndex]) : null,
                    isset($row[$taxeIndex]) && $row[$taxeIndex] !== '' ? (float) str_replace(',', '.', $row[$taxeIndex]) : null,
                    $dateMaj,
                ]);
                $imported++;
            } catch (PDOException $e) {
                $errors[] = "Ligne $lineNum : " . $e->getMessage();
            }
        }
        fclose($handle);

        logAction('import', "$imported arrosants importes ($lineNum lignes traitees)");
        $results = ['imported' => $imported, 'errors' => $errors, 'total' => $lineNum];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Import CSV - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-success">
  <div class="container-fluid">
    <div class="d-flex align-items-center gap-3">
      <a class="navbar-brand fw-bold mb-0" href="<?= BASE_URL ?>/index.php">ASA Arrosants et Riverains du Paillon</a>
      <a href="<?= BASE_URL ?>/index.php" class="text-white text-decoration-none small">Liste</a>
      <a href="<?= BASE_URL ?>/admin/print.php" class="text-white text-decoration-none small">Imprimer</a>
      <a href="<?= BASE_URL ?>/admin/tarifs.php" class="text-white text-decoration-none small">Tarifs</a>
      <a href="<?= BASE_URL ?>/admin/index.php" class="text-white text-decoration-none small">Administration</a>
    </div>
    <div class="ms-auto">
      <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-light btn-sm">Deconnexion</a>
    </div>
  </div>
</nav>

<div class="container py-4" style="max-width: 700px;">
  <h1 class="h4 fw-bold mb-4">Import CSV</h1>

  <?php if ($message): ?>
    <div class="alert alert-danger"><?= h($message) ?></div>
  <?php endif; ?>

  <?php if (!empty($results)): ?>
    <div class="alert alert-<?= empty($results['errors']) ? 'success' : 'warning' ?>">
      <strong><?= $results['imported'] ?></strong> arrosants importes sur <?= $results['total'] ?> lignes traitees.
      <?php if (!empty($results['errors'])): ?>
        <hr>
        <strong>Erreurs :</strong>
        <ul class="mb-0 small">
          <?php foreach ($results['errors'] as $err): ?>
            <li><?= h($err) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-success text-white">Importer un fichier CSV</div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label fw-medium">Fichier CSV</label>
          <input type="file" name="csvfile" class="form-control" accept=".csv,.txt" required>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label fw-medium">Separateur</label>
            <select name="delimiter" class="form-select">
              <option value=";">Point-virgule (;)</option>
              <option value=",">Virgule (,)</option>
              <option value="	">Tabulation</option>
            </select>
          </div>
          <div class="col-6 d-flex align-items-end">
            <div class="form-check ms-2">
              <input type="checkbox" name="skip_header" id="skip_header" class="form-check-input" checked>
              <label for="skip_header" class="form-check-label">Ignorer la 1ere ligne</label>
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-success">Importer</button>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-light fw-semibold">Format CSV attendu</div>
    <div class="card-body">
      <p class="small text-muted mb-2">Les colonnes doivent etre dans cet ordre (separateur <code>;</code>) :</p>
      <div class="table-responsive">
        <table class="table table-sm table-bordered small">
          <thead class="table-light">
            <tr><th>#</th><th>Colonne</th><th>Exemple</th></tr>
          </thead>
          <tbody>
            <tr><td>0</td><td>Civilite</td><td>M.</td></tr>
            <tr><td>1</td><td>Nom *</td><td>DUPONT Jean</td></tr>
            <tr><td>2</td><td>Annee</td><td>2025</td></tr>
            <tr><td>3</td><td>Adresse 1</td><td>5 Bd de la Vallee</td></tr>
            <tr><td>4</td><td>Adresse 2</td><td>Batiment A</td></tr>
            <tr><td>5</td><td>Quartier</td><td>LES NOVAINES</td></tr>
            <tr><td>6</td><td>Code postal</td><td>06440</td></tr>
            <tr><td>7</td><td>Ville</td><td>PEILLON</td></tr>
            <tr><td>8</td><td>Parcelles</td><td>C 1269 Les Novaines</td></tr>
            <tr><td>9</td><td>Puisant</td><td>oui</td></tr>
            <tr><td>10</td><td>Surface m2</td><td>465</td></tr>
            <tr><td>11</td><td>Taxe annuelle EUR</td><td>23.5</td></tr>
            <tr><td>12</td><td>Date MAJ</td><td>20/04/2024</td></tr>
          </tbody>
        </table>
      </div>
      <p class="small text-muted mt-2 mb-0">L'ancien format sans <code>adresse2</code> ou sans <code>puisant</code> reste accepte. L'ancien format avec une colonne <code>ref</code> reste aussi accepte et ignore.</p>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
