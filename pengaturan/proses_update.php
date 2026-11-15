<?php
require_once __DIR__ . '/../includes/auth.php';
wajib_login();

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';

if (!is_post()) {
    flash_set('error', 'Metode request tidak diizinkan.');
    redirect('pengaturan/index.php');
}

csrf_verifikasi();

$admin_sesi          = admin_saat_ini();
$id_admin            = (int) ($admin_sesi['id_admin'] ?? 0);
$password_lama       = (string) ($_POST['password_lama'] ?? '');
$password_baru       = (string) ($_POST['password_baru'] ?? '');
$konfirmasi_password = (string) ($_POST['konfirmasi_password'] ?? '');

if ($id_admin <= 0) {
    flash_set('error', 'Sesi admin tidak valid. Silakan login kembali.');
    redirect('auth/login.php');
}

if ($password_lama === '' || $password_baru === '' || $konfirmasi_password === '') {
    flash_set('error', 'Semua kolom password wajib diisi.');
    redirect('pengaturan/index.php');
}

if (strlen($password_baru) < 8) {
    flash_set('error', 'Password baru minimal harus terdiri dari 8 karakter.');
    redirect('pengaturan/index.php');
}

if ($password_baru !== $konfirmasi_password) {
    flash_set('error', 'Konfirmasi password baru tidak sama dengan password baru.');
    redirect('pengaturan/index.php');
}

try {
    // 1. Ambil hash password lama dari database
    $stmtCek = $pdo->prepare('SELECT id_admin, password FROM admin WHERE id_admin = :id');
    $stmtCek->execute([':id' => $id_admin]);
    $dataAdmin = $stmtCek->fetch(PDO::FETCH_ASSOC);

    if (!$dataAdmin) {
        flash_set('error', 'Data akun admin tidak ditemukan.');
        redirect('pengaturan/index.php');
    }

    // 2. Verifikasi password lama dengan password_verify
    if (!password_verify($password_lama, (string) $dataAdmin['password'])) {
        flash_set('error', 'Password lama yang Anda masukkan salah.');
        redirect('pengaturan/index.php');
    }

    // 3. Simpan password baru dengan password_hash
    $hashBaru = password_hash($password_baru, PASSWORD_DEFAULT);
    $stmtUpd  = $pdo->prepare('UPDATE admin SET password = :password WHERE id_admin = :id');
    $stmtUpd->execute([
        ':password' => $hashBaru,
        ':id'       => $id_admin,
    ]);

    // 4. Regenerasi session ID untuk keamanan setelah perubahan kredensial
    session_regenerate_id(true);

    flash_set('sukses', 'Password akun admin berhasil diperbarui.');
    redirect('pengaturan/index.php');

} catch (PDOException) {
    flash_set('error', 'Terjadi kesalahan saat menyimpan password baru. Silakan coba lagi.');
    redirect('pengaturan/index.php');
}
