<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/koneksi.php';

wajib_login();

// ── Ambil parameter filter, pencarian, & paginasi ────────────────────────────
$cari       = trim($_GET['q'] ?? '');
$status     = trim($_GET['status'] ?? '');
$halaman    = ambil_int('page', 'get') ?? 1;
$perHalaman = 20;

$statusValid = ['menunggu_verifikasi', 'menunggu_diambil', 'disewa', 'selesai', 'dibatalkan'];
if (!in_array($status, $statusValid, true)) {
    $status = '';
}

try {
    // Hitung ringkasan per status untuk tab cepat
    $rowsCount = $pdo->query('
        SELECT status_transaksi, COUNT(*) AS jml
        FROM transaksi
        GROUP BY status_transaksi
    ')->fetchAll();
    $ringkasanStatus = [
        'menunggu_verifikasi' => 0,
        'menunggu_diambil'    => 0,
        'disewa'              => 0,
        'selesai'             => 0,
        'dibatalkan'          => 0,
    ];
    foreach ($rowsCount as $rc) {
        $st = (string) $rc['status_transaksi'];
        if (isset($ringkasanStatus[$st])) {
            $ringkasanStatus[$st] = (int) $rc['jml'];
        }
    }
    $totalSemuaStatus = array_sum($ringkasanStatus);

    // Bangun klausa WHERE
    $where  = [];
    $params = [];

    if ($status !== '') {
        $where[]           = 't.status_transaksi = :status';
        $params[':status'] = $status;
    }

    if ($cari !== '') {
        // Ekstrak angka bila user mengetik kode seperti "#SR-0015" atau "SR-15" atau "15"
        $angkaKode = preg_replace('/\D+/', '', $cari);
        if ($angkaKode !== '') {
            $where[] = '(p.nama_penyewa ILIKE :cari OR p.no_telepon ILIKE :cari OR t.id_transaksi = :id_cari OR LPAD(t.id_transaksi::text, 4, \'0\') ILIKE :cari_kode)';
            $params[':cari']      = '%' . $cari . '%';
            $params[':id_cari']   = (int) $angkaKode;
            $params[':cari_kode'] = '%' . $angkaKode . '%';
        } else {
            $where[]         = '(p.nama_penyewa ILIKE :cari OR p.no_telepon ILIKE :cari)';
            $params[':cari'] = '%' . $cari . '%';
        }
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Total baris hasil filter
    $stmtTotal = $pdo->prepare("
        SELECT COUNT(*)
        FROM transaksi t
        JOIN penyewa p ON p.id_penyewa = t.id_penyewa
        $whereSql
    ");
    $stmtTotal->execute($params);
    $totalBaris   = (int) $stmtTotal->fetchColumn();
    $totalHalaman = max(1, (int) ceil($totalBaris / $perHalaman));

    if ($halaman > $totalHalaman) {
        $halaman = $totalHalaman;
    }
    $offset = ($halaman - 1) * $perHalaman;

    // Ambil daftar transaksi (urut waktu_pemesanan terbaru)
    $sqlData = "
        SELECT
            t.id_transaksi,
            t.waktu_pemesanan,
            t.waktu_mulai_sewa,
            t.waktu_rencana_kembali,
            t.jenis_pembayaran,
            t.status_pembayaran,
            t.status_transaksi,
            t.total_tagihan,
            p.nama_penyewa,
            p.no_telepon,
            COALESCE(
                (
                    SELECT STRING_AGG(b.nama_barang, ', ' ORDER BY b.nama_barang)
                    FROM detail_transaksi dt
                    JOIN barang b ON b.id_barang = dt.id_barang
                    WHERE dt.id_transaksi = t.id_transaksi
                ),
                '-'
            ) AS daftar_barang
        FROM transaksi t
        JOIN penyewa p ON p.id_penyewa = t.id_penyewa
        $whereSql
        ORDER BY t.waktu_pemesanan DESC, t.id_transaksi DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmtData = $pdo->prepare($sqlData);
    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue(':limit', $perHalaman, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $daftarTransaksi = $stmtData->fetchAll();
} catch (PDOException $e) {
    error_log('[SnapRent] Error transaksi/list.php: ' . $e->getMessage());
    $ringkasanStatus  = ['menunggu_verifikasi' => 0, 'menunggu_diambil' => 0, 'disewa' => 0, 'selesai' => 0, 'dibatalkan' => 0];
    $totalSemuaStatus = 0;
    $daftarTransaksi  = [];
    $totalBaris       = 0;
    $totalHalaman     = 1;
    $offset           = 0;
    flash_set('error', 'Gagal memuat daftar transaksi.');
}

function _url_halaman_transaksi(int $page, string $cari, string $status): string
{
    $query = ['page' => $page];
    if ($cari !== '')   $query['q']      = $cari;
    if ($status !== '') $query['status'] = $status;
    return base_url('transaksi/list.php?' . http_build_query($query));
}

$judul_halaman = 'Daftar Transaksi';
require_once __DIR__ . '/../includes/header_admin.php';
?>

<!-- ══════════════════════════════════════════════════════════════════════════
     HEADER HALAMAN + TAB FILTER STATUS CEPAT
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-5">
    <div>
        <h1 class="text-2xl font-bold text-[#111111] tracking-tight">Daftar Transaksi Penyewaan</h1>
        <p class="text-xs text-gray-500 mt-1">
            Menampilkan <strong><?= e((string) count($daftarTransaksi)) ?></strong> dari
            <strong><?= e((string) $totalBaris) ?></strong> transaksi (diurutkan dari waktu pemesanan terbaru)
        </p>
    </div>

    <?php if ($ringkasanStatus['menunggu_verifikasi'] > 0): ?>
        <a href="<?= e(base_url('transaksi/list.php?status=menunggu_verifikasi')) ?>"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-amber-100 border border-amber-300 text-amber-900 text-xs font-semibold shadow-sm hover:bg-amber-200 transition-colors">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
            <span><?= e((string) $ringkasanStatus['menunggu_verifikasi']) ?> Booking Menunggu Verifikasi</span>
        </a>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     FILTER & PENCARIAN
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="card-admin mb-6">
    <!-- Tab Pil Status -->
    <div class="flex flex-wrap items-center gap-2 pb-4 mb-4 border-b border-gray-100">
        <?php
        $tabStatus = [
            ''                    => ['label' => 'Semua',              'jml' => $totalSemuaStatus],
            'menunggu_verifikasi' => ['label' => 'Menunggu Verifikasi', 'jml' => $ringkasanStatus['menunggu_verifikasi']],
            'menunggu_diambil'    => ['label' => 'Menunggu Diambil',    'jml' => $ringkasanStatus['menunggu_diambil']],
            'disewa'              => ['label' => 'Disewa',              'jml' => $ringkasanStatus['disewa']],
            'selesai'             => ['label' => 'Selesai',             'jml' => $ringkasanStatus['selesai']],
            'dibatalkan'          => ['label' => 'Dibatalkan',          'jml' => $ringkasanStatus['dibatalkan']],
        ];
        foreach ($tabStatus as $keySt => $infoSt):
            $aktifTab = ($status === $keySt);
            $urlTab   = base_url('transaksi/list.php' . ($keySt !== '' ? '?status=' . urlencode($keySt) : ''));
        ?>
            <a href="<?= e($urlTab) ?>"
               class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors inline-flex items-center gap-1.5
                      <?= $aktifTab ? 'bg-[#111111] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                <?php if ($keySt === 'menunggu_verifikasi' && $infoSt['jml'] > 0): ?>
                    <span class="w-2 h-2 rounded-full <?= $aktifTab ? 'bg-amber-400' : 'bg-amber-500' ?>"></span>
                <?php endif; ?>
                <span><?= e($infoSt['label']) ?></span>
                <span class="opacity-70">(<?= e((string) $infoSt['jml']) ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Form Pencarian & Dropdown Status -->
    <form action="<?= e(base_url('transaksi/list.php')) ?>" method="GET" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
        <div class="sm:col-span-6">
            <label for="q" class="form-label">Cari Nama Penyewa / No. Telepon / Kode Transaksi</label>
            <input type="text"
                   id="q"
                   name="q"
                   value="<?= e($cari) ?>"
                   placeholder="Contoh: Budi, 0812..., atau #SR-0012"
                   class="form-input">
        </div>

        <div class="sm:col-span-4">
            <label for="status" class="form-label">Filter Status Transaksi</label>
            <select id="status" name="status" class="form-input">
                <option value="">Semua Status</option>
                <option value="menunggu_verifikasi" <?= $status === 'menunggu_verifikasi' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                <option value="menunggu_diambil" <?= $status === 'menunggu_diambil' ? 'selected' : '' ?>>Menunggu Diambil</option>
                <option value="disewa" <?= $status === 'disewa' ? 'selected' : '' ?>>Sedang Disewa</option>
                <option value="selesai" <?= $status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                <option value="dibatalkan" <?= $status === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
            </select>
        </div>

        <div class="sm:col-span-2 flex items-center gap-2">
            <button type="submit" class="btn-utama flex-1 justify-center py-2.5">
                Cari
            </button>
            <?php if ($cari !== '' || $status !== ''): ?>
                <a href="<?= e(base_url('transaksi/list.php')) ?>" class="btn-sekunder py-2 px-3">
                    Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     TABEL SEMUA TRANSAKSI
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="card-admin">
    <div class="tabel-wrapper">
        <table class="tabel-admin">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Penyewa</th>
                    <th>Telepon</th>
                    <th>Barang</th>
                    <th>Waktu Sewa</th>
                    <th>Pembayaran</th>
                    <th>Status Transaksi</th>
                    <th>Total Tagihan</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarTransaksi)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-gray-400 py-10">
                            Tidak ada data transaksi yang sesuai dengan filter pencarian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarTransaksi as $t):
                        $idTrans    = (int) $t['id_transaksi'];
                        $kodeTrans  = sprintf('#SR-%04d', $idTrans);
                        $stTrans    = (string) $t['status_transaksi'];
                        $isMenunggu = ($stTrans === 'menunggu_verifikasi');

                        // Penekanan visual khusus untuk status "menunggu_verifikasi"
                        $kelasBaris = $isMenunggu ? 'baris-kuning font-medium' : '';

                        $badgeTrans = match ($stTrans) {
                            'menunggu_verifikasi' => 'badge-kuning',
                            'menunggu_diambil'    => 'badge-disetujui',
                            'disewa'              => 'badge-disewa',
                            'selesai'             => 'badge-selesai',
                            'dibatalkan'          => 'badge-dibatalkan',
                            default               => 'badge-normal',
                        };
                        $labelTrans = ucfirst(str_replace('_', ' ', $stTrans));

                        $lunas      = ($t['status_pembayaran'] === 'lunas');
                        $badgeBayar = $lunas ? 'badge-lunas' : 'badge-belum-dibayar';
                        $labelBayar = $lunas ? 'Lunas' : ucfirst(str_replace('_', ' ', (string) $t['status_pembayaran']));
                    ?>
                    <tr class="<?= $kelasBaris ?>">
                        <!-- Kode Transaksi -->
                        <td class="whitespace-nowrap">
                            <div class="flex items-center gap-1.5">
                                <?php if ($isMenunggu): ?>
                                    <span class="w-2 h-2 rounded-full bg-amber-500 flex-shrink-0" title="Menunggu Verifikasi"></span>
                                <?php endif; ?>
                                <span class="font-mono font-bold text-xs text-[#111111]"><?= e($kodeTrans) ?></span>
                            </div>
                            <div class="text-[10px] text-gray-400 mt-0.5">
                                Pesan: <?= e(format_tanggal($t['waktu_pemesanan'])) ?>
                            </div>
                        </td>

                        <!-- Penyewa -->
                        <td class="font-semibold text-gray-900">
                            <?= e($t['nama_penyewa']) ?>
                        </td>

                        <!-- Telepon -->
                        <td class="text-xs text-gray-600 whitespace-nowrap">
                            <?= e($t['no_telepon']) ?>
                        </td>

                        <!-- Barang -->
                        <td class="text-xs text-gray-800 max-w-xs">
                            <span class="line-clamp-2" title="<?= e($t['daftar_barang']) ?>">
                                <?= e($t['daftar_barang']) ?>
                            </span>
                        </td>

                        <!-- Waktu Sewa -->
                        <td class="text-xs text-gray-700 whitespace-nowrap">
                            <div><span class="text-gray-400">Mulai:</span> <?= e(format_tanggal($t['waktu_mulai_sewa'])) ?></div>
                            <div><span class="text-gray-400">Kembali:</span> <?= e(format_tanggal($t['waktu_rencana_kembali'])) ?></div>
                        </td>

                        <!-- Status Pembayaran -->
                        <td class="whitespace-nowrap">
                            <span class="badge <?= $badgeBayar ?>"><?= e($labelBayar) ?></span>
                            <div class="text-[11px] text-gray-400 mt-0.5"><?= e(ucfirst((string) $t['jenis_pembayaran'])) ?></div>
                        </td>

                        <!-- Status Transaksi -->
                        <td class="whitespace-nowrap">
                            <span class="badge <?= $badgeTrans ?>">
                                <?= $isMenunggu ? '⏳ ' : '' ?><?= e($labelTrans) ?>
                            </span>
                        </td>

                        <!-- Total Tagihan -->
                        <td class="text-xs font-bold text-[#111111] whitespace-nowrap">
                            <?= e(format_rupiah($t['total_tagihan'])) ?>
                        </td>

                        <!-- Aksi -->
                        <td class="text-right whitespace-nowrap">
                            <a href="<?= e(base_url('transaksi/detail.php?id=' . $idTrans)) ?>"
                               class="<?= $isMenunggu ? 'btn-utama' : 'btn-sekunder' ?> text-xs py-1.5 px-3.5">
                                <?= $isMenunggu ? 'Verifikasi' : 'Detail' ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════
         PAGINASI (20 BARIS PER HALAMAN)
         ═══════════════════════════════════════════════════════════════════ -->
    <?php if ($totalHalaman > 1): ?>
        <div class="flex flex-wrap items-center justify-between gap-3 mt-5 pt-4 border-t border-gray-100 text-xs">
            <div class="text-gray-500">
                Halaman <strong><?= e((string) $halaman) ?></strong> dari <strong><?= e((string) $totalHalaman) ?></strong>
                (Total <?= e((string) $totalBaris) ?> transaksi)
            </div>

            <div class="flex flex-wrap items-center gap-1">
                <?php if ($halaman > 1): ?>
                    <a href="<?= e(_url_halaman_transaksi($halaman - 1, $cari, $status)) ?>"
                       class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 hover:bg-[#111111] hover:text-white hover:border-[#111111] transition-colors">
                        &larr; Sebelumnya
                    </a>
                <?php endif; ?>

                <?php
                $awal  = max(1, $halaman - 2);
                $akhir = min($totalHalaman, $halaman + 2);
                for ($p = $awal; $p <= $akhir; $p++):
                ?>
                    <a href="<?= e(_url_halaman_transaksi($p, $cari, $status)) ?>"
                       class="w-8 h-8 rounded-full flex items-center justify-center font-semibold transition-colors
                              <?= $p === $halaman ? 'bg-[#111111] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                        <?= e((string) $p) ?>
                    </a>
                <?php endfor; ?>

                <?php if ($halaman < $totalHalaman): ?>
                    <a href="<?= e(_url_halaman_transaksi($halaman + 1, $cari, $status)) ?>"
                       class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 hover:bg-[#111111] hover:text-white hover:border-[#111111] transition-colors">
                        Berikutnya &rarr;
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer_admin.php'; ?>
