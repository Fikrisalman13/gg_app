<?php
// pages/resep_obat/migrate_add_kabag_role.php
// Jalankan sekali untuk menambah value 'KABAG' pada CHECK constraint kolom role_type
// di tabel dbo.resep_obat_groups.

session_start();
require_once __DIR__ . '/../../../koneksi.php';

header('Content-Type: text/html; charset=utf-8');

$results = [];

function addResult(&$results, $label, $ok, $msg = '') {
    $results[] = [
        'label' => $label,
        'status' => $ok ? 'OK' : 'ERROR',
        'msg' => $msg,
    ];
}

if (!isset($_SESSION['UserName'])) {
    addResult($results, 'Session check', false, 'Anda belum login. Login dulu, lalu buka halaman ini lagi.');
    render($results);
    exit;
}

// 1. Cari nama constraint yang ada pada kolom role_type di tabel resep_obat_groups
$sqlFind = "SELECT name
            FROM sys.check_constraints
            WHERE parent_object_id = OBJECT_ID('dbo.resep_obat_groups')
              AND definition LIKE '%role_type%'";

$stmtFind = @sqlsrv_query($conn, $sqlFind);
if ($stmtFind === false) {
    addResult($results, 'Cari constraint role_type', false, print_r(sqlsrv_errors(), true));
    render($results);
    exit;
}

$constraints = [];
while ($row = sqlsrv_fetch_array($stmtFind, SQLSRV_FETCH_ASSOC)) {
    $constraints[] = $row['name'];
}
@sqlsrv_free_stmt($stmtFind);

if (empty($constraints)) {
    addResult($results, 'Cari constraint role_type', true, 'Tidak ada constraint lama. Constraint baru akan ditambahkan.');
} else {
    foreach ($constraints as $cName) {
        $sqlDrop = "ALTER TABLE dbo.resep_obat_groups DROP CONSTRAINT $cName";
        $stmtDrop = @sqlsrv_query($conn, $sqlDrop);
        if ($stmtDrop === false) {
            addResult($results, "Drop constraint $cName", false, print_r(sqlsrv_errors(), true));
            render($results);
            exit;
        }
        @sqlsrv_free_stmt($stmtDrop);
        addResult($results, "Drop constraint $cName", true, 'Berhasil');
    }
}

// 2. Tambahkan constraint baru yang mengizinkan 'KABAG'
$sqlAdd = "ALTER TABLE dbo.resep_obat_groups
           ADD CONSTRAINT CK_resep_obat_role_type
           CHECK (role_type IN ('LAB', 'PRODUCTION', 'BOTH', 'KABAG'))";

$stmtAdd = @sqlsrv_query($conn, $sqlAdd);
if ($stmtAdd === false) {
    addResult($results, 'Tambah constraint (LAB, PRODUCTION, BOTH, KABAG)', false, print_r(sqlsrv_errors(), true));
} else {
    @sqlsrv_free_stmt($stmtAdd);
    addResult($results, 'Tambah constraint (LAB, PRODUCTION, BOTH, KABAG)', true, 'Berhasil');
}

render($results);

function render($results) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Migrasi Tambah Role KABAG</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, sans-serif; padding: 24px; background: #f4f6f9; }
            h2 { margin: 0 0 16px; }
            table { border-collapse: collapse; min-width: 720px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
            th, td { padding: 8px 12px; border: 1px solid #dee2e6; font-size: 14px; text-align: left; vertical-align: top; }
            th { background: #f4f6f9; }
            .OK { color: #28a745; font-weight: 600; }
            .ERROR { color: #dc3545; font-weight: 600; }
            pre { margin: 0; font-size: 12px; white-space: pre-wrap; }
            .info { background: #fff3cd; padding: 12px; border-left: 4px solid #ffc107; margin-bottom: 16px; }
        </style>
    </head>
    <body>
        <h2>Hasil Migrasi: Tambah Role <code>KABAG</code></h2>
        <div class="info">
            Tujuannya agar tipe role <b>KABAG</b> bisa dipilih saat membuat grup baru
            di <code>resep_role_manager.php</code>. Role KABAG dipakai untuk
            user yang boleh melihat kolom <i>price</i>, <i>total</i>, <i>Grand Total</i>,
            <i>Cost / Plan Qty</i>, dan <i>Total Cost / Meter</i> di halaman
            Input / View Resep Experiment.
        </div>
        <table>
            <tr><th>Proses</th><th>Status</th><th>Keterangan</th></tr>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['label']) ?></td>
                    <td class="<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></td>
                    <td><pre><?= htmlspecialchars($r['msg']) ?></pre></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <p style="margin-top:16px;color:#666">
            <b>Selesai.</b> Hapus file ini (<code>migrate_add_kabag_role.php</code>) setelah migrasi berhasil.
        </p>
    </body>
    </html>
    <?php
}
