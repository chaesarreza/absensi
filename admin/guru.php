<?php
session_start();
$title = "Data Guru";
require_once '../config/db.php';
require_once 'templates/header.php';

$query = "SELECT id, nip, nama_lengkap, username FROM users WHERE role = 'guru' ORDER BY nama_lengkap ASC";
$result = $koneksi->query($query);
?>

<div class="container-fluid">
    <?php
    if (isset($_GET['status'])) {
        $status = $_GET['status'];
        $message = '';
        $alert_type = 'success';

        if ($status == 'sukses_tambah') {
            $message = 'Data guru baru berhasil ditambahkan.';
        } elseif ($status == 'sukses_edit') {
            $message = 'Data guru berhasil diperbarui.';
            $alert_type = 'warning';
        } elseif ($status == 'sukses_hapus') {
            $message = 'Data guru berhasil dihapus.';
            $alert_type = 'danger';
        } elseif ($status == 'gagal_hapus') {
            $alert_type = 'danger';
            $reason = $_GET['reason'] ?? 'unknown';
            switch ($reason) {
                case 'wali_kelas':
                    $message = '<strong>Gagal Menghapus!</strong> Guru ini masih terdaftar sebagai Wali Kelas di satu atau beberapa kelas.';
                    break;
                case 'mengajar':
                    $message = '<strong>Gagal Menghapus!</strong> Guru ini masih memiliki Jadwal Mengajar. Hapus jadwalnya terlebih dahulu.';
                    break;
                case 'absensi':
                    $message = '<strong>Gagal Menghapus!</strong> Guru ini memiliki riwayat pencatatan absensi dan tidak bisa dihapus untuk menjaga keutuhan data.';
                    break;
                default:
                    $message = '<strong>Gagal Menghapus!</strong> Terjadi kesalahan yang tidak diketahui.';
                    break;
            }
        }
        
        if ($message) {
            echo "<div class='alert alert-{$alert_type} alert-dismissible fade show'>{$message}<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
    ?>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0">Data Guru</h1>
        <div>
            <a href="impor_guru.php" class="btn btn-primary"><i class="bi bi-file-earmark-excel me-2"></i>Impor dari Excel</a>
            <a href="tambah_guru.php" class="btn btn-success ms-2"><i class="bi bi-plus me-2"></i>Tambah Guru</a>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>NIP</th>
                            <th>Nama Lengkap</th>
                            <th>Username</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0) : $no = 1; ?>
                            <?php while($row = $result->fetch_assoc()) : ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($row['nip'] ?? '-'); ?></td>
                                    <td><?= htmlspecialchars($row['nama_lengkap']); ?></td>
                                    <td><?= htmlspecialchars($row['username']); ?></td>
                                    <td>
                                        <a href="edit_guru.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <a href="hapus_guru.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('PERINGATAN!\n\nAnda akan menghapus guru ini secara permanen.\n\nKonsekuensi:\n- Jabatan Wali Kelas akan dikosongkan.\n- Semua Jadwal Mengajar guru ini akan dihapus.\n\nData Siswa, Kelas, dan Riwayat Absensi TIDAK akan terhapus.\n\nLanjutkan?');">Hapus</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr><td colspan="5" class="text-center">Tidak ada data guru.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>