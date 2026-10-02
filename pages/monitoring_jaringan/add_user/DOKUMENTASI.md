# DOKUMENTASI LENGKAP - ADD USER MIKROTIK SYSTEM
## Fitur Pendaftaran User Internet Baru

---

## 📋 DAFTAR ISI
1. [Overview](#overview)
2. [Struktur File](#struktur-file)
3. [Alur Kerja](#alur-kerja)
4. [Format Comment & Queue Name](#format-comment--queue-name)
5. [Fitur-Fitur Detail](#fitur-fitur-detail)
6. [API Endpoints](#api-endpoints)
7. [Database Queries](#database-queries)

---

## Overview

Sistem ini memungkinkan admin untuk mendaftarkan user internet baru ke Mikrotik dengan flow yang terintegrasi meliputi:
- Pemilihan IP dari ARP List
- Konfigurasi DHCP Server Leases
- Setting Firewall Address List
- Pembuatan Simple Queue dengan bandwidth limit
- Manajemen data karyawan
- Format naming otomatis

### Fitur Utama:
✅ **6 Section dalam 1 Form:**
1. ARP List Selection dengan Make Static
2. DHCP Server Leases dengan Make Static
3. Firewall Address List Configuration
4. Simple Queue / Bandwidth Limit
5. Employee/Karyawan Selection
6. Device Type Selection

✅ **Auto-Generated:**
- Comment dengan format: `Departemen - Nama (Device Type)`
- Queue Name sama dengan Comment format
- Preview real-time sebelum submit

✅ **Interactive Features:**
- Select2 search di semua dropdown
- Real-time DHCP Lease population
- Button untuk Make Static (ARP & DHCP)
- Button untuk Add Firewall sebelum submit

---

## Struktur File

### File Utama:
```
/pages/monitoring_jaringan/add_user/
├── add_user.php                  ← Form UI + JavaScript interaktif
├── proses_add_user.php           ← Process final form submit
├── get_dhcp_leases.php           ← AJAX: Get DHCP Leases by IP
├── make_static_arp.php           ← AJAX: Make ARP Static
├── make_static_dhcp.php          ← AJAX: Make DHCP Static
└── add_firewall.php              ← AJAX: Add Firewall Address List
```

---

## Alur Kerja

### Step 1: Pilih IP dari ARP List
**File:** `add_user.php` (Section 1)
- Tampil dropdown ARP dengan filter:
  - ✅ Belum ada comment/tag
  - ✅ Status DHCP atau DC
- Setiap IP ditampilkan format: `IP - MAC (Status/Hostname/Comment)`
- Ada live search dengan Select2

**Process:**
```php
$arpAll = $API->comm('/ip/arp/print');

foreach ($arpAll as $arp) {
    $comment = $arp['comment'] ?? '';
    $status = $arp['status'] ?? '';
    
    // Filter: tanpa comment dan status DHCP/DC
    if (empty($comment) && (strpos($status, 'DHCP') !== false || strpos($status, 'DC') !== false)) {
        $arpList[] = $arp;
    }
}
```

### Step 2: DHCP Server Leases
**File:** `add_user.php` (Section 2) & `get_dhcp_leases.php`
- Dropdown otomatis terpopulasi saat IP dipilih
- Menampilkan: `IP - Hostname`
- Data diambil via AJAX

**AJAX Handler:**
```javascript
$('#arp_id').change(function() {
    let selectedIp = $(this).find('option:selected').data('ip');
    $.ajax({
        url: 'get_dhcp_leases.php',
        method: 'GET',
        data: { ip: selectedIp },
        dataType: 'json'
    });
});
```

### Step 3: Firewall Configuration
**File:** `add_user.php` (Section 3) & `add_firewall.php`
- Dropdown list firewall yang tersedia dari mikrotik
- Button "OK" untuk submit ke firewall (optional, sebelum form submit)
- Comment otomatis ditambahkan

### Step 4: Simple Queue Setup
**File:** `add_user.php` (Section 4)
- Dropdown Max Upload Limit (Unlimited, 64k-100M)
- Dropdown Max Download Limit (same options)
- Queue Name format = Comment otomatis

### Step 5: Data Karyawan
**File:** `add_user.php` (Section 5)
- Dropdown pilih dari tabel `m_emp` (hanya aktif = 1)
- Tampilan: `Departemen - Nama`
- Fetch dari database saat page load

**Query:**
```sql
SELECT m_emp.nik, m_emp.nama_lengkap, m_bag.bagian, m_dept.dept 
FROM dbo.m_emp 
LEFT JOIN dbo.m_bag ON m_emp.id_bag = m_bag.id_bag 
LEFT JOIN dbo.m_dept ON m_emp.id_dept = m_dept.id_dept 
WHERE m_emp.aktif = 1 
ORDER BY m_emp.dept ASC, m_emp.nama_lengkap ASC
```

### Step 6: Device Type
**File:** `add_user.php` (Section 6)
- Pilihan: PC, Laptop, Tab, HP, Printer, Server, CCTV
- Required field

### Step 7: Preview & Submit
- Preview comment format real-time
- Form submit ke `proses_add_user.php`
- Validasi semua field wajib diisi

---

## Format Comment & Queue Name

### Format Struktur:
```
Departemen - Nama (Device Type)
```

### Contoh:
```
Marketing - Lidiya Nurhalimah (Laptop)
IT - Yusup Sam (Desktop)
HRD - Muhammad Randani (HP)
Finance - Rina Safitri (Printer)
```

### Implementation (proses_add_user.php):
```php
$comment = trim(
    ($dept ? $dept : ($bagian ?: 'Tidak Ada')) .
    ' - ' . $nama_lengkap .
    ' (' . $device_type . ')'
);
```

### Penggunaan Comment di:
1. **ARP Entry** - Comment field
2. **DHCP Lease** - Comment field
3. **Queue Name** - Nama queue di simple queue
4. **Firewall Address** - Comment field

---

## Fitur-Fitur Detail

### Feature 1: Make Static ARP
**Button:** "Make Static (ARP)" di Section 1
**Endpoint:** `make_static_arp.php`

```javascript
$('#btnMakeStaticARP').click(function(e) {
    e.preventDefault();
    let arpId = $('#arp_id').val();
    let comment = $('#commentPreview').text();
    
    $.ajax({
        url: 'make_static_arp.php',
        method: 'POST',
        data: {
            arp_id: arpId,
            comment: comment
        }
    });
});
```

**Mikrotik Command:**
```php
$API->comm('/ip/arp/make-static', ['.id' => $arp_id]);
$API->comm('/ip/arp/set', [
    '.id' => $arp_id,
    'comment' => $comment
]);
```

### Feature 2: Make Static DHCP
**Button:** "Make Static (DHCP)" di Section 2
**Endpoint:** `make_static_dhcp.php`

```php
$API->comm('/ip/dhcp-server/lease/make-static', ['.id' => $lease_id]);
$API->comm('/ip/dhcp-server/lease/set', [
    '.id' => $lease_id,
    'comment' => $comment
]);
```

### Feature 3: Add Firewall
**Button:** "OK (Tambah ke Firewall)" di Section 3
**Endpoint:** `add_firewall.php`

```php
$API->comm('/ip/firewall/address-list/add', [
    'list' => $fw_list,
    'address' => $ip,
    'comment' => $comment
]);
```

### Feature 4: Preview Real-Time
**JavaScript:**
```javascript
function updateCommentPreview() {
    let deptBagian = $('#employee_select').find('option:selected').data('dept') || 'Departemen';
    let nama = $('#employee_select').val() || 'Nama';
    let device = $('#device_type').val() || 'Device Type';

    let comment = deptBagian + ' - ' + nama;
    if (device) {
        comment += ' (' + device + ')';
    }

    $('#commentPreview').text(comment);
    $('#queueNamePreview').text(comment);
}

$('#employee_select, #device_type').change(function() {
    updateCommentPreview();
});
```

---

## API Endpoints

### 1. GET /get_dhcp_leases.php
**Method:** GET
**Parameters:**
- `ip` (string) - IP Address untuk dicari

**Response:**
```json
{
    "leases": [
        {
            "id": "*1",
            "address": "192.168.1.100",
            "hostname": "PC-USER",
            "comment": ""
        }
    ]
}
```

### 2. POST /make_static_arp.php
**Parameters:**
- `arp_id` (string) - ID dari ARP entry
- `comment` (string) - Comment untuk di-set

**Response:**
```json
{
    "success": true,
    "message": "ARP berhasil dijadikan Static"
}
```

### 3. POST /make_static_dhcp.php
**Parameters:**
- `lease_id` (string) - ID dari DHCP Lease
- `comment` (string) - Comment untuk di-set

**Response:**
```json
{
    "success": true,
    "message": "DHCP Lease berhasil dijadikan Static"
}
```

### 4. POST /add_firewall.php
**Parameters:**
- `ip` (string) - IP Address
- `fw_list` (string) - Firewall List name
- `comment` (string) - Comment untuk di-set

**Response:**
```json
{
    "success": true,
    "message": "IP berhasil ditambahkan ke Firewall Address List"
}
```

### 5. POST /proses_add_user.php
**Parameters (Form):**
```
arp_id: string (required)
employee_select: string (required)
device_type: string (required)
bw_up: string (required)
bw_down: string (required)
fw_rule: string (optional)
```

**Actions di Mikrotik:**
1. Fetch ARP entry
2. Check duplikat queue
3. Make ARP static + set comment
4. Handle DHCP lease (create jika tidak ada, make static)
5. Create simple queue dengan max-limit
6. Add ke firewall address list (jika dipilih)

---

## Database Queries

### Query 1: Get All Active Employees (with Department & Section)
```sql
SELECT m_emp.nik, m_emp.nama_lengkap, m_bag.bagian, m_dept.dept 
FROM dbo.m_emp 
LEFT JOIN dbo.m_bag ON m_emp.id_bag = m_bag.id_bag 
LEFT JOIN dbo.m_dept ON m_emp.id_dept = m_dept.id_dept 
WHERE m_emp.aktif = 1 
ORDER BY m_emp.dept ASC, m_emp.nama_lengkap ASC
```

### Query 2: Get Employee Details by Name
```sql
SELECT m_bag.bagian, m_dept.dept 
FROM dbo.m_emp 
LEFT JOIN dbo.m_bag ON m_emp.id_bag = m_bag.id_bag 
LEFT JOIN dbo.m_dept ON m_emp.id_dept = m_dept.id_dept 
WHERE m_emp.nama_lengkap = ? AND m_emp.aktif = 1
```

---

## Validasi & Error Handling

### Validasi Input (Client-Side):
```javascript
if (!$('#arp_id').val()) {
    alert('Silakan pilih IP dari ARP List!');
    return false;
}

if (!$('#employee_select').val()) {
    alert('Silakan pilih nama karyawan!');
    return false;
}

if (!$('#device_type').val()) {
    alert('Silakan pilih jenis device!');
    return false;
}

if (!$('#bw_up').val() || !$('#bw_down').val()) {
    alert('Silakan pilih bandwidth limit!');
    return false;
}
```

### Validasi Input (Server-Side):
```php
$allowed_bw = ['unlimited','64k','128k','256k','384k','512k','768k','1M','2M','4M','5M','10M','20M','50M','100M'];
if (!in_array($bw_up, $allowed_bw)) $bw_up = 'unlimited';
if (!in_array($bw_down, $allowed_bw)) $bw_down = 'unlimited';
```

### Error Handling:
```php
try {
    $API->connect(...);
    // Process...
} catch (Exception $ex) {
    $_SESSION['error'] = "❌ Gagal: " . $ex->getMessage();
    header("Location: add_user.php");
    exit;
}
```

---

## Bandwidth Options

```php
$bwList = [
    "unlimited" => "Unlimited",
    "64k"  => "64 kbps",
    "128k" => "128 kbps",
    "256k" => "256 kbps",
    "384k" => "384 kbps",
    "512k" => "512 kbps",
    "768k" => "768 kbps",
    "1M"   => "1 Mbps",
    "2M"   => "2 Mbps",
    "4M"   => "4 Mbps",
    "5M"   => "5 Mbps",
    "10M"  => "10 Mbps",
    "20M"  => "20 Mbps",
    "50M"  => "50 Mbps",
    "100M" => "100 Mbps"
];
```

---

## Device Type Options

```php
$deviceTypes = [
    "PC" => "PC",
    "Laptop" => "Laptop",
    "Tab" => "Tab",
    "HP" => "HP",
    "Printer" => "Printer",
    "Server" => "Server",
    "CCTV" => "CCTV"
];
```

---

## Success Message Format

```
User berhasil didaftarkan:
✓ Comment/Nama: Marketing - Lidiya Nurhalimah (Laptop)
✓ IP Address: 192.168.1.100
✓ Queue Name: Marketing - Lidiya Nurhalimah (Laptop)
✓ Bandwidth: 512k/2M
✓ Karyawan: Marketing - Lidiya Nurhalimah
✓ Device: Laptop
✓ Firewall list 'Allow-Internet' ditambahkan.
```

---

## Troubleshooting

### Issue: DHCP Leases tidak muncul
**Solution:** 
- Pastikan IP sudah ada di DHCP Server Leases
- Check koneksi Mikrotik
- Verify parameter IP yang dikirim ke endpoint

### Issue: Queue duplicate error
**Solution:**
- Cek apakah queue sudah ada di `/queue/simple`
- Delete queue yang lama jika perlu

### Issue: Comment tidak tersimpan
**Solution:**
- Verify Mikrotik API memiliki permission untuk edit ARP/DHCP
- Check karakter spesial di comment (gunakan XSS prevention)

### Issue: Select2 tidak bekerja
**Solution:**
- Pastikan jQuery dan Select2 library sudah ter-load
- Check path CSS dan JS file

---

## Testing Checklist

- [ ] Form load dengan data karyawan lengkap
- [ ] ARP list muncul dengan filter correct (no comment + DHCP/DC)
- [ ] DHCP leases populate otomatis saat IP dipilih
- [ ] Make Static ARP button berfungsi
- [ ] Make Static DHCP button berfungsi
- [ ] Add Firewall button berfungsi
- [ ] Preview comment update real-time
- [ ] Form submit berhasil create queue
- [ ] Success message menampilkan semua info
- [ ] Dapat di-cancel dan kembali ke halaman utama

---

## Security Notes

✅ **XSS Prevention:**
- Gunakan `htmlspecialchars()` untuk output HTML
- Gunakan parameterized queries untuk SQL

✅ **CSRF Protection:**
- Check session sebelum process
- Validate user login

✅ **Input Validation:**
- Server-side validation untuk bandwidth
- Whitelist untuk device type

✅ **API Security:**
- Session validation di semua AJAX endpoints
- Return JSON response, bukan HTML

---

## Version History

**v1.0** - 2025-12-11
- Initial release dengan 6 section form
- AJAX real-time features
- Complete Mikrotik integration
- Comment format: `Departemen - Nama (Device Type)`

---

**Created:** 2025-12-11  
**Last Updated:** 2025-12-11  
**Status:** Production Ready
