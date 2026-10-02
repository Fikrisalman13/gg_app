<?php
/**
 * ============================================
 * TEST/CONTOH PENGGUNAAN CUTTING CALCULATION
 * ============================================
 * 
 * File ini menunjukkan contoh implementasi perhitungan cutting
 * dengan berbagai kombinasi UOM CP dan Type Counter
 */

// Include helper functions
require_once 'includes/cutting_helper.php';

// ============================================
// SCENARIO 1: UOM CP = M, Type Counter = M
// ============================================
// Panjang: 400 M, STD: 30 M, MIN: 10 M, MAX: 40 M, Toleransi: 30 CM
// Cacat: 39-49 M
// Perhitungan dalam Meter
// Expected: ~13 pcs potongan 30.30 M

$config_1 = [
    'panjang_awal' => 400,
    'panjang_akhir' => 400,
    'std' => 30,
    'min' => 10,
    'max' => 40,
    'toleransi' => 30, // CM
    'uom_cp' => 'M',
    'type_counter' => 'M',
    'cacat_list' => [
        ['dari' => 39, 'sampai' => 49, 'status' => 'CACAT KELIM']
    ]
];

$result_1 = performCutting($config_1);

echo "=== SCENARIO 1: M → M ===\n";
echo "Input:\n";
echo "  Panjang: {$result_1['config']['panjang_awal']} - {$result_1['config']['panjang_akhir']} M\n";
echo "  STD: {$result_1['config']['std']} M, MIN: 10 M, MAX: 40 M\n";
echo "  Toleransi: {$result_1['config']['toleransi_cm']} CM\n";
echo "  STD + Toleransi: {$result_1['config']['std_dengan_tol']} M\n";
echo "  UOM CP: {$result_1['config']['uom_cp']}, Type Counter: {$result_1['config']['type_counter']}\n";
echo "\nHasil:\n";
echo "  Total PCS: {$result_1['total_pcs']}\n";
echo "  Total Panjang: {$result_1['total_panjang']} M\n";
echo "\nRingkasan per Kategori:\n";
foreach ($result_1['summary'] as $kategori => $data) {
    echo "  {$kategori}: {$data['pcs']} pcs, Total: {$data['total_panjang']} M\n";
}
echo "\nDetail Cutting (First 3):\n";
for ($i = 0; $i < min(3, count($result_1['cutting'])); $i++) {
    $cut = $result_1['cutting'][$i];
    echo "  Potongan " . ($i + 1) . ": {$cut['start_pos']} - {$cut['end_pos']} M ({$cut['hasil_cutting']} M) = {$cut['kategori']}\n";
}
if (count($result_1['cutting']) > 3) {
    echo "  ... dan " . (count($result_1['cutting']) - 3) . " potongan lainnya\n";
}
echo "\n";

// ============================================
// SCENARIO 2: UOM CP = M, Type Counter = Y
// ============================================
// Panjang: 400 M, STD: 30 M, MIN: 10 M, MAX: 40 M, Toleransi: 30 CM
// Perhitungan dalam Yard
// Expected: ~13 pcs potongan 33.14 Y

$config_2 = [
    'panjang_awal' => 400,
    'panjang_akhir' => 400,
    'std' => 30,
    'min' => 10,
    'max' => 40,
    'toleransi' => 30, // CM
    'uom_cp' => 'M',
    'type_counter' => 'Y', // Counter dalam Yard
    'cacat_list' => []
];

$result_2 = performCutting($config_2);

echo "=== SCENARIO 2: M → Y ===\n";
echo "Input:\n";
echo "  Panjang: {$result_2['config']['panjang_awal']} M (display) = " . 
     number_format($result_2['config']['panjang_awal'] / 0.9144, 3) . " Y (kalkulasi)\n";
echo "  STD: {$result_2['config']['std']} M, MIN: 10 M, MAX: 40 M\n";
echo "  Toleransi: {$result_2['config']['toleransi_cm']} CM\n";
echo "  Conversion Factor: {$result_2['config']['conversion_factor']}\n";
echo "  UOM CP: {$result_2['config']['uom_cp']}, Type Counter: {$result_2['config']['type_counter']}\n";
echo "\nHasil:\n";
echo "  Total PCS: {$result_2['total_pcs']}\n";
echo "  Total Panjang (Yard): " . number_format($result_2['total_panjang'], 3) . " Y\n";
echo "\nRingkasan per Kategori:\n";
foreach ($result_2['summary'] as $kategori => $data) {
    echo "  {$kategori}: {$data['pcs']} pcs, Total: " . number_format($data['total_panjang'], 3) . " Y\n";
}
echo "\nDetail Cutting (First 3):\n";
for ($i = 0; $i < min(3, count($result_2['cutting'])); $i++) {
    $cut = $result_2['cutting'][$i];
    echo "  Potongan " . ($i + 1) . ": " . number_format($cut['start_pos'], 3) . " - " . 
         number_format($cut['end_pos'], 3) . " Y (" . number_format($cut['hasil_cutting'], 3) . " Y) = {$cut['kategori']}\n";
}
echo "\n";

// ============================================
// SCENARIO 3: UOM CP = Y, Type Counter = M
// ============================================
// Panjang: 400 M, STD: 30 Y, MIN: 10 Y, MAX: 40 Y, Toleransi: 30 CM
// Perhitungan dalam Meter
// Expected: ~14-15 pcs potongan 27.43 M

$config_3 = [
    'panjang_awal' => 400,
    'panjang_akhir' => 400,
    'std' => 30, // Dalam Yard
    'min' => 10, // Dalam Yard
    'max' => 40, // Dalam Yard
    'toleransi' => 30, // CM
    'uom_cp' => 'Y', // Potongan dalam Yard
    'type_counter' => 'M', // Counter dalam Meter
    'cacat_list' => []
];

$result_3 = performCutting($config_3);

echo "=== SCENARIO 3: Y → M ===\n";
echo "Input:\n";
echo "  Panjang: {$result_3['config']['panjang_awal']} - {$result_3['config']['panjang_akhir']} M\n";
echo "  STD: {$result_3['config']['std']} Y, MIN: 10 Y, MAX: 40 Y\n";
echo "  Toleransi: {$result_3['config']['toleransi_cm']} CM\n";
echo "  STD + Toleransi: " . number_format($result_3['config']['std_dengan_tol'], 6) . " Y\n";
echo "  Conversion Factor: {$result_3['config']['conversion_factor']}\n";
echo "  UOM CP: {$result_3['config']['uom_cp']}, Type Counter: {$result_3['config']['type_counter']}\n";
echo "\nHasil:\n";
echo "  Total PCS: {$result_3['total_pcs']}\n";
echo "  Total Panjang (Meter): {$result_3['total_panjang']} M\n";
echo "\nRingkasan per Kategori:\n";
foreach ($result_3['summary'] as $kategori => $data) {
    echo "  {$kategori}: {$data['pcs']} pcs, Total: {$data['total_panjang']} M\n";
}
echo "\nDetail Cutting (First 3):\n";
for ($i = 0; $i < min(3, count($result_3['cutting'])); $i++) {
    $cut = $result_3['cutting'][$i];
    echo "  Potongan " . ($i + 1) . ": {$cut['start_pos']} - {$cut['end_pos']} M ({$cut['hasil_cutting']} M) = {$cut['kategori']}\n";
}
if (count($result_3['cutting']) > 3) {
    echo "  ... dan " . (count($result_3['cutting']) - 3) . " potongan lainnya\n";
}
echo "\n";

// ============================================
// SCENARIO 4: Y → Y (Yard ke Yard)
// ============================================

$config_4 = [
    'panjang_awal' => 400,
    'panjang_akhir' => 400,
    'std' => 30, // Yard
    'min' => 10, // Yard
    'max' => 40, // Yard
    'toleransi' => 30, // CM
    'uom_cp' => 'Y',
    'type_counter' => 'Y',
    'cacat_list' => []
];

$result_4 = performCutting($config_4);

echo "=== SCENARIO 4: Y → Y ===\n";
echo "Input:\n";
echo "  Panjang: {$result_4['config']['panjang_awal']} M = " . 
     number_format($result_4['config']['panjang_awal'] / 0.9144, 3) . " Y\n";
echo "  STD: {$result_4['config']['std']} Y, MIN: 10 Y, MAX: 40 Y\n";
echo "  Toleransi: {$result_4['config']['toleransi_cm']} CM\n";
echo "  UOM CP: {$result_4['config']['uom_cp']}, Type Counter: {$result_4['config']['type_counter']}\n";
echo "\nHasil:\n";
echo "  Total PCS: {$result_4['total_pcs']}\n";
echo "  Total Panjang (Yard): " . number_format($result_4['total_panjang'], 3) . " Y\n";
echo "\nRingkasan per Kategori:\n";
foreach ($result_4['summary'] as $kategori => $data) {
    echo "  {$kategori}: {$data['pcs']} pcs\n";
}
echo "\n";

?>
