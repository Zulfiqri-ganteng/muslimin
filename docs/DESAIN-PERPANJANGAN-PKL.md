# Rancangan: Surat Permohonan Perpanjangan PKL (+ rencana Sertifikat PKL)

Dibuat 2026-10-26. **Status: F1–F5 SELESAI & TERUJI LOKAL 2026-10-26 (belum commit). Pasang di server: `docs/PANDUAN-DEPLOY-PERPANJANGAN.md`.**
Layar Android menyusul (tahap P18, S13, S14 di repo Flutter). Sertifikat PKL: menu Segera, menunggu format (bagian 12).
Kerja per fase: satu fase → lapor → BERHENTI menunggu aba-aba. Centang kotak di bagian 11 tiap langkah selesai supaya sesi baru bisa
melanjutkan. Layar Android TIDAK dibuat di repo ini (dikerjakan AI di `C:\flutter-muslimin`); di sini cukup API + dokumen kontrak.

## 0. Keputusan user (2026-10-26)

| # | Pokok | Keputusan |
|---|---|---|
| 1 | Batas waktu ajuan siswa | Buka **H-7** sebelum tanggal selesai, tutup **H+30** sesudahnya; keduanya diatur di Pengaturan PKL (0 = tanpa batas). Staf boleh membuat kapan saja. |
| 2 | Siapa yang ACC | Sama dengan Surat Balasan (sekarang Operator); Admin bisa mengubah di Sistem → Hak Akses. |
| 3 | Teman sekelompok | Pengaju **mencentang** teman yang ikut diperpanjang (bawaan tidak tercentang). |
| 4 | Tanggal selesai lama belum tercatat | Siswa **mengetik sendiri** → ditandai kuning untuk dicek operator. |
| 5 | Berapa kali | **MAKSIMAL 1 KALI** per siswa per ajuan PKL (revisi user). |
| 6 | Isi surat | Pakai draf di bagian 5 (contoh hasil .docx dikirim di F1 untuk dicek). |
| 7 | Pilihan alasan | 4 alasan (bagian 5) + "lainnya". |
| 8 | API siswa publik untuk Android | Dibuat (bagian 8b). |
| 9 | Menu Sertifikat PKL | Grup PKL, sesudah "Data Prakerin", bertanda **Segera**. |

Urutan kerja: fitur ini (F1–F5) **dulu**; perapian tampilan halaman lama sesudahnya (`docs/RENCANA-RAPI-TAMPILAN-PKL-SURAT.md`).
Halaman baru fitur ini **langsung memakai pola tampilan baru** (1 tombol utama, cari-lalu-pilih, tombol massal muncul saat dicentang)
supaya tidak dirombak dua kali.

## 1. Fakta kode & data (dasar rancangan, dicek 2026-10-26)

- Belum ada fitur perpanjangan sama sekali.
- Tanggal PKL disimpan **per ajuan** (`pkl_pengajuan.tanggal_mulai/selesai`), diisi Surat Balasan (`SuratPkl::buat`) atau laporan
  siswa (`pkl_lapor`). Tidak ada tanggal per siswa.
- Data hosting 25-10 (`muslimin_h1025`): **153 dari 182 ajuan disetujui TIDAK punya tanggal selesai**; yang punya: 2 selesai 27 Okt,
  6 di November (mulai 10 Nov), 11 di Desember, 10 di Januari. → aturan 4 (siswa mengetik tanggal lama) akan sering terpakai.
- `SuratPkl` sudah menangani Surat Balasan + Penarikan (surat ke SATU tempat PKL, siswa pilihan, kunci anti-ganda, isian beku di
  `surat_sekolah.isi`). Perpanjangan = **jenis ketiga** di library yang sama → nomor, ACC, unduh, WA, lonceng, Excel agenda, Daftar Surat,
  API generik (`api/v1/surat/{id}/…`) ikut tanpa kode baru.
- `surat_sekolah.jenis` dan `surat_permintaan.jenis` bertipe **ENUM** → migrasi wajib menambah nilai `perpanjangan_pkl`.
- Status surat diputuskan di SATU tempat (`SuratKeputusan::terapkan`, juga dipakai ACC massal & "Simpan & setujui"). Rancangan ini
  **tidak menyisipkan kode di sana**: tanggal berlaku dihitung dari status surat (bagian 4c), jadi tak ada data yang harus disinkronkan.
- Hak per jenis disimpan sebagai kunci `surat_buat:{jenis}`, `surat_acc:{jenis}`, `surat_unduh:{jenis}` di tabel `peran.hak` (JSON).
  Jenis baru di hosting otomatis TANPA pemegang (kecuali Admin) → migrasi menyalin hak Balasan (bagian 4a).
- API membuat surat memakai slug = ujung alamat menu (`Api\Surat::jenisDariSlug`): alamat `admin/surat/perpanjangan` → slug
  `perpanjangan`.
- Form surat siswa = Layanan Siswa (`layanan/surat/{kunci}`, masuk NISN/tanggal lahir, `LayananSiswa::SURAT/SIAP/kelayakan`,
  contoh terdekat `ajukanPenarikan`); ikon di `LayananPublik::semua`. Android sekarang membuka surat siswa lewat web.

## 2. Alur

**Siswa (Layanan Siswa, tanpa login):** ikon "Minta surat perpanjangan PKL" → pilih kelas → nama → NISN/tanggal lahir → formulir 3 langkah
→ "Kirim permintaan" → status di "Permintaan suratku" (Menunggu ACC → Siap diambil di TU / Dikembalikan + alasan).

**Staf (web/Android):** permintaan masuk ke kotak "Permintaan dari siswa" (Daftar Surat) + lonceng → buka → ACC (1 klik) → Unduh Word
(nomor terbit saat unduh pertama) → "Kabari siswa via WhatsApp". Staf juga bisa membuat sendiri dari menu **Perpanjangan PKL** atau dari
detail ajuan (tombol "Buat Surat Perpanjangan", terisi otomatis).

**Status surat** (mesin yang sudah ada): `menunggu` → `disetujui` (tanggal baru BERLAKU) · `dikembalikan` (siswa/staf perbaiki) ·
`dibatalkan` (tak berlaku; siswa boleh minta lagi). Batal ACC → kembali `menunggu` → tanggal lama berlaku lagi.

## 3. Aturan bisnis

| Kode | Aturan |
|---|---|
| R1 | Syarat: ajuan PKL **disetujui**, siswa masih anggota aktif ajuan itu (`pkl_anggota.siswa_aktif` terisi), tidak dikeluarkan, dan tidak punya Surat Penarikan yang belum dibatalkan. |
| R2 | Jendela siswa: `selesai_lama − H_sebelum ≤ hari ini ≤ selesai_lama + H_sesudah` (setelan; 0 = tanpa batas). Terlalu dini → "Bisa diajukan mulai {tanggal}". Lewat → "Sudah lewat batas, hubungi operator". **Staf tidak dibatasi jendela.** |
| R3 | Tanggal selesai lama: dari ajuan bila tercatat (sumber `sekolah`, terkunci di form siswa). Kosong → diketik siswa (sumber `siswa`, lencana kuning "diisi siswa — cek") atau staf (sumber `staf`). Wajib ≥ tanggal mulai ajuan bila ada; tahun masuk akal (tahun lalu s.d. tahun depan). Jendela R2 dihitung dari tanggal ini. |
| R4 | Tanggal selesai baru > tanggal lama. Perpanjangan mulai = tanggal lama + 1 hari. Lama perpanjangan maks **183 hari** (ditolak). Peringatan kuning (tidak memblokir, sama dengan `SuratPkl::peringatanPeriode`): melewati "selesai paling akhir" sekolah, atau total PKL (mulai ajuan → selesai baru) melewati "durasi maks" Pengaturan. |
| R5 | **Maksimal 1×** per siswa per ajuan: ditolak bila sudah ada baris perpanjangan siswa itu di ajuan itu yang suratnya BELUM dibatalkan ("Kamu sudah pernah diperpanjang: SRT-xxxxx"). Pindah tempat PKL (ajuan lain) = hitungan baru. |
| R6 | Satu surat per **ajuan + tanggal selesai baru**. Teman/penyusul dengan tanggal sama ikut surat itu selama surat masih bisa diubah (belum bernomor, `SuratSekolah::alasanTakBisaUbah` = null); kalau sudah bernomor → surat baru. Tanggal baru berbeda → surat terpisah. |
| R7 | Teman: centang teman satu ajuan (bawaan tidak tercentang). Teman yang sudah pernah diperpanjang / punya permintaan aktif tampil redup dengan kode suratnya. Teman yang dicantumkan melihat di "Permintaan suratku": "Namamu dicantumkan oleh temanmu, X." |
| R8 | **Tanggal baru berlaku hanya bila surat berstatus `disetujui`** (sesudah ACC, atau langsung bila Admin mematikan ACC jenis ini). Dihitung lewat JOIN (bagian 4c), tanpa menyalin ke tabel lain. |
| R9 | `pkl_pengajuan.tanggal_selesai` **tidak ditimpa** — Surat Balasan lama tetap benar; tanggal efektif = hasil hitung R8. Status ajuan tidak berubah. |
| R10 | Nomor surat: urutan bersama (`SuratNomor`), terbit sekali saat unduh pertama. Ubah data surat hanya selama belum bernomor. |
| R11 | Hak: `surat_buat/acc/unduh:perpanjangan_pkl`, bawaan & migrasi = sama dengan Surat Balasan; Admin mengubah di Hak Akses. |
| R12 | Jejak: riwayat surat (sudah ada) + baris `pkl_riwayat` ajuan aksi `perpanjang` ("Perpanjangan PKL s.d. {tgl} diminta — SRT-xxxxx") + Audit Log. |

## 4. Data — 1 migrasi `2026-10-27-000001_PklPerpanjangan` (idempoten, punya `down()`)

### 4a. Isi migrasi
1. `ALTER TABLE surat_sekolah MODIFY jenis ENUM(…lama…, 'perpanjangan_pkl')` dan sama untuk `surat_permintaan.jenis`.
   `down()` mengembalikan ENUM hanya bila belum ada baris `perpanjangan_pkl` (kalau ada: berhenti dengan pesan, data tak dihapus diam-diam).
2. Tabel baru `pkl_perpanjangan` — **DIBANGUN F1 sebagai satu baris per SURAT** (bukan per siswa seperti draf awal): semua siswa satu
   surat berbagi tanggal yang sama (R6), siswanya = `surat_sekolah_siswa`, jadi daftar siswa tidak disalin dua kali (tidak perlu disinkronkan).

| Kolom | Tipe | Catatan |
|---|---|---|
| id | INT UNSIGNED AI PK | |
| surat_id | INT UNSIGNED | UNIQUE, FK `surat_sekolah.id` ON DELETE CASCADE |
| pengajuan_id | INT UNSIGNED | FK `pkl_pengajuan.id` ON DELETE CASCADE |
| selesai_lama | DATE | tanggal selesai sebelum diperpanjang |
| sumber_lama | VARCHAR(8) | sekolah · siswa · staf — asal tanggal lama (R3) |
| selesai_baru | DATE | |
| created_at, updated_at | DATETIME | |

   Kunci: `UNIQUE(surat_id)`, `INDEX(pengajuan_id)`, `INDEX(selesai_baru)`. Status TIDAK disimpan di sini (dibaca dari surat).
3. `pkl_pengaturan` + `perpanjang_buka` TINYINT(1) DEFAULT 1 · `perpanjang_hari_sebelum` SMALLINT DEFAULT 7 · `perpanjang_hari_sesudah`
   SMALLINT DEFAULT 30.
4. Hak: tiap baris `peran` yang punya `surat_buat:balasan_pkl` / `surat_acc:balasan_pkl` / `surat_unduh:balasan_pkl` mendapat kunci
   `…:perpanjangan_pkl` yang sama (hanya menambah, tidak mencabut; dicatat di Audit Log). `down()` mencabut kunci perpanjangan saja.

Tanpa data pribadi di migrasi. Uji: `migrate` → `migrate:rollback -f` → `migrate` di DB lokal + salinan hosting `muslimin_h1025`.

### 4b. Penjaga ganda & balapan (R5, R6)
Pembuatan surat dalam SATU transaksi: kunci baris anggota dulu
`SELECT id FROM pkl_anggota WHERE pengajuan_id = ? AND siswa_id IN (…) FOR UPDATE`, baru periksa R5 dan kunci surat (R6), lalu insert
`surat_sekolah` + `surat_sekolah_siswa` + `pkl_perpanjangan` + `surat_permintaan` + riwayat. Gagal di mana pun → rollback. Dua klik
"Kirim" beruntun atau dua teman bersamaan tidak bisa menghasilkan perpanjangan ganda.

### 4c. Tanggal selesai efektif (satu fungsi baca, dipakai semua layar/API/sertifikat)
`SuratPerpanjangan::selesaiEfektif(array $siswaIds): array` (library `app/Libraries/SuratPerpanjangan.php`) — dua query tetap, tanpa N+1:
anggota ajuan disetujui + `aktif()` (perpanjangan yang belum dibatalkan):

```sql
SELECT ss.siswa_id, s.id, s.status, pp.pengajuan_id, pp.selesai_lama, pp.sumber_lama, pp.selesai_baru
FROM pkl_perpanjangan pp
JOIN surat_sekolah s        ON s.id = pp.surat_id AND s.jenis = 'perpanjangan_pkl' AND s.status <> 'dibatalkan'
JOIN surat_sekolah_siswa ss ON ss.surat_id = s.id
WHERE ss.siswa_id IN (…)
```
Efektif = `selesai_baru` bila suratnya `disetujui`, selain itu tanggal di ajuan (bisa null). Bentuk keluaran:
`siswa_id => {ajuan_id, selesai_ajuan, selesai, diperpanjang, menunggu, perpanjangan}`. Status Siswa (`PklPengajuanModel::sqlSiswaTurunan`)
memakai subkueri yang sama sehingga fase "Selesai PKL" ikut tanggal efektif (kolom `selesai_efektif`).

## 5. Surat Word `app/Libraries/Surat/perpanjangan_pkl.docx`

Dibuat dari `balasan_pkl.docx` dengan skrip sekali pakai di scratchpad (pola L3–L6, `docs/DESAIN-SURAT-SEKOLAH.md`): kop, kertas
21,5×33, Nomor/Lampiran/Perihal, tabel NO | NAMA | KELAS | KONSENTRASI KEAHLIAN | NOMOR HANDPHONE, blok TTD Waka Hubin + Kepsek, NB,
`${acc_footer}` SAMA persis; hanya badan surat diganti. Metadata penyusun dihapus (repo publik).

```
Nomor    : ${nomor}
Lampiran : -
Perihal  : Permohonan Perpanjangan Waktu PKL

Kepada Yth.
Bapak/Ibu ${sapaan}
${perusahaan}
Di Tempat

Dengan hormat,
Sehubungan dengan pelaksanaan Praktik Kerja Lapangan (PKL) peserta didik SMK Bina Nusa Kab. Bekasi di ${perusahaan} yang
berakhir pada tanggal ${selesai_lama}, dengan ini kami mengajukan permohonan perpanjangan waktu PKL bagi peserta didik berikut:

[tabel siswa]

Perpanjangan PKL tersebut kami ajukan dengan ketentuan sebagai berikut:
Perpanjangan       : ${lama_perpanjangan}, ${periode_perpanjangan}      ← mis. "1 (satu) bulan, 31 Oktober s.d. 30 November 2026"
Lokasi Penempatan  : ${perusahaan}
Alamat             : ${alamat_lengkap}

Perpanjangan ini kami ajukan karena ${alasan}. Selama masa perpanjangan, peserta didik tetap wajib mematuhi tata tertib, menjaga etika
dan disiplin kerja, serta mengikuti arahan pembimbing lapangan yang ditunjuk oleh perusahaan.

Demikian surat permohonan ini kami sampaikan. Atas perhatian, kesediaan, dan kerja sama yang baik, kami mengucapkan terima kasih.

Bekasi, ${tanggal_surat}
[blok TTD Waka Hubin + Kepala Sekolah]  ·  NB  ·  ${acc_footer}
```
**Dibangun F1:** tabel ketentuan "Periode / Lokasi / Alamat" Surat Balasan dipakai ulang (baris pertama jadi "Perpanjangan") supaya tinggi
surat sama → di Word asli 1 halaman untuk 2 dan 5 siswa. Skrip pembuat: `buat_template_perpanjangan.php` (scratchpad sesi c9c4625e, tidak di
repo; berhenti sendiri bila paragraf template Balasan berubah). Alamat dibekukan di isi surat (`alamat`) seperti Surat Balasan.

- `${lama_perpanjangan}` = `SuratPerpanjangan::lamaTeks(selesai_lama, selesai_baru)` (gaya "1 (satu) bulan" bila genap bulan ±3 hari; selain itu "N (…) hari").
- Alasan (`SuratPerpanjangan::ALASAN`, kalimat sesudah "karena …"; label pendek untuk tombol siswa):
  0. adanya permintaan dari pihak perusahaan/instansi tempat PKL — *Permintaan perusahaan*
  1. peserta didik belum menyelesaikan target kegiatan PKL yang ditetapkan — *Target PKL belum selesai*
  2. adanya penyesuaian jadwal kegiatan di tempat PKL — *Jadwal di tempat PKL berubah*
  3. permintaan peserta didik dan orang tua/wali untuk menambah pengalaman kerja — *Permintaan siswa & orang tua*
  - lainnya: ketik sendiri (maks 200 huruf, huruf pertama kecil otomatis seperti Penarikan).
- Nama berkas: `{urut} Surat Perpanjangan PKL - {perusahaan} ({n} siswa)` (pola Balasan, `SuratBerkas::namaBerkas`).
- Pemeriksaan di Word asli (PowerShell COM, baca saja) ✅ 2026-10-26: 1 halaman untuk 2 dan 5 siswa. Contoh hasil (data karangan):
  `downloadpublic/CONTOH HASIL - Surat Perpanjangan PKL (2 siswa).docx` dan `(5 siswa).docx` (folder diabaikan git).

## 6. Layar staf (web) — pola tampilan baru

- **Menu:** "Perpanjangan PKL" di grup SURAT SEKOLAH, sesudah "Lapor Diterima PKL", sebelum "Penarikan Izin PKL" (urutan alur PKL).
  `SuratJenis::DATA` + `SIAP` (selama F1 belum masuk SIAP → otomatis tampil "Segera").
- **Halaman `admin/surat/perpanjangan`** (satu kartu, dari atas ke bawah):
  1. Kotak cari "Ketik nama siswa atau perusahaan" → maks 10 hasil ajuan disetujui → pilih. (Bukan daftar 182 ajuan sekaligus.)
  2. Kartu ajuan terpilih: perusahaan, "PKL selesai: 30 Oktober 2026" (+ lencana kuning bila diisi siswa / isian tanggal bila kosong),
     centang siswa (yang sudah pernah diperpanjang redup + kode suratnya).
  3. "Diperpanjang sampai": tombol cepat **+2 minggu · +1 bulan · +2 bulan · +3 bulan · Pilih tanggal** + kalimat ringkas
     "Diperpanjang 31 hari: 31 Oktober s.d. 30 November 2026." + peringatan kuning bila ada.
  4. Alasan (pilihan + lainnya), sapaan, tanggal surat (bawaan hari ini).
  5. SATU tombol utama: **"Simpan & setujui"** bila memegang hak ACC, selain itu **"Buat & ajukan ACC"**. Tombol "Batal" teks biasa.
- **Pintu cepat:** detail ajuan (kartu Surat sekolah) → "Buat Surat Perpanjangan" (`?ajuan=ID`, terisi); Beranda PKL kartu
  "PKL berakhir ≤ 7 hari: N siswa" → daftar → "Buat surat".
- **Detail surat:** blok "Perpanjangan": selesai lama (+ sumber) → selesai baru, lama, "Berlaku setelah ACC" / "Sudah berlaku".
- **Detail ajuan:** baris per siswa "Selesai PKL: 30 Nov 2026 (diperpanjang, SRT-xxxxx)".
- **Status Siswa + Excel:** kolom BARU "Diperpanjang s.d." di AKHIR (kolom lama tidak digeser); saringan "Akan berakhir ≤ 7 hari".
- **Pengaturan PKL:** bagian "Perpanjangan PKL": saklar form siswa + H-sebelum + H-sesudah.
- **WA:** `SuratWa::JENIS` + `PESAN_JENIS[perpanjangan_pkl]` = "Halo {nama}, {surat} untuk {perusahaan} (No. {nomor_surat}) sudah selesai
  dan siap diambil di Tata Usaha {sekolah}. Setelah diambil, serahkan ke perusahaan tempat PKL-mu. Terima kasih."
- Komponen baru yang dibutuhkan (kotak cari-lalu-pilih, tombol cepat tanggal) dibuat di kit `resources/js` + `admin.css` sebagai komponen
  bersama — nanti dipakai ulang di perapian tampilan (U1). Siap CSP (JS/CSS terpisah), 3 tema, HP 360 px.

## 7. Form siswa (Layanan Siswa)

- `LayananPublik`: ikon `surat_perpanjangan` "Minta surat perpanjangan PKL" (bagian "Surat dari sekolah", ilustrasi
  `surat-perpanjangan.svg` bergaya sama), status "Sedang ditutup" bila saklar mati. `LayananSiswa::SURAT['perpanjangan']` + `SIAP` +
  `kelayakan()` (R1, R2, R5).
- Halaman `layanan/surat/perpanjangan` (JS kecil hanya untuk tombol cepat & kalimat ringkas; tanpa JS pun tetap bisa dikirim):
  - Kepala: "PKL-mu di **PT X** berakhir **30 Oktober 2026** (4 hari lagi)."
  - Terlalu dini / lewat batas / sudah pernah diperpanjang → hanya kartu info + status, tanpa formulir.
  - Langkah 1 "PKL-mu selesai tanggal…" (terkunci bila dari sekolah; isian tanggal bila kosong + petunjuk "lihat Surat Balasan atau tanya
    pembimbing di perusahaan").
  - Langkah 2 "Diperpanjang sampai…" (tombol cepat + Pilih tanggal + kalimat ringkas).
  - Langkah 3 "Kenapa diperpanjang?" (4 tombol alasan + lainnya) dan "Teman yang juga diperpanjang" (centang, bawaan kosong).
  - Tombol "Kirim permintaan". Sesudah kirim → `layanan/saya#status`.
- Pengaman sama dengan layanan lain: sesi masuk 30 menit, CSRF, jebakan bot, batas percobaan masuk (LoginThrottle).

## 8. API

### 8a. Staf (Bearer, gerbang `api_akses` "surat"/"pkl", hak per jenis — library sama dengan web)
| Metode & alamat | Isi |
|---|---|
| `GET surat/meta` | jenis `perpanjangan_pkl` ikut otomatis + `alasan_perpanjangan[]` + `perpanjang{buka, hari_sebelum, hari_sesudah}` |
| `GET surat/form/perpanjangan?q=` / `?ajuan_id=` | hasil cari ajuan / satu ajuan: siswa + `perpanjangan` (status, kode surat), `selesai_lama` + `sumber`, peringatan |
| `POST surat/perpanjangan` | `{ajuan_id, siswa_ids[], selesai_lama?, selesai_baru, alasan_pilih \| alasan_lain, sapaan, tanggal_surat, setujui?}` → `{dibuat[], ikut[], gagal[], peringatan[]}`; 422 dengan `galat{kolom: pesan}` |
| `GET surat/{id}` | + blok `perpanjangan{selesai_lama, sumber_lama, mulai, selesai_baru, lama_teks, berlaku}` |
| ACC/kembalikan/batal ACC/batal/unduh/ubah/WA/ACC massal/unduh massal | rute generik yang sudah ada — tanpa rute baru |
| `GET pkl/berakhir?hari=7` | siswa PKL berakhir ≤ N hari (tanggal efektif) + status perpanjangan |
| `GET pkl/ringkasan` | + `berakhir_7`, `perpanjangan_menunggu` |
| `GET pkl/ajuan/{id}`, `GET pkl/siswa` | + per siswa `selesai_efektif`, `diperpanjang` |

### 8b. Siswa publik (tanpa Bearer — untuk layar Android asli)
| Metode & alamat | Isi |
|---|---|
| `GET publik/layanan/kelas` · `GET publik/layanan/siswa?kelas_id=` | sama dengan langkah web |
| `POST publik/layanan/masuk` | `{siswa_id, nisn \| tanggal_lahir}` → `{token, berlaku_sampai, profil}`; batas percobaan sama dengan web |
| `GET publik/layanan/perpanjangan` | header `X-Layanan-Token` → kelayakan + data formulir |
| `POST publik/layanan/perpanjangan` | kirim permintaan → `{kode: baru\|ikut\|sudah, surat_kode, pesan}` |
| `GET publik/layanan/status` | daftar "Permintaan suratku" (semua jenis, sama dengan `layanan/saya`) |

Token = tanda tangan HMAC (kunci aplikasi) atas `siswa_id + kedaluwarsa 30 menit` — tanpa tabel baru, tanpa data pribadi di token.
Logika tetap `LayananSiswa` (satu sumber web + API).

Dokumen: `docs/API-SURAT.md` (perpanjangan), `docs/API-PKL.md` (berakhir, efektif), `docs/API-LAYANAN-SISWA.md` (baru).
Uji: `dev:uji-surat` bagian perpanjangan, `dev:uji-api-android`, fixture lewat `UJI_FIXTURE=<folder>`.

## 9. Kontrak Flutter (repo `C:\flutter-muslimin`, dokumen saja, tidak di-commit)
- `BLUEPRINT-SURAT.md` blok PEMBARUAN: tahap **S13** layar Perpanjangan PKL staf (butuh S0–S2) + **S14** layar siswa publik (form 3 langkah +
  status) memakai 8b.
- `BLUEPRINT-PKL.md`: **P18** kartu Beranda "PKL berakhir ≤ 7 hari" + "Selesai PKL (diperpanjang)" di Detail & Status Siswa.
- `PROGRES-KERJA.md` (tambah tahap), `CLAUDE.md` (penunjuk), fixture (dibuat: satu folder `test/fixtures/perpanjangan/`, 17 berkas).
- Susunan layar mengikuti pola baru (1 tombol utama, cari-lalu-pilih, tombol cepat tanggal).

## 10. Kewaspadaan kesalahan manusia

| Kejadian | Respon sistem |
|---|---|
| Tanggal baru ≤ tanggal lama / lebih dari 183 hari | Ditolak dengan pesan jelas |
| Melewati batas sekolah / total PKL kepanjangan | Peringatan kuning (tidak memblokir; surat dilewati ACC massal) |
| Tanggal lama diketik siswa | Lencana kuning "diisi siswa — cek" di kotak staf & detail surat |
| Minta ke-2 kali | Ditolak (R5) dengan kode surat sebelumnya |
| Dua klik "Kirim" / dua teman bersamaan | Transaksi + kunci baris (4b) |
| Teman beda tanggal | Surat terpisah otomatis (R6) |
| Teman dicantumkan tanpa tahu | Muncul di "Permintaan suratku" miliknya + bisa dikabari WA |
| Perusahaan batal setelah ACC | Batal ACC / Batalkan surat → tanggal lama berlaku lagi (R8) |
| Siswa sudah ditarik / dikeluarkan | Ditolak (R1) |
| Form ditutup sekolah | Ikon "Sedang ditutup"; staf tetap bisa membuat |

## 11. Fase & daftar centang

**F1 Fondasi ✅ 2026-10-26** (dikerjakan bersama F2 atas permintaan user)
- [x] Migrasi `2026-10-27-000001_PklPerpanjangan` (ENUM, tabel per surat, setelan, hak = hak Balasan) — migrate/rollback/migrate lokal ✅, `muslimin_h1025` ✅
- [x] `SuratJenis::PERPANJANGAN` (label "Surat Perpanjangan PKL", `admin/surat/perpanjangan`, bernomor, ACC, lencana kuning) + LABEL_ISI/NILAI_ISI
- [x] `Libraries\SuratPerpanjangan` (library sendiri, pola SuratPernyataan — kode Balasan/Penarikan tidak disentuh): R1–R6, jendela R2,
      `aktif()`, `selesaiEfektif()`, `ditarik()`, `ajuanForm()`, `cari()`, `berakhir()`, `buat()`/`ubahSurat()` dalam transaksi + `FOR UPDATE`, `token()`
- [x] Sambungan: `SuratBerkas` (penanda + kaki 2 baris), `SuratPeriksa` (butuh siswa/perusahaan + peringatan), `SuratWa` (pesan siap diambil)
- [x] Template `perpanjangan_pkl.docx` + 2 contoh hasil + Word asli 1 halaman (2 & 5 siswa)
- [x] Menu: "Surat Perpanjangan PKL" (SURAT SEKOLAH, sesudah Lapor Diterima) + **"Sertifikat PKL" Segera** (entri tersendiri = hanya Admin,
      sesuai aturan menu Segera; di akhir grup PKL — pindah ke sesudah Data Prakerin saat dibangun)
- [x] Uji baru `php spark dev:uji-perpanjangan` (67 cek) + regresi (bagian 11b)

**F2 Layar staf ✅ 2026-10-26** — [x] halaman Perpanjangan cari→pilih→isi (Admin\SuratPerpanjangan, view perpanjangan_form, js surat-perpanjangan.js) ·
[x] pintu cepat detail ajuan + kartu Beranda PKL "berakhir ≤ 7 hari" · [x] baris "Perpanjangan … Berlaku setelah ACC" di detail surat + lencana per siswa di
detail ajuan · [x] Status Siswa: fase ikut tanggal efektif + lencana, Excel kolom L "Diperpanjang s.d." · [x] Pengaturan PKL bagian Perpanjangan ·
[x] WA · [x] masuk SIAP · [x] API `surat/form|buat|ubah` jalur perpanjangan (dirapikan/didokumentasikan di F4) · [x] peramban: CSP 0, galat JS 0, HP 390 tidak melebar

**11b. Uji akhir F1+F2 (2026-10-26):** perpanjangan 67/0 · surat 401/0 · pkl 339/0 · hak 69/0 · lapor 114/0 · pkl-keluar 132/0 · pkl-biaya 130/0 ·
api-android 19/0 · cek-ui 0 temuan. Harapan uji lama yang disesuaikan (bukan logika): daftar 5→6 jenis & 4→5 bernomor (uji-surat, uji-hak),
Excel judul A:K→A:L (uji-pkl-keluar), pengecualian alamat baru `pkl/sertifikat` (uji-hak).

**F3 Form siswa ✅ 2026-10-26** — [x] ikon "Minta surat perpanjangan PKL" (`LayananPublik` `surat_perpanjangan`, ilustrasi `surat-perpanjangan.svg`,
status buka/tutup = saklar Pengaturan) · [x] `LayananSiswa::SURAT['perpanjangan']` + SIAP + kelayakan + `formPerpanjangan()` + `ajukanPerpanjangan()`
(diri + teman dicentang dalam SATU surat; teman dapat baris permintaan "diisikan oleh") · [x] view `layanan/surat_perpanjangan.php` 4 langkah TANPA
JavaScript: PKL sekarang (tanggal semula / isian bila belum tercatat) → Diperpanjang sampai (pilihan cepat bertanggal +2 minggu, +1/2/3 bulan, atau
tanggal lain) → Alasan → Teman · [x] info tanpa formulir: sudah pernah / ditarik / terlalu dini / lewat batas · [x] status & catatan "Namamu dicantumkan
oleh temanmu" · [x] kotak "Permintaan dari siswa" + lonceng (otomatis lewat LayananSiswa::SURAT) · [x] uji 14 cek baru (total 81/0) + peramban salinan
hosting: masuk NISN → formulir → kirim → layanan/saya#status, CSP 0, galat JS 0, HP 390 tidak melebar. Catatan: `dev:cek-ui` tidak mencakup folder
`layanan/` (celah lama — tidak ada skrip sebaris di view baru, diperiksa uji).

**F4 API ✅ 2026-10-26** — [x] 8a staf: `surat/form|buat|ubah` perpanjangan (422 per kolom, 409 "sudah"), `surat/{id}.perpanjangan`,
`pkl/ringkasan.perpanjangan`, **`GET pkl/berakhir`** (baru), `pkl/ajuan/{id}` anggota[].perpanjangan + selesai_efektif + hak.buat_perpanjangan,
`pkl/siswa` tanggal + selesai_efektif + diperpanjang · [x] 8b siswa publik `Api\Layanan` (kelas, siswa, masuk → token, saya, perpanjangan GET/POST)
+ `Libraries\LayananToken` (HMAC, 30 menit, header `X-Layanan-Token`, CORS diizinkan); `LayananSiswa::masuk()` dipecah → `cocokkan()` (perilaku web
sama) · [x] docs `API-SURAT.md` (blok 2026-10-26b), `API-PKL.md` (pembaruan 2026-10-26), **`API-LAYANAN-SISWA.md`** (baru) · [x] uji: perpanjangan
103/0 (22 cek API) + HTTP sungguhan lewat rute & filter 14/0 (salinan hosting) + regresi semua hijau · [x] fixture: `UJI_FIXTURE=<folder>` menulis
19 respons nyata (dipakai F5).

**F5 Penutup ✅ 2026-10-26** — [x] kontrak Flutter (repo `C:\flutter-muslimin`, dokumen saja): `BLUEPRINT-SURAT.md` blok "PEMBARUAN
2026-10-26b" (S13 staf, S14 siswa), `BLUEPRINT-PKL.md` blok P18, `PROGRES-KERJA.md` (84 tahap, 46 sisa), `CLAUDE.md` penunjuk, **17 fixture
nyata** `test/fixtures/perpanjangan/` (data fiktif; daftar `pkl_berakhir` disaring hanya data uji) · [x] regresi semua uji hijau (bagian 11c) ·
[x] `docs/PANDUAN-DEPLOY-PERPANJANGAN.md` · [x] memori

**11c. Uji akhir F1–F5 (2026-10-26):** perpanjangan 104/0 · surat 401/0 · pkl 339/0 · hak 69/0 · lapor 114/0 · pkl-keluar 132/0 ·
pkl-biaya 130/0 · api-android 19/0 · cek-ui 0 · HTTP sungguhan (rute + filter, salinan hosting) 14/0 · peramban staf & siswa (salinan
hosting) CSP 0, galat JS 0, HP 390 tidak melebar · Word asli 1 halaman (2 & 5 siswa).

**Deploy (user):** `cd ~/kangmuslim && git pull && php spark migrate` lalu Ctrl+F5. Cadangkan DB dulu (migrasi mengubah ENUM).

## 12. Sertifikat PKL — RENCANA (format belum ada)

- **Sekarang (F1 ✅):** entri menu "Sertifikat PKL" (ikon `award`, `'segera' => true`) di akhir grup PKL — tampil redup bagi Admin, tak bisa
  diklik (aturan menu Segera: hanya Admin). Saat dibangun: jadi item biasa sesudah "Data Prakerin".
- **Data yang sudah tersedia di sistem:** nama, NIS/NISN, tempat & tanggal lahir (data siswa format 8355), kelas, konsentrasi keahlian,
  perusahaan + alamat, periode = mulai ajuan → **tanggal selesai efektif** (sudah termasuk perpanjangan, `SuratPerpanjangan::selesaiEfektif`)
  atau tanggal efektif penarikan, nama Kepsek & Waka Hubin + TTD digital.
- **Pertanyaan saat format datang:** penanda tangan (Kepsek saja / + Waka Hubin / + pembimbing DUDI) · ada nilai/predikat? (siapa
  mengisi) · format nomor sertifikat · kertas & arah (A4 mendatar?) · kode QR cek keaslian (halaman publik "cek sertifikat") · foto siswa?
- **Syarat terbit (usulan):** ajuan disetujui, tanggal selesai efektif ≤ hari ini, tidak ditarik/dikeluarkan, nilai terisi (bila dipakai).
  1 siswa = 1 sertifikat per ajuan (UNIQUE), terbit ulang = nomor sama.
- **Fase nanti:** S1 data + pengaturan (nomor, penanda tangan, nilai) · S2 template persis format sekolah · S3 terbit massal per kelas /
  per perusahaan + unduh ZIP + QR verifikasi · S4 API + kontrak Flutter.
- **Rujukan pola:** `Admin\SertifikatUkk` (1 peserta = 1 sertifikat, nomor otomatis), mesin Word `PklDocx`.
