<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../koneksi.php';
session_start();

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

$permissions = checkPermissions($conn, $_SESSION['GroupId'], 56);
if ($permissions['CanView'] != 1) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk melihat halaman ini.";
    header('Location: ../dashboard.php');
    exit;
}

$filters = [
    'kategori' => $_GET['kategori'] ?? '',
    'lokasi'   => $_GET['lokasi'] ?? '',
    'status'   => $_GET['status'] ?? '',
    'search_value' => trim($_GET['search_value'] ?? '')
];

$assets = getFilteredAssets($conn, $filters);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Print QR Code Aset</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .qr-container {
            width: 200px;
            border: 1px solid #ccc;
            padding: 8px;
            margin: 10px;
            float: left;
            text-align: center;
        }
        .label { font-size: 10px; margin-top: 5px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>


<button class="no-print" onclick="window.print()">Print</button>
<div style="display: flex; flex-wrap: wrap;">
<?php
foreach ($assets as $asset) {
    $assetCode = !empty($asset['kode_asset_seq']) ? $asset['kode_asset_seq'] : 'ASSET-' . $asset['id_asset'];
    $qrCode = new TCPDF2DBarcode($assetCode, 'QRCODE,M');
    $qrPng = $qrCode->getBarcodePngData(4, 4, [0,0,0]);
    $base64 = base64_encode($qrPng);
    ?>
    <div class="qr-container">
        <img src="data:image/png;base64,<?= $base64 ?>" alt="QR Code">
        <div class="label"><strong><?= htmlspecialchars($assetCode) ?></strong></div>
        <div class="label">Kategori: <?= htmlspecialchars($asset['nama_kategori']) ?></div>
        <div class="label">Merk: <?= htmlspecialchars($asset['nama_merk']) ?></div>
        <div class="label">Tipe: <?= htmlspecialchars($asset['nama_tipe']) ?></div>
        <div class="label">Lokasi: <?= htmlspecialchars($asset['nama_lokasi']) ?></div>
        <div class="label">SN: <?= htmlspecialchars($asset['serial_number']) ?></div>
    </div>
    <?php
}
?>
</div>
</body>
</html>

<?php
// === FUNGSI ===

function checkPermissions($conn, $groupId, $menuId) {
    $sql = "SELECT CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$groupId, $menuId]);
    $result = ['CanView' => 0];
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $result = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $result;
}

function getFilteredAssets($conn, $filters) {
    $sql = "SELECT
                a.id_asset,
                a.kode_asset_seq,
                ka.kode_asset,
                kat.nama_kategori,
                m.nama_merk,
                t.nama_tipe,
                l.nama_lokasi,
                a.serial_number 
            FROM
                dbo.m_asset AS a
                LEFT JOIN dbo.m_kode_asset AS ka ON a.id_kode = ka.id_kode
                LEFT JOIN dbo.m_kategori AS kat ON a.id_kategori = kat.id_kategori
                LEFT JOIN dbo.m_merk AS m ON a.id_merk = m.id_merk
                LEFT JOIN dbo.m_tipe AS t ON a.id_tipe = t.id_tipe
                LEFT JOIN dbo.m_lokasi AS l ON a.id_lokasi = l.id_lokasi
                LEFT JOIN dbo.m_status AS s ON a.id_status = s.id_status
                LEFT JOIN dbo.m_emp AS e ON a.id_emp = e.id_emp
            WHERE 1=1";

    $params = [];
    if (!empty($filters['kategori'])) {
        $sql .= " AND a.id_kategori = ?";
        $params[] = $filters['kategori'];
    }
    if (!empty($filters['lokasi'])) {
        $sql .= " AND a.id_lokasi = ?";
        $params[] = $filters['lokasi'];
    }
    if (!empty($filters['status'])) {
        $sql .= " AND a.id_status = ?";
        $params[] = $filters['status'];
    }
    if (!empty($filters['search_value'])) {
        $searchCols = [
            "ka.kode_asset",
            "a.kode_asset_seq",
            "a.serial_number",
            "kat.nama_kategori",
            "m.nama_merk",
            "t.nama_tipe",
            "l.nama_lokasi",
            "s.nama_status",
            "e.nama_lengkap",
            "a.keterangan"
        ];
        $searchClauses = [];
        foreach ($searchCols as $col) {
            $searchClauses[] = "$col LIKE ?";
            $params[] = '%' . $filters['search_value'] . '%';
        }
        $sql .= " AND (" . implode(" OR ", $searchClauses) . ")";
    }

    $sql .= " ORDER BY a.kode_asset_seq";
    $stmt = sqlsrv_query($conn, $sql, $params);

    $assets = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $assets[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $assets;
}
?>
