<?php

require_once __DIR__ . '/functions.php';

try {
    $conn = getSqlsrvConnection('gg');
    ensureTablesExist($conn);
    echo 'Setup MKO-ACCWARNA berhasil.';
} catch (Throwable $e) {
    http_response_code(500);
    echo $e->getMessage();
}
