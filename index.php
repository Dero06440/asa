<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$db = getDB();
$currentQuery = $_SERVER['QUERY_STRING'] ?? '';
$returnTo = BASE_URL . '/index.php' . ($currentQuery !== '' ? '?' . $currentQuery : '');

$search   = trim($_GET['q'] ?? '');
$quartier = trim($_GET['quartier'] ?? '');
$ville    = trim($_GET['ville'] ?? '');
$puisant  = trim($_GET['puisant'] ?? '');
$sort     = trim($_GET['sort'] ?? 'nom');
$dir      = strtolower(trim($_GET['dir'] ?? 'asc'));

$selectionLabel = match ($puisant) {
    '0' => 'Arrosants',
    '1' => 'Puisants',
    default => 'Arrosants et Puisants',
};

$sortMap = [
    'nom' => 'a.nom',
    'adresse' => 'a.rue',
    'quartier' => 'a.quartier',
    'parcelles' => 'a.parcelles',
    'puisant' => 'a.puisant',
    'surface' => 'a.surface_m2',
    'cotisation' => 'cotisation_calc',
    'cotisation_simul' => 'cotisation_simul',
];

if (!isset($sortMap[$sort])) {
    $sort = 'nom';
}
if (!in_array($dir, ['asc', 'desc'], true)) {
    $dir = 'asc';
}

$orderBy = $sortMap[$sort] . ' ' . strtoupper($dir) . ', a.nom ASC';

$perPage = 25;
$page    = currentPage();
$offset  = ($page - 1) * $perPage;

$where  = ['a.actif = 1'];
$params = [];

if ($search !== '') {
    $where[]  = '(a.nom LIKE ? OR a.parcelles LIKE ? OR a.rue LIKE ? OR a.adresse2 LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($quartier !== '') {
    $where[]  = 'a.quartier = ?';
    $params[] = $quartier;
}
if ($ville !== '') {
    $where[]  = 'a.ville = ?';
    $params[] = $ville;
}
if ($puisant === '1' || $puisant === '0') {
    $where[]  = 'a.puisant = ?';
    $params[] = (int) $puisant;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stmtCount = $db->prepare("SELECT COUNT(*) FROM arrosants a $whereSQL");
$stmtCount->execute($params);
$total    = (int) $stmtCount->fetchColumn();
$lastPage = max(1, (int) ceil($total / $perPage));
$page     = min($page, $lastPage);
$offset   = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT a.*, calcul_cotisation_v2(a.surface_m2, a.puisant) AS cotisation_calc, calcul_cotisation_simul_v2(a.surface_m2, a.puisant) AS cotisation_simul FROM arrosants a $whereSQL ORDER BY $orderBy LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$arrosants = $stmt->fetchAll();

$quartiers = $db->query("SELECT DISTINCT quartier FROM arrosants WHERE quartier IS NOT NULL AND actif=1 ORDER BY quartier")->fetchAll(PDO::FETCH_COLUMN);
$villes    = $db->query("SELECT DISTINCT ville FROM arrosants WHERE ville IS NOT NULL AND actif=1 ORDER BY ville")->fetchAll(PDO::FETCH_COLUMN);

$stmtSum = $db->prepare("SELECT SUM(calcul_cotisation_v2(a.surface_m2, a.puisant)) FROM arrosants a $whereSQL");
$stmtSum->execute($params);
$totalCotisations = (float) $stmtSum->fetchColumn();

$stmtSumSimul = $db->prepare("SELECT SUM(calcul_cotisation_simul_v2(a.surface_m2, a.puisant)) FROM arrosants a $whereSQL");
$stmtSumSimul->execute($params);
$totalCotisationsSimul = (float) $stmtSumSimul->fetchColumn();

function sortLink(string $column, string $currentSort, string $currentDir): string {
    $nextDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
    return queryStringWithout(['page', 'sort', 'dir']) . 'sort=' . urlencode($column) . '&dir=' . urlencode($nextDir);
}

function sortIndicator(string $column, string $currentSort, string $currentDir): string {
    if ($currentSort !== $column) {
        return '';
    }
    return $currentDir === 'asc' ? ' ▲' : ' ▼';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-success">
  <div class="container-fluid">
    <div class="d-flex align-items-center gap-3">
      <a class="navbar-brand fw-bold mb-0 navbar-brand-branding" href="<?= BASE_URL ?>/index.php">
        <img src="<?= BASE_URL ?>/assets/img/peillon-blason.svg" alt="Blason de Peillon" class="navbar-brand-logo">
        <span class="navbar-brand-text">ASA Arrosants et Riverains du Paillon</span>
      </a>
      <a href="<?= BASE_URL ?>/index.php" class="text-white text-decoration-none small">Liste</a>
      <?php if (hasRole('editeur')): ?>
        <a href="<?= BASE_URL ?>/admin/print.php" class="text-white text-decoration-none small">Imprimer</a>
        <a href="<?= BASE_URL ?>/admin/tarifs.php" class="text-white text-decoration-none small">Tarifs</a>
        <a href="<?= BASE_URL ?>/admin/index.php" class="text-white text-decoration-none small">Administration</a>
      <?php endif; ?>
    </div>
    <div class="ms-auto d-flex align-items-center gap-3">
      <span class="text-white small opacity-75"><?= h($_SESSION['user_nom']) ?></span>
      <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-light btn-sm">Deconnexion</a>
    </div>
  </div>
</nav>

<div class="container-fluid py-3 px-3 px-lg-4">
  <?= flashRender() ?>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="h5 mb-0 fw-bold">Liste des Arrosants</h2>
    <div class="d-flex gap-2 flex-wrap">
      <span class="badge bg-success fs-6"><?= $total ?> <?= h(mb_strtolower($selectionLabel)) ?></span>
      <span class="badge bg-secondary fs-6"><?= euros($totalCotisations) ?></span>
      <span class="badge bg-warning text-dark fs-6"><?= euros($totalCotisationsSimul) ?></span>
    </div>
  </div>

  <form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-sm-5 col-lg-4">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Rechercher nom, parcelle, adresse..." value="<?= h($search) ?>">
        </div>
        <div class="col-6 col-sm-3 col-lg-2">
          <select name="quartier" class="form-select form-select-sm">
            <option value="">Tous les quartiers</option>
            <?php foreach ($quartiers as $q): ?>
              <option value="<?= h($q) ?>" <?= $quartier === $q ? 'selected' : '' ?>><?= h($q) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-sm-3 col-lg-2">
          <select name="ville" class="form-select form-select-sm">
            <option value="">Toutes les villes</option>
            <?php foreach ($villes as $v): ?>
              <option value="<?= h($v) ?>" <?= $ville === $v ? 'selected' : '' ?>><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-sm-3 col-lg-2">
          <select name="puisant" class="form-select form-select-sm">
            <option value="">Arrosants et Puisants</option>
            <option value="0" <?= $puisant === '0' ? 'selected' : '' ?>>Arrosants</option>
            <option value="1" <?= $puisant === '1' ? 'selected' : '' ?>>Puisants</option>
          </select>
        </div>
        <div class="col-12 col-lg-auto d-flex gap-2">
          <button type="submit" class="btn btn-success btn-sm">Filtrer</button>
          <?php if ($search || $quartier || $ville || $puisant !== ''): ?>
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-secondary btn-sm">Effacer</a>
          <?php endif; ?>
        </div>
        <?php if (hasRole('editeur')): ?>
        <div class="col-auto ms-auto">
          <a href="<?= BASE_URL ?>/edit.php?return_to=<?= urlencode($returnTo) ?>" class="btn btn-outline-success btn-sm">+ Ajouter</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0 align-middle">
        <thead class="table-success">
          <tr>
            <th><a class="text-reset text-decoration-none" href="<?= sortLink('nom', $sort, $dir) ?>">Nom<?= sortIndicator('nom', $sort, $dir) ?></a></th>
            <th class="d-none d-md-table-cell"><a class="text-reset text-decoration-none" href="<?= sortLink('adresse', $sort, $dir) ?>">Adresse<?= sortIndicator('adresse', $sort, $dir) ?></a></th>
            <th class="d-none d-lg-table-cell"><a class="text-reset text-decoration-none" href="<?= sortLink('quartier', $sort, $dir) ?>">Quartier<?= sortIndicator('quartier', $sort, $dir) ?></a></th>
            <th class="d-none d-sm-table-cell"><a class="text-reset text-decoration-none" href="<?= sortLink('parcelles', $sort, $dir) ?>">Parcelles<?= sortIndicator('parcelles', $sort, $dir) ?></a></th>
            <th class="text-center"><a class="text-reset text-decoration-none" href="<?= sortLink('puisant', $sort, $dir) ?>">Puisant<?= sortIndicator('puisant', $sort, $dir) ?></a></th>
            <th class="text-end"><a class="text-reset text-decoration-none" href="<?= sortLink('surface', $sort, $dir) ?>">Surface<?= sortIndicator('surface', $sort, $dir) ?></a></th>
            <th class="text-end"><a class="text-reset text-decoration-none" href="<?= sortLink('cotisation', $sort, $dir) ?>">Cotisation<?= sortIndicator('cotisation', $sort, $dir) ?></a></th>
            <th class="text-end"><a class="text-reset text-decoration-none" href="<?= sortLink('cotisation_simul', $sort, $dir) ?>">Cotisation simul<?= sortIndicator('cotisation_simul', $sort, $dir) ?></a></th>
            <?php if (hasRole('editeur')): ?>
            <th class="text-center">Actions</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($arrosants)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">Aucun resultat.</td></tr>
          <?php endif; ?>
          <?php foreach ($arrosants as $a): ?>
          <tr>
            <td>
              <?php if ($a['civilite']): ?>
                <span class="text-muted"><?= h($a['civilite']) ?></span>
              <?php endif; ?>
              <span class="fw-medium"><?= h($a['nom']) ?></span>
            </td>
            <td class="d-none d-md-table-cell small text-muted">
              <?= h($a['rue'] ?? '') ?>
              <?php if (!empty($a['adresse2'])): ?>
                <br><?= h($a['adresse2']) ?>
              <?php endif; ?>
              <?php if ($a['code_postal'] || $a['ville']): ?>
                <br><?= h(($a['code_postal'] ?? '') . ' ' . ($a['ville'] ?? '')) ?>
              <?php endif; ?>
            </td>
            <td class="d-none d-lg-table-cell small"><?= h($a['quartier'] ?? '-') ?></td>
            <td class="d-none d-sm-table-cell small text-muted" style="max-width:200px; white-space:normal;">
              <?= h($a['parcelles'] ?? '-') ?>
            </td>
            <td class="text-center small"><?= (int) $a['puisant'] === 1 ? 'Oui' : 'Non' ?></td>
            <td class="text-end small"><?= (int) $a['puisant'] === 1 ? '-' : surface($a['surface_m2']) ?></td>
            <td class="text-end fw-semibold text-success"><?= euros($a['cotisation_calc']) ?></td>
            <td class="text-end fw-semibold text-warning"><?= euros($a['cotisation_simul']) ?></td>
            <?php if (hasRole('editeur')): ?>
            <td class="text-center">
              <a href="<?= BASE_URL ?>/edit.php?id=<?= (int) $a['id'] ?>&return_to=<?= urlencode($returnTo) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2">Edit</a>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($lastPage > 1): ?>
  <nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
      <?php for ($p = 1; $p <= $lastPage; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
          <a class="page-link" href="<?= queryStringWithout() ?>page=<?= $p ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
