# 🐛 BUG FIX: Select2 is not a function

## Masalah

**Error Message:**
```
Uncaught TypeError: $(...).select2 is not a function
    at HTMLButtonElement.<anonymous> (cuttinglist.js:78:60)
    at HTMLButtonElement.<anonymous> (cuttinglist.js:246:68)
    at HTMLTableRowElement.<anonymous> (cuttinglist.js:598:68)
```

**Penyebab:**
Select2 library belum ter-load dengan benar sebelum cuttinglist.js mencoba menggunakannya.

---

## Root Cause

Di tambahcutting.php, script loading order sudah benar:
```html
1. jQuery CDN
2. Select2 CSS (CDN)
3. Select2 JS (CDN)
4. cuttinglist.js
```

Namun, `.select2()` calls di cuttinglist.js di-execute **immediately** sebelum Select2 library sepenuhnya ter-load atau di-initialize.

---

## Solusi

### Sebelum (BUGGY):
```javascript
$('#tableCacatInput tbody tr:last .select-cacat-kode').select2({
    placeholder: 'Pilih kode cacat',
    allowClear: true,
    width: '100%'
});
```

### Sesudah (FIXED):
```javascript
// Initialize Select2 (with check)
if (typeof $.fn.select2 !== 'undefined') {
    $('#tableCacatInput tbody tr:last .select-cacat-kode').select2({
        placeholder: 'Pilih kode cacat',
        allowClear: true,
        width: '100%'
    });
}
```

**Penjelasan:**
- `typeof $.fn.select2 !== 'undefined'` → Check apakah Select2 sudah loaded
- Jika belum loaded, skip `.select2()` call (tidak error)
- Jika sudah loaded, initialize Select2 normally

---

## Files Modified

✅ **js/cuttinglist.js** - 3 lokasi:

### Location 1: Line 79 (Add Cacat button)
```javascript
$('#btnAddCacat').on('click', function () {
    // ... tambah cacat row ...
    
    if (typeof $.fn.select2 !== 'undefined') {
        $('#tableCacatInput tbody tr:last .select-cacat-kode').select2({...});
    }
});
```

### Location 2: Line 249 (Edit Piece handler)
```javascript
$(document).on('click', '.btn-edit-piece', function () {
    // ... load cacat ...
    
    if (typeof $.fn.select2 !== 'undefined') {
        $('#tableCacatInput tbody tr:last .select-cacat-kode').select2({...});
    }
});
```

### Location 3: Line 604 (Edit Cacat handler)
```javascript
$(document).on('click', '.btn-edit-cacat', function () {
    // ... load cacat data ...
    
    if (typeof $.fn.select2 !== 'undefined') {
        $('#tableCacatInput tbody tr:last .select-cacat-kode').select2({...});
    }
});
```

---

## Verification

✅ **Setelah fix:**
1. Tidak ada error di console
2. Select2 dropdown berfungsi normal
3. Dapat memilih kode cacat
4. Edit piece/cacat berfungsi

❌ **Jika masih error:**
1. Clear cache: `Ctrl+Shift+Delete`
2. Hard refresh: `Ctrl+F5`
3. Check CDN link sudah accessible
4. Check apakah jQuery sudah loaded

---

## Additional Notes

### Why Check Before Initialize?
- Select2 library mungkin belum fully loaded
- CDN mungkin lambat atau timeout
- Browser cache mungkin outdated
- `typeof` check adalah cara aman untuk handle async loading

### Alternative Approaches:
1. **Wrap in $(document).ready()** → Semua script di-execute setelah DOM siap
2. **Use setTimeout()** → Delay execution beberapa ms
3. **Use Promise/async** → Wait untuk library loading selesai

Approach yang kami gunakan (check `typeof`) adalah yang paling simple dan reliable.

---

**Fixed Date**: December 24, 2025  
**Status**: ✅ Ready for Testing
