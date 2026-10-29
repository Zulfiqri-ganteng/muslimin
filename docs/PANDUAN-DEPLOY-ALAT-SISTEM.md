# Panduan deploy — Alat Sistem (Backup & Restore, Mode Maintenance, Log Error Hosting, Sesi Aktif)

Untuk server kangmuslim.com (Rumahweb cPanel, akun `kank9494`). Rancangan & isi fitur: `docs/DESAIN-ALAT-SISTEM.md`.
Ada **1 migrasi baru** (`2026-10-29-000001_SesiAdmin` — tabel `sesi_admin`) dan **1 baris cron baru** (backup harian).

## 1. Sebelum menarik kode

Backup database hosting dulu: phpMyAdmin → `kank9494_muslimin` → tab **Ekspor** → **Kirim**. Simpan berkasnya.

## 2. Tarik kode + migrasi (Terminal cPanel)

```
cd ~/kangmuslim && git pull origin main && phpm spark migrate
```

Harus muncul `Running: … SesiAdmin` lalu `Migrations complete.` (Jangan pakai `php spark` polos — PHP di Terminal = 7.4, mati diam.)

## 3. Uji backup sekali dari Terminal

```
phpm spark backup:buat --manual
```

Harus muncul `Backup database: db-…-manual.sql.gz (… KB, 106 tabel)`. Folder `writable/backup/` dibuat otomatis (tidak bisa dibuka
dari internet, dan tidak ikut git).

## 4. Pasang cron backup harian (cPanel → Cron Jobs)

- Waktu: **Sekali sehari** — menit `0`, jam `1` (01.00 malam).
- Perintah (salin path PHP yang SAMA dengan cron notifikasi yang sudah ada; alias `phpm` tidak berlaku di cron):

```
/opt/cpanel/ea-php83/root/usr/bin/php /home/kank9494/kangmuslim/spark backup:buat >/dev/null 2>&1
```

Disimpan 7 backup otomatis terakhir (yang lebih lama terhapus sendiri). Tambahkan `--berkas` di perintah bila foto & dokumen juga
ingin dibackup tiap malam.

## 5. Peramban

Buka panel admin lalu tekan **Ctrl+F5** (CSS & skrip baru).

## 6. Cek cepat (akun Admin)

1. Menu **SISTEM** berisi 4 menu tanpa tanda "Segera": Backup & Restore, Mode Maintenance, Log Error Hosting, Sesi Aktif.
2. **Log Error Hosting** terbuka dan menampilkan log hari-hari terakhir.
3. **Sesi Aktif** menampilkan sesi Anda dengan tanda "Sesi ini".
4. **Backup & Restore** menampilkan backup dari langkah 3; tombol "Backup database" membuat backup baru.
5. **Mode Maintenance**: Aktifkan → buka kangmuslim.com di jendela penyamaran → tampil "Sedang dalam perawatan" → kembali ke admin
   (spanduk merah tampil) → Matikan → situs normal lagi.

6. **Master Siswa** (perubahan 2026-10-29, tanpa migrasi — `docs/API-SISWA-STATUS.md`): tiap baris kini Detail · Ubah ·
   **Nonaktifkan** (Lulus/Pindah/Keluar) / **Aktifkan**. **Hapus permanen** ada di halaman Detail (hanya untuk siswa tanpa data
   terkait). Saringan Status → **Terhapus (lama)** menampilkan data yang dulu dihapus dengan cara lama (±70 di hosting, termasuk data
   asal-asalan 13 Okt) → Pulihkan atau Hapus permanen. `siswa:hapus-keluar --tulis` kini menandai status Keluar, bukan menghapus.

## Jalan darurat (Terminal cPanel)

| Keperluan | Perintah |
|---|---|
| Lihat status maintenance | `phpm spark maintenance` |
| Matikan maintenance (tidak bisa masuk web) | `phpm spark maintenance off` |
| Nyalakan maintenance | `phpm spark maintenance on --pesan "…" --selesai "2026-10-30 22:00"` |
| Daftar backup database | `phpm spark backup:pulihkan` |
| Restore tanpa web (berkas besar / web rusak) | `phpm spark backup:pulihkan db-…sql.gz --ya` (maintenance otomatis menyala; matikan sesudah memeriksa data) |

## Catatan

- **Restore** di web hanya bisa saat Mode Maintenance aktif + ketik sandi + ketik `PULIHKAN`. Sebelum mulai, sistem otomatis membuat
  backup "sebelum restore". Bila restore dari backup LAMA, halaman hasil memberi tahu bila perlu `phpm spark migrate`.
- Restore hanya mengganti **database**. Berkas unggahan (.zip) dipulihkan manual lewat cPanel → File Manager → Extract.
- `public/.htaccess` kini menolak akses langsung ke berkas `error_log` dari internet (isinya dibaca lewat menu Log Error Hosting).
- Aplikasi Android lama menampilkan pesan maintenance sebagai galat biasa sampai layar M0/M1 (BLUEPRINT-SISTEM.md) dirilis.
