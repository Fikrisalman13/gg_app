<?php
/**
 * Short barcode token helper for Form Umum.
 * Keeps TM-U220 barcode payload short while preserving original ticket flow.
 */

function formUmumBarcodeNormalizeTicket($ticket)
{
    $ticket = strtoupper(trim((string) $ticket));
    return preg_match('/^(IKS|IKP|IPC)-[A-Z0-9-]+$/', $ticket) ? $ticket : '';
}

function formUmumBarcodeNormalizeToken($token)
{
    $token = strtoupper(trim((string) $token));
    return preg_match('/^[A-Z2-9]{6}$/', $token) ? $token : '';
}

function formUmumBarcodeTicketType($ticket)
{
    $ticket = formUmumBarcodeNormalizeTicket($ticket);
    if ($ticket === '') {
        return '';
    }

    return substr($ticket, 0, 3);
}

function formUmumBarcodeEnsureTable($conn)
{
    $sql = "
IF OBJECT_ID('dbo.Form_Umum_Barcode_Token', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Form_Umum_Barcode_Token (
        id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        barcode_token VARCHAR(6) NOT NULL,
        ticket VARCHAR(50) NOT NULL,
        form_type VARCHAR(3) NOT NULL,
        created_at DATETIME NOT NULL CONSTRAINT DF_Form_Umum_Barcode_Token_created_at DEFAULT GETDATE(),
        created_by VARCHAR(150) NULL,
        CONSTRAINT UQ_Form_Umum_Barcode_Token_token UNIQUE (barcode_token),
        CONSTRAINT UQ_Form_Umum_Barcode_Token_ticket UNIQUE (ticket)
    );
END";

    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        return false;
    }
    sqlsrv_free_stmt($stmt);
    return true;
}

function formUmumBarcodeTicketExists($conn, $ticket)
{
    $ticket = formUmumBarcodeNormalizeTicket($ticket);
    if ($ticket === '') {
        return false;
    }

    if (strpos($ticket, 'IPC-') === 0) {
        $sql = "SELECT TOP 1 ticket FROM Form_Umum_Izin_Pulang_Cepat WHERE ticket = ?";
    } else {
        $sql = "SELECT TOP 1 ticket FROM Form_Umum_Izin_Keluar_Pabrik WHERE ticket = ?";
    }

    $stmt = sqlsrv_query($conn, $sql, [$ticket]);
    if ($stmt === false) {
        return false;
    }
    $exists = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) !== null;
    sqlsrv_free_stmt($stmt);
    return $exists;
}

function formUmumBarcodeRandomToken()
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $token = '';
    for ($i = 0; $i < 6; $i++) {
        $token .= $alphabet[random_int(0, 31)];
    }
    return $token;
}

function formUmumBarcodeGetOrCreateToken($conn, $ticket, $createdBy = null)
{
    $ticket = formUmumBarcodeNormalizeTicket($ticket);
    if ($ticket === '' || !formUmumBarcodeEnsureTable($conn) || !formUmumBarcodeTicketExists($conn, $ticket)) {
        return '';
    }

    $stmt = sqlsrv_query($conn, "SELECT barcode_token FROM Form_Umum_Barcode_Token WHERE ticket = ?", [$ticket]);
    if ($stmt === false) {
        return '';
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    if ($row && formUmumBarcodeNormalizeToken($row['barcode_token'] ?? '') !== '') {
        return strtoupper($row['barcode_token']);
    }

    $formType = formUmumBarcodeTicketType($ticket);
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $token = formUmumBarcodeRandomToken();
        $insert = sqlsrv_query(
            $conn,
            "INSERT INTO Form_Umum_Barcode_Token (barcode_token, ticket, form_type, created_by) VALUES (?, ?, ?, ?)",
            [$token, $ticket, $formType, $createdBy]
        );
        if ($insert !== false) {
            sqlsrv_free_stmt($insert);
            return $token;
        }
    }

    return '';
}

function formUmumBarcodeResolveTicket($conn, $input)
{
    $ticket = formUmumBarcodeNormalizeTicket($input);
    if ($ticket !== '') {
        return $ticket;
    }

    $token = formUmumBarcodeNormalizeToken($input);
    if ($token === '' || !formUmumBarcodeEnsureTable($conn)) {
        return '';
    }

    $stmt = sqlsrv_query($conn, "SELECT ticket FROM Form_Umum_Barcode_Token WHERE barcode_token = ?", [$token]);
    if ($stmt === false) {
        return '';
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    return $row ? formUmumBarcodeNormalizeTicket($row['ticket'] ?? '') : '';
}
