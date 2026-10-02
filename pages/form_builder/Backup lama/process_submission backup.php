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

function submissionsHasCreatedAt($conn) {
    return tableHasColumn($conn, 'Form_Dynamic_Submissions', 'created_at');
}

$supportsPublicColumn = formBuilderSupportsPublicColumn($conn);
$submissionsHasCreatedAt = submissionsHasCreatedAt($conn);
$isLoggedIn = isset($_SESSION['UserId']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $templateIdParam = $_POST['template_id'] ?? null;
    $templateNameParam = isset($_POST['template_name']) ? trim($_POST['template_name']) : null;
    $editSubmissionId = $_POST['edit_submission_id'] ?? null;
    if (!$templateIdParam && !$templateNameParam) {
        $_SESSION['error'] = "Template tidak valid.";
        header("Location: list_templates.php");
        exit();
    }

    $columnList = $supportsPublicColumn ? "id, template_name, fields_json, is_public" : "id, template_name, fields_json";
    if ($templateNameParam) {
        $sql = "SELECT $columnList FROM Form_Dynamic_Templates WHERE template_name = ?";
        $stmt = sqlsrv_query($conn, $sql, [$templateNameParam]);
    } else {
        $sql = "SELECT $columnList FROM Form_Dynamic_Templates WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, [$templateIdParam]);
    }
    $template = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$template) {
        $_SESSION['error'] = "Template tidak ditemukan.";
        header("Location: list_templates.php");
        exit();
    }

    $templateId = $template['id'];
    $templateName = $template['template_name'];
    $fields = json_decode($template['fields_json'], true) ?? [];
    // Simplify: just check the value if it exists, SELECT * already handled the column presence
    $isPublic = !empty($template['is_public']);

    if (!$isLoggedIn && !$isPublic) {
        header("Location: ../../login.php");
        exit();
    }

    $publicSubmitterName = trim($_POST['public_submitter_name'] ?? '');
    $createdBy = $isLoggedIn
        ? ($_SESSION['NamaLengkap'] ?? $_SESSION['UserName'])
        : ($publicSubmitterName !== '' ? $publicSubmitterName : 'Public User');

    $submissionData = [];
    $uploadDir = "../../uploads/dynamic_forms/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fieldIndex = 0; // Counter for field inputs
    foreach ($fields as $item) {
        // Skip example sections
        if (isset($item['item_type']) && $item['item_type'] === 'example') {
            continue;
        }
        
        // Process field
        $field = $item;
        $key = "field_" . $fieldIndex;
        $label = $field['label'];
        
        if ($field['type'] === 'file') {
            $existingFileJson = $_POST["existing_file_" . $fieldIndex] ?? null;
            $existingFileData = $existingFileJson ? json_decode($existingFileJson, true) : null;

            if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                $fileName = basename($_FILES[$key]["name"]);
                $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $newFileName = "file_" . time() . "_" . $fieldIndex . "." . $fileType;
                $targetFilePath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($_FILES[$key]["tmp_name"], $targetFilePath)) {
                    $submissionData[$label] = [
                        'type' => 'file',
                        'value' => $newFileName,
                        'original_name' => $fileName
                    ];

                    // If we have an old file and we are editing, we should probably delete the old one
                    // But for now let's just keep the reference updated. 
                    // To be safe, let's only delete if it's a different file.
                    if ($existingFileData && !empty($existingFileData['value'])) {
                        $oldFilePath = $uploadDir . $existingFileData['value'];
                        if (file_exists($oldFilePath)) {
                            @unlink($oldFilePath);
                        }
                    }
                }
            } elseif ($existingFileData) {
                // Keep the old file if no new file is uploaded
                $submissionData[$label] = $existingFileData;
            } else {
                $submissionData[$label] = ['type' => 'file', 'value' => null];
            }
        } else {
            $submissionData[$label] = [
                'type' => $field['type'],
                'value' => $_POST[$key] ?? ''
            ];
        }
        
        $fieldIndex++; // Increment for next field
    }

    $submissionJson = json_encode($submissionData);

    if ($editSubmissionId) {
        // UPDATE Logic
        $hasSubmissionId = tableHasColumn($conn, 'Form_Dynamic_Submissions', 'submission_id');
        $hasId = tableHasColumn($conn, 'Form_Dynamic_Submissions', 'id');
        $pkCol = $hasSubmissionId ? 'submission_id' : ($hasId ? 'id' : null);

        if ($pkCol) {
            $hasUpdatedBy = tableHasColumn($conn, 'Form_Dynamic_Submissions', 'updated_by');
            $hasUpdatedAt = tableHasColumn($conn, 'Form_Dynamic_Submissions', 'updated_at');

            $sqlAction = "UPDATE Form_Dynamic_Submissions SET submission_data = ?";
            $params = [$submissionJson];

            if ($hasUpdatedBy) {
                $sqlAction .= ", updated_by = ?";
                $params[] = $createdBy;
            }
            if ($hasUpdatedAt) {
                $sqlAction .= ", updated_at = SYSDATETIME()";
            }

            $sqlAction .= " WHERE {$pkCol} = ? AND template_id = ?";
            $params[] = $editSubmissionId;
            $params[] = $templateId;
            
            $isUpdate = true;
        } else {
            $_SESSION['error'] = "Gagal memperbarui: Kolom kunci tidak ditemukan.";
            header("Location: submissions.php?template_name=" . urlencode($templateName));
            exit();
        }
    } else {
        // INSERT Logic
        $isUpdate = false;
        if ($submissionsHasCreatedAt) {
            $sqlAction = "INSERT INTO Form_Dynamic_Submissions (template_id, submission_data, created_by, created_at) VALUES (?, ?, ?, SYSDATETIME())";
        } else {
            $sqlAction = "INSERT INTO Form_Dynamic_Submissions (template_id, submission_data, created_by) VALUES (?, ?, ?)";
        }
        $params = [$templateId, $submissionJson, $createdBy];
    }
    
    $stmtAction = sqlsrv_query($conn, $sqlAction, $params);

    if ($stmtAction === false) {
        $_SESSION['error'] = "Gagal menyimpan data: " . print_r(sqlsrv_errors(), true);
        $formUrl = "form.php?template_name=" . urlencode($templateName);
        if ($editSubmissionId) $formUrl .= "&edit_submission_id=" . urlencode($editSubmissionId);
        header("Location: " . $formUrl);
        exit();
    }

    $_SESSION['success'] = $isUpdate ? "Formulir berhasil diperbarui." : "Formulir berhasil dikirim.";
    $formUrl = "form.php?template_name=" . urlencode($templateName);

    // Always redirect back to form.php as requested
    if (!$isLoggedIn) {
        $_SESSION['success_view_link'] = "submissions.php?template_name=" . urlencode($templateName);
    }
    header("Location: " . $formUrl);
    exit();
}
?>
