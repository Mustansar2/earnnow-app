<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('EARNNOW_APP')) {
    define('EARNNOW_APP', true);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

function getAdminUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && ($user['role'] ?? '') === 'admin') {
        return $user;
    }
    return null;
}

function requireAdmin() {
    $admin = getAdminUser();
    if (!$admin) {
        header('Location: login.php');
        exit;
    }
    return $admin;
}
