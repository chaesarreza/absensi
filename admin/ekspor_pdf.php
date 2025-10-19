<?php
session_start();
require_once '../config/db.php';
require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { die("Akses ditolak."); }

// Ambil filter dari URL
$filter_kelas = $_GET['kelas_id'] ?? '';
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $filter_bulan, $filter_tahun);
$nama_bulan = date('F', mktime(0, 0, 0, $filter_bulan, 10));

// --- LOGIKA BARU UNTUK MENGELOMPOKKAN DATA ---

// 1. Ambil semua siswa dan kelompokkan per kelas
$query_siswa = "SELECT siswa.id, siswa.nama_siswa, siswa.nisn, kelas.nama_kelas 
                FROM siswa JOIN kelas ON siswa.kelas_id = kelas.id";
if (!empty($filter_kelas)) { 
    $query_siswa .= " WHERE siswa.kelas_id = " . (int)$filter_kelas; 
}
$query_siswa .= " ORDER BY kelas.nama_kelas, siswa.nama_siswa ASC";
$result_siswa = $koneksi->query($query_siswa);

$siswa_per_kelas = [];
while ($row = $result_siswa->fetch_assoc()) {
    $siswa_per_kelas[$row['nama_kelas']][] = $row;
}

// 2. Ambil semua data absensi untuk bulan & tahun yang dipilih
$query_absen = "SELECT siswa_id, DAY(tanggal) as tgl, status FROM absensi WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?";
$stmt_absen = $koneksi->prepare($query_absen);
$stmt_absen->bind_param("ss", $filter_bulan, $filter_tahun);
$stmt_absen->execute();
$result_absen = $stmt_absen->get_result();
$kehadiran = [];
while ($row = $result_absen->fetch_assoc()) {
    $kehadiran[$row['siswa_id']][$row['tgl']] = $row['status'][0];
}

// --- AKHIR LOGIKA PENGELOMPOKKAN ---


// Mulai membangun konten HTML untuk PDF
$html = '
<!DOCTYPE html>
<html>
<head>
<style>
    @page { margin: 20px; }
    body { font-family: sans-serif; }
    .header { text-align: center; margin-bottom: 20px; }
    h1 { font-size: 20px; margin: 0; } p { font-size: 14px; margin: 5px 0; }
    table { width: 100%; border-collapse: collapse; font-size: 9px; } /* Ukuran font dikecilkan agar muat */
    th, td { border: 1px solid #000; padding: 5px; text-align: center; }
    th { background-color: #f2f2f2; } 
    .nama { text-align: left; width: 150px; } /* Lebar kolom nama tetap */
    .page-break { page-break-after: always; } /* Ini untuk membuat halaman baru */
</style>
</head>
<body>';

$first_page = true;
foreach ($siswa_per_kelas as $nama_kelas => $siswa_list) {
    if (!$first_page) {
        $html .= '<div class="page-break"></div>'; // Tambahkan page break sebelum halaman kelas berikutnya
    }
    $html .= '
    <div class="header">
        <h1>Laporan Absensi Bulanan</h1>
        <p>Kelas: '.htmlspecialchars($nama_kelas).' | Bulan: '.$nama_bulan.' '.$filter_tahun.'</p>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 25px;">No</th>
                <th class="nama">Nama Siswa</th>';
    for ($i = 1; $i <= $days_in_month; $i++) { $html .= "<th style='width: 20px;'>$i</th>"; }
    $html .= '</tr></thead><tbody>';

    $no = 1;
    foreach ($siswa_list as $siswa) {
        $html .= '<tr>
                    <td>'.$no++.'</td>
                    <td class="nama">'.htmlspecialchars($siswa['nama_siswa']).'</td>';
        for ($i = 1; $i <= $days_in_month; $i++) {
            $status = $kehadiran[$siswa['id']][$i] ?? '';
            $html .= "<td>$status</td>";
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $first_page = false;
}

if (empty($siswa_per_kelas)) {
    $html .= '<div class="header"><h1>Laporan Absensi Bulanan</h1><p>Bulan: '.$nama_bulan.' '.$filter_tahun.'</p></div><p>Tidak ada data untuk filter yang dipilih.</p>';
}

$html .= '</body></html>';


// Inisialisasi Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$fileName = "Laporan_Bulanan_{$nama_bulan}_{$filter_tahun}.pdf";
$dompdf->stream($fileName, ["Attachment" => 1]);
?>