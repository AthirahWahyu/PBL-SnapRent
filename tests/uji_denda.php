<?php
/**
 * tests/uji_denda.php
 * Skrip CLI untuk menguji fungsi hitung_denda_preview() terhadap 6 skenario
 * keterlambatan (batas kembali pukul 10:00) sekaligus memastikan hasilnya
 * identik dengan stored procedure sp_proses_pengembalian di PostgreSQL.
 *
 * Cara menjalankan:
 *   php tests/uji_denda.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Skrip ini hanya dapat dijalankan melalui CLI.\n");
}

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/denda.php';

$tz = new DateTimeZone('Asia/Jakarta');

echo "=====================================================================\n";
echo " PENGUJIAN OTOMATIS PERHITUNGAN DENDA (hitung_denda_preview vs SP)\n";
echo "=====================================================================\n";

$semuaLulus = true;

try {
    // Gunakan transaksi database dan rollback di akhir agar data uji bersih 100%
    $pdo->beginTransaction();

    // 1. Ambil 1 id_admin dan 1 id_kategori untuk membuat data uji mandiri
    $idAdmin = (int) $pdo->query('SELECT id_admin FROM admin ORDER BY id_admin LIMIT 1')->fetchColumn();
    if ($idAdmin <= 0) {
        $stmtAdm = $pdo->prepare("INSERT INTO admin (username, password, nama_lengkap) VALUES ('admin_uji_cli', 'hash', 'Admin Uji CLI') RETURNING id_admin");
        $stmtAdm->execute();
        $idAdmin = (int) $stmtAdm->fetchColumn();
    }

    $idKategori = (int) $pdo->query('SELECT id_kategori FROM kategori ORDER BY id_kategori LIMIT 1')->fetchColumn();

    // 2. Buat 2 barang uji khusus agar punya multi-barang dengan harga_satuan jelas
    $stmtBrg1 = $pdo->prepare(<<<'SQL'
        INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, status_barang)
        VALUES (:kat, 'Kamera Uji CLI Alpha', 150000, 'aktif')
        RETURNING id_barang
    SQL);
    $stmtBrg1->execute([':kat' => $idKategori]);
    $idBarang1 = (int) $stmtBrg1->fetchColumn();

    $stmtBrg2 = $pdo->prepare(<<<'SQL'
        INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, status_barang)
        VALUES (:kat, 'Lensa Uji CLI Beta', 100000, 'aktif')
        RETURNING id_barang
    SQL);
    $stmtBrg2->execute([':kat' => $idKategori]);
    $idBarang2 = (int) $stmtBrg2->fetchColumn();

    $totalHargaSatuanHarapan = 150000.0 + 100000.0; // Rp 250.000 (1 hari penuh untuk 2 barang)

    // 3. Buat penyewa uji
    $stmtPenyewa = $pdo->prepare(<<<'SQL'
        INSERT INTO penyewa (nama_penyewa, no_telepon)
        VALUES ('Penyewa Uji Denda CLI', '089999991000')
        ON CONFLICT (no_telepon) DO UPDATE SET nama_penyewa = EXCLUDED.nama_penyewa
        RETURNING id_penyewa
    SQL);
    $stmtPenyewa->execute();
    $idPenyewa = (int) $stmtPenyewa->fetchColumn();

    // 4. Buat transaksi uji berstatus 'disewa' dengan batas kembali pukul 10:00
    $tanggalUji   = '2099-06-15';
    $waktuMulai   = '2099-06-14 10:00:00';
    $batasKembali = $tanggalUji . ' 10:00:00';

    $stmtTrx = $pdo->prepare(<<<'SQL'
        INSERT INTO transaksi (
            id_penyewa, id_admin, waktu_mulai_sewa, waktu_rencana_kembali, waktu_ambil_aktual,
            jenis_pembayaran, skema_pembayaran, bukti_pembayaran, setuju_syarat,
            status_pembayaran, status_transaksi
        ) VALUES (
            :penyewa, :admin, :mulai, :rencana, :ambil,
            'tunai', 'lunas', 'uji/bukti-uji.png', TRUE,
            'lunas', 'disewa'
        ) RETURNING id_transaksi
    SQL);
    $stmtTrx->execute([
        ':penyewa' => $idPenyewa,
        ':admin'   => $idAdmin,
        ':mulai'   => $waktuMulai,
        ':rencana' => $batasKembali,
        ':ambil'   => $waktuMulai,
    ]);
    $idTransaksi = (int) $stmtTrx->fetchColumn();

    // Masukkan 2 barang ke detail_transaksi
    $stmtDet = $pdo->prepare(<<<'SQL'
        INSERT INTO detail_transaksi (id_transaksi, id_barang, harga_satuan, subtotal)
        VALUES (:trx, :brg, :harga, 0)
    SQL);
    $stmtDet->execute([':trx' => $idTransaksi, ':brg' => $idBarang1, ':harga' => 150000]);
    $stmtDet->execute([':trx' => $idTransaksi, ':brg' => $idBarang2, ':harga' => 100000]);

    echo "Transaksi Uji #{$idTransaksi} | Batas Kembali: {$batasKembali}\n";
    echo "Total Harga Satuan (Sewa 1 Hari Penuh): " . format_rupiah($totalHargaSatuanHarapan) . "\n";
    echo "---------------------------------------------------------------------\n";

    // 5. Daftar 6 skenario sesuai spesifikasi
    $skenario = [
        [
            'label'          => '09.45 -> tanpa denda',
            'waktu'          => $tanggalUji . ' 09:45:00',
            'harapan_nominal'=> 0.0,
            'harapan_jenis'  => null,
            'cek_tanpa_denda'=> true,
        ],
        [
            'label'          => '10.30 -> Rp 0 (toleransi)',
            'waktu'          => $tanggalUji . ' 10:30:00',
            'harapan_nominal'=> 0.0,
            'harapan_jenis'  => 'nominal_tetap',
            'cek_tanpa_denda'=> false,
        ],
        [
            'label'          => '11.00 -> Rp 0 (batas akhir toleransi 1 jam)',
            'waktu'          => $tanggalUji . ' 11:00:00',
            'harapan_nominal'=> 0.0,
            'harapan_jenis'  => 'nominal_tetap',
            'cek_tanpa_denda'=> false,
        ],
        [
            'label'          => '12.00 -> Rp 50.000 (telat 2 jam)',
            'waktu'          => $tanggalUji . ' 12:00:00',
            'harapan_nominal'=> 50000.0,
            'harapan_jenis'  => 'nominal_tetap',
            'cek_tanpa_denda'=> false,
        ],
        [
            'label'          => '13.00 -> Rp 50.000 (batas akhir 3 jam)',
            'waktu'          => $tanggalUji . ' 13:00:00',
            'harapan_nominal'=> 50000.0,
            'harapan_jenis'  => 'nominal_tetap',
            'cek_tanpa_denda'=> false,
        ],
        [
            'label'          => '13.01 -> sewa 1 hari penuh (jumlah harga_satuan seluruh barang)',
            'waktu'          => $tanggalUji . ' 13:01:00',
            'harapan_nominal'=> $totalHargaSatuanHarapan,
            'harapan_jenis'  => 'sewa_harian',
            'cek_tanpa_denda'=> false,
        ],
    ];

    foreach ($skenario as $idx => $s) {
        $no = $idx + 1;
        $dtKembali = new DateTime($s['waktu'], $tz);

        // A. Hitung menggunakan hitung_denda_preview()
        $prev = hitung_denda_preview($pdo, $idTransaksi, $dtKembali);

        // B. Bandingkan dengan eksekusi nyata CALL sp_proses_pengembalian menggunakan SAVEPOINT
        $pdo->exec("SAVEPOINT sp_uji_{$no}");
        $stmtCall = $pdo->prepare('CALL sp_proses_pengembalian(:id, :waktu)');
        $stmtCall->execute([
            ':id'    => $idTransaksi,
            ':waktu' => $s['waktu'],
        ]);

        $stmtAfter = $pdo->prepare('SELECT id_denda, nominal_denda, total_tagihan FROM transaksi WHERE id_transaksi = :id');
        $stmtAfter->execute([':id' => $idTransaksi]);
        $hasilSp = $stmtAfter->fetch(PDO::FETCH_ASSOC);
        $pdo->exec("ROLLBACK TO SAVEPOINT sp_uji_{$no}");

        $idDendaSp    = $hasilSp['id_denda'] !== null ? (int) $hasilSp['id_denda'] : null;
        $nominalSp    = (float) $hasilSp['nominal_denda'];
        $totalAkhirSp = (float) $hasilSp['total_tagihan'];

        // Validasi kecocokan terhadap harapan dan terhadap stored procedure
        $cocokNominal = abs($prev['nominal'] - $s['harapan_nominal']) < 0.01;
        $cocokJenis   = ($prev['jenis_denda'] === $s['harapan_jenis']);
        $cocokNullId  = $s['cek_tanpa_denda'] ? ($prev['id_denda'] === null) : ($prev['id_denda'] !== null);
        $cocokSp      = (
            $prev['id_denda'] === $idDendaSp
            && abs($prev['nominal'] - $nominalSp) < 0.01
            && abs($prev['total_akhir'] - $totalAkhirSp) < 0.01
        );

        $lulus = $cocokNominal && $cocokJenis && $cocokNullId && $cocokSp;
        if (!$lulus) {
            $semuaLulus = false;
        }

        $statusTeks = $lulus ? 'LULUS' : 'GAGAL';
        printf(
            "[%s] Skenario %d (%s)\n" .
            "        Preview -> telat: %s jam | jenis: %s | nominal: %s | total_akhir: %s\n" .
            "        SP DB   -> id_denda: %s | nominal: %s | total_akhir: %s\n",
            $statusTeks,
            $no,
            $s['label'],
            number_format($prev['telat_jam'], 4, '.', ''),
            $prev['jenis_denda'] ?? 'NULL (tanpa denda)',
            format_rupiah($prev['nominal']),
            format_rupiah($prev['total_akhir']),
            $idDendaSp ?? 'NULL',
            format_rupiah($nominalSp),
            format_rupiah($totalAkhirSp)
        );
    }

    // Bersihkan seluruh data uji dengan rollback
    $pdo->rollBack();

    echo "---------------------------------------------------------------------\n";
    echo "Data uji sementara telah dibersihkan (rollback).\n";
    echo "HASIL AKHIR: " . ($semuaLulus ? "SEMUA SKENARIO LULUS" : "ADA SKENARIO GAGAL") . "\n";
    echo "=====================================================================\n";

    exit($semuaLulus ? 0 : 1);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "GAGAL EKSEKUSI UJI DENDA: " . $e->getMessage() . "\n");
    exit(1);
}
