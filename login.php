<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

sessionStart();

// Déjà connecté → accueil
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';
$success = '';
$step = 'email'; // 'email' ou 'code' — on passe par verify.php pour le code

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(strtolower($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez saisir une adresse email valide.';
    } else {
        // Vérifier que l'email existe et est actif
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM utilisateurs WHERE email = ? AND actif = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Cette adresse email n\'est pas reconnue.';
        } else {
            try {
                $code = createOTP($email);
                $sent = sendOTPEmail($email, $user['prenom'] . ' ' . $user['nom'], $code);

                if ($sent) {
                    // Stocker l'email en session pour verify.php
                    $_SESSION['otp_email'] = $email;
                    header('Location: ' . BASE_URL . '/verify.php');
                    exit;
                } else {
                    $error = 'Impossible d\'envoyer l\'email. Vérifiez la configuration SMTP.';
                }
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
  <div class="row justify-content-center mt-5">
    <div class="col-md-5 col-lg-4">

      <div class="text-center mb-4">
        <div class="logo-icon mb-2">🌿</div>
        <h1 class="h4 fw-bold text-success"><?= APP_NAME ?></h1>
        <p class="text-muted small">Association Syndicale Autorisée du Paillon</p>
      </div>

      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <h2 class="h6 fw-semibold mb-3 text-center">Connexion</h2>
          <p class="text-muted small text-center mb-4">
            Saisissez votre adresse email. Vous recevrez un code de connexion à 6 chiffres.
          </p>

          <?php if ($error): ?>
            <div class="alert alert-danger py-2 small">❌ <?= h($error) ?></div>
          <?php endif; ?>

          <form method="POST" action="">
            <div class="mb-3">
              <label for="email" class="form-label fw-medium">Adresse email</label>
              <input type="email" class="form-control" id="email" name="email"
                     placeholder="votre@email.fr" required autofocus
                     value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-success">
                Recevoir mon code →
              </button>
            </div>
          </form>
        </div>
      </div>

      <p class="text-center text-muted small mt-3">
        Problème de connexion ? Contactez l'administrateur.
      </p>

    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
