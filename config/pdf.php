<?php
/**
 * config/pdf.php
 * ---------------------------------------------------------------------------
 * Generator PDF minimal (tanpa library/Composer) untuk kebutuhan ekspor
 * tabel Ringkasan Siswa pada halaman Rekap Nilai admin.
 * ---------------------------------------------------------------------------
 */

/** Bersihkan teks agar aman ditulis pada PDF dengan font standar (WinAnsi). */
function pdf_teks($teks): string
{
    $teks = (string)$teks;
    if (function_exists('iconv')) {
        $hasil = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $teks);
        if ($hasil !== false) {
            $teks = $hasil;
        }
    }
    $teks = str_replace(["\r", "\n", "\t"], ' ', $teks);
    $teks = preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '?', $teks);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string)$teks);
}

/** Potong teks agar muat dalam lebar kolom (perkiraan lebar huruf Helvetica). */
function pdf_potong($teks, float $lebar, float $ukuran = 9.5): string
{
    $teks = (string)$teks;
    $maks = max(3, (int)floor($lebar / ($ukuran * 0.52)));
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($teks, 'UTF-8') > $maks) {
            return mb_substr($teks, 0, $maks - 3, 'UTF-8') . '...';
        }
        return $teks;
    }
    if (strlen($teks) > $maks) {
        return substr($teks, 0, $maks - 3) . '...';
    }
    return $teks;
}

/**
 * Bangun PDF rekap Ringkasan Siswa (A4 landscape).
 *
 * @param array<int, array{nama:string,email:string,xp:int,tuntas:int}> $baris
 * @param string $info_filter Keterangan filter yang sedang aktif.
 * @return string Isi file PDF.
 */
function pdf_rekap_siswa(array $baris, string $info_filter): string
{
    $lebar_halaman  = 842.0;
    $tinggi_halaman = 595.0;
    $margin         = 36.0;
    $lebar_tabel    = $lebar_halaman - ($margin * 2);

    // Lebar kolom: siswa, email, total XP, mode tuntas.
    $kolom = [
        ['label' => 'Siswa',        'lebar' => 230.0, 'kunci' => 'nama',   'align' => 'left'],
        ['label' => 'Email',        'lebar' => 330.0, 'kunci' => 'email',  'align' => 'left'],
        ['label' => 'Total XP',     'lebar' => 120.0, 'kunci' => 'xp',     'align' => 'right'],
        ['label' => 'Mode Tuntas',  'lebar' => $lebar_tabel - 680.0, 'kunci' => 'tuntas', 'align' => 'center'],
    ];

    $tinggi_baris  = 21.0;
    $tinggi_header = 24.0;
    $baris_pertama = 488.0; // posisi y awal tabel
    $maks_baris    = (int)floor(($baris_pertama - 64.0) / $tinggi_baris);
    $maks_baris    = max(1, $maks_baris);

    $halaman = array_chunk($baris, $maks_baris);
    if (!$halaman) {
        $halaman = [[]];
    }
    $total_halaman = count($halaman);

    $streams = [];
    foreach ($halaman as $indeks => $daftar) {
        $s = [];

        // Judul
        $s[] = '0.059 0.247 0.478 rg';
        $s[] = 'BT /F2 17 Tf ' . $margin . ' 548 Td (' . pdf_teks('Rekap Nilai Siswa - Ringkasan') . ') Tj ET';

        $s[] = '0.392 0.455 0.545 rg';
        $s[] = 'BT /F1 9 Tf ' . $margin . ' 532 Td (' . pdf_teks($info_filter) . ') Tj ET';
        $s[] = 'BT /F1 8.5 Tf ' . $margin . ' 519 Td (' . pdf_teks('Dicetak: ' . date('d/m/Y H:i') . ' WIB') . ') Tj ET';

        // Header tabel
        $s[] = '0.941 0.957 0.973 rg';
        $s[] = $margin . ' ' . $baris_pertama . ' ' . $lebar_tabel . ' ' . $tinggi_header . ' re f';

        $x = $margin;
        foreach ($kolom as $k) {
            $s[] = '0.059 0.247 0.478 rg';
            $s[] = 'BT /F2 9.5 Tf ' . ($x + 7) . ' ' . ($baris_pertama + 8) . ' Td (' . pdf_teks($k['label']) . ') Tj ET';
            $x += $k['lebar'];
        }

        // Isi tabel
        $y = $baris_pertama;
        foreach ($daftar as $i => $r) {
            $y -= $tinggi_baris;
            if ($i % 2 === 1) {
                $s[] = '0.976 0.980 0.984 rg';
                $s[] = $margin . ' ' . $y . ' ' . $lebar_tabel . ' ' . $tinggi_baris . ' re f';
            }

            $x = $margin;
            foreach ($kolom as $k) {
                $nilai = $r[$k['kunci']] ?? '';
                if ($k['kunci'] === 'xp') {
                    $nilai = number_format((int)$nilai, 0, ',', '.');
                } elseif ($k['kunci'] === 'tuntas') {
                    $nilai = (string)(int)$nilai;
                }
                $nilai = pdf_teks(pdf_potong($nilai, $k['lebar'] - 14, 9.5));
                $tx = $x + 7;
                if ($k['align'] === 'right') {
                    $perkiraan = strlen($nilai) * 5.0;
                    $tx = $x + $k['lebar'] - 7 - $perkiraan;
                } elseif ($k['align'] === 'center') {
                    $perkiraan = strlen($nilai) * 5.0;
                    $tx = $x + (($k['lebar'] - $perkiraan) / 2);
                }
                $s[] = '0.102 0.129 0.176 rg';
                $s[] = 'BT /F1 9.5 Tf ' . round($tx, 2) . ' ' . round($y + 6.5, 2) . ' Td (' . $nilai . ') Tj ET';
                $x += $k['lebar'];
            }
        }

        if (!$daftar) {
            $s[] = '0.392 0.455 0.545 rg';
            $s[] = 'BT /F1 9.5 Tf ' . ($margin + 7) . ' ' . ($baris_pertama - 18) . ' Td (' . pdf_teks('Belum ada akun siswa.') . ') Tj ET';
        }

        // Garis tabel
        $s[] = '0.851 0.878 0.910 RG';
        $s[] = '0.8 w';
        $jumlah_baris_tampil = $daftar ? count($daftar) : 1;
        $atas_header = $baris_pertama + $tinggi_header;
        $bawah_tabel  = $baris_pertama - ($jumlah_baris_tampil * $tinggi_baris);

        $s[] = $margin . ' ' . round($atas_header, 2) . ' m ' . ($margin + $lebar_tabel) . ' ' . round($atas_header, 2) . ' l S';
        for ($i = 0; $i <= $jumlah_baris_tampil; $i++) {
            $y_garis = $baris_pertama - ($i * $tinggi_baris);
            $s[] = $margin . ' ' . round($y_garis, 2) . ' m ' . ($margin + $lebar_tabel) . ' ' . round($y_garis, 2) . ' l S';
        }

        $x = $margin;
        $s[] = $margin . ' ' . round($atas_header, 2) . ' m ' . $margin . ' ' . round($bawah_tabel, 2) . ' l S';
        foreach ($kolom as $k) {
            $x += $k['lebar'];
            $s[] = round($x, 2) . ' ' . round($atas_header, 2) . ' m ' . round($x, 2) . ' ' . round($bawah_tabel, 2) . ' l S';
        }

        // Footer
        $s[] = '0.392 0.455 0.545 rg';
        $s[] = 'BT /F1 8 Tf ' . $margin . ' 30 Td (' . pdf_teks('RusVoca - Aplikasi Kosakata Bahasa Rusia') . ') Tj ET';
        $teks_halaman = 'Halaman ' . ($indeks + 1) . ' dari ' . $total_halaman;
        $s[] = 'BT /F1 8 Tf ' . ($lebar_halaman - $margin - 80) . ' 30 Td (' . pdf_teks($teks_halaman) . ') Tj ET';

        $streams[] = implode("\n", $s);
    }

    // -----------------------------------------------------------------------
    // Susun objek PDF
    // -----------------------------------------------------------------------
    $objek = [];
    $objek[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objek[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
    $objek[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

    $kids = [];
    $id = 5;
    foreach ($streams as $stream) {
        $id_halaman = $id++;
        $id_isi     = $id++;
        $kids[]     = $id_halaman . ' 0 R';
        $objek[$id_halaman] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $lebar_halaman $tinggi_halaman] "
            . "/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $id_isi 0 R >>";
        $objek[$id_isi] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    }

    $objek[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($streams) . " >>";
    ksort($objek);

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offset = [];
    foreach ($objek as $nomor => $isi) {
        $offset[$nomor] = strlen($pdf);
        $pdf .= $nomor . " 0 obj\n" . $isi . "\nendobj\n";
    }

    $xref = strlen($pdf);
    $jumlah_objek = max(array_keys($objek)) + 1;
    $pdf .= "xref\n0 " . $jumlah_objek . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i < $jumlah_objek; $i++) {
        if (isset($offset[$i])) {
            $pdf .= sprintf('%010d 00000 n ', $offset[$i]) . "\n";
        } else {
            $pdf .= "0000000000 65535 f \n";
        }
    }
    $pdf .= "trailer\n<< /Size " . $jumlah_objek . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xref . "\n%%EOF";

    return $pdf;
}
