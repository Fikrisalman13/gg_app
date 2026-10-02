<?php
require_once __DIR__ . '/../../koneksi.php';

echo "Memulai migrasi database untuk V2...\n";

$sqls = [
    // 1. dr_categories
    "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='dr_categories' AND xtype='U')
    CREATE TABLE dr_categories (
        id INT IDENTITY(1,1) PRIMARY KEY,
        category_name VARCHAR(255) NOT NULL,
        icon VARCHAR(100) DEFAULT 'fas fa-file',
        color VARCHAR(50) DEFAULT 'primary',
        reminder_interval INT DEFAULT 30,
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT GETDATE(),
        updated_by VARCHAR(100),
        updated_at DATETIME
    )",

    // 2. dr_fields
    "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='dr_fields' AND xtype='U')
    CREATE TABLE dr_fields (
        id INT IDENTITY(1,1) PRIMARY KEY,
        category_id INT NOT NULL FOREIGN KEY REFERENCES dr_categories(id) ON DELETE CASCADE,
        field_label VARCHAR(255) NOT NULL,
        field_name VARCHAR(100) NOT NULL,
        field_type VARCHAR(50) NOT NULL,
        is_required BIT DEFAULT 0,
        is_show_on_table BIT DEFAULT 0,
        sort_order INT DEFAULT 1,
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT GETDATE()
    )",

    // 3. dr_documents
    "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='dr_documents' AND xtype='U')
    CREATE TABLE dr_documents (
        id INT IDENTITY(1,1) PRIMARY KEY,
        category_id INT NOT NULL FOREIGN KEY REFERENCES dr_categories(id) ON DELETE CASCADE,
        expire_date DATE NOT NULL,
        status VARCHAR(50),
        bagian_id INT,
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT GETDATE(),
        updated_by VARCHAR(100),
        updated_at DATETIME
    )",

    // 4. dr_doc_values (EAV structure)
    "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='dr_doc_values' AND xtype='U')
    CREATE TABLE dr_doc_values (
        id INT IDENTITY(1,1) PRIMARY KEY,
        document_id INT NOT NULL FOREIGN KEY REFERENCES dr_documents(id) ON DELETE CASCADE,
        field_id INT NOT NULL FOREIGN KEY REFERENCES dr_fields(id) ON DELETE NO ACTION,
        field_value NVARCHAR(MAX)
    )",

    // 5. dr_doc_files
    "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='dr_doc_files' AND xtype='U')
    CREATE TABLE dr_doc_files (
        id INT IDENTITY(1,1) PRIMARY KEY,
        document_id INT NOT NULL FOREIGN KEY REFERENCES dr_documents(id) ON DELETE CASCADE,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        uploaded_at DATETIME DEFAULT GETDATE()
    )"
];

foreach ($sqls as $index => $sql) {
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        echo "Error eksekusi query ke-" . ($index + 1) . ":\n";
        die(print_r(sqlsrv_errors(), true));
    }
    echo "Tabel ke-" . ($index + 1) . " berhasil dipastikan ada.\n";
}

echo "\nSemua tabel V2 berhasil dibuat!\n";
?>
