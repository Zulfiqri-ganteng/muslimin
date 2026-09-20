# Rencana Fitur: Menu Ujian (ASTS 1 / ASAS / ASTS 2 / ASAT + Ujian Susulan)

Dokumen kerja step-by-step. **Centang tiap step selesai** agar pekerjaan bisa
dilanjutkan di sesi baru tanpa kehilangan konteks.

Dibuat: 2026-09-20. Basis: repo `muslimin` (CI4 4.7, MySQL, shared hosting
Rumahweb, LIVE di kangmuslim.com). Klien: **Wakasek Kurikulum** SMK Bina Nusa.

---

## Latar & batasan

Sekolah butuh **pendataan pelaksanaan asesmen sumatif** — bukan aplikasi ujian
online, bukan penilaian. Empat gelombang ujian per tahun pelajaran:

| Kode | Kepanjangan | Semester |
|---|---|---|
| **ASTS 1** | Asesmen Sumatif Tengah Semester 1 | Ganjil |
| **ASAS** | Asesmen Sumatif Akhir Semester | Ganjil |
| **ASTS 2** | Asesmen Sumatif Tengah Semester 2 | Genap |
| **ASAT** | Asesmen Sumatif Akhir Tahun | Genap |

Tiap gelombang punya **ujian susulan** untuk siswa yang berhalangan.

---

## Keputusan user (2026-09-20)

1. **Menu:** grup sidebar baru `UJIAN` berisi **4 link** (ASTS 1, ASAS, ASTS 2,
   ASAT). Sub-menu "ujian susulan" diwujudkan sebagai **tab di dalam halaman**,
   bukan item sidebar — `layouts/admin.php` cuma DITAMBAH satu grup, tidak
   dibedah (nol risiko ke halaman admin lain yang sudah live).
2. **Kehadiran:** **catat yang TIDAK HADIR saja.** Tidak ada absensi penuh per
   siswa per mapel. Alasan: 1.718 siswa aktif × ~12 mapel = ±20.000 baris per
   periode (±82.000/tahun) yang 99% isinya "hadir" — pemborosan di shared
   hosting dan entrinya lama. Daftar hadir dicetak kosong untuk tanda tangan.
3. **Pengawas: OPSIONAL.** Boleh diisi, boleh dibiarkan kosong. Tidak pernah
   memblokir penyimpanan jadwal.
4. **Tanpa nilai.** Murni pendataan pelaksanaan. Nilai tetap urusan guru/rapor
   di luar sistem.
5. **Web dulu 100%, Android menyusul** (pola sama SIMLAB & UKK).
6. **1 step selesai → BERHENTI → lapor → tunggu aba-aba** sebelum step berikut.

---

## Temuan analisis DB live (export `database/dbimport.sql`, 2026-09-20)

Angka di bawah hasil parsing langsung dump produksi, bukan perkiraan.

| Data | Jumlah aktif |
|---|---|
| Siswa (`status='aktif'`, `deleted_at IS NULL`) | **1.718** |
| Kelas | **42** — X: 16, XI: 15, XII: 11 |
| Rata-rata siswa/kelas | 40,9 (min 28, maks 59) |
| Mata pelajaran | **44** (dari 107+ baris; sisanya sudah soft-delete) |
| Guru | 48 |
| Jurusan | 4 (TKJT, TJKT, MPLB, AKL) |

Empat temuan yang **mengubah desain**:

1. **Tabel `tahun_ajaran` KOSONG (0 baris) di produksi.** Modul UKK terlanjur
   pakai `tahun_ajaran_id` sebagai FK → dropdown-nya kosong. Tahun pelajaran
   yang benar-benar dipakai ada di `settings.academic_year` = `2026/2027`.
   → Modul ujian **TIDAK** bergantung ke `tahun_ajaran`; simpan sebagai
   `VARCHAR` yang default-nya diambil dari `settings`. Langsung jalan tanpa
   setup apa pun.
2. **Shift tidak seragam per tingkat:** X = 16 kelas **semua pagi**;
   XI = 15 kelas **semua siang**; XII = **5 pagi + 6 siang**.
   → Jadwal ujian tidak cukup "per tingkat"; butuh kolom **`shift`**, terutama
   untuk XII yang terbelah.
3. **Kode mapel sudah ber-prefix tingkat** (`10PAI`, `11PAI`, `12PAI`,
   `10DPKTKJ`, `11EKOMBISNIS`) — tapi itu cuma teks di `kode_mapel`, bukan
   kolom terstruktur. → **`tingkat` wajib jadi kolom eksplisit** di jadwal
   ujian, haram di-parsing dari kode mapel.
4. **Semua 1.718 siswa punya `kelas_id` valid** (nol siswa yatim) → peserta
   ujian 100% bisa diturunkan dari data yang ada, tanpa entri ulang dan tanpa
   tabel peserta.

---

## Peta permintaan → modul

| Permintaan user | Modul (tabel) |
|---|---|
| Menu ASTS 1 / ASAS / ASTS 2 / ASAT | `ujian_periode` (1 baris per jenis per tahun ajaran) |
| Jadwal ujian per gelombang | `ujian_jadwal` |
| Pengawas (opsional) | `ujian_pengawas` |
| Siswa tidak hadir | `ujian_susulan` (status `belum`) |
| Ujian susulan | `ujian_susulan` (status `dijadwalkan` → `selesai`) |
| Peserta ujian | *(diturunkan dari `siswa` + `kelas` — tanpa tabel)* |
| Rekap & cetak | *(query + export, bukan tabel)* — `Admin\LaporanUjian` |

Dipakai ulang, **TIDAK diduplikasi**: `siswa`, `kelas`, `jurusan`,
`mata_pelajaran`, `guru`, `settings`.

---

## ERD (4 tabel baru)

```
ujian_periode  (ASTS1 / ASAS / ASTS2 / ASAT  ×  tahun ajaran)
      │
      ├──< ujian_jadwal  (mapel × tingkat × jurusan? × shift × tanggal × jam × ruang)
      │          │
      │          ├──< ujian_pengawas  (>= guru)        [OPSIONAL]
      │          │
      │          └──< ujian_susulan   (>= siswa)       [INTI]
      │
      └──────────────< ujian_susulan
```

`ujian_susulan` menggantung ke `periode_id` **dan** `jadwal_id` supaya baris
tetap bermakna kalau jadwalnya dihapus (`jadwal_id` jadi NULL, `mapel_id` dan
`tanggal_ujian` sudah didenormalisasi ke barisnya).

### Kolom inti per tabel

- **ujian_periode**: `jenis` enum(ASTS1/ASAS/ASTS2/ASAT), `tahun_ajaran`
  varchar(20), `semester` enum(Ganjil/Genap), `nama`? (label custom),
  `tanggal_mulai`?, `tanggal_selesai`?, `susulan_mulai`?, `susulan_selesai`?,
  `status` enum(draft/berjalan/selesai) default draft, `keterangan`?.
  **UNIQUE(jenis, tahun_ajaran)** → tiap tahun berulang, riwayat lama utuh.
  Soft delete.
- **ujian_jadwal**: `periode_id`(→ujian_periode CASCADE),
  `mapel_id`?(→mata_pelajaran SET NULL), `tingkat` enum(X/XI/XII),
  `jurusan_id`?(→jurusan SET NULL, untuk mapel kejuruan), `shift`
  enum(pagi/siang/semua) default semua, `tanggal`, `jam_mulai`?,
  `jam_selesai`?, `ruang`?, `keterangan`?. Soft delete.
- **ujian_pengawas** (pivot, **hard delete** — pola `jadwal_ukk_penguji`):
  `jadwal_id`(→ujian_jadwal CASCADE), `guru_id`?(→guru SET NULL), `ruang`?,
  `peran` enum(pengawas/cadangan) default pengawas, `keterangan`?.
- **ujian_susulan**: `periode_id`(→ujian_periode CASCADE),
  `jadwal_id`?(→ujian_jadwal SET NULL), `siswa_id`(→siswa CASCADE),
  `mapel_id`?(→mata_pelajaran SET NULL, didenormalisasi dari jadwal),
  `tanggal_ujian`? (tanggal ujian asli yang terlewat, didenormalisasi),
  `alasan` enum(sakit/izin/alpa/lainnya) default alpa, `keterangan`?,
  `status` enum(belum/dijadwalkan/selesai/batal) default belum,
  `tanggal_susulan`?, `jam_susulan`?, `ruang_susulan`?,
  `pengawas_guru_id`?(→guru SET NULL), `tanggal_pelaksanaan`?.
  **UNIQUE(siswa_id, jadwal_id)** → 1 siswa tak bisa dobel di 1 sesi ujian.
  Soft delete.

Semua InnoDB + utf8mb4, gaya migrasi mengikuti
`2026-08-27-000002_CreateUkk`.

> **Catatan pemulihan:** UNIQUE tetap berlaku pada baris soft-deleted. Jadi
> saat mencatat ketidakhadiran, cek `withDeleted()` dulu → kalau baris lama ada
> dan `deleted_at` terisi, **UPDATE (pulihkan)**, jangan INSERT. Pola persis
> `PesertaUkk::daftarkanStore` di modul UKK.

### Sengaja TIDAK dibuat

- **Tabel peserta ujian** — peserta = siswa aktif di tingkat/shift terkait,
  dihitung saat dibutuhkan. Nol entri data.
- **Tabel absensi ujian** — lihat keputusan #2.
- **Kolom nilai** — lihat keputusan #4.
- **Upload berkas surat izin/dokter** — belum diminta. Kalau nanti perlu,
  cukup migrasi tambahan `ALTER TABLE ujian_susulan ADD berkas VARCHAR(150)`
  (aditif, aman di live).
- **Pembagian ruang & kartu peserta** — pekerjaan tersendiri yang besar
  (alokasi 1.718 siswa ke ruang + cetak kartu). Ditunda, bukan dibatalkan.

---

## Rambu wajib (project ini LIVE)

1. **Migrasi hanya aditif** — cuma `CREATE TABLE` baru. Nol perubahan pada
   tabel yang sudah berisi data produksi.
2. **Semua aksi ubah/hapus pakai POST**, mengikuti modul Dokumen. Modul lama
   masih hapus lewat GET (utang CSRF) — jangan ditiru.
3. **Mapel wajib difilter `deleted_at IS NULL`** (44 aktif dari 107+ baris).
4. **Jangan generate baris peserta massal.**
5. Tiap mutasi panggil `master_data_changed('<modul>')`; tiap halaman pasang
   help card `admin/partials/help`; rebuild Tailwind tiap tambah kelas baru.
6. **Claude TIDAK commit** — user commit sendiri.
7. Deploy: `cd ~/kangmuslim && git pull && phpm spark migrate`.

### Cara uji (lokal)

- Apache: `http://localhost/muslimin/public`, login field **`login`**,
  `admin` / `admin123`, CSRF cookie `csrf_test_name` regenerate → ambil token
  dari halaman GET tiap sebelum POST.
- PHP: `/c/xampp/php/php.exe` (8.2) · DB:
  `/c/xampp/mysql/bin/mysql.exe -h 127.0.0.1 -u root muslimin`
- Data uji **wajib dibersihkan** setelah tiap step (termasuk baris audit).

---

## Step kerja — WEB (Step 1–9)

- [x] **Step 1 — Rencana & Fondasi DB** ✅ SELESAI 2026-09-20
      Dokumen ini + migrasi `2026-09-20-000001_CreateUjian` (4 tabel, urutan FK
      aman) + 4 model (`UjianPeriodeModel`, `UjianJadwalModel`,
      `UjianPengawasModel`, `UjianSusulanModel`). Soft delete semua kecuali
      `ujian_pengawas` (hard delete, pola pivot `jadwal_ukk_penguji`).
      `UjianPeriodeModel::ambilAtauBuat()` = auto-provision periode untuk tahun
      pelajaran berjalan (baca `settings.academic_year`), dipakai Step 2.
- [x] **Step 2 — Menu + Kerangka Periode** ✅ SELESAI 2026-09-20
      Grup sidebar **`UJIAN`** (4 link: ASTS 1 / ASAS / ASTS 2 / ASAT) — cuma
      DITAMBAH ke array `$groups`, `layouts/admin.php` tidak dibedah.
      `Admin\Ujian` melayani keempat jenis lewat slug (`admin/ujian/asts1`),
      dengan 5 tab (`periode` aktif; `jadwal`/`ketidakhadiran`/`susulan`/
      `rekap` masih panel "belum tersedia"). Periode TP berjalan
      **dibuat otomatis** saat halaman dibuka lewat
      `UjianPeriodeModel::ambilAtauBuat()`; tahun pelajaran LAMA hanya dibaca
      (`?tp=`), tidak pernah dibuat otomatis. Model dapat `dariSlug()`/
      `keSlug()`. Form pengaturan periode (tanggal ujian, tanggal susulan,
      status, nama tampil, keterangan) **POST** + `csrf_field()`; tanggal
      selesai < mulai ditolak; `id` dari form diperiksa harus milik jenis yang
      sedang dibuka (anti-tamper). Audit `update/ujian_periode` +
      `master_data_changed('ujian_periode')`. Tailwind di-rebuild.
      **Teruji e2e HTTP (35/35):** auto-provision 4 jenis tanpa duplikat
      (buka berulang tetap 4 baris), ASTS1/ASAS→Ganjil & ASTS2/ASAT→Genap,
      slug & tab ngawur → redirect aman, simpan periode valid tersimpan tepat,
      tanggal terbalik ditolak & data lama tidak tertimpa, id milik jenis lain
      ditolak (periode ASAT tidak ikut berubah), `?tp=` asing → redirect dan
      TIDAK membuat baris diam-diam, dropdown riwayat + spanduk peringatan TP
      lama muncul, simpan di TP lama tetap membawa `?tp` dan tidak menyentuh TP
      berjalan, audit tercatat, serta 5 halaman admin lama (dashboard,
      peserta-ukk, dokumen, master/siswa, jadwal) tetap 200. Data uji
      dibersihkan (periode & audit nol).
      **Catatan uji:** `curl -L -X POST` menyesatkan — `-X` membuat metode POST
      ikut terbawa saat mengikuti redirect sehingga yang terbaca halaman 404;
      pakai `-d` saja tanpa `-X`. Login membalas **303**, bukan 302.
- [x] **Step 3 — Jadwal Ujian** ✅ SELESAI 2026-09-20
      Tab `jadwal` jadi panel nyata. View dipecah: `index.php` tinggal kerangka
      (identitas periode + pemilih TP + bilah tab) lalu memanggil
      `tab_periode.php` / `tab_jadwal.php` / `tab_belum.php` — supaya tak
      menumpuk saat step berikutnya menambah tab.
      `Admin\Ujian` + `simpanJadwal()` (tambah & ubah satu pintu),
      `hapusJadwal()`, `dataJadwal()`, `builderJadwal()` (builder segar dipakai
      dua kali: hitung total & paginasi). Modal Alpine inline pola `JadwalUkk`
      (`openAdd`/`openEdit`), semua mutasi **POST + csrf_field()**, hapus pakai
      form POST bertombol `data-confirm` (delegasi global sudah menangani
      elemen apa pun, bukan cuma `<a>`).
      **Koreksi aturan bentrok (penting):** `UjianJadwalModel::bentrok()` kini
      MENGABAIKAN baris ber-mapel sama. Alasannya satu tingkat berisi 15-16
      kelas sehingga satu mapel wajar dipecah ke beberapa ruang pada jam yang
      sama — itu ujian paralel, bukan tabrakan. Yang ditolak hanya dua **mapel
      berbeda** pada tingkat + shift + jam yang beririsan.
      Hapus jadwal (transaksi, ditulis manual karena soft delete tak memicu
      FK): `ujian_pengawas` dihapus permanen, `ujian_susulan.jadwal_id`
      di-NULL-kan (catatan ketidakhadiran DIPERTAHANKAN — mapel & tanggal
      sudah didenormalisasi), lalu jadwal di-soft-delete. Jumlah yang dilepas
      disebut di flash & audit.
      **Teruji e2e HTTP (44/44):** simpan tepat ke DB; mapel beda jam
      beririsan ditolak & tak masuk DB; mapel sama beda ruang diterima; shift
      beda / tingkat beda / jam bersambung diterima; 4 validasi isian ditolak;
      ubah tak dianggap bentrok dengan dirinya sendiri; 4 guard lintas periode
      (`periode_id` jenis lain & `id` jadwal milik periode lain, untuk ubah
      maupun hapus) semuanya ditolak tanpa menyentuh data; filter tingkat &
      pencarian benar; hapus membersihkan pengawas tapi menyisakan susulan
      dengan `jadwal_id` NULL; kartu ringkasan & audit ikut benar; 4 halaman
      admin lama tetap 200. **Suite Step 2 dijalankan ulang: 35/35** (satu
      asersi lama diperbarui karena tab Jadwal bukan placeholder lagi).
      Data uji dibersihkan.
- [x] **Step 4 — Import & Export Jadwal** ✅ SELESAI 2026-09-20
      Controller BARU `Admin\UjianBerkas` (template / export / import-preview /
      import-commit). Dipisah dari `Admin\Ujian` karena setelah impor
      ditambahkan berkas itu menyentuh 988 baris — melewati controller
      terbesar di repo; pemisahan ini mengikuti pola yang sudah ada
      (`Admin\Export`, `Admin\Cetak` = controller khusus berkas). Kini 517 +
      525 baris. Helper `periodeTerpilih()`/`urlTab()` sengaja DISALIN, bukan
      dijadikan trait: repo ini **nol trait**, dan duplikasi helper kecil
      memang lazim di sini (`perPage()` ada di BaseMaster sekaligus
      PesertaUkk).
      Pratinjau memakai ulang view generik `admin/master/import_preview`
      (editor baris yang bisa disunting) tanpa mengubahnya — konteks periode
      dititipkan lewat query `?tp=` pada `commitUrl`, bukan field tersembunyi.
      Modal unggah memakai `admin/master/partials/modal_import`.
      **Identitas baris impor** = periode + mapel + tingkat + jurusan + shift
      + **ruang**. Ruang ikut dihitung supaya ujian paralel di dua ruang tetap
      jadi dua baris saat berkas diunggah ulang; baris beridentitas sama
      DIPERBARUI, jadi berkas boleh diunggah ulang setelah diperbaiki.
      Parser toleran: tanggal `YYYY-MM-DD`, `d/m/Y`, `d-m-Y`, dan nomor seri
      Excel; jam `HH:MM`, `H.MM`, `HH:MM:SS`, dan pecahan hari. Tiap baris
      gagal dilaporkan beserta ALASANNYA (maks 8 ditampilkan). Template polos
      tanpa kop (agar mudah diparse ulang), ekspor memakai kop sekolah.
      **Teruji e2e HTTP (40/40)** dengan berkas .xlsx sungguhan: template
      terunduh & header-nya cocok kolom impor; pratinjau tampil tanpa
      menyimpan apa pun; commit 9 baris → 4 masuk, 5 dilewati dengan alasan
      masing-masing (mapel tak terdaftar, tingkat ngawur, tanggal ngawur, jam
      terbalik, bentrok); `07/10/2026`→`2026-10-07` dan `07.30`→`07:30:00`;
      baris tanpa jam/ruang tersimpan NULL; unggah ulang memperbarui (tetap 4
      baris) sedangkan ruang berbeda menambah baris baru; ekspor ber-kop
      dengan 5 baris data; unggah berkas non-Excel tidak merusak aplikasi.
      **Suite Step 2 (35/35) & Step 3 (44/44) dijalankan ulang** setelah
      pemecahan controller. Data uji dibersihkan.
- [x] **Step 5 — Pengawas (opsional)** ✅ SELESAI 2026-09-20
      Halaman terpisah `admin/ujian/{slug}/pengawas/{jadwalId}` (pola
      `admin/jadwal_ukk/penguji`: form tugaskan di kiri, daftar di kanan),
      bukan tab — isinya daftar penugasan, bukan satu form. Angka pengawas di
      tabel Jadwal jadi tautan ke halaman ini.
      `Admin\Ujian` +`pengawas()`/`simpanPengawas()`/`hapusPengawas()` +
      `konteksJadwal()` (satu tempat menyelesaikan slug + periode + jadwal,
      sekaligus memastikan jadwal memang milik periode yang sedang dibuka).
      **Tetap opsional**: tak ada satu pun jalur yang mewajibkan pengawas
      diisi; sesi tanpa pengawas tetap tersimpan dan tampil normal.
      Ruang dikosongkan → otomatis ikut ruang sesi ujiannya.
      **Deteksi bentrok jam pengawas** (`UjianPengawasModel::bentrokGuru()`):
      seorang guru ditolak bila jam tugasnya beririsan dengan sesi lain yang
      sudah ia awasi — diperiksa **lintas periode**, karena guru tetap tak
      bisa berada di dua ruang sekaligus walau sesinya milik gelombang ujian
      berbeda. `UjianJadwalModel::jamBerimpit()` diubah dari private jadi
      **public static** agar bisa dipakai ulang model pengawas (satu sumber
      kebenaran untuk aturan tumpang-tindih jam).
      **Teruji e2e HTTP (34/34, lulus sekali jalan):** halaman tampil & sesi
      tanpa pengawas tetap sah; ruang kosong ikut ruang sesi; guru sama di
      sesi sama ditolak; tanpa guru / guru tak ada ditolak; jam beririsan
      ditolak & tak masuk DB; tanggal beda diterima; **bentrok lintas periode
      terdeteksi**; jadwal milik periode lain & id ngawur pada URL ditolak;
      lepas penugasan milik sesi lain ditolak; guru yang sudah dilepas boleh
      ditugaskan ulang; hapus jadwal ikut membersihkan pengawas; audit
      tercatat. **Regresi Step 2 (35/35), Step 3 (44/44), Step 4 (40/40)**
      dijalankan ulang setelah `jamBerimpit` dijadikan statis. Data uji
      dibersihkan.
- [x] **Step 6 — Pendataan Ketidakhadiran → Susulan** ⭐ INTI ✅ SELESAI 2026-09-20
      Tab `ketidakhadiran` (view `tab_ketidakhadiran.php`) dengan alur tiga
      langkah: pilih sesi → pilih kelas → centang yang TIDAK hadir. Siswa
      hadir tidak pernah masuk tabel.
      **Penyaringan kelas sasaran** (`UjianJadwalModel::kelasSasaran()`):
      tingkat wajib cocok; jurusan hanya membatasi bila sesi dikhususkan;
      shift hanya membatasi bila sesi bukan "semua". Query builder mentah
      dengan `deleted_at` DIKUALIFIKASI nama tabel (pola
      `SiswaModel::statistik`) supaya klausa soft-delete tidak ambigu saat
      JOIN.
      `simpanKetidakhadiran()` — satu transaksi, daftar siswa **diambil ulang
      dari DB** (bukan dari kiriman form) sehingga siswa kelas lain yang
      dititipkan ke POST diabaikan. Tiga aturan penting:
      (1) centangan dilepas = siswa ternyata hadir → catatannya dihapus;
      (2) susulan berstatus **`selesai` DIKUNCI** — tidak ikut terhapus,
      karena itu riwayat pelaksanaan yang nyata;
      (3) status `dijadwalkan`/`selesai` **tidak dimundurkan** jadi `belum`
      hanya karena pendataan disimpan ulang — alasan & keterangan tetap boleh
      berubah. Alasan di luar daftar jatuh ke `alpa`.
      Pemulihan baris soft-deleted lewat `UjianSusulanModel::catat()` (UNIQUE
      `(siswa_id, jadwal_id)` tetap berlaku pada baris terhapus).
      **Teruji e2e HTTP (40/40):** penyaringan kelas benar untuk tiga
      kombinasi (pagi/siang/semua × jurusan tertentu/semua); siswa berstatus
      lulus & siswa kelas lain tidak tampil; mapel + tanggal ujian tersalin
      dari sesi; simpan ulang menghasilkan 1 baru/1 ubah/1 batal; baris yang
      pernah dihapus DIPULIHKAN (bukan kembar); status `dijadwalkan`
      dipertahankan; **susulan `selesai` tidak terhapus walau centangan
      dilepas**; kelas di luar sasaran, sesi & periode ngawur semuanya
      ditolak; siswa titipan diabaikan; alasan ngawur dijinakkan; angka ikut
      benar di tabel jadwal & ringkasan sesi.
      **Regresi Step 2 (35/35), 3 (44/44), 4 (40/40), 5 (34/34).** Data uji
      (termasuk kelas & siswa sementara) dibersihkan.
      **Catatan uji:** sempat ada asersi yang lolos semu — baris di-`UPDATE`
      jadi `selesai` padahal sudah soft-deleted, sehingga penguncian tak
      pernah benar-benar diuji. Baris harus diaktifkan lebih dulu; dan jumlah
      pada asersi sebaiknya dibaca dari DB, bukan dipatok angka.
- [x] **Step 7 — Pengelolaan Susulan** ✅ SELESAI 2026-09-20
      Tab `susulan` (view `tab_susulan.php`): empat kartu ringkasan status
      yang sekaligus jadi pintasan filter, lima filter (cari nama/NIS, kelas,
      mapel, alasan, status) + per-halaman, tabel dengan centang baris, dan
      dua modal Alpine.
      `Admin\Ujian` +`jadwalkanSusulan()` / `statusSusulan()` /
      `hapusSusulan()` / `dataSusulan()` / `builderSusulan()` /
      `konteksSusulan()`.
      **Satu endpoint untuk satuan & massal**: modal Jadwalkan mengirim
      `ids[]` — dari satu baris (terisi otomatis nilainya) atau dari semua
      baris tercentang. Menghindari dua jalur kode yang harus dijaga selaras.
      **Aturan yang dijaga:** baris berstatus `selesai` DILEWATI saat
      penjadwalan massal (tidak boleh tergeser oleh centangan massal —
      konsisten dengan penguncian di Step 6); untuk menjadwalkan ulang,
      statusnya dikembalikan dulu lewat Ubah Status. `tanggal_pelaksanaan`
      hanya terisi saat status `selesai` (kosong → hari ini) dan DIBERSIHKAN
      begitu status pindah ke yang lain, supaya tidak menyesatkan.
      Filter yang sedang aktif dibawa pulang lewat field `kembali` sehingga
      halaman tidak melompat ke daftar penuh setelah menyimpan.
      **Teruji e2e HTTP (43/43, lulus sekali jalan):** daftar hanya memuat
      baris periode ini; 5 filter benar; jadwalkan satuan & massal (3
      sekaligus) tersimpan lengkap; pengawas kosong → NULL; tanpa tanggal /
      tanpa baris / guru ngawur ditolak tanpa mengubah data; tandai selesai
      dengan & tanpa tanggal; **baris `selesai` dilewati saat penjadwalan
      massal sementara baris lain tetap tergeser**; kembali ke status lain
      membersihkan tanggal pelaksanaan; ubah status / jadwalkan / hapus
      baris milik periode lain semuanya ditolak atau diabaikan; status
      ngawur ditolak; filter terbawa setelah aksi; hapus = soft delete.
      **Regresi Step 2 (35/35), 3 (44/44), 4 (40/40), 5 (34/34), 6 (40/40)**
      — satu asersi Step 2 diperbarui lagi karena tab Susulan bukan
      placeholder lagi. Data uji dibersihkan.
      **Catatan ukuran:** `Admin\Ujian` kini 1101 baris (di atas Jadwal.php
      yang 971). Laporan Step 8 sengaja ditaruh di controller terpisah
      (pola `LaporanLab`/`LaporanUkk`) supaya tidak menambah beban file ini.
- [x] **Step 8 — Cetak & Laporan** ✅ SELESAI 2026-09-20
      `App\Libraries\UjianReport` (pola `LabReport`) memegang SATU perhitungan
      yang dipakai bersama tab Rekap dan semua cetakan — angka di layar tak
      mungkin beda dengan angka di berkas. `Admin\LaporanUjian` (controller
      sendiri, pola `LaporanLab`/`LaporanUkk`) memuat 4 keluaran:
      **Rekap PDF**, **Rekap Excel** (ringkasan + per kelas + per mapel +
      daftar detail, ber-kop), **Daftar Hadir per sesi PDF** (satu kelas satu
      halaman, kolom tanda tangan dibiarkan kosong), dan **Berita Acara per
      sesi PDF** (narasi + daftar tidak hadir + blok ttd pengawas + kepala
      sekolah). Nomor berita acara DITURUNKAN dari jenis+tahun+id sesi,
      tidak disimpan, jadi stabil tiap kali dicetak tanpa tabel penomoran.
      Tab Rekap juga menyorot **siswa yang tidak hadir di lebih dari satu
      mapel** — penanda untuk wali kelas, bukan sekadar angka.
      Berkat library ini `Admin\Ujian` cuma bertambah 6 baris (1101 → 1107).
      Cetakan per sesi bisa diakses dari ikon di tiap baris tab Jadwal.

      ### ⚠ Bug produksi yang tertangkap di step ini
      Uji sengaja dijalankan dengan **`ONLY_FULL_GROUP_BY` dinyalakan** untuk
      meniru server (default MySQL 5.7+), sementara MariaDB lokal TIDAK
      memakainya. Hasilnya menyingkap dua masalah:
      1. **`first()` + `withDeleted()` pada model ber-soft-delete** membuat
         CI4 menyisipkan `GROUP BY <tabel>.id` ke query `SELECT *` — sah di
         lokal, **ditolak** di server ketat. Ini akan membuat **SELURUH
         halaman menu Ujian 500 di hosting**, karena `ambilAtauBuat()`
         dipanggil di setiap halaman. Diperbaiki jadi `findAll(1)[0] ?? null`
         di `UjianPeriodeModel::ambilAtauBuat()` dan
         `UjianSusulanModel::catat()`.
      2. `GROUP BY` di `UjianReport` dilengkapi semua kolom non-agregat
         (`kelas.nama_kelas`, `mata_pelajaran.nama_mapel`, dst) supaya sah di
         kedua mode.
      **Catatan untuk modul lain:** pola `withDeleted()->…->first()` yang sama
      masih ada di `Admin\Jadwal.php:870` dan alur pemulihan
      `PesertaUkk::daftarkanStore` (modul UKK) — berisiko sama di server
      ketat, belum disentuh karena di luar cakupan modul ini.

      **Teruji e2e HTTP (45/45):** tab Rekap + 3 kartu angkanya cocok dengan
      DB; rekap PDF & Excel terbentuk (isi Excel diverifikasi per teks:
      RINGKASAN, rekap per kelas, daftar detail, nama kelas/siswa, kop);
      daftar hadir & berita acara terbentuk; **sesi tanpa pengawas tetap bisa
      dicetak** (pengawas kan opsional); periode kosong tetap menghasilkan
      berkas; jenis ngawur, sesi periode lain, dan id tak ada semuanya
      redirect; tautan cetak muncul di baris jadwal; `sql_mode` dipulihkan
      setelah uji. **Seluruh suite Step 2–7 dijalankan ULANG DALAM MODE
      KETAT dan semuanya hijau** (35/44/40/34/40/43). Data uji dibersihkan.
- [x] **Step 9 — Integrasi & Audit Web** ✅ SELESAI 2026-09-20 → **WEB 100%**
      **Dashboard**: bagian baru "Ujian — TP {tahun}" berisi 4 kartu (satu per
      gelombang) yang menampilkan jumlah sesi, jumlah tidak hadir, dan
      peringatan "N susulan belum dijadwalkan", masing-masing menaut ke
      menunya. Datanya lewat `UjianReport::dashboard()` yang **HANYA MEMBACA**
      — periode yang belum ada ditampilkan nol, tidak dibuat otomatis, karena
      dashboard tidak boleh punya efek samping (diuji khusus).
      **Cache**: hasil dashboard disimpan di kunci tunggal `dash_ujian`
      (TTL 30 menit) dan didaftarkan pada `$singles` cache_helper untuk
      `ujian_periode`/`ujian_jadwal`/`ujian_susulan`, jadi tiap mutasi
      membuangnya. Tahun pelajaran ikut disimpan DI DALAM payload sehingga
      cache membatalkan dirinya sendiri saat tahun di Pengaturan Sekolah
      berganti — tanpa perlu kunci cache per tahun (diuji dengan menghapus
      hanya cache `app_setting`). `$ripple` juga diisi
      (periode → jadwal → susulan).
      **Audit**: tidak perlu perubahan — daftar tabel di halaman Audit Log
      diturunkan dari isi log (`AuditModel::tabelList()`), jadi `ujian_*`
      muncul sendiri.
      **Help card** terpasang di kelima tab + halaman pengawas.
      **Teruji e2e HTTP (60/60, lulus sekali jalan):** bagian dashboard tampil
      & tidak menggandakan judul lama; dashboard tidak membuat periode;
      angka ikut naik setelah jadwal & ketidakhadiran disimpan (bukti cache
      dibuang); ganti tahun pelajaran menyegarkan kartu walau cache lama
      masih ada; filter audit per tabel jalan; help card ada di 6 halaman;
      **sapuan 16 rute ujian** semuanya sehat; sidebar lengkap; 11 halaman
      admin lama tetap 200; pengaturan sekolah utuh setelah uji.

---

## Audit penutup WEB (2026-09-20)

Seluruh suite Step 2–9 dijalankan **dua kali**: mode normal dan mode
`ONLY_FULL_GROUP_BY` (meniru server).

| Step | Normal | Mode ketat |
|---|---|---|
| 2 Menu & Periode | 35/35 | 35/35 |
| 3 Jadwal | 44/44 | 44/44 |
| 4 Impor/Ekspor | 40/40 | 40/40 |
| 5 Pengawas | 34/34 | 34/34 |
| 6 Ketidakhadiran | 40/40 | 40/40 |
| 7 Susulan | 43/43 | 43/43 |
| 8 Cetak & Laporan | 45/45 | 43/45 * |
| 9 Integrasi | 60/60 | 58/60 * |

`*` Dua kegagalan itu **BUKAN dari modul Ujian**: keduanya halaman lama
`admin/laporan-ukk` dan `admin/laporan-lab` yang ikut diperiksa sebagai
regresi. Penyebabnya `'muslimin.lab.nama' isn't in GROUP BY` di
`App\Libraries\LabReport::hitung()` (`asetPerLab` mengelompokkan
`aset.lab_id` sambil memilih `lab.nama`). Seluruh halaman modul Ujian sendiri
hijau di kedua mode.

> **Petunjuk penting:** karena kedua halaman itu berjalan normal di
> kangmuslim.com hari ini, server produksi hampir pasti **tidak** memakai
> `ONLY_FULL_GROUP_BY`. Jadi pengerasan di Step 8 sifatnya jaga-jaga untuk
> masa depan (mis. saat MySQL hosting di-upgrade), bukan pemadam kebakaran.
> Tiga tempat yang masih rawan bila mode itu suatu saat menyala:
> `LabReport::hitung()`, `Admin\Jadwal.php:870`, dan
> `PesertaUkk::daftarkanStore` — semuanya DI LUAR modul ini dan belum
> disentuh.

## Step kerja — ANDROID (Step 10–12, mulai setelah Step 9)

- [x] **Step 10 — API `/api/v1/admin/ujian/*`** ✅ SELESAI 2026-09-20
      Tiga controller (`Api\Admin\Ujian`, `UjianJadwal`, `UjianSusulan`)
      mewarisi `BaseApiController`, semuanya di dalam grup `apiauth`
      (Bearer token) dengan amplop `{status, ok, message, data, meta}`.
      Bukan `BaseCrud` karena modul ini alur kerja, bukan master data —
      sama alasannya dengan sisi web.
      **Perapian sekalian:** aturan "slug + tahun pelajaran → periode"
      dipindah ke `UjianPeriodeModel::untukTahun()`. Sebelumnya tersalin di 2
      controller web dan akan jadi 5 setelah API. Kini web (`Admin\Ujian`,
      `UjianBerkas`, `LaporanUjian`) dan API memakai satu sumber aturan.
      `Api\Admin\Options` diperluas: `ujian_jenis`, `ujian_tingkat`,
      `ujian_shift`, `ujian_alasan`, `ujian_status`, `ujian_peran_pengawas`
      — supaya Flutter tidak hard-code daftar nilai tetap.
      **Teruji e2e HTTP (83/83, lulus sekali jalan)** dengan token sungguhan;
      semua guard sisi web punya padanannya di API (slug ngawur → 404, tahun
      asing → 404 tanpa membuat baris, id lintas periode ditolak, siswa kelas
      lain yang dititipkan diabaikan, baris `selesai` dilewati saat
      penjadwalan massal, bentrok jadwal & bentrok jam pengawas → 409).
      Audit mencatat "(via mobile)". Web diuji ulang setelahnya: tetap sehat.

### Kontrak API — untuk sesi Flutter (Step 11–12)

Semua butuh header `Authorization: Bearer <token>`. `{slug}` =
`asts1|asas|asts2|asat`. Semua endpoint menerima `?tp=YYYY/YYYY` untuk
membuka tahun pelajaran lama (kosong = tahun berjalan; tahun lama hanya
dibaca, tidak pernah dibuat).

| Metode | Endpoint | Guna |
|---|---|---|
| GET | `admin/ujian` | ringkasan 4 gelombang (read-only, untuk beranda) |
| GET | `admin/ujian/{slug}` | periode + ringkasan angka + riwayat tahun |
| POST | `admin/ujian/{slug}/periode` | simpan tanggal & status periode |
| GET | `admin/ujian/{slug}/rekap` | agregat lengkap (sama dengan tab Rekap web) |
| GET | `admin/ujian/{slug}/jadwal` | daftar jadwal — `q, tingkat, jurusan_id, per, page` |
| POST | `admin/ujian/{slug}/jadwal` | tambah; sertakan `id` di body untuk mengubah |
| DELETE | `admin/ujian/{slug}/jadwal/{id}` | hapus (balas jumlah susulan dilepas & pengawas dihapus) |
| GET | `admin/ujian/{slug}/jadwal/{id}/kelas` | kelas sasaran sesi |
| GET/POST | `admin/ujian/{slug}/jadwal/{id}/pengawas` | lihat / tugaskan pengawas |
| DELETE | `admin/ujian/{slug}/jadwal/{id}/pengawas/{pid}` | lepas penugasan |
| GET | `admin/ujian/{slug}/ketidakhadiran?jadwal_id=&kelas_id=` | daftar siswa + tanda tercatat |
| POST | `admin/ujian/{slug}/ketidakhadiran` | simpan pendataan satu kelas |
| GET | `admin/ujian/{slug}/susulan` | daftar — `q, kelas_id, mapel_id, status, alasan, per, page` |
| POST | `admin/ujian/{slug}/susulan/jadwalkan` | jadwalkan satuan/massal (`ids[]`) |
| POST | `admin/ujian/{slug}/susulan/{id}/status` | ubah status |
| DELETE | `admin/ujian/{slug}/susulan/{id}` | hapus catatan |
| GET | `admin/master/options?types=ujian_jenis,…` | daftar nilai tetap |

Catatan payload penting:
- **Ketidakhadiran** mengirim HANYA yang tidak hadir:
  `{"jadwal_id":1,"kelas_id":2,"tidak_hadir":[{"siswa_id":9,"alasan":"sakit","keterangan":"surat dokter"}]}`.
  Bentuk ringkas `"tidak_hadir":[9,10]` juga diterima (alasan jatuh ke `alpa`).
  Siswa yang **tidak** ikut dikirim dianggap hadir → catatannya dibatalkan,
  kecuali yang statusnya sudah `selesai` (dikunci, dilaporkan di
  `data.dipertahankan`).
- **Bentrok** memakai kode **409** dan menyertakan `data.bentrok` berisi
  baris yang bertabrakan, agar layar bisa menampilkannya langsung.
- Jam dikirim & dikembalikan sebagai `HH:MM`; tanggal `YYYY-MM-DD`.

### Step 10b — Endpoint cetak API ✅ SELESAI 2026-09-20

Diminta user setelah Step 10 di-push: aplikasi Android wajib bisa mencetak
juga. Ditambahkan `Api\Admin\UjianCetak` dengan 6 rute GET:

| Endpoint | Keluaran |
|---|---|
| `admin/ujian/{slug}/cetak` | JSON: daftar berkas yang tersedia |
| `admin/ujian/{slug}/cetak/rekap-pdf` | berkas PDF |
| `admin/ujian/{slug}/cetak/rekap-excel` | berkas XLSX |
| `admin/ujian/{slug}/cetak/jadwal-excel` | berkas XLSX (ber-kop) |
| `admin/ujian/{slug}/cetak/daftar-hadir/{jadwalId}` | berkas PDF |
| `admin/ujian/{slug}/cetak/berita-acara/{jadwalId}` | berkas PDF |

Bawaannya `Content-Disposition: inline` (PDF bisa langsung dipratinjau di
aplikasi); `?unduh=1` memaksa `attachment`. Galat tetap JSON beramplop, jadi
klien membedakannya lewat `Content-Type`.

**Perakitan berkas dipindah ke `App\Libraries\UjianCetak`** supaya web dan
mobile memakai satu mesin — kalau tidak, akan ada dua salinan perakitan
PDF/Excel yang pasti lekas berbeda isinya. Library itu kini memegang:
definisi kolom Excel jadwal (dipakai template, ekspor, **dan** parser impor
— satu definisi, mustahil melenceng), `rekapHtml`/`rekapSpreadsheet`,
`daftarHadirHtml`/`beritaAcaraHtml`, `nomorBeritaAcara`, serta perender
`pdf()`/`xlsx()`.
Akibatnya `Admin\LaporanUjian` menyusut dari 329 → 181 baris dan
`Admin\UjianBerkas` dari 525 → 425 baris; keduanya kini tinggal
menyelesaikan periode lalu melempar berkas.

**Teruji e2e HTTP (36/36, lulus sekali jalan):** kelima berkas terunduh &
valid; header `Content-Type`/`Content-Disposition` benar; `?unduh=1`
berfungsi; isi Excel dari API **identik (md5 sama)** dengan yang diunduh
lewat web, dan ukuran PDF berita acara sama persis; tanpa token → 401; slug
ngawur, sesi tak ada, sesi milik periode lain, dan tahun asing → 404 JSON;
periode kosong tetap menghasilkan berkas. **Regresi penuh Step 2–10
dijalankan ulang** (semua hijau; mode ketat juga, kecuali 2 kegagalan
pre-existing pada halaman laporan lama).
- [ ] **Step 11 — Flutter: Drawer sidebar + Jadwal Ujian**
      Bottom-nav `flutter-muslimin` sudah 6 tab — nambah tab ke-7 bikin sempit.
      Ganti/tambah **Drawer sidebar kiri** meniru
      `C:\flutter-galajuara\lib\features\admin\dashboard\presentation\admin_sidebar.dart`
      (pola `_Group`/`_Item`), lalu layar Jadwal Ujian.
- [ ] **Step 12 — Flutter: Ketidakhadiran + Susulan + Rekap**
      `flutter analyze` bersih + `flutter test` lolos → **ANDROID 100%**.
