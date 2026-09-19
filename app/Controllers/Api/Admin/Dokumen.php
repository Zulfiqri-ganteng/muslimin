<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\DokumenStream;
use App\Models\AuditModel;
use App\Models\DokumenAksesLogModel;
use App\Models\DokumenFolderModel;
use App\Models\DokumenModel;
use App\Models\DokumenShareModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Manajemen Dokumen (API) — cermin App\Controllers\Admin\Dokumen.
 *
 * Perbedaan yang disengaja dari sisi web:
 *  - `index` memulangkan SATU amplop berisi semua yang dibutuhkan satu layar
 *    (folder kini, remah jejak, subfolder, dokumen, pemakaian penyimpanan)
 *    supaya aplikasi Android cukup sekali panggil per layar — hemat kuota
 *    dan terasa cepat di jaringan sekolah.
 *  - Tiap dokumen membawa URL lengkap berkas/unduhan/thumbnail sehingga
 *    klien tak perlu merangkai alamat sendiri.
 *  - Penyajian berkas memakai App\Libraries\DokumenStream yang sama dengan
 *    web, jadi Range/ETag/pengamanan tipe berlaku identik di HP.
 *
 * Rute: lihat grup `api/v1` → `admin/dokumen*` di Config\Routes.
 */
class Dokumen extends BaseApiController
{
    protected DokumenModel $model;
    protected DokumenFolderModel $folder;
    protected DokumenShareModel $share;
    protected DokumenAksesLogModel $log;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->model  = new DokumenModel();
        $this->folder = new DokumenFolderModel();
        $this->share  = new DokumenShareModel();
        $this->log    = new DokumenAksesLogModel();
        $this->audit  = new AuditModel();
        helper('dokumen');
    }

    // =================================================================
    //  Penjelajah
    // =================================================================

    public function index(): ResponseInterface
    {
        $folderId = $this->folderIdDari($this->request->getGet('folder'));

        if ($folderId !== null && $this->folder->find($folderId) === null) {
            return $this->missing('Folder tidak ditemukan.');
        }

        $q        = trim((string) $this->request->getGet('q'));
        $kategori = $this->pilihan($this->request->getGet('kategori'), $this->daftarKategori());
        $urut     = $this->pilihan($this->request->getGet('urut'), ['created_at', 'judul', 'ukuran', 'jml_unduh'], 'created_at');
        $arah     = strtoupper((string) $this->request->getGet('arah')) === 'ASC' ? 'ASC' : 'DESC';
        $per      = max(5, min(100, (int) ($this->request->getGet('per') ?: 30)));
        $page     = max(1, (int) ($this->request->getGet('page') ?: 1));

        $filter     = ['q' => $q, 'kategori' => $kategori, 'urut' => $urut, 'arah' => $arah];
        $cariGlobal = $q !== '';

        $builder = $cariGlobal ? $this->model->cariSemua($filter) : $this->model->daftar($folderId, $filter);
        $rows    = $builder->paginate($per, 'dok', $page);
        $total   = $this->model->pager->getTotal('dok');

        $subfolder = $cariGlobal ? [] : $this->folder->anak($folderId);
        $isi       = $this->hitungIsiFolder(array_column($subfolder, 'id'));

        $pakai    = $this->model->pemakaian();
        $kuota    = dokumen_setting('dok_kuota_mb', 2048) * 1024 * 1024;
        $terpakai = (int) $pakai['total_byte'];

        return $this->ok([
            'folder' => $folderId !== null ? $this->transformFolder($this->folder->find($folderId), $isi) : null,
            'jejak'  => $folderId !== null
                ? array_map(static fn ($j) => ['id' => (int) $j['id'], 'nama' => $j['nama']], $this->folder->jejak($folderId))
                : [],
            'subfolder'   => array_map(fn ($f) => $this->transformFolder($f, $isi), $subfolder),
            'dokumen'     => array_map([$this, 'transform'], $rows),
            'cari_global' => $cariGlobal,
            'penyimpanan' => [
                'terpakai'         => $terpakai,
                'terpakai_teks'    => dokumen_ukuran_manusia($terpakai),
                'kuota'            => $kuota,
                'kuota_teks'       => dokumen_ukuran_manusia($kuota),
                'persen'           => $kuota > 0 ? min(100, (int) round($terpakai / $kuota * 100)) : 0,
                'jml_berkas'       => (int) $pakai['total_berkas'],
                'maks_berkas'      => dokumen_maks_bytes(),
                'maks_berkas_teks' => dokumen_ukuran_manusia(dokumen_maks_bytes()),
                'izinkan_video'    => dokumen_izinkan_video(),
            ],
        ], 'Berhasil', [
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per,
                'total'       => $total,
                'total_pages' => $per > 0 ? (int) ceil($total / $per) : 1,
            ],
        ]);
    }

    public function detail($id = 0): ResponseInterface
    {
        $row = $this->model->detail((int) $id);
        if ($row === null) {
            return $this->missing('Dokumen tidak ditemukan.');
        }

        return $this->ok([
            'dokumen' => $this->transform($row) + [
                'folder_nama' => $row['folder_nama'] ?? null,
                'deskripsi'   => $row['deskripsi'],
            ],
            'share'   => array_map([$this, 'transformShare'], $this->share->untukDokumen((int) $id)),
            'riwayat' => array_map(static fn ($r) => [
                'aksi'       => $r['aksi'],
                'oleh'       => $r['admin_nama'] ?? 'Tamu lewat tautan',
                'created_at' => $r['created_at'],
            ], $this->log->untukDokumen((int) $id, 15)),
        ]);
    }

    // =================================================================
    //  Penyajian berkas (Bearer token)
    // =================================================================

    public function berkas($id = 0)
    {
        return $this->sajikan((int) $id, true, 'pratinjau');
    }

    public function unduh($id = 0)
    {
        return $this->sajikan((int) $id, false, 'unduh');
    }

    public function thumb($id = 0)
    {
        $row = $this->model->find((int) $id);
        if ($row === null || trim((string) $row['thumb']) === '') {
            return $this->missing('Thumbnail tidak ada.');
        }

        $path = dokumen_path($row['thumb']);
        if ($path === null) {
            return $this->missing('Thumbnail tidak ditemukan.');
        }

        DokumenStream::kirim($path, [
            'nama'   => 'thumb-' . (int) $id . '.webp',
            'mime'   => str_ends_with($path, '.jpg') ? 'image/jpeg' : 'image/webp',
            'inline' => true,
        ]);
    }

    private function sajikan(int $id, bool $inline, string $aksi)
    {
        $row = $this->model->withDeleted()->find($id);
        if ($row === null) {
            return $this->missing('Dokumen tidak ditemukan.');
        }

        if (($row['tipe'] ?? 'berkas') !== 'berkas') {
            return $this->ok(['url_eksternal' => $row['url_eksternal']], 'Dokumen ini berupa tautan.');
        }

        $path = dokumen_path($row['path_rel']);
        if ($path === null) {
            return $this->missing('Berkas sudah tidak ada di penyimpanan.');
        }

        $adminId = $this->adminId();

        DokumenStream::kirim($path, [
            'nama'    => (string) ($row['nama_asli'] ?: $row['judul']),
            'mime'    => (string) ($row['mime'] ?: 'application/octet-stream'),
            'inline'  => $inline,
            'hash'    => (string) $row['hash_sha256'],
            'onKirim' => function () use ($id, $aksi, $adminId) {
                $this->model->tambahHitung($id, $aksi === 'unduh' ? 'jml_unduh' : 'jml_lihat');
                $this->log->catat($aksi, $id, null, $adminId);
            },
        ]);
    }

    // =================================================================
    //  Folder
    // =================================================================

    public function folderStore(): ResponseInterface
    {
        $b    = $this->body();
        $nama = trim((string) ($b['nama'] ?? ''));

        if ($nama === '') {
            return $this->invalid(['nama' => 'Nama folder wajib diisi.']);
        }

        $induk = $this->folderIdDari($b['parent_id'] ?? null);
        if ($induk !== null && $this->folder->kedalaman($induk) >= DokumenFolderModel::MAKS_KEDALAMAN) {
            return $this->failure('Folder sudah terlalu dalam (maksimal ' . DokumenFolderModel::MAKS_KEDALAMAN . ' tingkat).', 422);
        }

        $id = $this->folder->insert([
            'nama'        => $nama,
            'parent_id'   => $induk,
            'deskripsi'   => trim((string) ($b['deskripsi'] ?? '')) ?: null,
            'visibilitas' => $this->pilihan($b['visibilitas'] ?? '', ['privat', 'link', 'publik'], 'privat'),
            'created_by'  => $this->adminId(),
        ], true);

        if (! $id) {
            return $this->invalid($this->folder->errors());
        }

        $this->audit->record('create', 'dokumen_folder', (int) $id, 'Buat folder ' . $nama . ' (API)');

        return $this->created($this->transformFolder($this->folder->find($id), []), 'Folder dibuat.');
    }

    public function folderUpdate($id = 0): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->folder->find($id);
        if ($row === null) {
            return $this->missing('Folder tidak ditemukan.');
        }

        $b    = $this->body();
        $nama = trim((string) ($b['nama'] ?? $row['nama']));
        if ($nama === '') {
            return $this->invalid(['nama' => 'Nama folder wajib diisi.']);
        }

        $data = [
            'id'          => $id,
            'nama'        => $nama,
            'deskripsi'   => array_key_exists('deskripsi', $b) ? (trim((string) $b['deskripsi']) ?: null) : $row['deskripsi'],
            'visibilitas' => $this->pilihan($b['visibilitas'] ?? '', ['privat', 'link', 'publik'], $row['visibilitas']),
        ];

        if (array_key_exists('parent_id', $b)) {
            $tujuan = $this->folderIdDari($b['parent_id']);
            if ($this->folder->akanMelingkar($id, $tujuan)) {
                return $this->failure('Folder tidak bisa dipindahkan ke dalam dirinya sendiri.', 422);
            }
            $data['parent_id'] = $tujuan;
        }

        if (! $this->folder->save($data)) {
            return $this->invalid($this->folder->errors());
        }

        $this->audit->record('update', 'dokumen_folder', $id, 'Ubah folder ' . $nama . ' (API)');

        return $this->ok($this->transformFolder($this->folder->find($id), []), 'Folder diperbarui.');
    }

    public function folderDestroy($id = 0): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->folder->find($id);
        if ($row === null) {
            return $this->missing('Folder tidak ditemukan.');
        }

        $ids = $this->folder->keturunan($id);
        $cap = date('Y-m-d H:i:s');
        $db  = db_connect();

        $db->transStart();
        $jml = $db->table('dokumen')->whereIn('folder_id', $ids)->where('deleted_at IS NULL', null, false)->update(['deleted_at' => $cap]);
        $db->table('dokumen_folder')->whereIn('id', $ids)->where('deleted_at IS NULL', null, false)->update(['deleted_at' => $cap]);
        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->failure('Gagal memindahkan folder ke tempat sampah.', 500);
        }

        $this->audit->record('delete', 'dokumen_folder', $id, 'Buang folder ' . $row['nama'] . ' (API)');

        return $this->ok(['folder' => count($ids), 'dokumen' => (int) $jml],
            'Folder beserta isinya dipindahkan ke tempat sampah.');
    }

    // =================================================================
    //  Unggah, tautan, ubah, pindah
    // =================================================================

    public function unggah(): ResponseInterface
    {
        $folderId = $this->folderIdDari($this->request->getPost('folder_id'));

        $berkas = $this->request->getFileMultiple('berkas');
        if (empty($berkas)) {
            $satu   = $this->request->getFile('berkas');
            $berkas = $satu ? [$satu] : [];
        }
        if (empty($berkas)) {
            return $this->invalid(['berkas' => 'Tidak ada berkas yang dikirim.']);
        }

        $visibilitas = $this->pilihan($this->request->getPost('visibilitas'), ['privat', 'link', 'publik'], 'privat');
        $sukses      = [];
        $gagal       = [];

        foreach ($berkas as $file) {
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $err  = null;
            $meta = dokumen_save($file, $err);

            if ($meta === null) {
                $gagal[] = ['nama' => dokumen_nama_aman($file->getClientName()), 'alasan' => $err];

                continue;
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
                dokumen_delete($meta['path_rel'], $meta['thumb']);
                $gagal[] = ['nama' => $meta['nama_asli'], 'alasan' => 'Gagal menyimpan data berkas.'];

                continue;
            }

            $sukses[] = $this->transform($this->model->find($id));
            $this->audit->record('create', 'dokumen', (int) $id, 'Unggah ' . $meta['nama_asli'] . ' (API)');
        }

        if ($sukses === []) {
            return $this->failure('Semua berkas gagal diunggah.', 422, ['gagal' => $gagal]);
        }

        return $this->created(['berhasil' => $sukses, 'gagal' => $gagal],
            count($sukses) . ' berkas berhasil diunggah.' . ($gagal !== [] ? ' ' . count($gagal) . ' gagal.' : ''));
    }

    public function tautanStore(): ResponseInterface
    {
        $b   = $this->body();
        $url = trim((string) ($b['url_eksternal'] ?? ''));

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
            return $this->invalid(['url_eksternal' => 'Tautan harus URL lengkap (http:// atau https://).']);
        }

        $penyedia = dokumen_penyedia($url);
        $judul    = trim((string) ($b['judul'] ?? ''));

        $id = $this->model->insert([
            'folder_id'     => $this->folderIdDari($b['folder_id'] ?? null),
            'judul'         => $judul !== '' ? $judul : 'Tautan ' . ucfirst($penyedia),
            'deskripsi'     => trim((string) ($b['deskripsi'] ?? '')) ?: null,
            'tipe'          => 'tautan',
            'url_eksternal' => $url,
            'penyedia'      => $penyedia,
            'kategori'      => $penyedia === 'youtube' ? 'video' : 'lainnya',
            'visibilitas'   => $this->pilihan($b['visibilitas'] ?? '', ['privat', 'link', 'publik'], 'privat'),
            'created_by'    => $this->adminId(),
        ], true);

        if (! $id) {
            return $this->invalid($this->model->errors());
        }

        $this->audit->record('create', 'dokumen', (int) $id, 'Tambah tautan ' . $url . ' (API)');

        return $this->created($this->transform($this->model->find($id)), 'Tautan ditambahkan.');
    }

    public function update($id = 0): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if ($row === null) {
            return $this->missing('Dokumen tidak ditemukan.');
        }

        $b     = $this->body();
        $judul = trim((string) ($b['judul'] ?? $row['judul']));
        if ($judul === '') {
            return $this->invalid(['judul' => 'Judul wajib diisi.']);
        }

        $data = [
            'id'          => $id,
            'judul'       => $judul,
            'deskripsi'   => array_key_exists('deskripsi', $b) ? (trim((string) $b['deskripsi']) ?: null) : $row['deskripsi'],
            'visibilitas' => $this->pilihan($b['visibilitas'] ?? '', ['privat', 'link', 'publik'], $row['visibilitas']),
        ];

        if ($row['tipe'] === 'tautan' && ! empty($b['url_eksternal'])) {
            $url = trim((string) $b['url_eksternal']);
            if (preg_match('#^https?://#i', $url)) {
                $data['url_eksternal'] = $url;
                $data['penyedia']      = dokumen_penyedia($url);
            }
        }

        if (! $this->model->save($data)) {
            return $this->invalid($this->model->errors());
        }

        $this->audit->record('update', 'dokumen', $id, 'Ubah dokumen ' . $judul . ' (API)');

        return $this->ok($this->transform($this->model->find($id)), 'Dokumen diperbarui.');
    }

    public function pindah(): ResponseInterface
    {
        $b   = $this->body();
        $ids = array_filter(array_map('intval', (array) ($b['ids'] ?? [])));

        if ($ids === []) {
            return $this->invalid(['ids' => 'Tidak ada dokumen yang dipilih.']);
        }

        $tujuan = $this->folderIdDari($b['tujuan'] ?? null);
        if ($tujuan !== null && $this->folder->find($tujuan) === null) {
            return $this->missing('Folder tujuan tidak ditemukan.');
        }

        $this->model->whereIn('id', $ids)->set(['folder_id' => $tujuan, 'updated_at' => date('Y-m-d H:i:s')])->update();
        $this->audit->record('update', 'dokumen', null, 'Pindahkan ' . count($ids) . ' dokumen (API)');

        return $this->ok(['jumlah' => count($ids)], count($ids) . ' dokumen dipindahkan.');
    }

    // =================================================================
    //  Tempat sampah
    // =================================================================

    public function destroy($id = 0): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if ($row === null) {
            return $this->missing('Dokumen tidak ditemukan.');
        }

        $this->model->delete($id);
        $this->audit->record('delete', 'dokumen', $id, 'Buang ke sampah: ' . $row['judul'] . ' (API)');

        return $this->ok(null, '"' . $row['judul'] . '" dipindahkan ke tempat sampah.');
    }

    public function sampah(): ResponseInterface
    {
        $per   = max(5, min(100, (int) ($this->request->getGet('per') ?: 30)));
        $page  = max(1, (int) ($this->request->getGet('page') ?: 1));
        $rows  = $this->model->sampah()->paginate($per, 'dok', $page);
        $total = $this->model->pager->getTotal('dok');

        $folders = $this->folder->onlyDeleted()->orderBy('deleted_at', 'DESC')->findAll();

        return $this->ok([
            'dokumen' => array_map([$this, 'transform'], $rows),
            'folder'  => array_map(static fn ($f) => [
                'id' => (int) $f['id'], 'nama' => $f['nama'], 'deleted_at' => $f['deleted_at'],
            ], $folders),
        ], 'Berhasil', [
            'pagination' => [
                'page' => $page, 'per_page' => $per, 'total' => $total,
                'total_pages' => $per > 0 ? (int) ceil($total / $per) : 1,
            ],
        ]);
    }

    public function pulihkan($id = 0): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->model->withDeleted()->find($id);
        if ($row === null) {
            return $this->missing('Dokumen tidak ditemukan.');
        }

        $data  = ['deleted_at' => null];
        $pesan = 'Dokumen dipulihkan.';

        if ($row['folder_id'] !== null) {
            $induk = $this->folder->withDeleted()->find((int) $row['folder_id']);
            if ($induk === null || $induk['deleted_at'] !== null) {
                $data['folder_id'] = null;
                $pesan .= ' Folder asalnya masih di tempat sampah, jadi dokumen ditaruh di folder utama.';
            }
        }

        $this->model->protect(false)->update($id, $data);
        $this->model->protect(true);
        $this->audit->record('update', 'dokumen', $id, 'Pulihkan dari sampah (API)');

        return $this->ok($this->transform($this->model->find($id)), $pesan);
    }

    public function pulihkanFolder($id = 0): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->folder->withDeleted()->find($id);
        if ($row === null || $row['deleted_at'] === null) {
            return $this->missing('Folder tidak ada di tempat sampah.');
        }

        $cap = $row['deleted_at'];
        $ids = $this->folder->keturunan($id, true);
        $db  = db_connect();

        $db->transStart();
        $db->table('dokumen_folder')->whereIn('id', $ids)->where('deleted_at', $cap)->update(['deleted_at' => null]);
        $db->table('dokumen')->whereIn('folder_id', $ids)->where('deleted_at', $cap)->update(['deleted_at' => null]);

        if ($row['parent_id'] !== null) {
            $induk = $this->folder->withDeleted()->find((int) $row['parent_id']);
            if ($induk === null || $induk['deleted_at'] !== null) {
                $db->table('dokumen_folder')->where('id', $id)->update(['parent_id' => null]);
            }
        }
        $db->transComplete();

        $this->audit->record('update', 'dokumen_folder', $id, 'Pulihkan folder ' . $row['nama'] . ' (API)');

        return $this->ok(null, 'Folder "' . $row['nama'] . '" beserta isinya dipulihkan.');
    }

    public function hapusPermanen($id = 0): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->model->withDeleted()->find($id);
        if ($row === null) {
            return $this->missing('Dokumen tidak ditemukan.');
        }

        dokumen_delete($row['path_rel'], $row['thumb']);
        $this->model->delete($id, true);
        $this->audit->record('delete', 'dokumen', $id, 'Hapus permanen: ' . $row['judul'] . ' (API)');

        return $this->ok(null, 'Dokumen dihapus permanen.');
    }

    // =================================================================
    //  Berbagi
    // =================================================================

    public function bagikan($id = 0): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->model->find($id);
        if ($row === null) {
            return $this->missing('Dokumen tidak ditemukan.');
        }

        $sid = $this->buatTautan(['dokumen_id' => $id]);
        if ($sid === null) {
            return $this->failure('Tautan gagal dibuat.', 500);
        }

        // Sama seperti web: dokumen privat dinaikkan ke 'link' supaya tautan
        // yang baru dibuat benar-benar bisa dibuka penerimanya.
        if ($row['visibilitas'] === 'privat') {
            $this->model->protect(false)->update($id, ['visibilitas' => 'link']);
            $this->model->protect(true);
        }

        $this->audit->record('create', 'dokumen_share', $sid, 'Buat tautan berbagi (API)');

        return $this->created($this->transformShare($this->share->find($sid)), 'Tautan berbagi dibuat.');
    }

    public function bagikanFolder($id = 0): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->folder->find($id);
        if ($row === null) {
            return $this->missing('Folder tidak ditemukan.');
        }

        $sid = $this->buatTautan(['folder_id' => $id]);
        if ($sid === null) {
            return $this->failure('Tautan gagal dibuat.', 500);
        }

        $this->audit->record('create', 'dokumen_share', $sid, 'Buat tautan berbagi folder (API)');

        return $this->created($this->transformShare($this->share->find($sid)), 'Tautan berbagi folder dibuat.');
    }

    public function cabutShare($id = 0): ResponseInterface
    {
        $id  = (int) $id;
        $row = $this->share->find($id);
        if ($row === null) {
            return $this->missing('Tautan tidak ditemukan.');
        }

        $this->share->update($id, ['aktif' => 0]);
        $this->audit->record('update', 'dokumen_share', $id, 'Cabut tautan berbagi (API)');

        return $this->ok(null, 'Tautan dicabut.');
    }

    /** @param array<string, int> $sasaran */
    private function buatTautan(array $sasaran): ?int
    {
        $b          = $this->body();
        $sandi      = trim((string) ($b['sandi'] ?? ''));
        $kadaluarsa = trim((string) ($b['expired_at'] ?? ''));
        $maks       = (int) ($b['maks_unduh'] ?? 0);

        $id = $this->share->insert($sasaran + [
            'token'         => $this->share->tokenBaru(),
            'password_hash' => $sandi !== '' ? password_hash($sandi, PASSWORD_DEFAULT) : null,
            'expired_at'    => $kadaluarsa !== '' ? date('Y-m-d 23:59:59', strtotime($kadaluarsa)) : null,
            'boleh_unduh'   => ! isset($b['boleh_unduh']) || (int) $b['boleh_unduh'] === 1 ? 1 : 0,
            'maks_unduh'    => $maks > 0 ? $maks : null,
            'catatan'       => trim((string) ($b['catatan'] ?? '')) ?: null,
            'aktif'         => 1,
            'created_by'    => $this->adminId(),
        ], true);

        return $id ? (int) $id : null;
    }

    // =================================================================
    //  Penyimpanan
    // =================================================================

    public function penyimpanan(): ResponseInterface
    {
        $pakai = $this->model->pemakaian();
        $kuota = dokumen_setting('dok_kuota_mb', 2048) * 1024 * 1024;
        $total = (int) $pakai['total_byte'];

        return $this->ok([
            'terpakai'      => $total,
            'terpakai_teks' => dokumen_ukuran_manusia($total),
            'kuota'         => $kuota,
            'kuota_teks'    => dokumen_ukuran_manusia($kuota),
            'persen'        => $kuota > 0 ? min(100, (int) round($total / $kuota * 100)) : 0,
            'jml_berkas'    => (int) $pakai['total_berkas'],
            'sampah'        => (int) $pakai['sampah_byte'],
            'sampah_teks'   => dokumen_ukuran_manusia((int) $pakai['sampah_byte']),
            'per_kategori'  => array_map(static fn ($k) => [
                'kategori'   => $k['kategori'],
                'jumlah'     => (int) $k['jml'],
                'byte'       => (int) $k['byte_total'],
                'byte_teks'  => dokumen_ukuran_manusia((int) $k['byte_total']),
            ], $pakai['per_kategori']),
        ]);
    }

    // =================================================================
    //  Pembantu
    // =================================================================

    /** @return array<string, mixed> */
    private function transform(array $r): array
    {
        $id     = (int) $r['id'];
        $tautan = ($r['tipe'] ?? 'berkas') === 'tautan';
        $base   = rtrim(base_url('api/v1/admin/dokumen'), '/');

        return [
            'id'            => $id,
            'folder_id'     => $r['folder_id'] !== null ? (int) $r['folder_id'] : null,
            'judul'         => $r['judul'],
            'deskripsi'     => $r['deskripsi'] ?? null,
            'tipe'          => $r['tipe'],
            'nama_asli'     => $r['nama_asli'],
            'ekstensi'      => $r['ekstensi'],
            'mime'          => $r['mime'],
            'ukuran'        => $r['ukuran'] !== null ? (int) $r['ukuran'] : null,
            'ukuran_teks'   => $tautan ? null : dokumen_ukuran_manusia((int) $r['ukuran']),
            'kategori'      => $r['kategori'],
            'visibilitas'   => $r['visibilitas'],
            'url_eksternal' => $r['url_eksternal'],
            'penyedia'      => $r['penyedia'],
            'punya_thumb'   => trim((string) $r['thumb']) !== '',
            'url_thumb'     => trim((string) $r['thumb']) !== '' ? $base . '/' . $id . '/thumb' : null,
            'url_berkas'    => $tautan ? $r['url_eksternal'] : $base . '/' . $id . '/berkas',
            'url_unduh'     => $tautan ? $r['url_eksternal'] : $base . '/' . $id . '/unduh',
            // Dipakai aplikasi untuk memutuskan: tampilkan di layar sendiri,
            // atau serahkan ke aplikasi lain di HP (Word/PowerPoint dsb).
            'bisa_pratinjau' => ! $tautan && dokumen_bisa_pratinjau((string) $r['kategori']),
            'jml_lihat'      => (int) $r['jml_lihat'],
            'jml_unduh'      => (int) $r['jml_unduh'],
            'pengunggah'     => $r['pengunggah'] ?? null,
            'created_at'     => $r['created_at'],
            'deleted_at'     => $r['deleted_at'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function transformFolder(?array $f, array $isi): array
    {
        if ($f === null) {
            return [];
        }

        return [
            'id'          => (int) $f['id'],
            'nama'        => $f['nama'],
            'parent_id'   => $f['parent_id'] !== null ? (int) $f['parent_id'] : null,
            'deskripsi'   => $f['deskripsi'],
            'visibilitas' => $f['visibilitas'],
            'jml_berkas'  => (int) ($isi[(int) $f['id']] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function transformShare(?array $s): array
    {
        if ($s === null) {
            return [];
        }

        $lewat = ! empty($s['expired_at']) && strtotime((string) $s['expired_at']) < time();
        $habis = $s['maks_unduh'] !== null && (int) $s['jml_unduh'] >= (int) $s['maks_unduh'];

        return [
            'id'          => (int) $s['id'],
            'token'       => $s['token'],
            'url'         => site_url('d/' . $s['token']),
            'dokumen_id'  => $s['dokumen_id'] !== null ? (int) $s['dokumen_id'] : null,
            'folder_id'   => $s['folder_id'] !== null ? (int) $s['folder_id'] : null,
            'pakai_sandi' => trim((string) $s['password_hash']) !== '',
            'expired_at'  => $s['expired_at'],
            'boleh_unduh' => (int) $s['boleh_unduh'] === 1,
            'maks_unduh'  => $s['maks_unduh'] !== null ? (int) $s['maks_unduh'] : null,
            'jml_akses'   => (int) $s['jml_akses'],
            'jml_unduh'   => (int) $s['jml_unduh'],
            'catatan'     => $s['catatan'],
            'aktif'       => (int) $s['aktif'] === 1,
            'status'      => (int) $s['aktif'] !== 1 ? 'dicabut' : ($lewat ? 'kedaluwarsa' : ($habis ? 'habis' : 'aktif')),
            'created_at'  => $s['created_at'],
        ];
    }

    /** @param list<int|string> $ids @return array<int,int> */
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

    private function folderIdDari($nilai): ?int
    {
        $id = (int) $nilai;

        return $id > 0 ? $id : null;
    }

    private function pilihan($nilai, array $daftar, string $bawaan = ''): string
    {
        $nilai = trim((string) $nilai);

        return in_array($nilai, $daftar, true) ? $nilai : $bawaan;
    }

    private function daftarKategori(): array
    {
        return ['pdf', 'dokumen', 'spreadsheet', 'presentasi', 'gambar', 'audio', 'video', 'arsip', 'lainnya'];
    }

    private function judulDariNama(string $namaAsli): string
    {
        $judul = preg_replace('/\.[A-Za-z0-9]{1,10}$/', '', $namaAsli) ?? $namaAsli;

        return mb_substr(trim($judul) !== '' ? trim($judul) : $namaAsli, 0, 200);
    }
}
