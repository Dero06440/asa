<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
sessionStart();
logAction('logout', '');
logoutUser();
header('Location: ' . BASE_URL . '/login.php');
exit;
