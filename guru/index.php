<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header("Location: ../login.php"); exit();
}
$title = "Guru Workspace";
require_once '../config/db.php';

$id_guru = $_SESSION['user_id'];
$page = $_GET['page'] ?? 'dashboard';
$active_jadwal_id = $_GET['jadwal_id'] ?? null;
$active_kelas_id = null;

// Ambil semua jadwal mengajar untuk menu kiri
$query_all_jadwal = "SELECT j.id, k.id as kelas_id, k.nama_kelas, m.nama_mapel FROM jadwal_mengajar j JOIN kelas k ON j.kelas_id = k.id JOIN mata_pelajaran m ON j.mapel_id = m.id WHERE j.guru_id = ? ORDER BY k.nama_kelas, m.nama_mapel";
$stmt_all = $koneksi->prepare($query_all_jadwal);
$stmt_all->bind_param("i", $id_guru);
$stmt_all->execute();
$result_all_jadwal = $stmt_all->get_result();
$jadwal_per_kelas = [];
while ($row = $result_all_jadwal->fetch_assoc()) {
    $jadwal_per_kelas[$row['nama_kelas']][] = $row;
    if ($active_jadwal_id && $row['id'] == $active_jadwal_id) {
        $active_kelas_id = $row['kelas_id'];
    }
}
require_once 'templates/header.php';
?>

<div class="guru-workspace">
    <aside class="schedule-list">
        <h3 class="mt-2">Menu Utama</h3>
        <a href="index.php?page=dashboard" class="schedule-link <?= ($page == 'dashboard' && !$active_jadwal_id) ? 'active' : ''; ?>">
            <span class="subject-name"><i class="bi bi-house-door me-2"></i>Dashboard</span>
        </a>
        <a href="index.php?page=laporan" class="schedule-link <?= ($page == 'laporan') ? 'active' : ''; ?>">
            <span class="subject-name"><i class="bi bi-file-earmark-text me-2"></i>Laporan Absensi</span>
        </a>

        <h3 class="mt-4">Jadwal Mengajar</h3>
        <div class="accordion accordion-flush schedule-accordion" id="scheduleAccordion">
            <?php foreach ($jadwal_per_kelas as $nama_kelas => $jadwal_items): ?>
                <?php
                    $kelas_id_group = $jadwal_items[0]['kelas_id'];
                    $is_active_group = ($active_kelas_id == $kelas_id_group);
                ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?= $is_active_group ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $kelas_id_group; ?>">
                            <?= htmlspecialchars($nama_kelas); ?>
                        </button>
                    </h2>
                    <div id="collapse-<?= $kelas_id_group; ?>" class="accordion-collapse collapse <?= $is_active_group ? 'show' : ''; ?>" data-bs-parent="#scheduleAccordion">
                        <div class="accordion-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($jadwal_items as $jadwal): ?>
                                    <a href="index.php?page=sesi_kelas&jadwal_id=<?= $jadwal['id']; ?>" class="schedule-link list-group-item-action <?= (isset($_GET['jadwal_id']) && $_GET['jadwal_id'] == $jadwal['id']) ? 'active' : ''; ?>">
                                        <span class="subject-name"><?= htmlspecialchars($jadwal['nama_mapel']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>

    <main class="main-content">
        <?php
        // PERUBAHAN DI SINI: Tanggal sekarang diambil dari URL, atau default hari ini
        $tanggal_terpilih = $_GET['tanggal'] ?? date('Y-m-d');

        if ($page == 'sesi_kelas' && $active_jadwal_id) {
            $jadwal_id = $active_jadwal_id;
            // Query-query di bawah ini sekarang akan menggunakan $tanggal_terpilih
            $stmt_info = $koneksi->prepare("SELECT k.nama_kelas, m.nama_mapel, k.id as kelas_id, m.id as mapel_id FROM jadwal_mengajar j JOIN kelas k ON j.kelas_id = k.id JOIN mata_pelajaran m ON j.mapel_id=m.id WHERE j.id = ? AND j.guru_id = ?");
            $stmt_info->bind_param("ii", $jadwal_id, $id_guru); $stmt_info->execute();
            $info = $stmt_info->get_result()->fetch_assoc();

            $query_siswa = "SELECT s.id, s.nama_siswa, a.status FROM siswa s LEFT JOIN absensi a ON s.id = a.siswa_id AND a.tanggal = ? AND a.mapel_id = ? WHERE s.kelas_id = ? ORDER BY s.nama_siswa ASC";
            $stmt_siswa = $koneksi->prepare($query_siswa);
            $stmt_siswa->bind_param("sii", $tanggal_terpilih, $info['mapel_id'], $info['kelas_id']);
            $stmt_siswa->execute();
            $siswa_list = $stmt_siswa->get_result()->fetch_all(MYSQLI_ASSOC);

            $stmt_jurnal = $koneksi->prepare("SELECT materi_diajarkan, catatan FROM jurnal_guru WHERE jadwal_id = ? AND tanggal = ?");
            $stmt_jurnal->bind_param("is", $jadwal_id, $tanggal_terpilih); $stmt_jurnal->execute();
            $jurnal_tersimpan = $stmt_jurnal->get_result()->fetch_assoc();

            $stmt_nilai = $koneksi->prepare("SELECT p.jenis_nilai, p.nilai, s.nama_siswa FROM penilaian p JOIN siswa s ON p.siswa_id = s.id WHERE p.jadwal_id = ? AND p.tanggal = ? ORDER BY p.jenis_nilai, s.nama_siswa");
            $stmt_nilai->bind_param("is", $jadwal_id, $tanggal_terpilih); $stmt_nilai->execute();
            $nilai_tersimpan = $stmt_nilai->get_result()->fetch_all(MYSQLI_ASSOC);
            
            include 'pages/sesi_kelas.php';

        } elseif ($page == 'laporan') {
            include 'pages/laporan.php';
        } else {
            // Halaman dashboard default
            echo '<div class="d-flex flex-column justify-content-center align-items-center h-100 text-center welcome-message">';
            echo '<i class="bi bi-journal-bookmark-fill" style="font-size: 5rem; opacity: 0.5;"></i>';
            echo '<h2 class="mt-4">Selamat Datang, ' . htmlspecialchars($_SESSION['nama_lengkap']) . '</h2>';
            echo '<p class="welcome-subtitle">Pilih menu di sebelah kiri untuk memulai.</p>';
            echo '</div>';
        }
        ?>
    </main>
</div>
<?php require_once 'templates/footer.php'; ?>