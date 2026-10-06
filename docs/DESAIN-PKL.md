# Fitur: PKL / Prakerin Online (binuspkl.kangmuslim.com)

Dokumen kerja. **Centang tiap tahap selesai** agar pekerjaan bisa dilanjutkan di sesi baru
tanpa kehilangan konteks.

Dibuat: 2026-10-06. Basis: repo `muslimin` (CI4 4.7, MySQL/MariaDB, shared hosting Rumahweb,
LIVE di kangmuslim.com). Sekolah: **SMK Bina Nusa** (AKL, MPLB, TKJT). Permintaan berasal dari
operator sekolah yang membantu Waka Hubin; user (pemilik sistem) menyetujui rancangan ringkas ini.

---

## Latar

Sekarang: siswa menulis tempat PKL di kertas → operator mengetik ulang di Word → mengetik lagi di
Excel sebagai "database". Tiga kali kerja untuk satu data, rawan salah ketik, tanpa status, dan
nomor HP siswa cepat usang. **Sasaran: operator tidak mengetik lagi** — siswa mengisi sekali,
staf memeriksa, surat Word keluar otomatis.

Skala (data asli, 2026-09-24): kelas XI = 561 siswa, XII = 432 (AKL 37/38, MPLB 172/121, TKJT
352/273). Perkiraan 100–200 surat per periode (asumsi 2–4 siswa per perusahaan).

## Keputusan user (2026-10-06)

1. **Siswa TANPA akun.** Pola seperti Isian Biodata (form publik → kotak masuk → ACC).
2. **Login staf lewat EMAIL**, satu halaman login untuk semua; menu mengikuti peran.
   Peran: `admin` (akun lama, akses penuh), `operator` (Operator Sekolah), `hubin` (Waka Hubin).
3. **Operator dan Hubin sama-sama bisa**: ACC, kembalikan, ubah data langsung, isi atas nama,
   cetak. Hubin juga cadangan saat operator keteteran.
4. **Alur:** siswa ajukan → ACC → bila salah: staf ubah langsung ATAU dikembalikan agar siswa
   edit → **surat Word** siap cetak untuk perusahaan → sekolah cetak, **hanya Waka Hubin yang
   tanda tangan + stempel basah** (kertas dibiarkan kosong untuk TTD/stempel, tanpa gambar TTD).
5. **Tingkat fleksibel:** kelas XI dan/atau XII boleh mengajukan (diatur di Pengaturan PKL).
   **Siswa yang sudah PKL / sudah punya ajuan aktif TIDAK boleh mengajukan lagi.**
6. **Tanggal PKL beda tiap perusahaan**, tetapi sekolah menetapkan batas (paling awal mulai,
   paling akhir selesai) → fleksibel dengan pagar. Tanggal ikut di form (tidak ada "Tahap 2"
   terpisah).
7. **Nomor surat** berdasar tanggal + urutan surat keluar (format bertoken, bisa diatur).
8. **Output wajib jelas:** siapa yang sudah/belum mengisi dan siapa yang sudah/belum PKL.
9. **Web dulu sampai 100% sempurna; Android ditunda.** Tampilan wajib profesional; wajib teliti
   terhadap kesalahan manusia. Target ±2 hari. Kerja per tahap, berhenti & lapor tiap tahap.
10. Subdomain siswa **binuspkl.kangmuslim.com** (dibuat user di cPanel, document root sama dengan
    domain utama, seperti datasiswa). Panel staf tetap di kangmuslim.com/admin.

Menunggu dari teman user (dikirim 2026-10-07): **contoh surat Word** yang sekarang dipakai dan
**foto kertas isian siswa**. Sampai itu datang, kolom form & isi surat memakai rancangan acuan
profesional di bawah, lalu disesuaikan.

---

## Peran & hak akses (Tahap 1)

Satu sumber kebenaran: `Config\Peran` (daftar peran + awalan alamat yang boleh dibuka) dibaca
`Libraries\HakAkses`. **Deny by default**: alamat di luar daftar ditolak. Dipakai oleh
(a) `AuthFilter` (penjaga rute web), (b) sidebar layout, (c) `ApiAuthFilter`.

| Peran | Alamat yang boleh | Beranda |
|---|---|---|
| admin | semua (`*`) + Kelola Akun | dashboard |
| operator | `admin/pkl`, `biodata`, `master/siswa`, `master/kelas`, `dokumen` | admin/pkl |
| hubin | `admin/pkl` | admin/pkl |
| (semua) | `admin/profile`, `admin/logout` | |

Tambahan di Tahap 1: kolom `admins.aktif` (nonaktifkan tanpa hapus; sesi yang sedang berjalan
langsung terputus karena filter membaca ulang akun tiap permintaan), `wajib_ganti_sandi`
(sandi sementara dari admin harus diganti saat login pertama), `last_login_at`.
**Pintu belakang API:** login & token Android memakai tabel akun yang sama dan dulu tak cek
peran → kini hanya peran `admin` yang boleh memakai API (peran lain 403 dengan pesan jelas)
sampai layar PKL Android ada.

Pengaman kesalahan manusia di Kelola Akun: tak bisa menonaktifkan/mereset/ubah peran diri
sendiri; tak bisa menyisakan nol admin aktif (dikunci `FOR UPDATE`); email unik tanpa beda
huruf besar; HP berisi huruf ditolak (tidak dibuang diam-diam); sandi sementara acak (huruf
tak ambigu, tanpa 0/O/1/l/I) hanya tampil SEKALI dan dialognya tak bisa tertutup tak sengaja;
sandi minimal 8 karakter; rute dilindungi CSRF; semua aksi tercatat di Audit Log (sandi TIDAK
pernah ditulis ke log).

Menambah peran/hak baru nanti cukup menyunting `Config\Peran` (satu berkas): menu, penjaga
rute, dan gembok API ikut otomatis.

---

## Aturan bisnis PKL

**Status ajuan (4):** `menunggu` · `perbaikan` · `disetujui` · `ditolak`.
- Siswa: kirim → `menunggu`. Boleh edit sendiri HANYA saat `perbaikan` (buka ulang dengan
  tanggal lahir yang dulu ia isi, dibatasi LoginThrottle). Setelah `disetujui` terkunci.
- Staf: ACC · Kembalikan (catatan wajib) · Tolak (alasan wajib) · Batalkan persetujuan
  (alasan wajib + konfirmasi; untuk perusahaan yang menarik diri) · Ubah langsung · Isi atas
  nama. Semua tercatat (siapa, kapan, peran).

**Satu siswa satu ajuan aktif.** Aktif = `menunggu`/`perbaikan`/`disetujui`. Dijaga 3 lapis:
(1) daftar nama di form menandai & mengunci siswa yang sudah aktif; (2) validasi server;
(3) **UNIQUE di database** (kolom `siswa_aktif` = siswa_id saat ajuan aktif, NULL bila tidak) —
tahan terhadap dobel-klik dan dua HP sekaligus. Siswa yang riwayat PKL-nya terjadi sebelum
sistem ada diisi operator (atau diimpor dari Excel lama) agar tidak bisa mengajukan lagi.

**Status PKL turunan siswa** (untuk laporan, dihitung otomatis dari ajuan + tanggal, tanpa
input manual): Belum mengisi · Ditolak (perlu ajukan ulang) · Menunggu ACC · Perlu perbaikan ·
Disetujui (belum mulai) · **Sedang PKL** · **Selesai PKL**. Dua angka utama untuk Waka:
*Sudah mengisi / Belum mengisi* dan *Sudah PKL / Belum PKL*.

**Tanggal:** pengaturan sekolah = `mulai_paling_awal`, `selesai_paling_akhir`, durasi min/maks
(hari). Siswa wajib di dalam batas (selesai > mulai). Staf boleh mengubah ke luar batas hanya
dengan konfirmasi eksplisit, dan tercatat.

**Perusahaan:** data diketik siswa di dalam ajuan (ada saran nama dari perusahaan yang pernah
disetujui agar "PT Telkom"/"telkom" tak jadi dua data). Master `pkl_perusahaan` hanya berisi
yang SUDAH disetujui; saat ACC staf diberi peringatan bila ada perusahaan serupa.

**Nomor surat:** format bertoken `{urut}` `{tgl}` `{bln}` `{bln_romawi}` `{thn}`; default
`{urut}/PKL/{tgl}-{bln}-{thn}`. Nomor ditetapkan SEKALI saat surat pertama diterbitkan; cetak
ulang memakai nomor sama; urutan per tahun; "nomor berikutnya" bisa diatur (bila sekolah sudah
memakai nomor berjalan); UNIQUE (tahun, urut) mencegah nomor ganda.

**Surat Word:** template Word milik sekolah (penanda `${...}`, tabel siswa tambah baris
otomatis) lewat pustaka PhpWord; tanpa template unggahan → template bawaan (kop dari
Pengaturan Sekolah, tujuan, tabel siswa, periode, penutup, ruang TTD+stempel, nama & NIP Waka
Hubin dari Pengaturan PKL). Cetak satuan dan massal (banyak surat satu berkas; dicoba dulu,
cadangan ZIP). Bila data berubah setelah surat dicetak → penanda **"perlu cetak ulang"**.

---

## Alur

**Siswa** (HP, tanpa login) — `https://binuspkl.kangmuslim.com`:
1. Pilih kelas → nama → konfirmasi "Benar ini kamu?" (nama yang sudah aktif bertanda & terkunci).
2. Data perusahaan (nama dengan saran, alamat, kota, telepon, kontak/pimpinan + jabatan).
3. Teman satu tempat (opsional, maks. sesuai pengaturan) — hanya siswa yang belum aktif.
4. Periode mulai–selesai + nomor HP terbaru + tanggal lahir (kunci buka ulang).
5. Periksa & kirim → nomor bukti. Status dilihat dari daftar nama (tanpa membuka rahasia).

**Staf** — menu PKL (`/admin/pkl`): beranda ringkasan + antrean; tab Menunggu · Perbaikan ·
Disetujui · Ditolak · Status Siswa; detail ajuan dengan peringatan otomatis (perusahaan mirip,
tanggal di luar batas, anggota bentrok, HP kosong); tombol keputusan; cetak; Excel.

Tampilan beda per peran: **Hubin** = fokus memutuskan (antrean "Menunggu ACC" di depan);
**Operator** = fokus mengerjakan (antrean kerja + isi atas nama + pengaturan + hapus).

---

## Kewaspadaan kesalahan manusia (diuji tiap tahap)

| Kesalahan yang sering terjadi | Pencegahan |
|---|---|
| Salah pilih nama/kelas (memilih teman) | Konfirmasi "Benar ini kamu?" dengan nama + kelas besar |
| Dobel kirim / dua HP sekaligus | Tombol terkunci saat proses + UNIQUE `siswa_aktif` di DB |
| Menambah teman yang sudah PKL/ajuan lain | Ditolak dengan pesan jelas, tanpa membuka detail orang lain |
| Salah ketik HP/telepon/tanggal | Normalisasi & validasi ketat + layar "Periksa & kirim" |
| Nama perusahaan beda tulisan | Saran nama + peringatan perusahaan serupa saat ACC |
| ACC ajuan yang salah | Batalkan persetujuan (alasan wajib) + riwayat |
| Data diubah setelah surat dicetak | Penanda "perlu cetak ulang" |
| Nomor surat ganda / loncat | Ditetapkan sekali + UNIQUE + "nomor berikutnya" |
| Salah beri peran / mengunci diri sendiri | Deskripsi tiap peran, konfirmasi, larangan ubah diri, jaga admin terakhir |
| Staf berhenti tapi akun masih hidup | Nonaktifkan = putus seketika (web & API) |
| Daftar siswa master kotor (dulu ada kelas salinan) | Cek data sebelum link disebar |
| Link disebar sebelum siap | Form default TUTUP + buka/tutup + batas waktu otomatis |

---

## Tahap & status

- [x] **F0 Rancangan** — dokumen ini.
- [x] **T1 Akun & Role — SELESAI 2026-10-06** (belum di-commit/push). Dibangun: migrasi
      `2026-10-06-000001_AddAkunStaf` (aktif, wajib_ganti_sandi, last_login_at; reversibel,
      teruji rollback + juga di DB data asli) · `Config\Peran` + `Libraries\HakAkses` ·
      `AuthFilter` ditulis ulang (baca ulang akun tiap permintaan, wajib ganti sandi, peran per
      alamat, pesan jelas bila ditendang) · login → beranda per peran · sidebar per peran +
      lencana peran di topbar · `Admin\Akun` + `views/admin/akun` + `js/admin/akun.js` (tambah,
      ubah, reset sandi, nonaktifkan/aktifkan; dialog konfirmasi; sandi sementara sekali
      tampil + salin pesan WhatsApp) · gembok API (hanya peran `admin`; nonaktif → 401/403;
      token dicabut saat nonaktif/reset/ganti peran) · beranda penampung `Admin\Pkl` · rute
      Akun dilindungi **CSRF** (pesan galat diindonesiakan: `Language/en/Security.php`) ·
      sandi minimal 8 karakter di Profil web **dan** API · panduan (helpKey `akun`).
      **Uji:** 155 cek e2e lulus (skrip scratchpad `uji_akun.sh`) termasuk matriks akses
      Hubin/Operator, tipuan jalur (`../`, `//`, huruf besar, `%2F`, awalan palsu), POST ke
      area terlarang tak punya efek, nonaktif memutus sesi seketika, gembok API, CSRF; regresi
      54 halaman admin tetap 200; `dev:uji-dokumen` 103/103; foto layar Chrome 1280 & 390px
      (tanpa geser samping). `dev:smoke-views` punya 10 FAIL yang SUDAH ADA sebelum Tahap 1
      (usang terhadap view baru) — bukan regresi.
      **Deploy:** `git pull` → `phpm spark migrate` → login admin → Kelola Akun → buat akun
      Operator & Hubin. Akun lama otomatis tetap `admin` (akses penuh).
- [x] **T2 Database + form siswa — SELESAI 2026-10-07** (lokal; belum di-commit/push/deploy).
      Dibangun: migrasi `2026-10-06-000002_CreatePkl` (pkl_pengaturan baris tunggal — form default
      TUTUP; pkl_perusahaan master; pkl_pengajuan; pkl_anggota dgn UNIQUE `siswa_aktif`;
      pkl_riwayat) · `Config\Pkl` + `App::$allowedHostnames` + `Filters\SubdomainHostFilter`
      (MENGGANTIKAN `BiodataHostFilter`; satu filter melayani datasiswa.* & pkl.*) · rute `/`
      (hostname pkl) + `pkl/*` (cadangan di domain utama) · `Libraries\IsianBantu`, `PklForm`
      (pagar tanggal, lama PKL, telepon/HP ketat — HURUF DITOLAK, nama perusahaan dirapikan),
      `PklAjuan` (transaksi atomik `kirimBaru`/`kirimUlang`/`ubahStatus` + sinkron `siswa_aktif`
      + `periksaKonsistensi`) · model `PklPengaturan/PklPerusahaan/PklPengajuan` · controller publik
      `Pkl` (index, siswa, perusahaan, buka, kirim, selesai) · view `pkl/*` + `layouts/pkl` +
      `assets/js/pkl.js` (wizard 5 langkah: Cari Nama → Perusahaan [saran] → Teman → Waktu &
      Kontak → Periksa & Kirim; draf di HP; perbaikan dibuka dgn tanggal lahir, hanya PENGAJU).
      Pengaman: anti-ganda 3 lapis, honeypot, batas 100 kiriman/IP/10 mnt, throttle buka-ulang
      (5 salah = kunci 15 mnt), penjaga klik ganda.
      **Uji:** `php spark dev:uji-pkl` (162 cek, permanen) · uji HTTP 94 cek termasuk balapan 2
      pengiriman serentak ×10 · E2E Chrome 55 cek (HP 390px & desktop 1280px). Dua temuan nyata
      dari uji, sudah dibetulkan: telepon berhuruf dibuang diam-diam (O↔0) dan klik ganda cepat
      mengirim 3 permintaan. Skrip uji HTTP/E2E ada di folder sementara (tidak di repo).
      **Catatan:** form default TUTUP dan layar Pengaturan baru ada di T3 → sebelum T3, form hanya
      bisa dibuka lewat SQL. Foto layar baru 1 dari ±25 yg diperiksa mata.
      **Deploy (bersama T1):** `git pull` → `phpm spark migrate` (2 migrasi: T1 + T2) → buat
      subdomain binuspkl.kangmuslim.com di cPanel (document root sama) → pastikan Biodata masih normal.
- [x] **T3 Kotak masuk staf + Status Siswa + Pengaturan — SELESAI 2026-10-07** (lokal; tanpa
      migrasi baru, memakai tabel T2). Dibangun: `Admin\Pkl` (beranda, kotak masuk per status,
      detail, ACC / kembalikan / tolak / batalkan persetujuan, isi atas nama, ubah langsung, hapus,
      Status Siswa, Pengaturan, JSON pemilih siswa) · view `admin/pkl/{index,daftar,detail,form,
      siswa,pengaturan,_nav,_lencana}` + `assets/js/admin/pkl.js` · menu samping (Beranda, Kotak
      Masuk, Status Siswa, Isi atas Nama, Pengaturan; opsi "persis" agar Beranda tak ikut menyala) ·
      `Libraries\PklPeringatan` (perusahaan mirip/ganda, tanggal di luar pagar, HP kosong, tanggal
      lahir ≠ Master Siswa, siswa tak aktif, kelas pindah …; tingkat bahaya/awas/info) ·
      `PklAjuan::ubahStatus` kini menautkan perusahaan ke master saat ACC (nama pembanding sama →
      pakai yang ada; mirip → staf memilih; selain itu buat baru) + `ubahIsi()` + `kirimBaru` bisa
      langsung disetujui · `PklPengajuanModel` (hitungStatus, daftar, detail, riwayat, ajuanSerupa,
      statusSiswa/ringkasanSiswa = status turunan dari ajuan + tanggal hari ini).
      **Hak akses:** `Config\Peran` + `HakAkses` punya daftar `kecuali`: Hubin boleh semua di
      `admin/pkl` KECUALI `admin/pkl/pengaturan` & `admin/pkl/hapus` (ditolak 403, menu & tombol
      ikut tersembunyi). Semua aksi POST + CSRF.
      **Pengaman:** kembalikan/tolak/batal-ACC wajib beralasan (≥5 huruf); ACC dengan peringatan
      BAHAYA butuh centang "sudah saya periksa"; tanggal di luar pagar hanya lewat centang & tercatat;
      hapus ajuan disetujui butuh konfirmasi; pengaju tak bisa diganti; Pengaturan divalidasi
      (rentang ≥ lama minimal, tanggal akhir belum lewat, waktu tutup ke depan, dst.).
      **Uji:** `php spark dev:uji-pkl` kini 214 cek · uji HTTP staf 84 cek (3 peran, CSRF, semua
      keputusan, hak akses, XSS, pengaturan, hapus) · foto Chrome desktop 1280 & HP 390 (tanpa geser
      samping, tanpa galat JS). Skrip HTTP/foto ada di folder sementara (tidak di repo).
      **Definisi "Sudah PKL"** = ajuan sudah disetujui (dirinci belum mulai / sedang / selesai
      menurut tanggal); "Sudah mengisi" = punya ajuan aktif.
- [ ] **T4 Surat Word + nomor + rekap** — PhpWord · template bawaan & unggahan · nomor surat ·
      cetak satuan/massal · "perlu cetak ulang" · Excel status siswa · impor riwayat PKL lama.
- [ ] **T5 Uji menyeluruh + panduan + pasang** — e2e Chrome (subdomain) · regresi halaman admin ·
      help card semua halaman · langkah deploy (`git pull`, `phpm spark migrate`, subdomain).

**Sengaja belum dikerjakan:** Android (setelah web 100%), jurnal/absensi/nilai PKL, notifikasi
otomatis, ACC dua tingkat, akun siswa/guru.
