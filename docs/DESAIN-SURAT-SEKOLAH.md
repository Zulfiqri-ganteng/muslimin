# Rancangan: Surat Sekolah (Izin ASTS · Izin TKA · Pernyataan Orang Tua PKL · Surat Balasan PKL · Penarikan Izin PKL)

Dibuat 2026-10-09. **Status (2026-10-11): L1 FONDASI ✅ · L2 ALUR ACC ✅ · L3 IZIN ASTS & TKA ✅ (sudah di-push & migrate hosting) · L4 PERNYATAAN ORANG TUA ✅ · L5 SURAT BALASAN ✅ · L6 PENARIKAN ✅ (2026-10-11, sudah di-push 2d574f3; tanpa migrasi baru) — kelima menu aktif · L7 PERAPIAN ✅ · L8 API ANDROID ✅ · L9 KONTRAK FLUTTER ✅ (2026-10-12, lokal). Sisa: layar Flutter (dikerjakan AI di repo flutter-muslimin).** Dokumen kerja: centang tiap langkah (bagian 7) agar sesi baru bisa melanjutkan.
Sumber: 7 berkas Word di `formatdatasekolah/` (folder di-.gitignore: data pribadi; repo PUBLIK → template yang masuk repo HANYA berisi penanda `${…}`, tanpa nama/HP/nomor asli).
Asal: user 2026-10-08 "untuk folder formatdatasekolah saya juga perlu fiturnya, rancangan di sidebar tandai segera" → grup SURAT SEKOLAH di `app/Views/layouts/admin.php` (`'segera' => true`). 2026-10-09: "bangun… konsep yang sama seperti ajuan PKL; analisis dulu, lapor berapa step".
Arti "konsep sama seperti ajuan PKL" (tafsiran, dikonfirmasi di bagian 6): data diambil dari sistem (operator tidak mengetik ulang), surat Word PERSIS format sekolah, nomor otomatis, **Buat → ACC Waka Hubin → Unduh**, tercatat siapa/kapan, hak diatur Admin.

## 1. Bedah 7 contoh surat
| Menu | Berkas contoh | Isi | Kertas | Nomor | Data yang berubah |
|---|---|---|---|---|---|
| Surat Izin ASTS | SURAT PERMOHONAN IZIN ASTS | Pemberitahuan ASTS + mohon dispensasi untuk siswa PKL, ke "Bapak/Ibu Pimpinan / Pembimbing PKL di Tempat" | A4 | ya | nomor, tanggal surat, ASTS Ganjil/Genap, tahun pelajaran, hari+tanggal, tempat |
| Surat Izin TKA | SURAT PERMOHONAN IZIN TKA (+ Gelombang 1, + Gelombang 2) | Sama untuk TKA; ada baris "Sesi : Gelombang n" | A4 | ya | + sesi/gelombang |
| Pernyataan Orang Tua PKL | SURAT PERNYATAAN ORANG TUA PKL | Formulir 11 butir; diisi tangan orang tua + materai Rp10.000; tanpa nomor, tanpa tanda tangan sekolah | 21,5×33 | tidak | siswa: nama, kelas, konsentrasi, HP; orang tua: nama, pekerjaan, alamat, HP (titik-titik bila kosong) |
| Surat Balasan PKL | Surat Balasan PKL - Dafa … | Sekolah menyatakan perusahaan X bersedia menerima siswa; tabel siswa (NO·NAMA·KELAS·KONSENTRASI·HP); periode, lokasi, alamat | 21,5×33 | ya | nomor, tanggal, perusahaan+alamat, tabel siswa, periode PKL |
| Penarikan Izin PKL | Surat Penarikan Izin PKL BINUS - Dinas … | "Pemberhentian dan Penarikan Peserta PKL"; tabel siswa; tanggal efektif; alasan | 21,5×33 | ya | nomor, tanggal, perusahaan, tabel siswa, tanggal efektif, alasan |

Semua surat bernomor: pola `{urut}/SMK-BN/PKL/{bln_romawi}/{thn}` (sama dengan Surat Izin PKL), kop gambar TIFF, tanda tangan+stempel Kepsek berupa gambar di dalam contoh, "NB: hubungi Puguh Wira Sakti, S.Pd. 0812 8584 526" (= kontak di Pengaturan PKL).

## 2. Temuan di contoh (kesalahan manusia yang dicegah sistem)
- Nomor sama untuk surat berbeda: ASTS & TKA sama-sama "220", Balasan & Penarikan sama-sama "255".
- TKA umum tertulis "Senin s.d. Jumat, 5-8 Oktober 2026" (5–8 Okt 2026 = Senin–Kamis; versi Gelombang 1 sudah "Kamis") → nama hari dihitung dari tanggal.
- TKA Gelombang 2: acara 12–15 Oktober tetapi tanggal surat masih "5 Oktober" (sisa salin).
- Balasan: sapaan "Bapak/Ibu Rektorat" untuk sebuah CV; kalimat "3 (tiga) bulan, bulan 24 Agustus s.d. …" (kata "bulan" dobel). Usulan: sapaan dapat dipilih (bawaan "Pimpinan"), kalimat periode dirapikan — **bila user ingin 100% persis, dipertahankan**.
- ASTS/TKA menyebut "peserta didik tersebut" tanpa daftar siswa → opsi surat per perusahaan memuat daftar siswanya.
- Pernyataan: tahun "2026" tertulis tetap di baris tanggal → mengikuti tahun surat.
- Penarikan: alasan satu kalimat tetap → pilihan alasan + isian bebas.

## 3. Sudah ada dan dipakai ulang
- Mesin Word `Libraries\PklDocx::dariTemplate` (penanda `${…}`, gandakan baris tabel `${no}/${siswa_*}`, banyak surat per berkas, gambar TTD) — template baru = berkas .docx bertanda, tanpa kode baru untuk merakit.
- `PklNomorSurat` (token) + kunci `SELECT … FROM pkl_pengaturan WHERE id=1 FOR UPDATE` + lantai "nomor berikutnya" + `PklNamaBerkas`.
- Penanda tangan & kontak di `pkl_pengaturan` (`kepsek_nama`, `waka_hubin_*`, `kontak_surat_*`, `ttd_hubin`; halaman Tanda Tangan).
- ACC + kode verifikasi + kaki surat jujur (`PklKeputusan`, `PklSurat::catatanAcc`), hak yang diatur Admin (`PklHak`, halaman Hak Akses), penjaga rute (`HakAkses`, `Config\Peran`), riwayat, Audit Log.
- Data: `pkl_pengajuan/anggota/perusahaan` (ajuan disetujui); `siswa` (`nama_orang_tua` 100%, alamat orang tua 1.726 dari 1.727; pekerjaan & HP orang tua umumnya kosong); kelas/jurusan; `ujian_periode` (jenis ASTS1/ASTS2, `tanggal_mulai/selesai` → tanggal ASTS bisa terisi otomatis; **TKA tidak ada di modul Ujian → diketik**).
- Pola layar: tab Menunggu/Disetujui, dialog, unduh dengan cookie `unduh_selesai`, panduan halaman (`helpKey`).

## 4. Yang baru (usulan)
- Tabel `surat_sekolah` (jenis, tahun, urut, nomor, tanggal_surat, perusahaan/pengajuan bila ada, isi JSON [kegiatan, tanggal, sesi, periode, alasan, sapaan], status, ACC [siapa/kapan/peran/kode], sidik, cetak_ke, dibuat_oleh) + `surat_sekolah_siswa` (surat_id, siswa_id, kelas & HP saat itu).
- **Nomor satu urutan dengan Surat Izin PKL**: `PklSurat::terbitkan` diubah 1 baris (MAX urut ikut tabel baru) di bawah kunci yang sama; nomor ditetapkan SEKALI saat pertama diunduh; surat bernomor tidak dihapus, hanya "dibatalkan" (nomor tetap tercatat, tak dipakai ulang).
- 5 template Word dari contoh (ASTS, TKA, Pernyataan, Balasan, Penarikan); tanpa fitur unggah template (bawaan = contoh sekolah).
- Hak baru `surat_sekolah` (buat & unduh; bawaan Operator) di Hak Akses; ACC memakai hak `acc` yang sudah ada (bawaan Waka Hubin).
- Alamat web `admin/surat/…`: Daftar Surat (buku agenda surat keluar + Menunggu ACC), `asts`, `tka`, `pernyataan-ortu`, `balasan`, `penarikan`. Sidebar: `'segera'` dipindah dari grup ke tiap item (yang selesai jadi menu biasa).
- Kolom `pkl_pengajuan.tanggal_mulai/selesai` SUDAH ada tetapi tidak ada layar yang mengisinya (dihapus dari form 2026-10-07) → diisi lewat form Surat Balasan, ditampilkan di detail ajuan.

## 5. Aturan ringkas
- Pernyataan Orang Tua: tanpa nomor/ACC; cetak per kelas / per ajuan / per siswa; nama & alamat orang tua dari data sekolah bila ada, sisanya titik-titik; ada pilihan "kosongkan semua".
- Balasan & Penarikan: hanya untuk ajuan berstatus Disetujui; Penarikan memilih siswa dalam ajuan itu (boleh sebagian).
- ASTS/TKA: mode umum (1 surat, 1 nomor, persis contoh) + mode per perusahaan (nama perusahaan + daftar siswanya, nomor beda-beda; perusahaan yang periodenya tak menjangkau tanggal acara diberi tanda bila periode PKL sudah terisi).
- Tanggal: nama hari dihitung otomatis; rentang lintas bulan benar ("28 September – 2 Oktober 2026"); tanggal surat tak boleh setelah acara.
- Surat bernomor mengikuti Surat Izin PKL: blok Waka Hubin (nama, jabatan, TTD digital bila yang ACC akun Hubin) + kaki "disetujui secara elektronik oleh … pada …, kode …".
- Sidik data → penanda "perlu cetak ulang" bila data berubah setelah diunduh (nomor tetap).

## 6. Keputusan (user 2026-10-09 membalas "silahkan gas" → SEMUA usulan pilihan 1 berlaku)
1. Alur & siapa yang mengisi: (1) staf menyiapkan, Waka Hubin ACC, Operator unduh — siswa tidak mengisi form baru; (2) tambah form publik siswa "Konfirmasi diterima + tanggal PKL" untuk Balasan (+1 langkah).
2. ACC: (1) semua surat bernomor (ASTS, TKA, Balasan, Penarikan) di-ACC Waka Hubin, Admin bisa mematikan per jenis; Pernyataan tanpa ACC; (2) hanya Balasan & Penarikan; (3) tanpa ACC (tinggal catat siapa yang mencetak; −1 langkah).
3. Nomor: (1) satu urutan bersama Surat Izin PKL; (2) urutan terpisah per jenis (risiko nomor sama antar jenis).
4. ASTS/TKA: (1) umum + per perusahaan; (2) umum saja.
5. Penarikan: (1) hanya surat + riwayat, status PKL siswa tidak berubah; (2) otomatis menandai siswa "ditarik" (boleh mengajukan PKL lagi) — menyentuh inti PKL (`siswa_aktif`), uji ekstra.

## 7. Langkah (web)
- [x] **L1 Fondasi ✅ (2026-10-09)** — hasil di bagian 8.
- [x] **L2 Alur ACC ✅ (2026-10-09)** — hasil di bagian 8b.
- [x] **L3 Izin ASTS & TKA ✅ (2026-10-09)** — hasil di bagian 8c.
- [x] **L4 Pernyataan Orang Tua PKL ✅ (2026-10-11)**: template, cetak per kelas/ajuan/siswa, data orang tua otomatis — hasil di bagian 8d.
- [x] **L5 Surat Balasan PKL ✅ (2026-10-11)**: template, periode PKL (isi `tanggal_mulai/selesai`), per ajuan & massal, tautan di detail ajuan — bagian 8d.
- [x] **L6 Penarikan Izin PKL ✅ (2026-10-11)**: template, pilih siswa, alasan, tanggal efektif; status PKL siswa TIDAK berubah (keputusan 5 no.1) — bagian 8d.
- [x] **L7 Perapian ✅ (2026-10-12)**: panduan halaman, kartu ringkas di Beranda PKL, Excel Daftar Surat, regresi, `docs/PANDUAN-DEPLOY-SURAT.md`, contoh di Word asli — bagian 8e.
- [x] **L8 API Android ✅ (2026-10-12)**: `Api\Surat` + 15 rute `api/v1/surat…` + `docs/API-SURAT.md`; Operator & Hubin `api_akses` + `surat`; uji HTTP 35/35.
- [x] **L9 Kontrak Flutter ✅ (2026-10-12)**: `C:\flutter-muslimin\BLUEPRINT-SURAT.md` (tahap S0–S5) + 23 fixture nyata `test/fixtures/surat/` + `CLAUDE.md` Flutter diperbarui.

## 8. Hasil L1 (Fondasi) — 2026-10-09
**Dibangun:** migrasi `2026-10-14-000001_CreateSuratSekolah` (tabel `surat_sekolah`, `surat_sekolah_siswa`, `surat_sekolah_riwayat` + kolom `pkl_pengaturan.surat_perlu_acc`; hak `surat_sekolah` ditambahkan ke Operator bila Admin pernah menyimpan Hak Akses) ·
`Libraries\SuratJenis` (5 jenis, alamat, bernomor?, ACC bawaan/aturan Admin, `SIAP` = daftar jenis yang halamannya sudah ada) · `Libraries\SuratNomor` (maksTahun/urutBerikutnya/pola — SATU urutan dengan `pkl_surat`; `PklSurat::terbitkan` kini memanggilnya, diganti 3 baris → 1 baris, dengan penjaga `tableExists` supaya hosting yang belum migrasi tetap bisa menerbitkan Surat Izin PKL) ·
`Libraries\SuratSekolah` (buat · muat · siswa · riwayat · daftar · hitungStatus · terbitkan nomor · sidik · catat; siswa tanpa kelas diisi kelas SAAT INI) · `Models\SuratSekolahModel` (status & lencana, kode `SRT-00012`) ·
`PklHak` (hak `surat_sekolah` + pemetaan alamat `admin/surat/…`: kosong/{id} = terbuka; `{id}/acc|kembalikan|batal-acc`, `acc-massal` = `acc`; sisanya = `surat_sekolah`; TIDAK wajib dipegang siapa pun; peringatan pemisahan tugas ikut) · `Config\Peran` (Operator & Hubin + `admin/surat`) ·
`Admin\SuratDasar` + `Admin\Surat` (Daftar Surat dengan tab status + saringan jenis/tahun/kata kunci + halaman; detail read-only) · views `admin/surat/_nav|daftar|detail` · rute `admin/surat`, `admin/surat/(:num)` · sidebar: grup SURAT SEKOLAH kini dibangun dari `SuratJenis` — item redup "Segera" per jenis sampai kodenya masuk `SuratJenis::SIAP` · teks "satu urutan" di Pengaturan PKL · `tailwind.config.js` ikut memindai `SuratJenis.php` & `SuratSekolahModel.php` (kelas warna lencana) dan CSS dibangun ulang.
**Cara menambah jenis yang selesai:** buat halamannya, lalu tambahkan kode jenisnya ke `SuratJenis::SIAP` → menu otomatis aktif.
**Uji:** `php spark dev:uji-surat` **97 lulus** (jenis/ACC, 20 alamat→hak, 12 alamat × 5 peran, register, 8 masukan tidak sah, nomor bersama dengan Surat Izin PKL termasuk lantai/tahun/format rusak, sidik, UNIQUE/CASCADE/SET NULL, render nyata Daftar+detail untuk Operator/Hubin/Admin) · regresi `dev:uji-pkl` 322 ✅ · `dev:uji-pkl-biaya` 124 ✅.
**Deploy (bila user push):** `git pull` lalu `phpm spark migrate` (1 migrasi baru). Tanpa migrasi, Surat Izin PKL tetap normal (penjaga `tableExists`), tetapi menu Daftar Surat akan error karena tabelnya belum ada. API Android: peta `hak_pkl` kini memuat kunci baru `surat_sekolah` (menambah kunci saja; kontrak Flutter diperbarui di L8/L9).
**Catatan kerja bersama:** pada saat L1 dikerjakan, sesi AI lain sedang membangun API Honor (`Api/Admin/Honor*.php`, `docs/API-HONOR.md`, bagian API di `Routes.php`) — tidak ada tumpang tindih dengan berkas surat sekolah selain `Routes.php` (bagian berbeda).

## 8b. Hasil L2 (Alur ACC) — 2026-10-09
**Alur status:** `menunggu` ─ACC→ `disetujui` (unduh pertama: nomor terbit) · `menunggu` ─kembalikan→ `dikembalikan` ─ajukan ulang→ `menunggu` · `disetujui` ─batal ACC (hanya selama BELUM bernomor & BELUM diunduh)→ `dikembalikan` · `menunggu|dikembalikan|disetujui` ─batalkan surat→ `dibatalkan` (nomor yang sudah terbit tetap tercatat dan tak dipakai lagi; `MAX(urut)` ikut menghitungnya). Jenis tanpa ACC (Pernyataan, atau jenis yang dimatikan Admin) langsung `disetujui` dengan `perlu_acc=0`.
**Hak:** ACC / kembalikan / batal-ACC = hak `acc` (bawaan Waka Hubin; Admin cadangan WAJIB mencentang "mewakili Waka Hubin" → riwayat "Mewakili Waka Hubin — …", `acc_peran='admin'`). Ajukan ulang / batalkan surat / unduh = hak `surat_sekolah` (bawaan Operator). Dijaga dua lapis: rute (`PklHak::hakUntukAlamat`) dan `Libraries\SuratKeputusan` (penolakan → Audit Log "DITOLAK: … <nama pelaku> mencoba …").
**Dibangun:** `Libraries\SuratKeputusan` (putuskan, accMassal ≤200, `kodeVerifikasi` SRT-00012-8F3A9C berbasis HMAC; update dijaga status asal → dua staf bersamaan tak saling menimpa) · `Libraries\SuratPeriksa` (bahaya = tanpa siswa pada jenis yang butuh siswa / tanpa perusahaan → ACC satuan wajib centang "sudah saya periksa"; awas = nama Kepsek/Waka Hubin/kontak NB kosong → tidak memblokir; ACC massal MELEWATI surat yang punya peringatan apa pun) · `Libraries\SuratBerkas` (perakit Word: `bangun()` → hanya `disetujui`, nomor terbit sekali, ≤60 surat 1 berkas / >60 ZIP per 50, pembukuan cetak_ke+sidik+riwayat setelah berkas jadi; penanda umum + kaki surat [wajib-ACC: catatan ACC lengkap seperti Surat Izin PKL; tanpa ACC: hanya "Dicetak oleh …"] + blok Waka Hubin + TTD digital HANYA bila yang ACC akun Hubin; hook `khusus()` untuk penanda tiap jenis di L3–L6) · `SuratSekolah::perluCetakUlang|perluUlangBanyak|pilihUntukUnduh` · `Admin\Surat` (acc, kembalikan, batalAcc, ajukanUlang, batal, accMassal, unduh, unduhMassal) · rute POST `admin/surat/{id}/acc|kembalikan|batal-acc|ajukan-ulang|batal|unduh`, `admin/surat/acc-massal|unduh-massal`, `admin/pkl/hak-akses/surat-acc` · Daftar Surat: ACC massal (tab Menunggu), unduh massal (tab Siap unduh + satu jenis, 3 mode), penanda "⚠ perlu cetak ulang", info penyetuju · detail surat: peringatan, kartu Tindakan, 4 dialog (ACC, kembalikan, batal ACC, batalkan surat) · halaman PKL → Hak Akses: bagian "Surat Sekolah: jenis yang wajib ACC" (`SuratJenis::simpanAcc`, Audit Log) · `PklSurat::REL_TTD` jadi `public`.
**Uji:** `php spark dev:uji-surat` **188 lulus** (L1 + 91 cek baru: matriks ACC/kembalikan/ajukan ulang/batal untuk Operator·Hubin·Admin·peran asing, kode verifikasi, nomor tak dipakai ulang setelah batal, ACC massal terpilih/aman/awas/Admin, aturan ACC per jenis, kaki surat, penanda, TTD, berkas Word nyata [isi dibaca dari .docx], unduh ulang, perlu-cetak-ulang, banyak surat, 6 galat jelas, ZIP 61 surat, controller + tampilan per peran) · regresi `dev:uji-pkl` 322 ✅ · `dev:uji-pkl-biaya` 124 ✅. Penimpa uji `SuratJenis::$templateTimpa` (kosong di pemakaian biasa) memakai template Surat Izin PKL sebagai pengganti sampai template tiap jenis ada; uji TIDAK menyentuh folder `Libraries/Surat`.
**Belum (sengaja):** tombol "Ubah" data surat tiap jenis (di L3–L6, bersama formulir pembuatnya) · kartu "Surat menunggu ACC" di Beranda PKL (L7) · API Android (L8).

## 8c. Hasil L3 (Izin ASTS & TKA) — 2026-10-09
**Menu aktif:** `Surat Izin ASTS` (`admin/surat/asts`) dan `Surat Izin TKA` (`admin/surat/tka`) — `SuratJenis::SIAP` = [ASTS, TKA]. Tanpa migrasi baru.
**Template Word (4 berkas, dibuat dari contoh sekolah lewat skrip sekali pakai; bersih dari data pribadi, hanya penanda `${…}`):** `izin_asts.docx`, `izin_tka.docx` = surat UMUM, BADAN SURAT sama persis dengan contoh sekolah (diuji otomatis terhadap `formatdatasekolah/…ASTS.docx` dan `…TKA Gelombang 1.docx`); `izin_asts_perusahaan.docx`, `izin_tka_perusahaan.docx` = surat per perusahaan: "Lampiran : 1 (satu) lembar", kalimat "(sebagaimana daftar terlampir)", dan halaman 2 **LAMPIRAN** (judul, nomor surat, perusahaan, kegiatan, tanggal + tabel NO·NAMA·NIS·KELAS·KONSENTRASI·HP). Ekor surat = blok Waka Hubin + Kepala Sekolah + NB + kaki ACC (dicangkok dari template Surat Izin PKL). Kertas A4 seperti contoh. **Diperiksa di Microsoft Word asli (COM): surat umum = 1 halaman; per perusahaan = 2 halaman (halaman 1 identik dengan surat umum, blok NB tetap di halaman 1; halaman 2 = Lampiran).** Ruang sisa halaman 1 sangat tipis (±16 pt), karena itu alamat per perusahaan dibuat SATU baris ("Bapak/Ibu Pimpinan {perusahaan}"); nama perusahaan yang sangat panjang (>±60 huruf) bisa membuat blok NB turun ke halaman 2.
**Dibangun:** `Libraries\SuratAcara` (format tanggal otomatis: nama hari dihitung — "Senin s.d. Kamis, 5 - 8 Oktober 2026", lintas bulan/tahun, satu hari; validasi masukan; judul; kunci pencegah ganda; penanda template; daftar perusahaan dari ajuan DISETUJUI [siswa aktif saja, digabung per perusahaan, penanda "di luar periode PKL" bila periode ajuan sudah tercatat]; periode ASTS dari `ujian_periode`; `buat()`) · `SuratSekolah::ubah()` (ubah data surat menunggu/dikembalikan yang belum bernomor; daftar siswa bisa diganti) · `SuratJenis::varian()/pathTemplate($kode,$varian)/templateLengkap()` · `SuratBerkas::bangun()` mengenali varian template (campuran umum + per perusahaan → ZIP dua berkas; template dicek SEBELUM nomor terbit) · `Admin\SuratIzin` (formulir baru, simpan, ubah, simpan ubah) · view `admin/surat/izin_form.php` + `public/assets/js/admin/surat-izin.js` (pratinjau "Hari/Tanggal" langsung, isi dari menu Ujian, saringan & centang perusahaan; fungsi tanggalnya diuji di Node sama dengan PHP) · tombol "✎ Ubah data surat" di detail · rute `admin/surat/{asts|tka}` (GET/POST) dan `…/{id}/ubah` (GET/POST).
**Aturan:** tanggal surat tak boleh setelah kegiatan selesai; rentang maks 31 hari; ASTS: semester + tahun pelajaran `2026/2027` (tahun kedua = pertama + 1); TKA: sesi/gelombang wajib (saran Gelombang 1–3, boleh ketik sendiri). **Pencegah ganda:** kegiatan + tanggal + sesi/semester + cakupan (+ perusahaan) yang sama tidak bisa dibuat dua kali selama suratnya belum dibatalkan (juga saat mengubah). Tanggal surat acara ditulis tanpa nol ("Bekasi, 5 Oktober 2026") mengikuti contoh. Isian JSON `surat_sekolah.isi`: kunci, pkey (kunci perusahaan), kegiatan, semester, tahun_pelajaran, sesi, tgl_mulai, tgl_selesai, tempat, mode (disembunyikan: kunci, pkey).
**Uji:** `php spark dev:uji-surat` **258 lulus** (L1+L2 + 70 cek baru: format tanggal 6 bentuk, 12 masukan buruk, kunci ganda, penanda, varian template, perusahaan fixture [urut pengaju, periode, siswa non-aktif], buat/kembar/dibatalkan, ubah [4 status], formulir & controller per peran, 4 template [penanda dikenal, tanpa data asli], badan surat = contoh sekolah, Lampiran, unduhan campuran → ZIP) · regresi `dev:uji-pkl` 322 ✅ · `dev:uji-pkl-biaya` 124 ✅. Contoh hasil Word ada di `downloadpublic/CONTOH HASIL - Izin …` (folder diabaikan git; isi uji: nama "ZZUJI SS", Waka Hubin "Hubin Uji").
**Belum diuji di peramban sungguhan:** tampilan formulir (Alpine) — di-render di PHP dan logika JS-nya diuji di Node, tetapi belum dibuka di Chrome.

## 8d. Hasil L4–L6 (Pernyataan Orang Tua · Surat Balasan · Penarikan Izin PKL) — 2026-10-11
**Permintaan user:** "outputnya wajib 100% sama seperti contoh, hanya datanya yang berubah". **Menu aktif:** `SuratJenis::SIAP` = kelima jenis (tak ada lagi "Segera"). **Tanpa migrasi baru** (deploy = `git pull`).
**Template Word (dibuat dari contoh sekolah lewat skrip sekali pakai di scratchpad — `buat_template_pkl.php`, TIDAK di repo; bersih dari data asli, diuji otomatis):**
- `pernyataan_ortu.docx` — contoh persis (F4 21,5×33): 8 kotak titik-titik jadi `${ortu_nama|ortu_pekerjaan|ortu_alamat|ortu_hp|anak_nama|anak_kelas|anak_jurusan|anak_hp}`, kurung tanda tangan `(${ttd_anak})`/`(${ttd_ortu})`, tahun `${tahun}`. Satu halaman per siswa. Uji: template + isian titik-titik = SELURUH teks contoh sama persis.
- `balasan_pkl.docx`, `penarikan_pkl.docx` — badan surat contoh persis (nomor, sapaan `Bapak/Ibu ${sapaan}`, perusahaan, tabel NO·NAMA·KELAS·KONSENTRASI·HP tanpa NIS seperti contoh, ketentuan "`${lama_pkl}`, bulan `${periode_pkl}`" — kata "bulan" ganda dari contoh DIPERTAHANKAN —, tanggal efektif tetap TEBAL, `${alasan}`). Ekor tanda tangan = ekor template Surat Izin PKL (dokumen dasar sama: tab 5760 = 8 tab contoh, gambar TTD Kepsek rId9 identik) → blok Waka Hubin kiri + Kepsek kanan; NB milik contoh (nomor otomatis Word). **Kaki ACC** TIDAK berupa bingkai dasar halaman (pada surat padat 4 siswa bingkai itu menabrak NB → 2 halaman). **Sejak 2026-10-21 (permintaan klien) kaki ACC = paragraf TERAKHIR surat, di bawah "NB : … 1. kontak"** (dulu di ruang dua baris kosong di atas "NB :"): 6,5 pt abu, garis tipis di atas (tanpa jarak baris tetap — gabungan garis + jarak tetap membuat Word memindahkannya ke halaman 2), 2 baris (`SuratPkl::kakiDuaBaris()`: "disetujui secara elektronik" + penyetuju · waktu, kode, pencetak). Agar 5 siswa tetap 1 halaman F4, dua baris kosong sesudah nama penanda tangan dan baris kosong sebelum kontak "1. …" DIHAPUS. **Diukur di Microsoft Word (COM, `Information(4)` jumlah halaman):** Balasan & Penarikan 1/4/5 siswa (termasuk 3 ajuan asli beranggota 5) = 1 halaman; dengan template lama Balasan 5 siswa ternyata 2 halaman (baris kontak pindah) — kini ikut beres. **Diperiksa di Microsoft Word (COM):** Balasan dengan 4 siswa persis contoh = 1 halaman, posisi "Kepala SMK Bina Nusa" 24,96 cm, "NB :" 29,33 cm, "Jika ada…" 29,81 cm — SAMA dengan contoh; Penarikan NB 26,39 vs 26,37 cm (beda isi baris); Pernyataan "Materai" 29,3 cm sama; kertas 21,5×33.
**Dibangun:** `Libraries\SuratPernyataan` (cakupan kelas/ajuan/siswa, `ortu()` [nama orang tua → ayah → ibu → wali; pekerjaan hanya milik orang yang tercetak; "Sudah Meninggal" → titik; alamat lengkap tanpa mengulang RT/kelurahan; HP ortu → HP wali], `halaman()` per siswa, `buat()` + pencegah ganda, HP dari ajuan PKL aktif) · `Libraries\SuratPkl` (ajuan disetujui + Balasan/Penarikan yang ada, `periksa()`, lama PKL = hari ÷ 30,4375 dibulatkan, `periodeTeks()`, sapaan, alasan [5 saran, saran ke-1 = kalimat contoh; ketikan dirapikan], kunci ganda [Balasan satu per ajuan; Penarikan ajuan+siswa+tanggal], `buat()`/`ubahSurat()`, jejak di riwayat ajuan: aksi `periode` [periode disimpan ke `pkl_pengajuan`] & `surat_sekolah`, `peringatanPeriode()` terhadap Pengaturan PKL [tidak memblokir; muncul sebagai "awas" sehingga ACC massal melewatinya]) · `SuratBerkas`: penanda khusus Balasan/Penarikan, Pernyataan = satu halaman per siswa (beberapa surat > 60 halaman → ZIP satu Word per surat; batas 600 halaman), `jumlah` = jumlah surat · `SuratSekolah`: kolom data orang tua ikut dibaca, sidik Pernyataan berisi data orang tua (jenis lain rumus sidik TETAP), `adaKunci()` (SuratAcara::adaSama memakainya) · `SuratPeriksa`: peringatan periode & alamat kosong untuk Balasan · `SuratJenis`: SIAP 5 jenis, label isian · controller `Admin\SuratPernyataan`, `Admin\SuratPkl` (+ `SuratDasar::muatBisaDiubah/nilaiAwal/idLama`, dipindah dari SuratIzin) · views `admin/surat/pernyataan_form.php`, `admin/surat/pkl_form.php` + JS `public/assets/js/admin/surat-pkl.js` (pratinjau = rumus server; dibandingkan 1.218 pasangan tanggal + 7 alasan: 0 beda) · 10 rute `admin/surat/{pernyataan-ortu|balasan|penarikan}[/{id}/ubah]` · detail ajuan PKL: kartu "Surat sekolah" (surat tertaut + tombol Buat Balasan / Penarikan / Cetak Pernyataan, `?ajuan=ID` memilih otomatis) + label riwayat baru.
**BUG diperbaiki (mesin bersama):** `PklDocx::gandakanBaris` memakai regex `(?:(?!<w:tr[ >]).)*` yang kehabisan tumpukan JIT PCRE pada baris tabel panjang → `preg_replace_callback` NULL → baris siswa tidak digandakan dan `${no}` tercetak apa adanya (diam-diam). Kini pola tanpa runut-balik per huruf `<w:tr[ >][^<]*+(?:<(?!w:tr[ >]|\/w:tr>)[^<]*+)*+<\/w:tr>` — untuk template lama (PKL, ASTS/TKA) hasilnya identik byte-per-byte — dan kegagalan apa pun jadi galat yang jelas.
**Uji:** `php spark dev:uji-surat` **309 lulus** (L1–L3 + 51 cek baru: murni, template [penanda dikenal, bersih dari data asli — kata terlarang dibaca dari contoh saat uji, tak ditulis di repo], badan Balasan & Penarikan = contoh sekolah [data contoh dibaca dari berkasnya; baris siswa dibandingkan terpisah], Pernyataan = contoh utuh, buat/ubah/ganda/riwayat ajuan, berkas Word, perlu cetak ulang data ortu, controller per peran, detail ajuan) · regresi `dev:uji-pkl` 324 · `dev:uji-pkl-biaya` 124 · peramban Chrome tanpa layar 17/17 (skrip di scratchpad, bukan repo). `UJI_SURAT_KELUAR=<folder>` menyimpan contoh hasil; salinan untuk dilihat: `downloadpublic/CONTOH HASIL - …` (diabaikan git).
**Cara pakai:** Daftar Surat / menu SURAT SEKOLAH → Pernyataan Orang Tua PKL (pilih kelas/ajuan/siswa → Buat → Unduh; tanpa ACC) · Surat Balasan PKL (centang ajuan disetujui, isi periode → menunggu ACC Waka Hubin → Operator unduh; nomor terbit saat unduh pertama) · Penarikan Izin PKL (pilih ajuan, centang siswa, tanggal & alasan → ACC → unduh). Ketiganya juga dari detail ajuan PKL.

## 8e. Hasil L7 (Perapian) — 2026-10-12
**Panduan halaman:** kelima halaman + Daftar + detail sudah punya kartu panduan (sejak L1–L6); Daftar Surat ditambah penjelasan tombol Excel.
**Kartu Beranda PKL:** 3 kartu *Surat sekolah menunggu ACC / belum diunduh (disetujui, `cetak_ke = 0`) / dikembalikan* → tautan ke tab Daftar Surat yang sesuai. Tampil bagi pemegang hak `acc` ATAU `surat_sekolah`; dijaga `tableExists('surat_sekolah')` (hosting yang belum migrasi tidak galat).
**Excel Daftar Surat:** `GET admin/surat/excel?status=&jenis=&tahun=&q=` (`Admin\Surat::excel`, `Libraries\SuratAgenda`) = *Buku Agenda Surat Keluar*: kop sekolah, saringan yang sedang tampil, SEMUA halaman (maks 5.000), 14 kolom (No · Nomor · Tanggal · Jenis · Perihal · Tujuan/Perusahaan · Jml siswa · Status · Disetujui oleh [+ "mewakili Waka Hubin"] · Waktu ACC · Kode ACC · Dibuat oleh · Dibuat pada · Diunduh), isian ditulis sebagai TEKS. Hak: sama dengan membuka daftar (`PklHak::hakSurat('excel')` = null). Penyaring dipindah ke `Surat::saringan()` (dipakai daftar & Excel).
**Contoh di Word asli:** sudah dilakukan lewat COM di L3 (ASTS/TKA: umum 1 hal, per perusahaan 2 hal) dan L4–L6 (Balasan, Penarikan, Pernyataan; posisi baris = contoh) — lihat 8c/8d. Pemeriksaan mata terakhir oleh user saat uji di hosting (`docs/PANDUAN-DEPLOY-SURAT.md` langkah 3).
**Uji:** `dev:uji-surat` 309 ✅ · `dev:uji-pkl` 324 ✅ · `dev:uji-pkl-biaya` 124 ✅ · HTTP: Excel agenda (Admin/Operator/Hubin), kartu Beranda PKL & tombol Excel untuk 3 peran, isi baris Excel (nomor, tanggal, tujuan, status, "mewakili Waka Hubin", jumlah unduh).
**Catatan bersama hari yang sama (SEC1):** sejak 2026-10-12 filter `csrf` berlaku untuk SEMUA rute admin (`Config\Filters::$filters`); rute surat yang sudah memakai `['filter' => 'csrf']` tidak diperiksa dua kali (alias sama → digabung CI).

## 8e. Revisi klien 2026-10-21 — Surat Balasan: staf mengatur siswa yang diterima; kaki ACC di paling bawah
- **Keluhan:** daftar siswa Surat Balasan terkunci (semua anggota ajuan); bila perusahaan hanya menerima sebagian, operator tidak bisa
  memperbaiki — hanya lewat lapor ulang siswa (kasus nyata: PKL-00032, Saqila tidak diterima, diperbaiki manual dengan SQL).
- **Sekarang:** formulir Buat & Ubah Balasan menampilkan centang **"Siswa yang diterima perusahaan"** per ajuan (`atur_terima[{ajuan}]`,
  `terima[{ajuan}][]`); yang dilepas → `SuratPkl::pilahTerima()` (minimal 1 diterima, alasan ≥ 5 huruf, bawaan = template alasan
  "Ditolak perusahaan") → `lepasTolak()` = `PklKeluar::keluarkan(jenis ditolak_perusahaan, periksaHak false)` SEBELUM surat
  disimpan — aturan yang sama dengan memproses laporan "sebagian diterima" (hak Buat Balasan, tanpa hak ACC ajuan; surat tetap
  melewati ACC Waka Hubin). Ubah tanpa `atur_terima` = daftar tetap. API: `diterima` (buat) / `siswa` (ubah) + `alasan_tolak`,
  `data.dilepas`. Uji `dev:uji-surat` bagian "centang siswa yang diterima" (11 cek) + uji peramban Chrome 7/0.
- **Kaki ACC** Balasan & Penarikan dipindah ke paling bawah (lihat 8d `balasan_pkl.docx`), diukur di Word sampai 5 siswa = 1 halaman.

## 9. Risiko & kewaspadaan
- Membuat template dari contoh: teks bervariasi sering terpecah jadi beberapa "run" Word → pakai pola yang sama dengan `surat_pkl_binus.docx`; template di repo bersih dari data asli.
- Mengubah `PklSurat::terbitkan` menyentuh fitur yang sudah LIVE → wajib regresi `dev:uji-pkl` + `dev:uji-pkl-biaya` sebelum lapor.
- Tampilan visual Word tidak bisa dirender otomatis di mesin ini (PDF via COM macet; tak ada LibreOffice) → uji struktur otomatis + user membuka satu contoh tiap jenis di Word (seperti PKL).
- Migrasi baru perlu `phpm spark migrate` di hosting; cadangkan DB seperti biasa. Claude tidak commit/push.

## 10. Layanan Siswa — siswa mengajukan surat sendiri lewat satu link (permintaan klien 2026-10-21)
**Permintaan:** siswa cukup membuka SATU link lalu memilih: Biodata · Pengajuan PKL · Lapor hasil PKL (Surat Balasan) · Izin ASTS ·
Izin TKA (KHUSUS kelas XII) · Pernyataan Orang Tua · Penarikan PKL. Surat diserahkan siswa ke perusahaan. **Operator yang ACC**
(tanda tangan Waka Hubin tetap tercetak — sudah begitu sejak 2026-10-13). Layar staf menampilkan kotak per jenis surat yang diajukan siswa.

**Keputusan user (semua saran diterima):** (1) siswa membuktikan diri dengan NISN ATAU tanggal lahir (HP siswa di data sekolah hampir
kosong; NISN 100%, tanggal lahir 99%) · (2) ACC Izin ASTS, Izin TKA, Penarikan pindah ke Operator · (3) staf membuka JADWAL
kegiatan dulu (ASTS dari menu Ujian, TKA per gelombang) — siswa memilih jadwal · (4) Izin ASTS/TKA hanya untuk siswa yang ajuan
PKL-nya disetujui di sistem (perusahaan dari sistem) · (5) Pernyataan Orang Tua: siswa mengisi data orang tua, surat langsung siap,
Operator mencetak · (6) Penarikan boleh diajukan siswa, Operator memeriksa + ACC, status PKL tidak berubah · (7) link baru, link lama
tetap jalan · (8) kabar WhatsApp "siap diambil" sekali klik. Satu surat izin per perusahaan per jadwal: siswa kedua dari perusahaan
yang sama ikut surat yang sama (tidak dobel).

**Tahap:** T1 fondasi ✅ · T2 halaman satu link + verifikasi + status permintaan · T3 form Izin ASTS/TKA · T4 form Pernyataan Orang
Tua + Penarikan · T5 layar staf (kotak per jenis, lencana "Diajukan siswa", lonceng, WhatsApp) · T6 API Android + kontrak Flutter.

**T1 (2026-10-21) — dibangun:** migrasi `2026-10-26-000001_SuratPermintaanSiswa` (tabel `surat_jadwal`, `surat_permintaan`
[satu baris per permintaan, tertaut ke surat; isian khusus JSON], kolom `surat_sekolah.sumber` staf|siswa, hak ACC
`surat_acc:izin_asts|izin_tka|penarikan_pkl` dipindah Hubin → Operator bila isian hak peran sah, tercatat di Audit Log) ·
`Libraries\SuratJadwal` (periksa/simpan/buka-tutup/hapus, keadaan buka|tutup|selesai, `terbuka()` untuk siswa, `isiSurat()` →
isian surat acara per perusahaan; jadwal yang sudah dipakai permintaan terkunci) · halaman `admin/surat/jadwal` (`Admin\SuratJadwal`,
menu "Jadwal ASTS & TKA" di akhir grup SURAT SEKOLAH; hak = Buat Izin ASTS ATAU Buat Izin TKA; tanpa Alpine) · **perbaikan:**
`SuratAcara::TINGKAT` — surat TKA per perusahaan hanya memuat siswa yang SEKARANG kelas XII (dulu ikut kelas XI; dipakai form web,
API, dan pembuatan surat). Uji: `dev:uji-surat` 336 (+11 jadwal, +1 TKA XII), `dev:uji-hak` 69, `dev:uji-api-android` 19, cek-ui 0;
salinan hosting: migrasi jalan, TKA 14 siswa XII dari 11 perusahaan (ASTS 115 siswa / 46 perusahaan).

**Tambahan sebelum T2 (2026-10-21, permintaan user): Izin ASTS → Surat Izin ASTS/ASAS/ASAT.** Kode jenis tetap `izin_asts` (data &
hak lama). Template `izin_asts.docx` + `izin_asts_perusahaan.docx`: tiga kata "ASTS" yang tertulis mati diganti `${singkatan}`
(selain itu berkas sama; badan surat ASTS tetap = contoh sekolah, diuji). `SuratAcara::UJIAN` (ASTS, ASAS, ASAT) + isian `kegiatan`
(bawaan ASTS; ASAT selalu Genap & tercetak tanpa semester), judul/`namaPendek`, sidik pencegah ganda ikut kode ujian (ASTS tidak →
sidik surat lama sama), `periodeUjian()` membaca ASTS 1/ASAS/ASTS 2/ASAT. Form staf: pilihan Ujian (Alpine, semester tersembunyi
untuk ASAT). Jadwal: kolom `surat_jadwal.kegiatan`, bagian "Jadwal Ujian (ASTS, ASAS, ASAT)", menu "Jadwal Ujian & TKA". API
`surat/form/asts` + `ujian`. Uji: `dev:uji-surat` 342 (+3 murni, +2 berkas ASAS/ASAT, +1 jadwal ASAT), `dev:uji-hak` 69.

**T2 (2026-10-21) — halaman satu link `/layanan` (publik, tanpa akun):** `Controllers\Layanan` + `Libraries\LayananSiswa` + views
`layanan/{index,masuk,saya,_kartu_surat}` + `assets/js/layanan.js` (Alpine CSP `layananMasuk`). Beranda: 4 kartu surat (Izin Ujian,
Izin TKA, Pernyataan Orang Tua, Penarikan — "Segera" sampai formulirnya dibangun di T3/T4 lewat `LayananSiswa::SIAP`) + "Cek status
suratku" + kartu Biodata/Pengajuan PKL/Lapor dari `LayananPublik`. Masuk: kelas (tingkat PKL + XII) → nama (GET `layanan/siswa`) →
NISN ATAU tanggal lahir (POST `layanan/masuk`, CSRF, LoginThrottle `layanan:{id}`, sesi 30 menit sejak aktivitas terakhir, id sesi
diperbarui). Halaman siswa `layanan/saya`: identitas + Keluar, status PKL, kelayakan tiap surat dengan alasan (tanpa ajuan PKL /
ajuan belum disetujui / khusus XII / belum ada jadwal dibuka / khusus kelas PKL), daftar permintaan dengan status dari suratnya
(Menunggu ACC · Sedang diperiksa sekolah · Disetujui, sedang disiapkan · Siap diambil (sudah dicetak) · Dibatalkan + alasan).
`LayananPublik` + entri `layanan_siswa` ("Minta surat sekolah") → tampil di /siswa & /cari. Di subdomain PKL/Biodata alamat
`/layanan` dialihkan ke domain utama. Uji: `dev:uji-surat` 352 (+10 Layanan), uji-hak 69, uji-lapor 114, uji-api-android 19, HTTP
alur masuk salah/benar/keluar/CSRF.

**T3 (2026-10-21) — siswa meminta Surat Izin Ujian / Izin TKA:** `GET|POST layanan/surat/{izin_ujian|izin_tka}` (view
`layanan/surat_izin`, tanpa JS: kartu radio jadwal). `LayananSiswa::ajukanIzin`: syarat = kelayakan (ajuan PKL disetujui, TKA kelas
XII, jadwal dibuka) → kelompok perusahaan siswa (`SuratAcara::perusahaan` + saringan tingkat) → sidik `SuratAcara::kunci` (sama
dengan surat per perusahaan buatan staf) → surat yang sudah ada (diminta teman / dibuat operator): permintaan DITAUTKAN, nama siswa
ditambahkan bila surat masih bisa diubah (`SuratSekolah::ubah`), riwayat aksi baru `minta`; belum ada: `SuratSekolah::buat` dengan
`sumber = 'siswa'`, dibuat "Siswa: {nama}", isian jadwal + `jadwal_id`, semua siswa perusahaan itu, menunggu ACC. Satu baris
`surat_permintaan` per siswa; minta ulang jadwal yang sama → "sudah"; surat dibatalkan → boleh minta lagi. Setelah masuk dengan
tujuan surat, siswa langsung dibawa ke formulirnya. Uji: `dev:uji-surat` 358 (+6 alur permintaan + ACC + berkas Word).

**T4 (2026-10-21) — Pernyataan Orang Tua & Penarikan PKL dari siswa:** `layanan/surat/pernyataan` (view `surat_pernyataan`) —
data orang tua terisi dari data sekolah (`SuratPernyataan::ortu`, atau isian terakhir), siswa membetulkan → `ajukanPernyataan`: surat
cakupan "siswa" (sidik sama dengan pilihan operator → bila ada, ditautkan), `isi.ortu_isian[siswa_id]` menimpa data sekolah saat
dicetak (`SuratPernyataan::halaman`), tanpa nomor & ACC (langsung "Siap dicetak sekolah"); kirim ulang sebelum dicetak = data
diperbarui (surat sama), sesudah dicetak = ditolak (arahkan ke operator). `layanan/surat/penarikan` (view `surat_penarikan`) —
alasan pilihan ramah siswa (`LayananSiswa::ALASAN_PENARIKAN` → kalimat baku `SuratPkl::ALASAN_SARAN`) atau tulis sendiri + tanggal
mulai berlaku → `SuratPkl::buat(PENARIKAN, …, [siswa sendiri], opsi sumber=siswa)` menunggu ACC; satu permintaan aktif per siswa;
status PKL tidak berubah (disebut jelas di formulir). Status permintaan membedakan surat tanpa ACC + pesan materai untuk Pernyataan.
Uji: `dev:uji-surat` 363 (+5), uji-pkl 339, uji-hak 69, uji-api-android 19; HTTP di server pratinjau DB salinan hosting (masuk →
langsung ke formulir, data orang tua terisi, Penarikan menampilkan perusahaan, Izin Ujian tanpa jadwal → kembali dengan alasan).

**Revisi user (2026-10-21): satu ikon per surat, langsung ke formulirnya** (seperti "Ajukan tempat PKL"). `LayananPublik`: entri
`surat_ujian`, `surat_tka`, `surat_ortu`, `surat_penarikan` (ilustrasi `surat-{ujian,tka,ortu,penarikan}.svg`, bagian "Surat dari
sekolah" di /siswa) → `layanan/surat/{kunci}`; kisi "Daftar layanan" (`public/partials/kepala.php`) memuat keempatnya; kartu
"Layanan unggulan" beranda TIDAK diubah. Belum masuk → `layanan/masuk?tujuan={kunci}` berjudul "Minta {nama surat}" (langkah 1
dari 2), lalu langsung ke formulir. Halaman `/layanan` tetap ada (status permintaan ada di `/layanan/saya`).
