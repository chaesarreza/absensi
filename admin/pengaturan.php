<?php
session_start();
$title = "Pengaturan Aplikasi";
require_once '../config/db.php';
require_once 'templates/header.php';

$message = '';
// Logika untuk update data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil semua data dari form
    $settings_data = [
        'nama_madrasah' => $_POST['nama_madrasah'],
        'alamat_madrasah' => $_POST['alamat_madrasah'],
        'nsm' => $_POST['nsm'],
        'npsn' => $_POST['npsn'],
        'nama_kepala' => $_POST['nama_kepala'],
        'nip_kepala' => $_POST['nip_kepala']
    ];

    $stmt = $koneksi->prepare("UPDATE settings SET setting_value = ? WHERE setting_name = ?");
    
    foreach ($settings_data as $name => $value) {
        $stmt->bind_param("ss", $value, $name);
        $stmt->execute();
    }
    
    $message = '<div class="alert alert-success">Pengaturan berhasil diperbarui.</div>';
}

// Ambil semua data pengaturan saat ini
$result = $koneksi->query("SELECT * FROM settings");
$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_name']] = $row['setting_value'];
}
?>

<div class="page-header">
    <h1 class="h3">Pengaturan Umum</h1>
</div>
<div class="page-body">
    <?= $message ?>
    <div class="card shadow">
        <div class="card-header">
            <h6 class="m-0">Informasi Madrasah & Laporan</h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Madrasah</label>
                        <input type="text" name="nama_madrasah" class="form-control" value="<?= htmlspecialchars($settings['nama_madrasah'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Alamat Madrasah</label>
                        <input type="text" name="alamat_madrasah" class="form-control" value="<?= htmlspecialchars($settings['alamat_madrasah'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">NSM (Nomor Statistik Madrasah)</label>
                        <input type="text" name="nsm" class="form-control" value="<?= htmlspecialchars($settings['nsm'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">NPSN (Nomor Pokok Sekolah Nasional)</label>
                        <input type="text" name="npsn" class="form-control" value="<?= htmlspecialchars($settings['npsn'] ?? ''); ?>" required>
                    </div>
                    <hr class="my-3">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Kepala Madrasah</label>
                        <input type="text" name="nama_kepala" class="form-control" value="<?= htmlspecialchars($settings['nama_kepala'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">NIP Kepala Madrasah</label>
                        <input type="text" name="nip_kepala" class="form-control" value="<?= htmlspecialchars($settings['nip_kepala'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="mt-3 text-end">
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>