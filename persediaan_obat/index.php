<?php
require_once "config.php";
require_once "auth.php";
require_login();
$user = current_user();

/**
 * =========================
 * FILTER TANGGAL (default hari ini)
 * =========================
 */
$tanggal = (isset($_GET['tanggal']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tanggal']))
    ? $_GET['tanggal']
    : date('Y-m-d');

$tanggalEsc = mysqli_real_escape_string($conn, $tanggal);

// Statistik dasar
$obat_count = (int)(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM obat"))[0] ?? 0);
$total_stok = (int)(mysqli_fetch_row(mysqli_query($conn, "SELECT COALESCE(SUM(stok),0) FROM obat"))[0] ?? 0);

/**
 * =========================
 * BARANG MASUK (sinkron dari tabel barang_masuk)
 * - Akan bertambah jika:
 *   1) tambah obat baru dengan stok awal > 0 (dicatat barang_masuk)
 *   2) edit obat stok naik (dicatat barang_masuk)
 *   3) tambah stok (dicatat barang_masuk)
 * =========================
 */
$bm = (int)(mysqli_fetch_row(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(jumlah),0) FROM barang_masuk WHERE DATE(tanggal)='$tanggalEsc'"
))[0] ?? 0);

/**
 * =========================
 * BARANG KELUAR
 * - Manual dari barang_keluar
 * - Resep dari resep (created_at)
 * =========================
 */
$bk_manual = (int)(mysqli_fetch_row(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(jumlah),0) FROM barang_keluar WHERE DATE(tanggal)='$tanggalEsc'"
))[0] ?? 0);

$bk_resep = (int)(mysqli_fetch_row(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(jumlah),0) FROM resep WHERE DATE(created_at)='$tanggalEsc'"
))[0] ?? 0);

$bk = $bk_manual + $bk_resep;

/**
 * =========================
 * (OPSIONAL) DIAGNOSTIK:
 * jika ada stok naik tapi belum tercatat, kamu bisa lihat selisihnya di sini.
 * Ini bukan angka utama, cuma info tambahan.
 * =========================
 */
$stok_tambah_master = 0;
$stok_kurang_master = 0;

// Kalau kamu punya kolom updated_at di tabel obat, kita bisa hitung lebih akurat.
// Kalau tidak ada, bagian ini akan tetap 0 (aman).
$cekUpdatedAt = mysqli_query($conn, "SHOW COLUMNS FROM obat LIKE 'updated_at'");
if ($cekUpdatedAt && mysqli_num_rows($cekUpdatedAt) > 0) {
    // total perubahan stok (+/-) dari edit master obat di tanggal filter
    // Ini asumsi: setiap perubahan stok update updated_at (kalau tidak, abaikan saja)
    // Karena tidak ada log stok sebelumnya, ini hanya indikasi, bukan akurat 100%.
    $stok_tambah_master = (int)(mysqli_fetch_row(mysqli_query(
        $conn,
        "SELECT 0"
    ))[0] ?? 0);
    $stok_kurang_master = 0;
}

include "partials/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Dashboard</h4>
        <small class="text-muted">Ringkasan persediaan & transaksi</small>
    </div>

    <!-- Filter Tanggal -->
    <form method="get" class="d-flex gap-2 align-items-center">
        <label class="small text-muted mb-0">Filter tanggal:</label>
        <input type="date" name="tanggal" class="form-control form-control-sm"
               value="<?php echo htmlspecialchars($tanggal); ?>">
        <button class="btn btn-sm btn-primary">Terapkan</button>
       
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small mb-1">Jumlah Obat</div>
                    <div class="fs-4 fw-semibold"><?php echo $obat_count; ?></div>
                </div>
                <span class="badge bg-soft-primary text-primary px-3 py-2">
                    <i class="bi bi-capsule"></i>
                </span>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small mb-1">Total Stok</div>
                    <div class="fs-4 fw-semibold"><?php echo $total_stok; ?></div>
                </div>
                <span class="badge bg-soft-primary text-primary px-3 py-2">
                    <i class="bi bi-stack"></i>
                </span>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small mb-1">Barang Masuk (<?php echo htmlspecialchars($tanggal); ?>)</div>
                    <div class="fs-4 fw-semibold"><?php echo $bm; ?></div>
                    
                </div>
                <span class="badge bg-soft-primary text-primary px-3 py-2">
                    <i class="bi bi-box-arrow-in-down"></i>
                </span>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small mb-1">Barang Keluar (<?php echo htmlspecialchars($tanggal); ?>)</div>
                    <div class="fs-4 fw-semibold"><?php echo $bk; ?></div>
                    
                </div>
                <span class="badge bg-soft-primary text-primary px-3 py-2">
                    <i class="bi bi-box-arrow-up"></i>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">Persediaan Obat Terbaru</h5>
                    <small class="text-muted">5 data terakhir</small>
                </div>
            </div>
            <div class="card-body">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Nama Obat</th>
                        <th>Deskripsi</th>
                        <th>Stok</th>
                        <th>Harga</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $q = mysqli_query($conn, "SELECT * FROM obat ORDER BY id DESC LIMIT 5");
                    if (mysqli_num_rows($q) === 0): ?>
                        <tr><td colspan='4' class='text-center text-muted'>Belum ada data obat.</td></tr>
                    <?php else:
                        while ($row = mysqli_fetch_assoc($q)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                <td><?php echo htmlspecialchars($row['deskripsi']); ?></td>
                                <td><?php echo (int)$row['stok']; ?></td>
                                <td>Rp <?php echo number_format((int)$row['harga'],0,',','.'); ?></td>
                            </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h6 class="card-title mb-1">Ringkasan Transaksi (<?php echo htmlspecialchars($tanggal); ?>)</h6>
                <p class="text-muted small mb-2">
                    <b>Barang Masuk</b> diambil dari tabel <code>barang_masuk</code> (jadi akan naik jika stok ditambah lewat tambah/edit obat & input barang masuk).
                    <br>
                    <b>Barang Keluar</b> = tabel <code>barang_keluar</code> + tabel <code>resep</code>.
                </p>
                <ul class="mb-0">
                    <li>Barang masuk: <b><?php echo $bm; ?></b></li>
                    <li>Barang keluar manual: <b><?php echo $bk_manual; ?></b></li>
                    <li>Barang keluar dari resep: <b><?php echo $bk_resep; ?></b></li>
                    <li>Total keluar: <b><?php echo $bk; ?></b></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-title mb-1">Akses Cepat</h6>
                <p class="text-muted small mb-2">Menu yang sering digunakan.</p>
                <div class="d-grid gap-2">
                    <a href="stock.php" class="btn btn-primary btn-sm"><i class="bi bi-box-seam me-1"></i> Kelola Stock</a>
                    <a href="barang_masuk.php" class="btn btn-soft btn-sm"><i class="bi bi-box-arrow-in-down me-1"></i> Input Barang Masuk</a>
                    <a href="barang_keluar.php" class="btn btn-soft btn-sm"><i class="bi bi-box-arrow-up me-1"></i> Input Barang Keluar</a>
                    <a href="resep.php" class="btn btn-soft btn-sm"><i class="bi bi-clipboard2-pulse me-1"></i> Input Resep</a>
                </div>
            </div>
        </div>

        <div class="card border-0">
            <div class="card-body">
                <h6 class="card-title mb-1">Info Login</h6>
                <p class="small mb-1"><strong><?php echo htmlspecialchars($user['name']); ?></strong></p>
                <p class="small text-muted mb-0">
                    Role: <span class="text-capitalize"><?php echo htmlspecialchars($user['role']); ?></span><br>
                    Email: <?php echo htmlspecialchars($user['email']); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include "partials/footer.php"; ?>
