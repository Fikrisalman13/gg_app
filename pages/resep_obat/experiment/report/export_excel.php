<?php
session_start();
require_once __DIR__ . '/report_export_data.php';

if (!isset($_SESSION['UserName']) || !report_export_can_view($conn)) {
  http_response_code(403);
  exit('Forbidden');
}

$rows = report_export_rows($conn, $conn3, $_GET);
usort($rows, function ($a, $b) {
  foreach (['label', 'kode_lab_warna', 'status', 'no_cp', 'experiment_seq', 'tgl_match', 'tgl_planning', 'mesin_paddry', 'qty', 'aktual_qty', 'posisi_hari_ini', 'acc_warna_r', 'keputusan', 'tindakan', 'qc_catatan'] as $key) {
    $cmp = strcmp((string) $a[$key], (string) $b[$key]);
    if ($cmp !== 0) return $cmp;
  }
  return 0;
});

$headers = ['No', 'Tgl Match', 'Label', 'KodeLab / Warna', 'Status', 'No CP', 'Exp', 'Tgl Planning', 'Tgl Celup Padd', 'Mesin Paddry', 'Planning Qty', 'Aktual Qty PMT', 'Posisi Hari Ini', 'ACC Warna R', 'Keputusan', 'Tindakan QC', 'Catatan QC'];
$fields = ['tgl_match', 'label', 'kode_lab_warna', 'status', 'no_cp', 'experiment_seq', 'tgl_planning', 'tgl_celup_padd', 'mesin_paddry', 'qty', 'aktual_qty', 'posisi_hari_ini', 'acc_warna_r', 'keputusan', 'tindakan', 'qc_catatan'];
$esc = static fn($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

$labelSpans = [];
foreach ($rows as $idx => $row) {
  if ($idx > 0 && (string) $row['label'] === (string) $rows[$idx - 1]['label']) continue;
  $span = 1;
  for ($j = $idx + 1; $j < count($rows) && (string) $rows[$j]['label'] === (string) $row['label']; $j++) $span++;
  $labelSpans[$idx] = $span;
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="laporan_hasil_eksperimen_' . date('Ymd_His') . '.xls"');
header('Cache-Control: max-age=0');
echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; }
    th, td { border: 1px solid #777; padding: 4px 6px; vertical-align: middle; mso-number-format: "\@"; }
    th { background: #eeeeee; font-weight: bold; text-align: center; }
    .center { text-align: center; }
    .right { text-align: right; }
    .wrap { white-space: normal; }
  </style>
</head>
<body>
  <table>
    <thead>
      <tr>
        <?php foreach ($headers as $header): ?>
          <th><?= $esc($header) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr>
          <td colspan="<?= count($headers) ?>" class="center">Tidak ada data</td>
        </tr>
      <?php endif; ?>
      <?php foreach ($rows as $idx => $row): ?>
        <tr>
          <td class="center"><?= $idx + 1 ?></td>
          <?php foreach ($fields as $field): ?>
            <?php if ($field === 'label' && !isset($labelSpans[$idx])) continue; ?>
            <?php $rowspan = $field === 'label' ? (int) $labelSpans[$idx] : 1; ?>
            <td<?= $rowspan > 1 ? ' rowspan="' . $rowspan . '"' : '' ?> class="<?= in_array($field, ['qty', 'aktual_qty'], true) ? 'right' : 'center' ?> wrap">
              <?php if ($field === 'kode_lab_warna'): ?>
                <?php $parts = explode("\n", (string) ($row[$field] ?? '-'), 2); ?>
                <strong><?= $esc($parts[0] ?? '-') ?></strong><br><?= $esc($parts[1] ?? '-') ?>
              <?php elseif ($field === 'experiment_seq'): ?>
                <strong>#<?= $esc($row[$field] ?? '-') ?></strong><br style="mso-data-placement:same-cell;"><?= $esc($row['created_by'] ?? '-') ?>
              <?php elseif ($field === 'mesin_paddry'): ?>
                <?= implode('<br style="mso-data-placement:same-cell;">', array_map($esc, preg_split('/\r\n|\r|\n/', (string) ($row[$field] ?? '-')))) ?>
              <?php else: ?>
                <?= $esc($row[$field] ?? '-') ?>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
<?php
exit;
