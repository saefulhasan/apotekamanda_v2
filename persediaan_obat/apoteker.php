<?php
require_once "config.php";
require_once "auth.php";
require_role(['admin']); // hanya admin

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $nama = trim($_POST['nama']);
    $kontak = trim($_POST['kontak']);

    if ($id) {
        $stmt = mysqli_prepare($conn, "UPDATE apoteker SET nama=?, kontak=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssi", $nama, $kontak, $id);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO apoteker(nama, kontak) VALUES(?,?)");
        mysqli_stmt_bind_param($stmt, "ss", $nama, $kontak);
    }
    mysqli_stmt_execute($stmt);
    header("Location: apoteker.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM apoteker WHERE id=$id");
    header("Location: apoteker.php");
    exit;
}

$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM apoteker WHERE id=$id"));
}

include "partials/header.php";
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0">Data Apoteker</h5>
            <small class="text-muted">Kelola data apoteker.</small>
        </div>
    </div>
    <div class="card-body">
        <form method="post" class="row g-2">
            <input type="hidden" name="id" value="<?php echo $edit['id'] ?? ''; ?>">
            <div class="col-md-5">
                <label class="form-label small">Nama Apoteker</label>
                <input type="text" name="nama" class="form-control" required
                       value="<?php echo htmlspecialchars($edit['nama'] ?? ''); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Kontak</label>
                <input type="text" name="kontak" class="form-control"
                       value="<?php echo htmlspecialchars($edit['kontak'] ?? ''); ?>">
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
        <h6 class="card-title mb-2">Data Apoteker</h6>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Apoteker</th>
                    <th>Kontak</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                $q = mysqli_query($conn, "SELECT * FROM apoteker ORDER BY nama");
                if (mysqli_num_rows($q) === 0): ?>
                    <tr><td colspan="4" class="text-center text-muted">Belum ada data.</td></tr>
                <?php else:
                    while ($row = mysqli_fetch_assoc($q)): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['nama']); ?></td>
                            <td><?php echo htmlspecialchars($row['kontak']); ?></td>
                            <td>
                                <a href="apoteker.php?edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-soft">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="apoteker.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger"
                                   onclick="return confirm('Hapus data ini?');">
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

<?php
include "partials/footer.php";
?>
