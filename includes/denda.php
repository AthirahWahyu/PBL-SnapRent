<?php
/**
 * includes/denda.php
 * Fungsi perhitungan pratinjau denda pengembalian yang identik dengan
 * stored procedure sp_proses_pengembalian di PostgreSQL.
 * File ini TIDAK boleh menampilkan output apa pun bila dibuka langsung.
 */

if (defined('_DENDA_LOADED')) return;
define('_DENDA_LOADED', true);

/**
 * Menghitung pratinjau keterlambatan dan denda untuk suatu transaksi.
 * Logika identik dengan sp_proses_pengembalian(p_id_transaksi, p_waktu_kembali):
 *   - telat_jam = max(0, (waktu_kembali - waktu_rencana_kembali) dalam jam)
 *   - pilih 1 aturan denda dengan telat_jam > batas_min_jam
 *     dan (batas_max_jam IS NULL atau telat_jam <= batas_max_jam)
 *     diurutkan berdasarkan batas_min_jam DESC
 *   - jenis_denda = 'nominal_tetap' -> nilai_denda
 *   - jenis_denda = 'sewa_harian'   -> SUM(harga_satuan * jumlah) dari detail_transaksi
 *
 * @param PDO      $pdo           Koneksi PDO PostgreSQL aktif
 * @param int      $id_transaksi  ID transaksi yang dihitung
 * @param DateTime $waktu_kembali Waktu aktual/pratinjau pengembalian
 * @return array{
 *     telat_jam: float,
 *     id_denda: int|null,
 *     jenis_denda: string|null,
 *     nominal: float,
 *     total_akhir: float,
 *     subtotal_sewa: float,
 *     total_harga_satuan: float,
 *     keterangan: string
 * }
 * @throws InvalidArgumentException Bila transaksi tidak ditemukan
 */
function hitung_denda_preview(PDO $pdo, int $id_transaksi, DateTime $waktu_kembali): array
{
    $waktuStr = $waktu_kembali->format('Y-m-d H:i:s');

    // 1. Ambil waktu_rencana_kembali, selisih jam presisi NUMERIC PostgreSQL,
    //    serta total subtotal sewa dan total harga_satuan barang.
    $sqlTrx = <<<'SQL'
        SELECT t.id_transaksi,
               t.waktu_rencana_kembali,
               GREATEST(
                   0,
                   EXTRACT(EPOCH FROM (:waktu_kembali::timestamp - t.waktu_rencana_kembali))::NUMERIC / 3600
               ) AS telat_jam,
               COALESCE((
                   SELECT SUM(dt.subtotal)
                     FROM detail_transaksi dt
                    WHERE dt.id_transaksi = t.id_transaksi
               ), 0) AS subtotal_sewa,
               COALESCE((
                   SELECT SUM(dt.harga_satuan * dt.jumlah)
                     FROM detail_transaksi dt
                    WHERE dt.id_transaksi = t.id_transaksi
               ), 0) AS total_harga_satuan,
                t.biaya_kerusakan
          FROM transaksi t
         WHERE t.id_transaksi = :id_transaksi
    SQL;

    $stmtTrx = $pdo->prepare($sqlTrx);
    $stmtTrx->execute([
        ':waktu_kembali' => $waktuStr,
        ':id_transaksi'  => $id_transaksi,
    ]);
    $trx = $stmtTrx->fetch(PDO::FETCH_ASSOC);

    if (!$trx) {
        throw new InvalidArgumentException("Transaksi #{$id_transaksi} tidak ditemukan.");
    }

    $telatJamNumeric  = (string) $trx['telat_jam'];
    $telatJamFloat    = (float) $trx['telat_jam'];
    $subtotalSewa     = (float) $trx['subtotal_sewa'];
    $totalHargaSatuan = (float) $trx['total_harga_satuan'];

    // 2. Cari aturan denda yang cocok (identik dengan sp_proses_pengembalian)
    $sqlAturan = <<<'SQL'
        SELECT id_denda,
               jenis_denda,
               batas_min_jam,
               batas_max_jam,
               nilai_denda,
               keterangan
          FROM denda d
         WHERE :telat_jam::NUMERIC > d.batas_min_jam
           AND (d.batas_max_jam IS NULL OR :telat_jam::NUMERIC <= d.batas_max_jam)
         ORDER BY d.batas_min_jam DESC
         LIMIT 1
    SQL;

    $stmtAturan = $pdo->prepare($sqlAturan);
    $stmtAturan->execute([':telat_jam' => $telatJamNumeric]);
    $aturan = $stmtAturan->fetch(PDO::FETCH_ASSOC);

    $idDenda    = null;
    $jenisDenda = null;
    $nominal    = 0.0;
    $keterangan = 'Tepat waktu (tanpa denda)';

    if ($aturan) {
        $idDenda    = (int) $aturan['id_denda'];
        $jenisDenda = (string) $aturan['jenis_denda'];
        $keterangan = (string) ($aturan['keterangan'] ?? '');

        if ($jenisDenda === 'nominal_tetap') {
            $nominal = (float) $aturan['nilai_denda'];
        } else {
            // sewa_harian = jumlah (harga_satuan x jumlah) semua barang pada transaksi
            $nominal = $totalHargaSatuan;
        }
    }

    $totalAkhir = $subtotalSewa + $nominal + (float) $trx['biaya_kerusakan'];

    return [
        'telat_jam'          => $telatJamFloat,
        'id_denda'           => $idDenda,
        'jenis_denda'        => $jenisDenda,
        'nominal'            => $nominal,
        'total_akhir'        => $totalAkhir,
        'subtotal_sewa'      => $subtotalSewa,
        'total_harga_satuan' => $totalHargaSatuan,
        'keterangan'         => $keterangan,
    ];
}
