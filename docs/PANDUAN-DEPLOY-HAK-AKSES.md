# Panduan: memasang Hak Akses diatur Admin + Lapor Diterima PKL + TTD/Stempel Kepsek

Dibuat 2026-10-13. Rancangan & hasil lengkap: `docs/DESAIN-HAK-AKSES.md`. Perintah server dijalankan di **cPanel → Terminal**,
folder `~/kangmuslim`. `phpm` = perintah PHP di server ini (sama seperti `php`).

Isi pembaruan ini:
1. **Sistem → Hak Akses** (khusus Admin): siapa boleh membuka menu apa (Tidak / Lihat saja / Kelola), hak PKL & Surat per jenis
   surat, dan peran baru (mis. Waka Kesiswaan) — tanpa koding lagi.
2. **Lapor Diterima PKL**: siswa melapor "sudah diterima perusahaan" lewat `https://pklbinanusa.kangmuslim.com/pkl/lapor`; staf
   membuat Surat Balasan dari laporan itu (menu SURAT SEKOLAH → Lapor Diterima PKL).
3. **Tanda tangan + stempel Kepala Sekolah** diunggah sekali → otomatis tercetak di semua surat bernomor.

## 1. Push (di laptop)
```
git add .
git commit -m "Hak akses diatur Admin, lapor diterima PKL, TTD + stempel Kepsek"
git push
```
Gambar tanda tangan/stempel TIDAK ikut ter-push (folder `writable/pkl/` diabaikan Git) — memang harus diunggah lewat web di server.

## 2. Di server: cadangkan, tarik kode, migrasi
1. Cadangkan database dulu: cPanel → **Backup** → *Download a MySQL Database Backup*.
2. Jalankan:
```
cd ~/kangmuslim
git pull
phpm spark migrate
phpm spark migrate:status
```
3. Pastikan dua baris ini punya tanggal di kolom "Migrated On":
   - **`2026-10-17-000001 HakAksesPeran`** — tabel `peran` (diisi otomatis dari aturan lama, jadi hak semua akun TETAP sama).
   - **`2026-10-17-000002 PklLapor`** — tabel `pkl_lapor` + saklar form lapor + kolom TTD Kepsek.
4. Buka situs admin, tekan **Ctrl+F5** (memuat ulang CSS/JS terbaru).

## 3. Cek cepat setelah deploy (±10 menit)
1. Login **Admin** → **SISTEM → Hak Akses** terbuka. Tab *Menu*: Operator & Waka Hubin terisi sama seperti sebelumnya.
2. Login **Operator** dan **Waka Hubin** (atau minta mereka login): menu samping sama seperti kemarin; ada menu baru
   **SURAT SEKOLAH → Lapor Diterima PKL**.
3. **PKL → Pengaturan PKL** → bagian *Form lapor diterima*: pilihan "Buka form lapor" terpilih; salin tautannya.
4. Buka tautan `https://pklbinanusa.kangmuslim.com/pkl/lapor` di HP (mode penyamaran): halaman tampil, kelas bisa dipilih.
5. **PKL → Tanda Tangan**: ada dua bagian (Waka Hubin, Kepala Sekolah). Admin unggah gambar **tanda tangan + stempel Kepala Sekolah**
   (satu gambar PNG/JPG, latar putih). Unduh satu Surat Balasan yang SUDAH bernomor (unduh ulang tidak membuat nomor baru) → buka
   di Word → stempel tercetak di tempat tanda tangan Kepala Sekolah.

## 4. Atur sekali (sesuai permintaan sekolah)
**Surat Balasan di-ACC Operator saja:** SISTEM → Hak Akses → tab **PKL & Surat** → tabel *Surat Sekolah per jenis* → baris
**Surat Balasan PKL**: centang **ACC** di kolom Operator Sekolah, hapus centang ACC di kolom Waka Hubin → **Simpan**. Berlaku langsung.
Kalau kelak Kepala Sekolah ingin Waka Hubin lagi yang ACC, cukup balik centangnya — tidak perlu koding.

## 5. Bila ada masalah
- Halaman galat / putih: kirim tangkapan layar + isi berkas log terbaru `~/kangmuslim/writable/logs/log-YYYY-MM-DD.log` ke pengembang.
- Ada akun yang tiba-tiba tidak bisa membuka menu: SISTEM → Hak Akses → tab Menu → tombol **Kembalikan ke bawaan sekolah**.
- JANGAN `migrate:rollback` tanpa bertanya: pengaturan Hak Akses dan laporan siswa ikut terhapus.
