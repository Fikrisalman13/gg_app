<?php
declare(strict_types=1);
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$theme = $_SESSION['Theme'] ?? 'primary';

// Query dinamis untuk testing
$sql = "
SELECT 
    c.category_name AS jenis, 
    d.id, 
    COALESCE(
        (SELECT TOP 1 field_value FROM dr_doc_values v JOIN dr_fields f ON v.field_id = f.id WHERE v.document_id = d.id AND f.is_show_on_table = 1 ORDER BY f.sort_order ASC), 
        'Dokumen #' + CAST(d.id AS VARCHAR)
    ) AS identitas, 
    d.expire_date, 
    d.no_whatsapp, 
    d.email_reminder, 
    d.created_by 
FROM dr_documents d
JOIN dr_categories c ON d.category_id = c.id
ORDER BY d.expire_date ASC
";
$stmt = sqlsrv_query($conn, $sql);
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Dev Menu: Test Reminder</h1>
            <p class="text-muted">Gunakan halaman ini untuk mengetes pengiriman notifikasi tanpa menunggu tanggal kadaluarsa.</p>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-danger">
                <div class="card-header">
                    <h3 class="card-title">Daftar Dokumen (Semua Jenis)</h3>
                </div>
                <div class="card-body">
                    <table id="devTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Jenis</th>
                                <th>Identitas</th>
                                <th>Expire</th>
                                <th>WA</th>
                                <th>Email</th>
                                <th>User (CreatedBy)</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td class="text-capitalize"><?= $r['jenis'] ?></td>
                                <td><?= htmlspecialchars((string)$r['identitas']) ?></td>
                                <td><?= $r['expire_date'] ? $r['expire_date']->format('Y-m-d') : '-' ?></td>
                                <td><?= htmlspecialchars((string)$r['no_whatsapp']) ?></td>
                                <td><?= htmlspecialchars((string)$r['email_reminder']) ?></td>
                                <td><?= htmlspecialchars((string)$r['created_by']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning btn-test" 
                                            data-jenis="<?= $r['jenis'] ?>" 
                                            data-id="<?= $r['id'] ?>">
                                        <i class="fas fa-paper-plane"></i> Test Send
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<!-- DataTables & Plugins -->
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="/gg_app/plugins/AdminLTE-3.2.0/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="/gg_app/plugins/js/notifikasi/sweetalert2@11.js"></script>

<script>
$(function() {
    console.log('Checking jQuery and DataTables...');
    if (typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables plugin is missing!');
        return;
    }

    $('#devTable').DataTable({
        "responsive": true,
        "autoWidth": false,
        "order": [[2, "asc"]]
    });

    $(document).on('click', '.btn-test', function() {
        const btn = $(this);
        const jenis = btn.data('jenis');
        const id = btn.data('id');

        Swal.fire({
            title: 'Kirim Test?',
            text: "Reminder akan dikirim ke Email & WA yang tertera.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Kirim!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
                
                $.ajax({
                    url: 'services/ajax_test_reminder.php',
                    method: 'POST',
                    data: { jenis: jenis, id: id },
                    dataType: 'json',
                    success: function(res) {
                        btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Test Send');
                        if (res.status === 'success') {
                            Swal.fire('Berhasil!', res.message, 'success');
                        } else if (res.status === 'warning') {
                            Swal.fire('Peringatan', res.message, 'warning');
                        } else {
                            Swal.fire('Gagal!', res.message, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Test Send');
                        Swal.fire('Error!', 'Terjadi kesalahan sistem: ' + error, 'error');
                        console.error(xhr.responseText);
                    }
                });
            }
        });
    });
});
</script>
