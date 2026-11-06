<?php

namespace App\Database\Migrations;

use App\Libraries\HakPeran;
use CodeIgniter\Database\Migration;

/**
 * Fitur WALI KELAS (2026-11-07, tahap W1):
 *   - admins.guru_id (NULL, UNIQUE): akun staf ↔ data guru. Akun yang gurunya tercatat sebagai wali (kelas.wali_kelas_id) mendapat
 *     menu "Kelas Saya" — SEBAGAI TAMBAHAN di atas perannya (mis. Waka Hubin yang juga wali kelas tetap Waka Hubin);
 *   - peran "Wali Kelas" (bukan peran sistem; bisa diubah Admin di Sistem → Hak Akses) untuk guru yang tidak punya peran lain:
 *     beranda admin/kelas-saya, boleh aplikasi Android, tanpa menu lain (menu Kelas Saya = umum, dibuka sesuai data wali).
 * Idempoten (aman diulang).
 */
class WaliKelas extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('guru_id', 'admins')) {
            $this->forge->addColumn('admins', ['guru_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'role']]);
            $this->db->query('ALTER TABLE admins ADD UNIQUE KEY admins_guru_id_unique (guru_id)');
        }

        if ($this->db->tableExists('peran')
            && $this->db->table('peran')->where("kode = 'wali_kelas' OR LOWER(label) = 'wali kelas'", null, false)->countAllResults() === 0) {
            $now  = date('Y-m-d H:i:s');
            $urut = (int) ($this->db->table('peran')->selectMax('urut', 'm')->get()->getRowArray()['m'] ?? 0) + 1;
            $this->db->table('peran')->insert([
                'kode'       => 'wali_kelas',
                'label'      => 'Wali Kelas',
                'ringkas'    => 'Guru wali kelas: membuka menu Kelas Saya (siswa kelasnya, biodata, PKL, keterlambatan, surat izin; hanya melihat + kirim WA orang tua). Akun dihubungkan ke data guru di Kelola Akun. Juga di aplikasi Android.',
                'beranda'    => 'admin/kelas-saya',
                'api'        => 1,
                'sistem'     => 0,
                'urut'       => $urut,
                'menu'       => json_encode([], JSON_FORCE_OBJECT),
                'hak'        => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            HakPeran::lupakan();
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('guru_id', 'admins')) {
            $this->db->query('ALTER TABLE admins DROP INDEX admins_guru_id_unique');
            $this->forge->dropColumn('admins', 'guru_id');
        }
        if ($this->db->tableExists('peran') && $this->db->table('admins')->where('role', 'wali_kelas')->countAllResults() === 0) {
            $this->db->table('peran')->where('kode', 'wali_kelas')->where('sistem', 0)->delete();
            HakPeran::lupakan();
        }
    }
}
