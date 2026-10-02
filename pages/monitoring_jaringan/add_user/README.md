# README - Fitur Add User Mikrotik

## 🎯 Quick Start

Fitur lengkap untuk mendaftarkan user internet baru ke Mikrotik telah selesai diimplementasikan dengan:

✅ **6 Section Form yang Terintegrasi**  
✅ **Real-time Preview & Validation**  
✅ **AJAX Live Interaction**  
✅ **Otomatis Comment & Queue Naming**  
✅ **Database Integration dengan Employee Data**  
✅ **Complete Mikrotik API Integration**

---

## 📁 File-File yang Sudah Dibuat/Diupdate

### 1. **add_user.php** (MAIN FORM)
- 6 section utama dalam 1 form responsif
- Select2 untuk live search di semua dropdown
- Real-time preview comment & queue name
- Validasi client-side sebelum submit
- Bootstrap 5 styling dengan card sections

### 2. **proses_add_user.php** (FORM PROCESSING)
- Fetch data karyawan dari database
- Validate input dan bandwidth options
- Create/Update ARP entry dengan make-static
- Create/Update DHCP Lease dengan make-static
- Create Simple Queue dengan bandwidth limit
- Add Firewall Address List (optional)
- Return success/error message

### 3. **get_dhcp_leases.php** (AJAX ENDPOINT)
- Get DHCP Leases berdasarkan IP yang dipilih
- Return JSON dengan lease details
- Used saat user pilih IP dari ARP

### 4. **make_static_arp.php** (AJAX ENDPOINT)
- Make ARP entry menjadi static
- Set/Update comment otomatis
- Return JSON response

### 5. **make_static_dhcp.php** (AJAX ENDPOINT)
- Make DHCP Lease menjadi static
- Set/Update comment otomatis
- Return JSON response

### 6. **add_firewall.php** (AJAX ENDPOINT)
- Add IP ke Firewall Address List
- Handle duplicate (update comment jika sudah ada)
- Return JSON response

### 7. **DOKUMENTASI.md** (COMPLETE REFERENCE)
- Full technical documentation
- API endpoints reference
- Database queries
- Format specifications
- Troubleshooting guide

---

## 🔄 Workflow / Alur Kerja

```
┌─────────────────────────────────────────────────────────┐
│                   FORM LOAD (add_user.php)              │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  1. AMBIL DATA DARI MIKROTIK & DATABASE:                │
│     ✓ Fetch ARP List (filter: tanpa comment + DHCP)    │
│     ✓ Fetch DHCP Leases list                            │
│     ✓ Fetch Firewall Address Lists                      │
│     ✓ Fetch Active Employees dari database              │
│                                                         │
│  2. RENDER FORM DENGAN 6 SECTION:                       │
│     ✓ Section 1: ARP List + Make Static ARP             │
│     ✓ Section 2: DHCP Leases + Make Static DHCP        │
│     ✓ Section 3: Firewall List + Add Firewall          │
│     ✓ Section 4: Queue Bandwidth Setup                  │
│     ✓ Section 5: Employee/Karyawan Selection            │
│     ✓ Section 6: Device Type Selection                  │
│                                                         │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│            USER INTERACTION & PREVIEW                   │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  STEP 1: Pilih IP dari ARP List                         │
│  → Trigger: $.ajax → get_dhcp_leases.php               │
│  → Result: DHCP Leases dropdown populate otomatis      │
│                                                         │
│  STEP 2: Pilih Employee & Device Type                   │
│  → Trigger: onchange event                             │
│  → Result: Update preview comment real-time             │
│  Format: "Departemen - Nama (Device Type)"              │
│                                                         │
│  STEP 3 (Optional): Click "Make Static ARP"             │
│  → Call: $.ajax → make_static_arp.php                  │
│  → Action: ARP → static, set comment                    │
│  → Button disabled setelah success                      │
│                                                         │
│  STEP 4 (Optional): Click "Make Static DHCP"            │
│  → Call: $.ajax → make_static_dhcp.php                 │
│  → Action: DHCP Lease → static, set comment             │
│  → Button disabled setelah success                      │
│                                                         │
│  STEP 5 (Optional): Click "OK (Add Firewall)"           │
│  → Call: $.ajax → add_firewall.php                     │
│  → Action: Add IP ke Firewall Address List              │
│  → Button disabled setelah success                      │
│                                                         │
│  STEP 6: Select Bandwidth Up & Down                     │
│                                                         │
│  STEP 7: Verify Preview & Submit Form                   │
│                                                         │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│         FORM SUBMIT → proses_add_user.php               │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ACTION 1: Fetch ARP Data                               │
│  → Get IP address dari ARP ID                           │
│  → Format target = IP/32                                │
│                                                         │
│  ACTION 2: Check Duplikat Queue                         │
│  → Query: /queue/simple/print (filter target)          │
│  → Jika ada → Error: Queue sudah ada                    │
│                                                         │
│  ACTION 3: Handle ARP Entry                             │
│  → Make Static (jika belum)                             │
│  → Set Comment = "Departemen - Nama (Device)"           │
│                                                         │
│  ACTION 4: Handle DHCP Lease                            │
│  → Create lease jika belum ada                          │
│  → Make Static                                          │
│  → Set Comment                                          │
│                                                         │
│  ACTION 5: Create Simple Queue                          │
│  → Name: Comment format                                 │
│  → Target: IP/32                                        │
│  → Max-Limit: bw_up/bw_down                             │
│                                                         │
│  ACTION 6: Add Firewall Address (jika dipilih)         │
│  → Add ke Firewall Address List                         │
│  → Set Comment                                          │
│  → Handle duplicate (update comment)                    │
│                                                         │
│  RESULT: Success message atau error                     │
│                                                         │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│          REDIRECT → add_user.php (show result)          │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  SUCCESS → Display:                                     │
│  ✓ Comment: Marketing - Lidiya Nurhalimah (Laptop)    │
│  ✓ IP: 192.168.1.100                                   │
│  ✓ Queue: Marketing - Lidiya Nurhalimah (Laptop)      │
│  ✓ Bandwidth: 512k/2M                                   │
│  ✓ Employee: Marketing - Lidiya Nurhalimah             │
│  ✓ Device: Laptop                                       │
│  ✓ Firewall: Allow-Internet (added)                     │
│                                                         │
│  ERROR → Display error message & suggest fix            │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## 🎨 Form Structure

### Section 1: ARP List (Primary)
```
┌─ Pilih IP dari ARP List ──────────────────┐
│ Dropdown: IP - MAC (Status)               │
│ [Search...]                               │
│                                           │
│ [Make Static] [Comment akan di-set otomatis]
└───────────────────────────────────────────┘
```

### Section 2: DHCP Server
```
┌─ DHCP Server Leases ──────────────────────┐
│ Dropdown: IP - Hostname                   │
│ (Auto-populate saat IP dipilih)           │
│                                           │
│ [Make Static] [Comment akan di-set otomatis]
└───────────────────────────────────────────┘
```

### Section 3: Firewall
```
┌─ Firewall Address List ───────────────────┐
│ Dropdown: Allow-Internet, Block-Torrent...│
│ [OK] [Tambah ke Firewall]                 │
└───────────────────────────────────────────┘
```

### Section 4: Queue Bandwidth
```
┌─ Queues / Bandwidth Limit ────────────────┐
│ Max Upload: [Unlimited ▼]                 │
│ Max Download: [10M ▼]                     │
│ Queue Name Preview: "Marketing - Lidiya..." │
└───────────────────────────────────────────┘
```

### Section 5: Employee (Sidebar)
```
┌─ Data Karyawan ───────────────────────────┐
│ [Marketing - Lidiya Nurhalimah]           │
│ (Auto select dept from database)          │
└───────────────────────────────────────────┘
```

### Section 6: Device Type (Sidebar)
```
┌─ Jenis Device ────────────────────────────┐
│ PC ▼                                      │
│ Options: Laptop, Tab, HP, Printer, Server │
│ Server, CCTV                              │
└───────────────────────────────────────────┘
```

### Preview (Sidebar)
```
┌─ Preview ─────────────────────────────────┐
│ Comment Format:                           │
│ Marketing - Lidiya Nurhalimah (Laptop)   │
└───────────────────────────────────────────┘
```

---

## 📊 Comment Format Reference

### Format Standar:
```
Departemen - Nama (Device Type)
```

### Contoh Real:
```
✓ Marketing - Lidiya Nurhalimah (Laptop)
✓ IT - Yusup Sam (Desktop)
✓ HRD - Muhammad Randani (HP)
✓ Finance - Rina Safitri (Printer)
✓ Operations - Budi Santoso (Server)
✓ Security - Ahmad Rifai (CCTV)
```

### Penggunaan:
- **ARP Comment** ← Comment field
- **DHCP Comment** ← Comment field
- **Queue Name** ← Nama queue
- **Firewall Comment** ← Comment field

---

## ⚙️ Bandwidth Options

| Value | Label |
|-------|-------|
| unlimited | Unlimited |
| 64k | 64 kbps |
| 128k | 128 kbps |
| 256k | 256 kbps |
| 384k | 384 kbps |
| 512k | 512 kbps |
| 768k | 768 kbps |
| 1M | 1 Mbps |
| 2M | 2 Mbps |
| 4M | 4 Mbps |
| 5M | 5 Mbps |
| 10M | 10 Mbps |
| 20M | 20 Mbps |
| 50M | 50 Mbps |
| 100M | 100 Mbps |

---

## 🔧 Device Type Options

| Value | Label |
|-------|-------|
| PC | PC |
| Laptop | Laptop |
| Tab | Tab |
| HP | HP |
| Printer | Printer |
| Server | Server |
| CCTV | CCTV |

---

## 📌 Key Features

### ✨ Real-Time Features
- **Live Search (Select2)** - Cari IP, DHCP, Employee, etc
- **Auto DHCP Populate** - DHCP terpopulasi saat IP dipilih
- **Live Comment Preview** - Update otomatis saat pilihan berubah
- **Queue Name Preview** - Lihat nama queue sebelum submit

### 🔐 Safety Features
- **Validation Client-Side** - Semua field harus diisi
- **Validation Server-Side** - Bandwidth whitelist
- **Duplicate Check** - Cek queue duplikat
- **XSS Prevention** - HTML encode semua output
- **CSRF Protection** - Session validation

### 🎯 Smart Features
- **Auto Department Fetch** - Ambil dept dari employee name
- **Auto Comment Generate** - Format: "Dept - Name (Device)"
- **Auto Queue Naming** - Sama dengan comment
- **Optional Features** - Make Static & Firewall bersifat optional
- **Button State** - Disabled setelah action success

---

## 🧪 Testing

Sebelum production, test hal-hal berikut:

1. **Form Load**
   - [ ] ARP list muncul dengan benar
   - [ ] Employee dropdown populated
   - [ ] Firewall list populated
   - [ ] Device type dropdown complete

2. **Interaction**
   - [ ] Pilih IP → DHCP leases populate
   - [ ] Pilih Employee → Comment preview update
   - [ ] Pilih Device → Comment preview update
   - [ ] Make Static ARP → Button disabled
   - [ ] Make Static DHCP → Button disabled
   - [ ] Add Firewall → Button disabled

3. **Form Submit**
   - [ ] Semua field required tervalidasi
   - [ ] Queue berhasil dibuat
   - [ ] Comment tersimpan di semua tempat
   - [ ] Firewall entry dibuat (jika dipilih)
   - [ ] Success message muncul

4. **Error Handling**
   - [ ] Queue duplicate error
   - [ ] ARP not found error
   - [ ] Database connection error
   - [ ] Mikrotik connection error

---

## 📞 Support

Jika ada issue atau pertanyaan, refer ke:
- **DOKUMENTASI.md** - Complete technical reference
- **Code Comments** - In-line explanations
- **Error Messages** - Descriptive error info

---

## 🚀 Version

**v1.0** - Initial Release  
**Date:** 2025-12-11  
**Status:** Production Ready

---

**Enjoy! 🎉**
