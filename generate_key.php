<?php
require 'config/license.php';
if(isset($_POST['nama'])){
    $nama = $_POST['nama'];
    $key = hash('sha256', $nama . SECRET_SALT);
    echo "Nama Madrasah: <b>" . htmlspecialchars($nama) . "</b><br>";
    echo "SECRET_SALT: <b>" . SECRET_SALT . "</b><br>";
    echo "Kunci Lisensi Anda adalah:<br><textarea rows='3' cols='80' readonly>" . $key . "</textarea>";
}
?>
<form method="POST">
    <label>Masukkan Nama Madrasah Resmi:</label><br>
    <input type="text" name="nama" size="50">
    <button type="submit">Generate Kunci</button>
</form>