<?php
session_start();
require_once '../config/db.php';
require '../vendor/autoload.php';

use Mpdf\Mpdf;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') { die("Akses ditolak."); }

// --- FUNGSI BANTUAN --- (Tidak Berubah)
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

// Ambil parameter (Tidak Berubah)
$id_guru = $_SESSION['user_id'];
$jadwal_id = $_GET['jadwal_id'] ?? 0;
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
if (empty($jadwal_id)) { die("Parameter jadwal_id tidak lengkap."); }

// Ambil info dasar (Tidak Berubah)
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

// --- PERSIAPAN KONTEN LAPORAN --- (Tidak Berubah)
$html_content = '';
$fileName = 'Laporan_Interaksi_Gabungan_' . $info['nama_kelas'] . '_' . $nama_bulan . '.pdf';
$page_orientation = 'landscape';

// ==== KOP SURAT & TANDA TANGAN ==== (Tidak Berubah)
$logo_path = '../assets/img/logo.png';
$logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
$kop_surat = '
<table class="kop-surat" style="width: 100%; border-bottom: 3px solid #000; margin-bottom: 20px;">
    <tr>
        <td style="width: 15%; text-align: right;"><img src="' . $logo_base64 . '" style="width: 70px;"></td>
        <td style="width: 70%; text-align: center;">
            <h1 style="font-size: 16px; margin: 0;">KEMENTERIAN AGAMA REPUBLIK INDONESIA</h1>
            <h2 style="font-size: 18px; margin: 5px 0; font-weight: bold;">' . htmlspecialchars($settings['nama_madrasah']) . '</h2>
            <p style="font-size: 11px; margin: 0;">' . htmlspecialchars($settings['alamat_madrasah']) . '</p>
        </td>
        <td style="width: 15%;"></td>
    </tr>
</table>';
$tanda_tangan = '
<table class="tanda-tangan" style="width: 100%; margin-top: 40px; font-size: 12px; page-break-inside: avoid;">
    <tr>
        <td style="width: 50%; text-align: center;">
            <p>Mengetahui,<br>Kepala Madrasah</p><br><br><br>
            <p style="font-weight: bold; text-decoration: underline;">' . htmlspecialchars($settings['nama_kepala']) . '</p>
            <p>NIP. ' . htmlspecialchars($settings['nip_kepala']) . '</p>
        </td>
        <td style="width: 50%; text-align: center;">
            <p>Aceh Timur, ' . $tanggal_laporan_lengkap . '<br>Guru Mata Pelajaran</p><br><br><br>
            <p style="font-weight: bold; text-decoration: underline;">' . htmlspecialchars($info['nama_guru']) . '</p>
            <p>NIP. ' . htmlspecialchars($info['nip_guru']) . '</p>
        </td>
    </tr>
</table>';

// ==== LOGIKA PENGGABUNGAN DATA INTERAKSI ==== (Tidak Berubah)
$stmt_siswa = $koneksi->prepare("SELECT id, nama_siswa FROM siswa WHERE kelas_id = ? ORDER BY nama_siswa ASC");
$stmt_siswa->bind_param("i", $info['kelas_id']);
$stmt_siswa->execute();
$daftar_siswa = $stmt_siswa->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_poin = $koneksi->prepare("SELECT siswa_id, COUNT(id) as total_poin FROM poin_keaktifan WHERE jadwal_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? GROUP BY siswa_id");
$stmt_poin->bind_param("iss", $jadwal_id, $bulan, $tahun);
$stmt_poin->execute();
$result_poin = $stmt_poin->get_result();
$poin_map = [];
while($row = $result_poin->fetch_assoc()) { $poin_map[$row['siswa_id']] = $row['total_poin']; }
$stmt_catatan = $koneksi->prepare("SELECT siswa_id, tanggal, jenis_catatan, catatan FROM catatan_perilaku WHERE jadwal_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? ORDER BY tanggal ASC");
$stmt_catatan->bind_param("iss", $jadwal_id, $bulan, $tahun);
$stmt_catatan->execute();
$result_catatan = $stmt_catatan->get_result();
$catatan_map = [];
while($row = $result_catatan->fetch_assoc()) { $catatan_map[$row['siswa_id']][] = $row; }

// ==== BANGUN TABEL HTML GABUNGAN ==== (Tidak Berubah)
$html_content .= '<div style="text-align: center; margin-top: 20px;"><h3 style="font-size: 14px; text-decoration: underline;">LAPORAN INTERAKSI SISWA</h3><p style="font-size:12px;">Bulan: '.$nama_bulan.' '.$tahun.' | Kelas: '.htmlspecialchars($info['nama_kelas']).' | Mapel: '.htmlspecialchars($info['nama_mapel']).'</p></div>';
$html_content .= '<table class="main-table" style="font-size:10px; margin-top: 15px;">
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
        
        $catatan_html = '<table class="nested-table">';
        if (!empty($catatan_siswa)) {
            foreach($catatan_siswa as $catatan) {
                $jenis_label = htmlspecialchars(ucfirst($catatan['jenis_catatan']));
                $catatan_html .= '<tr>';
                $catatan_html .= '<td class="nested-tgl">' . date('d/m/Y', strtotime($catatan['tanggal'])) . '</td>';
                $catatan_html .= '<td class="nested-jenis">' . $jenis_label . '</td>';
                $catatan_html .= '<td class="nested-catatan">' . nl2br(htmlspecialchars($catatan['catatan'])) . '</td>';
                $catatan_html .= '</tr>';
            }
        } else {
             $catatan_html .= '<tr><td colspan="3" class="nested-empty">- Tidak ada catatan -</td></tr>';
        }
        $catatan_html .= '</table>';

        $html_content .= '<tr>
                            <td>' . $no++ . '</td>
                            <td class="nama">' . htmlspecialchars($siswa['nama_siswa']) . '</td>
                            <td>' . $total_poin . ' Poin</td>
                            <td class="nama catatan-wrapper">' . $catatan_html . '</td>
                          </tr>';
    }
} else {
    $html_content .= '<tr><td colspan="4">Tidak ada data siswa di kelas ini.</td></tr>';
}
$html_content .= '</tbody></table>';


// ==== RAKIT HTML & GENERATE PDF ====
// (PERBAIKAN TERAKHIR) CSS Diperbarui
$full_html = '
<!DOCTYPE html><html><head><style>
    @page { margin: 25px; } 
    body { font-family: "Helvetica", sans-serif; }
    h1,h2,h3 { margin: 0; } p { margin: 2px 0; }
    
    table { 
        width: 100%; 
        border-collapse: collapse; 
    }
    table.kop-surat td, 
    table.tanda-tangan td {
        border: none !important;
        padding: 2px;
    }

    table.main-table { 
        width: 100%; 
        border-collapse: collapse !important; 
    }
    
    /* === INI PERBAIKANNYA === */
    table.main-table th {
        border: 1px solid #ccc; /* Border halus */
        padding: 6px;
        text-align: center;
        vertical-align: middle; /* RATA TENGAH VERTIKAL */
        background-color: #f2f2f2;
    }
    table.main-table td {
        border: 1px solid #ccc; /* Border halus */
        padding: 6px;
        text-align: center;
        vertical-align: top; /* Data tetap rata atas */
    }
    /* === AKHIR PERBAIKAN === */
    
    table.main-table td.nama { text-align: left; }
    
    td.catatan-wrapper {
        padding: 0 !important; 
        text-align: left;
    }
    .nested-table { 
        width: 100%; 
        border: none !important; 
    }
    .nested-table td { 
        border: none !important; 
        border-top: 1px solid #eee !important; 
        padding: 4px; 
        text-align: left;
        vertical-align: top; 
    } 
    .nested-table tr:first-child td { 
        border-top: none !important; 
    }
    .nested-table td.nested-tgl { width: 20%; }
    .nested-table td.nested-jenis { width: 20%; font-weight: bold; }
    .nested-table td.nested-catatan { width: 60%; }
    .nested-table td.nested-empty { text-align: center; color: #888; }
    
</style></head><body>' . $kop_surat . $html_content . $tanda_tangan . '</body></html>';

// === BLOK MPDF (Tidak Berubah) ===
$mpdf = new \Mpdf\Mpdf([
    'format' => 'A4',
    'orientation' => $page_orientation == 'portrait' ? 'P' : 'L'
]);

$mpdf->WriteHTML($full_html);
$mpdf->Output($fileName, \Mpdf\Output\Destination::DOWNLOAD);
?>
