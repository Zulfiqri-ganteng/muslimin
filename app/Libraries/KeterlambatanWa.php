<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\KeterlambatanModel;
use App\Models\SettingModel;

/**
 * Kabar WhatsApp MANUAL ke orang tua siswa yang terlambat (menu Guru Piket → Catat Keterlambatan). Pola sama dengan kabar PKL
 * (Libraries\PklWa, SuratWa): sistem menyiapkan nomor, pesan, dan tautan wa.me — guru piket menekan tombol lalu mengirim dari
 * WhatsApp-nya sendiri (tanpa gateway, tanpa biaya, nomor sekolah tak berisiko diblokir). Jejak: keterlambatan_siswa.dikabari_at/
 * dikabari_oleh + Audit Log.
 *
 * Nomor tujuan = HP orang tua di Data Siswa (kolom Telepon orang tua, cadangan HP wali); isian berisi beberapa nomor ("0812… / 0857…")
 * memakai nomor HP sah yang PERTAMA. Nomor HP siswa sendiri TIDAK dipakai (pesannya untuk orang tua).
 * Data siswa banyak yang belum punya nomor orang tua (± 95% saat fitur dibuat) → guru piket bisa MENGISI nomor dari halaman Catat:
 * disimpan ke Data Siswa hanya di kolom yang masih KOSONG (Telepon orang tua, lalu HP wali) — nomor yang sudah ada tidak pernah ditimpa.
 * Siswa BELUM TERDAFTAR (catatan manual): tidak ada Data Siswa → nomor orang tua disimpan di catatan itu sendiri (keterlambatan_siswa.
 * hp_ortu, sumber "isian guru piket"); saat catatannya ditautkan ke Data Siswa, nomor itu ikut diisikan ke kolom yang masih kosong.
 * Templat: settings.wa_pesan_terlambat (kosong = PESAN_BAWAAN), diubah Admin.
 */
final class KeterlambatanWa
{
    /** wa.sumber untuk nomor yang diisi guru piket pada catatan siswa belum terdaftar. */
    public const SUMBER_ISIAN = 'isian guru piket';

    public const PESAN_BAWAAN = 'Yth. Bapak/Ibu orang tua/wali dari {nama} ({kelas}). Kami informasikan bahwa ananda datang terlambat ke sekolah pada {waktu}. Mohon bimbingannya agar ananda dapat datang tepat waktu. Terima kasih. Guru Piket {sekolah}';
    public const TOKEN = ['{nama}', '{kelas}', '{tanggal}', '{jam}', '{waktu}', '{sanksi}', '{keterangan}', '{guru_piket}', '{sekolah}'];

    private static ?bool $kolomAda = null;

    /** Migrasi KeterlambatanSiswa (kolom settings.wa_pesan_terlambat) sudah jalan? */
    public static function kolomAda(): bool
    {
        if (self::$kolomAda === null) {
            try {
                self::$kolomAda = db_connect()->fieldExists('wa_pesan_terlambat', 'settings');
            } catch (\Throwable $e) {
                self::$kolomAda = false;
            }
        }

        return self::$kolomAda;
    }

    /** Templat yang berlaku (isian Admin, atau bawaan). */
    public static function templat(): string
    {
        $t = self::kolomAda() ? trim((string) ((new SettingModel())->get()['wa_pesan_terlambat'] ?? '')) : '';

        return $t !== '' ? $t : self::PESAN_BAWAAN;
    }

    /** Simpan templat (sama dengan bawaan / kosong → disimpan kosong = ikut bawaan). Galat atau null bila tersimpan. */
    public static function simpanTemplat(string $pesan): ?string
    {
        if (! self::kolomAda()) {
            return 'Fitur pesan WhatsApp belum aktif di server ini (migrasi belum dijalankan).';
        }
        $pesan = trim(str_replace("\r\n", "\n", $pesan));
        if (mb_strlen($pesan) > PklWa::MAKS_PESAN) {
            return 'Pesan WhatsApp terlalu panjang (maksimal ' . PklWa::MAKS_PESAN . ' huruf).';
        }
        preg_match_all('/\{[^}]*\}/', $pesan, $m);
        foreach ($m[0] as $t) {
            if (! in_array($t, self::TOKEN, true)) {
                return 'Penanda ' . $t . ' tidak dikenal. Yang tersedia: ' . implode(' ', self::TOKEN) . '.';
            }
        }
        (new SettingModel())->store(['wa_pesan_terlambat' => ($pesan === '' || $pesan === self::PESAN_BAWAAN) ? null : $pesan]);
        (new AuditModel())->record('update', 'settings', 1, 'Ubah templat pesan WhatsApp keterlambatan siswa');

        return null;
    }

    /** Nomor HP sah PERTAMA dalam isian (boleh beberapa nomor dipisah "/", ",", ";", "atau", "dan") → "08…"; null bila tidak ada. */
    public static function hpPertama(?string $teks): ?string
    {
        foreach (preg_split('/\s*(?:[\/,;|]|\batau\b|\bdan\b)\s*/iu', trim((string) $teks)) ?: [] as $bagian) {
            $n = IsianBantu::telepon($bagian);
            if (IsianBantu::hpSah($n)) {
                return $n;
            }
        }

        return null;
    }

    /**
     * Nomor orang tua dari data siswa: Telepon orang tua, cadangan HP wali.
     *
     * @return array{hp: ?string, sumber: ?string, ada_isian: bool}
     */
    public static function nomorOrtu(array $siswa): array
    {
        foreach (['ortu_telepon' => 'orang tua', 'no_hp_wali' => 'wali'] as $kolom => $sumber) {
            $hp = self::hpPertama((string) ($siswa[$kolom] ?? ''));
            if ($hp !== null) {
                return ['hp' => $hp, 'sumber' => $sumber, 'ada_isian' => true];
            }
        }
        $ada = trim((string) ($siswa['ortu_telepon'] ?? '')) !== '' || trim((string) ($siswa['no_hp_wali'] ?? '')) !== '';

        return ['hp' => null, 'sumber' => null, 'ada_isian' => $ada];
    }

    /**
     * Bahan tombol WA satu catatan (baris Keterlambatan::daftar(), memuat ortu_telepon & no_hp_wali).
     *
     * @return array{hp: ?string, sumber: ?string, url: ?string, pesan: string, alasan_tidak: ?string}
     */
    public static function kabar(array $row, string $templat, string $sekolah): array
    {
        $manual = Keterlambatan::manual($row);
        if ($manual) {
            $hp = self::hpPertama((string) ($row['hp_ortu'] ?? ''));
            $no = ['hp' => $hp, 'sumber' => $hp !== null ? self::SUMBER_ISIAN : null, 'ada_isian' => trim((string) ($row['hp_ortu'] ?? '')) !== ''];
        } else {
            $no = self::nomorOrtu($row);
        }
        $jam  = $row['jam'] !== null && $row['jam'] !== '' ? str_replace(':', '.', substr((string) $row['jam'], 0, 5)) : '';
        $tgl  = Keterlambatan::hariTanggal((string) $row['tanggal']);
        $pesan = PklWa::isi($templat, [
            'nama'       => IsianBantu::judul((string) $row['nama_siswa']),
            'kelas'      => (string) ($row['nama_kelas'] ?? ''),
            'tanggal'    => $tgl,
            'jam'        => $jam,
            'waktu'      => $tgl . ($jam !== '' ? ' pukul ' . $jam : ''),
            'sanksi'     => (string) ($row['sanksi'] ?? ''),
            'keterangan' => (string) ($row['keterangan'] ?? ''),
            'guru_piket' => (string) ($row['guru_piket'] ?? ''),
            'sekolah'    => $sekolah,
        ]);
        $wa = $no['hp'] !== null ? PklWa::nomorWa($no['hp']) : null;

        return [
            'hp'           => $no['hp'],
            'sumber'       => $no['sumber'],
            'url'          => $wa !== null ? 'https://wa.me/' . $wa . '?text=' . rawurlencode($pesan) : null,
            'pesan'        => $pesan,
            'alasan_tidak' => $wa !== null ? null : (! $manual && $row['siswa_id'] === null ? 'Data siswa sudah tidak ada' : ($no['ada_isian'] ? 'Nomor HP orang tua tidak valid' : 'Nomor HP orang tua belum ada')),
        ];
    }

    /** Tombol "Isi no. ortu" berlaku untuk catatan ini? (siswa terdaftar, atau siswa belum terdaftar yang dicatat manual) */
    public static function bisaIsiHp(array $row): bool
    {
        return $row['siswa_id'] !== null || Keterlambatan::manual($row);
    }

    /**
     * Tandai catatan sudah dikabari (dipanggil saat guru piket menekan tombol WhatsApp).
     *
     * @return array{ok: bool, waktu?: string, oleh?: string, pesan?: string}
     */
    public function tandai(int $id, string $oleh, string $saluran = 'web'): array
    {
        $row = (new KeterlambatanModel())->find($id);
        if ($row === null) {
            return ['ok' => false, 'kode' => 404, 'pesan' => 'Catatan keterlambatan tidak ditemukan. Muat ulang halaman.'];
        }
        $waktu = date('Y-m-d H:i:s');
        $oleh  = mb_substr($oleh !== '' ? $oleh : 'Guru piket', 0, 150);
        (new KeterlambatanModel())->update($id, ['dikabari_at' => $waktu, 'dikabari_oleh' => $oleh]);
        (new AuditModel())->record('update', 'keterlambatan_siswa', $id, mb_substr('Orang tua dikabari lewat WhatsApp: ' . $row['nama_siswa'] . ', ' . $row['tanggal'] . ($saluran === 'aplikasi' ? ' (via aplikasi)' : ''), 0, 255));

        return ['ok' => true, 'waktu' => $waktu, 'oleh' => $oleh];
    }

    /**
     * Isi nomor HP orang tua siswa pada catatan ini ke Data Siswa — HANYA kolom yang masih kosong (Telepon orang tua, lalu HP wali).
     * Catatan siswa belum terdaftar: nomor disimpan di catatan itu (hp_ortu) bila masih kosong; menggantinya lewat Ubah.
     *
     * @return array{ok: bool, pesan: string}
     */
    public function isiHp(int $id, string $hp, string $saluran = 'web'): array
    {
        $row = (new KeterlambatanModel())->find($id);
        if ($row === null || ! self::bisaIsiHp($row)) {
            return ['ok' => false, 'kode' => 404, 'pesan' => 'Catatan atau data siswanya tidak ditemukan.'];
        }
        $hp = trim($hp);
        $n  = IsianBantu::telepon($hp);
        if (! IsianBantu::teleponMurni($hp) || ! IsianBantu::hpSah($n)) {
            return ['ok' => false, 'kode' => 422, 'pesan' => 'Nomor HP tidak valid. Tulis nomor WhatsApp yang diawali 08, mis. 0812 3456 7890.'];
        }
        if (Keterlambatan::manual($row)) {
            if (self::hpPertama((string) ($row['hp_ortu'] ?? '')) !== null) {
                return ['ok' => false, 'kode' => 409, 'pesan' => 'Catatan ' . $row['nama_siswa'] . ' sudah berisi nomor HP orang tua. Untuk menggantinya, pakai Ubah.'];
            }
            (new KeterlambatanModel())->update($id, ['hp_ortu' => $n]);
            (new AuditModel())->record('update', 'keterlambatan_siswa', $id, mb_substr('Nomor HP orang tua diisi (siswa belum terdaftar): ' . $row['nama_siswa'] . ', ' . $row['tanggal'] . ($saluran === 'aplikasi' ? ' (via aplikasi)' : ''), 0, 255));

            return ['ok' => true, 'pesan' => 'Nomor ' . $n . ' disimpan di catatan ' . $row['nama_siswa'] . ' (siswa belum terdaftar di Data Siswa). Tekan "Kabari ortu" untuk mengirim pesan.'];
        }
        $db    = db_connect();
        $siswa = $db->table('siswa')->select('id, nama, ortu_telepon, no_hp_wali')->where('id', (int) $row['siswa_id'])->get()->getRowArray();
        if ($siswa === null) {
            return ['ok' => false, 'kode' => 404, 'pesan' => 'Data siswa tidak ditemukan.'];
        }
        if (self::nomorOrtu($siswa)['hp'] !== null) {
            return ['ok' => false, 'kode' => 409, 'pesan' => 'Data siswa ' . $siswa['nama'] . ' sudah berisi nomor HP orang tua. Untuk menggantinya, ubah lewat Data Siswa.'];
        }
        $kolom = trim((string) ($siswa['ortu_telepon'] ?? '')) === '' ? 'ortu_telepon' : (trim((string) ($siswa['no_hp_wali'] ?? '')) === '' ? 'no_hp_wali' : null);
        if ($kolom === null) {
            return ['ok' => false, 'kode' => 409, 'pesan' => 'Kolom telepon orang tua & HP wali di Data Siswa sudah terisi (tetapi bukan nomor HP). Perbaiki lewat Data Siswa.'];
        }
        $db->table('siswa')->where('id', (int) $siswa['id'])->update([$kolom => $n, 'updated_at' => date('Y-m-d H:i:s')]);
        (new AuditModel())->record('update', 'siswa', (int) $siswa['id'], mb_substr(($kolom === 'ortu_telepon' ? 'Telepon orang tua' : 'HP wali') . ' diisi dari Catat Keterlambatan: ' . $siswa['nama'] . ($saluran === 'aplikasi' ? ' (via aplikasi)' : ''), 0, 255));

        return ['ok' => true, 'pesan' => 'Nomor ' . $n . ' disimpan ke Data Siswa ' . $siswa['nama'] . ' (' . ($kolom === 'ortu_telepon' ? 'Telepon orang tua' : 'HP wali') . '). Tekan "Kabari ortu" untuk mengirim pesan.'];
    }
}
