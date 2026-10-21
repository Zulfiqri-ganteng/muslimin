# API Supervisi Guru (Android) — `/api/v1/supervisi…`

Dibuat 2026-10-21 (Supervisi **S4**). Cermin menu web **KURIKULUM → Supervisi Guru** (`admin/supervisi/jadwal`, `…/penilaian`,
`…/rekap`) untuk aplikasi Android. Guna utama di HP/tablet: **supervisor mengisi lembar penilaian langsung di kelas**. Rancangan:
`docs/DESAIN-SUPERVISI.md`. Kontrak sisi Flutter (layar, tahap, jebakan): `C:\flutter-muslimin\BLUEPRINT-SUPERVISI.md` + contoh
respons nyata (data fiktif) `C:\flutter-muslimin\test\fixtures\supervisi\` (daftar berkas: `_CATATAN.txt` di folder itu).

## Prinsip
- Controller tipis `App\Controllers\Api\Supervisi`; SEMUA aturan dari library yang sama dengan web: `Libraries\Supervisi` (jadwal,
  buat massal, pilihan guru/supervisor/mapel/kelas, saran Penugasan), `Libraries\SupervisiPenilaian` (lembar, hitung nilai &
  predikat, simpan draf/selesai, rekap), `Libraries\SupervisiCetak` (Excel Program, PDF Lembar Hasil, Excel Rekap). Hasil web =
  hasil aplikasi.
- **Kelola instrumen & butir TIDAK ada di aplikasi** (web saja: Instrumen Supervisi). Aplikasi menerima instrumen aktif + butirnya
  lewat `meta`.
- Audit Log memakai akhiran **"(via aplikasi)"**.
- Amplop JSON standar: `{ "status": "success"|"error", "ok": bool, "message": string, "data": any }`. Tampilkan `message` apa adanya
  (sudah Bahasa Indonesia untuk pengguna).
- Migrasi: `2026-10-24-000001_SupervisiGuru` (5 tabel `supervisi_*` + 3 instrumen bawaan). S3/S4 **tanpa migrasi baru**.
- **Angka nilai** (`nilai_akhir`, `nilai`, `rata_rata`, `ringkas.nilai`) dikirim sebagai angka JSON 2 desimal; nilai bulat bisa datang
  TANPA desimal (`75`, bukan `75.0`) → di Dart baca `(x as num?)?.toDouble()`. Tampilkan dengan koma: `78,89`.

## Gerbang & hak
- `ApiAuthFilter` (token Bearer dari `POST auth/login`): peran harus **boleh aplikasi** (Sistem → Hak Akses) dan memegang menu
  **Supervisi Guru** (awalan API `supervisi`). Admin `*`. Tanpa menu itu → **403** "tidak punya akses ke fitur ini di aplikasi";
  tanpa/kedaluwarsa token → **401**.
- Bawaan: hanya **Admin**. Peran lain (mis. Waka Kurikulum, Kepala Sekolah) diberi lewat Hak Akses: **Lihat** = melihat & mengunduh,
  **Kelola** = tambah/ubah/hapus jadwal + isi penilaian.
- Hak per aksi = `HakAkses::boleh` pada alamat WEB padanannya; `meta.data.hak` membawa hasilnya — aplikasi menyembunyikan tombol yang
  `false`, jangan menebak dari nama peran:

| Kunci `hak` | Aksi | Padanan web |
|---|---|---|
| `lihat` | meta, daftar, detail, saran | GET `admin/supervisi/jadwal` |
| `tambah`, `ubah`, `massal`, `hapus` | tambah/ubah/batalkan/hapus jadwal, buat massal | POST `admin/supervisi/jadwal…` |
| `nilai` | isi lembar penilaian (draf/selesai) | POST `admin/supervisi/penilaian/{id}` |
| `rekap` | rekap | GET `admin/supervisi/rekap` |
| `unduh` | Excel Program/Rekap, PDF Lembar Hasil | GET `admin/supervisi/program` dll. |

(Semua kunci saat ini satu menu, jadi praktisnya Lihat → `lihat/rekap/unduh` true; Kelola → semuanya true.)

## Aturan (sama dengan web)
- Satu **kunjungan** = guru disupervisi + supervisor + tanggal (+ jam) + mapel & kelas yang diamati (opsional) + instrumen dipakai.
  **Tahun ajaran & semester dihitung dari tanggal** (Jul–Des = ganjil, Jan–Jun = genap) — aplikasi tidak mengirimnya.
- Satu guru boleh beberapa kunjungan per semester, tetapi tidak dua kunjungan (yang tidak batal) pada **tanggal yang sama** → 409.
- Supervisor ≠ guru yang disupervisi (422). Supervisor dipilih dari daftar guru (`meta.supervisor.saran` = berjabatan struktural:
  kepala sekolah, wakasek — kurikulum didahulukan —, kaprog; `lain` = guru lain). Supervisor tidak wajib punya akun.
- Jadwal yang sudah ada penilaiannya: tidak bisa **dihapus** (409; batalkan saja), gurunya tidak bisa diganti (409), instrumen yang
  sudah dinilai tidak bisa dicabut (409). Jadwal **batal** tidak bisa dinilai (409) dan tidak punya PDF (409).
- **Skor butir:** `1` Kurang · `2` Cukup · `3` Baik · `4` Sangat Baik · `0` = **TD** (tidak dinilai, tidak dihitung) · `null` = belum
  diisi (hanya boleh di draf).
- **Nilai instrumen** = jumlah skor ÷ (butir dinilai × 4) × 100, 2 desimal. **Nilai akhir** = rata-rata nilai instrumen.
  **Predikat:** ≥ 91 Amat Baik · ≥ 76 Baik · ≥ 61 Cukup · selain itu Kurang (`meta.predikat`). Aplikasi BOLEH menghitung langsung di
  layar dengan rumus ini (seperti web), tetapi nilai resmi = jawaban server.
- **Draf** (`selesai: false`): boleh belum lengkap; status tetap `dijadwalkan` (kemajuan `draf`); instrumen yang belum disentuh sama
  sekali tidak dibuatkan hasil. **Selesai** (`selesai: true`): SEMUA butir semua instrumen wajib terisi (skor 1–4 atau TD) dan minimal
  satu butir berskor per instrumen → status `selesai` + `nilai_akhir` + `predikat`. Jadwal yang sudah selesai hanya bisa diubah
  dengan `selesai: true` lagi (draf → 409).
- Tindak lanjut (boleh lebih dari satu): `individual` · `kelompok` · `pelatihan` · `model` · `tidak_perlu` — `tidak_perlu` TIDAK boleh
  bersama yang lain (422).
- Butir **disalin** saat lembar pertama disimpan: instrumen yang diubah kemudian di web tidak mengubah lembar lama.

## Endpoint

| Metode | Alamat | Isi / catatan |
|---|---|---|
| GET | `supervisi/meta` | `hari_ini`, `periode` {tahun_ajaran, semester, label}, `tahun_ajaran[]` (pilihan saringan, terbaru dulu), `semester[]` & `status[]` {kode,label}, `instrumen[]` (AKTIF saja: {id, kode, nama, nama_pendek, jenis, keterangan, jumlah_butir, butir[] {id, aspek, uraian}}), `guru[]` {id, nama, nip} (pilihan guru disupervisi), `supervisor` {saran[] {id, nama, jabatan}, lain[] {id, nama}}, `mapel[]` & `kelas[]` {id, nama}, `skor[]` {nilai, label} (1–4 + 0 TD), `predikat[]` {predikat, min, rentang}, `tindak_lanjut[]` {kode, label}, `batas` {catatan_jadwal:255, catatan_butir:255, teks:2000, massal_per_hari:3, massal_maks_per_hari:10, massal_maks_hari:184, pdf_gabungan:120}, `hak` |
| GET | `supervisi/jadwal?ta=&semester=&status=&guru_id=` | kunjungan satu periode (bawaan periode sekarang; nilai tak sah → bawaan), urut tanggal & jam: `saringan` {ta, semester, status, guru_id, label}, `ringkas` {dijadwalkan, selesai, batal, total, rata_rata, guru_belum[] {id,nama} = guru yang BELUM punya jadwal (tidak batal) semester itu}, `jumlah`, `baris[]` (bentuk **Baris jadwal**) |
| GET | `supervisi/jadwal/saran?guru_id=` | mapel & kelas yang diampu guru (Penugasan), JP terbanyak dulu: `[{mapel_id, mapel, kelas_id, kelas, jp}]` — tampilkan sebagai pilihan cepat setelah guru dipilih |
| GET | `supervisi/jadwal/{id}` | **Detail** = Baris jadwal + `lembar`; tidak ada → 404 |
| POST | `supervisi/jadwal` | `{guru_id*, supervisor_id*, tanggal* "YYYY-MM-DD", jam? "08:00", mapel_id?, kelas_id?, instrumen*: [id,…], catatan?}` → **201** Detail. Ganda → 409; isian tak sah → 422. Instrumen diurutkan server (urutan daftar instrumen) |
| POST | `supervisi/jadwal/{id}/ubah` | kunci sama dengan tambah + `batal?: bool`. **Kunci yang tidak dikirim = tetap** (boleh kirim sebagian, mis. `{batal: true}` atau `{jam, catatan}`); `""`/`null` = dikosongkan (untuk jam/mapel/kelas/catatan). `batal: false` = aktif lagi. → Detail. 404/409/422. Jadwal selesai yang ditambah instrumen baru / dibatalkan → nilai akhir dikosongkan |
| POST | `supervisi/jadwal/massal` | `{guru_ids*: [id], supervisor_id*, mulai*, selesai*, per_hari? (3, maks 10), jam?, instrumen*: [id]}` — guru dibagi rata ke hari sekolah dalam rentang (satu semester, maks 184 hari); mapel & kelas = Penugasan JP terbanyak; guru yang sudah terjadwal semester itu & supervisor sendiri dilewati → **201** `{jumlah, ids[], dilewati[] (nama)}`. Semua dilewati → 409; rentang/isian salah → 422 |
| DELETE | `supervisi/jadwal/{id}` | → `{id}`. Sudah ada penilaian → 409 (pakai ubah `{batal:true}`); tidak ada → 404 |
| POST | `supervisi/jadwal/{id}/nilai` | Lembar penilaian UTUH (lihat **Mengisi penilaian**) → Detail terbaru. 422 = isian kurang/tak sah (+ `data.instrumen_id` = instrumen yang bermasalah → buka tab itu); 409 = jadwal batal / sudah selesai lalu dikirim draf; 404 |
| GET | `supervisi/rekap?ta=&semester=&predikat=` | lihat **Rekap**. `predikat` tak sah → semua |
| GET | `supervisi/jadwal/{id}/pdf` | **berkas PDF** Lembar Hasil satu kunjungan (belum selesai → bertanda DRAF / BELUM DINILAI, bisa dicetak untuk diisi tangan). Batal → 409 JSON; tidak ada → 404 JSON |
| GET | `supervisi/lembar?ta=&semester=&guru_id=` | **berkas PDF** gabungan semua kunjungan SELESAI periode itu (+ saring guru), satu kunjungan per halaman. Kosong → **404 JSON**; > 120 kunjungan → 422 JSON (saring per guru). Bisa lambat (puluhan detik) → batas waktu unduh ≥ 120 dtk |
| GET | `supervisi/program?ta=&semester=&status=&guru_id=` | **berkas .xlsx** Program Supervisi Akademik (A4 mendatar + TTD Kepala Sekolah). Tanpa `status`, jadwal batal tidak ikut |
| GET | `supervisi/rekap/excel?ta=&semester=&predikat=` | **berkas .xlsx** Rekap Hasil (lembar "Rekap" guru × instrumen + "Ringkasan" per predikat & guru belum disupervisi) |

Berkas dikirim `Content-Type: application/octet-stream` + `Content-Disposition: attachment; filename="…"` (nama berkas siap pakai,
mis. `Lembar Supervisi Rina Contoh 2026-10-21.pdf`, `Program Supervisi 2026-2027 Ganjil.xlsx`). Bila jawabannya JSON (galat), baca
`message`. Contoh kepala: fixture `_unduhan_kepala.txt`.

### Baris jadwal
```json
{
  "id": 149, "tahun_ajaran": "2026/2027", "semester": "ganjil",
  "tanggal": "2026-10-21", "hari_tanggal": "Rabu, 21 Oktober 2026", "jam": "08:00",
  "guru_id": 863, "nama_guru": "Rina Contoh, S.Pd.", "nip_guru": "198701012010012001",
  "mapel_id": 72, "nama_mapel": "Matematika", "kelas_id": 71, "nama_kelas": "XI AKL",
  "supervisor_id": 866, "nama_supervisor": "Dewi Contoh, M.Pd.", "jabatan_supervisor": "Wakil Kepala Sekolah Bidang Kurikulum",
  "instrumen": [{"id": 1, "nama": "Perencanaan Pembelajaran (Modul Ajar)", "nama_pendek": "Perencanaan Pembelajaran", "dinilai": true}],
  "status": "selesai", "status_label": "Selesai",
  "kemajuan": "selesai",
  "nilai_akhir": 78.89, "predikat": "Baik",
  "catatan": "Kunjungan pertama semester ini",
  "ada_nilai": true, "bisa_hapus": false,
  "dicatat_oleh": "Waka Kurikulum (contoh)", "dibuat_at": "2026-10-21 10:03:07", "diubah_at": "2026-10-21 10:03:08"
}
```
- `kemajuan`: `belum` (belum ada isian) · `draf` (sebagian tersimpan) · `selesai` · `batal` — dasar lencana & tombol (Nilai /
  Lanjutkan / Lihat hasil). `status` = `dijadwalkan|selesai|batal`.
- `nip_guru`, `jam`, `mapel_*`, `kelas_*`, `jabatan_supervisor`, `catatan`, `nilai_akhir`, `predikat` boleh `null`.
- `instrumen[].dinilai` = instrumen itu sudah punya hasil (tidak bisa dicabut lagi).

### Detail (+ `lembar`)
```json
"lembar": {
  "instrumen": [{
    "id": 1, "nama": "Perencanaan Pembelajaran (Modul Ajar)", "nama_pendek": "Perencanaan Pembelajaran", "jenis": "perencanaan",
    "tersimpan": true,
    "butir": [{"kunci": "n477", "butir_id": 1, "aspek": "Identitas & Tujuan", "uraian": "Identitas modul ajar … lengkap.", "skor": 4, "catatan": "Modul ajar lengkap & rapi"}],
    "ringkas": {"total": 33, "maks": 40, "nilai": 82.5, "predikat": "Baik", "dinilai": 10, "td": 1, "kosong": 0, "jumlah": 11},
    "kekuatan": "…", "kelemahan": "…", "rekomendasi": "…", "tindak_lanjut": ["individual"],
    "dinilai_at": "2026-10-21 10:03:08", "dinilai_oleh": "Waka Kurikulum (contoh)"
  }],
  "ringkas": {"nilai": 78.89, "predikat": "Baik", "lengkap": true}
}
```
- Butir dikelompokkan per `aspek` (urutan = urutan larik; tampilkan judul aspek saat berganti).
- `kunci`: `"b{butir_id}"` = belum pernah disimpan, `"n{id}"` = sudah tersimpan. **Kirim balik kunci PERSIS dari detail terakhir.**
  Kunci `"b…"` lama tetap diterima walau lembar sudah tersimpan oleh orang lain (aman bila lembar dibuka sebelum disimpan).
- `skor`: `1–4`, `0` (TD) atau `null` (belum diisi). `tersimpan: false` + `dinilai_at: null` = instrumen itu belum pernah disimpan.
- `ringkas.nilai`/`predikat` `null` bila belum ada butir berskor; `lembar.ringkas.lengkap` = siap "Simpan & selesai".

### Mengisi penilaian — `POST supervisi/jadwal/{id}/nilai`
```json
{
  "selesai": false,
  "nilai": {
    "1": {
      "skor":    {"b1": 4, "b2": 3, "b3": 0},
      "catatan": {"b1": "Modul ajar lengkap & rapi"},
      "kekuatan": "Tujuan pembelajaran jelas.", "kelemahan": "", "rekomendasi": "",
      "tindak_lanjut": ["individual"]
    },
    "2": { "skor": {"b12": 3} }
  }
}
```
- Kunci `nilai` = `instrumen_id` (teks). Kirim **lembar utuh** semua instrumen yang ingin disimpan: per instrumen, `skor`/`catatan`
  yang tidak dikirim untuk sebuah butir = **dikosongkan** (skor null, catatan kosong); teks & tindak lanjut yang tidak dikirim =
  kosong. Jadi simpan dari keadaan layar penuh, jangan kirim selisih.
- Instrumen yang tidak ada di `nilai`: draf → dilewati bila belum pernah disimpan (bila sudah pernah, isinya dikosongkan!);
  selesai → 422 "butir belum diisi". **Selalu kirim semua instrumen dari lembar.**
- Jawaban 200 = Detail terbaru (kunci butir kini `"n…"`) — ganti keadaan layar dengan jawaban ini. `message` = "Draf … disimpan." /
  "Penilaian … selesai — nilai 78,89 (Baik)."
- 422: `{"status":"error","message":"Perencanaan Pembelajaran (Modul Ajar): 11 butir belum diisi. …","data":{"errors":{},"instrumen_id":1}}`.

### Rekap — `GET supervisi/rekap`
`data`: `label`, `ta`, `semester`, `predikat` (saringan dipakai, `""` = semua), `instrumen[]` {id, kunci "i{id}", nama} = kolom
(hanya instrumen yang dinilai di periode itu, urut daftar instrumen), `baris[]` (satu per guru dengan kunjungan SELESAI; saringan
predikat hanya menyaring ini): {guru_id, nama_guru, nip_guru, mapel[], kunjungan[] {id, tanggal, nama_supervisor, nama_kelas,
nilai_akhir, predikat}, nilai {"i1": 82.5, …} (rata-rata per instrumen; kunci tak ada/`null` = tidak dinilai), rata_rata, predikat,
tindak_lanjut[] (kode, gabungan semua kunjungan; "tidak_perlu" gugur bila ada pembinaan lain), tindak_lanjut_label[]}, `belum[]`
{id, nama, status `terjadwal`|`belum_dijadwalkan`, tanggal, jadwal_id} = guru tanpa kunjungan selesai, `ringkas` {guru_dinilai,
kunjungan_selesai, rata_rata, rata_predikat, per_predikat {"Amat Baik": n, "Baik": n, "Cukup": n, "Kurang": n}, terjadwal,
belum_dijadwalkan} (selalu dihitung tanpa saringan predikat).

## Galat umum
| Kode | Kapan | Contoh fixture |
|---|---|---|
| 401 | token tidak ada/kedaluwarsa | (umum) |
| 403 | peran tanpa menu Supervisi Guru / tanpa hak aksi | `galat_403_guru_piket.json` |
| 404 | jadwal tidak ada; PDF gabungan tanpa kunjungan selesai | `galat_404_detail.json`, `galat_404_lembar_kosong.json` |
| 409 | jadwal ganda; massal semua dilewati; hapus yang sudah dinilai; nilai draf pada yang selesai; PDF jadwal batal | `galat_409_*.json` |
| 422 | isian tak sah (pesan menyebut isiannya); nilai belum lengkap (+ `instrumen_id`) | `galat_422_jadwal.json`, `galat_422_nilai_belum_lengkap.json` |

## Uji
`php spark dev:uji-supervisi` (102 cek: S1–S2 + rekap, berkas cetak, halaman, API semua endpoint & hak; data uji dibersihkan sendiri;
`--simpan C:/folder` menyimpan contoh PDF/Excel). Fixture ditangkap lewat HTTP + token sungguhan (Apache lokal) dengan data fiktif.
