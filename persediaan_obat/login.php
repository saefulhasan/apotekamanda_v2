<?php
require_once "config.php";
session_start();

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = mysqli_prepare($conn, "SELECT id, name, email, password, role FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($res);

    if ($user && $user['password'] === $password) { // TANPA HASH sesuai permintaan
        $_SESSION['user'] = [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role']
        ];
        header("Location: index.php");
        exit;
    } else {
        $error = "Email atau password salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Apotek Amanda</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            display:flex;
            min-height:100vh;
            align-items:center;
            justify-content:center;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            border-radius: 22px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .18);
            background: #ffffff;
            padding: 26px 26px 24px;
        }
        .login-pill {
            background: rgba(91,60,250,.07);
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 12px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="d-flex align-items-center mb-3">
        <div class="brand-icon me-2">
            <i class="bi bi-prescription2"></i>
        </div>
        <div>
            <div class="brand-title">Apotek Amanda</div>
            <small class="text-muted">Dashboard Persediaan Obat</small>
        </div>
    </div>
    <div class="login-pill d-inline-flex align-items-center gap-2">
        <i class="bi bi-shield-lock"></i> <span>Masuk ke panel</span>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger small py-2"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" class="mt-2">
        <div class="mb-3">
            <label class="form-label small">Email</label>
            <input type="email" name="email" class="form-control" placeholder="admin@gmail.com" required>
        </div>
        <div class="mb-3">
            <label class="form-label small">Password</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button class="btn btn-primary w-100 py-2">
            Masuk <i class="bi bi-arrow-right-short"></i>
        </button>
        
    </form>
</div>
</body>
</html>
