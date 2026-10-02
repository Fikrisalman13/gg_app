<?php
// Deprecated endpoint. Gunakan save_washing2_watt.php / save_washing2_steam.php / save_washing2_water.php
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Endpoint tidak digunakan. Gunakan endpoint save per form Washing2.']);
