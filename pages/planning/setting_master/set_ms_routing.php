<?php
session_start();
ob_start();

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

include '../../../koneksi.php';
include '../../../koneksi3.php';
include '../../../includes/header.php';
include '../../../includes/sidebar.php';

$themeColor = $_SESSION['Theme'] ?? 'primary';
?>

<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<style>
    .routing-card-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
    }

    .tab-routing {
        border-bottom: 1px solid #dee2e6;
        margin-bottom: 10px;
    }

    .tab-routing .nav-link {
        cursor: pointer;
    }

    .tab-routing .nav-link.active {
        font-weight: 700;
    }

    .routing-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: end;
        justify-content: space-between;
        margin: 12px 0 16px;
    }

    .routing-toolbar .left-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .routing-toolbar .right-filter {
        min-width: 260px;
    }

    .entry-panel {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 10px;
        background: #f8f9fa;
        margin-bottom: 12px;
    }

    .entry-panel label {
        font-size: 0.8rem;
        margin-bottom: 4px;
        font-weight: 600;
    }

    .entry-panel .form-control[readonly] {
        background-color: #ffffff;
    }

    .table thead th {
        white-space: nowrap;
    }

    #searchRoutingTable td,
    #mainRoutingTable td,
    #searchMachineTable td,
    #mainMachineTable td,
    #mainBreakTable td,
    #mainDownTimeTable td,
    #mainMaxCapacityTable td,
    #planTypeTable td {
        vertical-align: middle;
        font-size: 0.92rem;
    }

    #mainRoutingTable th,
    #mainRoutingTable td,
    #searchRoutingTable th,
    #searchRoutingTable td {
        text-align: center;
    }

    .tab-pane-section {
        display: none;
    }

    .tab-pane-section.active {
        display: block;
    }

    .plan-type-panel {
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 14px;
        background: #f8f9fa;
        margin-top: 12px;
    }

    #btnAddPlanType {
        min-width: 116px;
        height: calc(1.8125rem + 2px);
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .modal-xl {
        max-width: 1200px;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Setting Master CP Planning</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="/gg_app/pages/planning/index.php">Planning</a></li>
                        <li class="breadcrumb-item active">Setting Routing</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="routing-card-title">CP Planning Setting</h3>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs tab-routing" id="masterTabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-tab-target="tabPlanType">Tipe Planning</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab-target="tabRouting">Routing</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab-target="tabMachine">Machine</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab-target="tabBreakTime">Break Time</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab-target="tabDownTime">Down Time</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-tab-target="tabMaxCapacity">Max Production Capacity</a>
                        </li>
                    </ul>

                    <div id="tabRouting" class="tab-pane-section">
                        <div class="entry-panel">
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label for="entryPlanType">Plan Type</label>
                                    <select id="entryPlanType" class="form-control form-control-sm"></select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="entryRoutingCode">Routing Code</label>
                                    <div class="input-group input-group-sm">
                                        <input type="hidden" id="entryRtgmsid" />
                                        <input type="text" id="entryRoutingCode" class="form-control" placeholder="Klik ... untuk pilih" readonly />
                                        <div class="input-group-append">
                                            <button type="button" id="btnLookupRouting" class="btn btn-outline-secondary" title="Cari Routing">...</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="entryRoutingName">Routing Name</label>
                                    <input type="text" id="entryRoutingName" class="form-control form-control-sm" readonly />
                                </div>
                                <div class="form-group col-md-2">
                                    <label>Use Machine</label>
                                    <div class="form-control form-control-sm d-flex align-items-center justify-content-center">
                                        <input type="checkbox" id="entryUseMachine" disabled />
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-12 d-flex align-items-end justify-content-end">
                                    <button type="button" id="btnSaveEntry" class="btn btn-primary btn-sm mr-2">
                                        <i class="fas fa-check mr-1"></i> Simpan
                                    </button>
                                    <button type="button" id="btnClearEntry" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-undo mr-1"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="mainRoutingTable" class="table table-bordered table-hover table-sm w-100">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:32px;" class="text-center">
                                            <input type="checkbox" id="checkAllMain" />
                                        </th>
                                        <th class="text-center">Plan Type</th>
                                        <th class="text-center">Routing Code</th>
                                        <th class="text-center">Routing Name</th>
                                        <th class="text-center">Use Machine</th>
                                        <th class="text-center">Last Update</th>
                                        <th class="text-center">Updated By</th>
                                        <th style="width:150px;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="tabMachine" class="tab-pane-section">
                        <div class="entry-panel">
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label for="entryPlanTypeMachine">Plan Type</label>
                                    <select id="entryPlanTypeMachine" class="form-control form-control-sm"></select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="entryMachineCode">Machine Code</label>
                                    <div class="input-group input-group-sm">
                                        <input type="hidden" id="entryFamasterid" />
                                        <input type="text" id="entryMachineCode" class="form-control" placeholder="Klik ... untuk pilih" readonly />
                                        <div class="input-group-append">
                                            <button type="button" id="btnLookupMachine" class="btn btn-outline-secondary" title="Cari Machine">...</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="entryMachineName">Machine Name</label>
                                    <input type="text" id="entryMachineName" class="form-control form-control-sm" readonly />
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="entryMachineDesc">Description</label>
                                    <input type="text" id="entryMachineDesc" class="form-control form-control-sm" placeholder="Ketik description..." />
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-12 d-flex align-items-end justify-content-end">
                                    <button type="button" id="btnSaveMachineEntry" class="btn btn-primary btn-sm mr-2">
                                        <i class="fas fa-check mr-1"></i> Simpan
                                    </button>
                                    <button type="button" id="btnClearMachineEntry" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-undo mr-1"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="mainMachineTable" class="table table-bordered table-hover table-sm w-100">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:32px;" class="text-center">
                                            <input type="checkbox" id="checkAllMachineMain" />
                                        </th>
                                        <th class="text-center">Plan Type</th>
                                        <th class="text-center">Machine Code</th>
                                        <th class="text-center">Machine Name</th>
                                        <th class="text-center">Description</th>
                                        <th class="text-center">Last Update</th>
                                        <th class="text-center">Updated By</th>
                                        <th style="width:150px;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="tabBreakTime" class="tab-pane-section">
                        <div class="entry-panel">
                            <div class="form-row">
                                <div class="form-group col-md-7">
                                    <label for="entryBreakTimeName">Break Time</label>
                                    <input type="hidden" id="entryBreakId" />
                                    <input type="text" id="entryBreakTimeName" class="form-control form-control-sm" maxlength="200" placeholder="Ketik nama break time..." />
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="entryBreakTimeMinutes">Waktu (Menit)</label>
                                    <input type="number" id="entryBreakTimeMinutes" class="form-control form-control-sm" min="0" step="1" placeholder="Contoh: 30" />
                                </div>
                                <div class="form-group col-md-2 d-flex align-items-end justify-content-end">
                                    <button type="button" id="btnSaveBreakEntry" class="btn btn-primary btn-sm mr-2">
                                        <i class="fas fa-check mr-1"></i> Simpan
                                    </button>
                                    <button type="button" id="btnClearBreakEntry" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-undo mr-1"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="mainBreakTable" class="table table-bordered table-hover table-sm w-100">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:32px;" class="text-center">
                                            <input type="checkbox" id="checkAllBreakMain" />
                                        </th>
                                        <th class="text-center">Break Time</th>
                                        <th class="text-center">Waktu (Menit)</th>
                                        <th class="text-center">Last Update</th>
                                        <th class="text-center">Updated By</th>
                                        <th style="width:150px;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="tabDownTime" class="tab-pane-section">
                        <div class="entry-panel">
                            <div class="form-row">
                                <div class="form-group col-md-10">
                                    <label for="entryDownTimeName">Down Time</label>
                                    <input type="hidden" id="entryDownTimeId" />
                                    <input type="text" id="entryDownTimeName" class="form-control form-control-sm" maxlength="200" placeholder="Ketik nama down time..." />
                                </div>
                                <div class="form-group col-md-2 d-flex align-items-end justify-content-end">
                                    <button type="button" id="btnSaveDownTimeEntry" class="btn btn-primary btn-sm mr-2">
                                        <i class="fas fa-check mr-1"></i> Simpan
                                    </button>
                                    <button type="button" id="btnClearDownTimeEntry" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-undo mr-1"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="mainDownTimeTable" class="table table-bordered table-hover table-sm w-100">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:32px;" class="text-center">
                                            <input type="checkbox" id="checkAllDownTimeMain" />
                                        </th>
                                        <th class="text-center">Down Time</th>
                                        <th class="text-center">Last Update</th>
                                        <th class="text-center">Updated By</th>
                                        <th style="width:150px;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="tabMaxCapacity" class="tab-pane-section">
                        <div class="entry-panel">
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="entryPlanTypeCapacity">Plan Type</label>
                                    <input type="hidden" id="entryMaxCapacityId" />
                                    <select id="entryPlanTypeCapacity" class="form-control form-control-sm"></select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="entryMachineCapacity">Machine</label>
                                    <select id="entryMachineCapacity" class="form-control form-control-sm"></select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="entryMaxCapacityDay">Max Capacity/Day</label>
                                    <input type="text" id="entryMaxCapacityDay" class="form-control form-control-sm" maxlength="30" placeholder="Contoh: 54.000" />
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-12 d-flex align-items-end justify-content-end">
                                    <button type="button" id="btnSaveMaxCapacityEntry" class="btn btn-primary btn-sm mr-2">
                                        <i class="fas fa-check mr-1"></i> Simpan
                                    </button>
                                    <button type="button" id="btnClearMaxCapacityEntry" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-undo mr-1"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="mainMaxCapacityTable" class="table table-bordered table-hover table-sm w-100">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:32px;" class="text-center">
                                            <input type="checkbox" id="checkAllMaxCapacityMain" />
                                        </th>
                                        <th class="text-center">Plan Type</th>
                                        <th class="text-center">Machine</th>
                                        <th class="text-center">Max Capacity/Day</th>
                                        <th class="text-center">Last Update</th>
                                        <th class="text-center">Updated By</th>
                                        <th style="width:150px;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="tabPlanType" class="tab-pane-section active">
                        <div class="plan-type-panel">
                            <div class="form-row">
                                <div class="form-group col-md-7 mb-2">
                                    <label for="newPlanType">Tambah Tipe Planning</label>
                                    <input type="text" id="newPlanType" class="form-control form-control-sm" maxlength="80" placeholder="Contoh: Open Width">
                                </div>
                                <div class="form-group col-md-5 mb-2 d-flex align-items-end">
                                    <button type="button" id="btnAddPlanType" class="btn btn-success btn-sm mr-2">
                                        <i class="fas fa-plus mr-1"></i> Tambah Tipe
                                    </button>
                                    <small class="text-muted">Menghapus tipe akan menghapus mapping routing dan machine pada tipe tersebut.</small>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="planTypeTable" class="table table-bordered table-hover table-sm w-100 mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width:60px;" class="text-center">No</th>
                                            <th>Tipe Planning</th>
                                            <th style="width:180px;" class="text-center">Jumlah Routing</th>
                                            <th style="width:140px;" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="planTypeTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="searchRoutingModal" tabindex="-1" role="dialog" aria-labelledby="searchRoutingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="searchRoutingModalLabel">Search Routing</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-3 mb-2 mb-md-0" id="wrapFormPlanType">
                        <label for="formPlanType" class="mb-1">Plan Type</label>
                        <select id="formPlanType" class="form-control form-control-sm"></select>
                    </div>
                    <div class="col-md-3 mb-2 mb-md-0">
                        <label for="searchBy" class="mb-1">Search For</label>
                        <select id="searchBy" class="form-control form-control-sm">
                            <option value="rtgcode">Routing Code</option>
                            <option value="rtgname">Routing Name</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label for="searchKeyword" class="mb-1">Keyword</label>
                        <input type="text" id="searchKeyword" class="form-control form-control-sm" placeholder="Ketik keyword routing...">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" id="btnSearchRouting" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-search mr-1"></i> Cari
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="searchRoutingTable" class="table table-bordered table-hover table-sm w-100">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:32px;" class="text-center">
                                    <input type="checkbox" id="checkAllSearch">
                                </th>
                                <th class="text-center">Routing Code</th>
                                <th class="text-center">Routing Name</th>
                                <th class="text-center">Use Machine</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <small id="routingSelectedInfo" class="mr-auto text-muted">0 routing dipilih</small>
                <button type="button" id="btnSubmitRouting" class="btn btn-primary btn-sm">Submit</button>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="searchMachineModal" tabindex="-1" role="dialog" aria-labelledby="searchMachineModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="searchMachineModalLabel">Search Machine</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-3 mb-2 mb-md-0">
                        <label for="searchMachineBy" class="mb-1">Search For</label>
                        <select id="searchMachineBy" class="form-control form-control-sm">
                            <option value="facode">Machine Code</option>
                            <option value="faname">Machine Name</option>
                        </select>
                    </div>
                    <div class="col-md-7 mb-2 mb-md-0">
                        <label for="searchMachineKeyword" class="mb-1">Keyword</label>
                        <input type="text" id="searchMachineKeyword" class="form-control form-control-sm" placeholder="Ketik keyword machine...">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" id="btnSearchMachine" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-search mr-1"></i> Cari
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="searchMachineTable" class="table table-bordered table-hover table-sm w-100">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:32px;" class="text-center">
                                    <input type="checkbox" id="checkAllMachineSearch">
                                </th>
                                <th>Machine Code</th>
                                <th>Machine Name</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <small id="machineSelectedInfo" class="mr-auto text-muted">0 machine dipilih</small>
                <button type="button" id="btnSubmitMachine" class="btn btn-primary btn-sm">Submit</button>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tabs = document.querySelectorAll('#masterTabs .nav-link[data-tab-target]');
    var panes = document.querySelectorAll('.tab-pane-section');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            if (tab.classList.contains('disabled')) return;

            tabs.forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');

            panes.forEach(function (pane) { pane.classList.remove('active'); });
            var targetId = tab.getAttribute('data-tab-target');
            var targetPane = document.getElementById(targetId);
            if (targetPane) {
                targetPane.classList.add('active');
            }
        });
    });
});
</script>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
$(function () {
    var apiUrl = 'set_ms_routing_api.php';
    var selectedRoutingIds = {};
    var selectedMachineIds = {};
    var planTypeRows = [];
    var entryMode = 'add';
    var editingRoutingId = '';
    var machineEntryMode = 'add';
    var editingMachineId = '';
    var breakEntryMode = 'add';
    var editingBreakId = '';
    var downTimeEntryMode = 'add';
    var editingDownTimeId = '';
    var maxCapacityEntryMode = 'add';
    var editingMaxCapacityId = '';

    function safeHtml(text) {
        return $('<div/>').text(text == null ? '' : String(text)).html();
    }

    function toast(icon, title) {
        Swal.fire({
            icon: icon,
            title: title,
            timer: 1800,
            showConfirmButton: false
        });
    }

    function formatDateTime(val) {
        if (!val) return '-';
        var dt = new Date(val);
        if (!isNaN(dt.getTime())) {
            return dt.toLocaleString('id-ID');
        }
        return String(val);
    }

    function formatCapacityNumber(val) {
        var num = Number(val);
        if (!Number.isFinite(num)) {
            return '-';
        }
        return num.toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }

    function parseCapacityInput(val) {
        var raw = String(val || '').trim();
        if (!raw) {
            return NaN;
        }
        var digitsOnly = raw.replace(/[^0-9]/g, '');
        if (!digitsOnly) {
            return NaN;
        }
        return Number(digitsOnly);
    }

    function getPlanTypes() {
        return planTypeRows.map(function (r) { return r.plan_type; });
    }

    function findPlanTypeCaseInsensitive(target) {
        var t = String(target || '').toLowerCase();
        var found = '';
        $.each(getPlanTypes(), function (_, type) {
            if (String(type).toLowerCase() === t) {
                found = type;
                return false;
            }
        });
        return found;
    }

    function setSelectOptions($select, options, includeAll, preferredValue) {
        var current = $select.val();
        $select.empty();
        if (includeAll) {
            $select.append('<option value="">Semua Tipe</option>');
        }
        $.each(options, function (_, item) {
            $select.append('<option value="' + safeHtml(item) + '">' + safeHtml(item) + '</option>');
        });

        var finalValue = '';
        if (preferredValue && options.indexOf(preferredValue) >= 0) {
            finalValue = preferredValue;
        } else if (current && options.indexOf(current) >= 0) {
            finalValue = current;
        } else if (!includeAll && options.length) {
            finalValue = options[0];
        }
        $select.val(finalValue);
    }

    function renderPlanTypeTable() {
        var $body = $('#planTypeTableBody');
        $body.empty();

        if (!planTypeRows.length) {
            $body.append(
                '<tr><td colspan="4" class="text-center text-muted">Belum ada tipe planning.</td></tr>'
            );
            return;
        }

        $.each(planTypeRows, function (idx, row) {
            var btnDelete = '<button type="button" class="btn btn-danger btn-xs btnDeletePlanType" ' +
                'data-type="' + safeHtml(row.plan_type) + '" data-count="' + Number(row.route_count || 0) + '">' +
                '<i class="fas fa-trash"></i> Hapus</button>';

            $body.append(
                '<tr>' +
                    '<td class="text-center">' + (idx + 1) + '</td>' +
                    '<td>' + safeHtml(row.plan_type) + '</td>' +
                    '<td class="text-center">' + Number(row.route_count || 0) + '</td>' +
                    '<td class="text-center">' + btnDelete + '</td>' +
                '</tr>'
            );
        });
    }

    function syncPlanTypeControls(preferredValue) {
        var types = getPlanTypes();
        setSelectOptions($('#entryPlanType'), types, false, preferredValue);
        setSelectOptions($('#formPlanType'), types, false, preferredValue);
        setSelectOptions($('#entryPlanTypeMachine'), types, false, preferredValue);
        setSelectOptions($('#entryPlanTypeCapacity'), types, false, preferredValue);
        loadMaxCapacityMachineOptions($('#entryMachineCapacity').val());
    }

    function loadPlanTypes(preferredValue) {
        return $.getJSON(apiUrl, { action: 'plan_type_list' }).done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal memuat tipe planning.');
                return;
            }
            planTypeRows = res.data || [];
            renderPlanTypeTable();
            syncPlanTypeControls(preferredValue);
        }).fail(function () {
            toast('error', 'Gagal memuat tipe planning.');
        });
    }

    function clearEntryRoutingFields() {
        entryMode = 'add';
        editingRoutingId = '';
        $('#btnSaveEntry').html('<i class="fas fa-check mr-1"></i> Simpan');
        $('#entryRtgmsid').val('');
        $('#entryRoutingCode').val('');
        $('#entryRoutingName').val('');
        $('#entryRoutingName').prop('readonly', true);
        $('#entryUseMachine').prop('checked', false);
        $('#entryUseMachine').prop('disabled', true);
    }

    function getMainRowFromButton($btn) {
        var $tr = $btn.closest('tr');
        if ($tr.hasClass('child')) {
            $tr = $tr.prev();
        }
        return mainTable.row($tr).data() || null;
    }

    function startEditEntry(rowData) {
        if (!rowData) return;

        var currentType = rowData.plan_type || '';
        var canonicalType = findPlanTypeCaseInsensitive(currentType);
        if (canonicalType) {
            $('#entryPlanType').val(canonicalType);
        } else if (currentType) {
            $('#entryPlanType').val(currentType);
        }

        entryMode = 'edit';
        editingRoutingId = String(rowData.id || rowData.rtgcode || '').trim();
        $('#btnSaveEntry').html('<i class="fas fa-save mr-1"></i> Update');

        $('#entryRtgmsid').val('');
        $('#entryRoutingCode').val(rowData.rtgcode || rowData.id || '');
        $('#entryRoutingName').val(rowData.rtgname || '');
        $('#entryRoutingName').prop('readonly', false);
        $('#entryUseMachine').prop('checked', String(rowData.fgmachine || '').toUpperCase() === 'Y');
        $('#entryUseMachine').prop('disabled', false);
    }

    function clearEntryMachineFields() {
        machineEntryMode = 'add';
        editingMachineId = '';
        $('#btnSaveMachineEntry').html('<i class="fas fa-check mr-1"></i> Simpan');
        $('#entryFamasterid').val('');
        $('#entryMachineCode').val('');
        $('#entryMachineName').val('');
        $('#entryMachineDesc').val('');
        $('#entryMachineName').prop('readonly', true);
        $('#entryMachineDesc').prop('readonly', false);
    }

    function getMachineMainRowFromButton($btn) {
        var $tr = $btn.closest('tr');
        if ($tr.hasClass('child')) {
            $tr = $tr.prev();
        }
        return machineTable.row($tr).data() || null;
    }

    function startEditMachineEntry(rowData) {
        if (!rowData) return;

        var currentType = rowData.plan_type || '';
        var canonicalType = findPlanTypeCaseInsensitive(currentType);
        if (canonicalType) {
            $('#entryPlanTypeMachine').val(canonicalType);
        } else if (currentType) {
            $('#entryPlanTypeMachine').val(currentType);
        }

        machineEntryMode = 'edit';
        editingMachineId = String(rowData.id || rowData.facode || '').trim();
        $('#btnSaveMachineEntry').html('<i class="fas fa-save mr-1"></i> Update');

        $('#entryFamasterid').val('');
        $('#entryMachineCode').val(rowData.facode || rowData.id || '');
        $('#entryMachineName').val(rowData.faname || '');
        $('#entryMachineDesc').val(rowData.faalias || '');
        $('#entryMachineName').prop('readonly', false);
        $('#entryMachineDesc').prop('readonly', false);
    }

    function clearEntryBreakFields() {
        breakEntryMode = 'add';
        editingBreakId = '';
        $('#btnSaveBreakEntry').html('<i class="fas fa-check mr-1"></i> Simpan');
        $('#entryBreakId').val('');
        $('#entryBreakTimeName').val('');
        $('#entryBreakTimeMinutes').val('');
    }

    function getBreakMainRowFromButton($btn) {
        var $tr = $btn.closest('tr');
        if ($tr.hasClass('child')) {
            $tr = $tr.prev();
        }
        return breakTable.row($tr).data() || null;
    }

    function startEditBreakEntry(rowData) {
        if (!rowData) return;

        breakEntryMode = 'edit';
        editingBreakId = String(rowData.id || '').trim();
        $('#btnSaveBreakEntry').html('<i class="fas fa-save mr-1"></i> Update');
        $('#entryBreakId').val(editingBreakId);
        $('#entryBreakTimeName').val(rowData.break_time_name || '');
        $('#entryBreakTimeMinutes').val(rowData.break_time_minutes != null ? rowData.break_time_minutes : '');
    }

    function clearEntryDownTimeFields() {
        downTimeEntryMode = 'add';
        editingDownTimeId = '';
        $('#btnSaveDownTimeEntry').html('<i class="fas fa-check mr-1"></i> Simpan');
        $('#entryDownTimeId').val('');
        $('#entryDownTimeName').val('');
    }

    function loadMaxCapacityMachineOptions(preferredMachineId) {
        var $machineSelect = $('#entryMachineCapacity');
        var planType = $('#entryPlanTypeCapacity').val() || '';

        $machineSelect.empty();
        if (!planType) {
            $machineSelect.append('<option value="">Pilih plan type terlebih dahulu</option>');
            $machineSelect.prop('disabled', true);
            return $.Deferred().resolve().promise();
        }

        $machineSelect.prop('disabled', true);
        $machineSelect.append('<option value="">Memuat machine...</option>');

        return $.getJSON(apiUrl, {
            action: 'machine_list',
            plan_type: planType
        }).done(function (res) {
            $machineSelect.empty();
            if (!res.success) {
                toast('error', res.message || 'Gagal memuat data machine.');
                $machineSelect.append('<option value="">Machine tidak tersedia</option>');
                $machineSelect.prop('disabled', true);
                return;
            }

            var rows = res.data || [];
            if (!rows.length) {
                $machineSelect.append('<option value="">Machine tidak tersedia</option>');
                $machineSelect.prop('disabled', true);
                return;
            }

            $machineSelect.append('<option value="">Pilih machine</option>');
            $.each(rows, function (_, row) {
                var code = String(row.facode || row.id || '').trim();
                if (!code) return;
                var name = String(row.faname || '').trim();
                var label = name ? (name + ' (' + code + ')') : code;
                $machineSelect.append('<option value="' + safeHtml(code) + '">' + safeHtml(label) + '</option>');
            });

            if (preferredMachineId) {
                $machineSelect.val(preferredMachineId);
            }
            $machineSelect.prop('disabled', false);
        }).fail(function () {
            $machineSelect.empty().append('<option value="">Machine tidak tersedia</option>');
            $machineSelect.prop('disabled', true);
            toast('error', 'Gagal memuat data machine.');
        });
    }

    function clearEntryMaxCapacityFields() {
        maxCapacityEntryMode = 'add';
        editingMaxCapacityId = '';
        $('#btnSaveMaxCapacityEntry').html('<i class="fas fa-check mr-1"></i> Simpan');
        $('#entryMaxCapacityId').val('');
        $('#entryMaxCapacityDay').val('');
        loadMaxCapacityMachineOptions();
    }

    function getMaxCapacityMainRowFromButton($btn) {
        var $tr = $btn.closest('tr');
        if ($tr.hasClass('child')) {
            $tr = $tr.prev();
        }
        return maxCapacityTable.row($tr).data() || null;
    }

    function startEditMaxCapacityEntry(rowData) {
        if (!rowData) return;

        var currentType = rowData.plan_type || '';
        var canonicalType = findPlanTypeCaseInsensitive(currentType);
        if (canonicalType) {
            $('#entryPlanTypeCapacity').val(canonicalType);
        } else if (currentType) {
            $('#entryPlanTypeCapacity').val(currentType);
        }

        maxCapacityEntryMode = 'edit';
        editingMaxCapacityId = String(rowData.id || '').trim();
        $('#btnSaveMaxCapacityEntry').html('<i class="fas fa-save mr-1"></i> Update');
        $('#entryMaxCapacityId').val(editingMaxCapacityId);
        $('#entryMaxCapacityDay').val(formatCapacityNumber(rowData.max_capacity_day || 0));

        loadMaxCapacityMachineOptions(String(rowData.machine_id || '').trim());
    }

    function getDownTimeMainRowFromButton($btn) {
        var $tr = $btn.closest('tr');
        if ($tr.hasClass('child')) {
            $tr = $tr.prev();
        }
        return downTimeTable.row($tr).data() || null;
    }

    function startEditDownTimeEntry(rowData) {
        if (!rowData) return;

        downTimeEntryMode = 'edit';
        editingDownTimeId = String(rowData.id || '').trim();
        $('#btnSaveDownTimeEntry').html('<i class="fas fa-save mr-1"></i> Update');
        $('#entryDownTimeId').val(editingDownTimeId);
        $('#entryDownTimeName').val(rowData.down_time_name || '');
    }

    function loadBreakMainData() {
        $('#checkAllBreakMain').prop('checked', false);
        $.getJSON(apiUrl, {
            action: 'break_list'
        }).done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal memuat data break time.');
                return;
            }
            breakTable.clear().rows.add(res.data || []).draw();
        }).fail(function () {
            toast('error', 'Gagal memuat data break time.');
        });
    }

    function loadDownTimeMainData() {
        $('#checkAllDownTimeMain').prop('checked', false);
        $.getJSON(apiUrl, {
            action: 'downtime_list'
        }).done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal memuat data down time.');
                return;
            }
            downTimeTable.clear().rows.add(res.data || []).draw();
        }).fail(function () {
            toast('error', 'Gagal memuat data down time.');
        });
    }

    function loadMaxCapacityMainData() {
        $('#checkAllMaxCapacityMain').prop('checked', false);
        $.getJSON(apiUrl, {
            action: 'max_capacity_list',
            plan_type: ''
        }).done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal memuat data max production capacity.');
                return;
            }
            maxCapacityTable.clear().rows.add(res.data || []).draw();
        }).fail(function () {
            toast('error', 'Gagal memuat data max production capacity.');
        });
    }

    function openSearchModal() {
        var types = getPlanTypes();
        if (!types.length) {
            toast('warning', 'Tambahkan Tipe Planning terlebih dahulu.');
            return;
        }

        clearSelectedRoutingIds();
        $('#searchKeyword').val('');
        $('#wrapFormPlanType').show();
        $('#searchRoutingModalLabel').text('Search Routing');
        $('#btnSubmitRouting').text('Submit');
        var preferred = $('#entryPlanType').val() || types[0];
        var canonical = findPlanTypeCaseInsensitive(preferred) || types[0];
        $('#formPlanType').val(canonical);

        loadSearchData();
        $('#searchRoutingModal').modal('show');
    }

    function getSelectedRoutingIds() {
        return Object.keys(selectedRoutingIds);
    }

    function clearSelectedRoutingIds() {
        selectedRoutingIds = {};
        syncRoutingSelectionUi();
    }

    function syncRoutingSelectionUi() {
        var selectedIds = getSelectedRoutingIds();
        var selectedMap = {};
        $.each(selectedIds, function (_, id) {
            selectedMap[String(id)] = true;
        });

        var pageNodes = searchTable.rows({ page: 'current' }).nodes();
        var pageCount = 0;
        var checkedCount = 0;

        $(pageNodes).find('.search-check').each(function () {
            var id = String($(this).val() || '').trim();
            var checked = !!selectedMap[id];
            $(this).prop('checked', checked);
            pageCount++;
            if (checked) {
                checkedCount++;
            }
        });

        $('#checkAllSearch').prop('checked', pageCount > 0 && checkedCount === pageCount);
        $('#routingSelectedInfo').text(selectedIds.length + ' routing dipilih');
    }

    function openSearchMachineModal() {
        var types = getPlanTypes();
        if (!types.length) {
            toast('warning', 'Tambahkan Tipe Planning terlebih dahulu.');
            return;
        }

        clearSelectedMachineIds();
        $('#searchMachineKeyword').val('');
        loadSearchMachineData();
        $('#searchMachineModal').modal('show');
    }

    function getSelectedMachineIds() {
        return Object.keys(selectedMachineIds);
    }

    function clearSelectedMachineIds() {
        selectedMachineIds = {};
        syncMachineSelectionUi();
    }

    function syncMachineSelectionUi() {
        var selectedIds = getSelectedMachineIds();
        var selectedMap = {};
        $.each(selectedIds, function (_, id) {
            selectedMap[String(id)] = true;
        });

        var pageNodes = searchMachineTable.rows({ page: 'current' }).nodes();
        var pageCount = 0;
        var checkedCount = 0;

        $(pageNodes).find('.machine-search-check').each(function () {
            var id = String($(this).val() || '').trim();
            var checked = !!selectedMap[id];
            $(this).prop('checked', checked);
            pageCount++;
            if (checked) {
                checkedCount++;
            }
        });

        $('#checkAllMachineSearch').prop('checked', pageCount > 0 && checkedCount === pageCount);
        $('#machineSelectedInfo').text(selectedIds.length + ' machine dipilih');
    }

    var mainTable = $('#mainRoutingTable').DataTable({
        data: [],
        processing: true,
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        order: [[1, 'asc'], [2, 'asc']],
        columns: [
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (data) {
                    return '<input type="checkbox" class="main-check" value="' + safeHtml(data) + '">';
                }
            },
            { data: 'plan_type', defaultContent: '-', className: 'text-center' },
            { data: 'rtgcode', defaultContent: '-', className: 'text-center' },
            { data: 'rtgname', defaultContent: '-', className: 'text-center' },
            {
                data: 'fgmachine',
                className: 'text-center',
                render: function (data) {
                    var val = (String(data || '').toUpperCase() === 'Y') ? 'Y' : 'N';
                    var cls = val === 'Y' ? 'badge badge-success' : 'badge badge-secondary';
                    return '<span class="' + cls + '">' + val + '</span>';
                }
            },
            {
                data: 'upddate',
                className: 'text-center',
                render: function (data) {
                    return formatDateTime(data);
                }
            },
            { data: 'upduser', defaultContent: '-', className: 'text-center' }
            ,
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center text-nowrap',
                render: function () {
                    return '' +
                        '<button type="button" class="btn btn-info btn-xs mr-1 btnViewRouting" title="View">' +
                            '<i class="fas fa-eye"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-warning btn-xs mr-1 btnEditRouting" title="Edit">' +
                            '<i class="fas fa-edit"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-danger btn-xs btnDeleteRouting" title="Delete">' +
                            '<i class="fas fa-trash"></i>' +
                        '</button>';
                }
            }
        ],
        language: {
            processing: 'Memuat data...',
            emptyTable: 'Belum ada data routing.',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
            paginate: {
                first: 'Awal',
                last: 'Akhir',
                next: '>',
                previous: '<'
            }
        }
    });

    var searchTable = $('#searchRoutingTable').DataTable({
        data: [],
        processing: true,
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        order: [[1, 'asc']],
        columns: [
            {
                data: 'rtgmsid',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (data) {
                    return '<input type="checkbox" class="search-check" value="' + safeHtml(data) + '">';
                }
            },
            { data: 'rtgcode', defaultContent: '-', className: 'text-center' },
            { data: 'rtgname', defaultContent: '-', className: 'text-center' },
            {
                data: 'fgmachine',
                className: 'text-center',
                render: function (data) {
                    var val = (String(data || '').toUpperCase() === 'Y') ? 'Y' : 'N';
                    var cls = val === 'Y' ? 'badge badge-success' : 'badge badge-secondary';
                    return '<span class="' + cls + '">' + val + '</span>';
                }
            }
        ],
        language: {
            processing: 'Memuat data...',
            emptyTable: 'Data routing tidak ditemukan.',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data'
        }
    });

    var machineTable = $('#mainMachineTable').DataTable({
        data: [],
        processing: true,
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        order: [[1, 'asc'], [2, 'asc']],
        columns: [
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (data) {
                    return '<input type="checkbox" class="machine-main-check" value="' + safeHtml(data) + '">';
                }
            },
            { data: 'plan_type', defaultContent: '-', className: 'text-center' },
            { data: 'facode', defaultContent: '-', className: 'text-center' },
            { data: 'faname', defaultContent: '-', className: 'text-center' },
            { data: 'faalias', defaultContent: '-', className: 'text-center' },
            {
                data: 'upddate',
                className: 'text-center',
                render: function (data) {
                    return formatDateTime(data);
                }
            },
            { data: 'upduser', defaultContent: '-', className: 'text-center' },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center text-nowrap',
                render: function () {
                    return '' +
                        '<button type="button" class="btn btn-info btn-xs mr-1 btnViewMachine" title="View">' +
                            '<i class="fas fa-eye"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-warning btn-xs mr-1 btnEditMachine" title="Edit">' +
                            '<i class="fas fa-edit"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-danger btn-xs btnDeleteMachine" title="Delete">' +
                            '<i class="fas fa-trash"></i>' +
                        '</button>';
                }
            }
        ],
        language: {
            processing: 'Memuat data...',
            emptyTable: 'Belum ada data machine.',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
            paginate: {
                first: 'Awal',
                last: 'Akhir',
                next: '>',
                previous: '<'
            }
        }
    });

    var searchMachineTable = $('#searchMachineTable').DataTable({
        data: [],
        processing: true,
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        order: [[1, 'asc']],
        columns: [
            {
                data: 'famasterid',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (data) {
                    return '<input type="checkbox" class="machine-search-check" value="' + safeHtml(data) + '">';
                }
            },
            { data: 'facode', defaultContent: '-' },
            { data: 'faname', defaultContent: '-' },
            { data: 'faalias', defaultContent: '-' }
        ],
        language: {
            processing: 'Memuat data...',
            emptyTable: 'Data machine tidak ditemukan.',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data'
        }
    });

    var breakTable = $('#mainBreakTable').DataTable({
        data: [],
        processing: true,
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        order: [[1, 'asc']],
        columns: [
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (data) {
                    return '<input type="checkbox" class="break-main-check" value="' + safeHtml(data) + '">';
                }
            },
            { data: 'break_time_name', defaultContent: '-', className: 'text-center' },
            { data: 'break_time_minutes', defaultContent: 0, className: 'text-center' },
            {
                data: 'upddate',
                className: 'text-center',
                render: function (data) {
                    return formatDateTime(data);
                }
            },
            { data: 'upduser', defaultContent: '-', className: 'text-center' },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center text-nowrap',
                render: function () {
                    return '' +
                        '<button type="button" class="btn btn-info btn-xs mr-1 btnViewBreak" title="View">' +
                            '<i class="fas fa-eye"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-warning btn-xs mr-1 btnEditBreak" title="Edit">' +
                            '<i class="fas fa-edit"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-danger btn-xs btnDeleteBreak" title="Delete">' +
                            '<i class="fas fa-trash"></i>' +
                        '</button>';
                }
            }
        ],
        language: {
            processing: 'Memuat data...',
            emptyTable: 'Belum ada data break time.',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
            paginate: {
                first: 'Awal',
                last: 'Akhir',
                next: '>',
                previous: '<'
            }
        }
    });

    var downTimeTable = $('#mainDownTimeTable').DataTable({
        data: [],
        processing: true,
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        order: [[1, 'asc']],
        columns: [
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (data) {
                    return '<input type="checkbox" class="downtime-main-check" value="' + safeHtml(data) + '">';
                }
            },
            { data: 'down_time_name', defaultContent: '-', className: 'text-center' },
            {
                data: 'upddate',
                className: 'text-center',
                render: function (data) {
                    return formatDateTime(data);
                }
            },
            { data: 'upduser', defaultContent: '-', className: 'text-center' },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center text-nowrap',
                render: function () {
                    return '' +
                        '<button type="button" class="btn btn-info btn-xs mr-1 btnViewDownTime" title="View">' +
                            '<i class="fas fa-eye"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-warning btn-xs mr-1 btnEditDownTime" title="Edit">' +
                            '<i class="fas fa-edit"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-danger btn-xs btnDeleteDownTime" title="Delete">' +
                            '<i class="fas fa-trash"></i>' +
                        '</button>';
                }
            }
        ],
        language: {
            processing: 'Memuat data...',
            emptyTable: 'Belum ada data down time.',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
            paginate: {
                first: 'Awal',
                last: 'Akhir',
                next: '>',
                previous: '<'
            }
        }
    });

    var maxCapacityTable = $('#mainMaxCapacityTable').DataTable({
        data: [],
        processing: true,
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        order: [[1, 'asc'], [2, 'asc']],
        columns: [
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (data) {
                    return '<input type="checkbox" class="max-capacity-main-check" value="' + safeHtml(data) + '">';
                }
            },
            { data: 'plan_type', defaultContent: '-', className: 'text-center' },
            { data: 'machine_label', defaultContent: '-', className: 'text-center' },
            {
                data: 'max_capacity_day',
                className: 'text-center',
                render: function (data) {
                    return formatCapacityNumber(data);
                }
            },
            {
                data: 'upddate',
                className: 'text-center',
                render: function (data) {
                    return formatDateTime(data);
                }
            },
            { data: 'upduser', defaultContent: '-', className: 'text-center' },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center text-nowrap',
                render: function () {
                    return '' +
                        '<button type="button" class="btn btn-info btn-xs mr-1 btnViewMaxCapacity" title="View">' +
                            '<i class="fas fa-eye"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-warning btn-xs mr-1 btnEditMaxCapacity" title="Edit">' +
                            '<i class="fas fa-edit"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-danger btn-xs btnDeleteMaxCapacity" title="Delete">' +
                            '<i class="fas fa-trash"></i>' +
                        '</button>';
                }
            }
        ],
        language: {
            processing: 'Memuat data...',
            emptyTable: 'Belum ada data max production capacity.',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
            paginate: {
                first: 'Awal',
                last: 'Akhir',
                next: '>',
                previous: '<'
            }
        }
    });

    function loadMainData() {
        $('#checkAllMain').prop('checked', false);
        $.getJSON(apiUrl, {
            action: 'list',
            plan_type: ''
        }).done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal memuat data routing.');
                return;
            }
            mainTable.clear().rows.add(res.data || []).draw();
        }).fail(function () {
            toast('error', 'Gagal memuat data routing.');
        });
    }

    function loadSearchData() {
        clearSelectedRoutingIds();
        $.getJSON(apiUrl, {
            action: 'search',
            search_by: $('#searchBy').val(),
            keyword: $('#searchKeyword').val().trim()
        }).done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal mencari routing.');
                return;
            }
            searchTable.clear().rows.add(res.data || []).draw();
        }).fail(function () {
            toast('error', 'Gagal mengambil data routing.');
        });
    }

    function loadMachineMainData() {
        $('#checkAllMachineMain').prop('checked', false);
        $.getJSON(apiUrl, {
            action: 'machine_list',
            plan_type: ''
        }).done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal memuat data machine.');
                return;
            }
            machineTable.clear().rows.add(res.data || []).draw();
        }).fail(function () {
            toast('error', 'Gagal memuat data machine.');
        });
    }

    function loadSearchMachineData() {
        clearSelectedMachineIds();
        $.getJSON(apiUrl, {
            action: 'machine_search',
            search_by: $('#searchMachineBy').val(),
            keyword: $('#searchMachineKeyword').val().trim()
        }).done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal mencari machine.');
                return;
            }
            searchMachineTable.clear().rows.add(res.data || []).draw();
        }).fail(function () {
            toast('error', 'Gagal mengambil data machine.');
        });
    }

    function loadBreakMainData() {
        $('#checkAllBreakMain').prop('checked', false);
        $.getJSON(apiUrl, {
            action: 'break_list'
        }).done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal memuat data break time.');
                return;
            }
            breakTable.clear().rows.add(res.data || []).draw();
        }).fail(function () {
            toast('error', 'Gagal memuat data break time.');
        });
    }

    $('#masterTabs .nav-link').on('click', function () {
        if ($(this).hasClass('disabled')) return;

        $('#masterTabs .nav-link').removeClass('active');
        $(this).addClass('active');

        $('.tab-pane-section').removeClass('active');
        var target = $(this).data('tab-target');
        $('#' + target).addClass('active');
    });

    $('#btnLookupRouting, #entryRoutingCode').on('click', function () {
        openSearchModal();
    });
    $('#btnLookupMachine, #entryMachineCode').on('click', function () {
        openSearchMachineModal();
    });
    $('#entryPlanTypeCapacity').on('change', function () {
        loadMaxCapacityMachineOptions();
    });
    $('#entryMaxCapacityDay').on('blur', function () {
        var parsed = parseCapacityInput($(this).val());
        if (!Number.isFinite(parsed) || parsed <= 0) {
            $(this).val('');
            return;
        }
        $(this).val(formatCapacityNumber(parsed));
    });

    $('#btnSearchRouting').on('click', loadSearchData);
    $('#searchKeyword').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadSearchData();
        }
    });
    $('#btnSearchMachine').on('click', loadSearchMachineData);
    $('#searchMachineKeyword').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadSearchMachineData();
        }
    });

    $('#searchRoutingTable').on('change', '.search-check', function () {
        var id = String($(this).val() || '').trim();
        if (!id) return;

        if ($(this).is(':checked')) {
            selectedRoutingIds[id] = true;
        } else {
            delete selectedRoutingIds[id];
        }
        syncRoutingSelectionUi();
    });

    $('#searchRoutingTable tbody').on('click', 'tr', function (e) {
        if ($(e.target).is('input,button,a,label')) {
            return;
        }
        var $cb = $(this).find('.search-check');
        if (!$cb.length) return;
        var id = String($cb.val() || '').trim();
        if (!id) return;

        if (selectedRoutingIds[id]) {
            delete selectedRoutingIds[id];
        } else {
            selectedRoutingIds[id] = true;
        }
        syncRoutingSelectionUi();
    });
    $('#searchMachineTable').on('change', '.machine-search-check', function () {
        var id = String($(this).val() || '').trim();
        if (!id) return;

        if ($(this).is(':checked')) {
            selectedMachineIds[id] = true;
        } else {
            delete selectedMachineIds[id];
        }
        syncMachineSelectionUi();
    });
    $('#searchMachineTable tbody').on('click', 'tr', function (e) {
        if ($(e.target).is('input,button,a,label')) {
            return;
        }
        var $cb = $(this).find('.machine-search-check');
        if (!$cb.length) return;
        var id = String($cb.val() || '').trim();
        if (!id) return;

        if (selectedMachineIds[id]) {
            delete selectedMachineIds[id];
        } else {
            selectedMachineIds[id] = true;
        }
        syncMachineSelectionUi();
    });

    $('#checkAllMain').on('change', function () {
        $('.main-check').prop('checked', $(this).is(':checked'));
    });
    $('#checkAllMachineMain').on('change', function () {
        $('.machine-main-check').prop('checked', $(this).is(':checked'));
    });
    $('#checkAllBreakMain').on('change', function () {
        $('.break-main-check').prop('checked', $(this).is(':checked'));
    });

    $('#checkAllDownTimeMain').on('change', function () {
        $('.downtime-main-check').prop('checked', $(this).is(':checked'));
    });
    $('#checkAllMaxCapacityMain').on('change', function () {
        $('.max-capacity-main-check').prop('checked', $(this).is(':checked'));
    });

    $('#mainRoutingTable').on('draw.dt', function () {
        $('#checkAllMain').prop('checked', false);
    });
    $('#mainMachineTable').on('draw.dt', function () {
        $('#checkAllMachineMain').prop('checked', false);
    });
    $('#mainBreakTable').on('draw.dt', function () {
        $('#checkAllBreakMain').prop('checked', false);
    });
    $('#mainDownTimeTable').on('draw.dt', function () {
        $('#checkAllDownTimeMain').prop('checked', false);
    });
    $('#mainMaxCapacityTable').on('draw.dt', function () {
        $('#checkAllMaxCapacityMain').prop('checked', false);
    });

    $('#checkAllSearch').on('change', function () {
        var shouldCheck = $(this).is(':checked');
        var pageNodes = searchTable.rows({ page: 'current' }).nodes();
        $(pageNodes).find('.search-check').each(function () {
            var id = String($(this).val() || '').trim();
            if (!id) return;
            if (shouldCheck) {
                selectedRoutingIds[id] = true;
            } else {
                delete selectedRoutingIds[id];
            }
        });
        syncRoutingSelectionUi();
    });

    $('#searchRoutingTable').on('draw.dt', function () {
        syncRoutingSelectionUi();
    });
    $('#searchRoutingModal').on('hidden.bs.modal', function () {
        clearSelectedRoutingIds();
    });
    $('#checkAllMachineSearch').on('change', function () {
        var shouldCheck = $(this).is(':checked');
        var pageNodes = searchMachineTable.rows({ page: 'current' }).nodes();
        $(pageNodes).find('.machine-search-check').each(function () {
            var id = String($(this).val() || '').trim();
            if (!id) return;
            if (shouldCheck) {
                selectedMachineIds[id] = true;
            } else {
                delete selectedMachineIds[id];
            }
        });
        syncMachineSelectionUi();
    });
    $('#searchMachineTable').on('draw.dt', function () {
        syncMachineSelectionUi();
    });
    $('#searchMachineModal').on('hidden.bs.modal', function () {
        clearSelectedMachineIds();
    });

    $('#btnSubmitRouting').on('click', function () {
        var selectedIds = getSelectedRoutingIds();

        if (!selectedIds.length) {
            toast('warning', 'Pilih routing terlebih dahulu.');
            return;
        }

        var planType = $('#formPlanType').val();
        if (!planType) {
            toast('warning', 'Pilih Plan Type terlebih dahulu.');
            return;
        }

        $.post(apiUrl, {
            action: 'add',
            plan_type: planType,
            rtgmsids: selectedIds
        }, null, 'json').done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal menambahkan routing.');
                return;
            }
            $('#searchRoutingModal').modal('hide');
            loadMainData();
            loadPlanTypes(planType);
            toast('success', res.message || 'Routing berhasil ditambahkan.');
        }).fail(function () {
            toast('error', 'Gagal menambahkan routing.');
        });
    });

    $('#btnSubmitMachine').on('click', function () {
        var selectedIds = getSelectedMachineIds();

        if (!selectedIds.length) {
            toast('warning', 'Pilih machine terlebih dahulu.');
            return;
        }

        var planType = $('#entryPlanTypeMachine').val();
        if (!planType) {
            toast('warning', 'Pilih Plan Type terlebih dahulu.');
            return;
        }

        $.post(apiUrl, {
            action: 'machine_add',
            plan_type: planType,
            famasterids: selectedIds
        }, null, 'json').done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal menambahkan machine.');
                return;
            }
            $('#searchMachineModal').modal('hide');
            loadMachineMainData();
            loadPlanTypes(planType);
            clearEntryMachineFields();
            toast('success', res.message || 'Machine berhasil ditambahkan.');
        }).fail(function () {
            toast('error', 'Gagal menambahkan machine.');
        });
    });

    $('#btnSaveEntry').on('click', function () {
        var planType = $('#entryPlanType').val();
        var rtgmsid = $('#entryRtgmsid').val();
        var routingId = String(editingRoutingId || $('#entryRoutingCode').val() || '').trim();
        var routingName = $('#entryRoutingName').val().trim();
        var useMechine = $('#entryUseMachine').is(':checked') ? 'Y' : 'N';

        if (!planType) {
            toast('warning', 'Pilih Plan Type terlebih dahulu.');
            return;
        }

        if (entryMode === 'edit') {
            if (!routingId) {
                toast('warning', 'Routing ID tidak valid untuk update.');
                return;
            }

            $.post(apiUrl, {
                action: 'update',
                routing_id: routingId,
                plan_type: planType,
                routing_name: routingName,
                use_mechine: useMechine
            }, null, 'json').done(function (res) {
                if (!res.success) {
                    toast('error', res.message || 'Gagal update routing.');
                    return;
                }
                loadMainData();
                loadPlanTypes(planType);
                clearEntryRoutingFields();
                toast('success', res.message || 'Routing berhasil diupdate.');
            }).fail(function () {
                toast('error', 'Gagal update routing.');
            });
            return;
        }

        if (!rtgmsid) {
            toast('warning', 'Pilih Routing Code dari lookup terlebih dahulu.');
            return;
        }

        $.post(apiUrl, {
            action: 'add',
            plan_type: planType,
            rtgmsids: [rtgmsid]
        }, null, 'json').done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal menyimpan routing.');
                return;
            }
            loadMainData();
            loadPlanTypes(planType);
            clearEntryRoutingFields();
            toast('success', res.message || 'Routing berhasil disimpan.');
        }).fail(function () {
            toast('error', 'Gagal menyimpan routing.');
        });
    });

    $('#btnClearEntry').on('click', function () {
        clearEntryRoutingFields();
    });

    $('#btnSaveMachineEntry').on('click', function () {
        var planType = $('#entryPlanTypeMachine').val();
        var famasterid = $('#entryFamasterid').val();
        var machineId = String(editingMachineId || $('#entryMachineCode').val() || '').trim();
        var machineName = $('#entryMachineName').val().trim();
        var machineDesc = $('#entryMachineDesc').val().trim();

        if (!planType) {
            toast('warning', 'Pilih Plan Type terlebih dahulu.');
            return;
        }

        if (machineEntryMode === 'edit') {
            if (!machineId) {
                toast('warning', 'Machine ID tidak valid untuk update.');
                return;
            }

            $.post(apiUrl, {
                action: 'machine_update',
                machine_id: machineId,
                plan_type: planType,
                machine_name: machineName,
                machine_desc: machineDesc
            }, null, 'json').done(function (res) {
                if (!res.success) {
                    toast('error', res.message || 'Gagal update machine.');
                    return;
                }
                loadMachineMainData();
                loadPlanTypes(planType);
                clearEntryMachineFields();
                toast('success', res.message || 'Machine berhasil diupdate.');
            }).fail(function () {
                toast('error', 'Gagal update machine.');
            });
            return;
        }

        if (!famasterid) {
            toast('warning', 'Pilih Machine Code dari lookup terlebih dahulu.');
            return;
        }

        $.post(apiUrl, {
            action: 'machine_add',
            plan_type: planType,
            famasterids: [famasterid]
        }, null, 'json').done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal menyimpan machine.');
                return;
            }
            loadMachineMainData();
            loadPlanTypes(planType);
            clearEntryMachineFields();
            toast('success', res.message || 'Machine berhasil disimpan.');
        }).fail(function () {
            toast('error', 'Gagal menyimpan machine.');
        });
    });

    $('#btnClearMachineEntry').on('click', function () {
        clearEntryMachineFields();
    });

    $('#btnSaveBreakEntry').on('click', function () {
        var breakId = String(editingBreakId || $('#entryBreakId').val() || '').trim();
        var breakTimeName = $('#entryBreakTimeName').val().trim();
        var breakTimeMinutesRaw = String($('#entryBreakTimeMinutes').val() || '').trim();
        var breakTimeMinutes = breakTimeMinutesRaw === '' ? 0 : parseInt(breakTimeMinutesRaw, 10);

        if (!breakTimeName) {
            toast('warning', 'Isi Break Time terlebih dahulu.');
            return;
        }
        if (!Number.isFinite(breakTimeMinutes) || breakTimeMinutes < 0) {
            toast('warning', 'Waktu (Menit) harus angka 0 atau lebih.');
            return;
        }

        if (breakEntryMode === 'edit') {
            if (!breakId) {
                toast('warning', 'Break ID tidak valid untuk update.');
                return;
            }

            $.post(apiUrl, {
                action: 'break_update',
                break_id: breakId,
                break_time_name: breakTimeName,
                break_time_minutes: breakTimeMinutes
            }, null, 'json').done(function (res) {
                if (!res.success) {
                    toast('error', res.message || 'Gagal update break time.');
                    return;
                }
                loadBreakMainData();
                clearEntryBreakFields();
                toast('success', res.message || 'Break time berhasil diupdate.');
            }).fail(function () {
                toast('error', 'Gagal update break time.');
            });
            return;
        }

        $.post(apiUrl, {
            action: 'break_add',
            break_time_name: breakTimeName,
            break_time_minutes: breakTimeMinutes
        }, null, 'json').done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal menyimpan break time.');
                return;
            }
            loadBreakMainData();
            clearEntryBreakFields();
            toast('success', res.message || 'Break time berhasil disimpan.');
        }).fail(function () {
            toast('error', 'Gagal menyimpan break time.');
        });
    });

    $('#btnClearBreakEntry').on('click', function () {
        clearEntryBreakFields();
    });

    $('#btnSaveDownTimeEntry').on('click', function () {
        var downTimeId = String(editingDownTimeId || $('#entryDownTimeId').val() || '').trim();
        var downTimeName = $('#entryDownTimeName').val().trim();

        if (!downTimeName) {
            toast('warning', 'Isi Down Time terlebih dahulu.');
            return;
        }

        if (downTimeEntryMode === 'edit') {
            if (!downTimeId) {
                toast('warning', 'Down Time ID tidak valid untuk update.');
                return;
            }

            $.post(apiUrl, {
                action: 'downtime_update',
                downtime_id: downTimeId,
                down_time_name: downTimeName
            }, null, 'json').done(function (res) {
                if (!res.success) {
                    toast('error', res.message || 'Gagal update down time.');
                    return;
                }
                loadDownTimeMainData();
                clearEntryDownTimeFields();
                toast('success', res.message || 'Down time berhasil diupdate.');
            }).fail(function () {
                toast('error', 'Gagal update down time.');
            });
            return;
        }

        $.post(apiUrl, {
            action: 'downtime_add',
            down_time_name: downTimeName
        }, null, 'json').done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal menyimpan down time.');
                return;
            }
            loadDownTimeMainData();
            clearEntryDownTimeFields();
            toast('success', res.message || 'Down time berhasil disimpan.');
        }).fail(function () {
            toast('error', 'Gagal menyimpan down time.');
        });
    });

    $('#btnClearDownTimeEntry').on('click', function () {
        clearEntryDownTimeFields();
    });

    $('#btnSaveMaxCapacityEntry').on('click', function () {
        var capacityId = String(editingMaxCapacityId || $('#entryMaxCapacityId').val() || '').trim();
        var planType = $('#entryPlanTypeCapacity').val();
        var machineId = $('#entryMachineCapacity').val();
        var maxCapacity = parseCapacityInput($('#entryMaxCapacityDay').val());

        if (!planType) {
            toast('warning', 'Pilih Plan Type terlebih dahulu.');
            return;
        }
        if (!machineId) {
            toast('warning', 'Pilih Machine terlebih dahulu.');
            return;
        }
        if (!Number.isFinite(maxCapacity) || maxCapacity <= 0) {
            toast('warning', 'Isi Max Capacity/Day dengan angka yang valid.');
            return;
        }

        if (maxCapacityEntryMode === 'edit') {
            if (!capacityId) {
                toast('warning', 'ID max capacity tidak valid untuk update.');
                return;
            }

            $.post(apiUrl, {
                action: 'max_capacity_update',
                capacity_id: capacityId,
                plan_type: planType,
                machine_id: machineId,
                max_capacity_day: maxCapacity
            }, null, 'json').done(function (res) {
                if (!res.success) {
                    toast('error', res.message || 'Gagal update max production capacity.');
                    return;
                }
                loadMaxCapacityMainData();
                clearEntryMaxCapacityFields();
                toast('success', res.message || 'Max production capacity berhasil diupdate.');
            }).fail(function () {
                toast('error', 'Gagal update max production capacity.');
            });
            return;
        }

        $.post(apiUrl, {
            action: 'max_capacity_add',
            plan_type: planType,
            machine_id: machineId,
            max_capacity_day: maxCapacity
        }, null, 'json').done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal menyimpan max production capacity.');
                return;
            }
            loadMaxCapacityMainData();
            clearEntryMaxCapacityFields();
            toast('success', res.message || 'Max production capacity berhasil disimpan.');
        }).fail(function () {
            toast('error', 'Gagal menyimpan max production capacity.');
        });
    });

    $('#btnClearMaxCapacityEntry').on('click', function () {
        clearEntryMaxCapacityFields();
    });

    $('#mainRoutingTable tbody').on('click', '.btnViewRouting', function () {
        var row = getMainRowFromButton($(this));
        if (!row) return;

        Swal.fire({
            title: 'Detail Routing',
            icon: 'info',
            html:
                '<div class="text-left">' +
                    '<div><b>Plan Type:</b> ' + safeHtml(row.plan_type || '-') + '</div>' +
                    '<div><b>Routing Code:</b> ' + safeHtml(row.rtgcode || '-') + '</div>' +
                    '<div><b>Routing Name:</b> ' + safeHtml(row.rtgname || '-') + '</div>' +
                    '<div><b>Use Machine:</b> ' + safeHtml(row.fgmachine || 'N') + '</div>' +
                    '<div><b>Last Update:</b> ' + safeHtml(formatDateTime(row.upddate)) + '</div>' +
                    '<div><b>Updated By:</b> ' + safeHtml(row.upduser || '-') + '</div>' +
                '</div>'
        });
    });

    $('#mainRoutingTable tbody').on('click', '.btnEditRouting', function () {
        var row = getMainRowFromButton($(this));
        if (!row) return;

        startEditEntry(row);
        $('html, body').animate({ scrollTop: $('.entry-panel').offset().top - 90 }, 250);
    });

    $('#mainRoutingTable tbody').on('click', '.btnDeleteRouting', function () {
        var row = getMainRowFromButton($(this));
        if (!row) return;

        var routingId = String(row.id || row.rtgcode || '').trim();
        if (!routingId) {
            toast('warning', 'Routing ID tidak valid.');
            return;
        }

        Swal.fire({
            title: 'Hapus routing?',
            text: 'Routing "' + routingId + '" akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post(apiUrl, {
                action: 'delete',
                ids: [routingId]
            }, null, 'json').done(function (res) {
                if (!res.success) {
                    toast('error', res.message || 'Gagal menghapus routing.');
                    return;
                }
                loadMainData();
                loadPlanTypes($('#entryPlanType').val());
                clearEntryRoutingFields();
                toast('success', res.message || 'Routing berhasil dihapus.');
            }).fail(function (xhr) {
                var msg = 'Gagal menghapus routing.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                toast('error', msg);
            });
        });
    });

    $('#mainMachineTable tbody').on('click', '.btnViewMachine', function () {
        var row = getMachineMainRowFromButton($(this));
        if (!row) return;

        Swal.fire({
            title: 'Detail Machine',
            icon: 'info',
            html:
                '<div class="text-left">' +
                    '<div><b>Plan Type:</b> ' + safeHtml(row.plan_type || '-') + '</div>' +
                    '<div><b>Machine Code:</b> ' + safeHtml(row.facode || '-') + '</div>' +
                    '<div><b>Machine Name:</b> ' + safeHtml(row.faname || '-') + '</div>' +
                    '<div><b>Description:</b> ' + safeHtml(row.faalias || '-') + '</div>' +
                    '<div><b>Last Update:</b> ' + safeHtml(formatDateTime(row.upddate)) + '</div>' +
                    '<div><b>Updated By:</b> ' + safeHtml(row.upduser || '-') + '</div>' +
                '</div>'
        });
    });

    $('#mainMachineTable tbody').on('click', '.btnEditMachine', function () {
        var row = getMachineMainRowFromButton($(this));
        if (!row) return;

        startEditMachineEntry(row);
        $('#masterTabs .nav-link').removeClass('active');
        $('#masterTabs .nav-link[data-tab-target="tabMachine"]').addClass('active');
        $('.tab-pane-section').removeClass('active');
        $('#tabMachine').addClass('active');
        $('html, body').animate({ scrollTop: $('#tabMachine .entry-panel').offset().top - 90 }, 250);
    });

    $('#mainMachineTable tbody').on('click', '.btnDeleteMachine', function () {
        var row = getMachineMainRowFromButton($(this));
        if (!row) return;

        var machineId = String(row.id || row.facode || '').trim();
        if (!machineId) {
            toast('warning', 'Machine ID tidak valid.');
            return;
        }

        Swal.fire({
            title: 'Hapus machine?',
            text: 'Machine "' + machineId + '" akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post(apiUrl, {
                action: 'machine_delete',
                ids: [machineId]
            }, null, 'json').done(function (res) {
                if (!res.success) {
                    toast('error', res.message || 'Gagal menghapus machine.');
                    return;
                }
                loadMachineMainData();
                loadPlanTypes($('#entryPlanTypeMachine').val());
                clearEntryMachineFields();
                toast('success', res.message || 'Machine berhasil dihapus.');
            }).fail(function () {
                toast('error', 'Gagal menghapus machine.');
            });
        });
    });

    $('#mainBreakTable tbody').on('click', '.btnViewBreak', function () {
        var row = getBreakMainRowFromButton($(this));
        if (!row) return;

        Swal.fire({
            title: 'Detail Break Time',
            icon: 'info',
            html:
                '<div class="text-left">' +
                    '<div><b>Break Time:</b> ' + safeHtml(row.break_time_name || '-') + '</div>' +
                    '<div><b>Waktu (Menit):</b> ' + safeHtml(row.break_time_minutes != null ? row.break_time_minutes : 0) + '</div>' +
                    '<div><b>Last Update:</b> ' + safeHtml(formatDateTime(row.upddate)) + '</div>' +
                    '<div><b>Updated By:</b> ' + safeHtml(row.upduser || '-') + '</div>' +
                '</div>'
        });
    });

    $('#mainBreakTable tbody').on('click', '.btnEditBreak', function () {
        var row = getBreakMainRowFromButton($(this));
        if (!row) return;

        startEditBreakEntry(row);
        $('#masterTabs .nav-link').removeClass('active');
        $('#masterTabs .nav-link[data-tab-target="tabBreakTime"]').addClass('active');
        $('.tab-pane-section').removeClass('active');
        $('#tabBreakTime').addClass('active');
        $('html, body').animate({ scrollTop: $('#tabBreakTime .entry-panel').offset().top - 90 }, 250);
    });

    $('#mainBreakTable tbody').on('click', '.btnDeleteBreak', function () {
        var row = getBreakMainRowFromButton($(this));
        if (!row) return;

        var breakId = String(row.id || '').trim();
        if (!breakId) {
            toast('warning', 'Break ID tidak valid.');
            return;
        }

        Swal.fire({
            title: 'Hapus break time?',
            text: 'Data break time ini akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post(apiUrl, {
                action: 'break_delete',
                ids: [breakId]
            }, null, 'json').done(function (res) {
                if (!res.success) {
                    toast('error', res.message || 'Gagal menghapus break time.');
                    return;
                }
                loadBreakMainData();
                clearEntryBreakFields();
                toast('success', res.message || 'Break time berhasil dihapus.');
            }).fail(function () {
                toast('error', 'Gagal menghapus break time.');
            });
        });
    });

    $('#mainDownTimeTable tbody').on('click', '.btnViewDownTime', function () {
        var row = getDownTimeMainRowFromButton($(this));
        if (!row) return;

        Swal.fire({
            title: 'Detail Down Time',
            icon: 'info',
            html:
                '<div class="text-left">' +
                    '<div><b>Down Time:</b> ' + safeHtml(row.down_time_name || '-') + '</div>' +
                    '<div><b>Last Update:</b> ' + safeHtml(formatDateTime(row.upddate)) + '</div>' +
                    '<div><b>Updated By:</b> ' + safeHtml(row.upduser || '-') + '</div>' +
                '</div>'
        });
    });

    $('#mainDownTimeTable tbody').on('click', '.btnEditDownTime', function () {
        var row = getDownTimeMainRowFromButton($(this));
        if (!row) return;

        startEditDownTimeEntry(row);
        $('#masterTabs .nav-link').removeClass('active');
        $('#masterTabs .nav-link[data-tab-target="tabDownTime"]').addClass('active');
        $('.tab-pane-section').removeClass('active');
        $('#tabDownTime').addClass('active');
        $('html, body').animate({ scrollTop: $('#tabDownTime .entry-panel').offset().top - 90 }, 250);
    });

    $('#mainDownTimeTable tbody').on('click', '.btnDeleteDownTime', function () {
        var row = getDownTimeMainRowFromButton($(this));
        if (!row) return;

        var downTimeId = String(row.id || '').trim();
        if (!downTimeId) {
            toast('warning', 'Down Time ID tidak valid.');
            return;
        }

        Swal.fire({
            title: 'Hapus down time?',
            text: 'Data down time ini akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post(apiUrl, {
                action: 'downtime_delete',
                ids: [downTimeId]
            }, null, 'json').done(function (res) {
                if (!res.success) {
                    toast('error', res.message || 'Gagal menghapus down time.');
                    return;
                }
                loadDownTimeMainData();
                clearEntryDownTimeFields();
                toast('success', res.message || 'Down time berhasil dihapus.');
            }).fail(function () {
                toast('error', 'Gagal menghapus down time.');
            });
        });
    });

    $('#mainMaxCapacityTable tbody').on('click', '.btnViewMaxCapacity', function () {
        var row = getMaxCapacityMainRowFromButton($(this));
        if (!row) return;

        Swal.fire({
            title: 'Detail Max Production Capacity',
            icon: 'info',
            html:
                '<div class="text-left">' +
                    '<div><b>Plan Type:</b> ' + safeHtml(row.plan_type || '-') + '</div>' +
                    '<div><b>Machine:</b> ' + safeHtml(row.machine_label || '-') + '</div>' +
                    '<div><b>Max Capacity/Day:</b> ' + safeHtml(formatCapacityNumber(row.max_capacity_day)) + '</div>' +
                    '<div><b>Last Update:</b> ' + safeHtml(formatDateTime(row.upddate)) + '</div>' +
                    '<div><b>Updated By:</b> ' + safeHtml(row.upduser || '-') + '</div>' +
                '</div>'
        });
    });

    $('#mainMaxCapacityTable tbody').on('click', '.btnEditMaxCapacity', function () {
        var row = getMaxCapacityMainRowFromButton($(this));
        if (!row) return;

        startEditMaxCapacityEntry(row);
        $('#masterTabs .nav-link').removeClass('active');
        $('#masterTabs .nav-link[data-tab-target="tabMaxCapacity"]').addClass('active');
        $('.tab-pane-section').removeClass('active');
        $('#tabMaxCapacity').addClass('active');
        $('html, body').animate({ scrollTop: $('#tabMaxCapacity .entry-panel').offset().top - 90 }, 250);
    });

    $('#mainMaxCapacityTable tbody').on('click', '.btnDeleteMaxCapacity', function () {
        var row = getMaxCapacityMainRowFromButton($(this));
        if (!row) return;

        var capId = Number(row.id || 0);
        if (!Number.isFinite(capId) || capId <= 0) {
            toast('warning', 'ID max capacity tidak valid.');
            return;
        }

        Swal.fire({
            title: 'Hapus max production capacity?',
            text: 'Data max production capacity ini akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post(apiUrl, {
                action: 'max_capacity_delete',
                ids: [capId]
            }, null, 'json').done(function (res) {
                if (!res.success) {
                    toast('error', res.message || 'Gagal menghapus max production capacity.');
                    return;
                }
                loadMaxCapacityMainData();
                clearEntryMaxCapacityFields();
                toast('success', res.message || 'Max production capacity berhasil dihapus.');
            }).fail(function () {
                toast('error', 'Gagal menghapus max production capacity.');
            });
        });
    });

    $('#btnAddPlanType').on('click', function () {
        var newType = $('#newPlanType').val().trim();
        if (!newType) {
            toast('warning', 'Isi nama tipe planning terlebih dahulu.');
            return;
        }

        $.post(apiUrl, {
            action: 'plan_type_add',
            plan_type: newType
        }, null, 'json').done(function (res) {
            if (!res.success) {
                toast('error', res.message || 'Gagal menambahkan tipe planning.');
                return;
            }
            $('#newPlanType').val('');
            loadPlanTypes(newType);
            toast('success', res.message || 'Tipe planning berhasil ditambahkan.');
        }).fail(function () {
            toast('error', 'Gagal menambahkan tipe planning.');
        });
    });

    $('#newPlanType').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#btnAddPlanType').trigger('click');
        }
    });

    $('#planTypeTableBody').on('click', '.btnDeletePlanType', function () {
        var type = $(this).data('type');
        var routeCount = Number($(this).data('count') || 0);

        var message = 'Tipe planning "' + type + '" akan dihapus.';
        if (routeCount > 0) {
            message += ' Mapping routing terkait (' + routeCount + ' data) juga akan dihapus.';
        }
        message += ' Mapping machine pada tipe ini juga akan dihapus.';

        Swal.fire({
            title: 'Hapus tipe planning?',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            $.post(apiUrl, {
                action: 'plan_type_delete',
                plan_type: type
            }, null, 'json').done(function (res) {
                if (!res.success) {
                    toast('error', res.message || 'Gagal menghapus tipe planning.');
                    return;
                }
                var preferred = $('#entryPlanType').val();
                loadPlanTypes(preferred);
                loadMainData();
                loadMachineMainData();
                loadBreakMainData();
                loadDownTimeMainData();
                loadMaxCapacityMainData();
                clearEntryRoutingFields();
                clearEntryMachineFields();
                clearEntryBreakFields();
                clearEntryDownTimeFields();
                clearEntryMaxCapacityFields();

                if (res.removed_mappings && Number(res.removed_mappings) > 0) {
                    toast('success', 'Tipe planning dihapus. Mapping terhapus: ' + Number(res.removed_mappings));
                } else {
                    toast('success', res.message || 'Tipe planning berhasil dihapus.');
                }
            }).fail(function () {
                toast('error', 'Gagal menghapus tipe planning.');
            });
        });
    });

    loadPlanTypes().always(function () {
        loadMainData();
        loadMachineMainData();
        loadBreakMainData();
        loadDownTimeMainData();
        loadMaxCapacityMainData();
        clearEntryMaxCapacityFields();
    });
});
</script>
