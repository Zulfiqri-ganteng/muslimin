-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 01 Jul 2026 pada 20.54
-- Versi server: 10.11.18-MariaDB-cll-lve
-- Versi PHP: 8.4.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `zulh7811_kang-m`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admins`
--

CREATE TABLE `admins` (
  `id` int(11) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admins`
--

INSERT INTO `admins` (`id`, `full_name`, `email`, `username`, `password`, `phone`, `photo`, `role`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', 'admin@sekolah.sch.id', 'admin', '$2y$10$aromG23U1IZrBnmn5hK.5.dBLEhkSxGa5.8TTmtL6yjzDQNWYgPVu', NULL, NULL, 'admin', '2026-06-01 07:39:07', '2026-06-01 07:39:07');

-- --------------------------------------------------------

--
-- Struktur dari tabel `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) UNSIGNED NOT NULL,
  `admin_id` int(11) UNSIGNED DEFAULT NULL,
  `aksi` varchar(20) NOT NULL,
  `tabel` varchar(50) NOT NULL,
  `record_id` int(11) UNSIGNED DEFAULT NULL,
  `deskripsi` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `audit_log`
--

INSERT INTO `audit_log` (`id`, `admin_id`, `aksi`, `tabel`, `record_id`, `deskripsi`, `ip_address`, `created_at`) VALUES
(1, 1, 'create', 'guru', 1, 'Tambah guru Muslimin, S.Kom', '103.238.232.238', '2026-06-18 20:11:36'),
(2, 1, 'create', 'mata_pelajaran', 1, 'Tambah mapel Pendidikan Agama Islam & Budi Pekerti', '103.238.232.238', '2026-06-18 20:18:16'),
(3, 1, 'update', 'guru_mapel', 1, 'Atur kompetensi: 1 guru', '103.238.232.238', '2026-06-18 20:18:34'),
(4, 1, 'update', 'guru_mapel', 1, 'Atur kompetensi: 0 guru', '103.238.232.238', '2026-06-18 20:18:42'),
(5, 1, 'create', 'jurusan', 1, 'Tambah jurusan 01', '103.238.232.238', '2026-06-18 20:20:18'),
(6, 1, 'create', 'jurusan', 2, 'Tambah jurusan 02', '103.238.232.238', '2026-06-18 20:20:38'),
(7, 1, 'create', 'jurusan', 3, 'Tambah jurusan 03', '103.238.232.238', '2026-06-18 20:21:03'),
(8, 1, 'update', 'jurusan', 1, 'Ubah jurusan 1', '103.238.232.238', '2026-06-18 20:22:00'),
(9, 1, 'update', 'jurusan', 2, 'Ubah jurusan 2', '103.238.232.238', '2026-06-18 20:22:55'),
(10, 1, 'update', 'jurusan', 3, 'Ubah jurusan 3', '103.238.232.238', '2026-06-18 20:23:09'),
(11, 1, 'create', 'kelas', 1, 'Tambah kelas X TKJ 1', '103.238.232.238', '2026-06-18 20:23:28'),
(12, 1, 'create', 'pengampu', 1, 'Tambah penugasan kelas#1', '103.238.232.238', '2026-06-18 20:23:50'),
(13, 1, 'create', 'hari', 1, 'Tambah hari SENIN', '103.238.232.238', '2026-06-18 20:24:07'),
(14, 1, 'create', 'hari', 2, 'Tambah hari SELASA', '103.238.232.238', '2026-06-18 20:24:18'),
(15, 1, 'delete', 'hari', 2, 'Hapus hari', '103.238.232.238', '2026-06-18 20:24:54'),
(16, 1, 'create', 'hari', 3, 'Tambah hari SELASA', '103.238.232.238', '2026-06-18 20:25:05'),
(17, 1, 'create', 'hari', 4, 'Tambah hari RABU', '103.238.232.238', '2026-06-18 20:25:27'),
(18, 1, 'create', 'hari', 5, 'Tambah hari KAMIS', '103.238.232.238', '2026-06-18 20:25:38'),
(19, 1, 'create', 'hari', 6, 'Tambah hari JUM\'AT', '103.238.232.238', '2026-06-18 20:25:49'),
(20, 1, 'create', 'jam_pelajaran', 1, 'Tambah jam pagi ke-1', '103.238.232.238', '2026-06-18 20:26:54'),
(21, 1, 'update', 'jam_pelajaran', 1, 'Ubah jam pagi ke-1', '103.238.232.238', '2026-06-18 20:27:03'),
(22, 1, 'update', 'guru', 1, 'Ubah guru Muslimin, S.Kom', '2001:448a:2002:a58b:4c0:5e87:f2e0:8c61', '2026-06-18 21:48:56'),
(23, 1, 'delete', 'pengampu', 1, 'Hapus penugasan', '2001:448a:2002:a58b:64bb:8b30:8967:da8f', '2026-06-18 22:30:03'),
(24, 1, 'create', 'pengampu', 1, 'Tambah penugasan kelas#1', '2400:9800:1ff:689f:18ba:48a9:f22c:a288', '2026-06-18 22:47:17'),
(25, 1, 'update', 'ketersediaan_guru', 1, 'Atur ketersediaan (pagi): 0 slot tidak tersedia', '2400:9800:1ff:689f:18ba:48a9:f22c:a288', '2026-06-18 22:48:03'),
(26, 1, 'create', 'jadwal', 1, 'Tempatkan 1 PAI kelas#1', '2400:9800:1ff:689f:18ba:48a9:f22c:a288', '2026-06-18 22:49:02'),
(27, 1, 'delete', 'jadwal', 1, 'Hapus sel jadwal', '2400:9800:1ff:689f:18ba:48a9:f22c:a288', '2026-06-18 22:49:06'),
(28, 1, 'update', 'jam_pelajaran', 1, 'Ubah jam pagi ke-1', '101.255.166.198', '2026-06-18 23:18:25'),
(29, 1, 'update', 'jam_pelajaran', 1, 'Ubah jam pagi ke-1', '101.255.166.198', '2026-06-18 23:18:59'),
(30, 1, 'delete', 'pengampu', 1, 'Hapus penugasan', '2400:9800:12:5a5e:18ba:cb80:c663:5976', '2026-06-20 21:14:22'),
(31, 1, 'import', 'mata_pelajaran', NULL, 'Import mapel: +44 baru, 0 update, 0 dilewati', '101.255.166.198', '2026-06-21 06:49:11'),
(32, 1, 'delete', 'kelas', 1, 'Hapus kelas', '101.255.166.198', '2026-06-21 06:49:55'),
(33, 1, 'import', 'kelas', NULL, 'Import kelas: +41 baru, 0 update, 0 dilewati', '101.255.166.198', '2026-06-21 06:59:59'),
(34, 1, 'delete', 'kelas', 2, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:01:19'),
(35, 1, 'delete', 'kelas', 24, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:01:24'),
(36, 1, 'delete', 'kelas', 11, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:01:28'),
(37, 1, 'delete', 'kelas', 12, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:01:31'),
(38, 1, 'delete', 'kelas', 13, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:01:35'),
(39, 1, 'delete', 'kelas', 14, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:01:40'),
(40, 1, 'delete', 'kelas', 15, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:01:42'),
(41, 1, 'delete', 'kelas', 16, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:01:46'),
(42, 1, 'delete', 'kelas', 17, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:01:49'),
(43, 1, 'delete', 'kelas', 18, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:01:50'),
(44, 1, 'delete', 'kelas', 19, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:01:52'),
(45, 1, 'delete', 'kelas', 20, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:01:54'),
(46, 1, 'delete', 'kelas', 3, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:01:57'),
(47, 1, 'delete', 'kelas', 21, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:01:59'),
(48, 1, 'delete', 'kelas', 22, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:01'),
(49, 1, 'delete', 'kelas', 23, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:03'),
(50, 1, 'delete', 'kelas', 25, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:05'),
(51, 1, 'delete', 'kelas', 26, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:07'),
(52, 1, 'delete', 'kelas', 27, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:09'),
(53, 1, 'delete', 'kelas', 28, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:11'),
(54, 1, 'delete', 'kelas', 29, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:14'),
(55, 1, 'delete', 'kelas', 30, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:15'),
(56, 1, 'delete', 'kelas', 4, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:17'),
(57, 1, 'delete', 'kelas', 31, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:20'),
(58, 1, 'delete', 'kelas', 32, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:22'),
(59, 1, 'delete', 'kelas', 33, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:24'),
(60, 1, 'delete', 'kelas', 34, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:26'),
(61, 1, 'delete', 'kelas', 35, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:28'),
(62, 1, 'delete', 'kelas', 36, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:29'),
(63, 1, 'delete', 'kelas', 37, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:32'),
(64, 1, 'delete', 'kelas', 38, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:35'),
(65, 1, 'delete', 'kelas', 39, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:38'),
(66, 1, 'delete', 'kelas', 40, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:40'),
(67, 1, 'delete', 'kelas', 5, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:42'),
(68, 1, 'delete', 'kelas', 41, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:44'),
(69, 1, 'delete', 'kelas', 42, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:46'),
(70, 1, 'delete', 'kelas', 6, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:48'),
(71, 1, 'delete', 'kelas', 7, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:50'),
(72, 1, 'delete', 'kelas', 8, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:51'),
(73, 1, 'delete', 'kelas', 9, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:53'),
(74, 1, 'delete', 'kelas', 10, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:02:54'),
(75, 1, 'import', 'kelas', NULL, 'Import kelas: +0 baru, 41 update, 0 dilewati', '101.255.166.198', '2026-06-21 07:09:40'),
(76, 1, 'delete', 'kelas', 2, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:10:55'),
(77, 1, 'delete', 'kelas', 11, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:10:58'),
(78, 1, 'delete', 'kelas', 12, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:00'),
(79, 1, 'delete', 'kelas', 13, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:02'),
(80, 1, 'delete', 'kelas', 14, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:03'),
(81, 1, 'delete', 'kelas', 15, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:05'),
(82, 1, 'delete', 'kelas', 16, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:07'),
(83, 1, 'delete', 'kelas', 17, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:08'),
(84, 1, 'delete', 'kelas', 18, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:11'),
(85, 1, 'delete', 'kelas', 19, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:12'),
(86, 1, 'delete', 'kelas', 20, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:14'),
(87, 1, 'delete', 'kelas', 3, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:16'),
(88, 1, 'delete', 'kelas', 21, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:18'),
(89, 1, 'delete', 'kelas', 22, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:19'),
(90, 1, 'delete', 'kelas', 23, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:21'),
(91, 1, 'delete', 'kelas', 24, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:24'),
(92, 1, 'delete', 'kelas', 25, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:26'),
(93, 1, 'delete', 'kelas', 26, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:28'),
(94, 1, 'delete', 'kelas', 27, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:29'),
(95, 1, 'delete', 'kelas', 28, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:36'),
(96, 1, 'delete', 'kelas', 29, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:38'),
(97, 1, 'delete', 'kelas', 30, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:40'),
(98, 1, 'delete', 'kelas', 4, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:41'),
(99, 1, 'delete', 'kelas', 31, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:43'),
(100, 1, 'delete', 'kelas', 32, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:45'),
(101, 1, 'delete', 'kelas', 33, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:46'),
(102, 1, 'delete', 'kelas', 34, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:48'),
(103, 1, 'delete', 'kelas', 35, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:50'),
(104, 1, 'delete', 'kelas', 36, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:52'),
(105, 1, 'delete', 'kelas', 37, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:54'),
(106, 1, 'delete', 'kelas', 38, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:56'),
(107, 1, 'delete', 'kelas', 39, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:57'),
(108, 1, 'delete', 'kelas', 40, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:11:59'),
(109, 1, 'delete', 'kelas', 5, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:12:01'),
(110, 1, 'delete', 'kelas', 41, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:12:03'),
(111, 1, 'delete', 'kelas', 42, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:12:05'),
(112, 1, 'delete', 'kelas', 6, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:12:06'),
(113, 1, 'delete', 'kelas', 7, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:12:08'),
(114, 1, 'delete', 'kelas', 8, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:12:10'),
(115, 1, 'delete', 'kelas', 9, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:12:11'),
(116, 1, 'delete', 'kelas', 10, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:12:13'),
(117, 1, 'import', 'kelas', NULL, 'Import kelas: +40 baru, 1 update, 0 dilewati', '101.255.166.198', '2026-06-21 07:13:41'),
(118, 1, 'delete', 'kelas', 56, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:23'),
(119, 1, 'delete', 'kelas', 51, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:25'),
(120, 1, 'delete', 'kelas', 52, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:27'),
(121, 1, 'delete', 'kelas', 53, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:29'),
(122, 1, 'delete', 'kelas', 54, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:31'),
(123, 1, 'delete', 'kelas', 55, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:33'),
(124, 1, 'delete', 'kelas', 1, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:35'),
(125, 1, 'delete', 'kelas', 43, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:37'),
(126, 1, 'delete', 'kelas', 44, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:38'),
(127, 1, 'delete', 'kelas', 45, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:41'),
(128, 1, 'delete', 'kelas', 46, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:43'),
(129, 1, 'delete', 'kelas', 47, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:45'),
(130, 1, 'delete', 'kelas', 48, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:47'),
(131, 1, 'delete', 'kelas', 49, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:49'),
(132, 1, 'delete', 'kelas', 50, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:51'),
(133, 1, 'delete', 'kelas', 71, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:52'),
(134, 1, 'delete', 'kelas', 66, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:15:54'),
(135, 1, 'delete', 'kelas', 67, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:21'),
(136, 1, 'delete', 'kelas', 68, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:23'),
(137, 1, 'delete', 'kelas', 69, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:27'),
(138, 1, 'delete', 'kelas', 70, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:29'),
(139, 1, 'delete', 'kelas', 57, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:33'),
(140, 1, 'delete', 'kelas', 58, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:35'),
(141, 1, 'delete', 'kelas', 59, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:37'),
(142, 1, 'delete', 'kelas', 60, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:40'),
(143, 1, 'delete', 'kelas', 61, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:43'),
(144, 1, 'delete', 'kelas', 62, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:45'),
(145, 1, 'delete', 'kelas', 63, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:47'),
(146, 1, 'delete', 'kelas', 64, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:50'),
(147, 1, 'delete', 'kelas', 65, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:53'),
(148, 1, 'delete', 'kelas', 82, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:54'),
(149, 1, 'delete', 'kelas', 79, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:56'),
(150, 1, 'delete', 'kelas', 80, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:16:58'),
(151, 1, 'delete', 'kelas', 81, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:17:00'),
(152, 1, 'delete', 'kelas', 72, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:17:02'),
(153, 1, 'delete', 'kelas', 73, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:17:04'),
(154, 1, 'delete', 'kelas', 74, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:17:08'),
(155, 1, 'delete', 'kelas', 75, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:17:10'),
(156, 1, 'delete', 'kelas', 76, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:17:13'),
(157, 1, 'delete', 'kelas', 77, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:17:15'),
(158, 1, 'delete', 'kelas', 78, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:17:17'),
(159, 1, 'import', 'kelas', NULL, 'Import kelas: +0 baru, 41 update, 0 dilewati', '101.255.166.198', '2026-06-21 07:17:31'),
(160, 1, 'delete', 'kelas', 56, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:12'),
(161, 1, 'delete', 'kelas', 51, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:15'),
(162, 1, 'delete', 'kelas', 52, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:17'),
(163, 1, 'delete', 'kelas', 53, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:19'),
(164, 1, 'delete', 'kelas', 54, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:21'),
(165, 1, 'delete', 'kelas', 55, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:23'),
(166, 1, 'delete', 'kelas', 1, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:25'),
(167, 1, 'delete', 'kelas', 43, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:26'),
(168, 1, 'delete', 'kelas', 44, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:28'),
(169, 1, 'delete', 'kelas', 45, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:30'),
(170, 1, 'delete', 'kelas', 46, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:32'),
(171, 1, 'delete', 'kelas', 47, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:35'),
(172, 1, 'delete', 'kelas', 48, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:37'),
(173, 1, 'delete', 'kelas', 49, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:39'),
(174, 1, 'delete', 'kelas', 50, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:41'),
(175, 1, 'delete', 'kelas', 71, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:43'),
(176, 1, 'delete', 'kelas', 66, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:44'),
(177, 1, 'delete', 'kelas', 67, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:46'),
(178, 1, 'delete', 'kelas', 68, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:48'),
(179, 1, 'delete', 'kelas', 69, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:50'),
(180, 1, 'delete', 'kelas', 70, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:51'),
(181, 1, 'delete', 'kelas', 57, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:54'),
(182, 1, 'delete', 'kelas', 58, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:56'),
(183, 1, 'delete', 'kelas', 59, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:57'),
(184, 1, 'delete', 'kelas', 60, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:20:59'),
(185, 1, 'delete', 'kelas', 61, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:01'),
(186, 1, 'delete', 'kelas', 62, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:03'),
(187, 1, 'delete', 'kelas', 63, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:05'),
(188, 1, 'delete', 'kelas', 64, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:07'),
(189, 1, 'delete', 'kelas', 65, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:17'),
(190, 1, 'delete', 'kelas', 82, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:18'),
(191, 1, 'delete', 'kelas', 79, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:20'),
(192, 1, 'delete', 'kelas', 80, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:22'),
(193, 1, 'delete', 'kelas', 81, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:24'),
(194, 1, 'delete', 'kelas', 72, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:25'),
(195, 1, 'delete', 'kelas', 73, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:27'),
(196, 1, 'delete', 'kelas', 74, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:29'),
(197, 1, 'delete', 'kelas', 75, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:31'),
(198, 1, 'delete', 'kelas', 76, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:33'),
(199, 1, 'delete', 'kelas', 77, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:35'),
(200, 1, 'delete', 'kelas', 78, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:21:36'),
(201, 1, 'import', 'kelas', NULL, 'Import kelas: +0 baru, 41 update, 0 dilewati', '101.255.166.198', '2026-06-21 07:22:01'),
(202, 1, 'import', 'kelas', NULL, 'Import kelas: +1 baru, 41 update, 0 dilewati', '101.255.166.198', '2026-06-21 07:25:28'),
(203, 1, 'delete', 'kelas', 50, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:25:47'),
(204, 1, 'delete', 'kelas', 56, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:25:52'),
(205, 1, 'delete', 'kelas', 51, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:25:55'),
(206, 1, 'delete', 'kelas', 52, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:25:57'),
(207, 1, 'delete', 'kelas', 53, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:25:59'),
(208, 1, 'delete', 'kelas', 54, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:01'),
(209, 1, 'delete', 'kelas', 55, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:03'),
(210, 1, 'delete', 'kelas', 1, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:05'),
(211, 1, 'delete', 'kelas', 43, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:08'),
(212, 1, 'delete', 'kelas', 44, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:10'),
(213, 1, 'delete', 'kelas', 45, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:12'),
(214, 1, 'delete', 'kelas', 46, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:14'),
(215, 1, 'delete', 'kelas', 47, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:17'),
(216, 1, 'delete', 'kelas', 48, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:20'),
(217, 1, 'delete', 'kelas', 49, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:25'),
(218, 1, 'delete', 'kelas', 83, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:27'),
(219, 1, 'delete', 'kelas', 71, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:31'),
(220, 1, 'delete', 'kelas', 66, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:33'),
(221, 1, 'delete', 'kelas', 67, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:35'),
(222, 1, 'delete', 'kelas', 68, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:37'),
(223, 1, 'delete', 'kelas', 69, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:42'),
(224, 1, 'delete', 'kelas', 70, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:44'),
(225, 1, 'delete', 'kelas', 57, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:47'),
(226, 1, 'delete', 'kelas', 58, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:49'),
(227, 1, 'delete', 'kelas', 59, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:51'),
(228, 1, 'delete', 'kelas', 60, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:53'),
(229, 1, 'delete', 'kelas', 61, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:26:58'),
(230, 1, 'delete', 'kelas', 62, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:00'),
(231, 1, 'delete', 'kelas', 63, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:02'),
(232, 1, 'delete', 'kelas', 64, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:05'),
(233, 1, 'delete', 'kelas', 65, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:07'),
(234, 1, 'delete', 'kelas', 82, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:09'),
(235, 1, 'delete', 'kelas', 79, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:11'),
(236, 1, 'delete', 'kelas', 80, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:13'),
(237, 1, 'delete', 'kelas', 81, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:15'),
(238, 1, 'delete', 'kelas', 72, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:18'),
(239, 1, 'delete', 'kelas', 73, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:23'),
(240, 1, 'delete', 'kelas', 74, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:25'),
(241, 1, 'delete', 'kelas', 75, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:27'),
(242, 1, 'delete', 'kelas', 76, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:29'),
(243, 1, 'delete', 'kelas', 77, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:31'),
(244, 1, 'delete', 'kelas', 78, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:27:35'),
(245, 1, 'import', 'kelas', NULL, 'Import kelas: +0 baru, 1 update, 0 dilewati', '101.255.166.198', '2026-06-21 07:29:11'),
(246, 1, 'update', 'kelas', 83, 'Ubah kelas X TKJT 1', '101.255.166.198', '2026-06-21 07:30:00'),
(247, 1, 'delete', 'kelas', 83, 'Hapus kelas', '101.255.166.198', '2026-06-21 07:30:06'),
(248, 1, 'delete', 'guru', 1, 'Hapus guru', '101.255.166.198', '2026-06-21 07:31:39'),
(249, 1, 'import', 'guru', NULL, 'Import guru: +47 baru, 1 update, 0 dilewati', '114.8.197.122', '2026-06-21 09:22:07'),
(250, 1, 'update', 'jurusan', 1, 'Ubah jurusan 1', '114.8.197.122', '2026-06-21 09:28:36'),
(251, 1, 'create', 'kelas', 84, 'Tambah kelas 10 TKJ 1', '114.8.197.122', '2026-06-21 09:29:39'),
(252, 1, 'create', 'kelas', 85, 'Tambah kelas 10 TKJ 2', '114.8.197.122', '2026-06-21 09:30:27'),
(253, 1, 'create', 'kelas', 86, 'Tambah kelas 10 TKJ 3', '114.8.197.122', '2026-06-21 09:31:47'),
(254, 1, 'create', 'kelas', 87, 'Tambah kelas 10 TKJ 4', '114.8.197.122', '2026-06-21 09:35:22'),
(255, 1, 'create', 'kelas', 88, 'Tambah kelas 10 TKJ 5', '114.8.197.122', '2026-06-21 09:35:39'),
(256, 1, 'create', 'kelas', 89, 'Tambah kelas 10 TKJ 6', '114.8.197.122', '2026-06-21 09:37:33'),
(257, 1, 'create', 'kelas', 90, 'Tambah kelas 10 TKJ 7', '114.8.197.122', '2026-06-21 09:37:54'),
(258, 1, 'create', 'kelas', 91, 'Tambah kelas 10 TKJ 8', '114.8.197.122', '2026-06-21 09:38:54'),
(259, 1, 'create', 'kelas', 92, 'Tambah kelas 10 TKJ 9', '114.8.197.122', '2026-06-21 09:39:41'),
(260, 1, 'create', 'kelas', 93, 'Tambah kelas 10 MPLB 1', '114.8.197.122', '2026-06-21 09:40:28'),
(261, 1, 'create', 'kelas', 94, 'Tambah kelas 10 MPLB 2', '114.8.197.122', '2026-06-21 09:41:31'),
(262, 1, 'create', 'kelas', 95, 'Tambah kelas 10 MPLB 3', '114.8.197.122', '2026-06-21 09:41:50'),
(263, 1, 'create', 'kelas', 96, 'Tambah kelas 10 MPLB 4', '114.8.197.122', '2026-06-21 09:42:40'),
(264, 1, 'create', 'kelas', 97, 'Tambah kelas 10 MPLB 5', '114.8.197.122', '2026-06-21 09:43:02'),
(265, 1, 'create', 'kelas', 98, 'Tambah kelas 10 AKL', '114.8.197.122', '2026-06-21 09:43:44'),
(266, 1, 'create', 'kelas', 99, 'Tambah kelas 11 TKJT 1', '114.8.197.122', '2026-06-21 09:44:46'),
(267, 1, 'create', 'kelas', 100, 'Tambah kelas 11 TKJ 2', '114.8.197.122', '2026-06-21 09:45:58'),
(268, 1, 'create', 'kelas', 101, 'Tambah kelas 11 TKJ 3', '114.8.197.122', '2026-06-21 09:46:58'),
(269, 1, 'update', 'kelas', 99, 'Ubah kelas 11 TKJT 1', '114.8.197.122', '2026-06-21 09:47:17'),
(270, 1, 'create', 'kelas', 102, 'Tambah kelas 11 TKJ 4', '114.8.197.122', '2026-06-21 09:47:48'),
(271, 1, 'create', 'kelas', 103, 'Tambah kelas 11 TKJ 5', '114.8.197.122', '2026-06-21 09:48:08'),
(272, 1, 'create', 'kelas', 104, 'Tambah kelas 11 TKJ 6', '114.8.197.122', '2026-06-21 09:49:47'),
(273, 1, 'create', 'kelas', 105, 'Tambah kelas 11 TKJ 7', '114.8.197.122', '2026-06-21 09:50:14'),
(274, 1, 'update', 'kelas', 99, 'Ubah kelas 11 TKJ 1', '114.8.197.122', '2026-06-21 09:50:35'),
(275, 1, 'create', 'kelas', 106, 'Tambah kelas 11 TKJT 9', '114.8.197.122', '2026-06-21 09:50:59'),
(276, 1, 'update', 'kelas', 106, 'Ubah kelas 11 TKJ 9', '114.8.197.122', '2026-06-21 09:51:15'),
(277, 1, 'create', 'kelas', 107, 'Tambah kelas 11 TKJ 8', '114.8.197.122', '2026-06-21 09:51:39'),
(278, 1, 'create', 'kelas', 108, 'Tambah kelas 11 MPLB 1', '114.8.197.122', '2026-06-21 09:52:11'),
(279, 1, 'create', 'kelas', 109, 'Tambah kelas 11 MPLB 2', '114.8.197.122', '2026-06-21 09:52:32'),
(280, 1, 'create', 'kelas', 110, 'Tambah kelas 11 MPLB 3', '114.8.197.122', '2026-06-21 09:53:00'),
(281, 1, 'create', 'kelas', 111, 'Tambah kelas 11 MPLB 4', '114.8.197.122', '2026-06-21 09:53:18'),
(282, 1, 'create', 'kelas', 112, 'Tambah kelas 11 MPLB 5', '114.8.197.122', '2026-06-21 09:53:47'),
(283, 1, 'create', 'kelas', 113, 'Tambah kelas 11 AKL', '114.8.197.122', '2026-06-21 09:54:31'),
(284, 1, 'create', 'kelas', 114, 'Tambah kelas 12 TKJ 1', '114.8.197.122', '2026-06-21 09:55:06'),
(285, 1, 'create', 'kelas', 115, 'Tambah kelas 12 TKJ 2', '114.8.197.122', '2026-06-21 09:55:24'),
(286, 1, 'create', 'kelas', 116, 'Tambah kelas 12 TKJ 3', '114.8.197.122', '2026-06-21 09:56:00'),
(287, 1, 'create', 'kelas', 117, 'Tambah kelas 12 TKJ 4', '114.8.197.122', '2026-06-21 09:56:20'),
(288, 1, 'create', 'kelas', 118, 'Tambah kelas 12 TKJ 5', '114.8.197.122', '2026-06-21 09:56:53'),
(289, 1, 'create', 'kelas', 119, 'Tambah kelas 12 TKJ 6', '114.8.197.122', '2026-06-21 09:57:28'),
(290, 1, 'create', 'kelas', 120, 'Tambah kelas 12 TKJ 7', '114.8.197.122', '2026-06-21 09:58:09'),
(291, 1, 'create', 'kelas', 121, 'Tambah kelas 12 MPLB 1', '114.8.197.122', '2026-06-21 09:58:36'),
(292, 1, 'create', 'kelas', 122, 'Tambah kelas 12 MPLB 2', '114.8.197.122', '2026-06-21 09:59:14'),
(293, 1, 'update', 'kelas', 120, 'Ubah kelas 12 TKJ 7', '114.8.197.122', '2026-06-21 09:59:41'),
(294, 1, 'create', 'kelas', 123, 'Tambah kelas 12 MPLB 3', '114.8.197.122', '2026-06-21 10:00:21'),
(295, 1, 'create', 'kelas', 124, 'Tambah kelas 12 AKL', '114.8.197.122', '2026-06-21 10:00:52'),
(296, 1, 'create', 'jam_pelajaran', 2, 'Tambah jam pagi ke-2', '114.8.197.122', '2026-06-21 10:03:05'),
(297, 1, 'update', 'jam_pelajaran', 2, 'Ubah jam pagi ke-2', '114.8.197.122', '2026-06-21 10:04:21'),
(298, 1, 'create', 'jam_pelajaran', 3, 'Tambah jam pagi ke-3', '114.8.197.122', '2026-06-21 10:05:00'),
(299, 1, 'create', 'jam_pelajaran', 4, 'Tambah jam pagi ke-4', '114.8.197.122', '2026-06-21 10:05:43'),
(300, 1, 'create', 'jam_pelajaran', 5, 'Tambah jam pagi ke-5', '114.8.197.122', '2026-06-21 10:06:32'),
(301, 1, 'create', 'jam_pelajaran', 7, 'Tambah jam pagi ke-6', '114.8.197.122', '2026-06-21 10:08:13'),
(302, 1, 'create', 'jam_pelajaran', 8, 'Tambah jam pagi ke-7', '114.8.197.122', '2026-06-21 10:10:55'),
(303, 1, 'create', 'jam_pelajaran', 9, 'Tambah jam pagi ke-8', '114.8.197.122', '2026-06-21 10:11:30'),
(304, 1, 'update', 'jam_pelajaran', 5, 'Ubah jam pagi ke-10', '114.8.197.122', '2026-06-21 10:12:30'),
(305, 1, 'update', 'jam_pelajaran', 5, 'Ubah jam pagi ke-5', '114.8.197.122', '2026-06-21 10:12:53'),
(306, 1, 'update', 'jam_pelajaran', 5, 'Ubah jam pagi ke-11', '114.8.197.122', '2026-06-21 10:13:56'),
(307, 1, 'update', 'jam_pelajaran', 7, 'Ubah jam pagi ke-5', '114.8.197.122', '2026-06-21 10:14:05'),
(308, 1, 'update', 'jam_pelajaran', 8, 'Ubah jam pagi ke-6', '114.8.197.122', '2026-06-21 10:14:14'),
(309, 1, 'update', 'jam_pelajaran', 9, 'Ubah jam pagi ke-7', '114.8.197.122', '2026-06-21 10:14:21'),
(310, 1, 'create', 'jam_pelajaran', 13, 'Tambah jam pagi ke-8', '114.8.197.122', '2026-06-21 10:15:19'),
(311, 1, 'create', 'jadwal', 84, 'Generate otomatis: 0 JP', '114.8.197.122', '2026-06-21 10:18:44'),
(312, 1, 'create', 'jam_pelajaran', 14, 'Tambah jam siang ke-1', '114.8.197.122', '2026-06-21 17:34:19'),
(313, 1, 'create', 'jam_pelajaran', 15, 'Tambah jam siang ke-2', '114.8.197.122', '2026-06-21 17:35:02'),
(314, 1, 'create', 'jam_pelajaran', 16, 'Tambah jam siang ke-3', '114.8.197.122', '2026-06-21 17:36:17'),
(315, 1, 'create', 'jam_pelajaran', 17, 'Tambah jam siang ke-4', '114.8.197.122', '2026-06-21 17:36:53'),
(316, 1, 'create', 'jam_pelajaran', 18, 'Tambah jam siang ke-9', '114.8.197.122', '2026-06-21 17:37:35'),
(317, 1, 'create', 'jam_pelajaran', 19, 'Tambah jam siang ke-5', '114.8.197.122', '2026-06-21 17:38:22'),
(318, 1, 'create', 'jam_pelajaran', 20, 'Tambah jam siang ke-6', '114.8.197.122', '2026-06-21 17:39:05'),
(319, 1, 'create', 'jam_pelajaran', 21, 'Tambah jam siang ke-7', '114.8.197.122', '2026-06-21 17:39:38'),
(320, 1, 'create', 'jam_pelajaran', 22, 'Tambah jam siang ke-8', '114.8.197.122', '2026-06-21 17:40:19'),
(321, 1, 'update', 'guru_mapel', 1, 'Atur kompetensi: 4 guru', '114.8.197.122', '2026-06-21 17:45:53'),
(322, 1, 'update', 'guru_mapel', 14, 'Atur kompetensi: 4 guru', '114.8.197.122', '2026-06-21 17:47:46'),
(323, 1, 'update', 'ketersediaan_guru', 20, 'Atur ketersediaan (siang): 9 slot tidak tersedia', '114.4.215.53', '2026-06-21 20:10:52'),
(324, 1, 'update', 'guru_mapel', 5, 'Atur kompetensi: 1 guru', '114.4.215.53', '2026-06-21 20:15:53'),
(325, 1, 'update', 'guru_mapel', 4, 'Atur kompetensi: 2 guru', '114.4.215.53', '2026-06-21 20:16:37'),
(326, 1, 'update', 'guru_mapel', 13, 'Atur kompetensi: 1 guru', '114.4.215.53', '2026-06-21 20:17:11'),
(327, 1, 'update', 'guru_mapel', 13, 'Atur kompetensi: 4 guru', '114.4.215.53', '2026-06-21 20:17:47'),
(328, 1, 'create', 'pengampu', 2, 'Tambah penugasan kelas#108', '101.255.166.198', '2026-06-22 03:43:32'),
(329, 1, 'create', 'pengampu', 3, 'Tambah penugasan kelas#109', '101.255.166.198', '2026-06-22 03:43:59'),
(330, 1, 'create', 'pengampu', 4, 'Tambah penugasan kelas#110', '101.255.166.198', '2026-06-22 03:44:19'),
(331, 1, 'create', 'pengampu', 5, 'Tambah penugasan kelas#111', '101.255.166.198', '2026-06-22 03:44:36'),
(332, 1, 'create', 'pengampu', 6, 'Tambah penugasan kelas#112', '101.255.166.198', '2026-06-22 03:44:54'),
(333, 1, 'create', 'pengampu', 7, 'Tambah penugasan kelas#99', '101.255.166.198', '2026-06-22 03:45:12'),
(334, 1, 'create', 'pengampu', 8, 'Tambah penugasan kelas#100', '101.255.166.198', '2026-06-22 03:45:34'),
(335, 1, 'create', 'pengampu', 9, 'Tambah penugasan kelas#101', '101.255.166.198', '2026-06-22 03:45:50'),
(336, 1, 'create', 'pengampu', 10, 'Tambah penugasan kelas#102', '101.255.166.198', '2026-06-22 03:46:08'),
(337, 1, 'create', 'pengampu', 11, 'Tambah penugasan kelas#103', '101.255.166.198', '2026-06-22 03:46:24'),
(338, 1, 'create', 'pengampu', 12, 'Tambah penugasan kelas#104', '101.255.166.198', '2026-06-22 03:46:39'),
(339, 1, 'create', 'pengampu', 13, 'Tambah penugasan kelas#105', '101.255.166.198', '2026-06-22 03:46:58'),
(340, 1, 'create', 'pengampu', 14, 'Tambah penugasan kelas#107', '101.255.166.198', '2026-06-22 03:47:14'),
(341, 1, 'create', 'pengampu', 15, 'Tambah penugasan kelas#106', '101.255.166.198', '2026-06-22 03:47:30'),
(342, 1, 'create', 'pengampu', 16, 'Tambah penugasan kelas#113', '101.255.166.198', '2026-06-22 03:48:05'),
(343, 1, 'create', 'pengampu', 17, 'Tambah penugasan kelas#124', '101.255.166.198', '2026-06-22 03:48:23'),
(344, 1, 'create', 'pengampu', 18, 'Tambah penugasan kelas#121', '101.255.166.198', '2026-06-22 03:48:40'),
(345, 1, 'create', 'pengampu', 19, 'Tambah penugasan kelas#122', '101.255.166.198', '2026-06-22 03:48:56'),
(346, 1, 'create', 'pengampu', 20, 'Tambah penugasan kelas#123', '101.255.166.198', '2026-06-22 03:49:13'),
(347, 1, 'create', 'pengampu', 21, 'Tambah penugasan kelas#114', '101.255.166.198', '2026-06-22 03:49:31'),
(348, 1, 'create', 'pengampu', 22, 'Tambah penugasan kelas#115', '101.255.166.198', '2026-06-22 03:49:46'),
(349, 1, 'create', 'pengampu', 23, 'Tambah penugasan kelas#116', '101.255.166.198', '2026-06-22 03:50:01'),
(350, 1, 'create', 'pengampu', 24, 'Tambah penugasan kelas#117', '101.255.166.198', '2026-06-22 03:50:14'),
(351, 1, 'create', 'pengampu', 25, 'Tambah penugasan kelas#118', '101.255.166.198', '2026-06-22 03:50:30'),
(352, 1, 'create', 'pengampu', 26, 'Tambah penugasan kelas#119', '101.255.166.198', '2026-06-22 03:50:43'),
(353, 1, 'create', 'pengampu', 27, 'Tambah penugasan kelas#120', '101.255.166.198', '2026-06-22 03:50:56'),
(354, 1, 'create', 'jadwal', 124, 'Generate otomatis: 2 JP', '101.255.166.198', '2026-06-22 03:51:55'),
(355, 1, 'delete', 'jadwal', 3, 'Hapus sel jadwal', '101.255.166.198', '2026-06-22 03:52:42'),
(356, 1, 'create', 'jadwal', 124, 'Generate otomatis: 1 JP', '101.255.166.198', '2026-06-22 03:52:50'),
(357, 1, 'delete', 'jadwal', 4, 'Hapus sel jadwal', '101.255.166.198', '2026-06-22 03:52:54'),
(358, 1, 'create', 'jadwal', 124, 'Generate otomatis: 2 JP', '101.255.166.198', '2026-06-22 03:53:00'),
(359, 1, 'delete', 'jadwal', 6, 'Hapus sel jadwal', '101.255.166.198', '2026-06-22 03:53:08'),
(360, 1, 'delete', 'jadwal', 5, 'Hapus sel jadwal', '101.255.166.198', '2026-06-22 03:53:10'),
(361, 1, 'update', 'ketersediaan_guru', 20, 'Atur ketersediaan (pagi): 17 slot tidak tersedia', '114.4.78.17', '2026-06-22 13:40:41'),
(362, 1, 'update', 'ketersediaan_guru', 20, 'Atur ketersediaan (pagi): 9 slot tidak tersedia', '114.4.78.17', '2026-06-22 13:41:03'),
(363, 1, 'update', 'ketersediaan_guru', 12, 'Atur ketersediaan (pagi): 16 slot tidak tersedia', '114.4.78.17', '2026-06-22 13:41:48'),
(364, 1, 'update', 'ketersediaan_guru', 12, 'Atur ketersediaan (siang): 34 slot tidak tersedia', '114.4.78.17', '2026-06-22 13:42:07'),
(365, 1, 'update', 'ketersediaan_guru', 19, 'Atur ketersediaan (pagi): 16 slot tidak tersedia', '114.4.78.17', '2026-06-22 13:42:57'),
(366, 1, 'update', 'ketersediaan_guru', 19, 'Atur ketersediaan (siang): 34 slot tidak tersedia', '114.4.78.17', '2026-06-22 13:43:16'),
(367, 1, 'create', 'jadwal', 98, 'Generate otomatis: 0 JP', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 22:19:46'),
(368, 1, 'create', 'jadwal', 104, 'Generate otomatis: 2 JP', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 22:19:59'),
(369, 1, 'create', 'jadwal', 9, 'Tempatkan 7 kelas#113', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 22:25:59'),
(370, 1, 'create', 'jadwal', 10, 'Tempatkan 7 kelas#113', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 22:26:08'),
(371, 1, 'delete', 'jadwal', 10, 'Hapus sel jadwal', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 22:26:14'),
(372, 1, 'create', 'jadwal', 11, 'Tempatkan 7 kelas#113', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 22:26:25'),
(373, 1, 'delete', 'jadwal', 11, 'Hapus sel jadwal', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 22:26:32'),
(374, 1, 'import', 'kelas', NULL, 'Import kelas: +0 baru, 41 update, 0 dilewati', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 22:35:23'),
(375, 1, 'import', 'kelas', NULL, 'Import kelas: +0 baru, 41 update, 0 dilewati', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 22:40:44'),
(376, 1, 'update', 'kelas', 81, 'Ubah kelas XII MPLB 3', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 22:41:37'),
(377, 1, 'delete', 'kelas', NULL, 'Hapus massal 82 kelas (all)', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 22:44:39'),
(378, 1, 'import', 'kelas', NULL, 'Import kelas: +0 baru, 41 update, 0 dilewati', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 22:59:52'),
(379, 1, 'update', 'kelas', 56, 'Ubah kelas X AKL', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 23:00:45'),
(380, 1, 'delete', 'kelas', NULL, 'Hapus massal 41 kelas (all)', '2001:448a:2003:25c4:2424:e5c0:ef7c:8a5d', '2026-06-22 23:02:51'),
(381, 1, 'import', 'kelas', NULL, 'Import kelas: +0 baru, 4 update, 0 dilewati', '2001:448a:2003:25c4:2424:e5c0:ef7c:8a5d', '2026-06-22 23:11:12'),
(382, 1, 'delete', 'kelas', NULL, 'Hapus massal 4 kelas (all)', '2001:448a:2003:25c4:2424:e5c0:ef7c:8a5d', '2026-06-22 23:11:35'),
(383, 1, 'import', 'kelas', NULL, 'Import kelas: +0 baru, 1 update, 0 dilewati', '2001:448a:2003:25c4:2424:e5c0:ef7c:8a5d', '2026-06-22 23:12:16'),
(384, 1, 'update', 'kelas', 83, 'Ubah kelas X TKJT 1', '2001:448a:2003:25c4:2424:e5c0:ef7c:8a5d', '2026-06-22 23:13:29'),
(385, 1, 'delete', 'kelas', NULL, 'Hapus massal 1 kelas (all)', '2001:448a:2003:25c4:2424:e5c0:ef7c:8a5d', '2026-06-22 23:18:01'),
(386, 1, 'import', 'kelas', NULL, 'Import kelas: +0 baru, 1 update, 0 dilewati', '2001:448a:2003:25c4:2424:e5c0:ef7c:8a5d', '2026-06-22 23:18:28'),
(387, 1, 'delete', 'kelas', 83, 'Hapus kelas', '2001:448a:2003:25c4:2424:e5c0:ef7c:8a5d', '2026-06-22 23:18:51'),
(388, 1, 'import', 'kelas', NULL, 'Import kelas: +0 baru, 1 update, 0 dilewati', '2001:448a:2003:25c4:2424:e5c0:ef7c:8a5d', '2026-06-22 23:19:05'),
(389, 1, 'delete', 'kelas', NULL, 'Hapus massal 1 kelas (all)', '2001:448a:2003:25c4:2424:e5c0:ef7c:8a5d', '2026-06-22 23:19:14'),
(390, 1, 'import', 'kelas', NULL, 'Import kelas: +0 baru, 41 update, 0 dilewati', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 23:19:38'),
(391, 1, 'update', 'jam_pelajaran', 18, 'Ubah jam siang ke-9', '2001:448a:2003:25c4:edf8:a85:39b6:637a', '2026-06-22 23:39:51'),
(392, 1, 'delete', 'jurusan', 1, 'Hapus jurusan', '101.255.166.198', '2026-06-24 16:27:08'),
(393, 1, 'delete', 'jurusan', 2, 'Hapus jurusan', '101.255.166.198', '2026-06-24 16:27:12'),
(394, 1, 'delete', 'jurusan', 3, 'Hapus jurusan', '101.255.166.198', '2026-06-24 16:27:16'),
(395, 1, 'update', 'guru_mapel', 10, 'Atur kompetensi: 1 guru', '101.255.166.198', '2026-06-24 16:27:52'),
(396, 1, 'update', 'guru_mapel', 17, 'Atur kompetensi: 1 guru', '101.255.166.198', '2026-06-24 16:28:13'),
(397, 1, 'update', 'guru_mapel', 27, 'Atur kompetensi: 1 guru', '101.255.166.198', '2026-06-24 16:28:26'),
(398, 1, 'update', 'guru_mapel', 28, 'Atur kompetensi: 1 guru', '101.255.166.198', '2026-06-24 16:28:36'),
(399, 1, 'update', 'guru_mapel', 29, 'Atur kompetensi: 1 guru', '101.255.166.198', '2026-06-24 16:28:47'),
(400, 1, 'update', 'guru_mapel', 22, 'Atur kompetensi: 1 guru', '101.255.166.198', '2026-06-24 16:28:59'),
(401, 1, 'update', 'guru_mapel', 24, 'Atur kompetensi: 2 guru', '101.255.166.198', '2026-06-24 16:29:11'),
(402, 1, 'update', 'guru_mapel', 18, 'Atur kompetensi: 2 guru', '101.255.166.198', '2026-06-24 16:29:58'),
(403, 1, 'update', 'guru_mapel', 16, 'Atur kompetensi: 6 guru', '101.255.166.198', '2026-06-24 16:30:46'),
(404, 1, 'create', 'pengampu', 28, 'Tambah penugasan kelas#1', '154.19.39.89', '2026-07-01 19:27:08'),
(405, 1, 'create', 'pengampu', 29, 'Tambah penugasan kelas#1', '154.19.39.89', '2026-07-01 19:27:55'),
(406, 1, 'create', 'pengampu', 30, 'Tambah penugasan kelas#1', '154.19.39.89', '2026-07-01 19:28:26'),
(407, 1, 'create', 'pengampu', 31, 'Tambah penugasan kelas#1', '154.19.39.89', '2026-07-01 19:28:49'),
(408, 1, 'create', 'pengampu', 32, 'Tambah penugasan kelas#1', '154.19.39.89', '2026-07-01 19:29:35'),
(409, 1, 'create', 'pengampu', 33, 'Tambah penugasan kelas#1', '154.19.39.89', '2026-07-01 19:30:10'),
(410, 1, 'update', 'guru_mapel', 4, 'Atur kompetensi: 3 guru', '154.19.39.89', '2026-07-01 19:31:40'),
(411, 1, 'create', 'pengampu', 34, 'Tambah penugasan kelas#1', '154.19.39.89', '2026-07-01 19:32:02'),
(412, 1, 'create', 'pengampu', 35, 'Tambah penugasan kelas#1', '154.19.39.89', '2026-07-01 19:32:49'),
(413, 1, 'create', 'pengampu', 36, 'Tambah penugasan kelas#1', '154.19.39.89', '2026-07-01 19:33:12'),
(414, 1, 'create', 'pengampu', 37, 'Tambah penugasan kelas#1', '154.19.39.89', '2026-07-01 19:33:47'),
(415, 1, 'create', 'pengampu', 38, 'Tambah penugasan kelas#1', '154.19.39.89', '2026-07-01 19:34:07'),
(416, 1, 'update', 'pengampu', 36, 'Ubah penugasan', '154.19.39.89', '2026-07-01 19:34:46'),
(417, 1, 'create', 'jadwal', 56, 'Generate otomatis: 0 JP', '154.19.39.89', '2026-07-01 19:35:03'),
(418, 1, 'create', 'jadwal', 1, 'Generate otomatis: 40 JP', '154.19.39.89', '2026-07-01 19:35:27'),
(419, 1, 'create', 'jadwal', 1, 'Generate otomatis: 40 JP', '154.19.39.89', '2026-07-01 19:36:11'),
(420, 1, 'delete', 'jadwal', 83, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:36:17'),
(421, 1, 'create', 'jadwal', 92, 'Tempatkan 4A kelas#1', '154.19.39.89', '2026-07-01 19:36:27'),
(422, 1, 'delete', 'jadwal', 52, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:36:54'),
(423, 1, 'delete', 'jadwal', 55, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:36:56'),
(424, 1, 'delete', 'jadwal', 58, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:36:57'),
(425, 1, 'delete', 'jadwal', 60, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:37:13'),
(426, 1, 'delete', 'jadwal', 63, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:37:17'),
(427, 1, 'delete', 'jadwal', 64, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:37:20'),
(428, 1, 'delete', 'jadwal', 68, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:37:21'),
(429, 1, 'delete', 'jadwal', 72, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:37:23'),
(430, 1, 'delete', 'jadwal', 77, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:37:32'),
(431, 1, 'delete', 'jadwal', 76, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:37:38'),
(432, 1, 'create', 'jadwal', 93, 'Tempatkan 11 kelas#1', '154.19.39.89', '2026-07-01 19:37:49'),
(433, 1, 'update', 'jadwal', 93, 'Pindah sel jadwal', '154.19.39.89', '2026-07-01 19:37:52'),
(434, 1, 'update', 'jadwal', 93, 'Pindah sel jadwal', '154.19.39.89', '2026-07-01 19:37:55'),
(435, 1, 'update', 'jadwal', 78, 'Pindah sel jadwal', '154.19.39.89', '2026-07-01 19:38:04'),
(436, 1, 'update', 'jadwal', 79, 'Pindah sel jadwal', '154.19.39.89', '2026-07-01 19:38:10'),
(437, 1, 'delete', 'jadwal', 62, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:23'),
(438, 1, 'delete', 'jadwal', 73, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:24'),
(439, 1, 'delete', 'jadwal', 69, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:25'),
(440, 1, 'delete', 'jadwal', 65, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:26'),
(441, 1, 'delete', 'jadwal', 61, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:27'),
(442, 1, 'delete', 'jadwal', 53, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:28'),
(443, 1, 'delete', 'jadwal', 56, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:30'),
(444, 1, 'delete', 'jadwal', 59, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:31'),
(445, 1, 'delete', 'jadwal', 67, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:32'),
(446, 1, 'delete', 'jadwal', 71, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:33'),
(447, 1, 'delete', 'jadwal', 54, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:35'),
(448, 1, 'delete', 'jadwal', 57, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:37'),
(449, 1, 'delete', 'jadwal', 75, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:38'),
(450, 1, 'delete', 'jadwal', 82, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:40'),
(451, 1, 'delete', 'jadwal', 66, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:42'),
(452, 1, 'delete', 'jadwal', 81, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:46'),
(453, 1, 'delete', 'jadwal', 84, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:48'),
(454, 1, 'delete', 'jadwal', 70, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:50'),
(455, 1, 'delete', 'jadwal', 74, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:52'),
(456, 1, 'delete', 'jadwal', 80, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:53'),
(457, 1, 'delete', 'jadwal', 92, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:54'),
(458, 1, 'delete', 'jadwal', 86, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:56'),
(459, 1, 'delete', 'jadwal', 88, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:58'),
(460, 1, 'delete', 'jadwal', 87, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:38:59'),
(461, 1, 'delete', 'jadwal', 91, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:39:01'),
(462, 1, 'delete', 'jadwal', 90, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:39:02'),
(463, 1, 'delete', 'jadwal', 89, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:39:04'),
(464, 1, 'delete', 'jadwal', 85, 'Hapus sel jadwal', '154.19.39.89', '2026-07-01 19:39:05'),
(465, 1, 'create', 'jadwal', 94, 'Tempatkan 11 kelas#1', '154.19.39.89', '2026-07-01 19:39:12'),
(466, 1, 'update', 'jadwal', 94, 'Pindah sel jadwal', '154.19.39.89', '2026-07-01 19:39:15'),
(467, 1, 'create', 'jadwal', 95, 'Tempatkan 6 kelas#1', '154.19.39.89', '2026-07-01 19:39:34'),
(468, 1, 'update', 'jadwal', 95, 'Pindah sel jadwal', '154.19.39.89', '2026-07-01 19:39:38'),
(469, 1, 'create', 'jadwal', 96, 'Tempatkan 6 kelas#1', '154.19.39.89', '2026-07-01 19:39:42'),
(470, 1, 'update', 'jadwal', 96, 'Pindah sel jadwal', '154.19.39.89', '2026-07-01 19:39:45'),
(471, 1, 'create', 'jadwal', 97, 'Tempatkan 2 kelas#1', '154.19.39.89', '2026-07-01 19:39:48'),
(472, 1, 'create', 'jadwal', 98, 'Tempatkan 2 kelas#1', '154.19.39.89', '2026-07-01 19:39:50'),
(473, 1, 'create', 'jadwal', 99, 'Tempatkan 9 kelas#1', '154.19.39.89', '2026-07-01 19:40:09'),
(474, 1, 'create', 'jadwal', 100, 'Tempatkan 9 kelas#1', '154.19.39.89', '2026-07-01 19:40:11'),
(475, 1, 'create', 'jadwal', 101, 'Tempatkan 9 kelas#1', '154.19.39.89', '2026-07-01 19:40:14'),
(476, 1, 'create', 'jadwal', 102, 'Tempatkan 9 kelas#1', '154.19.39.89', '2026-07-01 19:40:16'),
(477, 1, 'create', 'jadwal', 103, 'Tempatkan 8A kelas#1', '154.19.39.89', '2026-07-01 19:40:20'),
(478, 1, 'create', 'jadwal', 104, 'Tempatkan 8A kelas#1', '154.19.39.89', '2026-07-01 19:40:22'),
(479, 1, 'create', 'jadwal', 105, 'Tempatkan 8A kelas#1', '154.19.39.89', '2026-07-01 19:40:23'),
(480, 1, 'create', 'jadwal', 106, 'Tempatkan 8A kelas#1', '154.19.39.89', '2026-07-01 19:40:26'),
(481, 1, 'create', 'jadwal', 107, 'Tempatkan 12A kelas#1', '154.19.39.89', '2026-07-01 19:40:42'),
(482, 1, 'create', 'jadwal', 108, 'Tempatkan 12A kelas#1', '154.19.39.89', '2026-07-01 19:40:43'),
(483, 1, 'create', 'jadwal', 109, 'Tempatkan 12A kelas#1', '154.19.39.89', '2026-07-01 19:40:45'),
(484, 1, 'create', 'jadwal', 110, 'Tempatkan 12A kelas#1', '154.19.39.89', '2026-07-01 19:40:47'),
(485, 1, 'create', 'jadwal', 111, 'Tempatkan 12A kelas#1', '154.19.39.89', '2026-07-01 19:40:50'),
(486, 1, 'create', 'jadwal', 112, 'Tempatkan 12A kelas#1', '154.19.39.89', '2026-07-01 19:40:52'),
(487, 1, 'create', 'jadwal', 113, 'Tempatkan 12A kelas#1', '154.19.39.89', '2026-07-01 19:40:54'),
(488, 1, 'create', 'jadwal', 114, 'Tempatkan 12A kelas#1', '154.19.39.89', '2026-07-01 19:40:56'),
(489, 1, 'create', 'jadwal', 115, 'Tempatkan 3A kelas#1', '154.19.39.89', '2026-07-01 19:41:09'),
(490, 1, 'create', 'jadwal', 116, 'Tempatkan 3A kelas#1', '154.19.39.89', '2026-07-01 19:41:11'),
(491, 1, 'create', 'jadwal', 117, 'Tempatkan 3A kelas#1', '154.19.39.89', '2026-07-01 19:41:12'),
(492, 1, 'create', 'jadwal', 118, 'Tempatkan 3A kelas#1', '154.19.39.89', '2026-07-01 19:41:14'),
(493, 1, 'create', 'jadwal', 119, 'Tempatkan 5 kelas#1', '154.19.39.89', '2026-07-01 19:41:19'),
(494, 1, 'create', 'jadwal', 120, 'Tempatkan 5 kelas#1', '154.19.39.89', '2026-07-01 19:41:21'),
(495, 1, 'update', 'jadwal', 120, 'Pindah sel jadwal', '154.19.39.89', '2026-07-01 19:41:28'),
(496, 1, 'update', 'jadwal', 120, 'Pindah sel jadwal', '154.19.39.89', '2026-07-01 19:41:34'),
(497, 1, 'update', 'jadwal', 120, 'Pindah sel jadwal', '154.19.39.89', '2026-07-01 19:41:37'),
(498, 1, 'update', 'jadwal', 119, 'Pindah sel jadwal', '154.19.39.89', '2026-07-01 19:41:38'),
(499, 1, 'create', 'jadwal', 121, 'Tempatkan 10 kelas#1', '154.19.39.89', '2026-07-01 19:41:40'),
(500, 1, 'create', 'jadwal', 122, 'Tempatkan 10 kelas#1', '154.19.39.89', '2026-07-01 19:41:42'),
(501, 1, 'create', 'jadwal', 123, 'Tempatkan 10 kelas#1', '154.19.39.89', '2026-07-01 19:41:59'),
(502, 1, 'create', 'jadwal', 124, 'Tempatkan 10 kelas#1', '154.19.39.89', '2026-07-01 19:42:03'),
(503, 1, 'create', 'jadwal', 125, 'Tempatkan 4A kelas#1', '154.19.39.89', '2026-07-01 19:42:08'),
(504, 1, 'create', 'jadwal', 126, 'Tempatkan 4A kelas#1', '154.19.39.89', '2026-07-01 19:42:10'),
(505, 1, 'create', 'jadwal', 127, 'Tempatkan 4A kelas#1', '154.19.39.89', '2026-07-01 19:42:13'),
(506, 1, 'create', 'jadwal', 128, 'Tempatkan 1 kelas#1', '154.19.39.89', '2026-07-01 19:42:16'),
(507, 1, 'create', 'jadwal', 129, 'Tempatkan 1 kelas#1', '154.19.39.89', '2026-07-01 19:42:18'),
(508, 1, 'create', 'jadwal', 130, 'Tempatkan 1 kelas#1', '154.19.39.89', '2026-07-01 19:42:21'),
(509, 1, 'update', 'guru_mapel', 15, 'Atur kompetensi: 2 guru', '154.19.39.89', '2026-07-01 19:44:56');

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru`
--

CREATE TABLE `guru` (
  `id` int(11) UNSIGNED NOT NULL,
  `nip` varchar(60) DEFAULT NULL,
  `kode_guru` varchar(20) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `status_guru` enum('PNS','PPPK','GTY','GTT') DEFAULT NULL,
  `max_beban` smallint(5) UNSIGNED NOT NULL DEFAULT 24,
  `keterangan` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `guru`
--

INSERT INTO `guru` (`id`, `nip`, `kode_guru`, `nama`, `jenis_kelamin`, `status_guru`, `max_beban`, `keterangan`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, '-', '3', 'Muslimin, S.Kom', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-18 20:11:36', '2026-06-21 09:22:07'),
(2, '-', '1', 'Napis Kuturupi, S.T', 'L', 'GTY', 40, 'Kepala Sekolah', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(3, '-', '2', 'Maya Fadhillah, S.Pd', 'P', 'GTY', 40, 'Bendahara Sekolah', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(4, '-', '4', 'Beni Akbar, S.Pd', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(5, '-', '5', 'Puguh Wira Sakti, S.Pd', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(6, '-', '6', 'Ari Umar Sahid, S.Pd', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(7, '-', '7', 'Ferina Salsabilla Ayu, S.Kom', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(8, '-', '8', 'Maulida Qodrun Nada, S.M', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(9, '-', '9', 'Elvira Safitri, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(10, '-', '10', 'Atpal Parahi, S.Pd', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(11, '-', '11', 'Ghina Aulia, S.M', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(12, '-', '12', 'Agung Prayoga, S.Pd', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(13, '-', '13', 'Abil Septian', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(14, '-', '14', 'Nurfajri Ridwan, S.Pd', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(15, '-', '15', 'Muhsin, S.Ag', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(16, '-', '16', 'M.Syakur Kurniawan, S.Pd', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(17, '-', '17', 'Andri Maulana, S.Pd', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(18, '-', '18', 'Sigit Imam Pambudi, S.Pd', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(19, '-', '19', 'Fairuz, S.Pd', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(20, '-', '20', 'Abid Muchlisien, S.Kom', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(21, '-', '21', 'Imran Rahman Surbakti, S.Kom', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(22, '-', '22', 'Ihsan Fahmi, S.Sos', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(23, '-', '23', 'Khoirul Umam', 'L', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(24, '-', '24', 'Nurhilaliyah, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(25, '-', '25', 'Rr. Findarina MMK, S.E., M.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(26, '-', '26', 'Elsifa Busra, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(27, '-', '27', 'Septimawati, M.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(28, '-', '28', 'Hj. Intan Suryanim, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(29, '-', '29', 'Muji Rahayu, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(30, '-', '30', 'Ambarsari Dwi Sulistya Wati, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(31, '-', '31', 'Nihayatul Kamilah, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(32, '-', '32', 'Nasrotun Kamilah, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(33, '-', '33', 'Maria Ulfah, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(34, '-', '34', 'Wardani Widya Gayatri, S.S', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(35, '-', '35', 'Widya Husniati, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(36, '-', '36', 'Indri Rachmawati Chasanah, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(37, '-', '37', 'Yeni Maryani, S.Kom', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(38, '-', '38', 'Siti Hajar, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(39, '-', '39', 'Muthia Fitri Maulida, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(40, '-', '40', 'Fahrani Juhana, S.M', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(41, '-', '41', 'Khopipah Inayahtul Lail, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(42, '-', '42', 'Iin Mutmainah, S.Kom', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(43, '-', '43', 'Razqiyatul Awwal Mubdiyah, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(44, '-', '44', 'Desi Ria Fitri Yani, A.Md. SI.Ak', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(45, '-', '45', 'Siti Nurjanah, S.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(46, '-', '46', 'Bella Aprillia, M.Pd', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(47, '-', '47', 'Farah Miftahul Jannah, S.Kom', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07'),
(48, '-', '48', 'Rizky Zalianty, S.M', 'P', 'GTY', 40, 'Guru Mata Pelajaran', NULL, '2026-06-21 09:22:07', '2026-06-21 09:22:07');

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru_mapel`
--

CREATE TABLE `guru_mapel` (
  `id` int(11) UNSIGNED NOT NULL,
  `guru_id` int(11) UNSIGNED NOT NULL,
  `mapel_id` int(11) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `guru_mapel`
--

INSERT INTO `guru_mapel` (`id`, `guru_id`, `mapel_id`, `created_at`) VALUES
(2, 4, 1, '2026-06-21 17:45:53'),
(3, 33, 1, '2026-06-21 17:45:53'),
(4, 15, 1, '2026-06-21 17:45:53'),
(5, 31, 1, '2026-06-21 17:45:53'),
(6, 7, 14, '2026-06-21 17:47:46'),
(7, 42, 14, '2026-06-21 17:47:46'),
(8, 1, 14, '2026-06-21 17:47:46'),
(9, 37, 14, '2026-06-21 17:47:46'),
(10, 26, 5, '2026-06-21 20:15:53'),
(14, 12, 13, '2026-06-21 20:17:47'),
(15, 17, 13, '2026-06-21 20:17:47'),
(16, 18, 13, '2026-06-21 20:17:47'),
(17, 35, 13, '2026-06-21 20:17:47'),
(18, 34, 10, '2026-06-24 16:27:52'),
(19, 8, 17, '2026-06-24 16:28:13'),
(20, 8, 27, '2026-06-24 16:28:26'),
(21, 8, 28, '2026-06-24 16:28:36'),
(22, 8, 29, '2026-06-24 16:28:47'),
(23, 37, 22, '2026-06-24 16:28:59'),
(24, 20, 24, '2026-06-24 16:29:11'),
(25, 1, 24, '2026-06-24 16:29:11'),
(26, 30, 18, '2026-06-24 16:29:58'),
(27, 25, 18, '2026-06-24 16:29:58'),
(28, 20, 16, '2026-06-24 16:30:46'),
(29, 19, 16, '2026-06-24 16:30:46'),
(30, 47, 16, '2026-06-24 16:30:46'),
(31, 21, 16, '2026-06-24 16:30:46'),
(32, 1, 16, '2026-06-24 16:30:46'),
(33, 37, 16, '2026-06-24 16:30:46'),
(34, 26, 4, '2026-07-01 19:31:40'),
(35, 33, 4, '2026-07-01 19:31:40'),
(36, 24, 4, '2026-07-01 19:31:40'),
(37, 9, 15, '2026-07-01 19:44:56'),
(38, 27, 15, '2026-07-01 19:44:56');

-- --------------------------------------------------------

--
-- Struktur dari tabel `hari`
--

CREATE TABLE `hari` (
  `id` int(11) UNSIGNED NOT NULL,
  `nama` varchar(15) NOT NULL,
  `urutan` tinyint(3) UNSIGNED NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `hari`
--

INSERT INTO `hari` (`id`, `nama`, `urutan`, `aktif`) VALUES
(1, 'SENIN', 1, 1),
(3, 'SELASA', 2, 1),
(4, 'RABU', 3, 1),
(5, 'KAMIS', 4, 1),
(6, 'JUM\'AT', 5, 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal`
--

CREATE TABLE `jadwal` (
  `id` int(11) UNSIGNED NOT NULL,
  `tahun_ajaran_id` int(11) UNSIGNED DEFAULT NULL,
  `kelas_id` int(11) UNSIGNED NOT NULL,
  `hari_id` int(11) UNSIGNED NOT NULL,
  `jam_id` int(11) UNSIGNED NOT NULL,
  `pengampu_id` int(11) UNSIGNED NOT NULL,
  `guru_id` int(11) UNSIGNED NOT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jadwal`
--

INSERT INTO `jadwal` (`id`, `tahun_ajaran_id`, `kelas_id`, `hari_id`, `jam_id`, `pengampu_id`, `guru_id`, `created_by`, `created_at`, `updated_at`) VALUES
(7, NULL, 104, 1, 14, 12, 34, 1, '2026-06-22 22:19:59', '2026-06-22 22:19:59'),
(8, NULL, 104, 3, 14, 12, 34, 1, '2026-06-22 22:19:59', '2026-06-22 22:19:59'),
(9, NULL, 113, 1, 15, 16, 34, 1, '2026-06-22 22:25:59', '2026-06-22 22:25:59'),
(78, NULL, 1, 1, 2, 28, 27, 1, '2026-07-01 19:36:11', '2026-07-01 19:38:04'),
(79, NULL, 1, 1, 3, 28, 27, 1, '2026-07-01 19:36:11', '2026-07-01 19:38:10'),
(93, NULL, 1, 1, 1, 28, 27, 1, '2026-07-01 19:37:49', '2026-07-01 19:37:55'),
(94, NULL, 1, 1, 4, 28, 27, 1, '2026-07-01 19:39:12', '2026-07-01 19:39:15'),
(95, NULL, 1, 1, 7, 29, 48, 1, '2026-07-01 19:39:34', '2026-07-01 19:39:38'),
(96, NULL, 1, 1, 8, 29, 48, 1, '2026-07-01 19:39:42', '2026-07-01 19:39:45'),
(97, NULL, 1, 1, 9, 30, 43, 1, '2026-07-01 19:39:48', '2026-07-01 19:39:48'),
(98, NULL, 1, 1, 13, 30, 43, 1, '2026-07-01 19:39:50', '2026-07-01 19:39:50'),
(99, NULL, 1, 3, 1, 31, 12, 1, '2026-07-01 19:40:09', '2026-07-01 19:40:09'),
(100, NULL, 1, 3, 2, 31, 12, 1, '2026-07-01 19:40:11', '2026-07-01 19:40:11'),
(101, NULL, 1, 3, 3, 31, 12, 1, '2026-07-01 19:40:14', '2026-07-01 19:40:14'),
(102, NULL, 1, 3, 4, 31, 12, 1, '2026-07-01 19:40:16', '2026-07-01 19:40:16'),
(103, NULL, 1, 3, 7, 32, 46, 1, '2026-07-01 19:40:20', '2026-07-01 19:40:20'),
(104, NULL, 1, 3, 8, 32, 46, 1, '2026-07-01 19:40:22', '2026-07-01 19:40:22'),
(105, NULL, 1, 3, 9, 32, 46, 1, '2026-07-01 19:40:23', '2026-07-01 19:40:23'),
(106, NULL, 1, 3, 13, 32, 46, 1, '2026-07-01 19:40:26', '2026-07-01 19:40:26'),
(107, NULL, 1, 4, 1, 33, 19, 1, '2026-07-01 19:40:42', '2026-07-01 19:40:42'),
(108, NULL, 1, 4, 2, 33, 19, 1, '2026-07-01 19:40:43', '2026-07-01 19:40:43'),
(109, NULL, 1, 4, 3, 33, 19, 1, '2026-07-01 19:40:45', '2026-07-01 19:40:45'),
(110, NULL, 1, 4, 4, 33, 19, 1, '2026-07-01 19:40:47', '2026-07-01 19:40:47'),
(111, NULL, 1, 4, 7, 33, 19, 1, '2026-07-01 19:40:50', '2026-07-01 19:40:50'),
(112, NULL, 1, 4, 8, 33, 19, 1, '2026-07-01 19:40:52', '2026-07-01 19:40:52'),
(113, NULL, 1, 4, 9, 33, 19, 1, '2026-07-01 19:40:54', '2026-07-01 19:40:54'),
(114, NULL, 1, 4, 13, 33, 19, 1, '2026-07-01 19:40:56', '2026-07-01 19:40:56'),
(115, NULL, 1, 5, 1, 34, 26, 1, '2026-07-01 19:41:09', '2026-07-01 19:41:09'),
(116, NULL, 1, 5, 2, 34, 26, 1, '2026-07-01 19:41:11', '2026-07-01 19:41:11'),
(117, NULL, 1, 5, 3, 34, 26, 1, '2026-07-01 19:41:12', '2026-07-01 19:41:12'),
(118, NULL, 1, 5, 4, 34, 26, 1, '2026-07-01 19:41:14', '2026-07-01 19:41:14'),
(119, NULL, 1, 5, 8, 35, 14, 1, '2026-07-01 19:41:19', '2026-07-01 19:41:38'),
(120, NULL, 1, 5, 7, 35, 14, 1, '2026-07-01 19:41:21', '2026-07-01 19:41:37'),
(121, NULL, 1, 5, 9, 36, 7, 1, '2026-07-01 19:41:40', '2026-07-01 19:41:40'),
(122, NULL, 1, 5, 13, 36, 7, 1, '2026-07-01 19:41:42', '2026-07-01 19:41:42'),
(123, NULL, 1, 6, 9, 36, 7, 1, '2026-07-01 19:41:59', '2026-07-01 19:41:59'),
(124, NULL, 1, 6, 13, 36, 7, 1, '2026-07-01 19:42:03', '2026-07-01 19:42:03'),
(125, NULL, 1, 6, 8, 38, 5, 1, '2026-07-01 19:42:08', '2026-07-01 19:42:08'),
(126, NULL, 1, 6, 7, 38, 5, 1, '2026-07-01 19:42:10', '2026-07-01 19:42:10'),
(127, NULL, 1, 6, 4, 38, 5, 1, '2026-07-01 19:42:13', '2026-07-01 19:42:13'),
(128, NULL, 1, 6, 3, 37, 4, 1, '2026-07-01 19:42:16', '2026-07-01 19:42:16'),
(129, NULL, 1, 6, 2, 37, 4, 1, '2026-07-01 19:42:18', '2026-07-01 19:42:18'),
(130, NULL, 1, 6, 1, 37, 4, 1, '2026-07-01 19:42:21', '2026-07-01 19:42:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jam_pelajaran`
--

CREATE TABLE `jam_pelajaran` (
  `id` int(11) UNSIGNED NOT NULL,
  `shift` enum('pagi','siang') NOT NULL,
  `jam_ke` tinyint(3) UNSIGNED NOT NULL,
  `waktu_mulai` time NOT NULL,
  `waktu_selesai` time NOT NULL,
  `durasi` smallint(5) UNSIGNED NOT NULL DEFAULT 35,
  `is_istirahat` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jam_pelajaran`
--

INSERT INTO `jam_pelajaran` (`id`, `shift`, `jam_ke`, `waktu_mulai`, `waktu_selesai`, `durasi`, `is_istirahat`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 'pagi', 1, '07:00:00', '07:35:00', 35, 0, NULL, '2026-06-18 20:26:54', '2026-06-18 23:18:59'),
(2, 'pagi', 2, '07:35:00', '08:10:00', 35, 0, NULL, '2026-06-21 10:03:05', '2026-06-21 10:04:21'),
(3, 'pagi', 3, '08:10:00', '08:45:00', 35, 0, NULL, '2026-06-21 10:05:00', '2026-06-21 10:05:00'),
(4, 'pagi', 4, '08:45:00', '09:20:00', 35, 0, NULL, '2026-06-21 10:05:43', '2026-06-21 10:05:43'),
(5, 'pagi', 11, '09:20:00', '09:40:00', 20, 1, NULL, '2026-06-21 10:06:32', '2026-06-21 10:13:56'),
(7, 'pagi', 5, '09:40:00', '10:15:00', 35, 0, NULL, '2026-06-21 10:08:13', '2026-06-21 10:14:05'),
(8, 'pagi', 6, '10:15:00', '10:50:00', 35, 0, NULL, '2026-06-21 10:10:55', '2026-06-21 10:14:14'),
(9, 'pagi', 7, '10:50:00', '11:25:00', 35, 0, NULL, '2026-06-21 10:11:30', '2026-06-21 10:14:21'),
(13, 'pagi', 8, '11:25:00', '12:00:00', 35, 0, NULL, '2026-06-21 10:15:19', '2026-06-21 10:15:19'),
(14, 'siang', 1, '13:00:00', '13:30:00', 30, 0, NULL, '2026-06-21 17:34:19', '2026-06-21 17:34:19'),
(15, 'siang', 2, '13:30:00', '14:00:00', 30, 0, NULL, '2026-06-21 17:35:02', '2026-06-21 17:35:02'),
(16, 'siang', 3, '14:00:00', '14:30:00', 30, 0, NULL, '2026-06-21 17:36:17', '2026-06-21 17:36:17'),
(17, 'siang', 4, '14:30:00', '15:00:00', 30, 0, NULL, '2026-06-21 17:36:53', '2026-06-21 17:36:53'),
(18, 'siang', 9, '15:00:00', '15:20:00', 20, 1, NULL, '2026-06-21 17:37:35', '2026-06-22 23:39:51'),
(19, 'siang', 5, '15:20:00', '15:45:00', 25, 0, NULL, '2026-06-21 17:38:22', '2026-06-21 17:38:22'),
(20, 'siang', 6, '15:45:00', '16:10:00', 25, 0, NULL, '2026-06-21 17:39:05', '2026-06-21 17:39:05'),
(21, 'siang', 7, '16:10:00', '16:35:00', 25, 0, NULL, '2026-06-21 17:39:38', '2026-06-21 17:39:38'),
(22, 'siang', 8, '16:35:00', '17:00:00', 25, 0, NULL, '2026-06-21 17:40:19', '2026-06-21 17:40:19');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jurusan`
--

CREATE TABLE `jurusan` (
  `id` int(11) UNSIGNED NOT NULL,
  `kode` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jurusan`
--

INSERT INTO `jurusan` (`id`, `kode`, `nama`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, '1', 'TKJ', '2026-06-24 16:27:08', '2026-06-18 20:20:18', '2026-06-24 16:27:08'),
(2, '2', 'MPLB', '2026-06-24 16:27:12', '2026-06-18 20:20:38', '2026-06-24 16:27:12'),
(3, '3', 'AKL', '2026-06-24 16:27:16', '2026-06-18 20:21:03', '2026-06-24 16:27:16'),
(4, 'TKJT', 'TKJT', NULL, '2026-06-22 23:18:28', '2026-06-22 23:18:28'),
(5, 'TJKT', 'TJKT', NULL, '2026-06-22 23:19:38', '2026-06-22 23:19:38'),
(6, 'MPLB', 'MPLB', NULL, '2026-06-22 23:19:38', '2026-06-22 23:19:38'),
(7, 'AKL', 'AKL', NULL, '2026-06-22 23:19:38', '2026-06-22 23:19:38');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kelas`
--

CREATE TABLE `kelas` (
  `id` int(11) UNSIGNED NOT NULL,
  `nama_kelas` varchar(50) NOT NULL,
  `tingkat` enum('X','XI','XII') NOT NULL,
  `jurusan_id` int(11) UNSIGNED DEFAULT NULL,
  `wali_kelas_id` int(11) UNSIGNED DEFAULT NULL,
  `shift` enum('pagi','siang') NOT NULL DEFAULT 'pagi',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kelas`
--

INSERT INTO `kelas` (`id`, `nama_kelas`, `tingkat`, `jurusan_id`, `wali_kelas_id`, `shift`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 'X TKJ 1', 'X', 5, 1, 'pagi', NULL, '2026-06-18 20:23:28', '2026-06-22 23:19:38'),
(2, '1', 'X', NULL, NULL, 'pagi', '2026-06-21 07:10:55', '2026-06-21 06:59:59', '2026-06-21 07:10:55'),
(3, '2', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:16', '2026-06-21 06:59:59', '2026-06-21 07:11:16'),
(4, '3', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:41', '2026-06-21 06:59:59', '2026-06-21 07:11:41'),
(5, '4', 'X', NULL, NULL, 'pagi', '2026-06-21 07:12:01', '2026-06-21 06:59:59', '2026-06-21 07:12:01'),
(6, '5', 'X', NULL, NULL, 'pagi', '2026-06-21 07:12:06', '2026-06-21 06:59:59', '2026-06-21 07:12:06'),
(7, '6', 'X', NULL, NULL, 'pagi', '2026-06-21 07:12:08', '2026-06-21 06:59:59', '2026-06-21 07:12:08'),
(8, '7', 'X', NULL, NULL, 'pagi', '2026-06-21 07:12:10', '2026-06-21 06:59:59', '2026-06-21 07:12:10'),
(9, '8', 'X', NULL, NULL, 'pagi', '2026-06-21 07:12:11', '2026-06-21 06:59:59', '2026-06-21 07:12:11'),
(10, '9', 'X', NULL, NULL, 'pagi', '2026-06-21 07:12:13', '2026-06-21 06:59:59', '2026-06-21 07:12:13'),
(11, '10', 'X', NULL, NULL, 'pagi', '2026-06-21 07:10:58', '2026-06-21 06:59:59', '2026-06-21 07:10:58'),
(12, '11', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:00', '2026-06-21 06:59:59', '2026-06-21 07:11:00'),
(13, '12', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:02', '2026-06-21 06:59:59', '2026-06-21 07:11:02'),
(14, '13', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:03', '2026-06-21 06:59:59', '2026-06-21 07:11:03'),
(15, '14', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:05', '2026-06-21 06:59:59', '2026-06-21 07:11:05'),
(16, '15', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:07', '2026-06-21 06:59:59', '2026-06-21 07:11:07'),
(17, '16', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:08', '2026-06-21 06:59:59', '2026-06-21 07:11:08'),
(18, '17', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:11', '2026-06-21 06:59:59', '2026-06-21 07:11:11'),
(19, '18', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:12', '2026-06-21 06:59:59', '2026-06-21 07:11:12'),
(20, '19', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:14', '2026-06-21 06:59:59', '2026-06-21 07:11:14'),
(21, '20', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:18', '2026-06-21 06:59:59', '2026-06-21 07:11:18'),
(22, '21', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:19', '2026-06-21 06:59:59', '2026-06-21 07:11:19'),
(23, '22', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:21', '2026-06-21 06:59:59', '2026-06-21 07:11:21'),
(24, '23', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:24', '2026-06-21 06:59:59', '2026-06-21 07:11:24'),
(25, '24', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:26', '2026-06-21 06:59:59', '2026-06-21 07:11:26'),
(26, '25', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:28', '2026-06-21 06:59:59', '2026-06-21 07:11:28'),
(27, '26', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:29', '2026-06-21 06:59:59', '2026-06-21 07:11:29'),
(28, '27', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:36', '2026-06-21 06:59:59', '2026-06-21 07:11:36'),
(29, '28', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:38', '2026-06-21 06:59:59', '2026-06-21 07:11:38'),
(30, '29', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:40', '2026-06-21 06:59:59', '2026-06-21 07:11:40'),
(31, '30', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:43', '2026-06-21 06:59:59', '2026-06-21 07:11:43'),
(32, '31', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:45', '2026-06-21 06:59:59', '2026-06-21 07:11:45'),
(33, '32', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:46', '2026-06-21 06:59:59', '2026-06-21 07:11:46'),
(34, '33', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:48', '2026-06-21 06:59:59', '2026-06-21 07:11:48'),
(35, '34', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:50', '2026-06-21 06:59:59', '2026-06-21 07:11:50'),
(36, '35', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:52', '2026-06-21 06:59:59', '2026-06-21 07:11:52'),
(37, '36', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:54', '2026-06-21 06:59:59', '2026-06-21 07:11:54'),
(38, '37', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:56', '2026-06-21 06:59:59', '2026-06-21 07:11:56'),
(39, '38', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:57', '2026-06-21 06:59:59', '2026-06-21 07:11:57'),
(40, '39', 'X', NULL, NULL, 'pagi', '2026-06-21 07:11:59', '2026-06-21 06:59:59', '2026-06-21 07:11:59'),
(41, '40', 'X', NULL, NULL, 'pagi', '2026-06-21 07:12:03', '2026-06-21 06:59:59', '2026-06-21 07:12:03'),
(42, '41', 'X', NULL, NULL, 'pagi', '2026-06-21 07:12:05', '2026-06-21 06:59:59', '2026-06-21 07:12:05'),
(43, 'X TKJ 2', 'X', 5, 4, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(44, 'X TKJ 3', 'X', 5, 5, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(45, 'X TKJ 4', 'X', 5, 6, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(46, 'X TKJ 5', 'X', 5, 7, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(47, 'X TKJ 6', 'X', 5, 8, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(48, 'X TKJ 7', 'X', 5, 9, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(49, 'X TKJ 8', 'X', 5, 10, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(50, 'X TKJ 9', 'X', 5, 11, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(51, 'X MPLB 1', 'X', 6, 12, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(52, 'X MPLB 2', 'X', 6, 14, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(53, 'X MPLB 3', 'X', 6, 15, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(54, 'X MPLB 4', 'X', 6, 17, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(55, 'X MPLB 5', 'X', 6, 18, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(56, 'X AKL', 'X', 7, 19, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(57, 'XI TKJ 1', 'XI', 5, 20, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(58, 'XI TKJ 2', 'XI', 5, 21, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(59, 'XI TKJ 3', 'XI', 5, 22, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(60, 'XI TKJ 4', 'XI', 5, 24, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(61, 'XI TKJ 5', 'XI', 5, 25, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(62, 'XI TKJ 6', 'XI', 5, 26, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(63, 'XI TKJ 7', 'XI', 5, 27, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(64, 'XI TKJ 8', 'XI', 5, 28, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(65, 'XI TKJ 9', 'XI', 5, 29, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(66, 'XI MPLB 1', 'XI', 6, 30, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(67, 'XI MPLB 2', 'XI', 6, 31, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(68, 'XI MPLB 3', 'XI', 6, 32, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(69, 'XI MPLB 4', 'XI', 6, 33, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(70, 'XI MPLB 5', 'XI', 6, 34, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(71, 'XI AKL', 'XI', 7, 35, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(72, 'XII TKJ 1', 'XII', 5, 36, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(73, 'XII TKJ 2', 'XII', 5, 37, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(74, 'XII TKJ 3', 'XII', 5, 38, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(75, 'XII TKJ 4', 'XII', 5, 39, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(76, 'XII TKJ 5', 'XII', 5, 40, 'siang', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(77, 'XII TKJ 6', 'XII', 5, 41, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(78, 'XII TKJ 7', 'XII', 5, 42, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(79, 'XII MPLB 1', 'XII', 6, 43, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(80, 'XII MPLB 2', 'XII', 6, 44, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(81, 'XII MPLB 3', 'XII', 6, 45, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(82, 'XII AKL', 'XII', 7, 46, 'pagi', NULL, '2026-06-21 07:13:41', '2026-06-22 23:19:38'),
(83, 'X TKJT 1', 'X', 4, 27, 'pagi', '2026-06-22 23:19:14', '2026-06-21 07:25:28', '2026-06-22 23:19:14'),
(84, '10 TKJ 1', 'X', 1, 1, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:29:39', '2026-06-22 22:44:39'),
(85, '10 TKJ 2', 'X', 1, 4, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:30:27', '2026-06-22 22:44:39'),
(86, '10 TKJ 3', 'X', 1, 5, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:31:47', '2026-06-22 22:44:39'),
(87, '10 TKJ 4', 'X', 1, 6, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:35:22', '2026-06-22 22:44:39'),
(88, '10 TKJ 5', 'X', 1, 7, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:35:39', '2026-06-22 22:44:39'),
(89, '10 TKJ 6', 'X', 1, 8, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:37:33', '2026-06-22 22:44:39'),
(90, '10 TKJ 7', 'X', 1, 10, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:37:54', '2026-06-22 22:44:39'),
(91, '10 TKJ 8', 'X', 1, 12, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:38:54', '2026-06-22 22:44:39'),
(92, '10 TKJ 9', 'X', 1, 14, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:39:41', '2026-06-22 22:44:39'),
(93, '10 MPLB 1', 'X', 2, 15, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:40:28', '2026-06-22 22:44:39'),
(94, '10 MPLB 2', 'X', 2, 17, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:41:31', '2026-06-22 22:44:39'),
(95, '10 MPLB 3', 'X', 2, 18, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:41:50', '2026-06-22 22:44:39'),
(96, '10 MPLB 4', 'X', 2, 19, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:42:40', '2026-06-22 22:44:39'),
(97, '10 MPLB 5', 'X', 2, 20, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:43:02', '2026-06-22 22:44:39'),
(98, '10 AKL', 'X', 3, 21, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:43:44', '2026-06-22 22:44:39'),
(99, '11 TKJ 1', 'XI', 1, 22, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:44:46', '2026-06-22 22:44:39'),
(100, '11 TKJ 2', 'XI', 1, 24, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:45:58', '2026-06-22 22:44:39'),
(101, '11 TKJ 3', 'XI', 1, 25, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:46:58', '2026-06-22 22:44:39'),
(102, '11 TKJ 4', 'XI', 1, 26, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:47:48', '2026-06-22 22:44:39'),
(103, '11 TKJ 5', 'XI', 1, 27, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:48:08', '2026-06-22 22:44:39'),
(104, '11 TKJ 6', 'XI', 1, 28, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:49:47', '2026-06-22 22:44:39'),
(105, '11 TKJ 7', 'XI', 1, 29, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:50:14', '2026-06-22 22:44:39'),
(106, '11 TKJ 9', 'XI', 1, 30, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:50:59', '2026-06-22 22:44:39'),
(107, '11 TKJ 8', 'XI', 1, 31, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:51:39', '2026-06-22 22:44:39'),
(108, '11 MPLB 1', 'XI', 2, 32, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:52:11', '2026-06-22 22:44:39'),
(109, '11 MPLB 2', 'XI', 2, 33, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:52:32', '2026-06-22 22:44:39'),
(110, '11 MPLB 3', 'XI', 2, 34, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:53:00', '2026-06-22 22:44:39'),
(111, '11 MPLB 4', 'XI', 2, 35, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:53:18', '2026-06-22 22:44:39'),
(112, '11 MPLB 5', 'XI', 2, 36, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:53:47', '2026-06-22 22:44:39'),
(113, '11 AKL', 'XI', 3, 37, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:54:31', '2026-06-22 22:44:39'),
(114, '12 TKJ 1', 'XII', 1, 38, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:55:06', '2026-06-22 22:44:39'),
(115, '12 TKJ 2', 'XII', 1, 39, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:55:24', '2026-06-22 22:44:39'),
(116, '12 TKJ 3', 'XII', 1, 40, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:56:00', '2026-06-22 22:44:39'),
(117, '12 TKJ 4', 'XII', 1, 41, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:56:20', '2026-06-22 22:44:39'),
(118, '12 TKJ 5', 'XII', 1, 43, 'siang', '2026-06-22 22:44:39', '2026-06-21 09:56:53', '2026-06-22 22:44:39'),
(119, '12 TKJ 6', 'XII', 1, 42, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:57:28', '2026-06-22 22:44:39'),
(120, '12 TKJ 7', 'XII', 1, 44, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:58:09', '2026-06-22 22:44:39'),
(121, '12 MPLB 1', 'XII', 2, 44, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:58:36', '2026-06-22 22:44:39'),
(122, '12 MPLB 2', 'XII', 2, 45, 'pagi', '2026-06-22 22:44:39', '2026-06-21 09:59:14', '2026-06-22 22:44:39'),
(123, '12 MPLB 3', 'XII', 2, 46, 'pagi', '2026-06-22 22:44:39', '2026-06-21 10:00:21', '2026-06-22 22:44:39'),
(124, '12 AKL', 'XII', 3, 48, 'pagi', '2026-06-22 22:44:39', '2026-06-21 10:00:52', '2026-06-22 22:44:39');

-- --------------------------------------------------------

--
-- Struktur dari tabel `ketersediaan_guru`
--

CREATE TABLE `ketersediaan_guru` (
  `id` int(11) UNSIGNED NOT NULL,
  `guru_id` int(11) UNSIGNED NOT NULL,
  `hari_id` int(11) UNSIGNED NOT NULL,
  `jam_id` int(11) UNSIGNED NOT NULL,
  `status` enum('tersedia','tidak') NOT NULL DEFAULT 'tersedia',
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `ketersediaan_guru`
--

INSERT INTO `ketersediaan_guru` (`id`, `guru_id`, `hari_id`, `jam_id`, `status`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 20, 6, 14, 'tidak', NULL, '2026-06-21 20:10:52', '2026-06-21 20:10:52'),
(2, 20, 6, 15, 'tidak', NULL, '2026-06-21 20:10:52', '2026-06-21 20:10:52'),
(3, 20, 6, 16, 'tidak', NULL, '2026-06-21 20:10:52', '2026-06-21 20:10:52'),
(4, 20, 6, 17, 'tidak', NULL, '2026-06-21 20:10:52', '2026-06-21 20:10:52'),
(5, 20, 6, 19, 'tidak', NULL, '2026-06-21 20:10:52', '2026-06-21 20:10:52'),
(6, 20, 6, 20, 'tidak', NULL, '2026-06-21 20:10:52', '2026-06-21 20:10:52'),
(7, 20, 6, 21, 'tidak', NULL, '2026-06-21 20:10:52', '2026-06-21 20:10:52'),
(8, 20, 6, 22, 'tidak', NULL, '2026-06-21 20:10:52', '2026-06-21 20:10:52'),
(9, 20, 6, 18, 'tidak', NULL, '2026-06-21 20:10:52', '2026-06-21 20:10:52'),
(18, 12, 5, 1, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(19, 12, 5, 2, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(20, 12, 5, 3, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(21, 12, 5, 4, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(22, 12, 5, 7, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(23, 12, 5, 8, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(24, 12, 5, 9, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(25, 12, 5, 13, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(26, 12, 6, 1, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(27, 12, 6, 2, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(28, 12, 6, 3, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(29, 12, 6, 4, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(30, 12, 6, 7, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(31, 12, 6, 8, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(32, 12, 6, 9, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(33, 12, 6, 13, 'tidak', NULL, '2026-06-22 13:41:48', '2026-06-22 13:41:48'),
(34, 12, 5, 14, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(35, 12, 5, 15, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(36, 12, 5, 16, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(37, 12, 6, 14, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(38, 12, 6, 15, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(39, 12, 6, 16, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(40, 12, 5, 17, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(41, 12, 6, 17, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(42, 12, 5, 19, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(43, 12, 6, 19, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(44, 12, 6, 20, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(45, 12, 5, 20, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(46, 12, 6, 21, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(47, 12, 5, 21, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(48, 12, 5, 22, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(49, 12, 6, 22, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(50, 12, 5, 18, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(51, 12, 6, 18, 'tidak', NULL, '2026-06-22 13:42:07', '2026-06-22 13:42:07'),
(52, 19, 3, 1, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(53, 19, 3, 2, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(54, 19, 3, 3, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(55, 19, 3, 4, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(56, 19, 3, 7, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(57, 19, 3, 8, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(58, 19, 3, 9, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(59, 19, 3, 13, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(60, 19, 5, 1, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(61, 19, 5, 2, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(62, 19, 5, 3, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(63, 19, 5, 4, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(64, 19, 5, 7, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(65, 19, 5, 8, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(66, 19, 5, 9, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(67, 19, 5, 13, 'tidak', NULL, '2026-06-22 13:42:57', '2026-06-22 13:42:57'),
(68, 19, 3, 14, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(69, 19, 3, 15, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(70, 19, 3, 16, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(71, 19, 3, 17, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(72, 19, 3, 19, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(73, 19, 3, 20, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(74, 19, 3, 21, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(75, 19, 3, 22, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(76, 19, 3, 18, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(77, 19, 5, 14, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(78, 19, 5, 15, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(79, 19, 5, 16, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(80, 19, 5, 17, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(81, 19, 5, 19, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(82, 19, 5, 20, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(83, 19, 5, 21, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(84, 19, 5, 22, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16'),
(85, 19, 5, 18, 'tidak', NULL, '2026-06-22 13:43:16', '2026-06-22 13:43:16');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mata_pelajaran`
--

CREATE TABLE `mata_pelajaran` (
  `id` int(11) UNSIGNED NOT NULL,
  `kode_mapel` varchar(20) NOT NULL,
  `nama_mapel` varchar(150) NOT NULL,
  `kelompok` varchar(50) DEFAULT NULL,
  `jp_default` smallint(5) UNSIGNED NOT NULL DEFAULT 2,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `mata_pelajaran`
--

INSERT INTO `mata_pelajaran` (`id`, `kode_mapel`, `nama_mapel`, `kelompok`, `jp_default`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, '1 PAI', 'Pendidikan Agama Islam & Budi Pekerti', 'Umum', 3, NULL, '2026-06-18 20:18:16', '2026-06-18 20:18:16'),
(2, '1', 'Pendidikan Agama Islam dan Budi Pekerti', 'Umum', 3, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(3, '2', 'Pendidikan Pancasila', 'Umum', 2, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(4, '3A', 'Bahasa Indonesia (10-11)', 'Umum', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(5, '3B', 'Bahasa Indonesia (12)', 'Umum', 3, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(6, '4A', 'Pendidikan Jasmani, Olahraga dan Kesehatan (10)', 'Umum', 3, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(7, '4B', 'Pendidikan Jasmani, Olahraga dan Kesehatan (11)', 'Umum', 2, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(8, '5', 'Sejarah', 'Umum', 2, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(9, '6', 'Seni Musik', 'Umum', 2, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(10, '7', 'Bahasa Jepang', 'Umum', 2, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(11, '8A', 'Matematika (10 & 12)', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(12, '8B', 'Matematika (11)', 'Kejuruan', 3, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(13, '9', 'Bahasa Inggris', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(14, '10', 'Informatika', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(15, '11', 'Projek Ilmu Pengetahuan Alam dan Sosial', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(16, '12A', 'Dasar-dasar Teknik Jaringan Komputer dan Telekomunikasi', 'Kejuruan', 8, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(17, '12B', 'Dasar-dasar Akuntansi', 'Kejuruan', 8, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(18, '12C', 'Dasar-dasar Manajemen Perkantoran', 'Kejuruan', 8, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(19, '13A', 'Perencanaan dan \nPengalamatan \nJaringan', 'Kejuruan', 2, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(20, '13B', 'Teknologi Jaringan \nKabel dan Nirkabel', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(21, '13C1', 'Pemasangan dan Konfigurasi Perangkat Jaringan (11)', 'Kejuruan', 6, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(22, '13D1', 'Administrasi Sistem Jaringan (11)', 'Kejuruan', 2, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(23, '13C2', 'Pemasangan dan Konfigurasi Perangkat Jaringan (12)', 'Kejuruan', 6, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(24, '13D2', 'Administrasi Sistem Jaringan (12)', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(25, '14', 'Keamanan Jaringan', 'Kejuruan', 8, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(26, '15A', 'Ekonomi bisnis \ndan administrasi \numum', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(27, '15B', 'Akuntansi \nperusahaan jasa, \ndagang dan \nmanufaktur', 'Kejuruan', 6, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(28, '15C', 'Akuntansi \nlembaga/instansi \npemerintah', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(29, '15D', 'Akuntansi \nkeuangan', 'Kejuruan', 8, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(30, '15E', 'Komputer \nakuntansi', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(31, '15F', 'Perpajakan', 'Kejuruan', 6, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(32, '16A', 'Ekonomi dan Bisnis', 'Kejuruan', 2, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(33, '16B', 'Pengelolaan Administrasi Umum', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(34, '16C1', 'Komunikasi di Tempat Kerja (11)', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(35, '16C2', 'Komunikasi di Tempat Kerja (12)', 'Kejuruan', 2, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(36, '16D', 'Pengelolaan Kearsipan', 'Kejuruan', 2, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(37, '16E', 'Teknologi kantor', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(38, '16F', 'Pengelolaan Rapat/pertemuan', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(39, '16G', 'Pengelolaan Keuangan Sederhana', 'Kejuruan', 2, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(40, '16H', 'Pengelolaan Sumberdaya Manusia (SDM)', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(41, '16I', 'Pengelolaan Sarana dan Prasarana', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(42, '16J', 'Pengelolan Humas dan Keprotokolan', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(43, '17', 'Kecerdasan Koding Artificial', 'Kejuruan', 4, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(44, '18', 'KIK', 'Kejuruan', 5, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11'),
(45, '19', 'Praktik Kerja Lapangan', 'Kejuruan', 36, NULL, '2026-06-21 06:49:11', '2026-06-21 06:49:11');

-- --------------------------------------------------------

--
-- Struktur dari tabel `migrations`
--

CREATE TABLE `migrations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `migrations`
--

INSERT INTO `migrations` (`id`, `version`, `class`, `group`, `namespace`, `time`, `batch`) VALUES
(1, '2026-06-01-000001', 'App\\Database\\Migrations\\CreateCoreTables', 'default', 'App', 1780299547, 1),
(2, '2026-06-01-000002', 'App\\Database\\Migrations\\SeedInitialData', 'default', 'App', 1780299547, 1),
(3, '2026-06-01-000003', 'App\\Database\\Migrations\\AddEditTokenToSubmissions', 'default', 'App', 1780302490, 2),
(4, '2026-06-01-000004', 'App\\Database\\Migrations\\AddIndexesToSubmissions', 'default', 'App', 1780308613, 3),
(5, '2026-06-01-000005', 'App\\Database\\Migrations\\MakeNipNullable', 'default', 'App', 1780308613, 3),
(6, '2026-06-19-000001', 'App\\Database\\Migrations\\CreatePenjadwalanTables', 'default', 'App', 1781810968, 4),
(7, '2026-06-20-000001', 'App\\Database\\Migrations\\FrontendPublik', 'default', 'App', 1781848343, 5);

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengampu`
--

CREATE TABLE `pengampu` (
  `id` int(11) UNSIGNED NOT NULL,
  `kelas_id` int(11) UNSIGNED NOT NULL,
  `mapel_id` int(11) UNSIGNED NOT NULL,
  `guru_id` int(11) UNSIGNED NOT NULL,
  `jp` smallint(5) UNSIGNED NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengampu`
--

INSERT INTO `pengampu` (`id`, `kelas_id`, `mapel_id`, `guru_id`, `jp`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 8, '2026-06-20 21:14:22', '2026-06-18 20:23:50', '2026-06-20 21:14:22'),
(2, 108, 10, 34, 2, NULL, '2026-06-22 03:43:32', '2026-06-22 03:43:32'),
(3, 109, 10, 34, 2, NULL, '2026-06-22 03:43:59', '2026-06-22 03:43:59'),
(4, 110, 10, 34, 2, NULL, '2026-06-22 03:44:19', '2026-06-22 03:44:19'),
(5, 111, 10, 34, 2, NULL, '2026-06-22 03:44:36', '2026-06-22 03:44:36'),
(6, 112, 10, 34, 2, NULL, '2026-06-22 03:44:54', '2026-06-22 03:44:54'),
(7, 99, 10, 34, 2, NULL, '2026-06-22 03:45:12', '2026-06-22 03:45:12'),
(8, 100, 10, 34, 2, NULL, '2026-06-22 03:45:34', '2026-06-22 03:45:34'),
(9, 101, 10, 34, 2, NULL, '2026-06-22 03:45:50', '2026-06-22 03:45:50'),
(10, 102, 10, 34, 2, NULL, '2026-06-22 03:46:08', '2026-06-22 03:46:08'),
(11, 103, 10, 34, 2, NULL, '2026-06-22 03:46:24', '2026-06-22 03:46:24'),
(12, 104, 10, 34, 2, NULL, '2026-06-22 03:46:39', '2026-06-22 03:46:39'),
(13, 105, 10, 34, 2, NULL, '2026-06-22 03:46:58', '2026-06-22 03:46:58'),
(14, 107, 10, 34, 2, NULL, '2026-06-22 03:47:14', '2026-06-22 03:47:14'),
(15, 106, 10, 34, 2, NULL, '2026-06-22 03:47:30', '2026-06-22 03:47:30'),
(16, 113, 10, 34, 2, NULL, '2026-06-22 03:48:05', '2026-06-22 03:48:05'),
(17, 124, 10, 34, 2, NULL, '2026-06-22 03:48:23', '2026-06-22 03:48:23'),
(18, 121, 10, 34, 2, NULL, '2026-06-22 03:48:40', '2026-06-22 03:48:40'),
(19, 122, 10, 34, 2, NULL, '2026-06-22 03:48:56', '2026-06-22 03:48:56'),
(20, 123, 10, 34, 2, NULL, '2026-06-22 03:49:13', '2026-06-22 03:49:13'),
(21, 114, 10, 34, 2, NULL, '2026-06-22 03:49:31', '2026-06-22 03:49:31'),
(22, 115, 10, 34, 2, NULL, '2026-06-22 03:49:46', '2026-06-22 03:49:46'),
(23, 116, 10, 34, 2, NULL, '2026-06-22 03:50:01', '2026-06-22 03:50:01'),
(24, 117, 10, 34, 2, NULL, '2026-06-22 03:50:14', '2026-06-22 03:50:14'),
(25, 118, 10, 34, 2, NULL, '2026-06-22 03:50:30', '2026-06-22 03:50:30'),
(26, 119, 10, 34, 2, NULL, '2026-06-22 03:50:43', '2026-06-22 03:50:43'),
(27, 120, 10, 34, 2, NULL, '2026-06-22 03:50:56', '2026-06-22 03:50:56'),
(28, 1, 15, 27, 4, NULL, '2026-07-01 19:27:08', '2026-07-01 19:27:08'),
(29, 1, 9, 48, 2, NULL, '2026-07-01 19:27:55', '2026-07-01 19:27:55'),
(30, 1, 3, 43, 2, NULL, '2026-07-01 19:28:26', '2026-07-01 19:28:26'),
(31, 1, 13, 12, 4, NULL, '2026-07-01 19:28:49', '2026-07-01 19:28:49'),
(32, 1, 11, 46, 4, NULL, '2026-07-01 19:29:35', '2026-07-01 19:29:35'),
(33, 1, 16, 19, 8, NULL, '2026-07-01 19:30:10', '2026-07-01 19:30:10'),
(34, 1, 4, 26, 4, NULL, '2026-07-01 19:32:02', '2026-07-01 19:32:02'),
(35, 1, 8, 14, 2, NULL, '2026-07-01 19:32:49', '2026-07-01 19:32:49'),
(36, 1, 14, 7, 4, NULL, '2026-07-01 19:33:12', '2026-07-01 19:34:46'),
(37, 1, 2, 4, 3, NULL, '2026-07-01 19:33:47', '2026-07-01 19:33:47'),
(38, 1, 6, 5, 3, NULL, '2026-07-01 19:34:07', '2026-07-01 19:34:07');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengumuman`
--

CREATE TABLE `pengumuman` (
  `id` int(11) UNSIGNED NOT NULL,
  `judul` varchar(200) NOT NULL,
  `isi` text NOT NULL,
  `is_aktif` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `settings`
--

CREATE TABLE `settings` (
  `id` int(11) UNSIGNED NOT NULL,
  `school_name` varchar(200) NOT NULL DEFAULT 'NAMA SEKOLAH',
  `school_level` varchar(60) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `headmaster_name` varchar(150) DEFAULT NULL,
  `headmaster_nip` varchar(60) DEFAULT NULL,
  `city` varchar(100) NOT NULL DEFAULT 'Bekasi',
  `academic_year` varchar(20) NOT NULL DEFAULT '2026/2027',
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `website` varchar(150) DEFAULT NULL,
  `form_open` tinyint(1) NOT NULL DEFAULT 1,
  `jadwal_publik` tinyint(1) DEFAULT 1,
  `form_intro` text DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `settings`
--

INSERT INTO `settings` (`id`, `school_name`, `school_level`, `logo`, `headmaster_name`, `headmaster_nip`, `city`, `academic_year`, `address`, `phone`, `email`, `website`, `form_open`, `jadwal_publik`, `form_intro`, `updated_at`) VALUES
(1, 'SMK BINA NUSA', 'SMK', 'logo_1780456697.jpg', 'Napis Kuturupi, S.T', '-', 'Bekasi', '2026/2027', 'BEKASI', '', '', 'zulfiqri.site', 1, 0, 'Surat pernyataan kesediaan guru untuk melaksanakan tugas mengajar dan tugas tambahan sesuai penugasan sekolah.', '2026-06-19 15:16:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `submissions`
--

CREATE TABLE `submissions` (
  `id` int(11) UNSIGNED NOT NULL,
  `nama_lengkap` varchar(150) NOT NULL,
  `nip_nuptk` varchar(60) DEFAULT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `pendidikan_terakhir` varchar(100) DEFAULT NULL,
  `guru_mapel` varchar(150) DEFAULT NULL,
  `status_kepegawaian` varchar(10) DEFAULT NULL,
  `nomor_hp` varchar(30) DEFAULT NULL,
  `mapel_diampu` longtext DEFAULT NULL,
  `total_jam` int(11) NOT NULL DEFAULT 0,
  `tugas_tambahan` longtext DEFAULT NULL,
  `tugas_lainnya` varchar(255) DEFAULT NULL,
  `kesediaan_jam` longtext DEFAULT NULL,
  `preferensi` longtext DEFAULT NULL,
  `ketersediaan_hari` longtext DEFAULT NULL,
  `keterangan_tambahan` text DEFAULT NULL,
  `bersedia_mengajar` tinyint(1) NOT NULL DEFAULT 1,
  `komitmen_setuju` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'baru',
  `catatan_admin` text DEFAULT NULL,
  `edit_token` varchar(64) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `submissions`
--

INSERT INTO `submissions` (`id`, `nama_lengkap`, `nip_nuptk`, `tempat_lahir`, `tanggal_lahir`, `pendidikan_terakhir`, `guru_mapel`, `status_kepegawaian`, `nomor_hp`, `mapel_diampu`, `total_jam`, `tugas_tambahan`, `tugas_lainnya`, `kesediaan_jam`, `preferensi`, `ketersediaan_hari`, `keterangan_tambahan`, `bersedia_mengajar`, `komitmen_setuju`, `status`, `catatan_admin`, `edit_token`, `ip_address`, `created_at`, `updated_at`) VALUES
(4, 'MUSLIMIN', NULL, 'Pemalang', '1991-04-02', 'S1 Teknik Informatika', 'Produktif Teknik Komputer dan Jaringan', 'GTT', '089654611179', '[{\"mapel\":\"Produktif TKJ\",\"kelas\":\"10\",\"jam\":20},{\"mapel\":\"Produktif AKL\",\"kelas\":\"11\",\"jam\":20},{\"mapel\":\"Produktif MPLB\",\"kelas\":\"12\",\"jam\":10}]', 50, '[\"Wali Kelas\"]', '', '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi maupun siang sesuai kebutuhan sekolah.\"]', '[{\"prioritas\":1,\"mapel\":\"Informatika\"}]', '{\"Senin\":\"Ya\",\"Selasa\":\"Ya\",\"Rabu\":\"Ya\",\"Kamis\":\"Ya\",\"Jumat\":\"Ya\"}', '', 1, 1, 'ditolak', 'ada yg salah', 'df8ceffa85efe1da49cace66a3c7d94b907e2407', '114.8.206.223', '2026-06-02 13:41:21', '2026-06-02 13:48:01'),
(6, 'Siti Hajar, S.Pd.', NULL, NULL, NULL, 'S1 Pendidikan Agama Islam', 'Pendidikan Agama Islam', 'GTT', '089529130233', '[{\"mapel\":\"Pendidikan Agama Islam\",\"kelas\":\"XI\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar pada jadwal siang.\"]', '[]', '{\"Selasa\":[\"Siang\"],\"Rabu\":[\"Siang\"],\"Jumat\":[\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '114.10.27.253', '2026-06-16 10:59:48', '2026-06-16 10:59:48'),
(7, 'Beni Akbar, S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Agama Islam', 'Pendidikan Agama Islam, Bahasa Indonesia', 'GTT', '08989138757', '[{\"mapel\":\"Pendidikan Agama Islam\",\"kelas\":\"X,XI & XII\"},{\"mapel\":\"Bahasa Indonesia\",\"kelas\":\"X\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '114.10.75.147', '2026-06-18 04:57:38', '2026-06-18 04:57:38'),
(8, 'Puguh Wira Sakti, S.Pd', NULL, NULL, NULL, 'S1-Penjas', 'PenJaskesor', 'GTT', '08128584526', '[{\"mapel\":\"Penjaskesor\",\"kelas\":\"X,XI\"},{\"mapel\":\"Bahasa Indonesia\",\"kelas\":\"X & XI\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '114.10.75.147', '2026-06-18 05:00:50', '2026-06-18 05:00:50'),
(9, 'Ari Umar Sahid, S.Pd', NULL, NULL, NULL, 'S1-Penjas', 'Penjaskesor', 'GTT', '085773766608', '[{\"mapel\":\"Penjaskesor\",\"kelas\":\"X & XI\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '114.10.75.147', '2026-06-18 05:03:13', '2026-06-18 05:03:13'),
(10, 'Ferina Salsabilla Ayu, S.Kom', NULL, NULL, NULL, 'S1-Ilmu Komputer', 'Informatika', 'GTT', '089529529542', '[{\"mapel\":\"Informatika\",\"kelas\":\"X\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '114.10.75.147', '2026-06-18 05:06:30', '2026-06-18 05:06:30'),
(11, 'Maulida Qodrun Nada, S.M', NULL, NULL, NULL, 'S1-PENJAS Manajemen', 'Produktif Akuntansi dan Manajemen Perkantoran', 'GTT', '089502104761', '[{\"mapel\":\"Dasar dasar AKL\",\"kelas\":\"X\"},{\"mapel\":\"Praktik Akuntansi Jasa\",\"kelas\":\"XI\"},{\"mapel\":\"Pengelolaan Keuangan Sederhana\",\"kelas\":\"XI\"},{\"mapel\":\"Pengelolaan SDM\",\"kelas\":\"XII\"},{\"mapel\":\"Akuntansi Keuangan\",\"kelas\":\"XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '114.10.75.147', '2026-06-18 05:11:53', '2026-06-18 05:11:53'),
(12, 'Elvira Safitri, S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Biologi', 'Projek I.P.A.S', 'GTT', '085889623070', '[{\"mapel\":\"Projek I.P.A.S\",\"kelas\":\"X\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '114.10.75.147', '2026-06-18 05:14:07', '2026-06-18 05:14:07'),
(13, 'Atpal Parahi, S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Ekonomi', 'PKK', 'GTT', '0895344340723', '[{\"mapel\":\"K.I.K\",\"kelas\":\"XI & XII\"},{\"mapel\":\"PALIP\",\"kelas\":\"XII AKL\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '114.10.75.147', '2026-06-18 06:17:33', '2026-06-18 06:17:33'),
(14, 'Agung Prayoga, S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Sastra Inggris', 'Bahasa Inggris', 'GTT', '085772121420', '[{\"mapel\":\"Bahasa Inggris\",\"kelas\":\"X\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '114.10.75.147', '2026-06-18 06:19:32', '2026-06-18 06:19:32'),
(15, 'Nurfajri Ridwan, S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Geografi', 'Sejarah', 'GTT', '0895342869419', '[{\"mapel\":\"Sejarah\",\"kelas\":\"X AKL MP\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\"],\"Selasa\":[\"Pagi\"],\"Rabu\":[\"Pagi\"],\"Kamis\":[\"Pagi\"],\"Jumat\":[\"Pagi\"]}', '', 1, 1, 'baru', NULL, NULL, '114.10.75.147', '2026-06-18 06:34:44', '2026-06-18 06:34:44'),
(16, 'Muhsin, S.Ag', NULL, NULL, NULL, 'S1-Pendidikan Agama Islam', 'PAI', 'GTT', '085791415270', '[{\"mapel\":\"PAI\",\"kelas\":\"XI\"},{\"mapel\":\"Pkn\",\"kelas\":\"X & XI\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '114.10.75.147', '2026-06-18 06:44:43', '2026-06-18 06:44:43'),
(17, 'Andri Maulana', NULL, NULL, NULL, 'S1-Pendidikan Sastra Inggris', 'Bahasa Inggris', 'GTT', '081288740397', '[{\"mapel\":\"Bahasa Inggris\",\"kelas\":\"XI\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Rabu\":[\"Siang\"],\"Kamis\":[\"Siang\"],\"Jumat\":[\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '114.10.75.147', '2026-06-18 06:48:15', '2026-06-18 06:48:15'),
(18, 'Sigit Imam Pambudi, S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Sastra Inggris', 'Bahasa Inggris', 'GTT', '081328093438', '[{\"mapel\":\"Bahasa Inggris\",\"kelas\":\"XI & XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '114.10.75.147', '2026-06-18 06:54:18', '2026-06-18 06:54:18'),
(19, 'Fairuz, S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Agama Islam', 'Produktif TKJ', 'GTT', '087876566348', '[{\"mapel\":\"Dasar dasar TKJ\",\"kelas\":\"X\"},{\"mapel\":\"P.Pengalaman Jaringan\",\"kelas\":\"XI\"},{\"mapel\":\"PKPJ\",\"kelas\":\"XI TKJ\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 07:35:36', '2026-06-18 07:35:36'),
(20, 'Abid Muchlisien, S.Kom', NULL, NULL, NULL, 'S1-Teknik Informatika', 'Produktif TKJ', 'GTT', '081385161712', '[{\"mapel\":\"Dasar Dasar TKJ\",\"kelas\":\"X\"},{\"mapel\":\"PPJ\",\"kelas\":\"XI\"},{\"mapel\":\"PKPJ\",\"kelas\":\"XI & XII\"},{\"mapel\":\"TJKN\",\"kelas\":\"XI\"},{\"mapel\":\"ASJ\",\"kelas\":\"XI & XII\"},{\"mapel\":\"Keamanan Jaringan\",\"kelas\":\"XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 07:40:18', '2026-06-18 07:40:18'),
(21, 'Imran Rahman Surbakti, S.Kom', NULL, NULL, NULL, 'S1-Teknik Informatika', 'Produktif TKJ', 'GTT', '0811304417', '[{\"mapel\":\"DDTKJ\",\"kelas\":\"X\"},{\"mapel\":\"TJKN\",\"kelas\":\"XI\"},{\"mapel\":\"PKPJ\",\"kelas\":\"XI & XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 07:43:36', '2026-06-18 07:43:36'),
(22, 'Ihsan Fahmi, S.Pd', NULL, NULL, NULL, 'S1', 'Produktif TKJ', 'GTT', '085212300414', '[{\"mapel\":\"K.I.K\",\"kelas\":\"XI & XII\"},{\"mapel\":\"Produktif TKJ\",\"kelas\":\"XI & XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Siang\"],\"Selasa\":[\"Siang\"],\"Rabu\":[\"Siang\"],\"Kamis\":[\"Siang\"],\"Jumat\":[\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 07:47:58', '2026-06-18 07:47:58'),
(23, 'Nurhilalliyah,S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Bahasa & Sastra Indonesia', 'Bahasa Indonesia', 'GTT', '089506813938', '[{\"mapel\":\"Bahasa Indonesia\",\"kelas\":\"XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Siang\"],\"Selasa\":[\"Siang\"],\"Rabu\":[\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 08:10:06', '2026-06-18 08:10:06'),
(24, 'Rr. Findarina SE.MP', NULL, NULL, NULL, 'S1-Ekonomi', 'Bisnis dan Manajemen', 'GTT', '0895328305305', '[{\"mapel\":\"Dasar dasar Manajemen\",\"kelas\":\"X mplb\"},{\"mapel\":\"Ekonomi Bisnis\",\"kelas\":\"XI mplb\"},{\"mapel\":\"Ekonomi Bisnis\",\"kelas\":\"XI akl\"},{\"mapel\":\"Administrasi Umum\",\"kelas\":\"XI akl\"},{\"mapel\":\"Sarana Prasarana\",\"kelas\":\"XII MPLB\"},{\"mapel\":\"Sarana Kepegawaian\",\"kelas\":\"XII MPLB\"},{\"mapel\":\"Sarana Humas dan Keprotokolan\",\"kelas\":\"XII MPLB\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 08:16:47', '2026-06-18 08:16:47'),
(25, 'Elsifa Busra, S.Pd', NULL, NULL, NULL, 'S1-PendidikaBahasa', 'Bahasa Indonesia', 'GTT', '081315364502', '[{\"mapel\":\"Bahasa Indonesia\",\"kelas\":\"X,XI & XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 08:22:07', '2026-06-18 08:22:07'),
(26, 'Septimawati, S.Pd', NULL, NULL, NULL, 'S2-MIPA', 'Projek I.P.A.S', 'GTT', '082124522410', '[{\"mapel\":\"Projek I.P.A.S\",\"kelas\":\"X\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\"],\"Selasa\":[\"Pagi\"],\"Rabu\":[\"Pagi\"],\"Kamis\":[\"Pagi\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 08:24:02', '2026-06-18 08:24:02'),
(27, 'Hj. Intan Suryanim, S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Akuntansi', 'Seni Budaya (Seni Musik)', 'GTT', '089630015794', '[{\"mapel\":\"Seni Musik\",\"kelas\":\"X\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Selasa\":[\"Pagi\"],\"Rabu\":[\"Pagi\"],\"Kamis\":[\"Pagi\"],\"Jumat\":[\"Pagi\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 08:27:41', '2026-06-18 08:27:41'),
(28, 'Muji Rahayu, S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Pancasila', 'Pendidikan Pancasila', 'GTT', '081359390512', '[{\"mapel\":\"Pendidikan Pancasila\",\"kelas\":\"X & XI\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Rabu\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 08:35:11', '2026-06-18 08:35:11'),
(29, 'Ambarsari Dwi S. S.E', NULL, NULL, NULL, 'S1-Ekonomi', 'Produktif MPLB', 'GTT', '081381283737', '[{\"mapel\":\"Dasar Dasar MPLB\",\"kelas\":\"X\"},{\"mapel\":\"Administrasi Umum\",\"kelas\":\"XI\"},{\"mapel\":\"Kearsipan\",\"kelas\":\"XI\"},{\"mapel\":\"Komunikasi di Tempat Kerja\",\"kelas\":\"XII\"},{\"mapel\":\"Humas\",\"kelas\":\"XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Siang\"],\"Selasa\":[\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 08:39:31', '2026-06-18 08:39:31'),
(30, 'Nihayatul Kamilah, S.Pd.I', NULL, NULL, NULL, 'S1-Pendidikan Agama Islam', 'P.A.I & PKN', 'GTT', '089510302666', '[{\"mapel\":\"PAI\",\"kelas\":\"X & XII\"},{\"mapel\":\"PKN\",\"kelas\":\"XI\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 08:42:59', '2026-06-18 08:42:59'),
(31, 'Nasrotun Kamilah, S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Matematika', 'Matematika', 'GTT', '085729370529', '[{\"mapel\":\"Matematika\",\"kelas\":\"X, XI dan XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 08:48:29', '2026-06-18 08:48:29'),
(32, 'Maria Ulfah, S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Agama Islam', 'Bahasa Indonesia', 'GTT', '08567003516', '[{\"mapel\":\"Bahasa Indonesia\",\"kelas\":\"X & XI\"},{\"mapel\":\"K.I.K\",\"kelas\":\"XI\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 08:53:15', '2026-06-18 08:53:15'),
(33, 'Wardani Widya Gayatri, S.S', NULL, NULL, NULL, 'S1- Pendidikan Sastra dan Bahasa Jepang', 'Bahasa Jepang', 'GTT', '081213235707', '[{\"mapel\":\"Bahasa Jepang\",\"kelas\":\"XI & XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 08:55:30', '2026-06-18 08:55:30'),
(34, 'Widya Husniati, S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Bahasa Inggris', 'Bahasa Inggris', 'GTT', '085695123339', '[{\"mapel\":\"Bahasa Inggris\",\"kelas\":\"X & XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\"],\"Selasa\":[\"Pagi\"],\"Rabu\":[\"Pagi\"],\"Kamis\":[\"Pagi\"],\"Jumat\":[\"Pagi\"]}', '', 1, 1, 'baru', NULL, NULL, '180.252.141.20', '2026-06-18 09:11:52', '2026-06-18 09:11:52'),
(35, 'Indri Rachmawati Chasanah, S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Matematika', 'Matematika', 'GTT', '082324893039', '[{\"mapel\":\"Matematika\",\"kelas\":\"X,XI & XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\"]}', '', 1, 1, 'baru', NULL, NULL, '2001:448a:2002:a58b:a829:561:1fce:588a', '2026-06-18 17:39:19', '2026-06-18 17:39:19'),
(36, 'NURFITRIANA,S.Pd', NULL, NULL, NULL, 'S1-MATEMATIKA', 'MATEMATIKA', 'GTT', '082317612617', '[{\"mapel\":\"MATEMATIKA\",\"kelas\":\"X,XI & XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '114.8.207.238', '2026-06-24 08:52:27', '2026-06-24 08:52:27'),
(37, 'Yeni Maryani, S.T', NULL, NULL, NULL, 'S1 Teknik Informatika', 'Informatika', 'GTY', '085773176933', '[{\"mapel\":\"Informatika\",\"kelas\":\"X\"},{\"mapel\":\"Dasar dasar TKJ\",\"kelas\":\"X\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\"]', '[]', '{\"Senin\":[\"Pagi\"],\"Selasa\":[\"Pagi\"],\"Kamis\":[\"Pagi\"]}', '', 1, 1, 'baru', NULL, NULL, '182.3.43.40', '2026-06-24 09:32:48', '2026-06-24 09:32:48'),
(38, 'Muthia Fitri Maulida', NULL, NULL, NULL, 'S1-Pendidikan Sejarah', 'Sejarah', 'GTT', '085711087285', '[{\"mapel\":\"Sejarah\",\"kelas\":\"X & XI\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '101.255.166.198', '2026-06-24 16:02:53', '2026-06-24 16:02:53'),
(39, 'Fhrani Juhana, S.M', NULL, NULL, NULL, 'S1-Manajemen', 'Produktif MPLB', 'GTT', '089516697762', '[{\"mapel\":\"Produktif MPLB\",\"kelas\":\"X & XI\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '101.255.166.198', '2026-06-24 16:04:58', '2026-06-24 16:04:58'),
(40, 'Khopipah Inayatul lail', NULL, NULL, NULL, 'S1-Bimbingan & Konseling', 'Bahasa Indonesia', 'GTT', '081381740091', '[{\"mapel\":\"Bahasa Indonesia\",\"kelas\":\"X\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '101.255.166.198', '2026-06-24 16:07:22', '2026-06-24 16:07:22'),
(41, 'Iin Mutmainah, S.Kom', NULL, NULL, NULL, 'S1-Teknik Informatika', 'Informatika', 'GTT', '087744624067', '[{\"mapel\":\"Informatika\",\"kelas\":\"x\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '101.255.166.198', '2026-06-24 16:09:43', '2026-06-24 16:09:43'),
(42, 'Razkiyatul Awwal Mubdiyah, S.Psi', NULL, NULL, NULL, 'S1-Psikologi', 'PKN', 'GTT', '081524240504', '[{\"mapel\":\"PKN\",\"kelas\":\"X,XI\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '101.255.166.198', '2026-06-24 16:11:37', '2026-06-24 16:11:37'),
(43, 'Desi Ria Fitri Yani, A.Md S.I. Ak', NULL, NULL, NULL, 'S1-Sistem Informasi Akuntansi', 'Produktif TKJ', 'GTT', '085894432819', '[{\"mapel\":\"Produktif TKJ\",\"kelas\":\"X & XI\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Siang\"],\"Selasa\":[\"Siang\"],\"Rabu\":[\"Siang\"],\"Kamis\":[\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '101.255.166.198', '2026-06-24 16:14:11', '2026-06-24 16:14:11'),
(44, 'Siti Nurjanah, S.Pd', NULL, NULL, NULL, 'S1-Pendidikan Agama Islam', 'Pendidikan Agama Islam', 'GTT', '089513953588', '[{\"mapel\":\"PAI\",\"kelas\":\"XI\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Kamis\":[\"Siang\"],\"Jumat\":[\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '101.255.166.198', '2026-06-24 16:16:40', '2026-06-24 16:16:40'),
(45, 'Bella Aprilia', NULL, NULL, NULL, 'S1-MIPA', 'Matematika', 'GTT', '087747466424', '[{\"mapel\":\"Matematika\",\"kelas\":\"X\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\"],\"Selasa\":[\"Pagi\"],\"Jumat\":[\"Pagi\"]}', '', 1, 1, 'baru', NULL, NULL, '101.255.166.198', '2026-06-24 16:18:20', '2026-06-24 16:18:20'),
(46, 'Rizky Zalianty', NULL, NULL, NULL, 'S1-Manajemen', 'Produktif MPLB', 'GTT', '08979718728', '[{\"mapel\":\"Produktif MPLB\",\"kelas\":\"X\"},{\"mapel\":\"Bahasa Indonesia\",\"kelas\":\"X\"},{\"mapel\":\"PKWU\",\"kelas\":\"XI & XII\"},{\"mapel\":\"Seni Budaya\",\"kelas\":\"X\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '101.255.166.198', '2026-06-24 16:21:26', '2026-06-24 16:21:26'),
(47, 'Farah Miftakhul Jannah, S.Kom', NULL, NULL, NULL, 'S1-Ilmu Komputer/Informatika', 'Produktif TKJ', 'GTT', '088245464014', '[{\"mapel\":\"Produktif TKJ\",\"kelas\":\"X,XI & XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '101.255.166.198', '2026-06-24 16:24:19', '2026-06-24 16:24:19'),
(48, 'Aida Fitriah, S.H', NULL, NULL, NULL, 'S1-Hukum Ekonomi Syariah', 'Ekonomi', 'GTT', '082224352905', '[{\"mapel\":\"Ekonomi dan Bisnis\",\"kelas\":\"X\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '101.255.166.198', '2026-06-24 16:34:15', '2026-06-24 16:34:15'),
(49, 'Astuty Pohan, M.M', NULL, NULL, NULL, 'S2-Magister Manajemen', 'Bahasa Inggris', 'GTT', '085691327520', '[{\"mapel\":\"Bahasa Inggris\",\"kelas\":\"X, XI dan XII\"}]', 0, '[\"Bersedia menerima tugas tambahan\"]', NULL, '[\"Bersedia mengajar sesuai beban kerja yang ditetapkan sekolah.\",\"Bersedia mengajar lintas kelas\\/program keahlian sesuai kompetensi.\",\"Bersedia mengajar pada jadwal pagi.\",\"Bersedia mengajar pada jadwal siang.\",\"Bersedia mengajar pada jadwal pagi & siang.\"]', '[]', '{\"Senin\":[\"Pagi\",\"Siang\"],\"Selasa\":[\"Pagi\",\"Siang\"],\"Rabu\":[\"Pagi\",\"Siang\"],\"Kamis\":[\"Pagi\",\"Siang\"],\"Jumat\":[\"Pagi\",\"Siang\"]}', '', 1, 1, 'baru', NULL, NULL, '114.8.207.223', '2026-06-26 09:31:21', '2026-06-26 09:31:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tahun_ajaran`
--

CREATE TABLE `tahun_ajaran` (
  `id` int(11) UNSIGNED NOT NULL,
  `tahun` varchar(20) NOT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL,
  `is_aktif` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `audit_log_admin_id_foreign` (`admin_id`),
  ADD KEY `tabel_record_id` (`tabel`,`record_id`);

--
-- Indeks untuk tabel `guru`
--
ALTER TABLE `guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_guru` (`kode_guru`),
  ADD KEY `nama` (`nama`);

--
-- Indeks untuk tabel `guru_mapel`
--
ALTER TABLE `guru_mapel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `guru_id_mapel_id` (`guru_id`,`mapel_id`),
  ADD KEY `guru_mapel_mapel_id_foreign` (`mapel_id`);

--
-- Indeks untuk tabel `hari`
--
ALTER TABLE `hari`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indeks untuk tabel `jadwal`
--
ALTER TABLE `jadwal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kelas_id_hari_id_jam_id` (`kelas_id`,`hari_id`,`jam_id`),
  ADD UNIQUE KEY `guru_id_hari_id_jam_id` (`guru_id`,`hari_id`,`jam_id`),
  ADD KEY `jadwal_hari_id_foreign` (`hari_id`),
  ADD KEY `jadwal_jam_id_foreign` (`jam_id`),
  ADD KEY `jadwal_pengampu_id_foreign` (`pengampu_id`),
  ADD KEY `tahun_ajaran_id` (`tahun_ajaran_id`);

--
-- Indeks untuk tabel `jam_pelajaran`
--
ALTER TABLE `jam_pelajaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shift_jam_ke` (`shift`,`jam_ke`);

--
-- Indeks untuk tabel `jurusan`
--
ALTER TABLE `jurusan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indeks untuk tabel `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_kelas` (`nama_kelas`),
  ADD KEY `kelas_jurusan_id_foreign` (`jurusan_id`),
  ADD KEY `kelas_wali_kelas_id_foreign` (`wali_kelas_id`),
  ADD KEY `shift` (`shift`);

--
-- Indeks untuk tabel `ketersediaan_guru`
--
ALTER TABLE `ketersediaan_guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `guru_id_hari_id_jam_id` (`guru_id`,`hari_id`,`jam_id`),
  ADD KEY `ketersediaan_guru_hari_id_foreign` (`hari_id`),
  ADD KEY `ketersediaan_guru_jam_id_foreign` (`jam_id`);

--
-- Indeks untuk tabel `mata_pelajaran`
--
ALTER TABLE `mata_pelajaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_mapel` (`kode_mapel`);

--
-- Indeks untuk tabel `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pengampu`
--
ALTER TABLE `pengampu`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kelas_id_mapel_id` (`kelas_id`,`mapel_id`),
  ADD KEY `pengampu_mapel_id_foreign` (`mapel_id`),
  ADD KEY `guru_id` (`guru_id`);

--
-- Indeks untuk tabel `pengumuman`
--
ALTER TABLE `pengumuman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `is_aktif_deleted_at` (`is_aktif`,`deleted_at`);

--
-- Indeks untuk tabel `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nip_nuptk` (`nip_nuptk`),
  ADD KEY `idx_status_kepegawaian` (`status_kepegawaian`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indeks untuk tabel `tahun_ajaran`
--
ALTER TABLE `tahun_ajaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tahun_semester` (`tahun`,`semester`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=510;

--
-- AUTO_INCREMENT untuk tabel `guru`
--
ALTER TABLE `guru`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT untuk tabel `guru_mapel`
--
ALTER TABLE `guru_mapel`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT untuk tabel `hari`
--
ALTER TABLE `hari`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `jadwal`
--
ALTER TABLE `jadwal`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT untuk tabel `jam_pelajaran`
--
ALTER TABLE `jam_pelajaran`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `jurusan`
--
ALTER TABLE `jurusan`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=125;

--
-- AUTO_INCREMENT untuk tabel `ketersediaan_guru`
--
ALTER TABLE `ketersediaan_guru`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT untuk tabel `mata_pelajaran`
--
ALTER TABLE `mata_pelajaran`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT untuk tabel `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `pengampu`
--
ALTER TABLE `pengampu`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT untuk tabel `pengumuman`
--
ALTER TABLE `pengumuman`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT untuk tabel `tahun_ajaran`
--
ALTER TABLE `tahun_ajaran`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `guru_mapel`
--
ALTER TABLE `guru_mapel`
  ADD CONSTRAINT `guru_mapel_guru_id_foreign` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `guru_mapel_mapel_id_foreign` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal`
--
ALTER TABLE `jadwal`
  ADD CONSTRAINT `jadwal_guru_id_foreign` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`),
  ADD CONSTRAINT `jadwal_hari_id_foreign` FOREIGN KEY (`hari_id`) REFERENCES `hari` (`id`),
  ADD CONSTRAINT `jadwal_jam_id_foreign` FOREIGN KEY (`jam_id`) REFERENCES `jam_pelajaran` (`id`),
  ADD CONSTRAINT `jadwal_kelas_id_foreign` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_pengampu_id_foreign` FOREIGN KEY (`pengampu_id`) REFERENCES `pengampu` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_tahun_ajaran_id_foreign` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `kelas`
--
ALTER TABLE `kelas`
  ADD CONSTRAINT `kelas_jurusan_id_foreign` FOREIGN KEY (`jurusan_id`) REFERENCES `jurusan` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `kelas_wali_kelas_id_foreign` FOREIGN KEY (`wali_kelas_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `ketersediaan_guru`
--
ALTER TABLE `ketersediaan_guru`
  ADD CONSTRAINT `ketersediaan_guru_guru_id_foreign` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ketersediaan_guru_hari_id_foreign` FOREIGN KEY (`hari_id`) REFERENCES `hari` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ketersediaan_guru_jam_id_foreign` FOREIGN KEY (`jam_id`) REFERENCES `jam_pelajaran` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pengampu`
--
ALTER TABLE `pengampu`
  ADD CONSTRAINT `pengampu_guru_id_foreign` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`),
  ADD CONSTRAINT `pengampu_kelas_id_foreign` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pengampu_mapel_id_foreign` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
