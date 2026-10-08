<?php
// pages/ppc_guidance_v2/resep_obat/migrate_from_v1.php
session_start();
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$currentUser = $_SESSION['UserName'] ?? 'Admin';

$migrationResult = null;
$dryRunResult = null;

// Function to fetch table counts
function getCounts($conn) {
    $counts = [
        'v1_header'   => 0,
        'v1_detail'   => 0,
        'v1_machines' => 0,
        'v2_header'   => 0,
        'v2_detail'   => 0,
        'v2_machines' => 0,
        'bak_header'  => 0,
        'bak_detail'  => 0,
        'bak_machines'=> 0,
    ];

    // V1
    $q = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM dbo.resep_obat");
    if ($q && $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $counts['v1_header'] = (int)$r['c'];

    $q = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM dbo.resep_obat_detail");
    if ($q && $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $counts['v1_detail'] = (int)$r['c'];

    $q = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM dbo.resep_obat_machines");
    if ($q && $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $counts['v1_machines'] = (int)$r['c'];

    // V2
    $q = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM dbo.resep_obat_v2");
    if ($q && $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $counts['v2_header'] = (int)$r['c'];

    $q = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM dbo.resep_obat_detail_v2");
    if ($q && $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $counts['v2_detail'] = (int)$r['c'];

    $q = sqlsrv_query($conn, "SELECT COUNT(*) as c FROM dbo.resep_obat_machines_v2");
    if ($q && $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $counts['v2_machines'] = (int)$r['c'];

    // Backup tables if exist
    $q = sqlsrv_query($conn, "IF OBJECT_ID('dbo.resep_obat_v2_bak', 'U') IS NOT NULL SELECT COUNT(*) as c FROM dbo.resep_obat_v2_bak ELSE SELECT 0 as c");
    if ($q && $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $counts['bak_header'] = (int)$r['c'];

    $q = sqlsrv_query($conn, "IF OBJECT_ID('dbo.resep_obat_detail_v2_bak', 'U') IS NOT NULL SELECT COUNT(*) as c FROM dbo.resep_obat_detail_v2_bak ELSE SELECT 0 as c");
    if ($q && $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $counts['bak_detail'] = (int)$r['c'];

    $q = sqlsrv_query($conn, "IF OBJECT_ID('dbo.resep_obat_machines_v2_bak', 'U') IS NOT NULL SELECT COUNT(*) as c FROM dbo.resep_obat_machines_v2_bak ELSE SELECT 0 as c");
    if ($q && $r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) $counts['bak_machines'] = (int)$r['c'];

    return $counts;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'execute_migration') {
        $startTime = microtime(true);
        $logs = [];

        if (sqlsrv_begin_transaction($conn) === false) {
            $migrationResult = [
                'ok' => false,
                'message' => 'Gagal memulai transaksi database: ' . json_encode(sqlsrv_errors())
            ];
        } else {
            try {
                // 1. Backup current V2 tables
                $logs[] = '1. Membuat backup tabel cadangan V2 (dbo.resep_obat_v2_bak, dll)...';
                $sqlBak = "
                IF OBJECT_ID('dbo.resep_obat_v2_bak', 'U') IS NOT NULL DROP TABLE dbo.resep_obat_v2_bak;
                SELECT * INTO dbo.resep_obat_v2_bak FROM dbo.resep_obat_v2;

                IF OBJECT_ID('dbo.resep_obat_detail_v2_bak', 'U') IS NOT NULL DROP TABLE dbo.resep_obat_detail_v2_bak;
                SELECT * INTO dbo.resep_obat_detail_v2_bak FROM dbo.resep_obat_detail_v2;

                IF OBJECT_ID('dbo.resep_obat_machines_v2_bak', 'U') IS NOT NULL DROP TABLE dbo.resep_obat_machines_v2_bak;
                SELECT * INTO dbo.resep_obat_machines_v2_bak FROM dbo.resep_obat_machines_v2;
                ";
                $stmtBak = sqlsrv_query($conn, $sqlBak);
                if ($stmtBak === false) {
                    throw new Exception('Gagal membuat tabel backup: ' . json_encode(sqlsrv_errors()));
                }

                // 2. Clear V2 tables
                $logs[] = '2. Mengosongkan data V2 sebelumnya...';
                if (sqlsrv_query($conn, "DELETE FROM dbo.resep_obat_machines_v2") === false) {
                    throw new Exception('Gagal mengosongkan dbo.resep_obat_machines_v2: ' . json_encode(sqlsrv_errors()));
                }
                if (sqlsrv_query($conn, "DELETE FROM dbo.resep_obat_detail_v2") === false) {
                    throw new Exception('Gagal mengosongkan dbo.resep_obat_detail_v2: ' . json_encode(sqlsrv_errors()));
                }
                if (sqlsrv_query($conn, "DELETE FROM dbo.resep_obat_v2") === false) {
                    throw new Exception('Gagal mengosongkan dbo.resep_obat_v2: ' . json_encode(sqlsrv_errors()));
                }

                // 3. Migrate Header with exact original IDs
                $logs[] = '3. Menyalin Header Resep (dbo.resep_obat -> dbo.resep_obat_v2) dengan nomor ID asli...';
                $sqlHeader = "
                SET IDENTITY_INSERT dbo.resep_obat_v2 ON;
                INSERT INTO dbo.resep_obat_v2 (
                    id, no_cp, kode_warna, lot_no, weight, plan_qty, created_at, created_by, updated_at, updated_by,
                    color_name, color_desc, kode_grey, vlot, resep_prod_code, resep_prod_name, resep_no, resep_seq,
                    resep_date, resep_type, is_manual, no_cp_resep, no_so, rtg_code, rtg_name, status_desc, cus_color,
                    speed, temperature, nilai_l, nilai_a, nilai_b, lampiran_path, lampiran_pdf_path, machine_code,
                    machine_name, temperature_ch2, lebar_kain, mesin, status_resep_lipat, proint_resephdid, source_experiment_id
                )
                SELECT 
                    id, no_cp, kode_warna, lot_no, weight, plan_qty, created_at, created_by, updated_at, updated_by,
                    color_name, color_desc, kode_grey, vlot, resep_prod_code, resep_prod_name, resep_no, resep_seq,
                    resep_date, resep_type, is_manual, no_cp_resep, no_so, rtg_code, rtg_name, status_desc, cus_color,
                    speed, temperature, nilai_l, nilai_a, nilai_b, lampiran_path, lampiran_pdf_path, machine_code,
                    machine_name, temperature_ch2, lebar_kain, mesin, status_resep_lipat, proint_resephdid, source_experiment_id
                FROM dbo.resep_obat;
                SET IDENTITY_INSERT dbo.resep_obat_v2 OFF;
                ";
                $stmtH = sqlsrv_query($conn, $sqlHeader);
                if ($stmtH === false) {
                    throw new Exception('Gagal menyalin Header Resep: ' . json_encode(sqlsrv_errors()));
                }

                // 4. Migrate Details with routing information mapped to Table 1
                $logs[] = '4. Menyalin Detail Obat (dbo.resep_obat_detail -> dbo.resep_obat_detail_v2) ke Tabel 1...';
                $sqlDetail = "
                INSERT INTO dbo.resep_obat_detail_v2 (
                    id_resep, table_index, sort_order, bonno, rtg_code, rtg_name, vlot,
                    kode, name, category, receipe, uom, cf, uom_cf, std_price, total,
                    created_at, created_by, updated_at, updated_by, price_satuan, price_source,
                    is_manual, used_type, machine_name
                )
                SELECT 
                    d.id_resep,
                    1 AS table_index,
                    ROW_NUMBER() OVER (PARTITION BY d.id_resep ORDER BY d.id ASC) AS sort_order,
                    COALESCE(NULLIF(h.no_cp_resep, ''), h.no_cp, '-') AS bonno,
                    COALESCE(h.rtg_code, '-') AS rtg_code,
                    COALESCE(h.rtg_name, '-') AS rtg_name,
                    h.vlot AS vlot,
                    COALESCE(NULLIF(LTRIM(RTRIM(legacy_master.codeprod_proint)), ''), d.kode) AS kode,
                    d.name, d.category, d.receipe, d.uom, d.cf, d.uom_cf, d.std_price, d.total,
                    d.created_at, d.created_by, d.updated_at, d.updated_by, d.price_satuan, d.price_source,
                    d.is_manual,
                    'G' AS used_type,
                    COALESCE(h.mesin, h.machine_name, '') AS machine_name
                FROM dbo.resep_obat_detail d
                INNER JOIN dbo.resep_obat h ON d.id_resep = h.id
                OUTER APPLY (
                    SELECT TOP 1 master.codeprod_proint
                    FROM dbo.resep_master_obat master
                    WHERE (master.kode_obat = d.kode OR master.codeprod_proint = d.kode)
                    ORDER BY CASE WHEN LTRIM(RTRIM(master.nama_obat)) = LTRIM(RTRIM(d.name)) THEN 0 ELSE 1 END, master.id DESC
                ) legacy_master;
                ";
                $stmtD = sqlsrv_query($conn, $sqlDetail);
                if ($stmtD === false) {
                    throw new Exception('Gagal menyalin Detail Resep: ' . json_encode(sqlsrv_errors()));
                }

                // 5. Migrate Machines
                $logs[] = '5. Menyalin Data Mesin (dbo.resep_obat_machines -> dbo.resep_obat_machines_v2)...';
                $sqlMachines = "
                INSERT INTO dbo.resep_obat_machines_v2 (
                    id_resep, machine_code, machine_name, speed, temperature,
                    temperature_ch2, temperature_ch3, temperature_ch4, temperature_ch5,
                    temperature_ch6, temperature_ch7, temperature_ch8, temperature_ch9,
                    temperature_ch10, temperature_ch11, temperature_ch12, lebar_kain,
                    created_at, created_by, update_at, update_by, table_order
                )
                SELECT 
                    m.id_resep, m.machine_code, m.machine_name, m.speed, m.temperature,
                    m.temperature_ch2, m.temperature_ch3, m.temperature_ch4, m.temperature_ch5,
                    m.temperature_ch6, m.temperature_ch7, m.temperature_ch8, m.temperature_ch9,
                    m.temperature_ch10, m.temperature_ch11, m.temperature_ch12, m.lebar_kain,
                    m.created_at, m.created_by, m.update_at, m.update_by,
                    0 AS table_order
                FROM dbo.resep_obat_machines m;
                ";
                $stmtM = sqlsrv_query($conn, $sqlMachines);
                if ($stmtM === false) {
                    throw new Exception('Gagal menyalin Mesin Resep: ' . json_encode(sqlsrv_errors()));
                }

                // 6. Reseed identity
                $logs[] = '6. Menyesuaikan seed auto-increment IDENTITY ke resep terakhir...';
                $stmtSeed = sqlsrv_query($conn, "DBCC CHECKIDENT('dbo.resep_obat_v2', RESEED) WITH NO_INFOMSGS");
                if ($stmtSeed === false) {
                    throw new Exception('Gagal reseed identity: ' . json_encode(sqlsrv_errors()));
                }

                // 7. Verify row counts
                $cntCheck = getCounts($conn);
                if ($cntCheck['v2_header'] !== $cntCheck['v1_header']) {
                    throw new Exception("Mismatch Header count: V1 = {$cntCheck['v1_header']}, V2 = {$cntCheck['v2_header']}");
                }
                if ($cntCheck['v2_detail'] !== $cntCheck['v1_detail']) {
                    throw new Exception("Mismatch Detail count: V1 = {$cntCheck['v1_detail']}, V2 = {$cntCheck['v2_detail']}");
                }
                if ($cntCheck['v2_machines'] !== $cntCheck['v1_machines']) {
                    throw new Exception("Mismatch Machines count: V1 = {$cntCheck['v1_machines']}, V2 = {$cntCheck['v2_machines']}");
                }

                sqlsrv_commit($conn);
                $elapsed = round(microtime(true) - $startTime, 2);
                $logs[] = "7. Sukses! Transaksi di-commit dalam {$elapsed} detik.";

                $migrationResult = [
                    'ok' => true,
                    'message' => "Migrasi data berhasil 100%! Seluruh {$cntCheck['v2_header']} resep, {$cntCheck['v2_detail']} detail, dan {$cntCheck['v2_machines']} mesin berhasil dipindahkan ke V2.",
                    'logs' => $logs,
                    'elapsed' => $elapsed,
                    'counts' => $cntCheck
                ];
            } catch (Exception $ex) {
                sqlsrv_rollback($conn);
                $logs[] = 'TRANSAKSI DI-ROLLBACK KARENA TERJADI ERROR: ' . $ex->getMessage();
                $migrationResult = [
                    'ok' => false,
                    'message' => 'Migrasi dibatalkan secara aman (Rollback): ' . $ex->getMessage(),
                    'logs' => $logs
                ];
            }
        }
    }
}

$counts = getCounts($conn);

include '../../../includes/header.php';
include '../../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-exchange-alt mr-2 text-<?= htmlspecialchars($themeColor); ?>"></i>
                        Migrasi Data PPC Guidance (V1 &rarr; V2)
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="list_resep.php">Resep V2</a></li>
                        <li class="breadcrumb-item active">Migrasi dari V1</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- Result Alerts -->
            <?php if ($migrationResult): ?>
                <?php if ($migrationResult['ok']): ?>
                    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                        <h5><i class="icon fas fa-check-circle"></i> Migrasi Berhasil!</h5>
                        <?= htmlspecialchars($migrationResult['message']); ?>
                        <div class="mt-3">
                            <a href="list_resep.php" class="btn btn-light text-success font-weight-bold">
                                <i class="fas fa-list mr-1"></i> Buka Daftar Resep V2 Sekarang
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                        <h5><i class="icon fas fa-exclamation-triangle"></i> Migrasi Gagal (Data Aman di-Rollback)</h5>
                        <?= htmlspecialchars($migrationResult['message']); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Status Comparison Cards -->
            <div class="row">
                <!-- V1 Card -->
                <div class="col-md-6">
                    <div class="card card-outline card-secondary shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-database text-muted mr-1"></i> Data Sumber (PPC Guidance V1)
                            </h3>
                            <span class="badge badge-secondary float-right">Tabel Asli</span>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-unbordered mb-3">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-file-prescription mr-2 text-primary"></i> <b>Header Resep</b> (<code>resep_obat</code>)</span>
                                    <span class="badge badge-primary badge-pill font-size-14 px-3 py-2">
                                        <?= number_format($counts['v1_header'], 0, ',', '.'); ?> Baris
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-vial mr-2 text-info"></i> <b>Detail Bahan Obat</b> (<code>resep_obat_detail</code>)</span>
                                    <span class="badge badge-info badge-pill font-size-14 px-3 py-2">
                                        <?= number_format($counts['v1_detail'], 0, ',', '.'); ?> Baris
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-cogs mr-2 text-success"></i> <b>Data Mesin Produksi</b> (<code>resep_obat_machines</code>)</span>
                                    <span class="badge badge-success badge-pill font-size-14 px-3 py-2">
                                        <?= number_format($counts['v1_machines'], 0, ',', '.'); ?> Baris
                                    </span>
                                </li>
                            </ul>
                            <small class="text-muted"><i class="fas fa-lock mr-1"></i> Data di V1 hanya dibaca (READ-ONLY) dan tidak akan diubah atau dihapus.</small>
                        </div>
                    </div>
                </div>

                <!-- V2 Card -->
                <div class="col-md-6">
                    <div class="card card-outline card-<?= htmlspecialchars($themeColor); ?> shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold text-<?= htmlspecialchars($themeColor); ?>">
                                <i class="fas fa-rocket mr-1"></i> Data Target (PPC Guidance V2)
                            </h3>
                            <span class="badge badge-<?= htmlspecialchars($themeColor); ?> float-right">Tabel V2</span>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-unbordered mb-3">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-file-prescription mr-2 text-primary"></i> <b>Header Resep V2</b> (<code>resep_obat_v2</code>)</span>
                                    <span class="badge badge-<?= $counts['v2_header'] === $counts['v1_header'] ? 'success' : 'warning'; ?> badge-pill font-size-14 px-3 py-2">
                                        <?= number_format($counts['v2_header'], 0, ',', '.'); ?> Baris
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-vial mr-2 text-info"></i> <b>Detail Bahan Obat V2</b> (<code>resep_obat_detail_v2</code>)</span>
                                    <span class="badge badge-<?= $counts['v2_detail'] === $counts['v1_detail'] ? 'success' : 'warning'; ?> badge-pill font-size-14 px-3 py-2">
                                        <?= number_format($counts['v2_detail'], 0, ',', '.'); ?> Baris
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-cogs mr-2 text-success"></i> <b>Data Mesin Produksi V2</b> (<code>resep_obat_machines_v2</code>)</span>
                                    <span class="badge badge-<?= $counts['v2_machines'] === $counts['v1_machines'] ? 'success' : 'warning'; ?> badge-pill font-size-14 px-3 py-2">
                                        <?= number_format($counts['v2_machines'], 0, ',', '.'); ?> Baris
                                    </span>
                                </li>
                            </ul>

                            <?php if ($counts['bak_header'] > 0): ?>
                                <small class="text-success"><i class="fas fa-shield-alt mr-1"></i> Tersedia tabel cadangan backup V2: <?= $counts['bak_header']; ?> resep tercadangkan di <code>dbo.resep_obat_v2_bak</code>.</small>
                            <?php else: ?>
                                <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Belum ada tabel backup sebelumnya.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Migration Strategy & Action Card -->
            <div class="row">
                <div class="col-12">
                    <div class="card card-default shadow-sm">
                        <div class="card-header bg-light">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-clipboard-check text-success mr-2"></i> Konfirmasi Aturan & Mekanisme Migrasi
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-7">
                                    <h5>Strategi yang Diterapkan:</h5>
                                    <ol class="pl-3">
                                        <li class="mb-2">
                                            <b>Auto-Backup:</b> Data V2 saat ini (5 resep uji coba) otomatis disalin ke tabel cadangan <code>dbo.resep_obat_v2_bak</code>.
                                        </li>
                                        <li class="mb-2">
                                            <b>Preserve Original IDs:</b> Nomor ID resep asli di V1 (ID 13 s/d 3799) dipertahankan sama persis 1-to-1 menggunakan <code>SET IDENTITY_INSERT ON</code>.
                                        </li>
                                        <li class="mb-2">
                                            <b>Pemetaan Multi-Tabel V2:</b> Seluruh detail obat dimasukkan ke <b>Tabel 1 (<code>table_index = 1</code>)</b>, nomor urut (<code>sort_order</code>) terurut rapi, dan routing serta nama mesin diambil dari header resep.
                                        </li>
                                        <li class="mb-2">
                                            <b>Aman & Bertransaksi:</b> Menggunakan <code>BEGIN TRANSACTION</code>. Jika terjadi gangguan jaringan atau kesalahan database, seluruh proses akan otomatis dibatalkan (Rollback) tanpa ada data yang rusak.
                                        </li>
                                        <li class="mb-2">
                                            <b>Reseed Auto-Increment:</b> Counter identity diperbarui ke ID maksimum agar pembuatan resep baru nantinya tidak mengalami konflik ID.
                                        </li>
                                    </ol>
                                </div>
                                <div class="col-md-5 d-flex flex-column justify-content-center border-left pl-4">
                                    <div class="callout callout-info mb-4">
                                        <h5><i class="fas fa-user-shield mr-1"></i> Dijalankan Oleh:</h5>
                                        <p class="mb-0">User: <b><?= htmlspecialchars($currentUser); ?></b><br>Waktu: <?= date('d F Y, H:i:s'); ?> WIB</p>
                                    </div>

                                    <form method="POST" id="formMigration">
                                        <input type="hidden" name="action" value="execute_migration">
                                        <button type="button" class="btn btn-success btn-lg btn-block shadow-sm" id="btnRunMigration">
                                            <i class="fas fa-play-circle mr-2"></i> Eksekusi Migrasi Data Sekarang
                                        </button>
                                    </form>

                                    <div class="text-center mt-2">
                                        <a href="list_resep.php" class="btn btn-link text-muted">
                                            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Resep V2
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <?php if ($migrationResult && !empty($migrationResult['logs'])): ?>
                                <hr>
                                <h5 class="font-weight-bold"><i class="fas fa-terminal mr-2"></i> Log Eksekusi:</h5>
                                <div class="p-3 bg-dark text-white rounded font-monospace" style="max-height: 250px; overflow-y: auto;">
                                    <?php foreach ($migrationResult['logs'] as $log): ?>
                                        <div class="small mb-1">&gt; <?= htmlspecialchars($log); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include '../../../includes/footer.php'; ?>

<!-- SweetAlert2 -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    $('#btnRunMigration').click(function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Mulai Migrasi Data?',
            text: 'Data V2 saat ini akan dibackup, lalu <?= number_format($counts['v1_header'], 0, ',', '.') ?> resep dari V1 akan disalin ke V2 dengan nomor ID asli. Lanjutkan?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check mr-1"></i> Ya, Jalankan Migrasi!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses Migrasi...',
                    text: 'Mohon tunggu, sedang menyalin data header, detail, dan mesin...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                        $('#formMigration').submit();
                    }
                });
            }
        });
    });
});
</script>
