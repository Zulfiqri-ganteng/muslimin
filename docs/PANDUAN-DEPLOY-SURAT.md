# Panduan: memasang Surat Sekolah di server (5 menu)

Dibuat 2026-10-12 (Surat Sekolah L7). Berlaku untuk kelima menu SURAT SEKOLAH: **Izin ASTS, Izin TKA, Pernyataan Orang Tua PKL,
Surat Balasan PKL, Penarikan Izin PKL** + Daftar Surat. Perintah server dijalankan di **cPanel → Terminal**, folder `~/kangmuslim`.
`phpm` = perintah PHP di server ini (sama seperti `php`). Rancangan lengkap: `docs/DESAIN-SURAT-SEKOLAH.md`.

## 1. Push (di laptop)
```
git add .
git commit -m "Surat Sekolah L7: Excel agenda, kartu Beranda PKL"
git push
```

## 2. Di server: tarik kode, cek migrasi
```
cd ~/kangmuslim
git pull
phpm spark migrate:status
```
- Cari baris **`2026-10-14-000001 CreateSuratSekolah`**. Bila kolom tanggal "Migrated On" masih kosong, jalankan `phpm spark migrate`
  (disarankan cadangkan database dulu: cPanel → **Backup** → *Download a MySQL Database Backup*).
- L4–L7 **tidak** menambah migrasi. Cukup `git pull`.
- Buka situs admin, tekan **Ctrl+F5** (memuat ulang CSS/JS terbaru).

## 3. Cek cepat setelah deploy (±5 menit)
1. Login **Admin** → menu **SURAT SEKOLAH → Daftar Surat** terbuka tanpa galat; kelima menu tidak lagi bertanda "Segera".
2. Tombol **⬇ Excel** di Daftar Surat mengunduh *Buku Agenda Surat Keluar* (kop sekolah + daftar surat sesuai tab & saringan).
3. **PKL → Beranda**: ada 3 kartu *Surat sekolah menunggu ACC / belum diunduh / dikembalikan* (untuk Waka Hubin, Operator, Admin).
4. Buat satu **Pernyataan Orang Tua PKL** untuk satu siswa → Unduh → buka di Word: 1 halaman per siswa, kertas F4 (21,5 × 33 cm).
   Setelah dicek, surat uji boleh **Dibatalkan** dari halaman detailnya (Pernyataan tidak bernomor, jadi tidak ada nomor terbuang).
5. Surat bernomor (ASTS, TKA, Balasan, Penarikan): nomor baru terbit saat **unduh pertama**, satu urutan dengan Surat Izin PKL.
   Jangan membuat surat bernomor hanya untuk uji, karena nomor yang sudah terbit tidak dipakai ulang.

## 4. Hak akses (atur sekali)
> **Diperbarui 2026-10-13:** hak kini diatur di **SISTEM → Hak Akses** per jenis surat, dan pernyataan "mewakili Waka Hubin"
> sudah dihapus — lihat `docs/PANDUAN-DEPLOY-HAK-AKSES.md`. Isi di bawah ini hanya catatan lama.

**PKL → Hak Akses** (lama):
- Hak **Surat sekolah** (membuat, mengubah, mengunduh) bawaannya dipegang **Operator**.
- **ACC** bawaannya dipegang **Waka Hubin**. Admin bisa meng-ACC sebagai cadangan dengan mencentang "mewakili Waka Hubin".
- Bagian *"Surat Sekolah: jenis yang wajib ACC"*: Admin bisa mematikan ACC per jenis (Pernyataan memang tanpa ACC).

## 5. Bila ada masalah
- **"Halaman ini sudah kedaluwarsa…"**: halaman terlalu lama terbuka (lebih dari 8 jam) atau dibuka di dua tab. Muat ulang lalu ulangi.
- **Daftar Surat galat / tabel tidak ada**: migrasi belum jalan (langkah 2).
- **Nama Waka Hubin kosong di surat (hanya titik-titik)**: isi di **PKL → Pengaturan**. Halaman detail surat juga menampilkan peringatannya.
- Galat lain: kirim isi `~/kangmuslim/writable/logs/log-<tanggal hari ini>.log` (baris paling bawah) ke AI/pengembang.
