<?php
function dbale_normalize_key($value): string
{
    return strtoupper(trim((string)$value));
}

function dbale_db_date($value): ?string
{
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }
    if (empty($value)) {
        return null;
    }
    $time = strtotime((string)$value);
    return $time ? date('Y-m-d', $time) : null;
}

function dbale_load_warehouses(PDO $conn3): array
{
    $stmt = $conn3->query("SELECT wrhsid, wrhsname FROM whwrhs WHERE wrhsid IS NOT NULL ORDER BY wrhsid");
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function dbale_load_rows(PDO $conn3, string $startDate, string $endDate, string $wrhsid): array
{
    $sql = "SELECT
                h.balehdid,
                h.balenmbr,
                h.baledate,
                h.refnmbr,
                h.upduser,
                h.baledesc,
                p.baleprodid,
                p.wrhsid,
                w.wrhsname,
                p.prodcode,
                p.prodname,
                p.totqtym,
                p.totqtyyard,
                p.totqtykg,
                COUNT(DISTINCT dt.batchno) AS total_batch
            FROM whbalehd h
            INNER JOIN whbaleprod p ON h.balehdid = p.balehdid
            LEFT JOIN whwrhs w ON w.wrhsid = p.wrhsid
            LEFT JOIN whbaledt dt ON dt.balehdid = h.balehdid AND dt.baleprodid = p.baleprodid
            WHERE h.baledate BETWEEN ? AND ? AND p.wrhsid = ?
            GROUP BY
                h.balehdid, h.balenmbr, h.baledate, h.refnmbr, h.upduser, h.baledesc,
                p.baleprodid, p.wrhsid, w.wrhsname, p.prodcode, p.prodname, p.totqtym, p.totqtyyard, p.totqtykg
            ORDER BY h.baledate, h.balenmbr, p.prodcode, p.prodname";
    $stmt = $conn3->prepare($sql);
    if (!$stmt || !$stmt->execute([$startDate, $endDate, $wrhsid])) {
        return [];
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function dbale_load_uploaded_balehdids($conn, string $startDate, string $endDate): array
{
    $sql = 'SELECT DISTINCT balehdid
            FROM dbo.data_bale_upload
            WHERE balehdid IS NOT NULL';
    $params = [];
    if ($startDate !== '') {
        $sql .= ' AND created_at >= CAST(? AS date)';
        $params[] = $startDate;
    }
    if ($endDate !== '') {
        $sql .= ' AND created_at < DATEADD(DAY, 1, CAST(? AS date))';
        $params[] = $endDate;
    }
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        throw new RuntimeException('Gagal mengambil daftar upload bale: ' . print_r(sqlsrv_errors(), true));
    }

    $balehdids = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $balehdid = trim((string)($row['balehdid'] ?? ''));
        if ($balehdid !== '') {
            $balehdids[$balehdid] = $balehdid;
        }
    }
    sqlsrv_free_stmt($stmt);
    return array_values($balehdids);
}

function dbale_load_rows_by_balehdids_paginated(
    PDO $conn3,
    array $balehdids,
    string $wrhsid,
    string $search,
    int $offset,
    int $limit
): array {
    $balehdids = array_values(array_unique(array_filter(array_map('trim', $balehdids), static function ($value) {
        return ctype_digit($value) && (int)$value > 0;
    })));
    if (empty($balehdids) || $wrhsid === '') {
        return ['rows' => [], 'total' => 0];
    }

    $offset = max(0, $offset);
    $limit = max(1, min(100, $limit));
    $balehdidArray = '{' . implode(',', array_map('intval', $balehdids)) . '}';
    $where = 'p.wrhsid = ? AND h.balehdid = ANY(CAST(? AS integer[]))';
    $params = [$wrhsid, $balehdidArray];

    if ($search !== '') {
        $where .= ' AND (h.balenmbr ILIKE ? OR p.prodcode ILIKE ? OR p.prodname ILIKE ? OR h.refnmbr ILIKE ? OR h.baledesc ILIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }

    // Hitung dari tabel header + produk saja. Join ke seluruh whbaledt pada query
    // COUNT membuat halaman lambat padahal detail batch tidak dibutuhkan di sini.
    $countSql = "SELECT COUNT(*)
        FROM whbalehd h
        INNER JOIN whbaleprod p ON h.balehdid = p.balehdid
        WHERE {$where}";
    $countStmt = $conn3->prepare($countSql);
    if (!$countStmt || !$countStmt->execute($params)) {
        throw new RuntimeException('Gagal menghitung data bale yang sudah di-upload.');
    }
    $total = (int)$countStmt->fetchColumn();

    // conn3 sudah menyimpan jumlah batch di whbaleprod.countbatch. Memakai
    // nilai tersebut menghindari scan dan GROUP BY pada seluruh whbaledt.
    $dataSql = "SELECT
            h.balehdid, h.balenmbr, h.baledate, h.refnmbr, h.upduser, h.baledesc,
            p.baleprodid, p.wrhsid, w.wrhsname, p.prodcode, p.prodname,
            p.totqtym, p.totqtyyard, p.totqtykg,
            COALESCE(p.countbatch, 0) AS total_batch
        FROM whbalehd h
        INNER JOIN whbaleprod p ON h.balehdid = p.balehdid
        LEFT JOIN whwrhs w ON w.wrhsid = p.wrhsid
        WHERE {$where}
        ORDER BY h.baledate DESC, h.balenmbr DESC, p.prodcode, p.prodname
        LIMIT {$limit} OFFSET {$offset}";
    $dataStmt = $conn3->prepare($dataSql);
    if (!$dataStmt || !$dataStmt->execute($params)) {
        throw new RuntimeException('Gagal mengambil halaman data bale yang sudah di-upload.');
    }
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    return ['rows' => is_array($rows) ? $rows : [], 'total' => $total];
}

function dbale_load_rows_by_balehdids(PDO $conn3, array $balehdids, string $wrhsid): array
{
    $balehdids = array_values(array_unique(array_filter(array_map('trim', $balehdids), static function ($value) {
        return $value !== '';
    })));
    if (empty($balehdids) || $wrhsid === '') {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($balehdids), '?'));
    $sql = "SELECT
                h.balehdid,
                h.balenmbr,
                h.baledate,
                h.refnmbr,
                h.upduser,
                h.baledesc,
                p.baleprodid,
                p.wrhsid,
                w.wrhsname,
                p.prodcode,
                p.prodname,
                p.totqtym,
                p.totqtyyard,
                p.totqtykg,
                COUNT(DISTINCT dt.batchno) AS total_batch
            FROM whbalehd h
            INNER JOIN whbaleprod p ON h.balehdid = p.balehdid
            LEFT JOIN whwrhs w ON w.wrhsid = p.wrhsid
            LEFT JOIN whbaledt dt ON dt.balehdid = h.balehdid AND dt.baleprodid = p.baleprodid
            WHERE p.wrhsid = ? AND h.balehdid IN ({$placeholders})
            GROUP BY
                h.balehdid, h.balenmbr, h.baledate, h.refnmbr, h.upduser, h.baledesc,
                p.baleprodid, p.wrhsid, w.wrhsname, p.prodcode, p.prodname, p.totqtym, p.totqtyyard, p.totqtykg
            ORDER BY h.baledate, h.balenmbr, p.prodcode, p.prodname";
    $stmt = $conn3->prepare($sql);
    if (!$stmt || !$stmt->execute(array_merge([$wrhsid], $balehdids))) {
        throw new RuntimeException('Gagal mengambil detail bale yang sudah di-upload.');
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function dbale_load_uploadpengebalan_candidates(PDO $conn3, string $wrhsid): array
{
    $sql = "SELECT
                h.balehdid,
                h.balenmbr,
                h.baledate,
                h.refnmbr,
                h.baledesc,
                p.baleprodid,
                p.wrhsid,
                w.wrhsname,
                p.prodcode,
                p.prodname,
                p.totqtym,
                p.totqtyyard,
                p.totqtykg,
                COUNT(DISTINCT dt.batchno) AS total_batch
            FROM whbalehd h
            INNER JOIN whbaleprod p ON h.balehdid = p.balehdid
            LEFT JOIN whwrhs w ON w.wrhsid = p.wrhsid
            LEFT JOIN whbaledt dt ON dt.balehdid = h.balehdid AND dt.baleprodid = p.baleprodid
            WHERE p.wrhsid = ?
            GROUP BY
                h.balehdid, h.balenmbr, h.baledate, h.refnmbr, h.baledesc,
                p.baleprodid, p.wrhsid, w.wrhsname, p.prodcode, p.prodname, p.totqtym, p.totqtyyard, p.totqtykg
            ORDER BY h.baledate, h.balenmbr, p.prodcode, p.prodname";
    $stmt = $conn3->prepare($sql);
    if (!$stmt || !$stmt->execute([$wrhsid])) {
        return [];
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function dbale_load_uploaded_balenmbr_set($conn): array
{
    $stmt = sqlsrv_query($conn, 'SELECT DISTINCT balenmbr FROM dbo.upload_pengebalan_dt WHERE balenmbr IS NOT NULL');
    if ($stmt === false) {
        return [];
    }

    $set = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $key = dbale_normalize_key($row['balenmbr'] ?? '');
        if ($key !== '') {
            $set[$key] = true;
        }
    }
    sqlsrv_free_stmt($stmt);
    return $set;
}

function dbale_load_pengebalan_bale_candidates(PDO $conn3, string $wrhsid, string $search = '', int $limit = 200): array
{
    $limit = max(1, min($limit, 500));
    $sql = "SELECT
                h.balehdid,
                h.balenmbr,
                h.refnmbr,
                h.baledate
            FROM whbalehd h
            INNER JOIN whbaleprod p ON h.balehdid = p.balehdid
            WHERE p.wrhsid = ?";
    $params = [$wrhsid];
    if ($search !== '') {
        $sql .= " AND (h.balenmbr LIKE ? OR h.refnmbr LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= "
            GROUP BY h.balehdid, h.balenmbr, h.refnmbr, h.baledate
            ORDER BY h.baledate, h.balenmbr
            LIMIT {$limit}";
    $stmt = $conn3->prepare($sql);
    if (!$stmt || !$stmt->execute($params)) {
        return [];
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function dbale_load_pengebalan_bales_by_ids(PDO $conn3, string $wrhsid, array $balehdids): array
{
    $balehdids = array_values(array_unique(array_filter(array_map('trim', $balehdids))));
    if (empty($balehdids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($balehdids), '?'));
    $sql = "SELECT
                h.balehdid,
                h.balenmbr,
                h.refnmbr,
                h.baledate
            FROM whbalehd h
            INNER JOIN whbaleprod p ON h.balehdid = p.balehdid
            WHERE p.wrhsid = ? AND h.balehdid IN ({$placeholders})
            GROUP BY h.balehdid, h.balenmbr, h.refnmbr, h.baledate";
    $stmt = $conn3->prepare($sql);
    if (!$stmt || !$stmt->execute(array_merge([$wrhsid], $balehdids))) {
        return [];
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function dbale_load_uploadpengebalan_source_details(PDO $conn3, string $balehdid, string $wrhsid): array
{
    $sql = "SELECT
                h.balehdid AS source_balehdid,
                p.baleprodid AS source_baleprodid,
                dt.batchno,
                dt.qtym,
                dt.qtyyard,
                dt.qtykg,
                h.balenmbr,
                h.baledesc,
                h.baledate,
                p.prodcode,
                p.prodname
            FROM whbalehd h
            INNER JOIN whbaleprod p ON h.balehdid = p.balehdid
            INNER JOIN whbaledt dt ON dt.balehdid = h.balehdid AND dt.baleprodid = p.baleprodid
            WHERE h.balehdid = ? AND p.wrhsid = ?
            ORDER BY p.prodcode, p.prodname, dt.batchno";
    $stmt = $conn3->prepare($sql);
    if (!$stmt || !$stmt->execute([$balehdid, $wrhsid])) {
        return [];
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function dbale_load_uploadpengebalan_source_balehdids($conn, int $hdid): array
{
    $stmt = sqlsrv_query($conn, 'SELECT DISTINCT source_balehdid FROM dbo.upload_pengebalan_dt WHERE hdid = ?', [$hdid]);
    if ($stmt === false) {
        return [];
    }

    $ids = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $balehdid = trim((string)($row['source_balehdid'] ?? ''));
        if ($balehdid !== '') {
            $ids[] = $balehdid;
        }
    }
    sqlsrv_free_stmt($stmt);
    return $ids;
}

function dbale_load_detail_rows(PDO $conn3, string $balehdid, string $baleprodid): array
{
    $stmt = $conn3->prepare("SELECT batchno, qtym, qtyyard, qtykg FROM whbaledt WHERE balehdid = ? AND baleprodid = ? ORDER BY batchno");
    if (!$stmt || !$stmt->execute([$balehdid, $baleprodid])) {
        return [];
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function dbale_load_upload($conn, string $balehdid): ?array
{
    $stmt = sqlsrv_query($conn, 'SELECT TOP 1 * FROM dbo.data_bale_upload WHERE balehdid = ?', [$balehdid]);
    if ($stmt === false) {
        return null;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) ?: null;
    sqlsrv_free_stmt($stmt);
    return $row ?: null;
}

function dbale_load_upload_group_map($conn, array $focusBalehdids = []): array
{
    $focusBalehdids = array_values(array_unique(array_filter(array_map('trim', $focusBalehdids), static function ($value) {
        return $value !== '';
    })));

    if (empty($focusBalehdids)) {
        $sql = '
            SELECT balehdid, photo_barang, photo_packinglist, created_at, updated_at
            FROM dbo.data_bale_upload
            WHERE balehdid IS NOT NULL
            ORDER BY id';
        $params = [];
    } else {
        $placeholders = implode(',', array_fill(0, count($focusBalehdids), '?'));
        $sql = "
            WITH focus_upload AS (
                SELECT balehdid, photo_barang, photo_packinglist
                FROM dbo.data_bale_upload
                WHERE balehdid IN ({$placeholders})
            )
            SELECT d.balehdid, d.photo_barang, d.photo_packinglist, d.created_at, d.updated_at
            FROM dbo.data_bale_upload d
            WHERE d.balehdid IN ({$placeholders})
               OR EXISTS (
                    SELECT 1
                    FROM focus_upload f
                    WHERE NULLIF(LTRIM(RTRIM(f.photo_barang)), '') IS NOT NULL
                      AND NULLIF(LTRIM(RTRIM(f.photo_packinglist)), '') IS NOT NULL
                      AND f.photo_barang = d.photo_barang
                      AND f.photo_packinglist = d.photo_packinglist
               )
            ORDER BY d.id";
        $params = array_merge($focusBalehdids, $focusBalehdids);
    }

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        throw new RuntimeException('Gagal mengambil grup bukti upload: ' . print_r(sqlsrv_errors(), true));
    }

    $groups = [];
    $rowsByBale = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $balehdid = trim((string)($row['balehdid'] ?? ''));
        if ($balehdid === '') {
            continue;
        }
        $photoBarang = trim((string)($row['photo_barang'] ?? ''));
        $photoPacking = trim((string)($row['photo_packinglist'] ?? ''));
        $groupKey = ($photoBarang !== '' && $photoPacking !== '')
            ? dbale_normalize_key($photoBarang) . '|' . dbale_normalize_key($photoPacking)
            : 'BALE|' . $balehdid;
        $groups[$groupKey][$balehdid] = $balehdid;
        $rowsByBale[$balehdid] = $row;
    }
    sqlsrv_free_stmt($stmt);

    $map = [];
    foreach ($groups as $groupBalehdids) {
        $ids = array_values($groupBalehdids);
        foreach ($ids as $balehdid) {
            $map[$balehdid] = [
                'balehdids' => $ids,
                'upload' => $rowsByBale[$balehdid] ?? null,
            ];
        }
    }
    return $map;
}

function dbale_upload_file_is_referenced($conn, string $fileName): bool
{
    $fileName = trim($fileName);
    if ($fileName === '') {
        return false;
    }

    $sql = 'SELECT
                (SELECT COUNT(*) FROM dbo.data_bale_upload WHERE photo_barang = ? OR photo_packinglist = ?)
              + (SELECT COUNT(*) FROM dbo.upload_pengebalan_hd WHERE photo_barang = ? OR photo_packinglist = ?) AS total_refs';
    $stmt = sqlsrv_query($conn, $sql, [$fileName, $fileName, $fileName, $fileName]);
    if ($stmt === false) {
        return true;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return (int)($row['total_refs'] ?? 0) > 0;
}

function dbale_delete_single_bale_upload($conn, string $balehdid, string $userName): array
{
    $uploadRow = dbale_load_upload($conn, $balehdid);
    if (!$uploadRow) {
        throw new RuntimeException('Bukti upload bale ' . $balehdid . ' tidak ditemukan.');
    }

    $photoBarang = trim((string)($uploadRow['photo_barang'] ?? ''));
    $photoPacking = trim((string)($uploadRow['photo_packinglist'] ?? ''));
    $photoNames = array_values(array_unique(array_filter([$photoBarang, $photoPacking])));
    $headerIds = [];

    if ($photoBarang !== '' && $photoPacking !== '') {
        $headerStmt = sqlsrv_query($conn, '
            SELECT hdid FROM dbo.upload_pengebalan_hd
            WHERE photo_barang = ? AND photo_packinglist = ?
        ', [$photoBarang, $photoPacking]);
        if ($headerStmt === false) {
            throw new RuntimeException('Gagal mencari header sheet: ' . print_r(sqlsrv_errors(), true));
        }
        while ($headerRow = sqlsrv_fetch_array($headerStmt, SQLSRV_FETCH_ASSOC)) {
            $headerId = (int)($headerRow['hdid'] ?? 0);
            if ($headerId > 0) {
                $headerIds[] = $headerId;
            }
        }
        sqlsrv_free_stmt($headerStmt);
    }

    foreach ($headerIds as $headerId) {
        $deleteDetailStmt = sqlsrv_query($conn, 'DELETE FROM dbo.upload_pengebalan_dt WHERE hdid = ? AND source_balehdid = ?', [$headerId, $balehdid]);
        if ($deleteDetailStmt === false) {
            throw new RuntimeException('Gagal menghapus detail bale: ' . print_r(sqlsrv_errors(), true));
        }
        sqlsrv_free_stmt($deleteDetailStmt);

        $summaryStmt = sqlsrv_query($conn, '
            SELECT
                COUNT(DISTINCT source_balehdid) AS total_pcs,
                COALESCE(SUM(qtym), 0) AS total_m,
                COALESCE(SUM(qtyyard), 0) AS total_yard
            FROM dbo.upload_pengebalan_dt
            WHERE hdid = ?
        ', [$headerId]);
        if ($summaryStmt === false) {
            throw new RuntimeException('Gagal menghitung ulang total sheet: ' . print_r(sqlsrv_errors(), true));
        }
        $summary = sqlsrv_fetch_array($summaryStmt, SQLSRV_FETCH_ASSOC) ?: [];
        sqlsrv_free_stmt($summaryStmt);

        $remainingBales = (int)($summary['total_pcs'] ?? 0);
        if ($remainingBales <= 0) {
            $deleteHeaderStmt = sqlsrv_query($conn, 'DELETE FROM dbo.upload_pengebalan_hd WHERE hdid = ?', [$headerId]);
            if ($deleteHeaderStmt === false) {
                throw new RuntimeException('Gagal menghapus header sheet kosong: ' . print_r(sqlsrv_errors(), true));
            }
            sqlsrv_free_stmt($deleteHeaderStmt);
        } else {
            $updateHeaderStmt = sqlsrv_query($conn, '
                UPDATE dbo.upload_pengebalan_hd
                SET total_pcs = ?, total_m = ?, total_yard = ?, updated_by = ?, updated_at = GETDATE()
                WHERE hdid = ?
            ', [
                $remainingBales,
                (float)($summary['total_m'] ?? 0),
                (float)($summary['total_yard'] ?? 0),
                $userName,
                $headerId,
            ]);
            if ($updateHeaderStmt === false) {
                throw new RuntimeException('Gagal memperbarui total header sheet: ' . print_r(sqlsrv_errors(), true));
            }
            sqlsrv_free_stmt($updateHeaderStmt);
        }
    }

    $deleteUploadStmt = sqlsrv_query($conn, 'DELETE FROM dbo.data_bale_upload WHERE balehdid = ?', [$balehdid]);
    if ($deleteUploadStmt === false) {
        throw new RuntimeException('Gagal menghapus bukti upload bale: ' . print_r(sqlsrv_errors(), true));
    }
    sqlsrv_free_stmt($deleteUploadStmt);
    return $photoNames;
}

function dbale_load_uploadpengebalan_header($conn, int $hdid): ?array
{
    $stmt = sqlsrv_query($conn, 'SELECT TOP 1 * FROM dbo.upload_pengebalan_hd WHERE hdid = ?', [$hdid]);
    if ($stmt === false) {
        return null;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) ?: null;
    sqlsrv_free_stmt($stmt);
    return $row ?: null;
}

function dbale_load_uploadpengebalan_details($conn, int $hdid): array
{
    $stmt = sqlsrv_query($conn, 'SELECT * FROM dbo.upload_pengebalan_dt WHERE hdid = ? ORDER BY prodcode, balenmbr, batchno, dtid', [$hdid]);
    if ($stmt === false) {
        return [];
    }
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $rows;
}

function dbale_create_uploadpengebalan_header($conn, string $wrhsid, string $wrhsname, string $userName): int
{
    $stmt = sqlsrv_query($conn, '
        INSERT INTO dbo.upload_pengebalan_hd (wrhsid, wrhsname, created_by, updated_by)
        OUTPUT INSERTED.hdid
        VALUES (?, ?, ?, ?)
    ', [$wrhsid, $wrhsname, $userName, $userName]);
    if ($stmt === false) {
        throw new RuntimeException('Gagal simpan header: ' . print_r(sqlsrv_errors(), true));
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    $headerId = (int)($row['hdid'] ?? 0);
    if ($headerId <= 0) {
        throw new RuntimeException('ID header tidak ditemukan.');
    }
    return $headerId;
}

function dbale_insert_uploadpengebalan_detail($conn, int $hdid, int $seq, array $row, string $userName): void
{
    $stmt = sqlsrv_query($conn, '
        INSERT INTO dbo.upload_pengebalan_dt
            (hdid, seq, source_balehdid, source_baleprodid, batchno, qtym, qtyyard, qtykg, balenmbr, baledesc, baledate, prodcode, prodname, created_by, updated_by)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ', [
        $hdid,
        $seq,
        $row['source_balehdid'] ?? '',
        $row['source_baleprodid'] ?? '',
        $row['batchno'] ?? '',
        $row['qtym'] ?? 0,
        $row['qtyyard'] ?? 0,
        $row['qtykg'] ?? 0,
        $row['balenmbr'] ?? '',
        $row['baledesc'] ?? '',
        $row['baledate'] ?? null,
        $row['prodcode'] ?? '',
        $row['prodname'] ?? '',
        $userName,
        $userName,
    ]);
    if ($stmt === false) {
        throw new RuntimeException('Gagal simpan detail: ' . print_r(sqlsrv_errors(), true));
    }
    sqlsrv_free_stmt($stmt);
}

function dbale_update_uploadpengebalan_summary($conn, int $hdid, int $totalPcs, float $totalM, float $totalYard, string $userName): void
{
    $stmt = sqlsrv_query($conn, 'UPDATE dbo.upload_pengebalan_hd SET total_pcs = ?, total_m = ?, total_yard = ?, updated_by = ?, updated_at = GETDATE() WHERE hdid = ?', [$totalPcs, $totalM, $totalYard, $userName, $hdid]);
    if ($stmt === false) {
        throw new RuntimeException('Gagal update total header: ' . print_r(sqlsrv_errors(), true));
    }
    sqlsrv_free_stmt($stmt);
}

function dbale_upsert_uploadpengebalan_photos($conn, int $hdid, ?string $photoBarang, ?string $photoPacking, string $userName): void
{
    $exists = dbale_load_uploadpengebalan_header($conn, $hdid);
    if (!$exists) {
        throw new RuntimeException('Header upload tidak ditemukan.');
    }

    $currentBarang = (string)($exists['photo_barang'] ?? '');
    $currentPacking = (string)($exists['photo_packinglist'] ?? '');
    $photoBarang = $photoBarang ?? $currentBarang;
    $photoPacking = $photoPacking ?? $currentPacking;
    $status = trim((string)$photoBarang) !== '' && trim((string)$photoPacking) !== ''
        ? 'DONE'
        : (string)($exists['status'] ?? 'DRAFT');

    $stmt = sqlsrv_query($conn, 'UPDATE dbo.upload_pengebalan_hd SET photo_barang = ?, photo_packinglist = ?, status = ?, updated_by = ?, updated_at = GETDATE() WHERE hdid = ?', [$photoBarang, $photoPacking, $status, $userName, $hdid]);
    if ($stmt === false) {
        throw new RuntimeException('Gagal update foto: ' . print_r(sqlsrv_errors(), true));
    }
    sqlsrv_free_stmt($stmt);
}

function dbale_ensure_upload_dir(): string
{
    $dir = __DIR__ . '/../../../uploads/databale';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function dbale_resize_gd_image($image, int $width, int $height)
{
    if (function_exists('imagescale')) {
        $scaled = imagescale($image, $width, $height, IMG_BILINEAR_FIXED);
        if ($scaled !== false) {
            return $scaled;
        }
    }

    $canvas = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
    return $canvas;
}

function dbale_compress_image_to_jpeg(string $sourcePath, string $destinationPath, int $maxBytes = 1048576): bool
{
    $requiredGdFunctions = [
        'imagecreatefromstring',
        'imagecreatetruecolor',
        'imagecolorallocate',
        'imagefill',
        'imagecopy',
        'imagecopyresampled',
        'imagejpeg',
        'imagesx',
        'imagesy',
        'imagedestroy',
    ];
    foreach ($requiredGdFunctions as $gdFunction) {
        if (!function_exists($gdFunction)) {
            throw new RuntimeException('Ekstensi PHP GD belum aktif. Aktifkan extension=gd lalu restart Apache.');
        }
    }

    $imageData = @file_get_contents($sourcePath);
    if ($imageData === false) {
        return false;
    }
    $image = @imagecreatefromstring($imageData);
    if (!$image) {
        return false;
    }

    $width = imagesx($image);
    $height = imagesy($image);
    // Foto kamera modern bisa sangat besar. Turunkan dimensinya sejak percobaan
    // pertama agar PHP tidak berulang kali mengolah canvas beresolusi penuh.
    $maxDimension = 1800;
    $scale = min(1.0, $maxDimension / max($width, $height));
    $quality = 85;
    $success = false;

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $working = $image;
        if ($scale < 0.999) {
            $newWidth = max(1, (int)floor($width * $scale));
            $newHeight = max(1, (int)floor($height * $scale));
            $working = dbale_resize_gd_image($image, $newWidth, $newHeight);
        }

        $tempFile = $destinationPath . '.tmp';
        $canvas = imagecreatetruecolor(imagesx($working), imagesy($working));
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $working, 0, 0, 0, 0, imagesx($working), imagesy($working));
        imagejpeg($canvas, $tempFile, $quality);
        imagedestroy($canvas);
        if ($working !== $image) {
            imagedestroy($working);
        }

        clearstatcache(true, $tempFile);
        $size = is_file($tempFile) ? filesize($tempFile) : false;
        if ($size !== false && $size <= $maxBytes) {
            rename($tempFile, $destinationPath);
            $success = true;
            break;
        }
        if (is_file($tempFile)) {
            unlink($tempFile);
        }

        if ($quality > 50) {
            $quality -= 10;
        } elseif ($scale > 0.35) {
            $scale *= 0.85;
            $quality = 88;
        } else {
            break;
        }
    }

    imagedestroy($image);
    return $success;
}

function dbale_store_upload_file(array $file, string $destinationPath): ?string
{
    if (!isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gagal.');
    }
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('File upload tidak valid.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new RuntimeException('File harus berupa gambar.');
    }

    // JPEG yang sudah kecil tidak perlu didekode dan dikompres ulang.
    $sourceSize = @filesize($file['tmp_name']);
    $sourceMime = strtolower((string)($imageInfo['mime'] ?? ''));
    if ($sourceMime === 'image/jpeg' && $sourceSize !== false && $sourceSize <= 1024 * 1024) {
        if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
            throw new RuntimeException('Gagal menyimpan gambar.');
        }
        return basename($destinationPath);
    }

    // Tetap izinkan upload pada proses Apache yang belum memuat ekstensi GD.
    // getimagesize() di atas memastikan file benar-benar merupakan gambar.
    if (!function_exists('imagecreatefromstring')) {
        if ($sourceSize === false || $sourceSize > 1024 * 1024) {
            throw new RuntimeException('Gambar lebih dari 1 MB dan tidak dapat dikompres karena ekstensi PHP GD belum aktif.');
        }
        $extensionByMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
        ];
        $sourceMime = strtolower((string)($imageInfo['mime'] ?? ''));
        $sourceExtension = $extensionByMime[$sourceMime] ?? '';
        $fallbackPath = $destinationPath;
        if ($sourceExtension !== '') {
            $fallbackPath = preg_replace('~\.[^.\\\\/]+$~', '.' . $sourceExtension, $destinationPath) ?: $destinationPath;
        }
        if (!move_uploaded_file($file['tmp_name'], $fallbackPath)) {
            throw new RuntimeException('Gagal menyimpan gambar.');
        }
        return basename($fallbackPath);
    }

    if (!dbale_compress_image_to_jpeg($file['tmp_name'], $destinationPath, 1024 * 1024)) {
        throw new RuntimeException('Gagal kompres gambar.');
    }

    return basename($destinationPath);
}

function dbale_rotate_upload_photo(string $filePath, string $direction): void
{
    if (!in_array($direction, ['left', 'right'], true)) {
        throw new InvalidArgumentException('Arah rotasi tidak valid.');
    }
    if (!is_file($filePath)) {
        throw new RuntimeException('File foto tidak ditemukan.');
    }
    if (!function_exists('imagecreatefromstring') || !function_exists('imagerotate')) {
        throw new RuntimeException('Fitur rotasi membutuhkan ekstensi PHP GD.');
    }

    $imageData = @file_get_contents($filePath);
    $imageInfo = @getimagesize($filePath);
    $image = $imageData !== false ? @imagecreatefromstring($imageData) : false;
    if (!$image || $imageInfo === false) {
        throw new RuntimeException('Foto tidak dapat dibaca.');
    }

    $background = imagecolorallocatealpha($image, 255, 255, 255, 0);
    $rotated = imagerotate($image, $direction === 'right' ? -90 : 90, $background);
    imagedestroy($image);
    if ($rotated === false) {
        throw new RuntimeException('Foto gagal diputar.');
    }

    $tempPath = $filePath . '.rotate-' . bin2hex(random_bytes(4)) . '.tmp';
    $mime = strtolower((string)($imageInfo['mime'] ?? 'image/jpeg'));
    if ($mime === 'image/png') {
        imagesavealpha($rotated, true);
        $saved = imagepng($rotated, $tempPath, 6);
    } elseif ($mime === 'image/gif' && function_exists('imagegif')) {
        $saved = imagegif($rotated, $tempPath);
    } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
        $saved = imagewebp($rotated, $tempPath, 88);
    } else {
        $saved = imagejpeg($rotated, $tempPath, 88);
    }
    imagedestroy($rotated);

    if (!$saved || !is_file($tempPath)) {
        @unlink($tempPath);
        throw new RuntimeException('Hasil rotasi gagal disimpan.');
    }
    if (!@copy($tempPath, $filePath)) {
        @unlink($tempPath);
        throw new RuntimeException('File foto gagal diperbarui.');
    }
    @unlink($tempPath);
    clearstatcache(true, $filePath);
}

function dbale_upsert_upload($conn, string $balehdid, ?string $photoBarang, ?string $photoPacking, string $userName): void
{
    $exists = dbale_load_upload($conn, $balehdid);
    if ($exists) {
        $currentBarang = (string)($exists['photo_barang'] ?? '');
        $currentPacking = (string)($exists['photo_packinglist'] ?? '');
        $photoBarang = $photoBarang ?? $currentBarang;
        $photoPacking = $photoPacking ?? $currentPacking;

        $stmt = sqlsrv_query($conn, 'UPDATE dbo.data_bale_upload SET photo_barang = ?, photo_packinglist = ?, updated_by = ?, updated_at = GETDATE() WHERE balehdid = ?', [$photoBarang, $photoPacking, $userName, $balehdid]);
        if ($stmt === false) {
            throw new RuntimeException('Gagal update upload: ' . print_r(sqlsrv_errors(), true));
        }
        sqlsrv_free_stmt($stmt);
        return;
    }

    $stmt = sqlsrv_query($conn, 'INSERT INTO dbo.data_bale_upload (balehdid, photo_barang, photo_packinglist, created_by, updated_by) VALUES (?, ?, ?, ?, ?)', [$balehdid, $photoBarang, $photoPacking, $userName, $userName]);
    if ($stmt === false) {
        throw new RuntimeException('Gagal simpan upload: ' . print_r(sqlsrv_errors(), true));
    }
    sqlsrv_free_stmt($stmt);
}


