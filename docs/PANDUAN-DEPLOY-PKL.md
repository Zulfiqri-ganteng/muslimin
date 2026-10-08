# Panduan Pasang & Pakai Fitur PKL (SMK Bina Nusa)

Bahasa sederhana, urut dari atas ke bawah. Rancangan teknis ada di `DESAIN-PKL.md`.

## A. Pasang di hosting (kangmuslim.com)

1. **Commit & push** semua perubahan dari laptop (termasuk `public/assets/css/app.css` dan `public/assets/js/admin/app.js`
   yang sudah dibangun ulang, karena hosting hanya `git pull`, tidak membangun CSS).
2. Buka **Terminal cPanel**, lalu:
   ```
   cd ~/kangmuslim
   git pull
   phpm spark migrate
   ```
   Migrasi PKL ada 4: `AddAkunStaf` (akun staf), `CreatePkl` (tabel PKL), `PklSurat` (nomor surat), dan yang terbaru
   `PklSuratSekolah` (kolom catatan ACC, batas keputusan, tanda tangan Waka Hubin, format surat sekolah). Yang sudah pernah
   dijalankan dilewati sendiri; kalau tak ada yang baru, tidak apa-apa.

   > Catatan: migrasi pengosongan data PKL (`PklKosongkanData`) SUDAH DIHAPUS dari repo atas permintaan (2026-10-08) — `phpm spark migrate` tidak lagi menghapus data ajuan PKL. Pengosongan data, bila suatu saat diperlukan, dilakukan manual lewat sistem.
3. Pastikan folder penyimpan template surat bisa ditulis:
   ```
   mkdir -p ~/kangmuslim/writable/pkl
   chmod 775 ~/kangmuslim/writable/pkl
   ```
4. **Subdomain** (cPanel → *Domains* → *Create A New Domain*):
   - Domain: `pklbinanusa.kangmuslim.com`
   - Centang **Share document root** (folder sama dengan kangmuslim.com).
   - Hapus subdomain lama (`pklbinus` atau `binuspkl`) bila masih ada.
   - Buka *SSL/TLS Status* → klik **Run AutoSSL** sampai `pklbinanusa` bergembok. Jangan menyertakan domain wildcard.
5. Buka `https://pklbinanusa.kangmuslim.com/` di HP. Kalau muncul "Pengajuan PKL Belum Dibuka", pemasangan sudah benar
   (form memang belum dibuka, lihat bagian B).

## A2. Pembaruan 2026-10-09 — hak diatur Admin, biaya, WhatsApp, nomor 001

Dibawa oleh `git pull` + `phpm spark migrate` yang sama (tidak ada langkah server lain). **Cadangkan database dulu** (cPanel → Backup / phpMyAdmin → Export), karena ada migrasi yang menghapus data.

1. Migrasi baru ada **dua** dan berjalan berurutan:
   - `PklHakBiayaWa` — menambah kolom/tabel hak, biaya, pembayaran, beasiswa, keringanan, kabar WhatsApp; memperbaiki **format nomor `{urut}00/…` → `{urut3}/…`** dan titik "S.T." pada nama Kepala Sekolah.
   - `PklBersihkanDataUji` — **MENGHAPUS PERMANEN semua ajuan PKL uji coba** (ajuan, siswa pada ajuan, surat bernomor, perusahaan master, catatan biaya) dan mengembalikan nomor surat ke 001. Data siswa, akun, dan Pengaturan PKL tetap utuh.
     **Pengaman:** bila sudah ada ajuan yang dibuat sejak **13 Okt 2026**, migrasi ini TIDAK menghapus apa-apa (dianggap data sungguhan) dan hanya mencatat di Audit Log.
     Jalankan **sebelum** tautan form dibagikan ke siswa.
2. Login sebagai **Admin** → menu **PKL → Hak Akses**. Bawaan: Waka Hubin = ACC + ubah + tanda tangan; Operator = unduh surat + laporan + ubah + pengaturan. Ubah bila perlu (berlaku langsung; web & aplikasi).
3. **PKL → Pengaturan** (Operator/Admin): periksa **Format nomor surat** (pilih "Tiga angka: 001"), nominal **Biaya** (Rp 300.000 / 150.000 / 50.000 / 10.000), dan **Pesan WhatsApp**.
4. Cek nama Kepala Sekolah di Pengaturan PKL tertulis "…, S.T." (titik penutup ditambahkan otomatis saat disimpan/dicetak).

### Cara kerja baru (menimpa bagian C di bawah bila bertentangan)
- **Waka Hubin tidak bisa mengunduh/mencetak surat** (bawaan) — hanya ACC. **Operator** yang mengunduh surat; sebelum berkas dibuat muncul kotak **Catat biaya**: centang biaya yang sudah diterima tiap siswa
  (wajib minimal satu per siswa; atau Beasiswa 3 tahun / Keringanan beralasan). Satu surat → per siswa; banyak surat → satu set untuk semua. Salah catat? Detail ajuan → kartu **Pembayaran siswa** → hapus catatan (wajib alasan).
- Setelah unduhan selesai muncul **Kabari via WhatsApp**: tekan tombol hijau, WhatsApp terbuka dengan pesan siap kirim, tekan Kirim. Nomor diambil dari HP yang siswa isi di form. Tombolnya juga ada di daftar Disetujui dan halaman detail.
- **PKL → Laporan Pembayaran**: siapa, kelas, jurusan, sudah bayar berapa, Lunas/Sebagian/Belum, beasiswa; filter; **Unduh Excel** (Rincian, Rekap Kelas, Rekap Jurusan).
- Hapus ajuan kini bawaan **Admin saja** (Admin bisa memberi hak itu ke Operator di Hak Akses).

## B. Siapkan sebelum dibagikan ke siswa

1. Login sebagai Admin → menu **Kelola Akun** → buat akun **Operator Sekolah** dan **Waka Hubin**.
   Sandi sementara tampil **sekali saja**, catat dan berikan ke orangnya. Mereka wajib menggantinya saat login pertama.
2. Login sebagai Operator → **PKL / PRAKERIN → Pengaturan PKL**, isi:
   - Tingkat yang boleh mengajukan (XI, XII, atau keduanya), jumlah siswa maksimal per perusahaan (paling banyak 5),
     dan **batas hari keputusan Waka Hubin** (bawaan 5 hari). Siswa tidak lagi mengisi tanggal lahir maupun tanggal PKL.
   - **Surat permohonan PKL**: nama, NIP, dan jabatan Waka Hubin, nama Kepala Sekolah, nama & HP kontak sekolah untuk
     catatan "NB" di surat, format nomor surat (`295/SMK-BN/PKL/VII/2026`) dan format nama berkas
     (`295 Surat Izin PKL BINUS - Ilyasha ALL XII TKJ 5`). Surat sudah memakai format resmi sekolah (template bawaan),
     jadi unggah template Word sendiri hanya bila sekolah mengubah format suratnya.
   - Terakhir, nyalakan **Form dibuka**.
   Kotak kuning **"Persiapan"** di Beranda PKL menandai mana yang belum siap dan hilang sendiri bila semuanya beres.
3. **Waka Hubin unggah tanda tangan digital** (login sebagai Waka Hubin → sidebar **PKL / PRAKERIN → Tanda Tangan**, atau tab **Tanda Tangan** di bagian atas halaman PKL; PNG/JPG, maks 1 MB,
   latar putih boleh). Gambar ini terpasang di surat **hanya bila Waka Hubin sendiri yang meng-ACC**.
4. **Uji coba satu kali**: pakai satu siswa contoh. Buka tautan siswa → isi sampai terkirim → login **Waka Hubin** →
   Kotak Masuk → Periksa → ACC → Disetujui → buka ajuannya → **Terbitkan nomor & unduh surat** → buka berkasnya di
   Microsoft Word. Setelah puas, minta Admin menghapus ajuan uji coba itu agar nomor surat dan statistik bersih.
   Nomor surat pertama bisa diatur lewat "nomor berikutnya" di Pengaturan bila perlu.
5. Bagikan tautan ke siswa (contoh pesan di bagian D).

## C. Pemakaian sehari-hari (Operator / Waka Hubin)

**Aturan sekolah: yang meng-ACC (menyetujui), menolak, atau mencabut persetujuan hanya Waka Hubin.** Operator hanya memeriksa,
mengembalikan untuk diperbaiki, mengisi atas nama, dan mencetak surat — tombol ACC tidak muncul di akun Operator, dan bila
dipaksa tetap ditolak sistem (tercatat di Audit Log). Admin hanya cadangan bila Waka Hubin berhalangan: wajib mencentang
"saya mewakili Waka Hubin", dan di kaki surat tertulis jelas bahwa ACC dilakukan Admin. Keputusan Waka Hubin dibatasi
**5 hari** sejak ajuan dikirim; yang lewat batas ditandai **TERLAMBAT**.

| Mau apa | Di mana |
|---|---|
| Periksa ajuan baru | PKL → **Kotak Masuk** → tab *Menunggu ACC* → **Periksa** |
| ACC banyak sekaligus (**Waka Hubin/Admin**) | Tab *Menunggu ACC* → centang lalu **ACC terpilih**, atau **ACC semua yang aman**. Hanya ajuan berlabel ✓ Aman yang disetujui; yang bertanda ⚠ dilewati dan dijelaskan alasannya |
| Minta siswa memperbaiki | Detail ajuan → **Kembalikan** (wajib alasan) |
| Cetak surat satu ajuan | Tab *Disetujui* → buka ajuan → **Terbitkan nomor & unduh surat** |
| Cetak banyak surat | Tab *Disetujui* → **Unduh surat terpilih**, **Yang belum dicetak / perlu cetak ulang**, atau **Unduh SEMUA surat** (tiap surat di halaman baru; lebih dari 60 surat jadi berkas ZIP) |
| Siapa yang belum mengisi / belum PKL | **Status Siswa** (ada tombol Excel) |
| Siswa tak bisa mengisi sendiri / data PKL lama | **Isi atas Nama**, atau **Impor** Excel (Operator/Admin) |

Surat memakai **format resmi sekolah** (kop, tabel siswa, TTD dan nama Kepala Sekolah, catatan "NB"), ditambah blok
**Waka Hubin** (jabatan, gambar tanda tangan, nama) dan **kaki surat** berisi catatan ACC: siapa yang menyetujui, kapan, dan
kode verifikasi — sehingga jelas siapa yang bertanggung jawab. Gambar tanda tangan Waka Hubin terpasang hanya bila **Waka Hubin
sendiri** yang meng-ACC; bila Admin yang meng-ACC, ruang tanda tangan dibiarkan kosong untuk tanda tangan basah. Surat dicetak,
distempel bila perlu, lalu siswa membawanya ke perusahaan.

Tanda **"Perlu cetak ulang"** muncul bila data berubah setelah surat diunduh (perusahaan, siswa, HP, penanda tangan, tanda
tangan, atau catatan ACC). Nomor surat tidak berubah, jadi cukup unduh lagi dan cetak ulang.

Waka Hubin **tidak** bisa membuka Pengaturan PKL, Hapus, Impor, Isian Biodata Siswa, dan Master Data (sengaja).
Operator tidak bisa membuka tab Tanda Tangan. Ajuan yang **sudah disetujui** hanya bisa diubah Waka Hubin/Admin dan hanya
bisa dihapus Admin.

Aplikasi Android: Operator dan Waka Hubin cukup login di aplikasi; yang tampil hanya menu PKL (API: `docs/API-PKL.md`).

## D. Contoh pesan WhatsApp untuk siswa

```
Assalamu'alaikum. Untuk siswa kelas XI yang akan PKL:
silakan isi pengajuan tempat PKL lewat tautan ini (cukup dari HP, tanpa login):

https://pklbinanusa.kangmuslim.com/

Siapkan dulu: nama & alamat lengkap perusahaan, nomor telepon perusahaan, nama pimpinan/kontak,
nomor HP kamu, dan nama + nomor HP teman yang satu tempat (maksimal 5 siswa per perusahaan).
Cukup SATU siswa yang mengisi untuk satu kelompok. Simpan/tangkap layar nomor bukti di akhir.
Setelah terkirim, JANGAN mengajukan ulang: tunggu keputusan Waka Hubin paling lama 5 hari.
Isi paling lambat: [tanggal]. Kalau ada kendala, hubungi operator sekolah.
```

## E. Kalau ada masalah

| Gejala | Penyebab / tindakan |
|---|---|
| Halaman siswa bilang "Belum Dibuka / Sudah Berakhir" | Form belum dibuka atau lewat batas waktu. Atur di Pengaturan PKL |
| `https://pklbinanusa...` tidak aman / tak terbuka | AutoSSL belum selesai (tunggu beberapa menit lalu *Run AutoSSL*), atau subdomain tidak memakai "Share document root" |
| Nama di bawah tanda tangan surat titik-titik | Nama Waka Hubin belum diisi di Pengaturan PKL. Isi, lalu unduh surat lagi |
| Layar "Menyiapkan berkas…" lama | Surat sedang dirakit (banyak surat butuh beberapa detik). Layar tertutup sendiri begitu berkas turun. Jangan klik dua kali |
| Tombol Pengaturan tidak ada | Akun Anda Waka Hubin; hanya Operator/Admin yang boleh |
| Tombol ACC tidak ada / "hanya boleh dilakukan Waka Hubin" | Akun Anda Operator. Minta Waka Hubin yang meng-ACC (Admin hanya cadangan) |
| Gambar tanda tangan Hubin tak muncul di surat | Surat itu di-ACC oleh Admin (bukan Hubin), atau tanda tangan belum diunggah di tab Tanda Tangan. Setelah diunggah, unduh surat lagi |
| Siswa hanya bisa ajak 1 teman (tulisan "Maksimal 2 siswa per perusahaan") | Angka di **Pengaturan PKL → Maksimal siswa per perusahaan** tersimpan lebih kecil dari 5. Ubah jadi **5** lalu Simpan — berlaku langsung, siswa cukup muat ulang halaman. |
| Siswa bilang "tidak bisa mengajukan lagi" | Memang aturannya: siswa yang sudah mengajukan menunggu keputusan Waka Hubin. Bila salah isi, Operator mengembalikannya untuk diperbaiki, atau Operator/Admin menghapus ajuannya (yang sudah disetujui hanya Admin) |
| Surat berantakan di Word | Kabari pengembang, sertakan berkasnya. Sementara, hapus template unggahan agar memakai surat bawaan |
| Tampilan lama / tak berubah setelah update | Muat ulang paksa (Ctrl+F5) |
