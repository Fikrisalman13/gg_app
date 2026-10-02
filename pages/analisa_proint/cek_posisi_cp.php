<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../koneksi3.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';
if (!$conn || !$conn3) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

// Permission Check
$groupId = $_SESSION['GroupId'];
$menuId  = 75; // Sesuaikan MenuId halaman ini
$sql = "SELECT TOP 1 CanView FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$stmt = sqlsrv_query($conn, $sql, array($groupId, $menuId));
$permissions = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
sqlsrv_free_stmt($stmt);
if (isset($permissions['CanView']) && $permissions['CanView'] == 0) {
    die("Anda tidak memiliki hak untuk melihat halaman ini.");
}

// Ambil parameter dari GET
$cpno = isset($_GET['cpno']) ? trim($_GET['cpno']) : '';
$results = [];
$row = null;
$routing_finished = false;
$routing_failed = false;

// Cek apakah ada parameter pencarian
if ($cpno !== '') {
    $query = "WITH last_process AS (
        SELECT 
            productionhdid,
            MAX(rtgseq) AS current_rtgseq
        FROM pdproductionrtg
        WHERE prdqty > 0
        GROUP BY productionhdid
    ),
    next_process AS (
        SELECT
            productionhdid,
            MIN(rtgseq) AS next_rtgseq
        FROM pdproductionrtg
        WHERE COALESCE(prdqty, 0.0000) = 0
        GROUP BY productionhdid
    ),
    ordered_rtg AS (
        SELECT
            productionhdid,
            rtgseq,
            prdqty,
            LEAD(prdqty) OVER (
                PARTITION BY productionhdid
                ORDER BY rtgseq ASC
            ) AS next_prdqty
        FROM pdproductionrtg
    ),
    failed_process AS (
        SELECT
            current_step.productionhdid,
            current_step.rtgseq AS failed_from_rtgseq,
            ROW_NUMBER() OVER (
                PARTITION BY current_step.productionhdid
                ORDER BY current_step.rtgseq ASC
            ) AS rn
        FROM ordered_rtg AS current_step
        WHERE current_step.prdqty > 0
            AND COALESCE(current_step.next_prdqty, 0.0000) = 0
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
        LEFT JOIN pdproductionmat
            ON pdbonreq.productionhdid = pdproductionmat.productionhdid
        WHERE
            pdproductionmat.fgusedtype = 'G'
            AND pdproductionmat.prodstructid IN ('51385','51386','39592')
            AND pdbonreq.rtgmsid IN 
            ('555','556','559','809','838','842',
             '571','572','573','814','841','844',
             '815','848','849','850')
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
            STRING_AGG(r.rtgname, ', ') AS lokasi_paddry
        FROM pdproductionrtg a
        JOIN pdrtgms r 
            ON a.rtgmsid = r.rtgmsid
        WHERE a.rtgmsid IN ('559','809')
        GROUP BY a.productionhdid
    )
    SELECT DISTINCT
        pdproductionhd.prdnmbr AS prdnmbr,
        pdproductionhd.fgstatus AS fgstatus,
        smprodtechdata.labeljual AS label,
        smprodtechdata.cuscolor AS cuscolor,
        pdcolorms.colorcode AS colorcode,
        pdcolorms.colorname AS colorname,
        smproduct.prodname AS prodname,
        pdproductionhd.prdqty AS qty,
        CASE
            WHEN pdproductionhd.fgstatus = 'F' THEN af_rtg.rtgname
            ELSE r1.rtgname
        END AS current_routing,
        r2.rtgname AS next_routing,
        CASE
            WHEN pdproductionhd.fgstatus = 'F' THEN af.resultdesc
            ELSE a.resultdesc
        END AS resultdesc,
        k.vlot AS vlot,
        CASE
            WHEN k.jumlah_kategori = 2 THEN 'MIX'
            WHEN k.jenis_kategori = 'LAB' THEN 'LAB'
            WHEN k.jenis_kategori = 'LA' THEN 'LA'
        END AS kategori_penimbangan,
        COALESCE(p.lokasi_paddry, '-') AS lokasi_paddry
    FROM pdproductionhd
    LEFT JOIN pdcolorms
        ON pdproductionhd.colorid = pdcolorms.colormsid
    LEFT JOIN smprodtechdata
        ON pdproductionhd.prodid = smprodtechdata.prodid
    LEFT JOIN last_process AS lp
        ON pdproductionhd.productionhdid = lp.productionhdid
    LEFT JOIN next_process AS np
        ON pdproductionhd.productionhdid = np.productionhdid
    LEFT JOIN failed_process AS fp
        ON pdproductionhd.productionhdid = fp.productionhdid
        AND fp.rn = 1
    LEFT JOIN pdproductionrtg AS a
        ON a.productionhdid = lp.productionhdid
        AND a.rtgseq = lp.current_rtgseq
    LEFT JOIN pdrtgms AS r1
        ON a.rtgmsid = r1.rtgmsid
    LEFT JOIN pdproductionrtg AS af
        ON af.productionhdid = fp.productionhdid
        AND af.rtgseq = fp.failed_from_rtgseq
    LEFT JOIN pdrtgms AS af_rtg
        ON af.rtgmsid = af_rtg.rtgmsid
    LEFT JOIN pdproductionrtg AS b
        ON b.productionhdid = np.productionhdid
        AND b.rtgseq = np.next_rtgseq
    LEFT JOIN pdrtgms AS r2
        ON b.rtgmsid = r2.rtgmsid
    LEFT JOIN kategori AS k
        ON pdproductionhd.prdnmbr = k.prdnumber
    LEFT JOIN paddry AS p
        ON pdproductionhd.productionhdid = p.productionhdid
    LEFT JOIN pdbomdt
        ON pdproductionhd.bomhdid = pdbomdt.bomhdid
    LEFT JOIN smproduct
        ON pdbomdt.prodid = smproduct.prodid
    WHERE
        pdproductionhd.workcenterid = '111'
        AND pdproductionhd.prdnmbr = ?
        AND pdproductionhd.fgstatus IN ('U','V','F')
        AND pdbomdt.fgusedtype = 'A';";
    $stmt = $conn3->prepare($query);
    $stmt->execute([$cpno]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if (($row['fgstatus'] ?? '') === 'F') {
            $row['next_routing'] = $row['current_routing'];
            $routing_failed = true;
        } elseif (empty($row['next_routing'])) {
            $row['next_routing'] = $row['current_routing'];
            $routing_finished = true;
        }
    }
}
?>
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Cek Posisi CP Saat ini </h1>
                        
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item active">History CP Routing</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div class="content">
            <div class="container-fluid">               
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-<?php echo htmlspecialchars($themeColor);?> text-white">
                                <h3 class="card-title"><i class="fas fa-list mr-1"></i>
                                History CP Routing</h3>
                            </div>
                            <div class="card-body">
                                <!-- Filter Form -->
                                <form method="get" class="mb-4" id="cpSearchForm" autocomplete="off">
                                    <div class="row justify-content-center">
                                        <div class="col-md-7 col-lg-6">
                                            <div class="input-group input-group-lg mb-3 shadow-sm rounded bg-white" style="border:2px solid #38c172; background:linear-gradient(90deg,#e6fff2 60%,#f8fafc 100%);">
                                                <input type="text" id="cpno" name="cpno" class="form-control form-control-lg border-0 rounded-left" value="<?= htmlspecialchars($cpno) ?>" placeholder="Scan / Input CP Number" style="font-size:1.25em; letter-spacing:1px; background:#e6fff2; border:2px solid #38c172; font-weight:600; color:#1b5e20;" maxlength="16" autocomplete="off" autofocus>
                                                <div class="input-group-append">
                                                    <a href="?" class="btn btn-light border-0 rounded-right" title="Reset" style="background:#f5f5f5"><i class="fas fa-sync-alt"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <?php if ($cpno !== '' && !empty($row)): ?>
                                <div class="row justify-content-center">
                                    <div class="col-md-5 mb-3">
                                        <div class="card shadow-sm rounded-lg border-0 mb-3" style="background:linear-gradient(120deg,#f8fafc 80%,#e3e9f7 100%);">
                                            <div class="card-body p-3">
                                                <table class="table table-borderless table-sm mb-0">
                                                    <tr>
                                                        <th class="align-middle text-secondary" style="width:38%">No.CP</th>
                                                        <td class="align-middle font-weight-bold" style="font-size:1.18em; letter-spacing:1px; color:#fff; background:#38c172; border-radius:6px; padding:6px 14px;">
                                                            <?= htmlspecialchars($row['prdnmbr']) ?>
                                                        </td>
                                                    </tr>
                                                    <tr><th class="align-middle text-secondary">Label</th><td class="align-middle"><?= htmlspecialchars($row['label']) ?></td></tr>
                                                    <tr><th class="align-middle text-secondary"><b>Material</b></th><td class="align-middle"><?= htmlspecialchars($row['prodname']) ?></td></tr>
                                                    <tr><th class="align-middle text-secondary">Qty</th><td class="align-middle"><?= htmlspecialchars($row['qty']) ?></td></tr>
                                                    <tr><th class="align-middle text-secondary">Cust Color</th><td class="align-middle"><?= htmlspecialchars($row['cuscolor']) ?></td></tr>
                                                    <tr><th class="align-middle text-secondary">Kode Lab</th><td class="align-middle"><?= htmlspecialchars($row['colorcode']) ?></td></tr>
                                                    <tr><th class="align-middle text-secondary">Color Name</th><td class="align-middle"><?= htmlspecialchars($row['colorname']) ?></td></tr>
                                                    <tr><th class="align-middle text-secondary">Vlot</th><td class="align-middle"><?= htmlspecialchars($row['vlot'] ?? '') ?></td></tr>
                                                    <tr><th class="align-middle text-secondary">Kategori Timbang</th><td class="align-middle"><?= htmlspecialchars($row['kategori_penimbangan'] ?? '') ?></td></tr>
                                                    <tr><th class="align-middle text-secondary">Lokasi Paddry</th><td class="align-middle"><?= htmlspecialchars($row['lokasi_paddry'] ?? '-') ?></td></tr>
                                                    <?php if (!empty($routing_failed)): ?>
                                                    <tr><th class="align-middle text-secondary">Failed Reason</th><td class="align-middle"><?= htmlspecialchars($row['resultdesc'] ?? '-') ?></td></tr>
                                                    <?php endif; ?>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-5 mb-3">
                                        <div class="card shadow-sm rounded-lg border-0 mb-3" style="background:linear-gradient(120deg,#f8fafc 80%,#e3e9f7 100%);">
                                            <div class="card-body p-3">
                                                <div class="text-center d-flex flex-column align-items-center justify-content-center" style="gap:10px;">
                                                    <div class="mb-1">
                                                        <span class="text-secondary" style="font-size:1.1em;font-weight:500;">Posisi Routing saat ini</span>
                                                        
                                                    </div>
                                                    <div style="font-size:1.35em;font-weight:700; color:#fff; background:#38c172; border-radius:6px; padding:6px 32px; letter-spacing:1px; min-width:180px; display:inline-block;">
                                                        <?= htmlspecialchars($row['next_routing'] ?? '') ?>
                                                    </div>
                                                    <?php if (!empty($routing_failed)): ?>
                                                        <div style="display:inline-block;">
                                                            <span class="badge badge-pill px-4 py-2" style="font-size:1em;vertical-align:middle;background:linear-gradient(90deg,#dc3545 60%,#f28b96 100%);color:#fff;font-weight:700;box-shadow:0 2px 8px rgba(220,53,69,0.35);">Failed</span>
                                                        </div>
                                                    <?php elseif (!empty($routing_finished)): ?>
                                                        <div style="display:inline-block;">
                                                            <span class="badge badge-pill px-4 py-2" style="font-size:1em;vertical-align:middle;background:linear-gradient(90deg,#ffd700 60%,#ffe066 100%);color:#856404;font-weight:700;box-shadow:0 2px 8px #ffe06680;">Proses Routing Selesai</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div style="display:inline-block;">
                                                            <span class="badge badge-pill px-4 py-2" style="font-size:1em;vertical-align:middle;background:linear-gradient(90deg,#ffe066 60%,#fffbe6 100%);color:#856404;font-weight:700;box-shadow:0 2px 8px #ffe06680;">Yang Harus Ditembak</span>
                                                        </div>
                                                    <?php endif; ?>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php elseif ($cpno !== ''): ?>
                                    <div class="alert alert-danger text-center shadow-sm rounded">Data tidak ditemukan untuk CP: <strong><?= htmlspecialchars($cpno) ?></strong></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script>
    // Loading overlay
    var loadingHtml = '<div id="loadingOverlay" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(255,255,255,0.7);z-index:9999;display:flex;align-items:center;justify-content:center;flex-direction:column;"><img src="https://cdn-icons-png.flaticon.com/512/189/189792.png" width="64" height="64" alt="Loading..." style="animation:spin 1.2s linear infinite;"><div style="margin-top:18px;font-size:1.3em;color:#444;font-weight:600;">Memuat data, mohon menunggu...</div></div>';
    var style = document.createElement('style');
    style.innerHTML = '@keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}';
    document.head.appendChild(style);

    // Tampilkan loading saat halaman mulai load, hilangkan setelah siap
    if($('#loadingOverlay').length === 0) {
        $('body').append(loadingHtml);
    }
    window.addEventListener('DOMContentLoaded', function() {
        setTimeout(function(){ $('#loadingOverlay').fadeOut(200, function(){ $(this).remove(); }); }, 600);
    });

    $(document).ready(function() {
        // Batasi input max 16 karakter, jika lebih: clear lalu hanya karakter baru yang masuk
        let lastValue = '';
        $('#cpno').on('input', function(e) {
            let val = $(this).val();
            if(val.length > 16) {
                // Jika lebih dari 16 karakter, clear lalu hanya karakter terakhir yang masuk
                val = val.slice(-1);
                $(this).val(val);
            }
            if(val.length === 16 && lastValue.length !== 16) {
                lastValue = val;
                // Tampilkan loading overlay di tengah layar
                if($('#loadingOverlay').length === 0) {
                    $('body').append(loadingHtml);
                }
                window.location.href = window.location.pathname + '?cpno=' + encodeURIComponent(val);
            } else {
                lastValue = val;
            }
        });

        // Otomatis submit form jika tekan Enter di input CP Number (untuk scan barcode)
        $('#cpno').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                let cpVal = $('#cpno').val().trim();
                if(cpVal.length === 16) {
                    if($('#loadingOverlay').length === 0) {
                        $('body').append(loadingHtml);
                    }
                    window.location.href = window.location.pathname + '?cpno=' + encodeURIComponent(cpVal);
                }
            }
        });

        // Jika data sudah muncul, clear input box otomatis
        <?php if ($cpno !== '' && !empty($row)): ?>
        setTimeout(function(){ $('#cpno').val(''); }, 100);
        <?php endif; ?>

        // DataTable init (jika ada tabel lain)
        $("#cpRoutingTable").DataTable({
            responsive: true,
            autoWidth: false,
            language: {
                processing: "Memproses...",
                lengthMenu: "Tampilkan _MENU_ data per halaman",
                zeroRecords: "Tidak ada data ditemukan",
                info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                infoEmpty: "Tidak ada data tersedia",
                infoFiltered: "(disaring dari _MAX_ total data)",
                search: "Cari:",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Selanjutnya",
                    previous: "Sebelumnya"
                }
            },
        });
    });
</script>
