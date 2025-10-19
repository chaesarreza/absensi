<?php
session_start();
require_once '../config/db.php';
require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

if (!isset($_GET['kelas_id']) || !is_numeric($_GET['kelas_id'])) {
    die("ID Kelas tidak valid.");
}

$kelas_id = $_GET['kelas_id'];

// Ambil data siswa di kelas ini
$stmt = $koneksi->prepare("SELECT nama_siswa, nisn, qr_code_key FROM siswa WHERE kelas_id = ? ORDER BY nama_siswa ASC");
$stmt->bind_param("i", $kelas_id);
$stmt->execute();
$siswa_result = $stmt->get_result();
$siswa_list = $siswa_result->fetch_all(MYSQLI_ASSOC);

// Ambil nama kelas dan wali kelas untuk kop surat
$kelas_info_stmt = $koneksi->prepare("SELECT k.nama_kelas, u.nama_lengkap as wali_kelas 
                                    FROM kelas k 
                                    LEFT JOIN users u ON k.wali_kelas_id = u.id 
                                    WHERE k.id = ?");
$kelas_info_stmt->bind_param("i", $kelas_id);
$kelas_info_stmt->execute();
$kelas_info = $kelas_info_stmt->get_result()->fetch_assoc();
$nama_kelas = $kelas_info['nama_kelas'];
$wali_kelas = $kelas_info['wali_kelas'] ?? 'Belum Ditentukan';

// Mulai membangun konten HTML untuk PDF
$html = '
<!DOCTYPE html>
<html>
<head>
<style>
    @page { margin: 20px; }
    body { font-family: "Helvetica", sans-serif; }
    .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
    h1 { font-size: 18px; margin: 0; }
    p { font-size: 14px; margin: 5px 0; }
    
    /* STRUKTUR TABEL UNTUK GRID */
    .card-table { width: 100%; border-collapse: separate; border-spacing: 5px; }
    .card-table td {
        width: 25%; /* 4 kolom */
        height: 125px; /* Tinggi kartu */
        border: 1px solid #ccc;
        border-radius: 8px;
        padding: 8px;
        text-align: center;
        vertical-align: top;
        page-break-inside: avoid;
    }

    .card-content img {
        width: 75px;
        height: 75px;
        margin-bottom: 5px;
    }
    .card-content .nama-siswa {
        font-size: 9px;
        font-weight: bold;
        margin: 0;
        line-height: 1.1;
    }
    .card-content .nisn {
        font-size: 8px;
        margin: 2px 0 0 0;
        color: #555;
    }
</style>
</head>
<body>
    <div class="header">
        <h1>DAFTAR QR CODE ABSENSI SISWA</h1>
        <p>Kelas: '.htmlspecialchars($nama_kelas).' | Wali Kelas: '.htmlspecialchars($wali_kelas).'</p>
    </div>
    <table class="card-table">';

if (!empty($siswa_list)) {
    $col_count = 0;
    foreach ($siswa_list as $siswa) {
        if ($col_count % 4 == 0) { // Mulai baris baru setiap 4 kartu
            $html .= '<tr>';
        }
        
        $qr_data = $siswa['qr_code_key'];
        $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=" . urlencode($qr_data);
        
        $html .= '
            <td>
                <div class="card-content">
                    <img src="'.$qr_code_url.'" alt="QR Code">
                    <p class="nama-siswa">'.htmlspecialchars($siswa['nama_siswa']).'</p>
                    <p class="nisn">'.htmlspecialchars($siswa['nisn']).'</p>
                </div>
            </td>';
        
        $col_count++;
        if ($col_count % 4 == 0) { // Tutup baris setiap 4 kartu
            $html .= '</tr>';
        }
    }
    // Jika jumlah siswa bukan kelipatan 4, tutup baris terakhir
    if ($col_count % 4 != 0) {
        // Tambah sel kosong untuk melengkapi baris
        while ($col_count % 4 != 0) {
            $html .= '<td></td>';
            $col_count++;
        }
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td>Tidak ada siswa di kelas ini.</td></tr>';
}

$html .= '
    </table>
</body>
</html>';

// Inisialisasi Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$fileName = 'Daftar_QR_Code_' . str_replace(' ', '_', $nama_kelas) . '.pdf';
$dompdf->stream($fileName, ["Attachment" => 1]);

?>