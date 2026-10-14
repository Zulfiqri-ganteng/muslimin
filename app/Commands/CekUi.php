<?php

namespace App\Commands;

use App\Libraries\MenuAdmin;
use App\Libraries\Tampilan;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Tampilan as TampilanConfig;

/**
 * Pemeriksa rombak tampilan admin per grup/fase (peta grup: Config\Tampilan; rancangan: docs/RENCANA-UPGRADE-TAMPILAN.md §5a).
 *
 *   php spark dev:cek-ui                       ringkasan semua grup (angka per grup)
 *   php spark dev:cek-ui --grup=master         rincian per berkas + nomor baris untuk satu grup
 *   php spark dev:cek-ui --grup=master --wajib-nol   keluar dengan kode 1 bila grup itu masih punya temuan (dipakai di akhir fase)
 *   php spark dev:cek-ui --json                peta grup sebagai JSON (dibaca tools/uji-peramban.mjs --grup=…)
 *
 * Siap CSP: <script> sebaris, on*= (onclick dll.), style=, <style>, href="javascript:", muatan dari luar (CDN), <script src> tanpa
 * nonce, dan isian Alpine yang ditolak Alpine versi CSP (diuji 2026-10-13 pada 3.15.12 di bawah CSP blokir): fungsi panah, template
 * literal, spread, ?. dan ??, global (Math, JSON, window, parseInt, confirm, …), fungsi global buatan sendiri, x-html, metode di
 * dalam objek x-data sebaris, menulis lewat $refs, x-data yang bukan Alpine.data, :style berupa teks. (BOLEH: form.nama = …,
 * metode teks/array seperti .toUpperCase() / .includes(), ternary, $dispatch, x-model, x-for.)
 * Aturan tampilan: kelas hidden + inline-* di satu elemen (tak tersembunyi di v4), hiasan terlarang (gradasi, rise/lift/reveal/orb,
 * emoji), sudut > rounded-lg, bayangan > shadow-sm, slate, <svg> tulisan tangan (info), ikon() yang berkasnya tidak ada.
 */
class CekUi extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:cek-ui';
    protected $description = 'Periksa view admin per grup rombak tampilan: aturan siap-CSP + aturan tampilan.';
    protected $usage       = 'dev:cek-ui [--grup=nama] [--wajib-nol] [--json]';
    protected $options     = [
        '--grup'      => 'Rincian satu grup (lihat Config\Tampilan).',
        '--wajib-nol' => 'Kode keluar 1 bila masih ada temuan (bersama --grup: hanya grup itu).',
        '--json'      => 'Cetak peta grup sebagai JSON.',
    ];

    /** Kolom ringkasan: kunci => [judul, jenis] (jenis csp|tampil|info). */
    private const KOLOM = [
        'script'      => ['<script>', 'csp'],
        'on'          => ['on*=', 'csp'],
        'style'       => ['style=', 'csp'],
        'tagstyle'    => ['<style>', 'csp'],
        'luar'        => ['CDN', 'csp'],
        'nonce'       => ['tanpa nonce', 'csp'],
        'alpine'      => ['Alpine✗', 'csp'],
        'bentrok'     => ['hidden+inline', 'tampil'],
        'hiasan'      => ['hiasan', 'tampil'],
        'sudut'       => ['sudut', 'tampil'],
        'bayangan'    => ['bayangan', 'tampil'],
        'slate'       => ['slate', 'tampil'],
        'ikon_hilang' => ['ikon✗', 'tampil'],
        'svg'         => ['<svg>', 'info'],
    ];

    private const GLOBAL_JS = 'Math|JSON|window|document|console|parseInt|parseFloat|Number|String|Boolean|Date|Intl|Object|Array|localStorage|'
        . 'sessionStorage|setTimeout|setInterval|location|navigator|confirm|alert|prompt|fetch|encodeURIComponent|decodeURIComponent|isNaN|isFinite|Ui';

    /** @var array<string, true> */
    private array $alpineData = [];

    /** @var array<string, true> */
    private array $fungsiGlobal = [];

    public function run(array $params)
    {
        $cfg = config(TampilanConfig::class);
        if (CLI::getOption('json') !== null) {
            CLI::write(json_encode(['grup' => $cfg->grup, 'alpineCsp' => $cfg->alpineCsp], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return EXIT_SUCCESS;
        }
        // CI4 membaca "--grup master"; bentuk "--grup=master" juga diterima.
        $pilih = CLI::getOption('grup');
        foreach (array_keys(CLI::getOptions()) as $opsi) {
            if (str_starts_with((string) $opsi, 'grup=')) {
                $pilih = substr((string) $opsi, 5);
            }
        }
        if ($pilih !== null && ! isset($cfg->grup[$pilih])) {
            CLI::error('Grup tidak dikenal: ' . $pilih . ' (ada: ' . implode(', ', array_keys($cfg->grup)) . ')');

            return EXIT_USER_INPUT;
        }
        $this->kumpulkanJs();

        $total = array_fill_keys(array_keys(self::KOLOM), 0);
        $baris = [];
        $rinci = [];
        foreach ($cfg->grup as $kunci => $g) {
            if ($pilih !== null && $kunci !== $pilih) {
                continue;
            }
            $jumlah = array_fill_keys(array_keys(self::KOLOM), 0);
            // Folder bertingkat (admin/partials ⊃ admin/partials/ui): tiap berkas hanya dihitung di grupnya sendiri (cocokan terpanjang).
            $berkas = array_values(array_filter($this->berkasGrup($g['view']), static fn (string $rel): bool => Tampilan::grupView($rel) === $kunci));
            foreach ($berkas as $rel) {
                foreach ($this->periksa($rel) as [$jenis, $nomor, $teks]) {
                    $jumlah[$jenis]++;
                    $rinci[$rel][] = [$jenis, $nomor, $teks];
                }
            }
            foreach ($jumlah as $k => $n) {
                $total[$k] += $n;
            }
            $baris[] = [$kunci, $g['fase'], count($berkas), $jumlah, in_array($kunci, $cfg->alpineCsp, true)];
        }

        // Rata kanan berdasar jumlah karakter (bukan bita — "✗" = 3 bita).
        $kanan = static fn (string $s, int $lebar): string => str_repeat(' ', max(0, $lebar - mb_strlen($s))) . $s;
        CLI::write('Cek rombak tampilan admin — ' . ($pilih ?? 'semua grup') . ' (aturan: docs/RENCANA-UPGRADE-TAMPILAN.md §4 & §5a)', 'yellow');
        $judul = str_pad('grup', 11) . str_pad('fase', 5) . str_pad('view', 5);
        foreach (self::KOLOM as [$j]) {
            $judul .= $kanan($j, mb_strlen($j) + 2);
        }
        CLI::write($judul . '  Alpine');
        foreach ($baris as [$kunci, $fase, $nView, $jumlah, $csp]) {
            $teks = str_pad($kunci, 11) . str_pad($fase, 5) . str_pad((string) $nView, 5);
            foreach (self::KOLOM as $k => [$j]) {
                $teks .= $kanan((string) $jumlah[$k], mb_strlen($j) + 2);
            }
            CLI::write($teks . '  ' . ($csp ? 'versi CSP' : 'biasa'));
        }
        $teks = str_pad('TOTAL', 21);
        foreach (self::KOLOM as $k => [$j]) {
            $teks .= $kanan((string) $total[$k], mb_strlen($j) + 2);
        }
        CLI::write($teks);
        $nCsp    = array_sum(array_intersect_key($total, array_filter(self::KOLOM, static fn (array $k): bool => $k[1] === 'csp')));
        $nTampil = array_sum(array_intersect_key($total, array_filter(self::KOLOM, static fn (array $k): bool => $k[1] === 'tampil')));
        CLI::write("Temuan siap-CSP: {$nCsp} · aturan tampilan: {$nTampil} · <svg> tulisan tangan (info): {$total['svg']}");

        if ($pilih === null) {
            $yatim = $this->viewTanpaGrup();
            CLI::write('View admin tanpa grup: ' . ($yatim === [] ? '0' : count($yatim) . ' → ' . implode(', ', $yatim)), $yatim === [] ? 'green' : 'red');
            $this->cekBuild();
        } else {
            foreach ($rinci as $rel => $daftar) {
                CLI::newLine();
                CLI::write($rel . ' (' . count($daftar) . ')', 'light_cyan');
                foreach ($daftar as [$jenis, $nomor, $teks]) {
                    CLI::write('  ' . str_pad((string) $nomor, 5, ' ', STR_PAD_LEFT) . '  ' . str_pad(self::KOLOM[$jenis][0], 12) . $teks);
                }
            }
        }

        if (CLI::getOption('wajib-nol') !== null && $nCsp + $nTampil > 0) {
            CLI::error('BELUM NOL: ' . ($nCsp + $nTampil) . ' temuan.');

            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }

    /**
     * Daftar berkas view (relatif dari app/Views) untuk sekumpulan berkas/folder.
     *
     * @param list<string> $view
     *
     * @return list<string>
     */
    private function berkasGrup(array $view): array
    {
        $hasil = [];
        foreach ($view as $v) {
            $abs = APPPATH . 'Views/' . $v;
            if (is_file($abs)) {
                $hasil[] = $v;
            } elseif (is_dir($abs)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    if ($f->getExtension() === 'php') {
                        $hasil[] = str_replace('\\', '/', substr($f->getPathname(), strlen(APPPATH . 'Views/')));
                    }
                }
            }
        }
        sort($hasil);

        return array_values(array_unique($hasil));
    }

    /** View sisi admin yang belum masuk grup mana pun (harus kosong). @return list<string> */
    private function viewTanpaGrup(): array
    {
        $yatim = [];
        foreach (array_merge($this->berkasGrup(['admin']), ['layouts/admin.php']) as $rel) {
            if (Tampilan::grupView($rel) === null) {
                $yatim[] = $rel;
            }
        }

        return $yatim;
    }

    /** Nama komponen Alpine.data & fungsi global dari JS admin + skrip sebaris view admin. */
    private function kumpulkanJs(): void
    {
        $teks = [];
        $it   = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(FCPATH . 'assets/js/admin', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() === 'js') {
                $teks[] = (string) file_get_contents($f->getPathname());
            }
        }
        foreach (array_merge($this->berkasGrup(['admin']), ['layouts/admin.php']) as $rel) {
            preg_match_all('#<script\b(?![^>]*\bsrc=)[^>]*>(.*?)</script>#si', (string) file_get_contents(APPPATH . 'Views/' . $rel), $m);
            $teks = array_merge($teks, $m[1]);
        }
        foreach ($teks as $t) {
            preg_match_all('/Alpine\.data\(\s*[\'"]([\w$]+)/', $t, $m);
            foreach ($m[1] as $n) {
                $this->alpineData[$n] = true;
            }
            preg_match_all('/(?:^|[;\n{}])\s*(?:async\s+)?function\s+([A-Za-z_$][\w$]*)\s*\(|window\.([A-Za-z_$][\w$]*)\s*=(?!=)/', $t, $m);
            foreach (array_filter(array_merge($m[1], $m[2])) as $n) {
                $this->fungsiGlobal[$n] = true;
            }
        }
    }

    /**
     * Temuan untuk satu berkas: list<[jenis, nomor baris, cuplikan]>.
     *
     * @return list<array{0: string, 1: int, 2: string}>
     */
    private function periksa(string $rel): array
    {
        $isi    = (string) file_get_contents(APPPATH . 'Views/' . $rel);
        $hasil  = [];
        $catat  = static function (string $jenis, int $pos, string $teks) use (&$hasil, $isi): void {
            $hasil[] = [$jenis, substr_count($isi, "\n", 0, $pos) + 1, mb_substr(trim(preg_replace('/\s+/', ' ', $teks)), 0, 110)];
        };
        $cocok = static function (string $pola) use ($isi): array {
            preg_match_all($pola, $isi, $m, PREG_OFFSET_CAPTURE);

            return $m[0];
        };

        // ---- siap CSP
        // Atribut tag boleh berisi blok kode PHP (src dari base_url(), penanda csp_script_nonce()): tanda penutup PHP di dalamnya
        // bukan akhir tag <script>.
        preg_match_all('#<script\b((?:<\?(?:php|=).*?\?>|[^>])*)>(.*?)</script>#si', $isi, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($m as $s) {
            $atr = $s[1][0];
            if (preg_match('/\bsrc\s*=/', $atr) === 1) {
                if (! str_contains($atr, 'csp_script_nonce') && ! str_contains($atr, '{csp-script-nonce}')) {
                    $catat('nonce', $s[0][1], '<script' . $atr . '>');
                }
            } elseif (preg_match('#type\s*=\s*["\']application/(ld\+)?json#i', $atr) !== 1) {
                $catat('script', $s[0][1], substr_count(trim($s[2][0]), "\n") + 1 . ' baris JS sebaris');
            }
        }
        foreach ($cocok('/\son[a-z]+\s*=\s*["\'][^"\']*/i') as [$t, $p]) {
            $catat('on', $p, $t);
        }
        foreach ($cocok('/\sstyle\s*=\s*["\'][^"\']*/i') as [$t, $p]) {
            $catat('style', $p, $t);
        }
        foreach ($cocok('/<style\b/i') as [$t, $p]) {
            $catat('tagstyle', $p, '<style> sebaris');
        }
        foreach ($cocok('/href\s*=\s*["\']\s*javascript:[^"\']*/i') as [$t, $p]) {
            $catat('on', $p, $t);
        }
        foreach ($cocok('#<(?:script|link|img|iframe|source|video|audio)\b[^>]*\s(?:src|href)\s*=\s*["\']https?://[^"\']+#i') as [$t, $p]) {
            $catat('luar', $p, preg_replace('/^.*?(https?:\/\/[^\/"\']+).*$/s', '$1', $t));
        }
        preg_match_all('/\s(x-[a-z][\w:.-]*|@[a-z][\w.:-]*|:[a-z][\w.-]*)\s*=\s*"([^"]*)"/', $isi, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($m as $a) {
            foreach ($this->alpineDitolak($a[1][0], $a[2][0]) as $alasan) {
                $catat('alpine', $a[0][1], $alasan . ': ' . $a[1][0] . '="' . $a[2][0] . '"');
            }
        }

        // ---- aturan tampilan
        // Kelas "hidden" bersama inline / inline-flex / inline-block di satu elemen: di Tailwind v4 kelas inline-* menang (urutan
        // abjad), jadi elemennya TIDAK tersembunyi. Pakai atribut hidden (preflight: [hidden] { display: none !important }).
        preg_match_all('/\sclass\s*=\s*"([^"]*)"/', $isi, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($m as $k) {
            if (preg_match('/(?<![\w:\/-])hidden(?![\w-])/', $k[1][0]) === 1
                && preg_match('/(?<![\w:\/-])inline(?:-flex|-block|-grid|-table)?(?![\w-])/', $k[1][0]) === 1) {
                $catat('bentrok', $k[0][1], 'kelas hidden + inline-* (v4: inline-* menang) → pakai atribut hidden');
            }
        }
        foreach ($cocok('/(?<![\w-])(?:[a-z-]+:)*(?:bg-(?:gradient|linear|radial|conic)-[\w-]+|rise(?:-\d)?|lift|reveal|orb(?:-float)?|hero-grid|btn-shine|animate-(?:ping|bounce))(?![\w-])/') as [$t, $p]) {
            $catat('hiasan', $p, $t);
        }
        foreach ($cocok('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]\x{FE0F}?/u') as [$t, $p]) {
            $catat('hiasan', $p, 'emoji ' . $t);
        }
        foreach ($cocok('/(?<![\w-])(?:[a-z-]+:)*rounded(?:-[trblse]{1,2})?-(?:xl|2xl|3xl)(?![\w-])/') as [$t, $p]) {
            $catat('sudut', $p, $t);
        }
        foreach ($cocok('/(?<![\w-])(?:[a-z-]+:)*shadow-(?:md|lg|xl|2xl)(?![\w-])/') as [$t, $p]) {
            $catat('bayangan', $p, $t);
        }
        foreach ($cocok('/-slate-\d+/') as [$t, $p]) {
            $catat('slate', $p, $t);
        }
        foreach ($cocok('/\bikon\(\s*[\'"]([a-z0-9-]+)[\'"](?:\s*,\s*[\'"][^\'"]*[\'"])?(?:\s*,\s*[\'"](outline|solid)[\'"])?/') as [$t, $p]) {
            preg_match('/[\'"]([a-z0-9-]+)[\'"](?:.*[\'"](outline|solid)[\'"])?/', $t, $n);
            if (! is_file(APPPATH . 'ThirdParty/flowbite-icons/' . ($n[2] ?? 'outline') . '/' . $n[1] . '.svg')) {
                $catat('ikon_hilang', $p, $t . ' → tambahkan ke resources/ikon.json lalu npm run vendor');
            }
        }
        foreach ($cocok('/<svg\b/i') as [$t, $p]) {
            $catat('svg', $p, '<svg> tulisan tangan (ganti ikon() bila ikon biasa)');
        }
        // Ikon menu samping berasal dari katalog MenuAdmin (bukan teks view) → diperiksa bersama layout (baris 0).
        if ($rel === 'layouts/admin.php') {
            foreach (MenuAdmin::sidebar() as $g) {
                foreach ($g['items'] as $it) {
                    if (! is_file(APPPATH . 'ThirdParty/flowbite-icons/outline/' . (string) $it[2] . '.svg')) {
                        $hasil[] = ['ikon_hilang', 0, 'ikon menu "' . $it[1] . '" (' . $it[2] . ') → tambahkan ke resources/ikon.json lalu npm run vendor'];
                    }
                }
            }
        }

        usort($hasil, static fn (array $a, array $b): int => $a[1] <=> $b[1]);

        return $hasil;
    }

    /**
     * Alasan sebuah isian Alpine ditolak versi CSP (kosong = aman).
     *
     * @return list<string>
     */
    private function alpineDitolak(string $atribut, string $nilai): array
    {
        $alasan = [];
        // Kode PHP di dalam isian (mis. json_encode) bukan ekspresi Alpine — buang sebelum diperiksa.
        $v = preg_replace('/<\?(?:php|=).*?\?>/s', '0', $nilai);
        if ($atribut === 'x-html') {
            return ['x-html'];
        }
        if (str_starts_with($atribut, 'x-transition') || $atribut === 'x-cloak' || $atribut === 'x-ref' || $atribut === 'x-id') {
            return [];
        }
        // Buang isi teks berkutip supaya kata di dalam string ('Date', 'window') tidak dianggap kode.
        $kode = preg_replace("/'(?:\\\\.|[^'\\\\])*'/", "''", $v);
        if (str_contains($kode, '=>')) {
            $alasan[] = 'fungsi panah';
        }
        if (str_contains($kode, '`')) {
            $alasan[] = 'template literal';
        }
        if (str_contains($kode, '...')) {
            $alasan[] = 'spread';
        }
        if (preg_match('/\?\.|\?\?/', $kode) === 1) {
            $alasan[] = '?. / ??';
        }
        if (preg_match('/(?<![\w$.])(?:' . self::GLOBAL_JS . ')\b|\bnew\s+[A-Z]/', $kode) === 1) {
            $alasan[] = 'global';
        }
        if (preg_match('/\$refs\.[\w$]+\.[\w$.]+\s*=(?!=)/', $kode) === 1) {
            $alasan[] = 'tulis lewat $refs';
        }
        preg_match_all('/(?<![\w$.])([A-Za-z_][\w$]*)\s*\(/', $kode, $m);
        foreach (array_unique($m[1]) as $f) {
            if (isset($this->fungsiGlobal[$f]) && ! in_array($f, ['if', 'for', 'while', 'switch', 'function', 'return'], true)) {
                $alasan[] = 'fungsi global ' . $f . '()';
            }
        }
        if ($atribut === 'x-data') {
            $t = trim($kode);
            if ($t !== '' && $t[0] === '{') {
                if (preg_match('/\bfunction\b|[\w$]\s*\([^)]*\)\s*\{/', $t) === 1) {
                    $alasan[] = 'metode di objek x-data sebaris';
                }
            } elseif ($t !== '' && preg_match('/^([A-Za-z_$][\w$]*)/', $t, $n) === 1 && ! isset($this->alpineData[$n[1]])) {
                $alasan[] = 'x-data bukan Alpine.data';
            }
        }
        if (in_array($atribut, [':style', 'x-bind:style'], true) && ! str_starts_with(trim($kode), '{')) {
            $alasan[] = ':style berupa teks';
        }

        return array_values(array_unique($alasan));
    }

    /**
     * admin.css sudah sama dengan hasil build terbaru? Dibangun ulang ke berkas sementara lalu dibandingkan isinya (tanggal berkas
     * tidak bisa dipercaya: Tailwind tidak menulis ulang bila isinya sama). Butuh node + node_modules (hanya di laptop).
     */
    private function cekBuild(): void
    {
        $hasil = FCPATH . 'assets/css/admin.css';
        $cli   = ROOTPATH . 'node_modules/@tailwindcss/cli/dist/index.mjs';
        if (! is_file($hasil)) {
            CLI::write('admin.css belum ada — jalankan: npm run build:admin', 'red');

            return;
        }
        if (! is_file($cli)) {
            CLI::write('admin.css: tidak dicek (node_modules belum ada — npm install)', 'yellow');

            return;
        }
        $sementara = WRITEPATH . 'cache/cek-ui-admin.css';
        $perintah  = 'node ' . escapeshellarg($cli) . ' -i ' . escapeshellarg(ROOTPATH . 'resources/css/admin.css')
            . ' -o ' . escapeshellarg($sementara) . ' --minify 2>&1';
        exec($perintah, $keluaran, $kode);
        if ($kode !== 0 || ! is_file($sementara)) {
            CLI::write('admin.css: build uji gagal — ' . implode(' ', array_slice($keluaran, -2)), 'red');

            return;
        }
        // Akhir baris diabaikan (CRLF/LF): isi CSS yang sama tetap dianggap sama walau git Windows menulis ulang berkasnya.
        $isi  = static fn (string $f): string => str_replace("\r\n", "\n", (string) file_get_contents($f));
        $sama = $isi($sementara) === $isi($hasil);
        @unlink($sementara);
        CLI::write('admin.css: ' . ($sama ? 'sudah sesuai sumber terbaru' : 'BELUM DI-BUILD ULANG — jalankan npm run build:admin sebelum commit'), $sama ? 'green' : 'red');
    }
}
