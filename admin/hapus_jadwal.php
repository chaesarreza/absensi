<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Validasi ID jadwal dan ID kelas
if (isset($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['kelas_id']) && is_numeric($_GET['kelas_id'])) {
    $id_jadwal = $_GET['id'];
    $id_kelas = $_GET['kelas_id'];

    $stmt = $koneksi->prepare("DELETE FROM jadwal_mengajar WHERE id = ?");
    $stmt->bind_param("i", $id_jadwal);
    if ($stmt->execute()) {
        header("Location: kelola_jadwal.php?id=" . $id_kelas . "&status=sukses_hapus");
    } else {
        header("Location: kelola_jadwal.php?id=" . $id_kelas . "&status=gagal_hapus");
    }
    exit();
} else {
    header("Location: kelas.php");
    exit();
}
?>