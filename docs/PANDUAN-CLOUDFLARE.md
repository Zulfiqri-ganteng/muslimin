# Panduan Cloudflare — kangmuslim.com

kangmuslim.com (beserta `pklbinanusa.` dan `datasiswa.`, satu aplikasi di `/public_html`) akan dilayani lewat Cloudflare
(akun yang sama dengan galajuara.sch.id, paket gratis). Dikerjakan bertahap:

| Fase | Isi | Status |
|---|---|---|
| F3 | Kode siap membaca IP asli pengunjung di balik Cloudflare | **selesai di laptop — deploy di bawah** |
| F4 | Pindah DNS ke Cloudflare, awan abu-abu (belum diproksi, situs tetap sama persis) | menyusul |
| F5 | Nyalakan awan oranye + SSL Full (strict) + pengaturan keamanan + uji web & Android | menyusul (WAJIB sesudah F3 ter-deploy) |
| F6 | Panduan pemakaian sehari-hari (subdomain baru, bersihkan cache, larangan) | menyusul |

## F3 — Kenapa perlu

Di balik Cloudflare, semua pengunjung datang dari IP server Cloudflare. Tanpa perbaikan ini aplikasi mengira ribuan orang adalah
"satu orang", sehingga:
- **kunci login** (30× salah sandi per IP dalam 15 menit) bisa mengunci **semua** guru, staf, dan aplikasi Android sekaligus;
- batas percobaan form PKL / Layanan Siswa / Biodata ikut kacau;
- IP di **Sesi Aktif** dan **Audit Log** salah semua.

Perbaikan: `app/Config/App.php` → `$proxyIPs` berisi 22 rentang IP resmi Cloudflare, header yang dipercaya hanya `CF-Connecting-IP`.
- Header hanya dipercaya bila permintaan memang datang dari IP Cloudflare — orang yang menembak IP hosting langsung dengan header
  palsu tetap tercatat dengan IP aslinya.
- `X-Forwarded-For` sengaja TIDAK dipakai (CodeIgniter mengambil IP pertama, dan IP pertama bisa dipalsukan pengunjung).
- Sebelum Cloudflare menyala perubahan ini **tidak berpengaruh apa-apa** (IP pengunjung bukan IP Cloudflare) → aman di-deploy kapan saja.

### Deploy F3 di hosting
Tanpa migrasi, tanpa CSS baru.
1. Terminal cPanel:
   ```
   cd ~/kangmuslim
   cp public/.htaccess ~/htaccess-cadangan
   git stash && git pull && git stash pop
   ```
2. Pastikan `.env` di hosting **tidak** berisi baris `app.proxyIPs` (bila ada, hapus — daftar sudah di kode).
3. Cek cepat: buka situs & login admin seperti biasa → tidak ada yang berubah. Menu **Sistem → Sesi Aktif** tetap menampilkan IP asli.

### Android
Tidak ada yang perlu diubah: alamat API tetap `https://kangmuslim.com/api/v1`, isi permintaan & jawaban API sama, layar Flutter tidak
disentuh, kontrak di repo `C:\flutter-muslimin` tetap. Aplikasi baru diuji ulang di F5 (login, ajukan PKL, unduh surat, cek update APK).

### Bila Cloudflare mengubah daftar IP-nya (jarang)
1. Buka https://www.cloudflare.com/ips-v4 dan https://www.cloudflare.com/ips-v6.
2. Samakan isi `$proxyIPs` di `app/Config/App.php` **dan** konstanta `RESMI` di `app/Commands/UjiCloudflareIp.php`.
3. Jalankan `php spark dev:uji-cloudflare-ip` → harus 0 gagal, lalu deploy seperti di atas.

### Uji (laptop, DB = salinan hosting 9 Nov)
`dev:uji-cloudflare-ip` 18 (baru) · `dev:uji-pkl-publik` 36 · `dev:uji-lapor` 127 · `dev:uji-biodata-publik` 22 · `dev:uji-hak` 69 ·
`dev:uji-sesi` 26 · `dev:uji-api-android` 19 — semua 0 gagal.
