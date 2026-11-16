<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';

$keranjang = $_SESSION['keranjang'] ?? [];
if (empty($keranjang)) {
    header('Location: /index.php');
    exit;
}

$subtotalHarian = 0;
foreach ($keranjang as $item) {
    $subtotalHarian += $item['harga_sewa_per_hari'] * $item['jumlah'];
}

$errorMsg = $_SESSION['checkout_error'] ?? null;
unset($_SESSION['checkout_error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Penyewaan - SnapRent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <a href="/index.php" class="btn btn-outline-secondary btn-sm mb-3">&larr; Tambah Kamera Lain</a>
    <h3 class="fw-bold mb-4">Formulir Checkout & Jaminan Sewa</h3>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <form id="formCheckout" action="/booking/proses_checkout.php" method="POST" enctype="multipart/form-data">
        <div class="row g-4">
            <!-- Kolom Kiri: Form Data -->
            <div class="col-lg-7">
                <!-- 1. Biodata Penyewa -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">1. Informasi Penyewa</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nama Lengkap *</label>
                                <input type="text" name="nama_penyewa" class="form-control" required placeholder="Contoh: Silvia Febrianti">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nomor WhatsApp / HP *</label>
                                <input type="tel" name="no_telepon" class="form-control" required placeholder="08xxxxxxxxxx" maxlength="15">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Jadwal Sewa -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">2. Jadwal Pengambilan & Pengembalian</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tanggal & Jam Mulai Sewa *</label>
                                <input type="datetime-local" id="waktu_mulai_sewa" name="waktu_mulai_sewa" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tanggal & Jam Rencana Kembali *</label>
                                <input type="datetime-local" id="waktu_rencana_kembali" name="waktu_rencana_kembali" class="form-control" required>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            *Durasi sewa dihitung per 24 jam (pembulatan ke atas per hari). Keterlambatan pengembalian dikenakan denda progresif.
                        </small>
                    </div>
                </div>

                <!-- 3. Syarat Jaminan 3 Identitas -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-1">3. Data Jaminan (Wajib 3 Identitas)</h5>
                        <p class="text-muted small mb-3">Sesuai SOP rental, wajib menyerahkan 1 KTP asli dan 2 kartu identitas pendukung saat pengambilan.</p>

                        <!-- Jaminan 1: KTP Utama -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Jaminan Utama *</label>
                                <input type="text" class="form-control bg-light" value="KTP (Asli)" readonly>
                                <input type="hidden" name="jenis_jaminan[]" value="KTP">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold">Nomor NIK KTP *</label>
                                <input type="text" name="no_identitas[]" class="form-control" required maxlength="30" placeholder="16 digit NIK KTP">
                            </div>
                        </div>

                        <!-- Jaminan 2: Identitas Pendukung 1 -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Identitas Pendukung 1 *</label>
                                <select name="jenis_jaminan[]" class="form-select" required>
                                    <option value="SIM">SIM</option>
                                    <option value="KTM">KTM (Kartu Tanda Mahasiswa)</option>
                                    <option value="STNK">STNK</option>
                                    <option value="Paspor">Paspor</option>
                                    <option value="Kartu Pelajar">Kartu Pelajar</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold">Nomor Kartu Pendukung 1 *</label>
                                <input type="text" name="no_identitas[]" class="form-control" required maxlength="30" placeholder="Nomor SIM / NIM KTM / Plat STNK">
                            </div>
                        </div>

                        <!-- Jaminan 3: Identitas Pendukung 2 -->
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Identitas Pendukung 2 *</label>
                                <select name="jenis_jaminan[]" class="form-select" required>
                                    <option value="KTM">KTM (Kartu Tanda Mahasiswa)</option>
                                    <option value="SIM">SIM</option>
                                    <option value="STNK">STNK</option>
                                    <option value="Paspor">Paspor</option>
                                    <option value="Kartu Pelajar">Kartu Pelajar</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold">Nomor Kartu Pendukung 2 *</label>
                                <input type="text" name="no_identitas[]" class="form-control" required maxlength="30" placeholder="Nomor identitas pendukung ke-2">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Metode Pembayaran & Upload Bukti -->
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">4. Pembayaran & Bukti Transfer</h5>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Metode Pembayaran *</label>
                                <select name="jenis_pembayaran" class="form-select" required>
                                    <option value="qris">QRIS (All Payment)</option>
                                    <option value="transfer">Transfer Bank (BCA / Mandiri)</option>
                                    <option value="tunai">Tunai di Toko (Lampirkan Bukti Booking)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Skema Pembayaran *</label>
                                <select id="skema_pembayaran" name="skema_pembayaran" class="form-select" required>
                                    <option value="lunas">Bayar Lunas (100%)</option>
                                    <option value="dp_50">DP 50% (Pelunasan saat ambil)</option>
                                </select>
                            </div>
                        </div>

                        <div class="alert alert-info small">
                            <strong>Rekening Tujuan SnapRent Malang:</strong><br>
                            BCA 1234567890 a.n. SnapRent Kamera Malang / QRIS Resmi Toko.
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Unggah Bukti Pembayaran (JPG/PNG, Maks. 2MB) *</label>
                            <input type="file" id="bukti_pembayaran" name="bukti_pembayaran" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                        </div>

                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="setuju_syarat" name="setuju_syarat" value="1" required>
                            <label class="form-check-label small" for="setuju_syarat">
                                Saya menyetujui syarat & ketentuan penyewaan SnapRent, termasuk tanggung jawab kerusakan unit dan aturan denda keterlambatan.
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Ringkasan Biaya -->
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Ringkasan Pesanan</h5>
                        <ul class="list-group list-group-flush mb-3">
                            <?php foreach ($keranjang as $item): ?>
                                <li class="list-group-item px-0 d-flex justify-content-between">
                                    <div>
                                        <span class="fw-semibold"><?= htmlspecialchars($item['nama_barang']) ?></span>
                                        <small class="text-muted d-block">
                                            <?= (int)$item['jumlah'] ?> unit &times; Rp <?= number_format($item['harga_sewa_per_hari'], 0, ',', '.') ?>/hari
                                        </small>
                                    </div>
                                    <span class="fw-semibold">
                                        Rp <?= number_format($item['harga_sewa_per_hari'] * $item['jumlah'], 0, ',', '.') ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <input type="hidden" id="harga_per_hari_total" value="<?= $subtotalHarian ?>">

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Tarif Sewa / Hari</span>
                            <span class="fw-semibold">Rp <?= number_format($subtotalHarian, 0, ',', '.') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Durasi Sewa</span>
                            <span id="preview_durasi" class="fw-bold text-primary">1 Hari</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-bold">Total Tagihan Sewa</span>
                            <span id="preview_total" class="fw-bold fs-5">Rp <?= number_format($subtotalHarian, 0, ',', '.') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-4 text-success">
                            <span class="fw-semibold">Nominal Bayar Awal</span>
                            <span id="preview_bayar_awal" class="fw-bold fs-5">Rp <?= number_format($subtotalHarian, 0, ',', '.') ?></span>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
                            Proses Pemesanan Sekarang &rarr;
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="/assets/js/checkout.js"></script>
</body>
</html>