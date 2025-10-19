<?php
require_once 'db.php';
require_once 'license.php';

// Ambil nama madrasah saat ini dari database
$result_settings = $koneksi->query("SELECT setting_value FROM settings WHERE setting_name = 'nama_madrasah'");
$nama_madrasah_sekarang = $result_settings->fetch_assoc()['setting_value'];

// Hasilkan kunci baru berdasarkan nama madrasah saat ini
$generated_key = hash('sha256', $nama_madrasah_sekarang . SECRET_SALT);

// Bandingkan kunci yang dihasilkan dengan kunci lisensi asli
if (LICENSE_KEY !== $generated_key) {
    // Jika tidak cocok, hentikan aplikasi
    die("
        <div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>
            <h1 style='color: red;'>LISENSI TIDAK VALID</h1>
            <p>Ada perubahan konfigurasi yang tidak sah. Silakan hubungi developer aplikasi Anda.</p>
        </div>
    ");
}