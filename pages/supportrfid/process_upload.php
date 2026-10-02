<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use MongoDB\Client;
use MongoDB\BSON\Int64;
use MongoDB\BSON\ObjectId;

ini_set('max_execution_time', 0); // 0 = tanpa batas waktu
set_time_limit(0); // alternatif
date_default_timezone_set('Asia/Jakarta'); // WIB

echo '<link rel="stylesheet" href="styles.css">';
if (session_status() === PHP_SESSION_NONE) session_start();

// 🔹 Koneksi MongoDB
$client = new Client("mongodb://doitAdmin:doitIntegra.sys@192.168.7.5:27017");
$db = $client->selectDatabase("api_sum");
$collection = $db->selectCollection("picking_process");

// 🔹 Mapping tipe data
$TYPE_MAPPING = [
  "rfidcode"=>"string","tid"=>"string","baleno"=>"string","packingno"=>"string",
  "batchno"=>"string","prdnmbr"=>"string","enddate"=>"string","lot"=>"string",
  "prddate"=>"string","prodcode"=>"string","prodid"=>"int","prodname"=>"string",
  "prodstructid"=>"int","prodtype"=>"string","resultseq"=>"int","registerqty"=>"double",
  "resultqty"=>"double","resultstdqty"=>"double","resultstduomcode"=>"string",
  "resultstduomid"=>"int","resultuomid"=>"int","resultuomcode"=>"string","startdate"=>"string",
  "transdtbatchid"=>"int","uomcode"=>"string","uomid"=>"int","wrhscode"=>"string",
  "wrhsid"=>"int","wrhsname"=>"string","wrhsrunid"=>"string","transferinitem"=>"string",
  "transferoutitem"=>"string","batchexpdate"=>"string","balehdid"=>"long",
  "upload_via"=>"string","upload_date"=>"string","status"=>"string","is_valid"=>"bool"
];

// 🔹 Fungsi konversi tipe data
function convertType($col,$val,$TYPE_MAPPING,$forImport=false){
  if ($val === null || $val === "") return null;
  $t = $TYPE_MAPPING[$col] ?? 'string';
  switch ($t){
    case 'int': return (int)$val;
    case 'double': return (float)$val;
    case 'bool': return in_array(strtolower($val), ['true','1','yes']);
    case 'long': return $forImport ? new Int64((string)$val) : $val;
    default: return (string)$val;
  }
}

// 🔹 Folder sementara
$tmpDir = __DIR__.'/tmp_uploads';
if (!is_dir($tmpDir)) mkdir($tmpDir,0755,true);

$action = $_POST['action'] ?? '';

/* ========================= PREVIEW ========================== */
if ($action === 'preview' && isset($_FILES['excelFile'])) {
  $file = $_FILES['excelFile'];
  if ($file['error'] === UPLOAD_ERR_OK) {
    $savePath = $tmpDir.'/'.uniqid('xls_').'_'.basename($file['name']);
    move_uploaded_file($file['tmp_name'],$savePath);
    $_SESSION['preview_file'] = $savePath;

    $sheet = IOFactory::load($savePath)->getActiveSheet()->toArray(null,true,true,true);
    $header = []; $rows = [];

    foreach ($sheet as $r=>$row) {
      if ($r==1) {
        foreach ($row as $k=>$v)
          $header[$k] = trim(strtolower($v)); // jaga underscore
      } else {
        $item = [];
        foreach ($row as $k=>$v) {
          $col = $header[$k] ?? null;
          if ($col) $item[$col] = convertType($col,trim($v),$TYPE_MAPPING);
        }
        if (!empty(array_filter($item))) $rows[] = $item;
      }
    }

    // 🔹 Validasi header template
    $expected = array_keys($TYPE_MAPPING);
    $uploadedCols = array_values(array_filter($header, fn($v) => $v !== ''));
    $missing = array_values(array_diff($expected, $uploadedCols));
    $extra   = array_values(array_diff($uploadedCols, $expected));

    if (!empty($missing) || !empty($extra)) {
      $msg = "❌ Format file tidak sesuai template!<br>";
      if (!empty($missing)) {
        $msg .= "<b>Hilang:</b> " . implode(', ', $missing) . "<br>";
      }
      if (!empty($extra)) {
        $msg .= "<b>Ada Kolom Tambahan:</b> " . implode(', ', $extra);
      }

      echo "<div class='bubble-error'>$msg</div>
            <script>
              setTimeout(()=>{document.querySelector('.bubble-error')?.remove();},5000);
            </script>";
      @unlink($savePath);
      unset($_SESSION['preview_file']);
      exit;
    }

    // 🔹 Tampilkan preview data
    echo "<div class='preview-container'>";
    foreach ($rows as $i=>$r) {
      echo "<div class='data-card'>";
      echo "<div class='data-card-title'>Data ".($i+1)."</div>";
      echo "<div class='data-card-body'>";
      foreach ($r as $k=>$v) {
        echo "<div class='data-item'>
                <span class='key'>".htmlspecialchars($k)."</span>
                <span class='val'>".htmlspecialchars($v===null?'-':$v)."</span>
              </div>";
      }
      echo "</div></div>";
    }
    echo "</div>";

    echo "
    <script>
      const popup = document.getElementById('popupSuccess');
      document.getElementById('confirmImport').addEventListener('click', async ()=>{
        const fd = new FormData();
        fd.append('action','import');
        const res = await fetch('process_upload.php',{method:'POST',body:fd});
        const txt = await res.text();
        if(txt.includes('Import selesai')) {
          popup.classList.add('show');
          setTimeout(()=>{ window.location='view_upload_data.php'; }, 2000);
        }
        document.querySelector('.preview-wrapper').innerHTML = txt;
      });
      document.getElementById('cancelPreview').addEventListener('click', ()=>{
        window.location='view_upload_data.php';
      });
    </script>";
    exit;
  }
}

/* ========================= CANCEL ========================== */
if ($action === 'cancel') {
  if (!empty($_SESSION['preview_file']) && file_exists($_SESSION['preview_file'])) {
    @unlink($_SESSION['preview_file']);
    unset($_SESSION['preview_file']);
  }
  echo "<p style='color:green;'>🗑️ Preview dibersihkan.</p>";
  exit;
}
/* ========================= IMPORT ========================== */
if ($action === 'import') {
  if (empty($_SESSION['preview_file']) || !file_exists($_SESSION['preview_file'])) {
    echo "<p style='color:red;'>❌ File preview tidak ditemukan.</p>";
    exit;
  }

  $file = $_SESSION['preview_file'];
  $sheet = IOFactory::load($file)->getActiveSheet()->toArray(null, true, true, true);
  $header = []; 
  $records = [];

  // 🔹 Baca file Excel
  foreach ($sheet as $r => $row) {
    if ($r == 1) {
      foreach ($row as $k => $v)
        $header[$k] = trim(strtolower($v));
    } else {
      $doc = [];
      foreach ($row as $k => $v) {
        $col = $header[$k] ?? null;
        if ($col) $doc[$col] = convertType($col, trim($v), $TYPE_MAPPING, true);
      }
      if (!empty(array_filter($doc))) $records[] = $doc;
    }
  }

  // 🔹 Pengecekan duplikat berdasarkan kolom unik
  $uniqueKeys = ['batchno']; // ubah sesuai kebutuhan Anda
  $newRecords = [];
  $duplicates = [];

  foreach ($records as $rec) {
    $query = [];
    foreach ($uniqueKeys as $key) {
      if (isset($rec[$key])) $query[$key] = $rec[$key];
    }

    if (empty($query)) continue;

    $exists = $collection->findOne($query);

    if ($exists) {
      $duplicates[] = $rec;
    } else {
      $newRecords[] = $rec;
    }
  }

  // 🔹 Insert hanya data baru (tidak duplikat)
  $count = 0;
  $insertedDocs = [];
  if (!empty($newRecords)) {
    $insertResult = $collection->insertMany($newRecords);
    $count = $insertResult->getInsertedCount();
    $insertedIds = $insertResult->getInsertedIds();

    foreach ($newRecords as $i => &$rec) {
      if (isset($insertedIds[$i])) {
        $rec['_id'] = ['$oid' => (string)$insertedIds[$i]];
      }
    }
    unset($rec);

    if ($count > 0) {
      $insertedDocs = array_slice($newRecords, 0, $count);
    }
  }

  // ================== LOG JSON ==================
  if (!empty($insertedDocs)) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);

    $logFile = $logDir . '/upload_logs_' . date('Y-m-d') . '.json';
    $existingLogs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) ?: [] : [];

    $existingLogs[] = [
      'timestamp' => date('Y-m-d H:i:s'),
      'user' => $_SESSION['username'] ?? 'anonymous',
      'count' => count($insertedDocs),
      'data' => $insertedDocs
    ];
    file_put_contents($logFile, json_encode($existingLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  }

  // 🧹 Hapus file sementara
  foreach (glob($tmpDir . '/*') as $tmpFile) if (is_file($tmpFile)) @unlink($tmpFile);
  unset($_SESSION['preview_file']);

  // === Tampilan hasil import (tetap dengan style asli) ===
  echo "
<style>
  /* === Notifikasi Gelembung === */
  .notif-bubble {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0.8);
    background: #4CAF50;
    color: white;
    padding: 16px 28px;
    border-radius: 30px;
    font-size: 18px;
    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    opacity: 0;
    z-index: 9999;
    animation: showBubble 2.2s ease forwards;
    text-align: center;
  }

  @keyframes showBubble {
    0% { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
    15% { opacity: 1; transform: translate(-50%, -50%) scale(1.05); }
    30% { transform: translate(-50%, -50%) scale(1); }
    80% { opacity: 1; }
    100% { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
  }

   /* === STYLE TABEL DUPLIKAT (versi modern) === */
  .dup-title {
    text-align: center;
    color: #c0392b;
    font-weight: 600;
    font-size: 18px;
    margin: 30px 0 10px 0;
    font-family: 'Segoe UI', Arial, sans-serif;
  }

  .dup-table {
    margin: 20px auto 40px auto;
    width: 85%;
    border-collapse: separate;
    border-spacing: 0;
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 14px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.08);
    border-radius: 10px;
    overflow: hidden;
    background: #ffffff;
  }

  .dup-table th {
    background: linear-gradient(135deg, #f44336, #e57373);
    color: #fff;
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #c62828;
  }

  .dup-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #f0f0f0;
    color: #333;
    background: #fff;
  }

  .dup-table tr:nth-child(even) td {
    background: #fafafa;
  }

  .dup-table tr:hover td {
    background: #fff5f5;
  }

  .dup-table tr:last-child td {
    border-bottom: none;
  }

</style>

<div class='notif-bubble'> ✅ Import selesai.<br>Jumlah dokumen baru: $count</div>
";

  // 🔹 Jika ada data duplikat, tampilkan daftar
  if (!empty($duplicates)) {
    echo "<div class='dup-title'>⚠️ Sistem Mendeteksi " . count($duplicates) . " Data Duplikasi — Hanya Data Baru Yang Berhasil Diimport</div>";
    echo "<table class='dup-table'>
            <tr><th>No</th>";
    foreach ($uniqueKeys as $key) echo "<th>" . htmlspecialchars($key) . "</th>";
    echo "</tr>";

    $no = 1;
    foreach ($duplicates as $dup) {
      echo "<tr><td>" . $no++ . "</td>";
      foreach ($uniqueKeys as $key) {
        echo "<td>" . htmlspecialchars($dup[$key] ?? '-') . "</td>";
      }
      echo "</tr>";
    }
    echo "</table>";
  }

  echo "
<script>
  setTimeout(() => {
    const notif = document.querySelector('.notif-bubble');
    if (notif) notif.remove();
    window.location = 'hak_akses_group.php';
  }, 2200);
</script>
";
  exit;
}

?>