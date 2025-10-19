<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') { die("Akses ditolak."); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $jadwal_id = $_POST['jadwal_id'];
    $tanggal = $_POST['tanggal'];
    $jenis_nilai = $_POST['jenis_nilai'];
    $nilai_siswa = $_POST['nilai'];

    $stmt = $koneksi->prepare("INSERT INTO penilaian (jadwal_id, siswa_id, tanggal, jenis_nilai, nilai) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($nilai_siswa as $siswa_id => $nilai) {
        if (!empty($nilai)) { // Hanya simpan jika nilai diisi
            $stmt->bind_param("iissi", $jadwal_id, $siswa_id, $tanggal, $jenis_nilai, $nilai);
            $stmt->execute();
        }
    }
    
// Redirect kembali ke halaman sebelumnya dengan anchor
$redirect_url = strtok($_SERVER['HTTP_REFERER'], '#') . '#penilaian';
header("Location: " . $redirect_url);
    exit();
}
?>