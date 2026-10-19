<?php
// pages/ppc_guidance_v2/edit_otomatis/index.php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

require_once __DIR__ . '/../../../koneksi.php';
$themeColor = $_SESSION['Theme'] ?? 'primary';

include '../../../includes/header.php';
include '../../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <!-- Header Content -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark font-weight-bold">
                        <i class="fas fa-magic text-primary mr-2"></i>Edit Otomatis Resep Obat
                    </h1>
                    <p class="text-muted text-sm mb-0">
                        Sinkronisasi urutan routing ProInt (<code>rtgseq ASC</code>) dan refresh item resep obat secara otomatis.
                    </p>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="../resep_obat/list_resep.php">Resep</a></li>
                        <li class="breadcrumb-item active">Edit Otomatis</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">

            <!-- Card Utama -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> text-white d-flex align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold mb-0">
                        <i class="fas fa-list-check mr-2"></i>Daftar Resep Layak Edit Otomatis
                    </h3>
                    <div class="card-tools">
                        <a href="../resep_obat/list_resep.php" class="btn btn-outline-light btn-sm mr-2 shadow-sm">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali ke List Resep
                        </a>
                        <button type="button" class="btn btn-warning btn-sm font-weight-bold shadow-sm" id="btnBulkEdit" disabled>
                            <i class="fas fa-magic mr-1"></i> Edit Otomatis Terpilih (<span id="selectedCount">0</span>)
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Section Filter -->
                    <div class="filter-box mb-4 p-3 rounded" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                        <div class="row align-items-end">
                            <div class="col-md-3 mb-2">
                                <label class="text-xs font-weight-bold text-uppercase text-secondary">Status Resep</label>
                                <select class="form-control form-control-sm select2" id="filterStatus" multiple="multiple" style="width:100%;">
                                    <option value="Shading" selected>Shading</option>
                                    <option value="Master Resep" selected>Master Resep</option>
                                    <option value="Kesetabilan" selected>Kesetabilan / Kestabilan</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="text-xs font-weight-bold text-uppercase text-secondary">Tanggal Mulai</label>
                                <input type="date" class="form-control form-control-sm" id="filterStartDate">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="text-xs font-weight-bold text-uppercase text-secondary">Tanggal Akhir</label>
                                <input type="date" class="form-control form-control-sm" id="filterEndDate">
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="text-xs font-weight-bold text-uppercase text-secondary">Pencarian Cepat</label>
                                <input type="text" class="form-control form-control-sm" id="quickSearch" placeholder="No CP, Kode Warna, Cus Color...">
                            </div>
                            <div class="col-md-2 mb-2 d-flex">
                                <button type="button" class="btn btn-primary btn-sm flex-fill mr-1 shadow-sm font-weight-bold" id="btnApplyFilter">
                                    <i class="fas fa-filter mr-1"></i> Filter
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm" id="btnResetFilter" title="Reset Filter">
                                    <i class="fas fa-undo"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="summary-cards-container mb-3">
                        <div class="summary-card stat-shading" data-status="Shading" title="Klik untuk filter Shading">
                            <div class="stat-info">
                                <span class="stat-title">Shading</span>
                                <h4 class="stat-number" id="summaryShading">0</h4>
                            </div>
                            <div class="stat-icon"><i class="fas fa-palette"></i></div>
                        </div>
                        <div class="summary-card stat-master" data-status="Master Resep" title="Klik untuk filter Master Resep">
                            <div class="stat-info">
                                <span class="stat-title">Master Resep</span>
                                <h4 class="stat-number" id="summaryMasterResep">0</h4>
                            </div>
                            <div class="stat-icon"><i class="fas fa-star"></i></div>
                        </div>
                        <div class="summary-card stat-kestabilan" data-status="Kesetabilan" title="Klik untuk filter Kesetabilan">
                            <div class="stat-info">
                                <span class="stat-title">Kesetabilan</span>
                                <h4 class="stat-number" id="summaryKesetabilan">0</h4>
                            </div>
                            <div class="stat-icon"><i class="fas fa-balance-scale"></i></div>
                        </div>
                        <div class="summary-card stat-total active" data-status="all" title="Klik untuk tampilkan semua">
                            <div class="stat-info">
                                <span class="stat-title">Total Resep Terfilter</span>
                                <h4 class="stat-number" id="summaryTotal">0</h4>
                            </div>
                            <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                        </div>
                    </div>

                    <!-- Informational Callout -->
                    <div class="alert alert-light border d-flex align-items-center py-2 px-3 mb-3 text-sm" style="border-left: 4px solid #3b82f6 !important; background: #eff6ff;">
                        <i class="fas fa-info-circle text-primary mr-2" style="font-size: 1.2rem;"></i>
                        <div>
                            Daftar di bawah memfilter resep dengan <strong>No CP valid</strong> dan berstatus <strong>Shading, Master Resep, atau Kestabilan</strong>.
                            Klik <strong>"Edit Otomatis"</strong> pada baris untuk 1 data, atau centang kotak dan klik <strong>"Edit Otomatis Terpilih"</strong> untuk proses masal.
                        </div>
                    </div>

                    <!-- Table Responsive -->
                    <div class="table-responsive">
                        <table id="tableAutoEdit" class="table table-bordered table-hover table-striped table-sm text-sm w-100">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center" style="width: 35px;">
                                        <input type="checkbox" id="checkAll" title="Pilih Semua Halaman Ini">
                                    </th>
                                    <th class="text-center" style="width: 45px;">No</th>
                                    <th>No CP</th>
                                    <th>Kode Warna</th>
                                    <th>Color Name</th>
                                    <th>Cus Color</th>
                                    <th>Status Resep</th>
                                    <th>Routing Saat Ini</th>
                                    <th class="text-center" style="width: 75px;">Item Obat</th>
                                    <th>Terakhir Update</th>
                                    <th class="text-center" style="width: 140px;">Aksi</th>
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

<?php include '../../../includes/footer.php'; ?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- Select2 CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">

<style>
    .summary-cards-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 0.85rem;
    }
    .summary-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.85rem 1.15rem;
        border-radius: 10px;
        color: #fff;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        border: 2px solid transparent;
    }
    .summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 14px rgba(0,0,0,0.12);
        filter: brightness(1.05);
    }
    .summary-card.active {
        border-color: #1e293b;
        transform: translateY(-2px);
        box-shadow: 0 6px 14px rgba(0,0,0,0.16);
    }
    .stat-shading { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .stat-master { background: linear-gradient(135deg, #10b981, #059669); }
    .stat-kestabilan { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
    .stat-total { background: linear-gradient(135deg, #1e293b, #0f172a); }

    .stat-info .stat-title {
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.9;
        display: block;
    }
    .stat-info .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
        line-height: 1.2;
    }
    .stat-icon {
        font-size: 1.9rem;
        opacity: 0.35;
    }

    .badge-status-shading { background-color: #fef3c7; color: #92400e; font-weight: 600; border: 1px solid #fde68a; }
    .badge-status-master { background-color: #d1fae5; color: #065f46; font-weight: 600; border: 1px solid #a7f3d0; }
    .badge-status-kestabilan { background-color: #ede9fe; color: #5b21b6; font-weight: 600; border: 1px solid #ddd6fe; }

    .btn-auto-edit-row {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: #fff;
        border: none;
        font-weight: 600;
        transition: all 0.2s;
    }
    .btn-auto-edit-row:hover {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(37,99,235,0.3);
    }

    .row-selected {
        background-color: #eff6ff !important;
    }
</style>

<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- Select2 JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<!-- SweetAlert2 -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    // Inisialisasi Select2
    $('#filterStatus').select2({
        theme: 'bootstrap4',
        placeholder: 'Pilih Status'
    });

    let selectedIds = new Set();

    function updateBulkButton() {
        const count = selectedIds.size;
        $('#selectedCount').text(count);
        if (count > 0) {
            $('#btnBulkEdit').prop('disabled', false).removeClass('btn-secondary').addClass('btn-warning');
        } else {
            $('#btnBulkEdit').prop('disabled', true).removeClass('btn-warning').addClass('btn-secondary');
        }
    }

    // Inisialisasi DataTable Server-Side
    const table = $('#tableAutoEdit').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        ajax: {
            url: 'serverside.php',
            type: 'POST',
            data: function(d) {
                d.statusFilter = $('#filterStatus').val() || [];
                d.startDate = $('#filterStartDate').val() || '';
                d.endDate = $('#filterEndDate').val() || '';
                d.quickSearch = $('#quickSearch').val() || '';
            },
            dataSrc: function(json) {
                // Update ringkasan kartu status
                if (json.statusSummary) {
                    $('#summaryShading').text(json.statusSummary.shading.toLocaleString());
                    $('#summaryMasterResep').text(json.statusSummary.master_resep.toLocaleString());
                    $('#summaryKesetabilan').text(json.statusSummary.kesetabilan.toLocaleString());
                    $('#summaryTotal').text(json.statusSummary.total.toLocaleString());
                }
                return json.data;
            }
        },
        columns: [
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                render: function(data, type, row) {
                    const isChecked = selectedIds.has(data) ? 'checked' : '';
                    return `<input type="checkbox" class="row-checkbox" value="${data}" data-cp="${row.no_cp}" ${isChecked}>`;
                }
            },
            {
                data: 'no',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle'
            },
            {
                data: 'no_cp',
                className: 'align-middle font-weight-bold',
                render: function(data, type, row) {
                    return `<span class="badge badge-light border text-dark font-weight-bold p-1">${data}</span>`;
                }
            },
            {
                data: 'kode_warna',
                className: 'align-middle'
            },
            {
                data: 'color_name',
                className: 'align-middle'
            },
            {
                data: 'cus_color',
                className: 'align-middle'
            },
            {
                data: 'status_resep_lipat',
                className: 'align-middle text-center',
                render: function(data) {
                    const s = (data || '').toLowerCase();
                    if (s === 'shading') {
                        return `<span class="badge badge-status-shading px-2 py-1"><i class="fas fa-palette mr-1"></i>${data}</span>`;
                    } else if (s === 'master resep' || s === 'master') {
                        return `<span class="badge badge-status-master px-2 py-1"><i class="fas fa-star mr-1"></i>${data}</span>`;
                    } else if (s === 'kestabilan' || s === 'kesetabilan') {
                        return `<span class="badge badge-status-kestabilan px-2 py-1"><i class="fas fa-balance-scale mr-1"></i>${data}</span>`;
                    }
                    return `<span class="badge badge-secondary px-2 py-1">${data}</span>`;
                }
            },
            {
                data: 'rtg_code',
                className: 'align-middle',
                render: function(data, type, row) {
                    let desc = data || '-';
                    if (row.mesin && row.mesin !== '-') {
                        desc += `<br><small class="text-muted"><i class="fas fa-cogs mr-1"></i>${row.mesin}</small>`;
                    }
                    return desc;
                }
            },
            {
                data: 'detail_count',
                className: 'align-middle text-center',
                render: function(data) {
                    return `<span class="badge badge-pill badge-primary px-2">${data}</span>`;
                }
            },
            {
                data: 'updated_info',
                className: 'align-middle text-nowrap'
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center align-middle text-nowrap',
                render: function(data, type, row) {
                    return `
                        <button type="button" class="btn btn-sm btn-auto-edit-row shadow-sm btn-single-edit" data-id="${row.id}" data-cp="${row.no_cp}" title="Jalankan Edit Otomatis">
                            <i class="fas fa-magic mr-1"></i> Edit Otomatis
                        </button>
                        <a href="../resep_obat/view_resep.php?id=${row.id}" target="_blank" class="btn btn-outline-info btn-sm ml-1" title="Lihat Resep di Tab Baru">
                            <i class="fas fa-eye"></i>
                        </a>
                    `;
                }
            }
        ],
        order: [[1, 'desc']], // Default order by ID/No
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Memuat...</span></div> Memuat data...',
            lengthMenu: "Tampilkan _MENU_ baris",
            zeroRecords: "Tidak ada data resep yang sesuai.",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ resep",
            infoEmpty: "Menampilkan 0 sampai 0 dari 0 resep",
            infoFiltered: "(disaring dari _MAX_ total resep)",
            search: "Cari:",
            paginate: {
                first: "Awal",
                previous: "Sebelumnya",
                next: "Selanjutnya",
                last: "Akhir"
            }
        },
        drawCallback: function() {
            // Update status checkbox checkAll di halaman ini
            const allCheckboxes = $('.row-checkbox');
            const checkedCheckboxes = $('.row-checkbox:checked');
            if (allCheckboxes.length > 0 && allCheckboxes.length === checkedCheckboxes.length) {
                $('#checkAll').prop('checked', true);
            } else {
                $('#checkAll').prop('checked', false);
            }

            // Tandai baris yang terpilih
            $('.row-checkbox').each(function() {
                if ($(this).is(':checked')) {
                    $(this).closest('tr').addClass('row-selected');
                } else {
                    $(this).closest('tr').removeClass('row-selected');
                }
            });
        }
    });

    // Handle Quick Search
    let searchTimeout = null;
    $('#quickSearch').on('keyup', function() {
        clearTimeout(searchTimeout);
        const val = this.value;
        searchTimeout = setTimeout(function() {
            table.search(val).draw();
        }, 400);
    });

    // Handle Filter Apply & Reset
    $('#btnApplyFilter').on('click', function() {
        table.draw();
    });

    $('#btnResetFilter').on('click', function() {
        $('#filterStatus').val(['Shading', 'Master Resep', 'Kesetabilan']).trigger('change');
        $('#filterStartDate').val('');
        $('#filterEndDate').val('');
        $('#quickSearch').val('');
        table.search('').draw();
        $('.summary-card').removeClass('active');
        $('.stat-total').addClass('active');
    });

    // Handle Klik Summary Cards untuk filter cepat
    $('.summary-card').on('click', function() {
        const status = $(this).data('status');
        $('.summary-card').removeClass('active');
        $(this).addClass('active');

        if (status === 'all') {
            $('#filterStatus').val(['Shading', 'Master Resep', 'Kesetabilan']).trigger('change');
        } else {
            $('#filterStatus').val([status]).trigger('change');
        }
        table.draw();
    });

    // Handle Checkbox "Check All"
    $('#checkAll').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.row-checkbox').each(function() {
            const id = parseInt($(this).val(), 10);
            $(this).prop('checked', isChecked);
            if (isChecked) {
                selectedIds.add(id);
                $(this).closest('tr').addClass('row-selected');
            } else {
                selectedIds.delete(id);
                $(this).closest('tr').removeClass('row-selected');
            }
        });
        updateBulkButton();
    });

    // Handle Checkbox Individual Row
    $(document).on('change', '.row-checkbox', function() {
        const id = parseInt($(this).val(), 10);
        if ($(this).is(':checked')) {
            selectedIds.add(id);
            $(this).closest('tr').addClass('row-selected');
        } else {
            selectedIds.delete(id);
            $(this).closest('tr').removeClass('row-selected');
        }

        const allCheckboxes = $('.row-checkbox');
        const checkedCheckboxes = $('.row-checkbox:checked');
        $('#checkAll').prop('checked', allCheckboxes.length > 0 && allCheckboxes.length === checkedCheckboxes.length);

        updateBulkButton();
    });

    // Single Edit Otomatis
    $(document).on('click', '.btn-single-edit', function() {
        const resepId = $(this).data('id');
        const noCp = $(this).data('cp');
        const $btn = $(this);

        Swal.fire({
            title: 'Edit Otomatis Resep?',
            html: `
                <div class="text-left text-sm">
                    <p class="mb-2">Anda akan menjalankan edit otomatis untuk resep:</p>
                    <div class="p-2 mb-3 rounded" style="background:#f1f5f9; border: 1px solid #cbd5e1;">
                        <strong>ID:</strong> #${resepId}<br>
                        <strong>No CP:</strong> <span class="badge badge-primary">${noCp}</span>
                    </div>
                    <ul class="pl-3 mb-0 text-muted">
                        <li>Mengambil data bon & routing terbaru dari ProInt.</li>
                        <li>Menyusun urutan routing berdasarkan <strong>rtgseq terkecil di paling atas (Tabel #1)</strong>.</li>
                        <li>Memperbarui detail item obat, mapping ke master obat, dan menghitung harga terkini.</li>
                    </ul>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="fas fa-magic mr-1"></i> Ya, Jalankan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses Resep...',
                    html: `Sedang menyusun ulang urutan routing dan material untuk CP <strong>${noCp}</strong>...`,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: 'process_edit.php',
                    type: 'POST',
                    data: { resep_id: resepId },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.ok) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil Diperbarui!',
                                html: `
                                    <div class="text-left text-sm">
                                        <p class="text-success font-weight-bold mb-2">Resep #${resepId} (${resp.no_cp}) telah selesai diperbarui otomatis.</p>
                                        <div class="p-2 rounded bg-light border">
                                            <strong>Urutan Routing:</strong> ${resp.rtg_summary || '-'}<br>
                                            <strong>Mesin:</strong> ${resp.mesin || '-'}<br>
                                            <strong>Total Routing:</strong> ${resp.routing_count} tabel<br>
                                            <strong>Total Item Bahan:</strong> ${resp.detail_count} item
                                        </div>
                                    </div>
                                `,
                                confirmButtonColor: '#2563eb'
                            });
                            table.ajax.reload(null, false);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal Memproses',
                                text: resp.message || 'Terjadi kesalahan saat memproses edit otomatis.',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    },
                    error: function(xhr) {
                        let msg = 'Gagal menghubungi server.';
                        try {
                            const errResp = JSON.parse(xhr.responseText);
                            if (errResp && errResp.message) msg = errResp.message;
                        } catch (e) {}
                        Swal.fire({
                            icon: 'error',
                            title: 'Error Server',
                            text: msg,
                            confirmButtonColor: '#ef4444'
                        });
                    }
                });
            }
        });
    });

    // Bulk Edit Otomatis
    $('#btnBulkEdit').on('click', function() {
        const idArray = Array.from(selectedIds);
        if (idArray.length === 0) {
            Swal.fire('Perhatian', 'Pilih minimal satu resep dengan mencentang kotak pada tabel.', 'warning');
            return;
        }

        Swal.fire({
            title: `Edit Otomatis Masal (${idArray.length} Resep)?`,
            html: `
                <div class="text-left text-sm">
                    <p class="mb-2">Sistem akan memproses <strong>${idArray.length} resep terpilih</strong> sekaligus secara otomatis:</p>
                    <ul class="pl-3 mb-3 text-muted">
                        <li>Mengambil bon & routing terbaru dari ProInt per masing-masing No CP.</li>
                        <li>Menyusun ulang urutan routing berdasarkan <code>rtgseq ASC</code> (terkecil di atas).</li>
                        <li>Memperbarui detail bahan obat & kalkulasi harga.</li>
                    </ul>
                    <div class="alert alert-warning py-2 px-3 text-xs mb-0">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Proses ini dapat memerlukan beberapa detik tergantung jumlah resep.
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#64748b',
            confirmButtonText: `<i class="fas fa-magic mr-1"></i> Ya, Proses ${idArray.length} Resep!`,
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses Masal...',
                    html: `Sedang memproses <strong>${idArray.length}</strong> resep...<br><span class="text-muted text-xs">Mohon jangan menutup halaman ini.</span>`,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: 'process_edit_bulk.php',
                    type: 'POST',
                    data: { resep_ids: idArray },
                    dataType: 'json',
                    success: function(resp) {
                        let detailsHtml = '';
                        if (resp.results && resp.results.length > 0) {
                            detailsHtml = '<div class="mt-3 text-left text-xs" style="max-height: 180px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px;">';
                            resp.results.forEach(item => {
                                const badge = item.ok 
                                    ? '<span class="badge badge-success mr-1">SUKSES</span>' 
                                    : '<span class="badge badge-danger mr-1">GAGAL</span>';
                                detailsHtml += `<div class="py-1 border-bottom d-flex justify-content-between align-items-center">
                                    <span>${badge} #${item.resep_id} (${item.no_cp})</span>
                                    <span class="text-muted text-right ml-2">${item.message}</span>
                                </div>`;
                            });
                            detailsHtml += '</div>';
                        }

                        if (resp.ok) {
                            Swal.fire({
                                icon: resp.failed_count === 0 ? 'success' : 'warning',
                                title: 'Proses Masal Selesai!',
                                html: `
                                    <div class="text-sm">
                                        <p class="mb-2"><strong>${resp.success_count}</strong> dari <strong>${resp.total}</strong> resep berhasil diperbarui.</p>
                                        ${resp.failed_count > 0 ? `<p class="text-danger mb-2 font-weight-bold">${resp.failed_count} resep gagal diproses.</p>` : ''}
                                        ${detailsHtml}
                                    </div>
                                `,
                                confirmButtonColor: '#2563eb'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Sebagian atau Semua Gagal',
                                html: `
                                    <div class="text-sm">
                                        <p class="text-danger font-weight-bold mb-2">${resp.failed_count} dari ${resp.total} resep gagal diproses.</p>
                                        ${detailsHtml}
                                    </div>
                                `,
                                confirmButtonColor: '#ef4444'
                            });
                        }

                        // Reset seleksi dan reload tabel
                        selectedIds.clear();
                        $('#checkAll').prop('checked', false);
                        updateBulkButton();
                        table.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error Permintaan',
                            text: 'Terjadi kesalahan saat memproses permintaan masal ke server.',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                });
            }
        });
    });

});
</script>
