<?php
session_start();
ob_start();

include '../../koneksi3.php';

if (!isset($_SESSION['UserName'])) {
    die("Silakan login terlebih dahulu!");
}

date_default_timezone_set('Asia/Jakarta');

if (!$conn3) {
    die("Koneksi database gagal.");
}

/*==========================================================
    AMBIL DATA DARI TEXTAREA
==========================================================*/

$cpno = $_POST['cpno'] ?? '';

if(trim($cpno)==''){
    die("No CP tidak boleh kosong.");
}

/*==========================================================
    PARSING TEXTAREA
==========================================================*/

$listCP = preg_split("/\r\n|\n|\r/",$cpno);

$listCP = array_map(function($item){

    $item = str_replace("\t","",$item);

    $item = trim($item);

    return $item;

},$listCP);

$listCP = array_filter($listCP);

$listCP = array_unique($listCP);

$listCP = array_values($listCP);

if(count($listCP)==0){
    die("No CP tidak ditemukan.");
}

/*==========================================================
    PLACEHOLDER
==========================================================*/

$placeholder = implode(",",array_fill(0,count($listCP),"?"));

/*==========================================================
    QUERY
==========================================================*/

$query = "

WITH last_process AS(

    SELECT

        productionhdid,

        MAX(rtgseq) last_done_rtgseq

    FROM pdproductionrtg

    WHERE prdqty>0

    GROUP BY productionhdid

),

max_rtg AS(

    SELECT

        productionhdid,

        MAX(rtgseq) max_rtgseq

    FROM pdproductionrtg

    GROUP BY productionhdid

),

current_process AS(

    SELECT

        mr.productionhdid,

        CASE

            WHEN lp.last_done_rtgseq IS NULL

                THEN 1

            WHEN lp.last_done_rtgseq>=mr.max_rtgseq

                THEN lp.last_done_rtgseq

            ELSE

                lp.last_done_rtgseq+1

        END current_rtgseq,

        mr.max_rtgseq

    FROM max_rtg mr

    LEFT JOIN last_process lp

        ON lp.productionhdid=mr.productionhdid

),

routing_seq_1 AS(

    SELECT

        productionhdid,

        prdqty

    FROM pdproductionrtg

    WHERE rtgseq=1

)

SELECT

    h.prdnmbr,

    result.enddate,

    rs1.prdqty,

    std.cuscolor,

    std.labeljual,

    r1.rtgname posisi_saat_ini,

    CASE

        WHEN cp.current_rtgseq IS NULL

            THEN 'Belum ada proses routing'

        WHEN cp.current_rtgseq>=cp.max_rtgseq

            THEN 'Tidak ada routing selanjutnya'

        ELSE

            COALESCE(
                r2.rtgname,
                'Routing selanjutnya tidak ditemukan'
            )

    END next_routing

FROM pdproductionhd h

LEFT JOIN last_process lp

ON lp.productionhdid=h.productionhdid

LEFT JOIN current_process cp

ON cp.productionhdid=h.productionhdid

LEFT JOIN pdproductionrtg result

ON result.productionhdid=h.productionhdid

AND result.rtgseq=lp.last_done_rtgseq

LEFT JOIN pdproductionrtg a

ON a.productionhdid=h.productionhdid

AND a.rtgseq=cp.current_rtgseq

LEFT JOIN pdrtgms r1

ON r1.rtgmsid=a.rtgmsid

LEFT JOIN pdproductionrtg b

ON b.productionhdid=a.productionhdid

AND b.rtgseq=a.rtgseq+1

LEFT JOIN pdrtgms r2

ON r2.rtgmsid=b.rtgmsid

LEFT JOIN routing_seq_1 rs1

ON rs1.productionhdid=h.productionhdid

LEFT JOIN smprodtechdata std

ON std.prodid=h.prodid

WHERE

h.prdnmbr IN ($placeholder)

ORDER BY

h.prdnmbr

";

$stmt = $conn3->prepare($query);

$stmt->execute($listCP);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
/*==========================================================
    HEADER EXCEL
==========================================================*/

$fileName = "Monitoring_Routing_".date("Ymd_His").".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=".$fileName);
header("Pragma: no-cache");
header("Expires: 0");

?>

<html>

<head>

<meta charset="utf-8">

<style>

body{

    font-family:Calibri;

    font-size:11pt;

}

table{

    border-collapse:collapse;

    width:100%;

}

th{

    background:#D9EAD3;

    border:1px solid #000;

    text-align:center;

    font-weight:bold;

    padding:6px;

}

td{

    border:1px solid #000;

    padding:5px;

}

.title{

    font-size:18px;

    font-weight:bold;

}

.subtitle{

    font-size:11px;

}

.center{

    text-align:center;

}

.right{

    text-align:right;

}

</style>

</head>

<body>

<table border="0">

<tr>

<td colspan="8" class="title">

<center>MONITORING ROUTING PRODUKSI</center>

</td>

</tr>



<tr>

<td colspan="8">

&nbsp;

</td>

</tr>

</table>

<table>

<thead>

<tr>

<th width="40">

No

</th>

<th width="180">

No CP

</th>

<th width="150">

Result

</th>

<th width="120">

Qty Pemartaian

</th>

<th width="180">

Customer Color

</th>

<th width="180">

Label Jual

</th>

<th width="220">

Posisi Saat Ini

</th>

<th width="220">

Next Routing

</th>

</tr>

</thead>

<tbody>
    <?php

if(count($rows)>0){

    $no=1;

    foreach($rows as $row){

        $result="-";

        if(!empty($row['enddate'])){

            $result=date(
                "d/m/Y H:i",
                strtotime($row['enddate'])
            );

        }

        $qty=0;

        if($row['prdqty']!==null){

            $qty=$row['prdqty'];

        }

?>

<tr>

<td class="center">

<?=$no++?>

</td>

<td>

<?=htmlspecialchars($row['prdnmbr'])?>

</td>

<td class="center">

<?=$result?>

</td>

<td class="right">

<?=number_format($qty,2)?>

</td>

<td>

<?=htmlspecialchars($row['cuscolor'] ?? '-')?>

</td>

<td>

<?=htmlspecialchars($row['labeljual'] ?? '-')?>

</td>

<td>

<?=htmlspecialchars($row['posisi_saat_ini'] ?? '-')?>

</td>

<td>

<?=htmlspecialchars($row['next_routing'] ?? '-')?>

</td>

</tr>

<?php

    }

}else{

?>

<tr>

<td colspan="8" class="center">

Tidak ada data.

</td>

</tr>

<?php

}

?>

</tbody>

</table>

<br>

<table border="0">

<tr>



</tr>

</table>

</body>

</html>
