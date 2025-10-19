<?php
session_start();
require_once '../config/db.php';
require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') { die("Akses ditolak."); }

// --- FUNGSI BANTUAN ---
function get_nama_bulan_id($bulan_angka) {
    $daftar_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
    return $daftar_bulan[$bulan_angka] ?? '';
}
function format_tanggal_indonesia($tanggal_db) {
    if (empty($tanggal_db)) return '';
    $timestamp = strtotime($tanggal_db);
    $bulan_map = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];
    return date('d', $timestamp) . ' ' . $bulan_map[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
}

// Ambil parameter
$id_guru = $_SESSION['user_id'];
$jadwal_id = $_GET['jadwal_id'] ?? 0;
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

if (empty($jadwal_id)) { die("Parameter jadwal_id tidak lengkap."); }

// Ambil info dasar (settings, jadwal, kelas, dll.)
$result_settings = $koneksi->query("SELECT setting_name, setting_value FROM settings");
$settings = array_column($result_settings->fetch_all(MYSQLI_ASSOC), 'setting_value', 'setting_name');
$stmt_jadwal = $koneksi->prepare("SELECT k.id as kelas_id, k.nama_kelas, m.nama_mapel, u.nama_lengkap as nama_guru, u.nip as nip_guru FROM jadwal_mengajar j JOIN kelas k ON j.kelas_id=k.id JOIN mata_pelajaran m ON j.mapel_id=m.id JOIN users u ON j.guru_id=u.id WHERE j.id = ? AND j.guru_id = ?");
$stmt_jadwal->bind_param("ii", $jadwal_id, $id_guru);
$stmt_jadwal->execute();
$info = $stmt_jadwal->get_result()->fetch_assoc();
if (!$info) { die("Jadwal tidak valid."); }

$nama_bulan = get_nama_bulan_id($bulan);
$tanggal_laporan_lengkap = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun) . ' ' . $nama_bulan . ' ' . $tahun;

// --- KOP SURAT & TANDA TANGAN ---
// (Anda bisa menyalin dari file ekspor_laporan.php jika perlu)
$logo_path = '../assets/img/logo.png';
$logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
$kop_surat = '...'; // Isi dengan HTML Kop Surat Anda
$tanda_tangan = '...'; // Isi dengan HTML Tanda Tangan Anda
// (Kode kop surat dan tanda tangan disembunyikan untuk keringkasan, tapi harus ada di file Anda)

$kop_surat = '
<table style="width: 100%; border-bottom: 3px solid #000; margin-bottom: 20px;">
    <tr>
        <td style="width: 15%; text-align: right; border: none;"><img src="' . $logo_base64 . '" style="width: 70px;"></td>
        <td style="width: 70%; text-align: center; border: none;">
            <h1 style="font-size: 16px; margin: 0;">KEMENTERIAN AGAMA REPUBLIK INDONESIA</h1>
            <h2 style="font-size: 18px; margin: 5px 0; font-weight: bold;">' . htmlspecialchars($settings['nama_madrasah']) . '</h2>
            <p style="font-size: 11px; margin: 0;">' . htmlspecialchars($settings['alamat_madrasah']) . '</p>
        </td>
        <td style="width: 15%; border: none;"></td>
    </tr>
</table>';
$tanda_tangan = '
<table style="width: 100%; margin-top: 40px; font-size: 12px; page-break-inside: avoid; border: none;">
    <tr>
        <td style="width: 50%; border: none; text-align: center;">
            <p>Mengetahui,<br>Kepala Madrasah</p><br><br><br>
            <p style="font-weight: bold; text-decoration: underline;">' . htmlspecialchars($settings['nama_kepala']) . '</p>
            <p>NIP. ' . htmlspecialchars($settings['nip_kepala']) . '</p>
        </td>
        <td style="width: 50%; text-align: center; border: none;">
            <p>Aceh Timur, ' . $tanggal_laporan_lengkap . '<br>Guru Mata Pelajaran</p><br><br><br>
            <p style="font-weight: bold; text-decoration: underline;">' . htmlspecialchars($info['nama_guru']) . '</p>
            <p>NIP. ' . htmlspecialchars($info['nip_guru']) . '</p>
        </td>
    </tr>
</table>';

// --- LOGIKA PENGUMPULAN DATA ---
// 1. Ambil semua siswa di kelas tersebut
$stmt_siswa = $koneksi->prepare("SELECT id, nama_siswa FROM siswa WHERE kelas_id = ? ORDER BY nama_siswa ASC");
$stmt_siswa->bind_param("i", $info['kelas_id']);
$stmt_siswa->execute();
$daftar_siswa = $stmt_siswa->get_result()->fetch_all(MYSQLI_ASSOC);

// 2. Ambil semua catatan perilaku pada periode ini dan proses ke dalam map
$stmt_catatan = $koneksi->prepare("SELECT siswa_id, tanggal, jenis_catatan, catatan FROM catatan_perilaku WHERE jadwal_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? ORDER BY tanggal ASC");
$stmt_catatan->bind_param("iss", $jadwal_id, $bulan, $tahun);
$stmt_catatan->execute();
$result_catatan = $stmt_catatan->get_result();

$catatan_map = [];
$tanggal_unik = [];
$detail_catatan = [];

while($row = $result_catatan->fetch_assoc()) {
    // Kumpulkan tanggal unik untuk header tabel
    if (!in_array($row['tanggal'], $tanggal_unik)) {
        $tanggal_unik[] = $row['tanggal'];
    }
    // Buat simbol untuk sel tabel
    $simbol = ($row['jenis_catatan'] == 'Positif') 
        ? '<span style="color:green; font-weight:bold;">(+)</span>' 
        : '<span style="color:red; font-weight:bold;">(-)</span>';
    
    // Simpan simbol ke map
    if (!isset($catatan_map[$row['siswa_id']][$row['tanggal']])) {
        $catatan_map[$row['siswa_id']][$row['tanggal']] = '';
    }
    $catatan_map[$row['siswa_id']][$row['tanggal']] .= $simbol . ' ';

    // Simpan detail catatan untuk lampiran
    $detail_catatan[] = $row;
}
sort($tanggal_unik); // Pastikan tanggal urut

// --- MEMBANGUN HTML ---
$html_content = '<div style="text-align: center;"><h3 style="font-size: 14px; text-decoration: underline;">REKAPITULASI CATATAN PERILAKU SISWA</h3><p style="font-size:12px;">Bulan: '.$nama_bulan.' '.$tahun.' | Kelas: '.htmlspecialchars($info['nama_kelas']).' | Mapel: '.htmlspecialchars($info['nama_mapel']).'</p></div>';

// Bangun Tabel Utama
$html_content .= '<table style="font-size:9px; margin-top: 15px;"><thead><tr><th rowspan="2" style="width:5%;">No</th><th rowspan="2" class="nama" style="width:25%;">Nama Siswa</th><th colspan="'.count($tanggal_unik).'">Tanggal Pemberian Catatan</th></tr><tr>';
foreach ($tanggal_unik as $tgl) { $html_content .= "<th>".date('d/m', strtotime($tgl))."</th>"; }
$html_content .= '</tr></thead><tbody>';

$no = 1;
foreach ($daftar_siswa as $siswa) {
    $html_content .= '<tr><td>'.$no++.'</td><td class="nama">'.htmlspecialchars($siswa['nama_siswa']).'</td>';
    foreach ($tanggal_unik as $tgl) {
        $simbol = $catatan_map[$siswa['id']][$tgl] ?? '';
        $html_content .= '<td>'.$simbol.'</td>';
    }
    $html_content .= '</tr>';
}
$html_content .= '</tbody></table>';

// Bangun Lampiran Rincian Catatan
if (!empty($detail_catatan)) {
    $html_content .= '<div style="margin-top: 20px; font-size: 10px; page-break-inside: avoid;">';
    $html_content .= '<h4 style="font-size:12px; text-decoration:underline; margin-bottom: 5px;">Rincian Catatan:</h4>';
    $html_content .= '<table style="font-size:9px;"><thead><tr><th style="width:15%;">Tanggal</th><th class="nama" style="width:25%;">Nama Siswa</th><th style="width:10%;">Jenis</th><th class="nama">Isi Catatan</th></tr></thead><tbody>';
    foreach ($detail_catatan as $catatan) {
        // Cari nama siswa dari daftar siswa yang sudah diambil sebelumnya
        $nama_siswa_catatan = '';
        foreach($daftar_siswa as $s) {
            if ($s['id'] == $catatan['siswa_id']) {
                $nama_siswa_catatan = $s['nama_siswa'];
                break;
            }
        }
        $html_content .= '<tr>
                            <td>'.format_tanggal_indonesia($catatan['tanggal']).'</td>
                            <td class="nama">'.htmlspecialchars($nama_siswa_catatan).'</td>
                            <td>'.htmlspecialchars($catatan['jenis_catatan']).'</td>
                            <td class="nama">'.nl2br(htmlspecialchars($catatan['catatan'])).'</td>
                          </tr>';
    }
    $html_content .= '</tbody></table></div>';
}

// --- RAKIT PDF & OUTPUT ---
$full_html = '
<!DOCTYPE html><html><head><style>
    @page { margin: 25px; } body { font-family: "Helvetica", sans-serif; }
    h1,h2,h3,h4 { margin: 0; } p { margin: 2px 0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 4px; text-align: center; vertical-align: middle; }
    th { background-color: #f2f2f2; } 
    td.nama { text-align: left; }
</style></head><body>' . $kop_surat . $html_content . $tanda_tangan . '</body></html>';

$fileName = 'Rekap_Catatan_Perilaku_' . $info['nama_kelas'] . '_' . $nama_bulan . '.pdf';
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($full_html);
$dompdf->setPaper('A4', 'landscape'); // Gunakan landscape agar muat banyak tanggal
$dompdf->render();
$dompdf->stream($fileName, ["Attachment" => 1]);
?>