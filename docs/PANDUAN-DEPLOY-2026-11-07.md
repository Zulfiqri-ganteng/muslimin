# Panduan deploy 2026-11-07 — PKL otomatis, Log Error, halaman Aplikasi, Wali Kelas

Satu kali deploy untuk semua perubahan F1–F4. **Ada 1 migrasi** (wali kelas). Backup database dulu (Sistem → Backup & Restore).

## Langkah di hosting
1. Backup database (menu Sistem → Backup & Restore → Buat backup).
2. Terminal cPanel:
   ```
   cd ~/kangmuslim
   cp public/.htaccess ~/htaccess-cadangan   # blok PHP cPanel (lihat jebakan deploy 2026-10-29)
   git stash && git pull && git stash pop
   phpm spark migrate                          # 2026-11-07-000001_WaliKelas
   ```
3. Buka situs, tekan **Ctrl+F5** (CSS admin & publik dibangun ulang).

## Cek cepat sesudah deploy
- `kangmuslim.com/aplikasi` → tampil versi 1.1.5 (86 MB) + tombol Unduh; kotak "Aplikasi Android" muncul di beranda.
- Admin → **Sistem → Log Error Hosting** → tombol "Hapus semua log" ada.
- Admin → **Kelola Akun** → kartu "Akun wali kelas" (0/42 punya akun) → **Buat akun wali kelas** → tabel kuning berisi username &
  sandi sementara (tampil SEKALI) → tekan **Kirim WA** per baris. Perkiraan: 39 akun baru + 3 akun lama dihubungkan (Puguh, Abil, Debi).
- Masuk sebagai salah satu wali → menu **Kelas Saya** muncul (tab Siswa / PKL / Keterlambatan / Surat).
- Form PKL siswa: lencana kuning "Lewat batas — boleh ajukan ulang" hanya muncul untuk ajuan menunggu > 5 hari.

## Yang berubah (ringkas)
| Fase | Isi | Dokumen |
|---|---|---|
| F1 | Lapor "ditolak perusahaan" langsung membebaskan siswa; ajuan menunggu lewat batas → boleh ajukan ulang (ajuan lama ditutup otomatis) | API-LAYANAN-SISWA.md (LY4/LY5), API-PKL.md |
| F2 | Log Error: hapus per berkas / hapus semua, bersih otomatis > 30 hari; notif tengah malam tidak lagi menulis log untuk gangguan sesaat | API-SISTEM.md |
| F3 | Halaman publik `/aplikasi` + API `publik/aplikasi` | API-LAYANAN-SISWA.md (LY8) |
| F4 | Wali kelas: akun & akses, Kelas Saya, API `wali/kelas` | API-WALI-KELAS.md |

## Kontrak Android (repo `C:\flutter-muslimin`)
`PROGRES-KERJA.md`: LY7 (aturan PKL), L2 (hapus log), LY8 (bagikan aplikasi), WK1–WK2 (wali kelas) + `BLUEPRINT-WALI-KELAS.md`;
fixture baru `test/fixtures/{pkl_aturan_lewat_batas, lapor_lepas_otomatis, aplikasi_publik, wali_kelas}/`.

## Uji (laptop)
`dev:uji-pkl` 339 · `dev:uji-pkl-publik` 36 · `dev:uji-lapor` 127 · `dev:uji-pkl-keluar` 134 · `dev:uji-log-error` 33 · `dev:uji-maintenance` 48 ·
`dev:uji-aplikasi-publik` 14 · `dev:uji-wali-kelas` 29 · `dev:uji-hak` 69 · `dev:uji-api-android` 19 · `dev:cek-ui` 0 temuan.
