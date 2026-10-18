/**
 * Format Kesediaan Guru Mengajar (public/form.php) — wizard 6 langkah + tabel mata pelajaran.
 * P7 rombak publik: dipindah dari skrip sebaris (siap CSP). Logika SAMA dengan versi lama: validasi per langkah (alert), mode
 * "tidak bersedia" (langsung kirim dari langkah 1), tombol Kirim baru muncul bila data wajib lengkap, isian dikirim sebagai form
 * biasa (POST). Data awal (isian lama setelah galat / data revisi) dibaca dari <script type="application/json" id="data-kesediaan">.
 * Yang berubah: baris tabel dibuat lewat DOM (bukan innerHTML berisi teks), bilah langkah bersegmen (kelas, bukan style.width),
 * animasi hiasan lama (step-anim / pop-anim) dibuang.
 */
(function () {
    'use strict';

    var formEl = document.getElementById('formKesediaan');
    if (!formEl) { return; }

    var data = {};
    try { data = JSON.parse((document.getElementById('data-kesediaan') || {}).textContent || '{}'); } catch (e) { data = {}; }
    var oldMapel = data.oldMapel || [];
    var oldKelas = data.oldKelas || [];
    var editMapel = data.editMapel || [];

    /* ---- Tabel Mata Pelajaran (tanpa jam) ---- */
    var body = document.getElementById('mapelBody');

    function renumber() {
        Array.prototype.forEach.call(body.querySelectorAll('tr'), function (tr, i) { tr.querySelector('.row-no').textContent = i + 1; });
    }

    function sel(kelas, isi) {
        var td = document.createElement('td');
        td.className = kelas;
        if (isi) { td.appendChild(isi); }
        return td;
    }

    function isian(nama, nilai, ph, label) {
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.name = nama;
        inp.value = nilai === null || nilai === undefined ? '' : String(nilai);
        inp.className = 'inp';
        inp.placeholder = ph;
        inp.setAttribute('aria-label', label);
        return inp;
    }

    function addRow(mapel, kelas) {
        var tr = document.createElement('tr');
        var no = sel('row-no px-3 text-center text-teks-2 align-middle');
        var hapus = document.createElement('button');
        hapus.type = 'button';
        hapus.className = 'del-row tombol-ikon mx-auto text-teks-2 hover:bg-red-50 hover:text-red-700';
        hapus.setAttribute('aria-label', 'Hapus baris');
        hapus.innerHTML = document.getElementById('ikonHapus').innerHTML; // ikon dari server (SVG tepercaya), bukan teks isian
        tr.appendChild(no);
        tr.appendChild(sel('px-2 py-2', isian('mapel[]', mapel, 'Mata pelajaran', 'Mata pelajaran')));
        tr.appendChild(sel('px-2 py-2', isian('kelas[]', kelas, 'X / XI / XII', 'Kelas')));
        tr.appendChild(sel('px-2 py-2 text-center align-middle', hapus));
        body.appendChild(tr);
        hapus.addEventListener('click', function () {
            if (body.children.length > 1) {
                tr.remove();
                renumber();
            }
        });
        renumber();
    }

    document.getElementById('addMapel').addEventListener('click', function () { addRow('', ''); });
    if (oldMapel.length) {
        oldMapel.forEach(function (m, i) { addRow(m || '', oldKelas[i] || ''); });
    } else if (editMapel.length) {
        editMapel.forEach(function (m) { addRow(m.mapel || '', m.kelas || ''); });
    } else {
        addRow('', ''); addRow('', ''); addRow('', '');
    }

    /* ---- Wizard ---- */
    var steps = Array.prototype.slice.call(document.querySelectorAll('.wizard-step'));
    var dots = Array.prototype.slice.call(document.querySelectorAll('.langkah-titik'));
    var bilah = Array.prototype.slice.call(document.querySelectorAll('.langkah-bilah > span'));
    var prevBtn = document.getElementById('prevBtn');
    var nextBtn = document.getElementById('nextBtn');
    var submitBtn = document.getElementById('submitBtn');
    var submitHint = document.getElementById('submitHint');
    var declineMsg = document.getElementById('declineMsg');
    var curStepEl = document.getElementById('curStep');
    var titleEl = document.getElementById('stepTitle');
    var formTop = document.getElementById('formTop');
    var current = 0;

    function valOf(n) {
        var e = formEl.querySelector('[name="' + n + '"]');
        return e ? String(e.value).trim() : '';
    }
    function radioOf(n) {
        var r = formEl.querySelector('input[name="' + n + '"]:checked');
        return r ? r.value : '';
    }
    function willing() { return radioOf('bersedia'); }
    function namaOk() { return valOf('nama_lengkap') !== ''; }

    function isComplete() {
        return namaOk() && willing() === 'ya' && valOf('nomor_hp') !== '' &&
            radioOf('status_kepegawaian') !== '' && formEl.querySelector('[name="komitmen_setuju"]').checked;
    }

    function render(scroll) {
        steps.forEach(function (s, i) { s.classList.toggle('hidden', i !== current); });
        dots.forEach(function (d, i) {
            d.classList.toggle('is-active', i === current);
            d.classList.toggle('is-done', i < current);
        });
        bilah.forEach(function (b, i) { b.classList.toggle('is-done', i <= current); });
        curStepEl.textContent = current + 1;
        titleEl.textContent = steps[current].getAttribute('data-title');
        prevBtn.classList.toggle('invisible', current === 0);
        updateNav();
        if (scroll) {
            window.scrollTo({ top: formTop.offsetTop - 16, behavior: 'smooth' });
        }
    }

    function updateNav() {
        var last = current === steps.length - 1;
        // Mode "tidak bersedia" hanya di langkah 1
        if (current === 0 && willing() === 'tidak') {
            declineMsg.classList.remove('hidden');
            nextBtn.classList.add('hidden');
            var okNama = namaOk();
            submitBtn.classList.toggle('hidden', !okNama);
            submitHint.classList.toggle('hidden', okNama);
            return;
        }
        declineMsg.classList.add('hidden');
        if (!last) {
            nextBtn.classList.remove('hidden');
            submitBtn.classList.add('hidden');
            submitHint.classList.add('hidden');
        } else {
            nextBtn.classList.add('hidden');
            var ok = isComplete();
            submitBtn.classList.toggle('hidden', !ok);
            submitHint.classList.toggle('hidden', ok);
        }
    }

    function validateStep(idx) {
        if (idx === 0) {
            var nm = formEl.querySelector('[name="nama_lengkap"]');
            if (!nm.value.trim()) {
                window.alert('Mohon isi Nama Lengkap.');
                nm.focus();
                return false;
            }
            if (willing() === '') {
                window.alert('Silakan pilih kesediaan Anda terlebih dahulu.');
                return false;
            }
        }
        if (idx === 1) {
            var hp = formEl.querySelector('[name="nomor_hp"]');
            if (!hp.value.trim()) {
                window.alert('Mohon isi Nomor HP / WhatsApp.');
                hp.focus();
                return false;
            }
            if (radioOf('status_kepegawaian') === '') {
                window.alert('Silakan pilih Status Kepegawaian.');
                return false;
            }
        }
        return true;
    }

    nextBtn.addEventListener('click', function () {
        if (validateStep(current) && current < steps.length - 1) {
            current++;
            render(true);
        }
    });
    prevBtn.addEventListener('click', function () {
        if (current > 0) {
            current--;
            render(true);
        }
    });
    dots.forEach(function (d, i) {
        d.addEventListener('click', function () {
            if (i < current) {
                current = i;
                render(true);
            }
        });
    });
    formEl.addEventListener('input', updateNav);
    formEl.addEventListener('change', updateNav);

    render(false);

    formEl.addEventListener('submit', function () {
        submitBtn.disabled = true;
        submitBtn.classList.add('cursor-wait');
        submitBtn.textContent = '';
        var putar = document.createElement('span');
        putar.className = 'pemutar';
        putar.setAttribute('aria-hidden', 'true');
        submitBtn.appendChild(putar);
        submitBtn.appendChild(document.createTextNode(' ' + (submitBtn.getAttribute('data-teks-proses') || 'Mengirim...')));
    });
})();
