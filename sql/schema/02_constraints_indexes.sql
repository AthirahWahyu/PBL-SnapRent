-- =====================================================================
-- SnapRent - 02_constraints_indexes.sql (PostgreSQL)  [REVISI]
-- Constraint tambahan dan indeks untuk mempercepat query.
-- Jalankan setelah 01_create_tables.sql
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- Constraint tambahan
-- ---------------------------------------------------------------------

-- Nomor telepon/WhatsApp: hanya angka (boleh diawali +), 8-15 karakter
ALTER TABLE penyewa
    ADD CONSTRAINT ck_penyewa_telepon CHECK (no_telepon ~ '^\+?[0-9]{8,14}$');

-- Transaksi selesai wajib punya waktu pengembalian aktual
ALTER TABLE transaksi
    ADD CONSTRAINT ck_transaksi_selesai
    CHECK (status_transaksi <> 'selesai' OR waktu_aktual_kembali IS NOT NULL);

-- Transaksi selesai wajib lunas
ALTER TABLE transaksi
    ADD CONSTRAINT ck_transaksi_selesai_lunas
    CHECK (status_transaksi <> 'selesai' OR status_pembayaran = 'lunas');

-- Transaksi yang sudah diproses admin (disetujui/disewa/selesai) wajib punya id_admin
ALTER TABLE transaksi
    ADD CONSTRAINT ck_transaksi_admin
    CHECK (status_transaksi IN ('menunggu_verifikasi', 'dibatalkan') OR id_admin IS NOT NULL);

-- Transaksi disewa/selesai wajib punya waktu pengambilan aktual
ALTER TABLE transaksi
    ADD CONSTRAINT ck_transaksi_ambil
    CHECK (status_transaksi NOT IN ('disewa', 'selesai') OR waktu_ambil_aktual IS NOT NULL);

-- ---------------------------------------------------------------------
-- Indeks (PK dan UNIQUE sudah otomatis terindeks)
-- ---------------------------------------------------------------------

-- Katalog: filter barang per kategori dan status
CREATE INDEX idx_barang_kategori ON barang (id_kategori);
CREATE INDEX idx_barang_status   ON barang (status_barang);

-- Transaksi: FK dan pencarian umum
CREATE INDEX idx_transaksi_penyewa ON transaksi (id_penyewa);
CREATE INDEX idx_transaksi_admin   ON transaksi (id_admin);
CREATE INDEX idx_transaksi_denda   ON transaksi (id_denda);
CREATE INDEX idx_transaksi_status  ON transaksi (status_transaksi);

-- Pengecekan stok/bentrok jadwal: transaksi yang menahan stok
-- (checkout sudah menahan stok, jadi ikut status menunggu_verifikasi)
CREATE INDEX idx_transaksi_rentang
    ON transaksi (waktu_mulai_sewa, waktu_rencana_kembali)
    WHERE status_transaksi IN ('menunggu_verifikasi', 'menunggu_diambil', 'disewa');

-- Rekap pendapatan harian/bulanan
CREATE INDEX idx_transaksi_selesai
    ON transaksi (waktu_aktual_kembali)
    WHERE status_transaksi = 'selesai';

-- Detail: PK gabungan sudah mengindeks id_transaksi; tambahkan sisi id_barang
CREATE INDEX idx_detail_barang ON detail_transaksi (id_barang);

-- Jaminan: lookup per transaksi + maksimal 1 KTP per transaksi
CREATE INDEX idx_jaminan_transaksi ON jaminan (id_transaksi);
CREATE UNIQUE INDEX uq_jaminan_satu_ktp ON jaminan (id_transaksi) WHERE jenis_jaminan = 'KTP';

COMMIT;
