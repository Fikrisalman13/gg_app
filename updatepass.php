<?php
include 'koneksi.php'; // Pastikan koneksi benar

// Ambil semua user
$query = "SELECT UserName, UserPassword FROM SMUserMs";
$stmt = sqlsrv_query($conn, $query);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$update_query = "UPDATE SMUserMs SET UserPassword = ? WHERE UserName = ?";
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $new_hashed_password = password_hash('admin123', PASSWORD_DEFAULT); // Gunakan password baru atau minta user reset

    // Update password dengan format password_hash()
    $params = array($new_hashed_password, $row['UserName']);
    $update_stmt = sqlsrv_query($conn, $update_query, $params);

    if ($update_stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }
}

echo "Password berhasil diperbarui!";
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
