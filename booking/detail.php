<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';

$idBarang = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idBarang) {
    header('Location: /index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT b.*, k.nama_kategori, k.syarat_jaminan 
                       FROM barang b 
                       JOIN kategori k ON b.id_kategori = k.id_kategori 
                       WHERE b.id_barang = :id");
$stmt->execute([':id' => $idBarang]);
$barang = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$barang) {
    die("Data kamera tidak ditemukan.");
}

$foto = !empty($barang['foto_barang']) ? '/' . ltrim($barang['foto_barang'], '/') : 'https://placehold.co/600x400?text=SnapRent+Camera';
$tersedia = ($barang['status_barang'] === 'aktif' && (int)$barang['stok'] > 0);

if (file_exists(__DIR__ . '/../includes/header.php')) {
    require_once __DIR__ . '/../includes/header.php';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail <?= htmlspecialchars($barang['nama_barang']) ?> - SnapRent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <a href="/index.php" class="btn btn-outline-secondary btn-sm mb-4">&larr; Kembali ke Katalog</a>

    <div class="card shadow-sm border-0">
        <div class="row g-0">
            <div class="col-md-5 bg-white d-flex align-items-center justify-content-center p-4 border-end">
                <img src="<?= htmlspecialchars($foto) ?>" alt="<?= htmlspecialchars($barang['nama_barang']) ?>"
                     class="img-fluid rounded" style="max-height: 360px; object-fit: contain;">
            </div>
            <div class="col-md-7 p-4 p-md-5">
                <span class="badge bg-primary-subtle text-primary mb-2"><?= htmlspecialchars($barang['nama_kategori']) ?></span>
                <h2 class="fw-bold mb-2"><?= htmlspecialchars($barang['nama_barang']) ?></h2>
                <h4 class="text-primary fw-bold mb-3">
                    Rp <?= number_format($barang['harga_sewa_per_hari'], 0, ',', '.') ?> <small class="text-muted fs-6 fw-normal">/ hari</small>
                </h4>

                <div class="mb-3">
                    <span class="badge <?= $tersedia ? 'bg-success' : 'bg-danger' ?>">
                        <?= $tersedia ? 'Tersedia (Stok: ' . (int)$barang['stok'] . ' unit)' : 'Tidak Tersedia' ?>
                    </span>
                </div>

                <h6 class="fw-bold mt-4">Kondisi Fisik & Kelengkapan Unit</h6>
                <p class="text-muted">
                    <?= !empty($barang['kondisi_fisik']) ? nl2br(htmlspecialchars($barang['kondisi_fisik'])) : 'Unit terawat, siap pakai lengkap dengan baterai, charger, dan tas kamera.' ?>
                </p>

                <div class="alert alert-warning small mt-3">
                    <strong>Syarat Jaminan Wajib:</strong><br>
                    Wajib meninggalkan <strong>1 KTP Asli + 2 Kartu Identitas Pendukung</strong> (SIM / KTM / STNK / Paspor / Kartu Pelajar) saat pengambilan unit.
                    <?php if (!empty($barang['syarat_jaminan'])): ?>
                        <br><em>Catatan kategori: <?= htmlspecialchars($barang['syarat_jaminan']) ?></em>
                    <?php endif; ?>
                </div>

                <?php if ($tersedia): ?>
                    <div class="row g-2 align-items-center mt-4">
                        <div class="col-auto">
                            <label for="qtyBarang" class="col-form-label fw-semibold">Jumlah Unit:</label>
                        </div>
                        <div class="col-3 col-sm-2">
                            <input type="number" id="qtyBarang" class="form-control" value="1" min="1" max="<?= (int)$barang['stok'] ?>">
                        </div>
                        <div class="col-12 col-sm-auto d-flex gap-2 mt-2 mt-sm-0">
                            <button type="button" class="btn btn-outline-primary fw-semibold"
                                    onclick="tambahKeKeranjang(<?= (int)$barang['id_barang'] ?>, document.getElementById('qtyBarang').value)">
                                + Tambah ke Keranjang
                            </button>
                            <button type="button" class="btn btn-primary fw-semibold"
                                    onclick="pesanSekarang(<?= (int)$barang['id_barang'] ?>, document.getElementById('qtyBarang').value)">
                                Pesan Sekarang
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/keranjang.js"></script>
</body>
</html>
<?php
if (file_exists(__DIR__ . '/../includes/footer.php')) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>