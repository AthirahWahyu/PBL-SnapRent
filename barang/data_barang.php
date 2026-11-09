<?php
// Mengambil daftar semua kategori barang
function getDaftarKategori($pdo) {
    $stmt = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Mengambil daftar barang (dengan pencarian dan filter kategori)
function getDaftarBarang($pdo, $search = '', $kategori_id = 0) {
    $params = [];
    $sql = "SELECT b.*, k.nama_kategori 
            FROM barang b 
            JOIN kategori k ON b.id_kategori = k.id_kategori 
            WHERE 1=1";

    if (!empty($search)) {
        // Filter mencari barang yang NAMA AWALNYA cocok dengan keyword (search%)
        // atau kondisi fisik yang cocok
        $sql .= " AND (b.nama_barang ILIKE ? OR b.kondisi_fisik ILIKE ?)";
        $params[] = "$search%";
        $params[] = "%$search%";
    }

    if ($kategori_id > 0) {
        $sql .= " AND b.id_kategori = ?";
        $params[] = $kategori_id;
    }

    if (!empty($search)) {
        // Prioritaskan barang yang NAMA AWALNYA cocok di posisi paling atas,
        // lalu urutkan nama barang dari A-Z
        $sql .= " ORDER BY (b.nama_barang ILIKE ?) DESC, b.nama_barang ASC";
        $params[] = "$search%";
    } else {
        $sql .= " ORDER BY b.id_barang ASC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Mengambil ID barang yang sedang disewa
function getBarangDisewaIds($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT dt.id_barang 
                         FROM detail_transaksi dt 
                         JOIN transaksi t ON dt.id_transaksi = t.id_transaksi 
                         WHERE t.status_transaksi = 'disewa'");
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}
