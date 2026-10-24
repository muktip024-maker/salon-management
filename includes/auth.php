<?php
/**
 * Admin Authentication Protection File
 * Salon Management System
 * Uses Procedural MySQLi ONLY
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/flash.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    set_flash('danger', 'Please login to access the admin dashboard.');
    header("Location: login.php");
    exit();
}

$admin_id = (int)$_SESSION['admin_id'];
$sql = "SELECT admin_id, name, email, status, created_at FROM admins WHERE admin_id = $admin_id AND status = 'active' LIMIT 1";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $current_admin = mysqli_fetch_assoc($result);
} else {
    // Admin session invalid or account deactivated
    unset($_SESSION['admin_id']);
    session_destroy();
    session_start();
    set_flash('danger', 'Session expired or your admin account is inactive.');
    header("Location: login.php");
    exit();
}
?>
