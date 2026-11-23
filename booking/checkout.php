<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/ketersediaan.php';

$itemsSession = $_SESSION['keranjang']['items'] ?? [];
if (empty($itemsSession) || !is_array($itemsSession)) {
    flash_set('error', 'Keranjang Anda masih kosong. Silakan pilih peralatan kamera terlebih dahulu dari katalog.');
    redirect('index.php');
}

// ── 1. Ambil & Sinkronkan Jadwal / Durasi Sewa dari Keranjang ─────────────────
$tzJakarta = new DateTimeZone('Asia/Jakarta');
$sekarang  = new DateTimeImmutable('now', $tzJakarta);
$hariIni   = $sekarang->format('Y-m-d');

$tanggalMulai = (string) ($_SESSION['keranjang']['tanggal_mulai'] ?? $hariIni);
if ($tanggalMulai < $hariIni) {
    $tanggalMulai = $hariIni;
}

$dtTanggal = DateTimeImmutable::createFromFormat('Y-m-d', $tanggalMulai, $tzJakarta) ?: $sekarang;
$dtMulai   = ($tanggalMulai === $hariIni) ? $sekarang : $dtTanggal->setTime(8, 0, 0);
$mulaiSql  = $dtMulai->format('Y-m-d H:i:s');

$_SESSION['keranjang']['tanggal_mulai'] = $tanggalMulai;
$_SESSION['keranjang']['mulai']         = $mulaiSql;

// ── 2. Ambil Detail Barang & Cek Ulang Ketersediaan Per Item ──────────────────
$idsBarang = array_values(array_filter(array_map('intval', array_keys($itemsSession)), fn($v) => $v > 0));
sort($idsBarang);

if (empty($idsBarang)) {
    unset($_SESSION['keranjang']);
    flash_set('error', 'Keranjang Anda masih kosong. Silakan pilih peralatan kamera terlebih dahulu.');
    redirect('index.php');
}

$placeholders = implode(',', array_fill(0, count($idsBarang), '?'));
$stmt = $pdo->prepare("
    SELECT b.id_barang,
           b.nama_barang,
           b.foto_barang,
           b.harga_sewa_per_hari,
           b.stok,
           b.status_barang,
           k.nama_kategori,
           k.syarat_jaminan
      FROM barang b
      JOIN kategori k ON k.id_kategori = b.id_kategori
     WHERE b.id_barang IN ($placeholders)
     ORDER BY b.id_barang ASC
");
$stmt->execute($idsBarang);
$rowsDb = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mapDb = [];
foreach ($rowsDb as $r) {
    $mapDb[(int) $r['id_barang']] = $r;
}

$daftarBarangCheckout = [];
$totalPerkiraan       = 0.0;
$totalUnit            = 0;
$syaratJaminanUnik    = [];
$maksDurasiHari       = 1;

foreach ($idsBarang as $idBrg) {
    if (!isset($mapDb[$idBrg])) {
        unset($_SESSION['keranjang']['items'][$idBrg]);
        continue;
    }
    $brg        = $mapDb[$idBrg];
    $jumlahUnit = max(1, (int) ($itemsSession[$idBrg]['jumlah'] ?? 1));
    $durasiItem = max(1, (int) ($itemsSession[$idBrg]['hari'] ?? 1));
    if ($durasiItem > $maksDurasiHari) {
        $maksDurasiHari = $durasiItem;
    }

    $dtSelesaiItem  = $dtMulai->modify('+' . $durasiItem . ' days');
    $selesaiItemSql = $dtSelesaiItem->format('Y-m-d H:i:s');
    $sisaStok       = ambil_sisa_stok($pdo, $idBrg, $mulaiSql, $selesaiItemSql);

    if ($brg['status_barang'] !== 'aktif' || $sisaStok < $jumlahUnit) {
        flash_set(
            'error',
            'Barang "' . $brg['nama_barang'] . '" tidak tersedia pada jadwal yang dipilih.'
        );
        redirect('index.php');
    }

    $hargaSatuan     = (float) $brg['harga_sewa_per_hari'];
    $subtotal        = $hargaSatuan * $jumlahUnit * $durasiItem;
    $totalPerkiraan += $subtotal;
    $totalUnit      += $jumlahUnit;

    if (!empty($brg['syarat_jaminan'])) {
        $syaratJaminanUnik[$brg['syarat_jaminan']] = $brg['syarat_jaminan'];
    }

    $fotoRel      = trim((string) ($brg['foto_barang'] ?? ''));
    $adaFotoFisik = false;
    $urlFoto      = '';
    if ($fotoRel !== '') {
        $pathFisik = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($fotoRel, '/\\'));
        if (is_file($pathFisik)) {
            $adaFotoFisik = true;
            $urlFoto      = base_url($fotoRel);
        }
    }

    $daftarBarangCheckout[] = [
        'id_barang'        => $idBrg,
        'nama_barang'      => $brg['nama_barang'],
        'nama_kategori'    => $brg['nama_kategori'],
        'jumlah'           => $jumlahUnit,
        'durasi_hari'      => $durasiItem,
        'rencana_kembali'  => $dtSelesaiItem->format('d/m/Y'),
        'harga_satuan'     => $hargaSatuan,
        'subtotal'         => $subtotal,
        'ada_foto'         => $adaFotoFisik,
        'url_foto'         => $urlFoto,
    ];
}

$dtSelesai = $dtMulai->modify('+' . $maksDurasiHari . ' days');
$selesaiSql = $dtSelesai->format('Y-m-d H:i:s');
$lamaHari   = $maksDurasiHari;

$_SESSION['keranjang']['hari']    = $lamaHari;
$_SESSION['keranjang']['selesai'] = $selesaiSql;

if (empty($daftarBarangCheckout)) {
    unset($_SESSION['keranjang']);
    flash_set('error', 'Keranjang Anda kosong. Silakan pilih peralatan dari katalog.');
    redirect('index.php');
}

$teksSyaratJaminan = !empty($syaratJaminanUnik)
    ? implode('; ', $syaratJaminanUnik)
    : '1 KTP asli + 2 kartu identitas pendukung';

// Ambil nilai form lama bila sebelumnya gagal validasi
$formLama = $_SESSION['_checkout_old'] ?? [];
unset($_SESSION['_checkout_old']);

$oldNama   = (string) ($formLama['nama_penyewa'] ?? '');
$oldTelp   = (string) ($formLama['no_telepon'] ?? '');
$oldMetode = (string) ($formLama['jenis_pembayaran'] ?? 'transfer');
if ($oldMetode !== 'qris') {
    $oldMetode = 'transfer';
}
$oldSkema  = (string) ($formLama['skema_pembayaran'] ?? 'lunas');
$estimasiDp = round($totalPerkiraan * 0.5);

$judul_halaman = 'Checkout & Pembayaran';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="wadah-pelanggan max-w-4xl space-y-6">

    <!-- Header Halaman Checkout -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-[#111111] tracking-tight m-0">
                Checkout &amp; Pembayaran
            </h1>
            <p class="text-sm text-gray-600 mt-1 m-0">
                Lengkapi data pemesan dan unggah bukti pembayaran untuk memproses penyewaan kamera Anda.
            </p>
        </div>
        <a href="<?= e(base_url('index.php')) ?>" class="btn-sekunder text-xs self-start sm:self-auto">
            ← Kembali ke Katalog
        </a>
    </div>

    <form id="formCheckout"
          action="<?= e(base_url('booking/proses_checkout.php')) ?>"
          method="POST"
          enctype="multipart/form-data"
          class="space-y-6"
          novalidate>
        <?= csrf_field() ?>

        <!-- ══════════════════════════════════════════════════════════════════
             1. INFORMASI PEMESAN (Sesuai Wireframe Checkout & Pembayaran.png)
             ═══════════════════════════════════════════════════════════════ -->
        <section class="checkout-section-card space-y-5" aria-labelledby="judulStep1">
            <div class="flex items-start gap-3.5">
                <span class="checkout-step-badge" aria-hidden="true">1</span>
                <div>
                    <h2 id="judulStep1" class="text-lg font-bold text-[#111111] m-0">Informasi Pemesan</h2>
                    <p class="text-xs text-gray-500 mt-0.5 m-0">
                        Data ini digunakan untuk identifikasi penyewa dan konfirmasi pesanan via WhatsApp.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="nama_penyewa" class="form-label">
                        Nama Lengkap <span class="text-red-600">*</span>
                    </label>
                    <input type="text"
                           id="nama_penyewa"
                           name="nama_penyewa"
                           value="<?= e($oldNama) ?>"
                           minlength="3"
                           maxlength="100"
                           placeholder="Masukkan nama lengkap sesuai KTP"
                           autocomplete="name"
                           class="form-input"
                           required>
                    <p id="errNamaPenyewa" class="text-xs text-red-600 mt-1 hidden" role="alert"></p>
                </div>

                <div>
                    <label for="no_telepon" class="form-label">
                        Nomor WhatsApp / Telepon <span class="text-red-600">*</span>
                    </label>
                    <input type="tel"
                           id="no_telepon"
                           name="no_telepon"
                           value="<?= e($oldTelp) ?>"
                           placeholder="Contoh: 081234567890"
                           autocomplete="tel"
                           class="form-input"
                           required>
                    <p class="text-[11px] text-gray-500 mt-1 m-0">
                        Hanya angka (boleh diawali +), panjang 8–14 digit angka.
                    </p>
                    <p id="errNoTelepon" class="text-xs text-red-600 mt-1 hidden" role="alert"></p>
                </div>
            </div>

            <!-- Pilihan Skema Pembayaran (LUNAS atau DP 50% sesuai Wireframe) -->
            <div class="pt-1">
                <span class="form-label">Skema Pembayaran</span>
                <div class="flex flex-wrap items-center gap-3 mt-1.5" role="radiogroup" aria-label="Skema Pembayaran">
                    <label class="pilihan-skema-pill">
                        <input type="radio"
                               name="skema_pembayaran"
                               value="lunas"
                               class="sr-only"
                               <?= $oldSkema !== 'dp_50' ? 'checked' : '' ?>>
                        <span>LUNAS (100%)</span>
                    </label>
                    <span class="text-xs text-gray-500">atau</span>
                    <label class="pilihan-skema-pill">
                        <input type="radio"
                               name="skema_pembayaran"
                               value="dp_50"
                               class="sr-only"
                               <?= $oldSkema === 'dp_50' ? 'checked' : '' ?>>
                        <span>DP 50%</span>
                    </label>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════════════
             2. RINGKASAN PESANAN
             ═══════════════════════════════════════════════════════════════ -->
        <section class="checkout-section-card space-y-5" aria-labelledby="judulStep2">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-start gap-3.5">
                    <span class="checkout-step-badge" aria-hidden="true">2</span>
                    <div>
                        <h2 id="judulStep2" class="text-lg font-bold text-[#111111] m-0">Ringkasan Pesanan</h2>
                        <p class="text-xs text-gray-500 mt-0.5 m-0">
                            Tanggal Ambil (Hari-H): <strong class="text-[#111111]"><?= e($dtMulai->format('d/m/Y')) ?></strong>
                            · Durasi sewa diatur per barang (Pengembalian bertahap).
                        </p>
                    </div>
                </div>
                <span class="badge badge-menunggu"><?= (int) $totalUnit ?> Unit</span>
            </div>

            <!-- Daftar Barang yang Disewa -->
            <div class="space-y-3">
                <?php foreach ($daftarBarangCheckout as $item): ?>
                    <div class="item-keranjang">
                        <div class="item-keranjang__thumb" aria-hidden="true">
                            <?php if ($item['ada_foto']): ?>
                                <img src="<?= e($item['url_foto']) ?>" alt="<?= e($item['nama_barang']) ?>">
                            <?php else: ?>
                                <svg class="w-7 h-7 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/>
                                </svg>
                            <?php endif; ?>
                        </div>

                        <div class="item-keranjang__info">
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">
                                <?= e($item['nama_kategori']) ?>
                            </div>
                            <div class="font-bold text-sm text-[#111111]">
                                <?= e($item['nama_barang']) ?>
                            </div>
                            <div class="text-xs text-gray-600 mt-0.5">
                                <?= e(format_rupiah($item['harga_satuan'])) ?> / Hari × <?= (int) $item['jumlah'] ?> unit × <?= (int) $item['durasi_hari'] ?> hari
                                <span class="text-[11px] text-gray-500 block">Jadwal kembali: <?= e($item['rencana_kembali']) ?></span>
                            </div>
                        </div>

                        <div class="text-right font-bold text-sm text-[#111111] whitespace-nowrap">
                            <?= e(format_rupiah($item['subtotal'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Rincian Total Perkiraan -->
            <div class="detail-produk__kotak-info space-y-2"
                 id="ringkasanNominalCheckout"
                 data-total="<?= (float) $totalPerkiraan ?>"
                 data-dp="<?= (float) $estimasiDp ?>">
                <div class="flex items-center justify-between text-xs text-gray-600">
                    <span>Total Biaya Sewa Peralatan</span>
                    <span class="font-semibold text-[#111111]"><?= e(format_rupiah($totalPerkiraan)) ?></span>
                </div>
                <div id="barisDpCheckout"
                     class="flex items-center justify-between text-xs text-gray-600 <?= $oldSkema === 'dp_50' ? '' : 'hidden' ?>">
                    <span>Skema DP 50% (Sisa dilunasi saat pengambilan)</span>
                    <span class="font-semibold text-[#111111]"><?= e(format_rupiah($estimasiDp)) ?></span>
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-gray-200 text-sm">
                    <span class="font-bold text-[#111111]">Total Pembayaran Awal</span>
                    <span id="nominalBayarAwalTampil" class="font-bold text-base text-[#111111]">
                        <?= e(format_rupiah($oldSkema === 'dp_50' ? $estimasiDp : $totalPerkiraan)) ?>
                    </span>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════════════
             3. METODE PEMBAYARAN & UNGGAH BUKTI (Verifikasi Manual oleh Admin)
             ═══════════════════════════════════════════════════════════════ -->
        <section class="checkout-section-card space-y-5" aria-labelledby="judulStep3">
            <div class="flex items-start gap-3.5">
                <span class="checkout-step-badge" aria-hidden="true">3</span>
                <div>
                    <h2 id="judulStep3" class="text-lg font-bold text-[#111111] m-0">Metode Pembayaran &amp; Bukti Transfer</h2>
                    <p class="text-xs text-gray-500 mt-0.5 m-0">
                        Pilih metode transfer, lakukan pembayaran sesuai nominal, dan unggah bukti transfer/QRIS Anda untuk diperiksa oleh Admin.
                    </p>
                </div>
            </div>

            <!-- Pilihan Metode Pembayaran: Hanya Transfer Bank & QRIS (Tunai Dihapus) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" role="radiogroup" aria-label="Pilih Metode Pembayaran">
                <!-- Transfer Bank -->
                <label class="pilihan-radio-kartu <?= $oldMetode === 'transfer' ? 'pilihan-radio-kartu--aktif' : '' ?>">
                    <input type="radio"
                           name="jenis_pembayaran"
                           value="transfer"
                           class="mt-1 accent-[#111111]"
                           <?= $oldMetode === 'transfer' ? 'checked' : '' ?>>
                    <div>
                        <div class="font-bold text-sm text-[#111111] flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                            Transfer Bank
                        </div>
                        <p class="text-xs text-gray-600 mt-0.5 m-0">
                            Transfer ke rekening resmi BCA SnapRent dan unggah bukti transfer.
                        </p>
                    </div>
                </label>

                <!-- QRIS -->
                <label class="pilihan-radio-kartu <?= $oldMetode === 'qris' ? 'pilihan-radio-kartu--aktif' : '' ?>">
                    <input type="radio"
                           name="jenis_pembayaran"
                           value="qris"
                           class="mt-1 accent-[#111111]"
                           <?= $oldMetode === 'qris' ? 'checked' : '' ?>>
                    <div>
                        <div class="font-bold text-sm text-[#111111] flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                            </svg>
                            QRIS (Transfer QR)
                        </div>
                        <p class="text-xs text-gray-600 mt-0.5 m-0">
                            Pindai kode QRIS toko dari dompet digital atau m-banking apa saja.
                        </p>
                    </div>
                </label>
            </div>

            <!-- Panel Detail Pembayaran: Rekening Bank -->
            <div id="panelTransferBank" class="p-4 rounded-xl bg-gray-50 border border-gray-200 text-xs space-y-3 <?= $oldMetode === 'transfer' ? '' : 'hidden' ?>">
                <div class="font-bold text-[#111111] flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    Rekening Resmi SnapRent:
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl bg-white border border-gray-200 shadow-sm">
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-gray-400 tracking-wider">Bank BCA (Kode: 014)</span>
                        <span class="font-mono font-extrabold text-base sm:text-lg text-[#111111] tracking-wider select-all">8120-4459-10</span>
                        <span class="block text-gray-600 text-xs mt-0.5">a.n. <strong>SnapRent Official</strong></span>
                    </div>
                    <button type="button"
                            id="btnSalinRekening"
                            data-salin="8120445910"
                            class="btn-sekunder text-xs py-2 px-3 self-start sm:self-auto flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <span>Salin No. Rek</span>
                    </button>
                </div>
                <p class="text-[11px] text-gray-500 m-0">
                    Silakan transfer tepat sebesar nominal pembayaran awal yang tertera di atas.
                </p>
            </div>

            <!-- Panel Detail Pembayaran: QRIS -->
            <div id="panelQris" class="p-4 rounded-xl bg-gray-50 border border-gray-200 text-xs space-y-3 <?= $oldMetode === 'qris' ? '' : 'hidden' ?>">
                <div class="font-bold text-[#111111] flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    Pindai QRIS SnapRent:
                </div>
                <div class="flex flex-col sm:flex-row items-center gap-4 p-3.5 rounded-xl bg-white border border-gray-200 shadow-sm">
                    <div class="w-36 h-36 shrink-0 bg-white border border-gray-200 rounded-xl p-2 flex items-center justify-center">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=SnapRent-Official-Pembayaran"
                             alt="QRIS SnapRent"
                             class="w-full h-full object-contain">
                    </div>
                    <div class="space-y-1.5 text-center sm:text-left flex-1">
                        <span class="text-sm font-bold text-[#111111] block">SnapRent Official</span>
                        <span class="text-xs text-gray-500 block font-mono">NMID: ID1020304050607</span>
                        <p class="text-[11px] text-gray-600 leading-relaxed m-0">
                            Dapat dipindai dari seluruh aplikasi perbankan (BCA Mobile, Livin' by Mandiri, BRImo, BNI Mobile) dan dompet digital (GoPay, OVO, Dana, ShopeePay).
                        </p>
                    </div>
                </div>
            </div>

            <!-- Formulir Unggah Bukti Pembayaran -->
            <div class="pt-2 border-t border-gray-100 space-y-2">
                <label for="bukti_pembayaran" class="form-label flex items-center justify-between">
                    <span>Unggah Bukti Pembayaran <span class="text-red-600">*</span></span>
                    <span class="text-[11px] text-gray-400 font-normal">Maksimal 5 MB (JPG, PNG, WEBP, PDF)</span>
                </label>
                <div class="relative">
                    <input type="file"
                           id="bukti_pembayaran"
                           name="bukti_pembayaran"
                           accept="image/jpeg,image/png,image/webp,application/pdf"
                           class="form-input py-2 text-xs cursor-pointer file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#111111] file:text-white hover:file:bg-black"
                           required>
                </div>
                <p class="text-[11px] text-gray-500 mt-1 m-0">
                    Unggah struk atau tangkapan layar bukti transfer Anda. Admin akan memeriksa keaslian bukti pembayaran ini sebelum menyetujui pesanan Anda.
                </p>
                <p id="errBuktiPembayaran" class="text-xs text-red-600 mt-1 hidden" role="alert"></p>

                <!-- Kotak Pratinjau Bukti Pembayaran -->
                <div id="previewBuktiContainer" class="hidden mt-3 p-3 rounded-xl bg-gray-50 border border-gray-200 items-center gap-3">
                    <img id="previewBuktiGambar" src="" alt="Pratinjau Bukti" class="w-16 h-16 object-cover rounded-lg border border-gray-200 shrink-0 hidden">
                    <div id="previewBuktiFile" class="text-xs text-gray-700 truncate flex-1"></div>
                    <button type="button"
                            id="btnHapusBukti"
                            class="text-xs font-semibold text-red-600 hover:text-red-800 shrink-0">
                        Ganti File
                    </button>
                </div>
            </div>

            <!-- Informasi Jaminan Fisik & Verifikasi -->
            <div class="detail-produk__kotak-info space-y-1.5">
                <div class="font-bold text-xs text-[#111111] uppercase tracking-wider">
                    Informasi Jaminan Identitas &amp; Verifikasi Admin
                </div>
                <p class="text-xs text-gray-600 leading-relaxed m-0">
                    • <strong>Syarat Jaminan Fisik:</strong> Penyewa wajib menyerahkan fisik
                    <strong class="text-[#111111]"><?= e($teksSyaratJaminan) ?></strong> secara langsung kepada Admin di lokasi toko saat serah terima alat.<br>
                    • <strong>Pemeriksaan Bukti Pembayaran:</strong> Admin akan mencocokkan mutasi rekening dengan file bukti pembayaran yang Anda unggah sebelum pesanan disetujui.
                </p>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════════════
             4. SYARAT & KETENTUAN + TOMBOL SUBMIT
             ═══════════════════════════════════════════════════════════════ -->
        <section class="checkout-section-card space-y-4" aria-labelledby="judulSyarat">
            <h2 id="judulSyarat" class="text-lg font-bold text-[#111111] m-0">Syarat &amp; Ketentuan</h2>

            <div class="text-xs text-gray-700 space-y-2.5 leading-relaxed">
                <div>
                    <strong class="text-[#111111]">• Jaminan Identitas Fisik</strong><br>
                    Penyewa wajib menyerahkan <strong><?= e($teksSyaratJaminan) ?></strong> (sesuai nama pemesan) saat pengambilan barang di lokasi toko.
                </div>
                <div>
                    <strong class="text-[#111111]">• Pengembalian &amp; Denda Progresif</strong><br>
                    - Toleransi keterlambatan maksimal 1 jam dari jadwal pengembalian.<br>
                    - Keterlambatan lebih dari 1 sampai 3 jam dikenakan denda tetap sebesar Rp 50.000.<br>
                    - Keterlambatan lebih dari 3 jam dihitung sebagai denda sewa 1 hari penuh.
                </div>
                <div>
                    <strong class="text-[#111111]">• Kondisi &amp; Tanggung Jawab Barang</strong><br>
                    - Penyewa dan admin wajib melakukan pengecekan fungsi serta kondisi fisik alat bersama-sama saat serah terima.<br>
                    - Segala bentuk kerusakan fisik atau kehilangan unit/aksesoris selama masa sewa menjadi tanggung jawab penuh penyewa untuk biaya perbaikan/penggantian.
                </div>
                <div>
                    <strong class="text-[#111111]">• Penggunaan Barang &amp; Verifikasi</strong><br>
                    - Unit kamera dan aksesori yang disewa dilarang dipindahtangankan atau disewakan kembali kepada pihak ketiga.<br>
                    - Pesanan dianggap sah setelah Admin memverifikasi pesanan dan pembayaran Anda.
                </div>
            </div>

            <div class="pt-2 border-t border-gray-200 space-y-4">
                <label class="flex items-start gap-2.5 cursor-pointer text-xs text-gray-800 font-medium">
                    <input type="checkbox"
                           id="setuju_syarat"
                           name="setuju_syarat"
                           value="1"
                           checked
                           required
                           class="mt-0.5 w-4 h-4 accent-[#111111]">
                    <span>Saya telah membaca dan menyetujui Syarat &amp; Ketentuan yang berlaku di SnapRent.</span>
                </label>
                <p id="errSetujuSyarat" class="text-xs text-red-600 hidden" role="alert"></p>

                <button type="submit"
                        id="btnSubmitCheckout"
                        class="btn-utama w-full sm:w-auto px-8 py-3.5 text-sm">
                    <span>PROSES PESANAN SEKARANG</span>
                    <span aria-hidden="true">→</span>
                </button>
            </div>
        </section>

    </form>
</div>

<script src="<?= e(base_url('assets/js/keranjang.js')) ?>" defer></script>
<script src="<?= e(base_url('assets/js/checkout.js')) ?>" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
