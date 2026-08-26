# Rencana Fitur: Laboratorium & Inventaris (SIMLAB)

Dokumen kerja step-by-step. **Centang tiap fase selesai** agar pekerjaan bisa
dilanjutkan di sesi baru tanpa kehilangan konteks.

Dibuat: 2026-08-26. Basis: repo `muslimin` (CI4 4.7, MySQL, shared hosting Rumahweb).

---

## Keputusan user (2026-08-26)

1. **Cakupan lab:** FOKUS lab komputer/TKJ — detail komputer (CPU/RAM/OS/IP)
   jadi kelas satu; lab lain tetap bisa dibuat, komputer diprioritaskan.
2. **Jadwal lab:** MODUL TERPISAH (`jadwal_lab`), tidak menempel ke Penjadwalan
   KBM. "Jadwal praktik guru" = jadwal_lab yang difilter per guru.
3. **Peminjam & teknisi:** peminjam BOLEH teks bebas (guru/siswa/umum/pihak luar);
   **teknisi tabel sendiri** (`teknisi`), bisa staf non-guru, opsional tautkan ke
   `guru`.
4. **Platform:** WEB DULU bertahap (pola Jabatan/Siswa). API & Flutter menyusul
   setelah web stabil.

**Aturan kecil disepakati:**
- Nomor aset: auto-generate format `LAB01-KOM-001` (bisa ditimpa manual).
- TANPA upload foto dulu (hemat inode Rumahweb; tambah kemudian bila perlu).
- Claude TIDAK commit (user commit sendiri via GitHub Desktop).

---

## Peta 17 permintaan → modul

| Modul (tabel) | Mencakup |
|---|---|
| `lab` | Data laboratorium |
| `aset` (+ `aset_komputer`) | Inventaris · Nomor aset · Kondisi barang · Data komputer |
| `peminjaman` | Peminjaman barang · Pengembalian barang |
| `jadwal_lab` | Jadwal penggunaan lab · Jadwal praktik guru |
| `jurnal_lab` | Jurnal penggunaan lab |
| `kerusakan` | Kerusakan komputer |
| `perbaikan` | Perbaikan · Maintenance · Penggantian komponen |
| `sparepart` (+ `sparepart_mutasi`) | Stok sparepart |
| `teknisi` | Teknisi/Penanggung jawab |
| *(query + export, bukan tabel)* | Laporan laboratorium |

Dipakai ulang dari sistem yang ada (TIDAK diduplikasi): `guru`, `kelas`,
`mata_pelajaran`, `hari`, `jam_pelajaran`, `siswa`.

---

## ERD ringkas (11 tabel baru)

```
teknisi ──< lab ──< aset ──1:1── aset_komputer
   │                 │
   │                 ├──< peminjaman
   │                 ├──< kerusakan ──< perbaikan >── sparepart (via sparepart_mutasi)
   │                 └── (lokasi)
lab ──< jadwal_lab (>= hari, jam_pelajaran, guru, kelas, mata_pelajaran)
lab ──< jurnal_lab (>= guru, kelas, teknisi)
```

### Kolom inti per tabel
- **teknisi**: kode*, nama, peran(teknisi/kepala_lab/laboran/lainnya), no_hp,
  guru_id?(→guru SET NULL), keterangan. Soft delete.
- **lab**: kode*, nama, jenis(komputer/jaringan/multimedia/lainnya), ruang,
  kapasitas, teknisi_id?(→teknisi SET NULL), keterangan. Soft delete.
- **aset**: nomor_aset*, nama, kategori(komputer/laptop/printer/proyektor/
  jaringan/furnitur/lainnya), lab_id?(→lab SET NULL), merk, spesifikasi,
  tahun_pengadaan, sumber_dana, harga, kondisi(baik/rusak_ringan/rusak_berat),
  status(tersedia/dipinjam/perbaikan/dihapus), keterangan. Soft delete.
- **aset_komputer** (1:1): aset_id*(→aset CASCADE, unik), hostname, processor,
  ram, storage, gpu, os, mac_address, ip_address, monitor, keterangan.
- **peminjaman**: aset_id(→aset CASCADE), peminjam_nama, peminjam_tipe(guru/
  siswa/umum), peminjam_ref?(int polos, tanpa FK), tujuan, tanggal_pinjam,
  tanggal_kembali_rencana, tanggal_kembali_aktual?, kondisi_pinjam, kondisi_kembali?,
  status(dipinjam/dikembalikan/terlambat/hilang), petugas_id?(→teknisi SET NULL),
  keterangan. Soft delete. **Pengembalian = update baris ini.**
- **kerusakan**: aset_id(→aset CASCADE), tanggal_lapor, pelapor, deskripsi,
  tingkat(ringan/sedang/berat), status(dilaporkan/diproses/selesai/tak_teratasi),
  teknisi_id?(→teknisi SET NULL), keterangan. Soft delete.
- **perbaikan**: aset_id(→aset CASCADE), kerusakan_id?(→kerusakan SET NULL),
  jenis(perbaikan/maintenance/penggantian), tanggal, teknisi_id?(→teknisi SET NULL),
  tindakan, hasil(berhasil/sebagian/gagal/ganti_unit), biaya, status(proses/selesai),
  keterangan. Soft delete.
- **sparepart**: kode*, nama, kategori, satuan, stok, stok_minimum, harga,
  lokasi, keterangan. Soft delete.
- **sparepart_mutasi**: sparepart_id(→sparepart CASCADE), tanggal, tipe(masuk/
  keluar), jumlah, perbaikan_id?(→perbaikan SET NULL), keterangan, petugas.
- **jadwal_lab**: lab_id(→lab CASCADE), hari_id(→hari CASCADE), jam_id(→
  jam_pelajaran CASCADE), guru_id?(→guru SET NULL), kelas_id?(→kelas SET NULL),
  mapel_id?(→mata_pelajaran SET NULL), kegiatan, keterangan.
  UNIQUE(lab_id,hari_id,jam_id) = 1 lab 1 slot (anti bentrok pemakaian).
- **jurnal_lab**: lab_id(→lab CASCADE), tanggal, jam_mulai, jam_selesai,
  guru_id?(→guru SET NULL), kelas_id?(→kelas SET NULL), kegiatan, jumlah_hadir,
  kondisi_setelah(baik/ada_kendala), kendala, teknisi_id?(→teknisi SET NULL),
  keterangan. Soft delete.

Semua InnoDB + utf8mb4, mengikuti gaya migrasi yang sudah ada
(`2026-07-18-000001_CreateJabatanSiswa`).

---

## Fase kerja (web, bertahap)

- [x] **P0 — Fondasi DB & Model** ✅ SELESAI 2026-08-26
      Migrasi `2026-08-26-000001_CreateLabInventaris` (11 tabel, urutan FK aman) +
      11 model (pola BaseMaster: allowedFields, softDeletes, validationRules
      dengan `id => permit_empty|is_natural` untuk is_unique saat edit).
      **Teruji:** `php spark migrate` sukses & reversible; 11 tabel + semua FK
      terbentuk (jadwal_lab 6 FK, jurnal_lab 4, dst); `php -l` bersih semua model;
      8 SQL join `withRelations` divalidasi ke DB (LIMIT 0) — tak ada kolom salah.
      Model: Teknisi, Lab, Aset, AsetKomputer(1:1, tanpa soft delete),
      Sparepart, SparepartMutasi(append-only), Peminjaman, Kerusakan, Perbaikan,
      JadwalLab(hard delete + slotTerpakai() anti-bentrok), JurnalLab.
- [x] **P1 — Master Lab + Teknisi** ✅ SELESAI 2026-08-26
      Controller `Admin\Master\Lab` & `Teknisi` (extends BaseMaster) — index/store/
      update/collect/export/template/import + cleanupRelations. View pakai komponen
      generik `masterList` (bukan js per-halaman). Model dapat `options()`
      (opt_lab/opt_teknisi). Rute di grup `master`. Menu sidebar **grup baru
      "LABORATORIUM"** (Lab + Teknisi). Help card lab & teknisi. Modul lab/teknisi/
      aset didaftarkan ke ripple+singles `cache_helper`.
      **Teruji e2e via HTTP** (localhost/muslimin/public, login admin/admin123,
      CSRF cookie): GET 200 kedua halaman + sidebar tampil; POST buat teknisi →
      buat lab (PJ terjoin benar); export xlsx valid (PK); hapus teknisi →
      cleanupRelations melepas `lab.teknisi_id` jadi NULL; `php -l` bersih;
      Tailwind di-rebuild; tak ada error di render. Data uji + audit dibersihkan.
- [x] **P2 — Master Aset (+ detail Komputer) + Sparepart** ✅ SELESAI 2026-08-26
      `Admin\Master\Aset` & `Sparepart` (extends BaseMaster). Aset: nomor_aset
      AUTO `KODELAB-KAT-###` bila kosong (generateNomor pakai max suffix), filter
      lab/kategori/kondisi/status, import resolve kode lab. **Detail Komputer =
      sub-halaman tersendiri** (`aset/komputer/{id}` GET+POST → view
      `aset_komputer.php`, upsert `aset_komputer` 1:1) — tautan hanya untuk
      kategori komputer/laptop. Sparepart: stok + stok_minimum (baris merah bila
      menipis). Rute (aset komputer diletakkan SEBELUM `aset/(:num)`), sidebar
      LABORATORIUM +Aset +Sparepart, cache sparepart.
      **Teruji e2e HTTP:** GET 200; auto-nomor terverifikasi `LABX-KOM-001`;
      detail komputer GET/POST tersimpan; sparepart stok 1≤min 5 → MENIPIS; export
      xlsx valid; hapus aset → cleanupRelations hapus aset_komputer. `php -l`
      bersih, Tailwind rebuild, tak ada error render. Data uji dibersihkan.
- [x] **P3 — Peminjaman ↔ Pengembalian** ✅ SELESAI 2026-08-26
      Controller `Admin\Peminjaman` (BUKAN BaseMaster — workflow): index (filter
      status/cari + ringkasan sedang/terlambat), store (pinjam → set aset
      'dipinjam', transaksi), kembalikan (isi tgl/kondisi → aset 'tersedia' +
      kondisi ikut; 'hilang' → aset 'dihapus'), delete (bebaskan aset bila masih
      dipinjam). View `admin/peminjaman/index.php` (Alpine INLINE 2 modal: Pinjam
      & Kembalikan, badge Terlambat dihitung rencana<hari-ini). `AsetModel::
      optionsTersedia()`. Rute `admin/peminjaman/*` (di grup auth, luar master).
      Sidebar grup baru **"OPERASIONAL LAB"**. Tiap mutasi `master_data_changed('aset')`.
      **Teruji e2e HTTP:** pinjam→dipinjam/dipinjam; kembali→dikembalikan+aset
      tersedia/rusak_ringan; hilang→aset dihapus; hapus saat dipinjam→aset bebas.
      `php -l` bersih, Tailwind rebuild, tak ada error. Data uji dibersihkan.
- [x] **P4 — Kerusakan → Perbaikan/Maintenance → mutasi Sparepart** ✅ SELESAI 2026-08-26
      `Admin\Kerusakan` (lapor → aset 'perbaikan'; status modal diproses/selesai/
      tak_teratasi → bebaskan aset bila tak ada kerusakan lain terbuka;
      terbukaCount) & `Admin\Perbaikan` (catat perbaikan/maintenance/penggantian;
      **komponen opsional**: sparepart+jumlah → validasi stok, mutasi keluar +
      kurangi stok dalam transaksi; hapus perbaikan → PULIHKAN stok; bila selesai
      & dikaitkan kerusakan → kerusakan selesai + aset tersedia/baik). Model dapat
      `AsetModel::options()`, `SparepartModel::options()`, `KerusakanModel::
      optionsTerbuka()/terbukaCount()`. View index masing-masing (Alpine inline).
      Rute + sidebar OPERASIONAL LAB (+Kerusakan +Perbaikan). Tiap mutasi
      `master_data_changed('aset','sparepart')`.
      **Teruji e2e HTTP:** lapor→aset perbaikan; perbaikan+ganti 3 komponen→stok
      10→7 + mutasi keluar/3 + kerusakan selesai + aset tersedia/baik; hapus
      perbaikan→stok pulih 10; stok tak cukup (99)→ditolak, stok tetap; ubah
      status kerusakan→selesai + aset bebas. `php -l` bersih, Tailwind rebuild,
      tak ada error. Data uji dibersihkan.
- [x] **P5 — Jadwal Lab + Jurnal Lab** ✅ SELESAI 2026-08-26
      `Admin\JadwalLab` (mode lab: pilih lab → kelola slot, anti-bentrok
      slotTerpakai + guru tak boleh 2 lab 1 slot; mode guru: lihat jadwal praktik
      guru baca-saja; tabel diurut hari.urutan+jam; opsi hari aktif & jam
      non-istirahat dibangun in-controller) & `Admin\JurnalLab` (CRUD log realisasi
      + filter lab/tanggal/cari, kondisi_setelah baik/ada_kendala + kendala).
      View index masing-masing (Alpine inline; jurnal punya field kendala tampil
      saat ada_kendala). Rute admin/jadwal-lab/* & admin/jurnal-lab/*. Sidebar
      grup baru **"PENJADWALAN LAB"** (Jadwal Lab + Jurnal Lab).
      **Teruji e2e HTTP:** GET 200; tambah slot; slot sama DITOLAK (anti-bentrok);
      jurnal tersimpan (hadir/kondisi/kendala). `php -l` bersih, Tailwind rebuild,
      tak ada error. Data uji dibersihkan.
- [x] **P6 — Laporan Laboratorium** ✅ SELESAI 2026-08-26 (MODUL WEB SIMLAB 100%)
      `Admin\LaporanLab` (index/pdf/excel) — agregasi via `hitung($dari,$sampai)`
      dengan RAW db builder + deleted_at DIKUALIFIKASI (aset.deleted_at dll, aman
      saat JOIN — meniru SiswaModel::statistik). Rekap: aset (total/status/kondisi/
      per-lab), peminjaman (periode + snapshot sedang/terlambat), kerusakan &
      perbaikan (periode + total biaya), sparepart (menipis), jurnal (sesi per
      lab). Filter rentang tanggal (default bulan berjalan). View web (stat tiles +
      bar kondisi + tabel), **PDF** `pdf/laporan_lab.php` (dompdf portrait + kop_pdf),
      **Excel** (ringkasan + kop_excel_prepend). Rute admin/laporan-lab/{,/pdf,/excel}.
      Sidebar masuk grup **LAPORAN** yang sudah ada.
      **Teruji e2e HTTP (dengan data contoh):** index 200 tanpa error (join per-lab
      jalan, sparepart menipis tampil), PDF %PDF, Excel PK. `php -l` bersih, Tailwind
      rebuild. Data uji dibersihkan.
- [ ] **(nanti) API + Flutter** — setelah web stabil.

---

## Catatan teknis (agar tak error)

- Nama tabel existing untuk FK: `guru`, `kelas`, `hari`, `jam_pelajaran`,
  `mata_pelajaran`, `siswa` (bukan `jam`/`mapel`).
- Pola is_unique saat EDIT (CI4 4.7): model WAJIB punya `'id' =>
  'permit_empty|is_natural'` DAN controller sertakan `$data['id']=$id` saat
  update — lihat [[project-penjadwalan-kbm]] "BUG is_unique".
- Restore baris soft-deleted lewat Model->update TIDAK mengosongkan deleted_at
  (bukan allowedField) → pakai `protect(false)->update(id, data+['deleted_at'=>null])`.
- Tiap mutasi panggil `master_data_changed('<modul>')`; daftarkan modul lab ke
  ripple/singles di `app/Helpers/cache_helper.php` saat controllernya dibuat.
- Uji HTTP lokal: `http://localhost/muslimin/public`; login field POST `login`
  (bukan username), admin/admin123. DB: `/c/xampp/mysql/bin/mysql.exe -h 127.0.0.1
  -u root muslimin`. Rebuild Tailwind tiap tambah kelas:
  `./tailwindcss.exe -i resources/css/app.css -o public/assets/css/app.css --minify`.
- Deploy: `cd ~/kangmuslim && git pull origin main && phpm spark migrate`
  (`phpm` = PHP 8.3). Claude TIDAK commit.
