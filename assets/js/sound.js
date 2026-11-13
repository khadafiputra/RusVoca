/* RusVoca SoundFX - efek suara Web Audio API (tanpa file eksternal) */
window.RusvocaSound = (function () {
    let ctx = null;
    let enabled = true;

    function ac() {
        try {
            if (!ctx) {
                const AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) return null;
                ctx = new AC();
            }
            if (ctx.state === 'suspended') ctx.resume();
            return ctx;
        } catch (e) { return null; }
    }

    // Bunyikan satu nada dengan envelope sederhana
    function nada(freq, mulai, durasi, tipe, volume) {
        const c = ac();
        if (!c || !enabled) return;
        try {
            const osc = c.createOscillator();
            const gain = c.createGain();
            const t = c.currentTime + (mulai || 0);
            osc.type = tipe || 'sine';
            osc.frequency.setValueAtTime(freq, t);
            gain.gain.setValueAtTime(0.0001, t);
            gain.gain.exponentialRampToValueAtTime(volume || 0.18, t + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + durasi);
            osc.connect(gain);
            gain.connect(c.destination);
            osc.start(t);
            osc.stop(t + durasi + 0.05);
        } catch (e) { /* abaikan */ }
    }

    return {
        setEnabled: function (v) { enabled = !!v; },
        isEnabled: function () { return enabled; },
        // Klik tombol / tile
        klik: function () { nada(620, 0, 0.09, 'triangle', 0.12); },
        // Kartu dibalik
        balik: function () {
            const c = ac();
            if (!c || !enabled) return;
            try {
                const osc = c.createOscillator();
                const gain = c.createGain();
                const t = c.currentTime;
                osc.type = 'sine';
                osc.frequency.setValueAtTime(300, t);
                osc.frequency.exponentialRampToValueAtTime(700, t + 0.12);
                gain.gain.setValueAtTime(0.12, t);
                gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.14);
                osc.connect(gain);
                gain.connect(c.destination);
                osc.start(t);
                osc.stop(t + 0.2);
            } catch (e) { /* abaikan */ }
        },
        // Jawaban benar
        benar: function () { nada(523.25, 0, 0.14, 'sine', 0.2); nada(783.99, 0.12, 0.22, 'sine', 0.2); },
        // Jawaban salah
        salah: function () { nada(220, 0, 0.18, 'sawtooth', 0.1); nada(155.56, 0.15, 0.28, 'sawtooth', 0.1); },
        // Kemenangan / kuis selesai
        menang: function () {
            nada(523.25, 0, 0.16, 'triangle', 0.2);
            nada(659.25, 0.14, 0.16, 'triangle', 0.2);
            nada(783.99, 0.28, 0.16, 'triangle', 0.2);
            nada(1046.5, 0.42, 0.34, 'triangle', 0.22);
        }
    };
})();
