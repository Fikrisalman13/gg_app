<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');
require_once __DIR__ . '/rekap_biaya_energi_data.php';

$menuId = 230;
requireView($conn, $menuId);

$start = normalizeDateInputRekapBiayaEnergi($_POST['start_date'] ?? date('Y-m-01'));
$end = normalizeDateInputRekapBiayaEnergi($_POST['end_date'] ?? date('Y-m-d'));

if ($start === '') {
    $start = date('Y-m-01');
}
if ($end === '') {
    $end = date('Y-m-d');
}

if (strtotime($start) > strtotime($end)) {
    $tmp = $start;
    $start = $end;
    $end = $tmp;
}

$errorMsg = '';
$data = buildRekapBiayaEnergiData($conn, $start, $end, $errorMsg);
if ($errorMsg !== '') {
    echo '<b>Gagal menyiapkan export:</b> ' . htmlspecialchars($errorMsg);
    exit;
}

$monthLabel = monthLabelRekapBiayaEnergi($start);

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename=rekapan_biaya_energi_' . $start . '_sd_' . $end . '.xls');
header('Pragma: no-cache');
header('Expires: 0');

echo "<html><head><meta charset='UTF-8'><style>
table{border-collapse:collapse;border-spacing:0;font-size:11px;min-width:1780px;background:#fff}
th,td{border:1px solid #000;text-align:center;vertical-align:middle;padding:2px 4px;white-space:nowrap}
.title{background:#fff200;font-weight:700;font-size:34px;line-height:1.05}
.month{background:#efe8b8;font-weight:700;font-size:24px}
.h-date{background:#d9d9c9;font-weight:700;min-width:80px}
.h-listrik{background:#fff200;font-weight:700;min-width:110px}
.h-ipab{background:#95b3d7;font-weight:700;min-width:110px}
.h-kimia{background:#d9d9d9;font-weight:700;min-width:110px}
.h-bb{background:#e6b8af;font-weight:700;min-width:118px}
.h-lpg{background:#fff200;font-weight:700;min-width:112px}
.h-total{background:#ffc000;font-weight:700;min-width:112px}
.unit{background:#fce5cd;font-weight:700}
.d-date{background:#f0f4e3;font-weight:700}
.d-listrik{background:#fff200}
.d-ipab{background:#b8cce4}
.d-kimia{background:#d9d9d9}
.d-bb{background:#c9c9c9}
.d-lpg{background:#fff200}
.d-total{background:#ffc000}
.sum td{background:#fff200 !important;font-weight:700}
</style></head><body>";

echo "<table>";
echo "<thead>";
echo "<tr><th class='title' colspan='14'>REKAPAN BIAYA ENERGI</th></tr>";
echo "<tr><th class='month' colspan='14'>Bulan: " . htmlspecialchars($monthLabel) . "</th></tr>";
echo "<tr>";
echo "<th class='h-date' rowspan='2'>Tanggal</th>";
echo "<th class='h-listrik' rowspan='2'>Biaya Listrik</th>";
echo "<th class='h-ipab' rowspan='2'>Biaya<br>Chemical Air<br>IPAB</th>";
echo "<th class='h-kimia' rowspan='2'>Biaya Kimia<br>IPAL</th>";
echo "<th class='h-kimia' rowspan='2'>BIAYA KIMIA<br>BOILER</th>";
echo "<th class='h-kimia' rowspan='2'>BIAYA<br>KIMIA<br>WEAVING</th>";
echo "<th class='h-bb' rowspan='2'>Biaya Batu Bara<br>Boiler Wuxi</th>";
echo "<th class='h-bb' rowspan='2'>Biaya Batu<br>Bara Boiler<br>OIL XINENG</th>";
echo "<th class='h-bb' rowspan='2'>Biaya Batu<br>Bara Boiler<br>STEAM 20TON<br>LAMA</th>";
echo "<th class='h-bb' rowspan='2'>Biaya Batu<br>Bara Boiler<br>STEAM 20TON<br>BARU<br>LONGCHUAN</th>";
echo "<th class='h-bb' rowspan='2'>Biaya Batu Bara<br>Boiler STEAM<br>21TON ACTOM</th>";
echo "<th class='h-bb' rowspan='2'>Biaya Batu Bara<br>Boiler JINENG</th>";
echo "<th class='h-lpg' rowspan='2'>Biaya Gas LPG<br>Skid Tank</th>";
echo "<th class='h-total' rowspan='2'>Total Biaya</th>";
echo "</tr><tr></tr>";
echo "<tr>";
echo "<th class='unit'></th>";
for ($i = 0; $i < 13; $i++) {
    echo "<th class='unit'>(Rp)</th>";
}
echo "</tr>";
echo "</thead><tbody>";

if (empty($data['rows'])) {
    echo "<tr><td colspan='14'>Tidak ada data pada rentang tanggal ini.</td></tr>";
} else {
    foreach ($data['rows'] as $r) {
        echo "<tr>";
        echo "<td class='d-date'>" . htmlspecialchars(fmtDayRekapBiayaEnergi($r['tanggal'])) . "</td>";
        echo "<td class='d-listrik'>" . htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_listrik'])) . "</td>";
        echo "<td class='d-ipab'>" . htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_kimia_ipab'])) . "</td>";
        echo "<td class='d-kimia'>" . htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_kimia_ipal'])) . "</td>";
        echo "<td class='d-kimia'>" . htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_kimia_boiler'])) . "</td>";
        echo "<td class='d-kimia'>" . htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_kimia_weaving'])) . "</td>";
        echo "<td class='d-bb'>" . htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_bb_wuxi'])) . "</td>";
        echo "<td class='d-bb'>" . htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_bb_oil_xineng'])) . "</td>";
        echo "<td class='d-bb'>" . htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_bb_20t_lama'])) . "</td>";
        echo "<td class='d-bb'>" . htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_bb_20t_baru_longchuan'])) . "</td>";
        echo "<td class='d-bb'>" . htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_bb_21t_actom'])) . "</td>";
        echo "<td class='d-bb'>" . htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_bb_jineng'])) . "</td>";
        echo "<td class='d-lpg'>" . htmlspecialchars(fmtNumRekapBiayaEnergi($r['biaya_lpg_skid_tank'])) . "</td>";
        echo "<td class='d-total'>" . htmlspecialchars(fmtNumRekapBiayaEnergi($r['total_biaya'])) . "</td>";
        echo "</tr>";
    }

    echo "<tr class='sum'>";
    echo "<td>TOTAL</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_listrik'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_kimia_ipab'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_kimia_ipal'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_kimia_boiler'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_kimia_weaving'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_bb_wuxi'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_bb_oil_xineng'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_bb_20t_lama'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_bb_20t_baru_longchuan'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_bb_21t_actom'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_bb_jineng'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['biaya_lpg_skid_tank'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['totals']['total_biaya'])) . "</td>";
    echo "</tr>";

    echo "<tr class='sum'>";
    echo "<td>RATA-RATA</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_listrik'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_kimia_ipab'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_kimia_ipal'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_kimia_boiler'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_kimia_weaving'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_bb_wuxi'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_bb_oil_xineng'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_bb_20t_lama'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_bb_20t_baru_longchuan'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_bb_21t_actom'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_bb_jineng'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['biaya_lpg_skid_tank'])) . "</td>";
    echo "<td>" . htmlspecialchars(fmtNumRekapBiayaEnergi($data['averages']['total_biaya'])) . "</td>";
    echo "</tr>";
}

echo "</tbody></table></body></html>";
