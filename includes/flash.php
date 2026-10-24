<?php
/**
 * Session-based Flash Messages System
 * Salon Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function display_flash() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $message = $_SESSION['flash']['message'];
        unset($_SESSION['flash']);

        $icon = 'info-circle';
        if ($type === 'success') $icon = 'check-circle';
        if ($type === 'danger') $icon = 'exclamation-circle';
        if ($type === 'warning') $icon = 'exclamation-triangle';

        echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show d-flex align-items-center mb-4" role="alert">';
        echo '  <i class="fas fa-' . $icon . ' me-2 flex-shrink-0"></i>';
        echo '  <div>' . htmlspecialchars($message) . '</div>';
        echo '  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}
?>
