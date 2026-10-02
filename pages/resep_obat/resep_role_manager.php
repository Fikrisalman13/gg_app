<?php
// pages/resep_obat/resep_role_manager.php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php'); exit;
}

require_once __DIR__ . '/../../koneksi.php';

/* JSON endpoint: get field settings for subbag — must be before any output */
if (isset($_GET['action']) && $_GET['action'] === 'get_trustee' && isset($_GET['subbag_id'])) {
    $sid = (int)$_GET['subbag_id'];
    $res = [];
    $q = sqlsrv_query($conn, "SELECT field_key, is_readonly FROM dbo.resep_field_trustee WHERE subbag_id=?", [$sid]);
    if ($q) while ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $res[$r['field_key']] = (int)$r['is_readonly'];
    header('Content-Type: application/json');
    echo json_encode(['settings'=>$res]);
    exit;
}

include '../../includes/header.php';
include '../../includes/sidebar.php';

$themeColor = $_SESSION['Theme'] ?? 'primary';

// Load groups for member tab dropdown
$groupsForSelect = [];
$gStmt = sqlsrv_query($conn, "SELECT id, group_name, role_type FROM dbo.resep_obat_groups ORDER BY group_name ASC");
if ($gStmt) {
    while ($r = sqlsrv_fetch_array($gStmt, SQLSRV_FETCH_ASSOC)) {
        $groupsForSelect[] = $r;
    }
}

/* --- Trustee Field: Load sub bagian --- */
$subbagList = [];
$s = sqlsrv_query($conn, "SELECT s.id_subbag, s.subbag, b.bagian, d.dept
    FROM dbo.m_subbag s
    LEFT JOIN dbo.m_bag b ON s.id_bag = b.id_bag
    LEFT JOIN dbo.m_dept d ON b.id_dept = d.id_dept
    ORDER BY d.dept, b.bagian, s.subbag");
if ($s) while ($r = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC)) $subbagList[] = $r;
?>

<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">

<style>
.badge-lab        { background:#17a2b8; color:#fff; }
.badge-production { background:#fd7e14; color:#fff; }
.badge-both       { background:#6f42c1; color:#fff; }
.tab-content-panel { display:none; }
.tab-content-panel.active { display:block; }
.member-table td, .member-table th { vertical-align:middle; font-size:0.92rem; }
.table-permission th, .table-permission td { vertical-align: middle; }
.table-permission .field-name { font-weight: 500; }
.table-permission .form-check { padding-left: 2.5rem; }
.badge-readonly { background:#dc3545; }
.badge-editable { background:#28a745; }
</style>

<div class="content-wrapper">
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Role Manager - Resep Obat</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                    <li class="breadcrumb-item"><a href="list_resep.php">Resep</a></li>
                    <li class="breadcrumb-item active">Role Manager</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
<div class="container-fluid">
<div class="card">
    <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <h3 class="card-title"><i class="fas fa-users-cog mr-1"></i> Manajemen Grup & Anggota</h3>
    </div>
    <div class="card-body">
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="roleTabs">
            <li class="nav-item">
                <a class="nav-link active" data-target="tabGrup" href="#"><i class="fas fa-layer-group mr-1"></i> Grup</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-target="tabAnggota" href="#"><i class="fas fa-user-friends mr-1"></i> Anggota</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-target="tabTrustee" href="#"><i class="fas fa-shield-alt mr-1"></i> Trustee Field</a>
            </li>
        </ul>

        <!-- Tab: Grup -->
        <div id="tabGrup" class="tab-content-panel active">
            <div class="mb-3">
                <button class="btn btn-success btn-sm" id="btnAddGroup">
                    <i class="fas fa-plus mr-1"></i> Tambah Grup
                </button>
            </div>
            <div class="table-responsive">
                <table id="groupTable" class="table table-bordered table-hover table-sm w-100">
                    <thead class="thead-light">
                        <tr>
                            <th>No</th>
                            <th>Nama Grup</th>
                            <th>Tipe Role</th>
                            <th class="text-center">Jumlah Anggota</th>
                            <th>Akses Experiment</th>
                            <th>Terakhir Diubah</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Anggota -->
        <div id="tabAnggota" class="tab-content-panel">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label>Pilih Grup</label>
                    <select id="selectGroupForMember" class="form-control form-control-sm">
                        <option value="">-- Pilih Grup --</option>
                        <?php foreach ($groupsForSelect as $g): ?>
                        <option value="<?= (int)$g['id'] ?>" data-role="<?= htmlspecialchars($g['role_type']) ?>">
                            <?= htmlspecialchars($g['group_name']) ?> (<?= htmlspecialchars($g['role_type']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-success btn-sm mr-2" id="btnAddMember" disabled>
                        <i class="fas fa-user-plus mr-1"></i> Tambah Anggota
                    </button>
                    <button class="btn btn-secondary btn-sm" id="btnRefreshMembers" disabled>
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>

            <div id="memberGrupInfo" class="mb-2" style="display:none;">
                <span class="badge" id="memberRoleBadge"></span>
                <span id="memberGrupName" class="font-weight-bold ml-1"></span>
            </div>

            <div class="table-responsive">
                <table class="member-table table table-bordered table-hover table-sm w-100">
                    <thead class="thead-light">
                        <tr>
                            <th>No</th>
                            <th>Username</th>
                            <th>Nama</th>
                            <th>Ditambahkan Oleh</th>
                            <th>Tanggal</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="memberTableBody">
                        <tr><td colspan="5" class="text-center text-muted">Pilih grup terlebih dahulu.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Trustee Field -->
        <div id="tabTrustee" class="tab-content-panel">
            <div class="card card-<?= htmlspecialchars($themeColor) ?>">
                <div class="card-body">
                    <div class="form-inline mb-3">
                        <label class="mr-2">Pilih Sub Bagian:</label>
                        <select id="selectTrusteeSubbag" class="form-control" style="min-width:250px">
                            <option value="">-- Pilih Sub Bagian --</option>
                            <?php foreach ($subbagList as $sb): ?>
                                <option value="<?= (int)$sb['id_subbag'] ?>"><?= htmlspecialchars($sb['subbag'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm ml-2" id="btnResetTrustee"><i class="fas fa-times"></i> Reset</button>
                    </div>
                    <div id="trusteePanel">
                        <div class="alert alert-info"><i class="fas fa-info-circle"></i> Pilih sub bagian terlebih dahulu untuk mengatur field read-only.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</section>
</div>

<!-- Modal: Tambah / Edit Grup -->
<div class="modal fade" id="modalGrup" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title" id="modalGrupTitle">Tambah Grup</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="grupId">
                <div class="form-group">
                    <label>Nama Grup <span class="text-danger">*</span></label>
                    <input type="text" id="grupName" class="form-control" maxlength="100" placeholder="Contoh: Tim Lab QC">
                </div>
                <div class="form-group">
                    <label>Tipe Role <span class="text-danger">*</span></label>
                    <select id="grupRole" class="form-control">
                        <option value="">-- Pilih --</option>
                        <option value="LAB">LAB</option>
                        <option value="PRODUCTION">PRODUCTION</option>
                        <option value="BOTH">LAB &amp; PRODUCTION</option>
                        <option value="KABAG">KABAG (Lihat Harga)</option>
                        <option value="LAB_APPROVAL_EXPERIMENT">Lab Approval Experiment</option>
                        <option value="PPC">PPC</option>
                        <option value="QC">QC</option>
                    </select>
                    <small class="form-text text-muted">
                        Role <b>KABAG</b> dipakai untuk melihat harga/cost.
                        Role <b>Lab Approval Experiment</b> hanya memunculkan tombol Approve/Unapprove
                        di halaman View Resep Experiment tanpa membuka kolom harga.
                    </small>
                </div>
                <div class="form-group">
                    <label>Akses Data Experiment <span class="text-danger">*</span></label>
                    <select id="experimentViewScope" class="form-control">
                        <option value="ALL">Semua Data</option>
                        <option value="APPROVED_ONLY">Approved Saja</option>
                        <option value="PROCESS_ONLY">Process Saja</option>
                    </select>
                    <small class="form-text text-muted">Pilih <b>Approved Saja</b> untuk role seperti PPC, <b>Process Saja</b> untuk QC.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-<?= htmlspecialchars($themeColor) ?> btn-sm" id="btnSaveGroup">
                    <i class="fas fa-save mr-1"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Tambah Anggota -->
<div class="modal fade" id="modalMember" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                <h5 class="modal-title">Tambah Anggota Grup</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Pilih User <span class="text-danger">*</span></label>
                    <select id="selectUser" class="form-control form-control-sm w-100" style="width:100%"></select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success btn-sm" id="btnSaveMember">
                    <i class="fas fa-user-plus mr-1"></i> Tambah
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(function(){
const apiUrl = 'resep_role_manager_api.php';

function toast(icon, msg) {
    Swal.fire({ icon, title: msg, timer: 2000, showConfirmButton: false });
}

// =====================
// TABS
// =====================
$('#roleTabs .nav-link').on('click', function(e) {
    e.preventDefault();
    $('#roleTabs .nav-link').removeClass('active');
    $(this).addClass('active');
    const target = $(this).data('target');
    $('.tab-content-panel').removeClass('active');
    $('#' + target).addClass('active');
});

// =====================
// GROUP TABLE (DataTables server-side)
// =====================
const groupTable = $('#groupTable').DataTable({
    processing: true,
    serverSide: true,
    ajax: { url: apiUrl, type: 'POST', data: d => { d.action = 'list_groups'; } },
    columns: [
        { data: null, orderable: false, render: (_, __, r, m) => m.row + m.settings._iDisplayStart + 1 },
        { data: 'group_name' },
        { data: 'role_type', render: v => {
            let cls = 'badge-secondary';
            if (v === 'LAB') cls = 'badge-lab';
            else if (v === 'PRODUCTION') cls = 'badge-production';
            else if (v === 'BOTH') cls = 'badge-both';
            return `<span class="badge ${cls}">${v === 'BOTH' ? 'LAB & PROD' : v}</span>`;
        }},
        { data: 'member_count', className: 'text-center' },
        { data: 'experiment_view_scope', render: v => {
            if (v === 'APPROVED_ONLY') return '<span class="badge badge-success">Approved Saja</span>';
            if (v === 'PROCESS_ONLY') return '<span class="badge badge-warning">Process Saja</span>';
            return '<span class="badge badge-secondary">Semua Data</span>';
        }},
        { data: 'update_at', render: (v, _, r) => `${v}<br><small class="text-muted">${r.update_by}</small>` },
        { data: 'id', orderable: false, render: (id, _, r) =>
            `<button class="btn btn-warning btn-xs mr-1 btn-edit-group" data-id="${id}" data-name="${r.group_name}" data-role="${r.role_type}" data-scope="${r.experiment_view_scope || 'ALL'}"><i class="fas fa-edit"></i></button>
             <button class="btn btn-danger btn-xs btn-del-group" data-id="${id}" data-name="${r.group_name}"><i class="fas fa-trash"></i></button>`
        },
    ],
    language: { processing: 'Memuat...', zeroRecords: 'Tidak ada data.' },
    order: [[0, 'asc']]
});

// Add Group
$('#btnAddGroup').on('click', function() {
    $('#grupId').val('');
    $('#grupName').val('');
    $('#grupRole').val('');
    $('#experimentViewScope').val('ALL');
    $('#modalGrupTitle').text('Tambah Grup');
    $('#modalGrup').modal('show');
});

// Edit Group
$(document).on('click', '.btn-edit-group', function() {
    $('#grupId').val($(this).data('id'));
    $('#grupName').val($(this).data('name'));
    $('#grupRole').val($(this).data('role'));
    $('#experimentViewScope').val($(this).data('scope') || 'ALL');
    $('#modalGrupTitle').text('Edit Grup');
    $('#modalGrup').modal('show');
});

// Delete Group
$(document).on('click', '.btn-del-group', function() {
    const id = $(this).data('id');
    const name = $(this).data('name');
    Swal.fire({
        title: `Hapus grup "${name}"?`,
        text: 'Grup tidak boleh memiliki anggota.',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#d33', cancelButtonText: 'Batal', confirmButtonText: 'Ya, Hapus'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post(apiUrl, { action: 'delete_group', id }, res => {
            if (res.success) { toast('success', res.message); groupTable.ajax.reload(); refreshGroupSelect(); }
            else toast('error', res.message);
        }, 'json');
    });
});

// Save Group
$('#btnSaveGroup').on('click', function() {
    const id   = $('#grupId').val();
    const name = $.trim($('#grupName').val());
    const role = $('#grupRole').val();
    const scope = $('#experimentViewScope').val() || 'ALL';
    if (!name || !role) { toast('warning', 'Nama dan tipe role wajib diisi.'); return; }

    const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');
    const action = id ? 'edit_group' : 'add_group';
    $.post(apiUrl, { action, id, group_name: name, role_type: role, experiment_view_scope: scope }, res => {
        $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
        if (res.success) {
            $('#modalGrup').modal('hide');
            toast('success', res.message);
            groupTable.ajax.reload();
            refreshGroupSelect();
        } else toast('error', res.message);
    }, 'json');
});

// =====================
// MEMBER TAB
// =====================
let currentGroupId = null;

function refreshGroupSelect() {
    $.getJSON(apiUrl, { action: 'list_groups', start: 0, length: 1000, 'search[value]': '' }, res => {
        const $sel = $('#selectGroupForMember');
        const cur = $sel.val();
        $sel.find('option:not(:first)').remove();
        (res.data || []).forEach(g => {
            $sel.append(`<option value="${g.id}" data-role="${g.role_type}">${g.group_name} (${g.role_type})</option>`);
        });
        $sel.val(cur);
    });
}

$('#selectGroupForMember').on('change', function() {
    currentGroupId = $(this).val() || null;
    $('#btnAddMember, #btnRefreshMembers').prop('disabled', !currentGroupId);
    if (currentGroupId) {
        const opt = $(this).find(':selected');
        const role = opt.data('role');
        const name = opt.text().split(' (')[0];
        const cls  = role === 'LAB' ? 'badge-lab' : (role === 'PRODUCTION' ? 'badge-production' : 'badge-both');
        $('#memberRoleBadge').attr('class', 'badge ' + cls).text(role === 'BOTH' ? 'LAB & PROD' : role);
        $('#memberGrupName').text(name);
        $('#memberGrupInfo').show();
        loadMembers(currentGroupId);
    } else {
        $('#memberGrupInfo').hide();
        $('#memberTableBody').html('<tr><td colspan="5" class="text-center text-muted">Pilih grup terlebih dahulu.</td></tr>');
    }
});

$('#btnRefreshMembers').on('click', () => { if (currentGroupId) loadMembers(currentGroupId); });

function loadMembers(groupId) {
    $('#memberTableBody').html('<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i></td></tr>');
    $.getJSON(apiUrl, { action: 'list_members', group_id: groupId }, res => {
        if (!res.success || !res.data.length) {
            $('#memberTableBody').html('<tr><td colspan="5" class="text-center text-muted">Belum ada anggota.</td></tr>');
            return;
        }
        let rows = '';
        res.data.forEach((m, i) => {
            rows += `<tr>
                <td>${i+1}</td>
                <td><i class="fas fa-user mr-1 text-muted"></i>${m.username}</td>
                <td>${m.nama_lengkap || '<span class="text-muted">-</span>'}</td>
                <td>${m.created_by}</td>
                <td>${m.created_at}</td>
                <td class="text-center">
                    <button class="btn btn-danger btn-xs btn-remove-member" data-id="${m.id}" data-uname="${m.username}">
                        <i class="fas fa-user-minus"></i>
                    </button>
                </td>
            </tr>`;
        });
        $('#memberTableBody').html(rows);
    });
}

// Remove member
$(document).on('click', '.btn-remove-member', function() {
    const id = $(this).data('id');
    const uname = $(this).data('uname');
    Swal.fire({
        title: `Keluarkan "${uname}" dari grup?`, icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#d33',
        cancelButtonText: 'Batal', confirmButtonText: 'Ya, Keluarkan'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.post(apiUrl, { action: 'remove_member', id }, res => {
            if (res.success) { toast('success', res.message); loadMembers(currentGroupId); }
            else toast('error', res.message);
        }, 'json');
    });
});

// Add Member Modal
$('#btnAddMember').on('click', function() {
    if (!currentGroupId) return;
    if ($('#selectUser').hasClass('select2-hidden-accessible')) {
        $('#selectUser').select2('destroy');
    }
    $('#selectUser').html('').select2({
        dropdownParent: $('#modalMember'),
        theme: 'bootstrap4',
        placeholder: 'Cari username atau nama...',
        minimumInputLength: 1,
        ajax: {
            url: apiUrl,
            dataType: 'json',
            delay: 300,
            data: params => ({ action: 'search_users', q: params.term }),
            processResults: data => ({ results: data.results })
        }
    });
    $('#modalMember').modal('show');
});

$('#btnSaveMember').on('click', function() {
    const username = $('#selectUser').val();
    if (!username) { toast('warning', 'Pilih user terlebih dahulu.'); return; }
    const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>');
    $.post(apiUrl, { action: 'add_member', group_id: currentGroupId, username }, res => {
        $btn.prop('disabled', false).html('<i class="fas fa-user-plus mr-1"></i> Tambah');
        if (res.success) {
            $('#modalMember').modal('hide');
            toast('success', res.message);
            loadMembers(currentGroupId);
        } else toast('error', res.message);
    }, 'json');
});

/* ===================== */
/* TRUSTEE FIELD TAB     */
/* ===================== */
const FIELD_MAP = {
    'kode_grey':'Kode Grey','kode_warna':'Kode Warna','soi':'SOI','no_cp':'No CP','resep_prod_code':'Resep Prod Code',
    'cus_color':'Cus Color','lot_no':'Lot No','plan_qty':'Plan Qty','weight':'Weight','vlot':'Vlot',
    'catatan':'Catatan','lampiran':'Lampiran','detail_items':'Detail Resep Items',
    'detail_kode':'  └ Kode Item','detail_qty':'  └ Qty','detail_cf':'  └ Cf','detail_uom_cf':'  └ Uom Cf',
    'lab_machine':'Mesin Lab','lab_param':'  └ Parameter Mesin','lab_data':'Data Lab'
};
const FIELD_KEYS = Object.keys(FIELD_MAP);

function renderTrusteeTable(settings, subbagId) {
    var html = '<form id="trusteeForm"><input type="hidden" name="subbag_id" value="'+subbagId+'"><div class="table-responsive"><table class="table table-bordered table-hover table-sm table-permission"><thead class="thead-light"><tr><th width="50" class="text-center">#</th><th>Field</th><th>Identifier</th><th width="160" class="text-center">Akses</th><th class="text-center">Aksi Cepat</th></tr></thead><tbody>';
    var no = 1;
    FIELD_KEYS.forEach(function(key){
        var ro = settings[key] === 1;
        var label = FIELD_MAP[key];
        html += '<tr><td class="text-center">'+(no++)+'</td>';
        html += '<td class="field-name">'+label+'</td>';
        html += '<td><code>'+key+'</code></td>';
        html += '<td class="text-center"><div class="form-check d-inline-block">';
        html += '<input class="form-check-input cb-field" type="checkbox" id="cb_'+key+'" data-key="'+key+'" '+(ro?'checked':'')+'>';
        html += '<label class="form-check-label" for="cb_'+key+'"><span class="badge '+(ro?'badge-readonly':'badge-editable')+'" id="badge_'+key+'">'+(ro?'Read Only':'Editable')+'</span></label>';
        html += '</div></td>';
        html += '<td class="text-center"><button type="button" class="btn btn-xs btn-outline-danger btn-set-ro" data-key="'+key+'">RO</button> <button type="button" class="btn btn-xs btn-outline-success btn-set-edit" data-key="'+key+'">Edit</button></td>';
        html += '</tr>';
    });
    html += '</tbody></table></div><div class="mt-3">';
    html += '<button type="button" class="btn btn-primary" id="btnSaveTrustee"><i class="fas fa-save"></i> Simpan Setting</button> ';
    html += '<button type="button" class="btn btn-warning" id="btnAllRO"><i class="fas fa-lock"></i> Semua Read Only</button> ';
    html += '<button type="button" class="btn btn-success" id="btnAllEdit"><i class="fas fa-unlock"></i> Semua Editable</button>';
    html += '<span class="float-right text-muted small mt-2"><i class="fas fa-info-circle"></i> Admin (GroupId=1) selalu bypass setting ini.</span>';
    html += '</div></form>';
    $('#trusteePanel').html(html);
    bindTrusteeEvents();
}

function refreshBadge($cb){
    var isRO = $cb.is(':checked');
    var $badge = $cb.closest('td').find('.badge');
    $badge.removeClass('badge-readonly badge-editable')
           .addClass(isRO ? 'badge-readonly' : 'badge-editable')
           .text(isRO ? 'Read Only' : 'Editable');
}

function bindTrusteeEvents(){
    $('.cb-field').on('change', function(){ refreshBadge($(this)); });
    $('.btn-set-ro').on('click', function(){
        var key = $(this).data('key');
        var $cb = $('#cb_' + key); $cb.prop('checked', true); refreshBadge($cb);
    });
    $('.btn-set-edit').on('click', function(){
        var key = $(this).data('key');
        var $cb = $('#cb_' + key); $cb.prop('checked', false); refreshBadge($cb);
    });
    $('#btnAllRO').on('click', function(){
        $('.cb-field').each(function(){ $(this).prop('checked', true); refreshBadge($(this)); });
    });
    $('#btnAllEdit').on('click', function(){
        $('.cb-field').each(function(){ $(this).prop('checked', false); refreshBadge($(this)); });
    });
    $('#btnSaveTrustee').on('click', function(){
        var subbagId = $('input[name="subbag_id"]').val();
        var fields = [];
        $('.cb-field').each(function(){
            fields.push({ key: $(this).data('key'), readonly: $(this).is(':checked') ? 1 : 0 });
        });
        Swal.fire({title:'Simpan Setting?',text:'Perubahan akan langsung berlaku untuk user dengan sub bagian ini.',icon:'question',showCancelButton:true,confirmButtonText:'Simpan',cancelButtonText:'Batal'})
          .then(function(r){
            if(!r.isConfirmed) return;
            $.ajax({
              url:'experiment/save_field_trustee.php', type:'POST', dataType:'json',
              data:{ subbag_id: subbagId, fields: JSON.stringify(fields) }
            }).done(function(resp){
              if(resp.status==='success'){ Swal.fire('Sukses',resp.message||'Setting berhasil disimpan.','success'); }
              else { Swal.fire('Gagal',resp.message||'Gagal menyimpan.','error'); }
            }).fail(function(){ Swal.fire('Error','Gagal menghubungi server.','error'); });
          });
    });
}

function loadTrustee(subbagId) {
    $('#trusteePanel').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2 text-muted">Memuat setting...</p></div>');
    $.getJSON('resep_role_manager.php', { action:'get_trustee', subbag_id: subbagId }, function(resp){
        renderTrusteeTable(resp.settings||{}, subbagId);
    }).fail(function(){
        $('#trusteePanel').html('<div class="alert alert-danger">Gagal memuat data.</div>');
    });
}

$('#selectTrusteeSubbag').on('change', function(){
    var val = $(this).val();
    if(val) loadTrustee(val);
    else $('#trusteePanel').html('<div class="alert alert-info"><i class="fas fa-info-circle"></i> Pilih sub bagian terlebih dahulu untuk mengatur field read-only.</div>');
});
$('#btnResetTrustee').on('click', function(){
    $('#selectTrusteeSubbag').val('').trigger('change');
    $('#trusteePanel').html('<div class="alert alert-info"><i class="fas fa-info-circle"></i> Pilih sub bagian terlebih dahulu untuk mengatur field read-only.</div>');
});
$('#selectTrusteeSubbag').select2({theme:'bootstrap4', width:'style'});
});
</script>
