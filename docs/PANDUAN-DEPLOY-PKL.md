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
   Migrasi PKL ada 3: `AddAkunStaf` (akun staf), `CreatePkl` (tabel PKL), `PklSurat` (nomor surat). Yang sudah pernah
   dijalankan dilewati sendiri; kalau tak ada yang baru, tidak apa-apa.
3. Pastikan folder penyimpan template surat bisa ditulis:
   ```
   mkdir -p ~/kangmuslim/writable/pkl
   chmod 775 ~/kangmuslim/writable/pkl
   ```
4. **Subdomain** (cPanel → *Domains* → *Create A New Domain*):
   - Domain: `binuspkl.kangmuslim.com`
   - Centang **Share document root** (folder sama dengan kangmuslim.com).
   - Hapus subdomain lama `pklbinus` bila masih ada.
   - Buka *SSL/TLS Status* → klik **Run AutoSSL** sampai `binuspkl` bergembok. Jangan menyertakan domain wildcard.
5. Buka `https://binuspkl.kangmuslim.com/` di HP. Kalau muncul "Pengajuan PKL Belum Dibuka", pemasangan sudah benar
   (form memang belum dibuka, lihat bagian B).

## B. Siapkan sebelum dibagikan ke siswa

1. Login sebagai Admin → menu **Kelola Akun** → buat akun **Operator Sekolah** dan **Waka Hubin**.
   Sandi sementara tampil **sekali saja**, catat dan berikan ke orangnya. Mereka wajib menggantinya saat login pertama.
2. Login sebagai Operator → **PKL / PRAKERIN → Pengaturan PKL**, isi:
   - Tingkat yang boleh mengajukan (XI, XII, atau keduanya), tanggal PKL paling awal dan paling akhir, lama PKL
     minimal dan maksimal, jumlah siswa maksimal per perusahaan.
   - **Surat permohonan PKL**: nama, NIP, dan jabatan Waka Hubin (nama inilah yang tercetak di bawah ruang tanda
     tangan), serta format nomor surat. Kalau nama tidak diisi, di surat hanya ada titik-titik.
   - Opsional: unggah **template Word** milik sekolah (unduh "contoh template" dulu, sunting di Word, jangan ubah
     penanda `${...}`, lalu unggah).
   - Terakhir, nyalakan **Form dibuka**.
   Kotak kuning **"Persiapan"** di Beranda PKL menandai mana yang belum siap dan hilang sendiri bila semuanya beres.
3. **Uji coba satu kali**: pakai satu siswa contoh. Buka tautan siswa → isi sampai terkirim → login Operator →
   Kotak Masuk → Periksa → ACC → Disetujui → buka ajuannya → **Terbitkan nomor & unduh surat** → buka berkasnya di
   Microsoft Word. Setelah puas, hapus ajuan uji coba itu (tombol "Hapus ajuan ini") agar nomor surat dan statistik bersih.
   Nomor surat pertama bisa diatur lewat "nomor berikutnya" di Pengaturan bila perlu.
4. Bagikan tautan ke siswa (contoh pesan di bagian D).

## C. Pemakaian sehari-hari (Operator / Waka Hubin)

| Mau apa | Di mana |
|---|---|
| Periksa ajuan baru | PKL → **Kotak Masuk** → tab *Menunggu ACC* → **Periksa** |
| ACC banyak sekaligus | Tab *Menunggu ACC* → centang lalu **ACC terpilih**, atau **ACC semua yang aman**. Hanya ajuan berlabel ✓ Aman yang disetujui; yang bertanda ⚠ dilewati dan dijelaskan alasannya |
| Minta siswa memperbaiki | Detail ajuan → **Kembalikan** (wajib alasan) |
| Cetak surat satu ajuan | Tab *Disetujui* → buka ajuan → **Terbitkan nomor & unduh surat** |
| Cetak banyak surat | Tab *Disetujui* → **Unduh surat terpilih**, **Yang belum dicetak / perlu cetak ulang**, atau **Unduh SEMUA surat** (tiap surat di halaman baru; lebih dari 60 surat jadi berkas ZIP) |
| Siapa yang belum mengisi / belum PKL | **Status Siswa** (ada tombol Excel) |
| Siswa tak bisa mengisi sendiri / data PKL lama | **Isi atas Nama**, atau **Impor** Excel (Operator/Admin) |

Surat dicetak, lalu **Waka Hubin menandatangani dan memberi stempel basah** di kertas, kemudian siswa membawanya ke
perusahaan. Gambar tanda tangan/stempel sengaja tidak dipasang otomatis.

Tanda **"Perlu cetak ulang"** muncul bila data berubah setelah surat diunduh (perusahaan, tanggal, siswa, atau
nama/NIP/jabatan Waka Hubin). Nomor surat tidak berubah, jadi cukup unduh lagi dan cetak ulang.

Waka Hubin **tidak** bisa membuka Pengaturan PKL, Hapus, Impor, Isian Biodata Siswa, dan Master Data (sengaja).

## D. Contoh pesan WhatsApp untuk siswa

```
Assalamu'alaikum. Untuk siswa kelas XI yang akan PKL:
silakan isi pengajuan tempat PKL lewat tautan ini (cukup dari HP, tanpa login):

https://binuspkl.kangmuslim.com/

Siapkan dulu: nama & alamat lengkap perusahaan, nomor telepon perusahaan, nama pimpinan/kontak,
tanggal mulai-selesai PKL, dan nama teman yang satu tempat (kalau ada).
Cukup SATU siswa yang mengisi untuk satu kelompok. Simpan/tangkap layar nomor bukti di akhir.
Isi paling lambat: [tanggal]. Kalau ada kendala, hubungi operator sekolah.
```

## E. Kalau ada masalah

| Gejala | Penyebab / tindakan |
|---|---|
| Halaman siswa bilang "Belum Dibuka / Sudah Berakhir" | Form belum dibuka atau lewat batas waktu. Atur di Pengaturan PKL |
| `https://binuspkl...` tidak aman / tak terbuka | AutoSSL belum selesai (tunggu beberapa menit lalu *Run AutoSSL*), atau subdomain tidak memakai "Share document root" |
| Nama di bawah tanda tangan surat titik-titik | Nama Waka Hubin belum diisi di Pengaturan PKL. Isi, lalu unduh surat lagi |
| Layar "Menyiapkan berkas…" lama | Surat sedang dirakit (banyak surat butuh beberapa detik). Layar tertutup sendiri begitu berkas turun. Jangan klik dua kali |
| Tombol Pengaturan tidak ada | Akun Anda Waka Hubin; hanya Operator/Admin yang boleh |
| Surat berantakan di Word | Kabari pengembang, sertakan berkasnya. Sementara, hapus template unggahan agar memakai surat bawaan |
| Tampilan lama / tak berubah setelah update | Muat ulang paksa (Ctrl+F5) |
