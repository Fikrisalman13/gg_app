Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
[System.Windows.Forms.Application]::EnableVisualStyles()

$script:ConfigPath = Join-Path $PSScriptRoot 'config.json'
$script:Config = Get-Content -LiteralPath $script:ConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json
$script:Devices = @($script:Config.modbus_devices)
$script:PortMapPath = Join-Path $PSScriptRoot 'device-ports.json'
$script:SavedPortMap = @{}
if (Test-Path $script:PortMapPath) {
    try {
        $savedMapObject = Get-Content -LiteralPath $script:PortMapPath -Raw -Encoding UTF8 | ConvertFrom-Json
        foreach ($property in $savedMapObject.PSObject.Properties) { $script:SavedPortMap[$property.Name] = [string]$property.Value }
    } catch { $script:SavedPortMap = @{} }
}
$script:DatabasePath = Join-Path $PSScriptRoot $script:Config.database.path
$script:DatabaseDirectory = Split-Path -Parent $script:DatabasePath
if ($script:DatabaseDirectory -and -not (Test-Path $script:DatabaseDirectory)) { New-Item -ItemType Directory -Path $script:DatabaseDirectory | Out-Null }
$script:Params = @($script:Config.parameters | Where-Object active)
$script:History = [System.Collections.Generic.List[object]]::new()
$script:LastValues = @{}
$script:ActivePorts = @{}
$script:DatabaseWriteErrorReported = $false
$script:PollJob = $null
$script:DetectJob = $null
$script:WorkerScriptPath = Join-Path $PSScriptRoot 'SerialWorker.ps1'

Add-Type -TypeDefinition @'
using System;
using System.Collections.Generic;
using System.Runtime.InteropServices;
using System.Text;

public static class NativeSqlite
{
    private delegate int ExecCallback(IntPtr argument, int columnCount, IntPtr values, IntPtr columnNames);
    [DllImport("winsqlite3.dll", CallingConvention = CallingConvention.Cdecl, EntryPoint = "sqlite3_open16")]
    private static extern int Open16([MarshalAs(UnmanagedType.LPWStr)] string fileName, out IntPtr database);
    [DllImport("winsqlite3.dll", CallingConvention = CallingConvention.Cdecl, EntryPoint = "sqlite3_exec")]
    private static extern int Exec(IntPtr database, IntPtr sql, IntPtr callback, IntPtr argument, out IntPtr errorMessage);
    [DllImport("winsqlite3.dll", CallingConvention = CallingConvention.Cdecl, EntryPoint = "sqlite3_errmsg")]
    private static extern IntPtr ErrorMessage(IntPtr database);
    [DllImport("winsqlite3.dll", CallingConvention = CallingConvention.Cdecl, EntryPoint = "sqlite3_free")]
    private static extern void Free(IntPtr memory);
    [DllImport("winsqlite3.dll", CallingConvention = CallingConvention.Cdecl, EntryPoint = "sqlite3_close")]
    private static extern int Close(IntPtr database);

    private static string Utf8(IntPtr value)
    {
        if (value == IntPtr.Zero) return null;
        int length = 0;
        while (Marshal.ReadByte(value, length) != 0) length++;
        byte[] bytes = new byte[length];
        Marshal.Copy(value, bytes, 0, length);
        return Encoding.UTF8.GetString(bytes);
    }

    private static int CollectRow(IntPtr argument, int columnCount, IntPtr values, IntPtr columnNames)
    {
        var rows = (List<string[]>)GCHandle.FromIntPtr(argument).Target;
        var row = new string[columnCount];
        for (int i = 0; i < columnCount; i++) row[i] = Utf8(Marshal.ReadIntPtr(values, i * IntPtr.Size));
        rows.Add(row);
        return 0;
    }

    public static string[][] Query(string fileName, string sql)
    {
        IntPtr database;
        int result = Open16(fileName, out database);
        if (result != 0) throw new InvalidOperationException("SQLite open error " + result);
        IntPtr sqlMemory = IntPtr.Zero;
        IntPtr error = IntPtr.Zero;
        var rows = new List<string[]>();
        var handle = GCHandle.Alloc(rows);
        ExecCallback callback = CollectRow;
        try
        {
            byte[] bytes = Encoding.UTF8.GetBytes(sql + "\0");
            sqlMemory = Marshal.AllocHGlobal(bytes.Length);
            Marshal.Copy(bytes, 0, sqlMemory, bytes.Length);
            result = Exec(database, sqlMemory, Marshal.GetFunctionPointerForDelegate(callback), GCHandle.ToIntPtr(handle), out error);
            if (result != 0)
            {
                string message = error == IntPtr.Zero ? Marshal.PtrToStringAnsi(ErrorMessage(database)) : Marshal.PtrToStringAnsi(error);
                throw new InvalidOperationException("SQLite error " + result + ": " + message);
            }
            return rows.ToArray();
        }
        finally
        {
            GC.KeepAlive(callback);
            if (handle.IsAllocated) handle.Free();
            if (error != IntPtr.Zero) Free(error);
            if (sqlMemory != IntPtr.Zero) Marshal.FreeHGlobal(sqlMemory);
            Close(database);
        }
    }

    public static void Execute(string fileName, string sql)
    {
        IntPtr database;
        int result = Open16(fileName, out database);
        if (result != 0) throw new InvalidOperationException("SQLite open error " + result);
        IntPtr sqlMemory = IntPtr.Zero;
        IntPtr error = IntPtr.Zero;
        try
        {
            byte[] bytes = Encoding.UTF8.GetBytes(sql + "\0");
            sqlMemory = Marshal.AllocHGlobal(bytes.Length);
            Marshal.Copy(bytes, 0, sqlMemory, bytes.Length);
            result = Exec(database, sqlMemory, IntPtr.Zero, IntPtr.Zero, out error);
            if (result != 0)
            {
                string message = error == IntPtr.Zero ? Marshal.PtrToStringAnsi(ErrorMessage(database)) : Marshal.PtrToStringAnsi(error);
                throw new InvalidOperationException("SQLite error " + result + ": " + message);
            }
        }
        finally
        {
            if (error != IntPtr.Zero) Free(error);
            if (sqlMemory != IntPtr.Zero) Marshal.FreeHGlobal(sqlMemory);
            Close(database);
        }
    }
}
'@

function Invoke-DatabaseSql([string]$Sql) {
    [NativeSqlite]::Execute($script:DatabasePath, $Sql)
}
function ConvertTo-SqlText([string]$Text) {
    return "'" + $Text.Replace("'", "''") + "'"
}
try {
    Invoke-DatabaseSql 'CREATE TABLE IF NOT EXISTS sensor_readings (id INTEGER PRIMARY KEY AUTOINCREMENT, timestamp TEXT NOT NULL, parameter_code TEXT NOT NULL, parameter_name TEXT NOT NULL, value REAL NOT NULL, unit TEXT NOT NULL, port TEXT NOT NULL); CREATE INDEX IF NOT EXISTS idx_sensor_readings_timestamp ON sensor_readings(timestamp); CREATE INDEX IF NOT EXISTS idx_sensor_readings_code_timestamp ON sensor_readings(parameter_code, timestamp); CREATE TABLE IF NOT EXISTS sensor_readings_by_device (id INTEGER PRIMARY KEY AUTOINCREMENT, timestamp TEXT NOT NULL, device_name TEXT NOT NULL, parameter_code TEXT NOT NULL, parameter_name TEXT NOT NULL, value REAL NOT NULL, unit TEXT NOT NULL, port TEXT NOT NULL); CREATE INDEX IF NOT EXISTS idx_readings_device_time ON sensor_readings_by_device(device_name, timestamp); CREATE INDEX IF NOT EXISTS idx_readings_device_param_time ON sensor_readings_by_device(device_name, parameter_code, timestamp);'
} catch {
    [System.Windows.Forms.MessageBox]::Show(('Could not open the local SQLite database: ' + $_.Exception.Message), 'Database error', 'OK', 'Error')
    exit 1
}

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

function Read-ModbusFloat($Port, [int]$Slave, [int]$Register, [string]$ByteOrder, [int]$Count = 2) {
    $frame = [byte[]]@($Slave, 3, (($Register -shr 8) -band 255), ($Register -band 255), 0, $Count)
    $crc = Get-ModbusCrc $frame
    $request = [byte[]]($frame + [byte]($crc -band 255) + [byte](($crc -shr 8) -band 255))
    $Port.DiscardInBuffer()
    $Port.Write($request, 0, $request.Length)
    $response = [byte[]]::new(9)
    $offset = 0
    while ($offset -lt $response.Length) {
        $n = $Port.Read($response, $offset, $response.Length - $offset)
        if ($n -le 0) { break }
        $offset += $n
    }
    if ($offset -ne 9) { throw "Tidak ada respons lengkap dari perangkat Modbus alamat $Slave." }
    if ($response[0] -ne $Slave -or $response[1] -ne 3 -or $response[2] -ne 4) { throw "Respons Modbus tidak sesuai (alamat $Slave)." }
    $receivedCrc = [uint16]($response[7] -bor ($response[8] -shl 8))
    if ((Get-ModbusCrc ([byte[]]$response[0..6])) -ne $receivedCrc) { throw 'CRC Modbus tidak valid.' }
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

function New-SerialPort([string]$PortName, $DeviceConfig, [int]$Timeout = 650) {
    $parityText = [string]$DeviceConfig.parity
    $parityName = switch ($parityText.ToUpperInvariant()) { 'N' { 'None' } 'E' { 'Even' } 'O' { 'Odd' } 'M' { 'Mark' } 'S' { 'Space' } default { $parityText } }
    $parity = [System.Enum]::Parse([System.IO.Ports.Parity], $parityName, $true)
    $stopBits = [System.Enum]::Parse([System.IO.Ports.StopBits], [string]$DeviceConfig.stopbits, $true)
    $sp = [System.IO.Ports.SerialPort]::new($PortName, [int]$DeviceConfig.baudrate,
        $parity, [int]$DeviceConfig.bytesize, $stopBits)
    $sp.ReadTimeout = $Timeout
    $sp.WriteTimeout = $Timeout
    $sp.Handshake = [System.IO.Ports.Handshake]::None
    $sp.DtrEnable = $false
    $sp.RtsEnable = $false
    $sp.Open()
    return $sp
}

$form = [System.Windows.Forms.Form]::new()
$form.Text = 'Monitoring Sedimen Dewatering'
$form.StartPosition = 'CenterScreen'
$form.Size = [Drawing.Size]::new(1180, 780)
$form.MinimumSize = [Drawing.Size]::new(980, 680)
$form.BackColor = [Drawing.Color]::FromArgb(12, 18, 28)
$form.ForeColor = [Drawing.Color]::FromArgb(230, 237, 245)
$form.Font = [Drawing.Font]::new('Segoe UI', 10)

$header = [Windows.Forms.Panel]::new(); $header.Dock = 'Top'; $header.Height = 92; $header.BackColor = [Drawing.Color]::FromArgb(18, 27, 40)
$title = [Windows.Forms.Label]::new(); $title.Text = 'SEDIMEN DEWATERING'; $title.Font = [Drawing.Font]::new('Segoe UI Semibold', 20); $title.ForeColor = [Drawing.Color]::White; $title.AutoSize = $true; $title.Location = [Drawing.Point]::new(24, 15)
$sub = [Windows.Forms.Label]::new(); $sub.Text = 'Pemantauan kualitas air dan debit secara langsung'; $sub.ForeColor = [Drawing.Color]::FromArgb(151, 170, 190); $sub.AutoSize = $true; $sub.Location = [Drawing.Point]::new(27, 54)
$status = [Windows.Forms.Label]::new(); $status.Text = 'Status: Belum terhubung'; $status.ForeColor = [Drawing.Color]::FromArgb(255, 190, 90); $status.AutoSize = $true; $status.Anchor = 'Top,Right'; $status.Location = [Drawing.Point]::new(930, 34)
$header.Controls.AddRange(@($title, $sub, $status))

$toolbar = [Windows.Forms.Panel]::new(); $toolbar.Dock = 'Top'; $toolbar.Height = 82; $toolbar.BackColor = [Drawing.Color]::FromArgb(15, 23, 35)
$clarifierLabel = [Windows.Forms.Label]::new(); $clarifierLabel.Text = 'Clarifier'; $clarifierLabel.Location = [Drawing.Point]::new(20, 12); $clarifierLabel.AutoSize = $true
$clarifierPort = [Windows.Forms.ComboBox]::new(); $clarifierPort.DropDownStyle = 'DropDownList'; $clarifierPort.Location = [Drawing.Point]::new(20, 38); $clarifierPort.Width = 105; $clarifierPort.BackColor = [Drawing.Color]::FromArgb(29, 41, 58); $clarifierPort.ForeColor = [Drawing.Color]::White
$biologyLabel = [Windows.Forms.Label]::new(); $biologyLabel.Text = 'Sedimen Biologi'; $biologyLabel.Location = [Drawing.Point]::new(140, 12); $biologyLabel.AutoSize = $true
$biologyPort = [Windows.Forms.ComboBox]::new(); $biologyPort.DropDownStyle = 'DropDownList'; $biologyPort.Location = [Drawing.Point]::new(140, 38); $biologyPort.Width = 135; $biologyPort.BackColor = [Drawing.Color]::FromArgb(29, 41, 58); $biologyPort.ForeColor = [Drawing.Color]::White
$refresh = [Windows.Forms.Button]::new(); $refresh.Text = 'Muat Port'; $refresh.Location = [Drawing.Point]::new(290, 36); $refresh.Size = [Drawing.Size]::new(96, 34)
$detect = [Windows.Forms.Button]::new(); $detect.Text = 'Deteksi USB'; $detect.Location = [Drawing.Point]::new(395, 36); $detect.Size = [Drawing.Size]::new(112, 34)
$start = [Windows.Forms.Button]::new(); $start.Text = 'Mulai'; $start.Location = [Drawing.Point]::new(525, 36); $start.Size = [Drawing.Size]::new(90, 34); $start.BackColor = [Drawing.Color]::FromArgb(34, 190, 135); $start.ForeColor = [Drawing.Color]::White
$stop = [Windows.Forms.Button]::new(); $stop.Text = 'Berhenti'; $stop.Location = [Drawing.Point]::new(625, 36); $stop.Size = [Drawing.Size]::new(95, 34); $stop.Enabled = $false
$serialInfo = [Windows.Forms.Label]::new(); $serialInfo.Text = 'Modbus RTU - 9600 baud - 8-N-1'; $serialInfo.ForeColor = [Drawing.Color]::FromArgb(151, 170, 190); $serialInfo.AutoSize = $true; $serialInfo.Location = [Drawing.Point]::new(740, 44)
$savedDataButton = [Windows.Forms.Button]::new(); $savedDataButton.Text = 'Data Tersimpan'; $savedDataButton.Location = [Drawing.Point]::new(990, 36); $savedDataButton.Size = [Drawing.Size]::new(145, 34)
$toolbar.Controls.AddRange(@($clarifierLabel, $clarifierPort, $biologyLabel, $biologyPort, $refresh, $detect, $start, $stop, $serialInfo, $savedDataButton))

$main = [Windows.Forms.TableLayoutPanel]::new(); $main.Dock = 'Fill'; $main.Padding = [Windows.Forms.Padding]::new(14, 10, 14, 12); $main.ColumnCount = 1; $main.RowCount = 2; $main.GrowStyle = 'FixedSize'
$main.ColumnStyles.Add([Windows.Forms.ColumnStyle]::new('Percent', 100))
$main.RowStyles.Add([Windows.Forms.RowStyle]::new('Percent', 67)); $main.RowStyles.Add([Windows.Forms.RowStyle]::new('Percent', 33))
$tabs = [Windows.Forms.TabControl]::new(); $tabs.Dock = 'Fill'; $tabs.DrawMode = 'OwnerDrawFixed'; $tabs.ItemSize = [Drawing.Size]::new(190, 34); $tabs.SizeMode = 'Fixed'; $tabs.Padding = [Drawing.Point]::new(16, 5)
$tabs.add_DrawItem({
    param($sender, $e)
    $selected = ($e.Index -eq $sender.SelectedIndex)
    $backColor = if ($selected) { [Drawing.Color]::FromArgb(34, 190, 135) } else { [Drawing.Color]::FromArgb(22, 32, 47) }
    $textColor = if ($selected) { [Drawing.Color]::White } else { [Drawing.Color]::FromArgb(160, 181, 202) }
    $tabBrush = [Drawing.SolidBrush]::new($backColor); $textBrush = [Drawing.SolidBrush]::new($textColor)
    $e.Graphics.FillRectangle($tabBrush, $e.Bounds)
    $format = [Drawing.StringFormat]::new(); $format.Alignment = 'Center'; $format.LineAlignment = 'Center'
    $tabBounds = [Drawing.RectangleF]::new([single]$e.Bounds.X, [single]$e.Bounds.Y, [single]$e.Bounds.Width, [single]$e.Bounds.Height)
    $e.Graphics.DrawString([string]$sender.TabPages[$e.Index].Text, $sender.Font, $textBrush, $tabBounds, $format)
    $tabBrush.Dispose(); $textBrush.Dispose(); $format.Dispose()
})
$cards = @{}
foreach ($device in $script:Devices) {
    $page = [Windows.Forms.TabPage]::new(); $page.Text = [string]$device.device_name; $page.BackColor = [Drawing.Color]::FromArgb(12, 18, 28); $page.ForeColor = [Drawing.Color]::White; $page.Padding = [Windows.Forms.Padding]::new(8)
    $deviceGrid = [Windows.Forms.TableLayoutPanel]::new(); $deviceGrid.Dock = 'Fill'; $deviceGrid.ColumnCount = 3; $deviceGrid.RowCount = 2; $deviceGrid.BackColor = [Drawing.Color]::FromArgb(12, 18, 28)
    $deviceGrid.ColumnStyles.Add([Windows.Forms.ColumnStyle]::new('Percent', 33.33)); $deviceGrid.ColumnStyles.Add([Windows.Forms.ColumnStyle]::new('Percent', 33.33)); $deviceGrid.ColumnStyles.Add([Windows.Forms.ColumnStyle]::new('Percent', 33.34))
    $deviceGrid.RowStyles.Add([Windows.Forms.RowStyle]::new('Percent', 50)); $deviceGrid.RowStyles.Add([Windows.Forms.RowStyle]::new('Percent', 50))
    for ($i = 0; $i -lt $script:Params.Count; $i++) {
        $p = $script:Params[$i]
        $card = [Windows.Forms.Panel]::new(); $card.Dock = 'Fill'; $card.Margin = [Windows.Forms.Padding]::new(7); $card.BackColor = [Drawing.Color]::FromArgb(22, 32, 47)
        $name = [Windows.Forms.Label]::new(); $name.Text = [string]$p.name; $name.Font = [Drawing.Font]::new('Segoe UI Semibold', 10); $name.ForeColor = [Drawing.Color]::FromArgb(160, 181, 202); $name.Location = [Drawing.Point]::new(16, 13); $name.AutoSize = $true
        $value = [Windows.Forms.Label]::new(); $value.Text = '--'; $value.Font = [Drawing.Font]::new('Segoe UI Semibold', 27); $value.ForeColor = [Drawing.Color]::White; $value.Location = [Drawing.Point]::new(15, 40); $value.AutoSize = $true
        $displayUnit = switch ([string]$p.code) { 'suhu' { 'deg C' } 'debit' { 'm3/min' } default { [string]$p.unit } }
        $unit = [Windows.Forms.Label]::new(); $unit.Text = $displayUnit; $unit.ForeColor = [Drawing.Color]::FromArgb(112, 139, 164); $unit.Location = [Drawing.Point]::new(18, 91); $unit.AutoSize = $true
        $stamp = [Windows.Forms.Label]::new(); $stamp.Text = 'Menunggu data'; $stamp.Font = [Drawing.Font]::new('Segoe UI', 9); $stamp.ForeColor = [Drawing.Color]::FromArgb(112, 139, 164); $stamp.Location = [Drawing.Point]::new(18, 112); $stamp.AutoSize = $true
        $card.Controls.AddRange(@($name, $value, $unit, $stamp)); $deviceGrid.Controls.Add($card, ($i % 3), [int][Math]::Floor($i / 3))
        $cards[([string]$device.device_name + '|' + [string]$p.code)] = @{ Value = $value; Stamp = $stamp; Parameter = $p; Device = $device }
    }
    $page.Controls.Add($deviceGrid); [void]$tabs.TabPages.Add($page)
}
$main.Controls.Add($tabs, 0, 0)
$chartHost = [Windows.Forms.Panel]::new(); $chartHost.Dock = 'Fill'; $chartHost.Margin = [Windows.Forms.Padding]::new(7); $chartHost.BackColor = [Drawing.Color]::FromArgb(22, 32, 47); $chartHost.add_Paint({
    param($sender, $e)
    $g = $e.Graphics; $g.SmoothingMode = 'AntiAlias'; $rect = $sender.ClientRectangle
    $font = [Drawing.Font]::new('Segoe UI Semibold', 11); $brush = [Drawing.SolidBrush]::new([Drawing.Color]::FromArgb(160, 181, 202))
    $g.DrawString('GRAFIK DEBIT', $font, $brush, 17, 14)
    $penGrid = [Drawing.Pen]::new([Drawing.Color]::FromArgb(43, 57, 74), 1)
    for ($n = 0; $n -lt 4; $n++) { $yy = 55 + (($rect.Height - 78) * $n / 3); $g.DrawLine($penGrid, 18, [float]$yy, $rect.Width - 18, [float]$yy) }
    foreach ($series in @(@{ Name='Clarifier'; Color=[Drawing.Color]::FromArgb(34, 190, 135) }, @{ Name='Sedimen Biologi'; Color=[Drawing.Color]::FromArgb(80, 160, 255) })) {
        $points = @($script:History | Where-Object { $_.device_name -eq $series.Name } | Select-Object -Last 60)
        if ($points.Count -gt 1) {
            $vals = @($points | ForEach-Object { [double]$_.debit }); $min = ($vals | Measure-Object -Minimum).Minimum; $max = ($vals | Measure-Object -Maximum).Maximum
            if ($max -eq $min) { $max = $min + 1 }
            $xy = [Drawing.PointF[]]::new($points.Count)
            for ($j = 0; $j -lt $points.Count; $j++) { $x = 18 + ($rect.Width - 36) * $j / ($points.Count - 1); $y = $rect.Height - 23 - (($vals[$j] - $min) / ($max - $min)) * ($rect.Height - 88); $xy[$j] = [Drawing.PointF]::new([single]$x, [single]$y) }
            $seriesPen = [Drawing.Pen]::new($series.Color, 2.5); $g.DrawLines($seriesPen, $xy); $seriesPen.Dispose()
        }
    }
    $font.Dispose(); $brush.Dispose(); $penGrid.Dispose()
})
$bottom = [Windows.Forms.TableLayoutPanel]::new(); $bottom.Dock = 'Fill'; $bottom.ColumnCount = 2; $bottom.RowCount = 1; $bottom.Margin = [Windows.Forms.Padding]::Empty; $bottom.Padding = [Windows.Forms.Padding]::Empty
$bottom.ColumnStyles.Add([Windows.Forms.ColumnStyle]::new('Percent', 68)); $bottom.ColumnStyles.Add([Windows.Forms.ColumnStyle]::new('Percent', 32)); $bottom.RowStyles.Add([Windows.Forms.RowStyle]::new('Percent', 100))
$logBox = [Windows.Forms.ListBox]::new(); $logBox.Dock = 'Fill'; $logBox.Margin = [Windows.Forms.Padding]::new(7); $logBox.BackColor = [Drawing.Color]::FromArgb(17, 25, 37); $logBox.ForeColor = [Drawing.Color]::FromArgb(181, 198, 215); $logBox.BorderStyle = 'None'
$bottom.Controls.Add($chartHost, 0, 0); $bottom.Controls.Add($logBox, 1, 0); $main.Controls.Add($bottom, 0, 1)

$layout = [Windows.Forms.TableLayoutPanel]::new(); $layout.Dock = 'Fill'; $layout.Margin = [Windows.Forms.Padding]::Empty; $layout.Padding = [Windows.Forms.Padding]::Empty; $layout.ColumnCount = 1; $layout.RowCount = 3
$layout.ColumnStyles.Add([Windows.Forms.ColumnStyle]::new('Percent', 100))
$layout.RowStyles.Add([Windows.Forms.RowStyle]::new('Absolute', 92))
$layout.RowStyles.Add([Windows.Forms.RowStyle]::new('Absolute', 82))
$layout.RowStyles.Add([Windows.Forms.RowStyle]::new('Percent', 100))
$header.Dock = 'Fill'; $toolbar.Dock = 'Fill'; $main.Dock = 'Fill'
$layout.Controls.Add($header, 0, 0); $layout.Controls.Add($toolbar, 0, 1); $layout.Controls.Add($main, 0, 2)
$form.Controls.Add($layout)

function Add-Log([string]$Message) {
    $line = "$(Get-Date -Format 'HH:mm:ss')  $Message"
    $logBox.Items.Insert(0, $line)
    while ($logBox.Items.Count -gt 60) { $logBox.Items.RemoveAt($logBox.Items.Count - 1) }
}
function Show-SavedData {
    $historyForm = [Windows.Forms.Form]::new()
    $historyForm.Text = 'Data Tersimpan - Riwayat Pembacaan'
    $historyForm.StartPosition = 'CenterParent'; $historyForm.Size = [Drawing.Size]::new(1100, 680); $historyForm.MinimumSize = [Drawing.Size]::new(820, 480)
    $historyForm.BackColor = [Drawing.Color]::FromArgb(12, 18, 28); $historyForm.ForeColor = [Drawing.Color]::FromArgb(230, 237, 245); $historyForm.Font = [Drawing.Font]::new('Segoe UI', 9)
    $filterPanel = [Windows.Forms.FlowLayoutPanel]::new(); $filterPanel.Dock = 'Top'; $filterPanel.Height = 88; $filterPanel.Padding = [Windows.Forms.Padding]::new(12, 10, 8, 6); $filterPanel.BackColor = [Drawing.Color]::FromArgb(18, 27, 40); $filterPanel.WrapContents = $false; $filterPanel.AutoScroll = $true
    $deviceFilter = [Windows.Forms.ComboBox]::new(); $deviceFilter.DropDownStyle = 'DropDownList'; $deviceFilter.Width = 150; [void]$deviceFilter.Items.Add('Semua alat'); foreach ($device in $script:Devices) { [void]$deviceFilter.Items.Add([string]$device.device_name) }; $deviceFilter.SelectedIndex = 0
    $parameterFilter = [Windows.Forms.ComboBox]::new(); $parameterFilter.DropDownStyle = 'DropDownList'; $parameterFilter.Width = 125; [void]$parameterFilter.Items.Add('Semua parameter'); foreach ($p in $script:Params) { [void]$parameterFilter.Items.Add([string]$p.name) }; $parameterFilter.SelectedIndex = 0
    $dateFrom = [Windows.Forms.DateTimePicker]::new(); $dateFrom.Format = 'Custom'; $dateFrom.CustomFormat = 'dd-MM-yyyy'; $dateFrom.Width = 120; $dateFrom.Value = (Get-Date).Date.AddDays(-7)
    $dateTo = [Windows.Forms.DateTimePicker]::new(); $dateTo.Format = 'Custom'; $dateTo.CustomFormat = 'dd-MM-yyyy'; $dateTo.Width = 120; $dateTo.Value = (Get-Date).Date
    $rowLimit = [Windows.Forms.NumericUpDown]::new(); $rowLimit.Minimum = 100; $rowLimit.Maximum = 10000; $rowLimit.Increment = 100; $rowLimit.Value = 1000; $rowLimit.Width = 90
    $loadButton = [Windows.Forms.Button]::new(); $loadButton.Text = 'Muat Data'; $loadButton.Width = 100; $loadButton.Height = 30
    $filterLabels = @()
    foreach ($labelText in @('Alat', 'Parameter', 'Dari', 'Sampai', 'Maks. baris')) {
        $filterLabel = [Windows.Forms.Label]::new(); $filterLabel.Text = $labelText; $filterLabel.AutoSize = $true; $filterLabel.Margin = [Windows.Forms.Padding]::new(8, 8, 2, 0); $filterLabel.ForeColor = [Drawing.Color]::FromArgb(181, 198, 215)
        $filterLabels += $filterLabel
    }
    foreach ($control in @($filterLabels[0], $deviceFilter, $filterLabels[1], $parameterFilter, $filterLabels[2], $dateFrom, $filterLabels[3], $dateTo, $filterLabels[4], $rowLimit, $loadButton)) { $filterPanel.Controls.Add($control) }
    foreach ($combo in @($deviceFilter, $parameterFilter)) { $combo.BackColor = [Drawing.Color]::FromArgb(29, 41, 58); $combo.ForeColor = [Drawing.Color]::White }
    $rowLimit.BackColor = [Drawing.Color]::FromArgb(29, 41, 58); $rowLimit.ForeColor = [Drawing.Color]::White
    $grid = [Windows.Forms.DataGridView]::new(); $grid.Dock = 'Fill'; $grid.ReadOnly = $true; $grid.AllowUserToAddRows = $false; $grid.AllowUserToDeleteRows = $false; $grid.AutoSizeColumnsMode = 'Fill'; $grid.SelectionMode = 'FullRowSelect'; $grid.MultiSelect = $false
    $grid.BackgroundColor = [Drawing.Color]::FromArgb(17, 25, 37); $grid.BorderStyle = 'None'; $grid.EnableHeadersVisualStyles = $false
    $grid.ColumnHeadersDefaultCellStyle.BackColor = [Drawing.Color]::FromArgb(29, 41, 58); $grid.ColumnHeadersDefaultCellStyle.ForeColor = [Drawing.Color]::White
    $grid.DefaultCellStyle.BackColor = [Drawing.Color]::FromArgb(22, 32, 47); $grid.DefaultCellStyle.ForeColor = [Drawing.Color]::FromArgb(230, 237, 245); $grid.DefaultCellStyle.SelectionBackColor = [Drawing.Color]::FromArgb(34, 100, 86); $grid.DefaultCellStyle.SelectionForeColor = [Drawing.Color]::White
    $resultLabel = [Windows.Forms.Label]::new(); $resultLabel.Dock = 'Bottom'; $resultLabel.Height = 28; $resultLabel.Padding = [Windows.Forms.Padding]::new(12, 5, 0, 0); $resultLabel.ForeColor = [Drawing.Color]::FromArgb(151, 170, 190)
    $historyForm.Controls.Add($grid); $historyForm.Controls.Add($resultLabel); $historyForm.Controls.Add($filterPanel)
    $loadButton.Add_Click({
        if ($dateFrom.Value.Date -gt $dateTo.Value.Date) { [Windows.Forms.MessageBox]::Show('Tanggal Dari tidak boleh melewati tanggal Sampai.', 'Rentang tanggal tidak valid', 'OK', 'Warning'); return }
        $conditions = @("timestamp >= $(ConvertTo-SqlText $dateFrom.Value.ToString('yyyy-MM-dd 00:00:00'))", "timestamp < $(ConvertTo-SqlText $dateTo.Value.Date.AddDays(1).ToString('yyyy-MM-dd 00:00:00'))")
        if ($deviceFilter.SelectedIndex -gt 0) { $conditions += 'device_name = ' + (ConvertTo-SqlText ([string]$deviceFilter.SelectedItem)) }
        if ($parameterFilter.SelectedIndex -gt 0) {
            $chosenParam = $script:Params | Where-Object { [string]$_.name -eq [string]$parameterFilter.SelectedItem } | Select-Object -First 1
            if ($chosenParam) { $conditions += 'parameter_code = ' + (ConvertTo-SqlText ([string]$chosenParam.code)) }
        }
        $limit = [int]$rowLimit.Value
        $source = "(SELECT timestamp, device_name, parameter_name, value, unit, port, parameter_code FROM sensor_readings_by_device UNION ALL SELECT timestamp, 'Data lama', parameter_name, value, unit, port, parameter_code FROM sensor_readings)"
        $sql = 'SELECT timestamp AS Waktu, device_name AS Alat, parameter_name AS Parameter, value AS Nilai, unit AS Satuan, port AS Port FROM ' + $source + ' WHERE ' + ($conditions -join ' AND ') + " ORDER BY timestamp DESC LIMIT $limit;"
        try {
            $records = [NativeSqlite]::Query($script:DatabasePath, $sql)
            $table = [System.Data.DataTable]::new()
            foreach ($columnName in @('Waktu', 'Alat', 'Parameter', 'Nilai', 'Satuan', 'Port')) { [void]$table.Columns.Add($columnName) }
            foreach ($record in $records) { [void]$table.Rows.Add([object[]]$record) }
            $grid.DataSource = $table
            $resultLabel.Text = "Menampilkan $($table.Rows.Count) baris dari database lokal: $($script:DatabasePath)"
        } catch { [Windows.Forms.MessageBox]::Show(('Gagal membaca database: ' + $_.Exception.Message), 'Kesalahan database', 'OK', 'Error') }
    })
    $historyForm.Add_Shown({ $loadButton.PerformClick() })
    [void]$historyForm.ShowDialog($form)
}

function Refresh-Ports {
    $boxes = @($clarifierPort, $biologyPort)
    $names = @()
    try { $names = @([System.IO.Ports.SerialPort]::GetPortNames() | Sort-Object { [int]($_ -replace '\D') }) } catch {}
    for ($i = 0; $i -lt $boxes.Count; $i++) {
        $box = $boxes[$i]
        $selected = [string]$box.SelectedItem
        $box.Items.Clear()
        [void]$box.Items.Add('Tidak dipilih')
        foreach ($name in $names) { [void]$box.Items.Add($name) }
        $idx = if ($selected -and $selected -ne 'Tidak dipilih') { $box.Items.IndexOf($selected) } else { -1 }
        if ($idx -lt 0 -and $script:SavedPortMap.ContainsKey([string]$script:Devices[$i].device_name)) { $idx = $box.Items.IndexOf([string]$script:SavedPortMap[[string]$script:Devices[$i].device_name]) }
        if ($idx -lt 0) { $idx = $box.Items.IndexOf([string]$script:Devices[$i].port) }
        if ($idx -lt 0 -and $i -lt $names.Count) { $idx = $box.Items.IndexOf($names[$i]) }
        $box.SelectedIndex = if ($idx -ge 0) { $idx } else { 0 }
    }
    Add-Log ("Port COM tersedia: " + $(if ($names.Count) { $names -join ', ' } else { 'tidak ada port serial' }))
}

function Update-DeviceGridLayout([string]$DeviceName) {
    $tabPage = $tabs.TabPages | Where-Object { $_.Text -eq $DeviceName } | Select-Object -First 1
    if (-not $tabPage) { return }
    $grid = $tabPage.Controls[0]
    $visibleCards = @()
    foreach ($p in $script:Params) {
        $pKey = "$DeviceName|$([string]$p.code)"
        if ($cards.ContainsKey($pKey) -and $cards[$pKey].Value.Parent.Visible) {
            $visibleCards += $cards[$pKey].Value.Parent
        }
    }
    if ($visibleCards.Count -eq 0) {
        for ($i = 0; $i -lt $script:Params.Count; $i++) {
            $p = $script:Params[$i]
            $key = "$DeviceName|$([string]$p.code)"
            $cards[$key].Value.Parent.Visible = $true
            $visibleCards += $cards[$key].Value.Parent
        }
    }
    $grid.Controls.Clear()
    $cols = [Math]::Min(3, [Math]::Max(1, $visibleCards.Count))
    $rows = [Math]::Max(1, [int][Math]::Ceiling($visibleCards.Count / $cols))
    $grid.ColumnCount = $cols; $grid.RowCount = $rows
    $grid.ColumnStyles.Clear(); $grid.RowStyles.Clear()
    for ($c = 0; $c -lt $cols; $c++) { $grid.ColumnStyles.Add([Windows.Forms.ColumnStyle]::new('Percent', (100.0 / $cols))) }
    for ($r = 0; $r -lt $rows; $r++) { $grid.RowStyles.Add([Windows.Forms.RowStyle]::new('Percent', (100.0 / $rows))) }
    for ($vi = 0; $vi -lt $visibleCards.Count; $vi++) {
        $grid.Controls.Add($visibleCards[$vi], ($vi % $cols), [int][Math]::Floor($vi / $cols))
    }
}

function Process-WorkerMessage($item) {
    if ($item.Type -eq 'detectResult') {
        $detect.Enabled = $true; $refresh.Enabled = $true; $clarifierPort.Enabled = $true; $biologyPort.Enabled = $true
        $ports = @($item.Ports)
        if ($ports.Count -ge 2) {
            $clarifierPort.SelectedItem = $ports[0]; $biologyPort.SelectedItem = $ports[1]
            Add-Log ("Dua port yang merespons Modbus ditemukan: " + $ports[0] + ', ' + $ports[1])
            [Windows.Forms.MessageBox]::Show("Port yang merespons ditemukan: $($ports[0]) dan $($ports[1]). Urutan COM tidak menunjukkan label fisik; pastikan pilihan port sesuai alat.", 'Deteksi selesai', 'OK', 'Information')
        } elseif ($ports.Count -eq 1) {
            $clarifierPort.SelectedItem = $ports[0]
            Add-Log ('Satu port yang merespons Modbus ditemukan: ' + $ports[0])
            [Windows.Forms.MessageBox]::Show("Port $($ports[0]) merespons. Port dipilih untuk Clarifier. Jika alat yang tersambung adalah Sedimen Biologi, ubah pilihan port secara manual.", 'Deteksi sebagian', 'OK', 'Warning')
        } else {
            Add-Log 'Tidak ada port yang merespons register Modbus.'
            [Windows.Forms.MessageBox]::Show('Tidak ada port COM yang merespons register Modbus pada config.json. Periksa adaptor, kabel A/B, alamat slave, dan register.', 'Perangkat belum ditemukan', 'OK', 'Warning')
        }
        return
    }
    if ($item.Type -eq 'probeResult') {
        $responding = @($item.Responding)
        $notResponding = @($item.NotResponding)
        Add-Log ("Probe selesai: $($responding.Count) sensor merespons, $($notResponding.Count) tidak merespons")
        foreach ($rKey in $responding) { Add-Log ("  [OK] $rKey") }
        if ($responding.Count -gt 0) {
            foreach ($nrKey in $notResponding) {
                if ($cards.ContainsKey($nrKey)) {
                    $cards[$nrKey].Value.Parent.Visible = $false
                    Add-Log ("  [SKIP] $nrKey - disembunyikan")
                }
            }
            foreach ($device in $script:Devices) {
                Update-DeviceGridLayout [string]$device.device_name
            }
            $status.Text = "Status: $($responding.Count) sensor aktif"
            $status.ForeColor = [Drawing.Color]::FromArgb(56, 210, 155)
        } else {
            $status.Text = 'Status: Tidak ada sensor merespons'
            $status.ForeColor = [Drawing.Color]::FromArgb(255, 100, 100)
            Add-Log 'Tidak ada sensor yang merespons. Menunggu respon perangkat...'
        }
        return
    }
    if ($item.Type -eq 'paramOnline') {
        $onKey = [string]$item.DeviceName + '|' + [string]$item.Code
        if ($cards.ContainsKey($onKey)) {
            $cards[$onKey].Value.Parent.Visible = $true
            Update-DeviceGridLayout [string]$item.DeviceName
            Add-Log ("Sensor online: $onKey")
        }
        return
    }
    $key = [string]$item.DeviceName + '|' + [string]$item.Code
    if ($item.Type -eq 'connected') { Add-Log ($item.DeviceName + ' terhubung melalui ' + $item.Port); return }
    if ($item.Type -eq 'value') {
        if ($cards.ContainsKey($key) -and -not $cards[$key].Value.Parent.Visible) {
            $cards[$key].Value.Parent.Visible = $true
            Update-DeviceGridLayout [string]$item.DeviceName
        }
        $p = $cards[$key].Parameter; $decimals = [Math]::Max(0, [Math]::Min(6, [int]$p.decimal_places))
        $cards[$key].Value.Text = ([double]$item.Value).ToString("F$decimals", [Globalization.CultureInfo]::InvariantCulture)
        $itemTime = [DateTime]::Parse([string]$item.Time).ToLocalTime()
        $cards[$key].Stamp.Text = 'Diperbarui ' + $itemTime.ToString('HH:mm:ss')
        $script:LastValues[$key] = $item.Value
        $status.Text = 'Status: Terhubung'; $status.ForeColor = [Drawing.Color]::FromArgb(56, 210, 155)
        try {
            $timeSql = ConvertTo-SqlText $itemTime.ToString('yyyy-MM-dd HH:mm:ss.fff', [Globalization.CultureInfo]::InvariantCulture)
            $deviceSql = ConvertTo-SqlText ([string]$item.DeviceName); $codeSql = ConvertTo-SqlText ([string]$p.code)
            $nameSql = ConvertTo-SqlText ([string]$p.name); $unitSql = ConvertTo-SqlText ([string]$p.unit)
            $portSql = ConvertTo-SqlText ([string]$script:ActivePorts[$item.DeviceName])
            $valueSql = ([double]$item.Value).ToString('R', [Globalization.CultureInfo]::InvariantCulture)
            Invoke-DatabaseSql "INSERT INTO sensor_readings_by_device (timestamp, device_name, parameter_code, parameter_name, value, unit, port) VALUES ($timeSql, $deviceSql, $codeSql, $nameSql, $valueSql, $unitSql, $portSql);"
        } catch { if (-not $script:DatabaseWriteErrorReported) { Add-Log ('Gagal menyimpan pembacaan ke database: ' + $_.Exception.Message); $script:DatabaseWriteErrorReported = $true } }
        if ($item.Code -eq 'debit') {
            $script:History.Add([pscustomobject]@{ time=$itemTime; debit=$item.Value; device_name=$item.DeviceName }); $chartHost.Invalidate()
        }
    } elseif ($item.Type -eq 'error') { $cards[$key].Stamp.Text = 'Belum ada respons'; Add-Log ($item.DeviceName + ' ' + $item.Code + ': ' + $item.Message) }
    elseif ($item.Type -eq 'deviceError') {
        foreach ($p in $script:Params) { $deviceKey = [string]$item.DeviceName + '|' + [string]$p.code; $cards[$deviceKey].Stamp.Text = 'Port gagal dibuka' }
        Add-Log ($item.DeviceName + ': ' + $item.Message)
    } elseif ($item.Type -eq 'fatal') { Add-Log ('Koneksi berakhir: ' + $item.Message) }
}

$workerTimer = [Windows.Forms.Timer]::new(); $workerTimer.Interval = 250
$workerTimer.Add_Tick({
    foreach ($jobName in @('PollJob', 'DetectJob')) {
        $job = Get-Variable -Name $jobName -Scope Script -ValueOnly
        if (-not $job) { continue }
        try { foreach ($message in @(Receive-Job -Job $job -ErrorAction SilentlyContinue)) { Process-WorkerMessage $message } } catch { Add-Log ('Gagal membaca hasil worker: ' + $_.Exception.Message) }
        if ($job.State -in @('Completed', 'Failed', 'Stopped')) {
            try { Remove-Job -Job $job -Force -ErrorAction SilentlyContinue } catch {}
            Set-Variable -Name $jobName -Scope Script -Value $null
            if ($jobName -eq 'PollJob') {
                $start.Enabled = $true; $stop.Enabled = $false; $detect.Enabled = $true; $refresh.Enabled = $true; $clarifierPort.Enabled = $true; $biologyPort.Enabled = $true
                $status.Text = 'Status: Terputus'; $status.ForeColor = [Drawing.Color]::FromArgb(255, 190, 90)
                if ($job.State -eq 'Stopped') { Add-Log 'Pemantauan dihentikan.' }
                elseif ($job.State -eq 'Failed' -and $job.ChildJobs[0].JobStateInfo.Reason) { Add-Log ('Worker gagal: ' + $job.ChildJobs[0].JobStateInfo.Reason.Message) }
                elseif ($job.State -eq 'Completed') { Add-Log 'Pembacaan selesai.' }
            } else {
                $detect.Enabled = $true; $refresh.Enabled = $true; $clarifierPort.Enabled = $true; $biologyPort.Enabled = $true
                if ($job.State -eq 'Failed' -and $job.ChildJobs[0].JobStateInfo.Reason) { Add-Log ('Worker deteksi gagal: ' + $job.ChildJobs[0].JobStateInfo.Reason.Message) }
            }
        }
    }
    if (-not $script:PollJob -and -not $script:DetectJob) { $workerTimer.Stop() }
})
$refresh.Add_Click({ Refresh-Ports })
$savedDataButton.Add_Click({ Show-SavedData })
$detect.Add_Click({
    if ($script:DetectJob -or $script:PollJob) { return }
    Refresh-Ports
    if ($clarifierPort.Items.Count -le 1) { [Windows.Forms.MessageBox]::Show('Windows belum menemukan port serial. Sambungkan adaptor USB-RS485, lalu klik Muat Port.', 'Port COM tidak ditemukan', 'OK', 'Warning'); return }
    $detect.Enabled = $false; $refresh.Enabled = $false; $clarifierPort.Enabled = $false; $biologyPort.Enabled = $false; $status.Text = 'Status: Mendeteksi port alat'; $status.ForeColor = [Drawing.Color]::FromArgb(90, 180, 255); Add-Log 'Menguji port COM yang tersedia dengan register Modbus.'
    $jobCommand = { param($workerPath, $workerMode, $portsJson) & $workerPath -Mode $workerMode -PortsJson $portsJson }
    $script:DetectJob = Start-Job -ScriptBlock $jobCommand -ArgumentList $script:WorkerScriptPath, 'Detect', '{}'
    $workerTimer.Start()
})
$start.Add_Click({
    if ($script:PollJob -or $script:DetectJob) { return }
    $clarifierSelection = [string]$clarifierPort.SelectedItem
    $biologySelection = [string]$biologyPort.SelectedItem
    if ($clarifierSelection -eq 'Tidak dipilih') { $clarifierSelection = '' }
    if ($biologySelection -eq 'Tidak dipilih') { $biologySelection = '' }
    if (-not $clarifierSelection -and -not $biologySelection) { [Windows.Forms.MessageBox]::Show('Pilih minimal satu port COM untuk alat yang akan dipantau.', 'Port belum dipilih', 'OK', 'Warning'); return }
    if ($clarifierSelection -and $biologySelection -and $clarifierSelection -eq $biologySelection) { [Windows.Forms.MessageBox]::Show('Jika hanya ada satu port, pilih port itu pada salah satu alat saja. Untuk memantau dua alat sekaligus, pilih dua port yang berbeda.', 'Port COM sama', 'OK', 'Warning'); return }
    $script:History.Clear(); $script:LastValues.Clear()
    $script:ActivePorts = @{}
    if ($clarifierSelection) { $script:ActivePorts[[string]$script:Devices[0].device_name] = $clarifierSelection }
    if ($biologySelection) { $script:ActivePorts[[string]$script:Devices[1].device_name] = $biologySelection }
    $script:SavedPortMap = $script:ActivePorts.Clone()
    try { $script:SavedPortMap | ConvertTo-Json | Set-Content -LiteralPath $script:PortMapPath -Encoding UTF8 } catch { Add-Log ('Pemetaan port tidak tersimpan: ' + $_.Exception.Message) }
    $script:DatabaseWriteErrorReported = $false
    # Reset semua card menjadi visible dan pulihkan grid ke tata letak awal
    foreach ($device in $script:Devices) {
        $deviceName = [string]$device.device_name
        $tabPage = $tabs.TabPages | Where-Object { $_.Text -eq $deviceName } | Select-Object -First 1
        if ($tabPage) {
            $grid = $tabPage.Controls[0]
            $grid.Controls.Clear()
            $grid.ColumnCount = 3; $grid.RowCount = 2
            $grid.ColumnStyles.Clear(); $grid.RowStyles.Clear()
            $grid.ColumnStyles.Add([Windows.Forms.ColumnStyle]::new('Percent', 33.33))
            $grid.ColumnStyles.Add([Windows.Forms.ColumnStyle]::new('Percent', 33.33))
            $grid.ColumnStyles.Add([Windows.Forms.ColumnStyle]::new('Percent', 33.34))
            $grid.RowStyles.Add([Windows.Forms.RowStyle]::new('Percent', 50))
            $grid.RowStyles.Add([Windows.Forms.RowStyle]::new('Percent', 50))
            for ($i = 0; $i -lt $script:Params.Count; $i++) {
                $p = $script:Params[$i]
                $key = "$deviceName|$([string]$p.code)"
                $cardPanel = $cards[$key].Value.Parent
                $cardPanel.Visible = $true
                $grid.Controls.Add($cardPanel, ($i % 3), [int][Math]::Floor($i / 3))
            }
        }
    }
    foreach ($device in $script:Devices) { foreach ($p in $script:Params) { $key = [string]$device.device_name + '|' + [string]$p.code; $cards[$key].Value.Text = '--'; $cards[$key].Stamp.Text = if ($script:ActivePorts.ContainsKey([string]$device.device_name)) { 'Memeriksa sensor...' } else { 'Port tidak dipilih' } } }
    $start.Enabled = $false; $stop.Enabled = $true; $detect.Enabled = $false; $refresh.Enabled = $false; $clarifierPort.Enabled = $false; $biologyPort.Enabled = $false
    $status.Text = 'Status: Menghubungkan ke alat'; $status.ForeColor = [Drawing.Color]::FromArgb(90, 180, 255)
    $connectionSummary = @($script:ActivePorts.GetEnumerator() | ForEach-Object { $_.Key + ': ' + $_.Value }) -join '; '
    Add-Log ('Memulai pemantauan - ' + $connectionSummary)
    $portsJson = $script:ActivePorts | ConvertTo-Json -Compress
    $jobCommand = { param($workerPath, $workerMode, $ports) & $workerPath -Mode $workerMode -PortsJson $ports }
    $script:PollJob = Start-Job -ScriptBlock $jobCommand -ArgumentList $script:WorkerScriptPath, 'Poll', $portsJson
    $workerTimer.Start()
})
$stop.Add_Click({ if ($script:PollJob) { $stop.Enabled = $false; $status.Text = 'Status: Menghentikan'; Stop-Job -Job $script:PollJob -ErrorAction SilentlyContinue } })
$form.Add_Shown({ Refresh-Ports })
$form.Add_FormClosing({ foreach ($job in @($script:PollJob, $script:DetectJob)) { if ($job) { Stop-Job -Job $job -ErrorAction SilentlyContinue; Remove-Job -Job $job -Force -ErrorAction SilentlyContinue } } })
[void]$form.ShowDialog()

