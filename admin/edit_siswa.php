<?php
session_start();
require_once '../config/db.php';
$id_siswa = $_GET['id'];

// Ambil data kelas
$kelas_result = $koneksi->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nisn = trim($_POST['nisn']);
    $nama_siswa = trim($_POST['nama_siswa']);
    $kelas_id = $_POST['kelas_id'];
    $id_to_update = $_POST['id'];

    $stmt = $koneksi->prepare("UPDATE siswa SET nisn = ?, nama_siswa = ?, kelas_id = ? WHERE id = ?");
    $stmt->bind_param("ssii", $nisn, $nama_siswa, $kelas_id, $id_to_update);
    if ($stmt->execute()) {
        header("Location: siswa.php?status=sukses_edit");
        exit();
    }
}

$stmt = $koneksi->prepare("SELECT * FROM siswa WHERE id = ?");
$stmt->bind_param("i", $id_siswa);
$stmt->execute();
$result = $stmt->get_result();
$siswa = $result->fetch_assoc();

$title = "Edit Siswa";
require_once 'templates/header.php';

?>
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Edit Data Siswa</h1>
    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="edit_siswa.php?id=<?= $siswa['id']; ?>" method="POST">
                <input type="hidden" name="id" value="<?= $siswa['id']; ?>">
                <div class="mb-3"><label class="form-label">NISN</label><input type="text" class="form-control" name="nisn" value="<?= htmlspecialchars($siswa['nisn']); ?>" required></div>
                <div class="mb-3"><label class="form-label">Nama Siswa</label><input type="text" class="form-control" name="nama_siswa" value="<?= htmlspecialchars($siswa['nama_siswa']); ?>" required></div>
                <div class="mb-3">
                    <label class="form-label">Kelas</label>
                    <select name="kelas_id" class="form-control" required>
                        <option value="">Pilih Kelas</option>
                        <?php while($kelas = $kelas_result->fetch_assoc()): ?>
                        <option value="<?= $kelas['id']; ?>" <?= ($kelas['id'] == $siswa['kelas_id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($kelas['nama_kelas']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-warning">Update</button>
                <a href="siswa.php" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>
<?php require_once 'templates/footer.php'; ?>