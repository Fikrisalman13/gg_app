<?php
// edit_asset.php (final versi)
session_start();
ob_start();

date_default_timezone_set('Asia/Jakarta');

require_once '../../koneksi.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// ---------- AUTH ----------
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}
$themeColor = $_SESSION['Theme'] ?? 'primary';

// Check DB connection
if (!$conn) {
    die("Koneksi ke database gagal: " . print_r(sqlsrv_errors(), true));
}

// Permission check
$groupId = $_SESSION['GroupId'];
$menuId = 56;
$sql = "SELECT CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die("Kesalahan saat mengambil hak akses: " . print_r(sqlsrv_errors(), true));
}
$canEdit = false;
if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $canEdit = $row['CanEdit'] == 1;
}
sqlsrv_free_stmt($stmt);
if (!$canEdit) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengedit data.";
    header('Location: asset.php');
    exit;
}

// Validate id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID Asset tidak valid.";
    header('Location: asset.php');
    exit;
}
$assetId = (int) $_GET['id'];

// Fetch asset
$sql = "SELECT * FROM dbo.m_asset WHERE id_asset = ?";
$params = [$assetId];
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die("Gagal mengambil data asset: " . print_r(sqlsrv_errors(), true));
}
$assetData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);
if (!$assetData) {
    $_SESSION['error'] = "Asset tidak ditemukan.";
    header('Location: asset.php');
    exit;
}

// Fetch dropdowns
$dropdownQueries = [
    'kodeAssets' => "SELECT id_kode, kode_asset FROM dbo.m_kode_asset ORDER BY kode_asset",
    'kategori'   => "SELECT id_kategori, nama_kategori FROM dbo.m_kategori ORDER BY nama_kategori",
    'merks'      => "SELECT id_merk, nama_merk FROM dbo.m_merk ORDER BY nama_merk",
    'lokasi'     => "SELECT id_lokasi, nama_lokasi FROM dbo.m_lokasi ORDER BY nama_lokasi",
    'status'     => "SELECT id_status, nama_status FROM dbo.m_status ORDER BY nama_status",
    'karyawan'   => "SELECT id_emp, nama_lengkap FROM dbo.m_emp ORDER BY nama_lengkap"
];

foreach ($dropdownQueries as $var => $query) {
    ${$var} = [];
    $stmt = sqlsrv_query($conn, $query);
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            ${$var}[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}

// Fetch tipe for selected merk
$tipes = [];
if (!empty($assetData['id_merk'])) {
    $sql = "SELECT id_tipe, nama_tipe FROM dbo.m_tipe WHERE id_merk = ? ORDER BY nama_tipe";
    $stmt = sqlsrv_query($conn, $sql, [$assetData['id_merk']]);
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $tipes[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
}

// Helper functions
function getStatusNameById($statusList, $id) {
    foreach ($statusList as $s) {
        if ($s['id_status'] == $id) return $s['nama_status'];
    }
    return '';
}
function formatDateForDisplay($value) {
    if (empty($value)) return '';
    if ($value instanceof DateTime) return $value->format('d-m-Y');
    $d = date_create($value);
    return $d ? $d->format('d-m-Y') : '';
}
function getEmpNameById($conn, $id_emp) {
    if (empty($id_emp)) return '-';
    $sql = "SELECT nama_lengkap FROM dbo.m_emp WHERE id_emp = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id_emp]);
    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        sqlsrv_free_stmt($stmt);
        return $row['nama_lengkap'];
    }
    return '-';
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $id_kode = filter_input(INPUT_POST, 'id_kode', FILTER_VALIDATE_INT);
    $id_kategori = filter_input(INPUT_POST, 'id_kategori', FILTER_VALIDATE_INT);
    $id_merk = filter_input(INPUT_POST, 'id_merk', FILTER_VALIDATE_INT);
    $id_tipe = filter_input(INPUT_POST, 'id_tipe', FILTER_VALIDATE_INT);
    $id_lokasi = filter_input(INPUT_POST, 'id_lokasi', FILTER_VALIDATE_INT);
    $id_status = filter_input(INPUT_POST, 'id_status', FILTER_VALIDATE_INT);
    $id_emp = filter_input(INPUT_POST, 'id_emp', FILTER_VALIDATE_INT) ?: null;

    $serial_number = trim(filter_input(INPUT_POST, 'serial_number', FILTER_SANITIZE_STRING));
    $tanggal_pembelian = trim(filter_input(INPUT_POST, 'tanggal_pembelian', FILTER_SANITIZE_STRING));
    $no_po = trim(filter_input(INPUT_POST, 'no_po', FILTER_SANITIZE_STRING));
    $keterangan = trim(filter_input(INPUT_POST, 'keterangan', FILTER_SANITIZE_STRING));

    $tgl_rusak = trim(filter_input(INPUT_POST, 'tgl_rusak', FILTER_SANITIZE_STRING));
    $ket_rusak = trim(filter_input(INPUT_POST, 'ket_rusak', FILTER_SANITIZE_STRING));

    $upduser = $_SESSION['UserName'];
    $errorMessages = [];

    // Required validation
    if (empty($id_kode)) $errorMessages[] = "Jenis Asset harus dipilih";
    if (empty($id_kategori)) $errorMessages[] = "Kategori harus dipilih";
    if (empty($id_merk)) $errorMessages[] = "Merk harus dipilih";
    if (empty($id_tipe)) $errorMessages[] = "Tipe harus dipilih";
    if (empty($id_lokasi)) $errorMessages[] = "Lokasi harus dipilih";
    if (empty($id_status)) $errorMessages[] = "Status harus dipilih";
    if (empty($serial_number)) $errorMessages[] = "Serial number tidak boleh kosong";

    // tanggal pembelian format
    if (!empty($tanggal_pembelian) && !DateTime::createFromFormat('d-m-Y', $tanggal_pembelian)) {
        $errorMessages[] = "Format tanggal pembelian tidak valid (harus dd-mm-yyyy)";
    }

    // status name
    $statusNama = getStatusNameById($status, $id_status);

    // If Broken -> tgl_rusak and ket_rusak wajib
    if (strtolower($statusNama) === 'broken') {
        if (empty($tgl_rusak)) $errorMessages[] = "Tanggal rusak wajib diisi jika status Broken.";
        if (empty($ket_rusak)) $errorMessages[] = "Keterangan rusak wajib diisi jika status Broken.";
        if (!empty($tgl_rusak) && !DateTime::createFromFormat('d-m-Y', $tgl_rusak)) {
            $errorMessages[] = "Format tanggal rusak tidak valid (harus dd-mm-yyyy)";
        }
    } else {
        // if not broken, clear these values
        $tgl_rusak = '';
        $ket_rusak = '';
    }

    if (empty($errorMessages)) {
        $tanggal_pembelian_sql = !empty($tanggal_pembelian) ? DateTime::createFromFormat('d-m-Y', $tanggal_pembelian)->format('Y-m-d') : null;
        $tgl_rusak_sql = !empty($tgl_rusak) ? DateTime::createFromFormat('d-m-Y', $tgl_rusak)->format('Y-m-d') : null;

        // Old values for history
        $oldStatusId = $assetData['id_status'];
        $oldStatusNama = getStatusNameById($status, $oldStatusId);
        $oldEmpId = $assetData['id_emp'];
        $oldEmpNama = getEmpNameById($conn, $oldEmpId);

        // Update asset
        $sql = "UPDATE dbo.m_asset SET
                    id_kode = ?, 
                    id_kategori = ?, 
                    id_merk = ?, 
                    id_tipe = ?, 
                    id_lokasi = ?, 
                    id_status = ?, 
                    id_emp = ?, 
                    serial_number = ?, 
                    tanggal_pembelian = ?, 
                    no_po = ?, 
                    keterangan = ?, 
                    tgl_rusak = ?, 
                    ket_rusak = ?, 
                    upddate = GETDATE(), 
                    upduser = ?
                WHERE id_asset = ?";
        $params = [
            $id_kode, $id_kategori, $id_merk, $id_tipe, $id_lokasi,
            $id_status, $id_emp, $serial_number,
            $tanggal_pembelian_sql, $no_po, $keterangan,
            $tgl_rusak_sql, $ket_rusak, $upduser,
            $assetId
        ];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $_SESSION['error'] = "Gagal mengupdate asset: " . print_r(sqlsrv_errors(), true);
        } else {
            // Prepare history
            $jenisPerubahan = [];
            $note = "";

            if ($oldStatusId != $id_status) {
                $jenisPerubahan[] = 'Status';
                $note .= "Status: $oldStatusNama → $statusNama";
                if (strtolower($statusNama) === 'broken') {
                    $note .= "; Tgl rusak: " . ($tgl_rusak_sql ?: '-') . "; Ket: " . ($ket_rusak ?: '-');
                }
            }

            if ($oldEmpId != $id_emp) {
                $jenisPerubahan[] = 'Pegawai';
                if (!empty($note)) $note .= "; ";
                $newEmpNama = getEmpNameById($conn, $id_emp);
                $note .= "Pegawai: $oldEmpNama → $newEmpNama";
            }

            if (!empty($note)) {
                $historySql = "INSERT INTO dbo.asset_history
                                (id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at)
                               VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
                $historyParams = [
                    $assetId,
                    $oldStatusNama,
                    $statusNama,
                    $note,
                    implode('; ', $jenisPerubahan),
                    $upduser
                ];
                $hstmt = @sqlsrv_query($conn, $historySql, $historyParams);
                if ($hstmt === false) {
                    $_SESSION['warning_history'] = "Update sukses, tapi gagal menyimpan history: " . print_r(sqlsrv_errors(), true);
                } else {
                    sqlsrv_free_stmt($hstmt);
                }
            }

            $_SESSION['success'] = "Asset berhasil diupdate.";
            header('Location: asset.php');
            exit;
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errorMessages);
    }
}

// Prepare display values
$tanggal_pembelian_display = formatDateForDisplay($assetData['tanggal_pembelian']);
$tgl_rusak_display = formatDateForDisplay($assetData['tgl_rusak']);
$ket_rusak_display = htmlspecialchars($assetData['ket_rusak'] ?? '', ENT_QUOTES);

// Fetch history for display
$historyRows = [];
$hSql = "SELECT TOP 100 id_history, id_asset, old_status, new_status, note, jenis_perubahan, created_by, created_at
         FROM dbo.asset_history
         WHERE id_asset = ?
         ORDER BY created_at DESC";
$hstmt = sqlsrv_query($conn, $hSql, [$assetId]);
if ($hstmt !== false) {
    while ($hr = sqlsrv_fetch_array($hstmt, SQLSRV_FETCH_ASSOC)) {
        $historyRows[] = $hr;
    }
    sqlsrv_free_stmt($hstmt);
}


?>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"><h1>Edit Asset</h1></div>
          <div class="col-sm-6"><ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
              <li class="breadcrumb-item"><a href="asset.php">Asset</a></li>
              <li class="breadcrumb-item active">Edit Asset</li>
          </ol></div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
            <div class="card">
              <div class="card-header bg-<?php echo htmlspecialchars($themeColor); ?> text-white">
                <h3 class="card-title">Form Edit Asset</h3>
              </div>
              <form method="POST" autocomplete="off" id="form-edit-asset">
                <div class="card-body">
                  <?php if (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                  <?php endif; ?>
                  <?php if (!empty($_SESSION['warning_history'])): ?>
                    <div class="alert alert-warning"><?= $_SESSION['warning_history']; unset($_SESSION['warning_history']); ?></div>
                  <?php endif; ?>
                  <?php if (!empty($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                  <?php endif; ?>

                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>Jenis Asset <span class="required-star">*</span></label>
                        <select id="id_kode" name="id_kode" class="form-control" required>
                          <option value="">-- Pilih Jenis Asset --</option>
                          <?php foreach ($kodeAssets as $k): ?>
                            <option value="<?= $k['id_kode'] ?>" data-kode="<?= htmlspecialchars($k['kode_asset']) ?>"
                              <?= ($assetData['id_kode'] == $k['id_kode']) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($k['kode_asset']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                        <small class="small-muted">Kode asset: <?= htmlspecialchars($assetData['kode_asset_seq']) ?></small>
                      </div>

                      <div class="form-group">
                        <label>Kategori <span class="required-star">*</span></label>
                        <select id="id_kategori" name="id_kategori" class="form-control" required>
                          <option value="">-- Pilih Kategori --</option>
                          <?php foreach ($kategori as $kat): ?>
                            <option value="<?= $kat['id_kategori'] ?>" <?= ($assetData['id_kategori'] == $kat['id_kategori']) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($kat['nama_kategori']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div class="form-group">
                        <label>Merk <span class="required-star">*</span></label>
                        <select id="id_merk" name="id_merk" class="form-control" required>
                          <option value="">-- Pilih Merk --</option>
                          <?php foreach ($merks as $m): ?>
                            <option value="<?= $m['id_merk'] ?>" <?= ($assetData['id_merk'] == $m['id_merk']) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($m['nama_merk']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div class="form-group">
                        <label>Tipe <span class="required-star">*</span></label>
                        <select id="id_tipe" name="id_tipe" class="form-control" required>
                          <option value="">-- Pilih Tipe --</option>
                          <?php foreach ($tipes as $ti): ?>
                            <option value="<?= $ti['id_tipe'] ?>" <?= ($assetData['id_tipe'] == $ti['id_tipe']) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($ti['nama_tipe']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div class="form-group">
                        <label>Serial Number <span class="required-star">*</span></label>
                        <input type="text" id="serial_number" name="serial_number" class="form-control" maxlength="100" required
                          value="<?= htmlspecialchars($assetData['serial_number']) ?>">
                      </div>
                    </div>

                    <div class="col-md-6">
                      <div class="form-group">
                        <label>Lokasi <span class="required-star">*</span></label>
                        <select id="id_lokasi" name="id_lokasi" class="form-control" required>
                          <option value="">-- Pilih Lokasi --</option>
                          <?php foreach ($lokasi as $l): ?>
                            <option value="<?= $l['id_lokasi'] ?>" <?= ($assetData['id_lokasi'] == $l['id_lokasi']) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($l['nama_lokasi']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div class="form-group">
                        <label>Status <span class="required-star">*</span></label>
                        <select id="id_status" name="id_status" class="form-control" required>
                          <option value="">-- Pilih Status --</option>
                          <?php foreach ($status as $s): ?>
                            <option value="<?= $s['id_status'] ?>" <?= ($assetData['id_status'] == $s['id_status']) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($s['nama_status']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <!-- Tgl Rusak + Ket Rusak (hidden kecuali Broken) -->
                      <div id="tgl_rusak_group" class="form-group" style="display:none;">
                        <label>Tanggal Rusak <span class="required-star">*</span></label>
                        <div class="input-group">
                          <input type="text" id="tgl_rusak" name="tgl_rusak" class="form-control datepicker" placeholder="dd-mm-yyyy" value="<?= $tgl_rusak_display ?>">
                          <div class="input-group-append"><span class="input-group-text"><i class="far fa-calendar-alt"></i></span></div>
                        </div>
                      </div>

                      <div id="ket_rusak_group" class="form-group" style="display:none;">
                        <label>Keterangan Rusak <span class="required-star">*</span></label>
                        <textarea id="ket_rusak" name="ket_rusak" class="form-control" rows="3" placeholder="Jelaskan kerusakan"><?= $ket_rusak_display ?></textarea>
                      </div>

                      <div class="form-group">
                        <label>Pegawai</label>
                        <select id="id_emp" name="id_emp" class="form-control select2">
                          <option value="">-- Pilih Pegawai --</option>
                          <?php foreach ($karyawan as $emp): ?>
                            <option value="<?= $emp['id_emp'] ?>" <?= ($assetData['id_emp'] == $emp['id_emp']) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($emp['nama_lengkap']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div class="form-group">
                        <label>Tanggal Pembelian</label>
                        <div class="input-group">
                          <input type="text" id="tanggal_pembelian" name="tanggal_pembelian" class="form-control datepicker" placeholder="dd-mm-yyyy" value="<?= $tanggal_pembelian_display ?>">
                          <div class="input-group-append"><span class="input-group-text"><i class="far fa-calendar-alt"></i></span></div>
                        </div>
                      </div>

                      <div class="form-group">
                        <label>No PO</label>
                        <input type="text" id="no_po" name="no_po" class="form-control" maxlength="50" value="<?= htmlspecialchars($assetData['no_po']) ?>">
                      </div>
                    </div>
                  </div>

                  <div class="form-group">
                    <label>Keterangan</label>
                    <textarea id="keterangan" name="keterangan" class="form-control" rows="3" placeholder="Masukkan keterangan tambahan"><?= htmlspecialchars($assetData['keterangan']) ?></textarea>
                  </div>
                </div>

                <div class="card-footer d-flex justify-content-between">
                  <div>
                    <button type="submit" class="btn btn-<?php echo htmlspecialchars($themeColor); ?>"><i class="fas fa-save"></i> Simpan</button>
                    <a href="asset.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                  </div>
                  <div class="small-muted">Diupdate oleh: <?= htmlspecialchars($assetData['upduser'] ?? '-') ?> pada <?= isset($assetData['upddate']) ? ( $assetData['upddate'] instanceof DateTime ? $assetData['upddate']->format('d-m-Y H:i') : $assetData['upddate']) : '-' ?></div>
                </div>
              </form>
            </div>

            <!-- History card -->
            <div class="card">
              <div class="card-header bg-<?php echo htmlspecialchars($themeColor ?? 'primary'); ?> text-white">
                <h3 class="card-title">Riwayat Perubahan (Asset)</h3>
              </div>
              <div class="card-body p-0">
                <?php if (empty($historyRows)): ?>
                  <div class="p-3 small-muted">Belum ada riwayat perubahan untuk asset ini.</div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-hover table-sm">
                      <thead class="thead-light">

                        <tr>
                          <th>#</th>
                          <th>Tanggal</th>
                          <th>User</th>
                          <th>Status</th>
                          <th>Perubahan</th>
                          <th>Keterangan</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($historyRows as $h): ?>
                          <tr>
                            <td><?= htmlspecialchars($h['id_history']) ?></td>
                            <td><?= isset($h['created_at']) && $h['created_at'] instanceof DateTime ? $h['created_at']->format('d-m-Y H:i') : htmlspecialchars($h['created_at']) ?></td>
                            <td><?= htmlspecialchars($h['created_by']) ?></td>
                            <td><?= htmlspecialchars(($h['old_status'] ?: '-') . ' → ' . ($h['new_status'] ?: '-')) ?></td>
                            <td><?= htmlspecialchars($h['jenis_perubahan']) ?></td>
                            <td><?= htmlspecialchars($h['note']) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>

         

          

        </div>
      </div>
    </section>
  </div>
</div>
<!-- ===================================================
    10. IMPORT FOOTER
======================================================= -->
<?php include '../../includes/footer.php'; ?>

<!-- JS libs -->
<link rel="stylesheet" href="/gg_app/plugins/css/bootstrap-datepicker.min.css">
<script src="/gg_app/plugins/js/bootstrap-datepicker.min.js"></script>
<script src="/gg_app/plugins/js/bootstrap-datepicker.id.min.js"></script>

<script>
$(function(){
    // datepicker
    $('.datepicker').datepicker({
        format: 'dd-mm-yyyy',
        language: 'id',
        autoclose: true,
        todayHighlight: true,
        orientation: "bottom auto"
    });

    // select2
    $('.select2').select2({ theme: 'bootstrap', width: '100%' });

    // dynamic tipe load
    $('#id_merk').on('change', function(){
        var idMerk = $(this).val();
        if (!idMerk) { $('#id_tipe').html('<option value="">-- Pilih Merk terlebih dahulu --</option>'); return; }
        $.ajax({
            url: 'get_tipe_by_merk.php',
            type: 'POST',
            data: { id_merk: idMerk },
            success: function(resp){ $('#id_tipe').html(resp); },
            error: function(){ $('#id_tipe').html('<option value="">Gagal memuat tipe</option>'); }
        });
    });

    // auto-fill kategori when kode changed
    $('#id_kode').on('change', function(){
        var kode = $('#id_kode option:selected').data('kode') || '';
        if (!kode) return;
        $.ajax({
            url: 'get_kategori_by_kode.php',
            type: 'POST',
            data: { kode: kode },
            success: function(resp){
                $('#id_kategori').html(resp);
            },
            error: function(){ console.error('Gagal memuat kategori'); }
        });
    });

    function toggleBrokenFields(){
        var st = $('#id_status option:selected').text().toLowerCase();
        if (st.indexOf('broken') !== -1) {
            $('#tgl_rusak_group, #ket_rusak_group').show();
            $('#tgl_rusak, #ket_rusak').attr('required', true);
        } else {
            $('#tgl_rusak_group, #ket_rusak_group').hide();
            $('#tgl_rusak, #ket_rusak').removeAttr('required').val('');
        }
    }
    toggleBrokenFields();
    $('#id_status').on('change', toggleBrokenFields);

    // client side guard
    $('#form-edit-asset').on('submit', function(e){
        var st = $('#id_status option:selected').text().toLowerCase();
        if (st.indexOf('broken') !== -1) {
            var t = $('#tgl_rusak').val().trim();
            var k = $('#ket_rusak').val().trim();
            if (!t) { alert('Tanggal rusak wajib diisi jika status Broken.'); $('#tgl_rusak').focus(); e.preventDefault(); return false; }
            if (!k) { alert('Keterangan rusak wajib diisi jika status Broken.'); $('#ket_rusak').focus(); e.preventDefault(); return false; }
        }
        return true;
    });
});
</script>

<?php
ob_end_flush();
?>
