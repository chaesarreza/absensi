<?php
session_start();
require_once '../config/db.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { die("Akses ditolak."); }

$filter_kelas = $_GET['kelas_id'] ?? '';
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $filter_bulan, $filter_tahun);
$nama_bulan = date('F', mktime(0, 0, 0, $filter_bulan, 10));

// 1. Ambil semua siswa berdasarkan filter kelas
$query_siswa = "SELECT siswa.id, nama_siswa, nisn, kelas.nama_kelas FROM siswa JOIN kelas ON siswa.kelas_id = kelas.id";
if (!empty($filter_kelas)) { $query_siswa .= " WHERE kelas_id = " . (int)$filter_kelas; }
$query_siswa .= " ORDER BY kelas.nama_kelas, nama_siswa ASC";
$result_siswa = $koneksi->query($query_siswa);
$siswa_list = $result_siswa->fetch_all(MYSQLI_ASSOC);

// 2. Ambil semua data absensi untuk bulan & tahun yang dipilih
$query_absen = "SELECT siswa_id, DAY(tanggal) as tgl, status FROM absensi WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?";
$stmt_absen = $koneksi->prepare($query_absen);
$stmt_absen->bind_param("ss", $filter_bulan, $filter_tahun);
$stmt_absen->execute();
$result_absen = $stmt_absen->get_result();
$kehadiran = [];
while ($row = $result_absen->fetch_assoc()) {
    $kehadiran[$row['siswa_id']][$row['tgl']] = $row['status'][0]; // Ambil huruf pertama saja (H, S, I, A)
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Laporan Absensi $nama_bulan $filter_tahun");

// Judul
$sheet->mergeCells('A1:F1');
$sheet->setCellValue('A1', 'Laporan Absensi Bulanan');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->setCellValue('A2', "Bulan: $nama_bulan $filter_tahun");

// Header Tabel
$sheet->setCellValue('A4', 'No.');
$sheet->setCellValue('B4', 'NISN');
$sheet->setCellValue('C4', 'Nama Siswa');
$sheet->setCellValue('D4', 'Kelas');
// Kolom tanggal
$col = 'E';
for ($i = 1; $i <= $days_in_month; $i++) {
    $sheet->setCellValue($col . '4', $i);
    $sheet->getColumnDimension($col)->setWidth(3);
    $col++;
}
$sheet->getStyle('A4:' . $col . '4')->getFont()->setBold(true);
$sheet->getStyle('E4:' . $col . '4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);


// Isi data
$rowNum = 5;
$no = 1;
foreach ($siswa_list as $siswa) {
    $sheet->setCellValue('A' . $rowNum, $no++);
    $sheet->setCellValue('B' . $rowNum, $siswa['nisn']);
    $sheet->setCellValue('C' . $rowNum, $siswa['nama_siswa']);
    $sheet->setCellValue('D' . $rowNum, $siswa['nama_kelas']);

    $col = 'E';
    for ($i = 1; $i <= $days_in_month; $i++) {
        $status = $kehadiran[$siswa['id']][$i] ?? '-';
        $sheet->setCellValue($col . $rowNum, $status);
        $col++;
    }
    $rowNum++;
}

// Atur lebar kolom otomatis untuk A-D
foreach (range('A', 'D') as $columnID) { $sheet->getColumnDimension($columnID)->setAutoSize(true); }

$fileName = "Laporan_Absensi_{$nama_bulan}_{$filter_tahun}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();