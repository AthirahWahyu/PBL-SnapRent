<?php
declare(strict_types=1);

if (!defined('_DATA_DASHBOARD_LOADED')) {
    define('_DATA_DASHBOARD_LOADED', true);

    /** 1. Total transaksi yang berstatus disewa atau selesai (Kartu: Total Disewa) */
    function db_total_disewa(PDO $pdo): int
    {
        return (int) $pdo->query("
            SELECT COUNT(*) FROM transaksi
            WHERE status_transaksi IN ('disewa', 'selesai')
        ")->fetchColumn();
    }

    /** 2. Jumlah transaksi berstatus sedang disewa (Kartu: Sedang Disewa) */
    function db_sedang_disewa(PDO $pdo): int
    {
        return (int) $pdo->query("
            SELECT COUNT(*) FROM transaksi
            WHERE status_transaksi = 'disewa'
        ")->fetchColumn();
    }

    /** 3. Total pendapatan hari ini (Kartu: Pendapatan Hari Ini) */
    function db_pendapatan_hari_ini(PDO $pdo): float
    {
        return (float) $pdo->query("
            SELECT COALESCE(SUM(total_tagihan), 0)
            FROM transaksi
            WHERE (status_transaksi = 'selesai' AND DATE(waktu_aktual_kembali) = CURRENT_DATE)
               OR (status_pembayaran = 'lunas' AND DATE(waktu_pemesanan) = CURRENT_DATE)
        ")->fetchColumn();
    }

    /** 4. Pelanggan baru hari ini (Kartu: Pelanggan Baru) */
    function db_pelanggan_baru(PDO $pdo): int
    {
        return (int) $pdo->query("
            SELECT COUNT(*) FROM (
                SELECT id_penyewa
                FROM transaksi
                GROUP BY id_penyewa
                HAVING MIN(DATE(waktu_pemesanan)) = CURRENT_DATE
            ) AS pelanggan_hari_ini
        ")->fetchColumn();
    }

    /** 5. Hitung total baris transaksi untuk paginasi Rincian Hari Ini */
    function db_hitung_rincian_hari_ini(PDO $pdo): int
    {
        return (int) $pdo->query("
            SELECT COUNT(*)
            FROM transaksi t
            WHERE DATE(t.waktu_pemesanan) = CURRENT_DATE
        ")->fetchColumn();
    }

    /** 6. Ambil daftar transaksi untuk tabel Rincian Hari Ini dengan paginasi */
    function db_rincian_hari_ini(PDO $pdo, int $limit = 10, int $offset = 0): array
    {
        $stmt = $pdo->prepare("
            SELECT
                t.id_transaksi,
                p.nama_penyewa,
                p.no_telepon,
                t.waktu_pemesanan,
                t.waktu_mulai_sewa,
                t.waktu_rencana_kembali,
                t.status_transaksi,
                t.status_pembayaran,
                t.total_tagihan,
                v.level_reminder,
                COALESCE(v.mendekati_batas, FALSE) AS mendekati_batas,
                COALESCE(v.jam_terlambat, 0) AS jam_terlambat,
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
            LEFT JOIN v_reminder_pengembalian v ON v.id_transaksi = t.id_transaksi
            WHERE DATE(t.waktu_pemesanan) = CURRENT_DATE
            ORDER BY t.waktu_pemesanan DESC, t.id_transaksi DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 7. Statistik 12 bulan terakhir untuk bar chart pendapatan & jumlah transaksi.
     * Digunakan oleh halaman laporan/index.php.
     */
    function db_chart_pendapatan(PDO $pdo): array
    {
        $rowsPendapatan = $pdo->query("
            SELECT
                TO_CHAR(DATE_TRUNC('month', waktu_aktual_kembali), 'YYYY-MM') AS ym,
                COALESCE(SUM(total_tagihan), 0) AS total
            FROM transaksi
            WHERE status_transaksi = 'selesai'
              AND waktu_aktual_kembali >= DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '11 months'
            GROUP BY 1
            ORDER BY 1
        ")->fetchAll(PDO::FETCH_ASSOC);

        $rowsTransaksi = $pdo->query("
            SELECT
                TO_CHAR(DATE_TRUNC('month', waktu_pemesanan), 'YYYY-MM') AS ym,
                COUNT(*) AS jumlah
            FROM transaksi
            WHERE status_transaksi <> 'dibatalkan'
              AND waktu_pemesanan >= DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '11 months'
            GROUP BY 1
            ORDER BY 1
        ")->fetchAll(PDO::FETCH_ASSOC);

        $bulan_indo = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $result = [];
        for ($i = 11; $i >= 0; $i--) {
            $ts  = strtotime("first day of -$i months");
            $key = date('Y-m', $ts);
            $result[$key] = [
                'periode' => $key,
                'bulan'   => $bulan_indo[(int) date('n', $ts) - 1],
                'label'   => $bulan_indo[(int) date('n', $ts) - 1],
                'total'   => 0.0,
                'jumlah'  => 0,
            ];
        }

        foreach ($rowsPendapatan as $r) {
            $key = $r['ym'];
            if (isset($result[$key])) {
                $result[$key]['total'] = (float) $r['total'];
            }
        }

        foreach ($rowsTransaksi as $r) {
            $key = $r['ym'];
            if (isset($result[$key])) {
                $result[$key]['jumlah'] = (int) $r['jumlah'];
            }
        }

        return array_values($result);
    }
}

// ── Endpoint JSON bila file ini dipanggil langsung lewat fetch (Auto-Refresh) ──
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/helpers.php';
    require_once __DIR__ . '/../includes/koneksi.php';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    if (!admin_saat_ini()) {
        http_response_code(401);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Sesi berakhir. Silakan login kembali.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $total_disewa     = db_total_disewa($pdo);
        $sedang_disewa    = db_sedang_disewa($pdo);
        $pendapatan_hari  = db_pendapatan_hari_ini($pdo);
        $pelanggan_baru   = db_pelanggan_baru($pdo);

        $rows_hari_ini = db_rincian_hari_ini($pdo, 10, 0);
        $list_hari_ini = [];
        foreach ($rows_hari_ini as $idx => $r) {
            $idTrans = (int) $r['id_transaksi'];
            $list_hari_ini[] = [
                'no'                    => $idx + 1,
                'id_transaksi'          => $idTrans,
                'kode_transaksi'        => sprintf('#SR-%04d', $idTrans),
                'daftar_barang'         => $r['daftar_barang'],
                'nama_penyewa'          => $r['nama_penyewa'],
                'no_telepon'            => $r['no_telepon'],
                'waktu_mulai_sewa'      => format_tanggal($r['waktu_mulai_sewa']),
                'waktu_rencana_kembali' => format_tanggal($r['waktu_rencana_kembali']),
                'status_transaksi'      => (string) $r['status_transaksi'],
                'level_reminder'        => $r['level_reminder'],
                'mendekati_batas'       => !empty($r['mendekati_batas']),
                'url_detail'            => base_url('transaksi/detail.php?id=' . $idTrans),
                'url_kembali'           => base_url('pengembalian/kembali.php?id=' . $idTrans),
            ];
        }

        echo json_encode([
            'status' => 'ok',
            'data'   => [
                'kartu' => [
                    'total_disewa'          => $total_disewa,
                    'sedang_disewa'         => $sedang_disewa,
                    'pendapatan_hari_penuh' => format_rupiah($pendapatan_hari),
                    'pelanggan_baru'        => $pelanggan_baru,
                ],
                'rincian_hari_ini' => $list_hari_ini,
                'waktu_server'     => date('H:i:s'),
            ],
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('[SnapRent] data_dashboard JSON error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Gagal memuat data dasbor.',
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
