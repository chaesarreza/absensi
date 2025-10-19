<?php
session_start();
require_once '../config/db.php';

$error = '';
$sukses = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nip = trim($_POST['nip']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($nama_lengkap) || empty($username) || empty($password)) {
        $error = "Semua kolom (selain NIP) wajib diisi!";
    } else {
        $stmt_check = $koneksi->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error = "Username sudah digunakan. Silakan pilih username lain.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'guru';

            $stmt = $koneksi->prepare("INSERT INTO users (nip, nama_lengkap, username, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nip, $nama_lengkap, $username, $password_hash, $role);

            if ($stmt->execute()) {
                header("Location: guru.php?status=sukses_tambah");
                exit();
            } else {
                $error = "Gagal menambahkan data: " . $stmt->error;
            }
            $stmt->close();
        }
        $stmt_check->close();
    }
}

$title = "Tambah Guru";
require_once 'templates/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Tambah Guru Baru</h1>
    <div class="card shadow mb-4">
        <div class="card-header"><h6 class="m-0">Formulir Data Guru</h6></div>
        <div class="card-body">
            <?php if ($error): ?><div class="alert alert-danger"><?= $error; ?></div><?php endif; ?>
            <form action="tambah_guru.php" method="POST">
                <div class="mb-3">
                    <label for="nip" class="form-label">NIP (Opsional)</label>
                    <input type="text" class="form-control" id="nip" name="nip">
                </div>
                <div class="mb-3">
                    <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                </div>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-success">Simpan</button>
                <a href="guru.php" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>