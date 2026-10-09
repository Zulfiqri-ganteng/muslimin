# API Honor Ujian (aplikasi Android) — kontrak

Dibuat 2026-10-09. **KHUSUS ADMIN** (data gaji). Cermin tab **Honor** di web (menu Ujian) dan **Pengaturan Honor**.
Seluruh aturan hitung/validasi ada di pustaka server (`Libraries\HonorDokumen`, `HonorHitung`, `HonorPengaturan`,
`HonorPembuatSoal`, `HonorCetak`) yang SAMA dengan web — angka dan pesan galat tidak mungkin berbeda.
Rancangan bisnis: `docs/DESAIN-HONOR.md`. Contoh respons nyata (data difiktifkan): `flutter-muslimin/test/fixtures/honor/`.
Kode: `app/Controllers/Api/Admin/Honor.php`, `HonorPengaturan.php`, `HonorCetak.php` (dasar: `HonorBase.php`).

## 1. Akses
- Bearer token seperti endpoint lain (`Authorization: Bearer …`). Hanya peran **admin**.
- Operator & Waka Hubin: **403** di semua endpoint honor (penyaring `apiauth` hanya mengizinkan awalan `pkl`; controller memeriksa
  ulang peran). Tanpa token: **401**.
- **Impor Excel lama TIDAK ada di API** (hanya web). Aplikasi cukup menyediakan petunjuk "gunakan situs web untuk impor".

## 2. Konvensi
- Amplop standar: `{status, ok, message, data}`. Pesan galat (`message`) sudah berbahasa Indonesia dan **siap ditampilkan** ke pengguna.
- Periode ditentukan oleh `{slug}` (`asts1|asas|asts2|asat`) + **`tp`** (tahun pelajaran `"2026/2027"`; kosong = tahun berjalan).
  `tp` di query (GET/DELETE) atau di body (POST). **Selalu kirim `tp` yang didapat dari GET honor** supaya perubahan tidak jatuh ke
  periode lain bila tahun berjalan bergeser. Bila body memuat `periode_id`, harus cocok (409 bila tidak).
- Body JSON (`Content-Type: application/json`). `DELETE` tanpa body. Boolean memakai `true/false`.
- Uang = **bilangan bulat rupiah**. Isian `nilai` boleh angka (`4`) atau teks (`"1.500.000"`, `"360"`); ditolak: minus, pecahan/koma, huruf,
  di atas batas (jumlah ≤ 99.999; nominal ≤ 99.999.999).
- **Total SELALU dari server.** Jangan menghitung rupiah/total di aplikasi; tampilkan angka dari respons (`rupiah`, `total_baris`,
  `total_komponen`, `total`).

| Kode | Arti |
|---|---|
| 200/201 | berhasil |
| 401 | token hilang/kedaluwarsa |
| 403 | bukan Admin |
| 404 | jenis ujian / tahun pelajaran / honor / penerima tidak ada ("Honor belum dibuat untuk ujian ini") |
| 409 | `periode_id` tidak cocok dengan `{slug}`+`tp` |
| 422 | ditolak aturan (isian tak sah, honor terkunci, nama kembar, dst) — `message` siap tampil |

## 3. Pengaturan honor (`/api/v1/admin/honor/pengaturan…`)
| Method & path | Body | Hasil |
|---|---|---|
| `GET pengaturan` | – | `data`: `komponen[]`, `panitia[]`, `tanda_tangan`, `jenis_ujian[]`, `batas` |
| `POST pengaturan/komponen` | `{komponen:{"<id>":{nama,judul_cetak,tarif,satuan,urut,aktif,jenis:[]}}}` | simpan SEMUA komponen sekaligus; "Tidak ada perubahan." bila sama |
| `POST pengaturan/komponen/tambah` | `{nama,tipe:"satuan"\|"tetap",tarif,satuan}` | 201 |
| `DELETE pengaturan/komponen/{id}` | – | hanya komponen tambahan yang belum dipakai honor |
| `POST pengaturan/panitia` | `{nominal:{"<jabatan_id>":"1.500.000"}}` | kosong/0 = tanpa tunjangan |
| `POST pengaturan/tanda-tangan` | `{ketua_nama,bendahara_nama}` | titik gelar dirapikan ("S.Pd" → "S.Pd.") |

Komponen: `{id,kode,nama,judul_cetak,tipe,tarif,satuan,sumber,berlaku_di:[jenis],aktif:bool,urut,bawaan:bool}`.
`tipe`: `tetap` = nominal per orang; `satuan` = jumlah × tarif. `sumber`: `manual|koreksi|rapot|soal` (3 terakhir bisa dihitung otomatis).
Dikirim `jenis` kosong → 422 ("pilih minimal satu jenis ujian").

## 4. Honor per ujian (`/api/v1/admin/ujian/{slug}/…`, tambahkan `?tp=`)
| Method & path | Body | Hasil `data` |
|---|---|---|
| `GET honor` | – | lihat §5 |
| `POST honor` | `{salin_dari?}` | 201 `{id,status,jumlah_penerima,total}`; 422 bila sudah ada / tak ada komponen aktif |
| `DELETE honor` | – | hapus seluruh honor (422 bila terkunci) |
| `GET honor/calon` | – | `[{id,kode_guru,nama,jabatan,bukan_pengajar}]` guru yang belum masuk |
| `POST honor/dokumen` | `{judul,tempat,tanggal,ketua_nama,bendahara_nama,kepsek_nama}` | data surat; `tanggal` `YYYY-MM-DD` atau kosong |
| `POST honor/penerima` | `{guru_ids:[…]}` | `{jumlah, honor:{status,jumlah_penerima,total}}` |
| `POST honor/penerima/semua` | – | tambah semua guru yang belum ada |
| `DELETE honor/baris/{id}` | – | hapus penerima |
| `POST honor/baris/{id}/jabatan` | `{jabatan}` | `{jabatan}` (≤ 120 huruf; kosong → null) |
| `POST honor/baris/{id}/pindah` | `{posisi}` | `{urutan:[id…], posisi, honor}`; `posisi` 1 = paling atas, berlebih → paling bawah |
| `POST honor/nilai` | `{baris,komponen,nilai}` | angka resmi: `{nilai,otomatis,rupiah,total_baris,total_komponen,total_jumlah,total}` |
| `POST honor/sinkron` | – | perbarui tarif/komponen dokumen dari Pengaturan Honor |
| `POST honor/hitung` | `{sumber:["koreksi","rapot","soal"],timpa?:bool}` | `{ringkas:[{nama,diisi,diubah,sama,dilewati,tanpa_data,jumlah,terpotong}], honor}` |
| `POST honor/status` | `{ke:"draf"\|"final"\|"dikunci",alasan?}` | `{honor:{status,…}}`; **buka kunci wajib `alasan`** (5–200 huruf) |
| `GET pembuat-soal` | – | `{periode_id, jadwal:[{id,tanggal,jam_mulai,jam_selesai,tingkat,jurusan,shift,ruang,mapel,pembuat:[{id,guru_id,nama}]}], guru:[{id,label}]}` |
| `POST pembuat-soal` | `{jadwal_id,guru_id}` | 201 `{id}`; 422 bila sudah menjadi pembuat soal |
| `DELETE pembuat-soal/{id}` | – | cabut penugasan |
| `GET honor/cetak` | – | daftar berkas `[{kunci,label,mime,path,per_penerima}]` + `tersedia` + `catatan` |
| `GET honor/cetak/rekap-pdf` | – | **berkas biner** PDF rekap (F4 landscape) |
| `GET honor/cetak/rekap-xlsx` | – | **berkas biner** Excel REKAP + SLIP (identik dengan rekap sekolah) |
| `GET honor/cetak/slip-pdf` | `?baris=ID` (opsional) | **berkas biner** PDF slip semua / satu penerima |

Unduhan: `?unduh=1` → header `attachment` (bawaan `inline`). Galat tetap JSON beramplop → klien periksa `Content-Type` (`application/json` = gagal).
Setiap unduhan tercatat di Audit Log server. Cetakan berstatus Draf bertanda "DRAF" (otomatis).

## 5. Bentuk `GET honor`
Belum dibuat: `{periode, ada:false, honor:null, pratinjau_komponen:[…], honor_lain:[{id,label,jumlah_penerima}]}`.
Sudah dibuat:
```
{ periode:{id,jenis,slug,tahun_ajaran,label}, ada:true,
  honor:{ dokumen:{id,status,status_label,judul,tempat,tanggal,ketua_nama,bendahara_nama,kepsek_nama,dibuat_oleh,dikunci_at,dikunci_oleh,boleh_ubah},
          komponen:[{id,komponen_id,kode,nama,judul_kolom,tipe,tarif,satuan,sumber,urut}],      // id = dok_komponen_id (dipakai di "komponen" & kunci peta)
          baris:[{id,no,guru_id,nama,jabatan,catatan,nilai:{"<id komponen>":{nilai,otomatis,manual}},rupiah:{"<id komponen>":int},total}],
          total_komponen:{"<id>":int}, total_jumlah:{"<id>":int}, total:int,
          utuh: true|false|null },                                                                // false = isi berubah sejak dikunci (tampilkan peringatan merah)
  pemeriksaan:[{level:"peringatan"|"info",kode,teks}], gambaran_hitung:{koreksi:{orang,total},rapot:{…},soal:{…}},
  petunjuk_pengawas:{"<guru_id>":jumlah_sesi_menurut_jadwal} }
```
- `nilai`, `rupiah`, `total_komponen`, `total_jumlah`, `petunjuk_pengawas` adalah **OBJEK JSON** berkunci id (string), bukan list.
- `nilai.otomatis` ≠ null → sel pernah dihitung otomatis; `nilai ≠ otomatis` → tampilkan "diubah". `manual:true` = diketik/hasil impor: **tidak ditimpa** Hitung otomatis kecuali `timpa:true`.
- Status: `draf` → `final` → `dikunci` (hanya final→dikunci; dikunci→final dengan alasan). Terkunci: semua perubahan 422 ("Honor ini sudah DIKUNCI…").
- `jabatan` dan `nama` adalah teks bebas dari pengguna → tampilkan sebagai TEKS biasa (jangan render sebagai HTML).
- Urutan `baris` = urutan di rekap/Excel/PDF (bawaan menurut hierarki jabatan; atur lewat `pindah`).

## 6. Perubahan API lain pada pembaruan ini (tanpa layar baru, tapi aplikasi perlu tahu)
1. **Master Guru** (`/admin/master/guru`): field baru **`bukan_pengajar`** (boolean) di daftar/detail, dan diterima saat simpan (POST buat / POST ubah).
   Bila aplikasi lama TIDAK mengirim field ini, nilainya TIDAK berubah. Arti: staf TU/operator — tetap bisa menerima honor, tetapi **tidak dihitung**
   sebagai guru di Dashboard (`penjadwalan.guru`), "Guru Kurang Jam", beban mengajar, dan statistik beranda publik (`/home` → jumlah guru).
2. **Dashboard** `GET /admin/dashboard` → `penjadwalan.guru`/`kurang` kini tanpa staf `bukan_pengajar`.
3. Hapus guru / hapus jadwal ujian lewat API kini ikut membersihkan data pembuat soal (sebelumnya hanya web).
4. Hapus jadwal ujian & pembuat soal: tidak ada perubahan bentuk respons.

## 7. Pengujian
101 pemeriksaan API (token sungguhan: akses semua peran × seluruh endpoint honor, pengaturan, alur honor, isian & validasi, hitung otomatis, pembuat soal,
unduhan, status/kunci/sidik jari, tambalan di §6) lulus; regresi web 496 (CLI) + suite HTTP honor lulus. Rencana tampilan Flutter: `flutter-muslimin/BLUEPRINT-HONOR.md`.
