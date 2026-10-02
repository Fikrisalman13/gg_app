<?php

function closinganApprovalFlow(array $row): array
{
    $requestFlags = closinganRequestFlags($row);
    $hasGudang = $requestFlags['has_gudang'];
    $hasTransaksi = $requestFlags['has_transaksi'];
    $isRevisiHarga = $requestFlags['is_revisi_harga'];

    $roleMengetahui = [];
    $roleDisetujui = [];

    if ($hasGudang && $hasTransaksi) {
        if ($isRevisiHarga) {
            $roleMengetahui[] = 'Kadept ACC';
            $roleDisetujui[] = 'Kabag ICS';
        } else {
            $roleDisetujui[] = 'Kadept ACC';
            $roleDisetujui[] = 'Kabag ICS';
        }
    } elseif ($hasGudang) {
        $roleMengetahui[] = 'Kabag ICS';
        $roleDisetujui[] = 'Kadept ACC';
    } elseif ($hasTransaksi) {
        $roleMengetahui[] = 'Kadept ACC';
        $roleDisetujui[] = 'Kabag ICS';
    }

    if ($isRevisiHarga && !in_array('Direksi', $roleDisetujui, true)) {
        $roleDisetujui[] = 'Direksi';
    }

    if (in_array('Direksi', $roleDisetujui, true)) {
        foreach ($roleDisetujui as $role) {
            if ($role !== 'Direksi' && !in_array($role, $roleMengetahui, true)) {
                $roleMengetahui[] = $role;
            }
        }
        $roleDisetujui = ['Direksi'];
    }

    return [
        'mengetahui' => $roleMengetahui,
        'disetujui' => $roleDisetujui,
        'required_roles' => array_merge(['Pemohon', 'Atasan Pemohon'], $roleMengetahui, $roleDisetujui),
    ];
}

function closinganRequestFlags(array $row): array
{
    $jenis = strtolower(trim((string)($row['jenis_pengajuan'] ?? '')));
    $hasGudang = ($jenis === 'gudang' || $jenis === 'campuran' || !empty($row['request_gudang']));
    $hasTransaksi = ($jenis === 'transaksi' || $jenis === 'campuran' || !empty($row['request_transaksi']));
    $isRevisiHarga = !empty($row['is_revisi_harga']);

    $itemsText = trim((string)($row['gudang_transaksi'] ?? ''));
    if ($itemsText !== '' && ($itemsText[0] === '[' || $itemsText[0] === '{')) {
        $decoded = json_decode($itemsText, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (!empty($item['request_gudang'])) {
                    $hasGudang = true;
                }
                if (!empty($item['request_transaksi'])) {
                    $hasTransaksi = true;
                }
                if (strcasecmp(trim((string)($item['transaksi'] ?? '')), 'Revisi Harga') === 0) {
                    $isRevisiHarga = true;
                }
            }
        }
    } elseif (stripos($itemsText, 'Revisi Harga') !== false) {
        $isRevisiHarga = true;
    }

    return [
        'has_gudang' => $hasGudang,
        'has_transaksi' => $hasTransaksi,
        'is_revisi_harga' => $isRevisiHarga,
    ];
}

function closinganRoleAliases(string $role): array
{
    return strcasecmp($role, 'Kadept ACC') === 0 ? ['Kadept ACC', 'Acc Audit', 'Kadept'] : [$role];
}

function closinganSignedSlotCount($conn, string $ticket, array $requiredRoles): int
{
    if ($ticket === '' || empty($requiredRoles)) {
        return 0;
    }

    $stmt = sqlsrv_query(
        $conn,
        "SELECT GroupRole, SignaturePath FROM Form_Umum_TTD WHERE Ticket = ? AND SignaturePath IS NOT NULL AND SignaturePath != ''",
        [$ticket]
    );

    $signed = [];
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $signed[strtolower(trim((string)$row['GroupRole']))] = true;
        }
        sqlsrv_free_stmt($stmt);
    }

    $count = 0;
    foreach ($requiredRoles as $role) {
        foreach (closinganRoleAliases($role) as $alias) {
            if (!empty($signed[strtolower($alias)])) {
                $count++;
                break;
            }
        }
    }

    return $count;
}

function closinganSyncApprovalStatus($conn, string $ticket, ?array $row = null): array
{
    if ($ticket === '') {
        return ['status' => '', 'count' => 0, 'required' => 0, 'is_complete' => false];
    }

    if ($row === null) {
        $stmt = sqlsrv_query($conn, "SELECT * FROM Form_Umum_Buka_Tanggal_Closingan WHERE ticket = ?", [$ticket]);
        if (!$stmt || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
            return ['status' => '', 'count' => 0, 'required' => 0, 'is_complete' => false];
        }
        sqlsrv_free_stmt($stmt);
    }

    $flow = closinganApprovalFlow($row);
    $required = count($flow['required_roles']);
    $count = closinganSignedSlotCount($conn, $ticket, $flow['required_roles']);
    $isComplete = ($required > 0 && $count >= $required);
    $currentStatus = strtolower(trim((string)($row['status_ticket'] ?? '')));
    $newStatus = trim((string)($row['status_ticket'] ?? ''));

    if ($currentStatus === 'approved' && !$isComplete) {
        $newStatus = 'Pending';
        sqlsrv_query($conn, "UPDATE Form_Umum_Buka_Tanggal_Closingan SET status_ticket = ?, updated_at = GETDATE() WHERE ticket = ?", [$newStatus, $ticket]);
    } elseif (!in_array($currentStatus, ['ditolak', 'rejected', 'unclosing', 'closed'], true) && $isComplete) {
        $newStatus = 'Approved';
        sqlsrv_query($conn, "UPDATE Form_Umum_Buka_Tanggal_Closingan SET status_ticket = ?, updated_at = GETDATE() WHERE ticket = ?", [$newStatus, $ticket]);
    }

    return [
        'status' => $newStatus,
        'count' => $count,
        'required' => $required,
        'is_complete' => $isComplete,
    ];
}

function formUmumGetVisibilityFilter($conn, ?string $category = null, string $tableAlias = ''): array
{
    $isAdmin = (isset($_SESSION['GroupId']) && (int) $_SESSION['GroupId'] === 1);
    if ($isAdmin) {
        return ['where' => '', 'params' => []];
    }

    $prefix = $tableAlias !== '' ? $tableAlias . '.' : '';
    $currentUserId = (int) ($_SESSION['UserId'] ?? 0);
    $currentUserName = $_SESSION['NamaLengkap'] ?? $_SESSION['UserName'] ?? '';

    $userRoles = [];
    $sqlRoles = "SELECT DISTINCT GroupRole FROM dbo.User_TTD_Template_Umum WHERE UserId = ? AND IsActive = 1";
    $stmtRoles = sqlsrv_query($conn, $sqlRoles, [$currentUserId]);
    if ($stmtRoles) {
        while ($rowR = sqlsrv_fetch_array($stmtRoles, SQLSRV_FETCH_ASSOC)) {
            $userRoles[] = trim((string) $rowR['GroupRole']);
        }
        sqlsrv_free_stmt($stmtRoles);
    }

    $roleCategoryMap = [
        'Atasan Pemohon' => ['Buka Tanggal Closingan', 'Izin Keluar Pabrik', 'Izin Pulang Cepat'],
        'Kabag ICS' => ['Buka Tanggal Closingan'],
        'Kabag Purchasing' => ['Buka Tanggal Closingan'],
        'Kadept ACC' => ['Buka Tanggal Closingan'],
        'Acc Audit' => ['Buka Tanggal Closingan'],
        'Kadept Purchasing' => ['Buka Tanggal Closingan'],
        'Direksi' => ['Buka Tanggal Closingan'],
        'HRD' => ['Izin Keluar Pabrik', 'Izin Pulang Cepat'],
        'Personalia' => ['Izin Keluar Pabrik'],
        'Kadept IT' => ['Izin Keluar Pabrik'],
        'DanRu SATPAM' => ['Izin Keluar Pabrik'],
    ];

    $authorizedCategories = [];
    foreach ($userRoles as $ur) {
        if (isset($roleCategoryMap[$ur])) {
            $authorizedCategories = array_merge($authorizedCategories, $roleCategoryMap[$ur]);
        }
    }
    $authorizedCategories = array_values(array_unique($authorizedCategories));

    if ($category !== null) {
        $authorizedCategories = in_array($category, $authorizedCategories, true) ? [$category] : [];
    }

    $visibilityClauses = [];
    $visibilityParams = [];

    // 1. Owner/pembuat dapat melihat form miliknya sendiri
    $visibilityClauses[] = "({$prefix}created_by = ? OR {$prefix}nama_pemohon = ?)";
    $visibilityParams[] = $currentUserName;
    $visibilityParams[] = $currentUserName;

    // 2. Per-user TTD-based filter
    if (!empty($authorizedCategories)) {
        $pairPlaceholders = [];
        $pairParams = [];
        foreach ($roleCategoryMap as $mapRole => $mapCats) {
            foreach ($mapCats as $mapCat) {
                if ($category !== null && $mapCat !== $category) {
                    continue;
                }
                $pairPlaceholders[] = '(?, ?)';
                $pairParams[] = $mapCat;
                $pairParams[] = $mapRole;
            }
        }

        if (!empty($pairPlaceholders)) {
            $pairValuesSql = implode(', ', $pairPlaceholders);
            $kategoriExpr = $category !== null ? "'$category'" : "{$prefix}kategori";

            $isRevisiExpr = ($category === 'Izin Keluar Pabrik' || $category === 'Izin Pulang Cepat') ? '0' : "ISNULL({$prefix}is_revisi_harga, 0)";
            $reqGudangExpr = ($category === 'Izin Keluar Pabrik' || $category === 'Izin Pulang Cepat') ? '0' : "ISNULL({$prefix}request_gudang, 0)";
            $reqTransaksiExpr = ($category === 'Izin Keluar Pabrik' || $category === 'Izin Pulang Cepat') ? '0' : "ISNULL({$prefix}request_transaksi, 0)";

            $ttdSubquery = "
                (
                    EXISTS (
                        SELECT 1
                        FROM Form_Umum_TTD t
                        WHERE t.Ticket = {$prefix}ticket
                          AND t.SignedByUserId = ?
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM dbo.User_TTD_Template_Umum u
                        INNER JOIN (VALUES $pairValuesSql) AS rc(Kategori, GroupRole)
                            ON rc.GroupRole = u.GroupRole
                           AND rc.Kategori = $kategoriExpr
                        WHERE u.UserId = ?
                          AND u.IsActive = 1
                          AND (
                              rc.Kategori != 'Buka Tanggal Closingan'
                              OR (
                                  rc.Kategori = 'Buka Tanggal Closingan'
                                  AND (
                                      (u.GroupRole = 'Direksi' AND $isRevisiExpr = 1)
                                      OR (u.GroupRole = 'Kabag ICS' AND (
                                          $isRevisiExpr = 1
                                          OR $reqGudangExpr = 1
                                          OR $reqTransaksiExpr = 1
                                      ))
                                      OR (u.GroupRole IN ('Kadept ACC', 'Acc Audit') AND (
                                          $isRevisiExpr = 1
                                          OR $reqGudangExpr = 1
                                          OR $reqTransaksiExpr = 1
                                      ))
                                      OR u.GroupRole NOT IN ('Direksi', 'Kabag ICS', 'Kadept ACC', 'Acc Audit')
                                  )
                              )
                          )
                          AND NOT EXISTS (
                              SELECT 1
                              FROM Form_Umum_TTD t2
                              WHERE t2.Ticket = {$prefix}ticket
                                AND (
                                    t2.GroupRole = u.GroupRole
                                    OR (u.GroupRole IN ('Kadept ACC', 'Acc Audit') AND t2.GroupRole IN ('Kadept ACC', 'Acc Audit'))
                                )
                                AND t2.SignaturePath IS NOT NULL
                                AND t2.SignaturePath != ''
                          )
                    )
                )
            ";

            if ($category === null) {
                $placeholders = implode(',', array_fill(0, count($authorizedCategories), '?'));
                $visibilityClauses[] = "({$prefix}kategori IN ($placeholders) AND $ttdSubquery)";
                $visibilityParams = array_merge($visibilityParams, $authorizedCategories);
            } else {
                $visibilityClauses[] = $ttdSubquery;
            }

            $visibilityParams[] = $currentUserId;
            $visibilityParams = array_merge($visibilityParams, $pairParams);
            $visibilityParams[] = $currentUserId;
        }
    }

    $combined = "(" . implode(" OR ", $visibilityClauses) . ")";
    return ['where' => $combined, 'params' => $visibilityParams];
}

