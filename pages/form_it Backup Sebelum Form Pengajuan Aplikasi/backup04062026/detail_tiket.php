<?php
ob_start();
session_start();
require_once '../../koneksi.php';

// Ambil ticket dari URL
$ticket = $_GET['ticket'] ?? '';

// Query data tiket
$sql = "SELECT * FROM Form_Pengajuan_Barang WHERE ticket = ?";
$stmt = sqlsrv_query($conn, $sql, [$ticket]);
if (!$stmt || sqlsrv_has_rows($stmt) === false) {
    die("Tiket tidak ditemukan.");
}

$data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Hak tanda tangan per role berdasarkan Role TTD yang dimiliki user login
$canSignPemohon       = false;
$canSignAtasanPemohon = false;
$canSignPetugasIT     = false;
$canSignKabagIT       = false;
$canSignKadeptIT      = false;

if (isset($_SESSION['UserId'])) {
    $sqlRole = "SELECT DISTINCT GroupRole
                FROM User_TTD_Template
                WHERE UserId = ? AND IsActive = 1";
    $stmtRole = sqlsrv_query($conn, $sqlRole, [$_SESSION['UserId']]);
    if ($stmtRole) {
        while ($rowR = sqlsrv_fetch_array($stmtRole, SQLSRV_FETCH_ASSOC)) {
            $role = trim($rowR['GroupRole'] ?? '');
            if (strcasecmp($role, 'Pemohon') === 0) {
                $canSignPemohon = true;
            } elseif (strcasecmp($role, 'Atasan Pemohon') === 0) {
                $canSignAtasanPemohon = true;
            } elseif (strcasecmp($role, 'Petugas IT') === 0) {
                $canSignPetugasIT = true;
            } elseif (strcasecmp($role, 'Kabag IT') === 0) {
                $canSignKabagIT = true;
            } elseif (strcasecmp($role, 'Kadept IT') === 0) {
                $canSignKadeptIT = true;
            }
        }
    }
}

$sqlTtd = "SELECT GroupRole, SignaturePath, SignedByUserName, SignedByUserId FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ?";
$stmtTtd = sqlsrv_query($conn, $sqlTtd, [$ticket]);
$ttd = [];
if ($stmtTtd && sqlsrv_has_rows($stmtTtd)) {
    while ($rowTtd = sqlsrv_fetch_array($stmtTtd, SQLSRV_FETCH_ASSOC)) {
        $role = $rowTtd['GroupRole'];
        $ttd[$role] = [
            'SignaturePath' => $rowTtd['SignaturePath'],
            'SignedByUserName' => $rowTtd['SignedByUserName'],
            'SignedByUserId' => $rowTtd['SignedByUserId']
        ];
    }
}

// Helper function untuk cek apakah TTD sudah ada dan ditandatangani oleh user login
function isTtdByCurrentUser($ttdArray, $currentUserId) {
    if (!isset($ttdArray['SignaturePath']) || empty($ttdArray['SignaturePath'])) {
        return false; // TTD belum ada
    }
    if (!isset($ttdArray['SignedByUserId'])) {
        return false;
    }
    return $ttdArray['SignedByUserId'] == $currentUserId;
}

// Helper function untuk cek apakah TTD sudah ada (oleh siapa pun)
function isTtdExists($ttdArray) {
    return isset($ttdArray['SignaturePath']) && !empty($ttdArray['SignaturePath']);
}

/* ============================================
   FIX PENTING — Pastikan JSON decode menjadi array
============================================ */
$pengajuan = json_decode($data['pengajuan'] ?? '[]', true);
if (!is_array($pengajuan)) $pengajuan = [];

$peripheral = json_decode($data['peripheral'] ?? '[]', true);
if (!is_array($peripheral)) $peripheral = [];

/* ==========================================================
   Fungsi tampilan garis bawah
========================================================== */
function tampilkan($text, $len = 40){
    $text = trim($text ?? "");
    return $text === "" 
        ? "<span style='display:inline-block;border-bottom:1px solid #000;width:{$len}ch;'>&nbsp;</span>"
        : htmlspecialchars($text);
}

/* ==========================================================
   FIX PENTING — SAFE CHECKBOX GROUP
   • $selected dijamin array
========================================================== */
function renderChecklistGroup($items, $selected, $data, $prefix){

    if (!is_array($selected)) {
        $selected = [];
    }

    $html = "";

    foreach ($items as $item) {

        $key = $prefix . strtolower(str_replace(" ", "_", $item));
        $qty = intval($data[$key] ?? 0);

        // Centang otomatis jika qty > 0
        $checked = ($qty > 0) || in_array($item, $selected);

        // Kotak + Tanda cek
        if ($checked) {
            $box = "<span style='display:inline-block;width:12px;height:12px;border:1px solid #000;
                    margin-right:4px;font-size:10px;text-align:center;line-height:12px;'>✔</span>";
        } else {
            $box = "<span style='display:inline-block;width:12px;height:12px;border:1px solid #000;
                    margin-right:4px;'></span>";
        }
 // Cek jika "Lainnya" dan ada keterangan
        $ket = "";
        if (strtolower($item) === "lainnya") {
            $ketKey = ($prefix === "qty_perip_") ? "ket_perip_lainnya" : "ket_lainnya";
            if (!empty($data[$ketKey])) {
                $ket = $data[$ketKey] . " "; // tambahkan spasi agar sebelum Qty
            }
        }
        // Tampilkan Qty hanya jika diceklis
        $qtyText = ($checked && $qty > 0) ? " ( <b>{$ket}{$qty} Unit</b> )" : "";
        $html .= $box . $item . $qtyText . "&nbsp;&nbsp;&nbsp; ";
    }

    return $html;
}
/* ==========================================================
   HITUNG TOTAL QTY AKURAT (berbasis item nyata)
========================================================== */

// List item actual yang digunakan
$itemsPengajuan = ['Komputer','Laptop','Tablet','Handphone','Lainnya'];
$itemsPeripheral = ['Keyboard','Mouse','Monitor','Printer','Scanner','Lainnya'];

function totalQtyByItems($items, $data, $prefix) {
    $total = 0;

    foreach ($items as $item) {
        $key = $prefix . strtolower(str_replace(" ", "_", $item));
        $total += intval($data[$key] ?? 0);
    }

    return $total;
}

$totalPengajuan = totalQtyByItems($itemsPengajuan, $data, "qty_");
$totalPeripheral = totalQtyByItems($itemsPeripheral, $data, "qty_perip_");

$totalQty = $totalPengajuan + $totalPeripheral;

// Flag apakah tiket sudah ditolak
$isRejected = isset($data['status_ticket']) && strtolower(trim($data['status_ticket'])) === 'ditolak';


?>
<!DOCTYPE html>
<html>
<head>
<title>Detail Tiket <?= $data['ticket'] ?></title>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">

<style>
  body { background:#f4f6f9; }
  .form-box { background:#fff; padding:20px; border:1px solid #ccc; }
  .label { width:25%; font-weight:bold; }
  table { border-collapse: collapse; }
  td { padding:5px 8px; vertical-align: top; }
  /* Border hanya untuk tabel dengan attribute border */
  table[border="1"] { border: 1px solid #000; }
  table[border="1"] td { border: 1px solid #000; }

  /* Mobile Responsive Styles - Horizontal Scroll */
  @media (max-width: 768px) {
      .form-wrapper {
          overflow-x: auto;
          -webkit-overflow-scrolling: touch;
          width: 100%;
          padding-bottom: 10px; /* Space for scrollbar */
      }
      
      /* Force table to keep its minimum width to preserve PC layout */
      .outer-form {
          min-width: 800px; 
      }
      
      /* Header logo adjustment */
      img[alt="logo"] {
          width: 50px !important;
      }
      
      /* Adjust font sizes slightly but keep layout */
      body { font-size: 14px; }
  }
</style>

<script>
// Notify parent window about user's permissions
window.addEventListener('load', function() {
    var canReject = <?php echo ($canSignKabagIT || $canSignKadeptIT) && (strtolower(trim($data['status_ticket'] ?? '')) !== 'ditolak') ? 'true' : 'false'; ?>;
    var ticket = '<?= htmlspecialchars($ticket) ?>';
    
    if (window.parent && window.parent.updateRejectButtonVisibility) {
        window.parent.updateRejectButtonVisibility(canReject, ticket);
    }
});
</script>
</head>

<body>
<div class="container mt-3">

<div class="card">


<div class="card-body form-box">

<style>
.rejection-box {
    border-left: 5px solid #dc3545;
    background-color: #fff5f5;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    margin-bottom: 25px;
    position: relative;
    overflow: hidden;
}
.rejection-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, transparent 48%, rgba(220, 53, 69, 0.03) 50%, transparent 52%);
    background-size: 20px 20px;
    pointer-events: none;
}
.rejection-header {
    color: #dc3545;
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    border-bottom: 1px solid rgba(220, 53, 69, 0.2);
    padding-bottom: 10px;
}
.rejection-header i {
    margin-right: 12px;
    font-size: 1.5rem;
}
.rejection-content {
    color: #333;
    font-size: 0.95rem;
}
.rejection-content strong {
    color: #555;
    min-width: 140px;
    display: inline-block;
}
.rejection-reason {
    background: #ffecec; /* Darker red tint */
    padding: 12px 15px;
    border-radius: 6px;
    border: 1px solid #f5c6cb;
    margin-top: 8px;
    font-style: italic;
    color: #721c24; /* Darker text color */
    font-weight: 500;
}
</style>

<!-- STATUS PENOLAKAN (jika ada) -->
<?php if (isset($data['status_ticket']) && strtolower(trim($data['status_ticket'])) === 'ditolak'): ?>
<div class="rejection-box">
    <div class="rejection-header">
        <i class="fas fa-times-circle"></i> PENGAJUAN DITOLAK
    </div>
    <div class="rejection-content">
        <div class="mb-2">
            <strong>Ditolak oleh</strong>: 
            <?php 
            if (!empty($data['rejected_by'])) {
                $rejectedUserId = (int)$data['rejected_by'];
                $fullName = null;
                $userNameFromTTD = null;

                // 1. Coba ambil dari SMUserMs join m_emp
                $sqlUser = "SELECT e.nama_lengkap, u.UserName 
                            FROM SMUserMs u 
                            LEFT JOIN m_emp e ON u.EmpId = e.id_emp 
                            WHERE u.UserId = ?";
                $stmtUser = @sqlsrv_query($GLOBALS['conn'], $sqlUser, [$rejectedUserId]);
                if ($stmtUser && ($rowUser = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC))) {
                    $fullName = $rowUser['nama_lengkap'] ?? $rowUser['UserName'];
                }

                // 2. Jika belum dapat, ambil SignedByUserName dari TTD
                if ($fullName === null) {
                  $sqlTTDName = "SELECT TOP 1 SignedByUserName FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ? AND SignedByUserId = ?";
                  $stmtTTD = @sqlsrv_query($GLOBALS['conn'], $sqlTTDName, [$data['ticket'], $rejectedUserId]);
                  if ($stmtTTD && ($rowTTD = sqlsrv_fetch_array($stmtTTD, SQLSRV_FETCH_ASSOC))) {
                    $userNameFromTTD = $rowTTD['SignedByUserName'] ?? null;
                  }
                  if ($userNameFromTTD) {
                     $fullName = $userNameFromTTD;
                  }
                }

                // 3. Jika tetap belum dapat dan penolak adalah user yang sedang login, pakai session
                if ($fullName === null && isset($_SESSION['UserId']) && $_SESSION['UserId'] == $rejectedUserId) {
                  $fullName = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'];
                }

                // 4. Tampilkan hasil
                if ($fullName) {
                  echo htmlspecialchars($fullName);
                } else {
                  echo 'UserId: ' . htmlspecialchars($rejectedUserId);
                }
            } else {
                echo 'N/A';
            }
            ?>
        </div>
        <div class="mb-2">
            <strong>Tanggal Penolakan</strong>: 
            <?= isset($data['rejection_date']) && $data['rejection_date'] instanceof DateTime 
                ? $data['rejection_date']->format('d-m-Y H:i') 
                : $data['rejection_date'] ?? '-' 
            ?>
        </div>
        <div>
            <strong>Alasan Penolakan</strong>:
            <div class="rejection-reason">
                <?= nl2br(htmlspecialchars($data['rejection_reason'] ?? '-')) ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="form-wrapper" style="max-width:900px;margin:10px auto;">
<table class="outer-form" style="width:100%;border:1px solid #000;border-collapse:collapse;">
  <tr>
    <td style="width:100%;padding:0;">
      <!-- Header Table -->
      <table style="width:100%;border-collapse:collapse;">
        <tr>
          <td style="width:90px;border-right:1px solid #000;padding:8px 6px;vertical-align:top;text-align:center;">
            <img src="../../dist/img/sumlogo.png" width="70" alt="logo" style="display:block;margin:0 auto;">
          </td>
          <td style="padding:6px 8px;vertical-align:top;">
            <b style="font-size:16px;">PT. SURYA USAHA MANDIRI</b><br>
            Jl. Tarajusari No. 8 Kp. Cipeundeuy RT 001 RW 007<br>
            Banjaran – Kab. Bandung<br>
            40377 Telp. (022) 594-0313
          </td>
        </tr>
        <tr>
          <td colspan="2" style="border-top:1px solid #000;border-bottom:1px solid #000;text-align:center;font-weight:bold;padding:6px 4px;">PENGAJUAN PERANGKAT IT</td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td style="padding:0;">
      <!-- Form Data Table -->
      <table style="width:100%;border-collapse:collapse;">
        <tr>
          <td style="width:150px;padding:4px 6px;">Nama Pemohon</td>
          <td colspan="3" style="padding:4px 6px;">: <?= tampilkan($data['nama_pemohon']) ?></td>
        </tr>
        <tr>
          <td style="width:150px;padding:4px 6px;">Jabatan</td>
          <td style="padding:4px 6px 4px 6px;padding-right:250px;">: <?= tampilkan($data['jabatan']) ?></td>
          <td style="width:140px;padding:4px 6px;text-align:right;">Tgl Pengajuan</td>
          <td style="padding:4px 6px;text-align:right;">: <?= $data['tgl_pengajuan'] instanceof DateTime ? $data['tgl_pengajuan']->format('d-m-Y') : "" ?></td>
        </tr>
        <tr>
          <td style="width:150px;padding:4px 6px;">Departemen</td>
          <td colspan="3" style="padding:4px 6px;">: <?= tampilkan($data['departemen']) ?></td>
        </tr>
        <tr>
          <td style="width:150px;padding:4px 6px;">Bagian</td>
          <td colspan="3" style="padding:4px 6px;">: <?= tampilkan($data['bagian']) ?></td>
        </tr>
        <tr>
          <td style="width:150px;padding:4px 6px;">Pengajuan</td>
          <td colspan="3" style="padding:4px 6px;">: <?= renderChecklistGroup(
                  ['Komputer','Laptop','Tablet','Handphone','Lainnya'],
                  $pengajuan,
                  $data,
                  "qty_"
              ) ?>
          </td>
        </tr>
        <tr>
          <td style="width:150px;padding:4px 6px;">Spesifikasi Khusus</td>
          <td colspan="3" style="padding:4px 6px;">: <?= tampilkan($data['spesifikasi'],70) ?></td>
        </tr>
        <tr>
          <td style="width:150px;padding:4px 6px;">Peripheral</td>
          <td colspan="3" style="padding:4px 6px;">: <?= renderChecklistGroup(
                  ['Keyboard','Mouse','Monitor','Printer','Scanner','Lainnya'],
                  $peripheral,
                  $data,
                  "qty_perip_"
              ) ?>
          </td>
        </tr>
        <tr>
          <td style="width:150px;padding:4px 6px;">Total Qty</td>
          <td colspan="3" style="padding:4px 6px;">: <b><?= $totalQty ?> Unit</b></td>
        </tr>
        <tr>
          <td style="width:150px;padding:4px 6px;vertical-align:top;">Keterangan</td>
          <td colspan="3" style="padding:4px 6px;vertical-align:top;">
            <div style="display:flex; align-items:flex-start;">
              <div style="margin-right:5px;">:</div>
              <div><?= tampilkan($data['keterangan'],70) ?></div>
            </div>
            <br><br>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td style="padding:6px;">
      <!-- Signature Table -->
      <div class="signature-wrapper">
      <table class="signature-table" style="width:100%;border-collapse:collapse;table-layout:fixed;" border="1">
        <colgroup>
            <col style="width:20%;">
            <col style="width:20%;">
            <col style="width:20%;">
            <col style="width:20%;">
            <col style="width:20%;">
        </colgroup>
        <tr style="text-align:center;font-size:14px;font-weight:bold;">
          <td style="padding:4px;">Di Ajukan Oleh</td>
          <td style="padding:4px;">Mengetahui</td>
          <td style="padding:4px;">Di Ketahui Oleh</td>
          <td style="padding:4px;" colspan="2">Di Setujui Oleh</td>
        </tr>
        <tr style="height:auto;min-height:120px;text-align:center;vertical-align:middle;">
          <td style="padding:6px;vertical-align:middle;height:120px;">
            <div id="ttd-area-pemohon">
              <?php if (isset($ttd['Pemohon'])): ?><img src="<?= htmlspecialchars($ttd['Pemohon']['SignaturePath']) ?>" height="60" style="max-width:100%;object-fit:contain;" alt="TTD Pemohon"><?php endif; ?>
            </div>
            <?php if ($canSignPemohon && !$isRejected): ?>
              <?php if (isTtdByCurrentUser($ttd['Pemohon'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="pemohon">Hapus Tanda Tangan</button>
              <?php elseif (isTtdExists($ttd['Pemohon'] ?? [])): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
              <?php else: ?>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="pemohon">Tanda Tangan</button>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td style="padding:6px;vertical-align:middle;height:120px;">
            <div id="ttd-area-atasan">
              <?php if (isset($ttd['Atasan Pemohon'])): ?><img src="<?= htmlspecialchars($ttd['Atasan Pemohon']['SignaturePath']) ?>" height="60" style="max-width:100%;object-fit:contain;" alt="TTD Atasan Pemohon"><?php endif; ?>
            </div>
            <?php if ($canSignAtasanPemohon && !$isRejected): ?>
              <?php if (isTtdByCurrentUser($ttd['Atasan Pemohon'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="atasan_pemohon">Hapus Tanda Tangan</button>
              <?php elseif (isTtdExists($ttd['Atasan Pemohon'] ?? [])): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
              <?php else: ?>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="atasan_pemohon">Tanda Tangan</button>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td style="padding:6px;vertical-align:middle;height:120px;">
            <div id="ttd-area-petugas">
              <?php if (isset($ttd['Petugas IT'])): ?><img src="<?= htmlspecialchars($ttd['Petugas IT']['SignaturePath']) ?>" height="60" style="max-width:100%;object-fit:contain;" alt="TTD Petugas IT"><?php endif; ?>
            </div>
            <?php if ($canSignPetugasIT && !$isRejected): ?>
              <?php if (isTtdByCurrentUser($ttd['Petugas IT'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="petugas_it">Hapus Tanda Tangan</button>
              <?php elseif (isTtdExists($ttd['Petugas IT'] ?? [])): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
              <?php else: ?>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="petugas_it">Tanda Tangan</button>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td style="padding:6px;vertical-align:middle;height:120px;">
            <div id="ttd-area-kabag">
              <?php if (isset($ttd['Kabag IT'])): ?><img src="<?= htmlspecialchars($ttd['Kabag IT']['SignaturePath']) ?>" height="60" style="max-width:100%;object-fit:contain;" alt="TTD Kabag IT"><?php endif; ?>
            </div>
            <?php if ($canSignKabagIT && !$isRejected): ?>
              <?php if (isTtdByCurrentUser($ttd['Kabag IT'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="kabag_it">Hapus Tanda Tangan</button>
              <?php elseif (isTtdExists($ttd['Kabag IT'] ?? [])): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
              <?php else: ?>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="kabag_it">Tanda Tangan</button>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td style="padding:6px;vertical-align:middle;height:120px;">
            <div id="ttd-area-kadept">
              <?php if (isset($ttd['Kadept IT'])): ?><img src="<?= htmlspecialchars($ttd['Kadept IT']['SignaturePath']) ?>" height="60" style="max-width:100%;object-fit:contain;" alt="TTD Kadept IT"><?php endif; ?>
            </div>
            <?php if ($canSignKadeptIT && !$isRejected): ?>
              <?php if (isTtdByCurrentUser($ttd['Kadept IT'] ?? [], $_SESSION['UserId'] ?? 0)): ?>
                <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="kadept_it">Hapus Tanda Tangan</button>
              <?php elseif (isTtdExists($ttd['Kadept IT'] ?? [])): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" disabled>Sudah Ditandatangani</button>
              <?php else: ?>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="kadept_it">Tanda Tangan</button>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
        <tr style="text-align:center;font-size:14px;font-weight:bold;">
          <td style="padding:4px;" id="ttd-label-pemohon"><?= isset($ttd['Pemohon']) ? htmlspecialchars($ttd['Pemohon']['SignedByUserName']) : 'Pemohon' ?></td>
          <td style="padding:4px;" id="ttd-label-atasan"><?= isset($ttd['Atasan Pemohon']) ? htmlspecialchars($ttd['Atasan Pemohon']['SignedByUserName']) : 'Atasan Pemohon' ?></td>
          <td style="padding:4px;" id="ttd-label-petugas"><?= isset($ttd['Petugas IT']) ? htmlspecialchars($ttd['Petugas IT']['SignedByUserName']) : 'Petugas IT' ?></td>
          <td style="padding:4px;" id="ttd-label-kabag"><?= isset($ttd['Kabag IT']) ? htmlspecialchars($ttd['Kabag IT']['SignedByUserName']) : 'Kabag IT' ?></td>
          <td style="padding:4px;" id="ttd-label-kadept"><?= isset($ttd['Kadept IT']) ? htmlspecialchars($ttd['Kadept IT']['SignedByUserName']) : 'Kadept IT' ?></td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td style="padding:6px;font-size:14px;">
      <b>Perhatian :</b><br>
      <ol style="margin:4px 0 0 18px;padding-left:0;">
        <li>Untuk spesifikasi ditentukan IT mengikuti standar.</li>
        <li>Untuk jenis perangkat dengan spesifikasi di luar standar, harus mendapatkan approval dari Direksi.</li>
      </ol>
    </td>
  </tr>
  <tr>
    <td style="padding:0;">
      <table style="width:100%;border-collapse:collapse;border-top:1px solid #000;">
        <tr style="text-align:center;font-weight:bold;">
          <td style="width:80%;padding:6px;border-right:1px solid #000;">SUM-FM-IT-014</td>
          <td style="width:20%;padding:6px;">HW</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</div>



<!-- Modal Konfirmasi Hapus TTD -->
<div class="modal fade" id="modalKonfirmasiHapusTtd" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Konfirmasi Hapus Tanda Tangan</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Apakah Anda yakin ingin menghapus tanda tangan ini?</p>
        <p><small class="text-muted">Tindakan ini tidak dapat dibatalkan.</small></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="btnKonfirmasiHapusTtd">Ya, Hapus Tanda Tangan</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Notifikasi Sukses Hapus TTD -->
<div class="modal fade" id="modalSuksesHapusTtd" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Berhasil</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p id="pesanSuksesHapus">Tanda tangan berhasil dihapus.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

</div>
<!-- Modal Tanda Tangan -->
<div class="modal fade" id="modalTtd" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Buat / Upload Tanda Tangan</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="formTtd" enctype="multipart/form-data">
          <input type="hidden" name="ticket" value="<?= htmlspecialchars($data['ticket']) ?>">
          <input type="hidden" name="role_code" id="ttdRoleCode" value="">
          <input type="hidden" name="canvas_data" id="canvasData" value="">

          <div class="row">
            <div class="col-md-8 mb-3">
              <label><b>Gambar di Canvas</b></label>
              <div style="border:1px solid #ccc; display:inline-block;">
                <canvas id="ttdCanvas" width="600" height="200" style="background:#fff;cursor:crosshair;"></canvas>
              </div>
              <button type="button" class="btn btn-sm btn-secondary mt-2" id="btnClearCanvas">Bersihkan Canvas</button>
            </div>
            <div class="col-md-4 mb-3">
              <label><b>Atau Upload File PNG/JPG</b></label>
              <input type="file" name="ttd_file" id="ttdFile" accept="image/png,image/jpeg" class="form-control mb-2">
              <div class="form-check mt-2">
                <input type="checkbox" class="form-check-input" id="chkSaveTemplate" name="save_template" value="1" checked>
                <label class="form-check-label" for="chkSaveTemplate">Simpan tanda tangan ini sebagai template</label>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success" id="btnSimpanTtd">Simpan & Gunakan</button>
      </div>
    </div>
  </div>
</div>

<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script>
$(function() {
  var currentRoleCode = null;
  var isRejected = <?php echo $isRejected ? 'true' : 'false'; ?>;
  var roleToDelete = null;

  function resetCanvas() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
  }

  // Reset canvas ketika modal dibuka
  $('#modalTtd').on('shown.bs.modal', function() {
    resetCanvas();
    $('#ttdFile').val('');
  });

  // Klik tombol Tanda Tangan
  $('.btn-ttd').on('click', function() {
    var roleCode = $(this).data('role');
    currentRoleCode = roleCode;

    $.post('ttd_sign.php', { ticket: '<?= htmlspecialchars($data['ticket']) ?>', role_code: roleCode }, function(resp) {
      try { resp = typeof resp === 'string' ? JSON.parse(resp) : resp; } catch(e) { resp = {}; }

      if (resp && resp.success && resp.signature_url) {
        updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id);
      } else if (resp && resp.need_template) {
        $('#ttdRoleCode').val(roleCode);
        $('#modalTtd').modal('show');
      } else {
        alert(resp.message || 'Gagal memproses tanda tangan.');
      }
    });
  });

  // Klik tombol Hapus Tanda Tangan - tampilkan modal konfirmasi
  $('.btn-delete-ttd').on('click', function() {
    roleToDelete = $(this).data('role');
    $('#modalKonfirmasiHapusTtd').modal('show');
  });

  // Klik tombol konfirmasi hapus di modal
  $('#btnKonfirmasiHapusTtd').on('click', function() {
    if (!roleToDelete) {
      alert('Role tidak ditemukan.');
      return;
    }

    $.post('delete_ttd.php', { ticket: '<?= htmlspecialchars($data['ticket']) ?>', role_code: roleToDelete }, function(resp) {
      try { resp = typeof resp === 'string' ? JSON.parse(resp) : resp; } catch(e) { resp = {}; }

      $('#modalKonfirmasiHapusTtd').modal('hide');

      if (resp && resp.success) {
        removeTtdArea(roleToDelete);
        $('#pesanSuksesHapus').text(resp.message || 'Tanda tangan berhasil dihapus.');
        $('#modalSuksesHapusTtd').modal('show');
      } else {
        alert(resp.message || 'Gagal menghapus tanda tangan.');
      }
    });
  });

  // Canvas
  var canvas = document.getElementById('ttdCanvas');
  var ctx = canvas.getContext('2d');
  var drawing = false;
  ctx.strokeStyle = '#000';
  ctx.lineWidth = 2;

  function getPos(e) {
    var rect = canvas.getBoundingClientRect();
    var x, y;
    if (e.touches && e.touches.length) {
      x = e.touches[0].clientX - rect.left;
      y = e.touches[0].clientY - rect.top;
    } else {
      x = e.clientX - rect.left;
      y = e.clientY - rect.top;
    }
    return {x:x, y:y};
  }

  function startDraw(e) {
    drawing = true;
    var p = getPos(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
  }

  function draw(e) {
    if (!drawing) return;
    e.preventDefault();
    var p = getPos(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
  }

  function endDraw() {
    drawing = false;
  }

  canvas.addEventListener('mousedown', startDraw);
  canvas.addEventListener('mousemove', draw);
  canvas.addEventListener('mouseup', endDraw);
  canvas.addEventListener('mouseleave', endDraw);

  canvas.addEventListener('touchstart', function(e){ startDraw(e); }, {passive:false});
  canvas.addEventListener('touchmove', function(e){ draw(e); }, {passive:false});
  canvas.addEventListener('touchend', function(e){ endDraw(e); }, {passive:false});

  $('#btnClearCanvas').on('click', function(){ resetCanvas(); });

  // Simpan TTD
  $('#btnSimpanTtd').on('click', function() {
    var roleCode = $('#ttdRoleCode').val() || currentRoleCode;
    if (!roleCode) {
      alert('Role tanda tangan tidak dikenal.');
      return;
    }

    $('#canvasData').val(canvas.toDataURL('image/png'));

    var form = document.getElementById('formTtd');
    var formData = new FormData(form);

    $.ajax({
      url: 'ttd_save_template.php',
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(resp) {
        try { resp = typeof resp === 'string' ? JSON.parse(resp) : resp; } catch(e) { resp = {}; }

        if (resp && resp.success && resp.signature_url) {
          $('#modalTtd').modal('hide');
          updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id);
        } else {
          alert(resp.message || 'Gagal menyimpan tanda tangan.');
        }
      },
      error: function() {
        alert('Error saat menyimpan tanda tangan.');
      }
    });
  });

  function updateTtdArea(roleCode, imgUrl, name, signedByUserId) {
    var areaId, labelId;
    if (roleCode === 'pemohon') {
      areaId  = '#ttd-area-pemohon';
      labelId = '#ttd-label-pemohon';
    } else if (roleCode === 'atasan_pemohon') {
      areaId  = '#ttd-area-atasan';
      labelId = '#ttd-label-atasan';
    } else if (roleCode === 'petugas_it') {
      areaId  = '#ttd-area-petugas';
      labelId = '#ttd-label-petugas';
    } else if (roleCode === 'kabag_it') {
      areaId  = '#ttd-area-kabag';
      labelId = '#ttd-label-kabag';
    } else if (roleCode === 'kadept_it') {
      areaId  = '#ttd-area-kadept';
      labelId = '#ttd-label-kadept';
    }

    // Update gambar TTD
    if (areaId) {
      $(areaId).html('<img src="' + imgUrl + '" height="60" style="max-width:100%;object-fit:contain;">');
    }

    // Update nama yang menandatangani
    if (labelId && name) {
      $(labelId).text(name);
    }

    // Ganti button - hanya tampilkan "Hapus" jika ditandatangani oleh user login
    var tdContainer = $('button[data-role="' + roleCode + '"]').closest('td');
    if (tdContainer.length) {
      // Hapus semua button dengan role ini
      tdContainer.find('button[data-role="' + roleCode + '"]').remove();
      
      var currentUserId = <?= $_SESSION['UserId'] ?? 0 ?>;
      console.log('updateTtdArea - roleCode:', roleCode, 'signedByUserId:', signedByUserId, 'currentUserId:', currentUserId);
      // Jangan tambahkan tombol jika tiket sudah ditolak
      if (isRejected) {
        console.log('Ticket is rejected - skip adding TTD buttons for role', roleCode);
        return;
      }

      // Hanya tampilkan "Hapus Tanda Tangan" jika ditandatangani oleh user login
      if (signedByUserId && parseInt(signedByUserId) === parseInt(currentUserId)) {
        console.log('Showing delete button for:', roleCode);
        // Tambah button "Hapus Tanda Tangan"
        tdContainer.append(
          '<button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-delete-ttd" data-role="' + roleCode + '">Hapus Tanda Tangan</button>'
        );
        // Attach event listener untuk button baru
        attachDeleteButtonListener();
      } else {
        console.log('Showing disabled button for:', roleCode);
        // Jika user lain yang tanda tangan, tampilkan button yang disabled
        tdContainer.append(
          '<button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-role="' + roleCode + '" disabled>Sudah Ditandatangani</button>'
        );
      }
    }
  }

  function removeTtdArea(roleCode) {
    var areaId, labelId, defaultName;
    if (roleCode === 'pemohon') {
      areaId  = '#ttd-area-pemohon';
      labelId = '#ttd-label-pemohon';
      defaultName = 'Pemohon';
    } else if (roleCode === 'atasan_pemohon') {
      areaId  = '#ttd-area-atasan';
      labelId = '#ttd-label-atasan';
      defaultName = 'Atasan Pemohon';
    } else if (roleCode === 'petugas_it') {
      areaId  = '#ttd-area-petugas';
      labelId = '#ttd-label-petugas';
      defaultName = 'Petugas IT';
    } else if (roleCode === 'kabag_it') {
      areaId  = '#ttd-area-kabag';
      labelId = '#ttd-label-kabag';
      defaultName = 'Kabag IT';
    } else if (roleCode === 'kadept_it') {
      areaId  = '#ttd-area-kadept';
      labelId = '#ttd-label-kadept';
      defaultName = 'Kadept IT';
    }

    // Hapus gambar TTD
    if (areaId) {
      $(areaId).html('');
    }

    // Reset label ke default
    if (labelId) {
      $(labelId).text(defaultName);
    }

    // Ganti button "Hapus Tanda Tangan" menjadi "Tanda Tangan"
    var tdContainer = $('button[data-role="' + roleCode + '"]').closest('td');
    if (tdContainer.length) {
      // Hapus semua button dengan role ini
      tdContainer.find('button[data-role="' + roleCode + '"]').remove();
      // Tambah button "Tanda Tangan" hanya jika tiket tidak ditolak
      if (!isRejected) {
        tdContainer.append(
          '<button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-ttd" data-role="' + roleCode + '">Tanda Tangan</button>'
        );
        // Attach event listener untuk button baru
        attachSignButtonListener();
      }
    }
  }

  // Helper function untuk attach event listener ke button tanda tangan
  function attachSignButtonListener() {
    $(document).off('click', '.btn-ttd').on('click', '.btn-ttd', function() {
      var roleCode = $(this).data('role');
      currentRoleCode = roleCode;

      $.post('ttd_sign.php', { ticket: '<?= htmlspecialchars($data['ticket']) ?>', role_code: roleCode }, function(resp) {
        try { resp = typeof resp === 'string' ? JSON.parse(resp) : resp; } catch(e) { resp = {}; }

        if (resp && resp.success && resp.signature_url) {
          updateTtdArea(roleCode, resp.signature_url, resp.signed_by, resp.signed_by_user_id);
        } else if (resp && resp.need_template) {
          $('#ttdRoleCode').val(roleCode);
          $('#modalTtd').modal('show');
        } else {
          alert(resp.message || 'Gagal memproses tanda tangan.');
        }
      });
    });
  }

  // Helper function untuk attach event listener ke button hapus
  function attachDeleteButtonListener() {
    $(document).off('click', '.btn-delete-ttd').on('click', '.btn-delete-ttd', function() {
      roleToDelete = $(this).data('role');
      $('#modalKonfirmasiHapusTtd').modal('show');
    });
  }


});
</script>

</body>
</html>
