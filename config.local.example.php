<?php
if (APP_ENV === 'local') {
    define('DB_HOST', 'your-local-or-remote-db-host');
    define('DB_NAME', 'your_database_name');
    define('DB_USER', 'your_database_user');
    define('DB_PASS', 'your_database_password');

    define('SMTP_HOST', 'smtp.example.com');
    define('SMTP_PORT', 465);
    define('SMTP_SECURE', 'ssl');
    define('SMTP_USER', 'user@example.com');
    define('SMTP_PASS', 'your_smtp_password');
    define('SMTP_FROM', 'user@example.com');
    define('SMTP_FROM_NAME', 'ASA Arrosants et Riverains du Paillon');
    define('SMTP_ENABLED', false);
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'your_database_name');
    define('DB_USER', 'your_database_user');
    define('DB_PASS', 'your_database_password');

    define('SMTP_HOST', 'smtp.example.com');
    define('SMTP_PORT', 465);
    define('SMTP_SECURE', 'ssl');
    define('SMTP_USER', 'user@example.com');
    define('SMTP_PASS', 'your_smtp_password');
    define('SMTP_FROM', 'user@example.com');
    define('SMTP_FROM_NAME', 'ASA Arrosants et Riverains du Paillon');
    define('SMTP_ENABLED', true);
}
