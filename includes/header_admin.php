<?php
declare(strict_types=1);

if (!defined('_AUTH_LOADED')) {
    require_once __DIR__ . '/auth.php';
}
if (!function_exists('base_url')) {
    require_once __DIR__ . '/helpers.php';
}
if (!function_exists('csrf_field')) {
    require_once __DIR__ . '/csrf.php';
}

$admin      = admin_saat_ini();
$judulAdmin = $judul_halaman ?? 'Dashboard';
$uriSaatIni = $_SERVER['REQUEST_URI'] ?? '';

// Muat daftar menu dari config includes/menu_admin.php
$daftarMenu = require __DIR__ . '/menu_admin.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($judulAdmin) ?> – SnapRent Admin</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Admin CSS -->
    <link rel="stylesheet" href="<?= e(base_url('assets/css/admin.css')) ?>">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Poppins', 'sans-serif'] },
                    colors: {
                        arang:  '#111111',
                        emas:   '#111111',
                        kanvas: '#eaecf0',
                        'zebra-ganjil': '#ffffff',
                        'zebra-genap':  '#f8f9fa',
                        'baris-kuning': '#fefce8',
                        'baris-merah':  '#fff1f2',
                    }
                }
            }
        };
    </script>
</head>
<body class="bg-[#eaecf0] font-sans text-gray-900 flex h-screen overflow-hidden antialiased">

<!-- OVERLAY – Menutup sidebar pada layar mobile -->
<div id="overlay" class="fixed inset-0 bg-black/40 backdrop-blur-[1px] z-20 hidden lg:hidden"></div>

<!-- SIDEBAR PUTIH (Jobgio Style) -->
<aside id="sidebar"
       class="fixed top-0 left-0 h-full w-64 bg-white text-gray-800 flex flex-col z-30
              transform -translate-x-full transition-transform duration-300
              lg:translate-x-0 lg:static lg:flex-shrink-0 border-r border-gray-200/80 select-none">

    <!-- Logo SnapRent -->
    <div class="px-7 pt-8 pb-6 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-[#111111] text-white flex items-center justify-center shadow-sm flex-shrink-0">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3L2 8l10 5 10-5-10-5z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2 12l10 5 10-5"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2 16l10 5 10-5"/>
            </svg>
        </div>
        <span class="text-xl font-bold tracking-tight text-[#111111]">SnapRent</span>
    </div>

    <!-- Navigasi Sidebar Menu -->
    <nav class="flex-1 overflow-y-auto px-5 py-2 flex flex-col gap-2">
        <?php foreach ($daftarMenu as $item):
            $isAktif = str_contains($uriSaatIni, $item['pola']);
        ?>
            <a href="<?= e($item['href']) ?>"
               class="sidebar-pill group flex items-center gap-3.5 p-1.5 pr-4 rounded-full text-[13px] font-medium transition-all duration-200
                      <?= $isAktif ? 'sidebar-pill--aktif' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50' ?>">
                <span class="sidebar-pill__icon w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 transition-colors
                             <?= $isAktif ? 'bg-white text-[#111111] shadow-sm' : 'bg-gray-100 text-gray-600 group-hover:bg-gray-200 group-hover:text-gray-900' ?>">
                    <?= $item['svg'] ?>
                </span>
                <span class="truncate"><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Profil Admin & Tombol Keluar -->
    <div class="px-6 py-6 flex flex-col items-center text-center border-t border-gray-100">
        <div class="relative mb-2.5">
            <div class="w-14 h-14 rounded-full bg-gray-100 border-2 border-white shadow-md flex items-center justify-center text-[#111111] font-bold text-lg">
                <?= e(mb_strtoupper(mb_substr($admin['nama_lengkap'] ?? 'A', 0, 1))) ?>
            </div>
            <span class="absolute -top-0.5 -right-2 bg-[#111111] text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow">
                Admin
            </span>
        </div>
        <div class="font-bold text-sm text-[#111111] truncate max-w-full">
            <?= e($admin['nama_lengkap'] ?? 'Administrator') ?>
        </div>
        <div class="text-[11px] text-gray-400 truncate max-w-full mb-3">
            <?= e(($admin['username'] ?? 'admin') . '@snaprent.id') ?>
        </div>
        <form action="<?= e(base_url('auth/logout.php')) ?>" method="POST" class="w-full">
            <?= csrf_field() ?>
            <button type="submit"
                    class="w-full py-2 px-4 rounded-full text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-[#111111] hover:text-white transition-colors flex items-center justify-center gap-1.5 cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                </svg>
                <span>Keluar</span>
            </button>
        </form>
    </div>
</aside>

<!-- AREA KONTEN UTAMA -->
<div class="flex flex-col flex-1 min-w-0 overflow-hidden bg-[#eaecf0] relative">

    <!-- Efek Cahaya Latar -->
    <div class="pointer-events-none absolute -top-24 left-8 w-80 h-80 rounded-full bg-white/60 blur-3xl"></div>

    <!-- NAVBAR MOBILE / TABLET -->
    <header class="lg:hidden bg-white/90 backdrop-blur border-b border-gray-200 px-5 py-3.5 flex items-center gap-4 flex-shrink-0 z-10">
        <button id="btnBukaSidebar"
                type="button"
                class="text-gray-700 hover:text-black focus:outline-none"
                aria-label="Buka menu">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <h1 class="text-base font-bold text-[#111111] flex-1"><?= e($judulAdmin) ?></h1>
        <div class="w-8 h-8 rounded-full bg-[#111111] text-white flex items-center justify-center font-bold text-xs">
            <?= e(mb_strtoupper(mb_substr($admin['nama_lengkap'] ?? 'A', 0, 1))) ?>
        </div>
    </header>

    <!-- AREA KONTEN + SCROLL -->
    <main class="flex-1 overflow-y-auto p-5 md:p-7 lg:p-8 relative z-10">

        <!-- Notifikasi Flash Message -->
        <?php if (function_exists('flash_get')): ?>
            <?php foreach (flash_get() as $tipe => $pesan):
                $kelas = match ($tipe) {
                    'sukses' => 'flash--sukses',
                    'error'  => 'flash--error',
                    default  => 'flash--info',
                };
            ?>
                <div class="flash-message <?= $kelas ?> mb-5 flex items-start gap-3 animate-fade-in" role="alert">
                    <span class="mt-0.5 flex-shrink-0">
                        <?= $tipe === 'sukses' ? '✅' : ($tipe === 'error' ? '❌' : 'ℹ️') ?>
                    </span>
                    <span><?= e($pesan) ?></span>
                    <button type="button"
                            data-dismiss="flash"
                            class="ml-auto text-current opacity-50 hover:opacity-100 leading-none text-lg cursor-pointer"
                            aria-label="Tutup">×</button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
