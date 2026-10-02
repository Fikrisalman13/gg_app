<?php
session_start();
include '../../koneksi.php';
include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$currUser = $_SESSION['UserName'] ?? '';
$groupId = $_SESSION['GroupId'] ?? 0;

// Otorisasi Routing Operator: Group 1 (Admin) tidak dibatasi
$isRestricted = false;
$allowedRoutings = [];

if ($groupId != 1) {
    $sqlFilter = "
        SELECT DISTINCT r.rtg_name 
        FROM planning_user_group u
        INNER JOIN planning_group_rtg r ON u.group_name = r.group_name
        WHERE u.username = ?
    ";
    $stmtFilter = sqlsrv_query($conn, $sqlFilter, [$currUser]);

    $hasEntries = false;
    if ($stmtFilter) {
        while ($r = sqlsrv_fetch_array($stmtFilter, SQLSRV_FETCH_ASSOC)) {
            $hasEntries = true;
            $allowedRoutings[] = $r['rtg_name'];
        }
    }
    // Jika tidak didaftarkan, operator lihat tabel kosong
    if (!$hasEntries) {
        $draw = $_POST['draw'] ?? 1;
        echo json_encode(['draw' => intval($draw), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        exit;
    }
    $isRestricted = true;
}

// DataTables parameters
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 25;
$search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

// Custom filters
$filterRouting = isset($_POST['filter_routing']) ? trim($_POST['filter_routing']) : '';
$filterKategori = isset($_POST['filter_kategori']) ? trim($_POST['filter_kategori']) : '';

$filterGrup = isset($_POST['filter_grup']) ? trim($_POST['filter_grup']) : '';

// Ordering
$orderCol = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
$orderDir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';

// Use column aliases that match SELECT aliases in $baseSelect (used inside wrapping subquery)
$colMap = [
    0 => 'no_cp',
    1 => 'tgl_cp',
    2 => 'label',
    3 => 'cust_color',
    4 => 'kode_lab',
    5 => 'color_name',
    6 => 'material',
    7 => 'qty',
    8 => 'current_routing',
    9 => 'next_routing',
];
$orderColName = $colMap[$orderCol] ?? 'no_cp';

$baseCte = "
WITH active_hd AS (
    SELECT
        h.productionhdid,
        h.prdnmbr,
        h.prddate,
        h.colorid,
        h.prodid,
        rm.prodname AS material,
        rm.matqty AS qty
    FROM pdproductionhd h
    INNER JOIN pdresultmat rm
        ON h.productionhdid = rm.productionhdid
        AND rm.matseq = '1'
    WHERE h.workcenterid = '111'
      AND h.fgstatus = 'U'
),
fast_rtg AS (
    SELECT productionhdid, rtgseq, rtgmsid, prdqty 
    FROM pdproductionrtg 
    WHERE productionhdid IN (SELECT productionhdid FROM active_hd)
),
last_process AS (
    SELECT
        rtg.productionhdid,
        MAX(rtg.rtgseq) AS current_rtgseq
    FROM fast_rtg rtg
    WHERE rtg.prdqty > 0
    GROUP BY rtg.productionhdid
),
base AS (
    SELECT
        req.productionhdid,
        req.prdnumber,
        MAX(req.vlot) AS vlot,
        CASE
            WHEN mat.matqty < 500 THEN 'LAB'
            ELSE 'LA'
        END AS lokasi_timbang
    FROM pdbonreq req
    INNER JOIN active_hd ah ON req.productionhdid = ah.productionhdid
    LEFT JOIN pdproductionmat mat
        ON req.productionhdid = mat.productionhdid
    WHERE
        mat.fgusedtype = 'G'
        AND mat.prodstructid IN ('51385','51386','39592')
        AND req.rtgmsid IN
        ('555','556','559','809','838','842',
         '571','572','573','814','841','844',
         '815','848','849','850')
    GROUP BY
        req.productionhdid,
        req.prdnumber,
        CASE
            WHEN mat.matqty < 500 THEN 'LAB'
            ELSE 'LA'
        END
),
kategori AS (
    SELECT
        prdnumber,
        MAX(vlot) AS vlot,
        COUNT(DISTINCT lokasi_timbang) AS jumlah_kategori,
        MAX(lokasi_timbang) AS jenis_kategori
    FROM base
    GROUP BY prdnumber
)
";

$baseSelect = "
SELECT DISTINCT
    ah.prdnmbr AS no_cp,
    ah.prddate AS tgl_cp,
    smprodtechdata.labeljual AS label,
    smprodtechdata.cuscolor AS cust_color,
    pdcolorms.colorcode AS kode_lab,
    pdcolorms.colorname AS color_name,
    ah.material,
    ah.qty,
    r1.rtgname AS current_routing,
    r2.rtgname AS next_routing,
    k.vlot,
    CASE
        WHEN k.jumlah_kategori = 2 THEN 'MIX'
        WHEN k.jenis_kategori = 'LAB' THEN 'LAB'
        WHEN k.jenis_kategori = 'LA' THEN 'LA'
    END AS kategori_penimbangan
FROM active_hd ah
LEFT JOIN pdcolorms
    ON ah.colorid = pdcolorms.colormsid
LEFT JOIN smprodtechdata
    ON ah.prodid = smprodtechdata.prodid
LEFT JOIN last_process lp
    ON ah.productionhdid = lp.productionhdid
LEFT JOIN fast_rtg a
    ON a.productionhdid = lp.productionhdid
    AND a.rtgseq = lp.current_rtgseq
LEFT JOIN pdrtgms r1
    ON a.rtgmsid = r1.rtgmsid
LEFT JOIN fast_rtg b
    ON b.productionhdid = a.productionhdid
    AND b.rtgseq = a.rtgseq + 1
LEFT JOIN pdrtgms r2
    ON b.rtgmsid = r2.rtgmsid
LEFT JOIN kategori k
    ON ah.prdnmbr = k.prdnumber
";

// Build extra WHERE conditions
$whereExtra = [];
$params = [];

if ($filterRouting !== '') {
    $whereExtra[] = "r2.rtgname = :filter_routing";
    $params[':filter_routing'] = $filterRouting;
}

if ($filterGrup !== '') {
    // Ambil daftar routing dari SQL Server (planning_group_rtg), lalu pakai di PostgreSQL
    $grupRoutings = [];
    $sqlGrupRtg = "SELECT rtg_name FROM planning_group_rtg WHERE group_name = ?";
    $stmtGrupRtg = sqlsrv_query($conn, $sqlGrupRtg, [$filterGrup]);
    if ($stmtGrupRtg) {
        while ($gr = sqlsrv_fetch_array($stmtGrupRtg, SQLSRV_FETCH_ASSOC)) {
            $grupRoutings[] = $gr['rtg_name'];
        }
    }
    if (!empty($grupRoutings)) {
        $grpPlaceholders = [];
        foreach ($grupRoutings as $gi => $grRtg) {
            $key = ":grp_rtg_$gi";
            $grpPlaceholders[] = $key;
            $params[$key] = $grRtg;
        }
        $whereExtra[] = "r2.rtgname IN (" . implode(", ", $grpPlaceholders) . ")";
    } else {
        // Grup dipilih tapi tidak ada routing, return kosong
        $whereExtra[] = "1=0";
    }
}
if ($filterKategori !== '') {
    if ($filterKategori === 'MIX') {
        $whereExtra[] = "k.jumlah_kategori = 2";
    } elseif ($filterKategori === 'LAB') {
        $whereExtra[] = "(k.jumlah_kategori = 1 AND k.jenis_kategori = 'LAB')";
    } elseif ($filterKategori === 'LA') {
        $whereExtra[] = "(k.jumlah_kategori = 1 AND k.jenis_kategori = 'LA')";
    }
}
if ($search !== '') {
    $whereExtra[] = "(ah.prdnmbr ILIKE :search
        OR smprodtechdata.labeljual ILIKE :search
        OR smprodtechdata.cuscolor ILIKE :search
        OR pdcolorms.colorcode ILIKE :search
        OR pdcolorms.colorname ILIKE :search
        OR ah.material ILIKE :search
        OR r1.rtgname ILIKE :search
        OR r2.rtgname ILIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Restricted clause (Security)
$whereRestricted = [];
$paramsRestricted = [];
if ($isRestricted && count($allowedRoutings) > 0) {
    $inPlaceholders = [];
    foreach ($allowedRoutings as $i => $rtg) {
        $key = ":ar_rtg_" . $i;
        $inPlaceholders[] = $key;
        $paramsRestricted[$key] = $rtg;
    }
    $whereRestricted[] = "(r2.rtgname IN (" . implode(", ", $inPlaceholders) . "))";
}
$restrictedClause = count($whereRestricted) ? ' WHERE ' . implode(' AND ', $whereRestricted) : '';

// ... (Existing filter logic for whereExtra) ...
$extraClause = count($whereExtra) ? ' WHERE ' . implode(' AND ', $whereExtra) : '';

try {
    // Count total records (Only those ALLOWED for this user)
    $countAllSql = $baseCte . "SELECT COUNT(*) FROM (" . $baseSelect . $restrictedClause . ") AS cnt_all";
    $stmtAll = $conn3->prepare($countAllSql);
    foreach ($paramsRestricted as $k => $v) {
        $stmtAll->bindValue($k, $v);
    }
    $stmtAll->execute();
    $totalRecords = (int) $stmtAll->fetchColumn();

    // Count filtered records (Allowed + Applied Filters)
    $countFilteredSql = $baseCte . "SELECT COUNT(*) FROM (" . $baseSelect . $extraClause . ") AS cnt_filtered";
    $stmtFiltered = $conn3->prepare($countFilteredSql);
    foreach ($params as $k => $v) {
        $stmtFiltered->bindValue($k, $v);
    }
    $stmtFiltered->execute();
    $totalFiltered = (int) $stmtFiltered->fetchColumn();

    // Main data query
    $dataSql = $baseCte
        . "SELECT * FROM (" . $baseSelect . $extraClause . ") AS data_main"
        . " ORDER BY " . $orderColName . " " . $orderDir
        . " LIMIT :limit OFFSET :offset";
    $stmtData = $conn3->prepare($dataSql);
    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue(':limit', $length, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $start, PDO::PARAM_INT);
    $stmtData->execute();
    $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    // MAPPING RENCANA START DARI SQL SERVER (cpp_paddry)
    $waktuStartMap = [];
    $cpList = [];
    foreach ($rows as $r) {
        if (!empty($r['no_cp'])) {
            $cpList[] = $r['no_cp'];
        }
    }

    if (!empty($cpList)) {
        $inPlaceholders = array_fill(0, count($cpList), '?');
        $sqlRencana = "
            SELECT cp_no, rencana_start 
            FROM dbo.cpp_paddry 
            WHERE cp_no IN (" . implode(',', $inPlaceholders) . ") 
              AND rencana_start IS NOT NULL
        ";
        
        $stmtRencana = sqlsrv_query($conn, $sqlRencana, $cpList);
        if ($stmtRencana) {
            while ($rowR = sqlsrv_fetch_array($stmtRencana, SQLSRV_FETCH_ASSOC)) {
                $cpNo = trim($rowR['cp_no']);
                $rStart = $rowR['rencana_start'] ? $rowR['rencana_start']->format('H:i') : null;
                if ($rStart && $rStart !== '00:00') {
                    $waktuStartMap[$cpNo] = $rStart;
                }
            }
        }
    }

    $data = [];
    foreach ($rows as $r) {
        $tglCp = $r['tgl_cp'] ? date('d-m-Y', strtotime($r['tgl_cp'])) : '-';
        $qty = ($r['qty'] !== null) ? rtrim(rtrim(number_format((float) $r['qty'], 2, '.', ','), '0'), '.') : '-';

        $kat = $r['kategori_penimbangan'] ?? '-';
        switch ($kat) {
            case 'LAB':
                $badge = '<span class="badge badge-pill badge-lab">LAB</span>';
                break;
            case 'LA':
                $badge = '<span class="badge badge-pill badge-la">LA</span>';
                break;
            case 'MIX':
                $badge = '<span class="badge badge-pill badge-mix">MIX</span>';
                break;
            default:
                $badge = '<span class="badge badge-pill badge-secondary">-</span>';
                break;
        }

        $vlot = ($r['vlot'] !== null) ? rtrim(rtrim(number_format((float) $r['vlot'], 4, '.', ','), '0'), '.') : '-';
        
        $noCp = htmlspecialchars($r['no_cp'] ?? '-');
        $waktuStart = isset($waktuStartMap[$r['no_cp']]) ? $waktuStartMap[$r['no_cp']] : '-';

        $no = $start + count($data) + 1; // Correct counter relative to page
        $data[] = [
            $no,
            $noCp,
            $tglCp,
            $waktuStart,
            htmlspecialchars($r['label'] ?? '-'),
            htmlspecialchars($r['cust_color'] ?? '-'),
            htmlspecialchars($r['kode_lab'] ?? '-'),
            htmlspecialchars($r['color_name'] ?? '-'),
            htmlspecialchars($r['material'] ?? '-'),
            $qty,
            htmlspecialchars($r['current_routing'] ?? '-'),
            htmlspecialchars($r['next_routing'] ?? '-'),
            $vlot,
            $badge,
        ];
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $totalFiltered,
        'data' => $data,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => $e->getMessage(),
    ]);
}
