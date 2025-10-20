<?php
session_start();
require_once '../config/db.php';
require '../vendor/autoload.php';

// Gunakan kelas-kelas mPDF dan FPDI yang relevan
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Mpdf\Exception\MpdfException;
use setasign\Fpdi\Tcpdf\Fpdi; // Pastikan use statement ini benar

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') { die("Akses ditolak."); }

// --- FUNGSI-FUNGSI BANTU ---
// ** DIBUNGKUS DENGAN if (!function_exists(...)) **
if (!function_exists('get_nama_bulan_id')) {
    function get_nama_bulan_id($bulan_angka) {
        $daftar_bulan = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];
        return $daftar_bulan[$bulan_angka] ?? '';
    }
}
if (!function_exists('format_tanggal_indonesia')) {
    function format_tanggal_indonesia($tanggal_db) {
        if (empty($tanggal_db)) return '';
        $timestamp = strtotime($tanggal_db);
        $bulan_map = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        return date('d', $timestamp) . ' ' . $bulan_map[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
    }
}

// --- AMBIL SEMUA PARAMETER & INFO DASAR ---
// (Kode ini sama, tidak perlu disalin ulang)
$id_guru = $_SESSION['user_id'];
$jadwal_id = $_GET['jadwal_id'] ?? 0;
// ... (sisa parameter & query info jadwal/settings) ...
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
if (!$info) { die("Jwal tidak valid."); }
$periode_laporan = get_nama_bulan_id($bulan_mulai) . ($bulan_mulai != $bulan_selesai ? " - " . get_nama_bulan_id($bulan_selesai) : "") . " " . $tahun;
$tanggal_awal = $tahun . '-' . $bulan_mulai . '-01';
$tanggal_akhir_bulan_selesai = cal_days_in_month(CAL_GREGORIAN, $bulan_selesai, $tahun);
$tanggal_akhir = $tahun . '-' . $bulan_selesai . '-' . $tanggal_akhir_bulan_selesai;
$tanggal_laporan_lengkap = format_tanggal_indonesia(date('Y-m-d'));


// ==== TEMPLATE HTML UTAMA (Kop & Tanda Tangan) ====
$logo_path = '../assets/img/logo.png';
$logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
// ** DIBUNGKUS DENGAN if (!function_exists(...)) **
if (!function_exists('get_kop_surat')) {
    function get_kop_surat($logo_base64, $settings) {
        return '
        <table class="kop-surat" style="width: 100%; border-bottom: 3px solid #000; margin-bottom: 20px;">
            <tr>
                <td style="width: 15%; text-align: right;"><img src="'.$logo_base64.'" style="width: 70px;"></td>
                <td style="width: 70%; text-align: center;">
                    <h1 style="font-size: 16px; margin: 0;">KEMENTERIAN AGAMA REPUBLIK INDONESIA</h1>
                    <h2 style="font-size: 18px; margin: 5px 0; font-weight: bold;">'.htmlspecialchars($settings['nama_madrasah']).'</h2>
                    <p style="font-size: 11px; margin: 0;">'.htmlspecialchars($settings['alamat_madrasah']).'</p>
                </td>
                <td style="width: 15%;"></td>
            </tr>
        </table>';
    }
}
// ** DIBUNGKUS DENGAN if (!function_exists(...)) **
if (!function_exists('get_tanda_tangan')) {
    function get_tanda_tangan($settings, $info, $tanggal_laporan_lengkap) {
        return '
        <table class="tanda-tangan-utama" style="width: 100%; font-size: 12px; margin-top: 40px; page-break-inside: avoid;">
            <tr>
                <td style="width: 50%; text-align: center; vertical-align: top;">
                    <p>Mengetahui,<br>Kepala Madrasah</p><br><br><br>
                    <p style="font-weight: bold; text-decoration: underline;">' . htmlspecialchars($settings['nama_kepala']) . '</p>
                    <p>NIP. ' . htmlspecialchars($settings['nip_kepala']) . '</p>
                </td>
                <td style="width: 50%; text-align: center; vertical-align: top;">
                    <p>Aceh Timur, ' . $tanggal_laporan_lengkap . '<br>Guru Mata Pelajaran</p><br><br><br>
                    <p style="font-weight: bold; text-decoration: underline;">' . htmlspecialchars($info['nama_guru']) . '</p>
                    <p>NIP. ' . htmlspecialchars($info['nip_guru']) . '</p>
                </td>
            </tr>
        </table>';
    }
}

// ==== 1. GENERATE KONTEN LAPORAN DAFTAR HADIR ====
// (Kode PHP sama, tidak perlu disalin ulang)
$stmt_siswa = $koneksi->prepare("SELECT id, nama_siswa FROM siswa WHERE kelas_id = ? ORDER BY nama_siswa ASC");
$stmt_siswa->bind_param("i", $info['kelas_id']); $stmt_siswa->execute();
$siswa_list = $stmt_siswa->get_result()->fetch_all(MYSQLI_ASSOC);
// ... (sisa logika hadir) ...
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
$tabel_hadir_isi = '<table class="main-table" style="font-size:8px; margin-top: 15px;"><thead><tr><th rowspan="2" style="width: 5%;">No</th><th rowspan="2" class="nama" style="width: 25%;">Nama Siswa</th><th colspan="'.count($tanggal_mengajar).'">Tanggal Mengajar</th><th colspan="4">Jumlah</th></tr><tr>';
foreach ($tanggal_mengajar as $tgl) {
    $tabel_hadir_isi .= "<th class='th-rotated'><div>".date('d/m', strtotime($tgl))."</div></th>";
}
$tabel_hadir_isi .= '<th class="td-jumlah">H</th><th class="td-jumlah">S</th><th class="td-jumlah">I</th><th class="td-jumlah">A</th></tr></thead><tbody>';
$no = 1;
foreach($siswa_list as $siswa) {
    $count_h=0; $count_s=0; $count_i=0; $count_a=0;
    $tabel_hadir_isi .= '<tr><td>'.$no++.'</td><td class="nama">'.htmlspecialchars($siswa['nama_siswa']).'</td>';
    foreach ($tanggal_mengajar as $tgl) {
        $status = $kehadiran[$siswa['id']][$tgl] ?? '';
        $tabel_hadir_isi .= '<td>'.$status.'</td>';
        if($status == 'H') $count_h++; if($status == 'S') $count_s++; if($status == 'I') $count_i++; if($status == 'A') $count_a++;
    }
    $tabel_hadir_isi .= '<td class="td-jumlah">'.$count_h.'</td><td class="td-jumlah">'.$count_s.'</td><td class="td-jumlah">'.$count_i.'</td><td class="td-jumlah">'.$count_a.'</td></tr>';
}
$tabel_hadir_isi .= '</tbody></table>';
// Gabungkan HTML untuk bagian ini
$html_hadir = get_kop_surat($logo_base64, $settings) . $tabel_hadir_judul . $tabel_hadir_isi . get_tanda_tangan($settings, $info, $tanggal_laporan_lengkap);


// ==== 2. GENERATE KONTEN LAPORAN DAFTAR NILAI ====
// (Kode PHP sama, tidak perlu disalin ulang)
$stmt_jenis = $koneksi->prepare("SELECT DISTINCT jenis_nilai FROM penilaian WHERE jadwal_id = ? AND (tanggal BETWEEN ? AND ?) ORDER BY FIELD(jenis_nilai, 'Tugas', 'Ulangan', 'UTS', 'UAS'), jenis_nilai ASC");
$stmt_jenis->bind_param("iss", $jadwal_id, $tanggal_awal, $tanggal_akhir);
$stmt_jenis->execute();
$jenis_nilai_list = $stmt_jenis->get_result()->fetch_all(MYSQLI_ASSOC);
// ... (sisa logika nilai) ...
$stmt_avg = $koneksi->prepare("SELECT siswa_id, jenis_nilai, AVG(nilai) as rata_rata_nilai FROM penilaian WHERE jadwal_id = ? AND (tanggal BETWEEN ? AND ?) GROUP BY siswa_id, jenis_nilai");
$stmt_avg->bind_param("iss", $jadwal_id, $tanggal_awal, $tanggal_akhir);
$stmt_avg->execute();
$result_avg = $stmt_avg->get_result();
$nilai_avg_map = [];
while ($row = $result_avg->fetch_assoc()) { $nilai_avg_map[$row['siswa_id']][$row['jenis_nilai']] = round($row['rata_rata_nilai']); }
$tabel_nilai_judul = '<div style="text-align: center;"><h3 style="font-size: 14px; text-decoration: underline;">REKAPITULASI NILAI SISWA (RATA-RATA)</h3><p style="font-size:12px;">Periode: '.$periode_laporan.' | Kelas: '.htmlspecialchars($info['nama_kelas']).' | Mapel: '.htmlspecialchars($info['nama_mapel']).'</p></div>';
$tabel_nilai_isi = '<table class="main-table" style="font-size:10px; margin-top: 15px;"><thead><tr><th style="width: 5%;">No.</th><th class="nama" style="width: 40%;">Nama Siswa</th>';
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
$tabel_nilai_isi .= '</tbody></table>';
// Gabungkan HTML untuk bagian ini
$html_nilai = get_kop_surat($logo_base64, $settings) . $tabel_nilai_judul . $tabel_nilai_isi . get_tanda_tangan($settings, $info, $tanggal_laporan_lengkap);

// *** TENTUKAN ORIENTASI NILAI ***
$jumlah_kolom_nilai = count($jenis_nilai_list);
$nilai_orientation_code = 'L';
if ($jumlah_kolom_nilai <= 5) {
    $nilai_orientation_code = 'P';
}

// ==== 3. GENERATE KONTEN LAPORAN JURNAL MENGAJAR ====
// (Kode PHP sama, tidak perlu disalin ulang)
$stmt_jurnal = $koneksi->prepare("SELECT tanggal, materi_diajarkan, catatan FROM jurnal_guru WHERE jadwal_id = ? AND (tanggal BETWEEN ? AND ?) ORDER BY tanggal ASC");
$stmt_jurnal->bind_param("iss", $jadwal_id, $tanggal_awal, $tanggal_akhir); $stmt_jurnal->execute();
$jurnal_list = $stmt_jurnal->get_result()->fetch_all(MYSQLI_ASSOC);
// ... (sisa logika jurnal) ...
$tabel_jurnal_judul = '<div style="text-align: center;"><h3 style="font-size: 14px; text-decoration: underline;">JURNAL MENGAJAR</h3><p style="font-size:12px;">Periode: '.$periode_laporan.' | Kelas: '.htmlspecialchars($info['nama_kelas']).' | Mapel: '.htmlspecialchars($info['nama_mapel']).'</p></div>';
$tabel_jurnal_isi = '<table class="main-table" style="font-size:11px; margin-top: 15px;"><thead><tr><th style="width:20%;">Tanggal</th><th class="nama" style="width:40%;">Materi yang Diajarkan</th><th class="nama" style="width:40%;">Catatan</th></tr></thead><tbody>';
if(count($jurnal_list) > 0) {
    foreach($jurnal_list as $jurnal) { $tabel_jurnal_isi .= '<tr><td style="vertical-align: top;">'.format_tanggal_indonesia($jurnal['tanggal']).'</td><td class="nama" style="vertical-align: top;">'.nl2br(htmlspecialchars($jurnal['materi_diajarkan'])).'</td><td class="nama" style="vertical-align: top;">'.nl2br(htmlspecialchars($jurnal['catatan'])).'</td></tr>'; }
} else { $tabel_jurnal_isi .= '<tr><td colspan="3">Tidak ada data jurnal untuk periode ini.</td></tr>'; }
$tabel_jurnal_isi .= '</tbody></table>';
// Gabungkan HTML untuk bagian ini
$html_jurnal = get_kop_surat($logo_base64, $settings) . $tabel_jurnal_judul . $tabel_jurnal_isi . get_tanda_tangan($settings, $info, $tanggal_laporan_lengkap);

// ==== 4. GENERATE KONTEN LAPORAN INTERAKSI (SEMESTER) ====
// (Kode PHP sama, tidak perlu disalin ulang)
$stmt_poin_semester = $koneksi->prepare("SELECT siswa_id, COUNT(id) as total_poin FROM poin_keaktifan WHERE jadwal_id = ? AND (tanggal BETWEEN ? AND ?) GROUP BY siswa_id");
$stmt_poin_semester->bind_param("iss", $jadwal_id, $tanggal_awal, $tanggal_akhir); $stmt_poin_semester->execute();
$poin_map_semester = [];
// ... (sisa logika interaksi) ...
$result_poin_semester = $stmt_poin_semester->get_result();
while($row = $result_poin_semester->fetch_assoc()) { $poin_map_semester[$row['siswa_id']] = $row['total_poin']; }
$stmt_catatan_semester = $koneksi->prepare("SELECT siswa_id, tanggal, jenis_catatan, catatan FROM catatan_perilaku WHERE jadwal_id = ? AND (tanggal BETWEEN ? AND ?) ORDER BY tanggal ASC");
$stmt_catatan_semester->bind_param("iss", $jadwal_id, $tanggal_awal, $tanggal_akhir); $stmt_catatan_semester->execute();
$catatan_map_semester = [];
$result_catatan_semester = $stmt_catatan_semester->get_result();
while($row = $result_catatan_semester->fetch_assoc()) { $catatan_map_semester[$row['siswa_id']][] = $row; }
$tabel_interaksi_judul = '<div style="text-align: center;"><h3 style="font-size: 14px; text-decoration: underline;">REKAPITULASI INTERAKSI SISWA</h3><p style="font-size:12px;">Periode: '.$periode_laporan.' | Kelas: '.htmlspecialchars($info['nama_kelas']).' | Mapel: '.htmlspecialchars($info['nama_mapel']).'</p></div>';
$tabel_interaksi_isi = '<table class="main-table" style="font-size:9px; margin-top: 15px;">
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
    $tabel_interaksi_isi .= '<td>' . $total_poin . ' Poin</td>';
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
    $tabel_interaksi_isi .= '<td class="nama catatan-wrapper">' . $catatan_html . '</td>';
    $tabel_interaksi_isi .= '</tr>';
}
$tabel_interaksi_isi .= '</tbody></table>';
// Gabungkan HTML untuk bagian ini
$html_interaksi = get_kop_surat($logo_base64, $settings) . $tabel_interaksi_judul . $tabel_interaksi_isi . get_tanda_tangan($settings, $info, $tanggal_laporan_lengkap);


// ==== STYLESHEET ====
$stylesheet = '
    /* CSS tidak berubah */
    @page { margin: 25px; }
    body { font-family: "Helvetica", sans-serif; }
    h1,h2,h3,h4 { margin: 0; } p { margin: 2px 0; }

    table {
        width: 100%;
        border-collapse: collapse;
    }
    table.kop-surat td,
    table.tanda-tangan-utama td {
        border: none !important;
        padding: 2px;
    }

    table.main-table {
        width: 100%;
        border-collapse: collapse !important;
    }

    table.main-table th {
        border: 1px solid #ccc;
        padding: 5px;
        text-align: center;
        vertical-align: middle;
        background-color: #f2f2f2;
    }
    table.main-table td {
        border: 1px solid #ccc;
        padding: 5px;
        text-align: center;
        vertical-align: top;
    }

    table.main-table td.nama { text-align: left; }

    .th-rotated {
        height: 70px;
        line-height: 1.1;
        padding-bottom: 5px !important;
        text-align: center;
    }
    .th-rotated > div {
        transform: rotate(-90deg);
        white-space: nowrap;
        width: 65px;
        display: block;
        margin: 0 auto;
        text-align: center;
    }

    .td-jumlah {
        white-space: nowrap !important;
        width: 3% !important;
    }

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
';

// ==== GENERATE PDF (Metode Gabung PDF) ====
try {
    $tempPdfFiles = [];
    $finalFileName = 'Bundel_Laporan_Semester_' . str_replace(' ', '_', $info['nama_kelas']) . '.pdf';
    $commonConfig = [ // Konfigurasi umum mPDF
        'format' => 'A4',
        'default_font_size' => 9,
        'default_font' => 'Helvetica',
        'margin_left' => 25, // Eksplisit set margin mPDF
        'margin_right' => 25,
        'margin_top' => 25,
        'margin_bottom' => 25,
    ];

    // -- Buat PDF 1: Hadir (Landscape) --
    $mpdf1 = new \Mpdf\Mpdf(array_merge($commonConfig, ['orientation' => 'L']));
    $mpdf1->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf1->WriteHTML('<body>' . $html_hadir . '</body>');
    $pdfContent1 = $mpdf1->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    $tempFile1 = tempnam(sys_get_temp_dir(), 'pdf1_') . '.pdf';
    file_put_contents($tempFile1, $pdfContent1);
    $tempPdfFiles[] = $tempFile1;
    unset($mpdf1, $pdfContent1);

    // -- Buat PDF 2: Nilai (Orientasi Dinamis) --
    $mpdf2 = new \Mpdf\Mpdf(array_merge($commonConfig, ['orientation' => $nilai_orientation_code]));
    $mpdf2->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf2->WriteHTML('<body>' . $html_nilai . '</body>');
    $pdfContent2 = $mpdf2->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    $tempFile2 = tempnam(sys_get_temp_dir(), 'pdf2_') . '.pdf';
    file_put_contents($tempFile2, $pdfContent2);
    $tempPdfFiles[] = $tempFile2;
    unset($mpdf2, $pdfContent2);

    // -- Buat PDF 3: Jurnal (Portrait) --
    $mpdf3 = new \Mpdf\Mpdf(array_merge($commonConfig, ['orientation' => 'P']));
    $mpdf3->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf3->WriteHTML('<body>' . $html_jurnal . '</body>');
    $pdfContent3 = $mpdf3->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    $tempFile3 = tempnam(sys_get_temp_dir(), 'pdf3_') . '.pdf';
    file_put_contents($tempFile3, $pdfContent3);
    $tempPdfFiles[] = $tempFile3;
    unset($mpdf3, $pdfContent3);

    // -- Buat PDF 4: Interaksi (Portrait) --
    $mpdf4 = new \Mpdf\Mpdf(array_merge($commonConfig, ['orientation' => 'P']));
    $mpdf4->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf4->WriteHTML('<body>' . $html_interaksi . '</body>');
    $pdfContent4 = $mpdf4->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    $tempFile4 = tempnam(sys_get_temp_dir(), 'pdf4_') . '.pdf';
    file_put_contents($tempFile4, $pdfContent4);
    $tempPdfFiles[] = $tempFile4;
    unset($mpdf4, $pdfContent4);

    // -- Gabungkan PDF menggunakan FPDI --
    $pdfMerger = new \setasign\Fpdi\Tcpdf\Fpdi();

    // Set Margin FPDI agar sama dengan mPDF
    $pdfMerger->SetMargins(25, 25, 25);
    $pdfMerger->SetAutoPageBreak(true, 25);
    $pdfMerger->setPrintHeader(false);
    $pdfMerger->setPrintFooter(false);

    $isFirstPageOfMergedDoc = true;

    foreach ($tempPdfFiles as $file) {
        if (!file_exists($file) || filesize($file) == 0) {
             error_log("FPDI Error: File sementara tidak valid atau kosong: " . $file);
             continue;
        }
        try {
            $pageCount = $pdfMerger->setSourceFile($file);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdfMerger->importPage($pageNo);
                $size = $pdfMerger->getTemplateSize($templateId);

                if (!$isFirstPageOfMergedDoc) {
                    $pdfMerger->AddPage($size['orientation'], [$size['width'], $size['height']]);
                } else {
                    $pdfMerger->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $isFirstPageOfMergedDoc = false;
                }

                $pdfMerger->useTemplate($templateId, 0, 0);

                // ** PERBAIKAN: Buat Kotak Putih Lebih Tipis **
                $pageWidth = $pdfMerger->GetPageWidth();
                $leftMargin = 25;
                $rightMargin = 25;
                $contentWidth = $pageWidth - $leftMargin - $rightMargin;
                $maskY = 24; // Posisi Y tepat di margin atas
                // *** Ubah nilai ini menjadi lebih kecil ***
                $maskHeight = 0.1; // Coba 0.2mm (sebelumnya 0.5mm)

                $pdfMerger->SetFillColor(255, 255, 255); // Warna putih
                $pdfMerger->Rect($leftMargin, $maskY, $contentWidth, $maskHeight, 'F');
                // ** AKHIR PERBAIKAN **

            }
        } catch (\Exception $e) {
             error_log("FPDI Error processing file " . $file . ": " . $e->getMessage());
             continue;
        }
    } // Akhir loop foreach

    if ($pdfMerger->PageNo() > 0) {
        $pdfMerger->Output($finalFileName, 'D');
    } else {
        die('Error: Tidak ada halaman yang berhasil digabungkan.');
    }

} catch (\Mpdf\MpdfException $e) {
    die('Error saat membuat PDF dengan mPDF: ' . $e->getMessage());
} catch (\setasign\Fpdi\PdfParser\PdfParserException $e) {
    die('Error saat parsing PDF dengan FPDI: ' . $e->getMessage());
} catch (\Exception $e) {
    die('Terjadi error: ' . $e->getMessage());
} finally {
    // Cleanup file sementara
    if (!empty($tempPdfFiles)) {
        foreach ($tempPdfFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
} // Akhir blok try...catch...finally
?> // Akhir tag PHP
