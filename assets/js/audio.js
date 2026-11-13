/* ==========================================================================
   RusVoca Audio - pelafalan Bahasa Rusia (Web Speech API)
   Menyediakan satu helper bersama agar semua halaman memakai perilaku sama:
   - suara dibatalkan dulu supaya tidak dobel
   - kecepatan bisa diatur
   - tombol mendapat kelas "is-playing" selama suara berbunyi
   ========================================================================== */
var kecepatanAudio = 1.0;

function ubahKecepatan(val) {
    kecepatanAudio = parseFloat(val);
}

/** Apakah browser mendukung pelafalan otomatis? */
function audioDidukung() {
    return 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
}

/**
 * Ucapkan teks Rusia.
 * @param {string} teks Teks yang akan diucapkan.
 * @param {object} opsi { rate, btn, onend, onstart }
 * @returns {boolean} true jika berhasil memulai.
 */
function ucapkanTeks(teks, opsi) {
    opsi = opsi || {};
    if (!audioDidukung()) return false;
    if (!teks) return false;

    window.speechSynthesis.cancel();

    var msg = new SpeechSynthesisUtterance(String(teks));
    msg.lang = 'ru-RU';
    msg.rate = typeof opsi.rate === 'number' ? opsi.rate : kecepatanAudio;
    msg.pitch = 1;

    var tombol = opsi.btn || null;
    if (tombol) {
        // Bersihkan status tombol audio lain agar tidak ada animasi nyangkut.
        document.querySelectorAll('.rv-audio, .btn-audio').forEach(function (el) {
            if (el !== tombol) el.classList.remove('is-playing', 'playing');
        });
        tombol.classList.add('is-playing');
    }

    function selesai() {
        if (tombol) tombol.classList.remove('is-playing');
        if (typeof opsi.onend === 'function') opsi.onend();
    }

    msg.onend = selesai;
    msg.onerror = selesai;
    if (typeof opsi.onstart === 'function') msg.onstart = opsi.onstart;

    window.speechSynthesis.speak(msg);
    return true;
}

/** Kompatibilitas dengan halaman lama. */
function putarAudio(teksRusia) {
    ucapkanTeks(teksRusia);
}

/**
 * Pasang audio ke semua elemen ber-atribut data-ucap.
 * Contoh: <button class="rv-audio" data-ucap="Привет">...</button>
 */
function pasangAudioOtomatis() {
    document.querySelectorAll('[data-ucap]').forEach(function (el) {
        if (el.dataset.audioTerpasang === '1') return;
        el.dataset.audioTerpasang = '1';
        el.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (window.RusvocaSound) RusvocaSound.klik();
            var rate = parseFloat(el.dataset.rate || '0.85');
            if (!ucapkanTeks(el.dataset.ucap, { btn: el, rate: rate }) && !audioDidukung()) {
                alert('Maaf, browser kamu belum mendukung pemutar suara otomatis.');
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', pasangAudioOtomatis);
