<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_mapel = $_GET['id'];
    $stmt = $koneksi->prepare("DELETE FROM mata_pelajaran WHERE id = ?");
    $stmt->bind_param("i", $id_mapel);
    if ($stmt->execute()) {
        header("Location: mapel.php?status=sukses_hapus");
    } else {
        header("Location: mapel.php?status=gagal_hapus");
    }
    exit();
}
?>