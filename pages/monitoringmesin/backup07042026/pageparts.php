<?php
declare(strict_types=1);

function mm_render_page_start(string $title, string $subtitle, string $themeColor, ?array $flash, string $activePage): void
{
    global $conn, $serverName, $connectionOptions;

    include __DIR__ . '/../../includes/header.php';
    include __DIR__ . '/../../includes/sidebar.php';
    ob_end_flush();
    ?>
<style>
    .mm-card { border-top: 3px solid #0d6efd; }
    .mm-filter .form-control, .mm-filter .btn, .mm-upload-inline .form-control, .mm-upload-inline .btn { height: 38px; }
    .mm-table-wrap { max-height: 560px; overflow: auto; }
    .mm-sticky th { position: sticky; top: 0; z-index: 2; background: #fff; white-space: nowrap; vertical-align: middle; }
    .mm-sticky th, .mm-sticky td { text-align: center; }
    .mm-sticky th br { content: ""; }
    .mm-code-badge { font-size: .85rem; padding: .35rem .6rem; border-radius: 999px; background: #e9f2ff; color: #0d6efd; font-weight: 600; }
    .mm-value { max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mm-loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(255, 255, 255, 0.78);
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }
    .mm-loading-icon {
        width: 68px;
        height: 68px;
        animation: mm-spin 1.2s linear infinite;
    }
    .mm-loading-text {
        margin-top: 18px;
        font-size: 1.15rem;
        color: #444;
        font-weight: 600;
    }
    .mm-actions {
        white-space: nowrap;
        width: 110px;
    }
    .mm-filter-toolbar {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: .75rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }
    .mm-filter-actions {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
        align-items: center;
    }
    .mm-header-label {
        display: inline-block;
        white-space: pre-line;
        line-height: 1.0;
        min-width: 90px;
    }
    .pagination { margin-bottom: 0; }
    @keyframes mm-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    @media (max-width: 767.98px) {
        .mm-filter-toolbar,
        .mm-filter-actions {
            flex-direction: column;
            align-items: stretch;
        }
        .mm-filter-actions .btn {
            width: 100%;
        }
    }
</style>

<div id="mmLoadingOverlay" class="mm-loading-overlay">
    <img src="https://cdn-icons-png.flaticon.com/512/189/189792.png" alt="Loading" class="mm-loading-icon">
    <div class="mm-loading-text">Memuat data, mohon menunggu...</div>
</div>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0"><?= htmlspecialchars($title) ?></h1>
                    <small class="text-muted"><?= htmlspecialchars($subtitle) ?></small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Monitoring Mesin</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
                    <?= htmlspecialchars($flash['message']) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
    <?php
}

function mm_render_page_end(): void
{
    global $conn, $serverName, $connectionOptions;
    ?>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var overlay = document.getElementById('mmLoadingOverlay');
    var uploadForm = document.getElementById('uploadExcelForm');
    var filterForms = document.querySelectorAll('.mm-filter');
    var deleteForms = document.querySelectorAll('.mm-delete-form');
    var exportTriggers = document.querySelectorAll('.mm-export-form');

    function showOverlay() {
        if (overlay) {
            overlay.style.display = 'flex';
        }
    }

    function hideOverlay() {
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    if (uploadForm && overlay) {
        uploadForm.addEventListener('submit', function () {
            showOverlay();
        });
    }

    filterForms.forEach(function (form) {
        form.addEventListener('submit', function () {
            showOverlay();
        });
    });

    deleteForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var uniqueCode = form.getAttribute('data-unique-code') || '';
            var confirmMessage = form.getAttribute('data-confirm-message') || ('Hapus semua data dengan kode unik ' + uniqueCode + '?');
            var confirmed = window.confirm(confirmMessage);
            if (!confirmed) {
                event.preventDefault();
                return;
            }

            showOverlay();
        });
    });

    exportTriggers.forEach(function (trigger) {
        if (trigger.tagName === 'FORM') {
            trigger.addEventListener('submit', function () {
                showOverlay();
            });
            return;
        }

        trigger.addEventListener('click', function () {
            showOverlay();
        });
    });

    window.addEventListener('pageshow', function () {
        hideOverlay();
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
    <?php
}
