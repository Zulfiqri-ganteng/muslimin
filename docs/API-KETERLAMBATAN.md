# API Catat Keterlambatan Siswa (Android) — `/api/v1/keterlambatan…`

Dibuat 2026-10-19 (Guru Piket **G5**). Cermin menu web **KURIKULUM → Guru Piket → Catat Keterlambatan** (`admin/keterlambatan`)
dan **Rekap Keterlambatan** (`admin/keterlambatan/rekap`) untuk aplikasi Android. Kontrak sisi Flutter (layar, tahap T0–T6, jebakan):
`C:\flutter-muslimin\BLUEPRINT-KETERLAMBATAN.md` + contoh respons nyata (data fiktif) `C:\flutter-muslimin\test\fixtures\keterlambatan\`
(T6 siswa manual: subfolder `manual\`). Tampilan: `C:\flutter-muslimin\BLUEPRINT-TAMPILAN.md`.

> **PEMBARUAN 2026-10-19 malam (K1 — keluhan klien): siswa BELUM TERDAFTAR di Data Siswa bisa dicatat MANUAL** (nama diketik, kelas
> dipilih, HP orang tua opsional) dan kemudian **ditautkan** ke Data Siswa. Rincian: bagian **"Siswa belum terdaftar (manual)"** di bawah.
> Semua tambahan bersifat **menambah** (kunci baru, parameter opsional) — aplikasi versi lama tetap jalan. Butuh migrasi
> `2026-10-23-000001_KeterlambatanManual` (`phpm spark migrate`).

> **PEMBARUAN 2026-10-20 (permintaan klien — guru piket kurang lancar mengetik): "Pilih dari kelas".** Di bawah kotak cari ada
> tombol kelas; tekan kelas → semua nama siswanya muncul → tekan nama. API: **`GET keterlambatan/siswa?kelas_id=`** (lihat tabel
> endpoint + bagian "Pilih dari kelas"). `meta.kelas[]` kini `{id, nama, tingkat, jumlah_siswa}` dan urut tingkat lalu nama alami
> ("X TKJ 2" sebelum "X TKJ 10"). **Perbaikan:** `POST {id}/ubah` — `jam`/`sanksi`/`keterangan` yang TIDAK dikirim kini tetap
> (dulu ikut terhapus); Excel buku piket hari Selasa/Jum'at/Minggu tidak lagi membuat baris data dua kali tinggi. Semua bersifat
> menambah — aplikasi lama tetap jalan; tanpa migrasi (cukup `git pull`).

## Prinsip
- Controller tipis `App\Controllers\Api\Keterlambatan`; SEMUA aturan dari library yang sama dengan web:
  `Libraries\Keterlambatan` (cari siswa, catat, ubah, hapus, daftar, rentang, rekap, guru piket hari itu),
  `Libraries\KeterlambatanExcel` (Excel buku piket format sekolah + Excel rekap), `Libraries\KeterlambatanWa` (kabar WhatsApp
  orang tua, isi nomor HP orang tua, templat pesan). Hasil web = hasil aplikasi.
- Audit Log memakai akhiran **"(via aplikasi)"**.
- Amplop JSON standar semua API: `{ "status": "success"|"error", "ok": bool, "message": string, "data": any, "meta"?: object }`.
- Migrasi: `2026-10-22-000001_KeterlambatanSiswa` (tabel `keterlambatan_siswa`, kolom `settings.wa_pesan_terlambat`, peran
  `guru_piket`) + `2026-10-23-000001_KeterlambatanManual` (kolom `manual`, `hp_ortu`). Tanpa migrasi pertama semua endpoint ini galat
  500 (tabel belum ada); tanpa migrasi kedua, cari siswa & catat manual galat 500 (kolom belum ada).

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
| catat (termasuk siswa manual), ubah, hapus, kabari, isi nomor HP, tautkan | **Kelola** Catat Keterlambatan | POST `admin/keterlambatan…` |
| rekap, Excel rekap, Excel buku piket rentang | Lihat Rekap Keterlambatan | GET `admin/keterlambatan/rekap` |
| ubah templat pesan WA | **khusus Admin** | POST `admin/keterlambatan/pesan` |

- `meta.data.hak` membawa hak persis peran ini — aplikasi menyembunyikan tombol yang `false`, tidak perlu menebak dari nama peran.

## Endpoint

| Metode | Alamat | Isi / catatan |
|---|---|---|
| GET | `keterlambatan/meta?tanggal=` | `hari_ini`, `tanggal`, `hari_tanggal`, `tahun_ajaran`, `guru_piket` {pagi[], siang[]} (jadwal piket hari itu, {id,nama}), `guru[]` (semua pilihan guru piket), `kelas[]` {id, nama, **tingkat, jumlah_siswa**} (urut tingkat lalu nama alami; juga pilihan kelas siswa manual & tombol "Pilih dari kelas"), `saran_sanksi[]`, `batas` {sanksi:150, keterangan:255, rentang_hari:366, cari_min:2, baris_excel:50, **nama_min:3, nama:150**}, `hak` {lihat, catat, ubah, hapus, kabari, isi_hp, **tautkan**, excel, rekap, ubah_pesan}, `wa` {templat, bawaan, penanda[], maks:600} |
| GET | `keterlambatan?tanggal=YYYY-MM-DD` | catatan satu tanggal (bawaan hari ini; tanggal tak sah/masa depan → hari ini), urut jam datang (tanpa jam di akhir): `tanggal`, `hari_tanggal`, `guru_piket`, `jumlah`, `baris[]` |
| GET | `keterlambatan/{id}` | satu `baris`; tidak ada → 404 |
| GET | `keterlambatan/siswa?q=&tanggal=&manual=1` | siswa **aktif** yang namanya memuat `q` atau NIS/NISN diawali `q` (≥ 2 huruf, maks 15): `[{id, manual:false, nama, nis, kelas, kelas_id, shift, tercatat}]` — `tercatat` = `"jam 07:20"`/`"sudah dicatat"` bila siswa itu sudah dicatat pada tanggal itu (tampilkan redup, tidak bisa dipilih), selain itu `null`. **`manual=1`**: + di AKHIR daftar, maks 5 siswa **belum terdaftar** yang pernah dicatat manual: `{id:null, manual:true, nama, nis:"", kelas, kelas_id, shift, tercatat, jumlah, hp_ortu}` |
| GET | `keterlambatan/siswa?kelas_id=&tanggal=&manual=1` | **baru 2026-10-20** — SEMUA siswa aktif kelas itu, urut nama (maks 200), bentuk baris SAMA dengan cari (`q` diabaikan). `message` = `"38 siswa aktif di XII AKL."`. **`manual=1`**: + di AKHIR siswa belum terdaftar yang pernah dicatat manual di kelas itu **tahun ajaran ini**. Kelas tidak ada/terhapus → **404** |
| POST | `keterlambatan` | `{siswa_id*, guru_id*, tanggal? (bawaan hari ini), jam? "07:15", sanksi?, keterangan?}` → **201** `baris`. Ganda (siswa sama, tanggal sama) → **409**; isian tak sah → **422**. **Siswa belum terdaftar:** `{manual:true, nama_siswa*, kelas_id*, hp_ortu?, guru_id*, tanggal?, jam?, sanksi?, keterangan?}` (lihat bagian manual) |
| POST | `keterlambatan/{id}/ubah` | `{guru_id*, jam?, sanksi?, keterangan?}` → `baris`. Kunci `jam`/`sanksi`/`keterangan` yang **tidak dikirim = tetap**, `""` = dikosongkan (sejak 2026-10-20; dulu tidak dikirim = terhapus). Siswa terdaftar & tanggal TIDAK bisa diubah (salah siswa → hapus lalu catat ulang). Catatan manual: + `nama_siswa?`, `kelas_id?`, `hp_ortu?` (kunci tak dikirim = tetap). 404/409/422 |
| DELETE | `keterlambatan/{id}` | → `{id, tanggal}`; tidak ada → 404 |
| POST | `keterlambatan/{id}/kabari` | panggil SESUDAH membuka `baris.wa.url` → `{id, dikabari_at, dikabari_oleh}` |
| POST | `keterlambatan/{id}/hp` | `{hp}` — isi nomor HP orang tua ke **Data Siswa** (hanya kolom yang masih kosong: Telepon orang tua, lalu HP wali; nomor yang sudah ada TIDAK ditimpa) → `baris` terbaru (kini `wa.url` terisi). Nomor bukan HP 08… → 422; data siswa sudah punya nomor HP → 409. **Catatan manual:** nomor disimpan di catatan itu (`baris.hp_ortu`); sudah ada → 409 (ganti lewat ubah) |
| POST | `keterlambatan/{id}/tautkan` | **baru** — `{siswa_id*, semua?: bool (bawaan true)}`: catatan siswa belum terdaftar → siswa di Data Siswa → `{jumlah, ids[], dilewati[] (tanggal), baris}`. Bukan catatan manual / siswa_id salah → 422; semua tanggal bentrok → 409 |
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
- **`manual`** (bool, baru): `true` = siswa belum terdaftar (nama & kelas diketik guru piket; `siswa_id` & `nis` null). **`hp_ortu`**
  (baru): nomor HP orang tua yang diisi guru piket — hanya untuk catatan manual, selain itu `null`.
- `wa.url = null` → tampilkan `wa.alasan_tidak` ("Nomor HP orang tua belum ada" / "Nomor HP orang tua tidak valid" /
  "Data siswa sudah tidak ada"); bila `wa.bisa_isi_hp` → tombol **Isi no. ortu** (kini juga untuk catatan manual tanpa nomor).
- `wa.sumber`: `"orang tua"` (kolom Telepon orang tua), `"wali"` (HP wali), atau `"isian guru piket"` (catatan manual, dari
  `hp_ortu`). HP siswa sendiri tidak pernah dipakai.

## Siswa belum terdaftar (manual) — K1, 2026-10-19 malam
Keluhan klien: guru piket tidak bisa memilih anak yang namanya belum ada di Data Siswa (mis. siswa pindahan / data belum diimpor).
- **Catat:** `POST keterlambatan` dengan `manual: true` (juga diterima `1`, `"1"`, `"ya"`, `"true"`), `nama_siswa` (3–150 huruf; boleh
  titik, koma, petik, tanda hubung; disimpan **HURUF BESAR** dengan spasi dirapikan, seperti Data Siswa), `kelas_id` (dari `meta.kelas`),
  `hp_ortu` opsional (HP 08…, disimpan `08…` tanpa pemisah). `siswa_id` diabaikan. Galat 422 membawa `data.errors.{nama_siswa|kelas_id|
  hp_ortu}`.
- **Nama itu ternyata siswa aktif terdaftar di kelas yang sama** (tidak beda huruf besar/kecil) → **422** + `data.siswa_terdaftar`
  `{id, nama, nis, kelas, kelas_id}` — aplikasi sebaiknya menawarkan "Pakai siswa ini" (catat ulang dengan `siswa_id`).
- Satu **nama + kelas** paling banyak satu catatan per tanggal → **409**. Nama sama di kelas lain boleh (bisa anak lain).
- **Cari:** `GET keterlambatan/siswa?…&manual=1` menambahkan saran siswa belum terdaftar yang pernah dicatat (dikelompokkan per nama +
  kelas, `jumlah` = berapa kali, `hp_ortu` = nomor terakhir yang diisi). Memilih saran = isi formulir manual dengan ejaan, kelas, dan
  nomor yang sama (agar rekap menyatu). Saran ber-`tercatat` ≠ null = sudah dicatat hari itu (redup).
- **Ubah:** catatan manual boleh dibetulkan nama/kelas/HP-nya (`POST {id}/ubah` + `nama_siswa`/`kelas_id`/`hp_ortu`). Mengganti nama
  menjadi nama siswa terdaftar di kelas yang sama → 422 "pakai tombol Tautkan". Mengubah jam/sanksi saja tidak pernah ditolak.
- **Tautkan** (setelah anak itu dimasukkan Admin ke Data Siswa): `POST {id}/tautkan {siswa_id, semua}` — `semua` (bawaan `true`) ikut
  menautkan catatan manual lain dengan nama + kelas sama. Hasilnya catatan biasa: `manual=false`, `siswa_id` terisi, nama dari Data
  Siswa; kelas dari Data Siswa bila catatan itu tahun ajaran sekarang (catatan tahun lalu tetap kelas lamanya). Tanggal yang siswa itu
  SUDAH punya catatan dilewati (`dilewati[]`). Nomor `hp_ortu` diisikan ke Data Siswa bila di sana masih kosong (aturan sama dengan
  Isi no. ortu). `baris` = catatan `{id}` sesudah ditautkan (`null` bila justru catatan itu yang dilewati).
- **Rekap:** `siswa[]` kini membawa `manual` (bool); siswa manual dikelompokkan per nama + kelas, `nis` kosong. Excel rekap menulis
  "belum terdaftar" di kolom NIS. **Excel buku piket tidak berubah** (format sekolah; nama & kelas tertulis seperti biasa).
- Web: tombol "Siswa belum terdaftar? Tulis manual" di bawah kotak cari, lencana kuning **Belum terdaftar** di daftar & rekap, tombol
  **Tautkan** (dialog cari Data Siswa) pada catatan manual.

```json
// 201 — POST keterlambatan {"manual":true,"nama_siswa":"eko  contoh wibowo","kelas_id":56,"hp_ortu":"0812-3456-7802","guru_id":862,"jam":"07:15"}
{ "status": "success", "ok": true, "message": "EKO CONTOH WIBOWO (X AKL) dicatat terlambat — ditandai \"belum terdaftar\" di Data Siswa.",
  "data": { "id": 107, "siswa_id": null, "manual": true, "hp_ortu": "081234567802", "nama_siswa": "EKO CONTOH WIBOWO", "nis": null,
            "kelas_id": 56, "nama_kelas": "X AKL", "...": "…",
            "wa": { "hp": "081234567802", "sumber": "isian guru piket", "url": "https://wa.me/6281234567802?text=…", "alasan_tidak": null, "bisa_isi_hp": false } } }
// 422 — nama ternyata siswa terdaftar di kelas yang sama
{ "status": "error", "ok": false, "message": "DEDI CONTOH SAPUTRA sudah terdaftar di Data Siswa (X AKL, NIS 9990011) — cari lalu pilih dari hasil pencarian.",
  "data": { "errors": { "nama_siswa": "…" }, "siswa_terdaftar": { "id": 3789, "nama": "DEDI CONTOH SAPUTRA", "nis": "9990011", "kelas": "X AKL", "kelas_id": 56 } } }
// 200 — POST keterlambatan/105/tautkan {"siswa_id":3789,"semua":true}
{ "status": "success", "ok": true, "message": "2 catatan ditautkan ke DEDI CONTOH SAPUTRA (X AKL) di Data Siswa. Nomor HP orang tua 085711112203 disimpan ke Data Siswa.",
  "data": { "jumlah": 2, "ids": [105, 106], "dilewati": [], "baris": { "id": 105, "manual": false, "siswa_id": 3789, "nama_siswa": "DEDI CONTOH SAPUTRA", "...": "…" } } }
```

### Contoh galat
```json
// 409 — POST keterlambatan (siswa sama, tanggal sama)
{ "status": "error", "ok": false, "message": "ADE FIRMANSYAH sudah tercatat terlambat pada Senin, 19 Oktober 2026 (jam 07:25). Ubah catatan yang ada bila perlu.", "data": { "errors": [] } }
// 422 — tanpa guru piket
{ "status": "error", "ok": false, "message": "Pilih guru piket yang mencatat.", "data": { "errors": [] } }
// 403 — peran tanpa hak
{ "status": "error", "ok": false, "message": "Akun Operator Sekolah tidak punya hak melihat catatan keterlambatan. Admin bisa mengaturnya di Sistem → Hak Akses.", "data": null }
```

## Pilih dari kelas — 2026-10-20 (permintaan klien)
Guru piket yang kurang lancar mengetik cukup menekan **kelas** lalu **nama**:
- Tombol kelas dari `meta.kelas[]` — kelompokkan per `tingkat` (X / XI / XII), urutan sudah benar dari server; `jumlah_siswa` boleh
  ditampilkan kecil ("38 siswa"). Kelas `jumlah_siswa: 0` tetap tampil (anak belum terdaftar bisa dicatat manual di kelas itu).
- Tekan kelas → `GET keterlambatan/siswa?kelas_id={id}&tanggal={tanggal}&manual=1` → daftar nama urut abjad. Baris berbentuk SAMA
  dengan hasil cari: `tercatat` ≠ null → redup & tidak bisa dipilih; `manual:true` → memilihnya mengisi formulir manual (seperti saran
  manual di pencarian). Memilih siswa biasa = sama persis dengan memilih hasil cari (`siswa_id` untuk `POST keterlambatan`).
- Tombol "Namanya tidak ada? Tulis manual di kelas ini" → formulir manual dengan `kelas_id` kelas itu sudah terpilih.
- Contoh: `GET keterlambatan/siswa?kelas_id=82&tanggal=2026-10-20&manual=1` →
  `{"ok":true,"message":"38 siswa aktif di XII AKL.","data":[{"id":3101,"manual":false,"nama":"ADE …","nis":"…","kelas":"XII AKL","kelas_id":82,"shift":"pagi","tercatat":null}, …]}`.

## Aturan (sama dengan web)
- Satu siswa paling banyak **satu catatan per tanggal**; tanggal tidak boleh di masa depan (tanggal lampau boleh).
- Yang bisa dicatat dari pencarian hanya siswa **aktif**; nama/kelas/shift/tahun ajaran disalin. Siswa yang belum ada di Data Siswa
  → catat manual (bagian di atas).
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

## Saran layar Flutter (T0–T5 sudah dibangun; T6 siswa manual — lihat BLUEPRINT-KETERLAMBATAN.md §14)
1. **Catat** (beranda akun Guru Piket): pilih guru piket (saran jadwal hari ini di atas) → kotak cari siswa (`siswa?q=&manual=1`, ≥ 2
   huruf, jeda ±300 md; tombol "Siswa belum terdaftar? Tulis manual"; di bawahnya **tombol kelas → daftar nama** (`siswa?kelas_id=`,
   bagian "Pilih dari kelas")) → jam (isi jam sekarang) → sanksi (saran `meta.saran_sanksi`) →
   keterangan → Simpan (`POST keterlambatan`).
2. **Daftar hari ini** (`GET keterlambatan`): kartu per siswa; tombol hijau **Kabari ortu** (`wa.url` → buka → `POST kabari`),
   status "Sudah dikabari {waktu} oleh {nama}", **Isi no. ortu** bila `wa.bisa_isi_hp`, Ubah, Hapus (konfirmasi); pilih tanggal lain.
3. **Rekap** (`GET keterlambatan/rekap`): rentang + kelas, ringkasan, tab Per siswa / Per kelas, unduh Excel.
4. **Unduh**: Excel hari ini, buku piket rentang, rekap.
5. **Pesan WA** (hanya bila `hak.ubah_pesan`): teks templat + daftar penanda + kembali ke bawaan (kirim kosong).

## Pengujian
- `php spark dev:uji-telat` — 129 cek (hak & gerbang, pustaka, Excel dibanding langsung dengan contoh sekolah, WA, halaman web,
  bagian **API**: meta, daftar, cari, catat 201/409/422, ubah, hapus, kabari, isi nomor, templat Admin-only, Excel, rekap,
  Audit "(via aplikasi)"; bagian **siswa manual & tautkan** (28 cek: pustaka, WA, halaman, API); bagian **pilih dari kelas** (10 cek,
  2026-10-20: urutan & jumlah kelas, daftar kelas web/API 200/404/403, ubah tanpa kunci = tetap, Excel hari Selasa)). Data uji &
  nomor HP siswa dikembalikan otomatis.
- Fixture T6 ditangkap 2026-10-19 malam lewat HTTP + token sungguhan (server `php spark serve` :8080; guru/siswa/akun fiktif lalu
  dihapus): `C:\flutter-muslimin\test\fixtures\keterlambatan\manual\`.
- Uji HTTP lewat token sungguhan (2026-10-19): login akun Guru Piket → `keterlambatan/*` 200, Excel `PK…`; `surat`, `pkl/ajuan`,
  `admin/master/siswa`, `admin/dashboard` → 403; `POST keterlambatan/pesan` → 403; tanpa token → 401.
