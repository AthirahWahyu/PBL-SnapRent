<?php
/**
 * includes/footer.php
 * Footer utama Sisi Pelanggan SnapRent.
 * File ini TIDAK menampilkan output apa pun bila dibuka langsung.
 */

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit;
}

if (!defined('_HELPERS_LOADED')) {
    require_once __DIR__ . '/helpers.php';
}

$tokoFooter = ambil_pengaturan_toko();
?>
</main>

<!-- ══════════════════════════════════════════════════════════════════════════
     FOOTER PELANGGAN
     ═══════════════════════════════════════════════════════════════════════ -->
<footer class="footer-pelanggan">
    <div class="wadah-pelanggan">
        <!-- Baris Utama: Identitas, Alamat Toko, dan Kontak -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pb-6 border-b border-gray-200 text-sm">
            <div>
                <div class="font-bold text-base text-[#111111] mb-1">SnapRent</div>
                <p class="text-xs text-gray-600 leading-relaxed m-0">
                    Layanan penyewaan kamera, lensa, dan perlengkapan fotografi/videografi praktis dan terpercaya.
                </p>
            </div>

            <div>
                <div class="font-semibold text-xs text-gray-800 uppercase tracking-wider mb-1">Alamat &amp; Jam Operasional</div>
                <p class="text-xs text-gray-600 leading-relaxed m-0">
                    Lokasi: <span class="font-medium text-gray-800"><?= e($tokoFooter['lokasi']) ?></span><br>
                    Jam Operasional: <span class="font-medium text-gray-800"><?= e($tokoFooter['jam_operasional']) ?></span>
                </p>
            </div>

            <div class="md:text-right">
                <div class="font-semibold text-xs text-gray-800 uppercase tracking-wider mb-1">Kontak &amp; Informasi</div>
                <p class="text-xs text-gray-600 leading-relaxed m-0">
                    WhatsApp / Telepon: <span class="font-medium text-gray-800 font-mono"><?= e($tokoFooter['kontak']) ?></span>
                </p>
                <div class="mt-2 flex flex-wrap md:justify-end gap-3 text-xs text-gray-600">
                    <a href="<?= e(base_url('tentang.php')) ?>" class="hover:text-[#111111] hover:underline">Tentang Kami</a>
                    <span aria-hidden="true">·</span>
                    <a href="<?= e(base_url('bantuan.php')) ?>" class="hover:text-[#111111] hover:underline">Pusat Bantuan</a>
                </div>
            </div>
        </div>

        <!-- Baris Paling Bawah: Hak Cipta & Satu-satunya Tautan Masuk Admin (12px, warna teks sekunder) -->
        <div class="pt-4 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-gray-500">
            <div>
                &copy; <?= date('Y') ?> SnapRent — Camera Rent. Hak cipta dilindungi.
            </div>
            <div>
                <a href="<?= e(base_url('auth/login.php')) ?>" class="tautan-masuk-admin">Masuk Admin</a>
            </div>
        </div>
    </div>
</footer>

</body>
</html>
