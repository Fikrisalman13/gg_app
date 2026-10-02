# Implementation Plan - Dynamic Document Reminder System V2

## Goal Description
Modernize the existing hardcoded document tracking system (Vehicle, Certificate, Contract) into a **Dynamic Document Builder**. This system will allow admins to create new document categories and define custom fields without coding, ensuring scalability and centralized management.

---

## Proposed Changes

### 1. Database Schema Redesign [SQL Server]

We will transition from separate tables (`dr_kontrak`, `dr_sertifikat`, `dr_surat_kendaraan`) to a dynamic architecture.

#### [NEW] `dr_categories`
- `id` (INT, PK, Identity)
- `category_name` (VARCHAR)
- `icon` (VARCHAR)
- `color` (VARCHAR)
- `reminder_interval` (INT)
- `created_by` (VARCHAR)
- `created_at` (DATETIME, default GETDATE())
- `updated_by` (VARCHAR)
- `updated_at` (DATETIME)

#### [NEW] `dr_fields`
- `id` (INT, PK, Identity)
- `category_id` (INT, FK to `dr_categories`)
- `field_label` (VARCHAR)
- `field_name` (VARCHAR)
- `field_type` (VARCHAR) - text, date, number, select, file
- `is_required` (BIT)
- `is_show_on_table` (BIT)
- `sort_order` (INT)
- `created_by` (VARCHAR)
- `created_at` (DATETIME, default GETDATE())

#### [NEW] `dr_documents`
- `id` (INT, PK, Identity)
- `category_id` (INT, FK to `dr_categories`)
- `expire_date` (DATE)
- `status` (VARCHAR)
- `bagian_id` (INT)
- `created_by` (VARCHAR)
- `created_at` (DATETIME, default GETDATE())
- `updated_by` (VARCHAR)
- `updated_at` (DATETIME)

> [!TIP]
> **Handling "Banyak Kolom"**: Dengan struktur EAV (`dr_doc_values`), sistem bisa menampung jumlah kolom yang tidak terbatas (10, 50, atau bahkan 100+) tanpa mengubah skema database. Untuk menjaga kenyamanan user (UX), sistem akan menggunakan **Grid Layout (Bootstrap Row/Col)** agar field bisa disusun secara menyamping (2 atau 3 kolom) jika jumlahnya banyak, tidak hanya memanjang ke bawah.

#### [NEW] `dr_doc_values`
- `id` (INT, PK, Identity)
- `document_id` (INT, FK to `dr_documents`)
- `field_id` (INT, FK to `dr_fields`)
- `field_value` (NVARCHAR(MAX))

#### [NEW] `dr_doc_files`
- `id` (INT, PK, Identity)
- `document_id` (INT, FK to `dr_documents`)
- `file_name` (VARCHAR)
- `file_path` (VARCHAR)

---

### 2. Core Dashboard Implementation

#### [MODIFY] [master_dokumen.php](file:///c:/xampp/htdocs/gg_app/pages/dokumen_pengingat/master_dokumen.php)
- Fetch all categories from `dr_categories`.
- Dynamically generate tabs based on available categories.
- Render DataTables for each category, fetching columns defined in `dr_fields` (where `is_show_on_table = 1`).
- Implement a single modal that renders form fields dynamically based on the selected category.

---

### 3. Unified CRUD Handler

#### [NEW] `services/DocumentService.php`
- A single class to handle `Save`, `Update`, `Delete`, and `GetDetail`.
- It will iterate through the `dr_fields` for a given category to process data and files correctly.

#### [NEW] `ajax_handler.php`
- A single entry point for all AJAX requests from the dynamic dashboard.

---

### 4. Dynamic Settings Page

#### [MODIFY] [pengaturan_pengingat.php](file:///c:/xampp/htdocs/gg_app/pages/dokumen_pengingat/pengaturan_pengingat.php)
- Replace the hardcoded interval inputs with a dynamic loop over `dr_categories`.
- Allow users to set the `reminder_interval` for each category individually.

---

### 5. Unified Reminder Engine

#### [MODIFY] `cron/reminder_runner.php` & `services/ReminderService.php`
- Refactor the logic to fetch all documents across all categories that are nearing their `expire_date` (based on the category's specific interval).
- Consolidate notification triggers (Email/WA) into a single optimized loop.

---

## Hybrid Strategy (Backward Compatibility)

As requested, we will **NOT migrate** existing data. Instead, we will implement a **Hybrid System**:

1.  **Legacy Data**: Documents in `dr_kontrak`, `dr_sertifikat`, and `dr_surat_kendaraan` will remain in their respective tables. The existing CRUD logic for these tables will be maintained for backward compatibility.
2.  **Dynamic Data**: Any **NEW** document categories (e.g., "Legal", "Asset", "HR") will use the new dynamic tables (`dr_categories`, `dr_documents`, `dr_doc_values`).

---

## Proposed Changes (Hybrid)

### 1. Dashboard & Navigation
#### [MODIFY] [index.php](file:///c:/xampp/htdocs/gg_app/pages/dokumen_pengingat/index.php)
- Add logic to fetch counts from the new `dr_documents` table.
- Dynamically render summary boxes for each new category alongside the legacy boxes.

#### [MODIFY] [master_dokumen.php](file:///c:/xampp/htdocs/gg_app/pages/dokumen_pengingat/master_dokumen.php)
- Keep the hardcoded tabs for Vehicle, Certificate, and Contract.
- Add logic to fetch all **other** categories from `dr_categories` and render them as additional tabs.
- The new tabs will use a **Unified Dynamic View** (DataTable and Form) that reads from the EAV structure.

### 2. Database Schema [SQL Server]
- Implement the same dynamic schema (`dr_categories`, `dr_fields`, `dr_documents`, `dr_doc_values`, `dr_doc_files`) to house only the new/dynamic document types.

---

## Verification Plan

### Manual Verification
- Verify that "Surat Kendaraan" still points to the old table and works as before.
- Create a new category "Izin Lingkungan" in the new system.
- Add a document to "Izin Lingkungan" and verify it appears as a new tab in `master_dokumen.php` and shows a new summary box in `index.php`.
