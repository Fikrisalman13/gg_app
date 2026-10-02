<?php
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['LAST_ACTIVITY'] = time();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['UserName']) || empty($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header("Location: /gg_app/login.php");
    exit;
}

require_once '../../koneksi.php';

$themeColor = $_SESSION['Theme'] ?? 'primary';
$currentUser = trim((string) ($_SESSION['UserName'] ?? 'SYSTEM'));
$isIt1User = strtoupper($currentUser) === 'IT1';
$flashSuccess = '';
$flashError = '';

function e($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function monthOrder($monthName)
{
    static $months = [
        'Januari' => 1,
        'Februari' => 2,
        'Maret' => 3,
        'April' => 4,
        'Mei' => 5,
        'Juni' => 6,
        'Juli' => 7,
        'Agustus' => 8,
        'September' => 9,
        'Oktober' => 10,
        'November' => 11,
        'Desember' => 12
    ];

    return $months[$monthName] ?? 13;
}

$schemaQueries = [
    "
    IF NOT EXISTS (
        SELECT 1 FROM sys.objects
        WHERE object_id = OBJECT_ID(N'dbo.internet_usage_providers') AND type = N'U'
    )
    BEGIN
        CREATE TABLE dbo.internet_usage_providers (
            id INT IDENTITY(1,1) PRIMARY KEY,
            provider_name VARCHAR(150) NOT NULL UNIQUE,
            created_date DATETIME NOT NULL DEFAULT GETDATE(),
            updated_date DATETIME NOT NULL DEFAULT GETDATE(),
            created_by VARCHAR(100) NOT NULL,
            updated_by VARCHAR(100) NOT NULL
        )
    END
    ",
    "
    IF NOT EXISTS (
        SELECT 1 FROM sys.objects
        WHERE object_id = OBJECT_ID(N'dbo.internet_usage_locations') AND type = N'U'
    )
    BEGIN
        CREATE TABLE dbo.internet_usage_locations (
            id INT IDENTITY(1,1) PRIMARY KEY,
            provider_id INT NOT NULL,
            location_name VARCHAR(150) NOT NULL,
            created_date DATETIME NOT NULL DEFAULT GETDATE(),
            updated_date DATETIME NOT NULL DEFAULT GETDATE(),
            created_by VARCHAR(100) NOT NULL,
            updated_by VARCHAR(100) NOT NULL,
            CONSTRAINT FK_internet_usage_locations_provider
                FOREIGN KEY (provider_id) REFERENCES dbo.internet_usage_providers(id),
            CONSTRAINT UQ_internet_usage_locations UNIQUE (provider_id, location_name)
        )
    END
    ",
    "
    IF NOT EXISTS (
        SELECT 1 FROM sys.objects
        WHERE object_id = OBJECT_ID(N'dbo.internet_usage_reports') AND type = N'U'
    )
    BEGIN
        CREATE TABLE dbo.internet_usage_reports (
            id INT IDENTITY(1,1) PRIMARY KEY,
            provider_id INT NOT NULL,
            location_id INT NULL,
            month_name VARCHAR(20) NOT NULL,
            total_data_used_gb INT NOT NULL,
            total_usage_hours INT NOT NULL,
            average_daily_usage_gb INT NOT NULL,
            keterangan VARCHAR(255) NULL,
            created_date DATETIME NOT NULL DEFAULT GETDATE(),
            updated_date DATETIME NOT NULL DEFAULT GETDATE(),
            created_by VARCHAR(100) NOT NULL,
            updated_by VARCHAR(100) NOT NULL,
            CONSTRAINT FK_internet_usage_reports_provider
                FOREIGN KEY (provider_id) REFERENCES dbo.internet_usage_providers(id),
            CONSTRAINT FK_internet_usage_reports_location
                FOREIGN KEY (location_id) REFERENCES dbo.internet_usage_locations(id)
        )
    END
    "
];

foreach ($schemaQueries as $schemaSql) {
    $schemaStmt = sqlsrv_query($conn, $schemaSql);
    if ($schemaStmt === false) {
        die("Gagal menyiapkan tabel: " . print_r(sqlsrv_errors(), true));
    }
    sqlsrv_free_stmt($schemaStmt);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_provider') {
        $providerName = trim((string) ($_POST['provider_name'] ?? ''));

        if ($providerName === '') {
            $flashError = 'Nama provider wajib diisi.';
        } else {
            $sql = "
                INSERT INTO dbo.internet_usage_providers (
                    provider_name, created_date, updated_date, created_by, updated_by
                ) VALUES (?, GETDATE(), GETDATE(), ?, ?)
            ";
            $stmt = sqlsrv_query($conn, $sql, [$providerName, $currentUser, $currentUser]);
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $flashError = 'Gagal menambahkan provider.';
                if (!empty($errors[0]['message'])) {
                    $flashError .= ' ' . $errors[0]['message'];
                }
            } else {
                sqlsrv_free_stmt($stmt);
                $_SESSION['flash_success_laporan_internet'] = 'Provider berhasil ditambahkan.';
                header('Location: laporanpemakaianinternet.php');
                exit;
            }
        }
    }

    if ($action === 'edit_provider') {
        $providerId = (int) ($_POST['provider_id'] ?? 0);
        $providerName = trim((string) ($_POST['provider_name'] ?? ''));

        if ($providerId <= 0 || $providerName === '') {
            $flashError = 'Data provider tidak valid.';
        } else {
            $sql = "UPDATE dbo.internet_usage_providers SET provider_name = ?, updated_date = GETDATE(), updated_by = ? WHERE id = ?";
            $stmt = sqlsrv_query($conn, $sql, [$providerName, $currentUser, $providerId]);
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $flashError = 'Gagal mengubah provider.';
                if (!empty($errors[0]['message'])) {
                    $flashError .= ' ' . $errors[0]['message'];
                }
            } else {
                sqlsrv_free_stmt($stmt);
                $_SESSION['flash_success_laporan_internet'] = 'Provider berhasil diubah.';
                header('Location: laporanpemakaianinternet.php?provider_id=' . $providerId);
                exit;
            }
        }
    }

    if ($action === 'delete_provider') {
        $providerId = (int) ($_POST['provider_id'] ?? 0);
        if ($providerId <= 0) {
            $flashError = 'Provider tidak valid.';
        } else {
            $checkStmt = sqlsrv_query($conn, "SELECT COUNT(1) AS total FROM dbo.internet_usage_reports WHERE provider_id = ?", [$providerId]);
            $checkRow = $checkStmt ? sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC) : null;
            if ($checkStmt) {
                sqlsrv_free_stmt($checkStmt);
            }

            if (($checkRow['total'] ?? 0) > 0) {
                $flashError = 'Provider tidak bisa dihapus karena sudah memiliki data pemakaian.';
            } else {
                sqlsrv_query($conn, "DELETE FROM dbo.internet_usage_locations WHERE provider_id = ?", [$providerId]);
                $stmt = sqlsrv_query($conn, "DELETE FROM dbo.internet_usage_providers WHERE id = ?", [$providerId]);
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $flashError = 'Gagal menghapus provider.';
                    if (!empty($errors[0]['message'])) {
                        $flashError .= ' ' . $errors[0]['message'];
                    }
                } else {
                    sqlsrv_free_stmt($stmt);
                    $_SESSION['flash_success_laporan_internet'] = 'Provider berhasil dihapus.';
                    header('Location: laporanpemakaianinternet.php');
                    exit;
                }
            }
        }
    }

    if ($action === 'add_location') {
        $providerId = (int) ($_POST['provider_id'] ?? 0);
        $locationName = trim((string) ($_POST['location_name'] ?? ''));

        if ($providerId <= 0 || $locationName === '') {
            $flashError = 'Provider dan nama lokasi wajib diisi.';
        } else {
            $sql = "
                INSERT INTO dbo.internet_usage_locations (
                    provider_id, location_name, created_date, updated_date, created_by, updated_by
                ) VALUES (?, ?, GETDATE(), GETDATE(), ?, ?)
            ";
            $stmt = sqlsrv_query($conn, $sql, [$providerId, $locationName, $currentUser, $currentUser]);
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $flashError = 'Gagal menambahkan lokasi.';
                if (!empty($errors[0]['message'])) {
                    $flashError .= ' ' . $errors[0]['message'];
                }
            } else {
                sqlsrv_free_stmt($stmt);
                $_SESSION['flash_success_laporan_internet'] = 'Lokasi berhasil ditambahkan.';
                header('Location: laporanpemakaianinternet.php?provider_id=' . $providerId);
                exit;
            }
        }
    }

    if ($action === 'edit_location') {
        if (!$isIt1User) {
            $flashError = 'Hanya UserName IT1 yang dapat mengedit lokasi.';
        } else {
        $locationId = (int) ($_POST['location_id'] ?? 0);
        $providerId = (int) ($_POST['provider_id'] ?? 0);
        $locationName = trim((string) ($_POST['location_name'] ?? ''));

        if ($locationId <= 0 || $providerId <= 0 || $locationName === '') {
            $flashError = 'Data lokasi tidak valid.';
        } else {
            $sql = "UPDATE dbo.internet_usage_locations SET location_name = ?, updated_date = GETDATE(), updated_by = ? WHERE id = ? AND provider_id = ?";
            $stmt = sqlsrv_query($conn, $sql, [$locationName, $currentUser, $locationId, $providerId]);
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $flashError = 'Gagal mengubah lokasi.';
                if (!empty($errors[0]['message'])) {
                    $flashError .= ' ' . $errors[0]['message'];
                }
            } else {
                sqlsrv_free_stmt($stmt);
                $_SESSION['flash_success_laporan_internet'] = 'Lokasi berhasil diubah.';
                header('Location: laporanpemakaianinternet.php?provider_id=' . $providerId);
                exit;
            }
        }
        }
    }

    if ($action === 'delete_location') {
        if (!$isIt1User) {
            $flashError = 'Hanya UserName IT1 yang dapat menghapus lokasi.';
        } else {
        $locationId = (int) ($_POST['location_id'] ?? 0);
        $providerId = (int) ($_POST['provider_id'] ?? 0);
        if ($locationId <= 0 || $providerId <= 0) {
            $flashError = 'Lokasi tidak valid.';
        } else {
            $checkStmt = sqlsrv_query($conn, "SELECT COUNT(1) AS total FROM dbo.internet_usage_reports WHERE location_id = ?", [$locationId]);
            $checkRow = $checkStmt ? sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC) : null;
            if ($checkStmt) {
                sqlsrv_free_stmt($checkStmt);
            }

            if (($checkRow['total'] ?? 0) > 0) {
                $flashError = 'Lokasi tidak bisa dihapus karena sudah dipakai pada data pemakaian.';
            } else {
                $stmt = sqlsrv_query($conn, "DELETE FROM dbo.internet_usage_locations WHERE id = ? AND provider_id = ?", [$locationId, $providerId]);
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $flashError = 'Gagal menghapus lokasi.';
                    if (!empty($errors[0]['message'])) {
                        $flashError .= ' ' . $errors[0]['message'];
                    }
                } else {
                    sqlsrv_free_stmt($stmt);
                    $_SESSION['flash_success_laporan_internet'] = 'Lokasi berhasil dihapus.';
                    header('Location: laporanpemakaianinternet.php?provider_id=' . $providerId);
                    exit;
                }
            }
        }
        }
    }

    if ($action === 'add_usage') {
        $providerId = (int) ($_POST['provider_id'] ?? 0);
        $locationIdRaw = $_POST['location_id'] ?? '';
        $locationId = $locationIdRaw === '' ? null : (int) $locationIdRaw;
        $monthName = trim((string) ($_POST['month_name'] ?? ''));
        $totalDataUsedGb = (int) ($_POST['total_data_used_gb'] ?? 0);
        $totalUsageHours = (int) ($_POST['total_usage_hours'] ?? 0);
        $keterangan = trim((string) ($_POST['keterangan'] ?? ''));
        $averageDailyUsageGb = $totalUsageHours > 0 ? (int) round($totalDataUsedGb / $totalUsageHours) : 0;

        if (
            $providerId <= 0 ||
            $monthName === '' ||
            $totalDataUsedGb < 0 ||
            $totalUsageHours <= 0
        ) {
            $flashError = 'Data pemakaian belum lengkap atau tidak valid.';
        } else {
            $sql = "
                INSERT INTO dbo.internet_usage_reports (
                    provider_id, location_id, month_name, total_data_used_gb, total_usage_hours,
                    average_daily_usage_gb, keterangan, created_date, updated_date, created_by, updated_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE(), ?, ?)
            ";
            $params = [
                $providerId,
                $locationId,
                $monthName,
                $totalDataUsedGb,
                $totalUsageHours,
                $averageDailyUsageGb,
                $keterangan,
                $currentUser,
                $currentUser
            ];
            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $flashError = 'Gagal menyimpan data pemakaian.';
                if (!empty($errors[0]['message'])) {
                    $flashError .= ' ' . $errors[0]['message'];
                }
            } else {
                sqlsrv_free_stmt($stmt);
                $_SESSION['flash_success_laporan_internet'] = 'Data pemakaian berhasil disimpan.';
                header('Location: laporanpemakaianinternet.php?provider_id=' . $providerId);
                exit;
            }
        }
    }

    if ($action === 'edit_usage') {
        $usageId = (int) ($_POST['usage_id'] ?? 0);
        $providerId = (int) ($_POST['provider_id'] ?? 0);
        $locationIdRaw = $_POST['location_id'] ?? '';
        $locationId = $locationIdRaw === '' ? null : (int) $locationIdRaw;
        $monthName = trim((string) ($_POST['month_name'] ?? ''));
        $totalDataUsedGb = (int) ($_POST['total_data_used_gb'] ?? 0);
        $totalUsageHours = (int) ($_POST['total_usage_hours'] ?? 0);
        $keterangan = trim((string) ($_POST['keterangan'] ?? ''));
        $averageDailyUsageGb = $totalUsageHours > 0 ? (int) round($totalDataUsedGb / $totalUsageHours) : 0;

        if ($usageId <= 0 || $providerId <= 0 || $monthName === '' || $totalUsageHours <= 0 || $totalDataUsedGb < 0) {
            $flashError = 'Data pemakaian untuk edit tidak valid.';
        } else {
            $sql = "UPDATE dbo.internet_usage_reports
                    SET location_id = ?, month_name = ?, total_data_used_gb = ?, total_usage_hours = ?,
                        average_daily_usage_gb = ?, keterangan = ?, updated_date = GETDATE(), updated_by = ?
                    WHERE id = ? AND provider_id = ?";
            $params = [$locationId, $monthName, $totalDataUsedGb, $totalUsageHours, $averageDailyUsageGb, $keterangan, $currentUser, $usageId, $providerId];
            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $flashError = 'Gagal mengubah data pemakaian.';
                if (!empty($errors[0]['message'])) {
                    $flashError .= ' ' . $errors[0]['message'];
                }
            } else {
                sqlsrv_free_stmt($stmt);
                $_SESSION['flash_success_laporan_internet'] = 'Data pemakaian berhasil diubah.';
                header('Location: laporanpemakaianinternet.php?provider_id=' . $providerId);
                exit;
            }
        }
    }

    if ($action === 'delete_usage') {
        $usageId = (int) ($_POST['usage_id'] ?? 0);
        $providerId = (int) ($_POST['provider_id'] ?? 0);
        if ($usageId <= 0 || $providerId <= 0) {
            $flashError = 'Data pemakaian tidak valid.';
        } else {
            $stmt = sqlsrv_query($conn, "DELETE FROM dbo.internet_usage_reports WHERE id = ? AND provider_id = ?", [$usageId, $providerId]);
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $flashError = 'Gagal menghapus data pemakaian.';
                if (!empty($errors[0]['message'])) {
                    $flashError .= ' ' . $errors[0]['message'];
                }
            } else {
                sqlsrv_free_stmt($stmt);
                $_SESSION['flash_success_laporan_internet'] = 'Data pemakaian berhasil dihapus.';
                header('Location: laporanpemakaianinternet.php?provider_id=' . $providerId);
                exit;
            }
        }
    }
}

if (isset($_SESSION['flash_success_laporan_internet'])) {
    $flashSuccess = $_SESSION['flash_success_laporan_internet'];
    unset($_SESSION['flash_success_laporan_internet']);
}

$providers = [];
$locationsByProvider = [];
$usageByProvider = [];
$allLocations = [];
$allUsages = [];

$providerSql = "
    SELECT id, provider_name, created_date, updated_date, created_by, updated_by
    FROM dbo.internet_usage_providers
    ORDER BY provider_name ASC
";
$providerStmt = sqlsrv_query($conn, $providerSql);
if ($providerStmt === false) {
    die("Gagal mengambil data provider: " . print_r(sqlsrv_errors(), true));
}

while ($row = sqlsrv_fetch_array($providerStmt, SQLSRV_FETCH_ASSOC)) {
    $providers[] = $row;
}
sqlsrv_free_stmt($providerStmt);

$activeProviderId = (int) ($_GET['provider_id'] ?? 0);
if ($activeProviderId <= 0 && !empty($providers)) {
    $activeProviderId = (int) $providers[0]['id'];
}

$locationSql = "
    SELECT id, provider_id, location_name, created_date, updated_date, created_by, updated_by
    FROM dbo.internet_usage_locations
    ORDER BY location_name ASC
";
$locationStmt = sqlsrv_query($conn, $locationSql);
if ($locationStmt !== false) {
    while ($row = sqlsrv_fetch_array($locationStmt, SQLSRV_FETCH_ASSOC)) {
        $providerId = (int) $row['provider_id'];
        $allLocations[] = $row;
        if (!isset($locationsByProvider[$providerId])) {
            $locationsByProvider[$providerId] = [];
        }
        $locationsByProvider[$providerId][] = $row;
    }
    sqlsrv_free_stmt($locationStmt);
}

$usageSql = "
    SELECT
        r.id, r.provider_id, r.location_id, YEAR(r.created_date) AS usage_year, r.month_name, r.total_data_used_gb,
        r.total_usage_hours, r.average_daily_usage_gb, r.keterangan, r.created_date,
        r.updated_date, r.created_by, r.updated_by, l.location_name
    FROM dbo.internet_usage_reports r
    LEFT JOIN dbo.internet_usage_locations l ON l.id = r.location_id
    ORDER BY
        r.provider_id ASC,
        CASE r.month_name
            WHEN 'Januari' THEN 1
            WHEN 'Februari' THEN 2
            WHEN 'Maret' THEN 3
            WHEN 'April' THEN 4
            WHEN 'Mei' THEN 5
            WHEN 'Juni' THEN 6
            WHEN 'Juli' THEN 7
            WHEN 'Agustus' THEN 8
            WHEN 'September' THEN 9
            WHEN 'Oktober' THEN 10
            WHEN 'November' THEN 11
            WHEN 'Desember' THEN 12
            ELSE 13
        END ASC,
        r.id DESC
";
$usageStmt = sqlsrv_query($conn, $usageSql);
if ($usageStmt !== false) {
    while ($row = sqlsrv_fetch_array($usageStmt, SQLSRV_FETCH_ASSOC)) {
        $providerId = (int) $row['provider_id'];
        $allUsages[] = $row;
        if (!isset($usageByProvider[$providerId])) {
            $usageByProvider[$providerId] = [];
        }
        $usageByProvider[$providerId][] = $row;
    }
    sqlsrv_free_stmt($usageStmt);
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<style>
.laporan-actions {
    gap: 10px;
}
.provider-summary {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}
.mini-box {
    border: 1px dashed #ced4da;
    border-radius: 8px;
    padding: 12px;
    background: #fff;
    height: 100%;
}
.nav-tabs .nav-link {
    font-weight: 600;
}
.nav-pills .nav-link {
    font-weight: 600;
}
.action-inline {
    display: inline-flex;
    gap: 6px;
    flex-wrap: wrap;
}
.filter-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}
.table td, .table th {
    vertical-align: middle;
}
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center mb-2">
                <div class="col-md-6">
                    <h1 class="m-0">Laporan Pemakaian Internet</h1>
                </div>
                <div class="col-md-6 text-md-right text-sm-left mt-sm-2 mt-md-0">
                    <ol class="breadcrumb float-md-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item active">Laporan Pemakaian Internet</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php if ($flashSuccess !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <?= e($flashSuccess) ?>
                </div>
            <?php endif; ?>

            <?php if ($flashError !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <?= e($flashError) ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-<?= e($themeColor) ?> text-white">
                    <div class="d-flex justify-content-between align-items-center flex-wrap laporan-actions">
                        <h3 class="card-title m-0">Data Provider & Pemakaian Internet</h3>
                        <button type="button" class="btn btn-light btn-sm" data-toggle="modal" data-target="#modalAddProvider">
                            <i class="fas fa-plus"></i> Input Tambahkan Provider
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($providers)): ?>
                        <div class="text-center py-5">
                            <h5 class="mb-3">Belum ada provider</h5>
                            <p class="text-muted mb-3">Tambahkan provider terlebih dahulu untuk membuat sheet/tab provider.</p>
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalAddProvider">
                                <i class="fas fa-plus"></i> Tambah Provider
                            </button>
                        </div>
                    <?php else: ?>
                        <ul class="nav nav-tabs" id="providerTab" role="tablist">
                            <?php foreach ($providers as $provider): ?>
                                <?php $providerId = (int) $provider['id']; ?>
                                <li class="nav-item">
                                    <a
                                        class="nav-link <?= $providerId === $activeProviderId ? 'active' : '' ?>"
                                        id="provider-tab-<?= $providerId ?>"
                                        data-toggle="tab"
                                        href="#provider-pane-<?= $providerId ?>"
                                        role="tab"
                                        aria-controls="provider-pane-<?= $providerId ?>"
                                        aria-selected="<?= $providerId === $activeProviderId ? 'true' : 'false' ?>"
                                        data-provider-id="<?= $providerId ?>"
                                    >
                                        <?= e($provider['provider_name']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="tab-content pt-3" id="providerTabContent">
                            <?php foreach ($providers as $provider): ?>
                                <?php
                                $providerId = (int) $provider['id'];
                                $providerLocations = $locationsByProvider[$providerId] ?? [];
                                $providerUsages = $usageByProvider[$providerId] ?? [];
                                $locationTabs = [];

                                foreach ($providerLocations as $location) {
                                    $locationTabs[] = [
                                        'id' => (int) $location['id'],
                                        'name' => $location['location_name']
                                    ];
                                }

                                $locationTabs[] = [
                                    'id' => 0,
                                    'name' => 'Semua Lokasi'
                                ];
                                ?>
                                <div
                                    class="tab-pane fade <?= $providerId === $activeProviderId ? 'show active' : '' ?>"
                                    id="provider-pane-<?= $providerId ?>"
                                    role="tabpanel"
                                    aria-labelledby="provider-tab-<?= $providerId ?>"
                                >
                                    <div class="provider-summary">
                                        <div class="row align-items-end">
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <h4 class="mb-1"><?= e($provider['provider_name']) ?></h4>
                                                <small class="text-muted">
                                                    Dibuat oleh <?= e($provider['created_by']) ?>
                                                    pada <?= e($provider['created_date'] instanceof DateTime ? $provider['created_date']->format('d-m-Y H:i') : '') ?>
                                                </small>
                                            </div>
                                            <div class="col-md-6 text-md-right">
                                                <button
                                                    type="button"
                                                    class="btn btn-info btn-sm btn-open-location-modal"
                                                    data-provider-id="<?= $providerId ?>"
                                                    data-provider-name="<?= e($provider['provider_name']) ?>"
                                                >
                                                    <i class="fas fa-map-marker-alt"></i> Tambah Lokasi
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn btn-warning btn-sm btn-open-edit-provider-modal"
                                                    data-provider-id="<?= $providerId ?>"
                                                    data-provider-name="<?= e($provider['provider_name']) ?>"
                                                >
                                                    <i class="fas fa-edit"></i> Edit Provider
                                                </button>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Hapus provider ini? Provider yang sudah punya data pemakaian tidak bisa dihapus.');">
                                                    <input type="hidden" name="action" value="delete_provider">
                                                    <input type="hidden" name="provider_id" value="<?= $providerId ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash"></i> Hapus Provider
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="filter-card">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label class="small">Filter Tahun</label>
                                                <select class="form-control form-control-sm provider-year-filter" data-provider-id="<?= $providerId ?>">
                                                    <option value="">Semua Tahun</option>
                                                    <?php for ($year = (int) date('Y') + 1; $year >= 2020; $year--): ?>
                                                        <option value="<?= $year ?>"><?= $year ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="small">Dari Bulan</label>
                                                <select class="form-control form-control-sm provider-month-start-filter" data-provider-id="<?= $providerId ?>">
                                                    <option value="">Semua</option>
                                                    <?php foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $month): ?>
                                                        <option value="<?= e($month) ?>"><?= e($month) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="small">Sampai Bulan</label>
                                                <select class="form-control form-control-sm provider-month-end-filter" data-provider-id="<?= $providerId ?>">
                                                    <option value="">Semua</option>
                                                    <?php foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $month): ?>
                                                        <option value="<?= e($month) ?>"><?= e($month) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card card-outline card-info">
                                        <div class="card-header">
                                            <h3 class="card-title">Sheet Lokasi</h3>
                                        </div>
                                        <div class="card-body">
                                            <?php if (empty($providerLocations)): ?>
                                                <p class="text-muted mb-0">Belum ada lokasi untuk provider ini.</p>
                                            <?php else: ?>
                                                <ul class="nav nav-pills mb-3" id="location-tab-list-<?= $providerId ?>" role="tablist">
                                                    <?php foreach ($locationTabs as $locationIndex => $locationTab): ?>
                                                        <li class="nav-item mr-2 mb-2">
                                                            <a
                                                                class="nav-link <?= $locationIndex === 0 ? 'active' : '' ?>"
                                                                id="location-tab-<?= $providerId ?>-<?= $locationTab['id'] ?>"
                                                                data-toggle="pill"
                                                                href="#location-pane-<?= $providerId ?>-<?= $locationTab['id'] ?>"
                                                                role="tab"
                                                            >
                                                                <?= e($locationTab['name']) ?>
                                                            </a>
                                                            <?php if ((int) $locationTab['id'] !== 0): ?>
                                                                <?php
                                                                $locationMeta = null;
                                                                foreach ($providerLocations as $pl) {
                                                                    if ((int) $pl['id'] === (int) $locationTab['id']) {
                                                                        $locationMeta = $pl;
                                                                        break;
                                                                    }
                                                                }
                                                                ?>
                                                                <?php if ($isIt1User): ?>
                                                                    <span class="action-inline ml-2">
                                                                        <button
                                                                            type="button"
                                                                            class="btn btn-warning btn-xs btn-open-edit-location-modal"
                                                                            data-location-id="<?= (int) $locationTab['id'] ?>"
                                                                            data-provider-id="<?= $providerId ?>"
                                                                            data-provider-name="<?= e($provider['provider_name']) ?>"
                                                                            data-location-name="<?= e($locationTab['name']) ?>"
                                                                        >
                                                                            Edit
                                                                        </button>
                                                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus lokasi ini?');">
                                                                            <input type="hidden" name="action" value="delete_location">
                                                                            <input type="hidden" name="provider_id" value="<?= $providerId ?>">
                                                                            <input type="hidden" name="location_id" value="<?= (int) $locationTab['id'] ?>">
                                                                            <button type="submit" class="btn btn-danger btn-xs">Hapus</button>
                                                                        </form>
                                                                    </span>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($providerLocations)): ?>
                                        <div class="tab-content" id="location-tab-content-<?= $providerId ?>">
                                            <?php foreach ($locationTabs as $locationIndex => $locationTab): ?>
                                                <?php
                                                $filteredUsages = array_values(array_filter(
                                                    $providerUsages,
                                                    static function ($usage) use ($locationTab) {
                                                        if ((int) $locationTab['id'] === 0) {
                                                            return true;
                                                        }
                                                        return (int) ($usage['location_id'] ?? 0) === (int) $locationTab['id'];
                                                    }
                                                ));
                                                $totalData = array_sum(array_map(static function ($item) { return (int) $item['total_data_used_gb']; }, $filteredUsages));
                                                $totalHours = array_sum(array_map(static function ($item) { return (int) $item['total_usage_hours']; }, $filteredUsages));
                                                $totalAverage = array_sum(array_map(static function ($item) { return (int) $item['average_daily_usage_gb']; }, $filteredUsages));
                                                $chartMonths = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                                                $chartDataUsed = array_fill(0, 12, 0);
                                                $chartUsageHours = array_fill(0, 12, 0);
                                                $chartAverage = array_fill(0, 12, 0);
                                                foreach ($filteredUsages as $chartUsage) {
                                                    $monthIdx = monthOrder($chartUsage['month_name']) - 1;
                                                    if ($monthIdx >= 0 && $monthIdx < 12) {
                                                        $chartDataUsed[$monthIdx] += (int) $chartUsage['total_data_used_gb'];
                                                        $chartUsageHours[$monthIdx] += (int) $chartUsage['total_usage_hours'];
                                                        $chartAverage[$monthIdx] += (int) $chartUsage['average_daily_usage_gb'];
                                                    }
                                                }
                                                ?>
                                                <div
                                                    class="tab-pane fade <?= $locationIndex === 0 ? 'show active' : '' ?>"
                                                    id="location-pane-<?= $providerId ?>-<?= $locationTab['id'] ?>"
                                                    role="tabpanel"
                                                >
                                                    <div class="card card-outline card-primary">
                                                        <div class="card-header">
                                                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                                                <h3 class="card-title">Data Pemakaian <?= e($locationTab['name']) ?></h3>
                                                                <div class="action-inline">
                                                                    <a
                                                                        href="export_pdf_laporanpemakaianinternet.php?provider_id=<?= $providerId ?>&location_id=<?= (int) $locationTab['id'] ?>&mode=view"
                                                                        class="btn btn-secondary btn-sm"
                                                                        target="_blank"
                                                                    >
                                                                        <i class="fas fa-eye"></i> View PDF
                                                                    </a>
                                                                    <a
                                                                        href="export_pdf_laporanpemakaianinternet.php?provider_id=<?= $providerId ?>&location_id=<?= (int) $locationTab['id'] ?>&mode=export"
                                                                        class="btn btn-danger btn-sm"
                                                                        target="_blank"
                                                                    >
                                                                        <i class="fas fa-file-pdf"></i> Export PDF
                                                                    </a>
                                                                    <?php if ((int) $locationTab['id'] !== 0): ?>
                                                                        <button
                                                                            type="button"
                                                                            class="btn btn-success btn-sm btn-open-usage-modal"
                                                                            data-provider-id="<?= $providerId ?>"
                                                                            data-provider-name="<?= e($provider['provider_name']) ?>"
                                                                            data-location-id="<?= (int) $locationTab['id'] ?>"
                                                                            data-location-name="<?= e($locationTab['name']) ?>"
                                                                        >
                                                                            <i class="fas fa-database"></i> Input Data Pemakaian
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-body border-bottom">
                                                            <canvas
                                                                class="internet-usage-chart"
                                                                id="usage-chart-<?= $providerId ?>-<?= $locationTab['id'] ?>"
                                                                height="120"
                                                                data-labels='<?= e(json_encode($chartMonths, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
                                                                data-total-data='<?= e(json_encode($chartDataUsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
                                                                data-total-hours='<?= e(json_encode($chartUsageHours, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
                                                                data-average='<?= e(json_encode($chartAverage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
                                                            ></canvas>
                                                        </div>
                                                        <div class="card-body table-responsive">
                                                            <table class="table table-bordered table-striped table-sm">
                                                                <thead class="thead-light text-center">
                                                                    <tr>
                                                                        <th>No</th>
                                                                        <?php if ((int) $locationTab['id'] === 0): ?>
                                                                            <th>Nama Lokasi</th>
                                                                        <?php endif; ?>
                                                                        <th>Bulan</th>
                                                                        <th>Total Data Digunakan (GB)</th>
                                                                        <th>Total Waktu Penggunaan (Jam)</th>
                                                                        <th>Rata-rata Penggunaan per Hari (GB)</th>
                                                                        <th>Keterangan</th>
                                                                        <th>Aksi</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php if (empty($filteredUsages)): ?>
                                                                        <tr>
                                                                            <td colspan="<?= (int) $locationTab['id'] === 0 ? 8 : 7 ?>" class="text-center text-muted">Belum ada data pemakaian.</td>
                                                                        </tr>
                                                                    <?php else: ?>
                                                                        <?php foreach ($filteredUsages as $index => $usage): ?>
                                                                            <tr class="usage-row" data-provider-id="<?= $providerId ?>" data-year="<?= (int) $usage['usage_year'] ?>" data-month-order="<?= monthOrder($usage['month_name']) ?>" data-month-name="<?= e($usage['month_name']) ?>" data-total-data="<?= (int) $usage['total_data_used_gb'] ?>" data-total-hours="<?= (int) $usage['total_usage_hours'] ?>" data-average="<?= (int) $usage['average_daily_usage_gb'] ?>">
                                                                                <td class="text-center"><?= $index + 1 ?></td>
                                                                                <?php if ((int) $locationTab['id'] === 0): ?>
                                                                                    <td><?= e($usage['location_name'] ?: '-') ?></td>
                                                                                <?php endif; ?>
                                                                                <td><?= e($usage['month_name']) ?></td>
                                                                                <td class="text-center"><?= (int) $usage['total_data_used_gb'] ?></td>
                                                                                <td class="text-center"><?= (int) $usage['total_usage_hours'] ?></td>
                                                                                <td class="text-center"><?= (int) $usage['average_daily_usage_gb'] ?></td>
                                                                                <td><?= e($usage['keterangan']) ?></td>
                                                                                <td class="text-center">
                                                                                    <div class="action-inline justify-content-center">
                                                                                        <button
                                                                                            type="button"
                                                                                            class="btn btn-warning btn-xs btn-open-edit-usage-modal"
                                                                                            data-usage-id="<?= (int) $usage['id'] ?>"
                                                                                            data-provider-id="<?= $providerId ?>"
                                                                                            data-provider-name="<?= e($provider['provider_name']) ?>"
                                                                                            data-location-id="<?= (int) ($usage['location_id'] ?? 0) ?>"
                                                                                            data-usage-year="<?= (int) $usage['usage_year'] ?>"
                                                                                            data-month-name="<?= e($usage['month_name']) ?>"
                                                                                            data-total-data="<?= (int) $usage['total_data_used_gb'] ?>"
                                                                                            data-total-hours="<?= (int) $usage['total_usage_hours'] ?>"
                                                                                            data-keterangan="<?= e($usage['keterangan']) ?>"
                                                                                        >
                                                                                            Edit
                                                                                        </button>
                                                                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus data pemakaian ini?');">
                                                                                            <input type="hidden" name="action" value="delete_usage">
                                                                                            <input type="hidden" name="provider_id" value="<?= $providerId ?>">
                                                                                            <input type="hidden" name="usage_id" value="<?= (int) $usage['id'] ?>">
                                                                                            <button type="submit" class="btn btn-danger btn-xs">Hapus</button>
                                                                                        </form>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    <?php endif; ?>
                                                                </tbody>
                                                                <tfoot>
                                                                    <tr class="font-weight-bold text-center bg-light">
                                                                        <td colspan="<?= (int) $locationTab['id'] === 0 ? 3 : 2 ?>">Total</td>
                                                                        <td class="total-data-cell"><?= $totalData ?></td>
                                                                        <td class="total-hours-cell"><?= $totalHours ?></td>
                                                                        <td class="total-average-cell"><?= $totalAverage ?></td>
                                                                        <td colspan="2"></td>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <?php
                                        $totalData = array_sum(array_map(static function ($item) { return (int) $item['total_data_used_gb']; }, $providerUsages));
                                        $totalHours = array_sum(array_map(static function ($item) { return (int) $item['total_usage_hours']; }, $providerUsages));
                                        $totalAverage = array_sum(array_map(static function ($item) { return (int) $item['average_daily_usage_gb']; }, $providerUsages));
                                        ?>
                                        <div class="card card-outline card-primary">
                                            <div class="card-header">
                                                <h3 class="card-title">Data Pemakaian</h3>
                                            </div>
                                            <div class="card-body table-responsive">
                                                <table class="table table-bordered table-striped table-sm">
                                                    <thead class="thead-light text-center">
                                                        <tr>
                                                            <th>No</th>
                                                            <th>Nama Lokasi</th>
                                                            <th>Bulan</th>
                                                            <th>Total Data Digunakan (GB)</th>
                                                            <th>Total Waktu Penggunaan (Jam)</th>
                                                            <th>Rata-rata Penggunaan per Hari (GB)</th>
                                                            <th>Keterangan</th>
                                                            <th>Aksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (empty($providerUsages)): ?>
                                                            <tr>
                                                                <td colspan="8" class="text-center text-muted">Belum ada data pemakaian.</td>
                                                            </tr>
                                                        <?php else: ?>
                                                            <?php foreach ($providerUsages as $index => $usage): ?>
                                                                <tr class="usage-row" data-provider-id="<?= $providerId ?>" data-year="<?= (int) $usage['usage_year'] ?>" data-month-order="<?= monthOrder($usage['month_name']) ?>" data-month-name="<?= e($usage['month_name']) ?>" data-total-data="<?= (int) $usage['total_data_used_gb'] ?>" data-total-hours="<?= (int) $usage['total_usage_hours'] ?>" data-average="<?= (int) $usage['average_daily_usage_gb'] ?>">
                                                                    <td class="text-center"><?= $index + 1 ?></td>
                                                                    <td><?= e($usage['location_name'] ?: '-') ?></td>
                                                                    <td><?= e($usage['month_name']) ?></td>
                                                                    <td class="text-center"><?= (int) $usage['total_data_used_gb'] ?></td>
                                                                    <td class="text-center"><?= (int) $usage['total_usage_hours'] ?></td>
                                                                    <td class="text-center"><?= (int) $usage['average_daily_usage_gb'] ?></td>
                                                                    <td><?= e($usage['keterangan']) ?></td>
                                                                    <td class="text-center">
                                                                        <div class="action-inline justify-content-center">
                                                                            <button
                                                                                type="button"
                                                                                class="btn btn-warning btn-xs btn-open-edit-usage-modal"
                                                                                data-usage-id="<?= (int) $usage['id'] ?>"
                                                                                data-provider-id="<?= $providerId ?>"
                                                                                data-provider-name="<?= e($provider['provider_name']) ?>"
                                                                                data-location-id="<?= (int) ($usage['location_id'] ?? 0) ?>"
                                                                                data-usage-year="<?= (int) $usage['usage_year'] ?>"
                                                                                data-month-name="<?= e($usage['month_name']) ?>"
                                                                                data-total-data="<?= (int) $usage['total_data_used_gb'] ?>"
                                                                                data-total-hours="<?= (int) $usage['total_usage_hours'] ?>"
                                                                                data-keterangan="<?= e($usage['keterangan']) ?>"
                                                                            >Edit</button>
                                                                            <form method="post" class="d-inline" onsubmit="return confirm('Hapus data pemakaian ini?');">
                                                                                <input type="hidden" name="action" value="delete_usage">
                                                                                <input type="hidden" name="provider_id" value="<?= $providerId ?>">
                                                                                <input type="hidden" name="usage_id" value="<?= (int) $usage['id'] ?>">
                                                                                <button type="submit" class="btn btn-danger btn-xs">Hapus</button>
                                                                            </form>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr class="font-weight-bold text-center bg-light">
                                                            <td colspan="3">Total</td>
                                                            <td class="total-data-cell"><?= $totalData ?></td>
                                                            <td class="total-hours-cell"><?= $totalHours ?></td>
                                                            <td class="total-average-cell"><?= $totalAverage ?></td>
                                                            <td colspan="2"></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>

<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/chart.js/Chart.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/select2/js/select2.full.min.js"></script>

<div class="modal fade" id="modalAddProvider" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="laporanpemakaianinternet.php" id="formAddProvider">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Input Tambahkan Provider</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_provider">
                    <div class="form-group">
                        <label>Nama Provider</label>
                        <input type="text" name="provider_name" class="form-control" maxlength="150" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="submit" form="formAddProvider" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditProvider" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="laporanpemakaianinternet.php" id="formEditProvider">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Provider</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_provider">
                    <input type="hidden" name="provider_id" id="edit_provider_id">
                    <div class="form-group mb-0">
                        <label>Nama Provider</label>
                        <input type="text" name="provider_name" id="edit_provider_name" class="form-control" maxlength="150" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="submit" form="formEditProvider" class="btn btn-warning">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAddLocation" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="laporanpemakaianinternet.php" id="formAddLocation">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-map-marker-alt"></i> Tambah Lokasi</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_location">
                    <input type="hidden" name="provider_id" id="location_provider_id">
                    <div class="form-group">
                        <label>Provider</label>
                        <input type="text" id="location_provider_name" class="form-control" readonly>
                    </div>
                    <div class="form-group mb-0">
                        <label>Nama Lokasi</label>
                        <input type="text" name="location_name" class="form-control" maxlength="150" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="submit" form="formAddLocation" class="btn btn-info">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditLocation" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="laporanpemakaianinternet.php" id="formEditLocation">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Lokasi</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_location">
                    <input type="hidden" name="provider_id" id="edit_location_provider_id">
                    <input type="hidden" name="location_id" id="edit_location_id">
                    <div class="form-group">
                        <label>Provider</label>
                        <input type="text" id="edit_location_provider_name" class="form-control" readonly>
                    </div>
                    <div class="form-group mb-0">
                        <label>Nama Lokasi</label>
                        <input type="text" name="location_name" id="edit_location_name" class="form-control" maxlength="150" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="submit" form="formEditLocation" class="btn btn-warning">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAddUsage" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="laporanpemakaianinternet.php" id="formAddUsage">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-database"></i> Input Data Pemakaian</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_usage">
                    <input type="hidden" name="provider_id" id="usage_provider_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Provider</label>
                                <input type="text" id="usage_provider_name" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tahun</label>
                                <input type="text" id="usage_year" class="form-control" value="Otomatis dari create date" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Bulan</label>
                                <select name="month_name" id="month_name" class="form-control" required>
                                    <option value="">Pilih Bulan</option>
                                    <option value="Januari">Januari</option>
                                    <option value="Februari">Februari</option>
                                    <option value="Maret">Maret</option>
                                    <option value="April">April</option>
                                    <option value="Mei">Mei</option>
                                    <option value="Juni">Juni</option>
                                    <option value="Juli">Juli</option>
                                    <option value="Agustus">Agustus</option>
                                    <option value="September">September</option>
                                    <option value="Oktober">Oktober</option>
                                    <option value="November">November</option>
                                    <option value="Desember">Desember</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Lokasi</label>
                                <input type="hidden" name="location_id" id="usage_location_id">
                                <input type="text" id="usage_location_name" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Total Data Digunakan (GB)</label>
                                <input type="number" name="total_data_used_gb" id="total_data_used_gb" class="form-control" min="0" step="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Total Waktu Penggunaan (Jam)</label>
                                <input type="number" name="total_usage_hours" id="total_usage_hours" class="form-control" min="1" step="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Rata-rata Penggunaan per Hari (GB)</label>
                                <input type="number" id="average_daily_usage_gb" class="form-control" min="0" step="1" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label>Keterangan</label>
                        <input type="text" name="keterangan" class="form-control" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditUsage" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="laporanpemakaianinternet.php" id="formEditUsage" novalidate>
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Data Pemakaian</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_usage">
                    <input type="hidden" name="usage_id" id="edit_usage_id">
                    <input type="hidden" name="provider_id" id="edit_usage_provider_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Provider</label>
                                <input type="text" id="edit_usage_provider_name" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tahun</label>
                                <input type="text" id="edit_usage_year" class="form-control" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Bulan</label>
                                <select name="month_name" id="edit_month_name" class="form-control select2-edit-usage" required>
                                    <option value="">Pilih Bulan</option>
                                    <option value="Januari">Januari</option><option value="Februari">Februari</option><option value="Maret">Maret</option><option value="April">April</option><option value="Mei">Mei</option><option value="Juni">Juni</option><option value="Juli">Juli</option><option value="Agustus">Agustus</option><option value="September">September</option><option value="Oktober">Oktober</option><option value="November">November</option><option value="Desember">Desember</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Lokasi</label>
                                <select name="location_id" id="edit_usage_location_id" class="form-control select2-edit-usage">
                                    <option value="">Pilih Lokasi</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Total Data Digunakan (GB)</label>
                                <input type="number" name="total_data_used_gb" id="edit_total_data_used_gb" class="form-control" min="0" step="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Total Waktu Penggunaan (Jam)</label>
                                <input type="number" name="total_usage_hours" id="edit_total_usage_hours" class="form-control" min="1" step="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Rata-rata Penggunaan per Hari (GB)</label>
                                <input type="number" name="average_daily_usage_gb" id="edit_average_daily_usage_gb" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Keterangan</label>
                                <input type="text" name="keterangan" id="edit_keterangan" class="form-control" maxlength="255">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const providerLocations = <?= json_encode($locationsByProvider, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

$(function () {
    function calculateAverageUsage() {
        const totalData = parseInt($('#total_data_used_gb').val(), 10) || 0;
        const totalHours = parseInt($('#total_usage_hours').val(), 10) || 0;
        const average = totalHours > 0 ? Math.round(totalData / totalHours) : 0;
        $('#average_daily_usage_gb').val(average);
    }

    function calculateEditAverageUsage() {
        const totalData = parseInt($('#edit_total_data_used_gb').val(), 10) || 0;
        const totalHours = parseInt($('#edit_total_usage_hours').val(), 10) || 0;
        const average = totalHours > 0 ? Math.round(totalData / totalHours) : 0;
        $('#edit_average_daily_usage_gb').val(average);
    }

    function validateUsageForm(config) {
        const providerId = $.trim($(config.providerSelector).val());
        const monthName = $.trim($(config.monthSelector).val());
        const totalDataRaw = $.trim($(config.totalDataSelector).val());
        const totalHoursRaw = $.trim($(config.totalHoursSelector).val());
        const totalData = parseInt(totalDataRaw, 10);
        const totalHours = parseInt(totalHoursRaw, 10);

        if (!providerId) {
            alert('Provider belum terpilih.');
            return false;
        }

        if (!monthName) {
            alert('Silakan pilih bulan terlebih dahulu.');
            $(config.monthSelector).select2('open');
            return false;
        }

        if (totalDataRaw === '' || Number.isNaN(totalData) || totalData < 0) {
            alert('Total Data Digunakan (GB) wajib diisi dengan angka yang valid.');
            $(config.totalDataSelector).trigger('focus');
            return false;
        }

        if (totalHoursRaw === '' || Number.isNaN(totalHours) || totalHours <= 0) {
            alert('Total Waktu Penggunaan (Jam) wajib diisi lebih dari 0.');
            $(config.totalHoursSelector).trigger('focus');
            return false;
        }

        return true;
    }

    function loadLocationOptions($target, providerId, selectedValue) {
        const locations = providerLocations[String(providerId)] || providerLocations[Number(providerId)] || [];
        $target.empty().append('<option value="">Pilih Lokasi</option>');
        locations.forEach(function (item) {
            $target.append($('<option>', { value: item.id, text: item.location_name }));
        });
        $target.val(selectedValue || '').trigger('change');
    }

    function applyProviderFilters(providerId) {
        const year = $(`.provider-year-filter[data-provider-id="${providerId}"]`).val();
        const startMonth = $(`.provider-month-start-filter[data-provider-id="${providerId}"]`).val();
        const endMonth = $(`.provider-month-end-filter[data-provider-id="${providerId}"]`).val();
        const startOrder = startMonth ? monthOrderMap[startMonth] : null;
        const endOrder = endMonth ? monthOrderMap[endMonth] : null;

        $(`.usage-row[data-provider-id="${providerId}"]`).each(function () {
            const rowYear = String($(this).data('year'));
            const rowMonthOrder = parseInt($(this).data('month-order'), 10);
            const yearOk = !year || rowYear === String(year);
            const startOk = !startOrder || rowMonthOrder >= startOrder;
            const endOk = !endOrder || rowMonthOrder <= endOrder;
            $(this).toggle(yearOk && startOk && endOk);
        });

        $(`.tab-pane[id^="location-pane-${providerId}-"]`).each(function () {
            const monthlyData = new Array(12).fill(0);
            const monthlyHours = new Array(12).fill(0);
            const monthlyAverage = new Array(12).fill(0);
            let totalData = 0;
            let totalHours = 0;
            let totalAverage = 0;

            $(this).find('.usage-row:visible').each(function () {
                const monthOrder = parseInt($(this).data('month-order'), 10) || 0;
                const dataUsed = parseInt($(this).data('total-data'), 10) || 0;
                const usageHours = parseInt($(this).data('total-hours'), 10) || 0;
                const average = parseInt($(this).data('average'), 10) || 0;

                if (monthOrder >= 1 && monthOrder <= 12) {
                    monthlyData[monthOrder - 1] += dataUsed;
                    monthlyHours[monthOrder - 1] += usageHours;
                    monthlyAverage[monthOrder - 1] += average;
                }

                totalData += dataUsed;
                totalHours += usageHours;
                totalAverage += average;
            });

            $(this).find('.total-data-cell').text(totalData);
            $(this).find('.total-hours-cell').text(totalHours);
            $(this).find('.total-average-cell').text(totalAverage);

            const chartCanvas = $(this).find('.internet-usage-chart').get(0);
            if (chartCanvas && chartCanvas.chartInstance) {
                chartCanvas.chartInstance.data.datasets[0].data = monthlyData;
                chartCanvas.chartInstance.data.datasets[1].data = monthlyHours;
                chartCanvas.chartInstance.data.datasets[2].data = monthlyAverage;
                chartCanvas.chartInstance.update();
            }
        });
    }

    function refreshVisibleLocationPane(providerId) {
        const $activePane = $(`#provider-pane-${providerId} .tab-pane.active, #provider-pane-${providerId} .tab-pane.show.active`).first();
        if ($activePane.length === 0) {
            applyProviderFilters(providerId);
            return;
        }

        const chartCanvas = $activePane.find('.internet-usage-chart').get(0);
        if (chartCanvas && chartCanvas.chartInstance) {
            chartCanvas.chartInstance.resize();
            chartCanvas.chartInstance.update();
        }

        applyProviderFilters(providerId);
    }

    const monthOrderMap = {
        'Januari': 1, 'Februari': 2, 'Maret': 3, 'April': 4, 'Mei': 5, 'Juni': 6,
        'Juli': 7, 'Agustus': 8, 'September': 9, 'Oktober': 10, 'November': 11, 'Desember': 12
    };

    $('.select2-edit-usage').select2({
        theme: 'bootstrap4',
        width: '100%',
        dropdownParent: $('#modalEditUsage')
    });

    $('.btn-open-edit-provider-modal').on('click', function () {
        $('#edit_provider_id').val($(this).data('provider-id'));
        $('#edit_provider_name').val($(this).data('provider-name'));
        $('#modalEditProvider').modal('show');
    });

    $('.btn-open-location-modal').on('click', function () {
        $('#location_provider_id').val($(this).data('provider-id'));
        $('#location_provider_name').val($(this).data('provider-name'));
        $('#modalAddLocation').modal('show');
    });

    $('.btn-open-edit-location-modal').on('click', function () {
        $('#edit_location_provider_id').val($(this).data('provider-id'));
        $('#edit_location_id').val($(this).data('location-id'));
        $('#edit_location_provider_name').val($(this).data('provider-name'));
        $('#edit_location_name').val($(this).data('location-name'));
        $('#modalEditLocation').modal('show');
    });

    $('.btn-open-usage-modal').on('click', function () {
        const providerId = String($(this).data('provider-id'));
        const providerName = $(this).data('provider-name');
        const locationId = String($(this).data('location-id') || '');
        const locationName = $(this).data('location-name') || '';
        const $location = $('#usage_location_id');

        $('#usage_provider_id').val(providerId);
        $('#usage_provider_name').val(providerName);
        $('#usage_year').val(new Date().getFullYear());
        $location.val(locationId);
        $('#usage_location_name').val(locationName);

        $('#month_name').val('');
        $('#total_data_used_gb').val('');
        $('#total_usage_hours').val('');
        $('#average_daily_usage_gb').val('');
        $('#formAddUsage button[type="submit"]').prop('disabled', false).text('Save');
        $('#modalAddUsage').modal('show');
    });

    $('.btn-open-edit-usage-modal').on('click', function () {
        const providerId = $(this).data('provider-id');
        $('#edit_usage_id').val($(this).data('usage-id'));
        $('#edit_usage_provider_id').val(providerId);
        $('#edit_usage_provider_name').val($(this).data('provider-name'));
        $('#edit_usage_year').val($(this).data('usage-year'));
        $('#edit_month_name').val($(this).data('month-name')).trigger('change');
        loadLocationOptions($('#edit_usage_location_id'), providerId, String($(this).data('location-id') || ''));
        $('#edit_total_data_used_gb').val($(this).data('total-data'));
        $('#edit_total_usage_hours').val($(this).data('total-hours'));
        $('#edit_keterangan').val($(this).data('keterangan'));
        calculateEditAverageUsage();
        $('#modalEditUsage').modal('show');
    });

    $('#total_data_used_gb, #total_usage_hours').on('input', calculateAverageUsage);
    $('#edit_total_data_used_gb, #edit_total_usage_hours').on('input', calculateEditAverageUsage);

    $('#formEditUsage').on('submit', function (event) {
        $('#formEditUsage button[type="submit"]').prop('disabled', true).text('Updating...');
        if (!validateUsageForm({
            providerSelector: '#edit_usage_provider_id',
            monthSelector: '#edit_month_name',
            totalDataSelector: '#edit_total_data_used_gb',
            totalHoursSelector: '#edit_total_usage_hours'
        })) {
            $('#formEditUsage button[type="submit"]').prop('disabled', false).text('Update');
            event.preventDefault();
        }
    });
    $('.provider-year-filter, .provider-month-start-filter, .provider-month-end-filter').on('change', function () {
        applyProviderFilters($(this).data('provider-id'));
    });

    $('[id^="location-tab-list-"] a[data-toggle="pill"]').on('shown.bs.tab', function () {
        const providerId = ($(this).attr('id') || '').split('-')[2];
        if (providerId) {
            refreshVisibleLocationPane(providerId);
        }
    });

    $('.internet-usage-chart').each(function () {
        const labels = JSON.parse($(this).attr('data-labels') || '[]');
        const totalData = JSON.parse($(this).attr('data-total-data') || '[]');
        const totalHours = JSON.parse($(this).attr('data-total-hours') || '[]');
        const average = JSON.parse($(this).attr('data-average') || '[]');

        this.chartInstance = new Chart(this.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Data Digunakan (GB)',
                        backgroundColor: '#4472c4',
                        borderColor: '#4472c4',
                        borderWidth: 1,
                        data: totalData
                    },
                    {
                        label: 'Total Waktu Penggunaan (Jam)',
                        backgroundColor: '#ed7d31',
                        borderColor: '#ed7d31',
                        borderWidth: 1,
                        data: totalHours
                    },
                    {
                        label: 'Rata-rata Penggunaan per Hari (GB)',
                        backgroundColor: '#a5a5a5',
                        borderColor: '#a5a5a5',
                        borderWidth: 1,
                        data: average
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                legend: {
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: 'GRAFIK PEMAKAIAN INTERNET',
                    fontSize: 22,
                    fontColor: '#2f4f6f'
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        },
                        gridLines: {
                            color: '#d9d9d9'
                        }
                    }],
                    xAxes: [{
                        gridLines: {
                            display: false
                        }
                    }]
                }
            }
        });
    });

    $('.provider-year-filter').each(function () {
        applyProviderFilters($(this).data('provider-id'));
    });

    $('#providerTab a[data-toggle="tab"]').on('shown.bs.tab', function (event) {
        const providerId = $(event.target).data('provider-id');
        const url = new URL(window.location.href);
        url.searchParams.set('provider_id', providerId);
        window.history.replaceState({}, '', url.toString());
        refreshVisibleLocationPane(providerId);
    });

    $('#providerTab a[data-toggle="tab"].active').each(function () {
        const providerId = $(this).data('provider-id');
        refreshVisibleLocationPane(providerId);
    });
});
</script>
