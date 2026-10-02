<?php
// pages/ai_v2/index.php
require_once __DIR__ . '/config.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';

$theme = $_SESSION['Theme'] ?? 'primary';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0">AI PT. Surya Usaha Mandiri</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="upload.php" class="btn btn-sm btn-primary">Upload Dokumen</a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-body">
                    <div class="ai-container ai-v2" data-theme="<?= htmlspecialchars($theme) ?>">
                        <div class="ai-grid single">
                            <div class="panel chat-panel">
                                <div class="chat-header">
                                    <div class="chat-title">
                                        <div class="chat-brand">AI PT. Surya Usaha Mandiri</div>
                                    </div>
                                    <div class="model-select">
                                        <label>Dokumen</label>
                                        <select id="doc-select">
                                            <option value="">Semua Dokumen</option>
                                        </select>
                                    </div>
                                    <div class="model-select">
                                        <label>Model</label>
                                        <select id="model-select">
                                            <?php foreach ($AI_AVAILABLE_MODELS as $key => $label): ?>
                                                <option value="<?= htmlspecialchars($key) ?>" <?= $key === AI_DEFAULT_MODEL ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div id="status" class="ai-status"></div>
                                <div id="messages" class="messages"></div>
                                <div class="composer">
                                    <textarea id="question" placeholder="Tanyakan sesuatu berdasarkan dokumen..."></textarea>
                                    <button id="send" class="btn-send">Kirim</button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<link rel="stylesheet" href="assets/ai.css">
<script src="assets/ai.js"></script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
