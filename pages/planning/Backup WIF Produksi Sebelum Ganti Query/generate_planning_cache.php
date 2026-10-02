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
    include $baseDir . '/../../koneksi.php';
    include $baseDir . '/../../koneksi3.php';

    // 2. QUERY BERAT (PostgreSQL)
    $sql = "
    WITH active_hd AS (
        SELECT
            h.productionhdid, h.prdnmbr, h.prddate, h.colorid, h.prodid,
            rm.prodname AS material, rm.matqty AS qty
        FROM pdproductionhd h
        INNER JOIN pdresultmat rm
            ON h.productionhdid = rm.productionhdid AND rm.fgusedtype = 'A'
        WHERE h.workcenterid = '111' AND h.fgstatus = 'U'
    ),
    fast_rtg AS (
        SELECT productionhdid, rtgseq, rtgmsid, prdqty 
        FROM pdproductionrtg 
        WHERE productionhdid IN (SELECT productionhdid FROM active_hd)
    ),
    last_process AS (
        SELECT rtg.productionhdid, MAX(rtg.rtgseq) AS current_rtgseq
        FROM fast_rtg rtg
        WHERE rtg.prdqty > 0
        GROUP BY rtg.productionhdid
    ),
    base AS (
        SELECT
            req.productionhdid, req.prdnumber, MAX(req.vlot) AS vlot,
            CASE WHEN mat.matqty < 500 THEN 'LAB' ELSE 'LA' END AS lokasi_timbang
        FROM pdbonreq req
        INNER JOIN active_hd ah ON req.productionhdid = ah.productionhdid
        LEFT JOIN pdproductionmat mat ON req.productionhdid = mat.productionhdid
        WHERE mat.fgusedtype = 'G' AND mat.prodstructid IN ('51385','51386','39592')
          AND req.rtgmsid IN ('555','556','559','809','838','842','571','572','573','814','841','844','815','848','849','850')
        GROUP BY req.productionhdid, req.prdnumber, CASE WHEN mat.matqty < 500 THEN 'LAB' ELSE 'LA' END
    ),
    kategori AS (
        SELECT prdnumber, MAX(vlot) AS vlot, COUNT(DISTINCT lokasi_timbang) AS jumlah_kategori, MAX(lokasi_timbang) AS jenis_kategori
        FROM base GROUP BY prdnumber
    ),
    paddry AS (
        SELECT DISTINCT ON (a.productionhdid)
            a.productionhdid,
            r.rtgname AS lokasi_paddry
        FROM pdproductionrtg a
        JOIN pdrtgms r ON a.rtgmsid = r.rtgmsid
        WHERE a.rtgmsid IN (
            '555','556','559','809','838','842',
            '571','572','573','814','841','844',
            '815','848','849','850'
        )
        ORDER BY
            a.productionhdid,
            CASE WHEN r.rtgname NOT LIKE 'TOP%' THEN 0 ELSE 1 END,
            r.rtgname
    )
    SELECT DISTINCT
        ah.prdnmbr AS no_cp, ah.prddate AS tgl_cp,
        smprodtechdata.labeljual AS label, smprodtechdata.cuscolor AS cust_color,
        pdcolorms.colorcode AS kode_lab, pdcolorms.colorname AS color_name,
        ah.material, ah.qty,
        r1.rtgname AS current_routing, r2.rtgname AS next_routing,
        k.vlot, k.jumlah_kategori, k.jenis_kategori,
        COALESCE(p.lokasi_paddry, '-') AS lokasi_paddry
    FROM active_hd ah
    LEFT JOIN pdcolorms ON ah.colorid = pdcolorms.colormsid
    LEFT JOIN smprodtechdata ON ah.prodid = smprodtechdata.prodid
    LEFT JOIN last_process lp ON ah.productionhdid = lp.productionhdid
    LEFT JOIN fast_rtg a ON a.productionhdid = lp.productionhdid AND a.rtgseq = lp.current_rtgseq
    LEFT JOIN pdrtgms r1 ON a.rtgmsid = r1.rtgmsid
    LEFT JOIN fast_rtg b ON b.productionhdid = a.productionhdid AND b.rtgseq = a.rtgseq + 1
    LEFT JOIN pdrtgms r2 ON b.rtgmsid = r2.rtgmsid
    LEFT JOIN kategori k ON ah.prdnmbr = k.prdnumber
    LEFT JOIN paddry p ON ah.productionhdid = p.productionhdid
    ";

    $stmt = $conn3->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. MAPPING RENCANA START (SQL Server) - Jalankan sekaligus
    $waktuStartMap = [];
    $cpList = array_column($rows, 'no_cp');

    if (!empty($cpList)) {
        $placeholders = array_fill(0, count($cpList), '?');
        $sqlRencana = "SELECT cp_no, rencana_start, period_date FROM dbo.cpp_paddry WHERE cp_no IN (" . implode(',', $placeholders) . ") AND rencana_start IS NOT NULL";
        $stmtR = sqlsrv_query($conn, $sqlRencana, $cpList);
        if ($stmtR) {
            while ($r = sqlsrv_fetch_array($stmtR, SQLSRV_FETCH_ASSOC)) {
                $cpNo = trim($r['cp_no']);
                $rStart = $r['rencana_start'] ? $r['rencana_start']->format('H:i') : null;
                $pDate = $r['period_date'] ? $r['period_date']->format('Y-m-d') : null;
                if ($rStart && $rStart !== '00:00') {
                    $waktuStartMap[$cpNo] = [
                        'start' => $rStart,
                        'period' => $pDate
                    ];
                }
            }
        }
    }

    // 4. PREPARE FINAL DATA
    foreach ($rows as &$r) {
        $meta = $waktuStartMap[trim($r['no_cp'])] ?? null;
        $r['waktu_start'] = $meta ? $meta['start'] : '-';
        $r['tgl_planning'] = $meta ? $meta['period'] : null;
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
