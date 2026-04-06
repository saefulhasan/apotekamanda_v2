<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login() {
    if (!isset($_SESSION['user'])) {
        header("Location: login.php");
        exit;
    }
}

function current_user() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_role($roles = []) {
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $roles)) {
        // Arahkan ke dashboard jika tidak punya akses
        header("Location: index.php?msg=noaccess");
        exit;
    }
}

function is_role($role) {
    $user = current_user();
    return $user && $user['role'] === $role;
}

function can_manage_users() {
    $user = current_user();
    return $user && $user['role'] === 'admin';
}
?>


