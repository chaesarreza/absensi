<?php
session_start();
$title = "Laporan Absensi";
require_once '../config/db.php';
require_once 'templates/header.php';

// Ambil daftar kelas untuk filter
$kelas_result = $koneksi->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");

// Inisialisasi filter
$filter_kelas = $_GET['kelas_id'] ?? '';
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');

// Bangun query dasar
$query_str = "SELECT absensi.tanggal, absensi.status, siswa.nama_siswa, siswa.nisn, kelas.nama_kelas, mapel.nama_mapel 
              FROM absensi
              JOIN siswa ON absensi.siswa_id = siswa.id
              JOIN kelas ON siswa.kelas_id = kelas.id
              LEFT JOIN mata_pelajaran mapel ON absensi.mapel_id = mapel.id
              WHERE MONTH(absensi.tanggal) = ? AND YEAR(absensi.tanggal) = ?";

$params = [$filter_bulan, $filter_tahun];
$types = "ss";

// Tambahkan filter kelas jika dipilih
if (!empty($filter_kelas)) {
    $query_str .= " AND kelas.id = ?";
    $params[] = $filter_kelas;
    $types .= "i";
}
$query_str .= " ORDER BY absensi.tanggal, kelas.nama_kelas, siswa.nama_siswa ASC";

$stmt = $koneksi->prepare($query_str);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Laporan Absensi Siswa</h1>

    <div class="card shadow mb-4">
        <div class="card-header"><h6 class="m-0">Filter Laporan</h6></div>
        <div class="card-body">
            <form method="GET" action="laporan_absen.php">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Bulan</label>
                        <select name="bulan" class="form-select">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT); ?>" <?= ($filter_bulan == $i) ? 'selected' : ''; ?>>
                                    <?= date('F', mktime(0, 0, 0, $i, 10)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tahun</label>
                        <select name="tahun" class="form-select">
                            <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                                <option value="<?= $i; ?>" <?= ($filter_tahun == $i) ? 'selected' : ''; ?>><?= $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Kelas</label>
                        <select name="kelas_id" class="form-select">
                            <option value="">Semua Kelas</option>
                            <?php mysqli_data_seek($kelas_result, 0); ?>
                            <?php while($kelas = $kelas_result->fetch_assoc()): ?>
                                <option value="<?= $kelas['id']; ?>" <?= ($filter_kelas == $kelas['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($kelas['nama_kelas']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><button type="submit" class="btn btn-primary">Tampilkan</button></div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="m-0">Hasil Laporan untuk Bulan: <?= htmlspecialchars(date('F Y', mktime(0,0,0,$filter_bulan,1,$filter_tahun))); ?></h6>
            <div>
                <a href="ekspor_pdf.php?bulan=<?= $filter_bulan; ?>&tahun=<?= $filter_tahun; ?>&kelas_id=<?= $filter_kelas; ?>" class="btn btn-danger btn-sm">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Ekspor ke PDF
                </a>
                <a href="ekspor_laporan.php?bulan=<?= $filter_bulan; ?>&tahun=<?= $filter_tahun; ?>&kelas_id=<?= $filter_kelas; ?>" class="btn btn-success btn-sm ms-2">
                    <i class="bi bi-file-earmark-excel me-2"></i>Ekspor ke Excel
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No.</th><th>NISN</th><th>Nama Siswa</th><th>Kelas</th><th>Mata Pelajaran</th><th>Status</th><th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0) : $no = 1; ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($row['nisn']); ?></td>
                                    <td><?= htmlspecialchars($row['nama_siswa']); ?></td>
                                    <td><?= htmlspecialchars($row['nama_kelas']); ?></td>
                                    <td><?= htmlspecialchars($row['nama_mapel'] ?? '-'); ?></td>
                                    <td><span class="badge bg-success"><?= htmlspecialchars($row['status']); ?></span></td>
                                    <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['tanggal']))); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center">Tidak ada data absensi untuk filter yang dipilih.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>