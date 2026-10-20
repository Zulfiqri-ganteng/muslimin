# Panduan deploy & cara pakai — Revisi PKL 2026-10-20

Isi revisi: **keluarkan sebagian siswa**, **ditolak perusahaan**, **Lapor Diterima sebagian / semua ditolak**, **template alasan**,
**riwayat siswa pernah ditolak**, kabar WhatsApp siswa yang dikeluarkan, plus perbaikan galat tombol "Kabari via WA" pada ajuan
ditolak/dikembalikan. Rancangan lengkap: `docs/DESAIN-PKL.md` (bagian terakhir). API: `docs/API-PKL.md`, `docs/API-SURAT.md`.

## 1. Pasang di hosting (urut)
1. **Cadangkan database** dulu (phpMyAdmin → Ekspor), sama seperti biasa.
2. `cd ~/kangmuslim && git pull`
3. `phpm spark migrate` → harus muncul `Running: … PklKeluarAlasan` lalu `Migrations complete.`
   Migrasi ini hanya MENAMBAH tabel/kolom (data lama tidak diubah), mengisi 20 template alasan (12 kalimat dari sekolah), dan memberi
   Waka Hubin hak baru "Kelola template alasan".
4. Buka web, tekan **Ctrl+F5** sekali (CSS/JS baru).

## 2. Cek cepat setelah pasang
- Masuk sebagai Waka Hubin → menu **PKL → Template Alasan** tampil, berisi 4 kelompok (Dikembalikan, Ditolak, Siswa dikeluarkan,
  Laporan dikembalikan).
- Buka satu ajuan disetujui yang berisi ≥ 2 siswa → ada tombol **Ditolak perusahaan (semua siswa)** dan **Keluarkan sebagian siswa**.
- Buka form siswa `https://pklbinanusa.kangmuslim.com/pkl/lapor` → setelah nomor HP cocok, muncul pilihan
  **Semua diterima / Sebagian diterima / Semua ditolak**.

## 3. Cara pakai (untuk disampaikan ke sekolah)
**Perusahaan hanya menerima sebagian siswa (contoh 4 mengajukan, diterima 2):**
1. Salah satu siswa membuka form **Lapor Diterima**, memilih **Sebagian diterima**, mencentang 2 siswa yang diterima, mengisi tanggal PKL.
2. Operator membuka **Surat Sekolah → Lapor Diterima PKL**: tertulis "Sebagian diterima (2 dari 4)" + nama yang ditolak.
3. Operator menekan **Setujui & buat surat** → 2 siswa yang ditolak otomatis **dikeluarkan** dari kelompok (tercatat), dan Surat Balasan
   hanya memuat 2 siswa yang diterima.
4. 2 siswa yang ditolak langsung bisa **mengajukan tempat PKL baru** lewat form PKL biasa → menunggu ACC Hubin → surat izin baru.
   Biaya PKL yang sudah dibayar tetap terhitung (tidak bayar dua kali).
5. Kabari siswa lewat tombol hijau **Kabari siswa yang dikeluarkan** di halaman ajuan.

**Perusahaan menolak semua siswa:** siswa memilih **Semua ditolak** di form Lapor → Operator menekan **Konfirmasi ditolak perusahaan**;
atau Hubin langsung menekan **Ditolak perusahaan (semua siswa)** di halaman ajuan. Semua siswa bebas mengajukan lagi.

**Satu siswa belum bayar administrasi (yang lain lanjut):** Hubin/Operator menekan **Keluarkan sebagian siswa** → pilih siswanya → jenis
**Administrasi belum lunas** → klik alasan cepat → simpan. Tidak perlu menolak seluruh kelompok lagi.

**Dua kelompok/siswa mengajukan terpisah ke tempat yang sama:** buka salah satu ajuan → **Gabungkan ke ajuan lain** → pilih ajuan
tujuan → semua siswanya pindah ke ajuan itu dan ajuan asal tertutup ("digabung"). Hanya muncul bila ada ajuan aktif lain di
perusahaan yang sama/mirip. Bila ajuan tujuan sudah punya surat, unduh ulang suratnya (nomor tetap).

**Template alasan:** di setiap jendela Tolak / Kembalikan / Batalkan persetujuan / Keluarkan / Ditolak perusahaan ada tombol kalimat siap
pakai — klik, lalu ubah bila perlu. Hubin bisa menambah/mengubah/mengurutkan kalimatnya di **PKL → Template Alasan**.

**Riwayat siswa:** di halaman ajuan, siswa yang pernah ditolak/dikeluarkan bertanda kuning **Pernah ditolak N×** + rincian singkat; klik
**Riwayat** untuk melihat semuanya. Bila siswa mengajukan lagi ke perusahaan yang dulu menolaknya, muncul peringatan kuning "Periksa".
Di **Status Siswa** ada saringan **Pernah ditolak**, dan Excel Status Siswa punya kolom **Riwayat Ditolak / Dikeluarkan**.

## 4. Catatan
- Hak baru diatur Admin di **Sistem → Hak Akses** (kunci "Kelola template alasan"). Keluarkan siswa: ajuan menunggu = hak "Periksa,
  kembalikan, ubah"; ajuan disetujui & "Ditolak perusahaan" = hak "ACC ajuan PKL".
- Siswa yang dulu dikeluarkan lewat tombol **Ubah** (sebelum revisi ini) tidak tercatat di riwayat; mulai sekarang pakai tombol
  **Keluarkan sebagian siswa** agar jejaknya tersimpan.
- Aplikasi Android lama tetap berjalan (API hanya menambah kolom). Layar baru untuk fitur ini dikerjakan di repo Flutter
  (`C:\flutter-muslimin\BLUEPRINT-PKL.md`, bagian pembaruan 2026-10-20).
