<?php
/**
 * pengaturan/proses_update_toko.php
 * Memproses pembaruan informasi profil toko (Jam Operasional, Lokasi, Kontak) oleh Admin.
 */

require_once __DIR__ . '/../includes/auth.php';
wajib_login();

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';

if (!is_post()) {
    flash_set('error', 'Metode request tidak diizinkan.');
    redirect('pengaturan/index.php');
}

csrf_verifikasi();

$jamOperasional = trim((string) ($_POST['jam_operasional'] ?? ''));
$lokasi         = trim((string) ($_POST['lokasi'] ?? ''));
$kontak         = trim((string) ($_POST['kontak'] ?? ''));

if ($jamOperasional === '' || $lokasi === '' || $kontak === '') {
    flash_set('error', 'Semua kolom informasi toko (Jam Operasional, Lokasi, dan Kontak) wajib diisi.');
    redirect('pengaturan/index.php');
}

$berhasil = simpan_pengaturan_toko([
    'jam_operasional' => $jamOperasional,
    'lokasi'          => $lokasi,
    'kontak'          => $kontak,
]);

if ($berhasil) {
    flash_set('sukses', 'Informasi toko (Jam Operasional, Lokasi, dan Kontak) berhasil diperbarui dan diterapkan ke seluruh halaman pelanggan.');
} else {
    flash_set('error', 'Gagal menyimpan pembaruan informasi toko ke server.');
}

redirect('pengaturan/index.php');
