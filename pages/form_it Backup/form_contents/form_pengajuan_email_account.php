<?php
// Form Pengajuan Email Account
if (!isset($conn) || $conn === false) {
	$koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
	if (file_exists($koneksiPath)) {
		require_once $koneksiPath;
	} else {
		$fallbackPath = dirname(dirname(dirname(__DIR__))) . '/koneksi.php';
		if (file_exists($fallbackPath)) {
			require_once $fallbackPath;
		}
	}
}

$username   = htmlspecialchars($_SESSION['NamaLengkap'] ?? '');
$jabatan    = '';
$departemen = '';
$bagian     = '';

if (!empty($username) && isset($conn) && $conn !== false) {
	$sql = "SELECT m_jab.jabatan, m_dept.dept, m_bag.bagian
			FROM dbo.m_emp
			LEFT JOIN dbo.m_jab ON m_emp.id_jab = m_jab.id_jab
			LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
			LEFT JOIN dbo.m_bag ON m_subbag.id_bag = m_bag.id_bag
			LEFT JOIN dbo.m_dept ON m_bag.id_dept = m_dept.id_dept
			WHERE m_emp.nama_lengkap = ?";
	$stmt = sqlsrv_query($conn, $sql, [$username]);
	if ($stmt !== false) {
		$emp = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
		if ($emp) {
			$jabatan    = $emp['jabatan'] ?? '';
			$departemen = $emp['dept'] ?? '';
			$bagian     = $emp['bagian'] ?? '';
		}
		sqlsrv_free_stmt($stmt);
	}
}
?>
<form method="POST" id="formPengajuanEmailAccount" style="margin-bottom:0;">
	<input type="hidden" name="form_type" value="pengajuan_email_account">
	<?php if (isset($_GET['edit']) && $_GET['edit'] == 1 && isset($_GET['ticket'])): ?>
	<input type="hidden" name="ticket" value="<?= htmlspecialchars($_GET['ticket']) ?>">
	<input type="hidden" name="action" value="update">
	<?php endif; ?>
	<div class="row">
		<div class="col-md-6">
			<div class="form-group mb-2">
				<label class="mb-1" style="font-size:.875rem;font-weight:600;">Nama Pemohon</label>
				<input type="text" name="nama_pemohon" class="form-control form-control-sm" value="<?= $username ?>" readonly required>
			</div>
		</div>
		<div class="col-md-6">
			<div class="form-group mb-2">
				<label class="mb-1" style="font-size:.875rem;font-weight:600;">Jabatan</label>
				<input type="text" name="jabatan" class="form-control form-control-sm" value="<?= htmlspecialchars($jabatan) ?>" readonly>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-4">
			<div class="form-group mb-2">
				<label class="mb-1" style="font-size:.875rem;font-weight:600;">Tanggal Pengajuan</label>
				<input type="date" name="tgl_pengajuan" class="form-control form-control-sm tgl-pengajuan-email" readonly>
			</div>
		</div>
		<div class="col-md-4">
			<div class="form-group mb-2">
				<label class="mb-1" style="font-size:.875rem;font-weight:600;">Departemen</label>
				<input type="text" name="departemen" class="form-control form-control-sm" value="<?= htmlspecialchars($departemen) ?>" readonly>
			</div>
		</div>
		<div class="col-md-4">
			<div class="form-group mb-2">
				<label class="mb-1" style="font-size:.875rem;font-weight:600;">Area (Bagian)</label>
				<input type="text" name="area" class="form-control form-control-sm" value="<?= htmlspecialchars($bagian) ?>" readonly>
			</div>
		</div>
	</div>
	<hr class="my-2">
	<div class="form-group mb-2">
		<label class="mb-1" style="font-size:.875rem;font-weight:600;">Alamat E-mail <i>(diisi oleh IT)</i>:</label>
		<div class="input-group">
			<input type="text" name="email_user" class="form-control form-control-sm" placeholder="" style="max-width:220px;">
			<div class="input-group-append">
				<span class="input-group-text" style="background:#f8f9fa;">@sumtex.co.id</span>
			</div>
		</div>
	</div>
	<div class="form-group mb-2">
		<label style="font-size:.875rem;font-weight:600;">Mengajukan permintaan untuk : <span class="text-danger">*</span></label>
		<div class="border rounded p-3">
			<div class="form-check mb-2">
				<input class="form-check-input" type="checkbox" name="request_email_account" id="requestEmailAccount" value="1">
				<label class="form-check-label" for="requestEmailAccount">
					<strong>Email Account</strong>
				</label>
			</div>
			<div class="form-check mb-2">
				<input class="form-check-input" type="checkbox" name="request_change_access" id="requestChangeAccess" value="1">
				<label class="form-check-label" for="requestChangeAccess">
					<strong>Perubahan Hak Akses Email Account</strong>
				</label>
			</div>
			<div class="form-check mb-2">
				<input class="form-check-input" type="checkbox" name="request_setting_device" id="requestSettingDevice" value="1">
				<label class="form-check-label" for="requestSettingDevice">
					<strong>Setting Email Account kantor pada mobile device/pribadi/kantor</strong>
				</label>
			</div>
			   <div id="deviceSection" style="margin-left:18px; display:none;">
				   <div class="form-check mb-1">
					   <input class="form-check-input" type="checkbox" name="device_android" id="deviceAndroid" value="Android" disabled>
					   <label class="form-check-label" for="deviceAndroid">Android</label>
				   </div>
				   <div class="form-check mb-1">
					   <input class="form-check-input" type="checkbox" name="device_ios" id="deviceIOS" value="Apple iOS" disabled>
					   <label class="form-check-label" for="deviceIOS">Apple iOS</label>
				   </div>
				   <div class="form-check mb-1">
					   <input class="form-check-input" type="checkbox" name="device_lainnya" id="deviceLainnya" value="Lainnya" disabled>
					   <label class="form-check-label" for="deviceLainnya">Perangkat lainnya :</label>
					   <input type="text" name="device_lainnya_text" class="form-control form-control-sm d-inline-block" style="width:200px; margin-left:8px;" placeholder="Sebutkan..." disabled>
				   </div>
			   </div>
		</div>
	</div>
	   <div class="form-group mb-2">
		   <label style="font-size:.875rem;font-weight:600;">Status :</label>
		   <div class="border rounded p-3">
			   <label style="font-size:.85rem;font-weight:600;">Akses E-mail:</label>
			   <div class="ps-3">
				   <div class="form-check mb-1">
					   <input class="form-check-input" type="checkbox" name="akses_email_local" id="aksesLocal" value="Local">
					   <label class="form-check-label" for="aksesLocal">
						   <strong>Local</strong> – Hanya bisa kirim & terima email internal (<span class="text-muted">Sesama @sumitex.co.id</span>) – <span class="text-muted">Default</span>
					   </label>
				   </div>
				   <div class="form-check mb-1">
					   <input class="form-check-input" type="checkbox" name="akses_email_global" id="aksesGlobal" value="Global">
					   <label class="form-check-label" for="aksesGlobal">
						   <strong>Global</strong>
					   </label>
				   </div>
				   <div id="globalSubOptions" class="ps-4" style="display:none;">
					   <div class="form-check mb-1">
						   <input class="form-check-input" type="checkbox" name="global_kirim" id="globalKirim" value="Kirim">
						   <label class="form-check-label" for="globalKirim">Hanya bisa kirim email keluar (<span class="text-muted">luar domain @sumitex.co.id</span>)</label>
					   </div>
					   <div class="form-check mb-1">
						   <input class="form-check-input" type="checkbox" name="global_terima" id="globalTerima" value="Terima">
						   <label class="form-check-label" for="globalTerima">Hanya bisa terima email dari luar (<span class="text-muted">luar domain @sumitex.co.id</span>)</label>
					   </div>
					   <div class="form-check mb-1">
						   <input class="form-check-input" type="checkbox" name="global_full" id="globalFull" value="Full">
						   <label class="form-check-label" for="globalFull">Bisa kirim & terima email dari luar (<span class="text-muted">luar domain @sumitex.co.id</span>) – <b>Full Akses</b></label>
					   </div>
				   </div>
			   </div>
		   </div>
	   </div>
	<div class="form-group mb-2">
		<label class="mb-1" style="font-size:.875rem;font-weight:600;">Keterangan</label>
		<textarea name="keterangan" class="form-control form-control-sm" rows="2" style="resize:vertical;" placeholder="Isi keterangan tambahan jika perlu..."></textarea>
	<script>
	(function waitForjQuery(){
		var start = Date.now();
		function tick(){
			if (window.jQuery) return init(window.jQuery);
			if (Date.now() - start > 10000) { console.error('Form Email Account: jQuery not found after 10s'); return; }
			setTimeout(tick, 100);
		}
		tick();
		function init($){
			var today = new Date();
			var isoDate = today.toISOString().slice(0,10);
			$('.tgl-pengajuan-email').val(isoDate);

			// Device section show/hide logic
			var settingDevice = document.getElementById('requestSettingDevice');
			var deviceSection = document.getElementById('deviceSection');
			var deviceInputs = deviceSection ? deviceSection.querySelectorAll('input') : [];
			function updateDeviceSection() {
				if (settingDevice && settingDevice.checked) {
					deviceSection.style.display = 'block';
					deviceInputs.forEach(function(inp){ inp.disabled = false; });
				} else {
					deviceSection.style.display = 'none';
					deviceInputs.forEach(function(inp){ inp.disabled = true; if(inp.type==='checkbox') inp.checked=false; if(inp.type==='text') inp.value=''; });
				}
			}
			if (settingDevice) {
				settingDevice.addEventListener('change', updateDeviceSection);
				updateDeviceSection();
			}

			// Global sub-options show/hide logic
			var aksesGlobal = document.getElementById('aksesGlobal');
			var globalSub = document.getElementById('globalSubOptions');
			function updateGlobalSub() {
				if (aksesGlobal && aksesGlobal.checked) {
					globalSub.style.display = 'block';
				} else {
					globalSub.style.display = 'none';
					var subs = globalSub.querySelectorAll('input[type=checkbox]');
					subs.forEach(function(inp){ inp.checked = false; });
				}
			}

			if (aksesGlobal && globalSub) {
				aksesGlobal.addEventListener('change', updateGlobalSub);
				updateGlobalSub();
			}

            // Defensive submit handling
            window._allowSubmit = false;
            $('#formPengajuanEmailAccount').off('submit.myTTD').on('submit.myTTD', function(e){
                if (!window._allowSubmit) {
                    e.preventDefault();
                    if (!window._skipTTDCheckOnce) {
                        if (typeof window.validateTTDBeforeSubmit === 'function') {
                            window.validateTTDBeforeSubmit().then(function(ok){
                                if(ok) {
                                    window._allowSubmit = true;
                                    $('#formPengajuanEmailAccount')[0].submit();
                                }
                            });
                        } else {
                            // Fallback if component not ready
                            console.warn('validateTTDBeforeSubmit not found');
                            window._allowSubmit = true;
                            $('#formPengajuanEmailAccount')[0].submit();
                        }
                    } else {
                        window._allowSubmit = true;
                        $('#formPengajuanEmailAccount')[0].submit();
                    }
                    return false;
                }
                window._allowSubmit = false;
                return true;
            });
		}
	})();
	</script>

    <?php include 'components/signature_canvas.php'; ?>

