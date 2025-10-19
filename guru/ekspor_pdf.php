<?php
session_start();
require_once '../config/db.php';
require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') { die("Akses ditolak."); }

// (Kode PHP untuk mengambil data sama persis dengan ekspor_excel.php)
$id_guru = $_SESSION['user_id'];
$filter_jadwal_id = $_GET['jadwal_id'] ?? '';
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
if (empty($filter_jadwal_id)) { die("Harap pilih jadwal mengajar terlebih dahulu."); }
$stmt_jadwal = $koneksi->prepare("SELECT kelas_id, mapel_id, k.nama_kelas, m.nama_mapel FROM jadwal_mengajar j JOIN kelas k ON j.kelas_id=k.id JOIN mata_pelajaran m ON j.mapel_id=m.id WHERE j.id = ? AND j.guru_id = ?");
$stmt_jadwal->bind_param("ii", $filter_jadwal_id, $id_guru);
$stmt_jadwal->execute();
$jadwal_detail = $stmt_jadwal->get_result()->fetch_assoc();
if (!$jadwal_detail) { die("Jadwal tidak valid."); }
$kelas_id = $jadwal_detail['kelas_id'];
$mapel_id = $jadwal_detail['mapel_id'];
$nama_kelas = $jadwal_detail['nama_kelas'];
$nama_mapel = $jadwal_detail['nama_mapel'];
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $filter_bulan, $filter_tahun);
$nama_bulan = date('F', mktime(0, 0, 0, $filter_bulan, 10));
$stmt_siswa = $koneksi->prepare("SELECT id, nama_siswa FROM siswa WHERE kelas_id = ? ORDER BY nama_siswa ASC");
$stmt_siswa->bind_param("i", $kelas_id);
$stmt_siswa->execute();
$siswa_list = $stmt_siswa->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_absen = $koneksi->prepare("SELECT siswa_id, DAY(tanggal) as tgl, status FROM absensi WHERE mapel_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmt_absen->bind_param("iss", $mapel_id, $filter_bulan, $filter_tahun);
$stmt_absen->execute();
$result_absen = $stmt_absen->get_result();
$kehadiran = [];
while ($row = $result_absen->fetch_assoc()) {
    $kehadiran[$row['siswa_id']][$row['tgl']] = substr($row['status'], 0, 1);
}

// Membangun HTML untuk PDF
$html = '
<!DOCTYPE html><html><head><style>
    @page { margin: 20px; } body { font-family: sans-serif; }
    .header { text-align: center; margin-bottom: 20px; }
    h1 { font-size: 18px; margin: 0; } p { font-size: 12px; margin: 3px 0; }
    table { width: 100%; border-collapse: collapse; font-size: 9px; }
    th, td { border: 1px solid #000; padding: 4px; text-align: center; }
    th { background-color: #f2f2f2; } .nama { text-align: left; width: 150px; }
</style></head><body>
    <div class="header">
        <h1>Rekap Absensi Bulanan</h1>
        <p>Kelas: '.htmlspecialchars($nama_kelas).' | Mata Pelajaran: '.htmlspecialchars($nama_mapel).'</p>
        <p>Bulan: '.$nama_bulan.' '.$filter_tahun.'</p>
    </div>
    <table><thead><tr>
        <th style="width: 25px;">No</th><th class="nama">Nama Siswa</th>';
for ($i = 1; $i <= $days_in_month; $i++) { $html .= "<th style='width: 18px;'>$i</th>"; }
$html .= '</tr></thead><tbody>';
if (!empty($siswa_list)) {
    $no = 1;
    foreach ($siswa_list as $siswa) {
        $html .= '<tr><td>'.$no++.'</td><td class="nama">'.htmlspecialchars($siswa['nama_siswa']).'</td>';
        for ($i = 1; $i <= $days_in_month; $i++) {
            $status = $kehadiran[$siswa['id']][$i] ?? '';
            $html .= "<td>$status</td>";
        }
        $html .= '</tr>';
    }
} else { $html .= '<tr><td colspan="'.($days_in_month + 2).'">Tidak ada data.</td></tr>'; }
$html .= '</tbody></table></body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$fileName = "Absensi_{$nama_kelas}_{$nama_mapel}_{$nama_bulan}_{$filter_tahun}.pdf";
$dompdf->stream($fileName, ["Attachment" => 1]);
?>