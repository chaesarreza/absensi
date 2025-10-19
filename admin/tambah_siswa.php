<?php
session_start();
require_once '../config/db.php';

// Ambil data kelas untuk dropdown
$kelas_result = $koneksi->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nisn = trim($_POST['nisn']);
    $nama_siswa = trim($_POST['nama_siswa']);
    $kelas_id = $_POST['kelas_id'];

    // PERBAIKAN DI SINI: Menambahkan qr_code_key dengan UUID()
    $stmt = $koneksi->prepare("INSERT INTO siswa (nisn, nama_siswa, kelas_id, qr_code_key) VALUES (?, ?, ?, UUID())");
    $stmt->bind_param("ssi", $nisn, $nama_siswa, $kelas_id);

    if ($stmt->execute()) {
        header("Location: siswa.php?status=sukses_tambah");
        exit();
    }
}
$title = "Tambah Siswa";
require_once 'templates/header.php';

?>
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Tambah Siswa Baru</h1>
    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="tambah_siswa.php" method="POST">
                <div class="mb-3"><label class="form-label">NISN</label><input type="text" class="form-control" name="nisn" required></div>
                <div class="mb-3"><label class="form-label">Nama Siswa</label><input type="text" class="form-control" name="nama_siswa" required></div>
                <div class="mb-3">
                    <label class="form-label">Kelas</label>
                    <select name="kelas_id" class="form-control" required>
                        <option value="">Pilih Kelas</option>
                        <?php while($kelas = $kelas_result->fetch_assoc()): ?>
                        <option value="<?= $kelas['id']; ?>"><?= htmlspecialchars($kelas['nama_kelas']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Simpan</button>
                <a href="siswa.php" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>
<?php require_once 'templates/footer.php'; ?>