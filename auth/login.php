<?php
/**
 * auth/login.php
 * Halaman login admin SnapRent.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';

header('X-Robots-Tag: noindex, nofollow');

// Jika sudah login, langsung ke dashboard/index.php
if (admin_saat_ini()) {
    redirect('dashboard/index.php');
}

// Cek status blokir sementara (setelah 5x gagal berturut-turut)
$sisaBlokir = 0;
if (!empty($_SESSION['login_blocked_until'])) {
    $sisaBlokir = (int) $_SESSION['login_blocked_until'] - time();
    if ($sisaBlokir <= 0) {
        unset($_SESSION['login_blocked_until']);
        $_SESSION['login_attempts'] = 0;
        $sisaBlokir = 0;
    }
}

// Ambil flash message bila ada
$flashes   = flash_get();
$errMsg    = $flashes['error'] ?? '';
$suksesMsg = $flashes['sukses'] ?? '';
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

        <!-- Logo SnapRent -->
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

        <!-- Flash Error -->
        <?php if ($errMsg !== ''): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl text-xs font-medium mb-5 text-center"
                 role="alert">
                <?= e($errMsg) ?>
            </div>
        <?php endif; ?>

        <!-- Flash Sukses -->
        <?php if ($suksesMsg !== ''): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-2xl text-xs font-medium mb-5 text-center"
                 role="alert">
                <?= e($suksesMsg) ?>
            </div>
        <?php endif; ?>

        <!-- Peringatan Blokir 60 Detik -->
        <?php if ($sisaBlokir > 0): ?>
            <div id="kotakBlokir"
                 class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-2xl text-xs font-medium mb-5 text-center"
                 role="alert">
                Terlalu banyak percobaan gagal. Silakan tunggu
                <strong id="detikBlokir"><?= e((string) $sisaBlokir) ?></strong> detik sebelum mencoba lagi.
            </div>
        <?php endif; ?>

        <!-- Form Login -->
        <form action="<?= e(base_url('auth/proses_login.php')) ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>

            <!-- Username -->
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
                           <?= $sisaBlokir > 0 ? 'disabled' : '' ?>
                           required>
                </div>
            </div>

            <!-- Password -->
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
                           <?= $sisaBlokir > 0 ? 'disabled' : '' ?>
                           required>
                </div>
            </div>

            <!-- Tombol Submit -->
            <button type="submit"
                    id="btnLogin"
                    <?= $sisaBlokir > 0 ? 'disabled' : '' ?>
                    class="w-full bg-[#111111] text-white text-sm font-semibold py-3 rounded-full mt-2
                           hover:bg-gray-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                Masuk
            </button>

            <!-- Tombol Kembali ke Katalog -->
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

    <?php if ($sisaBlokir > 0): ?>
    <script>
    (function () {
        let sisa = <?= (int) $sisaBlokir ?>;
        const elDetik  = document.getElementById('detikBlokir');
        const elKotak  = document.getElementById('kotakBlokir');
        const elUser   = document.getElementById('username');
        const elPass   = document.getElementById('password');
        const elBtn    = document.getElementById('btnLogin');

        const timer = setInterval(() => {
            sisa--;
            if (sisa <= 0) {
                clearInterval(timer);
                if (elKotak) elKotak.remove();
                if (elUser) elUser.disabled = false;
                if (elPass) elPass.disabled = false;
                if (elBtn)  elBtn.disabled  = false;
            } else if (elDetik) {
                elDetik.textContent = String(sisa);
            }
        }, 1000);
    })();
    </script>
    <?php endif; ?>

</body>
</html>

