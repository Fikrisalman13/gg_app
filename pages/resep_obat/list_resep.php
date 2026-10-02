<?php
// pages/resep_obat/list_resep.php
session_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

/// helper: ambil permission user untuk menu Asset (MenuId = 215)
function checkPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt !== false)
        sqlsrv_free_stmt($stmt);

    return $permissions;
}

// Ambil permission (dipakai untuk tombol "Tambah" dan fallback JS)
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 215);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Master Resep</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Master Resep</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title"><i class="fas fa-flask mr-1"></i> Daftar Master Resep</h3>
                    <div class="float-right">
                        <?php if ($permissions['CanAdd'] == 1): ?>
                            <a href="input_resep.php" class="btn btn-success btn-sm">
                                <i class="fas fa-plus"></i> Tambah Resep
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Date Filter Section -->
                    <div class="row mb-3 align-items-end resep-filter-row">
                        <div class="col-md-2">
                            <label for="startDate">Start Date:</label>
                            <input type="date" class="form-control" id="startDate" name="startDate">
                        </div>
                        <div class="col-md-2">
                            <label for="endDate">End Date:</label>
                            <input type="date" class="form-control" id="endDate" name="endDate">
                        </div>
                        <div class="col-md-3">
                            <label for="cusColorSearch">Cari Cus Color:</label>
                            <input type="search" class="form-control" id="cusColorSearch"
                                placeholder="Masukkan Cus Color..." autocomplete="off">
                        </div>
                        <div class="col-md-5 d-flex flex-wrap filter-actions">
                            <button id="btnFilter" class="btn btn-primary text-nowrap"><i class="fas fa-search"></i> Cari</button>
                            <button id="btnReset" class="btn btn-default ml-1 text-nowrap"><i class="fas fa-undo"></i> Reset</button>
                            <button id="btnExport" class="btn btn-success ml-1 text-nowrap"><i class="fas fa-file-excel"></i> Export Excel</button>
                        </div>
                    </div>

                    <div class="cus-color-summary mb-3" aria-live="polite">
                        <div class="cus-color-summary-item">
                            <span><i class="fas fa-palette mr-2"></i>Total Cus Color</span>
                            <strong id="totalCusColor">0</strong>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="resepTable" class="table table-bordered table-hover table-sm nowrap"
                            style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>No CP</th>
                                    <th>Kode Warna</th>
                                    <th>Color Name</th>
                                    <th>Cus Color</th>
                                    <th>Weight</th>
                                    <th>Plan Qty</th>
                                    <th>Deskripsi</th>
                                    <th>Status Resep</th>
                                    <th>Input Info</th>
                                    <th>Update Info</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="resepVariantManageModal" tabindex="-1" role="dialog"
    aria-labelledby="resepVariantManageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title" id="resepVariantManageModalLabel">
                    <i class="fas fa-layer-group mr-2"></i>Kelola Data Resep
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2">
                    Pilih data berdasarkan mesin, No CP, dan input info sebelum edit atau hapus.
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Mesin</th>
                                <th>No CP</th>
                                <th>Weight</th>
                                <th>Input Info</th>
                                <th>Update Info</th>
                                <th style="width:150px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="resepVariantManageRows"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet"
    href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<style>
    .status-master {
        color: #dc2626;
        font-style: italic;
        font-weight: 700;
    }

    .status-badge {
        display: inline-block;
        font-size: .75rem;
        font-weight: 600;
        padding: .25rem .6rem;
        border-radius: 4px;
    }

    .status-master-resep {
        background: #28a745;
        color: #fff;
    }

    .status-shading {
        background: #ffc107;
        color: #1f2d3d;
    }

    .status-top-paddry {
        background: #17a2b8;
        color: #fff;
    }

    .status-top-cpb {
        background: #6c757d;
        color: #fff;
    }

    .status-kestabilan {
        background: #7c3aed;
        color: #fff;
    }

    .status-default {
        background: #e3f2fd;
        color: #1976d2;
    }

    .resep-filter-row label {
        font-size: .82rem;
        font-weight: 600;
        margin-bottom: .35rem;
    }

    .filter-actions {
        align-items: center;
        gap: .35rem;
    }

    .filter-actions .btn {
        margin-left: 0 !important;
    }

    .cus-color-summary {
        display: grid;
        grid-template-columns: minmax(220px, 280px);
    }

    .cus-color-summary-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 54px;
        padding: .65rem .9rem;
        border-radius: 8px;
        background: #17a2b8;
        color: #fff;
        box-shadow: 0 2px 8px rgba(31, 45, 61, .14);
        font-size: .85rem;
        font-weight: 600;
    }

    .cus-color-summary-item strong {
        font-size: 1.4rem;
        line-height: 1;
        margin-left: 1rem;
    }

    @media (max-width: 767.98px) {
        .resep-filter-row > div {
            margin-bottom: .75rem;
        }

        .cus-color-summary {
            grid-template-columns: 1fr;
        }
    }
</style>
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
    $(document).ready(function () {
        // Pass Permissions to JS
        const canEdit = <?= $permissions['CanEdit'] ?>;
        const canDelete = <?= $permissions['CanDelete'] ?>;

        function escapeHtml(value) {
            return $('<div>').text(value == null ? '-' : String(value)).html();
        }

        function buildVariantActions(variant) {
            let actions = `<a href="view_resep.php?resep_id=${variant.id}" class="btn btn-info btn-sm" title="View"><i class="fas fa-eye"></i></a>`;
            if (canEdit == 1) {
                actions += ` <a href="input_resep.php?resep_id=${variant.id}" class="btn btn-warning btn-sm" title="Edit"><i class="fas fa-edit"></i></a>`;
            }
            if (canDelete == 1) {
                actions += ` <button class="btn btn-danger btn-sm btn-delete" data-id="${variant.id}" title="Hapus"><i class="fas fa-trash"></i></button>`;
            }
            return actions;
        }

        var table = $('#resepTable').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            ajax: {
                url: 'serverside_resep.php',
                type: 'POST',
                data: function (d) {
                    d.startDate = $('#startDate').val();
                    d.endDate = $('#endDate').val();
                    d.cusColor = $('#cusColorSearch').val();
                },
                dataSrc: function (json) {
                    $('#totalCusColor').text(Number(json.totalCusColor || 0).toLocaleString('id-ID'));
                    return json.data || [];
                },
                error: function (xhr, error, thrown) {
                    console.error('DataTables error:', xhr.responseText);
                    Swal.fire('Error', 'Gagal memuat data. Cek console.', 'error');
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                {
                    data: 'no_cp',
                    render: function (data, type, row) {
                        return `<b>${data}</b>`;
                    }
                },
                { data: 'kode_warna' },
                { data: 'color_name' },
                { data: 'cus_color' },
                { data: 'weight' },
                { data: 'plan_qty' },
                { data: 'color_desc' },
                {
                    data: 'status_resep_lipat',
                    className: 'text-center',
                    render: function (data) {
                        var label = (data == null ? '-' : String(data));
                        var lowerData = label.toLowerCase();
                        if (lowerData === 'master resep' || lowerData === 'master') {
                            return '<span class="status-badge status-master-resep">' + label + '</span>';
                        } else if (lowerData === 'shading') {
                            return '<span class="status-badge status-shading">' + label + '</span>';
                        } else if (lowerData === 'top paddry') {
                            return '<span class="status-badge status-top-paddry">' + label + '</span>';
                        } else if (lowerData === 'top cpb') {
                            return '<span class="status-badge status-top-cpb">' + label + '</span>';
                        } else if (lowerData === 'kestabilan') {
                            return '<span class="status-badge status-kestabilan">' + label + '</span>';
                        }
                        return '<span class="status-badge status-default">' + label + '</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row) {
                        return `<div><b>${row.created_by || '-'}</b></div><small class="text-muted">${row.created_at || '-'}</small>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row) {
                        return `<div><b>${row.updated_by || '-'}</b></div><small class="text-muted">${row.updated_at || '-'}</small>`;
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    render: function (data, type, row) {
                        const variants = Array.isArray(row.variants) ? row.variants : [];
                        if (variants.length > 1) {
                            return `<button class="btn btn-primary btn-sm btn-manage-variants" title="Pilih data yang akan dikelola">
                                <i class="fas fa-layer-group mr-1"></i> Kelola ${variants.length} Data
                            </button>`;
                        }

                        const variant = variants[0] || { id: data };
                        return buildVariantActions(variant);
                    }
                }
            ],
            order: [[9, 'desc']]
        });

        $('#btnFilter').click(function () {
            table.ajax.reload();
        });

        $('#cusColorSearch').on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                table.ajax.reload();
            }
        });

        $('#btnReset').click(function () {
            $('#startDate, #endDate, #cusColorSearch').val('');
            table.search('').ajax.reload();
        });

        // Handle Export Button
        $('#btnExport').click(function () {
            let startDate = $('#startDate').val();
            let endDate = $('#endDate').val();

            // Show loading animation
            Swal.fire({
                title: 'Mengekspor Data...',
                html: 'Mohon tunggu, sedang membuat file Excel Anda.<br><b>Proses ini mungkin memakan waktu beberapa saat untuk data yang besar.</b>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Use AJAX to download file
            let url = `export_resep_excel.php?startDate=${startDate}&endDate=${endDate}`;

            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.responseType = 'blob';

            xhr.onload = function () {
                Swal.close();

                if (this.status === 200) {
                    // Create download link
                    var blob = this.response;
                    var link = document.createElement('a');
                    link.href = window.URL.createObjectURL(blob);

                    // Extract filename from Content-Disposition header or use default
                    var contentDisposition = xhr.getResponseHeader('Content-Disposition');
                    var filename = 'Data_Resep_Detail_' + new Date().getTime() + '.xlsx';

                    if (contentDisposition) {
                        var filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
                        if (filenameMatch && filenameMatch[1]) {
                            filename = filenameMatch[1].replace(/['"]/g, '');
                        }
                    }

                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Ekspor Berhasil!',
                        text: 'File Excel Anda telah berhasil diunduh.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Ekspor Gagal',
                        text: 'Terjadi kesalahan saat mengekspor data. Silakan coba lagi.'
                    });
                }
            };

            xhr.onerror = function () {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Jaringan',
                    text: 'Gagal terhubung ke server. Silakan periksa koneksi Anda dan coba lagi.'
                });
            };

            xhr.ontimeout = function () {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Waktu Habis',
                    text: 'Proses ekspor memakan waktu terlalu lama. Silakan coba dengan rentang tanggal yang lebih kecil.'
                });
            };

            // Set timeout to 5 minutes (300000ms)
            xhr.timeout = 300000;

            xhr.send();
        });

        $('#resepTable tbody').on('click', '.btn-manage-variants', function () {
            let $tableRow = $(this).closest('tr');
            if ($tableRow.hasClass('child')) {
                $tableRow = $tableRow.prev('.parent');
            }
            const rowData = table.row($tableRow).data();
            const variants = rowData && Array.isArray(rowData.variants) ? rowData.variants : [];
            const rowsHtml = variants.map(function (variant) {
                const inputInfo = `<b>${escapeHtml(variant.created_by)}</b><br><small class="text-muted">${escapeHtml(variant.created_at)}</small>`;
                const updateInfo = `<b>${escapeHtml(variant.updated_by)}</b><br><small class="text-muted">${escapeHtml(variant.updated_at)}</small>`;
                return `<tr>
                    <td><span class="badge badge-info">${escapeHtml(variant.variant_name)}</span></td>
                    <td><b>${escapeHtml(variant.no_cp)}</b></td>
                    <td>${escapeHtml(variant.weight)}</td>
                    <td>${inputInfo}</td>
                    <td>${updateInfo}</td>
                    <td class="text-nowrap">${buildVariantActions(variant)}</td>
                </tr>`;
            }).join('');

            $('#resepVariantManageRows').html(rowsHtml);
            $('#resepVariantManageModalLabel').html(
                `<i class="fas fa-layer-group mr-2"></i>Kelola Data — ${escapeHtml(rowData.kode_warna)} / ${escapeHtml(rowData.cus_color)}`
            );
            $('#resepVariantManageModal').modal('show');
        });

        $(document).on('click', '.btn-delete', function () {
            var id = $(this).data('id');
            Swal.fire({
                title: 'Hapus Resep?',
                text: "Data tidak bisa dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'delete_resep.php',
                        type: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function (resp) {
                            if (resp.status === 'success') {
                                $('#resepVariantManageModal').modal('hide');
                                Swal.fire('Terhapus!', 'Data berhasil dihapus.', 'success');
                                table.ajax.reload(null, false);
                            } else {
                                Swal.fire('Gagal!', resp.message, 'error');
                            }
                        },
                        error: function (xhr) {
                            Swal.fire('Error', 'Gagal request hapus', 'error');
                        }
                    });
                }
            });
        });
    });
</script>