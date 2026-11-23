BEGIN;

-- 1. Tambah kolom-kolom baru pada tabel detail_transaksi
ALTER TABLE detail_transaksi
    ADD COLUMN IF NOT EXISTS lama_hari INTEGER NOT NULL DEFAULT 1 CHECK (lama_hari >= 1),
    ADD COLUMN IF NOT EXISTS waktu_rencana_kembali TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS waktu_aktual_kembali TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS status_item VARCHAR(20) NOT NULL DEFAULT 'disewa'
        CHECK (status_item IN ('disewa', 'kembali')),
    ADD COLUMN IF NOT EXISTS nominal_denda NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (nominal_denda >= 0);

-- Isi waktu_rencana_kembali untuk data lama yang sudah ada di detail_transaksi
UPDATE detail_transaksi dt
   SET waktu_rencana_kembali = t.waktu_rencana_kembali,
       waktu_aktual_kembali  = t.waktu_aktual_kembali,
       status_item           = CASE WHEN t.status_transaksi = 'selesai' THEN 'kembali' ELSE 'disewa' END
  FROM transaksi t
 WHERE dt.id_transaksi = t.id_transaksi
   AND dt.waktu_rencana_kembali IS NULL;

-- 2. Perbarui Trigger BEFORE WRITE pada detail_transaksi
--    - Subtotal = harga_satuan * jumlah * lama_hari
--    - waktu_rencana_kembali per item otomatis diisi waktu_mulai_sewa + lama_hari jika belum diset
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
                  OR NEW.jumlah <> OLD.jumlah
                  OR NEW.lama_hari <> OLD.lama_hari);
    END IF;

    -- Tentukan waktu rencana kembali untuk item ini
    IF NEW.waktu_rencana_kembali IS NULL AND v_mulai IS NOT NULL THEN
        NEW.waktu_rencana_kembali := v_mulai + (NEW.lama_hari || ' days')::INTERVAL;
    END IF;

    IF v_cek THEN
        IF v_status_brg IS NOT NULL AND v_status_brg <> 'aktif' THEN
            RAISE EXCEPTION 'Barang % tidak dapat disewa (status: %)', NEW.id_barang, v_status_brg;
        END IF;

        IF v_mulai IS NOT NULL
           AND v_status_trx IN ('menunggu_verifikasi', 'menunggu_diambil', 'disewa')
           AND NEW.jumlah > COALESCE(fn_sisa_stok(NEW.id_barang, v_mulai, COALESCE(NEW.waktu_rencana_kembali, v_kembali), NEW.id_transaksi), 0) THEN
            RAISE EXCEPTION 'Stok barang % tidak mencukupi pada rentang waktu tersebut (diminta: % unit)',
                NEW.id_barang, NEW.jumlah;
        END IF;
    END IF;

    IF NEW.harga_satuan IS NULL THEN
        NEW.harga_satuan := v_harga;
    END IF;

    -- Subtotal kini dikalikan dengan lama_hari per item
    NEW.subtotal := NEW.harga_satuan * NEW.jumlah * GREATEST(1, COALESCE(NEW.lama_hari, 1));

    RETURN NEW;
END;
$$;

-- 3. Perbarui Fungsi fn_stok_terpakai
--    Item yang status_item = 'kembali' dan sudah ada waktu_aktual_kembali 
--    hanya menahan stok sampai waktu_aktual_kembali tersebut, sehingga stok langsung bebas kembali!
CREATE OR REPLACE FUNCTION fn_stok_terpakai(
    p_id_barang          INTEGER,
    p_mulai              TIMESTAMP,
    p_kembali            TIMESTAMP,
    p_kecuali_transaksi  INTEGER DEFAULT NULL
) RETURNS INTEGER
LANGUAGE sql STABLE AS $$
    WITH ov AS (
        SELECT t.waktu_mulai_sewa AS s,
               CASE 
                   -- Jika item ini sudah dikembalikan secara bertahap:
                   WHEN dt.status_item = 'kembali' AND dt.waktu_aktual_kembali IS NOT NULL
                   THEN dt.waktu_aktual_kembali
                   -- Jika transaksi masih disewa dan belum kembali
                   WHEN t.status_transaksi = 'disewa' AND dt.waktu_aktual_kembali IS NULL
                   THEN GREATEST(COALESCE(dt.waktu_rencana_kembali, t.waktu_rencana_kembali), CURRENT_TIMESTAMP)
                   -- Normal: sampai waktu rencana kembali item
                   ELSE COALESCE(dt.waktu_rencana_kembali, t.waktu_rencana_kembali)
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

-- 4. Stored Procedure untuk Mengembalikan Barang Secara Bertahap Per Item
--    Admin memanggil ini saat satu barang tertentu diserahkan customer
CREATE OR REPLACE PROCEDURE sp_kembalikan_item(
    p_id_transaksi    INTEGER,
    p_id_barang       INTEGER,
    p_waktu_kembali   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    p_kondisi_akhir   VARCHAR DEFAULT NULL,
    p_biaya_kerusakan NUMERIC DEFAULT 0
)
LANGUAGE plpgsql AS $$
DECLARE
    v_rencana_item TIMESTAMP;
    v_telat_jam    NUMERIC;
    v_status_trx   VARCHAR(20);
    v_aturan       denda%ROWTYPE;
    v_denda_item   NUMERIC(12,2) := 0;
    v_harga_satuan NUMERIC(12,2);
    v_jumlah       INTEGER;
    v_masih_ada    INTEGER;
BEGIN
    SELECT status_transaksi INTO v_status_trx
      FROM transaksi
     WHERE id_transaksi = p_id_transaksi
       FOR UPDATE;

    IF v_status_trx <> 'disewa' THEN
        RAISE EXCEPTION 'Hanya transaksi berstatus disewa yang dapat diproses pengembaliannya (status: %)', v_status_trx;
    END IF;

    SELECT waktu_rencana_kembali, harga_satuan, jumlah
      INTO v_rencana_item, v_harga_satuan, v_jumlah
      FROM detail_transaksi
     WHERE id_transaksi = p_id_transaksi AND id_barang = p_id_barang
       FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Barang % tidak ditemukan pada transaksi %', p_id_barang, p_id_transaksi;
    END IF;

    -- Hitung keterlambatan untuk item ini
    v_telat_jam := GREATEST(0, EXTRACT(EPOCH FROM (p_waktu_kembali - v_rencana_item))::NUMERIC / 3600);

    IF v_telat_jam > 0 THEN
        SELECT * INTO v_aturan
          FROM denda d
         WHERE v_telat_jam > d.batas_min_jam
           AND (d.batas_max_jam IS NULL OR v_telat_jam <= d.batas_max_jam)
         ORDER BY d.batas_min_jam DESC
         LIMIT 1;

        IF FOUND THEN
            IF v_aturan.jenis_denda = 'nominal_tetap' THEN
                v_denda_item := v_aturan.nilai_denda;
            ELSE
                v_denda_item := v_harga_satuan * v_jumlah; -- 1 hari sewa untuk item ini
            END IF;
        END IF;
    END IF;

    -- Update baris detail_transaksi untuk item ini
    UPDATE detail_transaksi
       SET status_item          = 'kembali',
           waktu_aktual_kembali = p_waktu_kembali,
           nominal_denda        = v_denda_item,
           kondisi_akhir        = COALESCE(p_kondisi_akhir, kondisi_akhir)
     WHERE id_transaksi = p_id_transaksi AND id_barang = p_id_barang;

    -- Update kondisi fisik barang jika ada
    IF p_kondisi_akhir IS NOT NULL THEN
        UPDATE barang
           SET kondisi_fisik = p_kondisi_akhir
         WHERE id_barang = p_id_barang;
    END IF;

    -- Update total denda & biaya kerusakan di transaksi induk
    UPDATE transaksi
       SET nominal_denda   = (SELECT COALESCE(SUM(nominal_denda), 0) FROM detail_transaksi WHERE id_transaksi = p_id_transaksi),
           biaya_kerusakan = biaya_kerusakan + GREATEST(0, COALESCE(p_biaya_kerusakan, 0))
     WHERE id_transaksi = p_id_transaksi;

    -- Cek apakah seluruh barang dalam transaksi ini sudah kembali
    SELECT COUNT(*) INTO v_masih_ada
      FROM detail_transaksi
     WHERE id_transaksi = p_id_transaksi AND status_item <> 'kembali';

    -- Jika semua barang sudah kembali, catat waktu aktual kembali transaksi induk
    IF v_masih_ada = 0 THEN
        UPDATE transaksi
           SET waktu_aktual_kembali = p_waktu_kembali
         WHERE id_transaksi = p_id_transaksi;
    END IF;

    PERFORM fn_refresh_total(p_id_transaksi);
END;
$$;

COMMIT;
