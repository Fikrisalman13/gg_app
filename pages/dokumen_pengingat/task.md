# Task List - Dynamic Document Reminder System V2

- `[x]` **Phase 1: Database Setup**
    - `[x]` Create `dr_categories` table
    - `[x]` Create `dr_fields` table
    - `[x]` Create `dr_documents` table
    - `[x]` Create `dr_doc_values` table
    - `[x]` Create `dr_doc_files` table
    - `[x]` Insert seed data for testing (e.g., "Legal" category)

- `[x]` **Phase 2: Admin UI (Category & Field Management)**
    - `[x]` Create `manage_categories.php` (full CRUD - real database)
    - `[x]` Create `manage_fields.php` (full CRUD - real database)
    - `[x]` Add "Settings" link in sidebar/dashboard

- `[x]` **Phase 3: Hybrid Dashboard (`master_dokumen.php`)**
    - `[x]` Fetch dynamic categories alongside legacy ones
    - `[x]` Render dynamic tabs and DataTables
    - `[x]` Implement Dynamic Form Modal (JS based on field definitions)

- `[x]` **Phase 4: Backend Logic (CRUD)**
    - `[x]` Create `ajax_handler.php` for unified dynamic form, save, and delete logic
    - `[x]` Implement dynamic rendering of DataTables in `master_dokumen.php`

- `[x]` **Phase 5: Unified Dashboard (`index.php`)**
    - `[x]` Aggregate counts from both legacy and dynamic tables
    - `[x]` Render summary boxes for all active categories

- `[x]` **Phase 6: Reminder Engine**
    - `[x]` Refactor `reminder_runner.php` to support dynamic categories
    - `[x]` Add dynamic fields into database
