<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    echo json_encode(['status' => 'gagal', 'message' => 'Akses ditolak.']);
    exit();
}
// PERUBAHAN: Ambil tanggal dari data POST
if (isset($_POST['siswa_id']) && isset($_POST['status']) && isset($_POST['tanggal'])) {
    $id_siswa = $_POST['siswa_id'];
    $status_baru = $_POST['status'];
    $mapel_id = $_POST['mapel_id'];
    $id_guru = $_SESSION['user_id'];
    $tanggal = $_POST['tanggal']; // Gunakan tanggal yang dikirim
    
    if (!in_array($status_baru, ['Hadir', 'Sakit', 'Izin', 'Alpa'])) {
        echo json_encode(['status' => 'gagal', 'message' => 'Status tidak valid.']);
        exit();
    }

    $stmt = $koneksi->prepare("INSERT INTO absensi (siswa_id, tanggal, mapel_id, status, dicatat_oleh) 
                               VALUES (?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE status = VALUES(status), dicatat_oleh = VALUES(dicatat_oleh)");
    $stmt->bind_param("isisi", $id_siswa, $tanggal, $mapel_id, $status_baru, $id_guru);
    
    if ($stmt->execute()) {
        $badge_class = '';
        if ($status_baru == 'Hadir') { $badge_class = 'bg-success'; }
        elseif ($status_baru == 'Sakit') { $badge_class = 'bg-warning text-dark'; }
        elseif ($status_baru == 'Izin') { $badge_class = 'bg-info text-dark'; }
        elseif ($status_baru == 'Alpa') { $badge_class = 'bg-danger'; }
        echo json_encode(['status' => 'sukses', 'status_baru' => $status_baru, 'badge_class' => $badge_class]);
    } else {
        echo json_encode(['status' => 'gagal', 'message' => 'Gagal menyimpan data.']);
    }
} else {
    echo json_encode(['status' => 'gagal', 'message' => 'Data tidak lengkap.']);
}
?>