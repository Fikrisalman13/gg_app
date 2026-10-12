<?php
session_start();
require_once __DIR__ . "/../../../koneksi.php";
if (!isset($_SESSION["UserName"])) {
    header("Location:/gg_app/login.php");
    exit();
}
$themeColor = $_SESSION["Theme"] ?? "primary";
include "../../../includes/header.php";
include "../../../includes/sidebar.php";
?>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h1><i class="fas fa-clipboard-check mr-2"></i>Audit Detail Resep V2</h1>
          <p class="text-muted mb-0">Bandingkan formula detail resep V2 (Multi-Routing Table) dengan material Bon aktual ProInt berdasarkan No CP.</p>
        </div>
        <div>
          <a href="input_resep.php" class="btn btn-outline-primary mr-1"><i class="fas fa-plus mr-1"></i> Input Resep V2</a>
          <a href="list_resep.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> List Resep</a>
        </div>
      </div>
    </div>
  </section>
  <section class="content">
    <div class="container-fluid">
      <div class="card shadow-sm">
        <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
          <h3 class="card-title"><i class="fas fa-search-plus mr-1"></i> Filter & Pengawasan Resep V2</h3>
        </div>
        <div class="card-body">
          <div class="row align-items-end mb-3">
            <div class="col-lg-2 col-md-4 mb-2">
              <label for="auditCp">No CP</label>
              <input id="auditCp" class="form-control" maxlength="25" placeholder="Contoh D26I0829..." />
            </div>
            <div class="col-lg-2 col-md-4 mb-2">
              <label for="auditLabelJual">Label Jual</label>
              <select id="auditLabelJual" class="form-control" disabled>
                <option value="">Memuat label...</option>
              </select>
            </div>
            <div class="col-lg-2 col-md-4 mb-2">
              <label for="auditRoute">Routing Tabel</label>
              <select id="auditRoute" class="form-control">
                <option value="">Semua Routing</option>
              </select>
            </div>
            <div class="col-lg-2 col-md-4 mb-2">
              <label for="auditSource">Sumber Resep</label>
              <select id="auditSource" class="form-control">
                <option value="">Semua</option>
                <option value="PROINT">PROINT (Otomatis)</option>
                <option value="MANUAL">MANUAL</option>
              </select>
            </div>
            <div class="col-lg-1 col-md-3 mb-2">
              <label for="auditStart">Mulai</label>
              <input type="date" id="auditStart" class="form-control" />
            </div>
            <div class="col-lg-1 col-md-3 mb-2">
              <label for="auditEnd">Selesai</label>
              <input type="date" id="auditEnd" class="form-control" />
            </div>
            <div class="col-lg-2 col-md-6 mb-2 d-flex">
              <button id="auditFilter" class="btn btn-primary flex-fill" title="Terapkan filter">
                <i class="fas fa-filter mr-1"></i> Filter
              </button>
              <button id="auditExport" class="btn btn-success ml-2" title="Export audit ke Excel">
                <i class="fas fa-file-excel mr-1"></i> Excel
              </button>
            </div>
          </div>
          <div class="audit-summary">
            <div><span>Cocok</span><b id="sumMatch" class="text-success">0</b></div>
            <div><span>Berbeda</span><b id="sumDiff" class="text-danger">0</b></div>
            <button type="button" class="recipe-audit-low-stock-tile" id="recipe-audit-low-stock-open"
              aria-controls="recipe-audit-low-stock-modal" aria-label="Buka ringkasan material di bawah target minimum">
              <span>Di Bawah Target</span><b id="recipe-audit-low-stock-count">0 Material</b>
              <small id="recipe-audit-low-stock-progress">Menunggu audit</small>
            </button>
            <div><span>Belum Ada Bon</span><b id="sumMissing" class="text-secondary">0</b></div>
            <div><span>Total Resep</span><b id="sumTotal">0</b></div>
          </div>
          <section class="recipe-audit-low-stock-preview d-none" id="recipe-audit-low-stock-preview"
            aria-labelledby="recipe-audit-low-stock-preview-title">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <strong id="recipe-audit-low-stock-preview-title"><i class="fas fa-exclamation-triangle text-warning mr-1"></i>Material Paling Kritis (&lt; 500 KG)</strong>
              <button type="button" class="btn btn-sm btn-outline-danger" data-action="open-low-stock-summary">Lihat Semua</button>
            </div>
            <div id="recipe-audit-low-stock-preview-list"></div>
          </section>
          <div class="alert alert-info py-2 mb-3">
            <i class="fas fa-info-circle mr-1"></i> <b>Logika Audit V2:</b> Memeriksa seluruh material (Dyestuff, Chemical, Auxiliary) pada setiap <b>Routing Table</b> lokal (Paddry, Alkali, Resin, dll.) terhadap Bon Permintaan Obat ProInt aktual. Mendukung pencocokan otomatis cerdas dan komparasi kode ProInt langsung.
          </div>
          <div id="auditProgress" class="mb-3 d-none">
            <div class="d-flex justify-content-between mb-1">
              <small id="auditProgressText">Memeriksa data ProInt...</small><small id="auditProgressCount">0/0</small>
            </div>
            <div class="progress" style="height: 8px">
              <div
                id="auditProgressBar"
                class="progress-bar progress-bar-striped progress-bar-animated bg-<?= htmlspecialchars($themeColor) ?>"
                style="width: 0%"
              ></div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover table-bordered table-sm align-middle">
              <thead class="thead-light">
                <tr>
                  <th style="min-width: 140px;">No CP</th>
                  <th>Label Jual</th>
                  <th>Cus Color</th>
                  <th>Stock Warehouse</th>
                  <th style="min-width: 160px;">Routing Tabel Lokal</th>
                  <th>Sumber</th>
                  <th>Status Resep</th>
                  <th style="min-width: 90px;">Dibuat</th>
                  <th style="min-width: 140px;">Bon ProInt</th>
                  <th style="min-width: 150px;">Status Audit</th>
                  <th style="min-width: 130px;">Aksi</th>
                </tr>
              </thead>
              <tbody id="auditRows"></tbody>
            </table>
          </div>
          <nav><ul class="pagination justify-content-end" id="auditPager"></ul></nav>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Modal Perbandingan Detail Multi-Tabel V2 -->
<div class="modal fade" id="auditDetailModal" tabindex="-1" aria-labelledby="auditDetailTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white align-items-center">
        <div>
          <h5 class="modal-title mb-0" id="auditDetailTitle"><i class="fas fa-balance-scale mr-2"></i>Perbandingan Detail Formula Resep V2</h5>
          <small id="auditDetailSubtitle" class="text-white-50"></small>
        </div>
        <div class="d-flex align-items-center">
          <a id="auditBtnOpenRecipe" href="#" target="_blank" class="btn btn-warning btn-sm font-weight-bold mr-3">
            <i class="fas fa-edit mr-1"></i> Buka / Edit Resep
          </a>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">&times;</button>
        </div>
      </div>
      <div class="modal-body" id="auditDetailModalBody">
        <div id="auditDetailHeaderSummary" class="mb-3"></div>
        <div id="auditTablesContainer"></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Low Stock Material -->
<div class="modal fade" id="recipe-audit-low-stock-modal" tabindex="-1"
  aria-labelledby="recipe-audit-low-stock-title" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <div>
          <small class="text-uppercase">Pengingat Pembelian</small>
          <h5 class="modal-title" id="recipe-audit-low-stock-title">Material di Bawah Target Minimum</h5>
        </div>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">&times;</button>
      </div>
      <div class="modal-body">
        <label class="sr-only" for="recipe-audit-low-stock-search">Cari material, kode ProInt, atau gudang</label>
        <input type="search" class="form-control mb-3" id="recipe-audit-low-stock-search"
          placeholder="Cari kode lokal, kode ProInt, nama material, atau gudang...">
        <div id="recipe-audit-low-stock-modal-status" class="text-muted mb-2"></div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered table-hover recipe-audit-low-stock-table">
            <thead><tr><th>Kode Lokal</th><th>Kode ProInt</th><th>Material</th><th class="text-right">Total KG</th><th class="text-right">Target KG</th><th class="text-right">Kurang KG</th><th>Lokasi Stok</th></tr></thead>
            <tbody id="recipe-audit-low-stock-modal-rows"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Stock Detail -->
<div class="modal fade" id="stockDetailModal" tabindex="-1" aria-labelledby="stockDetailTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content stock-modal-content">
      <div class="modal-header stock-modal-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
        <div>
          <small class="stock-modal-eyebrow">STOCK WAREHOUSE</small>
          <h5 class="modal-title" id="stockDetailTitle">Detail Stock Resep</h5>
          <span class="stock-modal-subtitle" id="stockDetailCp"></span>
        </div>
        <button class="close text-white" data-dismiss="modal" aria-label="Tutup detail stock">&times;</button>
      </div>
      <div class="modal-body stock-modal-body" id="stockDetailBody"></div>
    </div>
  </div>
</div>

<?php include "../../../includes/footer.php"; ?>

<style>
  .audit-summary {
    display: grid;
    grid-template-columns: repeat(5, minmax(130px, 1fr));
    gap: 10px;
    margin-bottom: 16px;
  }

  .audit-summary div,
  .recipe-audit-low-stock-tile {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px;
    background: linear-gradient(135deg, #fff, #f7f9fc);
  }

  .recipe-audit-low-stock-tile {
    cursor: pointer;
    text-align: left;
    transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
  }

  .recipe-audit-low-stock-tile:hover,
  .recipe-audit-low-stock-tile:focus {
    border-color: #ef4444;
    box-shadow: 0 8px 20px rgba(239, 68, 68, .12);
    outline: none;
    transform: translateY(-1px);
  }

  .recipe-audit-low-stock-tile small {
    color: #64748b;
    display: block;
    font-size: .72rem;
  }

  .audit-summary span {
    display: block;
    color: #6b7280;
    font-size: 0.8rem;
  }

  .audit-summary b {
    display: block;
    font-size: 1.5rem;
  }

  .recipe-audit-low-stock-preview {
    background: linear-gradient(135deg, #fff7ed, #fff);
    border: 1px solid #fed7aa;
    border-radius: 12px;
    margin-bottom: 16px;
    padding: 14px;
  }

  .recipe-audit-low-stock-item {
    align-items: center;
    border-top: 1px solid #ffedd5;
    display: grid;
    gap: 12px;
    grid-template-columns: minmax(180px, 1fr) auto minmax(180px, 1fr);
    padding: 8px 0;
  }

  .recipe-audit-low-stock-item:first-child {
    border-top: 0;
  }

  .recipe-audit-low-stock-qty {
    color: #b91c1c;
    font-weight: 800;
  }

  .recipe-audit-proint-list,
  .recipe-audit-warehouse-list {
    display: flex;
    flex-direction: column;
    gap: 5px;
  }

  .recipe-audit-proint-code {
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 6px;
    color: #3730a3;
    display: inline-flex;
    font-family: Consolas, monospace;
    font-size: .75rem;
    font-weight: 700;
    padding: 3px 6px;
    width: fit-content;
  }

  .recipe-audit-warehouse-badge {
    align-items: center;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-left: 3px solid #f59e0b;
    border-radius: 7px;
    display: flex;
    gap: 12px;
    justify-content: space-between;
    min-width: 230px;
    padding: 5px 8px;
  }

  .recipe-audit-warehouse-badge b {
    color: #9a3412;
    font-size: .78rem;
    white-space: nowrap;
  }

  .recipe-audit-low-stock-table td {
    vertical-align: middle;
  }

  .audit-status {
    font-size: 0.72rem;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    font-weight: 700;
    display: inline-block;
    white-space: nowrap;
  }

  .audit-COCOK,
  .audit-COCOK---ROUTING-BERBEDA {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
  }

  .audit-COCOK-SEBAGIAN {
    background: #fef08a;
    color: #854d0e;
    border: 1px solid #fde047;
  }

  .audit-BERBEDA,
  .audit-SELISIH-NILAI,
  .audit-HANYA-LOKAL,
  .audit-HANYA-PROINT {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
  }

  .audit-PERLU-PILIH-BON {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
  }

  .audit-BELUM-ADA-BON-PROINT,
  .audit-CP-TIDAK-DITEMUKAN,
  .audit-ROUTING-TIDAK-COCOK,
  .audit-MENUNGGU {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
  }

  .route-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.72rem;
    padding: 2px 6px;
    border-radius: 5px;
    margin: 1px 2px;
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
    font-weight: 600;
  }

  .route-badge.table-2 {
    background: #eff6ff;
    color: #1e40af;
    border-color: #bfdbfe;
  }

  .route-badge.table-3 {
    background: #faf5ff;
    color: #6b21a8;
    border-color: #e9d5ff;
  }

  .status-badge {
    display: inline-block;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.6rem;
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

  .stock-cell {
    min-width: 170px;
    max-width: 200px;
  }

  .stock-summary-card {
    padding: 8px 10px;
    border: 1px solid #dfe8e6;
    border-radius: 8px;
    background: linear-gradient(145deg, #f7fbfa, #fff);
  }

  .stock-summary-card.has-low-stock {
    border-color: #f5c66a;
    background: linear-gradient(145deg, #fff9e8, #fff);
    box-shadow: inset 3px 0 #f59e0b;
  }

  .stock-high-warning {
    display: block;
    margin-bottom: 6px;
    color: #9a5a00;
    font-size: 0.7rem;
    font-weight: 800;
  }

  .stock-summary-count {
    display: block;
    color: #0f4c45;
    font-size: 0.85rem;
    font-weight: 800;
  }

  .stock-summary-meta {
    display: block;
    margin: 2px 0 6px;
    color: #667875;
    font-size: 0.7rem;
  }

  .stock-view-button {
    border-color: #0f766e;
    color: #0f766e;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 6px;
  }

  .stock-view-button:hover {
    background: #0f766e;
    color: #fff;
  }

  .stock-modal-content {
    overflow: hidden;
    border: 0;
    border-radius: 16px;
    box-shadow: 0 24px 70px rgba(15, 44, 40, 0.25);
  }

  .stock-modal-header {
    align-items: flex-start;
    border: 0;
  }

  .stock-modal-eyebrow,
  .stock-modal-subtitle {
    color: rgba(255, 255, 255, 0.72);
    letter-spacing: 0.08em;
  }

  .stock-modal-body {
    background: #f4f8f7;
  }

  .stock-material-card {
    margin-bottom: 12px;
    overflow: hidden;
    border: 1px solid #dce8e5;
    border-radius: 12px;
    background: #fff;
  }

  .stock-material-heading {
    padding: 10px 14px;
    border-bottom: 1px solid #e8efed;
    background: #f9fcfb;
  }

  .stock-material-heading.has-low-stock {
    background: #fff8e8;
  }

  .stock-high-badge {
    display: inline-block;
    margin-top: 4px;
    padding: 2px 6px;
    border-radius: 999px;
    background: #fff0c2;
    color: #8a5200;
    font-size: 0.68rem;
    font-weight: 800;
  }

  .stock-modal-alert {
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid #f3c35d;
    border-radius: 10px;
    background: #fff7df;
    color: #805000;
  }

  .stock-product-group {
    padding: 8px 12px;
  }

  .stock-product-group + .stock-product-group {
    border-top: 1px dashed #dce8e5;
  }

  .stock-list {
    font-size: 0.8rem;
  }

  .stock-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 6px 0;
    border-bottom: 1px solid #edf0f2;
  }

  .stock-row:last-child {
    border-bottom: 0;
  }

  .stock-name {
    min-width: 0;
  }

  .stock-qty {
    white-space: nowrap;
    color: #0b4f48;
    font-weight: 800;
  }

  .stock-product {
    display: block;
    color: #243c38;
    font-weight: 800;
  }

  .table-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
  }

  .table-card-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 10px 14px;
  }

  @media (max-width: 900px) {
    .audit-summary {
      grid-template-columns: repeat(2, 1fr);
    }
  }
</style>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
  $(function () {
    let page = 1,
      auditToken = 0,
      pageRequest = null,
      auditRequest = null,
      summary = {},
      auditResults = new Map(),
      stockResponses = new Map(),
      lowStockRequests = new Set(),
      lowStockState = createLowStockState(),
      currentModalRecipeId = null,
      currentTableBonOverrides = {};

    const DEFAULT_MINIMUM_STOCK_KG = 500;
    const LOW_STOCK_KG = 500;

    const esc = (v) => $("<div>").text(v == null ? "" : String(v)).html();

    const numberDisplay = (v) => {
      if (v == null || v === "") return "-";
      let s = String(v);
      if (s.startsWith(".")) return "0" + s;
      if (s.startsWith("-.")) return "-0" + s.slice(1);
      return s;
    };

    const badge = (s) => {
      let key = esc(s || "").replaceAll(" ", "-").split("(")[0].trim().replace(/-$/, "");
      return `<span class="audit-status audit-${key}">${esc(s || "-")}</span>`;
    };

    const recipeStatusBadge = (value) => {
      let label = value == null || value === "" ? "-" : String(value),
        status = label.toLowerCase(),
        className = "status-default";
      if (status === "master resep" || status === "master") className = "status-master-resep";
      else if (status === "shading") className = "status-shading";
      else if (status === "top paddry") className = "status-top-paddry";
      else if (status === "top cpb") className = "status-top-cpb";
      else if (status === "kestabilan" || status === "kesetabilan") className = "status-kestabilan";
      return `<span class="status-badge ${className}">${esc(label)}</span>`;
    };

    function params() {
      return {
        cp: $("#auditCp").val().trim(),
        label_jual: $("#auditLabelJual").val() || "",
        route: $("#auditRoute").val() || "",
        source: $("#auditSource").val(),
        start: $("#auditStart").val(),
        end: $("#auditEnd").val(),
      };
    }

    function error(xhr) {
      let r = xhr.responseJSON || {};
      Swal.fire(
        "Error",
        (r.message || "Gagal memuat audit.") + (r.request_id ? " Request ID: " + r.request_id : ""),
        "error",
      );
    }

    function initLabelFilter() {
      $("#auditLabelJual")
        .html('<option value=""></option>')
        .prop("disabled", false)
        .select2({
          theme: "bootstrap4",
          placeholder: "Cari Label Jual",
          allowClear: true,
          width: "100%",
          minimumInputLength: 2,
          ajax: {
            url: "debug_detail_resep_api.php",
            dataType: "json",
            delay: 300,
            data: (params) => ({
              action: "label_options",
              q: params.term || "",
            }),
            processResults: (response) => ({ results: response.results || [] }),
            cache: true,
          },
          language: {
            inputTooShort: () => "Ketik minimal 2 karakter",
            searching: () => "Mencari label lokal...",
            noResults: () => "Label tidak ditemukan",
            errorLoading: () => "Gagal memuat label",
          },
        });
    }

    function loadRoutes() {
      $.getJSON("debug_detail_resep_api.php", {
        action: "routes",
      })
        .done((r) => {
          let html = '<option value="">Semua Routing</option>' +
            (r.routes || []).map((x) =>
              `<option value="${esc(x.code)}">${esc(x.code === "-" ? "TANPA ROUTING" : x.code + " - " + (x.name || "-"))} (${x.total})</option>`
            ).join("");
          $("#auditRoute").html(html);
        })
        .fail(error);
    }

    function showResult(x) {
      let $tr = $(`tr[data-audit-id="${x.id}"]`);
      if (!$tr.length) return;

      $tr.find(".audit-source").text(x.source || "");

      // Display bon numbers
      let bonHtml = "-";
      if (x.tables && x.tables.length > 0) {
        bonHtml = x.tables.map(t => {
          return `<div><small class="badge badge-secondary mr-1">T${t.table_index}</small><b>${esc(t.bonno || '-')}</b></div>`;
        }).join("");
      } else if (x.bonno) {
        bonHtml = `<b>${esc(x.bonno)}</b>`;
      }
      $tr.find(".audit-bon").html(bonHtml);

      // Display audit result with table mini-badges
      let tableBadgesHtml = "";
      if (x.tables && x.tables.length > 1) {
        tableBadgesHtml = '<div class="mt-1">' + x.tables.map(t => {
          let isOk = t.status === "COCOK";
          let badgeClass = isOk ? "badge-success" : (t.status === "BELUM ADA BON PROINT" ? "badge-secondary" : "badge-danger");
          let icon = isOk ? "✓" : "✗";
          return `<span class="badge ${badgeClass} mr-1" title="Tabel #${t.table_index} (${t.rtg_code}): ${t.status}">T${t.table_index}: ${icon}</span>`;
        }).join("") + '</div>';
      }

      $tr.find(".audit-result").html(
        `${badge(x.status)}${tableBadgesHtml}<br><small class="text-muted">${esc(x.status_detail || "")}</small>`
      );
    }

    function load(p = 1, restart = false) {
      page = p;
      if (pageRequest) pageRequest.abort();
      if (restart) {
        auditToken++;
        if (auditRequest) auditRequest.abort();
        summary = {};
        auditResults.clear();
        resetLowStockSummary();
        $("#sumMatch,#sumDiff,#sumMissing").text(0);
        $("#auditProgress").addClass("d-none");
      }
      let token = auditToken;
      $("#auditRows").html('<tr><td colspan="11" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat daftar resep V2...</td></tr>');

      pageRequest = $.getJSON("debug_detail_resep_api.php", {
        ...params(),
        page: page,
      })
        .done((r) => {
          if (token !== auditToken) return;
          const rowsHtml = (r.rows || []).map((row) => {
            const recipe = row.recipe;

            // Multi-table routing summary
            let routesHtml = "";
            if (recipe.routes_summary && recipe.routes_summary.length > 0) {
              routesHtml = recipe.routes_summary.map(rs => {
                let badgeClass = rs.table_index === 2 ? "table-2" : (rs.table_index === 3 ? "table-3" : "");
                return `<span class="route-badge ${badgeClass}" title="Tabel #${rs.table_index}: ${esc(rs.rtg_name || '-')}">` +
                  `<span class="badge badge-light mr-1">T${rs.table_index}</span>${esc(rs.rtg_code || '-')}` +
                  `</span>`;
              }).join(" ");
            } else {
              routesHtml = `${esc(recipe.rtg_code || "-")} - ${esc(recipe.rtg_name || "-")}`;
            }

            return [
              `<tr data-audit-id="${recipe.id}">`,
              `<td><b>${esc(recipe.no_cp)}</b><br><small class="text-muted audit-source"></small></td>`,
              `<td>${esc(recipe.label_jual || "-")}</td>`,
              `<td>${esc(recipe.cus_color || "-")}</td>`,
              `<td class="stock-cell" data-stock-id="${recipe.id}">`,
              '<small class="text-muted"><i class="fas fa-spinner fa-spin"></i> Memuat stock...</small></td>',
              `<td>${routesHtml}</td>`,
              `<td><span class="badge ${Number(recipe.is_manual) === 1 ? 'badge-warning' : 'badge-light border'}">${Number(recipe.is_manual) === 1 ? "MANUAL" : "PROINT"}</span></td>`,
              `<td>${recipeStatusBadge(recipe.status_resep_lipat)}</td>`,
              `<td><small>${esc(recipe.created_at)}</small></td>`,
              '<td class="audit-bon">-</td>',
              `<td class="audit-result">${badge("MENUNGGU")}<br><small class="text-muted">Menunggu pemeriksaan ProInt</small></td>`,
              `<td>`,
              `<div class="btn-group btn-group-sm">`,
              `<button class="btn btn-info detail-audit" data-id="${recipe.id}" title="Bandingkan Detail Formula">`,
              `<i class="fas fa-balance-scale mr-1"></i> Bandingkan</button>`,
              `<a class="btn btn-outline-secondary" href="view_resep.php?resep_id=${recipe.id}" title="Lihat Resep">`,
              `<i class="fas fa-eye"></i></a>`,
              `<a class="btn btn-warning" href="input_resep.php?resep_id=${recipe.id}" target="_blank" title="Buka / Edit Resep V2">`,
              `<i class="fas fa-edit"></i></a>`,
              `</div>`,
              `</td></tr>`,
            ].join("");
          }).join("");

          $("#auditRows").html(rowsHtml || '<tr><td colspan="11" class="text-center py-4 text-muted">Tidak ada data resep yang sesuai filter.</td></tr>');

          (r.rows || []).forEach((x) => {
            const recipeId = Number(x.recipe.id);
            const stockResponse = stockResponses.get(recipeId);
            if (stockResponse) $(`[data-stock-id="${recipeId}"]`).html(stockSummaryHtml(recipeId, stockResponse));
            let result = auditResults.get(recipeId);
            if (result) showResult(result);
          });

          $("#sumTotal").text(r.total || 0);

          const pages = Math.max(1, Math.ceil((r.total || 0) / (r.limit || 25)));
          let nav = [
            `<li class="page-item ${page === 1 ? "disabled" : ""}">`,
            `<button class="page-link audit-page" data-page="${page - 1}" aria-label="Halaman sebelumnya">&laquo;</button></li>`,
          ].join("");

          const shown = [1, pages, page - 1, page, page + 1]
            .filter((value, index, allPages) => value >= 1 && value <= pages && allPages.indexOf(value) === index)
            .sort((a, b) => a - b);

          let previous = 0;
          shown.forEach((pageNumber) => {
            if (previous && pageNumber > previous + 1) {
              nav += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            nav += `<li class="page-item ${pageNumber === page ? "active" : ""}">` +
              `<button class="page-link audit-page" data-page="${pageNumber}">${pageNumber}</button></li>`;
            previous = pageNumber;
          });

          nav += [
            `<li class="page-item ${page === pages ? "disabled" : ""}">`,
            `<button class="page-link audit-page" data-page="${page + 1}" aria-label="Halaman berikutnya">&raquo;</button></li>`,
          ].join("");

          $("#auditPager").html(nav);
          if (restart) loadAllIds(token);
        })
        .fail((xhr, status) => {
          if (status !== "abort") error(xhr);
        })
        .always(() => {
          pageRequest = null;
        });
    }

    function formatStockKg(value, fixedDecimals = false) {
      const truncated = Math.trunc((Number(value) + Number.EPSILON) * 100) / 100;
      return truncated.toLocaleString("id-ID", {
        minimumFractionDigits: fixedDecimals ? 2 : 0,
        maximumFractionDigits: 2,
      });
    }

    function createLowStockState(total = 0) {
      return { total, completed: 0, failed: 0, materials: new Map() };
    }

    function resetLowStockSummary() {
      lowStockRequests.forEach((request) => request.abort());
      lowStockRequests.clear();
      stockResponses.clear();
      lowStockState = createLowStockState();
      $("#recipe-audit-low-stock-count").text("0 Material");
      $("#recipe-audit-low-stock-progress").text("Menunggu audit");
      $("#recipe-audit-low-stock-preview").addClass("d-none");
      $("#recipe-audit-low-stock-preview-list,#recipe-audit-low-stock-modal-rows").empty();
    }

    function stockSnapshotKey(product, stock) {
      return [
        product.code || "",
        stock.company_id || "",
        stock.warehouse_code || stock.warehouse_name || "-",
        String(stock.uom || "").trim().toUpperCase(),
      ].join("|");
    }

    function mergeLowStockResponse(response) {
      (response.materials || []).forEach((material) => {
        const products = material.products || [];
        if (!products.length) return;
        const materialKey = String(material.local_code || "").trim();
        if (!materialKey) return;
        let aggregate = lowStockState.materials.get(materialKey);
        if (!aggregate) {
          aggregate = {
            code: materialKey,
            name: material.local_name || "-",
            minimumStockKg: Number(material.minimum_stock_kg ?? DEFAULT_MINIMUM_STOCK_KG),
            minimumStockIsDefault: Boolean(material.minimum_stock_is_default),
            productCodes: new Set(),
            snapshots: new Map(),
            warehouses: new Map(),
          };
          lowStockState.materials.set(materialKey, aggregate);
        }
        products.forEach((product) => {
          const productCode = String(product.code || "").trim();
          if (productCode) aggregate.productCodes.add(productCode);
          (product.stocks || []).forEach((stock) => {
            if (String(stock.uom || "").trim().toUpperCase() !== "KG") return;
            aggregate.snapshots.set(stockSnapshotKey(product, stock), Number(stock.qty || 0));
            const warehouseKey = [
              stock.company_id || "",
              stock.warehouse_code || stock.warehouse_name || "-",
            ].join("|");
            const warehouse = aggregate.warehouses.get(warehouseKey) || {
              name: stock.warehouse_name || stock.warehouse_code || "-",
              snapshots: new Map(),
            };
            warehouse.snapshots.set(stockSnapshotKey(product, stock), Number(stock.qty || 0));
            aggregate.warehouses.set(warehouseKey, warehouse);
          });
        });
      });
    }

    function lowStockMaterials() {
      return Array.from(lowStockState.materials.values())
        .map((material) => ({
          ...material,
          totalKg: Array.from(material.snapshots.values()).reduce((total, qty) => total + qty, 0),
          shortageKg: Math.max(0, material.minimumStockKg - Array.from(material.snapshots.values()).reduce((total, qty) => total + qty, 0)),
          fulfillmentRatio: material.minimumStockKg > 0
            ? Array.from(material.snapshots.values()).reduce((total, qty) => total + qty, 0) / material.minimumStockKg
            : Number.POSITIVE_INFINITY,
          productCodes: Array.from(material.productCodes).sort(),
          warehouseRows: Array.from(material.warehouses.values())
            .map((warehouse) => ({
              name: warehouse.name,
              qty: Array.from(warehouse.snapshots.values()).reduce((total, qty) => total + qty, 0),
            }))
            .sort((first, second) => first.name.localeCompare(second.name)),
        }))
        .filter((material) => material.totalKg < material.minimumStockKg)
        .sort((first, second) => first.fulfillmentRatio - second.fulfillmentRatio
          || second.shortageKg - first.shortageKg
          || first.code.localeCompare(second.code));
    }

    function warehouseRowsHtml(material) {
      if (!material.warehouseRows.length) return '<span class="text-muted">Belum tersedia</span>';
      return material.warehouseRows
        .map((warehouse) => `<span class="recipe-audit-warehouse-badge">
          <span>${esc(warehouse.name)}</span>
          <b>${formatStockKg(warehouse.qty)} KG</b>
        </span>`)
        .join("");
    }

    function productCodesHtml(productCodes) {
      return productCodes.length
        ? productCodes.map((code) => `<span class="recipe-audit-proint-code">${esc(code)}</span>`).join("")
        : '<span class="text-muted">-</span>';
    }

    function lowStockItemHtml(material) {
      return `<div class="recipe-audit-low-stock-item">
        <span><b>${esc(material.code)}</b><small class="d-block text-muted">${esc(material.name)}</small>
          <small class="d-block">ProInt: ${esc(material.productCodes.join(", ") || "-")}</small></span>
        <span class="recipe-audit-low-stock-qty">${formatStockKg(material.totalKg)} / ${formatStockKg(material.minimumStockKg)} KG
          <small class="d-block">Kurang ${formatStockKg(material.shortageKg)} KG${material.minimumStockIsDefault ? " · Default" : ""}</small></span>
        <span class="recipe-audit-warehouse-list">${warehouseRowsHtml(material)}</span>
      </div>`;
    }

    function renderLowStockSummary() {
      const materials = lowStockMaterials();
      const successful = lowStockState.completed - lowStockState.failed;
      const finished = lowStockState.completed >= lowStockState.total;
      $("#recipe-audit-low-stock-count").text(`${materials.length} Material`);
      $("#recipe-audit-low-stock-progress").text(
        finished
          ? lowStockState.failed
            ? `${successful}/${lowStockState.total} resep berhasil`
            : `Selesai · ${lowStockState.total} resep`
          : `Menghitung ${lowStockState.completed}/${lowStockState.total} resep...`,
      );
      $("#recipe-audit-low-stock-preview")
        .toggleClass("d-none", materials.length === 0)
        .find("#recipe-audit-low-stock-preview-list")
        .html(materials.slice(0, 3).map(lowStockItemHtml).join(""));
    }

    function renderLowStockModal(search = "") {
      const query = search.trim().toLowerCase();
      const materials = lowStockMaterials().filter((material) => {
        return [
          material.code,
          material.name,
          ...material.productCodes,
          ...material.warehouseRows.map((warehouse) => warehouse.name),
        ].join(" ").toLowerCase().includes(query);
      });
      $("#recipe-audit-low-stock-modal-status").text(
        `${materials.length} material · ${lowStockState.completed}/${lowStockState.total} resep diperiksa` +
          (lowStockState.failed ? ` · ${lowStockState.failed} gagal` : ""),
      );
      $("#recipe-audit-low-stock-modal-rows").html(
        materials.length
          ? materials.map((material) => `<tr><td><b>${esc(material.code)}</b></td>
              <td><div class="recipe-audit-proint-list">${productCodesHtml(material.productCodes)}</div></td>
              <td>${esc(material.name)}</td>
              <td class="text-right text-danger font-weight-bold">${formatStockKg(material.totalKg)}</td>
              <td class="text-right">${formatStockKg(material.minimumStockKg)}${material.minimumStockIsDefault ? ' <span class="badge badge-secondary">Default</span>' : ''}</td>
              <td class="text-right text-danger font-weight-bold">${formatStockKg(material.shortageKg)}</td>
              <td><div class="recipe-audit-warehouse-list">${warehouseRowsHtml(material)}</div></td></tr>`).join("")
          : '<tr><td colspan="7" class="text-center text-muted">Material tidak ditemukan.</td></tr>',
      );
    }

    function materialStockKg(material) {
      return (material.products || []).reduce((materialTotal, product) => {
        return materialTotal + (product.stocks || []).reduce((productTotal, stock) => {
          return String(stock.uom || "").trim().toUpperCase() === "KG"
            ? productTotal + Number(stock.qty || 0)
            : productTotal;
        }, 0);
      }, 0);
    }

    function needsMaterialPurchase(material) {
      const minimumStockKg = Number(material.minimum_stock_kg ?? DEFAULT_MINIMUM_STOCK_KG);
      return (material.products || []).length > 0 && materialStockKg(material) < minimumStockKg;
    }

    function stockSummaryHtml(recipeId, response) {
      const materials = response.materials || [];
      const uniqueWarehouses = new Set();
      let available = 0;
      let unmapped = 0;
      let lowStockMaterials = 0;
      materials.forEach((material) => {
        const products = material.products || [];
        const materialStocks = products.flatMap((product) => product.stocks || []);
        if (!products.length) unmapped++;
        if (materialStocks.length > 0) available++;
        if (needsMaterialPurchase(material)) lowStockMaterials++;
        materialStocks.forEach((stock) => {
          const warehouseIdentity = [
            stock.company_id || "",
            stock.warehouse_code || stock.warehouse_name || "-",
          ].join("|");
          uniqueWarehouses.add(warehouseIdentity);
        });
      });
      const unavailable = materials.length - available;
      const statusParts = [`${available} tersedia`, `${unavailable} kosong`];
      if (unmapped) statusParts.push(`${unmapped} belum dipetakan`);
      const warning = lowStockMaterials
        ? `<span class="stock-high-warning"><i class="fas fa-shopping-cart mr-1"></i>` +
          `${lowStockMaterials} material perlu dibeli (&lt; ${LOW_STOCK_KG} KG)</span>`
        : "";

      return [
        `<div class="stock-summary-card${lowStockMaterials ? " has-low-stock" : ""}">`,
        `<span class="stock-summary-count">${materials.length} material</span>`,
        `<span class="stock-summary-meta">${statusParts.join(" · ")}<br>`,
        `${uniqueWarehouses.size} gudang</span>`,
        warning,
        `<button type="button" class="btn btn-sm btn-outline-primary stock-view-button" `,
        `data-stock-detail-id="${recipeId}"><i class="fas fa-box-open mr-1"></i>Lihat Stock</button>`,
        "</div>",
      ].join("");
    }

    function stockDetailHtml(response) {
      if (!response.materials || !response.materials.length) {
        return `<div class="text-muted text-center py-4">${esc(
          response.message || "Detail material resep tidak ditemukan.",
        )}</div>`;
      }

      const lowStockMaterials = response.materials.filter(needsMaterialPurchase);
      const alert = lowStockMaterials.length
        ? [
            '<div class="stock-modal-alert">',
            '<strong><i class="fas fa-shopping-cart mr-1"></i>Pengingat pembelian material</strong><br>',
            `${lowStockMaterials.length} material memiliki total stock kurang dari ${LOW_STOCK_KG} KG.`,
            "</div>",
          ].join("")
        : "";
      const materialCards = response.materials
        .map((material) => {
          const totalKg = materialStockKg(material);
          const isLowStock = needsMaterialPurchase(material);
          const lowStockBadge = isLowStock
            ? `<span class="stock-high-badge">${formatStockKg(totalKg, true)} KG · PERLU DIBELI (&lt; ${LOW_STOCK_KG} KG)</span>`
            : "";
          const materialHeading = [
            `<div class="stock-material-heading${isLowStock ? " has-low-stock" : ""}">`,
            `<span class="stock-product">${esc(material.local_code || "-")} · `,
            `${esc(material.local_name || "-")}</span>`,
            `<small class="text-muted d-block">${esc(material.category || "-")}</small>`,
            lowStockBadge,
            "</div>",
          ].join("");
          if (!material.products || !material.products.length) {
            return [
              '<section class="stock-material-card">',
              materialHeading,
              `<div class="stock-product-group text-warning">${esc(material.message)}</div>`,
              "</section>",
            ].join("");
          }

          const productGroups = material.products
            .map((product) => {
              const productName = product.name ? ` · ${esc(product.name)}` : "";
              const stockRows = (product.stocks || [])
                .map((stock) => {
                  const location = stock.location_name ? ` / ${esc(stock.location_name)}` : "";
                  const warehouseLabel = esc(stock.warehouse_name || stock.warehouse_code || "-");
                  const quantity = formatStockKg(stock.qty, true);
                  return [
                    '<div class="stock-row">',
                    `<span class="stock-name">${warehouseLabel}${location}</span>`,
                    `<span class="stock-qty">${quantity} ${esc(stock.uom || "-")}</span>`,
                    "</div>",
                  ].join("");
                })
                .join("");
              const productState = product.message ? `<span class="text-muted">${esc(product.message)}</span>` : "";
              return [
                '<div class="stock-product-group">',
                `<small class="d-block text-muted mb-1">ProInt: ${esc(product.code)}${productName}</small>`,
                `<div class="stock-list">${stockRows || productState}</div>`,
                "</div>",
              ].join("");
            })
            .join("");

          return [
            '<section class="stock-material-card">',
            materialHeading,
            productGroups,
            "</section>",
          ].join("");
        })
        .join("");

      return alert + materialCards;
    }

    function showStockDetail(recipeId) {
      const response = stockResponses.get(Number(recipeId));
      if (!response) return;
      $("#stockDetailCp").text(`No CP ${response.no_cp || "-"}`);
      $("#stockDetailBody").html(stockDetailHtml(response));
      $("#stockDetailModal").modal("show");
    }

    function loadStocks(ids, token) {
      const queue = Array.from(new Set(ids.map(Number))).filter(Number.isInteger);
      lowStockState = createLowStockState(queue.length);
      renderLowStockSummary();
      let active = 0;
      function next() {
        if (token !== auditToken) return;
        while (active < 3 && queue.length) {
          const id = queue.shift();
          active++;
          const request = $.getJSON("debug_detail_resep_api.php", { action: "stock", id });
          lowStockRequests.add(request);
          request
            .done((response) => {
              if (token !== auditToken) return;
              stockResponses.set(id, response);
              mergeLowStockResponse(response);
              $(`[data-stock-id="${id}"]`).html(stockSummaryHtml(id, response));
            })
            .fail((xhr, status) => {
              if (token !== auditToken || status === "abort") return;
              lowStockState.failed++;
              const response = xhr.responseJSON || {};
              $(`[data-stock-id="${id}"]`).html(
                `<small class="text-danger">${esc(response.message || "Gagal memuat stock.")}</small>`,
              );
            })
            .always(() => {
              lowStockRequests.delete(request);
              if (token !== auditToken) return;
              lowStockState.completed++;
              active--;
              renderLowStockSummary();
              next();
            });
        }
      }
      next();
    }

    $(document).on("click", "[data-stock-detail-id]", function () {
      showStockDetail($(this).data("stock-detail-id"));
    });

    $(document).on("click", "#recipe-audit-low-stock-open,[data-action='open-low-stock-summary']", function () {
      renderLowStockModal($("#recipe-audit-low-stock-search").val() || "");
      $("#recipe-audit-low-stock-modal").modal("show");
    });

    $("#recipe-audit-low-stock-search").on("input", function () {
      renderLowStockModal(this.value);
    });

    function updateSummary(s) {
      summary[s] = (summary[s] || 0) + 1;
      let cocok = (summary.COCOK || 0) + (summary["COCOK - ROUTING BERBEDA"] || 0);
      let sebagian = 0;
      Object.keys(summary).forEach(k => {
        if (k.startsWith("COCOK SEBAGIAN")) sebagian += summary[k];
      });
      $("#sumMatch").text(cocok + (sebagian > 0 ? ` (+${sebagian})` : ""));
      $("#sumDiff").text(summary.BERBEDA || 0);
      $("#sumMissing").text((summary["BELUM ADA BON PROINT"] || 0) + (summary["CP TIDAK DITEMUKAN"] || 0));
    }

    function loadAllIds(token) {
      auditRequest = $.getJSON("debug_detail_resep_api.php", {
        action: "ids",
        ...params(),
      })
        .done((r) => {
          if (token !== auditToken) return;
          const ids = r.ids || [];
          loadStocks(ids, token);
          runBatches(ids, token);
        })
        .fail((xhr, status) => {
          if (status !== "abort") error(xhr);
        })
        .always(() => {
          auditRequest = null;
        });
    }

    function runBatches(ids, token) {
      let done = 0,
        total = ids.length;
      if (!total) return;
      $("#auditProgress").removeClass("d-none");
      $("#auditProgressText").text("Memeriksa seluruh data ProInt...");
      $("#auditProgressCount").text(`0/${total}`);
      $("#auditProgressBar").addClass("progress-bar-animated").css("width", "0%");

      function next() {
        if (token !== auditToken) return;
        if (done >= total) {
          $("#auditProgressText").text("Audit seluruh data ProInt selesai");
          $("#auditProgressBar").removeClass("progress-bar-animated");
          return;
        }
        let batch = ids.slice(done, done + 5);
        auditRequest = $.getJSON("debug_detail_resep_api.php", {
          action: "audit_batch",
          ids: batch.join(","),
        })
          .done((r) => {
            if (token !== auditToken) return;
            (r.rows || []).forEach((x) => {
              auditResults.set(Number(x.id), x);
              showResult(x);
              updateSummary(x.status);
            });
            done += batch.length;
            let pct = Math.round((done / total) * 100);
            $("#auditProgressText").text("Memeriksa seluruh data ProInt...");
            $("#auditProgressCount").text(`${done}/${total}`);
            $("#auditProgressBar").css("width", pct + "%");
            next();
          })
          .fail((xhr, status) => {
            if (status !== "abort") error(xhr);
          })
          .always(() => {
            auditRequest = null;
          });
      }
      next();
    }

    // Modal detail with multi-table support
    function detail(id, tableOverrides = null) {
      currentModalRecipeId = id;
      if (tableOverrides) {
        currentTableBonOverrides = { ...currentTableBonOverrides, ...tableOverrides };
      } else {
        currentTableBonOverrides = {};
      }

      $("#auditTablesContainer").html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 text-muted">Memuat perbandingan formula multi-tabel...</p></div>');
      $("#auditDetailModal").modal("show");

      let apiParams = { action: "detail", id: id };
      if (Object.keys(currentTableBonOverrides).length > 0) {
        apiParams.table_bon = currentTableBonOverrides;
      }

      $.getJSON("debug_detail_resep_api.php", apiParams)
        .done((response) => {
          const audit = response.audit;
          const recipe = audit.recipe || {};

          // Subtitle and direct edit button
          $("#auditDetailSubtitle").text(`No CP: ${recipe.no_cp || "-"} | Label Jual: ${recipe.label_jual || "-"} | Cus Color: ${recipe.cus_color || "-"}`);
          $("#auditBtnOpenRecipe").attr("href", `input_resep.php?resep_id=${id}`);

          // Header summary box
          const headerSummaryHtml = `
            <div class="card bg-light border mb-3">
              <div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                  <h6 class="mb-1 font-weight-bold">
                    No CP: <span class="text-primary">${esc(recipe.no_cp)}</span>
                    <span class="ml-2 badge ${Number(recipe.is_manual) === 1 ? 'badge-warning' : 'badge-secondary'}">${Number(recipe.is_manual) === 1 ? 'MANUAL' : 'PROINT'}</span>
                  </h6>
                  <div class="text-muted small">
                    Label Jual: <b>${esc(recipe.label_jual || '-')}</b> | 
                    Warna: <b>${esc(recipe.cus_color || '-')}</b> | 
                    Total Tabel: <b>${audit.total_tables || (audit.tables ? audit.tables.length : 1)}</b> |
                    Dibuat: <b>${esc(recipe.created_at || '-')}</b>
                  </div>
                </div>
                <div class="text-right">
                  <div>${badge(audit.status)}</div>
                  <small class="text-muted">${esc(audit.status_detail || "")}</small>
                </div>
              </div>
            </div>
          `;
          $("#auditDetailHeaderSummary").html(headerSummaryHtml);

          // Render tables
          const tables = audit.tables || [];
          if (!tables.length) {
            $("#auditTablesContainer").html('<div class="alert alert-warning text-center">Tidak ada rincian tabel formula resep untuk CP ini.</div>');
            return;
          }

          let tablesHtml = "";
          tables.forEach((t) => {
            const selectedBon = t.selected_bon || {};
            const candidates = t.candidates || [];

            // Bon chooser dropdown for this table
            let bonChooserSelect = "";
            if (candidates.length > 1) {
              let options = candidates.map((c) => {
                let isSelected = selectedBon.bonreqid && Number(selectedBon.bonreqid) === Number(c.bonreqid);
                return `<option value="${c.bonreqid}" ${isSelected ? 'selected' : ''}>` +
                  `Bon: ${esc(c.bonno)} (VLot: ${c.vlot || '-'} · ${esc(c.rtgname || '-')})` +
                  `</option>`;
              }).join("");
              bonChooserSelect = `
                <div class="d-inline-flex align-items-center ml-2">
                  <small class="text-muted mr-1 font-weight-bold">Ganti Bon:</small>
                  <select class="form-control form-control-sm table-bon-select font-weight-bold" data-recipe-id="${id}" data-table-index="${t.table_index}" style="width: auto; max-width: 280px;">
                    ${options}
                  </select>
                </div>
              `;
            }

            // Material rows for this table
            let detailRowsHtml = "";
            const details = t.details || [];
            if (details.length > 0) {
              detailRowsHtml = details.map((d, idx) => {
                const local = d.local || {};
                const proint = d.proint || {};
                const reason = (d.reasons || []).join(", ") || d.status;

                let rowBgClass = "";
                if (d.status === "SELISIH NILAI") rowBgClass = "table-danger";
                else if (d.status === "HANYA LOKAL") rowBgClass = "table-warning";
                else if (d.status === "HANYA PROINT") rowBgClass = "table-info";

                return `
                  <tr class="${rowBgClass}">
                    <td class="text-center font-weight-bold text-muted">${idx + 1}</td>
                    <td><code>${esc(d.kode)}</code></td>
                    <td><b>${esc(local.name || proint.name || "-")}</b></td>
                    <td><small class="text-muted">${esc(local.category || proint.category || "-")}</small></td>
                    <td class="text-right font-weight-bold">${esc(numberDisplay(local.receipe))}</td>
                    <td class="text-right font-weight-bold">${esc(numberDisplay(proint.qty))}</td>
                    <td class="text-right">${esc(numberDisplay(local.cf))}</td>
                    <td class="text-right">${esc(numberDisplay(proint.cf))}</td>
                    <td class="text-center"><small>${esc(local.uom || proint.uom || "-")}</small></td>
                    <td>${badge(d.status)}<br><small class="text-muted">${esc(reason)}</small></td>
                  </tr>
                `;
              }).join("");
            } else {
              detailRowsHtml = '<tr><td colspan="10" class="text-center py-3 text-muted">Belum ada material yang terhubung dengan Bon ini.</td></tr>';
            }

            tablesHtml += `
              <div class="card table-card mb-4">
                <div class="table-card-header d-flex justify-content-between align-items-center flex-wrap">
                  <div>
                    <h6 class="mb-0 font-weight-bold">
                      <span class="badge badge-primary px-2 py-1 mr-1">Tabel #${t.table_index}</span>
                      ${esc(t.rtg_code)} - ${esc(t.rtg_name || 'ROUTING')}
                    </h6>
                    <small class="text-muted">
                      Bon Lokal: <b class="text-dark">${esc(t.local_bonno || '-')}</b> | 
                      Bon ProInt: <b class="${selectedBon.bonno ? 'text-success' : 'text-danger'}">${esc(selectedBon.bonno || 'BELUM TERBIT')}</b> | 
                      VLot: <b>${t.vlot || '-'}</b>
                    </small>
                  </div>
                  <div class="d-flex align-items-center mt-1 mt-md-0">
                    ${badge(t.status)}
                    ${bonChooserSelect}
                  </div>
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped mb-0">
                      <thead class="thead-light">
                        <tr>
                          <th style="width: 35px;" class="text-center">#</th>
                          <th>Kode ProInt</th>
                          <th>Nama Material</th>
                          <th>Kategori</th>
                          <th class="text-right">Qty Lokal</th>
                          <th class="text-right">Qty ProInt</th>
                          <th class="text-right">CF Lokal</th>
                          <th class="text-right">CF ProInt</th>
                          <th class="text-center">UOM</th>
                          <th>Status & Selisih</th>
                        </tr>
                      </thead>
                      <tbody>${detailRowsHtml}</tbody>
                    </table>
                  </div>
                </div>
              </div>
            `;
          });

          $("#auditTablesContainer").html(tablesHtml);
        })
        .fail((xhr) => {
          $("#auditTablesContainer").html('<div class="alert alert-danger text-center">Gagal memuat detail audit resep.</div>');
          error(xhr);
        });
    }

    $("#auditFilter").click(() => load(1, true));
    $("#auditCp").keydown((e) => {
      if (e.key === "Enter") load(1, true);
    });

    $("#auditRoute, #auditSource").change(() => load(1, true));

    $(document)
      .on("click", ".audit-page", function () {
        load(Number($(this).data("page")), false);
      })
      .on("click", ".detail-audit", function () {
        detail($(this).data("id"));
      })
      .on("change", ".table-bon-select", function () {
        const recipeId = $(this).data("recipe-id");
        const tableIndex = $(this).data("table-index");
        const selectedBonId = $(this).val();
        let overrides = {};
        overrides[tableIndex] = selectedBonId;
        detail(recipeId, overrides);
      });

    $("#auditExport").click(async () => {
      let choice = await Swal.fire({
        title: "Pilih Jenis Export",
        text: "Ringkasan cepat memakai hasil audit yang sudah selesai, atau Beserta Detail untuk audit lengkap per tabel.",
        icon: "question",
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '<i class="fas fa-bolt mr-1"></i> Ringkasan Cepat',
        denyButtonText: '<i class="fas fa-list-ul mr-1"></i> Beserta Detail',
        cancelButtonText: "Batal",
      });
      if (choice.isDismissed) return;
      if (choice.isDenied) {
        let q = new URLSearchParams(params());
        window.location = "export_debug_detail_resep.php?" + q.toString();
        return;
      }
      let expected = Number($("#sumTotal").text()) || 0;
      if (!expected || auditResults.size !== expected) {
        Swal.fire(
          "Audit Belum Selesai",
          `Tunggu sampai pemeriksaan ProInt selesai (${auditResults.size}/${expected}).`,
          "warning",
        );
        return;
      }
      Swal.fire({
        title: "Membuat ringkasan Excel...",
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });
      fetch("export_debug_detail_resep.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          rows: Array.from(auditResults.values()),
        }),
      })
        .then(async (response) => {
          if (!response.ok) throw new Error((await response.text()) || "Export gagal.");
          let blob = await response.blob(),
            url = URL.createObjectURL(blob),
            link = document.createElement("a");
          link.href = url;
          link.download = "Audit_Ringkasan_Resep_V2_" + Date.now() + ".xlsx";
          document.body.appendChild(link);
          link.click();
          link.remove();
          URL.revokeObjectURL(url);
          Swal.fire("Berhasil", "Ringkasan audit berhasil diunduh.", "success");
        })
        .catch((e) => Swal.fire("Export Gagal", e.message, "error"));
    });

    loadRoutes();
    initLabelFilter();
    load(1, true);
  });
</script>
