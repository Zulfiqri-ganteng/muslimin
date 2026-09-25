<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Modul Isian Biodata Siswa — siswa mengisi biodatanya sendiri lewat tautan
 * publik (subdomain datasiswa.kangmuslim.com) tanpa login.
 *
 *   1. siswa          — DITAMBAH kolom biodata sesuai format buku induk
 *                       (alamat terstruktur, orang tua, wali, sekolah asal).
 *                       Kolom lama dipakai ulang: nama_wali & no_hp_wali kini
 *                       berarti WALI SISWA (bukan orang tua) — di produksi
 *                       keduanya masih kosong sehingga aman diberi arti baru.
 *   2. biodata_isian  — KOTAK MASUK. Isian siswa tidak langsung menimpa
 *                       Master Siswa; admin meninjau (lama vs baru) lalu
 *                       menyetujui. UNIQUE(siswa_id) = satu siswa satu isian,
 *                       jadi data ganda mustahil walau tombol kirim ditekan
 *                       dua kali dari dua HP berbeda.
 *   3. settings       — saklar buka/tutup pengisian + batas waktu otomatis.
 *
 * Idempoten: aman dijalankan ulang bila sempat gagal di tengah jalan.
 * Lihat docs/DESAIN-BIODATA.md.
 */
class CreateBiodataSiswa extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    /** Index untuk filter "biodata lengkap / belum" di Master Siswa. */
    private const INDEX_BIODATA = 'siswa_biodata_at';

    /**
     * Kolom baru tabel siswa, URUT sesuai penambahan — tiap 'after' merujuk
     * kolom lama atau kolom yang sudah ditambahkan lebih dulu di daftar ini.
     *
     * @return array<string, array<string, mixed>>
     */
    private function kolomSiswa(): array
    {
        $teks = static fn (int $n, string $after) => ['type' => 'VARCHAR', 'constraint' => $n, 'null' => true, 'after' => $after];

        return [
            // Data diri
            'status_keluarga' => $teks(20, 'agama'),
            'anak_ke'         => ['type' => 'TINYINT', 'unsigned' => true, 'null' => true, 'after' => 'status_keluarga'],
            // Alamat siswa (kolom `alamat` lama = nama jalan/perumahan)
            'rt'        => $teks(5, 'alamat'),
            'rw'        => $teks(5, 'rt'),
            'kelurahan' => $teks(100, 'rw'),
            'kecamatan' => $teks(100, 'kelurahan'),
            'kota'      => $teks(100, 'kecamatan'),
            // Riwayat masuk — kelas SAAT DITERIMA, bukan kelas sekarang (kelas_id)
            'sekolah_asal'     => $teks(150, 'no_hp'),
            'diterima_kelas'   => $teks(50, 'sekolah_asal'),
            'diterima_tanggal' => ['type' => 'DATE', 'null' => true, 'after' => 'diterima_kelas'],
            // Orang tua
            'nama_ayah'      => $teks(150, 'diterima_tanggal'),
            'nama_ibu'       => $teks(150, 'nama_ayah'),
            'pekerjaan_ayah' => $teks(100, 'nama_ibu'),
            'pekerjaan_ibu'  => $teks(100, 'pekerjaan_ayah'),
            'ortu_alamat'    => $teks(255, 'pekerjaan_ibu'),
            'ortu_rt'        => $teks(5, 'ortu_alamat'),
            'ortu_rw'        => $teks(5, 'ortu_rt'),
            'ortu_kelurahan' => $teks(100, 'ortu_rw'),
            'ortu_kecamatan' => $teks(100, 'ortu_kelurahan'),
            'ortu_kota'      => $teks(100, 'ortu_kecamatan'),
            // Bisa dua nomor sekaligus, mis. "0813xxxx / 0852xxxx".
            'ortu_telepon' => $teks(60, 'ortu_kota'),
            // Wali (nama_wali & no_hp_wali sudah ada)
            'alamat_wali'    => $teks(255, 'nama_wali'),
            'pekerjaan_wali' => $teks(100, 'no_hp_wali'),
            // Kapan biodata terakhir disahkan dari isian siswa — penanda "lengkap".
            'biodata_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'keterangan'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function kolomSettings(): array
    {
        return [
            // Default TUTUP: admin sendiri yang membuka saat tautan siap dibagikan.
            'biodata_open' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            // Batas waktu opsional; lewat waktu ini form otomatis tertutup.
            'biodata_tutup' => ['type' => 'DATETIME', 'null' => true],
        ];
    }

    public function up()
    {
        // ============================================================
        // 1. Kolom biodata di tabel siswa
        // ============================================================
        foreach ($this->kolomSiswa() as $nama => $definisi) {
            if (! $this->db->fieldExists($nama, 'siswa')) {
                $this->forge->addColumn('siswa', [$nama => $definisi]);
            }
        }
        if (! $this->adaIndex('siswa', self::INDEX_BIODATA)) {
            $this->db->query('ALTER TABLE `siswa` ADD INDEX `' . self::INDEX_BIODATA . '` (`biodata_at`)');
        }

        // ============================================================
        // 2. biodata_isian — kotak masuk isian siswa
        // ============================================================
        $this->forge->addField([
            'id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'siswa_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            // Disalin dari `data` agar keunikan NISN antar-isian dijaga database.
            'nisn' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            // Seluruh isian siswa (JSON). LONGTEXT, bukan tipe JSON, demi MariaDB hosting.
            'data' => ['type' => 'LONGTEXT'],
            // Potret data Master Siswa tepat sebelum isian disetujui (jejak audit).
            'data_sebelum' => ['type' => 'LONGTEXT', 'null' => true],
            'status'       => [
                'type'       => 'ENUM',
                'constraint' => ['menunggu', 'disetujui', 'perbaikan'],
                'default'    => 'menunggu',
            ],
            'catatan_admin'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'kirim_ke'          => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 1],
            'ip_address'        => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'diverifikasi_at'   => ['type' => 'DATETIME', 'null' => true],
            'diverifikasi_oleh' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('siswa_id');
        $this->forge->addUniqueKey('nisn'); // NULL boleh berulang di MySQL
        $this->forge->addKey('status');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('siswa_id', 'siswa', 'id', '', 'CASCADE');
        $this->forge->createTable('biodata_isian', true, $this->attr);

        // ============================================================
        // 3. Saklar di settings
        // ============================================================
        foreach ($this->kolomSettings() as $nama => $definisi) {
            if (! $this->db->fieldExists($nama, 'settings')) {
                $this->forge->addColumn('settings', [$nama => $definisi]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('biodata_isian', true);

        foreach (array_keys($this->kolomSettings()) as $nama) {
            if ($this->db->fieldExists($nama, 'settings')) {
                $this->forge->dropColumn('settings', $nama);
            }
        }

        if ($this->adaIndex('siswa', self::INDEX_BIODATA)) {
            $this->db->query('ALTER TABLE `siswa` DROP INDEX `' . self::INDEX_BIODATA . '`');
        }
        foreach (array_reverse(array_keys($this->kolomSiswa())) as $nama) {
            if ($this->db->fieldExists($nama, 'siswa')) {
                $this->forge->dropColumn('siswa', $nama);
            }
        }
    }

    private function adaIndex(string $tabel, string $index): bool
    {
        return $this->db->query('SHOW INDEX FROM `' . $tabel . '` WHERE Key_name = ?', [$index])
            ->getNumRows() > 0;
    }
}
