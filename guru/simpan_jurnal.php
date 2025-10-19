<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') { die("Akses ditolak."); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $jadwal_id = $_POST['jadwal_id'];
    $tanggal = $_POST['tanggal'];
    $materi = $_POST['materi_diajarkan'];
    $catatan = $_POST['catatan'];

    // Gunakan INSERT ... ON DUPLICATE KEY UPDATE untuk handle jurnal yg sudah ada
    $stmt = $koneksi->prepare("INSERT INTO jurnal_guru (jadwal_id, tanggal, materi_diajarkan, catatan) VALUES (?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE materi_diajarkan = VALUES(materi_diajarkan), catatan = VALUES(catatan)");
    $stmt->bind_param("isss", $jadwal_id, $tanggal, $materi, $catatan);
    $stmt->execute();

    // Redirect kembali ke halaman sebelumnya dengan anchor
$redirect_url = strtok($_SERVER['HTTP_REFERER'], '#') . '#jurnal';
header("Location: " . $redirect_url);
    exit();
}
?>