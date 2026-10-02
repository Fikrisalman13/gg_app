<?php
session_start();
require_once __DIR__ . '/../../koneksi.php';
require_once __DIR__ . '/routing_filter_template_lib.php';

$username = outputVpkCurrentUsername();
if ($username === '') {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: laporan_routing_692.php');
    exit;
}

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['template_id'] ?? 0);
$name = trim((string) ($_POST['template_name'] ?? ''));
$groupId = (string) ($_POST['routing_group_id'] ?? '');
$groupName = trim((string) ($_POST['routing_group_name'] ?? ''));
$routingIds = $_POST['routing_ids'] ?? [];
$routingLabels = $_POST['routing_labels'] ?? [];
if (!is_array($routingIds)) { $routingIds = preg_split('/[\s,]+/', (string) $routingIds); }
if (!is_array($routingLabels)) { $routingLabels = []; }
$routingIds = array_values(array_unique(array_filter(array_map('strval', $routingIds), fn($id) => preg_match('/^\d+$/', $id))));
$routingLabels = array_values(array_map('strval', $routingLabels));

if ($action === 'delete') {
    $ok = outputVpkDeleteRoutingTemplate($conn, $username, $id);
    $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Template berhasil dihapus.' : 'Template gagal dihapus.';
    header('Location: laporan_routing_692.php');
    exit;
}

if ($name === '' || !preg_match('/^\d+$/', $groupId) || !$routingIds || count($routingIds) > 5) {
    $_SESSION['error'] = 'Template wajib punya nama, grup routing, dan routing maksimal 5.';
    header('Location: laporan_routing_692.php');
    exit;
}

if ($action === 'save') {
    $ok = outputVpkSaveRoutingTemplate($conn, $username, $name, $groupId, $groupName, $routingIds, $routingLabels);
} elseif ($action === 'update' && $id > 0) {
    $ok = outputVpkUpdateRoutingTemplate($conn, $username, $id, $name, $groupId, $groupName, $routingIds, $routingLabels);
} else {
    $ok = false;
}

$_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Template berhasil disimpan.' : 'Template gagal disimpan. Nama template mungkin sudah ada.';
header('Location: laporan_routing_692.php');
exit;
