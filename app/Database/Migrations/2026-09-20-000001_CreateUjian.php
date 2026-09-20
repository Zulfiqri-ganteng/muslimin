<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Modul Menu Ujian — pendataan asesmen sumatif (ASTS 1 / ASAS / ASTS 2 / ASAT)
 * beserta ujian susulannya.
 *
 * 4 tabel (urutan dibuat mengikuti ketergantungan foreign key):
 *   1. ujian_periode  — satu baris per jenis ujian per tahun pelajaran
 *   2. ujian_jadwal   — jadwal pelaksanaan (mapel × tingkat × shift × tanggal)
 *   3. ujian_pengawas — penugasan pengawas per jadwal (OPSIONAL, boleh kosong)
 *   4. ujian_susulan  — siswa tidak hadir + pengelolaan ujian susulannya
 *
 * Memakai ulang tabel yang sudah ada: siswa, kelas, jurusan, mata_pelajaran,
 * guru, settings. Peserta ujian TIDAK ditabelkan — diturunkan dari siswa aktif
 * pada tingkat/shift yang bersangkutan.
 *
 * Tahun pelajaran sengaja disimpan sebagai VARCHAR (bukan FK ke tahun_ajaran)
 * karena tabel `tahun_ajaran` KOSONG di produksi; sumber yang dipakai sekolah
 * adalah `settings.academic_year`. Lihat docs/DESAIN-UJIAN.md.
 *
 * Semua InnoDB + utf8mb4, gaya sama dengan CreateUkk.
 */
class CreateUjian extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    /** Kolom timestamp standar. */
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

    public function up()
    {
        // ============================================================
        // 1. ujian_periode
        // ============================================================
        // UNIQUE(jenis, tahun_ajaran) → tiap tahun pelajaran punya satu ASTS 1,
        // satu ASAS, dst. Riwayat tahun sebelumnya tetap utuh.
        $this->forge->addField([
            'id'              => $this->pk(),
            'jenis'           => ['type' => 'ENUM', 'constraint' => ['ASTS1', 'ASAS', 'ASTS2', 'ASAT']],
            'tahun_ajaran'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'semester'        => ['type' => 'ENUM', 'constraint' => ['Ganjil', 'Genap']],
            'nama'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'tanggal_mulai'   => ['type' => 'DATE', 'null' => true],
            'tanggal_selesai' => ['type' => 'DATE', 'null' => true],
            'susulan_mulai'   => ['type' => 'DATE', 'null' => true],
            'susulan_selesai' => ['type' => 'DATE', 'null' => true],
            'status'          => ['type' => 'ENUM', 'constraint' => ['draft', 'berjalan', 'selesai'], 'default' => 'draft'],
            'keterangan'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['jenis', 'tahun_ajaran']);
        $this->forge->addKey('tahun_ajaran');
        $this->forge->createTable('ujian_periode', true, $this->attr);

        // ============================================================
        // 2. ujian_jadwal
        // ============================================================
        // shift WAJIB ada: kelas X semuanya pagi, XI semuanya siang, tapi XII
        // terbelah 5 pagi + 6 siang — jadwal per tingkat saja tidak cukup.
        // jurusan_id dipakai untuk mapel kejuruan yang cuma diikuti 1 jurusan.
        $this->forge->addField([
            'id'          => $this->pk(),
            'periode_id'  => $this->fk(),
            'mapel_id'    => $this->fk(true),
            'tingkat'     => ['type' => 'ENUM', 'constraint' => ['X', 'XI', 'XII']],
            'jurusan_id'  => $this->fk(true),
            'shift'       => ['type' => 'ENUM', 'constraint' => ['pagi', 'siang', 'semua'], 'default' => 'semua'],
            'tanggal'     => ['type' => 'DATE'],
            'jam_mulai'   => ['type' => 'TIME', 'null' => true],
            'jam_selesai' => ['type' => 'TIME', 'null' => true],
            'ruang'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'keterangan'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addKey('periode_id');
        $this->forge->addKey('mapel_id');
        $this->forge->addKey('jurusan_id');
        $this->forge->addKey('tanggal');
        $this->forge->addKey(['periode_id', 'tingkat']);
        $this->forge->addForeignKey('periode_id', 'ujian_periode', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('mapel_id', 'mata_pelajaran', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('jurusan_id', 'jurusan', 'id', '', 'SET NULL');
        $this->forge->createTable('ujian_jadwal', true, $this->attr);

        // ============================================================
        // 3. ujian_pengawas  (OPSIONAL — jadwal sah tanpa satu pun pengawas)
        // ============================================================
        // Hard delete, pola pivot jadwal_ukk_penguji: tidak ada riwayat yang
        // berdiri sendiri di sini.
        $this->forge->addField([
            'id'         => $this->pk(),
            'jadwal_id'  => $this->fk(),
            'guru_id'    => $this->fk(true),
            'ruang'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'peran'      => ['type' => 'ENUM', 'constraint' => ['pengawas', 'cadangan'], 'default' => 'pengawas'],
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('jadwal_id');
        $this->forge->addKey('guru_id');
        $this->forge->addForeignKey('jadwal_id', 'ujian_jadwal', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'SET NULL');
        $this->forge->createTable('ujian_pengawas', true, $this->attr);

        // ============================================================
        // 4. ujian_susulan  (INTI: ketidakhadiran + pengelolaan susulan)
        // ============================================================
        // jadwal_id SET NULL (bukan CASCADE) + mapel_id/tanggal_ujian
        // didenormalisasi, supaya baris ketidakhadiran tetap bermakna kalau
        // jadwalnya dihapus.
        // UNIQUE(siswa_id, jadwal_id): satu siswa tak bisa dobel di satu sesi.
        // Catatan: UNIQUE tetap berlaku pada baris soft-deleted, jadi saat
        // mencatat ulang harus cek withDeleted() → pulihkan, bukan insert baru.
        $this->forge->addField([
            'id'                  => $this->pk(),
            'periode_id'          => $this->fk(),
            'jadwal_id'           => $this->fk(true),
            'siswa_id'            => $this->fk(),
            'mapel_id'            => $this->fk(true),
            'tanggal_ujian'       => ['type' => 'DATE', 'null' => true],
            'alasan'              => ['type' => 'ENUM', 'constraint' => ['sakit', 'izin', 'alpa', 'lainnya'], 'default' => 'alpa'],
            'keterangan'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'              => ['type' => 'ENUM', 'constraint' => ['belum', 'dijadwalkan', 'selesai', 'batal'], 'default' => 'belum'],
            'tanggal_susulan'     => ['type' => 'DATE', 'null' => true],
            'jam_susulan'         => ['type' => 'TIME', 'null' => true],
            'ruang_susulan'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'pengawas_guru_id'    => $this->fk(true),
            'tanggal_pelaksanaan' => ['type' => 'DATE', 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['siswa_id', 'jadwal_id']);
        $this->forge->addKey('periode_id');
        $this->forge->addKey('jadwal_id');
        $this->forge->addKey('mapel_id');
        $this->forge->addKey('status');
        $this->forge->addKey('pengawas_guru_id');
        $this->forge->addForeignKey('periode_id', 'ujian_periode', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('jadwal_id', 'ujian_jadwal', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('siswa_id', 'siswa', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('mapel_id', 'mata_pelajaran', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('pengawas_guru_id', 'guru', 'id', '', 'SET NULL');
        $this->forge->createTable('ujian_susulan', true, $this->attr);
    }

    public function down()
    {
        // Urutan drop = kebalikan create agar foreign key tidak menahan.
        foreach (['ujian_susulan', 'ujian_pengawas', 'ujian_jadwal', 'ujian_periode'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
