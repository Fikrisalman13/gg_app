<?php
/**
 * @param resource|null $conn Koneksi SQL Server aktif dari bootstrap aplikasi.
 * @return void Mengirim JSON berisi detail atau pesan kegagalan yang aman.
 */
function sendPurchasingIssueDetail($conn): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        echo json_encode(['success' => false, 'message' => 'Metode permintaan tidak didukung.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    require_once __DIR__ . '/issue_access.php';
    $access = purchasingIssueAccess($conn);
    if (empty($access['allowed'])) {
        http_response_code((int) $access['status']);
        echo json_encode(['success' => false, 'message' => (string) $access['message']], JSON_UNESCAPED_UNICODE);
        return;
    }

    $issueId = filter_var($_GET['issue_id'] ?? null, FILTER_VALIDATE_INT);
    if ($issueId === false || $issueId === null || $issueId < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID issue tidak valid.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $sql = 'SELECT i.issue_id, i.issue_name, i.issue_type, i.category_id, i.sub_id,
                   i.status, i.priority, i.due_date, i.description, i.departemen,
                   i.bagian, i.jabatan, i.created_by, i.created_at, i.update_by,
                   i.update_at, i.tanggal_selesai, i.row_version,
                   c.category_name, s.sub_name
            FROM dbo.purchasing_issues i
            LEFT JOIN dbo.purchasing_issue_categories c ON c.category_id = i.category_id
            LEFT JOIN dbo.purchasing_issue_sub_categories s
                ON s.sub_id = i.sub_id AND s.category_id = i.category_id
            WHERE i.issue_id = ?';
    $statement = sqlsrv_query($conn, $sql, [$issueId]);
    if ($statement === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Detail issue tidak dapat dimuat.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($statement);
    if ($row === null || $row === false) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data issue tidak ditemukan.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $rowVersionValue = $row['row_version'] ?? null;
    if (!is_string($rowVersionValue) || strlen($rowVersionValue) !== 8) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Versi issue tidak dapat dimuat.'], JSON_UNESCAPED_UNICODE);
        return;
    }
    $rowVersion = '0x' . strtoupper(bin2hex($rowVersionValue));

    foreach (['due_date' => 'Y-m-d', 'created_at' => 'Y-m-d H:i:s', 'update_at' => 'Y-m-d H:i:s', 'tanggal_selesai' => 'Y-m-d H:i:s'] as $field => $format) {
        $value = $row[$field] ?? null;
        if ($value instanceof DateTimeInterface) {
            $row[$field] = $value->format($format);
        } elseif ($value === null || $value === '') {
            $row[$field] = null;
        } else {
            $timestamp = strtotime((string) $value);
            $row[$field] = $timestamp === false ? null : date($format, $timestamp);
        }
    }

    $data = [
        'issue_id' => (int) $row['issue_id'],
        'issue_name' => (string) ($row['issue_name'] ?? ''),
        'issue_type' => (string) ($row['issue_type'] ?? ''),
        'category_id' => (int) ($row['category_id'] ?? 0),
        'sub_id' => (int) ($row['sub_id'] ?? 0),
        'kategori' => (string) ($row['category_name'] ?? ''),
        'sub_kategori' => (string) ($row['sub_name'] ?? ''),
        'status' => (string) ($row['status'] ?? ''),
        'priority' => (string) ($row['priority'] ?? ''),
        'due_date' => $row['due_date'],
        'description' => (string) ($row['description'] ?? ''),
        'departemen' => (string) ($row['departemen'] ?? ''),
        'bagian' => (string) ($row['bagian'] ?? ''),
        'jabatan' => (string) ($row['jabatan'] ?? ''),
        'created_by' => (string) ($row['created_by'] ?? ''),
        'created_at' => $row['created_at'],
        'updated_by' => (string) ($row['update_by'] ?? ''),
        'updated_at' => $row['update_at'],
        'tanggal_selesai' => $row['tanggal_selesai'],
        'row_version' => $rowVersion
    ];
    $json = json_encode(['success' => true, 'message' => 'OK', 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Detail issue tidak dapat dimuat.'], JSON_UNESCAPED_UNICODE);
        return;
    }
    echo $json;
}

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
sendPurchasingIssueDetail($conn);
