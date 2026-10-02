<?php
// pages/fabric_knowledge/problem_list.php
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
$themeColor = $_SESSION['Theme'] ?? 'primary';

// ====== Hak Akses Menu (MenuId = 123 untuk Knowledge Base) ======
function checkPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    $permissions = ['CanView' => 0, 'CanAdd' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $permissions = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    return $permissions;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 123);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../../index.php');
    exit;
}

// ====== Dropdown Kategori & Tag ======
function getDropdownOptions($conn)
{
    $options = ['kategori' => [], 'tag' => [], 'pdr' => []];

    // Get PDR options
    $sql = "SELECT DISTINCT pdr FROM dbo.fab_m_problem WHERE pdr IS NOT NULL ORDER BY pdr";
    $stmt = sqlsrv_query($conn, $sql);
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($row['pdr'] !== null) {
            $options['pdr'][] = $row['pdr'];
        }
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

    $sql = "SELECT id_kategori, nama_kategori FROM dbo.fab_m_kategori ORDER BY nama_kategori";
    $stmt = sqlsrv_query($conn, $sql);
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $options['kategori'][] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

    $sql = "SELECT id_tag, nama_tag, id_kategori FROM dbo.fab_m_tag ORDER BY nama_tag";
    $stmt = sqlsrv_query($conn, $sql);
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $options['tag'][] = $row;
    }
    if ($stmt) sqlsrv_free_stmt($stmt);

    return $options;
}
$dropdownOptions = getDropdownOptions($conn);

// ====== Include Layout ======
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

    <style>
        .filter-box {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }
        .filter-label {
            font-weight: 600;
            font-size: 0.75rem;
            color: #6c757d;
            margin-bottom: 6px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .form-control-sm {
            transition: all 0.3s ease;
        }
        .form-control-sm:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        #table-spinner {
            display: none;
            text-align: center;
            margin-bottom: 10px;
        }
        #table-spinner .spinner-border {
            width: 1rem;
            height: 1rem;
            vertical-align: middle;
        }
        .badge-status {
            padding: 5px 8px;
            font-size: 0.9em;
        }
        .tag-loading {
            display: none;
            color: #007bff;
            font-size: 0.8em;
        }
        .nocp-badge {
            background-color: #e9ecef;
            color: #495057;
            font-family: monospace;
            font-size: 0.85em;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .description-text {
            max-width: 350px;
            max-height: 80px;
            font-size: 0.9em;
            color: #6c757d;
            overflow-y: auto;
            padding-right: 5px;
        }
        /* Simple Scrollbar */
        .description-text::-webkit-scrollbar {
            width: 3px;
        }
        .description-text::-webkit-scrollbar-thumb {
            background: #ddd;
            border-radius: 10px;
        }
    </style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0">Masalah</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Masalah</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i>
                        Daftar Masalah</h3>
                    <div class="float-right">
                        <?php if ($permissions['CanAdd'] == 1): ?>
                            <a href="add_problem.php" class="btn btn-success btn-sm mr-2">
                                <i class="fas fa-plus"></i> Tambah Masalah
                            </a>
                        <?php endif; ?>
                        <!-- Tombol Export Excel -->
                        <button type="button" id="btnExportExcel" class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Filter -->
                    <div class="filter-box">
                        <div class="row align-items-end">
                            <div class="col-md-3 mb-2">
                                <label for="kategori" class="filter-label">Kategori:</label>
                                <select id="kategori" class="form-control form-control-sm">
                                    <option value="">Semua</option>
                                    <?php foreach ($dropdownOptions['kategori'] as $row): ?>
                                        <option value="<?= htmlspecialchars($row['id_kategori']) ?>">
                                            <?= htmlspecialchars($row['nama_kategori']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3 mb-2">
                                <label for="tag" class="filter-label">Tag:</label>
                                <select id="tag" class="form-control form-control-sm">
                                    <option value="">Semua</option>
                                    <?php foreach ($dropdownOptions['tag'] as $row): ?>
                                        <option value="<?= htmlspecialchars($row['id_tag']) ?>" 
                                                data-kategori="<?= htmlspecialchars($row['id_kategori']) ?>">
                                            <?= htmlspecialchars($row['nama_tag']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="tag-loading" id="tagLoading">
                                    <i class="fas fa-spinner fa-spin"></i> Memuat tag...
                                </div>
                            </div>

                            <div class="col-md-3 mb-2">
                                <label for="status" class="filter-label">Status:</label>
                                <select id="status" class="form-control form-control-sm">
                                    <option value="">Semua</option>
                                    <option value="Open">Open</option>
                                    <option value="Solved">Solved</option>
                                    <option value="Reopen">Reopen</option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-2">
                                <label for="padry" class="filter-label">Padry:</label>
                                <select id="padry" class="form-control form-control-sm">
                                    <option value="">Semua</option>
                                    <?php foreach ($dropdownOptions['pdr'] as $pdrVal): ?>
                                        <option value="<?= htmlspecialchars($pdrVal) ?>">
                                            <?= htmlspecialchars($pdrVal) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row align-items-end">
                            <div class="col-md-3 mb-2">
                                <label for="nocp" class="filter-label">No CP:</label>
                                <input type="text" id="nocp" class="form-control form-control-sm" placeholder="Search No CP...">
                            </div>

                            <div class="col-md-3 mb-2">
                                <label for="tanggal_awal" class="filter-label">Dari:</label>
                                <input type="date" id="tanggal_awal" class="form-control form-control-sm">
                            </div>
                            
                            <div class="col-md-3 mb-2">
                                <label for="tanggal_akhir" class="filter-label">Sampai:</label>
                                <input type="date" id="tanggal_akhir" class="form-control form-control-sm">
                            </div>

                            <div class="col-md-3 mb-2">
                                <label class="filter-label">&nbsp;</label>
                                <button type="button" id="btnReset" class="btn btn-outline-secondary btn-sm btn-block" style="height: 31px;">
                                    <i class="fas fa-sync-alt"></i> Reset Filter
                                </button>
                            </div>
                        </div>
                        
                        <!-- Hint Text -->
                        <div class="row mt-1">
                            <div class="col-md-12">
                                <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Filter akan otomatis diterapkan saat Anda mengubah pilihan.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Spinner -->
                    <div id="table-spinner">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <small class="text-muted ml-2">Memuat data...</small>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table id="problemTable" class="table table-hover table-sm" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>No CP</th>
                                    <th>Kategori</th>
                                    <th>Tag</th>
                                    <th>Status</th>
                                    <th>Deskripsi Masalah</th>
                                    <th>Tgl Lkp Qc</th>
                                    <th>Tgl Dibuat</th>
                                    <th>Dibuat</th>
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
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
(function(){
    window.appPermissions = {
        canEdit: <?= $permissions['CanEdit'] == 1 ? 'true' : 'false'; ?>,
        canDelete: <?= $permissions['CanDelete'] == 1 ? 'true' : 'false'; ?>
    };

    var $spinner = $('#table-spinner');
    var reloadTimeout;

    // Fungsi untuk reload table dengan debounce
    function reloadTable() {
        clearTimeout(reloadTimeout);
        reloadTimeout = setTimeout(() => {
            table.ajax.reload();
        }, 500); // Delay 500ms untuk menghindari terlalu banyak request
    }

    // Fungsi untuk filter tag berdasarkan kategori
    function filterTagsByCategory(categoryId) {
        const tagSelect = document.getElementById('tag');
        const tagLoading = document.getElementById('tagLoading');
        const currentSelectedTag = tagSelect.value;
        
        // Show loading
        tagLoading.style.display = 'block';
        
        // Reset tag options, keep the first empty option
        const emptyOption = tagSelect.querySelector('option[value=""]');
        tagSelect.innerHTML = '';
        if (emptyOption) {
            tagSelect.appendChild(emptyOption);
        }
        
        // Get all tag options from original data
        const allTags = <?php echo json_encode($dropdownOptions['tag']); ?>;
        
        // Filter tags by selected category
        const filteredTags = allTags.filter(tag => {
            return !categoryId || tag.id_kategori == categoryId;
        });
        
        // Add filtered tags to select
        filteredTags.forEach(tag => {
            const option = document.createElement('option');
            option.value = tag.id_tag;
            option.textContent = tag.nama_tag;
            option.setAttribute('data-kategori', tag.id_kategori);
            tagSelect.appendChild(option);
        });
        
        // Try to restore previous selection if it exists in filtered tags
        if (currentSelectedTag && filteredTags.some(tag => tag.id_tag == currentSelectedTag)) {
            tagSelect.value = currentSelectedTag;
        } else {
            tagSelect.value = '';
        }
        
        // Hide loading
        tagLoading.style.display = 'none';
        
        // Auto reload table setelah filter tag berubah
        reloadTable();
    }

    // Event listener untuk perubahan kategori
    document.getElementById('kategori').addEventListener('change', function() {
        const selectedCategoryId = this.value;
        filterTagsByCategory(selectedCategoryId);
    });

    // Initialize tags based on selected category (if any)
    const initialCategoryId = document.getElementById('kategori').value;
    if (initialCategoryId) {
        filterTagsByCategory(initialCategoryId);
    }

    var table = $('#problemTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
    url: 'problem_serverside.php',
    type: 'POST',
    data: function(d) {
        d.kategori = $('#kategori').val();
        d.tag = $('#tag').val();
        d.status = $('#status').val();
        d.nocp = $('#nocp').val();
        d.padry = $('#padry').val();
        d.tanggal_awal = $('#tanggal_awal').val();
        d.tanggal_akhir = $('#tanggal_akhir').val();
    },
    beforeSend: function(){ 
        $spinner.show(); 
    },
    complete: function(){ 
        $spinner.hide(); 
    },
    error: function(xhr, status, error){
        $spinner.hide();
        console.error('AJAX Error:', status, error);
        console.error('Response Text:', xhr.responseText);
        
        let errorMsg = 'Gagal memuat data: ';
        if (xhr.responseText) {
            try {
                const response = JSON.parse(xhr.responseText);
                errorMsg += response.error || 'Format JSON tidak valid';
            } catch (e) {
                errorMsg += 'Respons server tidak valid: ' + xhr.responseText.substring(0, 100);
            }
        } else {
            errorMsg += error;
        }
        
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            html: errorMsg
        });
    },
    dataFilter: function(data, type) {
        // Pre-process the response data
        try {
            const json = JSON.parse(data);
            return JSON.stringify(json);
        } catch (e) {
            console.error('JSON Parse Error:', e);
            console.error('Raw Data:', data);
            return JSON.stringify({
                draw: 0,
                recordsTotal: 0,
                recordsFiltered: 0,
                data: [],
                error: 'Invalid JSON response'
            });
        }
    }
},
        columns: [
            { 
                data: null, 
                orderable: false, 
                render: (data, type, row, meta) => meta.row + 1 + meta.settings._iDisplayStart 
            },
            { 
                data: 'nocp',
                render: function(data, type, row) {
                    if (!data) return '-';
                    return `<span class="nocp-badge">${data}</span>`;
                }
            },
            { 
                data: 'nama_kategori',
                render: function(data, type, row) {
                    return data || '-';
                }
            },
            { 
                data: 'nama_tag',
                render: function(data, type, row) {
                    return data || '-';
                }
            },
            { 
                data: 'status', 
                render: function(data, type, row) {
                    let color = 'secondary';
                    if (data === 'Solved') color = 'success';
                    else if (data === 'Open') color = 'warning';
                    else if (data === 'Reopen') color = 'danger';
                    
                    return `<span class="badge badge-${color} badge-status">${data || '-'}</span>`;
                }
            },
            { 
                data: 'deskripsi',
                width: '35%',
                render: function(data, type, row) {
                    if (!data) return '-';
                    // Replace newlines with <br> for display
                    let formattedData = data.replace(/\n/g, '<br>');
                    return `<div class='description-text'>${formattedData}</div>`;
                }
            },
            { 
                data: 'tgl_lkp_qc',
                render: function(data, type, row) {
                    return data || '-';
                }
            },
            {  
                // PERBAIKAN: Ganti dari updated_at menjadi created_at
                data: 'created_at',
                render: function(data, type, row) {
                    if (!data) return '-';
                    return data;
                }
            },
            { 
                data: 'created_by',
                render: function(data, type, row) {
                    return data || '-';
                }
            },
            { 
                data: 'id_problem', 
                orderable: false, 
                searchable: false, 
                render: function(id_problem, type, row) {
                    var html = `<a href="problem_detail.php?id=${id_problem}" class="btn btn-info btn-sm" title="Detail"><i class="fas fa-eye"></i></a> `;
                    
                    if (window.appPermissions.canEdit) {
                        // Tambahkan tombol Edit
                        html += `<a href="edit_problem.php?id=${id_problem}" class="btn btn-warning btn-sm" title="Edit Masalah"><i class="fas fa-edit"></i></a> `;
                        html += `<a href="analisa_solusi.php?id=${id_problem}" class="btn btn-primary btn-sm" title="Analisa & Solusi"><i class="fas fa-lightbulb"></i></a> `;
                    }
                    
                    if (window.appPermissions.canDelete) {
                        html += `<button class="btn btn-danger btn-sm btn-delete" data-id="${id_problem}" title="Hapus"><i class="fas fa-trash"></i></button>`;
                    }
                    
                    return html;
                }
            }
        ],
        responsive: true,
        autoWidth: false        
    });

    // Event listeners untuk autoload filter
    $('#tag, #status').on('change', function() {
        reloadTable();
    });

    $('#padry').on('change', function() {
        reloadTable();
    });

    $('#nocp').on('input', function() {
        reloadTable();
    });

    $('#tanggal_awal, #tanggal_akhir').on('change', function() {
        reloadTable();
    });

    // Reset filter
    $('#btnReset').on('click', function() {
        $('#kategori, #tag, #status, #nocp, #padry, #tanggal_awal, #tanggal_akhir').val('');
        // Reset juga filter tag berdasarkan kategori
        filterTagsByCategory('');
        table.ajax.reload();
    });

    // Format input No CP
    $('#nocp').on('input', function(e) {
        let value = e.target.value.replace(/[^D\dK\.]/g, '');
        
        // Auto format: D25K0097.01.0101
        if (value.length >= 3 && value.charAt(0) === 'D') {
            if (value.length >= 4 && value.charAt(3) !== 'K') {
                value = value.substring(0, 3) + 'K' + value.substring(3);
            }
            if (value.length >= 9 && value.charAt(8) !== '.') {
                value = value.substring(0, 8) + '.' + value.substring(8);
            }
            if (value.length >= 12 && value.charAt(11) !== '.') {
                value = value.substring(0, 11) + '.' + value.substring(11);
            }
        }
        
        e.target.value = value;
    });

    $(document).on('click', '.btn-delete', function(){
        var id = $(this).data('id');
        Swal.fire({
            title: 'Hapus Masalah?',
            text: 'Data ini akan dihapus permanen.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if (result.isConfirmed) {
                window.location.href = 'delete_problem.php?id=' + encodeURIComponent(id);
            }
        });
    });

    // Fungsi untuk menyimpan state ke sessionStorage
    function saveTableState() {
        const state = {
            search: table.search(),
            page: table.page(),
            pageLength: table.page.len(),
            order: table.order(),
            filters: {
                kategori: $('#kategori').val(),
                tag: $('#tag').val(),
                status: $('#status').val(),
                nocp: $('#nocp').val(),
                padry: $('#padry').val(),
                tanggal_awal: $('#tanggal_awal').val(),
                tanggal_akhir: $('#tanggal_akhir').val()
            },
            scrollPosition: window.pageYOffset
        };
        sessionStorage.setItem('problemListState', JSON.stringify(state));
    }

    // Fungsi untuk memulihkan state dari sessionStorage
    function restoreTableState() {
        const savedState = sessionStorage.getItem('problemListState');
        if (savedState) {
            const state = JSON.parse(savedState);
            
            // Terapkan filter
            $('#kategori').val(state.filters.kategori || '');
            $('#tag').val(state.filters.tag || '');
            $('#status').val(state.filters.status || '');
            $('#nocp').val(state.filters.nocp || '');
            $('#padry').val(state.filters.padry || '');
            $('#tanggal_awal').val(state.filters.tanggal_awal || '');
            $('#tanggal_akhir').val(state.filters.tanggal_akhir || '');
            
            // Set callback untuk setelah data dimuat
            table.one('draw', function() {
                // Terapkan pencarian
                if (state.search) {
                    table.search(state.search).draw();
                }
                
                // Terapkan halaman
                if (state.page !== undefined) {
                    table.page(state.page).draw(false);
                }
                
                // Terapkan urutan
                if (state.order && state.order.length > 0) {
                    table.order(state.order).draw(false);
                }
                
                // Terapkan posisi scroll setelah render selesai
                setTimeout(() => {
                    if (state.scrollPosition) {
                        window.scrollTo(0, state.scrollPosition);
                    }
                }, 100);
            });
            
            // Hapus state yang sudah dipulihkan
            sessionStorage.removeItem('problemListState');
        }
    }

    // ====== AUTO FILTER BERDASARKAN PARAMETER URL ======
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    // Event listener untuk semua link yang mengarah ke detail
    $(document).on('click', 'a[href*="problem_detail.php"], a[href*="analisa_solusi.php"], a[href*="view_solution.php"]', function(e) {
        saveTableState();
    });

    // Event listener untuk tombol "Kembali" di browser
    window.addEventListener('beforeunload', function() {
        // Hanya simpan state jika bukan navigasi ke halaman detail
        if (!window.location.href.includes('problem_detail.php') && 
            !window.location.href.includes('analisa_solusi.php') &&
            !window.location.href.includes('view_solution.php')) {
            saveTableState();
        }
    });

    // Inisialisasi saat halaman dimuat
    $(document).ready(function() {
        // Prioritaskan parameter URL di atas sessionStorage
        const urlStatus = getUrlParameter('filter_status');
        
        if (urlStatus) {
            // Ada parameter URL, set filter status dan reload
            $('#status').val(urlStatus);
            // Clear any saved state to avoid conflict
            sessionStorage.removeItem('problemListState');
            // Reload table dengan filter status
            setTimeout(() => {
                table.ajax.reload();
            }, 300);
        } else {
            // Tidak ada parameter URL, gunakan sessionStorage
            restoreTableState();
        }
        
        // Simpan state secara berkala (setiap 2 detik) untuk backup
        setInterval(saveTableState, 2000);
    });

    // Export to Excel
    $('#btnExportExcel').on('click', function() {
        // Ambil nilai filter saat ini
        const filters = {
            kategori: $('#kategori').val(),
            tag: $('#tag').val(),
            status: $('#status').val(),
            nocp: $('#nocp').val(),
            padry: $('#padry').val(),
            tanggal_awal: $('#tanggal_awal').val(),
            tanggal_akhir: $('#tanggal_akhir').val()
        };
        
        // Build URL dengan parameter filter
        const params = new URLSearchParams();
        Object.keys(filters).forEach(key => {
            if (filters[key]) {
                params.append(key, filters[key]);
            }
        });
        
        // Redirect ke halaman export
        window.location.href = 'export_problem.php?' + params.toString();
    });
})();
</script>

<!-- SweetAlert untuk Notifikasi -->
<?php if (!empty($_SESSION['success'])): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Berhasil!',
    text: '<?= addslashes($_SESSION['success']) ?>',
    timer: 1800,
    showConfirmButton: false
});
</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Gagal!',
    text: '<?= addslashes($_SESSION['error']) ?>'
});
</script>
<?php unset($_SESSION['error']); endif; ?>
