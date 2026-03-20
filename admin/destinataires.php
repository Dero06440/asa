<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('editeur');

$db = getDB();
if (!tableExists($db, 'destinataires')) {
    flashSet('danger', 'La table destinataires est absente. Appliquez migrate_destinataires.sql.');
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $db->prepare('SELECT nom FROM destinataires WHERE id = ?');
    $stmt->execute([$id]);
    $name = $stmt->fetchColumn();

    if ($name !== false) {
        $db->prepare('DELETE FROM destinataires WHERE id = ?')->execute([$id]);
        logAction('delete_destinataire', 'Destinataire supprime : ' . $name . ' (id ' . $id . ')');
        flashSet('success', 'Destinataire supprime.');
    } else {
        flashSet('danger', 'Destinataire introuvable.');
    }

    header('Location: ' . BASE_URL . '/admin/destinataires.php');
    exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
$params = [];
$whereSql = '';
if ($search !== '') {
    $whereSql = 'WHERE nom LIKE ? OR adresse_1 LIKE ? OR adresse_2 LIKE ? OR ville LIKE ? OR email LIKE ? OR telephone LIKE ?';
    for ($i = 0; $i < 6; $i++) {
        $params[] = '%' . $search . '%';
    }
}

$stmt = $db->prepare("SELECT * FROM destinataires $whereSql ORDER BY nom ASC, ville ASC");
$stmt->execute($params);
$destinataires = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Destinataires - <?= APP_NAME ?></title>
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

<div class="container py-4">
  <?= flashRender() ?>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 fw-bold mb-0">Destinataires externes</h1>
    <a href="<?= BASE_URL ?>/admin/destinataire_edit.php" class="btn btn-success btn-sm">+ Ajouter</a>
  </div>

  <form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-6">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Rechercher nom, adresse, ville, email, telephone..." value="<?= h($search) ?>">
        </div>
        <div class="col-12 col-md-auto d-flex gap-2">
          <button type="submit" class="btn btn-success btn-sm">Filtrer</button>
          <?php if ($search !== ''): ?>
            <a href="<?= BASE_URL ?>/admin/destinataires.php" class="btn btn-outline-secondary btn-sm">Effacer</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </form>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0">
        <thead class="table-success">
          <tr>
            <th>Nom</th>
            <th>Adresse</th>
            <th>Contact</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($destinataires)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">Aucun destinataire.</td></tr>
          <?php endif; ?>
          <?php foreach ($destinataires as $d): ?>
            <tr>
              <td class="fw-medium"><?= h($d['nom']) ?></td>
              <td class="small text-muted">
                <?= h($d['adresse_1'] ?? '') ?>
                <?php if (!empty($d['adresse_2'])): ?><br><?= h($d['adresse_2']) ?><?php endif; ?>
                <?php if (!empty($d['code_postal']) || !empty($d['ville'])): ?><br><?= h(trim(($d['code_postal'] ?? '') . ' ' . ($d['ville'] ?? ''))) ?><?php endif; ?>
              </td>
              <td class="small text-muted">
                <?php if (!empty($d['telephone'])): ?><?= h($d['telephone']) ?><br><?php endif; ?>
                <?= h($d['email'] ?? '') ?>
              </td>
              <td class="text-center">
                <div class="d-flex gap-1 justify-content-center flex-wrap">
                  <a href="<?= BASE_URL ?>/admin/print_destinataire.php?id=<?= (int) $d['id'] ?>" class="btn btn-sm btn-outline-success py-0" target="_blank" rel="noopener">Enveloppe</a>
                  <a href="<?= BASE_URL ?>/admin/destinataire_edit.php?id=<?= (int) $d['id'] ?>" class="btn btn-sm btn-outline-secondary py-0">Edit</a>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce destinataire ?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger py-0">Suppr</button>
                  </form>
                </div>
              </td>
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
