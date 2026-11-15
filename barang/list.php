<?php
declare(strict_types=1);
require_once __DIR__ . '/data_barang.php';
$judul_halaman = 'Inventaris Barang';
require_once __DIR__ . '/../includes/header_admin.php';
?>

<!-- ══════════════════════════════════════════════════════════════════════════
     HEADER HALAMAN + TOMBOL TAMBAH
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-[#111111] tracking-tight">Inventaris Barang</h1>
        <p class="text-xs text-gray-500 mt-1">
            Menampilkan <strong><?= e((string) count($daftarBarang)) ?></strong> dari total
            <strong><?= e((string) $totalBaris) ?></strong> unit peralatan kamera
        </p>
    </div>

    <a href="<?= e(base_url('barang/tambah.php')) ?>" class="btn-utama">
        <span>+ Tambah Barang</span>
    </a>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     FILTER & PENCARIAN
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="card-admin mb-6">
    <form action="<?= e(base_url('barang/list.php')) ?>" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">

        <!-- Pencarian Nama -->
        <div class="lg:col-span-5">
            <label for="q" class="form-label">Cari Nama Barang</label>
            <input type="text"
                   id="q"
                   name="q"
                   value="<?= e($cari) ?>"
                   placeholder="Contoh: Sony A7, Canon, Godox..."
                   class="form-input">
        </div>

        <!-- Filter Kategori -->
        <div class="lg:col-span-3">
            <label for="kategori" class="form-label">Kategori</label>
            <select id="kategori" name="kategori" class="form-input">
                <option value="">Semua Kategori</option>
                <?php foreach ($daftarKategori as $kat): ?>
                    <option value="<?= e((string) $kat['id_kategori']) ?>"
                            <?= $idKategori === (int) $kat['id_kategori'] ? 'selected' : '' ?>>
                        <?= e($kat['nama_kategori']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filter Status -->
        <div class="lg:col-span-2">
            <label for="status" class="form-label">Status Barang</label>
            <select id="status" name="status" class="form-input">
                <option value="">Semua Status</option>
                <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="rusak" <?= $status === 'rusak' ? 'selected' : '' ?>>Rusak</option>
                <option value="maintenance" <?= $status === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </div>

        <!-- Tombol Filter & Reset -->
        <div class="lg:col-span-2 flex items-center gap-2">
            <button type="submit" class="btn-utama flex-1 justify-center py-2.5">
                Filter
            </button>
            <?php if ($cari !== '' || $idKategori !== null || $status !== ''): ?>
                <a href="<?= e(base_url('barang/list.php')) ?>"
                   class="btn-sekunder py-2 px-3"
                   title="Reset Filter">
                    Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     TABEL PADAT INVENTARIS BARANG
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="card-admin">
    <div class="tabel-wrapper">
        <table class="tabel-admin">
            <thead>
                <tr>
                    <th class="w-12">No</th>
                    <th class="w-16">Foto</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th>Harga / Hari</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarBarang)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-gray-400 py-10">
                            Tidak ada data barang yang sesuai dengan filter pencarian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarBarang as $idx => $b):
                        $nomor     = $offset + $idx + 1;
                        $idBarang  = (int) $b['id_barang'];
                        $foto      = trim((string) ($b['foto_barang'] ?? ''));
                        $adaFoto   = $foto !== '' && file_exists(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $foto));

                        $badgeKelas = match ($b['status_barang']) {
                            'aktif'       => 'badge-aktif',
                            'rusak'       => 'badge-rusak',
                            'maintenance' => 'badge-maintenance',
                            default       => 'badge-nonaktif',
                        };
                        $labelStatus = ucfirst((string) $b['status_barang']);
                    ?>
                    <tr>
                        <!-- No -->
                        <td class="text-xs text-gray-500 font-mono">
                            <?= e((string) $nomor) ?>
                        </td>

                        <!-- Foto / Ikon Kamera -->
                        <td>
                            <?php if ($adaFoto): ?>
                                <img src="<?= e(base_url($foto)) ?>"
                                     alt="<?= e($b['nama_barang']) ?>"
                                     class="w-11 h-11 rounded-xl object-cover border border-gray-200 bg-gray-50">
                            <?php else: ?>
                                <div class="w-11 h-11 rounded-xl bg-gray-100 border border-gray-200 text-gray-500 flex items-center justify-center"
                                     title="Belum ada foto">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 9a3.75 3.75 0 100 7.5A3.75 3.75 0 0012 9z"/>
                                        <path fill-rule="evenodd" d="M9.344 3.071a49.52 49.52 0 015.312 0c.967.052 1.83.585 2.332 1.39l.821 1.317c.24.383.645.643 1.11.71.386.054.77.113 1.152.177 1.432.239 2.429 1.493 2.429 2.909V18a3 3 0 01-3 3h-15a3 3 0 01-3-3V9.574c0-1.416.997-2.67 2.429-2.909.382-.064.766-.123 1.151-.178a1.56 1.56 0 001.11-.71l.822-1.315a2.942 2.942 0 012.332-1.39zM6.75 12.75a5.25 5.25 0 1110.5 0 5.25 5.25 0 01-10.5 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Nama Barang + Kondisi Fisik -->
                        <td>
                            <div class="font-semibold text-gray-900">
                                <?= e($b['nama_barang']) ?>
                            </div>
                            <div class="text-[11px] text-gray-400 font-mono">
                                ID #<?= e((string) $idBarang) ?>
                                · Stok: <?= e((string) ($b['stok'] ?? 1)) ?> unit
                                <?php if (!empty($b['kondisi_fisik'])): ?>
                                    · <span class="font-sans text-gray-500"><?= e($b['kondisi_fisik']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Kategori -->
                        <td class="text-xs text-gray-700 whitespace-nowrap">
                            <?= e($b['nama_kategori']) ?>
                        </td>

                        <!-- Harga / Hari -->
                        <td class="text-xs font-bold text-[#111111] whitespace-nowrap">
                            <?= e(format_rupiah($b['harga_sewa_per_hari'])) ?>
                        </td>

                        <!-- Status -->
                        <td>
                            <span class="badge <?= $badgeKelas ?>">
                                <?= e($labelStatus) ?>
                            </span>
                        </td>

                        <!-- Aksi (Edit & Hapus via POST + CSRF) -->
                        <td class="text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                <a href="<?= e(base_url('barang/edit.php?id=' . $idBarang)) ?>"
                                   class="btn-sekunder text-xs py-1 px-3">
                                    Edit
                                </a>

                                <form action="<?= e(base_url('barang/hapus.php')) ?>"
                                      method="POST"
                                      class="inline"
                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus / menonaktifkan barang &quot;<?= e(addslashes($b['nama_barang'])) ?>&quot;?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_barang" value="<?= e((string) $idBarang) ?>">
                                    <button type="submit" class="btn-bahaya text-xs py-1 px-3">
                                        Hapus
                                    </button>
                                </form>
                            </div>
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
                (Total <?= e((string) $totalBaris) ?> unit)
            </div>

            <div class="flex flex-wrap items-center gap-1">
                <?php if ($halaman > 1): ?>
                    <a href="<?= e(_url_halaman_barang($halaman - 1, $cari, $idKategori, $status)) ?>"
                       class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 hover:bg-[#111111] hover:text-white hover:border-[#111111] transition-colors">
                        &larr; Sebelumnya
                    </a>
                <?php endif; ?>

                <?php
                $awal  = max(1, $halaman - 2);
                $akhir = min($totalHalaman, $halaman + 2);
                for ($p = $awal; $p <= $akhir; $p++):
                ?>
                    <a href="<?= e(_url_halaman_barang($p, $cari, $idKategori, $status)) ?>"
                       class="w-8 h-8 rounded-full flex items-center justify-center font-semibold transition-colors
                              <?= $p === $halaman ? 'bg-[#111111] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                        <?= e((string) $p) ?>
                    </a>
                <?php endfor; ?>

                <?php if ($halaman < $totalHalaman): ?>
                    <a href="<?= e(_url_halaman_barang($halaman + 1, $cari, $idKategori, $status)) ?>"
                       class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 hover:bg-[#111111] hover:text-white hover:border-[#111111] transition-colors">
                        Berikutnya &rarr;
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer_admin.php'; ?>
