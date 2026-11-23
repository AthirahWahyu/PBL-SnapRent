<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/ketersediaan.php';

if (!is_post()) {
    kirim_json('error', 'Metode permintaan tidak diizinkan. Gunakan metode POST.', 405);
}

// Verifikasi token CSRF untuk endpoint JSON
$tokenDikirim = (string) ($_POST['_csrf_token'] ?? '');
$tokenSesi    = (string) ($_SESSION['_csrf_token'] ?? '');
if ($tokenSesi === '' || !hash_equals($tokenSesi, $tokenDikirim)) {
    kirim_json('error', 'Permintaan tidak valid (token keamanan tidak cocok). Silakan muat ulang halaman.');
}

$tzJakarta = new DateTimeZone('Asia/Jakarta');
$sekarang  = new DateTimeImmutable('now', $tzJakarta);
$hariIni   = $sekarang->format('Y-m-d');

/**
 * Bentuk jadwal SQL (waktu_mulai_sewa & waktu_rencana_kembali) dari Tanggal Ambil (Y-m-d) dan Durasi (hari).
 */
$bentukJadwalDariDurasi = function (string $tanggalMulai, int $durasiHari) use ($tzJakarta, $sekarang, $hariIni): array {
    $durasiHari = max(1, min(60, $durasiHari));
    $dtTanggal  = DateTimeImmutable::createFromFormat('Y-m-d', $tanggalMulai, $tzJakarta);
    if (!$dtTanggal || $dtTanggal->format('Y-m-d') !== $tanggalMulai) {
        return ['valid' => false, 'pesan' => 'Format tanggal ambil tidak valid.'];
    }

    if ($tanggalMulai < $hariIni) {
        return ['valid' => false, 'pesan' => 'Tanggal ambil sewa tidak boleh berada di masa lalu.'];
    }

    if ($tanggalMulai === $hariIni) {
        $dtMulai = $sekarang;
    } else {
        $dtMulai = $dtTanggal->setTime(8, 0, 0);
    }
    $dtSelesai = $dtMulai->modify('+' . $durasiHari . ' days');

    return [
        'valid'         => true,
        'tanggal_mulai' => $tanggalMulai,
        'hari'          => $durasiHari,
        'mulai_sql'     => $dtMulai->format('Y-m-d H:i:s'),
        'selesai_sql'   => $dtSelesai->format('Y-m-d H:i:s'),
        'mulai_input'   => $dtMulai->format('Y-m-d\TH:i'),
        'selesai_input' => $dtSelesai->format('Y-m-d\TH:i'),
    ];
};

$aksi = trim((string) ($_POST['aksi'] ?? ''));

// ── Mode A: Atur Tanggal Ambil di Dalam Keranjang ────────────────────────────
if ($aksi === 'atur_jadwal') {
    $tanggalMulai = trim((string) ($_POST['tanggal_mulai'] ?? ($_SESSION['keranjang']['tanggal_mulai'] ?? $hariIni)));
    if ($tanggalMulai === '' || $tanggalMulai < $hariIni) {
        $tanggalMulai = $hariIni;
    }

    if (empty($_SESSION['keranjang']) || !is_array($_SESSION['keranjang'])) {
        $_SESSION['keranjang'] = ['items' => []];
    }

    $dtTanggal = DateTimeImmutable::createFromFormat('Y-m-d', $tanggalMulai, $tzJakarta) ?: $sekarang;
    $dtMulai   = ($tanggalMulai === $hariIni) ? $sekarang : $dtTanggal->setTime(8, 0, 0);

    $_SESSION['keranjang']['tanggal_mulai'] = $tanggalMulai;
    $_SESSION['keranjang']['mulai']         = $dtMulai->format('Y-m-d H:i:s');
    $_SESSION['keranjang']['mulai_input']   = $dtMulai->format('Y-m-d\TH:i');

    kirim_json('ok', [
        'message'       => 'Tanggal pengambilan diperbarui menjadi ' . $tanggalMulai . '.',
        'tanggal_mulai' => $tanggalMulai,
        'mulai'         => $_SESSION['keranjang']['mulai'],
        'jumlah_item'   => hitung_item_keranjang(),
    ]);
}

// ── Mode A2: Ubah Durasi Hari Khusus 1 Barang di Keranjang ────────────────────
if ($aksi === 'ubah_durasi_item') {
    $idBarang = ambil_int('id_barang', 'post');
    $durasiBaru = max(1, min(60, (int) ($_POST['durasi_hari'] ?? 1)));

    if ($idBarang === null || empty($_SESSION['keranjang']['items'][$idBarang])) {
        kirim_json('error', 'Barang tidak ditemukan di keranjang.');
    }

    $_SESSION['keranjang']['items'][$idBarang]['hari'] = $durasiBaru;

    kirim_json('ok', [
        'message'     => 'Durasi sewa berhasil diubah menjadi ' . $durasiBaru . ' hari.',
        'id_barang'   => $idBarang,
        'durasi_hari' => $durasiBaru,
        'jumlah_item' => hitung_item_keranjang(),
    ]);
}

// ── Validasi Barang untuk Mode Ubah Jumlah & Mode Tambah ──────────────────────
$idBarang = ambil_int('id_barang', 'post');
if ($idBarang === null) {
    kirim_json('error', 'ID peralatan tidak valid.');
}

$stmtBrg = $pdo->prepare('
    SELECT id_barang, nama_barang, harga_sewa_per_hari, stok, status_barang
      FROM barang
     WHERE id_barang = :id
     LIMIT 1
');
$stmtBrg->execute([':id' => $idBarang]);
$barang = $stmtBrg->fetch(PDO::FETCH_ASSOC);

if (!$barang) {
    kirim_json('error', 'Peralatan tidak ditemukan di katalog.');
}

if ($barang['status_barang'] !== 'aktif' || (int) $barang['stok'] < 1) {
    kirim_json('error', 'Maaf, "' . $barang['nama_barang'] . '" sedang tidak aktif dan tidak dapat disewa.');
}

// Tentukan jadwal sewa aktif untuk keranjang
$tanggalMulaiKeranjang = (string) ($_SESSION['keranjang']['tanggal_mulai'] ?? '');
$durasiHariKeranjang   = (int) ($_SESSION['keranjang']['hari'] ?? 0);

if ($tanggalMulaiKeranjang === '' || $durasiHariKeranjang < 1) {
    $rawMulaiFilter   = trim((string) ($_POST['mulai'] ?? ($_SESSION['rentang_sewa']['mulai'] ?? '')));
    $rawSelesaiFilter = trim((string) ($_POST['selesai'] ?? ($_SESSION['rentang_sewa']['selesai'] ?? '')));

    if ($rawMulaiFilter !== '' && $rawSelesaiFilter !== '') {
        try {
            $dtM = new DateTimeImmutable($rawMulaiFilter, $tzJakarta);
            $dtS = new DateTimeImmutable($rawSelesaiFilter, $tzJakarta);
            if ($dtM->format('Y-m-d') >= $hariIni && $dtS > $dtM) {
                $tanggalMulaiKeranjang = $dtM->format('Y-m-d');
                $durasiHariKeranjang   = db_hitung_hari($pdo, $dtM->format('Y-m-d H:i:s'), $dtS->format('Y-m-d H:i:s'));
            }
        } catch (Exception) {
            // Gunakan default di bawah
        }
    }
}

if ($tanggalMulaiKeranjang === '' || $tanggalMulaiKeranjang < $hariIni) {
    $tanggalMulaiKeranjang = $hariIni;
}
if ($durasiHariKeranjang < 1) {
    $durasiHariKeranjang = 1;
}

$jadwal = $bentukJadwalDariDurasi($tanggalMulaiKeranjang, $durasiHariKeranjang);
if (!$jadwal['valid']) {
    kirim_json('error', $jadwal['pesan']);
}

$mulaiSql   = $jadwal['mulai_sql'];
$selesaiSql = $jadwal['selesai_sql'];
$sisaStok   = ambil_sisa_stok($pdo, $idBarang, $mulaiSql, $selesaiSql);

// ── Mode B: Ubah Jumlah Unit Barang di Dalam Keranjang ────────────────────────
if ($aksi === 'ubah_jumlah') {
    $jumlahBaru = isset($_POST['jumlah']) ? (int) $_POST['jumlah'] : 1;

    if ($jumlahBaru <= 0) {
        unset($_SESSION['keranjang']['items'][$idBarang]);
        if (empty($_SESSION['keranjang']['items'])) {
            unset($_SESSION['keranjang']);
        }
        kirim_json('ok', [
            'message'     => '"' . $barang['nama_barang'] . '" dihapus dari keranjang.',
            'id_barang'   => $idBarang,
            'jumlah_unit' => 0,
            'jumlah_item' => hitung_item_keranjang(),
        ]);
    }

    if ($sisaStok < 1) {
        kirim_json('error', 'Maaf, "' . $barang['nama_barang'] . '" sedang tidak tersedia pada jadwal tersebut.');
    }

    if ($jumlahBaru > $sisaStok) {
        kirim_json(
            'error',
            'Stok tersedia untuk "' . $barang['nama_barang'] . '" pada tanggal tersebut hanya ' . $sisaStok . ' unit.'
        );
    }

    $_SESSION['keranjang']['items'][$idBarang] = [
        'id_barang'    => $idBarang,
        'jumlah'       => $jumlahBaru,
        'waktu_tambah' => $_SESSION['keranjang']['items'][$idBarang]['waktu_tambah'] ?? time(),
    ];

    kirim_json('ok', [
        'message'     => 'Jumlah "' . $barang['nama_barang'] . '" diperbarui menjadi ' . $jumlahBaru . ' unit.',
        'id_barang'   => $idBarang,
        'jumlah_unit' => $jumlahBaru,
        'sisa_stok'   => $sisaStok,
        'jumlah_item' => hitung_item_keranjang(),
    ]);
}

// ── Mode C: Tambah Barang ke Keranjang (Bisa > 1 Unit Sesuai Sisa Stok) ───────
$itemsSaatIni   = $_SESSION['keranjang']['items'] ?? [];
$jumlahSekarang = isset($itemsSaatIni[$idBarang])
    ? max(1, (int) ($itemsSaatIni[$idBarang]['jumlah'] ?? 1))
    : 0;
$tambahUnit     = max(1, (int) ($_POST['jumlah'] ?? 1));
$jumlahTarget   = $jumlahSekarang + $tambahUnit;

// Ambil durasi hari jika dikirim spesifik dari form pop-up detail (misal: Pesan Langsung)
$durasiKirim    = isset($_POST['durasi_hari']) ? max(1, min(60, (int) $_POST['durasi_hari'])) : null;
$durasiItemIni  = $durasiKirim ?? ($itemsSaatIni[$idBarang]['hari'] ?? 1);

if ($sisaStok < 1) {
    kirim_json(
        'error',
        'Maaf, "' . $barang['nama_barang'] . '" tidak tersedia (sudah dipesan/disewa) pada tanggal tersebut.'
    );
}

if ($jumlahTarget > $sisaStok) {
    if ($jumlahSekarang > 0) {
        kirim_json(
            'error',
            'Stok tersedia untuk "' . $barang['nama_barang'] . '" hanya ' . $sisaStok . ' unit (di keranjang Anda sudah ada ' . $jumlahSekarang . ' unit).'
        );
    }
    kirim_json(
        'error',
        'Stok tersedia untuk "' . $barang['nama_barang'] . '" hanya ' . $sisaStok . ' unit.'
    );
}

if (empty($_SESSION['keranjang']) || !is_array($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = ['items' => []];
}

$_SESSION['keranjang']['tanggal_mulai'] = $jadwal['tanggal_mulai'];
$_SESSION['keranjang']['hari']          = max((int)($_SESSION['keranjang']['hari'] ?? 1), $durasiItemIni);
$_SESSION['keranjang']['mulai']         = $jadwal['mulai_sql'];
$_SESSION['keranjang']['selesai']       = $jadwal['selesai_sql'];
$_SESSION['keranjang']['mulai_input']   = $jadwal['mulai_input'];
$_SESSION['keranjang']['selesai_input'] = $jadwal['selesai_input'];
$_SESSION['keranjang']['items'][$idBarang] = [
    'id_barang'    => $idBarang,
    'jumlah'       => $jumlahTarget,
    'hari'         => $durasiItemIni,
    'waktu_tambah' => $itemsSaatIni[$idBarang]['waktu_tambah'] ?? time(),
];

$jumlahItem = hitung_item_keranjang();

kirim_json('ok', [
    'message'     => $jumlahTarget > 1
        ? '"' . $barang['nama_barang'] . '" ditambahkan (' . $jumlahTarget . ' unit di keranjang).'
        : '"' . $barang['nama_barang'] . '" berhasil ditambahkan ke keranjang.',
    'id_barang'   => $idBarang,
    'jumlah_unit' => $jumlahTarget,
    'sisa_stok'   => $sisaStok,
    'jumlah_item' => $jumlahItem,
    'hari'        => $jadwal['hari'],
    'mulai'       => $jadwal['mulai_sql'],
    'selesai'     => $jadwal['selesai_sql'],
]);
