<?php
session_start();
require_once '../config/db.php';

// Keamanan dasar
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_guru = $_GET['id'];

    // Memulai transaksi database untuk memastikan semua proses aman
    $koneksi->begin_transaction();

    try {
        // 1. Kosongkan jabatan Wali Kelas
        $stmt1 = $koneksi->prepare("UPDATE kelas SET wali_kelas_id = NULL WHERE wali_kelas_id = ?");
        $stmt1->bind_param("i", $id_guru);
        $stmt1->execute();
        $stmt1->close();

        // 2. Hapus semua Jadwal Mengajar guru ini (mereset jadwal)
        $stmt2 = $koneksi->prepare("DELETE FROM jadwal_mengajar WHERE guru_id = ?");
        $stmt2->bind_param("i", $id_guru);
        $stmt2->execute();
        $stmt2->close();

        // 3. Kosongkan data pencatat absensi
        $stmt3 = $koneksi->prepare("UPDATE absensi SET dicatat_oleh = NULL WHERE dicatat_oleh = ?");
        $stmt3->bind_param("i", $id_guru);
        $stmt3->execute();
        $stmt3->close();

        // 4. Hapus data guru itu sendiri
        $stmt4 = $koneksi->prepare("DELETE FROM users WHERE id = ? AND role = 'guru'");
        $stmt4->bind_param("i", $id_guru);
        $stmt4->execute();
        $stmt4->close();

        // Jika semua berhasil, konfirmasi transaksi
        $koneksi->commit();
        header("Location: guru.php?status=sukses_hapus");

    } catch (mysqli_sql_exception $exception) {
        // Jika ada satu saja yang gagal, batalkan semua perubahan
        $koneksi->rollback();
        // Arahkan ke halaman guru dengan pesan error umum
        header("Location: guru.php?status=gagal_hapus&reason=transaksi_gagal");
    }
    
    exit();

} else {
    header("Location: guru.php");
    exit();
}
?>