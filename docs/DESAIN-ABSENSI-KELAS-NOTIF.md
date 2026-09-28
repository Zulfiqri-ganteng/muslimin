# Rencana Fitur: Absensi per Kelas + Notifikasi Jadwal Guru

Dokumen kerja step-by-step. **Centang tiap tahap selesai** agar pekerjaan bisa
dilanjutkan di sesi baru tanpa kehilangan konteks.

Dibuat: 2026-09-28. Basis: repo `muslimin` (CI4, MySQL, shared hosting
Rumahweb, LIVE di kangmuslim.com) + aplikasi Android `C:\flutter-muslimin`
(Flutter 3.44.2, AGP 9.0.1, Gradle 9.1, paket `com.kangmuslim.muslimin_app`).

Aturan kerja: **1 tahap → lapor → berhenti tunggu aba-aba.** Build APK hanya
dengan izin user. Claude TIDAK commit (user sendiri).

---

## Latar

Dua permintaan klien:

1. **Input Absensi (Android: sidebar Absensi Guru → Input Absensi)** — daftar
   sekarang berupa kartu per NAMA GURU (A–Z). Klien ingin melihat **per KELAS**
   (mis. "XII TKJ 2 — jam ke 1 …"), baru di dalamnya nama guru. Tombol
   **Kirim WA** juga harus berformat kelas: `nama guru kelas X jam ke N`,
   **tanpa mapel**, urut kelas A–Z (TKJ 1 … 10 berurutan benar).
2. **Notifikasi di HP klien** saat guru dijadwalkan masuk kelas. Fleksibel:
   klien memilih **hari apa** dan **guru siapa** yang dinotifkan.

---

## Keputusan user (2026-09-28)

1. **Notifikasi pakai Firebase Cloud Messaging (FCM)** + penjadwal di server
   (cron). User sudah punya akun Firebase (dipakai project galajuara).
2. **Pesan WA per kelas.** Daftar hadir / belum hadir / tidak hadir semuanya
   per kelas; jam berurutan digabung (`jam ke 1-3`). Guru piket & staf TU
   tetap per nama (mereka tidak punya kelas).
3. **Nama di WA TETAP seperti sekarang:** tag `@62…` bila guru punya No. WA,
   selain itu **nama tanpa gelar** (`AbsensiWa::tag()`, tidak diubah). User
   akan melengkapi No. WA guru manual lewat admin.
4. **Tampilan per kelas di WEB dan ANDROID.**
5. **Notif otomatis diam pada hari ujian.**
6. **Isi notif TANPA mapel** — cukup nama guru, kelas, jam ke berapa.
7. Foto contoh layar Android sudah diterima (daftar kartu guru, tombol
   KBM Pagi/Siang, banner "Sudah diabsen", ringkasan 6 angka, tombol bawah
   Kirim WA + Simpan). Tampilan baru mempertahankan semua elemen itu.

---

## Temuan analisis (fakta kode & data)

| # | Temuan | Dampak |
|---|---|---|
| T1 | Data sesi (`JadwalModel::sessionsForHari`) sudah membawa `kelas_id`, `nama_kelas`, `jam_ke`, `jam_shift`, waktu. | Fitur 1 **tanpa tabel baru**. |
| T2 | Sumber tunggal data input = `App\Libraries\AbsensiHarian::muat()` (dipakai web & API). Pesan WA disusun server di `App\Libraries\AbsensiWa`. | Ubah 1 tempat → web & Android sama. |
| T3 | Urutan teks biasa SALAH: MySQL/`strcmp` → `X TKJ 1, X TKJ 10, X TKJ 2`. `strnatcasecmp()` PHP JUGA salah: mengabaikan spasi → "XI AKL" jatuh di antara "X AKL" & "X TKJ". | Fungsi sendiri `KelasModel::bandingNatural()` (potongan angka/teks). Server kirim `kelas_urut` agar Android tak perlu menebak (fallback Dart `_bandingNatural` = cermin). |
| T4 | Data asli: 42 kelas (21 pagi: X + XII AKL/MPLB/TKJ 7; 21 siang: XI + XII TKJ 1–6), 8 jam per shift, ±168 sesi & 26–40 guru per shift per hari. | Android: 21 kartu per shift → perlu filter jurusan & cari. |
| T5 | Jurusan dobel: kelas TKJ memakai jurusan id 4 kode **"TKJT"** (nama pendek), sedang id 5 "TJKT – Teknik Jaringan Komputer dan Telekomunikasi" tidak dipakai kelas mana pun. | Label chip filter = kode jurusan kelas ("TKJT"). Saran ke user: rapikan di Master Jurusan (edit data, bukan kode). |
| T6 | Web input menyimpan dengan membaca elemen `.absen-row` di layar (`isiJson()`). | Tampilan per kelas di web = **render ulang server** (`?tampilan=kelas`) dengan elemen `.absen-row` yang SAMA → logika simpan tidak berubah. |
| T7 | `sessionsForHari` dipakai juga oleh Publik, AbsensiLaporan, AbsensiSnapshot. Snapshot hanya mengambil kolom tertentu. | Menambah kolom (tingkat, jurusan) di SELECT **aman**. |
| T8 | Belum ada Firebase/paket notifikasi/cron di mana pun. | Semua dibangun baru (Bagian B). |
| T9 | CLI hosting: `php` = 7.4 (mati diam), `phpm` = alias PHP 8.3. **Alias tidak berlaku di cron.** | Cron wajib pakai path lengkap PHP 8.3 (cek dengan `alias phpm`). |
| T10 | Hosting MySQL ber-`ONLY_FULL_GROUP_BY`. | Query mesin notif tanpa GROUP BY longgar. |
| T11 | Repo `muslimin` PUBLIK (lihat insiden dump). Repo `flutter-muslimin` di GitHub. | Kunci Firebase **tidak boleh** masuk kedua repo. |
| T12 | Token API: idle 60 menit → auto-logout di HP. | Pendaftaran HP untuk notif **tidak** terikat token login: notif tetap datang walau sesi habis; hanya **Keluar** manual yang mencabutnya. |
| T13 | Ujian: `ujian_periode.tanggal_mulai/selesai` (bisa NULL) + `ujian_jadwal.tanggal`. | "Hari ujian" = masuk rentang periode ATAU ada jadwal ujian pada tanggal itu. |

---

## BAGIAN A — Absensi per Kelas (web + Android + pesan WA)

### A1. Urutan kelas (natural)

Satu fungsi di server: `KelasModel::bandingNatural()` / `urutNatural()`. Hasil pada data asli:

- Pagi: X AKL → X MPLB 1…5 → X TKJ 1 → X TKJ 2 … X TKJ 10 → XII AKL →
  XII MPLB 1…3 → XII TKJ 7
- Siang: XI AKL → XI MPLB 1…5 → XI TKJ 1…9 → XII TKJ 1…6

(`"X "` < `"XI"` < `"XII"` karena spasi < huruf — X selalu sebelum XI.)

### A2. Tambahan data API (non-breaking, aplikasi lama tetap jalan)

`GET /api/v1/admin/absensi?tanggal=` ditambah:

- tiap `guru[].sesi[]`: `tingkat`, `jurusan` (kode jurusan kelas), `kelas_urut` (int).
- top-level `kelas`: `[{kelas_id, nama, tingkat, jurusan, shift, urut}]`
  (sudah urut natural; hanya kelas yang punya sesi hari itu).

Sumber: `sessionsForHari` ditambah `kelas.tingkat, kelas.jurusan_id,
jurusan.kode AS jurusan_kode` (LEFT JOIN jurusan). Web memakai data yang sama.

### A3. Pesan WhatsApp per kelas (`AbsensiWa::data()` dirombak)

**Baris kelas** (untuk guru yang MENGAJAR di shift itu). Tiap sesi shift ini
diberi kategori:

1. status `izin/sakit/alpa` → **tidak hadir**
2. guru ada di daftar belum hadir shift ini → **belum hadir**
3. selain itu (hadir/telat) → **hadir**

Sesi berurutan (`jam_ke` berselisih 1) dengan **guru + kelas + kategori** sama
digabung menjadi satu baris:

```
{tag} kelas {nama_kelas} jam ke {a}            ← satu jam
{tag} kelas {nama_kelas} jam ke {a}-{b}        ← gabungan
```

Akhiran:
- hadir normal → tanpa akhiran (sesuai format klien)
- hadir telat → ` (terlambat, masuk 07:20)` (jam masuk paling awal di blok)
- tidak hadir → ` - izin` / ` - sakit` / ` - tidak hadir` + ` (keterangan)` bila ada
- belum hadir → tanpa akhiran

Urut: **kelas natural → jam awal**. Guru yang mengajar di 3 kelas muncul di
3 baris (memang itu tujuannya: terlihat kelas mana yang kosong).

**Baris orang** (tanpa kelas, `{tag}` + akhiran seperti sekarang):
- `{daftar_piket}` — guru piket shift ini yang hadir (per nama, seperti sekarang).
- `{daftar_staf}` — kehadiran kerja yang berlaku di shift ini & hadir.
- Guru piket / staf yang TIDAK mengajar tetapi ditandai belum hadir atau
  izin/sakit/alpa → ditambahkan di ekor `{daftar_belum_hadir}` /
  `{daftar_tidak_hadir}` (setelah baris kelas, urut nama).

Aturan tambahan:
- Guru piket yang juga mengajar: kelasnya tetap tercantum di daftar kelas
  **dan** namanya tetap di daftar piket (dulu: hanya di piket). Alasannya: di
  format per kelas setiap kelas yang berjalan harus tercantum.
- `{jumlah_hadir}`, `{jumlah_belum_hadir}`, `{jumlah_tidak_hadir}` =
  jumlah **ORANG** berbeda (bukan jumlah baris).
- Placeholder TIDAK berganti nama → template buatan admin di hosting otomatis
  ikut format baru. Teks penjelasan `PLACEHOLDERS` diperbarui.
- Guru ganda (`induk_id`) digabung & `ikut_absensi = 0` dibuang — sama seperti
  sekarang (pakai `AbsensiHarian::muat`).

Contoh hasil (KBM pagi):

```
Berikut daftar Bapak dan Ibu guru yang sudah hadir:
1. @6281234567890 kelas X AKL jam ke 1-2
2. Muslimin kelas X TKJ 1 jam ke 1-3
3. Beni Akbar kelas X TKJ 2 jam ke 1-2 (terlambat, masuk 07:20)
4. @6285712345678 kelas X TKJ 10 jam ke 5-8

Yang belum hadir:
1. Napis Kuturupi kelas X TKJ 3 jam ke 1-2

Izin / sakit / tidak hadir:
1. Ferina Salsabilla Ayu kelas X TKJ 5 jam ke 1-4 - izin (acara keluarga)
```

Panjang pesan: ±60–80 baris per shift. Android & web: bila WhatsApp gagal
dibuka lewat link, dialog cadangan menyediakan **Salin** + **Bagikan**
(Android: `share_plus`, sudah terpasang).

### A4. Android — tampilan Per Kelas (`absensi_admin_screen.dart`)

Tetap: AppBar + 3 ikon, bar tanggal, tombol KBM Pagi/Siang, banner "Sudah
diabsen", ringkasan 6 angka, panel Belum Hadir, Kehadiran Kerja, tombol
bawah **Kirim WA** & **Simpan**.

Baru:
- Di bawah judul "Absensi Mengajar — KBM {shift}": pilihan
  **[Per Kelas] [Per Guru]** (default Per Kelas; pilihan diingat di HP).
- Baris filter: chip `Semua · {kode jurusan…}` (dari `kelas[]` shift ini) +
  ikon cari (nama kelas / nama guru).
- **Kartu kelas** (urut `kelas_urut`):
  - Kepala: `XII TKJ 2` + chip jurusan + ringkas kanan: `✓ Lengkap` (hijau)
    atau `⚠ 1 belum hadir` / `⚠ 1 izin` (merah/kuning). Kartu bermasalah
    berbingkai merah.
  - Baris per blok guru: `Jam 1–2 · 07:00–08:10 · Nama Guru · [chip status]`.
- Ketuk baris → bottom sheet: nama guru + kelas + jam; daftar sesi blok itu
  (ketuk sesi = ubah status, **pakai ulang** `_editSesi`/`_StatusSheet`);
  tombol **Tandai Belum Hadir / Sudah Datang** (per guru per shift, sama
  dengan kartu guru); tautan "Semua sesi guru ini" → `_showGuruDetail`.
- Status & perubahan lokal memakai `_edits` yang SAMA dengan tampilan per
  guru → pindah tampilan tidak menghilangkan perubahan yang belum disimpan.
- Tampilan **Per Guru** = persis seperti sekarang.

### A5. Web — tampilan Per Kelas (`app/Views/admin/absensi/index.php`)

- Parameter `?tampilan=kelas|guru` (default `kelas`). Tombol pilihan di atas
  daftar. Server merender daftar sesuai pilihan.
- Tiap sesi tetap satu elemen `.absen-row` dengan atribut `data-*` yang sama
  → `isiJson()`, `stats()`, "Sudah datang" (event `absen-datang` per `gid`)
  tetap bekerja tanpa diubah.
- Kartu kelas: kepala nama kelas + jurusan + jumlah sesi; baris sesi: jam,
  guru, dropdown status, jam masuk & keterangan (bila bukan hadir), tombol
  kecil **Belum hadir / Sudah datang** per guru.
- Ganti tampilan saat ada perubahan belum disimpan → konfirmasi "Perubahan
  belum disimpan akan hilang".
- `save()` redirect membawa `tampilan` agar kembali ke tampilan yang sama.
- Filter jurusan + cari (Alpine, sisi klien, menyembunyikan kartu).

---

## BAGIAN B — Notifikasi Jadwal Guru (Firebase)

### B1. Arsitektur

```
cPanel cron (tiap menit)
  └─ PHP 8.3 spark notif:kirim
       ├─ baca aturan klien + jadwal hari ini + absensi (untuk ⚠ kelas kosong)
       ├─ hitung "guru masuk kelas" yang waktunya tiba
       ├─ klaim slot (anti dobel) di notif_log
       └─ App\Libraries\Fcm
            ├─ JWT RS256 (ditandatangani kunci service account)
            ├─ tukar ke access token Google (cache 50 menit)
            └─ POST FCM HTTP v1 → HP klien (tiap token perangkat)
HP (firebase_messaging) → notif tampil → diketuk → buka Input Absensi
```

**JWT hanya dipakai server untuk minta izin ke Google.** Login aplikasi tetap
token Bearer yang sekarang.

### B2. Tabel baru (4)

1. `notif_perangkat` — HP penerima.
   `id, admin_id, device_id (varchar 64), token (text), token_hash (char 64,
   UNIQUE), nama_perangkat, terima (tinyint 1), last_seen_at, created_at,
   updated_at`. UNIQUE `(admin_id, device_id)`.
2. `notif_aturan` — aturan klien (boleh banyak).
   `id, admin_id, nama, hari (JSON id hari), guru (JSON id guru ORANG; kosong =
   semua), jurusan (JSON id jurusan; kosong = semua), menit_sebelum (0/5/10/15/30),
   aktif, created_at, updated_at`.
3. `notif_pengaturan` — saklar per admin.
   `admin_id (PK), aktif, jeda_sampai (date NULL), diam_saat_ujian (default 1),
   updated_at`.
4. `notif_log` — riwayat & anti dobel.
   `id, admin_id, jenis (jadwal/uji), kunci (varchar 100, UNIQUE), tanggal,
   slot (time), judul, isi (text), data (JSON), jml_perangkat, berhasil, gagal,
   galat (text NULL), created_at`.

### B3. Mesin penjadwal (`spark notif:kirim`)

Untuk tiap admin yang punya perangkat `terima = 1` & `notif_pengaturan.aktif = 1`:

1. **Diam** bila: `jeda_sampai >= hari ini`; hari tidak aktif (`hari.aktif`);
   `diam_saat_ujian = 1` dan hari ini hari ujian (T13).
2. Sesi hari ini (`sessionsForHari`) → petakan guru ke ORANG
   (`GuruModel::petaOrang`) → bentuk **blok masuk kelas**: sesi berurutan
   (`jam_ke` +1) guru+kelas sama = satu blok; waktu masuk = `waktu_mulai`
   sesi pertama. Notif hanya saat MASUK (awal blok), bukan tiap jam.
3. Cocokkan tiap blok ke aturan aktif: hari ∈ `hari`, guru ∈ `guru` (atau
   semua), jurusan kelas ∈ `jurusan` (atau semua). Blok cocok >1 aturan →
   dipakai `menit_sebelum` terbesar. `kirim_pada = waktu masuk − menit_sebelum`.
4. Ambil blok dengan `kirim_pada` ∈ (sekarang − 10 menit, sekarang]
   (toleransi bila cron telat). Kelompokkan per `kirim_pada` (HH:MM).
5. **Klaim** tiap kelompok: INSERT `notif_log` dengan
   `kunci = jadwal:{admin}:{tanggal}:{HH:MM}` (UNIQUE). Gagal insert = sudah
   dikirim → lewati. Mencegah dobel walau cron tumpang tindih.
6. Periksa absensi tanggal itu: sesi `izin/sakit/alpa` atau guru di daftar
   belum hadir shift → baris diberi **⚠** (kelas kosong).
7. Kirim ke semua token perangkat admin itu → isi `berhasil/gagal`. Token
   `UNREGISTERED`/`INVALID_ARGUMENT` → perangkat dihapus.

### B4. Isi notifikasi (tanpa mapel, nama tanpa gelar)

Satu guru:
```
Judul : Muslimin masuk X TKJ 1
Isi   : Jam ke 1-3 · 07:00 · KBM pagi
```
Beberapa guru pada waktu yang sama (satu notif, bukan berbunyi berkali-kali):
```
Judul : Jadwal masuk 07:00 · KBM pagi · 3 guru
Isi   : Muslimin → X TKJ 1 · jam ke 1-3
        Beni Akbar → X TKJ 2 · jam ke 1-2
        ⚠ Napis Kuturupi → X TKJ 3 · jam ke 1-2 (belum hadir)
```
Urut baris: kelas natural. Data tambahan (untuk ketuk): `jenis=jadwal,
tanggal, shift`. Kanal Android: `jadwal_guru` (penting, bersuara).

### B5. Pengirim FCM (`App\Libraries\Fcm`)

- Kunci: berkas JSON service account, path di `.env` → `FCM_KEY_PATH`.
  Lokal: `C:\xampp\rahasia\firebase-muslimin.json`. Hosting:
  `/home/kank9494/rahasia/firebase-muslimin.json` (di LUAR folder repo,
  izin 600). `project_id` dibaca dari berkas itu.
- JWT: header `{alg:RS256,typ:JWT}`, klaim `iss=client_email,
  scope=https://www.googleapis.com/auth/firebase.messaging,
  aud=https://oauth2.googleapis.com/token, iat, exp=iat+3600`; ditandatangani
  `openssl_sign(SHA256)`. Tanpa pustaka Composer baru.
- Access token di-cache 50 menit (cache CI4).
- Kirim: `POST https://fcm.googleapis.com/v1/projects/{id}/messages:send`
  per token, `android.priority = high`, `android.notification.channel_id =
  jadwal_guru`.

### B6. Cron hosting

```
* * * * * /PATH/PHP83 /home/kank9494/kangmuslim/spark notif:kirim >/dev/null 2>&1
```
`/PATH/PHP83` = hasil `alias phpm` di Terminal cPanel. Bila hosting tak
mengizinkan tiap menit → tiap 5 menit tetap jalan (toleransi 10 menit di B3),
tapi notif bisa telat s/d 5 menit.

### B7. Endpoint API (`/api/v1`, Bearer, milik admin yang login saja)

| Metode | Path | Guna |
|---|---|---|
| GET | `admin/notif` | ringkasan: pengaturan, jumlah aturan, perangkat ini terdaftar? |
| GET | `admin/notif/opsi` | pilihan hari, guru (orang), jurusan, menit |
| POST | `admin/notif/pengaturan` | `{aktif, jeda_sampai, diam_saat_ujian}` |
| GET | `admin/notif/aturan` | daftar aturan |
| POST | `admin/notif/aturan` | buat aturan |
| POST | `admin/notif/aturan/{id}` | ubah aturan |
| DELETE | `admin/notif/aturan/{id}` | hapus aturan |
| POST | `admin/notif/perangkat` | daftar/perbarui token HP `{device_id, token, nama_perangkat}` |
| POST | `admin/notif/perangkat/terima` | `{device_id, terima}` saklar HP ini |
| POST | `admin/notif/perangkat/hapus` | `{device_id}` — dipanggil saat Keluar |
| GET | `admin/notif/pratinjau?tanggal=` | daftar notif yang AKAN terkirim + alasan bila diam |
| POST | `admin/notif/uji` | `{device_id}` kirim notif uji ke HP ini |
| GET | `admin/notif/riwayat?page=` | riwayat notif terkirim |

### B8. Android

- Paket: `firebase_core ^3.15.2` + `firebase_messaging ^15.2.10` — versi SAMA
  dengan flutter-galajuara yang terbukti build di AGP 9.0.1 + Gradle 9.1 mesin
  ini. TANPA `flutter_local_notifications`: kanal `jadwal_guru` dibuat natif di
  `MainActivity.kt`; notif saat app terbuka → SnackBar (main.dart).
- Plugin Gradle `com.google.gms.google-services` 4.4.2 (= galajuara), dipasang
  di `app/build.gradle.kts` **hanya bila** `android/app/google-services.json`
  ada → tanpa berkas, APK tetap ter-build & menu notif tampil "belum aktif".
  Satu berkas cukup untuk flavor `selfhost` & `play` (applicationId sama).
  Berkas **masuk `.gitignore`**; salinan aman di `C:\xampp\rahasia\`.
- Izin `POST_NOTIFICATIONS` (dibawa plugin) diminta saat pengguna menyalakan
  "Terima notifikasi di HP ini" — OPT-IN per HP, bukan otomatis saat login.
- Opt-in → `POST admin/notif/perangkat` (`device_id` = milik `biometrik.dart`,
  kunci `muslimin_device_id`); token diperbarui tiap AdminShell dibuka &
  saat `onTokenRefresh`. Layanan: `lib/services/notif.dart` (NotifService).
- **Keluar** manual → `POST admin/notif/perangkat/hapus` SEBELUM token login
  dihapus (hook `Api.sebelumKeluar`). Auto-logout (401) TIDAK mencabut (T12).
- Notif diketuk → buka Input Absensi di `tanggal` & `shift` dari data notif
  (login dulu bila sesi habis).
- Layar **Pengaturan Notifikasi** (dari sidebar/profil): saklar utama, saklar
  "Terima di HP ini", jeda sampai tanggal, diam saat ujian, daftar aturan
  (tambah/ubah/hapus: nama, chip hari, pilih guru dengan cari / "semua",
  chip jurusan, menit sebelum, aktif), tombol **Kirim notif uji**,
  **Pratinjau** (hari ini/besok), **Riwayat**, dan panduan singkat
  "izinkan Autostart / tanpa batasan baterai" (Xiaomi/Oppo/Vivo).

### B9. Keamanan

- Berkas service account **tidak pernah** masuk repo / chat / folder htdocs.
- `google-services.json` masuk `.gitignore` repo flutter.
- Semua endpoint notif hanya menyentuh data `admin_id` milik token.
- Isi notif hanya nama guru, kelas, jam (tanpa data pribadi lain).

### B10. Panduan Firebase untuk user (tahap N0)

Pakai **akun Google yang sama**, tetapi buat **PROJECT BARU** (jangan pakai
project "Siakad Sekolah" milik galajuara): kunci terpisah → bila satu bocor
yang lain aman, kuota & pengaturan tidak saling ganggu. Tetap paket **Spark
(gratis)** — jangan klik Upgrade/Blaze.

1. console.firebase.google.com → **Create a new Firebase project**.
2. Nama project: `Muslimin Kangmuslim` → centang persetujuan → Continue.
3. (Bila ditanya AI assistance/Gemini) boleh dimatikan → Continue.
4. **Google Analytics → matikan** → **Create project** → tunggu → Continue.
5. Di beranda project klik ikon **Android** (atau *Add app → Android*):
   - Android package name: `com.kangmuslim.muslimin_app` (PERSIS).
   - App nickname: `Muslimin Admin`. SHA-1: kosongkan.
   - **Register app** → **Download google-services.json**.
   - Langkah "Add Firebase SDK" dst → cukup **Next / Continue to console**
     (bagian kode dikerjakan Claude).
6. Ikon gerigi ⚙ → **Project settings** → tab **Service accounts** →
   **Generate new private key** → **Generate key** → berkas `.json` terunduh.
7. Buat folder `C:\xampp\rahasia\` → pindahkan KEDUA berkas ke sana:
   - service account → ganti nama `firebase-muslimin.json`
   - `google-services.json` (nama tetap)
   JANGAN taruh di `C:\xampp\htdocs\muslimin`, JANGAN kirim isinya ke chat.
8. Project settings → tab **Cloud Messaging** → pastikan
   **Firebase Cloud Messaging API (V1): Enabled**. (Legacy API abaikan.)
9. Kabari Claude: **Project ID** (Project settings → General, mis.
   `muslimin-kangmuslim-xxxxx`; bukan rahasia).
10. Hosting: baris cron + path PHP 8.3 dicetak otomatis oleh
    `phpm spark notif:cek` (lihat Deploy tahap 2). Menu **Cron Jobs** harus
    punya pilihan *Once Per Minute*.

---

## Daftar tahap

Status 2026-09-28: user minta SEMUA tahap dikerjakan sekaligus; Firebase
dibuat user SETELAH build. Kode selesai & teruji lokal; sisa = langkah user.

### Fitur 1 — Absensi per kelas
- [x] **F0** Dokumen desain ini + kunci keputusan.
- [x] **F1** Server: `sessionsForHari` + tingkat/jurusan; `KelasModel::bandingNatural`;
      API `admin/absensi` + `kelas[]` & field sesi; `AbsensiWa::data()` per kelas.
- [x] **F2** Android: `absensi_kelas_view.dart` (part) + tombol Bagikan cadangan WA.
- [x] **F3** Web: `?tampilan=kelas|guru` (diingat di sesi), partial `_sesi_row.php`,
      filter jurusan + cari, konfirmasi bila ada perubahan belum disimpan. CSS di-build ulang.
- [ ] **F4** Deploy hosting + build APK (**izin user**) + cek web ↔ Android di HP.

### Fitur 2 — Notifikasi
- [ ] **N0** (user) Firebase + 2 berkas kunci + info cron/PHP (B10) — SETELAH build fitur 1.
- [x] **N1** Migrasi `2026-09-28-000001_CreateNotifJadwal` (4 tabel) + 4 model.
- [x] **N2** `App\Libraries\Fcm` (JWT RS256 → access token → FCM v1).
- [x] **N3** `NotifJadwal` + `spark notif:kirim [--sekarang "…"] [--kering]`.
- [x] **N4** `Api\Admin\Notif` — 13 endpoint, teruji e2e lewat HTTP.
- [x] **N5** Android: Firebase bersyarat, kanal natif, NotifService, ketuk → Input Absensi.
- [x] **N6** Android: `notif/notif_screen.dart` (+ Pratinjau, Riwayat) & `notif_aturan_form.dart`;
      menu sidebar "Absensi Guru → Notifikasi Jadwal".
- [x] **N7** Uji lokal: `dev:uji-absensi-kelas` 30/30, `dev:uji-notif` 46/46 (termasuk coba ulang bila semua HP gagal),
      `flutter analyze` bersih, `flutter test` 109/109.
- [ ] **N8** Deploy notif (berkas kunci + `.env` + cron) + taruh `google-services.json`
      + build APK (**izin user**) + tombol "Kirim notif uji" di HP klien.
- [ ] **N9** (opsional) Halaman web pengaturan notif — belum dikerjakan.

### Uji regresi (jalankan tiap modul disentuh)
```
php spark dev:uji-absensi-kelas --db muslimin_asli
php spark dev:uji-notif --db muslimin_asli
php spark notif:kirim --sekarang "2026-09-29 06:55" --kering
```
(`--db` memakai salinan DB asli; data uji dibuat & dihapus sendiri.)

## Deploy

**Tahap 1 — Fitur 1 + tabel notif (aman walau Firebase belum ada):**
```
cd ~/kangmuslim && git pull origin main && phpm spark migrate && phpm spark cache:clear
```
Migrasi hanya MENAMBAH 4 tabel notif. Lalu build APK (izin user) → rilis.

**Tahap 2 — setelah user membuat Firebase (B10):**
1. Unggah `firebase-muslimin.json` ke `/home/kank9494/rahasia/` (File Manager
   cPanel, di LUAR `kangmuslim`), izin 600.
2. `.env` hosting: tambah `FCM_KEY_PATH = /home/kank9494/rahasia/firebase-muslimin.json`
3. Terminal cPanel: `cd ~/kangmuslim && phpm spark notif:cek` → semua [OK] +
   mencetak BARIS CRON lengkap (path PHP 8.3 diambil otomatis).
4. cPanel → Cron Jobs → Once Per Minute → tempel baris cron dari langkah 3.
5. Salin `google-services.json` ke `C:\flutter-muslimin\android\app\` → build APK
   (izin user) → rilis.
6. HP klien: menu Notifikasi Jadwal → nyalakan "Terima notifikasi di HP ini" →
   "Kirim notif uji" → buat aturan.
7. Uji dari server (tanpa buka aplikasi): `phpm spark notif:uji` (notif uji) atau
   `phpm spark notif:uji --contoh` (contoh notif JADWAL asli dari aturan; dicatat
   jenis "uji" sehingga jadwal sungguhan tidak terganggu).
