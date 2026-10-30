-- Active: 1791383128716@@localhost@5432@snaprent_db
-- =====================================================================
-- SnapRent - 01_create_tables.sql (PostgreSQL)
-- Urutan pembuatan: tabel induk dahulu, baru tabel yang memakai FK.
-- Jalankan: psql -U <user> -d snaprent -f 01_create_tables.sql
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- Tabel induk (tidak memiliki FK)
-- ---------------------------------------------------------------------

CREATE TABLE admin (
    id_admin      INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,          -- simpan hash (password_hash() PHP)
    nama_lengkap  VARCHAR(100) NOT NULL
);

CREATE TABLE penyewa (
    id_penyewa    INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nama_penyewa  VARCHAR(100) NOT NULL,
    no_telepon    VARCHAR(15)  NOT NULL UNIQUE
);

CREATE TABLE kategori (
    id_kategori     INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nama_kategori   VARCHAR(50)  NOT NULL UNIQUE,
    syarat_jaminan  VARCHAR(255) NOT NULL
);

CREATE TABLE denda (
    id_denda       INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    jenis_denda    VARCHAR(15)   NOT NULL
                   CHECK (jenis_denda IN ('nominal_tetap', 'sewa_harian')),
    batas_min_jam  INTEGER       NOT NULL CHECK (batas_min_jam >= 0),   -- eksklusif
    batas_max_jam  INTEGER       NULL,                                  -- inklusif; NULL = tanpa batas atas
    nilai_denda    NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (nilai_denda >= 0),
    keterangan     VARCHAR(255)  NULL,
    CONSTRAINT ck_denda_rentang
        CHECK (batas_max_jam IS NULL OR batas_max_jam > batas_min_jam)
);

-- ---------------------------------------------------------------------
-- Tabel dengan FK
-- ---------------------------------------------------------------------

CREATE TABLE barang (
    id_barang            INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_kategori          INTEGER       NOT NULL,
    nama_barang          VARCHAR(100)  NOT NULL,
    foto_barang          VARCHAR(255)  NULL,
    kondisi_fisik        VARCHAR(255)  NULL,
    harga_sewa_per_hari  NUMERIC(12,2) NOT NULL CHECK (harga_sewa_per_hari >= 0),
    status_barang        VARCHAR(15)   NOT NULL DEFAULT 'aktif'
                         CHECK (status_barang IN ('aktif', 'rusak', 'maintenance', 'nonaktif')),
    CONSTRAINT fk_barang_kategori
        FOREIGN KEY (id_kategori) REFERENCES kategori (id_kategori)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE transaksi (
    id_transaksi           INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_penyewa             INTEGER       NOT NULL,
    id_admin               INTEGER       NULL,      -- NULL selama booking belum diproses
    id_denda               INTEGER       NULL,      -- NULL jika tidak terlambat
    waktu_pemesanan        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    waktu_mulai_sewa       TIMESTAMP     NOT NULL,
    waktu_rencana_kembali  TIMESTAMP     NOT NULL,
    waktu_aktual_kembali   TIMESTAMP     NULL,
    jenis_pembayaran       VARCHAR(10)   NOT NULL
                           CHECK (jenis_pembayaran IN ('tunai', 'transfer')),
    status_pembayaran      VARCHAR(15)   NOT NULL DEFAULT 'belum_dibayar'
                           CHECK (status_pembayaran IN ('belum_dibayar', 'lunas')),
    status_transaksi       VARCHAR(15)   NOT NULL DEFAULT 'menunggu'
                           CHECK (status_transaksi IN ('menunggu', 'disetujui', 'disewa', 'selesai', 'dibatalkan')),
    total_tagihan          NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (total_tagihan >= 0),  -- turunan (snapshot)
    nominal_denda          NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (nominal_denda >= 0),  -- turunan (snapshot)
    CONSTRAINT ck_transaksi_rencana
        CHECK (waktu_rencana_kembali > waktu_mulai_sewa),
    CONSTRAINT ck_transaksi_aktual
        CHECK (waktu_aktual_kembali IS NULL OR waktu_aktual_kembali >= waktu_mulai_sewa),
    CONSTRAINT fk_transaksi_penyewa
        FOREIGN KEY (id_penyewa) REFERENCES penyewa (id_penyewa)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_transaksi_admin
        FOREIGN KEY (id_admin) REFERENCES admin (id_admin)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_transaksi_denda
        FOREIGN KEY (id_denda) REFERENCES denda (id_denda)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE detail_transaksi (
    id_transaksi  INTEGER       NOT NULL,
    id_barang     INTEGER       NOT NULL,
    harga_satuan  NUMERIC(12,2) NOT NULL CHECK (harga_satuan >= 0),  -- snapshot harga saat transaksi
    subtotal      NUMERIC(12,2) NOT NULL CHECK (subtotal >= 0),      -- turunan: harga_satuan x lama sewa (hari)
    CONSTRAINT pk_detail_transaksi PRIMARY KEY (id_transaksi, id_barang),
    CONSTRAINT fk_detail_transaksi
        FOREIGN KEY (id_transaksi) REFERENCES transaksi (id_transaksi)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_detail_barang
        FOREIGN KEY (id_barang) REFERENCES barang (id_barang)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE jaminan (
    id_jaminan      INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_transaksi    INTEGER      NOT NULL,
    jenis_jaminan   VARCHAR(20)  NOT NULL
                    CHECK (jenis_jaminan IN ('KTP', 'SIM', 'KTM', 'Kartu Pelajar', 'Lainnya')),
    no_identitas    VARCHAR(30)  NOT NULL,
    status_jaminan  VARCHAR(15)  NOT NULL DEFAULT 'diterima'
                    CHECK (status_jaminan IN ('diterima', 'dikembalikan')),
    catatan         TEXT         NULL,
    CONSTRAINT uq_jaminan_per_transaksi UNIQUE (id_transaksi, jenis_jaminan, no_identitas),
    CONSTRAINT fk_jaminan_transaksi
        FOREIGN KEY (id_transaksi) REFERENCES transaksi (id_transaksi)
        ON UPDATE CASCADE ON DELETE CASCADE
);

COMMIT;
