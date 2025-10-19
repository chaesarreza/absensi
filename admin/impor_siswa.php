<?php
session_start();
$title = "Impor Data Siswa";
require_once '../config/db.php';
require_once 'templates/header.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_excel'])) {
    $file_mimes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

    if (isset($_FILES['file_excel']['name']) && in_array($_FILES['file_excel']['type'], $file_mimes)) {
        $spreadsheet = IOFactory::load($_FILES['file_excel']['tmp_name']);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        
        $sukses_count = 0; $gagal_count = 0;

        for ($i = 2; $i <= count($sheetData); $i++) {
            try {
                $nisn = $sheetData[$i]['B'];
                $nama = $sheetData[$i]['C'];
                $kelas_nama = $sheetData[$i]['D'];
                
                if (empty($nisn) || empty($nama) || empty($kelas_nama)) {
                    $gagal_count++; continue;
                }

                $stmt_kelas = $koneksi->prepare("SELECT id FROM kelas WHERE nama_kelas = ?");
                $stmt_kelas->bind_param("s", $kelas_nama);
                $stmt_kelas->execute();
                $result_kelas = $stmt_kelas->get_result();
                $kelas_data = $result_kelas->fetch_assoc();

                if ($kelas_data) {
                    $kelas_id = $kelas_data['id'];
                    $stmt = $koneksi->prepare("INSERT INTO siswa (nisn, nama_siswa, kelas_id, qr_code_key) VALUES (?, ?, ?, UUID()) ON DUPLICATE KEY UPDATE nama_siswa=VALUES(nama_siswa), kelas_id=VALUES(kelas_id)");
                    $stmt->bind_param("ssi", $nisn, $nama, $kelas_id);
                    if($stmt->execute()){ $sukses_count++; } else { $gagal_count++; }
                } else { $gagal_count++; }
            } catch (Exception $e) { $gagal_count++; }
        }
        $message = "Proses impor selesai. Berhasil: $sukses_count baris, Gagal: $gagal_count baris.";

    } else { $error = "File tidak valid. Harap unggah file Excel (.xlsx)."; }
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Impor Data Siswa dari Excel</h1>

    <?php if($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header"><h6 class="m-0">Panduan & Template</h6></div>
        <div class="card-body">
            <p>1. Unduh template excel.<br>2. Isi data siswa. Pastikan <strong>Nama Kelas</strong> yang dimasukkan sudah terdaftar di sistem.<br>3. Unggah kembali file yang sudah diisi.</p>
            <a href="../templates/template_siswa.xlsx" class="btn btn-primary" download>Unduh Template Siswa (.xlsx)</a>
        </div>
    </div>
    
    <div class="card shadow mb-4">
        <div class="card-header"><h6 class="m-0">Formulir Unggah</h6></div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Pilih File Excel (.xlsx)</label>
                    <input type="file" name="file_excel" class="form-control" accept=".xlsx" required>
                </div>
                <button type="submit" class="btn btn-success">Mulai Proses Impor</button>
                <a href="siswa.php" class="btn btn-secondary">Kembali</a>
            </form>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>