# Panduan: Mengisi Data Siswa Resmi (Format 8355) di Server

Bahasa sederhana, urut dari atas. Rancangan & temuan: `RENCANA-DATA-SISWA-8355.md`.

**Intinya:** Excel resmi dari sekolah (`Data Siswa.xlsx`, 1.727 siswa) dibaca oleh satu perintah yang mencocokkan tiap baris dengan siswa
yang sudah ada (lewat NAMA + tingkat + jurusan), lalu mengisi NIS asli, NISN, tempat/tanggal lahir, agama, nama orang tua, alamat orang tua,
STTB, dan tahun masuk. **Kelas (rombel) siswa lama tidak diubah** karena Excel tidak memuat rombel.

**Penting (privasi):** Excel berisi data pribadi ±1.700 siswa dan repo GitHub ini PUBLIK, jadi berkasnya **tidak boleh** di-commit. Folder
`formatdatasekolah/` dan `writable/data-sekolah/` sudah di `.gitignore`. Berkas diunggah **manual ke server**, dan sebaiknya dihapus lagi
dari server setelah selesai.

## A. Sebelum mulai (sekali)

1. Pastikan kode terbaru sudah di server: `cd ~/kangmuslim && git pull && phpm spark migrate` (migrasi 3 kolom baru, `SiswaDataSekolah`, harus sudah jalan).
2. **Cadangkan database** (cPanel → *Backup* → *Download a MySQL Database Backup*, atau phpMyAdmin → Export). Ini jaring pengaman utama.
3. Unggah Excel lewat cPanel **File Manager** ke folder `kangmuslim/writable/data-sekolah/` (buat foldernya bila belum ada) dengan nama persis
   **`Data Siswa.xlsx`**.

## B. Langkah 1 — LAPORAN (tidak mengubah apa pun)

```
cd ~/kangmuslim
phpm spark siswa:data-8355
```

Muncul ringkasan angka. Contoh pada salinan data produksi (angka di server bisa sedikit beda):

| Baris ringkasan | Arti | Contoh |
|---|---|---|
| Dipasangkan | siswa di sistem yang ketemu padanannya di Excel (akan diisi datanya) | 1.660 |
| Siswa BARU | ada di Excel, belum ada di sistem → akan ditambahkan **tanpa kelas** | 67 |
| Ditahan untuk ditinjau | nama mirip tapi meragukan → **tidak diubah** | 11 |
| Siswa sistem TIDAK ada di Excel | tidak diubah (mungkin sudah lulus/pindah, mis. kelas XII TKJ 3) | 58 |
| Konflik jenis kelamin | L/P di sistem berbeda dari Excel → memakai Excel | 15 |
| Bentrok NIS/NISN | NIS/NISN sudah dipakai siswa lain → baris itu dilewati | 0 |

Laporan lengkap berupa berkas CSV di `writable/data-sekolah/laporan-8355/` (unduh lewat File Manager, buka dengan Excel):

- `tinjau_mirip.csv` — pasangan yang ditahan. Bila ternyata ORANG YANG SAMA, tambahkan `--gabung NIS:ID` (kolom terakhir sudah berisi perintahnya).
- `pasangan_mirip_otomatis.csv` — pasangan nama mirip yang DITERIMA otomatis (salah ketik, singkatan, nama lebih panjang). Cek sekilas.
- `konflik_jenis_kelamin.csv`, `siswa_baru.csv`, `tidak_ada_di_excel.csv`, `bentrok.csv`, `beda_dengan_biodata.csv`, `anomali_excel.csv`.

Aturan penting yang dijalankan:

- Nama ditulis **Huruf Awal Besar** ("ADE FIRMANSYAH" → "Ade Firmansyah"); bila nama di Excel hanya versi LEBIH PENDEK dari nama di sistem
  ("MONA" untuk "Mona Septya"), nama sistem dipertahankan.
- Agama dibakukan (Katholik → Katolik, Budha → Buddha).
- `tahun_masuk` dihitung dari NIS (kelas X = 2026, XI = 2025, XII = 2024); data lama yang semuanya 2026 dikoreksi.
- Siswa yang SUDAH punya Biodata disahkan: tempat lahir, tanggal lahir, agama, dan alamat orang tua isian siswa **tidak ditimpa** (dilaporkan di `beda_dengan_biodata.csv`).
- Dua catatan anomali di Excel yang diputuskan apa adanya: NIS **24253** (Raditya Pratama; kemungkinan 242510253) dan **Andika Adha Pratama**
  (ber-NIS angkatan XI tetapi tercatat di bagian XII). Perbaiki manual di Master Siswa bila perlu.

## C. Langkah 2 — TULIS

Bila laporan sudah wajar:

```
phpm spark siswa:data-8355 --tulis
```

Semua penulisan dalam **satu transaksi** (gagal = tidak ada yang berubah). Nilai lama siswa yang diubah disimpan di
`writable/data-sekolah/cadangan/cadangan-<tanggal-jam>.csv`, dan satu catatan masuk ke Audit Log.

Bila ada pasangan dari `tinjau_mirip.csv` yang ingin digabung, jalankan LAPORAN dulu dengan opsi yang sama untuk memastikan, lalu tulis:

```
phpm spark siswa:data-8355 --gabung 252610299:1021,242510088:1402
phpm spark siswa:data-8355 --gabung 252610299:1021,242510088:1402 --tulis
```

## D. Langkah 3 — Pastikan beres

Jalankan laporan sekali lagi: `phpm spark siswa:data-8355`. Seharusnya **"ada kolom yang berubah" = 0** dan **"Siswa BARU" = 0**
(aman diulang kapan pun; siswa yang sudah terisi dikenali dari NIS-nya, tidak akan dobel).

## E. Sesudahnya

- Menu **Master Siswa** → saringan **— Tanpa kelas —**: tempatkan siswa baru ke rombel masing-masing (kolom Keterangan berisi tingkat+jurusan resmi).
- Siswa yang tidak ada di Excel dibiarkan; ubah statusnya (lulus/pindah/keluar) bila memang demikian.
- **Hapus `Data Siswa.xlsx` dari server** setelah selesai (data pribadi). Laporan CSV dan cadangan juga berisi data pribadi: unduh lalu hapus dari server.
- Surat PKL baru boleh memakai kolom NIS (fase F3) **setelah** langkah ini selesai di server.

## F. Kalau ada yang salah

- Salah sebagian: perbaiki manual di Master Siswa, atau minta siswa memperbaiki lewat Isian Biodata.
- Salah banyak: pulihkan dari **cadangan database** (langkah A.2). Berkas `cadangan-*.csv` berisi nilai lama kolom yang diubah (untuk melihat data sebelum).
