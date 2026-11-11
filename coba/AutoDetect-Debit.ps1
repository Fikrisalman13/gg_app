# Auto-Detect Modbus Debit Sensor
# Scan semua COM port, slave address, baudrate, dan register secara otomatis
# Jalankan: powershell -ExecutionPolicy Bypass -File .\AutoDetect-Debit.ps1

param(
    [string]$SpecificPort  # Opsional: hanya test 1 port tertentu
)

$ErrorActionPreference = 'Stop'

function Get-ModbusCrc([byte[]]$Bytes) {
    [uint16]$crc = 0xFFFF
    foreach ($b in $Bytes) {
        $crc = $crc -bxor [uint16]$b
        for ($i = 0; $i -lt 8; $i++) {
            if (($crc -band 1) -ne 0) { $crc = ($crc -shr 1) -bxor 0xA001 }
            else { $crc = $crc -shr 1 }
        }
    }
    return [uint16]$crc
}

function Test-ModbusRead {
    param(
        [System.IO.Ports.SerialPort]$Port,
        [int]$Slave,
        [int]$Register,
        [int]$Count = 2
    )
    
    $expectedBytes = 5 + ($Count * 2)  # slave + fc + bytecount + data + crc(2)
    $frame = [byte[]]@($Slave, 3, (($Register -shr 8) -band 255), ($Register -band 255), 0, $Count)
    $crc = Get-ModbusCrc $frame
    $request = [byte[]]($frame + [byte]($crc -band 255) + [byte](($crc -shr 8) -band 255))
    
    $Port.DiscardInBuffer()
    $Port.Write($request, 0, $request.Length)
    
    Start-Sleep -Milliseconds 150
    
    $response = [byte[]]::new(256)
    $offset = 0
    $deadline = (Get-Date).AddMilliseconds(1500)
    
    while ($offset -lt $expectedBytes -and (Get-Date) -lt $deadline) {
        $avail = $Port.BytesToRead
        if ($avail -gt 0) {
            $toRead = [Math]::Min($avail, $expectedBytes - $offset)
            try {
                $n = $Port.Read($response, $offset, $toRead)
                if ($n -gt 0) { $offset += $n }
            } catch { break }
        }
        if ($offset -lt $expectedBytes) { Start-Sleep -Milliseconds 30 }
    }
    
    if ($offset -lt $expectedBytes) { return $null }
    
    # Validasi response header
    if ($response[0] -ne $Slave) { return $null }
    if ($response[1] -ne 3) { return $null }
    if ($response[2] -ne ($Count * 2)) { return $null }
    
    # Validasi CRC
    $receivedCrc = [uint16]($response[$expectedBytes - 2] -bor ($response[$expectedBytes - 1] -shl 8))
    $calcCrc = Get-ModbusCrc ([byte[]]$response[0..($expectedBytes - 3)])
    if ($calcCrc -ne $receivedCrc) { return $null }
    
    # Return data bytes
    return [byte[]]$response[3..($expectedBytes - 3)]
}

function Parse-Float([byte[]]$Wire, [string]$ByteOrder) {
    switch ($ByteOrder) {
        'ABCD' { [byte[]]$le = @($Wire[3], $Wire[2], $Wire[1], $Wire[0]) }
        'BADC' { [byte[]]$le = @($Wire[2], $Wire[3], $Wire[0], $Wire[1]) }
        'CDAB' { [byte[]]$le = @($Wire[1], $Wire[0], $Wire[3], $Wire[2]) }
        'DCBA' { [byte[]]$le = @($Wire[0], $Wire[1], $Wire[2], $Wire[3]) }
    }
    return [BitConverter]::ToSingle($le, 0)
}

function Is-ReasonableFloat([single]$Val) {
    if ([single]::IsNaN($Val) -or [single]::IsInfinity($Val)) { return $false }
    if ([Math]::Abs($Val) -gt 1e10) { return $false }
    return $true
}

# ============================================
# KONFIGURASI SCAN
# ============================================

$baudrates = @(9600, 4800, 19200, 38400, 115200, 2400, 1200)
$slaveAddresses = @(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 16, 20, 100, 247)
$byteOrders = @('ABCD', 'BADC', 'CDAB', 'DCBA')

# Register umum untuk flow meter / debit sensor
$commonRegisters = @(
    @{ Addr = 0;     Desc = "Reg 0 (0x0000)" }
    @{ Addr = 1;     Desc = "Reg 1 (0x0001)" }
    @{ Addr = 2;     Desc = "Reg 2 (0x0002)" }
    @{ Addr = 4;     Desc = "Reg 4 (0x0004)" }
    @{ Addr = 6;     Desc = "Reg 6 (0x0006)" }
    @{ Addr = 8;     Desc = "Reg 8 (0x0008)" }
    @{ Addr = 10;    Desc = "Reg 10 (0x000A)" }
    @{ Addr = 16;    Desc = "Reg 16 (0x0010)" }
    @{ Addr = 100;   Desc = "Reg 100 (0x0064)" }
    @{ Addr = 200;   Desc = "Reg 200 (0x00C8)" }
    @{ Addr = 256;   Desc = "Reg 256 (0x0100)" }
    @{ Addr = 1000;  Desc = "Reg 1000 (0x03E8)" }
    @{ Addr = 9216;  Desc = "Reg 9216 (0x2400)" }
    @{ Addr = 9730;  Desc = "Reg 9730 (0x2602)" }
    @{ Addr = 10240; Desc = "Reg 10240 (0x2800)" }
    @{ Addr = 40001; Desc = "Reg 40001 (0x9C41)" }
)

# ============================================
# MULAI SCAN
# ============================================

Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "   AUTO-DETECT MODBUS SENSOR" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

# Dapatkan port COM yang tersedia
if ($SpecificPort) {
    $ports = @($SpecificPort)
} else {
    $ports = @([System.IO.Ports.SerialPort]::GetPortNames() | Sort-Object { [int]($_ -replace '\D') })
}

if ($ports.Count -eq 0) {
    Write-Host "[ERROR] Tidak ada port COM yang tersedia!" -ForegroundColor Red
    exit 1
}

Write-Host "Port COM ditemukan: $($ports -join ', ')" -ForegroundColor Yellow
Write-Host "Slave address    : $($slaveAddresses -join ', ')"
Write-Host "Baudrate         : $($baudrates -join ', ')"
Write-Host "Register         : $($commonRegisters.Count) register umum"
Write-Host ""

$found = @()
$totalCombinations = $ports.Count * $baudrates.Count * $slaveAddresses.Count * $commonRegisters.Count
$current = 0

foreach ($portName in $ports) {
    Write-Host "========== Scanning $portName ==========" -ForegroundColor Cyan
    
    foreach ($baud in $baudrates) {
        $sp = $null
        try {
            $sp = [System.IO.Ports.SerialPort]::new($portName, $baud, 'None', 8, 'One')
            $sp.ReadTimeout = 1500
            $sp.WriteTimeout = 1500
            $sp.Handshake = 'None'
            $sp.DtrEnable = $false
            $sp.RtsEnable = $false
            $sp.Open()
        } catch {
            Write-Host "  [SKIP] $portName @ $baud baud - gagal buka: $($_.Exception.Message)" -ForegroundColor DarkGray
            if ($sp) { try { $sp.Dispose() } catch {} }
            continue
        }
        
        $foundOnThisBaud = $false
        
        foreach ($slave in $slaveAddresses) {
            foreach ($reg in $commonRegisters) {
                $current++
                $pct = [Math]::Round(($current / $totalCombinations) * 100, 1)
                Write-Host "`r  [$pct%] $portName @ ${baud}baud | Slave $slave | $($reg.Desc)        " -NoNewline
                
                try {
                    $data = Test-ModbusRead -Port $sp -Slave $slave -Register $reg.Addr -Count 2
                } catch {
                    $data = $null
                }
                
                if ($null -ne $data -and $data.Length -ge 4) {
                    Write-Host ""
                    Write-Host "  [DITEMUKAN!] Respons valid!" -ForegroundColor Green
                    Write-Host "    Port     : $portName" -ForegroundColor Green
                    Write-Host "    Baudrate : $baud" -ForegroundColor Green
                    Write-Host "    Slave    : $slave" -ForegroundColor Green
                    Write-Host "    Register : $($reg.Addr) ($($reg.Desc))" -ForegroundColor Green
                    Write-Host "    Raw bytes: $($data | ForEach-Object { $_.ToString('X2') })" -ForegroundColor Green
                    
                    # Coba semua byte order
                    foreach ($bo in $byteOrders) {
                        $val = Parse-Float $data $bo
                        $reasonable = if (Is-ReasonableFloat $val) { "" } else { " [abnormal]" }
                        Write-Host "    Byte order $bo : $val$reasonable" -ForegroundColor $(if (Is-ReasonableFloat $val) { 'White' } else { 'DarkGray' })
                    }
                    Write-Host ""
                    
                    $found += [pscustomobject]@{
                        Port      = $portName
                        Baudrate  = $baud
                        Slave     = $slave
                        Register  = $reg.Addr
                        RegDesc   = $reg.Desc
                        RawHex    = ($data | ForEach-Object { $_.ToString('X2') }) -join ' '
                        ABCD      = Parse-Float $data 'ABCD'
                        BADC      = Parse-Float $data 'BADC'
                        CDAB      = Parse-Float $data 'CDAB'
                        DCBA      = Parse-Float $data 'DCBA'
                    }
                    $foundOnThisBaud = $true
                }
                
                Start-Sleep -Milliseconds 50
            }
            
            if ($foundOnThisBaud) {
                # Sudah menemukan respons, coba slave lain tapi tetap di baudrate ini
            }
        }
        
        Write-Host ""
        
        try { $sp.Close(); $sp.Dispose() } catch {}
        
        # Jika menemukan sesuatu di baudrate ini, tidak perlu coba baudrate lain di port ini
        if ($foundOnThisBaud) {
            Write-Host "  Ditemukan respons di $portName @ ${baud}baud, lanjut ke port berikutnya..." -ForegroundColor Yellow
            # Skip remaining baud rates on same COM port, still advance counter
            $remaining = ($baudrates.Count - ($baudrates.IndexOf($baud) + 1)) * $slaveAddresses.Count * $commonRegisters.Count
            $current += $remaining
            break
        }
    }
}

# ============================================
# RANGKUMAN HASIL
# ============================================

Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "   HASIL SCAN" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

if ($found.Count -eq 0) {
    Write-Host "[TIDAK DITEMUKAN] Tidak ada perangkat Modbus yang merespons!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Kemungkinan penyebab:" -ForegroundColor Yellow
    Write-Host "  1. Kabel A/B RS485 TERTUKAR -> tukar posisi kabel A dan B"
    Write-Host "  2. Sensor belum diberi daya / mati"
    Write-Host "  3. Adaptor USB-RS485 rusak / driver belum terinstall"
    Write-Host "  4. Kabel putus atau terlalu panjang"
    Write-Host "  5. Sensor menggunakan parity Even/Odd (saat ini scan hanya None)"
    Write-Host ""
    Write-Host "Coba: tukar kabel A/B, lalu jalankan script ini lagi." -ForegroundColor Yellow
} else {
    Write-Host "Ditemukan $($found.Count) respons Modbus valid:" -ForegroundColor Green
    Write-Host ""
    
    $resultIndex = 0
    foreach ($f in $found) {
        $resultIndex++
        Write-Host "--- Hasil #$resultIndex ---" -ForegroundColor Cyan
        Write-Host "  Port       : $($f.Port)"
        Write-Host "  Baudrate   : $($f.Baudrate)"
        Write-Host "  Slave Addr : $($f.Slave)"
        Write-Host "  Register   : $($f.Register) ($($f.RegDesc))"
        Write-Host "  Raw (hex)  : $($f.RawHex)"
        Write-Host "  Nilai (ABCD): $($f.ABCD)"
        Write-Host "  Nilai (BADC): $($f.BADC)"
        Write-Host "  Nilai (CDAB): $($f.CDAB)"
        Write-Host "  Nilai (DCBA): $($f.DCBA)"
        Write-Host ""
    }
    
    # Simpan config recommendation
    $best = $found[0]
    Write-Host "=============================================" -ForegroundColor Green
    Write-Host "   REKOMENDASI CONFIG.JSON" -ForegroundColor Green
    Write-Host "=============================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Gunakan setting berikut di config.json untuk sensor debit:" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  Port COM        : $($best.Port)"
    Write-Host "  modbus_address  : $($best.Slave)"
    Write-Host "  register_address: $($best.Register)"
    Write-Host "  baudrate        : $($best.Baudrate)"
    Write-Host ""
    Write-Host "  Pilih byte_order berdasarkan nilai yang masuk akal:"
    foreach ($bo in $byteOrders) {
        $val = switch ($bo) { 'ABCD' { $best.ABCD } 'BADC' { $best.BADC } 'CDAB' { $best.CDAB } 'DCBA' { $best.DCBA } }
        $flag = if (Is-ReasonableFloat $val) { " <-- kemungkinan ini" } else { "" }
        Write-Host "    $bo = $val$flag"
    }
    
    # Simpan hasil ke file
    $resultPath = Join-Path $PSScriptRoot 'detect-result.json'
    $found | ConvertTo-Json -Depth 3 | Set-Content -LiteralPath $resultPath -Encoding UTF8
    Write-Host ""
    Write-Host "Hasil scan disimpan ke: $resultPath" -ForegroundColor DarkGray
}

Write-Host ""
Write-Host "Scan selesai." -ForegroundColor Cyan

