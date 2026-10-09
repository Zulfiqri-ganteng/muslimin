# Rancangan: Surat Sekolah (Izin ASTS · Izin TKA · Pernyataan Orang Tua PKL · Surat Balasan PKL · Penarikan Izin PKL)

Dibuat 2026-10-09. **Status (2026-10-09 malam): KEPUTUSAN DITERIMA (user: "gas" = semua usulan no.1, bagian 6) · L1 FONDASI ✅ · L2 ALUR ACC ✅ · L3 IZIN ASTS & TKA ✅ — semuanya SELESAI & TERUJI LOKAL (user: push SEKALIGUS setelah L7 selesai; belum commit/push/migrate hosting) · berikutnya L4 (Pernyataan Orang Tua).** Dokumen kerja: centang tiap langkah (bagian 7) agar sesi baru bisa melanjutkan.
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
- [ ] **L4 Pernyataan Orang Tua PKL**: template, cetak per kelas/ajuan/siswa, data orang tua otomatis.
- [ ] **L5 Surat Balasan PKL**: template, periode PKL (isi `tanggal_mulai/selesai`), per ajuan & massal, tautan di detail ajuan.
- [ ] **L6 Penarikan Izin PKL**: template, pilih siswa, alasan, tanggal efektif, (opsi status "ditarik" bila diputuskan).
- [ ] **L7 Perapian**: panduan halaman, kartu ringkas di Beranda PKL, Excel Daftar Surat, regresi (`dev:uji-pkl` 322, `dev:uji-pkl-biaya` 124), `docs/PANDUAN-DEPLOY-SURAT.md`, buka tiap contoh di Word asli.
- (Opsional, bila diminta) L8 API Android `surat/…` + `docs/API-SURAT.md`; L9 kontrak Flutter `BLUEPRINT-SURAT.md`.

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

## 9. Risiko & kewaspadaan
- Membuat template dari contoh: teks bervariasi sering terpecah jadi beberapa "run" Word → pakai pola yang sama dengan `surat_pkl_binus.docx`; template di repo bersih dari data asli.
- Mengubah `PklSurat::terbitkan` menyentuh fitur yang sudah LIVE → wajib regresi `dev:uji-pkl` + `dev:uji-pkl-biaya` sebelum lapor.
- Tampilan visual Word tidak bisa dirender otomatis di mesin ini (PDF via COM macet; tak ada LibreOffice) → uji struktur otomatis + user membuka satu contoh tiap jenis di Word (seperti PKL).
- Migrasi baru perlu `phpm spark migrate` di hosting; cadangkan DB seperti biasa. Claude tidak commit/push.
