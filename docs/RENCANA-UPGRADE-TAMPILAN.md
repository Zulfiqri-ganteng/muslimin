# Rencana: Upgrade Tampilan Admin + Halaman Login (gaya Flowbite)

Dibuat 2026-10-13. **Status: RANCANGAN — belum ada kode.** Mulai setelah user bilang **"gas F0"**.
Kerja per fase: selesai satu fase → centang di bagian 7 → lapor → BERHENTI menunggu aba-aba. Claude tidak commit/push (user yang push).

## 1. Tujuan
- Seluruh halaman **admin (dalam)** + **halaman login** dirombak agar konsisten, profesional, ±90% mirip Flowbite (template admin gratis v2.0.0).
- Perpindahan halaman, buka-tutup menu, dan dialog terasa halus, tapi tidak norak.
- CSS dan JS **dipisah dari view** (tidak ada lagi `<script>`/`<style>` di dalam view).
- Tema bisa diganti oleh Admin (bagian 3).
- Di luar cakupan: halaman publik (beranda, form PKL, biodata, kesediaan, dll. — "frontend belum kefikiran"), Android/API, keamanan (CSP, header, TOTP = seri terpisah nanti).

## 2. Keputusan user (2026-10-13)
| No | Pertanyaan | Jawaban |
|---|---|---|
| 1 | Warna sidebar | **Bisa diganti-ganti lewat pilihan tema**: versi Flowbite Terang, Flowbite Gelap, dan versi sekolah Ungu Lilac |
| 2 | Siapa yang mengatur tema | **Admin saja** (berlaku untuk semua akun; tidak ada pilihan per akun) |
| 3 | Mode gelap seluruh halaman | **Nanti dulu** (ikut saran) → F11 ditunda, tapi fondasinya disiapkan |
| 4 | Grafik dashboard | **ApexCharts** |

Tafsiran no. 1 + no. 3: "versi gelap" = **sidebar gelap**, isi halaman tetap terang. Kalau yang dimaksud seluruh halaman gelap, itu F11 (bisa dimajukan bila user minta).

Keputusan teknis (usulan sebelumnya, dipakai kecuali user keberatan): Tailwind v4 + plugin Flowbite 3 **khusus admin** → berkas `admin.css` terpisah (publik tetap `app.css` Tailwind v3) · Alpine tetap dipakai (tanpa `flowbite.js`) · hasil build ikut di-commit sehingga server tetap cukup `git pull` · font Inter dan ikon disimpan di server sendiri · halaman cetak / dokumen format sekolah tidak diubah.

## 3. Tema yang bisa diganti (diatur Admin)
**Tempat mengatur:** Sistem → Pengaturan Sekolah → bagian baru **"Tampilan"** (hanya akun peran Admin). Ada pratinjau langsung sebelum disimpan. Berlaku untuk semua akun admin + halaman login.

**Tiga tema siap pakai:**
| Tema | Warna utama (tombol, tautan, menu aktif, grafik) | Sidebar |
|---|---|---|
| Flowbite Terang | Biru Flowbite (`#1d4ed8` dkk.) | Putih, garis kanan abu tipis (persis Flowbite) |
| Flowbite Gelap | Biru Flowbite | Abu tua (`gray-800`), teks terang |
| **Lilac Sekolah** (bawaan) | Ungu lilac + aksen mint | Ungu tua polos (`brand-900`), menu aktif bergaris mint |

Di baliknya ada 2 setelan: **warna utama** (biru / lilac) × **gaya sidebar** (terang / gelap / berwarna). Jadi kombinasi lain (mis. Lilac + sidebar putih) juga bisa dipilih lewat "Atur sendiri" — total 6 kombinasi, semuanya dirancang aman.

**Yang ikut berubah saat tema diganti:** warna tombol utama, tautan, cincin fokus, menu aktif, lencana merek, warna seri grafik, dan gaya sidebar. **Yang tidak berubah:** warna status (hijau = hadir/disetujui, kuning = menunggu, merah = ditolak/alpa, abu = draf), latar halaman `gray-50`, kartu putih, navbar putih.

**Cara kerja (catatan teknis):**
- Kolom baru di `settings`: `tema_warna` (`lilac`|`biru`, bawaan `lilac`) dan `tema_sidebar` (`terang`|`gelap`|`warna`, bawaan `warna`) → **1 migrasi** (di F3).
- Layout menulis `<html data-warna="…" data-sidebar="…">` langsung dari server → tidak ada kedipan warna saat halaman dibuka. Hosting yang belum migrate tetap jalan (pakai bawaan).
- Warna = variabel CSS: `--brand-50…950` (ramp per warna) dan token sidebar `--sb-bg`, `--sb-teks`, `--sb-teks-redup`, `--sb-hover`, `--sb-aktif-bg`, `--sb-aktif-teks`, `--sb-garis`, `--sb-penanda`. Tailwind: `@theme inline { --color-brand-500: var(--brand-500); … }`.
- Ramp lilac yang sudah dipakai (hex): 50 `#F7F4FC` · 100 `#EFE8F9` · 200 `#DED1F2` · 300 `#C8B3E8` · 400 `#AD8ED9` · 500 `#9270C8` · 600 `#7A56B3` · 700 `#634596` · 800 `#4E3778` · 900 `#3A2A5A`. Tombol utama = 700 (kontras teks putih lolos AA).
- Grafik ApexCharts membaca warna dari variabel CSS saat digambar, jadi ikut tema.
- Untuk F11 nanti: semua warna lewat token, sehingga mode gelap tinggal menambah nilai `.dark`.

## 4. Aturan tampilan (mirip Flowbite, bukan "buatan AI")
- **Kerangka Flowbite:** navbar putih selebar layar di atas (tombol ciutkan, logo + nama sekolah, menu akun: Profil Saya, Lihat Form Publik, Keluar), sidebar di bawahnya (11 grup ±60 menu, urutan tetap mengikuti aturan sidebar, kotak cari menu tetap ada, bisa diciutkan jadi ikon, laci di HP).
- **Kepala halaman** seragam: breadcrumb (Beranda / Grup / Halaman) → judul `text-xl font-semibold` → tombol aksi di kanan → tab bila ada.
- **Kartu:** putih, garis `gray-200` 1px, `rounded-lg`, bayangan `shadow-xs`. **Tabel:** kepala `bg-gray-50` huruf kapital `text-xs`, baris bergaris bawah, sorot saat disentuh. **Form:** gaya input Flowbite (`bg-gray-50`, garis `gray-300`, fokus cincin warna utama). **Lencana:** gaya badge Flowbite (`bg-*-100 text-*-800`).
- **Satu set ukuran:** sudut `rounded-lg` (bulat penuh hanya untuk avatar), bayangan hanya `xs`/`sm` (dialog boleh `lg`), warna netral `gray` (bukan `slate`), font Inter 400/500/600/700.
- **Ikon:** Flowbite Icons (MIT) lewat helper `ikon('nama', 'w-5 h-5')` — menggantikan ±223 `<svg>` tulisan tangan.
- **Gerak halus:** transisi 150–200 ms untuk hover, dropdown, dialog, buka-tutup grup; pindah halaman memakai View Transitions (pudar singkat; peramban yang belum mendukung tetap normal); status ciut sidebar disimpan di cookie supaya tidak berkedip; menghormati setelan "kurangi gerakan".
- **DILARANG** (sesuai keluhan user 2026-10-12): gradasi besar, bulatan cahaya, grid titik, lencana pil bertitik, emoji, angka berhitung naik, kartu ikon warna-warni, cincin bercahaya. Teks faktual, tanpa slogan.

## 5. Fondasi teknis
- **CSS:** `package.json` di akar repo (Tailwind v4 + `@tailwindcss/cli` + `flowbite` 3.1 + `apexcharts` 3.x, versi dikunci di `package-lock.json`; `node_modules/` diabaikan git). Sumber `resources/css/admin.css` (`@import "tailwindcss" source(none)` + `@source` hanya ke view admin, layout admin, login, dan JS admin; `@plugin "flowbite/plugin"`; token tema; komponen `@layer components` seperti `.btn`, `.inp`, `.lbl`, `.kartu`, `.tabel`, `.lencana` — nama `.inp`/`.lbl` yang sudah dipakai view dipertahankan). Hasil: `public/assets/css/admin.css` (di-commit).
- **Perintah:** `npm run build:admin` (sekali) · `npm run watch:admin` (saat bekerja). `app.css` publik tetap dibangun dengan `tailwindcss.exe` v3 seperti sekarang.
- **JS:** Alpine tetap. Skrip panjang di dalam view (±499 baris di 6 view) dan objek `x-data` besar (38) dipindah ke `public/assets/js/admin/*.js` sebagai `Alpine.data(...)`. Atribut `on*=` (50) dan `style=` statis (35) diganti kelas/Alpine; lebar dinamis lewat `data-*` + JS. Ekspresi Alpine pendek (`@click="open = !open"`) boleh tetap.
- **ApexCharts:** disalin dari `node_modules` ke `public/assets/js/vendor/apexcharts.min.js`, dimuat **hanya** di halaman yang punya grafik.
- **Font & ikon:** Inter (OFL) disimpan di `public/assets/fonts/` → link Google Fonts di layout admin dibuang. Ikon di helper PHP (SVG sebaris).
- **Alat cek `php spark dev:cek-ui [--grup=…]`:** menghitung per berkas: `<script>` sebaris, `<style>`, `on*=`, `style=`, `x-data` panjang, kelas hiasan terlarang, nama kelas khas v3, `slate-*`, `<svg>` manual, sudut/bayangan di luar aturan, dan `admin.css` yang lebih tua dari sumbernya (lupa build). Juga mendaftar view per grup menu. Target akhir: 0.
- **Persiapan keamanan (seri nanti):** tanpa skrip/gaya sebaris dan tanpa `on*=`, CSP berbasis nonce kelak jauh lebih mudah dipasang.
- **Lisensi:** pola dari Flowbite Admin Dashboard (MIT) boleh dipakai — cantumkan catatan hak cipta MIT di kepala `admin.css`. Kode **Flowbite Pro (berbayar) tidak disalin**. Template zip hanya diekstrak ke scratchpad, tidak masuk repo.

## 6. Yang TIDAK berubah
Halaman publik + `app.css` · dokumen Word/Excel/PDF dan halaman cetak format sekolah · API & aplikasi Android · alur fitur, alamat URL, hak akses · isi data. Halaman admin yang belum masuk fasenya tetap tampil seperti sekarang (F0 menjamin itu).

## 7. Fase (web)
Setiap fase: lapor hasil + cara cek; uji = `dev:cek-ui` grup itu + buka semua halaman grup per peran (HTTP 200, tanpa galat PHP) + regresi `dev:uji-*` modul terkait + maks 1–2 foto layar.

- [ ] **F0 Fondasi mesin** — `package.json`, `resources/css/admin.css` + token tema (6 kombinasi, belum ada pilihannya), font Inter lokal, helper `ikon()`, alat `dev:cek-ui`. Skrip ganti nama kelas v3→v4 otomatis **khusus view admin** (`shadow-sm→shadow-xs`, `shadow→shadow-sm`, `rounded→rounded-sm`, `outline-none→outline-hidden`, `ring→ring-3`, `flex-shrink→shrink`, `*-opacity-*→/nn`, `theme(colors.x)`→`var(--color-x)`, dll.) + `slate→gray` + lapisan "kompatibel" (warna garis/cincin/placeholder bawaan v3, kursor tombol). Layout admin + login pindah ke `admin.css`. **Hasil: tampilan masih seperti sekarang (lilac), mesinnya sudah baru.** Uji: semua halaman admin dibuka per peran, bandingkan beberapa halaman sebelum/sesudah.
- [ ] **F1 Kit komponen** — tombol, input, select, checkbox, kartu, tabel, lencana, tab, dropdown, dialog, toast/peringatan, paginasi, keadaan kosong, kepala halaman + breadcrumb (partial PHP + kelas `@layer components`). Halaman `admin/ui-kit` (hanya mode development) menampilkan semuanya di 3 tema.
- [ ] **F2 Login** — gaya halaman masuk Flowbite: kartu di tengah latar abu muda, logo + nama sekolah, judul, isian akun & sandi dengan **tombol lihat sandi**, pesan galat rapi, tombol penuh warna utama; ikut tema; tanpa gradasi. Logika login tidak diubah.
- [ ] **F3 Kerangka + pemilih tema** — navbar, sidebar 3 gaya, kepala halaman, transisi pindah halaman, footer; migrasi `tema_warna`/`tema_sidebar`; bagian "Tampilan" di Pengaturan Sekolah (Admin, pratinjau langsung). **Mulai fase ini Admin bisa mengganti tema.**
- [ ] **F4 Dashboard + ApexCharts** — kartu angka utama; grafik: siswa aktif per jurusan (donat) & per tingkat (batang), beban mengajar guru (batang mendatar), kesediaan guru (donat), absensi guru hari ini (radial); tren kehadiran 30 hari (area) bila datanya tersedia; daftar "Perlu tindakan" & "Aktivitas terbaru" gaya list Flowbite; animasi grafik tumbuh halus, angka format Indonesia, keadaan kosong.
- [ ] **F5 Data Master** (±25 view).
- [ ] **F6 Kesiswaan + Guru + Kurikulum/Jadwal + Absensi** (±17 view; pindahkan ±480 baris JS sebaris, terutama `absensi/index` 276 & `jadwal/index` 157).
- [ ] **F7 Ujian + Honor/Koreksi + SKBM** (±15 view; grid Honor/Koreksi tetap memakai JS yang ada, hanya tampilannya).
- [ ] **F8 UKK + Lab & Inventaris.**
- [ ] **F9 PKL + Surat Sekolah** (±19 view).
- [ ] **F10 Arsip/Dokumen, Info, Sistem** (akun, audit, profil, pengaturan) + halaman galat 404/500 gaya Flowbite.
- [ ] **F11 Mode gelap seluruh halaman — DITUNDA** (keputusan no. 3). Bila diminta: nilai token `.dark` + tombol pilih di Tampilan.
- [ ] **F12 Penutup** — `dev:cek-ui` = 0; cek lebar HP 360 px; navigasi papan ketik & cincin fokus; kontras AA; buang CSS admin lama dari `app.css` (v3 hanya memindai view publik → berkas lebih kecil); panduan deploy.

**Deploy:** setiap fase aman di-push sendiri-sendiri (`git pull` di server). Migrasi hanya sekali di F3: `phpm spark migrate`. Sebelum commit: `npm run build:admin` (dicek oleh `dev:cek-ui`).

## 8. Risiko & pencegahan
- **Peramban lama:** Tailwind v4 dirancang untuk Chrome 111+ / Safari 16.4+ / Firefox 128+ (v4.1 ke atas punya cadangan warna untuk peramban lebih tua). Komputer Windows 7 hanya sampai Chrome 109 → bisa tampil kurang rapi. Pencegahan: token warna merek ditulis hex; di F0 dicek; **mohon ditanyakan ke klien apakah ada komputer TU/guru yang masih Windows 7.**
- **Ratusan view diubah:** ganti nama kelas otomatis + uji buka semua halaman + regresi tiap fase; satu fase = satu grup menu utuh.
- **Tampilan campur selama masa transisi:** wajar (halaman lama tetap rapi karena F0); urutan fase mendahulukan halaman yang paling sering dibuka.
- **Lupa build CSS sebelum push:** diperingatkan `dev:cek-ui`; tanda `?v=filemtime` sudah memaksa peramban mengambil CSS baru.
- **Ukuran ApexCharts (±500 KB):** hanya dimuat di halaman bergrafik, lalu tersimpan di cache peramban.

## 9. Catatan angka awal (temuan 2026-10-13, untuk sesi berikutnya)
104 view admin (±20 rb baris) · sidebar 11 grup / ±60 item · `app.css` (Tailwind v3.4.17 exe, `tailwind.config.js`, `resources/css/app.css`) dipakai bersama admin+publik · tema lilac sekarang = kelas `.tema-lilac` di `<html>` layout admin & login, brand = `rgb(var(--brand-N) / <alpha-value>)`, sidebar gradasi `brand-700→900`, menu aktif garis `emerald-300` · JS sebaris 499 baris di 6 view · 50 `on*=` · 35 `style=` · 2 `<style>` · 38 objek `x-data` · 295 `@click` · `rounded-lg` 883 / `2xl` 351 / `xl` 307 · 223 `<svg>` manual · `slate` 4.711 pemakaian · Inter dari Google Fonts · login: gradasi + kotak "K", Alpine tidak dimuat, tanpa tombol lihat sandi · grafik dashboard sekarang = batang CSS + tooltip (`public/assets/js/admin/dashboard.js`) · Node v24 + npm 11 ada di laptop · template: `Downloads/flowbite-admin-dashboard-main.zip` (Hugo; Tailwind ^4.0, flowbite ^3.1.1, apexcharts ^3.46; halaman: dashboard, sign-in/up, lupa/reset sandi, CRUD produk/user, settings, 404/500; sidebar `bg-white border-r border-gray-200`, item `hover:bg-gray-100 rounded-lg`).
