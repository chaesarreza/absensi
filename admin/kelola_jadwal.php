<?php
session_start();
require_once '../config/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: kelas.php");
    exit();
}
$id_kelas = $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_jadwal'])) {
    $guru_id = $_POST['guru_id'];
    $mapel_id = $_POST['mapel_id'];

    $stmt_cek = $koneksi->prepare("SELECT id FROM jadwal_mengajar WHERE guru_id = ? AND kelas_id = ? AND mapel_id = ?");
    $stmt_cek->bind_param("iii", $guru_id, $id_kelas, $mapel_id);
    $stmt_cek->execute();
    if ($stmt_cek->get_result()->num_rows == 0) {
        $stmt_insert = $koneksi->prepare("INSERT INTO jadwal_mengajar (guru_id, kelas_id, mapel_id) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("iii", $guru_id, $id_kelas, $mapel_id);
        $stmt_insert->execute();
    }
    header("Location: kelola_jadwal.php?id=" . $id_kelas . "&status=sukses");
    exit();
}

$stmt_kelas = $koneksi->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
$stmt_kelas->bind_param("i", $id_kelas);
$stmt_kelas->execute();
$kelas = $stmt_kelas->get_result()->fetch_assoc();
if (!$kelas) {
    header("Location: kelas.php");
    exit();
}

$query_jadwal = "SELECT j.id, u.nama_lengkap, u.nip, m.nama_mapel FROM jadwal_mengajar j JOIN users u ON j.guru_id = u.id JOIN mata_pelajaran m ON j.mapel_id = m.id WHERE j.kelas_id = ? ORDER BY m.nama_mapel";
$stmt_jadwal = $koneksi->prepare($query_jadwal);
$stmt_jadwal->bind_param("i", $id_kelas);
$stmt_jadwal->execute();
$result_jadwal = $stmt_jadwal->get_result();

$result_guru = $koneksi->query("SELECT id, nama_lengkap FROM users WHERE role = 'guru' ORDER BY nama_lengkap");
$result_mapel = $koneksi->query("SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel");

$title = "Kelola Jadwal " . $kelas['nama_kelas'];
require_once 'templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Kelola Jadwal Mengajar untuk Kelas: <span class="text-primary"><?= htmlspecialchars($kelas['nama_kelas']); ?></span></h1>

    <div class="row">
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header"><h6 class="m-0">Tambahkan Guru Pengajar</h6></div>
                <div class="card-body">
                    <form action="kelola_jadwal.php?id=<?= $id_kelas; ?>" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Pilih Guru</label>
                            <select name="guru_id" class="form-select" required>
                                <option value="">-- Pilih Guru --</option>
                                <?php while($guru = $result_guru->fetch_assoc()): ?>
                                <option value="<?= $guru['id']; ?>"><?= htmlspecialchars($guru['nama_lengkap']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pilih Mata Pelajaran</label>
                            <select name="mapel_id" class="form-select" required>
                                <option value="">-- Pilih Mata Pelajaran --</option>
                                <?php while($mapel = $result_mapel->fetch_assoc()): ?>
                                <option value="<?= $mapel['id']; ?>"><?= htmlspecialchars($mapel['nama_mapel']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" name="tambah_jadwal" class="btn btn-success">Tambahkan</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header"><h6 class="m-0">Daftar Guru dan Mata Pelajaran</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Mata Pelajaran</th><th>Nama Guru</th><th>NIP</th><th>Aksi</th></tr></thead>
                            <tbody>
                                <?php if ($result_jadwal->num_rows > 0): ?>
                                    <?php while($jadwal = $result_jadwal->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($jadwal['nama_mapel']); ?></td>
                                        <td><?= htmlspecialchars($jadwal['nama_lengkap']); ?></td>
                                        <td><?= htmlspecialchars($jadwal['nip'] ?? '-'); ?></td>
                                        <td>
                                            <a href="hapus_jadwal.php?id=<?= $jadwal['id']; ?>&kelas_id=<?= $id_kelas; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus jadwal ini?');">Hapus</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center">Belum ada jadwal mengajar untuk kelas ini.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>