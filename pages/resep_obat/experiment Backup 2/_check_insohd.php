<?php
include __DIR__ . '/../../../koneksi3.php';

echo "================================================================================\n";
echo "ANALISA DEEP: No CP -> Cus Color, Resep Prod Code -> Cus Color, Kode Warna -> Cus Color\n";
echo "================================================================================\n";

// ============================================================
// PATH 1: Kode Warna -> Cus Color
// get_colors.php: pdcolorms.colordesc -> explode('/', parts[0]) = extracted cus_color
// Example: colordesc='0618/1138/AY02-692/1.1.13.0.0143/ROUTING' -> '0618'
// ============================================================
echo "\n=== PATH 1: Kode Warna -> pdcolorms -> colordesc -> cus_color extraction ===\n";
$stmt = $conn3->query("SELECT colormsid, colorcode, colorname, colordesc FROM pdcolorms WHERE colordesc IS NOT NULL AND colordesc <> '' LIMIT 5");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $parts = explode('/', $r['colordesc']);
    $extracted = $parts[0];
    echo "  colormsid={$r['colormsid']}, colorcode={$r['colorcode']}, colorname={$r['colorname']}\n";
    echo "    colordesc = '{$r['colordesc']}'\n";
    echo "    -> extracted cus_color (parts[0]) = '{$extracted}'\n\n";
}

// ============================================================
// PATH 2: Resep Prod Code (Modal Pilih) -> smprodtechdata.cuscolor
// serverside_resep_prod.php: pdcolorms -> smprodtechdata -> smproduct -> cuscolor
// Chain: pdcolorms.colormsid = smprodtechdata.colormsid
//        smprodtechdata.prodid = smproduct.prodid
//        smproduct.prodtype = 'FG'
// Returns: smprodtechdata.cuscolor
// ============================================================
echo "\n=== PATH 2: Resep Prod Code (Modal) -> pdcolorms -> smprodtechdata -> smproduct -> cuscolor ===\n";
// Get a colormsid from pdcolorms that has data
$stmt = $conn3->query("SELECT c.colormsid, c.colorcode, c.colorname FROM pdcolorms c INNER JOIN smprodtechdata t ON t.colormsid = c.colormsid WHERE c.colordesc IS NOT NULL LIMIT 3");
while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  pdcolorms: colormsid={$c['colormsid']}, colorcode={$c['colorcode']}\n";
    // smprodtechdata for this colormsid
    $stmt2 = $conn3->prepare("SELECT td.prodid, td.cuscolor, sp.prodcode, sp.prodname FROM smprodtechdata td INNER JOIN smproduct sp ON sp.prodid = td.prodid WHERE td.colormsid = ? AND LOWER(COALESCE(sp.prodtype,'')) = 'fg' LIMIT 3");
    $stmt2->execute([$c['colormsid']]);
    while ($t = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        echo "    smprodtechdata: prodid={$t['prodid']}, cuscolor='{$t['cuscolor']}'\n";
        echo "    smproduct: prodcode={$t['prodcode']}, prodname={$t['prodname']}\n";
    }
    echo "\n";
}

// ============================================================
// PATH 3: No CP -> ???? -> cuscolor
// get_no_cp.php: pdiso -> pdproductionhd (via isoid)
// pdproductionhd has: prodid, prodcode, prodname
// pdproductionhd.prodcode = smproduct.prodcode
// smproduct.prodid = smprodtechdata.prodid
// smprodtechdata.cuscolor
// BUT: get_no_cp.php does NOT join to smprodtechdata!
// ============================================================
echo "\n=== PATH 3: No CP -> pdproductionhd -> smproduct -> smprodtechdata -> cuscolor (POTENTIAL CHAIN) ===\n";
echo "  CURRENT ISSUE: get_no_cp.php only returns pdproductionhd data.\n";
echo "  It does NOT join to smprodtechdata -> cuscolor.\n";
echo "  So No CP has NO direct way to get cuscolor currently!\n\n";

// Test the actual chain
$stmt = $conn3->query("SELECT ph.prdnmbr, ph.prodcode, ph.prodname, sp.prodid, td.cuscolor
    FROM pdproductionhd ph
    INNER JOIN smproduct sp ON sp.prodcode = ph.prodcode
    INNER JOIN smprodtechdata td ON td.prodid = sp.prodid
    WHERE ph.fgstatus = 'V'
    AND td.cuscolor IS NOT NULL AND td.cuscolor <> ''
    LIMIT 5");
if ($stmt) {
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  No CP: {$r['prdnmbr']}, prodcode: {$r['prodcode']}, cuscolor: '{$r['cuscolor']}'\n";
    }
} else {
    echo "  Query failed or no data.\n";
}

// ============================================================
// CRITICAL ANALYSIS: Are pdcolorms.colordesc prefix and smprodtechdata.cuscolor THE SAME?
// ============================================================
echo "\n=== CRITICAL: Compare pdcolorms.colordesc[0] vs smprodtechdata.cuscolor ===\n";
echo "  These are TWO DIFFERENT fields!\n";
echo "  - pdcolorms.colordesc[0] = extracted from color recipe path (e.g. '0618' from '0618/1138/...')\n";
echo "  - smprodtechdata.cuscolor = actual customer color tag stored per product (e.g. '#1', '0047', 'AY02-692')\n\n";

$stmt = $conn3->query("
    SELECT c.colormsid, c.colorcode, c.colordesc,
           td.cuscolor AS techdata_cuscolor,
           sp.prodcode, sp.prodname
    FROM pdcolorms c
    INNER JOIN smprodtechdata td ON td.colormsid = c.colormsid
    INNER JOIN smproduct sp ON sp.prodid = td.prodid
    WHERE c.colordesc IS NOT NULL
      AND c.colordesc <> ''
      AND td.cuscolor IS NOT NULL
      AND td.cuscolor <> ''
      AND LOWER(COALESCE(sp.prodtype,'')) = 'fg'
    LIMIT 5
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $parts = explode('/', $r['colordesc']);
    $extracted = $parts[0];
    echo "  Kode Warna: {$r['colorcode']}\n";
    echo "    colordesc = '{$r['colordesc']}'\n";
    echo "    -> extracted (parts[0]) = '{$extracted}'\n";
    echo "    smprodtechdata.cuscolor = '{$r['techdata_cuscolor']}'\n";
    echo "    prodcode = {$r['prodcode']}, prodname = {$r['prodname']}\n";
    $match = ($extracted === trim($r['techdata_cuscolor'])) ? "MATCH ✓" : "MISMATCH ✗";
    echo "    Comparison: {$match}\n\n";
}

// ============================================================
// Compare: SOI chain (get_soi.php) uses smprodtechdata.cuscolor
// But Kode Warna (get_colors.php) extracts from pdcolorms.colordesc
// These are DIFFERENT sources - this is the root cause of the "collision"
// ============================================================
echo "\n=== ROOT CAUSE ANALYSIS ===\n";
echo "  get_soi.php uses: smprodtechdata.cuscolor (product-level customer color)\n";
echo "  get_colors.php extracts from: pdcolorms.colordesc[0] (color recipe path prefix)\n";
echo "  serverside_resep_prod.php uses: smprodtechdata.cuscolor (product-level)\n";
echo "  These are DIFFERENT values from DIFFERENT sources!\n";
echo "  This causes data mismatch when:\n";
echo "    - User types cus_color '0047'\n";
echo "    - get_soi.php finds SOI where smprodtechdata.cuscolor = '0047' (from FG products)\n";
echo "    - But get_colors.php finds Kode Warna where colordesc LIKE '0047/%' (from color recipe paths)\n";
echo "    - These two datasets may NOT overlap!\n";
