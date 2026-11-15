<?php
/**
 * tentang.php
 * Halaman Tentang Kami Sisi Pelanggan SnapRent (Tahap C6).
 * Memuat profil singkat rental, nilai/keunggulan, alur sewa 4 langkah, serta informasi operasional dan kontak.
 */

$judul_halaman = 'Tentang Kami';
require_once __DIR__ . '/includes/header.php';

$toko = ambil_pengaturan_toko();
?>

<div class="wadah-pelanggan space-y-12 sm:space-y-16">

    <!-- ══════════════════════════════════════════════════════════════════════
         1. HERO SECTION (Sesuai docs/Wireframe/Customer/Tentang kami.png)
         ═══════════════════════════════════════════════════════════════════ -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start pt-2 sm:pt-4">
        
        <!-- Kolom Kiri: Judul Utama, Tagline & Kotak Info -->
        <div class="lg:col-span-5 space-y-6">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 text-slate-800 text-[11px] font-bold uppercase tracking-wider mb-3">
                    Profil Rental
                </span>
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-black text-[#111111] tracking-tight m-0">
                    SnapRent
                </h1>
                <p class="text-sm sm:text-base text-gray-600 mt-3 leading-relaxed font-medium">
                    Solusi Sewa Kamera &amp; Peralatan Fotografi Praktis, Cepat dan Terpercaya.
                </p>
            </div>

            <!-- Kotak Info Operasional (Dapat Diubah oleh Admin) -->
            <div class="space-y-3 pt-2">
                <!-- Jam Operasional -->
                <div class="p-4 sm:p-5 rounded-2xl bg-[#eaecf0]/60 border border-gray-200/80 transition-all hover:bg-white hover:shadow-sm">
                    <div class="flex items-center gap-2.5 text-xs font-bold text-[#111111] uppercase tracking-wider mb-1.5">
                        <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Jam Operasional :</span>
                    </div>
                    <p class="text-xs sm:text-sm font-semibold text-gray-800 m-0 leading-relaxed">
                        <?= e($toko['jam_operasional']) ?>
                    </p>
                </div>

                <!-- Lokasi Toko -->
                <div class="p-4 sm:p-5 rounded-2xl bg-[#eaecf0]/60 border border-gray-200/80 transition-all hover:bg-white hover:shadow-sm">
                    <div class="flex items-center gap-2.5 text-xs font-bold text-[#111111] uppercase tracking-wider mb-1.5">
                        <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span>Lokasi :</span>
                    </div>
                    <p class="text-xs sm:text-sm font-semibold text-gray-800 m-0 leading-relaxed">
                        <?= e($toko['lokasi']) ?>
                    </p>
                </div>

                <!-- Kontak Toko -->
                <div class="p-4 sm:p-5 rounded-2xl bg-[#eaecf0]/60 border border-gray-200/80 transition-all hover:bg-white hover:shadow-sm">
                    <div class="flex items-center gap-2.5 text-xs font-bold text-[#111111] uppercase tracking-wider mb-1.5">
                        <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        <span>Kontak WhatsApp / Telepon :</span>
                    </div>
                    <p class="text-xs sm:text-sm font-semibold text-gray-800 m-0 leading-relaxed font-mono">
                        <?= e($toko['kontak']) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Deskripsi Profil Lengkap (Sesuai Wireframe) -->
        <div class="lg:col-span-7 space-y-6">
            <div class="p-6 sm:p-8 rounded-3xl bg-white border border-gray-200/90 shadow-sm leading-relaxed text-gray-700 text-sm sm:text-base space-y-4">
                <p class="font-normal text-slate-800 text-base sm:text-lg leading-relaxed">
                    <strong>SnapRent</strong> hadir untuk memberikan kemudahan bagi para fotografer, videografer, kreator konten, maupun penghobi dalam mengakses peralatan visual berkualitas tinggi.
                </p>
                <p>
                    Melalui platform ini, Anda dapat menjelajahi berbagai pilihan kamera, lensa, hingga perlengkapan pendukung lainnya secara online, menentukan jadwal sewa sesuai kebutuhan, dan melakukan pemesanan dengan cepat tanpa proses yang rumit.
                </p>
                <p class="text-xs sm:text-sm text-gray-600 border-t border-gray-100 pt-3">
                    Kami berkomitmen menjaga setiap unit peralatan dalam performa optimal melalui kalibrasi dan pemeriksaan fisik rutin sebelum serah terima alat dilakukan.
                </p>
            </div>

            <!-- Mini Banner Aksi Cepat -->
            <div class="p-5 sm:p-6 rounded-3xl bg-[#111111] text-white flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <h2 class="text-base sm:text-lg font-bold m-0">Siap Mengabadikan Momen?</h2>
                    <p class="text-xs text-gray-300 mt-1 m-0">Temukan 170+ pilihan bodi kamera, lensa, stabilizer, dan lighting kami.</p>
                </div>
                <a href="<?= e(base_url('index.php')) ?>" class="btn-utama bg-white text-[#111111] hover:bg-gray-100 px-6 py-2.5 text-xs whitespace-nowrap">
                    Lihat Katalog Sekarang →
                </a>
            </div>
        </div>

    </section>

    <!-- ══════════════════════════════════════════════════════════════════════
         2. NILAI & KEUNGGULAN SNAPRENT
         ═══════════════════════════════════════════════════════════════════ -->
    <section class="space-y-6 pt-4 border-t border-gray-200/80">
        <div class="text-center max-w-xl mx-auto space-y-2">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-[#111111] tracking-tight m-0">
                Mengapa Memilih SnapRent?
            </h2>
            <p class="text-xs sm:text-sm text-gray-600 m-0">
                Nilai dan standar pelayanan yang kami jaga untuk mendukung kelancaran proyek fotografi dan videografi Anda.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
            <!-- Keunggulan 1 -->
            <div class="p-5 rounded-2xl bg-white border border-gray-200/80 shadow-sm space-y-3 hover:border-gray-400 transition-colors">
                <div class="w-10 h-10 rounded-xl bg-gray-100 text-[#111111] flex items-center justify-center font-bold text-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-[#111111] m-0">Unit Terawat &amp; Terkalibrasi</h3>
                <p class="text-xs text-gray-600 m-0 leading-relaxed">
                    Sensor bersih, optik lensa jernih, tombol responsif, dan baterai sehat diperiksa bersama sebelum serah terima unit.
                </p>
            </div>

            <!-- Keunggulan 2 -->
            <div class="p-5 rounded-2xl bg-white border border-gray-200/80 shadow-sm space-y-3 hover:border-gray-400 transition-colors">
                <div class="w-10 h-10 rounded-xl bg-gray-100 text-[#111111] flex items-center justify-center font-bold text-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-[#111111] m-0">Perhitungan 24 Jam Penuh</h3>
                <p class="text-xs text-gray-600 m-0 leading-relaxed">
                    Durasi sewa baru mulai berjalan saat Anda mengambil alat di toko, bukan saat waktu pemesanan di website.
                </p>
            </div>

            <!-- Keunggulan 3 -->
            <div class="p-5 rounded-2xl bg-white border border-gray-200/80 shadow-sm space-y-3 hover:border-gray-400 transition-colors">
                <div class="w-10 h-10 rounded-xl bg-gray-100 text-[#111111] flex items-center justify-center font-bold text-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-[#111111] m-0">Anti Bentrok Stok Real-Time</h3>
                <p class="text-xs text-gray-600 m-0 leading-relaxed">
                    Sistem database otomatis mengunci stok unit per tanggal sewa sehingga pesanan Anda terjamin aman dari overbooking.
                </p>
            </div>

            <!-- Keunggulan 4 -->
            <div class="p-5 rounded-2xl bg-white border border-gray-200/80 shadow-sm space-y-3 hover:border-gray-400 transition-colors">
                <div class="w-10 h-10 rounded-xl bg-gray-100 text-[#111111] flex items-center justify-center font-bold text-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-[#111111] m-0">Jaminan Aman &amp; Transparan</h3>
                <p class="text-xs text-gray-600 m-0 leading-relaxed">
                    Dokumen jaminan fisik disimpan aman di lemari arsip toko dan langsung dikembalikan utuh saat masa sewa selesai.
                </p>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════════════════════════════
         3. CARA KERJA SEWA DALAM 4 LANGKAH MUDAH
         ═══════════════════════════════════════════════════════════════════ -->
    <section class="space-y-6 pt-4 border-t border-gray-200/80">
        <div class="text-center max-w-xl mx-auto space-y-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 text-slate-800 text-[11px] font-bold uppercase tracking-wider">
                Alur Penyewaan
            </span>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-[#111111] tracking-tight m-0">
                Cara Kerja Sewa di SnapRent
            </h2>
            <p class="text-xs sm:text-sm text-gray-600 m-0">
                4 langkah praktis mulai dari pemilihan unit kamera hingga pengembalian.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <!-- Langkah 1 -->
            <div class="p-6 rounded-2xl bg-white border border-gray-200/90 shadow-sm relative space-y-3">
                <div class="w-8 h-8 rounded-full bg-[#111111] text-white flex items-center justify-center font-bold text-xs">
                    1
                </div>
                <h3 class="text-sm sm:text-base font-bold text-[#111111] m-0">Pilih Tanggal &amp; Barang</h3>
                <p class="text-xs text-gray-600 leading-relaxed m-0">
                    Jelajahi katalog kami, pilih tanggal ambil (Hari-H) dan durasi sewa, lalu tambahkan kamera atau lensa yang dibutuhkan ke keranjang.
                </p>
            </div>

            <!-- Langkah 2 -->
            <div class="p-6 rounded-2xl bg-white border border-gray-200/90 shadow-sm relative space-y-3">
                <div class="w-8 h-8 rounded-full bg-[#111111] text-white flex items-center justify-center font-bold text-xs">
                    2
                </div>
                <h3 class="text-sm sm:text-base font-bold text-[#111111] m-0">Checkout &amp; Unggah Bukti</h3>
                <p class="text-xs text-gray-600 leading-relaxed m-0">
                    Isi nama lengkap dan nomor WhatsApp, pilih skema pembayaran (Lunas atau DP 50%), lalu transfer dan unggah struk bukti transfer.
                </p>
            </div>

            <!-- Langkah 3 -->
            <div class="p-6 rounded-2xl bg-white border border-gray-200/90 shadow-sm relative space-y-3">
                <div class="w-8 h-8 rounded-full bg-[#111111] text-white flex items-center justify-center font-bold text-xs">
                    3
                </div>
                <h3 class="text-sm sm:text-base font-bold text-[#111111] m-0">Ambil Unit &amp; Serahkan ID</h3>
                <p class="text-xs text-gray-600 leading-relaxed m-0">
                    Datang ke toko membawa kode pesanan dan <strong>1 KTP Asli + 2 ID Pendukung</strong>. Cek kondisi alat bersama admin sebelum mulai sewa.
                </p>
            </div>

            <!-- Langkah 4 -->
            <div class="p-6 rounded-2xl bg-white border border-gray-200/90 shadow-sm relative space-y-3">
                <div class="w-8 h-8 rounded-full bg-[#111111] text-white flex items-center justify-center font-bold text-xs">
                    4
                </div>
                <h3 class="text-sm sm:text-base font-bold text-[#111111] m-0">Kembalikan Tepat Waktu</h3>
                <p class="text-xs text-gray-600 leading-relaxed m-0">
                    Kembalikan alat sesuai jadwal rencana kembali. Admin akan memeriksa fungsi unit dan langsung mengembalikan seluruh kartu identitas Anda.
                </p>
            </div>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
