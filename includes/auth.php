<?php
// Demarre la session si pas deja active
function sessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// Verifie que l'utilisateur est connecte, sinon redirige
function requireLogin(): void {
    sessionStart();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// Verifie un role minimum (lecteur < editeur < admin)
function requireRole(string $minRole): void {
    requireLogin();
    $hierarchy = ['lecteur' => 1, 'editeur' => 2, 'admin' => 3];
    $userLevel = $hierarchy[$_SESSION['user_role']] ?? 0;
    $minLevel  = $hierarchy[$minRole] ?? 99;
    if ($userLevel < $minLevel) {
        http_response_code(403);
        die('<p>Acces refuse. Vous n\'avez pas les droits necessaires.</p>');
    }
}

// Retourne true si l'utilisateur a au moins le role donne
function hasRole(string $minRole): bool {
    $hierarchy = ['lecteur' => 1, 'editeur' => 2, 'admin' => 3];
    $userLevel = $hierarchy[$_SESSION['user_role'] ?? ''] ?? 0;
    $minLevel  = $hierarchy[$minRole] ?? 99;
    return $userLevel >= $minLevel;
}

// Genere un code OTP a 6 chiffres
function generateOTP(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Cree et stocke un code OTP en base, retourne le code
function createOTP(string $email): string {
    $db = getDB();

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM otp_codes
         WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE) AND used = 0'
    );
    $stmt->execute([$email, OTP_VALIDITY_MINUTES]);
    if ((int) $stmt->fetchColumn() >= OTP_MAX_ATTEMPTS) {
        throw new RuntimeException('Trop de tentatives. Attendez ' . OTP_VALIDITY_MINUTES . ' minutes avant de reessayer.');
    }

    $db->prepare('UPDATE otp_codes SET used = 1 WHERE email = ? AND used = 0')->execute([$email]);

    $code = generateOTP();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_VALIDITY_MINUTES . ' minutes'));
    $stmt = $db->prepare('INSERT INTO otp_codes (email, code, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$email, $code, $expiresAt]);

    return $code;
}

// Verifie un code OTP. Retourne l'utilisateur si valide, null sinon.
function verifyOTP(string $email, string $code): ?array {
    $db = getDB();

    $stmt = $db->prepare(
        'SELECT id FROM otp_codes
         WHERE email = ? AND code = ? AND used = 0 AND expires_at > NOW()
         ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute([$email, $code]);
    $otp = $stmt->fetch();

    if (!$otp) {
        return null;
    }

    $db->prepare('UPDATE otp_codes SET used = 1 WHERE id = ?')->execute([$otp['id']]);

    $stmt = $db->prepare('SELECT * FROM utilisateurs WHERE email = ? AND actif = 1');
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

// Connecte l'utilisateur
function loginUser(array $user): void {
    sessionStart();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_nom'] = $user['prenom'] . ' ' . $user['nom'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
}

// Deconnecte l'utilisateur
function logoutUser(): void {
    sessionStart();
    $_SESSION = [];
    session_destroy();
}

// Enregistre une action dans le log
function logAction(string $action, string $detail = ''): void {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO sessions_log (utilisateur_id, action, detail, ip) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $detail,
            $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Exception $e) {
        // log silencieux, ne pas bloquer l'appli
    }
}
