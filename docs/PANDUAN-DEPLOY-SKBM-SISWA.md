# Panduan: setelah push — migrate, SKBM, siswa tanpa kelas, Koreksi honor

Urutan kerja dari awal sampai selesai (dibuat 2026-10-10). Perintah server dijalankan di **cPanel → Terminal**, akun hosting, folder `~/kangmuslim`.
`phpm` = perintah PHP yang dipakai di server ini (sama seperti `php`).

> Berkas Excel sekolah berisi data pribadi dan repo ini **publik**: unggah ke server lewat cPanel File Manager saja (jangan masuk git),
> dan hapus lagi setelah selesai.

## 1. Push (di laptop)
1. Buka terminal di `C:\xampp\htdocs\muslimin`.
2. `git status` (lihat daftar berkas), lalu:
   ```
   git add .
   git commit -m "SKBM, Excel SKBM, Koreksi, API Android, alur honor, nomor SK"
   git push
   ```
3. Repo Flutter (`C:\flutter-muslimin`) terpisah: berisi dokumen kontrak dan contoh respons baru. Tidak wajib di-push agar server jalan.

## 2. Di server: tarik kode dan migrasi
```
cd ~/kangmuslim
git pull
phpm spark migrate
```
- Harus muncul **dua** migrasi baru: `…SkbmSk` dan `…Skbm`, lalu `Migrations complete`.
- (Disarankan) cadangkan database dulu: cPanel → **Backup** → *Download a MySQL Database Backup*.
- Buka situs admin, tekan **Ctrl+F5**. Menu **GURU → SKBM** harus ada untuk Admin (Operator/Waka Hubin tidak melihatnya).

## 3. Siswa tanpa kelas (kerjakan SEBELUM Koreksi honor: jumlah lembar dihitung dari jumlah siswa tiap kelas)
**Yang dilakukan perintah ini:** hanya menempatkan siswa aktif tanpa kelas bila **pasti** (satu-satunya kelas kosong di tingkat + jurusan itu);
yang tidak pasti hanya dilaporkan, tidak pernah ditebak. Siswa yang tidak ada di daftar resmi **tidak dihapus**: statusnya diubah menjadi
*pindah* (mutasi) atau *keluar* dan tetap tersimpan sebagai arsip.

1. Unggah **`Data Siswa.xlsx`** (nama persis begitu) ke folder `kangmuslim/writable/data-sekolah/` lewat cPanel **File Manager** (buat folder bila belum ada).
2. **Laporan dulu** (tidak mengubah apa pun):
   ```
   cd ~/kangmuslim
   phpm spark siswa:tempatkan-kelas
   ```
   Cara membaca:
   - baris hijau **PASTI** = akan ditempatkan otomatis ke kelas yang disebut;
   - baris kuning **TEMPATKAN MANUAL** = nomor rombel tidak bisa disimpulkan;
   - "siswa aktif tidak ada di Excel resmi" = calon mutasi/keluar (daftar NIS-nya ada di laporan CSV).
   - Laporan CSV lengkap: `writable/data-sekolah/laporan-kelas/belum-berkelas.csv` (unduh lewat File Manager, buka di Excel).
3. **Tulis yang pasti:**
   ```
   phpm spark siswa:tempatkan-kelas --tulis
   ```
4. **Siswa yang tidak ada di daftar resmi** (tentukan dulu: pindah sekolah = `pindah`, berhenti/keluar = `keluar`):
   ```
   phpm spark siswa:tempatkan-kelas --tulis --keluarkan NIS1,NIS2 --status pindah
   ```
   Hanya NIS siswa aktif tanpa kelas yang tidak ada di Excel yang diproses; NIS lain dilewati (tertulis merah). Kolom keterangan siswa terisi otomatis.
5. Jalankan laporan lagi (tanpa `--tulis`): bagian **PASTI** harus tinggal 0.
6. **Sisanya ditempatkan manual di web** — Master → **Siswa**:
   - Satu per satu: filter **— Tanpa kelas —** (boleh ditambah tingkat/jurusan) → ikon edit (pensil) → pilih **Kelas** → Simpan.
   - Massal: **Export** (dengan filter Tanpa kelas) → isi kolom **Kelas** dengan nama kelas persis seperti Master Kelas (mis. `X TKJ 1`) → **Import** → periksa pratinjau → simpan.
     (Sel yang dikosongkan tidak mengubah data lama; pakai file hasil Export terbaru.)
   - Kelas yang benar ditanyakan ke wali kelas / kesiswaan.
7. Selesai: **hapus** `Data Siswa.xlsx` dan folder `laporan-kelas` dari server (data pribadi).

## 4. SKBM (kurikulum)
1. **Master → Guru**: pastikan semua guru di SKBM ada. Yang belum ada akan tampil "Tidak ada di Master Guru" di pratinjau impor; tambahkan dulu bila memang pengajar.
2. **Pengaturan Sekolah**: isi **Nama Kepala Sekolah** dan **NIP Kepala Sekolah** (nomor saja, mis. NRKS; tercetak "NRKS. …" di tanda tangan Excel SKBM).
3. **GURU → SKBM** → pilih tahun ajaran **2026/2027** → isi **Nomor SK** → Simpan.
4. **Impor dari Excel SKBM** → pilih berkas jadwal sekolah (lembar bernama "SKBM…" yang dibaca) → **periksa pratinjau**:
   Blok 2 terpilih otomatis, semua 42 kolom kelas dikenal; guru yang tidak cocok: pilih orangnya atau biarkan dilewati → **Terapkan impor**.
   Bila server menolak berkas karena ukuran: salin lembar SKBM ke berkas Excel baru yang kecil, lalu unggah itu.
5. Periksa: tile Guru/Baris/JP, **JP belum diisi**, dan **Beda dengan Penugasan** (angka; klik untuk rinciannya). Klik **Unduh Excel SKBM** dan bandingkan dengan berkas contoh sekolah.

## 5. Honor → Koreksi (TERAKHIR, setelah langkah 3 dan 4)
1. **Ujian → ASTS 1** (atau jenis lain) → tab **Honor** → langkah 3 → **Koreksi**.
2. Panel **Isi dari SKBM** sudah terbuka → tekan tombolnya. Panel **Terapkan ke kolom Koreksi honor** terbuka berikutnya → periksa daftar beda → **Terapkan**.
3. Lanjutkan alur di tab Honor: Periksa → Tandai Final → Kunci → Cetak/Unduh.
4. Catatan: angka Koreksi dari SKBM bisa sedikit berbeda dari Excel "KOREKSI NILAI" sekolah (berkas itu sendiri tidak persis sama dengan SKBM di beberapa baris);
   pimpinan/kurikulum yang memutuskan mana yang dipakai membayar.

## Bila ada masalah
- `Berkas Excel tidak ditemukan` → nama berkas bukan `Data Siswa.xlsx` atau foldernya bukan `writable/data-sekolah/`.
- `--keluarkan … DILEWATI` → NIS salah ketik, atau siswanya sudah punya kelas / ada di Excel resmi.
- Menu SKBM tidak muncul → `phpm spark migrate` belum dijalankan, atau akunnya bukan Admin.
- Halaman terlihat lama → Ctrl+F5.
- Salah menempatkan siswa → ubah lagi lewat Master → Siswa (edit → Kelas); status pindah/keluar dikembalikan lewat edit siswa (Status → Aktif).
