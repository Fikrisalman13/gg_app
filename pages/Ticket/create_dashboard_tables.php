<?php
// ===================================================
// 1. KONEKSI DATABASE
// ===================================================
require_once __DIR__ . '/../../koneksi.php';

// ===================================================
// 2. HELPER PEMBUATAN TABEL
// ===================================================
/**
 * Menjalankan perintah CREATE TABLE jika belum tersedia.
 */
function createTable($conn, $sql, $tableName) {
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt) {
        echo "Table $tableName created or already exists.\n";
    } else {
        $errors = sqlsrv_errors();
        if ($errors && strpos($errors[0]['message'], 'There is already an object named') !== false) {
            echo "Table $tableName already exists.\n";
        } else {
            echo "Error creating $tableName: ";
            print_r($errors);
        }
    }
}

// ===================================================
// 3. PEMBUATAN TABEL TICKET_TECH_GROUPS
// ===================================================
$sql1 = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='ticket_tech_groups' AND xtype='U')
    CREATE TABLE dbo.ticket_tech_groups (
        id INT IDENTITY(1,1) PRIMARY KEY,
        group_name VARCHAR(100) NOT NULL,
        created_at DATETIME DEFAULT GETDATE()
    )";
createTable($conn, $sql1, 'ticket_tech_groups');

// ===================================================
// 4. PEMBUATAN TABEL TICKET_TECH_MEMBERS
// ===================================================
$sql2 = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='ticket_tech_members' AND xtype='U')
    CREATE TABLE dbo.ticket_tech_members (
        id INT IDENTITY(1,1) PRIMARY KEY,
        group_id INT NOT NULL,
        id_emp INT NOT NULL,
        FOREIGN KEY (group_id) REFERENCES dbo.ticket_tech_groups(id) ON DELETE CASCADE
    )";
createTable($conn, $sql2, 'ticket_tech_members');

echo "\nDatabase setup completed.\n";
?>
