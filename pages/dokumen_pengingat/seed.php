<?php
require_once __DIR__ . '/../../koneksi.php';

echo "Menambahkan data seed...\n";

// Cek apakah sudah ada kategori
$check = sqlsrv_query($conn, "SELECT COUNT(*) as tot FROM dr_categories");
$row = sqlsrv_fetch_array($check, SQLSRV_FETCH_ASSOC);

if ($row['tot'] == 0) {
    // Kategori
    $sqlCat = "INSERT INTO dr_categories (category_name, icon, color, reminder_interval, created_by) 
               OUTPUT INSERTED.id
               VALUES ('Izin Lingkungan', 'fas fa-leaf', 'success', 30, 'admin')";
    $stmt = sqlsrv_query($conn, $sqlCat);
    $catRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $catId = $catRow['id'];

    // Fields
    $sqlFields = "INSERT INTO dr_fields (category_id, field_label, field_name, field_type, is_required, is_show_on_table, sort_order) VALUES 
                  (?, 'Nomor Dokumen', 'no_dokumen', 'text', 1, 1, 1),
                  (?, 'Instansi Penerbit', 'instansi', 'text', 0, 1, 2),
                  (?, 'Keterangan Tambahan', 'keterangan', 'textarea', 0, 0, 3)";
    
    sqlsrv_query($conn, $sqlFields, [$catId, $catId, $catId]);
    
    echo "Seed data kategori 'Izin Lingkungan' dan 3 kolomnya berhasil ditambahkan!\n";
} else {
    echo "Data kategori sudah ada, tidak perlu disemai lagi.\n";
}
?>
