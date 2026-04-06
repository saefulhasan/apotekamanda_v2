<?php
require_once "config.php";
require_once "auth.php";
require_login();

/**
 * Helper escape (AMAN PHP 8.1+)
 */
function e($str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

$error = '';
$edit_data = null;

/**
 * =========================
 * MODE EDIT
 * =========================
 */
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $qEdit = mysqli_query($conn, "SELECT * FROM barang_masuk WHERE id=$id LIMIT 1");
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
                $oldRes = mysqli_query($conn, "SELECT obat_id, jumlah FROM barang_masuk WHERE id=$id LIMIT 1");
                $oldRow = $oldRes ? mysqli_fetch_assoc($oldRes) : null;
                if (!$oldRow) throw new Exception("Data barang masuk tidak ditemukan.");

                $old_obat_id = (int)$oldRow['obat_id'];
                $old_jumlah  = (int)$oldRow['jumlah'];

                mysqli_query($conn, "UPDATE obat SET stok = GREATEST(stok - $old_jumlah,0) WHERE id=$old_obat_id");

                $stmt = mysqli_prepare($conn, "UPDATE barang_masuk SET obat_id=?, jumlah=?, penerima=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, "iisi", $obat_id, $jumlah, $penerima, $id);
                mysqli_stmt_execute($stmt);

                mysqli_query($conn, "UPDATE obat SET stok = stok + $jumlah WHERE id=$obat_id");

            } else {
                // ===== INSERT =====
                $stmt = mysqli_prepare($conn, "INSERT INTO barang_masuk(tanggal, obat_id, jumlah, penerima) VALUES(?,?,?,?)");
                mysqli_stmt_bind_param($stmt, "siis", $tanggal, $obat_id, $jumlah, $penerima);
                mysqli_stmt_execute($stmt);

                mysqli_query($conn, "UPDATE obat SET stok = stok + $jumlah WHERE id=$obat_id");
            }

            mysqli_commit($conn);
            header("Location: barang_masuk.php");
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

/**
 * =========================
 * DELETE
 * =========================
 */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    mysqli_begin_transaction($conn);
    try {
        $rowRes = mysqli_query($conn, "SELECT obat_id, jumlah FROM barang_masuk WHERE id=$id LIMIT 1");
        $row = $rowRes ? mysqli_fetch_assoc($rowRes) : null;

        if ($row) {
            $obat_id = (int)$row['obat_id'];
            $jumlah  = (int)$row['jumlah'];
            mysqli_query($conn, "UPDATE obat SET stok = GREATEST(stok - $jumlah,0) WHERE id=$obat_id");
        }

        mysqli_query($conn, "DELETE FROM barang_masuk WHERE id=$id");

        mysqli_commit($conn);
        header("Location: barang_masuk.php");
        exit;

    } catch (Throwable $e) {
        mysqli_rollback($conn);
        header("Location: barang_masuk.php?err=" . urlencode($e->getMessage()));
        exit;
    }
}

if (isset($_GET['err']) && !$error) $error = $_GET['err'];

include "partials/header.php";
?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
        <div>
            <h5 class="mb-0">Barang Masuk</h5>
            <small class="text-muted">Input penerimaan obat</small>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#formBM">
            <i class="bi bi-plus-circle"></i> Tambah
        </button>
    </div>

    <div class="collapse <?= $edit_data ? 'show' : '' ?>" id="formBM">
        <div class="card-body border-top">

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" class="row g-2">
                <input type="hidden" name="id" value="<?= (int)($edit_data['id'] ?? 0) ?>">

                <div class="col-md-4">
                    <label class="form-label">Obat</label>
                    <select name="obat_id" class="form-select" required>
                        <option value="">-- Pilih Obat --</option>
                        <?php
                        $o = mysqli_query($conn, "SELECT id,nama FROM obat ORDER BY nama");
                        while ($r = mysqli_fetch_assoc($o)):
                            $sel = ((int)($edit_data['obat_id'] ?? 0) === (int)$r['id']) ? 'selected' : '';
                        ?>
                            <option value="<?= $r['id'] ?>" <?= $sel ?>><?= e($r['nama']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Jumlah</label>
                    <input type="number" name="jumlah" class="form-control" required
                           value="<?= e($edit_data['jumlah'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Penerima</label>
                    <input type="text" name="penerima" class="form-control" required
                           value="<?= e($edit_data['penerima'] ?? (current_user()['name'] ?? '')) ?>">
                </div>

                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button class="btn btn-primary w-100">
                        <?= $edit_data ? 'Update' : 'Simpan' ?>
                    </button>
                    <?php if ($edit_data): ?>
                        <a href="barang_masuk.php" class="btn btn-secondary w-100">Batal</a>
                    <?php endif; ?>
                </div>
            </form>

        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h6>Riwayat Barang Masuk</h6>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Obat</th>
                        <th>Jumlah</th>
                        <th>Penerima</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $no=1;
                $q = mysqli_query($conn, "SELECT bm.*,o.nama AS nama_obat FROM barang_masuk bm JOIN obat o ON bm.obat_id=o.id ORDER BY bm.tanggal DESC");
                if (!$q || mysqli_num_rows($q)==0):
                ?>
                    <tr><td colspan="6" class="text-center text-muted">Belum ada data</td></tr>
                <?php else:
                    while ($r=mysqli_fetch_assoc($q)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= e($r['tanggal']) ?></td>
                            <td><?= e($r['nama_obat']) ?></td>
                            <td><?= (int)$r['jumlah'] ?></td>
                            <td><?= e($r['penerima']) ?></td>
                            <td>
                                <a href="?edit=<?= $r['id'] ?>" class="btn btn-sm btn-soft">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="?delete=<?= $r['id'] ?>" class="btn btn-sm btn-danger"
                                   onclick="return confirm('Hapus data ini?')">
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
