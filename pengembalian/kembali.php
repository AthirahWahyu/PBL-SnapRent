<?php
require_once __DIR__ . '/../includes/auth.php';
wajib_login();

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/denda.php';

// ── Endpoint JSON untuk Pratinjau Denda Real-Time (fetch dari JS) ────────────
if (isset($_GET['ajax_preview'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $idAjax    = ambil_int('id', 'get');
    $waktuRaw  = trim((string) ($_GET['waktu_kembali'] ?? ''));

    if ($idAjax === null || $waktuRaw === '') {
        http_response_code(400);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Parameter id dan waktu_kembali wajib diisi.',
        ]);
        exit;
    }

    try {
        $tz = new DateTimeZone('Asia/Jakarta');
        $dt = new DateTime(str_replace('T', ' ', $waktuRaw), $tz);

        $hasil = hitung_denda_preview($pdo, $idAjax, $dt);

        $telatJam = (float) $hasil['telat_jam'];
        if ($telatJam <= 0) {
            $telatLabel = '0 jam (Tepat waktu)';
        } else {
            $telatLabel = rtrim(rtrim(number_format($telatJam, 2, ',', '.'), '0'), ',') . ' jam';
        }

        $jenisLabel = match ($hasil['jenis_denda']) {
            'nominal_tetap' => 'Nominal Tetap',
            'sewa_harian'   => 'Sewa Harian (1 Hari Penuh)',
            default         => 'Tanpa Denda',
        };

        echo json_encode([
            'status' => 'ok',
            'data'   => [
                'telat_jam'          => $hasil['telat_jam'],
                'telat_jam_fmt'      => $telatLabel,
                'id_denda'           => $hasil['id_denda'],
                'jenis_denda'        => $hasil['jenis_denda'],
                'jenis_denda_label'  => $jenisLabel,
                'keterangan'         => $hasil['keterangan'],
                'nominal'            => $hasil['nominal'],
                'nominal_fmt'        => format_rupiah($hasil['nominal']),
                'subtotal_sewa'      => $hasil['subtotal_sewa'],
                'subtotal_sewa_fmt'  => format_rupiah($hasil['subtotal_sewa']),
                'total_akhir'        => $hasil['total_akhir'],
                'total_akhir_fmt'    => format_rupiah($hasil['total_akhir']),
            ],
        ]);
    } catch ( Throwable $e ) {
        http_response_code(400);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Format waktu tidak valid atau transaksi tidak ditemukan.',
        ]);
    }
    exit;
}

$id_transaksi = ambil_int('id', 'get');
$mode_hasil   = isset($_GET['hasil']) && $_GET['hasil'] === '1';

// ── Jika id diberikan, muat data transaksi ───────────────────────────────────
$trx        = null;
$items      = [];
$jaminan    = [];
$preview    = null;
$waktuDefaultInput = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d\TH:i');

if ($id_transaksi !== null) {
    $stmtTrx = $pdo->prepare(<<<'SQL'
        SELECT t.*,
               p.nama_penyewa,
               p.no_telepon,
               a.nama_lengkap AS nama_admin,
               d.jenis_denda AS denda_jenis_db,
               d.batas_min_jam AS denda_min_db,
               d.batas_max_jam AS denda_max_db,
               d.keterangan AS denda_ket_db,
               GREATEST(
                   0,
                   EXTRACT(EPOCH FROM (COALESCE(t.waktu_aktual_kembali, CURRENT_TIMESTAMP) - t.waktu_rencana_kembali))::NUMERIC / 3600
               ) AS telat_jam_aktual
          FROM transaksi t
          JOIN penyewa p ON p.id_penyewa = t.id_penyewa
          LEFT JOIN admin a ON a.id_admin = t.id_admin
          LEFT JOIN denda d ON d.id_denda = t.id_denda
         WHERE t.id_transaksi = :id
    SQL);
    $stmtTrx->execute([':id' => $id_transaksi]);
    $trx = $stmtTrx->fetch(PDO::FETCH_ASSOC);

    if (!$trx) {
        flash_set('error', 'Transaksi tidak ditemukan.');
        redirect('pengembalian/kembali.php');
    }

    // Hanya izinkan status 'disewa', kecuali jika dalam mode hasil ($mode_hasil && waktu_aktual_kembali sudah tercatat)
    if ($trx['status_transaksi'] !== 'disewa' && !($mode_hasil && ($trx['status_transaksi'] === 'selesai' || !empty($trx['waktu_aktual_kembali'])))) {
        flash_set('error', 'Hanya transaksi berstatus disewa yang dapat diproses pengembaliannya (status saat ini: ' . $trx['status_transaksi'] . ').');
        redirect('pengembalian/kembali.php');
    }

    // Ambil daftar barang pada transaksi beserta status pengembalian masing-masing
    $stmtItems = $pdo->prepare(<<<'SQL'
        SELECT dt.id_barang,
               dt.jumlah,
               dt.harga_satuan,
               dt.subtotal,
               dt.lama_hari,
               dt.waktu_rencana_kembali,
               dt.waktu_aktual_kembali,
               dt.status_item,
               dt.nominal_denda AS denda_item,
               dt.kondisi_akhir,
               b.nama_barang,
               k.nama_kategori
          FROM detail_transaksi dt
          JOIN barang b ON b.id_barang = dt.id_barang
          JOIN kategori k ON k.id_kategori = b.id_kategori
         WHERE dt.id_transaksi = :id
         ORDER BY dt.waktu_rencana_kembali ASC, b.nama_barang ASC
    SQL);
    $stmtItems->execute([':id' => $id_transaksi]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // Hitung berapa item yang belum kembali
    $jumlahBelumKembali = 0;
    foreach ($items as $it) {
        if (($it['status_item'] ?? 'disewa') !== 'kembali') {
            $jumlahBelumKembali++;
        }
    }

    // Ambil daftar jaminan sebagai pengingat
    $stmtJam = $pdo->prepare(<<<'SQL'
        SELECT id_jaminan,
               jenis_jaminan,
               no_identitas,
               status_jaminan,
               catatan
          FROM jaminan
         WHERE id_transaksi = :id
         ORDER BY id_jaminan ASC
    SQL);
    $stmtJam->execute([':id' => $id_transaksi]);
    $jaminan = $stmtJam->fetchAll(PDO::FETCH_ASSOC);

    if ($trx['status_transaksi'] === 'disewa') {
        $dtSekarang = new DateTime($waktuDefaultInput, new DateTimeZone('Asia/Jakarta'));
        $preview    = hitung_denda_preview($pdo, $id_transaksi, $dtSekarang);
    }
}

// ── Jika tanpa id, tampilkan daftar transaksi yang sedang disewa ─────────────
$daftar_disewa = [];
if ($id_transaksi === null) {
    $stmtDisewa = $pdo->query(<<<'SQL'
        SELECT r.id_transaksi,
               r.nama_penyewa,
               r.no_telepon,
               r.waktu_rencana_kembali,
               r.level_baris AS level_reminder,
               r.mendekati_batas,
               r.jam_terlambat,
               t.waktu_mulai_sewa,
               t.total_tagihan,
               STRING_AGG(b.nama_barang, ', ' ORDER BY b.nama_barang) AS daftar_barang
          FROM v_dasbor_admin r
          JOIN transaksi t ON t.id_transaksi = r.id_transaksi
          LEFT JOIN detail_transaksi dt ON dt.id_transaksi = r.id_transaksi
          LEFT JOIN barang b ON b.id_barang = dt.id_barang
         WHERE r.status_transaksi = 'disewa'
         GROUP BY r.id_transaksi, r.nama_penyewa, r.no_telepon,
                  r.waktu_rencana_kembali, r.level_baris, r.mendekati_batas, r.jam_terlambat,
                  t.waktu_mulai_sewa, t.total_tagihan
         ORDER BY r.waktu_rencana_kembali ASC
    SQL);
    $daftar_disewa = $stmtDisewa->fetchAll(PDO::FETCH_ASSOC);
}

$judul_halaman = 'Proses Pengembalian Barang';
require_once __DIR__ . '/../includes/header_admin.php';
?>

<!-- Heading Halaman -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
            <a href="<?= e(base_url('pengembalian/kembali.php')) ?>" class="hover:text-[#111111] transition-colors">Pengembalian</a>
            <?php if ($trx): ?>
                <span>/</span>
                <span class="font-semibold text-[#111111]">#SR-<?= e(str_pad((string) $trx['id_transaksi'], 4, '0', STR_PAD_LEFT)) ?></span>
            <?php endif; ?>
        </div>
        <h1 class="text-2xl font-bold text-[#111111] tracking-tight">
            <?php if ($trx && $mode_hasil && !empty($trx['waktu_aktual_kembali'])): ?>
                Rincian Final Pengembalian #SR-<?= e(str_pad((string) $trx['id_transaksi'], 4, '0', STR_PAD_LEFT)) ?>
            <?php elseif ($trx): ?>
                Proses Pengembalian #SR-<?= e(str_pad((string) $trx['id_transaksi'], 4, '0', STR_PAD_LEFT)) ?>
            <?php else: ?>
                Proses Pengembalian Barang
            <?php endif; ?>
        </h1>
        <p class="text-xs text-gray-500 mt-0.5">
            <?php if ($trx): ?>
                Periksa keterlambatan, kalkulasi denda progresif, dan pengembalian kartu identitas jaminan penyewa.
            <?php else: ?>
                Pilih transaksi berstatus <strong>Disewa</strong> di bawah ini untuk memproses pengembalian unit kamera.
            <?php endif; ?>
        </p>
    </div>

    <div class="flex items-center gap-2.5">
        <a href="<?= e(base_url('pengembalian/riwayat.php')) ?>"
           class="btn-sekunder inline-flex items-center gap-2 text-xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Lihat Riwayat Selesai</span>
        </a>
        <?php if ($trx): ?>
            <a href="<?= e(base_url('transaksi/detail.php?id=' . (int) $trx['id_transaksi'])) ?>"
               class="btn-sekunder inline-flex items-center gap-2 text-xs">
                <span>Detail Transaksi</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($trx === null): ?>
    <!-- ══════════════════════════════════════════════════════════════════════
         MODE PILIH TRANSAKSI BERSTATUS DISEWA
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="card-admin overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-[#111111]">Daftar Barang Sedang Disewa</h2>
                <p class="text-xs text-gray-500">Hanya transaksi berstatus <strong>disewa</strong> yang dapat diproses pengembaliannya</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-[#111111]">
                <?= count($daftar_disewa) ?> Transaksi Aktif
            </span>
        </div>

        <?php if (empty($daftar_disewa)): ?>
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-2xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-800">Tidak Ada Barang yang Sedang Disewa</p>
                <p class="text-xs text-gray-500 mt-1">Semua pesanan aktif telah dikembalikan atau belum diserahkan ke penyewa.</p>
                <div class="mt-4">
                    <a href="<?= e(base_url('transaksi/list.php')) ?>" class="btn-utama text-xs inline-flex items-center gap-1.5">
                        <span>Buka Daftar Transaksi</span>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="tabel-admin w-full text-left border-collapse">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Penyewa</th>
                            <th>Telepon</th>
                            <th>Barang Disewa</th>
                            <th>Batas Kembali</th>
                            <th>Status Waktu</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftar_disewa as $baris): ?>
                            <?php
                            $lvl = $baris['level_reminder'] ?? 'normal';
                            $mendekati = !empty($baris['mendekati_batas']);
                            $kelasBaris = match (true) {
                                $lvl === 'merah' => 'baris-merah',
                                $mendekati       => 'baris-kuning',
                                default          => '',
                            };
                            ?>
                            <tr class="<?= $kelasBaris ?>">
                                <td class="font-bold text-[#111111] whitespace-nowrap">
                                    #SR-<?= e(str_pad((string) $baris['id_transaksi'], 4, '0', STR_PAD_LEFT)) ?>
                                </td>
                                <td class="font-semibold text-gray-900"><?= e($baris['nama_penyewa']) ?></td>
                                <td class="text-gray-600 whitespace-nowrap"><?= e($baris['no_telepon']) ?></td>
                                <td class="max-w-xs truncate" title="<?= e($baris['daftar_barang'] ?? '-') ?>">
                                    <?= e($baris['daftar_barang'] ?? '-') ?>
                                </td>
                                <td class="whitespace-nowrap font-medium"><?= e(format_tanggal($baris['waktu_rencana_kembali'])) ?></td>
                                <td class="whitespace-nowrap">
                                    <?php if ($lvl === 'merah'): ?>
                                        <span class="badge badge-merah">
                                            Terlambat <?= e(number_format((float) $baris['jam_terlambat'], 1, ',', '.')) ?> jam
                                        </span>
                                    <?php elseif ($mendekati): ?>
                                        <span class="badge badge-kuning">Hampir jatuh tempo</span>
                                    <?php else: ?>
                                        <span class="badge badge-disewa">Sedang disewa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="<?= e(base_url('pengembalian/kembali.php?id=' . (int) $baris['id_transaksi'])) ?>"
                                       class="btn-utama text-xs py-1.5 px-3.5 inline-flex items-center gap-1.5">
                                        <span>Proses Pengembalian</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($mode_hasil && !empty($trx['waktu_aktual_kembali'])): ?>
    <!-- ══════════════════════════════════════════════════════════════════════
         MODE RINCIAN DENDA FINAL (DIBACA LANGSUNG DARI DATABASE SETELAH SUKSES)
         ═══════════════════════════════════════════════════════════════════ -->
    <?php
    $subtotalSewaDb = 0.0;
    foreach ($items as $it) {
        $subtotalSewaDb += (float) $it['subtotal'];
    }
    $nominalDendaDb = (float) $trx['nominal_denda'];
    $totalAkhirDb   = (float) $trx['total_tagihan'];
    $telatAktual    = (float) $trx['telat_jam_aktual'];
    ?>
    <div class="card-admin p-6 md:p-8 mb-6 border-2 border-emerald-500/30 bg-white">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-100">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0 font-bold text-xl">
                    ✓
                </div>
                <div>
                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 mb-1">
                        TRANSAKSI SELESAI • DATA FINAL DARI DATABASE
                    </span>
                    <h2 class="text-lg font-bold text-[#111111]">
                        Pengembalian #SR-<?= e(str_pad((string) $trx['id_transaksi'], 4, '0', STR_PAD_LEFT)) ?> Berhasil Dicatat
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Seluruh perhitungan denda dan total tagihan akhir di bawah ini dibaca langsung dari hasil eksekusi <code>sp_proses_pengembalian</code> di database.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="<?= e(base_url('pengembalian/riwayat.php')) ?>" class="btn-utama text-xs">
                    Ke Riwayat Selesai
                </a>
                <a href="<?= e(base_url('transaksi/detail.php?id=' . (int) $trx['id_transaksi'])) ?>" class="btn-sekunder text-xs">
                    Lihat Detail Transaksi
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 my-6">
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100">
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Batas Rencana Kembali</div>
                <div class="text-sm font-bold text-gray-900 mt-1"><?= e(format_tanggal($trx['waktu_rencana_kembali'])) ?></div>
            </div>
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100">
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Waktu Aktual Kembali</div>
                <div class="text-sm font-bold text-[#111111] mt-1"><?= e(format_tanggal($trx['waktu_aktual_kembali'])) ?></div>
                <div class="text-[11px] text-gray-500 mt-0.5">
                    Keterlambatan: <strong><?= e(number_format($telatAktual, 2, ',', '.')) ?> jam</strong>
                </div>
            </div>
            <div class="p-4 rounded-2xl <?= $nominalDendaDb > 0 ? 'bg-rose-50 border border-rose-200' : 'bg-emerald-50 border border-emerald-200' ?>">
                <div class="text-[11px] font-semibold <?= $nominalDendaDb > 0 ? 'text-rose-600' : 'text-emerald-600' ?> uppercase tracking-wider">
                    Nominal Denda Final
                </div>
                <div class="text-base font-bold <?= $nominalDendaDb > 0 ? 'text-rose-700' : 'text-emerald-800' ?> mt-1">
                    <?= e(format_rupiah($nominalDendaDb)) ?>
                </div>
                <div class="text-[11px] text-gray-600 mt-0.5">
                    <?php if ($trx['id_denda']): ?>
                        Aturan #<?= e((string) $trx['id_denda']) ?> (<?= e($trx['denda_jenis_db']) ?>): <?= e($trx['denda_ket_db']) ?>
                    <?php else: ?>
                        Tepat waktu (Tanpa denda)
                    <?php endif; ?>
                </div>
            </div>
            <div class="p-4 rounded-2xl bg-[#111111] text-white">
                <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Total Tagihan Akhir</div>
                <div class="text-lg font-bold text-white mt-1"><?= e(format_rupiah($totalAkhirDb)) ?></div>
                <div class="text-[11px] text-gray-300 mt-0.5">
                    Sewa <?= e(format_rupiah($subtotalSewaDb)) ?> + Denda <?= e(format_rupiah($nominalDendaDb)) ?>
                </div>
            </div>
        </div>

        <!-- Status Jaminan Setelah Pengembalian -->
        <div class="pt-4 border-t border-gray-100">
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Status Kartu Identitas Jaminan</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <?php foreach ($jaminan as $jam): ?>
                    <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200/80 flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold text-[#111111]"><?= e($jam['jenis_jaminan']) ?> — <?= e($jam['no_identitas']) ?></div>
                            <?php if (!empty($jam['catatan'])): ?>
                                <div class="text-[11px] text-gray-500"><?= e($jam['catatan']) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="badge <?= $jam['status_jaminan'] === 'dikembalikan' ? 'badge-selesai' : 'badge-menunggu' ?>">
                            <?= e(ucfirst($jam['status_jaminan'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ══════════════════════════════════════════════════════════════════════
         MODE FORM PENGEMBALIAN & PRATINJAU DENDA REAL-TIME (STATUS = DISEWA)
         ═══════════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Kolom Kiri (7 kolom): Ringkasan Transaksi, Barang, & Pengingat Jaminan -->
        <div class="lg:col-span-7 flex flex-col gap-6">

            <!-- Ringkasan Transaksi & Penyewa -->
            <div class="card-admin p-6">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-gray-100">
                    <div>
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Ringkasan Penyewaan</span>
                        <h2 class="text-lg font-bold text-[#111111] mt-0.5">
                            #SR-<?= e(str_pad((string) $trx['id_transaksi'], 4, '0', STR_PAD_LEFT)) ?> — <?= e($trx['nama_penyewa']) ?>
                        </h2>
                    </div>
                    <span class="badge badge-disewa">Sedang Disewa</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100">
                        <div class="text-gray-400 font-medium mb-1">Nama Penyewa & Kontak</div>
                        <div class="font-bold text-gray-900 text-sm"><?= e($trx['nama_penyewa']) ?></div>
                        <div class="text-gray-600 mt-0.5"><?= e($trx['no_telepon']) ?></div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100">
                        <div class="text-gray-400 font-medium mb-1">Pembayaran & Petugas</div>
                        <div class="font-bold text-gray-900 text-sm uppercase">
                            <?= e($trx['jenis_pembayaran']) ?> (<?= e($trx['status_pembayaran'] === 'lunas' ? 'Lunas' : ucfirst(str_replace('_', ' ', (string) $trx['status_pembayaran']))) ?>)
                        </div>
                        <div class="text-gray-600 mt-0.5">Admin: <?= e($trx['nama_admin'] ?? '-') ?></div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-100">
                        <div class="text-gray-400 font-medium mb-1">Waktu Mulai Sewa</div>
                        <div class="font-bold text-gray-900 text-sm"><?= e(format_tanggal($trx['waktu_mulai_sewa'])) ?> WIB</div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-amber-50/70 border border-amber-200/80">
                        <div class="text-amber-700 font-semibold mb-1">Batas Rencana Kembali</div>
                        <div class="font-bold text-[#111111] text-sm"><?= e(format_tanggal($trx['waktu_rencana_kembali'])) ?> WIB</div>
                    </div>
                </div>

                <!-- Tabel Barang yang Disewa & Pengembalian Bertahap -->
                <div class="mt-5">
                    <div class="flex items-center justify-between mb-2.5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 m-0">Unit Barang &amp; Status Pengembalian</h3>
                        <span class="text-xs font-bold <?= $jumlahBelumKembali > 0 ? 'text-amber-600' : 'text-emerald-600' ?>">
                            <?= $jumlahBelumKembali > 0 ? $jumlahBelumKembali . ' barang belum kembali' : 'Semua barang sudah kembali' ?>
                        </span>
                    </div>

                    <div class="space-y-3">
                        <?php foreach ($items as $item): ?>
                            <?php 
                            $isKembali = ($item['status_item'] ?? 'disewa') === 'kembali';
                            $rencanaItem = !empty($item['waktu_rencana_kembali']) ? new DateTime($item['waktu_rencana_kembali']) : new DateTime($trx['waktu_rencana_kembali']);
                            ?>
                            <div class="p-3.5 rounded-xl border <?= $isKembali ? 'bg-emerald-50/50 border-emerald-200' : 'bg-gray-50 border-gray-200' ?> flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-[#111111]"><?= e($item['nama_barang']) ?></span>
                                        <span class="text-xs text-gray-500">(<?= (int) $item['jumlah'] ?> unit · <?= (int) ($item['lama_hari'] ?? 1) ?> hari)</span>
                                        <span class="badge <?= $isKembali ? 'badge-selesai' : 'badge-disewa' ?>">
                                            <?= $isKembali ? 'Sudah Kembali' : 'Sedang Disewa' ?>
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1">
                                        Jatuh tempo: <strong class="text-[#111111]"><?= e(format_tanggal($rencanaItem->format('Y-m-d H:i:s'))) ?> WIB</strong>
                                        <?php if ($isKembali && !empty($item['waktu_aktual_kembali'])): ?>
                                            · Kembali pada: <strong class="text-emerald-700"><?= e(format_tanggal($item['waktu_aktual_kembali'])) ?> WIB</strong>
                                            <?php if ((float) ($item['denda_item'] ?? 0) > 0): ?>
                                                <span class="text-rose-600 font-semibold">(Denda: <?= e(format_rupiah($item['denda_item'])) ?>)</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div>
                                    <?php if (!$isKembali): ?>
                                        <form action="<?= e(base_url('pengembalian/proses_kembali.php')) ?>" method="POST" class="flex items-center gap-2">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="aksi" value="kembalikan_item">
                                            <input type="hidden" name="id_transaksi" value="<?= (int) $trx['id_transaksi'] ?>">
                                            <input type="hidden" name="id_barang" value="<?= (int) $item['id_barang'] ?>">
                                            <input type="hidden" name="waktu_kembali" value="<?= e($waktuDefaultInput) ?>">
                                            <button type="submit"
                                                    onclick="return confirm('Konfirmasi terima pengembalian barang <?= e(addslashes($item['nama_barang'])) ?> sekarang?');"
                                                    class="btn-sekunder px-3 py-1.5 text-xs font-bold text-emerald-700 border-emerald-300 hover:bg-emerald-50 cursor-pointer">
                                                ✓ Terima Barang Ini
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs font-bold text-emerald-700 flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                            </svg>
                                            Selesai
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Pengingat Kartu Identitas Jaminan -->
            <div class="card-admin p-6">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-sm font-bold text-[#111111]">Pengingat Kartu Identitas Jaminan</h3>
                        <p class="text-xs text-gray-500">Pastikan seluruh kartu fisik berikut dikembalikan kepada penyewa saat serah terima unit.</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-[#111111]">
                        <?= count($jaminan) ?> Kartu
                    </span>
                </div>

                <?php if (empty($jaminan)): ?>
                    <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-800">
                        Belum ada data jaminan tercatat pada transaksi ini.
                    </div>
                <?php else: ?>
                    <div class="space-y-2.5">
                        <?php foreach ($jaminan as $idx => $jam): ?>
                            <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200/80 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-7 h-7 rounded-full bg-[#111111] text-white text-xs font-bold flex items-center justify-center flex-shrink-0">
                                        <?= $idx + 1 ?>
                                    </span>
                                    <div>
                                        <div class="text-xs font-bold text-[#111111]">
                                            <?= e($jam['jenis_jaminan']) ?> — <span class="font-mono"><?= e($jam['no_identitas']) ?></span>
                                        </div>
                                        <?php if (!empty($jam['catatan'])): ?>
                                            <div class="text-[11px] text-gray-500 mt-0.5">Catatan: <?= e($jam['catatan']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge-status <?= $jam['status_jaminan'] === 'dikembalikan' ? 'badge--selesai' : 'badge--menunggu' ?>">
                                    <?= e(ucfirst($jam['status_jaminan'])) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Kolom Kanan (5 kolom): Form Waktu Kembali & Pratinjau Denda Langsung -->
        <div class="lg:col-span-5">
            <form action="<?= e(base_url('pengembalian/proses_kembali.php')) ?>" method="POST" class="card-admin p-6 sticky top-6">
                <?= csrf_field() ?>
                <input type="hidden" name="id_transaksi" value="<?= (int) $trx['id_transaksi'] ?>">

                <h2 class="text-base font-bold text-[#111111] mb-1">Form Konfirmasi Pengembalian</h2>
                <p class="text-xs text-gray-500 mb-5">
                    Ubah waktu aktual kembali di bawah ini untuk melihat pratinjau denda secara langsung sebelum disimpan.
                </p>

                <!-- Input Waktu Aktual Kembali -->
                <div class="mb-5">
                    <label for="input-waktu-kembali" class="form-label">
                        Waktu Aktual Kembali <span class="text-rose-600">*</span>
                    </label>
                    <input type="datetime-local"
                           id="input-waktu-kembali"
                           name="waktu_kembali"
                           value="<?= e($waktuDefaultInput) ?>"
                           step="60"
                           required
                           class="form-input font-medium">
                    <div class="flex items-center justify-between mt-1.5 text-[11px] text-gray-500">
                        <span>Batas kembali: <strong><?= e(format_tanggal($trx['waktu_rencana_kembali'])) ?></strong></span>
                        <button type="button"
                                id="btn-waktu-sekarang"
                                class="text-[#111111] font-semibold underline hover:opacity-75 cursor-pointer">
                            Atur ke Sekarang
                        </button>
                    </div>
                </div>

                <!-- Kotak Pratinjau Denda Real-Time -->
                <?php
                $telatAwal = (float) ($preview['telat_jam'] ?? 0);
                $telatAwalFmt = $telatAwal <= 0
                    ? '0 jam (Tepat waktu)'
                    : rtrim(rtrim(number_format($telatAwal, 2, ',', '.'), '0'), ',') . ' jam';
                $jenisAwalLabel = match ($preview['jenis_denda'] ?? null) {
                    'nominal_tetap' => 'Nominal Tetap',
                    'sewa_harian'   => 'Sewa Harian (1 Hari Penuh)',
                    default         => 'Tanpa Denda',
                };
                ?>
                <div class="rounded-2xl border border-gray-200 bg-gray-50/90 p-4 mb-5">
                    <div class="flex items-center justify-between mb-3 pb-2.5 border-b border-gray-200/80">
                        <span class="text-xs font-bold uppercase tracking-wider text-[#111111]">
                            Pratinjau Perhitungan Denda
                        </span>
                        <span id="indikator-loading" class="hidden text-[11px] text-gray-500 font-medium">
                            Menghitung...
                        </span>
                    </div>

                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Jam Terlambat</span>
                            <span id="prev-telat-jam" class="font-bold text-gray-900">
                                <?= e($telatAwalFmt) ?>
                            </span>
                        </div>

                        <div class="flex items-start justify-between gap-4">
                            <span class="text-gray-500 flex-shrink-0">Aturan Denda</span>
                            <div class="text-right">
                                <span id="prev-jenis-denda"
                                      class="inline-block px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-200 text-[#111111]">
                                    <?= e($jenisAwalLabel) ?>
                                </span>
                                <div id="prev-keterangan" class="text-[11px] text-gray-600 mt-0.5">
                                    <?= e($preview['keterangan'] ?? 'Tepat waktu (tanpa denda)') ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-gray-200/70">
                            <span class="text-gray-500">Subtotal Sewa Awal</span>
                            <span id="prev-subtotal" class="font-semibold text-gray-800">
                                <?= e(format_rupiah($preview['subtotal_sewa'] ?? $trx['total_tagihan'])) ?>
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Nominal Denda</span>
                            <span id="prev-nominal-denda"
                                  class="font-bold text-sm <?= ($preview['nominal'] ?? 0) > 0 ? 'text-rose-600' : 'text-emerald-700' ?>">
                                <?= e(format_rupiah($preview['nominal'] ?? 0)) ?>
                            </span>
                        </div>

                        <div class="flex items-center justify-between pt-3 border-t border-gray-300 text-sm">
                            <span class="font-bold text-[#111111]">Total Tagihan Akhir</span>
                            <span id="prev-total-akhir" class="font-bold text-base text-[#111111]">
                                <?= e(format_rupiah($preview['total_akhir'] ?? $trx['total_tagihan'])) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Checkbox Pengembalian Jaminan -->
                <label class="flex items-start gap-3 p-3.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 transition-colors cursor-pointer mb-5 select-none <?= $jumlahBelumKembali > 0 ? 'opacity-60 cursor-not-allowed' : '' ?>">
                    <input type="checkbox"
                           name="kembalikan_jaminan"
                           value="1"
                           <?= $jumlahBelumKembali === 0 ? 'checked' : 'disabled' ?>
                           class="mt-0.5 w-4 h-4 rounded border-gray-300 text-[#111111] focus:ring-[#111111]">
                    <span class="text-xs">
                        <strong class="block text-[#111111] font-semibold">Kartu identitas sudah dikembalikan ke penyewa</strong>
                        <span class="text-gray-500">
                            <?= $jumlahBelumKembali > 0 
                                ? 'Jaminan tetap ditahan toko sampai seluruh barang selesai dikembalikan.' 
                                : 'Ubah status seluruh kartu jaminan transaksi ini menjadi dikembalikan.' ?>
                        </span>
                    </span>
                </label>

                <button type="submit"
                        onclick="return confirm('Proses pengembalian seluruh sisa barang dan tutup transaksi ini?');"
                        class="btn-utama w-full py-3 text-xs font-bold flex items-center justify-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Selesaikan Seluruh Pengembalian</span>
                </button>
            </form>
        </div>

    </div>

    <!-- Script Update Pratinjau Denda via Fetch -->
    <script>
    (function () {
        const inputWaktu    = document.getElementById('input-waktu-kembali');
        const btnSekarang   = document.getElementById('btn-waktu-sekarang');
        const elLoading     = document.getElementById('indikator-loading');
        const elTelat       = document.getElementById('prev-telat-jam');
        const elJenis       = document.getElementById('prev-jenis-denda');
        const elKet         = document.getElementById('prev-keterangan');
        const elSubtotal    = document.getElementById('prev-subtotal');
        const elNominal     = document.getElementById('prev-nominal-denda');
        const elTotalAkhir  = document.getElementById('prev-total-akhir');

        const idTransaksi   = <?= (int) $trx['id_transaksi'] ?>;
        const endpointUrl   = <?= json_encode(base_url('pengembalian/kembali.php')) ?>;
        let timerDebounce   = null;

        function updatePreview() {
            const val = inputWaktu.value;
            if (!val) return;

            elLoading.classList.remove('hidden');
            const url = endpointUrl + '?ajax_preview=1&id=' + encodeURIComponent(idTransaksi) + '&waktu_kembali=' + encodeURIComponent(val);

            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (json.status !== 'ok' || !json.data) return;
                    const d = json.data;
                    elTelat.textContent      = d.telat_jam_fmt;
                    elJenis.textContent      = d.jenis_denda_label;
                    elKet.textContent        = d.keterangan;
                    elSubtotal.textContent   = d.subtotal_sewa_fmt;
                    elNominal.textContent    = d.nominal_fmt;
                    elTotalAkhir.textContent = d.total_akhir_fmt;

                    if (Number(d.nominal) > 0) {
                        elNominal.classList.remove('text-emerald-700');
                        elNominal.classList.add('text-rose-600');
                    } else {
                        elNominal.classList.remove('text-rose-600');
                        elNominal.classList.add('text-emerald-700');
                    }
                })
                .catch(function () {})
                .finally(function () {
                    elLoading.classList.add('hidden');
                });
        }

        if (inputWaktu) {
            inputWaktu.addEventListener('input', function () {
                clearTimeout(timerDebounce);
                timerDebounce = setTimeout(updatePreview, 150);
            });
            inputWaktu.addEventListener('change', updatePreview);
        }

        if (btnSekarang && inputWaktu) {
            btnSekarang.addEventListener('click', function () {
                const now = new Date();
                const pad = function (n) { return String(n).padStart(2, '0'); };
                const str = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate())
                          + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
                inputWaktu.value = str;
                updatePreview();
            });
        }
    })();
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer_admin.php'; ?>
