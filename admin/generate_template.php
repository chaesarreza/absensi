<?php
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { die("Akses ditolak."); }

$kelas_id = $_POST['kelas_id'];
$tanggal_mengajar_str = $_POST['tanggal_mengajar'];

// Ambil data kelas dan siswa
$stmt_kelas = $koneksi->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
$stmt_kelas->bind_param("i", $kelas_id);
$stmt_kelas->execute();
$nama_kelas = $stmt_kelas->get_result()->fetch_assoc()['nama_kelas'];

$stmt_siswa = $koneksi->prepare("SELECT nisn, nama_siswa FROM siswa WHERE kelas_id = ? ORDER BY nama_siswa ASC");
$stmt_siswa->bind_param("i", $kelas_id);
$stmt_siswa->execute();
$siswa_list = $stmt_siswa->get_result()->fetch_all(MYSQLI_ASSOC);

// Proses tanggal
$tanggal_arr = array_map('trim', explode(',', $tanggal_mengajar_str));

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Data Historis " . $nama_kelas);

// Header
$headers = [
    'Tanggal (YYYY-MM-DD)', 'NISN', 'Nama Siswa', 'Status Absen (H/S/I/A)',
    'Jenis Nilai', 'Nilai (0-100)', 'Tambah Poin (Angka)', 'Jenis Catatan (Positif/Negatif)', 'Catatan Perilaku',
    'Jurnal Materi Hari Itu'
];
$sheet->fromArray($headers, NULL, 'A1');

// Isi data
$rowNum = 2;
foreach ($tanggal_arr as $tanggal) {
    if (empty($tanggal)) continue;
    foreach ($siswa_list as $siswa) {
        $rowData = [
            $tanggal,
            $siswa['nisn'],
            $siswa['nama_siswa'],
            'H', // Default Hadir
            '', '', '', '', '', ''
        ];
        $sheet->fromArray($rowData, NULL, 'A' . $rowNum);
        $rowNum++;
    }
}

// Atur lebar kolom
foreach (range('A', 'J') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Download file
$fileName = 'Template_Historis_' . str_replace(' ', '_', $nama_kelas) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>