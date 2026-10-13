# API Surat Sekolah (Android) — `/api/v1/surat…`

Dibuat 2026-10-12 (Surat Sekolah **L8**). Cermin menu web *SURAT SEKOLAH* (Daftar Surat + 5 jenis) untuk aplikasi
Android staf. Kontrak sisi Flutter (layar, tahap kerja, contoh respons nyata): `C:\flutter-muslimin\BLUEPRINT-SURAT.md`
(**L9**) + fixture `C:\flutter-muslimin\test\fixtures\surat\*.json`. Rancangan modul: `docs/DESAIN-SURAT-SEKOLAH.md`.

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
