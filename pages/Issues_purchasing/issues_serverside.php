<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';

/**
 * Menormalisasi filter tanggal-waktu HTML menjadi format ISO menit yang ketat.
 *
 * @param mixed $value Nilai mentah dari permintaan DataTables.
 * @return string|null Nilai SQL Server `Y-m-d H:i:s`, string kosong, atau null bila tidak valid.
 */
function purchasingIssueDateTimeFilter($value): ?string
{
    if ($value === '') {
        return '';
    }

    if (!is_string($value)) {
        return null;
    }

    $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value);
    if ($dateTime === false || $dateTime->format('Y-m-d\\TH:i') !== $value) {
        return null;
    }

    return $dateTime->format('Y-m-d H:i:s');
}

/**
 * @param resource|null $conn Koneksi SQL Server aktif dari bootstrap aplikasi.
 * @return void Mengirim respons JSON sesuai kontrak DataTables.
 */
function sendPurchasingIssueListResponse($conn): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $drawValue = $_POST['draw'] ?? $_GET['draw'] ?? 0;
    $draw = filter_var($drawValue, FILTER_VALIDATE_INT);
    $draw = $draw === false || $draw < 0 ? 0 : $draw;
    $response = ['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []];

    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
        http_response_code(405);
        header('Allow: GET, POST');
        $response['error'] = 'Metode permintaan tidak didukung.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return;
    }

    require_once __DIR__ . '/issue_access.php';
    $access = purchasingIssueAccess($conn);
    if (empty($access['allowed'])) {
        http_response_code((int) $access['status']);
        $response['error'] = (string) $access['message'];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return;
    }

    $username = trim((string) ($_SESSION['UserName'] ?? ''));
    $currentProfile = purchasingIssueCurrentProfile($conn, $username);
    $currentCreatorName = trim((string) ($currentProfile['name'] ?? ''));

    $start = filter_var($_REQUEST['start'] ?? 0, FILTER_VALIDATE_INT);
    $start = $start === false || $start < 0 ? 0 : min($start, 1000000);
    $length = filter_var($_REQUEST['length'] ?? 10, FILTER_VALIDATE_INT);
    $length = $length === false || $length < 1 ? 10 : min($length, 100);
    $filters = [];
    $parameters = [];
    $scope = trim((string) ($_REQUEST['scope'] ?? 'active'));
    if (!in_array($scope, ['active', 'history'], true)) {
        http_response_code(400);
        $response['error'] = 'Filter tampilan tidak valid.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return;
    }
    $filters[] = $scope === 'history'
        ? 'i.status = ?'
        : '(i.status <> ? OR i.status IS NULL)';
    $parameters[] = 'Done';

    $type = trim((string) ($_REQUEST['filterType'] ?? ''));
    if ($type !== '') {
        if (!in_array($type, ['Task', 'Support Activities'], true)) {
            http_response_code(400);
            $response['error'] = 'Filter jenis tidak valid.';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return;
        }
        $filters[] = 'i.issue_type = ?';
        $parameters[] = $type;
    }

    $status = trim((string) ($_REQUEST['filterStatus'] ?? ''));
    if ($status !== '') {
        $scopeStatus = $scope === 'history' ? 'Done' : ['To Do', 'In Progress'];
        $validForScope = is_array($scopeStatus)
            ? in_array($status, $scopeStatus, true)
            : $status === $scopeStatus;
        if (!in_array($status, ['To Do', 'In Progress', 'Done'], true) || !$validForScope) {
            http_response_code(400);
            $response['error'] = 'Filter status tidak sesuai dengan tampilan.';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return;
        }
        $filters[] = 'i.status = ?';
        $parameters[] = $status;
    }

    $categoryValue = $_REQUEST['filterKategori'] ?? '';
    if ($categoryValue !== '') {
        $categoryId = filter_var($categoryValue, FILTER_VALIDATE_INT);
        if ($categoryId === false || $categoryId < 1) {
            http_response_code(400);
            $response['error'] = 'Filter kategori tidak valid.';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return;
        }
        $filters[] = 'i.category_id = ?';
        $parameters[] = $categoryId;
    }

    $dateStart = purchasingIssueDateTimeFilter($_REQUEST['filterTanggalStart'] ?? '');
    $dateEnd = purchasingIssueDateTimeFilter($_REQUEST['filterTanggalEnd'] ?? '');
    if ($dateStart === null || $dateEnd === null) {
        http_response_code(400);
        $response['error'] = $dateStart === null ? 'Tanggal dan jam mulai tidak valid.' : 'Tanggal dan jam akhir tidak valid.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return;
    }
    if ($dateStart !== '' && $dateEnd !== '' && $dateStart > $dateEnd) {
        http_response_code(400);
        $response['error'] = 'Rentang tanggal dan jam tidak valid.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return;
    }
    if ($dateStart !== '') {
        $filters[] = 'i.created_at >= ?';
        $parameters[] = $dateStart;
    }
    if ($dateEnd !== '') {
        $filters[] = 'i.created_at < DATEADD(minute, 1, ?)';
        $parameters[] = $dateEnd;
    }

    $search = trim((string) ($_REQUEST['search']['value'] ?? ''));
    if (strlen($search) > 200) {
        http_response_code(400);
        $response['error'] = 'Kata pencarian terlalu panjang.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return;
    }

    $scopeWhere = $scope === 'history'
        ? 'i.status = ?'
        : '(i.status <> ? OR i.status IS NULL)';
    $baseFrom = 'FROM dbo.purchasing_issues i
                 LEFT JOIN dbo.purchasing_issue_categories c ON c.category_id = i.category_id
                 LEFT JOIN dbo.purchasing_issue_sub_categories s
                    ON s.sub_id = i.sub_id AND s.category_id = i.category_id';
    $scopeCountStatement = sqlsrv_query(
        $conn,
        'SELECT COUNT_BIG(*) AS total FROM dbo.purchasing_issues i WHERE ' . $scopeWhere,
        ['Done']
    );
    if ($scopeCountStatement === false) {
        http_response_code(500);
        $response['error'] = 'Data issues tidak dapat dimuat.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return;
    }
    $scopeCountRow = sqlsrv_fetch_array($scopeCountStatement, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($scopeCountStatement);
    if ($scopeCountRow === false) {
        http_response_code(500);
        $response['error'] = 'Data issues tidak dapat dimuat.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return;
    }
    $response['recordsTotal'] = (int) ($scopeCountRow['total'] ?? 0);

    $where = 'WHERE ' . implode(' AND ', $filters);
    $filteredWhere = $where;
    $filteredParameters = $parameters;
    if ($search !== '') {
        $searchPattern = str_replace(['\\', '%', '_', '['], ['\\\\', '\\%', '\\_', '\\['], $search);
        $searchPattern = '%' . $searchPattern . '%';
        $filteredWhere .= ' AND (i.issue_name LIKE ? ESCAPE \'\\\' OR i.issue_type LIKE ? ESCAPE \'\\\' OR c.category_name LIKE ? ESCAPE \'\\\' OR s.sub_name LIKE ? ESCAPE \'\\\' OR i.status LIKE ? ESCAPE \'\\\' OR i.priority LIKE ? ESCAPE \'\\\' OR i.created_by LIKE ? ESCAPE \'\\\')';
        array_push($filteredParameters, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern);
    }

    $filteredCountSql = 'SELECT COUNT_BIG(*) AS total ' . $baseFrom . ' ' . $filteredWhere;
    $filteredCountStatement = sqlsrv_query($conn, $filteredCountSql, $filteredParameters);
    if ($filteredCountStatement === false) {
        http_response_code(500);
        $response['error'] = 'Data issues tidak dapat dimuat.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return;
    }
    $filteredCountRow = sqlsrv_fetch_array($filteredCountStatement, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($filteredCountStatement);
    if ($filteredCountRow === false) {
        http_response_code(500);
        $response['error'] = 'Data issues tidak dapat dimuat.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return;
    }
    $response['recordsFiltered'] = (int) ($filteredCountRow['total'] ?? 0);

    $activeOrderColumns = [
        1 => 'i.created_by',
        2 => 'i.issue_name',
        3 => 'i.issue_type',
        4 => 'c.category_name',
        5 => 's.sub_name',
        6 => 'i.status',
        7 => 'i.priority',
        8 => 'i.created_at'
    ];
    $historyOrderColumns = [
        1 => 'i.created_by',
        2 => 'i.issue_name',
        3 => 'i.issue_type',
        4 => 'c.category_name',
        5 => 's.sub_name',
        6 => 'i.priority',
        7 => 'i.created_at',
        8 => 'i.tanggal_selesai'
    ];
    $orderIndex = filter_var($_REQUEST['order'][0]['column'] ?? 0, FILTER_VALIDATE_INT);
    $orderColumns = $scope === 'history' ? $historyOrderColumns : $activeOrderColumns;
    $orderColumn = $orderIndex === false ? null : ($orderColumns[$orderIndex] ?? null);
    $orderDirection = strtolower((string) ($_REQUEST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $orderColumn = $orderColumn ?? 'i.created_at';
    $dataSql = 'SELECT i.issue_id, i.issue_name, i.issue_type, i.category_id,
                       c.category_name, s.sub_name, i.created_by, i.status, i.priority,
                                               i.due_date, i.description, i.created_at, i.tanggal_selesai
                ' . $baseFrom . ' ' . $filteredWhere . '
                ORDER BY ' . $orderColumn . ' ' . $orderDirection . ', i.issue_id DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY';
    $dataParameters = $filteredParameters;
    $dataParameters[] = $start;
    $dataParameters[] = $length;
    $dataStatement = sqlsrv_query($conn, $dataSql, $dataParameters);
    if ($dataStatement === false) {
        http_response_code(500);
        $response['error'] = 'Data issues tidak dapat dimuat.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return;
    }

    while (($row = sqlsrv_fetch_array($dataStatement, SQLSRV_FETCH_ASSOC)) !== null) {
        if ($row === false) {
            sqlsrv_free_stmt($dataStatement);
            http_response_code(500);
            $response['data'] = [];
            $response['error'] = 'Data issues tidak dapat dimuat.';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            return;
        }
        $dueDate = $row['due_date'] instanceof DateTimeInterface
            ? $row['due_date']->format('Y-m-d')
            : (string) ($row['due_date'] ?? '');
        $createdAt = $row['created_at'] instanceof DateTimeInterface
            ? $row['created_at']->format('Y-m-d H:i:s')
            : (string) ($row['created_at'] ?? '');
        $createdDate = $row['created_at'] instanceof DateTimeInterface
            ? $row['created_at']->format('Y-m-d')
            : substr($createdAt, 0, 10);
        $completionValue = $row['tanggal_selesai'] ?? null;
        $completedAt = $completionValue instanceof DateTimeInterface
            ? $completionValue->format('Y-m-d H:i:s')
            : (string) ($completionValue ?? '');
        $storedCreatorName = trim((string) ($row['created_by'] ?? ''));
        $canMarkDone = $scope === 'active'
            && in_array((string) ($row['status'] ?? ''), ['To Do', 'In Progress'], true)
            && $currentCreatorName !== ''
            && mb_strtolower($storedCreatorName) === mb_strtolower($currentCreatorName);
        $escapeText = static function ($value): string {
            return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $response['data'][] = [
            'issue_id' => (int) $row['issue_id'],
            'issue_name' => $escapeText($row['issue_name']),
            'type' => $escapeText($row['issue_type']),
            'category_id' => (int) ($row['category_id'] ?? 0),
            'kategori' => $escapeText($row['category_name']),
            'subkategori' => $escapeText($row['sub_name']),
            'nama_pemohon' => $escapeText($row['created_by']),
            'status' => $escapeText($row['status']),
            'priority' => $escapeText($row['priority']),
            'due_date' => $escapeText($dueDate),
            'created_at' => $escapeText($createdDate),
            'description' => $escapeText($row['description']),
            'tanggal_selesai' => $completedAt === '' ? null : $escapeText($completedAt),
            'can_mark_done' => $canMarkDone,
            'created_at_raw' => $createdAt
        ];
    }
    sqlsrv_free_stmt($dataStatement);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

sendPurchasingIssueListResponse($conn);
