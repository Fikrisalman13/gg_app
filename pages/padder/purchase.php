<?php
// purchase.php - Form Pembuatan Purchase Order Padder
session_start();
ob_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// ====== Auth & Permission ======
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// Cek permission untuk modul purchase
function checkPurchasePermission($conn, $groupId, $menuId) {
    $sql = "SELECT CanAdd FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $result = ['CanAdd' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $result = $row;
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);
    return $result;
}

$permissions = checkPurchasePermission($conn, $_SESSION['GroupId'], 113); // Anggap MenuId 113 untuk Padder
if ($permissions['CanAdd'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk membuat purchase order.";
    header('Location: master_padder.php');
    exit;
}

// Get data untuk dropdowns
function getDropdownData($conn) {
    $data = [];
    
    // --- Get vendors ---
    $sqlVendors = "SELECT vendor_id, vendor_name, address, contact_person, phone, email 
                   FROM pad_m_vendor 
                   WHERE vendor_id IS NOT NULL 
                   ORDER BY vendor_name";
    $stmtVendors = sqlsrv_query($conn, $sqlVendors);
    $data['vendors'] = [];
    while ($row = sqlsrv_fetch_array($stmtVendors, SQLSRV_FETCH_ASSOC)) {
        $data['vendors'][] = $row;
    }
    if ($stmtVendors !== false) sqlsrv_free_stmt($stmtVendors);
    
    // --- Get padders ---
    $sqlPadders = "SELECT p.padder_id, p.padder_name, p.created_at, p.remarks
                   FROM pad_m_padder p
                   WHERE p.padder_id NOT IN (SELECT padder_id FROM pad_t_purchase)
                   AND p.status = 'DIBUAT'
                   ORDER BY p.padder_id";
    $stmtPadders = sqlsrv_query($conn, $sqlPadders);
    $data['padders'] = [];
    while ($row = sqlsrv_fetch_array($stmtPadders, SQLSRV_FETCH_ASSOC)) {
        // --- Convert created_at ke string ISO untuk JS ---
        if ($row['created_at'] instanceof DateTime) {
            $row['created_at'] = $row['created_at']->format('Y-m-d'); // cukup tanggal
        } elseif (!empty($row['created_at'])) {
            $row['created_at'] = date('Y-m-d', strtotime($row['created_at']));
        } else {
            $row['created_at'] = null;
        }

        // --- Get spesifikasi ---
        $sqlSpec = "SELECT spec_name, spec_value 
                    FROM pad_m_padder_spec 
                    WHERE padder_id = ? 
                    ORDER BY id";
        $stmtSpec = sqlsrv_query($conn, $sqlSpec, [$row['padder_id']]);
        $row['specifications'] = [];
        while ($spec = sqlsrv_fetch_array($stmtSpec, SQLSRV_FETCH_ASSOC)) {
            $row['specifications'][] = $spec;
        }
        if ($stmtSpec !== false) sqlsrv_free_stmt($stmtSpec);
        
        $data['padders'][] = $row;
    }
    if ($stmtPadders !== false) sqlsrv_free_stmt($stmtPadders);
    
    return $data;
}


$dropdownData = getDropdownData($conn);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $padderId = trim($_POST['padder_id'] ?? '');
        $purchaseDate = trim($_POST['purchase_date'] ?? '');
        $poNumber = trim($_POST['po_number'] ?? '');
        $vendorId = trim($_POST['vendor_id'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $createdBy = $_SESSION['UserName'];
        
        // Validation
        if (empty($padderId)) {
            throw new Exception("Padder harus dipilih!");
        }
        
        if (empty($purchaseDate)) {
            throw new Exception("Tanggal purchase harus diisi!");
        }
        
        if (empty($poNumber)) {
            throw new Exception("Nomor PO harus diisi!");
        }
        
        if (empty($vendorId)) {
            throw new Exception("Vendor harus dipilih!");
        }
        
        // Cek apakah padder sudah ada di purchase
        $sqlCheck = "SELECT COUNT(*) as count FROM pad_t_purchase WHERE padder_id = ?";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$padderId]);
        $rowCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
        if ($rowCheck['count'] > 0) {
            throw new Exception("Padder ini sudah memiliki data purchase!");
        }
        
        // Cek status padder
        $sqlStatus = "SELECT status FROM pad_m_padder WHERE padder_id = ?";
        $stmtStatus = sqlsrv_query($conn, $sqlStatus, [$padderId]);
        $rowStatus = sqlsrv_fetch_array($stmtStatus, SQLSRV_FETCH_ASSOC);
        if (!$rowStatus || $rowStatus['status'] != 'DIBUAT') {
            throw new Exception("Status padder tidak valid untuk purchase!");
        }
        
        // Format price
        $price = str_replace(['.', ','], '', $price);
        if (!is_numeric($price) || $price < 0) {
            throw new Exception("Harga harus berupa angka yang valid!");
        }
        $price = (float)$price;
        
        // Begin transaction
        sqlsrv_begin_transaction($conn);
        
        // Insert purchase data
        $sqlInsert = "INSERT INTO pad_t_purchase 
                     (padder_id, purchase_date, po_number, vendor_id, price, remarks) 
                     VALUES (?, ?, ?, ?, ?, ?)";
        $paramsInsert = [
            $padderId, 
            $purchaseDate, 
            $poNumber, 
            $vendorId, 
            $price, 
            $remarks
        ];
        
        $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);
        
        if ($stmtInsert === false) {
            throw new Exception("Gagal menyimpan data purchase: " . print_r(sqlsrv_errors(), true));
        }
        
        // Update padder status to DIBELI
        $sqlUpdatePadder = "UPDATE pad_m_padder SET status = 'DIBELI' WHERE padder_id = ?";
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdatePadder, [$padderId]);
        
        if ($stmtUpdate === false) {
            throw new Exception("Gagal update status padder!");
        }
        
        // Log status change
        $sqlLog = "INSERT INTO pad_status_log (padder_id, status, changed_by, remarks) 
                  VALUES (?, 'DIBELI', ?, ?)";
        $logRemarks = "Purchase Order: " . $poNumber . " - " . $remarks;
        sqlsrv_query($conn, $sqlLog, [$padderId, $createdBy, $logRemarks]);
        
        // Commit transaction
        sqlsrv_commit($conn);
        
        $_SESSION['success'] = "Purchase Order berhasil dibuat untuk Padder " . $padderId;
        header('Location: purchase_list.php?id=' . $padderId);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($conn)) {
            sqlsrv_rollback($conn);
        }
        $_SESSION['error'] = $e->getMessage();
        
        // Store POST data for form repopulation
        $formData = [
            'padder_id' => $_POST['padder_id'] ?? '',
            'purchase_date' => $_POST['purchase_date'] ?? '',
            'po_number' => $_POST['po_number'] ?? '',
            'vendor_id' => $_POST['vendor_id'] ?? '',
            'price' => $_POST['price'] ?? '',
            'remarks' => $_POST['remarks'] ?? ''
        ];
    }
}

// Include layout parts
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buat Purchase Order - Monitoring Padder</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/select2.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/select2-bootstrap4.min.css">
    
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">
                            Buat Purchase Order
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="master_padder.php">Master Padder</a></li>
                            <li class="breadcrumb-item active">Purchase Order</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                                <h3 class="card-title">                                  
                                    Form Purchase Order Padder
                                </h3>
                                <div class="card-tools">
                                    <a href="purchase_list.php" class="btn btn-info btn-sm">
                                        <i class="fas fa-list mr-1"></i> Lihat Daftar PO
                                    </a>                                
                                </div>
                            </div>

                            <form method="POST" id="purchaseForm">
                                <div class="card-body">
                                    <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger alert-dismissible">
                                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                                        <i class="fas fa-exclamation-triangle mr-2"></i> 
                                        <?= htmlspecialchars($_SESSION['error']) ?>
                                        <?php unset($_SESSION['error']); ?>
                                    </div>
                                    <?php endif; ?>

                                    <div class="row">
                                        <!-- Left Column - Purchase Data -->
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                                                    <h3 class="card-title">Data Purchase Order</h3>
                                                </div>
                                                <div class="card-body">
                                                    <!-- Padder Selection -->
                                                    <div class="form-group">
                                                        <label for="padder_id" class="required-label">Pilih Padder</label>
                                                        <select class="form-control select2" id="padder_id" name="padder_id" 
                                                                required style="width: 100%;" 
                                                                data-placeholder="Pilih padder...">
                                                            <option value=""></option>
                                                            <?php foreach ($dropdownData['padders'] as $padder): ?>
                                                                <option value="<?= $padder['padder_id'] ?>" 
                                                                        <?= isset($formData['padder_id']) && $formData['padder_id'] == $padder['padder_id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($padder['padder_id']) ?> - <?= htmlspecialchars($padder['padder_name']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <small class="form-text text-muted">
                                                            Pilih padder yang akan dibeli (hanya padder dengan status "DIBUAT" yang tersedia)
                                                        </small>
                                                    </div>

                                                    <!-- Purchase Date -->
                                                    <div class="form-group">
                                                        <label for="purchase_date" class="required-label">Tanggal Purchase</label>
                                                        <input type="date" class="form-control" id="purchase_date" 
                                                               name="purchase_date" 
                                                               value="<?= isset($formData['purchase_date']) ? $formData['purchase_date'] : date('Y-m-d') ?>" 
                                                               required>
                                                    </div>

                                                    <!-- PO Number -->
                                                    <div class="form-group">
                                                        <label for="po_number" class="required-label">Nomor PO</label>
                                                        <input type="text" class="form-control" id="po_number" 
                                                               name="po_number" 
                                                               value="<?= isset($formData['po_number']) ? htmlspecialchars($formData['po_number']) : '' ?>" 
                                                               required maxlength="50" 
                                                               placeholder="Masukkan nomor purchase order...">
                                                        <small class="form-text text-muted">
                                                            Contoh: PO/PD/2024/001
                                                        </small>
                                                    </div>

                                                    <!-- Vendor Selection -->
                                                    <div class="form-group">
                                                        <label for="vendor_id" class="required-label">Pilih Vendor</label>
                                                        <select class="form-control select2" id="vendor_id" name="vendor_id" 
                                                                required style="width: 100%;" 
                                                                data-placeholder="Pilih vendor...">
                                                            <option value=""></option>
                                                            <?php foreach ($dropdownData['vendors'] as $vendor): ?>
                                                                <option value="<?= $vendor['vendor_id'] ?>" 
                                                                        <?= isset($formData['vendor_id']) && $formData['vendor_id'] == $vendor['vendor_id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($vendor['vendor_name']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <!-- Price -->
                                                    <div class="form-group">
                                                        <label for="price">Harga Purchase (Rp)</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text">Rp</span>
                                                            </div>
                                                            <input type="text" class="form-control price-input" 
                                                                   id="price" name="price" 
                                                                   value="<?= isset($formData['price']) ? htmlspecialchars($formData['price']) : '' ?>" 
                                                                   placeholder="0"
                                                                   oninput="formatCurrency(this)">
                                                        </div>
                                                        <small class="form-text text-muted">
                                                            Masukkan harga purchase dalam Rupiah
                                                        </small>
                                                    </div>

                                                    <!-- Remarks -->
                                                    <div class="form-group">
                                                        <label for="remarks">Keterangan</label>
                                                        <textarea class="form-control" id="remarks" name="remarks" 
                                                                  rows="3" placeholder="Keterangan tambahan..."
                                                                  maxlength="255"><?= isset($formData['remarks']) ? htmlspecialchars($formData['remarks']) : '' ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Right Column - Information -->
                                        <div class="col-md-6">
                                            <!-- Selected Padder Info -->
                                            <div class="card">
                                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                                                    <h3 class="card-title">
                                                        <i class="fas fa-info-circle mr-2"></i>
                                                        Informasi Padder
                                                    </h3>
                                                </div>
                                                <div class="card-body">
                                                    <div id="padderInfo" class="text-center text-muted py-4">
                                                        <i class="fas fa-cube fa-2x mb-2"></i><br>
                                                        Pilih padder untuk melihat informasi
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Selected Vendor Info -->
                                            <div class="card">
                                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                                                    <h3 class="card-title">
                                                        <i class="fas fa-building mr-2"></i>
                                                        Informasi Vendor
                                                    </h3>
                                                </div>
                                                <div class="card-body">
                                                    <div id="vendorInfo" class="text-center text-muted py-4">
                                                        <i class="fas fa-store fa-2x mb-2"></i><br>
                                                        Pilih vendor untuk melihat informasi
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>"><i class="fas fa-save"></i> Simpan</button>
                                   
                                    <a href="purchase_list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="/gg_app/plugins/js/jquery-3.6.0.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/gg_app/plugins/js/select2.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
// Vendor data for dynamic display
const vendorData = {
    <?php foreach ($dropdownData['vendors'] as $vendor): ?>
    "<?= $vendor['vendor_id'] ?>": <?= json_encode($vendor) ?>,
    <?php endforeach; ?>
};

// Padder data for dynamic display  
const padderData = {
    <?php foreach ($dropdownData['padders'] as $padder): ?>
    "<?= $padder['padder_id'] ?>": <?= json_encode($padder) ?>,
    <?php endforeach; ?>
};

(function(){
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap4',
        placeholder: function() {
            return $(this).data('placeholder');
        },
        allowClear: true
    });

    // Format currency input
    function formatCurrency(input) {
        // Remove non-numeric characters
        let value = input.value.replace(/[^\d]/g, '');
        
        // Format with thousand separators
        if (value.length > 0) {
            value = parseInt(value).toLocaleString('id-ID');
        }
        
        input.value = value;
    }

    // Format date untuk display
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    // Update padder info when selection changes
    $('#padder_id').on('change', function() {
        const padderId = $(this).val();
        const infoContainer = $('#padderInfo');
        
        if (padderId && padderData[padderId]) {
            const padder = padderData[padderId];
            const createdDate = padder.created_at ? formatDate(padder.created_at) : '-';
            
            let specsHtml = '';
            if (padder.specifications && padder.specifications.length > 0) {
                specsHtml = `
                    <div class="mt-3">
                        <strong>Spesifikasi:</strong>
                        <div class="mt-1">
                `;
                padder.specifications.forEach(spec => {
                    specsHtml += `
                        <div class="spec-item">
                            <strong>${spec.spec_name}:</strong> ${spec.spec_value}
                        </div>
                    `;
                });
                specsHtml += `
                        </div>
                    </div>
                `;
            } else {
                specsHtml = `
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-info-circle mr-1"></i>
                            Tidak ada spesifikasi
                        </small>
                    </div>
                `;
            }

            infoContainer.html(`
                <div class="text-left">
                    <table class="table table-sm table-borderless mb-2">
                        <tr>
                            <th width="40%">Padder ID</th>
                            <td><strong class="text-primary">${padder.padder_id}</strong></td>
                        </tr>
                        <tr>
                            <th>Nama Padder</th>
                            <td>${padder.padder_name}</td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td><span class="badge badge-info">DIBUAT</span></td>
                        </tr>
                        <tr>
                            <th>Dibuat</th>
                            <td>${createdDate}</td>
                        </tr>
                        <tr>
                            <th>Keterangan</th>
                            <td>${padder.remarks || '-'}</td>
                        </tr>
                    </table>
                    ${specsHtml}
                </div>
            `);
        } else {
            infoContainer.html(`
                <div class="text-center text-muted py-4">
                    <i class="fas fa-cube fa-2x mb-2"></i><br>
                    Pilih padder untuk melihat informasi
                </div>
            `);
        }
    });

    // Update vendor info when selection changes
    $('#vendor_id').on('change', function() {
        const vendorId = $(this).val();
        const infoContainer = $('#vendorInfo');
        
        if (vendorId && vendorData[vendorId]) {
            const vendor = vendorData[vendorId];
            
            let contactHtml = '';
            if (vendor.contact_person || vendor.phone || vendor.email) {
                contactHtml = `
                    <div class="vendor-detail-item">
                        <strong>Contact Person:</strong><br>
                        <div class="pl-2">
                `;
                if (vendor.contact_person) {
                    contactHtml += `<div><i class="fas fa-user mr-1 text-muted"></i> ${vendor.contact_person}</div>`;
                }
                if (vendor.phone) {
                    contactHtml += `<div><i class="fas fa-phone mr-1 text-muted"></i> ${vendor.phone}</div>`;
                }
                if (vendor.email) {
                    contactHtml += `<div><i class="fas fa-envelope mr-1 text-muted"></i> ${vendor.email}</div>`;
                }
                contactHtml += `
                        </div>
                    </div>
                `;
            }

            let addressHtml = '';
            if (vendor.address) {
                addressHtml = `
                    <div class="vendor-detail-item">
                        <strong>Alamat:</strong><br>
                        <div class="pl-2">
                            <small class="text-muted">
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                ${vendor.address}
                            </small>
                        </div>
                    </div>
                `;
            }

            infoContainer.html(`
                <div class="text-left">
                    <div class="vendor-detail-item">
                        <strong>Nama Vendor:</strong><br>
                        <strong class="text-primary">${vendor.vendor_name}</strong>
                    </div>
                    
                    <div class="vendor-detail-item">
                        <strong>Vendor ID:</strong><br>
                        <span class="text-muted">${vendor.vendor_id}</span>
                    </div>
                    
                    ${addressHtml}
                    ${contactHtml}
                </div>
            `);
        } else {
            infoContainer.html(`
                <div class="text-center text-muted py-4">
                    <i class="fas fa-store fa-2x mb-2"></i><br>
                    Pilih vendor untuk melihat informasi
                </div>
            `);
        }
    });

    // Form validation and submission
    $('#purchaseForm').on('submit', function(e) {
        e.preventDefault();
        
        const padderId = $('#padder_id').val();
        const poNumber = $('#po_number').val().trim();
        const vendorId = $('#vendor_id').val();
        const purchaseDate = $('#purchase_date').val();
        
        // Basic validation
        if (!padderId) {
            Swal.fire({
                icon: 'warning',
                title: 'Padder Belum Dipilih',
                text: 'Silakan pilih padder terlebih dahulu!',
                confirmButtonColor: '#ffc107'
            });
            $('#padder_id').focus();
            return false;
        }
        
        if (!poNumber) {
            Swal.fire({
                icon: 'warning',
                title: 'Nomor PO Kosong',
                text: 'Silakan masukkan nomor purchase order!',
                confirmButtonColor: '#ffc107'
            });
            $('#po_number').focus();
            return false;
        }
        
        if (!vendorId) {
            Swal.fire({
                icon: 'warning',
                title: 'Vendor Belum Dipilih',
                text: 'Silakan pilih vendor terlebih dahulu!',
                confirmButtonColor: '#ffc107'
            });
            $('#vendor_id').focus();
            return false;
        }
        
        if (!purchaseDate) {
            Swal.fire({
                icon: 'warning',
                title: 'Tanggal Belum Dipilih',
                text: 'Silakan pilih tanggal purchase!',
                confirmButtonColor: '#ffc107'
            });
            $('#purchase_date').focus();
            return false;
        }
        
        // Get form data for confirmation
        const padderName = padderData[padderId] ? padderData[padderId].padder_name : '';
        const vendorName = vendorData[vendorId] ? vendorData[vendorId].vendor_name : '';
        const price = $('#price').val() || '0';
        
        // Confirmation dialog
        Swal.fire({
            title: 'Buat Purchase Order?',
            html: `<div class="text-left">
                <strong>Padder:</strong> ${padderId} - ${padderName}<br>
                <strong>Vendor:</strong> ${vendorName}<br>
                <strong>Nomor PO:</strong> ${poNumber}<br>
                <strong>Tanggal:</strong> ${purchaseDate}<br>
                <strong>Harga:</strong> Rp ${price}<br>
                <div class="alert alert-warning mt-2 small">
                    <i class="fas fa-info-circle mr-1"></i>
                    Status padder akan berubah menjadi "DIBELI"
                </div>
            </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check mr-2"></i> Ya, Buat PO',
            cancelButtonText: '<i class="fas fa-times mr-2"></i> Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Menyimpan...',
                    text: 'Sedang membuat purchase order',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit form
                $('#purchaseForm').off('submit').submit();
            }
        });
    });

    // Format price on page load if exists
    const priceInput = document.getElementById('price');
    if (priceInput && priceInput.value) {
        formatCurrency(priceInput);
    }

    // Initialize info displays if values are pre-selected
    $(document).ready(function() {
        const padderId = $('#padder_id').val();
        const vendorId = $('#vendor_id').val();
        
        if (padderId) {
            $('#padder_id').trigger('change');
        }
        if (vendorId) {
            $('#vendor_id').trigger('change');
        }
        
        // Focus on first field
        $('#padder_id').select2('focus');
    });

})();
</script>

</body>
</html>

<?php 
include '../../includes/footer.php'; 
?>