/**
 * Kemajuan restore (Sistem → Backup & Restore; docs/DESAIN-ALAT-SISTEM.md F4). Halaman admin/sistem/backup/pulihkan/{id}:
 * memanggil POST …/langkah berulang (token CSRF ditambahkan otomatis oleh app.js pada fetch POST), memperbarui <progress>
 * dan teks status, lalu memuat ulang halaman saat selesai/gagal (hasil dirender server). Siap CSP: berkas terpisah, tanpa eval.
 */
(function () {
    'use strict';
    var akar = document.querySelector('[data-pulihkan]');
    if (!akar) { return; }
    var url = akar.getAttribute('data-url');
    var bilah = akar.querySelector('[data-bilah]');
    var teks = akar.querySelector('[data-teks]');
    var gagalBeruntun = 0;

    function tulis(pesan, persen) {
        if (teks) { teks.textContent = pesan + (typeof persen === 'number' ? ' (' + persen + '%)' : ''); }
        if (bilah && typeof persen === 'number') { bilah.value = persen; bilah.textContent = persen + '%'; }
    }

    function langkah() {
        fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                gagalBeruntun = 0;
                tulis(j.pesan || '', j.persen);
                if (j.ok === false) { return; } // dijeda (mis. Mode Maintenance dimatikan) — pesan sudah tampil
                if (j.tahap === 'siap' || j.tahap === 'jalan') {
                    window.setTimeout(langkah, 300);
                } else {
                    window.location.href = j.alamat;
                }
            })
            .catch(function () {
                gagalBeruntun++;
                tulis('Koneksi terputus, mencoba lagi… (' + gagalBeruntun + ')');
                window.setTimeout(langkah, Math.min(15000, 2000 * gagalBeruntun));
            });
    }

    langkah();
}());
