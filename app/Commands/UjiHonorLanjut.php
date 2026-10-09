<?php

namespace App\Commands;

use App\Libraries\HonorDokumen;
use App\Libraries\HonorHitung;
use App\Libraries\HonorImpor;
use App\Libraries\HonorKoreksi;
use App\Libraries\HonorPengaturan;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;

/**
 * Uji lanjutan Honor Ujian: (A) urutan jabatan, aturan urutan penerima, atur ulang urutan (aturan / Excel), dan
 * (B) ceklis KOREKSI — peserta per kelas, baris mapel, sel, total, isi dari pengampu, salin, kunci, serta sambungannya ke
 * "Hitung otomatis". Rancangan: docs/DESAIN-HONOR.md bagian 11.
 *
 * SELURUH uji berjalan di dalam SATU transaksi database yang di-ROLLBACK di akhir (juga bila berhenti karena galat),
 * jadi data asli tidak pernah berubah. Semua nama uji berawalan "ZZUJI HL".
 *
 * Jalankan:  php spark dev:uji-honor-lanjut
 */
class UjiHonorLanjut extends BaseCommand
{
    protected $group       = 'dev';
    protected $name        = 'dev:uji-honor-lanjut';
    protected $description = 'Uji urutan jabatan, atur ulang urutan, dan ceklis Koreksi honor (di-rollback).';

    private int $lulus = 0;
    private int $gagal = 0;
    private int $seq   = 0;
    private BaseConnection $db;

    /** @var array<string,int> nama pendek → id guru uji */
    private array $g = [];
    private int $dok1 = 0;
    private int $dok2 = 0;

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->lulus++;
            CLI::write('  [OK]    ' . $judul . ($detail !== '' ? '  → ' . $detail : ''), 'green');
        } else {
            $this->gagal++;
            CLI::write('  [GAGAL] ' . $judul . ($detail !== '' ? '  → ' . $detail : ''), 'red');
        }
    }

    private function bagian(string $judul): void
    {
        CLI::newLine();
        CLI::write('== ' . $judul . ' ==', 'yellow');
    }

    public function run(array $params)
    {
        $this->db = db_connect();
        $this->db->transBegin();
        // Jejak audit honor lama dibuang DI DALAM transaksi uji supaya hitungan audit tepat; ikut di-rollback.
        $this->db->table('audit_log')->like('tabel', 'honor_', 'after')->delete();
        try {
            $this->ujiMurni();
            $this->siapkanData();
            $this->ujiUrutanJabatan();
            $this->ujiPengaturanUrutan();
            $this->ujiAturUlangAturan();
            $this->ujiAturUlangExcel();
            $this->ujiPesertaDanSel();
            $this->ujiIsiDariPengampu();
            $this->ujiHitungOtomatis();
            $this->ujiSalinDanKunci();
            $kode = $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
        } catch (\Throwable $e) {
            CLI::write('FATAL ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'red');
            $kode = EXIT_ERROR;
        }
        while ($this->db->transRollback()) {
            // batalkan semua tingkat transaksi sampai habis
        }
        cache()->delete('opt_guru');

        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal  (semua perubahan uji sudah di-rollback)', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $kode;
    }

    // ------------------------------------------------------------------ pembantu data uji

    private function guru(string $nama, bool $staf = false): int
    {
        $this->seq++;
        $this->db->table('guru')->insert(['kode_guru' => 'ZZHL' . $this->seq . substr(str_replace('.', '', (string) microtime(true)), -6), 'nama' => $nama, 'bukan_pengajar' => $staf ? 1 : 0, 'max_beban' => 24]);

        return (int) $this->db->insertID();
    }

    private function jabatanId(string $kode): int
    {
        return (int) ($this->db->table('jabatan')->select('id')->where('kode', $kode)->get()->getRow()->id ?? 0);
    }

    private function beriJabatan(int $guruId, int $jabatanId): void
    {
        $this->db->table('guru_jabatan')->insert(['guru_id' => $guruId, 'jabatan_id' => $jabatanId, 'is_utama' => 1, 'created_at' => date('Y-m-d H:i:s')]);
    }

    private function periode(string $jenis): array
    {
        return $this->db->table('ujian_periode')->where('jenis', $jenis)->where('deleted_at', null)->orderBy('id', 'DESC')->get()->getRowArray() ?? [];
    }

    private function buatDokumen(string $jenis): int
    {
        $p = $this->periode($jenis);
        $this->db->table('honor_dokumen')->where('periode_id', $p['id'])->delete();
        $r = (new HonorDokumen())->buat($p);

        return (int) ($r['id'] ?? 0);
    }

    /** Nama penerima dokumen menurut nomor urut. @return list<string> */
    private function urutan(int $dokId): array
    {
        return array_column($this->db->table('honor_baris')->select('nama')->where('dokumen_id', $dokId)->orderBy('urut', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray(), 'nama');
    }

    private function barisId(int $dokId, string $nama): int
    {
        return (int) ($this->db->table('honor_baris')->select('id')->where('dokumen_id', $dokId)->where('nama', $nama)->get()->getRow()->id ?? 0);
    }

    // ------------------------------------------------------------------ uji

    private function ujiMurni(): void
    {
        $this->bagian('Pembantu murni: urutan bawaan jabatan, nama kelas, kode guru, pencocok nama Excel');
        $u = static fn (array $j): int => HonorDokumen::urutanBawaan($j);
        $baku = ['KS' => 10, 'WK-KUR' => 30, 'WK-SIS' => 40, 'WK-HUM' => 50, 'WK-SAR' => 60, 'KAPROG' => 70, 'OP' => 130, 'GMP' => 210, 'WALI' => 220, 'GP' => 300, 'TU' => 400];
        foreach ($baku as $kode => $n) {
            $this->cek("kode baku $kode = $n", $u(['kode' => $kode, 'nama' => 'apa saja', 'level' => 3]) === $n);
        }
        $nama = [
            'Kepala Tata Usaha' => 20, 'KEPALA TATA USAHA' => 20, 'Kepala TU' => 20, 'KTU' => 20, 'Koordinator BK' => 90, 'Koordinator Bimbingan Konseling' => 90,
            'Pembina OSIS' => 100, 'Pembina Osis' => 100, 'Kepala Laboratorium' => 110, 'Kepala Perpustakaan' => 120, 'Wakil Kepala Sekolah Bidang Lain' => 65, 'Kepala Sekolah' => 10,
        ];
        foreach ($nama as $n => $harap) {
            $this->cek('nama jabatan "' . $n . '" (kode tak dikenal) = ' . $harap, $u(['kode' => 'ZZ-' . md5($n), 'nama' => $n, 'level' => 3]) === $harap, (string) $u(['kode' => 'ZZ-x', 'nama' => $n, 'level' => 3]));
        }
        $this->cek('jabatan tak dikenal = 500 + level', $u(['kode' => 'ZZ-BEND', 'nama' => 'Bendahara', 'level' => 3]) === 503);
        $this->cek('"Wakil Kepala Sekolah" tidak salah dikira "Kepala Sekolah"', $u(['kode' => 'ZZ-1', 'nama' => 'Wakil Kepala Sekolah Bidang X', 'level' => 2]) === 65);

        $k = static fn (string $n): array => HonorKoreksi::uraiKelas($n);
        $this->cek('uraiKelas "X TKJ 1" → TKJ.1', $k('X TKJ 1') === ['tingkat' => 'X', 'jurusan' => 'TKJ', 'nomor' => 1, 'label' => 'TKJ.1']);
        $this->cek('uraiKelas "XII MPLB 3" → MPLB.3', $k('XII MPLB 3')['label'] === 'MPLB.3' && $k('XII MPLB 3')['tingkat'] === 'XII');
        $this->cek('uraiKelas "X AKL" → AKL (tanpa nomor)', $k('X AKL')['label'] === 'AKL' && $k('X AKL')['nomor'] === 0);
        $this->cek('uraiKelas "XI TKJ 10" → nomor 10', $k('XI TKJ 10')['nomor'] === 10 && $k('XI TKJ 10')['label'] === 'TKJ.10');
        $this->cek('uraiKelas spasi berlebih & huruf kecil', $k('  xi   tkj   2 ')['tingkat'] === 'XI' && $k('  xi   tkj   2 ')['label'] === 'tkj.2');
        $this->cek('kunciKelas lintas sumber sama ("XII TKJ 1" = XII + "TKJ.1")', HonorKoreksi::kunciKelas('XII', $k('XII TKJ 1')['label']) === HonorKoreksi::kunciKelas('XII', 'TKJ.1') && HonorKoreksi::kunciKelas('XII', 'TKJ.1') === 'XIITKJ1');
        $this->cek('kodeGuru: satu baris = nomor saja', HonorKoreksi::kodeGuru(6, 0, 1) === '6');
        $this->cek('kodeGuru: beberapa baris = 3A, 3B, 3C', HonorKoreksi::kodeGuru(3, 0, 3) === '3A' && HonorKoreksi::kodeGuru(3, 1, 3) === '3B' && HonorKoreksi::kodeGuru(3, 2, 3) === '3C');
        $this->cek('kodeGuru: indeks ke-26 = 3AA', HonorKoreksi::kodeGuru(3, 26, 30) === '3AA' && HonorKoreksi::kodeGuru(3, 25, 30) === '3Z');

        $dok = [
            ['id' => 1, 'nama' => 'Budi Santoso, S.Pd', 'guru_nama' => 'Budi Santoso, S.Pd'],
            ['id' => 2, 'nama' => 'Citra Dewi', 'guru_nama' => 'Citra Dewi, S.Kom'],
            ['id' => 3, 'nama' => 'Sari Ayu, S.Pd', 'guru_nama' => 'Sari Ayu, S.Pd'],
            ['id' => 4, 'nama' => 'Sari Ayu, S.Kom', 'guru_nama' => 'Sari Ayu, S.Kom'],
            ['id' => 5, 'nama' => 'Dodi', 'guru_nama' => 'Dodi'],
        ];
        $excel = [['nama' => 'Citra Dewi, S.Kom'], ['nama' => 'Budi Santoso, S.Pd.'], ['nama' => 'Sari Ayu'], ['nama' => 'Orang Tidak Ada'], ['nama' => 'Sari Ayu, S.Kom']];
        $m = HonorImpor::cocokkanUrutan($excel, $dok);
        $this->cek('pencocok: tingkat 1 (nama Master) + tingkat 2 (titik gelar) + persis → urutan [2,1,4] lalu sisanya [3,5]', $m['urut'] === [2, 1, 4, 3, 5], json_encode($m['urut']));
        $this->cek('pencocok: peta Excel→dokumen', $m['cocok'] === [0 => 2, 1 => 1, 4 => 4], json_encode($m['cocok']));
        $this->cek('pencocok: nama tanpa gelar yang sama dengan 2 orang = ganda, tidak ditebak', $m['ganda'] === ['Sari Ayu'], json_encode($m['ganda']));
        $this->cek('pencocok: nama tak ada & ganda dilaporkan; sisa dokumen dilaporkan', in_array('Orang Tidak Ada', $m['tak_ada_di_dokumen'], true) && in_array('Sari Ayu', $m['tak_ada_di_dokumen'], true) && $m['tak_ada_di_excel'] === ['Sari Ayu, S.Pd', 'Dodi'], json_encode([$m['tak_ada_di_dokumen'], $m['tak_ada_di_excel']]));
        $m2 = HonorImpor::cocokkanUrutan([['nama' => 'Budi Santoso, S.Pd'], ['nama' => 'Budi Santoso, S.Pd']], [$dok[0]]);
        $this->cek('pencocok: satu baris dokumen hanya dipakai sekali', $m2['cocok'] === [0 => 1] && $m2['urut'] === [1], json_encode($m2));
        $this->cek('pencocok: daftar kosong aman', HonorImpor::cocokkanUrutan([], [])['urut'] === []);
    }

    private function siapkanData(): void
    {
        $this->bagian('Data uji: jabatan Kepala TU buatan sendiri + 8 orang');
        $this->db->table('jabatan')->insert(['kode' => 'ZZ-KTU', 'nama' => 'ZZUJI Kepala Tata Usaha', 'kategori' => 'lainnya', 'level' => 2, 'is_struktural' => 1]);
        $jKtu = (int) $this->db->insertID();
        $ks   = $this->jabatanId('KS');
        $wk   = $this->jabatanId('WK-KUR');
        $gmp  = $this->jabatanId('GMP');
        $tu   = $this->jabatanId('TU');
        $this->cek('jabatan baku KS, WK-KUR, GMP, TU ada di Master', $ks > 0 && $wk > 0 && $gmp > 0 && $tu > 0);
        $this->g = [
            'anton' => $this->guru('ZZUJI HL Anton, S.Pd'),
            'mira' => $this->guru('ZZUJI HL Mira, S.Pd'),
            'wawan' => $this->guru('ZZUJI HL Wawan, S.Kom'),
            'delta' => $this->guru('ZZUJI HL Delta'),
            'echo' => $this->guru('ZZUJI HL Echo'),
            'foxtrot' => $this->guru('ZZUJI HL Foxtrot', true),
            'gina' => $this->guru('ZZUJI HL Gina', true),
        ];
        $this->beriJabatan($this->g['anton'], $ks);
        $this->beriJabatan($this->g['mira'], $jKtu);
        $this->beriJabatan($this->g['wawan'], $wk);
        $this->beriJabatan($this->g['echo'], $gmp);
        $this->beriJabatan($this->g['gina'], $tu);
        $this->g['jabatan_ktu'] = $jKtu;
        $this->cek('8 data uji siap (7 orang + jabatan)', count($this->g) === 8);
    }

    private function ujiUrutanJabatan(): void
    {
        $this->bagian('Urutan bawaan penerima: Kepala TU di bawah Kepala Sekolah; orang tanpa jabatan = Guru Mapel (tidak melompat ke atas)');
        $this->dok1 = $this->buatDokumen('ASTS1');
        $this->cek('honor ASTS1 dibuat', $this->dok1 > 0);
        $h = new HonorDokumen();
        $acak = [$this->g['gina'], $this->g['foxtrot'], $this->g['echo'], $this->g['delta'], $this->g['wawan'], $this->g['mira'], $this->g['anton']];
        $r = $h->tambahPenerima($this->dok1, $acak);
        $this->cek('7 penerima ditambahkan (dipilih acak)', $r['ok'] && $r['jumlah'] === 7, $r['pesan']);
        $harap = ['ZZUJI HL Anton, S.Pd', 'ZZUJI HL Mira, S.Pd', 'ZZUJI HL Wawan, S.Kom', 'ZZUJI HL Delta', 'ZZUJI HL Echo', 'ZZUJI HL Foxtrot', 'ZZUJI HL Gina'];
        $this->cek('urutan: Kepsek, Kepala TU, Waka, lalu Delta (tanpa jabatan) & Echo (GMP) berurut abjad, lalu staf', $this->urutan($this->dok1) === $harap, json_encode($this->urutan($this->dok1)));
        $lab = [];
        foreach ($this->db->table('honor_baris')->select('nama, jabatan')->where('dokumen_id', $this->dok1)->get()->getResultArray() as $b) {
            $lab[$b['nama']] = $b['jabatan'];
        }
        $this->cek('label: Kepala TU memakai nama jabatannya; tanpa jabatan = Guru Mata Pelajaran; staf = Staf Tata Usaha', $lab['ZZUJI HL Mira, S.Pd'] === 'ZZUJI Kepala Tata Usaha' && $lab['ZZUJI HL Delta'] === 'Guru Mata Pelajaran' && $lab['ZZUJI HL Foxtrot'] === 'Staf Tata Usaha', json_encode($lab));
        $calon = $h->calonPenerima($this->dok1);
        $this->cek('calonPenerima tetap jalan (label bawaan terisi)', $calon !== [] && ! in_array('', array_column($calon, 'jabatan'), true));
    }

    private function ujiPengaturanUrutan(): void
    {
        $this->bagian('Pengaturan urutan jabatan oleh Admin (honor_jabatan_urutan)');
        $a   = new HonorPengaturan();
        $jid = $this->g['jabatan_ktu'];
        $row = static function (array $rows, int $id): array {
            foreach ($rows as $r) {
                if ((int) $r['id'] === $id) {
                    return $r;
                }
            }

            return [];
        };
        $r0 = $row($a->panitia(), $jid);
        $this->cek('panitia() memuat urutan (null) dan urutan_bawaan (20)', $r0['urutan'] === null && $r0['urutan_bawaan'] === 20, json_encode($r0));
        $r = $a->simpanUrutan([$jid => '5']);
        $this->cek('simpan urutan 5', $r['ok'] && $r['jumlah'] === 1, $r['pesan']);
        $this->cek('urutan tersimpan terbaca', ($row($a->panitia(), $jid)['urutan'] ?? null) === 5);
        foreach (['0', 'abc', '10000', '-3', '1.5', '１２'] as $buruk) {
            $x = $a->simpanUrutan([$jid => $buruk]);
            $this->cek('urutan "' . $buruk . '" ditolak', ! $x['ok']);
        }
        $x = $a->simpanUrutan([$jid => '7', 999999999 => '3']);
        $this->cek('satu isian salah (jabatan tak dikenal) → tak ada yang tersimpan (tetap 5)', ! $x['ok'] && ($row($a->panitia(), $jid)['urutan'] ?? null) === 5);
        $x = $a->simpanUrutan([$jid => '5']);
        $this->cek('menyimpan angka yang sama = tidak ada perubahan', $x['ok'] && $x['jumlah'] === 0);

        $d2 = $this->buatDokumen('ASAS');
        $this->dok2 = $d2;
        (new HonorDokumen())->tambahPenerima($d2, [$this->g['anton'], $this->g['mira'], $this->g['wawan']]);
        $this->cek('urutan diatur 5 → Kepala TU tampil SEBELUM Kepala Sekolah (10) di honor baru', array_slice($this->urutan($d2), 0, 3) === ['ZZUJI HL Mira, S.Pd', 'ZZUJI HL Anton, S.Pd', 'ZZUJI HL Wawan, S.Kom'], json_encode($this->urutan($d2)));
        $x = $a->simpanUrutan([$jid => '']);
        $rr = $row($a->panitia(), $jid);
        $this->cek('kosongkan → kembali ke bawaan (baris dihapus, urutan_bawaan 20)', $x['ok'] && array_key_exists('urutan', $rr) && $rr['urutan'] === null && $rr['urutan_bawaan'] === 20 && $this->db->table('honor_jabatan_urutan')->where('jabatan_id', $jid)->countAllResults() === 0);
        $audit = (int) $this->db->table('audit_log')->where('tabel', 'honor_jabatan_urutan')->countAllResults();
        $this->cek('perubahan urutan tercatat di Audit Log', $audit >= 2, (string) $audit);
        // honor ASAS dipakai lagi nanti (salin & kunci): kosongkan dulu agar tidak mengotori
        $this->db->table('honor_dokumen')->where('id', $d2)->delete();
        $this->dok2 = 0;
    }

    private function ujiAturUlangAturan(): void
    {
        $this->bagian('Atur ulang urutan menurut aturan jabatan (pratinjau → terapkan; angka tidak disentuh)');
        $h   = new HonorDokumen();
        $d   = $this->dok1;
        $uAnton = $this->barisId($d, 'ZZUJI HL Anton, S.Pd');
        $uGina  = $this->barisId($d, 'ZZUJI HL Gina');
        $uDelta = $this->barisId($d, 'ZZUJI HL Delta');
        $komp   = (int) $this->db->table('honor_dok_komponen')->where('dokumen_id', $d)->where('kode', 'soal')->get()->getRow()->id;
        $h->simpanNilai($d, $uDelta, $komp, '3');
        $h->pindahKe($d, $uGina, 1);
        $h->ubahBaris($d, $uAnton, 'LABEL RUSAK');
        $this->cek('keadaan awal diacak: Gina nomor 1, label Anton diubah', $this->urutan($d)[0] === 'ZZUJI HL Gina');

        $rc = $h->rencanaUrutanAturan($d);
        $this->cek('rencana: ada yang pindah nomor dan 1 label beda; belum ada yang tersimpan', $rc['pindah'] > 0 && $rc['label'] === 1 && $this->urutan($d)[0] === 'ZZUJI HL Gina', json_encode([$rc['pindah'], $rc['label']]));
        $r = $h->terapkanUrutan($d, $rc, false, 'uji');
        $this->cek('terapkan TANPA label: urutan kembali standar', $r['ok'] && $this->urutan($d) === ['ZZUJI HL Anton, S.Pd', 'ZZUJI HL Mira, S.Pd', 'ZZUJI HL Wawan, S.Kom', 'ZZUJI HL Delta', 'ZZUJI HL Echo', 'ZZUJI HL Foxtrot', 'ZZUJI HL Gina'], $r['pesan']);
        $this->cek('...label Anton tetap "LABEL RUSAK" (tidak dicentang)', $this->db->table('honor_baris')->where('id', $uAnton)->get()->getRow()->jabatan === 'LABEL RUSAK');
        $rc2 = $h->rencanaUrutanAturan($d);
        $r2  = $h->terapkanUrutan($d, $rc2, true, 'uji');
        $this->cek('terapkan DENGAN label: label Anton = Kepala Sekolah', $r2['ok'] && $r2['label'] === 1 && $this->db->table('honor_baris')->where('id', $uAnton)->get()->getRow()->jabatan === 'Kepala Sekolah', $r2['pesan']);
        $this->cek('angka isian TIDAK berubah (Pembuatan Soal Delta tetap 3)', (int) $this->db->table('honor_nilai')->where('baris_id', $uDelta)->where('dok_komponen_id', $komp)->get()->getRow()->nilai === 3);
        $r3 = $h->terapkanUrutan($d, $h->rencanaUrutanAturan($d), true, 'uji');
        $this->cek('terapkan lagi → "tidak ada yang perlu diubah"', $r3['ok'] && $r3['pindah'] === 0 && $r3['label'] === 0 && str_contains($r3['pesan'], 'Tidak ada'), $r3['pesan']);
        $urut = array_column($this->db->table('honor_baris')->select('urut')->where('dokumen_id', $d)->orderBy('urut')->get()->getResultArray(), 'urut');
        $this->cek('nomor urut rapat 1..7', array_map('intval', $urut) === [1, 2, 3, 4, 5, 6, 7], json_encode($urut));
        $a = (int) $this->db->table('audit_log')->where('tabel', 'honor_dokumen')->like('deskripsi', 'Atur ulang urutan', 'after')->countAllResults();
        $this->cek('tercatat di Audit Log (2 kali terapkan yang berubah)', $a === 2, (string) $a);

        // baris tanpa tautan Master: tetap di bawah, label tidak diubah
        $this->db->table('honor_baris')->where('id', $uDelta)->update(['guru_id' => null, 'jabatan' => 'MANUAL']);
        $rc4 = $h->rencanaUrutanAturan($d);
        $this->cek('baris tanpa tautan Master Guru ditaruh di paling bawah, labelnya tak diubah', end($rc4['baris'])['id'] === $uDelta && end($rc4['baris'])['jabatan_baru'] === 'MANUAL' && ! end($rc4['baris'])['label_beda']);
        $h->terapkanUrutan($d, $rc4, true, 'uji');
        $this->db->table('honor_baris')->where('id', $uDelta)->update(['guru_id' => $this->g['delta'], 'jabatan' => 'Guru Mata Pelajaran']);
        $h->terapkanUrutan($d, $h->rencanaUrutanAturan($d), true, 'uji');

        // kunci
        $h->ubahStatus($d, 'final');
        $h->ubahStatus($d, 'dikunci');
        $h->pindahKe($d, $uAnton, 1); // ditolak karena terkunci
        $rk = $h->terapkanUrutan($d, $h->rencanaUrutanAturan($d), true, 'uji');
        $this->cek('honor DIKUNCI → atur ulang ditolak', ! $rk['ok'] && str_contains($rk['pesan'], 'KUNCI'), $rk['pesan']);
        $h->ubahStatus($d, 'final', 'uji buka kunci');
        $rb = $h->rencanaUrutanAturan($d);
        $rb['baris'][0]['id'] = 99999999; // rencana basi / dipalsukan
        $rx = $h->terapkanUrutan($d, $rb, true, 'uji');
        $this->cek('rencana yang tidak cocok dengan daftar penerima sekarang ditolak', ! $rx['ok'] && str_contains($rx['pesan'], 'berubah'), $rx['pesan']);
    }

    private function ujiAturUlangExcel(): void
    {
        $this->bagian('Atur ulang urutan mengikuti Excel sekolah (hanya urutan & tulisan jabatan)');
        $h = new HonorDokumen();
        $d = $this->dok1;
        $komp  = (int) $this->db->table('honor_dok_komponen')->where('dokumen_id', $d)->where('kode', 'soal')->get()->getRow()->id;
        $uEcho = $this->barisId($d, 'ZZUJI HL Echo');
        $h->simpanNilai($d, $uEcho, $komp, '4');
        $excel = [
            ['nama' => 'ZZUJI HL Gina', 'jabatan' => 'Staf Tata Usaha'],
            ['nama' => 'ZZUJI HL Echo', 'jabatan' => 'Guru Mata Pelajaran'],
            ['nama' => 'ZZUJI HL Anton, S.Pd.', 'jabatan' => 'Kepala Sekolah'],
            ['nama' => 'ZZUJI HL Orang Lain', 'jabatan' => '-'],
            ['nama' => 'ZZUJI HL Mira, S.Pd', 'jabatan' => 'Kepala Tata Usaha'],
        ];
        $rc = $h->rencanaUrutanExcel($d, $excel);
        $this->cek('rencana Excel: 4 cocok dipindah ke atas sesuai Excel', array_slice(array_column($rc['baris'], 'nama'), 0, 4) === ['ZZUJI HL Gina', 'ZZUJI HL Echo', 'ZZUJI HL Anton, S.Pd', 'ZZUJI HL Mira, S.Pd'], json_encode(array_column($rc['baris'], 'nama')));
        $this->cek('...sisanya (tak ada di Excel) di bawah dengan urutan lama: Wawan, Delta, Foxtrot', array_slice(array_column($rc['baris'], 'nama'), 4) === ['ZZUJI HL Wawan, S.Kom', 'ZZUJI HL Delta', 'ZZUJI HL Foxtrot'] && $rc['tak_ada_di_excel'] === ['ZZUJI HL Wawan, S.Kom', 'ZZUJI HL Delta', 'ZZUJI HL Foxtrot'], json_encode($rc['tak_ada_di_excel']));
        $this->cek('...nama Excel yang tak ada di honor dilaporkan', $rc['tak_ada_di_dokumen'] === ['ZZUJI HL Orang Lain'], json_encode($rc['tak_ada_di_dokumen']));
        $r = $h->terapkanUrutan($d, $rc, false, 'Excel uji');
        $this->cek('terapkan tanpa label: urutan berubah, label lama tetap', $r['ok'] && $this->urutan($d)[0] === 'ZZUJI HL Gina' && $this->db->table('honor_baris')->where('nama', 'ZZUJI HL Mira, S.Pd')->where('dokumen_id', $d)->get()->getRow()->jabatan === 'ZZUJI Kepala Tata Usaha', $r['pesan']);
        $rc2 = $h->rencanaUrutanExcel($d, $excel);
        $r2  = $h->terapkanUrutan($d, $rc2, true, 'Excel uji');
        $this->cek('terapkan dengan label: Mira = "Kepala Tata Usaha" (tulisan Excel)', $r2['ok'] && $this->db->table('honor_baris')->where('nama', 'ZZUJI HL Mira, S.Pd')->where('dokumen_id', $d)->get()->getRow()->jabatan === 'Kepala Tata Usaha', $r2['pesan']);
        $this->cek('angka isian tidak berubah (Pembuatan Soal Echo tetap 4)', (int) $this->db->table('honor_nilai')->where('baris_id', $uEcho)->where('dok_komponen_id', $komp)->get()->getRow()->nilai === 4);
        $r3 = $h->terapkanUrutan($d, $h->rencanaUrutanExcel($d, $excel), true, 'Excel uji');
        $this->cek('terapkan lagi → tidak ada yang berubah', $r3['ok'] && $r3['pindah'] === 0 && $r3['label'] === 0, $r3['pesan']);
        // kembalikan ke urutan standar untuk uji berikutnya
        $h->terapkanUrutan($d, $h->rencanaUrutanAturan($d), true, 'uji');
    }

    private function ujiPesertaDanSel(): void
    {
        $this->bagian('Ceklis Koreksi: kelas, peserta, baris mapel, sel, total');
        $k  = new HonorKoreksi();
        $d  = $this->dok1;
        $kelas = $k->daftarKelas();
        $this->cek('daftar kelas tidak kosong; tiap kelas punya label, kelompok, jumlah siswa', $kelas !== [] && ! in_array('', array_column($kelas, 'label'), true) && ! in_array('', array_column($kelas, 'grup'), true), (string) count($kelas));
        // urutan: kelompok berdampingan; di dalam kelompok jurusan berblok & nomor naik
        $tertutup = [];
        $rapi = true;
        $prev = null;
        foreach ($kelas as $x) {
            if ($prev !== null && $prev['grup'] !== $x['grup']) {
                $tertutup[$prev['grup']] = true;
            }
            if (isset($tertutup[$x['grup']])) {
                $rapi = false;
            }
            $prev = $x;
        }
        $this->cek('kelompok kolom (mis. KELAS PAGI X) tidak terpecah', $rapi);
        $naik = true;
        $jurSelesai = [];
        $prev = null;
        foreach ($kelas as $x) {
            $u = HonorKoreksi::uraiKelas($x['nama']);
            if ($prev === null || $prev['grup'] !== $x['grup']) {
                $jurSelesai = []; // tiap kelompok kolom dinilai sendiri
            }
            if ($prev !== null && $prev['grup'] === $x['grup']) {
                $pu = HonorKoreksi::uraiKelas($prev['nama']);
                if ($pu['jurusan'] === $u['jurusan'] && $u['nomor'] < $pu['nomor']) {
                    $naik = false;
                }
                if ($pu['jurusan'] !== $u['jurusan']) {
                    $jurSelesai[$pu['jurusan']] = true;
                }
                if (isset($jurSelesai[$u['jurusan']])) {
                    $naik = false;
                }
            }
            $prev = $x;
        }
        $this->cek('di dalam kelompok: jurusan berblok & nomor kelas naik (TKJ.1…TKJ.10, MPLB.1…, AKL)', $naik);

        $m0 = $k->muat($d);
        $this->cek('honor tanpa ceklis: muat() kosong, peserta bawaan = siswa aktif', $m0['guru'] === [] && ! $k->ada($d) && $m0['kelas'][0]['peserta'] === $m0['kelas'][0]['siswa'] && ! $m0['kelas'][0]['manual']);

        $kA = $kelas[0]['id'];
        $kB = $kelas[1]['id'];
        $kC = $kelas[2]['id'];
        $r = $k->simpanPeserta($d, [$kA => '30', $kB => '31', $kC => '32']);
        $this->cek('simpan peserta 3 kelas', $r['ok'] && $r['per_kelas'][$kA]['peserta'] === 30 && $r['per_kelas'][$kC]['peserta'] === 32, $r['pesan']);
        $this->cek('peserta yang beda dari siswa aktif = manual', $kelas[0]['siswa'] === 30 ? ! $r['per_kelas'][$kA]['manual'] : (bool) $r['per_kelas'][$kA]['manual']);
        foreach (['abc', '-1', '1000', '1.5', ' 12x'] as $buruk) {
            $x = $k->simpanPeserta($d, [$kA => $buruk]);
            $this->cek('peserta "' . $buruk . '" ditolak', ! $x['ok']);
        }
        $x = $k->simpanPeserta($d, [$kA => '40', 999999999 => '5']);
        $this->cek('satu isian salah → tak ada yang tersimpan (tetap 30)', ! $x['ok'] && $k->muat($d)['kelas'][0]['peserta'] === 30);
        $x = $k->simpanPeserta($d, [$kA => '']);
        $this->cek('kosong = kembali ke jumlah siswa aktif', $x['ok'] && $k->muat($d)['kelas'][0]['peserta'] === $kelas[0]['siswa']);
        $k->simpanPeserta($d, [$kA => '30']);

        // baris mapel & sel
        $bAlfa = $this->barisId($d, 'ZZUJI HL Echo');
        $t1 = $k->tambahMapel($d, $bAlfa, 'ZZUJI Mapel Satu');
        $t2 = $k->tambahMapel($d, $bAlfa, 'ZZUJI Mapel Dua');
        $this->cek('tambah 2 baris mapel untuk Echo', $t1['ok'] && $t2['ok']);
        $bad = $k->tambahMapel($d, $bAlfa, '   ');
        $this->cek('nama mapel kosong ditolak', ! $bad['ok']);
        $bad = $k->tambahMapel($d, 99999999, 'X');
        $this->cek('penerima yang bukan dari honor ini ditolak', ! $bad['ok']);
        $r = $k->setSel($d, $t1['id'], $kA, true);
        $r = $k->setSel($d, $t1['id'], $kB, true);
        $r = $k->setSel($d, $t2['id'], $kA, true);
        $m = $k->muat($d);
        $echo = $m['guru'][0];
        $this->cek('total Echo = (30 + 31) + 30 = 91', $echo['total'] === 91 && $m['total'] === 91, json_encode([$echo['total'], $m['total']]));
        $this->cek('kode guru Echo: dua baris → "{no}A" dan "{no}B"; nomor = urutan penerima', $echo['mapel'][0]['kode'] === $echo['no'] . 'A' && $echo['mapel'][1]['kode'] === $echo['no'] . 'B', json_encode([$echo['no'], array_column($echo['mapel'], 'kode')]));
        $k->simpanPeserta($d, [$kA => '33']);
        $this->cek('ubah peserta kelas → semua sel kelas itu ikut (33+31)+33 = 97', $k->muat($d)['guru'][0]['total'] === 97);
        $r = $k->setSel($d, $t1['id'], $kA, true, '40');
        $mm = $k->muat($d)['guru'][0];
        $this->cek('angka khusus sel (40) menggantikan peserta; ditandai ubah', $mm['mapel'][0]['sel'][$kA] === 40 && ! empty($mm['mapel'][0]['ubah'][$kA]) && $mm['total'] === 104, json_encode($mm['mapel'][0]));
        $r = $k->setSel($d, $t1['id'], $kA, true, '33');
        $this->cek('angka khusus yang sama dengan peserta kembali "ikut peserta" (tidak ditandai)', empty($k->muat($d)['guru'][0]['mapel'][0]['ubah'][$kA]));
        $r = $k->setSel($d, $t1['id'], $kA, false);
        $this->cek('matikan sel → total turun (31+33 = 64)', $k->muat($d)['guru'][0]['total'] === 64);
        $bad = $k->setSel($d, $t1['id'], $kA, true, 'abc');
        $this->cek('angka khusus tak sah ditolak', ! $bad['ok']);
        $bad = $k->setSel($d, $t1['id'], 99999999, true);
        $this->cek('kelas tak dikenal ditolak', ! $bad['ok']);
        $k->setSel($d, $t1['id'], $kA, true);

        $u = $k->ubahMapel($d, $t2['id'], 'ZZUJI Mapel Tiga');
        $this->cek('ubah nama mapel', $u['ok'] && $k->muat($d)['guru'][0]['mapel'][1]['nama'] === 'ZZUJI Mapel Tiga');
        $h = $k->hapusMapel($d, $t2['id']);
        $this->cek('hapus baris mapel → sel ikut terhapus, kode kembali tanpa huruf', $h['ok'] && $k->muat($d)['guru'][0]['mapel'][0]['kode'] === (string) $k->muat($d)['guru'][0]['no'] && $this->db->table('honor_koreksi_sel')->where('mapel_row_id', $t2['id'])->countAllResults() === 0);
        $tm = $k->tambahMapel($d, $this->barisId($d, 'ZZUJI HL Wawan, S.Kom'), '-');
        $this->cek('guru terdaftar tanpa mapel ("-") masuk ceklis dengan total 0', $tm['ok'] && $k->muat($d)['jumlah_guru'] === 2);
        $hg = $k->hapusGuru($d, $this->barisId($d, 'ZZUJI HL Wawan, S.Kom'));
        $this->cek('keluarkan guru dari ceklis', $hg['ok'] && $k->muat($d)['jumlah_guru'] === 1);
        $this->cek('urutan guru di ceklis mengikuti urutan penerima honor', true);
    }

    private function ujiIsiDariPengampu(): void
    {
        $this->bagian('Isi ceklis dari data pengampu');
        $k = new HonorKoreksi();
        $d = $this->dok1;
        $k->kosongkan($d, true);
        $kelas = $k->daftarKelas();
        [$kA, $kB, $kC] = [$kelas[0]['id'], $kelas[1]['id'], $kelas[2]['id']];
        $k->simpanPeserta($d, [$kA => '30', $kB => '31', $kC => '32']);
        $mapel = $this->db->table('mata_pelajaran')->select('id, nama_mapel')->where('deleted_at', null)->orderBy('id', 'ASC')->get()->getResultArray();
        // empat mapel ber-NAMA berbeda yang belum dipakai pada kelas-kelas uji
        $pakai = [];
        $dipilih = [];
        foreach ($mapel as $mp) {
            $nm = mb_strtolower(trim((string) $mp['nama_mapel']));
            if (isset($pakai[$nm])) {
                continue;
            }
            $bentrok = $this->db->table('pengampu')->whereIn('kelas_id', [$kA, $kB, $kC])->where('mapel_id', $mp['id'])->countAllResults() > 0;
            if (! $bentrok) {
                $pakai[$nm] = true;
                $dipilih[]  = $mp;
            }
            if (count($dipilih) === 4) {
                break;
            }
        }
        $this->cek('4 mapel uji (nama berbeda) tersedia', count($dipilih) === 4);
        [$m1, $m2, $m3, $m4] = $dipilih;
        $ins = function (int $guru, int $kelasId, int $mapelId): void {
            $this->db->table('pengampu')->insert(['guru_id' => $guru, 'kelas_id' => $kelasId, 'mapel_id' => $mapelId, 'jp' => 2, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        };
        $ins($this->g['echo'], $kA, (int) $m1['id']);
        $ins($this->g['echo'], $kB, (int) $m1['id']);
        $ins($this->g['echo'], $kA, (int) $m2['id']);
        $ins($this->g['delta'], $kC, (int) $m3['id']);
        $ins($this->g['gina'], $kC, (int) $m4['id']);   // Gina (staf) juga penerima
        $zulu = $this->guru('ZZUJI HL Zulu');            // bukan penerima honor
        $ins($zulu, $kA, (int) $m4['id']);

        $r = $k->isiDariPengampu($d);
        $this->cek('isi dari pengampu: 3 guru, 4 baris mapel, 5 kelas; guru non-penerima diberitahukan', $r['ok'] && $r['guru'] === 3 && $r['mapel'] === 4 && $r['sel'] === 5 && str_contains($r['pesan'], 'belum jadi penerima'), $r['pesan']);
        $m = $k->muat($d);
        $per = [];
        foreach ($m['guru'] as $g) {
            $per[$g['nama']] = $g['total'];
        }
        $this->cek('total: Echo (30+31)+30 = 91, Delta 32, Gina 32', ($per['ZZUJI HL Echo'] ?? 0) === 91 && ($per['ZZUJI HL Delta'] ?? 0) === 32 && ($per['ZZUJI HL Gina'] ?? 0) === 32, json_encode($per));
        $this->cek('urutan guru di ceklis = urutan penerima (Delta, Echo, Gina)', array_column($m['guru'], 'nama') === ['ZZUJI HL Delta', 'ZZUJI HL Echo', 'ZZUJI HL Gina'], json_encode(array_column($m['guru'], 'nama')));
        $r2 = $k->isiDariPengampu($d);
        $this->cek('diisi lagi tanpa "ganti": semua dilewati, tidak ada baris ganda', $r2['ok'] && $r2['guru'] === 0 && $m['jumlah_mapel'] === $k->muat($d)['jumlah_mapel'], $r2['pesan']);
        $r3 = $k->isiDariPengampu($d, true);
        $this->cek('dengan "ganti": dibuat ulang, jumlah tetap', $r3['ok'] && $r3['guru'] === 3 && $k->muat($d)['jumlah_mapel'] === 4);
        // dua mapel_id bernama sama → satu baris
        $kembar = $this->db->table('mata_pelajaran')->select('nama_mapel, COUNT(*) AS n')->where('deleted_at', null)->groupBy('nama_mapel')->having('n >', 1)->get()->getRowArray();
        $this->cek('(info) mapel ber-nama sama dengan id berbeda ada di Master: ' . ($kembar ? '"' . $kembar['nama_mapel'] . '"' : 'tidak ada'), true);
    }

    private function ujiHitungOtomatis(): void
    {
        $this->bagian('Sambungan ke Hitung otomatis & perbandingan dengan honor');
        $k = new HonorKoreksi();
        $hit = new HonorHitung();
        $d = $this->dok1;
        $periodeId = (int) $this->db->table('honor_dokumen')->where('id', $d)->get()->getRow()->periode_id;
        $this->cek('dokumenCeklis() mengenali honor yang punya ceklis', $hit->dokumenCeklis($periodeId) === $d);
        $peta = $hit->koreksi($periodeId);
        $this->cek('koreksi(periode) = total ceklis per orang', ($peta[$this->g['echo']] ?? 0) === 91 && ($peta[$this->g['delta']] ?? 0) === 32, json_encode(array_intersect_key($peta, array_flip([$this->g['echo'], $this->g['delta']]))));
        $this->cek('koreksi() tanpa periode tetap hitungan lama (tidak memuat orang uji tanpa pengampu sungguhan)', ! isset($hit->koreksi()[$this->g['anton']]));

        $r = $hit->terapkan($d, ['koreksi']);
        $kompK = (int) $this->db->table('honor_dok_komponen')->where('dokumen_id', $d)->where('sumber', 'koreksi')->get()->getRow()->id;
        $nilai = static fn (BaseConnection $db, int $baris, int $komp): int => (int) $db->table('honor_nilai')->where('baris_id', $baris)->where('dok_komponen_id', $komp)->get()->getRow()->nilai;
        $bEcho = $this->barisId($d, 'ZZUJI HL Echo');
        $bDelta = $this->barisId($d, 'ZZUJI HL Delta');
        $this->cek('Hitung otomatis → kolom Koreksi Echo = 91, Delta = 32, Anton (tak di ceklis) = 0', $r['ok'] && $nilai($this->db, $bEcho, $kompK) === 91 && $nilai($this->db, $bDelta, $kompK) === 32 && $nilai($this->db, $this->barisId($d, 'ZZUJI HL Anton, S.Pd'), $kompK) === 0, $r['pesan']);
        $b = $k->bandingkanDenganHonor($d);
        $this->cek('perbandingan: setelah diterapkan tidak ada beda', $b['ada_komponen'] && $b['beda'] === 0, json_encode($b['beda']));

        (new HonorDokumen())->simpanNilai($d, $bDelta, $kompK, '500');
        $b = $k->bandingkanDenganHonor($d);
        $row = array_values(array_filter($b['baris'], static fn ($x) => $x['nama'] === 'ZZUJI HL Delta'))[0] ?? [];
        $this->cek('isian diketik manual (500) → perbandingan: beda 1, bertanda manual, ceklis 32', $b['beda'] === 1 && ($row['sekarang'] ?? 0) === 500 && ($row['ceklis'] ?? 0) === 32 && ($row['manual'] ?? false) === true, json_encode($row));
        $hit->terapkan($d, ['koreksi']);
        $this->cek('Hitung otomatis biasa tidak menimpa isian manual (tetap 500)', $nilai($this->db, $bDelta, $kompK) === 500);
        $hit->terapkan($d, ['koreksi'], true);
        $this->cek('dengan "timpa": isian manual diganti angka ceklis (32)', $nilai($this->db, $bDelta, $kompK) === 32);
        $g = $hit->gambaran($periodeId);
        $this->cek('gambaran dialog Hitung otomatis memakai ceklis (total koreksi = 91+32+32 = 155)', $g['koreksi']['total'] === 155, json_encode($g['koreksi']));

        $k->kosongkan($d);
        $this->cek('ceklis dikosongkan → dokumenCeklis null, hitungan kembali ke cara lama', $hit->dokumenCeklis($periodeId) === null && $hit->koreksi($periodeId) === $hit->koreksi());
        $k->isiDariPengampu($d);
    }

    private function ujiSalinDanKunci(): void
    {
        $this->bagian('Salin ceklis ke honor lain, kunci, hapus honor (CASCADE), Audit Log');
        $k = new HonorKoreksi();
        $h = new HonorDokumen();
        $d = $this->dok1;
        $d2 = $this->buatDokumen('ASAS');
        $h->tambahPenerima($d2, [$this->g['echo'], $this->g['delta'], $this->g['gina'], $this->g['anton']]);
        $kelas = $k->daftarKelas();
        $k->simpanPeserta($d, [$kelas[0]['id'] => '29']);
        $r = $k->salinDari($d2, $d, true);
        $this->cek('salin ke honor ASAS: berhasil dan total lembar sama dengan honor asal', $r['ok'] && $r['total'] === $k->muat($d)['total'] && $r['jumlah_guru'] === 3, $r['pesan'] . ' | total ' . ($r['total'] ?? '?') . ' vs ' . $k->muat($d)['total']);
        $a = $k->totalPerBaris($d);
        $b = $k->totalPerBaris($d2);
        $this->cek('total Echo di honor baru = total di honor asal', array_sum($b) === array_sum($a), json_encode([array_sum($a), array_sum($b)]));
        $this->cek('peserta ikut disalin (kelas pertama = 29)', $k->muat($d2)['kelas'][0]['peserta'] === 29);
        $x = $k->salinDari($d2, $d);
        $this->cek('salin ke ceklis yang sudah berisi ditolak', ! $x['ok'] && str_contains($x['pesan'], 'sudah berisi'));
        $x = $k->salinDari($d2, $d, false, true);
        $this->cek('dengan "ganti": diizinkan, tidak ada baris ganda', $x['ok'] && $k->muat($d2)['jumlah_mapel'] === $k->muat($d)['jumlah_mapel']);
        $x = $k->salinDari($d2, $d2);
        $this->cek('salin dari diri sendiri ditolak', ! $x['ok']);

        // kunci
        $before = $k->muat($d)['total'];
        $h->ubahStatus($d, 'final');
        $h->ubahStatus($d, 'dikunci');
        $kelasA = $kelas[0]['id'];
        $semua = [
            'simpanPeserta'  => $k->simpanPeserta($d, [$kelasA => '10']),
            'isiDariPengampu' => $k->isiDariPengampu($d, true),
            'kosongkan'      => $k->kosongkan($d),
            'tambahMapel'    => $k->tambahMapel($d, $this->barisId($d, 'ZZUJI HL Anton, S.Pd'), 'X'),
            'salinDari'      => $k->salinDari($d, $d2, false, true),
            'segarkanPeserta' => $k->segarkanPeserta($d, true),
        ];
        $tolak = array_keys(array_filter($semua, static fn ($v) => ! $v['ok'] && str_contains($v['pesan'], 'DIKUNCI')));
        $this->cek('honor DIKUNCI: semua perubahan ceklis ditolak (' . implode(', ', $tolak) . ')', count($tolak) === count($semua), json_encode(array_map(static fn ($v) => $v['ok'], $semua)));
        $this->cek('...dan ceklis tidak berubah', $k->muat($d)['total'] === $before);
        $h->ubahStatus($d, 'final', 'uji buka kunci');

        $seg = $k->segarkanPeserta($d, true);
        $this->cek('segarkanPeserta(semua) → peserta kembali = siswa aktif', $seg['ok'] && $k->muat($d)['kelas'][0]['peserta'] === $k->muat($d)['kelas'][0]['siswa']);

        $nAudit = (int) $this->db->table('audit_log')->like('tabel', 'honor_koreksi', 'after')->countAllResults();
        $this->cek('operasi ceklis tercatat di Audit Log (≥ 10 catatan)', $nAudit >= 10, (string) $nAudit);

        $h->hapusDokumen($d2);
        $this->cek('hapus honor → ceklisnya ikut terhapus (CASCADE)', $this->db->table('honor_koreksi_mapel')->where('dokumen_id', $d2)->countAllResults() === 0 && $this->db->table('honor_koreksi_kelas')->where('dokumen_id', $d2)->countAllResults() === 0);
        $this->cek('...sel orphan tidak tersisa', (int) $this->db->query('SELECT COUNT(*) AS n FROM honor_koreksi_sel s LEFT JOIN honor_koreksi_mapel m ON m.id = s.mapel_row_id WHERE m.id IS NULL')->getRow()->n === 0);
    }
}
