# Rencana Fitur: Manajemen Dokumen (SIMDOK)

Modul penyimpanan & berbagi dokumen untuk **Wakil Kepala Sekolah bidang
Kesiswaan** (klien utama pemakai aplikasi). Diminta 2026-09-19, status
**URGEN** — pekerjaan lain ditunda (lihat memori `backlog-pekerjaan-tertunda`).

## Permintaan user (apa adanya)

> "dia butuh fitur untuk simpan file file dokumen, misalnya word, ppt, excel
> dll, misal ada file dia save terus dia bisa buka kapanpun, dan di aplikasi
> dia bisa baca langsung dokumennya, entah itu pdf word excel dll, bisa
> download juga bisa upload juga bisa bagikan juga ke guru guru lain lewat
> link. versi android dan web, fleksibel bisa di-share (public) atau privat."

Catatan penting: **guru tidak punya akun login** di sistem ini (guru = data
master, bukan user). Jadi "bagikan ke guru lain" = **tautan berbagi**, bukan
akun per guru. Ini justru menyederhanakan rancangan.

---

## Keputusan user (2026-09-19)

1. **TANPA video di server** — video cukup ditempel sebagai **tautan**
   YouTube/Google Drive. Alasan: hemat disk shared hosting; satu video
   kegiatan bisa ratusan MB sementara dokumen kerja cuma hitungan MB.
   (Catatan: alert inode yang pernah tercatat itu **akun hosting LAMA
   (zulfiqri)**, BUKAN hosting kangmuslim — hosting muslimin aman.)
   Konsekuensi: unggah berkas video DITOLAK
   dengan pesan ramah + diarahkan ke tombol "Tambah Tautan Video".
   **Tetap dibuat sebagai SAKLAR di Pengaturan** (`dok_izinkan_video`,
   default mati) supaya kalau nanti pindah hosting tinggal dinyalakan,
   bukan tulis ulang.
2. **Hak akses: semua admin yang login boleh lihat dokumen privat.** Tidak
   membangun sistem role (kolom `admins.role` tetap tak dipakai). "Privat"
   berarti tidak bocor ke internet — bukan saling sembunyi antar admin.
3. **Pratinjau Office: render lokal + Office Viewer khusus berkas publik.**
   Word (mammoth.js) & Excel (SheetJS) dirender di aplikasi sendiri sehingga
   berkas privat TIDAK PERNAH keluar ke pihak ketiga. Tombol "Buka di Office
   Viewer" (Microsoft) hanya muncul untuk berkas bervisibilitas `publik`/`link`,
   karena layanan itu menuntut URL yang bisa diakses dari internet.
4. **Struktur folder bertingkat ala Google Drive** (self-FK `parent_id` +
   breadcrumb), bukan kategori datar.

---

## Batas jujur yang harus dipahami (jangan dijanjikan lebih)

Browser tidak punya mesin render Word/PPT/Excel. Yang benar-benar bisa
dibaca langsung di dalam aplikasi:

| Tipe | Web | Android |
|---|---|---|
| PDF | penuh | penuh |
| JPG/PNG/WEBP/GIF/BMP | ya | ya |
| Teks/CSV/MD/kode | ya | ya |
| Excel (xlsx/xls) | tabel via SheetJS — isi utuh, format/warna hilang | buka via app HP |
| Word (docx) | HTML via mammoth.js — isi utuh, layout mendekati | buka via app HP |
| PowerPoint (pptx) | tak ada renderer → unduh / Office Viewer (bila publik) | buka via app HP |
| HEIC (foto iPhone) | dari HP aman (dikonversi di perangkat); dari desktop butuh Imagick yang server kemungkinan tak punya | ya |
| Video | disimpan sebagai TAUTAN (keputusan #1) | idem |
| Zip/rar/apa pun | simpan & unduh | ya |

Di Android justru lebih lapang: `open_filex` (sudah terpasang) membuka
Word/PPT lewat WPS/Google Docs yang umumnya sudah ada di HP.

**Aturan emas: modul ini TIDAK PERNAH menolak jenis berkas** (kecuali video
sesuai keputusan #1). Tipe tak dikenal tetap tersimpan & bisa diunduh —
yang berbeda hanya ada/tidaknya pratinjau.

---

## Rancangan penyimpanan

- Berkas disimpan di **`writable/uploads/dokumen/YYYY/MM/`** — DI LUAR
  webroot, sudah terlindung `writable/.htaccess` (`Require all denied`).
  **JANGAN** pakai `public/uploads/` seperti modul lama: apa pun di sana
  bisa dibuka siapa saja yang tahu URL-nya, dan modul ini memegang data
  siswa.
- Nama berkas di disk **diacak** (`uniqid`), nama asli disimpan di DB.
- Sharding per tahun/bulan supaya satu folder tak menumpuk ribuan entri.
- MIME dideteksi dari **ISI berkas** (`finfo` via `$file->getMimeType()`),
  BUKAN `getClientMimeType()`/`getExtension()` — pelajaran dari bug foto
  profil 2026-07-05 (paket `http` Flutter mengirim `application/octet-stream`).
- Setiap akses berkas melewati controller yang mengecek izin lebih dulu.

---

## ERD ringkas (4 tabel baru)

```
admins --+--< dokumen_folder --+   (parent_id self-FK, bertingkat)
         |          |          |
         |          +--< dokumen >--< dokumen_share >--< dokumen_akses_log
         +---------------------+
```

### Kolom inti per tabel

**`dokumen_folder`** — id, nama, `parent_id` (self-FK, folder bertingkat),
deskripsi, `visibilitas` ENUM(privat/link/publik), warna, created_by,
timestamps + softDelete.

**`dokumen`** — id, `folder_id` (NULL = akar), judul, deskripsi,
**`tipe`** ENUM(berkas/tautan), nama_asli, nama_file (acak, di disk),
path_rel (`2026/09/xxx.pdf`), ekstensi, mime, ukuran,
`kategori` ENUM(pdf/dokumen/spreadsheet/presentasi/gambar/audio/video/arsip/lainnya),
`hash_sha256` (integritas + deteksi duplikat), thumb,
`url_eksternal` + `penyedia` (youtube/drive/lainnya — untuk tipe tautan),
`visibilitas` ENUM(privat/link/publik), jml_lihat, jml_unduh, created_by,
timestamps + softDelete (= tempat sampah).

**`dokumen_share`** — id, dokumen_id NULL, folder_id NULL (salah satu wajib,
divalidasi di model), **`token`** VARCHAR(64) UNIQUE, password_hash NULL,
expired_at NULL, boleh_unduh, maks_unduh NULL, jml_akses, catatan,
aktif, created_by, timestamps.

**`dokumen_akses_log`** — id BIGINT, dokumen_id, share_id, `aksi`
ENUM(lihat/pratinjau/unduh), admin_id NULL (NULL = tamu dari tautan),
ip, user_agent, created_at.

**Tambahan kolom `settings`** (pola migrasi `AddAbsensiPublik`):
`dok_maks_mb` (default 25), `dok_izinkan_video` (default 0),
`dok_kuota_mb` (default 2048), `dokumen_publik` (saklar halaman publik).

---

## Catatan arsitektur

- **Rute hapus pakai POST, bukan GET.** Modul lama memakai GET untuk hapus
  (tercatat sebagai utang SEC1 di memori `web-upgrade-backlog`). Modul baru
  TIDAK ikut menambah utang itu.
- Ikut konvensi wajib repo: help card `admin/partials/help` di tiap halaman
  (+ naikkan `helpKey` tiap kali berubah), `master_data_changed()` tiap
  mutasi, rebuild Tailwind tiap tambah kelas, audit log tiap aksi.
- Controller folder/berkas = pola **workflow** (seperti `Admin\Peminjaman`),
  BUKAN `BaseMaster` — karena bukan tabel master datar ber-import/export.
- Claude TIDAK commit — user pegang git sendiri.

---

## Fase kerja

- [x] **D0 — Diagnostik hosting** ✅ SKRIP SIAP & TERUJI LOKAL 2026-09-19
      Berkas **`public/cek-hosting.php`** (berdiri sendiri, tak memuat CI4,
      dikunci `?kunci=d0-muslimin-2026`, balas 404 tanpa kunci). Dibuka lewat
      BROWSER di hosting — bukan CLI, karena konfigurasi PHP web berbeda dari
      CLI dan yang menentukan batas unggah adalah yang web. Isinya: batas
      unggah (`upload_max_filesize`/`post_max_size`/`memory_limit`/
      `max_execution_time`/`max_input_time`/`max_file_uploads`) + **batas
      nyata** = yang terkecil di antara keduanya; ekstensi (`fileinfo`/`gd`/
      `exif`/`zip`/`curl`/`imagick` + dukungan WebP/AVIF/HEIC); sisa disk;
      **uji tulis** ke `writable/uploads/dokumen/YYYY/MM/`; **uji keamanan
      kanari** (taruh berkas berisi teks rahasia di `writable/`, coba buka
      dari internet lewat 2 jalur, lapor BOCOR bila HTTP 200 + isi terbaca,
      lalu hapus); **form uji unggah nyata** (deteksi MIME dari isi berkas +
      `move_uploaded_file` ke folder tujuan, berkas uji langsung dihapus);
      deteksi `post_max_size` terlampaui (POST masuk tapi `$_FILES`+`$_POST`
      kosong); kesimpulan otomatis MASALAH/CATATAN + **saran angka
      `dok_maks_mb`**; kotak ringkasan teks siap salin.
      **Hasil baseline LOKAL (XAMPP):** PHP 8.2, upload 100M/post 100M,
      memory 512M, fileinfo+gd+exif+zip+curl ADA, **imagick TIDAK ADA**
      (HEIC tak bisa dikonversi di server — sesuai dugaan), GD WebP+AVIF YA,
      tulis folder dokumen BISA, proteksi `writable/` **aman (404 & 403)**,
      uji unggah PDF OK & MIME terbaca `application/pdf` dari isi.
      **SISA: jalankan di hosting kangmuslim.com** → angkanya mengunci
      `dok_maks_mb`. Setelah itu **HAPUS `public/cek-hosting.php`**.

- [x] **D1 — Fondasi DB & Model** ✅ SELESAI & TERUJI 2026-09-19 (103/103 lulus)
      Migrasi `2026-09-19-000001_CreateDokumen` (4 tabel, InnoDB utf8mb4) —
      **reversible, diuji rollback lalu migrate ulang**; 10 foreign key
      terpasang termasuk self-FK `dokumen_folder.parent_id` (ditambah lewat
      ALTER terpisah, **SET NULL bukan CASCADE** karena InnoDB punya
      keanehan pada cascade self-referensial; penghapusan bertingkat
      ditangani eksplisit di controller). Migrasi
      `2026-09-19-000002_AddDokumenSettings` (4 kolom `settings` +
      `SettingModel::$allowedFields` diperbarui).
      **4 model:** `DokumenFolderModel` (pohon: `anak`/`jejak` breadcrumb/
      `keturunan`/`akanMelingkar` penolak pindah-ke-dalam-diri-sendiri/
      `optionsBerjenjang`; `jejak()` tahan rantai melingkar), `DokumenModel`
      (`daftar`/`cariSemua` + filter & **daftar putih kolom urut**,
      `detail`, `sampah`, `tambahHitung`, `pemakaian`, `serupa` deteksi
      berkas kembar via hash), `DokumenShareModel` (`tokenBaru`,
      `periksa()` → 4 alasan tolak: tidak_ada/dicabut/kedaluwarsa/habis,
      sandi via `password_hash`), `DokumenAksesLogModel` (`catat()` tak
      pernah menggagalkan unduhan, urut `created_at,id` agar deterministik).
      **Helper `app/Helpers/dokumen_helper.php`:** `dokumen_root()` (+
      pasang `.htaccess` penolak sebagai lapis kedua), `dokumen_dir_bulan()`,
      **`dokumen_path()` penjaga path traversal** (realpath + wajib di dalam
      root), `dokumen_save()` (MIME dari isi, SHA-256 dihitung sebelum
      pindah, nama disk diacak, tolak video bila saklar mati),
      `dokumen_kategori()` (**ekstensi didahulukan** — finfo membaca
      docx/xlsx/pptx sebagai `application/zip`, jadi isi saja tak cukup),
      `dokumen_thumb_buat()` (Imagick → GD, perbaiki orientasi EXIF, WEBP
      q80, gagal thumbnail ≠ gagal unggah), `dokumen_penyedia()`/
      `dokumen_youtube_id()` untuk tautan.
      Diuji lewat `php spark dev:uji-dokumen` (`app/Commands/UjiDokumen.php`,
      disimpan sebagai uji regresi modul).

- [x] **D2 — Mesin penyajian berkas** ✅ SELESAI & TERUJI e2e 2026-09-19
      **`App\Libraries\DokumenStream`** (library, BUKAN di controller —
      supaya web, halaman berbagi publik D5, dan API D7 berperilaku persis
      sama; pola `LabReport`). Isinya: HTTP **Range** (206 + `Content-Range`,
      bentuk `bytes=a-b` & `bytes=-n`, di luar jangkauan → **416**),
      **ETag/Last-Modified → 304**, kirim berpotongan `fread` 8 KB + `flush`
      + berhenti saat `connection_aborted` (JANGAN `file_get_contents`),
      `ob_end_clean` semua buffer sebelum mengirim, `Content-Disposition`
      dengan nama ASCII + RFC 5987 (nama Indonesia/emoji aman), `HEAD`
      dilayani tanpa badan.
      **Pengamanan tipe (penting):** hanya **daftar putih** `INLINE_AMAN`
      yang boleh tampil di browser. HTML/SVG/XML dipaksa jadi unduhan —
      kalau tidak, skrip di dalam berkas unggahan berjalan DI DOMAIN SEKOLAH
      dan bisa mencuri sesi admin. Ditambah `X-Content-Type-Options: nosniff`
      + `Content-Security-Policy: default-src 'none'; … sandbox`.
      **`Admin\DokumenFile`** (tipis): `lihat`/`unduh`/`thumb`, rute
      `admin/dokumen/{berkas,unduh,thumb}/(:num)` didaftarkan `match(['get','head'])`
      di grup ber-filter `auth`.
      **Penghitung akurat:** pencatatan dipasang lewat callback `onKirim`
      yang HANYA jalan saat berkas benar-benar terkirim utuh — HEAD, 304,
      dan potongan Range tidak ikut terhitung (sebelum diperbaiki, 6
      permintaan terhitung 4 "dilihat"; sesudahnya tepat 1).
      **Hasil uji e2e:** tanpa login → 302 ke login (0 byte); inline PDF
      200 + header lengkap; unduh → attachment; Range `0-99` & `-50`
      byte-exact vs sumber; 416 benar; 304 benar; HEAD 200 tanpa badan;
      **berkas .html jahat dipaksa attachment**; **path traversal
      `../../../.env` (dan versi backslash) → 404, `.env` TIDAK bocor**;
      dokumen tipe tautan → 302 ke YouTube; thumbnail WEBP 480x320 tersaji;
      **berkas 30 MB terunduh utuh (sha256 cocok) dalam 0,18 dtk** dan
      lanjut-unduhan dari byte ke-31.457.180 cocok. Data uji dibersihkan.

- [x] **D3 — Web: jelajah, unggah, kelola** ✅ SELESAI & TERUJI e2e 2026-09-20
      `Admin\Dokumen` (pola workflow) + view `admin/dokumen/index.php` &
      `sampah.php` + 13 rute (**semua mutasi POST**) + grup sidebar
      **DOKUMEN** + help card (`dokumen`, `dokumen_sampah`) + audit log +
      Tailwind di-rebuild.
      Isi: breadcrumb folder bertingkat, kartu subfolder dengan jumlah
      berkas, unggah banyak berkas sekaligus (seret-lepas), tambah tautan
      eksternal, ubah judul/keterangan/akses, pindah massal, pilih banyak
      (grid & daftar), tempat sampah + pulihkan + hapus permanen +
      kosongkan, pencarian **lintas folder**, filter jenis, urut
      nama/tanggal/ukuran/paling-sering-diunduh, tampilan grid & daftar,
      bar pemakaian penyimpanan.
      **Aturan sampah yang dipilih:** menghapus folder membuang folder itu
      beserta SELURUH isinya, dan semua baris yang terbawa diberi stempel
      `deleted_at` yang SAMA PERSIS. Saat folder dipulihkan, hanya baris
      berstempel itu yang ikut kembali — jadi berkas yang sudah lebih dulu
      ada di sampah tidak ikut terbawa naik. Dokumen yang dipulihkan
      sementara folder asalnya masih di sampah ditaruh di folder utama
      (disertai pesan), bukan "hilang" di folder tak terlihat.
      **Hasil uji e2e:** buat folder + subfolder; unggah 6 berkas sekaligus
      → **docx/xlsx/pptx yang oleh finfo terbaca `application/zip` tetap
      dikenali benar** (dokumen/spreadsheet/presentasi), PNG dapat
      thumbnail otomatis, **MP4 ditolak** dengan pesan mengarahkan ke
      tombol Tambah Tautan; **berkas kembar terdeteksi** lewat hash dan
      dilaporkan; tautan YouTube tersimpan (penyedia terdeteksi), URL tak
      sah ditolak; ubah & pindah dokumen; hapus folder → 6 dokumen + folder
      satu stempel; pulihkan dokumen tunggal → naik ke folder utama +
      pesan; pulihkan folder → tepat isinya yang kembali; **hapus permanen
      membuang berkas + thumbnail dari disk** (sampah tidak);
      kosongkan sampah; **pindah folder ke dalam anaknya sendiri ditolak**;
      unggah tanpa berkas → pesan ramah yang juga menyinggung kemungkinan
      berkas terlalu besar. Data & berkas uji dibersihkan.
      **Catatan verifikasi tampilan:** tangkapan layar diambil dari HTML
      tersimpan (halaman butuh sesi login) — tata letak & gaya cocok dengan
      halaman lama; **lebar HP belum bisa diverifikasi otomatis** (headless
      tak mereflow ke 390px, sama seperti catatan di modul Jabatan/Siswa),
      jadi tampilan ponsel perlu dicek manual sekali di perangkat.

- [x] **D4 — Web: pratinjau di dalam aplikasi** ✅ SELESAI & TERUJI 2026-09-20
      `Admin\Dokumen::pratinjau($id)` + view `admin/dokumen/pratinjau.php` +
      rute `admin/dokumen/pratinjau/(:num)`; kartu & baris di penjelajah
      kini menuju halaman ini (bukan langsung ke berkas mentah).
      Penampil per jenis: **PDF** (iframe `#view=FitH`), **gambar** (latar
      gelap, muat-layar), **audio/video** (pemutar bawaan), **teks/MD**
      (aman dari HTML), **CSV/XLSX** (SheetJS + tab antar-lembar),
      **DOCX** (mammoth.js). **PPTX & tipe tak dikenal** → kartu "unduh
      untuk membuka" yang jujur. **HEIC & SVG** dapat penjelasan sendiri
      (SVG sengaja tak ditampilkan karena bisa memuat skrip).
      Panel kanan: nama berkas asli, ukuran, jenis, akses, folder,
      pengunggah, penghitung dibuka/diunduh, keterangan.
      **Pustaka di-VENDOR LOKAL** (`assets/js/vendor/xlsx.full.min.js` 862 KB
      & `mammoth.browser.min.js` 628 KB) mengikuti kebiasaan repo yang juga
      menyimpan Alpine sendiri — sekaligus memastikan **berkas privat tak
      pernah dikirim ke layanan pihak ketiga** karena seluruh pembacaan
      terjadi di peramban. Keduanya hanya dimuat di halaman pratinjau, dan
      hanya untuk jenis yang memerlukannya.
      Gaya hasil render ditambahkan sebagai `.dok-pratinjau` di
      `resources/css/app.css` (SheetJS & mammoth menghasilkan HTML polos).
      **Perbaikan pada D2 yang ketahuan di sini:** arahan CSP `sandbox`
      dipindah agar HANYA dipasang pada berkas yang diunduh — kalau ikut
      terpasang saat inline, penampil PDF bawaan peramban ikut lumpuh.
      **Hasil uji:** keempat jenis memuat penampil yang benar (docx→mammoth,
      xlsx→SheetJS, pdf→iframe, png→img), tanpa error PHP. Render Word &
      Excel **diverifikasi lewat tangkapan layar sungguhan**: judul,
      paragraf, dan teks tebal Word tampil benar; Excel menampilkan nama
      kedua lembar + tabel berbaris rapi. Tombol **Office Viewer tidak
      muncul saat berkas privat** dan muncul beserta peringatan privasi
      saat berkas dijadikan publik. Data & berkas uji dibersihkan.

- [x] **D5 — Berbagi** ✅ SELESAI & TERUJI e2e 2026-09-20
      **`App\Controllers\Berbagi`** (tanpa login) + 8 rute publik
      (`d/(:segment)`, `/buka`, `/berkas`, `/unduh`, varian `+/(:num)` untuk
      isi folder, `dokumen-publik`, `dokumen-publik/(:num)/{berkas,unduh}`).
      Sisi admin: `bagikan`, `bagikanFolder`, `cabutShare` + panel tautan di
      halaman pratinjau (salin, **kirim WhatsApp**, coba buka, cabut, status
      AKTIF/DICABUT/KEDALUWARSA/JATAH HABIS) + tombol bagikan di kartu folder.
      Opsi tiap tautan: kedaluwarsa, kata sandi, boleh-unduh, batas unduhan,
      catatan internal.
      **Penampil dipakai bersama:** blok penampil D4 diekstrak ke
      `app/Views/partials/dokumen_penampil.php` supaya guru yang membuka
      tautan melihat persis apa yang dilihat admin (satu sumber kode, tak
      bisa berbeda perilaku). Halaman publik: `berbagi_dokumen`,
      `berbagi_folder`, `berbagi_sandi`, `berbagi_tolak`, `dokumen`.
      **Dokumen privat yang dibagikan otomatis naik ke `link`** — kalau tidak,
      tautan yang baru dibuat justru tak bisa dibuka penerimanya.
      Pengaturan modul ditambahkan ke halaman **Pengaturan Sekolah**
      (`dok_maks_mb` dijepit 1–200, `dok_kuota_mb` min 100,
      `dok_izinkan_video`, `dokumen_publik`).
      **Hasil uji keamanan e2e (semua lulus):** tamu tanpa login bisa buka &
      unduh lewat tautan sah; **token ngawur → 404**; **dokumen lain tidak
      bisa diambil lewat token dokumen ini → 404**; **berkas di luar folder
      yang dibagikan → 404**; tautan dicabut/kedaluwarsa/jatah habis ditolak
      dengan pesan masing-masing; `boleh_unduh=0` → lihat 200 tapi unduh
      ditolak; tautan bersandi: akses berkas langsung tanpa sandi dialihkan
      ke gerbang sandi, sandi salah ditolak, sandi benar membuka;
      **5× salah → dikunci 15 menit** (memakai `LoginThrottle` yang sama
      dengan login admin); halaman `dokumen-publik` tertutup saat saklar mati
      dan hanya menampilkan berkas bervisibilitas `publik` (yang `link` &
      `privat` ditolak 404). Penghitung akses/unduh & jejak `admin_id=NULL`
      (tamu) tercatat benar. Data uji dibersihkan.

- [x] **D6 — Tautan eksternal + kuota & pemeliharaan** ✅ SELESAI & TERUJI 2026-09-20
      Tautan eksternal (YouTube/Drive) sudah masuk sejak D3–D4 (deteksi
      penyedia, sampul YouTube, pemutar tersemat `youtube-nocookie`).
      Yang ditambahkan di sini: halaman **Penggunaan Penyimpanan**
      (`admin/dokumen/penyimpanan`) — kartu terpakai/jumlah berkas/tertahan
      di sampah, bar pemakaian per jenis berkas, peringatan saat ≥80%, dan
      **pemeriksaan kesehatan arsip**: "berkas hilang" (tercatat tapi tak ada
      di disk) & "berkas yatim" (ada di disk tapi tak tercatat) + tombol
      pembersih. Bar penyimpanan di penjelajah kini menaut ke halaman ini.
      **Hasil uji:** dengan sengaja menghapus satu berkas dari disk dan
      menaruh dua berkas asing, keduanya terdeteksi; pembersih menghapus
      **tepat 2 berkas yatim** dan **tidak menyentuh thumbnail** berkas yang
      sah (thumbnail terdaftar lewat kolom `thumb`, jadi bukan yatim).
      Saklar `dok_izinkan_video` diuji dua arah: mati → MP4 ditolak, nyala →
      MP4 tersimpan sebagai kategori video.

**🎉 SISI WEB SELESAI (D0–D6).** Lanjut D7 (API) & D8 (Flutter).


- [ ] **D7 — API `/api/v1`**
      `Api\Admin\Dokumen` + `DokumenFolder` + `DokumenShare` (pola `BaseCrud`
      & `Api\Admin\LabGambar`): jelajah, unggah multipart, ubah, pindah,
      hapus/pulihkan, buat & cabut tautan berbagi, unduh berkas (stream
      Range juga, autentikasi Bearer). Endpoint publik `GET /api/v1/d/<token>`
      untuk tautan berbagi. Uji e2e memakai token (pola yang sudah terbukti
      di SIMLAB/UKK).

- [ ] **D8 — Flutter (`C:\flutter-muslimin`)**
      Hub "Dokumen" (tab baru di `admin_shell.dart`, pola `lab_hub_screen`):
      jelajah folder + breadcrumb, cari, grid/daftar dengan ikon per tipe.
      Unggah dari HP: berkas apa pun (`file_picker`), foto/galeri
      (`image_picker`), HEIC→WEBP di perangkat (`flutter_image_compress`
      SUDAH terpasang). Pratinjau: PDF (`pdfx`), gambar (`photo_view`),
      teks; Office → `open_filex` (SUDAH terpasang) membuka dengan aplikasi
      HP. Unduh dengan progres (`dio` SUDAH terpasang) lalu buka. Buat/salin/
      bagikan tautan (`share_plus`). **Paket baru yang perlu ditambah:**
      `file_picker`, `pdfx`, `photo_view`, `share_plus`.

- [ ] **D9 — Uji e2e + uji keamanan + deploy**
      Matriks uji per tipe berkas (docx/xlsx/pptx/pdf/jpg/png/heic/zip/
      berkas besar/nama berkas aneh & unicode). **Uji keamanan wajib:**
      berkas privat harus TERTOLAK tanpa login; tautan kedaluwarsa/dicabut
      harus 404; token salah harus 404; path traversal (`../`) harus mental.
      Bersihkan data uji, perbarui panduan, lalu deploy:
      `cd ~/kangmuslim && git pull && phpm spark migrate` + pastikan folder
      `writable/uploads/dokumen/` bisa ditulis di server.

---

## Catatan deploy

```
cd ~/kangmuslim && git pull origin main && phpm spark migrate
```

`phpm` = alias PHP 8.3. **JANGAN `php spark`** (CLI hosting = PHP 7.4,
mati diam). Berkas unggahan TIDAK ikut git (`.gitignore`) — hidup di disk
server saja, jadi **backup terpisah** perlu dipikirkan bila dokumen klien
sudah banyak.
