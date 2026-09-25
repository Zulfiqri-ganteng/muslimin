<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Absensi: guru piket bergilir, staf TU, pengecualian & data guru ganda.
 *
 *   jadwal_piket          — siapa piket pada hari + shift tertentu (bergilir).
 *   absensi_kerja.shift   — kehadiran kerja berlaku penuh / pagi / siang saja
 *                           (mis. piket pagi saja). Baris lama = 'penuh'.
 *   jabatan.hadir_harian  — penyandang (mis. Staf TU) otomatis diisikan ke
 *                           Kehadiran Kerja tiap hari, seperti jabatan struktural.
 *   guru.ikut_absensi     — 0 = tidak ikut absensi & laporan (mis. ketua
 *                           yayasan / kepala sekolah).
 *   guru.induk_id         — data guru GANDA (dibuat agar jadwal kelas gabungan
 *                           lolos cek bentrok) ditautkan ke data utamanya
 *                           sehingga absensi, pesan WA & rekap dihitung satu orang.
 *
 * Semua perubahan bersifat MENAMBAH. Ditambah satu jabatan bawaan "Staf Tata
 * Usaha" (kode TU) bila belum ada.
 */
class AbsensiPiketTuInduk extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'hari_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'shift'      => ['type' => 'ENUM', 'constraint' => ['pagi', 'siang']],
            'guru_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['hari_id', 'shift', 'guru_id']);
        $this->forge->addForeignKey('hari_id', 'hari', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'CASCADE');
        $this->forge->createTable('jadwal_piket', true, $this->attr);

        if (! $this->db->fieldExists('shift', 'absensi_kerja')) {
            $this->forge->addColumn('absensi_kerja', [
                'shift' => ['type' => 'ENUM', 'constraint' => ['penuh', 'pagi', 'siang'], 'default' => 'penuh', 'after' => 'status'],
            ]);
        }
        if (! $this->db->fieldExists('hadir_harian', 'jabatan')) {
            $this->forge->addColumn('jabatan', [
                'hadir_harian' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'is_struktural'],
            ]);
        }
        if (! $this->db->fieldExists('ikut_absensi', 'guru')) {
            $this->forge->addColumn('guru', [
                'ikut_absensi' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'no_wa'],
            ]);
        }
        if (! $this->db->fieldExists('induk_id', 'guru')) {
            $this->forge->addColumn('guru', [
                'induk_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'ikut_absensi'],
            ]);
        }

        // Jabatan bawaan untuk staf Tata Usaha (hadir harian, bukan struktural).
        $ada = $this->db->table('jabatan')->where('kode', 'TU')->countAllResults();
        if ($ada === 0) {
            $now = date('Y-m-d H:i:s');
            $this->db->table('jabatan')->insert([
                'kode'          => 'TU',
                'nama'          => 'Staf Tata Usaha',
                'kategori'      => 'lainnya',
                'level'         => 6,
                'is_struktural' => 0,
                'hadir_harian'  => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            cache()->delete('opt_jabatan'); // dropdown jabatan ber-cache 6 jam
        }
    }

    public function down()
    {
        foreach (['induk_id', 'ikut_absensi'] as $kolom) {
            if ($this->db->fieldExists($kolom, 'guru')) {
                $this->forge->dropColumn('guru', $kolom);
            }
        }
        if ($this->db->fieldExists('hadir_harian', 'jabatan')) {
            $this->forge->dropColumn('jabatan', 'hadir_harian');
        }
        if ($this->db->fieldExists('shift', 'absensi_kerja')) {
            $this->forge->dropColumn('absensi_kerja', 'shift');
        }
        $this->forge->dropTable('jadwal_piket', true);
    }
}
