# SKBM — SK Pembagian Tugas Mengajar (per tahun ajaran)

Menu baru **Guru → SKBM** (khusus Admin). Menyimpan salinan SK Pembagian Tugas Mengajar (Lampiran 3) per tahun ajaran:
siapa mengajar **mapel apa** di **kelas mana** dan berapa **JP** per minggu. Menjadi sumber ceklis **Koreksi honor**
(Honor → Koreksi → "Isi dari SKBM") dan bisa dibandingkan dengan data Penugasan (pengampu).

> Repo ini publik: dokumen, kode, dan migrasi **tidak boleh memuat nama orang atau data pribadi**. Berkas Excel sekolah
> dibaca dari server (`formatdatasekolah/` lokal, diabaikan git), bukan dari repo.

## 1. Keputusan rancangan

| Keputusan | Alasan |
|---|---|
| Tabel sendiri (`skbm_*`), **tidak** menimpa `pengampu` | `pengampu` dipakai Jadwal KBM, Rekap Beban, Cetak, dan API. SKBM = salinan SK resmi; menyelaraskannya ke Penugasan adalah keputusan tersendiri. |
| Per **tahun ajaran** (`2026/2027`) | SK terbit per tahun ajaran. Honor ujian punya tahun ajaran dari periodenya, jadi sambungannya langsung. |
| Khusus **Admin** | Sumber data gaji (honor). Rute tidak ada di daftar hak Operator/Waka Hubin; controller memeriksa ulang peran. |
| Mapel disimpan sebagai **teks** (+ `mapel_id` bila dikenal) | Berkas sekolah menulis nama mapel bebas; satu guru bisa punya beberapa baris mapel, atau "-" (tanpa mapel). |
| Isi sel = **JP** (NULL = mengajar tetapi JP belum diisi) | Sel di Excel sekolah berisi angka JP. Ditandai kuning ✓ supaya yang belum lengkap kelihatan. |
| Ceklis Koreksi diisi lewat **satu klik eksplisit** | Tidak otomatis saat halaman dibuka (menghindari perubahan data diam-diam). Bila ceklis kosong dan SKBM ada, panel "Isi dari SKBM" langsung terbuka. |

## 2. Data (migrasi `2026-10-16-000001_Skbm` dan `2026-10-16-000002_SkbmSk`, idempoten)

- `skbm_mapel`: `tahun_ajaran`, `guru_id` (CASCADE), `mapel_nama`, `mapel_id` (SET NULL), `kode` (kode guru di SK, mis. 3A), `urut`.
- `skbm_sel`: `skbm_mapel_id` (CASCADE), `kelas_id` (CASCADE), `jp` (NULL = belum diisi); unik `(skbm_mapel_id, kelas_id)`.
- `skbm_sk`: `tahun_ajaran` (kunci), `nomor` (nomor SK, ≤ 120 huruf), `updated_at` — tercetak di baris "Nomor :" pada Excel.
- Nomor guru & kode di layar: angka di depan kode tersimpan dari SK (impor) bila ada, kalau tidak dilanjutkan dari nomor sebelumnya; kode baris = kode tersimpan, atau
  diturunkan: satu baris = `{no}`, beberapa baris = `{no}A`, `{no}B` (`HonorKoreksi::kodeGuru`).
- Batas: 900 baris mapel per tahun, 40 baris per guru, JP 1–40, nama mapel ≤ 150 huruf.

## 3. Halaman dan rute (semua `admin/skbm…`, POST memakai filter `csrf`)

| Rute | Fungsi |
|---|---|
| `GET skbm?tahun=` | Matriks guru × mapel × kelas; tile Guru / Baris mapel / Guru×kelas / Total JP / JP belum diisi / Beda dengan Penugasan. |
| `POST skbm/sel` (JSON) | Nyalakan/matikan sel, isi JP. Balasan memuat angka resmi (JP per baris, guru, kelas, total) + token CSRF baru. |
| `POST skbm/mapel`, `…/mapel/{id}/ubah` (JSON), `…/mapel/{id}/hapus`, `…/guru/{id}/hapus` | Tambah baris, ganti nama, hapus baris, keluarkan guru. |
| `POST skbm/salin`, `POST skbm/kosongkan` | Salin dari tahun lain (tujuan harus kosong kecuali "ganti"); kosongkan satu tahun. |
| `POST skbm/impor/unggah` → `GET skbm/impor?blok=` → `POST skbm/impor/terapkan` | Impor Excel dengan pratinjau; pencocokan dihitung ulang di server saat Terapkan. |
| `GET skbm/bandingkan?tahun=` | Selisih dengan Penugasan (hanya membandingkan). |
| `POST ujian/{jenis}/honor/koreksi/isi-skbm` | Isi ceklis Koreksi honor dari SKBM tahun ajaran honor itu. |

Di layar: **klik** sel = nyala/mati; **klik kanan** (atau tombol mode "Klik = isi JP", untuk layar sentuh) = isi JP.
Pengiriman diantre satu per satu; token CSRF baru dari tiap balasan dipasang ke semua isian token di halaman.

### 3.1 Dua tampilan: Tabel dan Per guru
- **Tabel** (bawaan di layar lebar): matriks guru × mapel × kelas — nyaman di laptop.
- **Per guru** (bawaan di layar < 768 px, pilihan diingat di peramban): satu kartu per guru yang dapat dibuka; tiap baris mapel menampilkan chip kelas
  (ketuk = nyala/mati, ketuk lama atau mode "Klik = isi JP" = isi JP), dikelompokkan per kelas/shift. Kartu **dibangun dari tabel** oleh
  `public/assets/js/admin/kartu-guru.js` (satu sumber data; klik chip diteruskan ke sel tabel; angka tetap dari server) dan diisi saat dibuka (ringan di HP).
  Pemakaian sama di halaman Koreksi.
- **Unduh Excel SKBM** (`GET admin/skbm/xlsx?tahun=`; `Libraries\SkbmCetak`): **sama dengan lembar "SKBM 2026-2027" di berkas sekolah** (yang tampak di sana; blok kiri yang
  disembunyikan sekolah tidak ditiru): judul 4 baris (DAFTAR LAMPIRAN 3 / SURAT KEPUTUSAN KEPALA <SEKOLAH> / TAHUN PELAJARAN / Nomor : <nomor SK>), header ungu 3 baris
  (NO · NAMA GURU · KODE GURU · MATA PELAJARAN, 42 kolom kelas dengan nama kelas miring 90°, JUMLAH JP · JUMLAH TOTAL JP · JUMLAH KOREKSI), NO & nama hanya di baris pertama
  tiap guru, JUMLAH TOTAL JP & KOREKSI digabung per guru, baris TOTAL JP / KELAS dan TOTAL JP SELURUHNYA (rumus hidup), nama kepala sekolah + NRKS dari Pengaturan Sekolah,
  Legal landscape skala 95 %. **Nomor SK** disimpan per tahun ajaran (tabel `skbm_sk`, kolom "Nomor SK" di halaman SKBM; API `POST admin/skbm/nomor`).
  Pembanding otomatis terhadap berkas sekolah: `php spark dev:uji-skbm-excel` (51 titik: judul, header, 119 baris × 42 kelas, rumus, total, gabungan sel, huruf/warna/garis,
  lebar & tinggi, pengaturan cetak). Berkas yang sama bisa diedit di Excel lalu **diimpor kembali** (pulang-pergi diuji; sel menyala tanpa JP bertanda `?` dan dilewati impor).
  Tahun yang belum diisi → **template** (30 baris kosong berumus). Pembaca impor berhenti di baris "TOTAL JP / KELAS", jadi tanda tangan tak terbaca sebagai guru.
- **Nomor guru & kode** mengikuti SK sekolah: angka di depan kode yang tersimpan saat impor (mis. `49A` → nomor 49; nomor boleh melompat), kode baris ditampilkan apa adanya;
  baris/guru buatan manual melanjutkan nomor terakhir.
- Header halaman memuat tautan **"Pakai di honor <tahun>: [ASTS 1 → Koreksi] …"** ke halaman Koreksi honor tahun itu (bila honor sudah dibuat).

## 4. Impor Excel (`Libraries\SkbmImpor`)

Berkas jadwal sekolah ±2 MB (batas 8 MB). Hanya **lembar yang namanya memuat "SKBM"** dibaca (`setLoadSheetsOnly`, data saja):
±0,4 detik dan ±26 MB memori. Tidak ada yang tersimpan sebelum Admin menekan **Terapkan**.

1. **Baca** — cari baris judul ("NAMA GURU", "MATA PELAJARAN", NO, KODE GURU); kolom kelas dibaca dari tiga baris (kelompok → tingkat →
   nama kelas). Kolom bertuliskan JUMLAH/TOTAL/KETERANGAN atau tanpa nama kelas memisahkan **blok** kolom kelas.
2. **Cocokkan** — tiap kolom kelas dicocokkan ke Master Kelas (kunci tingkat+jurusan+nomor); blok yang kolomnya **paling banyak
   dikenal** dipilih otomatis (seri → paling kanan). Lembar sekolah punya dua blok: blok 1 (33 kolom, bukan penugasan SK) dan
   blok 2 (42 kolom, penugasan SK yang sebenarnya, 491 sel). Admin bisa mengganti blok di pratinjau. Nama guru dicocokkan ke Master
   Guru (persis → huruf-angka saja → nama inti → "mirip"); yang ragu/tidak ada → Admin memilih atau melewati.
3. **Terapkan** — satu transaksi; pilihan "ganti seluruh SKBM tahun itu" (bawaan: ya). Tanpa "ganti", guru yang sudah ada dilewati.
   Tercatat di Audit Log. Tahun tujuan terbaca dari nama lembar (bisa diubah).

## 5. Sambungan ke Koreksi honor

`HonorKoreksi::isiDariSkbm($dokumenId, $ganti)`: ambil SKBM tahun ajaran honor itu → per **orang** (induk guru) → satu baris per **nama mapel**
(kelas digabung) → hanya guru yang jadi penerima honor. Guru SKBM yang bukan penerima dilaporkan di pesan. Lembar per sel tetap =
jumlah peserta kelas (bukan JP). Pagar yang sama dengan sumber lain: honor DIKUNCI → ditolak. Kode terpasang sebelum migrasi → pesan jelas
(bukan galat 500).

## 6. Perbandingan dengan Penugasan

Pasangan **guru × kelas** (hanya guru yang ada di SKBM): *sama*, *hanya di SKBM*, *hanya di Penugasan*, *JP beda*; guru ber-Penugasan yang
tidak ada di SKBM dilaporkan terpisah. Tidak mengubah apa pun.

## 7. Uji

- `php spark dev:uji-skbm` — 104 cek (murni, hak akses, logika, nomor SK & kode, perbandingan, Excel pulang-pergi, impor berkas asli, banding dengan Excel KOREKSI, sambungan ke
  Koreksi); semua dalam transaksi yang di-rollback. Berkas asli dibaca dari `formatdatasekolah/` (dilewati bila tak ada).
- `php spark dev:uji-skbm-excel` — 51 titik pembanding terhadap lembar SKBM sekolah (lihat 3.1).
- HTTP end-to-end 86 cek (akses Admin/Operator/Hubin, impor 2 MB, klik sel, form, salin, kosongkan, Koreksi + langkah berikutnya, pagar migrasi) dan peramban
  44 cek (klik, klik kanan, mode JP, tampilan Per guru + chip, token CSRF setelah banyak klik, ponsel 390 px, tanpa galat JavaScript).
- API Android: 113 cek (lihat bagian 9).

## 8. Pasang

```
git pull
php spark migrate        # 2 migrasi baru: 2026-10-16-000001_Skbm dan 2026-10-16-000002_SkbmSk (nomor SK)
```

Lalu: Guru → SKBM → *Impor dari Excel SKBM* → periksa pratinjau → *Terapkan impor*. Setelah itu Honor → Koreksi → *Isi dari SKBM*.
Bila server menolak berkas karena batas unggah, salin lembar SKBM saja ke berkas Excel baru lalu unggah yang kecil itu.

## 9. API Android dan kontrak Flutter

SKBM tersedia di API (`/api/v1/admin/skbm…`, khusus Admin): lihat `docs/API-SKBM-KOREKSI.md` (kontrak) dan
`flutter-muslimin/BLUEPRINT-SKBM.md` (rencana layar S0–S4) + fixture `test/fixtures/skbm/`. Impor Excel hanya di web.

## 10. Belum / keputusan terbuka

- API & kontrak Flutter sudah ada (bagian 9); layar Flutter belum dikerjakan.
- Belum ada tombol "Terapkan SKBM ke Penugasan" (sengaja; keputusan tersendiri bila diminta).
- Dikonfirmasi klien 2026-10-10: SKBM **per tahun ajaran**; klien adalah pihak kurikulum dan memakai akun Admin, jadi menu **tetap khusus Admin**. Tugas tambahan
  (JP non-mengajar) belum diketahui — SKBM sementara hanya memuat tugas mengajar.
- Guru di Excel yang belum ada di Master Guru harus ditambahkan dulu agar ikut terimpor.
