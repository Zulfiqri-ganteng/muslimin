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

## Gerbang & hak
- `ApiAuthFilter` + `Config\Peran 'api_akses'`: Operator & Waka Hubin kini `['pkl', 'surat']` (Admin `*`). API lain tetap 403.
- Daftar, detail, meta: terbuka bagi ketiga peran.
- Hak **`surat_sekolah`** (bawaan Operator) — buat, ubah, ajukan ulang, batalkan, unduh, formulir: dijaga controller (`tolakKelola`)
  dan library (`SuratKeputusan` untuk ajukan ulang/batal).
- Hak **`acc`** (bawaan Waka Hubin) — ACC, kembalikan, batal ACC, ACC massal: dijaga `SuratKeputusan`. **Admin** cadangan wajib `wakil=1`.
- Hak diatur Admin di web (PKL → Hak Akses); respons membawa `hak` agar aplikasi tidak menebak.

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

Slug: `asts`, `tka`, `pernyataan-ortu`, `balasan`, `penarikan` (= ujung alamat menu web). Isian sama dengan formulir web
(rinci per slug: `BLUEPRINT-SURAT.md` §5). Boolean JSON (`true/false`) disamakan jadi `'1'/'0'` sebelum dibaca library
(mis. `isi_ortu: false` tetap berarti "tidak").

## Galat
- **422 isian** (buat/ubah): `data.errors.galat` = LIST kalimat dari library (`periksa()`); `message` = kalimat pertama.
- **422 keputusan**: `data.errors.{wakil|catatan|bahaya|ids}`.
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

## Deploy
`git pull` saja (tanpa migrasi). Aplikasi Android baru bisa memakai menu ini setelah layar Flutter dibuat (BLUEPRINT-SURAT S0–S5).
