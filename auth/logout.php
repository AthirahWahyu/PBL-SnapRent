<?php
// 1. Memulai atau memanggil sesi yang sedang berjalan
session_start();

// 2. Menghapus semua variabel sesi yang terdaftar (seperti $_SESSION['admin'])
session_unset();

// 3. Menghancurkan sesi sepenuhnya dari server
session_destroy();

// 4. Mengarahkan paksa pengguna kembali ke halaman login
header("Location: login.php");
exit;
?>