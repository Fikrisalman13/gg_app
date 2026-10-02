<?php
session_start();
require_once '../../koneksi.php';

if (!isset($_SESSION['UserId'])) {
    header("Location: ../../login.php");
    exit();
}

function submissionsHasColumn($conn, $column) {
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $sql = "SELECT 1 FROM sys.columns WHERE (object_id = OBJECT_ID('Form_Dynamic_Submissions') OR object_id = OBJECT_ID('dbo.Form_Dynamic_Submissions')) AND name = ?";
    $stmt = sqlsrv_query($conn, $sql, [$column]);
    $cache[$column] = ($stmt && sqlsrv_fetch_array($stmt)) ? true : false;
    if ($stmt) {
        sqlsrv_free_stmt($stmt);
    }

    return $cache[$column];
}

$function_exists_helper = function_exists('array_get_case_insensitive');
if (!$function_exists_helper) {
    function array_get_case_insensitive($array, $key) {
        if (isset($array[$key])) {
            return $array[$key];
        }
        foreach ($array as $k => $value) {
            if (strcasecmp($k, $key) === 0) {
                return $value;
            }
        }
        return null;
    }
}

$submissionsHasCreatedAt = submissionsHasColumn($conn, 'created_at');
$submissionsHasSubmissionId = submissionsHasColumn($conn, 'submission_id');
$submissionsHasIdColumn = submissionsHasColumn($conn, 'id');
$primaryKeyColumn = $submissionsHasSubmissionId ? 'submission_id' : ($submissionsHasIdColumn ? 'id' : null);

$templateSlug = isset($_GET['template_name']) ? trim($_GET['template_name']) : null;
$templateIdParam = $_GET['id'] ?? null; // legacy
if (!$templateSlug && !$templateIdParam) {
    die("Template tidak ditemukan.");
}

if ($templateSlug) {
    $sqlTemplate = "SELECT * FROM Form_Dynamic_Templates WHERE template_name = ?";
    $stmtTemplate = sqlsrv_query($conn, $sqlTemplate, [$templateSlug]);
} else {
    $sqlTemplate = "SELECT * FROM Form_Dynamic_Templates WHERE id = ?";
    $stmtTemplate = sqlsrv_query($conn, $sqlTemplate, [$templateIdParam]);
}
$template = sqlsrv_fetch_array($stmtTemplate, SQLSRV_FETCH_ASSOC);

if (!$template) {
    die("Template tidak ditemukan.");
}

$templateId = $template['id'];
$templateName = $template['template_name'];
$fields = json_decode($template['fields_json'], true);
$baseTemplateParams = $templateSlug ? ['template_name' => $templateSlug] : ['id' => $templateIdParam];
$startDateValue = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDateValue = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Adaptive threshold logic (Determine if table is 'wide')
$dynamicFieldsCount = 0;
foreach ($fields as $field) {
    if (!isset($field['item_type']) || $field['item_type'] === 'field') {
        $dynamicFieldsCount++;
    }
}
$isWideTable = $dynamicFieldsCount > 7; // More than 7 dynamic fields is considered "wide"
$dateFilterError = null;
$startDateSqlValue = null;
$endDateSqlValue = null;
$startDateObj = null;
$endDateObj = null;

if ($startDateValue !== '') {
    $startDateObj = DateTime::createFromFormat('Y-m-d', $startDateValue);
    if ($startDateObj) {
        $startDateSqlValue = (clone $startDateObj)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
    } else {
        $dateFilterError = "Tanggal awal tidak valid.";
    }
}

if ($endDateValue !== '') {
    $endDateObj = DateTime::createFromFormat('Y-m-d', $endDateValue);
    if ($endDateObj) {
        $endDateSqlValue = (clone $endDateObj)->setTime(23, 59, 59)->format('Y-m-d H:i:s');
    } else {
        $dateFilterError = $dateFilterError ?: "Tanggal akhir tidak valid.";
    }
}

if ($startDateObj && $endDateObj && $startDateObj > $endDateObj) {
    $dateFilterError = "Tanggal awal tidak boleh setelah tanggal akhir.";
    $startDateSqlValue = null;
    $endDateSqlValue = null;
}

if (!$submissionsHasCreatedAt && ($startDateValue !== '' || $endDateValue !== '')) {
    $dateFilterError = "Filter tanggal tidak dapat digunakan karena kolom tanggal tidak tersedia.";
    $startDateSqlValue = null;
    $endDateSqlValue = null;
}

$currentFilterParams = $baseTemplateParams;
if ($startDateValue !== '') {
    $currentFilterParams['start_date'] = $startDateValue;
}
if ($endDateValue !== '') {
    $currentFilterParams['end_date'] = $endDateValue;
}
$currentFilterQuery = http_build_query($currentFilterParams);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_submission_id'])) {
    if (!$primaryKeyColumn) {
        $_SESSION['error'] = "Fitur hapus tidak tersedia karena kolom kunci tidak ditemukan.";
        $redirectUrl = "submissions.php" . ($currentFilterQuery ? '?' . $currentFilterQuery : '');
        header("Location: " . $redirectUrl);
        exit();
    }

    $submissionId = (int)$_POST['delete_submission_id'];
    if ($submissionId > 0) {
        $filesToDelete = [];
        $sqlFetch = "SELECT submission_data FROM Form_Dynamic_Submissions WHERE {$primaryKeyColumn} = ? AND template_id = ?";
        $stmtFetch = sqlsrv_query($conn, $sqlFetch, [$submissionId, $templateId]);
        if ($stmtFetch && ($fetchRow = sqlsrv_fetch_array($stmtFetch, SQLSRV_FETCH_ASSOC))) {
            $submissionPayload = json_decode($fetchRow['submission_data'] ?? '', true);
            if (is_array($submissionPayload)) {
                foreach ($submissionPayload as $value) {
                    if (is_array($value) && ($value['type'] ?? '') === 'file') {
                        $fileName = $value['value'] ?? '';
                        if ($fileName) {
                            $filesToDelete[] = basename($fileName);
                        }
                    }
                }
            }
        }
        if ($stmtFetch) {
            sqlsrv_free_stmt($stmtFetch);
        }

        $sqlDelete = "DELETE FROM Form_Dynamic_Submissions WHERE {$primaryKeyColumn} = ? AND template_id = ?";
        $stmtDelete = sqlsrv_query($conn, $sqlDelete, [$submissionId, $templateId]);
        if ($stmtDelete) {
            if (!empty($filesToDelete)) {
                $uploadDirAbsolute = realpath(__DIR__ . '/../../uploads/dynamic_forms');
                foreach ($filesToDelete as $fileName) {
                    if ($uploadDirAbsolute) {
                        $filePath = $uploadDirAbsolute . DIRECTORY_SEPARATOR . $fileName;
                        if (is_file($filePath)) {
                            @unlink($filePath);
                        }
                    }
                }
            }
            $_SESSION['success'] = "Data berhasil dihapus.";
            sqlsrv_free_stmt($stmtDelete);
        } else {
            $_SESSION['error'] = "Gagal menghapus data.";
        }
    } else {
        $_SESSION['error'] = "ID data tidak valid.";
    }
    $redirectUrl = "submissions.php" . ($currentFilterQuery ? '?' . $currentFilterQuery : '');
    header("Location: " . $redirectUrl);
    exit();
}

include '../../includes/header.php';
include '../../includes/sidebar.php';

$themeColor = $_SESSION['Theme'] ?? 'primary';
$deleteActionBase = "submissions.php" . ($currentFilterQuery ? '?' . $currentFilterQuery : '');
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

<style>
    #table-submissions.table,
    #table-submissions.table thead th,
    #table-submissions.table tbody td {
        background-color: #fff !important;
        color: #000 !important;
    }

    #table-submissions.table tbody tr:nth-child(odd),
    #table-submissions.table tbody tr:nth-child(even) {
        background-color: #fff !important;
    }

    /* Loading State Styles */
    .table-loading-container {
        position: relative;
        min-height: 200px;
    }
    .table-loading-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(255,255,255,0.8);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 10;
        transition: opacity 0.3s ease;
    }
    #table-submissions-wrapper {
        opacity: 0;
        transition: opacity 0.4s ease;
    }
    #table-submissions-wrapper.is-ready {
        opacity: 1;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><?php echo htmlspecialchars($template['template_name']); ?></h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php 
            $successMessage = null;
            if (isset($_SESSION['success'])) {
                $successMessage = $_SESSION['success'];
                unset($_SESSION['success']);
            }
            ?>

            <div class="card card-<?php echo htmlspecialchars($themeColor); ?>">
                <div class="card-header">
                    <h3 class="card-title">Daftar Data</h3>
                    <div class="card-tools">
                        <a href="form.php?template_name=<?php echo urlencode($templateName); ?>" class="btn btn-success btn-sm float-right ml-2">
                            <i class="fas fa-plus"></i> Isi Form Lagi
                        </a>
                        <a href="list_templates.php" class="btn btn-sm ">
                            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                        </a>
                    </div>
                </div>
                <div class="card-body table-responsive table-loading-container">
                    <div id="table-loader" class="table-loading-overlay">
                        <div class="spinner-border text-primary" role="status"></div>
                        <span class="mt-2 text-muted">Menganalisa tabel...</span>
                    </div>

                    <div id="table-submissions-wrapper">
                    <form class="form-inline flex-wrap mb-2" method="GET" action="submissions.php">
                        <?php foreach ($baseTemplateParams as $key => $value): ?>
                            <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                        <?php endforeach; ?>
                        <div class="form-group mr-2 mb-1">
                            <label for="start_date" class="mr-1 mb-0" style="font-size: 0.875rem;">Tanggal Awal</label>
                            <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDateValue); ?>">
                        </div>
                        <div class="form-group mr-2 mb-1">
                            <label for="end_date" class="mr-1 mb-0" style="font-size: 0.875rem;">Tanggal Akhir</label>
                            <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDateValue); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm mb-1 mr-1">
                            <i class="fas fa-filter"></i> Terapkan Filter
                        </button>
                        <a href="submissions.php?<?php echo htmlspecialchars(http_build_query($baseTemplateParams)); ?>" class="btn btn-secondary btn-sm mb-1 mr-1">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                        <a href="export_submissions_excel.php?<?php echo htmlspecialchars($currentFilterQuery); ?>" target="_blank" class="btn btn-success btn-sm mb-1 mr-1">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </a>
                        <a href="export_submissions_pdf.php?<?php echo htmlspecialchars($currentFilterQuery); ?>" target="_blank" class="btn btn-danger btn-sm mb-1">
                            <i class="fas fa-file-pdf"></i> Generate PDF
                        </a>
                    </form>

                    <?php if ($dateFilterError): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($dateFilterError); ?>
                        </div>
                    <?php endif; ?>

                    <table id="table-submissions" class="table table-bordered table-striped nowrap">
                        <thead>
                            <tr>
                                <th>No</th>
                                <?php foreach ($fields as $field): ?>
                                    <?php if (!isset($field['item_type']) || $field['item_type'] === 'field'): ?>
                                        <th class="nowrap"><?php echo htmlspecialchars($field['label']); ?></th>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <th>User</th>
                                <th>Tanggal</th>
                                <?php if (isset($_SESSION['GroupId']) && $_SESSION['GroupId'] == 1): ?>
                                    <th>Aksi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $orderBy = $submissionsHasCreatedAt ? " ORDER BY created_at DESC" : " ORDER BY submission_id DESC";
                            $whereClauses = ["template_id = ?"];
                            $sqlParams = [$templateId];
                            if ($submissionsHasCreatedAt) {
                                if ($startDateSqlValue) {
                                    $whereClauses[] = "created_at >= ?";
                                    $sqlParams[] = $startDateSqlValue;
                                }
                                if ($endDateSqlValue) {
                                    $whereClauses[] = "created_at <= ?";
                                    $sqlParams[] = $endDateSqlValue;
                                }
                            }
                            $whereSql = implode(' AND ', $whereClauses);
                            $sqlSub = "SELECT * FROM Form_Dynamic_Submissions WHERE " . $whereSql . $orderBy;
                            $stmtSub = sqlsrv_query($conn, $sqlSub, $sqlParams);
                            $no = 1;
                            if ($stmtSub) {
                                while ($row = sqlsrv_fetch_array($stmtSub, SQLSRV_FETCH_ASSOC)) {
                                    $data = json_decode($row['submission_data'], true);
                                    echo "<tr>";
                                    echo "<td>" . $no++ . "</td>";
                                    foreach ($fields as $field) {
                                        // Skip non-field items (like examples) to stay in sync with table headers
                                        if (isset($field['item_type']) && $field['item_type'] !== 'field') {
                                            continue;
                                        }
                                        
                                        $label = $field['label'];
                                        $valObj = $data[$label] ?? null;
                                        
                                        echo "<td>";
                                        if ($valObj) {
                                            if ($valObj['type'] === 'file' && !empty($valObj['value'])) {
                                                $path = "../../uploads/dynamic_forms/" . $valObj['value'];
                                                echo "<a href='$path' target='_blank' class='btn btn-xs btn-outline-info'><i class='fas fa-file'></i> " . htmlspecialchars($valObj['original_name'] ?? 'Lihat') . "</a>";
                                            } elseif ($valObj['type'] === 'color' && !empty($valObj['value'])) {
                                                $colorVal = htmlspecialchars($valObj['value']);
                                                echo "<div style='display:flex;align-items:center;gap:6px;'>
                                                        <span style='display:inline-block;width:22px;height:22px;border-radius:5px;background:{$colorVal};border:1px solid rgba(0,0,0,0.15);flex-shrink:0;'></span>
                                                        <span style='font-size:0.85rem;color:#555;font-family:monospace;'>{$colorVal}</span>
                                                      </div>";
                                            } else {
                                                $val = $valObj['value'] ?? '';
                                                if (is_array($val)) {
                                                    echo htmlspecialchars(implode(', ', $val));
                                                } else {
                                                    echo htmlspecialchars($val);
                                                }
                                            }
                                        }
                                        echo "</td>";
                                    }
                                    echo "<td>" . htmlspecialchars($row['created_by']) . "</td>";
                                    $createdAtLabel = '-';
                                    if ($submissionsHasCreatedAt && !empty($row['created_at'])) {
                                        $createdAtLabel = $row['created_at']->format('d-m-Y H:i');
                                    } elseif (!empty($row['created_at'])) {
                                        // Be defensive in case column exists with different casing
                                        if ($row['created_at'] instanceof DateTime) {
                                            $createdAtLabel = $row['created_at']->format('d-m-Y H:i');
                                        } elseif (is_string($row['created_at'])) {
                                            $createdAtLabel = $row['created_at'];
                                        }
                                    }
                                     echo "<td>" . htmlspecialchars($createdAtLabel) . "</td>";
                                    
                                    if (isset($_SESSION['GroupId']) && $_SESSION['GroupId'] == 1) {
                                        $rowPrimaryValue = $primaryKeyColumn ? array_get_case_insensitive($row, $primaryKeyColumn) : null;
                                        if ($rowPrimaryValue !== null) {
                                            $rowPrimaryValue = (int)$rowPrimaryValue;
                                        }
                                        echo "<td>";
                                        if ($rowPrimaryValue) {
                                            echo "<div class='d-flex' style='gap: 5px;'>";
                                            // Edit Button
                                            $editUrl = "form.php?" . http_build_query(array_merge($baseTemplateParams, ['edit_submission_id' => $rowPrimaryValue]));
                                            echo "<a href='" . htmlspecialchars($editUrl) . "' class='btn btn-sm btn-primary' title='Edit' style='width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;'>";
                                            echo "<i class='fas fa-edit'></i>";
                                            echo "</a>";

                                            // Delete Button
                                            echo "<form method='POST' action='" . htmlspecialchars($deleteActionBase) . "' class='d-inline-block form-delete-submission'>";
                                            echo "<input type='hidden' name='delete_submission_id' value='" . $rowPrimaryValue . "'>";
                                            echo "<button type='submit' class='btn btn-sm btn-danger' title='Hapus' style='width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;'>";
                                            echo "<i class='fas fa-trash'></i>";
                                            echo "</button>";
                                            echo "</form>";
                                            echo "</div>";
                                        } else {
                                            echo "<span class='text-muted'>Tidak tersedia</span>";
                                        }
                                        echo "</td>";
                                    }
                                    echo "</tr>";
                                }
                                sqlsrv_free_stmt($stmtSub);
                            }
                            ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jszip/jszip.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/pdfmake/pdfmake.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/pdfmake/vfs_fonts.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.css">
<!-- SweetAlert2 JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(function () {
    var isWideTable = <?php echo $isWideTable ? 'true' : 'false'; ?>;
    
    var table = $("#table-submissions").DataTable({
        "responsive": !isWideTable, // Dynamic: Responsive for narrow tables, ScrollX focus for wide ones
        "lengthChange": true, 
        "autoWidth": false,
        "scrollX": true,
        "language": {
            "processing": "Memproses...",
            "lengthMenu": "Tampilkan _MENU_ data per halaman",
            "zeroRecords": "Tidak ada data ditemukan",
            "info": "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            "infoEmpty": "Tidak ada data tersedia",
            "infoFiltered": "(disaring dari _MAX_ total data)",
            "search": "Cari:",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            }
        },
        "initComplete": function(settings, json) {
            // Callback once table is ready
            var $this = $(this);
            setTimeout(function() {
                // Adjust alignment
                table.columns.adjust();
                
                // Show table, hide loader
                $("#table-loader").css('opacity', 0);
                setTimeout(function() {
                    $("#table-loader").addClass('d-none');
                    $("#table-submissions-wrapper").addClass('is-ready');
                }, 300);
            }, 500); // Small delay to ensure calculation is accurate
        }
    });

    table.buttons().container().appendTo('#table-submissions_wrapper .col-md-6:eq(0)');

    // Adjust table columns when sidebar is toggled
    $(document).on('collapsed.lte.pushmenu shown.lte.pushmenu', function() {
        setTimeout(function() {
            table.columns.adjust().responsive.recalc();
        }, 300);
    });

    // Also adjust on window resize
    $(window).on('resize', function() {
        table.columns.adjust();
    });
    
    const hasSwal = typeof Swal !== 'undefined';

    // Show success message with SweetAlert2 (fallback to alert if unavailable)
    <?php if ($successMessage): ?>
    if (hasSwal) {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: '<?php echo addslashes($successMessage); ?>',
            confirmButtonColor: '#28a745',
            timer: 3000,
            timerProgressBar: true
        });
    } else {
        alert('Berhasil!\n<?php echo addslashes($successMessage); ?>');
    }
    <?php endif; ?>

    $(document).on('submit', '.form-delete-submission', function (e) {
        var form = this;
        var submissionId = $(form).find('input[name="delete_submission_id"]').val() || '-';

        if (!hasSwal) {
            if (!confirm('Hapus Data Ini ?')) {
                e.preventDefault();
            }
            return;
        }

        e.preventDefault();
        Swal.fire({
            title: 'Hapus Data?',
            text: 'Data akan dihapus permanen.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
</script>
