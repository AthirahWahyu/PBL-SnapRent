<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';

// Hanya terima POST
if (!is_post()) {
    redirect('dashboard/index.php');
}

// Verifikasi CSRF
csrf_verifikasi();

// Kosongkan data session
$_SESSION = [];

// Hapus cookie session dari browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Strict',
        ]
    );
}

session_destroy();

redirect('auth/login.php');