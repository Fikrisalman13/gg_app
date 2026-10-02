<?php
// simpan_cod_air.php
header('Content-Type: application/json');
include($_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php');

$response = ["success" => false];

try {
    // Ambil data POST
    $tanggal = $_POST['tanggal'] ?? null;
    $pengecek = $_POST['pengecek'] ?? null;
    $shift = $_POST['shift'] ?? null;
    $nilai_kalibrasi = (isset($_POST['nilai_kalibrasi']) && $_POST['nilai_kalibrasi'] !== '') ? floatval($_POST['nilai_kalibrasi']) : null;
    // CreatedBy: ambil dari POST, lalu fallback ke session
    $created_by = $_POST['created_by'] ?? $_SESSION['UserName'] ?? $_SESSION['NamaLengkap'] ?? null;

    // Daftar bak
    $bakList = [
        'PekatBesar', 'PekatKecil', 'Anoxit', 'Daff1', 'Daff2', 'Daff3',
        'Aerasi1', 'Aerasi2', 'Aerasi3', 'Aerasi4', 'SelokanPekat', 'SelokanReaktif', 'Dwatring', 'Sedimen', 'Outlet', 'EqualSum'
    ];

    // Siapkan nilai bak (nilai asli tanpa dikali kalibrasi)
    $bakValues = [];
    foreach ($bakList as $bak) {
        $val = isset($_POST[$bak]) ? $_POST[$bak] : null;
        if ($val === '' || $val === null) {
            $bakValues[$bak] = null;
        } else {
            $valFloat = floatval($val);
            $bakValues[$bak] = round($valFloat, 2);
        }
    }

    // Koneksi SQL Server
    // Pastikan $conn adalah koneksi SQL Server
    // Nama tabel: COD_air
    $sql = "INSERT INTO COD_air (
        Tanggal, Pengecek, Shift, NilaiKalibrasi,
        PekatBesar, PekatKecil, Anoxit, Daff1, Daff2, Daff3,
        Aerasi1, Aerasi2, Aerasi3, Aerasi4, SelokanPekat, SelokanReaktif, Dwatring,
        Sedimen, Outlet, EqualSum,
        CreatedBy, CreatedAt, UpdateAt
    ) VALUES (
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        GETDATE(), GETDATE()
    )";

    $params = [
        $tanggal,
        $pengecek,
        $shift,
        $nilai_kalibrasi,
        $bakValues['PekatBesar'],
        $bakValues['PekatKecil'],
        $bakValues['Anoxit'],
        $bakValues['Daff1'],
        $bakValues['Daff2'],
        $bakValues['Daff3'],
        $bakValues['Aerasi1'],
        $bakValues['Aerasi2'],
        $bakValues['Aerasi3'],
        $bakValues['Aerasi4'],
        $bakValues['SelokanPekat'],
        $bakValues['SelokanReaktif'],
        $bakValues['Dwatring'],
        $bakValues['Sedimen'],
        $bakValues['Outlet'],
        $bakValues['EqualSum'],
        $created_by // pastikan ini username akun yang login
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
