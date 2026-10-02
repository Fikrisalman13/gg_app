<?php
session_start();
include '../../koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $group_name = strtoupper(trim($_POST['group_name']));
    $rtg_data = $_POST['rtg_data'] ?? []; // Array of "rtgmsid|rtgcode|rtgname"
    $user = $_SESSION['UserName'] ?? 'System';

    if (empty($group_name) || empty($rtg_data)) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap.']);
        exit;
    }

    $successCount = 0;
    foreach ($rtg_data as $rd) {
        $parts = explode('|', $rd);
        if (count($parts) == 3) {
            $msid = $parts[0];
            $code = $parts[1];
            $name = $parts[2];

            // Check if already exists in this group
            $sqlCheck = "SELECT id FROM planning_trustee_group WHERE group_name = ? AND rtgmsid = ?";
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, [$group_name, $msid]);
            if (sqlsrv_has_rows($stmtCheck)) continue;

            $sqlInsert = "INSERT INTO planning_trustee_group (group_name, rtgmsid, rtgcode, rtgname, created_by, created_at) VALUES (?, ?, ?, ?, ?, GETDATE())";
            if (sqlsrv_query($conn, $sqlInsert, [$group_name, $msid, $code, $name, $user])) {
                $successCount++;
            }
        }
    }

    echo json_encode(['status' => 'success', 'message' => "$successCount komponen routing berhasil ditambahkan ke grup $group_name."]);
}
