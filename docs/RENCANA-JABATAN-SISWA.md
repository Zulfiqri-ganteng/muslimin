# Rencana Fitur: Jabatan Guru + Data Siswa

Dokumen kerja step-by-step. **Centang tiap langkah selesai** agar pekerjaan bisa
dilanjutkan di sesi baru tanpa kehilangan konteks.

Dibuat: 2026-07-18. Basis: repo `muslimin` (CI4 4.7, MySQL, shared hosting Rumahweb).

---

## Ringkasan permintaan user

1. Semua guru punya **jabatan** (kurikulum, guru MTK, ketua program, dll) — fleksibel,
   bisa dibuat sendiri, dan **punya relasi** dengan entitas lain.
2. Absensi guru walau tak punya jadwal → **SUDAH ADA** (tabel `absensi_kerja`,
   panel "Kehadiran Kerja", 2026-07-11). Sisa: sambungkan ke jabatan.
3. Rekap laporan disesuaikan dengan penambahan hari ini.
4. **Data siswa lengkap** + import/export massal.
5. Semua tampil di frontend; jumlah siswa 2026 cukup **grafik angka** (tanpa detail).

---

## Keputusan desain

### Jabatan — kenapa 3 relasi, bukan 1 kolom teks

Satu kolom `jabatan` di tabel `guru` tidak memenuhi "fleksibel + punya relasi".
Dipakai 2 tabel:

- **`jabatan`** — master, dibuat bebas oleh admin. Relasi yang dibawa:
  - `parent_id` → self-FK, hierarki struktural
    (Kepala Sekolah → Wakasek Kurikulum → Ketua Program TKJ).
  - `jurusan_id` → FK jurusan, untuk jabatan yang melekat pada jurusan
    (mis. "Ketua Program TKJ"). NULL untuk jabatan umum.
  - `is_struktural` → penanda "wajib hadir walau tak ada jadwal KBM".
    **Inilah jembatan ke fitur #2**: guru dengan jabatan struktural otomatis
    muncul di panel Kehadiran Kerja.
- **`guru_jabatan`** — pivot M:N (1 guru boleh punya banyak jabatan:
  "Guru MTK" + "Wakasek Kurikulum"), dengan `is_utama` dan masa jabatan (`tmt`).

### Siswa — enteng untuk shared hosting

- Tabel `siswa` dengan FK ke `kelas` (jurusan & tingkat diturunkan dari kelas,
  **tidak diduplikasi** agar tak ada data tidak sinkron).
- Statistik publik **tidak pernah** menarik baris siswa: hanya
  `COUNT(*) ... GROUP BY`, hasilnya di-cache 30 menit (pola `publik_stats` yang sudah ada).
- Index: `kelas_id`, `status`, `tahun_masuk`, `nama`; unique `nis`, unique `nisn`.
- Import/export **gratis** dari `BaseMaster` (sudah punya alur unggah → pratinjau
  editable → commit upsert + template Excel).

### Keamanan

- Semua query lewat Query Builder (aman SQL-injection) — konsisten repo.
- Halaman publik hanya menampilkan **agregat angka**, tak pernah identitas siswa
  (nama/alamat/tgl lahir = data pribadi anak, jangan diekspos).
- CRUD siswa/jabatan di belakang filter `auth` (web) dan `apiauth` (API).

---

## Langkah kerja

- [x] **L1 — Fondasi DB & Model** ✅ SELESAI 2026-07-18
      Migrasi `2026-07-18-000001_CreateJabatanSiswa` sudah jalan di lokal
      (3 tabel + 8 jabatan awal ter-seed, self-FK `parent_id` OK).
      Model `JabatanModel` / `GuruJabatanModel` / `SiswaModel` selesai & teruji
      (22 pemeriksaan lolus: hierarki anti-melingkar, sync pivot, agregat
      statistik, tolak NIS duplikat). Modul `jabatan` & `siswa` didaftarkan ke
      `master_data_changed()`. Data uji sudah dibersihkan.
      *Detail rancangan asli:*
      Migrasi `2026-07-18-000001_CreateJabatanSiswa`: tabel `jabatan`,
      `guru_jabatan`, `siswa`. Model `JabatanModel`, `GuruJabatanModel`,
      `SiswaModel` (+ `options()` ber-cache, `statistik()` agregat).
      Seeder jabatan awal (Kepala Sekolah, Wakasek Kurikulum/Kesiswaan, Ketua Program).

- [x] **L2 — Web: Master Jabatan** ✅ SELESAI 2026-07-18
      `Admin\Master\Jabatan` + `app/Views/admin/master/jabatan.php` +
      `public/assets/js/admin/master/jabatan.js` + 9 rute + menu sidebar
      (Master Data → Jabatan). Kolom tabel: kode, nama (+badge Struktural),
      kategori, induk, jurusan, jumlah guru. Filter kategori + cari.
      Import mengenali **kode induk & kode jurusan** (relasi ikut terbawa).
      `cleanupRelations` melepas pivot `guru_jabatan` + menaikkan jabatan anak
      (soft delete → FK ON DELETE tidak jalan, jadi ditangani manual).
      **Teruji e2e via HTTP** (11 skenario: tambah, tolak kode duplikat, ubah,
      tolak induk=diri sendiri, tolak induk=keturunan/melingkar, hapus + bersih
      relasi, export, template, import preview, import commit upsert + resolusi
      relasi, hapus massal). Data uji & jejak audit sudah dibersihkan.
      Tailwind sudah di-rebuild.

- [x] **L3 — Web: Master Siswa** ✅ SELESAI 2026-07-18
      `Admin\Master\Siswa` + view + `siswa.js` + 9 rute + menu sidebar.
      18 kolom data lengkap; filter kelas/tingkat/status + cari; export
      **mengikuti filter aktif**; impor mencocokkan **nama kelas** (tak peka
      huruf besar/spasi ganda) & menerima tanggal `dd/mm/yyyy`, `dd-mm-yyyy`,
      `Y-m-d`, serial Excel.
      **Teruji e2e via HTTP (11 skenario)** termasuk yang rawan:
      dua siswa tanpa NISN → keduanya NULL, tidak bentrok unique;
      tanggal ngawur & kelas tak dikenal → dikosongkan, baris tetap tersimpan;
      NIS yang sudah ada → diperbarui bukan diduplikat;
      impor ulang siswa terhapus → dipulihkan, tetap 1 baris.
      Data uji & jejak audit dibersihkan; `php -l` bersih; 6 halaman admin
      di-smoke-test 200 tanpa error; Tailwind di-rebuild.

- [x] **L4 — Integrasi Jabatan ↔ Guru** ✅ SELESAI 2026-07-18
      Kolom **Jabatan** di Master Guru (badge; jabatan utama ber-bintang,
      jabatan struktural berwarna amber) + modal **Atur Jabatan**
      (centang banyak jabatan + radio penanda utama) via `guru.js`.
      Rute `POST admin/master/guru/jabatan/(:num)` → `Guru::jabatan()`.
      Export Master Guru kini punya kolom **Jabatan** (utama lebih dulu).
      `cleanupRelations` guru melepas `guru_jabatan` (soft delete → CASCADE
      tidak jalan).
      **Teruji 7 skenario**: pasang 2 jabatan + utama, badge tampil,
      utama tak valid → diperbaiki server, kosongkan semua, guru tak ada →
      ditolak rapi, export berisi jabatan, hapus guru → pivot ikut bersih.

      ⚠️ **PELAJARAN PENTING (cache berbentuk lama):** menambah kunci baru ke
      nilai `cachedList()` membuat kode baru membaca cache bentuk lama →
      **HTTP 500 setelah deploy** sampai cache kedaluwarsa (1 jam). Ketahuan
      saat uji L4. Solusi yang dipakai: sisipkan penanda versi bentuk pada
      kunci cache (`"list|v2|q=..."`) + akses defensif `?? []`.
      **Wajib naikkan penanda versi ini setiap struktur nilai cache berubah.**

- [x] **L5 — Absensi & Rekap disesuaikan (WEB)** ✅ SELESAI 2026-07-18
      **Input absensi:** guru berjabatan struktural otomatis diisikan ke panel
      Kehadiran Kerja, ditandai chip **disarankan** + banner penjelas. Sengaja
      TIDAK langsung disimpan (prinsip "belum di-save = belum tercatat"), dan
      saran **berhenti muncul begitu tanggal itu tercatat** supaya guru yang
      sengaja dihapus admin tidak muncul lagi.
      **Rekap:** kolom **Jabatan** (badge; struktural amber, tooltip berisi
      semua jabatan) + **filter jabatan** yang ikut terbawa ke tautan rincian
      dan tombol export. Halaman rincian per guru menampilkan jabatannya.
      **Export:** Excel dapat kolom Jabatan (kolom digeser A–J, rumus SUM
      ikut ke E–J, kop ke 'J') + label filter jabatan di judul; PDF dapat
      kolom Jabatan (colspan TOTAL 3→4).
      **Teruji**: saran muncul di hari Minggu (2 guru), setelah simpan-sebagian
      saran tidak memunculkan lagi guru yang dibuang, filter jabatan cocok &
      tidak cocok, Excel (verifikasi isi + rumus SUM E–J) & PDF 200,
      6 halaman smoke-test bersih. Data uji dibersihkan (absensi_hari 2 baris
      milik user 2–3 Juli sengaja DIBIARKAN).

- [x] **L6 — Frontend publik (web)** ✅ SELESAI 2026-07-19
      Beranda publik: kartu **Siswa Aktif** (kini 4 kartu) + bagian
      **Statistik Siswa** — angka total besar, komposisi L/P, batang per
      tingkat & per jurusan. Hanya AGREGAT; identitas siswa tak pernah dikirim
      ke publik. Seluruh bagian disembunyikan bila belum ada siswa.
      Bentuk mengikuti kaidah dataviz: batang **satu warna** (data ini
      membandingkan besaran, bukan identitas — bukan warna-per-batang), total
      sebagai angka hero (bukan grafik 1 batang), nilai selalu tertulis
      (tak ada info yang hanya terbaca lewat warna).
      Warna L/P `#1e6fd6` (brand-500) + `#b45309` **lolos 6 pemeriksaan
      validator** (pita terang, chroma, pemisahan buta warna, ambang normal,
      kontras). Pasangan brand-700+brand-400 sempat GAGAL → jangan dipakai.
      Cache beranda dinaikkan ke `publik_stats_v2` (+ kunci di `cache_helper`).

      ⚠️ **Dua bug tertangkap karena MELIHAT hasil render (bukan hanya HTML):**
      (1) batang tak berwarna — kelas Tailwind baru belum di-rebuild;
      (2) kartu statistik dibuat 2 kolom di layar terkecil → melebar di HP,
      dikembalikan ke `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`.
      Catatan: headless Chrome TIDAK mereflow ke lebar 390px (bagian yang
      diisolasi pun terpotong) — verifikasi HP wajib di perangkat/DevTools asli.

- [x] **Panduan dalam aplikasi diperbarui** ✅ SELESAI 2026-07-19
      Permintaan user: tiap menu wajib menjelaskan alur baru. Diperbarui +
      **kunci help dinaikkan** (agar panduan muncul lagi walau dulu ditutup):
      `dashboard→dashboard_v2` (alur jabatan & alur data siswa dari nol),
      `guru→guru_v2` (multi-jabatan + arti struktural), `kelas→kelas_v2`
      (kaitan ke siswa), `absensi_v2→absensi_v3` (pengisian otomatis struktural
      + arti "disarankan"), `rekap_absensi_v2→rekap_absensi_v3` (kolom+filter
      jabatan, export ikut filter). Menu Jabatan & Siswa sudah punya panduan
      sejak dibuat.

- [x] **L7 — API v1** ✅ SELESAI 2026-07-19
      `Api\Admin\Jabatan` + `Api\Admin\Siswa` (extends `BaseCrud`), endpoint
      jabatan-per-guru, statistik siswa publik, kolom+filter jabatan di rekap,
      saran struktural di absensi, jabatan di Options.
      **Teruji 16 skenario** (lihat daftar endpoint di bawah).

### Kontrak endpoint baru (untuk L8 Flutter)

**Publik (tanpa token)**
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/v1/statistik/siswa` | agregat: `total`, `per_tingkat`, `per_jurusan`, `jenis_kelamin`, `per_tahun`, `tahun`. **Tanpa identitas siswa.** |
| GET | `/api/v1/home` | `stats` kini memuat `siswa` (jumlah aktif) |

**Admin (Bearer token)**
| Method | Endpoint | Keterangan |
|---|---|---|
| GET/POST | `/admin/master/jabatan` | daftar (`?q=&kategori=&struktural=1&per=&page=`) / tambah |
| POST/DELETE | `/admin/master/jabatan/{id}` | ubah / hapus |
| POST | `/admin/master/jabatan/bulk-delete` | body `{ids:[..]}` |
| GET | `/admin/master/jabatan/options` | dropdown `{id,label,kode,is_struktural}` |
| GET/POST | `/admin/master/siswa` | daftar (`?q=&kelas_id=&tingkat=&status=&per=&page=`) / tambah |
| POST/DELETE | `/admin/master/siswa/{id}` | ubah / hapus |
| POST | `/admin/master/siswa/bulk-delete` | body `{ids:[..]}` |
| GET | `/admin/master/siswa/statistik` | agregat yang sama untuk dashboard admin |
| GET | `/admin/master/guru/{id}/jabatan` | jabatan yang disandang guru |
| POST | `/admin/master/guru/{id}/jabatan` | body `{jabatan_ids:[..], utama_id:n}` — **sinkron penuh** (mengganti, bukan menambah) |
| GET | `/admin/master/options?types=jabatan` | jabatan ikut di daftar tipe |

**Perubahan pada respons yang sudah ada (non-breaking, hanya penambahan):**
- `GET /admin/master/guru` → tiap item dapat `jabatan` (utama, boleh null) & `jabatan_list[]`
- `GET /admin/absensi` → dapat `saran_kerja[]` (guru struktural yang disarankan;
  **kosong bila tanggal sudah tercatat**). Klien tetap wajib `save-kerja` — saran belum tersimpan.
- `GET /admin/absensi/rekap` → tiap item dapat `jabatan`, `jabatan_all`,
  `jabatan_ids[]`, `struktural`; ada filter `?jabatan_id=` + balikan
  `jabatan_id` & `jabatan_nama`. `sum` dihitung SETELAH filter.
- `POST/PATCH` siswa menerima `tanggal_lahir` format `Y-m-d`, `dd/mm/yyyy`, `dd-mm-yyyy`.

- [ ] **L8 — Flutter (`C:\flutter-muslimin`)**
      Layar admin Jabatan & Siswa, grafik siswa di Landing, update `BLUEPRINT.md`.

- [ ] **L9 — Uji e2e + deploy**
      Uji tiap alur, bersihkan data uji, catat langkah deploy.

---

## Catatan deploy (hosting kangmuslim.com)

```
cd ~/kangmuslim && git pull origin main && phpm spark migrate
```

`phpm` = alias PHP 8.3. **JANGAN `php spark`** (CLI = PHP 7.4, mati diam).

Claude TIDAK commit — user commit sendiri via GitHub Desktop.
