<?php
// components/rejection_status.php
// Expects $data (array containing status_ticket, rejected_by, rejection_reason, rejection_date, etc.)
// And optionally $ticket (string) if not in $data

// Defensive check
if (!isset($data) || !is_array($data)) return;

$status = strtolower(trim($data['status_ticket'] ?? ''));
if ($status !== 'ditolak') return;

$rejectedBy = !empty($data['rejected_by']) ? $data['rejected_by'] : ($data['updated_by'] ?? '');
$rejectionDate = !empty($data['rejection_date']) ? $data['rejection_date'] : ($data['updated_at'] ?? null);
$rejectionReason = $data['rejection_reason'] ?? '-';
$ticketVal = $data['ticket'] ?? ($ticket ?? 'Unknown Ticket');

// Logic to determine name of rejector
$rejectedByName = 'N/A';
if (!empty($rejectedBy)) {
    global $conn;
    if ($conn) {
        $rId = (int)$rejectedBy;
        $found = false;
        
        // 1. SMUserMs join m_emp
        $sqlU = "SELECT e.nama_lengkap, u.UserName FROM SMUserMs u LEFT JOIN m_emp e ON u.EmpId = e.id_emp WHERE u.UserId = ?";
        $stmtU = @sqlsrv_query($conn, $sqlU, [$rId]);
        if ($stmtU && $rowU = sqlsrv_fetch_array($stmtU, SQLSRV_FETCH_ASSOC)) {
            $rejectedByName = $rowU['nama_lengkap'] ?? $rowU['UserName'];
            $found = true;
        }
        
        // 2. TTD fallback (Using general Form_Umum_TTD)
        if (!$found) {
            $sqlT = "SELECT TOP 1 SignedByUserName FROM Form_Umum_TTD WHERE Ticket = ? AND SignedByUserId = ?";
            $stmtT = @sqlsrv_query($conn, $sqlT, [$ticketVal, $rId]);
            if ($stmtT && $rowT = sqlsrv_fetch_array($stmtT, SQLSRV_FETCH_ASSOC)) {
                $rejectedByName = $rowT['SignedByUserName'];
                $found = true;
            }
        }
        
        // 3. User ID fallback
        if (!$found) {
             $rejectedByName = 'UserId: ' . $rId;
        }
    }
}

// Format date
$dateStr = '-';
if ($rejectionDate instanceof DateTime) {
    $dateStr = $rejectionDate->format('d-m-Y H:i');
} elseif (!empty($rejectionDate)) {
    $dateStr = $rejectionDate;
}
?>

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
.rejection-box style {
    display: none;
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
    background: #ffecec;
    padding: 12px 15px;
    border-radius: 6px;
    border: 1px solid #f5c6cb;
    margin-top: 8px;
    font-style: italic;
    color: #721c24;
    font-weight: 500;
}
</style>

<div class="rejection-box" style="max-width:900px;margin:10px auto;">
    <div class="rejection-header">
        <i class="fas fa-times-circle"></i> PENGAJUAN DITOLAK
    </div>
    <div class="rejection-content">
        <div class="mb-2">
            <strong>Ditolak oleh</strong>: <?= htmlspecialchars($rejectedByName) ?>
        </div>
        <div class="mb-2">
            <strong>Tanggal Penolakan</strong>: <?= htmlspecialchars($dateStr) ?>
        </div>
        <div>
            <strong>Alasan Penolakan</strong>:
            <div class="rejection-reason">
                <?= nl2br(htmlspecialchars($rejectionReason)) ?>
            </div>
        </div>
    </div>
</div>
