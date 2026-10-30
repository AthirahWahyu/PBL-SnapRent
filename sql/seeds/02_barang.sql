-- SnapRent - seed barang (173 item) dari pricelist DUDES Camera Rent
-- Tarif memakai kolom '1 HARI'. Tiap item di-seed sebagai 1 unit; tambah unit lain lewat INSERT biasa.
-- Jalankan setelah 01_kategori.sql.

BEGIN;

-- Lighting Equipment (8 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('Reflektor 5 in 1', 20000, NULL),
    ('Filter Cokin ND/GND', 20000, NULL),
    ('Variable ND Filter K&F (82mm)', 20000, NULL),
    ('Softbox Godox Square 60x90', 30000, NULL),
    ('Softbox Godox Octa 120', 40000, NULL),
    ('Payung Flash', 5000, NULL),
    ('Godox S Bracket', 10000, NULL),
    ('Godox Projection SA Lens 85mm', 120000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Lighting Equipment';

-- Video Equipment (6 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('Clapper Board', 5000, NULL),
    ('Kabel HDMI 10m', 15000, NULL),
    ('Hollyland Mars 400S Pro (Video Transmitter)', 150000, NULL),
    ('Monitor Godox GM55 5 inch', 50000, NULL),
    ('Monitor Feelworld Master 7 inch', 50000, NULL),
    ('HDMI Capture (HDMI to USB)', 15000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Video Equipment';

-- Audio - Recorder (17 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('Takstar SGC-598 Videomic', 25000, NULL),
    ('Godox VD Mic', 25000, NULL),
    ('RODE Videomic GO', 25000, NULL),
    ('RODE Videomic Micro', 25000, NULL),
    ('RODE NTG 4+ Condenser Mic', 75000, NULL),
    ('Clip On Boya WM4 (2 mic)', 50000, NULL),
    ('Clip On Godox Movelink M2 (2 mic)', 60000, NULL),
    ('Clip On Saramonic Blink B2 (2 mic)', 60000, NULL),
    ('Clip On Hollyland Lark M2 Combo (2 mic)', 90000, NULL),
    ('Clip On DJI Mic Mini 2s', 110000, NULL),
    ('Kabel XLR Audio', 10000, NULL),
    ('Boompole 3m', 25000, NULL),
    ('Zoom H1N Pro', 50000, NULL),
    ('Zoom H4N Audio Recorder', 70000, NULL),
    ('Zoom H8N', 150000, NULL),
    ('Headset AudioTechnica M40x', 25000, NULL),
    ('HT WLN (Handy Talky)', 15000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Audio - Recorder';

-- Battery & Memory (15 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('Sony NP-FW50 Battery (KINGMA)', 20000, NULL),
    ('Sony NP-FZ100 Battery (KINGMA)', 20000, NULL),
    ('Fujifilm NP-W126 Battery', 20000, NULL),
    ('Canon LP-E6 Battery (60D,6D,5D)', 20000, NULL),
    ('Canon LP-E8 Battery (700D)', 20000, NULL),
    ('Sony NP-F570 (Monitor/LED)', 20000, NULL),
    ('DJI Mini 3 Battery', 100000, NULL),
    ('DJI Phantom 4 Pro Battery', 125000, NULL),
    ('DJI Mavic Pro Battery', 125000, NULL),
    ('Baterai AA Rechargeable (4pcs)', 10000, NULL),
    ('V-Mount Battery 154WH', 50000, NULL),
    ('Smallrig V-Mount Batt Distributor', 50000, NULL),
    ('Memori SD Card 16 GB (Sandisk Ultra)', 15000, NULL),
    ('Memori SD Card 32 GB (Lexar)', 25000, NULL),
    ('Memori SD Card 64 GB (Lexar/VGen)', 30000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Battery & Memory';

-- Equipment (14 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('Lightstand Kecil (1,8m)', 5000, NULL),
    ('Lightstand Besar (2,8m)', 10000, NULL),
    ('Stand Background (3,3m)', 20000, NULL),
    ('C Stand', 25000, NULL),
    ('Sandbag 2pc', 10000, NULL),
    ('Tripod Standard Takara Eco', 20000, NULL),
    ('Tripod Video Bilico', 40000, NULL),
    ('Tripod Video Takara', 40000, NULL),
    ('Tripod Beike QZSD (monopod & horizontal)', 30000, NULL),
    ('Video Monopod Yungteng / Jieyang', 20000, NULL),
    ('RIG Andoer C500 for Sony', 50000, NULL),
    ('Slider Jieyang Carbon (100cm)', 80000, NULL),
    ('Magic Arm 11 inch', 5000, NULL),
    ('Head Strap / Chest Strap GoPro', 10000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Equipment';

-- Lighting Video (7 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('Yongnuo YN 300 Air (-BATT)', 40000, NULL),
    ('Yongnuo YN 300 LED Video (-BATT)', 40000, NULL),
    ('Godox LED 260c (-BATT)', 40000, NULL),
    ('Photoolex Portable Zoomlight', 40000, NULL),
    ('GVM LED Studio 800d (RGB)', 80000, NULL),
    ('Godox SL150w ii', 110000, NULL),
    ('Godox SL200w ii', 120000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Lighting Video';

-- Lighting Photo - Flash (12 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('Flash YN560 Speedlite Mk IV (-BATT)', 25000, NULL),
    ('Flash Godox TT-520 ii (-BATT)', 20000, NULL),
    ('Flash Godox TT-685 for Sony (-BATT)', 30000, NULL),
    ('Flash Godox TT-600 (-BATT)', 25000, NULL),
    ('Flash Godox V860 for Sony (+BATT)', 40000, NULL),
    ('Flash Studio Godox MS300', 80000, NULL),
    ('Flash Studio Godox AD600 BM II (+BATT)', 150000, NULL),
    ('Trigger Godox X2T (Sony/Canon)', 20000, NULL),
    ('Trigger FONIK AC-04A', 10000, NULL),
    ('Paket Strobist Basic', 75000, 'Isi paket: 2 Flash Godox TT-520 ii, 2 Lightstand Kecil, 1 Trigger, 2 Payung, Tas'),
    ('Paket Strobist Pro HSS', 90000, 'Isi paket: 2 Flash Godox TT-600, 2 Lightstand Kecil, 1 Trigger X2t (Sony/Canon), 2 Payung, Tas'),
    ('Paket Lighting Studio', 120000, 'Isi paket: 2 Strobe Flash Godox MS300, 2 Lightstand Besar, 1 Trigger, 2 Payung / Softbox, Tas')
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Lighting Photo - Flash';

-- Sony Lens APSC (9 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('SONY E 20mm F/2,8', 60000, NULL),
    ('SONY E 35mm F/1.8', 60000, NULL),
    ('SONY E 50mm F/1.8', 60000, NULL),
    ('SONY E 10-18mm F/4', 80000, NULL),
    ('SONY E 18-105mm F/4 G Lens', 70000, NULL),
    ('SIGMA DC 16mm F/1.4', 75000, NULL),
    ('SIGMA DC 30mm F/1.4', 75000, NULL),
    ('SIGMA DC 56mm F/1.4', 75000, NULL),
    ('SIGMA DC 18-50mm F/2.8', 100000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Sony Lens APSC';

-- Sony Lens Fullframe (16 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('SONY F.E 28mm F/2', 70000, NULL),
    ('SONY F.E 50mm F/1.8', 60000, NULL),
    ('SONY F.E 85mm F/1.8', 70000, NULL),
    ('SONY F.E 55mm F/1.8 ZEISS', 90000, NULL),
    ('SONY F.E 16-35mm F/4 ZEISS', 110000, NULL),
    ('SONY F.E 70-200mm F/4 G Lens', 150000, NULL),
    ('SONY F.E 16-35mm F/2.8 G Master', 200000, NULL),
    ('SONY F.E 24-70mm F/2.8 G Master', 200000, NULL),
    ('SONY F.E 50mm F/1.4 G Master', 200000, NULL),
    ('SIGMA ART 24-70mm F/2.8 FE Sony', 150000, NULL),
    ('SIGMA ART 35mm F/1.2 FE Sony', 150000, NULL),
    ('SIGMA ART 35mm F/1.4 FE Sony', 100000, NULL),
    ('Samyang AF 24mm F/1.8 FE', 75000, NULL),
    ('Samyang AF 45mm F/1.8 FE', 75000, NULL),
    ('Samyang AF 75mm F/1.8 FE', 75000, NULL),
    ('Tamron 28-75mm F/2.8 FE', 140000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Sony Lens Fullframe';

-- Fujifilm Lens (6 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('FUJIFILM XF 10-24mm F/4', 120000, NULL),
    ('FUJIFILM XF 16-55mm F/2.8', 160000, NULL),
    ('FUJIFILM XF 18mm F/2', 50000, NULL),
    ('FUJIFILM XF 23mm F/1.4', 100000, NULL),
    ('FUJIFILM XF 35mm F/1.4', 75000, NULL),
    ('FUJIFILM XF 56mm F/1.2', 125000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Fujifilm Lens';

-- Canon Lens (9 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('CANON FIX 50mm F/1.8 STM', 40000, NULL),
    ('CANON FIX 100mm F/2.8 Macro', 100000, NULL),
    ('CANON 17-40mm F/4 L Series', 100000, NULL),
    ('CANON 24-70mm F/2.8 L Series', 120000, NULL),
    ('CANON 70-200mm F/2.8 L (Non IS)', 150000, NULL),
    ('SIGMA ART 18-35mm F/1.8', 100000, NULL),
    ('SIGMA ART 24-35mm F/2', 120000, NULL),
    ('SIGMA ART 35mm F/1.4 EF Canon', 100000, NULL),
    ('YONGNUO YN 50mm F/1.4 EF', 55000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Canon Lens';

-- Lens Adapter (2 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('Adapter Viltrox IV (Canon Lens - Sony) AF', 25000, NULL),
    ('Adapter Original Canon EF-EOS M', 25000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Lens Adapter';

-- Stabilizer - Gimbal (8 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('DJI Osmo Mobile 2 (for smartphone)', 40000, NULL),
    ('DJI Osmo Mobile 3 (for smartphone)', 40000, NULL),
    ('DJI Osmo Mobile SE (for smartphone)', 60000, NULL),
    ('DJI RS Mini 3 (<1.9kg)', 120000, NULL),
    ('DJI RS Mini 4 (<1.9kg)', 150000, NULL),
    ('DJI Ronin - SC (<2kg)', 130000, NULL),
    ('DJI Ronin - SC 2 (<3kg)', 150000, NULL),
    ('Zhiyun Crane Plus', 120000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Stabilizer - Gimbal';

-- Multimedia (4 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('Proyektor EPSON EB-E500 + Screen (3600LM)', 250000, NULL),
    ('Proyektor EPSON EB-E500 (tanpa screen)', 200000, NULL),
    ('Screen Proyektor', 50000, NULL),
    ('Stand Proyektor', 25000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Multimedia';

-- Mirrorless Sony (15 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('SONY ZV-E10 (Kit 16-50mm)', 130000, NULL),
    ('SONY A6000 (Kit 16-50mm)', 110000, NULL),
    ('SONY A6300 (Kit 16-50mm)', 140000, NULL),
    ('SONY A6300 (Kit 18-105mm F/4)', 190000, NULL),
    ('SONY A6400 (Body Only)', 140000, NULL),
    ('SONY A6400 (Kit 16-50mm)', 160000, NULL),
    ('SONY A6500 (Body Only)', 140000, NULL),
    ('SONY A6500 (Kit 16-50mm)', 160000, NULL),
    ('SONY A7 Mk II (Body Only)', 200000, NULL),
    ('SONY A7 Mk II (50mm F.E F/1.8)', 240000, NULL),
    ('SONY A7 Mk II (Kit 16-35mm F.E F/4)', 300000, NULL),
    ('SONY A7 Mk III (Body Only)', 240000, NULL),
    ('SONY A7 Mk IV (Body Only)', 350000, NULL),
    ('SONY A7C (Body Only)', 240000, NULL),
    ('SONY FX 30 (Body Only)', 300000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Mirrorless Sony';

-- Mirrorless Fuji/Canon (11 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('INSTAX Mini Evo (Tanpa Paper Film) +16GB', 130000, NULL),
    ('FUJIFILM X-A5 (Kit 16-50mm)', 100000, NULL),
    ('FUJIFILM X-A7 (Kit 15-45mm)', 120000, NULL),
    ('FUJIFILM X-T20 (Kit 15-45mm)', 120000, NULL),
    ('FUJIFILM X-T20 (35mm F/1.4)', 180000, NULL),
    ('FUJIFILM X-T30 (Kit 15-45mm)', 140000, NULL),
    ('FUJIFILM X-T30 (35mm F/1.4)', 200000, NULL),
    ('CANON EOS M6 (Kit 18-150mm)', 100000, NULL),
    ('CANON EOS M6 mark II (Kit 18-150mm)', 110000, NULL),
    ('CANON EOS R100 (Kit 18-45mm)', 120000, NULL),
    ('CANON G7x mark III', 160000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Mirrorless Fuji/Canon';

-- Actioncam (5 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('DJI Osmo Nano', 150000, NULL),
    ('DJI Action 3 (2 batt, cage, tongsis)', 150000, NULL),
    ('DJI Osmo Pocket 3 (Creator Combo+Mic)', 200000, NULL),
    ('DJI Osmo Pocket 4 (Creator Combo+Mic+LED)', 250000, NULL),
    ('INSTA ONE X5 360 Camera +Invisible Stick', 200000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Actioncam';

-- Camcorder (3 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('SONY HXR NX100P', 250000, NULL),
    ('SONY HXR NX100P + TRIPOD', 275000, NULL),
    ('Feelworld Livepro L2 (video switcher)', 200000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'Camcorder';

-- DSLR Camera (6 item)
INSERT INTO barang (id_kategori, nama_barang, harga_sewa_per_hari, kondisi_fisik)
SELECT k.id_kategori, v.nama_barang, v.harga, v.kondisi_fisik
FROM (VALUES
    ('CANON 700D (Kit 18-55mm)', 110000, NULL),
    ('CANON 60D (Kit 18-55mm)', 120000, NULL),
    ('CANON 6D (Body Only)', 180000, NULL),
    ('CANON 6D (Kit 24-70mm f/2.8L)', 280000, NULL),
    ('CANON 5D Mk III (Body Only)', 200000, NULL),
    ('CANON 5D Mk III (Kit 24-70mm f/2.8L)', 300000, NULL)
) AS v(nama_barang, harga, kondisi_fisik)
JOIN kategori k ON k.nama_kategori = 'DSLR Camera';

COMMIT;
