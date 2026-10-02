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
    // Format standar: PREFIX-YYYYMMDD-001 (urutan 3 digit per tanggal).
    // Ambil nomor terbesar dengan prefix tanggal yang sama, lalu tambah 1.
    $ticketPrefix = 'NEW-' . date('Ymd') . '-';
    $ticket = $ticketPrefix . str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);

    // Semua endpoint terkait (load, update, download, detail) harus memakai pola yang sama:
    // /^NEW-\d{8}-\d{3}$/
    
    // 4. Insert ke Database
    $sql = "INSERT INTO Form_Nama_Baru (..., bagian, kategori) VALUES (..., ?, ?)";
    $params = [..., $bagian, $kategori];
    // Eksekusi query...
    
    echo json_encode(['success'=>true, 'message'=>'Berhasil disimpan']);
}
```

> Jangan menghitung tiket hanya dari jumlah baris. Gunakan nomor terbesar untuk prefix tanggal aktif dan lindungi kolom `ticket` dengan unique constraint agar tiket tidak ganda.

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
    ISNULL(kategori,'Nama Kategori Default') AS kategori,
    created_at,
    created_by
FROM Form_Nama_Baru
```

Selain `$baseQuery`, perbarui juga:
- Daftar kategori yang dapat dilihat approver.
- Jumlah tanda tangan wajib berdasarkan prefix tiket.
- Routing generator PDF.
- `get_ttd_count.php` untuk tabel status, jumlah persetujuan, dan nama tipe tiket.

## 7. Membuat Halaman Detail (View)
Buat file PHP baru di folder `pages/form_it/` (bukan di `form_contents`).
Contoh: `detail_nama_baru.php`.

Gunakan `detail_akses_internet.php` atau detail form aktif yang role tanda tangannya sama sebagai template. Pastikan mengambil data berdasarkan `ticket` dari parameter GET memakai parameterized query.

**Pola layout detail yang disetujui:**
- Gunakan satu bingkai luar untuk seluruh form.
- Header perusahaan hanya memakai garis pemisah logo dan judul.
- Informasi pemohon tidak memakai border pada setiap `<td>`.
- Jangan memakai selector luas seperti `.outer td { border: ... }`; selector itu membuat border tabel bersarang bertumpuk.
- Gunakan grid penuh hanya untuk area tanda tangan.
- Field pendek dapat disusun 2 baris × 3 kolom agar detail tidak terlalu panjang.
- Isi panjang harus memakai `white-space: pre-wrap` dan `overflow-wrap: anywhere`.
- Pada layar kecil, pertahankan bentuk form dengan wrapper horizontal scroll dan `min-width`.
- Escape seluruh output dengan `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Sertakan `rejection_status.php` dan beri tahu parent melalui `updateRejectButtonVisibility(...)` bila form mendukung penolakan.

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

Jika form memerlukan PDF, gunakan generator Form IT yang jumlah dan urutan role tanda tangannya paling mirip
sebagai acuan. Contoh:

- Lima role: `generate_pdf.php`.
- Empat role: `generate_pdf_perubahan_data_database.php`.
- Jangan hanya menyalin `generate_pdf_cctv.php` tanpa membandingkan kebutuhan form baru.

### 10a. Struktur Wajib Dokumen

Urutan dokumen mengikuti pola Form IT:

1. Header perusahaan dengan logo pada kolom kiri.
2. Judul form tepat di bawah header.
3. Body dengan satu border luar; field di dalamnya tidak diberi kotak masing-masing.
4. Tabel tanda tangan terpisah setelah body.
5. Catatan atau ketentuan.
6. Footer kode dokumen bila kode resmi tersedia.

> **Penting:** Hindari memberi `border` pada seluruh nested table dan seluruh sel body. Border bertumpuk
> menghasilkan garis ganda dan tampilan berbeda dari PDF Form IT lain.

### 10b. Susunan Identitas

Gunakan susunan identitas yang sama dengan PDF existing:

- Body langsung dimulai dari **Nama Pemohon**; nomor tiket tidak perlu dicetak di body.
- **Jabatan** berada di kiri dan **Tgl Pengajuan** berada di ujung kanan pada baris yang sama.
- Departemen dan Bagian menggunakan baris penuh.
- Field opsional, termasuk lampiran, tidak dirender jika nilainya kosong.
- Nomor tiket tetap dipakai pada nama file dan halaman preview untuk identifikasi.

Contoh kondisi field opsional:

```php
$attachmentRowHtml = '';
if (!empty($data['lampiran_nama_asli'])) {
    $attachmentRowHtml = '<tr><td>Lampiran Pendukung</td><td colspan="3">: '
        . clean($data['lampiran_nama_asli'])
        . '</td></tr>';
}
```

### 10c. Tabel Tanda Tangan

Gunakan `table-layout: fixed` dan `<colgroup>` agar lebar kolom stabil. Jumlah kolom harus sama dengan
jumlah role yang digunakan form.

```html
<table class="sign-table">
    <colgroup>
        <col style="width:20%">
        <col style="width:20%">
        <col style="width:20%">
        <col style="width:20%">
        <col style="width:20%">
    </colgroup>
    <tr class="sign-header">
        <td>Diajukan Oleh</td>
        <td>Mengetahui</td>
        <td>Diketahui Oleh</td>
        <td colspan="2">Disetujui Oleh</td>
    </tr>
    <!-- Baris gambar tanda tangan -->
    <!-- Baris label role -->
</table>
```

Aturan tanda tangan:

- Header menjelaskan tahap persetujuan; label role berada pada baris terakhir.
- Gunakan `colspan` jika dua role berada pada tahap persetujuan yang sama.
- Batasi tinggi gambar sekitar 50–60 piksel.
- Ubah gambar lokal menjadi data URI agar Dompdf dapat merendernya konsisten.
- Jangan menambahkan tabel pembungkus atau garis internal yang tidak ada pada generator acuan.

### 10d. Generate dan Output PDF

```php
<?php
ob_start();
require '../../vendor/autoload.php';
require '../../koneksi.php';

use Dompdf\Dompdf;

$dompdf = new Dompdf();
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$pdfContent = $dompdf->output();

if (($_GET['download'] ?? '') === '1') {
    ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Form-' . $ticket . '.pdf"');
    echo $pdfContent;
    exit;
}
```

### 10e. Halaman Preview

Samakan preview dengan generator Form IT lain:

- Muat AdminLTE dan Font Awesome.
- Gunakan container putih dengan `ticket-info`.
- Tampilkan nomor pengajuan, pemohon, dan departemen pada `ticket-info`.
- Gunakan `<object type="application/pdf">` untuk viewer.
- Sediakan tombol **Download PDF** dan **Kembali**.

### 10f. Checklist Verifikasi Visual

Sebelum PDF dinyatakan selesai:

- Bandingkan berdampingan dengan generator Form IT yang dijadikan acuan.
- Pastikan header, margin, font, border luar, dan posisi tanggal sama.
- Pastikan tidak ada garis ganda pada sambungan header, body, dan tanda tangan.
- Pastikan semua kolom tanda tangan sama lebar.
- Pastikan gambar tanda tangan tidak mengubah tinggi atau lebar kolom.
- Pastikan field opsional kosong tidak muncul.
- Uji teks panjang agar tidak bertumpuk atau terpotong.
- Uji preview, download, dan tombol kembali.
- Jalankan PHP lint dan `git diff --check`.



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

Jika form memiliki lampiran, baca nama file terverifikasi sebelum menghapus data, commit transaksi database terlebih dahulu, lalu hapus file dari folder penyimpanan yang sudah ditetapkan. Gunakan `basename()` dan jangan menerima path file dari browser.

## 11. Checklist Integrasi Akhir

Sebelum form dianggap selesai, pastikan prefix dan kategori baru sudah ditangani pada:

- `load_form.php`
- `list_form.php` untuk menu, submit, edit population, detail, dan rejection
- `proses_simpan.php`
- `load_data.php`
- `proses_update.php` atau endpoint update khusus
- `proses_delete.php`
- `proses_reject.php`
- `list_form_serverside.php`
- `get_ttd_count.php`
- Halaman detail dan generator PDF
- Endpoint lampiran bila tersedia

Jalankan `php -l` untuk semua PHP yang disentuh. Uji buat, detail, edit, tanda tangan tiap role, status parsial/final, penolakan, PDF, lampiran, dan hapus melalui browser.

Selesai! Form baru sekarang sudah berfungsi penuh sesuai alur yang dipakai.
