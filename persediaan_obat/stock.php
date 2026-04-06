<?php
require_once "config.php";
require_once "auth.php";
require_login();

/**
 * =========================
 * MODE CETAK SEMUA DATA STOCK
 * =========================
 */
if (isset($_GET['print_all'])) {
    $q = mysqli_query($conn, "SELECT * FROM obat ORDER BY nama ASC");

    // hitung total stok & total nilai stok
    $totStok = 0;
    $totNilai = 0;

    if ($q && mysqli_num_rows($q) > 0) {
        mysqli_data_seek($q, 0);
        while ($r = mysqli_fetch_assoc($q)) {
            $s = (int)($r['stok'] ?? 0);
            $h = (int)($r['harga'] ?? 0);
            $totStok += $s;
            $totNilai += ($s * $h);
        }
        mysqli_data_seek($q, 0);
    }
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Cetak Data Stock Obat</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            .wrap { max-width: 950px; margin: 20px auto; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            th, td { border: 1px solid #ddd; padding: 6px; vertical-align: top; }
            th { background: #f5f5f5; }
            .right { text-align:right; }
            .no-print { margin-bottom: 12px; }
            .meta { margin-top: 6px; color:#555; }
            @media print { .no-print { display:none; } }
        </style>
    </head>
    <body>
    <div class="wrap">
        <div class="no-print">
            <button onclick="window.print()">Print</button>
            <button onclick="window.close()">Tutup</button>
        </div>

        <h2>Data Stock Obat</h2>
        <div class="meta">Tanggal Cetak: <?php echo date('d-m-Y H:i'); ?></div>
        <div class="meta">Total Stok: <b><?php echo (int)$totStok; ?></b> | Total Nilai Stok: <b>Rp <?php echo number_format((int)$totNilai,0,',','.'); ?></b></div>

        <table>
            <thead>
            <tr>
                <th style="width:50px;">No</th>
                <th>Nama Obat</th>
                <th>Deskripsi</th>
                <th class="right" style="width:80px;">Stok</th>
                <th class="right" style="width:140px;">Harga</th>
                <th class="right" style="width:160px;">Nilai Stok</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $no = 1;
            if ($q && mysqli_num_rows($q) > 0):
                while ($row = mysqli_fetch_assoc($q)):
                    $stok = (int)($row['stok'] ?? 0);
                    $harga = (int)($row['harga'] ?? 0);
                    $nilai = $stok * $harga;
                    ?>
                    <tr>
                        <td class="right"><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($row['nama'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['deskripsi'] ?? ''); ?></td>
                        <td class="right"><?php echo $stok; ?></td>
                        <td class="right">Rp <?php echo number_format($harga,0,',','.'); ?></td>
                        <td class="right">Rp <?php echo number_format($nilai,0,',','.'); ?></td>
                    </tr>
                <?php endwhile;
            else: ?>
                <tr><td colspan="6" style="text-align:center;color:#666;">Belum ada data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>window.onload = function(){ window.print(); };</script>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Helper: cek kolom ada atau tidak (biar tidak error kalau tabel tidak punya keterangan)
 */
function has_column(mysqli $conn, string $table, string $column): bool {
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $colEsc   = mysqli_real_escape_string($conn, $column);
    $q = mysqli_query($conn, "
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$tableEsc'
          AND COLUMN_NAME = '$colEsc'
    ");
    $n = 0;
    if ($q) {
        $row = mysqli_fetch_row($q);
        $n = (int)($row[0] ?? 0);
    }
    return $n > 0;
}

$bm_has_ket = has_column($conn, 'barang_masuk', 'keterangan');
$bk_has_ket = has_column($conn, 'barang_keluar', 'keterangan');

$error = '';

/**
 * =========================
 * SAVE (INSERT / UPDATE)
 * =========================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = $_POST['id'] ?? '';
    $nama  = trim($_POST['nama'] ?? '');
    $desk  = trim($_POST['deskripsi'] ?? '');
    $stok  = (int)($_POST['stok'] ?? 0);
    $harga = (int)($_POST['harga'] ?? 0);
    $tanggal = date('Y-m-d H:i:s');

    if ($nama === '') {
        $error = "Nama obat wajib diisi.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            if ($id) {
                $id = (int)$id;

                // ambil stok lama
                $oldQ = mysqli_query($conn, "SELECT stok FROM obat WHERE id=$id LIMIT 1");
                $old = $oldQ ? mysqli_fetch_assoc($oldQ) : null;
                $stok_lama = (int)($old['stok'] ?? 0);

                // update obat
                $stmt = mysqli_prepare($conn, "UPDATE obat SET nama=?, deskripsi=?, stok=?, harga=? WHERE id=?");
                if (!$stmt) throw new Exception("Prepare UPDATE gagal.");
                mysqli_stmt_bind_param($stmt, "ssiii", $nama, $desk, $stok, $harga, $id);
                if (!mysqli_stmt_execute($stmt)) throw new Exception("Execute UPDATE gagal.");

                // hitung selisih
                $selisih = $stok - $stok_lama;

                if ($selisih > 0) {
                    // stok naik => barang_masuk
                    if ($bm_has_ket) {
                        $ket = "Penyesuaian stok (edit obat)";
                        $stmtBM = mysqli_prepare($conn, "INSERT INTO barang_masuk (obat_id, jumlah, tanggal, keterangan) VALUES (?,?,?,?)");
                        if (!$stmtBM) throw new Exception("Prepare INSERT barang_masuk gagal.");
                        mysqli_stmt_bind_param($stmtBM, "iiss", $id, $selisih, $tanggal, $ket);
                    } else {
                        $stmtBM = mysqli_prepare($conn, "INSERT INTO barang_masuk (obat_id, jumlah, tanggal) VALUES (?,?,?)");
                        if (!$stmtBM) throw new Exception("Prepare INSERT barang_masuk gagal.");
                        mysqli_stmt_bind_param($stmtBM, "iis", $id, $selisih, $tanggal);
                    }
                    if (!mysqli_stmt_execute($stmtBM)) throw new Exception("Execute INSERT barang_masuk gagal.");

                } elseif ($selisih < 0) {
                    // stok turun => barang_keluar
                    $keluar = abs($selisih);
                    if ($bk_has_ket) {
                        $ket = "Penyesuaian stok (edit obat)";
                        $stmtBK = mysqli_prepare($conn, "INSERT INTO barang_keluar (obat_id, jumlah, tanggal, keterangan) VALUES (?,?,?,?)");
                        if (!$stmtBK) throw new Exception("Prepare INSERT barang_keluar gagal.");
                        mysqli_stmt_bind_param($stmtBK, "iiss", $id, $keluar, $tanggal, $ket);
                    } else {
                        $stmtBK = mysqli_prepare($conn, "INSERT INTO barang_keluar (obat_id, jumlah, tanggal) VALUES (?,?,?)");
                        if (!$stmtBK) throw new Exception("Prepare INSERT barang_keluar gagal.");
                        mysqli_stmt_bind_param($stmtBK, "iis", $id, $keluar, $tanggal);
                    }
                    if (!mysqli_stmt_execute($stmtBK)) throw new Exception("Execute INSERT barang_keluar gagal.");
                }

            } else {
                // insert obat baru
                $stmt = mysqli_prepare($conn, "INSERT INTO obat(nama, deskripsi, stok, harga) VALUES(?,?,?,?)");
                if (!$stmt) throw new Exception("Prepare INSERT obat gagal.");
                mysqli_stmt_bind_param($stmt, "ssii", $nama, $desk, $stok, $harga);
                if (!mysqli_stmt_execute($stmt)) throw new Exception("Execute INSERT obat gagal.");

                $obat_id_baru = mysqli_insert_id($conn);

                // stok awal dianggap barang masuk (kalau > 0)
                if ($stok > 0) {
                    if ($bm_has_ket) {
                        $ket = "Stok awal (tambah obat)";
                        $stmtBM = mysqli_prepare($conn, "INSERT INTO barang_masuk (obat_id, jumlah, tanggal, keterangan) VALUES (?,?,?,?)");
                        if (!$stmtBM) throw new Exception("Prepare INSERT barang_masuk gagal.");
                        mysqli_stmt_bind_param($stmtBM, "iiss", $obat_id_baru, $stok, $tanggal, $ket);
                    } else {
                        $stmtBM = mysqli_prepare($conn, "INSERT INTO barang_masuk (obat_id, jumlah, tanggal) VALUES (?,?,?)");
                        if (!$stmtBM) throw new Exception("Prepare INSERT barang_masuk gagal.");
                        mysqli_stmt_bind_param($stmtBM, "iis", $obat_id_baru, $stok, $tanggal);
                    }
                    if (!mysqli_stmt_execute($stmtBM)) throw new Exception("Execute INSERT barang_masuk gagal.");
                }
            }

            mysqli_commit($conn);
            header("Location: stock.php");
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = "Gagal simpan: " . $e->getMessage();
        }
    }
}

/**
 * =========================
 * DELETE OBAT (BISA HAPUS APA PUN)
 * - akan hapus histori terkait juga supaya aman dari FK / data yatim
 * =========================
 */
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    mysqli_begin_transaction($conn);
    try {
        // opsional: cek ada atau tidak
        $cek = mysqli_query($conn, "SELECT id FROM obat WHERE id=$id LIMIT 1");
        if (!$cek || mysqli_num_rows($cek) === 0) {
            throw new Exception("Data obat tidak ditemukan.");
        }

        // hapus histori dulu (penting kalau ada foreign key)
        mysqli_query($conn, "DELETE FROM barang_masuk WHERE obat_id=$id");
        if (mysqli_errno($conn)) throw new Exception("Gagal hapus barang_masuk: " . mysqli_error($conn));

        mysqli_query($conn, "DELETE FROM barang_keluar WHERE obat_id=$id");
        if (mysqli_errno($conn)) throw new Exception("Gagal hapus barang_keluar: " . mysqli_error($conn));

        // baru hapus obat
        mysqli_query($conn, "DELETE FROM obat WHERE id=$id");
        if (mysqli_errno($conn)) throw new Exception("Gagal hapus obat: " . mysqli_error($conn));

        mysqli_commit($conn);
        header("Location: stock.php");
        exit;

    } catch (Throwable $e) {
        mysqli_rollback($conn);
        header("Location: stock.php?err=" . urlencode("Gagal hapus: " . $e->getMessage()));
        exit;
    }
}

/**
 * =========================
 * MODE EDIT
 * =========================
 */
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $res = mysqli_query($conn, "SELECT * FROM obat WHERE id=$id");
    $edit_data = $res ? mysqli_fetch_assoc($res) : null;
}

if (isset($_GET['err']) && !$error) {
    $error = $_GET['err'];
}

include "partials/header.php";
?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0">Stock Barang</h5>
            <small class="text-muted">
                Tambah/edit stok akan otomatis tercatat sebagai <b>Barang Masuk/Keluar</b>
            </small>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#formObat">
            <i class="bi bi-plus-circle"></i> Tambah / Edit Obat
        </button>
    </div>

    <div class="collapse <?php echo $edit_data ? 'show' : ''; ?>" id="formObat">
        <div class="card-body border-top">

            <?php if ($error): ?>
                <div class="alert alert-danger mb-2"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post" class="row g-2">
                <input type="hidden" name="id" value="<?php echo $edit_data['id'] ?? ''; ?>">

                <div class="col-md-4">
                    <label class="form-label small">Nama Obat</label>
                    <input type="text" name="nama" class="form-control" required
                           value="<?php echo htmlspecialchars($edit_data['nama'] ?? ''); ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Deskripsi</label>
                    <input type="text" name="deskripsi" class="form-control"
                           value="<?php echo htmlspecialchars($edit_data['deskripsi'] ?? ''); ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Stok</label>
                    <input type="number" name="stok" class="form-control" required
                           value="<?php echo htmlspecialchars($edit_data['stok'] ?? 0); ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Harga (Rp)</label>
                    <input type="number" name="harga" class="form-control"
                           value="<?php echo htmlspecialchars($edit_data['harga'] ?? 0); ?>">
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="card-title mb-0">Data Stock</h6>

            <!-- TOMBOL CETAK SEMUA -->
            <a href="stock.php?print_all=1" target="_blank" class="btn btn-success btn-sm">
                <i class="bi bi-printer"></i> Cetak Semua
            </a>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Obat</th>
                    <th>Deskripsi</th>
                    <th>Stok</th>
                    <th>Harga</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                $q = mysqli_query($conn, "SELECT * FROM obat ORDER BY nama ASC");
                if (!$q || mysqli_num_rows($q) === 0): ?>
                    <tr><td colspan="6" class="text-center text-muted">Belum ada data.</td></tr>
                <?php else:
                    while ($row = mysqli_fetch_assoc($q)): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['nama']); ?></td>
                            <td><?php echo htmlspecialchars($row['deskripsi']); ?></td>
                            <td><?php echo (int)$row['stok']; ?></td>
                            <td>Rp <?php echo number_format((int)$row['harga'],0,',','.'); ?></td>
                            <td class="d-flex gap-1">
                                <a href="stock.php?edit=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-soft">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                <a href="stock.php?delete=<?php echo (int)$row['id']; ?>"
                                   onclick="return confirm('Hapus data ini? (Akan menghapus histori barang masuk/keluar juga)');"
                                   class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include "partials/footer.php"; ?>
