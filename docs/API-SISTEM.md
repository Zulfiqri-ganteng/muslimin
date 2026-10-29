# API Sistem — Mode Maintenance, Log Error Hosting, Backup, Sesi Aktif

Kontrak untuk aplikasi Android (Flutter). Rancangan fitur: `docs/DESAIN-ALAT-SISTEM.md` (F1–F5). Kontrak layar Flutter:
`C:\flutter-muslimin\BLUEPRINT-SISTEM.md`. Contoh respons nyata: `C:\flutter-muslimin\test\fixtures\sistem\` (ditulis
`UJI_FIXTURE=<folder> php spark dev:uji-maintenance` / `dev:uji-backup` / `dev:uji-sesi`). Amplop standar: `{status, ok, message, data}`.
Semua endpoint bagian 2–6 KHUSUS Admin (peran lain 403).

## 1. Mode Maintenance — berlaku untuk SEMUA permintaan API

Saat Admin mengaktifkan Mode Maintenance (web Sistem → Mode Maintenance, API di bawah, atau `phpm spark maintenance on`):

| Permintaan | Hasil |
|---|---|
| Token milik akun **Admin** | jalan seperti biasa |
| `auth/login`, `auth/biometric/login`, `auth/logout`, `app/bootstrap`, `app/status`, `app/apk/register`, preflight `OPTIONS` | jalan |
| Semua lainnya (publik tanpa token, token Operator/Waka Hubin/Guru Piket/peran lain, token tidak sah) | **HTTP 503** |
| Login (`auth/login` / sidik jari) dengan akun selain Admin | **HTTP 503** (sandi sudah benar, tetapi ditolak) |

Bentuk 503 (header `Retry-After: <detik>`):

```json
{
  "status": "error", "ok": false, "kode": "maintenance",
  "message": "Sedang perbaikan database.",
  "data": { "maintenance": {
      "aktif": true,
      "pesan": "Sedang perbaikan database.",
      "selesai": "2026-10-29 23:02:00",          // perkiraan selesai, boleh null
      "selesai_label": "Kamis, 29 Oktober 2026 pukul 23.02 WIB",   // "" bila tanpa perkiraan
      "mulai": "2026-10-29 22:02:57",
      "coba_lagi": 3543                          // saran detik sebelum mencoba lagi (≥ 60)
  } }
}
```

Penanda yang dipakai aplikasi: **HTTP 503 dan `data.maintenance` ada** (`kode` = `"maintenance"` juga ada di galat penyaring;
galat login 503 hanya membawa `data.maintenance`). Mode maintenance TIDAK mati sendiri walau perkiraan selesai lewat.

### GET `app/status` — publik, tetap terbuka saat maintenance

Ringan (tanpa database). Dipakai tombol "Coba lagi" di layar perawatan.

```json
{ "ok": true, "data": { "maintenance": { …objek di atas…, "aktif": false bila mati }, "waktu_server": "2026-10-29 22:02:57" } }
```

Saat mati: `aktif=false`, `selesai=null`, `selesai_label=""`, `mulai=null`, `pesan` = pesan terakhir/bawaan.

### GET `app/bootstrap` — kolom baru

`maintenance` (bool — kini mengikuti status sungguhan, dulu selalu false), `maintenance_message` (string), dan **`maintenance_info`**
(objek sama dengan di atas). Dua kunci lama dipertahankan demi aplikasi yang sudah beredar.

## 2. Admin — Mode Maintenance (Bearer, KHUSUS Admin; peran lain 403)

### GET `sistem/maintenance`

`data` = objek maintenance di atas + kolom isian layar:

| Kolom | Arti |
|---|---|
| `pesan_tersimpan` | pesan yang tersimpan (isi awal kotak pesan) |
| `selesai_isian` | `"Y-m-d H:i"` perkiraan selesai yang tersimpan (null bila mati / tanpa perkiraan) |
| `oleh` | nama yang mengaktifkan (null bila mati) |
| `dimatikan`, `dimatikan_oleh` | kapan & oleh siapa terakhir dimatikan (null bila belum pernah) |
| `pesan_bawaan`, `maks_pesan` | teks bawaan & batas huruf (300) |

### POST `sistem/maintenance` — JSON

```json
{ "aktif": true, "pesan": "Perbaikan server.", "selesai": "2026-10-30 22:00" }   // nyalakan / perbarui (sudah aktif = perbarui pesan)
{ "aktif": false }                                                               // matikan
```

- `pesan` kosong → pesan bawaan. Maks 300 huruf. `selesai` opsional, `"Y-m-d H:i"` (atau `"Y-m-d\TH:i"`), harus waktu yang akan datang.
- 200 → `data` sama dengan GET, `message` = kalimat hasil untuk ditampilkan (snackbar).
- 422 → `data.errors.aktif` (kunci `aktif` tidak dikirim) atau `data.errors.selesai` (waktu tidak sah / sudah lewat / pesan terlalu panjang).
- Setiap aktif/perbarui/mati dicatat di Audit Log.

## 3. Admin — Log Error Hosting (Bearer, KHUSUS Admin)

### GET `sistem/log-error?berkas=&tingkat=&q=`

Semua parameter opsional. `berkas` = `kunci` dari daftar (kunci lain diabaikan → berkas terbaru). `tingkat` = `berat|galat|peringatan|info`.

```json
{ "data": {
    "berkas": [ { "kunci": "log-2026-10-29.log", "jenis": "aplikasi", "label": "Log aplikasi 29 Oktober 2026", "tanggal": "2026-10-29",
                  "ukuran": 18234, "diubah": "2026-10-29 21:10:02", "hitung": { "berat": 0, "galat": 8, "peringatan": 0, "info": 0 } },
                { "kunci": "php-public", "jenis": "php", "label": "error_log PHP (folder public)", "tanggal": null, … } ],
    "terpilih": "log-2026-10-29.log",          // null bila belum ada log sama sekali
    "saringan": { "tingkat": "", "q": "" },
    "isi": { "jumlah": 8, "terpotong": false,  // terpotong = berkas > 2 MB, hanya ujungnya dibaca
             "kelompok": [ { "tingkat": "galat", "pesan": "Duplicate entry …", "jejak": "… (teks banyak baris) …", "jumlah": 6,
                             "pertama": "2026-10-29 08:16:15", "terakhir": "2026-10-29 09:00:01", "asal": ["web"],   // web | perintah
                             "jam": ["2026-10-29 09:00:01", …],         // maks 30 terbaru
                             "penjelasan": "Data kembar ditolak database …" } ] },   // null bila galat belum dikenal
    "tingkat": [ { "kode": "berat", "label": "Galat berat" }, { "kode": "galat", "label": "Galat" }, … ],
    "simpan_hari": 30
} }
```

`kelompok` = galat yang sama digabung (angka diabaikan), urut kejadian terakhir terbaru di atas. Unduh berkas asli hanya di web.

### POST `sistem/log-error/bersihkan`

Hapus log aplikasi lebih dari 30 hari (log hari ini & error_log PHP tidak pernah dihapus). 200 → `data.terhapus` (angka). Dicatat di Audit Log.

## 4. Admin — Backup (Bearer, KHUSUS Admin)

Aplikasi hanya melihat daftar, membuat, dan menghapus backup. **Unduh & restore sengaja hanya di web** (berkas besar, berisi data
pribadi, restore butuh Mode Maintenance + sandi + ketik PULIHKAN dan berjalan bertahap).

### GET `sistem/backup`

```json
{ "data": {
    "backup": [ { "kunci": "db-20261029-210000-manual.sql.gz", "isi": "db", "jenis": "manual", "label_jenis": "Manual",
                  "waktu": "2026-10-29 21:00:00", "ukuran": 266545, "tabel": 106, "oleh": "Abil Septian" },
                { "kunci": "berkas-20261029-210000-manual.zip", "isi": "berkas", "jenis": "manual", "label_jenis": "Manual", …, "tabel": null, "oleh": null } ],
    "total_ukuran": 1234567,
    "terakhir": "2026-10-29 21:00:00",               // backup DATABASE terbaru (jenis apa pun); null = belum pernah
    "hari_sejak_terakhir": 0,                        // null = belum pernah
    "ingatkan_hari": 7,                              // tampilkan pengingat bila hari_sejak_terakhir >= ini
    "otomatis_terakhir": null,                       // kunci lama; tetap null karena TANPA cron (keputusan user 2026-10-29)
    "disimpan": { "manual": 10, "otomatis": 7, "sebelum-pulihkan": 3 },
    "maintenance_aktif": false
} }
```

Backup dibuat **manual** oleh Admin (tanpa cron). Jenis `otomatis` hanya muncul bila kelak cron dipasang.

`isi`: `db` (database, .sql.gz) | `berkas` (berkas unggahan, .zip). `jenis`: `manual` | `otomatis` | `sebelum-pulihkan` (dibuat otomatis
sebelum restore). Urut terbaru di atas. Yang lebih lama dari batas `disimpan` dihapus otomatis.

### POST `sistem/backup` — `{ "isi": "db" | "berkas" }`

200 → `data` sama dengan GET + `dibuat` (satu baris backup baru). 422 → `data.errors.isi`. 500 → `message` (mis. disk penuh).
Database ±0,3 MB terkompres, butuh beberapa detik — tampilkan indikator.

### POST `sistem/backup/hapus` — `{ "kunci": "db-…" }`

200 → `data` sama dengan GET. 404 → kunci tidak dikenal.

## 5. Admin — Sesi Aktif (Bearer, KHUSUS Admin)

### GET `sistem/sesi`

```json
{ "data": {
    "web": [ { "jenis": "web", "id": 16, "admin_id": 7, "nama": "Abil Septian", "username": "operator", "peran": "operator", "peran_label": "Operator",
               "perangkat": "Chrome 130 · Windows 10", "ip": "36.70.196.141", "masuk": "2026-10-29 08:01:00", "aktif": "2026-10-29 09:12:00", "ini": false } ],
    "android": [ { "jenis": "android", "id": 239, …, "perangkat": "Samsung A15", "ip": "", "masuk": "…", "aktif": "… (terakhir dipakai)",
                   "kedaluwarsa": "2026-12-28 22:31:22", "ini": true } ],      // ini = token yang sedang dipakai aplikasi ini
    "batas_aktif_menit": 60
} }
```

`web` = sesi dengan aktivitas < `batas_aktif_menit` (sesudah itu keluar otomatis). `android` = token belum kedaluwarsa, urut terakhir dipakai.

### POST `sistem/sesi/keluarkan` — `{ "jenis": "web" | "android", "id": 16 }`

Web: sesi itu langsung keluar pada klik berikutnya. Android: token dihapus → aplikasi itu mendapat 401 dan keluar sendiri.
200 → `data` sama dengan GET. 422 → jenis/id salah, atau sesi sendiri (`ini: true`). 404 → sudah berakhir / tidak ada.

### POST `sistem/sesi/keluarkan-semua`

Semua sesi web & Android **kecuali akun sendiri**. 200 → `data` sama dengan GET + `dikeluarkan: {web: n, android: n}`.

## 6. Menu

`auth/menu` (B2) memuat empat item grup SISTEM untuk Admin: `admin/sistem/backup` ("Backup & Restore", ikon `archive`),
`admin/sistem/maintenance` ("Mode Maintenance", `tools`), `admin/sistem/log-error` ("Log Error Hosting", `bug`),
`admin/sistem/sesi` ("Sesi Aktif", `users-group`). Semua sudah aktif (tidak ada lagi tanda Segera).
