<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('editeur');

function calculateCotisationForSurface(PDO $db, $surface, int $puisant = 0): ?float {
    if (!$puisant && ($surface === null || $surface === '')) {
        return null;
    }
    $stmt = $db->prepare('SELECT calcul_cotisation_v2(?, ?)');
    $stmt->execute([$surface, $puisant]);
    $value = $stmt->fetchColumn();
    return $value !== null ? (float) $value : null;
}

function calculateCotisationSimulForSurface(PDO $db, $surface, int $puisant = 0): ?float {
    if (!$puisant && ($surface === null || $surface === '')) {
        return null;
    }
    $stmt = $db->prepare('SELECT calcul_cotisation_simul_v2(?, ?)');
    $stmt->execute([$surface, $puisant]);
    $value = $stmt->fetchColumn();
    return $value !== null ? (float) $value : null;
}

$db = getDB();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isNew = ($id === 0);
$error = '';
$defaultReturnTo = BASE_URL . '/index.php';
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
if ($returnTo === '' || !str_starts_with($returnTo, BASE_URL . '/')) {
    $returnTo = $defaultReturnTo;
}

$a = [
    'id' => 0, 'annee' => 2025, 'civilite' => '', 'nom' => '',
    'rue' => '', 'adresse2' => '', 'quartier' => '', 'code_postal' => '', 'ville' => '',
    'parcelles' => '', 'surface_m2' => '', 'puisant' => 0,
    'cotisation' => '', 'cotisation_simul' => '', 'notes' => '', 'actif' => 1,
    'updated_at' => '',
];

if (!$isNew) {
    $stmt = $db->prepare('SELECT a.*, calcul_cotisation_v2(a.surface_m2, a.puisant) AS cotisation_calc, calcul_cotisation_simul_v2(a.surface_m2, a.puisant) AS cotisation_simul_calc FROM arrosants a WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flashSet('danger', 'Arrosant introuvable.');
        header('Location: ' . $returnTo);
        exit;
    }
    $a = $found;
    $a['cotisation'] = $a['cotisation_calc'];
    $a['cotisation_simul'] = $a['cotisation_simul_calc'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? 'save';

    if ($postAction === 'delete') {
        if ($isNew) {
            flashSet('danger', 'Suppression impossible pour un arrosant non enregistre.');
            header('Location: ' . $returnTo);
            exit;
        }

        $deletedName = (string) ($a['nom'] ?? ('id ' . $id));
        $stmt = $db->prepare('DELETE FROM arrosants WHERE id = ?');
        $stmt->execute([$id]);

        logAction('delete', 'Arrosant supprime : ' . $deletedName . ' (id ' . $id . ')');
        flashSet('success', 'Arrosant "' . $deletedName . '" supprime.');
        header('Location: ' . $returnTo);
        exit;
    }

    $surfaceInput = $_POST['surface_m2'] ?? '';
    $fields = [
        'civilite'      => trim($_POST['civilite'] ?? ''),
        'nom'           => trim($_POST['nom'] ?? ''),
        'rue'           => trim($_POST['rue'] ?? ''),
        'adresse2'      => trim($_POST['adresse2'] ?? ''),
        'quartier'      => trim($_POST['quartier'] ?? ''),
        'code_postal'   => trim($_POST['code_postal'] ?? ''),
        'ville'         => trim($_POST['ville'] ?? ''),
        'parcelles'     => trim($_POST['parcelles'] ?? ''),
        'surface_m2'    => $surfaceInput !== '' ? (float) str_replace(',', '.', $surfaceInput) : null,
        'puisant'       => isset($_POST['puisant']) ? 1 : 0,
        'notes'         => trim($_POST['notes'] ?? ''),
        'actif'         => isset($_POST['actif']) ? 1 : 0,
    ];

    if (empty($fields['nom'])) {
        $error = 'Le nom est obligatoire.';
    } else {
        if ($isNew) {
            $sql = 'INSERT INTO arrosants
                    (civilite, nom, rue, adresse2, quartier, code_postal, ville,
                     parcelles, surface_m2, puisant, notes, actif)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $fields['civilite'],
                $fields['nom'],
                $fields['rue'],
                $fields['adresse2'],
                $fields['quartier'],
                $fields['code_postal'],
                $fields['ville'],
                $fields['parcelles'],
                $fields['surface_m2'],
                $fields['puisant'],
                $fields['notes'],
                $fields['actif'],
            ]);
            logAction('create', 'Arrosant cree : ' . $fields['nom']);
            flashSet('success', 'Arrosant "' . $fields['nom'] . '" cree avec succes.');
        } else {
            $sql = 'UPDATE arrosants SET
                    civilite=?, nom=?, rue=?, adresse2=?, quartier=?, code_postal=?, ville=?,
                    parcelles=?, surface_m2=?, puisant=?, notes=?, actif=?
                    WHERE id=?';
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $fields['civilite'],
                $fields['nom'],
                $fields['rue'],
                $fields['adresse2'],
                $fields['quartier'],
                $fields['code_postal'],
                $fields['ville'],
                $fields['parcelles'],
                $fields['surface_m2'],
                $fields['puisant'],
                $fields['notes'],
                $fields['actif'],
                $id,
            ]);
            logAction('edit', 'Arrosant modifie : ' . $fields['nom'] . ' (id ' . $id . ')');
            flashSet('success', '"' . $fields['nom'] . '" mis a jour.');
        }
        header('Location: ' . $returnTo);
        exit;
    }

    $a = array_merge($a, $fields, [
        'id' => $id,
        'cotisation' => calculateCotisationForSurface($db, $fields['surface_m2'], $fields['puisant']),
        'cotisation_simul' => calculateCotisationSimulForSurface($db, $fields['surface_m2'], $fields['puisant']),
    ]);
}

$pageTitle = $isNew ? 'Nouvel arrosant' : 'Modifier : ' . $a['nom'];
$lastSeenUpdate = !empty($a['updated_at']) ? substr((string) $a['updated_at'], 0, 10) : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> - <?= APP_NAME ?></title>
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
    <div class="ms-auto d-flex align-items-center gap-3">
      <span class="text-white small opacity-75"><?= h($_SESSION['user_nom']) ?></span>
      <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-light btn-sm">Deconnexion</a>
    </div>
  </div>
</nav>

<div class="container py-3 edit-compact" style="max-width: 820px;">
  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= h($returnTo) ?>" class="text-muted text-decoration-none">Retour</a>
    <span class="text-muted">/</span>
    <h1 class="h5 mb-0 fw-bold"><?= h($pageTitle) ?></h1>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="">
    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <div class="row g-2">
          <div class="col-6 col-md-3">
            <label class="form-label fw-medium mb-1">Civilite</label>
            <select name="civilite" class="form-select form-select-sm">
              <option value="">-</option>
              <?php foreach (['M.','Mme','M. Mme','M.Mme','Mme.'] as $civ): ?>
                <option value="<?= h($civ) ?>" <?= $a['civilite'] === $civ ? 'selected' : '' ?>><?= h($civ) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-9">
            <label class="form-label fw-medium mb-1">Nom *</label>
            <input type="text" name="nom" class="form-control form-control-sm" required value="<?= h($a['nom']) ?>">
          </div>
        </div>

        <div class="row g-2 mt-1">
          <div class="col-12 col-md-7">
            <label class="form-label fw-medium mb-1">Adresse 1</label>
            <input type="text" name="rue" class="form-control form-control-sm" value="<?= h($a['rue'] ?? '') ?>">
          </div>
          <div class="col-12 col-md-5">
            <label class="form-label fw-medium mb-1">Adresse 2</label>
            <input type="text" name="adresse2" class="form-control form-control-sm" value="<?= h($a['adresse2'] ?? '') ?>">
          </div>
          <div class="col-4 col-md-3">
            <label class="form-label fw-medium mb-1">Code postal</label>
            <input type="text" name="code_postal" class="form-control form-control-sm" value="<?= h($a['code_postal'] ?? '') ?>">
          </div>
          <div class="col-8 col-md-4">
            <label class="form-label fw-medium mb-1">Ville</label>
            <input type="text" name="ville" class="form-control form-control-sm" value="<?= h($a['ville'] ?? '') ?>">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label fw-medium mb-1">Mise a jour constatee</label>
            <input type="text" class="form-control form-control-sm" value="<?= h($lastSeenUpdate) ?>" readonly>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-success text-white">Parcelles et cotisations</div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-12 col-md-5">
            <label class="form-label fw-medium mb-1">Quartier</label>
            <input type="text" name="quartier" class="form-control form-control-sm" value="<?= h($a['quartier'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label fw-medium mb-1">Parcelles</label>
            <textarea name="parcelles" class="form-control form-control-sm" rows="2"><?= h($a['parcelles'] ?? '') ?></textarea>
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label fw-medium mb-1">Surface (m2)</label>
            <input type="number" name="surface_m2" class="form-control form-control-sm" step="0.01" value="<?= h((string) ($a['surface_m2'] ?? '')) ?>" <?= (int) ($a['puisant'] ?? 0) === 1 ? 'disabled' : '' ?>>
          </div>
          <div class="col-6 col-md-4 d-flex align-items-end">
            <div class="form-check mb-2">
              <input type="checkbox" name="puisant" id="puisant" class="form-check-input" value="1" <?= (int) ($a['puisant'] ?? 0) === 1 ? 'checked' : '' ?>>
              <label for="puisant" class="form-check-label">Puisant</label>
            </div>
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label fw-medium mb-1">Cotisation</label>
            <input type="number" class="form-control form-control-sm" step="0.01" readonly value="<?= h((string) ($a['cotisation'] ?? '')) ?>">
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label fw-medium mb-1">Cotisation simul</label>
            <input type="number" class="form-control form-control-sm" step="0.01" readonly value="<?= h((string) ($a['cotisation_simul'] ?? '')) ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-success text-white">Notes</div>
      <div class="card-body">
        <textarea name="notes" class="form-control form-control-sm" rows="2"><?= h($a['notes'] ?? '') ?></textarea>
        <div class="form-check mt-2">
          <input type="checkbox" name="actif" id="actif" class="form-check-input" value="1" <?= $a['actif'] ? 'checked' : '' ?>>
          <label for="actif" class="form-check-label">Arrosant actif</label>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" name="action" value="save" class="btn btn-success btn-sm"><?= $isNew ? 'Creer' : 'Enregistrer' ?></button>
      <a href="<?= h($returnTo) ?>" class="btn btn-outline-secondary btn-sm">Annuler</a>
      <?php if (!$isNew): ?>
        <button
          type="submit"
          name="action"
          value="delete"
          class="btn btn-outline-danger btn-sm ms-auto"
          onclick="return confirm('Supprimer cet arrosant ? Cette action est irreversible.');"
        >Supprimer</button>
      <?php endif; ?>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var puisant = document.getElementById('puisant');
  var surface = document.querySelector('input[name="surface_m2"]');
  if (!puisant || !surface) {
    return;
  }

  function syncPuisantState() {
    surface.disabled = puisant.checked;
    if (puisant.checked) {
      surface.value = '';
    }
  }

  puisant.addEventListener('change', syncPuisantState);
  syncPuisantState();
});
</script>
</body>
</html>
