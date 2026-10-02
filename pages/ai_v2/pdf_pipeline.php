<?php
// pages/ai_v2/pdf_pipeline.php
// Pipeline: PDF -> PNG pages (+ optional OCR hook)
// Output: ['ok'=>bool, 'errors'=>[], 'texts'=>[], 'images'=>[], 'meta'=>[]]

require_once __DIR__ . '/config.php';

function ai_pdf_assert_imagick() {
    if (!extension_loaded('imagick')) {
        return ['ok' => false, 'error' => 'Imagick extension tidak aktif.'];
    }
    try {
        $fmt = (new Imagick())->queryFormats('PDF');
        if (empty($fmt)) {
            return ['ok' => false, 'error' => 'Imagick tidak mendukung PDF (delegate/GS belum aktif).'];
        }
    } catch (Exception $e) {
        return ['ok' => false, 'error' => 'Gagal memeriksa dukungan PDF pada Imagick: ' . $e->getMessage()];
    }
    return ['ok' => true];
}

function ai_pdf_normalize_dpi($dpi) {
    $dpi = (int)$dpi;
    if ($dpi < 150) $dpi = 150;
    if ($dpi > 300) $dpi = 300;
    return $dpi;
}

function ai_pdf_base_dir() {
    $base = realpath(__DIR__ . '/../../uploads');
    if (!$base) $base = __DIR__ . '/../../uploads';
    $dir = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ai_v2';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

function ai_pdf_make_dir($dir) {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
    }
    return true;
}

function ai_pdf_is_pdf($path) {
    if (!is_file($path)) return false;
    $fh = @fopen($path, 'rb');
    if (!$fh) return false;
    $sig = @fread($fh, 4);
    @fclose($fh);
    return $sig === '%PDF';
}

function ai_ocr_tesseract($imagePath, $lang = AI_TESSERACT_LANG, $bin = AI_TESSERACT_BIN) {
    // Hook OCR Tesseract. Return '' if disabled or fails.
    if (!$bin) return '';
    if (!is_file($imagePath)) return '';
    $tmpBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai_ocr_' . uniqid();
    $cmd = '"' . $bin . '" ' . escapeshellarg($imagePath) . ' ' . escapeshellarg($tmpBase) . ' -l ' . escapeshellarg($lang) . ' --psm 6';
    @exec($cmd, $out, $code);
    if ($code !== 0) return '';
    $txtPath = $tmpBase . '.txt';
    if (!is_file($txtPath)) return '';
    $text = @file_get_contents($txtPath);
    @unlink($txtPath);
    return is_string($text) ? $text : '';
}

function ai_pdf_pipeline($pdfPath, $opts = []) {
    $errors = [];
    $images = [];
    $texts = [];
    $meta = [
        'pages' => 0,
        'dpi' => null,
        'out_dir' => null,
    ];

    $check = ai_pdf_assert_imagick();
    if (!$check['ok']) {
        return ['ok' => false, 'errors' => [$check['error']], 'texts' => [], 'images' => [], 'meta' => $meta];
    }

    if (!ai_pdf_is_pdf($pdfPath)) {
        return ['ok' => false, 'errors' => ['File bukan PDF valid.'], 'texts' => [], 'images' => [], 'meta' => $meta];
    }

    $dpi = ai_pdf_normalize_dpi($opts['dpi'] ?? 200);
    $meta['dpi'] = $dpi;

    $docId = $opts['doc_id'] ?? null;
    $baseDir = $opts['base_dir'] ?? ai_pdf_base_dir();
    $subDir = $docId ? ('doc_' . $docId) : ('doc_' . date('Ymd_His') . '_' . uniqid());
    $outDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $subDir . DIRECTORY_SEPARATOR . 'pages';
    if (!ai_pdf_make_dir($outDir)) {
        return ['ok' => false, 'errors' => ['Gagal membuat folder output.'], 'texts' => [], 'images' => [], 'meta' => $meta];
    }
    $meta['out_dir'] = $outDir;

    $totalPages = 0;
    try {
        $ping = new Imagick();
        $ping->pingImage($pdfPath);
        $totalPages = (int)$ping->getNumberImages();
        $ping->clear();
        $ping->destroy();
    } catch (Exception $e) {
        return ['ok' => false, 'errors' => ['Gagal membaca PDF (Imagick/GS): ' . $e->getMessage()], 'texts' => [], 'images' => [], 'meta' => $meta];
    }

    if ($totalPages < 1) {
        return ['ok' => false, 'errors' => ['PDF tidak memiliki halaman terbaca.'], 'texts' => [], 'images' => [], 'meta' => $meta];
    }
    $meta['pages'] = $totalPages;

    $maxPages = (int)($opts['max_pages'] ?? $totalPages);
    if ($maxPages > $totalPages) $maxPages = $totalPages;
    if ($maxPages < 1) $maxPages = 1;

    $enableOcr = (bool)($opts['enable_ocr'] ?? false);
    $ocrCallback = $opts['ocr_callback'] ?? null;

    for ($i = 0; $i < $maxPages; $i++) {
        try {
            $img = new Imagick();
            $img->setResolution($dpi, $dpi);
            $img->readImage($pdfPath . '[' . $i . ']');
            $img->setImageFormat('png');
            $img->setImageCompressionQuality(90);
            $img->stripImage();

            $pageNo = $i + 1;
            $fileName = 'page_' . str_pad((string)$pageNo, 4, '0', STR_PAD_LEFT) . '.png';
            $filePath = $outDir . DIRECTORY_SEPARATOR . $fileName;
            $img->writeImage($filePath);
            $img->clear();
            $img->destroy();

            $images[] = $filePath;

            if ($enableOcr) {
                if (is_callable($ocrCallback)) {
                    $texts[] = (string)call_user_func($ocrCallback, $filePath, $pageNo);
                } else {
                    $texts[] = ai_ocr_tesseract($filePath);
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Gagal proses halaman ' . ($i + 1) . ': ' . $e->getMessage();
        }
    }

    $ok = empty($errors) && !empty($images);
    return ['ok' => $ok, 'errors' => $errors, 'texts' => $texts, 'images' => $images, 'meta' => $meta];
}
