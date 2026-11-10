# Panduan Cloudflare — kangmuslim.com

Sejak **10 November 2026** kangmuslim.com, `www`, `pklbinanusa` dan `datasiswa` (satu aplikasi, folder `/public_html`) dilayani lewat
**Cloudflare paket gratis** — akun yang sama dengan galajuara.sch.id. Hosting, database, cPanel dan file tetap di **Rumahweb**;
Cloudflare hanya "pintu depan" (keamanan + menyimpan file statis supaya hosting tidak terus dibebani).

| Fase | Isi | Status |
|---|---|---|
| F3 | Kode membaca IP asli pengunjung di balik Cloudflare | selesai (di-push 9e8ea85, 9 Nov) |
| F4 | DNS dipindah ke Cloudflare | selesai (9 Nov malam) |
| F5 | Keamanan, cache, awan oranye, ceklis login admin | selesai (10 Nov) |
| F6 | Panduan ini | selesai |

---

## 1. Yang sudah dipasang (catatan pengaturan)

**DNS** (Cloudflare → DNS → Records). Nameserver: `elliot.ns.cloudflare.com` + `evangeline.ns.cloudflare.com`
(diisi di Clientzone Rumahweb → Domain → kangmuslim.com → Nameservers).

| Alamat | Awan | Alasan |
|---|---|---|
| `kangmuslim.com`, `www`, `pklbinanusa`, `datasiswa` | **oranye (Proxied)** | situs web & API aplikasi — lewat Cloudflare |
| `cpanel`, `webmail`, `webdisk`, `whm`, `ftp`, `mail`, `cpcalendars`, `cpcontacts`, `autodiscover`, `autoconfig` | **abu-abu (DNS only)** | cPanel/FTP/email TIDAK boleh lewat Cloudflare |
| MX, SPF, DKIM (`default._domainkey`), DMARC, SRV, `_cpanel-dcv-test-record` | abu-abu | email & verifikasi cPanel |

**SSL/TLS:** mode **Full (strict)** · sertifikat Universal `*.kangmuslim.com` (diperpanjang otomatis oleh Cloudflare) ·
Always Use HTTPS **On** · Minimum TLS **1.2** · TLS 1.3 **On** · Automatic HTTPS Rewrites **On** · HSTS **mati**.
Gembok di hosting (Let's Encrypt, AutoSSL cPanel) tetap dipakai untuk sambungan Cloudflare → hosting.

**Security:** Bot fight mode **Off** · AI Labyrinth **Off** · Email Address Obfuscation **Off** · Rocket Loader (Speed) **Off**.
Custom rule **"Ceklis login admin"**: `(http.request.uri.path contains "/admin/login")` → **Managed Challenge**
(halaman "Verifying you are human" sebelum login admin web; lolos ±30 menit per peramban). Aplikasi Android, form PKL/biodata dan
halaman publik TIDAK kena.

**Caching → Configuration:** Caching Level **Standard** · Browser Cache TTL **Respect Existing Headers**.

**Caching → Cache Rules:**

| Aturan | Expression | Pengaturan |
|---|---|---|
| Aset CSS JS bernomor versi | `(http.request.uri.path contains "/assets/" and http.request.uri.query contains "v=")` | Eligible · Edge TTL abaikan header, 1 bulan · Browser TTL 1 tahun |
| APK aplikasi Android | `(http.request.uri.path contains "/downloads/apk/" and http.request.uri.path contains ".apk")` | Eligible · Edge TTL abaikan header, 1 bulan |

Halaman (admin, login, form, API, unduhan Excel/surat) **tidak pernah disimpan** Cloudflare: alamatnya tidak berakhiran nama file dan
aplikasi mengirim `no-store`. Terbukti saat uji: `cf-cache-status: DYNAMIC`.

---

## 2. Pemakaian sehari-hari

### Deploy kode (git pull) — tidak ada yang berubah
Tetap seperti biasa. **Tidak perlu** bersihkan cache Cloudflare: setiap CSS/JS dimuat dengan nomor versi `?v=` yang ikut berubah saat
file berubah, jadi pengunjung otomatis mendapat versi baru.

### Kapan perlu "Purge Cache" (bersihkan cache)
Hanya bila sebuah file **diganti tetapi namanya sama**:
- **APK diunggah ulang dengan nomor build yang sama** (mis. `muslimin-22.apk` ditimpa) → wajib purge alamat APK itu.
  Bila nomor build naik (`muslimin-23.apk`), tidak perlu apa-apa.
- Gambar di `assets/img/` ditimpa dengan nama sama (jarang) → paling lama 2 jam sudah baru sendiri; purge bila mendesak.
- Logo & foto profil yang diunggah lewat admin selalu diberi nama baru → tidak perlu purge.

Caranya: Cloudflare → kangmuslim.com → **Caching → Configuration → Purge Cache → Custom Purge** → pilih **URL** → tempel alamat
lengkap (mis. `https://kangmuslim.com/downloads/apk/muslimin-22.apk`) → **Purge**. Hindari **Purge Everything** kecuali benar-benar perlu
(semua file harus diambil ulang dari hosting).

### Menambah subdomain baru
Membuat subdomain di cPanel **tidak lagi cukup** — DNS sekarang di Cloudflare. Sesudah membuat subdomain di cPanel:
Cloudflare → **DNS → Records → Add record** → Type `A`, Name `<nama subdomain>`, IPv4 `202.10.43.56`, Proxy **Proxied** bila untuk
situs web (abu-abu bila untuk email/FTP) → Save. Gembok Cloudflare otomatis mencakup subdomain satu tingkat (`*.kangmuslim.com`).

### Mematikan ceklis login sementara
Security → **Security rules** → aturan "Ceklis login admin" → geser ke **Disabled** (nyalakan lagi dengan cara yang sama).

---

## 3. JANGAN dinyalakan / diubah

| Pengaturan | Kenapa |
|---|---|
| **Bot fight mode** | memblokir aplikasi Android (aplikasi tidak bisa mencentang ceklis) |
| **I'm Under Attack Mode** / Security Level "I'm under attack" | semua orang kena ceklis → aplikasi Android & form PKL siswa macet. Hanya untuk serangan nyata, matikan lagi sesudahnya |
| SSL mode **Flexible** atau **Off** | situs berputar-putar terus (aplikasi sudah mengalihkan sendiri ke https) |
| Cache Rule **"Cache everything"** / menyimpan halaman | halaman admin berisi data pribadi bisa tersimpan & terlihat orang lain |
| Custom rule tantangan untuk `/api/` | aplikasi Android gagal login |
| **Rocket Loader**, **Email Address Obfuscation** | menyisipkan kode ke halaman → tombol/menu bisa tidak jalan |
| **HSTS** | sulit dibatalkan bila kelak gembok bermasalah |
| Awan oranye untuk `cpanel`, `mail`, `ftp`, `webmail` | cPanel/FTP/email putus |

Di Rumahweb: tulisan **"NS Hosting: Belum Terkonfigurasi"** di Clientzone = wajar (nameserver memang ke Cloudflare) — **abaikan**,
jangan ikuti sarannya mengembalikan ke ns1–ns4 rumahweb.

PHP: di cPanel → **MultiPHP Manager**, kangmuslim.com memakai **PHP 8.3** (neethub.my.id ikut). Tulisan "PHP 7.4 Deprecation warning" dan
"native (7.4)" di PHP Selector adalah **bawaan server**, bukan versi yang dipakai aplikasi — **jangan diubah**. PHP 8.3 masih menerima
perbaikan keamanan s.d. akhir 2027; pembaruan kecilnya diurus Rumahweb. Pindah ke versi lebih baru nanti = pekerjaan tersendiri (diuji dulu).

---

## 4. Bila ada masalah

| Yang terlihat | Arti | Tindakan |
|---|---|---|
| Halaman Cloudflare **Error 521 / 522 / 523** | hosting mati / tidak menjawab | cek cPanel & status Rumahweb; bukan masalah Cloudflare |
| **Error 525 / 526** | gembok di hosting bermasalah/kedaluwarsa | cPanel → **SSL/TLS Status → Run AutoSSL**. Darurat: Cloudflare → SSL/TLS → Configure → **Full** (bukan strict), kembalikan ke Full (strict) sesudah gembok hosting beres |
| **Error 524** | proses > 100 detik (mis. backup/impor besar) | proses di server biasanya tetap jalan; tunggu lalu muat ulang. Pecah data impor bila sering |
| Unggah file > **100 MB** gagal | batas Cloudflare gratis | unggah lewat cPanel File Manager (alamat cPanel tidak lewat Cloudflare). APK sekarang 91 MB — mepet |
| Aplikasi Android tiba-tiba gagal semua | kemungkinan aturan keamanan memblokir | Security → **Analytics/Events**, cari blokir di `/api/`; matikan aturan penyebabnya |
| Perlu melewati Cloudflare sementara | — | DNS → record `kangmuslim.com` (dan lainnya) → **DNS only** (abu-abu); berlaku dalam menit. Kembalikan ke Proxied sesudahnya |
| Ceklis login admin **berputar terus** | peramban "menyamar": mode tampilan HP di Chrome laptop (Device Toolbar "iPhone …"), ekstensi pengubah User-Agent, mode "Situs desktop", peramban sangat ketat privasi | buka dengan Chrome biasa / HP asli. Untuk cek tampilan HP di laptop: lolos ceklis dulu, lalu Device Toolbar pilih **Responsive** + isi lebar (mis. 390), bukan "iPhone …"; atau matikan aturan sementara (bagian 2) |
| Login staf terkunci padahal tidak salah sandi | IP asli tidak terbaca | lihat Sistem → **Sesi Aktif**: IP harus IP pengguna, bukan 172.x/104.x/162.x. Lihat bagian 5 |

---

## 5. Untuk pengembang

### IP asli pengunjung (F3)
`app/Config/App.php` → `$proxyIPs` berisi 22 rentang IP resmi Cloudflare, header yang dipercaya hanya `CF-Connecting-IP`.
- Header hanya dipercaya bila REMOTE_ADDR memang IP Cloudflare — akses langsung ke IP hosting dengan header palsu tetap tercatat IP aslinya.
- `X-Forwarded-For` sengaja TIDAK dipakai (CodeIgniter mengambil IP pertama, dan IP pertama bisa dipalsukan).
- Tanpa ini: kunci login 30× gagal per IP (LoginThrottle) mengunci SEMUA pengguna, batas percobaan form publik kacau, IP Sesi Aktif/Audit salah.
- `.env` hosting tidak boleh berisi `app.proxyIPs`.

Bila Cloudflare mengubah daftar IP-nya (jarang): samakan `$proxyIPs` dengan https://www.cloudflare.com/ips-v4 dan /ips-v6 **dan**
konstanta `RESMI` di `app/Commands/UjiCloudflareIp.php`, lalu `php spark dev:uji-cloudflare-ip` (harus 0 gagal) dan deploy.

### Cek cepat dari laptop
```
curl -sI https://kangmuslim.com/admin/login          # 403 "Just a moment..." = ceklis aktif
curl -s  https://kangmuslim.com/cdn-cgi/trace         # ip= IP Anda menurut Cloudflare
curl -sI "https://kangmuslim.com/assets/css/admin.css?v=<angka>"   # ke-2 kali: cf-cache-status: HIT
curl -s -A "Dart/3.5 (dart:io)" https://kangmuslim.com/api/v1/app/bootstrap   # 200 JSON (API Android lolos)
```

### Catatan
- LiteSpeed di hosting tidak menjalankan aturan `.htaccess` `E=ASET_BERVERSI` + `Header ... env=` (CSS/JS ber-`?v=` tetap `no-cache`
  dari hosting) — karena itu cache-nya diatur lewat Cache Rule Cloudflare.
- Syarat layanan Cloudflare paket gratis mengarahkan file besar ke layanan khusus (R2). APK 91 MB yang diunduh sesekali umumnya aman;
  bila Cloudflare memberi peringatan, pindahkan APK ke R2 (akun yang sama sudah punya R2 untuk galajuara).
- Akun Cloudflare memakai email guru — sebaiknya aktifkan **verifikasi 2 langkah (2FA)** di Profil Cloudflare, karena akun ini kini
  memegang DNS kangmuslim.com dan galajuara.sch.id.
- `neethub.my.id` (domain tambahan di hosting yang sama) masih memakai DNS Rumahweb — tidak terpengaruh; bisa dipindah dengan cara yang sama bila perlu.
- Uji F3 (laptop, DB salinan hosting 9 Nov): `dev:uji-cloudflare-ip` 18 · `dev:uji-pkl-publik` 36 · `dev:uji-lapor` 127 ·
  `dev:uji-biodata-publik` 22 · `dev:uji-hak` 69 · `dev:uji-sesi` 26 · `dev:uji-api-android` 19 — semua 0 gagal.
