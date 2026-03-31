<?php
function sessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('ong_sess');
        session_start();
    }
}

function isAdmin(): bool {
    sessionStart();
    return !empty($_SESSION[ADMIN_SESSION_KEY]);
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function adminUsername(): string {
    return $_SESSION[ADMIN_SESSION_KEY . '_user'] ?? 'admin';
}
