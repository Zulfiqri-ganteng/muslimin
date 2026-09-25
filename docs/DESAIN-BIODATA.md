# Fitur: Isian Biodata Siswa Online (datasiswa.kangmuslim.com)

Dokumen kerja. **Centang tiap tahap selesai** agar pekerjaan bisa dilanjutkan di
sesi baru tanpa kehilangan konteks.

Dibuat: 2026-09-25. Basis: repo `muslimin` (CI4 4.7, MySQL/MariaDB, shared hosting
Rumahweb, LIVE di kangmuslim.com). Klien: **Wakasek Kesiswaan** SMK Bina Nusa.

---

## Latar

Sekolah hanya punya **nama + kelas + jenis kelamin** 1.718 siswa aktif (NISN kosong,
NIS berisi nomor urut 1–1718). Klien ingin siswa **mengisi biodata sendiri** (format
buku induk, ±18 poin) lewat tautan yang dibagikan di WhatsApp — seperti Google Form,
**tanpa login**, **tanpa data ganda** — lalu hasilnya melengkapi Master Siswa di web
dan aplikasi Android.

## Keputusan user (2026-09-25)

1. Isian masuk **kotak masuk** dulu, admin **menyetujui** (bisa massal) → baru menulis
   ke Master Siswa. Tidak langsung menimpa.
2. Setelah dikirim **terkunci**; bila salah, admin **mengembalikan** (siswa membuka lagi
   dengan NISN / tanggal lahir) atau **menghapus** isian (siswa isi dari nol).
3. **Tanpa unggah berkas.** Peringatan "WAJIB sesuai Kartu Keluarga" di atas form.
4. **Web dulu (wajib 100%)**, Android menyusul (kolom baru + layar progres/verifikasi).
5. **Laporan Excel** siap cetak: siswa mana yang biodatanya lengkap / belum, per kelas.
6. Subdomain dibuat user dengan **Share document root** (satu aplikasi, satu database).
7. Repo GitHub **tetap publik** (keputusan user) — dump data asli TIDAK boleh ada di repo
   (`.gitignore`: `/database/*.sql`, `/database/*.xlsx`).

---

## Alur

**Siswa** (HP, tanpa login) — wizard 6 langkah, `https://datasiswa.kangmuslim.com`:

1. **Cari nama**: pilih kelas → ketik/pilih nama → konfirmasi "Benar ini kamu?".
   Nama yang sudah mengisi bertanda *Sudah mengisi / Terverifikasi / Perlu perbaikan*.
2. Data diri · 3. Alamat & sekolah asal · 4. Orang tua · 5. Wali · 6. Periksa & kirim.
   Draf tersimpan otomatis di HP (localStorage, 7 hari). Halaman selesai menampilkan
   **nomor bukti** isian.

**Admin** — menu **KESISWAAN → Isian Biodata Siswa** (`/admin/biodata`):

- Saklar buka/tutup + batas waktu; tautan + tombol **Bagikan ke WA**.
- Angka progres; tab **Menunggu / Perlu Perbaikan / Disetujui / Belum Mengisi / Rekap per
  Kelas**; pesan WA daftar nama yang belum mengisi per kelas.
- **Periksa**: data lama vs isian (hijau = baru, kuning = berubah) + peringatan
  (NISN/NIS bentrok, nama/JK beda dengan data sekolah). Setujui → otomatis membuka isian
  berikutnya. Setujui massal maks. 300 per klik.
- **Unduh Laporan** (Excel): lembar REKAP + 1 lembar per kelas, kop + tanda tangan.

---

## Data

### Tabel `siswa` — +24 kolom (migrasi `2026-09-25-000002_CreateBiodataSiswa`)

`status_keluarga, anak_ke, rt, rw, kelurahan, kecamatan, kota, sekolah_asal,
diterima_kelas, diterima_tanggal, nama_ayah, nama_ibu, pekerjaan_ayah, pekerjaan_ibu,
ortu_alamat, ortu_rt, ortu_rw, ortu_kelurahan, ortu_kecamatan, ortu_kota, ortu_telepon,
alamat_wali, pekerjaan_wali, biodata_at` (+ index `siswa_biodata_at`).

Kolom lama dipakai ulang: `alamat` = jalan/perumahan siswa, `no_hp` = HP siswa,
`nama_wali` / `no_hp_wali` kini berarti **wali selain orang tua** (di produksi kosong).
`diterima_kelas` = kelas **saat pertama diterima**, bukan `kelas_id` (kelas sekarang).
`biodata_at` = kapan isian terakhir disetujui (penanda "Biodata ✓").

### Tabel `biodata_isian` — kotak masuk

`siswa_id` **UNIQUE** (satu siswa satu isian) · `nisn` **UNIQUE** · `data` (JSON LONGTEXT) ·
`data_sebelum` (potret Master Siswa saat disetujui) · `status` menunggu/disetujui/perbaikan ·
`catatan_admin` · `kirim_ke` · `ip_address` · `diverifikasi_at/oleh`. FK siswa CASCADE.

### Tabel `settings` — `biodata_open` (default **0 = tutup**), `biodata_tutup` (DATETIME).

---

## Aturan penting (jangan dilanggar saat mengubah kode)

| Aturan | Di mana |
|---|---|
| Daftar kolom isian = `BiodataIsianModel::KOLOM` (34); label/urutan = `BiodataForm::LABEL/BAGIAN`; kolom wajib = `BiodataForm::WAJIB` (27) | satu sumber |
| Isian disetujui dalam **satu transaksi**; NIS/NISN bentrok → batal utuh | `BiodataVerifikasi::setujui` |
| NIS, No HP siswa, tanggal diterima yang **kosong di isian tidak menimpa** data lama | `BiodataVerifikasi::KOSONG_JANGAN_TIMPA` |
| **Lengkap** = 27 kolom wajib terisi di **Master Siswa** (bukan dari isian) | `BiodataLaporan`, `BiodataForm::kolomKosong` |
| Impor Excel siswa: **sel kosong = tidak diubah**; 15 kolom lama template tetap urutan, kolom biodata di belakang | `Admin\Master\Siswa::kolomImpor/normalizeImportRow` |
| Ekspor: nomor ditulis `setCellValueExplicit(TYPE_STRING)`, **jangan** awalan `'` | `Admin\Master\Siswa::export` |
| Subdomain hanya melayani `/` & `biodata/*`, selain itu 302 ke domain utama | `BiodataHostFilter` (global) |
| Host subdomain harus ada di `App::$allowedHostnames` (ditulis langsung, .env tak bisa) | `Config\App`, `Config\Biodata` |
| Data pribadi hanya keluar lewat "buka ulang" ber-NISN/tanggal lahir + `LoginThrottle` | `Controllers\Biodata::buka` |
| API Android lama (`Api\Admin\Siswa::collect`) hanya menulis 15 kolom lama → biodata aman | teruji B6 |
| Semua aksi admin POST; tidak ada hapus lewat GET | `Routes.php` |

---

## Berkas

| Lapisan | Berkas |
|---|---|
| Migrasi | `app/Database/Migrations/2026-09-25-000002_CreateBiodataSiswa.php` |
| Config | `app/Config/Biodata.php`, `App.php` (allowedHostnames), `Filters.php` (`biodatahost`), `Routes.php` |
| Filter | `app/Filters/BiodataHostFilter.php` |
| Model | `app/Models/BiodataIsianModel.php`, `SiswaModel.php` (+kolom, AGAMA/STATUS_KELUARGA/PEKERJAAN), `SettingModel.php` |
| Library | `app/Libraries/BiodataForm.php` (rapikan+validasi), `BiodataVerifikasi.php` (setujui/kembalikan/hapus), `BiodataLaporan.php` (Excel) |
| Controller | `app/Controllers/Biodata.php` (publik), `app/Controllers/Admin/Biodata.php`, `Admin/Master/Siswa.php` |
| View | `app/Views/biodata/{form,selesai,tutup,_header}.php`, `layouts/biodata.php`, `admin/biodata/{index,detail}.php`, `admin/master/siswa.php`, `layouts/admin.php` (menu) |
| JS/CSS | `public/assets/js/biodata.js`, `public/assets/js/admin/biodata.js`, `public/assets/js/admin/master/siswa.js`, `resources/css/app.css` → `public/assets/css/app.css` |

**Rute publik:** `GET /` (hanya di subdomain), `GET biodata`, `GET biodata/siswa?kelas_id=`,
`POST biodata/buka`, `POST biodata/kirim`, `GET biodata/selesai`.
**Rute admin:** `GET admin/biodata`, `GET admin/biodata/laporan[?kelas_id=]`,
`POST admin/biodata/pengaturan`, `POST admin/biodata/setujui-massal`, `GET admin/biodata/{id}`,
`POST admin/biodata/{id}/setujui|kembalikan|hapus`.

---

## Tahap

- [x] **B0** Analisis & keputusan
- [x] **B1** Database (migrasi idempoten, rollback teruji)
- [x] **B2** Form publik + subdomain (29 skenario HTTP + Chrome 390px)
- [x] **B3** Admin kotak masuk & verifikasi (16 skenario + foto 1280/390)
- [x] **B4** Master Siswa ikut kolom baru (35 cek impor, ekspor dibaca ulang)
- [x] **B5** Laporan Excel siap cetak
- [x] **B6** Uji ujung-ke-ujung (Chrome sungguhan lewat subdomain) + regresi 53 halaman admin + dokumen ini
- [x] **B7** Deploy web (user push 2026-09-25)
- [x] **Android A1** API (2026-09-25) — `Api\Admin\Biodata` (12 rute, cermin menu web) +
      `Api\Admin\Siswa` (kolom biodata di transform, `?biodata=`, tulis PARSIAL: kunci biodata
      yang tak dikirim tak diubah → aplikasi lama aman). Logika web & API disatukan di
      `BiodataVerifikasi` (bandingkan, peringatan, setujuiBanyak, simpanPengaturan) dan
      `BiodataPesan` (tautan, teks WA). Kontrak + contoh respons nyata:
      **`C:\flutter-muslimin\BLUEPRINT-BIODATA.md`**.
- [ ] **Android A2** Flutter — dikerjakan AI di project flutter-muslimin memakai blueprint itu

### Rute API (`/api/v1`, Bearer)

`GET admin/biodata` · `GET admin/biodata/meta` · `POST admin/biodata/pengaturan` ·
`GET admin/biodata/kelas` · `GET admin/biodata/isian?status=&kelas_id=&q=&page=&per=` ·
`GET admin/biodata/belum?kelas_id=` (+`meta.pesan_wa`) · `GET admin/biodata/laporan?kelas_id=&unduh=1` (biner) ·
`POST admin/biodata/setujui-massal` · `GET admin/biodata/isian/{id}` ·
`POST admin/biodata/isian/{id}/setujui|kembalikan` · `DELETE admin/biodata/isian/{id}` ·
`GET admin/master/siswa?biodata=lengkap|belum`.

---

## Langkah deploy (B7)

**Sebelum deploy**

1. **Perbaiki data XII TKJ 3**: 40 namanya salinan persis XII TKJ 2 (salah impor).
   Impor ulang daftar XII TKJ 3 yang benar, kalau tidak siswa aslinya tak menemukan nama.
2. Pastikan SSL `datasiswa.kangmuslim.com` aktif (cPanel → SSL/TLS Status). Sertifikat
   `*.datasiswa…` tidak diperlukan.

**Deploy**

3. Commit & push lewat GitHub Desktop (ikutkan juga penghapusan `database/dbasli.sql`,
   `database/laporan.xlsx` dari repo dan perubahan `.gitignore`).
4. Terminal cPanel:
   ```
   cd ~/kangmuslim && git pull origin main && phpm spark migrate && phpm spark cache:clear
   ```
   (`phpm` = PHP 8.3. `cache:clear` wajib: pengaturan sekolah di-cache 6 jam dan belum
   mengenal saklar biodata.)

**Setelah deploy**

5. Buka `https://kangmuslim.com/admin/biodata` → status **DITUTUP**; buka
   `https://datasiswa.kangmuslim.com` → "Pengisian Sedang Ditutup". Keduanya benar.
6. Centang **Buka form isian** → coba isi sebagai 1 siswa dari HP → cek muncul di tab
   Menunggu → **Hapus isian** uji itu.
7. **Bagikan ke WA** grup siswa / wali kelas.

**Rollback** (hanya sebelum siswa mulai mengisi!): `phpm spark migrate:rollback`
menghapus kolom biodata & tabel isian — **data isian ikut hilang**.
