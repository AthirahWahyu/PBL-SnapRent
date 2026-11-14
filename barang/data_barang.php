<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/koneksi.php';

wajib_login();

// ── Parameter Filter & Paginasi ─────────────────────────────────────────────
$cari       = trim($_GET['q'] ?? '');
$idKategori = ambil_int('kategori', 'get');
$status     = trim($_GET['status'] ?? '');
$halaman    = ambil_int('page', 'get') ?? 1;
$perHalaman = 20;

$statusValid = ['aktif', 'rusak', 'maintenance', 'nonaktif'];
if (!in_array($status, $statusValid, true)) {
    $status = '';
}

try {
    // 1. Ambil opsi kategori untuk filter dropdown
    $daftarKategori = $pdo->query('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori ASC')->fetchAll();

    // 2. Susun klausa WHERE dinamis
    $where  = [];
    $params = [];

    if ($cari !== '') {
        $where[]         = 'b.nama_barang ILIKE :cari';
        $params[':cari'] = '%' . $cari . '%';
    }
    if ($idKategori !== null) {
        $where[]                = 'b.id_kategori = :id_kategori';
        $params[':id_kategori'] = $idKategori;
    }
    if ($status !== '') {
        $where[]           = 'b.status_barang = :status';
        $params[':status'] = $status;
    }

    $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

    // 3. Hitung total baris untuk kebutuhan paginasi
    $stmtTotal = $pdo->prepare("
        SELECT COUNT(*)
        FROM barang b
        JOIN kategori k ON k.id_kategori = b.id_kategori
        $whereSql
    ");
    $stmtTotal->execute($params);
    $totalBaris   = (int) $stmtTotal->fetchColumn();
    $totalHalaman = max(1, (int) ceil($totalBaris / $perHalaman));

    if ($halaman > $totalHalaman) {
        $halaman = $totalHalaman;
    }
    $offset = ($halaman - 1) * $perHalaman;

    // 4. Ambil record inventaris barang halaman ini
    $sqlData = "
        SELECT
            b.id_barang,
            b.id_kategori,
            b.nama_barang,
            b.foto_barang,
            b.kondisi_fisik,
            b.harga_sewa_per_hari,
            b.stok,
            b.status_barang,
            k.nama_kategori
        FROM barang b
        JOIN kategori k ON k.id_kategori = b.id_kategori
        $whereSql
        ORDER BY b.id_barang DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmtData = $pdo->prepare($sqlData);
    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue(':limit', $perHalaman, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $daftarBarang = $stmtData->fetchAll();
} catch (PDOException $e) {
    error_log('[SnapRent] Error barang/data_barang.php: ' . $e->getMessage());
    $daftarKategori = [];
    $daftarBarang   = [];
    $totalBaris     = 0;
    $totalHalaman   = 1;
    $offset         = 0;
    flash_set('error', 'Gagal memuat data inventaris barang.');
}

/**
 * Buat query string untuk tautan paginasi dengan mempertahankan filter.
 */
function _url_halaman_barang(int $page, string $cari, ?int $idKategori, string $status): string
{
    $query = ['page' => $page];
    if ($cari !== '')         $query['q']        = $cari;
    if ($idKategori !== null) $query['kategori'] = $idKategori;
    if ($status !== '')       $query['status']   = $status;
    return base_url('barang/list.php?' . http_build_query($query));
}
