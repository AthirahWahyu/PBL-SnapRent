<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/ketersediaan.php';
require_once __DIR__ . '/../includes/koneksi.php';

wajib_login();

$idTransaksi = ambil_int('id', 'get');
if ($idTransaksi === null) {
    flash_set('error', 'ID transaksi tidak valid.');
    redirect('transaksi/list.php');
}

try {
    // 1. Ambil data transaksi + penyewa + admin + aturan denda
    $stmt = $pdo->prepare('
        SELECT
            t.*,
            p.nama_penyewa,
            p.no_telepon,
            a.nama_lengkap AS nama_admin,
            d.keterangan   AS keterangan_denda
        FROM transaksi t
        JOIN penyewa p ON p.id_penyewa = t.id_penyewa
        LEFT JOIN admin a ON a.id_admin = t.id_admin
        LEFT JOIN denda d ON d.id_denda = t.id_denda
        WHERE t.id_transaksi = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $idTransaksi]);
    $transaksi = $stmt->fetch();

    if (!$transaksi) {
        flash_set('error', 'Data transaksi tidak ditemukan.');
        redirect('transaksi/list.php');
    }

    // 2. Ambil daftar barang pada detail_transaksi
    $stmtDetail = $pdo->prepare('
        SELECT
            dt.id_barang,
            dt.jumlah,
            dt.harga_satuan,
            dt.subtotal,
            b.nama_barang,
            b.foto_barang,
            b.status_barang,
            k.nama_kategori
        FROM detail_transaksi dt
        JOIN barang b ON b.id_barang = dt.id_barang
        JOIN kategori k ON k.id_kategori = b.id_kategori
        WHERE dt.id_transaksi = :id
        ORDER BY dt.id_barang ASC
    ');
    $stmtDetail->execute([':id' => $idTransaksi]);
    $daftarBarang = $stmtDetail->fetchAll();

    // 3. Ambil daftar jaminan yang sudah tersimpan (urutkan KTP di baris pertama)
    $stmtJaminan = $pdo->prepare("
        SELECT id_jaminan, jenis_jaminan, no_identitas, status_jaminan, catatan
        FROM jaminan
        WHERE id_transaksi = :id
        ORDER BY CASE WHEN jenis_jaminan = 'KTP' THEN 0 ELSE 1 END, id_jaminan ASC
    ");
    $stmtJaminan->execute([':id' => $idTransaksi]);
    $daftarJaminan = $stmtJaminan->fetchAll();
} catch (PDOException $e) {
    error_log('[SnapRent] Error transaksi/detail.php: ' . $e->getMessage());
    flash_set('error', 'Gagal memuat detail transaksi.');
    redirect('transaksi/list.php');
}

// ── Hitung durasi sewa (hari) ────────────────────────────────────────────────
$tsMulai   = strtotime((string) $transaksi['waktu_mulai_sewa']);
$tsKembali = strtotime((string) $transaksi['waktu_rencana_kembali']);
$lamaHari  = max(1, (int) ceil(($tsKembali - $tsMulai) / 86400));

// ── Evaluasi kelengkapan jaminan & syarat status ────────────────────────────
$jumlahJaminan = count($daftarJaminan);
$jumlahKtp     = 0;
foreach ($daftarJaminan as $jm) {
    if ($jm['jenis_jaminan'] === 'KTP' && trim((string) $jm['no_identitas']) !== '') {
        $jumlahKtp++;
    }
}
$jaminanLengkap = ($jumlahJaminan >= 3 && $jumlahKtp >= 1);

$statusTrans = (string) $transaksi['status_transaksi'];
$statusBayar = (string) $transaksi['status_pembayaran'];
$skemaBayar  = (string) ($transaksi['skema_pembayaran'] ?? 'lunas');
$isDp50      = ($skemaBayar === 'dp_50');
$sudahLunas  = ($statusBayar === 'lunas');
$kodeOrder   = sprintf('#SR-%04d', $idTransaksi);

// ── Aturan ketersediaan tombol aksi beserta alasan bila nonaktif ─────────────
// 1. Simpan Jaminan
$bolehSimpanJaminan = in_array($statusTrans, ['menunggu_verifikasi', 'menunggu_diambil', 'disewa'], true);
$alasanJaminan      = $bolehSimpanJaminan
    ? 'Simpan atau perbarui 3 kartu identitas fisik penyewa.'
    : 'Tidak dapat diubah karena transaksi sudah berstatus ' . ucfirst($statusTrans) . '.';

// 2. Setujui (menunggu_verifikasi -> menunggu_diambil)
$bolehSetujui = ($statusTrans === 'menunggu_verifikasi');
if ($statusTrans !== 'menunggu_verifikasi') {
    $alasanSetujui = 'Nonaktif: Hanya berlaku untuk transaksi berstatus Menunggu Verifikasi (status saat ini: ' . ucfirst(str_replace('_', ' ', $statusTrans)) . ').';
} else {
    $alasanSetujui = 'Verifikasi bukti bayar awal dan setujui pesanan menjadi Menunggu Diambil.';
}

// 3. Tandai Lunas (Verifikasi Pembayaran Cash di Toko saat Ambil Barang)
$bolehTandaiLunas = (!$sudahLunas && in_array($statusTrans, ['menunggu_diambil', 'disewa'], true));
if ($sudahLunas) {
    $alasanLunas = 'Nonaktif: Pembayaran sudah berstatus Lunas.';
} elseif ($statusTrans === 'menunggu_verifikasi') {
    $alasanLunas = 'Nonaktif: Setujui booking terlebih dahulu sebelum mencatat pelunasan di toko.';
} elseif (in_array($statusTrans, ['dibatalkan', 'selesai'], true)) {
    $alasanLunas = 'Nonaktif: Transaksi sudah berstatus ' . ucfirst($statusTrans) . '.';
} else {
    $alasanLunas = 'Catat pelunasan sisa tagihan 50% yang dibayarkan tunai/transfer di toko.';
}

// 4. Mulai Sewa / Serahkan Barang (menunggu_diambil -> disewa)
$bolehMulaiSewa = ($statusTrans === 'menunggu_diambil' && $jaminanLengkap && $sudahLunas);
if ($statusTrans === 'menunggu_verifikasi') {
    $alasanMulaiSewa = 'Nonaktif: Pesanan harus Disetujui terlebih dahulu sebelum barang diserahkan.';
} elseif ($statusTrans === 'disewa') {
    $alasanMulaiSewa = 'Nonaktif: Barang sudah diserahkan dan sedang dalam masa sewa.';
} elseif (in_array($statusTrans, ['selesai', 'dibatalkan'], true)) {
    $alasanMulaiSewa = 'Nonaktif: Transaksi sudah berstatus ' . ucfirst($statusTrans) . '.';
} elseif (!$jaminanLengkap && !$sudahLunas) {
    $alasanMulaiSewa = 'Nonaktif: Jaminan fisik belum lengkap (wajib 3 kartu termasuk 1 KTP) dan pembayaran belum Lunas.';
} elseif (!$jaminanLengkap) {
    $alasanMulaiSewa = 'Nonaktif: Jaminan fisik belum lengkap (wajib minimal 3 kartu termasuk 1 KTP).';
} elseif (!$sudahLunas) {
    $alasanMulaiSewa = 'Nonaktif: Verifikasi bayar lunas terlebih dahulu sebelum barang diserahkan.';
} else {
    $alasanMulaiSewa = 'Syarat terpenuhi (jaminan lengkap & lunas). Serahkan unit kamera kepada penyewa.';
}

// 5. Batalkan (hanya dari menunggu_verifikasi atau menunggu_diambil)
$bolehBatalkan = in_array($statusTrans, ['menunggu_verifikasi', 'menunggu_diambil'], true);
if (!$bolehBatalkan) {
    $alasanBatalkan = 'Nonaktif: Pembatalan hanya diizinkan dari status Menunggu Verifikasi atau Menunggu Diambil.';
} else {
    $alasanBatalkan = 'Batalkan pesanan ini bila penyewa membatalkan atau tidak memenuhi syarat.';
}

// Siapkan nilai default untuk 3 baris form jaminan
$barisFormJaminan = [
    0 => [
        'jenis_jaminan' => 'KTP',
        'no_identitas'  => $daftarJaminan[0]['no_identitas'] ?? '',
        'catatan'       => $daftarJaminan[0]['catatan'] ?? '',
    ],
    1 => [
        'jenis_jaminan' => $daftarJaminan[1]['jenis_jaminan'] ?? 'SIM',
        'no_identitas'  => $daftarJaminan[1]['no_identitas'] ?? '',
        'catatan'       => $daftarJaminan[1]['catatan'] ?? '',
    ],
    2 => [
        'jenis_jaminan' => $daftarJaminan[2]['jenis_jaminan'] ?? 'KTM',
        'no_identitas'  => $daftarJaminan[2]['no_identitas'] ?? '',
        'catatan'       => $daftarJaminan[2]['catatan'] ?? '',
    ],
];
$opsiJenisJaminan = ['KTP', 'SIM', 'KTM', 'STNK', 'Paspor', 'Kartu Pelajar', 'Lainnya'];

$badgeStatusTrans = match ($statusTrans) {
    'menunggu_verifikasi' => 'badge-kuning',
    'menunggu_diambil'    => 'badge-disetujui',
    'disewa'              => 'badge-disewa',
    'selesai'             => 'badge-selesai',
    'dibatalkan'          => 'badge-dibatalkan',
    default               => 'badge-normal',
};

$judul_halaman = 'Detail Transaksi ' . $kodeOrder;
require_once __DIR__ . '/../includes/header_admin.php';
?>

<!-- ══════════════════════════════════════════════════════════════════════════
     HEADER HALAMAN
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-[#111111] tracking-tight">
                Transaksi <?= e($kodeOrder) ?>
            </h1>
            <span class="badge <?= $badgeStatusTrans ?> text-xs px-3 py-1">
                <?= e(ucfirst(str_replace('_', ' ', $statusTrans))) ?>
            </span>
            <span class="badge <?= $sudahLunas ? 'badge-lunas' : 'badge-belum-dibayar' ?> text-xs px-3 py-1">
                <?= $sudahLunas ? 'Lunas' : e(ucfirst(str_replace('_', ' ', $statusBayar))) ?>
            </span>
        </div>
        <p class="text-xs text-gray-500 mt-1">
            Waktu Pemesanan: <strong><?= e(format_tanggal($transaksi['waktu_pemesanan'])) ?></strong>
            <?php if (!empty($transaksi['nama_admin'])): ?>
                · Diproses oleh Admin: <strong><?= e($transaksi['nama_admin']) ?></strong>
            <?php endif; ?>
        </p>
    </div>

    <div class="flex items-center gap-2">
        <?php if ($statusTrans === 'disewa'): ?>
            <a href="<?= e(base_url('pengembalian/kembali.php?id=' . $idTransaksi)) ?>"
               class="btn-utama">
                ↩ Proses Pengembalian Barang
            </a>
        <?php endif; ?>
        <a href="<?= e(base_url('transaksi/list.php')) ?>" class="btn-sekunder">
            &larr; Kembali ke Daftar
        </a>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     BARIS 1: INFO PENYEWA, JADWAL SEWA, & PEMBAYARAN
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

    <!-- 1. Data Penyewa -->
    <div class="card-admin">
        <h2 class="text-sm font-bold text-[#111111] uppercase tracking-wider mb-3">Data Penyewa</h2>
        <div class="space-y-2.5 text-xs">
            <div class="flex justify-between py-1.5 border-b border-gray-100">
                <span class="text-gray-400">Nama Penyewa</span>
                <span class="font-bold text-gray-900"><?= e($transaksi['nama_penyewa']) ?></span>
            </div>
            <div class="flex justify-between py-1.5 border-b border-gray-100">
                <span class="text-gray-400">No. Telepon / WA</span>
                <span class="font-semibold text-gray-800"><?= e($transaksi['no_telepon']) ?></span>
            </div>
            <div class="flex justify-between py-1.5">
                <span class="text-gray-400">ID Penyewa</span>
                <span class="font-mono text-gray-600">#<?= e((string) $transaksi['id_penyewa']) ?></span>
            </div>
        </div>
    </div>

    <!-- 2. Jadwal Penyewaan -->
    <div class="card-admin">
        <h2 class="text-sm font-bold text-[#111111] uppercase tracking-wider mb-3">Jadwal Penyewaan</h2>
        <div class="space-y-2.5 text-xs">
            <div class="flex justify-between py-1.5 border-b border-gray-100">
                <span class="text-gray-400">Mulai Sewa</span>
                <span class="font-semibold text-gray-900"><?= e(format_tanggal($transaksi['waktu_mulai_sewa'])) ?></span>
            </div>
            <div class="flex justify-between py-1.5 border-b border-gray-100">
                <span class="text-gray-400">Rencana Kembali</span>
                <span class="font-semibold text-gray-900"><?= e(format_tanggal($transaksi['waktu_rencana_kembali'])) ?></span>
            </div>
            <div class="flex justify-between py-1.5 border-b border-gray-100">
                <span class="text-gray-400">Aktual Kembali</span>
                <span class="font-semibold text-gray-900">
                    <?= !empty($transaksi['waktu_aktual_kembali']) ? e(format_tanggal($transaksi['waktu_aktual_kembali'])) : '-' ?>
                </span>
            </div>
            <div class="flex justify-between py-1">
                <span class="text-gray-400">Durasi Sewa</span>
                <span class="font-bold text-[#111111]"><?= e((string) $lamaHari) ?> Hari</span>
            </div>
        </div>
    </div>

    <!-- 3. Ringkasan Pembayaran & Total -->
    <div class="card-admin">
        <h2 class="text-sm font-bold text-[#111111] uppercase tracking-wider mb-3">Pembayaran & Tagihan</h2>
        <div class="space-y-2.5 text-xs">
            <div class="flex justify-between py-1.5 border-b border-gray-100">
                <span class="text-gray-400">Metode Pembayaran</span>
                <span class="font-semibold text-gray-900"><?= e(ucfirst((string) $transaksi['jenis_pembayaran'])) ?></span>
            </div>
            <div class="flex justify-between py-1.5 border-b border-gray-100">
                <span class="text-gray-400">Status Pembayaran</span>
                <span class="badge <?= $sudahLunas ? 'badge-lunas' : 'badge-belum-dibayar' ?>">
                    <?= $sudahLunas ? 'Lunas' : e(ucfirst(str_replace('_', ' ', $statusBayar))) ?>
                </span>
            </div>
            <div class="flex justify-between py-1.5 border-b border-gray-100 items-center">
                <span class="text-gray-400">Bukti Pembayaran</span>
                <span>
                    <?php if (!empty($transaksi['bukti_pembayaran']) && $transaksi['bukti_pembayaran'] !== 'verifikasi_manual'): ?>
                        <a href="<?= e(base_url($transaksi['bukti_pembayaran'])) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-bold text-blue-600 hover:text-blue-800 hover:underline">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            Periksa Bukti ↗
                        </a>
                    <?php else: ?>
                        <span class="text-gray-400 italic">Belum ada file</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if ((float) $transaksi['nominal_denda'] > 0): ?>
                <div class="flex justify-between py-1.5 border-b border-gray-100">
                    <span class="text-gray-400">Denda Keterlambatan</span>
                    <span class="font-semibold text-red-600"><?= e(format_rupiah($transaksi['nominal_denda'])) ?></span>
                </div>
            <?php endif; ?>
            <div class="flex justify-between py-1.5 items-center">
                <span class="text-gray-500 font-semibold">Total Tagihan</span>
                <span class="text-base font-bold text-[#111111]"><?= e(format_rupiah($transaksi['total_tagihan'])) ?></span>
            </div>
            <?php if (!empty($transaksi['bukti_pembayaran']) && $transaksi['bukti_pembayaran'] !== 'verifikasi_manual'): ?>
                <?php
                $ext = strtolower(pathinfo((string) $transaksi['bukti_pembayaran'], PATHINFO_EXTENSION));
                $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
                ?>
                <?php if ($isImg): ?>
                    <div class="pt-2 border-t border-gray-100">
                        <span class="text-[11px] text-gray-400 block mb-1.5 font-medium">Pratinjau Bukti Transfer:</span>
                        <a href="<?= e(base_url($transaksi['bukti_pembayaran'])) ?>" target="_blank" rel="noopener noreferrer" class="block group relative overflow-hidden rounded-xl border border-gray-200 bg-gray-50 max-h-36 flex items-center justify-center p-1">
                            <img src="<?= e(base_url($transaksi['bukti_pembayaran'])) ?>" alt="Bukti Pembayaran" class="max-h-32 object-contain group-hover:scale-105 transition-transform duration-200">
                            <span class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-semibold gap-1">
                                Buka Ukuran Asli ↗
                            </span>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     BARIS 2: DAFTAR BARANG YANG DISEWA
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="card-admin mb-6">
    <h2 class="text-base font-bold text-[#111111] mb-4">Daftar Barang yang Disewa</h2>
    <div class="tabel-wrapper">
        <table class="tabel-admin">
            <thead>
                <tr>
                    <th class="w-12">No</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th>Harga Satuan / Hari</th>
                    <th>Durasi</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarBarang)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-gray-400 py-8">Tidak ada rincian barang pada transaksi ini.</td>
                    </tr>
                <?php else: ?>
                    <?php
                    $totalSubtotalBarang = 0.0;
                    foreach ($daftarBarang as $idx => $b):
                        $totalSubtotalBarang += (float) $b['subtotal'];
                    ?>
                    <tr>
                        <td class="text-xs font-mono text-gray-500"><?= e((string) ($idx + 1)) ?></td>
                        <td>
                            <div class="font-semibold text-gray-900">
                                <?= e($b['nama_barang']) ?>
                                <?php if ((int) ($b['jumlah'] ?? 1) > 1): ?>
                                    <span class="text-xs font-normal text-gray-500">(×<?= (int) $b['jumlah'] ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[11px] text-gray-400 font-mono">ID Barang #<?= e((string) $b['id_barang']) ?></div>
                        </td>
                        <td class="text-xs text-gray-700"><?= e($b['nama_kategori']) ?></td>
                        <td class="text-xs font-medium text-gray-800 whitespace-nowrap"><?= e(format_rupiah($b['harga_satuan'])) ?></td>
                        <td class="text-xs text-gray-600 whitespace-nowrap"><?= e((string) $lamaHari) ?> hari</td>
                        <td class="text-xs font-bold text-[#111111] text-right whitespace-nowrap"><?= e(format_rupiah($b['subtotal'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <?php if ((float) $transaksi['nominal_denda'] > 0): ?>
                    <tr class="border-t border-gray-200 bg-gray-50/60">
                        <td colspan="5" class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Subtotal Sewa</td>
                        <td class="px-3 py-2 text-right text-xs font-bold text-gray-800"><?= e(format_rupiah($totalSubtotalBarang ?? 0)) ?></td>
                    </tr>
                    <tr class="bg-red-50/50">
                        <td colspan="5" class="px-3 py-2 text-right text-xs font-semibold text-red-700">
                            Denda Keterlambatan <?= !empty($transaksi['keterangan_denda']) ? '(' . e($transaksi['keterangan_denda']) . ')' : '' ?>
                        </td>
                        <td class="px-3 py-2 text-right text-xs font-bold text-red-700"><?= e(format_rupiah($transaksi['nominal_denda'])) ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="border-t-2 border-gray-300 bg-gray-50">
                    <td colspan="5" class="px-3 py-3 text-right text-xs font-bold text-[#111111] uppercase">Total Tagihan</td>
                    <td class="px-3 py-3 text-right text-sm font-bold text-[#111111]"><?= e(format_rupiah($transaksi['total_tagihan'])) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     BARIS 3: JAMINAN IDENTITAS FISIK (1 KTP + 2 KARTU PENDUKUNG) & TINDAKAN STATUS
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

    <!-- Kiri (7 Kolom): Daftar Jaminan Tersimpan + Form 3 Baris Jaminan -->
    <div class="card-admin xl:col-span-7">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div>
                <h2 class="text-base font-bold text-[#111111]">Jaminan Identitas Fisik</h2>
                <p class="text-xs text-gray-400">
                    Wajib <strong>1 KTP Asli + 2 Kartu Identitas Pendukung</strong> (diserahkan penyewa di lokasi)
                </p>
            </div>
            <span class="badge <?= $jaminanLengkap ? 'badge-lunas' : 'badge-kuning' ?>">
                <?= $jaminanLengkap ? '✓ Jaminan Lengkap (' . e((string) $jumlahJaminan) . ' Kartu)' : '⚠ Belum Lengkap (' . e((string) $jumlahJaminan) . '/3 Kartu)' ?>
            </span>
        </div>

        <!-- Tabel Jaminan yang Sudah Tersimpan -->
        <?php if (!empty($daftarJaminan)): ?>
            <div class="mb-5">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Jaminan Tersimpan Saat Ini</h3>
                <div class="tabel-wrapper">
                    <table class="tabel-admin">
                        <thead>
                            <tr>
                                <th class="w-10">#</th>
                                <th>Jenis Kartu</th>
                                <th>No. Identitas</th>
                                <th>Catatan</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftarJaminan as $i => $jm): ?>
                                <tr>
                                    <td class="text-xs font-mono text-gray-500"><?= e((string) ($i + 1)) ?></td>
                                    <td class="font-semibold text-xs text-gray-900"><?= e($jm['jenis_jaminan']) ?></td>
                                    <td class="font-mono text-xs text-gray-800"><?= e($jm['no_identitas']) ?></td>
                                    <td class="text-xs text-gray-600"><?= e($jm['catatan'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge <?= $jm['status_jaminan'] === 'dikembalikan' ? 'badge-selesai' : 'badge-aktif' ?>">
                                            <?= e(ucfirst((string) $jm['status_jaminan'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl px-4 py-3 text-xs mb-5">
                Belum ada kartu jaminan yang tercatat untuk transaksi ini. Silakan catat 3 kartu identitas di bawah sebelum menyetujui pesanan.
            </div>
        <?php endif; ?>

        <!-- Form Input / Perbarui 3 Baris Jaminan -->
        <form action="<?= e(base_url('transaksi/proses_jaminan.php')) ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id_transaksi" value="<?= e((string) $idTransaksi) ?>">

            <!-- Baris 1: Wajib KTP -->
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200">
                <div class="text-xs font-bold text-[#111111] mb-3">
                    Kartu 1 — Wajib KTP Utama <span class="text-red-500">*</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-3">
                        <label class="form-label text-[11px]">Jenis Kartu</label>
                        <input type="hidden" name="jenis_jaminan[0]" value="KTP">
                        <input type="text"
                               value="KTP"
                               class="form-input bg-gray-200/70 font-semibold text-gray-700 cursor-not-allowed"
                               readonly>
                    </div>
                    <div class="sm:col-span-5">
                        <label for="no_identitas_0" class="form-label text-[11px]">Nomor Identitas (NIK) <span class="text-red-500">*</span></label>
                        <input type="text"
                               id="no_identitas_0"
                               name="no_identitas[0]"
                               maxlength="30"
                               value="<?= e($barisFormJaminan[0]['no_identitas']) ?>"
                               placeholder="Nomor NIK KTP"
                               class="form-input"
                               <?= !$bolehSimpanJaminan ? 'disabled' : '' ?>
                               required>
                    </div>
                    <div class="sm:col-span-4">
                        <label for="catatan_0" class="form-label text-[11px]">Catatan Kondisi</label>
                        <input type="text"
                               id="catatan_0"
                               name="catatan[0]"
                               value="<?= e($barisFormJaminan[0]['catatan']) ?>"
                               placeholder="Mis: KTP asli kondisi baik"
                               class="form-input"
                               <?= !$bolehSimpanJaminan ? 'disabled' : '' ?>>
                    </div>
                </div>
            </div>

            <!-- Baris 2: Kartu Pendukung 1 -->
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200">
                <div class="text-xs font-bold text-[#111111] mb-3">
                    Kartu 2 — Identitas Pendukung Pertama <span class="text-red-500">*</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-3">
                        <label for="jenis_jaminan_1" class="form-label text-[11px]">Jenis Kartu</label>
                        <select id="jenis_jaminan_1"
                                name="jenis_jaminan[1]"
                                class="form-input"
                                <?= !$bolehSimpanJaminan ? 'disabled' : '' ?>
                                required>
                            <?php foreach ($opsiJenisJaminan as $oj): ?>
                                <option value="<?= e($oj) ?>" <?= $barisFormJaminan[1]['jenis_jaminan'] === $oj ? 'selected' : '' ?>>
                                    <?= e($oj) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-5">
                        <label for="no_identitas_1" class="form-label text-[11px]">Nomor Identitas <span class="text-red-500">*</span></label>
                        <input type="text"
                               id="no_identitas_1"
                               name="no_identitas[1]"
                               maxlength="30"
                               value="<?= e($barisFormJaminan[1]['no_identitas']) ?>"
                               placeholder="Nomor SIM / KTM / lainnya"
                               class="form-input"
                               <?= !$bolehSimpanJaminan ? 'disabled' : '' ?>
                               required>
                    </div>
                    <div class="sm:col-span-4">
                        <label for="catatan_1" class="form-label text-[11px]">Catatan Kondisi</label>
                        <input type="text"
                               id="catatan_1"
                               name="catatan[1]"
                               value="<?= e($barisFormJaminan[1]['catatan']) ?>"
                               placeholder="Opsional"
                               class="form-input"
                               <?= !$bolehSimpanJaminan ? 'disabled' : '' ?>>
                    </div>
                </div>
            </div>

            <!-- Baris 3: Kartu Pendukung 2 -->
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200">
                <div class="text-xs font-bold text-[#111111] mb-3">
                    Kartu 3 — Identitas Pendukung Kedua <span class="text-red-500">*</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-3">
                        <label for="jenis_jaminan_2" class="form-label text-[11px]">Jenis Kartu</label>
                        <select id="jenis_jaminan_2"
                                name="jenis_jaminan[2]"
                                class="form-input"
                                <?= !$bolehSimpanJaminan ? 'disabled' : '' ?>
                                required>
                            <?php foreach ($opsiJenisJaminan as $oj): ?>
                                <option value="<?= e($oj) ?>" <?= $barisFormJaminan[2]['jenis_jaminan'] === $oj ? 'selected' : '' ?>>
                                    <?= e($oj) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-5">
                        <label for="no_identitas_2" class="form-label text-[11px]">Nomor Identitas <span class="text-red-500">*</span></label>
                        <input type="text"
                               id="no_identitas_2"
                               name="no_identitas[2]"
                               maxlength="30"
                               value="<?= e($barisFormJaminan[2]['no_identitas']) ?>"
                               placeholder="Nomor SIM / KTM / lainnya"
                               class="form-input"
                               <?= !$bolehSimpanJaminan ? 'disabled' : '' ?>
                               required>
                    </div>
                    <div class="sm:col-span-4">
                        <label for="catatan_2" class="form-label text-[11px]">Catatan Kondisi</label>
                        <input type="text"
                               id="catatan_2"
                               name="catatan[2]"
                               value="<?= e($barisFormJaminan[2]['catatan']) ?>"
                               placeholder="Opsional"
                               class="form-input"
                               <?= !$bolehSimpanJaminan ? 'disabled' : '' ?>>
                    </div>
                </div>
            </div>

            <!-- Tombol Simpan Jaminan -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <span class="text-xs <?= $bolehSimpanJaminan ? 'text-gray-500' : 'text-amber-700 font-medium' ?>">
                    <?= e($alasanJaminan) ?>
                </span>
                <button type="submit"
                        <?= !$bolehSimpanJaminan ? 'disabled' : '' ?>
                        class="btn-utama py-2.5 px-6 disabled:opacity-40 disabled:cursor-not-allowed">
                    Simpan Jaminan
                </button>
            </div>
        </form>
    </div>

    <!-- Kanan (5 Kolom): Panel Tindakan Status Transaksi & Pembayaran -->
    <div class="card-admin xl:col-span-5 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-1">
                <h2 class="text-base font-bold text-[#111111]">Tindakan Admin</h2>
                <span class="badge <?= $isDp50 ? 'badge-kuning' : 'badge-lunas' ?> text-[10px]">
                    <?= $isDp50 ? 'DP 50%' : 'Lunas 100%' ?>
                </span>
            </div>
            <p class="text-xs text-gray-400 mb-5">
                Alur status: <strong>Menunggu Verifikasi &rarr; Menunggu Diambil &rarr; Disewa &rarr; Selesai</strong>
            </p>

            <div class="space-y-4">

                <!-- 1. Setujui Booking (menunggu_verifikasi -> menunggu_diambil) -->
                <div class="p-4 rounded-2xl border <?= $bolehSetujui ? 'border-gray-300 bg-white shadow-sm' : 'border-gray-100 bg-gray-50' ?>">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-bold text-[#111111]">1. Setujui Booking</div>
                            <p class="text-[11px] <?= $bolehSetujui ? 'text-emerald-700 font-medium' : 'text-gray-400' ?> mt-0.5">
                                <?= e($alasanSetujui) ?>
                            </p>
                        </div>
                        <form action="<?= e(base_url('transaksi/proses_status.php')) ?>" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_transaksi" value="<?= e((string) $idTransaksi) ?>">
                            <input type="hidden" name="aksi" value="setujui">
                            <button type="submit"
                                    <?= !$bolehSetujui ? 'disabled' : '' ?>
                                    class="btn-utama text-xs py-2 px-4 disabled:opacity-40 disabled:cursor-not-allowed">
                                Setujui
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($isDp50): ?>
                    <!-- 2. Verifikasi Pembayaran Pelunasan (Khusus Customer DP 50%) -->
                    <div class="p-4 rounded-2xl border <?= $bolehTandaiLunas ? 'border-gray-300 bg-white shadow-sm' : 'border-gray-100 bg-gray-50' ?>">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-xs font-bold text-[#111111]">2. Verifikasi Bayar Lunas (Cash di Toko)</div>
                                <p class="text-[11px] <?= $bolehTandaiLunas ? 'text-emerald-700 font-medium' : 'text-gray-400' ?> mt-0.5">
                                    <?= e($alasanLunas) ?>
                                </p>
                            </div>
                            <form action="<?= e(base_url('transaksi/proses_status.php')) ?>" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id_transaksi" value="<?= e((string) $idTransaksi) ?>">
                                <input type="hidden" name="aksi" value="lunas">
                                <button type="submit"
                                        <?= !$bolehTandaiLunas ? 'disabled' : '' ?>
                                        class="btn-utama text-xs py-2 px-4 disabled:opacity-40 disabled:cursor-not-allowed">
                                    Tandai Lunas
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Mulai Sewa / Serahkan Barang -->
                <div class="p-4 rounded-2xl border <?= $bolehMulaiSewa ? 'border-gray-300 bg-white shadow-sm' : 'border-gray-100 bg-gray-50' ?>">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-bold text-[#111111]">
                                <?= $isDp50 ? '3. Mulai Sewa / Serahkan Barang' : '2. Mulai Sewa / Serahkan Barang' ?>
                            </div>
                            <p class="text-[11px] <?= $bolehMulaiSewa ? 'text-emerald-700 font-medium' : 'text-gray-400' ?> mt-0.5">
                                <?= e($alasanMulaiSewa) ?>
                            </p>
                        </div>
                        <form action="<?= e(base_url('transaksi/proses_status.php')) ?>" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_transaksi" value="<?= e((string) $idTransaksi) ?>">
                            <input type="hidden" name="aksi" value="disewa">
                            <button type="submit"
                                    <?= !$bolehMulaiSewa ? 'disabled' : '' ?>
                                    class="btn-utama text-xs py-2 px-4 disabled:opacity-40 disabled:cursor-not-allowed">
                                Mulai Sewa
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Batalkan Transaksi -->
                <div class="p-4 rounded-2xl border <?= $bolehBatalkan ? 'border-red-200 bg-red-50/30' : 'border-gray-100 bg-gray-50' ?>">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-bold text-red-700">
                                <?= $isDp50 ? '4. Batalkan Transaksi' : '3. Batalkan Transaksi' ?>
                            </div>
                            <p class="text-[11px] <?= $bolehBatalkan ? 'text-red-600/80' : 'text-gray-400' ?> mt-0.5">
                                <?= e($alasanBatalkan) ?>
                            </p>
                        </div>
                        <form action="<?= e(base_url('transaksi/proses_status.php')) ?>"
                              method="POST"
                              onsubmit="return confirm('Apakah Anda yakin ingin membatalkan transaksi <?= e($kodeOrder) ?>?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id_transaksi" value="<?= e((string) $idTransaksi) ?>">
                            <input type="hidden" name="aksi" value="batalkan">
                            <button type="submit"
                                    <?= !$bolehBatalkan ? 'disabled' : '' ?>
                                    class="btn-bahaya text-xs py-2 px-4 disabled:opacity-40 disabled:cursor-not-allowed">
                                Batalkan
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        <!-- Catatan Bawah -->
        <div class="mt-6 pt-4 border-t border-gray-100 text-[11px] text-gray-400 leading-relaxed">
            Catatan: Sistem database secara otomatis memeriksa stok barang pada rentang jadwal ketika status diubah menjadi <strong>Menunggu Diambil</strong> atau <strong>Disewa</strong>.
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer_admin.php'; ?>
