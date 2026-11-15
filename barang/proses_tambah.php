<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/koneksi.php';

wajib_login();

if (!is_post()) {
    redirect('barang/list.php');
}

// Tangani kasus ukuran unggahan melampaui post_max_size PHP (menyebabkan $_POST & $_FILES kosong)
if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash_set('error', 'Ukuran file melebihi batas maksimal 2 MB.');
    redirect('barang/tambah.php');
}

csrf_verifikasi();

$idKategori   = ambil_int('id_kategori', 'post');
$namaBarang   = trim($_POST['nama_barang'] ?? '');
$hargaRaw     = trim($_POST['harga_sewa_per_hari'] ?? '');
$stok         = ambil_int('stok', 'post') ?? 1;
$kondisiFisik = trim($_POST['kondisi_fisik'] ?? '');
$statusBarang = trim($_POST['status_barang'] ?? '');

// Simpan input lama agar form tidak kosong bila terjadi error validasi
$_SESSION['_old_barang'] = [
    'id_kategori'         => $idKategori,
    'nama_barang'         => $namaBarang,
    'harga_sewa_per_hari' => $hargaRaw,
    'stok'                => $stok,
    'kondisi_fisik'       => $kondisiFisik,
    'status_barang'       => $statusBarang,
];

// ── 1. Validasi sisi server ──────────────────────────────────────────────────
if ($namaBarang === '' || mb_strlen($namaBarang) > 100) {
    flash_set('error', 'Nama barang wajib diisi (maksimal 100 karakter).');
    redirect('barang/tambah.php');
}

if ($hargaRaw === '' || !is_numeric($hargaRaw) || (float) $hargaRaw < 0) {
    flash_set('error', 'Harga sewa per hari harus berupa angka minimal 0.');
    redirect('barang/tambah.php');
}
$hargaSewa = round((float) $hargaRaw, 2);

if ($stok < 0) {
    flash_set('error', 'Stok barang minimal 0.');
    redirect('barang/tambah.php');
}

$statusValid = ['aktif', 'rusak', 'maintenance', 'nonaktif'];
if (!in_array($statusBarang, $statusValid, true)) {
    flash_set('error', 'Status barang tidak valid.');
    redirect('barang/tambah.php');
}

if (mb_strlen($kondisiFisik) > 255) {
    flash_set('error', 'Kondisi fisik maksimal 255 karakter.');
    redirect('barang/tambah.php');
}
$kondisiDb = ($kondisiFisik !== '') ? $kondisiFisik : null;

if ($idKategori === null) {
    flash_set('error', 'Kategori barang wajib dipilih.');
    redirect('barang/tambah.php');
}

try {
    // Pastikan kategori benar-benar ada di database
    $stmtKat = $pdo->prepare('SELECT 1 FROM kategori WHERE id_kategori = :id');
    $stmtKat->execute([':id' => $idKategori]);
    if (!$stmtKat->fetchColumn()) {
        flash_set('error', 'Kategori yang dipilih tidak ditemukan.');
        redirect('barang/tambah.php');
    }

    // ── 2. Proses unggah foto bila ada ───────────────────────────────────────
    $pathFoto = null;
    if (isset($_FILES['foto_barang']) && $_FILES['foto_barang']['error'] !== UPLOAD_ERR_NO_FILE) {
        $namaUnik = $namaBarang . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $pathFoto = simpan_foto_barang($_FILES['foto_barang'], $namaUnik);
    }

    // ── 3. Simpan ke tabel barang ────────────────────────────────────────────
    $stmtIns = $pdo->prepare('
        INSERT INTO barang (id_kategori, nama_barang, foto_barang, kondisi_fisik, harga_sewa_per_hari, stok, status_barang)
        VALUES (:id_kategori, :nama_barang, :foto_barang, :kondisi_fisik, :harga_sewa_per_hari, :stok, :status_barang)
    ');
    $stmtIns->execute([
        ':id_kategori'         => $idKategori,
        ':nama_barang'         => $namaBarang,
        ':foto_barang'         => $pathFoto,
        ':kondisi_fisik'       => $kondisiDb,
        ':harga_sewa_per_hari' => $hargaSewa,
        ':stok'                => $stok,
        ':status_barang'       => $statusBarang,
    ]);

    unset($_SESSION['_old_barang']);
    flash_set('sukses', 'Barang "' . $namaBarang . '" berhasil ditambahkan ke inventaris.');
    redirect('barang/list.php');
} catch (RuntimeException $e) {
    flash_set('error', $e->getMessage());
    redirect('barang/tambah.php');
} catch (PDOException $e) {
    // Hapus file foto yang terlanjur tersimpan bila query DB gagal
    if (!empty($pathFoto)) {
        $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pathFoto);
        if (is_file($fullPath)) @unlink($fullPath);
    }
    error_log('[SnapRent] Error barang/proses_tambah.php: ' . $e->getMessage());
    flash_set('error', 'Gagal menyimpan data barang ke database. Silakan coba lagi.');
    redirect('barang/tambah.php');
}
