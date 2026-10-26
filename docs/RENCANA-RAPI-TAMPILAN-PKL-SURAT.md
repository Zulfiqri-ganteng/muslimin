# Rencana: Rapikan Tampilan PKL & Surat Sekolah (TANPA mengubah logika)

Dibuat 2026-10-26. **Status (2026-10-26 malam): U1–U7 SELESAI.** Dikerjakan SESUDAH fitur Perpanjangan PKL (`docs/DESAIN-PERPANJANGAN-PKL.md`
F1–F5). Kerja per fase: satu fase → lapor → BERHENTI menunggu aba-aba.

Permintaan user 2026-10-26: "rapikan tampilan web tanpa merubah logika sistem yang sudah berjalan" · "saya ga suka kalau terlalu rame
tombol, simple, mudah digunakan, tanpa bug".

## 0. Arti "tanpa mengubah logika" — aturan keras

**Boleh diubah (tampilan saja):**
- Berkas tampilan `app/Views/admin/pkl/*`, `app/Views/admin/surat/*`, bagian sidebar di `app/Views/layouts/admin.php`.
- Gaya `resources/css/admin.css` (lalu `npm run build:admin`), skrip tampilan `public/assets/js/admin/*` (buka/tutup, bilah pilih, cari).
- Teks panduan halaman (helpKey dinaikkan bila isi panduan berubah).

**TIDAK boleh diubah:**
- Controller (alur & aturan), `app/Libraries/*`, `app/Models/*`, migrasi/database.
- Alamat (`app/Config/Routes.php`), API `api/v1/*` dan isi JSON-nya → **layar Android yang sudah ada tetap jalan**.
- Nama isian formulir (`name="…"`) dan nilai yang dikirim, token CSRF, urutan langkah proses.
- Aturan hak (siapa boleh apa), isi/format surat Word & Excel.
- Katalog menu `MenuAdmin::definisi()` bagian kunci/awalan/hak (katalog ini juga sumber matriks Hak Akses).

**Pengecualian yang boleh, wajib dilaporkan:** bila tampilan baru butuh data yang belum dikirim ke view, boleh menambah **fungsi BACA
baru** (tanpa mengubah fungsi lama). Data yang sudah ada diutamakan.

**Penjaga (wajib lulus tiap fase):**
1. Semua uji lama hijau: `dev:uji-pkl`, `dev:uji-surat`, `dev:uji-lapor`, `dev:uji-hak`, `dev:uji-pkl-keluar`, `dev:uji-pkl-biaya`,
   `dev:uji-api-android`, `dev:cek-ui`. Uji yang memeriksa MARKUP (bukan logika) boleh disesuaikan — setiap penyesuaian disebut di laporan.
2. Matriks Hak Akses dibandingkan sebelum/sesudah: harus IDENTIK.
3. Daftar `name=` isian dan `action=` formulir per halaman dibandingkan sebelum/sesudah (skrip): harus identik.
4. Uji peramban: peran Admin/Operator/Hubin × lebar 1366 & 360 × tema Terang/Gelap/Warna Sekolah; galat JS 0, CSP 0, tidak melebar ke
   samping, kontras AA (`tools/uji-peramban.mjs --kontras --fokus --wajib-csp-nol`).
5. Tanpa foto layar berlebihan (uji teks/hitungan).

## 1. Hasil ukur sekarang (Operator, data salinan hosting 25-10, Chrome tanpa layar)

| Halaman | Tombol tampak | Panjang (layar laptop) | HP | Catatan |
|---|---|---|---|---|
| Beranda PKL | 4 | 2,6 | 4,1 | 12 kotak angka; angka sama tampil 2–3× |
| Kotak Masuk · Disetujui | **48** | 4,6 | 5,4 | 4 tombol massal + paragraf petunjuk selalu tampil |
| Detail ajuan (disetujui) | 16 | 3,9 | 4,9 | 10 bagian; 4 "Hapus catatan biaya" per siswa terbuka |
| Data Prakerin | 14 | **33** | **52** | 11 kelas sekaligus |
| Laporan Pembayaran | 4 | 10 | 12 | |
| Pengaturan PKL | 16 | 8 | 11 | 37 isian, 10 bagian di satu halaman |
| Daftar Surat | **30** | 5 | 6,8 | 74 lencana; 1 baris = ±6 baris teks |
| Buat Balasan / Penarikan | 5–7 | 2 | 2,5 | memuat **182 ajuan** (187 isian) untuk dicentang |
| Lapor Diterima PKL | 7 | 1,5 | 2,1 | 4 tombol per laporan |
| Detail surat | 2–7 | 1,9 | 1,8 | **sudah baik** |
| Form siswa (PKL, Lapor, Layanan) | 0–5 | 1–2 | 1–3 | **sudah baik** — tidak dirombak |

Navigasi: Operator melihat **15 menu** PKL+Surat di sidebar, dan tiap halaman PKL menampilkan lagi **bilah tab 9 item** yang mengulang
sidebar. Pekerjaan tersebar di 4 kotak: Kotak Masuk PKL, Lapor Diterima PKL, "Permintaan dari siswa" (Daftar Surat), menu per jenis surat.

**Android:** PKL sudah dibangun (P1–P17) dengan 4 tab bawah (Beranda · Ajuan · Siswa · Akun) — lebih sederhana dari web; Surat
(S0–S12) belum dibangun dan masih membuka web. Karena API tidak diubah, layar PKL Android tetap jalan; layar Surat Android nanti
langsung mengikuti susunan baru (catatan ke `BLUEPRINT-SURAT.md` di U7).

## 2. Prinsip tampilan

1. **1 halaman = 1 tugas, 1 tombol utama** (berwarna, berikon). Tombol lain = tombol garis biasa, maksimal 2.
2. Aksi jarang / berbahaya (hapus catatan, batalkan, pindah, cetak ulang) masuk menu **"⋯ Lainnya"**; aksi hapus tetap minta konfirmasi.
3. **Tombol massal muncul hanya saat ada yang dicentang** — bilah melayang di bawah: "3 dipilih · Unduh · ACC · Kabari WA · Batal pilih".
4. **Satu angka tampil sekali.** Ringkasan angka di Beranda saja; halaman lain cukup tab status berangka.
5. **Cari dulu, baru pilih** — tidak menampilkan ratusan baris untuk dicentang.
6. Penjelasan panjang pindah ke "Baca panduan halaman"; di halaman cukup 1 kalimat.
7. Bagian panjang dilipat (Riwayat, Pembayaran, data lama) dengan ringkasan satu baris di kepalanya.
8. Gaya tetap: kit Flowbite + 3 tema + siap CSP, kartu garis 1 px, lilac hemat, tanpa gradasi/emoji (tidak terlihat buatan AI).

## 3. Rancangan per halaman (sebelum → sesudah)

| Halaman | Sesudah |
|---|---|
| **Beranda PKL** | Menjadi **"Tugas hari ini"**: daftar urut, tiap baris = jumlah + kalimat + tombol "Kerjakan" (hanya yang > 0): ajuan menunggu ACC · ajuan perlu perbaikan · laporan "diterima perusahaan" → buat Balasan · permintaan surat dari siswa · surat siap diunduh · siswa belum dikabari WA · PKL berakhir ≤ 7 hari / permintaan perpanjangan. Di bawahnya 1 kartu ringkas: form siswa buka/tutup + tautan salin, dan 2 bilah kemajuan (sudah isi, sudah PKL). Angka status ajuan tidak diulang (sudah ada di tab Kotak Masuk). Antrean 8 ajuan teratas tetap. |
| **Bilah tab PKL** | Dihapus dari semua halaman (sidebar sudah cukup). Tab status (Menunggu/Perbaikan/Disetujui/Ditolak) tetap, hanya di Kotak Masuk. |
| **Kotak Masuk** | Per baris: perusahaan, pengaju + jumlah teman, kode, waktu, 1 lencana status surat, tombol "Buka". WA per baris → pindah ke bilah massal & detail. Tombol massal + petunjuk unduh → bilah melayang saat dicentang. Saringan kelas hanya tingkat PKL (XI/XII). "Isi atas nama" = tombol kecil di kepala halaman. |
| **Detail ajuan** | Kepala: nama perusahaan + status + **1 tombol utama sesuai status** (Menunggu → ACC; Disetujui → Unduh surat) + "⋯ Lainnya". 10 bagian → 4 kelompok: **Perusahaan & siswa** · **Keputusan & surat** (ACC, surat permohonan, surat sekolah) · **Pembayaran** (dilipat, total di kepala; hapus catatan di ⋯ tiap baris) · **Riwayat** (dilipat). Paragraf aturan hak → 1 kalimat + panduan. |
| **Status Siswa** | Saringan dirapikan jadi satu baris; kartu angka tetap; tabel tanpa perubahan kolom. |
| **Data Prakerin** | Pilih kelas dulu (tab/pilihan kelas, bawaan kelas pertama) → 1 kelas per tampilan. Unduh Excel 11 lembar tetap. |
| **Laporan Pembayaran** | Ringkasan di atas, tabel per halaman (mis. 50 baris) + cari; Unduh Excel tetap. |
| **Pengaturan PKL** | Dipecah jadi tab dalam satu halaman: Form siswa · Surat & nomor · Biaya & WA · Perpanjangan · Data lama. Isian & tombol Simpan tiap bagian tetap (nama isian sama). |
| **Daftar Surat** | Kotak "Permintaan dari siswa" jadi satu baris ringkas per jenis yang > 0. Baris surat: nomor/kode, jenis, tujuan, status (1 lencana), tombol "Buka"; nama pembuat/ACC/unduhan pindah ke detail. Bilah massal saat dicentang. |
| **Buat surat (Balasan, Penarikan, Perpanjangan)** | Kotak cari "nama siswa / perusahaan" → pilih → isi → 1 tombol utama. Daftar 182 ajuan dihapus dari tampilan (data & nama isian sama). |
| **Lapor Diterima PKL** | Per laporan 1 tombol utama sesuai hak ("Setujui & buat surat" atau "Buat & ajukan ACC") + "⋯" (Periksa & ubah dulu, Kembalikan ke siswa). |
| **Detail surat** | Sudah baik; hanya tombol jarang (Batalkan, Pindah) masuk "⋯". |

## 4. Menu samping (usulan, tunggu konfirmasi user)

Judul grup TIDAK diganti (kunci ingatan buka/tutup = md5 judul). Katalog hak tidak diubah; yang diubah hanya item yang DITAMPILKAN.

- **PKL / PRAKERIN:** Beranda PKL · Kotak Masuk · Status Siswa · Data Prakerin · **Sertifikat PKL (Segera)** · Laporan Pembayaran · Tanda Tangan ·
  Template Alasan · Pengaturan PKL. "Isi atas Nama" keluar dari sidebar → tombol di Kotak Masuk & Beranda (alamat tetap).
- **SURAT SEKOLAH:** Daftar Surat · **Buat Surat** (halaman pilihan: satu ubin per jenis yang boleh dibuat peran itu → membuka halaman
  jenis yang SAMA seperti sekarang) · Lapor Diterima PKL · Jadwal Ujian & TKA. Menu per jenis keluar dari sidebar; alamatnya tetap jalan
  (dari ubin, Daftar Surat, tautan lama/bookmark).
- Operator: 15 → ±11 item; tiap item yang hilang dari sidebar tetap bisa dicapai dalam 1 klik.
- Penjaga: `dev:uji-hak` (urutan grup, bagian, Segera) + matriks Hak Akses identik + menu aktif tetap menyala di halaman jenis surat.

## 5. Fase

| Fase | Isi | Penjaga khusus |
|---|---|---|
| **U1** Komponen bersama | bilah aksi saat dicentang, menu "⋯ Lainnya", kotak cari-lalu-pilih (sebagian sudah dibuat di Perpanjangan F2), bagian dilipat; contoh di `admin/ui-kit` | ui-kit `?uji=1` hijau, CSP 0 |
| **U2** Beranda & menu | "Tugas hari ini", hapus bilah tab PKL, sidebar (bagian 4) | matriks hak identik, uji-hak |
| **U3** Daftar | Kotak Masuk PKL + Daftar Surat | uji-pkl, uji-surat, daftar `name=` identik |
| **U4** Detail | detail ajuan + detail surat + Lapor Diterima | uji-pkl, uji-surat, uji-lapor |
| **U5** Formulir buat surat | Balasan, Penarikan (cari-lalu-pilih), Buat Surat (ubin) | uji-surat, isian terkirim identik |
| **U6** Halaman panjang | Data Prakerin per kelas, Laporan Pembayaran, Pengaturan PKL bertab | uji-pkl-biaya, Excel tetap sama byte-per-isi |
| **U7** Penutup | uji peramban lengkap (bagian 0), panduan halaman, catatan susunan baru di `BLUEPRINT-SURAT.md`/`BLUEPRINT-PKL.md` (API tak berubah; opsional tahap Android "Tugas hari ini"), memori | semua uji |

**Kemajuan 2026-10-26 malam.** U3: Kotak Masuk & Daftar Surat — tombol massal di kepala kartu + menu Lainnya, ACC/Unduh terpilih di bilah yang muncul saat dicentang (daftarCentang kini menghitung lewat @change; "Batal pilih" ikut mengosongkan kotak Pilih semua), kolom Dibuat oleh/Diunduh dibuang, Permintaan dari siswa jadi satu baris. U4: Detail ajuan (tanpa bilah tab; Periode ikut Perusahaan; info ACC ikut Keputusan; Keputusan = tombol utama + Lainnya; satu kartu Surat dengan menu Buat surat; Pembayaran & Riwayat dilipat), Detail surat (Ubah/Batalkan di Lainnya, Riwayat dilipat), Lapor Diterima (1 tombol utama + Lainnya; Kembalikan jadi dialog). U5: Balasan & Penarikan cari-lalu-pilih (daftar 182 ajuan baru tampil setelah mengetik ≥ 2 huruf / Lihat semua; yang dipilih selalu tampil). Halaman "Buat Surat" ber-ubin TIDAK dibuat: sudah tercakup sub-menu lipat "Buat surat" di sidebar (U2), jadi tanpa rute/hak baru. Penjaga: formulir 28 halaman × Operator/Admin identik, galat JS/CSP 0, HP tidak melebar, sidik hak/menu/API identik, semua dev:uji hijau (UjiSurat: satu pemeriksaan kini mengurai entitas HTML karena tautan Buat surat ditulis lewat esc(…, "attr")). Contoh: Detail ajuan 23 → 14 tombol, tinggi 4,1 → 1,7 layar; Kotak Masuk Disetujui 48 → 26 tombol; Lapor Diterima 11 → 5 tombol.

**U6 (2026-10-26 malam).** Data Prakerin: tiap kelas = bagian dilipat (ringkas "N siswa · M diterima"; rincian + Unduh kelas ini di dalam; satu kelas terpilih = langsung terbuka) — tinggi 33,8 → 1,9 layar (HP 52 → 2,2). Laporan Pembayaran: tujuh kotak angka → satu kartu, saringan Kelas/Jurusan/Tanggal/Beasiswa dilipat di "Saringan lain" (terbuka bila dipakai); paginasi 40 baris (PklBiaya::PER) TIDAK diubah, jadi tingginya tetap ± 10 layar karena memang 40 baris. Pengaturan PKL: tab kit Form siswa · Surat & nomor · Biaya & WhatsApp (data-hash; tautan lama #surat/#biaya membuka tabnya; tab bergalat dibuka & ditandai "!"; tab terakhir dipulihkan sesudah Simpan lewat sessionStorage) — tinggi 8,4 → 3,5 layar, tombol tampak 16 → 8. Penjaga: formulir 28 halaman identik, JS/CSP 0, semua uji hijau, uji klik `uji_u6.mjs` 5/0.

**U7 (2026-10-26 malam, uji diringkas atas permintaan user).** Panduan halaman diperbarui (Lapor Diterima, form Balasan/Penarikan, Laporan Pembayaran; halaman lain sudah di U3–U6), panduan staf `docs/PANDUAN-TAMPILAN-PKL-SURAT.md`, catatan deploy bagian 7 `docs/PANDUAN-DEPLOY-PERPANJANGAN.md`, catatan Flutter blok "PEMBARUAN 2026-10-26c" di `BLUEPRINT-SURAT.md`/`BLUEPRINT-PKL.md` + `PROGRES-KERJA.md` (API tak berubah, jumlah tahap tetap). Uji: semua dev:uji hijau, dev:cek-ui 0, peramban Hubin 28 halaman tanpa galat JS/CSP/melebar (Operator & Admin sudah di U3–U6). Uji tema Gelap/Warna Sekolah & kontras TIDAK dijalankan (dihemat).

**Deploy (user):** `git pull` + Ctrl+F5. **Tanpa migrasi.** CSS/JS hasil bangun ikut git.
