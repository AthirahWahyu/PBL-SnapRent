<?php
/**
 * includes/header.php
 * Header dan navigasi utama Sisi Pelanggan SnapRent.
 * File ini TIDAK menampilkan output apa pun bila dibuka langsung.
 *
 * Variabel opsional sebelum require:
 *   $judul_halaman (string) - Judul halaman spesifik
 */

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit;
}

if (!defined('_AUTH_LOADED')) {
    require_once __DIR__ . '/auth.php';
}
if (!defined('_HELPERS_LOADED')) {
    require_once __DIR__ . '/helpers.php';
}
if (!defined('_CSRF_LOADED')) {
    require_once __DIR__ . '/csrf.php';
}

$judulBersih = isset($judul_halaman) && trim((string) $judul_halaman) !== ''
    ? trim((string) $judul_halaman) . ' – SnapRent'
    : 'SnapRent – Sewa Kamera & Peralatan Fotografi';

$jumlahItemKeranjang = hitung_item_keranjang();

// Deteksi halaman aktif berdasarkan nama script saat ini
$scriptSaatIni = basename($_SERVER['SCRIPT_NAME'] ?? '');
$uriPath       = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

$menuPelanggan = [
    [
        'href'  => base_url('index.php'),
        'label' => 'Katalog',
        'aktif' => ($scriptSaatIni === 'index.php' && !str_contains($uriPath, '/dashboard/') && !str_contains($uriPath, '/laporan/') && !str_contains($uriPath, '/pengaturan/')),
    ],
    [
        'href'  => base_url('tentang.php'),
        'label' => 'Tentang Kami',
        'aktif' => ($scriptSaatIni === 'tentang.php'),
    ],
    [
        'href'  => base_url('bantuan.php'),
        'label' => 'Pusat Bantuan',
        'aktif' => ($scriptSaatIni === 'bantuan.php'),
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(base_url()) ?>">
    <title><?= e($judulBersih) ?></title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Customer CSS -->
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Poppins', 'sans-serif'] },
                    colors: {
                        arang:  '#111111',
                        emas:   '#111111',
                        kanvas: '#eaecf0',
                    }
                }
            }
        };
    </script>

    <!-- Utilitas JavaScript Bersama Sisi Pelanggan -->
    <script src="<?= e(base_url('assets/js/app.js')) ?>" defer></script>
</head>
<body class="bg-white text-gray-900 font-sans min-h-screen flex flex-col antialiased">

<!-- ══════════════════════════════════════════════════════════════════════════
     NAVBAR PELANGGAN (Tanpa ikon akun / tautan login admin)
     ═══════════════════════════════════════════════════════════════════════ -->
<header class="navbar-pelanggan">
    <div class="wadah-pelanggan navbar-pelanggan__inner">

        <!-- Logo SnapRent -->
        <a href="<?= e(base_url('index.php')) ?>" class="logo-snaprent" aria-label="SnapRent Beranda Katalog">
            <span class="logo-snaprent__ikon" aria-hidden="true">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3L2 8l10 5 10-5-10-5z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2 12l10 5 10-5"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2 16l10 5 10-5"/>
                </svg>
            </span>
            <span>SnapRent</span>
        </a>

        <!-- Menu Navigasi Desktop & Aksi Kanan -->
        <div class="flex items-center gap-2 sm:gap-4">
            <nav class="nav-menu-desktop" aria-label="Navigasi Utama">
                <?php foreach ($menuPelanggan as $item): ?>
                    <a href="<?= e($item['href']) ?>"
                       class="nav-link <?= $item['aktif'] ? 'nav-link--aktif' : '' ?>"
                       <?= $item['aktif'] ? 'aria-current="page"' : '' ?>>
                        <?= e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Tombol Keranjang Belanja dengan Badge Jumlah Item -->
            <button type="button"
                    id="btnBukaKeranjang"
                    data-buka-keranjang="<?= e(base_url('keranjang/isi.php')) ?>"
                    class="btn-keranjang-nav"
                    aria-label="Keranjang penyewaan, <?= (int) $jumlahItemKeranjang ?> barang"
                     aria-haspopup="dialog"
                    aria-controls="dialogKeranjang">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                </svg>
                <span id="badgeKeranjang"
                      data-keranjang-badge
                      class="keranjang-badge <?= $jumlahItemKeranjang === 0 ? 'keranjang-badge--kosong' : '' ?>">
                    <?= (int) $jumlahItemKeranjang ?>
                </span>
            </button>

            <!-- Tombol Menu Lipat (Hanya tampil di Ponsel) -->
            <button type="button"
                    id="btnMenuMobile"
                    class="btn-menu-mobile"
                    aria-expanded="false"
                    aria-controls="menuMobile"
                    aria-label="Buka menu navigasi">
                <svg id="ikonMenuBuka" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                </svg>
                <svg id="ikonMenuTutup" class="w-5 h-5 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Menu Lipat Ponsel -->
    <nav id="menuMobile" class="menu-mobile" aria-label="Navigasi Ponsel">
        <ul class="menu-mobile__list">
            <?php foreach ($menuPelanggan as $item): ?>
                <li>
                    <a href="<?= e($item['href']) ?>"
                       class="nav-link <?= $item['aktif'] ? 'nav-link--aktif' : '' ?>"
                       <?= $item['aktif'] ? 'aria-current="page"' : '' ?>>
                        <?= e($item['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</header>

<!-- ══════════════════════════════════════════════════════════════════════════
     DIALOG DRAWER KERANJANG (Aksesibel dengan <dialog>)
     ═══════════════════════════════════════════════════════════════════════ -->
<dialog id="dialogKeranjang" class="dialog-snaprent dialog-drawer" aria-labelledby="judulDialogKeranjang">
    <div class="dialog-panel">
        <div class="dialog-header">
            <h2 id="judulDialogKeranjang" class="dialog-judul">Keranjang</h2>
            <button type="button"
                    class="btn-tutup-dialog"
                    data-tutup-dialog="dialogKeranjang"
                    aria-label="Tutup keranjang">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div id="isiDialogKeranjang" class="dialog-body scroll-halus">
            <div class="keadaan-kosong">
                <p class="keadaan-kosong__judul">Keranjang masih kosong</p>
                <p class="keadaan-kosong__teks">Pilih rentang waktu sewa dan tambahkan kamera dari katalog.</p>
            </div>
        </div>
    </div>
</dialog>

<!-- Wadah Toast Notifikasi -->
<div id="toastContainer" class="toast-wadah" role="status" aria-live="polite" aria-atomic="true"></div>

<!-- ══════════════════════════════════════════════════════════════════════════
     AREA KONTEN UTAMA PELANGGAN
     ═══════════════════════════════════════════════════════════════════════ -->
<main id="kontenUtama" class="flex-1 py-6 md:py-8">
    <?php
    $flashesPelanggan = flash_get();
    if (!empty($flashesPelanggan)): ?>
        <div class="wadah-pelanggan mb-6 space-y-3">
            <?php foreach ($flashesPelanggan as $tipe => $pesan):
                $kelasFlash = match ($tipe) {
                    'sukses' => 'flash--sukses',
                    'error'  => 'flash--error',
                    default  => 'flash--info',
                };
            ?>
                <div class="flash-message <?= $kelasFlash ?> animate-fade-in" role="alert">
                    <span class="flex-1"><?= e($pesan) ?></span>
                    <button type="button"
                            onclick="this.parentElement.remove()"
                            class="text-current opacity-60 hover:opacity-100 font-bold text-lg leading-none"
                            aria-label="Tutup pesan">×</button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
