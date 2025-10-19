<?php
session_start();
require_once '../config/db.php';

$error = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: guru.php");
    exit();
}
$id_guru = $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nip = trim($_POST['nip']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $id_to_update = $_POST['id'];

    if (empty($nama_lengkap) || empty($username)) {
        $error = "Nama Lengkap dan Username wajib diisi!";
    } else {
        $stmt_check = $koneksi->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt_check->bind_param("si", $username, $id_to_update);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error = "Username sudah digunakan oleh pengguna lain.";
        } else {
            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $koneksi->prepare("UPDATE users SET nip = ?, nama_lengkap = ?, username = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $nip, $nama_lengkap, $username, $password_hash, $id_to_update);
            } else {
                $stmt = $koneksi->prepare("UPDATE users SET nip = ?, nama_lengkap = ?, username = ? WHERE id = ?");
                $stmt->bind_param("sssi", $nip, $nama_lengkap, $username, $id_to_update);
            }

            if ($stmt->execute()) {
                header("Location: guru.php?status=sukses_edit");
                exit();
            } else { $error = "Gagal memperbarui data."; }
            $stmt->close();
        }
        $stmt_check->close();
    }
}

$stmt_get = $koneksi->prepare("SELECT id, nip, nama_lengkap, username FROM users WHERE id = ? AND role = 'guru'");
$stmt_get->bind_param("i", $id_guru);
$stmt_get->execute();
$result = $stmt_get->get_result();
if ($result->num_rows === 1) {
    $guru = $result->fetch_assoc();
} else {
    echo "Data guru tidak ditemukan."; exit();
}
$stmt_get->close();

$title = "Edit Guru";
require_once 'templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Edit Data Guru</h1>
    <div class="card shadow mb-4">
        <div class="card-header"><h6 class="m-0">Formulir Edit Data Guru</h6></div>
        <div class="card-body">
            <?php if ($error): ?><div class="alert alert-danger"><?= $error; ?></div><?php endif; ?>
            <form action="edit_guru.php?id=<?= $guru['id']; ?>" method="POST">
                <input type="hidden" name="id" value="<?= $guru['id']; ?>">
                <div class="mb-3">
                    <label for="nip" class="form-label">NIP (Opsional)</label>
                    <input type="text" class="form-control" id="nip" name="nip" value="<?= htmlspecialchars($guru['nip']); ?>">
                </div>
                <div class="mb-3">
                    <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($guru['nama_lengkap']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($guru['username']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password">
                    <small class="form-text text-muted">Kosongkan jika tidak ingin mengubah password.</small>
                </div>
                <button type="submit" class="btn btn-warning">Update</button>
                <a href="guru.php" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>