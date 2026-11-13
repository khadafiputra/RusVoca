<?php
/**
 * config/level.php
 * ---------------------------------------------------------------------------
 * Satu sumber perhitungan tingkat keahlian (XP) agar dashboard, profil, dan
 * daftar pelajaran menampilkan level serta tema yang sama.
 *
 * Ambang level:
 *   Pemula   : 0    - 499 XP
 *   Menengah : 500  - 1.499 XP
 *   Mahir    : 1.500 - 2.999 XP
 *   Legenda  : 3.000+ XP
 * Admin ditampilkan pada level maksimal (Legenda).
 * ---------------------------------------------------------------------------
 */

if (!function_exists('hitung_level')) {
    /**
     * Hitung level user berdasarkan total XP terbaik.
     *
     * @return array{
     *     xp:int, nama:string, kelas:string, warna:string, ikon:string,
     *     next_xp:int, persen:float
     * }
     */
    function hitung_level(mysqli $koneksi, int $user_id, bool $is_admin = false): array
    {
        $total_xp = 0;
        $query_xp = mysqli_query($koneksi, "
            SELECT SUM(max_skor) AS total_xp FROM (
                SELECT MAX(skor) AS max_skor
                FROM tb_progres_belajar
                WHERE user_id = '$user_id'
                GROUP BY id_pelajaran, mode_belajar
            ) AS subquery
        ");
        if ($query_xp) {
            $data_xp = mysqli_fetch_assoc($query_xp);
            if ($data_xp && $data_xp['total_xp'] !== null) {
                $total_xp = (int)$data_xp['total_xp'];
            }
        }

        // Admin selalu tampil sebagai Legenda.
        if ($is_admin) {
            $total_xp = max($total_xp, 3000);
        }

        $nama    = 'Pemula';
        $kelas   = 'level-pemula';
        $warna   = '#B45309';
        $ikon    = 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z';
        $next_xp = 500;
        $persen  = ($total_xp / $next_xp) * 100;

        if ($total_xp >= 3000) {
            $nama    = 'Legenda';
            $kelas   = 'level-legenda';
            $warna   = '#2563EB';
            $ikon    = 'M12 2L2 9l10 13 10-13z';
            $next_xp = $total_xp;
            $persen  = 100;
        } elseif ($total_xp >= 1500) {
            $nama    = 'Mahir';
            $kelas   = 'level-mahir';
            $warna   = '#D97706';
            $ikon    = 'M5 16h14v2H5zm14-9l-4 3-3-5-3 5-4-3 2 8h10l2-8z';
            $next_xp = 3000;
            $persen  = (($total_xp - 1500) / 1500) * 100;
        } elseif ($total_xp >= 500) {
            $nama    = 'Menengah';
            $kelas   = 'level-menengah';
            $warna   = '#64748B';
            $ikon    = 'M12 2L3 6v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V6l-9-4z';
            $next_xp = 1500;
            $persen  = (($total_xp - 500) / 1000) * 100;
        }

        return [
            'xp'      => $total_xp,
            'nama'    => $nama,
            'kelas'   => $kelas,
            'warna'   => $warna,
            'ikon'    => $ikon,
            'next_xp' => $next_xp,
            'persen'  => min(100, max(0, $persen)),
        ];
    }
}
