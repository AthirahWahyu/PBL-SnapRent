<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';

if (!is_post()) {
    kirim_json('error', 'Metode permintaan tidak diizinkan. Gunakan metode POST.', 405);
}

// Verifikasi token CSRF
$tokenDikirim = (string) ($_POST['_csrf_token'] ?? '');
$tokenSesi    = (string) ($_SESSION['_csrf_token'] ?? '');
if ($tokenSesi === '' || !hash_equals($tokenSesi, $tokenDikirim)) {
    kirim_json('error', 'Permintaan tidak valid (token keamanan tidak cocok). Silakan muat ulang halaman.');
}

$aksi      = trim((string) ($_POST['aksi'] ?? ''));
$kosongkan = ($aksi === 'kosongkan' || (isset($_POST['kosongkan']) && $_POST['kosongkan'] === '1'));

if ($kosongkan) {
    unset($_SESSION['keranjang']);
    kirim_json('ok', [
        'message'     => 'Keranjang berhasil dikosongkan.',
        'jumlah_item' => 0,
    ]);
}

$idBarang = ambil_int('id_barang', 'post');
if ($idBarang === null) {
    kirim_json('error', 'ID peralatan yang akan dihapus tidak valid.');
}

if (empty($_SESSION['keranjang']['items'][$idBarang])) {
    kirim_json('error', 'Peralatan tidak ditemukan di dalam keranjang.');
}

unset($_SESSION['keranjang']['items'][$idBarang]);

// Bila keranjang sudah tidak memiliki item lagi, bersihkan struktur keranjang
if (empty($_SESSION['keranjang']['items'])) {
    unset($_SESSION['keranjang']);
}

$jumlahItem = hitung_item_keranjang();

kirim_json('ok', [
    'message'     => 'Peralatan berhasil dihapus dari keranjang.',
    'id_barang'   => $idBarang,
    'jumlah_item' => $jumlahItem,
]);
