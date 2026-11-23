<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/koneksi.php';

wajib_login();

if (!is_post()) {
    redirect('transaksi/list.php');
}

csrf_verifikasi();

$idTransaksi = ambil_int('id_transaksi', 'post');
if ($idTransaksi === null) {
    flash_set('error', 'ID transaksi tidak valid.');
    redirect('transaksi/list.php');
}

$urlDetail = 'transaksi/detail.php?id=' . $idTransaksi;

$jenisArr   = $_POST['jenis_jaminan'] ?? [];
$nomorArr   = $_POST['no_identitas'] ?? [];
$catatanArr = $_POST['catatan'] ?? [];

if (!is_array($jenisArr) || !is_array($nomorArr) || count($nomorArr) < 3) {
    flash_set('error', 'Jaminan wajib terdiri dari 3 kartu identitas (1 KTP + 2 kartu pendukung).');
    redirect($urlDetail);
}

$jenisValid = ['KTP', 'SIM', 'KTM', 'STNK', 'Paspor', 'Kartu Pelajar', 'Lainnya'];
$barisBersih = [];
$kombinasiUnik = [];

for ($i = 0; $i < 3; $i++) {
    $jenis   = ($i === 0) ? 'KTP' : trim((string) ($jenisArr[$i] ?? ''));
    $nomor   = trim((string) ($nomorArr[$i] ?? ''));
    $catatan = trim((string) ($catatanArr[$i] ?? ''));

    if (!in_array($jenis, $jenisValid, true)) {
        flash_set('error', 'Jenis jaminan pada kartu ke-' . ($i + 1) . ' tidak valid.');
        redirect($urlDetail);
    }

    if ($nomor === '') {
        flash_set('error', 'Nomor identitas pada kartu ke-' . ($i + 1) . ' (' . $jenis . ') wajib diisi.');
        redirect($urlDetail);
    }

    if (mb_strlen($nomor) > 30) {
        flash_set('error', 'Nomor identitas pada kartu ke-' . ($i + 1) . ' maksimal 30 karakter.');
        redirect($urlDetail);
    }

    // Cek duplikasi pasangan (jenis_jaminan, no_identitas) sesuai UNIQUE (id_transaksi, jenis_jaminan, no_identitas)
    $kunciUnik = mb_strtoupper($jenis . '|' . $nomor);
    if (isset($kombinasiUnik[$kunciUnik])) {
        flash_set('error', 'Kartu jaminan ' . $jenis . ' dengan nomor "' . $nomor . '" tidak boleh diinput ganda.');
        redirect($urlDetail);
    }
    $kombinasiUnik[$kunciUnik] = true;

    $barisBersih[] = [
        'jenis_jaminan' => $jenis,
        'no_identitas'  => $nomor,
        'catatan'       => ($catatan !== '') ? $catatan : null,
    ];
}

try {
    $pdo->beginTransaction();

    // Kunci baris transaksi untuk memastikan statusnya masih boleh diubah jaminannya
    $stmtCek = $pdo->prepare('SELECT status_transaksi FROM transaksi WHERE id_transaksi = :id FOR UPDATE');
    $stmtCek->execute([':id' => $idTransaksi]);
    $statusSaatIni = $stmtCek->fetchColumn();

    if ($statusSaatIni === false) {
        $pdo->rollBack();
        flash_set('error', 'Transaksi tidak ditemukan.');
        redirect('transaksi/list.php');
    }

    if (!in_array((string) $statusSaatIni, ['menunggu_verifikasi', 'menunggu_diambil', 'disewa'], true)) {
        $pdo->rollBack();
        flash_set('error', 'Jaminan tidak dapat diubah karena transaksi sudah berstatus ' . ucfirst((string) $statusSaatIni) . '.');
        redirect($urlDetail);
    }

    // Perbarui jaminan: hapus baris jaminan lama pada transaksi ini lalu masukkan 3 baris baru
    $stmtDel = $pdo->prepare('DELETE FROM jaminan WHERE id_transaksi = :id');
    $stmtDel->execute([':id' => $idTransaksi]);

    $stmtIns = $pdo->prepare("
        INSERT INTO jaminan (id_transaksi, jenis_jaminan, no_identitas, status_jaminan, catatan)
        VALUES (:id_transaksi, :jenis_jaminan, :no_identitas, 'ditahan', :catatan)
    ");

    foreach ($barisBersih as $item) {
        $stmtIns->execute([
            ':id_transaksi'  => $idTransaksi,
            ':jenis_jaminan' => $item['jenis_jaminan'],
            ':no_identitas'  => $item['no_identitas'],
            ':catatan'       => $item['catatan'],
        ]);
    }

    $pdo->commit();
    flash_set('sukses', 'Data 3 kartu jaminan identitas fisik berhasil disimpan.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[SnapRent] Error transaksi/proses_jaminan.php: ' . $e->getMessage());

    if ($e->getCode() === '23505') {
        flash_set('error', 'Terdapat duplikasi jenis kartu dan nomor identitas pada transaksi ini.');
    } else {
        flash_set('error', 'Gagal menyimpan data jaminan ke database. Silakan coba lagi.');
    }
}

redirect($urlDetail);
