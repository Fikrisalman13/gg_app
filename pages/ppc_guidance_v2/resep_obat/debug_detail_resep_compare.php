<?php
// Shared read-only audit logic for local recipe details vs actual ProInt production materials.
require_once __DIR__ . "/../../../koneksi.php";
require_once __DIR__ . "/../../../koneksi3.php";
require_once __DIR__ . "/label_jual_lookup.php";

const RESEP_AUDIT_TOLERANCE = 0.0001;
function auditAllowedCategory($category): bool
{
    $c = strtoupper(trim((string) $category));
    return str_starts_with($c, "DISPERSE") || str_starts_with($c, "REACTIVE");
}
function auditNumEqual($a, $b): bool
{
    return abs((float) $a - (float) $b) <= RESEP_AUDIT_TOLERANCE;
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
        $where[] = "LTRIM(RTRIM(ISNULL(r.rtg_code,'-')))=?";
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
        "SELECT COUNT(*) total FROM dbo.resep_obat r WHERE $ws",
        $params,
    );
    if (!$count) {
        throw auditSqlError("Gagal menghitung resep");
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
                r.created_by
            FROM dbo.resep_obat r
            WHERE $ws
            ORDER BY r.created_at DESC, r.id DESC
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    $p = array_merge($params, [$offset, $limit]);
    $stmt = sqlsrv_query($conn, $sql, $p);
    if (!$stmt) {
        throw auditSqlError("Gagal mengambil resep");
    }
    $rows = [];
    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $r["created_at"] =
            $r["created_at"] instanceof DateTime
                ? $r["created_at"]->format("Y-m-d H:i:s")
                : "";
        $rows[] = $r;
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
             kode,
             name,
             category,
             receipe,
             uom,
             cf,
             uom_cf,
             is_manual
         FROM dbo.resep_obat_detail
         WHERE id_resep = ?
           AND (
               UPPER(LTRIM(RTRIM(category))) LIKE 'DISPERSE%'
               OR UPPER(LTRIM(RTRIM(category))) LIKE 'REACTIVE%'
           )
         ORDER BY id",
        [$id],
    );
    if (!$s) {
        throw auditSqlError("Gagal mengambil detail lokal");
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
             rt.rtgcode,
             rt.rtgname
         FROM pdproductionhd ph
         JOIN pdbonreq br ON br.productionhdid = ph.productionhdid
         JOIN pdrtgms rt ON rt.rtgmsid = br.rtgmsid
         WHERE ph.prdnmbr = :cp
           AND rt.rtgcode IN ('06115', '06116', '06119', '06147', '06158', '06161')
         ORDER BY br.bondate DESC, br.bonseq, br.bonreqid",
    );
    $s->execute([":cp" => $cp]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
function auditChooseBon(array $recipe, array $bons, ?int $bonId = null): array
{
    if (!$bons) {
        return ["status" => "CP TIDAK DITEMUKAN", "bon" => null];
    }
    if ($bonId) {
        foreach ($bons as $b) {
            if ((int) $b["bonreqid"] === $bonId) {
                return ["status" => "", "bon" => $b];
            }
        }
        return ["status" => "ROUTING TIDAK COCOK", "bon" => null];
    }
    $rt = trim((string) ($recipe["rtg_code"] ?? ""));
    if ($rt !== "" && $rt !== "-") {
        foreach ($bons as $b) {
            if (trim((string) $b["rtgcode"]) === $rt) {
                return ["status" => "", "bon" => $b];
            }
        }
        return ["status" => "ROUTING TIDAK COCOK", "bon" => null];
    }
    return count($bons) === 1
        ? ["status" => "", "bon" => $bons[0]]
        : ["status" => "PERLU PILIH BON", "bon" => null];
}
function auditProintDetails(array $bon): array
{
    global $conn, $conn3;
    $s = $conn3->prepare(
        "SELECT
             production_material.prodcode,
             production_material.prodname,
             production_material.matqty,
             unit.uomcode
         FROM pdproductionmat production_material
         LEFT JOIN smuom unit ON unit.uomid = production_material.matuomid
         WHERE production_material.productionhdid = :ph
           AND production_material.productionrtgid = :pr
           AND production_material.fgusedtype = 'G'
         ORDER BY production_material.matseq, production_material.productionmatid",
    );
    $s->execute([
        ":ph" => $bon["productionhdid"],
        ":pr" => $bon["productionrtgid"],
    ]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    $v = (float) $bon["vlot"];
    foreach ($rows as $r) {
        $m = sqlsrv_query(
            $conn,
            "SELECT TOP 1 kode_obat,nama_obat,group_obat,uom FROM dbo.resep_master_obat WHERE codeprod_proint=?",
            [$r["prodcode"]],
        );
        if (!$m) {
            throw auditSqlError("Gagal mapping master obat");
        }
        $l = sqlsrv_fetch_array($m, SQLSRV_FETCH_ASSOC);
        if (!$l || !auditAllowedCategory($l["group_obat"])) {
            continue;
        }
        $out[] = [
            "kode" => $l["kode_obat"],
            "name" => $l["nama_obat"],
            "category" => $l["group_obat"],
            "qty" => (float) $r["matqty"],
            "cf" => $v > 0 ? (float) $r["matqty"] / $v : 0,
            "uom" => $r["uomcode"] ?? "",
            "uom_cf" => $l["uom"] ?? "",
            "codeprod" => $r["prodcode"],
        ];
    }
    return $out;
}
function auditNormalizeUom($value): string
{
    $u = strtoupper(trim((string) $value));
    return in_array($u, ["GR", "G/L"], true) ? "GR" : $u;
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
                $reasons[] = "Qty berbeda";
            }
            if (!auditNumEqual($l["cf"], $p["cf"])) {
                $reasons[] = "CF berbeda";
            }
            if (auditNormalizeUom($l["uom"]) !== auditNormalizeUom($p["uom"])) {
                $reasons[] = "UOM berbeda";
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
        "status_detail" => $summary
            ? implode(", ", $summary)
            : "Semua detail cocok",
        "details" => $out,
    ];
}
function auditLegacyDetails(array $recipe): array
{
    global $conn, $conn3;

    $recipeHeaderId = (int) ($recipe["proint_resephdid"] ?? 0);
    if (!$recipeHeaderId) {
        return [];
    }

    $statement = $conn3->prepare(
        "SELECT d.materialcode, d.materialname, d.qty, r.rtgcode, u.uomcode
         FROM pdresepdt d
         JOIN smproduct p ON p.prodid = d.materialid
         LEFT JOIN pdrtgms r ON r.rtgmsid = d.rtgmsid
         LEFT JOIN smuom u ON u.uomid = p.uomid
         WHERE d.resephdid = :id
           AND d.rtgdesc IN ('RESEP PADDRY DYESTUFF', 'RESEP OBAT PUTIH')
         ORDER BY d.rtgseq, d.materialseq",
    );
    $statement->execute([":id" => $recipeHeaderId]);

    $output = [];
    $vlot = (float) ($recipe["vlot"] ?? 0);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $proIntItem) {
        $localRouteCode = trim((string) ($recipe["rtg_code"] ?? ""));
        if (
            $localRouteCode !== "-" &&
            $localRouteCode !== "" &&
            trim((string) $proIntItem["rtgcode"]) !== $localRouteCode
        ) {
            continue;
        }

        $mappingStatement = sqlsrv_query(
            $conn,
            "SELECT TOP 1 kode_obat, nama_obat, group_obat, uom
             FROM dbo.resep_master_obat
             WHERE codeprod_proint = ?
               AND (UPPER(LTRIM(RTRIM(group_obat))) LIKE 'DISPERSE%'
                    OR UPPER(LTRIM(RTRIM(group_obat))) LIKE 'REACTIVE%')
             ORDER BY CASE WHEN kode_obat = RIGHT(?, 5) THEN 0 ELSE 1 END, kode_obat",
            [$proIntItem["materialcode"], $proIntItem["materialcode"]],
        );
        if (!$mappingStatement) {
            throw auditSqlError("Gagal mapping master obat");
        }

        $localMapping = sqlsrv_fetch_array(
            $mappingStatement,
            SQLSRV_FETCH_ASSOC,
        );
        if (!$localMapping) {
            continue;
        }

        $cf = (float) $proIntItem["qty"];
        $output[] = [
            "kode" => $localMapping["kode_obat"],
            "name" => $localMapping["nama_obat"],
            "category" => $localMapping["group_obat"],
            "qty" => $vlot > 0 ? $vlot * $cf : 0,
            "cf" => $cf,
            "uom" => $localMapping["uom"] ?? ($proIntItem["uomcode"] ?? ""),
            "uom_cf" => $localMapping["uom"] ?? "",
            "codeprod" => $proIntItem["materialcode"],
        ];
    }

    return $output;
}
function auditScoreComparison(array $comparison, bool $sameRoute): array
{
    $code = 0;
    $cf = 0;
    $qty = 0;
    $penalty = 0;
    foreach ($comparison["details"] as $d) {
        if (!$d["local"] || !$d["proint"]) {
            $penalty++;
            continue;
        }
        $code++;
        if (auditNumEqual($d["local"]["cf"], $d["proint"]["cf"])) {
            $cf++;
        }
        if (auditNumEqual($d["local"]["receipe"], $d["proint"]["qty"])) {
            $qty++;
        }
    }
    return [$code, $cf, $qty, -$penalty, $sameRoute ? 1 : 0];
}
function auditBestBon(array $recipe, array $bons, array $local): array
{
    $best = null;
    $ties = 0;
    foreach ($bons as $bon) {
        $cmp = auditCompare($local, auditProintDetails($bon));
        $sameRoute =
            trim((string) ($recipe["rtg_code"] ?? "")) ===
            trim((string) ($bon["rtgcode"] ?? ""));
        $score = auditScoreComparison($cmp, $sameRoute);
        if ($best === null || $score > $best["score"]) {
            $best = [
                "bon" => $bon,
                "comparison" => $cmp,
                "same_route" => $sameRoute,
                "score" => $score,
            ];
            $ties = 1;
        } elseif ($score === $best["score"]) {
            $ties++;
        }
    }
    if ($best) {
        $best["tie_count"] = $ties;
    }
    return $best ?? [];
}
function auditRecipe(
    array $recipe,
    ?int $bonId = null,
    bool $findBestBon = false,
): array {
    $local = auditLocalDetails((int) $recipe["id"]);
    $bons =
        $findBestBon || $bonId
            ? auditBonCandidates(trim($recipe["no_cp"]))
            : [];
    if ($findBestBon && !$bonId && $bons) {
        $best = auditBestBon($recipe, $bons, $local);
        $cmp = $best["comparison"];
        $sameRoute = $best["same_route"];
        $status =
            $cmp["status"] === "COCOK" && !$sameRoute
                ? "COCOK - ROUTING BERBEDA"
                : $cmp["status"];
        $detail =
            $cmp["status_detail"] .
            (!$sameRoute
                ? " | Routing lokal " .
                    trim((string) $recipe["rtg_code"]) .
                    ", ProInt " .
                    trim((string) $best["bon"]["rtgcode"])
                : "");
        if (($best["tie_count"] ?? 0) > 1) {
            $detail .= " | " . $best["tie_count"] . " Bon memiliki skor sama";
        }
        return [
            "recipe" => $recipe,
            "candidates" => $bons,
            "selected_bon" => $best["bon"],
            "source" => "",
            "status" => $status,
            "status_detail" => $detail,
            "details" => $cmp["details"],
        ];
    }
    if ($bonId && $bons) {
        $choice = auditChooseBon($recipe, $bons, $bonId);
        if ($choice["bon"]) {
            $cmp = auditCompare($local, auditProintDetails($choice["bon"]));
            return [
                "recipe" => $recipe,
                "candidates" => $bons,
                "selected_bon" => $choice["bon"],
                "source" => "BON DIPILIH",
                "status" => $cmp["status"],
                "status_detail" => $cmp["status_detail"],
                "details" => $cmp["details"],
            ];
        }
    }
    $legacy = auditLegacyDetails($recipe);
    if ($legacy) {
        $cmp = auditCompare($local, $legacy);
        return [
            "recipe" => $recipe,
            "candidates" => [],
            "selected_bon" => null,
            "source" => "RESEP PROINT LAMA",
            "status" => $cmp["status"],
            "status_detail" => $cmp["status_detail"],
            "details" => $cmp["details"],
        ];
    }
    if (!$bons) {
        $bons = auditBonCandidates(trim($recipe["no_cp"]));
    }
    $choice = auditChooseBon($recipe, $bons, $bonId);
    $base = [
        "recipe" => $recipe,
        "candidates" => $bons,
        "selected_bon" => $choice["bon"],
        "source" => "BON AKTUAL",
        "status" => $choice["status"],
        "status_detail" => $choice["status"],
        "details" => [],
    ];
    if (!$choice["bon"]) {
        return $base;
    }
    $cmp = auditCompare($local, auditProintDetails($choice["bon"]));
    $base["status"] = $cmp["status"];
    $base["status_detail"] = $cmp["status_detail"];
    $base["details"] = $cmp["details"];
    return $base;
}
function auditFindRecipe(int $id): ?array
{
    global $conn;
    $s = sqlsrv_query(
        $conn,
        "SELECT id,no_cp,cus_color,rtg_code,rtg_name,is_manual,status_resep_lipat,proint_resephdid,vlot,created_at,created_by FROM dbo.resep_obat WHERE id=?",
        [$id],
    );
    if (!$s) {
        throw auditSqlError("Gagal mengambil resep");
    }
    $r = sqlsrv_fetch_array($s, SQLSRV_FETCH_ASSOC);
    if ($r && $r["created_at"] instanceof DateTime) {
        $r["created_at"] = $r["created_at"]->format("Y-m-d H:i:s");
    }
    return $r ?: null;
}
