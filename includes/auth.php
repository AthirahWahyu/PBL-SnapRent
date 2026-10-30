<?php
// 1. Cek apakah session sudah berjalan atau belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Cek apakah tiket sesi 'admin' TIDAK ADA
if (!isset($_SESSION['admin'])) {
    // 3. Tendang (redirect) kembali ke halaman login
    header("Location: ../auth/login.php");
    
    // 4. Kunci mati eksekusi kode di bawahnya
    exit;
}
?>