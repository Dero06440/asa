<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('editeur');

$db = getDB();
$totalWithAddress = (int) $db->query(
    "SELECT COUNT(*) FROM arrosants
     WHERE actif = 1
       AND nom <> ''
       AND rue IS NOT NULL AND TRIM(rue) <> ''
       AND code_postal IS NOT NULL AND TRIM(code_postal) <> ''
       AND ville IS NOT NULL AND TRIM(ville) <> ''"
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Imprimer - <?= APP_NAME ?></title>
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
  <h1 class="h4 fw-bold mb-4">Imprimer</h1>

  <div class="row g-3">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title mb-2">Enveloppes</h5>
          <p class="card-text text-muted small mb-3">
            Genere un PDF avec une enveloppe par page pour les arrosants actifs ayant une adresse postale complete.
          </p>
          <p class="small mb-3">
            <span class="badge bg-success"><?= $totalWithAddress ?></span>
            destinataire<?= $totalWithAddress > 1 ? 's' : '' ?> imprimable<?= $totalWithAddress > 1 ? 's' : '' ?>
          </p>
          <a href="<?= BASE_URL ?>/admin/print_envelopes.php" class="btn btn-success" target="_blank" rel="noopener">Generer le PDF</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
