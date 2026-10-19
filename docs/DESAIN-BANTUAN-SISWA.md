# DESAIN — Bantuan Siswa (menu KURIKULUM → Bantuan Siswa: Beasiswa Sekolah & Beasiswa Pemerintah)

Ditulis 2026-10-19 malam. **Status: RANCANGAN SIAP KODING — belum ada kode.** Dikerjakan SESUDAH Supervisi (`docs/DESAIN-SUPERVISI.md`).
User: "pakai saran lu" (klien belum memberi contoh format). Keputusan user 2026-10-19: nama menu "Bantuan Siswa"; jenis seperti usulan
(Sekolah: SKTM / yatim / prestasi / yayasan; Pemerintah: PIP / KIP / provinsi); **Beasiswa Sekolah menjadi sumber bebas biaya PKL**.
Per fase B1–B5, satu fase lalu BERHENTI & lapor; JANGAN commit/push sebelum diminta. Uji: `php spark dev:uji-bantuan`.

## 1. Konsep
Data **penerima bantuan per siswa per tahun ajaran**, dua kategori:
- **Beasiswa Sekolah** (dari sekolah/yayasan): bentuk **pembebasan/potongan biaya** (SPP dll) atau uang. Program bawaan: SKTM (tidak
  mampu), Yatim/Piatu, Prestasi, Kebijakan Yayasan, Lainnya. Program yang ditandai **bebas SPP** otomatis membebaskan SPP pada biaya
  PKL (menggantikan peran tabel `pkl_beasiswa`).
- **Beasiswa Pemerintah**: PIP (Program Indonesia Pintar), KIP (Kartu Indonesia Pintar), Bantuan Provinsi, Lainnya — dengan nomor KIP,
  bank & nomor rekening, dan **pencairan per tahap** (tanggal, nominal, status).
Status penerima: **Diusulkan → Ditetapkan → (Dicabut / Ditolak)**; berlaku mulai–berakhir (tanggal; kosong = tanpa batas).

## 2. Menu (sudah ada di `MenuAdmin::definisi()`, `'segera' => true`)
`beasiswa_sekolah` → `admin/beasiswa/sekolah` · `beasiswa_pemerintah` → `admin/beasiswa/pemerintah` (grup BANTUAN SISWA; JANGAN ganti
judul grup). Tambah awalan agar sub-alamat ikut (`'awalan' => ['admin/beasiswa/sekolah']` dst.) + `admin/beasiswa/program` (atur program,
jenis ADMIN = khusus Admin, pola `keterlambatan_pesan`). Hapus `'segera'` saat halamannya jadi. `'api' => ['bantuan']` di B4.

## 3. Tabel (migrasi B1, idempoten)
- `bantuan_program`: id, kategori ENUM('sekolah','pemerintah'), kode VARCHAR(40) unik, nama VARCHAR(100), keterangan VARCHAR(255) null,
  bebas_spp TINYINT(1) def 0 (hanya berarti untuk kategori sekolah), nominal_bawaan INT null, aktif TINYINT(1) def 1, urut INT,
  timestamps. Isi bawaan: sekolah → `sktm` SKTM, `yatim` Yatim/Piatu, `prestasi` Prestasi, `yayasan` Kebijakan Yayasan,
  `lainnya` Lainnya (semua bebas_spp = 1, Admin bisa mencabut per program); pemerintah → `pip` PIP, `kip` KIP (Kartu Indonesia Pintar),
  `provinsi` Bantuan Provinsi, `pemerintah_lain` Lainnya.
- `bantuan_penerima`: id, program_id FK RESTRICT, siswa_id FK SET NULL + salinan nama_siswa, nis, nisn, nama_kelas; tahun_ajaran
  VARCHAR(20); status ENUM('diusulkan','ditetapkan','ditolak','dicabut') def 'ditetapkan'; mulai DATE null, berakhir DATE null;
  bentuk ENUM('pembebasan','potongan','uang','lainnya') null; nominal INT null (per tahun / total); nomor_sk VARCHAR(100) null;
  nomor_kip VARCHAR(30) null, nama_bank VARCHAR(50) null, nomor_rekening VARCHAR(30) null, nama_rekening VARCHAR(150) null (khusus
  pemerintah); keterangan VARCHAR(255) null; alasan_cabut VARCHAR(255) null; dicatat_oleh_id, dicatat_oleh, timestamps.
  Unik (program_id, siswa_id, tahun_ajaran).
- `bantuan_pencairan`: id, penerima_id FK CASCADE, tahap VARCHAR(30) ("Tahap 1"), tanggal DATE null, nominal INT, status
  ENUM('belum','cair','gagal') def 'belum', keterangan VARCHAR(255) null, dicatat_oleh, timestamps.

### Sambungan ke biaya PKL (BAGIAN PALING BERISIKO — baca dulu `app/Libraries/PklBiaya.php`, uji `dev:uji-pkl-biaya` 130 cek)
Sekarang: `pkl_beasiswa` (siswa_id UNIK, sumber `sktm|yayasan|lainnya` = `PklBiaya::SUMBER_BEASISWA`, berakhir_at = 30 Juni tahun
masuk + 3) dibuat dari dialog biaya saat unduh surat PKL, membebaskan **SPP saja**; ada aksi "cabut beasiswa".
Rencana B1:
1. Migrasi menyalin tiap baris `pkl_beasiswa` → `bantuan_penerima` (program sekolah dengan kode sama, status ditetapkan, berakhir =
   berakhir_at, keterangan, tahun ajaran = sekarang). Tabel `pkl_beasiswa` TIDAK dihapus (cadangan rollback).
2. `Libraries\BantuanSiswa::bebasSpp(array $siswaIds, string $tanggal): array<int, array{program, label, berakhir}>` = penerima
   ditetapkan, program sekolah `bebas_spp = 1`, berlaku pada tanggal itu. `PklBiaya` membaca DARI SINI (ganti pembacaan `pkl_beasiswa`);
   pencatatan beasiswa dari dialog biaya PKL menulis ke `bantuan_penerima`; "cabut" = status dicabut + alasan.
3. Pilihan sumber di dialog PKL = daftar program sekolah aktif (bukan konstanta). API PKL (`pkl/biaya` → `sumber_beasiswa`) ikut
   menyesuaikan dengan bentuk yang SAMA (kunci → label) agar aplikasi Android PKL tidak rusak.
4. Wajib hijau: `dev:uji-pkl-biaya`, `dev:uji-pkl`, `dev:uji-hak` sebelum lanjut.

## 4. Logika (satu sumber web + API)
`App\Libraries\BantuanSiswa`: program (daftar, simpan, aktif/nonaktif — khusus Admin), penerima (cari siswa ≥ 2 huruf pola
Keterlambatan, tambah, ubah, tetapkan, tolak, cabut + alasan, hapus hanya bila diusulkan), pencairan (tambah/ubah/hapus per tahap),
rekap(ta, kategori, saringan), bebasSpp() untuk PKL, impor Excel. Validasi: nomor rekening angka 8–20, nomor KIP 6–30 huruf/angka,
nominal ≥ 0, berakhir ≥ mulai, siswa aktif saat ditambah. Audit Log semua ubahan; hasil gagal membawa `kode` 404|409|422.
`BantuanCetak` (Excel). Integrasi tampilan: lencana "Penerima PIP" / "Beasiswa SKTM" di Detail Data Siswa (hanya baca).

## 5. Halaman web (kit F5, Alpine CSP, JS terpisah)
- **Beasiswa Sekolah** (`admin/beasiswa/sekolah`): kartu ringkas (jumlah penerima per program, bebas SPP); saring TA/program/kelas/
  status; tabel (nama, NIS, kelas, program, bentuk/nominal, berlaku, status); tambah (cari siswa → program → bentuk, nominal, No SK,
  mulai–berakhir, keterangan); ubah, cabut (alasan), hapus; **Impor Excel** (templat unduh) & **Unduh Excel**; tombol "Atur program"
  (Admin).
- **Beasiswa Pemerintah** (`admin/beasiswa/pemerintah`): sama + kolom No KIP, bank & rekening; **Pencairan** per penerima (dialog
  daftar tahap + tambah); ringkasan total cair per tahap; Impor Excel daftar penerima PIP (cocokkan **NISN**, cadangan NIS; baris yang
  tidak cocok dilaporkan, tidak disimpan).
- **Atur program** (`admin/beasiswa/program`, khusus Admin): nama, aktif, bebas SPP (sekolah), nominal bawaan, urutan.

## 6. Keluaran (laporan)
1. **Excel "DAFTAR PENERIMA BEASISWA SEKOLAH TA …"** — NO | NIS | NISN | NAMA SISWA | KELAS | PROGRAM | BENTUK | NOMINAL | NO. SK |
   BERLAKU | STATUS | KET; kaki TTD Kepala Sekolah (`settings.headmaster_name/nip`) — bisa dipakai sebagai lampiran SK.
2. **Excel "DAFTAR PENERIMA BEASISWA PEMERINTAH TA …"** per program — NO | NISN | NIS | NAMA | KELAS | NO. KIP | BANK | NO. REKENING |
   NAMA REKENING | TAHAP CAIR (kolom per tahap: tanggal & nominal) | TOTAL CAIR | STATUS.
3. **Excel "REKAP BANTUAN SISWA"** — lembar "Per program" (jumlah penerima, total nominal/cair) & "Per kelas" (jumlah penerima per
   program per kelas).
4. **Templat impor** (.xlsx) untuk sekolah & pemerintah (kolom wajib ditandai), hasil impor: diterima / dilewati + alasan per baris.

## 7. API Android (B4) — `api/v1/bantuan…`, kontrak `docs/API-BANTUAN-SISWA.md`
Terutama untuk melihat & mencari (staf/Admin di HP): `GET bantuan/meta` (program, TA, hak) · `GET bantuan?kategori=&ta=&program=&
kelas_id=&status=&q=` · `GET bantuan/{id}` (+ pencairan) · `POST bantuan`, `POST bantuan/{id}/ubah`, `POST bantuan/{id}/cabut`,
`DELETE bantuan/{id}` · `POST bantuan/{id}/pencairan`, `POST bantuan/pencairan/{id}/ubah`, `DELETE bantuan/pencairan/{id}` ·
`GET bantuan/rekap` · unduhan Excel (§6). Impor & atur program = web saja. Hak = `HakAkses::boleh` pada alamat web padanannya.

## 8. Fase kerja
- **B1** — migrasi (3 tabel + program bawaan + salin `pkl_beasiswa`) · `Libraries\BantuanSiswa` (program, penerima, bebasSpp) ·
  **sambungkan PklBiaya** (§3) · halaman **Beasiswa Sekolah** + **Atur program** · uji `dev:uji-bantuan` + regresi PKL.
- **B2** — Beasiswa Pemerintah: kolom KIP/rekening, **pencairan per tahap** · halaman + dialog pencairan · lencana di Detail Data Siswa.
- **B3** — `BantuanCetak` (3 Excel §6) + impor Excel (templat, NISN) · hapus tanda `segera` · uji baca ulang .xlsx & impor (baris salah).
- **B4** — `Api\Bantuan` + rute + `'api'` + `docs/API-BANTUAN-SISWA.md` + uji API.
- **B5** — kontrak Flutter `C:\flutter-muslimin\BLUEPRINT-BANTUAN-SISWA.md` + fixture HTTP nyata (data fiktif, folder sendiri) ·
  `docs/PANDUAN-DEPLOY-BANTUAN-SISWA.md` (git pull + `phpm spark migrate` + Ctrl+F5; cadangkan DB dulu karena data PKL disalin) ·
  uji akhir (uji-hak, uji-pkl-biaya, cek-ui, build:admin) · catat di memori.

## 9. Keputusan bawaan (ubah bila klien memberi format)
Program bawaan seperti §3, semua program sekolah bebas SPP · satu siswa boleh menerima beberapa program (asal beda program) · status
bawaan saat ditambah = Ditetapkan · nominal opsional · laporan seperti §6.
