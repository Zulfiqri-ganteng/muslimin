# API publik Layanan Siswa (tanpa akun) — Surat Perpanjangan PKL + status surat

Dibuat 2026-10-26 untuk layar Android siswa (`C:\flutter-muslimin\BLUEPRINT-SURAT.md` blok PEMBARUAN 2026-10-26b, tahap S14).
Rancangan & aturan: `docs/DESAIN-PERPANJANGAN-PKL.md` (R1–R12). Logika SAMA dengan halaman web `layanan/*`
(`Libraries\LayananSiswa`, `Libraries\SuratPerpanjangan`) — yang diubah sekolah di web (buka/tutup, H-sebelum/H-sesudah) langsung berlaku di aplikasi.
Contoh respons NYATA (data fiktif): `C:\flutter-muslimin\test\fixtures\layanan\`. Uji: `php spark dev:uji-perpanjangan` (bagian API).

Semua alamat di bawah `api/v1/`. Amplop respons sama dengan API lain: `{status, message, data}`.

## Alur
1. `GET publik/layanan/kelas?tujuan=perpanjangan` → siswa memilih kelas.
2. `GET publik/layanan/siswa?kelas_id=` → memilih nama.
3. `POST publik/layanan/masuk` `{siswa_id, nisn}` atau `{siswa_id, tanggal_lahir: "Y-m-d"}` → **token**.
4. Panggilan berikutnya mengirim header **`X-Layanan-Token: <token>`** (berlaku 30 menit = sesi web; habis/salah → `401` `data.kode = "masuk_lagi"` → ulangi langkah 1–3).

## Endpoint
| Metode & alamat | Token | Isi |
|---|---|---|
| `GET publik/layanan/kelas?tujuan=` | – | `[{tingkat, kelas: [{id, nama}]}]` — tingkat yang dilayani (tingkat PKL + XII); `tujuan=izin_tka` → hanya XII |
| `GET publik/layanan/siswa?kelas_id=` | – | `[{id, nama}]` (tanpa data lain). Kelas tanpa siswa / di luar tingkat → `404` |
| `POST publik/layanan/masuk` | – | `200 {token, berlaku_sampai, siswa}` · bukti salah `422 data.errors.bukti` · siswa tak ada `404` · NISN & tgl lahir belum tercatat `409` · terlalu sering salah `429` · kunci aplikasi belum diatur `503` |
| `GET publik/layanan/saya` | ✔ | `{siswa, surat[], permintaan[]}` |
| `GET publik/layanan/perpanjangan` | ✔ | bahan formulir (di bawah) · tidak boleh (bukan siswa PKL disetujui / form ditutup) → `409 data.kode = "tidak_boleh"` |
| `POST publik/layanan/perpanjangan` | ✔ | kirim permintaan → `201 {surat_id, kode: "baru"\|"ikut", kode_surat}` |
| `GET publik/layanan/surat/{kunci}` | ✔ | bahan formulir `izin_ujian` · `izin_tka` · `pernyataan` · `penarikan` (bagian "Formulir surat" di bawah) · tidak boleh → `409 data.kode = "tidak_boleh"` · kunci lain → `404` |
| `POST publik/layanan/surat/{kunci}` | ✔ | kirim permintaan (bagian "Formulir surat") |

`saya`, `perpanjangan` (GET) dan `surat/{kunci}` (GET) membawa **`token_baru`**: pakai untuk panggilan berikutnya. Sesi web diperpanjang tiap
aktivitas; dengan token baru ini siswa yang masih memakai layanan juga tidak dikeluarkan di tengah jalan.

### `siswa`
`{id, nama, kelas, tingkat, ajuan: {id, kode, status, perusahaan} | null}` — TANPA NISN / tanggal lahir.

### `surat[]` (halaman "Layanan Siswa")
`{kunci, judul, ket, ikon, jenis, siap, boleh, alasan, api, url_web}` — `boleh=false` → tampilkan `alasan`; `api=true` → formulir asli di
aplikasi (sejak 2026-11-01 kelima surat); selain itu buka `url_web` di peramban dalam aplikasi.

### `permintaan[]` ("Permintaan suratku")
`{id, jenis, judul, dibuat, perusahaan, nomor, catatan, status, status_teks, status_kelas, status_ket}` — status: `menunggu` · `diperiksa`
· `disetujui` · `ambil` (Siap diambil di TU) · `batal`. `catatan` contoh: "Namamu dicantumkan oleh temanmu, …" (teman yang mencentang).

### `GET publik/layanan/perpanjangan`
| Kunci | Isi |
|---|---|
| `status` | `isi` (tampilkan formulir) · `sudah` (sudah punya surat — maksimal 1×) · `ditarik` · `dini` (terlalu cepat) · `lewat` (lewat batas) |
| `pesan` | kalimat untuk status selain `isi` (sama dengan web) |
| `perusahaan`, `ajuan {id, kode, alamat, mulai}` | tempat PKL |
| `selesai_lama` | tanggal selesai semula (Y-m-d) **atau null = belum tercatat** → minta isian `selesai_lama` (tanpa pilihan cepat) |
| `sisa_hari` | hari menuju `selesai_lama` (negatif = sudah lewat) |
| `jendela` | `{status, buka_mulai, tutup}` (null bila `selesai_lama` null) |
| `setelan` | `{buka, sebelum, sesudah}` (hari; 0 = tanpa batas) |
| `sudah` | `{surat_id, kode, status, selesai_baru, berlaku}` \| null |
| `ditarik` | kode Surat Penarikan \| null |
| `cepat[]` | `{label: "+1 bulan", tanggal: "Y-m-d", teks: "27 November 2026"}` ×4 (+2 minggu, +1/2/3 bulan) |
| `alasan_saran[]` | `{kode: "0".."3", kalimat, label}` (+ pilihan "lain" di layar) |
| `teman[]` | `{id, nama, kelas, bisa, ket, satu_ajuan, ajuan_kode}` — **semua teman yang PKL di perusahaan yang sama** (2026-10-28; dulu hanya satu ajuan): `satu_ajuan=true` dulu, lalu ajuan lain urut nama. `bisa=false` → tampil redup dengan `ket` (sudah diperpanjang / ditarik / "PKL-nya selesai … (beda tanggal), minta surat sendiri"). `ket` bisa terisi walau `bisa=true` ("Tercatat selesai …" bila `selesai_lama` null) — tampilkan sebagai keterangan kecil. Daftar bisa panjang (belasan) → pakai daftar bergulir, bukan chip. |
| `maks_hari` | 183 |

### `POST publik/layanan/perpanjangan`
```json
{ "pilih_tanggal": "2026-11-27" | "lain", "selesai_baru": "Y-m-d (bila lain)", "selesai_lama": "Y-m-d (bila selesai_lama null)",
  "alasan_pilih": "0".."3" | "lain", "alasan_lain": "…", "teman": [123, 456] }
```
- `201` → tampilkan `message`, lalu buka "Permintaan suratku" (`GET publik/layanan/saya`).
- `teman` boleh berisi id teman dari ajuan lain di perusahaan yang sama → semuanya masuk SATU surat (bila sudah ada surat perusahaan itu
  dengan tanggal semula & baru yang sama dan belum bernomor, siswa ditambahkan ke surat itu).
- `422 data.errors.{selesai_baru|pilih_tanggal|selesai_lama|alasan|siswa|jendela}` — kalimat siap tampil. `errors.siswa` juga untuk teman
  yang tanggal selesai PKL-nya beda ("Satu surat hanya untuk satu tanggal selesai PKL …").
- `409 data.kode="sudah"` (sudah pernah diperpanjang) · `409 data.kode="tidak_boleh"` · `401` token · `500` gagal simpan.

## Formulir surat (`publik/layanan/surat/{kunci}`, ditambah 2026-11-01)
Padanan halaman web `layanan/surat/{kunci}`, memakai fungsi yang sama (`LayananSiswa::form*`, `ajukan*`). Web tidak diubah.
Uji & fixture: `UJI_FIXTURE=<folder> php spark dev:uji-layanan-surat`; contoh nyata `C:\flutter-muslimin\test\fixtures\layanan_surat\`.

GET selalu memuat `{kunci, judul, ket, siswa, token_baru}`, ditambah:

| Kunci | Isi GET | Isi POST |
|---|---|---|
| `izin_ujian` | `jadwal[] {id, label, hari_tanggal, tempat, luar_periode, sudah: {surat_id, kode} \| null}`, `perusahaan {nama, alamat} \| null`, `teman[]` (nama) | `{jadwal_id}` |
| `izin_tka` | `jadwal[] {id, label, hari_tanggal, tempat, luar_periode}`, `perusahaan`, `saya {penempatan[], jadwal_id, terkunci}`, `teman[] {id, nama, kelas, penempatan[], jadwal_id, terkunci, sendiri}`. `penempatan[] {surat_id, kode, status, label, hari_tanggal, status_teks, bisa_ubah, sendiri, diisikan, teks}`; `teks` = kalimat siap tampil | `{jadwal_id, teman: {siswa_id: jadwal_id}}` — teman yang tidak diubah tidak perlu dikirim |
| `pernyataan` | `ortu {nama, pekerjaan, alamat, hp}`, `hp_anak`, `aktif {surat_id, kode, status, cetak_ke} \| null`, `dicetak` (true = tidak bisa diperbaiki lagi), `maks {nama, pekerjaan, alamat, hp}` | `{nama, pekerjaan, alamat, hp, hp_anak}` |
| `penarikan` | `aktif {surat_id, kode, status} \| null` (terisi = tanpa formulir), `perusahaan`, `alasan[] {kode, label}` (+ pilihan "lain" di layar) | `{alasan_pilih: kode \| "lain", alasan_lain, tgl_efektif: "Y-m-d"}` |

Balasan POST:
- `201 {surat_id, kode: "baru"|"ikut", kode_surat}`: permintaan baru. `200` dengan `kode: "sudah"|"ubah"`: tidak ada permintaan baru. Tampilkan `message`, lalu buka "Permintaan suratku".
- `422 data.errors.{kolom}`: galat per kolom (`nama`, `alamat`, `hp`, `hp_anak`, `pekerjaan`, `alasan_pilih`, `alasan_lain`, `tgl_efektif`, `umum`); `message` = "Periksa lagi isianmu."
- `409 data.kode`: `tidak_boleh` (kembali ke halaman siswa + tampilkan `message`), `tolak` / `gagal` (tampilkan `message` di formulir lalu muat ulang bahannya).
- `401`: token habis, ulangi masuk.

## LY4 — Lapor Diterima PKL (`publik/pkl/lapor`, ditambah 2026-11-01)
`Api\LaporPkl` = form web `pkl/lapor` (`Controllers\LaporPkl`); aturan, pesan & penyimpanan SAMA (`Libraries\PklLapor`). Tidak memakai
token Layanan Siswa: bukti diri lapor = nomor HP (seperti web), dan izin kirimnya (di web: sesi 1 jam) dibawa token **`X-Izin-Token`**
(`LayananToken::buatIzin('pkl-lapor', …)`, kunci turunan sendiri → tidak berlaku sebagai token Layanan Siswa, dan sebaliknya).
- `GET publik/pkl/lapor` → `{terbuka, kelas: [{tingkat, kelas: [{id, nama}]}], hasil_ada, maks_alamat, maks_catatan}`; ditutup Admin
  (`lapor_buka` = 0) → `{terbuka: false}` (tampilkan "Form lapor sedang ditutup" web).
- `GET publik/pkl/lapor/siswa?kelas_id=` → `[{id, nama, status}]`, status `boleh|sudah_lapor|dikembalikan|selesai|belum_disetujui|ajukan_lagi|belum_ajuan`
  (lencana & kalimat per status = `pkl-lapor.js` / `pkl/lapor.php`). Tanpa kelas → 422; ditutup → 403 `kode: tutup`.
- `POST publik/pkl/lapor/cek` `{siswa_id, hp}` → ringkasan ajuan (`ajuan_id, kode, perusahaan, alamat, mulai, selesai, anggota[], balasan,
  hasil_ada, lapor{status, status_teks, alasan, mulai, selesai, alamat, catatan, pelapor, waktu, hasil, diterima[]}|null`) + `token`,
  `berlaku_sampai`. Galat = kalimat web (404 siswa, 409 ajuan belum ada/belum disetujui, 422 HP salah, 429 terlalu banyak percobaan);
  503 bila `encryption.key` kosong.
- `POST publik/pkl/lapor/kirim` (header `X-Izin-Token`) `{ajuan_id, hasil: semua|sebagian|ditolak, diterima: [siswa_id], tanggal_mulai,
  tanggal_selesai, alamat, catatan}` → 200 `{kode, perusahaan, periode, pelapor, anggota[], ulang, hasil, hasil_teks, diterima[], ditolak[]}`
  (isi layar "Laporan terkirim"). 422 `data.errors.{hasil|diterima|tanggal_mulai|tanggal_selesai|alamat|catatan}`; 403 `kode: sesi_habis`
  (token tidak ada/kedaluwarsa/ajuan lain → ulangi langkah 1); 409 kalimat web (Balasan sudah dibuat, sudah diproses, gagal simpan).
- Uji: `php spark dev:uji-lapor` bagian 5d (12 cek; `--fixture <folder>` menyimpan respons untuk tes aplikasi).

## LY5 — Ajukan tempat PKL (`publik/pkl`, ditambah 2026-11-01)
`Api\PklAjukan` = TURUNAN controller web `Controllers\Pkl` (form `pkl`), jadi aturan, pesan & pengamannya sama persis (satu siswa satu
ajuan, pesan terkunci, batas kiriman per IP, `LoginThrottle` saat buka). Yang berbeda hanya bentuk: isian JSON (dibaca sebagai POST;
`true` → "1"), amplop API, izin kirim PERBAIKAN = token `X-Izin-Token` (`buatIzin('pkl-ajukan', {ajuan}, 1 jam)`; web: sesi), dan
ringkasan "terkirim" langsung di respons (web: halaman `pkl/selesai`). Tanggal PKL & tanggal lahir tidak ditanyakan (sama dengan web).
- `GET publik/pkl` → `{terbuka:true, kelas: [{tingkat, kelas: [{id, nama}]}], maks_siswa, batas_hari, lapor_terbuka}`; ditutup →
  `{terbuka:false, alasan: belum_dibuka|sudah_ditutup|belum_siap, judul_tutup, pesan_tutup, lapor_terbuka}` (kalimat `pkl/tutup.php`).
- `GET publik/pkl/siswa?kelas_id=` → `[{id, nama, jk, status: belum|ditolak|menunggu|disetujui|perbaikan|lepas, peran: pengaju|teman|null,
  batas: Y-m-d|null}]` (lencana & panel per status = `pkl.js` / `pkl/form.php`). 422 tanpa kelas / kelas belum boleh; 403 form tutup.
- `GET publik/pkl/perusahaan?q=` (≥ 2 huruf) → saran `[{id, nama, alamat, kota, telepon, kontak_nama, kontak_jabatan}]`.
- `POST publik/pkl/buka` `{siswa_id, hp}` (hanya PENGAJU ajuan `perbaikan`) → `{ajuan_id, perusahaan_*, kontak_*, hp, teman: [{id, nama,
  kelas, hp}], catatan, token}`; 404/409/422/429 kalimat web; 503 bila `encryption.key` kosong.
- `POST publik/pkl/kirim` `{siswa_id, perusahaan_nama, perusahaan_alamat, perusahaan_kota, perusahaan_telepon, kontak_nama, kontak_jabatan,
  hp, pernyataan: "1", teman: [id], teman_hp: {id: hp}, ajuan_id?}` (+ `X-Izin-Token` bila `ajuan_id`) → 200 `{nama, kelas, no, revisi,
  perusahaan, jumlah, batas, hari}`. 422 `data.errors.{perusahaan_*|kontak_nama|hp|pernyataan|teman|hp_teman_<id>}`; 409 pesan terkunci /
  teman bentrok (`data.errors.teman`); 403 belum buka / token habis; 429 batas kiriman.
- Uji: `php spark dev:uji-pkl-publik` (24 cek: 11 jalur web sebagai patokan + 13 jalur API, skenario sama; `--fixture <folder>`).

## LY6 — Isi biodata siswa (`publik/biodata`, ditambah 2026-11-02)
`Api\BiodataPublik` = TURUNAN controller web `Controllers\Biodata` (form `biodata`), jadi aturan (`BiodataForm`), pesan & pengamannya sama
persis (satu isian per siswa, NISN/NIS unik, isian terkunci sesudah kirim, buka ulang dengan NISN ATAU tanggal lahir + `LoginThrottle`).
Yang berbeda hanya bentuk (sama dengan LY5; trait `IsianJsonSebagaiPost` dipakai keduanya): isian JSON dibaca sebagai POST, amplop API,
izin kirim PERBAIKAN = token `X-Izin-Token` (`buatIzin('biodata-buka', {siswa}, 1 jam)`; web: sesi), ringkasan "terkirim" di respons.
- `GET publik/biodata` → `{terbuka:true, kelas: [{tingkat, kelas: [{id, nama}]}], sekolah, label, bagian, wajib, agama, status_keluarga,
  pekerjaan, tahun_lahir: [min, maks], tahun_terima: [min, maks]}` (label/bagian/wajib = `BiodataForm`, pilihan = `SiswaModel`); ditutup →
  `{terbuka:false, judul_tutup, pesan_tutup}` (kalimat `biodata/tutup.php`).
- `GET publik/biodata/siswa?kelas_id=` → `[{id, nama, jk, status: belum|menunggu|disetujui|perbaikan}]`.
- `POST publik/biodata/buka` `{siswa_id, nisn?, tanggal_lahir?}` (cukup salah satu; hanya isian `perbaikan`) → isian lama (kolom server;
  NIS/NISN resmi mengisi yang masih kosong) + `catatan` + `token`; 404/409/422/429 kalimat web; 503 bila kunci aplikasi kosong.
- `POST publik/biodata/kirim` (kolom `BiodataForm` + `siswa_id`, `ortu_sama: "1"|"0"`, `ortu_telepon1/2`, `punya_wali: ya|tidak`,
  `pernyataan: "1"`; + `X-Izin-Token` untuk perbaikan) → 200 `{nama, kelas, no, revisi}`. 422 `data.errors.{kolom|ortu_telepon|punya_wali|
  pernyataan}`; 409 terkunci; 403 belum buka / token habis.
- Uji: `php spark dev:uji-biodata-publik` (22 cek: 10 jalur web sebagai patokan + 12 jalur API, skenario sama; `--fixture <folder>`).

## Keamanan
- Token = HMAC-SHA256 (kunci turunan `encryption.key`) atas `{siswa_id, kedaluwarsa}`; tanpa tabel, tidak bisa dipalsukan, kedaluwarsa 30 menit
  (`Libraries\LayananToken`). Bukti diri sama dengan web: NISN ATAU tanggal lahir, dibatasi `LoginThrottle` (per siswa + IP).
- Profil tidak memuat NISN / tanggal lahir. Daftar nama per kelas sama dengan web (id + nama saja).
- CORS mengizinkan header `X-Layanan-Token` dan `X-Izin-Token` (izin form lapor PKL, ajukan PKL, biodata).

## Deploy
`git pull` + `php spark migrate` (migrasi `2026-10-27-000001_PklPerpanjangan`) + pastikan `encryption.key` terisi di `.env` hosting
(tanpa itu `POST publik/layanan/masuk` menjawab 503).
