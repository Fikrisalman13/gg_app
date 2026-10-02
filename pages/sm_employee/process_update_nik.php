<?php
session_start();
include '../../koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['status' => 'error', 'message' => 'Silakan login terlebih dahulu!']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$data_update = json_decode($_POST['data_update'] ?? '[]', true);

if (empty($data_update)) {
    echo json_encode(['status' => 'error', 'message' => 'Tidak ada data yang akan diupdate.']);
    exit;
}

$user = $_SESSION['UserName'];
$total_success = 0;
$total_failed = 0;
$errors = [];

// Mulai transaksi
sqlsrv_begin_transaction($conn);

try {
    // Siapkan statement untuk update m_emp
    $sqlUpdate = "UPDATE dbo.m_emp SET nik = ?, upddate = GETDATE(), upduser = ? WHERE id_emp = ?";
    $stmtUpdate = sqlsrv_prepare($conn, $sqlUpdate, [&$new_nik, &$user, &$id_emp]);

    if (!$stmtUpdate) {
        throw new Exception("Gagal menyiapkan statement UPDATE m_emp.");
    }

    // Siapkan statement untuk insert history
    $sqlHistory = "INSERT INTO dbo.m_emp_history (id_emp, field_changed, old_value, new_value, diubah_oleh) VALUES (?, ?, ?, ?, ?)";
    $stmtHistory = sqlsrv_prepare($conn, $sqlHistory, [&$id_emp, &$field_changed, &$old_nik, &$new_nik, &$user]);

    if (!$stmtHistory) {
        throw new Exception("Gagal menyiapkan statement INSERT m_emp_history.");
    }

    foreach ($data_update as $row) {
        $id_emp = $row['id_emp'];
        $old_nik = $row['nik_lama'];
        $new_nik = $row['nik_baru'];
        $field_changed = 'NIK';

        // Eksekusi UPDATE
        if (!sqlsrv_execute($stmtUpdate)) {
            $total_failed++;
            $errors[] = "Gagal update ID $id_emp: " . print_r(sqlsrv_errors(), true);
            continue; // Lanjut ke karyawan berikutnya jika gagal
        }

        // Eksekusi INSERT history
        if (!sqlsrv_execute($stmtHistory)) {
            $total_failed++;
            $errors[] = "Gagal insert history ID $id_emp: " . print_r(sqlsrv_errors(), true);
            continue;
        }

        $total_success++;
    }

    // Jika ada yang berhasil, kita commit. Jika semua gagal, rollback.
    // Atau jika ingin strict (satu gagal, semua gagal), bisa disesuaikan. 
    // Di sini kita commit yang berhasil saja.
    if ($total_success > 0) {
        sqlsrv_commit($conn);
        $message = "Berhasil mengupdate $total_success data NIK.";
        if ($total_failed > 0) {
            $message .= " Gagal mengupdate $total_failed data.";
        }
        echo json_encode(['status' => 'success', 'message' => $message, 'errors' => $errors]);
    } else {
        sqlsrv_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => 'Semua proses update gagal.', 'errors' => $errors]);
    }

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

if (isset($stmtUpdate)) sqlsrv_free_stmt($stmtUpdate);
if (isset($stmtHistory)) sqlsrv_free_stmt($stmtHistory);
?>
