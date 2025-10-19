<?php
session_start();
$title = "Data Siswa";
require_once '../config/db.php';
require_once 'templates/header.php';

$query = "SELECT siswa.*, kelas.nama_kelas FROM siswa LEFT JOIN kelas ON siswa.kelas_id = kelas.id ORDER BY siswa.nama_siswa ASC";
$result = $koneksi->query($query);
?>

<div class="container-fluid">
    <?php
    if (isset($_GET['status'])) {
        $status = $_GET['status'];
        $message = '';
        $alert_type = 'success';
        if ($status == 'sukses_tambah') $message = 'Data siswa baru telah ditambahkan.';
        elseif ($status == 'sukses_edit') { $message = 'Data siswa telah diperbarui.'; $alert_type = 'warning'; }
        elseif ($status == 'sukses_hapus') { $message = 'Data siswa telah dihapus.'; $alert_type = 'danger'; }
        if ($message) {
            echo "<div class='alert alert-{$alert_type} alert-dismissible fade show'>{$message}<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
    ?>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Data Siswa</h1>
        <div>
            <a href="impor_siswa.php" class="btn btn-primary"><i class="bi bi-file-earmark-excel me-2"></i>Impor dari Excel</a>
            <a href="tambah_siswa.php" class="btn btn-success ms-2"><i class="bi bi-plus me-2"></i>Tambah Siswa</a>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>NISN</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
    <?php if ($result->num_rows > 0) : $no = 1; ?>
        <?php while($row = $result->fetch_assoc()) : ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= htmlspecialchars($row['nisn']); ?></td>
                <td><?= htmlspecialchars($row['nama_siswa']); ?></td>
                <td>
                    <?php if($row['nama_kelas']): ?>
                        <?= htmlspecialchars($row['nama_kelas']); ?>
                    <?php else: ?>
                        <span class="badge bg-secondary">Belum ada kelas</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($row['kelas_id']): // Tampilkan tombol 'Keluarkan' hanya jika siswa punya kelas ?>
                    <a href="keluarkan_siswa.php?id=<?= $row['id']; ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Yakin ingin mengeluarkan siswa ini dari kelasnya?');">Keluarkan</a>
                    <?php endif; ?>
                    <a href="edit_siswa.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                    <a href="hapus_siswa.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('PERINGATAN:\nMenghapus siswa ini akan menghapus SEMUA RIWAYAT ABSENSINYA secara permanen.\n\nAnda yakin ingin melanjutkan?');">Hapus</a>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else : ?>
        <tr><td colspan="5" class="text-center">Tidak ada data siswa.</td></tr>
    <?php endif; ?>
</tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>