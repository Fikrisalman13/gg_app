<?php
session_start();

require_once '../../koneksi.php';

function tableHasColumn($conn, $table, $column) {
    static $cache = [];
    $key = $table . '::' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $tableNameOnly = str_replace(['dbo.', '[', ']'], '', $table);
    // Information schema is more robust across different schemas/configurations
    $sqlCheck = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$tableNameOnly, $column]);
    
    $hasCol = false;
    if ($stmtCheck && sqlsrv_fetch_array($stmtCheck)) {
        $hasCol = true;
    }
    if ($stmtCheck) sqlsrv_free_stmt($stmtCheck);
    
    $cache[$key] = $hasCol;
    return $hasCol;
}

function formBuilderSupportsPublicColumn($conn) {
    return tableHasColumn($conn, 'Form_Dynamic_Templates', 'is_public');
}

function generatePatternFromPlaceholder($p) {
    if (empty($p)) return '';
    $regex = '^';
    $chars = str_split($p);
    $i = 0;
    while ($i < count($chars)) {
        $curr = $chars[$i];
        if (ctype_alpha($curr)) {
            $count = 0;
            while ($i < count($chars) && ctype_alpha($chars[$i])) {
                $count++;
                $i++;
            }
            $regex .= '[A-Za-z]{' . $count . '}';
        } elseif (ctype_digit($curr)) {
            $count = 0;
            while ($i < count($chars) && ctype_digit($chars[$i])) {
                $count++;
                $i++;
            }
            $regex .= '\d{' . $count . '}';
        } else {
            // Escape special regex characters
            $regex .= preg_quote($curr, '/');
            $i++;
        }
    }
    return $regex . '$';
}

$supportsPublicColumn = formBuilderSupportsPublicColumn($conn);

$templateSlug = isset($_GET['template_name']) ? trim($_GET['template_name']) : null;
$templateIdParam = $_GET['id'] ?? null; // legacy

if (!$templateSlug && !$templateIdParam) {
    die("Template tidak ditemukan.");
}

if ($templateSlug) {
    $sql = "SELECT * FROM Form_Dynamic_Templates WHERE template_name = ? AND is_active = 1";
    $stmt = sqlsrv_query($conn, $sql, [$templateSlug]);
} else {
    $sql = "SELECT * FROM Form_Dynamic_Templates WHERE id = ? AND is_active = 1";
    $stmt = sqlsrv_query($conn, $sql, [$templateIdParam]);
}
$template = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$template) {
    die("Template tidak ditemukan.");
}

$templateId = $template['id'];

// Simplify: just check the value if it exists, SELECT * already handled the column presence
$isPublicForm = !empty($template['is_public']);
$isLoggedIn = isset($_SESSION['UserId']);

if (!$isPublicForm && !$isLoggedIn) {
    header("Location: ../../login.php");
    exit();
}

$fields = json_decode($template['fields_json'], true);
if (!is_array($fields)) {
    $fields = [];
}
$fieldOnlyCount = 0;
$exampleCount = 0;
foreach ($fields as $item) {
    $itemType = $item['item_type'] ?? 'field';
    if ($itemType === 'example') {
        $exampleCount++;
    } else {
        $fieldOnlyCount++;
    }
}

// Edit Mode Logic
$editSubmissionId = $_GET['edit_submission_id'] ?? null;
$existingData = null;
if ($editSubmissionId && $isLoggedIn) {
    $hasSubmissionId = tableHasColumn($conn, 'Form_Dynamic_Submissions', 'submission_id');
    $hasId = tableHasColumn($conn, 'Form_Dynamic_Submissions', 'id');
    $pkCol = $hasSubmissionId ? 'submission_id' : ($hasId ? 'id' : null);

    if ($pkCol) {
        // Coba ambil data yang sesuai dengan template_id dulu (lebih aman)
        $sqlEdit = "SELECT * FROM Form_Dynamic_Submissions WHERE {$pkCol} = ? AND template_id = ?";
        $stmtEdit = sqlsrv_query($conn, $sqlEdit, [$editSubmissionId, $templateId]);
        $rowEdit = null;
        if ($stmtEdit && ($rowEdit = sqlsrv_fetch_array($stmtEdit, SQLSRV_FETCH_ASSOC))) {
            // Data ditemukan sesuai template
        } else {
            // Fallback: Jika tidak ketemu dengan template_id, coba cari ID-nya saja
            // Mungkin ada pergeseran template_id atau template_name di URL tidak pas
            $sqlFallback = "SELECT * FROM Form_Dynamic_Submissions WHERE {$pkCol} = ?";
            $stmtFallback = sqlsrv_query($conn, $sqlFallback, [$editSubmissionId]);
            if ($stmtFallback && ($rowEdit = sqlsrv_fetch_array($stmtFallback, SQLSRV_FETCH_ASSOC))) {
                // Jika ketemu, pastikan template_id-nya kita update agar form field-nya pas
                if ($rowEdit['template_id'] != $templateId) {
                    $templateId = $rowEdit['template_id'];
                    // Ambil ulang template settings jika berbeda
                    $sqlTpl = "SELECT * FROM Form_Dynamic_Templates WHERE id = ?";
                    $stmtTpl = sqlsrv_query($conn, $sqlTpl, [$templateId]);
                    if ($stmtTpl && ($newTpl = sqlsrv_fetch_array($stmtTpl, SQLSRV_FETCH_ASSOC))) {
                        $template = $newTpl;
                        $fields = json_decode($template['fields_json'], true) ?? [];
                    }
                }
            }
        }

        if ($rowEdit) {
            $existingData = json_decode($rowEdit['submission_data'] ?? '', true);
        }
    }
}

if ($isLoggedIn) {
    include '../../includes/header.php';
    include '../../includes/sidebar.php';
}

$themeColor = $isLoggedIn ? ($_SESSION['Theme'] ?? 'primary') : 'primary';
$themeColorKey = strtolower(preg_replace('/[^a-z]/', '', $themeColor));
$formAccentPalette = [
    'primary' => '#0062ff',
    'secondary' => '#6c757d',
    'success' => '#1aa179',
    'danger' => '#d64545',
    'warning' => '#f4a100',
    'info' => '#17a2b8',
    'light' => '#adb5bd',
    'dark' => '#212529',
    'indigo' => '#6610f2',
    'navy' => '#001f3f',
    'purple' => '#6f42c1',
    'pink' => '#d63384',
    'teal' => '#20c997',
    'orange' => '#fd7e14',
    'olive' => '#3d9970',
    'lime' => '#01ff70',
    'fuchsia' => '#f012be',
    'maroon' => '#85144b'
];
$formAccentHex = $formAccentPalette[$themeColorKey] ?? '#0062ff';
?>

<?php if (!$isLoggedIn): ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($template['template_name']); ?></title>
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
</head>
<body class="hold-transition layout-top-nav bg-light">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-<?php echo htmlspecialchars($themeColor); ?> navbar-dark">
        <div class="container">
            <span class="navbar-brand mb-0 h5">Support App • Form Publik</span>
        </div>
    </nav>
<?php endif; ?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><?php echo htmlspecialchars($template['template_name']); ?></h1>
                    <?php if ($isPublicForm): ?>
                <span class="badge badge-success">Form Publik</span>
                    <?php endif; ?>
                </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php 
            $successMessage = null;
            $successViewLink = null;
            if (isset($_SESSION['success'])) {
                $successMessage = $_SESSION['success'];
                unset($_SESSION['success']);
            }
            if (isset($_SESSION['success_view_link'])) {
                $successViewLink = $_SESSION['success_view_link'];
                unset($_SESSION['success_view_link']);
            }
            ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-9 mx-auto">
                    <div class="form-shell card border-0 shadow-form" style="--form-accent: <?php echo htmlspecialchars($formAccentHex); ?>;">
                        <div class="form-hero">
                            <div>
                                <p class="form-hero-eyebrow">
                                    <?php echo $isPublicForm ? 'Form Publik' : 'Internal Form'; ?>
                                </p>
                                <h2 class="form-hero-title mb-2"><?php echo htmlspecialchars($template['template_name']); ?></h2>
                                <?php if ($existingData): ?>
                                    <span class="badge badge-warning mb-2">Mode Edit: Mengubah Data</span>
                                <?php endif; ?>
                                <?php if (!empty($template['template_description'])): ?>
                                    <p class="form-hero-desc mb-0"><?php echo nl2br(htmlspecialchars($template['template_description'])); ?></p>
                                <?php else: ?>
                                    <p class="form-hero-desc mb-0 text-muted"></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form id="dynamic-form" class="form-modern" action="process_submission.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="template_id" value="<?php echo htmlspecialchars($templateId); ?>">
                            <input type="hidden" name="template_name" value="<?php echo htmlspecialchars($template['template_name']); ?>">
                            <?php if ($existingData): ?>
                                <input type="hidden" name="edit_submission_id" value="<?php echo htmlspecialchars($editSubmissionId); ?>">
                            <?php endif; ?>
                            <?php if (!$isLoggedIn && $isPublicForm): ?>
                                <input type="hidden" name="public_submitter_name" id="public_submitter_name" value="">
                            <?php endif; ?>
                            <div class="form-body">
                                <?php 
                                $fieldCounter = 0; // Counter for actual fields only
                                foreach ($fields as $index => $item): 
                                ?>
                                    <?php if (isset($item['item_type']) && $item['item_type'] === 'example'): ?>
                                        <!-- Example Section -->
                                        <div class="form-example-card">
                                            <?php if (!empty($item['description'])): ?>
                                                <p class="form-example-text mb-3">
                                                    <i class="fas fa-lightbulb text-warning mr-2"></i>
                                                    <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if (!empty($item['example_image'])): ?>
                                                <?php 
                                                    $exampleFile = htmlspecialchars($item['example_image']);
                                                    $exampleSrc = "../../uploads/form_examples/{$exampleFile}";
                                                ?>
                                                <a href="<?php echo $exampleSrc; ?>" class="form-example-image-link" data-example-image>
                                                    <img src="<?php echo $exampleSrc; ?>" 
                                                         class="img-thumbnail form-example-image" 
                                                         alt="Contoh">
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <!-- Regular Field -->
                                        <?php 
                                        // For backward compatibility, treat items without item_type as fields
                                        $field = $item;
                                        $currentFieldIndex = $fieldCounter; // Use counter for field name
                                        $fieldCounter++; // Increment counter
                                        ?>
                                        <div class="form-field-block">
                                            <label class="form-field-label">
                                                <?php echo htmlspecialchars($field['label'] ?? '(Tanpa Label)'); ?>
                                                <?php if (!empty($field['required'])): ?>
                                                    <span class="badge badge-pill badge-required">Wajib</span>
                                                <?php endif; ?>
                                            </label>
                                            
                                            <?php 
                                            // Pre-fill logic
                                            $fieldValue = '';
                                            $existingFile = null;
                                            if ($existingData && isset($field['label']) && isset($existingData[$field['label']])) {
                                                $fieldValue = $existingData[$field['label']]['value'] ?? '';
                                                if ($field['type'] === 'file' && !empty($fieldValue)) {
                                                    $existingFile = $existingData[$field['label']];
                                                }
                                            }
                                            ?>

                                            <?php if ($field['type'] === 'text'): ?>
                                                <?php 
                                                $fieldAttr = ($field['required'] ? 'required ' : '');
                                                $fieldFormat = !empty($field['placeholder']) ? $field['placeholder'] : '';
                                                $fieldVisualPlaceholder = !empty($field['visual_placeholder']) ? $field['visual_placeholder'] : '';

                                                if (!empty($fieldFormat)) {
                                                    $fieldAttr .= 'pattern="'.htmlspecialchars(generatePatternFromPlaceholder($fieldFormat)).'" ';
                                                    $fieldAttr .= 'minlength="'.strlen($fieldFormat).'" ';
                                                }
                                                
                                                if (!empty($fieldVisualPlaceholder)) {
                                                    $fieldAttr .= 'placeholder="'.htmlspecialchars($fieldVisualPlaceholder).'" ';
                                                }
                                                
                                                $fieldAttr .= (!empty($field['maxlength']) ? 'maxlength="'.htmlspecialchars($field['maxlength']).'" ' : '');
                                                
                                                if (!empty($field['capitalization'])) {
                                                    if ($field['capitalization'] === 'uppercase') {
                                                        $fieldAttr .= 'oninput="this.value = this.value.toUpperCase()" style="text-transform: uppercase" ';
                                                    } elseif ($field['capitalization'] === 'lowercase') {
                                                        $fieldAttr .= 'oninput="this.value = this.value.toLowerCase()" style="text-transform: lowercase" ';
                                                    }
                                                }
                                                ?>
                                                <input type="text" class="form-control form-control-modern" name="field_<?php echo $currentFieldIndex; ?>" value="<?php echo htmlspecialchars($fieldValue); ?>" <?php echo $fieldAttr; ?>>
                                                <?php if (!empty($fieldFormat)): ?>
                                                    <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Contoh: <?php echo htmlspecialchars($fieldFormat); ?></small>
                                                <?php endif; ?>
                                            
                                            <?php elseif ($field['type'] === 'textarea'): ?>
                                                <?php 
                                                $fieldAttr = ($field['required'] ? 'required ' : '');
                                                $fieldAttr .= (!empty($field['minlength']) ? 'minlength="'.htmlspecialchars($field['minlength']).'" ' : '');
                                                $fieldAttr .= (!empty($field['maxlength']) ? 'maxlength="'.htmlspecialchars($field['maxlength']).'" ' : '');
                                                $fieldVisualPlaceholder = !empty($field['visual_placeholder']) ? $field['visual_placeholder'] : '';
                                                if (!empty($fieldVisualPlaceholder)) {
                                                    $fieldAttr .= 'placeholder="'.htmlspecialchars($fieldVisualPlaceholder).'" ';
                                                }
                                                $fieldFormat = !empty($field['placeholder']) ? $field['placeholder'] : '';
                                                if (!empty($fieldFormat)) {
                                                    $fieldAttr .= 'data-pattern="'.htmlspecialchars(generatePatternFromPlaceholder($fieldFormat)).'" ';
                                                }
                                                
                                                if (!empty($field['capitalization'])) {
                                                    if ($field['capitalization'] === 'uppercase') {
                                                        $fieldAttr .= 'oninput="this.value = this.value.toUpperCase()" style="text-transform: uppercase" ';
                                                    } elseif ($field['capitalization'] === 'lowercase') {
                                                        $fieldAttr .= 'oninput="this.value = this.value.toLowerCase()" style="text-transform: lowercase" ';
                                                    }
                                                }
                                                ?>
                                                <textarea class="form-control form-control-modern" name="field_<?php echo $currentFieldIndex; ?>" rows="3" <?php echo $fieldAttr; ?>><?php echo htmlspecialchars($fieldValue); ?></textarea>
                                                <?php if (!empty($fieldFormat)): ?>
                                                    <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Contoh: <?php echo htmlspecialchars($fieldFormat); ?></small>
                                                <?php endif; ?>
                                            
                                            <?php elseif ($field['type'] === 'number'): ?>
                                                <?php 
                                                $fieldAttr = ($field['required'] ? 'required ' : '');
                                                
                                                // Block 'e', '+', '-', '.' characters
                                                $fieldAttr .= 'onkeydown="return ![\'e\',\'E\',\'+\',\'-\',\'.\'].includes(event.key)" ';
                                                
                                                if (!empty($field['maxlength'])) {
                                                    $fieldAttr .= 'maxlength="'.htmlspecialchars($field['maxlength']).'" ';
                                                    $fieldAttr .= 'oninput="if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength);" ';
                                                }
                                                $fieldVisualPlaceholder = !empty($field['visual_placeholder']) ? $field['visual_placeholder'] : '';
                                                if (!empty($fieldVisualPlaceholder)) {
                                                    $fieldAttr .= 'placeholder="'.htmlspecialchars($fieldVisualPlaceholder).'" ';
                                                }
                                                ?>
                                                <input type="number" class="form-control form-control-modern" name="field_<?php echo $currentFieldIndex; ?>" value="<?php echo htmlspecialchars($fieldValue); ?>" <?php echo $fieldAttr; ?>>
                                            
                                            <?php elseif ($field['type'] === 'date'): ?>
                                                <input type="date" class="form-control form-control-modern" name="field_<?php echo $currentFieldIndex; ?>" value="<?php echo htmlspecialchars($fieldValue); ?>" <?php echo $field['required'] ? 'required' : ''; ?>>
                                            
                                            <?php elseif ($field['type'] === 'select'): ?>
                                                <select class="form-control form-control-modern" name="field_<?php echo $currentFieldIndex; ?>" <?php echo $field['required'] ? 'required' : ''; ?>>
                                                    <option value="">-- Pilih --</option>
                                                    <?php foreach ($field['options'] as $option): ?>
                                                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($fieldValue === $option) ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            
                                            <?php elseif ($field['type'] === 'file'): ?>
                                                <?php if ($existingFile): ?>
                                                    <div class="mb-2">
                                                        <small class="text-muted">File saat ini: </small>
                                                        <a href="../../uploads/dynamic_forms/<?php echo htmlspecialchars($existingFile['value']); ?>" target="_blank" class="btn btn-xs btn-outline-info">
                                                            <i class="fas fa-file"></i> <?php echo htmlspecialchars($existingFile['original_name'] ?? 'Lihat File'); ?>
                                                        </a>
                                                        <input type="hidden" name="existing_file_<?php echo $currentFieldIndex; ?>" value="<?php echo htmlspecialchars(json_encode($existingFile)); ?>">
                                                    </div>
                                                <?php endif; ?>
                                                <div class="custom-file custom-file-modern">
                                                    <input type="file" class="custom-file-input" name="field_<?php echo $currentFieldIndex; ?>" id="file_<?php echo $currentFieldIndex; ?>" <?php echo ($field['required'] && !$existingFile) ? 'required' : ''; ?>>
                                                    <label class="custom-file-label" for="file_<?php echo $currentFieldIndex; ?>"><?php echo $existingFile ? 'Ganti file (opsional)' : 'Pilih file'; ?></label>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-actions card-footer d-flex flex-column flex-md-row align-items-stretch align-items-md-center gap-3 gap-md-4">
                                <?php if ($isLoggedIn): ?>
                                    <a href="list_templates.php" class="btn btn-outline-secondary btn-lg mb-2 mb-md-0 shadow-sm">Kembali</a>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor); ?> btn-lg ml-md-auto px-4 shadow-sm"><?php echo $existingData ? 'Simpan Perubahan' : 'Kirim Form'; ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
.content-wrapper {
    background: linear-gradient(180deg, #f4f7fb 0%, #eef2f7 100%);
    min-height: 100vh;
    padding-bottom: 40px;
}
.themed-header {
    background: linear-gradient(120deg, rgba(0, 0, 0, 0.15), rgba(0, 0, 0, 0.05)), var(--theme-color, #0062ff);
    color: #fff;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    padding: 1.75rem 0;
}
.themed-header h1 {
    color: #fff;
    font-weight: 700;
}
.header-kicker {
    letter-spacing: 0.32em;
    font-size: 0.7rem;
    opacity: 0.7;
    color: rgba(255, 255, 255, 0.85);
}
.badge-theme {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.35);
    color: #fff;
    padding: 0.45rem 1.2rem;
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.85rem;
}
.badge-theme-muted {
    background: rgba(255, 255, 255, 0.12);
}
.form-shell {
    border-radius: 28px;
    overflow: hidden;
    background: #fff;
}
.shadow-form {
    box-shadow: 0 25px 70px rgba(15, 28, 45, 0.12);
}
.form-hero {
    padding: 2rem 2.5rem;
    background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.2) 0%, transparent 50%), var(--form-accent, #0062ff);
    color: #fff;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
@media (min-width: 768px) {
    .form-hero { flex-direction: row; justify-content: space-between; align-items: center; }
}
.form-hero-eyebrow {
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-size: 0.75rem;
    opacity: 0.8;
    margin-bottom: 0.35rem;
}
.form-hero-title {
    font-size: 1.9rem;
    font-weight: 700;
}
.form-hero-desc {
    font-size: 0.98rem;
    opacity: 0.9;
}
.form-hero-meta {
    display: flex;
    gap: 0.85rem;
    flex-wrap: wrap;
}
.form-meta-pill {
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.35);
    border-radius: 14px;
    padding: 0.65rem 1rem;
    min-width: 130px;
    text-align: center;
    font-size: 0.85rem;
}
.form-meta-pill strong {
    display: block;
    font-size: 1.4rem;
    line-height: 1.2;
}
.form-modern {
    display: flex;
    flex-direction: column;
}
.form-body {
    padding: 2.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.form-field-block {
    background: #f8faff;
    border: 1px solid rgba(79, 114, 205, 0.15);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.form-field-block:focus-within {
    border-color: var(--form-accent, #0062ff);
    box-shadow: 0 12px 25px rgba(0, 98, 255, 0.08);
}
.form-field-label {
    font-weight: 600;
    font-size: 0.95rem;
    color: #1f2d3d;
    margin-bottom: 0.6rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.badge-required {
    background: #DC3545;
    color: #fff;
    font-size: 0.65rem;
    text-transform: uppercase;
    padding: 0.2em 0.4em;
    border-radius: 0.25rem;
}

.form-control-modern,
.custom-file-modern .custom-file-label {
    border-radius: 12px;
    border: 1px solid #d7deea;
    padding: 0.65rem 0.9rem;
    box-shadow: none;
    min-height: 48px;
    line-height: 1.4;
}
.form-control-modern:focus,
.custom-file-modern .custom-file-input:focus + .custom-file-label {
    border-color: var(--form-accent, #0062ff);
    box-shadow: 0 0 0 0.1rem rgba(0, 98, 255, 0.15);
}
.form-control-modern.select-modern,
select.form-control-modern {
    padding-right: 2.5rem;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23212b36' d='M6 8a.996.996 0 0 1-.707-.293l-5-5A.999.999 0 1 1 1.707.293L6 4.586 10.293.293A.999.999 0 1 1 11.707 1.7l-5 5A.996.996 0 0 1 6 8z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: calc(100% - 1rem) 50%;
    background-size: 14px 9px;
}
.custom-file-modern .custom-file-label::after {
    content: "Telusuri";
    border-radius: 0 12px 12px 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 1.25rem;
    min-height: 100%;
}
.custom-file-modern .custom-file-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}
.form-example-card {
    border-radius: 18px;
    padding: 1.5rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.8)), var(--form-accent, #0062ff);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #0c1b33;
    box-shadow: inset 0 0 20px rgba(255, 255, 255, 0.25);
}
.form-example-text {
    font-weight: 500;
    color: #063056;
}
.form-example-image-link {
    display: inline-block;
    line-height: 0;
}
.form-example-image {
    cursor: zoom-in;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
    border-radius: 14px;
    max-width: 100%;
}
.form-example-image:hover {
    box-shadow: 0 18px 28px rgba(0, 0, 0, 0.18);
    transform: translateY(-2px);
}
.form-actions {
    border-top: 1px solid rgba(15, 28, 45, 0.08);
    background: #fdfdfd;
    padding: 1.5rem 2.5rem;
}
.form-actions .btn {
    border-radius: 999px;
}
.form-actions .btn-outline-secondary {
    border-width: 2px;
}
.form-example-image-modal .modal-dialog {
    max-width: 95vw;
}
.form-example-image-modal .modal-content {
    background: transparent;
    border: none;
    box-shadow: none;
}
.form-example-image-modal .modal-body {
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 60px;
}
.form-example-image-stage {
    position: relative;
    display: inline-block;
    line-height: 0;
}
.form-example-image-modal img {
    max-width: 90vw;
    max-height: 85vh;
    border-radius: 12px;
    box-shadow: 0 18px 35px rgba(0, 0, 0, 0.35);
}
.form-example-image-modal .form-example-image-close {
    position: absolute;
    right: 12px;
    top: 8px;
    font-size: 1.75rem;
    color: #fff;
    opacity: 0.9;
    text-shadow: 0 2px 6px rgba(0, 0, 0, 0.4);
    border: none;
    background: transparent;
}
.form-example-image-modal .form-example-image-close:hover {
    opacity: 1;
}
@media (max-width: 575.98px) {
    .form-body { padding: 1.75rem; }
    .form-hero { padding: 1.75rem; }
    .form-actions { padding: 1.5rem; }
}
</style>

<div class="modal fade form-example-image-modal" id="formExampleImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-body">
                <div class="form-example-image-stage">
                    <button type="button" class="close form-example-image-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <img src="" alt="Preview" id="formExampleImageModalImg" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($isLoggedIn): ?>
    <?php include '../../includes/footer.php'; ?>
<?php else: ?>
    <footer class="main-footer text-center py-3">
        <small>&copy; <?php echo date('Y'); ?> Support App</small>
    </footer>
</div>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/gg_app/plugins/AdminLTE-3.2.0/dist/js/adminlte.min.js"></script>
<?php endif; ?>
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="../../plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.css">
<!-- SweetAlert2 JS -->
<script src="../../plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isPublicStandalone = <?php echo (!$isLoggedIn && $isPublicForm) ? 'true' : 'false'; ?>;
    let pendingSubmit = null;
    let formExampleImageModalInstance = null;
    let formExampleImageModalImg = null;

    function ensureExampleImageModal() {
        if (formExampleImageModalInstance && formExampleImageModalImg) {
            return formExampleImageModalInstance;
        }
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return null;
        }
        const modalEl = document.getElementById('formExampleImageModal');
        if (!modalEl) {
            return null;
        }
        formExampleImageModalInstance = new bootstrap.Modal(modalEl);
        formExampleImageModalImg = modalEl.querySelector('#formExampleImageModalImg');
        return formExampleImageModalInstance;
    }

    function openExampleImage(src, altText) {
        if (!src) { return; }
        const modal = ensureExampleImageModal();
        if (modal && formExampleImageModalImg) {
            formExampleImageModalImg.src = src;
            formExampleImageModalImg.alt = altText || 'Preview';
            modal.show();
        } else {
            window.open(src, '_blank');
        }
    }

    document.querySelectorAll('[data-example-image]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const img = this.querySelector('img');
            const src = img ? img.getAttribute('src') : this.getAttribute('href');
            const alt = img ? img.getAttribute('alt') : '';
            openExampleImage(src, alt);
        });
    });

    if (isPublicStandalone) {
        const modalHtml = `
            <div class="modal fade" id="publicNameModal" tabindex="-1" role="dialog" aria-labelledby="publicNameModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                            <h5 class="modal-title" id="publicNameModalLabel">Masukkan Nama Anda</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="publicNameInput">Nama Lengkap</label>
                                <input type="text" class="form-control" id="publicNameInput" placeholder="Contoh: Andi Setiawan" required>
                               
                            </div>
                            <div class="form-group mb-0">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="rememberPublicName">
                                    <label class="custom-control-label" for="rememberPublicName">Ingat nama ini untuk pengisian berikutnya</label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="button" class="btn btn-primary" id="savePublicName">Lanjutkan</button>
                        </div>
                    </div>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const form = document.getElementById('dynamic-form');
        const hiddenName = document.getElementById('public_submitter_name');
        const storageKey = 'formBuilderPublicName';

        if (form && hiddenName) {
            form.addEventListener('submit', function(e) {
                // Check standard validity first
                if (!form.checkValidity()) {
                    return;
                }
                
                // Check custom textarea validation
                let isCustomValid = true;
                const textareas = form.querySelectorAll('textarea[data-pattern]');
                textareas.forEach(textarea => {
                    const patternAttr = textarea.getAttribute('data-pattern');
                    if (patternAttr && textarea.value) {
                         const pattern = new RegExp(patternAttr);
                         if (!pattern.test(textarea.value)) {
                            isCustomValid = false;
                            // Trigger the error display logic by dispatching input event or just letting the other listener handle it?
                            // The other listener is also on 'submit', so it will run.
                            // But we need to know NOW if it's invalid.
                            textarea.classList.add('is-invalid');
                         }
                    }
                });
                
                if (!isCustomValid) {
                    return;
                }

                if (!hiddenName.value.trim()) {
                    e.preventDefault();
                    pendingSubmit = form;
                    $('#publicNameModal').modal('show');
                    setTimeout(() => document.getElementById('publicNameInput').focus(), 300);
                }
            });
        }

        $('#publicNameModal').on('shown.bs.modal', function () {
            const savedName = localStorage.getItem(storageKey);
            const nameInput = document.getElementById('publicNameInput');
            const rememberCheckbox = document.getElementById('rememberPublicName');
            if (savedName && nameInput && rememberCheckbox && !hiddenName.value.trim()) {
                nameInput.value = savedName;
                rememberCheckbox.checked = true;
            }
            if (nameInput) {
                nameInput.focus();
            }
        });

        document.addEventListener('click', function(e) {
            if (e.target && e.target.id === 'savePublicName') {
                const input = document.getElementById('publicNameInput');
                const rememberCheckbox = document.getElementById('rememberPublicName');
                if (input && input.value.trim()) {
                    hiddenName.value = input.value.trim();
                    if (rememberCheckbox && rememberCheckbox.checked) {
                        localStorage.setItem(storageKey, hiddenName.value);
                    } else {
                        localStorage.removeItem(storageKey);
                    }
                    $('#publicNameModal').modal('hide');
                    if (pendingSubmit) {
                        pendingSubmit.requestSubmit();
                        pendingSubmit = null;
                    }
                } else if (input) {
                    input.classList.add('is-invalid');
                }
            }
        });
        


        $('#publicNameModal').on('hidden.bs.modal', function () {
            const input = document.getElementById('publicNameInput');
            if (input) {
                input.classList.remove('is-invalid');
                input.value = '';
            }
            const rememberCheckbox = document.getElementById('rememberPublicName');
            if (rememberCheckbox && !rememberCheckbox.checked) {
                localStorage.removeItem(storageKey);
            }
        });
    }

    // Custom validation for textarea patterns (Global Scope)
    {
        const form = document.getElementById('dynamic-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                // If the form is already prevented (e.g. by public name modal), don't validate yet? 
                // Actually we should validate.
                
                let isValid = true;
                const textareas = form.querySelectorAll('textarea[data-pattern]');
                
                textareas.forEach(textarea => {
                    const patternAttr = textarea.getAttribute('data-pattern');
                    if (patternAttr && textarea.value) {
                         // Remove start/end anchors if they are double applied? 
                         // generatePatternFromPlaceholder puts ^ and $. RegExp handles them.
                         const pattern = new RegExp(patternAttr);
                         if (!pattern.test(textarea.value)) {
                            isValid = false;
                            textarea.classList.add('is-invalid');
                            // Add error message if not exists
                            let errorDiv = textarea.nextElementSibling;
                            if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
                                 errorDiv = document.createElement('div');
                                 errorDiv.className = 'invalid-feedback';
                                 errorDiv.innerText = 'Format tidak sesuai. Periksa contoh format.';
                                 textarea.parentNode.insertBefore(errorDiv, textarea.nextSibling);
                            }
                         } else {
                             textarea.classList.remove('is-invalid');
                         }
                    } else {
                         textarea.classList.remove('is-invalid');
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Scroll to first error
                    const firstError = form.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });

            // Clear validation on input
            form.querySelectorAll('textarea[data-pattern]').forEach(textarea => {
                textarea.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid')) {
                        this.classList.remove('is-invalid');
                    }
                });
            });
        }
    }

    document.querySelectorAll('.custom-file-input').forEach(function(input) {
        input.addEventListener('change', function() {
            var fileName = this.value.split('\\').pop();
            var label = this.nextElementSibling;
            if (label) {
                label.textContent = fileName || 'Pilih file';
            }
        });
    });
    
    // Show success message with SweetAlert2
    <?php if ($successMessage): ?>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: '<?php echo addslashes($successMessage); ?>',
        confirmButtonColor: '#28a745',
        <?php if ($successViewLink): ?>
        showCancelButton: true,
        cancelButtonText: 'Tutup',
        confirmButtonText: 'Lihat Hasil',
        <?php else: ?>
        timer: 3000,
        timerProgressBar: true,
        <?php endif; ?>
    }).then((result) => {
        <?php if ($successViewLink): ?>
        if (result.isConfirmed) {
            window.open('<?php echo htmlspecialchars($successViewLink); ?>', '_blank');
        }
        <?php endif; ?>
    });
    <?php endif; ?>
});
</script>
<?php if (!$isLoggedIn): ?>
</body>
</html>
<?php endif; ?>
