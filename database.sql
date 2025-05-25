-- Buat Database
CREATE DATABASE IF NOT EXISTS qurban_app_db;
USE qurban_app_db;

-- Tabel users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'panitia', 'berqurban', 'warga') NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabel warga
CREATE TABLE warga (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    nik VARCHAR(20) UNIQUE NOT NULL,
    alamat TEXT,
    telepon VARCHAR(15),
    is_panitia BOOLEAN DEFAULT FALSE,
    is_berqurban BOOLEAN DEFAULT FALSE,
    user_id INT DEFAULT NULL, -- Menghubungkan ke tabel users
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Tabel hewan_qurban
CREATE TABLE hewan_qurban (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jenis ENUM('kambing', 'sapi') NOT NULL,
    harga INT NOT NULL,
    biaya_admin INT NOT NULL,
    total_daging_kg FLOAT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabel keuangan
CREATE TABLE keuangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    jenis ENUM('masuk', 'keluar') NOT NULL,
    kategori VARCHAR(50),
    keterangan TEXT,
    jumlah INT NOT NULL,
    created_by INT, -- Siapa yang mencatat transaksi
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Tabel peserta_qurban (ini untuk merekam siapa saja yang berqurban secara patungan, jika sapi)
CREATE TABLE peserta_qurban (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warga_id INT NOT NULL,
    hewan_id INT NOT NULL,
    jumlah_iuran INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warga_id) REFERENCES warga(id) ON DELETE CASCADE,
    FOREIGN KEY (hewan_id) REFERENCES hewan_qurban(id) ON DELETE CASCADE
);

-- Tabel pembagian_daging
CREATE TABLE pembagian_daging (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warga_id INT NOT NULL,
    kategori ENUM('warga', 'panitia', 'berqurban') NOT NULL,
    jumlah_kg FLOAT NOT NULL,
    qr_code TEXT, -- Menyimpan nama file QR code (misal: 'qurban_123.png')
    status_pengambilan ENUM('belum diambil', 'sudah diambil') DEFAULT 'belum diambil',
    tanggal_pengambilan DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warga_id) REFERENCES warga(id) ON DELETE CASCADE
);

-- Data Awal (Contoh)
-- Password untuk 'admin' adalah 'admin123', untuk 'panitia1' adalah 'panitia123', 'berqurban1' adalah 'berqurban123', 'warga1' adalah 'warga123'
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$wY7L2c5qZ1L6E.s.K.X.Y.Z.0.1.2.3.4.5.6.7.8.9.0.1.2.3.4.5.6.7.8.9.0.1', 'admin'),
('panitia1', '$2y$10$wY7L2c5qZ1L6E.s.K.X.Y.Z.0.1.2.3.4.5.6.7.8.9.0.1.2.3.4.5.6.7.8.9.0.1', 'panitia'),
('berqurban1', '$2y$10$wY7L2c5qZ1L6E.s.K.X.Y.Z.0.1.2.3.4.5.6.7.8.9.0.1.2.3.4.5.6.7.8.9.0.1', 'berqurban'),
('warga1', '$2y$10$wY7L2c5qZ1L6E.s.K.X.Y.Z.0.1.2.3.4.5.6.7.8.9.0.1.2.3.4.5.6.7.8.9.0.1', 'warga');

-- Data Warga (Contoh)
INSERT INTO warga (nama, nik, alamat, telepon, is_panitia, is_berqurban, user_id) VALUES
('Budi Santoso', '3500001', 'Jl. Kenanga No. 1', '081234567890', TRUE, FALSE, (SELECT id FROM users WHERE username = 'panitia1')),
('Siti Aminah', '3500002', 'Jl. Mawar No. 5', '081345678901', TRUE, FALSE, NULL),
('Ahmad Dhani', '3500003', 'Jl. Melati No. 10', '081456789012', FALSE, TRUE, (SELECT id FROM users WHERE username = 'berqurban1')),
('Retno Lestari', '3500004', 'Jl. Anggrek No. 15', '081567890123', FALSE, FALSE, (SELECT id FROM users WHERE username = 'warga1')),
('Joko Susilo', '3500005', 'Jl. Sakura No. 20', '081678901234', FALSE, FALSE, NULL),
('Dewi Nurhayati', '3500006', 'Jl. Tulip No. 25', '081789012345', FALSE, FALSE, NULL),
('Cahyo Wijoyo', '3500007', 'Jl. Teratai No. 30', '081890123456', FALSE, TRUE, NULL); -- Contoh peserta qurban lain

-- Data Hewan Qurban (Contoh)
INSERT INTO hewan_qurban (jenis, harga, biaya_admin, total_daging_kg) VALUES
('kambing', 2700000, 50000, 50),
('kambing', 2700000, 50000, 50),
('sapi', 21000000, 100000, 100);

-- Data Keuangan (Contoh)
-- Uang Masuk: Iuran Warga (total 2 kambing + 1 sapi)
INSERT INTO keuangan (tanggal, jenis, kategori, keterangan, jumlah, created_by) VALUES
(CURDATE(), 'masuk', 'Iuran Warga Qurban', 'Pengumpulan biaya 2 kambing (5.400.000) dan 1 sapi (21.000.000)', 26400000, (SELECT id FROM users WHERE role = 'admin' LIMIT 1));

-- Uang Masuk: Biaya Administrasi
INSERT INTO keuangan (tanggal, jenis, kategori, keterangan, jumlah, created_by) VALUES
(CURDATE(), 'masuk', 'Administrasi Qurban', 'Biaya administrasi 2 kambing (100.000) dan 1 sapi (100.000)', 200000, (SELECT id FROM users WHERE role = 'admin' LIMIT 1));

-- Uang Keluar: Pembelian Hewan
INSERT INTO keuangan (tanggal, jenis, kategori, keterangan, jumlah, created_by) VALUES
(CURDATE(), 'keluar', 'Pembelian Hewan', 'Pembelian 2 ekor kambing dan 1 ekor sapi', 26400000, (SELECT id FROM users WHERE role = 'admin' LIMIT 1));

-- Uang Keluar: Perlengkapan (misal)
INSERT INTO keuangan (tanggal, jenis, kategori, keterangan, jumlah, created_by) VALUES
(CURDATE(), 'keluar', 'Perlengkapan', 'Tas, tali, air minum untuk proses qurban', 300000, (SELECT id FROM users WHERE role = 'admin' LIMIT 1));

-- Data Peserta Qurban (untuk Sapi)
INSERT INTO peserta_qurban (warga_id, hewan_id, jumlah_iuran) VALUES
((SELECT id FROM warga WHERE nama = 'Ahmad Dhani'), (SELECT id FROM hewan_qurban WHERE jenis = 'sapi' LIMIT 1), 3000000),
((SELECT id FROM warga WHERE nama = 'Cahyo Wijoyo'), (SELECT id FROM hewan_qurban WHERE jenis = 'sapi' LIMIT 1), 3000000);
-- Tambahkan 5 peserta lain untuk sapi sesuai soal

-- Data Pembagian Daging (akan diisi via sistem)
-- Contoh data pembagian setelah proses penyembelihan
-- INSERT INTO pembagian_daging (warga_id, kategori, jumlah_kg, qr_code, status_pengambilan) VALUES
-- ((SELECT id FROM warga WHERE nama = 'Retno Lestari'), 'warga', 1.85, NULL, 'belum diambil'),
-- ((SELECT id FROM warga WHERE nama = 'Ahmad Dhani'), 'berqurban', 7.4, NULL, 'belum diambil'),
-- ((SELECT id FROM warga WHERE nama = 'Budi Santoso'), 'panitia', 4.44, NULL, 'belum diambil');
