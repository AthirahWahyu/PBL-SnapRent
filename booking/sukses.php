<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';

$nomorTransaksi = trim($_GET['no'] ?? '');
if ($nomorTransaksi === '') {
    header('Location: /index.php');
    exit;
}

$sql = "SELECT t.*, p.nama_penyewa, p.no_telepon
        FROM transaksi t
        JOIN penyewa p ON t.id_penyewa = p.id_penyewa
        WHERE t.nomor_transaksi = :no";
$stmt = $pdo->prepare($sql);
$stmt->execute([':no' => $nomorTransaksi]);
$trx = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trx) {
    die("Nomor transaksi tidak ditemukan.");
}

// Ambil detail barang
$stmtDet = $pdo->prepare("SELECT dt.*, b.nama_barang 
                          FROM detail_transaksi dt 
                          JOIN barang b ON dt.id_barang = b.id_barang 
                          WHERE dt.id_transaksi = :id");
$stmtDet->execute([':id' => $trx['id_transaksi']]);
$details = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

// Ambil daftar jaminan
$stmtJam = $pdo->prepare("SELECT jenis_jaminan, no_identitas FROM jaminan WHERE id_transaksi = :id ORDER BY id_jaminan ASC");
$stmtJam->execute([':id' => $trx['id_transaksi']]);
$jaminans = $stmtJam->fetchAll(PDO::FETCH_ASSOC);

// Nomor WA Admin (sesuai proposal)
$waAdmin = '6281234567890';
$pesanWa = "Halo Admin SnapRent, saya sudah melakukan booking kamera dengan Nomor Transaksi *{$trx['nomor_transaksi']}* atas nama *{$trx['nama_penyewa']}*. Mohon verifikasi pembayaran saya. Terima kasih!";
$linkWa  = "https://wa.me/{$waAdmin}?text=" . urlencode($pesanWa);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Berhasil #<?= htmlspecialchars($trx['nomor_transaksi']) ?> - SnapRent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill mb-2">
                            Pemesanan Berhasil Tercatat
                        </span>
                        <h3 class="fw-bold mb-1">Invoice Pemesanan SnapRent</h3>
                        <p class="text-muted mb-0">Nomor Resi / Transaksi:</p>
                        <h2 class="fw-bold text-primary tracking-wide mt-1"><?= htmlspecialchars($trx['nomor_transaksi']) ?></h2>
                    </div>

                    <div class="row g-3 bg-light rounded p-3 mb-4 small">
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Nama Penyewa:</span>
                            <strong><?= htmlspecialchars($trx['nama_penyewa']) ?></strong> (<?= htmlspecialchars($trx['no_telepon']) ?>)
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Status Pesanan:</span>
                            <span class="badge bg-warning text-dark"><?= strtoupper(str_replace('_', ' ', $trx['status_transaksi'])) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Jadwal Ambil:</span>
                            <strong><?= date('d M Y, H:i', strtotime($trx['waktu_mulai_sewa'])) ?> WIB</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Rencana Kembali:</span>
                            <strong><?= date('d M Y, H:i', strtotime($trx['waktu_rencana_kembali'])) ?> WIB</strong>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2">Rincian Unit Disewa</h6>
                    <table class="table table-bordered table-sm mb-4">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Alat</th>
                                <th class="text-center">Jumlah</th>
                                <th class="text-end">Tarif/Hari</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($details as $d): ?>
                                <tr>
                                    <td><?= htmlspecialchars($d['nama_barang']) ?></td>
                                    <td class="text-center"><?= (int)$d['jumlah'] ?></td>
                                    <td class="text-end">Rp <?= number_format($d['harga_satuan'], 0, ',', '.') ?></td>
                                    <td class="text-end">Rp <?= number_format($d['subtotal'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total Tagihan Sewa</th>
                                <th class="text-end">Rp <?= number_format($trx['total_tagihan'], 0, ',', '.') ?></th>
                            </tr>
                            <tr class="table-success">
                                <th colspan="3" class="text-end">
                                    Nominal Bayar Awal (<?= $trx['skema_pembayaran'] === 'dp_50' ? 'DP 50%' : 'Lunas 100%' ?>)
                                </th>
                                <th class="text-end">Rp <?= number_format($trx['nominal_bayar_awal'], 0, ',', '.') ?></th>
                            </tr>
                        </tfoot>
                    </table>

                    <h6 class="fw-bold mb-2">Dokumen Jaminan yang Wajib Dibawa saat Pengambilan</h6>
                    <ul class="list-group list-group-flush mb-4 small border rounded">
                        <?php foreach ($jaminans as $j): ?>
                            <li class="list-group-item d-flex justify-content-between">
                                <span><?= htmlspecialchars($j['jenis_jaminan']) ?></span>
                                <strong><?= htmlspecialchars($j['no_identitas']) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                        <a href="<?= htmlspecialchars($linkWa) ?>" target="_blank" class="btn btn-success fw-semibold px-4">
                            Konfirmasi via WhatsApp Admin
                        </a>
                        <a href="/index.php" class="btn btn-outline-secondary px-4">
                            Kembali ke Katalog
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>