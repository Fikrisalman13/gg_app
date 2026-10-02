<?php
/**
 * Simpan tanda tangan "Yang Menyerahkan" untuk HT.
 * Path simpan:
 *   /gg_app/pages/si-iin/ht/esign/menyerahkan/SAFE_NO_GRN__SAFE_NAMA.png
 *
 * Input (POST):
 *   id             : id HT
 *   sign_name      : nama penanda tangan (diisi otomatis di modal, readonly)
 *   sign_image_data: dataURL base64 image/png
 */
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../_shared/siin_lib.php'; // $pdo = SIIN

// (Opsional) koneksi HR / MSSQL (SMUserMS & m_emp) → untuk ambil nama_lengkap dari UserName
$pdo_hr  = null;
$HRCONN  = null;

$KON = realpath(__DIR__ . '/../../../koneksi.php');
if ($KON) {
    require_once $KON;
    if (isset($pdo_mssql) && $pdo_mssql instanceof PDO) {
        $pdo_hr = $pdo_mssql;
    } elseif (isset($pdo) && $pdo instanceof PDO && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlsrv') {
        $pdo_hr = $pdo;
    }
    if (isset($conn)) {
        $HRCONN = $conn;
    } elseif (isset($sqlsrvConn)) {
        $HRCONN = $sqlsrvConn;
    }
}

header('Content-Type: application/json; charset=utf-8');

/**
 * Helper: ambil EmpId dari SMUserMS berdasarkan UserName
 */
function getEmpIdFromUserName(string $userName, $pdo_hr = null, $HRCONN = null): ?int {
    $userName = trim($userName);
    if ($userName === '') return null;

    if ($pdo_hr instanceof PDO && $pdo_hr->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlsrv') {
        $st = $pdo_hr->prepare("SELECT TOP 1 EmpId FROM SMUserMS WHERE UserName = ?");
        if ($st && $st->execute([$userName])) {
            $val = $st->fetchColumn();
            if ($val !== false && $val !== null) return (int)$val;
        }
    }

    if ($HRCONN) {
        $sql  = "SELECT TOP 1 EmpId FROM SMUserMS WHERE UserName = ?";
        $stmt = sqlsrv_query($HRCONN, $sql, [$userName]);
        if ($stmt !== false) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);
            if ($row && isset($row['EmpId'])) {
                return (int)$row['EmpId'];
            }
        }
    }

    return null;
}

/**
 * Helper: dari UserName → nama_lengkap (m_emp)
 */
function getFullNameFromUserName(string $userName, $pdo_hr = null, $HRCONN = null): ?string {
    $empId = getEmpIdFromUserName($userName, $pdo_hr, $HRCONN);
    if (!$empId) return null;

    if ($pdo_hr instanceof PDO && $pdo_hr->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlsrv') {
        $st = $pdo_hr->prepare("SELECT TOP 1 nama_lengkap FROM m_emp WHERE id_emp = ?");
        if ($st && $st->execute([$empId])) {
            $nm = $st->fetchColumn();
            if ($nm) return (string)$nm;
        }
    }

    if ($HRCONN) {
        $sql  = "SELECT TOP 1 nama_lengkap FROM m_emp WHERE id_emp = ?";
        $stmt = sqlsrv_query($HRCONN, $sql, [$empId]);
        if ($stmt !== false) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);
            if ($row && isset($row['nama_lengkap'])) {
                return (string)$row['nama_lengkap'];
            }
        }
    }

    return null;
}

// Pastikan hanya IT1..IT10 (+ ITADM kalau mau) yang boleh akses
$adminUserNames = ['GG','IT1','IT2','IT3','IT4','IT5','IT6','IT7','IT8','IT9','IT10','ITADM'];
$currentUser    = isset($_SESSION['UserName']) ? strtoupper((string)$_SESSION['UserName']) : '';

if (!in_array($currentUser, $adminUserNames, true)) {
  echo json_encode([
    'success' => false,
    'message' => 'Anda tidak memiliki hak untuk E-Sign Menyerahkan.'
  ]);
  exit;
}

try {
  if (!isset($_SESSION['UserName'])) {
    throw new Exception('Sesi habis. Silakan login.');
  }

  $id   = (int)($_POST['id'] ?? 0);
  // PERBAIKAN: ambil dari sign_name (bukan sender_name)
  $name = trim($_POST['sign_name'] ?? '');
  $data = $_POST['sign_image_data'] ?? '';

  if ($id <= 0)      throw new Exception('ID HT tidak valid.');
  if ($data === '')  throw new Exception('Data tanda tangan kosong.');

  // Ambil header HT untuk dapat no_grn
  $stH = $pdo->prepare("SELECT no_grn FROM siin_ht WHERE id = ?");
  $stH->execute([$id]);
  $H = $stH->fetch(PDO::FETCH_ASSOC);
  if (!$H) throw new Exception('Data HT tidak ditemukan.');

  $no_grn_raw = (string)($H['no_grn'] ?? '');
  if ($no_grn_raw === '') throw new Exception('No GRN belum terisi di HT.');

  // nama tampil:
  // - dari form (sign_name, isi nama_lengkap)
  // - kalau kosong → fallback ke nama_lengkap dari HR
  if ($name === '') {
      $fullName = getFullNameFromUserName($_SESSION['UserName'], $pdo_hr, $HRCONN);
      if ($fullName) {
          $name = $fullName;
      } else {
          $name = $currentUser; // fallback terakhir: username
      }
  }

  // siapkan folder
  $saveDir = __DIR__ . '/esign/menyerahkan';
  if (!is_dir($saveDir)) {
    if (!mkdir($saveDir, 0775, true) && !is_dir($saveDir)) {
      throw new Exception('Gagal membuat folder: '.$saveDir);
    }
  }

  // sanitize untuk nama file
  $safeNoGrn  = preg_replace('/[^A-Za-z0-9_\-]/', '_', $no_grn_raw);
  $safeName   = preg_replace('/[^A-Za-z0-9_\-]/', '_', strtoupper($name));
  $fileBase   = $safeNoGrn . '__' . $safeName;
  $filePath   = $saveDir . DIRECTORY_SEPARATOR . $fileBase . '.png';

  // hapus file lama utk no_grn ini (kalau ada)
  foreach (glob($saveDir . DIRECTORY_SEPARATOR . $safeNoGrn . '__*.png') as $old) {
    @unlink($old);
  }

  // decode dataURL
  if (preg_match('#^data:image/png;base64,#', $data)) {
    $data = substr($data, strlen('data:image/png;base64,'));
  }
  $bin = base64_decode($data);
  if ($bin === false) {
    throw new Exception('Gagal decode data tanda tangan.');
  }

  if (file_put_contents($filePath, $bin) === false) {
    throw new Exception('Gagal menyimpan file tanda tangan.');
  }

  echo json_encode([
    'success'   => true,
    'message'   => 'Tanda tangan Menyerahkan tersimpan.',
    'file_path' => $filePath
  ]);

} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
}
