<?php
session_start();
require_once '../../koneksi.php';

if (!isset($_SESSION['UserId'])) {
    header("Location: ../../login.php");
    exit();
}

function deleteUploadedFile($baseDir, $fileName) {
    if (!$fileName || !$baseDir) {
        return;
    }
    $safeName = basename($fileName);
    if ($safeName === '' || $safeName === '.' || $safeName === '..') {
        return;
    }
    $fullPath = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeName;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

$dynamicFormsDir = realpath(__DIR__ . '/../../uploads/dynamic_forms');
if ($dynamicFormsDir === false) {
    $dynamicFormsDir = __DIR__ . '/../../uploads/dynamic_forms';
}
$exampleDir = realpath(__DIR__ . '/../../uploads/form_examples');
if ($exampleDir === false) {
    $exampleDir = __DIR__ . '/../../uploads/form_examples';
}

if (!isset($_GET['template_name']) && !isset($_GET['id'])) {
    $_SESSION['error'] = "ID Template tidak ditemukan.";
    header("Location: list_templates.php");
    exit();
}

$templateNameParam = isset($_GET['template_name']) ? trim($_GET['template_name']) : null;
$templateIdParam = isset($_GET['id']) ? trim($_GET['id']) : null;
$template = null;

if ($templateNameParam) {
    $sqlFetch = "SELECT id, template_name, fields_json FROM Form_Dynamic_Templates WHERE template_name = ?";
    $stmtFetch = sqlsrv_query($conn, $sqlFetch, [$templateNameParam]);
} else {
    $sqlFetch = "SELECT id, template_name, fields_json FROM Form_Dynamic_Templates WHERE id = ?";
    $stmtFetch = sqlsrv_query($conn, $sqlFetch, [$templateIdParam]);
}

if ($stmtFetch && ($template = sqlsrv_fetch_array($stmtFetch, SQLSRV_FETCH_ASSOC))) {
    sqlsrv_free_stmt($stmtFetch);
} else {
    if ($stmtFetch) {
        sqlsrv_free_stmt($stmtFetch);
    }
    $_SESSION['error'] = "Template tidak ditemukan.";
    header("Location: list_templates.php");
    exit();
}

$templateId = $template['id'];
$fieldsJson = $template['fields_json'] ?? '';

// Hapus gambar contoh yang disimpan di template
$fieldsDecoded = json_decode($fieldsJson, true);
if (is_array($fieldsDecoded)) {
    foreach ($fieldsDecoded as $fieldItem) {
        if (isset($fieldItem['item_type']) && $fieldItem['item_type'] === 'example') {
            $exampleImage = $fieldItem['example_image'] ?? '';
            deleteUploadedFile($exampleDir, $exampleImage);
        }
    }
}

// Kumpulkan file upload submissions sebelum data dihapus
$sqlSubmissions = "SELECT submission_data FROM Form_Dynamic_Submissions WHERE template_id = ?";
$stmtSubmissions = sqlsrv_query($conn, $sqlSubmissions, [$templateId]);
if ($stmtSubmissions) {
    while ($submission = sqlsrv_fetch_array($stmtSubmissions, SQLSRV_FETCH_ASSOC)) {
        $submissionData = json_decode($submission['submission_data'] ?? '', true);
        if (is_array($submissionData)) {
            foreach ($submissionData as $field => $fieldData) {
                if (is_array($fieldData) && ($fieldData['type'] ?? '') === 'file') {
                    $uploadedFile = $fieldData['value'] ?? '';
                    deleteUploadedFile($dynamicFormsDir, $uploadedFile);
                }
            }
        }
    }
    sqlsrv_free_stmt($stmtSubmissions);
}

if (!sqlsrv_begin_transaction($conn)) {
    $_SESSION['error'] = "Gagal memulai transaksi penghapusan.";
    header("Location: list_templates.php");
    exit();
}

$allGood = true;

$sqlDeleteSubmissions = "DELETE FROM Form_Dynamic_Submissions WHERE template_id = ?";
$stmtDeleteSubmissions = sqlsrv_query($conn, $sqlDeleteSubmissions, [$templateId]);
if ($stmtDeleteSubmissions === false) {
    $allGood = false;
}
if ($stmtDeleteSubmissions) {
    sqlsrv_free_stmt($stmtDeleteSubmissions);
}

if ($allGood) {
    $sqlDeleteTemplate = $templateNameParam
        ? "DELETE FROM Form_Dynamic_Templates WHERE template_name = ?"
        : "DELETE FROM Form_Dynamic_Templates WHERE id = ?";
    $deleteParam = $templateNameParam ? $templateNameParam : $templateIdParam;
    $stmtDeleteTemplate = sqlsrv_query($conn, $sqlDeleteTemplate, [$deleteParam]);
    if ($stmtDeleteTemplate === false) {
        $allGood = false;
    }
    if ($stmtDeleteTemplate) {
        sqlsrv_free_stmt($stmtDeleteTemplate);
    }
}

if ($allGood) {
    sqlsrv_commit($conn);
    $_SESSION['success'] = "Template dan seluruh data isiannya berhasil dihapus permanen.";
} else {
    sqlsrv_rollback($conn);
    $_SESSION['error'] = "Gagal menghapus template: " . print_r(sqlsrv_errors(), true);
}

header("Location: list_templates.php");
exit();
?>
