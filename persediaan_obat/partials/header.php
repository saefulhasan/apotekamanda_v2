<?php
require_once __DIR__ . "/../auth.php";
require_login();
$user = current_user();
$role = $user['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Apotek Amanda</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap & Icons via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app-wrapper">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="bi bi-prescription2"></i>
            </div>
            <div>
                <div class="brand-title">Apotek Amanda</div>
                <small class="brand-subtitle">Inventory &amp; Resep</small>
            </div>
        </div>

        <nav class="sidebar-menu">
            <!-- DASHBOARD (SEMUA ROLE) -->
            <a href="index.php" class="sidebar-link<?php echo basename($_SERVER['PHP_SELF'])=='index.php' ? ' active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
            </a>

            <!-- =========================
                 PERSEDIAAN (ADMIN + GUDANG)
                 ========================= -->
            <?php if (in_array($role, ['admin','gudang'], true)): ?>
                <div class="sidebar-section-label">Persediaan</div>

                <a href="stock.php" class="sidebar-link<?php echo basename($_SERVER['PHP_SELF'])=='stock.php' ? ' active' : ''; ?>">
                    <i class="bi bi-box-seam"></i> <span>Stock Barang</span>
                </a>

                <a href="barang_masuk.php" class="sidebar-link<?php echo basename($_SERVER['PHP_SELF'])=='barang_masuk.php' ? ' active' : ''; ?>">
                    <i class="bi bi-box-arrow-in-down"></i> <span>Barang Masuk</span>
                </a>

                <a href="barang_keluar.php" class="sidebar-link<?php echo basename($_SERVER['PHP_SELF'])=='barang_keluar.php' ? ' active' : ''; ?>">
                    <i class="bi bi-box-arrow-up"></i> <span>Barang Keluar</span>
                </a>
            <?php endif; ?>

            <!-- =========================
                 LAPORAN (ADMIN + APOTEKER + GUDANG + OWNER)
                 ========================= -->
            <?php if (in_array($role, ['admin','apoteker','gudang','owner'], true)): ?>
                <div class="sidebar-section-label">Laporan</div>

                <a href="laporan_masuk.php" class="sidebar-link<?php echo basename($_SERVER['PHP_SELF'])=='laporan_masuk.php' ? ' active' : ''; ?>">
                    <i class="bi bi-file-earmark-text"></i> <span>Laporan Barang Masuk</span>
                </a>

                <a href="laporan_keluar.php" class="sidebar-link<?php echo basename($_SERVER['PHP_SELF'])=='laporan_keluar.php' ? ' active' : ''; ?>">
                    <i class="bi bi-file-earmark-text"></i> <span>Laporan Barang Keluar</span>
                </a>
            <?php endif; ?>

            <!-- =========================
                 PELAYANAN (ADMIN + APOTEKER)
                 ========================= -->
            <?php if (in_array($role, ['admin','apoteker'], true)): ?>
                <div class="sidebar-section-label">Pelayanan</div>

                <a href="resep.php" class="sidebar-link<?php echo basename($_SERVER['PHP_SELF'])=='resep.php' ? ' active' : ''; ?>">
                    <i class="bi bi-clipboard2-pulse"></i> <span>Input Resep</span>
                </a>
            <?php endif; ?>

            <!-- =========================
                 MENU ADMIN (ADMIN SAJA)
                 ========================= -->
            <?php if ($role === 'admin' && can_manage_users()): ?>
                <div class="sidebar-section-label">Menu Admin</div>

                <a href="user.php" class="sidebar-link<?php echo basename($_SERVER['PHP_SELF'])=='user.php' ? ' active' : ''; ?>">
                    <i class="bi bi-person-gear"></i> <span>Data User</span>
                </a>

                <a href="apoteker.php" class="sidebar-link<?php echo basename($_SERVER['PHP_SELF'])=='apoteker.php' ? ' active' : ''; ?>">
                    <i class="bi bi-person-badge"></i> <span>Data Apoteker</span>
                </a>

                <?php if (file_exists(__DIR__ . "/../member.php")): ?>
                    <a href="member.php" class="sidebar-link<?php echo basename($_SERVER['PHP_SELF'])=='member.php' ? ' active' : ''; ?>">
                        <i class="bi bi-people"></i> <span>Data Member</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <!-- LOGOUT (SEMUA ROLE) - PAKAI MODAL -->
            <a href="#"
               class="sidebar-link"
               data-bs-toggle="modal"
               data-bs-target="#logoutModal">
                <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
            </a>
        </nav>
    </aside>

    <main class="app-main">
        <header class="topbar">
            <div>
                <div class="topbar-title">Hi, <?php echo htmlspecialchars($user['name']); ?></div>
                <small class="topbar-subtitle text-capitalize">Role: <?php echo htmlspecialchars($role); ?></small>
            </div>
            <div class="topbar-right">
                <span class="badge bg-soft-primary text-primary">
                    <i class="bi bi-calendar-week me-1"></i>
                    <?php echo date('d M Y'); ?>
                </span>
            </div>
        </header>

        <div class="app-content container-fluid py-3">

<!-- =========================
     MODAL KONFIRMASI LOGOUT
     ========================= -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Logout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Yakin mau logout?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="logout.php" class="btn btn-danger">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS (wajib untuk modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
