<?php
session_start();
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../../../koneksi.php';

if (!isset($_SESSION['UserName'])) {
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';
$recentPeriods = [];
$setupError = null;

try {
    $gg = getSqlsrvConnection('gg');
    $conn = $gg;
    ensureTablesExist($gg);
    $recentPeriods = getRecentPeriods($gg, 12);
} catch (Throwable $e) {
    $setupError = $e->getMessage();
}

include '../../../includes/header.php';
include '../../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>MKO ACC Warna</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/pages/home.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="/gg_app/pages/resep_obat/list_resep.php">Resep Obat</a></li>
                        <li class="breadcrumb-item active">MKO ACC Warna</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($setupError): ?>
                <div class="alert alert-danger">
                    <strong>Setup database gagal.</strong><br>
                    <?= htmlspecialchars($setupError) ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-5">
                    <div class="card card-<?= htmlspecialchars($themeColor); ?>">
                        <div class="card-header">
                            <h3 class="card-title">Filter Tarik Raw Data</h3>
                        </div>
                        <form id="rawdataForm">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="rtgmsid">RTGMSID</label>
                                    <textarea class="form-control" id="rtgmsid" name="rtgmsid" rows="3" placeholder="Contoh: 601,613,589,639,861">601,613,589,639,861</textarea>
                                    <small class="form-text text-muted">Pisahkan dengan koma, spasi, atau enter.</small>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="startdate">Start Date</label>
                                        <input type="datetime-local" class="form-control" id="startdate" name="startdate" value="2026-04-23T00:00" required>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="enddate">End Date</label>
                                        <input type="datetime-local" class="form-control" id="enddate" name="enddate" value="2026-04-29T23:59" required>
                                    </div>
                                </div>

                                <div class="callout callout-info mb-0">
                                    Sistem akan:
                                    <br>1. Tarik data routing dari `ERP_Crystal_SUM_Demo`
                                    <br>2. Simpan ke `GG.dbo.mko_rawdata`
                                    <br>3. Bentuk kode periode unik `periode_YYYYMMDD_YYYYMMDD_XX`
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary" id="btnFetchRaw">
                                    <i class="fas fa-database mr-1"></i> Tarik Raw Data
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="card card-outline card-secondary">
                        <div class="card-header">
                            <h3 class="card-title">Tarik Material Obat</h3>
                        </div>
                        <form id="materialForm">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="kode_periode">Kode Periode</label>
                                    <input type="text" class="form-control" id="kode_periode" name="kode_periode" placeholder="Pilih dari hasil raw data atau isi manual">
                                    <small class="form-text text-muted">Material akan diambil dari `prdnmbr` unik di `mko_rawdata` sesuai kode periode.</small>
                                </div>
                                <div class="callout callout-warning mb-0">
                                    Query material sudah diubah tanpa `TempDataCP` dan `TempSubTotal`. Perhitungan `cost_meter` dilakukan langsung di query.
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-success" id="btnFetchMaterial">
                                    <i class="fas fa-flask mr-1"></i> Tarik Material Obat
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h3 class="card-title">Tarik Rekap</h3>
                        </div>
                        <form id="rekapForm">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="kode_periode_rekap">Kode Periode</label>
                                    <input type="text" class="form-control" id="kode_periode_rekap" name="kode_periode" placeholder="Pilih kode periode yang sudah selesai material">
                                    <small class="form-text text-muted">Rekap akan mengambil data dari `MKO_materialobat` dan membentuk hasil seperti PivotTable.</small>
                                </div>
                                <div class="callout callout-info mb-0">
                                    Format hasil rekap:
                                    <br>`Label Jual | Color Name | Cus Color | Color Code | rtgname | Disperse | Reactive | Grand Total`
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-info" id="btnFetchRekap">
                                    <i class="fas fa-table mr-1"></i> Tarik Rekap
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Hasil Proses</h3>
                        </div>
                        <div class="card-body">
                            <div id="resultBox" class="alert alert-light border mb-0">
                                Belum ada proses dijalankan.
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Riwayat Kode Periode</h3>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Kode Periode</th>
                                        <th>Periode</th>
                                        <th>Raw</th>
                                        <th>Material</th>
                                        <th>Rekap</th>
                                        <th>Dibuat</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$recentPeriods): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Belum ada data.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentPeriods as $period): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($period['kode_periode']) ?></td>
                                                <td>
                                                    <?= htmlspecialchars(formatDisplayDate($period['period_start'])) ?>
                                                    <br>
                                                    s/d <?= htmlspecialchars(formatDisplayDate($period['period_end'])) ?>
                                                </td>
                                                <td><?= (int) $period['raw_count'] ?></td>
                                                <td><?= (int) $period['material_count'] ?></td>
                                                <td><?= (int) $period['rekap_count'] ?></td>
                                                <td><?= htmlspecialchars(formatDisplayDate($period['created_at'])) ?></td>
                                                <td>
                                                    <button
                                                        type="button"
                                                        class="btn btn-xs btn-outline-primary use-period-btn"
                                                        data-kode="<?= htmlspecialchars($period['kode_periode']) ?>"
                                                        data-material-count="<?= (int) $period['material_count'] ?>"
                                                        data-rekap-count="<?= (int) $period['rekap_count'] ?>"
                                                    >
                                                        Pakai
                                                    </button>
                                                    <a
                                                        href="view_periode.php?kode_periode=<?= urlencode($period['kode_periode']) ?>"
                                                        class="btn btn-xs btn-outline-info"
                                                    >
                                                        View
                                                    </a>
                                                    <a
                                                        href="export_periode_excel.php?kode_periode=<?= urlencode($period['kode_periode']) ?>"
                                                        class="btn btn-xs btn-outline-success"
                                                    >
                                                        Export
                                                    </a>
                                                    <button
                                                        type="button"
                                                        class="btn btn-xs btn-outline-danger delete-period-btn"
                                                        data-kode="<?= htmlspecialchars($period['kode_periode']) ?>"
                                                    >
                                                        Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../../../includes/footer.php'; ?>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
function setLoading(button, isLoading, text) {
    if (!button) return;
    button.disabled = isLoading;
    if (isLoading) {
        button.dataset.originalHtml = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> ' + text;
    } else if (button.dataset.originalHtml) {
        button.innerHTML = button.dataset.originalHtml;
    }
}

function renderResult(type, html) {
    var el = document.getElementById('resultBox');
    el.className = 'alert alert-' + type + ' border mb-0';
    el.innerHTML = html;
}

document.getElementById('rawdataForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    var button = document.getElementById('btnFetchRaw');
    setLoading(button, true, 'Memproses raw data');

    try {
        var response = await fetch('fetch_rawdata.php', {
            method: 'POST',
            body: new FormData(this)
        });
        var data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Gagal tarik raw data.');
        }

        document.getElementById('kode_periode').value = data.kode_periode;
        document.getElementById('kode_periode_rekap').value = data.kode_periode;
        renderResult('success',
            '<strong>Raw data berhasil disimpan.</strong><br>' +
            'Kode periode: <strong>' + data.kode_periode + '</strong><br>' +
            'Jumlah row: <strong>' + data.count + '</strong><br>' +
            'Jumlah prdnmbr unik: <strong>' + data.prdnmbr_count + '</strong>'
        );
        Swal.fire('Sukses', 'Raw data berhasil disimpan ke mko_rawdata.', 'success')
            .then(function () { window.location.reload(); });
    } catch (error) {
        renderResult('danger', '<strong>Proses gagal.</strong><br>' + error.message);
        Swal.fire('Error', error.message, 'error');
    } finally {
        setLoading(button, false);
    }
});

document.getElementById('materialForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    var button = document.getElementById('btnFetchMaterial');
    setLoading(button, true, 'Memproses material');

    try {
        var payload = new URLSearchParams(new FormData(this));
        var response = await fetch('fetch_materialobat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: payload.toString()
        });
        var data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Gagal tarik material obat.');
        }

        renderResult('success',
            '<strong>Material obat berhasil disimpan.</strong><br>' +
            'Kode periode: <strong>' + data.kode_periode + '</strong><br>' +
            'Jumlah row: <strong>' + data.count + '</strong><br>' +
            'Jumlah prdnmbr unik: <strong>' + data.prdnmbr_count + '</strong>'
        );
        Swal.fire('Sukses', 'Material obat berhasil disimpan ke MKO_materialobat.', 'success')
            .then(function () { window.location.reload(); });
    } catch (error) {
        renderResult('danger', '<strong>Proses gagal.</strong><br>' + error.message);
        Swal.fire('Error', error.message, 'error');
    } finally {
        setLoading(button, false);
    }
});

document.getElementById('rekapForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    var button = document.getElementById('btnFetchRekap');
    setLoading(button, true, 'Memproses rekap');

    try {
        var payload = new URLSearchParams(new FormData(this));
        var response = await fetch('fetch_rekap.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: payload.toString()
        });
        var data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Gagal tarik rekap.');
        }

        renderResult('success',
            '<strong>Rekap berhasil disimpan.</strong><br>' +
            'Kode periode: <strong>' + data.kode_periode + '</strong><br>' +
            'Jumlah row rekap: <strong>' + data.count + '</strong>'
        );
        Swal.fire('Sukses', 'Rekap berhasil disimpan ke MKO_rekap.', 'success')
            .then(function () { window.location.reload(); });
    } catch (error) {
        renderResult('danger', '<strong>Proses gagal.</strong><br>' + error.message);
        Swal.fire('Error', error.message, 'error');
    } finally {
        setLoading(button, false);
    }
});

document.querySelectorAll('.use-period-btn').forEach(function (button) {
    button.addEventListener('click', function () {
        var kode = this.dataset.kode || '';
        var materialCount = parseInt(this.dataset.materialCount || '0', 10);
        var rekapCount = parseInt(this.dataset.rekapCount || '0', 10);

        if (materialCount > 0 && rekapCount > 0) {
            document.getElementById('kode_periode').value = '';
            document.getElementById('kode_periode_rekap').value = '';
            renderResult('warning', 'Kode periode <strong>' + kode + '</strong> sudah diproses untuk material obat dan rekap.');
            Swal.fire('Info', 'Kode periode ini sudah diproses di Material Obat dan Rekap.', 'info');
            return;
        }

        if (materialCount > 0) {
            document.getElementById('kode_periode').value = '';
            document.getElementById('kode_periode_rekap').value = kode;
            renderResult('info', 'Kode periode <strong>' + kode + '</strong> sudah selesai material obat, silakan lanjut ke proses rekap.');
            return;
        }

        document.getElementById('kode_periode').value = kode;
        document.getElementById('kode_periode_rekap').value = '';
        renderResult('info', 'Kode periode <strong>' + kode + '</strong> dipilih untuk proses material obat.');
    });
});

document.querySelectorAll('.delete-period-btn').forEach(function (button) {
    button.addEventListener('click', function () {
        var kode = this.dataset.kode || '';
        if (!kode) return;

        Swal.fire({
            title: 'Hapus Kode Periode?',
            html: 'Semua data untuk <strong>' + kode + '</strong> di `mko_rawdata`, `MKO_materialobat`, dan `MKO_rekap` akan dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc3545'
        }).then(async function (result) {
            if (!result.isConfirmed) return;

            try {
                var payload = new URLSearchParams();
                payload.set('kode_periode', kode);

                var response = await fetch('delete_periode.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: payload.toString()
                });
                var data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Gagal menghapus data periode.');
                }

                renderResult(
                    'success',
                    '<strong>Data periode berhasil dihapus.</strong><br>' +
                    'Kode periode: <strong>' + data.kode_periode + '</strong><br>' +
                    'mko_rawdata: <strong>' + data.deleted.mko_rawdata + '</strong><br>' +
                    'MKO_materialobat: <strong>' + data.deleted.MKO_materialobat + '</strong><br>' +
                    'MKO_rekap: <strong>' + data.deleted.MKO_rekap + '</strong>'
                );

                Swal.fire('Sukses', 'Data periode berhasil dihapus.', 'success')
                    .then(function () { window.location.reload(); });
            } catch (error) {
                renderResult('danger', '<strong>Proses gagal.</strong><br>' + error.message);
                Swal.fire('Error', error.message, 'error');
            }
        });
    });
});
</script>
