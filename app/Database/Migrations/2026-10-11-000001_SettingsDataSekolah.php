<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pengaturan Sekolah — 4 kolom untuk kop & tanda tangan "Daftar Nama Siswa" (Format 8355):
 *
 *   school_postal_code     Kode Pos
 *   school_accreditation   Status Akreditasi (mis. "B")
 *   supervisor_name        Pengawas Pembina (nama + gelar) — tanda tangan "Mengetahui,"
 *   supervisor_nip         NIP Pengawas Pembina
 *
 * Murni TAMBAHAN & idempoten. Kode pos dan akreditasi diisi awal hanya bila kolomnya baru dibuat
 * (nilai dari berkas resmi sekolah; boleh diubah di menu Pengaturan Sekolah). Nama + NIP pengawas
 * sengaja TIDAK diisi di sini (repo publik) — isi lewat Pengaturan Sekolah.
 * Lihat docs/RENCANA-DATA-SISWA-8355.md (F5).
 */
class SettingsDataSekolah extends Migration
{
    /** @return array<string, array<string, mixed>> */
    private function kolom(): array
    {
        return [
            'school_postal_code'   => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'after' => 'address'],
            'school_accreditation' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'school_postal_code'],
            'supervisor_name'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'headmaster_nip'],
            'supervisor_nip'       => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true, 'after' => 'supervisor_name'],
        ];
    }

    public function up()
    {
        $baru = [];
        foreach ($this->kolom() as $nama => $definisi) {
            if (! $this->db->fieldExists($nama, 'settings')) {
                $this->forge->addColumn('settings', [$nama => $definisi]);
                $baru[] = $nama;
            }
        }
        if (in_array('school_postal_code', $baru, true)) {
            $this->db->table('settings')->set('school_postal_code', '17610')->update();
        }
        if (in_array('school_accreditation', $baru, true)) {
            $this->db->table('settings')->set('school_accreditation', 'B')->update();
        }
        cache()->delete('app_setting');
    }

    public function down()
    {
        foreach (array_keys($this->kolom()) as $nama) {
            if ($this->db->fieldExists($nama, 'settings')) {
                $this->forge->dropColumn('settings', $nama);
            }
        }
        cache()->delete('app_setting');
    }
}
