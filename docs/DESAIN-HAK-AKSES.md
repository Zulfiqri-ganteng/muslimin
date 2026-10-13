# Rancangan: Hak Akses Diatur Admin + Lapor Diterima PKL + TTD/Stempel Kepsek

Dibuat 2026-10-13. Permintaan user: "semua akses diatur Admin — fungsi Operator, Hubin, dll. — supaya ke depan tidak perlu koding
lagi; kalau Admin mengatur keduanya ACC, sistem harus merespon sesuai maksud Admin" + permintaan klien PKL: "siswa input diterima DUDI,
sekolah memberi Surat Balasan". User: "gas semua tahap 1–7" (tanpa berhenti per tahap untuk pekerjaan ini).

## 1. Prinsip
- **Kode = daftar aksi yang ada** (katalog menu & hak). **Siapa memegang aksi = data** (tabel `peran`, diatur Admin di
  Sistem → Hak Akses). Fitur/menu BARU tetap perlu kode (menambah katalog); memindah hak antarperan tidak.
- **Admin selalu penuh** (tak bisa dicabut → sistem tak pernah terkunci). Kelola Akun & Hak Akses **khusus Admin** (kalau
  didelegasikan, peran lain bisa menaikkan haknya sendiri).
- **Bawaan = aturan lama persis** (migrasi mengisi tabel dari `Config\Peran` + `pkl_pengaturan.hak_peran`). Sebelum Admin
  mengubah centang, perilaku sistem identik (dibuktikan `php spark dev:uji-hak`: semua rute × peran × metode).
- **Tak ada lagi aturan yang menempel ke nama peran** di kode (dulu: Admin wajib centang "mewakili Waka Hubin", TTD Hubin hanya
  bila Hubin yang ACC, kaki surat per peran, honor/SKBM `role === 'admin'`).

## 2. Model data
Tabel `peran`: `kode` (PK, ≤20, huruf kecil/angka/_), `label`, `ringkas`, `beranda`, `api` (boleh aplikasi Android), `sistem`
(bawaan: admin/operator/hubin, tak bisa dihapus), `urut`, `menu` (JSON `{kunci_menu: "lihat"|"kelola"|"boleh"}`), `hak`
(JSON list kunci hak PKL/Surat), timestamps. Tabel belum ada (hosting belum migrate) → peran dibaca dari bawaan (aturan lama).

**Katalog menu** (`Libraries\MenuAdmin`, juga sumber sidebar): tiap entri = kunci, label, grup, url sidebar, awalan alamat,
jenis: `menu` (Tidak/Lihat/Kelola), `modul` (PKL & Surat Sekolah: Tidak/Boleh, isinya diatur hak), `admin` (khusus Admin),
`umum` (semua: Profil). Entri tersembunyi untuk alamat tanpa menu sendiri (honor ujian, tahun ajaran, fase, cetak, export…);
entri `ikut` = levelnya mengikuti entri lain (akar `admin/ujian`, foto lab). Alamat admin yang tak cocok entri mana pun =
khusus Admin (tolak-secara-bawaan).

**Penjaga rute** `HakPeran::boleh(peran, alamat, metode)`: umum → ya; Admin → ya; cari entri dengan awalan TERPANJANG; `menu`:
GET/HEAD butuh Lihat/Kelola, metode lain butuh Kelola; `modul`: butuh Boleh + hak per alamat (`PklHak::hakUntukAlamat`).
API (aplikasi Android) peran non-Admin tetap hanya modul PKL & Surat (awalan `pkl`, `surat`), mengikuti hak yang sama.

**Hak PKL & Surat** (`Libraries\PklHak`): `acc` (ACC/tolak/batal ACC ajuan PKL), `ubah`, `surat` (unduh Surat Izin PKL + biaya +
WA), `laporan`, `ttd` (TTD Waka Hubin), `ttd_kepsek` (BARU: TTD + stempel Kepsek), `pengaturan`, `hapus`, `hapus_disetujui`
(BARU; dulu khusus Admin). Per jenis surat sekolah: `surat_buat:{jenis}`, `surat_acc:{jenis}` (jenis bernomor), `surat_unduh:{jenis}`
+ saklar "wajib ACC" per jenis (`pkl_pengaturan.surat_perlu_acc`, sudah ada). Kunci lama `surat_sekolah` = turunan (punya
buat/unduh jenis apa pun) untuk aplikasi lama. Bawaan: Hubin = acc, ubah, ttd, surat_acc:* · Operator = surat, laporan, ubah,
pengaturan, surat_buat:*, surat_unduh:*.

## 3. Respons sesuai maksud Admin
- Beberapa peran memegang hak yang sama → semuanya melihat antrean & tombol; yang pertama menekan tercatat; yang lain melihat
  "sudah disetujui oleh …" (penjaga status asal di database, tak ada surat ganda).
- Kaki surat: "Disetujui oleh: {nama} ({label peran})" apa adanya; Admin = "(Admin Sistem)". Centang "mewakili" dihapus.
- **TTD Waka Hubin + TTD/stempel Kepsek selalu tercetak** di semua surat bernomor & Surat Izin PKL (keputusan user 2026-10-13),
  siapa pun yang ACC.
- Peran punya Buat + ACC jenis itu → tombol satu klik "Simpan & setujui" / "Setujui & buat Surat Balasan". Hanya Buat → masuk
  antrean ACC. Jenis tanpa ACC → langsung siap unduh. ACC massal hanya menyentuh jenis yang boleh di-ACC peran itu.
- Hak tak dipegang peran mana pun (selain Admin) → peringatan di Hak Akses (tetap boleh disimpan). Perubahan langsung berlaku,
  tercatat di Audit Log. Menu "Lihat saja" → halaman & unduhan terbuka, tombol simpan/hapus dikunci (server menolak POST).

## 4. Lapor diterima PKL (siswa) + kotak laporan
- Siswa: `https://{subdomain PKL}/pkl/lapor` (+ tombol di beranda form PKL, tetap tampil walau pendaftaran ditutup; saklar
  sendiri `pkl_pengaturan.lapor_buka`, bawaan buka). Alur: kelas → nama (hanya status) → nomor HP (cocok HP di ajuan / Master
  Siswa; dibatasi `LoginThrottle`) → ringkasan ajuan disetujui → tanggal mulai & selesai PKL (+ alamat perusahaan opsional,
  + catatan) → kirim. Anggota kelompok mana pun boleh melapor; kirim ulang memperbarui laporan yang belum diproses. Tanpa unggah
  berkas (keputusan user: semua surat format sekolah; TTD/stempel DUDI di kertas, opsional).
- Tabel `pkl_lapor`: pengajuan, pelapor (siswa), HP, periode, alamat, catatan, status `baru|diproses|dikembalikan`, alasan,
  surat_id, diproses oleh/kapan, IP, waktu.
- Staf: kotak "Laporan diterima dari siswa" di halaman Surat Balasan (+ kartu Beranda PKL, + detail ajuan). Pemegang
  `surat_acc:balasan_pkl` → "Setujui & buat Surat Balasan" (surat langsung disetujui atas namanya); hanya `surat_buat:balasan_pkl`
  → "Buat Surat Balasan" (masuk antrean ACC); "Periksa & ubah" membuka formulir terisi; "Kembalikan" + alasan (siswa melihatnya
  saat membuka form lagi). Unduh Balasan tidak memakai cek pembayaran (cek biaya hanya di Surat Izin PKL).

## 5. TTD + stempel Kepsek
Unggah sekali (PNG/JPG) di PKL → Tanda Tangan (hak `ttd_kepsek`, bawaan hanya Admin); disimpan `writable/pkl/ttd_kepsek.*` +
`pkl_pengaturan.ttd_kepsek` (TIDAK masuk repo publik). Saat merakit Word, gambar tanda tangan Kepsek bawaan template dikenali
dari sidik SHA-256 isinya (`35b0c864…`, relId berbeda-beda antartemplate) lalu diganti gambar unggahan: lebar 3,6 cm, tepi kanan
sejajar gambar lama, di belakang teks (meniru rapian klien di surat Polsek Babelan 13 Okt). Template unggahan sekolah yang tak
memuat gambar bawaan tidak diubah.

## 6. Tahap
- [x] T1 Mesin hak akses (katalog, tabel `peran` + migrasi, HakPeran, uji kesetaraan)
- [x] T2 Hak PKL & Surat per jenis + respons sesuai centang + halaman Sistem → Hak Akses (bagian PKL & Surat)
- [x] T3 Form lapor diterima (siswa) + migrasi `pkl_lapor`
- [x] T4 Kotak laporan + tombol satu klik + kartu Beranda PKL
- [x] T5 Unggah TTD + stempel Kepsek → semua surat
- [x] T6 Hak akses seluruh menu + peran baru + Kelola Akun + "Lihat saja"
- [x] T7 API Android + kontrak Flutter + uji regresi + panduan deploy

Deploy: `cd ~/kangmuslim && git pull && phpm spark migrate` (2 migrasi). Hasil tiap tahap dicatat di bagian 7.

## 7. Hasil (selesai 2026-10-13)

**T1 — mesin.** `Libraries\MenuAdmin` (katalog = sumber sidebar + penjaga rute + tabel Hak Akses), `Libraries\HakPeran` (baca tabel
`peran`, JSON rusak → bawaan, `boleh(peran, alamat, metode)`, beranda otomatis, peran baru), `HakAkses` = fasad. Migrasi
`2026-10-17-000001_HakAksesPeran` (tabel `peran`, diisi dari `Config\Peran` + `pkl_pengaturan.hak_peran`). `AuthFilter` sadar
metode: menu "Lihat" menolak POST dengan pesan. Uji kesetaraan: **4.230 pertanyaan (rute × peran × GET/POST), 0 beda** dengan aturan lama.
JEBAKAN: `transStart/transComplete` (mode strict) diam-diam membatalkan setelah kueri gagal sebelumnya → simpan pakai `transBegin` manual.

**T2 — hak per jenis + halaman.** `PklHak`: 9 kunci PKL + `surat_buat|acc|unduh:{jenis}`; simpan format baru (apa adanya) / lama
(beda-saklar, untuk aplikasi lama). "Mewakili" dihapus di web, API, `PklKeputusan`, `SuratKeputusan`; TTD Hubin selalu tercetak; kaki
surat "(Admin Sistem)" / "(label peran)". "Simpan & setujui" (`SuratKeputusan::accSetelahBuat`) bagi pemegang Buat + ACC. Halaman
`admin/hak-akses` (`Admin\AturHak`, tab Menu / PKL & Surat / Peran) menggantikan PKL → Hak Akses lama (alamat lama dialihkan).

**T3 — form siswa.** Migrasi `2026-10-17-000002_PklLapor` (tabel `pkl_lapor`, `pkl_pengaturan.lapor_buka` bawaan 1, `ttd_kepsek`).
`Libraries\PklLapor`, controller publik `LaporPkl` (`pkl/lapor`, `/siswa`, `/cek`, `/kirim`, `/selesai`), views `pkl/lapor*.php`,
`assets/js/pkl-lapor.js`; tombol "Lapor" di halaman form PKL (juga saat pendaftaran ditutup); saklar di Pengaturan PKL.

**T4 — kotak staf.** Menu samping **SURAT SEKOLAH → "Lapor Diterima PKL"** (`admin/surat/balasan/laporan`, `Admin\SuratLapor`):
tab Menunggu / Dikembalikan / Sudah dibuat, tombol "Setujui & buat" / "Buat & ajukan untuk ACC" / "Periksa & ubah dulu" (formulir
Balasan `?lapor=ID`, alamat bisa dikoreksi) / "Kembalikan". Klaim atomik (dua staf → yang kedua 409). Balasan yang dibuat lewat jalur
MANA PUN (`SuratPkl::buat`) menandai laporan menunggu ajuan itu selesai (`PklLapor::tautkanAjuan`). Pita di Beranda PKL & formulir
Balasan, blok status di detail ajuan (tombol Balasan memakai laporan). Sorotan menu samping = item beralamat TERPANJANG.

**T5 — TTD + stempel Kepsek.** Halaman PKL → Tanda Tangan kini 2 bagian (Waka Hubin `ttd`, Kepala Sekolah `ttd_kepsek`). Gambar di
`writable/pkl/ttd_kepsek.*` (PNG/JPG ≤ 2 MB). `PklDocx::dariTemplate(..., $ganti)` + `gantiGambar()`: gambar bawaan dikenali sha256,
diganti di SEMUA salinan surat; kotak 3,6 × 3,24 cm, tepi kanan 0,3 cm ke kiri & titik tengah 0,08 cm ke atas dari gambar lama
(= posisi rapian klien persis: V 0,15 cm). Berlaku di Surat Izin PKL + Izin ASTS/TKA + Balasan + Penarikan; Pernyataan Ortu tak berubah.
Sidik "perlu cetak ulang" berubah hanya bila gambar ada. **`.gitignore` kini `/writable/pkl/`** (sebelumnya tidak diabaikan!).

**T6 — seluruh menu.** Pagar "role === admin" mati diganti `HakAkses::boleh` + `HakAkses::tolak()` (pesan baku): Pengaturan Honor,
SKBM, Honor ujian (+ Koreksi), cetak honor, tab Honor di halaman ujian, hapus foto lab (cek menu pemilik foto). Bawaan tetap hanya
Admin. API honor/SKBM tetap khusus Admin (gerbang API hanya modul PKL/Surat). Mode **"Lihat saja"**: pita di layout +
`<main data-lihat-saja>` → `app.js` meredupkan tombol kirim & mencegat POST. Kelola Akun: kartu peran dinamis (jumlah menu & hak).

**T7 — API & dokumen.** `GET api/v1/surat/balasan/laporan`, `POST …/{id}/setujui|buat|kembalikan`; `pkl/ringkasan` + `lapor {buka,
tautan, baru}` + `surat.ttd_kepsek_ada`. Dokumen: `API-SURAT.md`, `API-PKL.md`, `C:\flutter-muslimin\BLUEPRINT-SURAT.md` &
`BLUEPRINT-PKL.md` (blok PEMBARUAN di atas), `PANDUAN-DEPLOY-HAK-AKSES.md`.

**Uji (semua lulus, 2026-10-13):** `dev:uji-hak` 67 · `dev:uji-lapor` 92 (BARU: siswa, hak, alur, halaman, form publik, API,
TTD Kepsek) · `dev:uji-surat` 312 · `dev:uji-pkl` 324 · `dev:uji-pkl-biaya` 130 · `dev:uji-honor` 496 · `dev:uji-honor-lanjut` 138 ·
`dev:uji-skbm` 104 · `dev:uji-dokumen` 103 · `dev:uji-absensi-kelas` 30 · pembanding Excel honor 43/0, koreksi 30/0, SKBM 51/0.
Semua file Word hasil (8 template + gambar pengganti) XML-nya sah. Uji HTTP lewat Apache: halaman siswa 200, staf → login, API → 401.
Tidak diuji: `dev:uji-notif` (menolak DB utama; tidak terkait), peramban sungguhan untuk `pkl-lapor.js` (diperiksa manual).

**Menyimpang dari rancangan (disengaja):** kotak laporan jadi menu sendiri (bukan di dalam halaman Surat Balasan) supaya terlihat di
sidebar; posisi stempel memakai rapian klien (tepi kanan −0,3 cm), bukan "sejajar gambar lama"; Surat Balasan yang dibuat manual ikut
menandai laporan selesai (agar laporan tidak menggantung).
