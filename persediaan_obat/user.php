<?php
require_once "config.php";
require_once "auth.php";
require_role(['admin']); // hanya admin

$error = '';

// =========================
// CREATE / UPDATE USER
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = trim($_POST['id'] ?? '');
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = $_POST['role'] ?? 'apoteker';

    if ($name === '' || $email === '') {
        $error = "Nama dan email wajib diisi.";
    } else {
        // cek email duplikat (role boleh sama berapa pun)
        if ($id !== '') {
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "si", $email, $id);
        } else {
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email=? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $email);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($res && mysqli_num_rows($res) > 0) {
            $error = "Email sudah digunakan user lain.";
        } else {
            // UPDATE
            if ($id !== '') {
                if ($password !== '') {
                    // password disimpan apa adanya (TANPA HASH)
                    $stmt = mysqli_prepare($conn, "UPDATE users SET name=?, email=?, password=?, role=? WHERE id=?");
                    mysqli_stmt_bind_param($stmt, "ssssi", $name, $email, $password, $role, $id);
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE users SET name=?, email=?, role=? WHERE id=?");
                    mysqli_stmt_bind_param($stmt, "sssi", $name, $email, $role, $id);
                }

                if (!mysqli_stmt_execute($stmt)) {
                    $error = "Gagal update user: " . mysqli_error($conn);
                } else {
                    header("Location: user.php");
                    exit;
                }
            }
            // INSERT
            else {
                if ($password === '') {
                    $error = "Password wajib diisi untuk user baru.";
                } else {
                    // password disimpan apa adanya (TANPA HASH)
                    $stmt = mysqli_prepare($conn, "INSERT INTO users(name, email, password, role) VALUES(?,?,?,?)");
                    mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $password, $role);

                    if (!mysqli_stmt_execute($stmt)) {
                        $error = "Gagal menambah user: " . mysqli_error($conn);
                    } else {
                        header("Location: user.php");
                        exit;
                    }
                }
            }
        }
    }
}

// =========================
// DELETE USER
// =========================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // jangan hapus diri sendiri
    if ($id !== (int)current_user()['id']) {
        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
    }

    header("Location: user.php");
    exit;
}

// =========================
// LOAD EDIT DATA
// =========================
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $edit = $res ? mysqli_fetch_assoc($res) : null;
}

include "partials/header.php";
?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0">Data User</h5>
            <small class="text-muted">Kelola akun pengguna sistem (role boleh lebih dari 1 user).</small>
        </div>
    </div>

    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-3">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="row g-2">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit['id'] ?? ''); ?>">

            <div class="col-md-3">
                <label class="form-label small">Nama</label>
                <input type="text" name="name" class="form-control" required
                       value="<?php echo htmlspecialchars($edit['name'] ?? ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label small">Email</label>
                <input type="email" name="email" class="form-control" required
                       value="<?php echo htmlspecialchars($edit['email'] ?? ''); ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label small">Password</label>
                <input type="text" name="password" class="form-control"
                       placeholder="<?php echo $edit ? '(kosongkan jika tidak diubah)' : ''; ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label small">Role</label>
                <select name="role" class="form-select">
                    <?php
                    $roles = ['admin'=>'Admin','apoteker'=>'Apoteker','gudang'=>'Gudang','owner'=>'Owner'];
                    $current = $edit['role'] ?? 'apoteker';
                    foreach ($roles as $val=>$label) {
                        $sel = ($val === $current) ? 'selected' : '';
                        echo "<option value='".htmlspecialchars($val)."' $sel>".htmlspecialchars($label)."</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end gap-2">
                <button class="btn btn-primary w-100">
                    <i class="bi bi-save"></i> Simpan
                </button>

                <?php if ($edit): ?>
                    <a href="user.php" class="btn btn-secondary w-100">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h6 class="card-title mb-2">List User</h6>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>No</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                $q = mysqli_query($conn, "SELECT * FROM users ORDER BY id ASC");
                if (!$q || mysqli_num_rows($q) === 0): ?>
                    <tr><td colspan="5" class="text-center text-muted">Belum ada data.</td></tr>
                <?php else:
                    while ($row = mysqli_fetch_assoc($q)): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td class="text-capitalize"><?php echo htmlspecialchars($row['role']); ?></td>
                            <td class="d-flex gap-1">
                                <a href="user.php?edit=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-soft">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                <?php if ((int)$row['id'] !== (int)current_user()['id']): ?>
                                    <a href="user.php?delete=<?php echo (int)$row['id']; ?>"
                                       onclick="return confirm('Hapus user ini?');"
                                       class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include "partials/footer.php"; ?>
