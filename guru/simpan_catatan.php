<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    echo json_encode(['status' => 'gagal']); exit();
}

$siswa_id = $_POST['siswa_id'];
$jadwal_id = $_POST['jadwal_id'];
$tanggal = $_POST['tanggal'];
$jenis_catatan = $_POST['jenis_catatan'];
$catatan = $_POST['catatan'];

$stmt = $koneksi->prepare("INSERT INTO catatan_perilaku (siswa_id, jadwal_id, tanggal, jenis_catatan, catatan) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iisss", $siswa_id, $jadwal_id, $tanggal, $jenis_catatan, $catatan);
if($stmt->execute()) {
    echo json_encode(['status' => 'sukses']);
} else {
    echo json_encode(['status' => 'gagal']);
}
?>