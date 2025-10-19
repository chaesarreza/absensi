<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_mapel = trim($_POST['nama_mapel']);
    $stmt = $koneksi->prepare("INSERT INTO mata_pelajaran (nama_mapel) VALUES (?)");
    $stmt->bind_param("s", $nama_mapel);
    if ($stmt->execute()) {
        header("Location: mapel.php?status=sukses_tambah");
        exit();
    }
}
$title = "Tambah Mata Pelajaran";
require_once 'templates/header.php';

?>
<div class="container-fluid">
    <h1 class="h3 mb-4">Tambah Mata Pelajaran Baru</h1>
    <div class="card shadow">
        <div class="card-body">
            <form action="tambah_mapel.php" method="POST">
                <div class="mb-3">
                    <label class="form-label">Nama Mata Pelajaran</label>
                    <input type="text" class="form-control" name="nama_mapel" required>
                </div>
                <button type="submit" class="btn btn-success">Simpan</button>
                <a href="mapel.php" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>
<?php require_once 'templates/footer.php'; ?>