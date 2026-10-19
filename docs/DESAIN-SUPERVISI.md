# DESAIN — Supervisi Guru (menu KURIKULUM → Supervisi Guru)

Ditulis 2026-10-19 malam. **Status: RANCANGAN SIAP KODING — belum ada kode.** User: "pakai saran lu untuk konsep fitur & output
laporannya" (klien belum punya format lembar supervisi). Kerjakan per fase S1–S5, satu fase lalu BERHENTI & lapor; JANGAN commit/push
sebelum diminta. Setiap fase = web + (bila ada) API + kontrak; uji secukupnya (perintah `php spark dev:uji-supervisi`).

## 1. Konsep
Supervisi **akademik** guru per semester, siklus yang lazim di sekolah:
1. **Instrumen** (lembar penilaian) — bawaan 3 instrumen (bisa diubah Admin; bila klien kelak membawa format sendiri, cukup ubah
   instrumennya, kode tidak berubah):
   - **Perencanaan Pembelajaran** (Modul Ajar/RPP),
   - **Pelaksanaan Pembelajaran** (observasi kelas),
   - **Penilaian/Asesmen Pembelajaran**.
2. **Program/Jadwal** — siapa disupervisi, oleh siapa, kapan, mapel & kelas yang diamati, instrumen yang dipakai.
3. **Pelaksanaan/Penilaian** — supervisor mengisi skor tiap butir (1–4), catatan, lalu temuan & tindak lanjut.
4. **Laporan** — Program Supervisi (Excel), Lembar Hasil per guru (PDF siap tanda tangan), Rekap Hasil semester (Excel).

Skor butir: **1 = Kurang, 2 = Cukup, 3 = Baik, 4 = Sangat Baik**; butir boleh "tidak dinilai" (tidak ikut dihitung).
Nilai instrumen = jumlah skor ÷ (jumlah butir dinilai × 4) × 100 (2 desimal). Nilai kunjungan = rata-rata nilai instrumennya.
Predikat: **91–100 Amat Baik · 76–90,99 Baik · 61–75,99 Cukup · ≤ 60,99 Kurang** (konstanta di library, satu tempat).
Tindak lanjut (pilih, boleh lebih dari satu): Pembinaan individual · Pembinaan kelompok/KKG-MGMP · Pelatihan/workshop · Contoh praktik
baik (model) · Tidak perlu tindak lanjut — + teks bebas rekomendasi.

Tahun ajaran = `settings.academic_year` (pola `Libraries\Keterlambatan::tahunAjaran()`; tabel `tahun_ajaran` kosong); semester
dipilih (ganjil/genap), bawaan menurut bulan (Jul–Des ganjil, Jan–Jun genap).

## 2. Menu (sudah ada di `MenuAdmin::definisi()`, kunci `supervisi`, masih `'segera' => true`)
`admin/supervisi/instrumen` Instrumen Supervisi · `admin/supervisi/jadwal` Jadwal Supervisi · `admin/supervisi/penilaian` Penilaian
Supervisi · `admin/supervisi/rekap` Rekap Supervisi (awalan `admin/supervisi`). Entri ini satu kunci → hapus `'segera'` sekali di akhir S3 (keempat halaman web
sudah jadi). Tambah `'api' => ['supervisi']` di S4. Hak: Lihat/Kelola lewat Hak Akses (bawaan hanya Admin). Urutan item sudah sesuai
aturan sidebar (master → proses → laporan); `dev:uji-hak` menjaga urutan grup & menu Segera — sesuaikan ujinya saat tanda dicabut.

## 3. Tabel (satu migrasi S1, idempoten; semua InnoDB)
- `supervisi_instrumen`: id, kode VARCHAR(40) unik, nama VARCHAR(150), jenis ENUM('perencanaan','pelaksanaan','penilaian','lainnya'),
  keterangan VARCHAR(255) null, aktif TINYINT(1) def 1, urut INT, created_at, updated_at.
- `supervisi_butir`: id, instrumen_id FK CASCADE, aspek VARCHAR(150) (kelompok, mis. "Kegiatan Pendahuluan"), uraian VARCHAR(500),
  urut INT, aktif TINYINT(1) def 1, timestamps.
- `supervisi_jadwal` (satu kunjungan): id, tahun_ajaran VARCHAR(20), semester ENUM('ganjil','genap'), tanggal DATE, jam TIME null,
  guru_id FK SET NULL + nama_guru (salinan) + nip_guru (salinan), mapel_id FK `mata_pelajaran` SET NULL + nama_mapel (salinan),
  kelas_id FK SET NULL + nama_kelas (salinan), supervisor_id FK `guru` SET NULL + nama_supervisor + jabatan_supervisor (salinan),
  instrumen JSON (daftar instrumen_id dipakai), status ENUM('dijadwalkan','selesai','batal') def 'dijadwalkan', catatan VARCHAR(255),
  nilai_akhir DECIMAL(5,2) null, predikat VARCHAR(20) null, dicatat_oleh_id, dicatat_oleh, timestamps. Indeks (tahun_ajaran, semester),
  (guru_id).
- `supervisi_nilai` (hasil satu instrumen pada satu kunjungan): id, jadwal_id FK CASCADE, instrumen_id FK SET NULL, nama_instrumen
  (salinan), skor_total INT, skor_maks INT, nilai DECIMAL(5,2), predikat VARCHAR(20), kekuatan TEXT null, kelemahan TEXT null,
  rekomendasi TEXT null, tindak_lanjut JSON null, dinilai_at DATETIME, dinilai_oleh VARCHAR(150), timestamps. Unik (jadwal_id,
  instrumen_id).
- `supervisi_nilai_butir`: id, nilai_id FK CASCADE, butir_id FK SET NULL, aspek & uraian (SALINAN — instrumen yang diubah kemudian
  tidak mengubah hasil lama), skor TINYINT null (1–4, null = tidak dinilai), catatan VARCHAR(255) null.
Migrasi mengisi 3 instrumen bawaan + butirnya (hanya bila tabel instrumen kosong). Perintah pembantu "Kembalikan instrumen bawaan"
membuat salinan baru (tidak menghapus yang dipakai hasil lama).

### Butir bawaan (ringkas; tulis lengkap di migrasi)
- **Perencanaan (Modul Ajar):** identitas modul & alokasi waktu · capaian & tujuan pembelajaran jelas & terukur · asesmen awal/
  diagnostik · pemilihan model/metode (berdiferensiasi) · kegiatan pendahuluan · kegiatan inti · kegiatan penutup · media, alat &
  sumber belajar · rencana asesmen formatif & sumatif + rubrik · lampiran (LKPD, bahan bacaan, glosarium) · refleksi guru & siswa.
- **Pelaksanaan (observasi kelas):** apersepsi & motivasi · menyampaikan tujuan · penguasaan materi · pembelajaran berpusat pada
  siswa · diferensiasi · pemanfaatan media/TIK · interaksi & pertanyaan pemantik · umpan balik · pengelolaan kelas & waktu · penguatan
  Profil Pelajar Pancasila · kesimpulan/refleksi bersama · asesmen di akhir & tindak lanjut.
- **Penilaian:** teknik sesuai tujuan · ragam teknik & instrumen · rubrik/pedoman penskoran · pengolahan & pencatatan hasil · umpan balik
  hasil ke siswa · tindak lanjut (remedial/pengayaan).

## 4. Logika (satu sumber web + API)
`App\Libraries\Supervisi` (instrumen & butir CRUD + urutkan; jadwal CRUD + buat massal; saran mapel/kelas guru dari `pengampu`;
saran supervisor = guru berjabatan Kepala Sekolah / Wakil Kepala Sekolah Bidang Kurikulum (tabel `jabatan`/`guru_jabatan`) di atas,
guru lain di bawah; simpanPenilaian (validasi skor 1–4/null, hitung nilai/predikat, simpan salinan butir, status → selesai bila semua
instrumen terisi); rekap(ta, semester, saringan)); `SupervisiCetak` (Excel program, PDF lembar hasil — Dompdf pola `HonorCetak`/
`UjianCetak`, Excel rekap — PhpSpreadsheet). Audit Log semua ubahan ("(via aplikasi)" untuk API). Hasil gagal membawa `kode`
404|409|422 (pola Keterlambatan) agar API memakai `tolakHasil`.
Aturan: satu guru boleh beberapa kunjungan per semester; jadwal berstatus selesai tidak bisa dihapus (batalkan/ubah saja oleh Admin);
penilaian boleh disimpan sebagai draf (status tetap dijadwalkan) lalu diselesaikan.

## 5. Halaman web (kit F5, Alpine CSP, JS terpisah)
- **Instrumen:** daftar instrumen (aktif/nonaktif, jumlah butir) → halaman butir per instrumen (tambah/ubah/hapus, urutkan naik-turun,
  kelompok aspek). Instrumen yang sudah dipakai hasil tidak bisa dihapus (nonaktifkan).
- **Jadwal:** saring TA/semester/status/guru; tambah/ubah (guru → saran mapel & kelas; supervisor; tanggal & jam; centang instrumen);
  **Buat massal**: pilih banyak guru + supervisor + rentang tanggal → dibagi rata per hari kerja (maks N per hari) — bisa diubah satu per
  satu sesudahnya; tombol **Unduh Program Supervisi (Excel)**.
- **Penilaian:** daftar kunjungan semester ini (status, nilai, predikat) → **lembar penilaian**: tab per instrumen, tiap butir radio
  1–4 + "tidak dinilai" + catatan kecil; ringkasan nilai & predikat dihitung langsung di layar; kekuatan, kelemahan, rekomendasi, centang
  tindak lanjut; **Simpan draf** / **Simpan & selesai**; tombol **Cetak Lembar Hasil (PDF)**.
- **Rekap:** saring TA/semester/predikat; tabel guru × instrumen (nilai), rata-rata, predikat, tindak lanjut; kartu ringkasan jumlah per
  predikat; **Unduh Rekap (Excel)**; guru yang belum disupervisi semester ini ditandai.

## 6. Keluaran (laporan)
1. **Excel "PROGRAM SUPERVISI AKADEMIK"** — judul + nama sekolah (pola `KeterlambatanExcel::namaSekolah()`) + TA & semester; kolom NO |
   HARI/TANGGAL | JAM | NAMA GURU | MAPEL | KELAS | SUPERVISOR | INSTRUMEN | KET; kaki: kota, tanggal, "Kepala Sekolah", nama &
   NIP dari `settings.headmaster_name/nip`. Kertas A4 mendatar, Times New Roman 11 (gaya Excel sekolah lain).
2. **PDF "LEMBAR HASIL SUPERVISI AKADEMIK"** per kunjungan — kop sekolah (logo `settings.logo`), identitas (nama guru, NIP, mapel,
   kelas, hari/tanggal, supervisor), satu tabel per instrumen (No | Aspek/Butir | skor 1–4 dicentang | Catatan), jumlah skor, nilai,
   predikat; kekuatan, kelemahan, rekomendasi, tindak lanjut; tanda tangan **Guru yang disupervisi · Supervisor · Mengetahui Kepala
   Sekolah**. Tombol unduh per kunjungan + unduh gabungan (satu PDF untuk semua kunjungan terpilih).
3. **Excel "REKAP HASIL SUPERVISI AKADEMIK"** semester — lembar "Rekap": NO | NAMA GURU | MAPEL | nilai per instrumen | RATA-RATA |
   PREDIKAT | TINDAK LANJUT; lembar "Ringkasan": jumlah guru per predikat + yang belum disupervisi; TTD Kepala Sekolah.

## 7. API Android (S4) — `api/v1/supervisi…`, kontrak `docs/API-SUPERVISI.md`
Guna utama di HP/tablet: supervisor mengisi lembar penilaian **di kelas**. Endpoint: `GET supervisi/meta` (TA, semester bawaan,
instrumen+butir aktif, guru, saran supervisor, hak) · `GET supervisi/jadwal?ta=&semester=&status=` · `GET supervisi/jadwal/{id}`
(+ nilai & butir) · `POST supervisi/jadwal`, `POST supervisi/jadwal/{id}/ubah`, `DELETE supervisi/jadwal/{id}` · `POST
supervisi/jadwal/{id}/nilai` (body: per instrumen {skor per butir_id, catatan, kekuatan, kelemahan, rekomendasi, tindak_lanjut[],
selesai: bool}) · `GET supervisi/rekap` · unduhan `GET supervisi/program.xlsx?…`, `GET supervisi/jadwal/{id}/lembar.pdf`, `GET
supervisi/rekap.xlsx?…`. Hak per aksi = `HakAkses::boleh` pada alamat web padanannya (pola `Api\Keterlambatan`). Kelola instrumen
TIDAK di aplikasi (web saja).

## 8. Fase kerja
- **S1** — migrasi (5 tabel + instrumen bawaan) · `Libraries\Supervisi` bagian instrumen & jadwal · halaman **Instrumen** & **Jadwal**
  (termasuk buat massal) · uji `dev:uji-supervisi` (instrumen, jadwal, hak).
- **S2** — penilaian: `simpanPenilaian`, hitung nilai/predikat, salinan butir, draf/selesai · halaman **Penilaian** (lembar per
  instrumen, JS hitung langsung) · uji perhitungan & batas predikat (60,99/61/75,99/76/90,99/91).
- **S3** — `SupervisiCetak`: Excel Program, PDF Lembar Hasil (satuan & gabungan), Excel Rekap · halaman **Rekap** · hapus tanda
  `segera` · uji isi berkas (baca ulang .xlsx, PDF berawalan `%PDF`).
- **S4** — `Api\Supervisi` + rute + `'api' => ['supervisi']` + `docs/API-SUPERVISI.md` · uji API di `dev:uji-supervisi`.
- **S5** — kontrak Flutter `C:\flutter-muslimin\BLUEPRINT-SUPERVISI.md` + fixture HTTP nyata (pola `tangkap_manual.php`: data fiktif
  lalu dihapus; fixture di folder sendiri `test/fixtures/supervisi/`) · `docs/PANDUAN-DEPLOY-SUPERVISI.md` (git pull + `phpm spark
  migrate` + Ctrl+F5) · uji akhir (uji-hak, cek-ui, build:admin) · catat di memori.

## 9. Keputusan bawaan (tanya klien hanya bila mereka membawa format sendiri)
Skala 1–4 & ambang predikat di atas · supervisor tidak wajib punya akun (dipilih dari daftar guru; yang mengisi di aplikasi = akun
yang diberi hak) · instrumen bawaan seperti §3 · laporan seperti §6. Bila format klien datang: ubah instrumen di halaman Instrumen,
dan bila tata letak lembarnya beda, sesuaikan `SupervisiCetak` saja.
