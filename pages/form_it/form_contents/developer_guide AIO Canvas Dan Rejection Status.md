# Panduan Pengembang: AIO Canvas & Rejection Status
**"All-In-One" Standardized Components for IT Forms**

Dokumen ini berisi panduan lengkap untuk menggunakan komponen standar **Tanda Tangan (Signature)** dan **Status Penolakan (Rejection Status)** pada form baru.

> **PENTING:** Implementasi tanda tangan melibatkan 3 bagian:
> 1. Include component di form input hanya bila tanda tangan Pemohon wajib sebelum simpan
> 2. Include component dan logic role di halaman detail
> 3. **(CRITICAL)** Registrasi ID form dan alur AJAX di controller (`list_form.php`)

---

## 1. Komponen Tanda Tangan (Signature Canvas)

**File:** `pages/form_it/form_contents/components/signature_canvas.php`

### Langkah 1: Implementasi pada File Form Input
Misal Anda membuat form baru bernama `form_pengajuan_baru.php`.

1.  **Include File Component**
    Tambahkan baris berikut di paling bawah file form Anda:
    ```php
    <?php include 'components/signature_canvas.php'; ?>
    ```

2.  **Logic Submit JavaScript**
    Gunakan pattern berikut pada script submit form Anda untuk memanggil validasi standar:
    ```javascript
    // Defensive submit handling
    window._allowSubmit = false;
    $('#formPengajuanBaru').off('submit.myTTD').on('submit.myTTD', function(e){
        if (!window._allowSubmit) {
            e.preventDefault();
            // Cek _skipTTDCheckOnce agar tidak looping saat script eksternal men-trigger submit ulang
            if (!window._skipTTDCheckOnce) { 
                // Panggil fungsi validasi global dari komponen
                if (typeof window.validateTTDBeforeSubmit === 'function') {
                    window.validateTTDBeforeSubmit().then(function(isSigned){
                        if (isSigned) {
                            window._allowSubmit = true;
                            $('#formPengajuanBaru')[0].submit();
                        }
                    });
                } else {
                    // Fallback
                    window._allowSubmit = true;
                    $('#formPengajuanBaru')[0].submit();
                }
            } else {
                 window._allowSubmit = true;
                 $('#formPengajuanBaru')[0].submit();
            }
            return false;
        }
        window._allowSubmit = false;
        return true;
    });
    ```

### Langkah 2: Registrasi di Controller (CRITICAL!)
**File:** `pages/form_it/list_form.php`

Agar validasi tanda tangan berjalan, Anda **WAJIB** mendaftarkan ID form tersebut di **DUA TEMPAT** dalam file `list_form.php`.

**1. Di Logic Validasi Tombol Simpan (`$('#btnSimpanForm').on('click'...)`)**
Cari blok kode `if ((formId === ...` yang mengecek `validateTTDBeforeSubmit`. Tambahkan ID form Anda disitu.

```javascript
// Cari kode ini sekitar baris 700-an
if ((formId === '#formPengajuanPerangkat' || 
     formId === '#formPengajuanAksesInternet' || 
     formId === '#formPengajuanBaru') // <-- Tambahkan ID Form Anda Disini!
    && typeof window.validateTTDBeforeSubmit === 'function') {
    
    // ... logic validasi ...
}
```
> **PENTING:** Jika tahap ini terlewat, form akan langsung tersimpan walau tanda tangan belum dibuat!

**2. Di Callback Success AJAX (`showFormSaveSuccess`)**
Tambahkan ID form Anda ke dalam logic penentuan `formId` agar sistem tahu form mana yang harus di-reload atau diproses TTD-nya setelah save berhasil.

```javascript
var formId = $('#formPengajuanPerangkat').length ? '#formPengajuanPerangkat' : 
             ($('#formPengajuanAksesInternet').length ? '#formPengajuanAksesInternet' : 
             ($('#formPengajuanBaru').length ? '#formPengajuanBaru' : null)); // <-- Tambah disini
```

> **Jika langkah ini dilewatkan:** Form akan tersimpan **tanpa** memunculkan popup tanda tangan.

---

## 2. Komponen Status Penolakan (Rejection Status)

**File:** `pages/form_it/form_contents/components/rejection_status.php`

Fitur ini memungkinkan Kabag/Kadept untuk menolak detail pengajuan dari modal, dan menampilkan status penolakan tersebut.

### Langkah 1: Persiapan Database
Pastikan tabel form Anda (misal `Form_Pengajuan_Baru`) memiliki kolom-kolom tracking berikut:
- `status_ticket` (VARCHAR)
- `rejected_by` (VARCHAR)
- `rejection_reason` (NVARCHAR - untuk alasan panjang)
- `rejection_date` (DATETIME)
- `updated_at` (DATETIME - opsional tapi disarankan)
- `updated_by` (VARCHAR - opsional tapi disarankan)

### Langkah 2: Registrasi di Backend
Anda perlu mendaftarkan form baru di **4 area backend** agar sistem mengenali tiket baru tersebut:
1. `proses_reject.php` untuk membaca dan memperbarui status penolakan.
2. `get_ttd_count.php` untuk polling status dan jumlah tanda tangan.
3. `list_form_serverside.php` untuk daftar, badge, jumlah wajib, dan routing PDF.
4. Query detail form untuk membaca `status_ticket`, `rejected_by`, `rejection_reason`, dan `rejection_date`.

#### A. `proses_reject.php`
Tambahkan logika deteksi prefix tiket dan query update tabel.
```php
$isBaru = stripos($ticket, 'BARU-') === 0; // Prefix tiket Anda

if ($isBaru) {
    $checkSql = "SELECT ticket, status_ticket FROM Form_Pengajuan_Baru WHERE ticket = ?";
} 
// ...
if ($isBaru) {
    $updateSql = "UPDATE Form_Pengajuan_Baru SET status_ticket = ?, rejected_by = ?, rejection_reason = ?, rejection_date = ? WHERE ticket = ?";
}
```

#### B. `pages/form_it/get_ttd_count.php`
Agar status tidak "berubah sendiri" saat polling (tampilan status real-time), registrasikan logic form baru di sini.

**1. Register Prefix Check:**
```php
$isBaru = stripos($ticket, 'BARU-') === 0;   // <-- Tambahkan ini
```

**2. Register Status Query:**
```php
} elseif ($isBaru) {
    $sqlStatus = "SELECT status_ticket FROM Form_Pengajuan_Baru WHERE ticket = ?";
}
```

**3. Register Return Logic (Ticket Type & Required Count):**
Cari blok `if ($isRejected)` dan blok return final. Tambahkan logic untuk form baru:

```php
// Tentukan label tipe tiket dan jumlah TTD wajib (misal 4 atau 5)
$ticketType = ... ($isBaru ? 'Baru' : 'IT');
$required = ... ($isBaru ? 4 : 5); 
```

Contoh lengkap return logic:
```php
$ticketType = $isInternet ? 'Internet' : ($isCCTV ? 'CCTV' : ($isEmail ? 'Email' : ($isBaru ? 'Nama Baru' : 'IT')));
$required = $isInternet ? 4 : ($isCCTV ? 4 : ($isEmail ? 5 : ($isBaru ? 4 : 5)));
```


#### C. `list_form_serverside.php`
Pastikan status "Ditolak" terbaca dengan benar. Terkadang perlu fungsi `trim()` untuk menghapus spasi dari database.
```php
$st = strtolower(trim($row['status_ticket'] ?? '')); // Gunakan trim()!
```

### Langkah 3: Implementasi Frontend (Detail Form)
Di file detail (misal `detail_pengajuan_baru.php`):

1. **Ambil Data & Cek Status**
   ```php
   $isRejected = isset($data['status_ticket']) && strtolower(trim($data['status_ticket'])) === 'ditolak';
   ```

2. **Include Komponen Info Penolakan**
   Taruh di bagian atas `<body>` atau container utama:
   ```php
   <?php include 'form_contents/components/rejection_status.php'; ?>
   ```
   Komponen membaca variabel `$data` dan `$conn`; siapkan keduanya sebelum include.

3. **Include Komponen Signature Canvas**
   Gunakan komponen bersama agar modal, canvas, upload template, dan event `ttdSaved` tidak diduplikasi:
   ```php
   <?php include 'form_contents/components/signature_canvas.php'; ?>
   ```
   Isi `#ttdRoleCode` ketika user memilih role. Setelah event `ttdSaved`, perbarui gambar, nama penanda tangan, dan tombol pada kolom role terkait.

4. **Aktifkan Tombol Tolak di Modal Parent**
   Inject script ini di `<head>` atau sebelum `</body>` untuk memberitahu `list_form.php` bahwa tombol "Tolak" boleh dimunculkan (jika user punya akses):
   ```php
   <script>
   window.addEventListener('load', function(){
       // Sesuaikan logic permission Anda ($canSignKabagIT, dll)
       var canReject = <?php echo ($canSignKabagIT || $canSignKadeptIT) && !$isRejected ? 'true':'false'; ?>;
       var ticket = '<?= htmlspecialchars($ticket) ?>';
       if (window.parent && window.parent.updateRejectButtonVisibility) {
           window.parent.updateRejectButtonVisibility(canReject, ticket);
       }
   });
   </script>
   ```

5. **Disable Tanda Tangan jika Ditolak**
   - **Di PHP:** Bungkus tombol sign dengan pengecekan `!$isRejected`.
   - **Di JS:** Tambahkan variabel `var isRejected = <?= $isRejected ? 'true':'false' ?>;` dan cek di fungsi `updateTtdArea` agar tombol sign tidak muncul lagi secara dinamis.

---

## 3. Tips Layout Tabel Tanda Tangan (Detail View)

Untuk layout tanda tangan 4 atau 5 kolom agar rapi dan responsif:

1.  **Row Container:** Gunakan `min-height` agar tetap tinggi meski belum ada gambar.
    ```html
    <tr style="height:auto; min-height:120px;">
    ```
2.  **Gambar TTD (PHP & JS):** Gunakan style object-fit.
    ```html
    <img src="..." style="max-width:100%; object-fit:contain; height:60px;">
    ```
3.  **Update TTD via AJAX:**
    Pastikan fungsi `updateTtdArea` di JavaScript juga meng-inject `<img>` dengan style yang sama.

---
*Dokumen ini dibuat untuk mempermudah pengembangan dan standarisasi form IT kedepannya.*
