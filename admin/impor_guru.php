<?php
session_start();
$title = "Impor Data Guru";
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
        
        $sukses_count = 0;
        $gagal_count = 0;

        for ($i = 2; $i <= count($sheetData); $i++) {
            try {
                // Kolom 'A' (No. Urut) kita abaikan, data mulai dari 'B'
                $nip = $sheetData[$i]['B'];
                $nama = $sheetData[$i]['C'];
                $username = $sheetData[$i]['D'];
                $password = $sheetData[$i]['E'];

                if (empty($nama) || empty($username) || empty($password)) {
                    $gagal_count++;
                    continue;
                }
                
                // Hash password sebelum disimpan
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'guru';

                // Query INSERT... ON DUPLICATE KEY UPDATE berdasarkan username
                $stmt = $koneksi->prepare("INSERT INTO users (nip, nama_lengkap, username, password, role) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE nip=VALUES(nip), nama_lengkap=VALUES(nama_lengkap), password=VALUES(password), role=VALUES(role)");
                $stmt->bind_param("sssss", $nip, $nama, $username, $password_hash, $role);
                
                if($stmt->execute()){
                    $sukses_count++;
                } else {
                    $gagal_count++;
                }

            } catch (Exception $e) {
                $gagal_count++;
            }
        }
        $message = "Proses impor selesai. Berhasil: $sukses_count baris, Gagal: $gagal_count baris.";

    } else {
        $error = "File tidak valid. Harap unggah file Excel (.xlsx).";
    }
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Impor Data Guru dari Excel</h1>

    <?php if($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header"><h6 class="m-0">Panduan & Template</h6></div>
        <div class="card-body">
            <p>1. Unduh template excel yang disediakan.<br>2. Isi data guru sesuai format. Pastikan <strong>Username</strong> unik untuk setiap guru.<br>3. Unggah kembali file yang sudah diisi melalui form di bawah.</p>
            <a href="../templates/template_guru.xlsx" class="btn btn-primary" download>Unduh Template Guru (.xlsx)</a>
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
                <a href="guru.php" class="btn btn-secondary">Kembali</a>
            </form>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>