<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'gagal', 'message' => 'Akses ditolak']);
    exit();
}

if (isset($_POST['guru_id']) && isset($_POST['kelas_id'])) {
    $guru_id = $_POST['guru_id'];
    $kelas_id = $_POST['kelas_id'];
    // mapel_ids sekarang adalah array
    $mapel_ids = $_POST['mapel_ids'] ?? [];

    $koneksi->begin_transaction();
    
    try {
        // 1. Hapus dulu semua jadwal lama untuk kombinasi guru & kelas ini
        $stmt_delete = $koneksi->prepare("DELETE FROM jadwal_mengajar WHERE guru_id = ? AND kelas_id = ?");
        $stmt_delete->bind_param("ii", $guru_id, $kelas_id);
        $stmt_delete->execute();
        $stmt_delete->close();
        
        // 2. Jika ada mapel yang dipilih, masukkan satu per satu
        if (!empty($mapel_ids)) {
            $stmt_insert = $koneksi->prepare("INSERT INTO jadwal_mengajar (guru_id, kelas_id, mapel_id) VALUES (?, ?, ?)");
            foreach ($mapel_ids as $mapel_id) {
                $stmt_insert->bind_param("iii", $guru_id, $kelas_id, $mapel_id);
                $stmt_insert->execute();
            }
            $stmt_insert->close();
        }
        
        $koneksi->commit();
        echo json_encode(['status' => 'sukses']);

    } catch (mysqli_sql_exception $exception) {
        $koneksi->rollback();
        echo json_encode(['status' => 'gagal', 'message' => 'Error database: ' . $exception->getMessage()]);
    }

} else {
    echo json_encode(['status' => 'gagal', 'message' => 'Data tidak lengkap']);
}
?>