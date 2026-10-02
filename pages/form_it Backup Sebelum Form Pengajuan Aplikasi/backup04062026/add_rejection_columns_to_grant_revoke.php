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
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[Form_Grant_Revoke_Trustee]') AND name = 'rejected_by')
BEGIN
    ALTER TABLE [dbo].[Form_Grant_Revoke_Trustee] ADD [rejected_by] [varchar](100) NULL;
    PRINT 'Column rejected_by added.';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[Form_Grant_Revoke_Trustee]') AND name = 'rejection_reason')
BEGIN
    ALTER TABLE [dbo].[Form_Grant_Revoke_Trustee] ADD [rejection_reason] [nvarchar](max) NULL;
    PRINT 'Column rejection_reason added.';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[Form_Grant_Revoke_Trustee]') AND name = 'rejection_date')
BEGIN
    ALTER TABLE [dbo].[Form_Grant_Revoke_Trustee] ADD [rejection_date] [datetime] NULL;
    PRINT 'Column rejection_date added.';
END
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die("Error adding columns: " . print_r(sqlsrv_errors(), true));
} else {
    echo "Columns checked/added successfully.\n";
    while ($row = sqlsrv_next_result($stmt)) {
        // consumes results
    }
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
