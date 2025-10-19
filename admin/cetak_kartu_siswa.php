<?php
session_start();
$title = "Cetak Kartu Absensi Siswa";
require_once '../config/db.php';
require_once 'templates/header.php';

$kelas_result = $koneksi->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
$siswa_list = [];
$selected_kelas_nama = '';

if (isset($_GET['kelas_id']) && is_numeric($_GET['kelas_id'])) {
    $kelas_id = $_GET['kelas_id'];
    $stmt = $koneksi->prepare("SELECT id, nama_siswa, nisn, qr_code_key FROM siswa WHERE kelas_id = ? ORDER BY nama_siswa ASC");
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $siswa_result = $stmt->get_result();
    while ($row = $siswa_result->fetch_assoc()) {
        $siswa_list[] = $row;
    }

    $kelas_info_stmt = $koneksi->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
    $kelas_info_stmt->bind_param("i", $kelas_id);
    $kelas_info_stmt->execute();
    $selected_kelas_nama = $kelas_info_stmt->get_result()->fetch_assoc()['nama_kelas'];
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Cetak Kartu Absensi Siswa</h1>

    <div class="card shadow mb-4 no-print">
        <div class="card-body">
            <form method="GET" action="cetak_kartu_siswa.php">
                <div class="row align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Pilih Kelas</label>
                        <select name="kelas_id" class="form-select" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php while($kelas = $kelas_result->fetch_assoc()): ?>
                                <option value="<?= $kelas['id']; ?>" <?= (isset($_GET['kelas_id']) && $_GET['kelas_id'] == $kelas['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($kelas['nama_kelas']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><button type="submit" class="btn btn-primary">Tampilkan Siswa</button></div>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($siswa_list)): ?>
    <hr>
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h3>Kartu Absensi untuk Kelas: <?= htmlspecialchars($selected_kelas_nama); ?></h3>
    <div>
    <a href="ekspor_kartu_pdf.php?kelas_id=<?= $kelas_id; ?>" class="btn btn-danger">
        <i class="bi bi-file-earmark-pdf me-2"></i>Ekspor Daftar QR ke PDF
    </a>
</div>
</div>

    <div class="row">
        <?php foreach ($siswa_list as $siswa): ?>
            <?php
                $qr_data = $siswa['qr_code_key'];
                $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_data);
            ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($siswa['nama_siswa']); ?></h5>
                        <p class="card-text mb-2">NISN: <?= htmlspecialchars($siswa['nisn']); ?></p>
                        <img src="<?= $qr_code_url; ?>" alt="QR Code" class="img-fluid">
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <style>@media print { .no-print { display: none !important; } .card { page-break-inside: avoid; border: 1px solid #ccc !important; } }</style>
    <?php endif; ?>
</div>

<?php require_once 'templates/footer.php'; ?>