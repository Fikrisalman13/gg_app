<?php
// edit_purchase.php - Form Edit Purchase Order Padder
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

// Cek permission untuk edit data
function checkEditPermission($conn, $groupId, $menuId) {
    $sql = "SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $result = ['CanEdit' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $result = $row;
    }
    if ($stmt !== false) sqlsrv_free_stmt($stmt);
    return $result;
}

$permissions = checkEditPermission($conn, $_SESSION['GroupId'], 113);
if ($permissions['CanEdit'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit purchase order.";
    header('Location: purchase_list.php');
    exit;
}

// Get purchase ID from URL
$purchaseId = $_GET['id'] ?? '';
if (empty($purchaseId)) {
    $_SESSION['error'] = "Purchase ID tidak valid!";
    header('Location: purchase_list.php');
    exit;
}

// Fetch purchase data
$sqlPurchase = "SELECT p.*, v.vendor_name, v.address, v.contact_person, v.phone, v.email,
                       pad.padder_id, pad.padder_name, pad.status as padder_status
                FROM pad_t_purchase p
                LEFT JOIN pad_m_vendor v ON p.vendor_id = v.vendor_id
                LEFT JOIN pad_m_padder pad ON p.padder_id = pad.padder_id
                WHERE p.id = ?";
$stmtPurchase = sqlsrv_query($conn, $sqlPurchase, [$purchaseId]);
$purchase = sqlsrv_fetch_array($stmtPurchase, SQLSRV_FETCH_ASSOC);

if (!$purchase) {
    $_SESSION['error'] = "Data purchase tidak ditemukan!";
    header('Location: purchase_list.php');
    exit;
}

// Get data untuk dropdowns
function getDropdownData($conn, $currentPadderId) {
    $data = [];
    
    // Get vendors dengan informasi lengkap
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
    
    // Get current padder data dengan spesifikasi
    $sqlPadder = "SELECT p.padder_id, p.padder_name, p.created_at, p.remarks
                  FROM pad_m_padder p
                  WHERE p.padder_id = ?";
    $stmtPadder = sqlsrv_query($conn, $sqlPadder, [$currentPadderId]);
    $data['current_padder'] = sqlsrv_fetch_array($stmtPadder, SQLSRV_FETCH_ASSOC);
    
    if ($data['current_padder']) {
        // Get spesifikasi untuk padder
        $sqlSpec = "SELECT spec_name, spec_value 
                    FROM pad_m_padder_spec 
                    WHERE padder_id = ? 
                    ORDER BY id";
        $stmtSpec = sqlsrv_query($conn, $sqlSpec, [$currentPadderId]);
        $data['current_padder']['specifications'] = [];
        while ($spec = sqlsrv_fetch_array($stmtSpec, SQLSRV_FETCH_ASSOC)) {
            $data['current_padder']['specifications'][] = $spec;
        }
        if ($stmtSpec !== false) sqlsrv_free_stmt($stmtSpec);
    }
    
    return $data;
}

$dropdownData = getDropdownData($conn, $purchase['padder_id']);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $purchaseDate = trim($_POST['purchase_date'] ?? '');
        $poNumber = trim($_POST['po_number'] ?? '');
        $vendorId = trim($_POST['vendor_id'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        
        // Validation
        if (empty($purchaseDate)) {
            throw new Exception("Tanggal purchase harus diisi!");
        }
        
        if (empty($poNumber)) {
            throw new Exception("Nomor PO harus diisi!");
        }
        
        if (empty($vendorId)) {
            throw new Exception("Vendor harus dipilih!");
        }
        
        // Format price
        $price = str_replace(['.', ','], '', $price);
        if (!is_numeric($price) || $price < 0) {
            throw new Exception("Harga harus berupa angka yang valid!");
        }
        $price = (float)$price;
        
        // Begin transaction
        sqlsrv_begin_transaction($conn);
        
        // Update purchase data
        $sqlUpdate = "UPDATE pad_t_purchase 
                     SET purchase_date = ?, po_number = ?, vendor_id = ?, price = ?, remarks = ?
                     WHERE id = ?";
        $paramsUpdate = [
            $purchaseDate, 
            $poNumber, 
            $vendorId, 
            $price, 
            $remarks,
            $purchaseId
        ];
        
        $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);
        
        if ($stmtUpdate === false) {
            throw new Exception("Gagal update data purchase: " . print_r(sqlsrv_errors(), true));
        }
        
        // Log the update
        $sqlLog = "INSERT INTO pad_status_log (padder_id, status, changed_by, remarks) 
                  VALUES (?, ?, ?, ?)";
        $logRemarks = "Update Purchase Order: " . $poNumber . " - " . $remarks;
        sqlsrv_query($conn, $sqlLog, [$purchase['padder_id'], $purchase['padder_status'], $_SESSION['UserName'], $logRemarks]);
        
        // Commit transaction
        sqlsrv_commit($conn);
        
        $_SESSION['success'] = "Purchase Order berhasil diupdate!";
        header('Location: purchase_list.php');
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($conn)) {
            sqlsrv_rollback($conn);
        }
        $_SESSION['error'] = $e->getMessage();
    }
}

// Include layout parts
include '../../includes/header.php';
include '../../includes/sidebar.php';

$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Purchase Order - Monitoring Padder</title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/select2.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/select2-bootstrap4.min.css">
    <style>
        .required-label::after { content: " *"; color: #dc3545; }
        .price-input { text-align: right; }
        .padder-info-card { border-left: 4px solid #28a745; }
        .vendor-info-card { border-left: 4px solid #007bff; }
        .select2-container--bootstrap4 .select2-selection--single { height: calc(2.25rem + 2px); }
        .spec-item { 
            padding: 4px 8px; 
            margin: 2px 0; 
            background: #f8f9fa; 
            border-radius: 4px;
            font-size: 0.85em;
        }
        .vendor-detail-item {
            padding: 3px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .vendor-detail-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark">
                            Edit Purchase Order
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="purchase_list.php">Daftar PO</a></li>
                            <li class="breadcrumb-item active">Edit PO</li>
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
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                <h3 class="card-title">                                  
                                    Form Edit Purchase Order
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
                                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                                    <h3 class="card-title">Data Purchase Order</h3>
                                                </div>
                                                <div class="card-body">
                                                    <!-- Padder Info (Read-only) -->
                                                    <div class="form-group">
                                                        <label>Padder</label>
                                                        <input type="text" class="form-control" 
                                                               value="<?= htmlspecialchars($purchase['padder_id']) ?> - <?= htmlspecialchars($purchase['padder_name']) ?>" 
                                                               readonly style="background-color: #e9ecef;">
                                                        <small class="form-text text-muted">
                                                            Padder tidak dapat diubah setelah purchase order dibuat
                                                        </small>
                                                    </div>

                                                    <!-- Purchase Date -->
                                                    <div class="form-group">
                                                        <label for="purchase_date" class="required-label">Tanggal Purchase</label>
                                                        <input type="date" class="form-control" id="purchase_date" 
                                                               name="purchase_date" 
                                                               value="<?= htmlspecialchars($purchase['purchase_date']->format('Y-m-d')) ?>" 
                                                               required>
                                                    </div>

                                                    <!-- PO Number -->
                                                    <div class="form-group">
                                                        <label for="po_number" class="required-label">Nomor PO</label>
                                                        <input type="text" class="form-control" id="po_number" 
                                                               name="po_number" 
                                                               value="<?= htmlspecialchars($purchase['po_number']) ?>" 
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
                                                                        <?= $purchase['vendor_id'] == $vendor['vendor_id'] ? 'selected' : '' ?>>
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
                                                                   value="<?= number_format($purchase['price'], 0, ',', '.') ?>" 
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
                                                                  maxlength="255"><?= htmlspecialchars($purchase['remarks'] ?? '') ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Right Column - Information -->
                                        <div class="col-md-6">
                                            <!-- Padder Info -->
                                            <div class="card">
                                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                                    <h3 class="card-title">
                                                        <i class="fas fa-info-circle mr-2"></i>
                                                        Informasi Padder
                                                    </h3>
                                                </div>
                                                <div class="card-body">
                                                    <?php if ($dropdownData['current_padder']): ?>
                                                        <?php 
                                                        $padder = $dropdownData['current_padder'];
                                                        $createdDate = $padder['created_at'] ? $padder['created_at']->format('d/m/Y') : '-';
                                                        ?>
                                                        <div class="text-left">
                                                            <table class="table table-sm table-borderless mb-2">
                                                                <tr>
                                                                    <th width="40%">Padder ID</th>
                                                                    <td><strong class="text-primary"><?= htmlspecialchars($padder['padder_id']) ?></strong></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Nama Padder</th>
                                                                    <td><?= htmlspecialchars($padder['padder_name']) ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Status</th>
                                                                    <td>
                                                                        <span class="badge badge-<?= 
                                                                            $purchase['padder_status'] == 'DIBELI' ? 'warning' : 
                                                                            ($purchase['padder_status'] == 'READY' ? 'success' : 'secondary')
                                                                        ?>">
                                                                            <?= $purchase['padder_status'] ?>
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Dibuat</th>
                                                                    <td><?= $createdDate ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Keterangan</th>
                                                                    <td><?= htmlspecialchars($padder['remarks'] ?? '-') ?></td>
                                                                </tr>
                                                            </table>
                                                            
                                                            <?php if (!empty($padder['specifications'])): ?>
                                                                <div class="mt-3">
                                                                    <strong>Spesifikasi:</strong>
                                                                    <div class="mt-1">
                                                                        <?php foreach ($padder['specifications'] as $spec): ?>
                                                                            <div class="spec-item">
                                                                                <strong><?= htmlspecialchars($spec['spec_name']) ?>:</strong> <?= htmlspecialchars($spec['spec_value']) ?>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="mt-2">
                                                                    <small class="text-muted">
                                                                        <i class="fas fa-info-circle mr-1"></i>
                                                                        Tidak ada spesifikasi
                                                                    </small>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center text-muted py-4">
                                                            <i class="fas fa-cube fa-2x mb-2"></i><br>
                                                            Data padder tidak ditemukan
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Vendor Info -->
                                            <div class="card">
                                                <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                                    <h3 class="card-title">
                                                        <i class="fas fa-building mr-2"></i>
                                                        Informasi Vendor
                                                    </h3>
                                                </div>
                                                <div class="card-body">
                                                    <div id="vendorInfo">
                                                        <?php 
                                                        $currentVendor = null;
                                                        foreach ($dropdownData['vendors'] as $vendor) {
                                                            if ($vendor['vendor_id'] == $purchase['vendor_id']) {
                                                                $currentVendor = $vendor;
                                                                break;
                                                            }
                                                        }
                                                        ?>
                                                        <?php if ($currentVendor): ?>
                                                            <div class="text-left">
                                                                <div class="vendor-detail-item">
                                                                    <strong>Nama Vendor:</strong><br>
                                                                    <strong class="text-primary"><?= htmlspecialchars($currentVendor['vendor_name']) ?></strong>
                                                                </div>
                                                                
                                                                <div class="vendor-detail-item">
                                                                    <strong>Vendor ID:</strong><br>
                                                                    <span class="text-muted"><?= htmlspecialchars($currentVendor['vendor_id']) ?></span>
                                                                </div>
                                                                
                                                                <?php if ($currentVendor['address']): ?>
                                                                    <div class="vendor-detail-item">
                                                                        <strong>Alamat:</strong><br>
                                                                        <div class="pl-2">
                                                                            <small class="text-muted">
                                                                                <i class="fas fa-map-marker-alt mr-1"></i>
                                                                                <?= htmlspecialchars($currentVendor['address']) ?>
                                                                            </small>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($currentVendor['contact_person'] || $currentVendor['phone'] || $currentVendor['email']): ?>
                                                                    <div class="vendor-detail-item">
                                                                        <strong>Contact Person:</strong><br>
                                                                        <div class="pl-2">
                                                                            <?php if ($currentVendor['contact_person']): ?>
                                                                                <div><i class="fas fa-user mr-1 text-muted"></i> <?= htmlspecialchars($currentVendor['contact_person']) ?></div>
                                                                            <?php endif; ?>
                                                                            <?php if ($currentVendor['phone']): ?>
                                                                                <div><i class="fas fa-phone mr-1 text-muted"></i> <?= htmlspecialchars($currentVendor['phone']) ?></div>
                                                                            <?php endif; ?>
                                                                            <?php if ($currentVendor['email']): ?>
                                                                                <div><i class="fas fa-envelope mr-1 text-muted"></i> <?= htmlspecialchars($currentVendor['email']) ?></div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="text-center text-muted py-4">
                                                                <i class="fas fa-store fa-2x mb-2"></i><br>
                                                                Data vendor tidak ditemukan
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor);?>">
                                        <i class="fas fa-save"></i> Simpan Perubahan
                                    </button>
                                    <a href="purchase_list.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Kembali
                                    </a>
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
        
        const poNumber = $('#po_number').val().trim();
        const vendorId = $('#vendor_id').val();
        const purchaseDate = $('#purchase_date').val();
        
        // Basic validation
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
        
        // Confirmation dialog
        Swal.fire({
            title: 'Update Purchase Order?',
            html: `Anda yakin ingin mengupdate data purchase order?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '<?= $themeColor === 'primary' ? '#007bff' : $themeColor ?>',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-save mr-2"></i> Ya, Update',
            cancelButtonText: '<i class="fas fa-times mr-2"></i> Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Submit form
                $('#purchaseForm').off('submit').submit();
            }
        });
    });

    // Format price on page load
    const priceInput = document.getElementById('price');
    if (priceInput && priceInput.value) {
        formatCurrency(priceInput);
    }

    // Initialize vendor info display
    $(document).ready(function() {
        const vendorId = $('#vendor_id').val();
        if (vendorId) {
            $('#vendor_id').trigger('change');
        }
        
        // Focus on first editable field
        $('#po_number').focus();
    });

})();
</script>

</body>
</html>

<?php 
include '../../includes/footer.php'; 
?>