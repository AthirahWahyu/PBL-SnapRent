<?php
/**
 * Utilitas upload bukti pembayaran SnapRent
 * Mengembalikan nama file relatif jika sukses, atau melempar Exception jika gagal.
 */
function uploadBuktiPembayaran(array $file, string $targetDir = __DIR__ . '/../uploads/pembayaran/'): string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Parameter file tidak valid.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('File bukti pembayaran wajib diunggah.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Ukuran file melebihi batas maksimal server.');
        default:
            throw new RuntimeException('Terjadi kesalahan saat mengunggah file.');
    }

    // Validasi ukuran maksimal 2MB
    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new RuntimeException('Ukuran file bukti pembayaran maksimal 2MB.');
    }

    // Validasi MIME type asli file
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!array_key_exists($mime, $allowedMimes)) {
        throw new RuntimeException('Format file harus berupa gambar JPG, PNG, atau WEBP.');
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // Nama file unik dan aman
    $ext = $allowedMimes[$mime];
    $newFileName = 'bukti_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destination = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Gagal menyimpan file bukti pembayaran ke server.');
    }

    return 'uploads/pembayaran/' . $newFileName;
}