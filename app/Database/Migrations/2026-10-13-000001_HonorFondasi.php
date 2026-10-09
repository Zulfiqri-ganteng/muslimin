<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Honor Ujian — FONDASI (Fase 1).
 *
 *   1. guru.bukan_pengajar   tanda staf yang bukan pengajar (TU, operator): tetap di Master Guru, tetapi tidak
 *                            dihitung sebagai "guru" di dashboard / beban mengajar. Bawaan 0 → tidak mengubah apa pun.
 *   2. honor_komponen        jenis honor beserta tarifnya (Tunjangan Panitia, Pembuatan Soal, Transport, Pengawas,
 *                            Koreksi, Rapot, dst). Tarif BISA DIUBAH Admin; komponen baru bisa ditambah.
 *   3. honor_panitia_jabatan jabatan → nominal tunjangan panitia bawaan (diisi Admin; boleh diubah per orang nanti).
 *   4. honor_pengaturan      satu baris: nama Ketua panitia & Bendahara untuk tanda tangan rekap (bawaan dokumen baru).
 *
 * Tarif di komponen awal adalah acuan dari rekap honor sekolah (Pembuatan Soal 20.000, Transport 25.000, Pengawas
 * 6.500, Koreksi 1.500, Rapot 20.000, Lembur 100.000). TIDAK ada nama orang / nominal gaji pribadi di migrasi ini
 * (repo publik) — nama penerima diambil dari Master Guru, nominal tunjangan panitia diisi Admin lewat halaman.
 *
 * Idempoten: aman dijalankan ulang. Rancangan: docs/DESAIN-HONOR.md.
 */
class HonorFondasi extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        // ---------- 1. guru.bukan_pengajar ----------
        if (! $this->db->fieldExists('bukan_pengajar', 'guru')) {
            $this->forge->addColumn('guru', [
                'bukan_pengajar' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'null' => false, 'after' => 'ikut_absensi'],
            ]);
        }

        // ---------- 2. honor_komponen ----------
        if (! $this->db->tableExists('honor_komponen')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'kode'       => ['type' => 'VARCHAR', 'constraint' => 30],
                'nama'       => ['type' => 'VARCHAR', 'constraint' => 80],
                // tetap = nominal per orang (mis. tunjangan); satuan = jumlah × tarif
                'tipe'       => ['type' => 'ENUM', 'constraint' => ['tetap', 'satuan'], 'default' => 'satuan'],
                'tarif'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
                'satuan'     => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                // asal angka: manual = diketik; koreksi/rapot/soal = bisa dihitung otomatis dari data sekolah
                'sumber'     => ['type' => 'ENUM', 'constraint' => ['manual', 'koreksi', 'rapot', 'soal'], 'default' => 'manual'],
                // daftar jenis ujian yang memakai komponen ini ("ASAS,ASAT"); NULL = semua jenis
                'berlaku_di' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'aktif'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'urut'       => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 100],
                // 1 = komponen bawaan sistem (kode & sumber terkunci, tidak bisa dihapus, hanya dinonaktifkan)
                'bawaan'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('kode', 'honor_komponen_kode');
            $this->forge->addKey(['aktif', 'urut']);
            $this->forge->createTable('honor_komponen', true, $this->attr);
        }
        if ((int) $this->db->table('honor_komponen')->countAllResults() === 0) {
            $now = date('Y-m-d H:i:s');
            // [kode, nama, tipe, tarif, satuan, sumber, berlaku_di, aktif, urut]
            $awal = [
                ['tunj_struktural', 'Tunjangan Struktural', 'tetap',  0,      null,     'manual',  null,        0, 10],
                ['tunj_walas',      'Tunjangan Wali Kelas', 'tetap',  0,      null,     'manual',  null,        0, 20],
                ['tunj_panitia',    'Tunjangan Panitia',    'tetap',  0,      null,     'manual',  null,        1, 30],
                ['soal',            'Pembuatan Soal',       'satuan', 20000,  'set',    'soal',    null,        1, 40],
                ['transport',       'Transport',            'satuan', 25000,  'hari',   'manual',  null,        1, 50],
                ['pengawas',        'Pengawas',             'satuan', 6500,   'sesi',   'manual',  null,        1, 60],
                ['koreksi',         'Koreksi',              'satuan', 1500,   'lembar', 'koreksi', null,        1, 70],
                ['rapot',           'Rapot',                'satuan', 20000,  'siswa',  'rapot',   'ASAS,ASAT', 1, 80],
                ['lembur',          'Lembur',               'satuan', 100000, 'kali',   'manual',  null,        0, 90],
            ];
            $baris = [];
            foreach ($awal as [$kode, $nama, $tipe, $tarif, $satuan, $sumber, $berlaku, $aktif, $urut]) {
                $baris[] = [
                    'kode' => $kode, 'nama' => $nama, 'tipe' => $tipe, 'tarif' => $tarif, 'satuan' => $satuan,
                    'sumber' => $sumber, 'berlaku_di' => $berlaku, 'aktif' => $aktif, 'urut' => $urut, 'bawaan' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            $this->db->table('honor_komponen')->insertBatch($baris);
        }

        // ---------- 3. honor_panitia_jabatan ----------
        if (! $this->db->tableExists('honor_panitia_jabatan')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'jabatan_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'nominal'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('jabatan_id', 'honor_panitia_satu_per_jabatan');
            $this->forge->addForeignKey('jabatan_id', 'jabatan', 'id', '', 'CASCADE');
            $this->forge->createTable('honor_panitia_jabatan', true, $this->attr);
        }

        // ---------- 4. honor_pengaturan ----------
        if (! $this->db->tableExists('honor_pengaturan')) {
            $this->forge->addField([
                'id'            => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true],
                'ketua_nama'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'bendahara_nama' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('honor_pengaturan', true, $this->attr);
        }
        if ((int) $this->db->table('honor_pengaturan')->countAllResults() === 0) {
            $this->db->table('honor_pengaturan')->insert(['id' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    public function down()
    {
        foreach (['honor_pengaturan', 'honor_panitia_jabatan', 'honor_komponen'] as $tabel) {
            $this->forge->dropTable($tabel, true);
        }
        if ($this->db->fieldExists('bukan_pengajar', 'guru')) {
            $this->forge->dropColumn('guru', 'bukan_pengajar');
        }
    }
}
