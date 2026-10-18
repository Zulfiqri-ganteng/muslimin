# Panduan: memasang rombak tampilan halaman PUBLIK di server

Isi paket (P0–P7, `docs/RENCANA-TAMPILAN-PUBLIK.md`): seluruh halaman publik bergaya portal **rumah.pendidikan.go.id** dengan
identitas sekolah (warna lilac) — beranda baru, halaman Siswa / Guru / Orang Tua / Profil, pengumuman, pencarian, bantuan, jadwal,
kehadiran guru, dokumen & tautan berbagi, formulir **Pengajuan PKL, Lapor diterima, Biodata, Kesediaan guru**, halaman 404.
Menu admin baru **Arsip & Info → Foto Sekolah**, pengumuman berkategori + gambar, kartu **Situs publik** di Pengaturan Sekolah.
Semua skrip & gaya di berkas (siap CSP), Alpine & huruf **lokal** (tanpa CDN/Google Fonts).

**Satu migrasi database** (`2026-10-20-000001_TampilanPublik`): tabel baru `foto_sekolah`, kolom `pengumuman.kategori` &
`pengumuman.gambar`, kolom `settings.profil_singkat`, `settings.wa_bantuan`, `settings.tautan_aplikasi`. Data lama tidak berubah.
**Tanpa npm/composer di server**: CSS & JS hasil build ikut di-commit. `public/.htaccess` **tidak** berubah di seri ini.

## 1. Di server (cPanel → Terminal)
```
cd ~/kangmuslim
git pull origin main
phpm spark migrate
```
- **`migrate` WAJIB** dan harus langsung setelah `git pull`. Tanpa migrasi: menu Foto Sekolah, simpan Pengumuman, dan simpan
  Pengaturan Sekolah akan galat. (Beranda tetap tampil walau migrasi belum dijalankan.)
- **Jangan** `migrate:rollback`.
- Bila `git pull` menolak karena ada perubahan lokal di server: jalankan `git stash` → `git pull origin main` → `git stash pop`
  (pola yang sama dengan deploy sebelumnya).

## 2. Wajib setelah deploy
1. **Ctrl+F5** di setiap tab situs/admin yang sudah terbuka sebelumnya.
2. Admin → **Pengaturan Sekolah → kartu Situs publik**: isi Profil singkat, Nomor WhatsApp bantuan, Tautan aplikasi Android
   (yang dikosongkan tidak tampil).
3. Admin → **Arsip & Info → Foto Sekolah**: unggah 2–3 foto asli sekolah (mendatar, ±1600 px). Foto yang dicentang
   "Tampilkan di banner" muncul di banner beranda. Tanpa foto, beranda tetap rapi (banner berisi teks).
4. Bila Operator/staf lain perlu mengelola foto: **Sistem → Hak Akses → Foto Sekolah** (bawaan: hanya Admin).

## 3. Cek cepat (±15 menit, sebaiknya dari HP)
1. **Beranda**: banner geser (tombol kiri/kanan, titik, jeda), ubin Siswa/Guru/Orang Tua/Profil, Tentang sekolah + grafik,
   Informasi untuk Anda, tanya-jawab. Kotak cari di atas → ketik "jadwal".
2. Tombol **kisi (Daftar layanan)** di kanan atas / tombol menu di HP → laci samping terbuka, Esc menutup.
3. **Jadwal kelas**: pilih kelas → langsung tampil; geser tabel di HP; Unduh PDF.
4. **Pengajuan PKL** (`/pkl` atau subdomain PKL): pilih kelas → nama → isi sampai halaman Periksa (tak perlu kirim bila tidak ada
   ajuan sungguhan).
5. **Lapor diterima** dan **Biodata**: buka, pilih kelas → nama tampil.
6. **Form kesediaan guru** (`/isi`): langkah 1–6 bisa dilewati (bila form sedang dibuka).
7. Admin → **Pengumuman**: tambah satu dengan kategori Berita + gambar → tampil di beranda → hapus.
8. Alamat ngawur (mis. `/asal-saja`) → halaman 404 berbahasa Indonesia.

## 4. Bila ada masalah
- Tampilan lama / berantakan: **Ctrl+F5** (CSS lama tersimpan di peramban).
- Galat 500 saat simpan Pengumuman / Pengaturan / Foto Sekolah: migrasi belum jalan → `phpm spark migrate`.
- Foto gagal diunggah: pesan galat menyebut alasannya (format/ukuran). Batas 5 MB; bila "berkas terlalu besar" padahal kecil,
  naikkan `upload_max_filesize` di cPanel.
- Galat lain: kirim baris terbawah `~/kangmuslim/writable/logs/log-<tanggal>.log`.

## 5. Catatan teknis
- Berkas lama yang DIHAPUS: `public/assets/css/app.css`, `resources/css/app.css`, `tailwind.config.js` (Tailwind v3),
  `layouts/public.php`, `layouts/pkl.php`, `layouts/biodata.php`, `partials/kop_form.php`. Ubah tampilan publik sekarang lewat
  `resources/css/publik.css` → `npm run build:publik` (laptop) → commit `public/assets/css/publik.css`.
- `tailwindcss.exe` di folder laptop (tidak masuk git) tidak dipakai lagi — boleh dihapus.
- Pemeriksa: `php spark dev:cek-ui` (semua grup harus 0). Panduan gaya untuk aplikasi Android: `docs/PANDUAN-GAYA-PUBLIK.md`.
