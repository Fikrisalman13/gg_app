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
IF NOT EXISTS (
  SELECT * 
  FROM   sys.columns 
  WHERE  object_id = OBJECT_ID(N'[dbo].[Form_Grant_Revoke_Trustee]') 
         AND name = 'area'
)
BEGIN
    ALTER TABLE [dbo].[Form_Grant_Revoke_Trustee] ADD [area] VARCHAR(100) NULL;
    PRINT 'Column [area] added to Form_Grant_Revoke_Trustee.';
END
ELSE
BEGIN
    PRINT 'Column [area] already exists.';
END
";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    die("Error altering table: " . print_r(sqlsrv_errors(), true));
} else {
    echo "Migration completed.\n";
    while ($row = sqlsrv_next_result($stmt)) {
        // consumes results
    }
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>
