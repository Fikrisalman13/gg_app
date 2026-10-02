<?php
require_once __DIR__ . '/_shared.php';

if (!coba1_has_menu_access('updatepicking')) {
    http_response_code(403);
    echo 'Anda tidak memiliki hak akses ke halaman ini.';
    exit;
}

$cari = trim($_GET['cari'] ?? '');
$keywords = coba1_parse_keywords($cari);
$canSearch = coba1_has_button_access('search');
$canShow = coba1_has_button_access('tampilkan');
$canUpdate = coba1_has_button_access('update-btn');
$onlySby = coba1_has_button_access('onlysby');
$autoMode = coba1_detect_mode($keywords[0] ?? '');
$allWarehouses = coba1_warehouses();
$fields = $canSearch ? ['baleno', 'batchno', 'packingno'] : ['batchno', 'packingno'];
$results = [];

if ($keywords) {
    $collection = coba1_mongo_collection('picking_process');
    foreach ($keywords as $keyword) {
        $regex = coba1_regex($keyword, $canSearch);
        $cursor = $collection->find(['$or' => array_map(static fn ($field) => [$field => $regex], $fields)]);
        $docs = iterator_to_array($cursor, false);
        $results[] = ['keyword' => $keyword, 'jumlah' => count($docs), 'contoh' => $docs[0] ?? null];
    }
}

coba1_layout_start('Update Picking Process');
?>
<style>
.c1-card{background:#fff;border:1px solid #dce4f0;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,.08);padding:16px;margin-bottom:16px}
.c1-row{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start}
.c1-row>*{flex:1 1 240px}
.c1-textarea,.c1-select{width:100%;padding:10px;border:1px solid #cfd8dc;border-radius:8px}
.c1-btn{padding:10px 14px;border:none;border-radius:8px;background:#1976d2;color:#fff;cursor:pointer}
.c1-badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#e3f2fd;color:#0d47a1;font-size:12px}
.c1-result{border:1px solid #e5eef9;border-radius:10px;padding:12px;margin-top:12px}
.c1-table{width:100%;border-collapse:collapse;font-size:13px}
.c1-table td{border:1px solid #e7edf5;padding:8px;vertical-align:top}
.c1-status{margin-top:12px;padding:12px;border-radius:8px;background:#f8f9fa;border:1px dashed #cfd8dc}
</style>

<div class="c1-card">
  <form method="get" class="c1-row">
    <div>
      <label><b>Masukkan<?php if ($canSearch): ?> Baleno /<?php endif; ?> Batchno / Packingno</b></label>
      <textarea name="cari" class="c1-textarea" rows="3" placeholder="Pisahkan dengan koma atau baris baru"><?= htmlspecialchars($cari) ?></textarea>
    </div>
    <div style="max-width:180px;align-self:flex-end">
      <button type="submit" class="c1-btn">Cari</button>
    </div>
  </form>
</div>

<?php if ($keywords): ?>
<div class="c1-card">
  <form action="../update_baleno.php" method="post" id="updateForm">
    <div class="c1-row">
      <div>
        <label><b>Mode Update Berdasarkan</b></label>
        <select name="update_mode" id="update_mode" class="c1-select" required>
          <?php if ($canSearch): ?><option value="baleno" <?= $autoMode === 'baleno' ? 'selected' : '' ?>>Baleno</option><?php endif; ?>
          <option value="batchno" <?= $autoMode === 'batchno' ? 'selected' : '' ?>>Batch No</option>
          <option value="packingno" <?= $autoMode === 'packingno' ? 'selected' : '' ?>>Packing No</option>
        </select>
      </div>
      <div>
        <label><b>Pilih Gudang</b></label>
        <select id="warehouse_select" class="c1-select">
          <option value="">-- Pilih Gudang --</option>
          <?php foreach ($allWarehouses as $warehouse): ?>
            <?php if ($onlySby && !in_array($warehouse['wrhscode'], ['172','172-A','172-B','172-C','173'], true)) continue; ?>
            <option value="<?= htmlspecialchars($warehouse['wrhsname']) ?>" data-wrhscode="<?= htmlspecialchars($warehouse['wrhscode']) ?>" data-wrhsrunid="<?= htmlspecialchars($warehouse['wrhsrunid']) ?>" data-wrhsid="<?= htmlspecialchars((string) $warehouse['wrhsid']) ?>"><?= htmlspecialchars($warehouse['wrhsname']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <input type="hidden" name="wrhsname" id="wrhsname">
    <input type="hidden" name="wrhscode" id="wrhscode">
    <input type="hidden" name="wrhsrunid" id="wrhsrunid">
    <input type="hidden" name="wrhsid" id="wrhsid">

    <?php foreach ($results as $item): ?>
      <?php $uid = md5($item['keyword']); ?>
      <div class="c1-result">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <div><b><?= htmlspecialchars($item['keyword']) ?></b> <span class="c1-badge"><?= (int) $item['jumlah'] ?> data</span></div>
          <?php if ($item['jumlah'] === 0): ?><span class="text-danger">No result</span><?php elseif ($canShow): ?><button type="button" class="btn btn-sm btn-outline-primary" data-toggle-target="<?= htmlspecialchars($uid) ?>">Tampilkan</button><?php endif; ?>
        </div>

        <?php if (!empty($item['contoh'])): ?>
          <input type="hidden" name="baleno[]" value="<?= htmlspecialchars((string) ($item['contoh']['baleno'] ?? '')) ?>">
          <input type="hidden" name="batchno[]" value="<?= htmlspecialchars((string) ($item['contoh']['batchno'] ?? '')) ?>">
          <input type="hidden" name="packingno[]" value="<?= htmlspecialchars((string) ($item['contoh']['packingno'] ?? '')) ?>">
          <input type="hidden" name="wrhsname[]" value="">
          <input type="hidden" name="wrhscode[]" value="">
          <input type="hidden" name="wrhsrunid[]" value="">
          <input type="hidden" name="wrhsid[]" value="">
          <div id="<?= htmlspecialchars($uid) ?>" style="display:none">
            <table class="c1-table">
              <?php foreach ($item['contoh'] as $field => $value): if ($field === '_id') continue; ?>
                <tr><td style="width:30%"><b><?= htmlspecialchars($field) ?></b></td><td><?= htmlspecialchars(is_scalar($value) ? (string) $value : json_encode($value)) ?></td></tr>
              <?php endforeach; ?>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div class="c1-status" id="streamStatus">Siap update.</div>
    <?php if ($canUpdate): ?><button type="submit" class="c1-btn mt-2">Update Semua Data</button><?php endif; ?>
  </form>
</div>
<?php endif; ?>

<script>
(() => {
  const form = document.getElementById('updateForm');
  const select = document.getElementById('warehouse_select');
  const statusBox = document.getElementById('streamStatus');

  function syncWarehouse() {
    const option = select && select.selectedOptions && select.selectedOptions[0];
    const value = option ? option.value : '';
    const code = option ? option.dataset.wrhscode || '' : '';
    const runid = option ? option.dataset.wrhsrunid || '' : '';
    const id = option ? option.dataset.wrhsid || '' : '';
    document.querySelectorAll('input[name="wrhsname[]"]').forEach(input => input.value = value);
    document.querySelectorAll('input[name="wrhscode[]"]').forEach(input => input.value = code);
    document.querySelectorAll('input[name="wrhsrunid[]"]').forEach(input => input.value = runid);
    document.querySelectorAll('input[name="wrhsid[]"]').forEach(input => input.value = id);
    const map = { wrhsname: value, wrhscode: code, wrhsrunid: runid, wrhsid: id };
    ['wrhsname','wrhscode','wrhsrunid','wrhsid'].forEach(name => { const el = document.getElementById(name); if (el) el.value = map[name]; });
  }

  async function submitStream(event) {
    event.preventDefault();
    syncWarehouse();
    statusBox.textContent = 'Kirim data...';
    const response = await fetch(form.action, { method: 'POST', body: new FormData(form) });
    if (!response.ok || !response.body) { statusBox.textContent = 'Gagal update.'; return; }
    const reader = response.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let buffer = '';
    while (true) {
      const { value, done } = await reader.read();
      buffer += decoder.decode(value || new Uint8Array(), { stream: !done });
      let boundary = buffer.indexOf('\n\n');
      while (boundary !== -1) {
        const chunk = buffer.slice(0, boundary);
        buffer = buffer.slice(boundary + 2);
        const line = chunk.split('\n').find(row => row.startsWith('data:'));
        if (line) {
          try {
            const payload = JSON.parse(line.slice(5).trim());
            statusBox.textContent = payload.message + ' (' + payload.percent + '%)';
            if (payload.percent >= 100) setTimeout(() => window.location.reload(), 1400);
          } catch (error) {}
        }
        boundary = buffer.indexOf('\n\n');
      }
      if (done) break;
    }
  }

  if (select) select.addEventListener('change', syncWarehouse);
  if (form) form.addEventListener('submit', submitStream);
  syncWarehouse();
  document.querySelectorAll('[data-toggle-target]').forEach(button => button.addEventListener('click', () => {
    const target = document.getElementById(button.dataset.toggleTarget);
    if (target) target.style.display = target.style.display === 'none' ? 'block' : 'none';
  }));
})();
</script>
<?php coba1_layout_end(); ?>
