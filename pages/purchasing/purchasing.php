<?php
session_start();
include_once file_exists(__DIR__ . '/../../koneksi.php') ? __DIR__ . '/../../koneksi.php' : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include_once file_exists(__DIR__ . '/../../includes/permissions.php') ? __DIR__ . '/../../includes/permissions.php' : ($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/permissions.php');

// Menu ID Purchasing (dapat disesuaikan jika sudah didaftarkan di dbo.SMMenu)
$menuId = 1330; 
$permissions = ['CanView' => 1, 'CanAdd' => 1, 'CanEdit' => 1, 'CanDelete' => 1];

if (!empty($menuId) && !empty($_SESSION['GroupId'])) {
    $perm = getPermissions($conn, $_SESSION['GroupId'], $menuId);
    if (!empty($perm)) {
        $permissions = $perm;
    }
    if ($permissions['CanView'] != 1) {
        $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
        header('Location: /gg_app/index.php');
        exit;
    }
}

// Ambil data Akun Pengirim saat ini (Format: Nama (Departemen))
$currentUserPengirim = 'Fikri Salman Ramadhan (Information Technology)'; // Default fallback
if (!empty($_SESSION['UserName']) && !empty($conn)) {
    $qUser = sqlsrv_query($conn, "SELECT e.nama_lengkap, ISNULL(d.dept, 'Information Technology') AS dept
        FROM dbo.SMUserMs u
        LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
        LEFT JOIN dbo.m_subbag sb ON e.id_subbag = sb.id_subbag
        LEFT JOIN dbo.m_bag b ON sb.id_bag = b.id_bag
        LEFT JOIN dbo.m_dept d ON b.id_dept = d.id_dept
        WHERE u.UserName = ?", [$_SESSION['UserName']]);
    if ($qUser && ($uRow = sqlsrv_fetch_array($qUser, SQLSRV_FETCH_ASSOC))) {
        $namaUser = !empty($uRow['nama_lengkap']) ? trim($uRow['nama_lengkap']) : (!empty($_SESSION['NamaLengkap']) ? trim($_SESSION['NamaLengkap']) : $_SESSION['UserName']);
        $deptUser = !empty($uRow['dept']) ? trim($uRow['dept']) : 'Information Technology';
        $currentUserPengirim = $namaUser . ' (' . $deptUser . ')';
    } elseif (!empty($_SESSION['NamaLengkap'])) {
        $currentUserPengirim = trim($_SESSION['NamaLengkap']) . ' (Information Technology)';
    }
}

// Penerima tidak diinput saat pembuatan — akan otomatis terisi dari akun yang menandatangani

// Ambil daftar unik type_keterangan khusus type PO dari database
$poKeteranganOptions = ['Untuk dibuat Cek', 'Untuk di bayar'];
if (!empty($conn)) {
    $qKetInit = sqlsrv_query($conn, "SELECT DISTINCT LTRIM(RTRIM(type_keterangan)) AS ket FROM dbo.purchasing_header WHERE type = 'PO' AND type_keterangan IS NOT NULL AND LTRIM(RTRIM(type_keterangan)) <> '' AND is_deleted = 0 ORDER BY ket ASC");
    if ($qKetInit) {
        while ($rk = sqlsrv_fetch_array($qKetInit, SQLSRV_FETCH_ASSOC)) {
            $kVal = trim($rk['ket'] ?? '');
            if (!empty($kVal) && !in_array($kVal, $poKeteranganOptions, true)) {
                $poKeteranganOptions[] = $kVal;
            }
        }
    }
}

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<style>
/* Style Canvas Tanda Tangan */
.signature-canvas-container {
    position: relative;
    width: 100%;
    height: 180px;
    border: 2px dashed #b8c2cc;
    border-radius: 6px;
    background-color: #ffffff;
    touch-action: none;
    user-select: none;
    margin-bottom: 8px;
}
.signature-canvas-container canvas {
    width: 100%;
    height: 100%;
    display: block;
    cursor: crosshair;
}
.signature-guide-line {
    position: absolute;
    bottom: 30px;
    left: 10%;
    right: 10%;
    border-bottom: 1px dashed #ced4da;
    pointer-events: none;
    text-align: right;
    color: #adb5bd;
    font-size: 11px;
    padding-right: 5px;
}
.btn-group-xs > .btn, .btn-xs {
    padding: .15rem .35rem;
    font-size: .75rem;
    line-height: 1.5;
    border-radius: .2rem;
}
/* Floating Action Bar untuk Batch Signature (Modern Pill Design) */
.batch-floating-bar {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1040;
    width: max-content;
    max-width: calc(100vw - 32px);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: auto;
}
.batch-floating-content {
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    color: #fff;
    padding: 8px 10px 8px 14px;
    border-radius: 9999px;
    box-shadow: 0 16px 36px -6px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.12);
    display: inline-flex;
    align-items: center;
    gap: 12px;
}
.batch-counter-badge {
    background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 13px;
    padding: 7px 14px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.35);
    letter-spacing: 0.2px;
    flex-shrink: 0;
}
.batch-floating-divider {
    width: 1px;
    height: 24px;
    background: rgba(255, 255, 255, 0.18);
    flex-shrink: 0;
}
.btn-batch-cancel {
    background: rgba(255, 255, 255, 0.08);
    color: #cbd5e1 !important;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 9999px;
    font-size: 12.5px;
    font-weight: 600;
    padding: 7px 15px;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.btn-batch-cancel:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5 !important;
    border-color: rgba(239, 68, 68, 0.35);
}
.btn-batch-submit {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff !important;
    border: none;
    border-radius: 9999px;
    font-size: 13px;
    font-weight: 700;
    padding: 8px 18px;
    white-space: nowrap;
    box-shadow: 0 3px 12px rgba(16, 185, 129, 0.35);
    display: inline-flex;
    align-items: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.btn-batch-submit:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-1px);
    box-shadow: 0 5px 16px rgba(16, 185, 129, 0.45);
}
.btn-batch-submit:active {
    transform: translateY(0);
}
@media (max-width: 576px) {
    .batch-floating-bar { width: 95%; max-width: 95%; bottom: 12px; }
    .batch-floating-content { padding: 10px 14px; border-radius: 18px; flex-wrap: wrap; justify-content: center; gap: 8px; }
    .batch-floating-divider { display: none; }
}
/* =========================================================
   GAYA TABEL SERAH TERIMA PURCHASING (MODERN & RAPIH)
   ========================================================= */
.filter-clean-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 16px 6px 16px;
    margin-bottom: 16px;
}
/* Responsive Control Column (+) & (-) khusus agar terpisah dari checkbox */
#purchasingTable.dtr-column > tbody > tr > td.dtr-control,
#purchasingTable.dtr-column > tbody > tr > th.dtr-control {
    position: relative;
    cursor: pointer;
    text-align: center !important;
    padding: 0 !important;
    width: 26px !important;
}
#purchasingTable.dtr-column > tbody > tr > td.dtr-control::before,
#purchasingTable.dtr-column > tbody > tr > th.dtr-control::before {
    top: 50% !important;
    left: 50% !important;
    height: 16px !important;
    width: 16px !important;
    margin-top: -8px !important;
    margin-left: -8px !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    position: absolute;
    color: #ffffff !important;
    border: 2px solid #ffffff !important;
    border-radius: 50% !important;
    box-shadow: 0 1px 4px rgba(111, 66, 193, 0.45) !important;
    box-sizing: border-box;
    text-align: center;
    font-family: inherit;
    font-weight: 700;
    font-size: 13px !important;
    line-height: 1 !important;
    content: "+" !important;
    background-color: #6f42c1 !important;
    transition: all 0.15s ease-in-out;
}
#purchasingTable.dtr-column > tbody > tr.parent > td.dtr-control::before,
#purchasingTable.dtr-column > tbody > tr.parent > th.dtr-control::before {
    content: "−" !important;
    background-color: #ef4444 !important;
    box-shadow: 0 1px 4px rgba(239, 68, 68, 0.45) !important;
}
#purchasingTable.dtr-column > tbody > tr > td.dtr-control:hover::before {
    transform: scale(1.15);
}
#purchasingTable {
    border-collapse: collapse !important;
    border: 1px solid #e2e8f0;
}
#purchasingTable thead th {
    background-color: #f1f5f9 !important;
    color: #334155 !important;
    font-size: 11.5px !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-top: 1px solid #e2e8f0 !important;
    border-bottom: 2px solid #cbd5e1 !important;
    padding: 10px 8px !important;
    vertical-align: middle !important;
    white-space: nowrap;
}
#purchasingTable tbody td {
    vertical-align: middle !important;
    padding: 6px 8px !important;
    font-size: 12.5px;
    color: #1e293b;
    border: 1px solid #edf2f7 !important;
    background-color: #ffffff;
}
#purchasingTable tbody tr:hover td {
    background-color: #f8fafc !important;
}

/* Multi-line cells alignment across columns */
.multi-line-wrapper {
    display: flex;
    flex-direction: column;
    width: 100%;
}
.sub-row-item {
    min-height: 34px;
    display: flex;
    align-items: center;
    padding: 4px 6px;
    box-sizing: border-box;
    width: 100%;
    font-size: 12.5px;
}
.sub-row-item.border-top {
    border-top: 1px solid #e2e8f0 !important;
}
.sub-row-item-center {
    justify-content: center;
    text-align: center;
}
.sub-row-text {
    color: #1e293b;
    line-height: 1.4;
    word-break: break-word;
}
.vendor-name {
    color: #0f172a;
    font-size: 12px;
}

/* Number badge bulat elegan */
.item-num-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 19px;
    height: 19px;
    border-radius: 50%;
    background-color: #e2e8f0;
    color: #475569;
    font-size: 10px;
    font-weight: 700;
    margin-right: 8px;
    flex-shrink: 0;
    border: 1px solid #cbd5e1;
}

/* Code badge untuk PO & GRN */
.badge-code {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
    letter-spacing: 0.2px;
    display: inline-block;
}
.badge-po {
    background-color: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}
.badge-grn {
    background-color: #f8fafc;
    color: #475569;
    border: 1px solid #cbd5e1;
}

/* Type Badges Beragam Warna Harmonis */
.badge-type-cod {
    background-color: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    font-weight: 700;
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
}
.badge-type-po {
    background-color: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    font-weight: 700;
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
}
.badge-type-rfp {
    background-color: #faf5ff;
    color: #7e22ce;
    border: 1px solid #e9d5ff;
    font-weight: 700;
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
}
.badge-type-offset {
    background-color: #f0fdfa;
    color: #0f766e;
    border: 1px solid #99f6e4;
    font-weight: 700;
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
}
.badge-type-kontrabon {
    background-color: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
    font-weight: 700;
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
}
.badge-type-default {
    background-color: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
    font-weight: 700;
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
}
.badge-type-sub {
    background-color: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    display: inline-block;
}

/* Styling Combobox & Dropdown Keterangan Khusus PO */
.menu-po-keterangan {
    min-width: 190px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    border-radius: 6px;
    padding: 4px 0;
    z-index: 1060;
}
.menu-po-keterangan .dropdown-item {
    font-size: 12px;
    padding: 6px 12px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.menu-po-keterangan .dropdown-item:hover {
    background-color: #f1f5f9;
    color: #0284c7;
    font-weight: 600;
}
.po-keterangan-group, .edit-po-keterangan-group {
    position: relative;
}

/* Status TTD Badges */
.badge-status-signed {
    background-color: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
    font-weight: 600;
    font-size: 11px;
    border-radius: 20px;
    padding: 3px 10px;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
}
.badge-status-pending {
    background-color: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
    font-weight: 600;
    font-size: 11px;
    border-radius: 20px;
    padding: 3px 10px;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
}

/* Action Button Group */
.action-btn-group {
    display: inline-flex;
    gap: 4px;
    justify-content: center;
    align-items: center;
}
.action-btn-group .btn {
    width: 27px;
    height: 27px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    font-size: 11px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.action-btn-group .btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(0,0,0,0.15);
}

/* Modal Form Tambah & Edit Dibuat Lebih Lebar & Lega */
#modalTambahPurchasing .modal-dialog,
#modalEditPurchasing .modal-dialog {
    max-width: 95% !important;
    width: 95% !important;
    margin: 1.25rem auto;
}
@media (min-width: 1600px) {
    #modalTambahPurchasing .modal-dialog,
    #modalEditPurchasing .modal-dialog {
        max-width: 92% !important;
        width: 92% !important;
    }
}
</style>

<div class="wrapper">
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold">Serah Terima Purchasing</h1>
                    <small class="text-muted">Daftar serah terima berkas/dokumen purchasing untuk verifikasi & tanda tangan accounting</small>
                </div>
                <div class="col-sm-6 text-right">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item">Purchasing</li>
                        <li class="breadcrumb-item active">Serah Terima</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white clearfix">
                    <h3 class="card-title font-weight-bold pt-1 mb-0">
                        <i class="fas fa-file-signature mr-1"></i> List Serah Terima Purchasing (Accounting)
                    </h3>
                    <div class="card-tools float-right d-flex align-items-center">
                        <div class="custom-control custom-switch mr-3 text-white">
                            <input type="checkbox" class="custom-control-input" id="desktopNotifToggle">
                            <label class="custom-control-label small font-weight-bold" for="desktopNotifToggle" id="desktopNotifLabel" style="cursor: pointer;">
                                <i class="fas fa-bell mr-1"></i> Notifikasi Browser
                            </label>
                        </div>
                        <button type="button" class="btn btn-success btn-sm font-weight-bold shadow-sm" id="btnTambahData">
                            <i class="fas fa-plus mr-1"></i> Tambah Data
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Section Filter Data -->
                    <div class="filter-clean-box">
                        <div class="row align-items-end">
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label for="filterTanggal" class="small font-weight-bold text-muted mb-1">
                                    <i class="fas fa-calendar-alt mr-1"></i> Filter Tanggal
                                </label>
                                <input type="date" id="filterTanggal" name="filterTanggal" class="form-control form-control-sm" autocomplete="off">
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label for="filterType" class="small font-weight-bold text-muted mb-1">
                                    <i class="fas fa-filter mr-1"></i> Filter Type
                                </label>
                                <select id="filterType" class="form-control form-control-sm">
                                    <option value="">Semua Type</option>
                                    <option value="PO">PO</option>
                                    <option value="COD">COD</option>
                                    <option value="RFP">RFP</option>
                                    <option value="OFFSET">OFFSET</option>
                                    <option value="Kontrabon">Kontrabon</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label for="filterStatus" class="small font-weight-bold text-muted mb-1">
                                    <i class="fas fa-check-double mr-1"></i> Status TTD Accounting
                                </label>
                                <select id="filterStatus" class="form-control form-control-sm">
                                    <option value="">Semua Status</option>
                                    <option value="Menunggu TTD">Menunggu TTD</option>
                                    <option value="Sudah Ditandatangani">Sudah Ditandatangani</option>
                                    <option value="Ditolak / Revisi">Ditolak / Revisi</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <button type="button" class="btn btn-secondary btn-sm px-3" id="btnResetFilter">
                                    <i class="fas fa-undo mr-1"></i> Reset Filter
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Data Server-Side sesuai format Excel/Gambar Serah Terima -->
                    <div class="table-responsive">
                        <table id="purchasingTable" class="table table-hover align-middle text-center w-100 mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 26px;" class="text-center align-middle"></th>
                                    <th style="width: 38px;" class="text-center align-middle">
                                        <div class="custom-control custom-checkbox d-inline-block">
                                            <input type="checkbox" class="custom-control-input" id="checkAllRows">
                                            <label class="custom-control-label" for="checkAllRows" title="Pilih Semua yang Belum TTD"></label>
                                        </div>
                                    </th>
                                    <th style="width: 38px;">No</th>
                                    <th style="width: 95px;">Tanggal</th>
                                    <th style="width: 130px;">Type</th>
                                    <th style="min-width: 250px;">Description</th>
                                    <th style="width: 170px;">Nama Vendor</th>
                                    <th style="width: 130px;">No PO</th>
                                    <th style="width: 100px;">No GRN</th>
                                    <th style="width: 105px;">Tanda Tangan</th>
                                    <th style="width: 110px;">Tanggal Diterima</th>
                                    <th style="width: 115px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data dimuat via AJAX Server-Side -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Datalist untuk Autocomplete Keterangan PO -->
<datalist id="listPoKeterangan">
    <?php foreach ($poKeteranganOptions as $opt): ?>
        <option value="<?= htmlspecialchars($opt) ?>"></option>
    <?php endforeach; ?>
</datalist>

<!-- ======================================================= -->
<!-- MODAL TAMBAH DATA (FORM DINAMIS BISA TAMBAH BARIS)     -->
<!-- ======================================================= -->
<div class="modal fade" id="modalTambahPurchasing" tabindex="-1" role="dialog" aria-labelledby="modalTambahPurchasingLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title font-weight-bold" id="modalTambahPurchasingLabel">
                    <i class="fas fa-plus-circle mr-1"></i> Tambah Data Serah Terima Purchasing
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form id="formTambahPurchasing">
                <div class="modal-body">
                    <!-- Form Header Info -->
                    <div class="row bg-light p-3 rounded mb-3 border">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group mb-0">
                                <label for="inputTanggal" class="font-weight-bold small text-muted">Tanggal Serah Terima <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm" id="inputTanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group mb-0">
                                <label for="inputPengirim" class="font-weight-bold small text-muted">Pengirim</label>
                                <input type="text" class="form-control form-control-sm bg-white font-weight-bold" id="inputPengirim" name="pengirim" value="<?= htmlspecialchars($currentUserPengirim) ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-12 mt-2 mt-md-0">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold small text-muted">Penerima</label>
                                <div class="form-control form-control-sm bg-light text-muted d-flex align-items-center" style="height:auto; min-height:31px; font-size:0.82em;">
                                    <i class="fas fa-info-circle mr-1 text-primary"></i>
                                    Terisi otomatis saat dokumen ditandatangani
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Header Section Tabel Dinamis -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="font-weight-bold text-primary mb-0">
                            <i class="fas fa-list-ol mr-1"></i> Rincian Dokumen / Berkas Serah Terima
                        </h6>
                        <button type="button" class="btn btn-outline-primary btn-sm font-weight-bold" id="btnAddRow">
                            <i class="fas fa-plus mr-1"></i> Tambah Baris Dokumen
                        </button>
                    </div>

                    <!-- Tabel Input Dinamis -->
                    <div class="table-responsive border rounded">
                        <table class="table table-bordered table-sm mb-0 text-center" id="tableDynamicRows">
                            <thead class="bg-secondary text-white small">
                                <tr>
                                    <th style="width: 3%">No</th>
                                    <th style="width: 14%">Type <span class="text-warning">*</span></th>
                                    <th style="width: 34%">Description (Uraian Berkas) <span class="text-warning">*</span></th>
                                    <th style="width: 18%">Nama Vendor <span class="text-warning">*</span></th>
                                    <th style="width: 16%">No PO <span class="text-warning">*</span></th>
                                    <th style="width: 11%">No GRN</th>
                                    <th style="width: 4%"><i class="fas fa-cog"></i></th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDynamicRows">
                                <!-- Baris dinamis di-generate oleh javascript -->
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted font-italic mt-1 d-block">
                        * Catatan: Anda dapat menambah banyak baris dokumen sekaligus serta menambah beberapa sub-deskripsi di setiap baris.
                    </small>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold" id="btnSubmitTambah">
                        <i class="fas fa-save mr-1"></i> Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- MODAL EDIT DATA SERAH TERIMA PURCHASING                -->
<!-- ======================================================= -->
<div class="modal fade" id="modalEditPurchasing" tabindex="-1" role="dialog" aria-labelledby="modalEditPurchasingLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title font-weight-bold" id="modalEditPurchasingLabel">
                    <i class="fas fa-edit mr-1"></i> Edit Data Serah Terima Purchasing
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formEditPurchasing">
                <input type="hidden" id="editRowId" name="id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="small font-weight-bold">Tanggal Serah Terima</label>
                            <input type="date" class="form-control form-control-sm" id="editTanggal" name="tanggal" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="small font-weight-bold">Pengirim</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="editPengirim" name="pengirim" readonly>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="small font-weight-bold">Penerima</label>
                            <div class="form-control form-control-sm bg-light text-muted d-flex align-items-center" style="height:auto; min-height:31px; font-size:0.82em;">
                                <i class="fas fa-info-circle mr-1 text-primary"></i>
                                Terisi otomatis saat dokumen ditandatangani
                            </div>
                        </div>
                        <div class="col-12 form-group">
                            <label class="small font-weight-bold">Type</label>
                            <select class="form-control form-control-sm mb-1 font-weight-bold" id="editType" name="type" required>
                                <option value="PO">PO</option>
                                <option value="COD">COD</option>
                                <option value="RFP">RFP</option>
                                <option value="OFFSET">OFFSET</option>
                                <option value="Kontrabon">Kontrabon</option>
                            </select>
                            <div class="input-group input-group-sm edit-po-keterangan-group">
                                <input type="text" class="form-control form-control-sm" id="editTypeKeterangan" name="type_keterangan" list="listPoKeterangan" placeholder="Keterangan / No. Type (opsional)" autocomplete="off">
                                <div class="input-group-append" id="btnEditPoKetDropdownWrapper">
                                    <button class="btn btn-outline-secondary dropdown-toggle px-2" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Pilih keterangan PO"></button>
                                    <div class="dropdown-menu dropdown-menu-right menu-po-keterangan shadow-sm" style="max-height: 200px; overflow-y: auto;">
                                        <!-- Populated dynamically -->
                                    </div>
                                </div>
                                <div class="input-group-append d-none" id="btnEditRfpFetchWrapper">
                                    <button class="btn btn-outline-secondary" type="button" id="btnEditFetchRfp" title="Ambil Data Vendor dari No. RFP di Proint ERP">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <small id="editRfpLoadingText" class="text-primary font-weight-bold d-none" style="font-size: 10px;">
                                <i class="fas fa-spinner fa-spin mr-1"></i> Memuat data ERP...
                            </small>
                        </div>
                        
                        <!-- SECTION EDIT MULTI-PO (UNTUK DIBUAT CEK) -->
                        <div class="col-12" id="editMultiPoSection">
                            <div class="card card-outline card-primary mb-2 shadow-none border">
                                <div class="card-header py-1 px-2 d-flex justify-content-between align-items-center bg-light">
                                    <span class="font-weight-bold text-primary small">
                                        <i class="fas fa-list-ol mr-1"></i> Rincian PO
                                    </span>
                                    <div>
                                        <button type="button" class="btn btn-xs btn-outline-secondary mr-1" id="btnEditCopyVendor" title="Salin Supplier Baris 1 ke Semua Baris">
                                            <i class="fas fa-copy mr-1"></i> Samakan Supplier
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary mr-1" id="btnEditAddExtraDesc">
                                            <i class="fas fa-file-alt mr-1"></i> Tambah Uraian
                                        </button>
                                        <button type="button" class="btn btn-xs btn-primary font-weight-bold" id="btnEditAddSubPo">
                                            <i class="fas fa-plus mr-1"></i> Tambah Baris PO
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body p-1 table-responsive">
                                    <table class="table table-bordered table-sm mb-0 text-center" id="tableEditSubPo">
                                        <thead class="thead-light small">
                                            <tr>
                                                <th style="width: 4%">No</th>
                                                <th style="width: 24%">Description / Nominal <span class="text-danger">*</span></th>
                                                <th style="width: 24%">Nama Supplier <span class="text-danger">*</span></th>
                                                <th style="width: 22%">No. PO <span class="text-danger">*</span></th>
                                                <th style="width: 22%">No. GRN</th>
                                                <th style="width: 4%"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyEditSubPo">
                                            <!-- Populated dynamically by javascript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- URAIAN BERKAS TAMBAHAN untuk type PO (Edit Mode) -->
                            <div class="border rounded px-2 pt-1 pb-2 bg-white mb-2">
                                <small class="font-weight-bold text-secondary d-block mb-1"><i class="fas fa-file-alt mr-1"></i> Uraian Berkas (Dokumen Lampiran)</small>
                                <div id="editExtraDescContainer">
                                    <!-- Akan diisi secara dinamis saat buka modal edit -->
                                </div>
                            </div>
                        </div>

                        <!-- SECTION EDIT DOKUMEN STANDAR (COD, RFP, OFFSET, Kontrabon) -->
                        <div class="col-12" id="editStandardSection">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label class="small font-weight-bold">Nama Vendor</label>
                                    <input type="text" class="form-control form-control-sm" id="editVendor" name="vendor">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="small font-weight-bold">No. PO</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm" id="editNoPo" name="no_po" placeholder="e.g. POLC/2607/0389">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary" id="btnEditFetchPo" title="Ambil Data PO dari Proint ERP">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <small id="editPoLoadingText" class="text-primary font-weight-bold d-none" style="font-size: 10px;">
                                        <i class="fas fa-spinner fa-spin mr-1"></i> Memuat data ERP...
                                    </small>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="small font-weight-bold">No. GRN</label>
                                    <input type="text" class="form-control form-control-sm" id="editNoGrn" name="no_grn">
                                </div>
                                <div class="col-12 form-group mb-0">
                                    <label class="small font-weight-bold">Description (Uraian Berkas)</label>
                                    <textarea class="form-control form-control-sm" id="editDescription" name="description" rows="3" placeholder="Masukkan uraian dokumen (satu baris per deskripsi)..."></textarea>
                                    <small class="text-muted font-italic">Tips: Tekan Enter untuk memisahkan setiap uraian dokumen.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- MODAL DETAIL SERAH TERIMA PURCHASING                   -->
<!-- ======================================================= -->
<div class="modal fade" id="modalDetailPurchasing" tabindex="-1" role="dialog" aria-labelledby="modalDetailPurchasingLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title font-weight-bold" id="modalDetailPurchasingLabel">
                    <i class="fas fa-file-alt mr-1"></i> Detail Serah Terima Dokumen
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="purchasingDetailBody">
                <div class="text-center py-4">
                    <span class="spinner-border spinner-border-sm text-primary"></span> Memuat data...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- MODAL POPUP TANDA TANGAN DIGITAL ACCOUNTING            -->
<!-- ======================================================= -->
<div class="modal fade" id="modalSignature" tabindex="-1" role="dialog" aria-labelledby="modalSignatureLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title font-weight-bold" id="modalSignatureLabel">
                    <i class="fas fa-file-signature mr-1"></i> <span id="modalSignatureTitleText">Tanda Tangan Digital Accounting</span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Info Ringkas Dokumen (Single Mode) -->
                <div class="alert alert-light border mb-3 py-2 px-3 small" id="signDocSingleInfo">
                    <div class="row">
                        <div class="col-6"><strong>No. PO:</strong> <span id="signDocPo">-</span></div>
                        <div class="col-6"><strong>Type:</strong> <span id="signDocType">-</span></div>
                        <div class="col-12 mt-1"><strong>Vendor:</strong> <span id="signDocVendor">-</span></div>
                    </div>
                </div>

                <!-- Info Ringkas Dokumen (Batch Mode) -->
                <div class="alert mb-3 py-2 px-3 small" id="signDocBatchInfo" style="display: none; background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px;">
                    <div class="d-flex align-items-center">
                        <div class="mr-3 text-primary" style="font-size: 24px;">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div>
                            <div class="font-weight-bold text-dark" style="font-size: 13px;">Tanda Tangan Dokumen Massal</div>
                            <div class="text-muted" style="font-size: 11.5px;">
                                Anda akan menandatangani <span class="badge badge-primary px-2" id="signDocBatchCount">0</span> dokumen terpilih sekaligus dengan akun Anda.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bagian 1: Tanda Tangan Tersimpan (Jika Sudah Pernah TTD) -->
                <div id="sectionSavedSignature" class="text-center p-3 border rounded bg-light mb-3" style="display: none;">
                    <span class="badge badge-success px-2 py-1 mb-2"><i class="fas fa-check-circle mr-1"></i> Tanda Tangan Tersimpan Ditemukan</span>
                    <p class="small text-muted mb-2">Anda sudah memiliki tanda tangan tersimpan. Anda dapat langsung menggunakannya atau membuat tanda tangan baru:</p>
                    <div class="bg-white p-2 border rounded d-inline-block shadow-sm mb-3">
                        <img id="imgSavedSignature" src="" alt="Tanda Tangan Tersimpan" style="max-height: 90px; max-width: 260px;">
                    </div>
                    <div>
                        <button type="button" class="btn btn-success btn-sm font-weight-bold px-3 mr-1" id="btnUseSavedSig">
                            <i class="fas fa-check mr-1"></i> Gunakan Tanda Tangan Ini
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnChangeSig">
                            <i class="fas fa-pen-nib mr-1"></i> Ganti Tanda Tangan
                        </button>
                    </div>
                </div>

                <!-- Bagian 2: Area Gambar Tanda Tangan Baru (Canvas) -->
                <div id="sectionCanvasSignature" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="font-weight-bold small text-muted mb-0">Goreskan Tanda Tangan Anda:</label>
                        <button type="button" class="btn btn-outline-danger btn-xs" id="btnClearCanvas">
                            <i class="fas fa-eraser mr-1"></i> Hapus / Reset
                        </button>
                    </div>
                    <div class="signature-canvas-container mb-2">
                        <canvas id="signatureCanvas"></canvas>
                        <div class="signature-guide-line">Tanda Tangan Accounting</div>
                    </div>
                    <div class="custom-control custom-checkbox mb-3">
                        <input type="checkbox" class="custom-control-input" id="checkSaveSignature" checked>
                        <label class="custom-control-label small text-muted font-weight-normal" for="checkSaveSignature">
                            Simpan tanda tangan ini agar dapat langsung digunakan di dokumen berikutnya tanpa harus menggambar lagi
                        </label>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-link btn-sm text-muted p-0" id="btnCancelCanvas" style="display: none;">
                            <i class="fas fa-undo mr-1"></i> Batal, pakai tanda tangan tersimpan
                        </button>
                        <button type="button" class="btn btn-primary btn-sm font-weight-bold ml-auto" id="btnApplyCanvas">
                            <i class="fas fa-save mr-1"></i> Simpan & Terapkan Tanda Tangan
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- FLOATING ACTION BAR: TANDATANGANI MASSAL DOKUMEN        -->
<!-- ======================================================= -->
<div id="batchSignFloatingBar" class="batch-floating-bar" style="display: none;">
    <div class="batch-floating-content">
        <div class="batch-counter-badge" id="floatingSelectedBadge">
            <i class="fas fa-check-circle mr-2" style="font-size: 13px;"></i>
            <span id="floatingSelectedCount" class="mr-1">0</span> Dokumen Dipilih
        </div>
        <div class="batch-floating-divider d-none d-sm-block"></div>
        <button type="button" class="btn btn-batch-cancel" id="btnCancelBatchSelection" title="Batalkan semua pilihan">
            <i class="fas fa-times mr-1"></i> Batal Pilihan
        </button>
        <button type="button" class="btn btn-batch-submit" id="btnTriggerBatchSign">
            <i class="fas fa-file-signature mr-2"></i> <span id="batchFloatingBtnText">Tandatangani Dokumen Terpilih</span>
        </button>
    </div>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>

<!-- Select2 -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">

<!-- DataTables & SweetAlert -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    // ---------------------------------------------------------
    // FITUR DINAMIS KETERANGAN KHUSUS TYPE PO (DROPDOWN + MANUAL)
    // ---------------------------------------------------------
    var poKeteranganCache = <?= json_encode($poKeteranganOptions ?? ['Untuk dibuat Cek', 'Untuk di bayar']) ?>;

    function updatePoKeteranganElements(list) {
        if (!Array.isArray(list) || list.length === 0) return;
        poKeteranganCache = list;

        // 1. Update datalist
        var $dl = $('#listPoKeterangan');
        $dl.empty();
        list.forEach(function(item) {
            $dl.append($('<option>').val(item));
        });

        // 2. Update all dropdown menus
        var $menus = $('.menu-po-keterangan');
        $menus.empty();
        list.forEach(function(item) {
            $menus.append(`
                <a class="dropdown-item py-1 small d-flex align-items-center" href="javascript:void(0);">
                    <i class="fas fa-tag mr-2 text-primary" style="font-size: 10px;"></i>
                    <span>${$('<div>').text(item).html()}</span>
                </a>
            `);
        });
    }

    function fetchAndRefreshPoKeteranganList() {
        $.ajax({
            url: 'purchasing_serverside.php',
            type: 'POST',
            data: { action: 'get_po_keterangan_list' },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success' && Array.isArray(res.data)) {
                    updatePoKeteranganElements(res.data);
                }
            }
        });
    }

    // Inisialisasi awal dropdown menu keterangan PO
    updatePoKeteranganElements(poKeteranganCache);
    fetchAndRefreshPoKeteranganList();

    // Event klik pilihan dari dropdown keterangan PO
    $(document).on('click', '.menu-po-keterangan .dropdown-item', function(e) {
        e.preventDefault();
        var selectedVal = $(this).find('span').text().trim();
        var $group = $(this).closest('.po-keterangan-group, .edit-po-keterangan-group');
        $group.find('input[name*="type_keterangan"]').val(selectedVal).trigger('input').trigger('change');
    });

    // ---------------------------------------------------------
    // LOGIKA SELEKSI CHECKBOX & FLOATING ACTION BAR BATCH TTD
    // ---------------------------------------------------------
    var selectedDocIds = new Set();

    function updateFloatingBar() {
        var count = selectedDocIds ? selectedDocIds.size : 0;
        $('#floatingSelectedCount').text(count);

        if (count > 0) {
            $('#batchFloatingBtnText').text('Tandatangani (' + count + ' Dokumen)');
            if ($('#batchSignFloatingBar').is(':hidden')) {
                $('#batchSignFloatingBar').stop(true, true).fadeIn(250);
            }
        } else {
            $('#batchSignFloatingBar').stop(true, true).fadeOut(200);
            $('#checkAllRows').prop('checked', false).prop('indeterminate', false);
        }
    }

    function syncRowCheckboxes() {
        if (!selectedDocIds) return;
        var totalCheckboxes = $('.row-select-checkbox').length;
        var checkedCount = 0;

        $('.row-select-checkbox').each(function() {
            var id = parseInt($(this).val());
            if (selectedDocIds.has(id)) {
                $(this).prop('checked', true);
                checkedCount++;
            } else {
                $(this).prop('checked', false);
            }
        });

        if (totalCheckboxes > 0 && checkedCount === totalCheckboxes) {
            $('#checkAllRows').prop('checked', true).prop('indeterminate', false);
        } else if (checkedCount > 0) {
            $('#checkAllRows').prop('checked', false).prop('indeterminate', true);
        } else {
            $('#checkAllRows').prop('checked', false).prop('indeterminate', false);
        }

        updateFloatingBar();
    }

    window.clearAllSelections = function() {
        if (selectedDocIds) selectedDocIds.clear();
        $('.row-select-checkbox').prop('checked', false);
        $('#checkAllRows').prop('checked', false).prop('indeterminate', false);
        updateFloatingBar();
    };

    // ---------------------------------------------------------
    // 1. INISIALISASI DATATABLES SERVERSIDE
    // ---------------------------------------------------------
    var table = $('#purchasingTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'purchasing_serverside.php',
            type: 'POST',
            data: function(d) {
                d.filterTanggal = $('#filterTanggal').val();
                d.filterType = $('#filterType').val();
                d.filterStatus = $('#filterStatus').val();
            },
            error: function(xhr, error, thrown) {
                let msg = 'Gagal memuat data: ' + (thrown || 'Unknown error');
                try {
                    let json = JSON.parse(xhr.responseText);
                    if (json.error) {
                        msg += '\nError: ' + JSON.stringify(json.error);
                    }
                } catch (e) {
                    msg += '\nResponse: ' + xhr.responseText;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: msg
                });
            }
        },
        responsive: {
            details: {
                type: 'column',
                target: 0
            }
        },
        columnDefs: [
            { className: 'dtr-control', orderable: false, searchable: false, targets: 0 },
            { responsivePriority: 1, targets: 1 }, // Checkbox selalu tampil
            { responsivePriority: 2, targets: 2 }, // No selalu tampil
            { responsivePriority: 3, targets: 3 }  // Tanggal
        ],
        columns: [
            { 
                data: null, 
                defaultContent: '', 
                orderable: false, 
                searchable: false, 
                className: 'dtr-control text-center align-middle',
                width: '26px'
            },
            { 
                data: null, 
                orderable: false, 
                searchable: false, 
                className: 'text-center align-middle',
                render: function (data, type, row) {
                    if (row.status_ttd_raw === 'Sudah Ditandatangani') {
                        return '<span class="text-success" title="Sudah ditandatangani"><i class="fas fa-check-circle" style="font-size:13px;"></i></span>';
                    }
                    var isChecked = (selectedDocIds && selectedDocIds.has(row.id)) ? 'checked' : '';
                    return '<div class="custom-control custom-checkbox d-inline-block">' +
                           '<input type="checkbox" class="custom-control-input row-select-checkbox" id="chkRow_' + row.id + '" value="' + row.id + '" ' + isChecked + '>' +
                           '<label class="custom-control-label" for="chkRow_' + row.id + '"></label>' +
                           '</div>';
                }
            },
            { data: null, orderable: false, searchable: false, className: 'text-center align-middle', render: function (data, type, row, meta) { return '<span class="font-weight-bold text-muted small">' + (meta.row + meta.settings._iDisplayStart + 1) + '</span>'; } },
            { data: 'tanggal', className: 'text-center align-middle' },
            { data: 'type', className: 'text-center align-middle' },
            { data: 'description', className: 'text-left align-middle' },
            { data: 'vendor', className: 'text-left align-middle' },
            { data: 'no_po', className: 'text-center align-middle' },
            { data: 'no_grn', className: 'text-center align-middle' },
            { data: 'tanda_tangan', className: 'text-center align-middle' },
            { data: 'tanggal_diterima', className: 'text-center align-middle' },
            { data: 'aksi', orderable: false, searchable: false, className: 'text-center align-middle' }
        ],
        order: [[3, 'desc']],
        autoWidth: false,
        drawCallback: function() {
            syncRowCheckboxes();
        },
        language: {
            processing: "<div class='py-2'><span class='spinner-border spinner-border-sm text-primary'></span> Memproses data...</div>",
            lengthMenu: "Tampilkan _MENU_ data per halaman",
            zeroRecords: "Tidak ada data serah terima ditemukan",
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
        }
    });

    window.purchasingDataTable = table;

    // Handler checkbox per-baris
    $(document).on('change', '.row-select-checkbox', function() {
        var id = parseInt($(this).val());
        if ($(this).is(':checked')) {
            selectedDocIds.add(id);
        } else {
            selectedDocIds.delete(id);
        }
        syncRowCheckboxes();
    });

    // Handler master checkbox di header tabel
    $('#checkAllRows').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('.row-select-checkbox').each(function() {
            var id = parseInt($(this).val());
            $(this).prop('checked', isChecked);
            if (isChecked) {
                selectedDocIds.add(id);
            } else {
                selectedDocIds.delete(id);
            }
        });
        updateFloatingBar();
    });

    // Batal pilihan
    $('#btnCancelBatchSelection').on('click', function() {
        window.clearAllSelections();
    });

    // Klik tombol Floating Bar: Langsung buka modal tanda tangan untuk dokumen terpilih
    $('#btnTriggerBatchSign').on('click', function() {
        if (selectedDocIds.size === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Pilihan Kosong',
                text: 'Silakan centang dokumen yang ingin ditandatangani terlebih dahulu.'
            });
            return;
        }

        isBatchSignMode = true;
        activeSignRow = null;

        $('#modalSignatureTitleText').text('Tanda Tangan Massal (' + selectedDocIds.size + ' Dokumen)');
        $('#signDocSingleInfo').hide();
        $('#signDocBatchInfo').show();
        $('#signDocBatchCount').text(selectedDocIds.size);

        prepareSignatureModalUI();
        $('#modalSignature').modal('show');
    });

    // Event handler filter
    $('#filterTanggal, #filterType, #filterStatus').on('change', function() {
        table.ajax.reload();
    });

    $('#btnResetFilter').on('click', function() {
        $('#filterTanggal').val('');
        $('#filterType').val('');
        $('#filterStatus').val('');
        window.clearAllSelections();
        table.search('').draw();
    });

    // =========================================================
    // NOTIFIKASI DESKTOP BROWSER (ALA MODUL TICKET)
    // =========================================================
    var notifSupport = ('Notification' in window);
    var desktopNotifStorageKey = 'purchasing_desktop_notif_enabled';
    var desktopNotifEnabled = localStorage.getItem(desktopNotifStorageKey) === '1';
    var latestPurchasingId = 0;
    var purchasingPollTimer = null;

    var $desktopNotifToggle = $('#desktopNotifToggle');
    var $desktopNotifLabel = $('#desktopNotifLabel');

    if (!notifSupport) {
        $desktopNotifToggle.prop('disabled', true);
        $desktopNotifLabel.html('<i class="fas fa-bell-slash mr-1"></i> Notif Tidak Didukung');
        desktopNotifEnabled = false;
    } else if (Notification.permission !== 'granted' && desktopNotifEnabled) {
        desktopNotifEnabled = false;
        localStorage.setItem(desktopNotifStorageKey, '0');
    }
    $desktopNotifToggle.prop('checked', desktopNotifEnabled);

    function setDesktopNotifState(state) {
        desktopNotifEnabled = state;
        localStorage.setItem(desktopNotifStorageKey, state ? '1' : '0');
        $desktopNotifToggle.prop('checked', state);
        if (state) {
            startPurchasingPolling();
        } else {
            stopPurchasingPolling();
        }
    }

    $desktopNotifToggle.on('change', function() {
        if (!notifSupport) { this.checked = false; return; }
        if (this.checked) {
            if (Notification.permission === 'granted') {
                setDesktopNotifState(true);
                playChimeSound();
                Swal.fire({
                    icon: 'success',
                    title: 'Notifikasi Browser Aktif',
                    text: 'Anda akan menerima pemberitahuan desktop saat ada dokumen serah terima baru yang ditujukan kepada Anda.',
                    timer: 2500,
                    showConfirmButton: false
                });
                return;
            }
            if (Notification.permission === 'denied') {
                Swal.fire('Notifikasi Diblokir', 'Izinkan notifikasi untuk situs ini melalui pengaturan perizinan browser Anda.', 'warning');
                this.checked = false;
                setDesktopNotifState(false);
                return;
            }
            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    setDesktopNotifState(true);
                    playChimeSound();
                    Swal.fire({
                        icon: 'success',
                        title: 'Notifikasi Browser Aktif',
                        text: 'Anda akan menerima pemberitahuan desktop saat ada dokumen serah terima baru yang ditujukan kepada Anda.',
                        timer: 2500,
                        showConfirmButton: false
                    });
                } else {
                    $desktopNotifToggle.prop('checked', false);
                    setDesktopNotifState(false);
                }
            }).catch(function() {
                $desktopNotifToggle.prop('checked', false);
                setDesktopNotifState(false);
            });
        } else {
            setDesktopNotifState(false);
        }
    });

    // Nada Chime Halus Menggunakan Web Audio API (cross-browser tanpa load file audio eksternal)
    function playChimeSound() {
        try {
            var AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            var audioCtx = new AudioContext();
            var osc = audioCtx.createOscillator();
            var gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
            osc.frequency.setValueAtTime(880, audioCtx.currentTime + 0.1); // A5
            gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.4);
        } catch (e) {}
    }

    function maybeShowDesktopNotification(docInfo) {
        if (!notifSupport || !desktopNotifEnabled) return;
        if (Notification.permission !== 'granted') return;
        if (!docInfo || !docInfo.id) return;

        // Cegah notifikasi ganda dalam 60 detik
        var notifKey = 'purchasing_notif_shown_' + docInfo.id;
        var lastTime = localStorage.getItem(notifKey);
        var now = Date.now();
        if (lastTime && (now - parseInt(lastTime, 10) < 60000)) {
            return;
        }
        localStorage.setItem(notifKey, now);

        playChimeSound();

        var title = 'Dokumen Serah Terima Baru: ' + (docInfo.type || 'Purchasing');
        var body = 'Pengirim: ' + (docInfo.pengirim || '-') + '\n' +
                   'No. PO: ' + (docInfo.no_po || '-') + '\n' +
                   'Vendor: ' + (docInfo.vendor || '-') + '\n' +
                   'Segera lakukan verifikasi & tanda tangan.';

        var iconUrl = '/gg_app/dist/img/sumlogo.png';
        try {
            var n = new Notification(title, {
                body: body,
                icon: iconUrl,
                badge: iconUrl,
                tag: 'purchasing-doc-' + docInfo.id,
                renotify: true
            });
            n.onclick = function() {
                window.focus();
                n.close();
            };
        } catch (err) {
            console.error('Desktop notification error:', err);
        }
    }

    var isPollingPurchasing = false;
    function checkNewPurchasing() {
        if (isPollingPurchasing) return;
        isPollingPurchasing = true;

        $.ajax({
            url: 'check_new_purchasing.php',
            method: 'POST',
            dataType: 'json',
            global: false,
            data: { last_id: latestPurchasingId }
        }).done(function(resp) {
            isPollingPurchasing = false;
            if (!resp || !resp.success) return;

            var latest = parseInt(resp.latest_id || 0, 10);
            if (latestPurchasingId === 0 && latest > 0) {
                latestPurchasingId = latest;
                return;
            }

            if (resp.has_new && latest > latestPurchasingId) {
                latestPurchasingId = latest;
                table.ajax.reload(null, false);
                maybeShowDesktopNotification(resp.latest_doc || null);
            }
        }).fail(function() {
            isPollingPurchasing = false;
        });
    }

    function startPurchasingPolling() {
        if (purchasingPollTimer) clearInterval(purchasingPollTimer);
        checkNewPurchasing();
        purchasingPollTimer = setInterval(checkNewPurchasing, 8000);
    }

    function stopPurchasingPolling() {
        if (purchasingPollTimer) {
            clearInterval(purchasingPollTimer);
            purchasingPollTimer = null;
        }
    }

    // Jalankan polling awal jika notifikasi diaktifkan
    if (desktopNotifEnabled && Notification.permission === 'granted') {
        startPurchasingPolling();
    }

    // Helper row data responsive
    function getRowData($btn) {
        var $tr = $btn.closest('tr');
        var row = table.row($tr);
        var data = row.data();
        if (!data) {
            var $parentTr = $tr.prev('tr');
            data = table.row($parentTr).data();
        }
        return data;
    }

    // ---------------------------------------------------------
    // 2. AKSI: VIEW / DETAIL
    // ---------------------------------------------------------
    $(document).on('click', '.btn-detail', function() {
        var rowData = getRowData($(this));
        if (!rowData || !rowData.id) return;

        $('#purchasingDetailBody').html('<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Memuat detail data...</div>');
        $('#modalDetailPurchasing').modal('show');

        $.ajax({
            url: 'view_purchasing.php',
            type: 'GET',
            data: { id: rowData.id },
            success: function(html) {
                $('#purchasingDetailBody').html(html);
            },
            error: function() {
                $('#purchasingDetailBody').html('<div class="alert alert-danger mb-0"><i class="fas fa-exclamation-triangle mr-1"></i> Gagal memuat detail serah terima.</div>');
            }
        });
    });

    // ---------------------------------------------------------
    // 3. AKSI: EDIT DATA
    // ---------------------------------------------------------
    function createEditSubPoRow(subIndex, desc = '', vendor = '', noPo = '', noGrn = '-') {
        return `
            <tr class="edit-subpo-row" data-subidx="${subIndex}">
                <td class="align-middle font-weight-bold edit-subpo-num text-center">${subIndex + 1}</td>
                <td>
                    <input type="text" class="form-control form-control-sm input-editsubpo-desc" name="po_items[${subIndex}][desc]" value="${desc}" placeholder="e.g. Rp.25.000" required>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm input-editsubpo-vendor" name="po_items[${subIndex}][vendor]" value="${vendor}" placeholder="Nama Supplier" required>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm input-editsubpo-nopo" name="po_items[${subIndex}][no_po]" value="${noPo}" placeholder="e.g. POLC/2606/0671" required autocomplete="off">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary btn-fetch-editsubpo" title="Ambil Nominal & Supplier dari Proint ERP">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <small class="editsubpo-loading-text text-primary font-weight-bold d-none" style="font-size: 10px;">
                        <i class="fas fa-spinner fa-spin mr-1"></i> Memuat...
                    </small>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm input-editsubpo-nogrn" name="po_items[${subIndex}][no_grn]" value="${noGrn}" placeholder="-" title="${noGrn}">
                </td>
                <td class="align-middle text-center">
                    <button type="button" class="btn btn-outline-danger btn-xs btn-remove-editsubpo" title="Hapus Sub-PO">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
    }

    function toggleEditTypeSections(typeVal) {
        if (typeVal === 'PO' || typeVal === 'PO Untuk dibuat Cek') {
            $('#editMultiPoSection').show();
            $('#editStandardSection').hide();
            $('#btnEditPoKetDropdownWrapper').removeClass('d-none');
            $('#btnEditRfpFetchWrapper').addClass('d-none');
            $('#editRfpLoadingText').addClass('d-none');
            $('#editTypeKeterangan').attr('list', 'listPoKeterangan').attr('placeholder', 'Pilih / ketik keterangan...');
            if (!$('#editTypeKeterangan').val()) {
                $('#editTypeKeterangan').val('Untuk dibuat Cek');
            }
            $('#editMultiPoSection input').prop('disabled', false);
            $('#editStandardSection input, #editStandardSection textarea').prop('disabled', true);
        } else {
            $('#editMultiPoSection').hide();
            $('#editStandardSection').show();
            $('#btnEditPoKetDropdownWrapper').addClass('d-none');
            $('#editTypeKeterangan').removeAttr('list');
            if ($('#editTypeKeterangan').val() === 'Untuk dibuat Cek' || (Array.isArray(poKeteranganCache) && poKeteranganCache.indexOf($('#editTypeKeterangan').val()) !== -1)) {
                $('#editTypeKeterangan').val('');
            }
            $('#editMultiPoSection input').prop('disabled', true);
            $('#editStandardSection input, #editStandardSection textarea').prop('disabled', false);

            if (typeVal === 'RFP') {
                $('#btnEditRfpFetchWrapper').removeClass('d-none');
                $('#editTypeKeterangan').attr('placeholder', 'No. RFP (e.g. 2607.2106)');
                $('#editNoPo').prop('required', false).attr('placeholder', 'e.g. POLC/2607/0389 (opsional)');
            } else {
                $('#btnEditRfpFetchWrapper').addClass('d-none');
                $('#editRfpLoadingText').addClass('d-none');
                $('#editNoPo').prop('required', true).attr('placeholder', 'e.g. POLC/2607/0389');
                if (typeVal === 'COD') {
                    $('#editTypeKeterangan').attr('placeholder', 'Keterangan COD (e.g. Tunai/Toko)');
                } else if (typeVal === 'OFFSET') {
                    $('#editTypeKeterangan').attr('placeholder', 'Keterangan OFFSET (opsional)');
                } else if (typeVal === 'Kontrabon') {
                    $('#editTypeKeterangan').attr('placeholder', 'Keterangan Kontrabon (opsional)');
                } else {
                    $('#editTypeKeterangan').attr('placeholder', 'Keterangan / No. Type (opsional)');
                }
            }
        }
    }

    $('#editType').on('change', function() {
        toggleEditTypeSections($(this).val());
    });

    $('#btnEditAddSubPo').on('click', function() {
        var currentCount = $('#tbodyEditSubPo tr.edit-subpo-row').length;
        var firstVendor = $('#tbodyEditSubPo tr.edit-subpo-row:first').find('.input-editsubpo-vendor').val() || '';
        $('#tbodyEditSubPo').append(createEditSubPoRow(currentCount, '', firstVendor, '', '-'));
    });

    $(document).on('click', '.btn-remove-editsubpo', function() {
        if ($('#tbodyEditSubPo tr.edit-subpo-row').length <= 1) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Minimal harus ada 1 baris PO!' });
            return;
        }
        $(this).closest('tr.edit-subpo-row').remove();
        $('#tbodyEditSubPo tr.edit-subpo-row').each(function(idx) {
            $(this).find('.edit-subpo-num').text(idx + 1);
            $(this).find('.input-editsubpo-desc').attr('name', `po_items[${idx}][desc]`);
            $(this).find('.input-editsubpo-vendor').attr('name', `po_items[${idx}][vendor]`);
            $(this).find('.input-editsubpo-nopo').attr('name', `po_items[${idx}][no_po]`);
            $(this).find('.input-editsubpo-nogrn').attr('name', `po_items[${idx}][no_grn]`);
        });
    });

    $('#btnEditCopyVendor').on('click', function() {
        var firstVendor = $('#tbodyEditSubPo tr.edit-subpo-row:first').find('.input-editsubpo-vendor').val() || '';
        if (!firstVendor) {
            Swal.fire({ icon: 'info', title: 'Informasi', text: 'Silakan isi nama supplier pada baris 1 terlebih dahulu!' });
            return;
        }
        $('#tbodyEditSubPo tr.edit-subpo-row').each(function() {
            $(this).find('.input-editsubpo-vendor').val(firstVendor);
        });
    });

    $(document).on('click', '.btn-edit', function() {
        var rowData = getRowData($(this));
        if (!rowData || !rowData.id) return;

        $('#editRowId').val(rowData.id);
        $('#editTanggal').val(rowData.tanggal_raw || '');
        $('#editPengirim').val(rowData.pengirim || '<?= addslashes($currentUserPengirim) ?>');
        // Penerima tidak perlu diisi — otomatis dari TTD
        
        var isMultiPo = rowData.is_multi_po || (rowData.type_raw === 'PO') || (rowData.type_keterangan && rowData.type_keterangan.indexOf('Untuk dibuat Cek') !== -1);
        
        if (isMultiPo) {
            $('#editType').val('PO');
            $('#editTypeKeterangan').val(rowData.type_keterangan || 'Untuk dibuat Cek');
            toggleEditTypeSections('PO');

            $('#tbodyEditSubPo').empty();
            if (Array.isArray(rowData.po_items) && rowData.po_items.length > 0) {
                rowData.po_items.forEach(function(sub, idx) {
                    $('#tbodyEditSubPo').append(createEditSubPoRow(idx, sub.desc || '', sub.vendor || '', sub.no_po || '', sub.no_grn || '-'));
                });
            } else {
                $('#tbodyEditSubPo').append(createEditSubPoRow(0, '', '', '', '-'));
            }

            // Populate uraian berkas tambahan (Dokumen Lampiran) — desc yang bukan dari sub-PO
            var subPoDescs = [];
            if (Array.isArray(rowData.po_items)) {
                rowData.po_items.forEach(function(sub) {
                    if (sub.desc && sub.desc.trim()) subPoDescs.push(sub.desc.trim().toLowerCase());
                });
            }
            var extraDescs = [];
            if (Array.isArray(rowData.descriptions_raw)) {
                rowData.descriptions_raw.forEach(function(d) {
                    var dClean = (d || '').trim();
                    if (dClean && subPoDescs.indexOf(dClean.toLowerCase()) === -1) {
                        extraDescs.push(dClean);
                    }
                });
            }

            $('#editExtraDescContainer').empty();
            if (extraDescs.length === 0) {
                extraDescs = [''];
            }
            extraDescs.forEach(function(desc, i) {
                $('#editExtraDescContainer').append(buildEditExtraDescItem(i, desc));
            });
        } else {
            var typeText = rowData.type_raw || 'COD';
            var baseType = typeText;
            $('#editType').val(baseType);
            $('#editTypeKeterangan').val(rowData.type_keterangan || '');
            toggleEditTypeSections(baseType);

            $('#editVendor').val((rowData.vendor_raw || '').replace(/<[^>]*>?/gm, '').trim());
            $('#editNoPo').val((rowData.no_po_raw || '').replace(/<[^>]*>?/gm, '').trim());
            $('#editNoGrn').val((rowData.no_grn_raw || '').replace(/<[^>]*>?/gm, '').trim());
            
            if (Array.isArray(rowData.descriptions_raw)) {
                $('#editDescription').val(rowData.descriptions_raw.join("\n"));
            } else {
                $('#editDescription').val((rowData.description || '').replace(/<[^>]*>?/gm, "\n").trim());
            }
        }

        $('#modalEditPurchasing').modal('show');
    });

    function buildEditExtraDescItem(idx, val) {
        return `<div class="input-group input-group-sm mb-1 edit-extra-desc-item">
            <div class="input-group-prepend">
                <span class="input-group-text font-weight-bold px-2 edit-extra-desc-num">${idx + 1}</span>
            </div>
            <input type="text" class="form-control form-control-sm" name="descriptions[]" value="${$('<div>').text(val).html()}" placeholder="e.g. PO Asli + SJ, Invoice + FP...">
            <div class="input-group-append">
                <button type="button" class="btn btn-outline-danger btn-xs btn-edit-remove-extra-desc" title="Hapus uraian ini"><i class="fas fa-times"></i></button>
            </div>
        </div>`;
    }

    $('#btnEditAddExtraDesc').on('click', function() {
        var count = $('#editExtraDescContainer .edit-extra-desc-item').length;
        $('#editExtraDescContainer').append(buildEditExtraDescItem(count, ''));
    });

    $(document).on('click', '.btn-edit-remove-extra-desc', function() {
        var $container = $('#editExtraDescContainer');
        if ($container.find('.edit-extra-desc-item').length <= 1) {
            $(this).closest('.edit-extra-desc-item').find('input').val('');
            return;
        }
        $(this).closest('.edit-extra-desc-item').remove();
        $container.find('.edit-extra-desc-item').each(function(i) {
            $(this).find('.edit-extra-desc-num').text(i + 1);
        });
    });


    $('#formEditPurchasing').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize() + '&action=edit';

        $.ajax({
            url: 'purchasing_serverside.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil Diperbarui!',
                        text: res.message || 'Data serah terima berhasil diperbarui!',
                        confirmButtonColor: '#28a745'
                    }).then(function() {
                        $('#modalEditPurchasing').modal('hide');
                        fetchAndRefreshPoKeteranganList();
                        table.ajax.reload(null, false);
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: res.message || 'Gagal memperbarui data.' });
                }
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Koneksi server gagal: ' + xhr.statusText });
            }
        });
    });

    // ---------------------------------------------------------
    // 4. AKSI: DELETE DATA
    // ---------------------------------------------------------
    $(document).on('click', '.btn-delete', function() {
        var rowData = getRowData($(this));
        if (!rowData || !rowData.id) return;

        var poLabel = rowData.is_multi_po ? 'Batch Multi-PO (Untuk dibuat Cek)' : (rowData.no_po_raw || '');
        Swal.fire({
            title: 'Hapus Data Dokumen?',
            text: `Apakah Anda yakin ingin menghapus data untuk: ${poLabel}? Data yang dihapus tidak dapat dikembalikan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash mr-1"></i> Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'purchasing_serverside.php',
                    type: 'POST',
                    data: { action: 'delete', id: rowData.id },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Terhapus!',
                                text: res.message || 'Data serah terima berhasil dihapus.',
                                confirmButtonColor: '#28a745'
                            });
                            table.ajax.reload(null, false);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Gagal', text: res.message || 'Gagal menghapus data.' });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Koneksi server gagal: ' + xhr.statusText });
                    }
                });
            }
        });
    });

    // ---------------------------------------------------------
    // 5. AKSI: TANDA TANGAN DIGITAL ACCOUNTING (ULTRA-SMOOTH)
    // ---------------------------------------------------------
    // 5. AKSI: TANDA TANGAN DIGITAL ACCOUNTING (NATIVE SMOOTH ENGINE)
    // ---------------------------------------------------------
    var activeSignRow = null;
    var canvas = document.getElementById('signatureCanvas');
    var signaturePad = null;

    function initSmoothSignaturePad(cvs) {
        if (!cvs) return null;
        var ctx = cvs.getContext('2d');
        var isDrawing = false;
        var hasDrawn = false;
        var lastX = 0, lastY = 0;
        var dpr = window.devicePixelRatio || 1;

        function getCoords(e) {
            var rect = cvs.getBoundingClientRect();
            var clientX = 0, clientY = 0;
            if (e.touches && e.touches.length > 0) {
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            } else if (e.changedTouches && e.changedTouches.length > 0) {
                clientX = e.changedTouches[0].clientX;
                clientY = e.changedTouches[0].clientY;
            } else {
                clientX = e.clientX;
                clientY = e.clientY;
            }
            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        }

        function resize() {
            var container = cvs.parentElement;
            if (!container) return;
            var w = container.clientWidth || 450;
            var h = 180;

            var prevData = hasDrawn ? cvs.toDataURL() : null;

            dpr = window.devicePixelRatio || 1;
            cvs.width = Math.round(w * dpr);
            cvs.height = Math.round(h * dpr);
            cvs.style.width = w + 'px';
            cvs.style.height = h + 'px';

            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.scale(dpr, dpr);
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.lineWidth = 2.5;
            ctx.strokeStyle = '#111827';
            ctx.fillStyle = '#111827';

            if (prevData) {
                var img = new Image();
                img.onload = function() {
                    ctx.drawImage(img, 0, 0, w, h);
                };
                img.src = prevData;
            }
        }

        function start(e) {
            if (e.button !== undefined && e.button !== 0) return;
            e.preventDefault();
            isDrawing = true;
            hasDrawn = true;
            var pos = getCoords(e);
            lastX = pos.x;
            lastY = pos.y;
            ctx.beginPath();
            ctx.arc(lastX, lastY, 1.25, 0, Math.PI * 2);
            ctx.fill();
        }

        function move(e) {
            if (!isDrawing) return;
            e.preventDefault();
            var pos = getCoords(e);
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            lastX = pos.x;
            lastY = pos.y;
            hasDrawn = true;
        }

        function stop(e) {
            if (!isDrawing) return;
            isDrawing = false;
            ctx.beginPath();
        }

        // Attach listeners
        cvs.addEventListener('mousedown', start, false);
        window.addEventListener('mousemove', move, false);
        window.addEventListener('mouseup', stop, false);

        cvs.addEventListener('touchstart', start, { passive: false });
        window.addEventListener('touchmove', move, { passive: false });
        window.addEventListener('touchend', stop, { passive: false });

        return {
            clear: function() {
                ctx.save();
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.clearRect(0, 0, cvs.width, cvs.height);
                ctx.restore();
                hasDrawn = false;
            },
            isEmpty: function() {
                return !hasDrawn;
            },
            resize: resize,
            toDataURL: function(format) {
                return cvs.toDataURL(format || 'image/png');
            }
        };
    }

    window.initSmoothSignaturePad = initSmoothSignaturePad;

    if (canvas) {
        signaturePad = initSmoothSignaturePad(canvas);
    }

    function resizeCanvas() {
        if (signaturePad) {
            signaturePad.resize();
        }
    }

    var isBatchSignMode = false;

    function prepareSignatureModalUI() {
        var savedSig = localStorage.getItem('purchasing_accounting_signature');
        if (savedSig) {
            $('#imgSavedSignature').attr('src', savedSig);
            $('#sectionSavedSignature').show();
            $('#sectionCanvasSignature').hide();
            $('#btnCancelCanvas').show();
        } else {
            $('#sectionSavedSignature').hide();
            $('#sectionCanvasSignature').show();
            $('#btnCancelCanvas').hide();
        }
    }

    // Buka Modal Signature (Single Mode)
    $(document).on('click', '.btn-signature', function() {
        isBatchSignMode = false;
        activeSignRow = getRowData($(this));
        if (!activeSignRow) return;

        $('#modalSignatureTitleText').text('Tanda Tangan Digital Accounting');
        $('#signDocSingleInfo').show();
        $('#signDocBatchInfo').hide();

        $('#signDocPo').text(activeSignRow.no_po_raw || '-');
        $('#signDocType').text(activeSignRow.type_raw || '-');
        $('#signDocVendor').text(activeSignRow.vendor_raw || '-');

        prepareSignatureModalUI();
        $('#modalSignature').modal('show');
    });

    $('#modalSignature').on('shown.bs.modal', function() {
        setTimeout(function() {
            resizeCanvas();
        }, 80);
    });

    // Tombol Ganti Tanda Tangan
    $('#btnChangeSig').on('click', function() {
        $('#sectionSavedSignature').slideUp(200, function() {
            $('#sectionCanvasSignature').slideDown(200, function() {
                resizeCanvas();
            });
        });
    });

    // Tombol Batal Ganti, kembali ke tanda tangan tersimpan
    $('#btnCancelCanvas').on('click', function() {
        $('#sectionCanvasSignature').slideUp(200, function() {
            $('#sectionSavedSignature').slideDown(200);
        });
    });

    // Hapus / Reset canvas gambar
    $('#btnClearCanvas').on('click', function() {
        if (signaturePad) {
            signaturePad.clear();
        }
    });

    // Gunakan Tanda Tangan Tersimpan
    $('#btnUseSavedSig').on('click', function() {
        var savedSig = localStorage.getItem('purchasing_accounting_signature');
        if (!savedSig) {
            Swal.fire({
                icon: 'warning',
                title: 'Tanda Tangan Tidak Ditemukan',
                text: 'Silakan buat tanda tangan baru terlebih dahulu pada area canvas!'
            });
            return;
        }
        var msg = isBatchSignMode 
            ? (selectedDocIds.size + ' dokumen berhasil ditandatangani menggunakan tanda tangan tersimpan!')
            : 'Tanda tangan tersimpan berhasil digunakan! Dokumen telah diverifikasi & ditandatangani oleh bagian Accounting.';
        applySignatureSuccess(msg, savedSig);
    });

    // Simpan & Terapkan Tanda Tangan dari Canvas
    $('#btnApplyCanvas').on('click', function() {
        if (!signaturePad || signaturePad.isEmpty()) {
            Swal.fire({
                icon: 'warning',
                title: 'Tanda Tangan Kosong',
                text: 'Silakan goreskan tanda tangan Anda pada area canvas yang disediakan!'
            });
            return;
        }

        var newSigData = signaturePad.toDataURL('image/png');
        if ($('#checkSaveSignature').is(':checked')) {
            localStorage.setItem('purchasing_accounting_signature', newSigData);
        }

        var msg = isBatchSignMode 
            ? (selectedDocIds.size + ' dokumen berhasil ditandatangani!')
            : 'Tanda tangan baru berhasil dibuat, disimpan, dan diterapkan pada dokumen ini!';
        applySignatureSuccess(msg, newSigData);
    });

    function applySignatureSuccess(msg, sigData) {
        if (isBatchSignMode && selectedDocIds.size > 0) {
            var idsArray = Array.from(selectedDocIds);
            $.ajax({
                url: 'purchasing_serverside.php',
                type: 'POST',
                data: { 
                    action: 'batch_sign', 
                    ids: idsArray,
                    signature_data: sigData || ''
                },
                dataType: 'json',
                success: function(res) {
                    if (res && res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil Ditandatangani!',
                            text: res.message || msg,
                            confirmButtonColor: '#28a745'
                        }).then(function() {
                            $('#modalSignature').modal('hide');
                            window.clearAllSelections();
                            table.ajax.reload(null, false);
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Ditandatangani',
                            text: (res && res.message) ? res.message : 'Terjadi kendala saat verifikasi tanda tangan massal.'
                        });
                    }
                },
                error: function(xhr) {
                    var errMsg = 'Terjadi kesalahan pada server saat verifikasi tanda tangan massal.';
                    try {
                        var json = JSON.parse(xhr.responseText);
                        if (json && json.message) errMsg = json.message;
                    } catch(e) {}
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Ditandatangani',
                        text: errMsg
                    });
                }
            });
            return;
        }

        if (activeSignRow && activeSignRow.id) {
            $.ajax({
                url: 'purchasing_serverside.php',
                type: 'POST',
                data: { 
                    action: 'sign', 
                    id: activeSignRow.id,
                    signature_data: sigData || ''
                },
                dataType: 'json',
                success: function(res) {
                    if (res && res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil Ditandatangani!',
                            text: msg,
                            confirmButtonColor: '#28a745'
                        }).then(function() {
                            $('#modalSignature').modal('hide');
                            table.ajax.reload(null, false);
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Ditandatangani',
                            text: (res && res.message) ? res.message : 'Terjadi kendala saat verifikasi tanda tangan.'
                        });
                    }
                },
                error: function(xhr) {
                    var errMsg = 'Terjadi kesalahan pada server saat verifikasi tanda tangan.';
                    try {
                        var json = JSON.parse(xhr.responseText);
                        if (json && json.message) errMsg = json.message;
                    } catch(e) {}
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Ditandatangani',
                        text: errMsg
                    });
                }
            });
        }
    }

    // ---------------------------------------------------------
    // 6. LOGIKA FORM DINAMIS (TAMBAH DATA & TAMBAH BARIS)
    // ---------------------------------------------------------
    var rowIndex = 0;

    function createSubPoRow(rowIndex, subIndex, desc = '', vendor = '', noPo = '', noGrn = '-') {
        return `
            <tr class="subpo-row" data-subidx="${subIndex}">
                <td class="align-middle font-weight-bold subpo-num text-center">${subIndex + 1}</td>
                <td>
                    <input type="text" class="form-control form-control-sm input-subpo-desc" name="items[${rowIndex}][po_items][${subIndex}][desc]" value="${desc}" placeholder="e.g. Rp.25.000" required>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm input-subpo-vendor" name="items[${rowIndex}][po_items][${subIndex}][vendor]" value="${vendor}" placeholder="Nama Supplier" required>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm input-subpo-nopo" name="items[${rowIndex}][po_items][${subIndex}][no_po]" value="${noPo}" placeholder="e.g. POLC/2606/0671" required autocomplete="off">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary btn-fetch-subpo" title="Ambil Nominal & Supplier dari Proint ERP">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <small class="subpo-loading-text text-primary font-weight-bold d-none" style="font-size: 10px;">
                        <i class="fas fa-spinner fa-spin mr-1"></i> Memuat...
                    </small>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm input-subpo-nogrn" name="items[${rowIndex}][po_items][${subIndex}][no_grn]" value="${noGrn}" placeholder="-" title="${noGrn}">
                </td>
                <td class="align-middle text-center">
                    <button type="button" class="btn btn-outline-danger btn-xs btn-remove-subpo" title="Hapus Sub-PO">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
    }

    function createRowTemplate(index, defaultType = 'PO') {
        var isMulti = (defaultType === 'PO' || defaultType === 'PO Untuk dibuat Cek');
        return `
            <tr class="item-row" data-row="${index}">
                <td class="align-middle font-weight-bold row-num text-center">${index + 1}</td>
                <td class="align-top text-left" style="min-width: 170px;">
                    <select class="form-control form-control-sm select-type mb-1 font-weight-bold" name="items[${index}][type]" required>
                        <option value="PO" ${(defaultType === 'PO' || defaultType === 'PO Untuk dibuat Cek') ? 'selected' : ''}>PO</option>
                        <option value="COD" ${defaultType === 'COD' ? 'selected' : ''}>COD</option>
                        <option value="RFP" ${defaultType === 'RFP' ? 'selected' : ''}>RFP</option>
                        <option value="OFFSET" ${defaultType === 'OFFSET' ? 'selected' : ''}>OFFSET</option>
                        <option value="Kontrabon" ${defaultType === 'Kontrabon' ? 'selected' : ''}>Kontrabon</option>
                    </select>
                    <div class="input-group input-group-sm po-keterangan-group">
                        <input type="text" class="form-control form-control-sm input-type-keterangan" name="items[${index}][type_keterangan]" list="listPoKeterangan" value="${isMulti ? 'Untuk dibuat Cek' : ''}" placeholder="${isMulti ? 'Pilih / ketik keterangan...' : (defaultType === 'RFP' ? 'No. RFP (e.g. 2607.2106)' : 'Keterangan / No. Type (opsional)')}" autocomplete="off">
                        <div class="input-group-append po-ket-dropdown-wrapper ${isMulti ? '' : 'd-none'}">
                            <button class="btn btn-outline-secondary dropdown-toggle px-1" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Pilih keterangan PO"></button>
                            <div class="dropdown-menu dropdown-menu-right menu-po-keterangan shadow-sm" style="max-height: 200px; overflow-y: auto;">
                                <!-- Terisi dinamis -->
                            </div>
                        </div>
                        <div class="input-group-append rfp-fetch-wrapper ${defaultType === 'RFP' ? '' : 'd-none'}">
                            <button class="btn btn-outline-secondary btn-fetch-rfp" type="button" title="Ambil Data Vendor dari No. RFP di Proint ERP">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <small class="rfp-loading-text text-primary font-weight-bold d-none" style="font-size: 10px;">
                        <i class="fas fa-spinner fa-spin mr-1"></i> Memuat ERP...
                    </small>
                </td>

                <!-- BAGIAN 1: MULTI-PO SUBTABLE (Default untuk PO) -->
                <td colspan="4" class="align-top p-2 cell-multipo ${isMulti ? '' : 'd-none'}">
                    <div class="card card-outline card-primary mb-0 shadow-none border">
                        <div class="card-header py-1 px-2 d-flex justify-content-between align-items-center bg-light">
                            <span class="font-weight-bold text-primary small">
                                <i class="fas fa-list-ol mr-1"></i> Rincian PO
                            </span>
                            <div>
                                <button type="button" class="btn btn-xs btn-outline-secondary btn-copy-vendor mr-1" data-row="${index}" title="Salin Supplier Baris 1 ke Semua Baris">
                                    <i class="fas fa-copy mr-1"></i> Samakan Supplier
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-secondary mr-1 btn-add-extra-desc" data-row="${index}">
                                    <i class="fas fa-file-alt mr-1"></i> Tambah Uraian
                                </button>
                                <button type="button" class="btn btn-xs btn-primary font-weight-bold btn-add-subpo" data-row="${index}">
                                    <i class="fas fa-plus mr-1"></i> Tambah Baris PO
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-1 table-responsive">
                            <table class="table table-bordered table-sm mb-0 bg-white subpo-table text-center" id="subpo-table-${index}">
                                <thead class="thead-light small">
                                    <tr>
                                        <th style="width: 4%">No</th>
                                        <th style="width: 24%">Description / Nominal <span class="text-danger">*</span></th>
                                        <th style="width: 24%">Nama Supplier <span class="text-danger">*</span></th>
                                        <th style="width: 22%">No. PO <span class="text-danger">*</span></th>
                                        <th style="width: 22%">No. GRN</th>
                                        <th style="width: 4%"></th>
                                    </tr>
                                </thead>
                                <tbody class="subpo-tbody">
                                    ${createSubPoRow(index, 0, '', '', '', '-')}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- URAIAN BERKAS TAMBAHAN untuk type PO -->
                    <div class="mt-1 extra-desc-section border rounded px-2 pt-1 pb-2 bg-white">
                        <small class="font-weight-bold text-secondary d-block mb-1"><i class="fas fa-file-alt mr-1"></i> Uraian Berkas (Dokumen Lampiran)</small>
                        <div class="extra-desc-container" id="extra-desc-container-${index}">
                            <div class="input-group input-group-sm mb-1 extra-desc-item">
                                <div class="input-group-prepend">
                                    <span class="input-group-text font-weight-bold px-2 extra-desc-num">1</span>
                                </div>
                                <input type="text" class="form-control form-control-sm input-extra-desc" name="items[${index}][descriptions][]" placeholder="e.g. PO Asli + SJ, Invoice + FP..." ${isMulti ? '' : 'disabled'}>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-danger btn-xs btn-remove-extra-desc" title="Hapus uraian ini"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </td>

                <!-- BAGIAN 2: STANDAR DOKUMEN TUNGGAL (Untuk COD, RFP, OFFSET, Kontrabon) -->
                <td class="align-top text-left cell-std-desc ${isMulti ? 'd-none' : ''}">
                    <div class="desc-container" id="desc-container-${index}">
                        <div class="input-group input-group-sm mb-1 desc-item">
                            <div class="input-group-prepend">
                                <span class="input-group-text font-weight-bold px-2 desc-num">1</span>
                            </div>
                            <input type="text" class="form-control form-control-sm input-std-desc" name="items[${index}][descriptions][]" placeholder="Uraian dokumen / pembayaran..." ${isMulti ? 'disabled' : 'required'}>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-xs btn-add-desc" data-row="${index}">
                        <i class="fas fa-plus mr-1"></i> Tambah Uraian
                    </button>
                </td>
                <td class="align-top cell-std-vendor ${isMulti ? 'd-none' : ''}">
                    <input type="text" class="form-control form-control-sm input-std-vendor" name="items[${index}][vendor]" placeholder="Nama Vendor..." ${isMulti ? 'disabled' : 'required'}>
                </td>
                <td class="align-top cell-std-po ${isMulti ? 'd-none' : ''}">
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm input-std-po" name="items[${index}][no_po]" placeholder="${defaultType === 'RFP' ? 'e.g. POLC/2607/0389 (opsional)' : 'e.g. POLC/2607/0389'}" ${isMulti ? 'disabled' : (defaultType === 'RFP' ? '' : 'required')} autocomplete="off">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary btn-fetch-po" title="Ambil Data PO dari Proint ERP">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <small class="po-loading-text text-primary font-weight-bold d-none" style="font-size: 10px;">
                        <i class="fas fa-spinner fa-spin mr-1"></i> Memuat data ERP...
                    </small>
                </td>
                <td class="align-top cell-std-grn ${isMulti ? 'd-none' : ''}">
                    <input type="text" class="form-control form-control-sm input-std-grn" name="items[${index}][no_grn]" placeholder="e.g. GRNLC/2607/0755" ${isMulti ? 'disabled' : ''}>
                </td>

                <!-- AKSI ROW -->
                <td class="align-middle text-center">
                    <button type="button" class="btn btn-danger btn-xs btn-remove-row" title="Hapus Baris Dokumen">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }

    $(document).on('change', '.select-type', function() {
        var val = $(this).val();
        var $tr = $(this).closest('tr.item-row');
        var $inputKet = $tr.find('.input-type-keterangan');

        if (val === 'PO' || val === 'PO Untuk dibuat Cek') {
            $tr.find('.cell-multipo').removeClass('d-none');
            $tr.find('.cell-std-desc, .cell-std-vendor, .cell-std-po, .cell-std-grn').addClass('d-none');
            
            $tr.find('.cell-multipo input.input-extra-desc').prop('disabled', false);
            $tr.find('.cell-multipo .subpo-tbody input').prop('disabled', false);
            $tr.find('.cell-std-desc input, .cell-std-vendor input, .cell-std-po input, .cell-std-grn input').prop('disabled', true);
            
            $tr.find('.po-ket-dropdown-wrapper').removeClass('d-none');
            $tr.find('.rfp-fetch-wrapper').addClass('d-none');
            $tr.find('.rfp-loading-text').addClass('d-none');
            $inputKet.attr('list', 'listPoKeterangan').attr('placeholder', 'Pilih / ketik keterangan...');
            if (!$inputKet.val()) {
                $inputKet.val('Untuk dibuat Cek');
            }
        } else {
            $tr.find('.cell-multipo').addClass('d-none');
            $tr.find('.cell-std-desc, .cell-std-vendor, .cell-std-po, .cell-std-grn').removeClass('d-none');
            
            $tr.find('.cell-multipo input').prop('disabled', true);
            $tr.find('.cell-std-desc input, .cell-std-vendor input, .cell-std-po input, .cell-std-grn input').prop('disabled', false);

            $tr.find('.po-ket-dropdown-wrapper').addClass('d-none');
            $inputKet.removeAttr('list');

            if ($inputKet.val() === 'Untuk dibuat Cek' || (Array.isArray(poKeteranganCache) && poKeteranganCache.indexOf($inputKet.val()) !== -1)) {
                $inputKet.val('');
            }
            if (val === 'RFP') {
                $tr.find('.rfp-fetch-wrapper').removeClass('d-none');
                $inputKet.attr('placeholder', 'No. RFP (e.g. 2607.2106)');
                $tr.find('.input-std-po').prop('required', false).attr('placeholder', 'e.g. POLC/2607/0389 (opsional)');
                var existingRfp = $.trim($inputKet.val());
                if (existingRfp) {
                    fetchRfpDetails(existingRfp, $tr, $inputKet);
                }
            } else {
                $tr.find('.rfp-fetch-wrapper').addClass('d-none');
                $tr.find('.rfp-loading-text').addClass('d-none');
                $tr.find('.input-std-po').prop('required', true).attr('placeholder', 'e.g. POLC/2607/0389');
                if (val === 'COD') {
                    $inputKet.attr('placeholder', 'Keterangan COD (e.g. Tunai/Toko)');
                    var existingPo = $.trim($tr.find('.input-std-po').val());
                    if (existingPo) {
                        fetchPoDetailsForCod(existingPo, $tr, $tr.find('.input-std-po'));
                    }
                } else if (val === 'OFFSET') {
                    $inputKet.attr('placeholder', 'Keterangan OFFSET (opsional)');
                } else if (val === 'Kontrabon') {
                    $inputKet.attr('placeholder', 'Keterangan Kontrabon (opsional)');
                } else {
                    $inputKet.attr('placeholder', 'Keterangan / No. Type (opsional)');
                }
            }
        }
    });

    // Handler tambah uraian di section extra-desc (type PO)
    $(document).on('click', '.btn-add-extra-desc', function() {
        var rIdx = $(this).attr('data-row');
        var $container = $(`#extra-desc-container-${rIdx}`);
        var count = $container.find('.extra-desc-item').length;
        var newItem = `
            <div class="input-group input-group-sm mb-1 extra-desc-item">
                <div class="input-group-prepend">
                    <span class="input-group-text font-weight-bold px-2 extra-desc-num">${count + 1}</span>
                </div>
                <input type="text" class="form-control form-control-sm input-extra-desc" name="items[${rIdx}][descriptions][]" placeholder="e.g. PO Asli + SJ, Invoice + FP...">
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-danger btn-xs btn-remove-extra-desc" title="Hapus uraian ini"><i class="fas fa-times"></i></button>
                </div>
            </div>
        `;
        $container.append(newItem);
    });

    // Handler hapus uraian extra-desc
    $(document).on('click', '.btn-remove-extra-desc', function() {
        var $item = $(this).closest('.extra-desc-item');
        var $container = $item.closest('.extra-desc-container');
        if ($container.find('.extra-desc-item').length <= 1) {
            // Kosongkan saja jika hanya 1 baris
            $container.find('.input-extra-desc').val('');
            return;
        }
        $item.remove();
        // Renumber
        $container.find('.extra-desc-item').each(function(i) {
            $(this).find('.extra-desc-num').text(i + 1);
        });
    });

    // =========================================================
    // FITUR AUTO-FILL RFP DARI PROINT ERP
    // Mengisi: Nama Vendor + No. PO (dari reqdesc) + No. GRN dalam 1 request
    // =========================================================
    function fetchRfpDetails(rfpVal, $row, $inputRfp) {
        if (!rfpVal) return;
        if ($inputRfp.data('fetching') === true) return;

        $inputRfp.data('fetching', true);
        var $btn = $row.find('.btn-fetch-rfp');
        var $loading = $row.find('.rfp-loading-text');
        var originalBtnHtml = $btn.html();

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $loading.removeClass('d-none');
        $inputRfp.addClass('border-primary').css('background-color', '#f0f7ff');

        $.ajax({
            url: 'purchasing_serverside.php',
            type: 'POST',
            data: { action: 'get_rfp_detail', no_rfp: rfpVal },
            dataType: 'json',
            success: function(res) {
                $inputRfp.data('fetching', false);
                $btn.prop('disabled', false).html(originalBtnHtml);
                $loading.addClass('d-none');
                $inputRfp.removeClass('border-primary').css('background-color', '');

                if (res.status === 'success' && res.data) {
                    var data = res.data;

                    // Normalisasi No. RFP ke format resmi dari DB
                    if (data.reqnmbr) {
                        $inputRfp.val(data.reqnmbr);
                    }

                    // Isi Nama Vendor
                    var $vendorInput = $row.find('.input-std-vendor');
                    if ($vendorInput.length > 0 && data.vendor) {
                        $vendorInput.val(data.vendor);
                    }

                    // Isi No. PO dari hasil ekstraksi reqdesc (jika ada)
                    var $poInput = $row.find('.input-std-po');
                    if ($poInput.length > 0 && data.po_combined) {
                        $poInput.val(data.po_combined);
                    }

                    // Isi No. GRN dari hasil pencarian ke prpohd/prgrnhd (jika ada)
                    var $grnInput = $row.find('.input-std-grn');
                    if ($grnInput.length > 0 && data.grn_combined && data.grn_combined !== '-') {
                        $grnInput.val(data.grn_combined);
                    }

                    // Flash highlight semua field yang terisi
                    var $filled = $row.find('.input-std-vendor, .input-std-po, .input-std-grn').filter(function() {
                        return $(this).val() !== '';
                    });
                    $filled.addClass('bg-light font-weight-bold');
                    setTimeout(function() { $filled.removeClass('bg-light font-weight-bold'); }, 1800);

                    // Susun teks ringkasan untuk toast
                    var toastParts = ['Vendor: ' + (data.vendor || '-')];
                    if (data.po_combined) toastParts.push('PO: ' + data.po_combined);
                    if (data.grn_combined && data.grn_combined !== '-') toastParts.push('GRN: ' + data.grn_combined);

                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Data RFP Berhasil Dimuat!',
                        text: toastParts.join(' | ')
                    });
                } else {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3500
                    });
                    Toast.fire({
                        icon: 'info',
                        title: 'Info Proint ERP',
                        text: res.message || 'No RFP tidak ditemukan di database Proint ERP.'
                    });
                }
            },
            error: function() {
                $inputRfp.data('fetching', false);
                $btn.prop('disabled', false).html(originalBtnHtml);
                $loading.addClass('d-none');
                $inputRfp.removeClass('border-primary').css('background-color', '');
            }
        });
    }

    // Trigger input No RFP di Modal Tambah (change, blur, enter, & klik icon search)
    $(document).on('change blur', '.input-type-keterangan', function() {
        var $row = $(this).closest('tr.item-row');
        var type = $row.find('.select-type').val();
        if (type === 'RFP') {
            var rfpVal = $.trim($(this).val());
            if (rfpVal) {
                fetchRfpDetails(rfpVal, $row, $(this));
            }
        }
    });

    $(document).on('click', '.btn-fetch-rfp', function() {
        var $row = $(this).closest('tr.item-row');
        var $inputRfp = $row.find('.input-type-keterangan');
        var rfpVal = $.trim($inputRfp.val());
        if (!rfpVal) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Ketikkan No. RFP terlebih dahulu!' });
            return;
        }
        fetchRfpDetails(rfpVal, $row, $inputRfp);
    });

    $(document).on('keydown', '.input-type-keterangan', function(e) {
        var $row = $(this).closest('tr.item-row');
        var type = $row.find('.select-type').val();
        if (type === 'RFP' && e.key === 'Enter') {
            e.preventDefault();
            $(this).trigger('change');
        }
    });

    // Helper Auto-Fill RFP untuk Modal Edit
    // Mengisi: Nama Vendor + No. PO (dari reqdesc) + No. GRN dalam 1 request
    function fetchRfpDetailsForEdit(rfpVal, $inputRfp) {
        if (!rfpVal) return;
        if ($inputRfp.data('fetching') === true) return;

        $inputRfp.data('fetching', true);
        var $btn = $('#btnEditFetchRfp');
        var $loading = $('#editRfpLoadingText');
        var originalBtnHtml = $btn.html();

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $loading.removeClass('d-none');
        $inputRfp.addClass('border-primary').css('background-color', '#f0f7ff');

        $.ajax({
            url: 'purchasing_serverside.php',
            type: 'POST',
            data: { action: 'get_rfp_detail', no_rfp: rfpVal },
            dataType: 'json',
            success: function(res) {
                $inputRfp.data('fetching', false);
                $btn.prop('disabled', false).html(originalBtnHtml);
                $loading.addClass('d-none');
                $inputRfp.removeClass('border-primary').css('background-color', '');

                if (res.status === 'success' && res.data) {
                    var data = res.data;

                    // Normalisasi No. RFP ke format resmi dari DB
                    if (data.reqnmbr) {
                        $inputRfp.val(data.reqnmbr);
                    }

                    // Isi Nama Vendor
                    if (data.vendor) {
                        $('#editVendor').val(data.vendor);
                    }

                    // Isi No. PO dari hasil ekstraksi reqdesc (jika ada)
                    if (data.po_combined) {
                        $('#editNoPo').val(data.po_combined);
                    }

                    // Isi No. GRN dari hasil pencarian ke prpohd/prgrnhd (jika ada)
                    if (data.grn_combined && data.grn_combined !== '-') {
                        $('#editNoGrn').val(data.grn_combined);
                    }

                    // Flash highlight
                    var $filled = $('#editVendor, #editNoPo, #editNoGrn').filter(function() {
                        return $(this).val() !== '';
                    });
                    $filled.addClass('bg-light font-weight-bold');
                    setTimeout(function() { $filled.removeClass('bg-light font-weight-bold'); }, 1800);

                    // Susun teks ringkasan untuk toast
                    var toastParts = ['Vendor: ' + (data.vendor || '-')];
                    if (data.po_combined) toastParts.push('PO: ' + data.po_combined);
                    if (data.grn_combined && data.grn_combined !== '-') toastParts.push('GRN: ' + data.grn_combined);

                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Data RFP Berhasil Dimuat!',
                        text: toastParts.join(' | ')
                    });
                } else {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3500
                    });
                    Toast.fire({
                        icon: 'info',
                        title: 'Info Proint ERP',
                        text: res.message || 'No RFP tidak ditemukan di database Proint ERP.'
                    });
                }
            },
            error: function() {
                $inputRfp.data('fetching', false);
                $btn.prop('disabled', false).html(originalBtnHtml);
                $loading.addClass('d-none');
                $inputRfp.removeClass('border-primary').css('background-color', '');
            }
        });
    }

    // Trigger input No RFP di Modal Edit
    $(document).on('change blur', '#editTypeKeterangan', function() {
        var type = $('#editType').val();
        if (type === 'RFP') {
            var rfpVal = $.trim($(this).val());
            if (rfpVal) {
                fetchRfpDetailsForEdit(rfpVal, $(this));
            }
        }
    });

    $(document).on('click', '#btnEditFetchRfp', function() {
        var rfpVal = $.trim($('#editTypeKeterangan').val());
        if (!rfpVal) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Ketikkan No. RFP terlebih dahulu!' });
            return;
        }
        fetchRfpDetailsForEdit(rfpVal, $('#editTypeKeterangan'));
    });

    $(document).on('keydown', '#editTypeKeterangan', function(e) {
        var type = $('#editType').val();
        if (type === 'RFP' && e.key === 'Enter') {
            e.preventDefault();
            $(this).trigger('change');
        }
    });

    // =========================================================
    // FITUR AUTO-FILL PO COD DARI PROINT ERP (koneksi3.php)
    // =========================================================
    // type: 'COD' => isi Description + Vendor + GRN
    // type: 'OFFSET'|'Kontrabon' => isi Vendor + GRN saja (Description dikosongkan)
    function fetchPoDetailsForCod(poVal, $row, $inputPo, type) {
        if (!poVal) return;
        if ($inputPo.data('fetching') === true) return;

        type = type || $row.find('.select-type').val() || 'COD';
        var isCod = (type === 'COD');
        var autoFillTypes = ['COD', 'OFFSET', 'Kontrabon'];
        if (autoFillTypes.indexOf(type) === -1) return; // hanya untuk tipe yang didukung

        $inputPo.data('fetching', true);
        var $btn = $row.find('.btn-fetch-po');
        var $loading = $row.find('.po-loading-text');
        var originalBtnHtml = $btn.html();

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $loading.removeClass('d-none');
        $inputPo.addClass('border-primary').css('background-color', '#f0f7ff');

        $.ajax({
            url: 'purchasing_serverside.php',
            type: 'POST',
            data: { action: 'get_po_detail', no_po: poVal },
            dataType: 'json',
            success: function(res) {
                $inputPo.data('fetching', false);
                $btn.prop('disabled', false).html(originalBtnHtml);
                $loading.addClass('d-none');
                $inputPo.removeClass('border-primary').css('background-color', '');

                if (res.status === 'success' && res.data) {
                    var data = res.data;
                    $inputPo.val(data.ponmbr);

                    // 1. Kolom Description (Uraian Berkas) baris pertama — HANYA untuk COD
                    if (isCod) {
                        // Coba beberapa selector sesuai struktur HTML yang mungkin terbuild
                        var $firstDescInput = $row.find('.desc-container .desc-item:first-child .input-std-desc');
                        if ($firstDescInput.length === 0) {
                            $firstDescInput = $row.find('.desc-container .input-std-desc').first();
                        }
                        if ($firstDescInput.length > 0) {
                            $firstDescInput.val(data.description_line).addClass('bg-light font-weight-bold');
                            setTimeout(function() {
                                $firstDescInput.removeClass('bg-light font-weight-bold');
                            }, 1800);
                        }
                    }

                    // 2. Kolom Nama Vendor terisi otomatis
                    var $vendorInput = $row.find('.input-std-vendor');
                    if ($vendorInput.length > 0) {
                        $vendorInput.val(data.vendor);
                    }

                    // 3. Kolom No GRN terisi otomatis
                    var $grnInput = $row.find('.input-std-grn');
                    if ($grnInput.length > 0 && data.no_grn) {
                        $grnInput.val(data.no_grn);
                    }

                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                    var toastTitle = isCod
                        ? 'Data ' + type + ' Berhasil Dimuat!'
                        : 'Supplier & GRN Berhasil Dimuat!';
                    var toastText = isCod
                        ? data.description_line
                        : 'Vendor: ' + data.vendor + (data.no_grn !== '-' ? ' | GRN: ' + data.no_grn : '');
                    Toast.fire({ icon: 'success', title: toastTitle, text: toastText });
                } else {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3500
                    });
                    Toast.fire({
                        icon: 'info',
                        title: 'Info Proint ERP',
                        text: res.message || 'No PO tidak ditemukan di database Proint ERP.'
                    });
                }
            },
            error: function() {
                $inputPo.data('fetching', false);
                $btn.prop('disabled', false).html(originalBtnHtml);
                $loading.addClass('d-none');
                $inputPo.removeClass('border-primary').css('background-color', '');
            }
        });
    }

    // Trigger saat input No PO berubah, blur, atau ditekan Enter di Modal Tambah
    // Berlaku untuk type: COD (isi semua) | OFFSET, Kontrabon (isi Vendor + GRN saja)
    var autoFillPoTypes = ['COD', 'OFFSET', 'Kontrabon'];

    $(document).on('change blur', '.input-std-po', function() {
        var $inputPo = $(this);
        var poVal = $.trim($inputPo.val());
        if (!poVal) return;

        var $row = $inputPo.closest('tr.item-row');
        var type = $row.find('.select-type').val();

        if (autoFillPoTypes.indexOf(type) !== -1) {
            fetchPoDetailsForCod(poVal, $row, $inputPo, type);
        }
    });

    $(document).on('click', '.btn-fetch-po', function() {
        var $row = $(this).closest('tr.item-row');
        var $inputPo = $row.find('.input-std-po');
        var poVal = $.trim($inputPo.val());
        var type = $row.find('.select-type').val();
        if (!poVal) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Ketikkan No PO terlebih dahulu!' });
            return;
        }
        fetchPoDetailsForCod(poVal, $row, $inputPo, type);
    });

    $(document).on('keydown', '.input-std-po', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $(this).trigger('change');
        }
    });

    // Helper untuk Modal Edit
    // type: 'COD' => isi Description + Vendor + GRN
    // type: 'OFFSET'|'Kontrabon' => isi Vendor + GRN saja (Description dibiarkan)
    function fetchPoDetailsForEditCod(poVal, $inputPo, type) {
        if (!poVal) return;
        if ($inputPo.data('fetching') === true) return;

        type = type || $('#editType').val() || 'COD';
        var isCod = (type === 'COD');
        var autoFillTypes = ['COD', 'OFFSET', 'Kontrabon'];
        if (autoFillTypes.indexOf(type) === -1) return;

        $inputPo.data('fetching', true);
        var $btn = $('#btnEditFetchPo');
        var $loading = $('#editPoLoadingText');
        var originalBtnHtml = $btn.html();

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $loading.removeClass('d-none');
        $inputPo.addClass('border-primary').css('background-color', '#f0f7ff');

        $.ajax({
            url: 'purchasing_serverside.php',
            type: 'POST',
            data: { action: 'get_po_detail', no_po: poVal },
            dataType: 'json',
            success: function(res) {
                $inputPo.data('fetching', false);
                $btn.prop('disabled', false).html(originalBtnHtml);
                $loading.addClass('d-none');
                $inputPo.removeClass('border-primary').css('background-color', '');

                if (res.status === 'success' && res.data) {
                    var data = res.data;
                    $inputPo.val(data.ponmbr);

                    // 1. Description baris 1 di textarea — HANYA untuk COD
                    if (isCod) {
                        var curDesc = $('#editDescription').val();
                        var lines = curDesc ? curDesc.split('\n') : [];
                        if (lines.length > 0) {
                            lines[0] = data.description_line;
                        } else {
                            lines = [data.description_line];
                        }
                        $('#editDescription').val(lines.join('\n'));
                    }
                    // Untuk OFFSET/Kontrabon: Description dibiarkan apa adanya (tidak diubah)

                    // 2. Vendor
                    $('#editVendor').val(data.vendor);

                    // 3. No GRN
                    if (data.no_grn) {
                        $('#editNoGrn').val(data.no_grn);
                    }

                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                    var toastTitle = isCod
                        ? 'Data ' + type + ' Berhasil Dimuat!'
                        : 'Supplier & GRN Berhasil Dimuat!';
                    var toastText = isCod
                        ? data.description_line
                        : 'Vendor: ' + data.vendor + (data.no_grn !== '-' ? ' | GRN: ' + data.no_grn : '');
                    Toast.fire({ icon: 'success', title: toastTitle, text: toastText });
                } else {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3500
                    });
                    Toast.fire({
                        icon: 'info',
                        title: 'Info Proint ERP',
                        text: res.message || 'No PO tidak ditemukan di database Proint ERP.'
                    });
                }
            },
            error: function() {
                $inputPo.data('fetching', false);
                $btn.prop('disabled', false).html(originalBtnHtml);
                $loading.addClass('d-none');
                $inputPo.removeClass('border-primary').css('background-color', '');
            }
        });
    }

    var autoFillPoTypesEdit = ['COD', 'OFFSET', 'Kontrabon'];

    $(document).on('change blur', '#editNoPo', function() {
        var poVal = $.trim($(this).val());
        if (!poVal) return;
        var type = $('#editType').val();
        if (autoFillPoTypesEdit.indexOf(type) !== -1) {
            fetchPoDetailsForEditCod(poVal, $(this), type);
        }
    });

    $(document).on('click', '#btnEditFetchPo', function() {
        var poVal = $.trim($('#editNoPo').val());
        var type = $('#editType').val();
        if (!poVal) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Ketikkan No PO terlebih dahulu!' });
            return;
        }
        fetchPoDetailsForEditCod(poVal, $('#editNoPo'), type);
    });

    $(document).on('keydown', '#editNoPo', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $(this).trigger('change');
        }
    });

    $('#editType').on('change', function() {
        var val = $(this).val();
        var poVal = $.trim($('#editNoPo').val());
        if (autoFillPoTypesEdit.indexOf(val) !== -1 && poVal) {
            fetchPoDetailsForEditCod(poVal, $('#editNoPo'), val);
        } else if (val === 'RFP') {
            var rfpVal = $.trim($('#editTypeKeterangan').val());
            if (rfpVal) {
                fetchRfpDetailsForEdit(rfpVal, $('#editTypeKeterangan'));
            }
        }
    });

    // =========================================================
    // FITUR AUTO-FILL PO (Untuk dibuat Cek) - BARIS SUB-PO
    // Mengisi Description (hanya pototalamounthc: Rp.xxx) + Nama Supplier
    // =========================================================
    function fetchPoDetailsForSubPo(poVal, $subRow, $inputNoPo, isEdit) {
        if (!poVal) return;
        if ($inputNoPo.data('fetching') === true) return;

        $inputNoPo.data('fetching', true);
        var $btn = $subRow.find(isEdit ? '.btn-fetch-editsubpo' : '.btn-fetch-subpo');
        var $loading = $subRow.find(isEdit ? '.editsubpo-loading-text' : '.subpo-loading-text');
        var originalBtnHtml = $btn.html();

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $loading.removeClass('d-none');
        $inputNoPo.addClass('border-primary').css('background-color', '#f0f7ff');

        $.ajax({
            url: 'purchasing_serverside.php',
            type: 'POST',
            data: { action: 'get_po_detail', no_po: poVal },
            dataType: 'json',
            success: function(res) {
                $inputNoPo.data('fetching', false);
                $btn.prop('disabled', false).html(originalBtnHtml);
                $loading.addClass('d-none');
                $inputNoPo.removeClass('border-primary').css('background-color', '');

                if (res.status === 'success' && res.data) {
                    var data = res.data;
                    // Nomor PO dari ERP (terstandarisasi)
                    $inputNoPo.val(data.ponmbr);

                    // Description: hanya nominal (Rp.xxx dari pototalamounthc)
                    var descClass = isEdit ? '.input-editsubpo-desc' : '.input-subpo-desc';
                    var vendorClass = isEdit ? '.input-editsubpo-vendor' : '.input-subpo-vendor';
                    $subRow.find(descClass).val(data.formatted_amount).addClass('bg-light font-weight-bold');
                    setTimeout(function() {
                        $subRow.find(descClass).removeClass('bg-light font-weight-bold');
                    }, 1800);

                    // Nama Supplier
                    $subRow.find(vendorClass).val(data.vendor);

                    // No GRN (otomatis dari Proint ERP, bisa berisi multiple GRN dipisahkan koma)
                    var grnClass = isEdit ? '.input-editsubpo-nogrn' : '.input-subpo-nogrn';
                    var grnVal = data.no_grn || '-';
                    $subRow.find(grnClass).val(grnVal).attr('title', grnVal).addClass('bg-light font-weight-bold');
                    setTimeout(function() {
                        $subRow.find(grnClass).removeClass('bg-light font-weight-bold');
                    }, 1800);

                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                    var toastText = data.vendor + ' | ' + data.formatted_amount;
                    if (grnVal && grnVal !== '-') {
                        toastText += ' | GRN: ' + grnVal;
                    }
                    Toast.fire({
                        icon: 'success',
                        title: 'Data PO Berhasil Dimuat!',
                        text: toastText
                    });
                } else {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3500
                    });
                    Toast.fire({
                        icon: 'info',
                        title: 'Info Proint ERP',
                        text: res.message || 'No PO tidak ditemukan di database Proint ERP.'
                    });
                }
            },
            error: function() {
                $inputNoPo.data('fetching', false);
                $btn.prop('disabled', false).html(originalBtnHtml);
                $loading.addClass('d-none');
                $inputNoPo.removeClass('border-primary').css('background-color', '');
            }
        });
    }

    // Event handlers untuk sub-PO rows di Modal TAMBAH
    $(document).on('change blur', '.input-subpo-nopo', function() {
        var poVal = $.trim($(this).val());
        if (!poVal) return;
        fetchPoDetailsForSubPo(poVal, $(this).closest('tr.subpo-row'), $(this), false);
    });
    $(document).on('click', '.btn-fetch-subpo', function() {
        var $subRow = $(this).closest('tr.subpo-row');
        var $inputNoPo = $subRow.find('.input-subpo-nopo');
        var poVal = $.trim($inputNoPo.val());
        if (!poVal) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Ketikkan No PO terlebih dahulu!' });
            return;
        }
        fetchPoDetailsForSubPo(poVal, $subRow, $inputNoPo, false);
    });
    $(document).on('keydown', '.input-subpo-nopo', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); $(this).trigger('change'); }
    });

    // Event handlers untuk sub-PO rows di Modal EDIT
    $(document).on('change blur', '.input-editsubpo-nopo', function() {
        var poVal = $.trim($(this).val());
        if (!poVal) return;
        fetchPoDetailsForSubPo(poVal, $(this).closest('tr.edit-subpo-row'), $(this), true);
    });
    $(document).on('click', '.btn-fetch-editsubpo', function() {
        var $subRow = $(this).closest('tr.edit-subpo-row');
        var $inputNoPo = $subRow.find('.input-editsubpo-nopo');
        var poVal = $.trim($inputNoPo.val());
        if (!poVal) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Ketikkan No PO terlebih dahulu!' });
            return;
        }
        fetchPoDetailsForSubPo(poVal, $subRow, $inputNoPo, true);
    });
    $(document).on('keydown', '.input-editsubpo-nopo', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); $(this).trigger('change'); }
    });

    $(document).on('click', '.btn-add-subpo', function() {
        var rIdx = $(this).attr('data-row');
        var $tbody = $(`#subpo-table-${rIdx} .subpo-tbody`);
        var count = $tbody.find('tr.subpo-row').length;
        var firstVendor = $tbody.find('tr.subpo-row:first').find('.input-subpo-vendor').val() || '';
        $tbody.append(createSubPoRow(rIdx, count, '', firstVendor, '', '-'));
    });

    $(document).on('click', '.btn-remove-subpo', function() {
        var $tbody = $(this).closest('.subpo-tbody');
        if ($tbody.find('tr.subpo-row').length <= 1) {
            Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Minimal harus ada 1 baris PO!' });
            return;
        }
        var $tr = $(this).closest('tr.subpo-row');
        var rIdx = $(this).closest('tr.item-row').attr('data-row');
        $tr.remove();

        $tbody.find('tr.subpo-row').each(function(subIdx) {
            $(this).attr('data-subidx', subIdx);
            $(this).find('.subpo-num').text(subIdx + 1);
            $(this).find('.input-subpo-desc').attr('name', `items[${rIdx}][po_items][${subIdx}][desc]`);
            $(this).find('.input-subpo-vendor').attr('name', `items[${rIdx}][po_items][${subIdx}][vendor]`);
            $(this).find('.input-subpo-nopo').attr('name', `items[${rIdx}][po_items][${subIdx}][no_po]`);
            $(this).find('.input-subpo-nogrn').attr('name', `items[${rIdx}][po_items][${subIdx}][no_grn]`);
        });
    });

    $(document).on('click', '.btn-copy-vendor', function() {
        var rIdx = $(this).attr('data-row');
        var $tbody = $(`#subpo-table-${rIdx} .subpo-tbody`);
        var firstVendor = $tbody.find('tr.subpo-row:first').find('.input-subpo-vendor').val() || '';
        if (!firstVendor) {
            Swal.fire({ icon: 'info', title: 'Informasi', text: 'Silakan isi nama supplier pada baris 1 terlebih dahulu!' });
            return;
        }
        $tbody.find('tr.subpo-row').each(function() {
            $(this).find('.input-subpo-vendor').val(firstVendor);
        });
    });

    function reindexRows() {
        $('#tbodyDynamicRows tr.item-row').each(function(idx) {
            $(this).find('.row-num').text(idx + 1);
            $(this).attr('data-row', idx);
            $(this).find('select[name*="[type]"]').attr('name', `items[${idx}][type]`);
            $(this).find('input[name*="[type_keterangan]"]').attr('name', `items[${idx}][type_keterangan]`);
            $(this).find('input[name*="[vendor]"]').attr('name', `items[${idx}][vendor]`);
            $(this).find('input[name*="[no_po]"]').attr('name', `items[${idx}][no_po]`);
            $(this).find('input[name*="[no_grn]"]').attr('name', `items[${idx}][no_grn]`);
            $(this).find('input[name*="[descriptions]"]').attr('name', `items[${idx}][descriptions][]`);
            $(this).find('.btn-add-desc').attr('data-row', idx);
            $(this).find('.desc-container').attr('id', `desc-container-${idx}`);
            
            $(this).find('.subpo-table').attr('id', `subpo-table-${idx}`);
            $(this).find('.btn-add-subpo').attr('data-row', idx);
            $(this).find('.btn-copy-vendor').attr('data-row', idx);

            $(this).find('tr.subpo-row').each(function(subIdx) {
                $(this).attr('data-subidx', subIdx);
                $(this).find('.subpo-num').text(subIdx + 1);
                $(this).find('.input-subpo-desc').attr('name', `items[${idx}][po_items][${subIdx}][desc]`);
                $(this).find('.input-subpo-vendor').attr('name', `items[${idx}][po_items][${subIdx}][vendor]`);
                $(this).find('.input-subpo-nopo').attr('name', `items[${idx}][po_items][${subIdx}][no_po]`);
                $(this).find('.input-subpo-nogrn').attr('name', `items[${idx}][po_items][${subIdx}][no_grn]`);
            });

            // Reindex extra-desc (uraian tambahan type PO)
            $(this).find('.extra-desc-container').attr('id', `extra-desc-container-${idx}`);
            $(this).find('.btn-add-extra-desc').attr('data-row', idx);
            $(this).find('.extra-desc-item .input-extra-desc').attr('name', `items[${idx}][descriptions][]`);
            $(this).find('.extra-desc-item').each(function(descIdx) {
                $(this).find('.extra-desc-num').text(descIdx + 1);
            });

            $(this).find('.desc-item').each(function(descIdx) {
                $(this).find('.desc-num').text(descIdx + 1);
            });
        });
        rowIndex = $('#tbodyDynamicRows tr.item-row').length;
    }

    // Inisialisasi Select2 (hanya untuk field lain, penerima sudah dihapus)
    function initSelect2Fields() {
        // Select2 Penerima sudah dihapus — penerima diisi otomatis saat TTD
    }
    initSelect2Fields();

    $('#modalTambahPurchasing').on('shown.bs.modal', function() {
        // tidak ada select2 penerima
    });

    $('#modalEditPurchasing').on('shown.bs.modal', function() {
        // tidak ada select2 penerima
    });

    $('#btnTambahData').on('click', function(e) {
        e.preventDefault();
        $('#formTambahPurchasing')[0].reset();
        $('#inputTanggal').val(new Date().toISOString().split('T')[0]);
        $('#inputPengirim').val('<?= addslashes($currentUserPengirim) ?>');
        $('#tbodyDynamicRows').empty();

        rowIndex = 0;
        $('#tbodyDynamicRows').append(createRowTemplate(0, 'PO'));
        updatePoKeteranganElements(poKeteranganCache);
        fetchAndRefreshPoKeteranganList();
        $('#modalTambahPurchasing').modal('show');
    });

    $('#btnAddRow').on('click', function() {
        var currentCount = $('#tbodyDynamicRows tr.item-row').length;
        $('#tbodyDynamicRows').append(createRowTemplate(currentCount, 'PO'));
        updatePoKeteranganElements(poKeteranganCache);
        reindexRows();
    });

    $(document).on('click', '.btn-remove-row', function() {
        if ($('#tbodyDynamicRows tr.item-row').length <= 1) {
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Minimal harus ada 1 baris rincian dokumen!'
            });
            return;
        }
        $(this).closest('tr.item-row').remove();
        reindexRows();
    });

    $(document).on('click', '.btn-add-desc', function() {
        var rIdx = $(this).attr('data-row');
        var $container = $(`#desc-container-${rIdx}`);
        var descCount = $container.find('.desc-item').length + 1;

        var descHtml = `
            <div class="input-group input-group-sm mb-1 desc-item">
                <div class="input-group-prepend">
                    <span class="input-group-text font-weight-bold px-2 desc-num">${descCount}</span>
                </div>
                <input type="text" class="form-control form-control-sm" name="items[${rIdx}][descriptions][]" placeholder="Uraian dokumen tambahan..." required>
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove-desc">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        $container.append(descHtml);
    });

    $(document).on('click', '.btn-remove-desc', function() {
        var $container = $(this).closest('.desc-container');
        $(this).closest('.desc-item').remove();
        $container.find('.desc-item').each(function(idx) {
            $(this).find('.desc-num').text(idx + 1);
        });
    });

    $('#formTambahPurchasing').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize() + '&action=add';

        $.ajax({
            url: 'purchasing_serverside.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil Disimpan!',
                        text: res.message || 'Data serah terima purchasing berhasil disimpan!',
                        confirmButtonColor: '#28a745'
                    }).then(function() {
                        $('#modalTambahPurchasing').modal('hide');
                        fetchAndRefreshPoKeteranganList();
                        table.ajax.reload(null, false);
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: res.message || 'Gagal menyimpan data.' });
                }
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Koneksi server gagal: ' + xhr.statusText });
            }
        });
    });
});
</script>
