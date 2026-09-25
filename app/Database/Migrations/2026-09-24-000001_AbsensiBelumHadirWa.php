<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Laporan kehadiran guru per SHIFT lewat WhatsApp.
 *
 *   absensi_belum              — daftar guru yang BELUM HADIR saat laporan
 *                                dikirim, per tanggal + shift (pagi/siang).
 *                                Status sementara: hilang begitu admin menandai
 *                                "Sudah datang". Yang tersisa dihitung TIDAK
 *                                HADIR di rekap (lihat AbsensiRekap).
 *   guru.no_wa                 — nomor WhatsApp (format 62…) untuk tag asli
 *                                "@62…" di pesan grup.
 *   settings.wa_template_absensi — template pesan WA yang bisa diubah admin;
 *                                NULL = pakai template bawaan (AbsensiWa).
 *
 * Semua perubahan bersifat MENAMBAH — tidak ada kolom/tabel lama yang diubah.
 */
class AbsensiBelumHadirWa extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tanggal'    => ['type' => 'DATE'],
            'shift'      => ['type' => 'ENUM', 'constraint' => ['pagi', 'siang']],
            'guru_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // Satu guru hanya sekali per tanggal + shift.
        $this->forge->addUniqueKey(['tanggal', 'shift', 'guru_id']);
        $this->forge->addKey('tanggal');
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'admins', 'id', '', 'SET NULL');
        $this->forge->createTable('absensi_belum', true, $this->attr);

        if (! $this->db->fieldExists('no_wa', 'guru')) {
            $this->forge->addColumn('guru', [
                'no_wa' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'nama'],
            ]);
        }

        if (! $this->db->fieldExists('wa_template_absensi', 'settings')) {
            $this->forge->addColumn('settings', [
                'wa_template_absensi' => ['type' => 'TEXT', 'null' => true, 'after' => 'absensi_publik'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('wa_template_absensi', 'settings')) {
            $this->forge->dropColumn('settings', 'wa_template_absensi');
        }
        if ($this->db->fieldExists('no_wa', 'guru')) {
            $this->forge->dropColumn('guru', 'no_wa');
        }
        $this->forge->dropTable('absensi_belum', true);
    }
}
