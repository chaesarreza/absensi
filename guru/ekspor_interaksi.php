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
    $bulan_map = [
        1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni',
        7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'
    ];
    return date('d', $timestamp) . ' ' . $bulan_map[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
}

// Ambil parameter
$id_guru = $_SESSION['user_id'];
$jadwal_id = $_GET['jadwal_id'] ?? 0;
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

if (empty($jadwal_id)) { die("Parameter jadwal_id tidak lengkap."); }

// Ambil info dasar
$result_settings = $koneksi->query("SELECT setting_name, setting_value FROM settings");
$settings = array_column($result_settings->fetch_all(MYSQLI_ASSOC), 'setting_value', 'setting_name');

$stmt_jadwal = $koneksi->prepare("SELECT k.id as kelas_id, m.id as mapel_id, k.nama_kelas, m.nama_mapel, u.nama_lengkap as nama_guru, u.nip as nip_guru FROM jadwal_mengajar j JOIN kelas k ON j.kelas_id=k.id JOIN mata_pelajaran m ON j.mapel_id=m.id JOIN users u ON j.guru_id=u.id WHERE j.id = ? AND j.guru_id = ?");
$stmt_jadwal->bind_param("ii", $jadwal_id, $id_guru);
$stmt_jadwal->execute();
$info = $stmt_jadwal->get_result()->fetch_assoc();
if (!$info) { die("Jadwal tidak valid."); }

$nama_bulan = get_nama_bulan_id($bulan);
$tanggal_akhir_bulan = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
$tanggal_laporan_lengkap = $tanggal_akhir_bulan . ' ' . $nama_bulan . ' ' . $tahun;

// --- PERSIAPAN KONTEN LAPORAN ---
$html_content = '';
$fileName = 'Laporan_Interaksi_Gabungan_' . $info['nama_kelas'] . '_' . $nama_bulan . '.pdf';
$page_orientation = 'landscape'; // Diubah ke landscape agar lebih lebar

// ==== KOP SURAT & TANDA TANGAN ====
$logo_path = '../assets/img/logo.png';
$logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
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

// ==== LOGIKA PENGGABUNGAN DATA INTERAKSI ====

// 1. Ambil semua siswa di kelas tersebut
$stmt_siswa = $koneksi->prepare("SELECT id, nama_siswa FROM siswa WHERE kelas_id = ? ORDER BY nama_siswa ASC");
$stmt_siswa->bind_param("i", $info['kelas_id']);
$stmt_siswa->execute();
$daftar_siswa = $stmt_siswa->get_result()->fetch_all(MYSQLI_ASSOC);

// 2. Ambil total poin semua siswa dan simpan dalam map
$stmt_poin = $koneksi->prepare("SELECT siswa_id, COUNT(id) as total_poin FROM poin_keaktifan WHERE jadwal_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? GROUP BY siswa_id");
$stmt_poin->bind_param("iss", $jadwal_id, $bulan, $tahun);
$stmt_poin->execute();
$result_poin = $stmt_poin->get_result();
$poin_map = [];
while($row = $result_poin->fetch_assoc()) {
    $poin_map[$row['siswa_id']] = $row['total_poin'];
}

// 3. Ambil semua catatan perilaku siswa dan simpan dalam map
$stmt_catatan = $koneksi->prepare("SELECT siswa_id, tanggal, jenis_catatan, catatan FROM catatan_perilaku WHERE jadwal_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? ORDER BY tanggal ASC");
$stmt_catatan->bind_param("iss", $jadwal_id, $bulan, $tahun);
$stmt_catatan->execute();
$result_catatan = $stmt_catatan->get_result();
$catatan_map = [];
while($row = $result_catatan->fetch_assoc()) {
    $catatan_map[$row['siswa_id']][] = $row;
}

// ==== BANGUN TABEL HTML GABUNGAN ====
$html_content .= '<div style="text-align: center; margin-top: 20px;"><h3 style="font-size: 14px; text-decoration: underline;">LAPORAN INTERAKSI SISWA</h3><p style="font-size:12px;">Bulan: '.$nama_bulan.' '.$tahun.' | Kelas: '.htmlspecialchars($info['nama_kelas']).' | Mapel: '.htmlspecialchars($info['nama_mapel']).'</p></div>';
$html_content .= '<table style="font-size:10px; margin-top: 15px;">
                    <thead>
                        <tr>
                            <th style="width: 5%;">No</th>
                            <th class="nama" style="width: 25%;">Nama Siswa</th>
                            <th style="width: 10%;">Total Poin</th>
                            <th class="nama" style="width: 60%;">Rincian Catatan Perilaku</th>
                        </tr>
                    </thead>
                    <tbody>';

if (count($daftar_siswa) > 0) {
    $no = 1;
    foreach ($daftar_siswa as $siswa) {
        $siswa_id = $siswa['id'];
        $total_poin = $poin_map[$siswa_id] ?? 0;
        $catatan_siswa = $catatan_map[$siswa_id] ?? [];
        
        $catatan_html = '';
        if (!empty($catatan_siswa)) {
            foreach($catatan_siswa as $catatan) {
                $jenis_label = ucfirst($catatan['jenis_catatan']);
                $warna = ($catatan['jenis_catatan'] == 'Positif') ? 'green' : 'red';
                $catatan_html .= '<div style="margin-bottom: 5px; padding-left: 5px; border-left: 2px solid '.$warna.';">';
                $catatan_html .= '<strong>' . date('d/m/Y', strtotime($catatan['tanggal'])) . ' - ' . $jenis_label . ':</strong><br>';
                $catatan_html .= nl2br(htmlspecialchars($catatan['catatan']));
                $catatan_html .= '</div>';
            }
        } else {
            $catatan_html = '<span style="color:#888;">- Tidak ada catatan -</span>';
        }

        $html_content .= '<tr>
                            <td>' . $no++ . '</td>
                            <td class="nama">' . htmlspecialchars($siswa['nama_siswa']) . '</td>
                            <td>' . $total_poin . ' Poin</td>
                            <td class="nama">' . $catatan_html . '</td>
                          </tr>';
    }
} else {
    $html_content .= '<tr><td colspan="4">Tidak ada data siswa di kelas ini.</td></tr>';
}
$html_content .= '</tbody></table>';


// ==== RAKIT HTML & GENERATE PDF ====
$full_html = '
<!DOCTYPE html><html><head><style>
    @page { margin: 25px; } body { font-family: "Helvetica", sans-serif; }
    h1,h2,h3 { margin: 0; } p { margin: 2px 0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 6px; text-align: center; vertical-align: top; }
    th { background-color: #f2f2f2; } 
    td.nama { text-align: left; }
</style></head><body>' . $kop_surat . $html_content . $tanda_tangan . '</body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($full_html);
$dompdf->setPaper('A4', $page_orientation);
$dompdf->render();
$dompdf->stream($fileName, ["Attachment" => 1]);
?>