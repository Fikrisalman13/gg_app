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
?>

<style>
    .welcome-visual img {
        width: 100%;
        height: 100%;
        max-height: 400px;
        object-fit: cover;
        display: block;
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

            <div class="card shadow border-0">
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
                        <div class="card-body text-center">

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
