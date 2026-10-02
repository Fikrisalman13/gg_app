<?php
// pages/resep_obat/settings_resep.php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Helper to clean currency input
    function cleanCurrency($str) {
        $str = str_replace(['Rp', ' ', '.', ','], '', $str); 
        // Note: We remove Comma too because we assume integer input for Limits usually, 
        // or standard format. If user types 10.000,00 -> 1000000. 
        // Let's be safer: Remove 'Rp', ' ', '.' then replace ',' with '.'?
        // Actually for limits, integer is common. Let's just strip non-numeric/dash.
        if (strpos($str, '-') !== false && strlen($str) < 5) return null; // Logic for '-'
        
        // Remove Rp, dots, spaces.
        $clean = preg_replace('/[^0-9,-]/', '', $str); 
        return ($clean === '' || $clean === '-') ? null : floatval($clean);
    }

    $min_cost = cleanCurrency($_POST['min_cost'] ?? '');
    $max_cost = cleanCurrency($_POST['max_cost'] ?? '');
    
    // Process Category Limits
    $cat_limits = [];
    if (isset($_POST['cat_name']) && is_array($_POST['cat_name'])) {
        for ($i = 0; $i < count($_POST['cat_name']); $i++) {
            $cat = $_POST['cat_name'][$i];
            $lim = $_POST['cat_limit'][$i];
            
            // Clean limit: Allow decimals, no currency
            $lim = floatval($lim);
            if ($cat && $lim > 0) {
                 $cat_limits[$cat] = $lim;
            }
        }
    }
    $cat_limits_json = json_encode($cat_limits);
    
    $user = $_SESSION['UserName'];
    $now = date('Y-m-d H:i:s');
    
    // Update (singleton table, update all/top 1)
    $sql = "UPDATE dbo.resep_config SET min_cost = ?, max_cost = ?, category_limits = ?, updated_at = ?, updated_by = ?";
    $params = [$min_cost, $max_cost, $cat_limits_json, $now, $user];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt) {
        $success = "Pengaturan berhasil disimpan.";
    } else {
        $error = "Gagal menyimpan: " . print_r(sqlsrv_errors(), true);
    }
}

// Fetch Current Settings
$curr = ['min_cost' => null, 'max_cost' => null];
$stmt = sqlsrv_query($conn, "SELECT top 1 * FROM dbo.resep_config");
if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $curr = $row;
}
$curr_limits = json_decode($curr['category_limits'] ?? '{}', true) ?: [];

// Fetch Categories from Master
$categories = [];
$stmtCat = sqlsrv_query($conn, "SELECT DISTINCT group_obat FROM dbo.resep_master_obat WHERE group_obat IS NOT NULL AND group_obat != '' ORDER BY group_obat");
while ($stmtCat && $rC = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
    $categories[] = $rC['group_obat'];
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>Pengaturan Limit Biaya</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="list_resep.php">Resep</a></li>
                        <li class="breadcrumb-item active">Settings</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <form method="post">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card card-<?= htmlspecialchars($themeColor); ?>">
                        <div class="card-header bg-<?= htmlspecialchars($themeColor); ?>">
                            <h3 class="card-title text-white">Limit Biaya (Cost)</h3>
                        </div>
                            <div class="card-body">
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Isi dengan angka atau tanda <b>-</b> jika tidak terbatas.
                                </div>

                                <div class="form-group">
                                    <label>Min Cost</label>
                                    <input type="text" class="form-control currency-input" name="min_cost" 
                                           value="<?= is_null($curr['min_cost']) ? '-' : number_format($curr['min_cost'], 0, ',', '.') ?>" 
                                           placeholder="Contoh: 10000 atau -">
                                </div>
                                <div class="form-group">
                                    <label>Max Cost</label>
                                    <input type="text" class="form-control currency-input" name="max_cost" 
                                           value="<?= is_null($curr['max_cost']) ? '-' : number_format($curr['max_cost'], 0, ',', '.') ?>" 
                                           placeholder="Contoh: 150000 atau -">
                                </div>
                            </div>
                            <!-- 
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
                                <a href="list_resep.php" class="btn btn-default float-right">Kembali</a>
                            </div> 
                            -->
                            <!-- Removed duplicate button per user request -->
                    </div>
                </div>
                    
                    <div class="col-md-6">
                        <div class="card card-bg-<?= $themeColor ?>">
                            <div class="card-header bg-<?= $themeColor ?>">
                                <h3 class="card-title text-white">Limit CF per Category</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-sm btn-success" id="btnAddLimit"><i class="fas fa-plus"></i> Tambah Limit</button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm" id="tblLimits">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th style="width: 120px;">Max Total CF</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyLimits">
                                        <?php if (!empty($curr_limits)): ?>
                                            <?php foreach ($curr_limits as $cat => $lim): ?>
                                            <tr>
                                                <td>
                                                    <select class="form-control form-control-sm" name="cat_name[]" required>
                                                        <option value="">- Pilih -</option>
                                                        <?php foreach ($categories as $c): ?>
                                                            <option value="<?= htmlspecialchars($c) ?>" <?= $c == $cat ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control form-control-sm" name="cat_limit[]" value="<?= $lim ?>" required>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-danger btn-xs btn-remove-limit"><i class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                               
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <div class="row justify-content-center mb-4">
                    <div class="col-md-12 text-center">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Simpan Pengaturan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- SweetAlert2 -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    // Show SweetAlert if PHP set success/error
    <?php if(isset($success)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: '<?= addslashes($success) ?>',
            timer: 2000,
            showConfirmButton: false
        });
    <?php endif; ?>

    <?php if(isset($error)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: '<?= addslashes($error) ?>'
        });
    <?php endif; ?>

    function formatRupiah(angka) {
        if (!angka) return '';
        if (angka.toString() === '-') return '-';
        var number_string = angka.toString().replace(/[^,\d-]/g, '').toString(),
            split   = number_string.split(','),
            sisa    = split[0].length % 3,
            rupiah  = split[0].substr(0, sisa),
            ribuan  = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        return 'Rp ' + rupiah;
    }
    
    // Auto format on load
    $('.currency-input').each(function() {
        let val = $(this).val();
        if(val && val !== '-') {
            $(this).val(formatRupiah(val));
        }
    });

    // Format on type
    $('.currency-input').on('keyup', function(e) {
        let val = $(this).val();
        if (val === '-') return; 
        $(this).val(formatRupiah(val));
    });
    
    // Add new limit row
    $('#btnAddLimit').click(function() {
        let newRow = `<tr>
            <td>
                <select class="form-control form-control-sm" name="cat_name[]" required>
                    <option value="">- Pilih -</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <input type="number" step="0.01" class="form-control form-control-sm" name="cat_limit[]" required>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-xs btn-remove-limit"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
        $('#tbodyLimits').append(newRow);
    });
    
    // Remove limit row
    $(document).on('click', '.btn-remove-limit', function() {
        $(this).closest('tr').remove();
    });
});
</script>
