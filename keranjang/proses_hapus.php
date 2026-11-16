<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan.']);
    exit;
}

$aksi     = $_POST['aksi'] ?? 'hapus_item'; // 'hapus_item', 'kurangi', 'kosongkan'
$idBarang = filter_input(INPUT_POST, 'id_barang', FILTER_VALIDATE_INT);

if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

if ($aksi === 'kosongkan') {
    $_SESSION['keranjang'] = [];
} elseif ($idBarang && isset($_SESSION['keranjang'][$idBarang])) {
    if ($aksi === 'kurangi' && $_SESSION['keranjang'][$idBarang]['jumlah'] > 1) {
        $_SESSION['keranjang'][$idBarang]['jumlah'] -= 1;
    } else {
        unset($_SESSION['keranjang'][$idBarang]);
    }
}

$totalItem = array_sum(array_column($_SESSION['keranjang'], 'jumlah'));

echo json_encode([
    'status'     => 'success',
    'message'    => 'Keranjang berhasil diperbarui.',
    'total_item' => $totalItem
]);