# SQL Migration & Deployment Guide

## 🗄️ Database Migration - REQUIRED

### Step 1: Backup Database
```sql
-- Create backup (recommended before any schema changes)
-- Using SQL Server Management Studio or similar
-- File > Backup Database
```

### Step 2: Execute Migration
**Copy and run this exact SQL query:**

```sql
ALTER TABLE cl_cutting_process
ADD uom_hasil_cutting CHAR(1) DEFAULT 'M',
    hasil_cutting_conv DECIMAL(10,3) DEFAULT 0.000,
    uom_conversi CHAR(1) DEFAULT 'M';
```

### Step 3: Verify Migration
**Run this query to confirm columns were added:**

```sql
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'cl_cutting_process'
ORDER BY ORDINAL_POSITION;
```

**Expected output should include:**
```
COLUMN_NAME              │ DATA_TYPE      │ CHARACTER_MAX │ COLUMN_DEFAULT
─────────────────────────┼────────────────┼───────────────┼─────────────────
...existing columns...   │ ...            │ ...           │ ...
uom_hasil_cutting        │ char           │ 1             │ ('M')
hasil_cutting_conv   │ decimal        │ NULL          │ ((0.000))
uom_conversi             │ char           │ 1             │ ('M')
```

### Step 4: Update Existing Data (Optional)
**If you have existing data in cl_cutting_process:**

```sql
UPDATE cl_cutting_process
SET uom_hasil_cutting = 'M',
    hasil_cutting_conv = hasil_cutting,
    uom_conversi = 'M'
WHERE uom_hasil_cutting IS NULL
   OR hasil_cutting_conv = 0.000
   OR uom_conversi IS NULL;
```

### Step 5: Verify Data Update

```sql
SELECT COUNT(*) as total_records,
       COUNT(CASE WHEN hasil_cutting_conv = 0 THEN 1 END) as null_conversi,
       COUNT(CASE WHEN uom_hasil_cutting IS NULL THEN 1 END) as null_uom_hasil
FROM cl_cutting_process;
```

---

## 📋 File Deployment Checklist

### Files to Deploy

```
Your Server Directory: c:\xampp\htdocs\gg_app\pages\cuttinglist\

☐ 1. delete_header.php (NEW FILE)
    Location: cuttinglist/delete_header.php
    Action: UPLOAD new file
    Size: ~2.5 KB

☐ 2. process_cutting.php (MODIFIED)
    Location: cuttinglist/process_cutting.php
    Action: REPLACE existing file
    Changes: +15 lines for conversion columns
    Backup: Save original first

☐ 3. index.php (MODIFIED)
    Location: cuttinglist/index.php
    Action: REPLACE existing file
    Changes: +75 lines (delete handler + sweet alert)
    Backup: Save original first

☐ 4. detail_cutting.php (MODIFIED)
    Location: cuttinglist/detail_cutting.php
    Action: REPLACE existing file
    Changes: +8 lines (conversion display)
    Backup: Save original first

☐ 5. edit_detail.php (MODIFIED)
    Location: cuttinglist/edit_detail.php
    Action: REPLACE existing file
    Changes: +40 lines (sweet alert integration)
    Backup: Save original first
```

### Documentation Files (Reference Only)

```
☐ QUICK_START.md (NEW)
☐ IMPLEMENTATION_COMPLETE.md (NEW)
☐ DATABASE_SCHEMA_UPDATE_CONVERSION.md (NEW)
☐ README_UPDATE_2025.md (NEW)
☐ VISUAL_SUMMARY.md (NEW)
☐ SQL_DEPLOYMENT_GUIDE.md (THIS FILE)
```

---

## 🔧 Complete Deployment Steps

### Phase 1: Preparation (5 min)

1. **Backup Database**
   ```
   - Open SQL Server Management Studio
   - Right-click database → Tasks → Backup
   - Save to safe location
   ```

2. **Backup Current Files**
   ```
   - Copy these files to backup folder:
     * process_cutting.php
     * index.php
     * detail_cutting.php
     * edit_detail.php
   ```

3. **Test Environment (Optional)**
   ```
   - If available, test on dev/test server first
   - Then deploy to production
   ```

### Phase 2: Database Migration (5 min)

1. **Run SQL Migration**
   ```sql
   ALTER TABLE cl_cutting_process
   ADD uom_hasil_cutting CHAR(1) DEFAULT 'M',
       hasil_cutting_conv DECIMAL(10,3) DEFAULT 0.000,
       uom_conversi CHAR(1) DEFAULT 'M';
   ```

2. **Verify Columns Exist**
   ```sql
   SELECT TOP 1 * FROM cl_cutting_process;
   -- Should see 3 new columns at the end
   ```

3. **Update Existing Data (Optional)**
   ```sql
   UPDATE cl_cutting_process
   SET uom_hasil_cutting = 'M',
       hasil_cutting_conv = hasil_cutting,
       uom_conversi = 'M'
   WHERE hasil_cutting_conv = 0.000;
   ```

### Phase 3: File Deployment (10 min)

Using FTP/File Manager:

1. **Upload delete_header.php (NEW)**
   ```
   From: delete_header.php
   To: /cuttinglist/delete_header.php
   Overwrite: No (new file)
   ```

2. **Replace process_cutting.php**
   ```
   From: process_cutting.php
   To: /cuttinglist/process_cutting.php
   Overwrite: Yes
   ```

3. **Replace index.php**
   ```
   From: index.php
   To: /cuttinglist/index.php
   Overwrite: Yes
   ```

4. **Replace detail_cutting.php**
   ```
   From: detail_cutting.php
   To: /cuttinglist/detail_cutting.php
   Overwrite: Yes
   ```

5. **Replace edit_detail.php**
   ```
   From: edit_detail.php
   To: /cuttinglist/edit_detail.php
   Overwrite: Yes
   ```

### Phase 4: Post-Deployment (10 min)

1. **Clear Browser Cache**
   ```
   - Ctrl + Shift + Delete (or Cmd + Shift + Delete on Mac)
   - Clear all cache
   - Or use Incognito/Private window
   ```

2. **Verify Deployment**
   ```
   - Open index.php in browser
   - Should see:
     * Delete button present
     * No console errors (F12 → Console)
     * Sweet Alert CSS loaded
   ```

3. **Test Functionality**
   ```
   - Test delete unprocessed
   - Test process button
   - Test yard conversion display
   - Check redirects work
   ```

---

## ⚠️ Troubleshooting Deploy Issues

### Issue: Delete button not working

**Check:**
1. delete_header.php uploaded successfully
2. File permissions correct (644 or 755)
3. Browser console (F12) for JavaScript errors
4. Network tab shows delete_header.php being called

**Fix:**
```
- Re-upload delete_header.php
- Check file permissions
- Clear browser cache
- Try different browser
```

### Issue: Sweet Alerts not showing

**Check:**
1. CDN links accessible (check network tab)
2. JavaScript enabled in browser
3. No console errors (F12 → Console)
4. jQuery loaded before Sweet Alert

**Fix:**
```
- Check internet connection
- Try different browser
- Clear browser cache
- Check CDN status online
```

### Issue: Conversion not working

**Check:**
1. Database columns exist (run verification query)
2. process_cutting.php deployed correctly
3. UOM CP and Type Counter values correct
4. Detail view shows conversion data

**Fix:**
```
- Run verification SQL query
- Re-upload process_cutting.php
- Check database data
- Review conversion logic
```

### Issue: Yard results showing as meter

**Check:**
1. hasil_cutting_conv column has values
2. detail_cutting.php displays conversi values
3. UOM CP = Y and Type Counter = M

**Fix:**
```
- Verify conversion columns exist
- Re-upload detail_cutting.php
- Process a new cutting record
- Check database directly
```

---

## 🧪 Post-Deployment Testing

### Test 1: Basic Functionality

```
Steps:
1. Go to index.php → Status: Unprocessed
2. Create new cutting (if none exist)
3. Select cutting in list
4. Click "Delete" button
5. Confirm in Sweet Alert dialog

Expected:
✓ Sweet Alert appears with warning
✓ Clicking "Hapus" deletes all data
✓ Redirects to index.php automatically
✓ List refreshes and data is gone
```

### Test 2: Yard Conversion

```
Steps:
1. Create cutting: UOM CP=Yard, Type Counter=M
2. Add piece: std=30 yard
3. Process cutting
4. Go to detail tab
5. Click on piece
6. View "Proses Cutting" table

Expected:
✓ Results show "Y" suffix (e.g., "30.3 Y")
✓ Database has converted values
✓ Both original and converted stored
```

### Test 3: Permission Controls

```
Steps:
1. Create cutting and process it
2. Go to detail tab
3. Try to click edit/delete buttons

Expected:
✓ Edit/Delete buttons are disabled
✓ "Read-Only" badge shows
✓ Cannot click or submit
✓ Sweet Alert prevents action
```

### Test 4: Redirect After Operations

```
Steps:
1. Process a cutting
2. Delete something
3. Save changes

Expected:
✓ All operations auto-redirect to index.php
✓ Data refreshed in list view
✓ No manual page reload needed
```

---

## 📞 Support & Rollback

### If Something Goes Wrong

**Quick Rollback:**
```
1. Restore database from backup
2. Replace modified files with originals
3. Clear browser cache
4. Reload page
5. Should be back to working state
```

**Getting Help:**
- Check console errors (F12)
- Review deployment checklist
- Re-read documentation
- Check file permissions
- Verify database migration ran

---

## ✅ Deployment Completion Checklist

```
BEFORE DEPLOYMENT:
☐ Backup database taken
☐ Current files backed up
☐ Deployment plan understood
☐ All files ready to upload

DURING DEPLOYMENT:
☐ SQL migration executed
☐ Columns verified in database
☐ 5 PHP files deployed
☐ File permissions set correct

AFTER DEPLOYMENT:
☐ Browser cache cleared
☐ Page loads without errors
☐ Console has no JS errors
☐ Sweet Alert CSS visible
☐ Delete button present
☐ All 4 tests passed

GO LIVE:
☐ Users notified of changes
☐ Training completed
☐ Support ready
☐ Monitoring active
```

---

## 📊 Deployment Summary

| Phase | Task | Duration | Status |
|-------|------|----------|--------|
| Prep | Backup & Verification | 5 min | ☐ |
| DB | SQL Migration | 5 min | ☐ |
| Files | Deploy 5 PHP files | 10 min | ☐ |
| Post | Testing & Verification | 10 min | ☐ |
| **TOTAL** | **Complete Deployment** | **30 min** | ☐ |

---

## 🎯 Success Criteria

Deployment is successful when:

✅ All files uploaded without error
✅ Database columns exist and have default values
✅ Delete button works for unprocessed data
✅ Sweet Alerts display properly
✅ Processed data cannot be deleted
✅ Yard conversions display correctly
✅ All redirects work as expected
✅ No console errors in browser
✅ All 4 test scenarios pass

---

**Last Updated:** December 22, 2025
**Version:** 1.0
**Status:** Ready for Production Deployment ✅
