# API Catat Keterlambatan Siswa (Android) — `/api/v1/keterlambatan…`

Dibuat 2026-10-19 (Guru Piket **G5**). Cermin menu web **KURIKULUM → Guru Piket → Catat Keterlambatan** (`admin/keterlambatan`)
dan **Rekap Keterlambatan** (`admin/keterlambatan/rekap`) untuk aplikasi Android. Layar Flutter BELUM dibangun — dokumen ini
kontraknya (untuk AI/pengembang Android).

## Prinsip
- Controller tipis `App\Controllers\Api\Keterlambatan`; SEMUA aturan dari library yang sama dengan web:
  `Libraries\Keterlambatan` (cari siswa, catat, ubah, hapus, daftar, rentang, rekap, guru piket hari itu),
  `Libraries\KeterlambatanExcel` (Excel buku piket format sekolah + Excel rekap), `Libraries\KeterlambatanWa` (kabar WhatsApp
  orang tua, isi nomor HP orang tua, templat pesan). Hasil web = hasil aplikasi.
- Audit Log memakai akhiran **"(via aplikasi)"**.
- Amplop JSON standar semua API: `{ "status": "success"|"error", "ok": bool, "message": string, "data": any, "meta"?: object }`.
- Migrasi: `2026-10-22-000001_KeterlambatanSiswa` (tabel `keterlambatan_siswa`, kolom `settings.wa_pesan_terlambat`, peran
  `guru_piket`). Sebelum migrasi dijalankan semua endpoint ini galat 500 (tabel belum ada).

## Gerbang & hak
- `ApiAuthFilter` (token Bearer dari `POST auth/login`): peran harus **boleh aplikasi** (Sistem → Hak Akses) dan memegang menu
  **Catat Keterlambatan** (awalan API `keterlambatan`) atau hanya **Rekap Keterlambatan** (awalan `keterlambatan/rekap`). Admin `*`.
  Alamat lain → **403**; tanpa/kedaluwarsa token → **401**.
- Peran bawaan **Guru Piket** (`guru_piket`, akun bersama di meja piket): boleh aplikasi; Kelola Catat Keterlambatan, Lihat Rekap,
  Lihat Jadwal Piket. Operator & Waka Hubin bawaan: tidak punya akses.
- Hak per aksi dijaga controller dengan aturan web yang SAMA (`HakAkses::boleh` pada alamat web padanannya):

| Aksi | Butuh | Padanan web |
|---|---|---|
| meta, daftar, detail, cari siswa, Excel harian | Lihat Catat Keterlambatan | GET `admin/keterlambatan` |
| catat, ubah, hapus, kabari, isi nomor HP | **Kelola** Catat Keterlambatan | POST `admin/keterlambatan…` |
| rekap, Excel rekap, Excel buku piket rentang | Lihat Rekap Keterlambatan | GET `admin/keterlambatan/rekap` |
| ubah templat pesan WA | **khusus Admin** | POST `admin/keterlambatan/pesan` |

- `meta.data.hak` membawa hak persis peran ini — aplikasi menyembunyikan tombol yang `false`, tidak perlu menebak dari nama peran.

## Endpoint

| Metode | Alamat | Isi / catatan |
|---|---|---|
| GET | `keterlambatan/meta?tanggal=` | `hari_ini`, `tanggal`, `hari_tanggal`, `tahun_ajaran`, `guru_piket` {pagi[], siang[]} (jadwal piket hari itu, {id,nama}), `guru[]` (semua pilihan guru piket), `kelas[]` {id,nama}, `saran_sanksi[]`, `batas` {sanksi:150, keterangan:255, rentang_hari:366, cari_min:2, baris_excel:50}, `hak` {lihat, catat, ubah, hapus, kabari, isi_hp, excel, rekap, ubah_pesan}, `wa` {templat, bawaan, penanda[], maks:600} |
| GET | `keterlambatan?tanggal=YYYY-MM-DD` | catatan satu tanggal (bawaan hari ini; tanggal tak sah/masa depan → hari ini), urut jam datang (tanpa jam di akhir): `tanggal`, `hari_tanggal`, `guru_piket`, `jumlah`, `baris[]` |
| GET | `keterlambatan/{id}` | satu `baris`; tidak ada → 404 |
| GET | `keterlambatan/siswa?q=&tanggal=` | siswa **aktif** yang namanya memuat `q` atau NIS/NISN diawali `q` (≥ 2 huruf, maks 15): `[{id, nama, nis, kelas, shift, tercatat}]` — `tercatat` = `"jam 07:20"`/`"sudah dicatat"` bila siswa itu sudah dicatat pada tanggal itu (tampilkan redup, tidak bisa dipilih), selain itu `null` |
| POST | `keterlambatan` | `{siswa_id*, guru_id*, tanggal? (bawaan hari ini), jam? "07:15", sanksi?, keterangan?}` → **201** `baris`. Ganda (siswa sama, tanggal sama) → **409**; isian tak sah → **422** |
| POST | `keterlambatan/{id}/ubah` | `{guru_id*, jam?, sanksi?, keterangan?}` → `baris`. Siswa & tanggal TIDAK bisa diubah (salah siswa → hapus lalu catat ulang). 404/422 |
| DELETE | `keterlambatan/{id}` | → `{id, tanggal}`; tidak ada → 404 |
| POST | `keterlambatan/{id}/kabari` | panggil SESUDAH membuka `baris.wa.url` → `{id, dikabari_at, dikabari_oleh}` |
| POST | `keterlambatan/{id}/hp` | `{hp}` — isi nomor HP orang tua ke **Data Siswa** (hanya kolom yang masih kosong: Telepon orang tua, lalu HP wali; nomor yang sudah ada TIDAK ditimpa) → `baris` terbaru (kini `wa.url` terisi). Nomor bukan HP 08… → 422; data siswa sudah punya nomor HP → 409 |
| POST | `keterlambatan/pesan` | **Admin**: `{wa_pesan}` templat (kosong = bawaan) → `{templat, bawaan: bool}`; penanda asing / > 600 huruf → 422; peran lain → 403 |
| GET | `keterlambatan/excel?tanggal=` | berkas **.xlsx** buku piket hari itu (format sekolah, selalu 1 lembar, min 50 baris) |
| GET | `keterlambatan/rekap?mulai=&selesai=&kelas_id=` | bawaan awal bulan s.d. hari ini, maks 366 hari (disesuaikan + alasan di `message`): `mulai`, `selesai`, `kelas_id`, `ringkas` {total, jumlah_siswa, jumlah_hari}, `siswa[]` {nama, nis, kelas, jumlah, tanggal[], terakhir} (terbanyak dulu), `kelas[]` {kelas, jumlah, siswa} |
| GET | `keterlambatan/rekap/excel?…` | berkas .xlsx rekap (lembar "Per siswa" & "Per kelas") |
| GET | `keterlambatan/rekap/buku?…` | berkas .xlsx buku piket rentang (satu lembar per hari yang ada catatannya); tidak ada catatan → **404 JSON** |

Rute statis (`meta`, `siswa`, `excel`, `rekap…`, `pesan`) didaftarkan SEBELUM pola `{id}`.

### Bentuk `baris`
```json
{
  "id": 12, "tanggal": "2026-10-19", "hari_tanggal": "Senin, 19 Oktober 2026", "jam": "07:25",
  "siswa_id": 431, "nama_siswa": "ADE FIRMANSYAH", "nis": "262710001", "kelas_id": 1, "nama_kelas": "X TKJ 1", "shift": "pagi",
  "tahun_ajaran": "2026/2027", "sanksi": "Menyapu halaman", "keterangan": null,
  "guru_id": 7, "guru_piket": "Iin Mutmainah, S.Kom", "dicatat_oleh": "Guru Piket", "dicatat_at": "2026-10-19 07:26:03",
  "dikabari_at": null, "dikabari_oleh": null,
  "wa": {
    "hp": "081244445555", "sumber": "orang tua",
    "url": "https://wa.me/6281244445555?text=Yth.%20Bapak%2FIbu%20orang%20tua%2Fwali%20dari%20Ade%20Firmansyah%20...",
    "pesan": "Yth. Bapak/Ibu orang tua/wali dari Ade Firmansyah (X TKJ 1). Kami informasikan bahwa ananda datang terlambat ke sekolah pada Senin, 19 Oktober 2026 pukul 07.25. ...",
    "alasan_tidak": null, "bisa_isi_hp": false
  }
}
```
- `nama_siswa`, `nama_kelas`, `shift`, `tahun_ajaran`, `guru_piket` = SALINAN saat dicatat (tidak berubah walau siswa naik kelas).
  `siswa_id`/`kelas_id`/`guru_id` bisa `null` bila datanya sudah dihapus.
- `wa.url = null` → tampilkan `wa.alasan_tidak` ("Nomor HP orang tua belum ada" / "Nomor HP orang tua tidak valid" /
  "Data siswa sudah tidak ada"); bila `wa.bisa_isi_hp` → tombol **Isi no. ortu**.
- `wa.sumber`: `"orang tua"` (kolom Telepon orang tua) atau `"wali"` (HP wali). HP siswa sendiri tidak pernah dipakai.

### Contoh galat
```json
// 409 — POST keterlambatan (siswa sama, tanggal sama)
{ "status": "error", "ok": false, "message": "ADE FIRMANSYAH sudah tercatat terlambat pada Senin, 19 Oktober 2026 (jam 07:25). Ubah catatan yang ada bila perlu.", "data": { "errors": [] } }
// 422 — tanpa guru piket
{ "status": "error", "ok": false, "message": "Pilih guru piket yang mencatat.", "data": { "errors": [] } }
// 403 — peran tanpa hak
{ "status": "error", "ok": false, "message": "Akun Operator Sekolah tidak punya hak melihat catatan keterlambatan. Admin bisa mengaturnya di Sistem → Hak Akses.", "data": null }
```

## Aturan (sama dengan web)
- Satu siswa paling banyak **satu catatan per tanggal**; tanggal tidak boleh di masa depan (tanggal lampau boleh).
- Yang bisa dicatat hanya siswa **aktif**; nama/kelas/shift/tahun ajaran disalin.
- **Guru piket wajib dipilih** (akun Guru Piket dipakai bersama). Saran = `meta.guru_piket` (jadwal piket hari itu, pagi/siang);
  boleh guru lain dari `meta.guru`. Di web, pilihan terakhir diingat sampai ganti hari — aplikasi sebaiknya menyimpan sendiri
  (mis. SharedPreferences ber-tanggal).
- `jam` "H:MM"/"HH:MM"/"HH.MM" (boleh kosong); sanksi ≤ 150 huruf, keterangan ≤ 255 huruf, teks bebas (spasi ganda dirapikan).
- Kabar WhatsApp **manual**: aplikasi membuka `wa.url` (WhatsApp di HP guru piket, tinggal tekan Kirim) lalu memanggil
  `POST {id}/kabari`. Tanpa gateway, tanpa biaya.
- Templat pesan: penanda `{nama} {kelas} {tanggal} {jam} {waktu} {sanksi} {keterangan} {guru_piket} {sekolah}`; `{waktu}` =
  hari, tanggal + " pukul HH.MM" bila jam ada. Hanya Admin yang mengubah.

## Unduhan Excel
- Jawaban sukses = biner `.xlsx` (`Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`), nama berkas
  di `Content-Disposition` ("Buku Piket Keterlambatan 2026-10-19.xlsx", "… 2026-10-01 sd 2026-10-19.xlsx", "Rekap Keterlambatan
  … sd ….xlsx"). Simpan ke penyimpanan HP lalu buka/bagikan.
- Gagal = JSON beramplop (403/404) — periksa `Content-Type` sebelum menyimpan.
- Isi buku piket PERSIS format sekolah (judul 3 baris, HARI/TANGGAL, GURU PIKET, NO | HARI/TANGGAL | NAMA | KELAS | SANKSI |
  KETERANGAN, min 50 baris, Times New Roman, Legal tegak) — lihat `Libraries\KeterlambatanExcel`.

## Saran layar Flutter (belum dibangun)
1. **Catat** (beranda akun Guru Piket): pilih guru piket (saran jadwal hari ini di atas) → kotak cari siswa (`siswa?q=`, ≥ 2 huruf,
   jeda ±300 md) → jam (isi jam sekarang) → sanksi (saran `meta.saran_sanksi`) → keterangan → Simpan (`POST keterlambatan`).
2. **Daftar hari ini** (`GET keterlambatan`): kartu per siswa; tombol hijau **Kabari ortu** (`wa.url` → buka → `POST kabari`),
   status "Sudah dikabari {waktu} oleh {nama}", **Isi no. ortu** bila `wa.bisa_isi_hp`, Ubah, Hapus (konfirmasi); pilih tanggal lain.
3. **Rekap** (`GET keterlambatan/rekap`): rentang + kelas, ringkasan, tab Per siswa / Per kelas, unduh Excel.
4. **Unduh**: Excel hari ini, buku piket rentang, rekap.
5. **Pesan WA** (hanya bila `hak.ubah_pesan`): teks templat + daftar penanda + kembali ke bawaan (kirim kosong).

## Pengujian
- `php spark dev:uji-telat` — 91 cek (hak & gerbang, pustaka, Excel dibanding langsung dengan contoh sekolah, WA, halaman web,
  dan bagian **API**: meta, daftar, cari, catat 201/409/422, ubah, hapus, kabari, isi nomor, templat Admin-only, Excel, rekap,
  Audit "(via aplikasi)"). Data uji & nomor HP siswa dikembalikan otomatis.
- Uji HTTP lewat token sungguhan (2026-10-19): login akun Guru Piket → `keterlambatan/*` 200, Excel `PK…`; `surat`, `pkl/ajuan`,
  `admin/master/siswa`, `admin/dashboard` → 403; `POST keterlambatan/pesan` → 403; tanpa token → 401.
