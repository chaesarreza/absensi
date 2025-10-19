<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    echo json_encode(['status' => 'gagal', 'message' => 'Akses ditolak.']);
    exit();
}

$response = [];
// Sekarang harus menerima mapel_id juga
if (isset($_POST['qr_code_key']) && isset($_POST['kelas_id']) && isset($_POST['mapel_id'])) {
    $qr_code_key = $_POST['qr_code_key'];
    $id_kelas_absen = $_POST['kelas_id'];
    $id_mapel_absen = $_POST['mapel_id']; // Ambil mapel_id
    $id_guru = $_SESSION['user_id'];

    $stmt_siswa = $koneksi->prepare("SELECT id, nama_siswa, kelas_id FROM siswa WHERE qr_code_key = ?");
    $stmt_siswa->bind_param("s", $qr_code_key);
    $stmt_siswa->execute();
    $result_siswa = $stmt_siswa->get_result();

    if ($result_siswa->num_rows === 1) {
        $siswa = $result_siswa->fetch_assoc();
        
        if ($siswa['kelas_id'] != $id_kelas_absen) {
            $response = ['status' => 'gagal', 'message' => 'Error: Siswa ini tidak terdaftar di kelas yang sedang Anda absen.'];
        } else {
            $id_siswa = $siswa['id'];
            $nama_siswa = $siswa['nama_siswa'];
            $tanggal_hari_ini = date('Y-m-d');

            // Cek absensi hari ini untuk mapel yg sama
            $stmt_cek_absen = $koneksi->prepare("SELECT id FROM absensi WHERE siswa_id = ? AND tanggal = ? AND mapel_id = ?");
            $stmt_cek_absen->bind_param("isi", $id_siswa, $tanggal_hari_ini, $id_mapel_absen);
            $stmt_cek_absen->execute();

            if ($stmt_cek_absen->get_result()->num_rows > 0) {
                $response = ['status' => 'gagal', 'message' => $nama_siswa . ' sudah diabsen untuk mata pelajaran ini hari ini.'];
            } else {
                $status_hadir = 'Hadir';
                // Tambahkan mapel_id ke query INSERT
                $stmt_insert = $koneksi->prepare("INSERT INTO absensi (siswa_id, tanggal, status, mapel_id, dicatat_oleh) VALUES (?, ?, ?, ?, ?)");
                $stmt_insert->bind_param("issii", $id_siswa, $tanggal_hari_ini, $status_hadir, $id_mapel_absen, $id_guru);
                
                if ($stmt_insert->execute()) {
                    $response = ['status' => 'sukses', 'message' => 'Hadir! ' . $nama_siswa, 'nama_siswa' => $nama_siswa, 'id' => $id_siswa];
                } else {
                    $response = ['status' => 'gagal', 'message' => 'Gagal menyimpan data absensi.'];
                }
            }
        }
    } else {
        $response = ['status' => 'gagal', 'message' => 'QR Code tidak valid atau siswa tidak ditemukan.'];
    }
    echo json_encode($response);
} else {
    echo json_encode(['status' => 'gagal', 'message' => 'Data tidak lengkap.']);
}
?>