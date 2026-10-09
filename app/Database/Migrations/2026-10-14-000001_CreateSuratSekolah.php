<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Surat Sekolah — Langkah 1 (fondasi). Rancangan: docs/DESAIN-SURAT-SEKOLAH.md.
 *
 *   1. pkl_pengaturan.surat_perlu_acc — JSON {"izin_asts":1,...}: jenis surat yang wajib di-ACC (NULL = hak bawaan di kode).
 *   2. surat_sekolah         — satu baris per surat: Izin ASTS, Izin TKA, Pernyataan Orang Tua PKL, Surat Balasan PKL,
 *                              Penarikan Izin PKL. Nomor ditetapkan SEKALI saat pertama diunduh dan memakai SATU urutan
 *                              dengan pkl_surat (Surat Izin PKL) — lihat Libraries\SuratNomor. UNIQUE(tahun, urut) menjaga
 *                              nomor ganda di dalam tabel ini; antar-tabel dijaga kunci pkl_pengaturan (FOR UPDATE).
 *   3. surat_sekolah_siswa   — siswa yang tercantum di surat (tabel siswa / daftar pernyataan).
 *   4. surat_sekolah_riwayat — jejak kejadian per surat (siapa, kapan, apa).
 *   5. Hak baru 'surat_sekolah' ditambahkan ke hak Operator HANYA bila Admin pernah menyimpan Hak Akses PKL
 *      (kolom hak_peran kosong = hak bawaan di kode, yang sudah memuatnya).
 *
 * Idempoten: aman dijalankan ulang bila sempat gagal di tengah jalan.
 */
class CreateSuratSekolah extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        // 1. Pengaturan: jenis surat yang wajib ACC
        if (! $this->db->fieldExists('surat_perlu_acc', 'pkl_pengaturan')) {
            $this->forge->addColumn('pkl_pengaturan', [
                'surat_perlu_acc' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ]);
        }

        // 2. surat_sekolah
        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'jenis'  => ['type' => 'ENUM', 'constraint' => ['izin_asts', 'izin_tka', 'pernyataan_ortu', 'balasan_pkl', 'penarikan_pkl']],
            // menunggu = menunggu ACC · dikembalikan = diminta diperbaiki · disetujui = siap unduh (ACC, atau jenis tanpa ACC) · dibatalkan
            'status' => ['type' => 'ENUM', 'constraint' => ['menunggu', 'dikembalikan', 'disetujui', 'dibatalkan'], 'default' => 'menunggu'],
            // Salinan aturan saat surat dibuat (1 = wajib ACC): mengubah aturan nanti tidak mengubah surat yang sudah ada.
            'perlu_acc' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            // Ringkasan yang tampil di Daftar Surat.
            'judul'         => ['type' => 'VARCHAR', 'constraint' => 190],
            'tanggal_surat' => ['type' => 'DATE'],
            // Penomoran (diisi SEKALI saat pertama diunduh; jenis tanpa nomor tetap NULL).
            'tahun' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
            'urut'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'nomor' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            // Tujuan: ajuan PKL bila ada (SET NULL saat ajuan dihapus; nomor & surat tetap tercatat) + salinan nama perusahaan.
            'pengajuan_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'perusahaan_nama' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            // Data khusus jenis (kegiatan, tanggal, sesi, periode, alasan, sapaan…) sebagai JSON.
            'isi'          => ['type' => 'TEXT', 'null' => true],
            'catatan_staf' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'diajukan_at'  => ['type' => 'DATETIME', 'null' => true],
            // Catatan ACC (siapa, kapan, dari mana) — tercetak di kaki surat.
            'acc_admin_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'acc_nama'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'acc_peran'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'acc_at'       => ['type' => 'DATETIME', 'null' => true],
            'acc_ip'       => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'acc_kode'     => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            // Cetak: berapa kali diunduh + sidik data terakhir (untuk penanda "perlu cetak ulang").
            'cetak_ke'          => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'terakhir_cetak_at' => ['type' => 'DATETIME', 'null' => true],
            'sidik'             => ['type' => 'CHAR', 'constraint' => 40, 'null' => true],
            'dibuat_oleh' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'dibuat_nama' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tahun', 'urut'], 'surat_sekolah_nomor_unik');
        $this->forge->addKey(['status', 'created_at']);
        $this->forge->addKey(['jenis', 'status']);
        $this->forge->addKey('tanggal_surat');
        $this->forge->addKey('pengajuan_id');
        $this->forge->addForeignKey('pengajuan_id', 'pkl_pengajuan', 'id', '', 'SET NULL');
        $this->forge->createTable('surat_sekolah', true, $this->attr);

        // 3. surat_sekolah_siswa
        $this->forge->addField([
            'id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'surat_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'siswa_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            // Kelas saat surat dibuat (kelas berubah tiap kenaikan, sedangkan surat lama harus tetap benar).
            'kelas_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            // HP yang dicetak bila berbeda dari data induk siswa (kosong = pakai siswa.no_hp).
            'hp'         => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
            'urut'       => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['surat_id', 'siswa_id'], 'surat_siswa_unik');
        $this->forge->addKey('siswa_id');
        $this->forge->addForeignKey('surat_id', 'surat_sekolah', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('siswa_id', 'siswa', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'SET NULL');
        $this->forge->createTable('surat_sekolah_siswa', true, $this->attr);

        // 4. surat_sekolah_riwayat
        $this->forge->addField([
            'id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'surat_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            // buat, ajukan_ulang, acc, kembalikan, ubah, nomor, cetak, batal …
            'aksi'       => ['type' => 'VARCHAR', 'constraint' => 30],
            'oleh'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'admin_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'peran'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'catatan'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['surat_id', 'created_at']);
        $this->forge->addForeignKey('surat_id', 'surat_sekolah', 'id', '', 'CASCADE');
        $this->forge->createTable('surat_sekolah_riwayat', true, $this->attr);

        // 5. Hak baru untuk Operator (hanya bila Admin pernah menyimpan Hak Akses PKL).
        $baris  = $this->db->table('pkl_pengaturan')->select('hak_peran')->where('id', 1)->get()->getRowArray();
        $mentah = (string) ($baris['hak_peran'] ?? '');
        if ($mentah !== '') {
            $hak = json_decode($mentah, true);
            if (is_array($hak)) {
                $operator = is_array($hak['operator'] ?? null) ? array_map('strval', $hak['operator']) : [];
                if (! in_array('surat_sekolah', $operator, true)) {
                    $operator[]     = 'surat_sekolah';
                    $hak['operator'] = array_values($operator);
                    $this->db->table('pkl_pengaturan')->where('id', 1)->update(['hak_peran' => json_encode($hak, JSON_UNESCAPED_UNICODE)]);
                }
            }
        }
    }

    public function down()
    {
        // Anak dulu, baru induk (kunci asing).
        foreach (['surat_sekolah_riwayat', 'surat_sekolah_siswa', 'surat_sekolah'] as $tabel) {
            $this->forge->dropTable($tabel, true);
        }
        if ($this->db->fieldExists('surat_perlu_acc', 'pkl_pengaturan')) {
            $this->forge->dropColumn('pkl_pengaturan', 'surat_perlu_acc');
        }
    }
}
