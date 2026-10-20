<?php

namespace App\Database\Migrations;

use App\Libraries\Supervisi;
use CodeIgniter\Database\Migration;

/**
 * Supervisi akademik guru (menu KURIKULUM → Supervisi Guru; docs/DESAIN-SUPERVISI.md):
 *   - supervisi_instrumen   — lembar penilaian (Perencanaan, Pelaksanaan, Penilaian; bisa diubah Admin);
 *   - supervisi_butir       — butir tiap instrumen, berkelompok aspek;
 *   - supervisi_jadwal      — satu kunjungan supervisi (guru, mapel/kelas, supervisor, tanggal, instrumen JSON); nama-nama DISALIN;
 *   - supervisi_nilai       — hasil satu instrumen pada satu kunjungan (skor total, nilai, predikat, temuan, tindak lanjut);
 *   - supervisi_nilai_butir — skor per butir + SALINAN aspek & uraian (instrumen yang diubah kemudian tidak mengubah hasil lama).
 * Instrumen bawaan + butirnya dipasang bila tabel instrumen masih kosong. Idempoten.
 */
class SupervisiGuru extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        $id  = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
        $fk  = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true];
        $wkt = ['created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]];

        if (! $this->db->tableExists('supervisi_instrumen')) {
            $this->forge->addField([
                'id'         => $id,
                'kode'       => ['type' => 'VARCHAR', 'constraint' => 40],
                'nama'       => ['type' => 'VARCHAR', 'constraint' => 150],
                'jenis'      => ['type' => 'ENUM', 'constraint' => ['perencanaan', 'pelaksanaan', 'penilaian', 'lainnya'], 'default' => 'lainnya'],
                'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'aktif'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'urut'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            ] + $wkt);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('kode');
            $this->forge->createTable('supervisi_instrumen', true, $this->attr);
        }

        if (! $this->db->tableExists('supervisi_butir')) {
            $this->forge->addField([
                'id'           => $id,
                'instrumen_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'aspek'        => ['type' => 'VARCHAR', 'constraint' => 150],
                'uraian'       => ['type' => 'VARCHAR', 'constraint' => 500],
                'urut'         => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'aktif'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ] + $wkt);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['instrumen_id', 'urut']);
            $this->forge->addForeignKey('instrumen_id', 'supervisi_instrumen', 'id', '', 'CASCADE');
            $this->forge->createTable('supervisi_butir', true, $this->attr);
        }

        if (! $this->db->tableExists('supervisi_jadwal')) {
            $this->forge->addField([
                'id'                 => $id,
                'tahun_ajaran'       => ['type' => 'VARCHAR', 'constraint' => 20],
                'semester'           => ['type' => 'ENUM', 'constraint' => ['ganjil', 'genap']],
                'tanggal'            => ['type' => 'DATE'],
                'jam'                => ['type' => 'TIME', 'null' => true],
                'guru_id'            => $fk,
                'nama_guru'          => ['type' => 'VARCHAR', 'constraint' => 150],
                'nip_guru'           => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'mapel_id'           => $fk,
                'nama_mapel'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'kelas_id'           => $fk,
                'nama_kelas'         => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'supervisor_id'      => $fk,
                'nama_supervisor'    => ['type' => 'VARCHAR', 'constraint' => 150],
                'jabatan_supervisor' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'instrumen'          => ['type' => 'TEXT', 'null' => true], // JSON daftar id instrumen
                'status'             => ['type' => 'ENUM', 'constraint' => ['dijadwalkan', 'selesai', 'batal'], 'default' => 'dijadwalkan'],
                'catatan'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'nilai_akhir'        => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
                'predikat'           => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'dicatat_oleh_id'    => $fk,
                'dicatat_oleh'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            ] + $wkt);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['tahun_ajaran', 'semester']);
            $this->forge->addKey('guru_id');
            $this->forge->addKey('tanggal');
            $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'SET NULL');
            $this->forge->addForeignKey('mapel_id', 'mata_pelajaran', 'id', '', 'SET NULL');
            $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'SET NULL');
            $this->forge->addForeignKey('supervisor_id', 'guru', 'id', '', 'SET NULL');
            $this->forge->createTable('supervisi_jadwal', true, $this->attr);
        }

        if (! $this->db->tableExists('supervisi_nilai')) {
            $this->forge->addField([
                'id'             => $id,
                'jadwal_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'instrumen_id'   => $fk,
                'nama_instrumen' => ['type' => 'VARCHAR', 'constraint' => 150],
                'skor_total'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'skor_maks'      => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'nilai'          => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
                'predikat'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'kekuatan'       => ['type' => 'TEXT', 'null' => true],
                'kelemahan'      => ['type' => 'TEXT', 'null' => true],
                'rekomendasi'    => ['type' => 'TEXT', 'null' => true],
                'tindak_lanjut'  => ['type' => 'TEXT', 'null' => true], // JSON daftar kode tindak lanjut
                'dinilai_at'     => ['type' => 'DATETIME', 'null' => true],
                'dinilai_oleh'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            ] + $wkt);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['jadwal_id', 'instrumen_id']);
            $this->forge->addForeignKey('jadwal_id', 'supervisi_jadwal', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('instrumen_id', 'supervisi_instrumen', 'id', '', 'SET NULL');
            $this->forge->createTable('supervisi_nilai', true, $this->attr);
        }

        if (! $this->db->tableExists('supervisi_nilai_butir')) {
            $this->forge->addField([
                'id'       => $id,
                'nilai_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'butir_id' => $fk,
                'aspek'    => ['type' => 'VARCHAR', 'constraint' => 150],
                'uraian'   => ['type' => 'VARCHAR', 'constraint' => 500],
                'urut'     => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'skor'     => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true], // 1–4; null = tidak dinilai
                'catatan'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('nilai_id');
            $this->forge->addForeignKey('nilai_id', 'supervisi_nilai', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('butir_id', 'supervisi_butir', 'id', '', 'SET NULL');
            $this->forge->createTable('supervisi_nilai_butir', true, $this->attr);
        }

        if ($this->db->table('supervisi_instrumen')->countAllResults() === 0) {
            Supervisi::isiBawaan($this->db);
        }
    }

    public function down()
    {
        foreach (['supervisi_nilai_butir', 'supervisi_nilai', 'supervisi_jadwal', 'supervisi_butir', 'supervisi_instrumen'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
