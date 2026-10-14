/**
 * Jadwal guru piket (Absensi → Piket) — rombak tampilan F6: dulu fungsi global jadwalPiket(cfg) di <script> sebaris dengan
 * x-data='jadwalPiket({...})' dan isian berisi $event & penugasan bersarang (tidak didukung Alpine versi CSP). Kini komponen
 * Alpine.data('jadwalPiket') di berkas ini: konfigurasi dibaca dari data-cfg (JSON), pilihan "+ tambah guru piket" ditangkap lewat
 * delegasi event 'change' (select[data-tambah-piket] dengan data-hari & data-shift), pemeriksaan di isian = metode sederhana.
 * Grid: [hari_id][pagi|siang] => [guru_id…], dikirim sebagai JSON lewat <input x-ref="json"> saat formulir dikirim.
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('jadwalPiket', function () {
        var akar = null; // elemen root (closure, bukan data reaktif)
        return {
            hari: [],
            guru: [],
            grid: {},
            shift: ['pagi', 'siang'],

            init: function () {
                var self = this;
                akar = this.$el;
                var cfg = window.readJson(akar, 'cfg', { hari: [], grid: {}, guru: [] });
                var grid = {};
                (cfg.hari || []).forEach(function (h) {
                    var g = (cfg.grid || {})[h.id] || {};
                    grid[h.id] = { pagi: (g.pagi || []).map(Number), siang: (g.siang || []).map(Number) };
                });
                this.hari = cfg.hari || [];
                this.guru = cfg.guru || [];
                this.grid = grid;
                // "+ tambah guru piket…" → tambahkan lalu kembalikan pilihan ke baris pertama.
                akar.addEventListener('change', function (e) {
                    var s = e.target.closest ? e.target.closest('select[data-tambah-piket]') : null;
                    if (!s || !akar.contains(s)) { return; }
                    self.tambah(Number(s.getAttribute('data-hari')), s.getAttribute('data-shift'), s.value);
                    s.selectedIndex = 0;
                });
            },

            daftar: function (hid, sh) { return (this.grid[hid] && this.grid[hid][sh]) || []; },
            kosong: function (hid, sh) { return this.daftar(hid, sh).length === 0; },
            sudahAda: function (hid, sh, id) { return this.daftar(hid, sh).indexOf(Number(id)) !== -1; },
            nama: function (id) {
                var g = this.guru.filter(function (x) { return x.id === id; })[0];
                return g ? g.nama : ('Guru #' + id);
            },
            tambah: function (hid, sh, val) {
                var id = Number(val);
                if (id && this.grid[hid] && this.grid[hid][sh].indexOf(id) === -1) { this.grid[hid][sh].push(id); }
            },
            hapus: function (hid, sh, id) {
                this.grid[hid][sh] = this.grid[hid][sh].filter(function (x) { return x !== id; });
            },
            siapkanKirim: function () { this.$refs.json.value = JSON.stringify(this.grid); },
        };
    });
});
