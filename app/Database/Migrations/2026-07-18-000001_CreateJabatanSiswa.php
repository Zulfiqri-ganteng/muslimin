<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Jabatan guru (fleksibel + berelasi) & data siswa.
 *
 * 3 tabel:
 *  1. jabatan       — master jabatan yang dibuat bebas oleh admin. Berelasi ke
 *                     dirinya sendiri (parent_id → hierarki struktural) dan ke
 *                     jurusan (mis. "Ketua Program TKJ" melekat pada jurusan TKJ).
 *  2. guru_jabatan  — pivot M:N; satu guru boleh menyandang beberapa jabatan
 *                     ("Guru MTK" + "Wakasek Kurikulum"), salah satunya utama.
 *  3. siswa         — data siswa lengkap, tingkat & jurusan DITURUNKAN dari
 *                     kelas (tidak diduplikasi) agar tak pernah tidak sinkron.
 *
 * Semua InnoDB + utf8mb4, mengikuti gaya migrasi penjadwalan yang sudah ada.
 */
class CreateJabatanSiswa extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        $ts = static fn () => [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        // ============================================================
        // 1. jabatan
        // ============================================================
        $this->forge->addField([
            'id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'kode' => ['type' => 'VARCHAR', 'constraint' => 30],
            'nama' => ['type' => 'VARCHAR', 'constraint' => 150],
            // Pengelompokan bebas untuk memfilter daftar & rekap.
            'kategori' => [
                'type'       => 'ENUM',
                'constraint' => ['struktural', 'kurikulum', 'kesiswaan', 'wali_kelas', 'mapel', 'pembina', 'lainnya'],
                'default'    => 'lainnya',
            ],
            // Relasi hierarki: Kepala Sekolah → Wakasek → Ketua Program.
            'parent_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            // Relasi jurusan: dipakai jabatan yang melekat pada satu jurusan.
            'jurusan_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            // Urutan tampil / kedalaman hierarki (1 = paling tinggi).
            'level' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 5],
            // Penanda "wajib hadir walau tidak punya jadwal KBM" — dipakai panel
            // Kehadiran Kerja agar wakil kepala dsb. otomatis muncul.
            'is_struktural' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'keterangan'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ] + $ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->addKey('nama');
        $this->forge->addKey('is_struktural');
        $this->forge->addKey('parent_id');
        $this->forge->addForeignKey('jurusan_id', 'jurusan', 'id', '', 'SET NULL');
        $this->forge->createTable('jabatan', true, $this->attr);

        // FK ke diri sendiri ditambahkan SETELAH tabel ada agar aman di semua
        // versi MariaDB/MySQL shared hosting.
        $this->db->query(
            'ALTER TABLE `jabatan` ADD CONSTRAINT `jabatan_parent_id_foreign`
             FOREIGN KEY (`parent_id`) REFERENCES `jabatan`(`id`) ON DELETE SET NULL'
        );

        // ============================================================
        // 2. guru_jabatan (pivot)
        // ============================================================
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'guru_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'jabatan_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            // Jabatan utama = yang ditampilkan ringkas di daftar & rekap.
            'is_utama'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'tmt'          => ['type' => 'DATE', 'null' => true],
            'tmt_selesai'  => ['type' => 'DATE', 'null' => true],
            'keterangan'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['guru_id', 'jabatan_id']);
        $this->forge->addKey('jabatan_id');
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('jabatan_id', 'jabatan', 'id', '', 'CASCADE');
        $this->forge->createTable('guru_jabatan', true, $this->attr);

        // ============================================================
        // 3. siswa
        // ============================================================
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nis'           => ['type' => 'VARCHAR', 'constraint' => 30],
            'nisn'          => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'nama'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'jenis_kelamin' => ['type' => 'ENUM', 'constraint' => ['L', 'P'], 'null' => true],
            'tempat_lahir'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'tanggal_lahir' => ['type' => 'DATE', 'null' => true],
            'agama'         => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
            'alamat'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'no_hp'         => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
            'nama_wali'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'no_hp_wali'    => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
            // Tingkat & jurusan diturunkan dari kelas — sengaja tidak disimpan ulang.
            'kelas_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'tahun_masuk' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'status'      => [
                'type'       => 'ENUM',
                'constraint' => ['aktif', 'lulus', 'pindah', 'keluar'],
                'default'    => 'aktif',
            ],
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nis');
        $this->forge->addUniqueKey('nisn'); // NULL boleh berulang di MySQL
        $this->forge->addKey('nama');
        $this->forge->addKey('kelas_id');
        $this->forge->addKey('status');
        $this->forge->addKey('tahun_masuk');
        // Statistik publik selalu memfilter status lalu mengelompokkan kelas.
        $this->forge->addKey(['status', 'kelas_id']);
        $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'SET NULL');
        $this->forge->createTable('siswa', true, $this->attr);

        $this->seedJabatan();
    }

    /**
     * Jabatan awal yang lazim dipakai sekolah. Admin tetap bebas menambah,
     * mengubah, atau menghapusnya lewat Master Jabatan.
     */
    private function seedJabatan(): void
    {
        $tabel = $this->db->table('jabatan');
        if ($tabel->countAllResults() > 0) {
            return; // jangan timpa data yang sudah ada
        }

        $now = date('Y-m-d H:i:s');
        $baris = static fn (array $d) => $d + [
            'kategori'      => 'lainnya',
            'parent_id'     => null,
            'jurusan_id'    => null,
            'level'         => 5,
            'is_struktural' => 0,
            'keterangan'    => null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];

        // Level 1 — kepala sekolah (induk seluruh jabatan struktural).
        $tabel->insert($baris([
            'kode' => 'KS', 'nama' => 'Kepala Sekolah',
            'kategori' => 'struktural', 'level' => 1, 'is_struktural' => 1,
        ]));
        $kepalaId = (int) $this->db->insertID();

        // Level 2 — wakil kepala sekolah, semua struktural (masuk walau tanpa jadwal).
        foreach ([
            ['WK-KUR', 'Wakil Kepala Sekolah Bidang Kurikulum', 'kurikulum'],
            ['WK-SIS', 'Wakil Kepala Sekolah Bidang Kesiswaan', 'kesiswaan'],
            ['WK-SAR', 'Wakil Kepala Sekolah Bidang Sarana Prasarana', 'struktural'],
            ['WK-HUM', 'Wakil Kepala Sekolah Bidang Hubungan Masyarakat', 'struktural'],
        ] as [$kode, $nama, $kategori]) {
            $tabel->insert($baris([
                'kode' => $kode, 'nama' => $nama, 'kategori' => $kategori,
                'parent_id' => $kepalaId, 'level' => 2, 'is_struktural' => 1,
            ]));
        }

        // Level 3 — ketua program keahlian (nanti bisa dikaitkan ke jurusan).
        $tabel->insert($baris([
            'kode' => 'KAPROG', 'nama' => 'Ketua Program Keahlian',
            'kategori' => 'struktural', 'parent_id' => $kepalaId,
            'level' => 3, 'is_struktural' => 1,
        ]));

        // Jabatan non-struktural yang disandang mayoritas guru.
        $tabel->insert($baris([
            'kode' => 'WALI', 'nama' => 'Wali Kelas', 'kategori' => 'wali_kelas', 'level' => 4,
        ]));
        $tabel->insert($baris([
            'kode' => 'GMP', 'nama' => 'Guru Mata Pelajaran', 'kategori' => 'mapel', 'level' => 5,
        ]));
    }

    public function down()
    {
        // Lepas FK ke diri sendiri dulu agar dropTable tidak tertahan.
        $this->db->query('ALTER TABLE `jabatan` DROP FOREIGN KEY `jabatan_parent_id_foreign`');

        foreach (['siswa', 'guru_jabatan', 'jabatan'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
