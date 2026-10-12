<?php
session_start();
date_default_timezone_set("Asia/Jakarta");
header("Content-Type: application/json; charset=utf-8");
$requestId = bin2hex(random_bytes(8));

function auditApiOut(int $status, array $data): void
{
    global $requestId;
    http_response_code($status);
    echo json_encode(
        array_merge(["request_id" => $requestId], $data),
        JSON_UNESCAPED_UNICODE,
    );
    exit();
}

function auditApiLog(Throwable $e): void
{
    global $requestId;
    $dir = __DIR__ . "/../../../logs";
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $entry = [
        "timestamp" => date(DATE_ATOM),
        "severity" => "ERROR",
        "request_id" => $requestId,
        "module" => "ppc_guidance_v2/resep_obat",
        "action" => "audit_detail_resep",
        "user" => $_SESSION["UserName"] ?? null,
        "message" => "Gagal memuat audit detail resep V2: " . $e->getMessage(),
        "source" => basename(__FILE__),
        "context" => [
            "exception" => get_class($e),
            "file" => $e->getFile(),
            "line" => $e->getLine(),
        ],
    ];
    @file_put_contents(
        $dir . "/error-" . date("Y-m-d") . ".log",
        json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND | LOCK_EX,
    );
}

if (!isset($_SESSION["UserName"]) && php_sapi_name() !== "cli") {
    auditApiOut(401, [
        "ok" => false,
        "message" => "Sesi login telah berakhir.",
    ]);
}

require_once __DIR__ . "/debug_detail_resep_compare.php";

function auditApiLocalProductionNumbers(): array
{
    global $conn;
    $statement = sqlsrv_query(
        $conn,
        "SELECT DISTINCT LTRIM(RTRIM(no_cp)) AS no_cp
         FROM dbo.resep_obat_v2
         WHERE NULLIF(LTRIM(RTRIM(no_cp)), '') IS NOT NULL
           AND LTRIM(RTRIM(no_cp)) <> '-'",
    );
    if (!$statement) {
        throw auditSqlError("Gagal mengambil No CP lokal V2");
    }
    $productionNumbers = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        $productionNumbers[] = $row["no_cp"];
    }
    return $productionNumbers;
}

function auditApiRoutes(): array
{
    global $conn;
    $routeStatement = sqlsrv_query(
        $conn,
        "SELECT
             LTRIM(RTRIM(ISNULL(NULLIF(d.rtg_code, ''), '-'))) AS code,
             MAX(NULLIF(LTRIM(RTRIM(d.rtg_name)), '-')) AS name,
             COUNT(DISTINCT d.id_resep) AS total
         FROM dbo.resep_obat_detail_v2 d
         JOIN dbo.resep_obat_v2 r ON r.id = d.id_resep
         WHERE NULLIF(LTRIM(RTRIM(r.no_cp)), '') IS NOT NULL
           AND r.no_cp <> '-'
           AND NULLIF(LTRIM(RTRIM(d.rtg_code)), '') IS NOT NULL
         GROUP BY LTRIM(RTRIM(ISNULL(NULLIF(d.rtg_code, ''), '-')))
         ORDER BY code",
    );
    if (!$routeStatement) {
        throw auditSqlError("Gagal mengambil routing V2");
    }
    $routes = [];
    while ($route = sqlsrv_fetch_array($routeStatement, SQLSRV_FETCH_ASSOC)) {
        $routes[] = $route;
    }
    return $routes;
}

function auditApiStock(int $recipeId): array
{
    global $conn, $conn3;

    $recipe = auditFindRecipe($recipeId);
    if (!$recipe) {
        auditApiOut(404, [
            "ok" => false,
            "message" => "Resep tidak ditemukan.",
        ]);
    }

    $materialStatement = sqlsrv_query(
        $conn,
        "SELECT
             detail.id AS detail_id,
             ISNULL(detail.table_index, 1) AS table_index,
             detail.kode AS local_code,
             detail.name AS local_name,
             detail.category,
             master.codeprod_proint,
             master.minimum_stock_kg
         FROM dbo.resep_obat_detail_v2 detail
         LEFT JOIN dbo.resep_master_obat master
           ON master.id = (
             SELECT TOP 1 latest_master.id
             FROM dbo.resep_master_obat latest_master
             WHERE LTRIM(RTRIM(latest_master.codeprod_proint)) = LTRIM(RTRIM(detail.kode))
                OR LTRIM(RTRIM(latest_master.kode_obat)) = LTRIM(RTRIM(detail.kode))
             ORDER BY CASE WHEN LTRIM(RTRIM(latest_master.codeprod_proint)) = LTRIM(RTRIM(detail.kode)) THEN 0 ELSE 1 END, latest_master.id DESC
           )
         WHERE detail.id_resep = ?
         ORDER BY ISNULL(detail.table_index, 1), ISNULL(detail.sort_order, detail.id), detail.id",
        [$recipeId],
    );
    if (!$materialStatement) {
        throw auditSqlError("Gagal mengambil mapping material resep V2");
    }

    $materials = [];
    while ($row = sqlsrv_fetch_array($materialStatement, SQLSRV_FETCH_ASSOC)) {
        $detailId = (int) $row["detail_id"];
        if (!isset($materials[$detailId])) {
            $materials[$detailId] = [
                "id" => $detailId,
                "table_index" => (int) $row["table_index"],
                "local_code" => $row["local_code"],
                "local_name" => $row["local_name"],
                "category" => $row["category"],
                "minimum_stock_kg" => $row["minimum_stock_kg"] === null ? 500.0 : (float) $row["minimum_stock_kg"],
                "minimum_stock_is_default" => $row["minimum_stock_kg"] === null,
                "products" => [],
                "message" => "Kode produk ProInt belum dipetakan.",
            ];
        }

        $productCode = trim((string) ($row["codeprod_proint"] ?? ""));
        if ($productCode === "" && preg_match('/^[A-Z]\d{7}\.\d+/', trim((string) $row["local_code"]))) {
            $productCode = trim((string) $row["local_code"]);
        }

        if ($productCode !== "") {
            $materials[$detailId]["products"][$productCode] = [
                "code" => $productCode,
                "name" => "",
                "uom" => "",
                "found" => false,
                "stocks" => [],
                "message" => "Produk ProInt tidak ditemukan.",
            ];
            $materials[$detailId]["message"] = "";
        }
    }

    $stockStatement = $conn3->prepare(
        "SELECT
             product.prodcode,
             product.prodname,
             product_uom.uomcode AS product_uom,
             current_stock.currentstockid,
             current_stock.compid,
             warehouse.wrhscode,
             warehouse.wrhsname,
             warehouse_location.wrhslocationcode,
             warehouse_location.wrhslocationname,
             current_stock.prodqty,
             stock_uom.uomcode AS stock_uom,
             current_stock.upddate,
             current_stock.updflag
         FROM smproduct product
         LEFT JOIN smuom product_uom ON product_uom.uomid = product.uomid
         LEFT JOIN whcurrentstock current_stock
           ON current_stock.prodid = product.prodid
          AND current_stock.prodqty <> 0
         LEFT JOIN whwrhs warehouse ON warehouse.wrhsid = current_stock.wrhsid
         LEFT JOIN whwrhslocation warehouse_location
           ON warehouse_location.wrhslocationid = current_stock.wrhslocid
         LEFT JOIN smuom stock_uom ON stock_uom.uomid = current_stock.produomid
         WHERE TRIM(product.prodcode) = TRIM(:product_code)
         ORDER BY
             current_stock.compid,
             warehouse.wrhsname,
             warehouse_location.wrhslocationname NULLS FIRST,
             current_stock.currentstockid",
    );

    foreach ($materials as &$material) {
        foreach ($material["products"] as &$product) {
            $stockStatement->execute([":product_code" => $product["code"]]);
            $stockRows = $stockStatement->fetchAll(PDO::FETCH_ASSOC);
            if (!$stockRows) {
                continue;
            }

            $product["found"] = true;
            $product["name"] = $stockRows[0]["prodname"];
            $product["uom"] = $stockRows[0]["product_uom"];
            $product["message"] = "Semua stock warehouse bernilai 0.";
            foreach ($stockRows as $stockRow) {
                if ($stockRow["currentstockid"] === null) {
                    continue;
                }
                $product["stocks"][] = [
                    "company_id" => (int) $stockRow["compid"],
                    "warehouse_code" => $stockRow["wrhscode"],
                    "warehouse_name" => $stockRow["wrhsname"],
                    "location_code" => $stockRow["wrhslocationcode"],
                    "location_name" => $stockRow["wrhslocationname"],
                    "qty" => (float) $stockRow["prodqty"],
                    "uom" => $stockRow["stock_uom"] ?: $stockRow["product_uom"],
                    "updated_at" => $stockRow["upddate"],
                    "update_flag" => $stockRow["updflag"],
                ];
            }
            if ($product["stocks"]) {
                $product["message"] = "";
            }
        }
        unset($product);
        $material["products"] = array_values($material["products"]);
    }
    unset($material);

    return [
        "no_cp" => trim((string) ($recipe["no_cp"] ?? "")),
        "materials" => array_values($materials),
        "message" => $materials ? "" : "Detail material resep tidak ditemukan.",
    ];
}

try {
    $action = $_GET["action"] ?? "list";

    if ($action === "detail") {
        $id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT) ?: null;
        $bon = filter_var($_GET["bonreq_id"] ?? null, FILTER_VALIDATE_INT) ?: null;

        $tableOverrides = null;
        if (!empty($_GET["table_bon"]) && is_array($_GET["table_bon"])) {
            $tableOverrides = [];
            foreach ($_GET["table_bon"] as $tIdx => $bId) {
                $tableOverrides[(int) $tIdx] = (int) $bId;
            }
        }

        if (!$id) {
            auditApiOut(422, [
                "ok" => false,
                "message" => "ID resep tidak valid.",
            ]);
        }
        $r = auditFindRecipe($id);
        if (!$r) {
            auditApiOut(404, [
                "ok" => false,
                "message" => "Resep tidak ditemukan.",
            ]);
        }
        auditApiOut(200, [
            "ok" => true,
            "audit" => auditRecipe($r, $bon, true, $tableOverrides),
        ]);
    }

    if ($action === "stock") {
        $id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT) ?: null;
        if (!$id) {
            auditApiOut(422, [
                "ok" => false,
                "message" => "ID resep tidak valid.",
            ]);
        }
        auditApiOut(200, array_merge(["ok" => true], auditApiStock($id)));
    }

    if ($action === "audit_batch") {
        $raw = explode(",", (string) ($_GET["ids"] ?? ""));
        $ids = array_values(
            array_unique(
                array_filter(array_map("intval", $raw), fn($id) => $id > 0),
            ),
        );
        if (!$ids || count($ids) > 5) {
            auditApiOut(422, [
                "ok" => false,
                "message" => "Batch audit harus berisi 1 sampai 5 ID resep.",
            ]);
        }
        $rows = [];
        foreach ($ids as $id) {
            $r = auditFindRecipe($id);
            if (!$r) {
                continue;
            }
            $a = auditRecipe($r, null, true);
            $rows[] = [
                "id" => $id,
                "no_cp" => $r["no_cp"],
                "cus_color" => $r["cus_color"],
                "rtg_code" => $r["rtg_code"],
                "rtg_name" => $r["rtg_name"],
                "is_manual" => $r["is_manual"],
                "status_resep_lipat" => $r["status_resep_lipat"],
                "created_at" => $r["created_at"],
                "status" => $a["status"],
                "status_detail" => $a["status_detail"],
                "source" => $a["source"],
                "candidate_count" => count($a["candidates"]),
                "matched_count" => $a["matched_count"] ?? 0,
                "total_tables" => $a["total_tables"] ?? 1,
                "tables" => array_map(function ($t) {
                    return [
                        "table_index" => $t["table_index"],
                        "rtg_code" => $t["rtg_code"],
                        "rtg_name" => $t["rtg_name"],
                        "bonno" => $t["selected_bon"]["bonno"] ?? ($t["local_bonno"] ?: "-"),
                        "status" => $t["status"],
                    ];
                }, $a["tables"] ?? []),
                "bonno" => implode(", ", array_unique(array_filter(array_map(function ($t) {
                    return $t["selected_bon"]["bonno"] ?? $t["local_bonno"] ?? "";
                }, $a["tables"] ?? [])))),
                "proint_rtgname" => $a["selected_bon"]["rtgname"] ?? "",
            ];
        }
        auditApiOut(200, ["ok" => true, "rows" => $rows]);
    }

    $filters = [
        "cp" => trim((string) ($_GET["cp"] ?? "")),
        "source" => strtoupper(trim((string) ($_GET["source"] ?? ""))),
        "start" => trim((string) ($_GET["start"] ?? "")),
        "end" => trim((string) ($_GET["end"] ?? "")),
        "route" => trim((string) ($_GET["route"] ?? "")),
        "label_jual" => trim((string) ($_GET["label_jual"] ?? "")),
    ];

    if ($action === "label_options") {
        $search = trim((string) ($_GET["q"] ?? ""));
        $labels = mb_strlen($search) < 2
            ? []
            : recipeSearchLocalSaleLabels(
                $conn3,
                auditApiLocalProductionNumbers(),
                $search,
                30,
            );
        auditApiOut(200, [
            "ok" => true,
            "results" => array_map(
                static fn(string $label): array => ["id" => $label, "text" => $label],
                $labels,
            ),
        ]);
    }

    if ($action === "routes") {
        auditApiOut(200, ["ok" => true, "routes" => auditApiRoutes()]);
    }

    if ($action === "ids") {
        $first = auditLocalRows($filters, 0, 1);
        if (!$first["total"]) {
            auditApiOut(200, ["ok" => true, "ids" => [], "total" => 0]);
        }
        $all = auditLocalRows($filters, 0, $first["total"]);
        auditApiOut(200, [
            "ok" => true,
            "ids" => array_map(fn($r) => (int) $r["id"], $all["rows"]),
            "total" => $first["total"],
        ]);
    }

    $page = max(1, (int) ($_GET["page"] ?? 1));
    $limit = 25;
    $data = auditLocalRows($filters, ($page - 1) * $limit, $limit);
    $rows = [];
    foreach ($data["rows"] as $r) {
        $rows[] = [
            "recipe" => $r,
            "status" => "MENUNGGU",
            "status_detail" => "Menunggu pemeriksaan ProInt",
            "source" => "",
            "candidate_count" => 0,
            "bonno" => "",
        ];
    }
    auditApiOut(200, [
        "ok" => true,
        "rows" => $rows,
        "total" => $data["total"],
        "page" => $page,
        "limit" => $limit,
    ]);
} catch (Throwable $e) {
    auditApiLog($e);
    auditApiOut(500, [
        "ok" => false,
        "message" => "Gagal memuat audit detail resep V2.",
    ]);
}
