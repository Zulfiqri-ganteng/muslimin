// Uji peramban halaman admin lewat Chrome tanpa layar (CDP) — alat tetap rombak tampilan (docs/RENCANA-UPGRADE-TAMPILAN.md §5a).
// Per halaman: status HTTP, galat PHP, galat JS/konsol, berkas gagal dimuat, pelanggaran CSP (mode "lapor saja"), font Inter.
// Opsional: potret gaya hasil-hitung semua elemen yang tampak → dibandingkan sebelum/sesudah (ukuran, huruf, warna, bayangan).
//
//   node tools/uji-peramban.mjs                                 semua halaman menu samping, peran admin
//   node tools/uji-peramban.mjs --peran=admin,operator,hubin    beberapa peran (akun uji dibuat & dihapus otomatis)
//   node tools/uji-peramban.mjs --awalan=admin/master           hanya alamat di bawah awalan itu (boleh beberapa, pisah koma)
//   node tools/uji-peramban.mjs --halaman=admin/biodata/1,…     daftar alamat sendiri (menggantikan menu samping; login tak ikut)
//   node tools/uji-peramban.mjs --grup=master --wajib-csp-nol   halaman satu grup rombak (peta: app/Config/Tampilan.php) — uji akhir fase
//   node tools/uji-peramban.mjs --wajib-csp-nol                 gagal bila masih ada pelanggaran CSP
//   node tools/uji-peramban.mjs --progres                       cetak posisi uji per halaman ke stderr (untuk uji yang lama)
//   node tools/uji-peramban.mjs --potret=sebelum.json.gz        simpan potret gaya (peran pertama saja)
//   node tools/uji-peramban.mjs --banding=sebelum.json.gz [--rinci=beda.json]   potret ulang lalu bandingkan
//   node tools/uji-peramban.mjs --lebar=360                     lebar layar (HP); halaman yang melebar ke samping selalu dilaporkan
//   node tools/uji-peramban.mjs --mode=gelap                    paksa mode tema terang|gelap|sekolah (atribut <html>, DB tidak diubah)
//   node tools/uji-peramban.mjs --kontras                       kontras tulisan WCAG AA (4,5:1; huruf besar 3:1)
//   node tools/uji-peramban.mjs --fokus                         elemen yang bisa difokus papan ketik tapi tanpa tanda fokus tampak
//
// Butuh Node 24 (fetch & WebSocket bawaan), Google Chrome, Apache lokal. Alamat situs dibaca dari app.baseURL di .env.
// Profil Chrome sementara dibuat di folder UJI_PROFIL_DIR (bawaan: folder temp sistem) lalu dihapus.
import { spawn, execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gzipSync, gunzipSync } from 'node:zlib';

const AKAR = join(dirname(fileURLToPath(import.meta.url)), '..');
const ARG = Object.fromEntries(process.argv.slice(2).map((a) => {
    const m = a.match(/^--([^=]+)(?:=(.*))?$/);
    return m ? [m[1], m[2] ?? true] : [a, true];
}));
const BASE = (readFileSync(join(AKAR, '.env'), 'utf8').match(/^\s*app\.baseURL\s*=\s*['"]?([^'"\s]+)/m)?.[1]
    ?? 'http://localhost/muslimin/public/').replace(/\/+$/, '');
const CHROME = process.env.CHROME ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const PERAN = String(ARG.peran ?? 'admin').split(',').filter(Boolean);
const AWALAN = String(ARG.awalan === true ? '' : (ARG.awalan ?? '')).split(',').filter(Boolean).map((a) => a.replace(/^\/+|\/+$/g, ''));
const HALAMAN = String(ARG.halaman === true ? '' : (ARG.halaman ?? '')).split(',').filter(Boolean).map((a) => a.replace(/^\/+/, ''));
// --grup=master: hanya halaman milik grup itu menurut Config\Tampilan (dibaca lewat php spark dev:cek-ui --json; awalan terpanjang menang).
const GRUP = typeof ARG.grup === 'string' ? ARG.grup : null;
let PETA_GRUP = null;
if (GRUP) {
    const keluaran = execFileSync('php', ['spark', 'dev:cek-ui', '--json', '--no-header'], { cwd: AKAR, encoding: 'utf8' });
    PETA_GRUP = JSON.parse(keluaran.split(/\r?\n/).find((b) => b.trim().startsWith('{'))).grup;
    if (!PETA_GRUP[GRUP]) throw new Error(`Grup tidak dikenal: ${GRUP} (ada: ${Object.keys(PETA_GRUP).join(', ')})`);
}
function grupAlamat(alamat) {
    let pilih = null;
    let panjang = -1;
    for (const [kunci, g] of Object.entries(PETA_GRUP ?? {})) {
        for (const a of g.url) {
            if ((alamat === a || alamat.startsWith(a + '/')) && a.length > panjang) { pilih = kunci; panjang = a.length; }
        }
    }
    return pilih;
}
// Halaman tanpa item menu samping (ikut diuji bila perannya boleh membuka). Halaman detail ber-ID & tab lain: pakai --halaman=…
// admin/ui-kit?uji=1 = halaman kit komponen (F1) yang menguji dirinya sendiri (hanya Admin; peran lain dialihkan).
const TAMBAHAN = ['admin/master/tahun-ajaran', 'admin/master/fase', 'admin/ujian/asts1/honor', 'admin/dokumen/sampah', 'admin/dokumen/penyimpanan',
    'admin/ui-kit?uji=1'];
// Ukuran layar: bawaan laptop 1366×900; --lebar=360 → HP (viewport mobile).
const LEBAR = Number(ARG.lebar) > 0 ? Number(ARG.lebar) : 1366;
const TINGGI = LEBAR < 768 ? 800 : 900;
// --mode=…: atribut <html> per mode (sama dengan App\Libraries\Tampilan::MODE).
const MODE_TEMA = { terang: ['terang', 'biru', 'terang'], gelap: ['gelap', 'biru', 'gelap'], sekolah: ['sekolah', 'lilac', 'warna'] };
const MODE = typeof ARG.mode === 'string' ? ARG.mode : null;
if (MODE && !MODE_TEMA[MODE]) throw new Error(`Mode tidak dikenal: ${MODE} (ada: ${Object.keys(MODE_TEMA).join(', ')})`);
// Gaya yang dipotret per elemen (urutan = urutan nilai di berkas potret).
const PROP = ['display', 'position', 'float', 'width', 'height', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
    'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'border-top-width', 'border-right-width', 'border-bottom-width',
    'border-left-width', 'border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius',
    'font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'text-align', 'text-transform', 'white-space',
    'color', 'background-color', 'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color',
    'background-image', 'box-shadow', 'opacity', 'visibility', 'z-index', 'gap', 'flex-direction', 'flex-wrap', 'justify-content',
    'align-items', 'grid-template-columns', 'overflow-x', 'overflow-y', 'cursor', 'transform', 'outline-style'];

const tidur = (ms) => new Promise((r) => setTimeout(r, ms));
const pendek = (u) => String(u).replace(BASE, '').replace(/\?v=\d+$/, '').slice(0, 140);
const jalur = (u) => { try { return new URL(u).pathname.replace(/\/+$/, ''); } catch { return String(u); } };

// ---------------------------------------------------------------- akun uji
function akunUji(aksi) {
    const keluaran = execFileSync('php', ['spark', 'dev:akun-uji', aksi], { cwd: AKAR, encoding: 'utf8' });
    if (aksi !== 'buat') return null;
    const baris = keluaran.split(/\r?\n/).find((b) => b.trim().startsWith('{'));
    if (!baris) throw new Error('dev:akun-uji tidak mengembalikan JSON: ' + keluaran);
    return JSON.parse(baris);
}

// ---------------------------------------------------------------- Chrome lewat CDP
class Cdp {
    static async buka() {
        const port = 9300 + Math.floor(Math.random() * 600);
        const profil = mkdtempSync(join(process.env.UJI_PROFIL_DIR ?? tmpdir(), 'uji-peramban-'));
        const proses = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profil}`, '--no-first-run',
            '--no-default-browser-check', '--disable-gpu', '--hide-scrollbars', '--disable-extensions', '--mute-audio', 'about:blank'],
            { stdio: 'ignore' });
        let alamat = null;
        for (let i = 0; i < 80 && !alamat; i++) {
            try {
                const daftar = await (await fetch(`http://127.0.0.1:${port}/json`)).json();
                alamat = daftar.find((x) => x.type === 'page')?.webSocketDebuggerUrl ?? null;
            } catch { /* Chrome belum siap */ }
            if (!alamat) await tidur(250);
        }
        if (!alamat) {
            proses.kill();
            throw new Error('Chrome tidak bisa dibuka: ' + CHROME);
        }
        const ws = new WebSocket(alamat);
        await new Promise((ok, gagal) => { ws.onopen = ok; ws.onerror = () => gagal(new Error('WebSocket CDP gagal tersambung')); });
        return new Cdp(proses, profil, ws);
    }

    constructor(proses, profil, ws) {
        Object.assign(this, { proses, profil, ws, n: 0, janji: new Map(), pendengar: [] });
        ws.onmessage = (e) => {
            const m = JSON.parse(e.data);
            if (m.id !== undefined && this.janji.has(m.id)) {
                const { ok, gagal, waktu } = this.janji.get(m.id);
                clearTimeout(waktu);
                this.janji.delete(m.id);
                if (m.error) gagal(new Error(m.error.message)); else ok(m.result);
            } else if (m.method) {
                for (const f of this.pendengar) f(m.method, m.params);
            }
        };
        // Sambungan ke Chrome putus → semua perintah yang menunggu langsung gagal (jangan menggantung selamanya).
        ws.onclose = () => {
            for (const [id, { gagal, waktu }] of this.janji) { clearTimeout(waktu); gagal(new Error('sambungan CDP ke Chrome terputus')); this.janji.delete(id); }
        };
    }

    // Tiap perintah CDP dibatasi 30 detik (2026-10-14: alat sempat menunggu jawaban Chrome yang tak pernah datang, 9+ menit tanpa kabar).
    kirim(method, params = {}) {
        return new Promise((ok, gagal) => {
            const id = ++this.n;
            const waktu = setTimeout(() => {
                if (this.janji.has(id)) { this.janji.delete(id); gagal(new Error(`perintah CDP ${method} tidak dijawab Chrome dalam 30 detik`)); }
            }, 30000);
            this.janji.set(id, { ok, gagal, waktu });
            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }

    async nilai(ekspresi) {
        const r = await this.kirim('Runtime.evaluate', { expression: ekspresi, returnByValue: true, awaitPromise: true });
        if (r.exceptionDetails) throw new Error('evaluate: ' + (r.exceptionDetails.exception?.description ?? r.exceptionDetails.text));
        return r.result.value;
    }

    async tutup() {
        try { this.ws.close(); } catch { /* sudah tertutup */ }
        this.proses.kill();
        await tidur(800);
        try { rmSync(this.profil, { recursive: true, force: true }); } catch { /* Chrome masih memegang berkas: biarkan */ }
    }
}

// Kejadian halaman yang sedang dibuka (diatur ulang tiap pindah halaman).
const kj = { muat: false, jalan: new Map(), galat: [], dialog: [] };

function dengarkan(c) {
    c.pendengar.push((m, p) => {
        if (m === 'Page.loadEventFired') kj.muat = true;
        else if (m === 'Network.requestWillBeSent') kj.jalan.set(p.requestId, p.request.url);
        else if (m === 'Network.loadingFinished') kj.jalan.delete(p.requestId);
        else if (m === 'Network.loadingFailed') {
            const url = kj.jalan.get(p.requestId);
            kj.jalan.delete(p.requestId);
            if (!p.canceled && url && !url.startsWith('data:')) kj.galat.push(`gagal muat ${pendek(url)} (${p.errorText})`);
        } else if (m === 'Network.responseReceived') {
            if (p.type !== 'Document' && p.response.status >= 400) kj.galat.push(`HTTP ${p.response.status} ${pendek(p.response.url)}`);
        } else if (m === 'Runtime.exceptionThrown') {
            kj.galat.push('JS: ' + String(p.exceptionDetails.exception?.description ?? p.exceptionDetails.text).split('\n')[0].slice(0, 200));
        } else if (m === 'Runtime.consoleAPICalled' && p.type === 'error') {
            kj.galat.push('console.error: ' + p.args.map((a) => a.value ?? a.description ?? '').join(' ').slice(0, 200));
        } else if (m === 'Log.entryAdded' && p.entry.level === 'error' && !['security', 'network', 'violation'].includes(p.entry.source)) {
            kj.galat.push('log: ' + p.entry.text.slice(0, 200));
        } else if (m === 'Page.javascriptDialogOpening') {
            kj.dialog.push(p.type + ': ' + p.message.slice(0, 80));
            c.kirim('Page.handleJavaScriptDialog', { accept: p.type === 'beforeunload' }).catch(() => {});
        }
    });
}

async function tungguMuat() {
    for (const batas = Date.now() + 20000; !kj.muat && Date.now() < batas;) await tidur(50);
    // Tunggu jaringan tenang 500 ms (fetch dari Alpine/grid), paling lama 6 detik.
    let tenang = Date.now();
    for (const batas = Date.now() + 6000; Date.now() < batas;) {
        if (kj.jalan.size > 0) tenang = Date.now();
        else if (Date.now() - tenang >= 500) break;
        await tidur(50);
    }
    await tidur(150);
}

function aturUlang() {
    kj.muat = false;
    kj.jalan.clear();
    kj.galat = [];
    kj.dialog = [];
}

async function buka(c, url) {
    aturUlang();
    await c.kirim('Page.navigate', { url });
    await tungguMuat();
    // Halaman yang menguji dirinya sendiri (ui-kit ?uji=1) menyalakan window.__ujiSibuk → tunggu sampai selesai (maks 15 detik).
    for (const batas = Date.now() + 15000; Date.now() < batas;) {
        if (!(await c.nilai('window.__ujiSibuk === true').catch(() => false))) break;
        await tidur(100);
    }
}

// ---------------------------------------------------------------- fungsi yang dijalankan DI DALAM halaman
function pendengarCsp() {
    window.__ujiCsp = [];
    document.addEventListener('securitypolicyviolation', (e) => {
        window.__ujiCsp.push({
            d: e.effectiveDirective || e.violatedDirective, b: String(e.blockedURI || '').slice(0, 80),
            s: String(e.sample || '').slice(0, 60), f: String(e.sourceFile || '').split('/').slice(-2).join('/'), l: e.lineNumber,
        });
    }, true);
}

function infoHalaman() {
    const nav = performance.getEntriesByType('navigation')[0];
    const html = document.documentElement.outerHTML;
    return {
        url: location.href,
        status: nav ? nav.responseStatus : 0,
        php: /Whoops!|ErrorException|Undefined (variable|array key|index|offset|property)|Fatal error|Parse error/.test(html),
        csp: (window.__ujiCsp || []).slice(0, 2000),
        inter: [...document.fonts].some((f) => f.family.replace(/["']/g, '') === 'Inter' && f.status === 'loaded'),
        alpine: typeof window.Alpine === 'object' && window.Alpine ? String(window.Alpine.version || 'ada') : null,
        elemen: document.getElementsByTagName('*').length,
        uji: typeof window.__ujiHasil === 'string' ? window.__ujiHasil : null,
    };
}

function isiLogin(user, sandi) {
    const f = document.querySelector('form');
    f.querySelector('[name=login]').value = user;
    f.querySelector('[name=password]').value = sandi;
    f.submit();
    return true;
}

function potretHalaman(PROP) {
    const kanvas = document.createElement('canvas');
    kanvas.width = kanvas.height = 1;
    const g = kanvas.getContext('2d', { willReadFrequently: true });
    const simpan = new Map();
    // Semua warna (rgb, oklch, oklab, color-mix yang sudah dihitung) diseragamkan lewat kanvas → "r,g,b,a" atau "t" (transparan).
    const warna = (v) => {
        if (!simpan.has(v)) {
            g.clearRect(0, 0, 1, 1);
            g.fillStyle = '#000';
            g.fillStyle = v;
            g.fillRect(0, 0, 1, 1);
            const d = g.getImageData(0, 0, 1, 1).data;
            simpan.set(v, d[3] === 0 ? 't' : d[0] + ',' + d[1] + ',' + d[2] + ',' + (d[3] / 255).toFixed(2));
        }
        return simpan.get(v);
    };
    const FUNGSI_WARNA = /(?:rgba?|hsla?|oklch|oklab|lab|lch|color)\([^()]*\)/g;
    // Bayangan: lapisan kosong (transparan / semua 0) dibuang — v3 & v4 menyusun lapisan cincin+bayangan secara berbeda.
    const bayangan = (v) => (v === 'none' ? 'none' : v.split(/,(?![^(]*\))/).map((lapis) => {
        const w = (lapis.match(FUNGSI_WARNA) || ['transparent'])[0];
        const sisa = lapis.replace(FUNGSI_WARNA, ' ').trim().split(/\s+/);
        return { w: warna(w), u: sisa.filter((x) => x !== 'inset').map((x) => Math.round(parseFloat(x) * 2) / 2), inset: sisa.includes('inset') };
    }).filter((l) => l.w !== 't' && l.u.some((x) => x !== 0)).map((l) => l.w + '|' + l.u.join(' ') + (l.inset ? '|inset' : '')).join(';') || 'none');
    // Transform efektif: v4 memakai properti translate/rotate/scale terpisah, v3 menaruh semuanya di transform → disatukan jadi satu
    // matriks (urutan CSS: translate → rotate → scale → transform), supaya keduanya bisa dibandingkan.
    const efektif = (el, cs, t) => {
        let m = new DOMMatrix();
        const tr = cs.getPropertyValue('translate');
        if (tr && tr !== 'none') {
            const kotak = el.getBoundingClientRect();
            const lebar = el.offsetWidth ?? kotak.width;
            const tinggi = el.offsetHeight ?? kotak.height;
            const [x = '0px', y = '0px'] = tr.split(/\s+/);
            const px = (v, ukuran) => (v.endsWith('%') ? (parseFloat(v) / 100) * ukuran : parseFloat(v) || 0);
            m = m.translate(px(x, lebar), px(y, tinggi));
        }
        const rot = cs.getPropertyValue('rotate');
        if (rot && rot !== 'none') m = m.rotate(parseFloat(rot));
        const sk = cs.getPropertyValue('scale');
        if (sk && sk !== 'none') {
            const [sx, sy = sx] = sk.split(/\s+/).map(Number);
            m = m.scale(sx, sy);
        }
        if (t && t !== 'none') m = m.multiply(new DOMMatrix(t));
        if (m.isIdentity) return t === 'none' && tr === 'none' && rot === 'none' && sk === 'none' ? 'none' : 'matrix(1, 0, 0, 1, 0, 0)';
        const r = (x) => Math.round(x * 1e4) / 1e4 + 0;
        return `matrix(${r(m.a)}, ${r(m.b)}, ${r(m.c)}, ${r(m.d)}, ${r(m.e)}, ${r(m.f)})`;
    };
    const iW = PROP.indexOf('width');
    const iH = PROP.indexOf('height');
    const LEWATI = new Set(['SCRIPT', 'STYLE', 'TEMPLATE', 'NOSCRIPT', 'LINK', 'META', 'BR']);
    const hasil = [];
    const jalan = (el, kunci) => {
        if (el.getClientRects().length > 0) {
            const cs = getComputedStyle(el);
            const r = el.getBoundingClientRect();
            const v = PROP.map((p) => {
                const x = cs.getPropertyValue(p);
                if (p.endsWith('color')) return warna(x);
                if (p === 'box-shadow') return bayangan(x);
                if (p === 'background-image') return x === 'none' ? 'none' : (x.includes('gradient') ? 'gradien' : 'gambar');
                if (p === 'font-family') return x.split(',')[0].replace(/["']/g, '').trim();
                if (p === 'transform') return efektif(el, cs, x);
                return x;
            });
            v[iW] = Math.round(r.width * 2) / 2;
            v[iH] = Math.round(r.height * 2) / 2;
            const kelas = (el.getAttribute('class') || '').trim().split(/\s+/).filter(Boolean).slice(0, 8).join('.');
            hasil.push([kunci, el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (kelas ? '.' + kelas : ''), ...v]);
        }
        if (el.namespaceURI === 'http://www.w3.org/2000/svg') return;
        let i = 0;
        for (const anak of el.children) {
            i++;
            if (!LEWATI.has(anak.tagName)) jalan(anak, kunci + '>' + i);
        }
    };
    jalan(document.body, 'b');
    return hasil;
}

// Label pendek elemen untuk laporan: tag#id.kelas (6 kelas pertama).
function labelElemen(el) {
    const kelas = (el.getAttribute('class') || '').trim().split(/\s+/).filter(Boolean).slice(0, 6).join('.');
    return el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (kelas ? '.' + kelas : '');
}

// Halaman melebar ke samping (ada gulir mendatar)? Laporkan elemen terluar yang menjorok melewati tepi kanan layar
// (elemen di dalam wadah ber-overflow selain visible — mis. tabel-bungkus yang memang digulir — tidak dihitung).
function cekLebar(labelElemen) {
    const W = document.documentElement.clientWidth;
    const lebar = document.documentElement.scrollWidth;
    if (lebar <= W + 1) return null;
    const terkurung = (el) => {
        for (let p = el.parentElement; p && p !== document.body && p !== document.documentElement; p = p.parentElement) {
            if (getComputedStyle(p).overflowX !== 'visible') return true;
        }
        return false;
    };
    const menjorok = (el) => {
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.right > W + 1 && getComputedStyle(el).position !== 'fixed' && !terkurung(el);
    };
    const elemen = [];
    for (const el of document.body.querySelectorAll('*')) {
        if (!menjorok(el)) continue;
        if (el.parentElement && el.parentElement !== document.body && menjorok(el.parentElement)) continue;
        elemen.push(labelElemen(el) + ` (sampai ${Math.round(el.getBoundingClientRect().right)}px)`);
        if (elemen.length >= 4) break;
    }
    return { lebar, layar: W, elemen };
}

// Kontras tulisan WCAG 2.x AA: 4,5:1, huruf besar (≥ 24px, atau ≥ 18,66px tebal) 3:1. Latar = tumpukan warna latar leluhur
// (berhenti di latar pertama yang pekat; latar bergambar/gradien dilewati karena tak bisa dihitung). Opasitas leluhur ikut dihitung.
// Elemen nonaktif (disabled / aria-disabled) dikecualikan WCAG.
function cekKontras(labelElemen) {
    const kanvas = document.createElement('canvas');
    kanvas.width = kanvas.height = 1;
    const g = kanvas.getContext('2d', { willReadFrequently: true });
    const simpan = new Map();
    const urai = (v) => {
        if (!simpan.has(v)) {
            g.clearRect(0, 0, 1, 1);
            g.fillStyle = '#000';
            g.fillStyle = v;
            g.fillRect(0, 0, 1, 1);
            const d = g.getImageData(0, 0, 1, 1).data;
            simpan.set(v, [d[0], d[1], d[2], d[3] / 255]);
        }
        return simpan.get(v);
    };
    const campur = (atas, bawah, a = atas[3]) => [0, 1, 2].map((i) => atas[i] * a + bawah[i] * (1 - a));
    const lum = (w) => {
        const [r, gg, b] = w.map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; });
        return 0.2126 * r + 0.7152 * gg + 0.0722 * b;
    };
    const latarDari = (el) => {
        const lapis = [];
        for (let p = el; p; p = p.parentElement) {
            const cs = getComputedStyle(p);
            if (cs.backgroundImage !== 'none' && !cs.backgroundImage.startsWith('url(')) return null;
            const w = urai(cs.backgroundColor);
            if (w[3] > 0) { lapis.push(w); if (w[3] >= 1) break; }
        }
        let hasil = [255, 255, 255];
        for (let i = lapis.length - 1; i >= 0; i--) hasil = campur(lapis[i], hasil);
        return hasil;
    };
    const hex = (w) => '#' + w.map((v) => Math.round(v).toString(16).padStart(2, '0')).join('');
    const ISIAN = 'input:not([type=hidden],[type=checkbox],[type=radio],[type=file],[type=range],[type=color],[type=submit],[type=button]),select,textarea';
    const hasil = [];
    let diperiksa = 0;
    for (const el of document.body.querySelectorAll('*')) {
        if (el.closest('svg,script,style,template,noscript')) continue;
        const isian = el.matches(ISIAN);
        const teks = isian ? String(el.value ?? '').trim()
            : [...el.childNodes].filter((n) => n.nodeType === 3).map((n) => n.textContent).join('').trim();
        if (!teks) continue;
        const r = el.getBoundingClientRect();
        if (r.width <= 1 || r.height <= 1 || r.right <= 0 || r.left >= innerWidth) continue; // laci tertutup di luar layar
        const cs = getComputedStyle(el);
        if (cs.visibility !== 'visible') continue;
        if (el.closest(':disabled,[aria-disabled="true"],[inert]')) continue;
        let opasitas = 1;
        for (let p = el; p; p = p.parentElement) opasitas *= Number(getComputedStyle(p).opacity);
        if (opasitas <= 0.05) continue;
        const latar = latarDari(el);
        if (!latar) continue;
        const w = urai(cs.color);
        const depan = campur(w, latar, w[3] * opasitas);
        const [a, b] = [lum(depan), lum(latar)];
        const rasio = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
        const ukuran = parseFloat(cs.fontSize);
        const besar = ukuran >= 24 || (ukuran >= 18.66 && Number(cs.fontWeight) >= 700);
        diperiksa++;
        if (rasio + 0.005 < (besar ? 3 : 4.5)) {
            hasil.push({ el: labelElemen(el), teks: teks.slice(0, 30), depan: hex(depan), latar: hex(latar), rasio: Math.round(rasio * 100) / 100 });
            if (hasil.length >= 300) break;
        }
    }
    return { diperiksa, gagal: hasil };
}

// Elemen yang bisa dicapai Tab tapi tidak menampilkan tanda fokus apa pun (garis luar, cincin/bayangan, warna garis, latar,
// tulisan, garis bawah) pada dirinya atau dua leluhurnya (pola :focus-within / :has). Hanya dihitung bila :focus-visible berlaku.
function cekFokus(labelElemen) {
    const PILIH = 'a[href],button:not([disabled]),input:not([type=hidden]):not([disabled]),select:not([disabled]),textarea:not([disabled]),'
        + 'summary,[tabindex]:not([tabindex="-1"])';
    const foto = (el) => {
        if (!el || el === document.body) return '';
        const cs = getComputedStyle(el);
        return [cs.outlineStyle, cs.outlineWidth, cs.outlineColor, cs.boxShadow, cs.borderTopColor, cs.borderBottomColor, cs.backgroundColor,
            cs.color, cs.textDecorationLine].join('|');
    };
    const daftar = [...document.querySelectorAll(PILIH)].filter((el) => {
        const r = el.getBoundingClientRect();
        return r.width > 1 && r.height > 1 && r.right > 0 && r.left < innerWidth && getComputedStyle(el).visibility === 'visible' && !el.closest('[inert]');
    }).slice(0, 300);
    const gagal = [];
    let diperiksa = 0;
    for (const el of daftar) {
        const rantai = [el, el.parentElement, el.parentElement?.parentElement].filter((x) => x && x !== document.body);
        // Transisi dimatikan sementara (CSSOM, bukan atribut style= → tidak melanggar CSP): gaya fokus langsung terbaca nilai akhirnya.
        const transisi = rantai.map((x) => x.style.transition);
        for (const x of rantai) x.style.transition = 'none';
        const sebelum = rantai.map(foto);
        el.focus({ preventScroll: true });
        const sah = document.activeElement === el && el.matches(':focus-visible');
        if (sah) {
            diperiksa++;
            const sesudah = rantai.map(foto);
            if (sebelum.every((v, i) => v === sesudah[i])) gagal.push(labelElemen(el));
        }
        el.blur();
        rantai.forEach((x, i) => { x.style.transition = transisi[i]; });
    }
    return { diperiksa, gagal };
}

// ---------------------------------------------------------------- pembanding potret
const GRUP_PROP = {
    ukuran: ['width', 'height', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding-top', 'padding-right', 'padding-bottom',
        'padding-left', 'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width', 'gap', 'grid-template-columns'],
    sudut: ['border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius'],
    huruf: ['font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'text-align', 'text-transform', 'white-space'],
    warna: ['color', 'background-color', 'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color'],
    bayangan: ['box-shadow'],
};
const grupDari = (p) => Object.keys(GRUP_PROP).find((g) => GRUP_PROP[g].includes(p)) ?? 'lain';

function keLab(rgba) {
    let [r, g, b, a] = rgba === 't' ? [255, 255, 255, 0] : rgba.split(',').map(Number);
    r = a * r + (1 - a) * 255; g = a * g + (1 - a) * 255; b = a * b + (1 - a) * 255; // komposit di atas putih
    const lin = (v) => { v /= 255; return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
    const [R, G, B] = [lin(r), lin(g), lin(b)];
    const f = (t) => (t > 0.008856 ? Math.cbrt(t) : 7.787 * t + 16 / 116);
    const X = f((R * 0.4124 + G * 0.3576 + B * 0.1805) / 0.95047);
    const Y = f(R * 0.2126 + G * 0.7152 + B * 0.0722);
    const Z = f((R * 0.0193 + G * 0.1192 + B * 0.9505) / 1.08883);
    return [116 * Y - 16, 500 * (X - Y), 200 * (Y - Z), a];
}

function bedaWarna(x, y) {
    if (x === y) return 0;
    const [L1, A1, B1, a1] = keLab(x);
    const [L2, A2, B2, a2] = keLab(y);
    return Math.abs(a1 - a2) > 0.15 ? 99 : Math.hypot(L1 - L2, A1 - A2, B1 - B2);
}

// Nilai yang tampilannya sama walau tulisannya berbeda antara v3 dan v4.
function setara(p, v) {
    if (p.endsWith('-radius') && parseFloat(v) >= 9999) return 'penuh'; // rounded-full: 9999px (v3) vs calc(infinity * 1px) (v4)
    if (p === 'transform' && v === 'none') return 'matrix(1, 0, 0, 1, 0, 0)';
    return v;
}

const angka = (s) => String(s).match(/-?\d+(\.\d+)?/g)?.map(Number) ?? [];
function samaAngka(a, b, toleransi) {
    const x = angka(a); const y = angka(b);
    if (String(a).replace(/-?\d+(\.\d+)?/g, '#') !== String(b).replace(/-?\d+(\.\d+)?/g, '#')) return false;
    return x.length === y.length && x.every((v, i) => Math.abs(v - y[i]) <= toleransi);
}

function banding(lama, baru) {
    const iProp = (p) => PROP.indexOf(p) + 2;
    const jenis = new Map(); // "prop: a → b" → {n, contoh[]}
    const kecil = new Map();
    const perHalaman = [];
    const rinci = {};
    for (const url of Object.keys(baru)) {
        if (!lama[url]) { perHalaman.push({ url, catatan: 'tidak ada di potret lama' }); continue; }
        const A = new Map(lama[url].map((r) => [r[0], r]));
        const B = new Map(baru[url].map((r) => [r[0], r]));
        const s = { url, elemen: B.size, hilang: 0, tambah: 0, sebab: 0, ikut: 0, pindah: 0, warnaKecil: 0, contohStruktur: [] };
        for (const k of A.keys()) if (!B.has(k)) { s.hilang++; if (s.contohStruktur.length < 3) s.contohStruktur.push('- ' + k + ' ' + A.get(k)[1]); }
        for (const [k, b] of B) {
            const a = A.get(k);
            if (!a) { s.tambah++; if (s.contohStruktur.length < 3) s.contohStruktur.push('+ ' + k + ' ' + b[1]); continue; }
            const beda = [];
            for (const p of PROP) {
                const x = setara(p, a[iProp(p)]); const y = setara(p, b[iProp(p)]);
                if (x === y) continue;
                // outline-none v3 (garis transparan) vs outline-hidden v4 (tanpa garis): sama-sama tak terlihat.
                if (p === 'outline-style' && x === 'solid' && y === 'none') continue;
                const grup = grupDari(p);
                if (grup === 'warna') {
                    const d = bedaWarna(x, y);
                    if (d <= 3) continue;
                    if (d <= 10) {
                        s.warnaKecil++;
                        const kunci = `${p}: ${x} → ${y}`;
                        kecil.set(kunci, (kecil.get(kunci) ?? 0) + 1);
                        continue;
                    }
                } else if (typeof x === 'number' && typeof y === 'number') {
                    if (Math.abs(x - y) <= 1) continue;
                } else if (p !== 'box-shadow' && /\d/.test(String(x)) && samaAngka(x, y, p === 'transform' ? 0.02 : 1)) continue;
                beda.push([p, x, y]);
            }
            if (!beda.length) continue;
            const hanyaUkuran = beda.every(([p]) => p === 'width' || p === 'height');
            if (hanyaUkuran) { s.ikut++; continue; }
            // space-y / space-x v4 memindah jarak dari atas ke bawah (kiri ke kanan); jumlah jaraknya tetap → bukan perubahan.
            if (beda.every(([p]) => p === 'width' || p === 'height' || p.startsWith('margin-'))) {
                const m = (r, p) => parseFloat(r[iProp(p)]) || 0;
                const tegak = (r) => m(r, 'margin-top') + m(r, 'margin-bottom');
                const datar = (r) => m(r, 'margin-left') + m(r, 'margin-right');
                if (Math.abs(tegak(a) - tegak(b)) <= 1 && Math.abs(datar(a) - datar(b)) <= 1) { s.pindah++; continue; }
            }
            s.sebab++;
            (rinci[url] ??= []).push({ k, label: b[1], beda });
            for (const [p, x, y] of beda) {
                if (p === 'width' || p === 'height') continue;
                const kunci = `${p}: ${x} → ${y}`;
                const j = jenis.get(kunci) ?? { n: 0, grup: grupDari(p), contoh: [] };
                j.n++;
                if (j.contoh.length < 2) j.contoh.push(`${url}  ${b[1].slice(0, 90)}`);
                jenis.set(kunci, j);
            }
        }
        perHalaman.push(s);
    }
    return { jenis, kecil, perHalaman, rinci };
}

function cetakBanding({ jenis, kecil, perHalaman }) {
    console.log('\n== Banding gaya: potret lama → sekarang ==');
    console.log('  (sebab = elemen yang gayanya sendiri berubah; ikut = hanya lebar/tinggi ikut bergeser; pindah = jarak space-y/x pindah sisi;'
        + ' warna kecil = ΔE 3–10)');
    let total = { elemen: 0, sebab: 0, ikut: 0, pindah: 0, warnaKecil: 0, hilang: 0, tambah: 0 };
    for (const s of perHalaman) {
        if (s.catatan) { console.log(`  ${s.url}: ${s.catatan}`); continue; }
        for (const k of Object.keys(total)) total[k] += s[k];
        if (s.sebab || s.ikut || s.hilang || s.tambah) {
            console.log(`  ${s.url.padEnd(46)} elemen ${String(s.elemen).padStart(5)} · sebab ${s.sebab} · ikut ${s.ikut} · pindah ${s.pindah}`
                + ` · warna kecil ${s.warnaKecil}` + (s.hilang || s.tambah ? ` · hilang ${s.hilang} / tambah ${s.tambah}` : ''));
            for (const c of s.contohStruktur) console.log('      ' + c.slice(0, 150));
        }
    }
    console.log(`  TOTAL: ${perHalaman.length} halaman · ${total.elemen} elemen · sebab ${total.sebab} · ikut ${total.ikut} · pindah ${total.pindah}`
        + ` · warna kecil ${total.warnaKecil} · hilang ${total.hilang} / tambah ${total.tambah}`);
    const urut = [...jenis.entries()].sort((a, b) => b[1].n - a[1].n);
    if (urut.length) {
        console.log(`\n  Jenis perubahan (${urut.length} jenis, 60 teratas):`);
        for (const [k, j] of urut.slice(0, 60)) {
            console.log(`  ${String(j.n).padStart(6)}× [${j.grup}] ${k.slice(0, 150)}`);
            for (const c of j.contoh) console.log(`           mis. ${c}`);
        }
    }
    const urutKecil = [...kecil.entries()].sort((a, b) => b[1] - a[1]);
    if (urutKecil.length) {
        console.log(`\n  Geseran warna kecil (${urutKecil.length} jenis, 12 teratas):`);
        for (const [k, n] of urutKecil.slice(0, 12)) console.log(`  ${String(n).padStart(6)}× ${k}`);
    }
}

// --mode: tulis atribut tema di <html> halaman yang baru dibuka (ApexCharts tetap warna awal).
async function pasangMode(c) {
    if (!MODE) return;
    const [mode, warna, sidebar] = MODE_TEMA[MODE];
    await c.nilai(`(() => { const h = document.documentElement; h.dataset.mode = ${JSON.stringify(mode)}; h.dataset.warna = ${JSON.stringify(warna)};
        h.dataset.sidebar = ${JSON.stringify(sidebar)}; return true; })()`);
    // Tunggu semua transisi warna (transition-colors) selesai — di halaman besar bisa lebih lama dari durasinya (maks 5 detik).
    await c.nilai(`(async () => {
        for (let i = 0; i < 100; i++) {
            await new Promise((r) => setTimeout(r, 50));
            if (!document.getAnimations().some((a) => a instanceof CSSTransition && a.playState !== 'finished')) return true;
        }
        return false;
    })()`);
}

// Pemeriksaan per halaman sesudah potret: melebar (selalu), kontras & fokus (bila diminta; fokus terakhir karena mengubah keadaan).
async function cekTambahan(c) {
    const lab = String(labelElemen);
    return {
        lebar: await c.nilai(`(${cekLebar})(${lab})`),
        kontras: ARG.kontras ? await c.nilai(`(${cekKontras})(${lab})`) : null,
        fokus: ARG.fokus ? await c.nilai(`(${cekFokus})(${lab})`) : null,
    };
}

// ---------------------------------------------------------------- utama
const mulai = Date.now();
const potret = {};
const hasil = [];
let c = null;
let akun = null;
try {
    akun = akunUji('buat');
    c = await Cdp.buka();
    dengarkan(c);
    for (const d of ['Page', 'Runtime', 'Network', 'Log']) await c.kirim(d + '.enable');
    await c.kirim('Page.addScriptToEvaluateOnNewDocument', { source: `(${pendengarCsp})()` });
    await c.kirim('Emulation.setDeviceMetricsOverride', { width: LEBAR, height: TINGGI, deviceScaleFactor: 1, mobile: LEBAR < 768 });
    await c.kirim('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    // Chrome tanpa layar tidak punya fokus jendela → :focus / :focus-visible tak pernah berlaku tanpa emulasi ini (--fokus).
    await c.kirim('Emulation.setFocusEmulationEnabled', { enabled: true });
    const butuhPotret = Boolean(ARG.potret || ARG.banding);

    for (const [i, peran] of PERAN.entries()) {
        const user = akun.akun[peran];
        if (!user) { console.log(`[GAGAL] peran "${peran}" tidak ada (tersedia: ${Object.keys(akun.akun).join(', ')})`); process.exitCode = 1; continue; }
        await c.kirim('Network.clearBrowserCookies');
        const daftar = [];
        if (i === 0 && !HALAMAN.length && (!GRUP || GRUP === 'login') && (!AWALAN.length || AWALAN.some((a) => 'admin/login'.startsWith(a)))) {
            daftar.push({ url: BASE + '/admin/login', keluar: true });
        }

        // Halaman login dulu (belum masuk), lalu masuk.
        for (const h of daftar) {
            await buka(c, h.url);
            await pasangMode(c);
            const info = await c.nilai(`(${infoHalaman})()`);
            const baris = { ...info, peran: '-', url: '/admin/login', akhir: pendek(info.url), galat: [...kj.galat], dialog: [...kj.dialog], dialihkan: false };
            if (butuhPotret) potret['/admin/login'] = await c.nilai(`(${potretHalaman})(${JSON.stringify(PROP)})`);
            hasil.push(Object.assign(baris, await cekTambahan(c)));
        }
        await buka(c, BASE + '/admin/login');
        aturUlang();
        await c.nilai(`(${isiLogin})(${JSON.stringify(user)}, ${JSON.stringify(akun.sandi)})`);
        await tungguMuat();
        const sesudahMasuk = await c.nilai('location.pathname');
        if (sesudahMasuk.includes('/admin/login')) throw new Error(`login ${user} gagal (masih di ${sesudahMasuk})`);

        const tautan = HALAMAN.length ? [] : await c.nilai(`[...document.querySelectorAll('aside a[href]')].map((a) => a.href)`);
        const sumber = HALAMAN.length ? HALAMAN : [...tautan.map((t) => t.split('#')[0].slice(BASE.length + 1)), ...TAMBAHAN];
        const semua = [...new Set(sumber.map((t) => BASE + '/' + t))]
            .filter((t) => t.startsWith(BASE + '/admin/') && !/\/logout\b/.test(t))
            .filter((t) => !AWALAN.length || AWALAN.some((a) => (t.slice(BASE.length + 1).split('?')[0] + '/').startsWith(a + '/')))
            .filter((t) => !GRUP || PETA_GRUP[GRUP].url.length === 0 || grupAlamat(t.slice(BASE.length + 1).split('?')[0]) === GRUP);
        for (const [k, url] of semua.entries()) {
            if (ARG.progres) process.stderr.write(`[${peran}] ${k + 1}/${semua.length} ${pendek(url)}\n`);
            try {
                await buka(c, url);
                await pasangMode(c);
                const info = await c.nilai(`(${infoHalaman})()`);
                const dialihkan = jalur(info.url) !== jalur(url);
                const baris = { ...info, peran, url: pendek(url), akhir: pendek(info.url), galat: [...kj.galat], dialog: [...kj.dialog], dialihkan };
                if (butuhPotret && i === 0 && !dialihkan) potret[pendek(url)] = await c.nilai(`(${potretHalaman})(${JSON.stringify(PROP)})`);
                hasil.push(Object.assign(baris, dialihkan ? {} : await cekTambahan(c)));
            } catch (e) {
                // Satu halaman bermasalah (mis. Chrome tak menjawab) → dicatat GAGAL, uji lanjut ke halaman berikutnya.
                hasil.push({ url: pendek(url), akhir: pendek(url), peran, status: 0, php: false, csp: [], inter: false, alpine: null, uji: null,
                    galat: [...kj.galat, 'ALAT: ' + e.message], dialog: [...kj.dialog], dialihkan: false });
            }
        }
    }
} finally {
    if (c) await c.tutup();
    try { akunUji('hapus'); } catch (e) { console.log('[PERINGATAN] akun uji gagal dihapus: ' + e.message); }
}

// ---------------------------------------------------------------- laporan
console.log(`Uji peramban — ${BASE} (Chrome tanpa layar ${LEBAR}×${TINGGI}${LEBAR < 768 ? ' HP' : ''}, gerak dikurangi`
    + `${MODE ? ', mode ' + MODE : ''}) · ${((Date.now() - mulai) / 1000).toFixed(0)} dtk`);
const perArahan = new Map();
for (const peran of ['-', ...PERAN]) {
    const baris = hasil.filter((h) => h.peran === peran);
    if (!baris.length) continue;
    // Masalah yang sama di banyak halaman ditulis sekali. 404 berkas unggahan = data laptop yang tak lengkap, bukan galat kode.
    const dataLokal = (p) => /^HTTP 404 \/uploads\//.test(p);
    const masalah = new Map();
    for (const h of baris) {
        const pesan = [...(h.status >= 400 ? [`HTTP ${h.status} (halaman)`] : []), ...(h.php ? ['GALAT PHP di halaman'] : []), ...h.galat,
            ...h.dialog.map((d) => 'dialog ' + d)];
        for (const p of new Set(pesan)) masalah.set(p, [...(masalah.get(p) ?? []), h.url]);
    }
    const buruk = baris.filter((h) => h.status >= 400 || h.php || h.dialog.length || h.galat.some((g) => !dataLokal(g)));
    const cspHal = baris.filter((h) => h.csp.length);
    const nCsp = baris.reduce((n, h) => n + h.csp.length, 0);
    const label = peran === '-' ? 'login (belum masuk)' : peran;
    console.log(`\n[${label}] ${baris.length} halaman · bermasalah ${buruk.length} · dialihkan ${baris.filter((h) => h.dialihkan).length}`
        + ` · CSP ${nCsp} pelanggaran di ${cspHal.length} halaman · Inter termuat ${baris.filter((h) => h.inter).length}/${baris.length}`
        + ` · Alpine ${[...new Set(baris.map((h) => h.alpine ?? 'tidak ada'))].join('/')}`);
    for (const [p, urls] of masalah) {
        const di = urls.length === baris.length ? 'semua halaman' : `${urls.length} halaman: ${urls.slice(0, 4).join(', ')}${urls.length > 4 ? ', …' : ''}`;
        console.log(`  ${dataLokal(p) ? '[DATA] ' : '[GAGAL]'} ${p} — ${di}`);
    }
    for (const h of baris.filter((x) => x.dialihkan)) console.log(`  (dialihkan) ${h.url} → ${h.akhir}`);
    for (const h of baris.filter((x) => x.uji)) console.log(`  [UJI-DIRI] ${h.url}: ${h.uji}`);
    for (const h of baris) for (const v of h.csp) perArahan.set(v.d, (perArahan.get(v.d) ?? 0) + 1);
    if (buruk.length) process.exitCode = 1;
}
// Melebar / kontras / fokus: dikumpulkan dari semua peran, temuan yang sama ditulis sekali beserta halaman contohnya.
const melebar = hasil.filter((h) => h.lebar);
if (melebar.length) {
    console.log(`\n[LEBAR] ${melebar.length} halaman bergulir ke samping di layar ${LEBAR}px:`);
    for (const h of melebar) console.log(`  ${h.url} (${h.peran}): lebar ${h.lebar.lebar}px · ${h.lebar.elemen.join(' · ') || '(elemen tak terdeteksi)'}`);
    process.exitCode = 1;
}
const kelompok = (daftar) => {
    const m = new Map();
    for (const [kunci, url] of daftar) {
        const v = m.get(kunci) ?? { n: 0, hal: new Set() };
        v.n++; v.hal.add(url);
        m.set(kunci, v);
    }
    return [...m].sort((a, b) => b[1].n - a[1].n);
};
if (ARG.kontras) {
    const semua = hasil.filter((h) => h.kontras).flatMap((h) => h.kontras.gagal.map((g) => [`${g.rasio}:1 ${g.depan} di atas ${g.latar} · ${g.el} «${g.teks}»`, h.url]));
    const nPeriksa = hasil.reduce((n, h) => n + (h.kontras?.diperiksa ?? 0), 0);
    const grup = kelompok(semua.map(([k, u]) => [k.replace(/ «.*»$/, ''), u]));
    console.log(`\n[KONTRAS] ${nPeriksa} tulisan diperiksa · di bawah AA ${semua.length} (${grup.length} jenis)`);
    for (const [k, v] of grup.slice(0, Number(ARG['kontras-batas']) || 60)) {
        const contoh = semua.find(([x]) => x.startsWith(k))?.[0].match(/«.*»$/)?.[0] ?? '';
        console.log(`  ${String(v.n).padStart(4)}× ${k} ${contoh} — ${[...v.hal].slice(0, 3).join(', ')}${v.hal.size > 3 ? ` (+${v.hal.size - 3})` : ''}`);
    }
    if (semua.length) process.exitCode = 1;
}
if (ARG.fokus) {
    const semua = hasil.filter((h) => h.fokus).flatMap((h) => h.fokus.gagal.map((g) => [g, h.url]));
    const nPeriksa = hasil.reduce((n, h) => n + (h.fokus?.diperiksa ?? 0), 0);
    const grup = kelompok(semua);
    console.log(`\n[FOKUS] ${nPeriksa} elemen difokus · tanpa tanda fokus ${semua.length} (${grup.length} jenis)`);
    for (const [k, v] of grup.slice(0, 60)) console.log(`  ${String(v.n).padStart(4)}× ${k} — ${[...v.hal].slice(0, 3).join(', ')}${v.hal.size > 3 ? ` (+${v.hal.size - 3})` : ''}`);
    if (semua.length) process.exitCode = 1;
}

if (perArahan.size) {
    console.log('\nPelanggaran CSP per arahan: ' + [...perArahan.entries()].sort((a, b) => b[1] - a[1]).map(([d, n]) => `${d} ${n}`).join(' · '));
    const atas = [...hasil].filter((h) => h.csp.length).sort((a, b) => b.csp.length - a.csp.length).slice(0, 12);
    console.log('Halaman terbanyak: ' + atas.map((h) => `${h.url} ${h.csp.length}`).join(' · '));
    if (ARG['wajib-csp-nol']) process.exitCode = 1;
}

if (ARG.potret) {
    writeFileSync(ARG.potret, gzipSync(JSON.stringify({ dibuat: new Date().toISOString(), base: BASE, prop: PROP, halaman: potret })));
    const n = Object.values(potret).reduce((a, x) => a + x.length, 0);
    console.log(`\nPotret gaya disimpan: ${ARG.potret} (${Object.keys(potret).length} halaman, ${n} elemen)`);
}
if (ARG.banding) {
    // Boleh beberapa berkas potret (pisah koma) — digabung.
    const lama = { halaman: {} };
    for (const berkas of String(ARG.banding).split(',')) {
        const isi = JSON.parse(gunzipSync(readFileSync(berkas)).toString('utf8'));
        if (JSON.stringify(isi.prop) !== JSON.stringify(PROP)) throw new Error(`Daftar gaya di ${berkas} berbeda dengan alat ini — potret ulang dulu.`);
        Object.assign(lama.halaman, isi.halaman);
    }
    const b = banding(lama.halaman, potret);
    cetakBanding(b);
    if (ARG.rinci) {
        writeFileSync(ARG.rinci, JSON.stringify(b.rinci, null, 1));
        console.log(`\nRincian per elemen: ${ARG.rinci}`);
    }
}
