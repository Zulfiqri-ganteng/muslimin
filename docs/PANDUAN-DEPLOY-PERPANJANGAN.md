# Panduan: memasang Surat Perpanjangan PKL di server

Dibuat 2026-10-26. Fitur: **Surat Permohonan Perpanjangan PKL** (menu staf + formulir siswa di Layanan Siswa + API Android) dan menu
**Sertifikat PKL** bertanda *Segera*. Rancangan & aturan: `docs/DESAIN-PERPANJANGAN-PKL.md`. Perintah server dijalankan di
**cPanel → Terminal**, folder `~/kangmuslim`. `phpm` = perintah PHP di server ini (sama seperti `php`).

## 1. Push (di laptop)
```
git add .
git commit -m "Surat Perpanjangan PKL: staf, Layanan Siswa, API Android + menu Sertifikat PKL (Segera)"
git push
```

## 2. Di server
1. **Cadangkan database dulu** (migrasi ini mengubah daftar jenis surat): cPanel → **Backup** → *Download a MySQL Database Backup*.
2. Tarik kode & jalankan migrasi:
   ```
   cd ~/kangmuslim
   git pull
   phpm spark migrate
   ```
   Harus muncul `Running: … 2026-10-27-000001 … PklPerpanjangan` lalu `Migrations complete.`
3. Pastikan kunci aplikasi terisi (dipakai token API siswa di Android; web tetap jalan tanpa ini):
   ```
   grep -c "^encryption.key" .env
   ```
   Hasil `1` = ada. Hasil `0` → beri tahu Claude/pengembang (JANGAN membuat kunci baru sembarangan bila sudah ada data terenkripsi).
4. Buka situs admin, tekan **Ctrl+F5** (memuat ulang CSS/JS terbaru).

## 3. Cek cepat (±5 menit)
1. Login **Operator** → menu **SURAT SEKOLAH → Surat Perpanjangan PKL** terbuka; ada kotak cari dan daftar *PKL berakhir dalam 7 hari*.
2. **PKL → Beranda**: kartu biru *"N siswa PKL-nya berakhir dalam 7 hari"* tampil bila ada siswa yang tanggal selesainya tercatat.
3. **PKL → Pengaturan PKL** → bagian *Permintaan Surat Perpanjangan PKL dari siswa*: bawaan *Siswa boleh meminta*, H-7, H+30.
4. Buka situs publik → *Daftar layanan* → ikon **"Ajukan surat perpanjangan PKL"** ada di bagian *Surat dari sekolah*.
5. Login **Admin** → di grup PKL ada **Sertifikat PKL** redup bertanda *Segera* (Operator/Hubin tidak melihatnya — aturan menu Segera).
6. Uji surat (boleh): buat satu Surat Perpanjangan untuk siswa sungguhan yang memang diperpanjang. Nomor surat baru terbit saat **unduh
   pertama** — jangan mengunduh surat uji. Surat yang belum diunduh boleh **Dibatalkan** dari detailnya tanpa membuang nomor.

## 4. Hak akses
Migrasi menyamakan hak Surat Perpanjangan dengan hak **Surat Balasan** (di sekolah ini: Operator membuat, meng-ACC, mengunduh). Bila ingin
lain, atur di **SISTEM → Hak Akses → PKL & Surat → Surat Perpanjangan PKL**.

## 5. Teks untuk siswa (WhatsApp grup kelas)
> Bagi siswa yang PKL-nya **diperpanjang perusahaan**: minta Surat Perpanjangan PKL lewat
> https://kangmuslim.com/layanan/surat/perpanjangan (masuk dengan NISN atau tanggal lahir). Bisa diminta mulai 7 hari sebelum PKL selesai
> sampai 30 hari sesudahnya. Perpanjangan hanya boleh **satu kali**, jadi pastikan tanggalnya sesuai kesepakatan dengan perusahaan.
> Bila temanmu di tempat PKL yang sama juga diperpanjang sampai tanggal yang sama, centang namanya. Surat yang sudah siap diambil di TU.

## 6. Masalah umum
| Gejala | Penyebab & langkah |
|---|---|
| Menu Surat Perpanjangan PKL error / tabel tidak ada | `phpm spark migrate` belum dijalankan. |
| Ikon siswa "Sedang ditutup" | Pengaturan PKL → Permintaan Surat Perpanjangan → *Siswa boleh meminta*. |
| Siswa: "bisa diminta mulai …" | Belum H-7. Operator tetap bisa membuatkan dari menu staf kapan saja (tanpa batas waktu). |
| Siswa: "sudah lewat batas" | Lewat H+30. Buatkan dari menu staf, atau ubah angka H+30 di Pengaturan (0 = tanpa batas). |
| "Perpanjangan PKL hanya boleh satu kali" | Siswa sudah punya surat perpanjangan. Bila surat lama salah: **Batalkan** surat lama, lalu buat lagi. |
| Tanggal selesai semula berlencana kuning "diketik siswa" | Ajuan belum punya Surat Balasan; cocokkan dengan perusahaan sebelum ACC. |
| Aplikasi Android: "Layanan siswa lewat aplikasi belum diaktifkan" | `encryption.key` di `.env` kosong (langkah 2.3). Web tetap bisa dipakai. |
| Ingin membatalkan migrasi | `phpm spark migrate:rollback` menolak bila sudah ada Surat Perpanjangan (data tidak dihapus diam-diam). |

## 7. Rapi tampilan PKL & Surat (ikut pembaruan yang sama)
Tampilan halaman PKL & Surat dirapikan (tombol jarang dipakai pindah ke menu **Lainnya**, bilah untuk yang dicentang, bagian dilipat,
cari-lalu-pilih di form Balasan/Penarikan, Pengaturan PKL bertab). **Tanpa migrasi tambahan, tanpa perubahan API, hak akses, maupun
isian formulir.** Sesudah `git pull` cukup minta staf menekan **Ctrl+F5**. Panduan untuk staf: `docs/PANDUAN-TAMPILAN-PKL-SURAT.md`.
Rincian teknis & hasil uji: `docs/RENCANA-RAPI-TAMPILAN-PKL-SURAT.md`.

## 8. Pembaruan 2026-10-28 — ajak teman satu PERUSAHAAN (lintas ajuan)
Teman yang bisa dicentang di Surat Perpanjangan kini = **semua siswa yang PKL di perusahaan yang sama**, walau dulu mengajukan PKL
terpisah; semuanya masuk **satu surat**. Rincian: `docs/DESAIN-PERPANJANGAN-PKL.md` bagian 13.

**Di server (1 migrasi kecil, menambah satu kolom):**
1. Cadangkan database (cPanel → Backup).
2. ```
   cd ~/kangmuslim
   git pull
   phpm spark migrate
   ```
   Harus muncul `Running: … 2026-10-28-000001 … PerpanjanganLintasAjuan` lalu `Migrations complete.` Surat perpanjangan yang SUDAH ada
   diisi otomatis (tidak perlu ekspor/impor data).
3. Ctrl+F5 di peramban staf.

**Cek cepat:** Operator → Surat Perpanjangan PKL → cari nama perusahaan yang siswanya banyak (mis. Noree) → hanya **satu** baris hasil
bertuliskan "· N siswa dari M ajuan" → Pilih → semua siswanya tampil; yang tercentang hanya siswa ajuan yang dipilih.

**Surat yang sudah terlanjur diajukan (sebelum pembaruan):** tetap sah, tidak berubah. Bila temannya ikut diperpanjang:
- teman meminta sendiri lewat Layanan Siswa dengan tanggal yang SAMA → otomatis **ikut surat itu** selama surat belum bernomor (belum diunduh); atau
- Operator buka surat itu → **Lainnya → Ubah data** → centang teman (kini tampil semua siswa perusahaan itu) → Simpan perubahan.
Surat yang sudah bernomor tidak bisa diubah: teman dibuatkan surat baru (menu Surat Perpanjangan → cari → centang teman).

**Teks WA untuk siswa (pengganti kalimat terakhir di bagian 5):**
> Di langkah *Teman yang juga diperpanjang* sekarang muncul **semua temanmu yang PKL di perusahaan yang sama**. Centang yang PKL-nya juga
> diperpanjang sampai tanggal yang sama — cukup satu orang yang mengisi, temanmu tidak perlu mengisi lagi.

| Gejala | Penyebab & langkah |
|---|---|
| Teman tampil redup "beda tanggal" | Tanggal selesai PKL teman itu tercatat beda. Satu surat = satu tanggal selesai; teman itu minta surat sendiri / Operator buat surat terpisah (tautan "buat surat terpisah"). |
| Teman tidak muncul sama sekali | Ajuan PKL teman belum **disetujui**, siswa tidak aktif, atau tempat PKL-nya tercatat sebagai perusahaan lain (beda data master). |
