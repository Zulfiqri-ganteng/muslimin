# Sistem Kesediaan Guru Mengajar

Aplikasi web untuk menggantikan Google Form — guru mengisi **Format Kesediaan Guru Mengajar**,
admin sekolah meninjau, mengelola, dan mencetak hasilnya (PDF / Excel).

- **Frontend:** TailwindCSS (responsif, semua perangkat)
- **Backend:** PHP + CodeIgniter 4
- **Database:** MySQL
- **Laporan:** PDF (Dompdf) & Excel (PhpSpreadsheet)

---

## 1. Struktur & Fitur

**Halaman Guru (publik, tanpa login)** — `/`
- Form kesediaan lengkap: identitas, mata pelajaran diampu (baris dinamis + total jam otomatis),
  tugas tambahan, kesediaan jam, preferensi mapel, ketersediaan hari, komitmen & pernyataan.
- 1 NIP/NUPTK hanya bisa mengisi **sekali** (duplikat ditolak).

**Panel Admin** — `/admin`
- **Dashboard** — statistik & kesediaan terbaru.
- **Data Kesediaan** — daftar, cari, filter status kepegawaian, lihat detail, verifikasi, hapus.
- **Export** — Surat Pernyataan per-guru (PDF), Rekap semua (PDF landscape & Excel).
- **Pengaturan Sekolah** — nama, logo, kepala sekolah, kota, tahun pelajaran, buka/tutup form.
- **Profil Admin** — ubah data, foto, dan password.

---

## 2. Akun Admin Default

```
URL      : http://localhost/muslimin/public/admin/login
Username : admin
Password : admin123
```
> ⚠️ **Wajib ganti password** setelah login pertama (menu Profil Saya).

---

## 3. Menjalankan di XAMPP (Lokal)

1. Pastikan folder proyek ada di `c:\xampp\htdocs\muslimin`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. **Buat database** (sudah otomatis dibuat saat development). Jika belum ada, buka
   phpMyAdmin → buat database `muslimin`, lalu **Import** file `database/muslimin.sql`.
4. Cek konfigurasi pada file `.env` (sudah disetel untuk XAMPP default):
   ```
   app.baseURL = 'http://localhost/muslimin/public/'
   database.default.database = muslimin
   database.default.username = root
   database.default.password =
   ```
5. Buka di browser: **http://localhost/muslimin/public/**
   - Form guru: `http://localhost/muslimin/public/`
   - Admin: `http://localhost/muslimin/public/admin/login`

> Alternatif tanpa `/public/`: buka `http://localhost/muslimin/` (sudah ada `.htaccess`
> yang mengarahkan ke `public/`).

---

## 4. Deploy ke cPanel via Git (alur seperti project galajuara)

Domain target: **kangmuslim.com** (domain utama hosting) → diarahkan ke `~/kangmuslim/public`.
Alur: `git push` dari lokal → di hosting jalankan deploy (pull + migrate). Folder `vendor/`
ikut di repo, jadi **tidak perlu** `composer install` di server.

> Catatan path: `~` = `$HOME` = `/home/<username-cpanel>`. Semua langkah di bawah
> memakai `~` agar tidak bergantung pada nama akun cPanel.

### A. Push ke GitHub (dari komputer lokal — sudah jalan)
Repo: `https://github.com/Zulfiqri-ganteng/muslimin.git` (remote `origin` sudah terpasang).
Update berikutnya cukup: `git add . && git commit -m "pesan" && git push`

### B. Siapkan Database di cPanel
1. cPanel → **MySQL Databases** → buat database `<akun>_muslimin` + user + password,
   lalu **Add User To Database** (All Privileges).
2. *(Opsional)* Tidak perlu import SQL — tabel & akun admin dibuat otomatis oleh
   `php spark migrate` saat deploy.

### C. Clone Repo ke Folder Project (sekali setup)
**Lewat cPanel → Git™ Version Control → Create:**
- *Clone URL:* `https://github.com/Zulfiqri-ganteng/muslimin.git`
- *Repository Path:* `kangmuslim`  (mis. `/home/<akun>/kangmuslim`)

> Repo privat butuh **GitHub Personal Access Token** pada URL:
> `https://<token>@github.com/Zulfiqri-ganteng/muslimin.git`

### D. Arahkan Document Root Domain
cPanel → **Domains** → `kangmuslim.com` → **Manage** → ubah *Document Root* menjadi:
```
/home/<akun>/kangmuslim/public
```
(Domain utama pun docroot-nya bisa diubah dari menu Domains. Menunjuk ke `.../public`,
sama seperti galajuara.)

### E. Buat File `.env` Produksi (sekali setup)
Lewat Terminal cPanel / SSH, di folder project:
```bash
cd ~/kangmuslim
cp .env.production.example .env
php spark key:generate          # generate kunci enkripsi unik
# lalu edit .env → isi nama DB, user, password sesuai langkah B
```

### F. Deploy Pertama & Selanjutnya
```bash
bash ~/kangmuslim/deploy/deploy.sh
```
Script `deploy/deploy.sh` (sudah disiapkan, pola galajuara) akan:
`git fetch` → `git reset --hard origin/main` → `git clean -fd` →
`php spark migrate --all` → `php spark cache:clear` → `chmod -R 775 writable`.

> **Otomatis:** pasang script itu sebagai **Cron Job** di cPanel (mis. tiap 5 menit)
> agar setiap `git push` otomatis ter-deploy — persis seperti galajuara.

### Catatan penting
- `.env` & `public/uploads/` **di-ignore Git**, jadi aman dari `git clean -fd`
  (tidak terhapus / tidak tertimpa saat deploy).
- Akun admin default dibuat otomatis: **admin / admin123** → segera ganti password.

---

## 5. Pemakaian Singkat

1. Admin login → **Pengaturan Sekolah** → isi nama sekolah, logo, kepala sekolah, dll.
2. Bagikan link form ke guru: `https://kangmuslim.com/` (atau `.../public/`).
3. Guru mengisi & mengirim.
4. Admin → **Data Kesediaan** → lihat detail, **Cetak Surat (PDF)**, atau **Export Excel/Rekap PDF**.
5. Jika periode selesai → matikan **"Buka pengisian form"** di Pengaturan.

---

## 6. Keamanan (Opsional, Disarankan untuk Produksi)
- Ganti password admin default.
- Set `CI_ENVIRONMENT = production` agar pesan error tidak tampil ke publik.
- Backup database secara berkala (Export di phpMyAdmin).
