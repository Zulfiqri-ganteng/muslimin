<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\DokumenAksesLogModel;
use App\Models\DokumenFolderModel;
use App\Models\DokumenModel;
use App\Models\DokumenShareModel;

/**
 * Manajemen Dokumen (SIMDOK) — penjelajah folder, unggah, dan pengelolaan.
 *
 * Pola WORKFLOW (seperti Admin\Peminjaman), bukan BaseMaster: ini bukan
 * tabel master datar yang perlu import/export Excel, melainkan penjelajah
 * berkas berjenjang.
 *
 * Catatan rancangan penting:
 *  - Menghapus folder memindahkan folder ITU BESERTA SELURUH ISINYA
 *    (subfolder & dokumen, rekursif) ke tempat sampah. Semua baris yang
 *    terbawa diberi stempel `deleted_at` yang SAMA PERSIS, sehingga saat
 *    dipulihkan, sistem tahu persis mana yang dulu terhapus bersama-sama
 *    dan mana yang memang sudah lebih dulu ada di sampah.
 *  - Semua aksi yang mengubah data memakai POST (bukan GET seperti modul
 *    lama) supaya tidak menambah utang CSRF.
 */
class Dokumen extends BaseController
{
    protected DokumenModel $model;
    protected DokumenFolderModel $folder;
    protected DokumenShareModel $share;
    protected AuditModel $audit;

    /** Pilihan jumlah baris per halaman. */
    private const PER_PAGE = [12, 24, 48, 96];

    public function __construct()
    {
        $this->model  = new DokumenModel();
        $this->folder = new DokumenFolderModel();
        $this->share  = new DokumenShareModel();
        $this->audit  = new AuditModel();
        helper('dokumen');
    }

    // =================================================================
    //  Penjelajah
    // =================================================================

    public function index()
    {
        $folderId = $this->folderIdDari($this->request->getGet('folder'));

        // Folder yang diminta harus benar-benar ada; kalau tidak, balik ke akar.
        if ($folderId !== null && $this->folder->find($folderId) === null) {
            return redirect()->to(site_url('admin/dokumen'))
                ->with('error', 'Folder tidak ditemukan atau sudah dihapus.');
        }

        $q        = trim((string) $this->request->getGet('q'));
        $kategori = $this->pilihan($this->request->getGet('kategori'), $this->daftarKategori());
        $urut     = $this->pilihan($this->request->getGet('urut'), ['created_at', 'judul', 'ukuran', 'jml_unduh'], 'created_at');
        $arah     = strtoupper((string) $this->request->getGet('arah')) === 'ASC' ? 'ASC' : 'DESC';
        // ikon = tampilan padat ala penjelajah berkas Windows (folder & berkas
        // dalam satu petak), grid = kartu bergambar, daftar = tabel rinci.
        $tampilan = $this->pilihan($this->request->getGet('tampilan'), ['grid', 'daftar', 'ikon'], 'grid');
        $per      = in_array((int) $this->request->getGet('per'), self::PER_PAGE, true)
            ? (int) $this->request->getGet('per') : 24;

        $filter = ['q' => $q, 'kategori' => $kategori, 'urut' => $urut, 'arah' => $arah];

        // Saat mencari, pencarian berlaku ke SELURUH arsip — bukan cuma folder
        // yang sedang dibuka; orang mencari karena lupa menaruhnya di mana.
        $cariGlobal = $q !== '';
        $builder    = $cariGlobal ? $this->model->cariSemua($filter) : $this->model->daftar($folderId, $filter);

        $rows  = $builder->paginate($per, 'dok');
        $pager = $this->model->pager;

        $subfolder = $cariGlobal ? [] : $this->folder->anak($folderId);

        return view('admin/dokumen/index', [
            'title'      => 'Manajemen Dokumen',
            'rows'       => $rows,
            'pager'      => $pager,
            'subfolder'  => $subfolder,
            'isiFolder'  => $this->hitungIsiFolder(array_column($subfolder, 'id')),
            'jejak'      => $folderId !== null ? $this->folder->jejak($folderId) : [],
            'folderId'   => $folderId,
            'folderKini' => $folderId !== null ? $this->folder->find($folderId) : null,
            'q'          => $q,
            'kategori'   => $kategori,
            'urut'       => $urut,
            'arah'       => $arah,
            'tampilan'   => $tampilan,
            'per'        => $per,
            'cariGlobal' => $cariGlobal,
            'kategoriList' => $this->daftarKategori(),
            'folderOpts' => $this->folder->optionsBerjenjang(),
            'pemakaian'  => $this->model->pemakaian(),
            'kuotaByte'  => dokumen_setting('dok_kuota_mb', 2048) * 1024 * 1024,
            'maksByte'   => dokumen_maks_bytes(),
            'jmlSampah'  => $this->model->onlyDeleted()->countAllResults()
                + $this->folder->onlyDeleted()->countAllResults(),
        ]);
    }

    /**
     * Halaman pratinjau satu dokumen.
     *
     * Yang bisa dibaca langsung di sini: PDF, gambar, audio, teks/CSV,
     * Excel (lewat SheetJS), dan Word (lewat mammoth.js) — dua yang
     * terakhir diolah DI PERAMBAN memakai pustaka yang disimpan sendiri di
     * server ini, sehingga berkas privat tidak pernah dikirim ke pihak lain.
     * PowerPoint tak punya penampil di peramban, jadi diarahkan ke unduhan.
     */
    public function pratinjau(int $id)
    {
        $row = $this->model->detail($id);
        if ($row === null) {
            return redirect()->to(site_url('admin/dokumen'))->with('error', 'Dokumen tidak ditemukan.');
        }

        return view('admin/dokumen/pratinjau', [
            'title'  => $row['judul'],
            'd'      => $row,
            'jejak'  => $row['folder_id'] !== null ? $this->folder->jejak((int) $row['folder_id']) : [],
            'shares' => $this->share->untukDokumen($id),
            'riwayat' => (new DokumenAksesLogModel())->untukDokumen($id, 15),
        ]);
    }

    // =================================================================
    //  Berbagi lewat tautan
    // =================================================================

    /** Buat tautan berbagi untuk satu dokumen. */
    public function bagikan(int $id)
    {
        $row = $this->model->find($id);
        if ($row === null) {
            return $this->kembali(null)->with('error', 'Dokumen tidak ditemukan.');
        }

        $sid = $this->buatTautan(['dokumen_id' => $id]);

        if ($sid === null) {
            return redirect()->to(site_url('admin/dokumen/pratinjau/' . $id))
                ->with('error', 'Tautan gagal dibuat.');
        }

        // Dokumen privat tak akan bisa dibuka lewat tautannya sendiri —
        // naikkan otomatis ke 'link' supaya tautan yang baru dibuat berguna.
        if ($row['visibilitas'] === 'privat') {
            $this->model->protect(false)->update($id, ['visibilitas' => 'link']);
            $this->model->protect(true);
        }

        $this->audit->record('create', 'dokumen_share', $sid, 'Buat tautan berbagi dokumen ' . $row['judul']);

        return redirect()->to(site_url('admin/dokumen/pratinjau/' . $id))
            ->with('success', 'Tautan berbagi dibuat. Salin dan kirimkan ke guru yang dituju.');
    }

    /** Buat tautan berbagi untuk satu folder beserta isinya. */
    public function bagikanFolder(int $id)
    {
        $row = $this->folder->find($id);
        if ($row === null) {
            return $this->kembali(null)->with('error', 'Folder tidak ditemukan.');
        }

        $sid = $this->buatTautan(['folder_id' => $id]);

        if ($sid === null) {
            return $this->kembali($id)->with('error', 'Tautan gagal dibuat.');
        }

        $this->audit->record('create', 'dokumen_share', $sid, 'Buat tautan berbagi folder ' . $row['nama']);

        return $this->kembali($id)->with('success', 'Tautan berbagi folder dibuat.');
    }

    /** Cabut sebuah tautan (tautan langsung mati, dokumen tetap aman). */
    public function cabutShare(int $id)
    {
        $row = $this->share->find($id);
        if ($row === null) {
            return $this->kembali(null)->with('error', 'Tautan tidak ditemukan.');
        }

        $this->share->update($id, ['aktif' => 0]);
        $this->audit->record('update', 'dokumen_share', $id, 'Cabut tautan berbagi');

        $balik = $row['dokumen_id'] !== null
            ? site_url('admin/dokumen/pratinjau/' . (int) $row['dokumen_id'])
            : site_url('admin/dokumen?folder=' . (int) $row['folder_id']);

        return redirect()->to($balik)->with('success', 'Tautan dicabut. Siapa pun yang memegangnya tidak bisa lagi membuka dokumen.');
    }

    /**
     * Bagian bersama pembuatan tautan: baca opsi dari formulir lalu simpan.
     *
     * @param array<string, int> $sasaran dokumen_id ATAU folder_id
     */
    private function buatTautan(array $sasaran): ?int
    {
        $sandi   = trim((string) $this->request->getPost('sandi'));
        $kadaluarsa = trim((string) $this->request->getPost('expired_at'));
        $maks    = (int) $this->request->getPost('maks_unduh');

        $data = $sasaran + [
            'token'         => $this->share->tokenBaru(),
            'password_hash' => $sandi !== '' ? password_hash($sandi, PASSWORD_DEFAULT) : null,
            'expired_at'    => $kadaluarsa !== '' ? date('Y-m-d 23:59:59', strtotime($kadaluarsa)) : null,
            'boleh_unduh'   => $this->request->getPost('boleh_unduh') !== null ? 1 : 0,
            'maks_unduh'    => $maks > 0 ? $maks : null,
            'catatan'       => trim((string) $this->request->getPost('catatan')) ?: null,
            'aktif'         => 1,
            'created_by'    => $this->adminId(),
        ];

        $id = $this->share->insert($data, true);

        return $id ? (int) $id : null;
    }

    // =================================================================
    //  Folder
    // =================================================================

    public function folderStore()
    {
        $nama   = trim((string) $this->request->getPost('nama'));
        $induk  = $this->folderIdDari($this->request->getPost('parent_id'));

        if ($nama === '') {
            return $this->kembali($induk)->with('error', 'Nama folder wajib diisi.');
        }

        if ($induk !== null && $this->folder->kedalaman($induk) >= DokumenFolderModel::MAKS_KEDALAMAN) {
            return $this->kembali($induk)->with('error',
                'Folder sudah terlalu dalam (maksimal ' . DokumenFolderModel::MAKS_KEDALAMAN . ' tingkat).');
        }

        $id = $this->folder->insert([
            'nama'        => $nama,
            'parent_id'   => $induk,
            'deskripsi'   => trim((string) $this->request->getPost('deskripsi')) ?: null,
            'visibilitas' => $this->pilihan($this->request->getPost('visibilitas'), ['privat', 'link', 'publik'], 'privat'),
            'created_by'  => $this->adminId(),
        ], true);

        if (! $id) {
            return $this->kembali($induk)->with('error',
                'Folder gagal dibuat: ' . implode(' ', $this->folder->errors()));
        }

        $this->audit->record('create', 'dokumen_folder', (int) $id, 'Buat folder ' . $nama);

        return $this->kembali($induk)->with('success', 'Folder "' . $nama . '" dibuat.');
    }

    public function folderUpdate(int $id)
    {
        $row = $this->folder->find($id);
        if ($row === null) {
            return $this->kembali(null)->with('error', 'Folder tidak ditemukan.');
        }

        $nama = trim((string) $this->request->getPost('nama'));
        if ($nama === '') {
            return $this->kembali((int) $row['parent_id'] ?: null)->with('error', 'Nama folder wajib diisi.');
        }

        $data = [
            'id'          => $id,
            'nama'        => $nama,
            'deskripsi'   => trim((string) $this->request->getPost('deskripsi')) ?: null,
            'visibilitas' => $this->pilihan($this->request->getPost('visibilitas'), ['privat', 'link', 'publik'], $row['visibilitas']),
        ];

        // Pemindahan folder hanya diproses bila memang dikirim.
        if ($this->request->getPost('pindah') !== null) {
            $tujuan = $this->folderIdDari($this->request->getPost('induk_baru'));

            if ($this->folder->akanMelingkar($id, $tujuan)) {
                return $this->kembali($id)->with('error',
                    'Folder tidak bisa dipindahkan ke dalam dirinya sendiri.');
            }
            $data['parent_id'] = $tujuan;
        }

        if (! $this->folder->save($data)) {
            return $this->kembali($id)->with('error', implode(' ', $this->folder->errors()));
        }

        $this->audit->record('update', 'dokumen_folder', $id, 'Ubah folder ' . $nama);

        return $this->kembali($id)->with('success', 'Folder diperbarui.');
    }

    /**
     * Buang folder beserta seluruh isinya ke tempat sampah.
     * Semua baris yang ikut terbawa diberi stempel waktu yang sama.
     */
    public function folderHapus(int $id)
    {
        $row = $this->folder->find($id);
        if ($row === null) {
            return $this->kembali(null)->with('error', 'Folder tidak ditemukan.');
        }

        $induk = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        $ids   = $this->folder->keturunan($id);
        $cap   = date('Y-m-d H:i:s');
        $db    = db_connect();

        $db->transStart();
        $jmlDok = $db->table('dokumen')
            ->whereIn('folder_id', $ids)
            ->where('deleted_at IS NULL', null, false)
            ->update(['deleted_at' => $cap]);
        $db->table('dokumen_folder')
            ->whereIn('id', $ids)
            ->where('deleted_at IS NULL', null, false)
            ->update(['deleted_at' => $cap]);
        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->kembali($induk)->with('error', 'Gagal memindahkan folder ke tempat sampah.');
        }

        $this->audit->record('delete', 'dokumen_folder', $id,
            'Buang folder ' . $row['nama'] . ' (' . count($ids) . ' folder, ' . (int) $jmlDok . ' dokumen)');

        return $this->kembali($induk)->with('success',
            'Folder "' . $row['nama'] . '" beserta isinya dipindahkan ke tempat sampah. Masih bisa dipulihkan.');
    }

    // =================================================================
    //  Unggah & tautan
    // =================================================================

    public function unggah()
    {
        $folderId = $this->folderIdDari($this->request->getPost('folder_id'));
        $berkas   = $this->request->getFileMultiple('berkas');

        if ($berkas === null || $berkas === []) {
            return $this->kembali($folderId)->with('error',
                'Tidak ada berkas yang dipilih. Bila berkas sangat besar, kemungkinan ditolak server sebelum sampai — coba berkas yang lebih kecil.');
        }

        $visibilitas = $this->pilihan($this->request->getPost('visibilitas'), ['privat', 'link', 'publik'], 'privat');
        $sukses      = 0;
        $gagal       = [];
        $kembar      = [];

        foreach ($berkas as $file) {
            // Kolom kosong pada input multiple — bukan kesalahan.
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $err  = null;
            $meta = dokumen_save($file, $err);

            if ($meta === null) {
                $gagal[] = dokumen_nama_aman($file->getClientName()) . ' — ' . $err;

                continue;
            }

            if ($meta['hash_sha256'] !== null && $this->model->serupa($meta['hash_sha256']) !== null) {
                $kembar[] = $meta['nama_asli'];
            }

            $id = $this->model->insert([
                'folder_id'   => $folderId,
                'judul'       => $this->judulDariNama($meta['nama_asli']),
                'tipe'        => 'berkas',
                'nama_asli'   => $meta['nama_asli'],
                'nama_file'   => $meta['nama_file'],
                'path_rel'    => $meta['path_rel'],
                'ekstensi'    => $meta['ekstensi'],
                'mime'        => $meta['mime'],
                'ukuran'      => $meta['ukuran'],
                'hash_sha256' => $meta['hash_sha256'],
                'thumb'       => $meta['thumb'],
                'kategori'    => $meta['kategori'],
                'visibilitas' => $visibilitas,
                'created_by'  => $this->adminId(),
            ], true);

            if (! $id) {
                // Baris gagal tersimpan — jangan tinggalkan berkas yatim di disk.
                dokumen_delete($meta['path_rel'], $meta['thumb']);
                $gagal[] = $meta['nama_asli'] . ' — gagal menyimpan data berkas.';

                continue;
            }

            $sukses++;
            $this->audit->record('create', 'dokumen', (int) $id, 'Unggah ' . $meta['nama_asli']);
        }

        return $this->kembali($folderId)->with(...$this->ringkasUnggah($sukses, $gagal, $kembar));
    }

    public function tautanStore()
    {
        $folderId = $this->folderIdDari($this->request->getPost('folder_id'));
        $url      = trim((string) $this->request->getPost('url_eksternal'));
        $judul    = trim((string) $this->request->getPost('judul'));

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
            return $this->kembali($folderId)->with('error',
                'Tautan harus berupa alamat lengkap yang diawali http:// atau https://');
        }

        $penyedia = dokumen_penyedia($url);

        $id = $this->model->insert([
            'folder_id'     => $folderId,
            'judul'         => $judul !== '' ? $judul : 'Tautan ' . ucfirst($penyedia),
            'deskripsi'     => trim((string) $this->request->getPost('deskripsi')) ?: null,
            'tipe'          => 'tautan',
            'url_eksternal' => $url,
            'penyedia'      => $penyedia,
            'kategori'      => $penyedia === 'youtube' ? 'video' : 'lainnya',
            'visibilitas'   => $this->pilihan($this->request->getPost('visibilitas'), ['privat', 'link', 'publik'], 'privat'),
            'created_by'    => $this->adminId(),
        ], true);

        if (! $id) {
            return $this->kembali($folderId)->with('error', implode(' ', $this->model->errors()));
        }

        $this->audit->record('create', 'dokumen', (int) $id, 'Tambah tautan ' . $url);

        return $this->kembali($folderId)->with('success', 'Tautan ditambahkan.');
    }

    // =================================================================
    //  Dokumen
    // =================================================================

    public function update(int $id)
    {
        $row = $this->model->find($id);
        if ($row === null) {
            return $this->kembali(null)->with('error', 'Dokumen tidak ditemukan.');
        }

        $judul = trim((string) $this->request->getPost('judul'));
        if ($judul === '') {
            return $this->kembali($row['folder_id'])->with('error', 'Judul wajib diisi.');
        }

        $data = [
            'id'          => $id,
            'judul'       => $judul,
            'deskripsi'   => trim((string) $this->request->getPost('deskripsi')) ?: null,
            'visibilitas' => $this->pilihan($this->request->getPost('visibilitas'), ['privat', 'link', 'publik'], $row['visibilitas']),
        ];

        if ($row['tipe'] === 'tautan') {
            $url = trim((string) $this->request->getPost('url_eksternal'));
            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                $data['url_eksternal'] = $url;
                $data['penyedia']      = dokumen_penyedia($url);
            }
        }

        if (! $this->model->save($data)) {
            return $this->kembali($row['folder_id'])->with('error', implode(' ', $this->model->errors()));
        }

        $this->audit->record('update', 'dokumen', $id, 'Ubah dokumen ' . $judul);

        return $this->kembali($row['folder_id'])->with('success', 'Dokumen diperbarui.');
    }

    /** Pindahkan sejumlah dokumen ke folder lain. */
    public function pindah()
    {
        $ids    = array_filter(array_map('intval', (array) $this->request->getPost('ids')));
        $tujuan = $this->folderIdDari($this->request->getPost('tujuan'));
        $asal   = $this->folderIdDari($this->request->getPost('folder_id'));

        if ($ids === []) {
            return $this->kembali($asal)->with('error', 'Tidak ada dokumen yang dipilih.');
        }
        if ($tujuan !== null && $this->folder->find($tujuan) === null) {
            return $this->kembali($asal)->with('error', 'Folder tujuan tidak ditemukan.');
        }

        $this->model->whereIn('id', $ids)->set(['folder_id' => $tujuan, 'updated_at' => date('Y-m-d H:i:s')])->update();
        $this->audit->record('update', 'dokumen', null, 'Pindahkan ' . count($ids) . ' dokumen');

        return $this->kembali($tujuan)->with('success', count($ids) . ' dokumen dipindahkan.');
    }

    /** Buang satu dokumen ke tempat sampah. */
    public function hapus(int $id)
    {
        $row = $this->model->find($id);
        if ($row === null) {
            return $this->kembali(null)->with('error', 'Dokumen tidak ditemukan.');
        }

        $this->model->delete($id);
        $this->audit->record('delete', 'dokumen', $id, 'Buang ke sampah: ' . $row['judul']);

        return $this->kembali($row['folder_id'])->with('success',
            '"' . $row['judul'] . '" dipindahkan ke tempat sampah.');
    }

    /** Buang sejumlah dokumen sekaligus. */
    public function hapusMassal()
    {
        $ids  = array_filter(array_map('intval', (array) $this->request->getPost('ids')));
        $asal = $this->folderIdDari($this->request->getPost('folder_id'));

        if ($ids === []) {
            return $this->kembali($asal)->with('error', 'Tidak ada dokumen yang dipilih.');
        }

        $this->model->delete($ids);
        $this->audit->record('delete', 'dokumen', null, 'Buang ' . count($ids) . ' dokumen ke sampah');

        return $this->kembali($asal)->with('success', count($ids) . ' dokumen dipindahkan ke tempat sampah.');
    }

    // =================================================================
    //  Penyimpanan & pemeliharaan
    // =================================================================

    /**
     * Halaman penggunaan penyimpanan + pemeriksaan kesehatan arsip.
     *
     * Dua hal yang dicari: baris yang berkasnya sudah tak ada di disk
     * (dokumen "kosong"), dan berkas di disk yang tak lagi punya baris
     * (sisa yang memakan ruang diam-diam). Keduanya hanya DILAPORKAN di
     * sini; pembersihan dilakukan lewat tombol tersendiri.
     */
    public function penyimpanan()
    {
        $pakai  = $this->model->pemakaian();
        $periksa = $this->periksaArsip();

        return view('admin/dokumen/penyimpanan', [
            'title'     => 'Penggunaan Penyimpanan',
            'pemakaian' => $pakai,
            'kuotaByte' => dokumen_setting('dok_kuota_mb', 2048) * 1024 * 1024,
            'maksByte'  => dokumen_maks_bytes(),
            'hilang'    => $periksa['hilang'],
            'yatim'     => $periksa['yatim'],
            'yatimByte' => $periksa['yatim_byte'],
        ]);
    }

    /** Buang berkas yatim (ada di disk, tak ada di basis data). */
    public function bersihkanYatim()
    {
        $periksa = $this->periksaArsip();
        $jml     = 0;

        foreach ($periksa['yatim'] as $y) {
            $abs = dokumen_root() . str_replace('/', DIRECTORY_SEPARATOR, $y['path']);
            if (is_file($abs) && @unlink($abs)) {
                $jml++;
            }
        }

        $this->audit->record('delete', 'dokumen', null, 'Bersihkan ' . $jml . ' berkas yatim');

        return redirect()->to(site_url('admin/dokumen/penyimpanan'))
            ->with('success', $jml . ' berkas yatim dihapus dari penyimpanan.');
    }

    /**
     * Bandingkan isi basis data dengan isi folder penyimpanan.
     *
     * @return array{hilang:list<array<string,mixed>>, yatim:list<array{path:string,ukuran:int}>, yatim_byte:int}
     */
    private function periksaArsip(): array
    {
        // Semua path yang SEHARUSNYA ada (termasuk yang di tempat sampah &
        // berkas thumbnail — keduanya sah menghuni disk).
        $baris   = $this->model->withDeleted()->select('id, judul, path_rel, thumb, tipe')->findAll();
        $terdaftar = [];
        $hilang    = [];

        foreach ($baris as $r) {
            if (($r['tipe'] ?? 'berkas') !== 'berkas') {
                continue;
            }
            $rel = trim((string) $r['path_rel']);
            if ($rel !== '') {
                $terdaftar[$rel] = true;
                if (dokumen_path($rel) === null) {
                    $hilang[] = $r;
                }
            }
            $thumb = trim((string) $r['thumb']);
            if ($thumb !== '') {
                $terdaftar[$thumb] = true;
            }
        }

        $yatim     = [];
        $yatimByte = 0;
        $root      = dokumen_root();

        if (is_dir($root)) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iter as $berkas) {
                if (! $berkas->isFile() || $berkas->getFilename() === '.htaccess') {
                    continue;
                }
                $rel = str_replace('\\', '/', substr($berkas->getPathname(), strlen($root)));
                if (! isset($terdaftar[$rel])) {
                    $ukuran      = (int) $berkas->getSize();
                    $yatim[]     = ['path' => $rel, 'ukuran' => $ukuran];
                    $yatimByte  += $ukuran;
                }
            }
        }

        return ['hilang' => $hilang, 'yatim' => $yatim, 'yatim_byte' => $yatimByte];
    }

    // =================================================================
    //  Tempat sampah
    // =================================================================

    public function sampah()
    {
        return view('admin/dokumen/sampah', [
            'title'   => 'Tempat Sampah Dokumen',
            'rows'    => $this->model->sampah()->paginate(25, 'dok'),
            'pager'   => $this->model->pager,
            'folders' => $this->folder->onlyDeleted()->orderBy('deleted_at', 'DESC')->findAll(),
        ]);
    }

    public function pulihkan(int $id)
    {
        $row = $this->model->withDeleted()->find($id);
        if ($row === null) {
            return redirect()->to(site_url('admin/dokumen/sampah'))->with('error', 'Dokumen tidak ditemukan.');
        }

        $pesan = 'Dokumen "' . $row['judul'] . '" dipulihkan.';
        $data  = ['deleted_at' => null];

        // Folder induknya mungkin masih di tempat sampah — dokumen tak boleh
        // dipulihkan ke folder yang tak terlihat, jadi ditaruh di akar.
        if ($row['folder_id'] !== null) {
            $induk = $this->folder->withDeleted()->find((int) $row['folder_id']);
            if ($induk === null || $induk['deleted_at'] !== null) {
                $data['folder_id'] = null;
                $pesan .= ' Folder asalnya masih di tempat sampah, jadi dokumen ditaruh di folder utama.';
            }
        }

        $this->model->protect(false)->update($id, $data);
        $this->model->protect(true);
        $this->audit->record('update', 'dokumen', $id, 'Pulihkan dari sampah: ' . $row['judul']);

        return redirect()->to(site_url('admin/dokumen/sampah'))->with('success', $pesan);
    }

    /**
     * Pulihkan folder beserta isi yang dulu terhapus BERSAMAAN dengannya
     * (dikenali dari stempel deleted_at yang sama persis).
     */
    public function pulihkanFolder(int $id)
    {
        $row = $this->folder->withDeleted()->find($id);
        if ($row === null || $row['deleted_at'] === null) {
            return redirect()->to(site_url('admin/dokumen/sampah'))->with('error', 'Folder tidak ada di tempat sampah.');
        }

        $cap = $row['deleted_at'];
        $ids = $this->folder->keturunan($id, true);
        $db  = db_connect();

        $db->transStart();
        $db->table('dokumen_folder')->whereIn('id', $ids)->where('deleted_at', $cap)->update(['deleted_at' => null]);
        $db->table('dokumen')->whereIn('folder_id', $ids)->where('deleted_at', $cap)->update(['deleted_at' => null]);

        // Bila induknya sendiri masih di sampah, folder ini naik ke akar
        // supaya tidak "hilang" di dalam folder yang tak terlihat.
        if ($row['parent_id'] !== null) {
            $induk = $this->folder->withDeleted()->find((int) $row['parent_id']);
            if ($induk === null || $induk['deleted_at'] !== null) {
                $db->table('dokumen_folder')->where('id', $id)->update(['parent_id' => null]);
            }
        }
        $db->transComplete();

        $this->audit->record('update', 'dokumen_folder', $id, 'Pulihkan folder ' . $row['nama']);

        return redirect()->to(site_url('admin/dokumen/sampah'))
            ->with('success', 'Folder "' . $row['nama'] . '" beserta isinya dipulihkan.');
    }

    /** Hapus permanen satu dokumen — berkas fisiknya ikut dibuang. */
    public function hapusPermanen(int $id)
    {
        $row = $this->model->withDeleted()->find($id);
        if ($row === null) {
            return redirect()->to(site_url('admin/dokumen/sampah'))->with('error', 'Dokumen tidak ditemukan.');
        }

        dokumen_delete($row['path_rel'], $row['thumb']);
        $this->model->delete($id, true);
        $this->audit->record('delete', 'dokumen', $id, 'Hapus permanen: ' . $row['judul']);

        return redirect()->to(site_url('admin/dokumen/sampah'))
            ->with('success', 'Dokumen "' . $row['judul'] . '" dihapus permanen.');
    }

    /** Kosongkan seluruh tempat sampah (dokumen + folder). */
    public function kosongkanSampah()
    {
        $rows = $this->model->onlyDeleted()->findAll();

        foreach ($rows as $r) {
            dokumen_delete($r['path_rel'], $r['thumb']);
        }

        $db = db_connect();
        $db->transStart();
        $db->table('dokumen')->where('deleted_at IS NOT NULL', null, false)->delete();
        $db->table('dokumen_folder')->where('deleted_at IS NOT NULL', null, false)->delete();
        $db->transComplete();

        $this->audit->record('delete', 'dokumen', null, 'Kosongkan tempat sampah (' . count($rows) . ' dokumen)');

        return redirect()->to(site_url('admin/dokumen/sampah'))
            ->with('success', 'Tempat sampah dikosongkan (' . count($rows) . ' dokumen dihapus permanen).');
    }

    // =================================================================
    //  Pembantu
    // =================================================================

    /**
     * Jumlah dokumen langsung di dalam tiap folder — satu query untuk semua
     * folder sekaligus, bukan satu query per baris.
     *
     * @param list<int|string> $ids
     *
     * @return array<int, int>
     */
    private function hitungIsiFolder(array $ids): array
    {
        $ids = array_filter(array_map('intval', $ids));
        if ($ids === []) {
            return [];
        }

        $baris = db_connect()->table('dokumen')
            ->select('folder_id, COUNT(*) AS jml')
            ->whereIn('folder_id', $ids)
            ->where('deleted_at IS NULL', null, false)
            ->groupBy('folder_id')
            ->get()->getResultArray();

        $out = [];

        foreach ($baris as $r) {
            $out[(int) $r['folder_id']] = (int) $r['jml'];
        }

        return $out;
    }

    /** Ubah masukan mentah jadi id folder yang sah, atau null untuk akar. */
    private function folderIdDari($nilai): ?int
    {
        $id = (int) $nilai;

        return $id > 0 ? $id : null;
    }

    /** Pastikan sebuah nilai ada di daftar yang diizinkan. */
    private function pilihan($nilai, array $daftar, string $bawaan = ''): string
    {
        $nilai = trim((string) $nilai);

        return in_array($nilai, $daftar, true) ? $nilai : $bawaan;
    }

    private function daftarKategori(): array
    {
        return ['pdf', 'dokumen', 'spreadsheet', 'presentasi', 'gambar', 'audio', 'video', 'arsip', 'lainnya'];
    }

    private function adminId(): ?int
    {
        $id = session('admin')['id'] ?? null;

        return $id !== null ? (int) $id : null;
    }

    /** Kembali ke penjelajah pada folder tertentu. */
    private function kembali(?int $folderId)
    {
        $url = site_url('admin/dokumen');
        if ($folderId !== null) {
            $url .= '?folder=' . $folderId;
        }

        return redirect()->to($url);
    }

    /** Judul bawaan dari nama berkas: buang ekstensinya saja. */
    private function judulDariNama(string $namaAsli): string
    {
        $judul = preg_replace('/\.[A-Za-z0-9]{1,10}$/', '', $namaAsli) ?? $namaAsli;

        return mb_substr(trim($judul) !== '' ? trim($judul) : $namaAsli, 0, 200);
    }

    /**
     * Susun satu pesan yang jujur tentang hasil unggahan massal:
     * berapa berhasil, mana yang gagal dan kenapa, mana yang kembar.
     *
     * @return array{0:string, 1:string}
     */
    private function ringkasUnggah(int $sukses, array $gagal, array $kembar): array
    {
        $catatan = [];

        if ($sukses > 0) {
            $catatan[] = $sukses . ' berkas berhasil diunggah.';
        }
        if ($kembar !== []) {
            $catatan[] = 'Sudah pernah ada sebelumnya (isi sama persis): ' . implode(', ', $kembar) . '.';
        }
        if ($gagal !== []) {
            $catatan[] = count($gagal) . ' gagal → ' . implode(' | ', $gagal);
        }

        $pesan = implode(' ', $catatan) ?: 'Tidak ada berkas yang diproses.';

        return [$gagal !== [] && $sukses === 0 ? 'error' : 'success', $pesan];
    }
}
