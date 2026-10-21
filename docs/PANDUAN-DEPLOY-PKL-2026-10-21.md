# Panduan deploy — perbaikan PKL & Surat 2026-10-21

## Isi pembaruan
1. **Surat Penarikan & Surat Balasan (Word):** tulisan "Surat ini disetujui secara elektronik…" kini di **paling bawah**, di bawah
   "NB : Jika ada hal penting harap menghubungi : 1. …". Diukur di Microsoft Word: tetap 1 halaman sampai 5 siswa (dulu Balasan
   5 siswa sudah 2 halaman — kini ikut beres). Supaya muat, baris kosong di atas "NB :" dan sebelum "1. …" dirapatkan.
2. **Operator bisa mengatur siswa di Surat Balasan:** di formulir Buat/Ubah Surat Balasan ada centang **"Siswa yang diterima
   perusahaan"**. Lepas centang siswa yang tidak diterima → isi alasan (sudah terisi bawaan "Ditolak perusahaan") → Simpan. Siswa itu
   otomatis **dikeluarkan dari ajuan sebagai "Ditolak perusahaan"** (bebas mengajukan tempat lain, tercatat di riwayatnya) dan Surat
   Balasan hanya memuat siswa yang diterima. Surat tetap di-ACC Waka Hubin seperti biasa.
3. **Audit Log:** ganti tema tampilan (Terang/Gelap/Warna Sekolah) tidak dicatat lagi.

4. **Excel Status PKL Siswa** (PKL → Status Siswa → Unduh Excel) dirapikan: kolom "No" tidak lagi selebar judul, garis tabel,
   judul & saringan yang dipakai tertulis di atas, ringkasan jadi tabel kecil, filter di judul kolom, judul kolom diulang tiap halaman
   cetak (Legal mendatar), status berwarna lembut. Kolom **Riwayat Ditolak / Dikeluarkan**: satu kejadian per baris + **alasannya**,
   mis. "15-10-2026 · PKL-00031 Hari Hari Pasar Swalayan … — ditolak sekolah: 2 Siswa belom melakukan administrasi".

## Perbaikan data riwayat lama (SQL, sekali jalan — sudah diuji pada salinan database hosting)
- PKL-00018 (Annisa Ramadhani, alasan "Ditolak oleh perusahaan") → ditandai **ditolak perusahaan**.
- PKL-00022 (Rizal Irawan, alasan "Penggabungan"; Rizal sudah di PKL-00021) → ditandai **digabung ke PKL-00021**, Rizal dicatat
  "pindah kelompok" → tidak lagi terhitung "pernah ditolak" (persis hasil tombol "Gabungkan").
- PKL-00010 (PT Kito, alasan "Pengajuan surat baru") **dibiarkan** (tidak pasti ditolak atau ganti tempat).
Query lengkap diberikan di percakapan (berkas `perbaikan_riwayat.sql`); aman dijalankan dua kali. Cadangkan DB dulu.

## Langkah di hosting
1. `cd ~/kangmuslim && git pull`
2. Di peramban: **Ctrl+F5**.

Tanpa migrasi database.

## Opsional: bersihkan catatan tema lama di Audit Log
phpMyAdmin → database → tab SQL:

```sql
DELETE FROM audit_log WHERE tabel = 'settings' AND deskripsi LIKE 'Tema tampilan panel admin diganti:%';
```

## Uji di laptop
`php spark dev:uji-surat` (324 cek) · `dev:uji-pkl` · `dev:uji-lapor` · `dev:uji-pkl-keluar` · `dev:uji-pkl-biaya` · `dev:uji-hak`.
