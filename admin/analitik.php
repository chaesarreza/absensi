<?php
session_start();
$title = "Dasbor Analitik";
require_once '../config/db.php';
require_once 'templates/header.php';

// --- Variabel Filter ---
$bulan_filter = $_GET['bulan'] ?? date('m');
$tahun_filter = $_GET['tahun'] ?? date('Y');
$nama_bulan_filter = date('F', mktime(0,0,0,$bulan_filter,10));

// --- LOGIKA UNTUK GRAFIK KEHADIRAN BULANAN ---
$tahun_ini = date('Y');
$kehadiran_per_bulan = [];
for ($bulan = 1; $bulan <= 12; $bulan++) {
    $stmt_hadir = $koneksi->prepare("SELECT COUNT(id) as total FROM absensi WHERE status = 'Hadir' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
    $stmt_hadir->bind_param("is", $bulan, $tahun_ini);
    $stmt_hadir->execute();
    $total_hadir = $stmt_hadir->get_result()->fetch_assoc()['total'];

    $stmt_total = $koneksi->prepare("SELECT COUNT(id) as total FROM absensi WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
    $stmt_total->bind_param("is", $bulan, $tahun_ini);
    $stmt_total->execute();
    $total_absensi = $stmt_total->get_result()->fetch_assoc()['total'];

    $persentase = ($total_absensi > 0) ? ($total_hadir / $total_absensi) * 100 : 0;
    $kehadiran_per_bulan[] = round($persentase, 2);
}
$data_grafik_kehadiran = json_encode($kehadiran_per_bulan);

// --- LOGIKA UNTUK PERINGKAT KELAS (DENGAN QUERY YANG DIPERBAIKI) ---
$query_peringkat = "SELECT 
                        k.nama_kelas,
                        COUNT(a.id) as total_absensi,
                        SUM(CASE WHEN a.status = 'Hadir' THEN 1 ELSE 0 END) as total_hadir
                    FROM kelas k
                    LEFT JOIN siswa s ON s.kelas_id = k.id
                    LEFT JOIN absensi a ON a.siswa_id = s.id AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
                    GROUP BY k.id
                    ORDER BY (SUM(CASE WHEN a.status = 'Hadir' THEN 1 ELSE 0 END) / COUNT(a.id)) DESC"; // PERBAIKAN DI SINI
$stmt_peringkat = $koneksi->prepare($query_peringkat);
$stmt_peringkat->bind_param("ss", $bulan_filter, $tahun_filter);
$stmt_peringkat->execute();
$peringkat_kelas = $stmt_peringkat->get_result()->fetch_all(MYSQLI_ASSOC);

// --- LOGIKA UNTUK SISWA PERLU PERHATIAN (ALPA > 3) ---
$query_alpa = "SELECT s.nama_siswa, k.nama_kelas, COUNT(a.id) as jumlah_alpa
               FROM absensi a
               JOIN siswa s ON a.siswa_id = s.id
               JOIN kelas k ON s.kelas_id = k.id
               WHERE a.status = 'Alpa' AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
               GROUP BY s.id
               HAVING jumlah_alpa > 3
               ORDER BY jumlah_alpa DESC";
$stmt_alpa = $koneksi->prepare($query_alpa);
$stmt_alpa->bind_param("ss", $bulan_filter, $tahun_filter);
$stmt_alpa->execute();
$siswa_alpa = $stmt_alpa->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header">
    <h1 class="h3">Dasbor Analitik</h1>
    <p class="text-muted">Ringkasan data dan statistik penting dari aktivitas absensi.</p>
</div>

<div class="page-body">
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-header"><h6 class="m-0">Grafik Persentase Kehadiran Siswa Tahun <?= $tahun_ini; ?></h6></div>
                <div class="card-body"><canvas id="grafikKehadiran"></canvas></div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header"><h6 class="m-0">Peringkat Kehadiran Kelas (Bulan <?= $nama_bulan_filter; ?>)</h6></div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead><tr><th>Peringkat</th><th>Nama Kelas</th><th>Kehadiran</th></tr></thead>
                        <tbody>
                            <?php if (count($peringkat_kelas) > 0): $no = 1; ?>
                                <?php foreach($peringkat_kelas as $kelas): ?>
                                    <?php $persen = ($kelas['total_absensi'] > 0) ? round(($kelas['total_hadir'] / $kelas['total_absensi']) * 100, 1) : 0; ?>
                                    <tr>
                                        <td><?= $no++; ?>.</td>
                                        <td><?= htmlspecialchars($kelas['nama_kelas']); ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" style="width: <?= $persen; ?>%;" aria-valuenow="<?= $persen; ?>" aria-valuemin="0" aria-valuemax="100"><?= $persen; ?>%</div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted">Belum ada data absensi bulan ini.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header"><h6 class="m-0">Siswa Perlu Perhatian (Alpa > 3 Kali Bulan Ini)</h6></div>
                <div class="card-body">
                    <?php if (count($siswa_alpa) > 0): ?>
                        <ul class="list-group list-group-flush">
                        <?php foreach($siswa_alpa as $siswa): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($siswa['nama_siswa']); ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($siswa['nama_kelas']); ?></small>
                                </div>
                                <span class="badge bg-danger rounded-pill"><?= $siswa['jumlah_alpa']; ?> Alpa</span>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-center text-muted p-3">Tidak ada siswa yang memenuhi kriteria bulan ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('grafikKehadiran');
    const dataKehadiran = <?= $data_grafik_kehadiran; ?>;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
            datasets: [{
                label: '% Kehadiran',
                data: dataKehadiran,
                backgroundColor: 'rgba(26, 188, 156, 0.7)',
                borderColor: 'rgba(26, 188, 156, 1)',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, max: 100, ticks: { callback: function(value) { return value + "%" } } } },
            plugins: { legend: { display: false } }
        }
    });
});
</script>

<?php require_once 'templates/footer.php'; ?>