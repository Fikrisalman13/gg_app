<?php
// pages/ai_v2/ask.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = ai_session_user_id();
if (!$userId) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$question = trim($data['question'] ?? '');
if ($question === '') {
    http_response_code(400);
    echo 'Pertanyaan kosong';
    exit;
}

global $AI_AVAILABLE_MODELS;
$model = $data['model'] ?? AI_DEFAULT_MODEL;
if (!isset($AI_AVAILABLE_MODELS[$model])) {
    $model = AI_DEFAULT_MODEL;
}

function ai_extract_terms($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\\x{00C0}-\\x{017F}\\s]+/u', ' ', $text);
    $parts = preg_split('/\\s+/', $text);
    $stop = array_flip([
        'yang','dan','atau','di','ke','dari','pada','untuk','dengan','tanpa','agar','jika','bila','saat','ketika',
        'apa','siapa','kapan','dimana','bagaimana','kenapa','berapa','cara','ini','itu','tersebut','nya','saya','kami','kita',
        'the','a','an','of','to','in','on','for','with','is','are','was','were','be','by','as','at','from'
    ]);
    $shortAllow = array_flip([
        'ac','it','hr','ga','po','qa','qc','pc','ip','ap','db','ui','ux','pp','pr'
    ]);
    $terms = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '' || isset($stop[$p])) continue;
        if (ctype_digit($p)) {
            if (strlen($p) < 2) continue;
        } else {
            $len = strlen($p);
            if ($len < 3 && !isset($shortAllow[$p])) continue;
        }
        $terms[$p] = true;
        if (count($terms) >= 8) break;
    }
    return array_keys($terms);
}

function ai_expand_terms($terms) {
    $expanded = [];
    foreach ($terms as $t) {
        $t = trim((string)$t);
        if ($t === '') continue;
        $expanded[$t] = true;
        // Toleransi typo umum: "penecekan" -> "pengecekan"
        if (strpos($t, 'pene') === 0 && strpos($t, 'penge') !== 0) {
            $expanded['penge' . substr($t, 4)] = true;
        }
    }
    return array_keys($expanded);
}

function ai_like_escape($text) {
    $text = str_replace('[', '[[]', $text);
    $text = str_replace('%', '[%]', $text);
    $text = str_replace('_', '[_]', $text);
    return $text;
}

function ai_save_history($userId, $role, $message) {
    global $AI_TABLES, $AI_COLS;
    $table = $AI_TABLES['history'];
    $cols = $AI_COLS['history'];
    try {
        $roleDb = ai_role_to_db($role, $table, $cols['role']);
        ai_db_insert($table, [
            $cols['user_id'] => $userId,
            $cols['role'] => $roleDb,
            $cols['message'] => $message,
            $cols['created_at'] => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        error_log('ai_save_history failed: ' . $e->getMessage());
    }
}

function ai_log_request($userId, $question, $response, $model, $context, $latencyMs, $status) {
    global $AI_TABLES, $AI_COLS;
    $table = $AI_TABLES['request_log'];
    $cols = $AI_COLS['request_log'];
    try {
        ai_db_insert($table, [
            $cols['user_id'] => $userId,
            $cols['question'] => $question,
            $cols['model'] => $model,
            $cols['latency_ms'] => $latencyMs,
            $cols['created_at'] => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        error_log('ai_log_request failed: ' . $e->getMessage());
    }
}

function ai_fetch_images_for_docs($docIds) {
    if (empty($docIds)) return [];
    global $AI_TABLES, $AI_COLS;
    $table = $AI_TABLES['images'] ?? null;
    if (!$table || !ai_db_table_exists($table)) return [];

    $cols = $AI_COLS['images'];
    $required = ['doc_id', 'image_path'];
    foreach ($required as $key) {
        if (!isset($cols[$key]) || !ai_db_has_col($table, $cols[$key])) {
            return [];
        }
    }

    $docIds = array_values($docIds);
    $placeholders = implode(',', array_fill(0, count($docIds), '?'));
    $topN = (int)AI_IMAGE_MAX_RETURN;
    $pageCol = $cols['page_no'] ?? null;
    $idxCol = $cols['image_index'] ?? null;
    $captionCol = $cols['caption'] ?? null;

    $select = [
        "[" . $cols['doc_id'] . "] AS document_id",
        "[" . $cols['image_path'] . "] AS image_path",
    ];
    if ($pageCol && ai_db_has_col($table, $pageCol)) {
        $select[] = "[$pageCol] AS page_no";
    } else {
        $select[] = "NULL AS page_no";
    }
    if ($idxCol && ai_db_has_col($table, $idxCol)) {
        $select[] = "[$idxCol] AS image_index";
    } else {
        $select[] = "NULL AS image_index";
    }
    if ($captionCol && ai_db_has_col($table, $captionCol)) {
        $select[] = "[$captionCol] AS caption";
    } else {
        $select[] = "NULL AS caption";
    }

    $orderBy = [];
    if ($pageCol && ai_db_has_col($table, $pageCol)) $orderBy[] = "[$pageCol] ASC";
    if ($idxCol && ai_db_has_col($table, $idxCol)) $orderBy[] = "[$idxCol] ASC";
    if (empty($orderBy)) $orderBy[] = "[" . $cols['doc_id'] . "] ASC";

    $sql = "SELECT TOP {$topN} " . implode(', ', $select) . " FROM {$table} WITH (NOLOCK) WHERE [" . $cols['doc_id'] . "] IN ({$placeholders}) ORDER BY " . implode(', ', $orderBy);
    return ai_db_fetch_all(ai_db_query($sql, $docIds));
}

function ai_find_doc_ids_by_title($question, $terms) {
    global $AI_TABLES, $AI_COLS;
    $docTable = $AI_TABLES['documents'];
    $docCols = $AI_COLS['documents'];
    $titleCol = $docCols['title'] ?? null;
    $idCol = $docCols['id'] ?? null;
    $activeCol = $docCols['is_active'] ?? null;

    if (!$titleCol || !$idCol) return [];
    if (!ai_db_has_col($docTable, $titleCol) || !ai_db_has_col($docTable, $idCol)) return [];

    $docIds = [];
    $question = trim((string)$question);

    if ($question !== '' && strlen($question) >= 6) {
        $where = "[$titleCol] LIKE ?";
        $params = ['%' . ai_like_escape($question) . '%'];
        if ($activeCol && ai_db_has_col($docTable, $activeCol)) {
            $where .= " AND [$activeCol] = 1";
        }
        $sql = "SELECT TOP 3 [$idCol] AS document_id FROM {$docTable} WITH (NOLOCK) WHERE {$where} ORDER BY LEN([$titleCol]) DESC";
        $rows = ai_db_fetch_all(ai_db_query($sql, $params));
        foreach ($rows as $r) {
            if (!empty($r['document_id'])) {
                $docIds[] = $r['document_id'];
            }
        }
        if (!empty($docIds)) {
            return array_values(array_unique($docIds));
        }
    }

    if (empty($terms)) return [];

    $scoreParts = [];
    $whereParts = [];
    $params = [];
    foreach ($terms as $t) {
        $like = '%' . ai_like_escape($t) . '%';
        $scoreParts[] = "CASE WHEN [$titleCol] LIKE ? THEN 1 ELSE 0 END";
        $params[] = $like;
        $whereParts[] = "[$titleCol] LIKE ?";
        $params[] = $like;
    }

    $minScore = count($terms) >= 2 ? 2 : 1;
    $whereSql = "(" . implode(' OR ', $whereParts) . ")";
    if ($activeCol && ai_db_has_col($docTable, $activeCol)) {
        $whereSql .= " AND [$activeCol] = 1";
    }

    $sql = "SELECT TOP 5 [$idCol] AS document_id, (" . implode(' + ', $scoreParts) . ") AS score
            FROM {$docTable} WITH (NOLOCK)
            WHERE {$whereSql}
            ORDER BY score DESC";
    $rows = ai_db_fetch_all(ai_db_query($sql, $params));
    foreach ($rows as $r) {
        if ((int)($r['score'] ?? 0) < $minScore) continue;
        if (!empty($r['document_id'])) {
            $docIds[] = $r['document_id'];
        }
    }
    return array_values(array_unique($docIds));
}

function ai_docs_are_scanned_pdfs($docIds) {
    if (empty($docIds)) return false;
    global $AI_TABLES, $AI_COLS;
    $docTable = $AI_TABLES['documents'];
    $docCols = $AI_COLS['documents'];
    $idCol = $docCols['id'] ?? null;
    $sourceCol = $docCols['source_type'] ?? null;
    $descCol = $docCols['description'] ?? null;
    if (!$idCol || !$sourceCol || !$descCol) return false;
    if (!ai_db_has_col($docTable, $idCol) || !ai_db_has_col($docTable, $sourceCol) || !ai_db_has_col($docTable, $descCol)) {
        return false;
    }

    $docIds = array_values($docIds);
    $placeholders = implode(',', array_fill(0, count($docIds), '?'));
    $sql = "SELECT [$idCol] AS document_id, [$sourceCol] AS source_type, [$descCol] AS description
            FROM {$docTable} WITH (NOLOCK)
            WHERE [$idCol] IN ({$placeholders})";
    $rows = ai_db_fetch_all(ai_db_query($sql, $docIds));
    if (empty($rows)) return false;

    foreach ($rows as $r) {
        $source = strtoupper(trim((string)($r['source_type'] ?? '')));
        $desc = trim((string)($r['description'] ?? ''));
        if ($source !== 'PDF') return false;
        if ($desc === '' || stripos($desc, 'PDF tanpa teks') === false) {
            return false;
        }
    }
    return true;
}

$terms = ai_expand_terms(ai_extract_terms($question));
global $AI_TABLES, $AI_COLS;
$docTable = $AI_TABLES['documents'];
$docCols = $AI_COLS['documents'];
$explicitDocId = (int)($data['document_id'] ?? 0);
$docFilterIds = [];
if ($explicitDocId > 0) {
    $docFilterIds = [(string)$explicitDocId];
} else {
    $docFilterIds = ai_find_doc_ids_by_title($question, $terms);
}

$chunkTable = $AI_TABLES['chunks'];
$chunkCols = $AI_COLS['chunks'];

$chunkTextCol = $chunkCols['chunk_text'];
$docIdCol = $chunkCols['doc_id'];
$chunkIdxCol = $chunkCols['chunk_idx'];
$docPkCol = $docCols['id'] ?? null;
$docActiveCol = $docCols['is_active'] ?? null;
$docSourceCol = $docCols['source_type'] ?? null;

if (!ai_db_has_col($chunkTable, $chunkTextCol)) {
    http_response_code(500);
    echo 'Kolom chunk_text tidak ditemukan.';
    exit;
}

if (empty($terms)) {
    $noData = 'Data tidak ditemukan dalam dokumen yang tersedia.';
    ai_save_history($userId, 'user', $question);
    ai_save_history($userId, 'assistant', $noData);
    ai_log_request($userId, $question, $noData, $model, '', 0, 'no_terms');
    echo $noData;
    exit;
}

$scoreParts = [];
$whereParts = [];
$params = [];
$useDocJoin = $docPkCol
    && ai_db_has_col($chunkTable, $docIdCol)
    && ai_db_has_col($docTable, $docPkCol);
$activeFilter = $useDocJoin && $docActiveCol && ai_db_has_col($docTable, $docActiveCol);
$sourceBoost = $useDocJoin && $docSourceCol && ai_db_has_col($docTable, $docSourceCol);

foreach ($terms as $t) {
    $like = '%' . ai_like_escape($t) . '%';
    $scoreParts[] = "CASE WHEN c.[$chunkTextCol] LIKE ? THEN 1 ELSE 0 END";
    $params[] = $like;
    $whereParts[] = "c.[$chunkTextCol] LIKE ?";
    $params[] = $like;
}

$topN = (int) (AI_CONTEXT_MAX_CHUNKS * 4);
$minScore = count($terms) >= 2 ? 2 : 1;
$whereSql = "(" . implode(' OR ', $whereParts) . ")";
if ($activeFilter) {
    $whereSql .= " AND d.[$docActiveCol] = 1";
}
if (!empty($docFilterIds) && ai_db_has_col($chunkTable, $docIdCol)) {
    $placeholders = implode(',', array_fill(0, count($docFilterIds), '?'));
    $whereSql .= " AND c.[$docIdCol] IN ({$placeholders})";
    foreach (array_values($docFilterIds) as $id) {
        $params[] = $id;
    }
}

$fromSql = "{$chunkTable} c WITH (NOLOCK)";
if ($useDocJoin) {
    $fromSql .= " INNER JOIN {$docTable} d WITH (NOLOCK) ON c.[$docIdCol] = d.[$docPkCol]";
}

$sql = "SELECT TOP {$topN}
            c.[$chunkTextCol] AS chunk_text,
            " . (ai_db_has_col($chunkTable, $docIdCol) ? "c.[$docIdCol] AS document_id," : "NULL AS document_id,") . "
            " . (ai_db_has_col($chunkTable, $chunkIdxCol) ? "c.[$chunkIdxCol] AS chunk_index," : "NULL AS chunk_index,") . "
            (" . implode(' + ', $scoreParts) . ") AS score
        FROM {$fromSql}
        WHERE {$whereSql}
        ORDER BY " . ($sourceBoost ? "CASE WHEN d.[$docSourceCol] = 'DB_SYNC' THEN 0 ELSE 1 END DESC, " : "") . "score DESC";

$rows = ai_db_fetch_all(ai_db_query($sql, $params));

$context = '';
$used = 0;
$docIds = [];
foreach ($rows as $r) {
    if ((int)($r['score'] ?? 0) < $minScore) continue;
    $chunk = trim($r['chunk_text'] ?? '');
    if ($chunk === '') continue;

    $header = '';
    if (!empty($r['document_id']) || !empty($r['chunk_index'])) {
        $header = "[Doc " . ($r['document_id'] ?? '-') . " | Chunk " . ($r['chunk_index'] ?? '-') . "]\n";
    }
    if (!empty($r['document_id'])) {
        $docIds[(string)$r['document_id']] = true;
    }
    $add = $header . $chunk . "\n\n";
    if (strlen($context) + strlen($add) > AI_CONTEXT_MAX_CHARS) {
        $remaining = AI_CONTEXT_MAX_CHARS - strlen($context);
        if ($remaining > 0) {
            $context .= substr($add, 0, $remaining);
        }
        break;
    }
    $context .= $add;
    $used++;
    if ($used >= AI_CONTEXT_MAX_CHUNKS) break;
}

if (trim($context) === '') {
    // Fallback: search in ai_documents description (sync data)
    $docTextCol = $docCols['description'] ?? null;
    if ($docTextCol && ai_db_has_col($docTable, $docTextCol)) {
        $scoreParts = [];
        $whereParts = [];
        $params = [];
        foreach ($terms as $t) {
            $like = '%' . ai_like_escape($t) . '%';
            $scoreParts[] = "CASE WHEN [$docTextCol] LIKE ? THEN 1 ELSE 0 END";
            $params[] = $like;
            $whereParts[] = "[$docTextCol] LIKE ?";
            $params[] = $like;
        }

        $topN = (int) (AI_CONTEXT_MAX_CHUNKS * 2);
        $whereSql = "(" . implode(' OR ', $whereParts) . ")";
        if ($docActiveCol && ai_db_has_col($docTable, $docActiveCol)) {
            $whereSql .= " AND [{$docActiveCol}] = 1";
        }
        if (!empty($docFilterIds) && ai_db_has_col($docTable, $docCols['id'])) {
            $placeholders = implode(',', array_fill(0, count($docFilterIds), '?'));
            $whereSql .= " AND [" . $docCols['id'] . "] IN ({$placeholders})";
            foreach (array_values($docFilterIds) as $id) {
                $params[] = $id;
            }
        }
        $sql = "SELECT TOP {$topN}
                    [$docTextCol] AS doc_text,
                    " . (ai_db_has_col($docTable, $docCols['id']) ? "[" . $docCols['id'] . "] AS document_id," : "NULL AS document_id,") . "
                    " . (ai_db_has_col($docTable, $docCols['title']) ? "[" . $docCols['title'] . "] AS title," : "NULL AS title,") . "
                    (" . implode(' + ', $scoreParts) . ") AS score
                FROM {$docTable} WITH (NOLOCK)
                WHERE {$whereSql}
                ORDER BY " . ($docSourceCol && ai_db_has_col($docTable, $docSourceCol) ? "CASE WHEN [{$docSourceCol}] = 'DB_SYNC' THEN 0 ELSE 1 END DESC, " : "") . "score DESC";

        $rows = ai_db_fetch_all(ai_db_query($sql, $params));
        foreach ($rows as $r) {
            if ((int)($r['score'] ?? 0) < $minScore) continue;
            $docText = trim($r['doc_text'] ?? '');
            if ($docText === '') continue;

            $header = '';
            if (!empty($r['document_id']) || !empty($r['title'])) {
                $header = "[Doc " . ($r['document_id'] ?? '-') . " | " . ($r['title'] ?? '-') . "]\n";
            }
            if (!empty($r['document_id'])) {
                $docIds[(string)$r['document_id']] = true;
            }

            $snippet = $docText;
            if (strlen($snippet) > 2000) {
                $snippet = substr($snippet, 0, 2000) . '...';
            }
            $add = $header . $snippet . "\n\n";
            if (strlen($context) + strlen($add) > AI_CONTEXT_MAX_CHARS) {
                $remaining = AI_CONTEXT_MAX_CHARS - strlen($context);
                if ($remaining > 0) {
                    $context .= substr($add, 0, $remaining);
                }
                break;
            }
            $context .= $add;
            $used++;
            if ($used >= AI_CONTEXT_MAX_CHUNKS) break;
        }
    }
}

if (trim($context) === '') {
    if (!empty($docFilterIds) && ai_docs_are_scanned_pdfs($docFilterIds)) {
        $noText = "Dokumen PDF ini tidak memiliki teks (hasil scan). OCR belum tersedia, jadi AI tidak bisa membaca isinya.\nSilakan upload versi PDF yang mengandung teks atau file TXT.";
        $images = ai_fetch_images_for_docs($docFilterIds);
        if (!empty($images)) {
            $noText .= "\n\n[LAMPIRAN_GAMBAR]\n";
            foreach ($images as $img) {
                $path = trim((string)($img['image_path'] ?? ''));
                if ($path === '') continue;
                $caption = trim((string)($img['caption'] ?? ''));
                if ($caption === '') {
                    $caption = 'Dokumen ' . ($img['document_id'] ?? '-') . (isset($img['page_no']) ? ' halaman ' . $img['page_no'] : '');
                }
                $noText .= '[[image:' . $path . '|' . $caption . "]]\n";
            }
        }
        ai_save_history($userId, 'user', $question);
        ai_save_history($userId, 'assistant', $noText);
        ai_log_request($userId, $question, $noText, $model, '', 0, 'no_text_pdf');
        echo $noText;
        exit;
    }
    $noData = 'Data tidak ditemukan dalam dokumen yang tersedia.';
    ai_save_history($userId, 'user', $question);
    ai_save_history($userId, 'assistant', $noData);
    ai_log_request($userId, $question, $noData, $model, '', 0, 'no_context');
    echo $noData;
    exit;
}

@set_time_limit(0);
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 'off');
while (ob_get_level() > 0) ob_end_flush();
ob_implicit_flush(true);

$prompt = AI_SYSTEM_PROMPT . "\n\nContext:\n" . $context . "\n\nUser: " . $question . "\nAI:";

$payload = [
    'model' => $model,
    'prompt' => $prompt,
    'stream' => true,
    'options' => [
        'temperature' => 0.2,
        'top_p' => 0.9,
        'num_ctx' => 4096,
        'num_predict' => 1024,
    ],
];

$assistantText = '';
$start = microtime(true);

$ch = curl_init(rtrim(AI_OLLAMA_URL, '/') . '/api/generate');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 0);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$buffer = '';
$stream_done = false;

curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$buffer, &$stream_done, &$assistantText) {
    $buffer .= $data;
    $lines = preg_split("/\r\n|\n|\r/", $buffer);
    $last = array_pop($lines);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (strpos($line, 'data:') === 0) {
            $line = trim(substr($line, 5));
        }

        $decoded = json_decode($line, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($decoded['response']) && $decoded['response'] !== null) {
                $out = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $decoded['response']);
                $assistantText .= $out;
                echo $out;
                @ob_flush(); @flush();
            }
            if (!empty($decoded['done'])) {
                $stream_done = true;
            }
        }
    }

    $buffer = $last;
    return strlen($data);
});

$res = curl_exec($ch);
$errno = curl_errno($ch);
$err = curl_error($ch);
curl_close($ch);

if (!empty($buffer)) {
    $line = trim($buffer);
    $decoded = json_decode($line, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['response'])) {
        $out = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $decoded['response']);
        $assistantText .= $out;
        echo $out;
    }
}

$latencyMs = (int) ((microtime(true) - $start) * 1000);
if ($res === false && !$stream_done) {
    $assistantText .= "\n\n[ERROR] {$err}";
    echo "\n\n[ERROR] {$err}";
    ai_log_request($userId, $question, $assistantText, $model, $context, $latencyMs, 'ollama_error');
    exit;
}

$images = ai_fetch_images_for_docs($docIds);
if (!empty($images)) {
    $assistantText .= "\n\n[LAMPIRAN_GAMBAR]\n";
    echo "\n\n[LAMPIRAN_GAMBAR]\n";
    foreach ($images as $img) {
        $path = trim((string)($img['image_path'] ?? ''));
        if ($path === '') continue;
        $caption = trim((string)($img['caption'] ?? ''));
        if ($caption === '') {
            $caption = 'Dokumen ' . ($img['document_id'] ?? '-') . (isset($img['page_no']) ? ' halaman ' . $img['page_no'] : '');
        }
        $line = '[[image:' . $path . '|' . $caption . "]]\n";
        $assistantText .= $line;
        echo $line;
    }
}

ai_save_history($userId, 'user', $question);
ai_save_history($userId, 'assistant', $assistantText);
ai_log_request($userId, $question, $assistantText, $model, $context, $latencyMs, 'ok');

@ob_flush(); @flush();
exit;
