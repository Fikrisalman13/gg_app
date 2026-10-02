<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../koneksi3.php';
include 'routing_692_data.php';
include 'routing_filter_template_lib.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';
date_default_timezone_set('Asia/Jakarta');

$themeColor = $_SESSION['Theme'] ?? 'primary';
$filterSubmitted = isset($_GET['tanggal_awal'], $_GET['tanggal_akhir']);
$selectedRoutingGroupId = isset($_GET['routing_group_id']) && preg_match('/^\d+$/', (string) $_GET['routing_group_id']) ? (string) $_GET['routing_group_id'] : '';
$selectedRoutingIds = $_GET['routing_ids'] ?? [];
if (!is_array($selectedRoutingIds)) { $selectedRoutingIds = preg_split('/[\s,]+/', $selectedRoutingIds); }
$selectedRoutingIds = array_values(array_unique(array_filter(array_map('strval', $selectedRoutingIds), fn($id) => preg_match('/^\d+$/', $id))));
if ($filterSubmitted) {
    $result = routing692FetchData($conn3, $_GET['tanggal_awal'], $_GET['tanggal_akhir'], $selectedRoutingIds);
} else {
    $today = date('Y-m-d');
    $result = [
        'startDate' => $today,
        'endDate' => $today,
        'errors' => [],
        'rows' => [],
        'summary' => [
            'total_pemartaian_m' => 0,
            'total_pemartaian_y' => 0,
            'total_qty_produksi' => 0,
            'total_rows' => 0,
        ],
        'maxDays' => 31,
        'selectedRoutingIds' => $selectedRoutingIds,
        'selectedRoutingGroupId' => $selectedRoutingGroupId,
    ];
}
extract($result);
$routingGroupOptions = [];
$routingOptions = [];
try {
    $routingGroupOptions = $conn3->query("SELECT DISTINCT grp.rtggrpid, grp.rtggrpname FROM pdproductionrtg r INNER JOIN pdproductionhd h ON h.productionhdid = r.productionhdid INNER JOIN pdrtgms ms ON ms.rtgmsid = r.rtgmsid INNER JOIN pdrtggrp grp ON grp.rtggrpid = ms.rtggrpid WHERE r.compid = 2 AND h.workcenterid = '111' ORDER BY grp.rtggrpname")->fetchAll(PDO::FETCH_ASSOC);
    $routingOptions = $conn3->query("SELECT DISTINCT ms.rtgmsid, ms.rtgcode, ms.rtgname, ms.rtggrpid FROM pdproductionrtg r INNER JOIN pdproductionhd h ON h.productionhdid = r.productionhdid INNER JOIN pdrtgms ms ON ms.rtgmsid = r.rtgmsid WHERE r.compid = 2 AND h.workcenterid = '111' ORDER BY ms.rtgname, ms.rtgcode")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $routingGroupOptions = [];
    $routingOptions = [];
}
$selectedRoutingIds = $selectedRoutingIds ?? [];
$selectedRoutingQuery = http_build_query(['routing_group_id' => $selectedRoutingGroupId, 'routing_ids' => $selectedRoutingIds]);
$currentUsername = outputVpkCurrentUsername();
$userRoutingTemplates = $currentUsername !== '' ? outputVpkGetRoutingTemplates($conn, $currentUsername) : [];
$userRoutingTemplatesJson = json_encode($userRoutingTemplates);
$columns = $rows ? array_keys($rows[0]) : [];
?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet"
    href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<style>
    .report-hero {
        border-radius: 18px;
        background: linear-gradient(135deg, rgba(255, 255, 255, .97), rgba(245, 247, 255, .9));
        border: 1px solid rgba(0, 0, 0, .06);
        box-shadow: 0 18px 45px rgba(15, 23, 42, .08)
    }

    .report-metric {
        height: 100%;
        border-radius: 16px;
        padding: 14px 16px;
        background: linear-gradient(135deg, #fff, rgba(248, 250, 252, .92));
        border: 1px solid rgba(15, 23, 42, .08);
        box-shadow: 0 10px 24px rgba(15, 23, 42, .06)
    }

    .report-metric-label {
        display: block;
        color: #64748b;
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em
    }

    .report-metric-value {
        display: block;
        margin-top: 4px;
        color: #0f172a;
        font-size: 1.1rem;
        font-weight: 800;
        line-height: 1.2
    }

    .table-wrap {
        overflow-x: auto
    }

    #routingTable th,
    #routingTable td {
        white-space: nowrap;
        vertical-align: middle
    }

    .select2-container--bootstrap4 .select2-selection--multiple {
        min-height: 38px;
        max-height: 78px;
        overflow-y: auto;
    }

    .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .filter-settings-btn {
        width: 32px;
        height: 32px;
        border: 1px solid rgba(255,255,255,.45);
        border-radius: 10px;
        color: #fff;
        background: rgba(255,255,255,.16);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: .15s ease;
    }

    .filter-settings-btn:hover {
        color: #fff;
        background: rgba(255,255,255,.28);
        transform: translateY(-1px);
    }
</style>
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-7">
                    <h1 class="m-0 text-dark">LKP Output Dyeing</h1><small class="text-muted">Filter tanggal selesai, maksimal <?= $maxDays ?> hari.</small>
                </div>
                <div class="col-sm-5">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Output VPK</a></li>
                        <li class="breadcrumb-item active">Dyeing</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <div class="card report-hero mb-3">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filter Data</h3>
                    <div class="card-tools">
                        <button type="button" id="btnAturFilterSaya" class="filter-settings-btn" title="Atur Filter Saya" data-toggle="modal" data-target="#templateSettingModal">
                            <i class="fas fa-sliders-h"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle mr-1"></i><?= htmlspecialchars($_SESSION['success']) ?><button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle mr-1"></i><?= htmlspecialchars($_SESSION['error']) ?><button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    <form method="get">
                        <div class="row align-items-end">
                            <div class="col-md-4 mb-2"><label for="tanggal_awal">Tanggal Awal</label><input type="date"
                                    id="tanggal_awal" name="tanggal_awal" class="form-control"
                                    value="<?= htmlspecialchars(substr($startDate, 0, 10)) ?>"></div>
                            <div class="col-md-4 mb-2"><label for="tanggal_akhir">Tanggal Akhir</label><input type="date"
                                    id="tanggal_akhir" name="tanggal_akhir" class="form-control"
                                    value="<?= htmlspecialchars(substr($endDate, 0, 10)) ?>"></div>
                            <div class="col-md-4 mb-2"><label for="routing_group_id">Grup Routing</label><select
                                    id="routing_group_id" name="routing_group_id" class="form-control">
                                    <option value="">Pilih grup routing...</option>
                                    <?php foreach ($routingGroupOptions as $group): $groupId = (string) $group['rtggrpid']; ?>
                                        <option value="<?= htmlspecialchars($groupId) ?>" <?= $groupId === $selectedRoutingGroupId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($group['rtggrpname'] ?? $groupId) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select></div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-2 text-right">
                                <button type="button" id="btnFilterSaya" class="btn btn-info"><i class="fas fa-magic mr-1"></i> <?= count($userRoutingTemplates) === 1 ? htmlspecialchars($userRoutingTemplates[0]['template_name']) : 'Filter Saya' ?></button>
                                <button type="submit"
                                    class="btn btn-<?= htmlspecialchars($themeColor) ?> ml-1"><i class="fas fa-search mr-1"></i>
                                    Tampilkan</button><a href="laporan_routing_692.php"
                                    class="btn btn-outline-secondary ml-1">Reset</a><?php if ($filterSubmitted && empty($errors)): ?><a
                                        href="export_routing_692_excel.php?tanggal_awal=<?= urlencode($startDate) ?>&tanggal_akhir=<?= urlencode($endDate) ?>&<?= htmlspecialchars($selectedRoutingQuery) ?>"
                                        class="btn btn-success ml-1"><i class="fas fa-file-excel mr-1"></i> Export
                                        Excel</a><?php endif; ?>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12"><label for="routing_ids">Routing (maks. 5)</label><select
                                    id="routing_ids" name="routing_ids[]" class="form-control" multiple>
                                    <?php foreach ($routingOptions as $routing): $routingId = (string) $routing['rtgmsid']; $groupId = (string) $routing['rtggrpid']; ?>
                                        <option value="<?= htmlspecialchars($routingId) ?>" data-group="<?= htmlspecialchars($groupId) ?>" <?= $selectedRoutingGroupId !== '' && $groupId !== $selectedRoutingGroupId ? 'disabled' : '' ?> <?= in_array($routingId, $selectedRoutingIds, true) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(trim(($routing['rtgcode'] ?? '') . ' - ' . ($routing['rtgname'] ?? '')) ?: $routingId) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select><small class="text-muted">Cari kode/nama routing, pilih maksimal 5.</small></div>
                        </div>
                    </form>
                    <?php if ($filterSubmitted): ?>
                        <div class="row mt-3">
                            <div class="col-lg-3 col-md-6 mb-2">
                                <div class="report-metric"><span class="report-metric-label">Jumlah Baris Data</span><span
                                        class="report-metric-value"><?= number_format($summary['total_rows'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-2">
                                <div class="report-metric"><span class="report-metric-label">Qty Permartaian Meter
                                        (M)</span><span
                                        class="report-metric-value"><?= number_format($summary['total_pemartaian_m'], 2, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-2">
                                <div class="report-metric"><span class="report-metric-label">Qty Pemartaian Yard
                                        (Y)</span><span
                                        class="report-metric-value"><?= number_format($summary['total_pemartaian_y'], 2, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-2">
                                <div class="report-metric"><span class="report-metric-label">Total Qty Produksi</span><span
                                        class="report-metric-value"><?= number_format($summary['total_qty_produksi'], 2, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-3 mb-0"><i class="fas fa-info-circle mr-1"></i> Silakan pilih filter
                            tanggal lalu klik <strong>Tampilkan</strong> untuk memuat data.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><i
                        class="fas fa-exclamation-triangle mr-1"></i><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
            <?php if ($filterSubmitted): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                        <h3 class="card-title"><i class="fas fa-table mr-1"></i> Hasil Laporan</h3>
                        <div class="card-tools">Total data: <?= number_format($summary['total_rows'], 0, ',', '.') ?> baris</div>
                    </div>
                    <div class="card-body table-wrap">
                        <table id="routingTable" class="table table-bordered table-hover table-sm w-100">
                            <thead class="thead-light">
                                <tr><?php if ($columns):
                                    foreach ($columns as $column): ?>
                                            <th><?= htmlspecialchars($column) ?></th><?php endforeach; else: ?>
                                        <th>Info</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody><?php if ($rows):
                                foreach ($rows as $row): ?>
                                        <tr><?php foreach ($columns as $column): ?>
                                                <td><?= htmlspecialchars((string) ($row[$column] ?? '')) ?></td><?php endforeach; ?>
                                        </tr><?php endforeach; else: ?>
                                    <tr>
                                        <td class="text-center text-muted">Tidak ada data.</td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
<div class="modal fade" id="templatePickerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Pilih Filter Saya</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body"><div id="templatePickerList" class="row"></div></div>
    </div></div>
</div>
<div class="modal fade" id="templateSettingModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <form method="post" action="routing_filter_template_action.php" id="templateForm">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Atur Filter Saya</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" id="template_action" value="save">
                <input type="hidden" name="template_id" id="template_id" value="">
                <input type="hidden" name="routing_group_id" id="template_routing_group_id">
                <input type="hidden" name="routing_group_name" id="template_routing_group_name">
                <div id="templateRoutingInputs"></div>
                <div class="form-group"><label>Nama Template</label><input type="text" name="template_name" id="template_name" class="form-control" placeholder="Contoh: Verpacking" required></div>
                <div class="alert alert-info mb-2">Template disimpan dari Grup Routing dan Routing yang sedang dipilih di filter halaman.</div>
                <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Template Saya</th><th>Routing</th><th style="width:190px">Aksi</th></tr></thead><tbody id="templateSettingList"></tbody></table></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Tutup</button><button type="submit" class="btn btn-info">Simpan Template</button></div>
        </form>
    </div></div>
</div>
<div class="modal fade" id="templateNoticeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white"><h5 class="modal-title">Informasi</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body" id="templateNoticeMessage"></div>
        <div class="modal-footer"><button type="button" class="btn btn-<?= htmlspecialchars($themeColor) ?>" data-dismiss="modal">OK</button></div>
    </div></div>
</div>
<div class="modal fade" id="deleteTemplateModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <form method="post" action="routing_filter_template_action.php">
            <div class="modal-header bg-danger text-white"><h5 class="modal-title">Hapus Template</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body">Hapus template ini?</div>
            <div class="modal-footer">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="template_id" id="delete_template_id">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Hapus</button>
            </div>
        </form>
    </div></div>
</div>
<?php include '../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script>
$(function () {
    const templates = <?= $userRoutingTemplatesJson ?: '[]' ?>;
    const allRoutingOptions = $('#routing_ids option').clone().removeAttr('disabled').removeAttr('selected');

    function showNotice(message, type = 'info') {
        $('#templateNoticeModal .modal-header').removeClass('bg-info bg-danger bg-warning bg-success').addClass('bg-' + type);
        $('#templateNoticeMessage').text(message);
        $('#templateNoticeModal').modal('show');
    }

    function filterRoutingOptions(clearSelection = true) {
        const groupId = $('#routing_group_id').val();
        const selectedValues = clearSelection ? [] : ($('#routing_ids').val() || []);
        if ($('#routing_ids').data('select2')) { $('#routing_ids').select2('destroy'); }
        $('#routing_ids').empty();
        allRoutingOptions.each(function () {
            if (groupId && $(this).data('group').toString() === groupId) {
                $('#routing_ids').append($(this).clone());
            }
        });
        $('#routing_ids').val(selectedValues);
        $('#routing_ids').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: groupId ? 'Cari routing...' : 'Pilih grup routing dulu...',
            maximumSelectionLength: 5,
            language: { maximumSelected: function () { return 'Maksimal 5 routing.'; } }
        });
    }

    function applyTemplate(tpl) {
        $('#routing_group_id').val(String(tpl.routing_group_id)).trigger('change.select2');
        filterRoutingOptions(true);
        $('#routing_ids').val((tpl.routing_ids_array || []).map(String)).trigger('change.select2');
        $('#templatePickerModal').modal('hide');
    }

    function syncTemplatePayload() {
        $('#template_routing_group_id').val($('#routing_group_id').val());
        $('#template_routing_group_name').val($('#routing_group_id option:selected').text().trim());
        $('#templateRoutingInputs').empty();
        ($('#routing_ids').val() || []).forEach(function (id) {
            const label = $('#routing_ids option[value="' + id + '"]').text().trim();
            $('<input>', { type: 'hidden', name: 'routing_ids[]', value: id }).appendTo('#templateRoutingInputs');
            $('<input>', { type: 'hidden', name: 'routing_labels[]', value: label }).appendTo('#templateRoutingInputs');
        });
    }

    function prepareTemplateForm(tpl) {
        $('#template_action').val(tpl ? 'update' : 'save');
        $('#template_id').val(tpl ? tpl.id : '');
        $('#template_name').val(tpl ? tpl.template_name : '');
        syncTemplatePayload();
    }

    function renderTemplates() {
        $('#templatePickerList').empty();
        $('#templateSettingList').empty();
        templates.forEach(function (tpl, idx) {
            const labels = (tpl.routing_labels_array || []).join(', ');
            $('#templatePickerList').append('<div class="col-md-6 mb-2"><button type="button" class="btn btn-outline-<?= htmlspecialchars($themeColor) ?> btn-block text-left js-apply-template" data-index="' + idx + '"><strong>' + tpl.template_name + '</strong><br><small>' + labels + '</small></button></div>');
            $('#templateSettingList').append('<tr><td>' + tpl.template_name + '</td><td>' + labels + '</td><td><button type="button" class="btn btn-xs btn-<?= htmlspecialchars($themeColor) ?> js-apply-template" data-index="' + idx + '">Pakai</button>  <button type="button" class="btn btn-xs btn-danger js-delete-template" data-id="' + tpl.id + '">Hapus</button></td></tr>');
        });
        if (!templates.length) {
            $('#templateSettingList').append('<tr><td colspan="3" class="text-muted text-center">Belum ada template.</td></tr>');
        }
    }

    $('#routing_group_id').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: 'Pilih grup routing...',
        allowClear: true
    }).on('change', function () { filterRoutingOptions(true); });
    filterRoutingOptions(false);
    $('#routing_ids').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: $('#routing_group_id').val() ? 'Cari routing...' : 'Pilih grup routing dulu...',
        maximumSelectionLength: 5,
        language: { maximumSelected: function () { return 'Maksimal 5 routing.'; } }
    });
    renderTemplates();
    $('#btnFilterSaya').on('click', function () {
        if (!templates.length) { showNotice('Belum ada template. Klik icon setting di header Filter Data untuk membuat template.', 'info'); return; }
        if (templates.length === 1) { applyTemplate(templates[0]); return; }
        $('#templatePickerModal').modal('show');
    });
    $('#btnAturFilterSaya').on('click', function () { prepareTemplateForm(null); });
    $(document).on('click', '.js-apply-template', function () { applyTemplate(templates[$(this).data('index')]); });
    $(document).on('click', '.js-edit-template', function () { prepareTemplateForm(templates[$(this).data('index')]); });
    $(document).on('click', '.js-delete-template', function () {
        $('#delete_template_id').val($(this).data('id'));
        $('#deleteTemplateModal').modal('show');
    });
    $('#templateForm').on('submit', function (e) {
        syncTemplatePayload();
        if (!$('#template_routing_group_id').val() || !($('#routing_ids').val() || []).length) { e.preventDefault(); showNotice('Pilih Grup Routing dan Routing dulu.', 'warning'); }
    });
    $('#routingTable').DataTable({ responsive: false, scrollX: true, autoWidth: false, pageLength: 25, language: { lengthMenu: 'Tampilkan _MENU_ data', zeroRecords: 'Tidak ada data', info: 'Halaman _PAGE_ dari _PAGES_', search: 'Cari:', paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' } } });
});
</script>