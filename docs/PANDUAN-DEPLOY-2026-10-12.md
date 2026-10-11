# Panduan: memasang pekerjaan 2026-10-12 di server

Isi paket ini: hapus `cek-hosting.php`, Surat Sekolah L7 (Excel agenda + kartu Beranda PKL), tanda petik di Excel,
pengaman CSRF untuk seluruh panel admin (+ tombol hapus lewat POST), 2 celah API Ujian, dashboard baru,
tema **ungu lilac** akun admin, L9 Jabatan & Siswa, D9 Dokumen (+ penyimpanan `.bin` untuk berkas skrip).
**Tidak ada migrasi database.** Perintah server di **cPanel → Terminal**, folder `~/kangmuslim`.

## 1. Push (di laptop)
```
git add .
git commit -m "CSRF admin, dashboard baru, tema lilac, Surat L7, API Ujian, L9, D9"
git push
```
Repo Flutter (`C:\flutter-muslimin`): `BLUEPRINT-UJIAN.md` diperbarui (API Ujian kini mengirim `nama` + opsi
`ujian_periode_status`). Tidak wajib di-push agar server jalan.

## 2. Di server
```
cd ~/kangmuslim
git pull
```
- `public/cek-hosting.php` ikut **terhapus** dari server oleh `git pull` (bila berkas itu belum pernah diubah di server).
  Cek: buka `https://kangmuslim.com/cek-hosting.php` → harus **404**.
- Tidak perlu `phpm spark migrate`.

## 3. Wajib setelah deploy: muat ulang semua tab admin
Pengaman CSRF baru aktif di semua halaman admin. Tab yang **sudah terbuka sebelum deploy** masih memakai skrip
lama, jadi simpan otomatis/tombol di tab itu bisa menolak dengan pesan *"Halaman ini sudah kedaluwarsa…"*.
**Tekan Ctrl+F5 di setiap tab admin** (atau tutup lalu buka lagi). Setelah itu normal.

## 4. Cek cepat (±10 menit)
1. **Login admin**: halaman login & sidebar berwarna **ungu lilac**, menu aktif bergaris **mint** di kiri.
   Halaman publik & form siswa (biodata, PKL) tetap **biru** — memang disengaja.
2. **Dashboard**: angka berhitung naik, batang beban guru tumbuh, kartu "Perlu tindakan", garis waktu Ujian, donat kesediaan.
3. **Hapus** satu data uji di salah satu master (mis. buat Pengumuman "uji" lalu hapus): muncul konfirmasi → terhapus.
4. **Simpan** satu formulir apa saja (mis. ubah Pengumuman) → berhasil, tanpa pesan kedaluwarsa.
5. **Absensi Guru** & **Jadwal KBM** (yang menyimpan tanpa pindah halaman) → simpan sekali, berhasil.
6. **Master Guru → Export**: kolom NIP & No. WhatsApp tanpa tanda petik `'`, angka 0 di depan tetap ada.
7. **Surat Sekolah → Daftar Surat → ⬇ Excel**: terunduh *Buku Agenda Surat Keluar*. **PKL → Beranda**: ada 3 kartu Surat sekolah.
8. **Arsip Dokumen**: unggah satu PDF kecil → buka → bagikan tautan → buka tautan di jendela penyamaran → hapus lagi.

## 4b. Tambahan paket kedua (2026-10-12 siang) — juga tanpa migrasi
- **Sapu warna lilac**: sisa warna biru/indigo/sky di 43 halaman admin + grid SKBM & Koreksi kini lilac (keterangan grid "ungu").
  Status yang dulu ungu (mis. *sakit* di absensi, *multimedia* di lab, *laboran*, *maintenance*) kini **merah muda** supaya tak tertukar dengan lilac.
  Warna jenis berkas di Arsip Dokumen (Word biru, dst.) **tetap**. Cek: buka Absensi, SKBM, Ujian → Koreksi, Peminjaman — tidak ada lagi biru.
- **API Surat Sekolah untuk Android** (`api/v1/surat…`, `docs/API-SURAT.md`): Operator & Waka Hubin kini boleh memakai API surat.
  Belum terlihat di aplikasi sampai layar Flutter dibuat (kontrak: `C:\flutter-muslimin\BLUEPRINT-SURAT.md`). Tidak mengubah apa pun di web.

## 5. Bila ada masalah
- Pesan **"Halaman ini sudah kedaluwarsa"** terus muncul walau sudah Ctrl+F5: kirim nama halamannya ke AI/pengembang.
- **"Berkas terlalu besar"** saat unggah: memang batas server (`post_max_size`) — kecilkan berkas.
- Warna masih biru di admin: CSS lama tersimpan di peramban → Ctrl+F5.
- Galat lain: kirim isi baris terbawah `~/kangmuslim/writable/logs/log-<tanggal>.log`.
