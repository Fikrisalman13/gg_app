<?php
// pages/resep_obat/list_resep.php
session_start();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../koneksi.php';
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

include '../../../includes/header.php';
include '../../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Resep</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">List Resep</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                    <h3 class="card-title"><i class="fas fa-flask mr-1"></i> List Resep</h3>
                    <div class="float-right">

                        <?php if ($permissions['CanEdit'] == 1): ?>
                            <a href="migrate_from_v1.php" class="btn btn-warning btn-sm mr-1">
                                <i class="fas fa-exchange-alt"></i> Migrasi dari V1
                            </a>
                            <button type="button" class="btn btn-secondary btn-sm mr-1" id="ppcExperimentBulkSyncOpen">
                                <i class="fas fa-sync-alt"></i> Sinkronkan Experiment
                            </button>
                        <?php endif; ?>
                        <?php if ($permissions['CanAdd'] == 1): ?>
                            <button type="button" class="btn btn-info btn-sm mr-1" id="ppcExperimentDraftOpen">
                                <i class="fas fa-magic"></i> Ambil dari Experiment
                            </button>
                            <a href="input_resep.php" class="btn btn-success btn-sm">
                                <i class="fas fa-plus"></i> Tambah Resep
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Date Filter Section -->
                    <div class="row mb-3 align-items-end">
                        <div class="col-md-2">
                            <label for="startDate">Start Date:</label>
                            <input type="date" class="form-control" id="startDate" name="startDate">
                        </div>
                        <div class="col-md-2">
                            <label for="endDate">End Date:</label>
                            <input type="date" class="form-control" id="endDate" name="endDate">
                        </div>
                        <div class="col-md-3">
                            <label for="statusFilter">Status:</label>
                            <select class="form-control" id="statusFilter" name="statusFilter[]" multiple>
                                <option value="Shading">Shading</option>
                                <option value="Experiment">Experiment</option>
                                <option value="Kesetabilan">Kesetabilan</option>
                                <option value="Master Resep">Master Resep</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label for="cusColorFilter">Cus Color:</label>
                            <input type="search" class="form-control" id="cusColorFilter" name="cusColorFilter"
                                placeholder="Maks. 8 digit" maxlength="8" autocomplete="off">
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label for="labelJualFilter">Label Jual:</label>
                            <select class="form-control" id="labelJualFilter" name="labelJualFilter[]" multiple style="width:100%;"></select>
                        </div>
                        <div class="col-lg-1 col-md-4 d-flex align-items-end">
                            <button id="btnFilter" type="button" class="btn btn-primary" title="Terapkan filter"
                                aria-label="Terapkan filter"><i class="fas fa-filter" aria-hidden="true"></i></button>
                            <button id="btnExport" type="button" class="btn btn-success ml-1" title="Export Excel"
                                aria-label="Export Excel"><i class="fas fa-file-excel" aria-hidden="true"></i></button>
                        </div>
                    </div>

                    <div class="status-summary mb-3" id="statusSummary" aria-live="polite">
                        <button type="button" class="status-summary-item status-shading ppc-summary-group-open" data-category="shading" data-label="Shading" aria-label="Lihat rincian grouping Shading">
                            <span>Shading</span><strong id="summaryShading">0</strong>
                        </button>
                        <button type="button" class="status-summary-item status-experiment ppc-summary-group-open" data-category="experiment" data-label="Experiment" aria-label="Lihat rincian grouping Experiment">
                            <span>Experiment</span><strong id="summaryExperiment">0</strong>
                        </button>
                        <button type="button" class="status-summary-item status-kestabilan ppc-summary-group-open" data-category="kesetabilan" data-label="Kesetabilan" aria-label="Lihat rincian grouping Kesetabilan">
                            <span>Kesetabilan</span><strong id="summaryKesetabilan">0</strong>
                        </button>
                        <button type="button" class="status-summary-item status-master-resep ppc-summary-group-open" data-category="master_resep" data-label="Master Resep" aria-label="Lihat rincian grouping Master Resep">
                            <span>Master Resep</span><strong id="summaryMasterResep">0</strong>
                        </button>
                        <button type="button" class="status-summary-item status-total ppc-summary-group-open" data-category="total" data-label="Total Hasil" aria-label="Lihat rincian seluruh grouping">
                            <span>Total Hasil</span><strong id="summaryTotal">0</strong>
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table id="resepTable" class="table table-bordered table-hover table-sm nowrap"
                            style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th>No</th>
                                    <th>No CP</th>
                                    <th>Label Jual</th>
                                    <th>Kode Warna</th>
                                    <th>Color Name</th>
                                    <th>Cus Color</th>
                                    <th>Weight</th>
                                    <th>Plan Qty</th>
                                    <th class="text-right">Total Cost / Meter</th>
                                    <th>Status Resep</th>
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

<div class="modal fade" id="ppcSummaryGroupModal" tabindex="-1" role="dialog" aria-labelledby="ppcSummaryGroupTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title" id="ppcSummaryGroupTitle"><i class="fas fa-layer-group mr-2"></i>Rincian Grouping</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" id="ppcSummaryGroupExplanation"></div>
                <div class="mb-2">
                    <small class="text-muted">Dasar grouping: <strong>Kode Warna + Cus Color + Status</strong></small>
                </div>
                <div id="ppcSummaryGroupList"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="ppcExperimentChoiceModal" tabindex="-1" role="dialog"
    aria-labelledby="ppcExperimentChoiceTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title" id="ppcExperimentChoiceTitle">
                    <i class="fas fa-flask mr-2"></i>Pilih Experiment
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" id="ppcExperimentChoiceHelp"></p>
                <div class="experiment-choice-list" id="ppcExperimentChoiceList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ppcExperimentSyncModal" tabindex="-1" role="dialog"
    aria-labelledby="ppcExperimentSyncTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title" id="ppcExperimentSyncTitle"><i class="fas fa-sync-alt mr-2"></i>Sinkronisasi Experiment</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info"><i class="fas fa-info-circle mr-1"></i>Hanya No CP dan No SO kosong/placeholder yang diperbarui. Nilai PPC valid tidak ditimpa.</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="thead-light"><tr><th>Field</th><th>PPC Sekarang</th><th>Experiment</th><th>Hasil</th></tr></thead>
                        <tbody id="ppcExperimentSyncRows"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-<?= htmlspecialchars($themeColor); ?>" id="ppcExperimentSyncConfirm"><i class="fas fa-sync-alt mr-1"></i>Sinkronkan</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ppcExperimentBulkSyncModal" tabindex="-1" role="dialog"
    aria-labelledby="ppcExperimentBulkSyncTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor); ?> text-white">
                <h5 class="modal-title" id="ppcExperimentBulkSyncTitle"><i class="fas fa-sync-alt mr-2"></i>Sinkronkan Semua Resep Experiment</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info"><i class="fas fa-globe mr-1"></i>Memindai seluruh resep PPC berstatus <strong>Experiment</strong>. Filter tabel tidak membatasi sinkronisasi.</div>
                <div class="row text-center mb-3" id="ppcExperimentBulkSummary"></div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="thead-light"><tr><th>PPC ID</th><th>Cus Color</th><th>No CP Sekarang</th><th>No CP Source</th><th>No SO Sekarang</th><th>No SO Source</th></tr></thead>
                        <tbody id="ppcExperimentBulkRows"></tbody>
                    </table>
                </div>
                <p class="text-muted mb-0" id="ppcExperimentBulkNote"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-<?= htmlspecialchars($themeColor); ?>" id="ppcExperimentBulkSyncConfirm"><i class="fas fa-sync-alt mr-1"></i>Sinkronkan</button>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>

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

    .status-experiment {
        background: #0f766e;
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

    .status-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
        gap: .65rem;
    }

    .status-summary-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 54px;
        padding: .65rem .85rem;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(31, 45, 61, .12);
        font-size: .82rem;
        font-weight: 600;
    }

    .status-summary-item {
        border: 0;
        cursor: pointer;
        font-family: inherit;
        text-align: left;
    }

    .status-summary-item.status-shading {
        color: #1f2d3d;
    }

    .status-summary-item.status-experiment,
    .status-summary-item.status-kestabilan,
    .status-summary-item.status-master-resep,
    .status-summary-item.status-total {
        color: #fff;
    }

    .status-summary-item:hover,
    .status-summary-item:focus {
        box-shadow: 0 6px 16px rgba(31, 45, 61, .24);
        outline: 3px solid rgba(255, 255, 255, .65);
        transform: translateY(-2px);
    }

    .status-summary-item strong {
        font-size: 1.35rem;
        margin-left: .5rem;
    }

    .ppc-summary-group-card {
        border: 1px solid #d8e2ee;
        border-left: 4px solid var(--primary, #2563eb);
        border-radius: 8px;
        margin-bottom: .75rem;
        padding: .75rem;
    }

    .ppc-summary-group-members {
        display: grid;
        gap: .4rem;
        margin-top: .65rem;
    }

    .ppc-summary-group-member {
        align-items: center;
        background: #f6f8fb;
        border-radius: 6px;
        display: grid;
        gap: .5rem;
        grid-template-columns: minmax(90px, 1fr) minmax(100px, 1.2fr) minmax(100px, 1fr) auto;
        padding: .45rem .6rem;
    }

    .status-total {
        background: #1f2937;
        color: #fff;
    }

    .experiment-choice-list {
        display: grid;
        gap: .75rem;
        max-height: 56vh;
        overflow-y: auto;
        padding: .2rem;
        text-align: left;
    }

    .experiment-choice-card {
        border: 1px solid #d8e2ee;
        border-left: 5px solid var(--primary, #2563eb);
        border-radius: 10px;
        background: #fff;
        padding: .9rem;
        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }

    .experiment-choice-card:hover {
        border-color: var(--primary, #2563eb);
        box-shadow: 0 8px 22px rgba(31, 45, 61, .14);
        transform: translateY(-1px);
    }

    .experiment-choice-heading,
    .experiment-choice-meta,
    .experiment-choice-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .7rem;
        flex-wrap: wrap;
    }

    .experiment-choice-title {
        color: #1f2937;
        font-size: 1rem;
        font-weight: 700;
    }

    .experiment-choice-code {
        color: #52606d;
        font-size: .85rem;
        margin-top: .2rem;
    }

    .experiment-choice-meta {
        background: #f6f8fb;
        border-radius: 7px;
        margin: .7rem 0;
        padding: .55rem .7rem;
        font-size: .8rem;
    }

    .experiment-choice-meta strong {
        color: #1f2937;
        display: block;
        font-size: .83rem;
    }

    .experiment-choice-status {
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 700;
        padding: .3rem .6rem;
    }

    .experiment-choice-status-approved { background: #dcfce7; color: #166534; }
    .experiment-choice-status-process { background: #fef3c7; color: #92400e; }

    .experiment-choice-footer {
        color: #6b7280;
        font-size: .76rem;
    }

    .experiment-choice-use {
        white-space: nowrap;
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

        function escapeDraftText(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        }

        async function fetchExperimentDraft(parameters) {
            const response = await fetch('experiment_draft.php?' + new URLSearchParams(parameters), {
                headers: { 'Accept': 'application/json' }
            });
            const text = await response.text();
            let payload;
            try {
                payload = JSON.parse(text);
            } catch (error) {
                throw new Error('Respons server tidak valid.');
            }
            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || 'Gagal memuat experiment.');
            }
            return payload;
        }

        $('#ppcExperimentDraftOpen').on('click', async function () {
            const searchDialog = await Swal.fire({
                title: 'Ambil dari Experiment',
                input: 'text',
                inputLabel: 'Cus Color',
                inputPlaceholder: 'Contoh: 244C',
                showCancelButton: true,
                confirmButtonText: 'Cari',
                cancelButtonText: 'Batal',
                inputValidator: value => value.trim() ? null : 'Cus Color wajib diisi.'
            });
            if (!searchDialog.isConfirmed) return;

            try {
                Swal.fire({ title: 'Mencari...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                const searchResult = await fetchExperimentDraft({ action: 'search', cus_color: searchDialog.value.trim() });
                if (!searchResult.results.length) {
                    await Swal.fire('Tidak Ditemukan', 'Tidak ada experiment yang pernah Approved untuk Cus Color tersebut.', 'info');
                    return;
                }

                Swal.close();
                const cards = searchResult.results.map(item => {
                    const statusClass = String(item.experiment_status).toLowerCase() === 'approved'
                        ? 'experiment-choice-status-approved'
                        : 'experiment-choice-status-process';
                    return `<article class="experiment-choice-card">
                        <div class="experiment-choice-heading">
                            <div>
                                <div class="experiment-choice-title">Experiment #${escapeDraftText(item.experiment_seq)}</div>
                                <div class="experiment-choice-code">${escapeDraftText(item.kode_warna)} · ${escapeDraftText(item.color_name || '-')}</div>
                            </div>
                            <span class="experiment-choice-status ${statusClass}">${escapeDraftText(item.experiment_status)}</span>
                        </div>
                        <div class="experiment-choice-meta">
                            <span>No CP<strong>${escapeDraftText(item.no_cp || '-')}</strong></span>
                            <span>No SO<strong>${escapeDraftText(item.soi || '-')}</strong></span>
                            <span>Formula<strong>${escapeDraftText(item.detail_count)} material</strong></span>
                        </div>
                        <div class="experiment-choice-footer">
                            <span><i class="far fa-calendar-alt mr-1"></i>${escapeDraftText(item.created_at || '-')} · ${escapeDraftText(item.created_by || '-')}</span>
                            <button type="button" class="btn btn-primary btn-sm experiment-choice-use"
                                data-experiment-id="${escapeDraftText(item.id)}">
                                <i class="fas fa-file-import mr-1"></i> Gunakan Data
                            </button>
                        </div>
                    </article>`;
                }).join('');
                const selection = await new Promise(resolve => {
                    const $modal = $('#ppcExperimentChoiceModal');
                    $('#ppcExperimentChoiceTitle').html(
                        `<i class="fas fa-flask mr-2"></i>Experiment Cus Color ${escapeDraftText(searchDialog.value.trim())}`
                    );
                    $('#ppcExperimentChoiceHelp').text(
                        'Pilih formula berdasarkan No CP, No SO, status, dan tanggal pembuatan.'
                    );
                    $('#ppcExperimentChoiceList').html(cards);
                    $modal.one('hidden.bs.modal', function () {
                        resolve(null);
                    });
                    $modal.off('click.experimentChoice').on(
                        'click.experimentChoice',
                        '.experiment-choice-use',
                        function () {
                            const experimentId = String($(this).data('experiment-id'));
                            $modal.off('hidden.bs.modal').modal('hide');
                            resolve(experimentId);
                        }
                    );
                    $modal.modal('show');
                });
                if (!selection) return;

                Swal.fire({ title: 'Memeriksa data...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                const draft = await fetchExperimentDraft({ action: 'draft', experiment_id: selection });
                if (draft.duplicates.length) {
                    const rows = draft.duplicates.map(item =>
                        `<li class="text-left">PPC #${item.id}: ${escapeDraftText(item.no_cp)} — ${escapeDraftText(item.status_resep_lipat || '-')}</li>`
                    ).join('');
                    const warning = await Swal.fire({
                        icon: 'warning',
                        title: 'Resep Serupa Ditemukan',
                        html: `<p>Data PPC serupa sudah ada:</p><ul>${rows}</ul><p>Tetap buat draft baru?</p>`,
                        showCancelButton: true,
                        confirmButtonText: 'Tetap Lanjut',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#d97706'
                    });
                    if (!warning.isConfirmed) return;
                }
                window.location.href = 'input_resep.php?source_experiment_id=' + encodeURIComponent(selection) + '&duplicate_confirmed=1';
            } catch (error) {
                Swal.fire('Gagal', error.message, 'error');
            }
        });

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
                    d.statusFilter = $('#statusFilter').val() || [];
                    d.cusColorFilter = $('#cusColorFilter').val().trim();
                    d.labelJualFilter = $('#labelJualFilter').val() || [];
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
                    name: 'no_cp',
                    render: function (data, type, row) {
                        return `<b>${data}</b>`;
                    }
                },
                {
                    data: 'label_jual',
                    name: 'label_jual',
                    orderable: false,
                    defaultContent: '-'
                },
                { data: 'kode_warna', name: 'kode_warna' },
                { data: 'color_name', name: 'color_name' },
                { data: 'cus_color', name: 'cus_color' },
                { data: 'weight', name: 'weight' },
                { data: 'plan_qty', name: 'plan_qty' },
                {
                    data: 'total_cost_per_meter',
                    name: 'total_cost_per_meter',
                    className: 'text-right font-weight-bold',
                    render: function (data, type, row) {
                        var val = parseFloat(data) || 0;
                        if (type === 'sort' || type === 'type') {
                            return val;
                        }
                        if (val <= 0) return '-';
                        return 'Rp ' + val.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                },
                {
                    data: 'status_resep_lipat',
                    name: 'status_resep_lipat',
                    className: 'text-center',
                    render: function (data) {
                        var label = (data == null ? '-' : String(data));
                        var lowerData = label.toLowerCase();
                        if (lowerData === 'master resep' || lowerData === 'master') {
                            return '<span class="status-badge status-master-resep">' + label + '</span>';
                        } else if (lowerData === 'shading') {
                            return '<span class="status-badge status-shading">' + label + '</span>';
                        } else if (lowerData === 'experiment') {
                            return '<span class="status-badge status-experiment">' + label + '</span>';
                        } else if (lowerData === 'kestabilan' || lowerData === 'kesetabilan') {
                            return '<span class="status-badge status-kestabilan">' + label + '</span>';
                        }
                        return '<span class="status-badge status-default">' + label + '</span>';
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    render: function (data, type, row) {
                        let buttons = `<a href="view_resep.php?resep_id=${data}" class="btn btn-info btn-sm" title="View"><i class="fas fa-eye"></i></a>`;
                        if (canEdit == 1 && row.has_experiment_source && String(row.status_resep_lipat).toLowerCase() === 'experiment') {
                            buttons += ` <button type="button" class="btn btn-secondary btn-sm ppc-experiment-sync-open" data-resep-id="${data}" title="Sinkronkan No CP/No SO"><i class="fas fa-sync-alt"></i></button>`;
                        }
                        return buttons;
                    }
                }
            ],
            order: [[1, 'asc']]
        });

        let experimentSyncPreview = null;
        const syncStateLabels = {
            update: '<span class="badge badge-warning">Akan diperbarui</span>',
            same: '<span class="badge badge-success">Sudah sama</span>',
            source_empty: '<span class="badge badge-secondary">Source kosong</span>',
            conflict: '<span class="badge badge-danger">Konflik, tidak ditimpa</span>'
        };

        async function requestExperimentSync(url, options = {}) {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, ...options });
            const payload = await response.json().catch(() => null);
            if (!payload || !response.ok || !payload.ok) throw new Error(payload?.message || 'Respons sinkronisasi tidak valid.');
            return payload;
        }

        $('#resepTable').on('click', '.ppc-experiment-sync-open', async function () {
            const recipeId = $(this).data('resep-id');
            try {
                Swal.fire({ title: 'Memeriksa data...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                experimentSyncPreview = await requestExperimentSync(`sync_experiment_metadata.php?resep_id=${encodeURIComponent(recipeId)}`);
                Swal.close();
                const rows = Object.values(experimentSyncPreview.fields).map(field => `<tr>
                    <th>${escapeDraftText(field.label)}</th>
                    <td>${escapeDraftText(field.current || '-')}</td>
                    <td><strong>${escapeDraftText(field.source || '-')}</strong></td>
                    <td>${syncStateLabels[field.state] || '-'}</td>
                </tr>`).join('');
                $('#ppcExperimentSyncRows').html(rows);
                $('#ppcExperimentSyncConfirm')
                    .prop('disabled', experimentSyncPreview.update_count === 0)
                    .html(`<i class="fas fa-sync-alt mr-1"></i>${experimentSyncPreview.update_count ? `Sinkronkan ${experimentSyncPreview.update_count} Field` : 'Data Sudah Sinkron'}`);
                $('#ppcExperimentSyncModal').modal('show');
            } catch (error) {
                Swal.fire('Gagal', error.message, 'error');
            }
        });

        $('#ppcExperimentSyncConfirm').on('click', async function () {
            if (!experimentSyncPreview) return;
            const formData = new FormData();
            formData.append('resep_id', experimentSyncPreview.resep_id);
            formData.append('baseline_no_cp', experimentSyncPreview.fields.no_cp.current);
            formData.append('baseline_no_so', experimentSyncPreview.fields.no_so.current);
            try {
                $(this).prop('disabled', true);
                const result = await requestExperimentSync('sync_experiment_metadata.php', { method: 'POST', body: formData });
                $('#ppcExperimentSyncModal').modal('hide');
                table.ajax.reload(null, false);
                Swal.fire('Berhasil', result.message, 'success');
            } catch (error) {
                $(this).prop('disabled', false);
                Swal.fire('Gagal', error.message, 'error');
            }
        });

        let experimentBulkPreview = null;
        $('#ppcExperimentBulkSyncOpen').on('click', async function () {
            try {
                Swal.fire({ title: 'Memindai seluruh resep Experiment...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                experimentBulkPreview = await requestExperimentSync('sync_experiment_metadata.php?action=bulk_preview');
                Swal.close();
                const summary = experimentBulkPreview.summary;
                const cards = [
                    ['can_update', 'Dapat diperbarui', summary.can_update, 'warning'],
                    ['already_sync', 'Sudah sinkron', summary.already_sync, 'success'],
                    ['source_empty', 'Source kosong', summary.source_empty, 'secondary'],
                    ['conflict', 'Konflik', summary.conflict, 'danger'],
                    ['no_source_id', 'Tanpa ID Experiment', summary.no_source_id, 'danger'],
                    ['scanned', 'Total dipindai', summary.scanned, 'info']
                ].map(item => `<div class="col"><button type="button" class="btn btn-block border rounded p-2 bg-white ppc-bulk-summary-filter" data-category="${item[0]}" ${item[2] ? '' : 'disabled'} aria-label="Lihat ${item[2]} data ${item[1]}"><small class="d-block text-dark">${item[1]}</small><span class="h4 mb-0 text-${item[3]}">${item[2]}</span></button></div>`).join('');
                $('#ppcExperimentBulkSummary').html(cards);
                renderBulkDetailRows('can_update');
                $('#ppcExperimentBulkNote').html(
                    (experimentBulkPreview.truncated
                        ? `Hanya ${experimentBulkPreview.batch_limit} kandidat pertama diproses pada batch ini.`
                        : 'Setiap resep diproses dalam transaction terpisah.')
                );
                $('#ppcExperimentBulkSyncConfirm').prop('disabled', experimentBulkPreview.candidates.length === 0).html(`<i class="fas fa-sync-alt mr-1"></i>Sinkronkan ${experimentBulkPreview.candidates.length} Resep`);
                $('#ppcExperimentBulkSyncModal').modal('show');
            } catch (error) {
                Swal.fire('Gagal', error.message, 'error');
            }
        });

        function renderBulkDetailRows(category) {
            const rows = experimentBulkPreview?.details?.[category] || [];
            $('#ppcExperimentBulkRows').html(rows.map(recipe => `<tr>
                <td>${recipe.resep_id}</td><td>${escapeDraftText(recipe.cus_color)}</td>
                <td>${escapeDraftText(recipe.fields.no_cp.current || '-')}</td><td><strong>${escapeDraftText(recipe.fields.no_cp.source || '-')}</strong></td>
                <td>${escapeDraftText(recipe.fields.no_so.current || '-')}</td><td><strong>${escapeDraftText(recipe.fields.no_so.source || '-')}</strong></td>
            </tr>`).join('') || '<tr><td colspan="6" class="text-center text-muted">Tidak ada data pada kategori ini.</td></tr>');
            $('.ppc-bulk-summary-filter').removeClass('active shadow-sm');
            $(`.ppc-bulk-summary-filter[data-category="${category}"]`).addClass('active shadow-sm');
        }

        $('#ppcExperimentBulkSummary').on('click', '.ppc-bulk-summary-filter', function () {
            renderBulkDetailRows($(this).data('category'));
        });

        $('#ppcExperimentBulkSyncConfirm').on('click', async function () {
            if (!experimentBulkPreview?.candidates?.length) return;
            const candidates = experimentBulkPreview.candidates.map(candidate => ({
                resep_id: candidate.resep_id,
                baseline_no_cp: candidate.fields.no_cp.current,
                baseline_no_so: candidate.fields.no_so.current
            }));
            const formData = new FormData();
            formData.append('action', 'bulk_sync');
            formData.append('candidates', JSON.stringify(candidates));
            try {
                $(this).prop('disabled', true);
                const response = await requestExperimentSync('sync_experiment_metadata.php', { method: 'POST', body: formData });
                $('#ppcExperimentBulkSyncModal').modal('hide');
                table.ajax.reload(null, false);
                const result = response.result;
                Swal.fire('Bulk selesai', `Berhasil: ${result.updated}<br>Dilewati: ${result.skipped}<br>Konflik: ${result.conflict}<br>Gagal: ${result.failed}`, result.failed ? 'warning' : 'success');
            } catch (error) {
                $(this).prop('disabled', false);
                Swal.fire('Gagal', error.message, 'error');
            }
        });

        let ppcSummaryGroupPayload = null;

        function renderSummaryGroups() {
            const groups = ppcSummaryGroupPayload.duplicate_groups;
            $('#ppcSummaryGroupList').html(groups.map(group => `<section class="ppc-summary-group-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <strong>${escapeDraftText(group.kode_warna)} · ${escapeDraftText(group.cus_color)}</strong>
                    <span class="badge badge-primary">${group.member_count} resep · ${escapeDraftText(group.status)}</span>
                </div>
                <div class="ppc-summary-group-members">${group.members.map(member => `<div class="ppc-summary-group-member">
                    <span><small class="text-muted d-block">No CP</small><strong>${escapeDraftText(member.no_cp)}</strong></span>
                    <span><small class="text-muted d-block">Color Name</small>${escapeDraftText(member.color_name)}</span>
                    <span><small class="text-muted d-block">Dibuat</small>${escapeDraftText(member.created_at)} · ${escapeDraftText(member.created_by)}</span>
                    <a class="btn btn-info btn-sm" href="view_resep.php?resep_id=${encodeURIComponent(member.id)}" title="Lihat resep ${member.id}"><i class="fas fa-eye" aria-hidden="true"></i> Detail</a>
                </div>`).join('')}</div>
            </section>`).join('') || '<div class="text-center text-muted py-4">Tidak ada kelompok beranggota lebih dari satu pada hasil ini.</div>');
        }

        $('#statusSummary').on('click', '.ppc-summary-group-open', async function () {
            const category = String($(this).data('category'));
            const label = String($(this).data('label'));
            const request = new URLSearchParams();
            request.set('category', category);
            request.set('startDate', $('#startDate').val());
            request.set('endDate', $('#endDate').val());
            request.set('cusColorFilter', $('#cusColorFilter').val().trim());
            request.set('search', table.search());
            ($('#statusFilter').val() || []).forEach(value => request.append('statusFilter[]', value));
            ($('#labelJualFilter').val() || []).forEach(value => request.append('labelJualFilter[]', value));
            try {
                Swal.fire({ title: 'Memuat rincian grouping...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                const response = await fetch('summary_group_details.php', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body: request.toString()
                });
                const payload = await response.json().catch(() => null);
                if (!response.ok || !payload?.ok) throw new Error(payload?.message || 'Respons rincian summary tidak valid.');
                Swal.close();
                ppcSummaryGroupPayload = payload;
                $('#ppcSummaryGroupTitle').html(`<i class="fas fa-layer-group mr-2"></i>Rincian ${escapeDraftText(label)}`);
                $('#ppcSummaryGroupExplanation').html(
                    `<strong>${payload.raw_total} resep mentah</strong> menjadi <strong>${payload.grouped_total} kelompok</strong>. ` +
                    `<strong>${payload.collapsed_total} resep</strong> dikelompokan karena memiliki Kode Warna, Cus Color, dan Status yang sama.`
                );
                renderSummaryGroups();
                $('#ppcSummaryGroupModal').modal('show');
            } catch (error) {
                Swal.fire('Gagal', error.message, 'error');
            }
        });

        table.on('xhr.dt', function (e, settings, json) {
            if (!json || !json.statusSummary) return;
            $('#summaryShading').text(json.statusSummary.shading || 0);
            $('#summaryExperiment').text(json.statusSummary.experiment || 0);
            $('#summaryKesetabilan').text(json.statusSummary.kesetabilan || 0);
            $('#summaryMasterResep').text(json.statusSummary.master_resep || 0);
            $('#summaryTotal').text(json.recordsFiltered || 0);
        });

        $('#statusFilter').select2({
            theme: 'bootstrap4',
            placeholder: 'Pilih Status',
            allowClear: true,
            width: '100%'
        });

        $('#labelJualFilter').select2({
            theme: 'bootstrap4',
            placeholder: 'Cari Label Jual',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: 'label_jual_options.php',
                dataType: 'json',
                delay: 300,
                data: function (params) { return { q: params.term || '' }; },
                processResults: function (response) { return { results: response.results || [] }; }
            },
            width: '100%'
        });

        $('#btnFilter').on('click', function () {
            table.ajax.reload();
        });

        $('#cusColorFilter').on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                table.ajax.reload();
            }
        });

        $('#labelJualFilter').on('select2:select select2:clear', function () {
            table.ajax.reload();
        });

        // Handle Export Button
        $('#btnExport').click(async function () {
            const choice = await Swal.fire({
                title: 'Pilih Jenis Export',
                text: 'Data mana yang ingin dimasukkan ke file Excel?',
                icon: 'question',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: '<i class="fas fa-list"></i> Ringkasan Saja',
                denyButtonText: '<i class="fas fa-flask"></i> Beserta Detail Resep',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#17a2b8',
                denyButtonColor: '#28a745'
            });
            if (choice.isDismissed) return;

            let includeDetails = choice.isDenied ? '1' : '0';
            let startDate = $('#startDate').val();
            let endDate = $('#endDate').val();
            let statusFilter = $('#statusFilter').val() || [];
            let cusColorFilter = $('#cusColorFilter').val().trim();
            let labelJualFilter = $('#labelJualFilter').val() || [];

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
            let url = `export_resep_excel.php?startDate=${encodeURIComponent(startDate)}&endDate=${encodeURIComponent(endDate)}&statusFilter=${encodeURIComponent(statusFilter.join(','))}&cusColorFilter=${encodeURIComponent(cusColorFilter)}&labelJualFilter=${encodeURIComponent(labelJualFilter.join(','))}&includeDetails=${includeDetails}`;

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
                    var filename = (includeDetails === '1' ? 'Data_Resep_Detail_' : 'Data_Resep_Ringkasan_') + new Date().getTime() + '.xlsx';

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
