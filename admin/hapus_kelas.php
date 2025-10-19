<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_kelas = $_GET['id'];
    $stmt = $koneksi->prepare("DELETE FROM kelas WHERE id = ?");
    $stmt->bind_param("i", $id_kelas);
    if ($stmt->execute()) {
        header("Location: kelas.php?status=sukses_hapus");
    } else {
        header("Location: kelas.php?status=gagal_hapus");
    }
    exit();
}
?>