<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'gagal', 'message' => 'Akses ditolak']);
    exit();
}

$guru_id = $_GET['guru_id'] ?? 0;
$kelas_id = $_GET['kelas_id'] ?? 0;

// Ambil semua mata pelajaran
$result_mapel = $koneksi->query("SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel ASC");
$semua_mapel = $result_mapel->fetch_all(MYSQLI_ASSOC);

// Ambil mapel yang sudah ditugaskan ke guru & kelas ini
$stmt = $koneksi->prepare("SELECT mapel_id FROM jadwal_mengajar WHERE guru_id = ? AND kelas_id = ?");
$stmt->bind_param("ii", $guru_id, $kelas_id);
$stmt->execute();
$result_jadwal = $stmt->get_result();
$mapel_terpilih = [];
while ($row = $result_jadwal->fetch_assoc()) {
    $mapel_terpilih[] = $row['mapel_id'];
}

echo json_encode([
    'semua_mapel' => $semua_mapel,
    'mapel_terpilih' => $mapel_terpilih
]);
?>