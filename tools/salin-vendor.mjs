// Salin pustaka & aset dari node_modules ke folder yang IKUT di-commit (server tidak menjalankan npm). Jalankan: npm run vendor
//   Alpine biasa + versi CSP + collapse      → public/assets/js/vendor/
//   Font Inter variabel (latin, latin-ext)   → public/assets/fonts/inter/
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
for (const n of ['latin', 'latin-ext']) salin(`@fontsource-variable/inter/files/inter-${n}-wght-normal.woff2`, `public/assets/fonts/inter/inter-${n}-wght-normal.woff2`);
salin('@fontsource-variable/inter/LICENSE', 'public/assets/fonts/inter/LICENSE.txt');

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
    + ` · Inter ${versi('@fontsource-variable/inter')} · flowbite-icons ${versi('flowbite-icons')} (${(daftar.outline ?? []).length + (daftar.solid ?? []).length} ikon)`);
if (hilang.length) {
    console.log('IKON TIDAK ADA di flowbite-icons: ' + hilang.join(', '));
    process.exitCode = 1;
}
