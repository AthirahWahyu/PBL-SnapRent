<?php
/**
 * bantuan.php
 * Halaman Pusat Bantuan & Tanya Jawab (FAQ) Sisi Pelanggan SnapRent (Tahap C6).
 * Memuat pertanyaan umum dalam bentuk akordeon aksesibel (keyboard & pembaca layar).
 * Nilai denda dibaca dinamis langsung dari tabel database PostgreSQL 'denda', bukan hardcoded.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/koneksi.php';

// ── Ambil Aturan Denda Aktif Langsung dari Tabel denda Database ────────────────
$daftarDenda = [];
try {
    $stmtDenda = $pdo->query('
        SELECT id_denda, jenis_denda, batas_min_jam, batas_max_jam, nilai_denda, keterangan
        FROM denda
        ORDER BY id_denda ASC
    ');
    $daftarDenda = $stmtDenda->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[SnapRent Bantuan] Gagal membaca tabel denda: ' . $e->getMessage());
}

// Ekstrak parameter denda dari baris tabel database
$dendaToleransi = $daftarDenda[0] ?? ['batas_max_jam' => 1, 'nilai_denda' => 0];
$dendaFlat      = $daftarDenda[1] ?? ['batas_min_jam' => 1, 'batas_max_jam' => 3, 'nilai_denda' => 50000];
$dendaHarian    = $daftarDenda[2] ?? ['batas_min_jam' => 3, 'nilai_denda' => 0];

$jamToleransi     = (int) ($dendaToleransi['batas_max_jam'] ?? 1);
$jamFlatMin       = (int) ($dendaFlat['batas_min_jam'] ?? 1);
$jamFlatMax       = (int) ($dendaFlat['batas_max_jam'] ?? 3);
$nominalDendaFlat = (float) ($dendaFlat['nilai_denda'] ?? 50000);
$jamHarianMin     = (int) ($dendaHarian['batas_min_jam'] ?? 3);

$toko = ambil_pengaturan_toko();
$waClean = preg_replace('/\D+/', '', $toko['kontak']);

$judul_halaman = 'Pusat Bantuan & Tanya Jawab';
require_once __DIR__ . '/includes/header.php';
?>

<div class="wadah-pelanggan max-w-4xl space-y-10 sm:space-y-12">

    <!-- ══════════════════════════════════════════════════════════════════════
         1. HEADER PUSAT BANTUAN (Sesuai docs/Wireframe/Customer/Pusat Bantuan.png)
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="text-center max-w-2xl mx-auto space-y-3 pt-2 sm:pt-4">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 text-slate-800 text-[11px] font-bold uppercase tracking-wider">
            Bantuan &amp; FAQ
        </span>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-[#111111] tracking-tight m-0">
            Pusat Bantuan SnapRent
        </h1>
        <p class="text-xs sm:text-sm text-gray-600 m-0 leading-relaxed">
            Temukan jawaban lengkap seputar tata cara penyewaan kamera, jaminan fisik, aturan denda, hingga pembayaran di bawah ini.
        </p>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════
         2. DAFTAR PERTANYAAN AKORDEON (Aksesibel Keyboard & Screen Reader)
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="akordeon-grup" role="region" aria-label="Daftar Pertanyaan Umum SnapRent">

        <!-- Pertanyaan 1: Syarat Jaminan Fisik -->
        <details class="akordeon-item group" name="faq" open>
            <summary class="akordeon-summary">
                <span>Q: Apa saja syarat jaminan identitas fisik untuk menyewa?</span>
                <svg class="akordeon-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="akordeon-konten space-y-2">
                <p class="m-0">
                    A: Untuk menjaga keamanan inventaris, SnapRent mewajibkan penyerahan <strong>jaminan dokumen identitas fisik asli</strong> secara langsung di lokasi toko saat serah terima alat:
                </p>
                <ul class="list-disc list-inside space-y-1 text-gray-700 pl-1 m-0">
                    <li><strong>1 Kartu Identitas Utama (Wajib):</strong> KTP Asli sesuai dengan nama pemesan yang terdaftar.</li>
                    <li><strong>2 Kartu Identitas Pendukung Asli:</strong> Pilihan kombinasi dari SIM, Kartu Tanda Mahasiswa (KTM), Kartu Pelajar, STNK kendaraan, atau Paspor asli yang masih berlaku.</li>
                </ul>
                <p class="text-xs text-gray-500 m-0 border-t border-gray-100 pt-2">
                    <em>Catatan: Dokumen fisik tidak perlu diunggah saat checkout online. Cukup bawa fisik kartu asli saat pengambilan alat di toko. Seluruh dokumen disimpan aman di lemari arsip dan dikembalikan utuh saat masa sewa selesai.</em>
                </p>
            </div>
        </details>

        <!-- Pertanyaan 2: Metode Pengambilan & Pengembalian -->
        <details class="akordeon-item group" name="faq">
            <summary class="akordeon-summary">
                <span>Q: Apa saja pilihan metode pengambilan dan pengembalian unit?</span>
                <svg class="akordeon-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="akordeon-konten space-y-2">
                <p class="m-0">
                    A: Kami menyediakan satu metode resmi: <strong>Self Pick-up (Ambil Langsung di Toko)</strong>. Anda dapat mengambil dan mengembalikan peralatan secara langsung ke lokasi toko kami sesuai jam operasional resmi (<strong><?= e($toko['jam_operasional']) ?></strong>).
                </p>
                <p class="m-0 text-gray-600">
                    Metode ini memastikan Anda dan tim kami dapat melakukan pengecekan fisik serta pengetesan fungsi kamera bersama-sama demi kenyamanan kedua belah pihak.
                </p>
            </div>
        </details>

        <!-- Pertanyaan 3: Kapan Bisa Mulai Mengambil Unit -->
        <details class="akordeon-item group" name="faq">
            <summary class="akordeon-summary">
                <span>Q: Kapan saya bisa mulai mengambil unit yang sudah disewa?</span>
                <svg class="akordeon-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="akordeon-konten space-y-2">
                <p class="m-0">
                    A: Unit kamera dapat diambil pada <strong>Tanggal Ambil (Hari-H)</strong> yang Anda pilih saat checkout, setelah pesanan diverifikasi oleh Admin. Tim Admin SnapRent akan menghubungi Anda via WhatsApp saat unit telah selesai dipersiapkan dan siap diambil.
                </p>
            </div>
        </details>

        <!-- Pertanyaan 4: Perhitungan 24 Jam & Durasi Sewa -->
        <details class="akordeon-item group" name="faq">
            <summary class="akordeon-summary">
                <span>Q: Bagaimana sistem perhitungan lama sewa dan durasi 24 jam?</span>
                <svg class="akordeon-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="akordeon-konten space-y-2">
                <p class="m-0">
                    A: Sewa di SnapRent dihitung berbasis <strong>24 jam penuh per hari</strong> (dibulatkan ke atas jika ada kelebihan waktu, minimal 1 hari sewa).
                </p>
                <p class="m-0 text-gray-700">
                    Jam mulai sewa <strong>tidak dihitung saat Anda memesan di web</strong>, melainkan baru mulai berjalan ketika Anda dan Admin melakukan serah terima alat di toko dan Admin menekan tombol <em>Mulai Sewa</em> di sistem. Misalnya, Anda mengambil kamera hari Senin pukul 10.00 WIB untuk durasi 1 hari, maka batas pengembalian adalah hari Selasa pukul 10.00 WIB.
                </p>
            </div>
        </details>

        <!-- Pertanyaan 5: Aturan Denda Keterlambatan (DIBACA DARI TABEL DENDA) -->
        <details class="akordeon-item group" name="faq">
            <summary class="akordeon-summary">
                <span>Q: Bagaimana aturan denda jika terlambat mengembalikan alat?</span>
                <svg class="akordeon-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="akordeon-konten space-y-2.5">
                <p class="m-0">
                    A: Perhitungan denda keterlambatan di SnapRent ditegakkan langsung secara otomatis oleh sistem database kami dengan tingkatan progresif:
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-1">
                    <!-- Tingkat 1: Toleransi -->
                    <div class="p-3 rounded-xl bg-gray-50 border border-gray-200">
                        <span class="text-[10px] uppercase font-bold text-gray-500 block">Tingkat 1 &bull; Toleransi</span>
                        <span class="font-bold text-[#111111] text-xs block mt-0.5">
                            Telat &le; <?= (int) $jamToleransi ?> Jam
                        </span>
                        <span class="text-emerald-700 font-extrabold text-xs block mt-1">
                            Bebas Denda (Rp 0)
                        </span>
                        <p class="text-[11px] text-gray-500 mt-1 m-0">Masa toleransi keterlambatan tanpa dikenakan denda tambahan.</p>
                    </div>

                    <!-- Tingkat 2: Nominal Tetap (Dari Database) -->
                    <div class="p-3 rounded-xl bg-amber-50/70 border border-amber-200">
                        <span class="text-[10px] uppercase font-bold text-amber-800 block">Tingkat 2 &bull; Denda Flat</span>
                        <span class="font-bold text-amber-950 text-xs block mt-0.5">
                            Telat <?= (int) $jamFlatMin ?> s.d. <?= (int) $jamFlatMax ?> Jam
                        </span>
                        <span class="text-amber-800 font-extrabold text-xs block mt-1">
                            <?= e(format_rupiah($nominalDendaFlat)) ?>
                        </span>
                        <p class="text-[11px] text-amber-900/80 mt-1 m-0">Denda nominal tetap sesuai aturan database aktif.</p>
                    </div>

                    <!-- Tingkat 3: Sewa Harian Penuh (Dari Database) -->
                    <div class="p-3 rounded-xl bg-red-50/70 border border-red-200">
                        <span class="text-[10px] uppercase font-bold text-red-800 block">Tingkat 3 &bull; Sewa Harian</span>
                        <span class="font-bold text-red-950 text-xs block mt-0.5">
                            Telat &gt; <?= (int) $jamHarianMin ?> Jam
                        </span>
                        <span class="text-red-700 font-extrabold text-xs block mt-1">
                            Sewa 1 Hari Penuh
                        </span>
                        <p class="text-[11px] text-red-900/80 mt-1 m-0">Dihitung sebesar tarif sewa 1 hari seluruh unit pada transaksi.</p>
                    </div>
                </div>
            </div>
        </details>

        <!-- Pertanyaan 6: Metode & Verifikasi Pembayaran -->
        <details class="akordeon-item group" name="faq">
            <summary class="akordeon-summary">
                <span>Q: Bagaimana cara membayar dan bagaimana proses verifikasinya?</span>
                <svg class="akordeon-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="akordeon-konten space-y-2">
                <p class="m-0">
                    A: Kami menyediakan metode pembayaran non-tunai melalui <strong>Transfer Bank (BCA)</strong> dan <strong>QRIS</strong>.
                </p>
                <p class="m-0 text-gray-700">
                    Saat checkout di website, Anda dapat memilih skema <strong>Lunas (100%)</strong> atau <strong>DP 50%</strong> (sisa dilunasi saat pengambilan alat). Anda wajib mengunggah file bukti transfer/struk QRIS. Seluruh pembayaran akan <strong>diverifikasi secara manual</strong> oleh Admin toko sebelum unit kamera diserahkan.
                </p>
            </div>
        </details>

        <!-- Pertanyaan 7: Pembaruan Status Transaksi via WhatsApp -->
        <details class="akordeon-item group" name="faq">
            <summary class="akordeon-summary">
                <span>Q: Bagaimana cara mengecek dan menerima pembaruan status pesanan saya?</span>
                <svg class="akordeon-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="akordeon-konten space-y-2">
                <p class="m-0">
                    A: Anda tidak perlu mengecek secara manual di website karena seluruh <strong>pembaruan status transaksi</strong> (verifikasi bukti transfer/QRIS, persetujuan pesanan, pengingat jadwal pengambilan, hingga pengembalian) akan <strong>dikirimkan langsung oleh Admin SnapRent melalui pesan WhatsApp</strong> ke nomor telepon yang Anda daftarkan saat checkout. Cukup simpan bukti pesanan berisikan <strong>Kode Pesanan</strong> resmi Anda (contoh: <code>SNP-000001</code>) untuk ditunjukkan kepada Admin saat serah terima peralatan di toko.
                </p>
            </div>
        </details>

        <!-- Pertanyaan 8: Pembatalan Pesanan -->
        <details class="akordeon-item group" name="faq">
            <summary class="akordeon-summary">
                <span>Q: Bagaimana jika saya ingin membatalkan pesanan?</span>
                <svg class="akordeon-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="akordeon-konten space-y-2">
                <p class="m-0">
                    A: Jika Anda perlu membatalkan pesanan karena kendala jadwal, harap segera <strong>menghubungi Admin SnapRent melalui WhatsApp (<?= e($toko['kontak']) ?>)</strong> sebelum jadwal pengambilan unit kamera tiba. Admin kami akan membantu memproses pembatalan pesanan Anda di sistem dasbor.
                </p>
            </div>
        </details>

        <!-- Pertanyaan 9: Pengecekan Kondisi Alat -->
        <details class="akordeon-item group" name="faq">
            <summary class="akordeon-summary">
                <span>Q: Bagaimana proses pengecekan kondisi alat saat penyerahan dan pengembalian?</span>
                <svg class="akordeon-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="akordeon-konten space-y-2">
                <p class="m-0">
                    A: Sebelum unit diserahterimakan, tim kami bersama penyewa akan melakukan pengecekan fisik, fungsi tombol, kebersihan sensor/optik lensa, dan kelengkapan aksesoris (baterai, charger, memory card, strap, lens cap, tas). Hasil pengecekan dicatat bersama demi menjaga transparansi kondisi awal dan akhir barang.
                </p>
            </div>
        </details>

    </div>

    <!-- ══════════════════════════════════════════════════════════════════════
         3. BANTUAN LEBIH LANJUT (Kontak Admin)
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="p-6 sm:p-8 rounded-3xl bg-gray-50 border border-gray-200 text-center space-y-3">
        <h2 class="text-lg sm:text-xl font-bold text-[#111111] m-0">Masih Memiliki Pertanyaan Lain?</h2>
        <p class="text-xs sm:text-sm text-gray-600 max-w-lg mx-auto m-0 leading-relaxed">
            Tim layanan pelanggan SnapRent siap membantu menjawab pertanyaan Anda seputar rekomendasi kamera, ketersediaan khusus, maupun kebutuhan sewa proyek visual Anda.
        </p>
        <div class="pt-2 flex flex-wrap justify-center gap-3">
            <a href="https://wa.me/<?= e($waClean) ?>" target="_blank" rel="noopener noreferrer" class="btn-utama px-6 py-2.5 text-xs">
                Hubungi Admin via WhatsApp (<?= e($toko['kontak']) ?>)
            </a>
            <a href="<?= e(base_url('tentang.php')) ?>" class="btn-sekunder px-6 py-2.5 text-xs">
                Pelajari Tentang SnapRent →
            </a>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
