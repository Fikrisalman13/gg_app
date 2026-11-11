param(
    [Parameter(Mandatory = $true)][ValidateSet('Poll', 'Detect')][string]$Mode,
    [string]$PortsJson = '{}'
)

$ErrorActionPreference = 'Stop'
$config = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'config.json') -Raw -Encoding UTF8 | ConvertFrom-Json
$devices = @($config.modbus_devices)
$parameters = @($config.parameters | Where-Object active)

function Get-WorkerCrc([byte[]]$Bytes) {
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

function Open-WorkerPort([string]$PortName, $Device, [int]$Timeout) {
    $parityName = switch ([string]$Device.parity) { 'N' { 'None' } 'E' { 'Even' } 'O' { 'Odd' } default { [string]$Device.parity } }
    $parity = [System.Enum]::Parse([System.IO.Ports.Parity], $parityName, $true)
    $stopBits = [System.Enum]::Parse([System.IO.Ports.StopBits], [string]$Device.stopbits, $true)
    $port = [System.IO.Ports.SerialPort]::new($PortName, [int]$Device.baudrate, $parity, [int]$Device.bytesize, $stopBits)
    $port.ReadTimeout = $Timeout; $port.WriteTimeout = $Timeout
    $port.Handshake = [System.IO.Ports.Handshake]::None; $port.DtrEnable = $false; $port.RtsEnable = $false
    $port.Open()
    return $port
}

function Read-WorkerFloat($Port, [int]$Slave, [int]$Register, [string]$ByteOrder) {
    $frame = [byte[]]@($Slave, 3, (($Register -shr 8) -band 255), ($Register -band 255), 0, 2)
    $crc = Get-WorkerCrc $frame
    $request = [byte[]]($frame + [byte]($crc -band 255) + [byte](($crc -shr 8) -band 255))
    $Port.DiscardInBuffer(); $Port.Write($request, 0, $request.Length)
    $response = [byte[]]::new(9); $offset = 0
    while ($offset -lt 9) { $n = $Port.Read($response, $offset, 9 - $offset); if ($n -le 0) { break }; $offset += $n }
    if ($offset -ne 9) { throw "Tidak ada respons lengkap dari perangkat Modbus alamat $Slave." }
    if ($response[0] -ne $Slave -or $response[1] -ne 3 -or $response[2] -ne 4) { throw "Respons Modbus tidak sesuai (alamat $Slave)." }
    $receivedCrc = [uint16]($response[7] -bor ($response[8] -shl 8))
    if ((Get-WorkerCrc ([byte[]]$response[0..6])) -ne $receivedCrc) { throw 'CRC Modbus tidak valid.' }
    [byte[]]$wire = $response[3..6]
    switch ($ByteOrder.ToUpperInvariant()) {
        'ABCD' { [byte[]]$little = @($wire[3], $wire[2], $wire[1], $wire[0]) }
        'BADC' { [byte[]]$little = @($wire[2], $wire[3], $wire[0], $wire[1]) }
        'CDAB' { [byte[]]$little = @($wire[1], $wire[0], $wire[3], $wire[2]) }
        'DCBA' { [byte[]]$little = @($wire[0], $wire[1], $wire[2], $wire[3]) }
        default { throw "Byte order '$ByteOrder' belum didukung." }
    }
    return [single]([BitConverter]::ToSingle($little, 0))
}

if ($Mode -eq 'Detect') {
    $found = @()
    $names = @([System.IO.Ports.SerialPort]::GetPortNames() | Sort-Object { [int]($_ -replace '\D') })
    foreach ($name in $names) {
        $port = $null
        try {
            $port = Open-WorkerPort $name $devices[0] 350
            foreach ($p in $parameters) {
                try { [void](Read-WorkerFloat $port ([int]$p.modbus_address) ([int]$p.register_address) ([string]$p.byte_order)); $found += $name; break } catch {}
            }
        } catch {}
        finally { if ($port -and $port.IsOpen) { $port.Close(); $port.Dispose() } }
    }
    [pscustomobject]@{ Type = 'detectResult'; Ports = @($found) }
    exit
}

$selectedPorts = ConvertFrom-Json -InputObject $PortsJson
$serials = @{}
try {
    foreach ($device in $devices) {
        $deviceName = [string]$device.device_name
        $portName = [string]$selectedPorts.$deviceName
        if (-not $portName) { continue }
        try {
            $serials[$deviceName] = Open-WorkerPort $portName $device 1500
            [pscustomobject]@{ Type = 'connected'; DeviceName = $deviceName; Port = $portName }
        } catch {
            [pscustomobject]@{ Type = 'deviceError'; DeviceName = $deviceName; Message = $_.Exception.Message }
        }
    }
    if ($serials.Count -eq 0) { [pscustomobject]@{ Type = 'fatal'; Message = 'Tidak ada port COM yang berhasil dibuka.' }; exit }

    # --- Fase Probe: cek parameter mana yang merespons ---
    $activeParams = @{}  # key = "deviceName|code", value = $true
    foreach ($device in $devices) {
        $deviceName = [string]$device.device_name
        if (-not $serials.ContainsKey($deviceName)) { continue }
        foreach ($p in $parameters) {
            $paramKey = "$deviceName|$([string]$p.code)"
            $responded = $false
            try {
                $raw = Read-WorkerFloat $serials[$deviceName] ([int]$p.modbus_address) ([int]$p.register_address) ([string]$p.byte_order)
                $responded = $true
            } catch {}
            $activeParams[$paramKey] = $responded
            Start-Sleep -Milliseconds 60
        }
    }

    # Kirim hasil probe ke UI
    $respondingList = @($activeParams.GetEnumerator() | Where-Object { $_.Value } | ForEach-Object { $_.Key })
    $notRespondingList = @($activeParams.GetEnumerator() | Where-Object { -not $_.Value } | ForEach-Object { $_.Key })
    [pscustomobject]@{ Type = 'probeResult'; Responding = $respondingList; NotResponding = $notRespondingList }

    # Jika tidak ada parameter yang merespons sama sekali, tetap lanjut polling semua (mungkin sensor lambat start)
    $hasAnyResponding = ($respondingList.Count -gt 0)

    # --- Fase Polling ---
    $loopCount = 0
    while ($true) {
        $loopCount++
        $isPeriodicRecheck = ($loopCount % 20 -eq 0)
        foreach ($device in $devices) {
            $deviceName = [string]$device.device_name
            if (-not $serials.ContainsKey($deviceName)) { continue }
            foreach ($p in $parameters) {
                $paramKey = "$deviceName|$([string]$p.code)"
                # Jika sensor belum aktif dan bukan jadwal recheck, lewati
                if ($hasAnyResponding -and -not $activeParams[$paramKey] -and -not $isPeriodicRecheck) { continue }
                try {
                    $raw = Read-WorkerFloat $serials[$deviceName] ([int]$p.modbus_address) ([int]$p.register_address) ([string]$p.byte_order)
                    $value = ([double]$raw * [double]$p.factor) + [double]$p.intercept
                    [pscustomobject]@{ Type = 'value'; DeviceName = $deviceName; Code = [string]$p.code; Value = $value; Time = (Get-Date).ToString('o') }
                    if (-not $activeParams[$paramKey]) {
                        $activeParams[$paramKey] = $true
                        [pscustomobject]@{ Type = 'paramOnline'; DeviceName = $deviceName; Code = [string]$p.code }
                    }
                } catch {
                    # Hanya laporkan error jika parameter aktif atau tidak ada sensor yg aktif sama sekali
                    if (-not $hasAnyResponding -or $activeParams[$paramKey]) {
                        [pscustomobject]@{ Type = 'error'; DeviceName = $deviceName; Code = [string]$p.code; Message = $_.Exception.Message }
                    }
                }
                Start-Sleep -Milliseconds 120
            }
        }
    }
} catch {
    [pscustomobject]@{ Type = 'fatal'; Message = $_.Exception.Message }
} finally {
    foreach ($port in $serials.Values) { if ($port -and $port.IsOpen) { $port.Close(); $port.Dispose() } }
}
