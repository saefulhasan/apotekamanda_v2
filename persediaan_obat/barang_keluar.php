<?php
require_once "config.php";
require_once "auth.php";
require_login();

$error = '';
$edit_data = null;

/**
 * =========================
 * MODE EDIT (AMBIL DATA)
 * =========================
 */
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $qEdit = mysqli_query($conn, "SELECT * FROM barang_keluar WHERE id=$id LIMIT 1");
    $edit_data = $qEdit ? mysqli_fetch_assoc($qEdit) : null;

    if (!$edit_data) {
        $error = "Data edit tidak ditemukan.";
    }
}

/**
 * =========================
 * SIMPAN (TAMBAH / UPDATE)
 * =========================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $obat_id  = (int)($_POST['obat_id'] ?? 0);
    $jumlah   = (int)($_POST['jumlah'] ?? 0);
    $penerima = trim($_POST['penerima'] ?? '');
    $tanggal  = date('Y-m-d H:i:s');

    if ($obat_id <= 0 || $jumlah <= 0 || $penerima === '') {
        $error = "Obat, jumlah, dan penerima wajib diisi.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            if ($id > 0) {
                // ===== UPDATE =====
                $oldRes = mysqli_query($conn, "SELECT obat_id, jumlah FROM barang_keluar WHERE id=$id LIMIT 1");
                $oldRow = $oldRes ? mysqli_fetch_assoc($oldRes) : null;
                if (!$oldRow) throw new Exception("Data barang keluar tidak ditemukan.");

                $old_obat_id = (int)$oldRow['obat_id'];
                $old_jumlah  = (int)$oldRow['jumlah'];

                // balikin stok transaksi lama
                mysqli_query($conn, "UPDATE obat SET stok = stok + $old_jumlah WHERE id=$old_obat_id");
                if (mysqli_errno($conn)) throw new Exception("Gagal mengembalikan stok lama: " . mysqli_error($conn));

                // cek stok untuk transaksi baru
                $cekRes = mysqli_query($conn, "SELECT stok FROM obat WHERE id=$obat_id LIMIT 1");
                $cekRow = $cekRes ? mysqli_fetch_assoc($cekRes) : null;
                if (!$cekRow) throw new Exception("Obat tidak ditemukan.");
                $stokNow = (int)$cekRow['stok'];

                if ($stokNow < $jumlah) throw new Exception("Stok tidak cukup untuk update. Stok tersedia: $stokNow.");

                // update barang_keluar
                $stmt = mysqli_prepare($conn, "UPDATE barang_keluar SET obat_id=?, jumlah=?, penerima=? WHERE id=?");
                if (!$stmt) throw new Exception("Prepare UPDATE gagal.");
                mysqli_stmt_bind_param($stmt, "iisi", $obat_id, $jumlah, $penerima, $id);
                if (!mysqli_stmt_execute($stmt)) throw new Exception("Execute UPDATE gagal.");

                // kurangi stok sesuai transaksi baru
                mysqli_query($conn, "UPDATE obat SET stok = stok - $jumlah WHERE id=$obat_id");
                if (mysqli_errno($conn)) throw new Exception("Gagal mengurangi stok baru: " . mysqli_error($conn));

            } else {
                // ===== TAMBAH =====
                $stokRes = mysqli_query($conn, "SELECT stok FROM obat WHERE id=$obat_id LIMIT 1");
                $stokRow = $stokRes ? mysqli_fetch_assoc($stokRes) : null;
                if (!$stokRow) throw new Exception("Obat tidak ditemukan.");

                $stokNow = (int)$stokRow['stok'];
                if ($stokNow < $jumlah) throw new Exception("Stok tidak cukup. Stok tersedia: $stokNow.");

                $stmt = mysqli_prepare($conn, "INSERT INTO barang_keluar(tanggal, obat_id, jumlah, penerima) VALUES(?,?,?,?)");
                if (!$stmt) throw new Exception("Prepare INSERT gagal.");
                mysqli_stmt_bind_param($stmt, "siis", $tanggal, $obat_id, $jumlah, $penerima);
                if (!mysqli_stmt_execute($stmt)) throw new Exception("Execute INSERT gagal.");

                mysqli_query($conn, "UPDATE obat SET stok = stok - $jumlah WHERE id=$obat_id");
                if (mysqli_errno($conn)) throw new Exception("Gagal update stok: " . mysqli_error($conn));
            }

            mysqli_commit($conn);
            header("Location: barang_keluar.php");
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

/**
 * =========================
 * DELETE (HAPUS + STOK KEMBALI)
 * =========================
 */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    mysqli_begin_transaction($conn);
    try {
        $rowRes = mysqli_query($conn, "SELECT obat_id, jumlah FROM barang_keluar WHERE id=$id LIMIT 1");
        $row = $rowRes ? mysqli_fetch_assoc($rowRes) : null;

        if ($row) {
            $obat_id = (int)$row['obat_id'];
            $jumlah  = (int)$row['jumlah'];

            mysqli_query($conn, "UPDATE obat SET stok = stok + $jumlah WHERE id=$obat_id");
            if (mysqli_errno($conn)) throw new Exception("Gagal kembalikan stok: " . mysqli_error($conn));
        }

        mysqli_query($conn, "DELETE FROM barang_keluar WHERE id=$id");
        if (mysqli_errno($conn)) throw new Exception("Gagal hapus data: " . mysqli_error($conn));

        mysqli_commit($conn);
        header("Location: barang_keluar.php");
        exit;

    } catch (Throwable $e) {
        mysqli_rollback($conn);
        header("Location: barang_keluar.php?err=" . urlencode("Gagal hapus: " . $e->getMessage()));
        exit;
    }
}

if (isset($_GET['err']) && !$error) $error = $_GET['err'];

include "partials/header.php";
?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0">Barang Keluar</h5>
            <small class="text-muted">Input pengeluaran obat ke pasien / unit lain.</small>
        </div>

        <!-- INI TOMBOL TAMBAHNYA -->
        <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#formBK">
            <i class="bi bi-plus-circle"></i> Tambah Barang Keluar
        </button>
    </div>

    <!-- Form pakai collapse, otomatis kebuka kalau mode edit -->
    <div class="collapse <?php echo $edit_data ? 'show' : ''; ?>" id="formBK">
        <div class="card-body border-top">
            <?php if ($error): ?>
                <div class="alert alert-danger mb-2"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post" class="row g-2">
                <input type="hidden" name="id" value="<?php echo (int)($edit_data['id'] ?? 0); ?>">

                <div class="col-md-4">
                    <label class="form-label small">Obat</label>
                    <select name="obat_id" class="form-select" required>
                        <option value="">-- Pilih Obat --</option>
                        <?php
                        $o = mysqli_query($conn, "SELECT id, nama, stok FROM obat ORDER BY nama");
                        while ($row = mysqli_fetch_assoc($o)) {
                            $label = $row['nama']." (stok: ".$row['stok'].")";
                            $selected = ((int)($edit_data['obat_id'] ?? 0) === (int)$row['id']) ? 'selected' : '';
                            echo "<option value='{$row['id']}' $selected>".htmlspecialchars($label)."</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Jumlah</label>
                    <input type="number" name="jumlah" class="form-control" required
                           value="<?php echo htmlspecialchars((string)($edit_data['jumlah'] ?? '')); ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Penerima</label>
                    <input type="text" name="penerima" class="form-control" required
                           value="<?php echo htmlspecialchars($edit_data['penerima'] ?? ''); ?>">
                </div>

                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button class="btn btn-primary w-100">
                        <i class="bi bi-save"></i> <?php echo $edit_data ? 'Update' : 'Simpan'; ?>
                    </button>

                    <?php if ($edit_data): ?>
                        <a href="barang_keluar.php" class="btn btn-secondary w-100">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h6 class="card-title mb-2">Riwayat Barang Keluar</h6>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Nama Obat</th>
                    <th>Jumlah</th>
                    <th>Penerima</th>
                    <th style="width:140px;">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                $q = mysqli_query($conn, "SELECT bk.*, o.nama AS nama_obat
                                         FROM barang_keluar bk
                                         JOIN obat o ON bk.obat_id=o.id
                                         ORDER BY bk.tanggal DESC");
                if (!$q || mysqli_num_rows($q) === 0): ?>
                    <tr><td colspan="6" class="text-center text-muted">Belum ada data.</td></tr>
                <?php else:
                    while ($row = mysqli_fetch_assoc($q)): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['tanggal']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_obat']); ?></td>
                            <td><?php echo (int)$row['jumlah']; ?></td>
                            <td><?php echo htmlspecialchars($row['penerima']); ?></td>
                            <td class="d-flex gap-1">
                                <!-- EDIT -->
                                <a href="barang_keluar.php?edit=<?php echo (int)$row['id']; ?>"
                                   class="btn btn-sm btn-soft">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                <!-- DELETE -->
                                <a href="barang_keluar.php?delete=<?php echo (int)$row['id']; ?>"
                                   onclick="return confirm('Hapus data ini? Stok akan dikembalikan.');"
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
