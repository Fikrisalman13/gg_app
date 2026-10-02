<?php
echo "<h1>Server Requirement Check for Excel Tool</h1>";

// 1. Cek Versi PHP
echo "<h3>1. PHP Version</h3>";
echo "Current PHP Version: " . phpversion() . "<br>";
if (version_compare(phpversion(), '7.4.0', '>=')) {
    echo "<span style='color:green'>[OK] PHP version is sufficient.</span><br>";
} else {
    echo "<span style='color:red'>[ERROR] PHP 7.4 or higher is required.</span><br>";
}

// 2. Cek Extensions Wajib untuk PhpSpreadsheet
$required_extensions = [
    'zip',
    'xml',
    'gd',
    'mbstring',
    'dom',
    'xmlwriter',
    'xmlreader'
];

echo "<h3>2. Required Extensions</h3>";
$all_ok = true;
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<span style='color:green'>[OK] Extension '$ext' is loaded.</span><br>";
    } else {
        echo "<span style='color:red'>[MISSING] Extension '$ext' is NOT loaded! Please install/enable it.</span><br>";
        $all_ok = false;
    }
}

// 3. Cek Composer / Vendor
echo "<h3>3. Composer Dependencies</h3>";
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    echo "<span style='color:green'>[OK] vendor/autoload.php found.</span><br>";
} else {
    echo "<span style='color:red'>[ERROR] vendor/autoload.php NOT found. Did you run 'composer install' on the server?</span><br>";
    $all_ok = false;
}

// 4. Cek Tulis Folder (Optional check for tmp)
echo "<h3>4. Temporary Directory</h3>";
$tmp = sys_get_temp_dir();
if (is_writable($tmp)) {
    echo "<span style='color:green'>[OK] Temp dir ($tmp) is writable.</span><br>";
} else {
    echo "<span style='color:orange'>[WARNING] Temp dir ($tmp) might not be writable. Uploads might fail.</span><br>";
}

echo "<hr>";
if ($all_ok) {
    echo "<h2 style='color:green'>System seems ready!</h2>";
} else {
    echo "<h2 style='color:red'>Please fix the errors above.</h2>";
}
?>
