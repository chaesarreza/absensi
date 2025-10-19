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

// Tambah 1 poin
$stmt_insert = $koneksi->prepare("INSERT INTO poin_keaktifan (siswa_id, jadwal_id, tanggal) VALUES (?, ?, ?)");
$stmt_insert->bind_param("iis", $siswa_id, $jadwal_id, $tanggal);
$stmt_insert->execute();

// Ambil total poin baru
$stmt_total = $koneksi->prepare("SELECT COUNT(id) as total FROM poin_keaktifan WHERE siswa_id = ? AND jadwal_id = ? AND tanggal = ?");
$stmt_total->bind_param("iis", $siswa_id, $jadwal_id, $tanggal);
$stmt_total->execute();
$total_poin = $stmt_total->get_result()->fetch_assoc()['total'];

echo json_encode(['status' => 'sukses', 'total_poin' => $total_poin]);
?>