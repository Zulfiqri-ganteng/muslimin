<?php

namespace App\Commands;

use App\Libraries\AbsensiWa;
use App\Libraries\Fcm;
use App\Libraries\NotifJadwal;
use App\Models\AbsensiBelumModel;
use App\Models\AbsensiGuruModel;
use App\Models\NotifLogModel;
use App\Models\NotifPerangkatModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Kirim notifikasi UJI dari server ke semua HP yang menyalakan "Terima
 * notifikasi di HP ini" — untuk memastikan notif benar-benar sampai.
 *
 *   phpm spark notif:uji              # notif uji biasa
 *   phpm spark notif:uji --contoh     # contoh notif JADWAL asli (jadwal terdekat sesuai aturan)
 *   phpm spark notif:uji --admin 1    # hanya HP milik admin #1
 *
 * Tidak mengganggu jadwal: dicatat sebagai jenis "uji" (kunci acak), jadi
 * notif jadwal sungguhan tetap terkirim pada waktunya.
 */
class NotifUji extends BaseCommand
{
    protected $group       = 'notif';
    protected $name        = 'notif:uji';
    protected $description = 'Kirim notifikasi uji ke semua HP terdaftar (cek notif sampai).';
    protected $usage       = 'notif:uji [--contoh] [--admin id]';
    protected $options     = [
        '--contoh' => 'Kirim contoh notif jadwal asli (jadwal terdekat sesuai aturan admin).',
        '--admin'  => 'Hanya HP milik admin ini.',
    ];

    public function run(array $params)
    {
        if (! Fcm::siap()) {
            CLI::error('Firebase belum dipasang (FCM_KEY_PATH). Jalankan: phpm spark notif:cek');

            return EXIT_ERROR;
        }
        $hanya  = (int) ($params['admin'] ?? CLI::getOption('admin') ?? 0);
        $contoh = array_key_exists('contoh', $params) || CLI::getOption('contoh') !== null;

        $penerima = (new NotifPerangkatModel())->penerimaPerAdmin();
        if ($hanya > 0) {
            $penerima = array_intersect_key($penerima, [$hanya => true]);
        }
        if ($penerima === []) {
            CLI::error('Belum ada HP terdaftar. Di aplikasi: menu Absensi Guru → Notifikasi Jadwal → nyalakan "Terima notifikasi di HP ini".');

            return EXIT_ERROR;
        }

        $log = new NotifLogModel();
        foreach ($penerima as $adminId => $perangkat) {
            [$judul, $isi, $data] = $contoh
                ? $this->contohJadwal($adminId)
                : ['Tes notifikasi dari server', 'Notifikasi jadwal guru sudah tersambung. ' . date('H:i'), ['jenis' => 'uji', 'tanggal' => date('Y-m-d')]];

            $k = NotifJadwal::kirimKePerangkat($perangkat, $judul, $isi, $data);
            $log->klaim([
                'admin_id' => $adminId, 'jenis' => 'uji', 'kunci' => 'uji:' . $adminId . ':' . bin2hex(random_bytes(8)),
                'tanggal'  => date('Y-m-d'), 'slot' => date('H:i:s'), 'judul' => mb_substr($judul, 0, 255), 'isi' => $isi,
                'data'     => $data, 'jml_perangkat' => count($perangkat), 'berhasil' => $k['berhasil'], 'gagal' => $k['gagal'],
                'galat'    => $k['galat'] !== [] ? implode("\n", $k['galat']) : null,
            ]);

            CLI::write("Admin #{$adminId}: {$judul}", 'yellow');
            CLI::write('  ' . str_replace("\n", "\n  ", $isi));
            CLI::write("  → {$k['berhasil']} HP berhasil, {$k['gagal']} gagal", $k['gagal'] === 0 ? 'green' : 'red');
            foreach ($k['galat'] as $g) {
                CLI::write('    ' . $g, 'red');
            }
        }

        return EXIT_SUCCESS;
    }

    /**
     * Notif jadwal terdekat (hari ini atau s/d 7 hari ke depan) sesuai aturan
     * admin — formatnya persis notif sungguhan. Tanpa aturan → contoh umum.
     *
     * @return array{0:string,1:string,2:array}
     */
    private function contohJadwal(int $adminId): array
    {
        for ($i = 0; $i < 8; $i++) {
            $tanggal = date('Y-m-d', strtotime('+' . $i . ' day'));
            NotifJadwal::lupakan();
            $r = NotifJadwal::rencana($adminId, $tanggal);
            foreach ($r['grup'] as $g) {
                if ($i === 0 && $g['kirim_ts'] < time()) {
                    continue; // hari ini: ambil yang belum lewat
                }
                $p = NotifJadwal::susunPesan(
                    $g['item'],
                    (new AbsensiGuruModel())->forDate($tanggal),
                    (new AbsensiBelumModel())->forDate($tanggal)
                );

                return [
                    '[CONTOH] ' . $p['judul'],
                    $p['isi'] . "\n(jadwal " . AbsensiWa::tanggalIndo($tanggal) . ', notif asli dikirim ' . $g['slot'] . ')',
                    ['jenis' => 'jadwal', 'tanggal' => $tanggal, 'shift' => $g['item'][0]['shift'], 'slot' => $g['slot']],
                ];
            }
        }

        return [
            '[CONTOH] Jadwal masuk 07:00 · KBM pagi · 2 guru',
            "Belum ada aturan aktif yang cocok 7 hari ke depan.\nBuat aturan di menu Notifikasi Jadwal.",
            ['jenis' => 'uji', 'tanggal' => date('Y-m-d')],
        ];
    }
}
