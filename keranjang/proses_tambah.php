<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan.']);
    exit;
}

$idBarang = filter_input(INPUT_POST, 'id_barang', FILTER_VALIDATE_INT);
$jumlah   = filter_input(INPUT_POST, 'jumlah', FILTER_VALIDATE_INT) ?: 1;
$mode     = $_POST['mode'] ?? 'tambah'; // 'tambah' atau 'set'

if (!$idBarang || $jumlah < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Data barang tidak valid.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id_barang, nama_barang, harga_sewa_per_hari, stok, foto_barang 
                           FROM barang 
                           WHERE id_barang = :id AND status_barang = 'aktif'");
    $stmt->execute([':id' => $idBarang]);
    $barang = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$barang) {
        echo json_encode(['status' => 'error', 'message' => 'Kamera/alat tidak ditemukan atau sedang nonaktif.']);
        exit;
    }

    if (!isset($_SESSION['keranjang'])) {
        $_SESSION['keranjang'] = [];
    }

    $qtySaatIni = $_SESSION['keranjang'][$idBarang]['jumlah'] ?? 0;
    $qtyBaru    = ($mode === 'set') ? $jumlah : ($qtySaatIni + $jumlah);

    if ($qtyBaru > (int)$barang['stok']) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Stok tersedia hanya ' . $barang['stok'] . ' unit.'
        ]);
        exit;
    }

    $_SESSION['keranjang'][$idBarang] = [
        'id_barang'           => (int)$barang['id_barang'],
        'nama_barang'         => $barang['nama_barang'],
        'harga_sewa_per_hari' => (float)$barang['harga_sewa_per_hari'],
        'foto_barang'         => $barang['foto_barang'],
        'stok'                => (int)$barang['stok'],
        'jumlah'              => $qtyBaru
    ];

    $totalItem = array_sum(array_column($_SESSION['keranjang'], 'jumlah'));

    echo json_encode([
        'status'     => 'success',
        'message'    => $barang['nama_barang'] . ' ditambahkan ke keranjang.',
        'total_item' => $totalItem
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Kesalahan database: ' . $e->getMessage()]);
}