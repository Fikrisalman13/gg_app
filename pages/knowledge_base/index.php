<?php
require_once __DIR__ . '/kb_helpers.php';
require_once __DIR__ . '/../ticket/theme_helper.php';

$context = kb_context($conn);
$themeColor = ticket_normalize_theme($_SESSION['Theme'] ?? 'primary');
$themeColorSafe = htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8');

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/summernote/summernote-bs4.min.css">

<style>
.kb-archive-search {
  max-width: 280px;
}

.kb-folder-card,
.kb-article-card {
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.kb-folder-card:hover,
.kb-article-card:hover {
  box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.12);
  transform: translateY(-2px);
}

.kb-folder-icon {
  color: #f4b400;
  font-size: 2rem;
}

.kb-archive-content {
  opacity: 1;
  transform: translateY(0);
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.kb-archive-content.is-loading {
  opacity: 0.35;
  pointer-events: none;
  transform: translateY(4px);
}

.kb-article-card {
  align-items: center;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  min-height: 64px;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.kb-article-card:hover {
  box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.12);
  transform: translateY(-2px);
}

.kb-article-main {
  align-items: center;
  display: flex;
  flex: 1 1 auto;
  min-width: 0;
}

.kb-article-meta {
  min-width: 0;
}

.kb-article-meta strong {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.kb-article-icon {
  color: #6c757d;
  flex: 0 0 28px;
  font-size: 1.5rem;
  text-align: center;
}

.kb-article-card .action-button-group {
  flex: 0 0 auto;
  margin-left: 1rem;
}

@media (max-width: 767.98px) {
  .kb-archive-search {
    margin-top: 0.75rem;
    max-width: none;
    width: 100%;
  }

  .kb-article-card {
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 0.75rem;
  }

  .kb-article-card .action-button-group {
    margin-left: 2rem;
  }
}

.kb-table {
  font-size: 0.9rem;
}

.kb-table th,
.kb-table td {
  vertical-align: middle;
}

.kb-table td:nth-child(2) {
  max-width: 280px;
  white-space: normal;
  word-break: break-word;
}

.kb-message-modal .modal-dialog {
  align-items: center;
  display: flex;
  max-width: 760px;
  min-height: calc(100vh - 2rem);
}

.kb-message-modal .modal-content {
  display: flex;
  max-height: calc(100vh - 2rem);
}

.kb-message-modal .modal-body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  padding: 1.25rem 1.5rem 0.75rem;
}

.kb-message-modal .modal-footer {
  flex-shrink: 0;
}

.kb-message-modal .form-group {
  margin-bottom: 1rem;
}

.kb-message-modal .note-editor.note-frame {
  border-radius: 0.25rem;
}

.kb-message-modal .note-editable {
  min-height: 220px;
}

.kb-image-modal .modal-body {
  overflow: hidden;
}

#kb-image-preview {
  max-height: 78vh;
  object-fit: contain;
  transform-origin: center center;
  user-select: none;
}

.kb-modal-header {
  align-items: flex-start;
}

.kb-detail-meta {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  font-size: 0.82rem;
  gap: 0.3rem;
  margin-top: 0.25rem;
  opacity: 0.9;
}

.kb-detail-title {
  font-size: 1.05rem;
  font-weight: 600;
}

.kb-detail-separator {
  opacity: 0.65;
}

.kb-message-view {
  background: #fff;
  border-radius: 0.35rem;
  font-size: 1rem;
  line-height: 1.7;
  min-height: 260px;
  padding: 1.5rem;
}

.kb-share-bagian-list {
  border: 1px solid #dee2e6;
  border-radius: 0.25rem;
  max-height: 220px;
  overflow-y: auto;
}

.kb-share-bagian-option {
  border-bottom: 1px solid #f0f1f3;
  padding: 0.55rem 0.75rem;
}

.kb-share-bagian-option:last-child {
  border-bottom: 0;
}

.kb-share-bagian-option:hover {
  background: #f8f9fa;
}

.kb-share-bagian-option.is-owner {
  background: #eef5ff;
}

.kb-share-bagian-option label {
  cursor: pointer;
  margin-bottom: 0;
  width: 100%;
}

.kb-share-selected .badge {
  font-weight: 400;
  margin: 0.15rem;
}

.kb-message-view img,
.note-editable img {
  cursor: zoom-in;
  max-width: 200px;
  height: auto;
  border-radius: 6px;
  display: block;
  margin-top: 0.35rem;
  background: rgba(0, 0, 0, 0.03);
  box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.04);
}

.kb-message-view img {
  cursor: zoom-in;
}

.action-button-group {
  align-items: center;
  display: inline-flex;
  flex-wrap: nowrap;
}

.action-button-group .action-btn {
  margin-right: 6px;
}

.action-button-group .action-btn:last-child {
  margin-right: 0;
}

@media (max-width: 767.98px) {
  .kb-message-modal .modal-dialog {
    align-items: stretch;
    margin: 0.5rem;
    max-width: none;
    min-height: calc(100vh - 1rem);
  }

  .kb-message-modal .modal-content {
    max-height: calc(100vh - 1rem);
  }

  .kb-message-modal .modal-body {
    padding: 1rem;
  }
}

.swal2-container {
  z-index: 10000;
}

.note-modal {
  z-index: 9999;
}

.note-modal-backdrop {
  z-index: 9998;
}
</style>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Knowledge Base</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
            <li class="breadcrumb-item active">Knowledge Base</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card">
        <div class="card-header bg-<?= $themeColorSafe ?> text-white">
          <h3 class="card-title"><i class="fas fa-book mr-1"></i> Daftar Artikel</h3>
          <div class="card-tools">
            <button id="kb-add-btn" class="btn btn-success btn-sm" type="button">
              <i class="fas fa-plus"></i> Tambah Artikel
            </button>
          </div>
        </div>
        <div class="card-body">
          <div class="d-flex flex-wrap align-items-center mb-3">
            <button id="kb-archive-back" class="btn btn-secondary btn-sm mr-2" type="button" disabled>
              <i class="fas fa-arrow-left"></i> Kembali
            </button>
            <nav id="kb-archive-breadcrumb" aria-label="Lokasi arsip" class="mr-auto"></nav>
            <div class="input-group input-group-sm kb-archive-search">
              <input id="kb-archive-search-input" class="form-control" type="search" placeholder="Cari artikel, kategori, atau Bagian..." aria-label="Cari artikel, kategori, atau Bagian">
              <div class="input-group-append"><button id="kb-archive-search" class="btn btn-outline-secondary" type="button" title="Cari artikel"><i class="fas fa-search"></i></button></div>
            </div>
          </div>
          <div class="kb-archive-content" id="kb-archive-content">
            <div id="kb-archive-status" class="text-muted small mb-3" role="status"></div>
            <div id="kb-archive-folders" class="row"></div>
            <div id="kb-archive-articles" class="list-group"></div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="modal fade kb-message-modal" id="kb-modal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form id="kb-form" class="modal-content">
      <div class="modal-header bg-<?= $themeColorSafe ?> text-white kb-modal-header">
        <div>
          <h5 class="modal-title" id="kb-modal-title">Artikel</h5>
          <div id="kb-detail-meta" class="kb-detail-meta d-none" aria-live="polite">
            <span id="kb-detail-title" class="kb-detail-title"></span>
            <span class="kb-detail-separator">·</span>
            <span id="kb-detail-category"></span>
            <span class="kb-detail-separator">·</span>
            <span id="kb-detail-bagian"></span>
          </div>
        </div>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="kb-id" name="id">

        <div class="form-group kb-detail-edit-only">
          <label for="kb-title">Nama Artikel</label>
          <input type="text" class="form-control" id="kb-title" name="title" maxlength="200" required>
        </div>

        <div class="form-group kb-detail-edit-only">
          <label for="kb-category">Kategori</label>
          <select id="kb-category" class="form-control" name="category_name" required></select>
        </div>

        <div class="form-group kb-detail-edit-only">
          <label for="kb-bagian">Bagian</label>
          <input
            type="text"
            class="form-control"
            id="kb-bagian"
            value="<?= htmlspecialchars($context['bagian'], ENT_QUOTES, 'UTF-8') ?>"
            readonly
          >
        </div>

        <div class="form-group kb-detail-message-editor">
          <label for="kb-message">Message</label>
          <textarea id="kb-message" name="message_html"></textarea>
        </div>

        <div id="kb-view" class="kb-message-view d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
        <button type="submit" id="kb-save-btn" class="btn btn-primary">
          <i class="fas fa-save"></i> Simpan
        </button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="kb-share-modal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-md" role="document">
    <form id="kb-share-form" class="modal-content">
      <div class="modal-header bg-<?= $themeColorSafe ?> text-white">
        <h5 class="modal-title"><i class="fas fa-share-alt mr-1"></i> Bagikan Artikel</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="kb-share-id" name="id">
        <p id="kb-share-article-title" class="font-weight-bold mb-3"></p>
        <div class="form-group mb-3">
          <label class="d-block">Bagikan artikel ke:</label>
          <div class="custom-control custom-radio mb-2">
            <input class="custom-control-input" type="radio" id="kb-share-all" name="share_mode" value="all">
            <label class="custom-control-label" for="kb-share-all"><strong>Semua Bagian</strong><small class="d-block text-muted">Semua Bagian dapat melihat artikel.</small></label>
          </div>
          <div class="custom-control custom-radio">
            <input class="custom-control-input" type="radio" id="kb-share-specific" name="share_mode" value="specific" checked>
            <label class="custom-control-label" for="kb-share-specific"><strong>Bagian tertentu</strong><small class="d-block text-muted">Pilih Bagian yang dapat melihat artikel.</small></label>
          </div>
        </div>
        <div id="kb-share-specific-panel">
          <label for="kb-share-search">Pilih Bagian</label>
          <input id="kb-share-search" class="form-control form-control-sm mb-2" type="search" placeholder="Cari Bagian..." autocomplete="off">
          <div id="kb-share-bagian-list" class="kb-share-bagian-list"></div>
          <div id="kb-share-selected" class="small text-muted mt-2"></div>
        </div>
        <small class="form-text text-muted">Pemilik artikel selalu memiliki akses.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="submit" id="kb-share-save" class="btn btn-primary"><i class="fas fa-check"></i> Terapkan Sharing</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade kb-image-modal" id="kb-image-modal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
    <div class="modal-content bg-dark">
      <div class="modal-header border-0 py-2">
        <h5 class="modal-title text-white">Preview Gambar</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-2 text-center">
        <img id="kb-image-preview" class="img-fluid" src="" alt="Preview gambar">
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/summernote/summernote-bs4.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>
<script src="/gg_app/pages/knowledge_base/assets/js/knowledge_base.js?v=20260810-archive"></script>
