<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('editeur');

$db = getDB();

$totalArrosants = (int) $db->query('SELECT COUNT(*) FROM arrosants WHERE actif=1')->fetchColumn();
$totalCotisations = (float) $db->query('SELECT SUM(calcul_cotisation_v2(surface_m2, puisant)) FROM arrosants WHERE actif=1')->fetchColumn();
$totalCotisationsSimul = (float) $db->query('SELECT SUM(calcul_cotisation_simul_v2(surface_m2, puisant)) FROM arrosants WHERE actif=1')->fetchColumn();
$totalUtilisateurs = hasRole('admin') ? (int) $db->query('SELECT COUNT(*) FROM utilisateurs WHERE actif=1')->fetchColumn() : 0;
$derniersMaj = $db->query('SELECT nom, updated_at FROM arrosants WHERE actif=1 ORDER BY updated_at DESC LIMIT 5')->fetchAll();
$derniersLogins = $db->query(
    'SELECT l.created_at, l.action, l.ip, u.nom, u.prenom
     FROM sessions_log l
     LEFT JOIN utilisateurs u ON u.id = l.utilisateur_id
     ORDER BY l.created_at DESC LIMIT 10'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administration - <?= APP_NAME ?></title>
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
    <div class="ms-auto d-flex align-items-center gap-3">
      <span class="badge bg-warning text-dark"><?= hasRole('admin') ? 'Admin' : 'Editeur' ?></span>
      <span class="text-white small opacity-75"><?= h($_SESSION['user_nom']) ?></span>
      <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-light btn-sm">Deconnexion</a>
    </div>
  </div>
</nav>

<div class="container py-4">
  <?= flashRender() ?>
  <h1 class="h4 fw-bold mb-4">Administration</h1>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center p-3">
        <div class="display-6 fw-bold text-success"><?= $totalArrosants ?></div>
        <div class="small text-muted">Arrosants actifs</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center p-3">
        <div class="display-6 fw-bold text-success"><?= euros($totalCotisations) ?></div>
        <div class="small text-muted">Total cotisations</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center p-3">
        <div class="display-6 fw-bold text-warning"><?= euros($totalCotisationsSimul) ?></div>
        <div class="small text-muted">Total simul</div>
      </div>
    </div>
    <?php if (hasRole('admin')): ?>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center p-3">
        <div class="display-6 fw-bold text-success"><?= $totalUtilisateurs ?></div>
        <div class="small text-muted">Utilisateurs actifs</div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="row g-3 mb-4">
    <?php if (hasRole('admin')): ?>
    <div class="col-12 col-md-3">
      <a href="<?= BASE_URL ?>/admin/utilisateurs.php" class="card border-0 shadow-sm text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Utilisateurs</h5>
          <p class="card-text text-muted small">Gerer les acces lecteur / editeur / admin</p>
        </div>
      </a>
    </div>
    <div class="col-12 col-md-3">
      <a href="<?= BASE_URL ?>/admin/import.php" class="card border-0 shadow-sm text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Importer</h5>
          <p class="card-text text-muted small">Importer un fichier CSV dans la base</p>
        </div>
      </a>
    </div>
    <?php endif; ?>
    <div class="col-12 col-md-3">
      <a href="<?= BASE_URL ?>/admin/print.php" class="card border-0 shadow-sm text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Imprimer</h5>
          <p class="card-text text-muted small">Generer les documents d'impression</p>
        </div>
      </a>
    </div>
    <div class="col-12 col-md-3">
      <a href="<?= BASE_URL ?>/admin/tarifs.php" class="card border-0 shadow-sm text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Tarifs</h5>
          <p class="card-text text-muted small">Appliquer un pourcentage sur le bareme simule</p>
        </div>
      </a>
    </div>
    <div class="col-12 col-md-3">
      <a href="<?= BASE_URL ?>/admin/export.php" class="card border-0 shadow-sm text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Exporter</h5>
          <p class="card-text text-muted small">Exporter les colonnes visibles de la liste</p>
        </div>
      </a>
    </div>
    <div class="col-12 col-md-3">
      <a href="<?= BASE_URL ?>/index.php" class="card border-0 shadow-sm text-decoration-none text-dark h-100">
        <div class="card-body">
          <h5 class="card-title">Liste</h5>
          <p class="card-text text-muted small">Retour a la liste principale</p>
        </div>
      </a>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12 col-md-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-light fw-semibold">Dernieres modifications</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($derniersMaj as $row): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center py-2">
            <span class="small"><?= h($row['nom']) ?></span>
            <span class="text-muted small"><?= dateFR(substr($row['updated_at'], 0, 10)) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-light fw-semibold">Journal des connexions</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($derniersLogins as $log): ?>
          <li class="list-group-item py-2">
            <div class="d-flex justify-content-between">
              <span class="small fw-medium"><?= h(trim(($log['prenom'] ?? '') . ' ' . ($log['nom'] ?? 'Inconnu'))) ?></span>
              <span class="badge <?= $log['action'] === 'login' ? 'bg-success' : ($log['action'] === 'logout' ? 'bg-secondary' : 'bg-warning text-dark') ?>">
                <?= h($log['action']) ?>
              </span>
            </div>
            <div class="text-muted" style="font-size:0.75rem;">
              <?= h(substr($log['created_at'], 0, 16)) ?> - <?= h($log['ip'] ?? '') ?>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
