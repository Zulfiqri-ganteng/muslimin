# API PKL / Prakerin untuk Aplikasi Android (staf)

Untuk akun **Operator Sekolah**, **Waka Hubin**, dan **Admin**. Form pengajuan SISWA tetap di web
(`https://binuspkl.kangmuslim.com/`) — aplikasi cukup menampilkan tautannya (`form.tautan` di `pkl/ringkasan`).

Aturan bisnisnya SAMA PERSIS dengan web, karena keduanya memanggil library yang sama
(`PklKeputusan`, `PklStaf`, `PklForm`, `PklAjuan`, `PklSurat`). Rancangan: `DESAIN-PKL.md`. Controller: `app/Controllers/Api/Pkl.php`.

## Dasar

- Alamat dasar: `/api/v1/` · Autentikasi: `Authorization: Bearer <token>` (dari `POST auth/login`).
- Amplop respons: `{ "status", "ok", "message", "data", "meta"? }`. Galat validasi: HTTP 422 dengan `data.errors = {kolom: pesan}`.
- Kode HTTP: `200/201` berhasil · `401` token tak ada/kedaluwarsa · `403` peran tak berhak · `404` tak ada · `409` status ajuan sudah
  berubah / bentrok · `422` isian tidak sah.
- **Gerbang peran.** Operator & Waka Hubin HANYA boleh alamat berawalan `pkl/…`, `auth/…`, dan `admin/profile…` (profil & ganti sandi sendiri, sama seperti di web); alamat API lain → `403`.
  Admin boleh semua. Info untuk menyusun menu ada di `auth/login` & `auth/me` → `admin.akses_api` (`["pkl"]` / `["*"]`) dan
  `admin.boleh_acc` (true untuk Waka Hubin & Admin), dan `admin.wajib_ganti_sandi` (true = sandi sementara). **Server tetap yang menjaga**; menyembunyikan tombol di aplikasi hanya kenyamanan.
- **Sandi sementara.** Operator/Waka Hubin yang masih `wajib_ganti_sandi` hanya boleh `auth/…` dan `admin/profile…`; API PKL membalas `403` "Anda masih memakai sandi sementara…" sampai sandi diganti (Admin tidak dibatasi, agar aplikasi lama tetap jalan).

## Siapa boleh apa

| Aksi | Operator | Waka Hubin | Admin |
|---|:-:|:-:|:-:|
| Lihat daftar/detail/ringkasan/status siswa | ✅ | ✅ | ✅ |
| Isi atas nama (status menunggu) | ✅ | ✅ | ✅ |
| Isi atas nama langsung **disetujui** | ❌ 403 | ✅ | ✅ (wajib `wakil`) |
| Ubah ajuan belum disetujui | ✅ | ✅ | ✅ |
| Ubah ajuan **sudah disetujui** | ❌ 403 | ✅ | ✅ |
| Kembalikan untuk perbaikan (alasan wajib) | ✅ | ✅ | ✅ |
| **ACC**, tolak, batalkan persetujuan, ACC massal | ❌ 403 | ✅ | ✅ cadangan, **wajib `"wakil": true`** untuk ACC |
| Hapus ajuan belum disetujui | ✅ | ❌ 403 | ✅ |
| Hapus ajuan **disetujui** | ❌ 403 | ❌ 403 | ✅ (wajib `"paham": true`) |
| Unduh surat Word | ✅ | ✅ | ✅ |
| Tanda tangan digital Waka Hubin (lihat/unggah/hapus) | ❌ 403 | ✅ | ✅ |

Percobaan yang ditolak (mis. Operator mencoba ACC) tercatat di **Audit Log** dengan tanda "(via aplikasi)".
Setiap ACC menyimpan **catatan ACC**: nama, peran (`hubin`/`admin`), waktu, IP, dan kode verifikasi `PKL-xxxxx-XXXXXX` — dicetak di kaki surat.
Admin yang ACC tercatat "Admin — mewakili Waka Hubin" dan **gambar tanda tangan Hubin tidak dipasang** pada suratnya.

## Endpoint

### Kamus & ringkasan
- `GET pkl/meta` → label status/fase/aksi, `aturan` (`maks_siswa`, `batas_keputusan_hari`, `maks_acc_massal`, `maks_surat_massal`,
  `ttd_maks_byte`), `kelas[]` (id, nama, tingkat), `hak` (peran ini: `acc`, `wajib_wakil`, `pengaturan`, `hapus`, `tanda_tangan`).
- `GET pkl/ringkasan` → `form` (`terbuka`, `alasan`, `tautan`, `tingkat`, `tutup_pada`), `hitung` per status + total, `terlambat`,
  `batas_hari`, `siswa` (sudah/belum mengisi & PKL), `persiapan[]` (hanya untuk yang boleh Pengaturan), `antrean[]` (8 tertua menunggu),
  `surat`, `hak`.

### Ajuan
- `GET pkl/ajuan?status=menunggu|perbaikan|disetujui|ditolak|semua&q=&kelas_id=&page=&per=` — `per` maks 100. Bawaan `menunggu`
  (tertua dulu = antrean; yang lain terbaru dulu). Setiap baris: `id, kode, status, status_label, sumber, perusahaan_nama, perusahaan_kota,
  pengaju, pengaju_kelas, jumlah_siswa, dikirim_pada, revisi_ke, batas_keputusan, sisa_hari, terlambat, catatan_staf, acc{…}`.
  Tambahan: baris `menunggu` memuat `pemeriksaan{aman, bahaya, awas, pertama}`; baris `disetujui` memuat `surat{nomor, perlu_cetak_ulang}`.
  `meta.pagination` berisi `page, per_page, total, total_pages`.
- `GET pkl/ajuan/{id}` → baris di atas + `perusahaan{…}`, `anggota[]` (siswa_id, nama, peran pengaju|teman, kelas, jurusan, nis, nisn, `hp`,
  `hp_master`), `peringatan[]` (`tingkat`: bahaya|awas|info), `ada_bahaya`, `master` (perusahaan master yang mirip), `surat_detail`,
  `riwayat[]`, dan **`hak`** (apa yang BOLEH dilakukan peran ini pada ajuan ini: `acc, kembalikan, tolak, batal_acc, ubah, hapus, surat,
  wajib_wakil`) — aplikasi cukup menampilkan tombol sesuai `hak`.
- `POST pkl/ajuan` — isi atas nama. JSON: `siswa_id` (pengaju), `perusahaan_nama`, `perusahaan_alamat`, `perusahaan_kota`, `hp`,
  `teman` (`[id,…]`, maks sesuai pengaturan), `teman_hp` (`{"<id>": "08…"}`) — **`hp` pengaju dan `teman_hp` SETIAP teman wajib** (422 `hp` / `hp_teman_<id>` bila kosong atau tak sah; berlaku juga di `…/ubah`) —, opsional `perusahaan_telepon`, `kontak_nama`,
  `kontak_jabatan`, `status_awal` (`menunggu` bawaan | `disetujui` hanya Hubin/Admin). Siswa yang sudah punya ajuan aktif → `422`
  (`siswa_id`/`teman`). Balasan `201` `{id, kode, status}`.
- `POST pkl/ajuan/{id}/ubah` — isi sama seperti di atas (tanpa `status_awal`); pengaju tak bisa diganti.
- `DELETE pkl/ajuan/{id}` — konfirmasi `{"paham": true}` di body **atau** `?paham=1` di alamat (wajib bila ajuan sudah disetujui).

### Keputusan (body JSON; semuanya mengembalikan `{id, kode, status}`)
- `POST pkl/ajuan/{id}/acc` — `{catatan?, perusahaan_id?, paham?, wakil?}`.
  `paham:true` wajib bila ada peringatan **bahaya** (`ada_bahaya`). `wakil:true` wajib bila yang ACC Admin. Dari status menunggu/perbaikan/ditolak.
- `POST pkl/ajuan/{id}/kembalikan` — `{catatan}` (alasan ≥ 5 huruf, dibaca siswa). Dari status menunggu.
- `POST pkl/ajuan/{id}/tolak` — `{catatan}` wajib. Dari menunggu/perbaikan. Siswa boleh mengajukan lagi.
- `POST pkl/ajuan/{id}/batal-acc` — `{catatan}` wajib. Persetujuan dicabut → status perbaikan, catatan ACC dikosongkan.
- `POST pkl/acc-massal` — `{mode: "terpilih"|"aman", ids?: [..], wakil?}`. Hanya ajuan tanpa peringatan bahaya/awas yang disetujui;
  sisanya dilewati dan dilaporkan. Balasan `{disetujui: n, dilewati: ["PKL-00012 PT … — dilewati: …"]}`. Maks 200 per aksi.

### Surat Word (balasan BERKAS, bukan JSON)
- `POST pkl/ajuan/{id}/surat` — body opsional `{"tanggal_surat": "YYYY-MM-DD"}` (bawaan hari ini). Hanya ajuan **disetujui** (`409` bila bukan).
  Nomor surat diterbitkan sekali (`{urut}/SMK-BN/PKL/{romawi}/{tahun}`), unduhan berikutnya memakai nomor yang sama.
- `POST pkl/surat-massal` — `{mode: "terpilih"|"belum"|"semua", ids?: [..]}`; maks 300 surat. Mode `belum` = yang belum dicetak /
  perlu cetak ulang; bila tak ada → `200` JSON `{jumlah: 0}` tanpa berkas.
- Balasan berkas: `Content-Disposition: attachment; filename="295 Surat Izin PKL BINUS - Ilyasha ALL XII TKJ 5.docx"` (pola sekolah),
  header `X-Jumlah-Surat`. Satu berkas `.docx` untuk sampai 60 surat (tiap surat di halaman baru); lebih dari itu → `.zip`.
  Gunakan unduhan biner (mis. Dio `ResponseType.bytes`) lalu simpan/bagikan; format HTTP biasa, bukan JSON.

### Status siswa
- `GET pkl/siswa?kelas_id=&fase=&q=&page=&per=` — `fase` ∈ `belum_mengisi, sudah_mengisi, sudah_pkl, belum, ditolak, menunggu, perbaikan,
  belum_mulai, sedang, selesai, disetujui` (kosong = semua); `per` maks 100 (bawaan 50). Baris: `siswa_id, nama, kelas, fase, fase_label,
  ajuan_id, kode, perusahaan, kota, nomor_surat, acc_nama, acc_waktu`.
- `GET pkl/siswa/ringkasan?kelas_id=` → angka sudah/belum mengisi & PKL + persen.
- `GET pkl/siswa-kelas?kelas_id=` → siswa aktif satu kelas (semua tingkat) + `status` (`belum|ditolak` boleh dipilih; `menunggu|perbaikan|disetujui` terkunci) — untuk pemilih siswa di form isi atas nama.

### Tanda tangan digital Waka Hubin (Waka Hubin / Admin)
- `GET pkl/ttd` → `{ada, jenis, versi, atas_nama, jabatan, gambar_url}`.
- `GET pkl/ttd/gambar` → gambar (PNG/JPG) — wajib pakai header Bearer (tidak bisa dimuat lewat `Image.network` polos; gunakan unduh biner).
- `POST pkl/ttd` — `multipart/form-data`, berkas pada kolom **`ttd`** (PNG/JPG, ≤ 1 MB, 100–4000 px; jenis dicek dari isi berkas).
- `DELETE pkl/ttd`.

## Catatan untuk pembuat aplikasi
1. Setelah login, baca `admin.akses_api`: bila `["pkl"]`, tampilkan HANYA menu PKL (jangan panggil API lain → 403).
2. Tombol per ajuan dibaca dari `data.hak` pada detail; jangan menebak dari peran.
3. Setelah aksi apa pun, muat ulang detail (status bisa sudah diubah staf lain → `409`, pesan sudah ramah).
4. Tampilkan `batas_keputusan`/`sisa_hari`/`terlambat` pada antrean (aturan: Waka Hubin memutuskan ≤ `batas_hari` hari sejak dikirim).
5. Pesan galat dari server berbahasa Indonesia sederhana dan boleh langsung ditampilkan ke pengguna.

## Pengujian
`node uji_api_pkl.mjs` (skrip di folder sementara sesi, bukan di repo) — 109 cek HTTP: login 3 peran, gerbang alamat, seluruh matriks
hak di atas, ACC/tolak/batal/massal, surat satu & massal (+ cek gambar tanda tangan hanya pada ACC Hubin), tanda tangan, hapus,
status siswa, profil & sandi sementara, serta jejak riwayat & Audit Log. Lulus 109/109 pada 2026-10-07; data ujinya dibersihkan sendiri.
Contoh respons nyata untuk tim Flutter: `C:lutter-muslimin	estixturespkl`; kontrak sisi aplikasi: `C:lutter-musliminBLUEPRINT-PKL.md`.
