<?php
/**
 * includes/csrf.php
 * Perlindungan CSRF sederhana berbasis token sesi.
 * File ini TIDAK boleh menampilkan output apa pun bila dibuka langsung.
 */

if (defined('_CSRF_LOADED')) return;
define('_CSRF_LOADED', true);

// ── Token ─────────────────────────────────────────────────────────────────────

/**
 * Dapatkan (atau buat) token CSRF untuk sesi ini.
 * Token baru dibuat sekali per sesi dan disimpan di $_SESSION.
 */
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Hasilkan input hidden HTML yang membawa token CSRF.
 * Gunakan di dalam setiap <form method="POST">.
 *
 * Contoh: <?= csrf_field() ?>
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
}

// ── Verifikasi ────────────────────────────────────────────────────────────────

/**
 * Verifikasi token CSRF dari request POST.
 * Bila gagal: set flash error, lalu redirect ke halaman sebelumnya (Referer)
 * atau ke dashboard, kemudian hentikan eksekusi.
 *
 * Panggil di awal setiap script proses_*.php setelah is_post().
 */
function csrf_verifikasi(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    $tokenDikirim = $_POST['_csrf_token'] ?? '';
    $tokenSesi    = $_SESSION['_csrf_token'] ?? '';

    // hash_equals mencegah timing attack
    if (!$tokenSesi || !hash_equals($tokenSesi, $tokenDikirim)) {
        // Muat helpers bila belum dimuat (urutan include bervariasi)
        if (!function_exists('flash_set')) {
            require_once __DIR__ . '/helpers.php';
        }
        flash_set('error', 'Permintaan tidak valid (token keamanan tidak cocok). Silakan coba lagi.');

        $kembali = $_SERVER['HTTP_REFERER'] ?? '';
        // Pastikan referer berasal dari domain yang sama untuk keamanan
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($kembali === '' || !str_contains($kembali, $host)) {
            $kembali = base_url('dashboard/index.php');
        }
        header('Location: ' . $kembali);
        exit;
    }
}
