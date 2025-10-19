<?php
session_start();
require_once '../config/db.php';
$id_mapel = $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_mapel = trim($_POST['nama_mapel']);
    $id_to_update = $_POST['id'];
    $stmt = $koneksi->prepare("UPDATE mata_pelajaran SET nama_mapel = ? WHERE id = ?");
    $stmt->bind_param("si", $nama_mapel, $id_to_update);
    if ($stmt->execute()) {
        header("Location: mapel.php?status=sukses_edit");
        exit();
    }
}
$stmt = $koneksi->prepare("SELECT * FROM mata_pelajaran WHERE id = ?");
$stmt->bind_param("i", $id_mapel);
$stmt->execute();
$mapel = $stmt->get_result()->fetch_assoc();
$title = "Edit Mata Pelajaran";
require_once 'templates/header.php';

?>
<div class="container-fluid">
    <h1 class="h3 mb-4">Edit Mata Pelajaran</h1>
    <div class="card shadow">
        <div class="card-body">
            <form action="edit_mapel.php?id=<?= $mapel['id']; ?>" method="POST">
                <input type="hidden" name="id" value="<?= $mapel['id']; ?>">
                <div class="mb-3">
                    <label class="form-label">Nama Mata Pelajaran</label>
                    <input type="text" class="form-control" name="nama_mapel" value="<?= htmlspecialchars($mapel['nama_mapel']); ?>" required>
                </div>
                <button type="submit" class="btn btn-warning">Update</button>
                <a href="mapel.php" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>
<?php require_once 'templates/footer.php'; ?>