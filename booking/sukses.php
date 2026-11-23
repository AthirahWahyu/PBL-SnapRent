<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/koneksi.php';

// 1. Pastikan hanya dapat diakses bila id_transaksi_terakhir ada di session
$idTransaksi = isset($_SESSION['id_transaksi_terakhir']) ? (int) $_SESSION['id_transaksi_terakhir'] : 0;
if ($idTransaksi <= 0) {
    flash_set('error', 'Tidak ada riwayat pesanan yang baru selesai diproses. Silakan pilih peralatan terlebih dahulu.');
    redirect('index.php');
}

// 2. Ambil data transaksi & penyewa langsung dari database
$stmtTrx = $pdo->prepare('
    SELECT
        t.id_transaksi,
        t.nomor_transaksi,
        t.waktu_mulai_sewa,
        t.waktu_rencana_kembali,
        t.total_tagihan,
        t.nominal_bayar_awal,
        t.jenis_pembayaran,
        t.skema_pembayaran,
        t.status_pembayaran,
        t.status_transaksi,
        t.bukti_pembayaran,
        t.waktu_pemesanan,
        fn_hitung_hari(t.waktu_mulai_sewa, t.waktu_rencana_kembali) AS durasi_hari,
        p.nama_penyewa,
        p.no_telepon
    FROM transaksi t
    JOIN penyewa p ON p.id_penyewa = t.id_penyewa
    WHERE t.id_transaksi = :id
    LIMIT 1
');
$stmtTrx->execute([':id' => $idTransaksi]);
$trx = $stmtTrx->fetch(PDO::FETCH_ASSOC);

if (!$trx) {
    unset($_SESSION['id_transaksi_terakhir']);
    flash_set('error', 'Data pesanan tidak ditemukan.');
    redirect('index.php');
}

// 3. Ambil rincian barang dari detail_transaksi (harga_satuan & subtotal hasil hitungan trigger DB)
$stmtDetail = $pdo->prepare('
    SELECT
        dt.id_barang,
        dt.jumlah,
        dt.harga_satuan,
        dt.subtotal,
        b.nama_barang,
        b.foto_barang,
        k.nama_kategori
    FROM detail_transaksi dt
    JOIN barang b ON b.id_barang = dt.id_barang
    JOIN kategori k ON k.id_kategori = b.id_kategori
    WHERE dt.id_transaksi = :id
    ORDER BY dt.id_barang ASC
');
$stmtDetail->execute([':id' => $idTransaksi]);
$daftarItem = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

// Format kode pesanan & tanggal
$kodePesananSnp = sprintf('SNP-%06d', (int) $trx['id_transaksi']);
$nomorTrxResmi  = (string) ($trx['nomor_transaksi'] ?? '');
$durasiHari     = max(1, (int) ($trx['durasi_hari'] ?? 1));
$totalTagihan   = (float) ($trx['total_tagihan'] ?? 0);
$nominalAwal    = (float) ($trx['nominal_bayar_awal'] ?? $totalTagihan);
$sisaPelunasan  = max(0.0, $totalTagihan - $nominalAwal);

$tzJakarta = new DateTimeZone('Asia/Jakarta');
$dtMulai   = new DateTimeImmutable((string) $trx['waktu_mulai_sewa'], $tzJakarta);
$dtKembali = new DateTimeImmutable((string) $trx['waktu_rencana_kembali'], $tzJakarta);
$dtDibuat  = new DateTimeImmutable((string) $trx['waktu_pemesanan'], $tzJakarta);

$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$labelTglAmbil   = (int) $dtMulai->format('d') . ' ' . ($namaBulan[(int) $dtMulai->format('n')] ?? '') . ' ' . $dtMulai->format('Y');
$labelTglKembali = (int) $dtKembali->format('d') . ' ' . ($namaBulan[(int) $dtKembali->format('n')] ?? '') . ' ' . $dtKembali->format('Y');
$labelWaktuBuat  = (int) $dtDibuat->format('d') . ' ' . ($namaBulan[(int) $dtDibuat->format('n')] ?? '') . ' ' . $dtDibuat->format('Y') . ', ' . $dtDibuat->format('H:i') . ' WIB';

$mapLabelMetode = [
    'transfer' => 'Transfer Bank (BCA)',
    'qris'     => 'QRIS (Transfer QR)',
];
$labelMetode = $mapLabelMetode[$trx['jenis_pembayaran']] ?? ucfirst((string) $trx['jenis_pembayaran']);

$labelSkema = ($trx['skema_pembayaran'] === 'dp_50')
    ? 'DP 50% (Pelunasan Saat Ambil Alat)'
    : 'Bayar Lunas (100%)';

$totalUnit = 0;
foreach ($daftarItem as $it) {
    $totalUnit += (int) $it['jumlah'];
}

$judul_halaman = 'Pesanan Berhasil #' . $kodePesananSnp;
require_once __DIR__ . '/../includes/header.php';
?>

<section class="py-8 md:py-12">
    <div class="wadah-pelanggan max-w-3xl mx-auto">

        <!-- Kartu Utama Bukti Pemesanan -->
        <div class="checkout-section-card overflow-hidden">

            <!-- Banner Atas Sukses -->
            <div class="bg-emerald-50/70 border-b border-emerald-200/80 px-6 py-7 text-center">
                <div class="w-16 h-16 rounded-full bg-emerald-600 text-white flex items-center justify-center mx-auto mb-4 shadow-md">
                    <i class="fa-solid fa-check text-2xl"></i>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-100 text-amber-800 text-[11px] font-bold uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-clock"></i> Menunggu Verifikasi Admin
                </span>
                <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">
                    Pesanan Penyewaan Berhasil Dibuat!
                </h1>
                <p class="text-xs md:text-sm text-slate-600 mt-1.5 max-w-lg mx-auto">
                    Terima kasih, <strong><?= e($trx['nama_penyewa']) ?></strong>. Simpan atau cetak bukti pesanan ini dan tunjukkan kepada Admin saat pengambilan unit di toko.
                </p>

                <!-- Kotak Kode Pesanan -->
                <div class="mt-5 inline-flex flex-col sm:flex-row items-center gap-3 sm:gap-5 px-5 py-3.5 rounded-2xl bg-white border-2 border-dashed border-emerald-300 shadow-sm">
                    <div class="text-center sm:text-left">
                        <span class="block text-[10px] font-bold uppercase tracking-widest text-slate-400">Kode Pesanan</span>
                        <span class="text-xl md:text-2xl font-extrabold text-slate-900 tracking-wider font-mono">
                            <?= e($kodePesananSnp) ?>
                        </span>
                    </div>
                    <?php if ($nomorTrxResmi !== ''): ?>
                        <div class="hidden sm:block w-px h-8 bg-slate-200"></div>
                        <div class="text-center sm:text-left">
                            <span class="block text-[10px] font-bold uppercase tracking-widest text-slate-400">No. Resi Sistem</span>
                            <span class="text-sm font-bold text-slate-700 font-mono">#<?= e($nomorTrxResmi) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Isi Detail Pesanan -->
            <div class="p-6 md:p-8 space-y-6">

                <!-- 1. Informasi Penyewa & Jadwal -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-xl bg-slate-50 border border-slate-200/80 text-xs">
                    <div class="space-y-2.5">
                        <div>
                            <span class="text-[11px] text-slate-400 block">Nama Penyewa</span>
                            <span class="font-bold text-slate-800 text-sm"><?= e($trx['nama_penyewa']) ?></span>
                        </div>
                        <div>
                            <span class="text-[11px] text-slate-400 block">Nomor WhatsApp / Telepon</span>
                            <span class="font-semibold text-slate-700 font-mono"><?= e($trx['no_telepon']) ?></span>
                        </div>
                        <div>
                            <span class="text-[11px] text-slate-400 block">Waktu Pemesanan</span>
                            <span class="font-medium text-slate-600"><?= e($labelWaktuBuat) ?></span>
                        </div>
                    </div>

                    <div class="space-y-2.5">
                        <div>
                            <span class="text-[11px] text-slate-400 block">Tanggal Ambil (Hari-H) & Durasi</span>
                            <span class="font-bold text-slate-800">
                                <?= e($labelTglAmbil) ?>
                                <span class="inline-block ml-1 px-2 py-0.5 rounded bg-slate-200/80 text-slate-800 text-[11px]">
                                    <?= (int) $durasiHari ?> Hari
                                </span>
                            </span>
                            <span class="block text-[11px] text-slate-500 mt-0.5">
                                Estimasi kembali: <?= e($labelTglKembali) ?> (jam mulai dihitung saat serah terima alat)
                            </span>
                        </div>
                        <div>
                            <span class="text-[11px] text-slate-400 block">Metode & Skema Pembayaran</span>
                            <span class="font-semibold text-slate-800"><?= e($labelMetode) ?></span>
                            <span class="block text-[11px] text-slate-500"><?= e($labelSkema) ?></span>
                            <?php if (!empty($trx['bukti_pembayaran'])): ?>
                                <a href="<?= e(base_url($trx['bukti_pembayaran'])) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-600 hover:underline mt-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Lihat Bukti Pembayaran yang Diunggah ↗
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 2. Rincian Peralatan yang Disewa (Dari Database) -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            Rincian Peralatan Disewa
                        </h2>
                        <span class="text-xs font-semibold text-slate-500">
                            <?= count($daftarItem) ?> Jenis &bull; <?= (int) $totalUnit ?> Unit
                        </span>
                    </div>

                    <div class="border border-slate-200 rounded-xl divide-y divide-slate-100 overflow-hidden">
                        <?php foreach ($daftarItem as $item): ?>
                            <div class="p-3.5 flex items-center justify-between gap-3 bg-white">
                                <div class="min-w-0 flex-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">
                                        <?= e($item['nama_kategori']) ?>
                                    </span>
                                    <h3 class="text-xs sm:text-sm font-bold text-slate-800 truncate">
                                        <?= e($item['nama_barang']) ?>
                                    </h3>
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        <?= e(format_rupiah((float) $item['harga_satuan'])) ?> / hari
                                        &times; <strong><?= (int) $item['jumlah'] ?> unit</strong>
                                        &times; <?= (int) $durasiHari ?> hari
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-xs sm:text-sm font-bold text-slate-900">
                                        <?= e(format_rupiah((float) $item['subtotal'])) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 3. Ringkasan Total Biaya (Langsung dari Tabel transaksi) -->
                <div class="p-4 rounded-xl bg-slate-900 text-white space-y-2">
                    <div class="flex items-center justify-between text-xs text-slate-300">
                        <span>Total Tagihan Sewa (<?= (int) $durasiHari ?> Hari)</span>
                        <span class="font-bold text-base text-white"><?= e(format_rupiah($totalTagihan)) ?></span>
                    </div>

                    <?php if ($trx['skema_pembayaran'] === 'dp_50'): ?>
                        <div class="pt-2 border-t border-slate-700/80 flex items-center justify-between text-xs">
                            <span class="text-amber-300 font-semibold">Nominal Bayar Awal (DP 50%)</span>
                            <span class="font-bold text-amber-300 text-sm"><?= e(format_rupiah($nominalAwal)) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>Sisa Pelunasan Saat Ambil Unit</span>
                            <span><?= e(format_rupiah($sisaPelunasan)) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="pt-2 border-t border-slate-700/80 flex items-center justify-between text-xs">
                            <span class="text-emerald-300 font-semibold">Nominal yang Harus Dibayar (Lunas)</span>
                            <span class="font-bold text-emerald-300 text-sm"><?= e(format_rupiah($nominalAwal)) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 4. Langkah Selanjutnya & Instruksi Jaminan Fisik -->
                <div class="p-4 sm:p-5 rounded-xl bg-amber-50/90 border border-amber-200/90 space-y-3">
                    <div class="flex items-center gap-2 text-amber-950 font-bold text-xs sm:text-sm">
                        <i class="fa-solid fa-clipboard-list text-amber-600"></i>
                        <span>Langkah Selanjutnya (Penting)</span>
                    </div>
                    <ol class="text-xs text-amber-900/90 space-y-2 list-decimal list-inside leading-relaxed">
                        <li>
                            <strong>Datang ke Toko SnapRent</strong> pada tanggal ambil (<strong><?= e($labelTglAmbil) ?></strong>) dan tunjukkan Kode Pesanan <strong><?= e($kodePesananSnp) ?></strong> kepada Admin.
                        </li>
                        <li>
                            <strong>Bawa Jaminan Identitas Fisik Asli:</strong> Wajib menyerahkan minimal <strong>1 KTP Asli</strong> ditambah <strong>2 Kartu Identitas Pendukung Asli</strong> (SIM / Kartu Pelajar / KTM / Kartu Keluarga / NPWP / STNK) saat serah terima alat.
                        </li>
                        <li>
                            <strong>Verifikasi Pembayaran & Pembaruan Status via WhatsApp:</strong>
                            File bukti transfer/QRIS sebesar <strong><?= e(format_rupiah($nominalAwal)) ?></strong> telah tersimpan di sistem. Admin SnapRent akan memverifikasi mutasi pembayaran dan mengirimkan pembaruan status transaksi secara langsung ke nomor WhatsApp Anda (<strong><?= e($trx['no_telepon']) ?></strong>).
                        </li>
                        <li>
                            <strong>Perhitungan Jam Sewa:</strong> Waktu mulai sewa (24 jam per hari) baru akan berjalan ketika Admin menekan tombol <em>Mulai Sewa</em> saat peralatan diserahkan kepada Anda.
                        </li>
                    </ol>
                </div>

                <!-- 5. Tombol Aksi (Cetak/Simpan & Kembali ke Katalog) -->
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3 no-print">
                    <button type="button"
                            onclick="window.print()"
                            class="btn-sekunder w-full sm:w-auto justify-center py-2.5 px-5 text-xs">
                        <i class="fa-solid fa-print"></i> Cetak / Simpan Bukti Pesanan
                    </button>

                    <a href="<?= e(base_url('index.php')) ?>"
                       class="btn-utama w-full sm:w-auto justify-center py-2.5 px-6 text-xs">
                        <i class="fa-solid fa-camera"></i> Kembali ke Katalog
                    </a>
                </div>

            </div>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
