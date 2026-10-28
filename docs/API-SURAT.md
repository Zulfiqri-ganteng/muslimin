# API Surat Sekolah (Android) — `/api/v1/surat…`

Dibuat 2026-10-12 (Surat Sekolah **L8**). Cermin menu web *SURAT SEKOLAH* (Daftar Surat + 5 jenis) untuk aplikasi
Android staf. Kontrak sisi Flutter (layar, tahap kerja, contoh respons nyata): `C:\flutter-muslimin\BLUEPRINT-SURAT.md`
(**L9**) + fixture `C:\flutter-muslimin\test\fixtures\surat\*.json`. Rancangan modul: `docs/DESAIN-SURAT-SEKOLAH.md`.

> **PEMBARUAN 2026-10-21 (permintaan klien): Surat Balasan — staf mengatur siswa yang DITERIMA perusahaan** (dulu daftar siswa
> terkunci = semua anggota ajuan; perbaikan hanya lewat lapor ulang siswa). Siswa yang tidak dicentang **dikeluarkan dari ajuan
> sebagai "ditolak perusahaan"** saat disimpan (sama dengan memproses laporan "sebagian diterima": bebas mengajukan tempat lain,
> tercatat di riwayatnya, Surat Izin yang terbit tetap sah). Hak = hak Buat Surat Balasan (tanpa hak ACC ajuan). Minimal satu siswa
> diterima (semua ditolak = tombol "Ditolak perusahaan" di ajuan). Semua bersifat menambah — aplikasi lama tetap jalan:
> - `POST surat/balasan` + **`diterima`** `{"<ajuan_id>": [siswa_id, …]}` (ajuan yang tidak disebut = semua diterima) + **`alasan_tolak`**
>   (wajib ≥ 5 huruf bila ada yang tidak diterima; dibaca siswa). Jawaban 201 + **`data.dilepas`** = nama siswa yang dikeluarkan.
>   Galat per ajuan masuk `data.gagal[]` seperti biasa (mis. "Centang minimal satu siswa yang diterima perusahaan…").
> - `POST surat/{id}/ubah` (Balasan) + **`siswa`** `[siswa_id, …]` = siswa yang diterima (kunci **tidak dikirim = daftar tetap**) +
>   `alasan_tolak` → 200 + `data.dilepas`; isian siswa salah → **422** `data.errors.siswa` (pesan siap tampil).
> - Berkas Word Balasan & Penarikan: kaki "Surat ini disetujui secara elektronik…" kini di **paling bawah** (sesudah "NB : … 1. kontak"),
>   2 baris kecil bergaris tipis; tetap 1 halaman F4 sampai 5 siswa (diukur di Microsoft Word).
> - Tanpa migrasi (cukup `git pull`).

## Prinsip
- Controller tipis `App\Controllers\Api\Surat`; SEMUA aturan dari library yang sama dengan web: `SuratSekolah` (daftar, muat,
  hitung, pilih unduhan, `alasanTakBisaUbah`), `SuratKeputusan` (ACC/kembalikan/batal ACC/ajukan ulang/batal, ACC massal),
  `SuratBerkas` (Word/ZIP, nomor terbit sekali), `SuratAcara` (ASTS/TKA: periksa, buat, **`ubahSurat`**, **`nilaiAwal`**),
  `SuratPernyataan`, `SuratPkl` (Balasan/Penarikan), `SuratPeriksa` (peringatan). Hasil web = hasil aplikasi.
- Pencatatan: riwayat surat + Audit Log memakai `saluran = 'aplikasi'`.
- Tanpa migrasi database.

## Gerbang & hak (diperbarui 2026-10-13 — hak akses diatur Admin, `docs/DESAIN-HAK-AKSES.md`)
- `ApiAuthFilter`: peran boleh memakai `surat/*` bila memegang **modul Surat Sekolah** (Sistem → Hak Akses → Menu) dan saklar
  "boleh aplikasi" perannya menyala. Admin `*`. API selain PKL & Surat tetap khusus Admin.
- Daftar, detail, meta: terbuka bagi pemegang modul.
- Hak **per jenis surat** (Sistem → Hak Akses → PKL & Surat): `surat_buat:{jenis}` (buat, ubah, ajukan ulang, batalkan, formulir),
  `surat_acc:{jenis}` (ACC, kembalikan, batal ACC, ACC massal — hanya jenis bernomor), `surat_unduh:{jenis}`. Bawaan: Operator =
  Buat + Unduh semua jenis; Waka Hubin = ACC semua jenis bernomor. Admin selalu boleh.
- **Tidak ada lagi "wakil/mewakili"**: kiriman `wakil` diabaikan, `hak.wajib_wakil` selalu `false`, kaki surat mencatat
  "(Admin Sistem)" / label peran penyetuju. Tanda tangan Waka Hubin & Kepala Sekolah SELALU tercetak, siapa pun yang meng-ACC.
- Bila beberapa peran memegang ACC jenis yang sama, yang menekan lebih dulu menang; yang kedua mendapat **409** (status sudah berubah).
- `meta.hak.jenis` / `hak` per surat membawa hak persis peran ini — aplikasi tidak perlu menebak dari nama peran.

## Endpoint

| Metode | Alamat | Isi / catatan |
|---|---|---|
| GET | `surat/meta` | `jenis[]` (kode, slug, label, singkat, bernomor, perlu_acc, siap, template_lengkap), `status` (kode→label), `aksi_riwayat`, `label_isi`, `aturan`, `tahun`, `hitung`, `hak` {peran, peran_label, kelola, acc, wajib_wakil} |
| GET | `surat?status=&jenis=&tahun=&q=&page=&per=` | baris daftar (kode, jenis, status, judul, nomor, perusahaan, jml_siswa, diunduh_kali, perlu_cetak_ulang, acc{nama,peran,mewakili,waktu,kode}, …) + `meta.pagination` + `meta.hitung`. Nilai saringan asing → 422 |
| GET | `surat/{id}` | baris + `pengajuan_id`, `isi` (tanpa kunci mesin), `ringkas_isi[]` {label,nilai}, `siswa[]`, `peringatan[]` {tingkat,teks}, `ada_bahaya`, `template_ada`, `riwayat[]`, `hak` {acc, kembalikan, batal_acc, ajukan_ulang, batal, ubah, unduh, wajib_wakil} |
| POST | `surat/{id}/acc` | `{paham?, wakil?, catatan?}` |
| POST | `surat/{id}/kembalikan` · `batal-acc` · `batal` | `{catatan}` wajib |
| POST | `surat/{id}/ajukan-ulang` | — |
| POST | `surat/acc-massal` | `{mode: terpilih\|aman, ids?, wakil?}` → `{disetujui, dilewati[]}` (surat berperingatan dilewati) |
| POST | `surat/{id}/unduh` | berkas `.docx`; header `X-Jumlah-Surat`, `X-Surat-Ids`, `X-Zip` |
| POST | `surat/unduh-massal` | `{jenis, mode: terpilih\|belum\|semua, ids?}` → `.docx`/`.zip`, atau JSON `{jumlah:0}` bila tak ada yang perlu diunduh |
| GET | `surat/form/{slug}` | ASTS/TKA: `nilai_awal`, `periode_ujian`, `sesi_saran`, `tempat_bawaan`, `perusahaan[]` (kunci, nama, alamat, kota, mulai, selesai, luar_periode, jml_siswa, siswa) — `?tgl_mulai=&tgl_selesai=` menandai luar periode. Pernyataan: `nilai_awal`, `cakupan`, `kelas[]`, `ajuan[]`. Balasan/Penarikan: `nilai_awal`, `ajuan[]` (+ balasan aktif, jumlah penarikan), `sapaan_saran`, `alasan_saran`, `batas_pkl` |
| GET | `surat/siswa?kelas_id[]=` | siswa aktif kelas yang boleh PKL (pemilih Pernyataan "siswa pilihan") |
| POST | `surat/{slug}` | buat → 201 `{dibuat[{id,kode}], sama[], gagal[], perlu_acc}`; tak ada yang dibuat → 409 dengan data sama |
| POST | `surat/{id}/ubah` | ASTS/TKA/Balasan/Penarikan yang belum bernomor & menunggu/dikembalikan; Pernyataan → 409 |

### Lapor Diterima PKL → Surat Balasan (2026-10-13)
Siswa melapor "sudah diterima perusahaan" + periode PKL lewat form publik `…/pkl/lapor`; staf memprosesnya jadi Surat Balasan.
Terbuka bagi pemegang hak **Buat ATAU ACC Surat Balasan** (`PklLapor::hak`); aturan & penyimpanan sama dengan web (`Libraries\PklLapor`).

| Metode | Alamat | Isi / catatan |
|---|---|---|
| GET | `surat/balasan/laporan?status=baru\|dikembalikan\|diproses` | baris: `id, pengajuan_id, kode_ajuan, status, status_teks, perusahaan, alamat_ajuan, alamat_baru (null = tidak berubah), tanggal_mulai, tanggal_selesai, periode, lama, catatan, pelapor, hp_pelapor, kirim_ke, dilaporkan_at, anggota[] {siswa_id,nama,kelas}, ajuan_sah, balasan {id,kode,status,nomor}\|null, peringatan[], alasan, surat_id, diproses_oleh, diproses_peran, diproses_at`. `meta.hitung` {baru, diproses, dikembalikan}, `meta.hak` {lihat, setujui, buat, kembalikan, perlu_acc, pemegang_acc}, `meta.form` {buka, tautan}. Status asing → 422; tanpa hak → 403 |
| POST | `surat/balasan/laporan/{id}/setujui` | "Setujui & buat": jenis wajib ACC → butuh hak ACC Balasan, surat langsung **disetujui** atas nama pemroses; jenis tanpa ACC → hak Buat, surat langsung siap unduh. → `{id, status:'diproses', surat_id, kode_surat, status_surat}` |
| POST | `surat/balasan/laporan/{id}/buat` | hak Buat (hanya bila jenis wajib ACC): surat dibuat lalu **menunggu ACC** |
| POST | `surat/balasan/laporan/{id}/kembalikan` | `{alasan}` 5–255 huruf, dibaca siswa saat membuka form lagi |

Laporan DIKLAIM atomik: dua staf menekan bersamaan → yang kedua **409**. Ajuan sudah punya Balasan → laporan hanya ditandai selesai
(tertaut ke surat lama, `200`). Ajuan tak lagi disetujui / isian tak sah → **409/422** dan laporan kembali "baru". Surat Balasan
yang dibuat lewat jalur LAIN (`POST surat/balasan`, web) ikut menandai laporan menunggu ajuan itu selesai.

**Pembaruan 2026-10-20 — jawaban perusahaan (semua / sebagian / semua ditolak).** Baris laporan kini memuat `hasil`
(`semua` | `sebagian` | `ditolak`), `hasil_teks`, `siswa_diterima[]` dan `siswa_ditolak[]` (`{siswa_id, nama, kelas}`, dihitung terhadap
anggota aktif ajuan sekarang). Laporan `ditolak` tidak punya tanggal (`tanggal_mulai`/`tanggal_selesai`/`periode`/`lama` kosong).
- `sebagian`: `setujui`/`buat` lebih dulu **mengeluarkan** `siswa_ditolak` dari ajuan (jenis `ditolak_perusahaan`, sumber `lapor`; mereka
  bebas mengajukan baru dan tetap tercatat), lalu Surat Balasan dibuat **hanya untuk `siswa_diterima`**. Bila ajuan sudah punya Balasan
  yang memuat semua siswa → `409` (batalkan surat itu dulu).
- `ditolak`: `setujui` (atau `buat`) = **konfirmasi**: ajuan menjadi "Ditolak perusahaan" (`tolak_sumber = perusahaan`), tanpa surat;
  cukup hak memproses kotak (Buat ATAU ACC Balasan). Respons: `surat_id` & `kode_surat` = `null`.
- Laporan `sebagian`/`ditolak` yang masih menunggu MENOLAK pembuatan Balasan lewat jalur lain (`POST surat/balasan` → `gagal[]` berisi
  pesan "Proses lewat kotak Lapor Diterima PKL").
- `kembalikan`: `alasan` kini 5–1000 huruf. Template alasan jenis `lapor`: `GET pkl/alasan?dialog=lapor` (lihat `docs/API-PKL.md`).

### Kabar WhatsApp "surat siap diambil" (2026-10-13)
Saat ini untuk **Surat Balasan PKL** (`Libraries\SuratWa`). Tersedia setelah surat DIUNDUH; hak = **Unduh** jenis itu. Sistem hanya
menyiapkan tautan `wa.me` + pesan — staf mengirim dari WhatsApp-nya sendiri, lalu aplikasi memanggil `tandai`.

| Metode | Alamat | Isi / catatan |
|---|---|---|
| GET | `surat/{id}/wa` | baris: `surat_id, kode, jenis, nomor_surat, perusahaan, siswa_id, nama, kelas, hp, wa (62…\|null), url (https://wa.me/…\|null), alasan_tidak, pesan, dikabari_at, dikabari_oleh`. Belum diunduh → `[]` |
| GET | `surat/wa?jenis=balasan_pkl` | kabar massal: siswa yang BELUM dikabari pada semua surat jenis itu yang sudah diunduh (maks 300) |
| POST | `surat/{id}/wa/{siswa_id}/tandai` | → `{dikabari_at, dikabari_oleh}`; surat belum diunduh / siswa bukan di surat → 409 |

`hak.kabari` pada detail surat = boleh menampilkan tombol. Jenis tanpa kabar → 422; tanpa hak Unduh → 403. Templat pesan:
Pengaturan PKL (`pkl_pengaturan.wa_pesan_balasan`, penanda sama dengan Surat Izin PKL).

Slug: `asts`, `tka`, `pernyataan-ortu`, `balasan`, `penarikan` (= ujung alamat menu web). Isian sama dengan formulir web
(rinci per slug: `BLUEPRINT-SURAT.md` §5). Boolean JSON (`true/false`) disamakan jadi `'1'/'0'` sebelum dibaca library
(mis. `isi_ortu: false` tetap berarti "tidak").

### Surat Izin ASTS/ASAS/ASAT (2026-10-21)
Jenis `izin_asts` kini melayani tiga ujian. Isian baru `kegiatan` = `ASTS` | `ASAS` | `ASAT` pada buat/ubah (tanpa isian = `ASTS`,
jadi aplikasi lama tetap jalan; nilai lain → 422). `ASAT` selalu semester Genap (isian `semester` diabaikan). Formulir
(`GET surat/form/asts`) menambah `ujian: [{kode, nama}]`; tiap butir `periode_ujian` kini juga memuat `kegiatan` dan daftar
periode mencakup ASTS 1, ASAS, ASTS 2, ASAT dari menu Ujian. Judul surat: "ASAS Ganjil 2026/2027 - …", "ASAT 2026/2027 - …". Label jenis
= "Surat Izin ASTS/ASAS/ASAT". Surat Izin TKA per perusahaan kini hanya memuat siswa yang SEKARANG kelas XII.

### Pembaruan 2026-10-22 — Layanan Siswa (siswa meminta surat sendiri, Operator ACC)
Siswa kini meminta Surat Izin Ujian / Izin TKA / Pernyataan Orang Tua / Penarikan PKL sendiri lewat web publik (`/layanan/surat/{…}`,
masuk NISN atau tanggal lahir). Surat dari siswa masuk Daftar Surat seperti biasa (pembuat "Siswa: {nama}"). Migrasi
`2026-10-26-000001` memindah hak ACC Izin Ujian / Izin TKA / Penarikan dari Waka Hubin ke **Operator** (`surat_acc:*` di peran).
- `GET surat?asal=siswa|staf` — saringan baru (nilai lain → 422 `errors.asal`). Tiap baris: `jml_minta` (jumlah siswa yang meminta)
  dan `diajukan_siswa` (bool → lencana "Diajukan siswa"). `meta.permintaan_siswa` = `{izin_asts|izin_tka|pernyataan_ortu|penarikan_pkl:
  {menunggu, siap, perlu_acc}}` — kotak "Permintaan dari siswa" (hanya jenis yang boleh ditangani akun ini; `{}` bila tidak ada).
  Ketuk kotak → `surat?jenis={j}&asal=siswa&status=menunggu` (atau `disetujui` untuk "siap dicetak").
- `GET surat/{id}` — `peminta: [{nama, kelas, waktu}]` (urutan permintaan) dan `ortu_isian: [{siswa_id, nama, pekerjaan, alamat, hp}]`
  (Pernyataan: data orang tua yang diisi siswa, dicetak menimpa data sekolah). Kunci `jadwal_id` & `ortu_isian` tidak lagi ada di `isi`.
- **Jadwal Ujian & TKA** (hak Buat Izin Ujian / Izin TKA; jenis lain → 403):
  - `GET surat/jadwal` → `{jenis: [...], jadwal: [{id, jenis, label, kegiatan, semester, tahun_pelajaran, sesi, tgl_mulai, tgl_selesai,
    hari_tanggal, tempat, buka, keadaan (buka|tutup|selesai), keadaan_label, jml_permintaan, bisa_diubah, dibuat_oleh}], ujian:
    [{kode, nama}], periode_ujian: [...], sesi_saran, tempat_bawaan, tahun_pelajaran}`.
  - `POST surat/jadwal` `{jenis: izin_asts|izin_tka, kegiatan?: ASTS|ASAS|ASAT, periode?: indeks periode_ujian (mengisi ujian,
    semester, tahun, tanggal), semester?, tahun_pelajaran?, sesi? (TKA, wajib), tgl_mulai, tgl_selesai, tempat?, buka: bool}` → 201
    jadwal; galat isian → 422 `errors.{kolom}`; kembar → 409.
  - `POST surat/jadwal/{id}` (ubah; jadwal yang sudah dipakai permintaan siswa → 409), `POST surat/jadwal/{id}/buka|tutup` (kegiatan
    yang sudah lewat tidak bisa dibuka → 409), `DELETE surat/jadwal/{id}` (sudah dipakai → 409, cukup ditutup).
- **WhatsApp "siap diambil"** (`surat/{id}/wa`, `surat/wa?jenis=`, `…/tandai`) kini berlaku untuk SEMUA jenis surat. Pesan: Surat
  Balasan = templat Pengaturan PKL; jenis lain = kalimat bawaan dengan nama surat ("Surat Izin ASAS", "Surat Izin TKA", …;
  Pernyataan + pengingat materai).

### Pembaruan 2026-10-26 — Surat Izin TKA: gelombang PER SISWA (hanya menambah; aplikasi lama tetap jalan)
Kasus klien: teman satu tempat PKL ternyata beda gelombang TKA, tetapi surat dulu otomatis memuat semua siswa perusahaan di satu
gelombang. Aturan baru: **satu siswa = satu gelombang**; surat per perusahaan per gelombang hanya memuat siswa gelombang itu. Rancangan:
`docs/DESAIN-SURAT-SEKOLAH.md` bagian 11. Contoh respons: repo Flutter `test/fixtures/surat/{form_tka, buat_tka_perusahaan,
daftar_tka_ganda, detail_tka_pindah, pindah_siswa, galat_422_pindah, galat_403_pindah}.json` (dibuat oleh
`UJI_FIXTURE=<folder> php spark dev:uji-surat`).
- `GET surat?q=` kini juga mencari **nama siswa** yang tercantum di surat (selain nomor, judul, perusahaan).
- `GET surat?ganda=1` — hanya Surat Izin TKA aktif yang memuat siswa yang juga tercantum di surat TKA lain. `meta.tka_ganda` (selalu
  ada, `[]` bila bersih) = `[{siswa_id, nama, kelas, surat: [{id, kode, label, status, status_teks, perusahaan}]}]` → kotak peringatan
  "N siswa tercantum di lebih dari satu Surat Izin TKA".
- `GET surat/form/tka` — `perusahaan[].jml_bebas` (siswa yang belum punya surat TKA) dan `perusahaan[].siswa[].tka` = surat TKA yang
  sudah memuatnya `{surat_id, kode, label, status_teks}` atau `null`. Siswa dengan `tka` ≠ null **tidak bisa dimasukkan** (pindahkan
  lewat `pindah-siswa` di surat itu). Jenis `asts` tidak berubah.
- `POST surat/tka` (cakupan `perusahaan`) — kirim **`siswa`: `{"<kunci perusahaan>": [siswa_id, …]}`** = siswa yang ikut gelombang ini
  per perusahaan (perusahaan tanpa daftar = tidak ada siswa). **Tanpa `siswa`** (aplikasi lama) = semua siswa perusahaan yang belum
  punya surat TKA. Surat gelombang itu untuk perusahaan yang sama dan masih menunggu ACC → siswa DITAMBAHKAN ke surat itu (`data.ditambah`
  = `[{id, kode}]`); sudah disetujui / belum ada → surat baru (`data.dibuat`). `data.sama` = catatan per perusahaan (siswa yang sudah
  punya surat TKA, atau tidak ada yang dicentang). Tak ada yang dibuat/ditambah → 409.
- `GET surat/{id}` (Surat Izin TKA per perusahaan yang belum dibatalkan) — tiap `siswa[]` + **`tka_lain`** `[{surat_id, kode, label,
  status_teks}]` (surat TKA LAIN yang juga memuat siswa itu = data ganda). **`pindah_tka`**: `null` untuk surat lain, atau
  `{boleh, tujuan: [{jadwal_id, label, hari_tanggal, keadaan}]}` — `boleh` = pemegang hak ACC Izin TKA; `tujuan` = jadwal TKA yang
  belum lewat selain gelombang surat ini (urut tanggal; `[]` bila tidak boleh).
- **`POST surat/{id}/pindah-siswa`** `{siswa_id, tujuan: jadwal_id | "keluar", catatan?}` (hak ACC Izin TKA) → 200
  `{tujuan_id, tujuan_kode}` (null bila "keluar"); `message` menyebut surat yang perlu **diunduh ulang** (nomor tetap). Pindah = siswa
  keluar dari surat ini lalu masuk surat perusahaan yang sama untuk gelombang tujuan (yang masih menunggu dulu, lalu yang sudah
  disetujui; belum ada → surat baru, langsung di-ACC bila surat asal sudah disetujui). "keluar" = keluarkan saja (data ganda). Surat
  yang jadi kosong dibatalkan (nomor tetap tercatat). Galat: 403 tanpa hak · 404 surat tak ada · 422 `errors.siswa_id|tujuan` ·
  409 surat baru saja berubah.
- `GET surat/{id}` `peminta[].diisikan_oleh` = nama teman yang mengisikan gelombang siswa itu (`""` = diminta sendiri). Riwayat: aksi
  baru **`pindah`** ("Siswa pindah gelombang", ikut `meta.aksi_riwayat`). `jml_minta` ikut menghitung siswa yang diisikan temannya.
- Web publik `layanan/surat/izin_tka` (dibuka di peramban dalam aplikasi — tanpa API): siswa memilih gelombangnya sendiri + boleh
  mengisi gelombang teman satu tempat PKL; halaman status menampilkan "diisikan oleh temanmu" dan surat yang memuat namanya.
- Uji: `dev:uji-surat` 402 (TKA siswa, Pindah, form staf, ganda, API), `dev:uji-hak` 69, `dev:uji-api-android` 19.

### PEMBARUAN 2026-10-26b — Surat Perpanjangan PKL (jenis ke-6 `perpanjangan_pkl`, slug `perpanjangan`; docs/DESAIN-PERPANJANGAN-PKL.md)
Jenis baru bernomor + wajib ACC (bawaan), urutan sesudah Balasan sebelum Penarikan. Hak per jenis `surat_{buat|acc|unduh}:perpanjangan_pkl`
(migrasi menyalin hak Surat Balasan). `surat/meta`, Daftar Surat, `permintaan_siswa`, ACC/kembalikan/batal ACC/batal/unduh/unduh massal/WA
**generik — tanpa rute baru**. Isi surat (`isi`): `sapaan, selesai_lama, sumber_lama (sekolah|siswa|staf), selesai_baru, alasan_perpanjangan, alamat`.
- **`GET surat/form/perpanjangan`** — `nilai_awal`, `alasan_saran[{kode, kalimat, label}]`, `sapaan_saran`, `setelan {buka, sebelum, sesudah}`,
  `maks_hari` (183), `batas_pkl {selesai_paling_akhir, durasi_maks_hari}` + SALAH SATU: `?ajuan_id=` → `ajuan` (SuratPerpanjangan::ajuanForm:
  `id, kode, perusahaan, alamat, mulai, selesai, selesai_lama, sumber_lama ("sekolah"|null), bisa, siswa[{siswa_id, nama, kelas, kelas_id, hp,
  bisa, pj{kode, surat_id, status, selesai_baru, berlaku}|null, ditarik (kode)|null}]`) · `?q=` (≥2 huruf) → `hasil[]` (bentuk sama, maks 10) ·
  tanpa keduanya → `berakhir[]` (lihat `GET pkl/berakhir`).
- **`POST surat/perpanjangan`** `{ajuan_id, siswa: [id…], tanggal_surat, sapaan?, selesai_lama? (hanya bila ajuan belum punya tanggal
  selesai), selesai_baru, alasan_pilih ("0".."3"|"lain"), alasan_lain?, langsung_acc?}` → **201** `{dibuat: [{id, kode}], ditambah: [{id, kode}],
  peringatan[], langsung_disetujui, …}` — `ditambah` = siswa ikut surat yang sama (ajuan + tanggal baru sama, belum bernomor).
  Galat: **422** `data.errors.{selesai_baru|selesai_lama|alasan|siswa|tanggal_surat|ajuan_id}` (kunci kolom, bukan list) · **409**
  `data.kode="sudah"` (maksimal SATU kali per siswa per ajuan; surat dibatalkan = boleh lagi) · 403 tanpa hak Buat · 500 gagal simpan.
- **`POST surat/{id}/ubah`** (perpanjangan, belum bernomor) — isian sama dengan buat (`siswa` = daftar lengkap) → 200; 422 per kolom seperti di atas.
- **`GET surat/{id}`** → **`perpanjangan`** `{selesai_lama, sumber_lama, mulai, selesai_baru, lama_teks ("1 (satu) bulan"), periode_teks
  ("31 Oktober s.d. 30 November 2026"), berlaku (= surat disetujui), alasan}` | null untuk jenis lain. `peringatan[]` memuat "awas" bila
  tanggal semula diketik siswa / melewati batas sekolah (surat itu dilewati ACC massal).
- Tanggal baru BERLAKU hanya bila surat disetujui; batal ACC / batal surat → tanggal lama berlaku lagi (dihitung, bukan disalin).
- Permintaan dari siswa: Layanan Siswa web + API publik `publik/layanan/perpanjangan` (docs/API-LAYANAN-SISWA.md).
- Uji: `php spark dev:uji-perpanjangan` (127 cek: aturan, alur, lintas ajuan, Word, halaman staf, Layanan Siswa, API staf & publik) — fixture
  ditulis dengan `UJI_FIXTURE=<folder> php spark dev:uji-perpanjangan`.

**PEMBARUAN 2026-10-28 — siswa satu PERUSAHAAN, lintas ajuan** (docs/DESAIN-PERPANJANGAN-PKL.md bagian 13; tanpa rute baru, kolom lama tetap):
- `ajuan` (ajuanForm) kini memuat **semua siswa yang PKL di perusahaan yang sama** (ajuan yang dipilih dulu, lalu ajuan lain urut id).
  Tambahan: `jml_ajuan`, `ajuan_ids[]`; tiap `siswa[]` + `ajuan_id`, `ajuan_kode`, `selesai_ajuan` (tanggal selesai tercatat di ajuannya |
  null), `ajuan_ini` (bool), `beda` (tanggal selesai tercatat yang BEDA dari `selesai_lama` → `bisa=false`; layar: "buat surat terpisah" =
  buka form dengan `ajuan_id` siswa itu). Bawaan tercentang di web = siswa `ajuan_ini && bisa`.
- `hasil[]` (`?q=`) = **satu butir per perusahaan** (ajuan yang cocok paling baru).
- `POST surat/perpanjangan` / `surat/{id}/ubah`: `siswa[]` boleh dari ajuan mana pun di perusahaan `ajuan_id`. Siswa beda tanggal selesai →
  **422** `errors.siswa`; siswa perusahaan lain → 422 `errors.siswa`. `ditambah` = ikut surat perusahaan itu yang tanggal semula & barunya sama.
- `pkl/ajuan/{id}` → `anggota[].perpanjangan` & `selesai_efektif` dihitung dari ajuan asal tiap siswa, jadi ajuan teman juga menampilkan
  perpanjangan dari surat yang dibuat lewat ajuan lain.

## Galat
- **422 isian** (buat/ubah): `data.errors.galat` = LIST kalimat dari library (`periksa()`); `message` = kalimat pertama.
- **422 keputusan**: `data.errors.{catatan|bahaya|ids}` (`wakil` tidak dipakai lagi sejak 2026-10-13).
- **403**: tak punya hak (`data` null) — dari gerbang peran, `tolakKelola`, atau `SuratKeputusan`.
- **404**: surat/slug tak dikenal. **409**: status sudah berubah, sudah bernomor, kembar, atau unduhan gagal dirakit (`data.kode`).
- **401**: tanpa/salah token.

## Perapian web yang ikut dibuat (satu sumber aturan)
- `SuratSekolah::alasanTakBisaUbah()` — dipakai `SuratDasar::muatBisaDiubah` (web) dan API.
- `SuratAcara::ubahSurat()` & `SuratAcara::nilaiAwal()` — dipindah dari `Admin\SuratIzin` (perilaku web tidak berubah).

## Uji (2026-10-12)
- HTTP e2e dengan token sungguhan: **35/35 lulus** — gerbang peran (Operator boleh `surat`, tetap 403 ke master; tanpa token 401),
  ASTS buat→kembar 409→ubah→Operator ACC 403→Admin tanpa wakil 422→kembalikan tanpa/dengan alasan→ajukan ulang→ACC→ubah 409→
  Hubin unduh 403→Operator unduh (.docx, nomor terbit, header)→batal ACC 409→daftar tersaring; Pernyataan siswa pilihan dengan
  `isi_ortu:false` + unduh massal; Balasan & Penarikan pada ajuan contoh (422 tanpa periode, buat, ubah, ACC massal, batal); slug asing 404.
- Regresi web: `dev:uji-surat` 309 ✅ · `dev:uji-pkl` 324 ✅.
- 23 respons nyata (data fiktif) disimpan sebagai fixture Flutter. Semua data uji dihapus.

## Uji lapor diterima (2026-10-13)
- `dev:uji-lapor` bagian API: daftar + hitungan + hak + tautan, status asing 422, peran tanpa hak 403, kembalikan (422/200),
  setujui → surat disetujui + Audit Log "via aplikasi", proses ulang 409, laporan tak ada 404.

## Deploy
`git pull` saja untuk endpoint lama. Endpoint lapor butuh migrasi `2026-10-17-000002_PklLapor` (`php spark migrate`) — lihat
`docs/PANDUAN-DEPLOY-HAK-AKSES.md`. Aplikasi Android baru bisa memakai menu ini setelah layar Flutter dibuat (BLUEPRINT-SURAT).
