<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Modul Sistem UKK / Uji Kompetensi Keahlian.
 *
 * 9 tabel (urutan dibuat mengikuti ketergantungan foreign key):
 *   1. tempat_uji         — lokasi pelaksanaan (opsional →lab)
 *   2. penguji_eksternal  — penguji dari DUDI/industri (bukan guru)
 *   3. paket_soal_ukk     — paket soal + kisi-kisi/jobsheet (file) + bobot nilai
 *   4. jadwal_ukk         — jadwal pelaksanaan (per paket soal + tempat uji)
 *   5. jadwal_ukk_penguji — penugasan penguji (internal →guru / eksternal) per jadwal
 *   6. peserta_ukk        — pendaftaran peserta (siswa + paket soal + jadwal)
 *   7. nilai_ukk          — penilaian per peserta per penguji (komponen berbobot)
 *   8. berita_acara_ukk   — berita acara pelaksanaan per jadwal
 *   9. sertifikat_ukk     — sertifikat kelulusan per peserta (1:1)
 *
 * Memakai ulang tabel yang sudah ada: siswa, guru, jurusan, tahun_ajaran, lab.
 * Semua InnoDB + utf8mb4, gaya sama dengan CreateLabInventaris.
 */
class CreateUkk extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    /** Kolom timestamp standar (dipakai tabel master). */
    private function ts(): array
    {
        return [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];
    }

    private function pk(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
    }

    /** Kolom INT unsigned untuk foreign key. */
    private function fk(bool $null = false): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => $null];
    }

    /** Kolom skor 0-100. */
    private function skor(): array
    {
        return ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true];
    }

    public function up()
    {
        // ============================================================
        // 1. tempat_uji
        // ============================================================
        $this->forge->addField([
            'id'         => $this->pk(),
            'kode'       => ['type' => 'VARCHAR', 'constraint' => 30],
            'nama'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'alamat'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'kapasitas'  => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'lab_id'     => $this->fk(true),
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->addKey('nama');
        $this->forge->addForeignKey('lab_id', 'lab', 'id', '', 'SET NULL');
        $this->forge->createTable('tempat_uji', true, $this->attr);

        // ============================================================
        // 2. penguji_eksternal
        // ============================================================
        $this->forge->addField([
            'id'         => $this->pk(),
            'kode'       => ['type' => 'VARCHAR', 'constraint' => 30],
            'nama'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'instansi'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'jabatan'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'no_hp'      => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->addKey('nama');
        $this->forge->createTable('penguji_eksternal', true, $this->attr);

        // ============================================================
        // 3. paket_soal_ukk
        // ============================================================
        $this->forge->addField([
            'id'              => $this->pk(),
            'kode'            => ['type' => 'VARCHAR', 'constraint' => 30],
            'nama'            => ['type' => 'VARCHAR', 'constraint' => 150],
            'jurusan_id'      => $this->fk(true),
            'tahun_ajaran_id' => $this->fk(true),
            'deskripsi'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'kisi_kisi_file'  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'jobsheet_file'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'bobot_persiapan' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 10],
            'bobot_proses'    => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 30],
            'bobot_hasil'     => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 40],
            'bobot_sikap'     => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 10],
            'bobot_waktu'     => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 10],
            'kkm'             => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 70],
            'keterangan'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->addKey('nama');
        $this->forge->addKey('jurusan_id');
        $this->forge->addForeignKey('jurusan_id', 'jurusan', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', '', 'SET NULL');
        $this->forge->createTable('paket_soal_ukk', true, $this->attr);

        // ============================================================
        // 4. jadwal_ukk
        // ============================================================
        $this->forge->addField([
            'id'              => $this->pk(),
            'paket_soal_id'   => $this->fk(),
            'tempat_uji_id'   => $this->fk(true),
            'tahun_ajaran_id' => $this->fk(true),
            'tanggal_mulai'   => ['type' => 'DATE'],
            'tanggal_selesai' => ['type' => 'DATE', 'null' => true],
            'sesi'            => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'keterangan'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addKey('paket_soal_id');
        $this->forge->addKey('tempat_uji_id');
        $this->forge->addKey('tanggal_mulai');
        $this->forge->addForeignKey('paket_soal_id', 'paket_soal_ukk', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('tempat_uji_id', 'tempat_uji', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', '', 'SET NULL');
        $this->forge->createTable('jadwal_ukk', true, $this->attr);

        // ============================================================
        // 5. jadwal_ukk_penguji (pivot penugasan, hard delete)
        // ============================================================
        $this->forge->addField([
            'id'                   => $this->pk(),
            'jadwal_ukk_id'        => $this->fk(),
            'tipe'                 => ['type' => 'ENUM', 'constraint' => ['internal', 'eksternal']],
            'guru_id'              => $this->fk(true),
            'penguji_eksternal_id' => $this->fk(true),
            'peran'                => [
                'type'       => 'ENUM',
                'constraint' => ['ketua', 'anggota'],
                'default'    => 'anggota',
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('jadwal_ukk_id');
        $this->forge->addForeignKey('jadwal_ukk_id', 'jadwal_ukk', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('penguji_eksternal_id', 'penguji_eksternal', 'id', '', 'SET NULL');
        $this->forge->createTable('jadwal_ukk_penguji', true, $this->attr);

        // ============================================================
        // 6. peserta_ukk (pendaftaran)
        // ============================================================
        $this->forge->addField([
            'id'              => $this->pk(),
            'siswa_id'        => $this->fk(),
            'paket_soal_id'   => $this->fk(),
            'jadwal_ukk_id'   => $this->fk(true),
            'tahun_ajaran_id' => $this->fk(true),
            'no_peserta'      => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'status'          => [
                'type'       => 'ENUM',
                'constraint' => ['terdaftar', 'hadir', 'tidak_hadir', 'lulus', 'tidak_lulus'],
                'default'    => 'terdaftar',
            ],
            'nilai_akhir' => $this->skor(),
            'predikat'    => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'keterangan'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['siswa_id', 'paket_soal_id']);
        $this->forge->addKey('jadwal_ukk_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('siswa_id', 'siswa', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('paket_soal_id', 'paket_soal_ukk', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('jadwal_ukk_id', 'jadwal_ukk', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('tahun_ajaran_id', 'tahun_ajaran', 'id', '', 'SET NULL');
        $this->forge->createTable('peserta_ukk', true, $this->attr);

        // ============================================================
        // 7. nilai_ukk (penilaian per peserta per penguji)
        // ============================================================
        $this->forge->addField([
            'id'                   => $this->pk(),
            'peserta_ukk_id'       => $this->fk(),
            'tipe_penguji'         => ['type' => 'ENUM', 'constraint' => ['internal', 'eksternal']],
            'guru_id'              => $this->fk(true),
            'penguji_eksternal_id' => $this->fk(true),
            'persiapan_skor'       => $this->skor(),
            'proses_skor'          => $this->skor(),
            'hasil_skor'           => $this->skor(),
            'sikap_skor'           => $this->skor(),
            'waktu_skor'           => $this->skor(),
            'nilai_akhir'          => $this->skor(),
            'tanggal_nilai'        => ['type' => 'DATE', 'null' => true],
            'keterangan'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addKey('peserta_ukk_id');
        $this->forge->addForeignKey('peserta_ukk_id', 'peserta_ukk', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('penguji_eksternal_id', 'penguji_eksternal', 'id', '', 'SET NULL');
        $this->forge->createTable('nilai_ukk', true, $this->attr);

        // ============================================================
        // 8. berita_acara_ukk
        // ============================================================
        $this->forge->addField([
            'id'            => $this->pk(),
            'jadwal_ukk_id' => $this->fk(),
            'nomor_ba'      => ['type' => 'VARCHAR', 'constraint' => 60],
            'tanggal'       => ['type' => 'DATE'],
            'catatan'       => ['type' => 'TEXT', 'null' => true],
            'keterangan'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nomor_ba');
        $this->forge->addKey('jadwal_ukk_id');
        $this->forge->addForeignKey('jadwal_ukk_id', 'jadwal_ukk', 'id', '', 'CASCADE');
        $this->forge->createTable('berita_acara_ukk', true, $this->attr);

        // ============================================================
        // 9. sertifikat_ukk (1:1 dengan peserta_ukk)
        // ============================================================
        $this->forge->addField([
            'id'                => $this->pk(),
            'peserta_ukk_id'    => $this->fk(),
            'nomor_sertifikat'  => ['type' => 'VARCHAR', 'constraint' => 60],
            'tanggal_terbit'    => ['type' => 'DATE'],
            'keterangan'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('peserta_ukk_id');
        $this->forge->addUniqueKey('nomor_sertifikat');
        $this->forge->addForeignKey('peserta_ukk_id', 'peserta_ukk', 'id', '', 'CASCADE');
        $this->forge->createTable('sertifikat_ukk', true, $this->attr);
    }

    public function down()
    {
        // Urutan drop = kebalikan create agar foreign key tidak menahan.
        foreach ([
            'sertifikat_ukk', 'berita_acara_ukk', 'nilai_ukk', 'peserta_ukk',
            'jadwal_ukk_penguji', 'jadwal_ukk', 'paket_soal_ukk',
            'penguji_eksternal', 'tempat_uji',
        ] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
