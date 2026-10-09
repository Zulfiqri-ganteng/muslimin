# Rancangan: Honor Ujian (ASTS 1 / ASAS / ASTS 2 / ASAT) + perapian tampilan Ujian

Dibuat 2026-10-09. **Status akhir (2026-10-09 malam): FASE 1–5 SEMUA SELESAI & TERUJI LOKAL — belum di-commit/push/migrate di hosting.** Rincian hasil, cara pakai, dan langkah deploy ada di bagian 8–9 di bawah.
- **Fase 1 SELESAI & TERUJI (2026-10-09):** migrasi `2026-10-13-000001_HonorFondasi` (guru.bukan_pengajar, honor_komponen [9 bawaan], honor_panitia_jabatan, honor_pengaturan), `Libraries\HonorPengaturan`, `Admin\HonorPengaturan` + view `admin/honor/pengaturan.php`, menu "Pengaturan Honor" (grup UJIAN, khusus Admin), Master Guru + API Guru (kolom bukan_pengajar), dashboard/Total Guru/Kurang Jam/beranda publik tidak menghitung staf. Uji: `php spark dev:uji-honor` (148, di-rollback) + skrip HTTP (79).
- **Keputusan Fase 1:** 6 nama staf Excel TIDAK dimasukkan lewat migrasi (repo publik) — ditambahkan Admin lewat tombol impor Fase 3 ("tambah ke Master Guru sebagai bukan pengajar"). Guru/TU yang SUDAH ada di Master Guru tidak ditandai otomatis; Admin mencentang "Bukan pengajar" sendiri (angka dashboard "Guru Kurang Jam 59" turun setelah itu).
- (DIGANTI 2026-10-09 sore) Rapot berlaku di SEMUA jenis ujian karena rekap ASTS sekolah punya kolom Rapot (berisi 0); migrasi 000006.
Sumber: `formatdatasekolah/HONOR ASTS.xlsx` (folder ini di-.gitignore: berisi data pribadi & gaji; repo PUBLIK — jangan masukkan data honor ke repo/migrasi).

## 1. Latar
Honor ujian dihitung manual di Excel (2 lembar: `REKAP HONOR` 59 orang, `SLIP` per orang). Temuan: baris JUMLAH rekap hanya menjumlah D7:D44 padahal data sampai baris 65
→ tertulis Rp 35.441.000, seharusnya **Rp 44.472.000** (kurang Rp 9.031.000); slip rusak (#REF!, kolom bergeser, label semester & tarif keliru); tarif tertulis keras di rumus; nama Kepsek tanpa titik;
6 nama tak ada di Master Guru (3 guru: Ambarsari Dwi Sulistya Wati, Bella Aprillia, Aida Fitriah; 3 staf TU: Afriyanti, Aldy Galuh Permana, Abde Herlambang).
Angka acuan (rekap Excel): komponen Tunjangan Panitia (nominal tetap: Kepsek 2.000.000, Kepala TU 1.200.000, Waka Kurikulum 1.500.000, 8 orang × 500.000), Pembuatan Soal Rp 20.000/set,
Transport Rp 25.000/hari (struktural 10 hari, Pembina OSIS 8, Perpus 5, sebagian guru 5), Pengawas Rp 6.500/sesi, Koreksi Rp 1.500/lembar, Rapot Rp 20.000 (0 di ASTS); tanda tangan: Ketua panitia (Elvira Safitri, S.Pd),
Bendahara (Maya Fadhillah, S.Pd = Kepala TU), Kepala SMK (Napis Kuturupi, S.T.). Cetak: landscape, Folio (kertas khusus 10000), skala 70 %, baris judul 1–6 diulang, area A1:P86.

## 2. Keputusan user (2026-10-09)
1. **Komponen fleksibel**: Tunjangan Struktural & Tunjangan Wali Kelas tidak ada di rekap sekarang → disediakan sebagai komponen yang bisa diaktifkan Admin (bawaan MATI), begitu pula Lembur (slip: Rp 100.000); komponen baru bisa ditambah.
2. **Pembuatan soal**: penugasan **pembuat soal per jadwal ujian**; jumlah set dihitung otomatis, tetap bisa diubah manual.
3. **Transport**: jumlah hari **diketik manual**; bawaan = jumlah hari ujian untuk jabatan struktural.
4. **Tunjangan panitia**: tabel jabatan → nominal (bisa diubah), dan boleh diubah per orang.
5. **Staf non-guru** masuk **Master Guru** dengan tanda "bukan pengajar" (Master Guru sudah memuat sebagian staf TU). WAJIB diperiksa dampaknya ke daftar/hitungan guru lain (dashboard "Total Guru", "Guru Kurang Jam", beban, jadwal).
6. **Pengawas**: jumlah sesi **diketik manual** (menjaga kasus guru tidak masuk dll.); "saran dari jadwal" hanya petunjuk.
7. **Hak akses: hanya Admin** (data gaji sensitif; Operator/Hubin ditolak di rute dan menu).
8. **Keluaran: Excel + PDF** (Rekap dan Slip), dicetak dari tab Honor.
9. Tampilan halaman Ujian ikut dirapikan; pengujian ketat tiap fase.

## 3. Data otomatis vs manual (hasil uji pada data produksi 2026-10-08)
| Komponen | Sumber |
|---|---|
| Koreksi | otomatis ≈ Σ siswa aktif kelas yang diampu (pengampu × kelas). Uji 44 guru: total Excel 18.477 vs sistem 17.473 (−5,4 %); 32 guru ≤ 5 %, 39 guru ≤ 10 % → hitungan awal, wajib bisa diubah |
| Rapot | otomatis = siswa kelas yang diwalikan (`kelas.wali_kelas_id`; 42 kelas terisi) × tarif — hanya jenis ujian yang memakainya |
| Pembuatan soal | otomatis dari penugasan pembuat soal per jadwal (baru), bisa diubah |
| Pengawas | manual (petunjuk dari `ujian_pengawas`) |
| Transport, Tunjangan panitia | manual (bawaan dari jabatan) |
| Nama/jabatan | Master Guru + label jabatan yang bisa diubah (singkatan seperti "Kaprog. TKJ") |

## 4. Rancangan data (draf)
- `honor_komponen`: kode, nama, tipe (`tetap` nominal per orang | `satuan` jumlah × tarif), tarif, satuan, sumber (`manual|koreksi|rapot|soal`), aktif, urut. Awal: Tunjangan Panitia, Pembuatan Soal, Transport, Pengawas, Koreksi, Rapot (aktif);
  Lembur, Tunj. Struktural, Tunj. Walas (mati).
- `honor_panitia_jabatan`: jabatan → nominal bawaan.
- `honor_dokumen` (1 per `ujian_periode`): status (`draf|final|dikunci`), judul, tempat/tanggal, nama Ketua/Bendahara/Kepala Sekolah, **snapshot komponen & tarif (JSON)** supaya periode lama tak berubah bila tarif diubah, dikunci_at/oleh.
- `honor_baris` (dokumen, guru, label jabatan, urut) + `honor_nilai` (baris, komponen, jumlah/nominal, `otomatis` bool).
- `ujian_pembuat_soal` (jadwal_id, guru_id). `guru.bukan_pengajar` (tinyint).
- Hak: rute `admin/ujian/*/honor*` hanya Admin (deny-by-default sudah memenuhi; tambah uji). Setiap perubahan → Audit Log.
- Total dihitung SERVER (satu fungsi) dan dipakai layar, Excel, PDF — angka tak mungkin berbeda.

## 5. Fase kerja (berhenti & lapor tiap fase)
| Fase | Isi | Hasil yang terlihat |
|---|---|---|
| **U — Perapian tampilan Ujian** | tab bar (hilangkan artefak gulir), tabel Jadwal → kartu di HP, label pada tombol ikon, indikator kelengkapan periode (jadwal → pengawas → pelaksanaan → susulan → honor), tab "Honor" | halaman Ujian nyaman di HP & laptop |
| **1 — Fondasi Honor** | migrasi; halaman Pengaturan Honor (komponen, tarif, tunjangan per jabatan); tanda "bukan pengajar" + tambah 6 nama; pemeriksaan dampak ke daftar guru | Admin mengatur tarif & komponen |
| **2 — Honor per periode + grid isian** | buat honor untuk periode, pilih penerima, grid isian seperti Excel (total langsung), validasi | menggantikan ketik di Excel |
| **3 — Hitung otomatis, pembuat soal, impor Excel lama** | tombol hitung koreksi/rapot/soal, penugasan pembuat soal di tab Jadwal, impor `HONOR ASTS.xlsx` (laporan nama tak cocok) | tak perlu ketik ulang data lama |
| **4 — Keluaran: Excel + PDF + Slip** | Excel format sekolah (JUMLAH selalu utuh, tarif di sel), lembar SLIP semua orang, PDF Rekap & Slip (semua/per orang), tombol Cetak | cetak langsung dari sistem |
| **5 — Kunci, Audit, anomali** | draf → final → dikunci, snapshot tarif, Audit Log, peringatan (total 0, nama ganda, koreksi menyimpang jauh dari hitungan sistem, bandingkan periode) | aman dari salah ubah setelah dibayar |

Urutan usulan: **1 → U (bagian kecil: tab bar & kerangka) → 2 → 4 → 3 → 5** (setelah 2+4 sudah bisa input & cetak; otomatisasi menyusul).

## 6. Pengujian (tiap fase)
- `php spark dev:uji-honor` (hitung, pembulatan, snapshot tarif, otomatis vs manual, kunci).
- **Uji penerimaan pakai Excel asli**: impor `HONOR ASTS.xlsx` → total tiap orang harus sama dengan Excel dan **total keseluruhan Rp 44.472.000** (bukan 35.441.000); slip sesuai.
- HTTP semua peran (Admin boleh; Operator/Hubin ditolak, termasuk menu), peramban (grid, cetak), regresi PKL/Ujian lama, foto layar laptop & HP.
- Excel dibuka ulang (PhpSpreadsheet) dan PDF diperiksa (jumlah halaman, orientasi, ulang judul).

## 7. Risiko & catatan
- Tanda "bukan pengajar" memengaruhi daftar/hitungan guru lain — periksa semua kueri guru sebelum menambah 6 nama.
- Tarif & jumlah di Excel lama dipakai sebagai acuan; angka koreksi otomatis ±5 % dari Excel → selalu bisa diubah dan ditandai "otomatis/diubah".
- Impor Excel membaca berkas yang diunggah Admin (tidak disimpan di repo/hosting setelah diproses).
- Android: tidak diperlukan (hanya Admin); bila nanti diminta, API menyusul.

## 8. Hasil (2026-10-09) — apa yang dibangun
| Fase | Hasil |
|---|---|
| 1 Fondasi | `Pengaturan Honor` (menu UJIAN, khusus Admin): komponen & tarif (bisa ditambah/dimatikan), tunjangan panitia per jabatan, nama Ketua/Bendahara; kolom `guru.bukan_pengajar` (staf tak dihitung di Total Guru/Guru Kurang Jam/beban) — migrasi `…000001` |
| 2 Honor per periode | tab **Honor** di tiap halaman Ujian: buat honor (snapshot tarif), tambah penerima dari Master Guru, grid isian simpan-otomatis (total dihitung server), data surat & tanda tangan, "Perbarui tarif dari pengaturan", salin penerima dari honor lain — migrasi `…000002` |
| 3 Otomatis & impor | **Hitung otomatis** (Koreksi = Σ siswa kelas yang diampu; Rapot = siswa kelas yang diwalikan; Pembuatan Soal = penugasan di halaman **Pembuat soal**); Pengawas tetap manual (petunjuk dari jadwal); **Impor Excel lama** (unggah → pratinjau pencocokan nama → terapkan; nama yang belum ada bisa ditambahkan ke Master Guru sebagai guru/staf); sel diketik/impor bertanda `manual` dan tak ditimpa — migrasi `…000003`, `…000004` |
| 4 Keluaran | menu **Cetak / Unduh**: Rekap PDF (F4 landscape), Excel (lembar REKAP rumus hidup + lembar SLIP semua orang), Slip PDF (semua atau satu orang; ikon printer per baris); tiap unduhan dicatat di Audit Log |
| 5 Kunci & periksa | status Draf → Final → Dikunci (buka kunci wajib beralasan, tercatat); sidik jari HMAC saat dikunci (banner merah bila isi berubah lewat database); kartu **Pemeriksaan honor** (anomali); penanda "DRAF" di cetakan bila belum final — migrasi `…000005` |
| U Tampilan Ujian | baris tab bersih (tanpa artefak gulir) + lencana kelengkapan per tab (Periode/Jadwal/Ketidakhadiran/Susulan/Honor); Jadwal Ujian jadi kartu berlabel di HP; tombol ikon punya `aria-label` |

Angka acuan (uji penerimaan dengan `HONOR ASTS.xlsx` asli): impor 59 penerima → **total Rp 44.472.000**, total tiap orang sama dengan Excel (59/59); Excel unduhan memuat rumus JUMLAH utuh (baris 7–65) dan 59 slip yang benar. (Excel lama salah: JUMLAH hanya menjumlah sebagian baris = Rp 35.441.000, slip rusak.)

## 9. Deploy & cara pakai
**Deploy (hosting):** `git pull` lalu `php spark migrate` (honor `2026-10-13-000001`…`000005`; migrasi lama yang tertunda ikut jalan). Tidak ada berkas data honor di repo — Excel diunggah lewat tab Honor.
**Langkah awal Admin:** (1) menu **Pengaturan Honor** → cek tarif, isi **Tunjangan panitia per jabatan** (kosong secara bawaan) dan nama Ketua/Bendahara; (2) Master Guru → centang **Bukan pengajar** pada staf TU/operator; (3) Ujian → pilih jenis (ASTS 1, ASAS, …) → tab **Honor** → **Buat honor** (atau **Impor dari Excel lama**); (4) isi/koreksi angka, **Tandai Final**, cetak, dan **Kunci** setelah dibayar.
**Catatan:** kolom Rapot ada di semua jenis ujian (di ASTS nilainya 0, seperti rekap sekolah; Hitung otomatis tidak mencentang Rapot untuk ASTS); transport bawaan pejabat struktural = jumlah hari ujian (Senin–Sabtu dari tanggal periode); jumlah koreksi otomatis ±5 % dari Excel lama — selalu bisa diubah. Hanya Admin yang bisa melihat/mengubah honor (Operator & Waka Hubin ditolak di rute dan menu).
**Pengujian:** `php spark dev:uji-honor` (471 pemeriksaan, di-rollback), regresi `dev:uji-pkl` (322) dan `dev:uji-pkl-biaya` (124); skrip HTTP/peramban ada di folder kerja sesi (tidak di repo).

## 10. Kesamaan Excel dengan rekap sekolah (2026-10-09 sore)
Klien meminta Excel keluaran sistem **100% sama** dengan `HONOR ASTS.xlsx`. Dikerjakan dan DIBANDINGKAN OTOMATIS sel demi sel (`php spark dev:uji-honor-excel`, 25 pemeriksaan, butuh berkas asli di `formatdatasekolah/`, di-rollback): judul 3 baris, header bernomor + gabungan sel, JABATAN & jumlah Koreksi berwarna kuning, format akuntansi "Rp", nama Times New Roman, tinggi baris 30 (judul 15), garis ganda di bawah header, lebar kolom A–P, blok tanda tangan (Ketua kiri, Bendahara kanan, Kepala Sekolah bawah tengah) dengan posisi relatif yang sama, zoom 70 %, margin & rata tengah, landscape. 59 baris × 12 kolom angka, nama, jabatan, dan urutan sama dengan aslinya (Napis, Maya, Muslimin, … dst) setelah diimpor.
**Selisih yang disengaja:** (1) baris JUMLAH tepat di bawah baris terakhir dan menjumlah SEMUA baris (Excel asli: Rp 35.441.000 keliru; benar Rp 44.472.000); (2) tanggal surat mengikuti isian honor; (3) lembar SLIP berisi satu slip per orang (aslinya satu slip terpilih & rusak); (4) kertas Folio + nomor halaman (aslinya kertas khusus); (5) tanda "DRAF" di footer bila belum final.
**Urutan baris:** bawaan menurut hierarki jabatan (Kepala Sekolah, Waka Kur/Kes/Humas/Sarpras, Kaprog, Operator, guru, piket, TU); urutan persis sekolah diatur sekali dengan **nomor urut di grid** (ketik nomor + Enter, atau ▲▼), lalu ikut tersalin ke honor berikutnya (opsi "Salin penerima") dan dipertahankan oleh impor Excel. **Judul kolom cetakan** bisa diatur per komponen di Pengaturan Honor ("Judul di cetakan", mis. "Rapot").
Catatan data: di Master Guru lokal, Maya Fadhillah berjabatan Kepala Sekolah (di Excel: Kepala Tata Usaha) — perbaiki di Master Guru atau ubah label jabatan di honor.
**PDF rekap (2026-10-09 sore):** urutan baris, nama, jabatan, semua kolom angka, total, dan judul SAMA dengan Excel (satu sumber `HonorCetak::bahan`); tampilan mengikuti Excel (JABATAN & jumlah Koreksi kuning, "Rp" akuntansi rata kiri/angka kanan/nol "-", nama bergaya Times, judul + header bernomor diulang di tiap halaman, tanda tangan 3 blok). Kertas F4 landscape SUNGGUHAN (215×330 mm) — nama kertas 'F4' tidak dikenal Dompdf dan diam-diam jadi Letter, jadi ukuran ditulis eksplisit. Lebar kolom PDF dibuat lebih longgar untuk nama/jabatan (59 baris ±4 halaman). Dibuktikan dengan membaca isi file PDF di `php spark dev:uji-honor-excel` (43 pemeriksaan; argumen opsional = folder untuk menyimpan rekap.pdf/slip.pdf/rekap.xlsx hasil uji).
