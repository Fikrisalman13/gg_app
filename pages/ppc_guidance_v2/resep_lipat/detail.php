<?php
// pages/resep_lipat/detail.php
session_start();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../koneksi.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$themeColor = strtolower($themeColor);

$resephdid = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($resephdid <= 0) {
    $_SESSION['error'] = 'ID Resep tidak valid.';
    header('Location: /gg_app/pages/resep_lipat/index.php');
    exit;
}

require_once __DIR__ . '/../../../koneksi3.php';

$usedTypeMap = [
    'G' => 'gr/liter',
    'P' => '%',
    'L' => 'liter'
];

try {
    $headerSql = "SELECT
            h.resephdid,
            h.resepno,
            h.resepseq,
            h.resepdate,
            h.reseptype,
            h.processcode,
            h.resepprodcode,
            h.resepprodname,
            h.kainprodcode,
            h.kainprodname,
            h.contsgreige,
            h.gramature,
            h.lot,
            h.transnmbr,
            h.sohdid,
            h.colormsid,
            h.resepstatusid,
            h.createddate,
            h.createdby,
            c.colorcode,
            c.colorname,
            s.cuscolor,
            st.statuscode,
            st.statusdesc,
            ct.contnmbr
        FROM pdresephd h
            INNER JOIN pdcolorms c ON h.colormsid = c.colormsid
            INNER JOIN (
                SELECT colormsid, MAX(cuscolor) AS cuscolor
                FROM smprodtechdata GROUP BY colormsid
            ) s ON c.colormsid = s.colormsid
            LEFT JOIN pdresepstatus st ON h.resepstatusid = st.resepstatusid
            LEFT JOIN incontracthd ct ON h.sohdid = ct.sohdid
        WHERE h.resephdid = :id
        LIMIT 1";
    $headerStmt = $conn3->prepare($headerSql);
    $headerStmt->execute([':id' => $resephdid]);
    $header = $headerStmt->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        $_SESSION['error'] = 'Resep dengan ID ' . htmlspecialchars($resephdid, ENT_QUOTES, 'UTF-8') . ' tidak ditemukan.';
        header('Location: /gg_app/pages/resep_lipat/index.php');
        exit;
    }

    $manualStatus = '';
    $sqlQuery = "SELECT TOP 1 status_resep_lipat 
                 FROM dbo.resep_obat_v2 
                 WHERE proint_resephdid = ? 
                    OR (resep_no = ? AND resep_seq = ?)
                 ORDER BY CASE WHEN proint_resephdid = ? THEN 1 ELSE 2 END, updated_at DESC, created_at DESC";
    $sqlParams = [$resephdid, trim($header['resepno'] ?? ''), intval($header['resepseq'] ?? 0), $resephdid];
    $stmtSqlServer = sqlsrv_query($conn, $sqlQuery, $sqlParams);
    if ($stmtSqlServer && $soRow = sqlsrv_fetch_array($stmtSqlServer, SQLSRV_FETCH_ASSOC)) {
        $manualStatus = $soRow['status_resep_lipat'] !== null ? trim($soRow['status_resep_lipat']) : '';
    }
    if ($manualStatus !== '') {
        $header['manual_status_resep_lipat'] = $manualStatus;
    }

    $detailSql = "SELECT
            d.rtgseq,
            d.rtgdesc,
            d.materialseq,
            d.materialcode,
            d.materialname,
            d.qty,
            d.fgusedtype,
            r.rtgcode,
            r.rtgname
        FROM pdresepdt d
            LEFT JOIN pdrtgms r ON d.rtgmsid = r.rtgmsid
        WHERE d.resephdid = :id
        ORDER BY d.rtgseq, d.materialseq";
    $detailStmt = $conn3->prepare($detailSql);
    $detailStmt->execute([':id' => $resephdid]);
    $details = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

    $routingGroups = [];
    foreach ($details as $row) {
        $groupKey = $row['rtgseq'] . '|' . $row['rtgdesc'];
        if (!isset($routingGroups[$groupKey])) {
            $routingGroups[$groupKey] = [
                'rtgseq' => $row['rtgseq'],
                'rtgdesc' => $row['rtgdesc'],
                'rtgcode' => $row['rtgcode'] ?? '',
                'rtgname' => $row['rtgname'] ?? '',
                'materials' => []
            ];
        }
        $routingGroups[$groupKey]['materials'][] = $row;
    }
} catch (Throwable $e) {
    error_log('Detail Resep Lipat Error: ' . $e->getMessage());
    $_SESSION['error'] = 'Terjadi kesalahan saat memuat detail resep.';
    header('Location: /gg_app/pages/resep_lipat/index.php');
    exit;
}

function rl_format_date(?string $date): string
{
    if (empty($date)) return '-';
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '-';
}

function rl_format_number($value, int $decimals = 4): string
{
    if ($value === null || $value === '') return '-';
    return number_format((float)$value, $decimals, '.', ',');
}

function rl_display($value): string
{
    $v = trim((string)($value ?? ''));
    return $v === '' ? '-' : htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function rl_status_badge_html(?string $status, ?string $erpCode, ?string $erpDesc): string
{
    $statusTrim = trim((string)($status ?? ''));
    if ($statusTrim !== '') {
        $lowerStatus = strtolower($statusTrim);
        $badgeClass = 'status-default';
        if ($lowerStatus === 'master resep' || $lowerStatus === 'master') {
            $badgeClass = 'status-master-resep';
        } elseif ($lowerStatus === 'shading') {
            $badgeClass = 'status-shading';
        } elseif ($lowerStatus === 'top paddry') {
            $badgeClass = 'status-top-paddry';
        } elseif ($lowerStatus === 'top cpb') {
            $badgeClass = 'status-top-cpb';
        }
        
        $html = '<span class="status-badge ' . $badgeClass . '">' . htmlspecialchars($statusTrim, ENT_QUOTES, 'UTF-8') . '</span>';
        
        $erpCodeTrim = trim((string)($erpCode ?? ''));
        $erpDescTrim = trim((string)($erpDesc ?? ''));
        if ($erpCodeTrim !== '' || $erpDescTrim !== '') {
            $erpRef = [];
            if ($erpCodeTrim !== '') $erpRef[] = $erpCodeTrim;
            if ($erpDescTrim !== '' && $erpDescTrim !== $erpCodeTrim) $erpRef[] = $erpDescTrim;
            $html .= '<br><small class="text-muted mt-1 d-block" style="font-size:0.75rem;">ERP: ' . htmlspecialchars(implode(' - ', $erpRef), ENT_QUOTES, 'UTF-8') . '</small>';
        }
        return $html;
    }

    $erpCodeTrim = trim((string)($erpCode ?? ''));
    $erpDescTrim = trim((string)($erpDesc ?? ''));
    if ($erpCodeTrim === '' && $erpDescTrim === '') {
        return '-';
    }

    $badgeClass = 'status-default';
    if (strtolower($erpCodeTrim) === 'master') {
        return '<span class="status-master">' . htmlspecialchars($erpCodeTrim, ENT_QUOTES, 'UTF-8') . '</span>' . 
               ($erpDescTrim !== '' ? '<small class="text-muted ml-1">' . htmlspecialchars($erpDescTrim, ENT_QUOTES, 'UTF-8') . '</small>' : '');
    }
    
    return '<span class="status-badge ' . $badgeClass . '">' . htmlspecialchars($erpCodeTrim ?: $erpDescTrim, ENT_QUOTES, 'UTF-8') . '</span>' . 
           ($erpCodeTrim !== '' && $erpDescTrim !== '' ? '<small class="text-muted ml-1">' . htmlspecialchars($erpDescTrim, ENT_QUOTES, 'UTF-8') . '</small>' : '');
}

$resepDate = rl_format_date($header['resepdate'] ?? null);
$resepNo = rl_display($header['resepno'] ?? null);
$resepVer = rl_display($header['resepseq'] ?? null);
$productName = rl_display($header['resepprodname'] ?? null);
$productCode = rl_display($header['resepprodcode'] ?? null);
$statusCode = rl_display($header['statuscode'] ?? null);
$statusDesc = rl_display($header['statusdesc'] ?? null);
$greigeName = rl_display($header['kainprodname'] ?? null);
$greigeCode = rl_display($header['kainprodcode'] ?? null);
$soNo = rl_display($header['transnmbr'] ?? null);
$cpNo = rl_display($header['contnmbr'] ?? null);
$contsGreige = rl_display($header['contsgreige'] ?? null);
$gramature = rl_format_number($header['gramature'] ?? null, 2);
$lotGreige = rl_display($header['lot'] ?? null);
$colorName = rl_display($header['colorname'] ?? null);
$colorCode = rl_display($header['colorcode'] ?? null);
$processCode = rl_display($header['processcode'] ?? null);

include '../../../includes/header.php';
include '../../../includes/sidebar.php';
?>

<style>
    .detail-info-table th {
        background: #f4f6f9;
        font-weight: 600;
        text-align: left;
        vertical-align: middle;
        width: 15%;
        white-space: nowrap;
    }

    .detail-info-table td {
        vertical-align: middle;
    }

    .routing-card {
        border: 1px solid #dee2e6;
        margin-bottom: 1rem;
    }

    .routing-card .card-header {
        cursor: pointer;
        background: #f8f9fa;
        padding: .65rem 1rem;
    }

    .routing-card .card-header:hover {
        background: #e9ecef;
    }

    .routing-card .card-header .toggle-icon {
        transition: transform .2s;
    }

    .routing-card .card-header.collapsed .toggle-icon {
        transform: rotate(-90deg);
    }

    .routing-card .card-body {
        padding: 1rem;
    }

    .routing-title {
        font-weight: 600;
        color: #495057;
    }

    .routing-description {
        margin-bottom: .75rem;
    }

    .routing-description .label {
        color: #6c757d;
        margin-right: .5rem;
    }

    .routing-description .value {
        font-weight: 600;
        color: #212529;
    }

    .status-master {
        color: #dc2626;
        font-style: italic;
        font-weight: 700;
    }

    .status-badge {
        display: inline-block;
        font-size: .75rem;
        font-weight: 600;
        padding: .25rem .6rem;
        border-radius: 4px;
    }

    .status-master-resep {
        background: #28a745;
        color: #fff;
    }

    .status-shading {
        background: #ffc107;
        color: #1f2d3d;
    }

    .status-top-paddry {
        background: #17a2b8;
        color: #fff;
    }

    .status-top-cpb {
        background: #6c757d;
        color: #fff;
    }

    .status-default {
        background: #e3f2fd;
        color: #1976d2;
    }

    .empty-state {
        padding: 2rem;
        text-align: center;
        color: #6c757d;
        font-style: italic;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-layer-group mr-2"></i>Detail Resep Lipat
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="/gg_app/pages/resep_lipat/index.php">Resep Lipat</a></li>
                        <li class="breadcrumb-item active">Detail</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <div class="card mb-3">
                <div class="card-header bg-<?= htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?> text-white">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-info-circle mr-1"></i> Info Resep
                        <small class="text-white-50 ml-2">Resep No <?= $resepNo ?> Ver. <?= $resepVer ?></small>
                    </h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-sm detail-info-table mb-0">
                        <tbody>
                            <tr>
                                <th>Resep No</th>
                                <td><strong><?= $resepNo ?></strong></td>
                                <th>Date</th>
                                <td><?= $resepDate ?></td>
                            </tr>
                            <tr>
                                <th>Ver.</th>
                                <td><?= $resepVer ?></td>
                                <th>Product</th>
                                <td>
                                    <strong><?= $productName ?></strong>
                                    <?php if ($productCode !== '-'): ?>
                                        <span class="text-muted">| <?= $productCode ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <?= rl_status_badge_html($header['manual_status_resep_lipat'] ?? null, $header['statuscode'] ?? null, $header['statusdesc'] ?? null) ?>
                                </td>
                                <th>Tipe Resep</th>
                                <td><?= rl_display($header['reseptype'] ?? null) ?></td>
                            </tr>
                            <tr>
                                <th>Greige</th>
                                <td>
                                    <strong><?= $greigeName ?></strong>
                                    <?php if ($greigeCode !== '-'): ?>
                                        <span class="text-muted">| <?= $greigeCode ?></span>
                                    <?php endif; ?>
                                </td>
                                <th>SO No</th>
                                <td><?= $soNo ?></td>
                            </tr>
                            <tr>
                                <th>CP No</th>
                                <td><?= $cpNo ?></td>
                                <th>Const. Greige</th>
                                <td><?= $contsGreige ?></td>
                            </tr>
                            <tr>
                                <th>Gramature (gr/m)</th>
                                <td><?= $gramature ?></td>
                                <th>Lot Greige</th>
                                <td><?= $lotGreige ?></td>
                            </tr>
                            <tr>
                                <th>Color</th>
                                <td>
                                    <strong><?= $colorName ?></strong>
                                    <?php if ($colorCode !== '-'): ?>
                                        <span class="text-muted">| <?= $colorCode ?></span>
                                    <?php endif; ?>
                                </td>
                                <th>Process</th>
                                <td><strong><?= $processCode ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-<?= htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8'); ?> text-white">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-list mr-1"></i> Detail Resep
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($routingGroups)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox fa-2x mb-2"></i><br>
                            Tidak ada detail material untuk resep ini.
                        </div>
                    <?php else: ?>
                        <?php $groupIdx = 0; ?>
                        <?php foreach ($routingGroups as $idx => $group): ?>
                            <?php
                            $collapseId = 'routing-collapse-' . $groupIdx;
                            $headerId = 'routing-header-' . $groupIdx;
                            $titleParts = [];
                            if (!empty($group['rtgcode'])) $titleParts[] = $group['rtgcode'];
                            if (!empty($group['rtgname'])) $titleParts[] = $group['rtgname'];
                            $routingTitle = !empty($titleParts) ? implode(' - ', $titleParts) : 'Routing #' . $group['rtgseq'];
                            ?>
                            <div class="card routing-card">
                                <div class="card-header collapsed"
                                     data-toggle="collapse"
                                     data-target="#<?= htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8'); ?>"
                                     id="<?= htmlspecialchars($headerId, ENT_QUOTES, 'UTF-8'); ?>"
                                     aria-expanded="false"
                                     aria-controls="<?= htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-chevron-right toggle-icon mr-2"></i>
                                            <span class="routing-title"><?= htmlspecialchars($routingTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div>
                                            <span class="badge badge-secondary">
                                                <?= count($group['materials']); ?> material
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div id="<?= htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8'); ?>"
                                     class="collapse"
                                     aria-labelledby="<?= htmlspecialchars($headerId, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="card-body">
                                        <div class="routing-description">
                                            <span class="label">Description:</span>
                                            <span class="value"><?= rl_display($group['rtgdesc']); ?></span>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm mb-0">
                                                <thead>
                                                    <tr>
                                                        <th style="width:5%;" class="text-center">No</th>
                                                        <th style="width:25%;">Material Code</th>
                                                        <th>Material Name</th>
                                                        <th style="width:15%;" class="text-right">Qty</th>
                                                        <th style="width:15%;" class="text-center">Used Type</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($group['materials'] as $mIdx => $m): ?>
                                                        <tr>
                                                            <td class="text-center"><?= $mIdx + 1 ?></td>
                                                            <td><?= rl_display($m['materialcode']); ?></td>
                                                            <td><?= rl_display($m['materialname']); ?></td>
                                                            <td class="text-right"><?= rl_format_number($m['qty'] ?? null, 4); ?></td>
                                                            <td class="text-center">
                                                                <?php
                                                                $ut = trim((string)($m['fgusedtype'] ?? ''));
                                                                if (isset($usedTypeMap[$ut])) {
                                                                    echo htmlspecialchars($usedTypeMap[$ut], ENT_QUOTES, 'UTF-8');
                                                                } else {
                                                                    echo $ut !== '' ? htmlspecialchars($ut, ENT_QUOTES, 'UTF-8') : '-';
                                                                }
                                                                ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php $groupIdx++; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="mt-3">
                        <a href="/gg_app/pages/resep_lipat/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali ke List
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include '../../../includes/footer.php'; ?>
