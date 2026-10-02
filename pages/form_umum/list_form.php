<?php
session_start();

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

function checkPermissions($conn, $groupId, $menuId)
{
    $sql = "SELECT CanView, CanAdd, CanEdit, CanDelete
            FROM dbo.SMGroupTrustee
            WHERE GroupId = ? AND MenuId = ?";
    $params = [$groupId, $menuId];
    $stmt = sqlsrv_query($conn, $sql, $params);

    // Fallback: jika menu belum terdaftar di SMGroupTrustee, izinkan user yang login.
    $permissions = ['CanView' => 1, 'CanAdd' => 1, 'CanEdit' => 1, 'CanDelete' => 1];
    if ($stmt !== false) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row)
            $permissions = $row;
    }
    if ($stmt !== false)
        sqlsrv_free_stmt($stmt);
    return $permissions;
}

// NOTE: Ganti MenuId sesuai menu yang terdaftar untuk Form Umum
$permissions = checkPermissions($conn, $_SESSION['GroupId'], 1293);
$isAdmin = (isset($_SESSION['GroupId']) && (int) $_SESSION['GroupId'] === 1);

// Jika belum ada menu khusus, sementara izinkan semua yang login
// Uncomment baris di bawah setelah menu terdaftar:
// if ($permissions['CanView'] != 1) {
//     $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
//     header('Location: ../dashboard.php');
//     exit;
// }

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

$filterTanggal = $_GET['tanggal'] ?? '';
?>

<style>
    .action-btn {
        margin-right: 6px;
    }

    table.dataTable.dtr-inline.collapsed>tbody>tr>td.dtr-control:before,
    table.dataTable.dtr-inline.collapsed>tbody>tr>th.dtr-control:before {
        background-color: #007bff;
        border: none;
        box-shadow: none;
        line-height: 1em;
        top: 50%;
        transform: translateY(-50%);
    }

    table.dataTable>tbody>tr.child ul.dtr-details {
        display: block;
        width: 100%;
        padding: 0;
    }

    table.dataTable>tbody>tr.child ul.dtr-details>li {
        border-bottom: 1px solid #efefef;
        padding: 8px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    table.dataTable>tbody>tr.child ul.dtr-details>li:last-child {
        border-bottom: none;
    }

    table.dataTable>tbody>tr.child ul.dtr-details>li .dtr-title {
        font-weight: 600;
        color: #555;
        min-width: 120px;
    }

    table.dataTable>tbody>tr.child ul.dtr-details>li .dtr-data {
        text-align: right;
        flex: 1;
        word-break: break-word;
    }
</style>

<div class="wrapper">
    <div class="content-wrapper">

        <section class="content-header">
            <div class="container-fluid">
                <h1>Form Umum</h1>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">

                <div class="card card-primary">
                    <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                        <h3 class="card-title"><i class="fas fa-list mr-1"></i> List Pengajuan Umum</h3>
                        <?php if (!empty($isAdmin) && $isAdmin): ?>
                            <button type="button" class="btn btn-info btn-sm float-right ml-2" data-toggle="modal"
                                data-target="#modalMasterFormChooser">
                                <i class="fas fa-th-large"></i> Master Form
                            </button>
                        <?php endif; ?>
                        <?php if (!empty($permissions['CanAdd']) && (int) $permissions['CanAdd'] === 1): ?>
                            <button type="button" class="btn btn-success btn-sm float-right" id="btnTambahForm">
                                <i class="fas fa-plus"></i> Tambah Form
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="card-body table-responsive">
                        <div class="filter-section p-3 mb-3" style="background-color: #f8f9fa; border-radius: 5px;">
                            <div class="row align-items-end">
                                <div class="col-md-2">
                                    <label class="small mb-1 font-weight-bold">Jenis Report</label>
                                    <select class="form-control form-control-sm" id="pdfReportType">
                                        <option value="">-- Semua --</option>
                                        <option value="rekap_closingan">Rekap Buka Tanggal Closingan</option>
                                        <option value="rekap_izin_keluar">Rekap Izin Keluar Pabrik</option>
                                        <option value="rekap_izin_pulang_cepat">Rekap Izin Pulang Cepat</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small mb-1 font-weight-bold" for="filterTanggal">Tgl Dari</label>
                                    <input type="date" id="filterTanggal" class="form-control form-control-sm"
                                        value="<?php echo htmlspecialchars($filterTanggal); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="small mb-1 font-weight-bold" for="filterTanggalSampai">Tgl
                                        Sampai</label>
                                    <input type="date" id="filterTanggalSampai" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="small mb-1 font-weight-bold">Status</label>
                                    <select class="form-control form-control-sm" id="pdfFilterStatus" disabled>
                                        <option value="">Pilih Jenis Report terlebih dahulu</option>
                                    </select>
                                </div>
                                <div class="col-md-1 pl-0">
                                    <button id="btnReset" class="btn btn-secondary btn-sm w-100" title="Reset Filter">
                                        <i class="fas fa-sync"></i> Reset
                                    </button>
                                </div>
                                <div class="col d-flex align-items-end justify-content-end">
                                    <button type="button" class="btn btn-danger btn-sm" id="btnGeneratePdf">
                                        <i class="fas fa-file-pdf"></i> Generate PDF
                                    </button>
                                </div>
                            </div>
                            <!-- Sub-filter Closingan (hanya tampil jika Jenis Report = rekap_closingan) -->
                            <div class="row mt-2" id="closinganSubFilter" style="display:none;">
                                <div class="col-auto">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="filterClosinganGudang"
                                            value="1">
                                        <label class="form-check-label small font-weight-bold"
                                            for="filterClosinganGudang">Closingan Gudang</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="filterClosinganTransaksi"
                                            value="1">
                                        <label class="form-check-label small font-weight-bold"
                                            for="filterClosinganTransaksi">Closingan Transaksi</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <table id="tiketTable" class="table table-hover table-sm nowrap" style="width:100%">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>No Pengajuan</th>
                                    <th>Kategori</th>
                                    <th>Pemohon</th>
                                    <th>Departemen</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal Detail Tiket -->
                <div class="modal fade" id="modalDetailTiket" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-xl" role="document" style="max-width: 95%;">
                        <div class="modal-content" style="height: 90vh; display: flex; flex-direction: column;">
                            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                <h5 class="modal-title">Detail Pengajuan : <span id="modalTicketNumber"></span></h5>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <button type="button" class="btn btn-sm btn-danger" id="btnRejectFromModal"
                                        style="display: none;">
                                        <i class="fas fa-ban"></i> Tolak Pengajuan
                                    </button>
                                    <button type="button" class="close text-white" data-dismiss="modal"
                                        aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            </div>
                            <div class="modal-body" style="padding:0; flex: 1 1 auto;">
                                <iframe id="iframeDetailTiket" src="about:blank"
                                    style="border:0; width:100%; height:100%;"></iframe>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Modal: QR Code Izin Keluar (untuk Satpam) ─────────────────────────── -->
                <div class="modal fade" id="modalQrIzin" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 420px;">
                        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
                            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white"
                                style="border: none;">
                                <h5 class="modal-title">
                                    <i class="fas fa-qrcode mr-2"></i>QR Code Izin Keluar
                                </h5>
                                <button type="button" class="close text-white" id="btnQrPrinterSettings"
                                    aria-label="Pengaturan printer" title="Pengaturan printer">
                                    <i class="fas fa-cog" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body text-center" style="padding: 1.5rem; background: #fafbfc;">
                                <div class="mb-2">
                                    <span class="badge badge-success badge-pill px-3 py-1" style="font-size: .8rem;">
                                        <i class="fas fa-check-circle"></i> Approved
                                    </span>
                                </div>
                                <h6 class="mb-1 font-weight-bold" id="modalQrTicket">IKP-XXXX</h6>
                                <p class="text-muted small mb-3" id="modalQrSubtitle">Tiket Izin Keluar Pabrik</p>

                                <div class="btn-group btn-group-sm mb-3" role="group" aria-label="Pilih isi QR">
                                    <button type="button" class="btn btn-primary active" id="btnQrModeLink"
                                        data-mode="link">QR Link</button>
                                    <button type="button" class="btn btn-outline-primary" id="btnQrModeTicket"
                                        data-mode="ticket">QR Ticket</button>
                                </div>

                                <div id="modalQrContainer"
                                    style="background: #fff; padding: 16px; border-radius: 10px; display: inline-block; box-shadow: 0 2px 8px rgba(0,0,0,.08); max-width: 100%;">
                                    <img id="modalQrImg"
                                        src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
                                        alt="QR Code" style="width: 240px; height: 240px; display: block; max-width: 100%; object-fit: contain;">
                                </div>

                                <p class="text-muted small mt-3 mb-0" id="modalQrHelp" style="line-height: 1.4;">
                                    QR berisi link scan langsung untuk halaman ini.
                                </p>
                            </div>
                            <div class="modal-footer"
                                style="background: #fafbfc; border-top: 1px solid #e9ecef; justify-content: flex-end;">
                                <button type="button" class="btn btn-secondary btn-sm"
                                    data-dismiss="modal">Tutup</button>
                                <button type="button" id="btnPrintQrIzin" class="btn btn-info btn-sm">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <?php if ($isAdmin): ?>
                                <button type="button" id="btnPrintQrCalibration" class="btn btn-warning btn-sm">
                                    <i class="fas fa-ruler-horizontal"></i> Kalibrasi
                                </button>
                                <?php endif; ?>
                                <a href="#" id="btnDownloadQr" download="qr-izin-keluar.png"
                                    class="btn btn-success btn-sm">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- QR printer settings modal -->
                <div class="modal fade" id="modalQrPrinterSettings" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width:420px;">
                        <div class="modal-content">
                            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                                <h5 class="modal-title"><i class="fas fa-print mr-2"></i>Pengaturan Printer QR</h5>
                                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="qrPrinterPrintMode">Mode tampilan & cetak</label>
                                    <select id="qrPrinterPrintMode" class="form-control form-control-sm">
                                        <option value="qr">QR via browser print</option>
                                        <option value="barcode">Barcode via browser print</option>
                                    </select>
                                    <small class="form-text text-muted">Mode barcode menampilkan barcode ticket dan tombol Print mencetak barcode yang terlihat di layar.</small>
                                </div>
                                <div class="form-group"><label for="qrPrinterTransport">Jenis koneksi</label><select id="qrPrinterTransport" class="form-control form-control-sm"><option value="windows">Windows Shared Printer</option><option value="lan">LAN Printer</option></select></div>
                                <div class="form-group" id="qrPrinterWindowsGroup"><label for="qrPrinterName">Nama printer / share name</label><input id="qrPrinterName" class="form-control form-control-sm" placeholder="\\\\SERVER\\EPSON TM-U220"></div>
                                <div class="form-row" id="qrPrinterLanGroup" style="display:none"><div class="form-group col-8"><label for="qrPrinterIp">IP printer</label><input id="qrPrinterIp" class="form-control form-control-sm" placeholder="192.168.1.100"></div><div class="form-group col-4"><label for="qrPrinterPort">Port</label><input id="qrPrinterPort" type="number" class="form-control form-control-sm" value="9100"></div></div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button><button type="button" class="btn btn-primary btn-sm" id="btnSaveQrPrinterSettings">Simpan</button></div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <!-- Modal Reject Pengajuan -->
    <div class="modal fade" id="modalRejectFromList" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Tolak Pengajuan</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menolak pengajuan ini?</p>
                    <hr>
                    <div class="form-group">
                        <label for="rejectReasonFromList"><b>Alasan Penolakan (Opsional)</b></label>
                        <textarea class="form-control" id="rejectReasonFromList" name="reject_reason" rows="4"
                            placeholder="Masukkan alasan penolakan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmRejectFromList">Ya, Tolak
                        Pengajuan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Pilih Form -->
    <div class="modal fade" id="modalPilihForm" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                    <h5 class="modal-title">Pilih Form</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label><i class="fas fa-search"></i> Cari Form:</label>
                        <input type="text" id="searchForm" class="form-control"
                            placeholder="Ketik untuk mencari form...">
                    </div>
                    <hr>
                    <div id="formList">
                        <div class="list-group">
                            <a href="#" class="list-group-item list-group-item-action form-item"
                                data-form-type="buka_tanggal_closingan">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><i class="fas fa-calendar-alt"></i> Form Buka Tanggal Closingan
                                    </h6>
                                </div>
                                <p class="mb-1 small text-muted">Form untuk permintaan buka tanggal closingan Gudang
                                    atau Transaksi</p>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action form-item"
                                data-form-type="izin_keluar_pabrik">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <i class="fas fa-door-open"></i> Form Izin Keluar Pabrik
                                    </h6>
                                </div>
                                <p class="mb-1 small text-muted">
                                    Form untuk pengajuan izin meninggalkan area pabrik (Dinas/Pribadi)
                                </p>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action form-item"
                                data-form-type="izin_pulang_cepat">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <i class="fas fa-running"></i> Form Izin Pulang Cepat
                                    </h6>
                                </div>
                                <p class="mb-1 small text-muted">
                                    Form pengajuan karyawan pulang sebelum jam kerja berakhir
                                </p>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </div>
        </div>
    </div>



    <!-- Modal Form Isian -->
    <div class="modal fade" id="modalFormIsian" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"
            style="max-height: 90vh; margin: 1.75rem auto;">
            <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
                <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white"
                    style="flex-shrink: 0;">
                    <h5 class="modal-title" id="modalFormTitle">Form</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="modalFormBody"
                    style="overflow-y: auto; flex: 1 1 auto; min-height: 0; padding: 1rem; max-height: calc(90vh - 140px);">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2">Memuat form...</p>
                    </div>
                </div>
                <div class="modal-footer" style="flex-shrink: 0; border-top: 1px solid #dee2e6;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success" id="btnSimpanForm">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ── Modal: Master Form Chooser ─────────────────────────────────────────── -->
<div class="modal fade" id="modalMasterFormChooser" tabindex="-1" role="dialog"
    aria-labelledby="modalMasterFormChooserTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius:12px; overflow:hidden;">
            <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white" style="border: none;">
                <h5 class="modal-title" id="modalMasterFormChooserTitle">
                    <i class="fas fa-th-large"></i> &nbsp;Master Form
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="background:#f4f6f9;">
                <p class="text-muted small mb-3">Pilih salah satu modul master di bawah ini:</p>
                <div class="row">

                    <!-- Card 1: Master Transaksi Closing -->
                    <div class="col-md-6 mb-3">
                        <a href="master_transaksi.php" style="text-decoration:none; color:inherit;">
                            <div class="card master-form-card h-100"
                                style="border:none; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.06); cursor:pointer; transition:all .2s ease;">
                                <div class="card-body text-center" style="padding:32px 18px;">
                                    <div style="font-size:48px; color:#1a73e8; margin-bottom:14px;">
                                        <i class="fas fa-database"></i>
                                    </div>
                                    <h5 style="font-weight:700; color:#1f2d3d;">Master Transaksi Closing</h5>
                                    <p class="text-muted small mb-0">
                                        Kelola data master transaksi closing (buka tanggal closingan)
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Card 2: Master Izin Keluar Pabrik / Scan -->
                    <div class="col-md-6 mb-3">
                        <a href="scan_index.php" style="text-decoration:none; color:inherit;">
                            <div class="card master-form-card h-100"
                                style="border:none; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.06); cursor:pointer; transition:all .2s ease;">
                                <div class="card-body text-center" style="padding:32px 18px;">
                                    <div style="font-size:48px; color:#28a745; margin-bottom:14px;">
                                        <i class="fas fa-qrcode"></i>
                                    </div>
                                    <h5 style="font-weight:700; color:#1f2d3d;">Master Izin Keluar Pabrik</h5>
                                    <p class="text-muted small mb-0">
                                        Halaman scan QR tiket IKS (Satpam &amp; operasional)
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>

                </div>
            </div>
            <div class="modal-footer" style="background:#fafbfc;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
    .master-form-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 22px rgba(26, 115, 232, .18) !important;
    }
</style>

<?php include '../../includes/footer.php'; ?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet"
    href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<!-- DataTables JS -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- SweetAlert -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
    var isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    $(document).ready(function () {

        // ─── DataTable ───────────────────────────────────────────────────
        var table = $('#tiketTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'list_form_serverside.php',
                type: 'POST',
                data: function (d) {
                    d.tanggal = $('#filterTanggal').val();
                    d.tanggal_sampai = $('#filterTanggalSampai').val();
                    d.status_filter = $('#pdfFilterStatus').val();
                    d.request_gudang = $('#filterClosinganGudang').is(':checked') ? '1' : '';
                    d.request_transaksi = $('#filterClosinganTransaksi').is(':checked') ? '1' : '';
                    d.report_type = $('#pdfReportType').val();
                    d.search_value = d.search.value;
                },
                error: function (xhr, error, thrown) {
                    console.error('DataTables Error:', xhr, error, thrown);
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal memuat data: ' + (thrown || 'Unknown error') });
                }
            },
            columns: [
                {
                    data: null, orderable: false, searchable: false,
                    render: function (data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; }
                },
                { data: 'ticket' },
                { data: 'kategori' },
                { data: 'nama_pemohon' },
                { data: 'departemen' },
                { data: 'tgl_pengajuan' },
                { data: 'status_ticket' },
                { data: 'aksi', orderable: false, searchable: false }
            ],
            order: [[5, 'desc']],
            responsive: true,
            drawCallback: function () {
                // Status badge & buttons are already rendered correctly by serverside.
                // No extra AJAX needed here.
            },
            language: {
                processing: "Memproses...",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                zeroRecords: "Tidak ada data ditemukan",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                infoEmpty: "Tidak ada data tersedia",
                infoFiltered: "(disaring dari _MAX_ total data)",
                search: "Cari:",
                paginate: { first: "Pertama", last: "Terakhir", next: "Selanjutnya", previous: "Sebelumnya" }
            }
        });

        // ─── Filter ──────────────────────────────────────────────────────
        $('#filterTanggal, #filterTanggalSampai, #pdfFilterStatus').on('change', function () {
            table.ajax.reload();
        });

        // Show/hide sub-filter closingan berdasarkan Jenis Report
        $('#pdfReportType').on('change', function () {
            var val = $(this).val();
            var $statusSelect = $('#pdfFilterStatus');
            
            // Update sub-filter closingan
            if (val === 'rekap_closingan') {
                $('#closinganSubFilter').show();
            } else {
                $('#closinganSubFilter').hide();
                $('#filterClosinganGudang').prop('checked', false);
                $('#filterClosinganTransaksi').prop('checked', false);
            }
            
            // Update status dropdown based on report type
            $statusSelect.empty();
            
            if (!val || val === '') {
                // No report type selected
                $statusSelect.prop('disabled', true);
                $statusSelect.append('<option value="">Pilih Jenis Report terlebih dahulu</option>');
            } else {
                // Report type selected, populate status options
                $statusSelect.prop('disabled', false);
                $statusSelect.append('<option value="">-- Semua --</option>');
                
                if (val === 'rekap_closingan') {
                    // Closingan has additional status options
                    $statusSelect.append('<option value="Pending">Pending</option>');
                    $statusSelect.append('<option value="Approved">Approved</option>');
                    $statusSelect.append('<option value="Ditolak">Ditolak</option>');
                    $statusSelect.append('<option value="Unclosing">Unclosing</option>');
                    $statusSelect.append('<option value="Closed">Closed</option>');
                } else if (val === 'rekap_izin_keluar' || val === 'rekap_izin_pulang_cepat') {
                    // IKS/IKP/IPC have standard approval status
                    $statusSelect.append('<option value="Pending">Pending</option>');
                    $statusSelect.append('<option value="Approved">Approved</option>');
                    $statusSelect.append('<option value="Ditolak">Ditolak</option>');
                }
            }
            
            table.ajax.reload();
        });

        // Checkbox closingan → reload DataTable
        $('#filterClosinganGudang, #filterClosinganTransaksi').on('change', function () {
            table.ajax.reload();
        });

        $('#btnReset').on('click', function () {
            $('#filterTanggal').val('');
            $('#filterTanggalSampai').val('');
            $('#pdfReportType').val('');
            $('#closinganSubFilter').hide();
            $('#filterClosinganGudang').prop('checked', false);
            $('#filterClosinganTransaksi').prop('checked', false);
            
            // Reset status dropdown to initial disabled state
            var $statusSelect = $('#pdfFilterStatus');
            $statusSelect.empty();
            $statusSelect.prop('disabled', true);
            $statusSelect.append('<option value="">Pilih Jenis Report terlebih dahulu</option>');
            
            table.ajax.reload();
        });

        // ─── Generate PDF Rekap ──────────────────────────────────────────
        $('#btnGeneratePdf').on('click', function () {
            var reportType = $('#pdfReportType').val();
            if (!reportType) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Pilih Jenis Report',
                    text: 'Silakan pilih Jenis Report terlebih dahulu untuk men-generate PDF Rekap.'
                });
                return;
            }

            var tglDari = $('#filterTanggal').val();
            var tglSampai = $('#filterTanggalSampai').val();
            var status = $('#pdfFilterStatus').val();

            var params = new URLSearchParams();
            if (tglDari) params.append('dari', tglDari);
            if (tglSampai) params.append('sampai', tglSampai);
            if (status) params.append('status', status);

            var targetUrl = '';
            if (reportType === 'rekap_closingan') {
                targetUrl = 'generate_pdf_rekap_closingan.php';
                if ($('#filterClosinganGudang').is(':checked')) params.append('gudang', '1');
                if ($('#filterClosinganTransaksi').is(':checked')) params.append('transaksi', '1');
            } else if (reportType === 'rekap_izin_keluar') {
                targetUrl = 'generate_pdf_rekap_izin_keluar.php';
            } else if (reportType === 'rekap_izin_pulang_cepat') {
                targetUrl = 'generate_pdf_rekap_izin_pulang_cepat.php';
            }

            if (targetUrl) {
                var queryString = params.toString();
                var fullUrl = targetUrl + (queryString ? '?' + queryString : '');
                window.open(fullUrl, '_blank');
            }
        });

        // ─── Notification helpers ─────────────────────────────────────────
        function showSuccess(message, title) {
            title = title || 'Sukses!';
            Swal.fire({ icon: 'success', title: title, html: message, timer: 3000, timerProgressBar: true, showConfirmButton: true, confirmButtonColor: '#28a745' });
        }
        function showError(message, title) {
            title = title || 'Error!';
            Swal.fire({ icon: 'error', title: title, html: message, timer: 4000, timerProgressBar: true, showConfirmButton: true, confirmButtonColor: '#dc3545' });
        }

        // ─── Tambah Form ─────────────────────────────────────────────────
        $('#btnTambahForm').on('click', function () {
            var btn = $(this);
            if (btn.prop('disabled')) return;
            btn.prop('disabled', true);
            $('#modalPilihForm').modal('show');
            setTimeout(function () { btn.prop('disabled', false); }, 1000);
        });

        $('#searchForm').on('keyup', function () {
            var value = $(this).val().toLowerCase();
            $('.form-item').filter(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        $('.form-item').on('click', function (e) {
            e.preventDefault();
            var $this = $(this);
            if ($this.hasClass('disabled') || $this.data('clicked')) return;
            $this.data('clicked', true).addClass('disabled');
            setTimeout(function () { $this.data('clicked', false).removeClass('disabled'); }, 2000);

            var formType = $this.data('form-type');
            var formTitle = $(this).find('h6').text();
            var $modalPilih = $('#modalPilihForm');
            var $modalForm = $('#modalFormIsian');

            $('.modal-backdrop').not('.modal-backdrop:first').remove();

            $modalPilih.one('hidden.bs.modal', function () {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css({ 'padding-right': '', 'overflow': '' });
                setTimeout(function () {
                    $('#modalFormTitle').text(formTitle);
                    $modalForm.modal('show');
                    loadFormContent(formType);
                }, 150);
            });
            $modalPilih.modal('hide');
        });

        // ─── Load Form Content ────────────────────────────────────────────
        function loadFormContent(formType) {
            $('#modalFormBody').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Memuat form...</p></div>');
            $.ajax({
                url: 'load_form.php',
                type: 'GET',
                data: { form_type: formType },
                dataType: 'json',
                success: function (response) {
                    if (response && response.success) {
                        $('#modalFormBody').html(response.content);
                        $('#modalFormBody').find('script').each(function () {
                            var t = ($(this).attr('type') || '').toLowerCase();
                            if (t === 'text/template' || t === 'text/html' || t === 'text/x-template') return;
                            try { eval($(this).html()); } catch (e) { console.error('Script error:', e); }
                        });
                    } else {
                        $('#modalFormBody').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat form: ' + (response.error || 'Unknown error') + '</div>');
                    }
                },
                error: function (xhr, status, error) {
                    var errorMsg = 'Error: ' + error;
                    try {
                        var r = JSON.parse(xhr.responseText);
                        errorMsg = r.error || errorMsg;
                    } catch (e) { }
                    $('#modalFormBody').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + errorMsg + '</div>');
                }
            });
        }

        // ─── Simpan Form ──────────────────────────────────────────────────
        $('#btnSimpanForm').on('click', function () {
            var formId = '';
            if ($('#formBukaTanggalClosingan').length) formId = '#formBukaTanggalClosingan';
            else if ($('#formIzinKeluarPabrik').length) formId = '#formIzinKeluarPabrik';
            else if ($('#formIzinPulangCepat').length) formId = '#formIzinPulangCepat';

            if (!formId || !$(formId).length) return;

            if (!$(formId)[0].reportValidity()) return;

            if (typeof window.validateTTDBeforeSubmit === 'function') {
                var skipTTD = window._skipTTDCheckOnce;
                if (skipTTD) {
                    window._skipTTDCheckOnce = false;
                    submitFormAjax(formId);
                    return;
                }

                // Get selected employee name for IKP form
                var employeeName = null;
                if (formId === '#formIzinKeluarPabrik' && $('#ikpNama').length) {
                    employeeName = $('#ikpNama').val();
                } else if (formId === '#formIzinPulangCepat' && $('#ipcNama').length) {
                    employeeName = $('#ipcNama').val();
                }

                window.validateTTDBeforeSubmit(employeeName).then(function (canSubmit) {
                    if (canSubmit) submitFormAjax(formId);
                }).catch(function (err) {
                    console.error('TTD validation error:', err);
                    submitFormAjax(formId);
                });
                return;
            }
            submitFormAjax(formId);
        });

        function submitFormAjax(formId) {
            // Jika ini form buka_tanggal_closingan, trigger serialisasi items JSON dulu
            // sebelum serialize form, karena hidden field gudangTransaksiJson diisi
            // oleh closingRepeater, bukan oleh native form submit event
            if ($(formId).is('#formBukaTanggalClosingan') && window.closingRepeater) {
                var items = window.closingRepeater.serializeItems();
                if (!items || items.length === 0) {
                    showError('Minimal satu item harus diisi!', 'Validasi Gagal');
                    $('#btnSimpanForm').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
                    return;
                }
                $('#gudangTransaksiJson').val(JSON.stringify(items));
                $('#bukaTglTicket').val(items[0].buka_tgl || '');
            }

            var isMultipart = $(formId).attr('enctype') === 'multipart/form-data';
            var formData;
            var isUpdate = false;

            if (isMultipart) {
                formData = new FormData($(formId)[0]);
                isUpdate = formData.get('action') === 'update';
            } else {
                formData = $(formId).serialize();
                isUpdate = formData.indexOf('action=update') !== -1;
            }

            var formAction = isUpdate ? 'proses_update.php' : 'proses_simpan.php';

            var ajaxSettings = {
                url: formAction,
                type: 'POST',
                data: formData,
                dataType: 'json',
                beforeSend: function () {
                    $('#btnSimpanForm').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
                },
                success: function (response) {
                    console.log('[Form] Form submission response:', response);
                    if (response && response.success) {
                        var ticket = response.ticket;
                        console.log('[Form] Form saved successfully, ticket:', ticket);

                        // Save TTD first, then show success popup (same flow as Form IT)
                        if (ticket && window.saveTTDWithTicket) {
                            var employeeName = null;
                            if (formId === '#formIzinKeluarPabrik' && $('#ikpNama').length) {
                                employeeName = $('#ikpNama').val();
                            } else if (formId === '#formIzinPulangCepat' && $('#ipcNama').length) {
                                employeeName = $('#ipcNama').val();
                            }

                            console.log('[Form] Calling saveTTDWithTicket with employee:', employeeName);
                            window.saveTTDWithTicket(ticket, employeeName).then(function () {
                                console.log('[Form] TTD saved successfully');
                                showFormSaveSuccess(response, ticket);
                            }).catch(function (err) {
                                console.error('[Form] TTD save error (form already saved):', err);
                                showFormSaveSuccess(response, ticket);
                            });
                        } else {
                            showFormSaveSuccess(response, ticket);
                        }
                    } else {
                        showError(response.message || 'Gagal menyimpan data.', 'Gagal Menyimpan');
                        $('#btnSimpanForm').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
                    }
                },
                error: function (xhr) {
                    var errorMsg = 'Terjadi kesalahan saat menyimpan data.';
                    try {
                        var r = JSON.parse(xhr.responseText);
                        errorMsg = r.message || r.error || errorMsg;
                    } catch (e) { }
                    showError(errorMsg, 'Error');
                    $('#btnSimpanForm').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
                }
            };

            if (isMultipart) {
                ajaxSettings.processData = false;
                ajaxSettings.contentType = false;
            }

            $.ajax(ajaxSettings);
        }

        function showFormSaveSuccess(response, ticket) {
            var message = 'Data berhasil disimpan!';
            if (ticket) message += '<br><strong>No. Pengajuan: ' + ticket + '</strong>';
            showSuccess(message, 'Berhasil!');
            setTimeout(function () {
                $('#modalFormIsian').modal('hide');
                setTimeout(function () {
                    table.ajax.reload(function () {
                        initializeStatusBadges();
                    }, false); // false = keep current page
                }, 300);
            }, 1500);
        }

        // ─── Edit ─────────────────────────────────────────────────────────
        $(document).on('click', '.btn-edit', function () {
            var btn = $(this);
            if (btn.prop('disabled')) return;
            btn.prop('disabled', true);
            setTimeout(function () { btn.prop('disabled', false); }, 1500);

            var ticket = btn.data('ticket');
            if (!ticket) return;

            $('#modalFormTitle').text('Edit Form Pengajuan');
            $('#modalFormIsian').modal('show');
            loadEditForm(ticket);
        });

        function loadEditForm(ticket) {
            $('#modalFormBody').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Memuat data...</p></div>');
            $.ajax({
                url: 'load_data.php', type: 'GET', data: { ticket: ticket }, dataType: 'json',
                success: function (response) {
                    if (response && response.success) {
                        // Pakai flag dari server (sumber kebenaran tunggal) alih-alih
                        // pattern-match prefix tiket di client. Server sudah handle
                        // IKP-, IKS-, dan CLS- secara lengkap.
                        var formType = response.isClosing ? 'buka_tanggal_closingan' : (response.isIPC ? 'izin_pulang_cepat' : 'izin_keluar_pabrik');
                        loadFormContentForEdit(response.data, formType);
                    } else {
                        $('#modalFormBody').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data: ' + (response.error || 'Unknown') + '</div>');
                    }
                },
                error: function () {
                    $('#modalFormBody').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error memuat data</div>');
                }
            });
        }

        function loadFormContentForEdit(data, formType) {
            $.ajax({
                url: 'load_form.php', type: 'GET', data: { form_type: formType, ticket: data.ticket, edit: 1 }, dataType: 'json',
                success: function (response) {
                    if (response && response.success) {
                        $('#modalFormBody').html(response.content);
                        $('#modalFormBody').find('script').each(function () {
                            var t = ($(this).attr('type') || '').toLowerCase();
                            if (t === 'text/template' || t === 'text/html' || t === 'text/x-template') return;
                            try { eval($(this).html()); } catch (e) { console.warn('Script eval error:', e); }
                        });
                        populateFormWithData(data, formType);
                    } else {
                        $('#modalFormBody').html('<div class="alert alert-danger">Gagal memuat form edit.</div>');
                    }
                },
                error: function () {
                    $('#modalFormBody').html('<div class="alert alert-danger">Error memuat form edit.</div>');
                }
            });
        }

        // ── Helper: parse Legacy Format gudang_transaksi menjadi satu objek item ──
        function parseLegacyGudangTransaksi(gt) {
            // Mengembalikan objek item dari string legacy
            // Data legacy sudah di-parse di PHP side; di JS kita hanya perlu
            // fallback ke 1 item dengan gudang = string lama
            return {
                buka_tgl: '',
                request_gudang: 0,
                request_transaksi: 0,
                gudang: gt || '',
                jenis_transaksi: '',
                transaksi: '',
                nomor_transaksi: '',
                keterangan: ''
            };
        }

        function populateFormWithData(data, formType) {
            if (formType === 'buka_tanggal_closingan') {
                // ── Isi field header tiket ─────────────────────────────────────────
                $('input[name="nama_pemohon"]').val(data.nama_pemohon || '');
                $('input[name="jabatan"]').val(data.jabatan || '');
                $('input[name="departemen"]').val(data.departemen || '');
                $('input[name="area"]').val(data.bagian || data.area || '');

                if (data.tgl_pengajuan) {
                    var dStr = data.tgl_pengajuan;
                    if (dStr.includes('T')) dStr = dStr.split('T')[0];
                    if (dStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
                        var p = dStr.split('-');
                        dStr = p[2] + '-' + p[1] + '-' + p[0];
                    }
                    $('input[name="tgl_pengajuan"]').val(dStr);
                }

                // ── Parse gudang_transaksi → items array ───────────────────────────
                var gt = data.gudang_transaksi || '';
                var items = [];
                try {
                    var parsed = JSON.parse(gt);
                    if (Array.isArray(parsed)) {
                        items = parsed;  // Format baru: JSON array
                    } else {
                        items = [parseLegacyGudangTransaksi(gt)];  // Fallback: JSON tapi bukan array
                    }
                } catch (e) {
                    items = [parseLegacyGudangTransaksi(gt)];  // Legacy Format atau JSON tidak valid
                }

                // ── Rebuild item blocks via closingRepeater ────────────────────────
                if (window.closingRepeater) {
                    window.closingRepeater.populateItemBlocks(items);
                }

                // ── Set hidden fields for update ───────────────────────────────────
                var $form = $('#formBukaTanggalClosingan');
                $form.find('input[name="ticket"]').remove();
                $form.find('input[name="action"]').remove();
                $form.append('<input type="hidden" name="ticket" value="' + (data.ticket || '') + '">');
                $form.append('<input type="hidden" name="action" value="update">');
            } else if (formType === 'izin_keluar_pabrik') {
                // ── Isi field IKP ──────────────────────────────────────────────────
                $('input[name="nama_pemohon"]').val(data.nama_pemohon || '');
                $('input[name="departemen"]').val(data.departemen || '');
                $('input[name="bagian"]').val(data.bagian || '');
                $('input[name="jam_keluar_dari"], #ikpJamDari').val(data.jam_keluar_dari || '');
                $('input[name="jam_keluar_sampai"], #ikpJamSampai').val(data.jam_keluar_sampai || '');
                $('textarea[name="alasan"]').val(data.alasan || '');

                if (data.tipe_keluar) {
                    $('input[name="tipe_keluar"][value="' + data.tipe_keluar + '"]').prop('checked', true);
                }

                if (data.tgl_pengajuan) {
                    var ikpDate = data.tgl_pengajuan;
                    if (ikpDate.includes('T')) ikpDate = ikpDate.split('T')[0];
                    $('#ikpTglPengajuan').val(ikpDate);
                }

                if (data.hari) {
                    $('#ikpHari').val(data.hari);
                }

                // ── Set hidden fields for update ───────────────────────────────────
                var $ikpForm = $('#formIzinKeluarPabrik');
                $ikpForm.find('input[name="ticket"]').remove();
                $ikpForm.find('input[name="action"]').remove();
                $ikpForm.append('<input type="hidden" name="ticket" value="' + (data.ticket || '') + '">');
                $ikpForm.append('<input type="hidden" name="action" value="update">');
            }
        }

        // ─── Delete ───────────────────────────────────────────────────────
        $(document).on('click', '.btn-delete', function () {
            var btn = $(this);
            if (btn.prop('disabled')) return;
            btn.prop('disabled', true);
            setTimeout(function () { btn.prop('disabled', false); }, 1000);

            var ticket = btn.data('ticket');
            if (!ticket) return;

            Swal.fire({
                title: 'Hapus Data?',
                html: 'Apakah Anda yakin ingin menghapus pengajuan:<br><strong>' + ticket + '</strong><br><br>Data tidak dapat dikembalikan!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
            }).then(function (result) {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Menghapus...', html: 'Sedang menghapus data...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });
                    $.ajax({
                        url: 'proses_delete.php', type: 'POST', data: { ticket: ticket }, dataType: 'json',
                        success: function (response) {
                            if (response && response.success) {
                                showSuccess('Data berhasil dihapus!', 'Berhasil Dihapus');
                                setTimeout(function () {
                                    table.ajax.reload(function () {
                                        initializeStatusBadges();
                                    }, false);
                                }, 1500);
                            } else {
                                showError(response.message || 'Gagal menghapus data.', 'Gagal Menghapus');
                            }
                        },
                        error: function () { showError('Terjadi kesalahan saat menghapus data.', 'Error'); }
                    });
                }
            });
        });

        // ─── Modal cleanup ─────────────────────────────────────────────────
        $('#modalFormIsian').on('hidden.bs.modal', function () {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css({ 'padding-right': '', 'overflow': '' });
            $('#modalFormBody').html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2">Memuat form...</p></div>');
            $('#btnSimpanForm').prop('disabled', false).html('<i class="fas fa-save"></i> Simpan');
            $('#modalFormBody').scrollTop(0);
        });

        $('#modalPilihForm').on('hidden.bs.modal', function () {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css({ 'padding-right': '', 'overflow': '' });
        });

        $(document).on('hidden.bs.modal', '.modal', function () {
            setTimeout(function () {
                if ($('.modal.show').length === 0) {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css({ 'padding-right': '', 'overflow': '' });
                }
            }, 100);
        });

        // ─── Status Badge Update ───────────────────────────────────────────
        function updateStatusBadge(ticket, row) {
            // Skip adding unclosing/closed buttons for Izin Keluar tickets (IKS or IKP)
            var isIzinKeluar = ticket.indexOf('IKS-') === 0 || ticket.indexOf('IKP-') === 0;

            $.ajax({
                url: 'get_ttd_count.php?ticket=' + encodeURIComponent(ticket),
                method: 'GET', dataType: 'json',
                success: function (resp) {
                    if (resp && resp.success) {
                        var statusText = resp.status;
                        var count = resp.count || 0;
                        var required = resp.required || 4;
                        var badgeHtml = '';

                        // Specific status checks with appropriate colors
                        if (statusText === 'Ditolak') {
                            badgeHtml = '<span class="badge badge-danger">Ditolak</span>';
                        } else if (statusText === 'Unclosing') {
                            badgeHtml = '<span class="badge badge-warning">Unclosing</span>';
                        } else if (statusText === 'Closed') {
                            badgeHtml = '<span class="badge badge-dark">Closed</span>';
                        }
                        // Attendance statuses for Izin Keluar
                        else if (statusText === 'Belum Keluar') {
                            badgeHtml = '<span class="badge badge-secondary">Belum Keluar</span>';
                        } else if (statusText === 'Sedang Keluar') {
                            badgeHtml = '<span class="badge badge-warning">Sedang Keluar</span>';
                        } else if (statusText === 'Sudah Kembali') {
                            badgeHtml = '<span class="badge badge-success">Sudah Kembali</span>';
                        } else if (statusText === 'Terlambat Kembali') {
                            badgeHtml = '<span class="badge badge-danger">Terlambat Kembali</span>';
                        }
                        // TTD progress statuses
                        else if (count === 0) {
                            badgeHtml = '<span class="badge badge-warning">' + statusText + '</span>';
                        } else if (count >= required) {
                            badgeHtml = '<span class="badge badge-success">' + statusText + '</span>';
                        } else {
                            badgeHtml = '<span class="badge badge-info">' + statusText + '</span>';
                        }
                        row.find('td').eq(6).html(badgeHtml);
                        if (statusText === 'Ditolak') {
                            try {
                                if (!isAdmin) {
                                    row.find('i.fa-file-pdf').closest('a').remove();
                                }
                                row.find('i.fa-edit').closest('button').remove();
                            } catch (e) { }
                        } else if (count >= required) {
                            ensurePdfButton(ticket, row);
                            // Only add unclosing/closed buttons for closingan tickets, not Izin Keluar
                            if (!isIzinKeluar) {
                                if (statusText === 'Approved' && resp.can_process) {
                                    ensureProcessButton(ticket, row, 'unclosing', 'Unclosing');
                                } else if (statusText === 'Unclosing' && resp.can_process) {
                                    ensureProcessButton(ticket, row, 'closed', 'Closed');
                                } else if (statusText === 'Closed') {
                                    row.find('.btn-proses-closingan').remove();
                                }
                            }
                        } else {
                            if (!isAdmin) {
                                row.find('i.fa-file-pdf').closest('a').remove();
                            } else {
                                ensurePdfButton(ticket, row);
                            }
                            row.find('.btn-proses-closingan').remove();
                        }
                    }
                }
            });
        }
        window.updateStatusBadge = updateStatusBadge;

        function ensurePdfButton(ticket, row) {
            if (!ticket || row.find('i.fa-file-pdf').length) return;
            var $group = row.find('td').eq(7).find('.btn-group');
            if (!$group.length) return;

            // Determine PDF script based on ticket prefix
            var isIzinKeluar = ticket.indexOf('IKS-') === 0 || ticket.indexOf('IKP-') === 0;
            var isIzinPulangCepat = ticket.indexOf('IPC-') === 0;
            var pdfScript = isIzinKeluar ? 'generate_pdf_izin_keluar_pabrik.php' : (isIzinPulangCepat ? 'generate_pdf_izin_pulang_cepat.php' : 'generate_pdf_buka_tanggal_closingan.php');

            var pdfHtml = '<a href="./' + pdfScript + '?ticket=' + encodeURIComponent(ticket) + '" class="btn btn-danger btn-sm action-btn btn-pdf" target="_blank" title="PDF"><i class="fas fa-file-pdf"></i></a>';
            var $edit = $group.find('.btn-edit').first();
            if ($edit.length) {
                $edit.before(pdfHtml);
            } else {
                $group.append(pdfHtml);
            }
        }

        function ensureProcessButton(ticket, row, newStatus, targetLabel) {
            if (!ticket || row.find('.btn-proses-closingan').length) return;
            var $group = row.find('td').eq(7).find('.btn-group');
            if (!$group.length) return;
            var icon = newStatus === 'closed' ? 'fa-check' : 'fa-play';
            var btnClass = newStatus === 'closed' ? 'btn-secondary' : 'btn-success';
            var btnHtml = '<button type="button" class="btn ' + btnClass + ' btn-sm btn-proses-closingan action-btn" data-ticket="' + ticket + '" data-new-status="' + newStatus + '" data-target-label="' + targetLabel + '" title="Proses menjadi ' + targetLabel + '"><i class="fas ' + icon + '"></i> Proses</button>';
            var $delete = $group.find('.btn-delete').first();
            if ($delete.length) {
                $delete.before(btnHtml);
            } else {
                $group.append(btnHtml);
            }
        }

        function initializeStatusBadges() {
            $('#tiketTable tbody tr').each(function () {
                var row = $(this);
                var ticket = row.find('.btn-detail').data('ticket');
                if (ticket) updateStatusBadge(ticket, row);
            });
        }

        // ── Reload table from detail iframe after TTD sign/delete ────────
        // Called by detail_buka_tanggal_closingan.php via window.parent
        window.refreshTableRow = function (ticket) {
            table.ajax.reload(null, false);
        };

        // ── Auto-refresh setiap 15 detik ──────────────────────────────────
        // Ambil data terbaru dari server (form baru dari tab/user lain)
        // Menggunakan ajax.reload() murni — tidak manipulasi DOM secara manual
        var refreshInterval = setInterval(function () {
            // Hanya reload jika tidak ada modal yang sedang terbuka
            if ($('.modal.show').length === 0) {
                table.ajax.reload(null, false); // false = pertahankan halaman saat ini
            }
        }, 15000); // 15 detik

        // ── Refresh table saat modal detail ditutup ───────────────────────
        // Karena scan/update absensi dilakukan di dalam modal detail,
        // kita perlu refresh data table segera setelah modal ditutup
        $('#modalDetailTiket').on('hidden.bs.modal', function () {
            // Beri jeda 500ms agar database sempat terupdate
            setTimeout(function () {
                table.ajax.reload(null, false);
            }, 500);
        });

        // ─── Detail Modal ─────────────────────────────────────────────────
        $(document).on('click', '.btn-detail', function () {
            var btn = $(this);
            if (btn.prop('disabled')) return;
            btn.prop('disabled', true);
            setTimeout(function () { btn.prop('disabled', false); }, 1500);

            var ticket = btn.data('ticket');
            if (!ticket) return;

            var kategori = $(this).data('kategori');
            var detailUrl;
            if (kategori === 'Izin Keluar Pabrik') {
                detailUrl = 'detail_izin_keluar_pabrik.php?ticket=' + encodeURIComponent(ticket);
            } else if (kategori === 'Izin Pulang Cepat') {
                detailUrl = 'detail_izin_pulang_cepat.php?ticket=' + encodeURIComponent(ticket);
            } else {
                detailUrl = 'detail_buka_tanggal_closingan.php?ticket=' + encodeURIComponent(ticket);
            }

            $('#modalTicketNumber').text(ticket);
            $('#iframeDetailTiket').attr('src', detailUrl);
            $('#modalDetailTiket').modal('show');
        });

        $('#modalDetailTiket').on('hidden.bs.modal', function () {
            $('#iframeDetailTiket').attr('src', 'about:blank');
            $('#btnRejectFromModal').hide();
        });

        // ─── Reject ───────────────────────────────────────────────────────
        var ticketToReject = null;

        $(document).on('click', '#btnRejectFromModal', function () {
            ticketToReject = $('#modalTicketNumber').text();
            if (!ticketToReject) { showError('Ticket tidak ditemukan', 'Error'); return; }
            $('#rejectReasonFromList').val('');
            $('#modalRejectFromList').modal('show');
        });

        // --- Proses Unclosing / Closed --------------------------
        $(document).on('click', '.btn-proses-closingan', function () {
            var ticket = $(this).data('ticket');
            var newStatus = $(this).data('new-status');
            var targetLabel = $(this).data('target-label') || newStatus;
            if (!ticket) return;
            if (newStatus !== 'unclosing' && newStatus !== 'closed') {
                Swal.fire('Error', 'Status proses tidak valid.', 'error');
                return;
            }

            Swal.fire({
                icon: 'question',
                title: 'Konfirmasi Proses',
                text: 'Apakah Anda yakin ingin mengubah status menjadi ' + targetLabel + '?',
                showCancelButton: true,
                confirmButtonText: 'Ya, Proses',
                cancelButtonText: 'Batal'
            }).then(function (result) {
                if (!result.isConfirmed) return;
                $.post('update_status_closingan.php', { ticket: ticket, new_status: newStatus }, function (resp) {
                    if (resp.success) {
                        Swal.fire('Berhasil', resp.message, 'success').then(function () {
                            table.ajax.reload(function () {
                                initializeStatusBadges();
                            }, false);
                        });
                    } else {
                        Swal.fire('Gagal', resp.message, 'error');
                    }
                }, 'json').fail(function () {
                    Swal.fire('Error', 'Gagal menghubungi server.', 'error');
                });
            });
        });
        $('#btnConfirmRejectFromList').on('click', function () {
            var reason = $('#rejectReasonFromList').val().trim();
            if (!ticketToReject) { showError('Ticket tidak ditemukan', 'Error'); return; }

            $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');

            $.ajax({
                url: './proses_reject.php', type: 'POST',
                data: { ticket: ticketToReject, alasan: reason },
                dataType: 'json',
                success: function (resp) {
                    $('#btnConfirmRejectFromList').prop('disabled', false).html('Ya, Tolak Pengajuan');
                    if (resp && resp.success) {
                        $('#modalRejectFromList').modal('hide');
                        $('#modalDetailTiket').modal('hide');
                        showSuccess('Pengajuan berhasil ditolak!<br><strong>' + ticketToReject + '</strong>', 'Berhasil');

                        try {
                            var $row = $('button.btn-detail[data-ticket="' + ticketToReject + '"]').closest('tr');
                            if ($row.length) {
                                $row.find('td').eq(6).html("<span class='badge badge-danger'>Ditolak</span>");
                                $row.find('i.fa-file-pdf').closest('a').remove();
                                $row.find('i.fa-edit').closest('button').remove();
                                $('#btnRejectFromModal').hide();
                            }
                        } catch (e) { }
                    } else {
                        showError(resp.message || 'Gagal menolak pengajuan.', 'Error');
                    }
                },
                error: function (xhr) {
                    $('#btnConfirmRejectFromList').prop('disabled', false).html('Ya, Tolak Pengajuan');
                    var errorMsg = 'Terjadi kesalahan saat mengirim permintaan.';
                    try {
                        var r = JSON.parse(xhr.responseText);
                        errorMsg = r.message || r.error || errorMsg;
                    } catch (e) { }
                    showError(errorMsg, 'Error');
                }
            });
        });

        window.updateRejectButtonVisibility = function (canReject, ticket) {
            if (canReject) {
                $('#btnRejectFromModal').show();
                ticketToReject = ticket;
            } else {
                $('#btnRejectFromModal').hide();
            }
        };

        // QR Code modal and ESC/POS printer
        var currentQrTicket = '';
        var currentQrMode = 'link';
        var currentPrintOutputMode = 'qr';

        function getQrUrl(ticket, mode) {
            return 'generate_qr_form_umum.php?ticket=' + encodeURIComponent(ticket) + '&mode=' + encodeURIComponent(mode || 'link');
        }

        function getBarcodeUrl(ticket) {
            return 'generate_barcode_form_umum.php?ticket=' + encodeURIComponent(ticket);
        }

        function updateQrModeButtons(mode) {
            $('#btnQrModeLink').toggleClass('btn-primary active', mode === 'link').toggleClass('btn-outline-primary', mode !== 'link');
            $('#btnQrModeTicket').toggleClass('btn-primary active', mode === 'ticket').toggleClass('btn-outline-primary', mode !== 'ticket');
            $('#modalQrHelp').html(mode === 'ticket' ? 'QR berisi nomor ticket saja.' : 'QR berisi link scan langsung untuk halaman ini.');
        }

        function applyPrintOutputMode(mode) {
            currentPrintOutputMode = mode === 'barcode' ? 'barcode' : 'qr';
            var barcode = currentPrintOutputMode === 'barcode';
            $('#btnQrModeLink, #btnQrModeTicket').parent().toggle(!barcode);
            $('#modalQrIzin .modal-title').html('<i class="fas ' + (barcode ? 'fa-barcode' : 'fa-qrcode') + ' mr-2"></i>' + (barcode ? 'Barcode Izin Keluar' : 'QR Code Izin Keluar'));
            $('#modalQrImg').attr('alt', barcode ? 'Barcode Ticket' : 'QR Code').css({
                width: barcode ? '320px' : '240px',
                height: barcode ? '120px' : '240px'
            });
            $('#modalQrContainer').css('padding', barcode ? '12px' : '16px');
        }

        function loadQrImage(ticket, mode) {
            var barcode = currentPrintOutputMode === 'barcode';
            var url = barcode ? getBarcodeUrl(ticket) : getQrUrl(ticket, mode);
            var safe = ticket.replace(/[^a-zA-Z0-9_-]/g, '_');
            var $img = $('#modalQrImg');
            var $container = $('#modalQrContainer');
            $container.css('opacity', 0.4);
            $img.attr('src', 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            $('#btnDownloadQr').attr({'href': url, 'download': (barcode ? 'barcode-' : 'qr-' + mode + '-') + safe + '.png'});
            $('#modalQrHelp').html(barcode ? '' : (mode === 'ticket' ? 'QR berisi nomor ticket saja.' : 'QR berisi link scan langsung untuk halaman ini.'));

            var pre = new Image();
            pre.onload = function () {
                $img.attr('src', url);
                $container.css('opacity', 1);
            };
            pre.onerror = function () {
                $container.css('opacity', 1);
                showError(barcode ? 'Gagal membuat Barcode.' : 'Gagal membuat QR Code.', barcode ? 'Barcode Error' : 'QR Error');
                $('#modalQrIzin').modal('hide');
            };
            pre.src = url;
        }

        function printQrInBrowser() {
            var ticket = $('#modalQrTicket').text().trim();
            var subtitle = $('#modalQrSubtitle').text().trim();
            var qrSrc = $('#modalQrImg').attr('src');
            var barcode = currentPrintOutputMode === 'barcode';
            var printLabel = barcode ? 'Barcode' : 'QR Code';

            if (!qrSrc || qrSrc.indexOf('data:image/gif;base64,R0lGODlhAQABA') === 0) {
                showError(printLabel + ' belum siap untuk dicetak.', 'Print ' + printLabel);
                return;
            }

            var printWindow = window.open('', '_blank', 'width=420,height=620');
            if (!printWindow) {
                showError('Popup print diblokir browser. Izinkan popup untuk mencetak ' + printLabel + '.', 'Print ' + printLabel);
                return;
            }

            var imageLaneCss = barcode
                ? '.qr-lane{width:100%;margin:0 auto 2mm auto;text-align:center;display:flex;justify-content:center;}.qr-lane img{max-width:62mm;width:auto;height:auto;display:block;margin:0 auto;}'
                : '.qr-lane{width:100%;margin:0 auto 2mm auto;text-align:center;display:flex;justify-content:center;}.qr-lane img{width:44mm;height:44mm;display:block;margin:0 auto;image-rendering:pixelated;}';
            var textLaneCss = '.text-lane{width:100%;margin:0 auto 2mm auto;color:#000;font-weight:700;text-align:center;overflow-wrap:anywhere;word-break:break-word;}';
            var note = barcode
                ? ''
                : (currentQrMode === 'ticket'
                    ? 'QR berisi nomor ticket saja.'
                    : 'QR berisi link scan langsung.<br>Tunjukkan ke Satpam untuk scan keluar / kembali.');
            var noteHtml = note ? '<div class="text-lane note">' + note + '</div>' : '';

            printWindow.document.write(
                '<!doctype html><html><head><title>Print ' + printLabel + ' ' + ticket + '</title>' +
                '<style>' +
                '@page{size:76mm auto;margin:0;}' +
                'html,body{width:100%;background:#fff;color:#000;margin:0;padding:0;overflow:hidden;text-align:center;}' +
                'body{font-family:Arial,Helvetica,sans-serif;text-align:center;-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
                '.card{width:100%;max-width:76mm;margin:0 auto;padding:2mm 0;border:none;box-sizing:border-box;text-align:center;}' +
                textLaneCss +
                '.brand{font-size:11px;line-height:1.15;letter-spacing:0;margin-top:0;text-transform:uppercase;}' +
                'h2{font-size:15px;line-height:1.15;font-weight:800;}' +
                'p{font-size:11px;}' +
                imageLaneCss +
                '.note{font-size:10px;line-height:1.25;margin-bottom:0;}' +
                '@media print{.card{page-break-inside:avoid;break-inside:avoid;}}' +
                '</style></head><body>' +
                '<div class="card">' +
                '<div class="text-lane brand">PT SURYA USAHA MANDIRI</div>' +
                '<h2 class="text-lane">' + ticket + '</h2>' +
                '<p class="text-lane">' + subtitle + '</p>' +
                '<div class="qr-lane"><img src="' + qrSrc + '" alt="' + printLabel + '"></div>' +
                noteHtml +
                '</div>' +
                '<script>window.onload=function(){setTimeout(function(){window.focus();window.print();},150);};<\/script>' +
                '</body></html>'
            );
            printWindow.document.close();
        }

        function printQrCalibration() {
            var printWindow = window.open('', '_blank', 'width=420,height=620');
            if (!printWindow) {
                showError('Popup print diblokir browser. Izinkan popup untuk mencetak kalibrasi.', 'Kalibrasi Print');
                return;
            }

            printWindow.document.write(
                '<!doctype html><html><head><title>Kalibrasi TM-U220</title>' +
                '<style>' +
                '@page{size:76mm auto;margin:0;}' +
                'html,body{width:76mm;background:#fff;color:#000;margin:0;padding:0;overflow:hidden;}' +
                'body{font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
                '.sheet{position:relative;width:76mm;height:95mm;margin:0;padding:0;box-sizing:border-box;}' +
                '.rule{position:absolute;left:0;top:6mm;width:76mm;height:18mm;border-left:1px solid #000;border-right:1px solid #000;border-top:1px solid #000;border-bottom:1px solid #000;}' +
                '.center{position:absolute;left:38mm;top:0;width:0;height:95mm;border-left:1px dashed #000;}' +
                '.label{position:absolute;left:1mm;top:1mm;}' +
                '.mark{position:absolute;left:0;width:100%;height:0;border-top:1px solid #000;}' +
                '.m1{top:30mm}.m2{top:50mm}.m3{top:70mm}' +
                '.box{position:absolute;top:34mm;width:18mm;height:18mm;border:1px solid #000;text-align:center;line-height:18mm;}' +
                '.o0{left:29mm}.on3{left:26mm}.on6{left:23mm}.on9{left:20mm}' +
                '.txt{position:absolute;left:1mm;font-size:10px;line-height:1.2;}' +
                '.t0{top:25mm}.t1{top:54mm}.t2{top:74mm}' +
                '</style></head><body>' +
                '<div class="sheet">' +
                '<div class="label">TM-U220 76mm CALIBRATION</div>' +
                '<div class="rule"></div><div class="center"></div>' +
                '<div class="mark m1"></div><div class="mark m2"></div><div class="mark m3"></div>' +
                '<div class="txt t0">Batas kiri/kanan + garis tengah. Kalau garis tengah fisik condong kanan, driver/paper punya offset.</div>' +
                '<div class="box o0">0</div><div class="box on3">-3</div><div class="box on6">-6</div><div class="box on9">-9</div>' +
                '<div class="txt t1">Pilih kotak yang paling pas di tengah kertas: 0 / -3 / -6 / -9 mm.</div>' +
                '<div class="txt t2">Set browser: Margins None, Scale 100, Headers off, paper Roll 76mm.</div>' +
                '</div>' +
                '<script>window.onload=function(){setTimeout(function(){window.focus();window.print();},150);};<\/script>' +
                '</body></html>'
            );
            printWindow.document.close();
        }

        function loadQrPrinterSettings(done) {
            $.getJSON('qr_printer_settings.php', function (response) {
                if (!response.ok) return;
                var s = response.settings || {};
                $('#qrPrinterPrintMode').val(s.print_mode || 'qr');
                $('#qrPrinterTransport').val(s.transport || 'windows').trigger('change');
                $('#qrPrinterName').val(s.printer_name || '');
                $('#qrPrinterIp').val(s.ip || '');
                $('#qrPrinterPort').val(s.port || 9100);
                applyPrintOutputMode(s.print_mode || 'qr');
                if (typeof done === 'function') done();
            }).fail(function () {
                applyPrintOutputMode('qr');
                if (typeof done === 'function') done();
            });
        }

        $('#qrPrinterTransport').on('change', function () {
            var lan = $(this).val() === 'lan';
            $('#qrPrinterWindowsGroup').toggle(!lan);
            $('#qrPrinterLanGroup').toggle(lan);
        });

        $(document).on('click', '#btnQrPrinterSettings', function () {
            loadQrPrinterSettings();
            $('#modalQrPrinterSettings').modal('show');
        });

        $(document).on('click', '#btnSaveQrPrinterSettings', function () {
            $.ajax({
                url: 'qr_printer_settings.php',
                method: 'POST',
                data: {
                    print_mode: $('#qrPrinterPrintMode').val(),
                    transport: $('#qrPrinterTransport').val(),
                    printer_name: $('#qrPrinterName').val(),
                    ip: $('#qrPrinterIp').val(),
                    port: $('#qrPrinterPort').val()
                },
                dataType: 'json'
            }).done(function (response) {
                if (!response.ok) {
                    showError(response.message || 'Gagal menyimpan setting', 'Printer');
                    return;
                }
                applyPrintOutputMode($('#qrPrinterPrintMode').val());
                if (currentQrTicket) loadQrImage(currentQrTicket, currentQrMode);
                $('#modalQrPrinterSettings').modal('hide');
            }).fail(function () {
                showError('Gagal menyimpan setting printer.', 'Printer');
            });
        });

        $(document).on('click', '.btn-qr-izin', function () {
            var ticket = $(this).data('ticket');
            if (!ticket) {
                showError('Ticket tidak ditemukan', 'Error');
                return;
            }
            currentQrTicket = ticket;
            currentQrMode = 'link';
            updateQrModeButtons(currentQrMode);
            $('#modalQrTicket').text(ticket);
            $('#modalQrSubtitle').text(ticket.indexOf('IPC-') === 0 ? 'Tiket Izin Pulang Cepat' : 'Tiket Izin Keluar Pabrik');
            $('#modalQrIzin').modal('show');
            loadQrPrinterSettings(function () {
                loadQrImage(ticket, currentQrMode);
            });
        });

        $(document).on('click', '#btnQrModeLink, #btnQrModeTicket', function () {
            var mode = $(this).data('mode');
            if (!currentQrTicket || mode === currentQrMode) return;
            currentQrMode = mode;
            updateQrModeButtons(mode);
            loadQrImage(currentQrTicket, mode);
        });

        $(document).on('click', '#btnPrintQrIzin', function () {
            var ticket = $('#modalQrTicket').text().trim();
            var button = this;
            if (!ticket) return;

            $(button).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Print...');
            printQrInBrowser();
            setTimeout(function () {
                $(button).prop('disabled', false).html('<i class="fas fa-print"></i> Print');
            }, 500);
        });

        $(document).on('click', '#btnPrintQrCalibration', function () {
            var button = this;
            $(button).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Kalibrasi...');
            printQrCalibration();
            setTimeout(function () {
                $(button).prop('disabled', false).html('<i class="fas fa-ruler-horizontal"></i> Kalibrasi');
            }, 500);
        });

    });
</script>
