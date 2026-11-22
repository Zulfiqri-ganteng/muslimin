<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cetak Jadwal Ujian & Jadwal Pengawas — F1 fondasi data (permintaan user 2026-11-22; contoh hasil: berkas klien
 * "jadwal ujian.xlsx" & "jadwal pengawas.xlsx"). HANYA MENAMBAH kolom (project live), idempoten (aman diulang):
 *   - mata_pelajaran.nama_ujian : nama mapel yang tampil di cetakan jadwal ujian (mis. "PAI & BUDI PEKERTI", "PENJASKESOR";
 *                                  tiga mapel DPK berbeda boleh sama-sama "DASAR-DASAR PROGRAM KEAHLIAN"). Kosong = nama_mapel.
 *   - jurusan.label_ujian       : label jurusan di judul kolom jadwal (mis. kode MP → "MPLB", AK → "AKL"). Kosong = kode.
 *   - jurusan.urutan_ujian      : urutan kolom jurusan di jadwal ujian (contoh pagi: MPLB, AKL, TKJ). Kosong = di belakang.
 *   - ujian_periode.ketua_panitia            : nama di blok tanda tangan jadwal pengawas ("Ketua Panitia ASAS").
 *   - ujian_periode.susulan_jam_mulai/selesai: jam baris "UJIAN SUSULAN" (kosong = 07.00 - 12.00 seperti contoh).
 *   - ujian_periode.ruang_pagi/ruang_siang   : jumlah ruang ujian per shift (kosong = jumlah kelas di shift itu).
 */
class UjianCetakJadwal extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('nama_ujian', 'mata_pelajaran')) {
            $this->forge->addColumn('mata_pelajaran', [
                'nama_ujian' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'nama_mapel'],
            ]);
        }
        if (! $this->db->fieldExists('label_ujian', 'jurusan')) {
            $this->forge->addColumn('jurusan', [
                'label_ujian' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'nama'],
            ]);
        }
        // Urutan kolom jurusan di jadwal ujian per tingkat (contoh: MPLB 1, AKL 2, TKJ 3). Kosong = di belakang, urut label.
        if (! $this->db->fieldExists('urutan_ujian', 'jurusan')) {
            $this->forge->addColumn('jurusan', [
                'urutan_ujian' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'after' => 'label_ujian'],
            ]);
        }

        $periode = [
            'ketua_panitia'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'susulan_selesai'],
            'susulan_jam_mulai'   => ['type' => 'TIME', 'null' => true, 'after' => 'ketua_panitia'],
            'susulan_jam_selesai' => ['type' => 'TIME', 'null' => true, 'after' => 'susulan_jam_mulai'],
            'ruang_pagi'          => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'after' => 'susulan_jam_selesai'],
            'ruang_siang'         => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'after' => 'ruang_pagi'],
        ];
        foreach ($periode as $kolom => $def) {
            if (! $this->db->fieldExists($kolom, 'ujian_periode')) {
                $this->forge->addColumn('ujian_periode', [$kolom => $def]);
            }
        }
    }

    public function down()
    {
        foreach (['ruang_siang', 'ruang_pagi', 'susulan_jam_selesai', 'susulan_jam_mulai', 'ketua_panitia'] as $kolom) {
            if ($this->db->fieldExists($kolom, 'ujian_periode')) {
                $this->forge->dropColumn('ujian_periode', $kolom);
            }
        }
        if ($this->db->fieldExists('urutan_ujian', 'jurusan')) {
            $this->forge->dropColumn('jurusan', 'urutan_ujian');
        }
        if ($this->db->fieldExists('label_ujian', 'jurusan')) {
            $this->forge->dropColumn('jurusan', 'label_ujian');
        }
        if ($this->db->fieldExists('nama_ujian', 'mata_pelajaran')) {
            $this->forge->dropColumn('mata_pelajaran', 'nama_ujian');
        }
    }
}
