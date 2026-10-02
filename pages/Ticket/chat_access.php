<?php

/**
 * Load account and employee identity used by ticket authorization.
 *
 * @param resource $conn Active SQL Server connection.
 * @param int $userId Authenticated SMUserMs.UserId.
 * @return array{user_id:int,emp_id:int,group_name:string}
 * @throws RuntimeException When identity cannot be loaded.
 */
function ticket_chat_load_identity($conn, int $userId): array
{
    $sql = "SELECT TOP 1 u.UserId, u.EmpId, g.GroupName
            FROM dbo.SMUserMs u
            LEFT JOIN dbo.SMUserGroup g ON g.GroupId = u.GroupId
            WHERE u.UserId = ?";
    $stmt = sqlsrv_query($conn, $sql, [$userId]);
    if ($stmt === false) {
        throw new RuntimeException('Gagal memuat identitas pengguna');
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    if (!$row) {
        throw new RuntimeException('Identitas pengguna tidak ditemukan');
    }

    $groupName = trim((string)($row['GroupName'] ?? ''));
    return [
        'user_id' => (int)$row['UserId'],
        'emp_id' => (int)($row['EmpId'] ?? 0),
        'group_name' => $groupName,
    ];
}

/**
 * Build ticket visibility SQL and parameters for current identity.
 * Participant access uses account UserId; assignment uses employee EmpId.
 *
 * @param array{user_id:int,emp_id:int} $identity
 * @param string $ticketAlias SQL alias for dbo.tickets.
 * @return array{sql:string,params:array<int,int>}
 */
function ticket_chat_visibility(array $identity, string $ticketAlias = 't'): array
{
    // Chat audience is involvement-based. Administrator management rights are handled separately.
    $clauses = [
        "$ticketAlias.creator_id = ?",
        "EXISTS (SELECT 1 FROM dbo.ticket_messages tm_part
                 WHERE tm_part.ticket_id = $ticketAlias.ticket_id AND tm_part.sender_id = ?)",
    ];
    $params = [$identity['user_id'], $identity['user_id']];

    if ($identity['emp_id'] > 0) {
        $clauses[] = "$ticketAlias.assigned_to = ?";
        $params[] = $identity['emp_id'];
    }

    return ['sql' => '(' . implode(' OR ', $clauses) . ')', 'params' => $params];
}

/**
 * Verify access to one ticket without changing participant state.
 *
 * @param resource $conn Active SQL Server connection.
 * @param int $ticketId Ticket primary key.
 * @param array{user_id:int,emp_id:int} $identity
 * @return bool
 * @throws RuntimeException When authorization query fails.
 */
function ticket_chat_can_access($conn, int $ticketId, array $identity): bool
{
    $visibility = ticket_chat_visibility($identity);
    $sql = "SELECT TOP 1 1 AS allowed
            FROM dbo.tickets t
            WHERE t.ticket_id = ? AND {$visibility['sql']}";
    $params = array_merge([$ticketId], $visibility['params']);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        throw new RuntimeException('Gagal memeriksa akses ticket');
    }

    $allowed = (bool)sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $allowed;
}
