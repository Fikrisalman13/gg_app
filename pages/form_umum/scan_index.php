<?php
/**
 * scan_index.php
 * Landing page universal untuk scan Form Umum.
 * Dirancang untuk dibuka terus-menerus (continuous scanning):
 *   - Auto-focus pada input field
 *   - Mendukung paste dari QR scanner USB (auto-submit saat Enter)
 *   - Mendukung scan URL lengkap dari handheld scanner di PC
 *     (auto-redirect ke URL hasil scan, seperti pengalaman di Android)
 *   - Menampilkan 5 scan terakhir (localStorage)
 *   - Validasi client & server (format IKS-XXX)
 *
 * Bisa diakses tanpa login (termasuk satpam).
 */
$scanPublicAccess = true;
$GLOBALS['scanPublicAccess'] = true;

include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
if (!empty($_SESSION['UserId'])) {
    include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');
}

$namaUser = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'] ?? 'User';
$themeColor = $_SESSION['Theme'] ?? 'primary';
$lightThemeOptions = ['warning', 'light', 'lime', 'white'];
$isLightTheme = in_array($themeColor, $lightThemeOptions, true);
$headerTextClass = $isLightTheme ? 'text-dark' : 'text-white';
?>

<style>
  .content-wrapper { background-color: #f4f6f9 !important; min-height: calc(100vh - 100px); }
  body.layout-top-nav .content-wrapper,
  body.layout-top-nav .main-header,
  body.layout-top-nav .main-footer {
    margin-left: 0 !important;
  }
  /* ── Layout shell ───────────────────────────────────────────── */
  .scan-shell {
    max-width: 1040px;
    margin: 0 auto;
  }

  /* ── Form card ──────────────────────────────────────────────── */
  .scan-form-card {
    border-radius: 10px;
    overflow: hidden;
  }

  .scan-form-header {
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 0;
  }

  .scan-form-header .icon-wrap {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, .25);
    font-size: 20px;
    flex-shrink: 0;
  }

  /* Light theme: solid white icon-wrap so the theme color text shows clearly */
  .scan-form-header.is-light .icon-wrap {
    background: #fff;
    color: #<?= htmlspecialchars($themeColor) ?>;
  }

  .scan-form-header .title {
    font-weight: 700;
    font-size: 1.05rem;
    margin: 0;
    line-height: 1.1;
  }

  .scan-form-header .sub {
    font-size: .8rem;
    opacity: .85;
  }

  .scan-form-body {
    padding: 18px;
  }

  /* Inline input + button row */
  .scan-input-row {
    display: flex;
    gap: 8px;
    align-items: stretch;
  }

  .scan-input-row .form-control {
    flex: 1;
    font-size: 1.05rem;
    font-weight: 600;
    letter-spacing: 1px;
    text-align: center;
    height: 44px;
  }

  .scan-input-row .form-control.is-invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, .12);
  }

  .scan-input-row .btn {
    flex-shrink: 0;
    height: 44px;
    padding: 0 18px;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(0, 0, 0, .08);
  }

  .helper-text {
    font-size: .8rem;
    color: #6c757d;
    margin-top: 8px;
    text-align: center;
  }

  .live-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #28a745;
    margin-right: 5px;
    box-shadow: 0 0 0 0 rgba(40, 167, 69, .7);
    animation: live-pulse 1.6s infinite;
  }

  @keyframes live-pulse {
    0% {
      box-shadow: 0 0 0 0 rgba(40, 167, 69, .7);
    }

    70% {
      box-shadow: 0 0 0 6px rgba(40, 167, 69, 0);
    }

    100% {
      box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
    }
  }

  /* ── Recent card ────────────────────────────────────────────── */
  .recent-card {
    border-radius: 10px;
    overflow: hidden;
  }

  .recent-card .card-header {
    border-bottom: 1px solid rgba(0, 0, 0, .08);
    padding: 12px 16px;
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }

  /* Kill AdminLTE's clearfix ::after — it occupies the rightmost flex
     slot and pushes the trash button into the middle. */
  .recent-card .card-header::after {
    display: none !important;
    content: none !important;
  }

  /* Light theme: keep white-ish header so card body blends with page */
  .recent-card .card-header.is-light {
    background: #fff;
    color: #212529;
  }

  .recent-card .card-header.is-light .card-title {
    color: #495057;
  }

  .recent-card .card-header.is-light .card-title i {
    color: #adb5bd;
  }

  .recent-card .card-header.is-light .btn-tool {
    color: #6c757d;
  }

  .recent-card .card-header.is-light .btn-tool:hover {
    color: #dc3545;
    background: rgba(220, 53, 69, .08);
  }

  /* Dark theme: card header follows theme color, button uses white overlay */
  .recent-card .card-header:not(.is-light) {
    border-bottom: 1px solid rgba(255, 255, 255, .12);
  }

  .recent-card .card-header:not(.is-light) .card-title {
    color: #fff;
  }

  .recent-card .card-header:not(.is-light) .card-title i {
    color: rgba(255, 255, 255, .75);
  }

  .recent-card .card-header:not(.is-light) .btn-tool {
    color: rgba(255, 255, 255, .85);
  }

  .recent-card .card-header:not(.is-light) .btn-tool:hover {
    color: #fff;
    background: rgba(255, 255, 255, .15);
  }

  .recent-card .card-header .card-title {
    font-size: .9rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
    margin: 0;
  }

  .recent-card .card-header .card-title i {
    transition: color .15s ease;
  }

  .recent-card .card-header .btn-tool {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background .15s ease, color .15s ease;
    font-size: .9rem;
    margin-left: auto !important;
    margin-right: 0 !important;
    flex-shrink: 0;
  }

  .recent-list {
    max-height: 320px;
    overflow-y: auto;
  }

  .recent-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 9px 14px;
    border-bottom: 1px solid #f0f2f5;
    cursor: pointer;
    transition: background .12s ease;
    font-size: .9rem;
  }

  .recent-item:last-child {
    border-bottom: none;
  }

  .recent-item:hover {
    background: #f6f9ff;
  }

  .recent-item .ticket {
    font-weight: 600;
    color: #1f2d3d;
  }

  .recent-item .time {
    font-size: .76rem;
    color: #6c757d;
  }

  .recent-empty {
    text-align: center;
    color: #adb5bd;
    font-size: .85rem;
    padding: 20px 0;
    font-style: italic;
  }

  /* ── Quick tip strip ────────────────────────────────────────── */
  .tip-strip {
    background: #f0f6ff;
    border: 1px solid #d0e3ff;
    border-radius: 8px;
    padding: 9px 14px;
    font-size: .82rem;
    color: #1f4e96;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .tip-strip i {
    color: #1a73e8;
  }

  @media (max-width: 767.98px) {
    .scan-input-row {
      flex-direction: column;
    }

    .scan-input-row .btn {
      width: 100%;
    }
  }
</style>

<div class="content-wrapper">

  <section class="content-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-sm-7">
          <h1 class="m-0">
            <i class="fas fa-qrcode text-<?= htmlspecialchars($themeColor) ?> mr-2"></i>
            Scan Form Umum
          </h1>
        </div>
        <div class="col-sm-5">
          <ol class="breadcrumb float-sm-right mb-0">
            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
            <li class="breadcrumb-item active">Scan IKS</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="scan-shell">

        <div class="row">

          <!-- ════ LEFT: Scan Form (primary focus) ════ -->
          <div class="col-lg-7 mb-3">
            <div class="card scan-form-card shadow-sm h-100">

              <div
                class="card-header bg-<?= htmlspecialchars($themeColor) ?> <?= $headerTextClass ?> scan-form-header <?= $isLightTheme ? 'is-light' : '' ?>">
                <div class="icon-wrap">
                  <i class="fas fa-qrcode"></i>
                </div>
                <div>
                  <div class="title">Scan Izin Keluar</div>
                  <div class="sub">Arahkan QR scanner ke barcode tiket IKS / IPC</div>
                </div>
              </div>

              <div class="scan-form-body">
                <form id="scanForm" method="GET" action="scan_action.php" autocomplete="off">
                  <label for="ticketInput"
                    class="font-weight-bold mb-2 d-block text-<?= htmlspecialchars($themeColor) ?>"
                    style="font-size:.85rem;">
                    <i class="fas fa-barcode mr-1"></i> Ticket IKS / IPC
                  </label>

                  <div class="scan-input-row">
                    <input type="text" id="ticketInput" name="ticket" class="form-control" placeholder="IKS-XXXXXX / IPC-XXXXXX"
                      required autofocus spellcheck="false" autocorrect="off" autocapitalize="characters">
                    <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?>" id="btnProses">
                      <i class="fas fa-search mr-1"></i> Proses
                    </button>
                  </div>

                  <div class="helper-text">
                    <i class="fas fa-info-circle"></i> QR scanner USB otomatis mengisi &amp; mengirim.
                  </div>

                  <div id="errorBox" class="alert alert-danger mt-2 mb-0 py-2" style="display:none; font-size:.88rem;">
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- ════ RIGHT: Recent Scans (reference) ════ -->
          <div class="col-lg-5 mb-3">
            <div class="card recent-card shadow-sm h-100">
              <div
                class="card-header bg-<?= htmlspecialchars($themeColor) ?> <?= $headerTextClass ?> <?= $isLightTheme ? 'is-light' : '' ?> d-flex align-items-center justify-content-between">
                <h3 class="card-title m-0">
                  <i class="fas fa-history mr-1"></i> Scan Terakhir
                </h3>
                <button type="button" class="btn btn-tool" id="btnClearRecent" title="Hapus daftar">
                  <i class="fas fa-trash-alt"></i>
                </button>
              </div>
              <div class="recent-list" id="recentList">
                <div class="recent-empty" id="recentEmpty">Belum ada scan</div>
              </div>
              <div class="card-footer bg-white text-muted"
                style="font-size:.78rem; padding:8px 16px; border-top:1px dashed #eef2f7;">
                <i class="fas fa-lightbulb text-warning mr-1"></i>
                Klik salah satu untuk scan ulang
              </div>
            </div>
          </div>

        </div>


      </div>
    </div>
  </section>
</div>

<script>
  (function () {
    const input = document.getElementById('ticketInput');
    const form = document.getElementById('scanForm');
    const errorBox = document.getElementById('errorBox');
    const recentList = document.getElementById('recentList');
    const recentEmpty = document.getElementById('recentEmpty');
    const clearBtn = document.getElementById('btnClearRecent');
    const STORAGE_KEY = 'scan_recent_form_umum_v1';
    const MAX_RECENT = 5;

    // ── Load recent scans ──────────────────────────────────────
    let recent = [];
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) recent = JSON.parse(raw);
      if (!Array.isArray(recent)) recent = [];
    } catch (e) {
      recent = [];
    }
    renderRecent();

    // ── Re-focus input if user clicks anywhere outside form ───
    document.addEventListener('click', function (e) {
      // Jangan refocus kalau user mengklik tombol clear atau list item
      if (e.target.closest('.recent-item') || e.target.closest('#btnClearRecent')) return;
      if (document.activeElement === input) return;
      // Hindari refocus saat user ingin select text
      if (window.getSelection().toString()) return;
      input.focus();
    });

    // ── Esc to clear input ────────────────────────────────────
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        input.value = '';
        input.classList.remove('is-invalid');
        errorBox.style.display = 'none';
        input.focus();
      }
    });

    // ── Submit handler ─────────────────────────────────────────
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const raw = (input.value || '').trim();
      if (!raw) {
        showError('Ticket tidak boleh kosong.');
        return;
      }

      // 1) Deteksi URL lengkap (kasus handheld scanner di PC yang
      //    "mengetik" URL apa adanya ke input). Pada Android,
      //    QR scanner otomatis me-redirect ke URL. Di PC, kita
      //    tirukan perilaku yang sama: redirect ke URL hasil scan.
      if (/^https?:\/\//i.test(raw)) {
        let ticketFromUrl = '';
        try {
          const u = new URL(raw);
          ticketFromUrl = (u.searchParams.get('ticket') || '').toUpperCase();
        } catch (e) { /* URL tidak valid, biarkan redirect apa adanya */ }
        if (ticketFromUrl) {
          saveRecent(ticketFromUrl);
        }
        // Tampilkan feedback singkat sebelum navigasi
        try {
          input.classList.add('is-valid');
          input.readOnly = true;
        } catch (e) { }
        window.location.href = raw;
        return;
      }

      // 2) Normal flow: input hanya berisi kode tiket atau token barcode 6 karakter
      const ticket = raw.toUpperCase();
      const isPrefixValid = ticket.startsWith('IKS-') || ticket.startsWith('IKP-') || ticket.startsWith('IPC-');
      const isTokenValid = /^[A-Z2-9]{6}$/.test(ticket);
      if (!isPrefixValid && !isTokenValid) {
        showError('Format ticket tidak valid. Harus diawali "IKS-", "IKP-", "IPC-", atau kode barcode 6 karakter.');
        input.classList.add('is-invalid');
        return;
      }
      // Save to recent
      saveRecent(ticket);
      // Navigate
      window.location.href = 'scan_action.php?ticket=' + encodeURIComponent(ticket);
    });

    // Reset invalid state saat mengetik
    input.addEventListener('input', function () {
      input.classList.remove('is-invalid');
      errorBox.style.display = 'none';

      // Handheld scanner di PC "mengetik" URL lengkap ke field ini.
      // Begitu URL lengkap terdeteksi, langsung submit form (yang akan
      // auto-redirect ke URL tsb) — tidak perlu menunggu Enter.
      // Pendinginan 80ms agar scanner sempat menyelesaikan ketikannya
      // (umumnya scanner USB mengirim ~50 karakter < 30ms).
      const v = (input.value || '').trim();
      if (/^https?:\/\/\S+$/i.test(v)) {
        clearTimeout(input._qrAutoSubmitTimer);
        input._qrAutoSubmitTimer = setTimeout(function () {
          const cur = (input.value || '').trim();
          if (/^https?:\/\/\S+$/i.test(cur)) {
            form.requestSubmit();
          }
        }, 80);
      }
    });

    // ── Auto-submit saat paste ─────────────────────────────────
    input.addEventListener('paste', function (e) {
      // Beri waktu sejenak agar paste operation selesai
      setTimeout(function () {
        const val = (input.value || '').trim();
        if (val) {
          // Auto-submit form setelah paste
          form.requestSubmit();
        }
      }, 100);
    });

    // ── Recent scans helpers ───────────────────────────────────
    function saveRecent(ticket) {
      recent = recent.filter(function (x) { return x.ticket !== ticket; });
      recent.unshift({ ticket: ticket, time: new Date().toISOString() });
      if (recent.length > MAX_RECENT) recent = recent.slice(0, MAX_RECENT);
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(recent));
      } catch (e) { /* quota exceeded - ignore */ }
    }

    function renderRecent() {
      recentList.innerHTML = '';
      if (recent.length === 0) {
        const div = document.createElement('div');
        div.className = 'recent-empty';
        div.id = 'recentEmpty';
        div.textContent = 'Belum ada scan';
        recentList.appendChild(div);
        return;
      }
      recent.forEach(function (item) {
        const div = document.createElement('div');
        div.className = 'recent-item';
        div.title = 'Klik untuk scan ulang: ' + item.ticket;
        div.innerHTML = '<span class="ticket"><i class="fas fa-barcode mr-2 text-muted"></i>' +
          escapeHtml(item.ticket) + '</span>' +
          '<span class="time">' + formatRelative(item.time) + '</span>';
        div.addEventListener('click', function () {
          input.value = item.ticket;
          input.focus();
          // Auto-submit jika user mau cepat
          form.requestSubmit();
        });
        recentList.appendChild(div);
      });
    }

    clearBtn.addEventListener('click', function () {
      recent = [];
      try { localStorage.removeItem(STORAGE_KEY); } catch (e) { }
      renderRecent();
    });

    function showError(msg) {
      errorBox.textContent = msg;
      errorBox.style.display = 'block';
    }

    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }

    function formatRelative(iso) {
      try {
        const d = new Date(iso);
        const diff = (Date.now() - d.getTime()) / 1000; // detik
        if (diff < 60) return Math.max(1, Math.floor(diff)) + ' dtk lalu';
        if (diff < 3600) return Math.floor(diff / 60) + ' mnt lalu';
        if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
        return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
      } catch (e) {
        return '';
      }
    }

    // ── Initial focus ──────────────────────────────────────────
    // Sedikit delay agar lebih reliable di berbagai browser
    setTimeout(function () { input.focus(); }, 50);
  })();
</script>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>