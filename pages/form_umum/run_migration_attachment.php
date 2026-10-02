<?php
session_start();
require_once '../../koneksi.php';

// Hanya admin yang bisa menjalankan migration
if (!isset($_SESSION['GroupId']) || (int)$_SESSION['GroupId'] !== 1) {
    die('Unauthorized: Only administrators can run migrations.');
}

echo "<!DOCTYPE html>";
echo "<html><head><title>Migration - Add Attachment Columns</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;}";
echo ".success{color:green;padding:10px;background:#d4edda;border:1px solid #c3e6cb;border-radius:4px;margin:10px 0;}";
echo ".error{color:red;padding:10px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:4px;margin:10px 0;}";
echo "h2{color:#333;}</style></head><body>";

echo "<h2>Running Database Migration: Add Attachment Columns</h2>";

// Migration 1: attachment_filename
$sql1 = "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' AND COLUMN_NAME = 'attachment_filename')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan ADD attachment_filename VARCHAR(255) NULL;
END";

$stmt1 = sqlsrv_query($conn, $sql1);
if ($stmt1 === false) {
    echo "<p class='error'>❌ Failed to add attachment_filename column: " . print_r(sqlsrv_errors(), true) . "</p>";
} else {
    echo "<p class='success'>✅ Column attachment_filename added successfully (or already exists)</p>";
}

// Migration 2: attachment_path
$sql2 = "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' AND COLUMN_NAME = 'attachment_path')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan ADD attachment_path VARCHAR(500) NULL;
END";

$stmt2 = sqlsrv_query($conn, $sql2);
if ($stmt2 === false) {
    echo "<p class='error'>❌ Failed to add attachment_path column: " . print_r(sqlsrv_errors(), true) . "</p>";
} else {
    echo "<p class='success'>✅ Column attachment_path added successfully (or already exists)</p>";
}

// Migration 3: attachment_size
$sql3 = "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' AND COLUMN_NAME = 'attachment_size')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan ADD attachment_size INT NULL;
END";

$stmt3 = sqlsrv_query($conn, $sql3);
if ($stmt3 === false) {
    echo "<p class='error'>❌ Failed to add attachment_size column: " . print_r(sqlsrv_errors(), true) . "</p>";
} else {
    echo "<p class='success'>✅ Column attachment_size added successfully (or already exists)</p>";
}

// Migration 4: attachment_uploaded_at
$sql4 = "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' AND COLUMN_NAME = 'attachment_uploaded_at')
BEGIN
    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan ADD attachment_uploaded_at DATETIME NULL;
END";

$stmt4 = sqlsrv_query($conn, $sql4);
if ($stmt4 === false) {
    echo "<p class='error'>❌ Failed to add attachment_uploaded_at column: " . print_r(sqlsrv_errors(), true) . "</p>";
} else {
    echo "<p class='success'>✅ Column attachment_uploaded_at added successfully (or already exists)</p>";
}

echo "<hr>";
echo "<h3 style='color:#28a745;'>Migration completed!</h3>";
echo "<p><a href='list_form.php' style='color:#007bff;text-decoration:none;'>← Back to Form List</a></p>";
echo "</body></html>";
?>
