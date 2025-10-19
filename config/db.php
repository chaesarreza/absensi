<?php

// Konfigurasi Database
$host = 'localhost';      // Nama host server database
$user = 'root';           // Username database (default XAMPP adalah 'root')
$pass = '';               // Password database (default XAMPP kosong)
$db_name = 'db_absensi_madrasah'; // Nama database yang sudah kita buat

// Membuat koneksi ke database
$koneksi = new mysqli($host, $user, $pass, $db_name);

// Memeriksa apakah koneksi berhasil atau gagal
if ($koneksi->connect_error) {
    // Jika koneksi gagal, hentikan skrip dan tampilkan pesan error
    die("Koneksi ke database gagal: " . $koneksi->connect_error);
}

// Mengatur zona waktu default ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

?>