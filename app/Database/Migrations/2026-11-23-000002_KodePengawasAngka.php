<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kode pengawas WAJIB ANGKA (permintaan klien 2026-11-23: cetakan Jadwal Pengawas tanpa huruf, padahal Kode Guru KBM memakai huruf,
 * mis. "13a", "20A", "36(1)"). Aturan: angka pengawas = angka di depan Kode Guru (otomatis, KBM tak diubah). Kolom ini = ANGKA
 * PENGGANTI yang diisi admin hanya bila Kode Guru tak punya angka atau angkanya bentrok dengan guru lain (mis. 15 & 15A beda orang).
 * Tersimpan per guru → angka sama dipakai di semua ujian. Kosong = pakai angka otomatis. HANYA MENAMBAH kolom, idempoten.
 */
class KodePengawasAngka extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('kode_pengawas', 'guru')) {
            $this->forge->addColumn('guru', [
                'kode_pengawas' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'after' => 'kode_guru'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('kode_pengawas', 'guru')) {
            $this->forge->dropColumn('guru', 'kode_pengawas');
        }
    }
}
