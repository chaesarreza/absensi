<?php
session_start();
$title = "Data Kelas";
require_once '../config/db.php';
require_once 'templates/header.php';

$query = "SELECT kelas.*, users.nama_lengkap AS nama_wali_kelas FROM kelas LEFT JOIN users ON kelas.wali_kelas_id = users.id ORDER BY kelas.nama_kelas ASC";
$result = $koneksi->query($query);
?>

<div class="container-fluid">
    <?php
    if (isset($_GET['status'])) {
        $status = $_GET['status'];
        $message = '';
        $alert_type = 'success';
        if ($status == 'sukses_tambah') $message = 'Data kelas baru telah ditambahkan.';
        elseif ($status == 'sukses_edit') { $message = 'Data kelas berhasil diperbarui.'; $alert_type = 'warning'; }
        elseif ($status == 'sukses_hapus') { $message = 'Data kelas berhasil dihapus.'; $alert_type = 'danger'; }
        if ($message) {
            echo "<div class='alert alert-{$alert_type} alert-dismissible fade show'>{$message}<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
    ?>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Data Kelas</h1>
        <a href="tambah_kelas.php" class="btn btn-success"><i class="bi bi-plus me-2"></i>Tambah Kelas</a>
    </div>

    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Kelas</th>
                            <th>Wali Kelas</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0) : $no = 1; ?>
                            <?php while($row = $result->fetch_assoc()) : ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($row['nama_kelas']); ?></td>
                                    <td><?= htmlspecialchars($row['nama_wali_kelas'] ?? 'Belum ditentukan'); ?></td>
                                    <td>
                                        <a href="kelola_jadwal.php?id=<?= $row['id']; ?>" class="btn btn-primary btn-sm">Kelola Jadwal</a>
                                        <a href="edit_kelas.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <a href="hapus_kelas.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('PERINGATAN:\nMenghapus kelas ini TIDAK akan menghapus data siswanya.\nSiswa yang ada di kelas ini akan berstatus \'Belum ada kelas\'.\n\nAnda yakin ingin melanjutkan?');">Hapus</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="4" class="text-center">Tidak ada data kelas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>