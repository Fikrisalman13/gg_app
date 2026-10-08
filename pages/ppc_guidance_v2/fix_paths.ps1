# Fix koneksi.php and koneksi3.php paths in ppc_guidance
$root = "c:\xampp\htdocs\gg_app"
$ppcDir = "$root\pages\ppc_guidance"

Get-ChildItem $ppcDir -Recurse -Filter "*.php" | ForEach-Object {
    $file = $_.FullName
    $relPath = $file.Substring($root.Length + 1)
    $depth = ($relPath -split '\\').Length - 1  # depth from gg_app/
    
    $content = Get-Content $file -Raw
    
    $changed = $false
    
    if ($depth -eq 3) {
        # Files in resep_obat/ or resep_lipat/ (depth 3 from gg_app)
        # Need: ../../../koneksi.php
        
        # Wrong: '../../koneksi.php' -> '../../../koneksi.php'
        if ($content -match "'\.\./\.\./koneksi\.php'") {
            $content = $content -replace "'\.\./\.\./koneksi\.php'(?!')", "'../../../koneksi.php'"
            $changed = $true
            Write-Host "FIXED ../../koneksi.php -> ../../../koneksi.php in $relPath"
        }
        # Wrong: '/../../koneksi.php' -> '/../../../koneksi.php'
        if ($content -match "'/\.\./\.\./koneksi\.php'") {
            $content = $content -replace "'/\.\./\.\./koneksi\.php'(?!')", "'/../../../koneksi.php'"
            $changed = $true
            Write-Host "FIXED /../../koneksi.php -> /../../../koneksi.php in $relPath"
        }
        # Wrong: '../../koneksi3.php' -> '../../../koneksi3.php'
        if ($content -match "'\.\./\.\./koneksi3\.php'") {
            $content = $content -replace "'\.\./\.\./koneksi3\.php'(?!')", "'../../../koneksi3.php'"
            $changed = $true
            Write-Host "FIXED ../../koneksi3.php -> ../../../koneksi3.php in $relPath"
        }
        if ($content -match "'/\.\./\.\./koneksi3\.php'") {
            $content = $content -replace "'/\.\./\.\./koneksi3\.php'(?!')", "'/../../../koneksi3.php'"
            $changed = $true
            Write-Host "FIXED /../../koneksi3.php -> /../../../koneksi3.php in $relPath"
        }
    }
    
    if ($depth -ge 4) {
        # Files in subdirectories of resep_obat/ (depth 4+ from gg_app)
        # Need: ../../../../koneksi.php
        
        # Wrong with __DIR__: '/../../../../../koneksi.php' -> '/../../../../koneksi.php'
        if ($content -match "'/\.\./\.\./\.\./\.\./\.\./koneksi\.php'") {
            $content = $content -replace "'/\.\./\.\./\.\./\.\./\.\./koneksi\.php'(?!')", "'/../../../../koneksi.php'"
            $changed = $true
            Write-Host "FIXED /../../../../../koneksi.php -> /../../../../koneksi.php in $relPath"
        }
        # Wrong no __DIR__: '../../../../../koneksi.php' -> '../../../../koneksi.php'
        if ($content -match "'\.\./\.\./\.\./\.\./\.\./koneksi\.php'") {
            $content = $content -replace "'\.\./\.\./\.\./\.\./\.\./koneksi\.php'(?!')", "'../../../../koneksi.php'"
            $changed = $true
            Write-Host "FIXED ../../../../../koneksi.php -> ../../../../koneksi.php in $relPath"
        }
        # Wrong with __DIR__: '/../../../../../koneksi3.php' -> '/../../../../koneksi3.php'
        if ($content -match "'/\.\./\.\./\.\./\.\./\.\./koneksi3\.php'") {
            $content = $content -replace "'/\.\./\.\./\.\./\.\./\.\./koneksi3\.php'(?!')", "'/../../../../koneksi3.php'"
            $changed = $true
            Write-Host "FIXED /../../../../../koneksi3.php -> /../../../../koneksi3.php in $relPath"
        }
        # Wrong no __DIR__: '../../../../../koneksi3.php' -> '../../../../koneksi3.php'
        if ($content -match "'\.\./\.\./\.\./\.\./\.\./koneksi3\.php'") {
            $content = $content -replace "'\.\./\.\./\.\./\.\./\.\./koneksi3\.php'(?!')", "'../../../../koneksi3.php'"
            $changed = $true
            Write-Host "FIXED ../../../../../koneksi3.php -> ../../../../koneksi3.php in $relPath"
        }
        # Wrong: '../../../koneksi.php' (3 up, less than 4 needed) -> '../../../../koneksi.php'
        if ($content -match "'\.\./\.\./\.\./koneksi\.php'") {
            $content = $content -replace "'\.\./\.\./\.\./koneksi\.php'(?!')", "'../../../../koneksi.php'"
            $changed = $true
            Write-Host "FIXED ../../../koneksi.php -> ../../../../koneksi.php in $relPath"
        }
        if ($content -match "'/\.\./\.\./\.\./koneksi\.php'") {
            $content = $content -replace "'/\.\./\.\./\.\./koneksi\.php'(?!')", "'/../../../../koneksi.php'"
            $changed = $true
            Write-Host "FIXED /../../../koneksi.php -> /../../../../koneksi.php in $relPath"
        }
        # Wrong: '../../../koneksi3.php' -> '../../../../koneksi3.php'
        if ($content -match "'\.\./\.\./\.\./koneksi3\.php'") {
            $content = $content -replace "'\.\./\.\./\.\./koneksi3\.php'(?!')", "'../../../../koneksi3.php'"
            $changed = $true
            Write-Host "FIXED ../../../koneksi3.php -> ../../../../koneksi3.php in $relPath"
        }
        if ($content -match "'/\.\./\.\./\.\./koneksi3\.php'") {
            $content = $content -replace "'/\.\./\.\./\.\./koneksi3\.php'(?!')", "'/../../../../koneksi3.php'"
            $changed = $true
            Write-Host "FIXED /../../../koneksi3.php -> /../../../../koneksi3.php in $relPath"
        }
        # Wrong: '../../koneksi.php' (2 up, way too few for depth 4) -> '../../../../koneksi.php'
        if ($content -match "'\.\./\.\./koneksi\.php'") {
            $content = $content -replace "'\.\./\.\./koneksi\.php'(?!')", "'../../../../koneksi.php'"
            $changed = $true
            Write-Host "FIXED ../../koneksi.php -> ../../../../koneksi.php in $relPath"
        }
        if ($content -match "'/\.\./\.\./koneksi\.php'") {
            $content = $content -replace "'/\.\./\.\./koneksi\.php'(?!')", "'/../../../../koneksi.php'"
            $changed = $true
            Write-Host "FIXED /../../koneksi.php -> /../../../../koneksi.php in $relPath"
        }
    }
    
    if ($changed) {
        Set-Content $file $content -NoNewline
    }
}

Write-Host "`nDone!"
