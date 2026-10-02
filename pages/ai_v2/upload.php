<?php
// pages/ai_v2/upload.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/pdf_pipeline.php';
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

function ai_pdf_unescape_literal($s) {
    $s = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($m) {
        return chr(octdec($m[1]));
    }, $s);

    $map = [
        '\\\\n' => "\n",
        '\\\\r' => "\r",
        '\\\\t' => "\t",
        '\\\\b' => "\x08",
        '\\\\f' => "\x0C",
        '\\\\(' => '(',
        '\\\\)' => ')',
        '\\\\\\\\' => '\\',
    ];

    return strtr($s, $map);
}

function ai_extract_text_from_pdf($pdfBinary) {
    if (strpos($pdfBinary, '%PDF') !== 0) {
        return '';
    }

    $blocks = [];
    if (preg_match_all('/<<(.+?)>>\s*stream\s*(.*?)\s*endstream/s', $pdfBinary, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $dict = $m[1];
            $stream = $m[2];
            if (strpos($dict, '/FlateDecode') !== false) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false && strlen($stream) > 2) {
                    $decoded = @gzinflate(substr($stream, 2));
                }
                if ($decoded !== false && $decoded !== null) {
                    $stream = $decoded;
                }
            }

            if (strpos($stream, 'BT') === false || strpos($stream, 'ET') === false) {
                continue;
            }
            $blocks[] = $stream;
        }
    }

    if (empty($blocks)) {
        return '';
    }

    $textParts = [];
    foreach ($blocks as $stream) {
        if (!preg_match_all('/BT(.*?)ET/s', $stream, $sections)) {
            continue;
        }

        foreach ($sections[1] as $section) {
            if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*Tj/s', $section, $direct)) {
                foreach ($direct[0] as $token) {
                    if (preg_match('/^\((.*)\)\s*Tj$/s', trim($token), $val)) {
                        $textParts[] = ai_pdf_unescape_literal($val[1]);
                    }
                }
            }

            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $section, $arrays)) {
                foreach ($arrays[1] as $arr) {
                    if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', $arr, $literals)) {
                        $line = '';
                        foreach ($literals[0] as $lit) {
                            $line .= ai_pdf_unescape_literal(substr($lit, 1, -1));
                        }
                        if ($line !== '') {
                            $textParts[] = $line;
                        }
                    }
                }
            }
        }
    }

    return trim(implode("\n", $textParts));
}

function ai_upload_base_dir() {
    $base = realpath(__DIR__ . '/../../uploads');
    if (!$base) {
        $base = __DIR__ . '/../../uploads';
    }
    $dir = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ai_v2';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function ai_upload_base_url() {
    return '/gg_app/uploads/ai_v2';
}

function ai_ensure_images_table() {
    global $AI_TABLES, $AI_COLS;
    $table = $AI_TABLES['images'];
    if (ai_db_table_exists($table)) {
        return;
    }
    $cols = $AI_COLS['images'];
    $sql = "
        CREATE TABLE {$table} (
            [{$cols['id']}] INT IDENTITY(1,1) PRIMARY KEY,
            [{$cols['doc_id']}] INT NOT NULL,
            [{$cols['page_no']}] INT NULL,
            [{$cols['image_index']}] INT NULL,
            [{$cols['image_path']}] NVARCHAR(500) NOT NULL,
            [{$cols['caption']}] NVARCHAR(400) NULL,
            [{$cols['created_at']}] DATETIME NULL
        )
    ";
    ai_db_query($sql);
}

function ai_ocr_temp_dir() {
    $dir = ai_upload_base_dir() . DIRECTORY_SEPARATOR . 'ocr_tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function ai_run_tesseract($imagePath) {
    $bin = AI_TESSERACT_BIN;
    $GLOBALS['ai_ocr_last_error'] = '';
    if (!$bin) {
        $GLOBALS['ai_ocr_last_error'] = 'Tesseract bin kosong.';
        return '';
    }
    if (strpos($bin, DIRECTORY_SEPARATOR) !== false && !is_file($bin)) {
        $GLOBALS['ai_ocr_last_error'] = 'Tesseract bin tidak ditemukan: ' . $bin;
        return '';
    }

    $tmpBase = ai_ocr_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_' . uniqid();
    $cmd = '"' . $bin . '" ' . escapeshellarg($imagePath) . ' ' . escapeshellarg($tmpBase) .
        ' -l ' . escapeshellarg(AI_TESSERACT_LANG) . ' --oem 1 --psm 6';
    @exec($cmd . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $GLOBALS['ai_ocr_last_error'] = 'Tesseract error (' . $code . '): ' . implode("\n", (array)$out);
        return '';
    }
    $txtPath = $tmpBase . '.txt';
    if (!is_file($txtPath)) {
        $GLOBALS['ai_ocr_last_error'] = 'Tesseract output tidak ditemukan.';
        return '';
    }
    $text = @file_get_contents($txtPath);
    @unlink($txtPath);
    return is_string($text) ? $text : '';
}

function ai_ocr_capture($imagePath, $pageNo) {
    $text = ai_run_tesseract($imagePath);
    $GLOBALS['ai_ocr_pages'][] = [
        'page_no' => (int)$pageNo,
        'image_path' => $imagePath,
        'text' => $text,
    ];
    return $text;
}

function ai_path_to_url($filePath) {
    $baseDir = rtrim(str_replace('\\', '/', ai_upload_base_dir()), '/');
    $path = str_replace('\\', '/', $filePath);
    if (strpos($path, $baseDir) === 0) {
        $rel = ltrim(substr($path, strlen($baseDir)), '/');
        return rtrim(ai_upload_base_url(), '/') . '/' . $rel;
    }
    return '';
}

function ai_extract_text_from_pdf_ocr($pdfPath, $docId = null) {
    $result = ai_pdf_pipeline($pdfPath, [
        'doc_id' => $docId,
        'dpi' => 300,
        'max_pages' => AI_OCR_MAX_PAGES,
        'enable_ocr' => true,
        'ocr_callback' => 'ai_ocr_capture',
    ]);
    if (!empty($result['texts'])) {
        return trim(implode("\n\n", $result['texts']));
    }
    return '';
}

$message = '';
$error = '';
$ocrPages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload gagal.');
        }

        $file = $_FILES['doc_file'];
        $maxSize = 3 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new Exception('Ukuran file terlalu besar (maks 3 MB).');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['txt', 'pdf'];
        if (!in_array($ext, $allowedExt, true)) {
            throw new Exception('Hanya file TXT atau PDF yang diizinkan.');
        }
        $forceOcr = !empty($_POST['force_ocr']);

        $raw = @file_get_contents($file['tmp_name']);
        if ($raw === false || $raw === null) {
            throw new Exception('Gagal membaca file.');
        }

        if ($ext === 'pdf') {
            $extracted = ai_extract_text_from_pdf($raw);
            $text = ai_normalize_text($extracted);
        } else {
            $text = ai_normalize_text($raw);
        }

        if ($text === '' && $ext !== 'pdf') {
            throw new Exception('Isi dokumen kosong.');
        }

        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            $title = pathinfo($file['name'], PATHINFO_FILENAME);
        }

        $conn = ai_db();
        sqlsrv_begin_transaction($conn);

        global $AI_TABLES, $AI_COLS;
        $docTable = $AI_TABLES['documents'];
        $docCols = $AI_COLS['documents'];

        if ($ext === 'pdf' && ($forceOcr || $text === '')) {
            $GLOBALS['ai_ocr_pages'] = [];
            $ocrText = ai_extract_text_from_pdf_ocr($file['tmp_name'], null);
            $ocrPages = $GLOBALS['ai_ocr_pages'];
            if ($ocrText !== '') {
                $text = ai_normalize_text($ocrText);
            } else {
                $text = 'Dokumen PDF tanpa teks (kemungkinan hasil scan). OCR belum tersedia atau gagal. Gunakan gambar terlampir jika ada.';
            }
        }

        $docId = ai_db_insert_returning($docTable, [
            $docCols['title'] => $title,
            $docCols['description'] => $text,
            $docCols['source_type'] => strtoupper($ext),
            $docCols['created_by'] => (string)$userId,
            $docCols['created_at'] => date('Y-m-d H:i:s'),
            $docCols['is_active'] => 1,
        ], $docCols['id']);
        if (!$docId) {
            throw new Exception('Gagal mendapatkan ID dokumen.');
        }

        $chunks = ai_chunk_text($text, AI_CHUNK_SIZE, AI_CHUNK_OVERLAP);
        $chunkTable = $AI_TABLES['chunks'];
        $chunkCols = $AI_COLS['chunks'];

        $chunkIndex = 0;
        foreach ($chunks as $chunkText) {
            $chunkIndex++;
            ai_db_insert($chunkTable, [
                $chunkCols['doc_id'] => $docId,
                $chunkCols['chunk_idx'] => $chunkIndex,
                $chunkCols['chunk_text'] => $chunkText,
                $chunkCols['created_at'] => date('Y-m-d H:i:s'),
            ]);
        }

        $imageCount = 0;
        $imageNote = '';
        if ($ext === 'pdf') {
            ai_ensure_images_table();
            $GLOBALS['ai_ocr_pages'] = [];
            $pipe = ai_pdf_pipeline($file['tmp_name'], [
                'doc_id' => $docId,
                'dpi' => AI_PDF_IMAGE_DPI,
                'max_pages' => AI_PDF_IMAGE_MAX_PAGES,
                'enable_ocr' => $forceOcr || $text === '',
                'ocr_callback' => 'ai_ocr_capture',
            ]);
            if (empty($ocrPages) && !empty($GLOBALS['ai_ocr_pages'])) {
                $ocrPages = $GLOBALS['ai_ocr_pages'];
            }

            $images = [];
            if (!empty($pipe['images'])) {
                $imgTable = $AI_TABLES['images'];
                $imgCols = $AI_COLS['images'];
                $webBase = ai_upload_base_url() . '/doc_' . $docId . '/pages';
                foreach ($pipe['images'] as $idx => $filePath) {
                    $fileName = basename($filePath);
                    $pageNo = $idx + 1;
                    ai_db_insert($imgTable, [
                        $imgCols['doc_id'] => $docId,
                        $imgCols['page_no'] => $pageNo,
                        $imgCols['image_index'] => $idx + 1,
                        $imgCols['image_path'] => $webBase . '/' . $fileName,
                        $imgCols['caption'] => 'Halaman ' . $pageNo,
                        $imgCols['created_at'] => date('Y-m-d H:i:s'),
                    ]);
                    $imageCount++;
                }
            }
            if (!empty($pipe['errors'])) {
                $imageNote = implode(' | ', $pipe['errors']);
            }
            if (($forceOcr || $text === '') && empty($ocrPages)) {
                $ocrErr = trim((string)($GLOBALS['ai_ocr_last_error'] ?? ''));
                if ($ocrErr !== '') {
                    $imageNote = trim($imageNote . ' | OCR: ' . $ocrErr);
                } else {
                    $imageNote = trim($imageNote . ' | OCR: hasil kosong. Cek Tesseract & kualitas scan.');
                }
            }
        }

        sqlsrv_commit($conn);
        $message = "Upload berhasil. Total chunk: " . count($chunks);
        if ($ext === 'pdf') {
            $message .= ". Gambar: " . $imageCount;
            if ($imageNote !== '') {
                $message .= ". " . $imageNote;
            }
        }
    } catch (Exception $e) {
        $conn = ai_db();
        if ($conn) {
            @sqlsrv_rollback($conn);
        }
        $error = $e->getMessage();
    }
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0">Upload Dokumen (TXT/PDF)</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="sync.php" class="btn btn-sm btn-outline-primary">Sync Database</a>
                    <a href="index.php" class="btn btn-sm btn-secondary">Kembali ke Chat</a>
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

                    <?php if (!empty($ocrPages)): ?>
                        <div class="card mt-3">
                            <div class="card-header">Hasil OCR per Halaman</div>
                            <div class="card-body">
                                <?php foreach ($ocrPages as $p): ?>
                                    <?php
                                        $imgUrl = ai_path_to_url($p['image_path'] ?? '');
                                        $ocrText = trim((string)($p['text'] ?? ''));
                                    ?>
                                    <div class="mb-3">
                                        <div class="font-weight-bold mb-2">Halaman <?= (int)($p['page_no'] ?? 0) ?></div>
                                        <?php if ($imgUrl): ?>
                                            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Halaman <?= (int)($p['page_no'] ?? 0) ?>" class="img-fluid border rounded mb-2" style="max-height: 360px;">
                                        <?php endif; ?>
                                        <pre class="p-2 border rounded bg-light" style="white-space: pre-wrap;"><?= htmlspecialchars($ocrText !== '' ? $ocrText : '[OCR kosong]') ?></pre>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Judul Dokumen</label>
                            <input type="text" name="title" class="form-control" placeholder="Opsional">
                        </div>
                        <div class="form-group">
                            <label>File TXT/PDF</label>
                            <input type="file" name="doc_file" accept=".txt,.pdf,application/pdf,text/plain" class="form-control-file" required>
                        </div>
                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="force_ocr" name="force_ocr" value="1">
                                <label class="form-check-label" for="force_ocr">Paksa OCR (Tesseract) untuk PDF scan</label>
                            </div>
                            <small class="form-text text-muted">Aktifkan jika PDF berupa gambar/scan agar teks bisa dibaca AI.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Upload & Proses</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
