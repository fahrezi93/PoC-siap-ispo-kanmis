CREATE DATABASE db_toko_a;
GO

USE db_toko_a;
GO

-- Tabel 1: db_pegawai (data sensitif)
CREATE TABLE db_pegawai (
    id INT IDENTITY(1,1) PRIMARY KEY,
    nama_pegawai VARCHAR(100) NOT NULL,
    jabatan VARCHAR(50) NOT NULL,
    gaji DECIMAL(18,2) NOT NULL
);
GO

-- Tabel 2: db_transaksi (data transaksi toko)
CREATE TABLE db_transaksi (
    id INT IDENTITY(1,1) PRIMARY KEY,
    kode_transaksi VARCHAR(50) UNIQUE NOT NULL,
    total_nominal DECIMAL(18,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'SUCCESS',
    created_at DATETIME DEFAULT GETDATE()
);
GO

-- Seed Dummy Data untuk db_pegawai
INSERT INTO db_pegawai (nama_pegawai, jabatan, gaji)
VALUES 
('Budi Santoso', 'Manager', 15000000.00),
('Siti Aminah', 'Kasir', 5000000.00);
GO

-- Seed Dummy Data untuk db_transaksi
INSERT INTO db_transaksi (kode_transaksi, total_nominal, status, created_at)
VALUES 
('TRX-001', 250000.00, 'SUCCESS', GETDATE()),
('TRX-002', 150000.00, 'FAILED', GETDATE()),
('TRX-003', 500000.00, 'SUCCESS', DATEADD(day, -1, GETDATE()));
GO
