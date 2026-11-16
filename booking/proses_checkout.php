<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /booking/checkout.php');
    exit;
}

$keranjang = $_SESSION['keranjang'] ?? [];
if (empty($keranjang)) {
    header('Location: /index.php');
    exit;
}

$namaPenyewa     = trim($_POST['nama_penyewa'] ?? '');
$noTelepon       = preg_replace('/[^0-9+]/', '', trim($_POST['no_telepon'] ?? ''));
$waktuMulai      = $_POST['waktu_mulai_sewa'] ?? '';
$waktuKembali    = $_POST['waktu_rencana_kembali'] ?? '';
$jenisPembayaran = $_POST['jenis_pembayaran'] ?? '';
$skemaPembayaran = $_POST['skema_pembayaran'] ?? '';
$setujuSyarat    = isset($_POST['setuju_syarat']);
$jenisJaminanArr = $_POST['jenis_jaminan'] ?? [];
$noIdentitasArr  = $_POST['no_identitas'] ?? [];

// Validasi dasar
if ($namaPenyewa === '' || $noTelepon === '' || $waktuMulai === '' || $waktuKembali === '' || !$setujuSyarat) {
    $_SESSION['checkout_error'] = 'Mohon lengkapi seluruh data pemesanan dan setujui syarat & ketentuan.';
    header('Location: /booking/checkout.php');
    exit;
}

$tsMulai   = strtotime($waktuMulai);
$tsKembali = strtotime($waktuKembali);
if (!$tsMulai || !$tsKembali || $tsKembali <= $tsMulai) {
    $_SESSION['checkout_error'] = 'Waktu rencana kembali harus lebih akhir dari waktu mulai sewa.';
    header('Location: /booking/checkout.php');
    exit;
}

// Validasi 3 jaminan identitas
if (count($jenisJaminanArr) < 3 || count($noIdentitasArr) < 3) {
    $_SESSION['checkout_error'] = 'Wajib mengisi 3 data kartu identitas jaminan (1 KTP + 2 ID pendukung).';
    header('Location: /booking/checkout.php');
    exit;
}

// Hitung jumlah hari sewa (pembulatan ke atas per 24 jam, minimal 1 hari)
$selisihDetik = $tsKembali - $tsMulai;
$jumlahHari   = max(1, (int)ceil($selisihDetik / 86400));

$pathBukti = null;

try {
    // 1. Upload bukti pembayaran terlebih dahulu
    $pathBukti = uploadBuktiPembayaran($_FILES['bukti_pembayaran']);

    // 2. Mulai Database Transaction
    $pdo->beginTransaction();

    // 3. Cek apakah penyewa sudah ada berdasarkan no_telepon (UNIQUE)
    $stmtCek = $pdo->prepare("SELECT id_penyewa FROM penyewa WHERE no_telepon = :telp FOR UPDATE");
    $stmtCek->execute([':telp' => $noTelepon]);
    $penyewa = $stmtCek->fetch(PDO::FETCH_ASSOC);

    if ($penyewa) {
        $idPenyewa = (int)$penyewa['id_penyewa'];
        $stmtUpd = $pdo->prepare("UPDATE penyewa SET nama_penyewa = :nama WHERE id_penyewa = :id");
        $stmtUpd->execute([':nama' => $namaPenyewa, ':id' => $idPenyewa]);
    } else {
        $stmtInsPenyewa = $pdo->prepare("INSERT INTO penyewa (nama_penyewa, no_telepon) VALUES (:nama, :telp) RETURNING id_penyewa");
        $stmtInsPenyewa->execute([':nama' => $namaPenyewa, ':telp' => $noTelepon]);
        $idPenyewa = (int)$stmtInsPenyewa->fetchColumn();
    }

    // 4. Insert ke tabel transaksi (nomor_transaksi, total_tagihan, nominal_bayar_awal dikelola trigger DB)
    $sqlTrx = "INSERT INTO transaksi (
                    id_penyewa, waktu_mulai_sewa, waktu_rencana_kembali,
                    jenis_pembayaran, skema_pembayaran, bukti_pembayaran, setuju_syarat
               ) VALUES (
                    :id_penyewa, :mulai, :kembali,
                    :jenis_bayar, :skema_bayar, :bukti, TRUE
               ) RETURNING id_transaksi, nomor_transaksi";
    $stmtTrx = $pdo->prepare($sqlTrx);
    $stmtTrx->execute([
        ':id_penyewa'  => $idPenyewa,
        ':mulai'       => date('Y-m-d H:i:s', $tsMulai),
        ':kembali'     => date('Y-m-d H:i:s', $tsKembali),
        ':jenis_bayar' => $jenisPembayaran,
        ':skema_bayar' => $skemaPembayaran,
        ':bukti'       => $pathBukti
    ]);
    $trxRow         = $stmtTrx->fetch(PDO::FETCH_ASSOC);
    $idTransaksi    = (int)$trxRow['id_transaksi'];
    $nomorTransaksi = $trxRow['nomor_transaksi'];

    // 5. Insert rincian ke detail_transaksi & validasi stok real-time
    $stmtBarang = $pdo->prepare("SELECT harga_sewa_per_hari, stok FROM barang WHERE id_barang = :id FOR UPDATE");
    $stmtDetail = $pdo->prepare("INSERT INTO detail_transaksi (id_transaksi, id_barang, jumlah, harga_satuan, subtotal)
                                 VALUES (:id_trx, :id_brg, :jml, :harga, :subtotal)");

    foreach ($keranjang as $item) {
        $idBrg = (int)$item['id_barang'];
        $jml   = (int)$item['jumlah'];

        $stmtBarang->execute([':id' => $idBrg]);
        $dbBrg = $stmtBarang->fetch(PDO::FETCH_ASSOC);

        if (!$dbBrg || (int)$dbBrg['stok'] < $jml) {
            throw new RuntimeException('Stok untuk "' . $item['nama_barang'] . '" tidak mencukupi.');
        }

        $hargaSatuan = (float)$dbBrg['harga_sewa_per_hari'];
        $subtotal    = $hargaSatuan * $jml * $jumlahHari;

        $stmtDetail->execute([
            ':id_trx'   => $idTransaksi,
            ':id_brg'   => $idBrg,
            ':jml'      => $jml,
            ':harga'    => $hargaSatuan,
            ':subtotal' => $subtotal
        ]);
    }

    // 6. Insert 3 baris jaminan ke tabel jaminan
    $stmtJaminan = $pdo->prepare("INSERT INTO jaminan (id_transaksi, jenis_jaminan, no_identitas)
                                  VALUES (:id_trx, :jenis, :nomor)");
    for ($i = 0; $i < 3; $i++) {
        $jenis = trim($jenisJaminanArr[$i] ?? '');
        $nomor = trim($noIdentitasArr[$i] ?? '');
        if ($jenis === '' || $nomor === '') {
            throw new RuntimeException('Semua nomor kartu jaminan wajib diisi.');
        }
        $stmtJaminan->execute([
            ':id_trx' => $idTransaksi,
            ':jenis'  => $jenis,
            ':nomor'  => $nomor
        ]);
    }

    // 7. Commit transaksi & bersihkan keranjang
    $pdo->commit();
    unset($_SESSION['keranjang']);

    header('Location: /booking/sukses.php?no=' . urlencode($nomorTransaksi));
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Hapus file terupload jika DB rollback
    if ($pathBukti && file_exists(__DIR__ . '/../' . $pathBukti)) {
        @unlink(__DIR__ . '/../' . $pathBukti);
    }

    $_SESSION['checkout_error'] = 'Gagal memproses pesanan: ' . $e->getMessage();
    header('Location: /booking/checkout.php');
    exit;
}