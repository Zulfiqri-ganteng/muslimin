# Rancangan: Sertifikat PKL

Ditulis 2026-10-28 (tanpa kode). Lanjutan rencana singkat di `docs/DESAIN-PERPANJANGAN-PKL.md` bagian 12.
Kerja per tahap S1–S5, berhenti tiap tahap. Layar Android dikerjakan AI di repo flutter — di sini cukup API + kontrak.

**Status:** file contoh sertifikat dari sekolah **belum ada** di `formatdatasekolah/` (isinya 13 berkas, tak satu pun sertifikat),
dan jawaban pertanyaan bagian 2 belum masuk. Bagian yang bergantung pada format ditandai **[MENUNGGU]**.

## 1. Fakta kode & data (dicek 2026-10-28, DB salinan `muslimin_h1028`)

- Menu `pkl_sertifikat` sudah ada sebagai "Segera" (`MenuAdmin.php` baris 152–154, alamat `admin/pkl/sertifikat`, ikon `award`); rutenya belum ada.
- Pola yang bisa dipakai ulang:
  - `Admin\SertifikatUkk` + `SertifikatUkkModel` — PDF Dompdf A4 mendatar, 1 peserta = 1 sertifikat (UNIQUE), nomor otomatis.
  - `PklNomorSurat::format()` — pola nomor `{urut}`, `{bln_romawi}`, `{thn}` dst. (dipakai surat PKL, bawaan `295/SMK-BN/PKL/VII/2026`).
  - `PklSurat` — nama/jabatan Waka Hubin & Kepsek + gambar TTD digital (`ttd_hubin`, `ttd_kepsek`, lewat `GambarTtd`).
  - `SuratPerpanjangan::selesaiEfektif()` (tanggal selesai sesudah perpanjangan) dan `::ditarik()` (siswa yang ditarik).
  - Bilah pilih / menu Lainnya / lipat (rapi tampilan U1) untuk layar daftar.
- Data siswa: 1.735 siswa aktif, NIS & NISN lengkap; tempat lahir kosong 7, tanggal lahir kosong 10. **Tidak ada foto siswa** di sistem.
- PKL 2026/2027: 236 ajuan disetujui (395 siswa). **196 ajuan (83%) belum punya tanggal mulai & selesai.** Yang punya tanggal
  selesai: Okt 2026 = 3, Nov = 8, Des = 13, Jan 2027 = 15, Feb = 1.
- Siswa dikeluarkan dari ajuan (`pkl_anggota_keluar`) 12; penarikan disetujui 1; perpanjangan berlaku 1.
- **Tidak ada data nilai PKL** dan tidak ada data pembimbing sekolah / instruktur perusahaan (`kontak_nama` ajuan = kontak perusahaan).
- `vendor/` ikut di-commit. Dompdf 3.1 sudah ada. **Pustaka QR belum ada** → bila QR dipakai: tambah `chillerlan/php-qrcode`
  (MIT, PHP murni, keluaran SVG yang bisa dibaca Dompdf — tanpa GD), vendor ikut di-commit.

## 2. Keputusan yang ditunggu (usulan Claude = yang pertama)

| No | Pertanyaan | Pilihan (usulan pertama) |
|---|---|---|
| 1 | Penanda tangan | a. Kepsek + Waka Hubin (TTD digital sudah ada) · b. Kepsek saja · c. Kepsek + pimpinan perusahaan (kolom TTD basah, nama dari kontak ajuan) |
| 2 | Nilai / predikat | a. tanpa nilai dulu (kolom predikat disiapkan, bisa dinyalakan nanti tanpa migrasi) · b. predikat saja (Amat Baik/Baik/Cukup) diisi staf, bisa massal · c. daftar nilai per aspek di halaman belakang + impor Excel |
| 3 | Nomor sertifikat | a. pola gaya surat sekolah `{urut}/SMK-BN/SERT-PKL/{bln_romawi}/{thn}`, bisa diubah di Pengaturan, urut per tahun ajaran, nomor awal bisa diatur · b. `SERT-PKL-2026-001` seperti UKK · c. sebut format yang sudah dipakai sekolah |
| 4 | Kertas & tampilan | a. A4 mendatar, dicetak penuh di atas gambar desain sekolah (diunggah di Pengaturan) · b. A4 mendatar di kertas blanko pra-cetak (sistem hanya mencetak teks) · c. F4 / tegak |
| 5 | QR cek keaslian | a. ya — halaman publik "Cek Sertifikat" (nomor, nama, perusahaan, periode, sah/dibatalkan) · b. tidak |
| 6 | Foto siswa | a. tanpa foto · b. pakai foto (perlu fitur unggah foto massal dulu = 1 tahap tambahan) |
| 7 | Siswa unduh sendiri | a. ya, dari Layanan Siswa (web + Android) setelah terbit · b. tidak, hanya staf |
| 8 | Siapa menerbitkan | a. Operator & Waka Hubin (bisa diubah di Sistem → Hak Akses) · b. Waka Hubin saja |
| 9 | Periode kosong (83% ajuan) | a. staf mengisi periode di form terbit, disimpan di sertifikat saja (logika PKL tak berubah) · b. wajib diisi dulu lewat Surat Balasan / Lapor diterima |

## 3. Aturan bisnis

- **R1** 1 siswa = 1 sertifikat per ajuan (UNIQUE `pengajuan_id + siswa_id`). Cetak ulang = nomor & kode sama.
- **R2 Syarat keras** (tombol Terbitkan menolak): ajuan disetujui · siswa masih anggota ajuan (tidak dikeluarkan) · tidak ditarik ·
  periode lengkap & masuk akal (selesai ≥ mulai, lama ≤ `MAKS_HARI_PKL` 400) · belum punya sertifikat terbit.
- **R3 Syarat lunak** (lencana kuning, tetap boleh): tanggal selesai efektif belum lewat · perpanjangan masih menunggu ACC ·
  tempat/tanggal lahir kosong · predikat kosong (bila 2b/2c).
- **R4 Periode** = tanggal mulai ajuan → `selesaiEfektif()`. Kosong → diisi staf di form terbit (keputusan 9), satu isian per ajuan.
- **R5 Data dicetak = salinan saat terbit** (nama, NIS, NISN, TTL, kelas, konsentrasi, perusahaan, periode, penanda tangan) →
  sertifikat lama tidak berubah sendiri. Data salah → betulkan data siswa → "Perbarui & cetak ulang" (nomor tetap).
- **R6 Nomor** terbit saat diterbitkan (bukan saat unduh), urut per tahun ajaran, tidak pernah dipakai ulang; dibatalkan = nomor hangus.
- **R7 Batal** = status `dibatalkan` + alasan wajib (tidak dihapus); halaman cek menampilkan "DIBATALKAN". Siswa yang dibatalkan boleh diterbitkan lagi (nomor baru).
- **R8 Kode cek** acak 10 huruf/angka (bukan id berurutan) → tidak bisa ditebak; halaman cek tanpa tanggal lahir / HP / alamat.
- **R9 Perubahan sesudah terbit:** perpanjangan baru di-ACC / dibatalkan, ajuan batal-ACC, siswa dikeluarkan → sertifikat TIDAK diubah/dibatalkan
  otomatis, hanya diberi lencana "data berubah — periksa / cetak ulang" (pola "perlu cetak ulang" yang sudah ada di surat).
- **R10 Massal:** pilih per perusahaan / per kelas / centang; satu PDF gabungan (1 halaman per siswa, siap cetak), maks 50 per unduhan (batas hosting).

## 4. Data — 1 migrasi `PklSertifikat` (idempoten, punya `down()`)

- Tabel `pkl_sertifikat`: `id`, `pengajuan_id`, `siswa_id`, `tahun_ajaran`, `urut`, `nomor` (UNIQUE), `kode_cek` (UNIQUE, 10),
  `status` ENUM(`terbit`,`dibatalkan`), salinan: `nama`, `nis`, `nisn`, `tempat_lahir`, `tanggal_lahir`, `kelas`, `konsentrasi`,
  `perusahaan_nama`, `perusahaan_kota`, `tanggal_mulai`, `tanggal_selesai`, `predikat` (NULL), `nilai_json` (NULL, untuk 2c),
  `penanda_json` (nama/jabatan/NIP penanda tangan saat terbit), `tanggal_terbit`, `terbit_oleh`, `dicetak_at`, `batal_at`, `batal_oleh`,
  `batal_alasan`, `created_at`, `updated_at`. UNIQUE (`pengajuan_id`, `siswa_id`, `status_aktif`) — kolom bantu `status_aktif` = 1 bila terbit, NULL bila dibatalkan
  (MySQL membolehkan banyak NULL), supaya yang dibatalkan tidak menghalangi terbit ulang.
- Setelan baru di `pkl_pengaturan`: `sertifikat_pola_nomor`, `sertifikat_urut_awal`, `sertifikat_kota`, `sertifikat_latar` (berkas gambar),
  `sertifikat_penanda`, `sertifikat_predikat_aktif`.
- Hak baru `sertifikat` (terbit, cetak, batal) — bawaan disalin ke peran sesuai keputusan 8; Admin selalu boleh.

## 5. Tampilan cetak **[MENUNGGU format]**

- PDF Dompdf A4 mendatar (seperti sertifikat UKK). Alasan bukan Word: sertifikat = desain tetap; Word mudah bergeser saat dibuka di
  komputer lain dan tidak bisa memuat QR dengan rapi. Bila sekolah tetap minta Word, mesin `PklDocx` bisa dipakai (tanpa latar presisi).
- 4a: latar = gambar desain sekolah (PNG/JPG, ±300 dpi, maks 3 MB) diunggah di Pengaturan; teks ditempatkan di atasnya mengikuti contoh.
- 4b: tanpa latar; posisi teks disesuaikan dengan blanko + tombol "Cetak uji posisi".
- QR di pojok (bila 5a), TTD digital dari setelan yang sudah ada, halaman 2 daftar nilai bila 2c.
- CONTOH HASIL di `downloadpublic/` (seperti surat lain) untuk dicocokkan sekolah sebelum dipakai.

## 6. Layar staf (web)

- Menu "Sertifikat PKL" lepas tanda Segera, letak sesudah "Data Prakerin" (aturan urutan sidebar).
- **Daftar** — tab: Siap terbit · Sudah terbit · Dibatalkan; saring perusahaan / kelas / cari nama; bilah pilih → "Terbitkan (n)" /
  "Unduh PDF (n)"; 1 tombol per baris, sisanya di menu Lainnya. Siswa belum memenuhi syarat keras tampil di lipatan "Belum bisa (n)" + alasannya.
- **Form terbit** — ringkasan siswa terpilih per perusahaan, isian periode untuk ajuan yang kosong, peringatan kuning, tanggal terbit (bawaan hari ini).
- **Detail** — pratinjau, Unduh, Perbarui & cetak ulang, Batalkan (alasan).
- **Pengaturan PKL** tab ke-4 "Sertifikat": pola nomor + contoh langsung, nomor awal, kota terbit, unggah latar, penanda tangan, predikat on/off, Unduh CONTOH.
- Detail ajuan PKL: satu baris ringkas "Sertifikat: 2 dari 3 terbit" + tautan (tanpa tombol baru).

## 7. Publik

- `cek-sertifikat/{kode}` tanpa masuk (sasaran QR): nomor, nama, kelas, perusahaan, periode, status. Kode salah → "tidak ditemukan".
- Bila 7a: ikon Layanan Siswa "Sertifikat PKL saya" (masuk NISN + tanggal lahir) → unduh PDF sendiri. **Ingat** daftar tetap ikon di
  `app/Views/public/partials/kepala.php` (pernah ketinggalan saat perpanjangan).

## 8. API

**8a. Staf** (Bearer, gerbang `api_akses` "pkl", hak `sertifikat`, library sama dengan web, bentuk galat sama dengan API-SURAT):

| Metode & alamat | Isi |
|---|---|
| `GET pkl/sertifikat/meta` | contoh nomor, predikat aktif + pilihannya, maks per unduhan |
| `GET pkl/sertifikat?status=siap\|terbit\|dibatalkan&perusahaan_id=&kelas_id=&q=&page=` | daftar + `syarat[]` / `peringatan[]` per siswa |
| `GET pkl/sertifikat/{id}` | detail + `berubah` (R9) |
| `POST pkl/sertifikat` | `{siswa: [{ajuan_id, siswa_id}], tanggal_terbit, periode: {ajuan_id: {mulai, selesai}}}` → `201 {terbit[], dilewati[{siswa_id, alasan}]}` |
| `POST pkl/sertifikat/{id}/perbarui` | salin ulang data, nomor tetap |
| `POST pkl/sertifikat/{id}/batal` | `{alasan}` |
| `GET pkl/sertifikat/{id}/pdf` · `GET pkl/sertifikat/pdf?ids=1,2` | `application/pdf` (maks 50 → lebih = 422) |

**8b. Publik:** `GET publik/sertifikat/{kode}` (tanpa token) · bila 7a: `GET publik/layanan/sertifikat` dan
`GET publik/layanan/sertifikat/{id}/pdf` (token `LayananToken`, hanya milik siswa itu; milik siswa lain → 404).

Dokumen: `docs/API-SERTIFIKAT-PKL.md` (baru) + tambahan di `docs/API-LAYANAN-SISWA.md`.

## 9. Kontrak Flutter (repo `C:\flutter-muslimin`, dokumen saja, tidak di-commit)

BLUEPRINT-PKL tahap P19 (staf: daftar, terbit massal, unduh/bagikan PDF) · BLUEPRINT-SURAT tahap S15 (siswa: Sertifikat PKL saya) ·
QR dibuka di peramban (tanpa layar khusus) · fixture `test/fixtures/sertifikat/` dari `UJI_FIXTURE=… php spark dev:uji-sertifikat` ·
PROGRES-KERJA + CLAUDE.md.

## 10. Kewaspadaan kesalahan manusia

Terbit ganda (UNIQUE + kunci baris saat terbit massal) · tekan Terbitkan dua kali (token formulir sekali pakai) · periode salah ketik
(selesai < mulai, > 400 hari → tolak; tahun beda dari tahun ajaran → kuning) · nama salah → perbarui, nomor tetap · terbit sebelum PKL selesai → kuning ·
siswa pindah perusahaan → sertifikat per ajuan, ajuan lama tidak dapat bila dikeluarkan · unduh 300 siswa sekaligus → dibatasi 50 ·
latar terlalu besar → ditolak saat unggah.

## 11. Tahap

| Tahap | Isi | Bergantung |
|---|---|---|
| S1 | Fondasi: migrasi, library `SertifikatPkl` (syarat, periode, nomor, salinan, terbit/perbarui/batal), hak, setelan, uji `dev:uji-sertifikat` | jawaban 1, 2, 3, 8, 9 |
| S2 | Cetak PDF persis format sekolah + QR + CONTOH HASIL | **file contoh sekolah** + jawaban 4, 5, 6 |
| S3 | Layar staf web (daftar, terbit massal, detail, tab Pengaturan, lepas Segera) | S1, S2 |
| S4 | Publik: halaman cek sertifikat (+ Layanan Siswa bila 7a) | S1, S2 |
| S5 | API + docs + kontrak Flutter + fixture + panduan deploy + regresi | S1–S4 |
