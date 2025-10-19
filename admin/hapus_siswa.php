<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_siswa = $_GET['id'];
    $stmt = $koneksi->prepare("DELETE FROM siswa WHERE id = ?");
    $stmt->bind_param("i", $id_siswa);
    if ($stmt->execute()) {
        header("Location: siswa.php?status=sukses_hapus");
    } else {
        header("Location: siswa.php?status=gagal_hapus");
    }
    exit();
}
?>