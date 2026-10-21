<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Layanan Siswa — siswa mengajukan surat sekolah sendiri lewat satu link (permintaan klien 2026-10-21; rancangan:
 * docs/DESAIN-SURAT-SEKOLAH.md bagian 10). Tahap T1 (fondasi):
 *
 *   - `surat_jadwal`     — jadwal kegiatan yang DIBUKA untuk permintaan siswa: ujian ASTS/ASAS/ASAT (semester + tahun pelajaran,
 *                          tanggal dari menu Ujian) dan TKA (gelombang/sesi, khusus kelas XII). Siswa memilih jadwal → surat izin per perusahaan.
 *   - `surat_permintaan` — satu baris per permintaan siswa (jenis, siswa, jadwal, ajuan PKL, isian khusus JSON) yang tertaut ke
 *                          surat_sekolah. Beberapa siswa di perusahaan yang sama bisa tertaut ke SATU surat (satu surat per
 *                          perusahaan per jadwal). Status permintaan dibaca dari status suratnya.
 *   - surat_sekolah.sumber — 'staf' (dibuat di panel admin) | 'siswa' (dibuat dari permintaan siswa) → lencana "Diajukan siswa".
 *   - Hak ACC (keputusan klien 2026-10-21: Operator yang ACC): Operator mendapat ACC Izin ASTS, Izin TKA, Penarikan Izin PKL
 *     (ACC Surat Balasan sudah dipegangnya); hak itu dicabut dari Waka Hubin. Tanda tangan digital Waka Hubin TETAP tercetak di
 *     surat (SuratBerkas: siapa pun yang meng-ACC), kaki surat menulis jujur siapa yang menyetujui. Hanya bila isian hak peran sah.
 *
 * Idempoten. Tidak menyentuh surat/ajuan yang sudah ada.
 */
class SuratPermintaanSiswa extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    /** Hak ACC jenis surat yang dipindah dari Waka Hubin ke Operator. */
    private const HAK_ACC = ['surat_acc:izin_asts', 'surat_acc:izin_tka', 'surat_acc:penarikan_pkl'];

    public function up()
    {
        // 1. surat_jadwal
        if (! $this->db->tableExists('surat_jadwal')) {
            $this->forge->addField([
                'id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'jenis' => ['type' => 'ENUM', 'constraint' => ['izin_asts', 'izin_tka']],
                // Ujian untuk jenis izin_asts: ASTS | ASAS | ASAT (SuratAcara::UJIAN); TKA = NULL.
                'kegiatan' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                // Ujian: Ganjil/Genap + 2026/2027 · TKA: "Gelombang 1" …
                'semester'        => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'tahun_pelajaran' => ['type' => 'VARCHAR', 'constraint' => 9, 'null' => true],
                'sesi'            => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'tgl_mulai'       => ['type' => 'DATE'],
                'tgl_selesai'     => ['type' => 'DATE'],
                'tempat'          => ['type' => 'VARCHAR', 'constraint' => 100],
                // 1 = tampil di Layanan Siswa (selama kegiatan belum selesai), 0 = ditutup staf.
                'buka'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'dibuat_oleh' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'dibuat_nama' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['jenis', 'buka', 'tgl_selesai']);
            $this->forge->createTable('surat_jadwal', true, $this->attr);
        }

        // 2. surat_permintaan
        if (! $this->db->tableExists('surat_permintaan')) {
            $this->forge->addField([
                'id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'jenis'    => ['type' => 'ENUM', 'constraint' => ['izin_asts', 'izin_tka', 'pernyataan_ortu', 'balasan_pkl', 'penarikan_pkl']],
                'siswa_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                // Kelas saat meminta (kelas berubah tiap kenaikan).
                'kelas_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'surat_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'jadwal_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'pengajuan_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                // Isian khusus jenis (alasan & tanggal penarikan, data orang tua, catatan) sebagai JSON.
                'isian'      => ['type' => 'TEXT', 'null' => true],
                'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['siswa_id', 'jenis']);
            $this->forge->addKey(['jenis', 'created_at']);
            $this->forge->addKey('surat_id');
            $this->forge->addForeignKey('siswa_id', 'siswa', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'SET NULL');
            $this->forge->addForeignKey('surat_id', 'surat_sekolah', 'id', '', 'SET NULL');
            $this->forge->addForeignKey('jadwal_id', 'surat_jadwal', 'id', '', 'SET NULL');
            $this->forge->addForeignKey('pengajuan_id', 'pkl_pengajuan', 'id', '', 'SET NULL');
            $this->forge->createTable('surat_permintaan', true, $this->attr);
        }

        // 3. Asal surat
        if (! $this->db->fieldExists('sumber', 'surat_sekolah')) {
            $this->forge->addColumn('surat_sekolah', [
                'sumber' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'staf', 'after' => 'status'],
            ]);
        }

        // 4. ACC Izin ASTS / Izin TKA / Penarikan → Operator (dicabut dari Waka Hubin).
        $beri  = $this->aturHak('operator', self::HAK_ACC, true);
        $cabut = $this->aturHak('hubin', self::HAK_ACC, false);
        if ($beri || $cabut) {
            $this->audit('Hak ACC Surat Izin ASTS, Izin TKA, dan Penarikan Izin PKL dipindah ke Operator (keputusan sekolah 2026-10-21; migrasi Layanan Siswa).');
        }
    }

    public function down()
    {
        if ($this->aturHak('hubin', self::HAK_ACC, true) || $this->aturHak('operator', self::HAK_ACC, false)) {
            $this->audit('Hak ACC Surat Izin ASTS, Izin TKA, dan Penarikan Izin PKL dikembalikan ke Waka Hubin (migrasi Layanan Siswa dibatalkan).');
        }
        if ($this->db->fieldExists('sumber', 'surat_sekolah')) {
            $this->forge->dropColumn('surat_sekolah', 'sumber');
        }
        $this->forge->dropTable('surat_permintaan', true);
        $this->forge->dropTable('surat_jadwal', true);
    }

    /**
     * Beri ($beri = true) atau cabut daftar hak dari satu peran (tabel `peran`, kolom hak JSON). true bila ada yang berubah.
     *
     * @param list<string> $kunci
     */
    private function aturHak(string $kode, array $kunci, bool $beri): bool
    {
        if (! $this->db->tableExists('peran')) {
            return false;
        }
        $r = $this->db->table('peran')->select('hak')->where('kode', $kode)->get()->getRowArray();
        if ($r === null) {
            return false;
        }
        $hak = json_decode((string) $r['hak'], true);
        if (! is_array($hak)) {
            return false; // isian rusak/kosong: dibiarkan (Admin bisa mengaturnya di Sistem → Hak Akses)
        }
        $hak  = array_values(array_map('strval', $hak));
        $baru = $beri ? array_values(array_unique(array_merge($hak, $kunci))) : array_values(array_diff($hak, $kunci));
        if ($baru === $hak) {
            return false;
        }
        $this->db->table('peran')->where('kode', $kode)->update(['hak' => json_encode($baru), 'updated_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    private function audit(string $teks): void
    {
        if (! $this->db->tableExists('audit_log')) {
            return;
        }
        $this->db->table('audit_log')->insert([
            'admin_id' => null, 'aksi' => 'update', 'tabel' => 'peran', 'record_id' => null,
            'deskripsi' => mb_substr($teks, 0, 255), 'ip_address' => null, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
