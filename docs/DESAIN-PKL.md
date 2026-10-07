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
| hubin | `admin/pkl` — KECUALI `admin/pkl/pengaturan` & `admin/pkl/hapus`. **Tidak boleh** Isian Biodata Siswa & Master Data (keputusan user 2026-10-07) | admin/pkl |
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
- [x] **ACC MASSAL (tambahan, 2026-10-08):** tab Menunggu punya kolom "Pemeriksaan" (✓ Aman / ⚠ n peringatan) + tombol
      "ACC terpilih" & "ACC semua yang aman" (`Admin\Pkl::accMassal`, maks 200/klik). HANYA ajuan tanpa peringatan
      bahaya/periksa yang di-ACC; sisanya dilewati & dilaporkan beralasan. Tiap ajuan transaksi sendiri; riwayat
      "ACC massal". Uji HTTP 10 cek.
- [x] **T4 Surat Word + nomor + rekap — SELESAI 2026-10-08** (lokal). **Migrasi BARU:**
      `2026-10-08-000001_PklSurat` (tabel `pkl_surat` UNIQUE(tahun, urut) + 7 kolom di `pkl_pengaturan`).
      TANPA PhpWord (vendor ikut di git, hosting hanya `git pull`): `Libraries\PklDocx` membuat .docx sendiri
      lewat ZipArchive — surat bawaan (kop+logo sekolah, tabel siswa+kompetensi keahlian, ruang TTD+stempel
      KOSONG), pengisi TEMPLATE Word sekolah (penanda `${...}`, penanda yang dipecah Word dirapikan, baris
      tabel digandakan per siswa), penggabung surat massal (pindah halaman). `PklNomorSurat` (format bertoken,
      per tahun, lantai "nomor berikutnya"), `PklSurat` (terbitkan dikunci FOR UPDATE + UNIQUE, sidik data →
      "perlu cetak ulang", rakit satu/massal; >60 surat → ZIP), `PklImpor` (Excel riwayat lama, pratinjau
      dulu, cocok lewat NIS atau Nama+Kelas, gabung per perusahaan+tanggal), `Admin\PklBerkas` + view
      `admin/pkl/impor`. UI: blok Surat di detail, kolom Surat + unduh massal di tab Disetujui, bagian Surat &
      template & tautan impor di Pengaturan, tombol Excel di Status Siswa. Hubin: boleh unduh surat & Excel,
      DITOLAK untuk template & impor (`kecuali` admin/pkl/impor). Template unggahan disimpan di
      `writable/pkl/` (perlu bisa ditulis di hosting). **Uji:** `dev:uji-pkl` 268 cek + HTTP 28 cek.
      **Diuji di Microsoft Word 16 asli (2026-10-07, otomatisasi COM di laptop dev):** dua surat (satu perusahaan 3 siswa,
      satu 1 siswa) terbuka TANPA galat/dialog perbaikan, 2 halaman (tiap surat muat satu halaman), 2 tabel. Berkas awal
      tampil "[Compatibility Mode]" → ditambah `word/settings.xml` (compatibilityMode 15) di `PklDocx::dokumen`.
      Ekspor PDF lewat COM macet (bukan masalah berkas), jadi tampilan visual halaman belum dilihat — user tetap
      diminta membuka satu surat dan melapor bila ada yang janggal. Contoh surat sekolah dari teman user
      belum diterima → surat bawaan; ganti lewat unggah template.
- [x] **Perbaikan dari uji user di produksi (sesi malam, lokal, belum di-push):**
      (a) layar "Memproses…" berputar terus setelah unduh surat → form unduh bertanda `data-unduh`; server menyetel
      cookie `unduh_selesai=<token>` lewat header (`PklBerkas::berkas`; respons unduhan CI4 tak memproses
      cookie), `adminLayout.mulaiUnduh` (admin/app.js) menutup layar begitu cookie terbaca (maks 2 menit);
      (b) kartu "Keputusan" di detail ajuan lengket dan menimpa kartu Surat/Riwayat → lengket dihapus;
      (c) nama Waka kosong di surat = belum diisi di Pengaturan PKL → peringatan merah di detail, tab Disetujui,
      Beranda ("Persiapan"), dan hint di Pengaturan; nama/NIP/jabatan Waka kini ikut "sidik" surat
      (`PklSurat::sidik($ajuan,$anggota,$p)`) sehingga mengisinya menandai surat lama "perlu cetak ulang"
      (setelah deploy, surat yang sudah pernah diunduh tampil "perlu cetak ulang" sekali — wajar);
      (d) tombol baru **"Unduh SEMUA surat"** (`mode=semua`, maks 300) + checkbox pilih di kartu HP.
      Uji: `uji_unduh.mjs` 16 cek.
- [x] **Upgrade tampilan (sesi malam):** beranda publik (`public/layout.php`, `home.php`: navbar dengan garis bawah
      meluncur & menu HP beranimasi, hero, kartu Layanan, angka berhitung naik, footer), kepala/sukses/tutup form PKL
      siswa, dan halaman PKL staf (animasi `rise`/`lift`/`bar-grow`, kotak "Persiapan"). Gaya bersama ada di
      `resources/css/app.css` (rise, reveal, lift, orb, nav-link, btn-shine, bar-grow; menghormati "kurangi gerakan").
      Logo hilang → huruf awal sekolah. Panel admin umum (dashboard admin, menu lain) TIDAK diubah.
- [x] **T5 ringan — SELESAI:** `docs/PANDUAN-DEPLOY-PKL.md` (pasang, siapkan, pemakaian, teks WhatsApp, pemecahan masalah).
      Regresi hijau: `dev:uji-pkl` 268 · HTTP surat 28 · HTTP staf 84 · ACC massal 10 · unduh 16.
      **Sisa (bukan kode):** user membuka SATU surat .docx di Microsoft Word asli & melapor; contoh surat sekolah +
      foto kertas isian dari teman user (→ unggah template di Pengaturan PKL / sesuaikan kolom); deploy ke hosting.

- [x] **FORMAT SURAT SEKOLAH + ATURAN BARU (2026-10-07 siang, permintaan klien — lokal, belum di-push):**
      Sumber: `downloadpublic/295 Surat Izin PKL BINUS - Ilyasha ALL XII TKJ 5.docx` (surat resmi sekolah).
      1. **Surat = surat resmi sekolah, persis.** Template bawaan `app/Libraries/Surat/surat_pkl_binus.docx` dibangun
         dari contoh itu (kop gambar TIFF, F4 21×33 cm, tabel NO|NAMA|KELAS|KONSENTRASI KEAHLIAN|NOMOR HANDPHONE,
         TTD+nama Kepsek, "NB"). Hanya data yang berubah (penanda `${...}`). Skrip pembangun: lihat catatan di bawah.
         Ditambah blok **Waka Hubin** di kiri TTD Kepsek (jabatan 2 baris, gambar TTD, nama) dan **kaki surat catatan ACC**
         (bingkai di dasar halaman: siapa meng-ACC, kapan, kode verifikasi, siapa mencetak). Diuji di Word 16 asli (2 surat =
         2 halaman, render PNG per halaman lewat EMF). Nomor `{urut}/SMK-BN/PKL/{bln_romawi}/{thn}` (295/SMK-BN/PKL/IX/2026);
         nama berkas `{urut} Surat Izin PKL BINUS - {nama_depan} {all} {kelas}.docx` (`{all}`=ALL bila >1 siswa), pola bisa diatur
         (`PklNamaBerkas`). Urutan siswa di tabel = urutan dipilih (pengaju dulu), bukan alfabet. HP tiap siswa dari `pkl_anggota.hp`
         (cadangan `siswa.no_hp`, selain itu "-"). Mesin: `PklDocx::dariTemplate($path,$daftar,$media)` — pageBreakBefore (bukan
         paragraf pemisah), id gambar unik, atribut paraId dibuang, media tambahan (TTD Hubin) + relasi + tipe konten, nilai
         penanda RAW (`PklDocx::RAW`) untuk XML gambar. Template unggahan sekolah tetap diutamakan; `contohTemplate()` = template bawaan.
      2. **Form siswa:** tanggal lahir & tanggal mulai/selesai DIHAPUS (kolom DB dibiarkan nullable, tak dipakai); maks 5 siswa
         (batas keras `PklForm::MAKS_SISWA`, Pengaturan 1–5); **HP wajib untuk pengaju DAN tiap teman** (`teman_hp[id]`, galat
         `hp_teman_<id>`); buka-ulang ajuan perbaikan kini pakai **nomor HP** (bukan tgl lahir); pernyataan + pesan "jangan
         mengajukan ulang, tunggu keputusan Waka Hubin maks N hari". Pagar tanggal/durasi dihapus dari Pengaturan & `alasanTutup`.
      3. **Perketat ajuan:** siswa dengan ajuan aktif tak bisa mengajukan lagi (pesan memuat nomor bukti, tanggal kirim, batas
         keputusan); daftar nama memuat `batas`; `pkl_pengajuan.diajukan_at` (jam kirim; diperbarui saat kirim ulang) + Pengaturan
         `batas_keputusan_hari` (bawaan 5) → Kotak Masuk/detail/Beranda menandai **TERLAMBAT**.
      4. **ACC HANYA Waka Hubin** (`Config\Peran` flag `acc`, `HakAkses::bolehAcc`): ACC, tolak, batalkan persetujuan, ACC massal,
         "langsung disetujui", dan mengubah/menghapus ajuan yang sudah disetujui → Hubin/Admin saja (Operator: ditolak di controller,
         tombol disembunyikan, percobaan masuk Audit Log; Operator tetap boleh memeriksa, **mengembalikan**, mengubah ajuan belum
         disetujui, mengisi atas nama (menunggu), mencetak surat). **Admin = cadangan** dan WAJIB mencentang "mewakili Waka Hubin"
         (tercatat di riwayat + kaki surat). Hapus ajuan disetujui hanya Admin.
      5. **Catatan ACC** (`pkl_pengajuan.acc_nama/acc_peran/acc_admin_id/acc_at/acc_ip/acc_kode`) disalin saat ACC (tak ikut berubah
         bila akun diganti nama), dikosongkan saat persetujuan dicabut/ditolak/dikirim ulang; impor → `acc_peran='impor'`. Kartu
         "Persetujuan (ACC)" di detail, kolom "Disetujui oleh" di tab Disetujui & Excel. `PklAjuan::kodeVerifikasi` = HMAC 6 huruf-angka.
         **Gambar TTD digital Waka Hubin** diunggah Hubin/Admin di tab "Tanda Tangan" (`admin/pkl/ttd`, Operator ditolak via 'kecuali'),
         disimpan `writable/pkl/ttd_hubin.png|jpg`, dipasang HANYA pada surat yang di-ACC akun Hubin (ACC Admin/impor → ruang kosong,
         kaki surat menjelaskan). Sidik surat kini memuat penanda tangan, Kepsek, kontak NB, TTD, catatan ACC, HP siswa.
      6. Migrasi baru: `2026-10-09-000001_PklSuratSekolah` (kolom di pkl_pengaturan & pkl_pengajuan, pola nomor lama → baru bila belum
         diubah, maks_anggota ≤ 5, backfill acc_* dari data keputusan lama). **Uji:** `dev:uji-pkl` 319 cek + HTTP `uji_aturan_baru.mjs` 71 cek.
      Membangun ulang template dari contoh sekolah: skrip `bangun_template.php` (DOM: ganti teks ber-run-terpecah dengan `${…}`,
      sisakan 1 baris tabel contoh, sisip blok Hubin & kaki ACC, ganti penomoran otomatis butir NB dengan "1." tertulis).
- [x] **API ANDROID PKL (backend) — SELESAI 2026-10-07** (lokal, belum di-push). Dokumentasi lengkap: `docs/API-PKL.md`.
      `app/Controllers/Api/Pkl.php` (20 rute `pkl/…` di grup `apiauth`): meta, ringkasan, daftar/detail ajuan (+blok `hak` per ajuan),
      isi atas nama/ubah/hapus, acc/kembalikan/tolak/batal-acc, acc-massal, surat satu & massal (biner .docx/.zip), status siswa,
      tanda tangan Waka Hubin. Gerbang: `Config\Peran 'api_akses'` (Operator & Hubin hanya `pkl/…`, Admin semua) via
      `ApiAuthFilter` → `HakAkses::bolehApiAlamat`; `auth/login` & `auth/me` kini memuat `admin.akses_api` (`HakAkses::aksesApi`) dan
      `admin.boleh_acc`. Logika bersama web+API dipindah ke library (tanpa duplikasi aturan): `PklKeputusan` (putuskan/ACC massal),
      `PklStaf::periksaAnggota`, `PklSurat::pilihUntukUnduh/simpanTtd/hapusTtd`. Uji: `uji_api_pkl.mjs` **99/99**, regresi
      `dev:uji-pkl` 319/319 + `uji_aturan_baru.mjs` 71/71.
- [ ] **Layar Flutter PKL (tahap berikut, `fluter-muslimin`):** beranda PKL (angka + antrean + TERLAMBAT), daftar per status, detail
      (tombol dari `data.hak`; dialog ACC: centang "paham" bila `ada_bahaya`, "mewakili Waka Hubin" bila Admin), unduh/bagikan surat
      (biner), Status Siswa, tanda tangan (Hubin/Admin). Menu hanya bila `admin.akses_api` memuat `pkl` atau `*`. Tanya sebelum `flutter build`.
**Sengaja belum dikerjakan:** layar Flutter PKL, jurnal/absensi/nilai PKL, notifikasi
otomatis, ACC dua tingkat, akun siswa/guru.
