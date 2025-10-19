<?php
session_start();
require_once '../config/db.php';

// Ambil data guru untuk dropdown wali kelas
$guru_result = $koneksi->query("SELECT id, nama_lengkap FROM users WHERE role = 'guru' ORDER BY nama_lengkap ASC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_kelas = trim($_POST['nama_kelas']);
    $wali_kelas_id = $_POST['wali_kelas_id'];

    $stmt = $koneksi->prepare("INSERT INTO kelas (nama_kelas, wali_kelas_id) VALUES (?, ?)");
    $stmt->bind_param("si", $nama_kelas, $wali_kelas_id);
    if ($stmt->execute()) {
        header("Location: kelas.php?status=sukses_tambah");
        exit();
    }
}

$title = "Tambah Kelas";
require_once 'templates/header.php';

?>
<div class="container-fluid">
    <h1 class="h3 mb-4">Tambah Kelas Baru</h1>
    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="tambah_kelas.php" method="POST">
                <div class="mb-3"><label class="form-label">Nama Kelas</label><input type="text" class="form-control" name="nama_kelas" required></div>
                <div class="mb-3">
                    <label class="form-label">Wali Kelas</label>
                    <select name="wali_kelas_id" class="form-control" required>
                        <option value="">Pilih Wali Kelas</option>
                        <?php while($guru = $guru_result->fetch_assoc()): ?>
                        <option value="<?= $guru['id']; ?>"><?= htmlspecialchars($guru['nama_lengkap']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Simpan</button>
                <a href="kelas.php" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>
<?php require_once 'templates/footer.php'; ?>