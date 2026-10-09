<?php
/**
 * Form Pengajuan Cash On Delivery (COD)
 * Sesuai format dokumen SUM-FM-PB-004
 * Dimuat via AJAX ke dalam #modalFormIsian di list_form.php
 */

if (!isset($conn) || $conn === false) {
    $koneksiPath = $_SERVER['DOCUMENT_ROOT'] . '/gg_app/koneksi.php';
    if (file_exists($koneksiPath)) {
        require_once $koneksiPath;
    }
}

$username = htmlspecialchars($_SESSION['NamaLengkap'] ?? $_SESSION['UserName'] ?? '');
$nik = '';
$departemen = '';
$bagian = '';
$jabatan = '';

if (!empty($username) && isset($conn) && $conn !== false) {
    $sql = "SELECT m_emp.nik, m_dept.dept, m_bag.bagian, m_jab.jabatan
            FROM dbo.m_emp
            LEFT JOIN dbo.m_subbag ON m_emp.id_subbag = m_subbag.id_subbag
            LEFT JOIN dbo.m_bag    ON m_subbag.id_bag  = m_bag.id_bag
            LEFT JOIN dbo.m_dept   ON m_bag.id_dept    = m_dept.id_dept
            LEFT JOIN dbo.m_jab    ON m_emp.id_jab     = m_jab.id_jab
            WHERE m_emp.nama_lengkap = ? OR m_emp.username = ?";
    $stmt = sqlsrv_query($conn, $sql, [$username, $username]);
    if ($stmt !== false) {
        $emp = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($emp) {
            $nik = $emp['nik'] ?? '';
            $departemen = $emp['dept'] ?? '';
            $bagian = $emp['bagian'] ?? '';
            $jabatan = $emp['jabatan'] ?? '';
        }
        sqlsrv_free_stmt($stmt);
    }
}

$isEdit = isset($_GET['edit']) && $_GET['edit'] == 1 && isset($_GET['ticket']);
$editTicket = $isEdit ? htmlspecialchars($_GET['ticket']) : '';
$tglHariIni = date('Y-m-d');
$dueDefault = date('Y-m-d', strtotime('+1 day'));
?>

<style>
    .cod-doc-box {
        border: 2px solid #333;
        padding: 5px 12px;
        font-weight: 800;
        font-size: 0.95rem;
        letter-spacing: 1px;
        display: inline-block;
        background: #fff;
    }
    .cod-section-title {
        font-size: 0.88rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #495057;
        border-bottom: 2px solid #dee2e6;
        padding-bottom: 4px;
        margin-bottom: 12px;
        margin-top: 8px;
    }
    .table-cod-items th {
        background-color: #f1f3f5;
        font-size: 0.82rem;
        font-weight: 700;
        text-align: center;
        vertical-align: middle;
        border: 1px solid #ced4da;
    }
    .table-cod-items td {
        vertical-align: middle;
        padding: 6px;
        border: 1px solid #ced4da;
    }
    .subtotal-highlight {
        font-size: 1.15rem;
        font-weight: 800;
        color: #1a73e8;
    }
    .terbilang-box {
        background: #f8f9fa;
        border-left: 4px solid #1a73e8;
        padding: 8px 12px;
        font-style: italic;
        font-size: 0.88rem;
        color: #333;
    }

    /* ── Modal Pilih Rekening Vendor ─────────────── */
    .swal2-container {
        z-index: 99999 !important;
    }
    #modalPilihRekening .modal-dialog {
        max-width: 480px !important;
        width: 95% !important;
        margin: 1.75rem auto;
    }
    #modalRekeningList {
        max-height: 60vh;
        overflow-y: auto;
        padding-right: 2px;
    }
    .rekening-card {
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 12px 14px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
        background: #fff;
        position: relative;
    }
    .rekening-card:hover {
        border-color: #1a73e8;
        background: #f0f6ff;
        box-shadow: 0 2px 8px rgba(26,115,232,0.12);
    }
    .rekening-card.selected {
        border-color: #1a73e8;
        background: #e8f0fe;
        box-shadow: 0 2px 10px rgba(26,115,232,0.18);
    }
    .rekening-card .rekening-bank {
        font-weight: 800;
        font-size: 0.95rem;
        color: #1a73e8;
    }
    .rekening-card .rekening-nmbr {
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        color: #212529;
    }
    .rekening-card .rekening-atas-nama {
        font-size: 0.82rem;
        color: #495057;
    }
    .rekening-card .rekening-currency {
        font-size: 0.78rem;
        color: #6c757d;
    }
    .rekening-card .check-icon {
        position: absolute;
        top: 10px;
        right: 12px;
        font-size: 1.2rem;
        color: #1a73e8;
        display: none;
    }
    .rekening-card.selected .check-icon {
        display: block;
    }
    .rekening-card.is-default {
        border-color: #28a745;
        background: #fcfefc;
    }
    .rekening-card.is-default:hover {
        border-color: #218838;
        background: #f2f9f4;
    }
    .rekening-card .badge-default {
        background: #28a745;
        color: #fff;
        font-size: 0.68rem;
        padding: 2px 8px;
        border-radius: 10px;
        font-weight: 700;
        margin-left: 8px;
        vertical-align: middle;
        display: inline-flex;
        align-items: center;
        letter-spacing: 0.3px;
        box-shadow: 0 1px 3px rgba(40,167,69,0.3);
    }
</style>

<form method="POST" id="formCashOnDelivery" enctype="multipart/form-data" style="margin-bottom:0;">
    <input type="hidden" name="form_type" value="cash_on_delivery">
    <input type="hidden" name="items_json" id="codItemsJson" value="">
    <input type="hidden" name="subtotal" id="codSubtotalInput" value="0">
    <input type="hidden" name="terbilang" id="codTerbilangInput" value="">
    <input type="hidden" name="nama_pemohon" value="<?= htmlspecialchars($username) ?>">
    <input type="hidden" name="departemen" value="<?= htmlspecialchars($departemen) ?>">

    <?php if ($isEdit): ?>
        <input type="hidden" name="ticket" value="<?= $editTicket ?>">
        <input type="hidden" name="action" value="update">
    <?php endif; ?>

    <!-- ── Header Banner Dokumen ─────────────────────────────────── -->
    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
        <div>
            <h5 class="font-weight-bold text-dark mb-0">Pengajuan Cash On Delivery</h5>
            <small class="text-muted">Formulir Pengajuan Pembelian / Pembayaran COD</small>
        </div>
        <div>
            <div class="cod-doc-box">SUM-FM-PB-004</div>
        </div>
    </div>

    <!-- ── Bagian 1: Header / Dokumen Info ────────────────────────── -->
    <div class="cod-section-title">
        <i class="fas fa-file-invoice mr-1"></i> 1. Informasi Receipt &amp; Pengajuan
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">No Receipt <small class="text-muted font-weight-normal">(Opsional)</small></label>
                <input type="text" name="no_receipt" id="codNoReceipt" class="form-control form-control-sm font-weight-bold text-primary"
                    placeholder="Contoh: GRNSP/2609/0155">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Tgl Receipt <small class="text-muted font-weight-normal">(Opsional)</small></label>
                <input type="date" name="tgl_receipt" id="codTglReceipt" class="form-control form-control-sm"
                    value="<?= $tglHariIni ?>">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Tgl Pengajuan</label>
                <input type="date" name="tgl_pengajuan" id="codTglPengajuan" class="form-control form-control-sm bg-light"
                    value="<?= $tglHariIni ?>" required>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Due Date (Jatuh Tempo) <span class="text-danger">*</span></label>
                <input type="date" name="due_date" id="codDueDate" class="form-control form-control-sm"
                    value="<?= $dueDefault ?>" required>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Supplier / Vendor <span class="text-danger">*</span></label>
                <input type="text" name="supplier" id="codSupplier" class="form-control form-control-sm"
                    placeholder="Contoh: STARLINK SERVICE INDONESIA, PT" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1 text-primary">
                    <i class="fas fa-barcode mr-1"></i> Refferensi PO No. (Tarik Otomatis dari ERP)
                </label>
                <div class="input-group input-group-sm">
                    <input type="text" name="ref_po_no" id="codRefPoNo" class="form-control font-weight-bold"
                        placeholder="Contoh: POSP/2609/0087">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-info font-weight-bold" id="btnTarikPo" title="Tarik data otomatis dari ERP">
                            <i class="fas fa-search"></i> Tarik Data PO
                        </button>
                    </div>
                </div>
                <small class="form-text text-muted">Tekan <strong>Tarik Data PO</strong> atau tombol <strong>Enter</strong> untuk auto-fill seluruh isian.</small>
            </div>
        </div>
    </div>

    <!-- ── Bagian 2: Akun & Pembayaran ───────────────────────────── -->
    <div class="cod-section-title">
        <i class="fas fa-university mr-1"></i> 2. Akun &amp; Pembayaran
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Cash/Bank Account <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                    <input type="text" name="cash_bank_account" id="codCashBankAccount" class="form-control form-control-sm"
                        placeholder="Ketik manual / pilih (Transfer / Cash)" list="codCashBankList" required autocomplete="off">
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Pilihan Dropdown">
                            Pilih
                        </button>
                        <div class="dropdown-menu dropdown-menu-right shadow-sm">
                            <a class="dropdown-item cod-opt-cash-bank font-weight-bold py-2" href="javascript:void(0)" data-value="Transfer">
                                <i class="fas fa-exchange-alt mr-2 text-primary"></i> Transfer
                            </a>
                            <a class="dropdown-item cod-opt-cash-bank font-weight-bold py-2" href="javascript:void(0)" data-value="Cash">
                                <i class="fas fa-money-bill-wave mr-2 text-success"></i> Cash
                            </a>
                        </div>
                    </div>
                </div>
                <datalist id="codCashBankList">
                    <option value="Transfer">
                    <option value="Cash">
                </datalist>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Dibayar Kepada</label>
                <input type="text" name="dibayar_kepada" id="codDibayarKepada" class="form-control form-control-sm"
                    placeholder="Nama Penerima Pembayaran">
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Currency</label>
                <select name="currency" id="codCurrency" class="form-control form-control-sm">
                    <option value="IDR" selected>IDR (Rupiah)</option>
                    <option value="USD">USD (US Dollar)</option>
                    <option value="SGD">SGD (Singapore Dollar)</option>
                    <option value="EUR">EUR (Euro)</option>
                    <option value="JPY">JPY (Japanese Yen)</option>
                </select>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Bank</label>
                <input type="text" name="bank" id="codBank" class="form-control form-control-sm"
                    placeholder="Contoh: BCA / Mandiri / BRI / Permata">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">A/C (No. Rekening)</label>
                <input type="text" name="rekening_ac" id="codRekeningAc" class="form-control form-control-sm"
                    placeholder="Nomor Rekening">
            </div>
        </div>
    </div>

    <!-- ── Bagian 3: Keterangan Pengajuan ───────────────────────── -->
    <div class="cod-section-title">
        <i class="fas fa-comment-alt mr-1"></i> 3. Keterangan Pengajuan
    </div>

    <div class="form-group mb-3">
        <label class="small font-weight-bold mb-1">Keterangan Umum / Uraian Transaksi <span class="text-danger">*</span></label>
        <textarea name="keterangan" id="codKeterangan" class="form-control form-control-sm" rows="2"
            placeholder="Contoh: PEMBAYARAN MONTHLY FEE INTERNET PERIODE 19/09/2026 – 19/10/2026, MENGGUNAKAN KARTU KREDIT PT. SUM" required></textarea>
    </div>

    <!-- ── Bagian 4: Tabel Rincian Item (DPP, PPN, PPH, Dll) ─────── -->
    <div class="d-flex justify-content-between align-items-center mb-1">
        <div class="cod-section-title mb-0 border-bottom-0 pb-0">
            <i class="fas fa-list-ol mr-1"></i> 4. Rincian Item / Pajak
        </div>
        <div class="d-flex align-items-center">
            <div class="btn-group btn-group-sm mr-2">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnQuickAddPPN" title="Tambah Baris PPN 11%">
                    <i class="fas fa-percentage"></i> + PPN 11%
                </button>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-danger btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" id="btnDropdownPPh" title="Pilih Potongan PPh">
                        <i class="fas fa-minus-circle"></i> + PPh
                    </button>
                    <div class="dropdown-menu dropdown-menu-right shadow-sm" style="font-size: 0.85rem; min-width: 250px;">
                        <h6 class="dropdown-header text-uppercase font-weight-bold py-1 px-3 text-secondary" style="font-size: 0.72rem;">
                            <i class="fas fa-percent mr-1"></i> Potongan PPh
                        </h6>
                        <a class="dropdown-item btn-quick-add-pph py-1 px-3" href="#" data-label="PPH 21 2.5%" data-rate="0.025">
                            <span class="badge badge-light text-danger mr-1 border font-weight-bold">21</span> PPh 21 (2.5%)
                        </a>
                        <a class="dropdown-item btn-quick-add-pph py-1 px-3" href="#" data-label="PPH 23 2%" data-rate="0.02">
                            <span class="badge badge-light text-danger mr-1 border font-weight-bold">23</span> PPh 23 (2%)
                        </a>
                        <div class="dropdown-divider my-1"></div>
                        <a class="dropdown-item btn-quick-add-pph py-1 px-3" href="#" data-label="PPH 4(2) 0.5%" data-rate="0.005">
                            <span class="badge badge-light text-danger mr-1 border font-weight-bold">4(2)</span> PPh 4(2) - 0.5% (Jasa/Konstruksi)
                        </a>
                        <a class="dropdown-item btn-quick-add-pph py-1 px-3" href="#" data-label="PPH 4(2) 10%" data-rate="0.10">
                            <span class="badge badge-light text-danger mr-1 border font-weight-bold">4(2)</span> PPh 4(2) - 10% (Sewa Bangunan)
                        </a>
                        <div class="dropdown-divider my-1"></div>
                        <a class="dropdown-item btn-quick-add-pph py-1 px-3" href="#" data-label="PPH 22 1.5%" data-rate="0.015">
                            <span class="badge badge-light text-danger mr-1 border font-weight-bold">22</span> PPh 22 (1.5%)
                        </a>
                        <a class="dropdown-item btn-quick-add-pph py-1 px-3" href="#" data-label="PPH 26 20%" data-rate="0.20">
                            <span class="badge badge-light text-danger mr-1 border font-weight-bold">26</span> PPh 26 (20%)
                        </a>
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" id="btnAddRow">
                    <i class="fas fa-plus"></i> Tambah Baris
                </button>
            </div>
            <button type="button" class="btn btn-danger btn-sm" id="btnDeleteAllRows" title="Hapus / Clear semua baris rincian item">
                <i class="fas fa-trash-alt"></i> Delete All
            </button>
        </div>
    </div>

    <div class="table-responsive mt-2 mb-2">
        <table class="table table-bordered table-sm table-cod-items mb-0" id="tableCodItems">
            <thead>
                <tr>
                    <th style="width: 40px;">NO</th>
                    <th>KETERANGAN <span class="text-danger">*</span></th>
                    <th style="width: 80px;">C/D</th>
                    <th style="width: 140px;">SIFAT</th>
                    <th style="width: 85px;">CURR</th>
                    <th style="width: 170px;">DPP (NILAI) <span class="text-danger">*</span></th>
                    <th style="width: 50px;">AKSI</th>
                </tr>
            </thead>
            <tbody id="codItemsBody">
                <!-- Rows injected dynamically via JS -->
            </tbody>
            <tfoot>
                <tr style="background: #f8f9fa;">
                    <td colspan="5" class="text-right font-weight-bold align-middle pr-3">
                        SubTotal:
                    </td>
                    <td colspan="2" class="align-middle">
                        <span id="labelSubTotal" class="subtotal-highlight">Rp 0,00</span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Terbilang Display -->
    <div class="mb-3">
        <div class="terbilang-box">
            <strong>Terbilang: </strong> <span id="labelTerbilang">Nol rupiah</span>
        </div>
    </div>

    <!-- ── Bagian 5: Penandatanganan & Lampiran ─────────── -->
    <div class="cod-section-title">
        <i class="fas fa-user-edit mr-1"></i> 5. Pembuat &amp; Lampiran
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Dibuat Oleh <span class="text-danger">*</span></label>
                <input type="text" name="dibuat_oleh" id="codDibuatOleh" class="form-control form-control-sm"
                    value="<?= htmlspecialchars($username ?: 'RIA') ?>" required>
                <small class="text-muted">Nama pembuat formulir</small>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group mb-2">
                <label class="small font-weight-bold mb-1">Dicek Oleh <span class="text-danger">*</span></label>
                <input type="text" name="dicek_oleh" id="codDicekOleh" class="form-control form-control-sm"
                    value="KABAG" required readonly>
                <small class="text-muted">Jabatan pemeriksa (KABAG)</small>
            </div>
        </div>
    </div>

    <div class="form-group mb-2">
        <label class="small font-weight-bold mb-1">Lampiran Dokumen / Bukti Receipt (Opsional)</label>
        <div class="custom-file custom-file-sm">
            <input type="file" class="custom-file-input" id="codLampiranFile" name="lampiran" accept=".jpg,.jpeg,.png,.pdf">
            <label class="custom-file-label col-form-label-sm" for="codLampiranFile" data-browse="Pilih">Pilih file scan / receipt...</label>
        </div>
        <small class="form-text text-muted">Format: JPG, PNG, PDF (Maks. 5MB)</small>
    </div>

</form>

<!-- ══ Modal Pilih Rekening Vendor (Multi Bank Account) ════════════════════ -->
<div class="modal fade" id="modalPilihRekening" tabindex="-1" role="dialog" aria-labelledby="modalPilihRekeningLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 480px !important; width: 95% !important;">
        <div class="modal-content shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title font-weight-bold mb-0" id="modalPilihRekeningLabel">
                    <i class="fas fa-university mr-2"></i>
                    Pilih Rekening Bank Vendor
                </h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body py-3">
                <p class="text-muted small mb-3" id="modalRekeningSubtitle">
                    Vendor memiliki beberapa rekening. Klik salah satu untuk memilih:
                </p>
                <div id="modalRekeningList">
                    <!-- Card rekening di-inject oleh JS -->
                </div>
            </div>
            <div class="modal-footer py-2 justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Lewati (Isi Manual)
                </button>
                <span class="text-muted small"><i class="fas fa-info-circle mr-1"></i>Klik card untuk memilih</span>
            </div>
        </div>
    </div>
</div>
<!-- ══ End Modal Pilih Rekening ════════════════════════════════════════════ -->

<script>
(function() {
    var $tbody = $('#codItemsBody');

    // Helper: Notifikasi Toast di Pojok Kanan Atas
    function showToast(opts) {
        if (typeof Swal !== 'undefined') {
            return Swal.fire($.extend({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            }, opts));
        } else if (opts && (opts.text || opts.title)) {
            alert(opts.title ? (opts.title + ': ' + (opts.text || '')) : opts.text);
        }
    }

    // Helper: Terbilang Rupiah Lengkap dengan Desimal / Koma
    function terbilangAngka(angka) {
        angka = Math.floor(Math.abs(angka));
        var huruf = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
        var hasil = '';

        if (angka < 12) {
            hasil = huruf[angka];
        } else if (angka < 20) {
            hasil = terbilangAngka(angka - 10) + ' Belas';
        } else if (angka < 100) {
            var puluh = terbilangAngka(Math.floor(angka / 10)) + ' Puluh';
            var sisa10 = terbilangAngka(angka % 10);
            hasil = puluh + (sisa10 ? ' ' + sisa10 : '');
        } else if (angka < 200) {
            var sisa100 = terbilangAngka(angka - 100);
            hasil = 'Seratus' + (sisa100 ? ' ' + sisa100 : '');
        } else if (angka < 1000) {
            var ratus = terbilangAngka(Math.floor(angka / 100)) + ' Ratus';
            var sisa100b = terbilangAngka(angka % 100);
            hasil = ratus + (sisa100b ? ' ' + sisa100b : '');
        } else if (angka < 2000) {
            var sisa1000 = terbilangAngka(angka - 1000);
            hasil = 'Seribu' + (sisa1000 ? ' ' + sisa1000 : '');
        } else if (angka < 1000000) {
            var ribu = terbilangAngka(Math.floor(angka / 1000)) + ' Ribu';
            var sisa1000b = terbilangAngka(angka % 1000);
            hasil = ribu + (sisa1000b ? ' ' + sisa1000b : '');
        } else if (angka < 1000000000) {
            var juta = terbilangAngka(Math.floor(angka / 1000000)) + ' Juta';
            var sisa1jt = terbilangAngka(angka % 1000000);
            hasil = juta + (sisa1jt ? ' ' + sisa1jt : '');
        } else if (angka < 1000000000000) {
            var miliar = terbilangAngka(Math.floor(angka / 1000000000)) + ' Miliar';
            var sisa1m = terbilangAngka(angka % 1000000000);
            hasil = miliar + (sisa1m ? ' ' + sisa1m : '');
        } else {
            var triliun = terbilangAngka(Math.floor(angka / 1000000000000)) + ' Triliun';
            var sisa1t = terbilangAngka(angka % 1000000000000);
            hasil = triliun + (sisa1t ? ' ' + sisa1t : '');
        }
        return hasil.trim();
    }

    function getTerbilangLengkap(val) {
        var num = parseFloat(val) || 0;
        if (num === 0) return 'Nol rupiah';

        var bagianBulat = Math.floor(num);
        var bagianDesimal = Math.round((num - bagianBulat) * 100);

        var kata = terbilangAngka(bagianBulat);
        if (kata.length > 0) {
            kata = kata.charAt(0).toUpperCase() + kata.slice(1).toLowerCase();
        }

        if (bagianDesimal > 0) {
            var desimalKata = terbilangAngka(bagianDesimal).toLowerCase();
            return kata + ' koma ' + desimalKata + ' rupiah.';
        } else {
            return kata + ' rupiah.';
        }
    }

    // Format angka ke format mata uang (contoh: 720,720.72)
    function formatCurrency(num) {
        var n = parseFloat(num) || 0;
        return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Tambah Baris Baru ke Tabel Item
    function addRow(data) {
        data = data || {};
        var rowCount = $tbody.find('tr').length + 1;
        var currVal = data.curr || $('#codCurrency').val() || 'IDR';
        var cdVal = data.cd || 'D';
        var sifatVal = data.sifat || '+';
        var ketVal = data.keterangan || '';
        var dppVal = (data.dpp !== undefined && data.dpp !== null) ? data.dpp : '';

        var html = '<tr class="cod-item-row">' +
            '<td class="text-center font-weight-bold row-no align-middle">' + rowCount + '</td>' +
            '<td>' +
                '<input type="text" class="form-control form-control-sm item-keterangan" value="' + $('<div>').text(ketVal).html() + '" placeholder="Keterangan item / pengeluaran..." required>' +
            '</td>' +
            '<td>' +
                '<select class="form-control form-control-sm item-cd text-center font-weight-bold">' +
                    '<option value="D" ' + (cdVal === 'D' ? 'selected' : '') + '>D</option>' +
                    '<option value="C" ' + (cdVal === 'C' ? 'selected' : '') + '>C</option>' +
                '</select>' +
            '</td>' +
            '<td>' +
                '<select class="form-control form-control-sm item-sifat font-weight-bold ' + (sifatVal === '-' ? 'text-danger' : 'text-success') + '">' +
                    '<option value="+" ' + (sifatVal === '+' ? 'selected' : '') + '>+ Penambah</option>' +
                    '<option value="-" ' + (sifatVal === '-' ? 'selected' : '') + '>- Potongan (PPh)</option>' +
                '</select>' +
            '</td>' +
            '<td>' +
                '<input type="text" class="form-control form-control-sm item-curr text-center" value="' + currVal + '" maxlength="5">' +
            '</td>' +
            '<td>' +
                '<input type="number" step="0.01" min="0" class="form-control form-control-sm item-dpp text-right font-weight-bold" value="' + dppVal + '" placeholder="0.00" required>' +
            '</td>' +
            '<td class="text-center align-middle">' +
                '<button type="button" class="btn btn-outline-danger btn-xs btn-remove-row" title="Hapus baris"><i class="fas fa-times"></i></button>' +
            '</td>' +
        '</tr>';

        $tbody.append(html);
        reindexRows();
        recalculateSubtotal();
    }

    function reindexRows() {
        $tbody.find('tr').each(function(idx) {
            $(this).find('.row-no').text(idx + 1);
        });
    }

    // Hitung Subtotal & Terbilang
    function recalculateSubtotal() {
        var total = 0;
        var items = [];

        $tbody.find('tr.cod-item-row').each(function(idx) {
            var $tr = $(this);
            var ket = $tr.find('.item-keterangan').val().trim();
            var cd = $tr.find('.item-cd').val();
            var sifat = $tr.find('.item-sifat').val();
            var curr = $tr.find('.item-curr').val().trim();
            var dpp = parseFloat($tr.find('.item-dpp').val()) || 0;

            if (sifat === '-') {
                total -= dpp;
            } else {
                total += dpp;
            }

            items.push({
                no: idx + 1,
                keterangan: ket,
                cd: cd,
                sifat: sifat,
                curr: curr,
                dpp: dpp
            });
        });

        // Set label & hidden inputs
        $('#labelSubTotal').text(formatCurrency(total));
        $('#codSubtotalInput').val(total.toFixed(2));

        var terbilangTxt = getTerbilangLengkap(total);
        $('#labelTerbilang').text(terbilangTxt);
        $('#codTerbilangInput').val(terbilangTxt);

        // Serialize items array to hidden JSON input for submission
        $('#codItemsJson').val(JSON.stringify(items));
    }

    // Event handlers
    $('#btnAddRow').on('click', function(e) {
        e.preventDefault();
        addRow();
    });

    $tbody.on('click', '.btn-remove-row', function(e) {
        e.preventDefault();
        if ($tbody.find('tr').length <= 1) {
            showToast({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Minimal harus ada 1 baris item!'
            });
            return;
        }
        $(this).closest('tr').remove();
        reindexRows();
        recalculateSubtotal();
    });

    // Delete All (Clear seluruh baris di tabel Rincian Item / Pajak langsung tanpa popup)
    $('#btnDeleteAllRows').on('click', function(e) {
        e.preventDefault();
        $tbody.empty();
        addRow({
            keterangan: '',
            cd: 'D',
            sifat: '+',
            curr: $('#codCurrency').val() || 'IDR',
            dpp: ''
        });
        recalculateSubtotal();
    });

    $tbody.on('input change', '.item-dpp, .item-sifat, .item-keterangan, .item-cd, .item-curr', function() {
        var $sifat = $(this).closest('tr').find('.item-sifat');
        if ($sifat.val() === '-') {
            $sifat.removeClass('text-success').addClass('text-danger');
        } else {
            $sifat.removeClass('text-danger').addClass('text-success');
        }
        recalculateSubtotal();
    });

    // Quick Add PPN 11%
    $('#btnQuickAddPPN').on('click', function(e) {
        e.preventDefault();
        var firstDpp = parseFloat($tbody.find('tr:first .item-dpp').val()) || 0;
        var ppnVal = Math.round(firstDpp * 0.11 * 100) / 100;
        addRow({
            keterangan: 'PPN 11%',
            cd: 'C',
            sifat: '+',
            curr: $('#codCurrency').val() || 'IDR',
            dpp: ppnVal > 0 ? ppnVal : ''
        });
    });

    // Quick Add PPh via Dropdown (+ backward-compatible alias untuk #btnQuickAddPPh)
    $(document).on('click', '.btn-quick-add-pph', function(e) {
        e.preventDefault();
        var label = $(this).data('label');
        var rate = parseFloat($(this).data('rate')) || 0;
        var firstDpp = parseFloat($tbody.find('tr:first .item-dpp').val()) || 0;
        var pphVal = Math.round(firstDpp * rate * 100) / 100;
        addRow({
            keterangan: label,
            cd: 'D',
            sifat: '-',
            curr: $('#codCurrency').val() || 'IDR',
            dpp: pphVal > 0 ? pphVal : ''
        });
    });

    $('#btnQuickAddPPh').on('click', function(e) {
        e.preventDefault();
        $('.btn-quick-add-pph[data-label="PPH 23 2%"]').trigger('click');
    });

    // Update currency in items when main currency changes
    $('#codCurrency').on('change', function() {
        var newCurr = $(this).val();
        $tbody.find('.item-curr').val(newCurr);
        recalculateSubtotal();
    });

    // Update file input label
    $('#codLampiranFile').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName || 'Pilih file scan / receipt...');
    });

    // Inisialisasi baris pertama jika tabel masih kosong
    if ($tbody.find('tr').length === 0) {
        addRow({
            keterangan: '',
            cd: 'D',
            sifat: '+',
            curr: 'IDR',
            dpp: ''
        });
    }

    // Expose populate helper untuk form edit & auto-fill PO
    window.populateCodItems = function(items) {
        $tbody.empty();
        if (Array.isArray(items) && items.length > 0) {
            items.forEach(function(item) {
                addRow(item);
            });
        } else {
            addRow();
        }
    };

    // ── Tarik Data PO dari ERP via get_po_data.php ──────────────

    /**
     * Isi semua field form COD berdasarkan data PO + rekening yang dipilih.
     * Dipanggil baik langsung (1 rekening) maupun setelah user pilih dari popup.
     */
    function fillCodFields(d, selectedAccount) {
        // 1. No Receipt & Tgl Receipt
        if (d.no_receipt) $('#codNoReceipt').val(d.no_receipt);
        if (d.tgl_receipt) $('#codTglReceipt').val(d.tgl_receipt);

        // 2. Supplier
        if (d.supplier) $('#codSupplier').val(d.supplier);

        // 3. Rekening: gunakan selectedAccount jika ada, fallback ke field di d
        var bank       = (selectedAccount && selectedAccount.bank)       || d.bank       || '';
        var rekeningAc = (selectedAccount && selectedAccount.no_rekening) || d.rekening_ac || '';
        var atasNama   = (selectedAccount && selectedAccount.atas_nama)   || d.dibayar_kepada || '';
        var currency   = (selectedAccount && selectedAccount.currency)    || d.currency   || '';
        var currencyName = (selectedAccount && selectedAccount.currency_name) || d.currency_name || '';

        if (atasNama)   $('#codDibayarKepada').val(atasNama);
        if (bank)       $('#codBank').val(bank);
        if (rekeningAc) $('#codRekeningAc').val(rekeningAc);

        // 4. Cash/Bank Account → Transfer jika ada rekening
        if (rekeningAc || bank) {
            $('#codCashBankAccount').val('Transfer');
        }

        // 5. Currency
        var currToSet = currency || d.currency || '';
        if (currToSet) {
            if ($('#codCurrency option[value="' + currToSet + '"]').length === 0) {
                var currText = currToSet + (currencyName ? ' (' + currencyName + ')' : '');
                $('#codCurrency').append(new Option(currText, currToSet));
            }
            $('#codCurrency').val(currToSet).trigger('change');
        }

        // 6. Keterangan
        if (d.keterangan) $('#codKeterangan').val(d.keterangan);

        // 7. Rincian Item
        if (Array.isArray(d.items) && d.items.length > 0) {
            window.populateCodItems(d.items);
        }
    }

    /**
     * Tampilkan modal Bootstrap berisi card list semua rekening vendor.
     * Setelah user klik sebuah card, fillCodFields dipanggil dengan rekening terpilih,
     * lalu modal ditutup.
     */
    function showBankPickerModal(d, poNumber) {
        var accounts = d.vendor_accounts || [];
        var supplierName = $('<div>').text(d.supplier || '').html();
        var $modalPilih = $('#modalPilihRekening');

        // Pindahkan modal ke body agar tidak bersarang di dalam #modalFormIsian
        if (!$modalPilih.parent().is('body')) {
            $modalPilih.appendTo('body');
        }

        // Hentikan propagasi event modal agar tidak mempengaruhi #modalFormIsian
        $modalPilih.off('hide.bs.modal hidden.bs.modal').on('hide.bs.modal hidden.bs.modal', function(e) {
            e.stopPropagation();
            if (e.type === 'hidden') {
                if ($('#modalFormIsian').hasClass('show')) {
                    $('body').addClass('modal-open');
                }
            }
        });

        // Update subtitle modal
        $('#modalRekeningSubtitle').html(
            '<i class="fas fa-exclamation-circle text-warning mr-1"></i>' +
            'Vendor <strong>' + supplierName + '</strong> memiliki ' +
            '<span class="badge badge-warning text-dark">' + accounts.length + ' rekening</span>.' +
            ' Klik card rekening yang akan digunakan:'
        );

        // Build card list
        var html = '';
        $.each(accounts, function(i, acc) {
            var bankLabel    = $('<div>').text(acc.bank || '-').html();
            var nomorRek     = $('<div>').text(acc.no_rekening || '-').html();
            var atasNama     = $('<div>').text(acc.atas_nama || '-').html();
            var currLabel    = $('<div>').text(acc.currency || 'IDR').html();
            var defaultBadge = acc.is_default ? '<span class="badge-default"><i class="fas fa-check-circle mr-1"></i>DEFAULT</span>' : '';
            var cardClass    = 'rekening-card' + (acc.is_default ? ' is-default' : '');

            html += '<div class="' + cardClass + '" data-idx="' + i + '">' +
                '<i class="fas fa-check-circle check-icon"></i>' +
                '<div class="d-flex align-items-center mb-1">' +
                    '<span class="rekening-bank"><i class="fas fa-university mr-1"></i>' + bankLabel + '</span>' +
                    defaultBadge +
                    '<span class="ml-auto rekening-currency"><i class="fas fa-coins mr-1"></i>' + currLabel + '</span>' +
                '</div>' +
                '<div class="rekening-nmbr">' + nomorRek + '</div>' +
                '<div class="rekening-atas-nama"><i class="fas fa-user mr-1"></i>' + atasNama + '</div>' +
            '</div>';
        });

        $('#modalRekeningList').html(html);

        // Event: klik card → pilih rekening → isi form → tutup modal
        $('#modalRekeningList').off('click', '.rekening-card').on('click', '.rekening-card', function() {
            var idx = parseInt($(this).data('idx'));
            var selectedAcc = accounts[idx];

            // Visual feedback
            $('.rekening-card').removeClass('selected');
            $(this).addClass('selected');

            // Sedikit delay supaya user melihat animasi selected
            setTimeout(function() {
                // Tutup modal
                $modalPilih.modal('hide');

                // Isi field rekening dengan yang dipilih
                fillCodFields(d, selectedAcc);

                // Toast sukses
                showToast({
                    icon: 'success',
                    title: 'Rekening Dipilih',
                    html: '<b>' + $('<div>').text(selectedAcc.bank || '').html() + '</b> ' +
                          $('<div>').text(selectedAcc.no_rekening || '').html() +
                          '<br><small class="text-muted">a/n ' + $('<div>').text(selectedAcc.atas_nama || '').html() + '</small>',
                    timer: 2500
                });
            }, 220);
        });

        // Tampilkan modal
        $modalPilih.modal({ backdrop: true, keyboard: true });
    }

    function tarikDataPo(poNumber) {
        poNumber = (poNumber || $('#codRefPoNo').val() || '').trim();
        if (!poNumber) {
            showToast({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Silakan ketik Nomor PO terlebih dahulu.'
            });
            return;
        }

        var $btn = $('#btnTarikPo');
        var originalBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menarik...');

        $.ajax({
            url: 'get_po_data.php',
            type: 'GET',
            data: { po: poNumber },
            dataType: 'json',
            success: function(resp) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                if (resp && resp.success && resp.data) {
                    var d = resp.data;
                    var accCount = d.vendor_account_count || 0;

                    if (accCount > 1) {
                        // ── Multi-rekening: fill data PO dulu (tanpa rekening), lalu popup
                        fillCodFields(d, null);
                        showBankPickerModal(d, poNumber);

                        // Notif ringkas di pojok kanan atas
                        showToast({
                            icon: 'info',
                            title: 'Data PO Ditemukan',
                            html: 'Vendor <b>' + $('<div>').text(d.supplier || '').html() + '</b> memiliki ' +
                                  '<b>' + accCount + ' rekening</b>.<br>' +
                                  '<small class="text-muted">Silakan pilih rekening pada popup.</small>',
                            timer: 3500
                        });
                    } else {
                        // ── 1 rekening atau tidak ada → langsung fill semua (behaviour lama)
                        var singleAcc = (d.vendor_accounts && d.vendor_accounts.length > 0) ? d.vendor_accounts[0] : null;
                        fillCodFields(d, singleAcc);

                        showToast({
                            icon: 'success',
                            title: 'Data PO Ditemukan!',
                            html: 'Data untuk PO <b>' + $('<div>').text(poNumber).html() + '</b> berhasil ditarik dari ERP.' +
                                  '<br><small class="text-muted">Supplier: ' + $('<div>').text(d.supplier || '').html() +
                                  (d.no_receipt ? '<br>No Receipt: ' + $('<div>').text(d.no_receipt).html() : '') +
                                  (d.bank && d.rekening_ac ? '<br>Rekening: ' + $('<div>').text(d.bank + ' - ' + d.rekening_ac + ' a/n ' + (d.dibayar_kepada || '')).html() : '') +
                                  '</small>',
                            timer: 4000
                        });
                    }
                } else {
                    showToast({
                        icon: 'error',
                        title: 'Tidak Ditemukan',
                        text: (resp && resp.message) ? resp.message : 'Nomor PO tidak ditemukan di sistem ERP.'
                    });
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                var errTxt = 'Gagal menghubungi server untuk menarik data PO.';
                try {
                    var r = JSON.parse(xhr.responseText);
                    errTxt = r.message || errTxt;
                } catch(e) {}
                showToast({
                    icon: 'error',
                    title: 'Error Tarik Data',
                    text: errTxt
                });
            }
        });
    }

    $('#btnTarikPo').on('click', function(e) {
        e.preventDefault();
        tarikDataPo();
    });

    $('#codRefPoNo').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            tarikDataPo();
        }
    });

    // ─── Pilihan Dropdown Cash/Bank Account (Transfer / Cash) ───────────
    $(document).on('click', '.cod-opt-cash-bank', function(e) {
        e.preventDefault();
        var selectedVal = $(this).data('value');
        if (selectedVal !== undefined) {
            $('#codCashBankAccount').val(selectedVal).trigger('input').trigger('change').focus();
        }
    });

    // Hook validation before submit
    window.prepareCodSubmission = function() {
        return true;
    };

})();
</script>
