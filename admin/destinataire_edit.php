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

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isNew = ($id === 0);
$error = '';

$destinataire = [
    'id' => 0,
    'nom' => '',
    'categorie' => '',
    'adresse_1' => '',
    'adresse_2' => '',
    'code_postal' => '',
    'ville' => '',
    'telephone' => '',
    'email' => '',
    'notes' => '',
];

if (!$isNew) {
    $stmt = $db->prepare('SELECT * FROM destinataires WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flashSet('danger', 'Destinataire introuvable.');
        header('Location: ' . BASE_URL . '/admin/destinataires.php');
        exit;
    }
    $destinataire = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete' && !$isNew) {
        $db->prepare('DELETE FROM destinataires WHERE id = ?')->execute([$id]);
        logAction('delete_destinataire', 'Destinataire supprime : ' . $destinataire['nom'] . ' (id ' . $id . ')');
        flashSet('success', 'Destinataire supprime.');
        header('Location: ' . BASE_URL . '/admin/destinataires.php');
        exit;
    }

    $fields = [
        'nom' => trim($_POST['nom'] ?? ''),
        'categorie' => trim($_POST['categorie'] ?? ''),
        'adresse_1' => trim($_POST['adresse_1'] ?? ''),
        'adresse_2' => trim($_POST['adresse_2'] ?? ''),
        'code_postal' => trim($_POST['code_postal'] ?? ''),
        'ville' => trim($_POST['ville'] ?? ''),
        'telephone' => trim($_POST['telephone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
    ];

    if ($fields['nom'] === '') {
        $error = 'Le nom est obligatoire.';
    } elseif ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'L email n est pas valide.';
    } else {
        if ($isNew) {
            $stmt = $db->prepare('INSERT INTO destinataires (nom, categorie, adresse_1, adresse_2, code_postal, ville, telephone, email, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $fields['nom'],
                $fields['categorie'],
                $fields['adresse_1'],
                $fields['adresse_2'],
                $fields['code_postal'],
                $fields['ville'],
                $fields['telephone'],
                $fields['email'],
                $fields['notes'],
            ]);
            logAction('create_destinataire', 'Destinataire cree : ' . $fields['nom']);
            flashSet('success', 'Destinataire cree.');
        } else {
            $stmt = $db->prepare('UPDATE destinataires SET nom=?, categorie=?, adresse_1=?, adresse_2=?, code_postal=?, ville=?, telephone=?, email=?, notes=? WHERE id=?');
            $stmt->execute([
                $fields['nom'],
                $fields['categorie'],
                $fields['adresse_1'],
                $fields['adresse_2'],
                $fields['code_postal'],
                $fields['ville'],
                $fields['telephone'],
                $fields['email'],
                $fields['notes'],
                $id,
            ]);
            logAction('edit_destinataire', 'Destinataire modifie : ' . $fields['nom'] . ' (id ' . $id . ')');
            flashSet('success', 'Destinataire mis a jour.');
        }

        header('Location: ' . BASE_URL . '/admin/destinataires.php');
        exit;
    }

    $destinataire = array_merge($destinataire, $fields, ['id' => $id]);
}

$pageTitle = $isNew ? 'Nouveau destinataire' : 'Modifier : ' . $destinataire['nom'];
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

<div class="container py-4" style="max-width: 850px;">
  <?= flashRender() ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

  <div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= BASE_URL ?>/admin/destinataires.php" class="text-muted text-decoration-none">Retour</a>
    <span class="text-muted">/</span>
    <h1 class="h5 mb-0 fw-bold"><?= h($pageTitle) ?></h1>
  </div>

  <form method="POST" action="">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-medium">Nom *</label>
            <input type="text" name="nom" class="form-control" required value="<?= h($destinataire['nom']) ?>">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label fw-medium">Categorie</label>
            <input type="text" name="categorie" class="form-control" value="<?= h($destinataire['categorie']) ?>">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label fw-medium">Adresse 1</label>
            <input type="text" name="adresse_1" class="form-control" value="<?= h($destinataire['adresse_1']) ?>">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label fw-medium">Adresse 2</label>
            <input type="text" name="adresse_2" class="form-control" value="<?= h($destinataire['adresse_2']) ?>">
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label fw-medium">Code postal</label>
            <input type="text" name="code_postal" class="form-control" value="<?= h($destinataire['code_postal']) ?>">
          </div>
          <div class="col-12 col-md-8">
            <label class="form-label fw-medium">Ville</label>
            <input type="text" name="ville" class="form-control" value="<?= h($destinataire['ville']) ?>">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label fw-medium">Telephone</label>
            <input type="text" name="telephone" class="form-control" value="<?= h($destinataire['telephone']) ?>">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label fw-medium">Email</label>
            <input type="email" name="email" class="form-control" value="<?= h($destinataire['email']) ?>">
          </div>
          <div class="col-12">
            <label class="form-label fw-medium">Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?= h($destinataire['notes']) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" name="action" value="save" class="btn btn-success"><?= $isNew ? 'Creer' : 'Enregistrer' ?></button>
      <a href="<?= BASE_URL ?>/admin/destinataires.php" class="btn btn-outline-secondary">Annuler</a>
      <?php if (!$isNew): ?>
        <a href="<?= BASE_URL ?>/admin/print_destinataire.php?id=<?= (int) $destinataire['id'] ?>" class="btn btn-outline-success ms-auto" target="_blank" rel="noopener">Imprimer enveloppe</a>
        <button type="submit" name="action" value="delete" class="btn btn-outline-danger" onclick="return confirm('Supprimer ce destinataire ?');">Supprimer</button>
      <?php endif; ?>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
