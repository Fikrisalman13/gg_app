/* 
   Daftarkan menu Serah Terima di sidebar dinamis.
   Jalankan di database aplikasi, lalu berikan akses group lain melalui menu hak akses jika diperlukan.
*/

DECLARE @MenuName NVARCHAR(100) = N'Serah Terima';
DECLARE @MenuUrl NVARCHAR(255) = N'/gg_app/pages/form_it/Serah_terima/list_serah_terima.php';
DECLARE @MenuIcon NVARCHAR(100) = N'fas fa-handshake';
DECLARE @ParentMenuId INT;
DECLARE @MenuId INT;

SELECT TOP 1 @ParentMenuId = ParentMenuId
FROM dbo.SMMenu
WHERE MenuUrl = N'/gg_app/pages/form_it/list_form.php'
   OR MenuName = N'List Form'
ORDER BY MenuId;

SELECT TOP 1 @MenuId = MenuId
FROM dbo.SMMenu
WHERE MenuUrl = @MenuUrl
   OR MenuName = @MenuName;

IF @MenuId IS NULL
BEGIN
    IF COLUMNPROPERTY(OBJECT_ID(N'dbo.SMMenu'), N'MenuId', 'IsIdentity') = 1
    BEGIN
        INSERT INTO dbo.SMMenu (MenuName, MenuUrl, MenuIcon, ParentMenuId)
        VALUES (@MenuName, @MenuUrl, @MenuIcon, @ParentMenuId);

        SET @MenuId = CONVERT(INT, SCOPE_IDENTITY());
    END
    ELSE
    BEGIN
        SELECT @MenuId = ISNULL(MAX(MenuId), 0) + 1
        FROM dbo.SMMenu;

        INSERT INTO dbo.SMMenu (MenuId, MenuName, MenuUrl, MenuIcon, ParentMenuId)
        VALUES (@MenuId, @MenuName, @MenuUrl, @MenuIcon, @ParentMenuId);
    END
END
ELSE
BEGIN
    UPDATE dbo.SMMenu
    SET MenuUrl = @MenuUrl,
        MenuIcon = @MenuIcon,
        ParentMenuId = COALESCE(@ParentMenuId, ParentMenuId)
    WHERE MenuId = @MenuId;
END;

IF NOT EXISTS (
    SELECT 1
    FROM dbo.SMGroupTrustee
    WHERE GroupId = 1
      AND MenuId = @MenuId
)
BEGIN
    INSERT INTO dbo.SMGroupTrustee (GroupId, MenuId, CanView, CanAdd, CanEdit, CanDelete)
    VALUES (1, @MenuId, 1, 1, 1, 1);
END;
