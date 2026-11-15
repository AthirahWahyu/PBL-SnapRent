-- =====================================================================
-- SnapRent - 03_views_functions_triggers.sql (PostgreSQL 12+)  [REVISI]
-- Logika bisnis di level database mengikuti alur 7 tahap:
--   1. Nomor transaksi otomatis (YYYYMMDD + urut)
--   2. Stok per rentang waktu (anti overbooking, mendukung jumlah unit)
--   3. Isi otomatis harga_satuan & subtotal, hitung ulang total tagihan + DP
--   4. Mesin status transaksi + aturan Hari-H, lunas, 3 ID jaminan
--   5. Prosedur alur admin: setujui, batalkan, mulai sewa, pengembalian,
--      kerusakan, pembayaran, kembalikan jaminan, selesai
--   6. Fungsi katalog & lacak pesanan, view dasbor/reminder/pendapatan
-- Jalankan setelah 01 dan 02.
--
-- Urutan pemakaian dari backend:
--   Checkout : BEGIN; INSERT penyewa (jika baru); INSERT transaksi;
--              INSERT detail_transaksi (urut id_barang); COMMIT;
--   Admin    : CALL sp_setujui_booking(id, id_admin);
--              -- H+1 tidak datang: CALL sp_batalkan_booking(id, id_admin);
--              INSERT 3 baris jaminan; UPDATE detail_transaksi SET kondisi_awal;
--              CALL sp_catat_pembayaran(id);      -- pelunasan sisa DP
--              CALL sp_mulai_sewa(id);
--              CALL sp_proses_pengembalian(id);   -- catat waktu kembali + denda telat
--              CALL sp_catat_kerusakan(id, id_barang, 'kondisi', biaya);  -- opsional
--              CALL sp_catat_pembayaran(id);      -- bayar denda/kerusakan
--              CALL sp_kembalikan_jaminan(id);
--              CALL sp_selesaikan_transaksi(id);
-- =====================================================================

-- ---------------------------------------------------------------------
-- 0. Helper: lama sewa (hari), dibulatkan ke atas per 24 jam, minimal 1
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_hitung_hari(p_mulai TIMESTAMP, p_kembali TIMESTAMP)
RETURNS INTEGER LANGUAGE sql IMMUTABLE AS $$
    SELECT GREATEST(1, CEIL(EXTRACT(EPOCH FROM (p_kembali - p_mulai))::NUMERIC / 86400)::INTEGER);
$$;

-- ---------------------------------------------------------------------
-- 1. Nomor transaksi otomatis: YYYYMMDD + urut 2 digit (mis. 2026111101)
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION trg_transaksi_before_insert() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
DECLARE
    v_prefix VARCHAR(8);
    v_urut   INTEGER;
BEGIN
    IF NEW.nomor_transaksi IS NULL THEN
        v_prefix := to_char(COALESCE(NEW.waktu_pemesanan, CURRENT_TIMESTAMP), 'YYYYMMDD');
        PERFORM pg_advisory_xact_lock(hashtext('nomor_transaksi' || v_prefix));
        SELECT COALESCE(MAX(SUBSTRING(nomor_transaksi FROM 9)::INTEGER), 0) + 1
          INTO v_urut
          FROM transaksi
         WHERE nomor_transaksi LIKE v_prefix || '%';
        NEW.nomor_transaksi := v_prefix || LPAD(v_urut::TEXT, 2, '0');
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_transaksi_before_insert
    BEFORE INSERT ON transaksi
    FOR EACH ROW EXECUTE FUNCTION trg_transaksi_before_insert();

-- ---------------------------------------------------------------------
-- 2. Stok per rentang waktu
--    Transaksi yang menahan stok: menunggu_verifikasi, menunggu_diambil, disewa.
--    Yang dihitung adalah PUNCAK pemakaian bersamaan (bukan penjumlahan buta).
--    Transaksi 'disewa' yang belum kembali dianggap menahan unit sampai
--    sekarang jika sudah lewat batas kembali.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_stok_terpakai(
    p_id_barang          INTEGER,
    p_mulai              TIMESTAMP,
    p_kembali            TIMESTAMP,
    p_kecuali_transaksi  INTEGER DEFAULT NULL
) RETURNS INTEGER
LANGUAGE sql STABLE AS $$
    WITH ov AS (
        SELECT t.waktu_mulai_sewa AS s,
               CASE WHEN t.status_transaksi = 'disewa' AND t.waktu_aktual_kembali IS NULL
                    THEN GREATEST(t.waktu_rencana_kembali, CURRENT_TIMESTAMP)
                    ELSE t.waktu_rencana_kembali
               END AS e,
               dt.jumlah
          FROM detail_transaksi dt
          JOIN transaksi t ON t.id_transaksi = dt.id_transaksi
         WHERE dt.id_barang = p_id_barang
           AND t.status_transaksi IN ('menunggu_verifikasi', 'menunggu_diambil', 'disewa')
           AND (p_kecuali_transaksi IS NULL OR t.id_transaksi <> p_kecuali_transaksi)
    ),
    ov2 AS (
        SELECT * FROM ov WHERE s < p_kembali AND p_mulai < e
    ),
    titik AS (
        SELECT p_mulai AS ts
        UNION
        SELECT s FROM ov2 WHERE s > p_mulai
    )
    SELECT COALESCE(MAX(x.total), 0)::INTEGER
      FROM (SELECT ti.ts, SUM(o.jumlah) AS total
              FROM titik ti
              JOIN ov2 o ON o.s <= ti.ts AND ti.ts < o.e
             GROUP BY ti.ts) x;
$$;

CREATE OR REPLACE FUNCTION fn_sisa_stok(
    p_id_barang          INTEGER,
    p_mulai              TIMESTAMP,
    p_kembali            TIMESTAMP,
    p_kecuali_transaksi  INTEGER DEFAULT NULL
) RETURNS INTEGER
LANGUAGE sql STABLE AS $$
    SELECT GREATEST(b.stok - fn_stok_terpakai(b.id_barang, p_mulai, p_kembali, p_kecuali_transaksi), 0)
      FROM barang b
     WHERE b.id_barang = p_id_barang;
$$;

-- ---------------------------------------------------------------------
-- 3. Trigger BEFORE INSERT/UPDATE pada detail_transaksi
--    - mengunci baris barang (serialisasi checkout bersamaan)
--    - menolak barang non-aktif atau stok tidak cukup
--    - mengisi harga_satuan (jika kosong) dan menghitung subtotal
--    Catatan backend: insert detail berurutan menurut id_barang agar tidak deadlock.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION trg_detail_before_write() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
DECLARE
    v_mulai       TIMESTAMP;
    v_kembali     TIMESTAMP;
    v_status_trx  VARCHAR(20);
    v_status_brg  VARCHAR(15);
    v_harga       NUMERIC(12,2);
    v_cek         BOOLEAN := TRUE;
BEGIN
    SELECT status_barang, harga_sewa_per_hari
      INTO v_status_brg, v_harga
      FROM barang
     WHERE id_barang = NEW.id_barang
       FOR NO KEY UPDATE;

    SELECT waktu_mulai_sewa, waktu_rencana_kembali, status_transaksi
      INTO v_mulai, v_kembali, v_status_trx
      FROM transaksi
     WHERE id_transaksi = NEW.id_transaksi;

    IF TG_OP = 'UPDATE' THEN
        v_cek := (NEW.id_barang <> OLD.id_barang
                  OR NEW.id_transaksi <> OLD.id_transaksi
                  OR NEW.jumlah <> OLD.jumlah);
    END IF;

    IF v_cek THEN
        IF v_status_brg IS NOT NULL AND v_status_brg <> 'aktif' THEN
            RAISE EXCEPTION 'Barang % tidak dapat disewa (status: %)', NEW.id_barang, v_status_brg;
        END IF;

        IF v_mulai IS NOT NULL
           AND v_status_trx IN ('menunggu_verifikasi', 'menunggu_diambil', 'disewa')
           AND NEW.jumlah > COALESCE(fn_sisa_stok(NEW.id_barang, v_mulai, v_kembali, NEW.id_transaksi), 0) THEN
            RAISE EXCEPTION 'Stok barang % tidak mencukupi pada rentang waktu tersebut (diminta: % unit)',
                NEW.id_barang, NEW.jumlah;
        END IF;
    END IF;

    IF NEW.harga_satuan IS NULL THEN
        NEW.harga_satuan := v_harga;
    END IF;

    NEW.subtotal := NEW.harga_satuan * NEW.jumlah * fn_hitung_hari(v_mulai, v_kembali);

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_detail_before_write
    BEFORE INSERT OR UPDATE ON detail_transaksi
    FOR EACH ROW EXECUTE FUNCTION trg_detail_before_write();

-- ---------------------------------------------------------------------
-- 4. Hitung ulang total_tagihan = SUM(subtotal) + denda + kerusakan
--    - nominal_bayar_awal (DP 50% / lunas) diperbarui selama menunggu_verifikasi
--    - status_pembayaran lunas/belum_lunas disinkronkan saat menunggu_diambil/disewa
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_refresh_total(p_id_transaksi INTEGER)
RETURNS VOID LANGUAGE sql AS $$
    UPDATE transaksi t
       SET total_tagihan = x.sewa + t.nominal_denda + t.biaya_kerusakan,
           nominal_bayar_awal = CASE
               WHEN t.status_transaksi = 'menunggu_verifikasi' THEN
                   CASE WHEN t.skema_pembayaran = 'dp_50' THEN ROUND(x.sewa * 0.5, 0) ELSE x.sewa END
               ELSE t.nominal_bayar_awal
           END,
           status_pembayaran = CASE
               WHEN t.status_transaksi IN ('menunggu_diambil', 'disewa') THEN
                   CASE WHEN t.jumlah_dibayar >= x.sewa + t.nominal_denda + t.biaya_kerusakan
                        THEN 'lunas' ELSE 'belum_lunas' END
               ELSE t.status_pembayaran
           END
      FROM (SELECT COALESCE(SUM(dt.subtotal), 0) AS sewa
              FROM detail_transaksi dt
             WHERE dt.id_transaksi = p_id_transaksi) x
     WHERE t.id_transaksi = p_id_transaksi;
$$;

CREATE OR REPLACE FUNCTION trg_detail_after_write() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        PERFORM fn_refresh_total(OLD.id_transaksi);
    ELSE
        PERFORM fn_refresh_total(NEW.id_transaksi);
    END IF;
    RETURN NULL;
END;
$$;

CREATE TRIGGER trg_detail_after_write
    AFTER INSERT OR UPDATE OR DELETE ON detail_transaksi
    FOR EACH ROW EXECUTE FUNCTION trg_detail_after_write();

-- ---------------------------------------------------------------------
-- 5. Mesin status transaksi (BEFORE UPDATE pada transaksi)
--    Transisi sah:
--      menunggu_verifikasi -> menunggu_diambil | dibatalkan
--      menunggu_diambil    -> disewa | dibatalkan (hanya setelah lewat Hari-H)
--      disewa              -> selesai
--    Syarat:
--      disewa  : ambil di Hari-H, status_pembayaran lunas, >= 3 jaminan ditahan
--                termasuk 1 KTP
--      selesai : lunas, semua jaminan sudah dikembalikan
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION trg_transaksi_before_update() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
DECLARE
    v_det        RECORD;
    v_ditahan    INTEGER;
    v_ktp        INTEGER;
BEGIN
    IF NEW.status_transaksi <> OLD.status_transaksi THEN

        IF NOT (   (OLD.status_transaksi = 'menunggu_verifikasi' AND NEW.status_transaksi IN ('menunggu_diambil', 'dibatalkan'))
                OR (OLD.status_transaksi = 'menunggu_diambil'    AND NEW.status_transaksi IN ('disewa', 'dibatalkan'))
                OR (OLD.status_transaksi = 'disewa'              AND NEW.status_transaksi = 'selesai')) THEN
            RAISE EXCEPTION 'Perubahan status % -> % tidak diizinkan', OLD.status_transaksi, NEW.status_transaksi;
        END IF;

        IF NEW.status_transaksi = 'dibatalkan'
           AND OLD.status_transaksi = 'menunggu_diambil'
           AND CURRENT_DATE <= OLD.waktu_mulai_sewa::DATE THEN
            RAISE EXCEPTION 'Booking belum melewati batas ambil (Hari-H) dan belum dapat dibatalkan';
        END IF;

        IF NEW.status_transaksi = 'disewa' THEN
            IF NEW.waktu_ambil_aktual IS NULL
               OR NEW.waktu_ambil_aktual::DATE <> NEW.waktu_mulai_sewa::DATE THEN
                RAISE EXCEPTION 'Pengambilan barang wajib pada Hari-H tanggal mulai sewa (%)', NEW.waktu_mulai_sewa::DATE;
            END IF;
            IF NEW.status_pembayaran <> 'lunas' THEN
                RAISE EXCEPTION 'Tagihan harus lunas sebelum sewa dimulai';
            END IF;
            SELECT COUNT(*) FILTER (WHERE status_jaminan = 'ditahan'),
                   COUNT(*) FILTER (WHERE status_jaminan = 'ditahan' AND jenis_jaminan = 'KTP')
              INTO v_ditahan, v_ktp
              FROM jaminan
             WHERE id_transaksi = NEW.id_transaksi;
            IF v_ditahan < 3 OR v_ktp < 1 THEN
                RAISE EXCEPTION 'Jaminan belum lengkap: butuh 1 KTP asli + 2 kartu identitas pendukung (tercatat: % kartu, % KTP)',
                    v_ditahan, v_ktp;
            END IF;
        END IF;

        IF NEW.status_transaksi = 'selesai' THEN
            IF NEW.status_pembayaran <> 'lunas' THEN
                RAISE EXCEPTION 'Tagihan (termasuk denda/kerusakan) belum lunas';
            END IF;
            IF EXISTS (SELECT 1 FROM jaminan
                        WHERE id_transaksi = NEW.id_transaksi AND status_jaminan = 'ditahan') THEN
                RAISE EXCEPTION 'Masih ada jaminan yang ditahan; kembalikan terlebih dahulu';
            END IF;
        END IF;
    END IF;

    -- Jadwal berubah pada transaksi yang menahan stok -> cek ulang stok semua barang
    IF NEW.status_transaksi IN ('menunggu_verifikasi', 'menunggu_diambil', 'disewa')
       AND (NEW.waktu_mulai_sewa <> OLD.waktu_mulai_sewa
            OR NEW.waktu_rencana_kembali <> OLD.waktu_rencana_kembali) THEN
        FOR v_det IN
            SELECT id_barang, jumlah FROM detail_transaksi WHERE id_transaksi = NEW.id_transaksi
        LOOP
            IF v_det.jumlah > COALESCE(fn_sisa_stok(v_det.id_barang, NEW.waktu_mulai_sewa,
                                                    NEW.waktu_rencana_kembali, NEW.id_transaksi), 0) THEN
                RAISE EXCEPTION 'Stok barang % tidak mencukupi pada jadwal baru', v_det.id_barang;
            END IF;
        END LOOP;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_transaksi_before_update
    BEFORE UPDATE ON transaksi
    FOR EACH ROW EXECUTE FUNCTION trg_transaksi_before_update();

-- Jadwal berubah -> subtotal semua detail dihitung ulang (ikut memicu refresh total)
CREATE OR REPLACE FUNCTION trg_transaksi_after_update() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.waktu_mulai_sewa <> OLD.waktu_mulai_sewa
       OR NEW.waktu_rencana_kembali <> OLD.waktu_rencana_kembali THEN
        UPDATE detail_transaksi SET harga_satuan = harga_satuan
         WHERE id_transaksi = NEW.id_transaksi;
    END IF;
    RETURN NULL;
END;
$$;

CREATE TRIGGER trg_transaksi_after_update
    AFTER UPDATE ON transaksi
    FOR EACH ROW EXECUTE FUNCTION trg_transaksi_after_update();

-- ---------------------------------------------------------------------
-- 6. Prosedur alur admin
-- ---------------------------------------------------------------------

-- Tahap 4: setujui booking (bukti bayar valid) -> "Tersewa, menunggu diambil"
CREATE OR REPLACE PROCEDURE sp_setujui_booking(p_id_transaksi INTEGER, p_id_admin INTEGER)
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE transaksi
       SET status_transaksi  = 'menunggu_diambil',
           id_admin          = p_id_admin,
           jumlah_dibayar    = nominal_bayar_awal,
           status_pembayaran = CASE WHEN nominal_bayar_awal >= total_tagihan THEN 'lunas' ELSE 'belum_lunas' END
     WHERE id_transaksi = p_id_transaksi
       AND status_transaksi = 'menunggu_verifikasi';
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Transaksi % tidak ditemukan atau tidak berstatus menunggu_verifikasi', p_id_transaksi;
    END IF;
END;
$$;

-- Tahap 4: batalkan booking.
--   dari menunggu_verifikasi : bukti ditolak (pembayaran 'ditolak')
--   dari menunggu_diambil    : hanya setelah lewat Hari-H (pembayaran 'hangus')
-- Stok otomatis bebas karena status tidak lagi menahan stok.
CREATE OR REPLACE PROCEDURE sp_batalkan_booking(p_id_transaksi INTEGER, p_id_admin INTEGER)
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE transaksi
       SET status_pembayaran = CASE WHEN status_transaksi = 'menunggu_verifikasi'
                                    THEN 'ditolak' ELSE 'hangus' END,
           status_transaksi  = 'dibatalkan',
           id_admin          = p_id_admin
     WHERE id_transaksi = p_id_transaksi
       AND status_transaksi IN ('menunggu_verifikasi', 'menunggu_diambil');
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Transaksi % tidak ditemukan atau tidak dapat dibatalkan', p_id_transaksi;
    END IF;
END;
$$;

-- Tahap 5/7: catat pembayaran terverifikasi di toko (pelunasan DP, denda, kerusakan).
-- p_nominal NULL = bayar seluruh sisa tagihan.
CREATE OR REPLACE PROCEDURE sp_catat_pembayaran(p_id_transaksi INTEGER, p_nominal NUMERIC DEFAULT NULL)
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE transaksi
       SET jumlah_dibayar = jumlah_dibayar + COALESCE(p_nominal, GREATEST(total_tagihan - jumlah_dibayar, 0))
     WHERE id_transaksi = p_id_transaksi
       AND status_transaksi IN ('menunggu_diambil', 'disewa');
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Pembayaran hanya dapat dicatat untuk transaksi menunggu_diambil/disewa (id %)', p_id_transaksi;
    END IF;
    PERFORM fn_refresh_total(p_id_transaksi);
END;
$$;

-- Tahap 5: klik "Mulai Sewa". Syarat (Hari-H, lunas, 3 ID) divalidasi trigger.
-- waktu_mulai_sewa & waktu_rencana_kembali tetap terkunci seperti pada booking;
-- waktu pengambilan sebenarnya dicatat di waktu_ambil_aktual.
CREATE OR REPLACE PROCEDURE sp_mulai_sewa(
    p_id_transaksi  INTEGER,
    p_waktu_ambil   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE transaksi
       SET status_transaksi   = 'disewa',
           waktu_ambil_aktual = p_waktu_ambil
     WHERE id_transaksi = p_id_transaksi
       AND status_transaksi = 'menunggu_diambil';
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Transaksi % tidak ditemukan atau tidak berstatus menunggu_diambil', p_id_transaksi;
    END IF;
END;
$$;

-- Tahap 7: input waktu kembali + denda keterlambatan progresif (status tetap disewa
-- sampai admin klik Selesai). Aturan dicocokkan dari tabel denda:
--   batas_min_jam < keterlambatan (jam) <= batas_max_jam
--   nominal_tetap -> nilai_denda ; sewa_harian -> SUM(harga_satuan x jumlah) 1 hari penuh
CREATE OR REPLACE PROCEDURE sp_proses_pengembalian(
    p_id_transaksi   INTEGER,
    p_waktu_kembali  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
LANGUAGE plpgsql AS $$
DECLARE
    v_rencana    TIMESTAMP;
    v_status     VARCHAR(20);
    v_telat_jam  NUMERIC;
    v_aturan     denda%ROWTYPE;
    v_id_denda   INTEGER := NULL;
    v_nominal    NUMERIC(12,2) := 0;
BEGIN
    SELECT waktu_rencana_kembali, status_transaksi
      INTO v_rencana, v_status
      FROM transaksi
     WHERE id_transaksi = p_id_transaksi
       FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Transaksi % tidak ditemukan', p_id_transaksi;
    END IF;

    IF v_status <> 'disewa' THEN
        RAISE EXCEPTION 'Hanya transaksi berstatus disewa yang dapat dikembalikan (status saat ini: %)', v_status;
    END IF;

    v_telat_jam := GREATEST(0, EXTRACT(EPOCH FROM (p_waktu_kembali - v_rencana))::NUMERIC / 3600);

    SELECT * INTO v_aturan
      FROM denda d
     WHERE v_telat_jam > d.batas_min_jam
       AND (d.batas_max_jam IS NULL OR v_telat_jam <= d.batas_max_jam)
     ORDER BY d.batas_min_jam DESC
     LIMIT 1;

    IF FOUND THEN
        v_id_denda := v_aturan.id_denda;
        IF v_aturan.jenis_denda = 'nominal_tetap' THEN
            v_nominal := v_aturan.nilai_denda;
        ELSE
            SELECT COALESCE(SUM(harga_satuan * jumlah), 0) INTO v_nominal
              FROM detail_transaksi
             WHERE id_transaksi = p_id_transaksi;
        END IF;
    END IF;

    UPDATE transaksi
       SET waktu_aktual_kembali = p_waktu_kembali,
           id_denda             = v_id_denda,
           nominal_denda        = v_nominal
     WHERE id_transaksi = p_id_transaksi;

    PERFORM fn_refresh_total(p_id_transaksi);
END;
$$;

-- Tahap 7: catat hasil cek fisik akhir per barang. Memperbarui kondisi_akhir pada detail,
-- kondisi_fisik (dan opsional status_barang, mis. 'rusak') pada barang, dan menambah
-- biaya perbaikan/penggantian ke transaksi.
CREATE OR REPLACE PROCEDURE sp_catat_kerusakan(
    p_id_transaksi    INTEGER,
    p_id_barang       INTEGER,
    p_kondisi_akhir   VARCHAR,
    p_biaya           NUMERIC DEFAULT 0,
    p_status_barang   VARCHAR DEFAULT NULL
)
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE detail_transaksi
       SET kondisi_akhir = p_kondisi_akhir
     WHERE id_transaksi = p_id_transaksi AND id_barang = p_id_barang;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Barang % tidak ada pada transaksi %', p_id_barang, p_id_transaksi;
    END IF;

    UPDATE barang
       SET kondisi_fisik = p_kondisi_akhir,
           status_barang = COALESCE(p_status_barang, status_barang)
     WHERE id_barang = p_id_barang;

    UPDATE transaksi
       SET biaya_kerusakan   = biaya_kerusakan + COALESCE(p_biaya, 0),
           catatan_kerusakan = concat_ws(E'\n', catatan_kerusakan, p_kondisi_akhir)
     WHERE id_transaksi = p_id_transaksi
       AND status_transaksi = 'disewa';
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Kerusakan hanya dapat dicatat pada transaksi berstatus disewa';
    END IF;

    PERFORM fn_refresh_total(p_id_transaksi);
END;
$$;

-- Tahap 7: kembalikan seluruh jaminan (ditahan -> dikembalikan)
CREATE OR REPLACE PROCEDURE sp_kembalikan_jaminan(p_id_transaksi INTEGER)
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE jaminan
       SET status_jaminan     = 'dikembalikan',
           waktu_dikembalikan = CURRENT_TIMESTAMP
     WHERE id_transaksi = p_id_transaksi
       AND status_jaminan = 'ditahan';
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Tidak ada jaminan berstatus ditahan pada transaksi %', p_id_transaksi;
    END IF;
END;
$$;

-- Tahap 7: klik "Selesai". Syarat lunas & jaminan kembali divalidasi trigger.
CREATE OR REPLACE PROCEDURE sp_selesaikan_transaksi(p_id_transaksi INTEGER)
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE transaksi
       SET status_transaksi = 'selesai'
     WHERE id_transaksi = p_id_transaksi
       AND status_transaksi = 'disewa'
       AND waktu_aktual_kembali IS NOT NULL;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Transaksi % harus berstatus disewa dan sudah melalui sp_proses_pengembalian', p_id_transaksi;
    END IF;
END;
$$;

-- ---------------------------------------------------------------------
-- 7. Katalog: barang yang masih punya sisa stok pada rentang waktu tertentu
--    Pemakaian: SELECT * FROM fn_barang_tersedia('2026-11-12 09:00', '2026-11-14 09:00');
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_barang_tersedia(
    p_mulai        TIMESTAMP,
    p_kembali      TIMESTAMP,
    p_id_kategori  INTEGER DEFAULT NULL
) RETURNS TABLE (
    id_barang            INTEGER,
    id_kategori          INTEGER,
    nama_barang          VARCHAR,
    foto_barang          VARCHAR,
    harga_sewa_per_hari  NUMERIC,
    sisa_stok            INTEGER
)
LANGUAGE sql STABLE AS $$
    SELECT x.id_barang, x.id_kategori, x.nama_barang, x.foto_barang, x.harga_sewa_per_hari, x.sisa
      FROM (SELECT b.id_barang, b.id_kategori, b.nama_barang, b.foto_barang, b.harga_sewa_per_hari,
                   fn_sisa_stok(b.id_barang, p_mulai, p_kembali) AS sisa
              FROM barang b
             WHERE b.status_barang = 'aktif'
               AND (p_id_kategori IS NULL OR b.id_kategori = p_id_kategori)) x
     WHERE x.sisa > 0
     ORDER BY x.nama_barang;
$$;

-- ---------------------------------------------------------------------
-- 8. Lacak pesanan (customer): kombinasi nomor transaksi + nomor WhatsApp
--    Pemakaian: SELECT * FROM fn_lacak_pesanan('2026111101', '08123456789');
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_lacak_pesanan(p_nomor VARCHAR, p_no_telepon VARCHAR)
RETURNS TABLE (
    nomor_transaksi        VARCHAR,
    nama_penyewa           VARCHAR,
    status_transaksi       VARCHAR,
    status_pembayaran      VARCHAR,
    waktu_mulai_sewa       TIMESTAMP,
    waktu_rencana_kembali  TIMESTAMP,
    waktu_aktual_kembali   TIMESTAMP,
    total_tagihan          NUMERIC,
    jumlah_dibayar         NUMERIC,
    sisa_tagihan           NUMERIC,
    nominal_denda          NUMERIC,
    biaya_kerusakan        NUMERIC
)
LANGUAGE sql STABLE AS $$
    SELECT t.nomor_transaksi, p.nama_penyewa, t.status_transaksi, t.status_pembayaran,
           t.waktu_mulai_sewa, t.waktu_rencana_kembali, t.waktu_aktual_kembali,
           t.total_tagihan, t.jumlah_dibayar,
           GREATEST(t.total_tagihan - t.jumlah_dibayar, 0),
           t.nominal_denda, t.biaya_kerusakan
      FROM transaksi t
      JOIN penyewa p ON p.id_penyewa = t.id_penyewa
     WHERE t.nomor_transaksi = p_nomor
       AND p.no_telepon = p_no_telepon;
$$;

-- ---------------------------------------------------------------------
-- 9. View dasbor admin
--    hijau = selesai | kuning = disewa & berjalan | merah = disewa & lewat tenggat
--    lewat_batas_ambil = menunggu_diambil dan CURRENT_DATE > tanggal mulai sewa
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_dasbor_admin AS
SELECT t.id_transaksi,
       t.nomor_transaksi,
       p.nama_penyewa,
       p.no_telepon,
       t.status_transaksi,
       t.status_pembayaran,
       t.skema_pembayaran,
       t.waktu_mulai_sewa,
       t.waktu_rencana_kembali,
       t.waktu_aktual_kembali,
       t.total_tagihan,
       t.jumlah_dibayar,
       GREATEST(t.total_tagihan - t.jumlah_dibayar, 0) AS sisa_tagihan,
       (t.status_transaksi = 'menunggu_diambil'
            AND CURRENT_DATE > t.waktu_mulai_sewa::DATE) AS lewat_batas_ambil,
       (t.status_transaksi = 'disewa' AND t.waktu_aktual_kembali IS NULL
            AND CURRENT_TIMESTAMP >= t.waktu_rencana_kembali - INTERVAL '1 hour'
            AND CURRENT_TIMESTAMP <= t.waktu_rencana_kembali) AS mendekati_batas,
       CASE WHEN t.status_transaksi = 'disewa' THEN
            ROUND(GREATEST(0, EXTRACT(EPOCH FROM (
                COALESCE(t.waktu_aktual_kembali, CURRENT_TIMESTAMP) - t.waktu_rencana_kembali))::NUMERIC / 3600), 2)
       END AS jam_terlambat,
       CASE
           WHEN t.status_transaksi = 'selesai' THEN 'hijau'
           WHEN t.status_transaksi = 'disewa'
                AND COALESCE(t.waktu_aktual_kembali, CURRENT_TIMESTAMP) > t.waktu_rencana_kembali THEN 'merah'
           WHEN t.status_transaksi = 'disewa' THEN 'kuning'
           ELSE 'normal'
       END AS level_baris
  FROM transaksi t
  JOIN penyewa p ON p.id_penyewa = t.id_penyewa;

-- Reminder pengembalian (hanya yang masih berjalan di tangan penyewa)
CREATE OR REPLACE VIEW v_reminder_pengembalian AS
SELECT id_transaksi, nomor_transaksi, nama_penyewa, no_telepon,
       waktu_rencana_kembali, level_baris AS level_reminder, mendekati_batas, jam_terlambat
  FROM v_dasbor_admin
 WHERE status_transaksi = 'disewa' AND waktu_aktual_kembali IS NULL
 ORDER BY waktu_rencana_kembali;

-- Status barang real-time: tersedia / menunggu_diambil / disewa (atau status master jika bukan aktif)
CREATE OR REPLACE VIEW v_status_barang_live AS
SELECT y.id_barang, y.nama_barang, y.stok, y.unit_disewa, y.unit_menunggu_diambil,
       GREATEST(y.stok - y.unit_disewa - y.unit_menunggu_diambil, 0) AS unit_tersedia,
       CASE
           WHEN y.status_barang <> 'aktif' THEN y.status_barang
           WHEN y.unit_disewa > 0 THEN 'disewa'
           WHEN y.unit_menunggu_diambil > 0 THEN 'menunggu_diambil'
           ELSE 'tersedia'
       END AS status_live
  FROM (SELECT b.id_barang, b.nama_barang, b.stok, b.status_barang,
               COALESCE(SUM(dt.jumlah) FILTER (
                   WHERE t.status_transaksi = 'disewa' AND t.waktu_aktual_kembali IS NULL), 0)::INTEGER AS unit_disewa,
               COALESCE(SUM(dt.jumlah) FILTER (
                   WHERE t.status_transaksi IN ('menunggu_verifikasi', 'menunggu_diambil')
                     AND t.waktu_mulai_sewa::DATE <= CURRENT_DATE
                     AND t.waktu_rencana_kembali > CURRENT_TIMESTAMP), 0)::INTEGER AS unit_menunggu_diambil
          FROM barang b
          LEFT JOIN detail_transaksi dt ON dt.id_barang = b.id_barang
          LEFT JOIN transaksi t ON t.id_transaksi = dt.id_transaksi
         GROUP BY b.id_barang, b.nama_barang, b.stok, b.status_barang) y;

-- Rekapan pendapatan (transaksi selesai, termasuk denda & kerusakan)
CREATE OR REPLACE VIEW v_pendapatan_harian AS
SELECT waktu_aktual_kembali::DATE AS tanggal,
       COUNT(*)                   AS jumlah_transaksi,
       SUM(total_tagihan)         AS pendapatan
  FROM transaksi
 WHERE status_transaksi = 'selesai'
 GROUP BY waktu_aktual_kembali::DATE;

CREATE OR REPLACE VIEW v_pendapatan_bulanan AS
SELECT date_trunc('month', waktu_aktual_kembali)::DATE AS bulan,
       COUNT(*)                                         AS jumlah_transaksi,
       SUM(total_tagihan)                               AS pendapatan
  FROM transaksi
 WHERE status_transaksi = 'selesai'
 GROUP BY date_trunc('month', waktu_aktual_kembali);
