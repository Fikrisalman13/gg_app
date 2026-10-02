<?php
session_start();

// Basic auth check from existing pages
if (!isset($_SESSION['UserName'])) {
    header("Location: /gg_app/login.php");
    exit();
}

require_once __DIR__ . '/../../koneksi.php'; // Fix: Include DB Connection
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';

$themeColor = $_SESSION['Theme'] ?? 'primary';
?>
<!-- AdminLTE Clean Style for Form -->
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Absensi Data Cleaner</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/gg_app/index.php">Home</a></li>
                        <li class="breadcrumb-item active">Excel Tools</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card card-<?= htmlspecialchars($themeColor) ?>">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-file-excel mr-1"></i> Upload & Process Excel</h3>
                        </div>
                        <form id="excelForm" enctype="multipart/form-data">
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h5><i class="icon fas fa-info"></i> Fungsi Tool Ini:</h5>
                                    <ol>
                                        <li>Hapus duplikat berdasarkan <strong>Nama Karyawan</strong>.</li>
                                        <li>(Opsional) Gabungkan <strong>Tanggal</strong> dan <strong>Jam</strong> ke satu kolom.</li>
                                        <li>(Opsional) Hapus kolom Tanggal dan Jam asli setelah digabung.</li>
                                    </ol>
                                </div>

                                <div class="form-group">
                                    <label for="fileExcel">File Excel (.xls / .xlsx)</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="fileExcel" name="fileExcel" accept=".xls,.xlsx" required>
                                        <label class="custom-file-label" for="fileExcel">Pilih file...</label>
                                    </div>
                                </div>

                                <hr>
                                <h5>Konfigurasi Kolom & Baris</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Baris Mulai Data (Header/Data Row)</label>
                                            <input type="number" class="form-control" name="start_row" value="7" min="1" required>
                                            <small class="text-muted">Contoh: Jika data dimulai di baris 7 (B7).</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="enableMerge" name="enable_merge" checked>
                                        <label class="custom-control-label" for="enableMerge">Gabungkan Tanggal & Jam?</label>
                                        <small class="form-text text-muted">Jika dicentang, kolom Tanggal & Jam akan digabung dan kolom asli dihapus. Jika tidak, hanya hapus duplikat.</small>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Kolom Nama Karyawan (Cek Duplikat)</label>
                                            <input type="text" class="form-control" name="col_name" value="D" required style="text-transform:uppercase">
                                            <small class="text-muted">Kolom acuan untuk menghapus duplikat.</small>
                                        </div>
                                        <div class="form-group">
                                            <label>Kolom Target (Hasil Gabungan)</label>
                                            <input type="text" class="form-control" name="col_target" value="B" required style="text-transform:uppercase">
                                            <small class="text-muted">Kolom di mana tanggal & jam gabungan akan disimpan.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Kolom Sumber Tanggal (Date)</label>
                                            <input type="text" class="form-control" name="col_date" value="B" required style="text-transform:uppercase">
                                            <small class="text-muted">Sumber Tanggal (Contoh: B).</small>
                                        </div>
                                        <div class="form-group">
                                            <label>Kolom Sumber Jam (Time)</label>
                                            <input type="text" class="form-control" name="col_time" value="C" required style="text-transform:uppercase">
                                            <small class="text-muted">Sumber Jam (Contoh: C). Akan dihapus setelah digabung.</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-warning" id="mergeWarning">
                                    <i class="icon fas fa-exclamation-triangle"></i> Perhatian: Kolom Sumber Tanggal & Jam akan <strong>DIHAPUS</strong> dari file hasil.
                                </div>
                            </div>
                            <div class="card-footer text-right">
                                <button type="submit" class="btn btn-success"><i class="fas fa-cogs"></i> Proses & Download</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<!-- jQuery is already included in footer.php -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function () {
  // Manual handler for file input change to ensure label updates
  $('#fileExcel').on('change', function(e){
      var fileName = e.target.files[0].name;
      $(this).next('.custom-file-label').html(fileName);
  });

  // Toggle Merge Options
  $('#enableMerge').change(function() {
      var isChecked = $(this).is(':checked');
      if (isChecked) {
          $('input[name="col_target"], input[name="col_date"], input[name="col_time"]').closest('.form-group').slideDown();
          $('#mergeWarning').slideDown();
          $('input[name="col_target"], input[name="col_date"], input[name="col_time"]').prop('required', true);
      } else {
          $('input[name="col_target"], input[name="col_date"], input[name="col_time"]').closest('.form-group').slideUp();
          $('#mergeWarning').slideUp();
          $('input[name="col_target"], input[name="col_date"], input[name="col_time"]').prop('required', false);
      }
  });

  // Trigger change on load to set initial state
  $('#enableMerge').trigger('change');

  // Handle Form Submission via Fetch
  $('#excelForm').on('submit', function(e) {
      e.preventDefault();

      var formData = new FormData(this);

      // SweetAlert Loading
      Swal.fire({
          title: 'Sedang Memproses...',
          html: 'Mohon tunggu sebentar, file sedang diproses.',
          allowOutsideClick: false,
          didOpen: () => {
              Swal.showLoading();
          }
      });



      fetch('process.php', {
          method: 'POST',
          body: formData
      })
      .then(response => {
          // Check content type to distinguish between File (Blob) and Error (JSON)
          const contentType = response.headers.get("content-type");
          
          if (!response.ok) {
              if (contentType && contentType.indexOf("application/json") !== -1) {
                  return response.json().then(data => {
                      throw new Error(data.message || "Terjadi kesalahan server (Status " + response.status + ")");
                  });
              }
              // Fallback for non-JSON errors (like HTML Fatal Error)
              return response.text().then(text => {
                   throw new Error("Server Error: " + response.status + " - " + text.substring(0, 100)); // Show preview
              });
          }

          if (contentType && contentType.indexOf("application/json") !== -1) {
              // Server sent JSON even on 200 OK (Logical Error)
              return response.json().then(data => {
                  throw new Error(data.message || "Terjadi kesalahan logis.");
              });
          } 
          
          // CRITICAL CHECK: If we get text/html, it's a PHP Fatal Error that happened before header() output
          if (contentType && contentType.indexOf("text/html") !== -1) {
               return response.text().then(text => {
                   // Extract a clean message if possible, or show snippet
                   var cleanText = text.replace(/<[^>]*>?/gm, ' ').substring(0, 300);
                   throw new Error("Server Fatal Error: " + cleanText);
               });
          }

          // Otherwise assume it's the valid file
          return response.blob();
      })
      .then(blob => {
          // Verify blob size/type if needed
          if (blob.size === 0) {
               throw new Error("File hasil kosong.");
          }

          // Create Download Link
          var url = window.URL.createObjectURL(blob);
          var a = document.createElement('a');
          a.href = url;
          // Clean filename extraction or default
          a.download = "Processed_" + (document.getElementById('fileExcel').files[0].name.replace(/\.[^/.]+$/, "") + ".xlsx");
          document.body.appendChild(a);
          a.click();
          a.remove();
          window.URL.revokeObjectURL(url);

          // Success Message
          Swal.fire({
              icon: 'success',
              title: 'Selesai!',
              text: 'File berhasil diproses dan diunduh.',
              timer: 2000,
              showConfirmButton: false
          });
      })
      .catch(error => {
          console.error('Error:', error);
          Swal.fire({
              icon: 'error',
              title: 'Gagal!',
              text: error.message || 'Terjadi kesalahan saat memproses file.'
          });
      });
  });
});
</script>
