<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/koneksi.php';

wajib_login();

$idBarang = ambil_int('id', 'get');
if ($idBarang === null) {
    flash_set('error', 'ID barang tidak valid.');
    redirect('barang/list.php');
}

try {
    $stmt = $pdo->prepare('SELECT * FROM barang WHERE id_barang = :id LIMIT 1');
    $stmt->execute([':id' => $idBarang]);
    $barang = $stmt->fetch();

    if (!$barang) {
        flash_set('error', 'Data barang tidak ditemukan.');
        redirect('barang/list.php');
    }

    $daftarKategori = $pdo->query('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori ASC')->fetchAll();
} catch (PDOException $e) {
    error_log('[SnapRent] Error barang/edit.php: ' . $e->getMessage());
    flash_set('error', 'Gagal memuat data barang.');
    redirect('barang/list.php');
}

$fotoSaatIni = trim((string) ($barang['foto_barang'] ?? ''));
$adaFoto     = $fotoSaatIni !== '' && file_exists(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fotoSaatIni));

$judul_halaman = 'Edit Barang';
require_once __DIR__ . '/../includes/header_admin.php';
?>

<!-- ══════════════════════════════════════════════════════════════════════════
     HEADER HALAMAN
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-[#111111] tracking-tight">Edit Unit Barang #<?= e((string) $idBarang) ?></h1>
        <p class="text-xs text-gray-500 mt-1">
            Perbarui informasi, harga sewa, status operasional, atau foto barang
        </p>
    </div>

    <a href="<?= e(base_url('barang/list.php')) ?>" class="btn-sekunder">
        &larr; Kembali ke Daftar
    </a>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     FORM EDIT BARANG
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="card-admin max-w-3xl">
    <form action="<?= e(base_url('barang/proses_edit.php')) ?>"
          method="POST"
          enctype="multipart/form-data"
          class="space-y-5">
        <?= csrf_field() ?>
        <input type="hidden" name="id_barang" value="<?= e((string) $idBarang) ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Kategori -->
            <div>
                <label for="id_kategori" class="form-label">Kategori <span class="text-red-500">*</span></label>
                <select id="id_kategori" name="id_kategori" class="form-input" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($daftarKategori as $kat): ?>
                        <option value="<?= e((string) $kat['id_kategori']) ?>"
                                <?= (int) $barang['id_kategori'] === (int) $kat['id_kategori'] ? 'selected' : '' ?>>
                            <?= e($kat['nama_kategori']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Barang -->
            <div>
                <label for="status_barang" class="form-label">Status Operasional <span class="text-red-500">*</span></label>
                <?php $st = (string) $barang['status_barang']; ?>
                <select id="status_barang" name="status_barang" class="form-input" required>
                    <option value="aktif" <?= $st === 'aktif' ? 'selected' : '' ?>>Aktif (Siap Sewa)</option>
                    <option value="maintenance" <?= $st === 'maintenance' ? 'selected' : '' ?>>Maintenance (Perawatan)</option>
                    <option value="rusak" <?= $st === 'rusak' ? 'selected' : '' ?>>Rusak</option>
                    <option value="nonaktif" <?= $st === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
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
                       value="<?= e($barang['nama_barang']) ?>"
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
                       value="<?= e((string) (float) $barang['harga_sewa_per_hari']) ?>"
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
                       value="<?= e((string) ($barang['stok'] ?? 1)) ?>"
                       class="form-input"
                       required>
            </div>
        </div>

        <!-- Kondisi Fisik -->
        <div>
            <label for="kondisi_fisik" class="form-label">Kondisi Fisik / Kelengkapan Paket (Opsional)</label>
            <textarea id="kondisi_fisik"
                      name="kondisi_fisik"
                      rows="3"
                      maxlength="255"
                      placeholder="Contoh: Kondisi mulus, termasuk baterai, charger, strap, dan tas kamera"
                      class="form-input"><?= e($barang['kondisi_fisik'] ?? '') ?></textarea>
            <p class="text-[11px] text-gray-400 mt-1">Maksimal 255 karakter.</p>
        </div>

        <!-- Foto Saat Ini & Unggah Foto Baru -->
        <div>
            <label for="foto_barang" class="form-label">Foto Barang (JPG/PNG, maks. 2 MB)</label>
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 mt-1">
                <div class="w-24 h-24 rounded-2xl bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center overflow-hidden flex-shrink-0">
                    <img id="imgPreview"
                         src="<?= $adaFoto ? e(base_url($fotoSaatIni)) : '' ?>"
                         alt="Pratinjau Foto"
                         class="w-full h-full object-cover <?= $adaFoto ? '' : 'hidden' ?>">
                    <span id="ikonPlaceholder"
                          class="text-gray-400 text-xs text-center px-2 <?= $adaFoto ? 'hidden' : '' ?>">
                        Belum ada foto
                    </span>
                </div>

                <div class="flex-1 w-full">
                    <input type="file"
                           id="foto_barang"
                           name="foto_barang"
                           accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                           onchange="pratinjauFotoEdit(this)"
                           class="block w-full text-xs text-gray-600
                                  file:mr-3 file:py-2 file:px-4
                                  file:rounded-full file:border-0
                                  file:text-xs file:font-semibold
                                  file:bg-[#111111] file:text-white
                                  hover:file:bg-gray-800 cursor-pointer">
                    <p class="text-[11px] text-gray-400 mt-1.5">
                        Biarkan kosong bila tidak ingin mengubah foto saat ini.
                        <?php if ($adaFoto): ?>
                            <span class="text-gray-600 font-medium">(Foto saat ini: <?= e($fotoSaatIni) ?>)</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Tombol Aksi -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="<?= e(base_url('barang/list.php')) ?>" class="btn-sekunder py-2 px-5">
                Batal
            </a>
            <button type="submit" class="btn-utama py-2.5 px-6">
                Simpan Perubahan
            </button>
        </div>
    </form>
</div>

<script>
const URL_FOTO_ASLI = <?= json_encode($adaFoto ? base_url($fotoSaatIni) : '') ?>;

function pratinjauFotoEdit(input) {
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
    } else if (URL_FOTO_ASLI !== '') {
        img.src = URL_FOTO_ASLI;
        img.classList.remove('hidden');
        if (ikon) ikon.classList.add('hidden');
    } else {
        img.src = '';
        img.classList.add('hidden');
        if (ikon) ikon.classList.remove('hidden');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer_admin.php'; ?>
