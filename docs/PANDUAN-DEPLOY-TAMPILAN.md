# Panduan: memasang rombak tampilan admin (cabang `upgrade-tampilan`) di server

Isi paket: seluruh panel admin + halaman masuk bergaya **Flowbite Admin Dashboard** (F0–F12), tiga mode tampilan
(**Terang** / **Gelap** / **Warna Sekolah** — serba ungu lilac: tombol, tabel, lencana) di *Pengaturan Sekolah → Tampilan*, ikon & tombol berwarna, dasbor bergrafik,
halaman galat 404 baru, semua skrip & gaya di berkas terpisah (siap CSP). **Halaman publik belum dirombak** (fase terakhir).
Rancangan & riwayat: `docs/RENCANA-UPGRADE-TAMPILAN.md`.

**Satu migrasi database** (`2026-10-19-000001_TemaTampilan`: kolom `settings.tema_warna` & `settings.tema_sidebar`, ber-nilai
bawaan — data lama tidak berubah). **Tanpa npm/composer di server**: CSS & JS hasil build ikut di-commit.
Perintah server di **cPanel → Terminal**, folder `~/kangmuslim`.

> Kapan dipasang: sesuai keputusan 2026-10-13, **setelah semua fase selesai** dan diuji di laptop. Selama belum dipasang,
> perbaikan mendesak klien tetap dikerjakan di cabang `main` seperti biasa (cabang ini menggabungkannya belakangan).

## 1. Gabung ke `main` lalu push (di laptop)
```
git switch upgrade-tampilan
git merge main                 # ambil perbaikan main terbaru (bila ada); selesaikan bentrok bila muncul
npm run build:admin            # pastikan admin.css terbaru
php spark dev:cek-ui           # harus: semua kolom 0 · "admin.css: sudah sesuai sumber terbaru"
git switch main
git merge upgrade-tampilan
git push origin main           # HANYA main — jangan --all
```

## 2. Di server
```
cd ~/kangmuslim
git pull
phpm spark migrate
```
- `migrate` hanya menambah 2 kolom tema. **Jangan** `migrate:rollback`.
- `public/.htaccess` ikut berubah: cache CSS/JS ber-versi 1 tahun + kompresi. Semua aturan di dalam `<IfModule>` → aman
  walau modul server tidak ada.
- **CSP tetap mati** (`.env` server jangan diberi `app.CSPEnabled`) sampai seri keamanan — lihat bagian 6.

## 3. Wajib setelah deploy
1. **Ctrl+F5 di setiap tab admin** yang sudah terbuka sebelum deploy (skrip lama di tab itu tidak cocok dengan halaman baru).
2. Masuk sebagai **Admin → Pengaturan Sekolah → Tampilan** → pilih mode (bawaan *Terang*; *Warna Sekolah* = ungu lilac
   seperti sebelumnya). Pilihan berlaku untuk semua akun.

## 4. Cek cepat (±15 menit)
1. **Halaman masuk** → masuk Admin: menu samping, bilah atas, kaki halaman tampil rapi; tombol ciutkan menu & cari menu jalan.
2. **Dasbor**: kartu angka & grafik kehadiran tampil; ganti 7/30/90 hari.
3. **Pengaturan → Tampilan**: klik Gelap → seluruh halaman gelap; klik Warna Sekolah → serba ungu lilac (tombol Hapus merah
   keunguan, tabel lavender); kembalikan ke pilihan sekolah.
4. **Master Siswa**: cari, saring kelas, Tambah → Simpan → Ubah → Hapus (muncul dialog konfirmasi merah) satu data uji.
5. **Absensi Guru** dan **Jadwal KBM**: simpan sekali (tanpa pindah halaman) → berhasil.
6. **Ujian → Honor / SKBM**: buka grid, ubah satu sel, tersimpan.
7. **PKL → Daftar** dan **Surat Sekolah → Daftar Surat**: buka detail, unduh satu surat.
8. **Arsip Dokumen**: unggah PDF kecil → pratinjau → Bagikan → buka tautan di jendela penyamaran → buang ke sampah → hapus.
9. **HP**: buka panel admin di HP → menu samping lewat tombol ☰, tabel bisa digeser ke samping, tombol tidak terpotong.
10. Alamat ngawur (mis. `/admin/asal`) → halaman 404 baru berbahasa Indonesia dengan tombol "Ke Dasbor".
11. Masuk sebagai **Operator** dan **Waka Hubin** (bila ada akunnya): menu sesuai hak, tidak ada halaman kosong/galat.

## 5. Bila ada masalah
- Tampilan berantakan / warna lama: CSS lama tersimpan di peramban → **Ctrl+F5**.
- Tombol tidak bereaksi sama sekali: buka tab baru (Ctrl+F5). Bila tetap, kirim nama halamannya + foto layar.
- Galat 500 / halaman putih: kirim baris terbawah `~/kangmuslim/writable/logs/log-<tanggal>.log`.
- Darurat (tampilan baru harus dibatalkan): `git log --oneline -5` → `git reset --hard <commit sebelum merge>` di server
  **hanya bila diminta pengembang**; kolom tema dari migrasi dibiarkan (tidak mengganggu versi lama).

## 6. Catatan serah-terima untuk seri keamanan (CSP)
Panel admin sudah **siap CSP penuh**: tanpa skrip/gaya sebaris, tanpa `on*=`, tanpa CDN, Alpine versi CSP (`@alpinejs/csp`,
tanpa `eval`), skrip ber-nonce. Uji peramban 113 halaman (Admin 73, Operator 25, Waka Hubin 14) = **0 pelanggaran CSP**.
Alat: `php spark dev:cek-ui` (statis) dan `node tools/uji-peramban.mjs --wajib-csp-nol` (Chrome tanpa layar).
Yang masih harus diselesaikan sebelum `app.CSPEnabled = true`:
- **Halaman publik** (beranda, form biodata/PKL siswa, berbagi dokumen) masih Alpine biasa dan sebagian memuat dari CDN →
  baru siap setelah fase halaman publik. Pilihan sementara: CSP hanya untuk `admin/*`.
- `app/Config/ContentSecurityPolicy.php` sudah disiapkan (gambar `data:`/`blob:`/sampul YouTube, bingkai pratinjau dokumen).
- `App\Libraries\DokumenStream` memasang header `Content-Security-Policy … sandbox` sendiri untuk berkas dokumen — pastikan
  CSP CodeIgniter tidak menimpanya.
- ApexCharts (dasbor): gayanya dari `assets/css/vendor/apexcharts.css`, tidak menyisipkan `<style>`.
- Halaman galat (`errors/html/*`) memakai `assets/css/galat.css` mandiri, tanpa skrip.
