// Sekali jalan di F0 rombak tampilan (docs/RENCANA-UPGRADE-TAMPILAN.md): nama kelas Tailwind v3 → v4 di berkas KHUSUS admin,
// plus slate → gray. Aturan dicek terhadap keluaran Tailwind 4.3.3 (nilai v3 dipertahankan):
//   shadow-sm→shadow-xs · shadow→shadow-sm · rounded-sm→rounded-xs · rounded→rounded-sm (juga rounded-t/-b/-tl/… polos)
//   blur-sm→blur-xs · blur→blur-sm · backdrop-blur-sm→backdrop-blur-xs · backdrop-blur→backdrop-blur-sm · drop-shadow serupa
//   outline-none→outline-hidden · ring→ring-3 · flex-shrink(-0)→shrink(-0) · flex-grow(-0)→grow(-0) · overflow-ellipsis→text-ellipsis
//   bg-gradient-to-X→bg-linear-to-X · theme(colors.a.b)→var(--color-a-b) · *-slate-N→*-gray-N
// Yang TIDAK perlu diubah (masih didukung v4): awalan "!" penting, placeholder-*, transform, filter.
// Hanya token kelas utuh (dibatasi spasi/kutip/backtick) yang diganti; boleh berawalan varian (hover:, lg:, group-hover:, …) dan "!".
// Berkas bersama halaman publik (pager/admin.php, partials/*) SENGAJA tidak disentuh — publik tetap Tailwind v3.
//
//   node tools/ganti-kelas-v4.mjs           periksa saja: hitungan per aturan + baris mencurigakan (token polos di luar atribut class)
//   node tools/ganti-kelas-v4.mjs --tulis   ubah berkas
import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs';
import { join, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const AKAR = join(dirname(fileURLToPath(import.meta.url)), '..');
const TULIS = process.argv.includes('--tulis');
const SASARAN = ['app/Views/admin', 'app/Views/layouts/admin.php', 'public/assets/js/admin', 'app/Libraries/SuratJenis.php',
    'app/Models/SuratSekolahModel.php'];

const PETA = new Map([
    ['shadow-sm', 'shadow-xs'], ['shadow', 'shadow-sm'], ['drop-shadow-sm', 'drop-shadow-xs'], ['drop-shadow', 'drop-shadow-sm'],
    ['blur-sm', 'blur-xs'], ['blur', 'blur-sm'], ['backdrop-blur-sm', 'backdrop-blur-xs'], ['backdrop-blur', 'backdrop-blur-sm'],
    ['rounded-sm', 'rounded-xs'], ['rounded', 'rounded-sm'], ['outline-none', 'outline-hidden'], ['ring', 'ring-3'],
    ['flex-shrink', 'shrink'], ['flex-shrink-0', 'shrink-0'], ['flex-grow', 'grow'], ['flex-grow-0', 'grow-0'],
    ['overflow-ellipsis', 'text-ellipsis'], ['decoration-slice', 'box-decoration-slice'], ['decoration-clone', 'box-decoration-clone'],
]);
for (const s of ['t', 'r', 'b', 'l', 'tl', 'tr', 'br', 'bl', 's', 'e', 'ss', 'se', 'es', 'ee']) {
    PETA.set(`rounded-${s}-sm`, `rounded-${s}-xs`);
    PETA.set(`rounded-${s}`, `rounded-${s}-sm`);
}
for (const arah of ['t', 'tr', 'r', 'br', 'b', 'bl', 'l', 'tl']) PETA.set(`bg-gradient-to-${arah}`, `bg-linear-to-${arah}`);
// Token polos yang juga bisa berupa kata biasa → baris di luar atribut class dilaporkan untuk dicek mata.
const POLOS = new Set(['shadow', 'rounded', 'blur', 'ring', 'backdrop-blur', 'drop-shadow']);

function berkas(p) {
    const abs = join(AKAR, p);
    if (!statSync(abs).isDirectory()) return [abs];
    return readdirSync(abs).flatMap((n) => berkas(join(p, n)));
}

// Token: (varian:)* + "!"? + nama, diapit awal/spasi/kutip/backtick.
const TOKEN = /(^|[\s"'`])((?:[^\s"'`:]+:)*)(!?)([a-z][a-z0-9-]*)(?=$|[\s"'`])/g;

// PENGAMAN: aturan ini TIDAK boleh dijalankan dua kali (rounded→rounded-sm lalu rounded-sm→rounded-xs = salah). Bila berkas
// sasaran sudah memuat nama khas v4, berarti sudah pernah dijalankan (F0, 2026-10-13) → berhenti.
const SEMUA = SASARAN.flatMap(berkas).filter((x) => /\.(php|js)$/.test(x));
const sudah = SEMUA.filter((f) => /[\s"'`:](outline-hidden|shadow-xs|rounded-xs)[\s"'`]/.test(readFileSync(f, 'utf8')));
if (sudah.length) {
    console.log(`BERHENTI: ${sudah.length} berkas sudah memakai nama kelas v4 (outline-hidden/shadow-xs/rounded-xs) — alat ini sudah pernah dijalankan.`);
    console.log('Menjalankannya lagi akan menggeser ukuran sekali lagi (mis. rounded-sm → rounded-xs). Tidak ada berkas yang diubah.');
    process.exit(1);
}

const hitung = new Map();
const curiga = [];
let nBerkas = 0;
for (const f of SEMUA) {
    const asli = readFileSync(f, 'utf8');
    const rel = relative(AKAR, f).replace(/\\/g, '/');
    const baris = asli.split('\n');
    const baru = baris.map((b, i) => {
        let x = b.replace(/theme\(colors\.([a-z]+)\.(\d+)\)/g, (_, w, n) => {
            hitung.set('theme(colors.*)', (hitung.get('theme(colors.*)') ?? 0) + 1);
            return `var(--color-${w}-${n})`;
        });
        x = x.replace(TOKEN, (semua, depan, varian, penting, nama) => {
            const ganti = PETA.get(nama);
            if (!ganti) return semua;
            hitung.set(nama, (hitung.get(nama) ?? 0) + 1);
            if (POLOS.has(nama) && !/class/.test(b)) curiga.push(`${rel}:${i + 1}  ${b.trim().slice(0, 150)}`);
            return depan + varian + penting + ganti;
        });
        x = x.replace(/-slate-(\d)/g, (_, d) => {
            hitung.set('slate→gray', (hitung.get('slate→gray') ?? 0) + 1);
            return '-gray-' + d;
        });
        return x;
    });
    const hasil = baru.join('\n');
    if (hasil !== asli) {
        nBerkas++;
        if (TULIS) writeFileSync(f, hasil);
    }
}

console.log(`${TULIS ? 'DIUBAH' : 'PERIKSA (belum diubah)'}: ${nBerkas} berkas`);
for (const [k, n] of [...hitung].sort((a, b) => b[1] - a[1])) console.log(`  ${String(n).padStart(5)}× ${k}${PETA.has(k) ? ' → ' + PETA.get(k) : ''}`);
if (curiga.length) {
    console.log(`\nToken polos di baris tanpa kata "class" (${curiga.length}) — cek bahwa ini memang kelas:`);
    for (const c of curiga) console.log('  ' + c);
}
