/**
 * Dashboard admin (rombak tampilan F4): grafik ApexCharts dengan pola template Flowbite Admin Dashboard (src/charts.js).
 * Tiap grafik = <div class="grafik" data-grafik="jenis" data-isi="JSON"> — data dari server lewat data-* (tanpa skrip sebaris).
 * Jenis: tren (area, kehadiran guru 7/30/90 hari), tingkat & batas (batang kecil di kartu angka), radial (persen), donat
 * (siswa per jurusan, absensi hari ini [data-warna="status"], kesediaan), beban (batang mendatar + garis batas maksimal).
 * Warna dibaca dari variabel CSS --grafik-* (admin.css) → ikut mode Terang / Gelap / Warna Sekolah; bila <html data-mode
 * data-warna> berubah (pratinjau tema), semua grafik digambar ulang. Tooltip dibuat sendiri (kelas .grafik-tip, tanpa atribut
 * style) dan CSS ApexCharts = berkas <link id="apexcharts-css"> → tidak ada <style> sisipan (siap CSP). Animasi tumbuh halus
 * mati bila Windows disetel "kurangi gerakan". Semua angka juga tertulis sebagai teks di halaman.
 */
(function () {
    'use strict';

    if (typeof window.ApexCharts === 'undefined') { return; }

    var html = document.documentElement;
    var angka = new Intl.NumberFormat('id-ID');
    var satuDesimal = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    var BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    var BULAN_PANJANG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    var HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    var STATUS = [['telat', 'Telat'], ['izin', 'Izin'], ['sakit', 'Sakit'], ['alpa', 'Alpa']];
    var LOKAL = {
        name: 'id',
        options: {
            months: BULAN_PANJANG, shortMonths: BULAN, days: HARI, shortDays: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
            toolbar: { exportToSVG: 'Unduh SVG', exportToPNG: 'Unduh PNG', exportToCSV: 'Unduh CSV', menu: 'Menu', selection: 'Pilihan',
                selectionZoom: 'Perbesar pilihan', zoomIn: 'Perbesar', zoomOut: 'Perkecil', pan: 'Geser', reset: 'Atur ulang' },
        },
    };
    var kurangiGerak = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    var cssApex = document.getElementById('apexcharts-css');
    var nonce = cssApex ? (cssApex.nonce || cssApex.getAttribute('nonce') || '') : '';

    /* ---------- alat bantu ---------- */
    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function gabung(a, b) {
        Object.keys(b).forEach(function (k) {
            var v = b[k];
            if (v && typeof v === 'object' && !Array.isArray(v) && a[k] && typeof a[k] === 'object' && !Array.isArray(a[k])) {
                gabung(a[k], v);
            } else {
                a[k] = v;
            }
        });
        return a;
    }
    function isi(el) {
        try { return JSON.parse(el.getAttribute('data-isi') || 'null'); } catch (e) { return null; }
    }
    function gelap() { return html.getAttribute('data-mode') === 'gelap'; }
    // Nilai variabel CSS → heksa (ApexCharts butuh heksa untuk bayangan/gradasi). "rgb(37 99 235)" / "#16bdca".
    function keHeksa(v) {
        v = String(v).trim();
        if (v.charAt(0) === '#') { return v; }
        var m = v.match(/[\d.]+/g);
        if (!m || m.length < 3) { return v; }
        return '#' + m.slice(0, 3).map(function (x) {
            var h = Math.max(0, Math.min(255, Math.round(+x))).toString(16);
            return h.length === 1 ? '0' + h : h;
        }).join('');
    }
    function palet() {
        var gaya = getComputedStyle(html);
        var w = function (n) { return keHeksa(gaya.getPropertyValue('--grafik-' + n)); };
        var p = { urut: [], teks: w('teks'), kuat: w('kuat'), garis: w('garis'), jalur: w('jalur'), tepi: w('tepi'), batas: w('batas') };
        for (var i = 1; i <= 8; i++) { p.urut.push(w(i)); }
        ['hadir', 'telat', 'izin', 'sakit', 'alpa'].forEach(function (s) { p[s] = w(s); });
        return p;
    }
    function tanggal(t) { return new Date(t + 'T00:00:00'); }
    function tanggalPanjang(t) {
        var d = tanggal(t);
        return HARI[d.getDay()] + ', ' + d.getDate() + ' ' + BULAN_PANJANG[d.getMonth()] + ' ' + d.getFullYear();
    }
    function persen(n, total) { return total > 0 ? Math.round(n / total * 100) : 0; }

    // Isi tooltip: judul (boleh kosong) + baris {warna: 'grafik-1'|'grafik-hadir'|…|'', label, nilai}. Hanya kelas, tanpa style.
    function tip(judul, baris) {
        var h = '<div class="grafik-tip">';
        if (judul) { h += '<p class="grafik-tip-judul">' + esc(judul) + '</p>'; }
        h += '<div class="grafik-tip-isi">';
        baris.forEach(function (b) {
            h += '<p class="grafik-tip-baris">' + (b.warna ? '<span class="titik-legenda warna-' + b.warna + '"></span>' : '')
                + '<span>' + esc(b.label) + '</span>' + (b.nilai !== undefined ? '<b class="grafik-tip-nilai">' + esc(b.nilai) + '</b>' : '') + '</p>';
        });
        return h + '</div></div>';
    }

    // Pengaturan bersama (template: Inter, tanpa toolbar, legenda HTML sendiri, label sumbu gray-500 / gray-400 di mode gelap).
    function dasar(jenis, tinggi, p) {
        return {
            chart: {
                type: jenis, height: tinggi, fontFamily: "'Plus Jakarta Sans', sans-serif", foreColor: p.teks, background: 'transparent',
                toolbar: { show: false }, zoom: { enabled: false }, parentHeightOffset: 0, nonce: nonce || undefined,
                locales: [LOKAL], defaultLocale: 'id',
                animations: { enabled: !kurangiGerak, speed: 650, animateGradually: { enabled: true, delay: 60 }, dynamicAnimation: { enabled: !kurangiGerak, speed: 350 } },
            },
            theme: { mode: gelap() ? 'dark' : 'light' },
            dataLabels: { enabled: false },
            legend: { show: false },
            grid: { show: false },
            states: { hover: { filter: { type: 'darken', value: 0.9 } }, active: { filter: { type: 'none' } } },
            tooltip: { theme: gelap() ? 'dark' : 'light', style: { fontSize: '14px', fontFamily: "'Plus Jakarta Sans', sans-serif" } },
        };
    }

    /* ---------- pembuat pengaturan per jenis grafik ---------- */
    var PEMBUAT = {
        // Batang kecil "Siswa per tingkat" (kartu angka) — batang latar abu seperti grafik "Users" template; angka di atas batang.
        tingkat: function (el, d, p) {
            var maks = Math.max.apply(null, d.map(function (x) { return x.n; }).concat([1]));
            return gabung(dasar('bar', 120, p), {
                series: [{ name: 'Siswa', data: d.map(function (x) { return x.n; }) }],
                colors: [p.urut[0]],
                plotOptions: { bar: { columnWidth: '55%', borderRadius: 3, borderRadiusApplication: 'end', dataLabels: { position: 'top' },
                    colors: { backgroundBarColors: [p.jalur], backgroundBarRadius: 3 } } },
                dataLabels: { enabled: true, offsetY: -18, formatter: function (v) { return angka.format(v); },
                    style: { fontSize: '11px', fontWeight: 600, colors: [p.kuat] } },
                xaxis: { categories: d.map(function (x) { return x.label; }), labels: { style: { colors: p.teks, fontSize: '12px', fontWeight: 500 } },
                    axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false } },
                yaxis: { show: false, min: 0, max: Math.ceil(maks * 1.3) },
                grid: { show: false, padding: { top: -8, left: 0, right: 0, bottom: -6 } },
                tooltip: { enabled: false },
                states: { hover: { filter: { type: 'none' } } },
            });
        },
        // Batang kecil "Guru menurut batas jam": kurang / sesuai / lebih.
        batas: function (el, d, p) {
            var nilai = [d.kurang, d.sesuai, d.lebih];
            return gabung(PEMBUAT.tingkat(el, [{ label: 'Kurang', n: d.kurang }, { label: 'Sesuai', n: d.sesuai }, { label: 'Lebih', n: d.lebih }], p), {
                series: [{ name: 'Guru', data: nilai }],
                colors: [p.telat, p.urut[0], p.alpa],
                plotOptions: { bar: { distributed: true } },
            });
        },
        // Lingkar persen (kehadiran hari ini, jadwal tersusun).
        radial: function (el, d, p) {
            return gabung(dasar('radialBar', 130, p), {
                series: [Math.max(0, Math.min(100, +el.getAttribute('data-nilai') || 0))],
                colors: [p.urut[0]],
                plotOptions: { radialBar: {
                    hollow: { size: '58%' }, track: { background: p.jalur, margin: 0 },
                    dataLabels: { name: { show: false }, value: { offsetY: 6, fontSize: '17px', fontWeight: 700, color: p.kuat,
                        formatter: function (v) { return Math.round(v) + '%'; } } },
                } },
                stroke: { lineCap: 'round' },
                grid: { padding: { top: -14, bottom: -14, left: -14, right: -14 } },
                states: { hover: { filter: { type: 'none' } } },
            });
        },
        // Donat (pola "Traffic by device" template): total di tengah, tooltip nama + jumlah + persen; legenda HTML di view.
        donat: function (el, d, p) {
            var total = d.reduce(function (s, x) { return s + x.n; }, 0);
            var satuan = el.getAttribute('data-satuan') || '';
            var status = el.getAttribute('data-warna') === 'status';
            var kelas = function (i) { return status ? 'grafik-' + d[i].kunci : 'grafik-' + ((i % 8) + 1); };
            return gabung(dasar('donut', 260, p), {
                series: d.map(function (x) { return x.n; }),
                labels: d.map(function (x) { return x.label; }),
                colors: d.map(function (x, i) { return status ? p[x.kunci] : p.urut[i % 8]; }),
                stroke: { colors: [p.tepi], width: 2 },
                plotOptions: { pie: { expandOnClick: false, donut: { size: '72%', labels: {
                    show: true,
                    name: { show: true, offsetY: 22, color: p.teks, fontSize: '14px', fontWeight: 400 },
                    value: { show: true, offsetY: -14, color: p.kuat, fontSize: '26px', fontWeight: 700, formatter: function (v) { return angka.format(+v); } },
                    total: { show: true, showAlways: true, label: el.getAttribute('data-total') || 'Total', color: p.teks, fontSize: '14px', fontWeight: 400,
                        formatter: function () { return angka.format(total); } },
                } } } },
                tooltip: { custom: function (o) {
                    var x = d[o.seriesIndex];
                    return tip(null, [{ warna: kelas(o.seriesIndex), label: x.nama, nilai: angka.format(x.n) + ' ' + satuan + ' (' + persen(x.n, total) + '%)' }]);
                } },
            });
        },
        // Batang mendatar beban mengajar + garis batas maksimal (goals). Merah = lebih dari batas; selebihnya warna merek.
        beban: function (el, d, p) {
            var maks = Math.max.apply(null, d.map(function (b) { return Math.max(b.jp, b.max); }).concat([1]));
            var warnaBatang = function (b) { return b.max > 0 && b.jp > b.max ? p.alpa : p.urut[0]; };
            var atas = Math.ceil((maks + 1) / 10) * 10;
            var sumbuX = function (jumlahTik) {
                return { min: 0, max: atas, tickAmount: jumlahTik, labels: { style: { colors: p.teks, fontSize: '12px', fontWeight: 500 },
                    formatter: function (v) { return Math.round(v); } }, axisBorder: { show: false }, axisTicks: { show: false } };
            };
            return gabung(dasar('bar', 48 + d.length * 30, p), {
                series: [{ name: 'JP penugasan', data: d.map(function (b) {
                    return { x: b.label, y: b.jp, fillColor: warnaBatang(b), goals: b.max > 0 ? [{ name: 'Batas maksimal', value: b.max,
                        strokeWidth: 3, strokeHeight: 18, strokeLineCap: 'round', strokeColor: p.batas }] : [] };
                }) }],
                plotOptions: { bar: { horizontal: true, barHeight: '62%', borderRadius: 3, borderRadiusApplication: 'end', dataLabels: { position: 'bottom' } } },
                dataLabels: { enabled: true, textAnchor: 'start', offsetX: 2, formatter: function (v, o) { return v + ' / ' + d[o.dataPointIndex].max; },
                    style: { fontSize: '11px', fontWeight: 600, colors: ['#ffffff'] }, dropShadow: { enabled: false } },
                xaxis: sumbuX(atas <= 100 ? atas / 10 : 10),
                yaxis: { labels: { maxWidth: 190, style: { colors: p.teks, fontSize: '13px', fontWeight: 500 } } },
                grid: { show: true, borderColor: p.garis, strokeDashArray: 1, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } },
                    padding: { left: 4, right: 12, top: -8, bottom: 0 } },
                tooltip: { custom: function (o) {
                    var b = d[o.dataPointIndex];
                    var selisih = b.jp - b.max;
                    var baris = [
                        { warna: selisih > 0 && b.max > 0 ? 'grafik-alpa' : 'grafik-1', label: 'JP penugasan', nilai: b.jp + ' JP' },
                        { warna: 'grafik-batas', label: 'Batas maksimal', nilai: b.max > 0 ? b.max + ' JP' : '–' },
                    ];
                    if (b.max > 0) { baris.push({ warna: '', label: 'Selisih', nilai: selisih > 0 ? 'lebih ' + selisih : (selisih < 0 ? 'kurang ' + (-selisih) : 'pas') }); }
                    baris.push({ warna: '', label: 'Penugasan', nilai: angka.format(b.tugas) });
                    return tip(b.kode + ' · ' + b.nama, baris);
                } },
                responsive: [{ breakpoint: 640, options: { xaxis: sumbuX(Math.max(2, Math.round(atas / 30))), yaxis: { labels: { maxWidth: 120, style: { colors: p.teks, fontSize: '12px', fontWeight: 500 } } } } }],
            });
        },
    };

    /* ---------- tren kehadiran (widget utama) ---------- */
    function sekarang() { var t = new Date(); t.setHours(0, 0, 0, 0); return t; }
    // Hari tercatat dalam [hari ini − geser − hari + 1, hari ini − geser].
    function potong(semua, hari, geser) {
        var akhir = sekarang();
        akhir.setDate(akhir.getDate() - geser);
        var awal = new Date(akhir);
        awal.setDate(awal.getDate() - hari + 1);
        return semua.filter(function (d) { var t = tanggal(d.t); return t >= awal && t <= akhir; });
    }
    function persenTren(daftar) {
        var h = 0, n = 0;
        daftar.forEach(function (d) { h += d.hadir + d.telat; n += d.n; });
        return n > 0 ? h / n * 100 : null;
    }
    function opsiTren(data, p) {
        var nilai = data.map(function (d) { return d.n > 0 ? Math.round((d.hadir + d.telat) / d.n * 1000) / 10 : 0; });
        var bawah = Math.max(0, Math.floor((Math.min.apply(null, nilai.concat([100])) - 5) / 10) * 10);
        // Pengaturan layar kecil di ApexCharts MENGGANTI seluruh yaxis → sumbu dibuat lewat fungsi & diulang lengkap di bawah.
        var sumbuY = function (jumlahTik) {
            return { min: bawah, max: 100, tickAmount: jumlahTik, labels: { style: { colors: p.teks, fontSize: '13px', fontWeight: 500 },
                formatter: function (v) { return Math.round(v) + '%'; } } };
        };
        return gabung(dasar('area', 380, p), {
            series: [{ name: 'Kehadiran', data: data.map(function (d, i) { return { x: tanggal(d.t).getTime(), y: nilai[i] }; }) }],
            colors: [p.urut[0]],
            fill: { type: 'gradient', gradient: { opacityFrom: gelap() ? 0 : 0.45, opacityTo: gelap() ? 0.15 : 0 } },
            stroke: { width: 3, curve: 'smooth' },
            markers: { size: data.length > 40 ? 0 : 4, strokeColors: p.tepi, strokeWidth: 2, hover: { sizeOffset: 3 } },
            grid: { show: true, borderColor: p.garis, strokeDashArray: 1, padding: { left: 8, right: 8, bottom: 4 } },
            xaxis: {
                type: 'datetime',
                labels: { datetimeUTC: false, style: { colors: p.teks, fontSize: '13px', fontWeight: 500 },
                    datetimeFormatter: { year: 'yyyy', month: 'MMM', day: 'd MMM', hour: 'd MMM' } },
                axisBorder: { color: p.garis }, axisTicks: { color: p.garis }, tooltip: { enabled: false },
                crosshairs: { show: true, position: 'back', stroke: { color: p.garis, width: 1, dashArray: 10 } },
            },
            yaxis: sumbuY(4),
            tooltip: { custom: function (o) {
                var d = data[o.dataPointIndex];
                var baris = [
                    { warna: 'grafik-1', label: 'Kehadiran', nilai: satuDesimal.format(nilai[o.dataPointIndex]) + '%' },
                    { warna: '', label: 'Guru hadir', nilai: angka.format(d.hadir + d.telat) + ' dari ' + angka.format(d.n) },
                ];
                STATUS.forEach(function (s) { if (d[s[0]] > 0) { baris.push({ warna: 'grafik-' + s[0], label: s[1], nilai: angka.format(d[s[0]]) }); } });
                return tip(tanggalPanjang(d.t), baris);
            } },
            responsive: [{ breakpoint: 640, options: { chart: { height: 260 }, yaxis: sumbuY(2) } }],
        });
    }
    function pasangTren(kartu, el) {
        var semua = isi(el) || [];
        var rentang = 30;
        var q = function (nama) { return kartu.querySelector('[data-tren-' + nama + ']'); };
        var item = { el: el, chart: null, buat: function (p) { return opsiTren(potong(semua, rentang, 0), p); } };

        function teks(nama, t) { var x = q(nama); if (x) { x.textContent = t; } }
        function perbarui() {
            var kini = potong(semua, rentang, 0);
            var pKini = persenTren(kini);
            var pLalu = persenTren(potong(semua, rentang, rentang));
            teks('angka', pKini === null ? '–' : Math.round(pKini) + '%');
            teks('judul', rentang + ' hari terakhir');
            teks('label', rentang + ' hari terakhir');
            teks('pembanding', rentang + ' hari sebelumnya');
            var banding = q('banding');
            if (banding) {
                var beda = (pKini === null || pLalu === null) ? null : Math.round((pKini - pLalu) * 10) / 10;
                banding.hidden = beda === null;
                if (beda !== null) {
                    var badan = q('beda');
                    badan.className = (beda > 0 ? 'tren-naik' : (beda < 0 ? 'tren-turun' : 'tren-datar')) + ' text-base';
                    teks('poin', satuDesimal.format(Math.abs(beda)) + ' poin');
                    q('naik').hidden = !(beda > 0);
                    q('turun').hidden = !(beda < 0);
                }
            }
            var kosong = q('kosong');
            el.hidden = kini.length === 0;
            if (kosong) { kosong.hidden = kini.length > 0; }
            if (kini.length > 0) { gambar(item); } else if (item.chart) { item.chart.destroy(); item.chart = null; }
        }

        document.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('[data-rentang]') : null;
            if (!b || !kartu.contains(b)) { return; }
            rentang = parseInt(b.getAttribute('data-rentang'), 10) || 30;
            perbarui();
        });
        item.tampil = function () { return !el.hidden; };
        daftar.push(item);
        perbarui();
    }

    /* ---------- gambar, gambar ulang saat tema berubah ---------- */
    var daftar = [];
    function gambar(item) {
        if (item.chart) { item.chart.destroy(); item.chart = null; }
        if (item.tampil && !item.tampil()) { return; }
        item.chart = new window.ApexCharts(item.el, item.buat(palet()));
        item.chart.render();
    }

    Array.prototype.forEach.call(document.querySelectorAll('.grafik[data-grafik]'), function (el) {
        var jenis = el.getAttribute('data-grafik');
        if (jenis === 'tren') {
            var kartu = el.closest('[data-tren]');
            if (kartu) { pasangTren(kartu, el); }
            return;
        }
        if (!PEMBUAT[jenis]) { return; }
        var data = jenis === 'radial' ? null : isi(el);
        if (jenis !== 'radial' && !data) { return; }
        var item = { el: el, chart: null, buat: function (p) { return PEMBUAT[jenis](el, data, p); } };
        daftar.push(item);
        gambar(item);
    });

    var tunda = null;
    new MutationObserver(function () {
        clearTimeout(tunda);
        tunda = setTimeout(function () { daftar.forEach(gambar); }, 60);
    }).observe(html, { attributes: true, attributeFilter: ['data-mode', 'data-warna'] });
})();
