# Panduan gaya tampilan publik — acuan aplikasi Android (Flutter)

Sumber kebenaran: `resources/css/publik.css` (Tailwind v4) hasil rombak publik P0–P7 (`docs/RENCANA-TAMPILAN-PUBLIK.md`), konsep portal
**rumah.pendidikan.go.id** dengan identitas sekolah sendiri. Dokumen ini menerjemahkan token & komponennya untuk aplikasi Flutter
`flutter-muslimin`. Bila nilai di sini berbeda dengan `publik.css`, **`publik.css` yang benar**.

> Jangan ubah `AppTheme` aplikasi tanpa izin pemilik proyek. Dokumen ini acuan saat layar baru dibuat / tema diselaraskan.

## 1. Prinsip
- **Tenang & resmi**: latar putih / abu sangat muda bergantian, satu warna utama (lilac) untuk yang bisa diketuk, garis tipis 1 px,
  sudut 8. Judul semibold ukuran sedang — tidak ada judul raksasa.
- **Disusun menurut pengguna** (Siswa, Guru, Orang Tua, Profil Sekolah), bukan menurut fitur sistem. Tanpa kata "Ruang".
- **DILARANG** (dinilai "buatan AI"): gradasi, bulatan cahaya, pola titik, pil/lencana bertitik, emoji, angka berhitung naik,
  ikon warna-warni pelangi, bayangan besar, kartu "terangkat" saat ditekan, testimoni karangan, slogan pemasaran.
- Bahasa lugas & faktual, sentence case ("Jadwal kelas", bukan "JADWAL KELAS"). Tanggal: **13 Juli 2026 | 15.59 WIB**.

## 2. Warna
| Token | Hex | Pakai untuk |
|---|---|---|
| `teks` | `#323232` | teks utama (bukan hitam murni) |
| `teks-2` | `#5E5E5E` | teks kedua, keterangan; satu-satunya teks redup yang boleh di atas latar abu |
| `teks-3` | `#727272` | placeholder / keterangan kecil **hanya di atas putih** |
| `garis` | `#E5E5E5` | garis kartu, pemisah baris |
| `garis-2` | `#C7C7C7` | garis isian, tombol netral |
| `permukaan` | `#F5F5F5` | latar bagian bergantian, latar halaman formulir |
| `permukaan-2` | `#FAFAFA` | sorot halus (hover/tekan) |
| `kaki` | `#1E1F1F` | latar kaki halaman (teks `#C7C7C7`, judul putih) |
| `brand-50` | `#F7F4FC` | latar terpilih, kotak info lilac |
| `brand-100` | `#EFE8F9` | garis kotak lilac, latar bilah "hari ini" |
| `brand-200` | `#DED1F2` | cincin fokus, garis panel terpilih |
| `brand-600` | `#7A56B3` | garis fokus, cincin item terpilih |
| `brand-700` | `#634596` | **warna utama**: tombol utama, tautan, nomor langkah, segmen bilah aktif |
| `brand-800` | `#4E3778` | tombol utama saat ditekan, teks label lilac, tema status bar |
| `brand-900` | `#3A2A5A` | teks di atas `brand-50` |

Warna status (latar / teks) — satu label per status, tanpa titik:
| Status | Latar | Teks | Contoh |
|---|---|---|---|
| Berhasil / hadir / disetujui | `#ECFDF5` | `#065F46` | Disetujui, Hadir, Dibuka |
| Perlu perhatian / telat / perbaikan | `#FFFBEB` | `#92400E` | Perlu perbaikan, Telat |
| Informasi / menunggu | `#F7F4FC` | `#4E3778` | Menunggu ACC, Izin |
| Galat / ditolak / tidak hadir | `#FEF2F2` | `#991B1B` | Ditolak, Tidak hadir |
| Sakit | `#FDF2F8` | `#9D174D` | Sakit |
| Ditutup / netral | `#F5F5F5` | `#5E5E5E` | Sedang ditutup |

Kontras: semua pasangan di atas ≥ 4,5:1 (AA). Aplikasi publik **tanpa mode gelap** (acuan pun tanpa mode gelap).

## 3. Huruf
Inter (variable), semua judul **semibold (600)**. Ukuran (sp) — layar HP pakai kolom kiri:
| Peran | HP | Tablet/lebar | Tinggi baris |
|---|---|---|---|
| Judul halaman (H1) | 24 | 32 | 1,25 |
| Judul bagian | 24 | 28–32 | 1,25 |
| Judul kartu besar | 19 | 22 | 1,3 |
| Judul kartu / item | 17 | 17 | 1,3 |
| Isi | 16 | 16 | 1,5 |
| Keterangan / label kecil | 14 | 14 | 1,45 |
| Label status, kapital kecil | 12 (semibold) | 12 | — |

## 4. Bentuk, jarak, bayangan
- Sudut: **8** (kartu, tombol, isian, label 4). Lingkaran penuh hanya untuk nomor langkah & tombol ikon bulat.
- Garis kartu 1 px `garis`; isian 1 px `garis-2`; panel terpilih 2 px.
- Jarak dasar 4: padding kartu 16 (HP) / 20; jarak antarkolom isian 20; jarak antarbagian 48–56 (HP) / 80.
- Bayangan **hanya** untuk benda melayang (laci, munculan, tombol geser banner): `0 2 10 rgba(0,0,0,.12)` + `0 1 2 rgba(0,0,0,.06)`.
- Sasaran sentuh minimal **44×44**. Gerak hanya transisi warna 150 ms; hormati "kurangi gerakan".

## 5. Komponen (nama kelas web → padanan Flutter)
| Web (`publik.css`) | Ciri | Padanan Flutter |
|---|---|---|
| `.kepala-portal` | putih, lengket, bayangan tipis; logo 40 + nama sekolah + tahun pelajaran; kotak cari; tombol kisi & Masuk | `AppBar` putih `elevation: 1`, judul 2 baris |
| `.laci-portal` + `.laci-kisi` | laci kanan lebar ≤ 336, kisi 3 kolom ilustrasi 56 + nama | `endDrawer` berisi `GridView` 3 kolom |
| `.tombol-utama` | latar `brand-700`, teks putih semibold 14, tinggi 40 (besar 48), sudut 8, ikon 20 di kiri/kanan | `FilledButton` |
| `.tombol-garis` | garis 1 `brand-700`, teks `brand-700`, latar putih | `OutlinedButton` |
| `.tombol-netral` | garis 1 `garis-2`, teks `teks` | `OutlinedButton` netral |
| `.tombol-teks` | teks `brand-700`, tanpa latar, garis bawah saat tekan | `TextButton` |
| `.kartu` | putih, garis 1 `garis`, sudut 8; kepala dipisah garis (judul 17 + keterangan 14) | `Card(elevation: 0, shape: …side)` |
| `.ubin` (Jelajahi layanan) | ilustrasi 72 (HP 36–64) + nama di bawah, latar `permukaan-2`, 4 kolom | `GridView` 4 kolom (HP 4 kecil) |
| `.kartu-layanan` | ikon/ilustrasi 48 + judul 19 + satu kalimat + "Buka layanan →"; label "Sedang ditutup" bila tutup | `ListTile` besar / `Card` |
| `.kartu-info` | gambar 16:9, label kategori, judul maks 2 baris, ringkasan 3 baris, tanggal, "Baca selengkapnya" | `Card` bergambar |
| `.label-*` | latar & teks status (tabel §2), 12 semibold, sudut 4 | `Container` kecil / `Chip` tanpa ikon |
| `.peringatan-*` | kotak garis tipis + ikon 20 kiri; info lilac, awas kuning, galat merah, sukses hijau | `MaterialBanner` / kotak sendiri |
| `.inp` | tinggi 44, garis `garis-2`, sudut 8, teks 16; fokus garis `brand-600` + cincin `brand-200`; galat garis merah + pesan merah 14 di bawah | `InputDecoration(OutlineInputBorder)` |
| `.langkah-*` (wizard) | "Langkah 2 dari 5" + judul; **bilah bersegmen** (1 segmen per langkah, aktif `brand-700`, sisa `garis`); titik bernomor 36 di layar lebar | `Row` segmen + `Stepper` tanpa garis |
| `.kartu-langkah` | kepala: nomor bulat 32 `brand-700` + judul 19; isi padding 20 | `Card` per langkah |
| `.pilih-item` (daftar nama) | baris 48, terpilih = latar `brand-50` + cincin 2 `brand-600`; label status di kanan | `ListTile(selected: …)` |
| `.pilihan-tombol` (radio) | kotak garis 2 `garis-2`; terpilih latar `brand-700` teks putih | `SegmentedButton` / `ChoiceChip` penuh |
| `.pilih-cek` (pernyataan) | kotak garis, centang 20 kiri; tercentang latar `brand-50` | `CheckboxListTile` berbingkai |
| `.formulir-nav` | Kembali (netral, kiri) · Lanjut / Kirim (utama, kanan), tinggi 48 | `Row(spaceBetween)` |
| `.jadwal` | tabel: kepala `permukaan`, garis 1, kolom jam terkunci saat geser, kolom hari ini latar `brand-50` + "Hari ini"; sel = kotak putih bergaris kiri 2 `brand-600` | `DataTable` gulir horizontal + kolom beku |
| `.kosong` | ikon 24 dalam lingkaran `permukaan` 48 + judul 17 + satu kalimat | widget "kosong" bersama |
| `.banner` | geser, sudut 8, titik bawah (aktif `brand-700`) + tombol jeda; foto penuh + kotak keterangan hitam 65% kiri bawah | `PageView` + indikator |

## 6. Ikon & ilustrasi
- Ikon garis Flowbite (outline), satu warna (`brand-700` atau `teks-2`), ukuran 20 (16 di tombol kecil). Padanan: Material Symbols
  Outlined / `flowbite` SVG yang sama.
- Ilustrasi HD berwarna lilac: `public/assets/img/ilustrasi/*.svg` (siswa, guru, orang-tua, sekolah, jadwal-kelas, jadwal-guru,
  absensi, pkl, lapor-pkl, biodata, kesediaan, pengumuman, dokumen, masuk, aplikasi, bantuan) — boleh disalin ke aset aplikasi.
- Logo/ilustrasi Kemendikdasmen **tidak** dipakai; identitas = logo & nama sekolah dari Pengaturan.

## 7. Pola layar
- **Beranda**: banner → "Jelajahi layanan" (4 ubin) → Tentang sekolah (foto + angka + grafik) → Layanan unggulan → Cek jadwal →
  Informasi untuk Anda → Pertanyaan yang sering ditanyakan.
- **Halaman dalam**: remah (Beranda › Guru › Jadwal mengajar) → label ber-ilustrasi → H1 → satu kalimat → isi di latar `permukaan`.
- **Formulir**: kop (logo, nama sekolah, judul formulir, tahun pelajaran) → peringatan persiapan → penunjuk langkah → satu kartu per
  langkah → navigasi bawah; draf disimpan di perangkat; galat menempel di isian; ringkasan sebelum kirim + pernyataan dicentang.
