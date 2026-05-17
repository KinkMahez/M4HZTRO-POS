-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 17 Bulan Mei 2026 pada 16.04
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_pos`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `categories`
--

CREATE TABLE `categories` (
  `id_category` int(11) NOT NULL,
  `nama_category` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `categories`
--

INSERT INTO `categories` (`id_category`, `nama_category`, `created_at`) VALUES
(6, 'T-Shirts', '2026-05-12 15:27:32'),
(7, 'Hoodies', '2026-05-14 13:05:33'),
(8, 'Accessories', '2026-05-15 12:22:03'),
(9, 'Jackets', '2026-05-15 12:26:11'),
(10, 'Skirts', '2026-05-15 14:39:15'),
(11, 'Pants', '2026-05-15 14:39:26'),
(12, 'Hats', '2026-05-15 14:39:29');

-- --------------------------------------------------------

--
-- Struktur dari tabel `products`
--

CREATE TABLE `products` (
  `id_product` int(11) NOT NULL,
  `nama_product` varchar(150) NOT NULL,
  `id_category` int(11) NOT NULL,
  `harga_beli` int(11) NOT NULL,
  `harga_jual` int(11) NOT NULL,
  `stok` int(11) NOT NULL,
  `barcode` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `gambar` varchar(255) DEFAULT 'default.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `products`
--

INSERT INTO `products` (`id_product`, `nama_product`, `id_category`, `harga_beli`, `harga_jual`, `stok`, `barcode`, `created_at`, `updated_at`, `gambar`) VALUES
(16, 'JPPG Hoodie 1945', 7, 300000, 500000, 1, 'AMRKSHIR696769', '2026-05-15 10:24:33', '2026-05-17 11:32:25', 'AMRKSHIR696769_1778841630.jpg'),
(19, 'T-Shirt Semi Long BRG', 6, 300000, 350000, 2, 'JsDsdSPXX', '2026-05-15 10:42:02', '2026-05-17 11:32:25', 'JsDsdSPXX.jpg'),
(20, 'CH Glasses 67', 8, 500000, 1500000, 3, 'CHGLS7927642NW', '2026-05-15 12:22:43', '2026-05-17 11:32:25', 'CHGLS7927642NW.jpg'),
(21, 'PARIS FSHN Glasses 69', 8, 950000, 2500000, 6, 'PRSGLSFSH83213', '2026-05-15 12:23:30', '2026-05-15 16:58:29', 'PRSGLSFSH83213.jpg'),
(22, 'Regular CH Glasses 67', 8, 430000, 850000, 5, 'CHGRLASRGLR81293', '2026-05-15 12:24:09', '2026-05-16 12:55:41', 'CHGRLASRGLR81293.jpg'),
(23, 'Grunge Hoodie Japans', 7, 950000, 3100000, 5, 'GRNGHDDIE8329824NW', '2026-05-15 12:24:57', '2026-05-17 12:45:26', 'GRNGHDDIE8329824NW.jpg'),
(24, 'Leather Jacket BB 90s', 7, 810000, 1100000, 4, 'LERTHARJCK84234', '2026-05-15 12:25:27', '2026-05-17 12:45:26', 'LERTHARJCK84234.jpg'),
(25, 'Grunges Jacket Red Switch', 9, 1200000, 1900000, 2, 'GRNGJhcksDsdSPXX', '2026-05-15 12:26:05', '2026-05-17 12:45:26', 'GRNGJhcksDsdSPXX.jpg'),
(26, 'Saint Warehouse Hat 1289', 12, 6000000, 7500000, 12, 'HATSLTH8329391', '2026-05-15 17:03:19', '2026-05-16 12:55:41', 'HATSLTH8329391.jpg'),
(27, 'Playboy\'s Pants 1998', 11, 1900000, 2500000, 10, 'PLYBYPANYS832183', '2026-05-15 17:03:47', '2026-05-17 12:45:26', 'PLYBYPANYS832183.jpg'),
(28, 'Denim Skirt Y2K', 10, 400000, 550000, 8, 'WLMSKIR7329699', '2026-05-15 17:04:20', '2026-05-17 12:53:07', 'WLMSKIR7329699.jpg');

-- --------------------------------------------------------

--
-- Struktur dari tabel `restock`
--

CREATE TABLE `restock` (
  `id` int(11) NOT NULL,
  `id_product` int(11) DEFAULT NULL,
  `jumlah` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `restock`
--

INSERT INTO `restock` (`id`, `id_product`, `jumlah`, `supplier_id`, `catatan`, `user_id`, `created_at`) VALUES
(78, NULL, 1, NULL, '', 1, '2026-05-12 20:58:07'),
(79, NULL, 2, NULL, '', 1, '2026-05-12 20:58:48'),
(80, NULL, 8, NULL, 'gf', 1, '2026-05-12 21:15:17'),
(81, NULL, 5, NULL, 'lol', 1, '2026-05-12 21:45:16'),
(82, NULL, 4, NULL, '', 1, '2026-05-12 21:45:32'),
(83, NULL, 100, NULL, '', 1, '2026-05-12 22:07:44'),
(84, NULL, 500, NULL, '', 1, '2026-05-12 22:07:51'),
(85, 16, 2, NULL, '', 1, '2026-05-15 17:34:06'),
(86, 24, 7, 1, '', 1, '2026-05-15 23:50:14'),
(87, 25, 5, NULL, '', 1, '2026-05-15 23:50:24'),
(88, 23, 5, NULL, '', 1, '2026-05-15 23:50:32'),
(89, 25, 2, NULL, '', 1, '2026-05-17 18:32:10'),
(90, 25, 2, NULL, '', 1, '2026-05-17 19:44:25');

-- --------------------------------------------------------

--
-- Struktur dari tabel `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `nama_toko` varchar(50) NOT NULL,
  `alamat` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `settings`
--

INSERT INTO `settings` (`id`, `nama_toko`, `alamat`) VALUES
(1, 'M4HZTRO©', 'Jln Margaasih no 69');

-- --------------------------------------------------------

--
-- Struktur dari tabel `supplier`
--

CREATE TABLE `supplier` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kontak` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `supplier`
--

INSERT INTO `supplier` (`id`, `nama`, `kontak`, `created_at`) VALUES
(1, 'mahesa', 'karmoyxx@gmail.com', '2026-05-15 23:50:06');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transactions`
--

CREATE TABLE `transactions` (
  `id_transaction` int(11) NOT NULL,
  `tanggal` datetime NOT NULL,
  `subtotal` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `bayar` int(11) NOT NULL,
  `kembalian` int(11) NOT NULL,
  `diskon_persen` decimal(5,2) DEFAULT 0.00,
  `diskon_nominal` int(11) DEFAULT 0,
  `id_user` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transactions`
--

INSERT INTO `transactions` (`id_transaction`, `tanggal`, `subtotal`, `total`, `bayar`, `kembalian`, `diskon_persen`, `diskon_nominal`, `id_user`) VALUES
(32, '2026-05-15 21:22:15', 12700000, 6350000, 8000000, 1650000, 50.00, 6350000, 1),
(33, '2026-05-15 21:29:23', 10600000, 10070000, 12000000, 1930000, 5.00, 530000, 1),
(34, '2026-05-15 21:42:39', 11300000, 113000, 120000, 7000, 99.00, 11187000, 2),
(35, '2026-05-15 23:48:13', 7200000, 7200000, 7200000, 0, 0.00, 0, 1),
(36, '2026-05-15 23:48:26', 3600000, 3600000, 3600000, 0, 0.00, 0, 1),
(37, '2026-05-15 23:49:04', 12750000, 2805000, 7000000, 4195000, 78.00, 9945000, 1),
(38, '2026-05-15 23:49:15', 6100000, 6100000, 6100000, 0, 0.00, 0, 1),
(39, '2026-05-15 23:49:31', 15100000, 15100000, 20000000, 4900000, 0.00, 0, 1),
(40, '2026-05-15 23:58:18', 6000000, 6000000, 6000000, 0, 0.00, 0, 1),
(41, '2026-05-15 23:58:29', 7650000, 7650000, 7650000, 0, 0.00, 0, 1),
(42, '2026-05-16 00:05:26', 33500000, 29480000, 30000000, 520000, 12.00, 4020000, 1),
(43, '2026-05-16 00:22:48', 37500000, 33000000, 34000000, 1000000, 12.00, 4500000, 1),
(44, '2026-05-16 18:31:48', 14200000, 9372000, 9500000, 128000, 34.00, 4828000, 1),
(45, '2026-05-16 19:33:05', 7600000, 7600000, 7600000, 0, 0.00, 0, 2),
(46, '2026-05-16 19:55:41', 17850000, 17850000, 17850000, 0, 0.00, 0, 2),
(48, '2026-05-17 18:32:25', 6150000, 6150000, 6150000, 0, 0.00, 0, 1),
(49, '2026-05-17 19:45:26', 27700000, 27700000, 29000000, 1300000, 0.00, 0, 2);

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaction_detail`
--

CREATE TABLE `transaction_detail` (
  `id_detail` int(11) NOT NULL,
  `id_transaction` int(11) NOT NULL,
  `id_product` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `harga_beli_now` int(11) NOT NULL,
  `harga_jual` int(11) NOT NULL,
  `subtotal` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transaction_detail`
--

INSERT INTO `transaction_detail` (`id_detail`, `id_transaction`, `id_product`, `qty`, `harga_beli_now`, `harga_jual`, `subtotal`) VALUES
(64, 32, 16, 1, 200000, 300000, 300000),
(65, 32, 19, 1, 300000, 350000, 350000),
(66, 32, 20, 1, 500000, 1500000, 1500000),
(67, 32, 21, 1, 950000, 2500000, 2500000),
(68, 32, 22, 1, 430000, 850000, 850000),
(69, 32, 23, 1, 950000, 3100000, 3100000),
(70, 32, 24, 2, 810000, 1100000, 2200000),
(71, 32, 25, 1, 1200000, 1900000, 1900000),
(72, 33, 19, 4, 300000, 350000, 1400000),
(73, 33, 16, 4, 200000, 300000, 1200000),
(74, 33, 21, 2, 950000, 2500000, 5000000),
(75, 33, 20, 2, 500000, 1500000, 3000000),
(76, 34, 25, 1, 1200000, 1900000, 1900000),
(77, 34, 24, 1, 810000, 1100000, 1100000),
(78, 34, 21, 1, 950000, 2500000, 2500000),
(79, 34, 20, 1, 500000, 1500000, 1500000),
(80, 34, 19, 1, 300000, 350000, 350000),
(81, 34, 23, 1, 950000, 3100000, 3100000),
(82, 34, 22, 1, 430000, 850000, 850000),
(83, 35, 25, 1, 1200000, 1900000, 1900000),
(84, 35, 24, 2, 810000, 1100000, 2200000),
(85, 35, 23, 1, 950000, 3100000, 3100000),
(86, 36, 19, 6, 300000, 350000, 2100000),
(87, 36, 16, 5, 200000, 300000, 1500000),
(88, 37, 21, 1, 950000, 2500000, 2500000),
(89, 37, 20, 1, 500000, 1500000, 1500000),
(90, 37, 19, 1, 300000, 350000, 350000),
(91, 37, 16, 2, 200000, 300000, 600000),
(92, 37, 25, 1, 1200000, 1900000, 1900000),
(93, 37, 24, 1, 810000, 1100000, 1100000),
(94, 37, 23, 1, 950000, 3100000, 3100000),
(95, 37, 22, 2, 430000, 850000, 1700000),
(96, 38, 25, 1, 1200000, 1900000, 1900000),
(97, 38, 24, 1, 810000, 1100000, 1100000),
(98, 38, 23, 1, 950000, 3100000, 3100000),
(99, 39, 22, 2, 430000, 850000, 1700000),
(100, 39, 23, 2, 950000, 3100000, 6200000),
(101, 39, 24, 2, 810000, 1100000, 2200000),
(102, 39, 25, 2, 1200000, 1900000, 3800000),
(103, 39, 16, 4, 200000, 300000, 1200000),
(104, 40, 25, 2, 1200000, 1900000, 3800000),
(105, 40, 24, 2, 810000, 1100000, 2200000),
(106, 41, 16, 1, 200000, 300000, 300000),
(107, 41, 19, 1, 300000, 350000, 350000),
(108, 41, 20, 1, 500000, 1500000, 1500000),
(109, 41, 21, 1, 950000, 2500000, 2500000),
(110, 41, 25, 1, 1200000, 1900000, 1900000),
(111, 41, 24, 1, 810000, 1100000, 1100000),
(112, 42, 27, 3, 1900000, 2500000, 7500000),
(113, 42, 28, 2, 3400000, 5500000, 11000000),
(114, 42, 26, 2, 6000000, 7500000, 15000000),
(115, 43, 26, 5, 6000000, 7500000, 37500000),
(116, 44, 23, 2, 950000, 3100000, 6200000),
(117, 44, 27, 1, 1900000, 2500000, 2500000),
(118, 44, 28, 1, 3400000, 5500000, 5500000),
(119, 45, 25, 4, 1200000, 1900000, 7600000),
(120, 46, 19, 2, 300000, 350000, 700000),
(121, 46, 20, 1, 500000, 1500000, 1500000),
(122, 46, 24, 1, 810000, 1100000, 1100000),
(123, 46, 23, 2, 950000, 3100000, 6200000),
(124, 46, 22, 1, 430000, 850000, 850000),
(125, 46, 26, 1, 6000000, 7500000, 7500000),
(130, 48, 16, 1, 300000, 500000, 500000),
(131, 48, 19, 1, 300000, 350000, 350000),
(132, 48, 20, 1, 500000, 1500000, 1500000),
(133, 48, 25, 2, 1200000, 1900000, 3800000),
(134, 49, 25, 1, 1200000, 1900000, 1900000),
(135, 49, 24, 2, 810000, 1100000, 2200000),
(136, 49, 23, 1, 950000, 3100000, 3100000),
(137, 49, 28, 1, 3400000, 5500000, 5500000),
(138, 49, 27, 6, 1900000, 2500000, 15000000);

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','kasir','owner') DEFAULT NULL,
  `qr_token` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `qr_token`, `is_active`, `created_at`) VALUES
(1, 'adminpos', '$2a$12$GE4LYRDkG9It.ZtaX5gDa.UoSgdk6I63EEsq30JiDtat5AHbWfsmu', 'admin', 'TOKEN_MHZTRO_6969', 1, '2026-02-10 13:07:59'),
(2, 'kasirpos', '$2a$12$txQyG0Rh1RfO.MsE1zhjMeihmKqZH9g/ydnUECTsYPRyaASVXqlaq', 'kasir', 'KINKMAHEZ-6969', 1, '2026-04-24 05:28:53'),
(6, 'ownerpos', '$2a$12$cPxXquCXVAaUhGQGnTDyOexaAx.53A30.Nc9pDQnv5pPFFC.4RBKe', 'owner', 'dc36c6c946c0611a6dcc43cdfaae55d2', 1, '2026-05-16 12:03:42');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id_category`);

--
-- Indeks untuk tabel `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id_product`),
  ADD UNIQUE KEY `barcode` (`barcode`);

--
-- Indeks untuk tabel `restock`
--
ALTER TABLE `restock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `restock_ibfk_1` (`id_product`);

--
-- Indeks untuk tabel `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id_transaction`);

--
-- Indeks untuk tabel `transaction_detail`
--
ALTER TABLE `transaction_detail`
  ADD PRIMARY KEY (`id_detail`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `qr_token` (`qr_token`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `categories`
--
ALTER TABLE `categories`
  MODIFY `id_category` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `products`
--
ALTER TABLE `products`
  MODIFY `id_product` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT untuk tabel `restock`
--
ALTER TABLE `restock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT untuk tabel `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `supplier`
--
ALTER TABLE `supplier`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id_transaction` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT untuk tabel `transaction_detail`
--
ALTER TABLE `transaction_detail`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `restock`
--
ALTER TABLE `restock`
  ADD CONSTRAINT `restock_ibfk_1` FOREIGN KEY (`id_product`) REFERENCES `products` (`id_product`) ON DELETE SET NULL,
  ADD CONSTRAINT `restock_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`id`),
  ADD CONSTRAINT `restock_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
