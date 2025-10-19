<?php
session_start();
require 'config/db.php';
// AMBIL NAMA MADRASAH DARI DATABASE
$result_settings = $koneksi->query("SELECT setting_value FROM settings WHERE setting_name = 'nama_madrasah'");
$nama_madrasah = $result_settings->fetch_assoc()['setting_value'] ?? 'Nama Madrasah';

// Cek apakah ada data yang dikirim melalui metode POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Menyiapkan query SQL untuk mencari user berdasarkan username
    // Menggunakan prepared statement untuk keamanan dari SQL Injection
    $stmt = $koneksi->prepare("SELECT id, nama_lengkap, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    // Cek apakah user ditemukan
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verifikasi password yang diinput dengan hash di database
        if (password_verify($password, $user['password'])) {
            // Jika password cocok, simpan data user ke dalam session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];

            // Arahkan berdasarkan role pengguna
if ($user['role'] === 'admin') {
    header("Location: admin/index.php");
} else if ($user['role'] === 'guru') {
    header("Location: guru/index.php");
}
exit();
        } else {
            // Jika password salah
            $error = "Username atau password salah!";
        }
    } else {
        // Jika username tidak ditemukan
        $error = "Username atau password salah!";
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Aplikasi Absensi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 0.5rem;
            background-color: white;
        }
        .logo-kemenag {
            display: block;
            margin: 0 auto 1.5rem auto;
            width: 80px; /* Atur ukuran logo */
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <img src="assets/img/logo.png" alt="Logo Kemenag" class="logo-kemenag">
            <h3 class="text-center">Login Aplikasi Absensi</h3>
<p class="text-center text-muted mb-4"><?= htmlspecialchars($nama_madrasah); ?></p>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-success">Login</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>