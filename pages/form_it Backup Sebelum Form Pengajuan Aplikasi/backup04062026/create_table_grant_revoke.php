<?php
$serverName = "202.150.136.51";
$connectionInfo = [
    "Database" => "GG",
    "UID" => "sa",
    "PWD" => "rahasiaIT2020",
    "TrustServerCertificate" => true
];
$conn = sqlsrv_connect($serverName, $connectionInfo);
if (!$conn) {
    die("Connection failed: " . print_r(sqlsrv_errors(), true));
}

$sql = "
IF NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[Form_Grant_Revoke_Trustee]') AND type in (N'U'))
BEGIN
    CREATE TABLE [dbo].[Form_Grant_Revoke_Trustee](
        [id] [int] IDENTITY(1,1) NOT NULL,
        [ticket] [varchar](50) NOT NULL,
        [nama_pemohon] [varchar](100) NULL,
        [jabatan] [varchar](100) NULL,
        [departemen] [varchar](100) NULL,
        [area] [varchar](100) NULL,
        [tgl_pengajuan] [date] NULL,
        [jenis_permintaan] [varchar](20) NULL, -- UPDATE: changed from 10 to 20 just in case
        [menu_akses] [nvarchar](max) NULL,
        [keterangan] [nvarchar](max) NULL,
        [status_ticket] [varchar](50) DEFAULT 'Pending',
        [kategori] [varchar](50) DEFAULT 'Grant/Revoke Trustee',
        [created_at] [datetime] DEFAULT GETDATE(),
        [created_by] [varchar](100) NULL,
        [updated_at] [datetime] NULL,
        [updated_by] [varchar](100) NULL,
        CONSTRAINT [PK_Form_Grant_Revoke_Trustee] PRIMARY KEY CLUSTERED 
        (
            [id] ASC
        )
    )
    PRINT 'Table Form_Grant_Revoke_Trustee created successfully.'
END
ELSE
BEGIN
    PRINT 'Table Form_Grant_Revoke_Trustee already exists.'
END
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die("Error creating table: " . print_r(sqlsrv_errors(), true));
} else {
    echo "Table check/creation completed.\n";
    while ($row = sqlsrv_next_result($stmt)) {
        // consumes results
    }
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
