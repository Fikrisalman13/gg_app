<?php
function outputVpkCurrentUsername(): string {
    return (string) ($_SESSION['UserName'] ?? '');
}

function outputVpkJsonArray($value): array {
    if (is_array($value)) { return $value; }
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? $decoded : [];
}

function outputVpkGetRoutingTemplates($conn, string $username): array {
    $sql = "SELECT id, username, template_name, routing_group_id, routing_group_name, routing_ids, routing_labels, is_default
            FROM dbo.output_vpk_user_routing_filter_templates
            WHERE username = ? AND is_active = 1
            ORDER BY is_default DESC, template_name ASC";
    $stmt = sqlsrv_query($conn, $sql, [$username]);
    if (!$stmt) { return []; }
    $templates = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $row['routing_ids_array'] = outputVpkJsonArray($row['routing_ids']);
        $row['routing_labels_array'] = outputVpkJsonArray($row['routing_labels']);
        $templates[] = $row;
    }
    return $templates;
}

function outputVpkSaveRoutingTemplate($conn, string $username, string $name, string $groupId, string $groupName, array $routingIds, array $routingLabels): bool {
    $sql = "INSERT INTO dbo.output_vpk_user_routing_filter_templates
            (username, template_name, routing_group_id, routing_group_name, routing_ids, routing_labels, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    return (bool) sqlsrv_query($conn, $sql, [$username, $name, $groupId, $groupName, json_encode(array_values($routingIds)), json_encode(array_values($routingLabels)), $username]);
}

function outputVpkUpdateRoutingTemplate($conn, string $username, int $id, string $name, string $groupId, string $groupName, array $routingIds, array $routingLabels): bool {
    $sql = "UPDATE dbo.output_vpk_user_routing_filter_templates
            SET template_name = ?, routing_group_id = ?, routing_group_name = ?, routing_ids = ?, routing_labels = ?, updated_at = SYSDATETIME(), updated_by = ?
            WHERE id = ? AND username = ? AND is_active = 1";
    $stmt = sqlsrv_query($conn, $sql, [$name, $groupId, $groupName, json_encode(array_values($routingIds)), json_encode(array_values($routingLabels)), $username, $id, $username]);
    return $stmt && sqlsrv_rows_affected($stmt) > 0;
}

function outputVpkDeleteRoutingTemplate($conn, string $username, int $id): bool {
    $sql = "UPDATE dbo.output_vpk_user_routing_filter_templates
            SET is_active = 0, deleted_at = SYSDATETIME(), deleted_by = ?
            WHERE id = ? AND username = ? AND is_active = 1";
    $stmt = sqlsrv_query($conn, $sql, [$username, $id, $username]);
    return $stmt && sqlsrv_rows_affected($stmt) > 0;
}
