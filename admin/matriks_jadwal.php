<?php
session_start();
$title = "Matriks Jadwal Mengajar";
require_once '../config/db.php';
require_once 'templates/header.php';

// 1. Ambil semua data master
$guru_list = $koneksi->query("SELECT id, nama_lengkap FROM users WHERE role = 'guru' ORDER BY nama_lengkap ASC")->fetch_all(MYSQLI_ASSOC);
$kelas_list = $koneksi->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC")->fetch_all(MYSQLI_ASSOC);
$mapel_list = $koneksi->query("SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel ASC")->fetch_all(MYSQLI_ASSOC);

// 2. Ambil data jadwal yang sudah ada dan susun untuk TAMPILAN
$result_jadwal = $koneksi->query("SELECT j.guru_id, j.kelas_id, m.nama_mapel FROM jadwal_mengajar j JOIN mata_pelajaran m ON j.mapel_id = m.id");
$jadwal_display_map = [];
while ($row = $result_jadwal->fetch_assoc()) {
    $jadwal_display_map[$row['guru_id']][$row['kelas_id']][] = $row['nama_mapel'];
}
?>

<div class="page-header">
    <h1 class="h3">Matriks Jadwal Mengajar</h1>
    <p class="text-muted">Klik "Atur" untuk menugaskan satu atau beberapa mata pelajaran pada setiap guru dan kelas.</p>
</div>

<div class="page-body">
    <div class="card shadow">
        <div class="card-body">
            <div id="status-simpan" class="mb-3"></div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm text-center">
                    <thead>
                        <tr>
                            <th class="align-middle" style="width: 20%;">Nama Guru</th>
                            <?php foreach ($kelas_list as $kelas): ?>
                                <th><?= htmlspecialchars($kelas['nama_kelas']); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($guru_list as $guru): ?>
                            <tr>
                                <td class="text-start align-middle p-2"><?= htmlspecialchars($guru['nama_lengkap']); ?></td>
                                <?php foreach ($kelas_list as $kelas): ?>
                                    <td class="p-2" id="cell-<?= $guru['id']; ?>-<?= $kelas['id']; ?>">
                                        <div class="subjects-list mb-2">
                                            <?php 
                                            $mapel_diajar = $jadwal_display_map[$guru['id']][$kelas['id']] ?? [];
                                            if (empty($mapel_diajar)) {
                                                echo '<span class="text-muted small">-- Kosong --</span>';
                                            } else {
                                                foreach ($mapel_diajar as $mapel) {
                                                    echo '<span class="badge bg-primary me-1 mb-1">' . htmlspecialchars($mapel) . '</span>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <button class="btn btn-secondary btn-sm btn-atur-jadwal" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#jadwalModal"
                                                data-guru-id="<?= $guru['id']; ?>" 
                                                data-kelas-id="<?= $kelas['id']; ?>"
                                                data-guru-nama="<?= htmlspecialchars($guru['nama_lengkap']); ?>"
                                                data-kelas-nama="<?= htmlspecialchars($kelas['nama_kelas']); ?>">
                                            Atur
                                        </button>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="jadwalModal" tabindex="-1" aria-labelledby="jadwalModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="jadwalModalLabel">Atur Jadwal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="modal-mapel-list">
            <p class="text-center">Memuat mata pelajaran...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="simpanJadwalBtn">Simpan Perubahan</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const jadwalModalEl = document.getElementById('jadwalModal');
    if (!jadwalModalEl) return;

    const jadwalModal = new bootstrap.Modal(jadwalModalEl);
    const modalTitle = document.getElementById('jadwalModalLabel');
    const modalMapelList = document.getElementById('modal-mapel-list');
    const simpanBtn = document.getElementById('simpanJadwalBtn');
    const statusDiv = document.getElementById('status-simpan');

    document.querySelectorAll('.btn-atur-jadwal').forEach(button => {
        button.addEventListener('click', function() {
            const guruId = this.dataset.guruId;
            const kelasId = this.dataset.kelasId;
            const guruNama = this.dataset.guruNama;
            const kelasNama = this.dataset.kelasNama;

            modalTitle.textContent = `Atur Mapel untuk ${guruNama} di ${kelasNama}`;
            modalMapelList.innerHTML = '<p class="text-center">Memuat mata pelajaran...</p>';
            simpanBtn.dataset.guruId = guruId;
            simpanBtn.dataset.kelasId = kelasId;

            fetch(`get_jadwal.php?guru_id=${guruId}&kelas_id=${kelasId}`)
                .then(response => response.json())
                .then(data => {
                    let mapelHtml = '';
                    if (data.semua_mapel && data.semua_mapel.length > 0) {
                        data.semua_mapel.forEach(mapel => {
                            // mapel_terpilih adalah array of IDs, jadi kita konversi ke string untuk perbandingan
                            const isChecked = data.mapel_terpilih.map(String).includes(mapel.id);
                            mapelHtml += `
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="${mapel.id}" id="mapel-${mapel.id}" ${isChecked ? 'checked' : ''}>
                                    <label class="form-check-label" for="mapel-${mapel.id}">
                                        ${mapel.nama_mapel}
                                    </label>
                                </div>
                            `;
                        });
                    } else {
                        mapelHtml = '<p>Belum ada data mata pelajaran. Silakan tambahkan terlebih dahulu.</p>';
                    }
                    modalMapelList.innerHTML = mapelHtml;
                });
        });
    });

    simpanBtn.addEventListener('click', function() {
        const guruId = this.dataset.guruId;
        const kelasId = this.dataset.kelasId;
        const checkedMapel = document.querySelectorAll('#modal-mapel-list .form-check-input:checked');
        
        const mapelIds = Array.from(checkedMapel).map(cb => cb.value);

        const formData = new URLSearchParams();
        formData.append('guru_id', guruId);
        formData.append('kelas_id', kelasId);
        mapelIds.forEach(id => formData.append('mapel_ids[]', id));
        
        statusDiv.innerHTML = '<div class="alert alert-info">Menyimpan...</div>';
        jadwalModal.hide();

        fetch('simpan_jadwal.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'sukses') {
                statusDiv.innerHTML = '<div class="alert alert-success">Jadwal berhasil diperbarui!</div>';
                const cellToUpdate = document.getElementById(`cell-${guruId}-${kelasId}`);
                const subjectsListDiv = cellToUpdate.querySelector('.subjects-list');
                let newSubjectsHtml = '';
                const checkedMapelLabels = Array.from(checkedMapel).map(cb => cb.nextElementSibling.textContent.trim());

                if (checkedMapelLabels.length > 0) {
                    checkedMapelLabels.forEach(label => {
                        newSubjectsHtml += `<span class="badge bg-primary me-1 mb-1">${label}</span>`;
                    });
                } else {
                    newSubjectsHtml = '<span class="text-muted small">-- Kosong --</span>';
                }
                subjectsListDiv.innerHTML = newSubjectsHtml;

            } else {
                statusDiv.innerHTML = `<div class="alert alert-danger">Gagal menyimpan: ${data.message}</div>`;
            }
            setTimeout(() => { statusDiv.innerHTML = ''; }, 3000);
        });
    });
});
</script>

<?php require_once 'templates/footer.php'; ?>