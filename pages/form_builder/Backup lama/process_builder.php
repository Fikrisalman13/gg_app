<?php
session_start();
require_once '../../koneksi.php';

function formBuilderSupportsPublicColumn($conn) {
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }

    $sqlCheck = "SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.Form_Dynamic_Templates') AND name = 'is_public'";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck);
    $hasColumn = ($stmtCheck && sqlsrv_fetch_array($stmtCheck)) ? true : false;
    if ($stmtCheck) {
        sqlsrv_free_stmt($stmtCheck);
    }

    return $hasColumn;
}

if (!isset($_SESSION['UserId'])) {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supportsPublicColumn = formBuilderSupportsPublicColumn($conn);
    $templateId = $_POST['template_id'] ?? null;
    $templateName = trim($_POST['template_name']);
    $originalTemplateName = trim($_POST['original_template_name'] ?? '');
    $templateDescription = $_POST['template_description'];
    $isPublic = ($supportsPublicColumn && isset($_POST['is_public'])) ? 1 : 0;
    $createdBy = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'];

    $itemOrders = $_POST['item_orders'] ?? [];
    $itemTypes = $_POST['item_types'] ?? [];
    
    $labels = $_POST['field_labels'] ?? [];
    $types = $_POST['field_types'] ?? [];
    $requireds = $_POST['field_required'] ?? [];
    $optionsArr = $_POST['field_options'] ?? [];
    
    // New validation fields
    $placeholders = $_POST['field_placeholders'] ?? [];
    $maxlengths = $_POST['field_maxlengths'] ?? [];
    
    $exampleDescriptions = $_POST['example_descriptions'] ?? [];
    $exampleExistingImages = $_POST['example_existing_images'] ?? [];

    $uploadDir = '../../uploads/form_examples/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Build items array maintaining order
    $fields = [];
    $fieldIndex = 0;
    $exampleIndex = 0;
    
    for ($i = 0; $i < count($itemTypes); $i++) {
        if ($itemTypes[$i] === 'field') {
            // Process field
            $fieldOptions = [];
            if ($types[$fieldIndex] === 'select' && !empty($optionsArr[$fieldIndex])) {
                $fieldOptions = array_map('trim', explode(',', $optionsArr[$fieldIndex]));
            }

            $fields[] = [
                'item_type' => 'field',
                'label' => $labels[$fieldIndex],
                'type' => $types[$fieldIndex],
                'required' => $requireds[$fieldIndex] == "1",
                'placeholder' => $placeholders[$fieldIndex] ?? '',
                'maxlength' => $maxlengths[$fieldIndex] ?? '',
                'options' => $fieldOptions
            ];
            $fieldIndex++;
            
        } else if ($itemTypes[$i] === 'example') {
            // Process example
            $imageName = $exampleExistingImages[$exampleIndex] ?? '';
            
            // Handle image upload
            if (isset($_FILES['example_images']['name'][$exampleIndex]) && !empty($_FILES['example_images']['name'][$exampleIndex])) {
                $file = [
                    'name' => $_FILES['example_images']['name'][$exampleIndex],
                    'type' => $_FILES['example_images']['type'][$exampleIndex],
                    'tmp_name' => $_FILES['example_images']['tmp_name'][$exampleIndex],
                    'error' => $_FILES['example_images']['error'][$exampleIndex],
                    'size' => $_FILES['example_images']['size'][$exampleIndex]
                ];

                if ($file['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $imageName = uniqid('example_') . '.' . $ext;
                    move_uploaded_file($file['tmp_name'], $uploadDir . $imageName);
                }
            }

            $fields[] = [
                'item_type' => 'example',
                'description' => $exampleDescriptions[$exampleIndex] ?? '',
                'example_image' => $imageName
            ];
            $exampleIndex++;
        }
    }

    $fieldsJson = json_encode($fields);

    // Ensure template_name is unique
    $sqlCheckName = "SELECT id FROM Form_Dynamic_Templates WHERE template_name = ? AND is_active = 1";
    $checkParams = [$templateName];
    if ($templateId) {
        $sqlCheckName .= " AND id <> ?";
        $checkParams[] = $templateId;
    }
    $stmtCheckName = sqlsrv_query($conn, $sqlCheckName, $checkParams);
    if ($stmtCheckName && sqlsrv_fetch_array($stmtCheckName, SQLSRV_FETCH_ASSOC)) {
        $_SESSION['error'] = "Nama template sudah digunakan. Gunakan nama lain.";
        $redirectUrl = 'builder.php';
        if ($templateId) {
            $targetName = $originalTemplateName ?: $templateName;
            $redirectUrl .= '?template_name=' . urlencode($targetName);
        }
        header("Location: $redirectUrl");
        exit();
    }

    if ($templateId) {
        // Update existing
        if ($supportsPublicColumn) {
            $sql = "UPDATE Form_Dynamic_Templates SET template_name = ?, template_description = ?, fields_json = ?, is_public = ? WHERE id = ?";
            $params = [$templateName, $templateDescription, $fieldsJson, $isPublic, $templateId];
        } else {
            $sql = "UPDATE Form_Dynamic_Templates SET template_name = ?, template_description = ?, fields_json = ? WHERE id = ?";
            $params = [$templateName, $templateDescription, $fieldsJson, $templateId];
        }
    } else {
        // Create new
        if ($supportsPublicColumn) {
            $sql = "INSERT INTO Form_Dynamic_Templates (template_name, template_description, fields_json, is_public, created_by) VALUES (?, ?, ?, ?, ?)";
            $params = [$templateName, $templateDescription, $fieldsJson, $isPublic, $createdBy];
        } else {
            $sql = "INSERT INTO Form_Dynamic_Templates (template_name, template_description, fields_json, created_by) VALUES (?, ?, ?, ?)";
            $params = [$templateName, $templateDescription, $fieldsJson, $createdBy];
        }
    }

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $_SESSION['success'] = "Template form berhasil disimpan.";
    header("Location: list_templates.php");
    exit();
}
?>
