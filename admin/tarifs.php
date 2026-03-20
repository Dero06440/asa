<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('editeur');

$db = getDB();
$message = '';
$percentage = '';
$puisantAmount = '';
$simulationSetting = $db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
$simulationSetting->execute(['simulation_percentage']);
$storedPercentage = $simulationSetting->fetchColumn();
$storedPercentage = $storedPercentage !== false ? (string) $storedPercentage : '100';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $percentage = trim($_POST['pourcentage'] ?? '');
    $puisantAmount = trim($_POST['cotisation_puisant'] ?? '');
    $numericPercentage = str_replace(',', '.', $percentage);
    $numericPuisantAmount = str_replace(',', '.', $puisantAmount);

    if ($numericPercentage === '' || !is_numeric($numericPercentage)) {
        $message = 'Saisissez un pourcentage valide.';
    } elseif ($numericPuisantAmount === '' || !is_numeric($numericPuisantAmount) || (float) $numericPuisantAmount < 0) {
        $message = 'Saisissez une cotisation puisant valide.';
    } else {
        $ratio = (float) $numericPercentage / 100;
        $stmt = $db->prepare('UPDATE tarifs SET tarif_simul = ROUND(tarif * ?, 2) WHERE m2 > 0');
        $stmt->execute([$ratio]);
        $stmtPuisant = $db->prepare('UPDATE tarifs SET tarif = ?, tarif_simul = ROUND(? * ?, 2) WHERE m2 = 0');
        $stmtPuisant->execute([$numericPuisantAmount, $numericPuisantAmount, $ratio]);
        $stmtSetting = $db->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $stmtSetting->execute(['simulation_percentage', $numericPercentage]);
        logAction('tarifs_simul', 'Pourcentage applique : ' . $numericPercentage . '%');
        logAction('tarif_puisant', 'Cotisation puisant : ' . $numericPuisantAmount . ' EUR');
        flashSet('success', 'Tarifs de simulation mis a jour avec ' . $numericPercentage . '%.');
        header('Location: ' . BASE_URL . '/admin/tarifs.php');
        exit;
    }
}

$rows = $db->query('SELECT m2, tarif, tarif_simul FROM tarifs WHERE m2 > 0 ORDER BY m2 ASC')->fetchAll();
$puisantTarif = $db->query('SELECT tarif FROM tarifs WHERE m2 = 0')->fetchColumn();
$percentage = $percentage !== '' ? $percentage : $storedPercentage;
$puisantAmount = $puisantAmount !== '' ? $puisantAmount : (string) $puisantTarif;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tarifs - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-success">
  <div class="container-fluid">
    <div class="d-flex align-items-center gap-3">
      <a class="navbar-brand fw-bold mb-0 navbar-brand-branding" href="<?= BASE_URL ?>/index.php">
        <img src="<?= BASE_URL ?>/assets/img/peillon-blason.svg" alt="Blason de Peillon" class="navbar-brand-logo">
        <span class="navbar-brand-text">ASA Arrosants et Riverains du Paillon</span>
      </a>
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

<div class="container py-4" style="max-width: 900px;">
  <h1 class="h4 fw-bold mb-4">Tarifs et simulation</h1>

  <?= flashRender() ?>

  <?php if ($message): ?>
    <div class="alert alert-danger"><?= h($message) ?></div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-success text-white">Parametres de simulation</div>
    <div class="card-body">
      <form method="POST" class="row g-3 align-items-end">
        <div class="col-12 col-md-4">
          <label for="pourcentage" class="form-label fw-medium">Pourcentage de simulation</label>
          <input type="number" step="0.01" min="0" name="pourcentage" id="pourcentage" class="form-control" value="<?= h($percentage) ?>" placeholder="ex: 40">
        </div>
        <div class="col-12 col-md-4">
          <label for="cotisation_puisant" class="form-label fw-medium">Cotisation puisant</label>
          <input type="number" step="0.01" min="0" name="cotisation_puisant" id="cotisation_puisant" class="form-control" value="<?= h($puisantAmount) ?>">
        </div>
        <div class="col-12 col-md-auto">
          <button type="submit" class="btn btn-success">Appliquer</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-light fw-semibold">Bareme et simulation</div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Seuil m2</th>
            <th class="text-end">Tarif</th>
            <th class="text-end">Tarif simulation</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= h((string) $row['m2']) ?></td>
              <td class="text-end"><?= euros($row['tarif']) ?></td>
              <td class="text-end"><?= euros($row['tarif_simul']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
