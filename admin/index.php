<?php
session_start();
$title = "Dashboard"; // Menentukan judul halaman

// Panggil koneksi database
require_once '../config/db.php';

// Dengan struktur baru, header sudah mencakup sidebar, jadi sidebar.php tidak dipanggil lagi
require_once 'templates/header.php';

// Query untuk mengambil data statistik
$result_guru = $koneksi->query("SELECT COUNT(id) as jumlah_guru FROM users WHERE role = 'guru'");
$jumlah_guru = $result_guru->fetch_assoc()['jumlah_guru'];

$result_siswa = $koneksi->query("SELECT COUNT(id) as jumlah_siswa FROM siswa");
$jumlah_siswa = $result_siswa->fetch_assoc()['jumlah_siswa'];

$result_kelas = $koneksi->query("SELECT COUNT(id) as jumlah_kelas FROM kelas");
$jumlah_kelas = $result_kelas->fetch_assoc()['jumlah_kelas'];
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Dashboard</h1>
    
    <div class="row">

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card dashboard-card border-start-primary">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col me-2">
                            <div class="text-xs">Jumlah Guru</div>
                            <div class="h5"><?= $jumlah_guru; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-person-video3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card dashboard-card border-start-success">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col me-2">
                            <div class="text-xs">Jumlah Siswa</div>
                            <div class="h5"><?= $jumlah_siswa; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card dashboard-card border-start-info">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col me-2">
                            <div class="text-xs">Jumlah Kelas</div>
                            <div class="h5"><?= $jumlah_kelas; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-building"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="alert alert-info mt-4">
        Selamat Datang, <strong><?= htmlspecialchars($_SESSION['nama_lengkap']); ?></strong>! Anda login sebagai admin.
    </div>
</div>

<?php
// Memanggil footer
require_once 'templates/footer.php';
?>