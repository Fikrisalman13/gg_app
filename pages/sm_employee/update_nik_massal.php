<?php
session_start();
ob_start();
include '../../koneksi.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

// Authentication check
if (!isset($_SESSION['UserName'])) {
    $_SESSION['error'] = "Silakan login terlebih dahulu!";
    header('Location: /gg_app/login.php');
    exit;
}

$themeColor = $_SESSION['Theme'] ?? 'primary';

if (!$conn) {
    die("Koneksi gagal: " . print_r(sqlsrv_errors(), true));
}

date_default_timezone_set('Asia/Jakarta');

// Permission check
$groupId = $_SESSION['GroupId'];
$menuId = 43; // Asumsi menu "Employee" juga membawahi fitur update NIK

$sql = "SELECT CanView, CanEdit FROM dbo.SMGroupTrustee WHERE GroupId = ? AND MenuId = ?";
$params = [$groupId, $menuId];
$stmt = sqlsrv_query($conn, $sql, $params);
$canEdit = false;

if ($stmt === false) {
    die("Kesalahan hak akses: " . print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$canEdit = $row && $row['CanEdit'] == 1;
sqlsrv_free_stmt($stmt);

if (!$canEdit) {
    $_SESSION['error'] = "Anda tidak memiliki hak untuk mengubah data NIK secara massal.";
    header('Location: emp.php');
    exit;
}
ob_end_flush();
?>

<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">Update NIK Massal</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Beranda</a></li>
                        <li class="breadcrumb-item"><a href="emp.php">Manajemen Karyawan</a></li>
                        <li class="breadcrumb-item active">Update NIK</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
            <div class="card card-<?= htmlspecialchars($themeColor) ?> card-outline">
                <div class="card-header bg-<?= htmlspecialchars($themeColor) ?> text-white">
                    <h3 class="card-title">
                        <i class="fas fa-id-badge mr-2"></i> Update NIK Berdasarkan Golongan
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="icon fas fa-info"></i> Petunjuk</h5>
                        Pilih kategori karyawan untuk meng-generate NIK baru. 
                        <strong>Proses ini akan me-reset sequence NIK menjadi 001 untuk masing-masing kategori pada bulan dan tahun berjalan.</strong>
                    </div>

                    <form id="formPreviewNIK">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="kategori_golongan">Kategori Golongan Karyawan</label>
                                    <select class="form-control" id="kategori_golongan" name="kategori_golongan" required>
                                        <option value="">-- Pilih Kategori --</option>
                                        <option value="magang">Magang 1 & 2</option>
                                        <option value="harian_lepas">Harian Lepas</option>
                                        <option value="reguler">Reguler</option>
                                        <option value="clerk_staff">Clerk & Staff</option>
                                    </select>
                                    <small id="keteranganReguler" class="form-text text-danger" style="display: none;">
                                        *Karyawan Tetap, Outsourcing, dan Surabaya <strong>TIDAK</strong> akan diupdate.
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Dibungkus dalam satu div agar bisa di hide/show -->
                            <div class="col-md-9" id="formatInputs" style="display: none;">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="prefix_nik">Awalan NIK</label>
                                            <input type="text" class="form-control" id="prefix_nik" name="prefix_nik" placeholder="Cth: 34" value="">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="tahun_nik">Tahun</label>
                                            <input type="text" class="form-control" id="tahun_nik" name="tahun_nik" placeholder="Cth: <?= date('y') ?>" value="<?= date('y') ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="bulan_nik">Bulan</label>
                                            <input type="text" class="form-control" id="bulan_nik" name="bulan_nik" placeholder="Cth: <?= date('m') ?>" value="<?= date('m') ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="start_sequence">Urutan Mulai</label>
                                            <input type="number" class="form-control" id="start_sequence" name="start_sequence" min="1" value="1" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-center mt-3">
                                        <button type="submit" class="btn btn-<?= htmlspecialchars($themeColor) ?> w-100" id="btnPreview" title="Tampilkan Preview NIK">
                                            <i class="fas fa-search"></i> Preview
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </form>

                    <div id="previewContainer" style="display:none; margin-top: 20px;">
                        <hr>
                        <h4>Preview Perubahan NIK</h4>
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered" style="background-color: #ffffff;" id="tablePreview">
                                <thead>
                                    <tr>
                                        <th style="width: 5%">No</th>
                                        <th>Nama Karyawan</th>
                                        <th>Departemen</th>
                                        <th>Bagian</th>
                                        <th>Golongan</th>
                                        <th class="text-danger">NIK Lama</th>
                                        <th class="text-success">NIK Baru</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data via AJAX -->
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 text-right">
                            <button type="button" class="btn btn-success" id="btnSimpanUpdate">
                                <i class="fas fa-save mr-1"></i> Simpan Update NIK
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================
    IMPORT FOOTER & JS
======================================================= -->
<?php include '../../includes/footer.php'; ?>
<!-- DataTables -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<!-- SheetJS with Style Support (Offline) -->
<script src="/gg_app/plugins/js/xlsx.bundle.js"></script>

<script>
$(document).ready(function() {
    let previewData = [];
    let table = $('#tablePreview').DataTable({
        "paging": true,
        "lengthChange": false,
        "searching": true,
        "ordering": false,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "language": {
            "emptyTable": "Tidak ada data karyawan yang perlu diupdate untuk kategori ini."
        }
    });

    // Tampilkan keterangan reguler dan inputs jika golongan dipilih
    $('#kategori_golongan').on('change', function() {
        let val = $(this).val();
        
        if (val) {
            $('#formatInputs').fadeIn(); // Munculkan field input NIK
        } else {
            $('#formatInputs').hide();
        }

        if (val === 'reguler' || val === 'clerk_staff') {
            if (val === 'reguler') {
                $('#keteranganReguler').show();
            } else {
                $('#keteranganReguler').hide();
            }
            $('#prefix_nik').val('20'); // Default awalan 20 untuk reguler dan clerk&staff
            $('#tahun_nik').val('<?= date('y') ?>'); // Tahun 2 digit
        } else {
            $('#keteranganReguler').hide();
            $('#tahun_nik').val('<?= date('y') ?>');
            
            if (val === 'magang') {
                $('#prefix_nik').val('34');
            } else if (val === 'harian_lepas') {
                $('#prefix_nik').val('55');
            } else {
                $('#prefix_nik').val('');
            }
        }
    });

    // Handle form submit untuk preview
    $('#formPreviewNIK').on('submit', function(e) {
        e.preventDefault();
        let kategori = $('#kategori_golongan').val();
        let prefix = $('#prefix_nik').val();
        let tahun = $('#tahun_nik').val();
        let bulan = $('#bulan_nik').val();
        let sequence = $('#start_sequence').val();
        
        if (!kategori || !tahun || !bulan || !sequence) {
            Swal.fire('Peringatan', 'Silakan lengkapi semua input format NIK!', 'warning');
            return;
        }

        let btn = $('#btnPreview');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: 'process_preview_nik.php',
            type: 'POST',
            data: { 
                kategori: kategori,
                prefix: prefix,
                tahun: tahun,
                bulan: bulan,
                sequence: sequence
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    previewData = response.data;
                    table.clear();
                    
                    if (previewData.length > 0) {
                        let rows = [];
                        $.each(previewData, function(index, item) {
                            rows.push([
                                index + 1,
                                item.nama_lengkap,
                                item.dept,
                                item.bagian,
                                item.golongan,
                                '<span class="text-danger">' + item.nik_lama + '</span>',
                                '<strong><span class="text-success">' + item.nik_baru + '</span></strong>'
                            ]);
                        });
                        table.rows.add(rows).draw();
                        $('#previewContainer').slideDown();
                    } else {
                        table.draw(); // Gambar tabel kosong
                        $('#previewContainer').slideDown();
                        $('#btnSimpanUpdate').prop('disabled', true);
                    }
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Terjadi kesalahan saat memuat data preview.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-search"></i> Preview');
            }
        });
    });

    // Handle Simpan Update
    $('#btnSimpanUpdate').on('click', function() {
        if (previewData.length === 0) {
            Swal.fire('Peringatan', 'Tidak ada data untuk disimpan!', 'warning');
            return;
        }

        Swal.fire({
            title: 'Konfirmasi Update',
            text: "Anda yakin ingin menyimpan perubahan NIK untuk " + previewData.length + " karyawan?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Simpan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                let btn = $(this);
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

                // Format XLSX Export
                function exportToXlsxAndRedirect(data) {
                    // Buat array data untuk SheetJS
                    let ws_data = [
                        ["No", "Nama Lengkap", "Departemen", "Bagian", "Golongan", "NIK Lama", "NIK Baru"]
                    ];
                    
                    data.forEach(function(item, index) {
                        ws_data.push([
                            index + 1,
                            item.nama_lengkap || '',
                            item.dept || '',
                            item.bagian || '',
                            item.golongan || '',
                            item.nik_lama || '', // String murni, SheetJS akan mengurusnya
                            item.nik_baru || ''
                        ]);
                    });

                    // Buat workbook dan worksheet
                    let wb = XLSX.utils.book_new();
                    let ws = XLSX.utils.aoa_to_sheet(ws_data);

                    // Atur Lebar Kolom
                    ws['!cols'] = [
                        {wch: 5},   // No
                        {wch: 35},  // Nama Lengkap
                        {wch: 25},  // Departemen
                        {wch: 25},  // Bagian
                        {wch: 20},  // Golongan
                        {wch: 15},  // NIK Lama
                        {wch: 15}   // NIK Baru
                    ];

                    // Atur Style Header (Baris 1)
                    let range = XLSX.utils.decode_range(ws['!ref']);
                    for (let C = range.s.c; C <= range.e.c; ++C) {
                        let address = XLSX.utils.encode_cell({r: 0, c: C});
                        if (!ws[address]) continue;
                        ws[address].s = {
                            font: { 
                                bold: true, 
                                color: { rgb: "FFFFFF" } 
                            },
                            fill: { 
                                fgColor: { rgb: "4B5563" } // Abu-abu gelap / Slate
                            },
                            alignment: { 
                                horizontal: "center", 
                                vertical: "center" 
                            },
                            border: {
                                top: { style: "thin", color: { rgb: "000000"} },
                                bottom: { style: "thin", color: { rgb: "000000"} },
                                left: { style: "thin", color: { rgb: "000000"} },
                                right: { style: "thin", color: { rgb: "000000"} }
                            }
                        };
                    }

                    // Tambahkan border untuk semua data jg biar rapi
                    for (let R = 1; R <= range.e.r; ++R) {
                        for (let C = range.s.c; C <= range.e.c; ++C) {
                            let address = XLSX.utils.encode_cell({r: R, c: C});
                            if (!ws[address]) continue;
                            if (!ws[address].s) ws[address].s = {};
                            ws[address].s.border = {
                                top: { style: "thin", color: { rgb: "D1D5DB"} },
                                bottom: { style: "thin", color: { rgb: "D1D5DB"} },
                                left: { style: "thin", color: { rgb: "D1D5DB"} },
                                right: { style: "thin", color: { rgb: "D1D5DB"} }
                            };
                        }
                    }

                    // Tambahkan sheet ke workbook
                    XLSX.utils.book_append_sheet(wb, ws, "Rekap Update NIK");

                    // Ekspor ke file .xlsx
                    let dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, "");
                    XLSX.writeFile(wb, "Laporan_Update_NIK_Massal_" + dateStr + ".xlsx");

                    // Tunggu sesaat sebelum redirect agar unduhan sempat dimulai
                    setTimeout(function() {
                        window.location.href = 'emp.php';
                    }, 2000);
                }

                const chunkSize = 20; // Proses 20 data per request agar tidak berat
                let currentIndex = 0;
                let totalData = previewData.length;
                let successCount = 0;
                let failedCount = 0;

                Swal.fire({
                    title: 'Memproses Update...',
                    html: `Mengupdate <b id="swal-update-count" class="text-primary">0</b> data / <b>${totalData}</b> data<br><small class="text-muted mt-2 d-block">Mohon jangan tutup halaman ini...</small>`,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                        processNextChunk();
                    }
                });

                function processNextChunk() {
                    let chunk = previewData.slice(currentIndex, currentIndex + chunkSize);
                    
                    if (chunk.length === 0) {
                        btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Update NIK');
                        
                        let msg = `Berhasil mengupdate ${successCount} data.`;
                        if (failedCount > 0) {
                            msg += ` Gagal mengupdate ${failedCount} data.`;
                        }

                        Swal.fire({
                            icon: failedCount === 0 ? 'success' : 'warning',
                            title: 'Proses Selesai!',
                            text: msg + ' File rekap (Excel) akan didownload otomatis.',
                            showConfirmButton: true,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            exportToXlsxAndRedirect(previewData);
                        });
                        return;
                    }

                    $.ajax({
                        url: 'process_update_nik.php',
                        type: 'POST',
                        data: { data_update: JSON.stringify(chunk) },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                successCount += chunk.length;
                            } else {
                                failedCount += chunk.length;
                            }
                        },
                        error: function() {
                            failedCount += chunk.length;
                        },
                        complete: function() {
                            currentIndex += chunkSize;
                            let currentDisplay = Math.min(currentIndex, totalData);
                            $('#swal-update-count').text(currentDisplay);
                            
                            // Lanjut ke batch berikutnya
                            processNextChunk();
                        }
                    });
                }
            }
        });
    });
});
</script>
