# API Wali Kelas (`api/v1/wali`) — ditambah 2026-11-07

Sama persis dengan halaman web **Kelas Saya** (`admin/kelas-saya`, `Controllers\Admin\KelasSaya`). Isi dari `Libraries\KelasSayaData`,
akses dari `Libraries\WaliKelas`. **Hanya membaca.** Kontrak layar Android: `C:\flutter-muslimin\BLUEPRINT-WALI-KELAS.md`.

## Siapa yang boleh
- Akun staf dihubungkan ke data guru (`admins.guru_id`, Kelola Akun). Guru yang tercatat sebagai wali di Master Kelas
  (`kelas.wali_kelas_id`) mendapat akses ke kelasnya — **sebagai tambahan di atas perannya** (mis. Waka Hubin yang juga wali kelas).
- Peran baru **Wali Kelas** (`wali_kelas`, migrasi `2026-11-07-000001_WaliKelas`): beranda `admin/kelas-saya`, boleh aplikasi, tanpa menu lain.
- **Admin**: semua kelas (pengawasan).
- Awalan `wali` (dan web `admin/kelas-saya`) ada di `Config\Peran::$umum` → lolos penyaring `apiauth` untuk semua peran yang boleh
  aplikasi; isi dijaga controller (kelas orang lain → 403).

## Menu aplikasi (`GET auth/menu`, B2)
Item baru di grup paling atas: `{kunci: "kelas_saya", label: "Kelas Saya", ikon: "users-group", alamat_web: "admin/kelas-saya", api: []}` —
**hanya** untuk akun yang wali kelas (dan Admin). Akun peran Wali Kelas: beranda = layar Kelas Saya.

## Profil akun (`auth/login`, `auth/me`) — ditambah 2026-11-07 (sesi aplikasi)
`admin.wali_kelas: bool` = akun BUKAN Admin yang mewalikan minimal satu kelas (lewat guru terhubung). Aplikasi memakainya untuk
menampilkan "Kelas Saya" di tab Akun bagi peran lain (Waka Hubin, Operator, Guru Piket) yang juga wali kelas; peran `wali_kelas`
langsung dibuka ke layar Kelas Saya. Admin memakai drawer (`auth/menu`). Server lama tanpa kolom ini → dianggap `false`.

## Endpoint
### `GET wali/kelas`
200 `{admin: bool, kelas: [{id, nama, tingkat, wali|null}], bagian: [{kunci: siswa|pkl|telat|surat, label}]}`. Bukan wali → `kelas: []` +
`message` "Anda belum tercatat sebagai wali kelas. Hubungi Admin sekolah." (layar kosong dengan kalimat itu). 503 bila server belum migrate.

### `GET wali/kelas/{id}`
200 `data`:
```
kelas: {id, nama, tingkat, wali, jurusan}
tahun_ajaran: "2026/2027"|null
ringkasan: {siswa, laki, perempuan, biodata_terisi, tanpa_hp_ortu,
            pkl: {ikut, belum_mengajukan, menunggu, sudah_pkl, sedang, selesai, persen_pkl}|null  (null = tingkat kelas tidak ikut PKL),
            telat_siswa, telat_kejadian, surat}
tautan_biodata: "https://…"   (ajak siswa yang HP orang tuanya kosong mengisi Biodata)
siswa: [{id, nama, jk, nis, nisn, hp, ortu_nama, ortu_hp, wa_ortu (url wa.me berisi salam pembuka wali kelas)|null, biodata: bool,
         pkl: {fase, label, perusahaan, kota, mulai, selesai, pernah_ditolak}|null,
         telat: {jumlah (tahun ajaran ini), terakhir}}]
keterlambatan: [{id, tanggal, jam "HH:MM", siswa_id, nama, sanksi, keterangan, guru_piket, ortu_dikabari}]   (terbaru, maks 100)
surat: [{id, kode, jenis, jenis_label, status, status_label, nomor, tanggal, siswa (nama-nama)}]                (terbaru, maks 100)
```
- `pkl.fase` = rumus **PKL → Status Siswa** (Waka Hubin): `belum | ditolak | menunggu | perbaikan | belum_mulai | sedang | selesai |
  disetujui`; `label` siap tampil (`KelasSayaData::LABEL_PKL`).
- 403 kelas yang bukan diwalikan akun ini; 404 kelas tidak ada.

## Uji
`php spark dev:uji-wali-kelas` (29 cek: W1 akun & akses, W2 isi, W3 API + menu; semua dalam transaksi yang dibatalkan).
`--fixture <folder>` → `wali_kelas_daftar.json`, `wali_kelas_daftar_kosong.json`, `wali_kelas_detail.json`, `wali_kelas_403.json`
(sudah dibuat di `C:\flutter-muslimin\test\fixtures\wali_kelas\`).

## Deploy
`git pull` + **`phpm spark migrate`** (migrasi `2026-11-07-000001_WaliKelas`) + Ctrl+F5. Sesudah itu Admin membuka **Kelola Akun** →
kartu "Akun wali kelas" → **Buat akun wali kelas** (data hosting 7 Nov: 39 akun baru + 3 akun lama dihubungkan — Puguh/Hubin,
Abil/Operator, Debi Saputra/Guru Piket) → kirim username & sandi sementara lewat tombol **Kirim WA** tiap baris (tampil sekali).
