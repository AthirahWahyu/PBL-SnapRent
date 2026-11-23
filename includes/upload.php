<?php
if (defined('_UPLOAD_LOADED')) return;
define('_UPLOAD_LOADED', true);

/**
 * Simpan foto barang yang diunggah ke uploads/barang/.
 *
 * @param array  $file        Elemen dari $_FILES['nama_field']
 * @param string $nama_barang Nama barang, digunakan sebagai slug nama file.
 *
 * @return string  Path relatif dari root proyek, mis. "uploads/barang/sony-a7-iv.jpg"
 *
 * @throws RuntimeException Bila file tidak valid atau gagal disimpan.
 */
function simpan_foto_barang(array $file, string $nama_barang): string
{
    // ── 1. Cek error upload dari PHP ─────────────────────────────────────────
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $pesanError = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file terlalu besar.',
            UPLOAD_ERR_PARTIAL                        => 'File hanya terunggah sebagian.',
            UPLOAD_ERR_NO_FILE                        => 'Tidak ada file yang diunggah.',
            default                                   => 'Terjadi kesalahan saat mengunggah file.',
        };
        throw new RuntimeException($pesanError);
    }

    // ── 2. Validasi ukuran (maks 2 MB) ───────────────────────────────────────
    $maxBytes = 2 * 1024 * 1024; // 2 MB
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Ukuran file melebihi batas maksimal 2 MB.');
    }

    // ── 3. Validasi MIME asli dengan finfo (bukan hanya ekstensi) ────────────
    $mimeIzin = ['image/jpeg', 'image/png'];
    $eksDari  = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeAsli = $finfo->file($file['tmp_name']);

    if (!in_array($mimeAsli, $mimeIzin, true)) {
        throw new RuntimeException('Format file tidak diizinkan. Hanya JPG dan PNG.');
    }

    // ── 4. Buat nama file dari slug nama barang ───────────────────────────────
    $ekstensi  = $eksDari[$mimeAsli];
    $namaFile  = _buat_slug($nama_barang) . '.' . $ekstensi;

    // ── 5. Tentukan direktori tujuan ──────────────────────────────────────────
    $rootProyek = dirname(__DIR__);                              // satu level di atas includes/
    $dirTujuan  = $rootProyek . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'barang';

    if (!is_dir($dirTujuan)) {
        if (!mkdir($dirTujuan, 0755, true)) {
            throw new RuntimeException('Direktori upload tidak dapat dibuat.');
        }
    }

    $pathTujuan = $dirTujuan . DIRECTORY_SEPARATOR . $namaFile;

    // ── 6. Pindahkan file ─────────────────────────────────────────────────────
    if (!move_uploaded_file($file['tmp_name'], $pathTujuan)) {
        throw new RuntimeException('Gagal menyimpan file. Periksa izin folder uploads/barang/.');
    }

    // ── 7. Kembalikan path relatif dari root proyek ───────────────────────────
    return 'uploads/barang/' . $namaFile;
}

/**
 * Ubah string menjadi slug URL-safe (huruf kecil, karakter non-alphanumerik → tanda hubung).
 * Contoh: "SONY A7 Mk IV (Body Only)" → "sony-a7-mk-iv-body-only"
 *
 * @internal  Hanya dipakai oleh simpan_foto_barang().
 */
function _buat_slug(string $teks): string
{
    // Ubah huruf besar ke kecil
    $slug = mb_strtolower($teks, 'UTF-8');

    // Ganti karakter non-alphanumerik (termasuk spasi, kurung, titik) dengan tanda hubung
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

    // Hapus tanda hubung di awal/akhir
    $slug = trim($slug, '-');

    // Jangan biarkan slug kosong
    if ($slug === '') {
        $slug = 'barang-' . time();
    }

    return $slug;
}

/**
 * Simpan foto atau dokumen bukti pembayaran yang diunggah ke uploads/pembayaran/.
 *
 * @param array  $file   Elemen dari $_FILES['bukti_pembayaran']
 * @param string $prefix Awalan nama file (default 'bukti')
 *
 * @return string Path relatif dari root proyek, mis. "uploads/pembayaran/bukti-20261112-abcdef12.jpg"
 *
 * @throws RuntimeException Bila file tidak valid, melebihi ukuran, atau gagal disimpan.
 */
function simpan_bukti_pembayaran(array $file, string $prefix = 'bukti'): string
{
    // ── 1. Cek error upload dari PHP ─────────────────────────────────────────
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $kodeErr = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $pesanError = match ($kodeErr) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran bukti pembayaran terlalu besar (maksimal 5 MB).',
            UPLOAD_ERR_PARTIAL                        => 'File bukti pembayaran hanya terunggah sebagian.',
            UPLOAD_ERR_NO_FILE                        => 'Wajib memilih dan mengunggah file bukti pembayaran.',
            default                                   => 'Terjadi kesalahan teknis saat mengunggah bukti pembayaran.',
        };
        throw new RuntimeException($pesanError);
    }

    // ── 2. Validasi ukuran (maks 5 MB) ───────────────────────────────────────
    $maxBytes = 5 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Ukuran file bukti pembayaran melebihi batas maksimal 5 MB.');
    }

    // ── 3. Validasi MIME asli dengan finfo ────────────────────────────────────
    $mimeIzin = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
    ];

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeAsli = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mimeAsli, $mimeIzin)) {
        throw new RuntimeException('Format bukti pembayaran tidak diizinkan. Gunakan format JPG, PNG, WEBP, atau PDF.');
    }

    // ── 4. Buat nama file acak unik ───────────────────────────────────────────
    $ekstensi = $mimeIzin[$mimeAsli];
    $tokenAcak = bin2hex(random_bytes(4));
    $namaFile = sprintf('%s-%s-%s.%s', $prefix, date('Ymd-His'), $tokenAcak, $ekstensi);

    // ── 5. Buat direktori bila belum ada ──────────────────────────────────────
    $rootProyek = dirname(__DIR__);
    $dirTujuan  = $rootProyek . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pembayaran';

    if (!is_dir($dirTujuan)) {
        if (!mkdir($dirTujuan, 0755, true)) {
            throw new RuntimeException('Direktori upload pembayaran tidak dapat dibuat di server.');
        }
    }

    $pathTujuan = $dirTujuan . DIRECTORY_SEPARATOR . $namaFile;

    // ── 6. Pindahkan file ─────────────────────────────────────────────────────
    if (!move_uploaded_file($file['tmp_name'], $pathTujuan)) {
        throw new RuntimeException('Gagal menyimpan file bukti pembayaran ke server.');
    }

    return 'uploads/pembayaran/' . $namaFile;
}

