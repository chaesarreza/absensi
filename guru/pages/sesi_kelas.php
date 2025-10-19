<?php
// File ini dipanggil dari index.php. Semua variabel yang dibutuhkan sudah disiapkan oleh file induknya.
// Variabel yang tersedia: $info, $siswa_list, $jurnal_tersimpan, $nilai_tersimpan, $active_jadwal_id, $tanggal_terpilih, $koneksi

// Ambil data Poin Keaktifan untuk tanggal yang dipilih
$stmt_poin = $koneksi->prepare("SELECT siswa_id, COUNT(id) as total_poin FROM poin_keaktifan WHERE jadwal_id = ? AND tanggal = ? GROUP BY siswa_id");
$stmt_poin->bind_param("is", $active_jadwal_id, $tanggal_terpilih);
$stmt_poin->execute();
$result_poin = $stmt_poin->get_result();
$poin_map = [];
while ($row = $result_poin->fetch_assoc()) {
    $poin_map[$row['siswa_id']] = $row['total_poin'];
}

// Ambil data Catatan Perilaku untuk tanggal yang dipilih
$stmt_catatan = $koneksi->prepare("SELECT siswa_id FROM catatan_perilaku WHERE jadwal_id = ? AND tanggal = ? GROUP BY siswa_id");
$stmt_catatan->bind_param("is", $active_jadwal_id, $tanggal_terpilih);
$stmt_catatan->execute();
$result_catatan = $stmt_catatan->get_result();
$catatan_map = [];
while ($row = $result_catatan->fetch_assoc()) {
    $catatan_map[$row['siswa_id']] = true;
}
?>

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?= htmlspecialchars($info['nama_kelas']); ?></h1>
        <p class="text-muted mb-0">Mata Pelajaran: <strong><?= htmlspecialchars($info['nama_mapel']); ?></strong></p>
    </div>
    <div class="date-selector">
        <form method="GET" class="d-flex align-items-center">
            <input type="hidden" name="page" value="sesi_kelas">
            <input type="hidden" name="jadwal_id" value="<?= $active_jadwal_id; ?>">
            <label for="tanggal" class="form-label me-2 mb-0">Tanggal:</label>
            <input type="date" name="tanggal" id="tanggal" class="form-control" value="<?= htmlspecialchars($tanggal_terpilih); ?>" onchange="this.form.submit()">
        </form>
    </div>
</div>

<div class="page-body">
    <ul class="nav nav-tabs mb-4" id="sesiKelasTab" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#absensi">Absensi & Interaksi</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#penilaian">Input Nilai</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#jurnal">Jurnal Mengajar</button></li>
    </ul>

    <div class="tab-content" id="sesiKelasTabContent">
        <div class="tab-pane fade show active" id="absensi" role="tabpanel">
            <?php if ($tanggal_terpilih == date('Y-m-d')): ?>
                <a href="scan.php?kelas_id=<?= $info['kelas_id']; ?>&mapel_id=<?= $info['mapel_id']; ?>" class="btn btn-lg btn-success mb-4 w-100"><i class="bi bi-qr-code-scan me-2"></i>Buka Scanner QR</a>
            <?php endif; ?>
            <div class="card">
                <div class="card-header"><h6 class="m-0">Daftar Siswa</h6></div>
                <div class="card-body">
                    <div id="status-update-absen" class="mb-3"></div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Nama Siswa & Poin Keaktifan</th>
                                    <th class="text-center">Status Kehadiran</th>
                                    <th class="text-center">Aksi Absensi</th>
                                    <th class="text-center">Catatan Perilaku</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if(!empty($siswa_list)): foreach($siswa_list as $siswa): ?>
                                <tr id="siswa-<?= $siswa['id']; ?>">
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($siswa['nama_siswa']); ?></div>
                                        <div class="d-flex align-items-center mt-1">
                                            <button class="btn btn-sm btn-outline-primary py-0 px-2 btn-tambah-poin" data-siswa-id="<?= $siswa['id']; ?>" title="Tambah Poin Keaktifan">+</button>
                                            <span class="ms-2 badge bg-primary poin-display" id="poin-<?= $siswa['id']; ?>"><?= $poin_map[$siswa['id']] ?? 0; ?> Poin</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $status = $siswa['status'];
                                        $badge_class = 'bg-secondary'; $badge_text = 'Belum Absen';
                                        if ($status == 'Hadir') { $badge_class = 'bg-success'; $badge_text = 'Hadir'; }
                                        elseif ($status == 'Sakit') { $badge_class = 'bg-warning text-dark'; $badge_text = 'Sakit'; }
                                        elseif ($status == 'Izin') { $badge_class = 'bg-info text-dark'; $badge_text = 'Izin'; }
                                        elseif ($status == 'Alpa') { $badge_class = 'bg-danger'; $badge_text = 'Alpa'; }
                                        ?><span class="badge rounded-pill <?= $badge_class; ?> status-badge" style="width: 90px;"><?= $badge_text; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-success btn-absen" title="Hadir" data-status="Hadir" data-siswa-id="<?= $siswa['id']; ?>">H</button>
                                            <button class="btn btn-outline-warning btn-absen" title="Sakit" data-status="Sakit" data-siswa-id="<?= $siswa['id']; ?>">S</button>
                                            <button class="btn btn-outline-info btn-absen" title="Izin" data-status="Izin" data-siswa-id="<?= $siswa['id']; ?>">I</button>
                                            <button class="btn btn-outline-danger btn-absen" title="Alpa" data-status="Alpa" data-siswa-id="<?= $siswa['id']; ?>">A</button>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-secondary btn-catatan" data-bs-toggle="modal" data-bs-target="#catatanModal" data-siswa-id="<?= $siswa['id']; ?>" data-siswa-nama="<?= htmlspecialchars($siswa['nama_siswa']); ?>">
                                            <i class="bi bi-journal-plus"></i>
                                            <?php if(isset($catatan_map[$siswa['id']])): ?>
                                                <i class="bi bi-check-circle-fill text-success ms-1" title="Sudah ada catatan"></i>
                                            <?php endif; ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="4" class="text-center text-muted p-3">Tidak ada siswa di kelas ini.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="penilaian" role="tabpanel">
             <form action="simpan_nilai.php#penilaian" method="POST">
                <input type="hidden" name="jadwal_id" value="<?= $active_jadwal_id; ?>"><input type="hidden" name="tanggal" value="<?= $tanggal_terpilih; ?>">
                <div class="card mb-4">
                    <div class="card-header"><h6 class="m-0">Formulir Input Nilai</h6></div>
                    <div class="card-body">
                        <div class="row mb-3"><div class="col-md-6"><label class="form-label">Jenis Penilaian</label><input type="text" name="jenis_nilai" class="form-control" placeholder="Contoh: Latihan Bab 1" required></div></div>
                        <table class="table">
                            <thead><tr><th>Nama Siswa</th><th style="width: 15%;">Nilai</th></tr></thead>
                            <tbody>
                                <?php if(!empty($siswa_list)): foreach($siswa_list as $siswa): ?>
                                    <tr><td><?= htmlspecialchars($siswa['nama_siswa']); ?></td><td><input type="number" name="nilai[<?= $siswa['id']; ?>]" class="form-control" min="0" max="100"></td></tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer text-end"><button type="submit" class="btn btn-primary">Simpan Semua Nilai</button></div>
                </div>
            </form>
            <div class="card">
                <div class="card-header"><h6 class="m-0">Nilai yang Sudah Diinput pada Tanggal Ini</h6></div>
                <div class="card-body">
                    <?php if (empty($nilai_tersimpan)): ?><p class="text-muted text-center p-3">Belum ada nilai yang diinput pada tanggal ini.</p>
                    <?php else: ?><table class="table table-striped table-hover"><thead><tr><th>Nama Siswa</th><th>Jenis Nilai</th><th>Nilai</th></tr></thead><tbody>
                        <?php foreach ($nilai_tersimpan as $nilai): ?><tr><td><?= htmlspecialchars($nilai['nama_siswa']); ?></td><td><?= htmlspecialchars($nilai['jenis_nilai']); ?></td><td><?= htmlspecialchars($nilai['nilai']); ?></td></tr><?php endforeach; ?>
                    </tbody></table><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="jurnal" role="tabpanel">
            <form action="simpan_jurnal.php#jurnal" method="POST">
                <input type="hidden" name="jadwal_id" value="<?= $active_jadwal_id; ?>"><input type="hidden" name="tanggal" value="<?= $tanggal_terpilih; ?>">
                <div class="card">
                    <div class="card-header"><h6 class="m-0">Jurnal Mengajar</h6></div>
                    <div class="card-body">
                        <div class="mb-3"><label class="form-label">Ringkasan Materi</label><textarea name="materi_diajarkan" class="form-control" rows="8" required><?= htmlspecialchars($jurnal_tersimpan['materi_diajarkan'] ?? ''); ?></textarea></div>
                        <div class="mb-3"><label class="form-label">Catatan Tambahan</label><textarea name="catatan" class="form-control" rows="4"><?= htmlspecialchars($jurnal_tersimpan['catatan'] ?? ''); ?></textarea></div>
                    </div>
                    <div class="card-footer text-end"><button type="submit" class="btn btn-primary">Simpan Jurnal</button></div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="catatanModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="catatanModalLabel">Catatan Perilaku</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="form-catatan">
          <input type="hidden" id="catatan-siswa-id" name="siswa_id">
          <p id="catatan-untuk-siswa" class="fw-bold"></p>
          <div class="mb-3">
            <label class="form-label">Jenis Catatan</label><br>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="jenis_catatan" id="jenis-positif" value="Positif" checked>
              <label class="form-check-label" for="jenis-positif">Positif</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="jenis_catatan" id="jenis-negatif" value="Negatif">
              <label class="form-check-label" for="jenis-negatif">Negatif</label>
            </div>
          </div>
          <div class="mb-3">
            <label for="catatan-teks" class="form-label">Isi Catatan</label>
            <textarea class="form-control" id="catatan-teks" name="catatan" rows="4" required></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="simpanCatatanBtn">Simpan Catatan</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusDivAbsen = document.getElementById('status-update-absen');
    if (!statusDivAbsen) return;
    const kelasId = <?= $info['kelas_id']; ?>; 
    const mapelId = <?= $info['mapel_id']; ?>;
    const tanggalAbsen = '<?= $tanggal_terpilih; ?>';
    const jadwalId = <?= $active_jadwal_id; ?>;

    // Logika Absensi Manual
    document.querySelectorAll('.btn-absen').forEach(button => {
        button.addEventListener('click', function() {
            const siswaId = this.dataset.siswaId;
            const status = this.dataset.status;
            const formData = new URLSearchParams();
            formData.append('siswa_id', siswaId); 
            formData.append('status', status);
            formData.append('kelas_id', kelasId); 
            formData.append('mapel_id', mapelId);
            formData.append('tanggal', tanggalAbsen);
            
            fetch('proses_absen_manual.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'sukses') {
                    const badge = document.querySelector(`#siswa-${siswaId} .status-badge`);
                    if(badge) {
                        badge.textContent = data.status_baru;
                        badge.className = `badge rounded-pill ${data.badge_class} me-3 status-badge`;
                    }
                } else {
                    statusDivAbsen.innerHTML = `<div class="alert alert-danger alert-dismissible fade show">${data.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
                }
            });
        });
    });

    // Logika Tambah Poin
    document.querySelectorAll('.btn-tambah-poin').forEach(button => {
        button.addEventListener('click', function() {
            const siswaId = this.dataset.siswaId;
            const formData = new URLSearchParams();
            formData.append('siswa_id', siswaId);
            formData.append('jadwal_id', jadwalId);
            formData.append('tanggal', tanggalAbsen);
            
            fetch('tambah_poin.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'sukses') {
                    document.getElementById(`poin-${siswaId}`).textContent = `${data.total_poin} Poin`;
                }
            });
        });
    });

    // Logika Modal Catatan
    const catatanModal = new bootstrap.Modal(document.getElementById('catatanModal'));
    const catatanSiswaIdInput = document.getElementById('catatan-siswa-id');
    const catatanUntukSiswaP = document.getElementById('catatan-untuk-siswa');
    const formCatatan = document.getElementById('form-catatan');

    document.querySelectorAll('.btn-catatan').forEach(button => {
        button.addEventListener('click', function() {
            catatanSiswaIdInput.value = this.dataset.siswaId;
            catatanUntukSiswaP.textContent = "Untuk: " + this.dataset.siswaNama;
            formCatatan.reset();
        });
    });
    
    document.getElementById('simpanCatatanBtn').addEventListener('click', function() {
        const formData = new URLSearchParams(new FormData(formCatatan));
        formData.append('jadwal_id', jadwalId);
        formData.append('tanggal', tanggalAbsen);
        
        fetch('simpan_catatan.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'sukses') {
                catatanModal.hide();
                // Refresh halaman untuk menampilkan ikon centang
                window.location.reload(); 
            }
        });
    });
 // --- KODE BARU UNTUK MENGATUR TAB AKTIF & NOTIFIKASI ---
    const urlHash = window.location.hash; // Mendapatkan anchor, e.g., #penilaian
    if (urlHash) {
        // Hapus kelas 'active' dari tab default
        document.querySelector('#sesiKelasTab .nav-link.active').classList.remove('active');
        document.querySelector('#sesiKelasTabContent .tab-pane.active').classList.remove('active', 'show');

        // Cari tab yang sesuai dengan anchor
        const tabToActivate = document.querySelector(`button[data-bs-target="${urlHash}"]`);
        if (tabToActivate) {
            tabToActivate.classList.add('active');
            const paneToActivate = document.querySelector(urlHash);
            if (paneToActivate) {
                paneToActivate.classList.add('active', 'show');
                
                // Tampilkan notifikasi "Berhasil Disimpan"
                const successAlert = `
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Data berhasil disimpan!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                // Tempatkan notifikasi di dalam tab yang aktif
                paneToActivate.insertAdjacentHTML('afterbegin', successAlert);
            }
        }
    }
});
</script>