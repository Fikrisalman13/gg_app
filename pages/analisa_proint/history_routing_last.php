<?php
session_start();
ob_start();

include '../../koneksi.php';
include '../../koneksi3.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header("Location:/gg_app/login.php");
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

if (!$conn || !$conn3) {
    die("Koneksi database gagal.");
}

date_default_timezone_set('Asia/Jakarta');

/* =====================================================
   PERMISSION
===================================================== */

$groupId = $_SESSION['GroupId'];
$menuId  = 75;

$sql = "
SELECT TOP 1 CanView
FROM dbo.SMGroupTrustee
WHERE GroupId=?
AND MenuId=?";

$stmt = sqlsrv_query($conn,$sql,[$groupId,$menuId]);

$permissions=[];

if($stmt && $row=sqlsrv_fetch_array($stmt,SQLSRV_FETCH_ASSOC)){
    $permissions=$row;
}

sqlsrv_free_stmt($stmt);

if(isset($permissions['CanView']) && $permissions['CanView']==0){
    die("Anda tidak memiliki hak untuk melihat halaman ini.");
}

/* =====================================================
   FILTER
===================================================== */

$cpno = $_POST['cpno'] ?? '';

$results=[];

if(trim($cpno)!=""){

    /*
        Pisahkan berdasarkan ENTER
    */

    $listCP = preg_split("/\r\n|\n|\r/",$cpno);

    /*
        Hilangkan TAB
        Hilangkan spasi depan belakang
    */

    $listCP = array_map(function($item){

        $item=str_replace("\t","",$item);

        $item=trim($item);

        return $item;

    },$listCP);

    /*
        Hilangkan baris kosong
    */

    $listCP=array_filter($listCP);

    /*
        Hilangkan duplicate
    */

    $listCP=array_unique($listCP);

    /*
        Reset index array
    */

    $listCP=array_values($listCP);

    /*
        Buat placeholder
    */

    $placeholder=implode(",",array_fill(0,count($listCP),"?"));

$query="

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

lp.productionhdid,

CASE

WHEN lp.last_done_rtgseq IS NULL THEN 1

WHEN lp.last_done_rtgseq>=mr.max_rtgseq
THEN lp.last_done_rtgseq

ELSE lp.last_done_rtgseq+1

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

ELSE COALESCE(r2.rtgname,'Routing selanjutnya tidak ditemukan')

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

WHERE h.prdnmbr IN ($placeholder)

ORDER BY h.prdnmbr

";

$stmt=$conn3->prepare($query);

$stmt->execute($listCP);

$results=$stmt->fetchAll(PDO::FETCH_ASSOC);

}

?>
<div class="wrapper">
        <div id="loadingOverlay">

    <div class="loading-content">

        <div class="spinner-border text-light"
             role="status"
             style="width:4rem;height:4rem;">

        </div>

        <br><br>

        <h4>

            Sedang memproses data...

        </h4>

        <p>

            Mohon tunggu.

        </p>

    </div>

</div>

    <div class="content-wrapper">

        <!-- Content Header -->
        <div class="content-header">

            <div class="container-fluid">

                <div class="row mb-2">

                    <div class="col-sm-6">
                        <h1 class="m-0">
                            Monitoring Routing Produksi
                        </h1>
                    </div>

                    <div class="col-sm-6">

                        <ol class="breadcrumb float-sm-right">

                            <li class="breadcrumb-item">
                                <a href="/gg_app/index.php">
                                    Beranda
                                </a>
                            </li>

                            <li class="breadcrumb-item active">
                                Monitoring Routing
                            </li>

                        </ol>

                    </div>

                </div>

            </div>

        </div>

        <!-- Main Content -->
        <div class="content">

            <div class="container-fluid">

                <div class="row">

                    <div class="col-12">

                        <div class="card">

                            <div class="card-header bg-<?=
                                htmlspecialchars($themeColor)
                            ?> text-white">

                                <h3 class="card-title">

                                    <i class="fas fa-route mr-1"></i>

                                    Monitoring Routing Produksi

                                </h3>

                            </div>

                            <div class="card-body">

                                <!-- ==========================
                                     FORM FILTER
                                =========================== -->

                            <form method="POST" id="searchForm">

                                    <div class="row">

                                        <div class="col-md-6">

                                            <div class="form-group">

                                                <label>

                                                    No CP

                                                </label>

                                                <textarea

                                                    name="cpno"

                                                    rows="10"

                                                    class="form-control form-control-sm"

                                                    placeholder="Satu No CP setiap baris&#10;&#10;Contoh :&#10;D21D779.01.0101&#10;D21D268.01.0101&#10;D21D555.01.0101"

                                                ><?=htmlspecialchars($cpno)?></textarea>

                                                <small class="text-muted">

                                                    Paste banyak No CP.
                                                    Spasi depan / belakang otomatis dihapus.

                                                </small>

                                            </div>

                                        </div>

                                        <div class="col-md-3">

                                            <div class="form-group"
                                                style="margin-top:32px;">

                                               <button
    type="submit"
    id="btnSearch"
    class="btn btn-<?=
        htmlspecialchars($themeColor)
    ?> btn-sm">

    <i class="fas fa-search"></i>

    Cari

</button>

                                                <a href="history_batch.php"
                                                    class="btn btn-secondary btn-sm">

                                                    Reset

                                                </a>
<button
    type="submit"
    formaction="export_history_routing_last.php"
    class="btn btn-success btn-sm">

    <i class="fas fa-file-excel"></i>
    
    Export Excel

</button>
                                            </div>

                                        </div>


                                        <div class="col-md-3">

<?php

if(!empty($listCP)){

?>

                                            <div class="alert alert-info mt-4 mb-0">

                                                Jumlah CP :

                                                <strong>

                                                    <?=count($listCP)?>

                                                </strong>

                                            </div>

<?php

}

?>

                                        </div>

                                    </div>

                                </form>

                                <hr>

                                <!-- ==========================
                                     TABLE
                                =========================== -->

                                <div class="table-responsive">

                                    <table

                                        id="batchTable"

                                        class="table table-bordered table-hover table-striped table-sm"

                                    >

                                        <thead class="thead-light">

                                            <tr class="text-center">

                                                <th width="50">

                                                    No

                                                </th>

                                                <th>

                                                    No CP

                                                </th>

                                                <th>

                                                    Result

                                                </th>

                                                <th>

                                                    Qty Pemartaian

                                                </th>

                                                <th>

                                                    Customer Color

                                                </th>

                                                <th>

                                                    Label Jual

                                                </th>

                                                <th>

                                                    Posisi Saat Ini

                                                </th>

                                                <th>

                                                    Next Routing

                                                </th>

                                            </tr>

                                        </thead>

                                        <tbody>
                                            <?php

if(count($results)>0){

    $no=1;

    foreach($results as $row){

        $resultDate='-';

        if(!empty($row['enddate'])){

            $resultDate=date(
                'd/m/Y H:i',
                strtotime($row['enddate'])
            );

        }

        $qty=0;

        if($row['prdqty']!=null){

            $qty=$row['prdqty'];

        }

        $nextRouting=$row['next_routing'];

        $badge='badge-secondary';

        if($nextRouting=='Belum ada proses routing'){

            $badge='badge-warning';

        }elseif($nextRouting=='Tidak ada routing selanjutnya'){

            $badge='badge-success';

        }else{

            $badge='badge-primary';

        }

?>

<tr>

    <td class="text-center">

        <?=$no++?>

    </td>

    <td>

        <strong>

            <?=htmlspecialchars($row['prdnmbr'])?>

        </strong>

    </td>

    <td class="text-center">

        <?=$resultDate?>

    </td>

    <td class="text-right">

        <?=number_format($qty,2)?>

    </td>

    <td>

        <?=htmlspecialchars($row['cuscolor']??'-')?>

    </td>

    <td>

        <?=htmlspecialchars($row['labeljual']??'-')?>

    </td>

    <td>

<?php

if(empty($row['posisi_saat_ini'])){

?>

<span class="badge badge-danger">

Belum Ada Routing

</span>

<?php

}else{

?>

<span class="badge badge-info">

<?=htmlspecialchars($row['posisi_saat_ini'])?>

</span>

<?php

}

?>

    </td>

    <td>

        <span class="badge <?=$badge?>">

            <?=htmlspecialchars($nextRouting)?>

        </span>

    </td>

</tr>

<?php

    }

}else{

?>

<tr>

<td colspan="8">

<div class="text-center p-5">

<i class="fas fa-search fa-3x text-secondary mb-3"></i>

<h5>

Tidak ada data ditemukan

</h5>

<p class="text-muted">

<?php

if(trim($cpno)!=""){

?>

No CP yang dicari tidak ditemukan.

<?php

}else{

?>

Silakan masukkan satu atau beberapa No CP.

<?php

}

?>

</p>

</div>

</td>

</tr>

<?php

}

?>

</tbody>

</table>

</div>

</div>

</div>

</div>

</div>

</div>

</div>

</div>

</div>
<!-- ===================================================
    FOOTER
=================================================== -->
<?php include '../../includes/footer.php'; ?>

<!-- ===================================================
    DATATABLES CSS
=================================================== -->

<link rel="stylesheet"
href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">

<link rel="stylesheet"
href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<link rel="stylesheet"
href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

<!-- ===================================================
    DATATABLES JS
=================================================== -->

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>

<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<script>
$("#searchForm").on("submit",function(){

    $("#loadingOverlay").fadeIn(200);

    $("#btnSearch")
        .prop("disabled",true)
        .html('<span class="spinner-border spinner-border-sm"></span> Memproses...');

});
$(document).ready(function(){

    $("#batchTable").DataTable({

        responsive:true,

        autoWidth:false,

        ordering:true,

        pageLength:25,

        order:[[1,"asc"]],

        language:{

            processing:"Memproses...",

            lengthMenu:"Tampilkan _MENU_ data",

            zeroRecords:"Tidak ada data ditemukan",

            info:"Menampilkan _START_ - _END_ dari _TOTAL_ data",

            infoEmpty:"Tidak ada data",

            infoFiltered:"(difilter dari _MAX_ data)",

            search:"Cari :",

            paginate:{

                first:"Pertama",

                last:"Terakhir",

                next:"Selanjutnya",

                previous:"Sebelumnya"

            }

        }

    });

});

</script>

<style>
#loadingOverlay{

    display:none;

    position:fixed;

    top:0;

    left:0;

    width:100%;

    height:100%;

    background:rgba(0,0,0,.65);

    z-index:999999;

}

.loading-content{

    position:absolute;

    top:50%;

    left:50%;

    transform:translate(-50%,-50%);

    text-align:center;

    color:#fff;

}
.card-title{

    font-weight:600;

}

.table td{

    vertical-align:middle;

}

.table thead th{

    text-align:center;

    white-space:nowrap;

    vertical-align:middle;

}

textarea{

    font-family:Consolas,monospace;

}

.badge{

    font-size:12px;

    padding:6px 10px;

}

</style>