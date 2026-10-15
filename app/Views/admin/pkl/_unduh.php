<?php
/**
 * Dua kotak dialog PKL (perilaku: assets/js/admin/pkl-unduh.js):
 *   1. "Catat biaya" — wajib sebelum surat diunduh (Biaya PKL, SPP, Tabungan Wajib, Iuran OSIS, Beasiswa, Keringanan).
 *      Bulan terpilih otomatis per siswa (bulan_saran). Cetak ulang yang semua siswanya sudah tercatat langsung
 *      diunduh tanpa kotak ini (kecuali dibuka lewat "Catat biaya lain" = paksa);
 *   2. "Kabari lewat WhatsApp" — daftar siswa + tautan wa.me, dikirim manual oleh staf; muncul sendiri sesudah unduh
 *      hanya bila masih ada siswa yang belum dikabari.
 * PENTING: tiap <option> bulan memakai :selected — x-model pada <select> yang opsinya dibuat x-for tidak memilih opsi
 * saat opsi baru muncul, sehingga browser menampilkan & MENGIRIM opsi pertama (dulu: 2 bulan ke depan).
 * Rombak tampilan F9: bingkai dialog kit (.dialog + :data-buka, kepala/kaki diam, isi bergulir), ikon pengganti emoji,
 * kotak centang .cek, tombol kit; skrip ber-nonce. Isian Alpine tidak berubah selain rupiah() → teksRupiah().
 * Dibuka lewat tombol ber-atribut data-pkl-unduh / data-pkl-unduh-ajuan / data-pkl-wa-ajuan / data-pkl-wa-terpilih (delegasi
 * di pkl-unduh.js; event 'pkl-unduh' / 'pkl-wa' tetap). Hanya dimuat untuk peran berhak 'surat'.
 */
$awal = rtrim(site_url('admin/pkl'), '/') . '/';
$cfg = [
    'urlSiap'      => site_url('admin/pkl/surat/siap'),
    'urlMassal'    => site_url('admin/pkl/surat-massal'),
    'urlSuratAwal' => $awal,
    'urlSuratAkhir' => '/surat',
    'urlHasil'     => site_url('admin/pkl/surat/hasil'),
    'urlWa'        => site_url('admin/pkl/wa'),
    'urlTandaiAwal' => $awal,
    'csrfName'     => csrf_token(),
    'hariIni'      => date('Y-m-d'),
];
$cfgAttr = esc(json_encode($cfg), 'attr');
// Kotak pilihan biaya: berbingkai, menyala saat dicentang.
$kotakPilih = 'flex cursor-pointer items-center gap-2.5 rounded-lg border border-gray-200 px-3 py-2 transition-colors has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:border-gray-600 dark:has-[:checked]:bg-gray-700';
?>
<!-- ===================== Dialog 1: catat biaya lalu unduh ===================== -->
<div x-data="pklUnduh" data-config="<?= $cfgAttr ?>" @keydown.escape.window="tutup()">
    <div class="dialog" :data-buka="buka" role="dialog" aria-modal="true" aria-labelledby="judulBiaya">
        <div class="dialog-latar" @click="tutup()" aria-hidden="true"></div>

        <form method="post" :action="tujuan" data-unduh data-pkl-wa @submit="cek($event)" x-ref="form"
              class="dialog-panel dialog-panel-xl flex max-h-[calc(100vh-2rem)] flex-col" tabindex="-1">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" :value="modeKirim">
            <template x-if="tujuan === cfg.urlMassal && modeKirim === 'terpilih'">
                <div><template x-for="i in ids" :key="i"><input type="hidden" name="ids[]" :value="i"></template></div>
            </template>
            <template x-if="perluTanggal && tujuan !== cfg.urlMassal">
                <input type="hidden" name="tanggal_surat" :value="tanggalSurat">
            </template>

            <div class="dialog-kepala">
                <div class="min-w-0">
                    <h3 id="judulBiaya" class="dialog-judul">Catat biaya, lalu unduh surat</h3>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400" x-show="data" x-text="data ? (data.surat.length + ' surat · ' + data.siswa.length + ' siswa') : ''"></p>
                </div>
                <button type="button" class="tombol-tutup" @click="tutup()" aria-label="Tutup"><?= ikon('close', 'h-5 w-5') ?></button>
            </div>

            <div class="dialog-isi flex-1 overflow-y-auto text-sm text-gray-600 dark:text-gray-300">
                <p x-show="memuat" class="py-8 text-center text-gray-500 dark:text-gray-400">Memuat data biaya siswa…</p>
                <div x-show="galat" class="peringatan peringatan-galat" role="alert"><p class="font-medium" x-text="galat"></p></div>

                <template x-if="data && !langsung">
                    <div class="space-y-4">
                        <div class="peringatan peringatan-info">
                            <p>Catat biaya yang <b>sudah diterima</b> dari tiap siswa. <b>Wajib minimal satu</b> centang per siswa (atau Beasiswa / Keringanan beralasan).
                                Biaya yang sudah tercatat tidak diminta lagi dan tidak digandakan.</p>
                        </div>

                        <!-- ========== SATU SURAT: per siswa ========== -->
                        <template x-if="mode === 'satu'">
                            <div class="space-y-3">
                                <div class="rounded-lg bg-gray-50 px-4 py-2.5 dark:bg-gray-700" x-show="data.surat[0]">
                                    <b class="text-gray-900 dark:text-white" x-text="data.surat[0].perusahaan"></b>
                                    <span x-show="data.surat[0].nomor"> · surat No. <span class="font-mono font-semibold" x-text="data.surat[0].nomor"></span></span>
                                </div>
                                <div x-show="perluTanggal" class="max-w-xs">
                                    <label class="lbl" for="tglSuratBiaya">Tanggal surat</label>
                                    <input id="tglSuratBiaya" type="date" x-model="tanggalSurat" class="inp">
                                    <p class="hint">Nomor surat ditetapkan sekali, saat surat pertama diunduh.</p>
                                </div>

                                <template x-for="s in data.siswa" :key="s.siswa_id">
                                    <section class="rounded-lg border p-4" :class="memenuhi(s) ? 'border-gray-200 dark:border-gray-600' : 'border-yellow-300 bg-yellow-50/60 dark:border-yellow-700 dark:bg-yellow-900/20'">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <p class="font-semibold text-gray-900 dark:text-white" x-text="s.nama"></p>
                                            <span class="lencana" :class="s.peran === 'pengaju' ? 'lencana-merek' : 'lencana-abu'" x-text="s.peran === 'pengaju' ? 'Pengaju' : 'Teman'"></span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400" x-text="s.kelas"></span>
                                        </div>
                                        <p class="mt-1 text-xs text-green-700 dark:text-green-400" x-show="catatanTercatat(s).length" x-text="'Sudah tercatat: ' + catatanTercatat(s).join('; ')"></p>

                                        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            <template x-for="j in data.jenis" :key="j.kode">
                                                <div>
                                                    <template x-if="j.kode === 'spp' && bebasSpp(s)">
                                                        <p class="flex items-center gap-1.5 rounded-lg bg-brand-50 px-3 py-2 text-xs font-medium text-brand-800 dark:bg-gray-700 dark:text-brand-300"><?= ikon('award', 'h-4 w-4 shrink-0') ?>SPP dibebaskan (beasiswa)</p>
                                                    </template>
                                                    <template x-if="!(j.kode === 'spp' && bebasSpp(s)) && tercatat(s, j)">
                                                        <div class="flex items-center gap-1.5 rounded-lg bg-green-50 px-3 py-2 text-xs font-medium text-green-800 dark:bg-gray-700 dark:text-green-400">
                                                            <input type="hidden" :name="'biaya[' + s.siswa_id + '][jenis][]'" :value="j.kode">
                                                            <?= ikon('check', 'h-4 w-4 shrink-0') ?><span><span x-text="j.nama"></span> sudah dicatat</span>
                                                        </div>
                                                    </template>
                                                    <template x-if="!(j.kode === 'spp' && bebasSpp(s)) && !tercatat(s, j)">
                                                        <label class="<?= $kotakPilih ?>">
                                                            <input type="checkbox" class="cek" :name="'biaya[' + s.siswa_id + '][jenis][]'" :value="j.kode" x-model="per[s.siswa_id].jenis">
                                                            <span class="min-w-0 flex-1 text-sm font-medium text-gray-900 dark:text-white" x-text="j.nama"></span>
                                                            <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400" x-text="teksRupiah(j.nominal) + (j.siklus === 'bulanan' ? ' /bln' : '')"></span>
                                                        </label>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>

                                        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs" x-show="bulananAda()">
                                            <span>Biaya bulanan untuk bulan</span>
                                            <select class="inp !w-auto !py-1.5 text-xs" :name="'biaya[' + s.siswa_id + '][bulan]'" x-model="per[s.siswa_id].bulan">
                                                <template x-for="o in opsiBulan()" :key="o.v"><option :value="o.v" x-text="o.t" :selected="o.v === per[s.siswa_id].bulan"></option></template>
                                            </select>
                                            <span>sebanyak</span>
                                            <input type="number" min="1" max="12" class="inp !w-16 !py-1.5 text-xs" :name="'biaya[' + s.siswa_id + '][jumlah_bulan]'" x-model.number="per[s.siswa_id].jumlah_bulan">
                                            <span>bulan</span>
                                            <span class="w-full text-gray-500 dark:text-gray-400">Bulan terpilih otomatis: bulan pertama yang belum lunas. Ganti hanya bila siswa membayar bulan lain.</span>
                                        </div>

                                        <div class="mt-3 space-y-2 border-t border-gray-200 pt-3 text-xs dark:border-gray-600">
                                            <template x-if="s.beasiswa">
                                                <p class="flex flex-wrap items-center gap-1.5 font-medium text-brand-800 dark:text-brand-300"><?= ikon('award', 'h-4 w-4 shrink-0') ?><span>Penerima beasiswa 3 tahun (<span x-text="s.beasiswa.sumber_label"></span>)<span x-show="s.beasiswa.berakhir_at" x-text="' s.d. ' + s.beasiswa.berakhir_at"></span> — SPP dibebaskan.</span></p>
                                            </template>
                                            <template x-if="!s.beasiswa">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span>Beasiswa 3 tahun (membebaskan SPP):</span>
                                                    <select class="inp !w-auto !py-1.5 text-xs" :name="'biaya[' + s.siswa_id + '][beasiswa]'" x-model="per[s.siswa_id].beasiswa">
                                                        <option value="">Tidak</option>
                                                        <option value="sktm">Ya — SKTM</option>
                                                        <option value="yayasan">Ya — Kebijakan Yayasan</option>
                                                        <option value="lainnya">Ya — Lainnya</option>
                                                    </select>
                                                    <input type="text" maxlength="150" class="inp !w-56 !py-1.5 text-xs" placeholder="Keterangan (opsional)" x-show="per[s.siswa_id].beasiswa" :name="'biaya[' + s.siswa_id + '][beasiswa_ket]'" x-model="per[s.siswa_id].beasiswa_ket">
                                                </div>
                                            </template>
                                            <label class="flex cursor-pointer items-center gap-2">
                                                <input type="checkbox" class="cek" x-model="per[s.siswa_id].ringan"> Keringanan / pembayaran ditunda <span class="text-gray-500 dark:text-gray-400">(wajib beralasan)</span>
                                            </label>
                                            <textarea rows="2" minlength="5" maxlength="255" class="inp text-xs" placeholder="Alasan keringanan / penundaan (minimal 5 huruf)"
                                                      x-show="per[s.siswa_id].ringan" :disabled="!per[s.siswa_id].ringan" :name="'biaya[' + s.siswa_id + '][keringanan]'" x-model="per[s.siswa_id].keringanan"></textarea>
                                        </div>
                                    </section>
                                </template>
                            </div>
                        </template>

                        <!-- ========== BANYAK SURAT: satu set untuk semua ========== -->
                        <template x-if="mode === 'massal'">
                            <div class="space-y-3">
                                <div class="peringatan peringatan-awas">
                                    <p>Pilihan di bawah berlaku untuk <b x-text="data.siswa.length + ' siswa pada ' + data.surat.length + ' surat'"></b> sekaligus
                                        (yang sudah tercatat dilewati). Bila tiap siswa berbeda — ada beasiswa atau keringanan — unduh per surat saja.</p>
                                </div>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <template x-for="j in data.jenis" :key="j.kode">
                                        <label class="<?= $kotakPilih ?>">
                                            <input type="checkbox" class="cek" name="semua[jenis][]" :value="j.kode" x-model="semua.jenis">
                                            <span class="min-w-0 flex-1 text-sm font-medium text-gray-900 dark:text-white" x-text="j.nama"></span>
                                            <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400" x-text="teksRupiah(j.nominal) + (j.siklus === 'bulanan' ? ' /bln' : '')"></span>
                                        </label>
                                    </template>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs" x-show="bulananAda()">
                                    <span>Biaya bulanan untuk bulan</span>
                                    <select class="inp !w-auto !py-1.5 text-xs" name="semua[bulan]" x-model="semua.bulan">
                                        <template x-for="o in opsiBulan()" :key="o.v"><option :value="o.v" x-text="o.t" :selected="o.v === semua.bulan"></option></template>
                                    </select>
                                    <span>sebanyak</span>
                                    <input type="number" min="1" max="12" class="inp !w-16 !py-1.5 text-xs" name="semua[jumlah_bulan]" x-model.number="semua.jumlah_bulan">
                                    <span>bulan</span>
                                </div>

                                <p class="text-xs font-semibold text-red-700 dark:text-red-400" x-show="kurang().length > 0"
                                   x-text="kurang().length + ' siswa belum punya catatan biaya — centang minimal satu biaya di atas.'"></p>

                                <button type="button" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-400" @click="daftarTerbuka = !daftarTerbuka" x-text="daftarTerbuka ? 'Sembunyikan daftar siswa' : 'Lihat daftar siswa'"></button>
                                <ul x-show="daftarTerbuka" class="max-h-56 divide-y divide-gray-200 overflow-y-auto rounded-lg border border-gray-200 text-xs dark:divide-gray-600 dark:border-gray-600">
                                    <template x-for="s in data.siswa" :key="s.siswa_id">
                                        <li class="flex items-start justify-between gap-3 px-3 py-2">
                                            <span><b class="text-gray-900 dark:text-white" x-text="s.nama"></b> <span class="text-gray-500 dark:text-gray-400" x-text="s.kelas"></span>
                                                <span x-show="s.beasiswa" class="lencana lencana-merek ms-1 px-1.5 py-0 text-[10px]">Beasiswa</span></span>
                                            <span class="text-right" :class="memenuhi(s) ? 'text-green-700 dark:text-green-400' : 'font-semibold text-yellow-700 dark:text-yellow-400'"
                                                  x-text="catatanTercatat(s).length ? catatanTercatat(s).join('; ') : (memenuhi(s) ? 'akan dicatat' : 'belum ada catatan')"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="dialog-kaki justify-between">
                <p class="text-sm text-gray-500 dark:text-gray-400" x-show="data">Akan dicatat sekarang: <b class="text-gray-900 dark:text-white" x-text="teksRupiah(totalBaru())"></b></p>
                <div class="ms-auto flex flex-wrap justify-end gap-3">
                    <button type="button" class="btn btn-netral" @click="tutup()">Batal</button>
                    <button type="submit" class="btn btn-utama" :disabled="!data || memuat"><?= ikon('download', 'h-4 w-4') ?>Simpan biaya &amp; unduh surat</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ===================== Dialog 2: kabari siswa lewat WhatsApp ===================== -->
<div x-data="pklWa" data-config="<?= $cfgAttr ?>" @keydown.escape.window="buka && tutup()">
    <div class="dialog" :data-buka="buka" role="dialog" aria-modal="true" aria-labelledby="judulWa">
        <div class="dialog-latar" @click="tutup()" aria-hidden="true"></div>
        <div class="dialog-panel dialog-panel-lg flex max-h-[calc(100vh-2rem)] flex-col" tabindex="-1">
            <div class="dialog-kepala">
                <div class="min-w-0">
                    <h3 id="judulWa" class="dialog-judul">Kabari siswa lewat WhatsApp</h3>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Tekan tombol hijau: WhatsApp terbuka dengan pesan siap kirim; Anda tinggal menekan Kirim. <span x-show="daftar.length" x-text="'Belum dikabari: ' + belumDikabari() + ' dari ' + daftar.length"></span></p>
                </div>
                <button type="button" class="tombol-tutup" @click="tutup()" aria-label="Tutup"><?= ikon('close', 'h-5 w-5') ?></button>
            </div>
            <div class="flex-1 overflow-y-auto">
                <p x-show="memuat" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Memuat…</p>
                <div x-show="galat" class="peringatan peringatan-awas m-4 md:m-5"><p class="font-medium" x-text="galat"></p></div>
                <ul class="divide-y divide-gray-200 dark:divide-gray-600">
                    <template x-for="r in daftar" :key="r.ajuan_id + '-' + r.siswa_id">
                        <li class="flex flex-col gap-2 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between md:px-5">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 dark:text-white" x-text="r.nama"></p>
                                <p class="text-sm text-gray-500 dark:text-gray-400"><span x-text="r.kelas"></span> · <span x-text="r.perusahaan"></span></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><span x-text="r.hp || 'Nomor HP kosong'"></span>
                                    <span x-show="r.alasan_tidak" class="font-semibold text-red-600 dark:text-red-400" x-text="' — ' + r.alasan_tidak"></span></p>
                                <p class="mt-0.5 flex items-center gap-1 text-xs font-semibold text-green-700 dark:text-green-400" x-show="r.dikabari_at"><?= ikon('check', 'h-3.5 w-3.5 shrink-0') ?><span x-text="'Sudah dikabari ' + waktu(r.dikabari_at) + (r.dikabari_oleh ? ' oleh ' + r.dikabari_oleh : '')"></span></p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button type="button" class="btn btn-netral btn-sm" @click="salin(r)"><?= ikon('file-copy', 'h-4 w-4') ?><span x-text="tersalin === r.siswa_id ? 'Tersalin' : 'Salin pesan'"></span></button>
                                <a x-show="r.url" :href="r.url" target="_blank" rel="noopener noreferrer" @click="tandai(r)" class="btn btn-sukses btn-sm"><?= ikon('whatsapp', 'h-4 w-4', 'solid') ?><span x-text="r.dikabari_at ? 'Kirim ulang' : 'Kabari via WA'"></span></a>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
            <div class="dialog-kaki">
                <button type="button" class="btn btn-netral" @click="tutup()">Tutup</button>
            </div>
        </div>
    </div>
</div>
<script defer src="<?= base_url('assets/js/admin/pkl-unduh.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/pkl-unduh.js') ?>" <?= csp_script_nonce() ?>></script>
