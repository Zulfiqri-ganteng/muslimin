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
