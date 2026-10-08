<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../../koneksi3.php';
require_once __DIR__ . '/verify_proint_item.php';
header('Content-Type: application/json; charset=utf-8');
$requestId = bin2hex(random_bytes(8));

function respond(int $status, array $payload): void {
    global $requestId;
    http_response_code($status);
    echo json_encode(array_merge(['request_id' => $requestId], $payload), JSON_UNESCAPED_UNICODE);
    exit;
}

function logLookupFailure(string $message, array $context = []): void {
    global $requestId;
    $dir = __DIR__ . '/../../../logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $entry = [
        'timestamp' => date(DATE_ATOM),
        'severity' => 'ERROR',
        'request_id' => $requestId,
        'module' => 'ppc_guidance_v2/resep_obat',
        'action' => 'lookup_detail_by_cp',
        'user' => $_SESSION['UserName'] ?? null,
        'message' => $message,
        'source' => basename(__FILE__),
        'context' => $context
    ];
    @file_put_contents($dir . '/error-' . date('Y-m-d') . '.log', json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

if (!isset($_SESSION['UserName'])) {
    respond(401, ['ok' => false, 'message' => 'Sesi login telah berakhir.']);
}

$noCp = trim((string)($_GET['no_cp'] ?? ''));
if ($noCp === '' || strlen($noCp) > 25) {
    respond(422, ['ok' => false, 'message' => 'No CP tidak valid.']);
}

// Support multiple bonreq_ids via array or comma-separated string
$rawBonreqIds = $_GET['bonreq_ids'] ?? null;
$bonreqIds = [];
if (is_array($rawBonreqIds)) {
    foreach ($rawBonreqIds as $bid) {
        $v = filter_var($bid, FILTER_VALIDATE_INT);
        if ($v !== false && $v > 0) $bonreqIds[] = $v;
    }
} elseif (is_string($rawBonreqIds) && trim($rawBonreqIds) !== '') {
    foreach (explode(',', $rawBonreqIds) as $bid) {
        $v = filter_var(trim($bid), FILTER_VALIDATE_INT);
        if ($v !== false && $v > 0) $bonreqIds[] = $v;
    }
}

// Fallback to legacy single bonreq_id
$singleBonreqId = filter_input(INPUT_GET, 'bonreq_id', FILTER_VALIDATE_INT);
if (empty($bonreqIds) && $singleBonreqId) {
    $bonreqIds[] = $singleBonreqId;
}

try {
    $sql = "SELECT br.bonreqid, br.bonno, br.bondate, br.bonseq, br.productionhdid, br.productionrtgid, br.bomhdid, br.rtgmsid, 
                   rt.rtgcode, rt.rtgname, br.planqty, br.bonweight, br.vlot, bh.bomcode, bh.bomname,
                   fa_pr.faname AS assigned_machine,
                   (SELECT STRING_AGG(fa.faname, ', ' ORDER BY fa.faname) 
                    FROM pdrtgmachine rm 
                    JOIN famaster fa ON fa.famasterid = rm.famasterid 
                    WHERE rm.rtgmsid = br.rtgmsid) AS all_machines,
                   COALESCE(
                       fa_pr.faname,
                       (SELECT STRING_AGG(fa.faname, ', ' ORDER BY fa.faname) 
                        FROM pdrtgmachine rm 
                        JOIN famaster fa ON fa.famasterid = rm.famasterid 
                        WHERE rm.rtgmsid = br.rtgmsid)
                   ) AS machine_name
            FROM pdproductionhd ph 
            JOIN pdbonreq br ON br.productionhdid = ph.productionhdid 
            LEFT JOIN pdrtgms rt ON rt.rtgmsid = br.rtgmsid 
            LEFT JOIN pdbomhd bh ON bh.bomhdid = br.bomhdid 
            LEFT JOIN pdproductionrtg pr ON pr.productionrtgid = br.productionrtgid
            LEFT JOIN famaster fa_pr ON fa_pr.famasterid = pr.famasterid
            WHERE ph.prdnmbr = :cp 
            ORDER BY br.bondate DESC, br.bonseq, br.bonreqid";
    $stmt = $conn3->prepare($sql);
    $stmt->execute([':cp' => $noCp]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$candidates) {
        respond(404, ['ok' => false, 'message' => 'Data bon untuk No CP tidak ditemukan.']);
    }

    // If no bonreqIds requested, just return list of candidates
    if (empty($bonreqIds)) {
        respond(200, ['ok' => true, 'candidates' => $candidates]);
    }

    $candidatesById = [];
    foreach ($candidates as $cand) {
        $candidatesById[(int)$cand['bonreqid']] = $cand;
    }

    $matSql = "SELECT pm.prodcode AS proint_code, pm.prodname AS proint_name, pm.matqty AS qty, pm.prodcf, pm.fgusedtype, u.uomcode AS satuan 
               FROM pdproductionmat pm 
               LEFT JOIN smuom u ON u.uomid = pm.matuomid 
               WHERE pm.productionhdid = :productionhdid AND pm.productionrtgid = :productionrtgid AND pm.fgusedtype IN ('G', 'P') 
               ORDER BY pm.matseq, pm.productionmatid";
    $matStmt = $conn3->prepare($matSql);

    $routings = [];
    foreach ($bonreqIds as $bid) {
        if (!isset($candidatesById[$bid])) continue;
        $selected = $candidatesById[$bid];

        $matStmt->execute([
            ':productionhdid' => $selected['productionhdid'],
            ':productionrtgid' => $selected['productionrtgid']
        ]);
        $rows = $matStmt->fetchAll(PDO::FETCH_ASSOC);

        $details = [];
        $vlot = (float)$selected['vlot'];
        $bonweight = (float)$selected['bonweight'];
        foreach ($rows as $row) {
            $local = checkLocalItem($row['proint_code']);
            $qty = (float)$row['qty'];
            $fgUsedType = strtoupper(trim((string)($row['fgusedtype'] ?? 'G')));
            if ($fgUsedType !== 'P') $fgUsedType = 'G';

            // Get CF: prefer ProInt's prodcf, fallback to calculated
            $cf = isset($row['prodcf']) ? (float)$row['prodcf'] : 0;
            if ($cf <= 0) {
                if ($fgUsedType === 'P') {
                    $cf = $bonweight > 0 ? $qty / ($bonweight * 10) : 0;
                } else {
                    $cf = $vlot > 0 ? $qty / $vlot : 0;
                }
            }

            // Concentration UOM
            $uomCf = $fgUsedType === 'P' ? '%' : ($local['uom'] ?? 'G/L');
            if (empty($uomCf)) $uomCf = ($fgUsedType === 'P' ? '%' : 'G/L');

            // Metadata: if local not found, use proint data and flag warning
            $kode = $local['kode_obat'] ?? '-';
            $name = $local['nama_obat'] ?? $row['proint_name'];
            $category = !empty($local['group_obat']) ? $local['group_obat'] : 'OTHERS';
            $warning = '';
            if (!$local) {
                $warning = "Material {$row['proint_code']} ({$row['proint_name']}) belum terdaftar di Master Obat lokal.";
            }

            $details[] = [
                'kode' => $kode,
                'codeprod' => $row['proint_code'],
                'name' => $name,
                'category' => $category,
                'receipe' => $qty,
                'uom' => strtoupper(trim((string)($row['satuan'] ?? ''))) === 'G/L' ? 'GR' : ($row['satuan'] ?? 'GR'),
                'cf' => $cf,
                'uom_cf' => $uomCf,
                'used_type' => $fgUsedType,
                'std_price' => 0,
                'price_source' => '',
                'satuan' => $row['satuan'] ?? 'GR',
                'warning' => $warning,
                'is_unmapped' => !$local ? 1 : 0
            ];
        }

        $routings[] = [
            'bonreqid'     => (int)$selected['bonreqid'],
            'bonno'        => $selected['bonno'] ?? '',
            'bondate'      => $selected['bondate'] ?? '',
            'rtgcode'      => $selected['rtgcode'] ?? '',
            'rtgname'      => $selected['rtgname'] ?? '',
            'machine_name' => $selected['machine_name'] ?? '',
            'assigned_machine' => $selected['assigned_machine'] ?? '',
            'all_machines' => $selected['all_machines'] ?? '',
            'vlot'         => (float)$selected['vlot'],
            'bonweight'    => (float)$selected['bonweight'],
            'planqty'      => (float)$selected['planqty'],
            'details'      => $details
        ];
    }

    if (empty($routings)) {
        respond(404, ['ok' => false, 'message' => 'Bon yang dipilih tidak sesuai dengan No CP.']);
    }

    // For single legacy compatibility, also attach first selected and details
    $first = $routings[0];
    respond(200, [
        'ok' => true,
        'selected' => [
            'bonreqid' => $first['bonreqid'],
            'bonno' => $first['bonno'],
            'rtgcode' => $first['rtgcode'],
            'rtgname' => $first['rtgname'],
            'vlot' => $first['vlot'],
            'bonweight' => $first['bonweight'],
            'planqty' => $first['planqty']
        ],
        'details' => $first['details'],
        'routings' => $routings
    ]);

} catch (Throwable $e) {
    logLookupFailure('Gagal mengambil detail resep berdasarkan No CP.', [
        'exception' => get_class($e),
        'message' => $e->getMessage()
    ]);
    respond(500, ['ok' => false, 'message' => 'Gagal mengambil detail resep.']);
}
