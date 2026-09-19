<?php

/**
 * =====================================================================
 *  D0 — Diagnostik Hosting untuk modul Manajemen Dokumen (SIMDOK)
 * =====================================================================
 *  SKRIP SEKALI PAKAI. Berdiri sendiri (tidak memuat CodeIgniter) supaya
 *  tetap bisa dipakai walau aplikasi sedang bermasalah.
 *
 *  CARA PAKAI DI HOSTING:
 *    1. Pastikan berkas ini ikut ter-deploy (git pull).
 *    2. Buka di browser:
 *         https://kangmuslim.com/cek-hosting.php?kunci=d0-muslimin-2026
 *    3. Salin isi kotak "RINGKASAN UNTUK DISALIN" di paling bawah.
 *    4. Coba juga tombol uji unggah dengan berkas yang agak besar.
 *    5. HAPUS berkas ini setelah selesai (ada tombol hapus di bawah).
 *
 *  Dibuka lewat BROWSER, bukan terminal — konfigurasi PHP untuk web
 *  berbeda dari PHP CLI, dan yang menentukan batas unggah adalah yang web.
 * =====================================================================
 */

const KUNCI = 'd0-muslimin-2026';

if (($_GET['kunci'] ?? $_POST['kunci'] ?? '') !== KUNCI) {
    http_response_code(404);
    exit('404 Not Found');
}

$ROOT     = dirname(__DIR__);
$WRITABLE = $ROOT . DIRECTORY_SEPARATOR . 'writable';
$ringkas  = [];   // baris teks polos untuk disalin

/** Catat satu baris ke ringkasan teks. */
function catat(string $label, string $nilai): void
{
    global $ringkas;
    $ringkas[] = str_pad($label, 26, '.') . ' ' . $nilai;
}

/** Ubah "8M" / "512K" / "1G" jadi byte. -1 / 0 tetap apa adanya. */
function keBytes(string $v): int
{
    $v = trim($v);
    if ($v === '' || $v === '-1') {
        return -1;
    }
    $satuan = strtolower(substr($v, -1));
    $angka  = (int) $v;

    return match ($satuan) {
        'g'     => $angka * 1024 * 1024 * 1024,
        'm'     => $angka * 1024 * 1024,
        'k'     => $angka * 1024,
        default => (int) $v,
    };
}

/** Format byte jadi ukuran yang enak dibaca. */
function ukuran(float $b): string
{
    if ($b < 0) {
        return 'tak terbatas';
    }
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($b >= 1024 && $i < count($u) - 1) {
        $b /= 1024;
        $i++;
    }

    return round($b, $b < 10 && $i > 0 ? 1 : 0) . ' ' . $u[$i];
}

/** Ambil base URL situs dari request sekarang. */
function baseUrl(): string
{
    $https  = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

    return ($https ? 'https://' : 'http://') . $host . $dir;
}

/** Ambil kode status HTTP sebuah URL (cURL, fallback null). */
function statusUrl(string $url): ?array
{
    if (! function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $isi  = curl_exec($ch);
    $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    return ['kode' => $kode, 'isi' => is_string($isi) ? $isi : '', 'err' => $err];
}

// =====================================================================
//  A. Identitas server & versi PHP
// =====================================================================
$phpVersi = PHP_VERSION;
$sapi     = PHP_SAPI;
$os       = PHP_OS_FAMILY;
catat('PHP', $phpVersi . ' (' . $sapi . ', ' . $os . ')');
catat('Server', $_SERVER['SERVER_SOFTWARE'] ?? '-');

// =====================================================================
//  B. Batas unggah — INI YANG PALING MENENTUKAN
// =====================================================================
$umf  = (string) ini_get('upload_max_filesize');
$pms  = (string) ini_get('post_max_size');
$mem  = (string) ini_get('memory_limit');
$met  = (string) ini_get('max_execution_time');
$mit  = (string) ini_get('max_input_time');
$mfu  = (string) ini_get('max_file_uploads');
$fups = ini_get('file_uploads') ? 'aktif' : 'MATI';

$umfB = keBytes($umf);
$pmsB = keBytes($pms);
// Batas nyata satu berkas = yang terkecil antara upload_max_filesize & post_max_size
$batasNyata = ($umfB < 0 || $pmsB < 0) ? max($umfB, $pmsB) : min($umfB, $pmsB);

catat('upload_max_filesize', $umf);
catat('post_max_size', $pms);
catat('BATAS NYATA / berkas', ukuran((float) $batasNyata));
catat('memory_limit', $mem);
catat('max_execution_time', $met . ' detik');
catat('max_input_time', $mit . ' detik');
catat('max_file_uploads', $mfu . ' berkas sekali kirim');
catat('file_uploads', $fups);

// =====================================================================
//  C. Ekstensi PHP yang dibutuhkan
// =====================================================================
$ekstensi = [
    'fileinfo' => 'WAJIB — deteksi tipe berkas dari isinya',
    'gd'       => 'WAJIB — buat thumbnail gambar',
    'exif'     => 'disarankan — perbaiki orientasi foto',
    'zip'      => 'sudah dipakai PhpSpreadsheet',
    'curl'     => 'disarankan',
    'imagick'  => 'opsional — satu-satunya cara baca HEIC iPhone di server',
];
$statusEkst = [];

foreach ($ekstensi as $nama => $ket) {
    $ada              = extension_loaded($nama);
    $statusEkst[$nama] = $ada;
    catat('ext ' . $nama, $ada ? 'ADA' : 'TIDAK ADA');
}

$gdInfo = function_exists('gd_info') ? gd_info() : [];
$gdWebp = ! empty($gdInfo['WebP Support']);
$gdAvif = ! empty($gdInfo['AVIF Support']);
if ($statusEkst['gd']) {
    catat('GD WebP', $gdWebp ? 'YA' : 'tidak');
    catat('GD AVIF', $gdAvif ? 'YA' : 'tidak');
}

$imagickHeic = false;
if ($statusEkst['imagick'] && class_exists('Imagick')) {
    try {
        $formats     = Imagick::queryFormats();
        $imagickHeic = (bool) array_intersect(['HEIC', 'HEIF'], array_map('strtoupper', $formats));
        catat('Imagick HEIC', $imagickHeic ? 'YA — HEIC bisa dikonversi di server' : 'tidak');
    } catch (Throwable $e) {
        catat('Imagick HEIC', 'gagal dicek: ' . $e->getMessage());
    }
}

// =====================================================================
//  D. Ruang disk
// =====================================================================
$diskBebas = @disk_free_space($ROOT);
$diskTotal = @disk_total_space($ROOT);
catat('Disk bebas', $diskBebas ? ukuran((float) $diskBebas) : 'tak terbaca');
catat('Disk total', $diskTotal ? ukuran((float) $diskTotal) : 'tak terbaca');

$openBasedir = (string) ini_get('open_basedir');
catat('open_basedir', $openBasedir === '' ? 'tidak dibatasi' : $openBasedir);
catat('folder temp', sys_get_temp_dir() . (is_writable(sys_get_temp_dir()) ? ' (bisa ditulis)' : ' (TIDAK bisa ditulis)'));

// =====================================================================
//  E. Uji tulis ke folder penyimpanan dokumen
// =====================================================================
$targetDir = $WRITABLE . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'dokumen'
    . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');
$tulisOk  = false;
$tulisPsn = '';

if (! is_dir($targetDir) && ! @mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
    $tulisPsn = 'GAGAL membuat folder: ' . $targetDir;
} else {
    $uji = $targetDir . DIRECTORY_SEPARATOR . '_uji_tulis.txt';
    $isi = 'uji ' . date('c');
    if (@file_put_contents($uji, $isi) === false) {
        $tulisPsn = 'Folder ada tapi TIDAK BISA DITULIS: ' . $targetDir;
    } elseif (@file_get_contents($uji) !== $isi) {
        $tulisPsn = 'Berkas tertulis tapi isinya tidak cocok saat dibaca ulang.';
    } else {
        $tulisOk  = true;
        $tulisPsn = 'BISA ditulis & dibaca: ' . $targetDir;
        @unlink($uji);
    }
}
catat('Tulis folder dokumen', $tulisOk ? 'BISA' : 'GAGAL');

// =====================================================================
//  F. Uji keamanan: folder writable/ WAJIB tidak bisa dibuka dari web
// =====================================================================
$base      = baseUrl();
$kanari    = 'kanari_' . bin2hex(random_bytes(8));
$kanariIsi = 'RAHASIA-' . $kanari;
$kanariFil = $WRITABLE . DIRECTORY_SEPARATOR . $kanari . '.txt';
@file_put_contents($kanariFil, $kanariIsi);

$ujiUrl  = [
    'lewat /writable/'    => $base . '/writable/' . $kanari . '.txt',
    'lewat /../writable/' => rtrim($base, '/') . '/../writable/' . $kanari . '.txt',
];
$hasilAman = [];

foreach ($ujiUrl as $labelUji => $u) {
    $r = statusUrl($u);
    if ($r === null) {
        $hasilAman[$labelUji] = ['aman' => null, 'pesan' => 'cURL tidak tersedia — cek manual di browser'];
        continue;
    }
    $bocor                = ($r['kode'] === 200 && str_contains($r['isi'], $kanariIsi));
    $hasilAman[$labelUji] = [
        'aman'  => ! $bocor,
        'pesan' => $bocor
            ? 'BOCOR! HTTP 200 dan isi berkas terbaca dari internet'
            : 'aman (HTTP ' . ($r['kode'] ?: '-') . ')' . ($r['err'] !== '' ? ' [' . $r['err'] . ']' : ''),
    ];
}
@unlink($kanariFil);

foreach ($hasilAman as $labelUji => $h) {
    catat('Proteksi ' . $labelUji, $h['pesan']);
}

// =====================================================================
//  G. Uji unggah nyata (kalau form dikirim)
// =====================================================================
$hasilUnggah = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['berkas'])) {
    $f    = $_FILES['berkas'];
    $kode = $f['error'] ?? UPLOAD_ERR_NO_FILE;

    $pesanErr = [
        UPLOAD_ERR_OK         => 'OK',
        UPLOAD_ERR_INI_SIZE   => 'DITOLAK — melebihi upload_max_filesize (' . $umf . ')',
        UPLOAD_ERR_FORM_SIZE  => 'DITOLAK — melebihi batas form',
        UPLOAD_ERR_PARTIAL    => 'Terkirim sebagian saja (koneksi putus / timeout)',
        UPLOAD_ERR_NO_FILE    => 'Tidak ada berkas dipilih',
        UPLOAD_ERR_NO_TMP_DIR => 'GAGAL — folder temp server tidak ada',
        UPLOAD_ERR_CANT_WRITE => 'GAGAL — server tidak bisa menulis ke disk',
        UPLOAD_ERR_EXTENSION  => 'Dihentikan oleh ekstensi PHP',
    ];

    $hasilUnggah = [
        'nama'   => (string) ($f['name'] ?? '-'),
        'ukuran' => (int) ($f['size'] ?? 0),
        'kode'   => $kode,
        'pesan'  => $pesanErr[$kode] ?? ('Kode error tak dikenal: ' . $kode),
        'mime'   => '-',
        'simpan' => '-',
    ];

    if ($kode === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name'])) {
        // Deteksi MIME dari ISI berkas — cara yang akan dipakai modul nanti.
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $hasilUnggah['mime'] = (string) finfo_file($fi, $f['tmp_name']);
            finfo_close($fi);
        }
        $tujuan = $targetDir . DIRECTORY_SEPARATOR . '_uji_unggah_' . bin2hex(random_bytes(4));
        if (@move_uploaded_file($f['tmp_name'], $tujuan)) {
            $hasilUnggah['simpan'] = 'BERHASIL tersimpan (' . ukuran((float) filesize($tujuan)) . ') lalu dihapus lagi';
            @unlink($tujuan);
        } else {
            $hasilUnggah['simpan'] = 'GAGAL memindahkan berkas ke folder dokumen';
        }
    }
}

// Kalau POST datang tapi $_FILES & $_POST kosong total → post_max_size terlampaui.
$postKebablasan = $_SERVER['REQUEST_METHOD'] === 'POST'
    && empty($_FILES) && empty($_POST)
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;

// =====================================================================
//  H. Kesimpulan otomatis
// =====================================================================
$masalah = [];
$catatan = [];

if (version_compare($phpVersi, '8.2', '<')) {
    $masalah[] = 'PHP ' . $phpVersi . ' terlalu tua untuk CodeIgniter 4.7 (butuh 8.2+).';
}
if (! $statusEkst['fileinfo']) {
    $masalah[] = 'Ekstensi fileinfo TIDAK ADA — deteksi tipe berkas dari isi tak bisa jalan. Wajib diaktifkan.';
}
if (! $statusEkst['gd']) {
    $masalah[] = 'Ekstensi gd TIDAK ADA — thumbnail gambar tak bisa dibuat.';
}
if (! $tulisOk) {
    $masalah[] = 'Folder penyimpanan dokumen tidak bisa ditulis: ' . $tulisPsn;
}
foreach ($hasilAman as $labelUji => $h) {
    if ($h['aman'] === false) {
        $masalah[] = 'BAHAYA — folder writable/ bisa dibuka dari internet (' . $labelUji . '). Berkas privat akan bocor.';
    }
}
if ($batasNyata > 0 && $batasNyata < 10 * 1024 * 1024) {
    $masalah[] = 'Batas unggah cuma ' . ukuran((float) $batasNyata) . ' — terlalu kecil untuk dokumen sekolah. Naikkan lewat cPanel > MultiPHP INI Editor.';
}
if (! $statusEkst['imagick'] || ! $imagickHeic) {
    $catatan[] = 'HEIC (foto iPhone) tidak bisa dikonversi di server — sesuai dugaan. Dari aplikasi Android tetap aman karena dikonversi di HP; dari web desktop HEIC tersimpan utuh tapi tanpa pratinjau.';
}
if (! $gdWebp) {
    $catatan[] = 'GD tanpa dukungan WebP — thumbnail akan dibuat sebagai JPEG.';
}
if ((int) $met > 0 && (int) $met < 60) {
    $catatan[] = 'max_execution_time cuma ' . $met . ' detik — unggah berkas besar lewat koneksi lambat bisa putus.';
}

$saranMaks = 25;
if ($batasNyata > 0) {
    $saranMaks = max(2, (int) floor($batasNyata / 1024 / 1024) - 2); // sisakan ruang untuk field lain
    $saranMaks = min($saranMaks, 50);
}
catat('SARAN dok_maks_mb', $saranMaks . ' MB');

$teksRingkas = "=== DIAGNOSTIK HOSTING SIMDOK (D0) ===\n"
    . 'Tanggal: ' . date('Y-m-d H:i:s') . "\n"
    . 'URL    : ' . $base . "\n\n"
    . implode("\n", $ringkas) . "\n";

if ($hasilUnggah !== null) {
    $teksRingkas .= "\n--- UJI UNGGAH ---\n"
        . 'Berkas : ' . $hasilUnggah['nama'] . ' (' . ukuran((float) $hasilUnggah['ukuran']) . ")\n"
        . 'Hasil  : ' . $hasilUnggah['pesan'] . "\n"
        . 'MIME   : ' . $hasilUnggah['mime'] . "\n"
        . 'Simpan : ' . $hasilUnggah['simpan'] . "\n";
}
if ($postKebablasan) {
    $teksRingkas .= "\n--- UJI UNGGAH ---\nBerkas TERLALU BESAR: post_max_size (" . $pms . ") terlampaui, PHP membuang seluruh data kiriman.\n";
}
if ($masalah !== []) {
    $teksRingkas .= "\n--- MASALAH ---\n- " . implode("\n- ", $masalah) . "\n";
}
if ($catatan !== []) {
    $teksRingkas .= "\n--- CATATAN ---\n- " . implode("\n- ", $catatan) . "\n";
}

$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Diagnostik Hosting — SIMDOK D0</title>
<style>
  :root { --bg:#f8fafc; --card:#fff; --tx:#0f172a; --mut:#64748b; --line:#e2e8f0;
          --ok:#15803d; --okbg:#dcfce7; --bad:#b91c1c; --badbg:#fee2e2;
          --warn:#a16207; --warnbg:#fef9c3; --brand:#1A3A6B; }
  @media (prefers-color-scheme: dark) {
    :root { --bg:#0b1220; --card:#111a2e; --tx:#e2e8f0; --mut:#94a3b8; --line:#1e293b;
            --ok:#4ade80; --okbg:#052e16; --bad:#fca5a5; --badbg:#450a0a;
            --warn:#fde047; --warnbg:#422006; --brand:#93b4e8; }
  }
  * { box-sizing:border-box; }
  body { margin:0; padding:16px; background:var(--bg); color:var(--tx);
         font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
  .wrap { max-width:900px; margin:0 auto; }
  h1 { font-size:22px; margin:8px 0 4px; color:var(--brand); }
  h2 { font-size:16px; margin:26px 0 10px; }
  p.sub { color:var(--mut); margin:0 0 18px; }
  .card { background:var(--card); border:1px solid var(--line); border-radius:12px;
          padding:14px 16px; margin-bottom:14px; }
  table { width:100%; border-collapse:collapse; font-size:14px; }
  td { padding:7px 6px; border-bottom:1px solid var(--line); vertical-align:top; }
  td:first-child { color:var(--mut); width:45%; }
  td:last-child { font-weight:600; word-break:break-word; }
  .pill { display:inline-block; padding:1px 9px; border-radius:999px; font-size:12px; font-weight:700; }
  .ok { background:var(--okbg); color:var(--ok); }
  .bad { background:var(--badbg); color:var(--bad); }
  .warn { background:var(--warnbg); color:var(--warn); }
  ul { margin:8px 0 0; padding-left:20px; }
  li { margin-bottom:6px; }
  textarea { width:100%; height:330px; font:12px/1.45 ui-monospace,Consolas,monospace;
             padding:10px; border:1px solid var(--line); border-radius:8px;
             background:var(--bg); color:var(--tx); }
  input[type=file] { font-size:14px; }
  button { background:var(--brand); color:#fff; border:0; border-radius:8px;
           padding:9px 16px; font-size:14px; font-weight:600; cursor:pointer; }
  .hint { color:var(--mut); font-size:13px; margin-top:8px; }
  code { background:var(--bg); padding:1px 5px; border-radius:4px; font-size:13px; }
</style>
</head>
<body>
<div class="wrap">

  <h1>Diagnostik Hosting — SIMDOK (D0)</h1>
  <p class="sub">Dijalankan <?= $e(date('d-m-Y H:i:s')) ?> di <code><?= $e($base) ?></code></p>

  <?php if ($masalah !== []) { ?>
    <div class="card" style="border-color:var(--bad)">
      <strong class="pill bad">PERLU DIBERESKAN</strong>
      <ul><?php foreach ($masalah as $m) { ?><li><?= $e($m) ?></li><?php } ?></ul>
    </div>
  <?php } else { ?>
    <div class="card" style="border-color:var(--ok)">
      <strong class="pill ok">SEMUA SYARAT TERPENUHI</strong>
      <p class="hint">Tidak ada penghalang untuk membangun modul dokumen di hosting ini.</p>
    </div>
  <?php } ?>

  <?php if ($catatan !== []) { ?>
    <div class="card" style="border-color:var(--warn)">
      <strong class="pill warn">CATATAN (bukan penghalang)</strong>
      <ul><?php foreach ($catatan as $c) { ?><li><?= $e($c) ?></li><?php } ?></ul>
    </div>
  <?php } ?>

  <h2>Batas unggah</h2>
  <div class="card">
    <table>
      <tr><td>upload_max_filesize</td><td><?= $e($umf) ?></td></tr>
      <tr><td>post_max_size</td><td><?= $e($pms) ?></td></tr>
      <tr><td><strong>Batas nyata per berkas</strong></td><td><?= $e(ukuran((float) $batasNyata)) ?></td></tr>
      <tr><td>memory_limit</td><td><?= $e($mem) ?></td></tr>
      <tr><td>max_execution_time</td><td><?= $e($met) ?> detik</td></tr>
      <tr><td>max_input_time</td><td><?= $e($mit) ?> detik</td></tr>
      <tr><td>max_file_uploads</td><td><?= $e($mfu) ?> berkas sekali kirim</td></tr>
      <tr><td>file_uploads</td><td><?= $e($fups) ?></td></tr>
      <tr><td><strong>Saran nilai dok_maks_mb</strong></td><td><?= $e($saranMaks) ?> MB</td></tr>
    </table>
  </div>

  <h2>Uji unggah nyata</h2>
  <div class="card">
    <?php if ($postKebablasan) { ?>
      <p><span class="pill bad">TERLALU BESAR</span> Berkas melampaui <code><?= $e($pms) ?></code>
         (post_max_size), PHP membuang seluruh data kiriman sebelum sempat diproses.</p>
    <?php } elseif ($hasilUnggah !== null) { ?>
      <table>
        <tr><td>Nama berkas</td><td><?= $e($hasilUnggah['nama']) ?></td></tr>
        <tr><td>Ukuran</td><td><?= $e(ukuran((float) $hasilUnggah['ukuran'])) ?></td></tr>
        <tr><td>Hasil</td>
            <td><span class="pill <?= $hasilUnggah['kode'] === UPLOAD_ERR_OK ? 'ok' : 'bad' ?>"><?= $e($hasilUnggah['pesan']) ?></span></td></tr>
        <tr><td>MIME terdeteksi dari isi</td><td><?= $e($hasilUnggah['mime']) ?></td></tr>
        <tr><td>Simpan ke folder dokumen</td><td><?= $e($hasilUnggah['simpan']) ?></td></tr>
      </table>
      <hr style="border:0;border-top:1px solid var(--line);margin:14px 0">
    <?php } ?>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="kunci" value="<?= $e(KUNCI) ?>">
      <input type="file" name="berkas" required>
      <button type="submit">Uji unggah</button>
      <p class="hint">Coba berkas yang agak besar (mis. PDF 10–20 MB) untuk tahu batas
         sebenarnya. Berkas uji langsung dihapus lagi setelah dicek — tidak menumpuk.</p>
    </form>
  </div>

  <h2>Keamanan folder penyimpanan</h2>
  <div class="card">
    <table>
      <tr><td>Folder dokumen bisa ditulis</td>
          <td><span class="pill <?= $tulisOk ? 'ok' : 'bad' ?>"><?= $e($tulisPsn) ?></span></td></tr>
      <?php foreach ($hasilAman as $labelUji => $h) { ?>
        <tr><td>Uji akses <?= $e($labelUji) ?></td>
            <td><span class="pill <?= $h['aman'] === true ? 'ok' : ($h['aman'] === false ? 'bad' : 'warn') ?>"><?= $e($h['pesan']) ?></span></td></tr>
      <?php } ?>
    </table>
    <p class="hint">Diuji dengan menaruh berkas penanda berisi teks rahasia di
       <code>writable/</code> lalu mencoba membukanya dari internet. Berkas penanda
       sudah dihapus otomatis.</p>
  </div>

  <h2>Ekstensi &amp; disk</h2>
  <div class="card">
    <table>
      <tr><td>PHP</td><td><?= $e($phpVersi) ?> (<?= $e($sapi) ?>)</td></tr>
      <?php foreach ($ekstensi as $nama => $ket) { ?>
        <tr><td><?= $e($nama) ?> <span style="font-weight:400">— <?= $e($ket) ?></span></td>
            <td><span class="pill <?= $statusEkst[$nama] ? 'ok' : ($nama === 'imagick' ? 'warn' : 'bad') ?>"><?= $statusEkst[$nama] ? 'ADA' : 'TIDAK ADA' ?></span></td></tr>
      <?php } ?>
      <?php if ($statusEkst['gd']) { ?>
        <tr><td>GD — WebP / AVIF</td><td><?= $gdWebp ? 'WebP ya' : 'WebP tidak' ?> &middot; <?= $gdAvif ? 'AVIF ya' : 'AVIF tidak' ?></td></tr>
      <?php } ?>
      <?php if ($statusEkst['imagick']) { ?>
        <tr><td>Imagick baca HEIC</td><td><?= $imagickHeic ? 'YA' : 'tidak' ?></td></tr>
      <?php } ?>
      <tr><td>Disk bebas / total</td>
          <td><?= $e($diskBebas ? ukuran((float) $diskBebas) : '-') ?> dari <?= $e($diskTotal ? ukuran((float) $diskTotal) : '-') ?></td></tr>
      <tr><td>open_basedir</td><td><?= $e($openBasedir === '' ? 'tidak dibatasi' : $openBasedir) ?></td></tr>
    </table>
  </div>

  <h2>Ringkasan untuk disalin</h2>
  <div class="card">
    <textarea readonly onclick="this.select()"><?= $e($teksRingkas) ?></textarea>
    <p class="hint">Klik kotak di atas untuk memilih semua, salin, lalu tempel ke chat.</p>
  </div>

  <div class="card" style="border-color:var(--warn)">
    <strong class="pill warn">JANGAN LUPA</strong>
    <p class="hint">Hapus berkas <code>public/cek-hosting.php</code> setelah selesai —
       skrip ini memaparkan konfigurasi server. Hapus lewat File Manager cPanel,
       atau hapus di repo lalu <code>git pull</code> lagi.</p>
  </div>

</div>
</body>
</html>
