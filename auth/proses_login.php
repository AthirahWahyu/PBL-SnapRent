<?php
session_start();
// Memanggil koneksi database
require_once '../includes/koneksi.php';

// Pastikan data dikirim melalui metode POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Tangkap data dari form dan bersihkan spasi
    $username_input = trim($_POST['username']);
    $password_input = trim($_POST['password']);

    try {
        // 1. Cari admin berdasarkan username di database PostgreSQL
        $sql = "SELECT * FROM admin WHERE username = :username";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':username' => $username_input]);
        
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Verifikasi Username dan Password
        // Jika data admin ditemukan DAN password cocok dengan hash di database
        if ($admin && password_verify($password_input, $admin['password'])) {
            
            // Login Berhasil: Buat session untuk menyimpan data admin
            $_SESSION['admin'] = [
                'id_admin' => $admin['id_admin'],
                'username' => $admin['username'],
                'nama_lengkap' => $admin['nama_lengkap']
            ];
            
            // Arahkan ke halaman Dasbor
            header("Location: ../dashboard/index.php");
            exit;

        } else {
            // Login Gagal: Username tidak ada ATAU password salah
            $_SESSION['error'] = "Username atau Password salah!";
            header("Location: login.php");
            exit;
        }

    } catch (PDOException $e) {
        // Tangkap error database (jangan tampilkan ke user secara detail di production)
        $_SESSION['error'] = "Terjadi kesalahan pada sistem database.";
        header("Location: login.php");
        exit;
    }
} else {
    // Jika ada yang mencoba mengakses file ini langsung dari URL
    header("Location: login.php");
    exit;
}
?>