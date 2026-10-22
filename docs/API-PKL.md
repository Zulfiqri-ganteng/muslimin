# API PKL / Prakerin untuk Aplikasi Android (staf)

Untuk akun **Operator Sekolah**, **Waka Hubin**, dan **Admin**. Form pengajuan SISWA tetap di web
(`https://pklbinanusa.kangmuslim.com/`) — aplikasi cukup menampilkan tautannya (`form.tautan` di `pkl/ringkasan`).

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

> **PEMBARUAN 2026-10-09:** hak PKL kini **diatur Admin** (web: PKL → Hak Akses; API: `GET/POST pkl/hak-akses`), bukan lagi tetap per peran.
> Tabel di bawah = hak **bawaan sebelum** pembaruan; yang berlaku sekarang ada di bagian **"Hak diatur Admin, biaya, WhatsApp, laporan"**
> (di bawah) — bawaan baru: Waka Hubin = ACC + ubah + tanda tangan; Operator = unduh surat + laporan + ubah + pengaturan; hapus = Admin saja.
> **Waka Hubin tidak lagi mengunduh surat** (agar jelas siapa menyetujui dan siapa mencetak). Yang bertentangan dengan bagian baru dianggap usang.

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
- `POST pkl/ttd` — `multipart/form-data`, berkas pada kolom **`ttd`** (PNG/JPG, ≤ 5 MB sejak 2026-10-13 — lihat `pkl/meta → aturan.ttd_maks_byte`,
  100–4000 px; jenis dicek dari isi berkas). Server MERAPIKAN otomatis (tepi kosong dipotong, latar kertas transparan, ≤ 1200 px, PNG);
  `GET pkl/ttd/gambar` mengembalikan hasil rapian (yang tercetak). Foto HP langsung boleh.
- `DELETE pkl/ttd`.

## Hak diatur Admin, biaya, WhatsApp, laporan (pembaruan 2026-10-09)

Rancangan & alasan: `docs/DESAIN-PKL.md` (bagian "Hak akses diatur Admin, biaya, WhatsApp, laporan"). Kontrak sisi aplikasi: `C:\flutter-muslimin\BLUEPRINT-PKL-BIAYA.md`.
Contoh respons nyata (data disamarkan): `C:\flutter-muslimin\test\fixtures\pkl\` (daftar berkas di `_CATATAN.txt`).

### Tujuh hak (`Libraries\PklHak`)
`acc` (ACC/tolak/batal ACC/ACC massal) · `surat` (unduh surat + catat biaya + WhatsApp + koreksi biaya) · `laporan` (Laporan Pembayaran) ·
`ubah` (kembalikan, ubah, isi atas nama) · `ttd` (tanda tangan digital Waka Hubin) · `pengaturan` (pengaturan, nominal biaya, impor) · `hapus`.
Bawaan: **Hubin** = `acc`, `ubah`, `ttd` · **Operator** = `surat`, `laporan`, `ubah`, `pengaturan` · **Admin** = semua, selalu (tak bisa dicabut).
Disimpan di `pkl_pengaturan.hak_peran` (JSON); kosong/rusak = bawaan. Berlaku **langsung**, di web & API.
Terbaca di: `auth/login`, `auth/biometric/login`, `auth/me` → `data.admin.hak_pkl` (kunci → bool) · `GET pkl/meta` → `data.hak` (+ `hak_pkl` = kamus judul/keterangan) ·
`GET pkl/ringkasan` → `data.hak` · `GET pkl/ajuan/{id}` → `data.hak` per ajuan (kunci baru: `kembalikan`, `surat`, `pembayaran`, `koreksi_pembayaran`).
`hak.atur_hak` = true hanya Admin. Pelanggaran → **403** (pesan menyebut peran dan "PKL → Hak Akses").

### Endpoint baru
| Metode | Endpoint | Hak | Keterangan |
|---|---|---|---|
| GET | `pkl/biaya[?semua=1]` | PKL | jenis biaya aktif (`semua=1` + hak `pengaturan`: termasuk nonaktif), `sumber_beasiswa`, `bulan_default`, `maks_jumlah_bulan`, `aturan`, `wa_templat`, `wa_penanda` |
| POST | `pkl/biaya` | `pengaturan` | `{biaya:{kode:{nama,nominal,aktif}}, wa_pesan?, wa_pesan_perbaikan?, wa_pesan_tolak?}` — sebagian boleh; 422 bila nominal tak sah / semua nonaktif / penanda asing. GET juga memuat `wa_templat_perbaikan`, `wa_templat_tolak`, `wa_penanda_keputusan` |
| GET | `pkl/surat/siap?mode=&ids[]=` | `surat` | keadaan biaya tiap siswa sebelum unduh; tiap siswa membawa `bulan_saran` (YYYY-MM: bulan pertama mulai bulan berjalan yang biaya bulanannya belum lengkap — nilai awal pemilih bulan) |
| POST | `pkl/ajuan/{id}/surat`, `pkl/surat-massal` | `surat` | kini WAJIB memuat biaya (di bawah) |
| GET | `pkl/ajuan/{id}/pembayaran` | `surat`/`laporan` | catatan biaya per siswa (+ `data.pembayaran` pada detail ajuan) |
| POST | `pkl/ajuan/{id}/pembayaran/hapus` | `surat` | `{pembayaran_id, alasan(5–200)}` |
| POST | `pkl/ajuan/{id}/pembayaran/beasiswa-cabut` | `surat` | `{siswa_id, alasan}` |
| GET | `pkl/wa?ids[]=` | `surat` | siswa + tautan `https://wa.me/62…?text=…` + pesan; `alasan_tidak` bila HP tak sah |
| POST | `pkl/ajuan/{id}/wa/{siswa_id}/tandai` | `surat` | catat "sudah dikabari" |
| GET | `pkl/ajuan/{id}/kabar-keputusan` | `acc`/`ubah`/`surat` | (2026-10-18) ajuan **dikembalikan/ditolak**: `{status, alasan, diputuskan_at, siswa[{siswa_id, nama, kelas, pengaju, hp, wa, url, alasan_tidak, pesan, dikabari_at, dikabari_oleh}]}`; 409 bila status lain. `dikabari_at` hanya terisi bila sesudah keputusan terakhir |
| POST | `pkl/ajuan/{id}/kabar-keputusan/{siswa_id}/tandai` | `acc`/`ubah`/`surat` | catat "sudah dikabari keputusan" (riwayat `kabari_keputusan`) |
| GET | `pkl/laporan?kelas_id=&jurusan=TKJ\|AKL\|MP&status=lunas\|sebagian\|belum&q=&dari=&sampai=&beasiswa=1&page=&per=` | `laporan` | `{ringkas, jenis, baris[]}` + `meta{page,perPage,total}` |
| GET | `pkl/laporan/excel?…` | `laporan` | berkas `.xlsx` (lembar Rincian, Rekap Kelas, Rekap Jurusan) |
| GET / POST | `pkl/hak-akses` | **Admin** | matriks, kamus, bawaan, peringatan; simpan `{hak:{hubin:[…],operator:[…]}}` |

### Unduh surat dengan biaya
Body: `{"tanggal_surat"?, "biaya": {"<siswa_id>": {"jenis":["pkl","spp"],"bulan":"2026-10","jumlah_bulan":1,"beasiswa":"sktm","beasiswa_ket":"…","keringanan":"alasan"}}, "semua": {"jenis":[…],"bulan":"2026-10","jumlah_bulan":1}}`
(massal juga `mode`, `ids`). **Gerbang:** tiap siswa wajib punya minimal satu catatan — biaya baru, biaya yang sudah tercatat untuk ajuan itu, beasiswa aktif (membebaskan SPP),
atau keringanan beralasan (≥ 5 huruf); jika tidak → **422** `data.errors` (`umum` = pesan menyebut nama siswa; atau per `siswa_id`), **tidak ada berkas, nomor surat tidak terpakai**.
Pembayaran dicatat **setelah** berkas jadi (satu transaksi, tak digandakan: UNIQUE siswa+jenis+periode). Header balasan sukses: `X-Jumlah-Surat`, `X-Surat-Ids` (id ajuan terbit),
`X-Biaya-Dicatat` (`item:total`). Jenis biaya: `pkl` (sekali per tahun ajaran), `spp`/`tabungan`/`osis` (bulanan; `bulan` YYYY-MM, `jumlah_bulan` 1–12).
**Cetak ulang tanpa dialog** (web sejak 2026-10-12, Flutter wajib meniru): bila semua surat sudah bernomor dan tiap siswa sudah memenuhi gerbang
(`milik_ajuan > 0 || kegiatan_ada || ada_keringanan || beasiswa`), kirim POST unduh tanpa `biaya`/`semua` — lolos, 0 item baru.
Nomor surat kini berformat tiga angka secara bawaan (`001/SMK-BN/PKL/X/2026`); format diatur Admin/Operator di Pengaturan PKL (`{urut}`, `{urut3}`, `{urut4}`).

## Pembaruan 2026-10-13 — semua hak diatur Admin, lapor diterima, TTD + stempel Kepala Sekolah

Rancangan: `docs/DESAIN-HAK-AKSES.md`. Bagian ini MENGGANTIKAN tabel "Siapa boleh apa" di atas bila bertentangan.
- **Peran & menu diatur Admin** (web: Sistem → Hak Akses). Peran baru bisa dibuat (mis. Waka Kesiswaan). `auth/login` & `auth/me` →
  `admin.akses_api` = modul yang dipegang (`["pkl","surat"]`, `["pkl"]`, `["*"]` Admin), `admin.hak_pkl` (kunci → bool),
  `admin.hak_surat` (per jenis surat: `{buat, acc|null, unduh}`), `admin.peran_label`. Susun menu dari sini, JANGAN dari nama peran.
- **Kunci hak PKL** kini 9: `acc, surat, laporan, ubah, ttd, ttd_kepsek, pengaturan, hapus, hapus_disetujui` (+ `surat_sekolah`
  turunan lama). `hapus_disetujui` (bawaan Admin) = boleh menghapus ajuan yang SUDAH disetujui (perlu `hapus` juga).
- **Tidak ada lagi "wakil/mewakili"**: Admin ACC tanpa pernyataan; kiriman `wakil` diabaikan; `hak.wajib_wakil` selalu `false`.
  Gambar tanda tangan Waka Hubin & Kepala Sekolah SELALU tercetak, siapa pun yang meng-ACC; kaki surat mencatat penyetuju
  ("Nama (Admin Sistem)" / "Nama (Label Peran)").
- `GET/POST pkl/hak-akses` tetap **khusus Admin**. Respons kini juga memuat `matriks_surat` (peran → jenis → aksi) dan
  `peran_label`. POST format LAMA (8 kunci) tetap diterima: hak per jenis surat dipertahankan, kecuali saklar `acc` / `surat_sekolah`
  berubah (maka semua jenis ikut berubah). Aplikasi baru sebaiknya mengarahkan Admin ke halaman web untuk pengaturan rinci.
- `GET pkl/ringkasan` → `lapor` `{buka, tautan, baru}`: saklar & tautan form **Lapor Diterima PKL** untuk dibagikan ke siswa
  (`https://pklbinanusa.kangmuslim.com/pkl/lapor`); `baru` = jumlah laporan menunggu (null bila peran ini tidak boleh memprosesnya).
  Kotak laporannya ada di API Surat: `GET/POST surat/balasan/laporan…` (lihat `docs/API-SURAT.md`).
- `GET pkl/ringkasan` → `surat.ttd_kepsek_ada`: gambar tanda tangan + stempel Kepala Sekolah sudah diunggah (menggantikan tanda tangan
  bawaan template di semua surat bernomor). Unggah/hapus hanya lewat web (Tanda Tangan → bagian Kepala Sekolah; hak `ttd_kepsek`).
- Riwayat ajuan punya aksi baru: `lapor_diterima`, `lapor_proses`, `lapor_kembali` (label di `pkl/meta → aksi_riwayat`).

## Pembaruan 2026-10-20 — keluarkan siswa, ditolak perusahaan, riwayat siswa, template alasan

Revisi klien (rancangan & aturan: `docs/DESAIN-PKL.md` bagian "Revisi klien 2026-10-20"). Semua aturan di `Libraries\PklKeluar`,
`Libraries\PklAlasan`, `Libraries\PklLapor` — sama persis dengan web. Hanya MENAMBAH kolom: aplikasi lama tetap jalan.
Butuh migrasi `2026-10-25-000001_PklKeluarAlasan` (server tanpa migrasi menjawab `503` untuk aksi baru).

**Aturan singkat**
- **Keluarkan siswa** (sebagian anggota): ajuan `menunggu`/`perbaikan` → hak `ubah`; ajuan `disetujui` → hak `acc`. Jenis wajib:
  `ditolak_perusahaan | administrasi | pindah | mundur | lainnya` (label di `pkl/meta → jenis_keluar`) + `catatan` 5–1000 huruf.
  Minimal satu siswa tersisa (`422 kode=semua`). Siswa yang dikeluarkan langsung bebas mengajukan baru, dan jejaknya tetap tersimpan.
  Pengaju yang dikeluarkan digantikan anggota tersisa paling awal. Jenis `ditolak_perusahaan` tidak membuat Surat Izin "perlu cetak
  ulang" (surat lama = arsip sah); jenis lain membuatnya perlu cetak ulang (nomor sama).
- **Ditolak perusahaan** (SEMUA siswa): hak `acc`, dari `menunggu`/`perbaikan`/`disetujui` dalam satu langkah. Status tetap
  `ditolak` + `tolak_sumber = "perusahaan"`; `status_label` = "Ditolak perusahaan". ACC ulang mengosongkan sumbernya.
- **Riwayat penolakan siswa** = ajuan ditolak (sekolah/perusahaan) + pernah dikeluarkan (kecuali jenis `pindah`). Peringatan
  `awas` di detail bila siswa dulu ditolak perusahaan YANG SAMA (ikut menahan ACC massal "aman"); selain itu `info`.
- **Template alasan**: siapa pun yang membuka jendela keputusan boleh MEMAKAI; mengelola butuh hak baru `alasan` (bawaan Waka Hubin;
  `admin.hak_pkl.alasan`, `pkl/meta → hak.kelola_alasan`). Jenis: `kembalikan | tolak | keluar | lapor`. Jendela → jenis:
  `kembalikan → [kembalikan]`, `tolak → [tolak]`, `batal_acc → [kembalikan, tolak]`, `keluarkan → [keluar, tolak]`,
  `ditolak_perusahaan → [keluar]`, `lapor → [lapor]`. Alasan keputusan kini maksimal **1000** huruf (`pkl/meta → aturan.alasan_maks_huruf`).
- Kunci hak PKL kini 10: `acc, surat, laporan, ubah, alasan, ttd, ttd_kepsek, pengaturan, hapus, hapus_disetujui`.

**Endpoint baru**

| Metode & alamat | Body / query | Hak | Hasil |
|---|---|---|---|
| `POST pkl/ajuan/{id}/keluarkan` | `{siswa_ids: [int], jenis, catatan}` | ubah (menunggu/perbaikan) · acc (disetujui) | `{id, kode, keluar: [{id, siswa_id, nama}]}` |
| `POST pkl/ajuan/{id}/gabungkan` | `{tujuan_id, catatan?}` | boleh mengubah KEDUA ajuan (ubah / acc bila disetujui) | `{id, kode, tujuan_id, kode_tujuan}` — semua siswa pindah ke tujuan (teman), ajuan ini ditutup: `ditolak` + `tolak_sumber = "gabung"`; perusahaan harus sama/mirip, total ≤ batas siswa |
| `POST pkl/ajuan/{id}/ditolak-perusahaan` | `{catatan}` | acc | `{id, kode, status: "ditolak", tolak_sumber: "perusahaan"}` |
| `GET pkl/ajuan/{id}/kabar-keluar` | — | acc \| ubah \| surat | daftar siswa dikeluarkan + `url` wa.me + `pesan` + `kabar_at` |
| `POST pkl/ajuan/{id}/kabar-keluar/{keluar_id}/tandai` | — | acc \| ubah \| surat | `{waktu}` |
| `GET pkl/siswa/{id}/riwayat` | — | modul PKL | `{siswa, aktif, jumlah_ditolak, riwayat: [{jenis: ajuan\|keluar, ajuan_id, kode, perusahaan, status, tolak_sumber, keterangan, jenis_keluar?, peran, alasan, waktu, diajukan}]}` |
| `GET pkl/alasan` | `?jenis=` atau `?dialog=` | modul PKL | daftar template (`dialog` = berkelompok); `meta {jenis, dialog, boleh_kelola, maks_judul, maks_isi}` |
| `POST pkl/alasan` | `{jenis, judul (3–80), isi (5–1000)}` | alasan | `201 {id}` |
| `POST pkl/alasan/{id}` | `{jenis?, judul, isi}` | alasan | `{id}` |
| `DELETE pkl/alasan/{id}` | — | alasan | `{id}` |
| `POST pkl/alasan/urut` | `{jenis, ids: [semua id kelompok itu, urutan baru]}` | alasan | — (`409` bila daftar tidak lengkap/berubah) |

**Respons yang diperluas**
- `pkl/meta`: `jenis_keluar`, `alasan {jenis, dialog: {jendela: [ {jenis, judul, item: [{id, judul, isi}]} ]}}`, `aturan.alasan_maks_huruf`,
  `hak.kelola_alasan`, `hak.hak_pkl.alasan`, `aksi_riwayat` + `keluarkan`, `tolak_perusahaan`, `kabari_keluar`.
- Baris daftar & detail ajuan: `tolak_sumber` (`perusahaan` | `gabung` | `sekolah` | null), `jumlah_keluar`, `status_label` "Ditolak perusahaan" / "Digabung ke ajuan lain".
- Detail ajuan: `kandidat_gabung` (`[{id, kode, status, perusahaan, pengaju, jumlah}]`, ajuan aktif lain di perusahaan sama/mirip) + `hak.gabungkan`;
  aksi riwayat baru `gabung`, `gabung_masuk`.
- Detail ajuan: `anggota[].riwayat_tolak` (riwayat penolakan siswa itu DI LUAR ajuan ini: `{ajuan_id, kode, perusahaan, sumber, jenis,
  keterangan, alasan, waktu}`), `keluar` (siswa yang dikeluarkan dari ajuan ini: `{id, siswa_id, nama, kelas, peran, hp, jenis,
  jenis_label, alasan, status_ajuan, sumber: staf|lapor, oleh, waktu, kabar_at, kabar_oleh}`), `hak.keluarkan`,
  `hak.ditolak_perusahaan`, `hak.kabar_keluar`.
- `pkl/siswa`: `?fase=pernah_ditolak` (siapa pun yang punya riwayat ditolak/dikeluarkan, apa pun statusnya) + `jumlah_ditolak` per baris.
- `pkl/ajuan/{id}/kabar-keputusan` untuk ajuan ditolak perusahaan: `sumber = "perusahaan"` dan pesan memakai templat "keluar".
- `POST pkl/biaya`: kunci baru `wa_pesan_keluar` (templat WhatsApp siswa dikeluarkan / ditolak perusahaan).
- Kotak Lapor Diterima (API Surat) membawa `hasil` + `siswa_diterima`/`siswa_ditolak` — lihat `docs/API-SURAT.md`.

## Pembaruan 2026-10-21 — peringatan layar penuh (pekerjaan lewat batas keputusan)
Keluhan klien: Waka Hubin jarang membuka sistem sehingga ajuan lama tidak di-ACC. Web menampilkan dialog LAYAR PENUH di setiap halaman
panel admin (`Libraries\PklPengingat`, partial `admin/pkl/_pengingat`); aplikasi memakai endpoint ini untuk hal yang sama.

`GET pkl/pengingat` (Bearer) →
```json
{ "hari": 5, "total": 2,
  "ajuan": { "n": 1, "siswa": 1, "baris": [ { "id": 77, "kode": "PKL-00077", "perusahaan": "ANTARESTAR Store Harapan indah", "siswa": "Romdoni",
             "kelas": "XI TKJ 9", "jumlah_siswa": 1, "dikirim": "2026-10-13 21:27:43", "menunggu_hari": 8 } ] },
  "lapor": { "n": 1, "tertahan": 2, "baris": [ { "id": 12, "ajuan_id": 4, "kode": "PKL-00004", "perusahaan": "Universitas Bhayangkara Jakarta Raya",
             "pelapor": "Dafa Rizki Ismail", "hasil": "sebagian", "hasil_teks": "Sebagian diterima", "tertahan": 2,
             "dikirim": "2026-10-12 21:27:43", "menunggu_hari": 9 } ] },
  "layar_penuh": true, "tunda_detik": 3600 }
```
- Batas `hari` = Pengaturan PKL → "Batas keputusan" (bawaan 5), rumus SAMA dengan `terlambat` di `pkl/ringkasan`: lewat bila tanggal
  kirim + `hari` < hari ini. Untuk laporan dihitung dari kiriman terakhir siswa.
- `ajuan` hanya bagi pemegang hak **ACC ajuan PKL** (bawaan Waka Hubin); `lapor` hanya bagi pemroses kotak Lapor Diterima (hak Buat/ACC
  Surat Balasan; di sekolah = Operator). Bagian yang tidak berlaku atau kosong = `null`. `baris` paling banyak 10; `n` = jumlah semua.
- `lapor.tertahan` = siswa yang dilaporkan TIDAK diterima perusahaan dan masih tercatat (terkunci) di ajuannya sampai laporan diproses.
- `layar_penuh` = `total > 0` dan akun bukan Admin (Admin cukup lonceng/tanda merah, sama dengan web).

`GET pkl/ringkasan` — tiga kunci baru (kotak Beranda PKL web yang sama, satu sumber `Libraries\PklBeranda`):
- `surat_ringkas` `{belum_bernomor, belum_dikabari, uang_bulan_ini}` — hanya bagi hak `surat` atau `laporan`, selain itu `null`.
  Kotak web: "Surat belum diunduh", "Siswa belum dikabari WhatsApp", "Uang tercatat bulan ini".
- `surat_sekolah` `{semua, menunggu, dikembalikan, disetujui, dibatalkan, belum_unduh}` — bagi yang boleh membuka modul Surat DAN
  memegang hak surat sekolah apa pun (buat/ACC/unduh), selain itu `null`. Kotak web: "menunggu ACC", "belum diunduh", "dikembalikan".
- `pemegang_acc` (teks, mis. "Waka Hubin") — untuk judul "Antrean ajuan — menunggu keputusan ACC (…)" bila akun ini tidak memegang
  hak ACC; bila memegang (`hak.acc`), judul web = "Menunggu keputusan Anda".

## Pembaruan 2026-10-22 — tiga kunci tambahan untuk aplikasi (hanya MENAMBAH; web tidak berubah)
- `GET pkl/ringkasan` → **`pemegang_surat`** (teks, mis. "Operator Sekolah") — pemegang hak unduh surat (`PklHak::teksPemegang('surat')`),
  untuk kalimat Kotak Masuk tab Disetujui "Surat diunduh oleh peran yang diberi hak itu oleh Admin (…)".
- `GET pkl/ajuan/{id}` → `riwayat[].peran_label` (`HakAkses::label(peran)`, sama dengan web; `null` bila tanpa peran) — peran baru
  buatan Admin tampil dengan namanya, bukan kodenya.
- `GET pkl/ajuan/{id}` → **`waka_hubin_nama`** (teks/null) — kartu Surat permohonan memperingatkan "Nama Waka Hubin belum diisi…" bila kosong.
- `POST …/wa/{siswa_id}/tandai`, `…/kabar-keputusan/{siswa_id}/tandai`, `…/kabar-keluar/{id}/tandai` → kini juga `oleh` (nama staf),
  sama dengan web — aplikasi menulis "Sudah dikabari … oleh …".
- `GET pkl/ajuan/{id}` → `hak.kabar_keputusan` (bool): tombol "Kabari siswa via WhatsApp" untuk ajuan dikembalikan/ditolak
  (`PklWaKeputusan::boleh` + status perbaikan/ditolak), sama dengan kotak Keputusan web.
- **Catatan klien `POST pkl/hak-akses`** (ditemukan sisi Flutter): kirim daftar LENGKAP per peran — hak PKL + kunci
  `surat_<aksi>:<jenis>` yang berlaku (dari `matriks_surat`). Kiriman hak PKL saja memakai format lama (`PklHak::simpan`) yang bisa
  mencabut hak buat/unduh surat sekolah peran yang tak dikirimi `surat_sekolah`. "ACC tanpa pemegang" kini DISIMPAN + `peringatan`
  (bukan 422 seperti fixture 2026-10-09).
- **`GET pkl/siswa/excel?kelas_id=&fase=&q=`** (baru) → BERKAS `.xlsx` Status Siswa sesuai saringan — isi & nama berkas sama dengan
  tombol web "Unduh Excel (sesuai saringan)" (`Status PKL Siswa YYYY-MM-DD.xlsx`). Gerbang = modul `pkl` (sama dengan `pkl/siswa`).

## Catatan untuk pembuat aplikasi
1. Setelah login, baca `admin.akses_api`: bila `["pkl"]`, tampilkan HANYA menu PKL (jangan panggil API lain → 403).
2. Tombol per ajuan dibaca dari `data.hak` pada detail; jangan menebak dari peran.
3. Setelah aksi apa pun, muat ulang detail (status bisa sudah diubah staf lain → `409`, pesan sudah ramah).
4. Tampilkan `batas_keputusan`/`sisa_hari`/`terlambat` pada antrean (aturan: Waka Hubin memutuskan ≤ `batas_hari` hari sejak dikirim).
5. Pesan galat dari server berbahasa Indonesia sederhana dan boleh langsung ditampilkan ke pengguna.

## Pengujian
**2026-10-09 (hak/biaya/WhatsApp/laporan):** `php spark dev:uji-pkl-biaya` (124 cek: hak & alamat, gerbang biaya, beasiswa, keringanan, koreksi, nomor 3 angka, titik gelar di Word, WhatsApp, laporan & Excel);
skrip HTTP+API sementara (71 cek: tiap peran lewat web & Bearer, rute dijaga hak, unduh berkas, rantai CSRF penandaan WhatsApp, hak berubah langsung); uji peramban Chrome (29 cek: dialog biaya, unduhan sungguhan,
dialog WhatsApp, sidebar). Regresi `php spark dev:uji-pkl` 322/322. Skrip uji lama:

`node uji_api_pkl.mjs` (skrip di folder sementara sesi, bukan di repo) — 109 cek HTTP: login 3 peran, gerbang alamat, seluruh matriks
hak di atas, ACC/tolak/batal/massal, surat satu & massal (+ cek gambar tanda tangan hanya pada ACC Hubin), tanda tangan, hapus,
status siswa, profil & sandi sementara, serta jejak riwayat & Audit Log. Lulus 109/109 pada 2026-10-07; data ujinya dibersihkan sendiri.
Contoh respons nyata untuk tim Flutter: `C:lutter-muslimin	estixturespkl`; kontrak sisi aplikasi: `C:lutter-musliminBLUEPRINT-PKL.md`.
