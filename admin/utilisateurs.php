<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$db    = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $uid = (int) ($_POST['uid'] ?? 0);
    if ($uid === (int) $_SESSION['user_id']) {
        flashSet('danger', 'Vous ne pouvez pas modifier votre propre compte ici.');
    } elseif ($_POST['action'] === 'toggle') {
        $db->prepare('UPDATE utilisateurs SET actif = 1 - actif WHERE id = ?')->execute([$uid]);
        flashSet('success', 'Statut mis a jour.');
    } elseif ($_POST['action'] === 'delete') {
        $db->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$uid]);
        flashSet('success', 'Utilisateur supprime.');
    } elseif ($_POST['action'] === 'save') {
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email  = trim(strtolower($_POST['email'] ?? ''));
        $tel    = trim($_POST['telephone'] ?? '');
        $role   = in_array($_POST['role'] ?? '', ['lecteur', 'editeur', 'admin']) ? $_POST['role'] : 'lecteur';

        if (!$nom || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Nom et email valide sont obligatoires.';
        } else {
            if ($uid === 0) {
                try {
                    $stmt = $db->prepare('INSERT INTO utilisateurs (nom, prenom, email, telephone, role) VALUES (?,?,?,?,?)');
                    $stmt->execute([$nom, $prenom, $email, $tel, $role]);
                    flashSet('success', "Utilisateur $prenom $nom cree.");
                } catch (PDOException $e) {
                    $error = 'Cet email est deja utilise.';
                }
            } else {
                $stmt = $db->prepare('UPDATE utilisateurs SET nom=?, prenom=?, email=?, telephone=?, role=? WHERE id=?');
                $stmt->execute([$nom, $prenom, $email, $tel, $role, $uid]);
                flashSet('success', 'Utilisateur mis a jour.');
            }
        }
    }

    if (!$error) {
        header('Location: ' . BASE_URL . '/admin/utilisateurs.php');
        exit;
    }
}

$editUser = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM utilisateurs WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editUser = $stmt->fetch();
}

$utilisateurs = $db->query('SELECT * FROM utilisateurs ORDER BY nom, prenom')->fetchAll();
$roles = ['lecteur' => 'Lecteur', 'editeur' => 'Editeur', 'admin' => 'Admin'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Utilisateurs - <?= APP_NAME ?></title>
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
      <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-light btn-sm">Deconnexion</a>
    </div>
  </div>
</nav>

<div class="container py-4" style="max-width: 900px;">
  <?= flashRender() ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

  <h1 class="h4 fw-bold mb-4">Gestion des utilisateurs</h1>

  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-success text-white">
      <?= $editUser ? 'Modifier : ' . h($editUser['prenom'] . ' ' . $editUser['nom']) : 'Nouvel utilisateur' ?>
    </div>
    <div class="card-body">
      <form method="POST" action="">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="uid" value="<?= $editUser ? (int) $editUser['id'] : 0 ?>">
        <div class="row g-3">
          <div class="col-12 col-sm-4">
            <label class="form-label fw-medium">Prenom</label>
            <input type="text" name="prenom" class="form-control" value="<?= h($editUser['prenom'] ?? '') ?>">
          </div>
          <div class="col-12 col-sm-4">
            <label class="form-label fw-medium">Nom *</label>
            <input type="text" name="nom" class="form-control" required value="<?= h($editUser['nom'] ?? '') ?>">
          </div>
          <div class="col-12 col-sm-4">
            <label class="form-label fw-medium">Role</label>
            <select name="role" class="form-select">
              <?php foreach ($roles as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($editUser['role'] ?? 'lecteur') === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-sm-5">
            <label class="form-label fw-medium">Email *</label>
            <input type="email" name="email" class="form-control" required value="<?= h($editUser['email'] ?? '') ?>">
          </div>
          <div class="col-12 col-sm-4">
            <label class="form-label fw-medium">Telephone</label>
            <input type="tel" name="telephone" class="form-control" value="<?= h($editUser['telephone'] ?? '') ?>">
          </div>
          <div class="col-12 col-sm-3 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-success flex-grow-1"><?= $editUser ? 'Modifier' : 'Creer' ?></button>
            <?php if ($editUser): ?>
              <a href="<?= BASE_URL ?>/admin/utilisateurs.php" class="btn btn-outline-secondary">Annuler</a>
            <?php endif; ?>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-success">
          <tr>
            <th>Nom</th>
            <th>Email</th>
            <th>Role</th>
            <th class="text-center">Statut</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($utilisateurs as $u): ?>
          <tr class="<?= !$u['actif'] ? 'opacity-50' : '' ?>">
            <td class="fw-medium"><?= h($u['prenom'] . ' ' . $u['nom']) ?></td>
            <td class="text-muted small"><?= h($u['email']) ?></td>
            <td>
              <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : ($u['role'] === 'editeur' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                <?= $roles[$u['role']] ?? $u['role'] ?>
              </span>
            </td>
            <td class="text-center">
              <?= $u['actif'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-secondary">Inactif</span>' ?>
            </td>
            <td class="text-center">
              <div class="d-flex gap-1 justify-content-center">
                <a href="?edit=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-secondary py-0">Edit</a>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="uid" value="<?= (int) $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-warning py-0"><?= $u['actif'] ? 'Pause' : 'Activer' ?></button>
                </form>
                <?php if ((int) $u['id'] !== (int) $_SESSION['user_id']): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer definitivement cet utilisateur ?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="uid" value="<?= (int) $u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger py-0">Suppr</button>
                </form>
                <?php endif; ?>
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
