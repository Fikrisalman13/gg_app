<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../koneksi3.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
if (!$conn || !$conn3) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

// Permission Check
$groupId = $_SESSION['GroupId'];
$menuId  = 217; // sesuaikan MenuId halaman ini
$sql = "SELECT TOP 1 CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$stmt = sqlsrv_query($conn, $sql, array($groupId, $menuId));
$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
sqlsrv_free_stmt($stmt);
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    die("Anda tidak memiliki hak untuk melihat halaman ini.");
}

// Ambil parameter dari GET
$prdnmbr = isset($_GET['prdnmbr']) ? trim($_GET['prdnmbr']) : '';
$startdate = isset($_GET['startdate']) ? trim($_GET['startdate']) : date('Ymd', strtotime('-1 month'));
$enddate = isset($_GET['enddate']) ? trim($_GET['enddate']) : date('Ymd');
$fgusedtype = isset($_GET['fgusedtype']) ? trim($_GET['fgusedtype']) : 'ALL';
$results = [];

// Query data
if (isset($_GET['search'])) {
    try {
        $query = "
        WITH mat_sum AS (
            SELECT
                productionhdid,
                SUM(actualqtyprice) AS total_cost
            FROM pdresultmat
            GROUP BY productionhdid
        )
        SELECT
            h.prdnmbr,
            h.prodcode,
            h.prodname,
            t.labeljual,
            t.cuscolor,
            c.colorcode,
            c.colorname,
            r.rtgcode,
            r.rtgname,
            m.prodcode AS material_code,
            m.prodname AS material_name,
            h.prdqty,
            rt.prdstdqty,
            rh.resultqty AS routing_qty,
            m.matqty,
            u.uomcode,
            m.actualprice AS unit_price,
            m.actualqtyprice AS subtotal,
            ms.total_cost / rt.prdstdqty AS cost_meter,
            m.prodcf,
            b.bonweight,
            bh.bomcode,
            b.vlot,
            rt.upddate,
            rt.upduser,
            m.fgusedtype,
            m.accmsid
        FROM pdproductionhd h
        JOIN smprodtechdata t ON t.prodid = h.prodid
        JOIN pdcolorms c ON c.colormsid = h.colorid
        JOIN pdresultmat m ON m.productionhdid = h.productionhdid
        JOIN smuom u ON u.uomid = m.matuomid
        JOIN pdproductionrtg rt ON m.productionrtgid = rt.productionrtgid
        JOIN pdrtgms r ON rt.rtgmsid = r.rtgmsid
        LEFT JOIN pdresulthd rh ON rh.productionhdid = h.productionhdid
        LEFT JOIN pdbonreq b 
            ON h.productionhdid = b.productionhdid
            AND rt.productionrtgid = b.productionrtgid
        LEFT JOIN pdbomhd bh 
            ON b.bomhdid = bh.bomhdid
            AND m.bomhdid = bh.bomhdid
        JOIN mat_sum ms ON ms.productionhdid = h.productionhdid
        WHERE
            h.fgstatus = 'X'
            AND h.prdcenterid = 53
            AND h.prddate BETWEEN :startdate AND :enddate
        ";

        // Tambahkan filter fgusedtype jika bukan "ALL"
        if ($fgusedtype !== 'ALL') {
            if ($fgusedtype == 'ACCESSORY') {
                $query .= " AND m.accmsid IS NOT NULL";
            } else {
                $query .= " AND m.fgusedtype = :fgusedtype";
            }
        }

        // Tambahkan filter prdnmbr jika diisi
        if ($prdnmbr !== '') {
            $query .= " AND h.prdnmbr = :prdnmbr";
        }

        $query .= " ORDER BY rt.upddate";

        $stmt = $conn3->prepare($query);
        $stmt->bindParam(':startdate', $startdate);
        $stmt->bindParam(':enddate', $enddate);
        
        if ($fgusedtype !== 'ALL' && $fgusedtype !== 'ACCESSORY') {
            $stmt->bindParam(':fgusedtype', $fgusedtype);
        }
        
        if ($prdnmbr !== '') {
            $stmt->bindParam(':prdnmbr', $prdnmbr);
        }

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        die("Error executing query: " . $e->getMessage());
    }
}
?>

<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Material Usage Report</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">Material Usage</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">               
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                                <h3 class="card-title">Material Usage Report</h3>
                            </div>
                            <div class="card-body">
                                <!-- Filter Form -->
                                <form method="get" class="form-inline mb-3">
                                    <div class="form-group mr-2">
                                        <label for="prdnmbr" class="mr-2">CP Number:</label>
                                        <input type="text" id="prdnmbr" name="prdnmbr" class="form-control form-control-sm" 
                                            value="<?= htmlspecialchars($prdnmbr) ?>" placeholder="Masukkan nomor CP">
                                    </div>
                                    
                                    <div class="form-group mr-2">
                                        <label for="startdate" class="mr-2">Tanggal Mulai:</label>
                                        <input type="date" id="startdate" name="startdate" class="form-control form-control-sm" 
                                            value="<?= htmlspecialchars(date('Y-m-d', strtotime($startdate))) ?>">
                                    </div>
                                    
                                    <div class="form-group mr-2">
                                        <label for="enddate" class="mr-2">Tanggal Akhir:</label>
                                        <input type="date" id="enddate" name="enddate" class="form-control form-control-sm" 
                                            value="<?= htmlspecialchars(date('Y-m-d', strtotime($enddate))) ?>">
                                    </div>
                                    
                                    <div class="form-group mr-2">
                                        <label for="fgusedtype" class="mr-2">Jenis Material:</label>
                                        <select id="fgusedtype" name="fgusedtype" class="form-control form-control-sm">
                                            <option value="ALL" <?= $fgusedtype == 'ALL' ? 'selected' : '' ?>>Semua Jenis</option>
                                            <option value="G" <?= $fgusedtype == 'G' ? 'selected' : '' ?>>Obat</option>
                                            <option value="A" <?= $fgusedtype == 'A' ? 'selected' : '' ?>>Kain Grey</option>
                                            <option value="ACCESSORY" <?= $fgusedtype == 'ACCESSORY' ? 'selected' : '' ?>>Aksesoris</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" name="search" value="1" class="btn btn-<?php echo htmlspecialchars($themeColor);?> btn-sm">
                                        <i class="fas fa-search"></i> Tampilkan
                                    </button>
                                    
                                    <?php if (!empty($results)) : ?>
                                    <a href="export_excel_material_usage.php?prdnmbr=<?= urlencode($prdnmbr) ?>&startdate=<?= urlencode($startdate) ?>&enddate=<?= urlencode($enddate) ?>&fgusedtype=<?= urlencode($fgusedtype) ?>" 
                                        class="btn btn-success btn-sm ml-2">
                                        <i class="fas fa-file-excel"></i> Export Excel
                                    </a>
                                    <?php endif; ?>
                                </form>

                                <?php if (!empty($results)) : ?>
                                    <!-- Summary Information -->
                                    <div class="alert alert-info mb-3">
                                        <strong>Periode:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($startdate))) ?> - <?= htmlspecialchars(date('d/m/Y', strtotime($enddate))) ?>
                                        | <strong>Jenis Material:</strong> 
                                        <?php 
                                        if ($fgusedtype == 'ALL') {
                                            echo 'Semua Jenis';
                                        } elseif ($fgusedtype == 'G') {
                                            echo 'Obat';
                                        } elseif ($fgusedtype == 'A') {
                                            echo 'Kain Grey';
                                        } elseif ($fgusedtype == 'ACCESSORY') {
                                            echo 'Aksesoris';
                                        }
                                        ?>
                                        | <strong>Total Data:</strong> <?= count($results) ?> records
                                        <?php if ($prdnmbr !== '') : ?>
                                        | <strong>CP Number:</strong> <?= htmlspecialchars($prdnmbr) ?>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Data Table -->
                                    <div class="table-responsive" style="overflow-x: auto;">                 
                                        <table id="materialTable" class="table table-hover table-sm">
                                            <thead class="thead-light">
                                                <tr class="text-center align-middle">
                                                    <th>No</th>
                                                    <th>Update Date</th>
                                                    <th>CP Number</th>
                                                    <th>Product Code</th>
                                                    <th>Product Name</th>
                                                    <th>Label Jual</th>
                                                    <th>Customer Color</th>
                                                    <th>Color Code</th>
                                                    <th>Color Name</th>
                                                    <th>Routing Code</th>
                                                    <th>Routing Name</th>
                                                    <th>Material Code</th>
                                                    <th>Material Name</th>
                                                    <th>PRD Qty</th>
                                                    <th>PRD Std Qty</th>
                                                    <th>Routing Qty</th>
                                                    <th>Material Qty</th>
                                                    <th>UOM</th>
                                                    <th>Unit Price</th>
                                                    <th>Subtotal</th>
                                                    <th>Cost/Meter</th>
                                                    <th>PRODCF</th>
                                                    <th>BON Weight</th>
                                                    <th>BOM Code</th>
                                                    <th>VLOT</th>
                                                    <th>Update User</th>
                                                    <th>Jenis Material</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $no = 1;
                                            $total_prdqty = 0;
                                            $total_prdstdqty = 0;
                                            $total_routingqty = 0;
                                            $total_matqty = 0;
                                            $total_subtotal = 0;
                                            $total_bonweight = 0;
                                            $type_counts = [
                                                'G' => 0,
                                                'A' => 0,
                                                'ACCESSORY' => 0
                                            ];
                                            
                                            foreach ($results as $row) {
                                                $total_prdqty += $row['prdqty'];
                                                $total_prdstdqty += $row['prdstdqty'];
                                                $total_routingqty += $row['routing_qty'] ?? 0;
                                                $total_matqty += $row['matqty'];
                                                $total_subtotal += $row['subtotal'];
                                                $total_bonweight += $row['bonweight'] ?? 0;
                                                
                                                // Tentukan jenis material
                                                $material_type = 'Unknown';
                                                if ($row['accmsid'] !== null) {
                                                    $material_type = 'ACCESSORY';
                                                    $type_counts['ACCESSORY']++;
                                                } elseif ($row['fgusedtype'] == 'G') {
                                                    $material_type = 'G';
                                                    $type_counts['G']++;
                                                } elseif ($row['fgusedtype'] == 'A') {
                                                    $material_type = 'A';
                                                    $type_counts['A']++;
                                                }
                                                
                                                // Tentukan label jenis
                                                $type_label = '';
                                                $type_color = '';
                                                if ($material_type == 'G') {
                                                    $type_label = 'Obat';
                                                    $type_color = 'primary';
                                                } elseif ($material_type == 'A') {
                                                    $type_label = 'Kain Grey';
                                                    $type_color = 'success';
                                                } elseif ($material_type == 'ACCESSORY') {
                                                    $type_label = 'Aksesoris';
                                                    $type_color = 'warning';
                                                }
                                                
                                                echo "<tr>";
                                                echo "<td class='text-center'>".$no++."</td>";
                                                echo "<td class='text-center'>".($row['upddate'] ? date("d/m/Y", strtotime($row['upddate'])) : '')."</td>";
                                                echo "<td>".htmlspecialchars($row['prdnmbr'])."</td>";
                                                echo "<td>".htmlspecialchars($row['prodcode'])."</td>";
                                                echo "<td>".htmlspecialchars($row['prodname'])."</td>";
                                                echo "<td>".htmlspecialchars($row['labeljual'])."</td>";
                                                echo "<td>".htmlspecialchars($row['cuscolor'])."</td>";
                                                echo "<td>".htmlspecialchars($row['colorcode'])."</td>";
                                                echo "<td>".htmlspecialchars($row['colorname'])."</td>";
                                                echo "<td>".htmlspecialchars($row['rtgcode'])."</td>";
                                                echo "<td>".htmlspecialchars($row['rtgname'])."</td>";
                                                echo "<td>".htmlspecialchars($row['material_code'])."</td>";
                                                echo "<td>".htmlspecialchars($row['material_name'])."</td>";
                                                echo "<td class='text-right'>".number_format($row['prdqty'], 2)."</td>";
                                                echo "<td class='text-right'>".number_format($row['prdstdqty'], 2)."</td>";
                                                echo "<td class='text-right'>".($row['routing_qty'] ? number_format($row['routing_qty'], 2) : '-')."</td>";
                                                echo "<td class='text-right'>".number_format($row['matqty'], 4)."</td>";
                                                echo "<td class='text-center'>".htmlspecialchars($row['uomcode'])."</td>";
                                                echo "<td class='text-right'>".number_format($row['unit_price'], 2)."</td>";
                                                echo "<td class='text-right'>".number_format($row['subtotal'], 2)."</td>";
                                                echo "<td class='text-right'>".number_format($row['cost_meter'], 4)."</td>";
                                                echo "<td class='text-center'>".htmlspecialchars($row['prodcf'])."</td>";
                                                echo "<td class='text-right'>".($row['bonweight'] ? number_format($row['bonweight'], 4) : '-')."</td>";
                                                echo "<td>".htmlspecialchars($row['bomcode'])."</td>";
                                                echo "<td>".htmlspecialchars($row['vlot'])."</td>";
                                                echo "<td>".htmlspecialchars($row['upduser'])."</td>";
                                                echo "<td class='text-center'><span class='badge badge-".$type_color."'>".htmlspecialchars($type_label)."</span></td>";
                                                echo "</tr>";
                                            }
                                            ?>
                                            </tbody>
                                            
                                        </table>
                                    </div>
                                <?php else : ?>
                                    <?php if (isset($_GET['search'])) : ?>
                                        <div class="alert alert-warning">
                                            Data tidak ditemukan untuk periode <?= htmlspecialchars(date('d/m/Y', strtotime($startdate))) ?> hingga <?= htmlspecialchars(date('d/m/Y', strtotime($enddate))) ?>
                                            <?php if ($prdnmbr !== '') : ?>
                                                dengan CP Number: <strong><?= htmlspecialchars($prdnmbr) ?></strong>
                                            <?php endif; ?>
                                            <?php if ($fgusedtype !== 'ALL') : ?>
                                                dan jenis material: <strong>
                                                <?php 
                                                if ($fgusedtype == 'G') {
                                                    echo 'Obat';
                                                } elseif ($fgusedtype == 'A') {
                                                    echo 'Kain Grey';
                                                } elseif ($fgusedtype == 'ACCESSORY') {
                                                    echo 'Aksesoris';
                                                }
                                                ?>
                                                </strong>
                                            <?php endif; ?>
                                        </div>
                                    <?php else : ?>
                                        <div class="alert alert-info">
                                            Silakan masukkan filter dan klik "Tampilkan" untuk melihat data material usage
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<!-- ===================================================
    11. JAVASCRIPT LIBRARIES
======================================================= -->
<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script>
    $(document).ready(function() {
        $("#materialTable").DataTable({
            responsive: true,
            autoWidth: false,
            language: {
                processing: "Memproses...",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                zeroRecords: "Tidak ada data ditemukan",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                infoEmpty: "Tidak ada data tersedia",
                infoFiltered: "(disaring dari _MAX_ total data)",
                search: "Cari:",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Selanjutnya",
                    previous: "Sebelumnya"
                }
            },
            pageLength: 50,
            dom: 'Bfrtip',
            buttons: [
                'pageLength', 
                {
                    extend: 'excel',
                    text: 'Export to Excel',
                    className: 'btn btn-success btn-sm',
                    title: 'Material Usage Report'
                }
            ],
            order: [[1, 'asc']], // Sort by Update Date descending
            columnDefs: [
                { targets: [0], orderable: false }, // No column
                { targets: [13, 14, 15, 16, 18, 19, 20, 22], className: 'text-right' }, // Right align numeric columns
                { targets: [17, 21, 25, 26], className: 'text-center' } // Center align specific columns
            ],
            scrollX: true,
            fixedHeader: true
        });
    });
</script>