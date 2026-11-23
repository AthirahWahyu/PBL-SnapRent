<?php
// Backend (Rafi'): Logika menangkap $_GET filter dan loop dari DB
// Frontend (Athirah): Layout katalog, grid, form filter dengan Tailwind CSS

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/koneksi.php';
require_once __DIR__ . '/includes/ketersediaan.php';

// ── 1. Ambil Daftar Kategori untuk Dropdown Filter ────────────────────────────
$stmtKat = $pdo->query('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori ASC');
$daftarKategori = $stmtKat->fetchAll(PDO::FETCH_ASSOC);
$mapKategoriValid = [];
foreach ($daftarKategori as $kat) {
    $mapKategoriValid[(int) $kat['id_kategori']] = $kat['nama_kategori'];
}

// ── 2. Tangkap & Validasi Input Filter (Rentang Waktu, Kategori, Pencarian) ───
$tzJakarta = new DateTimeZone('Asia/Jakarta');
$sekarang  = new DateTimeImmutable('now', $tzJakarta);

// Reset jadwal dari session bila diminta pengguna
if (isset($_GET['reset_jadwal']) && $_GET['reset_jadwal'] === '1') {
    unset($_SESSION['rentang_sewa']);
}

$adaParamTanggal = array_key_exists('mulai', $_GET) || array_key_exists('selesai', $_GET);
if ($adaParamTanggal) {
    $rawMulai   = trim((string) ($_GET['mulai'] ?? ''));
    $rawSelesai = trim((string) ($_GET['selesai'] ?? ''));
    // Bila keduanya dikosongkan oleh pengguna, bersihkan rentang di session
    if ($rawMulai === '' && $rawSelesai === '') {
        unset($_SESSION['rentang_sewa']);
    }
} elseif (!empty($_SESSION['rentang_sewa']) && is_array($_SESSION['rentang_sewa'])) {
    $rawMulai   = (string) ($_SESSION['rentang_sewa']['mulai_input'] ?? '');
    $rawSelesai = (string) ($_SESSION['rentang_sewa']['selesai_input'] ?? '');
} else {
    $rawMulai   = '';
    $rawSelesai = '';
}

$validasiError = null;
$rentangValid  = false;
$mulaiSql      = null;
$selesaiSql    = null;
$mulaiInput    = $rawMulai;
$selesaiInput  = $rawSelesai;
$durasiHari    = null;

if ($rawMulai !== '' || $rawSelesai !== '') {
    if ($rawMulai === '' || $rawSelesai === '') {
        $validasiError = 'Silakan lengkapi Tanggal & Jam Mulai serta Tanggal & Jam Selesai secara berpasangan untuk mengecek ketersediaan kamera.';
    } else {
        $dtMulai   = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', substr($rawMulai, 0, 16), $tzJakarta)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $rawMulai, $tzJakarta)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', substr($rawMulai, 0, 16), $tzJakarta);
        $dtSelesai = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', substr($rawSelesai, 0, 16), $tzJakarta)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $rawSelesai, $tzJakarta)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', substr($rawSelesai, 0, 16), $tzJakarta);

        if (!$dtMulai || !$dtSelesai) {
            $validasiError = 'Format tanggal dan jam tidak dikenali. Gunakan pemilih Tanggal & Jam Mulai serta Tanggal & Jam Selesai yang tersedia.';
        } else {
            // Toleransi 5 menit agar waktu saat ini tidak ditolak karena selisih detik
            $batasMasaLalu = $sekarang->modify('-5 minutes');
            if ($dtMulai < $batasMasaLalu) {
                if (!$adaParamTanggal) {
                    // Rentang lama di session sudah kedaluwarsa -> bersihkan otomatis tanpa memblokir
                    unset($_SESSION['rentang_sewa']);
                    $mulaiInput   = '';
                    $selesaiInput = '';
                } else {
                    $validasiError = 'Waktu mulai sewa tidak boleh berada di masa lalu. Silakan pilih tanggal dan jam mulai mulai dari waktu sekarang (' . $sekarang->format('d/m/Y H:i') . ') atau setelahnya.';
                }
            } elseif ($dtSelesai <= $dtMulai) {
                $validasiError = 'Waktu selesai sewa harus lebih besar dari waktu mulai sewa. Silakan pilih tanggal atau jam kembali setelah ' . $dtMulai->format('d/m/Y H:i') . '.';
            } else {
                $rentangValid = true;
                $mulaiSql     = $dtMulai->format('Y-m-d H:i:s');
                $selesaiSql   = $dtSelesai->format('Y-m-d H:i:s');
                $mulaiInput   = $dtMulai->format('Y-m-d\TH:i');
                $selesaiInput = $dtSelesai->format('Y-m-d\TH:i');
                $durasiHari   = db_hitung_hari($pdo, $mulaiSql, $selesaiSql);

                // Simpan rentang waktu valid di session agar bertahan saat pindah halaman
                $_SESSION['rentang_sewa'] = [
                    'mulai'         => $mulaiSql,
                    'selesai'       => $selesaiSql,
                    'mulai_input'   => $mulaiInput,
                    'selesai_input' => $selesaiInput,
                    'hari'          => $durasiHari,
                ];
            }
        }
    }
}

// Filter Kategori & Kata Kunci Pencarian
$idKategori = ambil_int('kategori', 'get');
if ($idKategori !== null && !isset($mapKategoriValid[$idKategori])) {
    $idKategori = null;
}
$kataKunci = trim((string) ($_GET['q'] ?? ''));

// Paginasi (24 kartu per halaman)
$perHalaman = 24;
$halaman    = max(1, (int) ($_GET['page'] ?? 1));

// ── 3. Ambil Data Katalog & Hitung Status Ketersediaan via ketersediaan.php ───
$sqlBarang = '
    SELECT b.id_barang,
           b.id_kategori,
           k.nama_kategori,
           b.nama_barang,
           b.foto_barang,
           b.harga_sewa_per_hari,
           b.stok,
           b.status_barang
      FROM barang b
      JOIN kategori k ON k.id_kategori = b.id_kategori
     WHERE b.status_barang <> \'nonaktif\'
';
$paramsBarang = [];

if ($idKategori !== null) {
    $sqlBarang .= ' AND b.id_kategori = :id_kategori';
    $paramsBarang[':id_kategori'] = $idKategori;
}
if ($kataKunci !== '') {
    $sqlBarang .= ' AND (b.nama_barang ILIKE :cari OR k.nama_kategori ILIKE :cari)';
    $paramsBarang[':cari'] = '%' . $kataKunci . '%';
}
$sqlBarang .= ' ORDER BY b.id_barang ASC';

$stmtBrg = $pdo->prepare($sqlBarang);
$stmtBrg->execute($paramsBarang);
$semuaBarang = $stmtBrg->fetchAll(PDO::FETCH_ASSOC);

// Jika rentang waktu dipilih dan valid, panggil fungsi dari includes/ketersediaan.php
$mapTersediaRentang = [];
if ($rentangValid && $mulaiSql !== null && $selesaiSql !== null) {
    $barisTersedia = ambil_barang_tersedia($pdo, $mulaiSql, $selesaiSql, $idKategori);
    foreach ($barisTersedia as $bt) {
        $mapTersediaRentang[(int) $bt['id_barang']] = (int) $bt['sisa_stok'];
    }
}

$totalTersedia = 0;
// Siapkan waktu saat ini untuk pengecekan ketersediaan default bila tanpa filter rentang
$sekarangSql = $sekarang->format('Y-m-d H:i:s');
$besokSql    = $sekarang->modify('+1 day')->format('Y-m-d H:i:s');

foreach ($semuaBarang as &$item) {
    $idBrg = (int) $item['id_barang'];
    if ($rentangValid) {
        $sisa = $mapTersediaRentang[$idBrg] ?? 0;
        $item['tersedia']  = ($sisa > 0);
        $item['sisa_stok'] = $sisa;
    } else {
        // Tanpa filter rentang waktu: periksa ketersediaan real-time hari ini dari database
        $aktif = ($item['status_barang'] === 'aktif' && (int) $item['stok'] > 0);
        if ($aktif) {
            $sisaRealtime = ambil_sisa_stok($pdo, $idBrg, $sekarangSql, $besokSql);
            $item['tersedia']  = ($sisaRealtime > 0);
            $item['sisa_stok'] = $sisaRealtime;
        } else {
            $item['tersedia']  = false;
            $item['sisa_stok'] = 0;
        }
    }
    if ($item['tersedia']) {
        $totalTersedia++;
    }
}
unset($item);

$totalBarang  = count($semuaBarang);
$totalHalaman = max(1, (int) ceil($totalBarang / $perHalaman));
if ($halaman > $totalHalaman) {
    $halaman = $totalHalaman;
}
$offset       = ($halaman - 1) * $perHalaman;
$barangTampil = array_slice($semuaBarang, $offset, $perHalaman);
$adaLagi      = ($halaman < $totalHalaman);

// Teks informasi jadwal sewa
if ($rentangValid && $mulaiSql !== null && $selesaiSql !== null) {
    $teksInfoJadwal = 'Menampilkan ketersediaan untuk jadwal '
        . format_tanggal($mulaiSql) . ' s/d ' . format_tanggal($selesaiSql)
        . ' (' . (int) $durasiHari . ' hari sewa).';
} else {
    $teksInfoJadwal = 'Pilih tanggal untuk memastikan ketersediaan';
}

// ── 4. Helper Render Kartu Produk ─────────────────────────────────────────────
function render_kartu_produk_katalog(array $brg): string
{
    $idBarang     = (int) $brg['id_barang'];
    $namaBarang   = $brg['nama_barang'] ?? '';
    $namaKategori = $brg['nama_kategori'] ?? '';
    $hargaSewa    = format_rupiah($brg['harga_sewa_per_hari'] ?? 0);
    $tersedia     = !empty($brg['tersedia']);
    $fotoRel      = trim((string) ($brg['foto_barang'] ?? ''));

    $adaFotoFisik = false;
    $urlFoto      = '';
    if ($fotoRel !== '') {
        $pathFisik = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($fotoRel, '/\\'));
        if (is_file($pathFisik)) {
            $adaFotoFisik = true;
            $urlFoto      = base_url($fotoRel);
        }
    }

    ob_start();
    ?>
    <article class="kartu-produk <?= !$tersedia ? 'kartu-produk--tidak-tersedia' : '' ?>"
             data-id-barang="<?= $idBarang ?>"
             data-tersedia="<?= $tersedia ? '1' : '0' ?>">
        <!-- Foto Barang atau Ikon Kamera Pengganti -->
        <div class="kartu-produk__media">
            <?php if ($adaFotoFisik): ?>
                <img src="<?= e($urlFoto) ?>"
                     alt="<?= e($namaBarang) ?>"
                     loading="lazy"
                     class="kartu-produk__foto">
            <?php else: ?>
                <div class="kartu-produk__placeholder" aria-hidden="true">
                    <svg class="w-12 h-12 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                    </svg>
                    <span>Foto kamera</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Kategori Kecil -->
        <span class="kartu-produk__kategori"><?= e($namaKategori) ?></span>

        <!-- Nama Barang -->
        <h2 class="kartu-produk__judul line-clamp-2" title="<?= e($namaBarang) ?>">
            <?= e($namaBarang) ?>
        </h2>

        <!-- Tarif per Hari -->
        <p class="kartu-produk__harga">
            <?= e($hargaSewa) ?> <span class="text-xs font-normal text-gray-500">/ hari</span>
        </p>

        <!-- Status Ketersediaan -->
        <div class="kartu-produk__baris-status">
            <span>Status :</span>
            <?php if ($tersedia): ?>
                <span class="badge badge-tersedia">Tersedia</span>
            <?php else: ?>
                <span class="badge badge-habis">Tidak tersedia</span>
            <?php endif; ?>
        </div>

        <!-- Tombol Aksi: Detail & Sewa (titik kait data-id untuk Tahap C3) + Ikon Keranjang -->
        <div class="kartu-produk__aksi">
            <button type="button"
                    class="btn-utama btn-detail-sewa"
                    data-id="<?= $idBarang ?>">
                Detail &amp; Sewa
            </button>
            <button type="button"
                    class="btn-ikon-keranjang"
                    data-id="<?= $idBarang ?>"
                    data-aksi="tambah-keranjang"
                    <?= !$tersedia ? 'disabled' : '' ?>
                    aria-label="Tambah <?= e($namaBarang) ?> ke keranjang"
                    title="<?= $tersedia ? 'Tambah ke keranjang' : 'Tidak tersedia pada jadwal ini' ?>">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75v4.5m2.25-2.25h-4.5"/>
                </svg>
            </button>
        </div>
    </article>
    <?php
    return (string) ob_get_clean();
}

function render_daftar_kartu_katalog(array $daftar): string
{
    $html = '';
    foreach ($daftar as $brg) {
        $html .= render_kartu_produk_katalog($brg);
    }
    return $html;
}

// ── 5. Respons JSON untuk Permintaan Fetch (Tanpa Reload Halaman) ─────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $ditampilkanSampai = min($offset + count($barangTampil), $totalBarang);
    kirim_json('ok', [
        'validasi_error'     => $validasiError,
        'rentang_aktif'      => $rentangValid,
        'mulai_input'        => $mulaiInput,
        'selesai_input'      => $selesaiInput,
        'durasi_hari'        => $durasiHari,
        'teks_info_jadwal'   => $teksInfoJadwal,
        'total_barang'       => $totalBarang,
        'total_tersedia'     => $totalTersedia,
        'ditampilkan_sampai' => $ditampilkanSampai,
        'halaman_sekarang'   => $halaman,
        'total_halaman'      => $totalHalaman,
        'ada_lagi'           => $adaLagi,
        'html_kartu'         => render_daftar_kartu_katalog($barangTampil),
    ]);
}

// ── 6. Render Halaman HTML Katalog ────────────────────────────────────────────
$judul_halaman = 'Temukan Kamera Impianmu';
$minDatetimeInput = $sekarang->format('Y-m-d\TH:i');
$ditampilkanSampaiAwal = min($offset + count($barangTampil), $totalBarang);

require_once __DIR__ . '/includes/header.php';
?>

<div class="wadah-pelanggan space-y-6">

    <!-- Judul Halaman Katalog -->
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-[#111111] tracking-tight m-0">
                Temukan Kamera Impianmu
            </h1>
            <p class="text-sm text-gray-600 mt-1 m-0">
                Pilih tanggal sewa, kategori, dan peralatan fotografi/videografi sesuai kebutuhan produksi Anda.
            </p>
        </div>
        <div class="text-xs font-medium text-gray-500" id="ringkasanJumlahKatalog">
            Menampilkan <strong class="text-[#111111]" id="angkaDitampilkan"><?= (int) $ditampilkanSampaiAwal ?></strong>
            dari <strong class="text-[#111111]" id="angkaTotalBarang"><?= (int) $totalBarang ?></strong> peralatan
            (<span id="angkaTotalTersedia"><?= (int) $totalTersedia ?></span> tersedia)
        </div>
    </div>

    <!-- Form Filter Katalog (Sesuai Wireframe Katalog.png) -->
    <section aria-label="Filter Katalog Kamera" class="panel-filter-katalog">
        <form id="formFilterKatalog" method="GET" action="<?= e(base_url('index.php')) ?>" class="space-y-4" novalidate>
            <div class="grid-filter-katalog">
                <!-- Tanggal & Jam Mulai -->
                <div>
                    <label for="filterMulai" class="form-label">Tanggal &amp; Jam Mulai</label>
                    <input type="datetime-local"
                           id="filterMulai"
                           name="mulai"
                           value="<?= e($mulaiInput) ?>"
                           min="<?= e($minDatetimeInput) ?>"
                           class="form-input">
                </div>

                <!-- Tanggal & Jam Selesai -->
                <div>
                    <label for="filterSelesai" class="form-label">Tanggal &amp; Jam Selesai</label>
                    <input type="datetime-local"
                           id="filterSelesai"
                           name="selesai"
                           value="<?= e($selesaiInput) ?>"
                           min="<?= e($minDatetimeInput) ?>"
                           class="form-input">
                </div>

                <!-- Kategori -->
                <div>
                    <label for="filterKategori" class="form-label">Kategori</label>
                    <select id="filterKategori" name="kategori" class="form-select">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($daftarKategori as $kat):
                            $idKat = (int) $kat['id_kategori'];
                        ?>
                            <option value="<?= $idKat ?>" <?= $idKategori === $idKat ? 'selected' : '' ?>>
                                <?= e($kat['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Cari Nama Barang -->
                <div>
                    <label for="filterCari" class="form-label">Cari</label>
                    <div class="input-ikon-wrapper">
                        <span class="input-ikon-wrapper__ikon" aria-hidden="true">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                            </svg>
                        </span>
                        <input type="search"
                               id="filterCari"
                               name="q"
                               value="<?= e($kataKunci) ?>"
                               placeholder="Cari kamera, lensa, audio..."
                               autocomplete="off"
                               class="form-input">
                    </div>
                </div>
            </div>

            <!-- Baris Info Jadwal / Petunjuk Ketersediaan & Tombol Aksi Filter -->
            <div id="kotakInfoJadwal"
                 class="info-jadwal-katalog <?= $rentangValid ? 'info-jadwal-katalog--aktif' : '' ?>">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                    </svg>
                    <span id="teksInfoJadwal"><?= e($teksInfoJadwal) ?></span>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" id="btnTerapkanFilter" class="btn-utama py-1.5 px-4 text-xs">
                        Cek Ketersediaan
                    </button>
                    <button type="button"
                            id="btnResetFilter"
                            class="btn-sekunder py-1.5 px-3.5 text-xs <?= ($mulaiInput === '' && $selesaiInput === '' && $idKategori === null && $kataKunci === '') ? 'hidden' : '' ?>">
                        Reset Filter
                    </button>
                </div>
            </div>

            <!-- Kotak Peringatan Validasi Tanggal (Browser & Server) -->
            <div id="kotakErrorValidasi"
                 class="peringatan-validasi-katalog <?= $validasiError === null ? 'hidden' : '' ?>"
                 role="alert"
                 aria-live="assertive">
                <?= e($validasiError ?? '') ?>
            </div>
        </form>
    </section>

    <!-- Indikator Keadaan Memuat (Loading State) -->
    <div id="indikatorMemuatKatalog" class="indikator-memuat-katalog hidden" role="status" aria-live="polite">
        <span class="spinner-katalog" aria-hidden="true"></span>
        <span>Memperbarui daftar peralatan...</span>
    </div>

    <!-- Grid Kartu Katalog (4 kolom desktop, 2 tablet, 1 ponsel) -->
    <section aria-label="Daftar Peralatan Kamera" id="wadahHasilKatalog">
        <div id="gridKatalog"
             class="grid-katalog <?= $totalBarang === 0 ? 'hidden' : '' ?>"
             data-halaman-sekarang="<?= (int) $halaman ?>"
             data-total-halaman="<?= (int) $totalHalaman ?>">
            <?= render_daftar_kartu_katalog($barangTampil) ?>
        </div>

        <!-- Keadaan Kosong (Empty State) -->
        <div id="keadaanKosongKatalog" class="keadaan-kosong <?= $totalBarang > 0 ? 'hidden' : '' ?>">
            <div class="keadaan-kosong__ikon" aria-hidden="true">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
            </div>
            <h2 class="keadaan-kosong__judul">Peralatan tidak ditemukan</h2>
            <p class="keadaan-kosong__teks">
                Tidak ada kamera atau perlengkapan yang cocok dengan kata kunci atau kategori yang dipilih. Coba ubah kata pencarian atau pilih Semua Kategori.
            </p>
            <button type="button" id="btnResetDariKosong" class="btn-sekunder">
                Tampilkan Semua Peralatan
            </button>
        </div>
    </section>

    <!-- Area Paginasi / Tombol "Muat lebih banyak" (24 kartu per halaman) -->
    <div id="wadahMuatLebihBanyak"
         class="pt-4 pb-2 flex flex-col items-center justify-center gap-3 <?= !$adaLagi ? 'hidden' : '' ?>">
        <button type="button"
                id="btnMuatLebihBanyak"
                class="btn-sekunder px-6 py-2.5 text-sm"
                data-next-page="<?= (int) ($halaman + 1) ?>">
            Muat lebih banyak
        </button>

        <!-- Fallback navigasi halaman bila JavaScript dinonaktifkan -->
        <noscript>
            <div class="flex flex-wrap items-center justify-center gap-2 mt-2">
                <?php for ($p = 1; $p <= $totalHalaman; $p++):
                    $queryHal = array_filter([
                        'mulai'    => $mulaiInput !== '' ? $mulaiInput : null,
                        'selesai'  => $selesaiInput !== '' ? $selesaiInput : null,
                        'kategori' => $idKategori,
                        'q'        => $kataKunci !== '' ? $kataKunci : null,
                        'page'     => $p,
                    ]);
                ?>
                    <a href="<?= e(base_url('index.php?' . http_build_query($queryHal))) ?>"
                       class="<?= $p === $halaman ? 'btn-utama' : 'btn-sekunder' ?> px-3 py-1 text-xs">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>
        </noscript>
    </div>

</div>

<script src="<?= e(base_url('assets/js/katalog.js')) ?>" defer></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
