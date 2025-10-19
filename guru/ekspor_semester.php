<?php
session_start();
require_once '../config/db.php';
require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') { die("Akses ditolak."); }

// --- FUNGSI-FUNGSI BANTU ---
function get_nama_bulan_id($bulan_angka) {
    $daftar_bulan = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    return $daftar_bulan[$bulan_angka] ?? '';
}
function format_tanggal_indonesia($tanggal_db) {
    if (empty($tanggal_db)) return '';
    $timestamp = strtotime($tanggal_db);
    $bulan_map = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return date('d', $timestamp) . ' ' . $bulan_map[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
}

// --- AMBIL SEMUA PARAMETER & INFO DASAR ---
$id_guru = $_SESSION['user_id'];
$jadwal_id = $_GET['jadwal_id'] ?? 0;
$bulan_mulai = $_GET['bulan_mulai'] ?? date('m');
$bulan_selesai = $_GET['bulan_selesai'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

if (empty($jadwal_id)) { die("Parameter tidak lengkap."); }

$result_settings = $koneksi->query("SELECT * FROM settings");
$settings = [];
while ($row = $result_settings->fetch_assoc()) { $settings[$row['setting_name']] = $row['setting_value']; }

$stmt_jadwal = $koneksi->prepare("SELECT k.id as kelas_id, m.id as mapel_id, k.nama_kelas, m.nama_mapel, u.nama_lengkap as nama_guru, u.nip as nip_guru FROM jadwal_mengajar j JOIN kelas k ON j.kelas_id=k.id JOIN mata_pelajaran m ON j.mapel_id=m.id JOIN users u ON j.guru_id=u.id WHERE j.id = ? AND j.guru_id = ?");
$stmt_jadwal->bind_param("ii", $jadwal_id, $id_guru);
$stmt_jadwal->execute();
$info = $stmt_jadwal->get_result()->fetch_assoc();
if (!$info) { die("Jadwal tidak valid."); }

$periode_laporan = get_nama_bulan_id($bulan_mulai) . ($bulan_mulai != $bulan_selesai ? " - " . get_nama_bulan_id($bulan_selesai) : "") . " " . $tahun;
$tanggal_awal = $tahun . '-' . $bulan_mulai . '-01';
$tanggal_akhir_bulan_selesai = cal_days_in_month(CAL_GREGORIAN, $bulan_selesai, $tahun);
$tanggal_akhir = $tahun . '-' . $bulan_selesai . '-' . $tanggal_akhir_bulan_selesai;
$tanggal_laporan_lengkap = format_tanggal_indonesia(date('Y-m-d'));

// ==== TEMPLATE HTML UTAMA (Kop & Tanda Tangan TFOOT) ====
$logo_path = '../assets/img/logo.png';
$logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
$kop_surat = '
<table style="width: 100%; border-bottom: 3px solid #000; margin-bottom: 20px;">
    <tr>
        <td style="width: 15%; text-align: right; border: none;"><img src="'.$logo_base64.'" style="width: 70px;"></td>
        <td style="width: 70%; text-align: center; border: none;">
            <h1 style="font-size: 16px; margin: 0;">KEMENTERIAN AGAMA REPUBLIK INDONESIA</h1>
            <h2 style="font-size: 18px; margin: 5px 0; font-weight: bold;">'.htmlspecialchars($settings['nama_madrasah']).'</h2>
            <p style="font-size: 11px; margin: 0;">'.htmlspecialchars($settings['alamat_madrasah']).'</p>
        </td>
        <td style="width: 15%; border: none;"></td>
    </tr>
</table>';

// --- PERBAIKAN: Buat Tanda Tangan dalam format TFOOT ---
$tanda_tangan_footer = '
<tfoot>
    <tr class="signature-row">
        <td colspan="100%" style="border: none; padding-top: 40px; text-align: center;">
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

$html_daftar_hadir = ''; $html_daftar_nilai = ''; $html_jurnal = ''; $html_laporan_interaksi = '';

// ==== 1. GENERATE KONTEN LAPORAN DAFTAR HADIR ====
$stmt_siswa = $koneksi->prepare("SELECT id, nama_siswa FROM siswa WHERE kelas_id = ? ORDER BY nama_siswa ASC");
$stmt_siswa->bind_param("i", $info['kelas_id']); $stmt_siswa->execute();
$siswa_list = $stmt_siswa->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_absen = $koneksi->prepare("SELECT siswa_id, tanggal, status FROM absensi WHERE mapel_id = ? AND (tanggal BETWEEN ? AND ?)");
$stmt_absen->bind_param("iss", $info['mapel_id'], $tanggal_awal, $tanggal_akhir); $stmt_absen->execute();
$result_absen = $stmt_absen->get_result();
$kehadiran = []; $tanggal_mengajar = [];
while ($row = $result_absen->fetch_assoc()) {
    $kehadiran[$row['siswa_id']][$row['tanggal']] = substr($row['status'], 0, 1);
    if (!in_array($row['tanggal'], $tanggal_mengajar)) { $tanggal_mengajar[] = $row['tanggal']; }
}
sort($tanggal_mengajar);
$tabel_hadir_judul = '<div style="text-align: center;"><h3 style="font-size: 14px; text-decoration: underline;">REKAPITULASI DAFTAR HADIR SISWA</h3><p style="font-size:12px;">Periode: '.$periode_laporan.' | Kelas: '.htmlspecialchars($info['nama_kelas']).' | Mapel: '.htmlspecialchars($info['nama_mapel']).'</p></div>';
$tabel_hadir_isi = '<table style="font-size:8px; margin-top: 15px;"><thead><tr><th rowspan="2">No</th><th rowspan="2" class="nama">Nama Siswa</th><th colspan="'.count($tanggal_mengajar).'">Tanggal Mengajar</th><th colspan="4">Jumlah</th></tr><tr>';
foreach ($tanggal_mengajar as $tgl) { $tabel_hadir_isi .= "<th>".date('d/m', strtotime($tgl))."</th>"; }
$tabel_hadir_isi .= '<th>H</th><th>S</th><th>I</th><th>A</th></tr></thead><tbody>';
$no = 1;
foreach($siswa_list as $siswa) {
    $count_h=0; $count_s=0; $count_i=0; $count_a=0;
    $tabel_hadir_isi .= '<tr><td>'.$no++.'</td><td class="nama">'.htmlspecialchars($siswa['nama_siswa']).'</td>';
    foreach ($tanggal_mengajar as $tgl) {
        $status = $kehadiran[$siswa['id']][$tgl] ?? '';
        $tabel_hadir_isi .= '<td>'.$status.'</td>';
        if($status == 'H') $count_h++; if($status == 'S') $count_s++; if($status == 'I') $count_i++; if($status == 'A') $count_a++;
    }
    $tabel_hadir_isi .= '<td>'.$count_h.'</td><td>'.$count_s.'</td><td>'.$count_i.'</td><td>'.$count_a.'</td></tr>';
}
$tabel_hadir_isi .= '</tbody>' . $tanda_tangan_footer . '</table>';
$html_daftar_hadir = $kop_surat . $tabel_hadir_judul . $tabel_hadir_isi;

// ==== 2. GENERATE KONTEN LAPORAN DAFTAR NILAI (REKAP RATA-RATA) ====
$stmt_jenis = $koneksi->prepare("SELECT DISTINCT jenis_nilai FROM penilaian WHERE jadwal_id = ? AND (tanggal BETWEEN ? AND ?) ORDER BY FIELD(jenis_nilai, 'Tugas', 'Ulangan', 'UTS', 'UAS'), jenis_nilai ASC");
$stmt_jenis->bind_param("iss", $jadwal_id, $tanggal_awal, $tanggal_akhir);
$stmt_jenis->execute();
$jenis_nilai_list = $stmt_jenis->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_avg = $koneksi->prepare("SELECT siswa_id, jenis_nilai, AVG(nilai) as rata_rata_nilai FROM penilaian WHERE jadwal_id = ? AND (tanggal BETWEEN ? AND ?) GROUP BY siswa_id, jenis_nilai");
$stmt_avg->bind_param("iss", $jadwal_id, $tanggal_awal, $tanggal_akhir);
$stmt_avg->execute();
$result_avg = $stmt_avg->get_result();
$nilai_avg_map = [];
while ($row = $result_avg->fetch_assoc()) { $nilai_avg_map[$row['siswa_id']][$row['jenis_nilai']] = round($row['rata_rata_nilai']); }
$tabel_nilai_judul = '<div style="text-align: center;"><h3 style="font-size: 14px; text-decoration: underline;">REKAPITULASI NILAI SISWA (RATA-RATA)</h3><p style="font-size:12px;">Periode: '.$periode_laporan.' | Kelas: '.htmlspecialchars($info['nama_kelas']).' | Mapel: '.htmlspecialchars($info['nama_mapel']).'</p></div>';
$tabel_nilai_isi = '<table style="font-size:10px; margin-top: 15px;"><thead><tr><th style="width: 5%;">No.</th><th class="nama" style="width: 40%;">Nama Siswa</th>';
foreach($jenis_nilai_list as $jenis) { $tabel_nilai_isi .= '<th>Rata-rata '.htmlspecialchars($jenis['jenis_nilai']).'</th>'; }
$tabel_nilai_isi .= '<th>Rata-rata Total</th></tr></thead><tbody>';
if (count($siswa_list) > 0) {
    $no = 1;
    foreach($siswa_list as $siswa) {
        $total_nilai_siswa = 0; $jumlah_jenis_nilai = 0;
        $tabel_nilai_isi .= '<tr><td>'.$no++.'</td><td class="nama">'.htmlspecialchars($siswa['nama_siswa']).'</td>';
        foreach($jenis_nilai_list as $jenis) {
            $rata_rata = $nilai_avg_map[$siswa['id']][$jenis['jenis_nilai']] ?? '-';
            $tabel_nilai_isi .= '<td>'.$rata_rata.'</td>';
            if (is_numeric($rata_rata)) { $total_nilai_siswa += $rata_rata; $jumlah_jenis_nilai++; }
        }
        $rata_rata_total = ($jumlah_jenis_nilai > 0) ? round($total_nilai_siswa / $jumlah_jenis_nilai) : '-';
        $tabel_nilai_isi .= '<td><strong>'.$rata_rata_total.'</strong></td></tr>';
    }
} else { $tabel_nilai_isi .= '<tr><td colspan="'.(count($jenis_nilai_list) + 3).'">Tidak ada data.</td></tr>'; }
$tabel_nilai_isi .= '</tbody>' . $tanda_tangan_footer . '</table>';
$html_daftar_nilai = $kop_surat . $tabel_nilai_judul . $tabel_nilai_isi;

// ==== 3. GENERATE KONTEN LAPORAN JURNAL MENGAJAR ====
$stmt_jurnal = $koneksi->prepare("SELECT tanggal, materi_diajarkan, catatan FROM jurnal_guru WHERE jadwal_id = ? AND (tanggal BETWEEN ? AND ?) ORDER BY tanggal ASC");
$stmt_jurnal->bind_param("iss", $jadwal_id, $tanggal_awal, $tanggal_akhir); $stmt_jurnal->execute();
$jurnal_list = $stmt_jurnal->get_result()->fetch_all(MYSQLI_ASSOC);
$tabel_jurnal_judul = '<div style="text-align: center;"><h3 style="font-size: 14px; text-decoration: underline;">JURNAL MENGAJAR</h3><p style="font-size:12px;">Periode: '.$periode_laporan.' | Kelas: '.htmlspecialchars($info['nama_kelas']).' | Mapel: '.htmlspecialchars($info['nama_mapel']).'</p></div>';
// --- PERBAIKAN: Atur lebar kolom & vertical-align ---
$tabel_jurnal_isi = '<table style="font-size:11px; margin-top: 15px;"><thead><tr><th style="width:20%;">Tanggal</th><th class="nama" style="width:40%;">Materi yang Diajarkan</th><th class="nama" style="width:40%;">Catatan</th></tr></thead><tbody>';
if(count($jurnal_list) > 0) {
    foreach($jurnal_list as $jurnal) { $tabel_jurnal_isi .= '<tr><td style="vertical-align: top;">'.format_tanggal_indonesia($jurnal['tanggal']).'</td><td class="nama" style="vertical-align: top;">'.nl2br(htmlspecialchars($jurnal['materi_diajarkan'])).'</td><td class="nama" style="vertical-align: top;">'.nl2br(htmlspecialchars($jurnal['catatan'])).'</td></tr>'; }
} else { $tabel_jurnal_isi .= '<tr><td colspan="3">Tidak ada data jurnal untuk periode ini.</td></tr>'; }
$tabel_jurnal_isi .= '</tbody>' . $tanda_tangan_footer . '</table>';
$html_jurnal = $kop_surat . $tabel_jurnal_judul . $tabel_jurnal_isi;

// ==== 4. GENERATE KONTEN LAPORAN INTERAKSI (SEMESTER) - DENGAN PERBAIKAN FINAL ====
$stmt_poin_semester = $koneksi->prepare("SELECT siswa_id, COUNT(id) as total_poin FROM poin_keaktifan WHERE jadwal_id = ? AND (tanggal BETWEEN ? AND ?) GROUP BY siswa_id");
$stmt_poin_semester->bind_param("iss", $jadwal_id, $tanggal_awal, $tanggal_akhir);
$stmt_poin_semester->execute();
$poin_map_semester = [];
$result_poin_semester = $stmt_poin_semester->get_result();
while($row = $result_poin_semester->fetch_assoc()) { $poin_map_semester[$row['siswa_id']] = $row['total_poin']; }
$stmt_catatan_semester = $koneksi->prepare("SELECT siswa_id, tanggal, jenis_catatan, catatan FROM catatan_perilaku WHERE jadwal_id = ? AND (tanggal BETWEEN ? AND ?) ORDER BY tanggal ASC");
$stmt_catatan_semester->bind_param("iss", $jadwal_id, $tanggal_awal, $tanggal_akhir);
$stmt_catatan_semester->execute();
$catatan_map_semester = [];
$result_catatan_semester = $stmt_catatan_semester->get_result();
while($row = $result_catatan_semester->fetch_assoc()) { $catatan_map_semester[$row['siswa_id']][] = $row; }
$tabel_interaksi_judul = '<div style="text-align: center;"><h3 style="font-size: 14px; text-decoration: underline;">REKAPITULASI INTERAKSI SISWA</h3><p style="font-size:12px;">Periode: '.$periode_laporan.' | Kelas: '.htmlspecialchars($info['nama_kelas']).' | Mapel: '.htmlspecialchars($info['nama_mapel']).'</p></div>';
$tabel_interaksi_isi = '<table style="font-size:9px; margin-top: 15px;">
                        <thead>
                            <tr>
                                <th style="width: 4%;">No</th>
                                <th class="nama" style="width: 20%;">Nama Siswa</th>
                                <th style="width: 7%;">Total Poin</th>
                                <th class="nama">Rincian Catatan Perilaku</th>
                            </tr>
                        </thead>
                        <tbody>';
$no = 1;
foreach ($siswa_list as $siswa) {
    $total_poin = $poin_map_semester[$siswa['id']] ?? 0;
    $catatan_siswa = $catatan_map_semester[$siswa['id']] ?? [];
    $tabel_interaksi_isi .= '<tr>';
    $tabel_interaksi_isi .= '<td>' . $no++ . '</td>';
    $tabel_interaksi_isi .= '<td class="nama">' . htmlspecialchars($siswa['nama_siswa']) . '</td>';
    $tabel_interaksi_isi .= '<td>' . $total_poin . '</td>';
    $tabel_interaksi_isi .= '<td class="nama" style="padding: 0;">';
    if (!empty($catatan_siswa)) {
        $tabel_interaksi_isi .= '<table class="nested-table"><tbody>';
        foreach ($catatan_siswa as $catatan) {
            $tabel_interaksi_isi .= '<tr>';
            $tabel_interaksi_isi .= '<td class="nested-td" style="width: 20%;">' . date('d/m/Y', strtotime($catatan['tanggal'])) . '</td>';
            $tabel_interaksi_isi .= '<td class="nested-td" style="width: 15%;">' . htmlspecialchars($catatan['jenis_catatan']) . '</td>';
            $tabel_interaksi_isi .= '<td class="nested-td nama" style="width: 65%;">' . nl2br(htmlspecialchars($catatan['catatan'])) . '</td>';
            $tabel_interaksi_isi .= '</tr>';
        }
        $tabel_interaksi_isi .= '</tbody></table>';
    } else {
        $tabel_interaksi_isi .= '<div style="text-align:center; color:#888; padding: 5px;">- Tidak ada catatan -</div>';
    }
    $tabel_interaksi_isi .= '</td>';
    $tabel_interaksi_isi .= '</tr>';
}
$tabel_interaksi_isi .= '</tbody>' . $tanda_tangan_footer . '</table>';
$html_laporan_interaksi = $kop_surat . $tabel_interaksi_judul . $tabel_interaksi_isi;

// ==== RAKIT SEMUA HTML MENJADI SATU DOKUMEN ====
$full_html = '<!DOCTYPE html><html><head><style>
    @page { margin: 25px; } 
    body { font-family: "Helvetica", sans-serif; }
    h1,h2,h3,h4 { margin: 0; } p { margin: 2px 0; }
    table { width: 100%; border-collapse: collapse; }
    
    /* Perbaikan Bug Tanda Tangan & Tabel */
    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
    tbody { display: table-row-group; }
    tr { page-break-inside: avoid !important; }
    
    th, td { border: 1px solid #333; padding: 5px; text-align: center; vertical-align: top; }
    th { background-color: #f2f2f2; } 
    td.nama { text-align: left; }
    .page-break { page-break-after: always; }
    
    /* Aturan untuk tabel di dalam sel (Laporan Interaksi) */
    .nested-table { border: none !important; }
    .nested-td { border: none !important; border-top: 1px solid #ccc !important; padding: 4px; }
    .nested-table tr:first-child .nested-td { border-top: none !important; }
</style></head><body>' . $html_daftar_hadir . '<div class="page-break"></div>' . $html_daftar_nilai . '<div class="page-break"></div>' . $html_jurnal . '<div class="page-break"></div>' . $html_laporan_interaksi . '</body></html>';

// ==== GENERATE PDF ====
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($full_html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$fileName = 'Bundel_Laporan_Semester_' . str_replace(' ', '_', $info['nama_kelas']) . '.pdf';
$dompdf->stream($fileName, ["Attachment" => 1]);
?>