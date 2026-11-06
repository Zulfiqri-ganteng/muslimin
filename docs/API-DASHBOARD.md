# API Dashboard Admin (`GET api/v1/admin/dashboard`) — diperluas 2026-11-07

Sumber data SAMA dengan halaman web `admin/dashboard`: `Libraries\DashboardData` (method dipindah utuh dari `Controllers\Admin\Dashboard`,
rumus tidak berubah). Kolom lama (`school, admin, absensi, kesediaan, penjadwalan, beban_chart, recent`) tetap → aplikasi lama tetap jalan.

## Kolom baru
- `kesediaan.dibuka: bool` — formulir kesediaan dibuka.
- `siswa: {aktif, tanpa_kelas, jurusan: [{label, nama, n}], tingkat: [{label, n}]}` (cache 5 menit, sama dengan web).
- `jp_tugas: int` — total JP penugasan guru pengajar (pembanding "Jadwal KBM tersusun" = `penjadwalan.jp_terjadwal ÷ jp_tugas`).
- `tindakan: [{tingkat: kritis|awas|info, n, teks, alamat}]` — "Tugas hari ini" (hanya n > 0, urut paling mendesak). `alamat` = halaman
  web relatif (mis. `admin/pkl/daftar/menunggu`, `admin/master/siswa/periksa#ganda`); aplikasi memetakannya ke layar sendiri. Butir
  Periksa Data Siswa hanya untuk peran yang boleh membuka Master Siswa.
- `aktivitas: [{nama, aksi, deskripsi, waktu}]` — 8 kegiatan staf terbaru (Audit Log); nama kosong → "Akun terhapus" / "Sistem".
- `tren: [{t: Y-m-d, n, hadir, telat, izin, sakit, alpa}]` — kehadiran guru 180 hari (hanya hari tercatat). Persen = (hadir + telat) ÷ n;
  rentang 7/30/90 hari dibanding rentang yang sama tepat sebelumnya (rumus web).
- `ujian: {tahun, kartu: [{jenis, label, slug, status: draft|berjalan|selesai, jadwal, tak_hadir, belum}]}`.
- `modul: [{judul, ikon, ket, ket2|null, alamat}]` — "Ringkasan modul", kalimat siap tampil (`DashboardData::barisModul`, dipakai juga view web).

## Juga 2026-11-07
- `auth/login` & `auth/me` → `admin.wali_kelas: bool` (lihat `docs/API-WALI-KELAS.md`).

## Uji
`php spark dev:uji-dashboard` (15 cek, hanya membaca). `--fixture <folder>` → `admin_dashboard.json` (sudah di
`C:\flutter-muslimin\test\fixtures\dashboard\`, nama guru/staf difiktifkan).

## Deploy
Cukup `git pull` (tanpa migrasi).
