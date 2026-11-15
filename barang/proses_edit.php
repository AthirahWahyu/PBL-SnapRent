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

// Tangani kasus ukuran unggahan melampaui post_max_size PHP
if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash_set('error', 'Ukuran file melebihi batas maksimal 2 MB.');
    $kembali = $_SERVER['HTTP_REFERER'] ?? base_url('barang/list.php');
    header('Location: ' . $kembali);
    exit;
}

csrf_verifikasi();

$idBarang     = ambil_int('id_barang', 'post');
$idKategori   = ambil_int('id_kategori', 'post');
$namaBarang   = trim($_POST['nama_barang'] ?? '');
$hargaRaw     = trim($_POST['harga_sewa_per_hari'] ?? '');
$stok         = ambil_int('stok', 'post') ?? 1;
$kondisiFisik = trim($_POST['kondisi_fisik'] ?? '');
$statusBarang = trim($_POST['status_barang'] ?? '');

if ($idBarang === null) {
    flash_set('error', 'ID barang tidak valid.');
    redirect('barang/list.php');
}

$urlEdit = 'barang/edit.php?id=' . $idBarang;

// ── 1. Validasi sisi server ──────────────────────────────────────────────────
if ($namaBarang === '' || mb_strlen($namaBarang) > 100) {
    flash_set('error', 'Nama barang wajib diisi (maksimal 100 karakter).');
    redirect($urlEdit);
}

if ($hargaRaw === '' || !is_numeric($hargaRaw) || (float) $hargaRaw < 0) {
    flash_set('error', 'Harga sewa per hari harus berupa angka minimal 0.');
    redirect($urlEdit);
}
$hargaSewa = round((float) $hargaRaw, 2);

if ($stok < 0) {
    flash_set('error', 'Stok barang minimal 0.');
    redirect($urlEdit);
}

$statusValid = ['aktif', 'rusak', 'maintenance', 'nonaktif'];
if (!in_array($statusBarang, $statusValid, true)) {
    flash_set('error', 'Status barang tidak valid.');
    redirect($urlEdit);
}

if (mb_strlen($kondisiFisik) > 255) {
    flash_set('error', 'Kondisi fisik maksimal 255 karakter.');
    redirect($urlEdit);
}
$kondisiDb = ($kondisiFisik !== '') ? $kondisiFisik : null;

if ($idKategori === null) {
    flash_set('error', 'Kategori barang wajib dipilih.');
    redirect($urlEdit);
}

$fotoBaru = null;

try {
    // Pastikan barang ada
    $stmtLama = $pdo->prepare('SELECT id_barang, foto_barang FROM barang WHERE id_barang = :id LIMIT 1');
    $stmtLama->execute([':id' => $idBarang]);
    $barangLama = $stmtLama->fetch();

    if (!$barangLama) {
        flash_set('error', 'Data barang tidak ditemukan.');
        redirect('barang/list.php');
    }

    // Pastikan kategori ada
    $stmtKat = $pdo->prepare('SELECT 1 FROM kategori WHERE id_kategori = :id');
    $stmtKat->execute([':id' => $idKategori]);
    if (!$stmtKat->fetchColumn()) {
        flash_set('error', 'Kategori yang dipilih tidak ditemukan.');
        redirect($urlEdit);
    }

    $fotoLama  = trim((string) ($barangLama['foto_barang'] ?? ''));
    $fotoAkhir = ($fotoLama !== '') ? $fotoLama : null;

    // ── 2. Bila ada unggahan foto baru, simpan lewat includes/upload.php ─────
    if (isset($_FILES['foto_barang']) && $_FILES['foto_barang']['error'] !== UPLOAD_ERR_NO_FILE) {
        $namaUnik  = $namaBarang . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $fotoBaru  = simpan_foto_barang($_FILES['foto_barang'], $namaUnik);
        $fotoAkhir = $fotoBaru;
    }

    // ── 3. Update baris di tabel barang ──────────────────────────────────────
    $stmtUpd = $pdo->prepare('
        UPDATE barang
           SET id_kategori         = :id_kategori,
               nama_barang         = :nama_barang,
               foto_barang         = :foto_barang,
               kondisi_fisik       = :kondisi_fisik,
               harga_sewa_per_hari = :harga_sewa_per_hari,
               stok                = :stok,
               status_barang       = :status_barang
         WHERE id_barang = :id_barang
    ');
    $stmtUpd->execute([
        ':id_kategori'         => $idKategori,
        ':nama_barang'         => $namaBarang,
        ':foto_barang'         => $fotoAkhir,
        ':kondisi_fisik'       => $kondisiDb,
        ':harga_sewa_per_hari' => $hargaSewa,
        ':stok'                => $stok,
        ':status_barang'       => $statusBarang,
        ':id_barang'           => $idBarang,
    ]);

    // ── 4. Bila foto diganti, hapus file foto lama dari disk ─────────────────
    if ($fotoBaru !== null && $fotoLama !== '' && $fotoLama !== $fotoBaru) {
        $pathLama = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fotoLama);
        if (is_file($pathLama)) {
            @unlink($pathLama);
        }
    }

    flash_set('sukses', 'Perubahan data barang "' . $namaBarang . '" berhasil disimpan.');
    redirect('barang/list.php');
} catch (RuntimeException $e) {
    flash_set('error', $e->getMessage());
    redirect($urlEdit);
} catch (PDOException $e) {
    // Bila gagal update DB tetapi foto baru sudah diunggah, bersihkan foto baru
    if ($fotoBaru !== null) {
        $pathBaru = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fotoBaru);
        if (is_file($pathBaru)) @unlink($pathBaru);
    }
    error_log('[SnapRent] Error barang/proses_edit.php: ' . $e->getMessage());
    flash_set('error', 'Gagal memperbarui data barang di database. Silakan coba lagi.');
    redirect($urlEdit);
}
