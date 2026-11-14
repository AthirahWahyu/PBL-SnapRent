<?php
/**
 * includes/helpers.php
 * Fungsi-fungsi pembantu yang dipakai di seluruh sisi admin.
 * File ini TIDAK boleh menampilkan output apa pun bila dibuka langsung.
 */

if (defined('_HELPERS_LOADED')) return;
define('_HELPERS_LOADED', true);

// ── Muat .env lebih awal agar APP_URL tersedia sebelum koneksi.php ────────────
(function () {
    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!file_exists($envFile)) return;
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
})();

// ── Keamanan output ───────────────────────────────────────────────────────────

/**
 * Escape string untuk output HTML yang aman.
 * Wajib dipakai untuk semua data dari DB / input user yang dicetak ke HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── URL ───────────────────────────────────────────────────────────────────────

/**
 * Mengembalikan URL dasar aplikasi (tanpa trailing slash).
 * Membaca APP_URL dari .env bila tersedia, lalu menebak dari $_SERVER.
 */
function base_url(string $path = ''): string
{
    // Prioritaskan APP_URL dari .env
    if (!empty($_ENV['APP_URL'])) {
        $base = rtrim($_ENV['APP_URL'], '/');
        return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    // Fallback: tebak otomatis dari $_SERVER
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));

    // Jika DOCUMENT_ROOT sudah root proyek (kasus php -S), tidak ada subfolder
    if ($docRoot !== '' && is_dir($docRoot . '/includes')) {
        return $scheme . '://' . $host . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    // Kasus Apache subfolder: naik dari script saat ini sampai ketemu folder yang ada includes/
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $dir       = $scriptDir;
    $subPath   = '';
    while ($dir !== '' && $dir !== '/' && $dir !== $docRoot) {
        if (is_dir($dir . '/includes') && file_exists($dir . '/index.php')) {
            // dir ini adalah root proyek, hitung sub-path relatif ke docRoot
            if ($docRoot !== '') {
                $subPath = substr($dir, strlen($docRoot));
            }
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;   // sudah di root filesystem
        $dir = $parent;
    }

    $base = $scheme . '://' . $host . $subPath;
    return rtrim($base, '/') . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

// ── Redirect ──────────────────────────────────────────────────────────────────

/**
 * Redirect ke URL dan hentikan eksekusi.
 * @param string $url  URL relatif (dari root proyek) atau URL lengkap.
 */
function redirect(string $url): never
{
    // Jika bukan URL lengkap, jadikan absolut via base_url()
    if (!preg_match('#^https?://#', $url)) {
        $url = base_url($url);
    }
    header('Location: ' . $url);
    exit;
}

// ── Format ────────────────────────────────────────────────────────────────────

/**
 * Format angka ke format Rupiah Indonesia, mis. "Rp 350.000".
 */
function format_rupiah(float|int|string|null $angka): string
{
    if ($angka === null || $angka === '') return 'Rp 0';
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

/**
 * Format timestamp/string tanggal ke "dd/mm/yyyy HH:mm" zona Asia/Jakarta.
 * Menerima string tanggal dari PostgreSQL atau objek DateTime.
 */
function format_tanggal(string|null $tanggal): string
{
    if ($tanggal === null || $tanggal === '') return '-';
    try {
        $dt = new DateTimeImmutable($tanggal, new DateTimeZone('Asia/Jakarta'));
        return $dt->format('d/m/Y H:i');
    } catch (Exception) {
        return e($tanggal);
    }
}

// ── Flash Message ─────────────────────────────────────────────────────────────

/**
 * Simpan flash message ke session.
 * @param string $tipe  'sukses' | 'error' | 'info'
 * @param string $pesan Isi pesan.
 */
function flash_set(string $tipe, string $pesan): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['_flash'][$tipe] = $pesan;
}

/**
 * Ambil dan hapus semua flash message dari session.
 * Mengembalikan array ['tipe' => 'pesan'] atau array kosong.
 */
function flash_get(): array
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $flash = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flash;
}

// ── Request Helpers ───────────────────────────────────────────────────────────

/**
 * Cek apakah request saat ini adalah POST.
 */
function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Ambil integer dari input (GET/POST/request) dengan aman.
 * Kembalikan null bila key tidak ada atau nilainya bukan integer valid.
 *
 * @param string $key    Nama parameter.
 * @param string $dari   'post' | 'get' | 'request'
 */
function ambil_int(string $key, string $dari = 'get'): int|null
{
    $sumber = match (strtolower($dari)) {
        'post'    => $_POST,
        'request' => $_REQUEST,
        default   => $_GET,
    };
    if (!isset($sumber[$key]) || !is_numeric($sumber[$key])) {
        return null;
    }
    $val = (int) $sumber[$key];
    return ($val > 0) ? $val : null;   // id negatif / nol dianggap tidak valid
}

// ── Helper Sisi Pelanggan & JSON ──────────────────────────────────────────────

/**
 * Hitung jumlah item barang di dalam keranjang session ($_SESSION['keranjang']).
 * Mendukung struktur $_SESSION['keranjang']['items'] maupun array daftar item langsung.
 */
function hitung_item_keranjang(): int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['keranjang']) || !is_array($_SESSION['keranjang'])) {
        return 0;
    }
    if (isset($_SESSION['keranjang']['items']) && is_array($_SESSION['keranjang']['items'])) {
        $totalUnit = 0;
        foreach ($_SESSION['keranjang']['items'] as $itm) {
            $totalUnit += is_array($itm) ? max(1, (int) ($itm['jumlah'] ?? 1)) : 1;
        }
        return $totalUnit;
    }
    // Jika disimpan sebagai array item dengan kunci meta 'mulai'/'selesai'
    $metaKeys = ['mulai', 'selesai', 'waktu_mulai', 'waktu_selesai'];
    $count = 0;
    foreach ($_SESSION['keranjang'] as $k => $v) {
        if (in_array((string) $k, $metaKeys, true)) {
            continue;
        }
        $count++;
    }
    return $count;
}

/**
 * Hitung perkiraan jumlah hari sewa (dibulatkan ke atas per 24 jam, minimal 1 hari).
 * Rumus identik dengan fungsi database fn_hitung_hari(p_mulai, p_kembali).
 */
function hitung_estimasi_hari(string $waktu_mulai, string $waktu_kembali): int
{
    try {
        $tz      = new DateTimeZone('Asia/Jakarta');
        $dtMulai = new DateTimeImmutable($waktu_mulai, $tz);
        $dtAkhir = new DateTimeImmutable($waktu_kembali, $tz);
        $selisih = $dtAkhir->getTimestamp() - $dtMulai->getTimestamp();
        if ($selisih <= 0) {
            return 1;
        }
        return max(1, (int) ceil($selisih / 86400));
    } catch (Exception) {
        return 1;
    }
}

/**
 * Kirim respons JSON standar {"status":"ok","data":...} atau {"status":"error","message":...}
 * lalu hentikan eksekusi script.
 */
function kirim_json(string $status, mixed $payload = null, int $httpCode = 200): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=UTF-8');
    if ($status === 'ok') {
        echo json_encode([
            'status' => 'ok',
            'data'   => $payload,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status'  => 'error',
            'message' => is_string($payload) ? $payload : 'Terjadi kesalahan pada permintaan.',
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ── Pengaturan Profil Toko (Jam Operasional, Lokasi, Kontak) ──────────────────

/**
 * Ambil data pengaturan profil toko untuk halaman publik/pelanggan.
 * Membaca dari file JSON config/pengaturan_toko.json dengan fallback nilai default.
 *
 * @return array{jam_operasional: string, lokasi: string, kontak: string}
 */
function ambil_pengaturan_toko(): array
{
    $dirConfig = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config';
    $fileJson  = $dirConfig . DIRECTORY_SEPARATOR . 'pengaturan_toko.json';

    $default = [
        'jam_operasional' => 'Senin – Sabtu : 08.00 – 20.00 WIB',
        'lokasi'          => 'Jalan Griya Shanta Blok C No. 305, Jalan Soekarno Hatta',
        'kontak'          => '+62 812-3456-7890',
    ];

    if (!file_exists($fileJson)) {
        return $default;
    }

    $konten = file_get_contents($fileJson);
    if ($konten === false) {
        return $default;
    }

    $decoded = json_decode($konten, true);
    if (!is_array($decoded)) {
        return $default;
    }

    return [
        'jam_operasional' => trim((string) ($decoded['jam_operasional'] ?? $default['jam_operasional'])),
        'lokasi'          => trim((string) ($decoded['lokasi'] ?? $default['lokasi'])),
        'kontak'          => trim((string) ($decoded['kontak'] ?? $default['kontak'])),
    ];
}

/**
 * Simpan data pengaturan profil toko ke file JSON config/pengaturan_toko.json.
 *
 * @param array $dataBaru Array dengan kunci 'jam_operasional', 'lokasi', 'kontak'
 * @return bool True jika berhasil disimpan
 */
function simpan_pengaturan_toko(array $dataBaru): bool
{
    $dirConfig = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config';
    if (!is_dir($dirConfig)) {
        if (!mkdir($dirConfig, 0755, true)) {
            return false;
        }
    }

    $fileJson = $dirConfig . DIRECTORY_SEPARATOR . 'pengaturan_toko.json';
    $lama     = ambil_pengaturan_toko();

    $jamOperasional = trim((string) ($dataBaru['jam_operasional'] ?? $lama['jam_operasional']));
    $lokasi         = trim((string) ($dataBaru['lokasi'] ?? $lama['lokasi']));
    $kontak         = trim((string) ($dataBaru['kontak'] ?? $lama['kontak']));

    $dataSimpan = [
        'jam_operasional' => $jamOperasional !== '' ? $jamOperasional : $lama['jam_operasional'],
        'lokasi'          => $lokasi !== '' ? $lokasi : $lama['lokasi'],
        'kontak'          => $kontak !== '' ? $kontak : $lama['kontak'],
    ];

    $json = json_encode($dataSimpan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return file_put_contents($fileJson, $json, LOCK_EX) !== false;
}

