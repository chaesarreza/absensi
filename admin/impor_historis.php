<?php
session_start();
$title = "Impor Data Historis";
require_once '../config/db.php';
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$message = '';
$error = '';

// "MESIN" BARU UNTUK MEMPROSES FILE UPLOAD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_historis'])) {
    $guru_id_impor = $_POST['guru_id_impor'];
    $kelas_id_impor = $_POST['kelas_id_impor'];
    $mapel_id_impor = $_POST['mapel_id_impor'];

    // Cari jadwal_id yang sesuai
    $stmt_jadwal = $koneksi->prepare("SELECT id FROM jadwal_mengajar WHERE guru_id = ? AND kelas_id = ? AND mapel_id = ?");
    $stmt_jadwal->bind_param("iii", $guru_id_impor, $kelas_id_impor, $mapel_id_impor);
    $stmt_jadwal->execute();
    $result_jadwal = $stmt_jadwal->get_result();
    
    if ($result_jadwal->num_rows === 0) {
        $error = "Gagal! Tidak ditemukan jadwal mengajar yang cocok untuk kombinasi Guru, Kelas, dan Mata Pelajaran yang dipilih.";
    } else {
        $jadwal_id = $result_jadwal->fetch_assoc()['id'];
        $file_mimes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

        if (isset($_FILES['file_historis']['name']) && in_array($_FILES['file_historis']['type'], $file_mimes)) {
            $spreadsheet = IOFactory::load($_FILES['file_historis']['tmp_name']);
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            
            $sukses_count = 0; $gagal_count = 0;
            $jurnal_map = [];
            
            // --- KAMUS PENERJEMAH STATUS ABSEN ---
            $status_map = ['H' => 'Hadir', 'S' => 'Sakit', 'I' => 'Izin', 'A' => 'Alpa'];

            for ($i = 2; $i <= count($sheetData); $i++) {
                try {
                    $tanggal = $sheetData[$i]['A'];
                    $nisn = $sheetData[$i]['B'];
                    $status_absen_singkat = strtoupper(trim($sheetData[$i]['D'])); // Ambil H/S/I/A
                    $jenis_nilai = $sheetData[$i]['E'];
                    $nilai = $sheetData[$i]['F'];
                    $poin = (int)$sheetData[$i]['G'];
                    $jenis_catatan = $sheetData[$i]['H'];
                    $catatan = $sheetData[$i]['I'];
                    $jurnal = $sheetData[$i]['J'];

                    if (empty($tanggal) || empty($nisn)) continue;

                    $stmt_siswa = $koneksi->prepare("SELECT id FROM siswa WHERE nisn = ?");
                    $stmt_siswa->bind_param("s", $nisn);
                    $stmt_siswa->execute();
                    $siswa = $stmt_siswa->get_result()->fetch_assoc();
                    if (!$siswa) { $gagal_count++; continue; }
                    $siswa_id = $siswa['id'];

                    // 1. Proses Absensi (dengan penerjemah)
                    if (!empty($status_absen_singkat) && isset($status_map[$status_absen_singkat])) {
                        $status_absen_lengkap = $status_map[$status_absen_singkat];
                        $stmt = $koneksi->prepare("INSERT INTO absensi (siswa_id, tanggal, mapel_id, status, dicatat_oleh) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status), dicatat_oleh=VALUES(dicatat_oleh)");
                        $stmt->bind_param("isisi", $siswa_id, $tanggal, $mapel_id_impor, $status_absen_lengkap, $guru_id_impor);
                        $stmt->execute();
                    }
                    
                    // 2. Proses Nilai
                    if (!empty($jenis_nilai) && is_numeric($nilai)) {
                        $stmt = $koneksi->prepare("INSERT INTO penilaian (jadwal_id, siswa_id, tanggal, jenis_nilai, nilai) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("iissi", $jadwal_id, $siswa_id, $tanggal, $jenis_nilai, $nilai);
                        $stmt->execute();
                    }
                    
                    // 3. Proses Poin Keaktifan
                    if ($poin > 0) {
                        $stmt = $koneksi->prepare("INSERT INTO poin_keaktifan (siswa_id, jadwal_id, tanggal, poin) VALUES (?, ?, ?, 1)");
                        for ($p = 0; $p < $poin; $p++) {
                            $stmt->bind_param("iis", $siswa_id, $jadwal_id, $tanggal);
                            $stmt->execute();
                        }
                    }

                    // 4. Proses Catatan Perilaku
                    if (!empty($catatan) && !empty($jenis_catatan)) {
                        $stmt = $koneksi->prepare("INSERT INTO catatan_perilaku (siswa_id, jadwal_id, tanggal, jenis_catatan, catatan) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("iisss", $siswa_id, $jadwal_id, $tanggal, $jenis_catatan, $catatan);
                        $stmt->execute();
                    }

                    // 5. Kumpulkan Jurnal
                    if (!empty($jurnal)) {
                        $jurnal_map[$tanggal] = $jurnal;
                    }

                    $sukses_count++;
                } catch (Exception $e) { $gagal_count++; }
            }

            // Proses Jurnal setelah loop selesai
            if (!empty($jurnal_map)) {
                $stmt = $koneksi->prepare("INSERT INTO jurnal_guru (jadwal_id, tanggal, materi_diajarkan) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE materi_diajarkan=VALUES(materi_diajarkan)");
                foreach ($jurnal_map as $tgl => $materi) {
                    $stmt->bind_param("iss", $jadwal_id, $tgl, $materi);
                    $stmt->execute();
                }
            }
            $message = "Proses impor selesai. Berhasil: $sukses_count baris, Gagal: $gagal_count baris.";
        } else { $error = "File tidak valid. Harap unggah file Excel (.xlsx)."; }
    }
}

require_once 'templates/header.php';
?>

<div class="page-header">
    <h1 class="h3">Impor Data Historis</h1>
    <p class="text-muted">Fitur khusus untuk mengisi data absensi, nilai, jurnal, dll. secara massal.</p>
</div>
<div class="page-body">
    <?php if($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="m-0">Langkah 1: Unduh Template</h6></div>
                <div class="card-body">
                    <p>Pilih kelas dan masukkan tanggal mengajar untuk membuat file Excel.</p>
                    <form action="generate_template.php" method="POST" target="_blank">
                        <div class="mb-3">
                            <label class="form-label">Kelas</label>
                            <select name="kelas_id" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <?php $result = $koneksi->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC"); while($row = $result->fetch_assoc()) echo "<option value='{$row['id']}'>".htmlspecialchars($row['nama_kelas'])."</option>"; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal Mengajar (YYYY-MM-DD, pisahkan koma)</label>
                            <textarea name="tanggal_mengajar" class="form-control" rows="4" placeholder="Contoh: 2025-07-21, 2025-07-28, 2025-08-04"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-file-earmark-arrow-down-fill me-2"></i>Generate & Unduh</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="m-0">Langkah 2: Unggah File</h6></div>
                <div class="card-body">
                    <p>Setelah mengisi data di Excel, unggah kembali di sini. Pastikan Anda memilih Guru, Kelas, dan Mapel yang sesuai.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Guru Pengajar</label>
                            <select name="guru_id_impor" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <?php $result = $koneksi->query("SELECT id, nama_lengkap FROM users WHERE role='guru' ORDER BY nama_lengkap ASC"); while($row = $result->fetch_assoc()) echo "<option value='{$row['id']}'>".htmlspecialchars($row['nama_lengkap'])."</option>"; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kelas</label>
                            <select name="kelas_id_impor" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <?php $result = $koneksi->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC"); mysqli_data_seek($result, 0); while($row = $result->fetch_assoc()) echo "<option value='{$row['id']}'>".htmlspecialchars($row['nama_kelas'])."</option>"; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mata Pelajaran</label>
                            <select name="mapel_id_impor" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <?php $result = $koneksi->query("SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel ASC"); while($row = $result->fetch_assoc()) echo "<option value='{$row['id']}'>".htmlspecialchars($row['nama_mapel'])."</option>"; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pilih File Excel (.xlsx)</label>
                            <input type="file" name="file_historis" class="form-control" accept=".xlsx" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-cloud-upload-fill me-2"></i>Mulai Impor</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>