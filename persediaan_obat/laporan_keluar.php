<?php
require_once "config.php";
require_once "auth.php";
require_login();

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

$whereBK = "1=1";
$whereR  = "1=1";

if ($from && $to) {
    $fromEsc = mysqli_real_escape_string($conn, $from);
    $toEsc   = mysqli_real_escape_string($conn, $to);

    $whereBK = "DATE(bk.tanggal) BETWEEN '$fromEsc' AND '$toEsc'";
    $whereR  = "DATE(r.created_at) BETWEEN '$fromEsc' AND '$toEsc'";
}

/**
 * Gabungkan:
 * - barang_keluar (manual)
 * - resep (otomatis keluar)
 *
 * Output disamakan kolomnya:
 * tanggal, nama_obat, jumlah, penerima, sumber
 */
$sql = "
    SELECT 
        bk.tanggal AS tanggal,
        o.nama AS nama_obat,
        bk.jumlah AS jumlah,
        bk.penerima AS penerima,
        'Manual' AS sumber
    FROM barang_keluar bk
    JOIN obat o ON bk.obat_id = o.id
    WHERE $whereBK

    UNION ALL

    SELECT
        r.created_at AS tanggal,
        o.nama AS nama_obat,
        r.jumlah AS jumlah,
        r.nama_pasien AS penerima,
        'Resep' AS sumber
    FROM resep r
    JOIN obat o ON r.obat_id = o.id
    WHERE $whereR

    ORDER BY tanggal DESC
";

$q = mysqli_query($conn, $sql);

include "partials/header.php";
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0">Laporan Barang Keluar</h5>
            <small class="text-muted">Keluar dihitung dari <b>Barang Keluar (manual)</b> + <b>Resep</b>.</small>
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
                    <th>Penerima / Pasien</th>
                    <th>Sumber</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                if (!$q || mysqli_num_rows($q) === 0): ?>
                    <tr><td colspan="6" class="text-center text-muted">Tidak ada data pada rentang ini.</td></tr>
                <?php else:
                    $total = 0;
                    while ($row = mysqli_fetch_assoc($q)):
                        $total += (int)$row['jumlah'];
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['tanggal']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_obat']); ?></td>
                            <td><?php echo (int)$row['jumlah']; ?></td>
                            <td><?php echo htmlspecialchars($row['penerima'] ?? '-'); ?></td>
                            <td>
                                <?php if (($row['sumber'] ?? '') === 'Resep'): ?>
                                    <span class="badge bg-success">Resep</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Manual</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <tr>
                        <td colspan="3" class="text-end"><b>Total Keluar</b></td>
                        <td><b><?php echo (int)$total; ?></b></td>
                        <td colspan="2"></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include "partials/footer.php"; ?>
