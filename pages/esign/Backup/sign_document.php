<?php
session_start();
ob_start();

require '../../koneksi.php'; // Harus menghasilkan $conn
require '../../includes/header.php';
require '../../includes/sidebar.php';

// -------------------------
// Helper kecil
// -------------------------
function abort_with_message($msg, $http_location = null) {
    // bisa diarahkan kembali ke halaman lain jika $http_location disediakan
    if ($http_location) {
        $_SESSION['error'] = $msg;
        header("Location: " . $http_location);
        exit;
    }
    echo "<div style='max-width:900px;margin:40px auto;font-family:Arial, sans-serif;'>";
    echo "<h3>Kesalahan</h3><p>" . htmlspecialchars($msg) . "</p>";
    echo "</div>";
    exit;
}

function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// -------------------------
// Validasi: login
// -------------------------
if (!isset($_SESSION['UserName'])) {
    // jangan lanjutkan bila belum login
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

// -------------------------
// Validasi koneksi db
// -------------------------
if (!isset($conn) || !$conn) {
    abort_with_message("Koneksi ke database gagal. Hubungi admin.");
}

// -------------------------
// Ambil & sanitize input
// -------------------------
$id_kontrak = isset($_GET['id_kontrak']) ? trim($_GET['id_kontrak']) : null;
$nik        = isset($_GET['nik'])        ? trim($_GET['nik']) : null;
$source     = isset($_GET['source'])     ? trim($_GET['source']) : 'kontrak';
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Validasi input minimal
if (empty($id_kontrak) || empty($nik)) {
    abort_with_message("Parameter tidak lengkap. Mohon pastikan id_kontrak dan nik tersedia.");
}

// Jika id_kontrak diharapkan numeric, cast aman (jika bukan numeric maka batal)
if (!is_numeric($id_kontrak)) {
    abort_with_message("Parameter id_kontrak tidak valid.");
}
$id_kontrak = (int)$id_kontrak;

// Sanitasi NIK (batasan panjang & karakter)
$nik = preg_replace('/[^0-9A-Za-z\-_.]/', '', substr($nik, 0, 50));

// -------------------------
// Cek apakah tanda tangan sudah ada
// -------------------------
$checkSql = "SELECT COUNT(*) AS jumlah FROM tanda_tangan WHERE nik = ?";
$checkStmt = sqlsrv_prepare($conn, $checkSql, array(&$nik));
if ($checkStmt === false) {
    abort_with_message("Gagal menyiapkan query pengecekan tanda tangan: " . print_r(sqlsrv_errors(), true));
}
if (!sqlsrv_execute($checkStmt)) {
    abort_with_message("Gagal mengeksekusi pengecekan tanda tangan: " . print_r(sqlsrv_errors(), true));
}
$row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
$tandaTanganAda = ($row && isset($row['jumlah']) && (int)$row['jumlah'] > 0);
sqlsrv_free_stmt($checkStmt);

// -------------------------
// Proses POST: simpan signature (jika belum ada tanda tangan)
// -------------------------
$status = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tandaTanganAda) {
    // Pastikan ada field signature
    $signatureData = $_POST['signature'] ?? '';
    $signatureData = trim($signatureData);

    if (empty($signatureData)) {
        $status = "Silakan buat tanda tangan terlebih dahulu sebelum menyimpan.";
    } else {
        // Minimal check: data URL biasanya dimulai "data:image/png;base64,"
        if (strpos($signatureData, 'data:') !== 0) {
            $status = "Format tanda tangan tidak valid.";
        } else {
            // Mulai transaksi agar INSERT + UPDATE konsisten (jika driver mendukung)
            // note: sqlsrv_begin_transaction tersedia jika koneksi dikonfigurasi
            @sqlsrv_begin_transaction($conn);

            // 1) Insert ke tanda_tangan
            $insertSql = "INSERT INTO tanda_tangan (nik, signature, created_at, created_by) VALUES (?, ?, GETDATE(), ?)";
            $createdBy = $_SESSION['UserName'] ?? $nik;
            $paramsInsert = array(&$nik, &$signatureData, &$createdBy);
            $insertStmt = sqlsrv_prepare($conn, $insertSql, $paramsInsert);
            if ($insertStmt === false) {
                @sqlsrv_rollback($conn);
                $status = "Gagal menyiapkan penyimpanan tanda tangan: " . print_r(sqlsrv_errors(), true);
            } else {
                $okInsert = sqlsrv_execute($insertStmt);
                if ($okInsert === false) {
                    @sqlsrv_rollback($conn);
                    $status = "Gagal menyimpan tanda tangan: " . print_r(sqlsrv_errors(), true);
                } else {
                    // 2) Update status kontrak
                    $updateSql = "UPDATE kontrak_kerja SET status_tanda_tangan = '1', upduser = ?, upddate = GETDATE() WHERE id = ?";
                    $updUser = $createdBy;
                    $paramsUpd = array(&$updUser, &$id_kontrak);
                    $updateStmt = sqlsrv_prepare($conn, $updateSql, $paramsUpd);
                    if ($updateStmt === false) {    
                        @sqlsrv_rollback($conn);
                        $status = "Gagal menyiapkan update kontrak: " . print_r(sqlsrv_errors(), true);
                    } else {
                        $okUpdate = sqlsrv_execute($updateStmt);
                        if ($okUpdate === false) {
                            @sqlsrv_rollback($conn);
                            $status = "Gagal mengupdate status kontrak: " . print_r(sqlsrv_errors(), true);
                        } else {
                            // Commit jika semua ok
                            @sqlsrv_commit($conn);
                            $status = "Tanda tangan berhasil disimpan!";
                            $tandaTanganAda = true;
                        }
                        sqlsrv_free_stmt($updateStmt);
                    }
                }
                sqlsrv_free_stmt($insertStmt);
            }
        }
    }
}

// -------------------------
// Ambil data kontrak
// -------------------------
$sqlContract = "SELECT * FROM kontrak_kerja WHERE id = ? AND nik = ?";
$stmtContract = sqlsrv_prepare($conn, $sqlContract, array(&$id_kontrak, &$nik));
if ($stmtContract === false) {
    abort_with_message("Gagal menyiapkan query kontrak: " . print_r(sqlsrv_errors(), true));
}
if (!sqlsrv_execute($stmtContract)) {
    abort_with_message("Gagal mengeksekusi query kontrak: " . print_r(sqlsrv_errors(), true));
}
$contract = sqlsrv_fetch_array($stmtContract, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtContract);
if (!$contract) {
    abort_with_message("Dokumen kontrak tidak ditemukan.");
}

// -------------------------
// Ambil data karyawan
// -------------------------
$sqlEmployee = "SELECT * FROM dbo.m_emp WHERE nik = ?";
$stmtEmployee = sqlsrv_prepare($conn, $sqlEmployee, array(&$nik));
if ($stmtEmployee === false) {
    abort_with_message("Gagal menyiapkan query karyawan: " . print_r(sqlsrv_errors(), true));
}
if (!sqlsrv_execute($stmtEmployee)) {
    abort_with_message("Gagal mengeksekusi query karyawan: " . print_r(sqlsrv_errors(), true));
}
$employee = sqlsrv_fetch_array($stmtEmployee, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtEmployee);
if (!$employee) {
    abort_with_message("Data karyawan tidak ditemukan.");
}

// -------------------------
// Helper formatting tanggal
// -------------------------
function format_date_object($val) {
    if ($val instanceof DateTime) return $val->format('d-m-Y');
    if (is_object($val) && method_exists($val, 'format')) return $val->format('d-m-Y');
    // Jika SQL Server mengembalikan array, coba cast:
    return is_string($val) ? date('d-m-Y', strtotime($val)) : '-';
}
$docDate = isset($contract['tanggal_dokumen']) ? format_date_object($contract['tanggal_dokumen']) : '-';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Proses Tanda Tangan Digital</title>

    <!-- AdminLTE & plugin CSS (tetap gunakan path Anda) -->
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="/gg_app/plugins/css/responsive.bootstrap5.min.css">

    <style>
        /* Styling bersih dan profesional */
        .signature-container { border: 1px solid #e6e6e6; border-radius: 10px; padding: 18px; background:#fff; }
        .canvas-container { border-radius:6px; overflow:hidden; border:1px solid #ddd; background:#fff; }
        #signatureCanvas { width:100%; height:200px; display:block; touch-action:none; cursor:crosshair; background:#fff; }
        .signature-instruction { color:#6c757d; font-size:0.95rem; text-align:center; margin-bottom:12px; }
        .btn-group-custom { display:flex; gap:10px; justify-content:center; margin-top:12px; flex-wrap:wrap; }
        .status-badge { font-size:0.85rem; padding:6px 12px; border-radius:15px; color:#fff; }
        .document-info-card, .signature-card { box-shadow:0 2px 6px rgba(0,0,0,0.06); }
        .info-item label { color:#444; font-weight:600; display:block; margin-bottom:6px; }
        .info-item p { margin:0; font-size:1.02rem; color:#222; }
        .redirect-options { background:#f8f9fa; border:1px solid #e9ecef; padding:16px; border-radius:8px; margin-top:14px; }
        @media (max-width:576px) {
            .btn-group-custom { flex-direction:column; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2 align-items-center">
                    <div class="col-sm-6">
                        <h1 class="m-0">Proses Tanda Tangan Digital</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                            <li class="breadcrumb-item"><a href="/gg_app/pages/esign/carikaryawan_<?php echo $source === 'staff' ? 'staff' : 'kontrak'; ?>.php">Cari Karyawan</a></li>
                            <li class="breadcrumb-item active">Proses Tanda Tangan</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content pb-5">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-8 mx-auto">

                        <!-- Informasi Dokumen -->
                        <div class="card document-info-card mb-3">
                            <div class="card-header bg-<?php echo e($themeColor);?> text-white d-flex justify-content-between align-items-center">
                                <h3 class="card-title mb-0"><i class="fas fa-file-contract mr-2"></i> Detail Dokumen Kontrak</h3>
                                <?php if ($tandaTanganAda): ?>
                                    <span class="status-badge bg-success">Telah Ditandatangani</span>
                                <?php else: ?>
                                    <span class="status-badge bg-warning">Belum Ditandatangani</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-item mb-3">
                                            <label>NIK</label>
                                            <p><?php echo e($employee['nik'] ?? '-'); ?></p>
                                        </div>
                                        <div class="info-item mb-3">
                                            <label>Nomor Dokumen</label>
                                            <p><?php echo e($contract['nomor_dokumen'] ?? '-'); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-item mb-3">
                                            <label>Nama Lengkap</label>
                                            <p><?php echo e($employee['nama_lengkap'] ?? '-'); ?></p>
                                        </div>
                                        <div class="info-item mb-3">
                                            <label>Tanggal Dokumen</label>
                                            <p><?php echo e($docDate); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tanda Tangan -->
                        <div class="card signature-card">
                            <div class="card-header bg-<?php echo e($themeColor);?> text-white">
                                <h3 class="card-title mb-0"><i class="fas fa-signature mr-2"></i> Tanda Tangan Digital</h3>
                            </div>
                            <div class="card-body">
                                <?php if ($tandaTanganAda): ?>
                                    <div class="alert alert-success d-flex align-items-start" role="alert">
                                        <i class="fas fa-check-circle fa-2x mr-3"></i>
                                        <div>
                                            <h5 class="mb-1">Tanda tangan berhasil disimpan!</h5>
                                            <div>Dokumen ini telah ditandatangani secara digital.</div>
                                        </div>
                                    </div>

                                    <div class="redirect-options text-center">
                                        <h5>Pilih tujuan selanjutnya:</h5>
                                        <div class="mt-3">
                                            <a href="carikaryawan_kontrak.php" class="btn btn-<?php echo e($themeColor);?>"><i class="fas fa-file-contract mr-2"></i> Proses Kontrak</a>
                                            <!-- Anda bisa menambahkan tautan lain jika diperlukan -->
                                        </div>
                                    </div>

                                <?php else: ?>

                                    <div class="signature-instruction">
                                        Gunakan mouse, touchscreen, atau stylus untuk menandatangani pada area di bawah.
                                    </div>

                                    <form method="POST" id="signatureForm" novalidate>
                                        <div class="signature-container">
                                            <div class="canvas-container">
                                                <canvas id="signatureCanvas" aria-label="Area tanda tangan"></canvas>
                                            </div>

                                            <div class="btn-group-custom">
                                                <button type="button" id="clearButton" class="btn btn-outline-secondary">
                                                    <i class="fas fa-eraser mr-1"></i> Hapus
                                                </button>
                                                <button type="button" id="undoButton" class="btn btn-outline-secondary" title="Batalkan goresan terakhir">
                                                    <i class="fas fa-undo mr-1"></i> Undo
                                                </button>
                                            </div>

                                            <input type="hidden" name="signature" id="signature" />
                                        </div>

                                        <div class="form-group text-center mt-4">
                                            <button type="submit" class="btn btn-<?php echo e($themeColor);?> btn-custom">
                                                <i class="fas fa-check-circle mr-2"></i> Simpan Tanda Tangan
                                            </button>
                                            <a href="carikaryawan_<?php echo $source === 'staff' ? 'staff' : 'kontrak'; ?>.php" class="btn btn-outline-secondary ml-2">
                                                <i class="fas fa-arrow-left mr-2"></i> Kembali
                                            </a>
                                        </div>
                                    </form>

                                <?php endif; ?>

                                <?php if (!empty($status)): ?>
                                    <div class="alert alert-<?php echo (stripos($status, 'berhasil') !== false) ? 'success' : 'danger'; ?> mt-3">
                                        <?php echo e($status); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script: letakkan di bawah untuk performa -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const canvas = document.getElementById('signatureCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const clearBtn = document.getElementById('clearButton');
    const undoBtn = document.getElementById('undoButton');
    const signatureInput = document.getElementById('signature');
    const form = document.getElementById('signatureForm');

    // Stack history untuk fitur undo sederhana (simpan imageData)
    const history = [];
    const maxHistory = 10;

    // Penyesuaian resolusi canvas untuk tampilan yang tajam
    function resizeCanvas() {
        const container = canvas.parentElement;
        const style = getComputedStyle(container);
        const width = container.clientWidth;
        // tetapkan tinggi tetap 200 (sama seperti desain)
        const height = 200;

        // dukungan devicePixelRatio untuk ketajaman di layar high-DPI
        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.round(width * dpr);
        canvas.height = Math.round(height * dpr);
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        ctx.scale(dpr, dpr);

        // Fill background putih
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width / dpr, canvas.height / dpr);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }

    // Panggil resize awal
    resizeCanvas();
    // Resize saat jendela berubah
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            // Simpan konten saat resize: convert to image, resize, lalu redraw
            const data = canvas.toDataURL();
            resizeCanvas();
            const img = new Image();
            img.onload = function() {
                ctx.drawImage(img, 0, 0, canvas.width / (window.devicePixelRatio || 1), canvas.height / (window.devicePixelRatio || 1));
            };
            img.src = data;
        }, 150);
    });

    // Drawing state
    let drawing = false;
    let lastX = 0, lastY = 0;
    let lastTime = 0;
    let lastPressure = 0.5;

    function pushHistory() {
        try {
            if (history.length >= maxHistory) history.shift();
            history.push(canvas.toDataURL());
        } catch (e) {
            // ignore
        }
    }

    function restoreFromHistory() {
        if (!history.length) return;
        const data = history.pop();
        const img = new Image();
        img.onload = function() {
            // reset canvas background putih
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width / (window.devicePixelRatio || 1), canvas.height / (window.devicePixelRatio || 1));
            ctx.drawImage(img, 0, 0, canvas.width / (window.devicePixelRatio || 1), canvas.height / (window.devicePixelRatio || 1));
        };
        img.src = data;
    }

    function getCoords(e) {
        const rect = canvas.getBoundingClientRect();
        if (e.touches && e.touches.length) {
            return [e.touches[0].clientX - rect.left, e.touches[0].clientY - rect.top];
        } else if (e.clientX !== undefined) {
            return [e.clientX - rect.left, e.clientY - rect.top];
        } else if (e.changedTouches && e.changedTouches.length) {
            return [e.changedTouches[0].clientX - rect.left, e.changedTouches[0].clientY - rect.top];
        }
        return [0,0];
    }

    function start(e) {
        e.preventDefault();
        // simpan snapshot untuk undo
        pushHistory();

        drawing = true;
        const coords = getCoords(e);
        lastX = coords[0];
        lastY = coords[1];
        lastTime = Date.now();
        // pressure support via PointerEvent
        lastPressure = (e.pressure !== undefined && e.pressure > 0) ? e.pressure : 0.5;
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
    }

    function draw(e) {
        if (!drawing) return;
        e.preventDefault();

        const [x, y] = getCoords(e);
        const now = Date.now();
        const elapsed = Math.max(1, now - lastTime);
        lastTime = now;

        const pressure = (e.pressure !== undefined && e.pressure > 0) ? e.pressure : lastPressure;
        const dist = Math.hypot(x - lastX, y - lastY);
        const speed = dist / elapsed;
        const dynamicWidth = Math.max(1, 5 - speed * 2) * pressure;

        ctx.lineWidth = dynamicWidth;
        ctx.strokeStyle = "#000";
        ctx.lineTo(x, y);
        ctx.stroke();

        lastX = x;
        lastY = y;
        lastPressure = pressure;
    }

    function stop(e) {
        if (!drawing) return;
        e.preventDefault();
        drawing = false;
        ctx.closePath();
    }

    // Event listeners: gunakan pointer events (kompatibel mouse/touch/stylus)
    canvas.addEventListener('pointerdown', start);
    canvas.addEventListener('pointermove', draw);
    canvas.addEventListener('pointerup', stop);
    canvas.addEventListener('pointercancel', stop);
    canvas.addEventListener('pointerleave', stop);

    // Prevent scrolling when touching canvas (mobile)
    canvas.addEventListener('touchstart', function(ev){ ev.preventDefault(); }, { passive:false });

    // Tombol clear
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            pushHistory();
            ctx.clearRect(0, 0, canvas.width / (window.devicePixelRatio || 1), canvas.height / (window.devicePixelRatio || 1));
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width / (window.devicePixelRatio || 1), canvas.height / (window.devicePixelRatio || 1));
        });
    }

    // Tombol undo
    if (undoBtn) {
        undoBtn.addEventListener('click', function () {
            restoreFromHistory();
        });
    }

    // Saat submit, ambil dataURL dan validasi panjang
    if (form) {
        form.addEventListener('submit', function (ev) {
            const dataUrl = canvas.toDataURL('image/png');
            // cek kecilnya data -> berarti kosong
            if (!dataUrl || dataUrl.length < 1000) {
                ev.preventDefault();
                alert('Harap buat tanda tangan terlebih dahulu sebelum menyimpan.');
                return false;
            }
            signatureInput.value = dataUrl;         
            // form submit berlanjut (POST)
        });
    }
});
</script>

</body>
</html>

<?php
ob_end_flush();
?>
