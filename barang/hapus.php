<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/koneksi.php';

wajib_login();

if (!is_post()) {
    redirect('barang/list.php');
}

csrf_verifikasi();

$idBarang = ambil_int('id_barang', 'post');
if ($idBarang === null) {
    flash_set('error', 'ID barang tidak valid.');
    redirect('barang/list.php');
}

try {
    $stmt = $pdo->prepare('SELECT id_barang, nama_barang, foto_barang, status_barang FROM barang WHERE id_barang = :id LIMIT 1');
    $stmt->execute([':id' => $idBarang]);
    $barang = $stmt->fetch();

    if (!$barang) {
        flash_set('error', 'Data barang tidak ditemukan.');
        redirect('barang/list.php');
    }

    $namaBarang = (string) $barang['nama_barang'];
    $fotoBarang = trim((string) ($barang['foto_barang'] ?? ''));

    // Cek apakah barang pernah dipakai di detail_transaksi (FK RESTRICT)
    $stmtPakai = $pdo->prepare('SELECT EXISTS (SELECT 1 FROM detail_transaksi WHERE id_barang = :id)');
    $stmtPakai->execute([':id' => $idBarang]);
    $pernahDipakai = (bool) $stmtPakai->fetchColumn();

    if ($pernahDipakai) {
        // Jangan hapus fisik: ubah status_barang menjadi 'nonaktif'
        $stmtNonaktif = $pdo->prepare("UPDATE barang SET status_barang = 'nonaktif' WHERE id_barang = :id");
        $stmtNonaktif->execute([':id' => $idBarang]);

        flash_set(
            'sukses',
            'Barang "' . $namaBarang . '" sudah pernah tercatat pada riwayat transaksi sehingga tidak dihapus permanen; statusnya telah diubah menjadi Nonaktif.'
        );
    } else {
        // Belum pernah dipakai: hapus baris dari database dan file fotonya
        $stmtDel = $pdo->prepare('DELETE FROM barang WHERE id_barang = :id');
        $stmtDel->execute([':id' => $idBarang]);

        if ($fotoBarang !== '') {
            $pathFoto = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fotoBarang);
            if (is_file($pathFoto)) {
                @unlink($pathFoto);
            }
        }

        flash_set('sukses', 'Barang "' . $namaBarang . '" berhasil dihapus dari inventaris.');
    }
} catch (PDOException $e) {
    error_log('[SnapRent] Error barang/hapus.php: ' . $e->getMessage());
    flash_set('error', 'Tidak dapat memproses penghapusan barang saat ini. Silakan coba lagi.');
}

redirect('barang/list.php');
