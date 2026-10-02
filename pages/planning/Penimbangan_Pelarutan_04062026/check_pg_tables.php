<?php
include '../../../koneksi3.php';

echo "Table: pdwheelms\n";
try {
    $stmt = $conn3->query("SELECT * FROM pdwheelms LIMIT 1");
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            foreach ($row as $key => $val) {
                echo "$key\n";
            }
        } else {
            echo "Table empty.\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n-------------------\n";
echo "Table: pddowntimems\n";
try {
    $stmt = $conn3->query("SELECT * FROM pddowntimems LIMIT 1");
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            foreach ($row as $key => $val) {
                echo "$key\n";
            }
        } else {
             echo "Table empty.\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
