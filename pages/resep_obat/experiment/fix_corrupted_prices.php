<?php
/**
 * FIX CORRUPTED PRICES IN resep_obat_experiment_detail
 * 
 * Root cause: cleanPriceNum() stripped '.' from raw numbers like 71773.8
 * turning them into 717738, then 7177380000 on next reload, etc.
 * 
 * Strategy: For each detail row with a kode, re-fetch price from source
 * (PO or Price Template), recalculate total, update.
 * 
 * Run: php fix_corrupted_prices.php [--dry-run]
 */

require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../koneksi3.php';

$dryRun = isset($argv) && in_array('--dry-run', $argv);
echo $dryRun ? "=== DRY RUN MODE ===\n" : "=== LIVE UPDATE MODE ===\n\n";

// 1. Get all detail rows
$sql = "SELECT d.id, d.kode, d.receipe, d.uom, d.std_price, d.total,
               m.codeprod_proint
        FROM dbo.resep_obat_experiment_detail d
        LEFT JOIN dbo.resep_master_obat m ON d.kode = m.kode_obat
        WHERE d.kode IS NOT NULL AND d.kode <> ''";
$stmt = sqlsrv_query($conn, $sql);
if (!$stmt) { die("Query error: " . print_r(sqlsrv_errors(), true)); }

$rows = [];
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $rows[] = $r; }
echo "Found " . count($rows) . " detail rows to check.\n\n";

$updated = 0; $skipped = 0; $errors = 0;

foreach ($rows as $row) {
    $id = $row['id'];
    $kode = $row['kode'];
    $codeprod = $row['codeprod_proint'] ?? '';
    $qty = (float)($row['receipe'] ?? 0);
    $uom = strtoupper(trim($row['uom'] ?? ''));
    $oldPrice = (float)($row['std_price'] ?? 0);
    $oldTotal = (float)($row['total'] ?? 0);

    // Skip if no codeprod — can't look up price
    if (!$codeprod) {
        echo "[SKIP] ID=$id kode=$kode — no codeprod_proint\n";
        $skipped++;
        continue;
    }

    // 2. Fetch correct price from PO or Template (same logic as get_item_price.php)
    $price = 0; $source = 'none';

    try {
        $sqlPO = "WITH last_po AS (
            SELECT h.pohdid
            FROM prpohd h
            JOIN prpodt d ON h.pohdid = d.pohdid
            JOIN smproduct p ON d.poprodid = p.prodid
            WHERE p.prodcode = :prodcode
            ORDER BY h.podate DESC, h.pohdid DESC
            OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY
        )
        SELECT (d.poprice * h.pocurrrate) AS price_po
        FROM last_po lp
        JOIN prpohd h ON lp.pohdid = h.pohdid
        JOIN prpodt d ON h.pohdid = d.pohdid
        JOIN smproduct p ON d.poprodid = p.prodid
        WHERE p.prodcode = :prodcode2";

        // Try LIMIT first (PostgreSQL/MySQL), fallback to TOP/OFFSET (SQL Server)
        $stmtPO = $conn3->prepare(str_replace(
            'OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY',
            'LIMIT 1',
            $sqlPO
        ));
        $stmtPO->bindParam(':prodcode', $codeprod);
        $stmtPO->bindParam(':prodcode2', $codeprod);
        $stmtPO->execute();
        $rPO = $stmtPO->fetch(PDO::FETCH_ASSOC);

        if ($rPO && (float)$rPO['price_po'] > 0) {
            $price = (float)$rPO['price_po'];
            $source = 'PO';
        } else {
            $sqlT = "SELECT w.price AS price_template
                     FROM whpricetemp w
                     JOIN smproduct p ON w.prodid = p.prodid
                     WHERE w.prodtypename = 'Raw Material'
                       AND p.prodcode = :prodcode
                     ORDER BY w.price DESC
                     LIMIT 1";
            $stmtT = $conn3->prepare($sqlT);
            $stmtT->bindParam(':prodcode', $codeprod);
            $stmtT->execute();
            $rT = $stmtT->fetch(PDO::FETCH_ASSOC);
            if ($rT) {
                $price = (float)$rT['price_template'];
                $source = 'Template';
            }
        }
    } catch (PDOException $e) {
        echo "[ERROR] ID=$id kode=$kode codeprod=$codeprod — " . $e->getMessage() . "\n";
        $errors++;
        continue;
    }

    if ($price <= 0) {
        echo "[SKIP] ID=$id kode=$kode codeprod=$codeprod — no price found in source\n";
        $skipped++;
        continue;
    }

    // 3. Recalculate total
    $newTotal = $qty * $price;
    if ($uom === 'GR' || $uom === 'G/L') $newTotal /= 1000;

    // 4. Check if price actually changed
    $priceDiff = abs($oldPrice - $price) > 0.001;
    $totalDiff = abs($oldTotal - $newTotal) > 0.001;

    if (!$priceDiff && !$totalDiff) {
        // Price is already correct
        continue;
    }

    $factor = $oldPrice > 0 ? round($oldPrice / $price, 1) : 'N/A';
    echo sprintf(
        "[%s] ID=%d kode=%s codeprod=%s\n" .
        "     Price: %s -> %s (old/new = %s)\n" .
        "     Total: %s -> %s | source=%s\n",
        $dryRun ? 'WOULD FIX' : 'FIX',
        $id, $kode, $codeprod,
        number_format($oldPrice, 4), number_format($price, 4), $factor,
        number_format($oldTotal, 4), number_format($newTotal, 4), $source
    );

    if (!$dryRun) {
        $sqlU = "UPDATE dbo.resep_obat_experiment_detail
                 SET std_price = ?, total = ?, updated_at = GETDATE(), updated_by = 'price_fix_script'
                 WHERE id = ?";
        $stmtU = sqlsrv_query($conn, $sqlU, [$price, $newTotal, $id]);
        if (!$stmtU) {
            echo "[ERROR] Failed to update ID=$id\n";
            $errors++;
        } else {
            $updated++;
        }
    } else {
        $updated++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total rows checked: " . count($rows) . "\n";
echo "Updated: $updated\n";
echo "Skipped (no codeprod/price): $skipped\n";
echo "Errors: $errors\n";
