<?php
/**
 * Worker Script: Berjalan di background untuk mengupdate cache JSON.
 * Script ini bisa dijalankan via CLI: php generate_planning_cache.php
 */
date_default_timezone_set('Asia/Jakarta');

// Agar tidak ada limit waktu eksekusi untuk query berat
set_time_limit(300);

$baseDir = __DIR__;
$cacheFile = $baseDir . '/planning_cache.json';
$lockFile = $baseDir . '/planning_cache.lock';
$cacheVersion = 3;
$defaultPaddryRtgmsIds = [
    '555','556','559','809','838','842',
    '571','572','573','814','841','844',
    '815','848','849','850'
];

$startTime = microtime(true);

// 1. CEK LOCK (Mencegah double execution)
if (file_exists($lockFile)) {
    // Jika lock file lebih lama dari 3 menit (180s), anggap proses sebelumnya stale/hang
    if (time() - filemtime($lockFile) < 180) {
        exit("Proses update sedang berjalan (Locked).\n");
    }
}

// Pasang LOCK
file_put_contents($lockFile, time());

try {
    require_once $baseDir . '/planning_master_routing_helper.php';
    include $baseDir . '/../../koneksi3.php';

    $routingSnapshot = planning_load_master_routing_snapshot();
    $routingCodes = array_values(array_unique(array_filter(array_map('trim', $routingSnapshot['codes'] ?? []))));
    $routingCodesNormalized = array_values(array_unique(array_filter(array_map('trim', $routingSnapshot['normalized_codes'] ?? []))));

    $paddryFilterSql = '';
    if (!empty($routingCodes) && !empty($routingCodesNormalized)) {
        $quotedCodes = array_map([$conn3, 'quote'], $routingCodes);
        $quotedNormalizedCodes = array_map([$conn3, 'quote'], $routingCodesNormalized);
        $paddryFilterSql = "
        (
            LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))) IN (" . implode(', ', $quotedCodes) . ")
            OR COALESCE(
                NULLIF(REGEXP_REPLACE(LTRIM(RTRIM(CAST(r.rtgcode AS TEXT))), '^0+', ''), ''),
                '0'
            ) IN (" . implode(', ', $quotedNormalizedCodes) . ")
        )";
    } else {
        $quotedRtgmsIds = array_map([$conn3, 'quote'], $defaultPaddryRtgmsIds);
        $paddryFilterSql = "a.rtgmsid IN (" . implode(', ', $quotedRtgmsIds) . ")";
    }

    // 2. QUERY BERAT (PostgreSQL)
    $sql = "
    WITH active_hd AS (
        SELECT
            h.productionhdid,
            h.prdnmbr,
            h.prddate,
            h.colorid,
            h.prodid,
            h.prdqty,
            h.bomhdid
        FROM pdproductionhd h
        WHERE
            h.workcenterid = '111'
            AND h.fgstatus IN ('U','V')
    ),
    fast_rtg AS (
        SELECT
            r.productionhdid,
            r.rtgseq,
            r.rtgmsid,
            r.prdqty
        FROM pdproductionrtg r
        INNER JOIN active_hd h
            ON h.productionhdid = r.productionhdid
    ),
    last_process AS (
        SELECT
            productionhdid,
            MAX(rtgseq) AS current_rtgseq
        FROM fast_rtg
        WHERE prdqty > 0
        GROUP BY productionhdid
    ),
    next_process AS (
        SELECT
            productionhdid,
            MIN(rtgseq) AS next_rtgseq
        FROM fast_rtg
        WHERE COALESCE(prdqty, 0.0000) = 0
        GROUP BY productionhdid
    ),
    base AS (
        SELECT
            pdbonreq.productionhdid,
            pdbonreq.prdnumber,
            MAX(pdbonreq.vlot) AS vlot,
            CASE
                WHEN pdproductionmat.matqty < 500 THEN 'LAB'
                ELSE 'LA'
            END AS lokasi_timbang
        FROM pdbonreq
        INNER JOIN active_hd
            ON active_hd.productionhdid = pdbonreq.productionhdid
        LEFT JOIN pdproductionmat
            ON pdbonreq.productionhdid = pdproductionmat.productionhdid
        WHERE
            pdproductionmat.fgusedtype = 'G'
            AND pdproductionmat.prodstructid IN ('51385','51386','39592')
            AND pdbonreq.rtgmsid IN (
                '555','556','559','809','838','842',
                '571','572','573','814','841','844',
                '815','848','849','850'
            )
        GROUP BY
            pdbonreq.productionhdid,
            pdbonreq.prdnumber,
            CASE
                WHEN pdproductionmat.matqty < 500 THEN 'LAB'
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
    ),
    paddry AS (
        SELECT
            a.productionhdid,
            STRING_AGG(r.rtgname, ', ' ORDER BY r.rtgname) AS lokasi_paddry
        FROM fast_rtg a
        JOIN pdrtgms r
            ON a.rtgmsid = r.rtgmsid
        WHERE {$paddryFilterSql}
        GROUP BY a.productionhdid
    )
    SELECT DISTINCT
        ah.productionhdid,
        ah.prdnmbr AS no_cp,
        ah.prddate AS tgl_cp,
        smprodtechdata.labeljual AS label,
        smprodtechdata.cuscolor AS cust_color,
        pdcolorms.colorcode AS kode_lab,
        smproduct.prodname AS material,
        ah.prdqty AS qty,
        pdcolorms.colorname AS color_name,
        r1.rtgname AS current_routing,
        r2.rtgname AS next_routing,
        k.vlot,
        k.jumlah_kategori,
        k.jenis_kategori,
        COALESCE(p.lokasi_paddry, '-') AS lokasi_paddry
    FROM active_hd ah
    LEFT JOIN pdcolorms
        ON ah.colorid = pdcolorms.colormsid
    LEFT JOIN smprodtechdata
        ON ah.prodid = smprodtechdata.prodid
    LEFT JOIN last_process lp
        ON ah.productionhdid = lp.productionhdid
    LEFT JOIN next_process np
        ON ah.productionhdid = np.productionhdid
    LEFT JOIN fast_rtg a
        ON a.productionhdid = lp.productionhdid
        AND a.rtgseq = lp.current_rtgseq
    LEFT JOIN pdrtgms r1
        ON a.rtgmsid = r1.rtgmsid
    LEFT JOIN fast_rtg b
        ON b.productionhdid = np.productionhdid
        AND b.rtgseq = np.next_rtgseq
    LEFT JOIN pdrtgms r2
        ON b.rtgmsid = r2.rtgmsid
    LEFT JOIN kategori k
        ON ah.prdnmbr = k.prdnumber
    LEFT JOIN paddry p
        ON ah.productionhdid = p.productionhdid
    INNER JOIN pdbomdt
        ON ah.bomhdid = pdbomdt.bomhdid
        AND pdbomdt.fgusedtype = 'A'
    LEFT JOIN smproduct
        ON pdbomdt.prodid = smproduct.prodid
    ";

    $stmt = $conn3->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. PREPARE FINAL DATA
    foreach ($rows as &$r) {
        $r['waktu_start'] = '-';
        $r['tgl_planning'] = null;
        // Format decimal data untuk mempermudah PHP filtering nantinya
        $r['qty'] = (float) $r['qty'];
        $r['vlot'] = (float) $r['vlot'];
        // Kategori Penimbangan (Logic mapping dipindah ke sini agar PHP consumernya ringan)
        $kat = '-';
        if (($r['jumlah_kategori'] ?? 0) == 2) {
            $kat = 'MIX';
        } elseif (($r['jenis_kategori'] ?? null) == 'LAB') {
            $kat = 'LAB';
        } elseif (($r['jenis_kategori'] ?? null) == 'LA') {
            $kat = 'LA';
        }
        $r['kategori_penimbangan'] = $kat;
    }

    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);

    $finalData = [
        'cache_version' => $cacheVersion,
        'generated_at' => date('Y-m-d H:i:s'),
        'execution_time' => $executionTime,
        'row_count' => count($rows),
        'data' => $rows
    ];

    // Simpan ke JSON
    file_put_contents($cacheFile, json_encode($finalData));
    echo "Cache updated successfully: " . count($rows) . " rows in " . $executionTime . "s.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    // Hapus LOCK
    if (file_exists($lockFile))
        unlink($lockFile);
}
