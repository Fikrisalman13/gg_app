<?php
// pages/ai_v2/config.php
// Konfigurasi DB + Ollama untuk AI v2 (RAG via database)

// =========================
// Database SQL Server
// =========================
define('AI_DB_SERVER', '202.150.136.51');
define('AI_DB_NAME', 'GG');
define('AI_DB_USER', 'sa');
define('AI_DB_PASS', 'rahasiaIT2020');

// =========================
// Ollama
// =========================
define('AI_OLLAMA_URL', 'http://192.168.7.13:11434');
define('AI_DEFAULT_MODEL', 'llama3.1:8b');

$AI_AVAILABLE_MODELS = [
    'qwen2.5:14b' => 'qwen2.5:14b (lebih akurat)',
    'qwen2.5:7b'  => 'qwen2.5:7b (lebih cepat)',
    'llama3.1:8b' => 'llama3.1:8b (imbang)',
];

// =========================
// Tabel & Kolom (ubah jika berbeda)
// =========================
$AI_TABLES = [
    'documents'   => 'dbo.ai_documents',
    'chunks'      => 'dbo.ai_document_chunks',
    'images'      => 'dbo.ai_document_images',
    'history'     => 'dbo.ai_chat_history',
    'request_log' => 'dbo.ai_request_log',
];

$AI_COLS = [
    'documents' => [
        'id'           => 'document_id',
        'code'         => 'document_code',
        'title'        => 'title',
        'description'  => 'description',
        'source_type'  => 'source_type',
        'created_by'   => 'created_by',
        'created_at'   => 'created_at',
        'is_active'    => 'is_active',
    ],
    'chunks' => [
        'id'         => 'chunk_id',
        'doc_id'     => 'document_id',
        'chunk_idx'  => 'chunk_index',
        'chunk_text' => 'content',
        'created_at' => 'created_at',
    ],
    'images' => [
        'id'          => 'image_id',
        'doc_id'      => 'document_id',
        'page_no'     => 'page_no',
        'image_index' => 'image_index',
        'image_path'  => 'image_path',
        'caption'     => 'image_caption',
        'created_at'  => 'created_at',
    ],
    'history' => [
        'id'         => 'chat_id',
        'user_id'    => 'user_id',
        'role'       => 'role',
        'message'    => 'message',
        'created_at' => 'created_at',
    ],
    'request_log' => [
        'id'         => 'request_id',
        'user_id'    => 'user_id',
        'question'   => 'question',
        'model'      => 'model_used',
        'latency_ms' => 'response_time_ms',
        'created_at' => 'created_at',
    ],
];

// =========================
// RAG & Chunking
// =========================
define('AI_CHUNK_SIZE', 800);          // panjang karakter per chunk
define('AI_CHUNK_OVERLAP', 120);       // overlap antar chunk
define('AI_CONTEXT_MAX_CHARS', 6000);  // maksimal context dikirim ke AI
define('AI_CONTEXT_MAX_CHUNKS', 6);    // maksimal jumlah chunk dikirim

// =========================
// PDF Image Extraction
// =========================
define('AI_PDF_IMAGE_DPI', 150);
define('AI_PDF_IMAGE_MAX_PAGES', 20);
define('AI_IMAGE_MAX_RETURN', 6);

// =========================
// OCR (Tesseract)
// =========================
// Set full path if tesseract is not in PATH, e.g. "C:\\Program Files\\Tesseract-OCR\\tesseract.exe"
define('AI_TESSERACT_BIN', 'tesseract');
define('AI_TESSERACT_LANG', 'ind+eng');
define('AI_OCR_MAX_PAGES', 6);

// =========================
// System Prompt
// =========================
define(
    'AI_SYSTEM_PROMPT',
    "Kamu adalah AI internal perusahaan.\n" .
    "Jawablah HANYA berdasarkan data yang tersedia di database.\n" .
    "Prioritaskan KELENGKAPAN dan KEAKURATAN data penting.\n" .
    "JANGAN memotong langkah prosedur, menghilangkan tahapan penting, meringkas bagian kritikal, atau menghentikan jawaban di tengah.\n" .
    "Jika jawaban panjang, lanjutkan sampai SEMUA langkah selesai dan gunakan poin serta subjudul agar mudah dibaca.\n" .
    "Jika data tidak ditemukan secara lengkap, jawab dengan jelas bahwa data tidak tersedia, tanpa mengarang atau menambah informasi.\n" .
    "Gunakan format jawaban profesional: Judul, Tujuan, Ruang Lingkup (jika ada), Langkah-langkah berurutan, dan Catatan penting.\n" .
    "Pastikan semua poin penting dari dokumen disertakan."
);
