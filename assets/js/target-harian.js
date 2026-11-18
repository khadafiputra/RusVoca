/**
 * assets/js/target-harian.js
 * ---------------------------------------------------------------------------
 * Heartbeat target belajar harian. Mengirim waktu belajar nyata ke server
 * setiap 15 detik saat tab terlihat, memperbarui widget, dan menampilkan
 * pemberitahuan saat target hari ini terpenuhi.
 * ---------------------------------------------------------------------------
 */
(function () {
    var cfg = window.RUSVOCA_TARGET;
    if (!cfg || !cfg.endpoint) { return; }

    var widget   = document.getElementById('rvTargetWidget');
    var fill     = document.getElementById('rvTargetFill');
    var menitEl  = document.getElementById('rvTargetMenit');
    var goalEl   = document.getElementById('rvTargetGoal');
    var persenEl = document.getElementById('rvTargetPercent');
    var toast    = document.getElementById('rvTargetToast');
    var toastTimer = null;

    var kunciNotif = 'rusvoca_target_notif_' + cfg.tanggal;
    var tercapai = !!cfg.tercapai;
    var navigasiInternal = false;

    function sudahDiberitahu() {
        try { return localStorage.getItem(kunciNotif) === '1'; } catch (e) { return false; }
    }

    function tandaiDiberitahu() {
        try { localStorage.setItem(kunciNotif, '1'); } catch (e) { /* abaikan */ }
    }

    function tampilkanNotifikasi() {
        if (!toast || sudahDiberitahu()) { return; }
        tandaiDiberitahu();
        toast.classList.add('is-show');
        if (toastTimer) { clearTimeout(toastTimer); }
        toastTimer = setTimeout(function () {
            toast.classList.remove('is-show');
        }, 9000);
    }

    function render(data) {
        var target = Math.max(1, parseInt(data.target, 10) || 1);
        var menit  = Math.max(0, parseInt(data.menit, 10) || 0);
        var persen = Math.min(100, Math.round((menit / target) * 100));
        var selesai = !!data.tercapai;
        tercapai = selesai;

        if (fill)     { fill.style.width = persen + '%'; }
        if (menitEl)  { menitEl.textContent = menit; }
        if (goalEl)   { goalEl.textContent = target; }
        if (persenEl) { persenEl.textContent = persen + '%'; }
        if (widget)   { widget.classList.toggle('is-done', selesai); }
    }

    render(cfg);
    if (cfg.tercapai) {
        setTimeout(tampilkanNotifikasi, 700);
    }

    var terakhir = Date.now();

    function kirimHeartbeat() {
        if (document.visibilityState !== 'visible') {
            terakhir = Date.now();
            return;
        }

        var sekarang = Date.now();
        var detik = Math.round((sekarang - terakhir) / 1000);
        terakhir = sekarang;
        if (detik < 1) { return; }

        var body = 'detik=' + encodeURIComponent(detik)
            + '&csrf_token=' + encodeURIComponent(cfg.csrf);

        fetch(cfg.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body
        }).then(function (res) {
            return res.json();
        }).then(function (data) {
            if (!data || !data.ok) { return; }
            render(data);
            if (data.baru_tercapai) {
                tampilkanNotifikasi();
            }
        }).catch(function () {
            // Jaringan sedang bermasalah; coba lagi pada heartbeat berikutnya.
        });
    }

    // --- Peringatan keluar sebelum target tercapai -------------------------
    // Navigasi di dalam aplikasi (klik tautan atau submit form biasa) tidak
    // dianggap keluar, jadi tidak memunculkan peringatan.
    document.addEventListener('click', function (ev) {
        var el = ev.target;
        while (el && el !== document && el.tagName !== 'A') { el = el.parentElement; }
        if (!el || el.tagName !== 'A') { return; }
        var href = el.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) { return; }
        if (el.target && el.target !== '_self') { return; }
        if (el.hasAttribute('download')) { return; }
        navigasiInternal = true;
    }, true);

    // Peringatan saat menutup tab/halaman. Browser hanya menampilkan pesan
    // bawaannya, jadi teks khusus tidak bisa dipaksakan di sini.
    window.addEventListener('beforeunload', function (ev) {
        if (tercapai || navigasiInternal) { return; }
        var pesan = cfg.pesanKeluar || 'Apakah Anda yakin keluar tetapi Anda belum mencapai target?';
        ev.preventDefault();
        ev.returnValue = pesan;
        return pesan;
    });

    // Tombol keluar (logout) sebelum target tercapai: tahan dulu, lalu minta
    // konfirmasi. Jika memilih Ya, form dikirim dengan penanda konfirmasi.
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || form.tagName !== 'FORM') { return; }

        var action = form.getAttribute('action') || '';
        var tombolKeluar = /(^|\/)logout\.php(\?|$)/.test(action);
        if (!tombolKeluar) {
            navigasiInternal = true;
            return;
        }
        if (tercapai) { return; }

        ev.preventDefault();
        ev.stopPropagation();
        ev.stopImmediatePropagation();

        if (!window.confirm(cfg.pesanKeluar || 'Apakah Anda yakin keluar tetapi Anda belum mencapai target?')) {
            return;
        }

        navigasiInternal = true;
        var penanda = form.querySelector('input[name="konfirmasi_keluar"]');
        if (!penanda) {
            penanda = document.createElement('input');
            penanda.type = 'hidden';
            penanda.name = 'konfirmasi_keluar';
            form.appendChild(penanda);
        }
        penanda.value = '1';
        HTMLFormElement.prototype.submit.call(form);
    }, true);

    setInterval(kirimHeartbeat, 15000);

    document.addEventListener('visibilitychange', function () {
        terakhir = Date.now();
        if (document.visibilityState === 'visible') {
            kirimHeartbeat();
        }
    });

    window.addEventListener('pagehide', function () {
        if (document.visibilityState !== 'visible') { return; }
        var detik = Math.round((Date.now() - terakhir) / 1000);
        if (detik < 1 || !navigator.sendBeacon) { return; }
        var form = new FormData();
        form.append('detik', detik);
        form.append('csrf_token', cfg.csrf);
        navigator.sendBeacon(cfg.endpoint, form);
    });
})();
