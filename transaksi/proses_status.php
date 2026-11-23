<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/ketersediaan.php';
require_once __DIR__ . '/../includes/koneksi.php';

wajib_login();

if (!is_post()) {
    redirect('transaksi/list.php');
}

csrf_verifikasi();

$idTransaksi = ambil_int('id_transaksi', 'post');
$aksi        = trim($_POST['aksi'] ?? '');

if ($idTransaksi === null) {
    flash_set('error', 'ID transaksi tidak valid.');
    redirect('transaksi/list.php');
}

$urlDetail = 'transaksi/detail.php?id=' . $idTransaksi;
$admin     = admin_saat_ini();
$idAdmin   = (int) ($admin['id_admin'] ?? 0);

if ($idAdmin <= 0) {
    flash_set('error', 'Sesi admin tidak valid. Silakan login ulang.');
    redirect('auth/login.php');
}

// Daftar transisi status_transaksi yang diizinkan oleh mesin status
$transisiDiizinkan = [
    'setujui'  => ['dari' => ['menunggu_verifikasi'],                        'ke' => 'menunggu_diambil'],
    'disewa'   => ['dari' => ['menunggu_diambil'],                           'ke' => 'disewa'],
    'batalkan' => ['dari' => ['menunggu_verifikasi', 'menunggu_diambil'],    'ke' => 'dibatalkan'],
];

try {
    $pdo->beginTransaction();

    // Kunci baris transaksi agar tidak terjadi race condition
    $stmt = $pdo->prepare('
        SELECT id_transaksi, status_transaksi, status_pembayaran, waktu_mulai_sewa, waktu_rencana_kembali
        FROM transaksi
        WHERE id_transaksi = :id
        FOR UPDATE
    ');
    $stmt->execute([':id' => $idTransaksi]);
    $transaksi = $stmt->fetch();

    if (!$transaksi) {
        $pdo->rollBack();
        flash_set('error', 'Data transaksi tidak ditemukan.');
        redirect('transaksi/list.php');
    }

    $statusSaatIni = (string) $transaksi['status_transaksi'];
    $bayarSaatIni  = (string) $transaksi['status_pembayaran'];

    // ── Aksi 1: Tandai Lunas (Verifikasi Pembayaran) ─────────────────────────
    if ($aksi === 'lunas') {
        if (in_array($statusSaatIni, ['dibatalkan', 'selesai'], true)) {
            $pdo->rollBack();
            flash_set('error', 'Pembayaran tidak dapat diubah karena transaksi sudah berstatus ' . ucfirst($statusSaatIni) . '.');
            redirect($urlDetail);
        }
        if ($bayarSaatIni === 'lunas') {
            $pdo->rollBack();
            flash_set('info', 'Pembayaran untuk transaksi ini sudah berstatus Lunas.');
            redirect($urlDetail);
        }
        if ($statusSaatIni === 'menunggu_verifikasi') {
            $pdo->rollBack();
            flash_set('error', 'Bukti pembayaran diverifikasi lewat aksi Setujui; pelunasan dicatat setelah booking disetujui.');
            redirect($urlDetail);
        }

        // status_pembayaran dikelola database: catat pelunasan sisa tagihan (p_nominal NULL = seluruh sisa)
        $stmtUpd = $pdo->prepare('CALL sp_catat_pembayaran(:id)');
        $stmtUpd->execute([':id' => $idTransaksi]);
        $pdo->commit();

        flash_set('sukses', 'Status pembayaran berhasil ditandai sebagai Lunas.');
        redirect($urlDetail);
    }

    // ── Validasi apakah aksi termasuk dalam mesin status ─────────────────────
    if (!isset($transisiDiizinkan[$aksi])) {
        $pdo->rollBack();
        flash_set('error', 'Aksi perubahan status tidak dikenali.');
        redirect($urlDetail);
    }

    $aturan     = $transisiDiizinkan[$aksi];
    $statusBaru = $aturan['ke'];

    // Validasi transisi dari status saat ini
    if (!in_array($statusSaatIni, $aturan['dari'], true)) {
        $pdo->rollBack();
        flash_set(
            'error',
            'Transisi status tidak diizinkan: tidak dapat mengubah dari "' . ucfirst($statusSaatIni) . '" menjadi "' . ucfirst($statusBaru) . '".'
        );
        redirect($urlDetail);
    }

    // ── Aksi 2: Setujui (menunggu_verifikasi -> menunggu_diambil) ────────────
    if ($aksi === 'setujui') {
        // sp_setujui_booking: status -> menunggu_diambil, mengisi id_admin & pembayaran awal
        $stmtUpd = $pdo->prepare('CALL sp_setujui_booking(:id, :id_admin)');
        $stmtUpd->execute([
            ':id'       => $idTransaksi,
            ':id_admin' => $idAdmin,
        ]);

        $pdo->commit();
        flash_set('sukses', 'Pesanan berhasil disetujui. Unit barang siap diserahkan kepada penyewa.');
        redirect($urlDetail);
    }

    // ── Aksi 3: Mulai Sewa / Serahkan Barang (menunggu_diambil -> disewa) ────
    if ($aksi === 'disewa') {
        // sp_mulai_sewa: syarat Hari-H, lunas, dan 3 jaminan ditahan divalidasi trigger database
        $stmtUpd = $pdo->prepare('CALL sp_mulai_sewa(:id)');
        $stmtUpd->execute([':id' => $idTransaksi]);

        $pdo->commit();
        flash_set('sukses', 'Status transaksi diubah menjadi Disewa. Barang telah diserahkan kepada penyewa.');
        redirect($urlDetail);
    }

    // ── Aksi 4: Batalkan (menunggu_verifikasi | menunggu_diambil -> dibatalkan) ─
    if ($aksi === 'batalkan') {
        // sp_batalkan_booking: status_pembayaran menjadi 'ditolak' atau 'hangus' sesuai tahap
        $stmtUpd = $pdo->prepare('CALL sp_batalkan_booking(:id, :id_admin)');
        $stmtUpd->execute([
            ':id'       => $idTransaksi,
            ':id_admin' => $idAdmin,
        ]);

        // Kembalikan status jaminan bila sudah sempat dicatat
        $stmtJmBack = $pdo->prepare("
            UPDATE jaminan
               SET status_jaminan     = 'dikembalikan',
                   waktu_dikembalikan = CURRENT_TIMESTAMP
             WHERE id_transaksi = :id
               AND status_jaminan = 'ditahan'
        ");
        $stmtJmBack->execute([':id' => $idTransaksi]);

        $pdo->commit();
        flash_set('sukses', 'Transaksi berhasil dibatalkan.');
        redirect($urlDetail);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $pesanMentah = $e->getMessage();
    error_log('[SnapRent] Error transaksi/proses_status.php: ' . $pesanMentah);

    // Tangkap exception stok/jadwal PostgreSQL (trg_transaksi_before_update: stok tidak mencukupi)
    if (stripos($pesanMentah, 'bentrok') !== false || stripos($pesanMentah, 'sudah dipesan') !== false
        || stripos($pesanMentah, 'tidak mencukupi') !== false) {
        $labelBarang = 'terkait';
        if (preg_match('/Barang\s+(\d+)/i', $pesanMentah, $matches)) {
            $idBarangBentrok = (int) $matches[1];
            try {
                $stmtBrg = $pdo->prepare('SELECT nama_barang FROM barang WHERE id_barang = :id LIMIT 1');
                $stmtBrg->execute([':id' => $idBarangBentrok]);
                $namaBrg = $stmtBrg->fetchColumn();
                if ($namaBrg) {
                    $labelBarang = '"' . $namaBrg . '" (ID #' . $idBarangBentrok . ')';
                } else {
                    $labelBarang = '#' . $idBarangBentrok;
                }
            } catch (PDOException) {
                $labelBarang = '#' . $idBarangBentrok;
            }
        }
        flash_set('error', 'Barang ' . $labelBarang . ' sudah dipesan pada rentang waktu tersebut.');
    } elseif ($e->getCode() === 'P0001' && preg_match('/ERROR:\s+(.+)$/s', $pesanMentah, $mPesan)) {
        // Pesan RAISE EXCEPTION dari aturan database (Hari-H, lunas, jaminan, dll.) sudah berbahasa Indonesia
        flash_set('error', trim(preg_replace('/\s*CONTEXT:.*$/s', '', $mPesan[1])));
    } else {
        flash_set('error', 'Gagal memperbarui status transaksi. Silakan coba lagi.');
    }

    redirect($urlDetail);
}
