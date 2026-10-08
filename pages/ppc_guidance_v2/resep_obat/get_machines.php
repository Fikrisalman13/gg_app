<?php
// pages/ppc_guidance_v2/resep_obat/get_machines.php
// Returns machine list for the Production modal Select2 dropdown.
// - When q is empty with resep_id: flat list of machines in the recipe's routing tables (shown on open)
// - When q has keyword with resep_id: two optgroups — "Mesin di Tabel Resep" and "Semua Mesin ProInt"
// - When no resep_id: full ProInt famaster list (fallback)
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['UserName'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../koneksi3.php';

$resepId = isset($_GET['resep_id']) ? (int) $_GET['resep_id'] : null;
$keyword = trim($_GET['q'] ?? '');

try {
    if ($resepId) {
        // ----------------------------------------
        // Step 1: Get machine names from recipe routing tables (SQL Server)
        // ----------------------------------------
        $stmtD = sqlsrv_query(
            $conn,
            "SELECT DISTINCT machine_name FROM dbo.resep_obat_detail_v2 WHERE id_resep = ?",
            [$resepId]
        );
        $tableMachineNames = [];
        while ($stmtD && $r = sqlsrv_fetch_array($stmtD, SQLSRV_FETCH_ASSOC)) {
            $raw = trim((string)($r['machine_name'] ?? ''));
            if ($raw !== '') {
                foreach (array_map('trim', explode(',', $raw)) as $p) {
                    if ($p !== '' && !in_array($p, $tableMachineNames, true)) {
                        $tableMachineNames[] = $p;
                    }
                }
            }
        }

        // ----------------------------------------
        // Step 2: Resolve facode from ProInt famaster (PostgreSQL, READ-ONLY)
        // ----------------------------------------
        $facodeMap = [];
        if (!empty($tableMachineNames)) {
            $clauses = array_fill(0, count($tableMachineNames), 'UPPER(TRIM(faname)) = UPPER(?)');
            $sqlFam = "SELECT facode, faname FROM famaster WHERE "
                . implode(' OR ', $clauses) . " ORDER BY faname ASC";
            $stmtFam = $conn3->prepare($sqlFam);
            $stmtFam->execute($tableMachineNames);
            while ($rowFam = $stmtFam->fetch(PDO::FETCH_ASSOC)) {
                $facodeMap[strtoupper(trim($rowFam['faname']))] = trim($rowFam['facode']);
            }
        }

        // Build table machines results (apply keyword filter if present)
        $tableResults = [];
        foreach ($tableMachineNames as $mName) {
            if ($keyword !== '' && stripos($mName, $keyword) === false) {
                continue;
            }
            $c = $facodeMap[strtoupper($mName)] ?? '';
            $tableResults[] = [
                'id'     => $c ?: $mName,
                'text'   => $mName,
                'faname' => $mName,
                'facode' => $c,
            ];
        }

        // ----------------------------------------
        // Without keyword: return flat table list only (shown immediately on open)
        // ----------------------------------------
        if ($keyword === '') {
            echo json_encode(['results' => $tableResults]);
            exit;
        }

        // ----------------------------------------
        // With keyword: return two optgroups
        // ----------------------------------------

        // Group 2: ProInt master machines (AJAX, READ-ONLY)
        $proIntResults = [];
        $sqlProInt = "SELECT facode, faname
                      FROM famaster
                      WHERE UPPER(COALESCE(facode, '')) LIKE 'FAM%'
                        AND (COALESCE(faname,'') ILIKE :kw OR COALESCE(facode,'') ILIKE :kw)
                      ORDER BY faname ASC
                      LIMIT 80";
        $stmtProInt = $conn3->prepare($sqlProInt);
        $stmtProInt->execute([':kw' => '%' . $keyword . '%']);
        $proIntRows = $stmtProInt->fetchAll(PDO::FETCH_ASSOC);

        // Collect table machine names (upper) to exclude duplicates in ProInt group
        $tableNamesUpper = array_map('strtoupper', $tableMachineNames);

        foreach ($proIntRows as $pr) {
            $name = trim($pr['faname']);
            $code = trim($pr['facode']);
            // Skip if already in table group (avoid duplicate)
            if (in_array(strtoupper($name), $tableNamesUpper, true)) {
                continue;
            }
            $proIntResults[] = [
                'id'     => $code,
                'text'   => $name,
                'faname' => $name,
                'facode' => $code,
            ];
        }

        // Build optgroups response
        $groups = [];
        if (!empty($tableResults)) {
            $groups[] = [
                'text'     => 'Mesin di Tabel Resep',
                'children' => $tableResults,
            ];
        }
        if (!empty($proIntResults)) {
            $groups[] = [
                'text'     => 'Semua Mesin ProInt',
                'children' => $proIntResults,
            ];
        }

        echo json_encode(['results' => $groups]);
        exit;
    }

    // ----------------------------------------
    // Fallback (no resep_id): full ProInt famaster list
    // ----------------------------------------
    $where  = "WHERE UPPER(COALESCE(m.facode, '')) LIKE 'FAM%'";
    $params = [];

    if ($keyword !== '') {
        $where   .= " AND (COALESCE(m.faname,'') ILIKE :kw OR COALESCE(m.facode,'') ILIKE :kw)";
        $params[':kw'] = '%' . $keyword . '%';
    }

    $sql  = "SELECT m.famasterid, m.facode, m.faname
             FROM famaster m
             $where
             ORDER BY m.faname ASC
             LIMIT 100";
    $stmt = $conn3->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = array_map(function ($r) {
        return [
            'id'     => trim($r['facode']),
            'text'   => trim($r['faname']),
            'faname' => trim($r['faname']),
            'facode' => trim($r['facode']),
        ];
    }, $rows);

    echo json_encode(['results' => $results]);
} catch (PDOException $e) {
    echo json_encode(['results' => [], 'error' => $e->getMessage()]);
}
