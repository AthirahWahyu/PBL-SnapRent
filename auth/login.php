<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';

// Proteksi crawling search engine
header('X-Robots-Tag: noindex, nofollow');

// Alihkan bila admin telah terautentikasi
if (admin_saat_ini()) {
    redirect('dashboard/index.php');
}

// Evaluasi status limitasi login (brute-force protection)
$detikTersisa = 0;
if (isset($_SESSION['login_blocked_until'])) {
    $selisihWaktu = (int) $_SESSION['login_blocked_until'] - time();
    if ($selisihWaktu > 0) {
        $detikTersisa = $selisihWaktu;
    } else {
        unset($_SESSION['login_blocked_until'], $_SESSION['login_attempts']);
    }
}

// Pengambilan notifikasi flash
$flashData    = flash_get();
$pesanError   = $flashData['error'] ?? '';
$pesanSukses  = $flashData['sukses'] ?? '';
$isFormLocked = ($detikTersisa > 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Masuk Admin – SnapRent</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/admin.css')) ?>">
</head>
<body class="bg-[#eaecf0] min-h-screen flex items-center justify-center p-4 antialiased">

    <div class="w-full max-w-sm bg-white rounded-3xl shadow-xl p-8 border border-white/80">

        <!-- Header & Logo -->
        <div class="flex flex-col items-center text-center mb-6">
            <div class="w-14 h-14 rounded-2xl bg-[#111111] text-white flex items-center justify-center shadow-md mb-3">
                <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3L2 8l10 5 10-5-10-5z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2 12l10 5 10-5"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2 16l10 5 10-5"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-[#111111] tracking-tight">SnapRent</h1>
            <p class="text-xs text-gray-400 mt-1">Panel Manajemen Penyewaan Kamera</p>
        </div>

        <!-- Notifikasi Error -->
        <?php if (!empty($pesanError)): ?>
            <div role="alert" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl text-xs font-medium mb-5 text-center">
                <?= e($pesanError) ?>
            </div>
        <?php endif; ?>

        <!-- Notifikasi Sukses -->
        <?php if (!empty($pesanSukses)): ?>
            <div role="alert" class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-2xl text-xs font-medium mb-5 text-center">
                <?= e($pesanSukses) ?>
            </div>
        <?php endif; ?>

        <!-- Peringatan Rate-Limit -->
        <?php if ($isFormLocked): ?>
            <div id="kotakBlokir" role="alert" class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-2xl text-xs font-medium mb-5 text-center">
                Terlalu banyak percobaan gagal. Silakan tunggu
                <strong id="detikBlokir"><?= e((string) $detikTersisa) ?></strong> detik sebelum mencoba lagi.
            </div>
        <?php endif; ?>

        <!-- Form Autentikasi -->
        <form action="<?= e(base_url('auth/proses_login.php')) ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>

            <!-- Input Username -->
            <div>
                <label for="username" class="block text-xs font-semibold text-gray-700 mb-1.5">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-400 pointer-events-none">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                    <input type="text"
                           id="username"
                           name="username"
                           placeholder="Masukkan username"
                           class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:bg-white focus:border-[#111111] transition-colors"
                           autocomplete="username"
                           <?= $isFormLocked ? 'disabled' : '' ?>
                           required>
                </div>
            </div>

            <!-- Input Password -->
            <div>
                <label for="password" class="block text-xs font-semibold text-gray-700 mb-1.5">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-400 pointer-events-none">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                    <input type="password"
                           id="password"
                           name="password"
                           placeholder="Masukkan password"
                           class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:bg-white focus:border-[#111111] transition-colors"
                           autocomplete="current-password"
                           <?= $isFormLocked ? 'disabled' : '' ?>
                           required>
                </div>
            </div>

            <!-- Tombol Submit -->
            <button type="submit"
                    id="btnLogin"
                    <?= $isFormLocked ? 'disabled' : '' ?>
                    class="w-full bg-[#111111] text-white text-sm font-semibold py-3 rounded-full mt-2 hover:bg-gray-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                Masuk
            </button>

            <!-- Link Kembali -->
            <div class="text-center pt-2">
                <a href="<?= e(base_url('index.php')) ?>" 
                   class="inline-flex items-center gap-2 text-xs font-medium text-gray-500 hover:text-gray-900 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Kembali ke Katalog
                </a>
            </div>
        </form>
    </div>

    <!-- Script Countdown Timer Blokir -->
    <?php if ($isFormLocked): ?>
    <script>
    (() => {
        let count = <?= (int) $detikTersisa ?>;
        const domDetik = document.getElementById('detikBlokir');
        const domKotak = document.getElementById('kotakBlokir');
        const fieldUser = document.getElementById('username');
        const fieldPass = document.getElementById('password');
        const btnSubmit = document.getElementById('btnLogin');

        const intervalId = setInterval(() => {
            count--;
            if (count <= 0) {
                clearInterval(intervalId);
                domKotak?.remove();
                if (fieldUser) fieldUser.disabled = false;
                if (fieldPass) fieldPass.disabled = false;
                if (btnSubmit) btnSubmit.disabled = false;
            } else if (domDetik) {
                domDetik.textContent = String(count);
            }
        }, 1000);
    })();
    </script>
    <?php endif; ?>

</body>
</html>
