<?php
session_start();
require_once 'koneksi.php';
include 'includes/header.php';
include 'includes/sidebar.php';

if (!isset($_SESSION['UserId'])) {
    header("Location: login.php");
    exit();
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$username   = htmlspecialchars($_SESSION['NamaLengkap'] ?? $_SESSION['UserName']);

date_default_timezone_set('Asia/Jakarta');
$hour = date('H');
$greeting = ($hour < 10) ? "Selamat Pagi" :
           (($hour < 15) ? "Selamat Siang" :
           (($hour < 18) ? "Selamat Sore" : "Selamat Malam"));

$timeImage = (((int)$hour >= 5 && (int)$hour < 10) || ((int)$hour >= 17 && (int)$hour < 18)) ? 'pagi.png' :
             (($hour < 17) ? 'siang.png' : 'malam.png');

$totalUsers = 0;
$onlineUsers = 0;
$statsSql = "SELECT
    (SELECT COUNT_BIG(*) FROM dbo.SMUserMs) AS TotalUsers,
    (SELECT COUNT_BIG(*) FROM dbo.SMUserOnline WHERE LastSeenAt >= DATEADD(MINUTE, -5, SYSDATETIME())) AS OnlineUsers";
$statsStmt = sqlsrv_query($conn, $statsSql);
if ($statsStmt !== false && ($statsRow = sqlsrv_fetch_array($statsStmt, SQLSRV_FETCH_ASSOC))) {
    $totalUsers = (int) $statsRow['TotalUsers'];
    $onlineUsers = (int) $statsRow['OnlineUsers'];
    sqlsrv_free_stmt($statsStmt);
} else {
    $requestId = bin2hex(random_bytes(8));
    $logDir = __DIR__ . '/logs';
    if (is_dir($logDir) || @mkdir($logDir, 0775, true)) {
        $entry = [
            'timestamp' => date(DATE_ATOM), 'severity' => 'ERROR', 'request_id' => $requestId,
            'module' => 'user_presence', 'action' => 'dashboard_stats', 'user_id' => (int) $_SESSION['UserId'],
            'message' => 'Gagal memuat statistik user.', 'source_file' => __FILE__, 'source_line' => __LINE__,
            'context' => ['stage' => 'presence_count', 'dependency' => 'SQL Server'],
        ];
        @file_put_contents($logDir . '/error-' . date('Y-m-d') . '.log', json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
?>

<style>
    .welcome-visual img {
        width: 100%;
        height: 100%;
        max-height: 400px;
        object-fit: cover;
        display: block;
    }
    .home-welcome-card { position: relative; }
    .home-user-presence-bar {
        position: absolute;
        top: .75rem;
        right: .75rem;
        z-index: 2;
        display: inline-flex;
        align-items: center;
        gap: .65rem;
        padding: .38rem .7rem;
        border: 1px solid rgba(0, 0, 0, .08);
        border-radius: .35rem;
        background: rgba(255, 255, 255, .92);
        color: #6c757d;
        font-size: .78rem;
        font-weight: 600;
        box-shadow: 0 .2rem .55rem rgba(0, 0, 0, .08);
    }
    .home-user-presence-item {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        white-space: nowrap;
    }
    .home-user-presence-item strong { color: #343a40; font-size: .85rem; }
    .home-user-presence-icon { color: var(--<?= htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8') ?>, #6c757d); }
    .home-user-presence-divider { width: 1px; height: 1rem; background: rgba(0, 0, 0, .12); }
    .home-user-presence-dot {
        width: .42rem;
        height: .42rem;
        border-radius: 50%;
        background: #28a745;
        box-shadow: 0 0 0 .16rem rgba(40, 167, 69, .12);
    }
    @media (max-width: 767.98px) {
        .home-user-presence-bar { top: .6rem; right: .6rem; }
        .home-welcome-copy { padding-top: 3.5rem; }
    }
</style>

<!-- Content Wrapper -->
<div class="content-wrapper">

    <!-- Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                
                <div class="col-sm-6">
                    <h1 class="m-0">Home</h1>
                </div>

                <!-- Tanggal jam -->
                <div class="col-sm-6">
                    <div class="float-sm-right text-muted">
                        <i class="far fa-clock mr-1"></i><?= date("d M Y, H:i") ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <div class="card shadow border-0 home-welcome-card">
                <div id="home-user-presence-stats" class="home-user-presence-bar" aria-label="Statistik pengguna">
                    <span id="home-user-presence-total" class="home-user-presence-item">
                        <i class="fas fa-users home-user-presence-icon" aria-hidden="true"></i>
                        <strong><?= number_format($totalUsers, 0, ',', '.') ?></strong> pengguna
                    </span>
                    <span class="home-user-presence-divider" aria-hidden="true"></span>
                    <span id="home-user-presence-online" class="home-user-presence-item">
                        <span class="home-user-presence-dot" aria-hidden="true"></span>
                        <strong><?= number_format($onlineUsers, 0, ',', '.') ?></strong> sedang online
                    </span>
                </div>
                <div class="row no-gutters">

                    <!-- Gambar (BS4 compatible) -->
                    <div class="col-md-7">
                        <div class="welcome-visual">
                            <img src="/gg_app/pictures/<?= htmlspecialchars($timeImage) ?>"
                                 alt="Welcome Image">
                        </div>
                    </div>

                    <!-- Teks -->
                    <div class="col-md-5 d-flex align-items-center bg-light">
                        <div class="card-body text-center home-welcome-copy">

                            <h4 class="text-secondary mb-2"><?= $greeting ?>,</h4>
                            <h2 class="font-weight-bold mb-3"><?= $username ?></h2>

                            <p class="text-muted">
                                Selamat datang di <b>Support System</b>.<br>
                                Silakan gunakan menu di sebelah kiri untuk mengelola data & informasi.
                            </p>


                            <a href="/gg_app/pages/profile.php"
                               class="btn btn-<?php echo htmlspecialchars($themeColor); ?> btn-sm">
                                <i class="fas fa-user-circle mr-1"></i> Lihat Profil
                            </a>

                        </div>
                    </div>

                </div>
            </div>

        </div>
    </section>

</div>

<?php include 'includes/footer.php'; ?>
