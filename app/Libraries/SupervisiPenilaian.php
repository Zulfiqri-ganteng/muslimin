<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\SupervisiJadwalModel;
use App\Models\SupervisiNilaiButirModel;
use App\Models\SupervisiNilaiModel;

/**
 * Penilaian supervisi (lembar penilaian satu kunjungan) — SATU sumber logika web (Admin\SupervisiPenilaian) & API (S4).
 *
 *   - Tiap butir diberi skor 1 = Kurang, 2 = Cukup, 3 = Baik, 4 = Sangat Baik, atau 0 = TIDAK DINILAI (tidak ikut dihitung);
 *     null = belum diisi (hanya boleh di draf).
 *   - Nilai instrumen = jumlah skor ÷ (jumlah butir dinilai × 4) × 100, dibulatkan 2 desimal. Nilai kunjungan = rata-rata nilai
 *     instrumennya (2 desimal). Predikat: ≥ 91 Amat Baik · ≥ 76 Baik · ≥ 61 Cukup · selain itu Kurang.
 *   - Per instrumen: kekuatan, kelemahan, rekomendasi (teks bebas) + tindak lanjut (boleh lebih dari satu).
 *   - Simpan DRAF: boleh belum lengkap, status jadwal tetap "dijadwalkan". SIMPAN & SELESAI: semua butir semua instrumen wajib diisi
 *     (skor atau tidak dinilai, minimal satu butir berskor per instrumen) → status "selesai" + nilai akhir & predikat di jadwal.
 *     Jadwal yang sudah selesai hanya bisa diubah dengan "selesai" lagi (tidak kembali menjadi draf). Jadwal batal tidak bisa dinilai.
 *   - Butir DISALIN saat pertama disimpan: instrumen yang diubah kemudian tidak mengubah lembar yang sudah ada. Tiap butir di lembar
 *     punya 'kunci' — "b{butir_id}" (belum pernah disimpan) atau "n{id baris nilai_butir}" (sudah tersimpan) — yang dikirim balik
 *     bersama skornya.
 * Hasil gagal membawa 'kode' 404|409|422.
 */
final class SupervisiPenilaian
{
    public const SKOR = [1 => 'Kurang', 2 => 'Cukup', 3 => 'Baik', 4 => 'Sangat Baik'];
    /** Batas bawah nilai → predikat (urut menurun). */
    public const PREDIKAT = [91 => 'Amat Baik', 76 => 'Baik', 61 => 'Cukup', 0 => 'Kurang'];
    public const TINDAK_LANJUT = [
        'individual'  => 'Pembinaan individual',
        'kelompok'    => 'Pembinaan kelompok/KKG-MGMP',
        'pelatihan'   => 'Pelatihan/workshop',
        'model'       => 'Contoh praktik baik (model)',
        'tidak_perlu' => 'Tidak perlu tindak lanjut',
    ];
    public const MAKS_TEKS    = 2000;
    public const MAKS_CATATAN = 255;

    private SupervisiNilaiModel $nilai;
    private SupervisiNilaiButirModel $nilaiButir;
    private string $saluran;

    /** @param string $saluran 'web' | 'aplikasi' (Audit Log diberi "(via aplikasi)"). */
    public function __construct(string $saluran = 'web')
    {
        $this->nilai      = new SupervisiNilaiModel();
        $this->nilaiButir = new SupervisiNilaiButirModel();
        $this->saluran    = $saluran;
    }

    // =================================================================
    // Hitung
    // =================================================================

    public static function predikat(?float $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }
        foreach (self::PREDIKAT as $batas => $label) {
            if (round($nilai, 2) >= $batas) {
                return $label;
            }
        }

        return 'Kurang';
    }

    /**
     * Ringkasan skor satu instrumen dari daftar skor (1–4, 0 = tidak dinilai, null = belum diisi).
     *
     * @param list<?int> $skor
     *
     * @return array{total: int, maks: int, nilai: ?float, predikat: ?string, dinilai: int, td: int, kosong: int, jumlah: int}
     */
    public static function hitung(array $skor): array
    {
        $total = 0;
        $dinilai = 0;
        $td = 0;
        $kosong = 0;
        foreach ($skor as $s) {
            if ($s === null) {
                $kosong++;
            } elseif ($s === 0) {
                $td++;
            } else {
                $total += $s;
                $dinilai++;
            }
        }
        $nilai = $dinilai > 0 ? round($total / ($dinilai * 4) * 100, 2) : null;

        return ['total' => $total, 'maks' => $dinilai * 4, 'nilai' => $nilai, 'predikat' => self::predikat($nilai),
            'dinilai' => $dinilai, 'td' => $td, 'kosong' => $kosong, 'jumlah' => count($skor)];
    }

    /** Rata-rata nilai instrumen (yang punya nilai), 2 desimal; null bila tidak ada. @param list<?float> $nilai */
    public static function rataRata(array $nilai): ?float
    {
        $ada = array_values(array_filter($nilai, static fn ($n): bool => $n !== null));

        return $ada === [] ? null : round(array_sum($ada) / count($ada), 2);
    }

    /** "68,18" */
    public static function teksNilai(mixed $n): string
    {
        return $n === null || $n === '' ? '—' : number_format((float) $n, 2, ',', '.');
    }

    // =================================================================
    // Lembar penilaian
    // =================================================================

    /**
     * Lembar penilaian satu kunjungan: identitas jadwal + tiap instrumennya (butir dengan skor tersimpan bila ada, atau butir aktif
     * instrumen itu bila belum pernah dinilai) + ringkasan.
     *
     * @return array{jadwal: array<string, mixed>, instrumen: list<array<string, mixed>>, ringkas: array{nilai: ?float, predikat: ?string, lengkap: bool}}|null
     */
    public function lembar(int $jadwalId): ?array
    {
        $sup    = new Supervisi();
        $jadwal = $sup->cariJadwal($jadwalId);
        if ($jadwal === null) {
            return null;
        }
        $semua  = array_column($sup->daftarInstrumen(), null, 'id');
        $simpan = array_column($this->nilai->where('jadwal_id', $jadwalId)->findAll(), null, 'instrumen_id');
        $butirN = [];
        if ($simpan !== []) {
            foreach ($this->nilaiButir->whereIn('nilai_id', array_column($simpan, 'id'))->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->findAll() as $b) {
                $butirN[(int) $b['nilai_id']][] = $b;
            }
        }
        $daftar  = [];
        $lengkap = true;
        foreach ($jadwal['instrumen_id'] as $insId) {
            $n     = $simpan[$insId] ?? null;
            $butir = [];
            if ($n !== null) {
                foreach ($butirN[(int) $n['id']] ?? [] as $b) {
                    $butir[] = ['kunci' => 'n' . $b['id'], 'butir_id' => $b['butir_id'] !== null ? (int) $b['butir_id'] : null, 'aspek' => (string) $b['aspek'],
                        'uraian' => (string) $b['uraian'], 'skor' => $b['skor'] !== null ? (int) $b['skor'] : null, 'catatan' => (string) ($b['catatan'] ?? '')];
                }
            } else {
                foreach ($sup->daftarButir($insId, true) as $b) {
                    $butir[] = ['kunci' => 'b' . $b['id'], 'butir_id' => (int) $b['id'], 'aspek' => (string) $b['aspek'], 'uraian' => (string) $b['uraian'], 'skor' => null, 'catatan' => ''];
                }
            }
            $ringkas = self::hitung(array_column($butir, 'skor'));
            $lengkap = $lengkap && $butir !== [] && $ringkas['kosong'] === 0 && $ringkas['dinilai'] > 0;
            $daftar[] = [
                'id'            => $insId,
                'nama'          => (string) ($n['nama_instrumen'] ?? $semua[$insId]['nama'] ?? 'Instrumen #' . $insId),
                'jenis'         => (string) ($semua[$insId]['jenis'] ?? 'lainnya'),
                'tersimpan'     => $n !== null,
                'kekuatan'      => (string) ($n['kekuatan'] ?? ''),
                'kelemahan'     => (string) ($n['kelemahan'] ?? ''),
                'rekomendasi'   => (string) ($n['rekomendasi'] ?? ''),
                'tindak_lanjut' => self::kodeTindakLanjut($n['tindak_lanjut'] ?? null),
                'dinilai_at'    => $n['dinilai_at'] ?? null,
                'dinilai_oleh'  => $n['dinilai_oleh'] ?? null,
                'butir'         => $butir,
                'ringkas'       => $ringkas,
            ];
        }
        $rata = self::rataRata(array_map(static fn (array $i): ?float => $i['ringkas']['nilai'], $daftar));

        return ['jadwal' => $jadwal, 'instrumen' => $daftar, 'ringkas' => ['nilai' => $rata, 'predikat' => self::predikat($rata), 'lengkap' => $lengkap && $daftar !== []]];
    }

    /** @return list<string> kode tindak lanjut yang sah dari JSON/larik. */
    public static function kodeTindakLanjut(mixed $v): array
    {
        $a = is_array($v) ? $v : json_decode((string) $v, true);

        return is_array($a) ? array_values(array_intersect(array_keys(self::TINDAK_LANJUT), array_map('strval', $a))) : [];
    }

    /** @return list<string> label tindak lanjut. */
    public static function labelTindakLanjut(mixed $v): array
    {
        return array_map(static fn (string $k): string => self::TINDAK_LANJUT[$k], self::kodeTindakLanjut($v));
    }

    // =================================================================
    // Simpan
    // =================================================================

    /**
     * Simpan lembar penilaian.
     *
     * @param array<int|string, array{skor?: array<string, mixed>, catatan?: array<string, mixed>, kekuatan?: mixed, kelemahan?: mixed,
     *              rekomendasi?: mixed, tindak_lanjut?: mixed}> $isi  per instrumen_id
     * @param bool  $selesai  true = Simpan & selesai (wajib lengkap); false = draf
     * @param array{id?: ?int, nama?: string} $pencatat
     *
     * @return array{ok: bool, pesan: string, kode?: int, status?: string, nilai?: ?float, predikat?: ?string}
     */
    public function simpan(int $jadwalId, array $isi, bool $selesai, array $pencatat): array
    {
        $lembar = $this->lembar($jadwalId);
        if ($lembar === null) {
            return ['ok' => false, 'kode' => 404, 'pesan' => 'Jadwal supervisi tidak ditemukan.'];
        }
        $jadwal = $lembar['jadwal'];
        if ($jadwal['status'] === 'batal') {
            return ['ok' => false, 'kode' => 409, 'pesan' => 'Jadwal ini dibatalkan — tidak bisa dinilai. Ubah statusnya di Jadwal Supervisi bila jadi dilaksanakan.'];
        }
        if (! $selesai && $jadwal['status'] === 'selesai') {
            return ['ok' => false, 'kode' => 409, 'pesan' => 'Penilaian ini sudah selesai — simpan perubahan dengan "Simpan & selesai".'];
        }
        if ($lembar['instrumen'] === []) {
            return ['ok' => false, 'kode' => 422, 'pesan' => 'Jadwal ini belum punya instrumen. Ubah jadwalnya dulu.'];
        }

        // 1) Periksa semua isian dulu (tanpa menulis apa pun).
        $rencana = [];
        foreach ($lembar['instrumen'] as $ins) {
            $in = $isi[$ins['id']] ?? $isi[(string) $ins['id']] ?? [];
            $in = is_array($in) ? $in : [];
            $p  = $this->periksa($ins, $in, $selesai);
            if (isset($p['galat'])) {
                return ['ok' => false, 'kode' => 422, 'pesan' => $ins['nama'] . ': ' . $p['galat'], 'instrumen_id' => $ins['id']];
            }
            $rencana[] = $p;
        }

        // 2) Tulis dalam satu transaksi.
        $db   = db_connect();
        $oleh = mb_substr((string) ($pencatat['nama'] ?? ''), 0, 150) ?: null;
        $kini = date('Y-m-d H:i:s');
        $nilaiIns = [];
        $db->transStart();
        foreach ($rencana as $p) {
            $ins = $p['instrumen'];
            if ($p['lewati']) {
                continue; // draf: instrumen yang belum disentuh sama sekali tidak dibuatkan baris
            }
            $nilaiIns[] = $p['ringkas']['nilai'];
            $baris = [
                'nama_instrumen' => mb_substr($ins['nama'], 0, 150),
                'skor_total'     => $p['ringkas']['total'],
                'skor_maks'      => $p['ringkas']['maks'],
                'nilai'          => $p['ringkas']['nilai'],
                'predikat'       => $p['ringkas']['predikat'],
                'kekuatan'       => $p['kekuatan'],
                'kelemahan'      => $p['kelemahan'],
                'rekomendasi'    => $p['rekomendasi'],
                'tindak_lanjut'  => json_encode($p['tindak_lanjut']),
                'dinilai_at'     => $kini,
                'dinilai_oleh'   => $oleh,
            ];
            $ada = $this->nilai->where('jadwal_id', $jadwalId)->where('instrumen_id', $ins['id'])->first();
            if ($ada !== null) {
                $nilaiId = (int) $ada['id'];
                $this->nilai->update($nilaiId, $baris);
            } else {
                $this->nilai->insert($baris + ['jadwal_id' => $jadwalId, 'instrumen_id' => $ins['id']]);
                $nilaiId = (int) $this->nilai->getInsertID();
            }
            foreach ($p['butir'] as $urut => $b) {
                if (str_starts_with($b['kunci'], 'n')) {
                    $this->nilaiButir->update((int) substr($b['kunci'], 1), ['skor' => $b['skor'], 'catatan' => $b['catatan'], 'urut' => $urut + 1]);
                } else {
                    $this->nilaiButir->insert(['nilai_id' => $nilaiId, 'butir_id' => $b['butir_id'], 'aspek' => mb_substr($b['aspek'], 0, 150),
                        'uraian' => mb_substr($b['uraian'], 0, 500), 'urut' => $urut + 1, 'skor' => $b['skor'], 'catatan' => $b['catatan']]);
                }
            }
        }
        $akhir = $selesai ? self::rataRata($nilaiIns) : null;
        (new SupervisiJadwalModel())->update($jadwalId, [
            'status'      => $selesai ? 'selesai' : 'dijadwalkan',
            'nilai_akhir' => $akhir,
            'predikat'    => self::predikat($akhir),
        ]);
        $db->transComplete();
        if (! $db->transStatus()) {
            return ['ok' => false, 'kode' => 409, 'pesan' => 'Penilaian gagal disimpan. Coba lagi.'];
        }
        (new AuditModel())->record('update', 'supervisi_jadwal', $jadwalId, mb_substr(($selesai ? 'Penilaian supervisi selesai: ' : 'Draf penilaian supervisi: ')
            . $jadwal['nama_guru'] . ', ' . $jadwal['tanggal'] . ($akhir !== null ? ' — nilai ' . self::teksNilai($akhir) . ' (' . self::predikat($akhir) . ')' : '')
            . ($this->saluran === 'aplikasi' ? ' (via aplikasi)' : ''), 0, 255));

        return $selesai
            ? ['ok' => true, 'pesan' => 'Penilaian ' . $jadwal['nama_guru'] . ' selesai — nilai ' . self::teksNilai($akhir) . ' (' . self::predikat($akhir) . ').',
                'status' => 'selesai', 'nilai' => $akhir, 'predikat' => self::predikat($akhir)]
            : ['ok' => true, 'pesan' => 'Draf penilaian ' . $jadwal['nama_guru'] . ' disimpan. Lanjutkan kapan saja, lalu tekan "Simpan & selesai".',
                'status' => 'dijadwalkan', 'nilai' => null, 'predikat' => null];
    }

    /**
     * Periksa isian satu instrumen. Hasil: ['galat' => …] atau rencana tulis (butir dengan skor & catatan, teks, tindak lanjut,
     * ringkasan, 'lewati' = draf yang belum disentuh).
     *
     * @return array<string, mixed>
     */
    private function periksa(array $ins, array $in, bool $selesai): array
    {
        if ($ins['butir'] === []) {
            return $selesai ? ['galat' => 'instrumen ini belum punya butir aktif — isi butirnya di Instrumen Supervisi atau cabut dari jadwal.']
                : ['instrumen' => $ins, 'lewati' => true];
        }
        $skorMasuk    = is_array($in['skor'] ?? null) ? $in['skor'] : [];
        $catatanMasuk = is_array($in['catatan'] ?? null) ? $in['catatan'] : [];
        $butir    = [];
        $disentuh = false;
        foreach ($ins['butir'] as $b) {
            // Lembar yang dibuka sebelum hasil pertama tersimpan (orang lain/aplikasi) masih memakai kunci "b{butir_id}" → tetap diterima.
            $kunciLama = $b['butir_id'] !== null ? 'b' . $b['butir_id'] : '';
            $mentah = $skorMasuk[$b['kunci']] ?? ($kunciLama !== '' ? $skorMasuk[$kunciLama] ?? null : null);
            $skor   = self::skorSah($mentah);
            if ($skor === false) {
                return ['galat' => 'skor butir "' . mb_strimwidth($b['uraian'], 0, 60, '…') . '" tidak sah (pakai 1–4 atau "tidak dinilai").'];
            }
            $catatan = IsianBantu::rapikan((string) ($catatanMasuk[$b['kunci']] ?? ($kunciLama !== '' ? $catatanMasuk[$kunciLama] ?? '' : '')));
            if (mb_strlen($catatan) > self::MAKS_CATATAN) {
                return ['galat' => 'catatan butir terlalu panjang (maks ' . self::MAKS_CATATAN . ' huruf).'];
            }
            $disentuh = $disentuh || $skor !== null || $catatan !== '';
            $butir[]  = ['skor' => $skor, 'catatan' => $catatan !== '' ? $catatan : null] + $b;
        }
        $teks = [];
        foreach (['kekuatan', 'kelemahan', 'rekomendasi'] as $k) {
            $t = trim(str_replace(["\r\n", "\r"], "\n", (string) ($in[$k] ?? '')));
            $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;
            if (mb_strlen($t) > self::MAKS_TEKS) {
                return ['galat' => ucfirst($k) . ' terlalu panjang (maks ' . self::MAKS_TEKS . ' huruf).'];
            }
            $teks[$k] = $t !== '' ? $t : null;
            $disentuh = $disentuh || $t !== '';
        }
        $tl = self::kodeTindakLanjut(is_array($in['tindak_lanjut'] ?? null) ? $in['tindak_lanjut'] : []);
        if (in_array('tidak_perlu', $tl, true) && count($tl) > 1) {
            return ['galat' => '"Tidak perlu tindak lanjut" tidak bisa dipilih bersama tindak lanjut lain.'];
        }
        $disentuh = $disentuh || $tl !== [];
        $ringkas  = self::hitung(array_column($butir, 'skor'));
        if ($selesai && $ringkas['kosong'] > 0) {
            return ['galat' => $ringkas['kosong'] . ' butir belum diisi. Beri skor 1–4 atau pilih "tidak dinilai".'];
        }
        if ($selesai && $ringkas['dinilai'] === 0) {
            return ['galat' => 'minimal satu butir harus diberi skor (tidak semua "tidak dinilai").'];
        }

        return ['instrumen' => $ins, 'butir' => $butir, 'ringkas' => $ringkas, 'tindak_lanjut' => $tl,
            'lewati' => ! $selesai && ! $disentuh && ! $ins['tersimpan']] + $teks;
    }

    /** Skor dari isian: '' / null → null (belum diisi); '0' / 'td' → 0 (tidak dinilai); '1'–'4' → angka; selain itu false. */
    public static function skorSah(mixed $v): int|false|null
    {
        if ($v === null) {
            return null;
        }
        $v = mb_strtolower(trim((string) $v));
        if ($v === '') {
            return null;
        }
        if ($v === 'td' || $v === '0') {
            return 0;
        }

        return in_array($v, ['1', '2', '3', '4'], true) ? (int) $v : false;
    }
}
