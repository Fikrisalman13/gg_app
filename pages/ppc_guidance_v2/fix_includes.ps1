# Fix include paths for includes/header, sidebar, footer in ppc_guidance
$root = "c:\xampp\htdocs\gg_app"
$ppcDir = "$root\pages\ppc_guidance"

Get-ChildItem $ppcDir -Recurse -Filter "*.php" | ForEach-Object {
    $file = $_.FullName
    $relPath = $file.Substring($root.Length + 1)
    $depth = ($relPath -split '\\').Length - 1  # depth from gg_app/

    $content = Get-Content $file -Raw
    $changed = $false

    if ($depth -eq 3) {
        # resep_obat/ etc — need ../../../includes/
        # ../../includes/ -> ../../../includes/
        if ($content -match "'\.\./\.\./includes/") {
            $content = $content -replace "'\.\./\.\./includes/", "'../../../includes/"
            $changed = $true
            Write-Host "FIXED ../../includes/ -> ../../../includes/ in $relPath"
        }
    }

    if ($depth -ge 4) {
        # experiment/, master_*/ etc — need ../../../../includes/
        # no __DIR__: ../../../includes/ -> ../../../../includes/
        if ($content -match "'\.\./\.\./\.\./includes/") {
            $content = $content -replace "'\.\./\.\./\.\./includes/", "'../../../../includes/"
            $changed = $true
            Write-Host "FIXED ../../../includes/ -> ../../../../includes/ in $relPath"
        }
        # with __DIR__: __DIR__ . '/../../../includes/' -> __DIR__ . '/../../../../includes/'
        if ($content -match "__DIR__\s*\.\s*'/\.\./\.\./\.\./includes/") {
            $content = $content -replace "__DIR__\s*\.\s*'/\.\./\.\./\.\./includes/", "__DIR__ . '/../../../../includes/"
            $changed = $true
            Write-Host "FIXED __DIR__.'/../../../includes/ -> __DIR__.'/../../../../includes/ in $relPath"
        }
    }

    if ($changed) {
        Set-Content $file $content -NoNewline
    }
}

Write-Host "`nDone!"
