# Test script untuk memverifikasi komunikasi Modbus ke sensor debit
# Jalankan: powershell -ExecutionPolicy Bypass -File .\Test-ModbusDebit.ps1 -Port COM4
# Ganti COM4 dengan port COM yang sesuai

param(
    [Parameter(Mandatory=$true)][string]$Port
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

Write-Host "=== Test Komunikasi Modbus Sensor Debit ===" -ForegroundColor Cyan
Write-Host "Port     : $Port"
Write-Host "Baudrate : 9600, 8-N-1"
Write-Host "Slave    : 5"
Write-Host "Register : 6 (0x0006)"
Write-Host ""

# Buka serial port
try {
    $sp = [System.IO.Ports.SerialPort]::new($Port, 9600, 'None', 8, 'One')
    $sp.ReadTimeout = 2000
    $sp.WriteTimeout = 2000
    $sp.Handshake = 'None'
    $sp.DtrEnable = $false
    $sp.RtsEnable = $false
    $sp.Open()
    Write-Host "[OK] Port $Port berhasil dibuka" -ForegroundColor Green
} catch {
    Write-Host "[GAGAL] Tidak bisa membuka port $Port : $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# Build Modbus request: Slave=5, FC=03, Register=0006, Count=2
$frame = [byte[]]@(5, 3, 0, 6, 0, 2)
$crc = Get-ModbusCrc $frame
$request = [byte[]]($frame + [byte]($crc -band 255) + [byte](($crc -shr 8) -band 255))

Write-Host ""
Write-Host "Request (hex): $($request | ForEach-Object { $_.ToString('X2') }) " -ForegroundColor Yellow

# Kirim request
$sp.DiscardInBuffer()
$sp.Write($request, 0, $request.Length)
Write-Host "[OK] Request terkirim, menunggu respons (timeout 2 detik)..." -ForegroundColor Green

# Baca respons
try {
    Start-Sleep -Milliseconds 100  # beri waktu sensor merespons
    $response = [byte[]]::new(256)
    $offset = 0
    $startTime = Get-Date
    while ($offset -lt 9 -and ((Get-Date) - $startTime).TotalMilliseconds -lt 2000) {
        try {
            $n = $sp.Read($response, $offset, [Math]::Min(9 - $offset, $sp.BytesToRead + 1))
            if ($n -gt 0) { $offset += $n }
        } catch [System.TimeoutException] {
            break
        }
        if ($offset -lt 9) { Start-Sleep -Milliseconds 50 }
    }

    if ($offset -eq 0) {
        Write-Host ""
        Write-Host "[GAGAL] Tidak ada respons dari sensor!" -ForegroundColor Red
        Write-Host ""
        Write-Host "Kemungkinan penyebab:" -ForegroundColor Yellow
        Write-Host "  1. Kabel A/B RS485 TERTUKAR - coba tukar posisi kabel A dan B"
        Write-Host "  2. Sensor belum diberi daya / power supply"
        Write-Host "  3. Slave address salah (bukan 5)"
        Write-Host "  4. COM port salah"
        Write-Host "  5. Baudrate tidak sesuai"
    } elseif ($offset -lt 9) {
        Write-Host ""
        Write-Host "[PARSIAL] Hanya menerima $offset byte (harusnya 9)" -ForegroundColor Yellow
        Write-Host "Response (hex): $($response[0..($offset-1)] | ForEach-Object { $_.ToString('X2') })"
        Write-Host ""
        Write-Host "Kemungkinan: baudrate tidak cocok, noise di kabel, atau kabel terlalu panjang"
    } else {
        Write-Host ""
        Write-Host "Response (hex): $($response[0..8] | ForEach-Object { $_.ToString('X2') })" -ForegroundColor Green
        
        # Verifikasi CRC
        $receivedCrc = [uint16]($response[7] -bor ($response[8] -shl 8))
        $calcCrc = Get-ModbusCrc ([byte[]]$response[0..6])
        
        if ($calcCrc -ne $receivedCrc) {
            Write-Host "[WARNING] CRC tidak cocok! Calc=$($calcCrc.ToString('X4')) Recv=$($receivedCrc.ToString('X4'))" -ForegroundColor Red
        } else {
            Write-Host "[OK] CRC valid" -ForegroundColor Green
        }
        
        # Parse float (ABCD = Big-Endian)
        [byte[]]$wire = $response[3..6]
        [byte[]]$little = @($wire[3], $wire[2], $wire[1], $wire[0])  # ABCD -> little endian
        $rawValue = [BitConverter]::ToSingle($little, 0)
        $debit = ([double]$rawValue * 0.0166)
        
        Write-Host ""
        Write-Host "=== HASIL ===" -ForegroundColor Cyan
        Write-Host "Raw bytes  : $($wire | ForEach-Object { $_.ToString('X2') })"
        Write-Host "Raw float  : $rawValue"
        Write-Host "Debit      : $debit m3/menit"
        Write-Host ""
        Write-Host "[SUKSES] Sensor debit merespons dengan benar!" -ForegroundColor Green
    }
} catch {
    Write-Host "[ERROR] $($_.Exception.Message)" -ForegroundColor Red
} finally {
    $sp.Close()
    $sp.Dispose()
    Write-Host ""
    Write-Host "Port ditutup."
}

# Tes tambahan: coba juga tukar kabel (kirim lagi untuk verifikasi)
Write-Host ""
Write-Host "=== Tips Troubleshooting ===" -ForegroundColor Cyan
Write-Host "Jika GAGAL, coba langkah berikut:"
Write-Host "  1. TUKAR kabel A dan B di adaptor USB-RS485"
Write-Host "  2. Jalankan script ini lagi"
Write-Host "  3. Jika masih gagal, coba baudrate lain (4800, 19200)"
Write-Host "  4. Pastikan sensor sudah ON dan LED menyala"

