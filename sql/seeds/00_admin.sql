-- =====================================================================
-- SnapRent - 00_admin.sql
-- Seed akun default administrator
-- Jalankan: psql -U <user> -d snaprent -f 00_admin.sql
-- =====================================================================

-- Default Akun:
-- Username : admin
-- Password : admin (di-hash dengan BCRYPT / password_hash)

INSERT INTO admin (username, password, nama_lengkap)
VALUES (
    'admin',
    '$2y$10$retcbQ56evAI9uVbar.P0uq1d73sBPnPiFI/1T/h.RPl3wb8tm/LG',
    'Administrator'
)
ON CONFLICT (username) DO UPDATE SET password = EXCLUDED.password;