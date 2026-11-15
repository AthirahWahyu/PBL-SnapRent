<?php
require_once __DIR__ . '/../includes/auth.php';
wajib_login();

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';

// ── Ambil Parameter Filter & Pencarian ───────────────────────────────────────
$cari       = trim((string) ($_GET['q'] ?? ''));
$tgl_dari   = trim((string) ($_GET['tgl_dari'] ?? ''));
$tgl_sampai = trim((string) ($_GET['tgl_sampai'] ?? ''));
$halaman    = max(1, (int) ($_GET['hal'] ?? 1));
$per_hal    = 20;

// Validasi format YYYY-MM-DD sederhana untuk filter rentang tanggal
if ($tgl_dari !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_dari)) {
    $tgl_dari = '';
}
if ($tgl_sampai !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_sampai)) {
    $tgl_sampai = '';
}

$where  = ["t.status_transaksi = 'selesai'"];
$params = [];

if ($cari !== '') {
    $angkaKode = preg_replace('/[^0-9]/', '', $cari);
    if ($angkaKode !== '' && (str_starts_with(strtoupper($cari), '#SR') || str_starts_with(strtoupper($cari), 'SR') || ctype_digit($cari))) {
        $where[]               = '(p.nama_penyewa ILIKE :cari OR p.no_telepon ILIKE :cari OR t.id_transaksi = :id_cari)';
        $params[':cari']       = '%' . $cari . '%';
        $params[':id_cari']    = (int) $angkaKode;
    } else {
        $where[]               = '(p.nama_penyewa ILIKE :cari OR p.no_telepon ILIKE :cari)';
        $params[':cari']       = '%' . $cari . '%';
    }
}

if ($tgl_dari !== '') {
    $where[]              = 'DATE(t.waktu_aktual_kembali) >= :tgl_dari';
    $params[':tgl_dari']  = $tgl_dari;
}

if ($tgl_sampai !== '') {
    $where[]                = 'DATE(t.waktu_aktual_kembali) <= :tgl_sampai';
    $params[':tgl_sampai']  = $tgl_sampai;
}

$whereSql = implode(' AND ', $where);

// ── Hitung Total Baris & Ringkasan ───────────────────────────────────────────
$sqlRekap = <<<SQL
    SELECT COUNT(*) AS total_baris,
           COALESCE(SUM(t.nominal_denda), 0) AS total_denda,
           COALESCE(SUM(t.total_tagihan), 0) AS total_pendapatan
      FROM transaksi t
      JOIN penyewa p ON p.id_penyewa = t.id_penyewa
     WHERE {$whereSql}
SQL;
$stmtRekap = $pdo->prepare($sqlRekap);
$stmtRekap->execute($params);
$rekap = $stmtRekap->fetch(PDO::FETCH_ASSOC);

$total_baris      = (int) ($rekap['total_baris'] ?? 0);
$total_denda      = (float) ($rekap['total_denda'] ?? 0);
$total_pendapatan = (float) ($rekap['total_pendapatan'] ?? 0);

$total_hal = max(1, (int) ceil($total_baris / $per_hal));
if ($halaman > $total_hal) {
    $halaman = $total_hal;
}
$offset = ($halaman - 1) * $per_hal;

// ── Query Daftar Transaksi Selesai ───────────────────────────────────────────
$sqlList = <<<SQL
    SELECT t.id_transaksi,
           t.waktu_mulai_sewa,
           t.waktu_rencana_kembali,
           t.waktu_aktual_kembali,
           t.nominal_denda,
           t.total_tagihan,
           t.jenis_pembayaran,
           t.status_pembayaran,
           p.nama_penyewa,
           p.no_telepon,
           d.jenis_denda,
           d.keterangan AS keterangan_denda,
           GREATEST(
               0,
               EXTRACT(EPOCH FROM (t.waktu_aktual_kembali - t.waktu_rencana_kembali))::NUMERIC / 3600
           ) AS jam_telat,
           (
               SELECT STRING_AGG(b.nama_barang, ', ' ORDER BY b.nama_barang)
                 FROM detail_transaksi dt
                 JOIN barang b ON b.id_barang = dt.id_barang
                WHERE dt.id_transaksi = t.id_transaksi
           ) AS daftar_barang,
           (
               SELECT COUNT(*)
                 FROM jaminan j
                WHERE j.id_transaksi = t.id_transaksi
                  AND j.status_jaminan = 'ditahan'
           ) AS jaminan_belum_kembali
      FROM transaksi t
      JOIN penyewa p ON p.id_penyewa = t.id_penyewa
      LEFT JOIN denda d ON d.id_denda = t.id_denda
     WHERE {$whereSql}
     ORDER BY t.waktu_aktual_kembali DESC NULLS LAST, t.id_transaksi DESC
     LIMIT :limit OFFSET :offset
SQL;

$stmtList = $pdo->prepare($sqlList);
foreach ($params as $k => $v) {
    $stmtList->bindValue($k, $v);
}
$stmtList->bindValue(':limit', $per_hal, PDO::PARAM_INT);
$stmtList->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtList->execute();
$daftar = $stmtList->fetchAll(PDO::FETCH_ASSOC);

// Helper pembangun query string paginasi
function url_riwayat_hal(int $hal): string
{
    $q = $_GET;
    $q['hal'] = $hal;
    return base_url('pengembalian/riwayat.php?' . http_build_query($q));
}

$judul_halaman = 'Riwayat Pengembalian Selesai';
require_once __DIR__ . '/../includes/header_admin.php';
?>

<!-- Heading Halaman -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-[#111111] tracking-tight">Riwayat Pengembalian Selesai</h1>
        <p class="text-xs text-gray-500 mt-0.5">
            Daftar seluruh transaksi penyewaan yang telah dikembalikan dan ditutup beserta rincian denda keterlambatan.
        </p>
    </div>
    <div class="flex items-center gap-2.5">
        <a href="<?= e(base_url('pengembalian/kembali.php')) ?>"
           class="btn-utama inline-flex items-center gap-2 text-xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
            </svg>
            <span>Proses Pengembalian Baru</span>
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Riwayat -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="card-admin p-5 flex items-center justify-between">
        <div>
            <div class="text-xs font-medium text-gray-500">Transaksi Selesai</div>
            <div class="text-2xl font-bold text-[#111111] mt-1"><?= number_format($total_baris, 0, ',', '.') ?></div>
        </div>
        <div class="w-10 h-10 rounded-full bg-gray-100 text-[#111111] flex items-center justify-center font-bold">
            ✓
        </div>
    </div>

    <div class="card-admin p-5 flex items-center justify-between">
        <div>
            <div class="text-xs font-medium text-gray-500">Total Denda Terkumpul</div>
            <div class="text-2xl font-bold <?= $total_denda > 0 ? 'text-rose-600' : 'text-[#111111]' ?> mt-1">
                <?= e(format_rupiah($total_denda)) ?>
            </div>
        </div>
        <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-sm">
            !
        </div>
    </div>

    <div class="card-admin p-5 flex items-center justify-between">
        <div>
            <div class="text-xs font-medium text-gray-500">Total Nilai Transaksi Akhir</div>
            <div class="text-2xl font-bold text-[#111111] mt-1"><?= e(format_rupiah($total_pendapatan)) ?></div>
        </div>
        <div class="w-10 h-10 rounded-full bg-[#111111] text-white flex items-center justify-center font-bold text-xs">
            Rp
        </div>
    </div>
</div>

<!-- Filter Pencarian & Rentang Tanggal -->
<div class="card-admin mb-6">
    <form action="<?= e(base_url('pengembalian/riwayat.php')) ?>" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5 items-end">
        <div class="lg:col-span-5">
            <label for="input-q" class="form-label">Cari Penyewa / Telepon / Kode</label>
            <input type="text"
                   id="input-q"
                   name="q"
                   value="<?= e($cari) ?>"
                   placeholder="Contoh: Budi, 0812..., atau #SR-0001"
                   class="form-input">
        </div>

        <div class="lg:col-span-2">
            <label for="input-tgl-dari" class="form-label">Dari Tanggal Kembali</label>
            <input type="date"
                   id="input-tgl-dari"
                   name="tgl_dari"
                   value="<?= e($tgl_dari) ?>"
                   class="form-input">
        </div>

        <div class="lg:col-span-2">
            <label for="input-tgl-sampai" class="form-label">Sampai Tanggal</label>
            <input type="date"
                   id="input-tgl-sampai"
                   name="tgl_sampai"
                   value="<?= e($tgl_sampai) ?>"
                   class="form-input">
        </div>

        <div class="lg:col-span-3 flex items-center gap-2">
            <button type="submit" class="btn-utama flex-1 justify-center py-2.5 text-xs font-semibold cursor-pointer">
                Terapkan Filter
            </button>
            <?php if ($cari !== '' || $tgl_dari !== '' || $tgl_sampai !== ''): ?>
                <a href="<?= e(base_url('pengembalian/riwayat.php')) ?>"
                   class="btn-sekunder justify-center py-2.5 px-3.5 text-xs">
                    Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabel Riwayat Pengembalian -->
<div class="card-admin overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-bold text-[#111111]">Daftar Transaksi Selesai</h2>
        <span class="text-xs text-gray-500">
            Menampilkan <?= count($daftar) ?> dari <?= $total_baris ?> transaksi
        </span>
    </div>

    <?php if (empty($daftar)): ?>
        <div class="p-12 text-center">
            <p class="text-sm font-semibold text-gray-800">Belum Ada Riwayat Pengembalian yang Cocok</p>
            <p class="text-xs text-gray-500 mt-1">Ubah kata kunci pencarian atau rentang tanggal untuk melihat data lainnya.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="tabel-admin w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Penyewa</th>
                        <th>Barang</th>
                        <th>Batas Kembali</th>
                        <th>Aktual Kembali</th>
                        <th class="text-right">Denda</th>
                        <th class="text-right">Total Akhir</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $row): ?>
                        <?php
                        $nominalDenda = (float) $row['nominal_denda'];
                        $jamTelat     = (float) $row['jam_telat'];
                        ?>
                        <tr>
                            <td class="font-bold text-[#111111] whitespace-nowrap">
                                #SR-<?= e(str_pad((string) $row['id_transaksi'], 4, '0', STR_PAD_LEFT)) ?>
                            </td>
                            <td>
                                <div class="font-semibold text-gray-900"><?= e($row['nama_penyewa']) ?></div>
                                <div class="text-[11px] text-gray-500"><?= e($row['no_telepon']) ?></div>
                            </td>
                            <td class="max-w-xs">
                                <div class="truncate font-medium text-gray-800" title="<?= e($row['daftar_barang'] ?? '-') ?>">
                                    <?= e($row['daftar_barang'] ?? '-') ?>
                                </div>
                                <?php if ((int) $row['jaminan_belum_kembali'] > 0): ?>
                                    <span class="inline-block mt-0.5 text-[10px] font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded">
                                        <?= (int) $row['jaminan_belum_kembali'] ?> jaminan belum dikembalikan
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap text-gray-600">
                                <?= e(format_tanggal($row['waktu_rencana_kembali'])) ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <div class="font-semibold text-[#111111]">
                                    <?= e(format_tanggal($row['waktu_aktual_kembali'])) ?>
                                </div>
                                <?php if ($jamTelat > 0): ?>
                                    <div class="text-[11px] <?= $nominalDenda > 0 ? 'text-rose-600 font-semibold' : 'text-amber-600' ?>">
                                        Telat <?= e(rtrim(rtrim(number_format($jamTelat, 2, ',', '.'), '0'), ',')) ?> jam
                                    </div>
                                <?php else: ?>
                                    <div class="text-[11px] text-emerald-600">Tepat waktu</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <?php if ($nominalDenda > 0): ?>
                                    <div class="font-bold text-rose-600"><?= e(format_rupiah($nominalDenda)) ?></div>
                                    <div class="text-[10px] text-gray-500">
                                        <?= e($row['jenis_denda'] === 'sewa_harian' ? 'Sewa 1 Hari' : 'Telat 1–3 Jam') ?>
                                    </div>
                                <?php else: ?>
                                    <span class="font-semibold text-emerald-700">Rp 0</span>
                                    <?php if (!empty($row['keterangan_denda'])): ?>
                                        <div class="text-[10px] text-gray-400">Toleransi</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-right font-bold text-[#111111] whitespace-nowrap">
                                <?= e(format_rupiah($row['total_tagihan'])) ?>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="<?= e(base_url('transaksi/detail.php?id=' . (int) $row['id_transaksi'])) ?>"
                                   class="btn-sekunder text-xs py-1.5 px-3 inline-flex items-center gap-1"
                                   title="Lihat detail transaksi (hanya baca)">
                                    <span>Detail</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginasi -->
        <?php if ($total_hal > 1): ?>
            <div class="px-6 py-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="text-xs text-gray-500">
                    Halaman <strong><?= $halaman ?></strong> dari <strong><?= $total_hal ?></strong>
                </div>
                <div class="flex items-center gap-1.5">
                    <?php if ($halaman > 1): ?>
                        <a href="<?= e(url_riwayat_hal($halaman - 1)) ?>" class="btn-sekunder py-1.5 px-3 text-xs">
                            &laquo; Sebelumnya
                        </a>
                    <?php endif; ?>

                    <?php
                    $mulaiHal = max(1, $halaman - 2);
                    $akhirHal = min($total_hal, $halaman + 2);
                    for ($i = $mulaiHal; $i <= $akhirHal; $i++):
                    ?>
                        <a href="<?= e(url_riwayat_hal($i)) ?>"
                           class="py-1.5 px-3 rounded-full text-xs font-semibold transition-colors <?= $i === $halaman ? 'bg-[#111111] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($halaman < $total_hal): ?>
                        <a href="<?= e(url_riwayat_hal($halaman + 1)) ?>" class="btn-sekunder py-1.5 px-3 text-xs">
                            Berikutnya &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer_admin.php'; ?>
