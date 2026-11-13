/* RusVoca QuizGuard - peringatan saat keluar di tengah kuis yang belum selesai */
(function () {
    let aktif = true;

    function tandaiSelesai() { aktif = false; }

    // Anggap kuis selesai saat layar hasil tampil (display inline berubah dari none)
    function pantau(id) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.style.display && el.style.display !== 'none') tandaiSelesai();
        try {
            new MutationObserver(function () {
                if (el.style.display && el.style.display !== 'none') tandaiSelesai();
            }).observe(el, { attributes: true, attributeFilter: ['style'] });
        } catch (e) { /* abaikan */ }
    }
    pantau('result-screen');
    pantau('end-screen');

    // Kuis juga dianggap selesai saat form skor dikirim
    document.querySelectorAll('form').forEach(function (f) {
        if (f.querySelector('input[name="skor_akhir"], input[name="skor"]')) {
            f.addEventListener('submit', tandaiSelesai);
        }
    });

    const PESAN = document.documentElement.lang === 'en'
        ? 'Are you sure you want to leave this quiz? Unsaved progress will be lost.'
        : 'Apakah Anda yakin keluar dari kuis ini? Progres yang belum disimpan akan hilang.';

    // Cegat klik link navigasi (tombol kembali, sidebar) selama kuis berjalan
    document.addEventListener('click', function (e) {
        const a = (e.target && e.target.closest) ? e.target.closest('a') : null;
        if (!a || !aktif) return;
        const href = a.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || a.target === '_blank') return;
        if (!window.confirm(PESAN)) {
            e.preventDefault();
        } else {
            aktif = false;
        }
    });

    // Pengaman untuk refresh / tutup tab / tombol back browser
    window.addEventListener('beforeunload', function (e) {
        if (aktif) { e.preventDefault(); e.returnValue = ''; }
    });
})();
