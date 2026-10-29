<?php
http_response_code(503);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => false,
    'message' => 'Endpoint Purchasing belum tersedia.'
], JSON_UNESCAPED_UNICODE);
