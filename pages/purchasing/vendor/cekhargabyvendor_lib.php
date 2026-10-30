<?php

function parseVendorProductCodes(string $input): array
{
    $parts = preg_split('/[\s,]+/', strtoupper(trim($input)), -1, PREG_SPLIT_NO_EMPTY);
    $codes = [];
    foreach ($parts ?: [] as $part) {
        $codes[$part] = $part;
    }
    return array_values($codes);
}

function fetchActiveVendor(PDO $conn3, string $vendorCode): ?array
{
    $stmt = $conn3->prepare("SELECT vendorcode, vendorname FROM smvendor WHERE fgstatus = 'A' AND vendorcode = :vendorcode LIMIT 1");
    $stmt->bindValue(':vendorcode', $vendorCode);
    $stmt->execute();
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    return $vendor ?: null;
}

/** Get latest PO lines per product for this vendor, with one row per associated GRN. */
function fetchVendorPriceRows(PDO $conn3, string $vendorCode, array $productCodes, bool $showAllHistory = false): array
{
    if (!$productCodes) {
        return [];
    }

    $productParams = [];
    $productPlaceholders = [];
    foreach ($productCodes as $index => $code) {
        $key = ':product' . $index;
        $productPlaceholders[] = $key;
        $productParams[$key] = $code;
    }

    $vendor = $vendorCode !== '' ? fetchActiveVendor($conn3, $vendorCode) : null;
    if ($vendorCode !== '' && !$vendor) {
        return [];
    }
    $vendorProductFilter = '';
    $vendorPoFilter = '';
    $params = $productParams;
    if ($vendor) {
        $vendorProductFilter = ' AND LOWER(BTRIM(ph.povendorname)) = LOWER(BTRIM(:vendor_product_name))';
        $vendorPoFilter = ' AND LOWER(BTRIM(h.povendorname)) = LOWER(BTRIM(:vendor_po_name))';
        $params[':vendor_product_name'] = $vendor['vendorname'];
        $params[':vendor_po_name'] = $vendor['vendorname'];
    }

    $selectedPoCte = $showAllHistory
        ? "selected_po AS (
            SELECT DISTINCT p.prodcode, p.prodid, h.pohdid, h.podate
            FROM prpohd h
            JOIN prpodt d ON d.pohdid = h.pohdid
            JOIN selected_products p ON d.poprodid = p.prodid
            WHERE EXISTS (SELECT 1 FROM prgrndt gd WHERE gd.pohdid = h.pohdid)
            $vendorPoFilter
        )"
        : "selected_po AS (
            SELECT DISTINCT ON (p.prodcode)
                p.prodcode, p.prodid, h.pohdid, h.podate
            FROM prpohd h
            JOIN prpodt d ON d.pohdid = h.pohdid
            JOIN selected_products p ON d.poprodid = p.prodid
            WHERE EXISTS (SELECT 1 FROM prgrndt gd WHERE gd.pohdid = h.pohdid)
            $vendorPoFilter
            ORDER BY p.prodcode, h.podate DESC NULLS LAST, h.pohdid DESC
        )";

    $sql = "
        WITH selected_products AS (
            SELECT p.prodid, p.prodcode, p.prodname, p.prodstructid
            FROM smproduct p
            WHERE UPPER(BTRIM(p.prodcode)) IN (" . implode(', ', $productPlaceholders) . ")
              AND EXISTS (
                  SELECT 1
                  FROM prpodt pd
                  JOIN prpohd ph ON ph.pohdid = pd.pohdid
                  WHERE pd.poprodid = p.prodid
                    $vendorProductFilter
              )
        ), $selectedPoCte, grn_by_po AS (
            SELECT DISTINCT d.pohdid, h.grnhdid, h.grnnmbr, h.grndate
            FROM prgrndt d
            JOIN prgrnhd h ON h.grnhdid = d.grnhdid
        )
        SELECT p.prodcode AS kode_product, p.prodname AS product_name,
            s.structname, h.ponmbr AS no_po, h.createdate AS po_date,
            g.grnnmbr AS no_grn, g.grndate AS grn_date,
            h.povendorname AS vendor_name,
            CAST(d.poprice * h.pocurrrate AS numeric(18,4)) AS price_po_rupiah,
            u.uomcode AS satuan, h.podesc AS deskripsi
        FROM selected_po lp
        JOIN prpohd h ON h.pohdid = lp.pohdid
        JOIN prpodt d ON d.pohdid = h.pohdid AND d.poprodid = lp.prodid
        JOIN selected_products p ON p.prodid = lp.prodid
        JOIN grn_by_po g ON g.pohdid = h.pohdid
        JOIN smuom u ON u.uomid = d.pouomid
        LEFT JOIN smprodstruct s ON s.prodstructid = p.prodstructid
        ORDER BY p.prodcode, h.podate DESC NULLS LAST, h.pohdid DESC, g.grndate, g.grnnmbr
    ";
    $stmt = $conn3->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
