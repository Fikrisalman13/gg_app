<?php
// ======================================================
// master_dokumen.php — Unified Document Management
// ======================================================

declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// CSS DataTables
echo '<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">';
echo '<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">';

// ------------------------------------------------------
// MAIN PAGE ACCESS CONTROL
// ------------------------------------------------------
// ID Master Dokumen
requireView($conn, 1283);

$theme = $_SESSION['Theme'] ?? 'primary';
$username = $_SESSION['UserName'] ?? null;

// ------------------------------------------------------
// TRUSTEE ACCESS CONTROL & SUPER ADMIN CHECK
// ------------------------------------------------------
$isSuperAdmin = false;
$permTrustee = userPermissions($conn, 1286); // 1286 = Menu Manage Trustees
if ($permTrustee['CanView'] == 1) {
    $isSuperAdmin = true;
}

// Fetch user's assigned trustees if not super admin
$myTrustees = [];
if (!$isSuperAdmin && $username) {
    $stmtT = sqlsrv_query($conn, "SELECT kategori_tipe, category_id, can_view, can_edit, can_delete FROM dr_trustees WHERE username = ?", [$username]);
    if ($stmtT) {
        while ($rt = sqlsrv_fetch_array($stmtT, SQLSRV_FETCH_ASSOC)) {
            $key = $rt['kategori_tipe'] === 'dynamic' ? 'dynamic_' . $rt['category_id'] : $rt['kategori_tipe'];
            $myTrustees[$key] = [
                'CanView' => $rt['can_view'],
                'CanEdit' => $rt['can_edit'],
                'CanDelete' => $rt['can_delete']
            ];
        }
    }
}

// Helper function to check permission
function checkTrustee($catKey, $myTrustees, $isSuperAdmin) {
    if ($isSuperAdmin) {
        return ['CanView' => 1, 'CanEdit' => 1, 'CanDelete' => 1, 'CanAdd' => 1];
    }
    $res = $myTrustees[$catKey] ?? ['CanView' => 0, 'CanEdit' => 0, 'CanDelete' => 0];
    $res['CanAdd'] = $res['CanEdit'] ?? 0;
    return $res;
}

$permKen = checkTrustee('kendaraan', $myTrustees, $isSuperAdmin);
$permSer = checkTrustee('sertifikat', $myTrustees, $isSuperAdmin);
$permKon = checkTrustee('kontrak', $myTrustees, $isSuperAdmin);

// ------------------------------------------------------
// TAB ACCESS CONTROL
// ------------------------------------------------------
$activeTab = '';
if ($permKen['CanView'] == 1) $activeTab = 'kendaraan';
elseif ($permSer['CanView'] == 1) $activeTab = 'sertifikat';
elseif ($permKon['CanView'] == 1) $activeTab = 'kontrak';

$idDeptUser = null;
$stmtDept = sqlsrv_query($conn, "SELECT b.id_bag FROM dbo.SMUserMs u JOIN dbo.m_emp e ON u.EmpId = e.id_emp JOIN dbo.m_bag b ON e.id_bag = b.id_bag WHERE u.UserName = ?", [$username]);
if ($stmtDept && $rd = sqlsrv_fetch_array($stmtDept, SQLSRV_FETCH_ASSOC)) {
    $idDeptUser = $rd['id_bag'];
}

$bagianList = [];
if ($username) {
    $stmtBag = sqlsrv_query($conn, "SELECT b.id_bag, b.bagian FROM dbo.SMUserMs u JOIN dbo.m_emp e ON u.EmpId = e.id_emp JOIN dbo.m_bag b ON e.id_bag = b.id_bag WHERE u.UserName = ?", [$username]);
    while ($rb = sqlsrv_fetch_array($stmtBag, SQLSRV_FETCH_ASSOC)) {
        $bagianList[] = $rb;
    }
}

// ------------------------------------------------------
// FETCH DYNAMIC CATEGORIES (V2)
// ------------------------------------------------------
$dynamicCategories = [];
$stmtCats = sqlsrv_query($conn, "SELECT * FROM dr_categories ORDER BY id ASC");
if ($stmtCats) {
    while ($rc = sqlsrv_fetch_array($stmtCats, SQLSRV_FETCH_ASSOC)) {
        // Cek Hak Akses Trustee
        $dynKey = 'dynamic_' . $rc['id'];
        $permDyn = checkTrustee($dynKey, $myTrustees, $isSuperAdmin);
        
        if ($permDyn['CanView'] == 1) {
            $rc['perms'] = $permDyn;
            $dynamicCategories[] = $rc;
            
            // If activeTab is empty, we can set it to the first dynamic tab
            if ($activeTab == '') {
                $activeTab = 'dynamic_' . $rc['id'];
            }
        }
    }
}

/**
 * Helper to render icon (Supports FontAwesome and Iconify SVG)
 */
function renderIcon($icon, $class = "") {
    $icon = trim($icon);
    if (empty($icon)) return '<i class="fas fa-file ' . $class . '"></i>';
    if (strpos($icon, ':') !== false) {
        $safeIcon = str_replace(':', '_', $icon);
        $localPath = __DIR__ . "/../../includes/icons/$safeIcon.svg";
        if (file_exists($localPath)) {
            return '<span class="local-icon ' . $class . '" style="display:inline-block; width:1em; height:1em; vertical-align:middle; fill:currentColor;">' . file_get_contents($localPath) . '</span>';
        }
        return '<span class="iconify ' . $class . '" data-icon="' . htmlspecialchars($icon) . '"></span>';
    }
    return '<i class="' . htmlspecialchars($icon) . ' ' . $class . '"></i>';
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-layer-group mr-2"></i>Master Dokumen Reminder</h1>
                </div>
            </div>
        </div>
    </section>

    <style>
        .card-tabs .nav-item .nav-link:not(.active) {
            color: rgba(255, 255, 255, 0.8) !important;
        }

        .card-tabs .nav-item .nav-link:not(.active):hover {
            color: #fff !important;
        }

        .card-tabs .nav-item .nav-link {
            display: flex;
            align-items: center;
        }

        .card-tabs .nav-item .nav-link.active {
            color: #333 !important;
            font-weight: bold;
        }

        .local-icon svg {
            width: 100%; 
            height: 100%; 
            fill: currentColor; 
            display: block;
        }
    <style>
        .hover-card:hover {
            transform: translateY(-5px) !important;
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.15) !important;
        }
        .icon-box .local-icon {
            vertical-align: middle !important;
        }
        .icon-box .local-icon svg {
            width: 1em;
            height: 1em;
        }
    </style>

    <section class="content">
        <div class="container-fluid">

            <!-- SECTION: GRID KATEGORI -->
            <div id="categoryGridSection">
                <div class="row justify-content-center mb-4">
                    <div class="col-md-8">
                        <div class="input-group input-group-lg shadow-sm" style="border-radius: 50px; overflow: hidden; border: 2px solid #007bff; background-color: #fff;">
                            <input type="text" class="form-control border-0" id="globalSearchInput" placeholder="Pencarian Global : Masukan Katerori Atau Apapun Yg Berkaitan Dengan Dokumen" style="padding-left: 25px; outline: none; box-shadow: none;">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-white border-0 px-3 d-none" id="btnResetSearch" style="color: #aaa; background-color: #fff;" title="Reset Pencarian">
                                    <i class="fas fa-times-circle fa-lg"></i>
                                </button>
                                <button type="button" class="btn btn-primary border-0 px-4" id="btnGlobalSearch">
                                    <i class="fas fa-search"></i> Cari
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hasil Pencarian -->
                <div id="globalSearchResults" class="card shadow-lg d-none mb-5" style="border-radius: 15px; border-top: 4px solid #007bff;">
                    <div class="card-header bg-white border-bottom-0" style="border-radius: 15px 15px 0 0;">
                        <h3 class="card-title font-weight-bold text-primary"><i class="fas fa-search mr-2"></i>Hasil Pencarian</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" id="btnCloseSearch"><i class="fas fa-times fa-lg"></i></button>
                        </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover text-nowrap m-0" id="tableGlobalSearch">
                            <thead class="bg-light">
                                <tr>
                                    <th>Kategori</th>
                                    <th>Identitas Dokumen</th>
                                    <th>Info Tambahan</th>
                                    <th>Kadaluarsa</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="globalSearchBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Ketik kata kunci untuk mencari...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row" id="categoriesContainer">
                    <?php if ($permKen['CanView'] == 1): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4 category-card" data-target="kendaraan" data-name="Surat Kendaraan">
                        <div class="card card-outline card-info shadow-sm hover-card h-100" style="cursor: pointer; border-radius:15px; transition: all 0.2s;">
                            <div class="card-body text-center p-4">
                                <div class="icon-box bg-info text-white mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:70px;height:70px;border-radius:20px;font-size:32px;">
                                    <i class="fas fa-car"></i>
                                </div>
                                <h5 class="font-weight-bold mb-0 text-dark">Surat Kendaraan</h5>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($permSer['CanView'] == 1): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4 category-card" data-target="sertifikat" data-name="Dokumen Sertifikat">
                        <div class="card card-outline card-primary shadow-sm hover-card h-100" style="cursor: pointer; border-radius:15px; transition: all 0.2s;">
                            <div class="card-body text-center p-4">
                                <div class="icon-box bg-primary text-white mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:70px;height:70px;border-radius:20px;font-size:32px;">
                                    <i class="fas fa-certificate"></i>
                                </div>
                                <h5 class="font-weight-bold mb-0 text-dark">Dokumen Sertifikat</h5>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($permKon['CanView'] == 1): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4 category-card" data-target="kontrak" data-name="Dokumen Kontrak">
                        <div class="card card-outline card-warning shadow-sm hover-card h-100" style="cursor: pointer; border-radius:15px; transition: all 0.2s;">
                            <div class="card-body text-center p-4">
                                <div class="icon-box bg-warning text-white mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:70px;height:70px;border-radius:20px;font-size:32px;">
                                    <i class="fas fa-file-contract"></i>
                                </div>
                                <h5 class="font-weight-bold mb-0 text-dark">Dokumen Kontrak</h5>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- DYNAMIC CATEGORIES -->
                    <?php foreach ($dynamicCategories as $dcat): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4 category-card" data-target="dynamic-<?= $dcat['id'] ?>" data-name="<?= htmlspecialchars($dcat['category_name']) ?>">
                        <div class="card card-outline card-<?= htmlspecialchars($dcat['color']) ?> shadow-sm hover-card h-100" style="cursor: pointer; border-radius:15px; transition: all 0.2s;">
                            <div class="card-body text-center p-4">
                                <div class="icon-box bg-<?= htmlspecialchars($dcat['color']) ?> text-white mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:70px;height:70px;border-radius:20px;font-size:32px;">
                                    <?= renderIcon($dcat['icon']) ?>
                                </div>
                                <h5 class="font-weight-bold mb-0 text-dark"><?= htmlspecialchars($dcat['category_name']) ?></h5>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($activeTab == '' && empty($dynamicCategories) && $permKen['CanView'] == 0 && $permSer['CanView'] == 0 && $permKon['CanView'] == 0): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-lock fa-4x text-muted mb-3"></i>
                        <h4 class="font-weight-bold">Akses Terbatas</h4>
                        <p class="text-muted">Anda tidak memiliki izin untuk melihat modul dokumen manapun.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SECTION: TABLE VIEW (Hidden by default) -->
            <div id="tableViewSection" class="d-none">
                <div class="card shadow-lg" style="border-radius: 15px;">
                    <div class="card-header bg-<?= htmlspecialchars($theme) ?>" style="border-radius: 15px 15px 0 0; padding: 1rem 1.25rem;">
                        <h3 class="card-title font-weight-bold text-white mt-1" id="tableCategoryTitle" style="font-size: 1.2rem;">
                            <i class="fas fa-layer-group mr-2"></i> Data Dokumen
                        </h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-light font-weight-bold rounded-pill px-3 shadow-sm" onclick="showCategoryGrid()">
                                <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Kategori
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div class="tab-content" id="masterTabsContent">

                        <!-- TAB: KENDARAAN -->
                        <?php if ($permKen['CanView'] == 1): ?>
                        <div class="tab-pane fade <?= ($activeTab == 'kendaraan') ? 'show active' : '' ?>" id="content-kendaraan" role="tabpanel">
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    <?php if ($permKen['CanAdd'] == 1): ?>
                                        <button class="btn btn-sm btn-success" data-toggle="modal"
                                            data-target="#modalTambahKen">
                                            <i class="fas fa-plus mr-1"></i> Tambah Baru
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <table id="tableKen" class="table table-bordered table-sm w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>#</th>
                                        <th>No Polisi</th>
                                        <th>Nama Kendaraan</th>
                                        <th>Pemilik</th>
                                        <th>Expire</th>
                                        <th width="80">File</th>
                                        <th width="80">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = sqlsrv_query($conn, "SELECT id, no_polisi, nama_kendaraan, nama_pemilik, expire_date, file_kendaraan FROM dr_surat_kendaraan ORDER BY expire_date ASC");
                                    $no = 1;
                                    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)):
                                        $exp = ($r['expire_date'] instanceof DateTimeInterface) ? $r['expire_date']->format('Y-m-d') : '-';
                                        ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><strong><?= htmlspecialchars($r['no_polisi']) ?></strong></td>
                                            <td><?= htmlspecialchars($r['nama_kendaraan']) ?></td>
                                            <td><?= htmlspecialchars($r['nama_pemilik']) ?></td>
                                            <td><span class="badge badge-light"><?= $exp ?></span></td>
                                            <td class="text-center">
                                                <?php 
                                                if ($r['file_kendaraan']) {
                                                    $fArr = explode(',', $r['file_kendaraan']);
                                                    foreach ($fArr as $idx => $f) {
                                                        $f = trim($f); if(!$f) continue;
                                                        $path = "kendaraan/uploads/kendaraan/".rawurlencode($f);
                                                        echo '<div class="mb-1"><a href="javascript:void(0)" onclick="openPreview(\''.$path.'\')" class="text-primary d-block" style="text-decoration:none;">
                                                                <i class="fas fa-eye fa-lg"></i><br><small>Lihat</small>
                                                              </a></div>';
                                                    }
                                                } else { echo '-'; }
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-xs btn-info btn-detail-ken mr-1"
                                                    data-id="<?= $r['id'] ?>" title="Lihat Detail"><i
                                                        class="fas fa-eye"></i></button>
                                                <?php if ($permKen['CanEdit'] == 1): ?>
                                                    <a href="kendaraan/edit_surat_kendaraan.php?id=<?= $r['id'] ?>"
                                                        class="btn btn-xs btn-primary mr-1" title="Edit"><i
                                                            class="fas fa-edit"></i></a>
                                                <?php endif; ?>
                                                <button class="btn btn-xs btn-success btn-renew-legacy mr-1" data-id="<?= $r['id'] ?>" data-type="kendaraan" title="Perpanjang Dokumen"><i class="fas fa-sync-alt"></i></button>
                                                <?php if ($permKen['CanDelete'] == 1): ?>
                                                    <button class="btn btn-xs btn-danger btn-delete" 
                                                        data-id="<?= $r['id'] ?>" 
                                                        data-url="kendaraan/hapus_surat_kendaraan.php" 
                                                        title="Hapus"><i class="fas fa-trash"></i></button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php endif; ?>

                        <!-- TAB: SERTIFIKAT -->
                        <?php if ($permSer['CanView'] == 1): ?>
                        <div class="tab-pane fade <?= ($activeTab == 'sertifikat') ? 'show active' : '' ?>" id="content-sertifikat" role="tabpanel">
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    <?php if ($permSer['CanAdd'] == 1): ?>
                                        <button class="btn btn-sm btn-success" data-toggle="modal"
                                            data-target="#modalTambahSer">
                                            <i class="fas fa-plus mr-1"></i> Tambah Baru
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <table id="tableSer" class="table table-bordered table-sm w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Lembaga</th>
                                        <th>Nama Sertifikat</th>
                                        <th>No Sertifikat</th>
                                        <th>Expire</th>
                                        <th width="80">File</th>
                                        <th width="80">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = sqlsrv_query($conn, "SELECT id, nama_lembaga, nama_sertifikat, no_sertifikat, expire_date, file_path FROM dr_sertifikat ORDER BY expire_date ASC");
                                    $no = 1;
                                    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)):
                                        $exp = ($r['expire_date'] instanceof DateTimeInterface) ? $r['expire_date']->format('Y-m-d') : '-';
                                        ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= htmlspecialchars($r['nama_lembaga']) ?></td>
                                            <td><?= htmlspecialchars($r['nama_sertifikat']) ?></td>
                                            <td><?= htmlspecialchars($r['no_sertifikat']) ?></td>
                                            <td><span class="badge badge-light"><?= $exp ?></span></td>
                                            <td class="text-center">
                                                <?php 
                                                if ($r['file_path']) {
                                                    $fArr = explode(',', $r['file_path']);
                                                    foreach ($fArr as $idx => $f) {
                                                        $f = trim($f); if(!$f) continue;
                                                        $path = "sertifikat/uploads/sertifikat/".rawurlencode($f);
                                                        echo '<div class="mb-1"><a href="javascript:void(0)" onclick="openPreview(\''.$path.'\')" class="text-primary d-block" style="text-decoration:none;">
                                                                <i class="fas fa-eye fa-lg"></i><br><small>Lihat</small>
                                                              </a></div>';
                                                    }
                                                } else { echo '-'; }
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-xs btn-info btn-detail-ser mr-1"
                                                    data-id="<?= $r['id'] ?>" title="Lihat Detail"><i
                                                        class="fas fa-eye"></i></button>
                                                <?php if ($permSer['CanEdit'] == 1): ?>
                                                    <a href="sertifikat/edit_dokumen_sertifikat.php?id=<?= $r['id'] ?>"
                                                        class="btn btn-xs btn-primary mr-1" title="Edit"><i
                                                            class="fas fa-edit"></i></a>
                                                <?php endif; ?>
                                                <button class="btn btn-xs btn-success btn-renew-legacy mr-1" data-id="<?= $r['id'] ?>" data-type="sertifikat" title="Perpanjang Dokumen"><i class="fas fa-sync-alt"></i></button>
                                                <?php if ($permSer['CanDelete'] == 1): ?>
                                                    <button class="btn btn-xs btn-danger btn-delete" 
                                                        data-id="<?= $r['id'] ?>" 
                                                        data-url="sertifikat/hapus_dokumen_sertifikat.php" 
                                                        title="Hapus"><i class="fas fa-trash"></i></button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php endif; ?>

                        <!-- TAB: KONTRAK -->
                        <?php if ($permKon['CanView'] == 1): ?>
                        <div class="tab-pane fade <?= ($activeTab == 'kontrak') ? 'show active' : '' ?>" id="content-kontrak" role="tabpanel">
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    <?php if ($permKon['CanAdd'] == 1): ?>
                                        <button class="btn btn-sm btn-success" data-toggle="modal"
                                            data-target="#modalTambahKon">
                                            <i class="fas fa-plus mr-1"></i> Tambah Baru
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <table id="tableKon" class="table table-bordered table-sm w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Vendor</th>
                                        <th>Pekerjaan</th>
                                        <th>No Kontrak</th>
                                        <th>Expire</th>
                                        <th width="80">File</th>
                                        <th width="80">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $whereKon = ($idDeptUser && $idDeptUser != 1) ? "WHERE bagian_id = $idDeptUser" : "";
                                    $stmt = sqlsrv_query($conn, "SELECT id, nama_vendor, nama_pekerjaan, no_kontrak, expire_date, file_path FROM dr_kontrak $whereKon ORDER BY expire_date ASC");
                                    $no = 1;
                                    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)):
                                        $exp = ($r['expire_date'] instanceof DateTimeInterface) ? $r['expire_date']->format('Y-m-d') : '-';
                                        ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><strong><?= htmlspecialchars($r['nama_vendor']) ?></strong></td>
                                            <td><?= htmlspecialchars($r['nama_pekerjaan']) ?></td>
                                            <td><?= htmlspecialchars($r['no_kontrak']) ?></td>
                                            <td><span class="badge badge-light"><?= $exp ?></span></td>
                                            <td class="text-center">
                                                <?php 
                                                if ($r['file_path']) {
                                                    $fArr = explode(',', $r['file_path']);
                                                    foreach ($fArr as $idx => $f) {
                                                        $f = trim($f); if(!$f) continue;
                                                        $path = "kontrak/uploads/kontrak/".rawurlencode($f);
                                                        echo '<div class="mb-1"><a href="javascript:void(0)" onclick="openPreview(\''.$path.'\')" class="text-primary d-block" style="text-decoration:none;">
                                                                <i class="fas fa-eye fa-lg"></i><br><small>Lihat</small>
                                                              </a></div>';
                                                    }
                                                } else { echo '-'; }
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-xs btn-info btn-detail-kon mr-1"
                                                    data-id="<?= $r['id'] ?>" title="Lihat Detail"><i
                                                        class="fas fa-eye"></i></button>
                                                <?php if ($permKon['CanEdit'] == 1): ?>
                                                    <a href="kontrak/edit_dokumen_kontrak.php?id=<?= $r['id'] ?>"
                                                        class="btn btn-xs btn-primary mr-1" title="Edit"><i
                                                            class="fas fa-edit"></i></a>
                                                <?php endif; ?>
                                                <button class="btn btn-xs btn-success btn-renew-legacy mr-1" data-id="<?= $r['id'] ?>" data-type="kontrak" title="Perpanjang Dokumen"><i class="fas fa-sync-alt"></i></button>
                                                <?php if ($permKon['CanDelete'] == 1): ?>
                                                    <button class="btn btn-xs btn-danger btn-delete" 
                                                        data-id="<?= $r['id'] ?>" 
                                                        data-url="kontrak/hapus_dokumen_kontrak.php" 
                                                        title="Hapus"><i class="fas fa-trash"></i></button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <!-- DYNAMIC TAB PANES -->
                        <?php foreach ($dynamicCategories as $dcat): ?>
                        <div class="tab-pane fade <?= ($activeTab == 'dynamic_'.$dcat['id']) ? 'show active' : '' ?>" id="content-dynamic-<?= $dcat['id'] ?>" role="tabpanel">
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    <button class="btn btn-sm btn-<?= htmlspecialchars($dcat['color']) ?>" data-toggle="modal"
                                        data-target="#modalTambahDynamic" onclick="setDynamicCategory(<?= $dcat['id'] ?>, '<?= htmlspecialchars($dcat['category_name']) ?>')">
                                        <i class="fas fa-plus mr-1"></i> Tambah <?= htmlspecialchars($dcat['category_name']) ?>
                                    </button>
                                </div>
                            </div>
                            <table id="tableDynamic<?= $dcat['id'] ?>" class="table table-bordered table-sm w-100 dynamic-table">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="text-center" width="50">#</th>
                                        <?php 
                                        // Fetch fields that should be shown on table
                                        $stmtFields = sqlsrv_query($conn, "SELECT field_label FROM dr_fields WHERE category_id = ? AND is_show_on_table = 1 ORDER BY sort_order ASC", [$dcat['id']]);
                                        if ($stmtFields) {
                                            while ($rf = sqlsrv_fetch_array($stmtFields, SQLSRV_FETCH_ASSOC)) {
                                                echo '<th>'.htmlspecialchars($rf['field_label']).'</th>';
                                            }
                                        }
                                        ?>
                                        <th width="100" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get fields to show
                                    $showFields = [];
                                    $stmtF = sqlsrv_query($conn, "SELECT id, field_name FROM dr_fields WHERE category_id = ? AND is_show_on_table = 1 ORDER BY sort_order ASC", [$dcat['id']]);
                                    if ($stmtF) {
                                        while ($rF = sqlsrv_fetch_array($stmtF, SQLSRV_FETCH_ASSOC)) {
                                            $showFields[] = $rF;
                                        }
                                    }

                                    // Get Documents
                                    $stmtDocs = sqlsrv_query($conn, "SELECT d.id, d.expire_date, d.status, d.email_reminder, d.no_whatsapp, b.bagian AS nama_bagian FROM dr_documents d LEFT JOIN dbo.m_bag b ON d.bagian_id = b.id_bag WHERE d.category_id = ? ORDER BY d.expire_date ASC", [$dcat['id']]);
                                    if ($stmtDocs) {
                                        $no = 1;
                                        while ($rDoc = sqlsrv_fetch_array($stmtDocs, SQLSRV_FETCH_ASSOC)) {
                                            // Get Values
                                            $docValues = [];
                                            $stmtVal = sqlsrv_query($conn, "SELECT field_id, field_value FROM dr_doc_values WHERE document_id = ?", [$rDoc['id']]);
                                            if ($stmtVal) {
                                                while ($rVal = sqlsrv_fetch_array($stmtVal, SQLSRV_FETCH_ASSOC)) {
                                                    $docValues[$rVal['field_id']] = $rVal['field_value'];
                                                }
                                            }

                                            // Get ALL Files (multi-file support)
                                            $docFiles = [];
                                            $stmtFile = sqlsrv_query($conn, "SELECT file_path, file_name FROM dr_doc_files WHERE document_id = ?", [$rDoc['id']]);
                                            if ($stmtFile) {
                                                while ($rFile = sqlsrv_fetch_array($stmtFile, SQLSRV_FETCH_ASSOC)) {
                                                    $docFiles[] = $rFile;
                                                }
                                            }

                                            echo '<tr>';
                                            echo '<td class="text-center align-middle">'.$no++.'</td>';
                                            
                                            // Render Dynamic Columns
                                            foreach ($showFields as $f) {
                                                if ($f['field_name'] === 'system_expire_date') {
                                                    $expDate = $rDoc['expire_date'] ? $rDoc['expire_date']->format('d/m/Y') : '-';
                                                    $badgeClass = 'success';
                                                    if ($rDoc['status'] === 'Expired') $badgeClass = 'danger';
                                                    elseif ($rDoc['status'] === 'Reminder') $badgeClass = 'warning';
                                                    echo '<td class="align-middle"><span class="badge badge-'.$badgeClass.' px-2 py-1" style="font-size:12px;">'.$expDate.'</span></td>';
                                                } elseif ($f['field_name'] === 'system_file_dokumen') {
                                                    echo '<td class="text-center align-middle">';
                                                    if (!empty($docFiles)) {
                                                        foreach ($docFiles as $df) {
                                                            echo '<div class="mb-1"><a href="javascript:void(0)" onclick="openPreview(\'' . htmlspecialchars($df['file_path']) . '\')" class="text-primary d-block" style="text-decoration:none;" title="'.htmlspecialchars($df['file_name']).'">';
                                                            echo '<i class="fas fa-eye fa-lg"></i><br><small>Lihat</small>';
                                                            echo '</a></div>';
                                                        }
                                                    } else {
                                                        echo '-';
                                                    }
                                                    echo '</td>';
                                                } elseif ($f['field_name'] === 'system_email_reminder') {
                                                    $val = $rDoc['email_reminder'] ?: '-';
                                                    echo '<td class="align-middle">'.htmlspecialchars($val).'</td>';
                                                } elseif ($f['field_name'] === 'system_no_whatsapp') {
                                                    $val = $rDoc['no_whatsapp'] ?: '-';
                                                    echo '<td class="align-middle">'.htmlspecialchars($val).'</td>';
                                                } elseif ($f['field_name'] === 'system_bagian') {
                                                    $val = $rDoc['nama_bagian'] ?: '-';
                                                    echo '<td class="align-middle">'.htmlspecialchars($val).'</td>';
                                                } else {
                                                    $val = $docValues[$f['id']] ?? '-';
                                                    echo '<td class="align-middle">'.htmlspecialchars($val).'</td>';
                                                }
                                            }

                                            // Aksi (Detail + Edit + Hapus + Perpanjang)
                                            echo '<td class="text-center align-middle" style="white-space: nowrap;">';
                                            echo '<button class="btn btn-xs btn-info btn-detail-dynamic mr-1" data-id="'.$rDoc['id'].'" title="Lihat Detail"><i class="fas fa-eye"></i></button>';
                                            if ($dcat['perms']['CanEdit'] == 1) {
                                                echo '<button class="btn btn-xs btn-primary btn-edit-dynamic mr-1" data-id="'.$rDoc['id'].'" title="Edit"><i class="fas fa-edit"></i></button>';
                                                echo '<button class="btn btn-xs btn-success btn-renew-dynamic mr-1" data-id="'.$rDoc['id'].'" title="Perpanjang Dokumen"><i class="fas fa-sync-alt"></i></button>';
                                            }
                                            if ($dcat['perms']['CanDelete'] == 1) {
                                                echo '<button class="btn btn-xs btn-danger btn-delete-dynamic" data-id="'.$rDoc['id'].'" title="Hapus"><i class="fas fa-trash"></i></button>';
                                            }
                                            echo '</td>';
                                            echo '</tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endforeach; ?>

                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- MODALS -->
<?php include __DIR__ . '\kendaraan\modal_tambah.php'; ?>
<?php include __DIR__ . '\sertifikat\modal_tambah.php'; ?>
<?php include __DIR__ . '\kontrak\modal_tambah.php'; ?>
<?php include __DIR__ . '\modal_tambah_dynamic.php'; ?>

<!-- RENEWAL MODAL -->
<div class="modal fade" id="modalRenewDocument" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formRenewDocument" enctype="multipart/form-data">
                <div class="modal-header bg-<?= htmlspecialchars($theme) ?>">
                    <h5 class="modal-title text-white"><i class="fas fa-sync-alt mr-1"></i> Perpanjang Dokumen</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="renew_document">
                    <input type="hidden" name="document_type" id="renew_document_type" value="dynamic">
                    <input type="hidden" name="doc_id" id="renew_doc_id" value="">
                    
                    <div class="form-group">
                        <label class="font-weight-bold text-danger">Tanggal Expire Baru <span class="text-danger">*</span></label>
                        <input type="date" name="new_expire_date" id="renew_expire_date" class="form-control border-danger" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="font-weight-bold">Upload File Baru (Opsional)</label>
                        <input type="file" name="new_file[]" multiple class="form-control-file" accept="application/pdf,image/jpeg,image/png">
                        <small class="text-muted">Format yang didukung: PDF, JPG, PNG. Kosongkan jika tidak ada pembaruan dokumen fisik.</small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Catatan Perpanjangan (Opsional)</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Contoh: Diperpanjang untuk periode 2026-2027"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSaveRenew"><i class="fas fa-save mr-1"></i> Simpan Perpanjangan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- DETAIL COMMON -->
<div class="modal fade" id="modalDetailCommon" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-<?= htmlspecialchars($theme) ?>">
                <h5 class="modal-title text-white"><i class="fas fa-info-circle mr-1"></i> Detail Informasi</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="detailBodyCommon">
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-3x text-<?= htmlspecialchars($theme) ?>"></i>
                    <p class="mt-2 text-muted">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>

<!-- DataTables & Scripts -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<!-- Modal Preview -->
<div class="modal fade" id="modalPreview" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 90%;">
        <div class="modal-content shadow-lg" style="border: none; border-radius: 8px; overflow: hidden;">
            <div class="modal-body p-0" style="height: 90vh; background-color: #525659;">
                <iframe id="previewIframe" src="" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
    function openPreview(path) {
        const viewerUrl = 'pdf_viewer.php?file=' + encodeURIComponent(path);
        $('#previewIframe').attr('src', viewerUrl);
        $('#modalPreview').modal('show');
    }

    $('#modalPreview').on('hidden.bs.modal', function () {
        $('#previewIframe').attr('src', '');
    });

    $(function () {
        // Config DataTables
        const dtConfig = {
            responsive: true,
            autoWidth: false,
            language: {
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                paginate: { first: "Awal", last: "Akhir", next: "→", previous: "←" }
            }
        };

        $('#tableKen').DataTable(dtConfig);
        $('#tableSer').DataTable(dtConfig);
        $('#tableKon').DataTable(dtConfig);
        $('.dynamic-table').DataTable(dtConfig); // Inisialisasi tabel dinamis V2

        // Ajax Submit Helper
        function initAjaxForm(formId, url) {
            $(formId).on('submit', function (e) {
                e.preventDefault();
                const fd = new FormData(this);
                const btn = $(this).find('button[type="submit"]');
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: fd,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message }).then(() => location.reload());
                        } else {
                            Swal.fire({ icon: 'error', title: 'Gagal!', text: res.message });
                            btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
                        }
                    },
                    error: function () {
                        Swal.fire({ icon: 'error', title: 'Error!', text: 'Terjadi kesalahan sistem.' });
                        btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
                    }
                });
            });
        }

        initAjaxForm('#formTambahKen', 'kendaraan/ajax_tambah.php');
        initAjaxForm('#formTambahSer', 'sertifikat/ajax_tambah.php');
        initAjaxForm('#formTambahKon', 'kontrak/ajax_tambah.php');
        initAjaxForm('#formTambahDynamic', 'ajax_handler.php');

        // Ajax Detail Helper
        function initDetailBtn(btnClass, url) {
            $(document).on('click', btnClass, function () {
                const id = $(this).data('id');
                $('#detailBodyCommon').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x text-<?= $theme ?>"></i><p class="mt-2 text-muted">Memuat data...</p></div>');
                $('#modalDetailCommon').modal('show');
                $.get(url + '?id=' + id, function (data) {
                    $('#detailBodyCommon').html(data);
                });
            });
        }

        initDetailBtn('.btn-detail-ken', 'kendaraan/ajax_detail.php');
        initDetailBtn('.btn-detail-ser', 'sertifikat/ajax_detail.php');
        initDetailBtn('.btn-detail-kon', 'kontrak/ajax_detail.php');

        // Ajax Delete Handler
        $(document).on('click', '.btn-delete', function () {
            const id = $(this).data('id');
            const url = $(this).data('url');

            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Data yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        type: 'GET', // Biasanya file legacy menggunakan GET untuk hapus
                        data: { id: id },
                        success: function (res) {
                            // Cek apakah response mengandung kata 'berhasil' (karena legacy mungkin tidak return JSON)
                            Swal.fire('Terhapus!', 'Data berhasil dihapus.', 'success').then(() => location.reload());
                        },
                        error: function () {
                            Swal.fire('Error!', 'Terjadi kesalahan saat menghapus data.', 'error');
                        }
                    });
                }
            });
        });

        // Ajax Delete Handler (Dynamic)
        $(document).on('click', '.btn-delete-dynamic', function () {
            const id = $(this).data('id');

            Swal.fire({
                title: 'Hapus Dokumen?',
                text: "Data dan file fisik akan dihapus permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax_handler.php',
                        type: 'POST',
                        data: { action: 'delete_document', id: id },
                        dataType: 'json',
                        success: function (res) {
                            if (res.status === 'success') {
                                Swal.fire('Terhapus!', res.message, 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Gagal!', res.message, 'error');
                            }
                        },
                        error: function () {
                            Swal.fire('Error!', 'Koneksi ke server gagal.', 'error');
                        }
                    });
                }
            });
        });

        // Detail Handler (Dynamic)
        $(document).on('click', '.btn-detail-dynamic', function () {
            const id = $(this).data('id');
            $('#detailBodyCommon').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x text-<?= $theme ?>"></i><p class="mt-2 text-muted">Memuat data...</p></div>');
            $('#modalDetailCommon').modal('show');
            $.get('ajax_handler.php', { action: 'get_detail', id: id }, function (data) {
                $('#detailBodyCommon').html(data);
            });
        });

        // Edit Handler (Dynamic)
        $(document).on('click', '.btn-edit-dynamic', function () {
            const id = $(this).data('id');
            $('#dynamicModalTitle').html('<i class="fas fa-edit mr-2"></i>Edit Dokumen');
            $('#dynamicFormContainer').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-3x text-<?= $theme ?>"></i><p class="mt-2 text-muted">Memuat form...</p></div>');
            $('#modalTambahDynamic').modal('show');
            $.get('ajax_handler.php', { action: 'get_edit_form', id: id }, function (data) {
                $('#dynamicFormContainer').html(data);
                $('#formTambahDynamic').find('[name="action"]').val('update_document');
                if (!$('#formTambahDynamic').find('[name="doc_id"]').length) {
                    $('<input type="hidden" name="doc_id">').val(id).appendTo('#formTambahDynamic');
                } else {
                    $('#formTambahDynamic').find('[name="doc_id"]').val(id);
                }
            });
        });

        // ----------------------------------------------------
        // UI Navigation Logic (SPA)
        // ----------------------------------------------------
        $('.category-card').on('click', function() {
            const targetId = $(this).data('target');
            const targetName = $(this).data('name');
            
            // Hide Grid, Show Table
            $('#categoryGridSection').addClass('d-none');
            $('#tableViewSection').removeClass('d-none');
            
            // Update Title
            $('#tableCategoryTitle').html('<i class="fas fa-layer-group mr-2"></i> Data ' + targetName);
            
            // Show corresponding tab pane
            $('.tab-pane').removeClass('show active');
            $('#content-' + targetId).addClass('show active');
            
            // Force redraw DataTables to prevent styling glitches inside hidden containers
            const tableId = (targetId === 'kendaraan') ? '#tableKen' : 
                            (targetId === 'sertifikat') ? '#tableSer' : 
                            (targetId === 'kontrak') ? '#tableKon' : 
                            '#tableDynamic' + targetId.replace('dynamic-', '');
                            
            if ($.fn.DataTable.isDataTable(tableId)) {
                $(tableId).DataTable().columns.adjust().responsive.recalc();
            }
        });

        window.showCategoryGrid = function() {
            $('#tableViewSection').addClass('d-none');
            $('#categoryGridSection').removeClass('d-none');
            // Reset global search as well when going back
            $('#globalSearchResults').addClass('d-none');
            $('#globalSearchInput').val('').trigger('input');
        };

                // ----------------------------------------------------
        // REVEWAL LOGIC
        // ----------------------------------------------------
        $(document).on('click', '.btn-renew-dynamic, .btn-renew-legacy', function() {
            const id = $(this).data('id');
            const type = $(this).data('type') || 'dynamic';
            $('#renew_doc_id').val(id);
            $('#renew_document_type').val(type);
            $('#formRenewDocument')[0].reset();
            $('#modalRenewDocument').modal('show');
        });

        $('#formRenewDocument').on('submit', function(e) {
            e.preventDefault();
            const btn = $('#btnSaveRenew');
            btn.html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...').prop('disabled', true);
            
            const formData = new FormData(this);
            $.ajax({
                url: 'ajax_handler.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(res) {
                    btn.html('<i class="fas fa-save mr-1"></i> Simpan Perpanjangan').prop('disabled', false);
                    if (res.status === 'success') {
                        $('#modalRenewDocument').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: res.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function() {
                    btn.html('<i class="fas fa-save mr-1"></i> Simpan Perpanjangan').prop('disabled', false);
                    Swal.fire('Error', 'Terjadi kesalahan jaringan/server.', 'error');
                }
            });
        });

        // ----------------------------------------------------
        // Global Search Logic
        // ----------------------------------------------------
        $('#btnGlobalSearch').on('click', performGlobalSearch);
        $('#globalSearchInput').on('keypress', function(e) {
            if(e.which == 13) performGlobalSearch();
        });

        // Show/hide reset button on typing & auto-filter category cards & auto reset search results if empty
        $('#globalSearchInput').on('input', function() {
            const val = $(this).val().trim().toLowerCase();
            if (val.length > 0) {
                $('#btnResetSearch').removeClass('d-none');
                
                // Real-time filter category cards
                $('.category-card').each(function() {
                    const cardName = $(this).data('name').toLowerCase();
                    if (cardName.includes(val)) {
                        $(this).removeClass('d-none');
                    } else {
                        $(this).addClass('d-none');
                    }
                });
            } else {
                $('#btnResetSearch').addClass('d-none');
                $('#globalSearchResults').addClass('d-none');
                
                // Show all category cards if search is cleared
                $('.category-card').removeClass('d-none');
            }
        });

        // Click on reset button
        $('#btnResetSearch').on('click', function() {
            $('#globalSearchInput').val('').trigger('input');
            $('#globalSearchResults').addClass('d-none');
        });

        $('#btnCloseSearch').on('click', function() {
            $('#globalSearchResults').addClass('d-none');
            $('#globalSearchInput').val('').trigger('input');
        });

        function performGlobalSearch() {
            const q = $('#globalSearchInput').val().trim();
            if (q.length < 3) {
                Swal.fire('Info', 'Masukkan minimal 3 karakter untuk melakukan pencarian.', 'info');
                return;
            }
            
            $('#globalSearchBody').html('<tr><td colspan="5" class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i><br>Mencari dokumen di semua database...</td></tr>');
            $('#globalSearchResults').removeClass('d-none');
            
            $.ajax({
                url: 'ajax_handler.php',
                type: 'POST',
                data: { action: 'global_search', keyword: q },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        if (res.data.length > 0) {
                            let html = '';
                            res.data.forEach(item => {
                                let badgeClass = 'badge-secondary';
                                if(item.kategori.includes('Kendaraan')) badgeClass = 'badge-info';
                                if(item.kategori.includes('Sertifikat')) badgeClass = 'badge-primary';
                                if(item.kategori.includes('Kontrak')) badgeClass = 'badge-warning';
                                
                                html += `<tr>
                                    <td><span class="badge ${badgeClass} px-3 py-2" style="font-size:12px; border-radius:10px;">${item.kategori}</span></td>
                                    <td><strong>${item.identitas}</strong></td>
                                    <td>${item.info || '-'}</td>
                                    <td><span class="badge badge-light border">${item.expire || '-'}</span></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary ${item.btn_class}" data-id="${item.id}" title="Lihat Detail">
                                            <i class="fas fa-eye mr-1"></i> Detail
                                        </button>
                                    </td>
                                </tr>`;
                            });
                            $('#globalSearchBody').html(html);
                        } else {
                            $('#globalSearchBody').html('<tr><td colspan="5" class="text-center text-muted py-5"><i class="fas fa-folder-open fa-3x mb-3" style="opacity:0.3;"></i><br><h4>Pencarian Nihil</h4><p>Tidak ada dokumen yang cocok dengan kata kunci "<b>'+htmlspecialchars(q)+'</b>".</p></td></tr>');
                        }
                    } else {
                        $('#globalSearchBody').html(`<tr><td colspan="5" class="text-center text-danger py-4">Error: ${res.message}</td></tr>`);
                    }
                },
                error: function() {
                    $('#globalSearchBody').html('<tr><td colspan="5" class="text-center text-danger py-4">Terjadi kesalahan koneksi saat mencari data.</td></tr>');
                }
            });
        }
        
        // simple htmlspecialchars for js
        function htmlspecialchars(str) {
            if (typeof(str) == "string") {
                str = str.replace(/&/g, "&amp;");
                str = str.replace(/"/g, "&quot;");
                str = str.replace(/'/g, "&#039;");
                str = str.replace(/</g, "&lt;");
                str = str.replace(/>/g, "&gt;");
            }
            return str;
        }

    });
</script>
