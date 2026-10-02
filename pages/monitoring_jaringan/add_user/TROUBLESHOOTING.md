# TROUBLESHOOTING & DEBUGGING GUIDE

## 🔧 Common Issues & Solutions

### Issue 1: Employee dropdown tidak muncul atau kosong

**Symptoms:**
- Employee select dropdown muncul kosong
- "Tidak ada data karyawan ditemukan"

**Causes:**
1. Database connection error
2. Table m_emp tidak ada atau struktur salah
3. Tidak ada employee dengan aktif = 1

**Solutions:**
```php
// Debug: Check query execution
$sqlEmp = "SELECT m_emp.nik, m_emp.nama_lengkap, m_bag.bagian, m_dept.dept 
FROM dbo.m_emp 
LEFT JOIN dbo.m_bag ON m_emp.id_bag = m_bag.id_bag 
LEFT JOIN dbo.m_dept ON m_emp.id_dept = m_dept.id_dept 
WHERE m_emp.aktif = 1 
ORDER BY m_emp.dept ASC, m_emp.nama_lengkap ASC";

$stmtEmp = sqlsrv_query($conn, $sqlEmp);

// Check result
if (!$stmtEmp) {
    echo "Query Error: " . print_r(sqlsrv_errors(), true);
}

// Check count
while ($row = sqlsrv_fetch_array($stmtEmp, SQLSRV_FETCH_ASSOC)) {
    echo "Employee found: " . $row['nama_lengkap'] . "<br>";
    $employeeList[] = $row;
}

echo "Total employees: " . count($employeeList);
```

---

### Issue 2: ARP List tidak muncul atau filter tidak bekerja

**Symptoms:**
- ARP dropdown kosong
- Muncul semua ARP (tidak di-filter)

**Causes:**
1. Mikrotik connection error
2. Filter status DHCP/DC tidak cocok
3. ARP tidak ada dengan status DHCP/DC

**Solutions:**
```php
// Debug: Check Mikrotik connection
try {
    $API = new RouterosAPI();
    if ($API->connect($mt_ip, $mt_user, $mt_pass)) {
        echo "✓ Mikrotik connected";
        
        // Get all ARP
        $arpAll = $API->comm('/ip/arp/print');
        echo "Total ARP: " . count($arpAll) . "<br>";
        
        // Check each ARP
        foreach ($arpAll as $arp) {
            echo "IP: " . ($arp['address'] ?? '-') . 
                 ", Status: " . ($arp['status'] ?? '-') . 
                 ", Comment: " . ($arp['comment'] ?? '-') . "<br>";
        }
        
        $API->disconnect();
    } else {
        echo "✗ Mikrotik connection failed";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}

// Check filter logic
if (empty($comment) && (strpos($status, 'DHCP') !== false || strpos($status, 'DC') !== false)) {
    echo "✓ Passed filter<br>";
} else {
    echo "✗ Failed filter - Comment: '$comment', Status: '$status'<br>";
}
```

---

### Issue 3: DHCP Leases tidak populate saat IP dipilih

**Symptoms:**
- DHCP dropdown tetap kosong
- get_dhcp_leases.php tidak return data

**Causes:**
1. AJAX request tidak terkirim
2. Endpoint get_dhcp_leases.php error
3. IP tidak ada di DHCP Leases

**Solutions:**

**A. Check AJAX Request:**
```javascript
// Add to console
$(document).ready(function() {
    $('#arp_id').change(function() {
        let selectedIp = $(this).find('option:selected').data('ip');
        console.log('IP dipilih:', selectedIp);
        
        $.ajax({
            url: 'get_dhcp_leases.php',
            method: 'GET',
            data: { ip: selectedIp },
            dataType: 'json',
            success: function(response) {
                console.log('Response:', response);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.log('Response:', xhr.responseText);
            }
        });
    });
});
```

**B. Debug get_dhcp_leases.php:**
```php
// Add logging
error_log('IP: ' . $_GET['ip']);

try {
    $API = new RouterosAPI();
    if (!$API->connect($mt_ip, $mt_user, $mt_pass)) {
        throw new Exception("Koneksi Mikrotik gagal");
    }

    $ip = $_GET['ip'];
    error_log('Searching for IP: ' . $ip);
    
    $leases = $API->comm('/ip/dhcp-server/lease/print', ['?address' => $ip]);
    error_log('Leases found: ' . count($leases));
    
    foreach ($leases as $lease) {
        error_log('Lease: ' . $lease['address']);
    }
    
    $API->disconnect();
} catch (Exception $e) {
    error_log('Error: ' . $e->getMessage());
}
```

---

### Issue 4: Make Static buttons tidak working

**Symptoms:**
- Button click tidak ada response
- Error dari server

**Causes:**
1. Session tidak valid
2. Endpoint error
3. Mikrotik API error
4. Invalid ARP/Lease ID

**Solutions:**

**A. Check Session:**
```php
// Di make_static_arp.php
session_start();
if (!isset($_SESSION['UserName'])) {
    error_log('Session invalid');
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
```

**B. Validate Input:**
```php
$arp_id = $_POST['arp_id'] ?? '';
error_log('ARP ID: ' . $arp_id);

if (!$arp_id) {
    error_log('ARP ID kosong');
    die(json_encode(['success' => false, 'message' => 'ARP ID tidak ditemukan']));
}
```

**C. Debug Mikrotik Command:**
```php
try {
    error_log('Connecting to Mikrotik...');
    $API = new RouterosAPI();
    
    if (!$API->connect($mt_ip, $mt_user, $mt_pass)) {
        throw new Exception("Koneksi Mikrotik gagal");
    }
    error_log('✓ Connected');
    
    error_log('Making ARP static...');
    $result = $API->comm('/ip/arp/make-static', ['.id' => $arp_id]);
    error_log('✓ Make static result: ' . print_r($result, true));
    
    error_log('Setting comment...');
    $result = $API->comm('/ip/arp/set', [
        '.id' => $arp_id,
        'comment' => $comment
    ]);
    error_log('✓ Set comment result: ' . print_r($result, true));
    
    $API->disconnect();
} catch (Exception $e) {
    error_log('✗ Error: ' . $e->getMessage());
}
```

---

### Issue 5: Queue tidak ter-create atau duplicate error

**Symptoms:**
- Error: "Queue untuk IP sudah ada!"
- Queue tidak muncul di Mikrotik

**Causes:**
1. Queue sudah ada dengan target yang sama
2. Target format salah
3. Queue name terlalu panjang

**Solutions:**

**A. Check Queue Existence:**
```php
$ip = '192.168.1.100';
$target = $ip . "/32";

// Check before create
$queueExist = $API->comm('/queue/simple/print', ['?target' => $target]);
error_log('Existing queues: ' . count($queueExist));

if (!empty($queueExist)) {
    foreach ($queueExist as $q) {
        error_log('Queue found: ' . ($q['name'] ?? ''));
    }
}
```

**B. Check Queue Name Length:**
```php
$comment = 'Marketing - Lidiya Nurhalimah (Laptop)';
$queueName = $comment;

// Mikrotik max name: 63 characters
if (strlen($queueName) > 63) {
    error_log('Queue name terlalu panjang: ' . strlen($queueName));
    $queueName = substr($queueName, 0, 60) . '...';
}

error_log('Queue name: ' . $queueName . ' (length: ' . strlen($queueName) . ')');
```

**C. Create Queue with Debug:**
```php
try {
    error_log('Creating queue...');
    $API->comm('/queue/simple/add', [
        'name'      => $queueName,
        'target'    => $target,
        'max-limit' => $bw_final
    ]);
    error_log('✓ Queue created');
} catch (Exception $e) {
    error_log('✗ Queue creation error: ' . $e->getMessage());
}
```

---

### Issue 6: Firewall entry tidak ter-create

**Symptoms:**
- Error saat add firewall
- Firewall list tidak berubah

**Causes:**
1. Firewall list tidak exist
2. IP format salah
3. Permission denied

**Solutions:**

**A. Check Firewall List:**
```php
// Get all firewall lists
$fwData = $API->comm('/ip/firewall/address-list/print');
error_log('Total firewall entries: ' . count($fwData));

foreach ($fwData as $fw) {
    error_log('List: ' . ($fw['list'] ?? '-') . 
              ', Address: ' . ($fw['address'] ?? '-'));
}

// Get unique lists
$fwLists = [];
foreach ($fwData as $fw) {
    if (!empty($fw['list'])) {
        $fwLists[] = $fw['list'];
    }
}
$fwLists = array_unique($fwLists);
error_log('Available lists: ' . implode(', ', $fwLists));
```

**B. Add with Debug:**
```php
$ip = '192.168.1.100';
$fw_list = 'Allow-Internet';
$comment = 'Marketing - Lidiya Nurhalimah (Laptop)';

try {
    error_log('Adding firewall: IP=' . $ip . ', List=' . $fw_list);
    
    $result = $API->comm('/ip/firewall/address-list/add', [
        'list' => $fw_list,
        'address' => $ip,
        'comment' => $comment
    ]);
    
    error_log('✓ Firewall added: ' . print_r($result, true));
} catch (Exception $e) {
    error_log('✗ Error: ' . $e->getMessage());
}
```

---

### Issue 7: Select2 tidak ter-load atau styling bermasalah

**Symptoms:**
- Dropdown plain, bukan Select2 style
- Search tidak working
- Placeholder tidak muncul

**Causes:**
1. Select2 CSS/JS tidak ter-load
2. jQuery versi conflict
3. Path CSS/JS salah

**Solutions:**

**A. Check CSS/JS Loaded:**
```html
<!-- Check di browser console -->
<script>
console.log('jQuery version:', $.fn.jquery);
console.log('Select2 version:', $.fn.select2 ? 'loaded' : 'NOT loaded');
</script>

<!-- Verify paths -->
<link rel="stylesheet" href="/gg_app/plugins/css/select2.min.css">
<link rel="stylesheet" href="/gg_app/plugins/css/select2-bootstrap.min.css">
<script src="/gg_app/plugins/js/jquery.min.js"></script>
<script src="/gg_app/plugins/js/select2.full.min.js"></script>
```

**B. Check Select2 Initialization:**
```javascript
$(document).ready(function() {
    console.log('Initializing Select2...');
    
    // Simple test
    $('#arp_id').select2({
        theme: 'bootstrap4',
        width: '100%'
    });
    
    console.log('✓ Select2 initialized');
    console.log('Dropdown hasClass .select2-hidden-accessible:', 
                $('#arp_id').hasClass('select2-hidden-accessible'));
});
```

---

## 🐛 Debug Mode

### Enable Logging

**add_user.php:**
```php
// Add di atas
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/xampp/htdocs/gg_app/logs/add_user.log');

// Log data
error_log('=== PAGE LOAD ===');
error_log('Employee count: ' . count($employeeList));
error_log('ARP count: ' . count($arpList));
error_log('DHCP count: ' . count($dhcpList));
error_log('Firewall lists: ' . implode(', ', $fwLists));
```

**proses_add_user.php:**
```php
error_log('=== FORM SUBMIT ===');
error_log('ARP ID: ' . $arp_id);
error_log('Employee: ' . $employee_select);
error_log('Device: ' . $device_type);
error_log('Bandwidth: ' . $bw_up . ' / ' . $bw_down);
error_log('Comment: ' . $comment);
```

**AJAX Endpoints:**
```php
// At start of each file
error_log('=== ' . __FILE__ . ' ===');
error_log('Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('Params: ' . json_encode($_POST ?? $_GET));
```

### Browser Console Debugging

```javascript
// Add to bottom of form
<script>
$(document).ready(function() {
    // Log all major events
    window.DEBUG = true;
    
    if (window.DEBUG) {
        console.log('🔍 DEBUG MODE ON');
        
        // Log ARP change
        $('#arp_id').on('change', function() {
            console.log('📍 ARP Changed:', $(this).val());
            console.log('📍 IP:', $(this).find('option:selected').data('ip'));
        });
        
        // Log DHCP dropdown change
        $('#dhcp_lease_id').on('change', function() {
            console.log('📋 DHCP Changed:', $(this).val());
        });
        
        // Log employee change
        $('#employee_select').on('change', function() {
            console.log('👤 Employee:', $(this).val());
            console.log('🏢 Dept:', $(this).find('option:selected').data('dept'));
        });
        
        // Log comment update
        $('#device_type').on('change', function() {
            console.log('💻 Device:', $(this).val());
            console.log('📝 Comment:', $('#commentPreview').text());
        });
    }
});
</script>
```

---

## 📊 Testing Checklist

```
FORM LOAD:
[ ] Employee dropdown populated
[ ] ARP list filtered correctly
[ ] DHCP leases loaded
[ ] Firewall lists loaded
[ ] All Select2 initialized

INTERACTION:
[ ] Select IP → DHCP populates
[ ] Select Employee → Comment updates
[ ] Select Device → Comment updates
[ ] Select Bandwidth → No error
[ ] Click Make Static ARP → Success
[ ] Click Make Static DHCP → Success
[ ] Click Add Firewall → Success

FORM SUBMIT:
[ ] Validation fires (empty field)
[ ] POST sent to proses_add_user.php
[ ] Queue created
[ ] Comment saved
[ ] Firewall entry created
[ ] Success message shown

ERROR SCENARIOS:
[ ] Invalid session → error
[ ] Queue duplicate → error
[ ] Mikrotik down → error
[ ] Invalid input → error
```

---

## 📞 Log Files Location

```
/xampp/htdocs/gg_app/logs/add_user.log
/xampp/htdocs/gg_app/logs/php_errors.log
```

---

## 🎯 Performance Debugging

```javascript
// Measure AJAX call time
console.time('get_dhcp_leases');
$.ajax({
    url: 'get_dhcp_leases.php',
    success: function() {
        console.timeEnd('get_dhcp_leases');
    }
});

// Measure form submit time
console.time('form_submit');
$('#formAddUser').submit(function() {
    console.timeEnd('form_submit');
});
```

---

Good luck! 🚀
