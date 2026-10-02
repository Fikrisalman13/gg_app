<?php
declare(strict_types=1);
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$theme = $_SESSION['Theme'] ?? 'primary';
$success = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // INTERVAL
    foreach ([
        'reminder_interval_kontrak',
        'reminder_interval_sertifikat',
        'reminder_interval_surat_kendaraan'
    ] as $k) {
        if (isset($_POST[$k])) {
            sqlsrv_query(
                $conn,
                "UPDATE dr_reminder_interval SET nilai=? WHERE kunci=?",
                [(string) $_POST[$k], $k]
            );
        }
    }

    // DYNAMIC CATEGORIES INTERVAL (V2)
    if (isset($_POST['dynamic_cat_ids'])) {
        foreach ($_POST['dynamic_cat_ids'] as $cid) {
            $val = (int) ($_POST['dynamic_interval_' . $cid] ?? 30);
            sqlsrv_query($conn, "UPDATE dr_categories SET reminder_interval = ? WHERE id = ?", [$val, $cid]);
        }
    }

    // EMAIL
    foreach ([
        'mail_host',
        'mail_username',
        'mail_password',
        'mail_port',
        'mail_from_email',
        'mail_from_name',
        'wa_api_url',
        'wa_api_key'
    ] as $k) {
        if (isset($_POST[$k]) && $_POST[$k] !== '') {
            setAppConfig($conn, $k, $_POST[$k]);
        }
    }

    $success = 'Pengaturan berhasil disimpan';
}

function interval($k)
{
    global $conn;
    $r = sqlsrv_fetch_array(
        sqlsrv_query($conn, "SELECT nilai FROM dr_reminder_interval WHERE kunci=?", [$k]),
        SQLSRV_FETCH_ASSOC
    );
    return $r['nilai'];
}
?>

<!-- UI -->
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-cogs mr-2"></i>Pengaturan Reminder</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <span class="badge badge-info">v2.0 Hybrid</span>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-check"></i> Berhasil!</h5>
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="row">
                    <!-- KOLOM KIRI: INTERVAL -->
                    <div class="col-md-6">
                        <div class="card card-outline card-<?= htmlspecialchars($theme) ?>">
                            <div class="card-header bg-<?= htmlspecialchars($theme) ?>">
                                <h3 class="card-title text-white"><i class="fas fa-clock mr-2"></i>Interval Pengingat
                                </h3>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-4">Tentukan berapa hari sebelum tanggal kadaluarsa sistem harus
                                    mulai mengirimkan notifikasi.</p>

                                <?php
                                // Fetch all categories (Parents & Children)
                                $categories = [];
                                $stmt = sqlsrv_query($conn, "SELECT id, category_name, parent_id, reminder_interval, color, icon FROM dr_categories ORDER BY parent_id ASC, category_name ASC");
                                if ($stmt) {
                                    while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                        if ($r['color'] === 'user') {
                                            $r['color'] = $theme;
                                        }
                                        $categories[] = $r;
                                    }
                                }

                                $parentCategories = [];
                                $subCategories = [];
                                foreach ($categories as $cat) {
                                    if ($cat['parent_id'] === null) {
                                        $parentCategories[$cat['id']] = $cat;
                                        $parentCategories[$cat['id']]['children'] = [];
                                    } else {
                                        $subCategories[] = $cat;
                                    }
                                }
                                foreach ($subCategories as $sub) {
                                    $pId = $sub['parent_id'];
                                    if (isset($parentCategories[$pId])) {
                                        $parentCategories[$pId]['children'][] = $sub;
                                    }
                                }

                                // Exclude Parent Categories that don't have children
                                $parentCategories = array_filter($parentCategories, function ($p) {
                                    return !empty($p['children']);
                                });

                                if (!empty($parentCategories)):
                                    foreach ($parentCategories as $parent):
                                        ?>
                                        <div
                                            class="card card-outline card-<?= htmlspecialchars($parent['color'] ?: $theme) ?> mt-3 shadow-sm">
                                            <div class="card-header bg-light py-2">
                                                <h6
                                                    class="card-title font-weight-bold text-<?= htmlspecialchars($parent['color'] ?: $theme) ?> mb-0">
                                                    <i
                                                        class="fas <?= htmlspecialchars($parent['icon'] ?: 'fa-folder') ?> mr-2"></i><?= htmlspecialchars($parent['category_name']) ?>
                                                </h6>
                                            </div>
                                            <div class="card-body bg-white p-3">
                                                <?php foreach ($parent['children'] as $child): ?>
                                                    <div class="form-group mb-3">
                                                        <label
                                                            class="font-weight-bold text-dark mb-1"><?= htmlspecialchars($child['category_name']) ?>
                                                            <span class="text-muted font-weight-normal"></span></label>
                                                        <input type="hidden" name="dynamic_cat_ids[]" value="<?= $child['id'] ?>">
                                                        <div class="input-group">
                                                            <input type="number" name="dynamic_interval_<?= $child['id'] ?>"
                                                                class="form-control" value="<?= (int) $child['reminder_interval'] ?>"
                                                                required>
                                                            <div class="input-group-append"><span
                                                                    class="input-group-text">Hari</span></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php
                                    endforeach;
                                else:
                                    ?>
                                    <div class="alert alert-warning py-3"><i
                                            class="fas fa-exclamation-triangle mr-2"></i>Belum ada sub-kategori yang
                                        terdaftar.</div>
                                <?php endif; ?>

                                <hr>
                                <div class="bg-light p-3 rounded border">
                                    <h5 class="text-primary"><i class="fas fa-magic mr-2"></i>Kelola Modul Dinamis</h5>
                                    <p class="small text-muted">Tambahkan kategori induk atau sub-kategori dokumen
                                        kustom dan atur kolom data secara mandiri.</p>
                                    <a href="manage_categories.php" class="btn btn-sm btn-primary">
                                        <i class="fas fa-external-link-alt mr-1"></i> Kelola Kategori & Kolom
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- WHATSAPP API -->
                        <div class="card card-outline card-<?= htmlspecialchars($theme) ?>">
                            <div class="card-header bg-<?= htmlspecialchars($theme) ?>">
                                <h3 class="card-title text-white"><i class="fab fa-whatsapp mr-2"></i>WhatsApp API
                                    (Fonnte)</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label>API URL</label>
                                    <input name="wa_api_url" class="form-control"
                                        value="<?= appConfig($conn, 'wa_api_url') ?>"
                                        placeholder="https://api.fonnte.com/send">
                                </div>
                                <div class="form-group">
                                    <label>API Key / Token</label>
                                    <div class="input-group">
                                        <input name="wa_api_key" type="password" id="wa_key" class="form-control"
                                            placeholder="Isi untuk mengubah token">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button"
                                                onclick="togglePass('wa_key')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-danger">Pastikan perangkat di Fonnte dalam status
                                        'Connected'.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KOLOM KANAN: EMAIL -->
                    <div class="col-md-6">
                        <div class="card card-outline card-<?= htmlspecialchars($theme) ?>">
                            <div class="card-header bg-<?= htmlspecialchars($theme) ?>">
                                <h3 class="card-title text-white"><i class="fas fa-envelope mr-2"></i>Konfigurasi Email
                                    (SMTP)</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-8">
                                        <div class="form-group">
                                            <label>SMTP Host</label>
                                            <input name="mail_host" class="form-control"
                                                value="<?= appConfig($conn, 'mail_host') ?>" placeholder="smtp.gmail.com">
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <div class="form-group">
                                            <label>Port</label>
                                            <input name="mail_port" class="form-control"
                                                value="<?= appConfig($conn, 'mail_port') ?>" placeholder="587">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Username / Email</label>
                                    <input name="mail_username" class="form-control"
                                        value="<?= appConfig($conn, 'mail_username') ?>" placeholder="email@gmail.com">
                                </div>

                                <div class="form-group">
                                    <label>Password App</label>
                                    <div class="input-group">
                                        <input name="mail_password" type="password" id="mail_pass" class="form-control"
                                            placeholder="Kosongkan jika tidak diubah">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button"
                                                onclick="togglePass('mail_pass')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <div class="form-group">
                                    <label>Sender Email</label>
                                    <input name="mail_from_email" class="form-control"
                                        value="<?= appConfig($conn, 'mail_from_email') ?>"
                                        placeholder="noreply@domain.com">
                                </div>

                                <div class="form-group">
                                    <label>Sender Name</label>
                                    <input name="mail_from_name" class="form-control"
                                        value="<?= appConfig($conn, 'mail_from_name') ?>" placeholder="Admin Reminder">
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-none">
                            <div class="card-body p-0">
                                <button type="submit" class="btn btn-block btn-lg btn-<?= htmlspecialchars($theme) ?>">
                                    <i class="fas fa-save mr-2"></i> Simpan Semua Perubahan
                                </button>
                                <a href="index.php" class="btn btn-block btn-link text-muted">Kembali ke Dashboard</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
    function togglePass(id) {
        const x = document.getElementById(id);
        if (x.type === "password") {
            x.type = "text";
        } else {
            x.type = "password";
        }
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>