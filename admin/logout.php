<?php
/**
 * Admin Logout Handler
 * Salon Management System
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/flash.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
session_start();

set_flash('success', 'You have been successfully logged out.');
header("Location: login.php");
exit();
?>
