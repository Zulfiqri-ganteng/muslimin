<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Modul Laboratorium & Inventaris (SIMLAB) — fokus lab komputer/TKJ.
 *
 * 11 tabel (urutan dibuat mengikuti ketergantungan foreign key):
 *   1. teknisi          — penanggung jawab/teknisi (bisa non-guru, opsional →guru)
 *   2. lab              — data laboratorium (PJ = teknisi)
 *   3. aset             — inventaris/barang (nomor aset, kondisi, lokasi = lab)
 *   4. aset_komputer    — detail 1:1 khusus komputer (CPU/RAM/OS/IP …)
 *   5. sparepart        — stok suku cadang
 *   6. peminjaman       — peminjaman + pengembalian (peminjam teks bebas)
 *   7. kerusakan        — laporan kerusakan aset
 *   8. perbaikan        — perbaikan/maintenance/penggantian komponen
 *   9. sparepart_mutasi — mutasi stok (keluar saat penggantian komponen)
 *  10. jadwal_lab       — jadwal pemakaian lab (praktik guru = filter per guru)
 *  11. jurnal_lab       — jurnal realisasi pemakaian lab
 *
 * Memakai ulang tabel yang sudah ada: guru, kelas, mata_pelajaran, hari,
 * jam_pelajaran. Semua InnoDB + utf8mb4, gaya sama dengan CreateJabatanSiswa.
 */
class CreateLabInventaris extends Migration
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

    private function kondisiEnum(bool $null = false, string $default = 'baik'): array
    {
        $col = ['type' => 'ENUM', 'constraint' => ['baik', 'rusak_ringan', 'rusak_berat']];
        if ($null) {
            $col['null'] = true;
        } else {
            $col['default'] = $default;
        }

        return $col;
    }

    public function up()
    {
        // ============================================================
        // 1. teknisi
        // ============================================================
        $this->forge->addField([
            'id'    => $this->pk(),
            'kode'  => ['type' => 'VARCHAR', 'constraint' => 30],
            'nama'  => ['type' => 'VARCHAR', 'constraint' => 150],
            'peran' => [
                'type'       => 'ENUM',
                'constraint' => ['teknisi', 'kepala_lab', 'laboran', 'lainnya'],
                'default'    => 'teknisi',
            ],
            'no_hp'      => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
            // Opsional: bila teknisi ternyata seorang guru, tautkan.
            'guru_id'    => $this->fk(true),
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->addKey('nama');
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'SET NULL');
        $this->forge->createTable('teknisi', true, $this->attr);

        // ============================================================
        // 2. lab
        // ============================================================
        $this->forge->addField([
            'id'    => $this->pk(),
            'kode'  => ['type' => 'VARCHAR', 'constraint' => 30],
            'nama'  => ['type' => 'VARCHAR', 'constraint' => 150],
            'jenis' => [
                'type'       => 'ENUM',
                'constraint' => ['komputer', 'jaringan', 'multimedia', 'lainnya'],
                'default'    => 'komputer',
            ],
            'ruang'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'kapasitas'  => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'teknisi_id' => $this->fk(true),
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->addKey('nama');
        $this->forge->addForeignKey('teknisi_id', 'teknisi', 'id', '', 'SET NULL');
        $this->forge->createTable('lab', true, $this->attr);

        // ============================================================
        // 3. aset
        // ============================================================
        $this->forge->addField([
            'id'         => $this->pk(),
            'nomor_aset' => ['type' => 'VARCHAR', 'constraint' => 50],
            'nama'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'kategori'   => [
                'type'       => 'ENUM',
                'constraint' => ['komputer', 'laptop', 'printer', 'proyektor', 'jaringan', 'furnitur', 'lainnya'],
                'default'    => 'komputer',
            ],
            'lab_id'          => $this->fk(true),
            'merk'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'spesifikasi'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'tahun_pengadaan' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'sumber_dana'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'harga'           => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'kondisi'         => $this->kondisiEnum(),
            'status'          => [
                'type'       => 'ENUM',
                'constraint' => ['tersedia', 'dipinjam', 'perbaikan', 'dihapus'],
                'default'    => 'tersedia',
            ],
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nomor_aset');
        $this->forge->addKey('nama');
        $this->forge->addKey('lab_id');
        $this->forge->addKey('kategori');
        $this->forge->addKey('kondisi');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('lab_id', 'lab', 'id', '', 'SET NULL');
        $this->forge->createTable('aset', true, $this->attr);

        // ============================================================
        // 4. aset_komputer (detail 1:1)
        // ============================================================
        $this->forge->addField([
            'id'          => $this->pk(),
            'aset_id'     => $this->fk(),
            'hostname'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'processor'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'ram'         => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'storage'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'gpu'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'os'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'mac_address' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'ip_address'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'monitor'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'keterangan'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('aset_id'); // 1:1 dengan aset
        $this->forge->addForeignKey('aset_id', 'aset', 'id', '', 'CASCADE');
        $this->forge->createTable('aset_komputer', true, $this->attr);

        // ============================================================
        // 5. sparepart
        // ============================================================
        $this->forge->addField([
            'id'           => $this->pk(),
            'kode'         => ['type' => 'VARCHAR', 'constraint' => 30],
            'nama'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'kategori'     => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'satuan'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'unit'],
            'stok'         => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'stok_minimum' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'harga'        => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'lokasi'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'keterangan'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode');
        $this->forge->addKey('nama');
        $this->forge->createTable('sparepart', true, $this->attr);

        // ============================================================
        // 6. peminjaman (+ pengembalian di baris yang sama)
        // ============================================================
        $this->forge->addField([
            'id'            => $this->pk(),
            'aset_id'       => $this->fk(),
            'peminjam_nama' => ['type' => 'VARCHAR', 'constraint' => 150],
            'peminjam_tipe' => [
                'type'       => 'ENUM',
                'constraint' => ['guru', 'siswa', 'umum'],
                'default'    => 'umum',
            ],
            // Rujukan opsional ke id guru/siswa — SENGAJA tanpa FK (polimorfik).
            'peminjam_ref'            => $this->fk(true),
            'tujuan'                  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'tanggal_pinjam'          => ['type' => 'DATE'],
            'tanggal_kembali_rencana' => ['type' => 'DATE', 'null' => true],
            'tanggal_kembali_aktual'  => ['type' => 'DATE', 'null' => true],
            'kondisi_pinjam'          => $this->kondisiEnum(),
            'kondisi_kembali'         => $this->kondisiEnum(true),
            'status'                  => [
                'type'       => 'ENUM',
                'constraint' => ['dipinjam', 'dikembalikan', 'terlambat', 'hilang'],
                'default'    => 'dipinjam',
            ],
            'petugas_id' => $this->fk(true),
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addKey('aset_id');
        $this->forge->addKey('status');
        $this->forge->addKey('tanggal_pinjam');
        $this->forge->addForeignKey('aset_id', 'aset', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('petugas_id', 'teknisi', 'id', '', 'SET NULL');
        $this->forge->createTable('peminjaman', true, $this->attr);

        // ============================================================
        // 7. kerusakan
        // ============================================================
        $this->forge->addField([
            'id'            => $this->pk(),
            'aset_id'       => $this->fk(),
            'tanggal_lapor' => ['type' => 'DATE'],
            'pelapor'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'deskripsi'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'tingkat'       => [
                'type'       => 'ENUM',
                'constraint' => ['ringan', 'sedang', 'berat'],
                'default'    => 'ringan',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['dilaporkan', 'diproses', 'selesai', 'tak_teratasi'],
                'default'    => 'dilaporkan',
            ],
            'teknisi_id' => $this->fk(true),
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addKey('aset_id');
        $this->forge->addKey('status');
        $this->forge->addKey('tanggal_lapor');
        $this->forge->addForeignKey('aset_id', 'aset', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('teknisi_id', 'teknisi', 'id', '', 'SET NULL');
        $this->forge->createTable('kerusakan', true, $this->attr);

        // ============================================================
        // 8. perbaikan (perbaikan / maintenance / penggantian)
        // ============================================================
        $this->forge->addField([
            'id'           => $this->pk(),
            'aset_id'      => $this->fk(),
            'kerusakan_id' => $this->fk(true),
            'jenis'        => [
                'type'       => 'ENUM',
                'constraint' => ['perbaikan', 'maintenance', 'penggantian'],
                'default'    => 'perbaikan',
            ],
            'tanggal'    => ['type' => 'DATE'],
            'teknisi_id' => $this->fk(true),
            'tindakan'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'hasil'      => [
                'type'       => 'ENUM',
                'constraint' => ['berhasil', 'sebagian', 'gagal', 'ganti_unit'],
                'default'    => 'berhasil',
            ],
            'biaya'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'status'     => [
                'type'       => 'ENUM',
                'constraint' => ['proses', 'selesai'],
                'default'    => 'selesai',
            ],
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addKey('aset_id');
        $this->forge->addKey('kerusakan_id');
        $this->forge->addKey('jenis');
        $this->forge->addKey('tanggal');
        $this->forge->addForeignKey('aset_id', 'aset', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('kerusakan_id', 'kerusakan', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('teknisi_id', 'teknisi', 'id', '', 'SET NULL');
        $this->forge->createTable('perbaikan', true, $this->attr);

        // ============================================================
        // 9. sparepart_mutasi (dibuat setelah perbaikan agar FK-nya ada)
        // ============================================================
        $this->forge->addField([
            'id'           => $this->pk(),
            'sparepart_id' => $this->fk(),
            'tanggal'      => ['type' => 'DATE'],
            'tipe'         => ['type' => 'ENUM', 'constraint' => ['masuk', 'keluar']],
            'jumlah'       => ['type' => 'INT', 'constraint' => 11],
            // Bila keluar untuk sebuah perbaikan/penggantian komponen.
            'perbaikan_id' => $this->fk(true),
            'keterangan'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'petugas'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sparepart_id');
        $this->forge->addKey('perbaikan_id');
        $this->forge->addKey('tanggal');
        $this->forge->addForeignKey('sparepart_id', 'sparepart', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('perbaikan_id', 'perbaikan', 'id', '', 'SET NULL');
        $this->forge->createTable('sparepart_mutasi', true, $this->attr);

        // ============================================================
        // 10. jadwal_lab (modul terpisah dari Penjadwalan KBM)
        // ============================================================
        $this->forge->addField([
            'id'         => $this->pk(),
            'lab_id'     => $this->fk(),
            'hari_id'    => $this->fk(),
            'jam_id'     => $this->fk(),
            'guru_id'    => $this->fk(true),
            'kelas_id'   => $this->fk(true),
            'mapel_id'   => $this->fk(true),
            'kegiatan'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        // Satu lab hanya boleh dipakai satu kegiatan per slot (anti bentrok).
        $this->forge->addUniqueKey(['lab_id', 'hari_id', 'jam_id']);
        $this->forge->addKey('guru_id');
        $this->forge->addForeignKey('lab_id', 'lab', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('hari_id', 'hari', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('jam_id', 'jam_pelajaran', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('mapel_id', 'mata_pelajaran', 'id', '', 'SET NULL');
        $this->forge->createTable('jadwal_lab', true, $this->attr);

        // ============================================================
        // 11. jurnal_lab (realisasi pemakaian)
        // ============================================================
        $this->forge->addField([
            'id'              => $this->pk(),
            'lab_id'          => $this->fk(),
            'tanggal'         => ['type' => 'DATE'],
            'jam_mulai'       => ['type' => 'TIME', 'null' => true],
            'jam_selesai'     => ['type' => 'TIME', 'null' => true],
            'guru_id'         => $this->fk(true),
            'kelas_id'        => $this->fk(true),
            'kegiatan'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'jumlah_hadir'    => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'kondisi_setelah' => [
                'type'       => 'ENUM',
                'constraint' => ['baik', 'ada_kendala'],
                'default'    => 'baik',
            ],
            'kendala'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'teknisi_id' => $this->fk(true),
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $this->ts());
        $this->forge->addKey('id', true);
        $this->forge->addKey('lab_id');
        $this->forge->addKey('tanggal');
        $this->forge->addKey('guru_id');
        $this->forge->addForeignKey('lab_id', 'lab', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('guru_id', 'guru', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('teknisi_id', 'teknisi', 'id', '', 'SET NULL');
        $this->forge->createTable('jurnal_lab', true, $this->attr);
    }

    public function down()
    {
        // Urutan drop = kebalikan create agar foreign key tidak menahan.
        foreach ([
            'jurnal_lab', 'jadwal_lab', 'sparepart_mutasi', 'perbaikan',
            'kerusakan', 'peminjaman', 'sparepart', 'aset_komputer',
            'aset', 'lab', 'teknisi',
        ] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
