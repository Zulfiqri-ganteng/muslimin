<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Modul PKL / Prakerin — siswa mengajukan tempat PKL lewat tautan publik
 * (subdomain binuspkl.kangmuslim.com) tanpa login; staf memeriksa & menyetujui.
 *
 *   1. pkl_pengaturan — baris tunggal (id = 1): saklar buka/tutup form, tingkat
 *                       yang boleh mengajukan, batas tanggal PKL dari sekolah,
 *                       batas lama PKL, maksimal siswa per perusahaan.
 *   2. pkl_perusahaan — master perusahaan. HANYA diisi saat ajuan disetujui,
 *                       dipakai untuk saran nama di form agar "PT Telkom" dan
 *                       "telkom" tidak menjadi dua data.
 *   3. pkl_pengajuan  — satu ajuan = satu perusahaan + satu rombongan siswa.
 *   4. pkl_anggota    — siswa dalam ajuan (satu PENGAJU + nol atau lebih TEMAN).
 *                       Kolom `siswa_aktif` = siswa_id selama ajuannya masih
 *                       aktif (menunggu/perbaikan/disetujui), NULL bila tidak.
 *                       UNIQUE(siswa_aktif) membuat "satu siswa satu ajuan
 *                       aktif" dijaga DATABASE sendiri — tahan dobel-klik dan
 *                       dua HP yang mengirim bersamaan. NULL boleh berulang di
 *                       MySQL, jadi ajuan yang ditolak tidak mengganjal.
 *   5. pkl_riwayat    — jejak setiap kejadian pada ajuan (siapa, kapan, apa).
 *
 * Idempoten: aman dijalankan ulang bila sempat gagal di tengah jalan.
 * Lihat docs/DESAIN-PKL.md.
 */
class CreatePkl extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    public function up()
    {
        // ============================================================
        // 1. pkl_pengaturan — baris tunggal
        // ============================================================
        $this->forge->addField([
            'id'   => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true],
            // Default TUTUP: staf sendiri yang membuka saat tautan siap dibagikan.
            'form_buka'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            // Batas waktu opsional; lewat waktu ini form otomatis tertutup.
            'form_tutup' => ['type' => 'DATETIME', 'null' => true],
            // Tingkat yang boleh mengajukan, dipisah koma (X, XI, XII). Contoh: 'XI,XII'.
            'tingkat' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'XI'],
            // Pagar tanggal dari sekolah: PKL tiap perusahaan boleh beda, tapi di dalam pagar ini.
            'mulai_paling_awal'    => ['type' => 'DATE', 'null' => true],
            'selesai_paling_akhir' => ['type' => 'DATE', 'null' => true],
            // Lama PKL (hari, dihitung inklusif) — penjaga salah ketik tahun/bulan.
            'durasi_min_hari'  => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 30],
            'durasi_maks_hari' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 270],
            // Maksimal siswa dalam satu ajuan (pengaju + teman).
            'maks_anggota' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 5],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('pkl_pengaturan', true, $this->attr);

        if ($this->db->table('pkl_pengaturan')->where('id', 1)->countAllResults() === 0) {
            $this->db->table('pkl_pengaturan')->insert(['id' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        }

        // ============================================================
        // 2. pkl_perusahaan — master (hanya yang sudah disetujui)
        // ============================================================
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nama'           => ['type' => 'VARCHAR', 'constraint' => 150],
            // Nama yang sudah diseragamkan (huruf kecil, tanpa tanda baca & "PT/CV") untuk cari & deteksi ganda.
            'nama_norm'      => ['type' => 'VARCHAR', 'constraint' => 150],
            'alamat'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'kota'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'telepon'        => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'kontak_nama'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'kontak_jabatan' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('nama_norm');
        $this->forge->createTable('pkl_perusahaan', true, $this->attr);

        // ============================================================
        // 3. pkl_pengajuan — satu ajuan = satu perusahaan + satu rombongan
        // ============================================================
        $this->forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['menunggu', 'perbaikan', 'disetujui', 'ditolak'],
                'default'    => 'menunggu',
            ],
            // Asal data: diisi siswa sendiri, diisi staf atas nama siswa, atau diimpor dari Excel lama.
            'sumber' => [
                'type'       => 'ENUM',
                'constraint' => ['siswa', 'staf', 'impor'],
                'default'    => 'siswa',
            ],
            'tahun_ajaran' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            // Diisi saat ACC (menautkan ke master). Ajuan menyimpan SALINAN data perusahaannya sendiri.
            'perusahaan_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'perusahaan_nama'   => ['type' => 'VARCHAR', 'constraint' => 150],
            'perusahaan_norm'   => ['type' => 'VARCHAR', 'constraint' => 150],
            // Nullable di database karena riwayat lama (impor) bisa tak lengkap; form siswa tetap mewajibkan.
            'perusahaan_alamat'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'perusahaan_kota'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'perusahaan_telepon' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'kontak_nama'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'kontak_jabatan'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'tanggal_mulai'      => ['type' => 'DATE', 'null' => true],
            'tanggal_selesai'    => ['type' => 'DATE', 'null' => true],
            // Alasan kembalikan / tolak dari staf. Dikosongkan saat siswa mengirim ulang (jejaknya ada di pkl_riwayat).
            'catatan_staf'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'kirim_ke'        => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 1],
            'ip_address'      => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'diputuskan_at'   => ['type' => 'DATETIME', 'null' => true],
            'diputuskan_oleh' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->addKey('perusahaan_norm');
        $this->forge->addKey(['ip_address', 'created_at']); // penjaga banjir kiriman per IP
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('perusahaan_id', 'pkl_perusahaan', 'id', '', 'SET NULL');
        $this->forge->createTable('pkl_pengajuan', true, $this->attr);

        // ============================================================
        // 4. pkl_anggota — siswa dalam ajuan
        // ============================================================
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'pengajuan_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'siswa_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'peran'        => ['type' => 'ENUM', 'constraint' => ['pengaju', 'teman'], 'default' => 'teman'],
            // Kelas SAAT mengajukan. Kelas siswa berubah tiap kenaikan, sedangkan surat lama harus tetap benar.
            'kelas_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            // HP terbaru & tanggal lahir (kunci buka-ulang) — diisi pengaju; teman boleh kosong.
            'hp'            => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
            'tanggal_lahir' => ['type' => 'DATE', 'null' => true],
            // = siswa_id selama ajuan AKTIF, NULL bila ditolak. Dijaga PklAjuan::sinkronAktif().
            'siswa_aktif' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['pengajuan_id', 'siswa_id'], 'anggota_unik_per_ajuan');
        $this->forge->addUniqueKey('siswa_aktif', 'siswa_aktif');
        $this->forge->addKey('siswa_id');
        $this->forge->addForeignKey('pengajuan_id', 'pkl_pengajuan', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('siswa_id', 'siswa', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'SET NULL');
        $this->forge->createTable('pkl_anggota', true, $this->attr);

        // ============================================================
        // 5. pkl_riwayat — jejak kejadian per ajuan
        // ============================================================
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'pengajuan_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            // kirim, kirim_ulang, acc, kembalikan, tolak, batal_acc, ubah, isi_atas_nama, cetak ...
            'aksi'     => ['type' => 'VARCHAR', 'constraint' => 30],
            // "Siswa: Nama" untuk kiriman siswa, nama staf untuk aksi staf.
            'oleh'     => ['type' => 'VARCHAR', 'constraint' => 150],
            'admin_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'peran'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'catatan'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['pengajuan_id', 'created_at']);
        $this->forge->addForeignKey('pengajuan_id', 'pkl_pengajuan', 'id', '', 'CASCADE');
        $this->forge->createTable('pkl_riwayat', true, $this->attr);
    }

    public function down()
    {
        // Urutan terbalik: anak dulu, baru induk (kunci asing).
        foreach (['pkl_riwayat', 'pkl_anggota', 'pkl_pengajuan', 'pkl_perusahaan', 'pkl_pengaturan'] as $tabel) {
            $this->forge->dropTable($tabel, true);
        }
    }
}
