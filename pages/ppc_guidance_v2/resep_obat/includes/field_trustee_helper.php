<?php

/**
 * Loads read-only recipe field keys for current user's subbagian.
 *
 * Admin GroupId 1 bypasses trustee settings.
 *
 * @param resource $conn SQL Server connection.
 * @param string $username Authenticated username.
 * @param int $groupId Authenticated group ID.
 * @return array<string, bool> Field key map where true means read-only.
 */
function loadPpcResepFieldTrustee($conn, string $username, int $groupId): array
{
    if ($groupId === 1 || $username === '') {
        return [];
    }

    $statement = sqlsrv_query(
        $conn,
        'SELECT t.field_key, t.is_readonly
         FROM dbo.SMUserMs u
         LEFT JOIN dbo.m_emp e ON u.EmpId = e.id_emp
         INNER JOIN dbo.resep_field_trustee t ON t.subbag_id = e.id_subbag
         WHERE u.UserName = ?',
        [$username]
    );
    if ($statement === false) {
        throw new RuntimeException('Field trustee gagal dimuat.');
    }

    $permissions = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        $permissions[(string) $row['field_key']] = (int) $row['is_readonly'] === 1;
    }
    return $permissions;
}

/** Returns whether one PPC recipe field is read-only. */
function isPpcResepFieldReadonly(array $permissions, string $fieldKey): bool
{
    return !empty($permissions[$fieldKey]);
}
