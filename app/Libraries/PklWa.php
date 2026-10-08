<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\PklPengajuanModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Kabar WhatsApp MANUAL ke siswa setelah suratnya diterbitkan: sistem hanya menyiapkan nomor, pesan, dan tautan
 * wa.me — staf yang menekan tombol dan mengirim dari WhatsApp-nya sendiri (tanpa gateway, tanpa biaya, tanpa risiko
 * nomor sekolah diblokir). Sistem hanya tahu surat sudah DITERBITKAN/diunduh, bukan sudah dicetak di kertas, jadi
 * redaksi pesan: "sudah diterbitkan dan siap diambil".
 *
 * Nomor = HP yang siswa isi sendiri di form PKL (pkl_anggota.hp, wajib 08…); cadangan: no_hp di Master Siswa.
 * Pesan sengaja ringkas dan TANPA rincian uang. Templat bisa diubah di Pengaturan PKL (kolom wa_pesan).
 */
final class PklWa
{
    public const PESAN_BAWAAN = 'Halo {nama}, surat izin PKL kamu untuk {perusahaan} (No. {nomor_surat}) sudah diterbitkan dan siap diambil di Tata Usaha {sekolah}. Terima kasih.';
    public const TOKEN = ['{nama}', '{kelas}', '{perusahaan}', '{nomor_surat}', '{tanggal_surat}', '{sekolah}'];
    public const MAKS_PESAN = 600;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** "0812-3456-7890" / "+62812…" → "62812…" untuk wa.me; null bila bukan HP yang sah. */
    public static function nomorWa(string $hp): ?string
    {
        $n = IsianBantu::telepon($hp);

        return IsianBantu::hpSah($n) ? '62' . substr($n, 1) : null;
    }

    /** Pesan galat untuk templat, atau null bila sah. */
    public static function periksaPesan(string $pesan): ?string
    {
        $pesan = trim($pesan);
        if ($pesan === '' || mb_strlen($pesan) > self::MAKS_PESAN) {
            return 'Pesan WhatsApp wajib diisi (maksimal ' . self::MAKS_PESAN . ' huruf).';
        }
        preg_match_all('/\{[^}]*\}/', $pesan, $m);
        foreach ($m[0] as $t) {
            if (! in_array($t, self::TOKEN, true)) {
                return 'Penanda ' . $t . ' tidak dikenal. Yang tersedia: ' . implode(' ', self::TOKEN) . '.';
            }
        }

        return null;
    }

    /** @param array<string, string> $nilai kunci tanpa kurung kurawal */
    public static function isi(string $templat, array $nilai): string
    {
        $peta = [];
        foreach ($nilai as $k => $v) {
            $peta['{' . $k . '}'] = $v;
        }

        return trim((string) preg_replace('/[ \t]+/', ' ', strtr($templat, $peta)));
    }

    /**
     * Daftar siswa yang perlu dikabari untuk ajuan-ajuan (hanya yang SUDAH punya surat bernomor).
     *
     * @param list<int>            $ajuanIds
     * @param array<string, mixed> $p        pkl_pengaturan
     * @param array<string, mixed> $setting  settings sekolah
     *
     * @return list<array<string,mixed>>
     */
    public function daftar(array $ajuanIds, array $p, array $setting): array
    {
        $ajuanIds = array_values(array_unique(array_filter(array_map('intval', $ajuanIds))));
        if ($ajuanIds === []) {
            return [];
        }
        $templat = trim((string) ($p['wa_pesan'] ?? '')) !== '' ? (string) $p['wa_pesan'] : self::PESAN_BAWAAN;
        $model = new PklPengajuanModel();
        $out = [];
        $surat = [];
        foreach ($this->db->table('pkl_surat')->whereIn('pengajuan_id', $ajuanIds)->get()->getResultArray() as $r) {
            $surat[(int) $r['pengajuan_id']] = $r;
        }
        foreach ($this->db->table('pkl_pengajuan')->whereIn('id', $ajuanIds)->where('status', 'disetujui')->orderBy('id', 'ASC')->get()->getResultArray() as $a) {
            $id = (int) $a['id'];
            if (! isset($surat[$id])) {
                continue;
            }
            foreach ($model->anggotaDetail($id) as $s) {
                $hp = trim((string) ($s['hp'] ?? '')) !== '' ? (string) $s['hp'] : (string) ($s['hp_master'] ?? '');
                $wa = $hp !== '' ? self::nomorWa($hp) : null;
                $pesan = self::isi($templat, [
                    'nama' => IsianBantu::judul((string) $s['nama']), 'kelas' => (string) ($s['nama_kelas'] ?? ''), 'perusahaan' => (string) $a['perusahaan_nama'],
                    'nomor_surat' => (string) $surat[$id]['nomor'], 'tanggal_surat' => IsianBantu::tanggalIndo($surat[$id]['tanggal_surat']),
                    'sekolah' => (string) ($setting['school_name'] ?? ''),
                ]);
                $dikabari = $this->db->table('pkl_anggota')->select('dikabari_at, dikabari_oleh')->where('id', (int) $s['id'])->get()->getRowArray() ?? [];
                $out[] = [
                    'ajuan_id' => $id, 'kode' => PklPengajuanModel::kode($id), 'siswa_id' => (int) $s['siswa_id'], 'nama' => (string) $s['nama'], 'kelas' => (string) ($s['nama_kelas'] ?? ''),
                    'peran' => (string) $s['peran'], 'perusahaan' => (string) $a['perusahaan_nama'], 'nomor_surat' => (string) $surat[$id]['nomor'],
                    'hp' => $hp, 'wa' => $wa, 'url' => $wa !== null ? 'https://wa.me/' . $wa . '?text=' . rawurlencode($pesan) : null,
                    'alasan_tidak' => $wa === null ? ($hp === '' ? 'Nomor HP kosong' : 'Nomor HP tidak valid') : null,
                    'pesan' => $pesan, 'dikabari_at' => $dikabari['dikabari_at'] ?? null, 'dikabari_oleh' => $dikabari['dikabari_oleh'] ?? null,
                ];
            }
        }

        return $out;
    }

    /**
     * Tandai siswa sudah dikabari (dipanggil setelah staf menekan tombol WhatsApp).
     *
     * @param array<string, mixed> $konteks
     */
    public function tandai(int $ajuanId, int $siswaId, array $konteks): bool
    {
        $a = $this->db->table('pkl_anggota a')->select('a.id, s.nama')->join('siswa s', 's.id = a.siswa_id')
            ->join('pkl_pengajuan p', "p.id = a.pengajuan_id AND p.status = 'disetujui'")
            ->where('a.pengajuan_id', $ajuanId)->where('a.siswa_id', $siswaId)->get()->getRowArray();
        if ($a === null) {
            return false;
        }
        $oleh = mb_substr((string) ($konteks['oleh'] ?? 'Staf'), 0, 150);
        $this->db->table('pkl_anggota')->where('id', (int) $a['id'])->update(['dikabari_at' => date('Y-m-d H:i:s'), 'dikabari_oleh' => $oleh]);
        (new PklAjuan($this->db))->catat($ajuanId, 'kabari', $konteks, 'Dikabari lewat WhatsApp: ' . $a['nama']);
        (new AuditModel())->record('update', 'pkl_anggota', (int) $a['id'], mb_substr('Siswa dikabari lewat WhatsApp: ' . $a['nama'] . ' (PKL ' . PklPengajuanModel::kode($ajuanId) . ')', 0, 255));

        return true;
    }
}
