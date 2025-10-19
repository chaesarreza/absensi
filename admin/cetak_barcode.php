<?php
session_start();
require_once '../config/db.php';

// Cek ID kelas dari URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Akses tidak valid.");
}

$id_kelas = $_GET['id'];
$stmt = $koneksi->prepare("SELECT nama_kelas, barcode_key FROM kelas WHERE id = ?");
$stmt->bind_param("i", $id_kelas);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Kelas tidak ditemukan.");
}
$kelas = $result->fetch_assoc();

// Membuat URL lengkap yang akan di-encode ke dalam QR Code
// Ganti 'absensi-madrasah' jika nama folder Anda berbeda
$url_absensi = "http://localhost/absensi-madrasah/absen.php?key=" . urlencode($kelas['barcode_key']);

// URL untuk generate QR Code via API
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($url_absensi);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak QR Code - <?= htmlspecialchars($kelas['nama_kelas']); ?></title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f0f0; }
        .print-container {
            width: 400px;
            margin: 50px auto;
            padding: 30px;
            background-color: white;
            border: 1px solid #ddd;
            text-align: center;
        }
        @media print {
            body { background-color: white; }
            .print-container { margin: 0; border: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <h3>QR Code Absensi</h3>
        <hr>
        <h1><?= htmlspecialchars($kelas['nama_kelas']); ?></h1>
        <p>Pindai (scan) kode ini dengan smartphone Anda untuk melakukan absensi.</p>
        <img src="<?= $qr_code_url; ?>" alt="QR Code Absensi">
        <p class="mt-3 text-muted" style="word-wrap: break-word;"><small><?= $url_absensi; ?></small></p>
        <button class="btn btn-primary no-print mt-3" onclick="window.print()">
            <i class="bi bi-printer"></i> Cetak Halaman Ini
        </button>
    </div>
</body>
</html>