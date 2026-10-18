# Rencana: Rombak Tampilan Halaman Publik (konsep Rumah Pendidikan)

Dibuat 2026-10-15. **Status: P0 ✅ · P1 ✅ · P3 ✅ · P4 ✅ (2026-10-15/16) · P2a ✅ · P2b ✅ · P5 ✅ · P6 Formulir siswa ✅ (P6a PKL · P6b Lapor · P6c Biodata, 2026-10-18) → P7.**
Semua masih perubahan lokal di folder (cabang `upgrade-tampilan`, belum di-commit — atas permintaan user).

> **Arahan user 2026-10-15 malam (wajib):** (1) jangan pakai kata "Ruang" — cukup **Siswa, Guru, Orang Tua, Profil Sekolah**
> (alamat `/siswa`, `/guru`, `/orang-tua`, `/profil`); (2) tambah backend agar sekolah bisa **mengunggah foto sekolah** yang tampil
> di halaman publik (banner beranda, profil); (3) **selesaikan P1–P7 100% tanpa push**, user menguji sendiri di lokal (Claude memberi
> daftar halaman); tanpa foto layar; (4) **bongkar total, 99% konsep rumah.pendidikan.go.id**; (5) hasilnya jadi **acuan tampilan
> aplikasi Android** (panduan gaya ditulis di P7). Keputusan bagian 9 yang tidak dijawab memakai saran bawaan (kaki arang #1E1F1F,
> isian profil singkat + WhatsApp bantuan + tautan aplikasi di Pengaturan, tanpa halaman contoh). Warna utama tetap lilac sekolah.

Kerja per fase: selesai satu fase → centang di bagian 8 → lapor → BERHENTI menunggu aba-aba. Dikerjakan di cabang git
`upgrade-tampilan` (saat rancangan ini dibuat sama persis dengan `main`, e99092c). `main` tetap siap deploy untuk perbaikan klien;
penggabungan + push + deploy di akhir (bagian 11). Seri ini lanjutan dari `docs/RENCANA-UPGRADE-TAMPILAN.md` (tampilan admin SELESAI).

## 1. Tujuan

Halaman publik (kangmuslim.com, form PKL, form biodata, form kesediaan guru) dikatai orang "AI slop". Halaman dirombak dengan
**konsep portal resmi https://rumah.pendidikan.go.id** (Kemendikdasmen): disusun per pengguna ("ruang"), cari dulu, gambar asli
sekolah, judul jelas, tenang, resmi. Yang diambil hanya **pola susunan & gaya**; identitas tetap milik sekolah (logo, nama, warna
lilac). Logo, nama, gambar, ilustrasi, dan warna biru Kemendikdasmen **tidak dipakai**.

Syarat tetap sama dengan seri admin: siap CSP (JS/CSS di berkas), tanpa fitur hilang, nyaman di HP, kontras AA.

## 2. Hasil pengamatan acuan (dirender Chrome tanpa layar, 2026-10-15)

### 2a. Susunan beranda rumah.pendidikan.go.id (atas → bawah)
1. Tautan tersembunyi "Langsung ke konten utama" (muncul saat Tab).
2. **Kepala putih lengket:** logo instansi + logo produk · **kotak cari besar di tengah** ("Contoh: Pembelajaran, Panduan, dsb..." +
   tombol Cari) · ikon Pusat Bantuan · tombol **Masuk**. Di HP kotak cari pindah ke baris kedua.
3. **Banner geser** berisi gambar buatan tim (berita, video, survei), sudut membulat, tombol bulat putih kiri/kanan, titik penanda.
4. **"Jelajahi Ruang di Rumah Pendidikan"** — 8 ubin (4 kolom × 2 baris, ±184 px): ikon ilustrasi + nama ruang (Ruang GTK, Murid,
   Sekolah, Bahasa, Pemerintah, Mitra, Publik, Orang Tua). Ubin putih, garis tipis, sudut 8 px, latar abu saat disorot.
5. "Semangat Rumah Pendidikan" — pita "tentang kami" bergambar + satu kalimat + tautan "Pelajari Selengkapnya".
6. "Layanan Unggulan" — kartu geser: gambar layar layanan + ikon kecil + judul + satu baris keterangan.
7. Testimoni pengguna (nama + jabatan asli).
8. **"Informasi Untuk Anda"** — kartu berita: gambar, label kategori ("Berita"), judul 2 baris, tanggal "13 Juli 2026 | 15.59 WIB",
   "Baca Selengkapnya"; tautan "Lihat lebih banyak".
9. **"Pertanyaan yang paling sering ditanyakan"** — daftar lipat + tombol "Ke Pusat Bantuan".
10. **Kaki gelap** (#1E1F1F): logo, nama instansi + alamat · Navigasi · Unduh Aplikasi.

### 2b. Halaman dalam "Ruang" (contoh /ruang/sekolah)
- Pembuka putih: ikon ruang + label kecil, **H1 "Ruang Sekolah"**, satu kalimat keterangan, banner lebar opsional.
- Bagian latar abu muda: **"Layanan yang Tersedia"**, dikelompokkan (mis. "Layanan Pendidikan"), tiap layanan = kartu ikon + judul +
  satu kalimat, seluruh kartu bisa diklik.

### 2c. Gaya (dibaca dari CSS acuan)
- Huruf Inter; semua judul **semibold (600)**, ukuran sedang: judul bagian 32 px (HP 24 px), judul kartu 19–22 px. Tidak ada judul raksasa.
- Teks abu tua netral **#323232** (bukan hitam, bukan slate kebiruan); teks kedua #5e5e5e–#727272.
- Satu warna utama untuk yang bisa diklik (biru #0954D9); latar putih & abu sangat muda (#F5F5F5) **bergantian per bagian**.
- Sudut 8 px; bayangan halus hanya pada tombol geser yang melayang; jarak antarbagian lega (56–128 px di layar lebar).

### 2d. Kenapa acuan terasa resmi & buatan manusia
Disusun menurut **siapa pengunjungnya** (ruang per peran), **cari** sebagai pintu utama, **gambar asli** buatan tim, bahasa lugas
("Informasi Untuk Anda"), berita bertanggal, tanya-jawab, kaki berisi identitas & alamat lembaga. Hiasan hampir nol.

## 3. Kondisi halaman publik sekarang

### 3a. Daftar halaman (25 view, 4 bingkai)
| Bingkai | View |
|---|---|
| `public/layout.php` (portal) | home, jadwal_kelas, jadwal_guru, jadwal_off, absensi, dokumen, berbagi_dokumen, berbagi_folder, berbagi_sandi, berbagi_tolak (+ `partials/dokumen_penampil.php`, bersama admin) |
| `layouts/pkl.php` (subdomain PKL) | pkl/form, selesai, tutup, lapor, lapor_selesai, lapor_tutup |
| `layouts/biodata.php` (datasiswa.) | biodata/form, selesai, tutup |
| `layouts/public.php` (form guru) | public/form (kesediaan), success, closed, revisi_invalid |
| mandiri (`galat.css`, F10) | errors/html/_galat (404, 400, production) — dipakai publik & admin |

### 3b. Kenapa masih terasa "AI" walau sudah ditenangkan 2026-10-12
1. **Semua bagian kotak putih bergaris yang sama** (kepala kotak + garis + isi) — beranda = 4 kotak kembar; terasa panel admin, tanpa titik fokus.
2. **Tidak ada satu pun gambar asli sekolah** — hanya teks, kotak, dan logo.
3. **Ikon garis tipis + panah › di setiap baris** — ciri paling umum halaman buatan generator Tailwind.
4. Kalimat pola generator: "Informasi akademik {sekolah}", "Jadwal pelajaran, kehadiran guru, dan pengumuman sekolah. Siswa juga dapat…".
5. Hiasan dasbor di halaman umum: titik hijau "● Jam ke-3", bilah angka tipis "Siswa per tingkat".
6. Palet bawaan Tailwind (slate + Inter) tanpa ciri; judul kecil rata kiri di semua tempat; jarak sempit → padat seperti dasbor.
7. Menu disusun menurut fitur sistem (Jadwal Kelas, Jadwal Guru, Absensi, Dokumen, Pengajuan PKL, Form Kesediaan), bukan menurut pengunjung.

### 3c. Masalah teknis
- **Alpine dari CDN dengan versi tidak dikunci** (`alpinejs@3.x.x`) di `public/layout.php` & `biodata/form.php` → bisa berubah/rusak
  sendiri kapan saja; bukan versi CSP. Form PKL memakai Alpine lokal versi biasa (bukan CSP).
- Google Fonts dari luar (4 bingkai publik) — admin sudah font lokal.
- Belum siap CSP: 1 skrip sebaris (`public/form.php`), `style=` di 5 berkas, `onerror`/`onchange` di 5 berkas.
- **Dua mesin Tailwind:** publik v3 (`tailwindcss.exe` lokal, tidak masuk git, + `resources/css/app.css` yang masih memuat kelas hiasan
  lama `rise/reveal/lift/orb/btn-shine`) vs admin v4 (npm). Komponen JS formulir sudah di berkas (`pkl.js`, `pkl-lapor.js`,
  `biodata.js` — `Alpine.data`), tetapi atribut di view masih memakai ekspresi (PKL 100, Biodata 79, Lapor 54 atribut Alpine).

## 4. Konsep baru: layanan per pengguna (tanpa kata "ruang")

### 4a. Peta kelompok pengguna & layanan (satu sumber: `App\Libraries\LayananPublik`)
| Ruang (alamat) | Layanan | Syarat tampil (sudah ada) |
|---|---|---|
| **Siswa** `/siswa` | Ajukan tempat PKL · Lapor diterima PKL · Isi biodata siswa · Jadwal kelas · Pengumuman · Dokumen sekolah | pengaturan PKL buka/tutup · `biodata_open` · `jadwal_publik` · `dokumen_publik` |
| **Guru** `/guru` | Jadwal mengajar · Kehadiran guru hari ini · Formulir kesediaan mengajar · Masuk panel guru & staf | `jadwal_publik` · `absensi_publik` · `form_open` |
| **Orang Tua** `/orang-tua` | Pengumuman · Jadwal kelas anak · Dokumen sekolah · Kontak sekolah | `jadwal_publik` · `dokumen_publik` |
| **Profil Sekolah** `/profil` | Identitas (nama, jenjang, akreditasi, kepala sekolah, alamat, kota, kode pos, telepon, email, situs, tahun pelajaran) + sekolah dalam angka | semua dari Pengaturan Sekolah yang sudah ada |

Formulir yang sedang ditutup (PKL, biodata, kesediaan) tetap tampil di ruangnya dengan label "Sedang ditutup" (pengunjung tahu
layanannya ada); layanan yang dimatikan demi privasi (jadwal/absensi/dokumen publik) disembunyikan seperti sekarang.
Tautan `/siswa` bisa dibagikan ke grup WA siswa: satu tautan berisi semua layanan siswa.

### 4b. Beranda baru (atas → bawah)
1. Kepala: logo + nama sekolah · **kotak cari** "Cari layanan, kelas, atau nama guru" · Bantuan · **Masuk**. HP: logo + tombol menu, kotak cari di baris kedua.
2. **Banner geser** dari Pengumuman bergambar yang dicentang "tampilkan di banner" (P2). Tanpa banner → kotak sambutan tenang:
   logo, nama sekolah, tahun pelajaran, status jam pelajaran saat ini.
3. **"Jelajahi layanan di {nama sekolah}"** — 4 ubin besar (Siswa, Guru, Orang Tua, Profil Sekolah); 4 kolom layar lebar, 2×2 di HP.
4. **Cek jadwal** — status "Saat ini" + pilih kelas → jadwal + tautan jadwal guru (fitur lama tetap).
5. **"Layanan untuk Anda"** — kartu layanan aktif (ikon, judul, satu kalimat).
6. **"Informasi untuk Anda"** — 3 pengumuman terbaru sebagai kartu (gambar bila ada, kategori, judul, "13 Juli 2026 | 15.59 WIB") + "Lihat semua".
7. **"Sekolah dalam angka"** — siswa aktif, guru, kelas, mapel, per tingkat, per jurusan, L/P (angka diam, tanpa animasi).
8. **"Pertanyaan yang sering ditanyakan"** (daftar lipat `<details>`, tanpa JS) + kontak sekolah — tujuan tombol "Bantuan".
9. Kaki gelap: logo, nama + alamat · navigasi ruang · kontak · unduh aplikasi Android (bila tautannya diisi) · © + kredit.

### 4c. Halaman dalam (jadwal, absensi, dokumen, pengumuman, cari)
Remah roti (Beranda › Guru › Jadwal mengajar) · H1 + satu kalimat · saringan dalam kartu · isi di latar abu muda · tombol
ber-ikon (Unduh PDF, Tampilkan). Tabel jadwal tenang: kepala abu, garis tipis, kolom jam terkunci saat digeser di HP.

### 4d. Pencarian `/cari?q=`
Hasil dikelompokkan: Layanan · Kelas (→ jadwal kelas) · Guru (→ jadwal guru; hanya bila jadwal publik, nama yang memang sudah tampil
di jadwal) · Pengumuman · Dokumen publik (bila dibuka). **Tanpa data siswa.** Hasil dibatasi, keluaran di-escape.

## 5. Aturan gaya (wajib di semua fase)
- **Warna:** teks #323232, teks kedua #5e5e5e (di latar abu pakai ini, bukan yang lebih muda — kontras AA); latar putih & #F5F5F5
  bergantian; garis #E5E5E5. **Warna utama = lilac sekolah** (tombol brand-700, sorot brand-800, terpilih brand-50); mint hanya
  untuk penanda "sedang berlangsung". Publik selalu terang + lilac (tidak mengikuti mode Terang/Gelap panel admin; acuan pun tanpa mode gelap).
- **Huruf:** Inter lokal (sudah ada di `public/assets/fonts/inter`). H1 32 px (HP 24), judul bagian 28 px (HP 22) — rata tengah di
  layar lebar, rata kiri di HP; judul kartu 17–19 px; isi 16 px; keterangan 14 px. Semua semibold, sentence case.
- **Bentuk:** kartu sudut 8 px, garis 1 px, latar putih, sorot = latar abu sangat muda (tanpa terangkat/bayangan besar).
- **Ikon:** ikon Flowbite lewat helper `ikon()` yang sudah ada, satu warna lilac. Ubin ruang = ikon besar lilac di kotak lavender —
  satu palet, **bukan** ikon warna-warni.
- **Bahasa:** lugas dan faktual, tanpa slogan, tanpa emoji. Tanggal "13 Juli 2026 | 15.59 WIB".
- **DILARANG:** gradasi, cahaya/bulatan, pola titik, lencana pil bertitik, angka berhitung, animasi muncul, testimoni karangan, kelas lama
  `rise/reveal/lift/orb/btn-shine`.
- **Aksesibilitas:** tautan "Langsung ke konten utama", fokus papan ketik jelas, sasaran sentuh ≥ 44 px, banner berhenti saat
  "kurangi gerakan" + tombol jeda, `alt` gambar dari judul pengumuman.

## 6. Fondasi teknis + siap CSP
- `resources/css/publik.css` (Tailwind v4, mesin npm yang sama dengan admin) → `public/assets/css/publik.css`; skrip
  `npm run build:publik` / `watch:publik`. Berkas terpisah dari admin.css supaya ringan di HP siswa. Hasil build ikut di-commit
  (server cukup `git pull`, tanpa npm).
- Selama peralihan: bingkai yang sudah pindah memakai `publik.css`; bingkai yang belum tetap `app.css` lama. `app.css`, `tailwind.config.js`,
  dan perintah `tailwindcss.exe` dibuang di P7. Komponen lama yang masih dipakai (`.lbl`, `.inp`, `.radio-pill`, `.step-dot`, …) dipindah
  ke `publik.css` dan diberi aturan kompat v3 (warna garis bawaan, dsb.) sampai halamannya dirombak.
- JS: `public/assets/js/vendor/alpine-csp.min.js` (lokal, 3.15.12, sudah ada) + `public/assets/js/publik/*.js` (`Alpine.data`).
  Ekspresi di atribut view diganti getter/metode komponen (pola seri admin). Daftar lipat tanya-jawab pakai `<details>` (tanpa JS).
- Tanpa skrip sebaris, tanpa `style=`, tanpa `on*=`. Logo hilang → server memeriksa berkasnya (pola `partials/favicon.php`) lalu menampilkan huruf awal.
- `php spark dev:cek-ui` diperluas ke view publik (grup baru di `Config\Tampilan`).
- Uji per fase (sesuai arahan user): `php -l`, build, `dev:cek-ui`, paling banyak 1× curl HTTP 200; **user membuka halaman sendiri**
  (Claude memberi daftar halaman + apa yang dicoba). Tanpa uji peramban kecuali diminta.

## 7. Yang tidak berubah / tidak dipakai
- **Logika tidak diubah:** isian & alur formulir PKL/Lapor/Biodata/Kesediaan, CSRF, validasi, nomor, berkas; jadwal & absensi publik
  tetap patuh saklar privasi; data siswa tetap hanya angka agregat.
- Alamat lama tetap hidup (`/`, `/jadwal-kelas`, `/jadwal-guru`, `/absensi`, `/dokumen-publik`, `/d/…`, `/isi`, `/pkl`, `/pkl/lapor`, `/biodata`, subdomain).
- Formulir subdomain (PKL, biodata) tetap **tanpa menu** (siswa datang dari WA, satu tujuan) — hanya gayanya diseragamkan.
- Panel admin & halaman masuk tidak disentuh (selain form Pengumuman di P2). Kredit "Design By" tetap di kaki.
- Dari acuan **tidak dipakai:** logo/nama/ilustrasi/warna Kemendikdasmen, testimoni (harus asli), widget aksesibilitas pihak ketiga,
  tombol App Store (aplikasi sekolah hanya Android).

## 8. Fase pekerjaan

### P0 Analisis & rancangan — ✅ SELESAI 2026-10-15
- [x] Acuan diamati (beranda + halaman ruang + token CSS), 25 view publik diinventaris, penyebab kesan "AI" & masalah teknis dicatat, dokumen ini.

### P1 Fondasi + kerangka portal + pencarian — ✅ SELESAI 2026-10-15
- [x] `resources/css/publik.css` (Tailwind v4) → `public/assets/css/publik.css`, `npm run build:publik`/`watch:publik`: token acuan
      (`text-teks`/`teks-2`/`teks-3`, `bg-permukaan`, `border-garis`, `bg-kaki`, skala `text-display-*`/`text-heading-*`, `shadow-melayang`),
      warna `brand-*` = lilac admin, Inter lokal, kit komponen (kepala, panel layanan, laci, kaki, cari + saran, tombol, bagian, pembuka,
      kartu, ubin, kartu layanan, label, kartu info, angka, tanya-jawab, daftar data, galeri, kosong, peringatan, isian, tabel, paginasi,
      tab tautan, banner, sambutan, pita tentang, aksesibilitas) + blok **KOMPAT ISI LAMA** (dibuang P7).
- [x] Alpine versi CSP lokal + `public/assets/js/publik/portal.js`: `kepalaPortal` (laci HP: Esc, Tab berputar, fokus kembali, tutup saat
      layar melebar; panel "Daftar layanan" tombol kisi, klik luar), `cariPortal` (saran dari `#data-saran-portal`, panah, Enter),
      `bannerGeser`, `aksesibilitas` (cookie `aks_huruf`/`aks_kontras`/`aks_garis` → server menulis `<html data-aks-*>`).
- [x] `App\Libraries\LayananPublik` — kelompok (Siswa/Guru/Orang Tua/Profil Sekolah), 13 layanan + syarat tampil & status buka/tutup,
      `untukKelompok()`, `unggulan()`, `saran()`, `cari()`, `tanyaJawab()`.
- [x] `public/layout.php` + `public/partials/` (kepala, cari, kaki, aksesibilitas, pembuka, kartu_layanan, kontak, tanya_jawab): tautan
      lompat, kepala lengket (logo + nama + tahun pelajaran · kotak cari · Bantuan · Daftar layanan · Masuk), laci HP, kaki arang
      (identitas + alamat + kontak, navigasi, layanan, unduh aplikasi/masuk, © + kredit), tombol Aksesibilitas. Logo hilang → huruf awal
      (dicek server, tanpa `onerror`). Semua halaman portal lama langsung berbingkai baru (isinya dirombak di P4–P5).
- [x] `/cari` (layanan & halaman, kelas → jadwal kelas, guru → jadwal mengajar; tanpa data siswa) + `/bantuan` (tanya-jawab + kontak).
- [x] `dev:cek-ui` mencakup publik: grup `publik-kerangka` P1 … `publik-guru` P7 di `Config\Tampilan`, JS publik dibaca terpisah dari JS
      admin (fungsi global per sisi), cek build `publik.css`, "View tanpa grup" mencakup view publik. *Halaman contoh komponen dipindah
      ke P7 sebagai bagian panduan gaya (acuan Android).*

### P2 Backend isi publik — dipecah dua (migrasi `2026-10-20-000001_TampilanPublik`: tabel `foto_sekolah`, `pengumuman.kategori/gambar`, `settings.profil_singkat/wa_bantuan/tautan_aplikasi`)

#### P2a Foto Sekolah — ✅ SELESAI 2026-10-18
- [x] Migrasi dijalankan di DB lokal laptop (server BELUM — ikut deploy bagian 11).
- [x] Menu admin **Arsip & Info → Foto Sekolah** (`admin/foto-sekolah`, entri `foto_sekolah` di `MenuAdmin`; peran lain "Tidak" sampai
      diberi di Hak Akses): `FotoSekolahModel` + `Admin\FotoSekolah` (tambah/ubah/hapus, judul, keterangan, tautan, urutan, saklar
      banner & aktif), dialog unggah + pratinjau (`assets/js/admin/foto-sekolah.js`). Unggahan lewat helper `labimage` (kini menerima
      folder): format dicek dari isi berkas, maks 5 MB, diperkecil ke 1920 px, disimpan WEBP di `public/uploads/foto-sekolah/`
      (folder dibuat otomatis); ganti/hapus foto ikut menghapus berkas lama.
- [x] Publik: foto "Tampilkan di banner" = slide pertama banner beranda (foto penuh + keterangan kiri bawah + tautan "Selengkapnya");
      foto utama (`FotoSekolahModel::utama`: foto pertama yang bukan banner, atau foto pertama) di **Tentang sekolah** (kotak angka
      pindah ke satu baris) dan lebar di pembuka **Profil**; galeri semua foto di Profil. Tanpa foto → tampilan lama tetap. Tabel belum
      ada (kode ditarik sebelum migrate) → beranda tetap tampil.
- [x] Uji: `dev:cek-ui` grup sistem + P1–P4 = 0; uji HTTP alur penuh dengan akun uji sementara (masuk, tolak tanpa berkas, unggah →
      WEBP, beranda/Profil, matikan banner, hapus → berkas hilang) lulus; data uji dibersihkan.

#### P2b Pengumuman bergambar + isian Pengaturan — ✅ SELESAI 2026-10-18
- [x] Admin Pengumuman: kolom Kategori (`PengumumanModel::KATEGORI` Pengumuman/Berita/Agenda/Prestasi, kategori lain ditolak) +
      gambar opsional (helper `labimage` folder `pengumuman`, 1600 px WEBP, pratinjau; saat mengubah: unggah baru = ganti,
      centang "Hapus gambar" = tanpa gambar; berkas lama ikut dihapus; hapus pengumuman ikut menghapus gambar). API Android tidak
      berubah (tidak mengirim kategori → bawaan DB "Pengumuman").
- [x] Publik: label kategori + gambar di kartu "Informasi untuk Anda", `/pengumuman`, `/pengumuman/{id}`; pengumuman terbaru yang
      bergambar menjadi slide foto "Pengumuman terbaru" di banner (tanpa gambar → kotak sorotan seperti sebelumnya).
- [x] Pengaturan Sekolah → kartu baru **Situs publik**: Profil singkat (maks 1.000 huruf), Nomor WhatsApp bantuan (9–15 angka, dicek
      server), Tautan unduh aplikasi Android (https:// ditambahkan bila lupa, dicek server) — dicek sebelum logo dipindahkan.
- [x] Uji: `dev:cek-ui` sistem + P1–P4 = 0; uji HTTP 14/14 (akun uji sementara; Pengaturan diuji tanpa menimpa data sekolah, nilai
      asli dikembalikan); data uji dibersihkan.

### P3 Halaman per pengguna + Profil Sekolah — ✅ SELESAI 2026-10-15 (lokal)
- [x] `/siswa`, `/guru`, `/orang-tua` (`public/kelompok.php`; tanpa kata "Ruang" — arahan user) pola 2b: pembuka + "Layanan yang
      tersedia" berkelompok, label "Sedang ditutup".
- [x] `/profil` (identitas sekolah dari Pengaturan + sekolah dalam angka/grafik + kontak); `/pengumuman` + `/pengumuman/{id}`.

### P4 Beranda baru — ✅ SELESAI 2026-10-15/16 (lokal)
- [x] Banner geser (`bannerGeser` di `publik/portal.js`: geser, titik, jeda, berhenti saat kurangi gerakan), Jelajahi layanan (ubin
      ilustrasi HD), Tentang sekolah + angka + grafik (`partials/grafik_sekolah.php`), Layanan unggulan, Cek jadwal, Informasi untuk
      Anda, tanya-jawab + Ke Pusat Bantuan; laci "Daftar layanan" (sheet) seperti acuan; 16 ilustrasi SVG di `public/assets/img/ilustrasi/`.
- [x] Semua isi beranda lama tetap ada (status jam, pilih kelas, layanan, pengumuman, data sekolah).

### P5 Halaman informasi — ✅ SELESAI 2026-10-18
- [x] Pola 4c di semua halaman: pembuka (remah Siswa/Guru/Orang Tua › halaman, ilustrasi, H1, satu kalimat) + isi latar abu.
      Jadwal kelas & jadwal mengajar: saringan dalam kartu (pilih → langsung tampil lewat `[data-kirim-otomatis]` di `portal.js`,
      pengganti `onchange` sebaris; tombol Tampilkan bila JS mati) + tautan silang kelas↔guru, tabel `.jadwal` (kolom jam terkunci saat
      digeser di HP, kolom hari ini lilac + "Hari ini", istirahat kuning), Unduh PDF; jadwal guru tetap satu tabel per shift.
- [x] Kehadiran guru: kartu saringan tanggal + ringkasan 5 status (`.ringkas-grid`), Kehadiran kerja, absensi mengajar per guru;
      satu label per status (hadir hijau, telat kuning, izin lilac, sakit pink, tidak hadir merah).
- [x] Halaman "belum dibuka untuk umum" (`jadwal_off`) kini menyebut halamannya: `Publik::blokir($setting, 'Kehadiran guru')`.
- [x] Dokumen sekolah + folder berbagi: partial bersama `public/partials/baris_berkas.php` (ikon jenis berkas satu warna, bukan label
      pelangi; Lihat/Unduh/Buka). Dokumen dibagikan: pembuka + tombol Unduh + peringatan "dibaca saja"; dokumen terkunci & tautan
      ditolak = `.kotak-pesan`.
- [x] `partials/dokumen_penampil.php` (ternyata hanya dipakai halaman berbagi publik; admin punya `admin/dokumen/_penampil.php`):
      ikon kit, latar `.penampil-gelap`, tab lembar Excel `aria-pressed`, alamat YouTube di `$urlYoutube` (frame-src sudah
      mengizinkan). **Perbaikan:** gaya isi Word/Excel `.dok-pratinjau` sebelumnya hanya ada di admin.css → kini juga di publik.css.
- [x] Uji: `dev:cek-ui` P5 = 0 di semua kolom (dari 205 temuan); HTTP 9/9 (jadwal kelas/guru + PDF, absensi, dokumen publik dinyalakan
      sebentar, tautan palsu, saklar dimatikan sebentar → pesan benar, saklar dikembalikan) + 4/4 dengan data uji sementara (absensi
      tercatat, berbagi dokumen YouTube, folder, bersandi); data uji dihapus.

### P6 Formulir siswa (PKL + Biodata) — (besar, risiko tertinggi: dipakai siswa setiap hari) — dipecah 3 (2026-10-18)
Bingkai baru bersama **`layouts/formulir.php`** (publik.css, font lokal, Alpine CSP lokal, tanpa menu) + **`partials/kop_formulir.php`**
(logo dicek server, tanpa onerror). Halaman yang belum dirombak tetap di `layouts/pkl.php` / `layouts/biodata.php` / `partials/kop_form.php`
(dibuang di P7). Kit formulir di publik.css: `.formulir`, `.kop-formulir*`, `.langkah-*` (bilah bersegmen, tanpa style=), `.kartu-langkah*`,
`.daftar-pilih`/`.pilih-item[aria-pressed]`, `.pilih-cek`, `.formulir-nav`, `.pemutar`, `.tanda-wajib/opsional`.

#### P6a Pengajuan PKL — ✅ SELESAI 2026-10-18 (tunggu user mencoba alur penuh)
- [x] `pkl/form.php`, `pkl/selesai.php`, `pkl/tutup.php`, `pkl/_header.php` → bingkai & kop baru; Alpine CSP (pengganti alpine.min.js biasa).
- [x] Alur, isian, kunci galat, draf (`pkl_draft_v2_`), dan logika `pkl.js` TIDAK berubah. Perubahan `pkl.js` hanya: kelas label status →
      kit (`label-merek/buka/kuning/merah`) + metode `lokasiSaran(s)` & `infoTeman(t)` pengganti ekspresi ber-fungsi global (Boolean/String).
      Pesan non-isian (daftar nama, buka ajuan, pemilih teman) sengaja TIDAK berkelas `err-msg` (dipakai pkl.js untuk lompat ke galat pertama).
- [x] Uji: `dev:cek-ui` berkas P6a = 0; uji peramban Chrome tanpa layar (skrip scratchpad `uji_pkl_wizard.mjs`) di 360/390/1280 px:
      28/28 — pilih kelas → nama (aria-pressed) → konfirmasi → galat langkah 2 → saran perusahaan (data contoh disuntik ke komponen,
      DB laptop belum punya perusahaan disetujui) → tambah teman + galat HP teman → kontak → ringkasan → Kirim TANPA centang = galat &
      **tanpa POST ke /pkl/kirim**; tanpa galat JS & tanpa pelanggaran CSP; tak melebar. Halaman tutup diuji (form_buka dimatikan sebentar,
      dikembalikan). Halaman selesai TIDAK teruji Claude (butuh kirim sungguhan) → dicoba user.

#### P6b Lapor diterima PKL — ✅ SELESAI 2026-10-18
- [x] `pkl/lapor.php`, `lapor_selesai.php`, `lapor_tutup.php` + kop bersama baru `pkl/_kop_lapor.php` → bingkai `layouts/formulir.php` + Alpine CSP.
      `pkl-lapor.js` logika tetap; hanya kelas label status → kit + metode `bolehBuka()` & `anggotaKelompok()` (pengganti array langsung
      di `x-if`/`x-for`). `layouts/pkl.php` kini TIDAK dipakai lagi (dibuang di P7).
- [x] Uji: berkas `pkl/` = 0 temuan `dev:cek-ui`; uji peramban `uji_lapor_wizard.mjs` 15/15 di 360 & 1280 px dengan data sementara
      (`data_uji_lapor.php buat|hapus`: 1 ajuan disetujui) — pilih kelas → nama "Bisa lapor" → HP salah ditolak → HP benar → tahap 2
      (perusahaan + anggota) → Kirim tanpa tanggal = galat & **tanpa POST** → lama PKL → Kembali; tanpa galat JS/CSP. Halaman ditutup diuji
      (`lapor_buka` dimatikan sebentar, dikembalikan). Data sementara dihapus (0 ajuan seperti semula). `lapor_selesai` dicoba user.

#### P6c Biodata siswa — ✅ SELESAI 2026-10-18
- [x] `biodata/form.php`, `selesai.php`, `tutup.php`, `_header.php` → bingkai `layouts/formulir.php` + kop baru; Alpine CDN
      `alpinejs@3.x.x` (versi tidak dikunci) → Alpine CSP lokal. `biodata.js` logika tetap; hanya kelas label status → kit + metode
      `jumlahAngkaNisn()` (penghitung NISN memakai REGEX di atribut — tidak tertangkap `dev:cek-ui`, pasti gagal di versi CSP).
      Kit baru publik.css: `.pilihan-tombol` (radio), `select.inp-sempit` (tgl/bulan/tahun), `.munculan*` (pop-up KK, tanpa x-transition).
- [x] Uji: berkas `biodata/` = 0 temuan; uji peramban `uji_biodata_wizard.mjs` 24/24 (360 px) & 25/25 (1280 px): kelas → nama → pop-up KK
      (terkunci sebelum centang, "Nanti dulu", buka-ulang segera) → galat langkah 2 → NISN 10/10 → pilihan tombol → alamat → pekerjaan
      "Lainnya" → alamat ortu sama (bawaan tercentang) → tanpa wali → ringkasan → Kirim tanpa centang = galat & **tanpa POST**; tanpa galat
      JS/CSP. Halaman ditutup diuji (`biodata_open` dimatikan sebentar, dikembalikan). `layouts/biodata.php` & `layouts/pkl.php` kini TIDAK
      dipakai; `partials/kop_form.php` tinggal dipakai form kesediaan (P7) — ketiganya dibuang di P7 (sisa temuan grup P6 = berkas lama ini).

### P7 Form Kesediaan Guru + halaman galat + penutup — (sedang)
- [ ] `layouts/public.php` + form kesediaan (skrip sebaris → berkas), terima kasih, tutup, revisi.
- [ ] Halaman galat disamakan dengan gaya portal (tetap mandiri, tanpa DB).
- [ ] Buang `app.css` v3, `tailwind.config.js`, kelas hiasan lama, semua CDN; cek siap CSP = 0, kontras AA, HP 360 px.
- [ ] `docs/PANDUAN-DEPLOY-PUBLIK.md` + catatan memori.

## 9. Keputusan untuk user (saran bawaan dipakai bila tidak dijawab)
1. **Warna kaki halaman:** arang gelap seperti acuan *(saran)* · atau ungu tua sekolah seperti sekarang.
2. **Banner beranda dari menu Pengumuman** (unggah gambar + centang "tampilkan di banner") *(saran: ya)*. Sekolah perlu menyiapkan
   2–3 foto/poster asli, ukuran ±1600 × 500 px. Tanpa gambar, beranda tetap rapi (kotak sambutan).
3. **Tambah 2 isian di Pengaturan Sekolah:** "Profil singkat sekolah" & "Tautan unduh aplikasi Android" *(saran: ya; kosong = tidak tampil)*.
   Bila aplikasi ada di Play Store, kirim tautannya.
4. **Contoh tampilan beranda (gambar halaman) sebelum P1** untuk dinilai dulu arahnya *(saran: ya — satu halaman contoh, kode situs belum diubah)*.

## 10. Risiko & cara aman
| Risiko | Cara aman |
|---|---|
| Formulir siswa rusak saat pindah ke Alpine CSP | logika JS tidak diubah, hanya cara pemanggilan; dikerjakan paling akhir (P6) setelah pola matang; user mencoba alur penuh |
| Beda kelas Tailwind v3 → v4 | kompat v3 di `publik.css` selama peralihan; tiap halaman dicek saat fasenya |
| Tidak ada foto asli | kotak sambutan tenang; banner muncul otomatis saat Admin mengunggah |
| Unggah gambar disalahgunakan | hanya jpg/png/webp, cek isi berkas, maks 2 MB, nama acak, hanya Admin/peran yang berhak mengelola Pengumuman |
| Kesan meniru situs pemerintah | hanya pola susunan; identitas, logo, warna, gambar milik sekolah |

## 11. Deploy (setelah P7)
`git switch main` → `git merge upgrade-tampilan` → `git push` → server: `git pull` (bila `public/.htaccess` berubah: stash → pull → stash pop,
lihat PANDUAN-DEPLOY-TAMPILAN.md) → `php spark migrate` (P2 pengumuman; P3 bila keputusan 3 = ya) → Ctrl+F5. Folder unggahan
gambar pengumuman dibuat otomatis oleh kode.
