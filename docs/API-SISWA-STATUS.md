# API Siswa — status jelas (Nonaktifkan/Aktifkan), Hapus permanen, Terhapus (lama)

Perubahan 2026-10-29 (permintaan user sesudah kasus data ganda Riska/Rizka). Pustaka: `App\Libraries\SiswaStatus`.
Kontrak layar Flutter: `C:\flutter-muslimin\BLUEPRINT-SISWA.md` blok "PEMBARUAN 2026-10-29". Contoh respons NYATA:
`C:\flutter-muslimin\test\fixtures\siswa\siswa_*.json` (ditulis `UJI_FIXTURE=<folder> php spark dev:uji-siswa-status`).
Amplop standar `{status, ok, message, data}`. Hak akses = hak Master Siswa yang sudah ada.

## Aturan

| Tindakan | Arti |
|---|---|
| **Nonaktifkan** | status → `lulus` / `pindah` / `keluar`. Data & riwayat (PKL, surat, biodata, …) TETAP. Siswa tidak muncul di form siswa (Layanan Siswa, Biodata, PKL) dan tidak dihitung aktif. Bisa diaktifkan lagi. |
| **Aktifkan** | status → `aktif`. |
| **Hapus permanen** | baris siswa benar-benar dihapus; NIS/NISN langsung bisa dipakai lagi. **Hanya** bila siswa belum punya data terkait apa pun (tabel ber-kolom `siswa_id`: ajuan/biaya/beasiswa/keringanan/lapor PKL, surat, permintaan surat, isian biodata, catatan terlambat, peserta UKK, ujian susulan). Bila ada → ditolak 409. |
| **Terhapus (lama)** | data yang dulu "dihapus" dengan cara lama (hapus lunak). Bisa dipulihkan atau dihapus permanen (aturan sama). |

Sebelumnya `DELETE` = hapus lunak (data tersembunyi tetapi NIS/NISN tetap terkunci → pesan "NIS sudah terdaftar" yang membingungkan).

## Perubahan endpoint lama

- `GET admin/master/siswa?status=` — selain `aktif|lulus|pindah|keluar` kini juga **`nonaktif`** (lulus+pindah+keluar) dan
  **`terhapus`** (HANYA data terhapus lama).
- Setiap baris siswa (daftar, detail, hasil simpan) mendapat kunci tambahan: `status_label` ("Aktif", "Lulus", "Pindah sekolah",
  "Keluar"), `aktif` (bool), `terhapus` (bool).
- `GET admin/master/siswa/{id}` tambahan: `terkait` = `[{label, jumlah}]` dan `bisa_hapus_permanen` (bool = `terkait` kosong).
- `DELETE admin/master/siswa/{id}` — **hapus permanen**. 200 bila berhasil. **409** bila punya data terkait:
  `{"ok":false,"message":"… TIDAK dihapus karena punya data: Catatan terlambat (1) — pakai Nonaktifkan.","data":{"terkait":[{"label":"Catatan terlambat","jumlah":1}]}}`.
  404 bila tidak ada.
- `POST admin/master/siswa/bulk-delete {ids:[…]}` — hapus permanen yang tanpa data terkait, sisanya dilewati:
  `data = {deleted: n, dihapus: n, ditolak: [{nama, terkait}]}` (`deleted` = kunci lama, dipertahankan). `{mode:"all"}` → 422.
- `POST admin/master/siswa` / `POST admin/master/siswa/{id}` — bentrok NIS/NISN kini 422 dengan kalimat jelas di `errors.nis` /
  `errors.nisn`, termasuk bila pemiliknya data TERHAPUS ("… masih dipakai data siswa yang TERHAPUS: Nama (Kelas). Buka saringan
  Status → "Terhapus (lama)", lalu Pulihkan atau Hapus permanen.").

## Endpoint baru

### POST `admin/master/siswa/{id}/status` — `{ "status": "lulus" | "pindah" | "keluar" | "aktif", "catatan": "opsional" }`

Nonaktifkan / aktifkan. `catatan` (alasan) ditambahkan ke `keterangan` siswa bersama tanggal. 200 → `data` = baris siswa segar,
`message` = kalimat hasil. 422 → `errors.status`. 404 → siswa tidak ada.

### POST `admin/master/siswa/{id}/pulihkan`

Data terhapus lama dikembalikan (status semula). 200 → baris siswa segar (`terhapus: false`). 404 → bukan data terhapus.

## Web (Master Siswa)

Per baris: Detail · Ubah · **Nonaktifkan** (aktif) / **Aktifkan** (tidak aktif). Hapus permanen: halaman Detail (kartu "Status
siswa", tombol hanya muncul bila tanpa data terkait) dan centang → Hapus terpilih. "Hapus semua" dihapus. Saringan Status:
Aktif, Tidak aktif (semua), Lulus, Pindah sekolah, Keluar, Terhapus (lama). Perintah `siswa:hapus-keluar --tulis` kini menandai
status **keluar** (tidak lagi hapus lunak).

## Periksa Data Siswa (Dashboard "Tugas hari ini") — tambahan 2026-10-29

Pustaka `App\Libraries\PeriksaSiswa` (hanya membaca). Web: Dashboard "Tugas hari ini" + halaman `admin/master/siswa/periksa`
(tombol "Periksa data" di Master Siswa). Terminal: `phpm spark siswa:periksa`. Fixture: `siswa_periksa.json`.

### GET `admin/master/siswa/periksa`

```json
{ "data": {
    "jumlah": { "ganda": 1, "tidak_sesuai": 2, "tidak_bisa_masuk": 1, "penempatan": 62, "tidak_aktif": 3, "terhapus": 70, "tanpa_kelas": 0 },
    "ganda": [ { "a": {siswa lebih lengkap}, "b": {kemungkinan salah input}, "alasan": "nama berbunyi sama, salah satu data belum lengkap" } ],
    "tidak_sesuai": [ { "id": 1800, "nama": "…", "nis": "…", "nisn": "…", "tanggal_lahir": null, "kelas": "XI TKJ 5", "status": "aktif",
                        "masalah": ["NIS tidak wajar", "NISN tidak wajar", "Tanggal lahir kosong", "Tidak bisa masuk Layanan Siswa"], "bisa_masuk": false } ],
    "penempatan": [ {siswa} ],                       // kelas hasil penempatan otomatis — perlu dicek
    "tugas": [ { "tingkat": "awas" | "info", "n": 1, "teks": "kemungkinan data siswa ganda (satu siswa tercatat dua kali)",
                 "bagian": "ganda" | "tidak_sesuai" | "penempatan" | "daftar:status=terhapus" | "daftar:status=nonaktif" | "daftar:kelas_id=tanpa" } ]
} }
```

Bentuk {siswa} = `id, nama, nis, nisn, tanggal_lahir, kelas, status`. `tugas` hanya berisi butir yang `n > 0`, urut kepentingan, siap
ditampilkan sebagai kartu "Tugas hari ini"; `bagian` = tujuan ketukan (bagian layar Periksa, atau daftar siswa dengan saringan itu).

Aturan:
- **ganda** — nama mirip (huruf saja / bunyi sama / beda 1–2 huruf) di tingkat yang sama (atau tanggal lahir sama) DAN salah satunya
  belum lengkap (tanggal lahir kosong / NISN tidak sah). Dua data lengkap (NISN sah berbeda) TIDAK dianggap ganda — termasuk anak kembar.
- **tidak_sesuai** — NIS tidak wajar (bukan 4–12 angka / angka berulang), NISN kosong/tidak sah (bukan 10 angka / berulang), tanggal
  lahir kosong. `bisa_masuk = false` bila NISN tidak sah DAN tanggal lahir kosong (tidak bisa masuk Layanan Siswa / Biodata).
