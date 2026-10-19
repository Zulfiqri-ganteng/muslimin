# API pendukung rombak tampilan Android — beranda publik (B1) & menu per akun (B2)

Dibuat 2026-10-19 untuk `C:\flutter-muslimin\BLUEPRINT-TAMPILAN.md` (rombak total aplikasi: sebelum login = konsep portal
rumah.pendidikan.go.id; sesudah login = panel admin seperti web). Contoh respons NYATA: `C:\flutter-muslimin\test\fixtures\publik\`
& `…\fixtures\menu\`. Uji: `php spark dev:uji-api-android` (19 cek). Tanpa migrasi.

Prinsip: **sumber data SAMA dengan web** → apa yang Admin ubah di web (foto sekolah, buka/tutup formulir, pengumuman, hak akses) langsung
sama di aplikasi. Logika bersama yang dipakai web & API: `LayananPublik::semua/kelompok/untukKelompok/unggulan/tanyaJawab/tentang`,
`MenuAdmin::sidebar/namaGrup`, `HakAkses::boleh/levelAlamat`.

---

## B1 — `GET publik/beranda` (TANPA token)
`App\Controllers\Api\Publik::beranda`. Satu panggilan berisi semua bahan beranda + halaman kelompok + profil:

| Kunci | Isi |
|---|---|
| `sekolah` | `nama, jenjang, logo (URL\|null), tahun_pelajaran, tentang` (profil singkat Pengaturan; kosong → kalimat faktual, sama dengan web), `alamat, kota, kode_pos, telepon, email, website, akreditasi, kepala_sekolah, wa_bantuan {nomor, url wa.me}\|null, tautan_aplikasi` |
| `sorotan[]` | isi banner geser, urut web: `jenis` `foto` (foto bertanda banner: `judul, teks, gambar, tautan`) → `sambutan` (`label, judul, teks, ilustrasi, aksi{label, layanan:null}` = gulir ke "Jelajahi layanan") → `layanan` (hanya yang SEDANG DIBUKA: `label, judul, teks, ilustrasi, tautan, aksi{label, layanan: kunci}`) |
| `foto[]` | semua foto sekolah aktif yang berkasnya ada: `id, judul, keterangan, gambar (URL), tautan, banner (bool)` — galeri Profil; foto "Tentang sekolah" = yang pertama dengan `banner:false` (cadangan foto pertama) |
| `kelompok[]` | 4 ubin "Jelajahi layanan": `kunci` (`siswa`/`guru`/`orang-tua`/`sekolah`), `label, ringkas, ket, ikon` (Flowbite), `ilustrasi` (URL SVG), `url_web`, `bagian[] {judul, layanan[kunci]}` = isi halaman kelompok (Profil Sekolah: `[]` → buka layar Profil) |
| `layanan[]` | semua layanan yang tampil, urut katalog: `kunci, judul, ket, ikon, ilustrasi, url, luar, kelompok[], bagian{kelompok: judul}, status` (`"buka"` / `"tutup"` = tampilkan label "Sedang ditutup" / `null` = selalu ada) |
| `unggulan[]` | kunci layanan untuk kartu "Layanan unggulan" (maks 6; yang tertutup di belakang) |
| `statistik` | `{guru, kelas, mapel, siswa}` (agregat, cache 30 menit) |
| `grafik_siswa` | agregat saja: `total, per_tingkat{X,XI,XII}, per_jurusan[{kode,nama,jumlah}], jenis_kelamin{L,P}, per_tahun{tahun:jumlah}, tahun` — TANPA identitas siswa |
| `pengumuman[]` | maks 5 aktif terbaru: `id, judul, kategori` (Pengumuman/Berita/Agenda/Prestasi), `ringkas` (≤150), `isi, gambar (URL\|null → pakai logo), tanggal ("19 Oktober 2026"), created_at, url_web` |
| `tanya_jawab[]` | `{tanya, jawab}` (pertanyaan layanan yang dimatikan tidak ikut) |
| `jadwal` | `{buka, kelas[{id,nama}]}` — pemilih "Cek jadwal"; `buka:false` → sembunyikan |
| `saklar` | `{form_open, jadwal_publik, absensi_publik, dokumen_publik}` |
| `sekarang` | `{hari_id, hari_nama, jam_ids[], label ("Jam ke-3 (08:30–09:15)"\|null), waktu}` |
| `web` | URL halaman web: `beranda, bantuan, profil, pengumuman` |

Kunci layanan (katalog `LayananPublik::semua`, contoh laptop 2026-10-19): `pkl`, `lapor_pkl`, `biodata`, `jadwal_kelas`, `jadwal_guru`,
`absensi`, `kesediaan`, `pengumuman`, `masuk`, `profil`, `bantuan`; + `dokumen` bila "dokumen publik" dinyalakan dan `aplikasi` bila
tautan aplikasi diisi (layanan yang saklarnya mati tidak ikut). Yang sudah punya layar aplikasi (jadwal kelas/guru `jadwal/*`, absensi
`absensi`, form kesediaan `form/*`, `masuk` = layar login, `profil` = layar Profil dari data B1, `pengumuman` = daftar dari B1) dibuka di
aplikasi; sisanya (`pkl`, `lapor_pkl`, `biodata`, `dokumen`, `bantuan`, …) dibuka di peramban dalam aplikasi memakai `url`. API lama
`GET home` tetap ada (tidak diubah).

## B2 — `GET auth/menu` (token; terbuka untuk semua peran yang boleh aplikasi)
`App\Controllers\Api\Auth::menu`. Menu panel admin akun ini = **menu samping web** (`MenuAdmin::sidebar()`), disaring aturan hak yang
sama dengan web (`HakAkses::boleh`):
```json
{"peran":"guru_piket","peran_label":"Guru Piket","akses_api":["keterlambatan","keterlambatan/rekap"],
 "grup":[{"judul":"Guru Piket","bagian":"KURIKULUM","ikon":"clock","item":[
    {"kunci":"absensi_piket","label":"Jadwal Piket","ikon":"calendar-plus","alamat_web":"admin/absensi/piket","segera":false,"level":"lihat","api":[]},
    {"kunci":"keterlambatan","label":"Catat Keterlambatan","ikon":"user-edit","alamat_web":"admin/keterlambatan","segera":false,"level":"kelola","api":["keterlambatan"]},
    {"kunci":"keterlambatan_rekap","label":"Rekap Keterlambatan","ikon":"chart","alamat_web":"admin/keterlambatan/rekap","segera":false,"level":"lihat","api":["keterlambatan/rekap"]}]},
  {"judul":"Sistem","bagian":"","ikon":"cog","item":[{"kunci":"profil","label":"Profil Saya", "...":"…"}]}]}
```
- `grup[]` urut web; `judul` sudah ditulis seperti web ("Uji Kompetensi (UKK)", "PKL / Prakerin"; `null` = baris tunggal Dashboard);
  `bagian` = judul bagian di atas grup (`"KURIKULUM"`, `"LAYANAN SEKOLAH"`, `""` = garis pemisah saja, `null` = tanpa bagian).
  Tampilkan judul bagian setiap kali `bagian` berubah dari grup sebelumnya.
- `item.kunci` = kunci katalog `MenuAdmin` (stabil — pakai untuk memetakan ke layar aplikasi); `ikon` = nama Flowbite Icons;
  `segera:true` = fitur belum dibangun di web (tampil redup "Segera"); `level` = `lihat` (hanya melihat) / `kelola` / `boleh` (modul
  ber-hak rinci, mis. PKL & Surat — tombol mengikuti `hak_*`).
- `item.api` = awalan API milik menu itu. **Admin** (`akses_api: ["*"]`) memakai seluruh API admin. **Peran lain** hanya bisa memakai
  menu yang `api`-nya tidak kosong; menu ber-`api: []` untuk peran selain Admin → tampilkan "Buka di web" (situs) atau sembunyikan.
- Menu yang layarnya BELUM ada di aplikasi → aplikasi menampilkannya redup "Segera" (aturan user).
- Tanpa token → 401. Galat lain tidak ada (peran tanpa menu → `grup` hanya berisi Sistem/Profil).

## Pengujian
`php spark dev:uji-api-android` — B1: semua bagian, nama & teks Tentang = sumber web, layanan & status sama dengan web, kelompok/bagian,
unggulan, sorotan hanya layanan dibuka, foto, pengumuman, tanya-jawab, statistik agregat, saklar jadwal (dimatikan → layanan jadwal
hilang & kelas kosong; dikembalikan). B2: Admin = semua grup web + bagian + judul + item Segera + kunci/ikon/level/api; Guru Piket =
hanya Guru Piket + Profil; Operator = Kesiswaan, PKL, Surat, Arsip, Sistem; Waka Hubin = PKL, Surat, Sistem; semua item boleh dibuka.
HTTP sungguhan (2026-10-19): `publik/beranda` 200, `auth/menu` 200 untuk 4 peran, tanpa token 401.
