<?php
// pages/ai_v2/db.php
require_once __DIR__ . '/config.php';

function ai_db() {
    static $conn = null;
    if ($conn !== null) return $conn;

    $conn = sqlsrv_connect(AI_DB_SERVER, [
        'Database' => AI_DB_NAME,
        'Uid' => AI_DB_USER,
        'PWD' => AI_DB_PASS,
        'TrustServerCertificate' => true,
        'CharacterSet' => 'UTF-8',
    ]);

    if (!$conn) {
        throw new Exception('DB connection failed: ' . print_r(sqlsrv_errors(), true));
    }
    return $conn;
}

function ai_db_query($sql, $params = []) {
    $stmt = sqlsrv_query(ai_db(), $sql, $params);
    if ($stmt === false) {
        throw new Exception('DB query failed: ' . print_r(sqlsrv_errors(), true));
    }
    return $stmt;
}

function ai_db_fetch_all($stmt) {
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

function ai_db_table_columns($tableName) {
    static $cache = [];
    if (isset($cache[$tableName])) return $cache[$tableName];

    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?";
    $parts = explode('.', $tableName, 2);
    if (count($parts) === 2) {
        $schema = $parts[0];
        $table = $parts[1];
    } else {
        $schema = 'dbo';
        $table = $tableName;
    }

    $stmt = ai_db_query($sql, [$table, $schema]);
    $cols = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $cols[strtolower($row['COLUMN_NAME'])] = true;
    }
    $cache[$tableName] = $cols;
    return $cols;
}

function ai_db_has_col($tableName, $colName) {
    $cols = ai_db_table_columns($tableName);
    return isset($cols[strtolower($colName)]);
}

function ai_db_table_exists($tableName) {
    static $cache = [];
    if (isset($cache[$tableName])) return $cache[$tableName];

    $parts = explode('.', $tableName, 2);
    if (count($parts) === 2) {
        $schema = $parts[0];
        $table = $parts[1];
    } else {
        $schema = 'dbo';
        $table = $tableName;
    }

    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
    $stmt = ai_db_query($sql, [$schema, $table]);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $cache[$tableName] = $row ? true : false;
    return $cache[$tableName];
}

function ai_db_insert($tableName, $data) {
    $cols = ai_db_table_columns($tableName);
    $fields = [];
    $params = [];
    foreach ($data as $k => $v) {
        if (isset($cols[strtolower($k)])) {
            $fields[] = "[$k]";
            if (is_string($v)) {
                $v = ai_utf8_clean($v);
                $params[] = [
                    $v,
                    SQLSRV_PARAM_IN,
                    SQLSRV_PHPTYPE_STRING('UTF-8'),
                    SQLSRV_SQLTYPE_NVARCHAR('max'),
                ];
            } else {
                $params[] = $v;
            }
        }
    }

    if (empty($fields)) {
        throw new Exception("No valid columns for insert into {$tableName}");
    }

    $placeholders = implode(',', array_fill(0, count($fields), '?'));
    $sql = "INSERT INTO {$tableName} (" . implode(',', $fields) . ") VALUES ({$placeholders})";
    ai_db_query($sql, $params);
}

function ai_db_insert_returning($tableName, $data, $returnCol) {
    $cols = ai_db_table_columns($tableName);
    $fields = [];
    $params = [];
    foreach ($data as $k => $v) {
        if (isset($cols[strtolower($k)])) {
            $fields[] = "[$k]";
            if (is_string($v)) {
                $v = ai_utf8_clean($v);
                $params[] = [
                    $v,
                    SQLSRV_PARAM_IN,
                    SQLSRV_PHPTYPE_STRING('UTF-8'),
                    SQLSRV_SQLTYPE_NVARCHAR('max'),
                ];
            } else {
                $params[] = $v;
            }
        }
    }

    if (empty($fields)) {
        throw new Exception("No valid columns for insert into {$tableName}");
    }
    if (!ai_db_has_col($tableName, $returnCol)) {
        throw new Exception("Return column {$returnCol} not found in {$tableName}");
    }

    $placeholders = implode(',', array_fill(0, count($fields), '?'));
    $sql = "INSERT INTO {$tableName} (" . implode(',', $fields) . ") OUTPUT INSERTED.[{$returnCol}] AS id VALUES ({$placeholders})";
    $stmt = ai_db_query($sql, $params);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $row ? $row['id'] : null;
}

function ai_db_last_id() {
    $stmt = ai_db_query('SELECT SCOPE_IDENTITY() AS id');
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

function ai_db_allowed_role_values($tableName, $colName) {
    static $cache = [];
    $key = strtolower($tableName . '|' . $colName);
    if (isset($cache[$key])) return $cache[$key];

    $parts = explode('.', $tableName, 2);
    if (count($parts) === 2) {
        $schema = $parts[0];
        $table = $parts[1];
    } else {
        $schema = 'dbo';
        $table = $tableName;
    }

    $sql = "
        SELECT cc.definition
        FROM sys.check_constraints cc
        JOIN sys.columns col
          ON cc.parent_object_id = col.object_id
         AND cc.parent_column_id = col.column_id
        JOIN sys.tables t ON t.object_id = col.object_id
        JOIN sys.schemas s ON t.schema_id = s.schema_id
        WHERE t.name = ? AND s.name = ? AND col.name = ?
    ";
    try {
        $stmt = ai_db_query($sql, [$table, $schema, $colName]);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if (!$row || empty($row['definition'])) {
            $cache[$key] = [];
            return $cache[$key];
        }
        $def = $row['definition'];
    } catch (Exception $e) {
        error_log('ai_db_allowed_role_values failed: ' . $e->getMessage());
        $cache[$key] = [];
        return $cache[$key];
    }
    $values = [];
    if (preg_match_all("/'([^']+)'/", $def, $m)) {
        foreach ($m[1] as $v) {
            $values[] = $v;
        }
    }

    $values = array_values(array_unique($values));
    $cache[$key] = $values;
    return $values;
}

function ai_role_to_db($role, $tableName, $colName) {
    $role = strtolower(trim((string)$role));
    $allowed = ai_db_allowed_role_values($tableName, $colName);
    if (empty($allowed)) return $role;

    foreach ($allowed as $a) {
        if (strtolower($a) === $role) return $a;
    }

    $userCandidates = ['user', 'u', 'human', 'request', 'question'];
    $assistantCandidates = ['assistant', 'ai', 'bot', 'system', 'answer', 'response'];

    $targets = in_array($role, $userCandidates, true) ? $userCandidates : $assistantCandidates;
    foreach ($allowed as $a) {
        if (in_array(strtolower($a), $targets, true)) return $a;
    }

    return $allowed[0];
}

function ai_utf8_clean($text) {
    if ($text === '') return $text;
    if (preg_match('//u', $text)) {
        return $text;
    }
    if (function_exists('mb_detect_encoding')) {
        $enc = mb_detect_encoding($text, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
        if ($enc && $enc !== 'UTF-8') {
            $converted = @mb_convert_encoding($text, 'UTF-8', $enc);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }
    }
    $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
    return is_string($converted) ? $converted : '';
}

function ai_session_user_id() {
    return $_SESSION['UserId'] ?? null;
}
