<?php
/**
 * includes/auth.php
 * Manajemen sesi admin: start session, perlindungan cookie, guard login.
 * File ini TIDAK boleh menampilkan output apa pun bila dibuka langsung.
 *
 * Cara pakai: require_once __DIR__ . '/../includes/auth.php';
 *             wajib_login();   // letakkan di baris atas halaman admin
 */

if (defined('_AUTH_LOADED')) return;
define('_AUTH_LOADED', true);

// ── Session ───────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,           // tutup browser = sesi habis
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,       // ubah ke true bila menggunakan HTTPS
        'httponly' => true,        // JS tidak bisa baca cookie
        'samesite' => 'Strict',    // proteksi CSRF tambahan di level cookie
    ]);
    session_start();
}

// ── Guard ─────────────────────────────────────────────────────────────────────

/**
 * Paksa halaman hanya dapat diakses oleh admin yang sudah login.
 * Bila belum login, redirect ke halaman login dan hentikan eksekusi.
 */
function wajib_login(): void
{
    if (empty($_SESSION['admin'])) {
        // Pastikan helpers sudah dimuat untuk base_url()
        if (!function_exists('base_url')) {
            require_once __DIR__ . '/helpers.php';
        }
        header('Location: ' . base_url('auth/login.php'));
        exit;
    }
}

// ── Info Admin ────────────────────────────────────────────────────────────────

/**
 * Kembalikan data admin yang sedang login sebagai array asosiatif.
 * Keys: id_admin, username, nama_lengkap
 * Kembalikan null bila belum login.
 */
function admin_saat_ini(): array|null
{
    return $_SESSION['admin'] ?? null;
}
