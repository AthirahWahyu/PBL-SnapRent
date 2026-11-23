<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/ketersediaan.php';
require_once __DIR__ . '/../includes/upload.php';

if (!is_post()) {
    redirect('booking/checkout.php');
}

csrf_verifikasi();

// 1. Pastikan keranjang masih berisi barang
$itemsKeranjang = $_SESSION['keranjang']['items'] ?? [];
if (empty($itemsKeranjang) || !is_array($itemsKeranjang)) {
    flash_set('error', 'Keranjang Anda kosong atau sesi telah berakhir. Silakan pilih peralatan kembali.');
    redirect('index.php');
}

// 2. Ambil & Validasi Input Form
$namaPenyewa     = trim((string) ($_POST['nama_penyewa'] ?? ''));
$rawTelepon      = trim((string) ($_POST['no_telepon'] ?? ''));
$noTelepon       = preg_replace('/[\s\-().]/', '', $rawTelepon);
$jenisPembayaran = trim((string) ($_POST['jenis_pembayaran'] ?? ''));
$skemaPembayaran = trim((string) ($_POST['skema_pembayaran'] ?? 'lunas'));
$setujuSyarat    = !empty($_POST['setuju_syarat']);

// Simpan input lama agar tidak hilang bila ada validasi yang gagal
$_SESSION['_checkout_old'] = [
    'nama_penyewa'     => $namaPenyewa,
    'no_telepon'       => $noTelepon,
    'jenis_pembayaran' => $jenisPembayaran,
    'skema_pembayaran' => $skemaPembayaran,
];

$panjangNama = mb_strlen($namaPenyewa, 'UTF-8');
if ($panjangNama < 3 || $panjangNama > 100) {
    flash_set('error', 'Nama lengkap wajib diisi antara 3 sampai 100 karakter.');
    redirect('booking/checkout.php');
}

if (!preg_match('/^\+?[0-9]{8,14}$/', $noTelepon)) {
    flash_set('error', 'Format Nomor WhatsApp/Telepon tidak valid. Gunakan 8 sampai 14 digit angka (contoh: 081234567890).');
    redirect('booking/checkout.php');
}

$metodeValid = ['transfer', 'qris'];
if (!in_array($jenisPembayaran, $metodeValid, true)) {
    flash_set('error', 'Silakan pilih metode pembayaran yang valid (Transfer Bank atau QRIS). Pembayaran tunai tidak tersedia.');
    redirect('booking/checkout.php');
}

$skemaValid = ['lunas', 'dp_50'];
if (!in_array($skemaPembayaran, $skemaValid, true)) {
    $skemaPembayaran = 'lunas';
}

if (!$setujuSyarat) {
    flash_set('error', 'Anda wajib menyetujui Syarat & Ketentuan penyewaan terlebih dahulu.');
    redirect('booking/checkout.php');
}

// 3. Validasi & Simpan File Bukti Pembayaran
if (empty($_FILES['bukti_pembayaran']) || ($_FILES['bukti_pembayaran']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    flash_set('error', 'Wajib mengunggah file bukti pembayaran (struk/tangkapan layar transfer atau QRIS).');
    redirect('booking/checkout.php');
}

try {
    $pathBukti = simpan_bukti_pembayaran($_FILES['bukti_pembayaran']);
} catch (RuntimeException $re) {
    flash_set('error', $re->getMessage());
    redirect('booking/checkout.php');
}

// 3. Validasi Rentang Waktu & Durasi dari Keranjang
$tzJakarta = new DateTimeZone('Asia/Jakarta');
$sekarang  = new DateTimeImmutable('now', $tzJakarta);
$hariIni   = $sekarang->format('Y-m-d');

$tanggalMulai = (string) ($_SESSION['keranjang']['tanggal_mulai'] ?? $hariIni);
if ($tanggalMulai < $hariIni) {
    flash_set('error', 'Tanggal mulai sewa pada keranjang sudah lewat. Silakan perbarui tanggal sewa Anda.');
    redirect('booking/checkout.php');
}

$dtTanggal = DateTimeImmutable::createFromFormat('Y-m-d', $tanggalMulai, $tzJakarta) ?: $sekarang;
$dtMulai   = ($tanggalMulai === $hariIni) ? $sekarang : $dtTanggal->setTime(8, 0, 0);
$mulaiSql  = $dtMulai->format('Y-m-d H:i:s');

// Hitung durasi maksimal untuk waktu_rencana_kembali pada transaksi induk
$maksDurasiHari = 1;
foreach ($itemsKeranjang as $it) {
    $d = max(1, (int) ($it['hari'] ?? 1));
    if ($d > $maksDurasiHari) {
        $maksDurasiHari = $d;
    }
}
$dtSelesai  = $dtMulai->modify('+' . $maksDurasiHari . ' days');
$selesaiSql = $dtSelesai->format('Y-m-d H:i:s');

// 4. Urutkan ID Barang untuk Mencegah Deadlock Saat Trigger Mengunci Baris Barang
$idsBarang = array_values(array_filter(array_map('intval', array_keys($itemsKeranjang)), fn($v) => $v > 0));
sort($idsBarang);

if (empty($idsBarang)) {
    unset($_SESSION['keranjang']);
    flash_set('error', 'Tidak ada peralatan valid di dalam keranjang.');
    redirect('index.php');
}

// Ambil nama barang untuk keperluan pesan error manusiawi bila terjadi bentrok
$placeholders = implode(',', array_fill(0, count($idsBarang), '?'));
$stmtNama = $pdo->prepare("SELECT id_barang, nama_barang FROM barang WHERE id_barang IN ($placeholders)");
$stmtNama->execute($idsBarang);
$mapNamaBarang = [];
foreach ($stmtNama->fetchAll(PDO::FETCH_ASSOC) as $b) {
    $mapNamaBarang[(int) $b['id_barang']] = $b['nama_barang'];
}

// ── 5. Eksekusi Transaksi Database Atomik ─────────────────────────────────────
try {
    $pdo->beginTransaction();

    // A. Cari penyewa berdasarkan no_telepon; bila sudah ada perbarui nama bila berbeda, bila belum ada INSERT baru
    $stmtPenyewa = $pdo->prepare('
        INSERT INTO penyewa (nama_penyewa, no_telepon)
        VALUES (:nama, :tel)
        ON CONFLICT (no_telepon) DO UPDATE
            SET nama_penyewa = EXCLUDED.nama_penyewa
        RETURNING id_penyewa
    ');
    $stmtPenyewa->execute([
        ':nama' => $namaPenyewa,
        ':tel'  => $noTelepon,
    ]);
    $idPenyewa = (int) $stmtPenyewa->fetchColumn();

    // B. Insert baris transaksi (nomor_transaksi diisi otomatis oleh trigger trg_transaksi_before_insert)
    $stmtTrx = $pdo->prepare("
        INSERT INTO transaksi (
            id_penyewa,
            waktu_mulai_sewa,
            waktu_rencana_kembali,
            jenis_pembayaran,
            skema_pembayaran,
            bukti_pembayaran,
            setuju_syarat,
            status_pembayaran,
            status_transaksi
        ) VALUES (
            :id_penyewa,
            :mulai,
            :selesai,
            :jenis_bayar,
            :skema_bayar,
            :bukti_bayar,
            TRUE,
            'menunggu_verifikasi',
            'menunggu_verifikasi'
        )
        RETURNING id_transaksi
    ");
    $stmtTrx->execute([
        ':id_penyewa'  => $idPenyewa,
        ':mulai'       => $mulaiSql,
        ':selesai'     => $selesaiSql,
        ':jenis_bayar' => $jenisPembayaran,
        ':skema_bayar' => $skemaPembayaran,
        ':bukti_bayar' => $pathBukti,
    ]);
    $idTransaksi = (int) $stmtTrx->fetchColumn();

    // C. Insert detail_transaksi berurutan menurut id_barang
    // Trigger trg_detail_before_write mengunci baris barang, memvalidasi stok per rentang waktu item,
    // mengisi harga_satuan, mengisi waktu_rencana_kembali, dan menghitung subtotal = harga * unit * lama_hari.
    $stmtDetail = $pdo->prepare('
        INSERT INTO detail_transaksi (
            id_transaksi,
            id_barang,
            jumlah,
            lama_hari,
            waktu_rencana_kembali,
            status_item
        ) VALUES (
            :id_transaksi,
            :id_barang,
            :jumlah,
            :lama_hari,
            :rencana_kembali,
            \'disewa\'
        )
    ');

    foreach ($idsBarang as $idBrg) {
        if (!isset($mapNamaBarang[$idBrg])) {
            throw new RuntimeException('Barang #' . $idBrg . ' tidak ditemukan di katalog.');
        }
        $jumlahUnit    = max(1, (int) ($itemsKeranjang[$idBrg]['jumlah'] ?? 1));
        $durasiItem    = max(1, (int) ($itemsKeranjang[$idBrg]['hari'] ?? 1));
        $dtSelesaiItem = $dtMulai->modify('+' . $durasiItem . ' days');

        $stmtDetail->execute([
            ':id_transaksi'     => $idTransaksi,
            ':id_barang'        => $idBrg,
            ':jumlah'           => $jumlahUnit,
            ':lama_hari'        => $durasiItem,
            ':rencana_kembali'  => $dtSelesaiItem->format('Y-m-d H:i:s'),
        ]);
    }

    $pdo->commit();

    // D. Bersihkan keranjang & simpan id_transaksi terakhir di session (untuk booking/sukses.php)
    unset($_SESSION['keranjang'], $_SESSION['rentang_sewa'], $_SESSION['_checkout_old']);
    $_SESSION['id_transaksi_terakhir'] = $idTransaksi;

    flash_set('sukses', 'Pesanan Anda berhasil dibuat! Admin akan memverifikasi bukti pembayaran Anda.');
    redirect('booking/sukses.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Hapus file bukti pembayaran yang sudah terlanjur diunggah bila transaksi database gagal
    if (!empty($pathBukti)) {
        $rootProyek = dirname(__DIR__);
        $fileHapus  = $rootProyek . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pathBukti);
        if (is_file($fileHapus)) {
            @unlink($fileHapus);
        }
    }

    $rawMsg = $e->getMessage();
    $pesanManusiawi = 'Gagal memproses pesanan karena kendala sistem. Silakan periksa kembali keranjang Anda dan coba lagi.';

    if (preg_match('/Stok barang\s+(\d+)\s+tidak mencukupi/i', $rawMsg, $m)) {
        $idBrgKonflik = (int) $m[1];
        $namaBrgKonflik = $mapNamaBarang[$idBrgKonflik] ?? ('#' . $idBrgKonflik);
        $pesanManusiawi = 'Barang "' . $namaBrgKonflik . '" baru saja dipesan orang lain pada rentang waktu tersebut.';
    } elseif (preg_match('/Barang\s+(\d+)\s+tidak dapat disewa/i', $rawMsg, $m)) {
        $idBrgNonaktif = (int) $m[1];
        $namaBrgNonaktif = $mapNamaBarang[$idBrgNonaktif] ?? ('#' . $idBrgNonaktif);
        $pesanManusiawi = 'Maaf, barang "' . $namaBrgNonaktif . '" sedang tidak aktif dan tidak dapat disewa.';
    }

    error_log('[SnapRent Checkout] Rollback: ' . $rawMsg);
    flash_set('error', $pesanManusiawi);
    redirect('index.php');
}
