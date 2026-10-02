<?php
$historyCpRows = [];
$historyCpWarning = '';
$historyAccWarnaRSecondColumnLabel = 'Nama Fail';

$historyEsc = static function ($value): string {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$historyFormatDateShort = static function ($value): string {
  if ($value === null || $value === '') {
    return '-';
  }
  $formatFullDate = static function (DateTimeInterface $dt): string {
    $months = [
      1 => 'Januari',
      2 => 'Februari',
      3 => 'Maret',
      4 => 'April',
      5 => 'Mei',
      6 => 'Juni',
      7 => 'Juli',
      8 => 'Agustus',
      9 => 'September',
      10 => 'Oktober',
      11 => 'November',
      12 => 'Desember',
    ];

    $month = $months[(int) $dt->format('n')] ?? $dt->format('F');
    return $dt->format('j') . ' ' . $month . ' ' . $dt->format('Y');
  };

  if ($value instanceof DateTimeInterface) {
    return $formatFullDate($value);
  }

  $raw = trim((string) $value);
  if ($raw === '') {
    return '-';
  }

  $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s.u'];
  foreach ($formats as $fmt) {
    $dt = DateTime::createFromFormat($fmt, $raw);
    if ($dt instanceof DateTime) {
      return $formatFullDate($dt);
    }
  }

  $ts = strtotime($raw);
  return ($ts !== false) ? $formatFullDate((new DateTime())->setTimestamp($ts)) : $raw;
};

$historyCpNo = trim((string) ($data['group_no_cp'] ?? ''));
if ($historyCpNo === '') {
  $historyCpNo = trim((string) ($data['no_cp'] ?? ''));
}
$historyCelupPaddryRtgmsIds = [838, 842, 555, 556, 559, 809];
$historyAccWarnaRRtgmsId = 589;
$historyPemartaianRtgmsId = 409;
$historyVerpackingRtgmsId = 692;

if ($historyCpNo === '') {
  $historyCpWarning = 'No CP belum tersedia.';
} else {
  try {
    if (!isset($conn3) || !($conn3 instanceof PDO)) {
      require_once __DIR__ . '/../../../../koneksi3.php';
    }

    $tglCelupPaddryRaw = null;
    $productionId = 0;
    $stmtProduction = $conn3->prepare("
      WITH ranked AS (
        SELECT
          productionhdid,
          TRIM(CAST(prdnmbr AS TEXT)) AS cp_no,
          prddate,
          ROW_NUMBER() OVER (
            ORDER BY prddate DESC NULLS LAST, productionhdid DESC
          ) AS rn
        FROM pdproductionhd
        WHERE UPPER(TRIM(CAST(prdnmbr AS TEXT))) = UPPER(:cp_no)
      )
      SELECT productionhdid, cp_no, prddate
      FROM ranked
      WHERE rn = 1
    ");
    $stmtProduction->execute([':cp_no' => $historyCpNo]);
    if ($rowProduction = $stmtProduction->fetch(PDO::FETCH_ASSOC)) {
      $productionId = (int) ($rowProduction['productionhdid'] ?? 0);
    }

    $accWarnaR = [
      'tgl_raw' => null,
      'nama_fail' => '-',
      'status' => '-',
      'desc_fail' => '-',
      'is_pass' => false,
    ];

    if ($productionId > 0) {
      $posisiHariIni = '-';
      $hasFilledProgress = false;
      $celupPlaceholders = implode(', ', array_map(static function ($idx): string {
        return ':celup_rtg_' . $idx;
      }, array_keys($historyCelupPaddryRtgmsIds)));

      $stmtCelup = $conn3->prepare("
        SELECT
          r.productionrtgid,
          r.rtgseq,
          r.rtgmsid,
          r.starttime
        FROM pdproductionrtg r
        WHERE r.productionhdid = :production_id
          AND r.rtgmsid IN ($celupPlaceholders)
          AND r.starttime IS NOT NULL
        ORDER BY
          r.rtgseq ASC NULLS LAST,
          r.starttime ASC NULLS LAST,
          r.productionrtgid ASC
        LIMIT 1
      ");
      $celupParams = [':production_id' => $productionId];
      foreach ($historyCelupPaddryRtgmsIds as $idx => $rtgmsId) {
        $celupParams[':celup_rtg_' . $idx] = $rtgmsId;
      }
      $stmtCelup->execute($celupParams);
      if ($rowCelup = $stmtCelup->fetch(PDO::FETCH_ASSOC)) {
        $tglCelupPaddryRaw = $rowCelup['starttime'] ?? null;
      }

      $stmtPosisi = $conn3->prepare("
        WITH last_filled AS (
          SELECT
            rtgseq,
            rtgmsid
          FROM pdproductionrtg
          WHERE productionhdid = :production_id_filled
            AND (
              COALESCE(prdqty, 0) > 0
              OR prduomid IS NOT NULL
              OR COALESCE(prdstdqty, 0) > 0
            )
          ORDER BY rtgseq DESC NULLS LAST, productionrtgid DESC
          LIMIT 1
        )
        SELECT
          lf.rtgseq AS filled_rtgseq,
          lf.rtgmsid AS filled_rtgmsid,
          COALESCE(m.rtgname, '') AS rtgname
        FROM last_filled lf
        LEFT JOIN pdproductionrtg r
          ON r.productionhdid = :production_id_next
          AND r.rtgseq = lf.rtgseq + 1
        LEFT JOIN pdrtgms m
          ON r.rtgmsid = m.rtgmsid
        LIMIT 1
      ");
      $stmtPosisi->execute([
        ':production_id_filled' => $productionId,
        ':production_id_next' => $productionId,
      ]);
      if ($rowPosisi = $stmtPosisi->fetch(PDO::FETCH_ASSOC)) {
        $hasFilledProgress = $rowPosisi['filled_rtgseq'] !== null && $rowPosisi['filled_rtgseq'] !== '';
        $filledRtgmsId = (int) ($rowPosisi['filled_rtgmsid'] ?? 0);
        $rtgName = trim((string) ($rowPosisi['rtgname'] ?? ''));
        $posisiHariIni = $filledRtgmsId === $historyVerpackingRtgmsId ? 'VERPACKING' : ($rtgName !== '' ? $rtgName : '-');
      }

      if (!$hasFilledProgress) {
        $stmtFirstRoute = $conn3->prepare("
          SELECT COALESCE(m.rtgname, '') AS rtgname
          FROM pdproductionrtg r
          LEFT JOIN pdrtgms m
            ON r.rtgmsid = m.rtgmsid
          WHERE r.productionhdid = :production_id
            AND r.rtgmsid = :pemartaian_rtgmsid
          ORDER BY r.rtgseq ASC NULLS LAST, r.productionrtgid ASC
          LIMIT 1
        ");
        $stmtFirstRoute->execute([
          ':production_id' => $productionId,
          ':pemartaian_rtgmsid' => $historyPemartaianRtgmsId,
        ]);
        if ($rowFirstRoute = $stmtFirstRoute->fetch(PDO::FETCH_ASSOC)) {
          $firstRouteName = trim((string) ($rowFirstRoute['rtgname'] ?? ''));
          $posisiHariIni = $firstRouteName !== '' ? $firstRouteName : 'PEMARTAIAN';
        }
      }

      $stmtFail = $conn3->prepare("
        SELECT
          r.productionhdid,
          r.productionrtgid,
          r.rtgmsid,
          COALESCE(r.failmsid, 0) AS failmsid,
          COALESCE(r.fgresult, '') AS fgresult,
          COALESCE(r.resultdesc, '') AS resultdesc,
          r.starttime AS acc_warna_r_date,
          COALESCE(f.failcode, '') AS failcode,
          COALESCE(f.faildesc, '') AS faildesc
        FROM pdproductionrtg r
        LEFT JOIN pdfailms f
          ON r.failmsid = f.failmsid
        WHERE r.productionhdid = :production_id
          AND r.rtgmsid = :acc_warna_r_rtgmsid
        ORDER BY
          CASE WHEN r.failmsid IS NOT NULL AND r.failmsid <> 0 THEN 0 ELSE 1 END ASC,
          r.starttime DESC NULLS LAST,
          r.productionrtgid DESC
        LIMIT 1
      ");
      $stmtFail->execute([
        ':production_id' => $productionId,
        ':acc_warna_r_rtgmsid' => $historyAccWarnaRRtgmsId,
      ]);
      if ($rowFail = $stmtFail->fetch(PDO::FETCH_ASSOC)) {
        $failCode = trim((string) ($rowFail['failcode'] ?? ''));
        $failDesc = trim((string) ($rowFail['faildesc'] ?? ''));
        $failMsId = (int) ($rowFail['failmsid'] ?? 0);
        $fgResult = strtoupper(trim((string) ($rowFail['fgresult'] ?? '')));
        $resultDesc = trim((string) ($rowFail['resultdesc'] ?? ''));

        $failName = '-';
        if ($failCode !== '' && $failDesc !== '') {
          $failName = $failCode . ' - ' . $failDesc;
        } elseif ($failDesc !== '') {
          $failName = $failDesc;
        } elseif ($failCode !== '') {
          $failName = $failCode;
        }
        $isPass = $fgResult === 'P' && $failMsId === 0 && $failName === '-';

        $accWarnaR = [
          'tgl_raw' => $rowFail['acc_warna_r_date'] ?? null,
          'nama_fail' => $failName,
          'status' => $isPass ? 'Pass' : '-',
          'desc_fail' => $resultDesc !== '' ? $resultDesc : '-',
          'is_pass' => $isPass,
        ];
      }
    }

    if (!empty($accWarnaR['is_pass'])) {
      $historyAccWarnaRSecondColumnLabel = 'Status';
    }

    $historyCpRows[] = [
      'cp' => $historyCpNo,
      'tgl_celup_paddry' => $historyFormatDateShort($tglCelupPaddryRaw),
      'posisi_hari_ini' => $posisiHariIni ?? '-',
      'acc_warna_r_tgl' => $historyFormatDateShort($accWarnaR['tgl_raw'] ?? null),
      'acc_warna_r_second_value' => !empty($accWarnaR['is_pass']) ? ($accWarnaR['status'] ?? 'Pass') : ($accWarnaR['nama_fail'] ?? '-'),
      'acc_warna_r_desc_fail' => $accWarnaR['desc_fail'] ?? '-',
    ];
  } catch (Throwable $e) {
    $historyCpWarning = 'History CP belum bisa dibaca: ' . $e->getMessage();
  }
}
?>
<style>
  .history-cp-table th,
  .history-cp-table td {
    font-size: .875rem;
    vertical-align: middle;
  }

  .history-cp-table thead th {
    background: #f4f6f9;
    text-align: center;
    vertical-align: middle;
  }

  .history-cp-table thead th.history-cp-accent {
    color: #fff;
  }

  .history-cp-table tbody td {
    text-align: center;
  }
</style>
<div class="card">
  <div class="card-header bg-light">
    <h3 class="card-title mb-0"><i class="fas fa-history mr-1"></i> History</h3>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered table-sm mb-0 history-cp-table">
        <thead>
          <tr>
            <th rowspan="2" style="width:18%">CP</th>
            <th rowspan="2" style="width:15%">Tanggal Celup Paddry</th>
            <th rowspan="2" style="width:18%">Posisi Hari Ini</th>
            <th colspan="3" class="history-cp-accent bg-<?= $historyEsc($themeColor ?? 'primary') ?>">ACC WARNA R</th>
          </tr>
          <tr>
            <th style="width:13%">Tanggal</th>
            <th style="width:18%"><?= $historyEsc($historyAccWarnaRSecondColumnLabel) ?></th>
            <th style="width:18%">Description Fail</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($historyCpRows)): ?>
            <?php foreach ($historyCpRows as $historyRow): ?>
              <tr>
                <td><?= $historyEsc($historyRow['cp'] ?? '-') ?></td>
                <td><?= $historyEsc($historyRow['tgl_celup_paddry'] ?? '-') ?></td>
                <td><?= $historyEsc($historyRow['posisi_hari_ini'] ?? '-') ?></td>
                <td><?= $historyEsc($historyRow['acc_warna_r_tgl'] ?? '-') ?></td>
                <td><?= $historyEsc($historyRow['acc_warna_r_second_value'] ?? '-') ?></td>
                <td><?= $historyEsc($historyRow['acc_warna_r_desc_fail'] ?? '-') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-muted text-center">
                <?= $historyEsc($historyCpWarning !== '' ? $historyCpWarning : 'Belum ada data History CP.') ?>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
