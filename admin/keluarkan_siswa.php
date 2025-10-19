<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_siswa = $_GET['id'];
    
    // Set kelas_id menjadi NULL untuk mengeluarkan siswa dari kelas
    $stmt = $koneksi->prepare("UPDATE siswa SET kelas_id = NULL WHERE id = ?");
    $stmt->bind_param("i", $id_siswa);
    
    if ($stmt->execute()) {
        header("Location: siswa.php?status=sukses_keluarkan"); // Anda bisa tambahkan notifikasi jika mau
    } else {
        header("Location: siswa.php?status=gagal_keluarkan");
    }
    exit();
}
?>