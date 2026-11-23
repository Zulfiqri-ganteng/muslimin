<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Pembaca berkas impor Master Siswa (perbaikan 2026-11-23 — keluhan klien: Export → isi No HP orang tua → Import, pratinjau macet;
 * dulu kolom dibaca MENURUT URUTAN sehingga berkas hasil Export bergeser: kolom "No" terbaca sebagai NIS, NISN sebagai Nama).
 *
 *  - Kolom dicocokkan menurut JUDUL-nya: berkas template impor DAN berkas hasil Export (kop sekolah 5 baris, kolom No, satu lembar per
 *    kelas) sama-sama terbaca. Kolom bantu ekspor (No, Tingkat, Jurusan, Biodata) diabaikan.
 *  - Baris judul dicari otomatis di 20 baris teratas (baris yang memuat kolom NIS dan Nama).
 *  - SEMUA lembar dibaca; siswa yang sama (NIS) di beberapa lembar digabung, sehingga mengedit di lembar "Semua Kelas" maupun di lembar
 *    kelasnya sama-sama terbaca. Nilai berbeda antar-lembar dikumpulkan; pemanggil yang memilih (lihat Admin\Master\Siswa).
 *  - Nilai dibaca MENTAH (bukan tampilan Excel): nomor yang oleh Excel dijadikan angka / ditampilkan ilmiah (8,12E+10) tetap utuh digitnya.
 */
class SiswaImpor
{
    /** Judul kolom dinormalkan: huruf kecil, petunjuk dalam kurung dibuang ("JK (L/P)" → "jk"), hanya huruf & angka. */
    public static function normal(string $judul): string
    {
        $s = mb_strtolower(trim($judul));
        $s = (string) preg_replace('/\([^)]*\)/', '', $s);

        return (string) preg_replace('/[^a-z0-9]/', '', $s);
    }

    /**
     * Peta judul ternormal → kunci kolom.
     *
     * @param list<array{key: string, label?: string, template?: string}> $kolom  definisi kolom impor (urutan = prioritas)
     * @param array<string, string>                                        $alias  judul lain => kunci (mis. judul di berkas Export)
     *
     * @return array<string, string>
     */
    public static function petaJudul(array $kolom, array $alias = []): array
    {
        $peta = [];
        foreach ($kolom as $k) {
            foreach (['template', 'label'] as $f) {
                $n = self::normal((string) ($k[$f] ?? ''));
                if ($n !== '' && ! isset($peta[$n])) {
                    $peta[$n] = $k['key'];
                }
            }
        }
        foreach ($alias as $judul => $key) {
            $n = self::normal($judul);
            if ($n !== '' && ! isset($peta[$n])) {
                $peta[$n] = $key;
            }
        }

        return $peta;
    }

    /**
     * Baca berkas → nilai per NIS per kolom.
     *
     * @param array<string, string> $peta hasil petaJudul()
     *
     * @return array{ok: bool, pesan?: string, nilai: array<string, array<string, array<string, true>>>, urut: list<string>,
     *               lembar: int, baris: int, galat: list<string>, kolom: list<string>}
     *               nilai[nis][kunci] = himpunan nilai tak-kosong yang ditemukan (kosong = [] — kolom ada tapi selnya kosong)
     */
    public static function baca(string $path, array $peta): array
    {
        $hasil = ['ok' => false, 'nilai' => [], 'urut' => [], 'lembar' => 0, 'baris' => 0, 'galat' => [], 'kolom' => []];
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true); // nilai saja (lebih cepat & hemat memori; gaya sel tak diperlukan)
            $ss = $reader->load($path);
        } catch (\Throwable $e) {
            return ['pesan' => 'Gagal membaca file: ' . $e->getMessage()] + $hasil;
        }

        $kolomDikenal = [];
        foreach ($ss->getAllSheets() as $ws) {
            $data = $ws->toArray(null, true, false, false); // dihitung (rumus), MENTAH (tanpa format tampilan)

            // Baris judul = baris pertama (≤ 20) yang memuat kolom NIS dan Nama.
            $barisJudul = null;
            $kolom      = [];
            foreach (array_slice($data, 0, 20, true) as $i => $row) {
                $map = [];
                foreach ($row as $c => $v) {
                    $n = self::normal((string) $v);
                    if ($n !== '' && isset($peta[$n]) && ! in_array($peta[$n], $map, true)) {
                        $map[$c] = $peta[$n];
                    }
                }
                if (in_array('nis', $map, true) && in_array('nama', $map, true)) {
                    $barisJudul = $i;
                    $kolom      = $map;
                    break;
                }
            }
            if ($barisJudul === null) {
                continue; // lembar tanpa tabel siswa (catatan, dsb.)
            }
            $hasil['lembar']++;
            $kolomDikenal += array_flip($kolom);

            foreach ($data as $i => $row) {
                if ($i <= $barisJudul) {
                    continue;
                }
                $r = [];
                foreach ($kolom as $c => $key) {
                    $r[$key] = self::teks($row[$c] ?? null);
                }
                if (implode('', $r) === '') {
                    continue;
                }
                $hasil['baris']++;
                $nis = $r['nis'] ?? '';
                if ($nis === '') {
                    if (($r['nama'] ?? '') !== '') {
                        $hasil['galat'][] = 'Lembar "' . $ws->getTitle() . '" baris ' . ($i + 1) . ': NIS kosong (' . $r['nama'] . ') — dilewati.';
                    }

                    continue;
                }
                if (! isset($hasil['nilai'][$nis])) {
                    $hasil['urut'][] = $nis;
                }
                foreach ($r as $key => $v) {
                    $hasil['nilai'][$nis][$key] ??= [];
                    if ($v !== '') {
                        $hasil['nilai'][$nis][$key][$v] = true;
                    }
                }
            }
        }
        $ss->disconnectWorksheets();

        if ($hasil['lembar'] === 0) {
            return ['pesan' => 'Judul kolom tidak ditemukan. Baris judul harus memuat kolom NIS dan Nama Siswa — pakai template impor atau berkas hasil Export Master Siswa.'] + $hasil;
        }
        if ($hasil['urut'] === []) {
            return ['pesan' => 'File tidak berisi data siswa.'] + $hasil;
        }
        $hasil['ok']    = true;
        $hasil['kolom'] = array_keys($kolomDikenal);

        return $hasil;
    }

    /** Nilai sel mentah → teks. Angka bulat (termasuk yang Excel tampilkan 8,12E+10) ditulis utuh tanpa ".0" / notasi ilmiah. */
    private static function teks(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_int($v)) {
            return (string) $v;
        }
        if (is_float($v)) {
            return floor($v) === $v && abs($v) < 1e15 ? number_format($v, 0, '', '') : rtrim(rtrim(sprintf('%.6F', $v), '0'), '.');
        }

        return trim((string) $v);
    }
}
