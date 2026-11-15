<?php
require_once __DIR__ . '/../includes/auth.php';
wajib_login();

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';

$admin = admin_saat_ini();

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
$waktu_cetak   = $sekarang->format('d/m/Y H:i') . ' WIB';

// ── 1. Query Ringkasan (identik dengan laporan/index.php) ────────────────────
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

// ── 2. Query Daftar Transaksi Selesai (identik dengan laporan/index.php) ─────
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

// ── 3. Query Ringkasan per Kategori Barang ───────────────────────────────────
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pendapatan SnapRent — <?= e($label_periode) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Poppins', Arial, sans-serif;
            color: #111111;
            background: #f3f4f6;
            font-size: 12px;
            line-height: 1.5;
            padding: 24px 16px;
        }
        .kertas {
            max-width: 960px;
            margin: 0 auto;
            background: #ffffff;
            padding: 36px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }
        .toolbar-cetak {
            max-width: 960px;
            margin: 0 auto 16px auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid #111111;
            transition: opacity 0.15s;
        }
        .btn-hitam {
            background: #111111;
            color: #ffffff;
        }
        .btn-putih {
            background: #ffffff;
            color: #111111;
            border-color: #d1d5db;
        }
        .btn:hover {
            opacity: 0.85;
        }
        .kop {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111111;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .kop-judul h1 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .kop-judul p {
            font-size: 12px;
            color: #4b5563;
            margin-top: 2px;
        }
        .kop-meta {
            text-align: right;
            font-size: 11px;
            color: #374151;
        }
        .kop-meta strong {
            color: #111111;
        }
        .grid-ringkasan {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .kotak-stat {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 12px 14px;
        }
        .kotak-stat--utama {
            background: #111111;
            color: #ffffff;
            border-color: #111111;
        }
        .kotak-stat__label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            font-weight: 600;
        }
        .kotak-stat--utama .kotak-stat__label {
            color: #d1d5db;
        }
        .kotak-stat__nilai {
            font-size: 15px;
            font-weight: 700;
            margin-top: 4px;
        }
        .judul-seksi {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 8px;
            color: #111111;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 11.5px;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
            vertical-align: top;
        }
        thead th {
            background: #f3f4f6;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.4px;
            color: #111111;
        }
        tfoot td {
            background: #f9fafb;
            font-weight: 700;
            border-top: 2px solid #111111;
        }
        .kanan {
            text-align: right;
        }
        .tengah {
            text-align: center;
        }
        .nowrap {
            white-space: nowrap;
        }
        .teks-denda {
            color: #be123c;
            font-weight: 600;
        }
        .ttd {
            margin-top: 28px;
            display: flex;
            justify-content: flex-end;
            page-break-inside: avoid;
        }
        .ttd-box {
            width: 220px;
            text-align: center;
            font-size: 11px;
        }
        .ttd-garis {
            margin-top: 56px;
            border-top: 1px solid #111111;
            padding-top: 4px;
            font-weight: 700;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm;
            }
            body {
                background: #ffffff;
                padding: 0;
                font-size: 11px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .kertas {
                max-width: 100%;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>

    <!-- Toolbar Aksi (Tidak Ikut Tercetak) -->
    <div class="toolbar-cetak no-print">
        <a href="<?= e(base_url('laporan/index.php?bulan=' . $bulan . '&tahun=' . $tahun)) ?>" class="btn btn-putih">
            &larr; Kembali ke Halaman Laporan
        </a>
        <button type="button" onclick="window.print()" class="btn btn-hitam">
            Cetak Laporan Sekarang
        </button>
    </div>

    <div class="kertas">
        <!-- Kop Laporan -->
        <div class="kop">
            <div class="kop-judul">
                <h1>Laporan Pendapatan SnapRent</h1>
                <p>Layanan Penyewaan Kamera &amp; Peralatan Fotografi Profesional</p>
            </div>
            <div class="kop-meta">
                <div>Periode Laporan: <strong><?= e($label_periode) ?></strong></div>
                <div>Tanggal Cetak: <strong><?= e($waktu_cetak) ?></strong></div>
                <div>Dicetak oleh: <strong><?= e($admin['nama_lengkap'] ?? 'Administrator') ?></strong></div>
            </div>
        </div>

        <!-- 4 Kotak Ringkasan (Angka Identik dengan laporan/index.php) -->
        <div class="grid-ringkasan">
            <div class="kotak-stat">
                <div class="kotak-stat__label">Jumlah Transaksi Selesai</div>
                <div class="kotak-stat__nilai"><?= number_format($jumlah_selesai, 0, ',', '.') ?> Transaksi</div>
            </div>
            <div class="kotak-stat">
                <div class="kotak-stat__label">Total Pendapatan Sewa</div>
                <div class="kotak-stat__nilai"><?= e(format_rupiah($total_sewa)) ?></div>
            </div>
            <div class="kotak-stat">
                <div class="kotak-stat__label">Total Denda</div>
                <div class="kotak-stat__nilai"><?= e(format_rupiah($total_denda)) ?></div>
            </div>
            <div class="kotak-stat kotak-stat--utama">
                <div class="kotak-stat__label">Total Pendapatan</div>
                <div class="kotak-stat__nilai"><?= e(format_rupiah($total_pendapatan)) ?></div>
            </div>
        </div>

        <!-- Tabel 1: Rincian Transaksi Selesai -->
        <div class="judul-seksi">1. Rincian Transaksi Selesai (Periode <?= e($label_periode) ?>)</div>
        <table>
            <thead>
                <tr>
                    <th class="tengah" style="width: 36px;">No</th>
                    <th style="width: 85px;">Kode</th>
                    <th>Penyewa &amp; Telepon</th>
                    <th>Barang Disewa</th>
                    <th style="width: 115px;">Aktual Kembali</th>
                    <th class="kanan" style="width: 110px;">Pendapatan Sewa</th>
                    <th class="kanan" style="width: 95px;">Denda</th>
                    <th class="kanan" style="width: 115px;">Total Tagihan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftar_transaksi)): ?>
                    <tr>
                        <td colspan="8" class="tengah" style="padding: 20px; color: #6b7280;">
                            Tidak ada transaksi selesai pada periode <?= e($label_periode) ?>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftar_transaksi as $idx => $row): ?>
                        <tr>
                            <td class="tengah"><?= $idx + 1 ?></td>
                            <td class="nowrap"><strong>#SR-<?= e(str_pad((string) $row['id_transaksi'], 4, '0', STR_PAD_LEFT)) ?></strong></td>
                            <td>
                                <strong><?= e($row['nama_penyewa']) ?></strong><br>
                                <span style="color: #4b5563; font-size: 10.5px;"><?= e($row['no_telepon']) ?></span>
                            </td>
                            <td><?= e($row['daftar_barang'] ?? '-') ?></td>
                            <td class="nowrap"><?= e(format_tanggal($row['waktu_aktual_kembali'])) ?></td>
                            <td class="kanan nowrap"><?= e(format_rupiah($row['pendapatan_sewa'])) ?></td>
                            <td class="kanan nowrap <?= (float) $row['nominal_denda'] > 0 ? 'teks-denda' : '' ?>">
                                <?= e(format_rupiah($row['nominal_denda'])) ?>
                            </td>
                            <td class="kanan nowrap"><strong><?= e(format_rupiah($row['total_tagihan'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="kanan">TOTAL PERIODE <?= e(mb_strtoupper($label_periode)) ?></td>
                    <td class="kanan nowrap"><?= e(format_rupiah($total_sewa)) ?></td>
                    <td class="kanan nowrap"><?= e(format_rupiah($total_denda)) ?></td>
                    <td class="kanan nowrap"><?= e(format_rupiah($total_pendapatan)) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Tabel 2: Ringkasan per Kategori Barang -->
        <div class="judul-seksi">2. Ringkasan Pendapatan Sewa per Kategori Barang</div>
        <table>
            <thead>
                <tr>
                    <th class="tengah" style="width: 36px;">No</th>
                    <th>Nama Kategori</th>
                    <th class="kanan" style="width: 130px;">Jumlah Transaksi</th>
                    <th class="kanan" style="width: 130px;">Unit Disewa</th>
                    <th class="kanan" style="width: 150px;">Total Subtotal Sewa</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ringkasan_kategori)): ?>
                    <tr>
                        <td colspan="5" class="tengah" style="padding: 16px; color: #6b7280;">
                            Tidak ada data kategori barang pada periode ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    $totalUnitKat = 0;
                    $totalSubKat  = 0.0;
                    foreach ($ringkasan_kategori as $idx => $kat):
                        $totalUnitKat += (int) $kat['jumlah_unit_disewa'];
                        $totalSubKat  += (float) $kat['total_subtotal_kategori'];
                    ?>
                        <tr>
                            <td class="tengah"><?= $idx + 1 ?></td>
                            <td><strong><?= e($kat['nama_kategori']) ?></strong></td>
                            <td class="kanan"><?= number_format((int) $kat['jumlah_transaksi'], 0, ',', '.') ?></td>
                            <td class="kanan"><?= number_format((int) $kat['jumlah_unit_disewa'], 0, ',', '.') ?> unit</td>
                            <td class="kanan nowrap"><strong><?= e(format_rupiah($kat['total_subtotal_kategori'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($ringkasan_kategori)): ?>
                <tfoot>
                    <tr>
                        <td colspan="3" class="kanan">TOTAL SELURUH KATEGORI</td>
                        <td class="kanan nowrap"><?= number_format($totalUnitKat, 0, ',', '.') ?> unit</td>
                        <td class="kanan nowrap"><?= e(format_rupiah($totalSubKat)) ?></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>

        <!-- Tanda Tangan Petugas -->
        <div class="ttd">
            <div class="ttd-box">
                <div>Mengetahui, Admin SnapRent</div>
                <div class="ttd-garis"><?= e($admin['nama_lengkap'] ?? 'Administrator') ?></div>
            </div>
        </div>
    </div>

</body>
</html>
