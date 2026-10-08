<?php
// pages/resep_obat/master_artikel_grey/run_alter_drop_unique.php
require_once __DIR__ . '/../../../../koneksi.php';

echo "<h2>Dropping UNIQUE constraint for master_artikel_grey</h2>";

// Script to find the constraint name and drop it
$sql = "
DECLARE @ConstraintName NVARCHAR(128);
DECLARE @Sql NVARCHAR(MAX);

SELECT @ConstraintName = name
FROM sys.key_constraints
WHERE parent_object_id = OBJECT_ID('dbo.master_artikel_grey')
  AND type = 'UQ'; -- Unique Constraint

-- If not found in key_constraints, check indexes
IF @ConstraintName IS NULL
BEGIN
    SELECT @ConstraintName = name
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.master_artikel_grey')
      AND is_unique = 1
      AND is_primary_key = 0;
END

IF @ConstraintName IS NOT NULL
BEGIN
    SET @Sql = 'ALTER TABLE dbo.master_artikel_grey DROP CONSTRAINT ' + @ConstraintName;
    -- If it's an index, use DROP INDEX
    -- Assuming it was created as a Constraint via CREATE TABLE ... UNIQUE
    -- But checking if it's an index just in case.
    
    -- Actually, simpler approach for SQL Server if we don't know the random name:
    -- But we need to be careful.
    
    PRINT 'Found constraint: ' + @ConstraintName;
    EXEC sp_executesql @Sql;
    PRINT 'Constraint dropped.';
END
ELSE
BEGIN
    PRINT 'No Unique Constraint found (or already dropped).';
END
";

// Execute
$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    echo "Error execution (Method 1). Trying Method 2 (Drop Index if separate)...<br>";
    // Sometimes unique constraint is just a unique index
    $sql2 = "
    DECLARE @IndexName NVARCHAR(128);
    SELECT @IndexName = name FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.master_artikel_grey') AND is_unique = 1 AND is_primary_key = 0;
    IF @IndexName IS NOT NULL
    BEGIN
        DECLARE @Sql2 NVARCHAR(MAX);
        SET @Sql2 = 'DROP INDEX ' + @IndexName + ' ON dbo.master_artikel_grey';
        EXEC sp_executesql @Sql2;
    END
    ";
    $stmt2 = sqlsrv_query($conn, $sql2);
    if ($stmt2 === false) {
        die(print_r(sqlsrv_errors(), true));
    }
}

echo "Done.";
?>
