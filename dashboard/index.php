<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/data_dashboard.php';

wajib_login();

// ── Ambil data 4 kartu statistik ─────────────────────────────────────────────
$total_disewa    = db_total_disewa($pdo);
$sedang_disewa   = db_sedang_disewa($pdo);
$pendapatan_hari = db_pendapatan_hari_ini($pdo);
$pelanggan_baru  = db_pelanggan_baru($pdo);

// ── Paginasi & data Rincian Hari Ini ─────────────────────────────────────────
$halaman       = ambil_int('page', 'get') ?? 1;
$per_halaman   = 10;
$total_baris   = db_hitung_rincian_hari_ini($pdo);
$total_halaman = max(1, (int) ceil($total_baris / $per_halaman));
if ($halaman > $total_halaman) {
    $halaman = $total_halaman;
}
$offset           = ($halaman - 1) * $per_halaman;
$rincian_hari_ini = db_rincian_hari_ini($pdo, $per_halaman, $offset);

$judul_halaman = 'Dashboard';
require_once __DIR__ . '/../includes/header_admin.php';
?>

<!-- Judul Halaman -->
<h1 class="text-[26px] font-extrabold text-[#111111] tracking-tight mb-6">Dashboard</h1>

<!-- ══════════════════════════════════════════════════════════════════════════
     BARIS 1: 4 KARTU STATISTIK HORIZONTAL (Sesuai Wireframe)
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-7">

    <!-- 1. Total Disewa -->
    <div class="bg-white rounded-[24px] px-6 py-5 shadow-sm flex items-center justify-between">
        <div>
            <p class="text-xs font-medium text-gray-500 mb-2">Total Disewa</p>
            <div id="statTotalDisewa" class="text-3xl font-extrabold text-[#111111] leading-none">
                <?= e((string) $total_disewa) ?>
            </div>
        </div>
        <div class="w-12 h-12 rounded-full bg-[#3b82f6] text-white flex items-center justify-center flex-shrink-0 shadow-sm">
            <!-- Ikon Tumpukan Kotak / Paket -->
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="M9 3.75A1.25 1.25 0 0110.25 2.5h3.5A1.25 1.25 0 0115 3.75v4.5a1.25 1.25 0 01-1.25 1.25h-3.5A1.25 1.25 0 019 8.25v-4.5zm2.25-.25v2h1.5v-2h-1.5z"/>
                <path d="M3.5 13.75a1.25 1.25 0 011.25-1.25h4.5a1.25 1.25 0 011.25 1.25v5.5a1.25 1.25 0 01-1.25 1.25h-4.5a1.25 1.25 0 01-1.25-1.25v-5.5zm2.75-.25v2h1.5v-2h-1.5z"/>
                <path d="M13.5 13.75a1.25 1.25 0 011.25-1.25h4.5a1.25 1.25 0 011.25 1.25v5.5a1.25 1.25 0 01-1.25 1.25h-4.5a1.25 1.25 0 01-1.25-1.25v-5.5zm2.75-.25v2h1.5v-2h-1.5z"/>
            </svg>
        </div>
    </div>

    <!-- 2. Sedang Disewa -->
    <div class="bg-white rounded-[24px] px-6 py-5 shadow-sm flex items-center justify-between">
        <div>
            <p class="text-xs font-medium text-gray-500 mb-2">Sedang Disewa</p>
            <div id="statSedangDisewa" class="text-3xl font-extrabold text-[#111111] leading-none">
                <?= e((string) $sedang_disewa) ?>
            </div>
        </div>
        <div class="w-12 h-12 rounded-full bg-[#f59e0b] text-white flex items-center justify-center flex-shrink-0 shadow-sm">
            <!-- Ikon Kamera -->
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 9a3.75 3.75 0 100 7.5A3.75 3.75 0 0012 9z"/>
                <path fill-rule="evenodd" d="M9.344 3.071a49.52 49.52 0 015.312 0c.967.052 1.83.585 2.332 1.39l.821 1.317c.24.383.645.643 1.11.71.386.054.77.113 1.152.177 1.432.239 2.429 1.493 2.429 2.909V18a3 3 0 01-3 3h-15a3 3 0 01-3-3V9.574c0-1.416.997-2.67 2.429-2.909.382-.064.766-.123 1.151-.178a1.56 1.56 0 001.11-.71l.822-1.315a2.942 2.942 0 012.332-1.39zM6.75 12.75a5.25 5.25 0 1110.5 0 5.25 5.25 0 01-10.5 0z" clip-rule="evenodd"/>
            </svg>
        </div>
    </div>

    <!-- 3. Pendapatan Hari Ini -->
    <div class="bg-white rounded-[24px] px-6 py-5 shadow-sm flex items-center justify-between">
        <div class="min-w-0 pr-2">
            <p class="text-xs font-medium text-gray-500 mb-2">Pendapatan Hari Ini</p>
            <div id="statPendapatanHari" class="text-2xl font-extrabold text-[#111111] leading-none truncate">
                <?= e(format_rupiah($pendapatan_hari)) ?>
            </div>
        </div>
        <div class="w-12 h-12 rounded-full bg-[#10b981] text-white flex items-center justify-center flex-shrink-0 shadow-sm">
            <!-- Ikon Dompet -->
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="M4.5 3.75a3 3 0 00-3 3v.75h21v-.75a3 3 0 00-3-3h-15z"/>
                <path fill-rule="evenodd" d="M22.5 9.75h-21v7.5a3 3 0 003 3h15a3 3 0 003-3v-7.5zm-6 3.75a1.5 1.5 0 110 3 1.5 1.5 0 010-3z" clip-rule="evenodd"/>
            </svg>
        </div>
    </div>

    <!-- 4. Pelanggan Baru -->
    <div class="bg-white rounded-[24px] px-6 py-5 shadow-sm flex items-center justify-between">
        <div>
            <p class="text-xs font-medium text-gray-500 mb-2">Pelanggan Baru</p>
            <div id="statPelangganBaru" class="text-3xl font-extrabold text-[#111111] leading-none">
                <?= e((string) $pelanggan_baru) ?>
            </div>
        </div>
        <div class="w-12 h-12 rounded-full bg-[#8b5cf6] text-white flex items-center justify-center flex-shrink-0 shadow-sm">
            <!-- Ikon Tambah Pengguna -->
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="M6.25 6.375a4.125 4.125 0 118.25 0 4.125 4.125 0 01-8.25 0zM3.25 19.125a7.125 7.125 0 0114.25 0v.003l-.001.119a.75.75 0 01-.363.63 13.067 13.067 0 01-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 01-.364-.63l-.001-.122zM19.75 7.5a.75.75 0 00-1.5 0v2.25H16a.75.75 0 000 1.5h2.25v2.25a.75.75 0 001.5 0v-2.25H22a.75.75 0 000-1.5h-2.25V7.5z"/>
            </svg>
        </div>
    </div>

</section>

<!-- ══════════════════════════════════════════════════════════════════════════
     BARIS 2: RINCIAN HARI INI (Sesuai Wireframe)
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="bg-white rounded-[24px] p-6 md:p-8 shadow-sm mb-6">

    <h2 class="text-lg font-bold text-[#111111] mb-4">Rincian Hari Ini</h2>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-black text-white text-xs font-bold">
                    <th class="py-3 px-4 rounded-l-xl w-14">NO</th>
                    <th class="py-3 px-4">Order ID</th>
                    <th class="py-3 px-4">Item</th>
                    <th class="py-3 px-4">Pelanggan</th>
                    <th class="py-3 px-4">Mulai</th>
                    <th class="py-3 px-4">Selesai</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4 rounded-r-xl text-right">Tindakan</th>
                </tr>
            </thead>
            <tbody id="tbodyRincianHariIni" class="text-xs text-gray-800">
                <?php if (empty($rincian_hari_ini)): ?>
                    <tr>
                        <td colspan="8" class="py-16 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <!-- Ikon Tray / Inbox Kosong -->
                                <svg class="w-8 h-8 text-gray-400 mb-2.5" viewBox="0 0 24 24" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.478 5.559A1.5 1.5 0 016.912 4.5H9A.75.75 0 009 3H6.912a3 3 0 00-2.868 2.118l-2.411 7.838a3 3 0 00-.133.882V18a3 3 0 003 3h15a3 3 0 003-3v-4.162c0-.299-.045-.596-.133-.882l-2.412-7.838A3 3 0 0017.088 3H15a.75.75 0 000 1.5h2.088a1.5 1.5 0 011.434 1.059l2.213 7.191H17.89a3 3 0 00-2.684 1.658l-.256.513a1.5 1.5 0 01-1.342.829h-3.218a1.5 1.5 0 01-1.342-.83l-.256-.512a3 3 0 00-2.684-1.658H3.265l2.213-7.191z" clip-rule="evenodd"/>
                                </svg>
                                <p class="text-xs text-gray-400">Belum ada transaksi sewa untuk hari ini.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rincian_hari_ini as $idx => $r):
                        $idTrans   = (int) $r['id_transaksi'];
                        $kodeTrans = sprintf('#SR-%04d', $idTrans);
                        $st        = (string) $r['status_transaksi'];
                        $level     = (string) ($r['level_reminder'] ?? '');
                        $mendekati = !empty($r['mendekati_batas']);

                        $kelasBaris = match (true) {
                            $level === 'merah' => 'bg-rose-50/70',
                            $mendekati         => 'bg-amber-50/70',
                            default            => 'border-b border-gray-100 hover:bg-gray-50/80',
                        };

                        $kelasBadge = match ($st) {
                            'menunggu_verifikasi' => 'badge-menunggu',
                            'menunggu_diambil'    => 'badge-disetujui',
                            'disewa'              => 'badge-disewa',
                            'selesai'             => 'badge-selesai',
                            'dibatalkan'          => 'badge-dibatalkan',
                            default               => 'badge-normal',
                        };
                    ?>
                        <tr class="<?= $kelasBaris ?> transition-colors">
                            <td class="py-3.5 px-4 font-semibold text-gray-500">
                                <?= $offset + $idx + 1 ?>
                            </td>
                            <td class="py-3.5 px-4 font-mono font-bold text-[#111111] whitespace-nowrap">
                                <?= e($kodeTrans) ?>
                            </td>
                            <td class="py-3.5 px-4 max-w-xs truncate font-medium text-gray-900" title="<?= e($r['daftar_barang']) ?>">
                                <?= e($r['daftar_barang']) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-gray-900"><?= e($r['nama_penyewa']) ?></div>
                                <div class="text-[11px] text-gray-400"><?= e($r['no_telepon']) ?></div>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap text-gray-700">
                                <?= e(format_tanggal($r['waktu_mulai_sewa'])) ?>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap text-gray-700">
                                <?= e(format_tanggal($r['waktu_rencana_kembali'])) ?>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="badge <?= $kelasBadge ?>">
                                    <?= e(ucfirst(str_replace('_', ' ', $st))) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <?php if ($st === 'disewa'): ?>
                                    <a href="<?= e(base_url('pengembalian/kembali.php?id=' . $idTrans)) ?>"
                                       class="btn-utama text-xs py-1.5 px-3.5 mr-1">
                                        Kembali
                                    </a>
                                <?php endif; ?>
                                <a href="<?= e(base_url('transaksi/detail.php?id=' . $idTrans)) ?>"
                                   class="btn-sekunder text-xs py-1.5 px-3.5">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginasi Bawah (Sesuai Wireframe) -->
    <?php
    $maksHalamanTampil = max(6, $total_halaman);
    ?>
    <div class="mt-6 pt-2 flex items-center justify-center gap-3 text-xs select-none">
        <?php if ($halaman > 1): ?>
            <a href="<?= e(base_url('dashboard/index.php?page=' . ($halaman - 1))) ?>"
               class="text-gray-400 hover:text-[#111111] px-1.5 py-1 transition-colors">&lt;</a>
        <?php else: ?>
            <span class="text-gray-400 px-1.5 py-1">&lt;</span>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $maksHalamanTampil; $p++): ?>
            <?php if ($p <= $total_halaman): ?>
                <a href="<?= e(base_url('dashboard/index.php?page=' . $p)) ?>"
                   class="px-1.5 py-1 transition-colors <?= $p === $halaman ? 'font-bold text-[#111111]' : 'text-gray-400 hover:text-gray-700' ?>">
                    <?= $p ?>
                </a>
            <?php else: ?>
                <span class="px-1.5 py-1 text-gray-400"><?= $p ?></span>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($halaman < $total_halaman): ?>
            <a href="<?= e(base_url('dashboard/index.php?page=' . ($halaman + 1))) ?>"
               class="text-gray-400 hover:text-[#111111] px-1.5 py-1 transition-colors">&gt;</a>
        <?php else: ?>
            <span class="text-gray-400 px-1.5 py-1">&gt;</span>
        <?php endif; ?>
    </div>

</section>

<!-- ══════════════════════════════════════════════════════════════════════════
     AUTO-REFRESH FETCH 60 DETIK
     ═══════════════════════════════════════════════════════════════════════ -->
<script>
    window.URL_DATA_DASHBOARD = <?= json_encode(base_url('dashboard/data_dashboard.php')) ?>;
</script>
<script src="<?= e(base_url('assets/js/dashboard.js')) ?>" defer></script>

<?php require_once __DIR__ . '/../includes/footer_admin.php'; ?>
