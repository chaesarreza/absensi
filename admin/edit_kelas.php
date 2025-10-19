<?php
session_start();
require_once '../config/db.php';
$id_kelas = $_GET['id'];

// Ambil data guru
$guru_result = $koneksi->query("SELECT id, nama_lengkap FROM users WHERE role = 'guru' ORDER BY nama_lengkap ASC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_kelas = trim($_POST['nama_kelas']);
    $wali_kelas_id = !empty($_POST['wali_kelas_id']) ? (int)$_POST['wali_kelas_id'] : null;
    $id_to_update = $_POST['id'];

    $stmt = $koneksi->prepare("UPDATE kelas SET nama_kelas = ?, wali_kelas_id = ? WHERE id = ?");
    $stmt->bind_param("sii", $nama_kelas, $wali_kelas_id, $id_to_update);
    if ($stmt->execute()) {
        header("Location: kelas.php?status=sukses_edit");
        exit();
    }
}

$stmt = $koneksi->prepare("SELECT * FROM kelas WHERE id = ?");
$stmt->bind_param("i", $id_kelas);
$stmt->execute();
$kelas = $stmt->get_result()->fetch_assoc();

$title = "Edit Kelas";
require_once 'templates/header.php';

?>
<div class="container-fluid">
    <h1 class="h3 mb-4">Edit Data Kelas</h1>
    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="edit_kelas.php?id=<?= $kelas['id']; ?>" method="POST">
                <input type="hidden" name="id" value="<?= $kelas['id']; ?>">
                <div class="mb-3"><label class="form-label">Nama Kelas</label><input type="text" class="form-control" name="nama_kelas" value="<?= htmlspecialchars($kelas['nama_kelas']); ?>" required></div>
                <div class="mb-3">
                    <label class="form-label">Wali Kelas</label>
                    <select name="wali_kelas_id" class="form-select">
    <option value="">-- Kosongkan Jabatan --</option>
    <?php while($guru = $guru_result->fetch_assoc()): ?>
    <option value="<?= $guru['id']; ?>" <?= ($guru['id'] == $kelas['wali_kelas_id']) ? 'selected' : ''; ?>>
        <?= htmlspecialchars($guru['nama_lengkap']); ?>
    </option>
    <?php endwhile; ?>
</select>
                </div>
                <button type="submit" class="btn btn-warning">Update</button>
                <a href="kelas.php" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>
<?php require_once 'templates/footer.php'; ?>