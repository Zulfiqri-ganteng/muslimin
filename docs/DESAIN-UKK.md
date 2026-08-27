# Rencana Fitur: Sistem UKK / Uji Kompetensi Keahlian

Dokumen kerja step-by-step. **Centang tiap fase selesai** agar pekerjaan bisa
dilanjutkan di sesi baru tanpa kehilangan konteks.

Dibuat: 2026-08-27. Basis: repo `muslimin` (CI4 4.7, MySQL, shared hosting Rumahweb).

---

## Keputusan user (2026-08-27)

1. **Peserta UKK:** pakai tabel `siswa` yang sudah ada (tidak duplikasi data).
   `peserta_ukk` = tabel PENDAFTARAN (siswa_id + paket_soal + jadwal + status +
   hasil), bukan data siswa baru.
2. **Tempat uji:** tabel master baru `tempat_uji`, opsional tertaut ke `lab`
   (SIMLAB) bila lokasinya lab sekolah — tapi bisa juga lokasi luar (mitra
   DUDI/industri).
3. **Penilaian:** format resmi berkomponen — Persiapan Kerja, Proses, Hasil
   Kerja, Sikap Kerja, Waktu — dengan **bobot % per paket soal** (bisa diatur,
   default 10/30/40/10/10). Penguji internal & eksternal **masing-masing input
   nilai sendiri**; sistem hitung Nilai Akhir per penguji lalu rata-rata jadi
   nilai akhir peserta.
4. **Dokumen:** Kisi-kisi & Jobsheet = **upload PDF** per paket soal (dokumen
   resmi dari luar sistem). Berita Acara & Sertifikat = **auto-generate PDF**
   dari data sistem (pola sama seperti `Admin\Cetak` yang sudah ada).
5. **Platform:** WEB DULU bertahap, Android (API + Flutter) menyusul setelah
   web stabil — sama seperti pola SIMLAB.

**Aturan kecil disepakati (mengikuti preseden SIMLAB/Lab Gambar):**
- Nomor peserta, nomor berita acara, nomor sertifikat: auto-generate (bisa
  ditimpa manual bila perlu di fase lanjut).
- File kisi-kisi/jobsheet: PDF saja, maks 10 MB, helper baru `ukkdoc_helper`
  (pola sama seperti `labimage_helper` tapi untuk dokumen, tanpa konversi
  gambar).

---

## Peta 12 permintaan → modul

| Permintaan user | Modul (tabel) |
|---|---|
| Data peserta UKK | `peserta_ukk` (pendaftaran, reuse `siswa`) |
| Paket soal | `paket_soal_ukk` |
| Kisi-kisi | kolom `kisi_kisi_file` di `paket_soal_ukk` |
| Jobsheet UKK | kolom `jobsheet_file` di `paket_soal_ukk` |
| Jadwal UKK | `jadwal_ukk` |
| Penguji internal | `jadwal_ukk_penguji` (tipe internal → `guru`) |
| Penguji eksternal | `penguji_eksternal` + `jadwal_ukk_penguji` (tipe eksternal) |
| Tempat uji | `tempat_uji` (opsional →`lab`) |
| Penilaian | `nilai_ukk` (per peserta per penguji) |
| Berita acara | `berita_acara_ukk` |
| Sertifikat | `sertifikat_ukk` |
| Rekap hasil UKK | *(query + export, bukan tabel)* — `Admin\LaporanUkk` |

Dipakai ulang dari sistem yang ada (TIDAK diduplikasi): `siswa`, `guru`,
`jurusan`, `tahun_ajaran`, `lab` (opsional).

---

## ERD ringkas (9 tabel baru)

```
tempat_uji (opsional →lab)          penguji_eksternal
        │                                   │
        └──────────┐              ┌─────────┘
                    ▼              ▼
paket_soal_ukk ──< jadwal_ukk ──< jadwal_ukk_penguji (>= guru)
        │                 │
        │                 └──< peserta_ukk (>= siswa)
        └────────────────────────< peserta_ukk
                                        │
                                        ├──< nilai_ukk (>= guru / penguji_eksternal)
                                        └──1:1── sertifikat_ukk

jadwal_ukk ──< berita_acara_ukk
```

### Kolom inti per tabel
- **tempat_uji**: kode*, nama, alamat, kapasitas, lab_id?(→lab SET NULL),
  keterangan. Soft delete.
- **penguji_eksternal**: kode*, nama, instansi, jabatan, no_hp, email,
  keterangan. Soft delete.
- **paket_soal_ukk**: kode*, nama, jurusan_id?(→jurusan SET NULL),
  tahun_ajaran_id?(→tahun_ajaran SET NULL), deskripsi, kisi_kisi_file?,
  jobsheet_file?, bobot_persiapan/proses/hasil/sikap/waktu (decimal, default
  10/30/40/10/10), kkm (decimal, default 70), keterangan. Soft delete.
- **jadwal_ukk**: paket_soal_id(→paket_soal_ukk CASCADE),
  tempat_uji_id?(→tempat_uji SET NULL), tahun_ajaran_id?(→tahun_ajaran SET
  NULL), tanggal_mulai, tanggal_selesai?, sesi?, keterangan. Soft delete.
- **jadwal_ukk_penguji** (pivot): jadwal_ukk_id(→jadwal_ukk CASCADE),
  tipe(internal/eksternal), guru_id?(→guru SET NULL),
  penguji_eksternal_id?(→penguji_eksternal SET NULL), peran(ketua/anggota).
  Hard delete (pola sama `jadwal_lab`).
- **peserta_ukk**: siswa_id(→siswa CASCADE), paket_soal_id(→paket_soal_ukk
  CASCADE), jadwal_ukk_id?(→jadwal_ukk SET NULL),
  tahun_ajaran_id?(→tahun_ajaran SET NULL), no_peserta (auto), status
  (terdaftar/hadir/tidak_hadir/lulus/tidak_lulus), nilai_akhir?, predikat?,
  keterangan. UNIQUE(siswa_id, paket_soal_id). Soft delete.
- **nilai_ukk**: peserta_ukk_id(→peserta_ukk CASCADE),
  tipe_penguji(internal/eksternal), guru_id?(→guru SET NULL),
  penguji_eksternal_id?(→penguji_eksternal SET NULL), persiapan_skor,
  proses_skor, hasil_skor, sikap_skor, waktu_skor (decimal 0-100),
  nilai_akhir (dihitung dari bobot paket_soal_ukk), tanggal_nilai,
  keterangan. Soft delete.
- **berita_acara_ukk**: jadwal_ukk_id(→jadwal_ukk CASCADE), nomor_ba* (auto),
  tanggal, catatan, keterangan. Soft delete.
- **sertifikat_ukk**: peserta_ukk_id*(→peserta_ukk CASCADE, unik = 1:1),
  nomor_sertifikat* (auto), tanggal_terbit, keterangan. Soft delete.

Semua InnoDB + utf8mb4, mengikuti gaya migrasi yang sudah ada
(`2026-08-26-000001_CreateLabInventaris`).

---

## Fase kerja (web, bertahap)

- [x] **U0 — Fondasi DB & Model** ✅ SELESAI 2026-08-27
      Migrasi `2026-08-27-000002_CreateUkk` (9 tabel, urutan FK aman) + 9 model
      (pola BaseMaster: `id => permit_empty|is_natural`, softDeletes kecuali
      `jadwal_ukk_penguji` hard delete pola pivot `jadwal_lab`).
      **Teruji:** `php spark migrate` sukses; 9 tabel + 17 FK terbentuk sesuai
      rencana (dicek via `information_schema`); `php -l` bersih semua model;
      8 SQL join `withRelations` divalidasi ke DB (LIMIT 0) — tak ada kolom
      salah. Model: TempatUji, PengujiEksternal, PaketSoalUkk (+totalBobot),
      JadwalUkk, JadwalUkkPenguji (hard delete), PesertaUkk (+nomorBerikutnya,
      sudahTerdaftar — is_unique CI4 TIDAK dukung 4+ param jadi cek duplikat
      manual, bukan lewat validationRules), NilaiUkk (+hitungNilaiAkhir
      berbobot, rataRataUntukPeserta), BeritaAcaraUkk (+nomorBerikutnya),
      SertifikatUkk (+nomorBerikutnya).
- [x] **U1 — Master Tempat Uji + Penguji Eksternal** ✅ SELESAI 2026-08-27
      `Admin\Master\TempatUji` & `PengujiEksternal` (extends BaseMaster) —
      index/store/update/collect/export/template/import + cleanupRelations.
      View pakai komponen generik `masterList`. Sidebar grup baru **"UJI
      KOMPETENSI (UKK)"**. Rute di grup `master`. cache_helper: ripple
      lab→tempat_uji + singles opt_tempat_uji/opt_penguji_eksternal.
      TempatUji::cleanupRelations lepas `jadwal_ukk.tempat_uji_id`.
      PengujiEksternal::cleanupRelations hapus baris `jadwal_ukk_penguji`
      terkait (hard delete pivot) + lepas `nilai_ukk.penguji_eksternal_id`
      (soft delete, skor historis tetap tersimpan).
      **Teruji e2e via HTTP** (localhost/muslimin/public, login admin/admin123,
      CSRF cookie): GET 200 kedua halaman; POST buat tempat uji + penguji
      eksternal → tampil di daftar; export xlsx valid (PK); delete → soft
      delete sukses. `php -l` bersih semua file. Data uji + audit dibersihkan.
- [x] **U2 — Master Paket Soal (+ upload Kisi-kisi & Jobsheet)** ✅ SELESAI 2026-08-27
      `Admin\Master\PaketSoalUkk` (extends BaseMaster) + helper baru
      `app/Helpers/ukkdoc_helper.php` (pola `labimage_helper` tapi TANPA
      konversi — validasi PDF + batas 10 MB, simpan ke `public/uploads/ukk/`
      nama unik). Form modal `enctype="multipart/form-data"` (masterList pakai
      form POST biasa, BUKAN fetch/AJAX, jadi upload file jalan langsung tanpa
      perubahan JS). Upload kisi-kisi/jobsheet OPSIONAL saat store/update —
      field hanya disentuh di `$data` bila ada unggahan baru (kunci
      dihilangkan total kalau tidak, supaya file lama tak tertimpa null).
      Validasi **total bobot 5 komponen harus 100%** (ditolak dgn flash error
      bila tidak, sebelum simpan). Endpoint terpisah `hapus-berkas/(:num)/
      (kisi-kisi|jobsheet)` untuk hapus 1 dokumen tanpa mengganti.
      `cleanupRelations` sengaja TIDAK hapus baris anak (jadwal_ukk/
      peserta_ukk pakai FK CASCADE tapi soft delete tak memicu FK — riwayat
      UKK lama dibiarkan utuh), hanya hapus 2 berkas fisik agar tak menumpuk
      di disk. Import Excel: bobot/KKM cuma diisi default untuk kode BARU
      (kode existing dicek via `withDeleted()->where('kode',...)` supaya
      pengaturan bobot manual di UI tak tertimpa saat re-import).
      **Teruji e2e HTTP:** bobot invalid (total 90%) DITOLAK, tak masuk DB;
      bobot valid (100%) + upload 2 PDF sekaligus (curl multipart) → tersimpan
      dgn nama unik di disk & DB; link dokumen tampil di daftar; hapus 1
      berkas → file hilang, kolom NULL, berkas lain utuh; export xlsx valid
      (PK); delete baris → **kedua berkas fisik ikut terhapus** via
      cleanupRelations meski baris hanya soft-deleted. `php -l` bersih semua
      file. Data uji + audit + berkas fisik dibersihkan.
- [x] **U3 — Peserta UKK (pendaftaran)** ✅ SELESAI 2026-08-27
      `Admin\PesertaUkk` (bukan BaseMaster — workflow, 2 halaman): index
      (filter paket soal/status/cari + ringkasan total/lulus/tidak lulus +
      modal Ubah Status) & `daftarkan` (2 langkah: pilih paket soal+kelas via
      `data-autosubmit` → checklist siswa aktif kelas itu yang BELUM
      terdaftar pada paket tsb, "pilih semua" via Alpine, submit POST massal).
      No_peserta auto `UKK-{KODE}-001` (`PesertaUkkModel::nomorBerikutnya`).
      **Pemulihan pendaftaran**: karena UNIQUE(siswa_id,paket_soal_id) tetap
      berlaku pada baris soft-deleted, `daftarkanStore` cek `withDeleted()`
      dulu — bila baris lama ada & `deleted_at` terisi → di-UPDATE (pulihkan,
      nomor baru), bukan INSERT baru (kalau langsung insert akan bentrok
      unique index). Sidebar grup baru **"PELAKSANAAN UKK"**. Rute
      `admin/peserta-ukk/*` (grup auth, LUAR grup `master` — pola
      Peminjaman/JurnalLab). Status lulus/tidak_lulus nanti diisi otomatis
      oleh U5 (Penilaian), tapi bisa diubah manual di sini.
      **Teruji e2e HTTP:** daftar 1 siswa → `UKK-TESTUKK-001` tersimpan; ubah
      status→hadir; hapus (soft delete); daftar ulang siswa yang sama →
      PULIH jadi `UKK-TESTUKK-002` (bukan error unique constraint); siswa
      yang sudah aktif terdaftar otomatis hilang dari checklist; paksa
      daftar ulang via POST langsung → 0 didaftarkan, 1 dilewati (guard
      ganda jalan); filter index by paket+status cocok. `php -l` bersih
      semua file. Data uji (siswa/paket/peserta/audit) dibersihkan.
- [x] **U4 — Jadwal UKK + penugasan penguji** ✅ SELESAI 2026-08-27
      `Admin\JadwalUkk` (workflow, 2 halaman): index (filter paket
      soal/cari + modal Tambah/Ubah — form JSON `data-row`-style inline
      Alpine, pola `openEdit(r)` mirip `masterList` tapi custom krn bukan
      BaseMaster; kolom "Jumlah Penguji" dihitung via query `GROUP BY
      jadwal_ukk_id` terpisah lalu digabung di controller) & `penguji/(:num)`
      (kelola penugasan: radio Internal/Eksternal via Alpine `x-show` →
      pilih guru atau penguji eksternal + peran ketua/anggota; guard
      duplikat — guru/penguji eksternal yang sama tak bisa ditugaskan dua
      kali pada jadwal yang sama).
      `JadwalUkk::delete` (bukan BaseMaster, jadi cleanup ditulis manual
      dalam transaksi): hapus SEMUA baris `jadwal_ukk_penguji` milik jadwal
      itu (hard delete, tak ada nilai riwayat berdiri sendiri) + lepas
      `peserta_ukk.jadwal_ukk_id` jadi NULL (peserta TETAP ada, cuma putus
      dari jadwal yang dihapus) sebelum soft-delete baris jadwal.
      `JadwalUkkModel::optionsUntukPaket()` baru (opsi jadwal 1 paket soal,
      dipakai form pendaftaran peserta U3). Sidebar "PELAKSANAAN UKK"
      +Jadwal UKK. Rute `admin/jadwal-ukk/*` (grup auth, luar `master`;
      rute `penguji/(:num)` diletakkan SEBELUM `(:num)` update generik,
      pola sama `aset/komputer`).
      **Teruji e2e HTTP:** buat jadwal → tersimpan; halaman penguji tampil
      nama paket; tugaskan 1 guru (ketua) + 1 penguji eksternal (anggota) →
      2 baris pivot; tugaskan guru yang sama lagi → DITOLAK ("sudah
      ditugaskan"); hapus 1 penugasan → sisa 1; ubah jadwal (tanggal+sesi)
      → tersimpan; hapus jadwal → soft-deleted DAN pivot tersisa 0 (cleanup
      transaksi jalan). `php -l` bersih semua file. Data uji (paket/
      penguji eksternal/tempat uji/jadwal/pivot/audit) dibersihkan.
- [x] **U5 — Penilaian** ✅ SELESAI 2026-08-27
      `Admin\PenilaianUkk` (workflow, 2 halaman): index (semua jadwal + progres
      bar "X/Y dinilai" per jadwal, link Nilai) & `jadwal/(:num)` (matriks
      peserta × penguji tertugas — kolom dinamis sesuai `jadwal_ukk_penguji`;
      klik sel buka modal Alpine `openNilai(...)` terisi otomatis dari
      `nilaiMap` bila sudah ada nilai). `simpan()`: upsert `nilai_ukk`
      (cek `withDeleted()` by peserta+tipe+guru/eksternal, sama pola
      pemulihan seperti U3) → `NilaiUkkModel::hitungNilaiAkhir()` (dari U0)
      hitung nilai berbobot per penguji → `rataRataUntukPeserta()` hitung
      ulang agregat → `peserta_ukk.nilai_akhir`/`status`/`predikat` di-update
      otomatis (lulus/Kompeten jika ≥ kkm paket, else tidak_lulus/Belum
      Kompeten), semua dalam SATU transaksi. **Guard anti-tamper**: submit
      ditolak bila peserta bukan milik jadwal ini ATAU penguji (guru/
      eksternal) tak ditugaskan pada `jadwal_ukk_penguji` jadwal tsb —
      dicek server-side, bukan cuma disembunyikan di UI. Rute
      `admin/penilaian-ukk/*` (grup auth, luar `master`). Link cepat "Nilai"
      ditambah di baris index Jadwal UKK. Sidebar +Penilaian UKK.
      **Teruji e2e HTTP:** isi nilai internal (semua skor 80) → nilai_akhir
      penguji **80.00** (bobot default 10/30/40/10/10 pada skor seragam =
      skor itu sendiri, matematis benar) + agregat peserta langsung
      80.00/lulus/Kompeten; tambah nilai eksternal (semua 60) → agregat jadi
      rata-rata **70.00**/lulus (batas KKM=70 inklusif, `>=` terbukti benar);
      submit ulang nilai internal (semua 40) → **UPDATE baris yang sama**
      (tetap 2 baris nilai_ukk, tidak duplikat) → agregat turun jadi
      **50.00/tidak_lulus**; submit dari guru yang TIDAK ditugaskan pada
      jadwal ini → ditolak "tidak ditugaskan", tak ada baris masuk DB.
      `php -l` bersih semua file. Data uji (siswa/paket/jadwal/pivot/nilai/
      peserta/audit) dibersihkan.
- [x] **U6 — Berita Acara** ✅ SELESAI 2026-08-27
      `Admin\BeritaAcaraUkk` (workflow, 1 halaman + PDF): index dengan modal
      Tambah/Ubah (pola sama JadwalUkk — jadwal_ukk_id HANYA muncul di mode
      tambah via `x-show="mode==='add'"`, tak bisa diubah setelah dibuat).
      `store()` pakai `BeritaAcaraUkkModel::nomorBerikutnya()` (dari U0,
      format `BA-UKK-{tahun}-001`). `pdf($id)`: dompdf A4 portrait pola
      persis `LaporanLab`/`Cetak` (`Options isRemoteEnabled` + DejaVu Sans),
      view `pdf/berita_acara_ukk.php` pakai `kop_pdf()` + narasi baku
      ("Pada hari ini {hari} tanggal {tgl}...") + tabel peserta (no
      peserta/nama/status) + tabel penguji (nama/tipe/peran) + blok tanda
      tangan **digenerate otomatis per penguji tertugas** (float 30% per
      box, bukan slot tetap 2/3). Rute `admin/berita-acara-ukk/*` (grup
      auth). Sidebar +Berita Acara.
      **Teruji e2e HTTP:** buat BA → nomor `BA-UKK-2026-001` otomatis
      tersimpan dengan jadwal_ukk_id benar; PDF 200 dengan magic bytes
      `%PDF-1.7` (23 KB, kop+narasi+2 tabel+ttd merender tanpa error);
      ubah tanggal+catatan → tersimpan; hapus → soft delete. `php -l` bersih
      semua file. Data uji (siswa/paket/jadwal/pivot/peserta/BA/audit)
      dibersihkan.
- [x] **U7 — Sertifikat** ✅ SELESAI 2026-08-27
      `Admin\SertifikatUkk` (workflow, 1 halaman + PDF). Dropdown Terbitkan
      hanya berisi peserta **lulus & belum bersertifikat**
      (`PesertaUkkModel::optionsLulusBelumSertifikat()` baru — filter
      status='lulus' + `whereNotIn` id yang sudah ada di `sertifikat_ukk`).
      `nomorBerikutnya()` (dari U0, format `SERT-UKK-{tahun}-001`). Guard
      ganda: UI sembunyikan peserta yang sudah lulus+bersertifikat DAN model
      punya `is_unique[sertifikat_ukk.peserta_ukk_id]` sebagai pertahanan
      server-side (submit paksa via POST langsung tetap ditolak). `pdf($id)`:
      dompdf **A4 LANDSCAPE** (beda dari BA yang portrait — sertifikat perlu
      lebar), view `pdf/sertifikat_ukk.php` desain dekoratif (frame border
      ganda, logo, nama besar, tabel nilai+predikat, blok ttd Kepala Sekolah
      dari `SettingModel::headmaster_name/headmaster_nip`). Rute
      `admin/sertifikat-ukk/*`. Sidebar +Sertifikat.
      **Teruji e2e HTTP:** peserta lulus tampil di dropdown; terbitkan →
      nomor `SERT-UKK-2026-001` otomatis; peserta ybs LANGSUNG hilang dari
      dropdown; PDF 200 dengan `%PDF-1.7` (22 KB, landscape+logo+tabel
      render tanpa error); terbitkan PAKSA lagi utk peserta yang sama via
      POST langsung → DITOLAK (unique constraint), tetap 1 baris di DB; ubah
      tanggal/keterangan → tersimpan; hapus → soft delete. `php -l` bersih
      semua file. Data uji (siswa/paket/peserta/sertifikat/audit)
      dibersihkan.
- [x] **U8 — Rekap / Laporan UKK** ✅ SELESAI 2026-08-27
      `Admin\LaporanUkk` (index/pdf/excel), pola persis `LaporanLab` tapi
      TANPA library terpisah (`hitung()` private di controller — 1 tabel
      utama `peserta_ukk`, tak perlu abstraksi seperti `LabReport` yang
      dipakai lintas 5+ tabel; akan diekstrak ke Library kalau nanti API
      Android butuh agregasi yang sama). Agregasi RAW query builder dengan
      `paket_soal_ukk.deleted_at IS NULL` DIKUALIFIKASI di kondisi JOIN
      (pola `SiswaModel::statistik`). Filter opsional **tahun ajaran**
      (`peserta_ukk.tahun_ajaran_id`, bukan rentang tanggal — field ini
      sudah ada langsung di tabel, tak perlu join `jadwal_ukk`). Tampil:
      stat tile total+per status (5 status) + rata-rata nilai akhir + tabel
      rekap per paket soal (total/lulus/tidak lulus/rata²) + tabel rekap
      per jurusan (join `paket_soal_ukk.jurusan_id`→`jurusan`). PDF A4
      portrait (`kop_pdf()`, pola `laporan_lab`), Excel (`kop_excel_prepend`,
      3 blok: ringkasan/per-paket/per-jurusan). Rute
      `admin/laporan-ukk/{,/pdf,/excel}`. Sidebar masuk grup **LAPORAN**
      existing (bareng Laporan Lab).
      **Teruji e2e HTTP** dengan data campuran (4 peserta, 2 paket soal beda
      jurusan TKJ/MPLB, status lulus/tidak_lulus/terdaftar campur, nilai
      85/50/90/null): index 200, angka stat tile **cocok persis** hasil
      hitung manual (total 4, terdaftar 1, lulus 2, tidak_lulus 1, rata-rata
      **75.0** = avg(85,50,90) dgn NULL diabaikan otomatis oleh SQL AVG);
      filter tahun_ajaran_id tak-cocok → total jadi 0 (filter WHERE benar
      diterapkan); PDF 200 `%PDF-1.7`; Excel 200 `PK` (xlsx valid). `php -l`
      bersih semua file. Data uji (siswa/paket/peserta/audit) dibersihkan.

**🎉 WEB SELESAI (U0–U8) — seluruh 12 permintaan awal (peserta, paket
soal, kisi-kisi, jobsheet, jadwal, penguji internal, penguji eksternal,
tempat uji, penilaian, berita acara, sertifikat, rekap) sudah punya
alur kerja lengkap end-to-end dan teruji.** Belum di-commit — user commit
sendiri. Langkah berikut (sesuai arahan user "web dulu baru Android"):
API `/api/v1/admin/*` (pola A1 SIMLAB: `BaseCrud` utk master, controller
workflow tersendiri utk peserta/jadwal/penilaian/BA/sertifikat) lalu
Flutter, MENYUSUL saat diminta.

**Konvensi WAJIB diikuti** (dari `[[project-lab-inventaris]]` &
`[[project-penjadwalan-kbm]]`): controller master warisi `BaseMaster`
(CRUD+import/export+bulk gratis); tiap mutasi panggil
`master_data_changed('<modul>')`; tiap halaman pasang help card
`admin/partials/help`; rebuild Tailwind tiap tambah kelas; uji lokal Apache
`http://localhost/muslimin/public`, login field `login`, admin/admin123.
Deploy: `cd ~/kangmuslim && git pull && phpm spark migrate`.
