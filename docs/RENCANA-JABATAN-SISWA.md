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

- [ ] **L4 — Integrasi Jabatan ↔ Guru**
      Kolom "Jabatan" di master guru + editor multi-jabatan (pola modal
      kompetensi mapel). `GuruModel` helper `jabatanMap()`.

- [ ] **L5 — Absensi & Rekap disesuaikan**
      Panel Kehadiran Kerja prefill guru struktural; rekap absensi tambah
      kolom + filter Jabatan; export Excel/PDF rekap ikut kolom jabatan.

- [ ] **L6 — Frontend publik (web)**
      `Publik::home` tambah statistik siswa (grafik batang SVG inline, tanpa lib
      eksternal — Node tidak terpasang). Halaman `/statistik` opsional.
      Rebuild Tailwind: `./tailwindcss.exe -i resources/css/app.css -o public/assets/css/app.css --minify`

- [ ] **L7 — API v1**
      `Api\Admin\Jabatan` + `Api\Admin\Siswa` (extends `BaseCrud`),
      `Publik::statistikSiswa`, field jabatan di rekap absensi & options.

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
