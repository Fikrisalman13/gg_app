-- ============================================================================
-- VERIFICATION QUERY: Check Timestamp Columns
-- ============================================================================
-- Query ini untuk memverifikasi bahwa kolom timestamp sudah ditambahkan
-- dan data tersimpan dengan benar

-- 1. Cek apakah kolom sudah ada di tabel
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'Form_Umum_Buka_Tanggal_Closingan'
  AND COLUMN_NAME IN ('unclosing_at', 'closed_at', 'unclosing_by', 'closed_by')
ORDER BY COLUMN_NAME;

-- 2. Lihat data form yang sudah di-unclosing atau closed
SELECT 
    ticket,
    status_ticket,
    nama_pemohon,
    unclosing_at,
    unclosing_by,
    closed_at,
    closed_by,
    created_at,
    updated_at
FROM Form_Umum_Buka_Tanggal_Closingan
WHERE status_ticket IN ('Unclosing', 'Closed')
ORDER BY updated_at DESC;

-- 3. Cek konsistensi data (closed_at harus lebih baru dari unclosing_at)
SELECT 
    ticket,
    status_ticket,
    unclosing_at,
    closed_at,
    DATEDIFF(MINUTE, unclosing_at, closed_at) as minutes_difference
FROM Form_Umum_Buka_Tanggal_Closingan
WHERE status_ticket = 'Closed'
  AND unclosing_at IS NOT NULL
  AND closed_at IS NOT NULL
ORDER BY closed_at DESC;

-- 4. Cek data yang mungkin tidak konsisten
-- (Status Unclosing tapi unclosing_at NULL, atau Status Closed tapi closed_at NULL)
SELECT 
    ticket,
    status_ticket,
    unclosing_at,
    closed_at,
    CASE 
        WHEN status_ticket = 'Unclosing' AND unclosing_at IS NULL THEN 'Missing unclosing_at'
        WHEN status_ticket = 'Closed' AND closed_at IS NULL THEN 'Missing closed_at'
        WHEN status_ticket = 'Closed' AND unclosing_at IS NULL THEN 'Missing unclosing_at'
        ELSE 'OK'
    END as data_status
FROM Form_Umum_Buka_Tanggal_Closingan
WHERE status_ticket IN ('Unclosing', 'Closed')
ORDER BY updated_at DESC;

-- 5. Summary report: Berapa form yang sudah unclosing/closed hari ini
SELECT 
    status_ticket,
    COUNT(*) as total_forms,
    MIN(CASE WHEN status_ticket = 'Unclosing' THEN unclosing_at ELSE closed_at END) as earliest_action,
    MAX(CASE WHEN status_ticket = 'Unclosing' THEN unclosing_at ELSE closed_at END) as latest_action
FROM Form_Umum_Buka_Tanggal_Closingan
WHERE status_ticket IN ('Unclosing', 'Closed')
  AND (
    (status_ticket = 'Unclosing' AND CAST(unclosing_at AS DATE) = CAST(GETDATE() AS DATE))
    OR
    (status_ticket = 'Closed' AND CAST(closed_at AS DATE) = CAST(GETDATE() AS DATE))
  )
GROUP BY status_ticket;
