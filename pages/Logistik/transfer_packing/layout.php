<?php
function transferPackingAppRoot(): string
{
    return rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? 'C:/xampp/htdocs'), '/\\') . '/gg_app';
}

function transferPackingHeader(): void
{
    global $conn;

    $packingConn = $conn ?? null;
    $appRoot = transferPackingAppRoot();

    include $appRoot . '/koneksi.php';
    include $appRoot . '/includes/header.php';
    include $appRoot . '/includes/sidebar.php';

    if ($packingConn !== null) {
        $conn = $packingConn;
    }
}

function transferPackingFooter(): void
{
    $appRoot = transferPackingAppRoot();

    // Footer includes ticket_chat.php, which expects $conn to be a SQL Server connection.
    include $appRoot . '/koneksi.php';
    include $appRoot . '/includes/footer.php';
}
?>
