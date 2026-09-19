<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Modul Manajemen Dokumen (SIMDOK).
 *
 * 4 tabel (urutan dibuat mengikuti ketergantungan foreign key):
 *   1. dokumen_folder    — folder bertingkat (self-FK parent_id), ala Drive
 *   2. dokumen           — berkas ATAU tautan eksternal (YouTube/Drive)
 *   3. dokumen_share     — tautan berbagi bertoken (kedaluwarsa/sandi/batas)
 *   4. dokumen_akses_log — jejak siapa melihat/mengunduh apa
 *
 * Berkas fisik TIDAK disimpan di public/, melainkan di
 * writable/uploads/dokumen/YYYY/MM/ yang tertutup dari web — modul ini
 * memegang dokumen kesiswaan, jadi tiap akses wajib lewat controller.
 *
 * Semua InnoDB + utf8mb4, gaya sama dengan CreateUkk / CreateLabInventaris.
 */
class CreateDokumen extends Migration
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

    /** Tiga tingkat privasi yang dipakai folder maupun dokumen. */
    private function visibilitas(): array
    {
        return [
            'type'       => 'ENUM',
            'constraint' => ['privat', 'link', 'publik'],
            'default'    => 'privat',
        ];
    }

    public function up()
    {
        // ============================================================
        // 1. dokumen_folder — bertingkat lewat parent_id (self-FK)
        // ============================================================
        $this->forge->addField([
            'id'          => $this->pk(),
            'nama'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'parent_id'   => $this->fk(true),
            'deskripsi'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'visibilitas' => $this->visibilitas(),
            'warna'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'created_by'  => $this->fk(true),
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addKey('parent_id');
        $this->forge->addKey('nama');
        $this->forge->addForeignKey('created_by', 'admins', 'id', '', 'SET NULL');
        $this->forge->createTable('dokumen_folder', true, $this->attr);

        // Self-FK ditambahkan terpisah (tabel harus ada lebih dulu).
        // SET NULL, BUKAN CASCADE: InnoDB punya keanehan pada cascade
        // self-referensial, dan penghapusan bertingkat lebih aman ditangani
        // eksplisit di controller (rekursif + hapus berkas fisiknya).
        $this->db->query(
            'ALTER TABLE `dokumen_folder`
             ADD CONSTRAINT `dokumen_folder_parent_id_foreign`
             FOREIGN KEY (`parent_id`) REFERENCES `dokumen_folder`(`id`) ON DELETE SET NULL'
        );

        // ============================================================
        // 2. dokumen — satu baris = satu berkas ATAU satu tautan
        // ============================================================
        $this->forge->addField([
            'id'        => $this->pk(),
            'folder_id' => $this->fk(true),          // NULL = folder akar
            'judul'     => ['type' => 'VARCHAR', 'constraint' => 200],
            'deskripsi' => ['type' => 'TEXT', 'null' => true],
            'tipe'      => [
                'type'       => 'ENUM',
                'constraint' => ['berkas', 'tautan'],
                'default'    => 'berkas',
            ],

            // --- khusus tipe 'berkas' ---
            'nama_asli'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'nama_file'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],  // nama acak di disk
            'path_rel'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],  // 2026/09/xxx.pdf
            'ekstensi'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'mime'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'ukuran'      => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'hash_sha256' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'thumb'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],

            // --- khusus tipe 'tautan' ---
            'url_eksternal' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'penyedia'      => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true], // youtube/drive/lainnya

            'kategori' => [
                'type'       => 'ENUM',
                'constraint' => ['pdf', 'dokumen', 'spreadsheet', 'presentasi', 'gambar', 'audio', 'video', 'arsip', 'lainnya'],
                'default'    => 'lainnya',
            ],
            'visibilitas' => $this->visibilitas(),
            'jml_lihat'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'jml_unduh'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'created_by'  => $this->fk(true),
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],   // = tempat sampah
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addKey('folder_id');
        $this->forge->addKey('kategori');
        $this->forge->addKey('visibilitas');
        $this->forge->addKey('hash_sha256');
        $this->forge->addKey('judul');
        $this->forge->addForeignKey('folder_id', 'dokumen_folder', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('created_by', 'admins', 'id', '', 'SET NULL');
        $this->forge->createTable('dokumen', true, $this->attr);

        // ============================================================
        // 3. dokumen_share — tautan berbagi (per dokumen ATAU per folder)
        // ============================================================
        $this->forge->addField([
            'id'            => $this->pk(),
            'dokumen_id'    => $this->fk(true),
            'folder_id'     => $this->fk(true),
            'token'         => ['type' => 'VARCHAR', 'constraint' => 64],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'expired_at'    => ['type' => 'DATETIME', 'null' => true],
            'boleh_unduh'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'maks_unduh'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'jml_akses'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'jml_unduh'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'catatan'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'aktif'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by'    => $this->fk(true),
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token');
        $this->forge->addKey('dokumen_id');
        $this->forge->addKey('folder_id');
        $this->forge->addForeignKey('dokumen_id', 'dokumen', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('folder_id', 'dokumen_folder', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'admins', 'id', '', 'SET NULL');
        $this->forge->createTable('dokumen_share', true, $this->attr);

        // ============================================================
        // 4. dokumen_akses_log — jejak akses (admin maupun tamu tautan)
        // ============================================================
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'dokumen_id' => $this->fk(true),
            'share_id'   => $this->fk(true),
            'aksi'       => [
                'type'       => 'ENUM',
                'constraint' => ['lihat', 'pratinjau', 'unduh'],
                'default'    => 'lihat',
            ],
            'admin_id'   => $this->fk(true),   // NULL = tamu lewat tautan berbagi
            'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('dokumen_id');
        $this->forge->addKey('created_at');
        // SET NULL (bukan CASCADE): jejak akses tetap berguna untuk audit
        // walau dokumennya sudah dihapus permanen.
        $this->forge->addForeignKey('dokumen_id', 'dokumen', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('share_id', 'dokumen_share', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('admin_id', 'admins', 'id', '', 'SET NULL');
        $this->forge->createTable('dokumen_akses_log', true, $this->attr);
    }

    public function down()
    {
        // Urutan terbalik supaya foreign key tidak menghalangi.
        $this->forge->dropTable('dokumen_akses_log', true);
        $this->forge->dropTable('dokumen_share', true);
        $this->forge->dropTable('dokumen', true);
        $this->forge->dropTable('dokumen_folder', true);
    }
}
