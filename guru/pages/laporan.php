<?php
// File ini dipanggil dari index.php, tidak perlu session_start atau require db, dll.
$jadwal_result = $koneksi->query("SELECT j.id, k.nama_kelas, m.nama_mapel FROM jadwal_mengajar j JOIN kelas k ON j.kelas_id = k.id JOIN mata_pelajaran m ON j.mapel_id = m.id WHERE j.guru_id = $id_guru ORDER BY k.nama_kelas, m.nama_mapel");

function get_nama_bulan_id_local($bulan_angka) {
    $daftar_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
    return $daftar_bulan[$bulan_angka] ?? '';
}
?>

<div class="page-header">
    <h1 class="h3">Cetak & Unduh Laporan</h1>
    <p class="text-muted">Pilih jenis laporan yang ingin Anda unduh dalam format PDF.</p>
</div>

<div class="page-body">
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header"><h5 class="m-0">Laporan Per Bulan</h5></div>
                <div class="card-body d-flex flex-column">
                    <p class="text-muted">Untuk mengunduh laporan individual untuk satu bulan spesifik.</p>
                    <form action="ekspor_laporan.php" method="GET" target="_blank" class="mt-auto">
                        <div class="mb-3">
                            <label class="form-label">1. Pilih Jadwal Mengajar</label>
                            <select name="jadwal_id" class="form-select" required>
                                <option value="">-- Pilih Kelas & Mapel --</option>
                                <?php mysqli_data_seek($jadwal_result, 0); ?>
                                <?php while($jadwal = $jadwal_result->fetch_assoc()): ?>
                                    <option value="<?= $jadwal['id']; ?>"><?= htmlspecialchars($jadwal['nama_kelas'] . " - " . $jadwal['nama_mapel']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">2. Pilih Bulan & Tahun</label>
                            <div class="d-flex">
                                <select name="bulan" class="form-select me-2">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT); ?>" <?= (date('m') == $i) ? 'selected' : ''; ?>>
                                        <?= get_nama_bulan_id_local(str_pad($i, 2, '0', STR_PAD_LEFT)); ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                                <select name="tahun" class="form-select">
                                    <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?><option value="<?= $i; ?>"><?= $i; ?></option><?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">3. Pilih Jenis Laporan</label>
                            <div class="d-grid gap-2">
                                <button type="submit" name="jenis" value="hadir" class="btn btn-primary"><i class="bi bi-person-check-fill me-2"></i>Unduh Daftar Hadir</button>
                                <button type="submit" name="jenis" value="nilai" class="btn btn-success"><i class="bi bi-card-list me-2"></i>Unduh Daftar Nilai</button>
                                <button type="submit" name="jenis" value="jurnal" class="btn btn-info"><i class="bi bi-journal-text me-2"></i>Unduh Jurnal Mengajar</button>
                                <button type="submit" name="jenis" value="interaksi-bulanan" class="btn btn-secondary"><i class="bi bi-person-star me-2"></i>Unduh Laporan Interaksi</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header"><h5 class="m-0">Bundel Laporan Lengkap (Semester)</h5></div>
                <div class="card-body d-flex flex-column">
                    <p class="text-muted">Menggabungkan semua laporan (Daftar Hadir, Nilai, Jurnal) dalam satu file PDF untuk rentang waktu tertentu.</p>
                      <form action="ekspor_semester.php" method="GET" target="_blank" class="mt-auto">
                        <div class="mb-3">
                            <label class="form-label">1. Pilih Jadwal Mengajar</label>
                            <select name="jadwal_id" class="form-select" required>
                                <option value="">-- Pilih Kelas & Mapel --</option>
                                <?php mysqli_data_seek($jadwal_result, 0); ?>
                                <?php while($jadwal = $jadwal_result->fetch_assoc()): ?>
                                    <option value="<?= $jadwal['id']; ?>"><?= htmlspecialchars($jadwal['nama_kelas'] . " - " . $jadwal['nama_mapel']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">2. Pilih Rentang Waktu</label>
                            <div class="row">
                                <div class="col-6"><label class="form-label small">Dari Bulan:</label><select name="bulan_mulai" class="form-select">
                                    <?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT); ?>" <?= ($i == 7) ? 'selected' : ''; ?>><?= get_nama_bulan_id_local(str_pad($i, 2, '0', STR_PAD_LEFT)); ?></option><?php endfor; ?>
                                </select></div>
                                <div class="col-6"><label class="form-label small">Hingga Bulan:</label><select name="bulan_selesai" class="form-select">
                                      <?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT); ?>" <?= ($i == 12) ? 'selected' : ''; ?>><?= get_nama_bulan_id_local(str_pad($i, 2, '0', STR_PAD_LEFT)); ?></option><?php endfor; ?>
                                </select></div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-6"><label class="form-label small">Tahun:</label><select name="tahun" class="form-select">
                                    <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?><option value="<?= $i; ?>"><?= $i; ?></option><?php endfor; ?>
                                </select></div>
                            </div>
                        </div>
                          <div class="d-grid gap-2 mt-auto">
                            <button type="submit" class="btn btn-danger"><i class="bi bi-file-earmark-zip-fill me-2"></i>Unduh Bundel Laporan Lengkap</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>