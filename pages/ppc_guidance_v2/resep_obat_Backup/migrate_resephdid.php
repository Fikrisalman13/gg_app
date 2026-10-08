<?php
// pages/resep_obat/migrate_resephdid.php
session_start();
require_once '../../../koneksi.php';  // SQL Server
require_once '../../../koneksi3.php'; // PostgreSQL

if (!isset($_SESSION['UserName'])) {
    die("Unauthorized. Silakan login terlebih dahulu.");
}

echo "<h2>Migrasi Data proint_resephdid & status_resep_lipat</h2>";
echo "Menghubungkan ke SQL Server dan PostgreSQL...<br><br>";

// 1. Ambil data dari SQL Server yang status_resep_lipat nya masih NULL atau proint_resephdid nya NULL
$sql = "SELECT id, resep_no, resep_seq FROM dbo.resep_obat WHERE proint_resephdid IS NULL OR status_resep_lipat IS NULL";
$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die("Gagal mengambil data dari SQL Server: " . print_r(sqlsrv_errors(), true));
}

$processed = 0;
$updated = 0;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $id = $row['id'];
    $resepNo = trim($row['resep_no'] ?? '');
    $resepSeq = $row['resep_seq'] !== null ? intval($row['resep_seq']) : null;

    $processed++;

    if ($resepNo === '' || $resepSeq === null) {
        // Jika no resep kosong, cukup update status_resep_lipat ke 'Master Resep'
        $updateSql = "UPDATE dbo.resep_obat SET status_resep_lipat = 'Master Resep' WHERE id = ?";
        sqlsrv_query($conn, $updateSql, [$id]);
        $updated++;
        continue;
    }

    // 2. Cari resephdid di PostgreSQL
    try {
        $pgSql = "SELECT resephdid FROM pdresephd WHERE resepno = :resepno AND resepseq = :resepseq LIMIT 1";
        $pgStmt = $conn3->prepare($pgSql);
        $pgStmt->execute([
            ':resepno' => $resepNo,
            ':resepseq' => $resepSeq
        ]);
        $pgRow = $pgStmt->fetch(PDO::FETCH_ASSOC);

        if ($pgRow && isset($pgRow['resephdid'])) {
            $resephdid = intval($pgRow['resephdid']);
            // Update proint_resephdid dan status_resep_lipat ke SQL Server
            $updateSql = "UPDATE dbo.resep_obat SET proint_resephdid = ?, status_resep_lipat = 'Master Resep' WHERE id = ?";
            $updateParams = [$resephdid, $id];
            $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);
            if ($updateStmt === false) {
                echo "Gagal update ID $id (Resep: $resepNo Ver $resepSeq): " . print_r(sqlsrv_errors(), true) . "<br>";
            } else {
                $updated++;
            }
        } else {
            // Jika tidak ditemukan di PostgreSQL, set status saja
            $updateSql = "UPDATE dbo.resep_obat SET status_resep_lipat = 'Master Resep' WHERE id = ?";
            sqlsrv_query($conn, $updateSql, [$id]);
            $updated++;
            echo "Resep <b>$resepNo Ver $resepSeq</b> tidak ditemukan di PostgreSQL. Status diupdate ke 'Master Resep'.<br>";
        }
    } catch (PDOException $e) {
        echo "Error PostgreSQL untuk ID $id: " . $e->getMessage() . "<br>";
    }
}

echo "<br><b>Selesai!</b> Berhasil memproses $processed baris data dan memperbarui $updated baris data.";
?>
