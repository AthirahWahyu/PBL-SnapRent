-- =====================================================================
-- SnapRent - 01_create_tables.sql (PostgreSQL 12+)  [REVISI: alur 7 tahap]
-- Urutan pembuatan: tabel induk dahulu, baru tabel yang memakai FK.
-- Jalankan: psql -U <user> -d snaprent_db -f 01_create_tables.sql
-- Struktur berubah dari versi lama -> pada lingkungan dev, DROP lalu buat ulang.
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

-- Guest checkout: penyewa dicari lewat no_telepon (UNIQUE), INSERT jika belum ada
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

-- Master aturan denda KETERLAMBATAN (progresif). Biaya kerusakan tidak disimpan di sini.
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
    kondisi_fisik        VARCHAR(255)  NULL,       -- diperbarui admin saat pengembalian jika ada kerusakan
    harga_sewa_per_hari  NUMERIC(12,2) NOT NULL CHECK (harga_sewa_per_hari >= 0),
    stok                 INTEGER       NOT NULL DEFAULT 1 CHECK (stok >= 0),   -- jumlah unit fisik
    -- Status MASTER/kondisi alat. Status "Tersedia / Menunggu diambil / Disewa"
    -- diturunkan real-time dari transaksi (lihat view v_status_barang_live).
    status_barang        VARCHAR(15)   NOT NULL DEFAULT 'aktif'
                         CHECK (status_barang IN ('aktif', 'rusak', 'maintenance', 'nonaktif')),
    CONSTRAINT fk_barang_kategori
        FOREIGN KEY (id_kategori) REFERENCES kategori (id_kategori)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE transaksi (
    id_transaksi           INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nomor_transaksi        VARCHAR(20)   NOT NULL UNIQUE,   -- diisi trigger: YYYYMMDD + urut 2 digit (2026111101)
    id_penyewa             INTEGER       NOT NULL,
    id_admin               INTEGER       NULL,      -- NULL selama booking belum diproses
    id_denda               INTEGER       NULL,      -- aturan denda keterlambatan yang terpakai
    waktu_pemesanan        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    waktu_mulai_sewa       TIMESTAMP     NOT NULL,  -- jadwal sewa (Hari-H pengambilan)
    waktu_rencana_kembali  TIMESTAMP     NOT NULL,
    waktu_ambil_aktual     TIMESTAMP     NULL,      -- diisi saat admin klik "Mulai Sewa"
    waktu_aktual_kembali   TIMESTAMP     NULL,
    jenis_pembayaran       VARCHAR(10)   NOT NULL
                           CHECK (jenis_pembayaran IN ('transfer', 'qris', 'tunai')),
    skema_pembayaran       VARCHAR(10)   NOT NULL
                           CHECK (skema_pembayaran IN ('dp_50', 'lunas')),
    bukti_pembayaran       VARCHAR(255)  NOT NULL,  -- path foto bukti transfer/QRIS
    setuju_syarat          BOOLEAN       NOT NULL CHECK (setuju_syarat),   -- wajib centang S&K
    status_pembayaran      VARCHAR(20)   NOT NULL DEFAULT 'menunggu_verifikasi'
                           CHECK (status_pembayaran IN
                                  ('menunggu_verifikasi', 'belum_lunas', 'lunas', 'ditolak', 'hangus')),
    status_transaksi       VARCHAR(20)   NOT NULL DEFAULT 'menunggu_verifikasi'
                           CHECK (status_transaksi IN
                                  ('menunggu_verifikasi', 'menunggu_diambil', 'disewa', 'selesai', 'dibatalkan')),
    -- Kolom turunan (dikelola trigger/prosedur, jangan diisi manual dari aplikasi)
    total_tagihan          NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (total_tagihan >= 0),      -- sewa + denda + kerusakan
    nominal_bayar_awal     NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (nominal_bayar_awal >= 0), -- DP 50% atau lunas saat checkout
    jumlah_dibayar         NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (jumlah_dibayar >= 0),     -- akumulasi pembayaran terverifikasi
    nominal_denda          NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (nominal_denda >= 0),      -- denda keterlambatan
    biaya_kerusakan        NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (biaya_kerusakan >= 0),    -- perbaikan/penggantian
    catatan_kerusakan      TEXT          NULL,
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
    jumlah        INTEGER       NOT NULL DEFAULT 1 CHECK (jumlah >= 1),   -- jumlah unit di keranjang
    harga_satuan  NUMERIC(12,2) NOT NULL CHECK (harga_satuan >= 0),       -- snapshot harga/hari saat transaksi
    subtotal      NUMERIC(12,2) NOT NULL CHECK (subtotal >= 0),           -- turunan: harga_satuan x jumlah x hari
    kondisi_awal  VARCHAR(255)  NULL,                                     -- hasil cek fisik saat pengambilan
    kondisi_akhir VARCHAR(255)  NULL,                                     -- hasil cek fisik saat pengembalian
    CONSTRAINT pk_detail_transaksi PRIMARY KEY (id_transaksi, id_barang),
    CONSTRAINT fk_detail_transaksi
        FOREIGN KEY (id_transaksi) REFERENCES transaksi (id_transaksi)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_detail_barang
        FOREIGN KEY (id_barang) REFERENCES barang (id_barang)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

-- Jaminan: 1 KTP asli + 2 kartu identitas pendukung (relasi 1-to-many ke transaksi)
CREATE TABLE jaminan (
    id_jaminan         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_transaksi       INTEGER      NOT NULL,
    jenis_jaminan      VARCHAR(20)  NOT NULL
                       CHECK (jenis_jaminan IN ('KTP', 'SIM', 'KTM', 'STNK', 'Paspor', 'Kartu Pelajar', 'Lainnya')),
    no_identitas       VARCHAR(30)  NOT NULL,
    status_jaminan     VARCHAR(15)  NOT NULL DEFAULT 'ditahan'
                       CHECK (status_jaminan IN ('ditahan', 'dikembalikan')),
    waktu_diterima     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    waktu_dikembalikan TIMESTAMP    NULL,
    catatan            TEXT         NULL,
    CONSTRAINT uq_jaminan_per_transaksi UNIQUE (id_transaksi, jenis_jaminan, no_identitas),
    CONSTRAINT ck_jaminan_kembali
        CHECK (status_jaminan <> 'dikembalikan' OR waktu_dikembalikan IS NOT NULL),
    CONSTRAINT fk_jaminan_transaksi
        FOREIGN KEY (id_transaksi) REFERENCES transaksi (id_transaksi)
        ON UPDATE CASCADE ON DELETE CASCADE
);

COMMIT;
