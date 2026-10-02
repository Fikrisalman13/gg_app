<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Server Environment Check</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current Dir: " . __DIR__ . "<br>";

$vendorPath = __DIR__ . '/../../vendor/autoload.php';
echo "Checking Vendor Path: " . $vendorPath . "<br>";

if (file_exists($vendorPath)) {
    echo "<b style='color:green'>Vendor Autoload Found!</b><br>";
    require $vendorPath;
    
    if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
        echo "<b style='color:green'>SUCCESS: PhpSpreadsheet Class Found!</b>";
    } else {
        echo "<b style='color:red'>FAIL: Autoload loaded, but PhpSpreadsheet class NOT found.</b><br>";
        echo "Please check if folder <code>vendor/phpoffice</code> exists and has permissions.";
    }
} else {
    echo "<b style='color:red'>FAIL: Vendor Autoload NOT found.</b>";
}
?>
