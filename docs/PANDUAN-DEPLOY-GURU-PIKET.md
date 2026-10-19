# Panduan: memasang Guru Piket (Catat Keterlambatan) + huruf Plus Jakarta Sans

Dibuat 2026-10-19. Perintah server dijalankan di **cPanel → Terminal**, folder `~/kangmuslim`. `phpm` = perintah PHP di server ini
(sama seperti `php`). Kontrak API Android: `docs/API-KETERLAMBATAN.md`.

Isi pembaruan ini (menu **KURIKULUM → Guru Piket**; susunan menu KURIKULUM sendiri sudah terpasang sejak commit `0e1c201`):
1. **Catat Keterlambatan** — guru piket mencatat siswa terlambat (cari nama/NIS, kelas otomatis, jam, sanksi & keterangan bebas),
   daftar per tanggal, ubah/hapus, satu siswa sekali per hari.
2. **Unduh Excel buku piket** PERSIS format sekolah (harian; per rentang = satu lembar per hari) + menu **Rekap Keterlambatan**
   (per siswa & per kelas, unduh Excel rekap).
3. **Tombol WhatsApp ke orang tua** ("Kabari ortu", tercatat "sudah dikabari") + **Isi no. ortu** (nomor disimpan ke Data Siswa,
   hanya kolom yang masih kosong). Templat pesan diubah Admin lewat tombol **Pesan WA ke ortu**.
4. **Peran baru "Guru Piket"** (akun bersama di meja piket): hanya Catat Keterlambatan + Rekap + lihat Jadwal Piket; boleh aplikasi.
5. **API Android** `api/v1/keterlambatan…` (layar Flutter dibangun nanti).
6. **Huruf seluruh tampilan admin & situs publik → Plus Jakarta Sans** (disimpan di server sendiri). Ukuran menu & tabel
   dirapikan. Dokumen (Excel/PDF/Word/surat) dan isi pratinjau dokumen di Arsip TIDAK berubah.

## 1. Di laptop: commit & push
Dikerjakan Claude bila diminta (`git push origin main`).

## 2. Di server: cadangkan, tarik kode, migrasi
1. Cadangkan database: cPanel → **Backup** → *Download a MySQL Database Backup*.
2. Jalankan:
```
cd ~/kangmuslim
git pull
phpm spark migrate
phpm spark migrate:status
```
3. Pastikan baris ini punya tanggal di kolom "Migrated On":
   - **`2026-10-22-000001 KeterlambatanSiswa`** — tabel `keterlambatan_siswa`, kolom `settings.wa_pesan_terlambat`, peran
     **Guru Piket**. Hanya MENAMBAH; data lama tidak diubah.
4. `public/.htaccess` TIDAK berubah di pembaruan ini → `git pull` tidak perlu stash. (Bila tetap muncul pesan konflik
   `.htaccess`, pakai cara lama: `git stash` → `git pull` → `git stash pop`.)
5. Buka situs, tekan **Ctrl+F5** (memuat CSS/JS & huruf baru).

## 3. Buat akun Guru Piket (Admin)
1. **SISTEM → Kelola Akun → Tambah akun**: nama mis. "Guru Piket", nama pengguna mis. `piket`, peran **Guru Piket**, sandi sementara.
2. Saat pertama masuk, akun itu diminta mengganti sandi, lalu langsung dibawa ke **Catat Keterlambatan**.
3. Hak bisa diubah di **SISTEM → Hak Akses** (peran Guru Piket: Kelola Catat Keterlambatan, Lihat Rekap, Lihat Jadwal Piket).
   Peran ini bukan peran sistem — boleh diganti nama atau dihapus Admin.

## 4. Cek cepat setelah deploy (±10 menit)
1. Login **Admin**: tulisan menu & halaman memakai huruf baru (Plus Jakarta Sans). Menu **KURIKULUM → Guru Piket** berisi
   Jadwal Piket, **Catat Keterlambatan**, **Rekap Keterlambatan** (keduanya TIDAK lagi bertanda "Segera").
2. **Catat Keterlambatan**: pilih guru piket → ketik 2–3 huruf nama siswa → pilih → Simpan. Baris muncul di daftar.
   Catat siswa yang sama sekali lagi → ditolak "sudah tercatat".
3. **Unduh Excel** → buka berkas: judul "BUKU PIKET KETERLAMBATAN SISWA / SMK BINA NUSA KABUPATEN BEKASI / TAHUN PELAJARAN …",
   HARI/TANGGAL & GURU PIKET terisi, 50 baris. (Baris 2 diambil dari Pengaturan Sekolah: nama sekolah tanpa "(…)" + "KABUPATEN" + kota.)
4. Baris siswa yang punya nomor HP orang tua: tombol hijau **Kabari ortu** → WhatsApp terbuka dengan pesan → kembali ke halaman,
   baris bertanda "Sudah dikabari". Siswa tanpa nomor: **Isi no. ortu** → isi nomor 08… → tersimpan, tombol hijau muncul.
5. Hapus catatan uji tadi (tombol Hapus).
6. **Rekap Keterlambatan**: rentang bulan ini tampil; **Unduh buku piket** & **Unduh rekap** menghasilkan Excel.
7. Login akun **Guru Piket**: menu hanya Guru Piket (+ Profil); Dashboard/Data Siswa/PKL tidak bisa dibuka.
8. Situs publik (beranda) memakai huruf baru; tidak ada yang berantakan.

## 5. Bila ada masalah
- Halaman Catat Keterlambatan galat 500 / "Table … keterlambatan_siswa doesn't exist" → migrasi belum jalan: ulangi
  `phpm spark migrate`.
- Huruf masih lama → Ctrl+F5 (cache peramban); periksa berkas `public/assets/fonts/plus-jakarta-sans/` ikut tertarik.
- Membatalkan migrasi (menghapus tabel keterlambatan, kolom templat, dan peran Guru Piket bila tak dipakai akun):
  `phpm spark migrate:rollback` — HANYA bila benar-benar perlu (catatan keterlambatan ikut hilang).

## Catatan
- Banyak siswa belum punya nomor HP orang tua di Data Siswa (± 4% saat fitur dibuat) — tombol **Isi no. ortu** membantu melengkapi.
- Contoh format Excel sekolah ada di laptop (`formatdatasekolah/KETERLAMBATAN SISWA DI GURU PIKET.xlsx`, tidak ikut Git); server
  tidak membutuhkannya (format ditulis di kode, `Libraries\KeterlambatanExcel`).
- Uji di laptop sebelum push: `php spark dev:uji-telat` (119 cek sejak K1), uji hak, cek UI, uji peramban semua halaman (lihat laporan G6).

## Pembaruan K1 (2026-10-19 malam) — siswa BELUM TERDAFTAR dicatat manual + Tautkan
Keluhan klien: guru piket tidak bisa memilih anak yang belum ada di Data Siswa. Sekarang di halaman Catat ada tombol
**"Siswa belum terdaftar? Tulis manual"** (nama, kelas, HP orang tua opsional), lencana **Belum terdaftar** di daftar & rekap, dan
tombol **Tautkan** untuk menyatukan catatan itu dengan Data Siswa setelah anaknya dimasukkan. API Android ikut (docs/API-KETERLAMBATAN.md).

Di server (sesudah commit & push dari laptop):
```
cd ~/kangmuslim
git pull
phpm spark migrate          # menjalankan 2026-10-23-000001_KeterlambatanManual (kolom manual & hp_ortu, catatan lama tidak berubah)
phpm spark migrate:status   # pastikan KeterlambatanManual sudah "Migrated"
```
Lalu Ctrl+F5 di peramban (JS & CSS halaman Catat berubah). Tanpa migrasi, pencarian siswa & simpan manual di halaman Catat gagal
(kolom belum ada).
