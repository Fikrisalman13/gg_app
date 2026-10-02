<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include '../../koneksi.php';
include '../../koneksi3.php';
require_once __DIR__ . '/rekapkartustock_query.php';

function rekapKartuStockDataResponse(int $draw, array $data = [], int $total = 0, ?string $error = null): void
{
    $response = ['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $data];
    if ($error !== null) {
        $response['error'] = $error;
    }
    echo json_encode($response);
    exit;
}

$draw = max(0, (int) ($_GET['draw'] ?? 0));
if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    rekapKartuStockDataResponse($draw, [], 0, 'Sesi login telah berakhir.');
}

$groupId = $_SESSION['GroupId'];
$permissionStmt = sqlsrv_query($conn, 'SELECT TOP 1 CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?', [$groupId, 78]);
$permission = ($permissionStmt && ($permissionRow = sqlsrv_fetch_array($permissionStmt, SQLSRV_FETCH_ASSOC))) ? $permissionRow : [];
if ($permissionStmt) {
    sqlsrv_free_stmt($permissionStmt);
}
if (isset($permission['CanView']) && (int) $permission['CanView'] === 0) {
    http_response_code(403);
    rekapKartuStockDataResponse($draw, [], 0, 'Anda tidak memiliki hak untuk melihat laporan ini.');
}

try {
    $warehouses = rekapKartuStockGetWarehouses($conn3);
    [$filters, $error] = rekapKartuStockValidateFilters($_GET, $warehouses);
    if ($filters === null) {
        rekapKartuStockDataResponse($draw, [], 0, $error);
    }

    $rows = rekapKartuStockFetch(
        $conn3,
        $filters,
        (int) ($_GET['length'] ?? 20),
        (int) ($_GET['start'] ?? 0),
        (string) ($_GET['search']['value'] ?? '')
    );
    $total = $rows ? (int) $rows[0]['total_rows'] : 0;
    rekapKartuStockDataResponse($draw, $rows, $total);
} catch (Throwable $e) {
    http_response_code(500);
    rekapKartuStockDataResponse($draw, [], 0, 'Data rekap tidak dapat dimuat. Silakan coba kembali.');
}
