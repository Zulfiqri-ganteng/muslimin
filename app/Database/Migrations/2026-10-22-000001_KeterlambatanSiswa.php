<?php

namespace App\Database\Migrations;

use App\Libraries\HakPeran;
use CodeIgniter\Database\Migration;

/**
 * Buku piket keterlambatan siswa (menu KURIKULUM → Guru Piket → Catat Keterlambatan, 2026-10-19):
 *   - keterlambatan_siswa — satu baris = satu siswa terlambat pada satu tanggal (unik siswa + tanggal). Nama siswa, kelas, shift
 *     kelas (pagi/siang), tahun ajaran, dan nama guru piket DISALIN saat dicatat → buku tetap utuh walau siswa naik kelas, pindah,
 *     atau datanya dihapus (id-nya lalu kosong). Sanksi & keterangan = teks bebas guru piket. dikabari_at = sudah dikabari ke
 *     orang tua lewat WhatsApp (tombol WA, Libraries\KeterlambatanWa).
 *   - settings.wa_pesan_terlambat — templat pesan WhatsApp ke orang tua (kosong = pesan bawaan).
 *   - peran "Guru Piket" (bukan peran sistem; bisa diubah/dihapus Admin di Sistem → Hak Akses): Kelola Catat Keterlambatan + Lihat
 *     Rekap Keterlambatan + Lihat Jadwal Piket; halaman awal = Catat Keterlambatan. Akunnya dibuat Admin di Kelola Akun (satu akun bersama di meja piket).
 * Idempoten.
 */
class KeterlambatanSiswa extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        if (! $this->db->tableExists('keterlambatan_siswa')) {
            $this->forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'tanggal'         => ['type' => 'DATE'],
                'jam'             => ['type' => 'TIME', 'null' => true],
                'siswa_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'nama_siswa'      => ['type' => 'VARCHAR', 'constraint' => 150],
                'kelas_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'nama_kelas'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'shift'           => ['type' => 'ENUM', 'constraint' => ['pagi', 'siang'], 'null' => true],
                'tahun_ajaran'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'sanksi'          => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'keterangan'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'guru_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'guru_piket'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'dikabari_at'     => ['type' => 'DATETIME', 'null' => true],
                'dikabari_oleh'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'dicatat_oleh_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'dicatat_oleh'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
                'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['siswa_id', 'tanggal']);
            $this->forge->addKey('tanggal');
            $this->forge->addKey('kelas_id');
            $this->forge->addForeignKey('siswa_id', 'siswa', 'id', '', 'SET NULL');
            $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'SET NULL');
            $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'SET NULL');
            $this->forge->createTable('keterlambatan_siswa', true, $this->attr);
        }

        // Templat pesan WhatsApp ke orang tua (kosong = pesan bawaan Libraries\KeterlambatanWa), diatur Admin di halaman Catat.
        if ($this->db->tableExists('settings') && ! $this->db->fieldExists('wa_pesan_terlambat', 'settings')) {
            $this->forge->addColumn('settings', ['wa_pesan_terlambat' => ['type' => 'VARCHAR', 'constraint' => 600, 'null' => true]]);
        }

        // Peran Guru Piket — hanya bila tabel peran ada dan belum ada peran berkode/bernama sama.
        if ($this->db->tableExists('peran')
            && $this->db->table('peran')->where("kode = 'guru_piket' OR LOWER(label) = 'guru piket'", null, false)->countAllResults() === 0) {
            $now  = date('Y-m-d H:i:s');
            $urut = (int) ($this->db->table('peran')->selectMax('urut', 'm')->get()->getRowArray()['m'] ?? 0) + 1;
            $this->db->table('peran')->insert([
                'kode'       => 'guru_piket',
                'label'      => 'Guru Piket',
                'ringkas'    => 'Akun bersama di meja piket: mencatat siswa yang terlambat (Catat Keterlambatan), melihat Rekap Keterlambatan & mengunduh Excel buku piket, melihat Jadwal Piket. Tidak bisa membuka menu lain. Hak persisnya diatur Admin.',
                'beranda'    => 'admin/keterlambatan',
                'api'        => 1, // boleh aplikasi Android (API api/v1/keterlambatan, docs/API-KETERLAMBATAN.md)
                'sistem'     => 0,
                'urut'       => $urut,
                'menu'       => json_encode(['absensi_piket' => 'lihat', 'keterlambatan' => 'kelola', 'keterlambatan_rekap' => 'lihat'], JSON_FORCE_OBJECT),
                'hak'        => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            HakPeran::lupakan();
        }
    }

    public function down()
    {
        $this->forge->dropTable('keterlambatan_siswa', true);
        if ($this->db->tableExists('settings') && $this->db->fieldExists('wa_pesan_terlambat', 'settings')) {
            $this->forge->dropColumn('settings', 'wa_pesan_terlambat');
        }
        // Peran dihapus hanya bila tidak dipakai akun mana pun.
        if ($this->db->tableExists('peran') && $this->db->table('admins')->where('role', 'guru_piket')->countAllResults() === 0) {
            $this->db->table('peran')->where('kode', 'guru_piket')->where('sistem', 0)->delete();
            HakPeran::lupakan();
        }
    }
}
