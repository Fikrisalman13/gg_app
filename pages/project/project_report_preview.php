<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/sidebar.php');

function previewH($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$projectId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$previewUrl = 'export_project_report.php?id=' . $projectId;
$downloadUrl = 'export_project_report.php?id=' . $projectId . '&download=1';
?>

<style>
    .project-report-preview {
        min-height: calc(100vh - 57px);
    }
    .project-report-preview .preview-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        background: #fff;
        border: 1px solid #dfe7f0;
        border-radius: 8px;
        padding: 12px 14px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .07);
        margin-bottom: 12px;
    }
    .project-report-preview .preview-toolbar h1 {
        font-size: 1.2rem;
        font-weight: 800;
        margin: 0;
        color: #1f2937;
    }
    .project-report-preview .preview-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .project-report-preview .preview-actions .btn {
        border-radius: 6px;
        font-weight: 700;
    }
    .project-report-preview .pdf-frame-wrap {
        background: #475569;
        border: 1px solid #d7e1ec;
        border-radius: 8px;
        overflow: hidden;
        height: calc(100vh - 190px);
        min-height: 560px;
    }
    .project-report-preview iframe {
        width: 100%;
        height: 100%;
        border: 0;
        background: #fff;
    }
    @media (max-width: 575.98px) {
        .project-report-preview .preview-actions,
        .project-report-preview .preview-actions .btn {
            width: 100%;
        }
        .project-report-preview .pdf-frame-wrap {
            height: calc(100vh - 250px);
            min-height: 480px;
        }
    }
</style>

<div class="content-wrapper project-report-preview">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Preview Report Project</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="project.php">Project</a></li>
                        <li class="breadcrumb-item active">Report Preview</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="preview-toolbar">
                <h1><i class="fas fa-file-pdf mr-2 text-danger"></i>Report Project</h1>
                <div class="preview-actions">
                    <a href="view_project.php?id=<?php echo (int) $projectId; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                    </a>
                    <a href="<?php echo previewH($downloadUrl); ?>" class="btn btn-danger">
                        <i class="fas fa-download mr-1"></i> Download PDF
                    </a>
                </div>
            </div>

            <?php if ($projectId <= 0) { ?>
                <div class="alert alert-danger">Project ID tidak valid.</div>
            <?php } else { ?>
                <div class="pdf-frame-wrap">
                    <iframe src="<?php echo previewH($previewUrl); ?>" title="Preview Report Project"></iframe>
                </div>
            <?php } ?>
        </div>
    </section>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/includes/footer.php'); ?>
