# Panduan deploy — Supervisi Guru (S1–S4)

Ditulis 2026-10-21. Rancangan: `docs/DESAIN-SUPERVISI.md` · API Android: `docs/API-SUPERVISI.md`.

## Isi pembaruan
- **S1–S2** (commit `3feaad2`, sudah di-push): Instrumen, Jadwal (+ buat massal), lembar Penilaian. Butuh migrasi
  `2026-10-24-000001_SupervisiGuru`.
- **S3** (baru): halaman **Rekap Supervisi** (menu tidak "Segera" lagi), unduh **Program Supervisi** (Excel, di halaman Jadwal),
  **Lembar Hasil** (PDF per kunjungan di Penilaian & lembar, PDF gabungan di Penilaian & Rekap), **Rekap** (Excel).
- **S4** (baru): API Android `api/v1/supervisi…` (layar Android dikerjakan terpisah di repo flutter-muslimin, `BLUEPRINT-SUPERVISI.md`).
- S3–S4 **tanpa migrasi baru**.

## Langkah di hosting
1. `cd ~/kangmuslim && git pull`
2. Bila migrasi S1 belum pernah dijalankan: cadangkan DB dulu, lalu `phpm spark migrate` (aman dijalankan ulang; bila sudah, tertulis
   "Nothing to migrate").
3. Di peramban: **Ctrl+F5** (CSS admin dibangun ulang).

## Cek cepat sesudah deploy (akun Admin)
- Sidebar KURIKULUM → Supervisi Guru: 4 item aktif (Instrumen, Jadwal, Penilaian, **Rekap**).
- Jadwal → tombol **Unduh Program (Excel)**.
- Penilaian → tombol **PDF** per kunjungan; kunjungan selesai → **Lembar Hasil (PDF)** gabungan.
- Rekap → nilai per guru, ringkasan predikat, daftar "Belum disupervisi", **Unduh Rekap (Excel)**.
- Hak peran lain (mis. Waka Kurikulum): Sistem → Hak Akses → baris "Supervisi Guru" (Lihat = melihat & mengunduh, Kelola = mengisi).
  Agar bisa dipakai di aplikasi Android, peran itu juga harus "boleh aplikasi".

## Uji di laptop
`php spark dev:uji-supervisi` (102 cek; `--simpan C:/folder` menyimpan contoh PDF & Excel untuk dibuka).
