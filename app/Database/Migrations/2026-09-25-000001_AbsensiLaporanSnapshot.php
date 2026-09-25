<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Laporan absensi bulanan format sekolah + potongan.
 *
 *   absensi_snapshot              — salinan sesi KBM suatu tanggal saat absensi
 *                                   disimpan. Rekap & laporan memakai salinan ini
 *                                   sehingga perubahan jadwal di kemudian hari
 *                                   TIDAK mengubah hitungan bulan yang sudah lewat
 *                                   (penting untuk gaji). Tanggal tanpa salinan
 *                                   (data lama) tetap dihitung dari jadwal aktif.
 *   settings.absensi_potongan_jp  — potongan per JP (telat/izin/sakit/alpa), Rp.
 *   settings.absensi_transport    — uang transport per hari hadir, Rp (0 = tidak dipakai).
 *
 * Semua perubahan bersifat MENAMBAH.
 */
class AbsensiLaporanSnapshot extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tanggal'   => ['type' => 'DATE'],
            'jadwal_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'guru_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'kelas_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'jam_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'shift'     => ['type' => 'ENUM', 'constraint' => ['pagi', 'siang']],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tanggal');
        $this->forge->addKey(['guru_id', 'tanggal']);
        $this->forge->createTable('absensi_snapshot', true, $this->attr);

        $kolom = [
            'absensi_potongan_jp' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 5000, 'after' => 'wa_template_absensi'],
            'absensi_transport'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0, 'after' => 'absensi_potongan_jp'],
        ];
        foreach ($kolom as $nama => $def) {
            if (! $this->db->fieldExists($nama, 'settings')) {
                $this->forge->addColumn('settings', [$nama => $def]);
            }
        }
        cache()->delete('app_setting'); // baris pengaturan ber-cache 6 jam
    }

    public function down()
    {
        foreach (['absensi_transport', 'absensi_potongan_jp'] as $nama) {
            if ($this->db->fieldExists($nama, 'settings')) {
                $this->forge->dropColumn('settings', $nama);
            }
        }
        $this->forge->dropTable('absensi_snapshot', true);
    }
}
