<?php

namespace App\Database\Migrations;

use App\Libraries\HakPeran;
use CodeIgniter\Database\Migration;

/**
 * Hak akses diatur Admin — tabel `peran` (rancangan: docs/DESAIN-HAK-AKSES.md). Satu baris per peran: label, beranda, boleh
 * aplikasi Android, peta menu (JSON {kunci_menu: lihat|kelola|boleh}) dan daftar hak rinci PKL/Surat (JSON list).
 *
 * Diisi dengan BAWAAN = aturan lama persis: Config\Peran (Admin, Operator Sekolah, Waka Hubin) + hak PKL yang sudah diatur Admin
 * di pkl_pengaturan.hak_peran (diperluas ke hak per jenis surat). Jadi setelah migrasi perilaku sistem SAMA, sampai Admin mengubah
 * centang di Sistem → Hak Akses. Tidak ada data pribadi. Idempoten (baris yang sudah ada tidak ditimpa).
 */
class HakAksesPeran extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        if (! $this->db->tableExists('peran')) {
            $this->forge->addField([
                'kode'       => ['type' => 'VARCHAR', 'constraint' => 20],
                'label'      => ['type' => 'VARCHAR', 'constraint' => 60],
                'ringkas'    => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
                'beranda'    => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'api'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'sistem'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'urut'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'menu'       => ['type' => 'TEXT', 'null' => true],
                'hak'        => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('kode', true);
            $this->forge->createTable('peran', true, $this->attr);
        }

        // Bawaan dihitung dari kode SEBELUM tabel dianggap ada (HakPeran membaca Config\Peran + hak lama).
        HakPeran::lupakan();
        $now = date('Y-m-d H:i:s');
        foreach (HakPeran::bawaan() as $kode => $p) {
            if ($this->db->table('peran')->where('kode', $kode)->countAllResults() > 0) {
                continue;
            }
            $this->db->table('peran')->insert([
                'kode'       => $kode,
                'label'      => $p['label'],
                'ringkas'    => mb_substr((string) $p['ringkas'], 0, 300),
                'beranda'    => $p['beranda'],
                'api'        => ! empty($p['api']) ? 1 : 0,
                'sistem'     => 1,
                'urut'       => (int) $p['urut'],
                'menu'       => json_encode($p['menu'], JSON_FORCE_OBJECT),
                'hak'        => json_encode(array_values($p['hak'])),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        HakPeran::lupakan();
    }

    public function down()
    {
        $this->forge->dropTable('peran', true);
        HakPeran::lupakan();
    }
}
