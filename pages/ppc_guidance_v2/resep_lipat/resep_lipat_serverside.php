<?php
// pages/resep_lipat/resep_lipat_serverside.php
ob_start();
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../../koneksi3.php';
require_once __DIR__ . '/../../../koneksi.php';

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

function resep_lipat_json_response(array $payload): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['UserName'])) {
    http_response_code(401);
    resep_lipat_json_response([
        'draw' => intval($_POST['draw'] ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Unauthorized'
    ]);
}

$draw = intval($_POST['draw'] ?? 1);
$start = max(0, intval($_POST['start'] ?? 0));
$length = intval($_POST['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}

$search = trim($_POST['search']['value'] ?? '');
$cuscolor = trim($_POST['cuscolor'] ?? '');
$colorcode = trim($_POST['colorcode'] ?? '');
$processcode = trim($_POST['processcode'] ?? '');

if ($cuscolor === '') {
    resep_lipat_json_response([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
}

$orderColumnIndex = intval($_POST['order'][0]['column'] ?? 5);
$orderDirection = strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$columnMap = [
    1 => 'c.colorcode',
    2 => 'c.colorname',
    3 => 's.cuscolor',
    4 => 'h.resepdate',
    5 => 'h.resepseq',
    6 => 'h.reseptype',
    7 => 'h.processcode',
    8 => 'h.resepprodcode',
    9 => 'h.resepprodname',
    10 => 'st.statusdesc'
];
$orderBy = $columnMap[$orderColumnIndex] ?? 'h.resepdate';

// Halaman utama: TIDAK JOIN dengan pdresepdt agar 1 resephdid = 1 baris (header info saja).
// Detail routing/material akan dimuat di halaman detail (placeholder saat ini).
$baseFrom = 'FROM pdresephd AS h
    INNER JOIN pdcolorms AS c ON h.colormsid = c.colormsid
    INNER JOIN (
        SELECT colormsid, MAX(cuscolor) AS cuscolor
        FROM smprodtechdata
        GROUP BY colormsid
    ) AS s ON c.colormsid = s.colormsid
    LEFT JOIN pdresepstatus AS st ON h.resepstatusid = st.resepstatusid';

$where = ['s.cuscolor ILIKE :cuscolor'];
$params = [':cuscolor' => '%' . $cuscolor . '%'];

if ($colorcode !== '') {
    $where[] = 'c.colorcode ILIKE :colorcode';
    $params[':colorcode'] = '%' . $colorcode . '%';
}

if ($processcode !== '') {
    $where[] = 'h.processcode ILIKE :processcode';
    $params[':processcode'] = '%' . $processcode . '%';
}

$baseWhere = 'WHERE ' . implode(' AND ', $where);
$filteredWhere = $baseWhere;
$filteredParams = $params;

if ($search !== '') {
    $filteredWhere .= ' AND (
        CAST(h.resephdid AS TEXT) ILIKE :search OR
        c.colorcode ILIKE :search OR
        c.colorname ILIKE :search OR
        s.cuscolor ILIKE :search OR
        h.resepno ILIKE :search OR
        CAST(h.resepseq AS TEXT) ILIKE :search OR
        TO_CHAR(h.resepdate, \'DD/MM/YYYY\') ILIKE :search OR
        TO_CHAR(h.resepdate, \'YYYY-MM-DD\') ILIKE :search OR
        h.reseptype ILIKE :search OR
        h.processcode ILIKE :search OR
        h.resepprodcode ILIKE :search OR
        h.resepprodname ILIKE :search OR
        h.prdnmbr ILIKE :search OR
        s.cuscolor ILIKE :search OR
        h.transnmbr ILIKE :search OR
        COALESCE(st.statusdesc, \'\') ILIKE :search
    )';
    $filteredParams[':search'] = '%' . $search . '%';
}

try {
    $countSql = "SELECT COUNT(*) {$baseFrom} {$baseWhere}";
    $countStmt = $conn3->prepare($countSql);
    $countStmt->execute($params);
    $recordsTotal = intval($countStmt->fetchColumn());

    $filteredSql = "SELECT COUNT(*) {$baseFrom} {$filteredWhere}";
    $filteredStmt = $conn3->prepare($filteredSql);
    $filteredStmt->execute($filteredParams);
    $recordsFiltered = intval($filteredStmt->fetchColumn());

    $dataSql = "SELECT
            h.resephdid,
            c.colorcode,
            c.colorname,
            h.resepno,
            h.resepseq,
            h.resepdate,
            h.reseptype,
            h.processcode,
            h.resepprodcode,
            h.resepprodname,
            h.prdnmbr,
            s.cuscolor,
            h.transnmbr,
            st.statusdesc
        {$baseFrom}
        {$filteredWhere}
        ORDER BY {$orderBy} {$orderDirection}, h.resepseq DESC
        LIMIT :length OFFSET :start";

    $dataStmt = $conn3->prepare($dataSql);
    foreach ($filteredParams as $key => $value) {
        $dataStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $dataStmt->bindValue(':length', $length, PDO::PARAM_INT);
    $dataStmt->bindValue(':start', $start, PDO::PARAM_INT);
    $dataStmt->execute();

    $rows = [];
    $resephdids = [];
    $noSeqKeys = [];

    while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = $row;
        if (!empty($row['resephdid'])) {
            $resephdids[] = intval($row['resephdid']);
        }
        if (!empty($row['resepno']) && isset($row['resepseq'])) {
            $noSeqKeys[] = [
                'no' => $row['resepno'],
                'seq' => intval($row['resepseq'])
            ];
        }
    }

    $statusMapByHdid = [];
    $statusMapByNoSeq = [];

    if (!empty($resephdids) || !empty($noSeqKeys)) {
        $whereClauses = [];
        $sqlParams = [];

        if (!empty($resephdids)) {
            $placeholders = implode(',', array_fill(0, count($resephdids), '?'));
            $whereClauses[] = "proint_resephdid IN ($placeholders)";
            foreach ($resephdids as $idVal) {
                $sqlParams[] = $idVal;
            }
        }

        if (!empty($noSeqKeys)) {
            $subOrs = [];
            foreach ($noSeqKeys as $keyPair) {
                $subOrs[] = "(resep_no = ? AND resep_seq = ?)";
                $sqlParams[] = $keyPair['no'];
                $sqlParams[] = $keyPair['seq'];
            }
            if (!empty($subOrs)) {
                $whereClauses[] = "(" . implode(" OR ", $subOrs) . ")";
            }
        }

        if (!empty($whereClauses)) {
            $sqlServerQuery = "SELECT proint_resephdid, resep_no, resep_seq, status_resep_lipat 
                               FROM dbo.resep_obat_v2 
                               WHERE (" . implode(" OR ", $whereClauses) . ") 
                               ORDER BY updated_at DESC, created_at DESC";
            
            $stmtSqlServer = sqlsrv_query($conn, $sqlServerQuery, $sqlParams);
            if ($stmtSqlServer) {
                while ($soRow = sqlsrv_fetch_array($stmtSqlServer, SQLSRV_FETCH_ASSOC)) {
                    $hdid = $soRow['proint_resephdid'] !== null ? intval($soRow['proint_resephdid']) : null;
                    $rNo = $soRow['resep_no'] !== null ? trim($soRow['resep_no']) : null;
                    $rSeq = $soRow['resep_seq'] !== null ? intval($soRow['resep_seq']) : null;
                    $statusLipat = $soRow['status_resep_lipat'] !== null ? trim($soRow['status_resep_lipat']) : '';

                    if ($statusLipat !== '') {
                        if ($hdid !== null && !isset($statusMapByHdid[$hdid])) {
                            $statusMapByHdid[$hdid] = $statusLipat;
                        }
                        if ($rNo !== null && $rSeq !== null) {
                            $key = $rNo . '|' . $rSeq;
                            if (!isset($statusMapByNoSeq[$key])) {
                                $statusMapByNoSeq[$key] = $statusLipat;
                            }
                        }
                    }
                }
            }
        }
    }

    $data = [];
    foreach ($rows as $row) {
        // FILTER: Only show data if it exists in SQL Server (koneksi.php)
        $resephdidVal = $row['resephdid'] ? intval($row['resephdid']) : null;
        $noSeqKey = ($row['resepno'] && isset($row['resepseq'])) ? trim($row['resepno']) . '|' . intval($row['resepseq']) : '';

        // Check if this record exists in SQL Server
        $existsInSqlServer = false;
        if ($resephdidVal !== null && isset($statusMapByHdid[$resephdidVal])) {
            $existsInSqlServer = true;
        } elseif ($noSeqKey !== '' && isset($statusMapByNoSeq[$noSeqKey])) {
            $existsInSqlServer = true;
        }

        // Skip if not found in SQL Server
        if (!$existsInSqlServer) {
            continue;
        }

        $resepDate = '-';
        if (!empty($row['resepdate'])) {
            $timestamp = strtotime($row['resepdate']);
            $resepDate = $timestamp ? date('d/m/Y', $timestamp) : $row['resepdate'];
        }

        $manualStatus = '';
        if ($resephdidVal !== null && isset($statusMapByHdid[$resephdidVal])) {
            $manualStatus = $statusMapByHdid[$resephdidVal];
        } elseif ($noSeqKey !== '' && isset($statusMapByNoSeq[$noSeqKey])) {
            $manualStatus = $statusMapByNoSeq[$noSeqKey];
        }

        $statusText = ($manualStatus !== '') ? $manualStatus : '-';

        $data[] = [
            'id' => $row['resephdid'],
            'colorcode' => $row['colorcode'] ?? '-',
            'colorname' => $row['colorname'] ?? '-',
            'resepno' => $row['resepno'] ?? '-',
            'resepdate' => $resepDate,
            'resepseq' => $row['resepseq'] ?? '-',
            'reseptype' => $row['reseptype'] ?? '-',
            'processcode' => $row['processcode'] ?? '-',
            'resepprodcode' => $row['resepprodcode'] ?? '-',
            'resepprodname' => $row['resepprodname'] ?? '-',
            'prdnmbr' => $row['prdnmbr'] ?? '-',
            'cuscolor' => $row['cuscolor'] ?? '-',
            'transnmbr' => $row['transnmbr'] ?? '-',
            'statusdesc' => $statusText
        ];
    }

    // Recalculate recordsFiltered based on filtered data count
    $recordsFiltered = count($data);

    resep_lipat_json_response([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    resep_lipat_json_response([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Gagal memuat data resep lipat.'
    ]);
}
