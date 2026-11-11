# Test langsung slave 5, register 6 di COM3 dengan diagnostik detail
param([string]$Port = 'COM3')

$ErrorActionPreference = 'Stop'

function Get-ModbusCrc([byte[]]$Bytes) {
    [uint16]$crc = 0xFFFF
    foreach ($b in $Bytes) { $crc = $crc -bxor [uint16]$b; for ($i = 0; $i -lt 8; $i++) { if (($crc -band 1) -ne 0) { $crc = ($crc -shr 1) -bxor 0xA001 } else { $crc = $crc -shr 1 } } }
    return [uint16]$crc
}

$sp = [System.IO.Ports.SerialPort]::new($Port, 9600, 'None', 8, 'One')
$sp.ReadTimeout = 3000; $sp.WriteTimeout = 3000
$sp.Handshake = 'None'; $sp.DtrEnable = $false; $sp.RtsEnable = $false
$sp.Open()

Write-Host "=== TEST SLAVE 5 REGISTER 6 @ $Port ===" -ForegroundColor Cyan
Write-Host ""

# Coba 5x pembacaan
for ($attempt = 1; $attempt -le 5; $attempt++) {
    Write-Host "--- Percobaan $attempt ---" -ForegroundColor Yellow
    
    $slave = 5
    $reg = 6
    $frame = [byte[]]@($slave, 3, 0, $reg, 0, 2)
    $crc = Get-ModbusCrc $frame
    $request = [byte[]]($frame + [byte]($crc -band 255) + [byte](($crc -shr 8) -band 255))
    
    Write-Host "  TX: $($request | ForEach-Object { $_.ToString('X2') })"
    
    $sp.DiscardInBuffer()
    $sp.Write($request, 0, $request.Length)
    
    # Tunggu respons dengan timeout panjang
    $response = [byte[]]::new(256)
    $offset = 0
    $deadline = (Get-Date).AddMilliseconds(3000)
    
    while ((Get-Date) -lt $deadline) {
        $avail = $sp.BytesToRead
        if ($avail -gt 0) {
            $n = $sp.Read($response, $offset, [Math]::Min($avail, 256 - $offset))
            $offset += $n
            if ($offset -ge 9) { break }
        }
        Start-Sleep -Milliseconds 20
    }
    
    if ($offset -eq 0) {
        Write-Host "  RX: (tidak ada respons - TIMEOUT)" -ForegroundColor Red
    } else {
        $hexRx = ($response[0..($offset-1)] | ForEach-Object { $_.ToString('X2') }) -join ' '
        Write-Host "  RX ($offset bytes): $hexRx" -ForegroundColor $(if ($offset -ge 9) { 'Green' } else { 'Yellow' })
        
        if ($offset -ge 9) {
            # Check response
            if ($response[0] -eq $slave -and $response[1] -eq 3 -and $response[2] -eq 4) {
                $receivedCrc = [uint16]($response[7] -bor ($response[8] -shl 8))
                $calcCrc = Get-ModbusCrc ([byte[]]$response[0..6])
                if ($calcCrc -eq $receivedCrc) {
                    [byte[]]$wire = $response[3..6]
                    foreach ($bo in @('ABCD','BADC','CDAB','DCBA')) {
                        switch ($bo) {
                            'ABCD' { [byte[]]$le = @($wire[3],$wire[2],$wire[1],$wire[0]) }
                            'BADC' { [byte[]]$le = @($wire[2],$wire[3],$wire[0],$wire[1]) }
                            'CDAB' { [byte[]]$le = @($wire[1],$wire[0],$wire[3],$wire[2]) }
                            'DCBA' { [byte[]]$le = @($wire[0],$wire[1],$wire[2],$wire[3]) }
                        }
                        $val = [BitConverter]::ToSingle($le, 0)
                        Write-Host "  $bo = $val" -ForegroundColor White
                    }
                    Write-Host "  [OK] Data valid!" -ForegroundColor Green
                } else {
                    Write-Host "  [ERROR] CRC mismatch: calc=$($calcCrc.ToString('X4')) recv=$($receivedCrc.ToString('X4'))" -ForegroundColor Red
                }
            } elseif ($response[1] -eq 0x83) {
                # Modbus exception
                $excCode = $response[2]
                $excMsg = switch ($excCode) { 1 {"Illegal Function"} 2 {"Illegal Data Address"} 3 {"Illegal Data Value"} 4 {"Server Failure"} default {"Unknown ($excCode)"} }
                Write-Host "  [EXCEPTION] Modbus exception: $excMsg" -ForegroundColor Red
            } else {
                Write-Host "  [WARNING] Response header tidak sesuai" -ForegroundColor Yellow
            }
        }
    }
    
    Start-Sleep -Milliseconds 500
}

# Juga coba slave 5 dengan register lain yang umum
Write-Host ""
Write-Host "=== Coba register lain pada Slave 5 ===" -ForegroundColor Cyan
foreach ($testReg in @(0, 1, 2, 4, 6, 8, 10, 100, 256, 1000)) {
    $frame = [byte[]]@(5, 3, (($testReg -shr 8) -band 255), ($testReg -band 255), 0, 2)
    $crc = Get-ModbusCrc $frame
    $request = [byte[]]($frame + [byte]($crc -band 255) + [byte](($crc -shr 8) -band 255))
    
    $sp.DiscardInBuffer()
    $sp.Write($request, 0, $request.Length)
    
    $response = [byte[]]::new(256); $offset = 0
    $deadline = (Get-Date).AddMilliseconds(1000)
    while ((Get-Date) -lt $deadline) {
        $avail = $sp.BytesToRead
        if ($avail -gt 0) { $n = $sp.Read($response, $offset, [Math]::Min($avail, 256 - $offset)); $offset += $n; if ($offset -ge 5) { break } }
        Start-Sleep -Milliseconds 20
    }
    
    if ($offset -gt 0) {
        $hexRx = ($response[0..($offset-1)] | ForEach-Object { $_.ToString('X2') }) -join ' '
        Write-Host "  Slave 5 Reg $testReg : [$offset bytes] $hexRx" -ForegroundColor Green
    } else {
        Write-Host "  Slave 5 Reg $testReg : (timeout)" -ForegroundColor DarkGray
    }
    Start-Sleep -Milliseconds 200
}

$sp.Close(); $sp.Dispose()

Write-Host ""
Write-Host "=== KESIMPULAN ===" -ForegroundColor Cyan
Write-Host "Jika semua TIMEOUT pada slave 5:"
Write-Host "  1. TUKAR kabel A dan B di RS485, lalu jalankan lagi"
Write-Host "  2. Jika sudah ditukar dan masih timeout: cek power supply sensor"
Write-Host "  3. Jika sensor ON tapi timeout: slave address mungkin bukan 5"
Write-Host "     -> cek dengan software Modbus Poll atau lihat setting di sensor"

