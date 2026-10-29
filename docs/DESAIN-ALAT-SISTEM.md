# Alat Sistem: Backup & Restore, Mode Maintenance, Log Error Hosting, Sesi Aktif

Permintaan user 2026-10-29. Keempatnya masuk grup **SISTEM** di menu samping, sesudah Audit Log, **khusus Admin**
(`MenuAdmin` entri `backup`, `mode_maintenance`, `log_error`, `sesi_aktif`). Selama belum dibangun, item bertanda "Segera".

Hosting: Rumahweb cPanel bersama (akun `kank9494`), `public_html` = symlink ke `~/kangmuslim/public`, PHP web 8.x, PHP CLI
wajib `phpm` (cron pakai path lengkap PHP 8.3). Akibatnya: jangan bergantung pada `exec`/`mysqldump` (sering diblokir hosting
bersama), batas waktu permintaan web ±30–60 detik, dan berkas penting disimpan di `writable/` (tidak bisa dibuka dari internet).

## Pembagian fase (kerja STEP BY STEP — berhenti tiap fase)

| Fase | Isi | Migrasi | Butuh |
|---|---|---|---|
| ✅ **F1** | **Log Error Hosting** — baca log galat asli server dari menu Admin | tidak | — |
| ✅ **F2** | **Mode Maintenance** — tutup situs publik, form siswa, Android sementara; Admin tetap bisa kerja | tidak | — |
| ✅ **F3** | **Backup** — cadangan database (+ berkas unggahan), unduh, simpan di server, otomatis tiap malam | tidak | — |
| ✅ **F4** | **Restore** — pulihkan dari backup | tidak | F2, F3 |
| ✅ **F5** | **Sesi Aktif** — siapa sedang masuk (web + Android), paksa keluar | 1 | — |
| ✅ **F6** | Penutup — panduan deploy (`docs/PANDUAN-DEPLOY-ALAT-SISTEM.md`), uji ulang semua | — | F1–F5 |

**SEMUA FASE SELESAI 2026-10-29 malam (lokal, belum commit — user commit/push saat semua fitur 100%).** User minta F3–F6 dikerjakan
sekaligus ("lanjut semua sampai selesai").

**Sejak 2026-10-29 (permintaan user): API Android + kontrak Flutter dikerjakan SEKALIAN di tiap fase** (bukan ditumpuk di F6).
API: `docs/API-SISTEM.md`. Kontrak Flutter: `C:\flutter-muslimin\BLUEPRINT-SISTEM.md` + `test/fixtures/sistem/`. User belum commit/push
sampai semua fitur 100%.

Alasan urutan: F1 paling aman (hanya membaca) dan langsung berguna menelusuri masalah hosting. F2 dan F3 harus ada sebelum F4,
karena restore wajib dalam mode maintenance dan wajib membuat backup otomatis dulu. F5 terakhir karena menyentuh proses login.

## F1 — Log Error Hosting (SELESAI 2026-10-29, lokal)

Sumber yang dibaca (hanya dari daftar tetap ini, bukan path kiriman pengguna):
1. Log aplikasi CodeIgniter `writable/logs/log-YYYY-MM-DD.log` (produksi mencatat CRITICAL/ERROR, `Config\Logger` threshold 4).
2. `error_log` PHP buatan cPanel di `public/` dan folder proyek, bila ada.

Halaman `admin/sistem/log-error`:
- Daftar berkas (terbaru di atas): tanggal, jumlah galat berat / galat / peringatan, ukuran, tombol Lihat & Unduh.
- Isi berkas terpilih: galat yang SAMA dikelompokkan (angka diabaikan saat membandingkan) → berapa kali, jam pertama/terakhir,
  asal (halaman web / perintah terjadwal cron), contoh jejak (dilipat), dan **penjelasan bahasa awam** untuk galat yang dikenal
  (mis. "Too many connections", "No such file or directory" = database hosting sesaat mati, bukan salah aplikasi).
- Saring tingkat + cari kata. Berkas besar dibaca 2 MB terakhirnya saja (ditandai).
- Unduh berkas asli (dicatat di Audit Log). Bersihkan log aplikasi lebih dari 30 hari (POST + konfirmasi, dicatat di Audit Log).
- `public/.htaccess` kini menolak akses langsung ke `error_log` dari internet.

Kode: `Libraries\LogGalat`, `Controllers\Admin\LogError`, `Views/admin/sistem/log_error.php`, rute `admin/sistem/log-error*`.
API (ditambah 2026-10-29 bersama F2): `GET sistem/log-error`, `POST sistem/log-error/bersihkan` (`Api\Admin\Sistem`).
Uji: `php spark dev:uji-log-error` (27/0).

## F2 — Mode Maintenance (SELESAI 2026-10-29, lokal)

Keputusan user: (1) hanya **Admin** tetap bisa bekerja, (2) **Android ikut ditutup**, (3) **semua layanan** menampilkan info maintenance.

- Status di BERKAS `writable/maintenance.json` (gitignored; bukan database → tetap bekerja walau database bermasalah): aktif, pesan,
  perkiraan selesai (informasi saja, TIDAK mati sendiri), mulai/oleh, dimatikan/oleh.
- Penyaring global `MaintenanceFilter` (paling depan): situs publik + semua form siswa + subdomain → halaman perawatan 503
  (`errors/html/maintenance.php`, kerangka `_galat.php`, tanpa database, siap CSP; permintaan JSON/fetch → JSON 503); panel admin →
  hanya Admin (`admin/login`, `admin/logout` tetap terbuka; staf lain yang sedang masuk melihat halaman perawatan + Keluar); API → 503
  `{kode:"maintenance", data.maintenance}` kecuali token Admin dan `auth/login`, `auth/biometric/login`, `auth/logout`,
  `app/bootstrap`, `app/status`, `app/apk/register`. Header `Retry-After`. Aturan = `ModeMaintenance::lolos()` (fungsi murni).
- Login web & API (termasuk sidik jari) akun selain Admin ditolak dengan pesan perawatan (`ModeMaintenance::tolakMasuk`).
- Halaman `admin/sistem/maintenance`: status, pesan (maks 300), perkiraan selesai, Aktifkan (konfirmasi merah) / Simpan / Matikan,
  pratinjau tampilan pengunjung. Spanduk merah "Mode Maintenance AKTIF" di semua halaman admin lain. Audit Log tiap perubahan.
- API: `GET app/status` (publik, tanpa database), `app/bootstrap` kini `maintenance` sungguhan + `maintenance_info`;
  `GET/POST sistem/maintenance` (Admin).
- Darurat: `phpm spark maintenance` (status) · `phpm spark maintenance off` · `phpm spark maintenance on --pesan "…" --selesai "Y-m-d H:i"`.
- Cron (perintah spark) tidak tersaring → notifikasi terjadwal tetap jalan.
- Uji: `php spark dev:uji-maintenance` (47/0, berkas status sementara — berkas asli tidak disentuh) + uji HTTP lewat Apache lokal.
- Server nanti: git pull + Ctrl+F5, tanpa migrasi.

## F3 + F4 — Backup & Restore (SELESAI 2026-10-29, lokal)

- `Libraries\BackupSistem`: dump PHP murni (mysqli, tanpa exec) per tabel → `writable/backup/db-YYYYmmdd-HHiiss-{jenis}.sql.gz`
  (DROP + CREATE + INSERT ≤200 baris/±1 MB, kolom generated dilewati, biner 0x…, time_zone +00:00, penanda awal & "-- Backup selesai");
  berkas unggahan (`public/uploads`, `writable/uploads`, `writable/pkl`, `writable/data-sekolah`) → `berkas-….zip`. Ditulis `.tmp` lalu
  diganti nama. Retensi per (isi, jenis): manual 10, otomatis 7, sebelum-pulihkan 3. Folder gitignored.
- `Libraries\PulihkanBackup`: tugas `pulihkan-{id}.json` + berkas kerja `.sql` terurai; `periksa()` (wajib tabel admins+migrations, tolak
  DELIMITER, tolak backup sistem terpotong); langkah ±8 detik (flock), langkah pertama = backup "sebelum-pulihkan" + hapus SEMUA tabel;
  pemecah SQL paham kutip/escape/komentar/komentar bersyarat; START TRANSACTION/COMMIT/AUTOCOMMIT/LOCK/USE dilewati (tiap perintah langsung
  tersimpan; COMMIT di akhir tiap langkah); berkas terpotong = gagal; sesudah selesai: cache dibersihkan + hitung migrasi belum jalan.
- Web `Admin\Backup` (`admin/sistem/backup…`): buat, unduh (ketik sandi), hapus, pulihkan dari daftar / unggah .sql/.sql.gz (syarat:
  maintenance aktif + sandi + PULIHKAN), halaman kemajuan + `assets/js/admin/pulihkan.js` (fetch POST langkah; dijeda bila maintenance
  dimatikan). Audit Log. Perintah `backup:buat [--berkas] [--manual]` (cron) & `backup:pulihkan [kunci|path] --ya`.
- API (Admin): `GET/POST sistem/backup`, `POST sistem/backup/hapus` — unduh & restore hanya web.
- Uji `dev:uji-backup` 41/0 (CHECKSUM 106 tabel hasil restore = asli; ekspor phpMyAdmin hosting dipulihkan bertahap) + uji HTTP Apache
  (login → backup → restore DB lokal dari backup sendiri → CHECKSUM sama).

## F5 — Sesi Aktif (SELESAI 2026-10-29, lokal; 1 migrasi)

- Migrasi `2026-10-29-000001_SesiAdmin`: tabel `sesi_admin` (sidik sha256 id sesi — bukan id mentah, IP, perangkat dari User-Agent,
  masuk/aktif/keluar, alasan keluar|habis|dicabut, dikeluarkan_oleh). Baris > 30 hari dibuang saat login.
- `Libraries\SesiAktif`: catat saat login (`Admin\Auth`), periksa tiap permintaan di `AuthFilter` (dicabut → keluar dengan pesan "Sesi
  Anda dihentikan oleh Admin"; aktif_at diperbarui paling sering tiap 5 menit; sesi lama tanpa catatan dicatat), logout/habis waktu
  ditandai. Android = `api_tokens` (keluarkan = hapus token → 401).
- Web `admin/sistem/sesi`: tabel web (aktif < 1 jam) & Android, "Sesi ini", Keluarkan, Keluarkan semua kecuali saya. Audit Log.
- API (Admin): `GET sistem/sesi`, `POST sistem/sesi/keluarkan {jenis,id}`, `POST sistem/sesi/keluarkan-semua`.
- Uji `dev:uji-sesi` 26/0 (dua akun uji; "keluarkan semua" di transaksi dibatalkan) + uji HTTP dua peramban (B dikeluarkan A → login).

## Rancangan awal F3–F5 (arsip — isi akhir di atas)

**F3 Backup.** Dump database dengan PHP murni (tanpa `mysqldump`), bertahap per tabel, dikompres `.sql.gz`, disimpan di
`writable/backup/`; daftar + unduh (wajib ketik sandi, isinya data pribadi) + hapus; simpan 7 terakhir. Opsi berkas unggahan
(`public/uploads`, arsip dokumen, TTD) sebagai `.zip` terpisah. Backup otomatis tiap malam lewat cron
`phpm spark backup:buat` (baris cron baru di cPanel).

**F4 Restore.** Sumber: backup di daftar, atau unggah `.sql`/`.sql.gz` (termasuk ekspor phpMyAdmin). Syarat: mode maintenance
aktif + ketik sandi + ketik `PULIHKAN`. Sistem membuat backup "sebelum restore" otomatis, lalu menjalankan isi berkas bertahap
(potongan kecil per permintaan + bilah kemajuan, agar tidak kena batas waktu hosting). Hasil dicatat di Audit Log.

**F5 Sesi Aktif.** Tabel baru jejak sesi login web (akun, IP, peramban/perangkat, waktu masuk, aktivitas terakhir), diisi saat login
dan diperbarui paling sering tiap 5 menit oleh `AuthFilter`; token Android dari `api_tokens`. Daftar "sedang aktif" (aktivitas
< 1 jam, sama dengan batas keluar otomatis); tombol Keluarkan (satu sesi / semua sesi satu akun / semua kecuali saya).
Sesi siswa Layanan Siswa tidak ikut.
