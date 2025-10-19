<?php

require_once '../config/check_license.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';
$result_settings = $koneksi->query("SELECT setting_value FROM settings WHERE setting_name = 'nama_madrasah'");
$nama_madrasah_header = $result_settings->fetch_assoc()['setting_value'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Dashboard Admin' ?> - <?= htmlspecialchars($nama_madrasah_header); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">

    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="../assets/img/logo.png" alt="Logo" class="brand-logo">
                <div class="brand-text">
                    <span class="brand-title"><?= htmlspecialchars($nama_madrasah_header); ?></span>
                    <span class="brand-subtitle">Sistem Absensi Digital</span>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAdmin">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNavAdmin">
                 <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a href="../logout.php" class="btn btn-outline-light">Logout <i class="bi bi-box-arrow-right"></i></a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-content-wrapper">
        <aside class="sidebar">
            
            <ul class="nav nav-pills flex-column">
    <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="index.php">
            <i class="bi bi-house-door-fill"></i> Dashboard
        </a>
    </li>
<li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'analitik.php' ? 'active' : '' ?>" href="analitik.php">
            <i class="bi bi-bar-chart-line-fill"></i> Dasbor Analitik
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'guru.php' ? 'active' : '' ?>" href="guru.php">
            <i class="bi bi-person-video3"></i> Data Guru
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'kelas.php' ? 'active' : '' ?>" href="kelas.php">
            <i class="bi bi-building"></i> Data Kelas
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'siswa.php' ? 'active' : '' ?>" href="siswa.php">
            <i class="bi bi-person-bounding-box"></i> Data Siswa
        </a>
    </li>
    <li class="nav-item">
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'mapel.php' ? 'active' : '' ?>" href="mapel.php">
        <i class="bi bi-journal-text"></i> Data Mata Pelajaran
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'matriks_jadwal.php' ? 'active' : '' ?>" href="matriks_jadwal.php">
        <i class="bi bi-grid-3x3-gap-fill"></i> Matriks Jadwal
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'impor_historis.php' ? 'active' : '' ?>" href="impor_historis.php">
        <i class="bi bi-cloud-upload-fill"></i> Impor Data Historis
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'cetak_kartu_siswa.php' ? 'active' : '' ?>" href="cetak_kartu_siswa.php">
            <i class="bi bi-credit-card"></i> Cetak Kartu Siswa
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'laporan_absen.php' ? 'active' : '' ?>" href="laporan_absen.php">
            <i class="bi bi-file-earmark-bar-graph"></i> Laporan Absensi Siswa
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'pengaturan.php' ? 'active' : '' ?>" href="pengaturan.php">
            <i class="bi bi-gear-fill"></i> Pengaturan
        </a>
    </li>
</ul>
        </aside>

        <main class="content-area">