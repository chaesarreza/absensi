<?php
session_start();
$title = "Data Mata Pelajaran";
require_once '../config/db.php';
require_once 'templates/header.php';

$query = "SELECT * FROM mata_pelajaran ORDER BY nama_mapel ASC";
$result = $koneksi->query($query);
?>

<div class="container-fluid">
    <?php
    if (isset($_GET['status'])) {
        $status = $_GET['status'];
        $message = '';
        $alert_type = 'success';
        if ($status == 'sukses_tambah') $message = 'Mata pelajaran baru berhasil ditambahkan.';
        elseif ($status == 'sukses_edit') { $message = 'Data mata pelajaran berhasil diperbarui.'; $alert_type = 'warning'; }
        elseif ($status == 'sukses_hapus') { $message = 'Data mata pelajaran berhasil dihapus.'; $alert_type = 'danger'; }
        if ($message) {
            echo "<div class='alert alert-{$alert_type} alert-dismissible fade show'>{$message}<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
    ?>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Data Mata Pelajaran</h1>
    <div>
        <a href="impor_mapel.php" class="btn btn-primary"><i class="bi bi-file-earmark-excel me-2"></i>Impor dari Excel</a>
        <a href="tambah_mapel.php" class="btn btn-success ms-2"><i class="bi bi-plus me-2"></i>Tambah Mata Pelajaran</a>
    </div>
</div>

    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Mata Pelajaran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0) : $no = 1; ?>
                            <?php while($row = $result->fetch_assoc()) : ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($row['nama_mapel']); ?></td>
                                    <td>
                                        <a href="edit_mapel.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <a href="hapus_mapel.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus data ini?');">Hapus</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center">Belum ada data mata pelajaran.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>