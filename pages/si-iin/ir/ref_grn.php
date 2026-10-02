<?php
// gg_app/pages/si-iin/ir/ref_grn.php

// ==== DEBUG (boleh dimatikan di produksi) ====
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// koneksi ke DB PR/PO/GRN
require_once __DIR__ . '/../../../koneksi3.php';

/*
  Di koneksi3.php kamu pakai:

    $conn3 = new PDO(...)

  Kita pakai $conn3 sebagai koneksi utama di sini.
*/
if (isset($conn3) && $conn3 instanceof PDO) {
    $db = $conn3;
} else {
    echo json_encode(['error' => 'Koneksi DB tidak ditemukan (conn3).']);
    exit;
}

$q         = trim($_GET['q'] ?? '');
$prod_code = trim($_GET['prod_code'] ?? '');

// kalau tidak ada product code → tidak usah cari apa-apa
if ($prod_code === '') {
    echo json_encode([]);
    exit;
}

/*
  QUERY (disesuaikan dengan contoh Navicat + kebutuhan PRIT/PRDP):

  - Deskripsi diambil dari prppdt.ppproddesc
  - Dikelompokkan per GRN (gh.grnnmbr) supaya tidak duplikat.
  - Filter PR: h.ppnmbr LIKE '%PRIT/%' OR '%PRDP/%'
  - Filter product: gd.grnprodcode = :prod_code
*/

$sql = "SELECT
          gh.grnnmbr,
          gh.grndate,
          h.ppnmbr,
          h.ppdate,
          oh.ponmbr,
          oh.podate,

          -- deskripsi diambil dari detail PR, dipaksa 1 baris dengan MAX
          MAX(pd.ppproddesc)   AS ppproddesc,

          -- info produk GRN (kalau mau dipakai)
          MAX(gd.grnprodname)  AS grnprodname,
          MAX(gd.grnprodcode)  AS grnprodcode,

          -- qty contoh, pakai MAX juga
          MAX(od.poqty)        AS poqty
        FROM prpphd h
          -- detail PR
          JOIN prppdt pd 
            ON pd.pphdid = h.pphdid

          -- detail PO (link ke PR lewat nomor PP)
          LEFT JOIN prpodt od 
            ON od.poppnmbr = h.ppnmbr

          -- header PO
          LEFT JOIN prpohd oh 
            ON oh.pohdid = od.pohdid

          -- detail GRN (link ke PO lewat ponmbr + kode barang)
          LEFT JOIN prgrndt gd 
            ON gd.ponmbr     = oh.ponmbr
           AND gd.grnprodcode = od.poprodcode

          -- header GRN
          LEFT JOIN prgrnhd gh 
            ON gh.grnhdid = gd.grnhdid
        WHERE
          (h.ppnmbr LIKE '%PRIT/%' OR h.ppnmbr LIKE '%PRDP/%')
          AND h.fgstatus IN ('U', 'X')
          AND gd.grnprodcode = :prod_code
          AND gh.grnnmbr IS NOT NULL";

$params = [':prod_code' => $prod_code];

// filter berdasarkan ketikan GRN No (opsional, untuk search di Select2)
if ($q !== '') {
    $sql .= " AND gh.grnnmbr LIKE :q";
    $params[':q'] = '%' . $q . '%';
}

$sql .= "
        GROUP BY
          gh.grnnmbr,
          gh.grndate,
          h.ppnmbr,
          h.ppdate,
          oh.ponmbr,
          oh.podate
        ORDER BY
          gh.grndate DESC,
          gh.grnnmbr DESC";

$st = $db->prepare($sql);
$ok = $st->execute($params);

if (!$ok) {
    $err = $st->errorInfo();
    echo json_encode([
        'error'  => 'SQL error',
        'info'   => $err,
        'sql'    => $sql,
        'params' => $params
    ]);
    exit;
}

$out = [];
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $out[] = [
        'id'          => $r['grnnmbr'],                // value di Select2
        'text'        => $r['grnnmbr'],                // label di Select2
        'descr'       => $r['ppproddesc'] ?? '',       // deskripsi dari PR (prppdt.ppproddesc)
        'ppnmbr'      => $r['ppnmbr'],
        'ppdate'      => $r['ppdate'],
        'ponmbr'      => $r['ponmbr'],
        'podate'      => $r['podate'],
        'grndate'     => $r['grndate'],
        'grnprodcode' => $r['grnprodcode'],
        'grnprodname' => $r['grnprodname'],
        'poqty'       => $r['poqty'],
    ];
}

echo json_encode($out);
