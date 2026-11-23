<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/ketersediaan.php';

$itemsSession = $_SESSION['keranjang']['items'] ?? [];

// Jika keranjang kosong
if (empty($itemsSession) || !is_array($itemsSession)) {
    ?>
    <div class="flex flex-col h-full justify-between gap-6" data-jumlah-keranjang="0">
        <div class="keadaan-kosong my-auto">
            <div class="keadaan-kosong__ikon" aria-hidden="true">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                </svg>
            </div>
            <h3 class="keadaan-kosong__judul">Keranjang masih kosong</h3>
            <p class="keadaan-kosong__teks">
                Tambahkan kamera atau perlengkapan pilihan Anda dari katalog, lalu atur durasi hari sewa di sini.
            </p>
        </div>

        <div class="ringkasan-keranjang-bawah space-y-3">
            <div class="flex items-center justify-between text-sm font-bold text-[#111111]">
                <span>SUBTOTAL</span>
                <span>Rp 0</span>
            </div>
            <button type="button" class="btn-utama w-full py-3 text-sm" disabled>
                Checkout Sekarang
            </button>
        </div>
    </div>
    <?php
    exit;
}

// ── Ambil & Sinkronkan Tanggal Ambil (Hari-H) ────────────────────────────────
$tzJakarta = new DateTimeZone('Asia/Jakarta');
$sekarang  = new DateTimeImmutable('now', $tzJakarta);
$hariIni   = $sekarang->format('Y-m-d');

$tanggalMulai = (string) ($_SESSION['keranjang']['tanggal_mulai'] ?? '');
if ($tanggalMulai === '' || $tanggalMulai < $hariIni) {
    $tanggalMulai = $hariIni;
}

$dtTanggal = DateTimeImmutable::createFromFormat('Y-m-d', $tanggalMulai, $tzJakarta) ?: $sekarang;
$dtMulai   = ($tanggalMulai === $hariIni) ? $sekarang : $dtTanggal->setTime(8, 0, 0);
$mulaiSql  = $dtMulai->format('Y-m-d H:i:s');

$_SESSION['keranjang']['tanggal_mulai'] = $tanggalMulai;
$_SESSION['keranjang']['mulai']         = $mulaiSql;
$_SESSION['keranjang']['mulai_input']   = $dtMulai->format('Y-m-d\TH:i');

// Ambil data terbaru semua barang di keranjang dari database & cek ketersediaan per durasi masing-masing
$idsBarang = array_values(array_filter(array_map('intval', array_keys($itemsSession)), fn($v) => $v > 0));
$daftarItemKeranjang  = [];
$totalPerkiraan       = 0.0;
$adaItemTidakTersedia = false;
$maksDurasiHari       = 1;

if (!empty($idsBarang)) {
    $placeholders = implode(',', array_fill(0, count($idsBarang), '?'));
    $stmt = $pdo->prepare("
        SELECT b.id_barang,
               b.nama_barang,
               b.foto_barang,
               b.harga_sewa_per_hari,
               b.stok,
               b.status_barang,
               k.nama_kategori
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

    foreach ($idsBarang as $idBrg) {
        if (!isset($mapDb[$idBrg])) {
            unset($_SESSION['keranjang']['items'][$idBrg]);
            continue;
        }
        $brg = $mapDb[$idBrg];
        $jumlahUnit  = max(1, (int) ($itemsSession[$idBrg]['jumlah'] ?? 1));
        $durasiItem  = max(1, (int) ($itemsSession[$idBrg]['hari'] ?? ($_SESSION['keranjang']['hari'] ?? 1)));
        
        // Simpan durasi per item ke sesi
        $_SESSION['keranjang']['items'][$idBrg]['hari'] = $durasiItem;
        if ($durasiItem > $maksDurasiHari) {
            $maksDurasiHari = $durasiItem;
        }

        $dtSelesaiItem = $dtMulai->modify('+' . $durasiItem . ' days');
        $selesaiItemSql = $dtSelesaiItem->format('Y-m-d H:i:s');

        $aktifMaster = ($brg['status_barang'] === 'aktif' && (int) $brg['stok'] > 0);
        $sisaStok    = ambil_sisa_stok($pdo, $idBrg, $mulaiSql, $selesaiItemSql);
        $tersedia    = ($aktifMaster && $sisaStok >= $jumlahUnit);

        if (!$tersedia) {
            $adaItemTidakTersedia = true;
        }

        $hargaSatuan = (float) $brg['harga_sewa_per_hari'];
        $subtotal    = $hargaSatuan * $jumlahUnit * $durasiItem;
        if ($tersedia) {
            $totalPerkiraan += $subtotal;
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

        $daftarItemKeranjang[] = [
            'id_barang'        => $idBrg,
            'nama_barang'      => $brg['nama_barang'],
            'nama_kategori'    => $brg['nama_kategori'],
            'jumlah'           => $jumlahUnit,
            'durasi_hari'      => $durasiItem,
            'rencana_kembali'  => $dtSelesaiItem->format('d/m/Y'),
            'sisa_stok'        => $sisaStok,
            'harga_satuan'     => $hargaSatuan,
            'subtotal'         => $subtotal,
            'tersedia'         => $tersedia,
            'ada_foto'         => $adaFotoFisik,
            'url_foto'         => $urlFoto,
        ];
    }
}

// Sinkronkan batas akhir transaksi terlama
$dtSelesaiMaks = $dtMulai->modify('+' . $maksDurasiHari . ' days');
$_SESSION['keranjang']['hari']          = $maksDurasiHari;
$_SESSION['keranjang']['selesai']       = $dtSelesaiMaks->format('Y-m-d H:i:s');
$_SESSION['keranjang']['selesai_input'] = $dtSelesaiMaks->format('Y-m-d\TH:i');

$jumlahAktual = hitung_item_keranjang();
$bisaCheckout = (!empty($daftarItemKeranjang) && !$adaItemTidakTersedia);
?>

<div class="flex flex-col h-full justify-between gap-5"
     data-jumlah-keranjang="<?= $jumlahAktual ?>"
     data-ada-bentrok="<?= $adaItemTidakTersedia ? '1' : '0' ?>">

    <div class="space-y-4">
        <!-- Pengaturan Tanggal Ambil (Hari-H) -->
        <div class="detail-produk__kotak-info space-y-2.5">
            <div class="flex items-center justify-between">
                <span class="font-bold text-xs text-[#111111] uppercase tracking-wider">Tanggal Pengambilan</span>
                <span class="badge badge-tersedia"><?= count($daftarItemKeranjang) ?> Item</span>
            </div>

            <div>
                <label for="keranjangInputTanggalMulai" class="form-label text-xs mb-1">Tanggal Ambil (Hari-H)</label>
                <input type="date"
                       id="keranjangInputTanggalMulai"
                       value="<?= e($tanggalMulai) ?>"
                       min="<?= e($hariIni) ?>"
                       class="form-input text-xs py-2 px-2.5 bg-white">
            </div>

            <p class="text-[11px] text-gray-500 leading-relaxed m-0">
                Jam sewa mulai dihitung otomatis saat Anda mengambil barang di toko dan Admin menekan <em>Mulai Sewa</em>. Durasi sewa diatur per masing-masing barang di bawah.
            </p>
        </div>

        <!-- Peringatan bila ada barang yang sudah tidak tersedia pada tanggal tersebut -->
        <?php if ($adaItemTidakTersedia): ?>
            <div class="peringatan-validasi-katalog text-xs" role="alert">
                Perhatian: Ada peralatan di keranjang yang sudah dipesan penyewa lain pada tanggal tersebut. Ubah durasi sewa atau hapus peralatan bertanda <strong>Tidak tersedia</strong> untuk melanjutkan ke Checkout.
            </div>
        <?php endif; ?>

        <!-- Daftar Item di Keranjang -->
        <div class="space-y-3">
            <?php foreach ($daftarItemKeranjang as $item): ?>
                <div class="item-keranjang <?= !$item['tersedia'] ? 'item-keranjang--bentrok' : '' ?>"
                     data-item-keranjang="<?= (int) $item['id_barang'] ?>">
                    <!-- Thumbnail Foto / Ikon Pengganti -->
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

                    <!-- Rincian Barang, Kontrol Jumlah Unit & Durasi Hari, & Subtotal -->
                    <div class="item-keranjang__info">
                        <div class="font-bold text-sm text-[#111111] leading-snug">
                            <?= e($item['nama_barang']) ?>
                        </div>
                        <div class="text-xs text-gray-600 mt-0.5">
                            <?= e(format_rupiah($item['harga_satuan'])) ?> / Hari
                        </div>

                        <!-- Kontrol Unit & Kontrol Hari -->
                        <div class="flex flex-wrap items-center justify-between gap-2 mt-2">
                            <div class="flex flex-wrap items-center gap-3">
                                <!-- Kontrol Jumlah Unit -->
                                <div class="inline-flex items-center gap-1">
                                    <span class="text-[11px] text-gray-500 mr-0.5">Unit:</span>
                                    <button type="button"
                                            data-ubah-qty="-1"
                                            data-id="<?= (int) $item['id_barang'] ?>"
                                            data-qty-sekarang="<?= (int) $item['jumlah'] ?>"
                                            class="btn-sekunder px-1.5 py-0.5 text-xs font-bold"
                                            aria-label="Kurangi jumlah unit">−</button>
                                    <span class="text-xs font-bold text-[#111111] min-w-[18px] text-center">
                                        <?= (int) $item['jumlah'] ?>
                                    </span>
                                    <button type="button"
                                            data-ubah-qty="1"
                                            data-id="<?= (int) $item['id_barang'] ?>"
                                            data-qty-sekarang="<?= (int) $item['jumlah'] ?>"
                                            class="btn-sekunder px-1.5 py-0.5 text-xs font-bold"
                                            <?= (int) $item['jumlah'] >= (int) $item['sisa_stok'] ? 'disabled' : '' ?>
                                            aria-label="Tambah jumlah unit">+</button>
                                </div>

                                <!-- Kontrol Durasi Hari Per Barang -->
                                <div class="inline-flex items-center gap-1">
                                    <span class="text-[11px] text-gray-500 mr-0.5">Sewa:</span>
                                    <button type="button"
                                            data-ubah-durasi-item="-1"
                                            data-id="<?= (int) $item['id_barang'] ?>"
                                            data-hari-sekarang="<?= (int) $item['durasi_hari'] ?>"
                                            class="btn-sekunder px-1.5 py-0.5 text-xs font-bold"
                                            <?= (int) $item['durasi_hari'] <= 1 ? 'disabled' : '' ?>
                                            aria-label="Kurangi durasi sewa">−</button>
                                    <span class="text-xs font-bold text-[#111111] min-w-[20px] text-center">
                                        <?= (int) $item['durasi_hari'] ?>h
                                    </span>
                                    <button type="button"
                                            data-ubah-durasi-item="1"
                                            data-id="<?= (int) $item['id_barang'] ?>"
                                            data-hari-sekarang="<?= (int) $item['durasi_hari'] ?>"
                                            class="btn-sekunder px-1.5 py-0.5 text-xs font-bold"
                                            aria-label="Tambah durasi sewa">+</button>
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="text-xs font-bold text-[#111111] block">
                                    <?= e(format_rupiah($item['subtotal'])) ?>
                                </span>
                                <span class="text-[10px] text-gray-500">
                                    Kembali: <?= e($item['rencana_kembali']) ?>
                                </span>
                            </div>
                        </div>

                        <?php if (!$item['tersedia']): ?>
                            <div class="mt-1.5">
                                <span class="badge badge-habis">
                                    <?= (int) $item['sisa_stok'] > 0
                                        ? 'Stok tersisa hanya ' . (int) $item['sisa_stok'] . ' unit'
                                        : 'Tidak tersedia pada durasi ini' ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tombol Hapus 1 Item -->
                    <button type="button"
                            class="item-keranjang__hapus"
                            data-hapus-keranjang="<?= (int) $item['id_barang'] ?>"
                            aria-label="Hapus <?= e($item['nama_barang']) ?> dari keranjang"
                            title="Hapus dari keranjang">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                        </svg>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Bagian Bawah: Total Perkiraan, Kosongkan, & Lanjut ke Checkout -->
    <div class="ringkasan-keranjang-bawah space-y-3 mt-4">
        <div class="flex items-center justify-between">
            <span class="text-sm font-semibold text-gray-700">
                TOTAL SEWA
            </span>
            <span class="text-base font-bold text-[#111111]"><?= e(format_rupiah($totalPerkiraan)) ?></span>
        </div>

        <?php if ($bisaCheckout): ?>
            <a href="<?= e(base_url('booking/checkout.php')) ?>"
               class="btn-utama w-full py-3 text-sm text-center">
                Checkout Sekarang
            </a>
        <?php else: ?>
            <button type="button" class="btn-utama w-full py-3 text-sm" disabled>
                Checkout Sekarang
            </button>
        <?php endif; ?>

        <div class="text-center">
            <button type="button"
                    data-kosongkan-keranjang="1"
                    class="text-xs font-medium text-gray-500 hover:text-red-600 hover:underline bg-transparent border-0 cursor-pointer py-1">
                Kosongkan Keranjang
            </button>
        </div>
    </div>
</div>
