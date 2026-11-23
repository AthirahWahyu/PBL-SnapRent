<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/ketersediaan.php';

$idBarang = ambil_int('id', 'get');
if ($idBarang === null) {
    http_response_code(400);
    ?>
    <div class="keadaan-kosong">
        <p class="keadaan-kosong__judul">ID barang tidak valid</p>
        <p class="keadaan-kosong__teks">Silakan pilih peralatan kamera kembali dari halaman katalog.</p>
    </div>
    <?php
    exit;
}

// Ambil rincian barang beserta kategori dan syarat jaminannya
$stmt = $pdo->prepare('
    SELECT b.id_barang,
           b.id_kategori,
           b.nama_barang,
           b.foto_barang,
           b.kondisi_fisik,
           b.harga_sewa_per_hari,
           b.stok,
           b.status_barang,
           k.nama_kategori,
           k.syarat_jaminan
      FROM barang b
      JOIN kategori k ON k.id_kategori = b.id_kategori
     WHERE b.id_barang = :id
       AND b.status_barang <> \'nonaktif\'
     LIMIT 1
');
$stmt->execute([':id' => $idBarang]);
$barang = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$barang) {
    http_response_code(404);
    ?>
    <div class="keadaan-kosong">
        <p class="keadaan-kosong__judul">Peralatan tidak ditemukan</p>
        <p class="keadaan-kosong__teks">Barang yang Anda pilih tidak tersedia di katalog.</p>
    </div>
    <?php
    exit;
}

// ── Cek Apakah Ada Filter Tanggal Aktif (Opsional untuk Cek Ketersediaan) ─────
$tzJakarta = new DateTimeZone('Asia/Jakarta');
$sekarang  = new DateTimeImmutable('now', $tzJakarta);

$rawMulai   = trim((string) ($_GET['mulai'] ?? ''));
$rawSelesai = trim((string) ($_GET['selesai'] ?? ''));

if ($rawMulai === '' && $rawSelesai === '') {
    if (!empty($_SESSION['keranjang']['mulai']) && !empty($_SESSION['keranjang']['selesai'])) {
        $rawMulai   = (string) ($_SESSION['keranjang']['mulai_input'] ?? $_SESSION['keranjang']['mulai']);
        $rawSelesai = (string) ($_SESSION['keranjang']['selesai_input'] ?? $_SESSION['keranjang']['selesai']);
    } elseif (!empty($_SESSION['rentang_sewa']['mulai']) && !empty($_SESSION['rentang_sewa']['selesai'])) {
        $rawMulai   = (string) ($_SESSION['rentang_sewa']['mulai_input'] ?? $_SESSION['rentang_sewa']['mulai']);
        $rawSelesai = (string) ($_SESSION['rentang_sewa']['selesai_input'] ?? $_SESSION['rentang_sewa']['selesai']);
    }
}

$rentangValid   = false;
$mulaiSql       = null;
$selesaiSql     = null;
$lamaHari       = null;
$perkiraanBiaya = null;

if ($rawMulai !== '' && $rawSelesai !== '') {
    $dtMulai   = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', substr($rawMulai, 0, 16), $tzJakarta)
        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $rawMulai, $tzJakarta)
        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', substr($rawMulai, 0, 16), $tzJakarta);
    $dtSelesai = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', substr($rawSelesai, 0, 16), $tzJakarta)
        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $rawSelesai, $tzJakarta)
        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', substr($rawSelesai, 0, 16), $tzJakarta);

    if ($dtMulai && $dtSelesai && $dtMulai >= $sekarang->modify('-5 minutes') && $dtSelesai > $dtMulai) {
        $rentangValid   = true;
        $mulaiSql       = $dtMulai->format('Y-m-d H:i:s');
        $selesaiSql     = $dtSelesai->format('Y-m-d H:i:s');
        $lamaHari       = db_hitung_hari($pdo, $mulaiSql, $selesaiSql);
        $perkiraanBiaya = (float) $barang['harga_sewa_per_hari'] * $lamaHari;
    }
}

// ── Hitung Status Ketersediaan Memakai includes/ketersediaan.php ──────────────
$statusAktifMaster = ($barang['status_barang'] === 'aktif' && (int) $barang['stok'] > 0);
if ($rentangValid && $mulaiSql !== null && $selesaiSql !== null) {
    $bentrok  = cek_bentrok_jadwal($pdo, $idBarang, $mulaiSql, $selesaiSql);
    $sisaStok = ambil_sisa_stok($pdo, $idBarang, $mulaiSql, $selesaiSql);
    $tersedia = ($statusAktifMaster && !$bentrok && $sisaStok > 0);
} else {
    // Tanpa filter rentang waktu: periksa ketersediaan saat ini
    $sekarangSql = $sekarang->format('Y-m-d H:i:s');
    $besokSql    = $sekarang->modify('+1 day')->format('Y-m-d H:i:s');
    $sisaStok    = $statusAktifMaster ? ambil_sisa_stok($pdo, $idBarang, $sekarangSql, $besokSql) : 0;
    $tersedia    = ($statusAktifMaster && $sisaStok > 0);
}

// ── Cek Kondisi Keranjang Saat Ini (Mendukung > 1 Unit Sesuai Sisa Stok) ─────
$itemsKeranjang    = $_SESSION['keranjang']['items'] ?? [];
$jumlahDiKeranjang = isset($itemsKeranjang[$idBarang])
    ? max(1, (int) ($itemsKeranjang[$idBarang]['jumlah'] ?? 1))
    : 0;
$sisaBisaDitambah  = max(0, $sisaStok - $jumlahDiKeranjang);

$bolehTambah    = false;
$alasanNonaktif = null;

if (!$statusAktifMaster) {
    $alasanNonaktif = 'Peralatan ini sedang dalam perawatan (' . $barang['status_barang'] . ') dan belum dapat disewa.';
} elseif (!$tersedia || $sisaStok < 1) {
    $alasanNonaktif = 'Maaf, peralatan ini sudah dipesan/disewa seluruh stoknya pada rentang waktu yang dipilih.';
} elseif ($sisaBisaDitambah < 1) {
    $alasanNonaktif = 'Seluruh sisa stok tersedia (' . $sisaStok . ' unit) untuk peralatan ini sudah berada di dalam keranjang Anda.';
} else {
    $bolehTambah = true;
}

// ── Foto Barang atau Placeholder ──────────────────────────────────────────────
$fotoRel      = trim((string) ($barang['foto_barang'] ?? ''));
$adaFotoFisik = false;
$urlFoto      = '';
if ($fotoRel !== '') {
    $pathFisik = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($fotoRel, '/\\'));
    if (is_file($pathFisik)) {
        $adaFotoFisik = true;
        $urlFoto      = base_url($fotoRel);
    }
}

$kondisiTeks = trim((string) ($barang['kondisi_fisik'] ?? ''));
if ($kondisiTeks === '') {
    $kondisiTeks = 'Unit fisik prima, fungsi normal, sensor/optik bersih, dilengkapi baterai, charger, dan tutup pelindung standar. Pengecekan fisik & fungsi dilakukan bersama admin saat pengambilan di toko.';
}
?>

<div class="detail-produk-grid" data-id-barang="<?= $idBarang ?>">
    <!-- Kolom Kiri: Foto Produk -->
    <div class="space-y-3">
        <div class="detail-produk__media">
            <?php if ($adaFotoFisik): ?>
                <img src="<?= e($urlFoto) ?>"
                     alt="<?= e($barang['nama_barang']) ?>"
                     class="detail-produk__foto">
            <?php else: ?>
                <div class="kartu-produk__placeholder py-8" aria-hidden="true">
                    <svg class="w-16 h-16 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                    </svg>
                    <span>Foto peralatan kamera</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Kotak Syarat Jaminan dari Kategori -->
        <div class="detail-produk__kotak-info">
            <div class="font-semibold text-xs text-[#111111] uppercase tracking-wider mb-1">
                Syarat Jaminan Penyewaan
            </div>
            <p class="text-xs text-gray-600 leading-relaxed m-0">
                Wajib menyerahkan fisik: <strong class="text-[#111111]"><?= e($barang['syarat_jaminan']) ?></strong>
                saat pengambilan barang di toko (dicatat langsung oleh admin).
            </p>
        </div>
    </div>

    <!-- Kolom Kanan: Rincian, Ketersediaan, & Tombol Aksi -->
    <div class="space-y-4">
        <div>
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">
                SnapRent · <?= e($barang['nama_kategori']) ?>
            </div>
            <h3 class="text-xl sm:text-2xl font-bold text-[#111111] leading-snug m-0">
                <?= e($barang['nama_barang']) ?>
            </h3>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <span class="text-lg font-bold text-[#111111]">
                    <?= e(format_rupiah($barang['harga_sewa_per_hari'])) ?>
                    <span class="text-xs font-normal text-gray-500">/ Hari</span>
                </span>
                <?php if ($tersedia): ?>
                    <span class="badge badge-tersedia">Tersedia</span>
                <?php else: ?>
                    <span class="badge badge-habis">Tidak tersedia</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Deskripsi Kondisi & Kelengkapan -->
        <div>
            <div class="font-semibold text-sm text-[#111111] mb-1">Deskripsi &amp; Kelengkapan Produk</div>
            <p class="text-xs text-gray-600 leading-relaxed m-0">
                <?= e($kondisiTeks) ?>
            </p>
        </div>

        <!-- Info Stok & Perkiraan Biaya (Bila Filter Tanggal Sedang Dipilih) -->
        <div class="text-xs text-gray-600 font-medium flex flex-wrap items-center gap-2">
            <span>Stok tersisa : <strong class="text-[#111111]"><?= (int) $sisaStok ?> produk</strong></span>
            <?php if ($jumlahDiKeranjang > 0): ?>
                <span class="badge badge-disetujui">Di keranjang: <?= (int) $jumlahDiKeranjang ?> unit</span>
            <?php endif; ?>
        </div>

        <!-- Form Pengaturan Tanggal Ambil & Durasi Sewa Langsung di Pop-up -->
        <?php
        $tglHariIni = $sekarang->format('Y-m-d');
        $tglPilihan = !empty($rawMulai) ? substr($rawMulai, 0, 10) : $tglHariIni;
        if ($tglPilihan < $tglHariIni) {
            $tglPilihan = $tglHariIni;
        }
        $durasiPilihan = max(1, (int) ($lamaHari ?? 1));
        $hargaItemPerHari = (float) $barang['harga_sewa_per_hari'];
        $estimasiTotal = $hargaItemPerHari * $durasiPilihan;
        ?>
        <div class="detail-produk__kotak-info space-y-3 bg-gray-50/80 border border-gray-200">
            <div class="flex items-center justify-between">
                <span class="font-bold text-xs text-[#111111] uppercase tracking-wider">Atur Jadwal &amp; Durasi Sewa</span>
                <span id="badgeDurasiDetail" class="badge badge-tersedia"><?= $durasiPilihan ?> Hari</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                <div>
                    <label for="detailInputTanggal" class="form-label text-xs mb-1">Tanggal Ambil (Hari-H)</label>
                    <input type="date"
                           id="detailInputTanggal"
                           value="<?= e($tglPilihan) ?>"
                           min="<?= e($tglHariIni) ?>"
                           class="form-input text-xs py-2 px-2.5 bg-white">
                </div>

                <div>
                    <label for="detailInputDurasi" class="form-label text-xs mb-1">Durasi Sewa (Hari)</label>
                    <div class="flex items-center gap-1.5">
                        <button type="button"
                                id="btnKurangDurasiDetail"
                                class="btn-sekunder px-2.5 py-1.5 text-xs font-bold"
                                aria-label="Kurangi 1 hari"
                                <?= $durasiPilihan <= 1 ? 'disabled' : '' ?>>−</button>
                        <input type="number"
                               id="detailInputDurasi"
                               min="1"
                               max="60"
                               value="<?= $durasiPilihan ?>"
                               class="form-input text-xs py-2 px-2 text-center font-bold bg-white">
                        <button type="button"
                                id="btnTambahDurasiDetail"
                                class="btn-sekunder px-2.5 py-1.5 text-xs font-bold"
                                aria-label="Tambah 1 hari">+</button>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-gray-200 text-xs">
                <span class="text-gray-600 font-medium">Estimasi Subtotal:</span>
                <span id="labelEstimasiBiayaDetail" class="font-bold text-sm text-[#111111]"
                      data-harga-per-hari="<?= $hargaItemPerHari ?>">
                    <?= e(format_rupiah($estimasiTotal)) ?>
                </span>
            </div>
        </div>

        <!-- Alasan bila tombol Tambah ke Keranjang nonaktif -->
        <?php if (!$bolehTambah && $alasanNonaktif !== null): ?>
            <div class="peringatan-validasi-katalog text-xs" role="note">
                <?= e($alasanNonaktif) ?>
            </div>
        <?php endif; ?>

        <!-- Tombol Aksi: Pesan Sekarang (Langsung Checkout) & Tambah Keranjang -->
        <div class="pt-1 flex flex-wrap items-center gap-2.5">
            <button type="button"
                    class="btn-utama flex-1 py-2.5 px-5 text-sm"
                    data-aksi="pesan-langsung"
                    data-id="<?= $idBarang ?>"
                    <?= !$bolehTambah ? 'disabled' : '' ?>>
                <?= (!$bolehTambah && $jumlahDiKeranjang > 0) ? 'Stok Maksimal di Keranjang' : 'Pesan Sekarang' ?>
            </button>

            <button type="button"
                    class="btn-ikon-keranjang"
                    data-aksi="tambah-keranjang"
                    data-id="<?= $idBarang ?>"
                    <?= !$bolehTambah ? 'disabled' : '' ?>
                    aria-label="Tambah <?= e($barang['nama_barang']) ?> ke keranjang"
                    title="<?= $bolehTambah ? 'Tambah ke keranjang' : e($alasanNonaktif ?? 'Tidak dapat ditambahkan') ?>">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75v4.5m2.25-2.25h-4.5"/>
                </svg>
            </button>
        </div>
        </div>
    </div>
</div>
