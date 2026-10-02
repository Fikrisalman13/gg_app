<?php
/**
 * Helper Terbilang Rupiah
 * Format baku: kata berjarak spasi, huruf awal kapital, diakhiri "rupiah."
 */
if (!function_exists('terbilangAngkaPHP')) {
    function terbilangAngkaPHP($angka) {
        $huruf = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        $angka = (float)$angka;
        if ($angka < 12) {
            return $huruf[(int)$angka];
        } elseif ($angka < 20) {
            return terbilangAngkaPHP($angka - 10) . ' belas';
        } elseif ($angka < 100) {
            $puluh = terbilangAngkaPHP(floor($angka / 10)) . ' puluh';
            $sisa = terbilangAngkaPHP(fmod($angka, 10));
            return $puluh . ($sisa ? ' ' . $sisa : '');
        } elseif ($angka < 200) {
            $sisa = terbilangAngkaPHP($angka - 100);
            return 'seratus' . ($sisa ? ' ' . $sisa : '');
        } elseif ($angka < 1000) {
            $ratus = terbilangAngkaPHP(floor($angka / 100)) . ' ratus';
            $sisa = terbilangAngkaPHP(fmod($angka, 100));
            return $ratus . ($sisa ? ' ' . $sisa : '');
        } elseif ($angka < 2000) {
            $sisa = terbilangAngkaPHP($angka - 1000);
            return 'seribu' . ($sisa ? ' ' . $sisa : '');
        } elseif ($angka < 1000000) {
            $ribu = terbilangAngkaPHP(floor($angka / 1000)) . ' ribu';
            $sisa = terbilangAngkaPHP(fmod($angka, 1000));
            return $ribu . ($sisa ? ' ' . $sisa : '');
        } elseif ($angka < 1000000000) {
            $juta = terbilangAngkaPHP(floor($angka / 1000000)) . ' juta';
            $sisa = terbilangAngkaPHP(fmod($angka, 1000000));
            return $juta . ($sisa ? ' ' . $sisa : '');
        } elseif ($angka < 1000000000000) {
            $miliar = terbilangAngkaPHP(floor($angka / 1000000000)) . ' miliar';
            $sisa = terbilangAngkaPHP(fmod($angka, 1000000000));
            return $miliar . ($sisa ? ' ' . $sisa : '');
        } else {
            $triliun = terbilangAngkaPHP(floor($angka / 1000000000000)) . ' triliun';
            $sisa = terbilangAngkaPHP(fmod($angka, 1000000000000));
            return $triliun . ($sisa ? ' ' . $sisa : '');
        }
    }
}

if (!function_exists('getTerbilangLengkapPHP')) {
    function getTerbilangLengkapPHP($val) {
        $num = (float)$val;
        if ($num == 0) return 'Nol rupiah';

        $bagianBulat = floor($num);
        $bagianDesimal = round(($num - $bagianBulat) * 100);

        $kata = trim(terbilangAngkaPHP($bagianBulat));
        if ($kata !== '') {
            $kata = ucfirst($kata);
        }

        if ($bagianDesimal > 0) {
            $desimalKata = trim(terbilangAngkaPHP($bagianDesimal));
            return $kata . ' koma ' . $desimalKata . ' rupiah.';
        } else {
            return $kata . ' rupiah.';
        }
    }
}
