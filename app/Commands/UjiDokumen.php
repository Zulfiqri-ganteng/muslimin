<?php

namespace App\Commands;

use App\Models\DokumenAksesLogModel;
use App\Models\DokumenFolderModel;
use App\Models\DokumenModel;
use App\Models\DokumenShareModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Uji regresi modul Dokumen (D1): helper penyimpanan + 4 model.
 *
 * Disimpan permanen (pola dev:smoke-views) supaya tiap kali modul ini
 * disentuh lagi, pohon folder, penjaga path traversal, pengenalan jenis
 * berkas, dan aturan tautan berbagi bisa dicek ulang dalam sekali jalan.
 * Data uji dibuat dan dihapus sendiri di akhir.
 *
 * Jalankan:  php spark dev:uji-dokumen
 */
class UjiDokumen extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-dokumen';
    protected $description = 'Uji helper + model modul Dokumen (D1).';

    private int $lulus = 0;
    private int $gagal = 0;

    /** @var list<int> */
    private array $folderUji = [];

    /** @var list<int> */
    private array $dokUji = [];

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->lulus++;
            CLI::write('  [OK]   ' . $judul . ($detail !== '' ? '  → ' . $detail : ''), 'green');
        } else {
            $this->gagal++;
            CLI::write('  [GAGAL] ' . $judul . ($detail !== '' ? '  → ' . $detail : ''), 'red');
        }
    }

    private function bagian(string $judul): void
    {
        CLI::newLine();
        CLI::write('== ' . $judul . ' ==', 'yellow');
    }

    public function run(array $params)
    {
        try {
            return $this->doRun();
        } catch (\Throwable $e) {
            CLI::write('FATAL ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'red');
            $this->bersihkan();

            return EXIT_ERROR;
        }
    }

    private function doRun(): int
    {
        helper('dokumen');

        $this->ujiHelperLokasi();
        $this->ujiHelperPengaturan();
        $this->ujiHelperKategori();
        $this->ujiHelperTautan();
        $this->ujiThumbnail();
        $this->ujiFolder();
        $this->ujiDokumen();
        $this->ujiShare();
        $this->ujiLog();
        $this->bersihkan();

        CLI::newLine();
        CLI::write('RINGKASAN: ' . $this->lulus . ' lulus, ' . $this->gagal . ' gagal', $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // -----------------------------------------------------------------
    private function ujiHelperLokasi(): void
    {
        $this->bagian('Helper — lokasi & penjaga path');

        $root = dokumen_root();
        $this->cek('dokumen_root() membuat folder', is_dir($root), $root);
        $this->cek('dokumen_root() memasang .htaccess penolak', is_file($root . '.htaccess'));

        $bulan = dokumen_dir_bulan();
        $this->cek('dokumen_dir_bulan() membuat folder YYYY/MM', is_dir($bulan), $bulan);

        // Penjaga path traversal — ini pengaman utama modul.
        $this->cek('tolak path traversal ../../.env', dokumen_path('../../.env') === null);
        $this->cek('tolak path traversal ..\\..\\.env', dokumen_path('..\\..\\.env') === null);
        $this->cek('tolak path kosong', dokumen_path('') === null);
        $this->cek('tolak byte NUL', dokumen_path("2026/09/a\0.pdf") === null);
        $this->cek('tolak berkas tak ada', dokumen_path('2026/09/tidak-ada-xyz.pdf') === null);

        // Berkas nyata di dalam folder harus DITERIMA.
        $nama = '_uji_path_' . bin2hex(random_bytes(4)) . '.txt';
        file_put_contents($bulan . $nama, 'halo');
        $rel = date('Y') . '/' . date('m') . '/' . $nama;
        $this->cek('terima berkas sah di dalam folder', dokumen_path($rel) !== null, $rel);
        dokumen_delete($rel);
        $this->cek('dokumen_delete() menghapus berkas', ! is_file($bulan . $nama));
    }

    private function ujiHelperPengaturan(): void
    {
        $this->bagian('Helper — pengaturan');

        $maks = dokumen_maks_bytes();
        $this->cek('dokumen_maks_bytes() = 25 MB (bawaan)', $maks === 25 * 1024 * 1024, dokumen_ukuran_manusia($maks));
        $this->cek('dokumen_izinkan_video() mati (bawaan)', dokumen_izinkan_video() === false);
    }

    private function ujiHelperKategori(): void
    {
        $this->bagian('Helper — pengenalan jenis berkas');

        $kasus = [
            ['pdf', '', 'pdf'],
            ['docx', 'application/zip', 'dokumen'],     // OOXML memang terbaca zip oleh finfo
            ['xlsx', 'application/zip', 'spreadsheet'],
            ['pptx', 'application/zip', 'presentasi'],
            ['doc', '', 'dokumen'],
            ['csv', 'text/plain', 'spreadsheet'],
            ['jpg', 'image/jpeg', 'gambar'],
            ['heic', 'application/octet-stream', 'gambar'],   // berkas iPhone
            ['mov', 'video/quicktime', 'video'],
            ['mp4', 'video/mp4', 'video'],
            ['mp3', 'audio/mpeg', 'audio'],
            ['zip', 'application/zip', 'arsip'],
            ['xyz', 'application/octet-stream', 'lainnya'],
            ['', 'image/png', 'gambar'],                     // tanpa ekstensi → tebak dari MIME
        ];

        foreach ($kasus as [$ext, $mime, $harap]) {
            $dapat = dokumen_kategori($ext, $mime);
            $this->cek(
                'kategori .' . ($ext === '' ? '(kosong)' : $ext) . ' → ' . $harap,
                $dapat === $harap,
                $dapat === $harap ? '' : 'dapat: ' . $dapat
            );
        }

        $this->cek('MIME docx dari peta ekstensi', dokumen_mime_map()['docx'] === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->cek('ekstensi dibersihkan dari karakter aneh', dokumen_ekstensi_bersih('rapor.PDF?x=1') === 'pdfx1');
        $this->cek('nama berkas dibersihkan', dokumen_nama_aman("lap\noran\t/rusak\".docx") === 'lap oran rusak .docx', dokumen_nama_aman("lap\noran\t/rusak\".docx"));
        $this->cek('nama kosong diberi cadangan', dokumen_nama_aman('   ') === 'berkas');

        $this->cek('ukuran 1536 B → 1,5 KB', dokumen_ukuran_manusia(1536) === '1,5 KB', dokumen_ukuran_manusia(1536));
        $this->cek('ukuran 0 → 0 B', dokumen_ukuran_manusia(0) === '0 B');
        $this->cek('ukuran 5 MB', dokumen_ukuran_manusia(5 * 1024 * 1024) === '5,0 MB', dokumen_ukuran_manusia(5 * 1024 * 1024));

        $this->cek('presentasi TIDAK punya pratinjau web', dokumen_bisa_pratinjau('presentasi') === false);
        $this->cek('pdf punya pratinjau web', dokumen_bisa_pratinjau('pdf') === true);
    }

    private function ujiHelperTautan(): void
    {
        $this->bagian('Helper — tautan eksternal');

        $this->cek('kenali youtube.com', dokumen_penyedia('https://www.youtube.com/watch?v=dQw4w9WgXcQ') === 'youtube');
        $this->cek('kenali youtu.be', dokumen_penyedia('https://youtu.be/dQw4w9WgXcQ') === 'youtube');
        $this->cek('kenali Google Drive', dokumen_penyedia('https://drive.google.com/file/d/abc/view') === 'drive');
        $this->cek('URL lain → lainnya', dokumen_penyedia('https://contoh.sch.id/berkas.pdf') === 'lainnya');

        $this->cek('id youtube dari ?v=', dokumen_youtube_id('https://www.youtube.com/watch?v=dQw4w9WgXcQ') === 'dQw4w9WgXcQ');
        $this->cek('id youtube dari youtu.be', dokumen_youtube_id('https://youtu.be/dQw4w9WgXcQ') === 'dQw4w9WgXcQ');
        $this->cek('id youtube dari /shorts/', dokumen_youtube_id('https://www.youtube.com/shorts/dQw4w9WgXcQ') === 'dQw4w9WgXcQ');
        $this->cek('bukan youtube → null', dokumen_youtube_id('https://drive.google.com/x') === null);
        $this->cek('sampul youtube terbentuk', str_contains((string) dokumen_thumb_url_eksternal('https://youtu.be/dQw4w9WgXcQ'), 'dQw4w9WgXcQ'));
    }

    private function ujiThumbnail(): void
    {
        $this->bagian('Helper — thumbnail gambar');

        if (! function_exists('imagecreatetruecolor')) {
            $this->cek('GD tersedia', false, 'GD tidak ada — thumbnail dilewati');

            return;
        }

        // Buat PNG 1200x800 sungguhan lalu minta thumbnail-nya.
        $dir  = dokumen_dir_bulan();
        $nama = '_uji_gambar_' . bin2hex(random_bytes(4)) . '.png';
        $img  = imagecreatetruecolor(1200, 800);
        imagefill($img, 0, 0, imagecolorallocate($img, 30, 111, 214));
        imagepng($img, $dir . $nama);
        imagedestroy($img);

        $rel   = date('Y') . '/' . date('m') . '/' . $nama;
        $thumb = dokumen_thumb_buat($dir . $nama, $rel, 480);

        $this->cek('thumbnail terbentuk', $thumb !== null, (string) $thumb);

        if ($thumb !== null) {
            $tAbs = dokumen_root() . str_replace('/', DIRECTORY_SEPARATOR, $thumb);
            $this->cek('berkas thumbnail ada di disk', is_file($tAbs));

            $ukur = @getimagesize($tAbs);
            $this->cek('thumbnail diperkecil ke <= 480px', $ukur !== false && max($ukur[0], $ukur[1]) <= 480, $ukur ? $ukur[0] . 'x' . $ukur[1] : '-');
            $this->cek('thumbnail lebih kecil dari aslinya', filesize($tAbs) < filesize($dir . $nama), dokumen_ukuran_manusia((int) filesize($tAbs)) . ' vs ' . dokumen_ukuran_manusia((int) filesize($dir . $nama)));
        }

        dokumen_delete($rel, $thumb);
        $this->cek('berkas uji + thumbnail dibersihkan', ! is_file($dir . $nama));
    }

    private function ujiFolder(): void
    {
        $this->bagian('Model — DokumenFolderModel (pohon folder)');

        $m = new DokumenFolderModel();

        $idA = $m->insert(['nama' => 'UJI Kesiswaan', 'parent_id' => null, 'visibilitas' => 'privat'], true);
        $idB = $m->insert(['nama' => 'UJI Proposal', 'parent_id' => $idA], true);
        $idC = $m->insert(['nama' => 'UJI 2026', 'parent_id' => $idB], true);
        $this->folderUji = [(int) $idA, (int) $idB, (int) $idC];

        $this->cek('buat 3 folder bertingkat', $idA && $idB && $idC, "A={$idA} B={$idB} C={$idC}");

        $akar = $m->anak(null);
        $this->cek('anak(null) memuat folder akar', in_array((int) $idA, array_column($akar, 'id'), false));
        $this->cek('anak(A) memuat B saja', count($m->anak((int) $idA)) === 1);

        $jejak = $m->jejak((int) $idC);
        $this->cek('jejak breadcrumb 3 tingkat', count($jejak) === 3, implode(' / ', array_column($jejak, 'nama')));
        $this->cek('jejak urut dari akar', ($jejak[0]['nama'] ?? '') === 'UJI Kesiswaan');

        $ket = $m->keturunan((int) $idA);
        sort($ket);
        $this->cek('keturunan(A) = A,B,C', $ket === [(int) $idA, (int) $idB, (int) $idC], implode(',', $ket));

        $this->cek('kedalaman C = 3', $m->kedalaman((int) $idC) === 3);

        // Penjaga terpenting: folder tak boleh dipindah ke dalam dirinya sendiri.
        $this->cek('tolak pindah A ke dalam C (melingkar)', $m->akanMelingkar((int) $idA, (int) $idC) === true);
        $this->cek('tolak pindah A ke A sendiri', $m->akanMelingkar((int) $idA, (int) $idA) === true);
        $this->cek('izinkan pindah C ke akar', $m->akanMelingkar((int) $idC, null) === false);
        $this->cek('izinkan pindah C ke A', $m->akanMelingkar((int) $idC, (int) $idA) === false);

        $opsi = $m->optionsBerjenjang((int) $idA);
        $this->cek('opsi berjenjang membuang A & keturunannya', ! isset($opsi[(int) $idA], $opsi[(int) $idB], $opsi[(int) $idC]));

        $opsiSemua = $m->optionsBerjenjang();
        $label     = $opsiSemua[(int) $idC] ?? '';
        $this->cek('label berjenjang pakai pemisah /', str_contains($label, 'UJI Kesiswaan / UJI Proposal / UJI 2026'), $label);

        // Rantai melingkar buatan tak boleh membuat jejak() berputar selamanya.
        $m->db->table('dokumen_folder')->where('id', $idA)->update(['parent_id' => $idC]);
        $jejakLingkar = $m->jejak((int) $idC);
        $this->cek('jejak() tahan rantai melingkar', count($jejakLingkar) <= DokumenFolderModel::MAKS_KEDALAMAN + 5, count($jejakLingkar) . ' baris');
        $m->db->table('dokumen_folder')->where('id', $idA)->update(['parent_id' => null]);
    }

    private function ujiDokumen(): void
    {
        $this->bagian('Model — DokumenModel');

        $m       = new DokumenModel();
        $folderB = $this->folderUji[1] ?? null;

        $id1 = $m->insert([
            'folder_id' => $folderB, 'judul' => 'UJI Proposal LDKS', 'tipe' => 'berkas',
            'nama_asli' => 'proposal.docx', 'nama_file' => 'x1.docx', 'path_rel' => '2026/09/x1.docx',
            'ekstensi'  => 'docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ukuran'    => 1048576, 'hash_sha256' => str_repeat('a', 64), 'kategori' => 'dokumen', 'visibilitas' => 'privat',
        ], true);

        $id2 = $m->insert([
            'folder_id' => $folderB, 'judul' => 'UJI Anggaran', 'tipe' => 'berkas',
            'nama_asli' => 'anggaran.xlsx', 'nama_file' => 'x2.xlsx', 'path_rel' => '2026/09/x2.xlsx',
            'ekstensi'  => 'xlsx', 'ukuran' => 524288, 'hash_sha256' => str_repeat('b', 64),
            'kategori'  => 'spreadsheet', 'visibilitas' => 'publik',
        ], true);

        $id3 = $m->insert([
            'folder_id' => $folderB, 'judul' => 'UJI Video Upacara', 'tipe' => 'tautan',
            'url_eksternal' => 'https://youtu.be/dQw4w9WgXcQ', 'penyedia' => 'youtube',
            'kategori'      => 'video', 'visibilitas' => 'link',
        ], true);

        $this->dokUji = [(int) $id1, (int) $id2, (int) $id3];
        $this->cek('buat 2 berkas + 1 tautan', $id1 && $id2 && $id3);

        $daftar = $m->daftar((int) $folderB)->findAll();
        $this->cek('daftar folder berisi 3 dokumen', count($daftar) === 3, count($daftar) . ' baris');

        $cariJudul = $m->daftar((int) $folderB, ['q' => 'Anggaran'])->findAll();
        $this->cek('cari judul "Anggaran" → 1', count($cariJudul) === 1);

        $cariNamaAsli = $m->daftar((int) $folderB, ['q' => 'proposal.docx'])->findAll();
        $this->cek('cari berdasar nama berkas asli → 1', count($cariNamaAsli) === 1);

        $filterKat = $m->daftar((int) $folderB, ['kategori' => 'spreadsheet'])->findAll();
        $this->cek('filter kategori spreadsheet → 1', count($filterKat) === 1);

        $filterVis = $m->cariSemua(['visibilitas' => 'publik', 'q' => 'UJI'])->findAll();
        $this->cek('filter visibilitas publik → 1', count($filterVis) === 1);

        $urut = $m->daftar((int) $folderB, ['urut' => 'ukuran', 'arah' => 'DESC'])->findAll();
        $this->cek('urut ukuran DESC menaruh yang terbesar di atas', ($urut[0]['judul'] ?? '') === 'UJI Proposal LDKS', $urut[0]['judul'] ?? '-');

        $urutNakal = $m->daftar((int) $folderB, ['urut' => 'id; DROP TABLE dokumen', 'arah' => 'X'])->findAll();
        $this->cek('kolom urut di luar daftar putih diabaikan', count($urutNakal) === 3);

        $detail = $m->detail((int) $id1);
        $this->cek('detail membawa nama folder', ($detail['folder_nama'] ?? '') === 'UJI Proposal', $detail['folder_nama'] ?? '-');

        $m->tambahHitung((int) $id1, 'jml_unduh');
        $m->tambahHitung((int) $id1, 'jml_unduh');
        $m->tambahHitung((int) $id1, 'kolom_palsu');
        $sesudah = $m->find($id1);
        $this->cek('penghitung unduh naik jadi 2', (int) $sesudah['jml_unduh'] === 2, (string) $sesudah['jml_unduh']);
        $this->cek('kolom penghitung palsu ditolak', true);

        $this->cek('deteksi berkas kembar lewat hash', ($m->serupa(str_repeat('a', 64))['id'] ?? null) == $id1);
        $this->cek('hash kosong tidak dianggap kembar', $m->serupa('') === null);

        $pakai = $m->pemakaian();
        $this->cek('pemakaian menjumlahkan byte berkas', $pakai['total_byte'] >= 1572864, dokumen_ukuran_manusia($pakai['total_byte']));
        $this->cek('pemakaian tak menghitung tautan', $pakai['total_berkas'] >= 2);

        // Tempat sampah
        $m->delete($id2);
        $this->cek('hapus → masuk sampah, hilang dari daftar', count($m->daftar((int) $folderB)->findAll()) === 2);
        $this->cek('sampah berisi dokumen terhapus', count($m->sampah()->where('dokumen.id', $id2)->findAll()) === 1);
        $this->cek('detail(withDeleted) tetap menemukan', $m->detail((int) $id2, true) !== null);

        $m->db->table('dokumen')->where('id', $id2)->update(['deleted_at' => null]);
        $this->cek('pulihkan dari sampah', count($m->daftar((int) $folderB)->findAll()) === 3);
    }

    private function ujiShare(): void
    {
        $this->bagian('Model — DokumenShareModel (tautan berbagi)');

        $m   = new DokumenShareModel();
        $dok = $this->dokUji[0] ?? null;

        $t1 = $m->tokenBaru();
        $this->cek('token 32 karakter heks', preg_match('/^[a-f0-9]{32}$/', $t1) === 1, $t1);
        $this->cek('dua token berbeda', $t1 !== $m->tokenBaru());

        $sid = $m->insert(['dokumen_id' => $dok, 'token' => $t1, 'boleh_unduh' => 1], true);
        $this->cek('buat tautan berbagi', (bool) $sid);

        $p = $m->periksa($t1);
        $this->cek('tautan baru → sah', $p['ok'] === true);

        $this->cek('token asal-asalan → tidak ada', $m->periksa('tokenpalsu')['alasan'] === DokumenShareModel::TOLAK_TIDAK_ADA);

        // Dicabut
        $m->update($sid, ['aktif' => 0]);
        $this->cek('tautan dicabut → ditolak', $m->periksa($t1)['alasan'] === DokumenShareModel::TOLAK_DICABUT);
        $m->update($sid, ['aktif' => 1]);

        // Kedaluwarsa
        $m->update($sid, ['expired_at' => date('Y-m-d H:i:s', time() - 3600)]);
        $this->cek('tautan kedaluwarsa → ditolak', $m->periksa($t1)['alasan'] === DokumenShareModel::TOLAK_KEDALUWARSA);
        $m->update($sid, ['expired_at' => date('Y-m-d H:i:s', time() + 3600)]);
        $this->cek('kedaluwarsa masa depan → tetap sah', $m->periksa($t1)['ok'] === true);

        // Batas unduhan habis
        $m->update($sid, ['maks_unduh' => 2, 'jml_unduh' => 2]);
        $this->cek('batas unduhan habis → ditolak', $m->periksa($t1)['alasan'] === DokumenShareModel::TOLAK_HABIS);
        $m->update($sid, ['maks_unduh' => null, 'jml_unduh' => 0]);

        // Kata sandi
        $row = $m->find($sid);
        $this->cek('tanpa sandi → tak minta sandi', $m->pakaiSandi($row) === false);

        $m->update($sid, ['password_hash' => password_hash('rahasia123', PASSWORD_DEFAULT)]);
        $row = $m->find($sid);
        $this->cek('dengan sandi → minta sandi', $m->pakaiSandi($row) === true);
        $this->cek('sandi benar diterima', $m->sandiCocok($row, 'rahasia123') === true);
        $this->cek('sandi salah ditolak', $m->sandiCocok($row, 'salah') === false);

        $m->tambahHitung((int) $sid, 'jml_akses');
        $this->cek('penghitung akses naik', (int) $m->find($sid)['jml_akses'] === 1);

        $this->cek('daftar tautan per dokumen', count($m->untukDokumen((int) $dok)) === 1);
        $lengkap = $m->daftarLengkap()->where('dokumen_share.id', $sid)->first();
        $this->cek('daftar lengkap membawa judul dokumen', ($lengkap['dokumen_judul'] ?? '') === 'UJI Proposal LDKS', $lengkap['dokumen_judul'] ?? '-');
    }

    private function ujiLog(): void
    {
        $this->bagian('Model — DokumenAksesLogModel');

        $m   = new DokumenAksesLogModel();
        $dok = $this->dokUji[0] ?? null;

        $m->catat('unduh', (int) $dok);
        $m->catat('pratinjau', (int) $dok);
        $m->catat('aksi_ngawur', (int) $dok);

        $riwayat = $m->untukDokumen((int) $dok);
        $this->cek('2 jejak tercatat, aksi ngawur ditolak', count($riwayat) === 2, count($riwayat) . ' baris');
        $this->cek('jejak terbaru di atas', ($riwayat[0]['aksi'] ?? '') === 'pratinjau', $riwayat[0]['aksi'] ?? '-');
        $this->cek('jejak dari CLI tak bikin error', ($riwayat[0]['user_agent'] ?? '') !== '');
    }

    private function bersihkan(): void
    {
        $this->bagian('Bersih-bersih data uji');

        $db = \Config\Database::connect();

        $db->table('dokumen_akses_log')->whereIn('dokumen_id', $this->dokUji ?: [0])->delete();
        $db->table('dokumen_share')->whereIn('dokumen_id', $this->dokUji ?: [0])->delete();
        $db->table('dokumen')->whereIn('id', $this->dokUji ?: [0])->delete();
        $db->table('dokumen_folder')->whereIn('id', $this->folderUji ?: [0])->delete();

        $sisaDok    = $db->table('dokumen')->like('judul', 'UJI ')->countAllResults();
        $sisaFolder = $db->table('dokumen_folder')->like('nama', 'UJI ')->countAllResults();

        $this->cek('tak ada dokumen uji tersisa', $sisaDok === 0, $sisaDok . ' baris');
        $this->cek('tak ada folder uji tersisa', $sisaFolder === 0, $sisaFolder . ' baris');
    }
}
