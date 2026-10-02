<?php
// export_pdf.php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: ../login.php');
    exit;
}

// Check permissions
$groupId = $_SESSION['GroupId'];
$menuId = 43; // MenuId untuk Employee
$permissions = checkPermissions($conn, $groupId, $menuId);

if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

try {
    // Create PDF instance with landscape orientation
    $pdf = new TCPDF('L', PDF_UNIT, 'F4', true, 'UTF-8', false);
    
    // Set document info
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('HR System');
    $pdf->SetTitle('Laporan Karyawan');
    $pdf->SetSubject('Laporan Data Karyawan');
    
    // Set margins
    $pdf->SetMargins(10, 15, 10);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 8);
    
    // Judul laporan
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'LAPORAN DATA KARYAWAN', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 8);
    
    // Info filter
    $filter_info = "Filter: ";
    
    if (!empty($_GET['dept_name']) && $_GET['dept_name'] != 'Semua') {
        $filter_info .= "Departemen: " . htmlspecialchars($_GET['dept_name']) . ", ";
    }
    if (!empty($_GET['bagian_name']) && $_GET['bagian_name'] != 'Semua') {
        $filter_info .= "Bagian: " . htmlspecialchars($_GET['bagian_name']) . ", ";
    }
    if (!empty($_GET['subbag_name']) && $_GET['subbag_name'] != 'Semua') {
        $filter_info .= "Sub Bagian: " . htmlspecialchars($_GET['subbag_name']) . ", ";
    }
    if (!empty($_GET['jabatan_name']) && $_GET['jabatan_name'] != 'Semua') {
        $filter_info .= "Jabatan: " . htmlspecialchars($_GET['jabatan_name']) . ", ";
    }
    if (isset($_GET['aktif']) && $_GET['aktif'] !== '') {
        $status = ($_GET['aktif'] == '1') ? 'Aktif' : 'Nonaktif';
        $filter_info .= "Status: " . $status . ", ";
    }
    
    if ($filter_info == "Filter: ") {
        $filter_info = "Semua Data";
    } else {
        $filter_info = rtrim($filter_info, ', ');
    }
    
    $pdf->Cell(0, 5, $filter_info, 0, 1);
    $pdf->Cell(0, 5, 'Tanggal Cetak: ' . date('d-m-Y H:i:s'), 0, 1);
    $pdf->Ln(3);
    
    // Header tabel
    $header = ['No', 'NIK', 'Nama Lengkap', 'Status', 'Departemen', 'Bagian', 'Sub Bagian', 'Jabatan', 'Golongan', 'Shift'];
    $w = [8, 15, 40, 15, 30, 30, 30, 30, 20, 15];
    
    // Set header table
    $pdf->SetFillColor(211, 211, 211); // Light gray background
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', 'B', 9);
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 6, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Data tabel
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetTextColor(0);
    $fill = false;
    
    // Get filtered data
    $employees = getFilteredEmployees($conn, $_GET);
    $index = 0;
    
    foreach ($employees as $row) {
        // Check if we need a new page
        if ($pdf->GetY() > 260) {
            $pdf->AddPage('L');
            // Redraw header
            $pdf->SetFont('helvetica', 'B', 9);
            for ($i = 0; $i < count($header); $i++) {
                $pdf->Cell($w[$i], 6, $header[$i], 1, 0, 'C', 1);
            }
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 9);
        }
        
        $pdf->Cell($w[0], 6, ++$index, 'LR', 0, 'C', $fill);
        $pdf->Cell($w[1], 6, $row['nik'] ?? '-', 'LR', 0, 'L', $fill);
        $pdf->Cell($w[2], 6, $row['nama_lengkap'] ?? '-', 'LR', 0, 'L', $fill);
        $pdf->Cell($w[3], 6, ($row['aktif'] == 1 ? 'Aktif' : 'Nonaktif'), 'LR', 0, 'C', $fill);
        $pdf->Cell($w[4], 6, $row['dept'] ?? '-', 'LR', 0, 'L', $fill);
        $pdf->Cell($w[5], 6, $row['bagian'] ?? '-', 'LR', 0, 'L', $fill);
        $pdf->Cell($w[6], 6, $row['subbag'] ?? '-', 'LR', 0, 'L', $fill);
        $pdf->Cell($w[7], 6, $row['jabatan'] ?? '-', 'LR', 0, 'L', $fill);
        $pdf->Cell($w[8], 6, $row['golongan'] ?? '-', 'LR', 0, 'L', $fill);
        $pdf->Cell($w[9], 6, $row['shift'] ?? '-', 'LR', 0, 'L', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }
    
    // Closing line
    $pdf->Cell(array_sum($w), 0, '', 'T');
    
    // Output PDF
    $pdf->Output('laporan_karyawan_' . date('YmdHis') . '.pdf', 'D');
    
} catch (Exception $e) {
    die("Error generating PDF: " . $e->getMessage());
}

/**
 * Get filtered employees for PDF export
 */
function getFilteredEmployees($conn, $filters) {
    $sql = "SELECT
                m_emp.nik,
                m_emp.nama_lengkap,
                m_emp.aktif,
                m_shift.shift,
                m_gol.golongan,
                m_jab.jabatan,
                m_subbag.subbag,
                m_bag.bagian,
                m_dept.dept
            FROM
                dbo.m_emp
                LEFT JOIN dbo.m_shift ON m_emp.id_shift = m_shift.id_shift
                LEFT JOIN dbo.m_gol ON m_emp.id_gol = m_gol.id_gol
                LEFT JOIN dbo.m_jab ON m_emp.id_jab = m_jab.id_jab
                LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
                LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
                LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
            WHERE 1=1";

    $params = [];
    
    // Add filters
    if (!empty($filters['dept'])) {
        $sql .= " AND m_dept.id_dept = ?";
        $params[] = $filters['dept'];
    }
    
    if (!empty($filters['bagian'])) {
        $sql .= " AND m_bag.id_bag = ?";
        $params[] = $filters['bagian'];
    }
    
    if (!empty($filters['subbag'])) {
        $sql .= " AND m_subbag.id_subbag = ?";
        $params[] = $filters['subbag'];
    }
    
    if (!empty($filters['jabatan'])) {
        $sql .= " AND m_jab.jabatan = ?";
        $params[] = $filters['jabatan'];
    }
    
    if (isset($filters['aktif']) && $filters['aktif'] !== '') {
        $aktifValue = ($filters['aktif'] == '1') ? 1 : 0;
        $sql .= " AND m_emp.aktif = ?";
        $params[] = $aktifValue;
    }
    
    $sql .= " ORDER BY m_emp.nama_lengkap";
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    $employees = [];
    
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $employees[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    
    return $employees;
}

/**
 * Check user permissions
 */
function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete 
            FROM dbo.SMGroupTrustee 
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    sqlsrv_free_stmt($stmt);
    
    return $permissions;
}