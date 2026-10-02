<?php
require_once __DIR__ . '/_shared.php';

if (!coba1_has_menu_access('updatemultimenu')) {
    http_response_code(403);
    echo 'Anda tidak memiliki hak akses ke halaman ini.';
    exit;
}

$cari = trim($_GET['cari'] ?? '');
$keywords = coba1_parse_keywords($cari);
$selectedCollections = $_GET['collections'] ?? ['picking_process', 'cp_batch_transfer_lists'];
if (!is_array($selectedCollections)) {
    $selectedCollections = [$selectedCollections];
}
$selectedCollections = array_values(array_intersect($selectedCollections, ['picking_process', 'cp_batch_transfer_lists']));
if ($selectedCollections === []) {
    $selectedCollections = ['picking_process', 'cp_batch_transfer_lists'];
}
$autoMode = coba1_detect_mode($keywords[0] ?? '');
$results = [];

if ($keywords) {
    foreach ($keywords as $keyword) {
        $found = [];
        foreach ($selectedCollections as $collectionName) {
            $collection = coba1_mongo_collection($collectionName);
            $regex = coba1_regex($keyword, true);
            $cursor = $collection->find(['$or' => [
                ['baleno' => $regex],
                ['batchno' => $regex],
                ['packingno' => $regex],
            ]]);
            foreach ($cursor as $doc) {
                $found[] = $doc;
            }
        }
        $results[] = ['keyword' => $keyword, 'jumlah' => count($found), 'contoh' => $found[0] ?? null];
    }
}

coba1_layout_start('Multi Update');
?>
<style>
.c1-card{background:#fff;border:1px solid #dce4f0;border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,.08);padding:16px;margin-bottom:16px}
.c1-row{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start}
.c1-row>*{flex:1 1 240px}
.c1-textarea,.c1-select,.c1-input{width:100%;padding:10px;border:1px solid #cfd8dc;border-radius:8px}
.c1-btn{padding:10px 14px;border:none;border-radius:8px;background:#1976d2;color:#fff;cursor:pointer}
.c1-badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#e3f2fd;color:#0d47a1;font-size:12px}
.c1-status{margin-top:12px;padding:12px;border-radius:8px;background:#f8f9fa;border:1px dashed #cfd8dc}
</style>

<div class="c1-card">
  <form method="get" class="c1-row">
    <div>
      <label><b>Koleksi</b></label>
      <div class="d-flex flex-wrap gap-3 mt-2">
        <label class="mr-3"><input type="checkbox" name="collections[]" value="picking_process" <?= in_array('picking_process', $selectedCollections, true) ? 'checked' : '' ?>> Picking Process</label>
        <label><input type="checkbox" name="collections[]" value="cp_batch_transfer_lists" <?= in_array('cp_batch_transfer_lists', $selectedCollections, true) ? 'checked' : '' ?>> CP Batch Transfer</label>
      </div>
    </div>
    <div>
      <label><b>Masukkan Baleno / Batchno / Packingno</b></label>
      <textarea name="cari" class="c1-textarea" rows="3" placeholder="Pisahkan dengan koma atau baris baru"><?= htmlspecialchars($cari) ?></textarea>
    </div>
    <div style="max-width:180px;align-self:flex-end"><button type="submit" class="c1-btn">Cari</button></div>
  </form>
</div>

<?php if ($keywords): ?>
<div class="c1-card">
  <form action="../update_multi.php" method="post" id="updateForm">
    <input type="hidden" name="collections" value="<?= htmlspecialchars(implode(',', $selectedCollections)) ?>">
    <input type="hidden" name="update_mode" id="update_mode" value="<?= htmlspecialchars($autoMode) ?>">

    <div class="c1-row">
      <div>
        <label><b>Mode Update Berdasarkan</b></label>
        <select class="c1-select" id="update_mode_select">
          <option value="baleno" <?= $autoMode === 'baleno' ? 'selected' : '' ?>>Baleno</option>
          <option value="batchno" <?= $autoMode === 'batchno' ? 'selected' : '' ?>>Batch No</option>
          <option value="packingno" <?= $autoMode === 'packingno' ? 'selected' : '' ?>>Packing No</option>
        </select>
      </div>
    </div>

    <?php foreach ($results as $item): ?>
      <?php $uid = md5($item['keyword']); $contoh = (array) ($item['contoh'] ?? []); ?>
      <div class="c1-card" style="margin:12px 0 0">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <div><b><?= htmlspecialchars($item['keyword']) ?></b> <span class="c1-badge"><?= (int) $item['jumlah'] ?> data</span></div>
          <?php if ($item['jumlah'] > 0): ?><button type="button" class="btn btn-sm btn-outline-primary" data-toggle-target="<?= htmlspecialchars($uid) ?>">Tampilkan</button><?php endif; ?>
        </div>

        <?php if ($item['jumlah'] > 0): ?>
          <?php $fields = ['batchno', 'transferinbale', 'transferinitem', 'transferoutbale', 'transferoutitem', 'baleno', 'packingno']; if (count($selectedCollections) === 1 && in_array('picking_process', $selectedCollections, true)) { $fields[] = 'status'; $fields[] = 'balehdid'; } ?>
          <div id="<?= htmlspecialchars($uid) ?>" style="display:none">
            <table class="table table-sm table-bordered mb-0">
              <?php foreach ($fields as $field): ?>
                <tr>
                  <th style="width:30%">
                    <div class="d-flex justify-content-between align-items-center">
                      <span><?= htmlspecialchars($field) ?></span>
                      <label class="mb-0 small"><input type="checkbox" name="update_field[<?= htmlspecialchars($uid) ?>][]" value="<?= htmlspecialchars($field) ?>" checked> Edit</label>
                    </div>
                  </th>
                  <td><input type="text" class="form-control form-control-sm" name="<?= htmlspecialchars($field) ?>[<?= htmlspecialchars($uid) ?>]" value="<?= htmlspecialchars((string) ($contoh[$field] ?? '')) ?>"></td>
                </tr>
              <?php endforeach; ?>
            </table>
            <input type="hidden" name="keyword[]" value="<?= htmlspecialchars($item['keyword']) ?>">
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div class="c1-status" id="streamStatus">Siap update.</div>
    <button type="submit" class="c1-btn mt-2">Update Semua Data</button>
  </form>
</div>
<?php endif; ?>

<script>
(() => {
  const form = document.getElementById('updateForm');
  const modeSelect = document.getElementById('update_mode_select');
  const modeHidden = document.getElementById('update_mode');
  const statusBox = document.getElementById('streamStatus');

  if (modeSelect && modeHidden) {
    modeSelect.addEventListener('change', () => modeHidden.value = modeSelect.value);
  }

  async function submitStream(event) {
    event.preventDefault();
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

  if (form) form.addEventListener('submit', submitStream);
  document.querySelectorAll('[data-toggle-target]').forEach(button => button.addEventListener('click', () => {
    const target = document.getElementById(button.dataset.toggleTarget);
    if (target) target.style.display = target.style.display === 'none' ? 'block' : 'none';
  }));
})();
</script>
<?php coba1_layout_end(); ?>
