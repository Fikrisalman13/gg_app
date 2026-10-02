<?php
session_start();
require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../koneksi.php';
require_once __DIR__ . '/../experiment_visibility_helper.php';
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
  $_SESSION['error'] = 'Silakan login terlebih dahulu!';
  header('Location: /gg_app/login.php');
  exit;
}

function checkPermissions($conn, $groupId, $menuId)
{
  $stmt = sqlsrv_query($conn, "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId=? AND MenuId=?", [$groupId, $menuId]);
  $p = ['CanView' => 0];
  if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))
    $p = $row;
  if ($stmt)
    sqlsrv_free_stmt($stmt);
  return $p;
}

$permissions = checkPermissions($conn, $_SESSION['GroupId'] ?? 0, 212);
if (($permissions['CanView'] ?? 0) != 1) {
  $_SESSION['error'] = 'Anda tidak memiliki hak untuk melihat halaman ini.';
  header('Location: ../../../dashboard.php');
  exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$createdByOptions = [];
$creatorConditions = [];
$creatorParams = [];
resepExperimentApplyVisibility($creatorConditions, $creatorParams, 'e', $conn);
$creatorWhere = $creatorConditions ? 'WHERE ' . implode(' AND ', $creatorConditions) . " AND NULLIF(LTRIM(RTRIM(e.created_by)), '') IS NOT NULL" : "WHERE NULLIF(LTRIM(RTRIM(e.created_by)), '') IS NOT NULL";
$creatorStmt = sqlsrv_query($conn, "SELECT DISTINCT e.created_by FROM dbo.resep_obat_experiment e $creatorWhere ORDER BY e.created_by", $creatorParams);
while ($creatorStmt && $creatorRow = sqlsrv_fetch_array($creatorStmt, SQLSRV_FETCH_ASSOC)) $createdByOptions[] = trim((string)$creatorRow['created_by']);
if ($creatorStmt) sqlsrv_free_stmt($creatorStmt);
include __DIR__ . '/../../../../includes/header.php';
include __DIR__ . '/../../../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet"
  href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<style>
  .report-toolbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
  }

  .report-filter {
    display: flex;
    align-items: flex-end;
    gap: .75rem;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .report-filter .filter-item {
    min-width: 160px;
  }

  .report-filter label {
    font-size: .8rem;
    font-weight: 600;
    margin-bottom: 2px;
  }

  .cell-main {
    font-weight: 700;
    line-height: 1.2;
  }

  .cell-sub {
    color: #6c757d;
    font-size: .78rem;
    line-height: 1.25;
    margin-top: 2px;
  }

  .badge-soft {
    display: inline-block;
    padding: .25rem .5rem;
    border-radius: .25rem;
    font-size: .75rem;
    font-weight: 700;
  }

  .badge-pass {
    background: #d4edda;
    color: #155724;
  }

  .badge-fail {
    background: #f8d7da;
    color: #721c24;
  }

  .badge-empty {
    background: #f0f0f0;
    color: #888;
  }

  .status-pass, .status-sukses { background: #28a745; color: #fff; }
  .status-fail { background: #dc3545; color: #fff; }
  .status-draft { background: #6c757d; color: #fff; }
  .status-approved { background: #007bff; color: #fff; }
  .status-gagal { background: #fd7e14; color: #fff; }
  .status-process { background: #ffc107; color: #212529; }
  .status-tunggu-cp { background: #17a2b8; color: #fff; }
  .status-tunggu-matching { background: #6f42c1; color: #fff; }

  .erp-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid #ccc;
    border-top-color: #007bff;
    border-radius: 50%;
    animation: erp-spin .6s linear infinite;
  }

  @keyframes erp-spin {
    to {
      transform: rotate(360deg);
    }
  }
</style>
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0">Laporan Hasil Eksperimen</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="../list_experiment.php">Experiment</a></li>
            <li class="breadcrumb-item active">Laporan Hasil Eksperimen</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
          <h3 class="card-title"><i class="fas fa-clipboard-list mr-1"></i> Laporan Hasil Eksperimen</h3>
        </div>
        <div class="card-body">
          <div class="report-toolbar mb-3">
            <div class="report-filter">
              <div class="filter-item"><label for="filterDateFrom">Tgl Match Dari</label><input type="date"
                  id="filterDateFrom" class="form-control form-control-sm"></div>
              <div class="filter-item"><label for="filterDateTo">Tgl Match Sampai</label><input type="date"
                  id="filterDateTo" class="form-control form-control-sm"></div>
              <div class="filter-item"><label for="filterCreatedBy">Created By</label><select
                  id="filterCreatedBy" class="form-control form-control-sm" multiple>
                  <?php foreach ($createdByOptions as $createdBy): ?>
                    <option value="<?= htmlspecialchars($createdBy, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($createdBy) ?></option>
                  <?php endforeach; ?>
                </select></div>
              <div class="filter-item"><label for="filterStatus">Status</label><select
                  id="filterStatus" class="form-control form-control-sm">
                  <option value="">Semua</option>
                  <?php foreach (['Draft', 'Tunggu CP', 'Tunggu Matching', 'Gagal', 'Sukses', 'Process', 'Approved'] as $statusOption): ?>
                    <option value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusOption) ?></option>
                  <?php endforeach; ?>
                </select></div>
              <div class="filter-actions"><button id="btnFilter" class="btn btn-sm btn-primary"><i
                    class="fas fa-filter"></i> Filter</button> <button id="btnReset" class="btn btn-sm btn-secondary"><i
                    class="fas fa-undo"></i> Reset</button> <button id="btnExportExcel"
                  class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Excel</button> <button
                  id="btnExportPdf" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> PDF</button></div>
            </div>
          </div>
          <div class="table-responsive">
            <table id="reportTable" class="table table-bordered table-hover table-sm nowrap" style="width:100%">
              <thead class="thead-light">
                <tr>
                  <th>No</th>
                  <th>Tgl Match</th>
                  <th>Label</th>
                  <th>KodeLab / Warna</th>
                  <th>Status</th>
                  <th>No CP</th>
                  <th>Exp</th>
                  <th>Tgl Planning</th>
                  <th>Tgl Celup Padd</th>
                  <th>Mesin Paddry</th>
                  <th>Planning Qty</th>
                  <th>Aktual Qty</th>
                  <th>Posisi Hari Ini</th>
                  <th>ACC Warna R</th>
                  <th>Keputusan</th>
                  <th>Tindakan QC</th>
                  <th>Catatan QC</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<?php include __DIR__ . '/../../../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
  $(function () {
    $('#filterCreatedBy').select2({
      theme: 'bootstrap4',
      placeholder: 'Semua',
      allowClear: true,
      closeOnSelect: false,
      width: '180px',
      matcher: function (params, data) {
        if ($(data.element).prop('selected')) return null;
        if (!params.term || data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return data;
        return null;
      }
    });
    const esc = s => (s == null ? '' : String(s)).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const statusBadge = s => {
      const value = (s || 'Draft').trim();
      const cls = {
        'Pass': 'status-pass', 'Fail': 'status-fail', 'Draft': 'status-draft',
        'Approved': 'status-approved', 'Sukses': 'status-sukses', 'Gagal': 'status-gagal',
        'Process': 'status-process', 'Tunggu CP': 'status-tunggu-cp',
        'Tunggu Matching': 'status-tunggu-matching'
      }[value] || 'status-draft';
      return `<span class="badge-soft ${cls}">${esc(value)}</span>`;
    };
    const badge = s => {
      const v = (s || '-').trim();
      const cls = v === 'Pass' ? 'badge-pass' : (v === 'Fail' ? 'badge-fail' : 'badge-empty');
      return `<span class="badge-soft ${cls}">${esc(v)}</span>`;
    };
    const qtyCell = d => {
      const v = (d || '-').trim();
      if (v.charAt(0) === '<') return v;
      return v === '-' ? '<span class="d-block text-center">-</span>' : '<span class="d-block text-right">' + esc(v) + '</span>';
    };
    const table = $('#reportTable').DataTable({
      processing: true, serverSide: true, responsive: true, stateSave: true, stateDuration: -1, order: [[1, 'desc']],
      stateLoadParams: function (settings, data) {
        if ((data.columns || []).length !== 18) return false;
      },
      ajax: { url: 'serverside_report.php', data: d => { d.filter_date_from = $('#filterDateFrom').val(); d.filter_date_to = $('#filterDateTo').val(); d.filter_created_by = ($('#filterCreatedBy').val() || []).join('|'); d.filter_status = $('#filterStatus').val() || ''; }, error: xhr => { console.error(xhr.responseText); Swal.fire('Error', 'Gagal memuat laporan.', 'error'); } },
      columns: [
        { data: null, orderable: false, searchable: false, className: 'text-center', render: (d, t, r, m) => m.row + m.settings._iDisplayStart + 1 },
        { data: 'tgl_match', className: 'text-center' },
        { data: 'label', render: d => esc(d || '-') },
        { data: null, render: d => `<div class="cell-main">${esc(d.kode_lab)}</div><div class="cell-sub">${esc(d.warna)}</div>` },
        { data: 'status', className: 'text-center', render: (d, t) => t === 'display' ? statusBadge(d) : (d || 'Draft') },
        { data: 'no_cp', render: d => `<b>${esc(d)}</b>` },
        { data: null, className: 'text-center', render: d => `<div class="cell-main">#${esc(d.experiment_seq || '-')}</div><div class="cell-sub">${esc(d.created_by || '-')}</div>` },
        { data: 'tgl_planning', className: 'text-center' },
        { data: 'tgl_celup_padd', className: 'text-center', render: (d, t) => t === 'display' ? d : (d || '-') },
        { data: 'mesin_paddry', render: d => esc(d || '-').replace(/,\s*/g, '<br>') },
        { data: 'qty', className: 'text-right' },
        { data: 'aktual_qty', className: 'text-nowrap', render: (d, t) => t === 'display' ? qtyCell(d) : (d || '-') },
        { data: 'posisi_hari_ini', render: (d, t) => t === 'display' ? d : (d || '-') },
        {
          data: null, className: 'text-center', render: (d, t) => {
            if (t !== 'display') return (d.acc_warna_r_status || '-') + ' ' + (d.acc_warna_r_tgl || '-');
            const vs = (d.acc_warna_r_status || '-').trim();
            const vt = (d.acc_warna_r_tgl || '-').trim();
            if (vs.charAt(0) === '<') return vs; // spinner in status
            if (vt.charAt(0) === '<') return vt; // spinner in tgl
            const cls = vs === 'Pass' ? 'badge-pass' : vs === 'Fail' ? 'badge-fail' : 'badge-empty';
            return '<div><span class="badge-soft ' + cls + '">' + esc(vs) + '</span></div><div class="cell-sub">' + esc(vt) + '</div>';
          }
        },
        { data: 'keputusan', className: 'text-center', render: badge },
        { data: 'tindakan', className: 'text-center', render: d => esc(d || '-') },
        { data: 'qc_catatan', render: d => `<div class="cell-sub">${esc(d || '-')}</div>` },
        { data: 'id', orderable: false, searchable: false, className: 'text-center', render: id => `<a href="../view_resep.php?resep_id=${id}&from=report" class="btn btn-info btn-sm" title="Detail"><i class="fas fa-eye"></i></a>` }
      ],
      language: { processing: 'Memuat data...', search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data', zeroRecords: 'Tidak ada data', info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data', infoEmpty: 'Tidak ada data', paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' } }
    });
    function getResponsiveChildRow(trNode) {
      var next = trNode ? trNode.nextElementSibling : null;
      return next && next.classList.contains('child') ? next : null;
    }

    function rowHasErpSpinner(trNode) {
      var childRow = getResponsiveChildRow(trNode);
      return !!(
        (trNode && trNode.querySelector('.erp-spinner')) ||
        (childRow && childRow.querySelector('.erp-spinner'))
      );
    }

    function applyErpRow(trNode, info) {
      function applyIn(container) {
        if (!container) return;
        var spTgl = container.querySelector('[data-erp="tgl"]');
        var spAktualQty = container.querySelector('[data-erp="aktual_qty"]');
        var spPos = container.querySelector('[data-erp="pos"]');
        var spAcc = container.querySelector('[data-erp="acc"]');
        if (spTgl) {
          var t = info.tgl_celup_padd || '-';
          spTgl.outerHTML = t.charAt(0) === '<' ? t : esc(t);
        }
        if (spAktualQty) {
          var q = info.aktual_qty || '-';
          spAktualQty.outerHTML = q.charAt(0) === '<' ? q : qtyCell(q);
        }
        if (spPos) {
          var p = info.posisi_hari_ini || '-';
          spPos.outerHTML = p.charAt(0) === '<' ? p : esc(p);
        }
        if (spAcc) {
          var st = (info.acc_warna_r_status || '-').trim();
          var dt = (info.acc_warna_r_tgl || '-').trim();
          var html;
          if (st.charAt(0) === '<') { html = st; }
          else if (dt.charAt(0) === '<') { html = dt; }
          else {
            var cls = st === 'Pass' ? 'badge-pass' : st === 'Fail' ? 'badge-fail' : 'badge-empty';
            html = '<div><span class="badge-soft ' + cls + '">' + esc(st) + '</span></div><div class="cell-sub">' + esc(dt) + '</div>';
          }
          spAcc.outerHTML = html;
        }
      }
      applyIn(trNode);
      applyIn(getResponsiveChildRow(trNode));
    }

    const erpCache = {};
    let erpLoading = false;

    const loadErpData = () => {
      const toFetch = [];
      table.rows({ page: 'current' }).every(function () {
        var row = this.data();
        var noCp = String(row.no_cp || '').toUpperCase();
        if (!noCp || noCp === '-') return;
        var trNode = this.node();
        if (!rowHasErpSpinner(trNode)) return;
        var item = { node: trNode, noCp: noCp };
        if (erpCache[noCp]) {
          applyErpRow(item.node, erpCache[noCp]);
        } else {
          toFetch.push(item);
        }
      });
      if (!toFetch.length || erpLoading) return;
      var cpSet = new Set();
      var fetchItems = [];
      toFetch.forEach(function (item) {
        if (!cpSet.has(item.noCp)) {
          cpSet.add(item.noCp);
          fetchItems.push(item);
        }
      });
      erpLoading = true;
      $.ajax({
        url: 'ajax_erp_data.php',
        method: 'POST',
        dataType: 'json',
        data: { cp: Array.from(cpSet) },
        timeout: 15000,
        success: function (res) {
          var map = (res && res.data) ? res.data : {};
          fetchItems.forEach(function (item) {
            var info = map[item.noCp];
            if (!info) {
              info = { tgl_celup_padd: '-', aktual_qty: '-', posisi_hari_ini: '-', acc_warna_r_status: '-', acc_warna_r_tgl: '-' };
            }
            erpCache[item.noCp] = info;
            applyErpRow(item.node, info);
          });
        },
        error: function () {
          var fallback = { tgl_celup_padd: '-', aktual_qty: '-', posisi_hari_ini: '-', acc_warna_r_status: '-', acc_warna_r_tgl: '-' };
          fetchItems.forEach(function (item) {
            erpCache[item.noCp] = fallback;
            applyErpRow(item.node, fallback);
          });
        },
        complete: function () { erpLoading = false; }
      });
    };
    table.on('draw.dt', function () { setTimeout(loadErpData, 80); });
    table.on('responsive-display.dt', function () { setTimeout(loadErpData, 0); });
    loadErpData();
    $('#btnFilter').on('click', () => table.draw());
    $('#btnReset').on('click', () => { $('#filterDateFrom,#filterDateTo,#filterStatus').val(''); $('#filterCreatedBy').val([]).trigger('change'); table.draw(); });
    const exportUrl = file => {
      const params = new URLSearchParams({
        filter_date_from: $('#filterDateFrom').val() || '',
        filter_date_to: $('#filterDateTo').val() || '',
        filter_created_by: ($('#filterCreatedBy').val() || []).join('|'),
        filter_status: $('#filterStatus').val() || '',
        search: table.search() || ''
      });
      window.open(file + '?' + params.toString(), '_blank');
    };
    $('#btnExportExcel').on('click', () => exportUrl('export_excel.php'));
    $('#btnExportPdf').on('click', () => exportUrl('export_pdf.php'));
  });
</script>
