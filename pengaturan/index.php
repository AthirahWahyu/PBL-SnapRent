<?php
require_once __DIR__ . '/../includes/auth.php';
wajib_login();

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin_sesi = admin_saat_ini();
$id_admin   = (int) ($admin_sesi['id_admin'] ?? 0);

// Ambil data terbaru admin dari database
$stmtAdmin = $pdo->prepare('SELECT id_admin, username, nama_lengkap FROM admin WHERE id_admin = :id');
$stmtAdmin->execute([':id' => $id_admin]);
$data_admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC) ?: $admin_sesi;

$pengaturan_toko = ambil_pengaturan_toko();

$judul_halaman = 'Pengaturan Akun Admin';
require_once __DIR__ . '/../includes/header_admin.php';
?>

<!-- Heading Halaman -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#111111] tracking-tight">Pengaturan Akun</h1>
    <p class="text-xs text-gray-500 mt-0.5">
        Kelola keamanan akun administrator dan perbarui kata sandi masuk Anda secara berkala.
    </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

    <!-- Kolom Kiri (4 kolom): Informasi Profil Admin Aktif -->
    <div class="lg:col-span-4">
        <div class="card-admin p-6">
            <div class="flex items-center gap-4 pb-5 border-b border-gray-100">
                <div class="w-14 h-14 rounded-2xl bg-[#111111] text-white flex items-center justify-center font-bold text-xl flex-shrink-0">
                    <?= e(mb_strtoupper(mb_substr($data_admin['nama_lengkap'] ?? 'A', 0, 1))) ?>
                </div>
                <div class="min-w-0">
                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-[#111111] mb-1">
                        ADMINISTRATOR AKTIF
                    </span>
                    <h2 class="text-base font-bold text-[#111111] truncate">
                        <?= e($data_admin['nama_lengkap'] ?? 'Administrator') ?>
                    </h2>
                    <p class="text-xs text-gray-500 truncate">@<?= e($data_admin['username'] ?? 'admin') ?></p>
                </div>
            </div>

            <div class="space-y-3 pt-4 text-xs">
                <div class="flex items-center justify-between py-1.5 border-b border-gray-50">
                    <span class="text-gray-500">ID Admin</span>
                    <span class="font-bold text-[#111111]">#<?= (int) ($data_admin['id_admin'] ?? 0) ?></span>
                </div>
                <div class="flex items-center justify-between py-1.5 border-b border-gray-50">
                    <span class="text-gray-500">Username Login</span>
                    <span class="font-semibold text-gray-900 font-mono"><?= e($data_admin['username'] ?? '-') ?></span>
                </div>
                <div class="flex items-center justify-between py-1.5">
                    <span class="text-gray-500">Nama Lengkap</span>
                    <span class="font-semibold text-gray-900"><?= e($data_admin['nama_lengkap'] ?? '-') ?></span>
                </div>
            </div>

            <div class="mt-5 p-3.5 rounded-xl bg-gray-50 border border-gray-200/70 text-[11px] text-gray-600 leading-relaxed">
                <strong class="block text-[#111111] mb-0.5">Panduan Keamanan:</strong>
                Gunakan kombinasi huruf, angka, dan simbol minimal 8 karakter. Setelah password diubah, sesi Anda akan diperbarui secara otomatis.
            </div>
        </div>
    </div>

    <!-- Kolom Kanan (8 kolom): Form Ubah Password -->
    <div class="lg:col-span-8">
        <div class="card-admin p-6 md:p-8">
            <div class="pb-4 mb-6 border-b border-gray-100">
                <h2 class="text-base font-bold text-[#111111]">Ubah Password</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    Masukkan password lama Anda untuk verifikasi sebelum menyimpan password baru.
                </p>
            </div>

            <form action="<?= e(base_url('pengaturan/proses_update.php')) ?>" method="POST" class="space-y-5 max-w-xl" autocomplete="off">
                <?= csrf_field() ?>

                <!-- Password Lama -->
                <div>
                    <label for="password_lama" class="form-label">
                        Password Lama <span class="text-rose-600">*</span>
                    </label>
                    <div class="relative">
                        <input type="password"
                               id="password_lama"
                               name="password_lama"
                               required
                               placeholder="Masukkan password saat ini"
                               class="form-input pr-16">
                        <button type="button"
                                onclick="toggleLihatPassword('password_lama', this)"
                                class="absolute inset-y-0 right-0 px-3.5 text-[11px] font-semibold text-gray-500 hover:text-[#111111] cursor-pointer">
                            Lihat
                        </button>
                    </div>
                </div>

                <!-- Password Baru -->
                <div>
                    <label for="password_baru" class="form-label">
                        Password Baru <span class="text-rose-600">*</span>
                    </label>
                    <div class="relative">
                        <input type="password"
                               id="password_baru"
                               name="password_baru"
                               minlength="8"
                               required
                               placeholder="Minimal 8 karakter"
                               class="form-input pr-16">
                        <button type="button"
                                onclick="toggleLihatPassword('password_baru', this)"
                                class="absolute inset-y-0 right-0 px-3.5 text-[11px] font-semibold text-gray-500 hover:text-[#111111] cursor-pointer">
                            Lihat
                        </button>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Wajib minimal 8 karakter.</p>
                </div>

                <!-- Konfirmasi Password Baru -->
                <div>
                    <label for="konfirmasi_password" class="form-label">
                        Konfirmasi Password Baru <span class="text-rose-600">*</span>
                    </label>
                    <div class="relative">
                        <input type="password"
                               id="konfirmasi_password"
                               name="konfirmasi_password"
                               minlength="8"
                               required
                               placeholder="Ketik ulang password baru"
                               class="form-input pr-16">
                        <button type="button"
                                onclick="toggleLihatPassword('konfirmasi_password', this)"
                                class="absolute inset-y-0 right-0 px-3.5 text-[11px] font-semibold text-gray-500 hover:text-[#111111] cursor-pointer">
                            Lihat
                        </button>
                    </div>
                </div>

                <div class="pt-3 flex items-center gap-3">
                    <button type="submit" class="btn-utama py-2.5 px-6 text-xs font-bold cursor-pointer">
                        Simpan Password Baru
                    </button>
                    <button type="reset" class="btn-sekunder py-2.5 px-5 text-xs cursor-pointer">
                        Bersihkan
                    </button>
                </div>
            </form>
        </div>

        <!-- Kartu 2: Pengaturan Informasi Toko (Halaman Tentang Kami & Pelanggan) -->
        <div class="card-admin p-6 md:p-8 mt-6">
            <div class="pb-4 mb-6 border-b border-gray-100 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-[#111111]">Informasi Profil Toko (Halaman Pelanggan)</h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Informasi ini tampil di halaman Tentang Kami, Pusat Bantuan, dan Footer sisi pelanggan.
                    </p>
                </div>
                <span class="inline-block px-2.5 py-1 rounded-lg text-[10px] font-bold bg-gray-100 text-[#111111] whitespace-nowrap">
                    PUBLIK
                </span>
            </div>

            <form action="<?= e(base_url('pengaturan/proses_update_toko.php')) ?>" method="POST" class="space-y-5 max-w-xl">
                <?= csrf_field() ?>

                <!-- Jam Operasional -->
                <div>
                    <label for="jam_operasional" class="form-label">
                        Jam Operasional <span class="text-rose-600">*</span>
                    </label>
                    <input type="text"
                           id="jam_operasional"
                           name="jam_operasional"
                           value="<?= e($pengaturan_toko['jam_operasional']) ?>"
                           required
                           placeholder="Contoh: Senin – Sabtu : 08.00 – 20.00 WIB"
                           class="form-input">
                    <p class="text-[11px] text-gray-400 mt-1">Jadwal buka dan tutup toko untuk pengambilan &amp; pengembalian alat.</p>
                </div>

                <!-- Lokasi Toko -->
                <div>
                    <label for="lokasi" class="form-label">
                        Lokasi / Alamat Toko <span class="text-rose-600">*</span>
                    </label>
                    <textarea id="lokasi"
                              name="lokasi"
                              rows="2"
                              required
                              placeholder="Contoh: Jalan Griya Shanta Blok C No. 305, Jalan Soekarno Hatta"
                              class="form-input leading-relaxed"><?= e($pengaturan_toko['lokasi']) ?></textarea>
                    <p class="text-[11px] text-gray-400 mt-1">Alamat fisik toko tempat penyewa datang mengambil dan mengembalikan kamera.</p>
                </div>

                <!-- Kontak WhatsApp / Telepon -->
                <div>
                    <label for="kontak" class="form-label">
                        Kontak WhatsApp / Telepon <span class="text-rose-600">*</span>
                    </label>
                    <input type="text"
                           id="kontak"
                           name="kontak"
                           value="<?= e($pengaturan_toko['kontak']) ?>"
                           required
                           placeholder="Contoh: +62 812-3456-7890"
                           class="form-input font-mono">
                    <p class="text-[11px] text-gray-400 mt-1">Nomor resmi untuk konfirmasi pesanan dan layanan pelanggan via WhatsApp.</p>
                </div>

                <div class="pt-3 flex items-center gap-3">
                    <button type="submit" class="btn-utama py-2.5 px-6 text-xs font-bold cursor-pointer">
                        Simpan Informasi Toko
                    </button>
                    <a href="<?= e(base_url('tentang.php')) ?>" target="_blank" class="btn-sekunder py-2.5 px-5 text-xs inline-flex items-center gap-1.5">
                        <span>Lihat Halaman Tentang Kami</span>
                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function toggleLihatPassword(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = 'Sembunyi';
    } else {
        input.type = 'password';
        btn.textContent = 'Lihat';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer_admin.php'; ?>
