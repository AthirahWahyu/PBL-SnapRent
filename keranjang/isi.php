<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$keranjang = $_SESSION['keranjang'] ?? [];
$subtotalHarian = 0;
foreach ($keranjang as $item) {
    $subtotalHarian += $item['harga_sewa_per_hari'] * $item['jumlah'];
}
?>
<div id="miniCartContent" class="p-4">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
        <h5 class="mb-0 fw-bold">Keranjang Sewa</h5>
        <?php if (!empty($keranjang)): ?>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="kosongkanKeranjang()">
                Kosongkan
            </button>
        <?php endif; ?>
    </div>

    <?php if (empty($keranjang)): ?>
        <div class="text-center py-5 text-muted">
            <p class="mb-2">Keranjang sewa Anda masih kosong.</p>
            <a href="/index.php" class="btn btn-sm btn-primary">Lihat Katalog Kamera</a>
        </div>
    <?php else: ?>
        <div class="list-group list-group-flush mb-3" style="max-height: 360px; overflow-y: auto;">
            <?php foreach ($keranjang as $item): 
                $foto = !empty($item['foto_barang']) ? '/' . ltrim($item['foto_barang'], '/') : 'https://placehold.co/80x80?text=Cam';
                $subItem = $item['harga_sewa_per_hari'] * $item['jumlah'];
            ?>
                <div class="list-group-item px-0 py-3 d-flex align-items-center gap-3">
                    <img src="<?= htmlspecialchars($foto) ?>" alt="<?= htmlspecialchars($item['nama_barang']) ?>"
                         class="rounded border" style="width: 60px; height: 60px; object-fit: cover;">
                    <div class="flex-grow-1">
                        <h6 class="mb-1 fw-semibold"><?= htmlspecialchars($item['nama_barang']) ?></h6>
                        <small class="text-muted d-block">
                            Rp <?= number_format($item['harga_sewa_per_hari'], 0, ',', '.') ?> / hari
                        </small>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-0"
                                    onclick="ubahJumlahKeranjang(<?= $item['id_barang'] ?>, 'kurangi')">-</button>
                            <span class="fw-bold small"><?= (int)$item['jumlah'] ?></span>
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-0"
                                    onclick="ubahJumlahKeranjang(<?= $item['id_barang'] ?>, 'tambah')">+</button>
                            <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none ms-auto p-0"
                                    onclick="hapusItemKeranjang(<?= $item['id_barang'] ?>)">Hapus</button>
                        </div>
                    </div>
                    <div class="text-end fw-bold small">
                        Rp <?= number_format($subItem, 0, ',', '.') ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="border-top pt-3">
            <div class="d-flex justify-content-between mb-3">
                <span class="text-muted">Estimasi Sewa / Hari</span>
                <span class="fw-bold fs-6">Rp <?= number_format($subtotalHarian, 0, ',', '.') ?></span>
            </div>
            <a href="/booking/checkout.php" class="btn btn-primary w-100 fw-semibold">
                Lanjut ke Checkout &rarr;
            </a>
        </div>
    <?php endif; ?>
</div>