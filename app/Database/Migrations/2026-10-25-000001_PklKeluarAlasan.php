<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Revisi klien PKL 2026-10-20 (docs/DESAIN-PKL.md bagian "Siswa dikeluarkan, ditolak perusahaan, template alasan"):
 *
 *   - `pkl_alasan`          — TEMPLATE ALASAN yang dikelola sendiri oleh pemegang hak "Kelola template alasan" (bawaan Waka Hubin).
 *                             Jenis: kembalikan | tolak | keluar | lapor. Isi awal = "Bank Kalimat Alasan" dari sekolah
 *                             (Alasan_PKL_Bina_Nusa.docx: 7 alasan dikembalikan + 5 alasan ditolak) + alasan keluar & laporan.
 *   - `pkl_anggota_keluar`  — JEJAK siswa yang dikeluarkan dari ajuan (ditolak perusahaan, administrasi belum lunas, pindah kelompok,
 *                             mengundurkan diri, lainnya). Baris pkl_anggota-nya dihapus (siswa bebas mengajukan lagi), salinan
 *                             peran/kelas/HP + alasan + siapa/kapan disimpan di sini → riwayat siswa tidak hilang.
 *   - pkl_pengajuan.tolak_sumber   — 'perusahaan' bila ajuan ditolak karena perusahaan tidak menerima (NULL = keputusan sekolah).
 *   - pkl_pengajuan.catatan_staf, pkl_riwayat.catatan, pkl_lapor.alasan → 1000 huruf (kalimat template sampai ±230 huruf,
 *                             staf boleh menggabungkan beberapa).
 *   - pkl_lapor.hasil ('semua' | 'sebagian' | 'ditolak') + pkl_lapor.diterima (JSON id siswa yang diterima, untuk 'sebagian');
 *                             tanggal_mulai/selesai boleh kosong (laporan "semua ditolak perusahaan" tidak punya periode).
 *   - pkl_pengaturan.wa_pesan_keluar — templat WhatsApp untuk siswa yang dikeluarkan / ajuannya ditolak perusahaan.
 *   - tabel `peran`: Waka Hubin + hak 'alasan' (Kelola template alasan) — hanya bila isian haknya sah; Admin selalu boleh.
 *
 * Idempoten. Tidak menyentuh data ajuan/siswa yang sudah ada. down() mengembalikan struktur (teks alasan yang lebih panjang dari
 * 255 huruf dipotong lebih dulu agar kolom bisa diperkecil).
 */
class PklKeluarAlasan extends Migration
{
    protected $attr = ['ENGINE' => 'InnoDB'];

    private const HAK = 'alasan';

    /** jenis => [[judul, isi], …] — isi awal template. */
    private const TEMPLATE = [
        'kembalikan' => [
            ['Nama perusahaan salah', 'Nama perusahaan yang dicantumkan belum sesuai. Silakan periksa dan perbaiki nama perusahaan sesuai dengan nama resmi perusahaan/instansi yang dituju.'],
            ['Alamat perusahaan kurang lengkap', 'Alamat perusahaan/instansi yang dicantumkan belum lengkap. Silakan lengkapi nama jalan, nomor bangunan, RT/RW, kelurahan, kecamatan, serta kota/kabupaten sesuai alamat perusahaan.'],
            ['Nomor telepon tidak sesuai', 'Nomor telepon perusahaan/instansi yang dicantumkan belum sesuai atau tidak dapat dihubungi. Silakan periksa dan masukkan nomor telepon yang benar dan aktif.'],
            ['Data perusahaan tidak sesuai', 'Terdapat ketidaksesuaian pada data perusahaan/instansi yang diajukan. Silakan periksa kembali seluruh informasi dan lakukan perbaikan sesuai data yang sebenarnya.'],
            ['Alamat tidak sesuai nama perusahaan', 'Alamat yang dicantumkan belum sesuai dengan lokasi perusahaan/instansi yang dituju. Silakan pastikan nama dan alamat perusahaan sudah benar.'],
            ['Data pengajuan belum lengkap', 'Data pengajuan PKL masih belum lengkap. Silakan periksa kembali seluruh kolom yang tersedia dan lengkapi informasi yang diperlukan sebelum mengajukan ulang.'],
            ['Penulisan data perlu diperbaiki', 'Terdapat kesalahan penulisan pada informasi perusahaan/instansi. Silakan perbaiki penulisan nama dan informasi lainnya agar sesuai dengan data resmi.'],
        ],
        'tolak' => [
            ['Administrasi belum lunas', 'Pengajuan PKL belum dapat disetujui karena masih terdapat kewajiban administrasi sekolah yang belum diselesaikan. Silakan melakukan konfirmasi dan penyelesaian administrasi melalui bagian Tata Usaha (TU) terlebih dahulu.'],
            ['Tunggakan pembayaran', 'Pengajuan PKL belum dapat disetujui karena masih terdapat tunggakan pembayaran administrasi sekolah. Silakan menghubungi bagian Tata Usaha (TU) untuk melakukan pengecekan dan penyelesaian kewajiban administrasi.'],
            ['Pembayaran belum terverifikasi', 'Pengajuan PKL belum dapat disetujui karena pembayaran administrasi belum terverifikasi oleh pihak sekolah. Silakan melakukan konfirmasi kepada bagian Tata Usaha (TU) dengan membawa atau menunjukkan bukti pembayaran.'],
            ['Administrasi perlu dikonfirmasi', 'Pengajuan PKL belum dapat diproses lebih lanjut karena status administrasi sekolah masih memerlukan konfirmasi. Silakan menghubungi bagian Tata Usaha (TU) untuk memastikan status pembayaran.'],
            ['Belum memenuhi persyaratan', 'Pengajuan PKL belum dapat disetujui karena persyaratan yang ditetapkan oleh pihak sekolah belum terpenuhi. Silakan menghubungi petugas PKL untuk memperoleh informasi lebih lanjut.'],
        ],
        'keluar' => [
            ['Ditolak perusahaan (kuota penuh)', 'Perusahaan/instansi tidak dapat menerima siswa ini karena kuota peserta PKL sudah terpenuhi. Silakan mengajukan tempat PKL lain melalui form pengajuan PKL.'],
            ['Ditolak perusahaan', 'Perusahaan/instansi menyatakan tidak menerima siswa ini untuk PKL. Silakan mengajukan tempat PKL lain melalui form pengajuan PKL.'],
            ['Administrasi belum lunas', 'Siswa ini dikeluarkan dari ajuan karena masih terdapat kewajiban administrasi sekolah yang belum diselesaikan. Setelah administrasi diselesaikan melalui bagian Tata Usaha (TU), silakan mengajukan kembali.'],
            ['Pindah ke kelompok lain', 'Siswa ini dipindahkan ke kelompok/ajuan lain di tempat PKL yang sama agar menjadi satu surat pengajuan.'],
            ['Mengundurkan diri', 'Siswa mengundurkan diri dari tempat PKL ini atas permintaan sendiri. Silakan mengajukan tempat PKL lain melalui form pengajuan PKL.'],
        ],
        'lapor' => [
            ['Tanggal PKL keliru', 'Tanggal mulai/selesai PKL belum sesuai dengan kesepakatan perusahaan. Silakan periksa kembali lalu kirim ulang laporan.'],
            ['Siswa yang diterima belum sesuai', 'Daftar siswa yang diterima perusahaan belum sesuai. Silakan periksa kembali siapa saja yang diterima, lalu kirim ulang laporan.'],
            ['Alamat perusahaan kurang lengkap', 'Alamat perusahaan yang dilaporkan belum lengkap. Silakan lengkapi nama jalan, nomor bangunan, kelurahan, kecamatan, serta kota/kabupaten, lalu kirim ulang laporan.'],
        ],
    ];

    public function up()
    {
        $now = date('Y-m-d H:i:s');

        // ---------- 1. Template alasan ----------
        if (! $this->db->tableExists('pkl_alasan')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'jenis'       => ['type' => 'VARCHAR', 'constraint' => 20],
                'judul'       => ['type' => 'VARCHAR', 'constraint' => 80],
                'isi'         => ['type' => 'VARCHAR', 'constraint' => 1000],
                'urut'        => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'diubah_oleh' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['jenis', 'urut']);
            $this->forge->createTable('pkl_alasan', true, $this->attr);
        }
        if ($this->db->table('pkl_alasan')->countAllResults() === 0) {
            $baris = [];
            foreach (self::TEMPLATE as $jenis => $daftar) {
                foreach ($daftar as $i => [$judul, $isi]) {
                    $baris[] = ['jenis' => $jenis, 'judul' => $judul, 'isi' => $isi, 'urut' => $i + 1, 'diubah_oleh' => 'Sistem (isi awal)', 'created_at' => $now, 'updated_at' => $now];
                }
            }
            $this->db->table('pkl_alasan')->insertBatch($baris);
        }

        // ---------- 2. Jejak siswa yang dikeluarkan dari ajuan ----------
        if (! $this->db->tableExists('pkl_anggota_keluar')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'pengajuan_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'siswa_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'kelas_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true], // kelas SAAT mengajukan
                'peran'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'teman'],         // peran di ajuan saat dikeluarkan
                'hp'           => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
                'jenis'        => ['type' => 'VARCHAR', 'constraint' => 20],                               // ditolak_perusahaan | administrasi | pindah | mundur | lainnya
                'alasan'       => ['type' => 'VARCHAR', 'constraint' => 1000],
                'status_ajuan' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],               // status ajuan saat itu
                'sumber'       => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'staf'],          // staf | lapor (laporan siswa)
                'oleh'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'admin_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'peran_staf'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'ip_address'   => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'kabar_at'     => ['type' => 'DATETIME', 'null' => true],
                'kabar_oleh'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('siswa_id');
            $this->forge->addKey('pengajuan_id');
            $this->forge->addForeignKey('pengajuan_id', 'pkl_pengajuan', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('siswa_id', 'siswa', 'id', '', 'CASCADE');
            $this->forge->addForeignKey('kelas_id', 'kelas', 'id', '', 'SET NULL');
            $this->forge->createTable('pkl_anggota_keluar', true, $this->attr);
        }

        // ---------- 3. Kolom baru / diperlebar ----------
        if (! $this->db->fieldExists('tolak_sumber', 'pkl_pengajuan')) {
            $this->forge->addColumn('pkl_pengajuan', ['tolak_sumber' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true, 'after' => 'catatan_staf']]);
        }
        $this->forge->modifyColumn('pkl_pengajuan', ['catatan_staf' => ['name' => 'catatan_staf', 'type' => 'VARCHAR', 'constraint' => 1000, 'null' => true]]);
        $this->forge->modifyColumn('pkl_riwayat', ['catatan' => ['name' => 'catatan', 'type' => 'VARCHAR', 'constraint' => 1000, 'null' => true]]);

        if ($this->db->tableExists('pkl_lapor')) {
            $kolom = [];
            if (! $this->db->fieldExists('hasil', 'pkl_lapor')) {
                $kolom['hasil'] = ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'semua', 'after' => 'hp'];
            }
            if (! $this->db->fieldExists('diterima', 'pkl_lapor')) {
                $kolom['diterima'] = ['type' => 'TEXT', 'null' => true, 'after' => 'hasil'];
            }
            if ($kolom !== []) {
                $this->forge->addColumn('pkl_lapor', $kolom);
            }
            $this->forge->modifyColumn('pkl_lapor', [
                'tanggal_mulai'   => ['name' => 'tanggal_mulai', 'type' => 'DATE', 'null' => true],
                'tanggal_selesai' => ['name' => 'tanggal_selesai', 'type' => 'DATE', 'null' => true],
                'alasan'          => ['name' => 'alasan', 'type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
            ]);
        }

        if (! $this->db->fieldExists('wa_pesan_keluar', 'pkl_pengaturan')) {
            $this->forge->addColumn('pkl_pengaturan', ['wa_pesan_keluar' => ['type' => 'VARCHAR', 'constraint' => 600, 'null' => true]]);
        }

        // ---------- 4. Hak "Kelola template alasan" untuk Waka Hubin ----------
        if ($this->aturHak('hubin', true)) {
            $this->audit('Hak baru "Kelola template alasan" diberikan ke Waka Hubin lewat migrasi (revisi PKL 2026-10-20)');
        }
    }

    public function down()
    {
        $this->aturHak('hubin', false);
        if ($this->db->fieldExists('wa_pesan_keluar', 'pkl_pengaturan')) {
            $this->forge->dropColumn('pkl_pengaturan', 'wa_pesan_keluar');
        }
        if ($this->db->tableExists('pkl_lapor')) {
            foreach (['hasil', 'diterima'] as $k) {
                if ($this->db->fieldExists($k, 'pkl_lapor')) {
                    $this->forge->dropColumn('pkl_lapor', $k);
                }
            }
            // Laporan "semua ditolak" tidak punya tanggal: tidak bisa dikembalikan ke kolom wajib → dihapus.
            $this->db->table('pkl_lapor')->where('tanggal_mulai', null)->orWhere('tanggal_selesai', null)->delete();
            $this->db->query('UPDATE pkl_lapor SET alasan = LEFT(alasan, 255) WHERE CHAR_LENGTH(alasan) > 255');
            $this->forge->modifyColumn('pkl_lapor', [
                'tanggal_mulai'   => ['name' => 'tanggal_mulai', 'type' => 'DATE', 'null' => false],
                'tanggal_selesai' => ['name' => 'tanggal_selesai', 'type' => 'DATE', 'null' => false],
                'alasan'          => ['name' => 'alasan', 'type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ]);
        }
        $this->db->query('UPDATE pkl_riwayat SET catatan = LEFT(catatan, 255) WHERE CHAR_LENGTH(catatan) > 255');
        $this->forge->modifyColumn('pkl_riwayat', ['catatan' => ['name' => 'catatan', 'type' => 'VARCHAR', 'constraint' => 255, 'null' => true]]);
        $this->db->query('UPDATE pkl_pengajuan SET catatan_staf = LEFT(catatan_staf, 255) WHERE CHAR_LENGTH(catatan_staf) > 255');
        $this->forge->modifyColumn('pkl_pengajuan', ['catatan_staf' => ['name' => 'catatan_staf', 'type' => 'VARCHAR', 'constraint' => 255, 'null' => true]]);
        if ($this->db->fieldExists('tolak_sumber', 'pkl_pengajuan')) {
            $this->forge->dropColumn('pkl_pengajuan', 'tolak_sumber');
        }
        $this->forge->dropTable('pkl_anggota_keluar', true);
        $this->forge->dropTable('pkl_alasan', true);
    }

    /** Beri ($beri = true) atau cabut hak 'alasan' dari satu peran. true bila ada yang berubah. */
    private function aturHak(string $kode, bool $beri): bool
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
            return false; // isian rusak: dibiarkan (sistem memakai aturan bawaan untuk peran ini)
        }
        $hak = array_values(array_map('strval', $hak));
        if (in_array(self::HAK, $hak, true) === $beri) {
            return false;
        }
        $hak = $beri ? array_merge($hak, [self::HAK]) : array_values(array_diff($hak, [self::HAK]));
        $this->db->table('peran')->where('kode', $kode)->update(['hak' => json_encode($hak), 'updated_at' => date('Y-m-d H:i:s')]);

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
