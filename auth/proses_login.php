<?php
/**
 * auth/proses_login.php
 * Memproses POST dari form login admin.
 * Fitur:
 * - Hanya menerima POST + verifikasi CSRF
 * - Batas percobaan: 5 kali gagal berturut-turut dalam satu session -> blokir 60 detik
 * - Pesan gagal generik ("Username atau password salah")
 * - session_regenerate_id(true) saat berhasil login
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/koneksi.php';

// Hanya terima POST
if (!is_post()) {
    redirect('auth/login.php');
}

// Verifikasi CSRF
csrf_verifikasi();

// ── Cek apakah sesi sedang dalam masa blokir 60 detik ─────────────────────────
if (!empty($_SESSION['login_blocked_until'])) {
    $sisaDetik = (int) $_SESSION['login_blocked_until'] - time();
    if ($sisaDetik > 0) {
        flash_set('error', "Terlalu banyak percobaan gagal. Silakan tunggu {$sisaDetik} detik lagi.");
        redirect('auth/login.php');
    }
    // Masa blokir sudah lewat, reset penghitung
    unset($_SESSION['login_blocked_until']);
    $_SESSION['login_attempts'] = 0;
}

$username = trim($_POST['username'] ?? '');
$password = (string) ($_POST['password'] ?? '');

/**
 * Catat kegagalan login ke session dan blokir 60 detik bila sudah 5 kali berturut-turut.
 */
function _catat_gagal_login(): never
{
    $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;

    if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['login_blocked_until'] = time() + 60;
    }

    flash_set('error', 'Username atau password salah');
    redirect('auth/login.php');
}

if ($username === '' || $password === '') {
    _catat_gagal_login();
}

try {
    $stmt = $pdo->prepare('SELECT id_admin, username, password, nama_lengkap FROM admin WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        // Reset catatan kegagalan login
        unset($_SESSION['login_attempts'], $_SESSION['login_blocked_until']);

        // Regenerasi session ID untuk mencegah session fixation
        session_regenerate_id(true);

        // Simpan data admin di session (kompatibel dengan auth.php & akses langsung)
        $_SESSION['id_admin']     = (int) $admin['id_admin'];
        $_SESSION['nama_lengkap'] = $admin['nama_lengkap'];
        $_SESSION['admin']        = [
            'id_admin'     => (int) $admin['id_admin'],
            'username'     => $admin['username'],
            'nama_lengkap' => $admin['nama_lengkap'],
        ];

        redirect('dashboard/index.php');
    }

    _catat_gagal_login();
} catch (PDOException $e) {
    error_log('[SnapRent] Login error: ' . $e->getMessage());
    flash_set('error', 'Terjadi kesalahan pada sistem. Coba lagi nanti.');
    redirect('auth/login.php');
}

