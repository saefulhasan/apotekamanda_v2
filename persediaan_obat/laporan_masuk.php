<?php
require_once "config.php";
require_once "auth.php";
require_login();

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

$where = "1=1";
if ($from && $to) {
    $where = "DATE(tanggal) BETWEEN '".mysqli_real_escape_string($conn,$from)."' AND '".mysqli_real_escape_string($conn,$to)."'";
}

$q = mysqli_query($conn, "SELECT bm.*, o.nama AS nama_obat FROM barang_masuk bm JOIN obat o ON bm.obat_id=o.id WHERE $where ORDER BY bm.tanggal DESC");

include "partials/header.php";
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0">Laporan Barang Masuk</h5>
            <small class="text-muted">Filter berdasarkan rentang tanggal.</small>
        </div>
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3" method="get">
            <div class="col-md-3">
                <label class="form-label small">Dari Tanggal</label>
                <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Sampai Tanggal</label>
                <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100">
                    <i class="bi bi-filter"></i> Filter
                </button>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-soft w-100" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak
                </button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Nama Obat</th>
                    <th>Jumlah</th>
                    <th>Penerima</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                if (mysqli_num_rows($q) === 0): ?>
                    <tr><td colspan="5" class="text-center text-muted">Tidak ada data pada rentang ini.</td></tr>
                <?php else:
                    while ($row = mysqli_fetch_assoc($q)): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo $row['tanggal']; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_obat']); ?></td>
                            <td><?php echo (int)$row['jumlah']; ?></td>
                            <td><?php echo htmlspecialchars($row['penerima']); ?></td>
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
