CREATE DATABASE IF NOT EXISTS db_persediaan_obat;
USE db_persediaan_obat;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(100) NOT NULL,
  role ENUM('admin','apoteker','gudang','owner') NOT NULL DEFAULT 'apoteker',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS apoteker (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  kontak VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS obat (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(150) NOT NULL,
  deskripsi VARCHAR(255),
  stok INT NOT NULL DEFAULT 0,
  harga INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS barang_masuk (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATETIME NOT NULL,
  obat_id INT NOT NULL,
  jumlah INT NOT NULL,
  penerima VARCHAR(100),
  FOREIGN KEY (obat_id) REFERENCES obat(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS barang_keluar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATETIME NOT NULL,
  obat_id INT NOT NULL,
  jumlah INT NOT NULL,
  penerima VARCHAR(100),
  FOREIGN KEY (obat_id) REFERENCES obat(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  alamat VARCHAR(255),
  telepon VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS resep (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NULL,
  nama_pasien VARCHAR(100) NOT NULL,
  apoteker_id INT NULL,
  obat_id INT NOT NULL,
  jumlah INT NOT NULL,
  dosis VARCHAR(100),
  keterangan VARCHAR(255),
  harga INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
  FOREIGN KEY (apoteker_id) REFERENCES apoteker(id) ON DELETE SET NULL,
  FOREIGN KEY (obat_id) REFERENCES obat(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pesan_obat (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama_pemesan VARCHAR(100),
  obat_id INT,
  jumlah INT,
  status ENUM('pending','diproses','selesai') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (obat_id) REFERENCES obat(id) ON DELETE SET NULL
);

INSERT INTO users (name, email, password, role) VALUES
('Admin Utama','admin@gmail.com','admin123','admin'),
('Owner','owner@gmail.com','owner123','owner'),
('Apoteker','apoteker@gmail.com','apoteker123','apoteker'),
('Staff Gudang','gudang@gmail.com','gudang123','gudang');

INSERT INTO apoteker (nama, kontak) VALUES
('Ani Nurhayati','083872565699'),
('Eryan Saputra','085862562672');

INSERT INTO obat (nama, deskripsi, stok, harga) VALUES
('RHEUMASON INHALER','Obat Sirup',56,10000),
('SALICYL GANESIS (FT) 60 G','Obat Salep',97,12000),
('SALICYL MENTHOL (FT) 60 G','Obat Salep',94,8000),
('ORTHO OINTMENT SHAPEL','Obat Salep',81,12000);
