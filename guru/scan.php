<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../login.php");
    exit();
}
// Validasi sekarang butuh kelas_id DAN mapel_id
if (!isset($_GET['kelas_id']) || !is_numeric($_GET['kelas_id']) || !isset($_GET['mapel_id']) || !is_numeric($_GET['mapel_id'])) {
    header("Location: index.php");
    exit();
}

$kelas_id = $_GET['kelas_id'];
$mapel_id = $_GET['mapel_id']; // Ambil mapel_id
require_once '../config/db.php';

// Ambil nama kelas dan mapel untuk ditampilkan
$stmt_info = $koneksi->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
$stmt_info->bind_param("i", $kelas_id);
$stmt_info->execute();
$nama_kelas = $stmt_info->get_result()->fetch_assoc()['nama_kelas'];

$stmt_info = $koneksi->prepare("SELECT nama_mapel FROM mata_pelajaran WHERE id = ?");
$stmt_info->bind_param("i", $mapel_id);
$stmt_info->execute();
$nama_mapel = $stmt_info->get_result()->fetch_assoc()['nama_mapel'];

$title = "Absensi " . $nama_kelas;
require_once 'templates/header.php';
?>

<div class="container-fluid text-center">
    <h2 class="text-dark">Absensi Kelas <span class="text-success"><?= htmlspecialchars($nama_kelas) ?></span></h2>
    <p class="text-muted">Mata Pelajaran: <strong><?= htmlspecialchars($nama_mapel) ?></strong></p>

    <div id="reader" style="width: 100%; max-width: 500px; margin: 20px auto; border: 5px solid #198754;"></div>
    <div id="scan-result" class="mt-3">Menunggu hasil pindaian...</div>

    <div class="mt-4">
        <h4>Daftar Hadir Hari Ini:</h4>
        <ul id="hadir-list" class="list-group text-start"></ul>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
    function onScanSuccess(decodedText, decodedResult) {
        let resultDiv = document.getElementById('scan-result');
        let hadirList = document.getElementById('hadir-list');
        const kelasId = <?= $kelas_id; ?>;
        const mapelId = <?= $mapel_id; ?>; // Kirim mapel_id juga

        const formData = new URLSearchParams();
        formData.append('qr_code_key', decodedText);
        formData.append('kelas_id', kelasId);
        formData.append('mapel_id', mapelId); // Tambahkan mapel_id ke data

        fetch('proses_absen.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            resultDiv.textContent = data.message;
            if (data.status === 'sukses') {
                resultDiv.className = 'alert alert-success';
                let isAlreadyListed = !!document.getElementById('siswa-' + data.id);
                if (!isAlreadyListed) {
                    let listItem = document.createElement('li');
                    listItem.id = 'siswa-' + data.id;
                    listItem.className = 'list-group-item';
                    listItem.textContent = data.nama_siswa;
                    hadirList.appendChild(listItem);
                }
            } else {
                resultDiv.className = 'alert alert-danger';
            }
        });
    }

    let html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", { fps: 10, qrbox: 250 }
    );
    html5QrcodeScanner.render(onScanSuccess);
</script>

<?php require_once 'templates/footer.php'; ?>