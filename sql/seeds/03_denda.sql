-- SnapRent - seed aturan denda progresif
-- Aturan berlaku jika: batas_min_jam < keterlambatan (jam) <= batas_max_jam
INSERT INTO denda (jenis_denda, batas_min_jam, batas_max_jam, nilai_denda, keterangan) VALUES
    ('nominal_tetap', 0, 1,    0,     'Masa toleransi, tanpa denda'),
    ('nominal_tetap', 1, 3,    50000, 'Terlambat lebih dari 1 sampai 3 jam'),
    ('sewa_harian',   3, NULL, 0,     'Terlambat lebih dari 3 jam dihitung sewa 1 hari penuh');
