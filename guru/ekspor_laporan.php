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

// --- PENGAMBILAN PARAMETER & DATA AWAL ---
$id_guru = $_SESSION['user_id'];
$jadwal_id = $_GET['jadwal_id'] ?? 0;
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$jenis_laporan = $_GET['jenis'] ?? '';

if (empty($jadwal_id) || empty($jenis_laporan)) { die("Parameter tidak lengkap."); }

$result_settings = $koneksi->query("SELECT * FROM settings");
$settings = [];
while ($row = $result_settings->fetch_assoc()) { $settings[$row['setting_name']] = $row['setting_value']; }

$stmt_jadwal = $koneksi->prepare("SELECT k.id as kelas_id, m.id as mapel_id, k.nama_kelas, m.nama_mapel, u.nama_lengkap as nama_guru, u.nip as nip_guru FROM jadwal_mengajar j JOIN kelas k ON j.kelas_id=k.id JOIN mata_pelajaran m ON j.mapel_id=m.id JOIN users u ON j.guru_id=u.id WHERE j.id = ? AND j.guru_id = ?");
$stmt_jadwal->bind_param("ii", $jadwal_id, $id_guru);
$stmt_jadwal->execute();
$info = $stmt_jadwal->get_result()->fetch_assoc();
if (!$info) { die("Jadwal tidak valid."); }

$nama_bulan = get_nama_bulan_id($bulan);
$tanggal_laporan_lengkap = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun) . ' ' . $nama_bulan . ' ' . $tahun;

// --- KOP SURAT ---
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

// --- TANDA TANGAN (DALAM TFOOT UNTUK MENEMPEL DI BAWAH TABEL) ---
$tanda_tangan_footer = '
<tfoot>
    <tr class="signature-row">
        <td colspan="100%" style="border: none; border-top: 1px solid #333; /* Garis pemisah atas */ padding-top: 30px; text-align: center;">
            <table style="width: 100%; font-size: 12px; border: none;">
                <tr>
                    <td style="width: 50%; border: none; text-align: center; vertical-align: top;">
                        <p>Mengetahui,<br>Kepala Madrasah</p><br><br><br>
                        <p style="font-weight: bold; text-decoration: underline;">' . htmlspecialchars($settings['nama_kepala']) . '</p>
                        <p>NIP. ' . htmlspecialchars($settings['nip_kepala']) . '</p>
                    </td>
                    <td style="width: 50%; text-align: center; border: none; vertical-align: top;">
                        <p>Aceh Timur, ' . $tanggal_laporan_lengkap . '<br>Guru Mata Pelajaran</p><br><br><br>
                        <p style="font-weight: bold; text-decoration: underline;">' . htmlspecialchars($info['nama_guru']) . '</p>
                        <p>NIP. ' . htmlspecialchars($info['nip_guru']) . '</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</tfoot>';

$html_judul = ''; 
$main_table_content = ''; 
$fileName = ''; 
$page_orientation = 'landscape';

// ==================================================================
// --- LOGIKA UTAMA UNTUK MEMBUAT SETIAP JENIS LAPORAN ---
// ==================================================================

if ($jenis_laporan == 'hadir') {
    $fileName = 'Daftar_Hadir_' . $info['nama_kelas'] . '_' . $nama_bulan . '.pdf';
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
    $stmt_siswa = $koneksi->prepare("SELECT id, nama_siswa FROM siswa WHERE kelas_id = ? ORDER BY nama_siswa ASC");
    $stmt_siswa->bind_param("i", $info['kelas_id']); $stmt_siswa->execute();
    $siswa_list = $stmt_siswa->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_absen = $koneksi->prepare("SELECT siswa_id, DAY(tanggal) as tgl, status FROM absensi WHERE mapel_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
    $stmt_absen->bind_param("iss", $info['mapel_id'], $bulan, $tahun); $stmt_absen->execute();
    $result_absen = $stmt_absen->get_result();
    $kehadiran = [];
    while ($row = $result_absen->fetch_assoc()) { $kehadiran[$row['siswa_id']][$row['tgl']] = substr($row['status'], 0, 1); }
    
    $html_judul = '<div style="text-align: center;"><h3 style="font-size: 14px; text-decoration: underline;">DAFTAR HADIR SISWA</h3><p style="font-size:12px;">Bulan: '.$nama_bulan.' '.$tahun.' | Kelas: '.htmlspecialchars($info['nama_kelas']).' | Mapel: '.htmlspecialchars($info['nama_mapel']).'</p></div>';
    $main_table_content = '<table class="main-table" style="font-size:8px; margin-top: 15px;"><thead><tr><th rowspan="2" style="width:25px;">No</th><th rowspan="2">Nama Siswa</th><th colspan="'.$days_in_month.'">Tanggal</th><th colspan="4">Jumlah</th></tr><tr>';
    for ($i = 1; $i <= $days_in_month; $i++) { $main_table_content .= "<th>$i</th>"; }
    $main_table_content .= '<th>H</th><th>S</th><th>I</th><th>A</th></tr></thead><tbody>';
    $no = 1;
    foreach($siswa_list as $siswa) {
        $count_h = 0; $count_s = 0; $count_i = 0; $count_a = 0;
        $main_table_content .= '<tr><td>'.$no++.'</td><td class="nama">'.htmlspecialchars($siswa['nama_siswa']).'</td>';
        for ($i = 1; $i <= $days_in_month; $i++) {
            $status = $kehadiran[$siswa['id']][$i] ?? '';
            $main_table_content .= '<td>'.$status.'</td>';
            if($status == 'H') $count_h++; if($status == 'S') $count_s++; if($status == 'I') $count_i++; if($status == 'A') $count_a++;
        }
        $main_table_content .= '<td>'.$count_h.'</td><td>'.$count_s.'</td><td>'.$count_i.'</td><td>'.$count_a.'</td></tr>';
    }
    $main_table_content .= '</tbody>' . $tanda_tangan_footer . '</table>';

} elseif ($jenis_laporan == 'nilai') {
    $page_orientation = 'portrait';
    $fileName = 'Daftar_Nilai_' . $info['nama_kelas'] . '_' . $nama_bulan . '.pdf';
    $stmt_siswa_nilai = $koneksi->prepare("SELECT id, nama_siswa FROM siswa WHERE kelas_id = ? ORDER BY nama_siswa ASC");
    $stmt_siswa_nilai->bind_param("i", $info['kelas_id']); $stmt_siswa_nilai->execute();
    $siswa_list_nilai = $stmt_siswa_nilai->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_kegiatan = $koneksi->prepare("SELECT DISTINCT jenis_nilai, tanggal FROM penilaian WHERE jadwal_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? ORDER BY tanggal, jenis_nilai ASC");
    $stmt_kegiatan->bind_param("iss", $jadwal_id, $bulan, $tahun); $stmt_kegiatan->execute();
    $kegiatan_list = $stmt_kegiatan->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_nilai = $koneksi->prepare("SELECT siswa_id, jenis_nilai, tanggal, nilai FROM penilaian WHERE jadwal_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
    $stmt_nilai->bind_param("iss", $jadwal_id, $bulan, $tahun); $stmt_nilai->execute();
    $result_nilai = $stmt_nilai->get_result();
    $nilai_map = [];
    while ($row = $result_nilai->fetch_assoc()) { $nilai_map[$row['siswa_id']][$row['jenis_nilai']][$row['tanggal']] = $row['nilai']; }
    
    $html_judul = '<div style="text-align: center;"><h3 style="font-size: 14px; text-decoration: underline;">REKAPITULASI NILAI SISWA</h3><p style="font-size:12px;">Bulan: '.$nama_bulan.' '.$tahun.' | Kelas: '.htmlspecialchars($info['nama_kelas']).' | Mapel: '.htmlspecialchars($info['nama_mapel']).'</p></div>';
    $main_table_content = '<table class="main-table" style="font-size:10px; margin-top: 15px;"><thead><tr><th style="width: 5%;">No.</th><th class="nama" style="width: 30%;">Nama Siswa</th>';
    foreach($kegiatan_list as $kegiatan) { $main_table_content .= '<th>'.htmlspecialchars($kegiatan['jenis_nilai']).'<br><small>('.date('d/m', strtotime($kegiatan['tanggal'])).')</small></th>'; }
    $main_table_content .= '<th>Rata-Rata</th></tr></thead><tbody>';
    if (count($siswa_list_nilai) > 0) {
        $no = 1; foreach($siswa_list_nilai as $siswa) {
            $total_nilai = 0; $jumlah_nilai = 0;
            $main_table_content .= '<tr><td>'.$no++.'</td><td class="nama">'.htmlspecialchars($siswa['nama_siswa']).'</td>';
            foreach($kegiatan_list as $kegiatan) {
                $nilai = $nilai_map[$siswa['id']][$kegiatan['jenis_nilai']][$kegiatan['tanggal']] ?? '-';
                $main_table_content .= '<td>'.$nilai.'</td>';
                if (is_numeric($nilai)) { $total_nilai += $nilai; $jumlah_nilai++; }
            }
            $rata_rata = ($jumlah_nilai > 0) ? round($total_nilai / $jumlah_nilai) : '-';
            $main_table_content .= '<td><strong>'.$rata_rata.'</strong></td></tr>';
        }
    } else { $main_table_content .= '<tr><td colspan="'.(count($kegiatan_list) + 3).'">Tidak ada data.</td></tr>'; }
    $main_table_content .= '</tbody>' . $tanda_tangan_footer . '</table>';

} elseif ($jenis_laporan == 'jurnal') {
    $page_orientation = 'portrait';
    $fileName = 'Jurnal_Mengajar_' . $info['nama_kelas'] . '_' . $nama_bulan . '.pdf';
    $stmt_jurnal = $koneksi->prepare("SELECT tanggal, materi_diajarkan, catatan FROM jurnal_guru WHERE jadwal_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? ORDER BY tanggal ASC");
    $stmt_jurnal->bind_param("iss", $jadwal_id, $bulan, $tahun);
    $stmt_jurnal->execute();
    $jurnal_list = $stmt_jurnal->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $html_judul = '<div style="text-align: center;"><h3 style="font-size: 14px; text-decoration: underline;">JURNAL MENGAJAR</h3><p style="font-size:12px;">Bulan: '.$nama_bulan.' '.$tahun.' | Kelas: '.htmlspecialchars($info['nama_kelas']).' | Mapel: '.htmlspecialchars($info['nama_mapel']).'</p></div>';
    $main_table_content = '<table class="main-table" style="font-size:11px; margin-top: 15px;"><thead><tr><th style="width:20%;">Tanggal</th><th class="nama" style="width:40%;">Materi yang Diajarkan</th><th class="nama" style="width:40%;">Catatan</th></tr></thead><tbody>';
    if(count($jurnal_list) > 0) {
        foreach($jurnal_list as $jurnal) {
            $main_table_content .= '<tr><td style="vertical-align: top;">'.format_tanggal_indonesia($jurnal['tanggal']).'</td><td class="nama" style="vertical-align: top;">'.nl2br(htmlspecialchars($jurnal['materi_diajarkan'])).'</td><td class="nama" style="vertical-align: top;">'.nl2br(htmlspecialchars($jurnal['catatan'])).'</td></tr>';
        }
    } else { $main_table_content .= '<tr><td colspan="3">Tidak ada data jurnal untuk periode ini.</td></tr>'; }
    $main_table_content .= '</tbody>' . $tanda_tangan_footer . '</table>';
    
} elseif ($jenis_laporan == 'interaksi-bulanan') {
    $page_orientation = 'portrait'; // Sesuai permintaan Anda
    $fileName = 'Laporan_Interaksi_Final_' . $info['nama_kelas'] . '_' . $nama_bulan . '.pdf';

    $stmt_siswa = $koneksi->prepare("SELECT id, nama_siswa FROM siswa WHERE kelas_id = ? ORDER BY nama_siswa ASC");
    $stmt_siswa->bind_param("i", $info['kelas_id']);
    $stmt_siswa->execute();
    $daftar_siswa = $stmt_siswa->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_poin = $koneksi->prepare("SELECT siswa_id, COUNT(id) as total_poin FROM poin_keaktifan WHERE jadwal_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? GROUP BY siswa_id");
    $stmt_poin->bind_param("iss", $jadwal_id, $bulan, $tahun);
    $stmt_poin->execute();
    $poin_map = [];
    $result_poin = $stmt_poin->get_result();
    while($row = $result_poin->fetch_assoc()) { $poin_map[$row['siswa_id']] = $row['total_poin']; }
    $stmt_catatan = $koneksi->prepare("SELECT siswa_id, tanggal, jenis_catatan, catatan FROM catatan_perilaku WHERE jadwal_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? ORDER BY tanggal ASC");
    $stmt_catatan->bind_param("iss", $jadwal_id, $bulan, $tahun);
    $stmt_catatan->execute();
    $catatan_map = [];
    $result_catatan = $stmt_catatan->get_result();
    while($row = $result_catatan->fetch_assoc()) { $catatan_map[$row['siswa_id']][] = $row; }

    $html_judul = '<div style="text-align: center;"><h3 style="font-size: 14px; text-decoration: underline;">REKAPITULASI INTERAKSI SISWA</h3><p style="font-size:12px;">Bulan: '.$nama_bulan.' '.$tahun.' | Kelas: '.htmlspecialchars($info['nama_kelas']).' | Mapel: '.htmlspecialchars($info['nama_mapel']).'</p></div>';
    $main_table_content = '<table class="main-table" style="font-size:9px; margin-top: 15px;">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th class="nama" style="width: 25%;">Nama Siswa</th>
                                <th style="width: 10%;">Total Poin</th>
                                <th class="nama">Rincian Catatan Perilaku</th>
                            </tr>
                        </thead>
                        <tbody>'; // Hanya satu tbody
    $no = 1;
    foreach ($daftar_siswa as $siswa) {
        $total_poin = $poin_map[$siswa['id']] ?? 0;
        $catatan_siswa = $catatan_map[$siswa['id']] ?? [];
        $main_table_content .= '<tr>'; // Satu baris per siswa
        $main_table_content .= '<td>' . $no++ . '</td>';
        $main_table_content .= '<td class="nama">' . htmlspecialchars($siswa['nama_siswa']) . '</td>';
        $main_table_content .= '<td>' . $total_poin . '</td>';
        
        $main_table_content .= '<td class="nama" style="padding: 0; vertical-align: top;">';
        if (!empty($catatan_siswa)) {
            $main_table_content .= '<table class="nested-table"><tbody>';
            foreach ($catatan_siswa as $catatan) {
                $main_table_content .= '<tr>';
                $main_table_content .= '<td class="nested-td" style="width: 20%;">' . date('d/m/Y', strtotime($catatan['tanggal'])) . '</td>';
                $main_table_content .= '<td class="nested-td" style="width: 15%;">' . htmlspecialchars($catatan['jenis_catatan']) . '</td>';
                $main_table_content .= '<td class="nested-td nama" style="width: 65%;">' . nl2br(htmlspecialchars($catatan['catatan'])) . '</td>';
                $main_table_content .= '</tr>';
            }
            $main_table_content .= '</tbody></table>';
        } else {
            $main_table_content .= '<div style="text-align:center; color:#888; padding: 5px;">- Tidak ada catatan -</div>';
        }
        $main_table_content .= '</td>';
        $main_table_content .= '</tr>';
    }
    $main_table_content .= '</tbody>' . $tanda_tangan_footer . '</table>';
}

// --- RAKIT PDF & OUTPUT ---
$full_html = '
<!DOCTYPE html><html><head><style>
    @page { margin: 25px; } 
    body { font-family: "Helvetica", sans-serif; }
    h1,h2,h3,h4 { margin: 0; } p { margin: 2px 0; }
    
    table.main-table { width: 100%; border-collapse: collapse; }
    
    /* Perbaikan Bug Tanda Tangan & Tabel */
    .main-table thead { display: table-header-group; } /* Ulangi header */
    .main-table tfoot { display: table-footer-group; } /* Tanda tangan menempel */
    .main-table tbody { display: table-row-group; }
    
    /* Mencegah baris terpotong */
    .main-table > tbody > tr { page-break-inside: avoid !important; } 

    th, td { border: 1px solid #333; padding: 5px; text-align: center; vertical-align: top; }
    th { background-color: #f2f2f2; } 
    td.nama { text-align: left; }
    
    /* Aturan untuk tabel di dalam sel (Laporan Interaksi) */
    .nested-table { border: none !important; margin: 0; padding: 0; width: 100%; border-collapse: collapse; } /* Tambah border-collapse */
    .nested-table tr { page-break-inside: avoid !important; }
    .nested-td { 
        border: none !important; 
        border-top: 1px solid #eee !important; /* Garis pemisah antar catatan */
        padding: 4px; 
        vertical-align: top; 
        text-align: left; 
    } 
    .nested-table tr:first-child .nested-td { border-top: none !important; } /* Hilangkan garis atas di catatan pertama */
</style></head><body>
    ' . $kop_surat . '
    ' . $html_judul . '
    ' . $main_table_content . '
</body></html>';

if (empty($html_judul) && empty($main_table_content)) { die("Jenis laporan tidak valid atau terjadi kesalahan."); }

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($full_html);
$dompdf->setPaper('A4', $page_orientation);
$dompdf->render();
$dompdf->stream($fileName, ["Attachment" => 1]);
?>