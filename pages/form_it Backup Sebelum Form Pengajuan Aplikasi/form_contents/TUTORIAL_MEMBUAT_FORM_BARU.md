# Tutorial: Cara Menambahkan Form Baru

Panduan ini menjelaskan langkah-langkah untuk menambahkan form pengajuan baru ke dalam sistem aplikasi IT.

## 1. Persiapan Database
Buat tabel baru di database SQL Server untuk menyimpan data form.
Pastikan tabel memiliki kolom standar seperti `ticket`, `created_at`, `created_by`, `updated_at`, `updated_by`dan `status_ticket`.

```sql
CREATE TABLE Form_Nama_Baru (
    id INT IDENTITY(1,1) PRIMARY KEY,
    ticket VARCHAR(50),
    nama_pemohon VARCHAR(100),
    jabatan VARCHAR(100) NULL,
    departemen VARCHAR(100) NULL,
    bagian VARCHAR(100) NULL,
    tgl_pengajuan DATE NULL,
    kategori VARCHAR(100) NULL,
    -- tambahkan kolom lain sesuai kebutuhan
    status_ticket VARCHAR(50) DEFAULT 'Pending',
    created_at DATETIME DEFAULT GETDATE(),
    created_by VARCHAR(100),
    updated_at DATETIME DEFAULT GETDATE(),
    updated_by VARCHAR(100)
);
```

## 2. Membuat Frontend Form (UI)
Buat file PHP baru di folder `pages/form_it/form_contents/`.
Contoh: `form_nama_baru.php`.
Setiap Modal Header harus pake theme user.
Gunakan struktur standar berikut:
```php
<form method="POST" id="formNamaBaru">
    <input type="hidden" name="form_type" value="nama_baru">
    
    <!-- Kolom Standar (Nama, Jabatan, dll) copy dari form lain -->
    
    <!-- Kolom Khusus Form Ini -->
    <div class="form-group">
        <label>Input Khusus</label>
        <input type="text" name="input_khusus" class="form-control">
    </div>
</form>

<script>
    // Script khusus form ini (jika ada)
</script>
```

## 3. Registrasi Form (Load Form)
Buka file `pages/form_it/load_form.php`.
Tambahkan file form anda ke dalam array `$formFiles`.

```php
$formFiles = [
    'pengajuan_perangkat' => 'form_contents/form_pengajuan_perangkat.php',
    'nama_baru'           => 'form_contents/form_nama_baru.php', // Tambahkan ini
];
```

## 4. Menambahkan di Menu Pilihan (List Form)
Buka file `pages/form_it/list_form.php`.

**Langkah 4a:** Tambahkan item di Modal "Pilih Form".
Cari `<div id="formList">` dan tambahkan:
```html
<a href="#" class="list-group-item list-group-item-action form-item" data-form-type="nama_baru">
    <div class="d-flex w-100 justify-content-between">
        <h5 class="mb-1"><i class="fas fa-file-alt mr-2"></i>Form Nama Baru</h5>
    </div>
    <small>Deskripsi singkat form.</small>
</a>
```

**Langkah 4b:** Tambahkan Logic Tombol Simpan.
Cari logic `$('#btnSimpanForm').on('click', ...` di bagian bawah file.
Tambahkan kondisi untuk form ID anda:

```javascript
} else if ($('#formNamaBaru').length) {
    formId = '#formNamaBaru';
}
```

## 5. Backend Logic (Proses Simpan)
Buka file `pages/form_it/proses_simpan.php`.

**Langkah 5a:** Daftarkan case baru di `switch ($formType)`.
```php
case 'nama_baru':
    handleNamaBaru();
    break;
```

**Langkah 5b:** Buat fungsi handler di bagian bawah file.
```php
function handleNamaBaru() {
    // 1. Ambil data dari $_POST
    $input = $_POST['input_khusus'] ?? '';
    $bagian = $_POST['bagian'] ?? '';     // Ambil bagian
    $kategori = 'Nama Kategori Form';     // Set kategori (bisa statis atau dari input)
    
    // 2. Validasi
    
    // 3. Generate Ticket
    // (Copy fungsi generateTicket dari yang lain dan sesuaikan prefixnya)
    
    // 4. Insert ke Database
    // 4. Insert ke Database
    $sql = "INSERT INTO Form_Nama_Baru (..., bagian, kategori) VALUES (..., ?, ?)";
    $params = [..., $bagian, $kategori];
    // Eksekusi query...
    
    echo json_encode(['success'=>true, 'message'=>'Berhasil disimpan']);
}
```

## 6. Menampilkan Data di Tabel Utama
Buka file `pages/form_it/list_form_serverside.php`.

Tambahkan query `UNION ALL` ke dalam `$baseQuery`.
```php
UNION ALL
SELECT 
    ticket,
    nama_pemohon,
    departemen,
    tgl_pengajuan,
    status_ticket,
    status_ticket,
    ISNULL(kategori,'Nama Kategori Default') AS kategori, -- Gunakan kolom kategori jika ada
    created_at
FROM Form_Nama_Baru
```

7. Membuat Halaman Detail (View)
Buat file PHP baru di folder `pages/form_it/` (bukan di form_contents).
Contoh: `detail_nama_baru.php`.

Gunakan file `detail_grant_revoke.php` atau `detail_akses_internet.php` sebagai template.
Pastikan mengambil data berdasarkan `ticket` yang dikirim via GET param.

**Routing ke Halaman Detail:**
Buka file `pages/form_it/list_form.php`.
Cari fungsi `$(document).on('click', '.btn-detail', function() {`.
Tambahkan kondisi routing berdasarkan prefix tiket atau kategori.

```javascript
var isNamaBaru = ticket.startsWith('NEW-') || kategori.toLowerCase() === 'nama baru';

if (isCCTV) {
    url = 'detail_cctv.php?ticket=' + encodeURIComponent(ticket);
} else if (isNamaBaru) {
    // Arahkan ke file detail yang baru dibuat
    url = 'detail_nama_baru.php?ticket=' + encodeURIComponent(ticket);
} else {
    // ...
}
```

## 10. Implementasi Cetak PDF (Opsional)

Jika form memerlukan fitur cetak PDF, ikuti langkah berikut:

**Langkah 10a:** Pastikan Library Dompdf Tersedia.
Cek folder `vendor/dompdf` atau `vendor/autoload.php`.

**Langkah 10b:** Buat Script Generator PDF.
Buat file baru, misalnya `pages/form_it/generate_pdf_nama_baru.php`.
Gunakan `generate_pdf_grant_revoke.php` atau `generate_pdf_cctv.php` sebagai referensi.

Struktur dasar script PDF:
```php
<?php
ob_start();
require '../../vendor/autoload.php';
use Dompdf\Dompdf;
require '../../koneksi.php';

// 1. Ambil ID/Ticket
$ticket = $_GET['ticket'] ?? '';

// 2. Ambil Data Utama
$sql = "SELECT * FROM Form_Nama_Baru WHERE ticket = ?";
// ... eksekusi query ... 

// 3. Ambil Data Tanda Tangan (jika ada)
$ttd = [];
$sqlTtd = "SELECT * FROM Form_Pengajuan_Barang_TTD WHERE Ticket = ?";
// ... fetch array ke $ttd ...

// 4. Helper Functions (Format Tanggal & Clean Text)
function fmtDate($d){ ... }
function clean($t){ ... }

// 5. Build HTML Content
$html = "<html><head><style>...</style></head><body>";
$html .= "<h1>Judul Form</h1>";
$html .= "<table>...data...</table>";
// ... dst ...
$html .= "</body></html>";

// 6. Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdf = $dompdf->output();

// 7. Output: Download atau Preview
if (isset($_GET['download']) && $_GET['download'] == '1') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Form-'.$ticket.'.pdf"');
    echo $pdf; 
    exit;
}

// Preview Mode (Base64 embed)
$b64 = base64_encode($pdf);
echo "<object data='data:application/pdf;base64,$b64' ... ></object>";
?>
```

**Tips Layout PDF:**
- Gunakan tabel (`<table>`) untuk layout kolom tanda tangan agar rapi.
- Gunakan CSS inline atau tag `<style>` di dalam `$html`.
- Untuk tanda tangan gambar, convert ke base64 (`base64_encode(file_get_contents($path))`) agar muncul di PDF.
- Sesuaikan footer tanda tangan dengan contoh `generate_pdf_cctv.php` jika ingin label peran yang statis (e.g. "Pemohon", "Kabag IT") bukan nama orangnya.


## 8. Implementasi Fitur Edit (Update)

Untuk memungkinkan pengeditan data form:

**Langkah 8a:** Update `pages/form_it/load_data.php`.
Tambahkan kondisi untuk mengambil data dari tabel baru berdasarkan prefix tiket.

```php
// Deteksi tipe tiket
$isNamaBaru = stripos($ticket, 'NEW-') === 0;

if ($isNamaBaru) {
    $sql = "SELECT TOP 1 * FROM Form_Nama_Baru WHERE ticket = ?";
}

// ...

// Tambahkan flag ke response JSON
echo json_encode([
    'success' => true,
    'data' => $row,
    // ...
    'isNamaBaru' => $isNamaBaru
], JSON_UNESCAPED_UNICODE);
```

**Langkah 8b:** Update `pages/form_it/list_form.php` (Frontend Population).
Cari fungsi `loadEditForm` dan tambahkan deteksi tipe form baru:

```javascript
/* Cari bagian ini di fungsi loadEditForm */
var formType = response.isEmail ? 'pengajuan_email_account' :
               // ...
               (response.isNamaBaru ? 'nama_baru' : 'pengajuan_perangkat');
```

Kemudian, cari fungsi `populateFormWithData` dan tambahkan logika untuk mengisi field khusus:

```javascript
if (formType === 'nama_baru') {
    // Populate field khusus
    $('input[name="input_khusus"]').val(data.input_khusus || '');
}
```

**Langkah 8c:** Update `pages/form_it/proses_update.php` (Backend Update).
Tambahkan logika update database:

```php
$isNamaBaru = stripos($ticket, 'NEW-') === 0;

// ...

} elseif ($isNamaBaru) {
    $input = $_POST['input_khusus'] ?? '';
    
    $sql = "UPDATE Form_Nama_Baru SET 
            input_khusus = ?,
            updated_at = GETDATE(),
            updated_by = ?
            WHERE ticket = ?";
            
    $params = [$input, $updatedByName, $ticket];
    $stmt = sqlsrv_query($conn, $sql, $params);
}
```

## 9. Implementasi Fitur Hapus (Delete)

Untuk memungkinkan penghapusan data:

**Langkah 9a:** Update `pages/form_it/proses_delete.php`.
Tambahkan kondisi penghapusan berdasarkan prefix tiket:

```php
} elseif (stripos($ticket, 'NEW-') === 0) {
    $sql = "DELETE FROM Form_Nama_Baru WHERE ticket = ?";
}
```

Selesai! Form baru sekarang sudah berfungsi sepenuhnya (Create, Read, Update, Delete).
