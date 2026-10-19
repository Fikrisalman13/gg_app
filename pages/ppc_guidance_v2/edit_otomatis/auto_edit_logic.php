<?php
// pages/ppc_guidance_v2/edit_otomatis/auto_edit_logic.php
/**
 * Logika Inti Edit Otomatis Resep Obat
 * - Mengambil urutan routing & bon dari PostgreSQL (ERP_Crystal_SUM) berdasarkan No CP
 * - Urutan routing DISORTIR berdasarkan pdproductionrtg.rtgseq ASC NULLS LAST
 * - Mengambil material dari pdproductionmat per routing
 * - Mapping material ke Master Obat lokal (dbo.resep_master_obat)
 * - Lookup harga terkini (Last PO / Template)
 * - Update header resep_obat_v2 & recreate detail resep_obat_detail_v2 secara atomik (transaksi SQL Server)
 */

require_once __DIR__ . '/../../../koneksi.php';
require_once __DIR__ . '/../../../koneksi3.php';

// In-memory cache selama script berjalan untuk mempercepat proses masal
$GLOBALS['_localItemCache'] = [];
$GLOBALS['_itemPriceCache'] = [];

/**
 * Cek mapping kode ProInt ke Master Obat Lokal
 */
function getLocalItemCached($conn, $prointCode) {
    if (empty($prointCode)) return null;
    if (array_key_exists($prointCode, $GLOBALS['_localItemCache'])) {
        return $GLOBALS['_localItemCache'][$prointCode];
    }
    
    $sql = "SELECT kode_obat, nama_obat, group_obat, uom FROM dbo.resep_master_obat WHERE codeprod_proint = ?";
    $stmt = sqlsrv_query($conn, $sql, [$prointCode]);
    $result = null;
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $result = $row;
    }
    $GLOBALS['_localItemCache'][$prointCode] = $result;
    return $result;
}

/**
 * Cek harga material terkini dari PostgreSQL (Priority 1: Last PO, Priority 2: Template)
 */
function getItemPriceCached($conn3, $prodCode) {
    if (empty($prodCode)) {
        return ['price' => 0.0, 'source' => '', 'satuan' => ''];
    }
    if (array_key_exists($prodCode, $GLOBALS['_itemPriceCache'])) {
        return $GLOBALS['_itemPriceCache'][$prodCode];
    }

    $price = 0.0;
    $source = '';
    $satuan = '';

    try {
        // 1. Last PO Price
        $sqlPo = "WITH last_po AS (
                    SELECT h.pohdid 
                    FROM prpohd h
                    JOIN prpodt d ON h.pohdid = d.pohdid
                    JOIN smproduct p ON d.poprodid = p.prodid 
                    WHERE p.prodcode = :prodcode 
                    ORDER BY h.podate DESC, h.pohdid DESC 
                    LIMIT 1 
                ) 
                SELECT (d.poprice * h.pocurrrate) AS price_po
                FROM last_po lp
                JOIN prpohd h ON lp.pohdid = h.pohdid
                JOIN prpodt d ON h.pohdid = d.pohdid
                JOIN smproduct p ON d.poprodid = p.prodid
                WHERE p.prodcode = :prodcode2";
        $stmtPo = $conn3->prepare($sqlPo);
        $stmtPo->execute([':prodcode' => $prodCode, ':prodcode2' => $prodCode]);
        $rowPo = $stmtPo->fetch(PDO::FETCH_ASSOC);

        if ($rowPo && isset($rowPo['price_po']) && (float)$rowPo['price_po'] > 0) {
            $price = (float)$rowPo['price_po'];
            $source = 'PO';
        } else {
            // 2. Template Price
            $sqlTpl = "SELECT whpricetemp.price AS price_template
                       FROM whpricetemp
                       JOIN smproduct ON whpricetemp.prodid = smproduct.prodid
                       WHERE whpricetemp.prodtypename = 'Raw Material' 
                         AND smproduct.prodcode = :prodcode
                       ORDER BY whpricetemp.price DESC
                       LIMIT 1";
            $stmtTpl = $conn3->prepare($sqlTpl);
            $stmtTpl->execute([':prodcode' => $prodCode]);
            $rowTpl = $stmtTpl->fetch(PDO::FETCH_ASSOC);

            if ($rowTpl && isset($rowTpl['price_template'])) {
                $price = (float)$rowTpl['price_template'];
                $source = 'Template';
            }
        }

        // 3. Raw UOM from smproduct
        $sqlUom = "SELECT u.uomcode 
                   FROM smproduct p
                   LEFT JOIN smuom u ON p.uomid = u.uomid 
                   WHERE p.prodcode = :prodcode";
        $stmtUom = $conn3->prepare($sqlUom);
        $stmtUom->execute([':prodcode' => $prodCode]);
        $rowUom = $stmtUom->fetch(PDO::FETCH_ASSOC);
        $satuan = $rowUom['uomcode'] ?? '';

    } catch (Throwable $e) {
        $price = 0.0;
        $source = '';
        $satuan = '';
    }

    $res = [
        'price' => $price,
        'source' => $source,
        'satuan' => $satuan
    ];
    $GLOBALS['_itemPriceCache'][$prodCode] = $res;
    return $res;
}

/**
 * Proses Edit Otomatis untuk 1 Resep
 *
 * @param resource $conn Koneksi SQL Server
 * @param PDO $conn3 Koneksi PostgreSQL
 * @param int $resepId ID resep di dbo.resep_obat_v2
 * @param string $user Nama user yang menjalankan
 * @return array Hasil proses ['ok' => bool, 'message' => string, ...]
 */
function processAutoEdit($conn, $conn3, $resepId, $user = 'system_auto') {
    $resepId = (int)$resepId;
    if ($resepId <= 0) {
        return ['ok' => false, 'message' => 'ID resep tidak valid.'];
    }

    // 1. Ambil data resep eksisting dari SQL Server
    $sqlResep = "SELECT id, no_cp, kode_warna, color_name, cus_color, status_resep_lipat, 
                        weight, plan_qty, vlot, rtg_code, rtg_name, mesin
                 FROM dbo.resep_obat_v2 
                 WHERE id = ?";
    $stmtResep = sqlsrv_query($conn, $sqlResep, [$resepId]);
    if (!$stmtResep || !($resep = sqlsrv_fetch_array($stmtResep, SQLSRV_FETCH_ASSOC))) {
        return ['ok' => false, 'message' => "Resep dengan ID #{$resepId} tidak ditemukan."];
    }

    $noCp = trim((string)($resep['no_cp'] ?? ''));
    if ($noCp === '' || $noCp === '-') {
        return ['ok' => false, 'message' => "Resep #{$resepId} tidak memiliki No CP yang valid."];
    }

    $status = trim((string)($resep['status_resep_lipat'] ?? ''));
    $allowedStatuses = ['shading', 'master resep', 'master', 'kestabilan', 'kesetabilan'];
    if (!in_array(strtolower($status), $allowedStatuses, true)) {
        return ['ok' => false, 'message' => "Status resep '{$status}' tidak termasuk kriteria auto-edit (harus Shading, Master Resep, atau Kestabilan)."];
    }

    // 2. Query Bon & Routing dari PostgreSQL dengan urutan rtgseq ASC NULLS LAST
    $sqlCandidates = "SELECT br.bonreqid, br.bonno, br.bondate, br.bonseq, br.productionhdid, br.productionrtgid, br.rtgmsid, 
                             rt.rtgcode, rt.rtgname, br.planqty, br.bonweight, br.vlot,
                             pr.rtgseq,
                             fa_pr.faname AS assigned_machine,
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
                      ORDER BY pr.rtgseq ASC NULLS LAST, br.bonseq ASC, br.bonreqid ASC";

    $stmtPg = $conn3->prepare($sqlCandidates);
    $stmtPg->execute([':cp' => $noCp]);
    $candidates = $stmtPg->fetchAll(PDO::FETCH_ASSOC);

    if (empty($candidates)) {
        return ['ok' => false, 'message' => "Data bon & routing di ProInt tidak ditemukan untuk No CP '{$noCp}'."];
    }

    // 3. Query Material untuk setiap routing terpilih
    $sqlMat = "SELECT pm.prodcode AS proint_code, pm.prodname AS proint_name, pm.matqty AS qty, pm.prodcf, pm.fgusedtype, u.uomcode AS satuan 
               FROM pdproductionmat pm 
               LEFT JOIN smuom u ON u.uomid = pm.matuomid 
               WHERE pm.productionhdid = :productionhdid AND pm.productionrtgid = :productionrtgid AND pm.fgusedtype IN ('G', 'P') 
               ORDER BY pm.matseq, pm.productionmatid";
    $stmtMat = $conn3->prepare($sqlMat);

    $collectedRtgCodes = [];
    $collectedRtgNames = [];
    $collectedMachines = [];
    $firstTableVlot = null;
    $firstTablePlanQty = null;
    $firstTableWeight = null;

    $detailRows = [];
    $tableIndex = 0;

    foreach ($candidates as $cand) {
        $tableIndex++;
        $rtgCode = trim((string)($cand['rtgcode'] ?? ''));
        $rtgName = trim((string)($cand['rtgname'] ?? ''));
        $machineName = trim((string)($cand['machine_name'] ?? ''));
        $bonNo = trim((string)($cand['bonno'] ?? ''));
        $vlot = (float)($cand['vlot'] ?? 0);
        $bonWeight = (float)($cand['bonweight'] ?? 0);
        $planQty = (float)($cand['planqty'] ?? 0);

        if ($rtgCode !== '' && $rtgCode !== '-' && !in_array($rtgCode, $collectedRtgCodes, true)) {
            $collectedRtgCodes[] = $rtgCode;
        }
        if ($rtgName !== '' && $rtgName !== '-' && !in_array($rtgName, $collectedRtgNames, true)) {
            $collectedRtgNames[] = $rtgName;
        }
        if ($machineName !== '' && $machineName !== '-' && !in_array($machineName, $collectedMachines, true)) {
            $collectedMachines[] = $machineName;
        }

        if ($firstTableVlot === null && $vlot > 0) $firstTableVlot = $vlot;
        if ($firstTablePlanQty === null && $planQty > 0) $firstTablePlanQty = $planQty;
        if ($firstTableWeight === null && $bonWeight > 0) $firstTableWeight = $bonWeight;

        // Fetch materials
        $stmtMat->execute([
            ':productionhdid' => $cand['productionhdid'],
            ':productionrtgid' => $cand['productionrtgid']
        ]);
        $materials = $stmtMat->fetchAll(PDO::FETCH_ASSOC);

        $sortOrder = 0;
        foreach ($materials as $m) {
            $sortOrder++;
            $prointCode = trim((string)($m['proint_code'] ?? ''));
            $prointName = trim((string)($m['proint_name'] ?? ''));
            $qty = (float)($m['qty'] ?? 0);
            $fgUsedType = strtoupper(trim((string)($m['fgusedtype'] ?? 'G')));
            if ($fgUsedType !== 'P') $fgUsedType = 'G';

            // Check mapping ke master obat lokal
            $local = getLocalItemCached($conn, $prointCode);

            // Concentration factor (cf)
            $cf = isset($m['prodcf']) ? (float)$m['prodcf'] : 0.0;
            if ($cf <= 0) {
                if ($fgUsedType === 'P') {
                    $cf = $bonWeight > 0 ? $qty / ($bonWeight * 10) : 0.0;
                } else {
                    $cf = $vlot > 0 ? $qty / $vlot : 0.0;
                }
            }

            // Concentration UOM
            $uomCf = $fgUsedType === 'P' ? '%' : ($local['uom'] ?? 'G/L');
            if (empty($uomCf)) $uomCf = ($fgUsedType === 'P' ? '%' : 'G/L');

            // Satuan obat
            $rawSatuan = strtoupper(trim((string)($m['satuan'] ?? '')));
            $uom = ($rawSatuan === 'G/L') ? 'GR' : ($m['satuan'] ?? 'GR');

            // Kode dan Nama: kolom 'kode' harus menyimpan kode material ProInt agar cocok dengan audit ProInt (debug.php)
            $kode = !empty($prointCode) ? $prointCode : (!empty($local['kode_obat']) ? $local['kode_obat'] : '-');
            $name = !empty($local['nama_obat']) ? $local['nama_obat'] : $prointName;
            $category = !empty($local['group_obat']) ? $local['group_obat'] : 'OTHERS';

            // Lookup harga
            $priceData = getItemPriceCached($conn3, $prointCode);
            $stdPrice = (float)($priceData['price'] ?? 0.0);
            $priceSource = $priceData['source'] ?? '';
            $priceSatuan = $priceData['satuan'] ?? '';

            // Hitung total harga
            $total = $qty * $stdPrice;
            $uomUpper = strtoupper(trim($uom));
            if ($uomUpper === 'GR' || $uomUpper === 'G/L') {
                $total = $total / 1000;
            }

            $detailRows[] = [
                'table_index'  => $tableIndex,
                'sort_order'   => $sortOrder,
                'bonno'        => $bonNo,
                'rtg_code'     => $rtgCode,
                'rtg_name'     => $rtgName,
                'vlot'         => $vlot,
                'kode'         => $kode,
                'name'         => $name,
                'category'     => $category,
                'receipe'      => $qty,
                'uom'          => $uom,
                'cf'           => $cf,
                'uom_cf'       => $uomCf,
                'std_price'    => $stdPrice,
                'total'        => $total,
                'price_satuan' => $priceSatuan,
                'price_source' => $priceSource,
                'is_manual'    => 0,
                'used_type'    => $fgUsedType,
                'machine_name' => $machineName
            ];
        }
    }

    if (empty($detailRows)) {
        return ['ok' => false, 'message' => "Tidak ada material obat yang ditemukan pada routing No CP '{$noCp}'."];
    }

    // 4. Hitung nilai header baru
    $newRtgCode = !empty($collectedRtgCodes) ? implode(', ', $collectedRtgCodes) : '-';
    $newRtgName = !empty($collectedRtgNames) ? implode(', ', $collectedRtgNames) : '-';
    $newMesin = !empty($collectedMachines) ? implode(', ', $collectedMachines) : ($resep['mesin'] ?? '');
    $newVlot = ($firstTableVlot !== null && $firstTableVlot > 0) ? $firstTableVlot : (float)($resep['vlot'] ?? 0);
    $newPlanQty = ($firstTablePlanQty !== null && $firstTablePlanQty > 0) ? $firstTablePlanQty : (float)($resep['plan_qty'] ?? 0);
    $newWeight = ($firstTableWeight !== null && $firstTableWeight > 0) ? $firstTableWeight : (float)($resep['weight'] ?? 0);
    $now = date('Y-m-d H:i:s');

    // 5. Eksekusi Database SQL Server dalam Transaksi
    sqlsrv_begin_transaction($conn);

    try {
        // A. Update header resep_obat_v2
        $sqlUpdateHeader = "UPDATE dbo.resep_obat_v2 
                            SET rtg_code = ?, 
                                rtg_name = ?, 
                                mesin = ?, 
                                vlot = ?, 
                                plan_qty = ?, 
                                weight = ?, 
                                updated_at = ?, 
                                updated_by = ? 
                            WHERE id = ?";
        $paramsHeader = [$newRtgCode, $newRtgName, $newMesin, $newVlot, $newPlanQty, $newWeight, $now, $user, $resepId];
        $stmtUpdateHeader = sqlsrv_query($conn, $sqlUpdateHeader, $paramsHeader);
        if ($stmtUpdateHeader === false) {
            throw new Exception("Gagal memperbarui header resep: " . json_encode(sqlsrv_errors()));
        }

        // B. Pembersihan resep duplikat lama untuk No CP yang sama jika ada
        $sqlFindDups = "SELECT id FROM dbo.resep_obat_v2 WHERE no_cp = ? AND id <> ?";
        $stmtFindDups = sqlsrv_query($conn, $sqlFindDups, [$noCp, $resepId]);
        $duplicateIds = [];
        if ($stmtFindDups) {
            while ($dupRow = sqlsrv_fetch_array($stmtFindDups, SQLSRV_FETCH_ASSOC)) {
                $duplicateIds[] = (int)$dupRow['id'];
            }
        }
        if (!empty($duplicateIds)) {
            $dupPlaceholders = implode(',', array_fill(0, count($duplicateIds), '?'));
            $stmtDelDupD = sqlsrv_query($conn, "DELETE FROM dbo.resep_obat_detail_v2 WHERE id_resep IN ($dupPlaceholders)", $duplicateIds);
            if ($stmtDelDupD === false) {
                throw new Exception("Gagal menghapus detail resep duplikat: " . json_encode(sqlsrv_errors()));
            }
            $stmtDelDupH = sqlsrv_query($conn, "DELETE FROM dbo.resep_obat_v2 WHERE id IN ($dupPlaceholders)", $duplicateIds);
            if ($stmtDelDupH === false) {
                throw new Exception("Gagal menghapus header resep duplikat: " . json_encode(sqlsrv_errors()));
            }
        }

        // C. Hapus detail lama resep_obat_detail_v2
        $sqlDeleteDetails = "DELETE FROM dbo.resep_obat_detail_v2 WHERE id_resep = ?";
        $stmtDeleteDetails = sqlsrv_query($conn, $sqlDeleteDetails, [$resepId]);
        if ($stmtDeleteDetails === false) {
            throw new Exception("Gagal menghapus detail lama resep: " . json_encode(sqlsrv_errors()));
        }

        // C. Insert detail baru yang telah tersusun rapi
        $sqlInsertDetail = "INSERT INTO dbo.resep_obat_detail_v2 
                            (id_resep, table_index, sort_order, bonno, rtg_code, rtg_name, vlot, 
                             kode, name, category, receipe, uom, cf, uom_cf, std_price, total, 
                             created_at, created_by, price_satuan, price_source, is_manual, used_type, machine_name) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        foreach ($detailRows as $row) {
            $paramsDetail = [
                $resepId,
                $row['table_index'],
                $row['sort_order'],
                $row['bonno'],
                $row['rtg_code'],
                $row['rtg_name'],
                $row['vlot'],
                $row['kode'],
                $row['name'],
                $row['category'],
                $row['receipe'],
                $row['uom'],
                $row['cf'],
                $row['uom_cf'],
                $row['std_price'],
                $row['total'],
                $now,
                $user,
                $row['price_satuan'],
                $row['price_source'],
                $row['is_manual'],
                $row['used_type'],
                $row['machine_name']
            ];

            $stmtInsert = sqlsrv_query($conn, $sqlInsertDetail, $paramsDetail);
            if ($stmtInsert === false) {
                throw new Exception("Gagal menyimpan detail resep ({$row['kode']}): " . json_encode(sqlsrv_errors()));
            }
        }

        // D. Commit transaksi
        sqlsrv_commit($conn);

        return [
            'ok'             => true,
            'message'        => "Resep #{$resepId} ({$noCp}) berhasil diperbarui otomatis.",
            'resep_id'       => $resepId,
            'no_cp'          => $noCp,
            'routing_count'  => $tableIndex,
            'detail_count'   => count($detailRows),
            'rtg_summary'    => $newRtgCode,
            'mesin'          => $newMesin
        ];

    } catch (Throwable $e) {
        sqlsrv_rollback($conn);
        return [
            'ok'       => false,
            'message'  => "Error saat memproses resep #{$resepId}: " . $e->getMessage(),
            'resep_id' => $resepId,
            'no_cp'    => $noCp
        ];
    }
}
