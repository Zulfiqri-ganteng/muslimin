# Rencana: Upgrade Tampilan Admin + Halaman Login (gaya Flowbite)

Dibuat 2026-10-13. **Status: F0 SELESAI (2026-10-13 malam, lokal, belum di-commit) → berikutnya F1, tunggu aba-aba.**
Diperbarui 2026-10-13 malam: semua usulan disetujui user → aturan **SIAP CSP** (bagian 5a) + daftar pekerjaan rinci per fase (bagian 7).
Kerja per fase: selesai satu fase → centang di bagian 7 → lapor → BERHENTI menunggu aba-aba. Claude tidak commit/push (user yang push).

## 1. Tujuan
- Seluruh halaman **admin (dalam)** + **halaman login** dirombak agar konsisten, profesional, ±90% mirip Flowbite (template admin gratis v2.0.0).
- Perpindahan halaman, buka-tutup menu, dan dialog terasa halus, tapi tidak norak.
- CSS dan JS **dipisah dari view** (tidak ada lagi `<script>`, `<style>`, `onclick=`, `style=` di dalam view) dan hasilnya **SIAP CSP**: nanti CSP ber-nonce + header keamanan tinggal dinyalakan tanpa membongkar view lagi (bagian 5a).
- Tema bisa diganti oleh Admin (bagian 3).
- Di luar cakupan: halaman publik (beranda, form PKL, biodata, kesediaan, dll. — "frontend belum kefikiran"), Android/API, dan **seri keamanan** (menyalakan CSP di server, header X-*, TOTP). Pembersihan CSP halaman publik ikut seri keamanan.

## 2. Keputusan user
**2026-10-13 siang**
| No | Pertanyaan | Jawaban |
|---|---|---|
| 1 | Warna sidebar | **Bisa diganti-ganti lewat pilihan tema**: versi Flowbite Terang, Flowbite Gelap, dan versi sekolah Ungu Lilac |
| 2 | Siapa yang mengatur tema | **Admin saja** (berlaku untuk semua akun; tidak ada pilihan per akun) |
| 3 | Mode gelap seluruh halaman | **Nanti dulu** (ikut saran) → F11 ditunda, tapi fondasinya disiapkan |
| 4 | Grafik dashboard | **ApexCharts** |

Tafsiran no. 1 + no. 3: "versi gelap" = **sidebar gelap**, isi halaman tetap terang. Kalau yang dimaksud seluruh halaman gelap, itu F11 (bisa dimajukan bila user minta).

**2026-10-13 malam — semua usulan disetujui**
| No | Hal | Keputusan |
|---|---|---|
| 5 | CSS template | Pakai "resep" template yang sama (`src/style.css` 23 baris: Tailwind v4 + plugin Flowbite + token + Inter) dan salin **pola kelas** dari HTML template ke view kita; `admin.css` di-build dari view kita sendiri. **Tidak** menyalin CSS jadi template, **tidak** memuat dari CDN. HTML template tidak disalin mentah (template sendiri belum bersih CSP). |
| 6 | JS | **Alpine versi CSP** (`@alpinejs/csp`, tanpa `eval`). Atribut sederhana boleh tetap di view (`@click="buka = !buka"`), logika panjang ke `Alpine.data(...)` di berkas JS. **flowbite.js tidak dipakai** (tugasnya dikerjakan Alpine; dua sistem bisa bentrok). |
| 7 | CSP | Wajib siap CSP **sejak F0**: penanda nonce di layout, CSP mode "lapor saja" di laptop, alat cek otomatis; tiap fase wajib 0 pelanggaran untuk grupnya. |
| 8 | Halaman publik | Tidak dirombak; pembersihan CSP-nya dikerjakan di seri keamanan (bagian 5a "Sisa"). |
| 9 | Template | Disimpan user di `resources/flowbite/` (Hugo, MIT v2.0.0) — **diabaikan git** (baris `.gitignore` ditambahkan 2026-10-13). |
| 10 | Peramban klien | **Semua komputer sekolah Windows 10** → risiko Chrome 109 (Windows 7) gugur. |
| 11 | Deploy | User **tidak deploy sebelum SEMUA fase selesai**. Tiap fase tetap harus utuh & teruji di laptop ("jangan asal, wajib serius detail"). |

Keputusan teknis (dipakai): Tailwind v4 + plugin Flowbite 3 **khusus admin** → berkas `admin.css` terpisah (publik tetap `app.css` Tailwind v3) · Alpine versi CSP (tanpa `flowbite.js`) · hasil build ikut di-commit sehingga server tetap cukup `git pull` · font Inter, ikon, dan semua pustaka JS disimpan di server sendiri (tanpa CDN) · halaman cetak / dokumen format sekolah tidak diubah.

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
- Layout menulis `<html data-warna="…" data-sidebar="…">` langsung dari server → tidak ada kedipan warna saat halaman dibuka, dan tidak perlu skrip sebaris (aman CSP). Hosting yang belum migrate tetap jalan (pakai bawaan).
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
- **Paket (dipasang F0, versi persis, `npm audit` = 0):** `tailwindcss` & `@tailwindcss/cli` 4.3.3 · `flowbite` 3.1.2 (seri 3 = cocok dengan template; 4.x sudah ada tapi template dibuat untuk 3.1) · `alpinejs`, `@alpinejs/csp`, `@alpinejs/collapse` 3.15.12 (sama dengan yang sudah dipakai; vendor lama identik bita-per-bita) · `@fontsource-variable/inter` 5.3.0 (OFL) · `flowbite-icons` 1.5.0 (MIT) · `overrides` `@parcel/watcher` 2.6.0 (versi bawaan CLI menarik `braces` yang punya celah DoS — hanya alat laptop, tetap dibereskan). **ApexCharts belum dipasang** → F4: pilih 3.54.1 atau 4.7.0 (keduanya MIT; **5.x ke atas BUKAN MIT lagi**) menurut pengaturan CSP-nya. `node_modules/` diabaikan git.
- **CSS:** `package.json` di akar repo, versi dikunci di `package-lock.json`. Sumber `resources/css/admin.css` (`@import "tailwindcss" source(none)` + `@source` hanya ke view admin, layout admin, login, dan JS admin; `@plugin "flowbite/plugin"`; token tema; komponen `@layer components` seperti `.btn`, `.inp`, `.lbl`, `.kartu`, `.tabel`, `.lencana` — nama `.inp`/`.lbl` yang sudah dipakai view dipertahankan). Hasil: `public/assets/css/admin.css` (di-commit).
- **Perintah:** `npm run build:admin` (sekali) · `npm run watch:admin` (saat bekerja) · `npm run vendor` (salin berkas jadi pustaka ke `public/assets/js/vendor/`). `app.css` publik tetap dibangun dengan `tailwindcss.exe` v3 seperti sekarang.
- **JS:** Alpine versi CSP (bagian 5a). Skrip panjang di dalam view (499 baris di 6 view) dipindah ke `public/assets/js/admin/<grup>/*.js` sebagai `Alpine.data(...)` — 24 komponen sudah memakai pola ini, tinggal diteruskan. `on*=` (48) → `@click` / pendengar di berkas JS. `style=` (34) → kelas; nilai dinamis lewat `:style` **bentuk objek** (`:style="{ width: persen + '%' }"`, aman CSP) atau JS. Data dari PHP ke JS lewat atribut `data-*` (JSON), bukan `<script>` sebaris.
- **ApexCharts:** disalin dari `node_modules` ke `public/assets/js/vendor/apexcharts.min.js`, dimuat **hanya** di halaman yang punya grafik. ApexCharts menyisipkan `<style>` sendiri → diberi nonce atau CSS-nya dimuat sebagai berkas (dicek di F4).
- **Font & ikon:** Inter (OFL) disimpan di `public/assets/fonts/` → link Google Fonts di `layouts/admin.php` **dan** `admin/login.php` dibuang. Ikon di helper PHP (SVG sebaris — SVG bukan skrip, aman CSP).
- **Alat cek `php spark dev:cek-ui [--grup=…]`:** menghitung per berkas: **pelanggaran CSP** (`<script>` sebaris, `<style>`, `on*=`, `style=`, `href="javascript:"`, muatan CDN, isian Alpine yang tidak didukung versi CSP, panggilan fungsi global dari atribut Alpine, `x-html`, `:style` berbentuk teks) + **aturan tampilan** (kelas hiasan terlarang, nama kelas khas v3, `slate-*`, `<svg>` manual, sudut/bayangan di luar aturan) + `admin.css` yang lebih tua dari sumbernya (lupa build). Mendaftar view per grup menu. Target: 0 untuk grup yang fasenya selesai, 0 total di F12.
- **Lisensi:** pola dari Flowbite Admin Dashboard (MIT) boleh dipakai — cantumkan catatan hak cipta MIT di kepala `admin.css`. Kode **Flowbite Pro (berbayar) tidak disalin**. Template ada di `resources/flowbite/` dan diabaikan git (tidak masuk repo publik).

## 5a. Aturan SIAP CSP (wajib di setiap fase)
**Kenapa:** nonce hanya menolong blok `<script>`/`<style>`. Nonce **tidak** menolong `onclick=`, `style=`, dan Alpine versi biasa. Alpine biasa menjalankan atribut dengan `eval` → CSP terpaksa diberi `unsafe-eval` → bolong; HTML sisipan penyerang tetap bisa menjalankan kode lewat Alpine walau sudah ada nonce. Alpine versi CSP tidak memakai `eval` dan tidak bisa menyentuh `window`/`document`/`fetch`.

**Aturan di view admin + login:**
1. 0 `<script>` sebaris — semua JS di berkas `public/assets/js/...`.
2. 0 `on*=` (`onclick`, `onchange`, `onsubmit`, …) dan 0 `href="javascript:…"`.
3. 0 `style=` — pakai kelas. Nilai dinamis: `:style` bentuk objek atau JS (`el.style.x = …`); **jangan** `:style` berbentuk teks atau `setAttribute('style', …)`.
4. 0 `<style>` — semua ke `admin.css`.
5. 0 CDN — font, Alpine, ApexCharts, dll. disimpan lokal.
6. Atribut Alpine hanya berisi ekspresi yang didukung versi CSP. **Diuji 2026-10-13** (Alpine CSP 3.15.12 & 3.17.4, CSP mode blokir, Chrome waktu nyata — 20/20 lulus, 0 pelanggaran): **BOLEH** baca/bandingkan nilai, ternary, `!`, `&&`, gabung teks `+`, `x = nilai`, `x++`, `form.nama = …` (properti bersarang), metode teks/array (`.toUpperCase()`, `.includes()`, `.length`, indeks `[1]`), panggil metode komponen (dengan/tanpa argumen), `Alpine.data` berparameter (`x-data="contoh(3)"`), literal objek/array, `:class` objek, `:style` **objek**, `x-model(.number)`, `x-for`, `x-if`, `x-init`, `$dispatch('x', {…})` + `$event.detail`, membaca `$refs`, `@click.outside/.prevent`, `x-collapse`. **DITOLAK:** fungsi panah `=>`, template literal, spread `...`, `?.` dan `??`, global (`Math`, `JSON`, `String()`, `parseInt`, `window`, `document`, `navigator`, `encodeURIComponent`, `confirm`, `fetch`, `console`, …), fungsi global buatan sendiri (`rupiah()`, `tglIndo()`, …), `x-html`, metode di dalam objek `x-data` sebaris (`{ naik() {…} }`), menulis lewat `$refs` (`$refs.x.textContent = …`), `x-data` yang bukan `Alpine.data`. Semua yang ditolak dipindah ke metode di `Alpine.data(...)` (di dalam berkas JS, global boleh dipakai).
7. Setiap tag `<script>`, `<style>`, `<link rel="stylesheet">` di layout diberi penanda nonce bawaan CI4 (`csp_script_nonce()` / `csp_style_nonce()`) — mengembalikan teks kosong selama CSP mati (sudah dicek di `system/Common.php`).

**Cara menjamin (tiap fase)** — alat dibuat di F0:
- `php spark dev:cek-ui` (ringkasan semua grup) · `php spark dev:cek-ui --grup master` (rincian berkas + nomor baris) · `--wajib-nol` (kode keluar 1 bila belum nol) · `--json` (peta grup). Peta grup = `app/Config/Tampilan.php` (grup ↔ fase ↔ view ↔ awalan alamat). Juga melaporkan view admin yang belum masuk grup (harus 0) dan apakah `admin.css` sudah di-build ulang (build uji ke berkas sementara lalu dibandingkan isinya).
- `node tools/uji-peramban.mjs` (Chrome tanpa layar, keluaran teks): `--peran=admin,operator,hubin` · `--awalan=admin/master` · `--halaman=a,b` · `--wajib-csp-nol` · `--potret=x.json.gz` / `--banding=x.json.gz` (bandingkan gaya hasil-hitung SEMUA elemen sebelum/sesudah — dipakai F0 & F12). Akun uji dibuat/dihapus otomatis: `php spark dev:akun-uji buat|hapus` (sandi acak, hanya mau jalan bila baseURL = localhost).
- `dev:cek-ui --grup …` = 0 pelanggaran untuk grup itu.
- **CSP mode "lapor saja" di laptop** lewat `.env` (`app.CSPEnabled = true` + `contentsecuritypolicy.reportOnly = true`); hosting tidak berubah. Rancangan kebijakan di `app/Config/ContentSecurityPolicy.php`: `default-src 'self'`, `script-src 'self'` + nonce, `style-src 'self'` + nonce, `img-src 'self' data: blob:`, `font-src 'self'`, `connect-src 'self'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, `frame-ancestors 'self'` (dirapikan lagi di seri keamanan).
- **Uji peramban otomatis** (Chrome headless lewat CDP, hasil berupa teks — tanpa foto layar): buka semua halaman grup per peran, kumpulkan laporan pelanggaran CSP + galat konsol. Target 0.

**Masa transisi Alpine:** dua versi Alpine tidak boleh dimuat bersamaan di satu halaman. Layout memilih per grup menu: grup yang fasenya sudah selesai memuat **Alpine versi CSP**, grup yang belum masih Alpine biasa (daftar grup di satu tempat, mis. `Config\Tampilan::$grupSiapCsp`). Kode baru/bersama (layout, kit komponen, `app.js`) selalu ditulis gaya CSP — gaya ini juga jalan di Alpine biasa. Di F12 Alpine biasa + pemilih per grup dibuang.

**Sisa untuk seri keamanan nanti:** di server nyalakan `CSPEnabled` (mode blokir), aktifkan filter `secureheaders` + header lain (HSTS, Referrer-Policy, Permissions-Policy, `frame-ancestors`), TOTP, dan bersihkan halaman publik (2 `<script>` sebaris, 5 `onclick`, 5 `style=`, 10 CDN — termasuk Alpine `@3.x.x` jsdelivr di `public/layout.php` & `biodata/form.php` yang versinya bisa berganti sendiri). View `pdf/` (132 `style=`, 15 `<style>`) tidak perlu: dirender jadi PDF di server, bukan halaman peramban.

## 6. Yang TIDAK berubah
Halaman publik + `app.css` · dokumen Word/Excel/PDF dan halaman cetak format sekolah · API & aplikasi Android · alur fitur, alamat URL, hak akses · isi data. Halaman admin yang belum masuk fasenya tetap tampil seperti sekarang (F0 menjamin itu).

## 7. Daftar pekerjaan per fase (web)
Setiap fase: lapor hasil + cara cek; uji = `dev:cek-ui` grup itu (tampilan + CSP = 0) + buka semua halaman grup per peran (HTTP 200, tanpa galat PHP) + uji peramban CSP "lapor saja" = 0 + regresi `dev:uji-*` modul terkait + maks 1–2 foto layar.

### F0 Fondasi mesin + siap CSP — ✅ SELESAI 2026-10-13
- [x] `.gitignore`: `/node_modules/` + `/resources/flowbite/`.
- [x] `package.json` + `package-lock.json` (versi persis — bagian 5 "Paket"). Skrip `build:admin`, `watch:admin`, `vendor`, `uji:peramban`. *ApexCharts sengaja belum dipasang → F4 (pilih versi MIT sesuai pengaturan CSP-nya).*
- [x] `resources/css/admin.css` → `public/assets/css/admin.css` (±92 KB): `@import "tailwindcss" source(none)` + `@source` hanya sisi admin, warna merek (biru bawaan + `.tema-lilac`) sebagai variabel, `@font-face` Inter lokal, komponen lama yang dipakai admin (`.inp`, `.lbl`, `.inp-err`, `.err-msg`, `.hint`, `.rise*`, `.lift`, `.bar-grow`, `.donut-seg`, `.dok-pratinjau`), **lapisan kompatibel v3** (warna garis bawaan gray-200, placeholder gray-400, kursor tombol, latar kotak isian `Field`, kotak tanggal/waktu `revert`, tinggi baris rem). *Token 6 kombinasi tema (`data-warna`/`data-sidebar`) dipindah ke F3* (baru berguna saat pemilih tema & sidebar dibuat). *`@plugin "flowbite/plugin"` dipindah ke F1*: plugin itu menambah gaya dasar formulir → tampilan formulir berubah, padahal F0 harus tetap sama.
- [x] Inter (OFL) di `public/assets/fonts/inter/` (2 berkas variabel 100–900, ±48 KB + latin-ext) + `<link rel="preload">`; Google Fonts dibuang dari layout & login.
- [x] Helper `ikon()` (`app/Helpers/ikon_helper.php`, autoload) + daftar `resources/ikon.json` (15 ikon awal) → `npm run vendor` menyalin SVG ke `app/ThirdParty/flowbite-icons/`.
- [x] `tools/ganti-kelas-v4.mjs` dijalankan SEKALI (113 berkas: `slate→gray` 4.910, `outline-none→outline-hidden` 360, `shadow-sm→shadow-xs` 307, `rounded→rounded-sm` 126, `shadow→shadow-sm` 9, `rounded-sm→rounded-xs` 6, `bg-gradient-to-*→bg-linear-to-*` 4, `backdrop-blur(-sm)` 2, `theme()` 1). Alat menolak jalan kedua (pengaman). Diuji: 1.022 kelas v3 bergaya yang dipakai sisi admin → semuanya punya padanan di `admin.css` (0 hilang). Berkas bersama publik (`pager/admin.php`, `partials/dokumen_penampil.php`) sengaja tidak disentuh.
- [x] Layout admin + login memakai `admin.css`; penanda nonce (`csp_style_nonce()`/`csp_script_nonce()`) di tag layout.
- [x] `app/Config/ContentSecurityPolicy.php` dirancang (bagian 5a; `frame-src` + `https://www.youtube-nocookie.com` untuk video Arsip Dokumen) + `.env` laptop: `app.CSPEnabled = true`, `contentsecuritypolicy.reportOnly = true`. Server tetap mati.
- [x] `alpine-csp.min.js` 3.15.12 di vendor + pemilih per grup (`Config\Tampilan::$alpineCsp`, kosong) di layout. Uji coba di halaman contoh dengan CSP **blokir**: 20/20 lulus, 0 pelanggaran (daftar boleh/ditolak di 5a no. 6).
- [x] `php spark dev:cek-ui` (+ `dev:akun-uji`) — angka awal di bagian 9.
- [x] `tools/uji-peramban.mjs` (Chrome tanpa layar, keluaran teks; `--grup`, `--peran`, `--halaman`, `--potret`/`--banding`, `--wajib-csp-nol`).
- [x] **Hasil: tampilan sama, mesin baru.** Bukti: potret gaya 87 halaman (±23.800 elemen) sebelum vs sesudah, 3 peran → 0 galat HTTP/PHP/JS; perbedaan yang tersisa = palet v4 (merah sedikit lebih tajam, abu-abu netral menggantikan slate kebiruan), jarak `space-y` pindah sisi (jaraknya sama), dan 2 perbaikan yang disengaja:
  - Tombol "Hapus Terpilih/Semua" di semua Data Master memakai **atribut** `hidden` (di v4 kelas `inline-flex` menang atas kelas `hidden` — urutan abjad; tanpa perbaikan ini tombolnya selalu tampil). `dev:cek-ui` kini memeriksa pasangan ini.
  - Baris Absensi: `border-emerald-400` dll. → `border-l-*`. Di v3 garis status kiri tertutup warna pembatas abu-abu (kecuali baris pertama); kini garis status berwarna di semua baris, pembatas tetap abu-abu.
  - Celah kosong 20 px tak sengaja di atas beberapa formulir (v3 memberi jarak walau elemen pertama tersembunyi `x-show`) kini hilang.

### F1 Kit komponen (semua gaya CSP)
- [ ] Nyalakan `@plugin "flowbite/plugin"` di `admin.css` (dari F0): gaya dasar formulir Flowbite (checkbox, select, input) — tampilan formulir halaman lama ikut sedikit berubah selama masa transisi (wajar, tidak di-deploy).
- [ ] Paginasi: pisahkan template `pager/admin.php` untuk admin (Flowbite) dan publik (`public/dokumen.php` tetap gaya lama).
- [ ] Partial PHP + kelas `@layer components`: tombol, input, select, checkbox, kartu, tabel, lencana, tab, dropdown, dialog, toast/peringatan, paginasi, keadaan kosong, kepala halaman + breadcrumb.
- [ ] Komponen Alpine bersama di berkas JS (`Alpine.data`): dropdown, dialog, laci (drawer), tab, toast, **dialog konfirmasi** (pengganti `confirm()` di atribut), salin teks.
- [ ] Pembantu format (`rupiah`, `tglIndo`, `periodeTeks`, dll.) dijadikan metode komponen / magic Alpine — bukan fungsi global yang dipanggil dari atribut.
- [ ] Halaman `admin/ui-kit` (hanya mode development) menampilkan semuanya di 3 tema, dimuat dengan **Alpine versi CSP** → sekaligus halaman uji CSP.

### F2 Login
- [ ] Gaya halaman masuk Flowbite: kartu di tengah latar abu muda, logo + nama sekolah, judul, isian akun & sandi dengan **tombol lihat sandi**, pesan galat rapi, tombol penuh warna utama; ikut tema; tanpa gradasi. Logika login tidak diubah.
- [ ] Login memakai Alpine versi CSP (untuk tombol lihat sandi) + berkas JS sendiri; 0 pelanggaran CSP.

### F3 Kerangka + pemilih tema
- [ ] Token tema 6 kombinasi (`data-warna` biru|lilac × `data-sidebar` terang|gelap|warna — dipindah dari F0) menggantikan `.tema-lilac`.
- [ ] Navbar, sidebar 3 gaya, kepala halaman, transisi pindah halaman (View Transitions), footer.
- [ ] Komponen `adminLayout` + `app.js` dirapikan gaya CSP (jalan di kedua versi Alpine).
- [ ] Migrasi `tema_warna`/`tema_sidebar`; `<html data-warna data-sidebar>` ditulis server (tanpa skrip sebaris, tanpa kedipan).
- [ ] Bagian "Tampilan" di Pengaturan Sekolah (Admin, pratinjau langsung). **Mulai fase ini Admin bisa mengganti tema.**

### F4 Dashboard + ApexCharts
- [ ] Kartu angka utama; grafik: siswa aktif per jurusan (donat) & per tingkat (batang), beban mengajar guru (batang mendatar), kesediaan guru (donat), absensi guru hari ini (radial); tren kehadiran 30 hari (area) bila datanya tersedia; daftar "Perlu tindakan" & "Aktivitas terbaru" gaya list Flowbite; animasi grafik tumbuh halus, angka format Indonesia, keadaan kosong.
- [ ] ApexCharts lokal; pengaturan grafik di berkas JS; data lewat `data-*`; `<style>` sisipan ApexCharts diberi nonce / CSS dimuat sebagai berkas.
- [ ] `admin/kurikulum/dashboard.php` ternyata **view mati** (`Kurikulum::dashboard()` hanya mengalihkan ke `admin/dashboard`; tak ada pengendali yang merendernya) → hapus berkasnya (sekalian hilang Chart.js CDN + `<script>` sebaris + 7 `style=`), keluarkan dari Config\Tampilan.
- [ ] Pasang ApexCharts (3.54.1 atau 4.7.0 — MIT; cek opsi `chart.nonce` / CSS berkas) lewat `npm run vendor`.
- [ ] Dashboard pindah ke Alpine versi CSP.

### F5–F10 — resep yang sama untuk setiap grup
1. Rombak view memakai kit komponen (F1) + aturan tampilan (bagian 4).
2. `<script>` sebaris → berkas `public/assets/js/admin/<grup>/…js` (`Alpine.data`).
3. `on*=` → `@click` / pendengar di berkas JS; `style=` → kelas; `<style>` → `admin.css`.
4. Isian Alpine yang tidak didukung + panggilan fungsi global → metode komponen.
5. Grup pindah ke **Alpine versi CSP** (masuk daftar `grupSiapCsp`).
6. `dev:cek-ui --grup` = 0 · uji peramban CSP = 0 · semua halaman grup HTTP 200 per peran · regresi `dev:uji-*` modul terkait.

- [ ] **F5 Data Master** (±25 view).
- [ ] **F6 Kesiswaan + Guru + Kurikulum/Jadwal + Absensi** (±17 view; pindahkan ±480 baris JS sebaris, terutama `absensi/index` 276 & `jadwal/index` 157 — `jadwalGrid` sekarang fungsi global).
- [ ] **F7 Ujian + Honor/Koreksi + SKBM** (±15 view; grid Honor/Koreksi tetap memakai JS yang ada, hanya tampilannya).
- [ ] **F8 UKK + Lab & Inventaris.**
- [ ] **F9 PKL + Surat Sekolah** (±19 view).
- [ ] **F10 Arsip/Dokumen, Info, Sistem** (akun, audit, profil, pengaturan; `pengPage` di pengumuman sekarang fungsi global) + halaman galat 404/500 gaya Flowbite.

### F11 Mode gelap seluruh halaman — DITUNDA
- [ ] (keputusan no. 3) Bila diminta: nilai token `.dark` + tombol pilih di Tampilan.

### F12 Penutup
- [ ] Semua grup sudah Alpine versi CSP → Alpine biasa + pemilih per grup dibuang.
- [ ] `dev:cek-ui` = 0 total (tampilan + CSP); uji peramban CSP semua halaman admin = 0.
- [ ] Cek lebar HP 360 px; navigasi papan ketik & cincin fokus; kontras AA.
- [ ] Buang CSS admin lama dari `app.css` (v3 hanya memindai view publik → berkas lebih kecil).
- [ ] Panduan deploy + catatan serah-terima untuk seri keamanan (bagian 5a "Sisa").

**Deploy:** user baru deploy setelah **SEMUA fase selesai** (keputusan 2026-10-13). Saran: simpan pekerjaan rombak di **cabang git terpisah** (mis. `upgrade-tampilan`) supaya `main` tetap bisa dipakai untuk perbaikan mendesak klien tanpa ikut membawa tampilan setengah jadi ke server. Saat akhirnya deploy: `git pull` + migrasi sekali (F3): `phpm spark migrate`. Sebelum commit: `npm run build:admin` (dicek oleh `dev:cek-ui`). CSP di hosting tetap mati sampai seri keamanan (`.env` server jangan diberi `app.CSPEnabled`).

## 8. Risiko & pencegahan
- ~~**Peramban lama**~~ — **GUGUR 2026-10-13**: klien memastikan semua komputer sekolah Windows 10 (Chrome terbaru), jadi syarat Tailwind v4 (Chrome 111+) terpenuhi.
- **Berkas bersama halaman publik:** `pager/admin.php` (dipakai juga `public/dokumen.php`), `partials/dokumen_penampil.php` (juga `public/berbagi_dokumen.php`), `partials/kredit.php`, `partials/favicon.php`. Publik masih Tailwind v3 → di berkas ini JANGAN pakai nama kelas khas v4 (`shadow-xs`, `outline-hidden`, `rounded-xs`). Saat F3/F10: pisahkan versi admin & publik bila perlu dirombak. (Ditandai `bersama` di Config\Tampilan.)
- **Header CSP berkas dokumen:** `App\Libraries\DokumenStream` memasang `Content-Security-Policy … sandbox` lewat `header()` PHP langsung. Di seri keamanan pastikan CSP CI4 (yang menyetel header `Content-Security-Policy` sendiri) tidak menimpanya.
- **Ratusan view diubah:** ganti nama kelas otomatis + uji buka semua halaman + regresi tiap fase; satu fase = satu grup menu utuh.
- **Tampilan campur selama masa transisi:** wajar (halaman lama tetap rapi karena F0); urutan fase mendahulukan halaman yang paling sering dibuka.
- **Lupa build CSS sebelum push:** diperingatkan `dev:cek-ui`; tanda `?v=filemtime` sudah memaksa peramban mengambil CSS baru.
- **Ukuran ApexCharts (±500 KB):** hanya dimuat di halaman bergrafik, lalu tersimpan di cache peramban.
- **Alpine versi CSP:** ±60 isian Alpine admin yang tidak didukung (±47 fitur terlarang + 2 `x-data` fungsi global + 12 panggilan fungsi global `rupiah`, `tampil`, `periodeTeks`, `tglIndo`, `geser`) → dibetulkan per grup; versi CSP dinyalakan per grup; diuji dulu di F0.
- **Pustaka pihak ketiga & CSP:** ApexCharts menyisipkan `<style>` sendiri (nonce/berkas, F4); grid Honor/Koreksi/SKBM, `xlsx`, `mammoth` dicek lewat uji peramban "lapor saja" di fasenya.

## 9. Catatan angka awal (temuan 2026-10-13, untuk sesi berikutnya)
104 view admin (±20 rb baris) · sidebar 11 grup / ±60 item · `app.css` (Tailwind v3.4.17 exe, `tailwind.config.js`, `resources/css/app.css`) dipakai bersama admin+publik · tema lilac sekarang = kelas `.tema-lilac` di `<html>` layout admin & login, brand = `rgb(var(--brand-N) / <alpha-value>)`, sidebar gradasi `brand-700→900`, menu aktif garis `emerald-300` · `rounded-lg` 883 / `2xl` 351 / `xl` 307 · 223 `<svg>` manual · `slate` 4.711 pemakaian · login: gradasi + kotak "K", Alpine tidak dimuat, tanpa tombol lihat sandi · grafik dashboard sekarang = batang CSS + tooltip (`public/assets/js/admin/dashboard.js`) · Node v24 + npm 11 ada di laptop · template (Hugo; Tailwind ^4.0, flowbite ^3.1.1, apexcharts ^3.46; halaman: dashboard, sign-in/up, lupa/reset sandi, CRUD produk/user, settings, 404/500; sidebar `bg-white border-r border-gray-200`, item `hover:bg-gray-100 rounded-lg`).

**Angka awal `php spark dev:cek-ui` sesudah F0 (2026-10-13)** — tiap fase menurunkan baris grupnya ke 0 (kecuali kolom `<svg>` = info):
```
grup       fase view <script> on*= style= <style> CDN tanpa-nonce Alpine✗ hidden+inline hiasan sudut bayangan slate ikon✗ <svg>
login      F2     1       0    0      0       0   0           0       0             0      1     2        2     0     0     0
kerangka   F3     5       0    0      0       0   0           3       4             0      2     5        0    17     0    13
dashboard  F4     2       1    0      7       0   1           2       0             0      1     6        1     0     0     7
master     F5    25       0    0      0       0   0           8       2             0      5    52       22     0     0    28
kesiswaan  F6    13       4    3      2       0   0           1      10             0     15   100        9     0     0    50
ujian      F7    18       0   15     18       0   0           5      14             0      3   139        7     0     0    24
ukk-lab    F8    16       0    1      3       0   0           0      14             0      0    59       12     0     0    48
pkl-surat  F9    20       0   18      1       0   0           6      28             0    162   204       14     0     0     4
sistem     F10   14       2   11      3       5   1           4      12             0      3   139       13    25     0    55
TOTAL                     7   48     34       5   2          29      84             0    192   706       80    42     0   229
```
Uji peramban F0 (CSP lapor-saja aktif, Alpine biasa): pelanggaran terbanyak `script-src` (eval Alpine biasa — hilang saat grup pindah ke Alpine versi CSP), lalu `style-src-attr` (`style=`). Data laptop: logo sekolah `/uploads/logo_1789827468.jpg` tidak ada (404 di semua halaman — data, bukan kode). `/admin/ujian/asts1/honor/koreksi|impor|urutan` & `/admin/skbm/impor` dialihkan bila data tahun pelajaran belum ada (untuk uji fase F7 siapkan datanya).

**Pindai CSP 2026-10-13 malam (regex kasar, angka sebelum F0):** admin + login: 6 `<script>` sebaris (499 baris), 48 `on*=`, 34 `style=`, 2 `<style>`, 3 CDN (Chart.js 4.4.1 jsdelivr di `admin/kurikulum/dashboard.php`, Google Fonts di `layouts/admin.php` & `admin/login.php`) · 1.418 isian Alpine: ±47 memakai fitur yang tidak didukung versi CSP + 2 `x-data` fungsi global (`jadwalGrid` di `admin/jadwal/index.php`, `pengPage` di `admin/pengumuman/index.php`) + 12 panggilan fungsi global · 40 `x-data` objek sebaris, 34 `x-data` sudah memakai 24 komponen `Alpine.data` · publik: 2 `<script>`, 5 `on*=`, 5 `style=`, 10 CDN · Alpine lokal 3.15.12 (`public/assets/js/vendor/`, + collapse, xlsx, mammoth) · CI4: `Config\ContentSecurityPolicy` ada (autoNonce = true), `App::$CSPEnabled = false`, filter `secureheaders` masih dikomentari · uji peramban sebelumnya: Chrome headless lewat CDP (WebSocket bawaan Node 24) · template di `resources/flowbite/`: `src/style.css` 23 baris; JS `src/charts.js` 601, `sidebar.js` 28, `dark-mode.js` 44; HTML-nya sendiri berisi 1 skrip sebaris (mode gelap), 1 `on*=`, 12 `style=`, 1 `<style>`.
