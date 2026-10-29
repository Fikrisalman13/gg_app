<?php
http_response_code(503);
header('Content-Type: text/plain; charset=utf-8');
header('Retry-After: 3600');
echo 'Halaman Issues Purchasing sedang dinonaktifkan sementara.';
