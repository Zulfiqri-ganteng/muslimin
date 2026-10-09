# API SKBM + Koreksi honor (aplikasi Android) — kontrak

Dibuat 2026-10-10. **KHUSUS ADMIN** (SKBM = sumber ceklis Koreksi; Koreksi = bahan angka honor/gaji). Cermin menu web
**Guru → SKBM** dan **Honor → Koreksi**. Seluruh aturan & angka ada di pustaka server (`Libraries\Skbm`, `HonorKoreksi`, `HonorHitung`)
yang SAMA dengan web — angka dan pesan galat tidak mungkin berbeda.
Rancangan bisnis: `docs/DESAIN-SKBM.md` (dan `docs/DESAIN-HONOR.md` bagian 11). Contoh respons nyata (nama difiktifkan):
`flutter-muslimin/test/fixtures/skbm/` dan `…/koreksi/`. Kode: `app/Controllers/Api/Admin/Skbm.php`, `Koreksi.php` (dasar Koreksi: `HonorBase.php`).

## 1. Akses
- Bearer token seperti endpoint lain. Hanya peran **admin**. Operator & Waka Hubin: **403** di semua endpoint ini (penyaring `apiauth`
  hanya mengizinkan awalan `pkl`; controller memeriksa ulang). Tanpa token: **401**.
- **Impor Excel (SKBM maupun KOREKSI NILAI) TIDAK ada di API** — hanya web. Aplikasi menampilkan petunjuk "Impor dari Excel dilakukan lewat situs web".
- Kode terpasang sebelum migrasi → **503** dengan pesan "…migrasi database belum dijalankan (php spark migrate)".

## 2. Konvensi
- Amplop standar `{status, ok, message, data}`; `message` berbahasa Indonesia dan **siap ditampilkan**.
- Body JSON (`Content-Type: application/json`); `DELETE` tanpa body (parameter di query). Boolean = `true/false`.
- **Peta berkunci id** (`sel`, `per_mapel`, `per_guru`, `per_baris`, `per_kelas`, …) selalu **OBJEK JSON** (kunci = id sebagai string), bukan list, walau kosong (`{}`).
- **Angka SELALU dari server.** Jangan menghitung total sendiri; setiap aksi pengubah membalas angka resmi terbaru — pakai itu.
- Simpan **satu per satu (antrean)**: kirim satu permintaan pada satu waktu; gagal → kembalikan tampilan sel dan tampilkan `message`.

| Kode | Arti |
|---|---|
| 200/201 | berhasil |
| 401 | token hilang/kedaluwarsa |
| 403 | bukan Admin |
| 404 | (Koreksi) jenis ujian / tahun pelajaran / honor tidak ada ("Honor belum dibuat untuk ujian ini") |
| 409 | (Koreksi) `periode_id` tidak cocok dengan `{slug}`+`tp` |
| 422 | ditolak aturan (isian tak sah, honor terkunci, tahun tak sah, dst.) — `message` siap tampil |
| 503 | migrasi database belum dijalankan di server |

## 3. SKBM (`/api/v1/admin/skbm…`)
`tahun` = tahun ajaran `"2026/2027"` (tahun kedua = tahun pertama + 1): di **query** (GET/DELETE) atau **body** (POST). GET tanpa `tahun` memakai tahun bawaan
(periode ujian terbaru). **Aksi pengubah WAJIB mengirim `tahun`** (server tidak menebak); aksi yang menunjuk baris (`sel`) harus cocok dengan tahunnya.

| Method & path | Body / query | Hasil `data` |
|---|---|---|
| `GET skbm?tahun=` | – | matriks lengkap, lihat §3.1 |
| `GET skbm/opsi` | – | `{guru:[{id,nama}], mapel:[teks]}` untuk dialog tambah (guru = Master Guru aktif) |
| `GET skbm/bandingkan?tahun=` | – | `{tahun, ada, sama, hanya_skbm[], hanya_pengampu[], jp_beda[], guru_skbm, guru_tanpa_skbm[]}` (hanya membandingkan) |
| `POST skbm/nomor` | `{tahun,nomor}` | simpan nomor SK tahun ajaran (mis. `"123/SMK-BN/SKBM/VII/2026"`; ≤ 120 huruf; hanya huruf, angka, spasi, `/ . - _ , : ( ) &`; `""` = hapus) → `{nomor}`. Tercetak di baris "Nomor :" pada Excel |
| `GET skbm/xlsx?tahun=` | – | **berkas biner** Excel SKBM tahun itu — **tata letak sama dengan lembar SKBM sekolah** (judul 4 baris, header ungu 3 baris, kolom kelas miring, JUMLAH JP / TOTAL JP / KOREKSI, baris TOTAL JP, tanda tangan kepala sekolah; Legal landscape) (`?unduh=1` = attachment); tahun yang belum diisi → **TEMPLATE kosong** (`SKBM-TEMPLATE-<tahun>.xlsx`, kolom kelas sudah terisi). Format sama dengan yang dibaca impor web (bisa diedit lalu diimpor kembali). Sel kelas menyala tanpa JP bertanda `?`. Galat tetap JSON beramplop → periksa `Content-Type` |
| `POST skbm/sel` | `{tahun,mapel,kelas,aktif,jp?}` | nyalakan/matikan sel. `jp`: tak dikirim = tak diubah (sel baru → `null`), `""`/`null` = kosongkan, `1–40` = isi (angka atau teks). Hasil: `{jp, ringkasan}` |
| `POST skbm/mapel` | `{tahun,guru,nama}` | **201** `{id, ringkasan}`; `nama` ≤ 150 huruf, `"-"` = guru terdaftar tanpa mapel |
| `POST skbm/mapel/{id}` | `{nama}` | ganti nama → `{nama}` |
| `DELETE skbm/mapel/{id}` | – | hapus baris (beserta kelasnya) → `{tahun, ringkasan}` |
| `DELETE skbm/guru/{guruId}?tahun=` | – | keluarkan guru dari SKBM tahun itu → `{ringkasan}`; 422 bila gurunya tak ada di tahun itu |
| `POST skbm/salin` | `{dari,ke,ganti?}` | salin satu tahun ke tahun lain; tujuan harus kosong kecuali `ganti:true` (422 "sudah berisi") → `{ringkasan tahun tujuan}` |
| `POST skbm/kosongkan` | `{tahun}` | buang seluruh SKBM tahun itu (tahun lain tak tersentuh) |

**Ringkasan angka (`ringkasan`)** dikirim setelah tiap aksi: `total` (total JP), `per_mapel{"<id baris>":jp}`, `per_guru{"<guruId>":jp}`,
`per_guru_jml{"<guruId>":jumlah kelas}`, `per_kelas{"<kelasId>":{jml,jp}}`, `jumlah_guru`, `jumlah_mapel`, `jumlah_sel`, `tanpa_jp`.

### 3.1 Bentuk `GET skbm`
```
{ tahun, ada:bool,                                   // ada=false → tampilkan "SKBM … belum diisi" + petunjuk impor lewat web / salin dari tahun lain
  tahun_opsi:[{tahun,guru,mapel}],                   // pilihan tahun (terbaru dulu; guru=0 → kosong)
  sk:{nomor},                                        // nomor SK tahun ajaran ini ("" = belum diisi); tercetak di baris "Nomor :" pada Excel SKBM
  kelas:[{id,nama,label,tingkat,shift,grup,jml,jp}], // kolom, urutan tampil; jml = baris mapel yang menyala, jp = total JP kelas itu
  grup:[{judul,dari,sampai}],                        // kelompok kolom (indeks ke kelas[]): "KELAS PAGI X", "KELAS SIANG XI", …
  guru:[{guru_id,no,nama,total_jp,jml,mapel:[{id,nama,mapel_id,kode,total_jp,jml,sel:{"<kelasId>":jp|null}}]}],
  ringkas:{jumlah_guru,jumlah_mapel,jumlah_sel,total_jp,tanpa_jp,beda_penugasan} }
```
- `sel`: kelas yang diajar pada baris mapel itu → nilai **JP** (`null` = mengajar, JP belum diisi → tandai khusus, mis. kuning). Kelas yang tidak ada di `sel` = tidak mengajar.
- `kode` = kode guru di SK (`1`, atau `3A/3B` bila satu guru punya beberapa baris). Untuk SKBM hasil **impor** ini kode persis seperti tertulis di SK sekolah;
  baris yang dibuat manual diturunkan dari nomor guru. `beda_penugasan` = jumlah selisih dengan data Penugasan (`null` bila SKBM kosong).
- Urutan `guru` = urutan di SK. **`no` = nomor guru di SK** (angka di depan kode, mis. `3A` → 3) dan **boleh melompat** (mis. …, 51, 54); guru yang dibuat manual melanjutkan nomor terakhir.
  Jangan menganggap `no` selalu 1..n; pakai sebagai teks tampilan, bukan indeks.

## 4. Koreksi honor (`/api/v1/admin/ujian/{slug}/honor/koreksi…`, tambahkan `?tp=`)
`{slug}` = `asts1|asas|asts2|asat`; **selalu kirim `tp`** (`"2026/2027"`) dari GET; periode & honor harus sudah ada (404 "Honor belum dibuat…" bila belum).
Lembar tiap guru = **jumlah peserta ujian** di kelas yang ia koreksi (per mapel); total guru menjadi angka **Koreksi** di honor.

| Method & path | Body | Hasil `data` |
|---|---|---|
| `GET koreksi` | – | ceklis lengkap, lihat §4.1 |
| `POST koreksi/sel` | `{mapel,kelas,aktif,jumlah?}` | nyalakan/matikan sel. `jumlah`: tak dikirim = tak diubah, `""` = ikut peserta kelas, angka 0–999 = lembar khusus. Hasil: `{nilai, ringkasan}` (`nilai` = lembar efektif sel; `null` bila dimatikan) |
| `POST koreksi/peserta` | `{kelas,nilai}` | peserta ujian satu kelas (`""` = ikut siswa aktif) → `{ringkasan}` (`per_kelas[kelas]`: `{peserta,jml,lembar,manual}`) |
| `POST koreksi/mapel` | `{baris,nama}` | **201** `{id, …}`; `baris` = id penerima honor (`penerima[].id`); `"-"` = tanpa mapel |
| `POST koreksi/mapel/{id}` | `{nama}` | ganti nama mapel |
| `DELETE koreksi/mapel/{id}` | – | hapus baris mapel |
| `DELETE koreksi/guru/{barisId}` | – | keluarkan penerima dari ceklis (angka Koreksi di honor tidak berubah) |
| `POST koreksi/isi-skbm` | `{ganti?}` | isi dari SKBM tahun ajaran honor ini; tanpa `ganti`, penerima yang sudah punya isi **dilewati**. 422 bila SKBM tahun itu kosong |
| `POST koreksi/isi-pengampu` | `{ganti?}` | isi dari data Penugasan (alternatif; 422 bila penerima tak punya data pengampu) |
| `POST koreksi/salin` | `{sumber,peserta?,ganti?}` | salin dari honor lain (`honor_lain[].id`); `peserta:true` = jumlah peserta ikut disalin |
| `POST koreksi/kosongkan` | `{peserta?}` | buang seluruh ceklis |
| `POST koreksi/segarkan-peserta` | `{semua?}` | samakan peserta dengan siswa aktif (`semua:true` = termasuk yang diketik) |
| `POST koreksi/terapkan` | `{timpa?}` | tulis total ceklis ke kolom **Koreksi** di honor (Hitung otomatis yang sama dengan web; sel yang diketik Admin dilewati kecuali `timpa:true`) → `{ringkas[], honor:{status,jumlah_penerima,total}}` |
| `GET koreksi/xlsx` | – | **berkas biner** Excel "KOREKSI NILAI" (identik berkas sekolah); `?unduh=1` = attachment. Galat tetap JSON beramplop → periksa `Content-Type` |

Honor **DIKUNCI** → semua aksi pengubah 422 ("Honor ini sudah DIKUNCI…"); `GET` tetap jalan (`boleh_ubah:false`) dan Excel tetap bisa diunduh.
Setiap aksi pengubah (kecuali sel) membalas **ringkasan angka resmi**: `total`, `per_baris{"<barisId>":lembar}`, `per_mapel{"<id baris mapel>":lembar}`,
`per_kelas{"<kelasId>":{peserta,jml,lembar,manual}}`, `jumlah_guru`, `jumlah_mapel`.

### 4.1 Bentuk `GET koreksi`
```
{ periode:{id,jenis,slug,tahun_ajaran,label}, status:"draf"|"final"|"dikunci", boleh_ubah:bool, ada_ceklis:bool,
  kelas:[{id,nama,label,tingkat,shift,grup,siswa,peserta,manual,jml,lembar}],   // siswa = siswa aktif; peserta = yang dipakai; manual=true bila peserta diketik
  grup:[{judul,dari,sampai}],
  guru:[{baris_id,no,guru_id,nama,jabatan,total,
         mapel:[{id,nama,mapel_id,kode,total,sel:{"<kelasId>":lembar},khusus:[kelasId…]}]}],  // khusus = sel yang angkanya diketik (≠ peserta kelas)
  ringkas:{jumlah_guru,jumlah_mapel,total},
  banding:{ada_komponen,beda,baris:[{baris_id,nama,sekarang,ceklis,beda,manual}]},          // total ceklis vs angka Koreksi yang sekarang di honor
  skbm:{aktif,tahun,guru,mapel,sel},                                                         // isi SKBM tahun ajaran honor ini (guru=0 → belum diisi)
  penerima:[{id,nama,jabatan}], mapel_opsi:[teks], honor_lain:[{id,label,guru}] }
```
- `sel[kelas]` = lembar efektif sel (angka khusus bila ada, kalau tidak peserta kelas). Kelas yang tidak ada = tidak dikoreksi.
- `banding.ada_komponen=false` → honor tak punya komponen Koreksi (tombol Terapkan disembunyikan; arahkan ke web: Pengaturan Honor).
- `skbm.guru>0` dan `ada_ceklis=false` → tawarkan **"Isi dari SKBM"** sebagai langkah utama (di web panelnya langsung terbuka).

## 5. Alur yang disarankan di aplikasi
1. SKBM: pilih tahun → lihat/ubah (klik sel = nyala/mati; ketuk lama = isi JP) → bandingkan dengan Penugasan bila perlu. Impor Excel lewat web.
2. Honor → Koreksi: **Isi dari SKBM** → periksa/koreksi per guru → (opsional) ubah peserta kelas → **Terapkan ke kolom Koreksi** → unduh Excel.
3. Total/rupiah honor tetap dari endpoint honor (`docs/API-HONOR.md`).

## 6. Pengujian
113 pemeriksaan API (token sungguhan: akses semua peran × seluruh endpoint, validasi tahun/JP/jumlah, angka resmi vs database, isi dari SKBM, terapkan,
Excel biner (SKBM & Koreksi), salin, honor dikunci, fixture tanpa nama asli) lulus; regresi web SKBM (CLI 104, pembanding Excel sekolah 51, HTTP 86, peramban 44) dan honor/koreksi (CLI 138/30/496, HTTP 55 + 80/71/43/23/26/14, peramban 37, CSRF 7) lulus.
Rencana tampilan Flutter: `flutter-muslimin/BLUEPRINT-SKBM.md` dan `BLUEPRINT-KOREKSI.md`.
