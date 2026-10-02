<?php
// pages/ai_v2/sync.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = ai_session_user_id();
if (!$userId) {
    header('Location: /gg_app/login.php');
    exit;
}

function ai_normalize_text($text) {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace("/[ \t]+/", " ", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

function ai_chunk_text($text, $maxLen, $overlap) {
    $words = preg_split('/\s+/', $text);
    $chunks = [];
    $chunk = '';

    foreach ($words as $w) {
        if ($chunk === '') {
            $chunk = $w;
            continue;
        }
        if ((strlen($chunk) + 1 + strlen($w)) <= $maxLen) {
            $chunk .= ' ' . $w;
        } else {
            $chunks[] = trim($chunk);
            $tail = substr($chunk, max(0, strlen($chunk) - $overlap));
            $chunk = trim($tail . ' ' . $w);
        }
    }
    if (trim($chunk) !== '') {
        $chunks[] = trim($chunk);
    }
    return $chunks;
}

function ai_value_to_text($value) {
    if ($value === null) return '';
    if ($value instanceof DateTime) return $value->format('Y-m-d H:i:s');
    if (is_bool($value)) return $value ? '1' : '0';
    $text = (string)$value;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    return $text;
}

function ai_token_count_approx($text) {
    $parts = preg_split('/\s+/u', trim($text));
    if (!$parts || $parts[0] === '') return 0;
    return count($parts);
}

$message = '';
$error = '';
$logs = [];

function ai_log_line(&$logs, $line) {
    $logs[] = $line;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        @set_time_limit(0);
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', 'off');

        $conn = ai_db();

        global $AI_TABLES, $AI_COLS;
        $docTable = $AI_TABLES['documents'];
        $docCols = $AI_COLS['documents'];
        $chunkTable = $AI_TABLES['chunks'];
        $chunkCols = $AI_COLS['chunks'];

        $docSourceCol = $docCols['source_type'] ?? null;
        $action = $_POST['action'] ?? 'sync';
        if ($action === 'clear') {
            if ($docSourceCol && ai_db_has_col($docTable, $docSourceCol)) {
                ai_log_line($logs, 'Menghapus semua data hasil sync (DB_SYNC)...');
                ai_db_query("DELETE FROM {$docTable} WHERE [{$docSourceCol}] = ?", ['DB_SYNC']);
                $message = 'Semua data hasil sync berhasil dihapus.';
            } else {
                $error = 'Kolom source_type tidak ditemukan.';
            }
            // stop here for clear action
            throw new Exception('__SYNC_DONE__');
        }

        if ($docSourceCol && ai_db_has_col($docTable, $docSourceCol)) {
            ai_log_line($logs, 'Menghapus dokumen sync sebelumnya...');
            ai_db_query("DELETE FROM {$docTable} WHERE [{$docSourceCol}] = ?", ['DB_SYNC']);
        }

        $skipTables = [
            'ai_documents',
            'ai_document_chunks',
            'ai_chat_history',
            'ai_request_log',
        ];

        $tablesStmt = ai_db_query("
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = 'dbo' AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        ");

        $tables = ai_db_fetch_all($tablesStmt);
        $totalDocs = 0;
        $totalChunks = 0;
        $totalRows = 0;

        foreach ($tables as $trow) {
            $tableName = $trow['TABLE_NAME'];
            if (in_array(strtolower($tableName), $skipTables, true)) {
                continue;
            }

            $colsStmt = ai_db_query("
                SELECT COLUMN_NAME, DATA_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION
            ", [$tableName]);
            $cols = [];
            while ($c = sqlsrv_fetch_array($colsStmt, SQLSRV_FETCH_ASSOC)) {
                $colName = $c['COLUMN_NAME'];
                if (stripos($colName, 'password') !== false) {
                    continue;
                }
                $cols[] = $colName;
            }

            if (empty($cols)) {
                ai_log_line($logs, "Skip {$tableName} (kolom kosong setelah filter).");
                continue;
            }

            ai_log_line($logs, "Sync tabel: {$tableName}");

            $selectCols = implode(', ', array_map(function ($c) {
                return '[' . $c . ']';
            }, $cols));
            $sql = "SELECT {$selectCols} FROM dbo.{$tableName} WITH (NOLOCK)";
            $stmt = ai_db_query($sql);

            $batchRows = 0;
            $batchIdx = 1;
            $docText = '';

            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $totalRows++;
                $batchRows++;

                $lines = [];
                foreach ($cols as $c) {
                    $val = ai_value_to_text($row[$c] ?? null);
                    if (strlen($val) > 1200) {
                        $val = substr($val, 0, 1200) . '…';
                    }
                    $lines[] = $c . ': ' . $val;
                }

                $rowText = "Row {$totalRows}\n" . implode("\n", $lines) . "\n";
                $rowText = ai_normalize_text($rowText) . "\n\n";

                $maxDocChars = 30000;
                $maxRowsPerDoc = 200;

                if ((strlen($docText) + strlen($rowText)) > $maxDocChars || $batchRows > $maxRowsPerDoc) {
                    if (trim($docText) !== '') {
                        $title = "DB Sync: {$tableName} (batch {$batchIdx})";
                        $docId = ai_db_insert_returning($docTable, [
                            $docCols['title'] => $title,
                            $docCols['description'] => $docText,
                            $docCols['source_type'] => 'DB_SYNC',
                            $docCols['created_by'] => (string)$userId,
                            $docCols['created_at'] => date('Y-m-d H:i:s'),
                            $docCols['is_active'] => 1,
                        ], $docCols['id']);

                        if ($docId) {
                            $totalDocs++;
                            $chunks = ai_chunk_text($docText, AI_CHUNK_SIZE, AI_CHUNK_OVERLAP);
                            $chunkIndex = 0;
                            foreach ($chunks as $chunkText) {
                                $chunkIndex++;
                                $data = [
                                    $chunkCols['doc_id'] => $docId,
                                    $chunkCols['chunk_idx'] => $chunkIndex,
                                    $chunkCols['chunk_text'] => $chunkText,
                                    $chunkCols['created_at'] => date('Y-m-d H:i:s'),
                                ];
                                if (ai_db_has_col($chunkTable, 'token_count')) {
                                    $data['token_count'] = ai_token_count_approx($chunkText);
                                }
                                ai_db_insert($chunkTable, $data);
                                $totalChunks++;
                            }
                        }
                    }

                    $docText = '';
                    $batchRows = 1;
                    $batchIdx++;
                }

                $docText .= $rowText;
            }

            if (trim($docText) !== '') {
                $title = "DB Sync: {$tableName} (batch {$batchIdx})";
                $docId = ai_db_insert_returning($docTable, [
                    $docCols['title'] => $title,
                    $docCols['description'] => $docText,
                    $docCols['source_type'] => 'DB_SYNC',
                    $docCols['created_by'] => (string)$userId,
                    $docCols['created_at'] => date('Y-m-d H:i:s'),
                    $docCols['is_active'] => 1,
                ], $docCols['id']);

                if ($docId) {
                    $totalDocs++;
                    $chunks = ai_chunk_text($docText, AI_CHUNK_SIZE, AI_CHUNK_OVERLAP);
                    $chunkIndex = 0;
                    foreach ($chunks as $chunkText) {
                        $chunkIndex++;
                        $data = [
                            $chunkCols['doc_id'] => $docId,
                            $chunkCols['chunk_idx'] => $chunkIndex,
                            $chunkCols['chunk_text'] => $chunkText,
                            $chunkCols['created_at'] => date('Y-m-d H:i:s'),
                        ];
                        if (ai_db_has_col($chunkTable, 'token_count')) {
                            $data['token_count'] = ai_token_count_approx($chunkText);
                        }
                        ai_db_insert($chunkTable, $data);
                        $totalChunks++;
                    }
                }
            }
        }

        $message = "Sync selesai. Dokumen: {$totalDocs}, Chunk: {$totalChunks}, Row: {$totalRows}";
    } catch (Exception $e) {
        if ($e->getMessage() !== '__SYNC_DONE__') {
            $error = $e->getMessage();
        }
    }
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0">Sync Database ke AI</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="upload.php" class="btn btn-sm btn-secondary">Kembali</a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="post" class="mb-2">
                        <p>Sync ini akan membaca semua tabel di database <code>GG</code> (schema <code>dbo</code>) dan memasukkan ke knowledge AI. Kolom berisi kata <code>password</code> akan diabaikan.</p>
                        <input type="hidden" name="action" value="sync">
                        <button type="submit" class="btn btn-primary">Mulai Sync</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Hapus semua data hasil sync (DB_SYNC)?');">
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" class="btn btn-outline-danger">Hapus Data Sync</button>
                    </form>

                    <?php if (!empty($logs)): ?>
                        <hr>
                        <div class="small text-muted">
                            <?php foreach ($logs as $line): ?>
                                <div><?= htmlspecialchars($line) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
