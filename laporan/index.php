<?php
require_once __DIR__ . '/../includes/auth.php';
wajib_login();

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';

$nama_bulan = [
    1  => 'Januari',
    2  => 'Februari',
    3  => 'Maret',
    4  => 'April',
    5  => 'Mei',
    6  => 'Juni',
    7  => 'Juli',
    8  => 'Agustus',
    9  => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember',
];

$tz = new DateTimeZone('Asia/Jakarta');
$sekarang = new DateTime('now', $tz);

$bulan = isset($_GET['bulan']) && is_numeric($_GET['bulan']) ? (int) $_GET['bulan'] : (int) $sekarang->format('n');
$tahun = isset($_GET['tahun']) && is_numeric($_GET['tahun']) ? (int) $_GET['tahun'] : (int) $sekarang->format('Y');

if ($bulan < 1 || $bulan > 12) {
    $bulan = (int) $sekarang->format('n');
}
if ($tahun < 2000 || $tahun > 2100) {
    $tahun = (int) $sekarang->format('Y');
}

$label_periode = $nama_bulan[$bulan] . ' ' . $tahun;

// ── 1. Query Kartu Ringkasan Periode (berdasarkan waktu_aktual_kembali) ──────
$sqlRingkasan = <<<'SQL'
    SELECT COUNT(*) AS jumlah_selesai,
           COALESCE(SUM(t.total_tagihan - t.nominal_denda), 0) AS total_sewa,
           COALESCE(SUM(t.nominal_denda), 0) AS total_denda,
           COALESCE(SUM(t.total_tagihan), 0) AS total_pendapatan
      FROM transaksi t
     WHERE t.status_transaksi = 'selesai'
       AND EXTRACT(MONTH FROM t.waktu_aktual_kembali) = :bulan
       AND EXTRACT(YEAR FROM t.waktu_aktual_kembali) = :tahun
SQL;
$stmtRingkasan = $pdo->prepare($sqlRingkasan);
$stmtRingkasan->execute([
    ':bulan' => $bulan,
    ':tahun' => $tahun,
]);
$ringkasan = $stmtRingkasan->fetch(PDO::FETCH_ASSOC);

$jumlah_selesai   = (int) ($ringkasan['jumlah_selesai'] ?? 0);
$total_sewa       = (float) ($ringkasan['total_sewa'] ?? 0);
$total_denda      = (float) ($ringkasan['total_denda'] ?? 0);
$total_pendapatan = (float) ($ringkasan['total_pendapatan'] ?? 0);

// ── 2. Query Daftar Transaksi Selesai pada Periode Tersebut ──────────────────
$sqlTransaksi = <<<'SQL'
    SELECT t.id_transaksi,
           t.waktu_mulai_sewa,
           t.waktu_rencana_kembali,
           t.waktu_aktual_kembali,
           t.jenis_pembayaran,
           (t.total_tagihan - t.nominal_denda) AS pendapatan_sewa,
           t.nominal_denda,
           t.total_tagihan,
           p.nama_penyewa,
           p.no_telepon,
           (
               SELECT STRING_AGG(b.nama_barang, ', ' ORDER BY b.nama_barang)
                 FROM detail_transaksi dt
                 JOIN barang b ON b.id_barang = dt.id_barang
                WHERE dt.id_transaksi = t.id_transaksi
           ) AS daftar_barang
      FROM transaksi t
      JOIN penyewa p ON p.id_penyewa = t.id_penyewa
     WHERE t.status_transaksi = 'selesai'
       AND EXTRACT(MONTH FROM t.waktu_aktual_kembali) = :bulan
       AND EXTRACT(YEAR FROM t.waktu_aktual_kembali) = :tahun
     ORDER BY t.waktu_aktual_kembali ASC, t.id_transaksi ASC
SQL;
$stmtTransaksi = $pdo->prepare($sqlTransaksi);
$stmtTransaksi->execute([
    ':bulan' => $bulan,
    ':tahun' => $tahun,
]);
$daftar_transaksi = $stmtTransaksi->fetchAll(PDO::FETCH_ASSOC);

// ── 3. Query Ringkasan per Kategori Barang pada Periode Tersebut ─────────────
$sqlKategori = <<<'SQL'
    SELECT k.id_kategori,
           k.nama_kategori,
           COALESCE(SUM(dt.jumlah), 0) AS jumlah_unit_disewa,
           COUNT(DISTINCT t.id_transaksi) AS jumlah_transaksi,
           COALESCE(SUM(dt.subtotal), 0) AS total_subtotal_kategori
      FROM kategori k
      JOIN barang b ON b.id_kategori = k.id_kategori
      JOIN detail_transaksi dt ON dt.id_barang = b.id_barang
      JOIN transaksi t ON t.id_transaksi = dt.id_transaksi
     WHERE t.status_transaksi = 'selesai'
       AND EXTRACT(MONTH FROM t.waktu_aktual_kembali) = :bulan
       AND EXTRACT(YEAR FROM t.waktu_aktual_kembali) = :tahun
     GROUP BY k.id_kategori, k.nama_kategori
     ORDER BY total_subtotal_kategori DESC, k.nama_kategori ASC
SQL;
$stmtKategori = $pdo->prepare($sqlKategori);
$stmtKategori->execute([
    ':bulan' => $bulan,
    ':tahun' => $tahun,
]);
$ringkasan_kategori = $stmtKategori->fetchAll(PDO::FETCH_ASSOC);

// Pilihan dropdown tahun (dari 2023 s.d. tahun depan, ditambah tahun transaksi yang ada di DB)
$tahunSekarang = (int) $sekarang->format('Y');
$daftar_tahun  = range($tahunSekarang + 1, 2023);
if (!in_array($tahun, $daftar_tahun, true)) {
    $daftar_tahun[] = $tahun;
    rsort($daftar_tahun);
}

$url_cetak = base_url('laporan/cetak.php?' . http_build_query([
    'bulan' => $bulan,
    'tahun' => $tahun,
]));

// ── 4. Data Grafik Statistik Penyewaan (12 Bulan Terakhir) ───────────────────
require_once __DIR__ . '/../dashboard/data_dashboard.php';
$chart_data   = db_chart_pendapatan($pdo);
$chart_labels = array_column($chart_data, 'label');
$chart_rupiah = array_map(fn($r) => (float) $r['total'], $chart_data);
$chart_jumlah = array_map(fn($r) => (int) $r['jumlah'], $chart_data);

$max_rupiah = max(array_merge($chart_rupiah, [100000]));
$max_jumlah = max(array_merge($chart_jumlah, [1]));
$chart_jumlah_scaled = array_map(
    fn($jml) => round(($jml / $max_jumlah) * ($max_rupiah * 0.75)),
    $chart_jumlah
);

$judul_halaman = 'Laporan Pendapatan Bulanan';
require_once __DIR__ . '/../includes/header_admin.php';
?>

<!-- Heading Halaman & Tombol Cetak -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-[#111111] tracking-tight">Laporan Pendapatan</h1>
        <p class="text-xs text-gray-500 mt-0.5">
            Rekapitulasi transaksi selesai, pendapatan sewa murni, dan denda keterlambatan periode <strong><?= e($label_periode) ?></strong>.
        </p>
    </div>

    <div class="flex items-center gap-2.5">
        <a href="<?= e($url_cetak) ?>"
           target="_blank"
           rel="noopener"
           class="btn-utama inline-flex items-center gap-2 text-xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/>
            </svg>
            <span>Cetak Laporan (<?= e($label_periode) ?>)</span>
        </a>
    </div>
</div>

<!-- Form Filter Bulan & Tahun -->
<div class="card-admin mb-6">
    <form action="<?= e(base_url('laporan/index.php')) ?>" method="GET" class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end">
        <div class="sm:col-span-5">
            <label for="pilih-bulan" class="form-label">Bulan Pengembalian</label>
            <select id="pilih-bulan" name="bulan" class="form-input">
                <?php foreach ($nama_bulan as $num => $nama): ?>
                    <option value="<?= $num ?>" <?= $num === $bulan ? 'selected' : '' ?>>
                        <?= e($nama) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sm:col-span-4">
            <label for="pilih-tahun" class="form-label">Tahun</label>
            <select id="pilih-tahun" name="tahun" class="form-input">
                <?php foreach ($daftar_tahun as $th): ?>
                    <option value="<?= $th ?>" <?= $th === $tahun ? 'selected' : '' ?>>
                        <?= $th ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sm:col-span-3 flex items-center gap-2">
            <button type="submit" class="btn-utama flex-1 justify-center py-2.5 text-xs font-semibold cursor-pointer">
                Tampilkan
            </button>
            <?php if ($bulan !== (int) $sekarang->format('n') || $tahun !== (int) $sekarang->format('Y')): ?>
                <a href="<?= e(base_url('laporan/index.php')) ?>" class="btn-sekunder justify-center py-2.5 px-3.5 text-xs">
                    Bulan Ini
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- 4 Kartu Ringkasan Pendapatan -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card-admin p-5">
        <div class="text-xs font-medium text-gray-500">Jumlah Transaksi Selesai</div>
        <div class="text-2xl font-bold text-[#111111] mt-1.5">
            <?= number_format($jumlah_selesai, 0, ',', '.') ?> <span class="text-xs font-normal text-gray-500">transaksi</span>
        </div>
        <div class="text-[11px] text-gray-400 mt-1">Periode <?= e($label_periode) ?></div>
    </div>

    <div class="card-admin p-5">
        <div class="text-xs font-medium text-gray-500">Total Pendapatan Sewa</div>
        <div class="text-2xl font-bold text-[#111111] mt-1.5">
            <?= e(format_rupiah($total_sewa)) ?>
        </div>
        <div class="text-[11px] text-gray-400 mt-1">Total tagihan dikurangi denda</div>
    </div>

    <div class="card-admin p-5">
        <div class="text-xs font-medium text-gray-500">Total Denda Keterlambatan</div>
        <div class="text-2xl font-bold <?= $total_denda > 0 ? 'text-rose-600' : 'text-[#111111]' ?> mt-1.5">
            <?= e(format_rupiah($total_denda)) ?>
        </div>
        <div class="text-[11px] text-gray-400 mt-1">Akumulasi denda pengembalian</div>
    </div>

    <div class="card-admin p-5 bg-[#111111] text-white border-[#111111]">
        <div class="text-xs font-medium text-gray-300">Total Pendapatan Akhir</div>
        <div class="text-2xl font-bold text-white mt-1.5">
            <?= e(format_rupiah($total_pendapatan)) ?>
        </div>
        <div class="text-[11px] text-gray-400 mt-1">Pendapatan sewa + total denda</div>
    </div>
</div>

<!-- Grafik Statistik Penyewaan (12 Bulan Terakhir) -->
<div class="card-admin mb-6">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-5">
        <h2 class="text-lg font-bold text-[#111111]">Statistik Penyewaan</h2>
        <div class="flex items-center gap-4 text-xs">
            <span class="inline-flex items-center gap-1.5 font-medium text-[#111111]">
                <span class="w-2.5 h-2.5 rounded-full bg-[#111111]"></span>
                Pendapatan
            </span>
            <span class="inline-flex items-center gap-1.5 font-medium text-gray-400">
                <span class="w-2.5 h-2.5 rounded-full bg-gray-300"></span>
                Transaksi Masuk
            </span>
        </div>
    </div>

    <div class="relative h-64 w-full">
        <canvas id="chartPendapatan"></canvas>
    </div>
</div>

<!-- Tabel Rincian Transaksi Selesai pada Periode -->
<div class="card-admin overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h2 class="text-sm font-bold text-[#111111]">Rincian Transaksi Selesai — <?= e($label_periode) ?></h2>
            <p class="text-xs text-gray-500">Berdasarkan waktu aktual pengembalian barang</p>
        </div>
        <span class="text-xs font-semibold text-gray-600">
            <?= count($daftar_transaksi) ?> Transaksi
        </span>
    </div>

    <?php if (empty($daftar_transaksi)): ?>
        <div class="p-12 text-center">
            <p class="text-sm font-semibold text-gray-800">Tidak Ada Transaksi Selesai pada Periode <?= e($label_periode) ?></p>
            <p class="text-xs text-gray-500 mt-1">Silakan pilih bulan atau tahun lainnya pada filter di atas.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="tabel-admin w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th class="w-12">No</th>
                        <th>Kode</th>
                        <th>Penyewa</th>
                        <th>Barang Disewa</th>
                        <th>Waktu Aktual Kembali</th>
                        <th>Metode</th>
                        <th class="text-right">Pendapatan Sewa</th>
                        <th class="text-right">Denda</th>
                        <th class="text-right">Total Tagihan</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar_transaksi as $idx => $row): ?>
                        <tr>
                            <td class="text-gray-500"><?= $idx + 1 ?></td>
                            <td class="font-bold text-[#111111] whitespace-nowrap">
                                #SR-<?= e(str_pad((string) $row['id_transaksi'], 4, '0', STR_PAD_LEFT)) ?>
                            </td>
                            <td>
                                <div class="font-semibold text-gray-900"><?= e($row['nama_penyewa']) ?></div>
                                <div class="text-[11px] text-gray-500"><?= e($row['no_telepon']) ?></div>
                            </td>
                            <td class="max-w-xs truncate" title="<?= e($row['daftar_barang'] ?? '-') ?>">
                                <?= e($row['daftar_barang'] ?? '-') ?>
                            </td>
                            <td class="whitespace-nowrap font-medium text-gray-800">
                                <?= e(format_tanggal($row['waktu_aktual_kembali'])) ?>
                            </td>
                            <td class="uppercase text-xs font-semibold text-gray-600">
                                <?= e($row['jenis_pembayaran']) ?>
                            </td>
                            <td class="text-right whitespace-nowrap font-medium text-gray-900">
                                <?= e(format_rupiah($row['pendapatan_sewa'])) ?>
                            </td>
                            <td class="text-right whitespace-nowrap font-semibold <?= (float) $row['nominal_denda'] > 0 ? 'text-rose-600' : 'text-gray-500' ?>">
                                <?= e(format_rupiah($row['nominal_denda'])) ?>
                            </td>
                            <td class="text-right whitespace-nowrap font-bold text-[#111111]">
                                <?= e(format_rupiah($row['total_tagihan'])) ?>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="<?= e(base_url('transaksi/detail.php?id=' . (int) $row['id_transaksi'])) ?>"
                                   class="btn-sekunder py-1 px-3 text-xs">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-bold border-t-2 border-gray-200">
                        <td colspan="6" class="px-4 py-3.5 text-right text-xs uppercase tracking-wider text-gray-600">
                            Total Periode <?= e($label_periode) ?>
                        </td>
                        <td class="px-4 py-3.5 text-right text-xs text-gray-900 whitespace-nowrap">
                            <?= e(format_rupiah($total_sewa)) ?>
                        </td>
                        <td class="px-4 py-3.5 text-right text-xs <?= $total_denda > 0 ? 'text-rose-600' : 'text-gray-700' ?> whitespace-nowrap">
                            <?= e(format_rupiah($total_denda)) ?>
                        </td>
                        <td class="px-4 py-3.5 text-right text-sm text-[#111111] whitespace-nowrap">
                            <?= e(format_rupiah($total_pendapatan)) ?>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Tabel Ringkasan per Kategori Barang -->
<div class="card-admin overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h2 class="text-sm font-bold text-[#111111]">Ringkasan Pendapatan Sewa per Kategori Barang</h2>
            <p class="text-xs text-gray-500">Kontribusi tiap kategori peralatan pada transaksi selesai periode <?= e($label_periode) ?></p>
        </div>
        <span class="text-xs font-semibold text-gray-600">
            <?= count($ringkasan_kategori) ?> Kategori Terpakai
        </span>
    </div>

    <?php if (empty($ringkasan_kategori)): ?>
        <div class="p-8 text-center text-xs text-gray-500">
            Belum ada data kategori barang dari transaksi selesai pada periode ini.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="tabel-admin w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th class="w-12">No</th>
                        <th>Kategori Barang</th>
                        <th class="text-right">Jumlah Transaksi</th>
                        <th class="text-right">Total Unit Disewa</th>
                        <th class="text-right">Total Subtotal Sewa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalUnitKat = 0;
                    $totalSubKat  = 0.0;
                    foreach ($ringkasan_kategori as $idx => $kat):
                        $totalUnitKat += (int) $kat['jumlah_unit_disewa'];
                        $totalSubKat  += (float) $kat['total_subtotal_kategori'];
                    ?>
                        <tr>
                            <td class="text-gray-500"><?= $idx + 1 ?></td>
                            <td class="font-semibold text-[#111111]"><?= e($kat['nama_kategori']) ?></td>
                            <td class="text-right"><?= number_format((int) $kat['jumlah_transaksi'], 0, ',', '.') ?></td>
                            <td class="text-right font-medium"><?= number_format((int) $kat['jumlah_unit_disewa'], 0, ',', '.') ?> unit</td>
                            <td class="text-right font-bold text-[#111111]"><?= e(format_rupiah($kat['total_subtotal_kategori'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-bold border-t-2 border-gray-200">
                        <td colspan="3" class="px-4 py-3 text-right text-xs uppercase tracking-wider text-gray-600">
                            Total Seluruh Kategori
                        </td>
                        <td class="px-4 py-3 text-right text-xs text-[#111111]">
                            <?= number_format($totalUnitKat, 0, ',', '.') ?> unit
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-[#111111]">
                            <?= e(format_rupiah($totalSubKat)) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const labels      = <?= json_encode($chart_labels) ?>;
    const dataRupiah  = <?= json_encode($chart_rupiah) ?>;
    const dataScaled  = <?= json_encode($chart_jumlah_scaled) ?>;
    const dataAsliJml = <?= json_encode($chart_jumlah) ?>;

    const canvas = document.getElementById('chartPendapatan');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Pendapatan',
                    data: dataRupiah,
                    backgroundColor: '#111111',
                    hoverBackgroundColor: '#27272a',
                    borderRadius: 6,
                    borderSkipped: false,
                    barThickness: 8,
                    categoryPercentage: 0.55,
                    barPercentage: 0.7,
                },
                {
                    label: 'Transaksi Masuk',
                    data: dataScaled,
                    backgroundColor: '#d1d5db',
                    hoverBackgroundColor: '#9ca3af',
                    borderRadius: 6,
                    borderSkipped: false,
                    barThickness: 8,
                    categoryPercentage: 0.55,
                    barPercentage: 0.7,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#111111',
                    titleColor: '#ffffff',
                    bodyColor: '#f3f4f6',
                    titleFont: { family: 'Poppins', size: 11, weight: '600' },
                    bodyFont: { family: 'Poppins', size: 11 },
                    padding: { top: 8, bottom: 8, left: 12, right: 12 },
                    cornerRadius: 8,
                    caretSize: 5,
                    displayColors: false,
                    callbacks: {
                        label: function (context) {
                            if (context.datasetIndex === 0) {
                                return 'Pendapatan: Rp ' + Number(context.parsed.y).toLocaleString('id-ID');
                            }
                            const jml = dataAsliJml[context.dataIndex] ?? 0;
                            return 'Transaksi: ' + jml + ' pesanan';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        font: { family: 'Poppins', size: 11 },
                        color: '#9ca3af'
                    }
                },
                y: {
                    beginAtZero: true,
                    border: { display: false, dash: [4, 4] },
                    grid: {
                        color: '#f1f3f5',
                        tickBorderDash: [4, 4]
                    },
                    ticks: {
                        font: { family: 'Poppins', size: 11 },
                        color: '#9ca3af',
                        maxTicksLimit: 5,
                        callback: function (v) {
                            if (v === 0) return '0';
                            if (v >= 1000000) return (v / 1000000).toFixed(1).replace('.0', '') + 'jt';
                            if (v >= 1000) return (v / 1000).toFixed(0) + 'rb';
                            return v;
                        }
                    }
                }
            }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer_admin.php'; ?>
