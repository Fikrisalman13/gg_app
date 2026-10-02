<?php
/**
 * Migration Runner: Add Timestamp Columns
 * 
 * Menambahkan kolom untuk tracking timestamp dan user yang melakukan
 * aksi Unclosing dan Closed pada tabel Form_Umum_Buka_Tanggal_Closingan
 * 
 * Hanya dapat dijalankan oleh Administrator (GroupId = 1)
 */

session_start();
require_once '../../koneksi.php';

// Security: Hanya admin yang bisa menjalankan migration
if (!isset($_SESSION['GroupId']) || (int)$_SESSION['GroupId'] !== 1) {
    die('<h2 style="color:red;">❌ Unauthorized</h2><p>Only administrators can run migrations.</p>');
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration - Add Timestamp Columns</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .success {
            color: #28a745;
            padding: 10px;
            margin: 10px 0;
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .error {
            color: #dc3545;
            padding: 10px;
            margin: 10px 0;
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .info {
            color: #0c5460;
            padding: 10px;
            margin: 10px 0;
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin-top: 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔧 Database Migration: Add Timestamp Columns</h2>
        <p><strong>Migration Date:</strong> <?= date('Y-m-d H:i:s') ?></p>
        <p><strong>Executed By:</strong> <?= htmlspecialchars($_SESSION['NamaLengkap'] ?? $_SESSION['UserName'] ?? 'Unknown') ?></p>
        <hr>

        <?php
        $migrations = [
            [
                'name' => 'unclosing_at',
                'description' => 'Timestamp when status changed to Unclosing',
                'sql' => "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' AND COLUMN_NAME = 'unclosing_at')
                BEGIN
                    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan ADD unclosing_at DATETIME NULL;
                END"
            ],
            [
                'name' => 'closed_at',
                'description' => 'Timestamp when status changed to Closed',
                'sql' => "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' AND COLUMN_NAME = 'closed_at')
                BEGIN
                    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan ADD closed_at DATETIME NULL;
                END"
            ],
            [
                'name' => 'unclosing_by',
                'description' => 'User who performed Unclosing action',
                'sql' => "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' AND COLUMN_NAME = 'unclosing_by')
                BEGIN
                    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan ADD unclosing_by VARCHAR(100) NULL;
                END"
            ],
            [
                'name' => 'closed_by',
                'description' => 'User who performed Closed action',
                'sql' => "IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan' AND COLUMN_NAME = 'closed_by')
                BEGIN
                    ALTER TABLE dbo.Form_Umum_Buka_Tanggal_Closingan ADD closed_by VARCHAR(100) NULL;
                END"
            ]
        ];

        $successCount = 0;
        $errorCount = 0;

        foreach ($migrations as $migration) {
            echo "<h3>📋 Migration: {$migration['name']}</h3>";
            echo "<p><em>{$migration['description']}</em></p>";
            
            $stmt = sqlsrv_query($conn, $migration['sql']);
            
            if ($stmt === false) {
                $errorCount++;
                $errors = sqlsrv_errors();
                echo "<div class='error'>";
                echo "❌ <strong>Failed to add column '{$migration['name']}'</strong><br>";
                echo "<pre>" . print_r($errors, true) . "</pre>";
                echo "</div>";
            } else {
                $successCount++;
                echo "<div class='success'>";
                echo "✅ <strong>Column '{$migration['name']}' added successfully</strong> (or already exists)";
                echo "</div>";
            }
        }

        echo "<hr>";
        echo "<h3>📊 Migration Summary</h3>";
        echo "<div class='info'>";
        echo "<strong>Total Migrations:</strong> " . count($migrations) . "<br>";
        echo "<strong>Successful:</strong> {$successCount}<br>";
        echo "<strong>Failed:</strong> {$errorCount}<br>";
        echo "</div>";

        if ($errorCount === 0) {
            echo "<div class='success'>";
            echo "<h3>🎉 All migrations completed successfully!</h3>";
            echo "<p>The following columns have been added to the <code>Form_Umum_Buka_Tanggal_Closingan</code> table:</p>";
            echo "<ul>";
            echo "<li><code>unclosing_at</code> - DATETIME NULL</li>";
            echo "<li><code>closed_at</code> - DATETIME NULL</li>";
            echo "<li><code>unclosing_by</code> - VARCHAR(100) NULL</li>";
            echo "<li><code>closed_by</code> - VARCHAR(100) NULL</li>";
            echo "</ul>";
            echo "</div>";
        }
        ?>

        <hr>
        <a href="list_form.php" class="btn">← Back to Form List</a>
    </div>
</body>
</html>
