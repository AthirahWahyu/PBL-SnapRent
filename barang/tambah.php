<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/koneksi.php';

wajib_login();

try {
    $daftarKategori = $pdo->query('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori ASC')->fetchAll();
} catch (PDOException $e) {
    error_log('[SnapRent] Error barang/tambah.php: ' . $e->getMessage());
    $daftarKategori = [];
    flash_set('error', 'Gagal memuat daftar kategori.');
}

// Ambil input lama bila gagal validasi sebelumnya
$old = $_SESSION['_old_barang'] ?? [];
unset($_SESSION['_old_barang']);

$judul_halaman = 'Tambah Barang';
require_once __DIR__ . '/../includes/header_admin.php';
?>

<!-- ══════════════════════════════════════════════════════════════════════════
     HEADER HALAMAN
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-[#111111] tracking-tight">Tambah Unit Barang</h1>
        <p class="text-xs text-gray-500 mt-1">
            Tambahkan unit peralatan kamera baru ke dalam inventaris SnapRent
        </p>
    </div>

    <a href="<?= e(base_url('barang/list.php')) ?>" class="btn-sekunder">
        &larr; Kembali ke Daftar
    </a>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     FORM TAMBAH BARANG
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="card-admin max-w-3xl">
    <form action="<?= e(base_url('barang/proses_tambah.php')) ?>"
          method="POST"
          enctype="multipart/form-data"
          class="space-y-5">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Kategori -->
            <div>
                <label for="id_kategori" class="form-label">Kategori <span class="text-red-500">*</span></label>
                <select id="id_kategori" name="id_kategori" class="form-input" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($daftarKategori as $kat): ?>
                        <option value="<?= e((string) $kat['id_kategori']) ?>"
                                <?= (isset($old['id_kategori']) && (int) $old['id_kategori'] === (int) $kat['id_kategori']) ? 'selected' : '' ?>>
                            <?= e($kat['nama_kategori']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Barang -->
            <div>
                <label for="status_barang" class="form-label">Status Operasional <span class="text-red-500">*</span></label>
                <?php $statusTerpilih = $old['status_barang'] ?? 'aktif'; ?>
                <select id="status_barang" name="status_barang" class="form-input" required>
                    <option value="aktif" <?= $statusTerpilih === 'aktif' ? 'selected' : '' ?>>Aktif (Siap Sewa)</option>
                    <option value="maintenance" <?= $statusTerpilih === 'maintenance' ? 'selected' : '' ?>>Maintenance (Perawatan)</option>
                    <option value="rusak" <?= $statusTerpilih === 'rusak' ? 'selected' : '' ?>>Rusak</option>
                    <option value="nonaktif" <?= $statusTerpilih === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <!-- Nama Barang -->
            <div>
                <label for="nama_barang" class="form-label">Nama & Model Barang <span class="text-red-500">*</span></label>
                <input type="text"
                       id="nama_barang"
                       name="nama_barang"
                       maxlength="100"
                       value="<?= e($old['nama_barang'] ?? '') ?>"
                       placeholder="Contoh: SONY A7 Mk IV (Body Only)"
                       class="form-input"
                       required>
            </div>

            <!-- Harga Sewa per Hari -->
            <div>
                <label for="harga_sewa_per_hari" class="form-label">Harga Sewa per Hari (Rp) <span class="text-red-500">*</span></label>
                <input type="number"
                       id="harga_sewa_per_hari"
                       name="harga_sewa_per_hari"
                       min="0"
                       step="1"
                       value="<?= e($old['harga_sewa_per_hari'] ?? '') ?>"
                       placeholder="Contoh: 350000"
                       class="form-input"
                       required>
            </div>

            <!-- Stok Unit Fisik -->
            <div>
                <label for="stok" class="form-label">Stok Unit <span class="text-red-500">*</span></label>
                <input type="number"
                       id="stok"
                       name="stok"
                       min="0"
                       step="1"
                       value="<?= e((string) ($old['stok'] ?? 1)) ?>"
                       class="form-input"
                       required>
            </div>
        </div>

        <!-- Kondisi Fisik / Kelengkapan -->
        <div>
            <label for="kondisi_fisik" class="form-label">Kondisi Fisik / Kelengkapan Paket (Opsional)</label>
            <textarea id="kondisi_fisik"
                      name="kondisi_fisik"
                      rows="3"
                      maxlength="255"
                      placeholder="Contoh: Kondisi mulus, termasuk baterai, charger, strap, dan tas kamera"
                      class="form-input"><?= e($old['kondisi_fisik'] ?? '') ?></textarea>
            <p class="text-[11px] text-gray-400 mt-1">Maksimal 255 karakter.</p>
        </div>

        <!-- Unggah Foto + Pratinjau -->
        <div>
            <label for="foto_barang" class="form-label">Foto Barang (Opsional — JPG/PNG, maks. 2 MB)</label>
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 mt-1">
                <div id="wadahPreview"
                     class="w-24 h-24 rounded-2xl bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center overflow-hidden flex-shrink-0">
                    <img id="imgPreview" src="" alt="Pratinjau Foto" class="w-full h-full object-cover hidden">
                    <span id="ikonPlaceholder" class="text-gray-400 text-xs text-center px-2">Belum ada foto</span>
                </div>

                <div class="flex-1 w-full">
                    <input type="file"
                           id="foto_barang"
                           name="foto_barang"
                           accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                           onchange="pratinjauFoto(this)"
                           class="block w-full text-xs text-gray-600
                                  file:mr-3 file:py-2 file:px-4
                                  file:rounded-full file:border-0
                                  file:text-xs file:font-semibold
                                  file:bg-[#111111] file:text-white
                                  hover:file:bg-gray-800 cursor-pointer">
                    <p class="text-[11px] text-gray-400 mt-1.5">
                        Format yang didukung: <strong>.jpg</strong>, <strong>.png</strong> (Ukuran maksimal 2 MB).
                    </p>
                </div>
            </div>
        </div>

        <!-- Tombol Simpan -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="<?= e(base_url('barang/list.php')) ?>" class="btn-sekunder py-2 px-5">
                Batal
            </a>
            <button type="submit" class="btn-utama py-2.5 px-6">
                Simpan Barang
            </button>
        </div>
    </form>
</div>

<script>
function pratinjauFoto(input) {
    const img  = document.getElementById('imgPreview');
    const ikon = document.getElementById('ikonPlaceholder');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;
            img.classList.remove('hidden');
            if (ikon) ikon.classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        img.src = '';
        img.classList.add('hidden');
        if (ikon) ikon.classList.remove('hidden');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer_admin.php'; ?>
