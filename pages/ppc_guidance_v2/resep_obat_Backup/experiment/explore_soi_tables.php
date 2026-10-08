<?php
include __DIR__ . '/../../../../koneksi3.php';

echo "<h3>1. insohd columns</h3>";
$stmt = $conn3->prepare("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'insohd' ORDER BY ordinal_position");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($rows, true) . "</pre>";

echo "<h3>2. pdiso columns</h3>";
$stmt = $conn3->prepare("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'pdiso' ORDER BY ordinal_position");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($rows, true) . "</pre>";

echo "<h3>3. pdproductionhd columns</h3>";
$stmt = $conn3->prepare("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'pdproductionhd' ORDER BY ordinal_position");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($rows, true) . "</pre>";
?>
