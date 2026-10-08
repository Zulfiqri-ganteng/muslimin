<?php
/**
 * Dua kotak dialog PKL (perilaku: assets/js/admin/pkl-unduh.js):
 *   1. "Catat biaya" — wajib sebelum surat diunduh (Biaya PKL, SPP, Tabungan Wajib, Iuran OSIS, Beasiswa, Keringanan);
 *   2. "Kabari lewat WhatsApp" — daftar siswa + tautan wa.me, dikirim manual oleh staf.
 * Dibuka lewat window.dispatchEvent(new CustomEvent('pkl-unduh', …)) / ('pkl-wa', …). Hanya dimuat untuk peran berhak 'surat'.
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
?>
<!-- ===================== Dialog 1: catat biaya lalu unduh ===================== -->
<div x-data="pklUnduh" data-config="<?= $cfgAttr ?>" @keydown.escape.window="tutup()">
    <div x-show="buka" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="judulBiaya">
        <div class="absolute inset-0 bg-slate-900/50" @click="tutup()"></div>

        <form method="post" :action="tujuan" data-unduh data-pkl-wa @submit="cek($event)"
              class="relative flex max-h-[94vh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:max-w-3xl sm:rounded-2xl">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" :value="modeKirim">
            <template x-if="tujuan === cfg.urlMassal && modeKirim === 'terpilih'">
                <div><template x-for="i in ids" :key="i"><input type="hidden" name="ids[]" :value="i"></template></div>
            </template>
            <template x-if="perluTanggal && tujuan !== cfg.urlMassal">
                <input type="hidden" name="tanggal_surat" :value="tanggalSurat">
            </template>

            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 id="judulBiaya" class="text-lg font-bold text-slate-800">Catat biaya, lalu unduh surat</h3>
                    <p class="mt-0.5 text-xs text-slate-500" x-show="data" x-text="data ? (data.surat.length + ' surat · ' + data.siswa.length + ' siswa') : ''"></p>
                </div>
                <button type="button" @click="tutup()" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4 text-sm text-slate-600">
                <p x-show="memuat" class="py-8 text-center text-slate-500">Memuat data biaya siswa…</p>
                <p x-show="galat" x-text="galat" class="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm font-semibold leading-relaxed text-red-800"></p>

                <template x-if="data">
                    <div class="space-y-4">
                        <p class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-xs leading-relaxed text-blue-900">
                            Catat biaya yang <b>sudah diterima</b> dari tiap siswa. <b>Wajib minimal satu</b> centang per siswa (atau Beasiswa / Keringanan beralasan).
                            Biaya yang sudah tercatat tidak diminta lagi dan tidak digandakan.
                        </p>

                        <!-- ========== SATU SURAT: per siswa ========== -->
                        <template x-if="mode === 'satu'">
                            <div class="space-y-3">
                                <div class="rounded-xl bg-slate-50 px-4 py-2.5 text-xs text-slate-600" x-show="data.surat[0]">
                                    <b class="text-slate-800" x-text="data.surat[0].perusahaan"></b>
                                    <span x-show="data.surat[0].nomor"> · surat No. <span class="font-mono font-semibold" x-text="data.surat[0].nomor"></span></span>
                                </div>
                                <div x-show="perluTanggal" class="max-w-xs">
                                    <label class="lbl" for="tglSuratBiaya">Tanggal surat</label>
                                    <input id="tglSuratBiaya" type="date" x-model="tanggalSurat" class="inp">
                                    <p class="mt-1 text-xs text-slate-400">Nomor surat ditetapkan sekali, saat surat pertama diunduh.</p>
                                </div>

                                <template x-for="s in data.siswa" :key="s.siswa_id">
                                    <section class="rounded-xl border p-4" :class="memenuhi(s) ? 'border-slate-200' : 'border-amber-300 bg-amber-50/40'">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <p class="font-semibold text-slate-800" x-text="s.nama"></p>
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="s.peran === 'pengaju' ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-500'" x-text="s.peran === 'pengaju' ? 'PENGAJU' : 'TEMAN'"></span>
                                            <span class="text-xs text-slate-400" x-text="s.kelas"></span>
                                        </div>
                                        <p class="mt-1 text-xs text-green-700" x-show="catatanTercatat(s).length" x-text="'Sudah tercatat: ' + catatanTercatat(s).join('; ')"></p>

                                        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            <template x-for="j in data.jenis" :key="j.kode">
                                                <div>
                                                    <template x-if="j.kode === 'spp' && bebasSpp(s)">
                                                        <p class="rounded-lg bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-800">🎓 SPP dibebaskan (beasiswa)</p>
                                                    </template>
                                                    <template x-if="!(j.kode === 'spp' && bebasSpp(s)) && tercatat(s, j)">
                                                        <div class="rounded-lg bg-green-50 px-3 py-2 text-xs font-semibold text-green-800">
                                                            <input type="hidden" :name="'biaya[' + s.siswa_id + '][jenis][]'" :value="j.kode">
                                                            ✓ <span x-text="j.nama"></span> sudah dicatat
                                                        </div>
                                                    </template>
                                                    <template x-if="!(j.kode === 'spp' && bebasSpp(s)) && !tercatat(s, j)">
                                                        <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                                            <input type="checkbox" class="h-4 w-4" :name="'biaya[' + s.siswa_id + '][jenis][]'" :value="j.kode" x-model="per[s.siswa_id].jenis">
                                                            <span class="min-w-0 flex-1 text-sm font-semibold text-slate-700" x-text="j.nama"></span>
                                                            <span class="shrink-0 text-xs text-slate-500" x-text="rupiah(j.nominal) + (j.siklus === 'bulanan' ? ' /bln' : '')"></span>
                                                        </label>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>

                                        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-slate-600" x-show="bulananAda()">
                                            <span>Biaya bulanan untuk bulan</span>
                                            <select class="inp !w-auto !py-1.5 text-xs" :name="'biaya[' + s.siswa_id + '][bulan]'" x-model="per[s.siswa_id].bulan">
                                                <template x-for="o in opsiBulan()" :key="o.v"><option :value="o.v" x-text="o.t"></option></template>
                                            </select>
                                            <span>sebanyak</span>
                                            <input type="number" min="1" max="12" class="inp !w-16 !py-1.5 text-xs" :name="'biaya[' + s.siswa_id + '][jumlah_bulan]'" x-model.number="per[s.siswa_id].jumlah_bulan">
                                            <span>bulan</span>
                                        </div>

                                        <div class="mt-3 space-y-2 border-t border-slate-100 pt-3 text-xs">
                                            <template x-if="s.beasiswa">
                                                <p class="font-semibold text-indigo-800">🎓 Penerima beasiswa 3 tahun (<span x-text="s.beasiswa.sumber_label"></span>)<span x-show="s.beasiswa.berakhir_at" x-text="' s.d. ' + s.beasiswa.berakhir_at"></span> — SPP dibebaskan.</p>
                                            </template>
                                            <template x-if="!s.beasiswa">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="text-slate-600">Beasiswa 3 tahun (membebaskan SPP):</span>
                                                    <select class="inp !w-auto !py-1.5 text-xs" :name="'biaya[' + s.siswa_id + '][beasiswa]'" x-model="per[s.siswa_id].beasiswa">
                                                        <option value="">Tidak</option>
                                                        <option value="sktm">Ya — SKTM</option>
                                                        <option value="yayasan">Ya — Kebijakan Yayasan</option>
                                                        <option value="lainnya">Ya — Lainnya</option>
                                                    </select>
                                                    <input type="text" maxlength="150" class="inp !w-56 !py-1.5 text-xs" placeholder="Keterangan (opsional)" x-show="per[s.siswa_id].beasiswa" :name="'biaya[' + s.siswa_id + '][beasiswa_ket]'" x-model="per[s.siswa_id].beasiswa_ket">
                                                </div>
                                            </template>
                                            <label class="flex cursor-pointer items-center gap-2 text-slate-600">
                                                <input type="checkbox" class="h-4 w-4" x-model="per[s.siswa_id].ringan"> Keringanan / pembayaran ditunda <span class="text-slate-400">(wajib beralasan)</span>
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
                                <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-xs font-semibold leading-relaxed text-amber-900">
                                    Pilihan di bawah berlaku untuk <b x-text="data.siswa.length + ' siswa pada ' + data.surat.length + ' surat'"></b> sekaligus
                                    (yang sudah tercatat dilewati). Bila tiap siswa berbeda — ada beasiswa atau keringanan — unduh per surat saja.
                                </div>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <template x-for="j in data.jenis" :key="j.kode">
                                        <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                            <input type="checkbox" class="h-4 w-4" name="semua[jenis][]" :value="j.kode" x-model="semua.jenis">
                                            <span class="min-w-0 flex-1 text-sm font-semibold text-slate-700" x-text="j.nama"></span>
                                            <span class="shrink-0 text-xs text-slate-500" x-text="rupiah(j.nominal) + (j.siklus === 'bulanan' ? ' /bln' : '')"></span>
                                        </label>
                                    </template>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-slate-600" x-show="bulananAda()">
                                    <span>Biaya bulanan untuk bulan</span>
                                    <select class="inp !w-auto !py-1.5 text-xs" name="semua[bulan]" x-model="semua.bulan">
                                        <template x-for="o in opsiBulan()" :key="o.v"><option :value="o.v" x-text="o.t"></option></template>
                                    </select>
                                    <span>sebanyak</span>
                                    <input type="number" min="1" max="12" class="inp !w-16 !py-1.5 text-xs" name="semua[jumlah_bulan]" x-model.number="semua.jumlah_bulan">
                                    <span>bulan</span>
                                </div>

                                <p class="text-xs font-semibold text-red-700" x-show="kurang().length > 0"
                                   x-text="kurang().length + ' siswa belum punya catatan biaya — centang minimal satu biaya di atas.'"></p>

                                <button type="button" @click="daftarTerbuka = !daftarTerbuka" class="text-xs font-semibold text-brand-700 hover:underline" x-text="daftarTerbuka ? 'Sembunyikan daftar siswa' : 'Lihat daftar siswa'"></button>
                                <ul x-show="daftarTerbuka" class="max-h-56 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200 text-xs">
                                    <template x-for="s in data.siswa" :key="s.siswa_id">
                                        <li class="flex items-start justify-between gap-3 px-3 py-2">
                                            <span><b class="text-slate-700" x-text="s.nama"></b> <span class="text-slate-400" x-text="s.kelas"></span>
                                                <span x-show="s.beasiswa" class="ml-1 rounded bg-indigo-100 px-1 text-[10px] font-bold text-indigo-700">BEASISWA</span></span>
                                            <span class="text-right" :class="memenuhi(s) ? 'text-green-700' : 'font-semibold text-amber-700'"
                                                  x-text="catatanTercatat(s).length ? catatanTercatat(s).join('; ') : (memenuhi(s) ? 'akan dicatat' : 'belum ada catatan')"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500" x-show="data">Akan dicatat sekarang: <b class="text-slate-800" x-text="rupiah(totalBaru())"></b></p>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" @click="tutup()" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Batal</button>
                    <button type="submit" :disabled="!data || memuat" class="rounded-xl bg-brand-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-brand-800 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50">⬇ Catat biaya &amp; unduh surat</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ===================== Dialog 2: kabari siswa lewat WhatsApp ===================== -->
<div x-data="pklWa" data-config="<?= $cfgAttr ?>" @keydown.escape.window="buka && tutup()">
    <div x-show="buka" x-cloak x-transition.opacity.duration.150ms class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="judulWa">
        <div class="absolute inset-0 bg-slate-900/50" @click="tutup()"></div>
        <div class="relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:max-w-2xl sm:rounded-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 id="judulWa" class="text-lg font-bold text-slate-800">Kabari siswa lewat WhatsApp</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Tekan tombol hijau: WhatsApp terbuka dengan pesan siap kirim; Anda tinggal menekan Kirim. <span x-show="daftar.length" x-text="'Belum dikabari: ' + belumDikabari() + ' dari ' + daftar.length"></span></p>
                </div>
                <button type="button" @click="tutup()" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto">
                <p x-show="memuat" class="px-5 py-8 text-center text-sm text-slate-500">Memuat…</p>
                <p x-show="galat" x-text="galat" class="m-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900"></p>
                <ul class="divide-y divide-slate-100">
                    <template x-for="r in daftar" :key="r.ajuan_id + '-' + r.siswa_id">
                        <li class="flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-800" x-text="r.nama"></p>
                                <p class="text-xs text-slate-500"><span x-text="r.kelas"></span> · <span x-text="r.perusahaan"></span></p>
                                <p class="text-xs text-slate-400"><span x-text="r.hp || 'Nomor HP kosong'"></span>
                                    <span x-show="r.alasan_tidak" class="font-semibold text-red-600" x-text="' — ' + r.alasan_tidak"></span></p>
                                <p class="text-xs font-semibold text-green-700" x-show="r.dikabari_at" x-text="'✓ Sudah dikabari ' + waktu(r.dikabari_at) + (r.dikabari_oleh ? ' oleh ' + r.dikabari_oleh : '')"></p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button type="button" @click="salin(r)" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50" x-text="tersalin === r.siswa_id ? '✓ Tersalin' : 'Salin pesan'"></button>
                                <a x-show="r.url" :href="r.url" target="_blank" rel="noopener noreferrer" @click="tandai(r)"
                                   class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3.5 py-2 text-sm font-bold text-white transition hover:bg-green-700 active:scale-95"
                                   x-text="r.dikabari_at ? 'Kirim ulang' : 'Kabari via WA'"></a>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
            <div class="flex justify-end border-t border-slate-100 bg-slate-50 px-5 py-3.5">
                <button type="button" @click="tutup()" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Tutup</button>
            </div>
        </div>
    </div>
</div>
<script defer src="<?= base_url('assets/js/admin/pkl-unduh.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/admin/pkl-unduh.js') ?>"></script>
