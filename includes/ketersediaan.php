<?php
/**
 * includes/ketersediaan.php
 * Fungsi pembungkus (wrapper) untuk fungsi database PostgreSQL:
 * - fn_sisa_stok(p_id_barang, p_mulai, p_kembali, p_kecuali_transaksi)  (menggantikan fn_ada_bentrok)
 * - fn_barang_tersedia(p_mulai, p_kembali, p_id_kategori)
 *
 * File ini TIDAK boleh menampilkan output apa pun bila dibuka langsung.
 * Dapat digunakan baik oleh sisi Admin maupun sisi Customer.
 */

if (defined('_KETERSEDIAAN_LOADED')) return;
define('_KETERSEDIAAN_LOADED', true);

/**
 * Cek apakah suatu unit barang bentrok jadwalnya pada rentang waktu tertentu.
 * Memanggil fungsi database fn_sisa_stok() secara langsung.
 *
 * @param PDO         $pdo                Koneksi PDO PostgreSQL
 * @param int         $id_barang          ID unit barang
 * @param string      $waktu_mulai        Timestamp mulai sewa (Y-m-d H:i:s)
 * @param string      $waktu_kembali      Timestamp rencana kembali (Y-m-d H:i:s)
 * @param int|null    $kecuali_transaksi  ID transaksi yang dikecualikan (opsional)
 * @return bool                           true bila ada bentrok, false bila aman/tersedia
 */
function cek_bentrok_jadwal(
    PDO $pdo,
    int $id_barang,
    string $waktu_mulai,
    string $waktu_kembali,
    ?int $kecuali_transaksi = null
): bool {
    // fn_ada_bentrok() sudah dihapus dari database; ketersediaan kini berbasis stok (fn_sisa_stok).
    // "Bentrok" = sisa stok pada rentang waktu tersebut kurang dari 1 unit.
    $stmt = $pdo->prepare('SELECT COALESCE(fn_sisa_stok(:id_barang, :mulai::timestamp, :kembali::timestamp, :kecuali), 0) < 1');
    $stmt->bindValue(':id_barang', $id_barang, PDO::PARAM_INT);
    $stmt->bindValue(':mulai', $waktu_mulai, PDO::PARAM_STR);
    $stmt->bindValue(':kembali', $waktu_kembali, PDO::PARAM_STR);
    if ($kecuali_transaksi === null) {
        $stmt->bindValue(':kecuali', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':kecuali', $kecuali_transaksi, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

/**
 * Alias singkat untuk cek_bentrok_jadwal().
 */
function fn_ada_bentrok(
    PDO $pdo,
    int $id_barang,
    string $waktu_mulai,
    string $waktu_kembali,
    ?int $kecuali_transaksi = null
): bool {
    return cek_bentrok_jadwal($pdo, $id_barang, $waktu_mulai, $waktu_kembali, $kecuali_transaksi);
}

/**
 * Ambil daftar barang aktif yang tersedia (tidak bentrok) pada rentang waktu tertentu.
 * Memanggil fungsi tabel database fn_barang_tersedia().
 *
 * @param PDO      $pdo            Koneksi PDO PostgreSQL
 * @param string   $waktu_mulai    Timestamp mulai sewa (Y-m-d H:i:s)
 * @param string   $waktu_kembali  Timestamp rencana kembali (Y-m-d H:i:s)
 * @param int|null $id_kategori    Filter ID kategori (opsional, null = semua kategori)
 * @return array                   Daftar baris asosiatif (id_barang, id_kategori, nama_barang, foto_barang, harga_sewa_per_hari, sisa_stok)
 */
function ambil_barang_tersedia(
    PDO $pdo,
    string $waktu_mulai,
    string $waktu_kembali,
    ?int $id_kategori = null
): array {
    $stmt = $pdo->prepare('
        SELECT id_barang, id_kategori, nama_barang, foto_barang, harga_sewa_per_hari, sisa_stok
        FROM fn_barang_tersedia(:mulai::timestamp, :kembali::timestamp, :kategori)
    ');
    $stmt->bindValue(':mulai', $waktu_mulai, PDO::PARAM_STR);
    $stmt->bindValue(':kembali', $waktu_kembali, PDO::PARAM_STR);
    if ($id_kategori === null) {
        $stmt->bindValue(':kategori', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':kategori', $id_kategori, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Alias singkat untuk ambil_barang_tersedia().
 */
function fn_barang_tersedia(
    PDO $pdo,
    string $waktu_mulai,
    string $waktu_kembali,
    ?int $id_kategori = null
): array {
    return ambil_barang_tersedia($pdo, $waktu_mulai, $waktu_kembali, $id_kategori);
}

/**
 * Ambil sisa stok unit barang yang tersedia pada rentang waktu tertentu
 * langsung dari fungsi database fn_sisa_stok().
 */
function ambil_sisa_stok(
    PDO $pdo,
    int $id_barang,
    string $waktu_mulai,
    string $waktu_kembali,
    ?int $kecuali_transaksi = null
): int {
    $stmt = $pdo->prepare('SELECT COALESCE(fn_sisa_stok(:id_barang, :mulai::timestamp, :kembali::timestamp, :kecuali), 0)');
    $stmt->bindValue(':id_barang', $id_barang, PDO::PARAM_INT);
    $stmt->bindValue(':mulai', $waktu_mulai, PDO::PARAM_STR);
    $stmt->bindValue(':kembali', $waktu_kembali, PDO::PARAM_STR);
    if ($kecuali_transaksi === null) {
        $stmt->bindValue(':kecuali', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':kecuali', $kecuali_transaksi, PDO::PARAM_INT);
    }
    $stmt->execute();

    return max(0, (int) $stmt->fetchColumn());
}

/**
 * Hitung lama sewa (hari) langsung menggunakan fungsi database fn_hitung_hari().
 */
function db_hitung_hari(PDO $pdo, string $waktu_mulai, string $waktu_kembali): int
{
    $stmt = $pdo->prepare('SELECT fn_hitung_hari(:mulai::timestamp, :kembali::timestamp)');
    $stmt->bindValue(':mulai', $waktu_mulai, PDO::PARAM_STR);
    $stmt->bindValue(':kembali', $waktu_kembali, PDO::PARAM_STR);
    $stmt->execute();

    return max(1, (int) $stmt->fetchColumn());
}
