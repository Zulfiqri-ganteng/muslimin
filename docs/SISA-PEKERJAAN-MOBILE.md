# Sisa Pekerjaan yang Menunggu Backend (dari sisi Aplikasi Mobile)

Catatan serah-terima dari sesi pengerjaan **aplikasi Flutter `muslimin`**
(project `C:\flutter-muslimin`), ditulis **2026-09-21** untuk dikerjakan di
repo backend ini.

Semua yang ada di sini **belum dikerjakan** dan **butuh sisi PHP** (migration /
model / controller web / API / routes). Sisi Flutter-nya menunggu ini selesai.

---

## 1. Google Authenticator (TOTP / 2FA) — BELUM ADA APA PUN

Diminta pemilik produk untuk aplikasi mobile. Saat diperiksa 2026-09-21 repo ini
**tidak punya** kolom, controller, maupun route apa pun untuk `totp` / `2fa`.
Aplikasi **tidak boleh** membuat TOTP hanya di sisi klien — verifikasi yang cuma
dilakukan di HP bisa dilewati siapa pun yang memegang endpoint API, jadi itu
keamanan palsu. Karena itu fitur ini ditahan sampai backend siap.

### Yang perlu dibangun

**Basis data**
- Kolom di tabel `admins` (atau tabel terpisah `admin_totp`):
  - `totp_secret` — Base32, **dienkripsi** saat disimpan (jangan plaintext);
  - `totp_enabled` — TINYINT, default 0;
  - `totp_confirmed_at` — DATETIME NULL (baru diisi setelah kode pertama benar).
- Tabel `admin_recovery_codes`: `admin_id`, `code_hash`, `used_at` NULL.
  **Wajib ada** — tanpa ini admin yang kehilangan HP terkunci selamanya dari
  aplikasi dan web.

**Web (paritas dengan mobile — aturan tetap di project ini: web + API + Flutter
dibangun bersamaan)**
- Halaman Pengaturan → Keamanan: tombol aktifkan 2FA, tampilkan **QR**
  (`otpauth://totp/SMK%20Bina%20Nusa:{username}?secret=...&issuer=...`) beserta
  kunci manual, kolom verifikasi kode 6 digit, daftar kode pemulihan sekali
  pakai, dan tombol matikan 2FA (minta sandi ulang).
- Alur login web ikut menanyakan kode bila `totp_enabled`.

**API yang dibutuhkan aplikasi** (nama boleh disesuaikan, tapi tolong tetap
memakai amplop `{status, ok, message, data, meta?}` seperti endpoint lain):

| Metode | Endpoint | Guna |
|---|---|---|
| GET | `/auth/totp/status` | `{enabled, confirmed_at}` untuk layar Keamanan |
| POST | `/auth/totp/setup` | buat secret baru → `{secret, otpauth_url, recovery_codes[]}` |
| POST | `/auth/totp/confirm` | body `{code}` → aktifkan setelah kode pertama benar |
| POST | `/auth/totp/disable` | body `{password}` → matikan |
| POST | `/auth/login` | **ubah**: bila 2FA aktif, jangan langsung beri token — balas `{needs_totp: true, challenge}` |
| POST | `/auth/login/totp` | body `{challenge, code}` → `{token, admin}` |

Catatan penting untuk implementasi:
- TOTP RFC 6238, SHA-1, 6 digit, periode 30 detik, **toleransi ±1 langkah**
  (jam HP sering meleset beberapa detik).
- `challenge` sebaiknya token sementara berumur pendek (mis. 5 menit) yang
  disimpan server, **bukan** admin_id mentah dari klien.
- Beri **throttle** pada `/auth/login/totp` (kode 6 digit mudah ditebak paksa);
  di repo ini sudah ada `LoginThrottle`, pakai itu.
- Kode pemulihan: tampilkan sekali saat dibuat, simpan hash-nya saja, tandai
  `used_at` setelah dipakai.

### Yang akan dikerjakan sisi Flutter setelah ini siap
Layar `KeamananScreen` (sudah ada, kini berisi sakelar sidik jari) tinggal
ditambah bagian 2FA, dan layar login ditambah langkah kode 6 digit. Tidak ada
perubahan lain yang dibutuhkan.

---

## 2. Dua celah kecil API Modul Ujian

Keduanya sudah dilaporkan ke pemilik produk dan sementara **diakali dengan cara
yang aman di klien**, tapi perbaikan bersihnya ada di sini.

### 2a. `GET /admin/ujian/{slug}` tidak mengembalikan `nama`
`App\Controllers\Api\Admin\Ujian::transform()` mengirim `label` (hasil
`UjianPeriodeModel::label()`), tapi **tidak** mengirim `nama` mentahnya —
padahal `simpanPeriode()` SELALU menulis ulang kolom `nama` dari body.
Akibatnya aplikasi tak bisa mengisi nilai awal field "Nama Tampil", dan sekali
simpan dari HP nama yang diketik lewat web bisa terhapus.

**Perbaikan:** tambahkan `'nama' => $r['nama'],` pada `transform()`. Idealnya
`simpanPeriode()` juga hanya menimpa `nama` bila kuncinya memang dikirim
(`array_key_exists('nama', $in)`), supaya klien lama tidak menghapusnya.

Sementara ini aplikasi menebak dengan membandingkan `label` terhadap bentuk
bawaan `JENIS_LABEL . ' — TP ' . tahun_ajaran` (tanda pisah U+2014). **Kalau
format `label()` diubah, beri tahu sisi mobile** — tebakan itu ikut berubah.

### 2b. Status periode belum ada di `/admin/master/options`
`Options::index()` sudah menyediakan `ujian_tingkat`, `ujian_shift`,
`ujian_alasan`, `ujian_status` (status SUSULAN), dan `ujian_peran_pengawas`,
tapi **belum** status PERIODE (`UjianPeriodeModel::STATUS` =
`draft|berjalan|selesai`). Aplikasi terpaksa menulis ketiga nilai itu di kode.

**Perbaikan:** tambahkan tipe `ujian_periode_status` memakai helper `nilai()`
yang sudah ada. Setelah itu daftar di klien akan dihapus.

---

## 3. Yang SUDAH jalan — mohon jangan dihapus

- **Login sidik jari** (`biometric_credentials` + `Api\Auth::biometric*`):
  sejak 2026-09-21 **sudah dipakai aplikasi**. Server hanya menyimpan hash
  device secret; alur ini yang dipakai tombol "Masuk dengan Sidik Jari".
- **Modul Ujian** (`Ujian`, `UjianJadwal`, `UjianSusulan`, `UjianCetak`):
  seluruh 22 endpoint sudah dipakai aplikasi, termasuk cetak PDF/Excel.
  Aplikasi membedakan berkas dari galat lewat `Content-Type`, jadi **balasan
  gagal harus tetap JSON beramplop**, jangan diubah jadi HTML/redirect.

## 4. Hal yang perlu diketahui: aplikasi kini menyimpan cache GET

Sejak 2026-09-21 aplikasi menyimpan balasan **GET yang sukses** dan
menampilkannya kembali saat HP offline (disertai penanda "data tersimpan X jam
lalu"). Dampaknya untuk backend:
- **bentuk amplop jangan diubah tanpa kabar** — perubahan bentuk data membuat
  simpanan lama tak terbaca (aplikasi sudah menolak cache dari versi aplikasi
  berbeda, tapi tetap saja jangan mengubah bentuk diam-diam);
- endpoint yang isinya sangat sensitif waktu sebaiknya tetap apa adanya;
  aplikasi tak pernah menyimpan balasan POST/DELETE.

---

Pertanyaan soal sisi aplikasi, rujuk `BLUEPRINT.md` & `BLUEPRINT-UJIAN.md` di
`C:\flutter-muslimin`.
