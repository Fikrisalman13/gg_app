<?php
// Shared read-only audit logic for local recipe details vs actual ProInt production materials (V2 Architecture).
require_once __DIR__ . "/../../../koneksi.php";
require_once __DIR__ . "/../../../koneksi3.php";
require_once __DIR__ . "/label_jual_lookup.php";

const RESEP_AUDIT_TOLERANCE = 0.0001;

function auditNumEqual($a, $b): bool
{
    return abs((float) $a - (float) $b) <= RESEP_AUDIT_TOLERANCE;
}

function auditNormalizeUom($value): string
{
    $u = strtoupper(trim((string) $value));
    return in_array($u, ["GR", "G/L"], true) ? "GR" : $u;
}

function auditSqlError(string $message): RuntimeException
{
    return new RuntimeException(
        $message . ": " . json_encode(sqlsrv_errors(), JSON_UNESCAPED_UNICODE),
    );
}

function auditLocalRows(array $filters, int $offset = 0, int $limit = 25): array
{
    global $conn, $conn3;
    $where = ["NULLIF(LTRIM(RTRIM(r.no_cp)),'') IS NOT NULL", "r.no_cp<>'-'"];
    $params = [];

    if (($filters["cp"] ?? "") !== "") {
        $where[] = "r.no_cp LIKE ?";
        $params[] = "%" . $filters["cp"] . "%";
    }

    if (($filters["source"] ?? "") === "MANUAL") {
        $where[] = "r.is_manual=1";
    } elseif (($filters["source"] ?? "") === "PROINT") {
        $where[] = "ISNULL(r.is_manual,0)=0";
    }

    if (($filters["start"] ?? "") !== "") {
        $where[] = "r.created_at>=?";
        $params[] = $filters["start"] . " 00:00:00";
    }

    if (($filters["end"] ?? "") !== "") {
        $where[] = "r.created_at<=?";
        $params[] = $filters["end"] . " 23:59:59";
    }

    if (($filters["route"] ?? "") !== "") {
        $where[] = "EXISTS (
            SELECT 1 FROM dbo.resep_obat_detail_v2 d
            WHERE d.id_resep = r.id AND LTRIM(RTRIM(ISNULL(d.rtg_code, '-'))) = ?
        )";
        $params[] = $filters["route"];
    }

    $saleLabel = trim((string) ($filters["label_jual"] ?? ""));
    $hasLabelFilter = $saleLabel !== "";
    if ($hasLabelFilter) {
        $productionNumbers = recipeProductionNumbersBySaleLabel($conn3, $saleLabel);
        if (!$productionNumbers) {
            return ["total" => 0, "rows" => []];
        }
        $resetTempTable = sqlsrv_query(
            $conn,
            "IF OBJECT_ID('tempdb..#recipe_label_cp') IS NOT NULL DROP TABLE #recipe_label_cp;
             CREATE TABLE #recipe_label_cp (no_cp nvarchar(100) NOT NULL PRIMARY KEY)",
        );
        if (!$resetTempTable) {
            throw auditSqlError("Gagal menyiapkan filter Label Jual");
        }
        foreach (array_chunk($productionNumbers, 500) as $numberBatch) {
            $valuePlaceholders = implode(",", array_fill(0, count($numberBatch), "(?)"));
            $insertTempRows = sqlsrv_query(
                $conn,
                "INSERT INTO #recipe_label_cp (no_cp) VALUES $valuePlaceholders",
                $numberBatch,
            );
            if (!$insertTempRows) {
                throw auditSqlError("Gagal mengisi filter Label Jual");
            }
        }
        $where[] = "EXISTS (
            SELECT 1 FROM #recipe_label_cp label_cp
            WHERE label_cp.no_cp = LTRIM(RTRIM(r.no_cp))
        )";
    }

    $ws = implode(" AND ", $where);
    $count = sqlsrv_query(
        $conn,
        "SELECT COUNT(*) total FROM dbo.resep_obat_v2 r WHERE $ws",
        $params,
    );
    if (!$count) {
        throw auditSqlError("Gagal menghitung resep V2");
    }
    $total = (int) sqlsrv_fetch_array($count, SQLSRV_FETCH_ASSOC)["total"];

    $sql = "SELECT
                r.id,
                r.no_cp,
                r.cus_color,
                r.rtg_code,
                r.rtg_name,
                r.is_manual,
                r.status_resep_lipat,
                r.proint_resephdid,
                r.vlot,
                r.created_at,
                r.created_by,
                r.kode_warna,
                r.lot_no,
                r.weight,
                r.plan_qty
            FROM dbo.resep_obat_v2 r
            WHERE $ws
            ORDER BY r.created_at DESC, r.id DESC
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    $p = array_merge($params, [$offset, $limit]);
    $stmt = sqlsrv_query($conn, $sql, $p);
    if (!$stmt) {
        throw auditSqlError("Gagal mengambil resep V2");
    }

    $rows = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $r["created_at"] =
            $r["created_at"] instanceof DateTime
                ? $r["created_at"]->format("Y-m-d H:i:s")
                : "";
        $rows[] = $r;
    }

    if (!empty($rows)) {
        // Fetch routing and bon summaries per recipe
        $recipeIds = array_column($rows, "id");
        $placeholders = implode(",", array_fill(0, count($recipeIds), "?"));
        $routesStmt = sqlsrv_query(
            $conn,
            "SELECT id_resep, ISNULL(table_index, 1) AS table_index, rtg_code, rtg_name, bonno
             FROM dbo.resep_obat_detail_v2
             WHERE id_resep IN ($placeholders)
             GROUP BY id_resep, ISNULL(table_index, 1), rtg_code, rtg_name, bonno
             ORDER BY id_resep, ISNULL(table_index, 1)",
            $recipeIds,
        );
        $routesByRecipe = [];
        if ($routesStmt) {
            while ($routeRow = sqlsrv_fetch_array($routesStmt, SQLSRV_FETCH_ASSOC)) {
                $rid = (int) $routeRow["id_resep"];
                $routesByRecipe[$rid][] = [
                    "table_index" => (int) $routeRow["table_index"],
                    "rtg_code" => trim((string) ($routeRow["rtg_code"] ?? "")),
                    "rtg_name" => trim((string) ($routeRow["rtg_name"] ?? "")),
                    "bonno" => trim((string) ($routeRow["bonno"] ?? "")),
                ];
            }
        }
        foreach ($rows as &$recipeRow) {
            $rid = (int) $recipeRow["id"];
            $recipeRow["routes_summary"] = $routesByRecipe[$rid] ?? [];
        }
        unset($recipeRow);
    }

    $labelsByProductionNumber = recipeSaleLabelsByProductionNumber(
        $conn3,
        array_column($rows, "no_cp"),
    );
    foreach ($rows as &$recipeRow) {
        $productionNumber = trim((string) ($recipeRow["no_cp"] ?? ""));
        $recipeRow["label_jual"] = $labelsByProductionNumber[$productionNumber] ?? "-";
    }
    unset($recipeRow);

    if ($hasLabelFilter) {
        sqlsrv_query($conn, "IF OBJECT_ID('tempdb..#recipe_label_cp') IS NOT NULL DROP TABLE #recipe_label_cp");
    }
    return ["total" => $total, "rows" => $rows];
}

function auditLocalDetails(int $id): array
{
    global $conn;
    $s = sqlsrv_query(
        $conn,
        "SELECT
             id,
             ISNULL(table_index, 1) AS table_index,
             sort_order,
             bonno,
             rtg_code,
             rtg_name,
             vlot,
             kode,
             name,
             category,
             receipe,
             uom,
             cf,
             uom_cf,
             is_manual,
             used_type
         FROM dbo.resep_obat_detail_v2
         WHERE id_resep = ?
         ORDER BY ISNULL(table_index, 1) ASC, ISNULL(sort_order, id) ASC, id ASC",
        [$id],
    );
    if (!$s) {
        throw auditSqlError("Gagal mengambil detail lokal V2");
    }
    $r = [];
    while ($x = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC)) {
        $r[] = $x;
    }
    return $r;
}

function auditBonCandidates(string $cp): array
{
    global $conn3;
    $s = $conn3->prepare(
        "SELECT
             br.bonreqid,
             br.bonno,
             br.bondate,
             br.productionhdid,
             br.productionrtgid,
             br.vlot,
             br.bonweight,
             br.planqty,
             rt.rtgcode,
             rt.rtgname
         FROM pdproductionhd ph
         JOIN pdbonreq br ON br.productionhdid = ph.productionhdid
         JOIN pdrtgms rt ON rt.rtgmsid = br.rtgmsid
         WHERE ph.prdnmbr = :cp
         ORDER BY br.bondate DESC, br.bonseq, br.bonreqid",
    );
    $s->execute([":cp" => $cp]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

function auditProintDetails(array $bon): array
{
    global $conn, $conn3;
    $s = $conn3->prepare(
        "SELECT
             pm.prodcode,
             pm.prodname,
             pm.matqty,
             pm.prodcf,
             pm.fgusedtype,
             u.uomcode
         FROM pdproductionmat pm
         LEFT JOIN smuom u ON u.uomid = pm.matuomid
         WHERE pm.productionhdid = :ph
           AND pm.productionrtgid = :pr
           AND pm.fgusedtype IN ('G', 'P')
         ORDER BY pm.matseq, pm.productionmatid",
    );
    $s->execute([
        ":ph" => $bon["productionhdid"],
        ":pr" => $bon["productionrtgid"],
    ]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    $v = (float) $bon["vlot"];
    $bw = (float) ($bon["bonweight"] ?? 0);

    foreach ($rows as $r) {
        $usedType = strtoupper(trim((string) ($r["fgusedtype"] ?? "G")));
        if ($usedType !== "P") $usedType = "G";

        // Read ProInt's prodcf directly if available (> 0), fallback to calculated
        $cf = isset($r["prodcf"]) ? (float) $r["prodcf"] : 0;
        if ($cf <= 0) {
            if ($usedType === "P") {
                $cf = $bw > 0 ? (float) $r["matqty"] / ($bw * 10) : 0;
            } else {
                $cf = $v > 0 ? (float) $r["matqty"] / $v : 0;
            }
        }
        $uom_cf = $usedType === "P" ? "%" : "G/L";

        $prodcode = trim((string) $r["prodcode"]);
        $m = sqlsrv_query(
            $conn,
            "SELECT TOP 1 kode_obat, nama_obat, group_obat, uom 
             FROM dbo.resep_master_obat 
             WHERE codeprod_proint = ? OR kode_obat = ?",
            [$prodcode, $prodcode],
        );
        $l = $m ? sqlsrv_fetch_array($m, SQLSRV_FETCH_ASSOC) : null;
        $category = $l["group_obat"] ?? "PROINT MATERIAL";
        if ($usedType !== "P" && !empty($l["uom"])) {
            $uom_cf = $l["uom"];
        }

        $out[] = [
            "kode" => $prodcode,
            "name" => trim((string) $r["prodname"]),
            "category" => $category,
            "qty" => (float) $r["matqty"],
            "cf" => $cf,
            "uom" => $r["uomcode"] ?? "",
            "uom_cf" => $uom_cf,
            "codeprod" => $prodcode,
            "used_type" => $usedType,
        ];
    }
    return $out;
}

function auditCompare(array $local, array $proint): array
{
    $lm = [];
    $pm = [];
    foreach ($local as $r) {
        $lm[strtoupper(trim((string) $r["kode"]))] = $r;
    }
    foreach ($proint as $r) {
        $pm[strtoupper(trim((string) $r["kode"]))] = $r;
    }

    $keys = array_unique(array_merge(array_keys($lm), array_keys($pm)));
    sort($keys);

    $out = [];
    $overall = "COCOK";
    $reasonCounts = [];

    foreach ($keys as $k) {
        $l = $lm[$k] ?? null;
        $p = $pm[$k] ?? null;
        $reasons = [];

        if (!$l) {
            $st = "HANYA PROINT";
            $reasons[] = "Item hanya ada di ProInt";
        } elseif (!$p) {
            $st = "HANYA LOKAL";
            $reasons[] = "Item hanya ada di lokal";
        } else {
            if (!auditNumEqual($l["receipe"], $p["qty"])) {
                $reasons[] = "Qty berbeda (L: " . round((float) $l["receipe"], 4) . " vs P: " . round((float) $p["qty"], 4) . ")";
            }
            if (!auditNumEqual($l["cf"], $p["cf"])) {
                $reasons[] = "CF berbeda (L: " . round((float) $l["cf"], 4) . " vs P: " . round((float) $p["cf"], 4) . ")";
            }
            if (auditNormalizeUom($l["uom"]) !== auditNormalizeUom($p["uom"])) {
                $reasons[] = "UOM berbeda (L: " . $l["uom"] . " vs P: " . $p["uom"] . ")";
            }
            $st = $reasons ? "SELISIH NILAI" : "COCOK";
        }

        $out[] = [
            "kode" => $k,
            "local" => $l,
            "proint" => $p,
            "status" => $st,
            "reasons" => $reasons,
        ];

        if ($st !== "COCOK") {
            $overall = "BERBEDA";
            foreach ($reasons as $reason) {
                $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
            }
        }
    }

    $summary = [];
    foreach ($reasonCounts as $reason => $count) {
        $summary[] = $reason . " (" . $count . ")";
    }

    return [
        "status" => $overall,
        "status_detail" => $summary ? implode(", ", $summary) : "Semua detail cocok",
        "details" => $out,
    ];
}

function auditRecipe(
    array $recipe,
    ?int $bonId = null,
    bool $findBestBon = false,
    ?array $tableBonOverrides = null,
): array {
    $recipeId = (int) $recipe["id"];
    $localRows = auditLocalDetails($recipeId);
    $cp = trim((string) ($recipe["no_cp"] ?? ""));
    $allBons = $cp !== "" ? auditBonCandidates($cp) : [];

    // Group local rows by table_index
    $tablesData = [];
    foreach ($localRows as $r) {
        $tIdx = (int) ($r["table_index"] ?: 1);
        if (!isset($tablesData[$tIdx])) {
            $tablesData[$tIdx] = [
                "table_index" => $tIdx,
                "rtg_code" => trim((string) ($r["rtg_code"] ?? "")),
                "rtg_name" => trim((string) ($r["rtg_name"] ?? "")),
                "bonno" => trim((string) ($r["bonno"] ?? "")),
                "vlot" => (float) ($r["vlot"] ?: ($recipe["vlot"] ?? 0)),
                "items" => [],
            ];
        }
        $tablesData[$tIdx]["items"][] = $r;
    }

    $tablesResult = [];
    $tableStatuses = [];
    $matchedCount = 0;
    $totalTables = count($tablesData);

    foreach ($tablesData as $tIdx => $tInfo) {
        // Candidates for this routing
        $tCandidates = [];
        foreach ($allBons as $b) {
            if ($tInfo["rtg_code"] !== "" && trim((string) $b["rtgcode"]) === $tInfo["rtg_code"]) {
                $tCandidates[] = $b;
            }
        }
        if (empty($tCandidates)) {
            $tCandidates = $allBons;
        }

        // Check override
        $overrideBonId = null;
        if ($tableBonOverrides && isset($tableBonOverrides[$tIdx])) {
            $overrideBonId = (int) $tableBonOverrides[$tIdx];
        } elseif ($bonId && $totalTables === 1) {
            $overrideBonId = $bonId;
        }

        $selectedBon = null;
        if ($overrideBonId) {
            foreach ($allBons as $b) {
                if ((int) $b["bonreqid"] === $overrideBonId) {
                    $selectedBon = $b;
                    break;
                }
            }
        } elseif ($tInfo["bonno"] !== "") {
            foreach ($allBons as $b) {
                if (trim((string) $b["bonno"]) === $tInfo["bonno"]) {
                    $selectedBon = $b;
                    break;
                }
            }
        }

        if (!$selectedBon && !empty($tCandidates)) {
            $selectedBon = $tCandidates[0];
        }

        if (!$selectedBon) {
            $tStatus = "BELUM ADA BON PROINT";
            $tDetail = "Belum ada Bon permintaan obat di ProInt untuk routing ini";
            $cmpDetails = [];
        } else {
            $prointItems = auditProintDetails($selectedBon);
            $cmp = auditCompare($tInfo["items"], $prointItems);
            $tStatus = $cmp["status"];
            $tDetail = $cmp["status_detail"];
            $cmpDetails = $cmp["details"];
            if ($tStatus === "COCOK") {
                $matchedCount++;
            }
        }

        $tableStatuses[] = "T{$tIdx} ({$tInfo['rtg_code']}): $tStatus";

        $tablesResult[] = [
            "table_index" => $tIdx,
            "rtg_code" => $tInfo["rtg_code"],
            "rtg_name" => $tInfo["rtg_name"],
            "vlot" => $tInfo["vlot"],
            "local_bonno" => $tInfo["bonno"],
            "selected_bon" => $selectedBon,
            "candidates" => $tCandidates,
            "status" => $tStatus,
            "status_detail" => $tDetail,
            "details" => $cmpDetails,
        ];
    }

    if ($totalTables === 0) {
        $overallStatus = "TIDAK ADA DETAIL";
    } elseif ($matchedCount === $totalTables) {
        $overallStatus = "COCOK";
    } elseif ($matchedCount > 0) {
        $overallStatus = "COCOK SEBAGIAN ($matchedCount/$totalTables)";
    } else {
        $allNoBon = true;
        foreach ($tablesResult as $tr) {
            if ($tr["status"] !== "BELUM ADA BON PROINT") {
                $allNoBon = false;
                break;
            }
        }
        $overallStatus = $allNoBon ? "BELUM ADA BON PROINT" : "BERBEDA";
    }

    // Backwards-compatible single-selected_bon for legacy callers
    $primarySelectedBon = $tablesResult[0]["selected_bon"] ?? null;

    return [
        "recipe" => $recipe,
        "candidates" => $allBons,
        "selected_bon" => $primarySelectedBon,
        "source" => "BON AKTUAL",
        "status" => $overallStatus,
        "status_detail" => implode(" | ", $tableStatuses),
        "matched_count" => $matchedCount,
        "total_tables" => $totalTables,
        "tables" => $tablesResult,
        "details" => $tablesResult[0]["details"] ?? [],
    ];
}

function auditFindRecipe(int $id): ?array
{
    global $conn;
    $s = sqlsrv_query(
        $conn,
        "SELECT
            id,
            no_cp,
            cus_color,
            rtg_code,
            rtg_name,
            is_manual,
            status_resep_lipat,
            proint_resephdid,
            vlot,
            created_at,
            created_by,
            kode_warna,
            lot_no,
            weight,
            plan_qty
        FROM dbo.resep_obat_v2
        WHERE id = ?",
        [$id],
    );
    if (!$s) {
        throw auditSqlError("Gagal mengambil resep V2");
    }
    $r = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC);
    if ($r && $r["created_at"] instanceof DateTime) {
        $r["created_at"] = $r["created_at"]->format("Y-m-d H:i:s");
    }
    return $r ?: null;
}
