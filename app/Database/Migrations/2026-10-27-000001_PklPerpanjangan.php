<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Surat Permohonan Perpanjangan PKL (permintaan user 2026-10-26; rancangan: docs/DESAIN-PERPANJANGAN-PKL.md bagian 4):
 *
 *   - Jenis surat baru `perpanjangan_pkl` di ENUM surat_sekolah.jenis dan surat_permintaan.jenis.
 *   - Tabel `pkl_perpanjangan`: SATU baris per surat perpanjangan (siswanya = surat_sekolah_siswa) — tanggal selesai lama + asalnya
 *     (sekolah = tercatat di ajuan, siswa = diketik siswa, staf = diketik staf) dan tanggal selesai baru, bertipe DATE supaya bisa
 *     dicari/diurutkan. Status TIDAK disimpan di sini: tanggal baru berlaku bila suratnya berstatus disetujui (dibaca lewat JOIN),
 *     jadi ACC, batal ACC, dan batal surat tidak perlu menyinkronkan apa pun.
 *   - pkl_pengaturan: perpanjang_buka (form siswa), perpanjang_hari_sebelum (bawaan 7 = buka H-7), perpanjang_hari_sesudah
 *     (bawaan 30 = tutup H+30; 0 = tanpa batas).
 *   - Hak: peran yang memegang hak Buat/ACC/Unduh Surat Balasan mendapat hak yang sama untuk Surat Perpanjangan (keputusan user
 *     2026-10-26: "ACC sama dengan Surat Balasan"). Hanya menambah; Admin tetap bebas mengubahnya di Sistem → Hak Akses.
 *
 * Idempoten. Tanpa data pribadi. down() menolak berjalan bila sudah ada surat perpanjangan (data tidak dihapus diam-diam).
 */
class PklPerpanjangan extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    private const JENIS_LAMA = ['izin_asts', 'izin_tka', 'pernyataan_ortu', 'balasan_pkl', 'penarikan_pkl'];
    private const JENIS      = 'perpanjangan_pkl';
    private const AKSI       = ['buat', 'acc', 'unduh'];

    public function up()
    {
        // 1. Jenis surat baru di ENUM (nilai lama tetap, urutan tetap — data lama tidak berubah).
        $semua = array_merge(self::JENIS_LAMA, [self::JENIS]);
        foreach (['surat_sekolah', 'surat_permintaan'] as $t) {
            if ($this->db->tableExists($t) && ! $this->enumPunya($t, self::JENIS)) {
                $this->forge->modifyColumn($t, ['jenis' => ['name' => 'jenis', 'type' => 'ENUM', 'constraint' => $semua, 'null' => false]]);
            }
        }

        // 2. Tanggal perpanjangan per surat.
        if (! $this->db->tableExists('pkl_perpanjangan')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'surat_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'pengajuan_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'selesai_lama' => ['type' => 'DATE'],
                'sumber_lama'  => ['type' => 'VARCHAR', 'constraint' => 8, 'default' => 'sekolah'], // sekolah | siswa | staf
                'selesai_baru' => ['type' => 'DATE'],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('surat_id');
            $this->forge->addKey('pengajuan_id');
            $this->forge->addKey('selesai_baru');
            $this->forge->addForeignKey('surat_id', 'surat_sekolah', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('pengajuan_id', 'pkl_pengajuan', 'id', '', 'CASCADE');
            $this->forge->createTable('pkl_perpanjangan', true, $this->attr);
        }

        // 3. Setelan form siswa.
        $kolom = [];
        if (! $this->db->fieldExists('perpanjang_buka', 'pkl_pengaturan')) {
            $kolom['perpanjang_buka'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1];
        }
        if (! $this->db->fieldExists('perpanjang_hari_sebelum', 'pkl_pengaturan')) {
            $kolom['perpanjang_hari_sebelum'] = ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 7];
        }
        if (! $this->db->fieldExists('perpanjang_hari_sesudah', 'pkl_pengaturan')) {
            $kolom['perpanjang_hari_sesudah'] = ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 30];
        }
        if ($kolom !== []) {
            $this->forge->addColumn('pkl_pengaturan', $kolom);
        }

        // 4. Hak = hak Surat Balasan.
        $diberi = $this->salinHak(true);
        if ($diberi !== []) {
            $this->audit('Hak Surat Perpanjangan PKL disamakan dengan Surat Balasan PKL (migrasi PklPerpanjangan): ' . implode('; ', $diberi));
        }
    }

    public function down()
    {
        if ($this->db->tableExists('surat_sekolah') && $this->enumPunya('surat_sekolah', self::JENIS)
            && $this->db->table('surat_sekolah')->where('jenis', self::JENIS)->countAllResults() > 0) {
            throw new \RuntimeException('Sudah ada Surat Perpanjangan PKL di database. Batalkan/hapus surat itu dulu bila migrasi ini memang mau dibatalkan.');
        }
        if ($this->db->tableExists('surat_permintaan') && $this->enumPunya('surat_permintaan', self::JENIS)) {
            $this->db->table('surat_permintaan')->where('jenis', self::JENIS)->delete();
        }

        $dicabut = $this->salinHak(false);
        if ($dicabut !== []) {
            $this->audit('Hak Surat Perpanjangan PKL dicabut (migrasi PklPerpanjangan dibatalkan): ' . implode('; ', $dicabut));
        }
        foreach (['perpanjang_buka', 'perpanjang_hari_sebelum', 'perpanjang_hari_sesudah'] as $k) {
            if ($this->db->fieldExists($k, 'pkl_pengaturan')) {
                $this->forge->dropColumn('pkl_pengaturan', $k);
            }
        }
        $this->forge->dropTable('pkl_perpanjangan', true);
        foreach (['surat_sekolah', 'surat_permintaan'] as $t) {
            if ($this->db->tableExists($t) && $this->enumPunya($t, self::JENIS)) {
                $this->forge->modifyColumn($t, ['jenis' => ['name' => 'jenis', 'type' => 'ENUM', 'constraint' => self::JENIS_LAMA, 'null' => false]]);
            }
        }
    }

    /** Kolom jenis tabel $tabel sudah mengenal nilai $nilai? */
    private function enumPunya(string $tabel, string $nilai): bool
    {
        $r = $this->db->query('SHOW COLUMNS FROM ' . $this->db->escapeIdentifiers($this->db->prefixTable($tabel)) . " LIKE 'jenis'")->getRowArray();

        return str_contains((string) ($r['Type'] ?? ''), "'" . $nilai . "'");
    }

    /**
     * $beri = true: tiap peran yang memegang surat_{aksi}:balasan_pkl mendapat surat_{aksi}:perpanjangan_pkl. $beri = false: semua
     * kunci perpanjangan dicabut. Isian hak yang rusak dilewati. Kembalian = ringkasan per peran yang berubah.
     *
     * @return list<string>
     */
    private function salinHak(bool $beri): array
    {
        if (! $this->db->tableExists('peran')) {
            return [];
        }
        $catat = [];
        foreach ($this->db->table('peran')->select('kode, hak')->get()->getResultArray() as $r) {
            $hak = json_decode((string) $r['hak'], true);
            if (! is_array($hak)) {
                continue;
            }
            $hak  = array_values(array_map('strval', $hak));
            $baru = $hak;
            $aksi = [];
            foreach (self::AKSI as $a) {
                $k = 'surat_' . $a . ':' . self::JENIS;
                if ($beri && in_array('surat_' . $a . ':balasan_pkl', $hak, true) && ! in_array($k, $hak, true)) {
                    $baru[] = $k;
                    $aksi[] = $a;
                } elseif (! $beri && in_array($k, $hak, true)) {
                    $baru   = array_values(array_diff($baru, [$k]));
                    $aksi[] = $a;
                }
            }
            if ($aksi !== []) {
                $this->db->table('peran')->where('kode', $r['kode'])->update(['hak' => json_encode(array_values($baru)), 'updated_at' => date('Y-m-d H:i:s')]);
                $catat[] = $r['kode'] . ' ' . ($beri ? '+' : '−') . implode('/', $aksi);
            }
        }

        return $catat;
    }

    private function audit(string $deskripsi): void
    {
        if (! $this->db->tableExists('audit_log')) {
            return;
        }
        $this->db->table('audit_log')->insert([
            'admin_id' => null, 'aksi' => 'update', 'tabel' => 'peran', 'record_id' => null,
            'deskripsi' => mb_substr($deskripsi, 0, 255), 'ip_address' => null, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
