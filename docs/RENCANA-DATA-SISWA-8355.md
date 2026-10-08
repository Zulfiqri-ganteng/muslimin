# Rencana: Data Siswa Resmi (Format 8355) + NIS di Surat PKL + Detail Siswa

Dibuat 2026-10-08 dari permintaan user. **Status: ANALISIS SELESAI, BELUM ADA KODE.** Berkas sumber dari sekolah:
`formatdatasekolah/Data Siswa.xlsx` (sheet "8355", "Daftar Nama Siswa Kelas X, XI, XII TP 2026/2027 (Format 8355)").
Contoh surat lain dari sekolah di folder yang sama (ASTS, TKA, pernyataan orang tua PKL, surat balasan, penarikan izin) BUKAN bagian tugas ini.

## 1. Permintaan user (ringkas)

1. Surat PKL yang sudah di-ACC: tabel siswa ditambah kolom **NIS** (NISN tidak dicetak; tetap tersedia sebagai penanda `${siswa_nisn}` untuk template).
2. Lengkapi data siswa dari Excel sekolah (NIS, NISN, TTL, agama, orang tua, STTB, dst.), tambah kolom bila belum ada, **tanpa mengurangi fitur/kolom yang ada**.
3. Migrasi data **100% valid**.
4. Tiap siswa punya tombol **Detail** (semua data lengkap).
5. Bila sekolah punya format Excel, sediakan **output Excel dengan format yang sama** (tambahan, bukan pengganti).

## 2. Temuan: isi Excel

- 1 sheet, **1.727 siswa**, 9 bagian = tingkat × jurusan: TKJ 465/353/268 (X/XI/XII), Akuntansi 50/38/38, Manajemen Perkantoran 227/169/119.
- Kolom: No · NIS · NISN · Nama · L/P · Tempat Lahir · Tanggal Lahir · Agama · Nama Orang Tua · Alamat Orang Tua/Wali · STTB (Nomor, Tahun) · Keterangan.
  **Tidak ada rombel (X TKJ 1…8) dan tidak ada nomor HP.** Hanya tingkat + jurusan.
- Kelengkapan: NIS, NISN, nama, L/P, tempat, tgl lahir, agama, nama ortu, tahun STTB = 100%; alamat ortu 1.726; **nomor STTB hanya 425 (semua kelas XII)**; Keterangan kosong semua.
- Tidak ada NIS ganda, tidak ada NISN ganda. NISN selalu 10 digit (sebagian sel bertipe angka/teks — baca sebagai TEKS agar nol di depan aman).
- Format NIS 9 digit: `[angkatan 2 digit][angkatan+1 2 digit]10[urut 3 digit]` → 2627 = masuk 2026 (kelas X), 2526 = XI, 2425 = XII. Urutan NIS berlanjut lintas jurusan dalam satu angkatan.
- Agama: Islam 1692, Kristen 31, **Katholik 1, Budha 2, Hindu 1** (ejaan Excel ≠ pilihan baku sistem: Katolik, Buddha).
- Nama: 1.427 HURUF BESAR, 292 campur, 8 huruf kecil, 9 berspasi ganda; 9 nama kembar.
- Anomali yang harus diputuskan: (a) **NIS 5 digit** `24253` — Raditya Pratama (baris 1095; pola menduga 242510253); (b) **Andika Adha Pratama** ber-NIS angkatan XI (252610397) tetapi berada di bagian XII; (c) **tgl lahir berupa angka serial Excel** (Muhamad Chikal Baysaqi, 40641 → 2011-04-08) — aman dikonversi; (d) 1 alamat ortu kosong.

## 3. Temuan: tabel siswa di sistem

Kolom `nis` (UNIQUE, wajib) dan `nisn` (UNIQUE, boleh kosong) **SUDAH ADA**, begitu pula tempat/tgl lahir, agama, JK, alamat siswa, biodata orang tua (ayah/ibu/wali, alamat ortu terstruktur), `keterangan`, `tahun_masuk`.
**Belum ada:** nama orang tua versi sekolah (satu kolom, bisa ayah/ibu), nomor STTB, tahun STTB.

Data produksi (salinan `muslimin_asli`, 1.718 siswa + 1 baris uji): hanya **nama + JK + kelas (rombel) + tahun masuk**. **NIS = nomor urut palsu 1…1718**, NISN/TTL/agama/ortu kosong. Import Excel yang ada mencocokkan lewat NIS → mengimpor Excel baru akan membuat siswa GANDA, jadi butuh migrasi khusus yang mencocokkan lewat nama.

## 4. Temuan: kecocokan Excel ↔ sistem (uji pada salinan asli)

| Tahap | Hasil |
|---|---|
| Nama persis + tingkat + jurusan | **1.570** pasangan (1.529 unik + 41 nama kembar dipasangkan menurut urutan) |
| Nama mirip (≥ 60%, 1:1, JK sama) | **100** pasangan: 78 skor ≥ 90 · 10 skor 80–89 · 5 skor 70–79 · 7 skor 60–69 → **22 pasangan < 90% perlu mata manusia** (sebagian jelas keliru, mis. "Hasanah" ↔ "SAKINAH") |
| Sisa tanpa pasangan | Sistem **48** siswa (34 di XII TKJ) · Excel **57** siswa (29 XII TKJ, 11 X TKJ, 10 X MP, 6 XI TKJ, 1 XI AKL) |
| Cocok lintas tingkat/jurusan | 0 |
| Jenis kelamin berbeda pada pasangan persis | **15** (nama jelas laki-laki/perempuan → data sistem yang keliru; Excel dianggap benar) |

Nomor urut Excel TIDAK bisa dipakai sebagai kunci (cocok nama hanya 145). Kunci = nama (dinormalkan) + tingkat + jurusan, ditambah keputusan eksplisit untuk yang mirip/ganda.
Jumlah per tingkat+jurusan sistem vs Excel berbeda di 7 dari 9 kelompok (selisih −9…+3).

## 5. Format Excel resmi (untuk ekspor "Format 8355")

Landscape, skala 78%, baris 11–12 diulang tiap halaman, Calibri 11, border tipis. Blok per jurusan: judul (A1 tebal 12) → "(Format 8355)" → Nama Sekolah / Alamat / No. Telpon / Kode Pos / Status Akreditasi / Konsentrasi Keahlian → tajuk 2 baris (STTB: Nomor, Tahun) → baris bagian "Kelas X <JURUSAN>" → siswa → tanda tangan ("Mengetahui, Pengawas Pembina — Mansur, M.Pd. NIP. 19821120 200902 1 001" kiri; "Bekasi, <tanggal>, Kepala SMK Bina Nusa — Napis Kuturupi, S.T. NIP. -" kanan).
Tabel ringkasan di samping (Q:U): KELAS · ROMBEL · L · P · TOTAL per tingkat+jurusan + total per tingkat (X 742, XI 560, XII 425).

## 6. Keputusan yang diperlukan dari user (usulan bawaan)

1. Kolom surat PKL: **NIS saja** (NISN tidak dicetak) — pesan user bertentangan ("NIS dan NISN" lalu "NIS saja"); dipilih NIS saja, NISN tersedia sebagai penanda template. Letak: NO · **NIS** · NAMA · KELAS · KONSENTRASI · HP.
2. "Nama Orang Tua" Excel → kolom BARU `nama_orang_tua` (tidak ditebak ayah/ibu). `nama_ayah`/`nama_ibu` biodata tidak disentuh.
3. Penulisan nama: ejaan resmi Excel menang, ditulis **Title Case** seperti sistem sekarang (HURUF BESAR → "Ade Firmansyah"); yang sudah campur dipertahankan.
4. Agama dinormalkan ke pilihan baku (Katholik → Katolik, Budha → Buddha).
5. **57 siswa baru di Excel** tanpa rombel: usulan → ditambahkan dengan kelas kosong + keterangan "perlu penempatan kelas", bisa disaring di Master Siswa. (Alternatif: tidak ditambahkan, hanya dilaporkan.)
6. **48 siswa sistem tanpa pasangan di Excel**: usulan → TIDAK diubah/dihapus, hanya masuk laporan "perlu diverifikasi" (kemungkinan lulus/pindah; 34 di XII TKJ).
7. 22 pasangan mirip < 90% dan 15 konflik JK → daftar tinjauan CSV; yang tidak dikonfirmasi tidak diubah.
8. Anomali 2.(a)(b) → ikuti keputusan user; bawaan: NIS apa adanya (24253) dan tingkat mengikuti kelas di sistem, ditandai di laporan.
9. Migrasi data harus dijalankan **kering dulu di produksi** (laporan, tanpa menulis) karena salinan `muslimin_asli` bukan produksi hari ini.

## 6b. KEPUTUSAN USER (2026-10-08) & status

- Surat PKL: **NIS saja**. Poin 2–8 §6: user menyerahkan ke teknis → dipakai usulan bawaan (kolom baru nama_orang_tua; Title Case; agama dibakukan; 57 siswa baru kelas kosong + keterangan; 48 siswa tak ada di Excel tidak diubah; 22 mirip<90% & 15 konflik JK → daftar tinjauan, Excel menang untuk JK; NIS 24253 apa adanya; tingkat Andika mengikuti kelas sistem). Kesalahan data nanti diperbaiki manual oleh user atau lewat siswa (form Biodata).
- Permintaan tambahan: fitur untuk surat-surat lain di folder `formatdatasekolah` (ASTS, TKA, Pernyataan Orang Tua PKL, Surat Balasan PKL, Penarikan Izin PKL) → **rancangan di sidebar bertanda "Segera"** (grup SURAT SEKOLAH, menu redup tak bisa diklik; Admin/Operator melihat 5, Waka Hubin 3 surat PKL). Saat fiturnya dibuat, ubah item di `app/Views/layouts/admin.php` (grup bertanda `'segera' => true`) menjadi menu biasa.
- **PRIVASI:** folder `formatdatasekolah/` berisi data asli 1.727 siswa dan repo PUBLIK → folder itu sudah masuk `.gitignore`. Karena itu F2 dirancang membaca Excel dari SERVER (diunggah manual ke `writable/`), BUKAN menyimpan data siswa di dalam repo/migrasi.
- **F1 SELESAI (lokal)**: migrasi `2026-10-10-000001_SiswaDataSekolah` (3 kolom, aditif); Master Siswa web (form, impor/ekspor Excel — kolom baru di PALING KANAN, urutan lama utuh), API (kunci baru, hanya ditulis bila dikirim → aplikasi lama aman), saringan **Tanpa kelas** (web & API), menu "Segera" di sidebar. Uji: `uji_siswa_f1.mjs` 24/24; regresi `dev:uji-pkl` 319 · HTTP 73 · API 111 hijau. Bug yang tertangkap lewat foto layar: `siswa.js` memetakan kolom edit secara manual (kolom baru harus ditambahkan di sana, kalau tidak form kosong & data terhapus saat disimpan).

- **F2 SELESAI (lokal; belum dijalankan di server)** — perintah `siswa:data-8355` (`app/Commands/SiswaData8355.php` + mesin `app/Libraries/SiswaResmi8355.php`); langkah di server: **`docs/PANDUAN-DATA-SISWA-8355.md`**. Tanpa `--tulis` = laporan CSV saja; `--tulis` = satu transaksi + cadangan nilai lama + Audit Log; `--gabung NIS:ID` memaksa pasangan yang ditahan; aman diulang (siswa yang sudah terisi dikenali lewat NIS). Dipakai PERINTAH, bukan migrasi, karena butuh berkas Excel yang sengaja tidak ada di repo (migrasi akan "habis dipakai" bila berkas belum ada). Hasil pada salinan produksi: 1.660 dipasangkan (1.529 persis + 41 kembar + 83 salah ketik + 6 singkatan/lebih panjang + 1 nama depan), 67 siswa baru tanpa kelas, 11 ditahan, 58 siswa sistem tak ada di Excel, 15 JK diperbaiki, 0 bentrok; kelas lama tidak berubah; 2 detik & muat di 64 MB. Uji: `uji_f2.mjs` 32/32. Aturan nama: Excel menang kecuali versi lebih pendek (nama sistem lebih lengkap dipertahankan). `tahun_masuk` dikoreksi dari NIS (data lama semuanya 2026). Siswa dengan Biodata disahkan: tempat/tgl lahir, agama, alamat ortu tidak ditimpa.

- **F2 DIJALANKAN DI SERVER 2026-10-08** oleh user: 1.660 dipasangkan (13 lewat NIS yang sudah asli dari Biodata, 1.516 nama persis, 41 kembar, 83 salah ketik, 6 singkatan, 1 nama depan), 67 siswa baru tanpa kelas, 59 siswa sistem tak ada di Excel, 84 nilai Biodata dipertahankan. 2 bentrok NIS: id 835 (Rr. Yuswita…) memegang NIS 252610042 milik Ahmad Padillah (id 770) — selesai di putaran `--tulis` kedua (laporan akhir: 1.727 terhubung lewat NIS, 0 perubahan, 0 bentrok). Total siswa 1.786. Catatan: CSV laporan ditulis ulang tiap kali perintah dijalankan, jadi `tinjau_mirip.csv` hasil run pertama hilang (setelah `--tulis` isinya kosong); dari 11 pasangan ditahan hanya 2 yang kemungkinan orang yang sama: NIS 252610299 ↔ id 1021 ("M Rafka Farizi" XI TKJ 8) dan NIS 242510030 ↔ id 1326 ("Riska" XII TKJ 1) — penggabungan manual (hapus duplikat tanpa kelas, lalu lengkapi data siswa lama) atau `--gabung` setelah menghapus duplikatnya.
- **F3 SELESAI (lokal; belum di-push)**: kolom **NIS** di tabel siswa surat PKL, setelah NO (NO · NIS · NAMA · KELAS · KONSENTRASI KEAHLIAN · HP; lebar 576/1360/2444/1406/1900/1782 = 9468 twips); NISN tidak dicetak (penanda `${siswa_nisn}` tetap tersedia untuk template unggahan). Template bawaan ditambal lewat skrip DOM; surat cadangan tanpa template mencetak NIS (dulu NISN). NIS masuk ke **sidik surat** (`PklSurat::sidik` + kueri `statusBanyak`) → bila NIS berubah, surat yang pernah diunduh ditandai "Perlu cetak ulang". Diperiksa di Word 16 asli (2 surat = 2 halaman, 9 digit satu baris). Uji: `dev:uji-pkl` 321 (+2 baru: kolom NIS, sidik NIS) · HTTP 73 · API 111.

- **F3 SUDAH DI-PUSH & DI-PULL di server (2026-10-08)** — tanpa migrasi, tidak ada langkah lain.
- **F4 SELESAI (lokal; belum di-push)**: tombol **Detail** (ikon mata) di tiap baris Master Siswa dan nama siswa jadi tautan → halaman `admin/master/siswa/{id}` (kepala: nama, NIS, NISN, lencana kelas/status/Biodata ✓, tombol "Edit data"; kartu: Identitas, Kelas & Status, Alamat & Kontak Siswa, Riwayat Masuk & STTB, Orang Tua, Wali, Isian Biodata [tanggal disahkan + kolom wajib yang masih kosong], PKL/Prakerin [ajuan aktif + tautan ke ajuan]; nilai kosong = "—"; tanggal format Indonesia). Satu sumber isi untuk web dan API: `app/Libraries/SiswaDetail.php`. API: `GET /api/v1/admin/master/siswa/{id}` → kunci lama siswa + `jurusan_nama`, `bagian[]` (judul → baris `{kunci,label,nilai}`, nilai sudah terformat, kosong = null), `biodata`, `pkl`, `jejak`; id tak ada → 404; rute `/statistik` tidak bentrok. "Edit data" membuka daftar dengan `?q=<NIS>&edit=<id>` dan modal edit terbuka otomatis (hook `onInit` di `siswa.js`). Hak akses: Admin & Operator saja (Waka Hubin ditolak; API Operator 403 seperti API master lain). Tombol Cetak sengaja tidak dibuat (F5 mengurus cetak/Excel). Uji: `uji_siswa_f4.mjs` 24/24 · `uji_siswa_f1` 24 · `dev:uji-pkl` 321 · HTTP 73 · API 111; foto layar desktop 1280 & HP 390 (tanpa geser samping, tanpa galat JS). Catatan uji: cache daftar harus dikosongkan bila data uji dimasukkan lewat SQL langsung.

- **F4 SUDAH di-push** (user, 2026-10-08).
- **Perintah `siswa:hapus-keluar` (permintaan user 2026-10-08, lokal)**: menghapus LUNAK (`deleted_at`) siswa di sistem yang tidak ada di Excel resmi (target: sistem 1.786 → 1.727). Tanpa `--tulis` = laporan (`laporan-hapus-keluar/akan_dihapus.csv` + `ditahan.csv`); `--tulis` = satu transaksi + cadangan daftar + Audit Log; `--kecuali ID,ID` (jangan hapus), `--paksa ID,ID` (ditahan karena "nama mirip" tapi pasti orang lain); otomatis DITAHAN: nama mirip siswa resmi tanpa kelas (kemungkinan orang sama, mis. id 1021 & 1326) atau masih punya ajuan PKL aktif; berhenti bila >150 siswa. Bukan migrasi karena daftar bergantung Excel (tidak boleh di repo). Panduan: PANDUAN-DATA-SISWA-8355.md bagian G. Uji pada salinan DB (F2 diterapkan lebih dulu): 58 kandidat → 54 dihapus + 4 ditahan; `--paksa`/`--kecuali` benar; diulang = 0 baru.
- **Migrasi `PklKosongkanData` DIHAPUS dari repo** (2026-10-08, atas permintaan user sebelumnya "hapus aja tuh migrasi"): berbahaya bila terjalan di server setelah PKL dipakai sungguhan.
- **F5 SELESAI (lokal; belum di-push)**: tombol **Format 8355** di Master Siswa (hijau, di samping Export; Export lama utuh) → `GET admin/master/siswa/export-resmi` → `Daftar-Nama-Siswa-Format-8355-<tanggal>.xlsx`. Bentuk dibedah dari berkas sekolah asli dan dibandingkan otomatis: satu lembar "8355", 3 blok (TKJ, AKL, Manajemen Perkantoran; TJKT dianggap TKJ) masing-masing judul + info sekolah + header 2 baris (A–M, identik) + bagian "Kelas X/XI/XII …" + siswa urut nama + tanda tangan "Mengetahui, Pengawas Pembina" / "Kepala <sekolah>"; nomor urut berlanjut; ringkasan L/P/Total/Rombel di kolom Q–U (rumus COUNTIF hidup, rombel dari sistem); cetak landscape Folio skala 78 %, baris 11–12 diulang, area cetak hanya A–M, tiap jurusan mulai halaman baru. Hanya siswa AKTIF (lulus/pindah/keluar tidak ikut); siswa tanpa kelas ditempatkan menurut catatan resmi F2 ("data resmi sekolah: X TKJ"), kalau tak ada → bagian "Belum ada kelas / jurusan"; catatan otomatis "Perlu penempatan kelas…" tidak dicetak di kolom Keterangan. Migrasi `2026-10-11-000001_SettingsDataSekolah`: kolom `school_postal_code`, `school_accreditation` (diisi awal 17610 & B hanya saat kolom baru dibuat), `supervisor_name`, `supervisor_nip` (TIDAK diisi — repo publik; isi lewat Pengaturan Sekolah, form ditambah di bagian Kepala Sekolah). Kode: `app/Libraries/SiswaExcel8355.php`, `Siswa::exportResmi`, rute `siswa/export-resmi`, toolbar `extraActions`. Uji: `uji_excel8355.php` 20/20 (1.730 siswa, 1,7 dtk, 52 MB), `uji_siswa_f5.mjs` 18/18 (Admin & Operator boleh, Waka Hubin ditolak), dilihat di Excel asli (COM), regresi F1 24 · F4 24 · API 111. Catatan: kertas berkas asli tercatat "10000" (ukuran khusus) → dipakai Folio (13") karena lebar 13 kolom × skala 78 % tidak muat di A4.

- **F5 SUDAH di-push, di-migrate (hanya `SettingsDataSekolah`), dan `siswa:hapus-keluar --tulis --paksa 1380` SUDAH dijalankan di server (2026-10-08)**: 56 siswa dihapus lunak, sistem 1.786 → 1.730; laporan ulang = 0 akan dihapus, 3 ditahan (id 1021 M Rafka Farizi ↔ id 1736; id 1326 Riska ↔ id 1739; id 1159 Jihan Rizkia Alfazhira ↔ id 1785). User lalu memilih **cara 1**: menghapus ketiga catatan lama → total tepat 1.727 (ketiga siswa versi Excel tinggal tanpa kelas, ditempatkan ulang lewat filter "Tanpa kelas"; kelas lama: XI TKJ 8, XII TKJ 1, XI MPLB 3).
- **Fitur "Kembalikan semua yang sudah mengisi untuk diperbaiki" (Isian Biodata, web; permintaan user 2026-10-08, lokal)**: bar kuning di tab Menunggu & Disetujui (`admin/biodata`) → `POST admin/biodata/kembalikan-massal` (`Admin\Biodata::kembalikanMassal`, `BiodataVerifikasi::kembalikanBanyak`, `BiodataIsianModel::idBisaDikembalikan`): satu UPDATE untuk semua isian berstatus menunggu+disetujui milik siswa aktif (sesuai saringan kelas/pencarian), satu catatan sama, pindah ke tab Perbaikan; Master Siswa tidak berubah; Audit Log. Tambahan: saat siswa membuka isian yang dikembalikan (`Biodata::buka`), NIS/NISN yang kini tercatat resmi di Master Siswa (≥5 digit) mengisi kolom yang kosong di isian lama (setelah verifikasi NISN/tgl lahir). Uji: `uji_biodata_massal.mjs` 21/21.
- **F6 SELESAI (2026-10-08)**: kontrak Flutter `C:\flutter-muslimin\BLUEPRINT-SISWA.md` (tahap S0–S4) + fixture nyata `test/fixtures/siswa/` (11 berkas) + `CLAUDE.md`/`README.md` Flutter diperbarui. Temuan yang dicatat di kontrak: `sttb_tahun` di luar 1990–2100 disimpan `null` tanpa galat (sama dengan web); API Settings belum memuat 4 isian baru; API belum punya "kembalikan massal".

- **Surat PKL: urutan kolom diubah (2026-10-08, permintaan user, lokal)**: tabel siswa kini **NO · NAMA · NIS · KELAS · KONSENTRASI KEAHLIAN · NOMOR HANDPHONE** (sebelumnya NIS di depan NAMA). Template bawaan `app/Libraries/Surat/surat_pkl_binus.docx` ditambal (sel NIS↔NAMA ditukar beserta lebar kolom; 11 run tanpa font eksplisit diberi Times New Roman; font dasar dokumen `docDefaults` dari font tema → Times New Roman, supaya tidak ada teks yang jatuh ke Calibri). Surat cadangan tanpa template (`PklDocx`) dan daftar penanda sudah berurutan Nama→NIS sejak awal. Template Word UNGGAHAN sendiri (Pengaturan PKL) tidak terpengaruh — urutan di sana mengikuti template itu. Uji: `dev:uji-pkl` 322 (cek urutan kolom diperbarui + cek baru "tanpa Calibri/Tahoma/font tema"), dilihat di Word asli (1 halaman).

## 7. Fase kerja (6 fase; berhenti & lapor tiap fase)

| Fase | Isi | Catatan |
|---|---|---|
| **F1 Kolom & Master Siswa** | migrasi skema (`nama_orang_tua`, `sttb_nomor`, `sttb_tahun`); model, form web, impor/ekspor Excel (kolom ditambah di BELAKANG, urutan lama utuh), API; tes | aditif, tidak mengurangi apa pun |
| **F2 Migrasi data resmi** | berkas data 1.727 siswa; aturan pencocokan; perintah kering `siswa:cek-8355` (laporan CSV: cocok/mirip/konflik/baru/tak ada di Excel); migrasi isi data (satu transaksi, idempoten, mengisi NIS asli, NISN, TTL, agama, ortu, STTB, JK, tahun masuk); uji pada salinan `muslimin_asli` | **fase paling berisiko; butuh keputusan §6** |
| **F3 NIS di surat PKL** | kolom NIS di template + lebar kolom, penanda `${siswa_nisn}` tersedia; uji render Word asli | **jangan dipakai sebelum F2 dijalankan** (NIS sekarang nomor urut palsu) |
| **F4 Detail siswa** | tombol Detail di tiap baris Master Siswa (semua data dikelompokkan: identitas, kelas & status, alamat, orang tua, wali, STTB, biodata, status PKL); API `GET admin/master/siswa/{id}` | |
| **F5 Excel Format 8355** | tombol "Unduh Format 8355" (tambahan); Pengaturan Sekolah ditambah kode pos, akreditasi, pengawas pembina + NIP, NIP kepala sekolah; ringkasan L/P/Total + rombel dari sistem | ekspor lama tetap |
| **F6 Kontrak Flutter** | `BLUEPRINT-SISWA.md` (+ fixture): form siswa bidang baru, layar Detail, filter "tanpa kelas" | aplikasi Android dikerjakan AI di `flutter-muslimin` |

Urutan usulan: F1 → F2 → F3 → F4 → F5 → F6. Skrip bantu analisis (di folder sementara sesi): `parse_siswa.php`, `stat_siswa.php`, `cocok_siswa.php`, `cocok2.php`, `cocok3.php`, `baca_xlsx*.php` — dibuat ulang bila perlu.
