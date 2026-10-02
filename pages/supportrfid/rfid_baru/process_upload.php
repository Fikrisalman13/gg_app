<?php
require_once __DIR__ . '/_shared.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use MongoDB\BSON\Int64;

if (!coba1_has_menu_access('upload')) {
    http_response_code(403);
    echo 'Anda tidak memiliki hak akses ke halaman ini.';
    exit;
}

date_default_timezone_set('Asia/Jakarta');

$typeMapping = [
    'rfidcode' => 'string', 'tid' => 'string', 'baleno' => 'string', 'packingno' => 'string',
    'batchno' => 'string', 'prdnmbr' => 'string', 'enddate' => 'string', 'lot' => 'string',
    'prddate' => 'string', 'prodcode' => 'string', 'prodid' => 'int', 'prodname' => 'string',
    'prodstructid' => 'int', 'prodtype' => 'string', 'resultseq' => 'int', 'registerqty' => 'double',
    'resultqty' => 'double', 'resultstdqty' => 'double', 'resultstduomcode' => 'string',
    'resultstduomid' => 'int', 'resultuomid' => 'int', 'resultuomcode' => 'string', 'startdate' => 'string',
    'transdtbatchid' => 'int', 'uomcode' => 'string', 'uomid' => 'int', 'wrhscode' => 'string',
    'wrhsid' => 'int', 'wrhsname' => 'string', 'wrhsrunid' => 'string', 'transferinitem' => 'string',
    'transferoutitem' => 'string', 'batchexpdate' => 'string', 'balehdid' => 'long',
    'upload_via' => 'string', 'upload_date' => 'string', 'status' => 'string', 'is_valid' => 'bool'
];

$collection = coba1_mongo_collection('picking_process');
$tmpDir = __DIR__ . '/tmp_uploads';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0755, true);
}

$convert = static function (string $column, $value, bool $forImport = false) use ($typeMapping) {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $type = $typeMapping[$column] ?? 'string';
    return match ($type) {
        'int' => (int) $value,
        'double' => (float) $value,
        'bool' => in_array(strtolower($value), ['true', '1', 'yes'], true),
        'long' => $forImport ? new Int64($value) : $value,
        default => (string) $value,
    };
};

$status = null;
$previewRows = [];
$previewFile = $_SESSION['coba1_preview_file'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'preview' && isset($_FILES['excelFile'])) {
        $file = $_FILES['excelFile'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $savePath = $tmpDir . '/' . uniqid('xls_', true) . '_' . basename((string) $file['name']);
            move_uploaded_file($file['tmp_name'], $savePath);
            $_SESSION['coba1_preview_file'] = $savePath;
            $previewFile = $savePath;

            $sheet = IOFactory::load($savePath)->getActiveSheet()->toArray(null, true, true, true);
            $header = [];
            $rows = [];
            foreach ($sheet as $rowIndex => $row) {
                if ($rowIndex === 1) {
                    foreach ($row as $column => $value) {
                        $header[$column] = trim(strtolower((string) $value));
                    }
                    continue;
                }
                $item = [];
                foreach ($row as $column => $value) {
                    $field = $header[$column] ?? null;
                    if ($field) {
                        $item[$field] = $convert($field, $value, false);
                    }
                }
                if (array_filter($item, static fn ($value) => $value !== null && $value !== '')) {
                    $rows[] = $item;
                }
            }

            $expected = array_keys($typeMapping);
            $uploaded = array_values(array_filter($header));
            $missing = array_values(array_diff($expected, $uploaded));
            $extra = array_values(array_diff($uploaded, $expected));

            if ($missing || $extra) {
                $status = ['type' => 'danger', 'message' => 'Format file tidak sesuai template.'];
                if ($previewFile && file_exists($previewFile)) {
                    @unlink($previewFile);
                }
                unset($_SESSION['coba1_preview_file']);
                $previewFile = null;
            } else {
                $previewRows = $rows;
            }
        } else {
            $status = ['type' => 'danger', 'message' => 'Upload gagal.'];
        }
    }

    if ($action === 'import') {
        if (!$previewFile || !file_exists($previewFile)) {
            $status = ['type' => 'danger', 'message' => 'File preview tidak ditemukan.'];
        } else {
            $sheet = IOFactory::load($previewFile)->getActiveSheet()->toArray(null, true, true, true);
            $header = [];
            $records = [];
            foreach ($sheet as $rowIndex => $row) {
                if ($rowIndex === 1) {
                    foreach ($row as $column => $value) {
                        $header[$column] = trim(strtolower((string) $value));
                    }
                    continue;
                }
                $doc = [];
                foreach ($row as $column => $value) {
                    $field = $header[$column] ?? null;
                    if ($field) {
                        $doc[$field] = $convert($field, $value, true);
                    }
                }
                if (array_filter($doc, static fn ($value) => $value !== null && $value !== '')) {
                    $records[] = $doc;
                }
            }

            $newRecords = [];
            $duplicates = [];
            foreach ($records as $record) {
                $query = [];
                if (isset($record['batchno'])) {
                    $query['batchno'] = $record['batchno'];
                }
                if ($query === []) {
                    continue;
                }
                if ($collection->findOne($query)) {
                    $duplicates[] = $record;
                } else {
                    $record['upload_via'] = 'coba1';
                    $record['upload_date'] = date('Y-m-d H:i:s');
                    $newRecords[] = $record;
                }
            }

            $insertedDocs = [];
            if ($newRecords) {
                $insertResult = $collection->insertMany($newRecords);
                $insertedIds = $insertResult->getInsertedIds();
                foreach ($newRecords as $index => $record) {
                    if (isset($insertedIds[$index])) {
                        $record['_id'] = ['$oid' => (string) $insertedIds[$index]];
                    }
                    $insertedDocs[] = $record;
                }
            }

            if ($insertedDocs) {
                $logDir = __DIR__ . '/../logs';
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                $logFile = $logDir . '/upload_logs_' . date('Y-m-d') . '.json';
                $existingLogs = file_exists($logFile) ? json_decode((string) file_get_contents($logFile), true) : [];
                $existingLogs = is_array($existingLogs) ? $existingLogs : [];
                $existingLogs[] = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'user' => coba1_support_username() ?: 'anonymous',
                    'count' => count($insertedDocs),
                    'data' => $insertedDocs,
                ];
                file_put_contents($logFile, json_encode($existingLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            foreach (glob($tmpDir . '/*') as $tmpFile) {
                if (is_file($tmpFile)) {
                    @unlink($tmpFile);
                }
            }
            unset($_SESSION['coba1_preview_file']);
            $previewFile = null;
            $status = ['type' => 'success', 'message' => 'Import selesai. Dokumen baru: ' . count($insertedDocs) . '. Duplikat: ' . count($duplicates) . '.'];
        }
    }
}

coba1_layout_start('Upload Data');
?>
<style>
.c1-card{background:#fff;border:1px solid #dce4f0;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,.08);padding:16px;margin-bottom:16px}
.c1-btn{padding:10px 14px;border:none;border-radius:8px;background:#1976d2;color:#fff;cursor:pointer}
.c1-input{width:100%;padding:10px;border:1px solid #cfd8dc;border-radius:8px}
.c1-alert{padding:12px;border-radius:8px;margin-bottom:12px}
.c1-alert.success{background:#d4edda;color:#155724}
.c1-alert.danger{background:#f8d7da;color:#721c24}
.c1-table{width:100%;border-collapse:collapse;font-size:13px}
.c1-table td,.c1-table th{border:1px solid #e7edf5;padding:8px;vertical-align:top}
</style>

<?php if ($status): ?>
  <div class="c1-alert <?= htmlspecialchars($status['type']) ?>"><?= htmlspecialchars($status['message']) ?></div>
<?php endif; ?>

<div class="c1-card">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h4 class="mb-1">Upload Excel</h4>
      <div class="text-muted">Template harus sesuai kolom source.</div>
    </div>
    <a class="btn btn-sm btn-outline-success" href="../template_import.xlsx">Download Template</a>
  </div>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="preview">
    <input type="file" name="excelFile" class="c1-input" accept=".xls,.xlsx" required>
    <button type="submit" class="c1-btn mt-3">Preview</button>
  </form>
</div>

<?php if ($previewRows): ?>
  <div class="c1-card">
    <h5>Preview</h5>
    <div class="table-responsive">
      <table class="c1-table">
        <thead><tr><th>No</th><?php foreach (array_keys($previewRows[0]) as $column): ?><th><?= htmlspecialchars($column) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach ($previewRows as $index => $row): ?>
            <tr>
              <td><?= $index + 1 ?></td>
              <?php foreach ($row as $value): ?><td><?= htmlspecialchars(is_scalar($value) || $value === null ? (string) $value : json_encode($value)) ?></td><?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <form method="post" class="mt-3 d-inline-block">
      <input type="hidden" name="action" value="import">
      <button type="submit" class="c1-btn">Import</button>
    </form>
    <a href="process_upload.php" class="btn btn-outline-secondary mt-3 ml-2">Batal</a>
  </div>
<?php endif; ?>

<?php coba1_layout_end(); ?>
