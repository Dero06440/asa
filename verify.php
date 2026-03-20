<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

sessionStart();

if (empty($_SESSION['otp_email'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$email = $_SESSION['otp_email'];
$error = '';
$debugOtp = '';

if (defined('APP_DEBUG_OTP') && APP_DEBUG_OTP) {
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT code FROM otp_codes WHERE email = ? AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute([$email]);
    $debugOtp = (string) ($stmt->fetchColumn() ?: '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    if (!preg_match('/^\d{6}$/', $code)) {
        $error = 'Le code doit contenir exactement 6 chiffres.';
    } else {
        $user = verifyOTP($email, $code);
        if ($user) {
            unset($_SESSION['otp_email']);
            loginUser($user);
            logAction('login', 'Connexion reussie');
            flashSet('success', 'Bienvenue, ' . $user['prenom'] . ' ' . $user['nom'] . ' !');
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        } else {
            $error = 'Code invalide ou expire. Verifiez votre email ou recommencez.';
            logAction('login_failed', 'Code OTP invalide pour ' . $email);
        }
    }
}

$emailDisplay = preg_replace('/(?<=.{2}).(?=.*@)/u', '*', $email);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Code de verification - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
  <div class="row justify-content-center mt-5">
    <div class="col-md-5 col-lg-4">

      <div class="text-center mb-4">
        <div class="logo-icon mb-2">ASA</div>
        <h1 class="h4 fw-bold text-success"><?= APP_NAME ?></h1>
      </div>

      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <h2 class="h6 fw-semibold mb-3 text-center">Verification</h2>
          <p class="text-muted small text-center mb-4">
            Un code a 6 chiffres a ete envoye a<br>
            <strong><?= h($emailDisplay) ?></strong><br>
            <span class="text-muted">(valable <?= OTP_VALIDITY_MINUTES ?> minutes)</span>
          </p>

          <?php if ($debugOtp): ?>
            <div class="alert alert-warning py-2 small">
              Mode local : code de test actuel <strong><?= h($debugOtp) ?></strong>
            </div>
          <?php endif; ?>

          <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= h($error) ?></div>
          <?php endif; ?>

          <form method="POST" action="">
            <div class="mb-3">
              <label for="code" class="form-label fw-medium">Code de verification</label>
              <input type="text" class="form-control form-control-lg text-center fw-bold otp-input"
                     id="code" name="code" maxlength="6" pattern="\d{6}"
                     placeholder="_ _ _ _ _ _" required autofocus
                     autocomplete="one-time-code" inputmode="numeric">
            </div>
            <div class="d-grid mb-3">
              <button type="submit" class="btn btn-success">Valider</button>
            </div>
          </form>

          <div class="text-center">
            <a href="<?= BASE_URL ?>/login.php" class="text-muted small">
              Changer d'adresse email
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('code').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    if (this.value.length === 6) {
        this.closest('form').submit();
    }
});
</script>
</body>
</html>
