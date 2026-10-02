<?php
// simpan_ptco_air.php
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$response = ["success" => false];

try {
    // Ambil data POST
    $tanggal = $_POST['tanggal'] ?? null;
    $pengecek = $_POST['pengecek'] ?? null;
    $shift = $_POST['shift'] ?? null;
    $created_by = $_POST['created_by'] ?? $_SESSION['UserName'] ?? $_SESSION['NamaLengkap'] ?? null;

    // Daftar bak/kolom yang akan disimpan
    $bakList = [
        'EqualSum', 'Daff1', 'Daff2', 'Daff3', 'Aerasi1', 'SedimenBiologi', 'Flogulan', 'PostSedimen', 'Dwatring', 'Outlet'
    ];

    $bakValues = [];
    foreach ($bakList as $bak) {
        $val = isset($_POST[$bak]) ? $_POST[$bak] : null;
        if ($val === '' || $val === null) {
            $bakValues[$bak] = null;
        } else {
            $bakValues[$bak] = round(floatval($val), 2);
        }
    }

    $sql = "INSERT INTO PTCO_Air (
        Tanggal, Pengecek, Shift,
        EqualSum, Daff1, Daff2, Daff3, Aerasi1, SedimenBiologi, Flogulan, PostSedimen, Dwatring, Outlet,
        CreatedBy, CreatedAt, UpdateAt
    ) VALUES (
        ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, GETDATE(), GETDATE()
    )";

    $params = [
        $tanggal,
        $pengecek,
        $shift,
        $bakValues['EqualSum'],
        $bakValues['Daff1'],
        $bakValues['Daff2'],
        $bakValues['Daff3'],
        $bakValues['Aerasi1'],
        $bakValues['SedimenBiologi'],
        $bakValues['Flogulan'],
        $bakValues['PostSedimen'],
        $bakValues['Dwatring'],
        $bakValues['Outlet'],
        $created_by
    ];

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt) {
        $response["success"] = true;
    } else {
        $err = sqlsrv_errors();
        $response["error"] = $err ? $err[0]["message"] : "Gagal menyimpan data ke database.";
    }
} catch (Exception $e) {
    $response["error"] = $e->getMessage();
}

echo json_encode($response);
