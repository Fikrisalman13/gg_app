<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'C:/xampp/htdocs';
include($docRoot . '/gg_app/koneksi.php');
include($docRoot . '/gg_app/includes/permissions.php');

$menuId = 230;
$permissions = getPermissions($conn, $_SESSION['GroupId'] ?? 0, $menuId);
if (!empty($permissions) && isset($permissions['CanView']) && $permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: /gg_app/index.php');
    exit;
}

$canAdd = !empty($permissions['CanAdd']) && (int)$permissions['CanAdd'] === 1;

// Urutan siklus 24 jam cut-off jam 09:00 s/d 08:00
$cycleOrder = [9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 0, 1, 2, 3, 4, 5, 6, 7, 8];

// Helper kalkulasi Awal & Akhir dari array pembacaan jam
function hitungAwalAkhir($readings, $cycleOrder) {
    $ordered = [];
    foreach ($cycleOrder as $j) {
        if (isset($readings[$j]) && $readings[$j] !== null && $readings[$j] !== '') {
            $ordered[] = ['jam' => $j, 'val' => (float)$readings[$j]];
        }
    }
    if (empty($ordered)) {
        return ['awal' => 0.0, 'akhir' => 0.0, 'jam_awal' => null, 'jam_akhir' => null];
    }
    $first = $ordered[0];
    $last = $ordered[count($ordered) - 1];
    return [
        'awal' => (float)$first['val'],
        'akhir' => (float)$last['val'],
        'jam_awal' => $first['jam'],
        'jam_akhir' => $last['jam']
    ];
}

// ==========================================
// HANDLE AJAX POST: EXECUTE MIGRATION
// ==========================================
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'execute_migration') {
    header('Content-Type: application/json');
    if (!$canAdd) {
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk menambah/migrasi data.']);
        exit;
    }

    $hdrSql = "SELECT id, periode, note, created_by, created_at, updated_by, updated_at 
               FROM dbo.pmlonchuan_hdr 
               ORDER BY CAST(periode AS DATE) ASC, id ASC";
    $hdrStmt = sqlsrv_query($conn, $hdrSql);
    if ($hdrStmt === false) {
        echo json_encode(['success' => false, 'message' => 'Gagal membaca data header: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }

    $migrated = [];
    $skipped = [];
    $errors = [];

    while ($h = sqlsrv_fetch_array($hdrStmt, SQLSRV_FETCH_ASSOC)) {
        $hdrId = (int)$h['id'];
        $pObj = $h['periode'];
        $tanggal = ($pObj instanceof DateTime) ? $pObj->format('Y-m-d') : date('Y-m-d', strtotime((string)$pObj));
        if ($tanggal === '' || $tanggal === '1970-01-01') continue;

        // Cek apakah tanggal sudah ada di tabel baru
        $checkStmt = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.air_steam_20tlonchuan_harian WHERE tanggal=?", [$tanggal]);
        $existsRow = $checkStmt ? sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC) : null;
        if ($checkStmt) sqlsrv_free_stmt($checkStmt);

        if ($existsRow) {
            $skipped[] = [
                'tanggal' => $tanggal,
                'hdr_id' => $hdrId,
                'reason' => 'Tanggal sudah ada di tabel baru (ID #' . $existsRow['id'] . ')'
            ];
            continue;
        }

        // Ambil detail jam per jenis
        $dtlStmt = sqlsrv_query($conn, "SELECT jam, jenis, nilai_ton FROM dbo.pmlonchuan_dtl WHERE hdr_id=? ORDER BY jam ASC", [$hdrId]);
        $airReadings = [];
        $steamReadings = [];
        if ($dtlStmt) {
            while ($d = sqlsrv_fetch_array($dtlStmt, SQLSRV_FETCH_ASSOC)) {
                $j = (int)$d['jam'];
                $val = (float)($d['nilai_ton'] ?? 0);
                if (strtoupper(trim($d['jenis'])) === 'AIR') {
                    $airReadings[$j] = $val;
                } else {
                    $steamReadings[$j] = $val;
                }
            }
            sqlsrv_free_stmt($dtlStmt);
        }

        // Koreksi typo pada 2026-10-13 (jam 6 tertulis 174154 harusnya 17415.4)
        if ($tanggal === '2026-10-13' && isset($steamReadings[6]) && $steamReadings[6] == 174154) {
            $steamReadings[6] = 17415.4;
        }

        $airCalc = hitungAwalAkhir($airReadings, $cycleOrder);
        $steamCalc = hitungAwalAkhir($steamReadings, $cycleOrder);

        $airAwal = $airCalc['awal'];
        $airAkhir = $airCalc['akhir'];
        $airTotal = $airAkhir - $airAwal;
        if ($airTotal < 0) $airTotal = 0;
        $airRata = $airTotal / 24;

        $steamAwal = $steamCalc['awal'];
        $steamAkhir = $steamCalc['akhir'];
        $steamTotal = $steamAkhir - $steamAwal;
        if ($steamTotal < 0) $steamTotal = 0;
        $steamRata = $steamTotal / 24;

        $cutOff = '09:00';
        $note = trim((string)($h['note'] ?? ''));
        $creatby = trim((string)($h['created_by'] ?? $_SESSION['UserName'] ?? 'system_migration'));
        $updateby = trim((string)($h['updated_by'] ?? ''));

        $insertSql = "INSERT INTO dbo.air_steam_20tlonchuan_harian (
            tanggal, air_awal_ton, air_akhir_ton, air_total_pemakaian_ton, air_rata_rata_per_jam_ton, air_ket,
            steam_awal_ton, steam_akhir_ton, steam_total_pemakaian_ton, steam_rata_rata_per_jam_ton, steam_ket,
            cut_off_jam, note, creatby, createat, updateby, updateat
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, SYSDATETIME(), ?, NULL)";

        $insertParams = [
            $tanggal,
            $airAwal, $airAkhir, $airTotal, $airRata, '',
            $steamAwal, $steamAkhir, $steamTotal, $steamRata, '',
            $cutOff, $note, $creatby,
            ($updateby !== '' ? $updateby : null)
        ];

        $insStmt = sqlsrv_query($conn, $insertSql, $insertParams);
        if ($insStmt === false) {
            $errors[] = [
                'tanggal' => $tanggal,
                'hdr_id' => $hdrId,
                'error' => print_r(sqlsrv_errors(), true)
            ];
        } else {
            sqlsrv_free_stmt($insStmt);
            $migrated[] = [
                'tanggal' => $tanggal,
                'hdr_id' => $hdrId,
                'air_total' => $airTotal,
                'steam_total' => $steamTotal
            ];
        }
    }
    sqlsrv_free_stmt($hdrStmt);

    echo json_encode([
        'success' => count($errors) === 0,
        'migrated_count' => count($migrated),
        'skipped_count' => count($skipped),
        'error_count' => count($errors),
        'migrated' => $migrated,
        'skipped' => $skipped,
        'errors' => $errors,
        'message' => count($migrated) . " data berhasil dimigrasi, " . count($skipped) . " data dilewati (skip)."
    ]);
    exit;
}

// ==========================================
// RENDER GET PAGE: PREVIEW & STATS
// ==========================================
include($docRoot . '/gg_app/includes/header.php');
include($docRoot . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Query semua header lama
$hdrSql = "SELECT id, periode, note, created_by, created_at, updated_by, updated_at 
           FROM dbo.pmlonchuan_hdr 
           ORDER BY CAST(periode AS DATE) ASC, id ASC";
$hdrStmt = sqlsrv_query($conn, $hdrSql);
$previewList = [];
$totalOld = 0;
$readyCount = 0;
$skippedCount = 0;

if ($hdrStmt) {
    while ($h = sqlsrv_fetch_array($hdrStmt, SQLSRV_FETCH_ASSOC)) {
        $totalOld++;
        $hdrId = (int)$h['id'];
        $pObj = $h['periode'];
        $tanggal = ($pObj instanceof DateTime) ? $pObj->format('Y-m-d') : date('Y-m-d', strtotime((string)$pObj));

        // Cek apakah tanggal sudah ada di tabel baru
        $checkStmt = sqlsrv_query($conn, "SELECT TOP 1 id FROM dbo.air_steam_20tlonchuan_harian WHERE tanggal=?", [$tanggal]);
        $existsRow = $checkStmt ? sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC) : null;
        if ($checkStmt) sqlsrv_free_stmt($checkStmt);
        $alreadyExists = ($existsRow !== null);

        if ($alreadyExists) {
            $skippedCount++;
        } else {
            $readyCount++;
        }

        // Ambil detail jam
        $dtlStmt = sqlsrv_query($conn, "SELECT jam, jenis, nilai_ton FROM dbo.pmlonchuan_dtl WHERE hdr_id=? ORDER BY jam ASC", [$hdrId]);
        $airReadings = [];
        $steamReadings = [];
        if ($dtlStmt) {
            while ($d = sqlsrv_fetch_array($dtlStmt, SQLSRV_FETCH_ASSOC)) {
                $j = (int)$d['jam'];
                $val = (float)($d['nilai_ton'] ?? 0);
                if (strtoupper(trim($d['jenis'])) === 'AIR') {
                    $airReadings[$j] = $val;
                } else {
                    $steamReadings[$j] = $val;
                }
            }
            sqlsrv_free_stmt($dtlStmt);
        }

        // Koreksi typo 2026-10-13 jam 6
        $isTypoCorrected = false;
        if ($tanggal === '2026-10-13' && isset($steamReadings[6]) && $steamReadings[6] == 174154) {
            $steamReadings[6] = 17415.4;
            $isTypoCorrected = true;
        }

        $airCalc = hitungAwalAkhir($airReadings, $cycleOrder);
        $steamCalc = hitungAwalAkhir($steamReadings, $cycleOrder);

        $airTotal = $airCalc['akhir'] - $airCalc['awal'];
        if ($airTotal < 0) $airTotal = 0;
        $airRata = $airTotal / 24;

        $steamTotal = $steamCalc['akhir'] - $steamCalc['awal'];
        if ($steamTotal < 0) $steamTotal = 0;
        $steamRata = $steamTotal / 24;

        $previewList[] = [
            'id' => $hdrId,
            'tanggal' => $tanggal,
            'air_awal' => $airCalc['awal'],
            'air_akhir' => $airCalc['akhir'],
            'air_total' => $airTotal,
            'air_rata' => $airRata,
            'steam_awal' => $steamCalc['awal'],
            'steam_akhir' => $steamCalc['akhir'],
            'steam_total' => $steamTotal,
            'steam_rata' => $steamRata,
            'note' => $h['note'] ?? '',
            'created_by' => $h['created_by'] ?? '',
            'already_exists' => $alreadyExists,
            'is_typo_corrected' => $isTypoCorrected
        ];
    }
    sqlsrv_free_stmt($hdrStmt);
}

$fmt = function($v, $d=2){ return is_numeric($v) ? number_format((float)$v, $d, '.', ',') : '-'; };
?>

<div class="wrapper">
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-6">
          <h1 class="m-0"><i class="fas fa-database mr-2 text-primary"></i>Migrasi Data 20T Lonchuan</h1>
        </div>
        <div class="col-sm-6 text-right">
          <a href="20tlonchuan.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali ke Data Utama</a>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">

      <!-- INFO CARDS -->
      <div class="row">
        <div class="col-md-4 col-sm-6 col-12">
          <div class="info-box shadow-sm">
            <span class="info-box-icon bg-info"><i class="fas fa-file-alt"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Total Data Historis Lama</span>
              <span class="info-box-number"><?= $totalOld ?> <small>Tanggal</small></span>
            </div>
          </div>
        </div>
        <div class="col-md-4 col-sm-6 col-12">
          <div class="info-box shadow-sm">
            <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Siap Dimigrasi</span>
              <span class="info-box-number" id="boxReadyCount"><?= $readyCount ?> <small>Tanggal</small></span>
            </div>
          </div>
        </div>
        <div class="col-md-4 col-sm-6 col-12">
          <div class="info-box shadow-sm">
            <span class="info-box-icon bg-warning"><i class="fas fa-forward"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Sudah Ada di Tabel Baru (Skip)</span>
              <span class="info-box-number" id="boxSkippedCount"><?= $skippedCount ?> <small>Tanggal</small></span>
            </div>
          </div>
        </div>
      </div>

      <!-- MAIN CARD -->
      <div class="card card-outline card-primary shadow-sm">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white d-flex align-items-center">
          <h3 class="card-title"><i class="fas fa-list-check mr-2"></i>Preview Data yang Akan Dimigrasi</h3>
          <div class="ml-auto" style="gap:8px;">
            <?php if ($canAdd): ?>
              <?php if ($readyCount > 0): ?>
                <button type="button" class="btn btn-success btn-sm shadow" id="btnMulaiMigrasi">
                  <i class="fas fa-play mr-1"></i> Jalankan Migrasi Sekarang (<?= $readyCount ?> Tanggal)
                </button>
              <?php else: ?>
                <button type="button" class="btn btn-light btn-sm" disabled>
                  <i class="fas fa-check-double text-success mr-1"></i> Semua Data Sudah Dimigrasi
                </button>
              <?php endif; ?>
            <?php endif; ?>
            <button type="button" class="btn btn-light btn-sm ml-2" onclick="location.reload();">
              <i class="fas fa-sync-alt mr-1"></i> Refresh
            </button>
          </div>
        </div>

        <div class="card-body">
          <div class="alert alert-info py-2 px-3 mb-3">
            <i class="fas fa-info-circle mr-1"></i>
            <strong>Informasi Migrasi:</strong>
            Data lama di tabel <code>dbo.pmlonchuan_hdr</code> & <code>dbo.pmlonchuan_dtl</code> <strong>TIDAK akan dihapus</strong>.
            Sistem mengambil nilai meter Awal & Akhir berdasarkan jam siklus (09:00 s/d 08:00), menghitung <code>Total = Akhir - Awal</code>, dan memindahkannya ke tabel <code>dbo.air_steam_20tlonchuan_harian</code>.
            Jika tanggal sudah ada di tabel baru, data tersebut otomatis <strong>dilewati (Skip)</strong> agar data yang sudah diinput tidak tertimpa.
          </div>

          <div class="table-responsive">
            <table class="table table-bordered table-sm table-hover text-center" style="font-size:13px;">
              <thead class="thead-light">
                <tr>
                  <th rowspan="2" class="align-middle" style="width:40px;">No</th>
                  <th rowspan="2" class="align-middle">Tanggal</th>
                  <th colspan="3" class="bg-primary text-white">METER AIR (ton)</th>
                  <th colspan="3" class="bg-info text-white">METER STEAM (ton)</th>
                  <th rowspan="2" class="align-middle">Note</th>
                  <th rowspan="2" class="align-middle">Created By</th>
                  <th rowspan="2" class="align-middle" style="width:140px;">Status</th>
                </tr>
                <tr>
                  <th>Awal</th><th>Akhir</th><th>Total</th>
                  <th>Awal</th><th>Akhir</th><th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($previewList)): ?>
                  <tr><td colspan="11">Tidak ada data di tabel lama.</td></tr>
                <?php else: $no = 1; foreach ($previewList as $row): ?>
                  <tr class="<?= $row['already_exists'] ? 'table-warning' : '' ?>">
                    <td><?= $no++ ?></td>
                    <td class="font-weight-bold"><?= htmlspecialchars(date('d-m-Y', strtotime($row['tanggal']))) ?></td>
                    <td><?= $fmt($row['air_awal']) ?></td>
                    <td><?= $fmt($row['air_akhir']) ?></td>
                    <td class="font-weight-bold text-primary"><?= $fmt($row['air_total']) ?></td>
                    <td>
                      <?= $fmt($row['steam_awal']) ?>
                      <?php if (!empty($row['is_typo_corrected'])): ?>
                        <br><span class="badge badge-success" title="Typo 174154 dikoreksi menjadi 17415.4">koreksi typo</span>
                      <?php endif; ?>
                    </td>
                    <td><?= $fmt($row['steam_akhir']) ?></td>
                    <td class="font-weight-bold text-info"><?= $fmt($row['steam_total']) ?></td>
                    <td class="text-left"><small><?= htmlspecialchars($row['note'] ?: '-') ?></small></td>
                    <td><small><?= htmlspecialchars($row['created_by'] ?: '-') ?></small></td>
                    <td>
                      <?php if ($row['already_exists']): ?>
                        <span class="badge badge-warning px-2 py-1"><i class="fas fa-check mr-1"></i> Sudah Ada (Skip)</span>
                      <?php else: ?>
                        <span class="badge badge-success px-2 py-1"><i class="fas fa-arrow-right mr-1"></i> Siap Migrasi</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>
<?php include($docRoot . '/gg_app/includes/footer.php'); ?>
</div>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
$(function(){
  $('#btnMulaiMigrasi').on('click', function(e){
    e.preventDefault();
    Swal.fire({
      title: 'Mulai Migrasi Data?',
      text: 'Sistem akan memindahkan data historis yang belum ada ke tabel baru dengan rumus Akhir - Awal.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#6c757d',
      confirmButtonText: '<i class="fas fa-play mr-1"></i> Ya, Jalankan Migrasi',
      cancelButtonText: 'Batal'
    }).then(function(result){
      if (!result.isConfirmed) return;

      Swal.fire({
        title: 'Sedang Memproses...',
        html: 'Mohon tunggu, sedang memindahkan data historis.',
        allowOutsideClick: false,
        didOpen: function(){ Swal.showLoading(); }
      });

      $.ajax({
        url: 'migrate_20tlonchuan.php',
        type: 'POST',
        data: { action: 'execute_migration' },
        dataType: 'json',
        success: function(resp){
          if (resp && resp.success) {
            Swal.fire({
              icon: 'success',
              title: 'Migrasi Selesai!',
              html: '<strong>' + resp.migrated_count + '</strong> tanggal berhasil dimigrasi.<br>' +
                    '<strong>' + resp.skipped_count + '</strong> tanggal dilewati (sudah ada).',
              confirmButtonText: 'OK'
            }).then(function(){
              location.reload();
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Gagal',
              text: (resp && resp.message) ? resp.message : 'Terjadi kesalahan saat migrasi.'
            });
          }
        },
        error: function(){
          Swal.fire({
            icon: 'error',
            title: 'Error Jaringan',
            text: 'Tidak dapat terhubung ke server.'
          });
        }
      });
    });
  });
});
</script>
