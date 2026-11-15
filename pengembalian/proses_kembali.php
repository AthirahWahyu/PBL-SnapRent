<?php
require_once __DIR__ . '/../includes/auth.php';
wajib_login();

require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';

if (!is_post()) {
    flash_set('error', 'Metode request tidak diizinkan.');
    redirect('pengembalian/kembali.php');
}

csrf_verifikasi();

$id_transaksi      = ambil_int('id_transaksi', 'post');
$waktu_kembali_raw = trim((string) ($_POST['waktu_kembali'] ?? ''));
$kembalikan_jaminan = !empty($_POST['kembalikan_jaminan']);

if ($id_transaksi === null || $waktu_kembali_raw === '') {
    flash_set('error', 'Data pengembalian tidak lengkap.');
    redirect('pengembalian/kembali.php');
}

try {
    $tz = new DateTimeZone('Asia/Jakarta');
    $dtKembali = new DateTime(str_replace('T', ' ', $waktu_kembali_raw), $tz);
    $waktuKembaliSql = $dtKembali->format('Y-m-d H:i:s');
} catch (Throwable) {
    flash_set('error', 'Format waktu pengembalian tidak valid.');
    redirect('pengembalian/kembali.php?id=' . $id_transaksi);
}

try {
    // Pastikan waktu kembali tidak sebelum waktu mulai sewa
    $stmtCek = $pdo->prepare('SELECT waktu_mulai_sewa, status_transaksi FROM transaksi WHERE id_transaksi = :id');
    $stmtCek->execute([':id' => $id_transaksi]);
    $trxAwal = $stmtCek->fetch(PDO::FETCH_ASSOC);

    if (!$trxAwal) {
        flash_set('error', 'Transaksi tidak ditemukan.');
        redirect('pengembalian/kembali.php');
    }

    if ($trxAwal['status_transaksi'] !== 'disewa') {
        flash_set('error', 'Hanya transaksi berstatus disewa yang dapat dikembalikan.');
        redirect('pengembalian/kembali.php');
    }

    $dtMulai = new DateTime($trxAwal['waktu_mulai_sewa'], $tz);
    if ($dtKembali < $dtMulai) {
        flash_set('error', 'Waktu aktual kembali tidak boleh lebih awal dari waktu mulai sewa (' . format_tanggal($trxAwal['waktu_mulai_sewa']) . ').');
        redirect('pengembalian/kembali.php?id=' . $id_transaksi);
    }

    $pdo->beginTransaction();

    // 1. Panggil stored procedure sp_proses_pengembalian di PostgreSQL
    $stmtSp = $pdo->prepare('CALL sp_proses_pengembalian(:id, :waktu)');
    $stmtSp->execute([
        ':id'    => $id_transaksi,
        ':waktu' => $waktuKembaliSql,
    ]);

    // 2. Bila checkbox dicentang, kembalikan seluruh jaminan transaksi melalui stored procedure
    if ($kembalikan_jaminan) {
        $stmtJam = $pdo->prepare('CALL sp_kembalikan_jaminan(:id)');
        $stmtJam->execute([':id' => $id_transaksi]);
    }

    // 3. Jika pembayaran sudah lunas dan tidak ada jaminan yang masih ditahan, selesaikan transaksi
    $stmtCekSelesai = $pdo->prepare(<<<'SQL'
        SELECT t.status_pembayaran,
               EXISTS (
                   SELECT 1 FROM jaminan j
                    WHERE j.id_transaksi = t.id_transaksi
                      AND j.status_jaminan = 'ditahan'
               ) AS masih_ditahan
          FROM transaksi t
         WHERE t.id_transaksi = :id
    SQL);
    $stmtCekSelesai->execute([':id' => $id_transaksi]);
    $cekSelesai = $stmtCekSelesai->fetch(PDO::FETCH_ASSOC);

    if ($cekSelesai && $cekSelesai['status_pembayaran'] === 'lunas' && !$cekSelesai['masih_ditahan']) {
        $stmtSelesai = $pdo->prepare('CALL sp_selesaikan_transaksi(:id)');
        $stmtSelesai->execute([':id' => $id_transaksi]);
    }

    $pdo->commit();

    // 3. Baca rincian denda final langsung dari database (bukan dari form)
    $stmtFinal = $pdo->prepare(<<<'SQL'
        SELECT t.id_transaksi,
               t.waktu_aktual_kembali,
               t.nominal_denda,
               t.total_tagihan,
               d.jenis_denda,
               d.keterangan AS keterangan_denda
          FROM transaksi t
          LEFT JOIN denda d ON d.id_denda = t.id_denda
         WHERE t.id_transaksi = :id
    SQL);
    $stmtFinal->execute([':id' => $id_transaksi]);
    $final = $stmtFinal->fetch(PDO::FETCH_ASSOC);

    $ketDenda = $final['keterangan_denda'] ?? 'Tepat waktu (tanpa denda)';
    $pesanSukses = sprintf(
        'Pengembalian #SR-%s berhasil diproses. Denda Final: %s (%s) | Total Tagihan Akhir: %s.',
        str_pad((string) $id_transaksi, 4, '0', STR_PAD_LEFT),
        format_rupiah($final['nominal_denda'] ?? 0),
        $ketDenda,
        format_rupiah($final['total_tagihan'] ?? 0)
    );

    flash_set('sukses', $pesanSukses);
    redirect('pengembalian/kembali.php?id=' . $id_transaksi . '&hasil=1');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_set('error', 'Gagal memproses pengembalian barang. Pastikan status transaksi masih disewa dan waktu kembali valid.');
    redirect('pengembalian/kembali.php?id=' . $id_transaksi);
}
