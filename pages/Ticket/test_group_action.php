<?php
// ===================================================
// 1. SIAPKAN REQUEST PALSU UNTUK group_action.php
// ===================================================
$_POST['action'] = 'add_group';
$_POST['group_name'] = 'Test via Script ' . time();

// ===================================================
// 2. SIMULASIKAN SESI LOGIN
// ===================================================
session_start();
$_SESSION['UserId'] = 1; // Simulate logged in user

// ===================================================
// 3. EKSEKUSI FILE TARGET DAN TANGKAP OUTPUT
// ===================================================
ob_start();
include 'group_action.php';
$output = ob_get_clean();

echo "=== Testing group_action.php ===\n";
echo "Output:\n";
echo $output;
echo "\n";

// ===================================================
// 4. PARSE HASIL JSON UNTUK VALIDASI
// ===================================================
$result = json_decode($output, true);
if ($result) {
    echo "\nParsed Result:\n";
    echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
    echo "Message: " . ($result['message'] ?? 'N/A') . "\n";
} else {
    echo "\nFailed to parse JSON!\n";
}
?>
