<?php
session_start();

require_once '../../koneksi.php';

$uniqueid = $_GET['uniqueid'] ?? '';

$q = sqlsrv_query(
    $conn,
    "SELECT *
     FROM orderitem_gistex
     WHERE uniqueid = ?",
    [$uniqueid]
);

$data = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC);

if (!$data) {
    die('Data tidak ditemukan');
}
?>

<form method="post" action="update_orderitem.php">

    <input type="hidden"
           name="uniqueid"
           value="<?= htmlspecialchars($data['uniqueid']) ?>">

    <div>
        Description
        <input type="text"
               name="description"
               value="<?= htmlspecialchars($data['description']) ?>">
    </div>

    <div>
        Qty.Order
        <input type="number"
               step="0.01"
               name="qty_order"
               value="<?= htmlspecialchars($data['Qty.Order']) ?>">
    </div>

    <div>
        Packaging
        <input type="text"
               name="packaging"
               value="<?= htmlspecialchars($data['packaging']) ?>">
    </div>

    <div>
        UOM
        <input type="text"
               name="uom"
               value="<?= htmlspecialchars($data['uom']) ?>">
    </div>

    <button type="submit">
        Update
    </button>

</form>