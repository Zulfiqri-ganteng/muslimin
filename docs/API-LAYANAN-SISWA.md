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

### `siswa`
`{id, nama, kelas, tingkat, ajuan: {id, kode, status, perusahaan} | null}` — TANPA NISN / tanggal lahir.

### `surat[]` (halaman "Layanan Siswa")
`{kunci, judul, ket, ikon, jenis, siap, boleh, alasan, api, url_web}` — `boleh=false` → tampilkan `alasan`; `api=true` → formulir asli di
aplikasi (sekarang hanya `perpanjangan`); selain itu buka `url_web` di peramban dalam aplikasi.

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
| `teman[]` | `{id, nama, kelas, bisa, ket}` — teman satu ajuan; `bisa=false` → tampil redup dengan `ket` |
| `maks_hari` | 183 |

### `POST publik/layanan/perpanjangan`
```json
{ "pilih_tanggal": "2026-11-27" | "lain", "selesai_baru": "Y-m-d (bila lain)", "selesai_lama": "Y-m-d (bila selesai_lama null)",
  "alasan_pilih": "0".."3" | "lain", "alasan_lain": "…", "teman": [123, 456] }
```
- `201` → tampilkan `message`, lalu buka "Permintaan suratku" (`GET publik/layanan/saya`).
- `422 data.errors.{selesai_baru|pilih_tanggal|selesai_lama|alasan|siswa|jendela}` — kalimat siap tampil.
- `409 data.kode="sudah"` (sudah pernah diperpanjang) · `409 data.kode="tidak_boleh"` · `401` token · `500` gagal simpan.

## Keamanan
- Token = HMAC-SHA256 (kunci turunan `encryption.key`) atas `{siswa_id, kedaluwarsa}`; tanpa tabel, tidak bisa dipalsukan, kedaluwarsa 30 menit
  (`Libraries\LayananToken`). Bukti diri sama dengan web: NISN ATAU tanggal lahir, dibatasi `LoginThrottle` (per siswa + IP).
- Profil tidak memuat NISN / tanggal lahir. Daftar nama per kelas sama dengan web (id + nama saja).
- CORS mengizinkan header `X-Layanan-Token`.

## Deploy
`git pull` + `php spark migrate` (migrasi `2026-10-27-000001_PklPerpanjangan`) + pastikan `encryption.key` terisi di `.env` hosting
(tanpa itu `POST publik/layanan/masuk` menjawab 503).
