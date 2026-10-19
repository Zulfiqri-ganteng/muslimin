// Salin pustaka & aset dari node_modules ke folder yang IKUT di-commit (server tidak menjalankan npm). Jalankan: npm run vendor
//   Alpine biasa + versi CSP + collapse      → public/assets/js/vendor/
//   Font Inter variabel (latin, latin-ext)   → public/assets/fonts/inter/ (kini hanya pratinjau isi dokumen)
//   Font Plus Jakarta Sans variabel           → public/assets/fonts/plus-jakarta-sans/ (huruf tampilan admin & publik)
//   Ikon Flowbite yang terdaftar di resources/ikon.json → app/ThirdParty/flowbite-icons/{outline,solid}/<nama>.svg
//     (ikon yang dihapus dari daftar ikut dibuang; pakai di view lewat helper ikon())
// Versi dikunci di package.json + package-lock.json. Berkas yang isinya sudah sama tidak disentuh.
import { readFileSync, writeFileSync, mkdirSync, existsSync, readdirSync, unlinkSync } from 'node:fs';
import { join, dirname, basename } from 'node:path';
import { fileURLToPath } from 'node:url';

const AKAR = join(dirname(fileURLToPath(import.meta.url)), '..');
const NM = join(AKAR, 'node_modules');
let ubah = 0;
let sama = 0;

function salin(dari, ke) {
    const isi = readFileSync(join(NM, dari));
    const tujuan = join(AKAR, ke);
    if (existsSync(tujuan) && Buffer.compare(readFileSync(tujuan), isi) === 0) {
        sama++;
        return;
    }
    mkdirSync(dirname(tujuan), { recursive: true });
    writeFileSync(tujuan, isi);
    ubah++;
    console.log('  salin  ' + ke);
}
const versi = (p) => JSON.parse(readFileSync(join(NM, p, 'package.json'), 'utf8')).version;

salin('alpinejs/dist/cdn.min.js', 'public/assets/js/vendor/alpine.min.js');
salin('@alpinejs/csp/dist/cdn.min.js', 'public/assets/js/vendor/alpine-csp.min.js');
salin('@alpinejs/collapse/dist/cdn.min.js', 'public/assets/js/vendor/alpine-collapse.min.js');
// ApexCharts (MIT; 5.x ke atas BUKAN MIT — jangan naik ke 5). CSS dimuat sebagai berkas <link id="apexcharts-css"> → ApexCharts
// tidak menyisipkan <style> sendiri (siap CSP). Hanya dimuat di halaman bergrafik.
salin('apexcharts/dist/apexcharts.min.js', 'public/assets/js/vendor/apexcharts.min.js');
salin('apexcharts/dist/apexcharts.css', 'public/assets/css/vendor/apexcharts.css');
salin('apexcharts/LICENSE', 'public/assets/js/vendor/apexcharts.LICENSE.txt');
for (const n of ['latin', 'latin-ext']) salin(`@fontsource-variable/inter/files/inter-${n}-wght-normal.woff2`, `public/assets/fonts/inter/inter-${n}-wght-normal.woff2`);
salin('@fontsource-variable/inter/LICENSE', 'public/assets/fonts/inter/LICENSE.txt');
// Plus Jakarta Sans (2026-10-19) = huruf SELURUH tampilan admin & publik; Inter tinggal dipakai pratinjau isi dokumen (.dok-pratinjau).
for (const n of ['latin', 'latin-ext']) salin(`@fontsource-variable/plus-jakarta-sans/files/plus-jakarta-sans-${n}-wght-normal.woff2`, `public/assets/fonts/plus-jakarta-sans/plus-jakarta-sans-${n}-wght-normal.woff2`);
salin('@fontsource-variable/plus-jakarta-sans/LICENSE', 'public/assets/fonts/plus-jakarta-sans/LICENSE.txt');

// Ikon: indeks nama → berkas (nama unik lintas kategori; ganda = galat).
const daftar = JSON.parse(readFileSync(join(AKAR, 'resources/ikon.json'), 'utf8'));
const hilang = [];
for (const varian of ['outline', 'solid']) {
    const indeks = {};
    const akarVarian = join(NM, 'flowbite-icons/src', varian);
    for (const kat of readdirSync(akarVarian)) {
        for (const f of readdirSync(join(akarVarian, kat)).filter((x) => x.endsWith('.svg'))) {
            const nama = basename(f, '.svg');
            if (indeks[nama]) throw new Error(`Nama ikon ganda: ${varian}/${nama}`);
            indeks[nama] = `flowbite-icons/src/${varian}/${kat}/${f}`;
        }
    }
    const pakai = new Set(daftar[varian] ?? []);
    for (const nama of pakai) {
        if (indeks[nama]) salin(indeks[nama], `app/ThirdParty/flowbite-icons/${varian}/${nama}.svg`);
        else hilang.push(`${varian}/${nama}`);
    }
    const folder = join(AKAR, 'app/ThirdParty/flowbite-icons', varian);
    if (existsSync(folder)) {
        for (const f of readdirSync(folder).filter((x) => x.endsWith('.svg') && !pakai.has(basename(x, '.svg')))) {
            unlinkSync(join(folder, f));
            ubah++;
            console.log(`  buang  app/ThirdParty/flowbite-icons/${varian}/${f}`);
        }
    }
}
salin('flowbite-icons/LICENSE', 'app/ThirdParty/flowbite-icons/LICENSE');

console.log(`Vendor: ${ubah} berubah, ${sama} sudah sama · Alpine ${versi('alpinejs')} (CSP ${versi('@alpinejs/csp')}, collapse ${versi('@alpinejs/collapse')})`
    + ` · Inter ${versi('@fontsource-variable/inter')} · Plus Jakarta Sans ${versi('@fontsource-variable/plus-jakarta-sans')} · flowbite-icons ${versi('flowbite-icons')} (${(daftar.outline ?? []).length + (daftar.solid ?? []).length} ikon)`);
if (hilang.length) {
    console.log('IKON TIDAK ADA di flowbite-icons: ' + hilang.join(', '));
    process.exitCode = 1;
}
