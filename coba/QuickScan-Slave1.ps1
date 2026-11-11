# Quick scan: slave 1 sudah ditemukan merespons di COM3 @ 9600
# Scan semua register 0-1000 untuk menemukan register debit yang benar
param([string]$Port = 'COM3')

$ErrorActionPreference = 'Stop'

function Get-ModbusCrc([byte[]]$Bytes) {
    [uint16]$crc = 0xFFFF
    foreach ($b in $Bytes) { $crc = $crc -bxor [uint16]$b; for ($i = 0; $i -lt 8; $i++) { if (($crc -band 1) -ne 0) { $crc = ($crc -shr 1) -bxor 0xA001 } else { $crc = $crc -shr 1 } } }
    return [uint16]$crc
}

$sp = [System.IO.Ports.SerialPort]::new($Port, 9600, 'None', 8, 'One')
$sp.ReadTimeout = 1000; $sp.WriteTimeout = 1000
$sp.Handshake = 'None'; $sp.DtrEnable = $false; $sp.RtsEnable = $false
$sp.Open()

Write-Host "=== QUICK SCAN: Slave 1 @ COM3 9600baud ===" -ForegroundColor Cyan
Write-Host "Scanning register 0 - 1000 (step 2)..." -ForegroundColor Yellow
Write-Host ""

$results = @()

# Scan register 0-1000 dengan step 2 (karena float = 2 register)
for ($reg = 0; $reg -le 1000; $reg += 2) {
    Write-Host "`r  Scanning register $reg / 1000...    " -NoNewline
    
    $slave = 1
    $frame = [byte[]]@($slave, 3, (($reg -shr 8) -band 255), ($reg -band 255), 0, 2)
    $crc = Get-ModbusCrc $frame
    $request = [byte[]]($frame + [byte]($crc -band 255) + [byte](($crc -shr 8) -band 255))
    
    $sp.DiscardInBuffer()
    $sp.Write($request, 0, $request.Length)
    Start-Sleep -Milliseconds 120
    
    $response = [byte[]]::new(9); $offset = 0
    $deadline = (Get-Date).AddMilliseconds(1000)
    while ($offset -lt 9 -and (Get-Date) -lt $deadline) {
        $avail = $sp.BytesToRead
        if ($avail -gt 0) { try { $n = $sp.Read($response, $offset, [Math]::Min($avail, 9 - $offset)); if ($n -gt 0) { $offset += $n } } catch { break } }
        if ($offset -lt 9) { Start-Sleep -Milliseconds 20 }
    }
    
    if ($offset -ne 9) { continue }
    if ($response[0] -ne $slave -or $response[1] -ne 3 -or $response[2] -ne 4) { continue }
    
    $receivedCrc = [uint16]($response[7] -bor ($response[8] -shl 8))
    if ((Get-ModbusCrc ([byte[]]$response[0..6])) -ne $receivedCrc) { continue }
    
    [byte[]]$wire = $response[3..6]
    $hexStr = ($wire | ForEach-Object { $_.ToString('X2') }) -join ' '
    
    # Parse semua byte order
    $vals = @{}
    foreach ($bo in @('ABCD','BADC','CDAB','DCBA')) {
        switch ($bo) {
            'ABCD' { [byte[]]$le = @($wire[3],$wire[2],$wire[1],$wire[0]) }
            'BADC' { [byte[]]$le = @($wire[2],$wire[3],$wire[0],$wire[1]) }
            'CDAB' { [byte[]]$le = @($wire[1],$wire[0],$wire[3],$wire[2]) }
            'DCBA' { [byte[]]$le = @($wire[0],$wire[1],$wire[2],$wire[3]) }
        }
        $vals[$bo] = [BitConverter]::ToSingle($le, 0)
    }
    
    # Hanya tampilkan jika bukan semua nol
    $allZero = ($wire[0] -eq 0 -and $wire[1] -eq 0 -and $wire[2] -eq 0 -and $wire[3] -eq 0)
    
    Write-Host ""
    $color = if ($allZero) { 'DarkGray' } else { 'Green' }
    Write-Host "  [REG $reg] Raw: $hexStr | ABCD=$($vals['ABCD']) | BADC=$($vals['BADC']) | CDAB=$($vals['CDAB']) | DCBA=$($vals['DCBA'])" -ForegroundColor $color
    
    $results += [pscustomobject]@{
        Register = $reg
        Hex = $hexStr
        ABCD = $vals['ABCD']
        BADC = $vals['BADC']
        CDAB = $vals['CDAB']
        DCBA = $vals['DCBA']
        AllZero = $allZero
    }
    
    Start-Sleep -Milliseconds 30
}

$sp.Close(); $sp.Dispose()

Write-Host ""
Write-Host ""
Write-Host "=== RANGKUMAN ===" -ForegroundColor Cyan

$nonZero = @($results | Where-Object { -not $_.AllZero })
$zeroRegs = @($results | Where-Object { $_.AllZero })

Write-Host "Total register yang merespons: $($results.Count)" -ForegroundColor Yellow
Write-Host "Register dengan data non-zero: $($nonZero.Count)" -ForegroundColor Green
Write-Host "Register dengan data zero    : $($zeroRegs.Count)" -ForegroundColor DarkGray
Write-Host ""

if ($nonZero.Count -gt 0) {
    Write-Host "=== REGISTER DENGAN DATA (kemungkinan debit) ===" -ForegroundColor Green
    foreach ($r in $nonZero) {
        Write-Host "  Register $($r.Register): Hex=$($r.Hex)"
        Write-Host "    ABCD=$($r.ABCD)  BADC=$($r.BADC)  CDAB=$($r.CDAB)  DCBA=$($r.DCBA)"
    }
}

if ($zeroRegs.Count -gt 0) {
    Write-Host ""
    Write-Host "Register dengan data zero: $($zeroRegs | ForEach-Object { $_.Register })" -ForegroundColor DarkGray
}

# Simpan hasil
$results | ConvertTo-Json -Depth 3 | Set-Content -LiteralPath (Join-Path $PSScriptRoot 'quickscan-result.json') -Encoding UTF8
Write-Host ""
Write-Host "Hasil disimpan ke quickscan-result.json"

