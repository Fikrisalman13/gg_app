<?php

if (!function_exists('ensurePrinterWhitelistTable')) {
    function ensurePrinterWhitelistTable($conn)
    {
        $sql = "IF OBJECT_ID('dbo.printer_user_whitelist', 'U') IS NULL
                BEGIN
                    CREATE TABLE dbo.printer_user_whitelist (
                        id INT IDENTITY(1,1) PRIMARY KEY,
                        id_emp INT NOT NULL,
                        id_asset INT NOT NULL,
                        jenis_tinta NVARCHAR(20) NOT NULL,
                        is_active BIT NOT NULL CONSTRAINT DF_printer_user_whitelist_is_active DEFAULT (1),
                        created_at DATETIME NOT NULL CONSTRAINT DF_printer_user_whitelist_created_at DEFAULT (GETDATE()),
                        created_by NVARCHAR(100) NULL,
                        updated_at DATETIME NULL,
                        updated_by NVARCHAR(100) NULL
                    );

                    CREATE UNIQUE INDEX UX_printer_user_whitelist_asset_active
                    ON dbo.printer_user_whitelist (id_asset)
                    WHERE is_active = 1;
                END";

        return sqlsrv_query($conn, $sql);
    }
}

if (!function_exists('ensurePrinterLateLogTable')) {
    function ensurePrinterLateLogTable($conn)
    {
        $sql = "IF OBJECT_ID('dbo.printer_isi_tinta_log', 'U') IS NULL
                BEGIN
                    CREATE TABLE dbo.printer_isi_tinta_log (
                        id INT IDENTITY(1,1) PRIMARY KEY,
                        id_asset INT NOT NULL,
                        id_history INT NOT NULL,
                        due_date DATE NOT NULL,
                        filled_date DATE NOT NULL,
                        late_days INT NOT NULL,
                        log_message NVARCHAR(500) NOT NULL,
                        created_at DATETIME NOT NULL CONSTRAINT DF_printer_isi_tinta_log_created_at DEFAULT (GETDATE()),
                        updated_at DATETIME NULL
                    );

                    CREATE UNIQUE INDEX UX_printer_isi_tinta_log_history
                    ON dbo.printer_isi_tinta_log (id_history);
                END";

        return sqlsrv_query($conn, $sql);
    }
}

if (!function_exists('normalizePrinterSqlsrvDate')) {
    function normalizePrinterSqlsrvDate($value)
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && trim($value) !== '') {
            $formats = [
                'Y-m-d H:i:s.u',
                'Y-m-d H:i:s',
                'd/m/Y H:i:s.u',
                'd/m/Y H:i:s',
                'd/n/Y H:i:s.u',
                'd/n/Y H:i:s',
                'd/m/Y',
                'd/n/Y',
            ];

            foreach ($formats as $format) {
                $parsed = DateTimeImmutable::createFromFormat($format, $value);
                if ($parsed instanceof DateTimeImmutable) {
                    return $parsed;
                }
            }

            try {
                return new DateTimeImmutable($value);
            } catch (Exception $e) {
                return null;
            }
        }

        return null;
    }
}

if (!function_exists('syncPrinterLateLogs')) {
    function syncPrinterLateLogs($conn)
    {
        if (!ensurePrinterLateLogTable($conn)) {
            return false;
        }

        $sql = "SELECT
                    ah.id_history,
                    ah.id_asset,
                    ah.created_at
                FROM dbo.asset_history ah
                INNER JOIN dbo.m_asset a ON ah.id_asset = a.id_asset
                INNER JOIN dbo.printer_user_whitelist pw ON a.id_asset = pw.id_asset AND pw.is_active = 1
                WHERE a.id_kode = 4
                  AND a.id_status IN (1, 6)
                  AND ah.new_status = 'Maintenance'
                  AND LOWER(ISNULL(ah.note, '')) LIKE '%isi tinta%'
                ORDER BY ah.id_asset ASC, ah.created_at ASC, ah.id_history ASC";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt === false) {
            return false;
        }

        $historyPerAsset = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $assetId = (int) ($row['id_asset'] ?? 0);
            if (!isset($historyPerAsset[$assetId])) {
                $historyPerAsset[$assetId] = [];
            }
            $historyPerAsset[$assetId][] = $row;
        }
        sqlsrv_free_stmt($stmt);

        $validHistoryIds = [];

        foreach ($historyPerAsset as $assetId => $rows) {
            $olderDate = null;
            $previousDate = null;

            foreach ($rows as $row) {
                $currentDate = normalizePrinterSqlsrvDate($row['created_at'] ?? null);
                $historyId = (int) ($row['id_history'] ?? 0);

                if ($currentDate && $previousDate && $olderDate) {
                    $referenceInterval = (int) $olderDate->setTime(0, 0, 0)->diff($previousDate->setTime(0, 0, 0))->format('%a');
                    if ($referenceInterval <= 0) {
                        $referenceInterval = 1;
                    }

                    $dueDate = $previousDate->setTime(0, 0, 0)->modify('+' . $referenceInterval . ' days');
                    $filledDate = $currentDate->setTime(0, 0, 0);
                    $lateDays = (int) $dueDate->diff($filledDate)->format('%r%a');

                    if ($lateDays > 0) {
                        $validHistoryIds[] = $historyId;
                        $message = 'Isi tinta telat ' . $lateDays . ' hari. Harusnya isi tanggal ' . $dueDate->format('d-m-Y') . ', diisi tanggal ' . $filledDate->format('d-m-Y');

                        $mergeSql = "MERGE dbo.printer_isi_tinta_log AS target
                                     USING (SELECT ? AS id_history) AS source
                                     ON target.id_history = source.id_history
                                     WHEN MATCHED THEN
                                        UPDATE SET
                                            id_asset = ?,
                                            due_date = ?,
                                            filled_date = ?,
                                            late_days = ?,
                                            log_message = ?,
                                            updated_at = GETDATE()
                                     WHEN NOT MATCHED THEN
                                        INSERT (id_asset, id_history, due_date, filled_date, late_days, log_message)
                                        VALUES (?, ?, ?, ?, ?, ?);";
                        $mergeParams = [
                            $historyId,
                            $assetId,
                            $dueDate->format('Y-m-d'),
                            $filledDate->format('Y-m-d'),
                            $lateDays,
                            $message,
                            $assetId,
                            $historyId,
                            $dueDate->format('Y-m-d'),
                            $filledDate->format('Y-m-d'),
                            $lateDays,
                            $message,
                        ];
                        $mergeStmt = sqlsrv_query($conn, $mergeSql, $mergeParams);
                        if ($mergeStmt === false) {
                            return false;
                        }
                        sqlsrv_free_stmt($mergeStmt);
                    }
                }

                $olderDate = $previousDate;
                $previousDate = $currentDate;
            }
        }

        $deleteSql = "DELETE FROM dbo.printer_isi_tinta_log";
        if (!empty($validHistoryIds)) {
            $placeholders = implode(',', array_fill(0, count($validHistoryIds), '?'));
            $deleteSql .= " WHERE id_history NOT IN ($placeholders)";
            $deleteStmt = sqlsrv_query($conn, $deleteSql, $validHistoryIds);
        } else {
            $deleteStmt = sqlsrv_query($conn, $deleteSql);
        }

        if ($deleteStmt === false) {
            return false;
        }
        sqlsrv_free_stmt($deleteStmt);

        return true;
    }
}
