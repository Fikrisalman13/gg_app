<?php
session_start();

while (ob_get_level()) {
    ob_end_clean();
}

error_reporting(0);
ini_set('display_errors', 0);

require_once '../../koneksi.php';
date_default_timezone_set('Asia/Jakarta');

function getKioskAccessToken(): string
{
    // Token khusus layar kiosk fullscreen agar polling tetap berjalan walau session user berakhir.
    return hash('sha256', 'gg_app_dashboard_kantin_kiosk_always_on_v1');
}

function jsonOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function normalizePhotoUrl(string $rawPath): string
{
    $path = trim(str_replace('\\', '/', $rawPath));
    if ($path === '') return '';
    $path = preg_replace('#^(\./|\.\./)+#', '', $path);
    if ($path === null) {
        $path = trim($rawPath);
    }
    if (preg_match('/^(https?:)?\/\//i', $path) === 1 || stripos($path, 'data:image/') === 0) return $path;
    $uploadsPos = stripos($path, 'uploads/');
    if ($uploadsPos !== false) {
        $path = substr($path, $uploadsPos);
    }
    if (strpos($path, '/gg_app/') === 0) return $path;
    if (strpos($path, '/') === 0) return '/gg_app' . $path;
    if (stripos($path, 'uploads/') === 0) return '/gg_app/' . $path;
    if (stripos($path, 'foto_karyawan/') === 0) return '/gg_app/uploads/' . $path;
    return '/gg_app/uploads/foto_karyawan/' . $path;
}

function fallbackAvatarDataUri(): string
{
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="360" height="480" viewBox="0 0 360 480">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#ecf4ef"/>
      <stop offset="100%" stop-color="#d6e8dc"/>
    </linearGradient>
  </defs>
  <rect width="360" height="480" fill="url(#g)"/>
  <circle cx="180" cy="165" r="70" fill="#8cb09a"/>
  <rect x="95" y="250" width="170" height="140" rx="24" fill="#7a9f89"/>
  <rect x="70" y="372" width="220" height="80" rx="18" fill="#5a7f69"/>
</svg>
SVG;
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function getTableColumns($conn, string $tableName): array
{
    static $cache = [];
    $key = strtolower($tableName);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME=?";
    $stmt = sqlsrv_query($conn, $sql, [$tableName]);
    $columns = [];

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $col = (string)($row['COLUMN_NAME'] ?? '');
            if ($col !== '') {
                $columns[strtolower($col)] = $col;
            }
        }
        sqlsrv_free_stmt($stmt);
    }

    $cache[$key] = $columns;
    return $columns;
}

function tableExists($conn, string $tableName): bool
{
    $stmt = sqlsrv_query($conn, "SELECT OBJECT_ID('dbo." . str_replace("'", "''", $tableName) . "', 'U') AS oid");
    if (!$stmt) return false;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return !empty($row['oid']);
}

function getMempColumns($conn): array
{
    return getTableColumns($conn, 'm_emp');
}

function detectPhotoColumn($conn): string
{
    $columns = getMempColumns($conn);
    $candidates = ['foto_path', 'photo_path', 'foto_karyawan', 'photo_karyawan', 'foto', 'photo', 'image', 'image_path', 'img', 'img_path', 'profile_photo'];
    foreach ($candidates as $candidate) {
        if (isset($columns[$candidate])) return $columns[$candidate];
    }
    return '';
}

function detectEmpNameColumn($conn): string
{
    $columns = getMempColumns($conn);
    $candidates = ['nama_lengkap', 'nama', 'name', 'full_name'];
    foreach ($candidates as $candidate) {
        if (isset($columns[$candidate])) return $columns[$candidate];
    }
    return '';
}

function formatDateIndo(string $dateYmd): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $dateYmd);
    if (!$dt) return $dateYmd;
    $bulan = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
    $b = $bulan[(int)$dt->format('n')] ?? $dt->format('m');
    return $dt->format('d') . ' ' . $b . ' ' . $dt->format('Y');
}

function resolveShiftByTime(string $timeHms): string
{
    $time = substr(trim($timeHms), 0, 8);
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return '-';
    }

    if ($time >= '11:30:00' && $time < '13:30:00') return 'Non Shift';
    if ($time >= '01:00:00' && $time < '04:00:00') return 'Shift Malam';
    if ($time >= '09:00:00' && $time < '11:30:00') return 'Shift Pagi';
    if ($time >= '17:00:00' && $time < '20:00:00') return 'Shift Siang';
    if (($time >= '04:00:00' && $time < '09:00:00') || ($time >= '14:00:00' && $time < '17:00:00') || ($time >= '20:00:00' || $time < '01:00:00')) {
        return 'No All';
    }
    return '-';
}

function quickDuplicateAcceptMarker(): string
{
    return '__ACCEPT_DUP_1MIN__';
}

function rejectOverTwoMarker(): string
{
    return '__REJECT_OVER2__';
}

function rejectCooldownMessage(): string
{
    return 'Absen kantin berikutnya hanya boleh setelah jeda minimal 3 jam.';
}

function normalizeDateRange(string $start, string $end): array
{
    $startDate = DateTime::createFromFormat('Y-m-d', $start) ?: new DateTime(date('Y-m-d'));
    $endDate = DateTime::createFromFormat('Y-m-d', $end) ?: new DateTime(date('Y-m-d'));
    if ($startDate > $endDate) {
        $tmp = $startDate;
        $startDate = $endDate;
        $endDate = $tmp;
    }
    return [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
}

function parseMachineData(string $data, string $p1, string $p2): string
{
    $data = ' ' . $data;
    $result = '';
    $start = strpos($data, $p1);
    if ($start !== false) {
        $end = strpos(strstr($data, $p1), $p2);
        if ($end !== false) {
            $result = substr($data, $start + strlen($p1), $end - strlen($p1));
        }
    }
    return $result;
}

function machineSoapRequest(string $ip, string $soapRequest, float $timeout = 1.2): string
{
    $connect = @fsockopen($ip, '80', $errno, $errstr, $timeout);
    if (!$connect) {
        return '';
    }

    $newLine = "\r\n";
    fputs($connect, 'POST /iWsService HTTP/1.0' . $newLine);
    fputs($connect, 'Content-Type: text/xml' . $newLine);
    fputs($connect, 'Content-Length: ' . strlen($soapRequest) . $newLine . $newLine);
    fputs($connect, $soapRequest . $newLine);

    $buffer = '';
    while ($response = fgets($connect, 2048)) {
        $buffer .= $response;
    }
    fclose($connect);

    return $buffer;
}

function fetchMachineUsers(string $ip, string $commKey): array
{
    $users = [];

    $soapRequest = '<?xml version="1.0"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <GetAllUserInfo xmlns="http://tempuri.org/">
      <ArgComKey xsi:type="xsd:integer" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' . $commKey . '</ArgComKey>
    </GetAllUserInfo>
  </soap:Body>
</soap:Envelope>';

    $buffer = machineSoapRequest($ip, $soapRequest);
    if ($buffer === '') {
        return $users;
    }

    $body = parseMachineData($buffer, '<GetAllUserInfoResponse>', '</GetAllUserInfoResponse>');
    if ($body === '') {
        return $users;
    }

    $rows = explode("\r\n", $body);
    foreach ($rows as $line) {
        $data = parseMachineData($line, '<Row>', '</Row>');
        if ($data === '') continue;

        $pin = trim(parseMachineData($data, '<PIN>', '</PIN>'));
        $pin2 = trim(parseMachineData($data, '<PIN2>', '</PIN2>'));
        $name = trim(parseMachineData($data, '<Name>', '</Name>'));
        if ($pin === '') continue;

        $userId = ($pin2 !== '') ? $pin2 : $pin;
        $userInfo = [
            'user_id' => $userId,
            'name' => $name !== '' ? $name : '-',
            'pin' => $pin,
            'pin2' => $pin2
        ];

        $users[$userId] = $userInfo;
        $users['pin_' . $pin] = $userInfo;
        if ($pin2 !== '') {
            $users['pin_' . $pin2] = $userInfo;
        }
    }

    return $users;
}

function resolveMachineUserInfo(array $users, string $pinFromLog): array
{
    // Samakan urutan lookup dengan pages/fingerprint/download_log_data.php
    // agar hasil mapping konsisten di semua halaman.
    if (isset($users[$pinFromLog])) {
        return $users[$pinFromLog];
    }
    if (isset($users['pin_' . $pinFromLog])) {
        return $users['pin_' . $pinFromLog];
    }

    return [
        'user_id' => $pinFromLog,
        'pin' => $pinFromLog,
        'pin2' => '',
        'name' => '-'
    ];
}

function fetchMachineLogs(string $ip, string $commKey): array
{
    $logs = [];
    $users = fetchMachineUsers($ip, $commKey);

    $soapRequest = '<GetAttLog><ArgComKey xsi:type="xsd:integer">' . $commKey . '</ArgComKey><Arg><PIN xsi:type="xsd:integer">All</PIN></Arg></GetAttLog>';
    $buffer = machineSoapRequest($ip, $soapRequest);
    if ($buffer === '') {
        return $logs;
    }

    $body = parseMachineData($buffer, '<GetAttLogResponse>', '</GetAttLogResponse>');
    if ($body === '') {
        return $logs;
    }

    $rows = explode("\r\n", $body);
    foreach ($rows as $line) {
        $data = parseMachineData($line, '<Row>', '</Row>');
        if ($data === '') continue;

        $pin = trim(parseMachineData($data, '<PIN>', '</PIN>'));
        $dateTimeRaw = trim(parseMachineData($data, '<DateTime>', '</DateTime>'));
        $verified = trim(parseMachineData($data, '<Verified>', '</Verified>'));
        $status = trim(parseMachineData($data, '<Status>', '</Status>'));

        if ($pin === '' || $dateTimeRaw === '') continue;

        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $dateTimeRaw);
        if (!$dt) {
            try {
                $dt = new DateTime($dateTimeRaw);
            } catch (Exception $e) {
                continue;
            }
        }
        $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
        $waktu = $dt->format('Y-m-d H:i:s');

        $mapped = resolveMachineUserInfo($users, $pin);
        $userId = $mapped['user_id'] ?? $pin;
        $name = $mapped['name'] ?? '-';

        $logs[] = [
            'pin' => $pin,
            'user_id' => (string)$userId,
            'name' => (string)$name,
            'waktu' => $waktu,
            'verified' => is_numeric($verified) ? (int)$verified : null,
            'status_code' => is_numeric($status) ? (int)$status : null,
            'shift_label' => resolveShiftByTime($dt->format('H:i:s'))
        ];
    }

    if (empty($logs)) {
        return $logs;
    }

    usort($logs, static function ($a, $b) {
        return strcmp($b['waktu'], $a['waktu']);
    });

    return array_slice($logs, 0, 300);
}

function shouldSyncMachineNow(string $machineId, float $minIntervalSec = 3.0): bool
{
    $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $machineId);
    if ($safeId === null || $safeId === '') {
        return false;
    }

    $lockFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dk_sync_' . $safeId . '.lock';
    $now = microtime(true);
    $last = 0.0;

    if (is_file($lockFile)) {
        $raw = @file_get_contents($lockFile);
        if ($raw !== false) {
            $raw = trim($raw);
            if ($raw !== '' && is_numeric($raw)) {
                $last = (float)$raw;
            }
        }
        if ($last <= 0) {
            $mtime = @filemtime($lockFile);
            if ($mtime !== false) {
                $last = (float)$mtime;
            }
        }
    }

    if ($last > 0 && ($now - $last) < $minIntervalSec) {
        return false;
    }

    @file_put_contents($lockFile, (string)$now, LOCK_EX);
    return true;
}

function getRealtimeSyncIntervalSec(): float
{
    $time = date('H:i:s');

    $inShiftMalam = ($time >= '01:00:00' && $time < '04:00:00');
    $inShiftPagi = ($time >= '09:00:00' && $time < '11:30:00');
    $inNonShift = ($time >= '11:30:00' && $time < '13:30:00');
    $inShiftSiang = ($time >= '17:00:00' && $time < '20:00:00');

    return ($inShiftMalam || $inShiftPagi || $inNonShift || $inShiftSiang) ? 1.5 : 3.0;
}

function getSyncMachines($conn, string $mesinFilter): array
{
    $machines = [];
    if ($mesinFilter !== '') {
        $sql = "SELECT id, nama_mesin, ip_address, comm_key FROM dbo.m_fingerprint WHERE CAST(id AS NVARCHAR(100)) = ?";
        $stmt = sqlsrv_query($conn, $sql, [$mesinFilter]);
    } else {
        $sql = "SELECT id, nama_mesin, ip_address, comm_key FROM dbo.m_fingerprint ORDER BY id DESC";
        $stmt = sqlsrv_query($conn, $sql);
    }

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $machines[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }

    return $machines;
}

function insertDashboardLog($conn, array $machine, array $log, array $tableCols): string
{
    $mesinId = (int)($machine['id'] ?? 0);
    $userId = trim((string)($log['user_id'] ?? ''));
    $waktu = trim((string)($log['waktu'] ?? ''));

    if ($mesinId <= 0 || $userId === '' || $waktu === '') {
        return 'invalid';
    }

    $incomingName = trim((string)($log['name'] ?? '-'));
    if ($incomingName === '') {
        $incomingName = '-';
    }
    $incomingShift = trim((string)($log['shift_label'] ?? resolveShiftByTime(substr($waktu, 11, 8))));
    if ($incomingShift === '') {
        $incomingShift = '-';
    }
    $incomingOriginalPin = trim((string)($log['pin'] ?? ''));
    $incomingUpdatedAt = date('Y-m-d H:i:s');

    $checkDup = sqlsrv_query(
        $conn,
        "SELECT TOP 1 LogId, UserId, Nama, ShiftLabel, OriginalPIN, Verified, StatusCode
         FROM dbo.LogDashboardKantin
         WHERE MesinId = ? AND UserId = ? AND Waktu = ?
         ORDER BY LogId DESC",
        [$mesinId, $userId, $waktu]
    );

    $dupRow = ($checkDup) ? sqlsrv_fetch_array($checkDup, SQLSRV_FETCH_ASSOC) : null;
    if (!$dupRow && $checkDup) {
        if ($checkDup) {
            sqlsrv_free_stmt($checkDup);
        }
        $checkDup = null;
    }

    // Fallback: cocokkan berdasarkan OriginalPIN + Waktu + Mesin untuk
    // menyembuhkan baris lama yang salah mapping UserId.
    if (
        !$dupRow &&
        $incomingOriginalPin !== '' &&
        isset($tableCols['originalpin'])
    ) {
        $checkDupByPin = sqlsrv_query(
            $conn,
            "SELECT TOP 1 LogId, UserId, Nama, ShiftLabel, OriginalPIN, Verified, StatusCode
             FROM dbo.LogDashboardKantin
             WHERE MesinId = ? AND Waktu = ? AND OriginalPIN = ?
             ORDER BY LogId DESC",
            [$mesinId, $waktu, $incomingOriginalPin]
        );
        $dupRow = ($checkDupByPin) ? sqlsrv_fetch_array($checkDupByPin, SQLSRV_FETCH_ASSOC) : null;
        if ($checkDupByPin) {
            sqlsrv_free_stmt($checkDupByPin);
        }
    }

    if ($dupRow) {
        $updates = [];
        $updateParams = [];

        if (isset($tableCols['userid'])) {
            $oldUserId = trim((string)($dupRow['UserId'] ?? ''));
            if ($oldUserId !== $userId) {
                $updates[] = '[' . $tableCols['userid'] . '] = ?';
                $updateParams[] = $userId;
            }
        }

        if (isset($tableCols['nama'])) {
            $oldName = trim((string)($dupRow['Nama'] ?? ''));
            if ($incomingName !== '' && strcasecmp($oldName, $incomingName) !== 0) {
                $updates[] = '[' . $tableCols['nama'] . '] = ?';
                $updateParams[] = $incomingName;
            }
        }

        if (isset($tableCols['shiftlabel'])) {
            $oldShift = trim((string)($dupRow['ShiftLabel'] ?? ''));
            if ($incomingShift !== '' && strcasecmp($oldShift, $incomingShift) !== 0) {
                $updates[] = '[' . $tableCols['shiftlabel'] . '] = ?';
                $updateParams[] = $incomingShift;
            }
        }

        if (isset($tableCols['originalpin'])) {
            $oldPin = trim((string)($dupRow['OriginalPIN'] ?? ''));
            if ($incomingOriginalPin !== '' && $oldPin !== $incomingOriginalPin) {
                $updates[] = '[' . $tableCols['originalpin'] . '] = ?';
                $updateParams[] = $incomingOriginalPin;
            }
        }

        if (isset($tableCols['verified']) && isset($log['verified']) && $log['verified'] !== null) {
            $oldVerified = isset($dupRow['Verified']) ? (string)$dupRow['Verified'] : '';
            $newVerified = (string)$log['verified'];
            if ($oldVerified !== $newVerified) {
                $updates[] = '[' . $tableCols['verified'] . '] = ?';
                $updateParams[] = $log['verified'];
            }
        }

        if (isset($tableCols['statuscode']) && isset($log['status_code']) && $log['status_code'] !== null) {
            $oldStatus = isset($dupRow['StatusCode']) ? (string)$dupRow['StatusCode'] : '';
            $newStatus = (string)$log['status_code'];
            if ($oldStatus !== $newStatus) {
                $updates[] = '[' . $tableCols['statuscode'] . '] = ?';
                $updateParams[] = $log['status_code'];
            }
        }

        sqlsrv_free_stmt($checkDup);
        if (empty($updates)) {
            return 'duplicate';
        }

        if (isset($tableCols['updatedat'])) {
            $updates[] = '[' . $tableCols['updatedat'] . '] = ?';
            $updateParams[] = $incomingUpdatedAt;
        }

        $updateParams[] = (int)$dupRow['LogId'];
        $updateSql = 'UPDATE dbo.LogDashboardKantin SET ' . implode(', ', $updates) . ' WHERE LogId = ?';
        $updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);
        return $updateStmt ? 'updated' : 'error';
    }
    if ($checkDup) {
        sqlsrv_free_stmt($checkDup);
    }

    $quickMarker = quickDuplicateAcceptMarker();
    $rejectOverTwo = rejectOverTwoMarker();
    $duplicateWindowSec = 60;
    $cooldownSec = 3 * 3600;
    $lastCountedSuccessTime = null;

    if (isset($tableCols['rejectreason'])) {
        $rejectReasonCol = $tableCols['rejectreason'];
        $lastSuccessStmt = sqlsrv_query(
            $conn,
            "SELECT TOP 1 Waktu AS success_waktu
             FROM dbo.LogDashboardKantin
             WHERE UserId = ?
               AND Waktu < ?
               AND ISNULL(IsRejected, 0) = 0
               AND ISNULL(LTRIM(RTRIM(CAST([$rejectReasonCol] AS NVARCHAR(255)))), '') <> ?
             ORDER BY Waktu DESC, LogId DESC",
            [$userId, $waktu, $quickMarker]
        );
    } else {
        $lastSuccessStmt = sqlsrv_query(
            $conn,
            "SELECT TOP 1 Waktu AS success_waktu
             FROM dbo.LogDashboardKantin
             WHERE UserId = ?
               AND Waktu < ?
               AND ISNULL(IsRejected, 0) = 0
             ORDER BY Waktu DESC, LogId DESC",
            [$userId, $waktu]
        );
    }

    if ($lastSuccessStmt && ($successRow = sqlsrv_fetch_array($lastSuccessStmt, SQLSRV_FETCH_ASSOC))) {
        $rawSuccess = $successRow['success_waktu'] ?? null;
        if ($rawSuccess instanceof DateTime) {
            $lastCountedSuccessTime = $rawSuccess->format('Y-m-d H:i:s');
        } elseif (!empty($rawSuccess)) {
            $lastCountedSuccessTime = (string)$rawSuccess;
        }
    }
    if ($lastSuccessStmt) {
        sqlsrv_free_stmt($lastSuccessStmt);
    }

    $isRejected = 0;
    $isQuickAcceptedDuplicate = false;
    $isRejectedOverTwoPlus = false;
    if ($lastCountedSuccessTime !== null) {
        $currTs = strtotime($waktu);
        $startTs = strtotime($lastCountedSuccessTime);
        if ($currTs !== false && $startTs !== false) {
            $elapsed = $currTs - $startTs;
            if ($elapsed >= 0 && $elapsed < $cooldownSec) {
                $attemptCountBeforeCurrent = 0;
                $attemptCountStmt = sqlsrv_query(
                    $conn,
                    "SELECT COUNT(*) AS total_attempt
                     FROM dbo.LogDashboardKantin
                     WHERE UserId = ?
                       AND Waktu >= ?
                       AND Waktu < ?",
                    [$userId, $lastCountedSuccessTime, $waktu]
                );
                if ($attemptCountStmt && ($attemptCountRow = sqlsrv_fetch_array($attemptCountStmt, SQLSRV_FETCH_ASSOC))) {
                    $attemptCountBeforeCurrent = (int)($attemptCountRow['total_attempt'] ?? 0);
                }
                if ($attemptCountStmt) {
                    sqlsrv_free_stmt($attemptCountStmt);
                }
                $attemptIndexInWindow = $attemptCountBeforeCurrent + 1;

                if ($elapsed < $duplicateWindowSec) {
                    $isRejected = 0;
                    $isQuickAcceptedDuplicate = true;
                } else {
                    $isRejected = 1;
                    $isRejectedOverTwoPlus = $attemptIndexInWindow >= 3;
                }
            }
        }
    }

    $rejectReason = null;
    if ($isRejected === 1) {
        $rejectReason = $isRejectedOverTwoPlus ? $rejectOverTwo : rejectCooldownMessage();
    } elseif ($isQuickAcceptedDuplicate) {
        $rejectReason = $quickMarker;
    }

    $rowData = [
        'MesinId' => $mesinId,
        'UserId' => $userId,
        'Nama' => $incomingName,
        'Waktu' => $waktu,
        'ShiftLabel' => $incomingShift,
        'Verified' => $log['verified'],
        'StatusCode' => $log['status_code'],
        'OriginalPIN' => $incomingOriginalPin,
        'IsRejected' => $isRejected,
        'RejectReason' => $rejectReason,
        'UpdatedAt' => $incomingUpdatedAt
    ];

    $insertCols = [];
    $placeholders = [];
    $params = [];

    foreach ($rowData as $col => $val) {
        $lk = strtolower($col);
        if (isset($tableCols[$lk])) {
            $insertCols[] = '[' . $tableCols[$lk] . ']';
            $placeholders[] = '?';
            $params[] = $val;
        }
    }

    if (empty($insertCols)) {
        return 'error';
    }

    $sqlInsert = 'INSERT INTO dbo.LogDashboardKantin (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $insStmt = sqlsrv_query($conn, $sqlInsert, $params);

    return $insStmt ? 'inserted' : 'error';
}

function syncRealtimeLogs($conn, string $mesinFilter): array
{
    $stats = [
        'machines' => 0,
        'inserted' => 0,
        'updated' => 0,
        'duplicate' => 0,
        'invalid' => 0,
        'error' => 0,
        'skipped' => 0
    ];

    if (!tableExists($conn, 'LogDashboardKantin')) {
        return $stats;
    }

    $tableCols = getTableColumns($conn, 'LogDashboardKantin');
    if (empty($tableCols)) {
        return $stats;
    }

    $machines = getSyncMachines($conn, $mesinFilter);
    $stats['machines'] = count($machines);
    $minIntervalSec = getRealtimeSyncIntervalSec();

    foreach ($machines as $machine) {
        $machineId = (string)($machine['id'] ?? '');
        if ($machineId === '' || !shouldSyncMachineNow($machineId, $minIntervalSec)) {
            $stats['skipped']++;
            continue;
        }

        $ip = trim((string)($machine['ip_address'] ?? ''));
        $commKey = trim((string)($machine['comm_key'] ?? ''));
        if ($ip === '' || $commKey === '') {
            $stats['invalid']++;
            continue;
        }

        $logs = fetchMachineLogs($ip, $commKey);
        if (empty($logs)) {
            continue;
        }

        usort($logs, static function ($a, $b) {
            return strcmp((string)($a['waktu'] ?? ''), (string)($b['waktu'] ?? ''));
        });

        foreach ($logs as $log) {
            $res = insertDashboardLog($conn, $machine, $log, $tableCols);
            if (isset($stats[$res])) {
                $stats[$res]++;
            }
        }
    }

    return $stats;
}

function fetchLatestAttendance($conn, string $mesinFilter, string $tanggalAwal, string $tanggalAkhir): ?array
{
    if (!tableExists($conn, 'LogDashboardKantin')) {
        return null;
    }

    $photoColumn = detectPhotoColumn($conn);
    $empNameColumn = detectEmpNameColumn($conn);
    $tableCols = getTableColumns($conn, 'LogDashboardKantin');

    $photoSelect = $photoColumn !== '' ? ', emp.[' . $photoColumn . '] AS emp_photo' : ', CAST(NULL AS NVARCHAR(255)) AS emp_photo';
    $empNameExpr = $empNameColumn !== '' ? 'emp.[' . $empNameColumn . ']' : 'NULL';
    $empNameOrderExpr = $empNameColumn !== '' ? 'e.[' . $empNameColumn . ']' : "''";
    $rejectReasonSelect = isset($tableCols['rejectreason'])
        ? ', ISNULL(LTRIM(RTRIM(CAST(k.[' . $tableCols['rejectreason'] . '] AS NVARCHAR(255)))), \'\') AS reject_reason'
        : ', CAST(\'\' AS NVARCHAR(255)) AS reject_reason';

    $sql = "SELECT TOP 1
                k.Waktu AS waktu,
                k.UserId AS user_id,
                k.ShiftLabel AS shift_label,
                k.IsRejected AS is_rejected,
                COALESCE(NULLIF(f.nama_mesin, ''), '-') AS nama_mesin,
                COALESCE(NULLIF(d.dept, ''), '-') AS dept,
                COALESCE(NULLIF(b.bagian, ''), '-') AS bagian,
                COALESCE(NULLIF(k.Nama, ''), NULLIF(" . $empNameExpr . ", ''), '-') AS nama_karyawan,
                CAST(ISNULL(cnt.total_masuk, 1) AS INT) AS total_masuk_hari,
                prev.prev_waktu AS prev_waktu
                " . $rejectReasonSelect . "
                " . $photoSelect . "
            FROM dbo.LogDashboardKantin AS k
            LEFT JOIN dbo.m_fingerprint AS f ON k.MesinId = f.id
            OUTER APPLY (
                SELECT TOP 1 e.*
                FROM dbo.m_emp AS e
                WHERE CAST(k.UserId AS NVARCHAR(100)) = CAST(e.id_emp AS NVARCHAR(100))
                   OR CAST(k.UserId AS NVARCHAR(100)) = CAST(e.nik AS NVARCHAR(100))
                   OR CAST(k.UserId AS NVARCHAR(100)) = CAST(e.finger_key AS NVARCHAR(100))
                ORDER BY
                    CASE
                        WHEN LTRIM(RTRIM(COALESCE(NULLIF(k.Nama, ''), ''))) <> ''
                             AND LOWER(LTRIM(RTRIM(COALESCE(" . $empNameOrderExpr . ", ''))))
                                 = LOWER(LTRIM(RTRIM(COALESCE(k.Nama, '')))) THEN 0
                        WHEN CAST(k.UserId AS NVARCHAR(100)) = CAST(e.finger_key AS NVARCHAR(100)) THEN 1
                        WHEN CAST(k.UserId AS NVARCHAR(100)) = CAST(e.id_emp AS NVARCHAR(100)) THEN 2
                        WHEN CAST(k.UserId AS NVARCHAR(100)) = CAST(e.nik AS NVARCHAR(100)) THEN 3
                        ELSE 99
                    END,
                    ISNULL(e.aktif, 0) DESC,
                    e.id_emp DESC
            ) AS emp
            LEFT JOIN dbo.m_dept AS d ON emp.id_dept = d.id_dept
            LEFT JOIN dbo.m_bag AS b ON emp.id_bag = b.id_bag
            OUTER APPLY (
                SELECT COUNT(*) AS total_masuk
                FROM dbo.LogDashboardKantin AS k2
                WHERE CAST(k2.Waktu AS DATE) = CAST(k.Waktu AS DATE)
                  AND CAST(k2.UserId AS NVARCHAR(100)) = CAST(k.UserId AS NVARCHAR(100))
            ) AS cnt
            OUTER APPLY (
                SELECT TOP 1
                    k3.Waktu AS prev_waktu
                FROM dbo.LogDashboardKantin AS k3
                WHERE CAST(k3.UserId AS NVARCHAR(100)) = CAST(k.UserId AS NVARCHAR(100))
                  AND k3.Waktu < k.Waktu
                ORDER BY k3.Waktu DESC, k3.LogId DESC
            ) AS prev
            WHERE k.Waktu >= ?
              AND k.Waktu < DATEADD(DAY, 1, ?)";

    $params = [$tanggalAwal . ' 00:00:00', $tanggalAkhir . ' 00:00:00'];
    if ($mesinFilter !== '') {
        if (ctype_digit($mesinFilter)) {
            $sql .= " AND k.MesinId = ?";
            $params[] = (int)$mesinFilter;
        } else {
            $sql .= " AND CAST(k.MesinId AS NVARCHAR(100)) = ?";
            $params[] = $mesinFilter;
        }
    }

    $sql .= " ORDER BY k.Waktu DESC, k.LogId DESC";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) return null;

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    if (!$row) return null;

    $waktuRaw = $row['waktu'] ?? null;
    $dt = null;
    if ($waktuRaw instanceof DateTime) {
        $dt = clone $waktuRaw;
    } elseif (!empty($waktuRaw)) {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', (string)$waktuRaw) ?: new DateTime((string)$waktuRaw);
    }
    if (!($dt instanceof DateTime)) $dt = new DateTime();
    $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));

    $tanggalYmd = $dt->format('Y-m-d');
    $jam = $dt->format('H:i:s');
    $shiftLabel = trim((string)($row['shift_label'] ?? ''));
    if ($shiftLabel === '') {
        $shiftLabel = resolveShiftByTime($jam);
    }

    $eventKey = $dt->format('YmdHis') . '|' . trim((string)($row['user_id'] ?? '')) . '|' . trim((string)($row['nama_mesin'] ?? ''));

    $photoUrl = normalizePhotoUrl((string)($row['emp_photo'] ?? ''));
    if ($photoUrl === '') {
        $photoUrl = fallbackAvatarDataUri();
    }

    $totalMasuk = (int)($row['total_masuk_hari'] ?? 1);
    $isRejectedFlag = (int)($row['is_rejected'] ?? 0) === 1;
    $isViolation = $isRejectedFlag;
    $rejectReasonRaw = trim((string)($row['reject_reason'] ?? ''));
    $rejectAudioKey = ($isViolation && $rejectReasonRaw === rejectOverTwoMarker()) ? 'reject_over2' : 'reject_default';
    $firstScanTime = '';
    $secondScanTime = '';
    if ($isViolation) {
        $dtFirst = null;
        $firstRaw = $row['prev_waktu'] ?? null;
        if ($firstRaw instanceof DateTime) {
            $dtFirst = clone $firstRaw;
        } elseif (!empty($firstRaw)) {
            $dtFirst = DateTime::createFromFormat('Y-m-d H:i:s', (string)$firstRaw) ?: new DateTime((string)$firstRaw);
        }
        if ($dtFirst instanceof DateTime) {
            $dtFirst->setTimezone(new DateTimeZone('Asia/Jakarta'));
            $firstScanTime = $dtFirst->format('H:i:s');
        }
        $secondScanTime = $jam;
    }

    return [
        'event_key' => $eventKey,
        'user_id' => trim((string)($row['user_id'] ?? '')),
        'nama' => (string)($row['nama_karyawan'] ?? '-'),
        'departemen' => (string)($row['dept'] ?? '-'),
        'bagian' => (string)($row['bagian'] ?? '-'),
        'shift_label' => $shiftLabel,
        'mesin' => (string)($row['nama_mesin'] ?? '-'),
        'jam' => $jam,
        'tanggal_iso' => $tanggalYmd,
        'tanggal_text' => formatDateIndo($tanggalYmd),
        'photo_url' => $photoUrl,
        'total_masuk_hari' => $totalMasuk,
        'first_scan_time' => $firstScanTime,
        'second_scan_time' => $secondScanTime,
        'reject_audio_key' => $rejectAudioKey,
        'status_code' => $isViolation ? 'violation' : 'success',
        'status_title' => $isViolation ? 'ABSEN DITOLAK' : 'ABSEN BERHASIL',
        'status_message' => $isViolation ? rejectCooldownMessage() : 'Terima kasih, absensi kantin Anda telah tercatat.'
    ];
}

function fetchRecentAttendanceHistory($conn, string $mesinFilter, string $tanggalAwal, string $tanggalAkhir, int $limitPerStatus = 7): array
{
    $result = [
        'success' => [],
        'reject' => []
    ];

    if (!tableExists($conn, 'LogDashboardKantin')) {
        return $result;
    }

    $limitPerStatus = max(1, min(20, $limitPerStatus));

    $empNameColumn = detectEmpNameColumn($conn);
    $empNameExpr = $empNameColumn !== '' ? 'emp.[' . $empNameColumn . ']' : 'NULL';
    $empNameOrderExpr = $empNameColumn !== '' ? 'e.[' . $empNameColumn . ']' : "''";
    $namaExpr = "COALESCE(NULLIF(k.Nama, ''), NULLIF(" . $empNameExpr . ", ''), '-')";

    $baseSql = "FROM dbo.LogDashboardKantin AS k
                OUTER APPLY (
                    SELECT TOP 1 e.*
                    FROM dbo.m_emp AS e
                    WHERE CAST(k.UserId AS NVARCHAR(100)) = CAST(e.id_emp AS NVARCHAR(100))
                       OR CAST(k.UserId AS NVARCHAR(100)) = CAST(e.nik AS NVARCHAR(100))
                       OR CAST(k.UserId AS NVARCHAR(100)) = CAST(e.finger_key AS NVARCHAR(100))
                    ORDER BY
                        CASE
                            WHEN LTRIM(RTRIM(COALESCE(NULLIF(k.Nama, ''), ''))) <> ''
                                 AND LOWER(LTRIM(RTRIM(COALESCE(" . $empNameOrderExpr . ", ''))))
                                     = LOWER(LTRIM(RTRIM(COALESCE(k.Nama, '')))) THEN 0
                            WHEN CAST(k.UserId AS NVARCHAR(100)) = CAST(e.finger_key AS NVARCHAR(100)) THEN 1
                            WHEN CAST(k.UserId AS NVARCHAR(100)) = CAST(e.id_emp AS NVARCHAR(100)) THEN 2
                            WHEN CAST(k.UserId AS NVARCHAR(100)) = CAST(e.nik AS NVARCHAR(100)) THEN 3
                            ELSE 99
                        END,
                        ISNULL(e.aktif, 0) DESC,
                        e.id_emp DESC
                ) AS emp
                WHERE k.Waktu >= ?
                  AND k.Waktu < DATEADD(DAY, 1, ?)";

    $statusFilters = [
        'success' => 'ISNULL(k.IsRejected, 0) = 0',
        'reject' => 'ISNULL(k.IsRejected, 0) = 1',
    ];

    foreach ($statusFilters as $bucket => $statusFilter) {
        $sql = "SELECT TOP {$limitPerStatus}
                    k.Waktu AS waktu,
                    " . $namaExpr . " AS nama_display
                " . $baseSql . "
                  AND " . $statusFilter;

        $params = [$tanggalAwal . ' 00:00:00', $tanggalAkhir . ' 00:00:00'];
        if ($mesinFilter !== '') {
            if (ctype_digit($mesinFilter)) {
                $sql .= " AND k.MesinId = ?";
                $params[] = (int)$mesinFilter;
            } else {
                $sql .= " AND CAST(k.MesinId AS NVARCHAR(100)) = ?";
                $params[] = $mesinFilter;
            }
        }

        $sql .= " ORDER BY k.Waktu DESC, k.LogId DESC";
        $stmt = sqlsrv_query($conn, $sql, $params);
        if (!$stmt) {
            continue;
        }

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $name = trim((string)($row['nama_display'] ?? '-'));
            if ($name === '') {
                $name = '-';
            }

            $dt = null;
            $waktuRaw = $row['waktu'] ?? null;
            if ($waktuRaw instanceof DateTime) {
                $dt = clone $waktuRaw;
            } elseif (!empty($waktuRaw)) {
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', (string)$waktuRaw) ?: new DateTime((string)$waktuRaw);
            }
            if (!($dt instanceof DateTime)) {
                $dt = new DateTime();
            }
            $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));

            $result[$bucket][] = [
                'nama' => $name,
                'jam' => $dt->format('H:i:s')
            ];
        }
        sqlsrv_free_stmt($stmt);
    }

    return $result;
}

function fetchEmployeeAttendanceHistory(
    $conn,
    string $mesinFilter,
    string $tanggalAwal,
    string $tanggalAkhir,
    string $userId,
    string $namaKaryawan,
    int $limit = 8
): array {
    $rows = [];
    if (!tableExists($conn, 'LogDashboardKantin')) {
        return $rows;
    }

    $userId = trim($userId);
    $namaKaryawan = trim($namaKaryawan);
    if ($userId === '' && $namaKaryawan === '') {
        return $rows;
    }

    $limit = max(1, min(20, $limit));
    $sql = "SELECT TOP {$limit}
                k.Waktu AS waktu,
                ISNULL(k.IsRejected, 0) AS is_rejected
            FROM dbo.LogDashboardKantin AS k
            WHERE k.Waktu >= ?
              AND k.Waktu < DATEADD(DAY, 1, ?)";

    $params = [$tanggalAwal . ' 00:00:00', $tanggalAkhir . ' 00:00:00'];

    if ($mesinFilter !== '') {
        if (ctype_digit($mesinFilter)) {
            $sql .= " AND k.MesinId = ?";
            $params[] = (int)$mesinFilter;
        } else {
            $sql .= " AND CAST(k.MesinId AS NVARCHAR(100)) = ?";
            $params[] = $mesinFilter;
        }
    }

    if ($userId !== '') {
        $sql .= " AND LTRIM(RTRIM(CAST(ISNULL(k.UserId, '') AS NVARCHAR(100)))) = ?";
        $params[] = $userId;
    } else {
        $sql .= " AND LOWER(LTRIM(RTRIM(COALESCE(k.Nama, '')))) = LOWER(?)";
        $params[] = $namaKaryawan;
    }

    $sql .= " ORDER BY k.Waktu DESC, k.LogId DESC";

    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) {
        return $rows;
    }

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $dt = null;
        $waktuRaw = $row['waktu'] ?? null;
        if ($waktuRaw instanceof DateTime) {
            $dt = clone $waktuRaw;
        } elseif (!empty($waktuRaw)) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', (string)$waktuRaw) ?: new DateTime((string)$waktuRaw);
        }
        if (!($dt instanceof DateTime)) {
            $dt = new DateTime();
        }
        $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));

        $tanggalIso = $dt->format('Y-m-d');
        $rows[] = [
            'tanggal_iso' => $tanggalIso,
            'tanggal' => formatDateIndo($tanggalIso),
            'jam' => $dt->format('H:i:s'),
            'status_code' => ((int)($row['is_rejected'] ?? 0) === 1) ? 'reject' : 'success',
            'status_label' => ((int)($row['is_rejected'] ?? 0) === 1) ? 'Ditolak' : 'Diterima',
        ];
    }
    sqlsrv_free_stmt($stmt);

    return $rows;
}

function fetchKioskStats($conn, string $mesinFilter, string $tanggalAwal, string $tanggalAkhir): array
{
    $stats = [
        'total_absensi' => 0,
        'total_karyawan_unik' => 0,
        'hari_aktif' => 0,
        'avg_harian' => 0,
        'total_berhasil' => 0,
        'total_ditolak' => 0,
        'shift_siang' => 0,
        'shift_malam' => 0,
        'shift_pagi' => 0,
        'non_shift' => 0,
        'no_all' => 0
    ];

    if (!tableExists($conn, 'LogDashboardKantin')) {
        return $stats;
    }

    $tableCols = getTableColumns($conn, 'LogDashboardKantin');
    $quickMarker = quickDuplicateAcceptMarker();
    $rejectReasonSelect = "CAST('' AS NVARCHAR(255)) AS reject_reason,";
    if (isset($tableCols['rejectreason'])) {
        $rejectReasonCol = $tableCols['rejectreason'];
        $rejectReasonSelect = "ISNULL(LTRIM(RTRIM(CAST(k.[$rejectReasonCol] AS NVARCHAR(255)))), '') AS reject_reason,";
    }

    $sql = "WITH base AS (
                SELECT
                    CAST(k.Waktu AS DATE) AS tanggal,
                    p.person_key,
                    CASE WHEN ISNULL(k.IsRejected, 0) = 1 THEN 1 ELSE 0 END AS is_violation,
                    $rejectReasonSelect
                    CASE
                        WHEN LOWER(LTRIM(RTRIM(COALESCE(NULLIF(k.ShiftLabel, ''), '')))) IN ('shift siang', 'sift siang') THEN 'Shift Siang'
                        WHEN LOWER(LTRIM(RTRIM(COALESCE(NULLIF(k.ShiftLabel, ''), '')))) = 'shift malam' THEN 'Shift Malam'
                        WHEN LOWER(LTRIM(RTRIM(COALESCE(NULLIF(k.ShiftLabel, ''), '')))) = 'shift pagi' THEN 'Shift Pagi'
                        WHEN LOWER(LTRIM(RTRIM(COALESCE(NULLIF(k.ShiftLabel, ''), '')))) = 'non shift' THEN 'Non Shift'
                        WHEN LOWER(LTRIM(RTRIM(COALESCE(NULLIF(k.ShiftLabel, ''), '')))) = 'no all' THEN 'No All'
                        WHEN CAST(k.Waktu AS TIME) >= '11:30:00' AND CAST(k.Waktu AS TIME) < '13:30:00' THEN 'Non Shift'
                        WHEN CAST(k.Waktu AS TIME) >= '01:00:00' AND CAST(k.Waktu AS TIME) < '04:00:00' THEN 'Shift Malam'
                        WHEN CAST(k.Waktu AS TIME) >= '09:00:00' AND CAST(k.Waktu AS TIME) < '11:30:00' THEN 'Shift Pagi'
                        WHEN CAST(k.Waktu AS TIME) >= '17:00:00' AND CAST(k.Waktu AS TIME) < '20:00:00' THEN 'Shift Siang'
                        WHEN (CAST(k.Waktu AS TIME) >= '04:00:00' AND CAST(k.Waktu AS TIME) < '09:00:00')
                          OR (CAST(k.Waktu AS TIME) >= '14:00:00' AND CAST(k.Waktu AS TIME) < '17:00:00')
                          OR (CAST(k.Waktu AS TIME) >= '20:00:00' OR CAST(k.Waktu AS TIME) < '01:00:00') THEN 'No All'
                        ELSE '-'
                    END AS shift_label
                FROM dbo.LogDashboardKantin AS k
                CROSS APPLY (
                    SELECT CASE
                        WHEN k.UserId IS NULL OR LTRIM(RTRIM(CAST(k.UserId AS NVARCHAR(100)))) = ''
                            THEN COALESCE(NULLIF(LTRIM(RTRIM(k.Nama)), ''), '-')
                        ELSE LTRIM(RTRIM(CAST(k.UserId AS NVARCHAR(100))))
                    END AS person_key
                ) AS p
                WHERE CAST(k.Waktu AS DATE) BETWEEN ? AND ?";

    $params = [$tanggalAwal, $tanggalAkhir];
    if ($mesinFilter !== '') {
        $sql .= " AND CAST(k.MesinId AS NVARCHAR(100)) = ?";
        $params[] = $mesinFilter;
    }

    $sql .= "),
            scored AS (
                SELECT
                    b.*,
                    CASE
                        WHEN b.is_violation = 0
                             AND b.reject_reason <> ?
                            THEN 1
                        ELSE 0
                    END AS is_success_countable
                FROM base AS b
            )
            SELECT
                COUNT(*) AS total_absensi,
                COUNT(DISTINCT scored.person_key) AS total_karyawan_unik,
                COUNT(DISTINCT scored.tanggal) AS hari_aktif,
                ISNULL(SUM(scored.is_success_countable), 0) AS total_berhasil,
                ISNULL(SUM(CASE WHEN scored.is_violation = 1 THEN 1 ELSE 0 END), 0) AS total_ditolak,
                ISNULL(SUM(CASE WHEN scored.is_success_countable = 1 AND scored.shift_label = 'Shift Siang' THEN 1 ELSE 0 END), 0) AS shift_siang,
                ISNULL(SUM(CASE WHEN scored.is_success_countable = 1 AND scored.shift_label = 'Shift Malam' THEN 1 ELSE 0 END), 0) AS shift_malam,
                ISNULL(SUM(CASE WHEN scored.is_success_countable = 1 AND scored.shift_label = 'Shift Pagi' THEN 1 ELSE 0 END), 0) AS shift_pagi,
                ISNULL(SUM(CASE WHEN scored.is_success_countable = 1 AND scored.shift_label = 'Non Shift' THEN 1 ELSE 0 END), 0) AS non_shift,
                ISNULL(SUM(CASE WHEN scored.is_success_countable = 1 AND scored.shift_label = 'No All' THEN 1 ELSE 0 END), 0) AS no_all
            FROM scored";
    $params[] = $quickMarker;

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        $stats['total_absensi'] = (int)($row['total_absensi'] ?? 0);
        $stats['total_karyawan_unik'] = (int)($row['total_karyawan_unik'] ?? 0);
        $stats['hari_aktif'] = (int)($row['hari_aktif'] ?? 0);
        $stats['total_berhasil'] = (int)($row['total_berhasil'] ?? 0);
        $stats['total_ditolak'] = (int)($row['total_ditolak'] ?? 0);
        $stats['shift_siang'] = (int)($row['shift_siang'] ?? 0);
        $stats['shift_malam'] = (int)($row['shift_malam'] ?? 0);
        $stats['shift_pagi'] = (int)($row['shift_pagi'] ?? 0);
        $stats['non_shift'] = (int)($row['non_shift'] ?? 0);
        $stats['no_all'] = (int)($row['no_all'] ?? 0);
        if ($stats['hari_aktif'] > 0) {
            $stats['avg_harian'] = round($stats['total_berhasil'] / $stats['hari_aktif'], 2);
        }
    }
    if ($stmt) {
        sqlsrv_free_stmt($stmt);
    }

    return $stats;
}

function queryCount($conn, string $sql, array $params): int
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return (int)($row['total'] ?? 0);
}

function fetchTableData($conn, string $mesinFilter, string $tanggalAwal, string $tanggalAkhir, int $draw, int $start, int $length, string $search): array
{
    if ($length <= 0) $length = 25;
    if ($length > 200) $length = 200;
    if ($start < 0) $start = 0;

    if (!tableExists($conn, 'LogDashboardKantin')) {
        return [
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => []
        ];
    }

    $empNameColumn = detectEmpNameColumn($conn);
    $empNameExpr = $empNameColumn !== '' ? 'emp.[' . $empNameColumn . ']' : 'NULL';
    $empNameOrderExpr = $empNameColumn !== '' ? 'e.[' . $empNameColumn . ']' : "''";
    $namaExpr = "COALESCE(NULLIF(k.Nama, ''), NULLIF(" . $empNameExpr . ", ''), '-')";

    $whereSql = " WHERE k.Waktu >= ? AND k.Waktu < DATEADD(DAY, 1, ?)";
    $params = [$tanggalAwal . ' 00:00:00', $tanggalAkhir . ' 00:00:00'];

    if ($mesinFilter !== '') {
        if (ctype_digit($mesinFilter)) {
            $whereSql .= " AND k.MesinId = ?";
            $params[] = (int)$mesinFilter;
        } else {
            $whereSql .= " AND CAST(k.MesinId AS NVARCHAR(100)) = ?";
            $params[] = $mesinFilter;
        }
    }

    $baseFrom = " FROM dbo.LogDashboardKantin AS k
                  LEFT JOIN dbo.m_fingerprint AS f ON k.MesinId = f.id
                  OUTER APPLY (
                    SELECT TOP 1 e.*
                    FROM dbo.m_emp AS e
                    WHERE CAST(k.UserId AS NVARCHAR(100)) = CAST(e.id_emp AS NVARCHAR(100))
                       OR CAST(k.UserId AS NVARCHAR(100)) = CAST(e.nik AS NVARCHAR(100))
                       OR CAST(k.UserId AS NVARCHAR(100)) = CAST(e.finger_key AS NVARCHAR(100))
                    ORDER BY
                        CASE
                            WHEN LTRIM(RTRIM(COALESCE(NULLIF(k.Nama, ''), ''))) <> ''
                                 AND LOWER(LTRIM(RTRIM(COALESCE(" . $empNameOrderExpr . ", ''))))
                                     = LOWER(LTRIM(RTRIM(COALESCE(k.Nama, '')))) THEN 0
                            WHEN CAST(k.UserId AS NVARCHAR(100)) = CAST(e.finger_key AS NVARCHAR(100)) THEN 1
                            WHEN CAST(k.UserId AS NVARCHAR(100)) = CAST(e.id_emp AS NVARCHAR(100)) THEN 2
                            WHEN CAST(k.UserId AS NVARCHAR(100)) = CAST(e.nik AS NVARCHAR(100)) THEN 3
                            ELSE 99
                        END,
                        ISNULL(e.aktif, 0) DESC,
                        e.id_emp DESC
                  ) AS emp
                  LEFT JOIN dbo.m_dept AS d ON emp.id_dept = d.id_dept
                  LEFT JOIN dbo.m_bag AS b ON emp.id_bag = b.id_bag";

    $totalRecords = queryCount($conn, "SELECT COUNT(*) AS total {$baseFrom} {$whereSql}", $params);

    $whereSearch = $whereSql;
    $searchParams = $params;
    if ($search !== '') {
        $whereSearch .= " AND (
            " . $namaExpr . " LIKE ? OR
            d.dept LIKE ? OR
            b.bagian LIKE ? OR
            f.nama_mesin LIKE ? OR
            CONVERT(VARCHAR(10), CAST(k.Waktu AS DATE), 105) LIKE ? OR
            CONVERT(VARCHAR(8), CAST(k.Waktu AS TIME), 108) LIKE ?
        )";
        $term = '%' . $search . '%';
        $searchParams[] = $term;
        $searchParams[] = $term;
        $searchParams[] = $term;
        $searchParams[] = $term;
        $searchParams[] = $term;
        $searchParams[] = $term;
    }

    $totalFiltered = $totalRecords;
    if ($search !== '') {
        $totalFiltered = queryCount($conn, "SELECT COUNT(*) AS total {$baseFrom} {$whereSearch}", $searchParams);
    }

    $sql = "SELECT
                k.Waktu AS waktu,
                " . $namaExpr . " AS nama_display,
                COALESCE(NULLIF(d.dept, ''), '-') AS dept,
                COALESCE(NULLIF(b.bagian, ''), '-') AS bagian,
                COALESCE(NULLIF(f.nama_mesin, ''), '-') AS nama_mesin,
                COUNT(*) OVER (
                    PARTITION BY CAST(k.Waktu AS DATE), CAST(k.UserId AS NVARCHAR(100))
                ) AS total_masuk_hari
            {$baseFrom}
            {$whereSearch}
            ORDER BY k.Waktu DESC, k.LogId DESC
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

    $dataParams = $searchParams;
    $dataParams[] = $start;
    $dataParams[] = $length;

    $stmt = sqlsrv_query($conn, $sql, $dataParams);
    $rows = [];

    if ($stmt) {
        $no = $start + 1;
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $dt = null;
            if (($row['waktu'] ?? null) instanceof DateTime) {
                $dt = clone $row['waktu'];
            } elseif (!empty($row['waktu'])) {
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', (string)$row['waktu']) ?: new DateTime((string)$row['waktu']);
            }
            if (!($dt instanceof DateTime)) {
                $dt = new DateTime();
            }
            $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));

            $cnt = (int)($row['total_masuk_hari'] ?? 1);
            $isDup = $cnt >= 2;
            $badgeClass = $isDup ? 'dk-badge-warn' : 'dk-badge-ok';
            $badge = '<span class="dk-badge ' . $badgeClass . '">' . $cnt . 'x</span>';

            $rows[] = [
                'no' => $no++,
                'tanggal' => $dt->format('d-m-Y'),
                'jam' => $dt->format('H:i:s'),
                'nama' => htmlspecialchars((string)($row['nama_display'] ?? '-'), ENT_QUOTES, 'UTF-8'),
                'dept' => htmlspecialchars((string)($row['dept'] ?? '-'), ENT_QUOTES, 'UTF-8'),
                'bagian' => htmlspecialchars((string)($row['bagian'] ?? '-'), ENT_QUOTES, 'UTF-8'),
                'mesin' => htmlspecialchars((string)($row['nama_mesin'] ?? '-'), ENT_QUOTES, 'UTF-8'),
                'jumlah_badge' => $badge,
                'jumlah_raw' => $cnt
            ];
        }
        sqlsrv_free_stmt($stmt);
    }

    return [
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $totalFiltered,
        'data' => $rows
    ];
}

function buildDateRangeKeys(string $startDate, string $endDate, int $maxDays = 31): array
{
    $start = DateTime::createFromFormat('Y-m-d', $startDate) ?: new DateTime(date('Y-m-d'));
    $end = DateTime::createFromFormat('Y-m-d', $endDate) ?: new DateTime(date('Y-m-d'));
    if ($start > $end) {
        $tmp = $start;
        $start = $end;
        $end = $tmp;
    }

    $totalDays = ((int)$start->diff($end)->format('%a')) + 1;
    $truncated = $totalDays > $maxDays;

    $dates = [];
    $cursor = clone $start;
    $count = 0;
    while ($cursor <= $end && $count < $maxDays) {
        $dates[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
        $count++;
    }

    return ['dates' => $dates, 'truncated' => $truncated];
}

function fetchMatrixMachines($conn, string $mesinFilter): array
{
    $params = [];
    $sql = "SELECT CAST(id AS NVARCHAR(100)) AS id, COALESCE(NULLIF(nama_mesin, ''), '-') AS nama_mesin
            FROM dbo.m_fingerprint";

    if ($mesinFilter !== '') {
        $sql .= " WHERE CAST(id AS NVARCHAR(100)) = ?";
        $params[] = $mesinFilter;
    }
    $sql .= " ORDER BY nama_mesin ASC";

    $rows = [];
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    return $rows;
}

function fetchSummaryMatrixHtml($conn, string $mesinFilter, string $tanggalAwal, string $tanggalAkhir): array
{
    $dateRange = buildDateRangeKeys($tanggalAwal, $tanggalAkhir, 31);
    $matrixDates = $dateRange['dates'];
    $warning = $dateRange['truncated'] ? 'Rentang tanggal lebih dari 31 hari. Tabel ditampilkan maksimal 31 hari pertama.' : '';

    $machines = fetchMatrixMachines($conn, $mesinFilter);
    $shiftCols = ['Shift Pagi', 'Shift Siang', 'Shift Malam', 'Non Shift', 'No All'];

    $matrixRows = [];
    foreach ($machines as $machine) {
        $cells = [];
        foreach ($matrixDates as $dateKey) {
            $shiftCounts = [];
            foreach ($shiftCols as $col) {
                $shiftCounts[$col] = 0;
            }
            $cells[$dateKey] = ['total' => 0, 'shift_counts' => $shiftCounts];
        }
        $matrixRows[(string)($machine['id'] ?? '')] = [
            'nama_mesin' => (string)($machine['nama_mesin'] ?? '-'),
            'cells' => $cells
        ];
    }

    $tableCols = getTableColumns($conn, 'LogDashboardKantin');
    $quickMarker = quickDuplicateAcceptMarker();
    $rejectReasonSelect = "CAST('' AS NVARCHAR(255)) AS reject_reason,";
    if (isset($tableCols['rejectreason'])) {
        $rejectReasonCol = $tableCols['rejectreason'];
        $rejectReasonSelect = "ISNULL(LTRIM(RTRIM(CAST(k.[$rejectReasonCol] AS NVARCHAR(255)))), '') AS reject_reason,";
    }

    // Samakan dengan aturan resolveShiftByTime: batas awal inklusif, batas akhir eksklusif.
    $shiftCaseSql = "CASE
        WHEN CAST(k.Waktu AS TIME) >= '11:30:00' AND CAST(k.Waktu AS TIME) < '13:30:00' THEN 'Non Shift'
        WHEN CAST(k.Waktu AS TIME) >= '01:00:00' AND CAST(k.Waktu AS TIME) < '04:00:00' THEN 'Shift Malam'
        WHEN CAST(k.Waktu AS TIME) >= '09:00:00' AND CAST(k.Waktu AS TIME) < '11:30:00' THEN 'Shift Pagi'
        WHEN CAST(k.Waktu AS TIME) >= '17:00:00' AND CAST(k.Waktu AS TIME) < '20:00:00' THEN 'Shift Siang'
        WHEN (CAST(k.Waktu AS TIME) >= '04:00:00' AND CAST(k.Waktu AS TIME) < '09:00:00')
          OR (CAST(k.Waktu AS TIME) >= '14:00:00' AND CAST(k.Waktu AS TIME) < '17:00:00')
          OR (CAST(k.Waktu AS TIME) >= '20:00:00' OR CAST(k.Waktu AS TIME) < '01:00:00') THEN 'No All'
        ELSE 'Unknown' END";

    $sql = "WITH base AS (
                SELECT
                    CAST(k.MesinId AS NVARCHAR(100)) AS mesin_id,
                    CAST(k.Waktu AS DATE) AS tanggal,
                    CASE WHEN ISNULL(k.IsRejected, 0) = 1 THEN 1 ELSE 0 END AS is_violation,
                    $rejectReasonSelect
                    {$shiftCaseSql} AS shift_name
                FROM dbo.LogDashboardKantin AS k
                WHERE CAST(k.Waktu AS DATE) BETWEEN ? AND ?";
    $params = [$tanggalAwal, $tanggalAkhir];
    if ($mesinFilter !== '') {
        $sql .= " AND CAST(k.MesinId AS NVARCHAR(100)) = ?";
        $params[] = $mesinFilter;
    }
    $sql .= "),
            scored AS (
                SELECT
                    b.mesin_id,
                    b.tanggal,
                    b.shift_name,
                    CASE
                        WHEN b.is_violation = 0
                             AND b.reject_reason <> ?
                            THEN 1
                        ELSE 0
                    END AS is_success_countable
                FROM base AS b
            )
            SELECT
                mesin_id,
                tanggal,
                shift_name,
                ISNULL(SUM(is_success_countable), 0) AS total
            FROM scored
            GROUP BY mesin_id, tanggal, shift_name";
    $params[] = $quickMarker;

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $mesinId = (string)($row['mesin_id'] ?? '');
            if ($mesinId === '' || !isset($matrixRows[$mesinId])) continue;

            $tanggalValue = $row['tanggal'] ?? null;
            $dateKey = ($tanggalValue instanceof DateTime) ? $tanggalValue->format('Y-m-d') : (strtotime((string)$tanggalValue) ? date('Y-m-d', strtotime((string)$tanggalValue)) : '');
            if ($dateKey === '' || !in_array($dateKey, $matrixDates, true)) continue;

            $shiftName = trim((string)($row['shift_name'] ?? ''));
            $total = (int)($row['total'] ?? 0);

            $matrixRows[$mesinId]['cells'][$dateKey]['total'] += $total;
            if (isset($matrixRows[$mesinId]['cells'][$dateKey]['shift_counts'][$shiftName])) {
                $matrixRows[$mesinId]['cells'][$dateKey]['shift_counts'][$shiftName] += $total;
            }
        }
        sqlsrv_free_stmt($stmt);
    }

    ob_start();
    if ($warning !== '') {
        echo '<div class="alert alert-warning py-2 px-3 mb-2">' . htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo '<div class="table-responsive dk-summary-matrix-wrap">';
    echo '<table class="table table-sm table-bordered table-hover mb-0 dk-summary-matrix-table">';
    echo '<thead>';
    echo '<tr class="text-center"><th rowspan="2" class="align-middle dk-sticky-col">Mesin</th>';
    foreach ($matrixDates as $dateKey) {
        echo '<th colspan="6">' . htmlspecialchars(date('d/m/Y', strtotime($dateKey)), ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr>';
    echo '<tr class="text-center">';
    foreach ($matrixDates as $_dateKey) {
        echo '<th>Shift Pagi</th><th>Shift Siang</th><th>Shift Malam</th><th>Non Shift</th><th>No All</th><th>Total Absensi</th>';
    }
    echo '</tr>';
    echo '</thead><tbody>';

    if (empty($matrixRows) || empty($matrixDates)) {
        $colspan = max(1, (count($matrixDates) * 6) + 1);
        echo '<tr><td colspan="' . $colspan . '" class="text-center text-muted">Tidak ada data absen berhasil untuk ditampilkan.</td></tr>';
    } else {
        foreach ($matrixRows as $row) {
            echo '<tr>';
            echo '<td class="dk-sticky-col">' . htmlspecialchars((string)($row['nama_mesin'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</td>';
            foreach ($matrixDates as $dateKey) {
                $cell = $row['cells'][$dateKey] ?? ['total' => 0, 'shift_counts' => []];
                $shiftCounts = is_array($cell['shift_counts'] ?? null) ? $cell['shift_counts'] : [];
                $totalCell = (int)($cell['total'] ?? 0);
                $vals = [
                    (int)($shiftCounts['Shift Pagi'] ?? 0),
                    (int)($shiftCounts['Shift Siang'] ?? 0),
                    (int)($shiftCounts['Shift Malam'] ?? 0),
                    (int)($shiftCounts['Non Shift'] ?? 0),
                    (int)($shiftCounts['No All'] ?? 0),
                    $totalCell
                ];
                foreach ($vals as $idx => $v) {
                    $cls = ($idx === 5) ? ' class="font-weight-bold text-primary text-center"' : ' class="text-center"';
                    echo '<td' . $cls . '>' . ($v > 0 ? (string)$v : '') . '</td>';
                }
            }
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';

    return ['html' => (string)ob_get_clean(), 'warning' => $warning];
}

function fetchMachineSuccessCards($conn, string $mesinFilter, string $tanggalAwal, string $tanggalAkhir): array
{
    $cards = [
        'kantin_depan' => 0,
        'kantin_produksi' => 0,
        'kantin_weaving_1' => 0,
        'kantin_weaving_2' => 0
    ];

    $nameMap = [
        'kantin depan' => 'kantin_depan',
        'kantin produksi' => 'kantin_produksi',
        'kantin weaving 1' => 'kantin_weaving_1',
        'kantin weaving 2' => 'kantin_weaving_2'
    ];

    $tableCols = getTableColumns($conn, 'LogDashboardKantin');
    $quickMarker = quickDuplicateAcceptMarker();
    $rejectReasonSelect = "CAST('' AS NVARCHAR(255)) AS reject_reason,";
    if (isset($tableCols['rejectreason'])) {
        $rejectReasonCol = $tableCols['rejectreason'];
        $rejectReasonSelect = "ISNULL(LTRIM(RTRIM(CAST(k.[$rejectReasonCol] AS NVARCHAR(255)))), '') AS reject_reason,";
    }

    $sql = "WITH base AS (
                SELECT
                    LOWER(LTRIM(RTRIM(COALESCE(f.nama_mesin, '')))) AS mesin_name,
                    CASE WHEN ISNULL(k.IsRejected, 0) = 1 THEN 1 ELSE 0 END AS is_violation,
                    $rejectReasonSelect
                FROM dbo.LogDashboardKantin AS k
                LEFT JOIN dbo.m_fingerprint AS f ON k.MesinId = f.id
                WHERE CAST(k.Waktu AS DATE) BETWEEN ? AND ?";
    $params = [$tanggalAwal, $tanggalAkhir];
    if ($mesinFilter !== '') {
        $sql .= " AND CAST(k.MesinId AS NVARCHAR(100)) = ?";
        $params[] = $mesinFilter;
    }
    $sql .= "),
            scored AS (
                SELECT
                    b.mesin_name,
                    CASE
                        WHEN b.is_violation = 0
                             AND b.reject_reason <> ?
                            THEN 1
                        ELSE 0
                    END AS is_success_countable
                FROM base AS b
            )
            SELECT
                mesin_name,
                ISNULL(SUM(is_success_countable), 0) AS total
            FROM scored
            GROUP BY mesin_name";
    $params[] = $quickMarker;

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $nameKey = trim((string)($row['mesin_name'] ?? ''));
            if ($nameKey === '' || !isset($nameMap[$nameKey])) continue;
            $cards[$nameMap[$nameKey]] = (int)($row['total'] ?? 0);
        }
        sqlsrv_free_stmt($stmt);
    }

    return $cards;
}

function canGroupViewDashboardKantin($conn, $groupId): bool
{
    if ($groupId === null || $groupId === '' || !is_numeric((string)$groupId)) {
        return false;
    }

    $sql = "SELECT TOP 1 1 AS ok
            FROM dbo.SMGroupTrustee AS ut
            LEFT JOIN dbo.SMMenu AS m ON ut.MenuId = m.MenuId
            OUTER APPLY (
                SELECT LOWER(REPLACE(LTRIM(RTRIM(ISNULL(m.MenuUrl, ''))), '\\', '/')) AS menu_url_norm
            ) AS x
            WHERE ut.GroupId = ?
              AND ISNULL(ut.CanView, 0) = 1
              AND (
                    ut.MenuId = 126
                 OR x.menu_url_norm IN (
                        '/gg_app/pages/absen/dashboard_kantin.php',
                        '/pages/absen/dashboard_kantin.php',
                        'pages/absen/dashboard_kantin.php',
                        'dashboard_kantin.php'
                    )
                 OR x.menu_url_norm LIKE '%/dashboard_kantin.php'
                 OR x.menu_url_norm LIKE '%dashboard_kantin.php'
              )";

    $stmt = sqlsrv_query($conn, $sql, [(int)$groupId]);
    if (!$stmt) {
        return false;
    }
    $ok = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) ? true : false;
    sqlsrv_free_stmt($stmt);
    return $ok;
}

if (!$conn) {
    jsonOut(['ok' => false, 'message' => 'Koneksi database gagal.'], 500);
}

$action = isset($_GET['action']) ? strtolower(trim((string)$_GET['action'])) : 'table';
$kioskMode = isset($_GET['kiosk_mode']) && (string)$_GET['kiosk_mode'] === '1';
$kioskToken = trim((string)($_GET['kiosk_token'] ?? ''));
$kioskAuthorized = $kioskMode && $kioskToken !== '' && hash_equals(getKioskAccessToken(), $kioskToken);
$kioskAllowedActions = ['latest', 'summary_matrix', 'machine_success_cards', 'table'];

if (!isset($_SESSION['UserName'])) {
    if (!$kioskAuthorized) {
        jsonOut(['ok' => false, 'message' => 'Session expired. Silakan login kembali.'], 401);
    }
    if (!in_array($action, $kioskAllowedActions, true)) {
        jsonOut(['ok' => false, 'message' => 'Aksi tidak diizinkan pada mode kiosk.'], 403);
    }
} else {
    $groupId = $_SESSION['GroupId'] ?? null;
    if (!$groupId) {
        jsonOut(['ok' => false, 'message' => 'GroupId tidak ditemukan dalam session.'], 403);
    }

    if (!canGroupViewDashboardKantin($conn, $groupId)) {
        jsonOut(['ok' => false, 'message' => 'Anda tidak memiliki akses untuk melihat data ini.'], 403);
    }
}

if ($kioskAuthorized && !in_array($action, $kioskAllowedActions, true)) {
    jsonOut(['ok' => false, 'message' => 'Aksi tidak diizinkan pada mode kiosk.'], 403);
}

// Lepas lock session lebih awal agar request AJAX paralel (DataTable + polling) tidak saling menunggu.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$mesinFilter = isset($_GET['mesin_id']) ? trim((string)$_GET['mesin_id']) : '';
if ($mesinFilter === '' && isset($_GET['mesin'])) {
    $mesinFilter = trim((string)$_GET['mesin']);
}

$tanggalAwal = isset($_GET['tanggal_awal']) && $_GET['tanggal_awal'] !== '' ? (string)$_GET['tanggal_awal'] : date('Y-m-d');
$tanggalAkhir = isset($_GET['tanggal_akhir']) && $_GET['tanggal_akhir'] !== '' ? (string)$_GET['tanggal_akhir'] : date('Y-m-d');
[$tanggalAwal, $tanggalAkhir] = normalizeDateRange($tanggalAwal, $tanggalAkhir);

if ($action === 'summary_matrix') {
    $matrix = fetchSummaryMatrixHtml($conn, $mesinFilter, $tanggalAwal, $tanggalAkhir);
    jsonOut([
        'ok' => true,
        'html' => $matrix['html'] ?? ''
    ]);
}

if ($action === 'machine_success_cards') {
    jsonOut([
        'ok' => true,
        'cards' => fetchMachineSuccessCards($conn, $mesinFilter, $tanggalAwal, $tanggalAkhir)
    ]);
}

if ($action === 'latest') {
    $syncStats = syncRealtimeLogs($conn, $mesinFilter);
    $event = fetchLatestAttendance($conn, $mesinFilter, $tanggalAwal, $tanggalAkhir);
    $employeeHistory = [];
    if (is_array($event) && !empty($event)) {
        $employeeHistory = fetchEmployeeAttendanceHistory(
            $conn,
            $mesinFilter,
            $tanggalAwal,
            $tanggalAkhir,
            (string)($event['user_id'] ?? ''),
            (string)($event['nama'] ?? ''),
            8
        );
    }

    jsonOut([
        'ok' => true,
        'server_time' => date('Y-m-d H:i:s'),
        'sync' => $syncStats,
        'kiosk_stats' => fetchKioskStats($conn, $mesinFilter, $tanggalAwal, $tanggalAkhir),
        'event' => $event,
        'recent_history' => fetchRecentAttendanceHistory($conn, $mesinFilter, $tanggalAwal, $tanggalAkhir, 7),
        'employee_history' => $employeeHistory
    ]);
}

$draw = isset($_GET['draw']) ? (int)$_GET['draw'] : 1;
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$length = isset($_GET['length']) ? (int)$_GET['length'] : 25;
$search = isset($_GET['search']['value']) ? trim((string)$_GET['search']['value']) : '';

$tableResponse = fetchTableData($conn, $mesinFilter, $tanggalAwal, $tanggalAkhir, $draw, $start, $length, $search);
jsonOut($tableResponse);
