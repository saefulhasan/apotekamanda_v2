<?php
require_once "config.php";
require_once "auth.php";
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_pemesan']);
    $obat_id = (int)$_POST['obat_id'];
    $jumlah = (int)$_POST['jumlah'];

    $stmt = mysqli_prepare($conn, "INSERT INTO pesan_obat(nama_pemesan, obat_id, jumlah) VALUES(?,?,?)");
    mysqli_stmt_bind_param($stmt, "sii", $nama, $obat_id, $jumlah);
    mysqli_stmt_execute($stmt);

    header("Location: pesan_obat.php");
    exit;
}

if (isset($_GET['status'])) {
    $id = (int)$_GET['status'];
    $status = $_GET['to'] ?? 'pending';
    $allowed = ['pending','diproses','selesai'];
    if (in_array($status, $allowed)) {
        $stmt = mysqli_prepare($conn, "UPDATE pesan_obat SET status=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "si", $status, $id);
        mysqli_stmt_execute($stmt);
    }
    header("Location: pesan_obat.php");
    exit;
}

include "partials/header.php";
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0">Pesan Obat</h5>
            <small class="text-muted">Catat permintaan obat dari pasien atau unit lain.</small>
        </div>
    </div>
    <div class="card-body">
        <form method="post" class="row g-2">
            <div class="col-md-4">
                <label class="form-label small">Nama Pemesan</label>
                <input type="text" name="nama_pemesan" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Obat</label>
                <select name="obat_id" class="form-select" required>
                    <option value="">-- Pilih Obat --</option>
                    <?php
                    $o = mysqli_query($conn, "SELECT id, nama FROM obat ORDER BY nama");
                    while ($row = mysqli_fetch_assoc($o)) {
                        echo "<option value='{$row['id']}'>".htmlspecialchars($row['nama'])."</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Jumlah</label>
                <input type="number" name="jumlah" class="form-control" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100">
                    <i class="bi bi-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h6 class="card-title mb-2">Data Pesanan</h6>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Nama Pemesan</th>
                    <th>Obat</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                $q = mysqli_query($conn, "SELECT p.*, o.nama AS nama_obat FROM pesan_obat p LEFT JOIN obat o ON p.obat_id=o.id ORDER BY p.created_at DESC");
                if (mysqli_num_rows($q) === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted">Belum ada data.</td></tr>
                <?php else:
                    while ($row = mysqli_fetch_assoc($q)): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo $row['created_at']; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_pemesan']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_obat']); ?></td>
                            <td><?php echo (int)$row['jumlah']; ?></td>
                            <td>
                                <?php
                                $badge = [
                                    'pending' => 'secondary',
                                    'diproses' => 'warning',
                                    'selesai' => 'success'
                                ][$row['status']];
                                ?>
                                <span class="badge bg-<?php echo $badge; ?> text-capitalize">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="pesan_obat.php?status=<?php echo $row['id']; ?>&to=pending" class="btn btn-outline-secondary">Pending</a>
                                    <a href="pesan_obat.php?status=<?php echo $row['id']; ?>&to=diproses" class="btn btn-outline-warning">Proses</a>
                                    <a href="pesan_obat.php?status=<?php echo $row['id']; ?>&to=selesai" class="btn btn-outline-success">Selesai</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include "partials/footer.php";
?>
