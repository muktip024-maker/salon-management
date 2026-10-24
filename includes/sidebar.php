<?php
/**
 * Admin Layout - Navigation Sidebar
 * Salon Management System
 */

if (!isset($current_page)) {
    $current_page = '';
}

// Fetch admin details
$admin_display_name = isset($current_admin['name']) ? $current_admin['name'] : 'Administrator';
$admin_display_email = isset($current_admin['email']) ? $current_admin['email'] : 'admin@salon.com';
$initials = strtoupper(substr($admin_display_name, 0, 1));
?>
<!-- Sidebar -->
<aside id="sidebar">
    <!-- Brand Header -->
    <div class="sidebar-brand-box">
        <div class="sidebar-logo-icon">
            <i class="fas fa-scissors"></i>
        </div>
        <span class="sidebar-brand-name">Salon<span style="color:var(--accent-gold);">Pro</span></span>
    </div>

    <!-- Admin Profile Card (As shown in reference Dribbble image) -->
    <div class="sidebar-profile-card">
        <div class="profile-avatar-circle">
            <?php echo htmlspecialchars($initials); ?>
            <span class="profile-avatar-status" title="Active"></span>
        </div>
        <div class="profile-name"><?php echo htmlspecialchars($admin_display_name); ?></div>
        <div class="profile-email"><?php echo htmlspecialchars($admin_display_email); ?></div>
    </div>

    <!-- Menu Links -->
    <ul class="sidebar-menu">
        <li class="nav-item">
            <a href="index.php" class="nav-link-custom <?php echo ($current_page === 'dashboard') ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="appointments.php" class="nav-link-custom <?php echo ($current_page === 'appointments') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span>Appointments</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="customers.php" class="nav-link-custom <?php echo ($current_page === 'customers') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="staff.php" class="nav-link-custom <?php echo ($current_page === 'staff') ? 'active' : ''; ?>">
                <i class="fas fa-user-nurse"></i>
                <span>Staff Members</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="categories.php" class="nav-link-custom <?php echo ($current_page === 'categories') ? 'active' : ''; ?>">
                <i class="fas fa-layer-group"></i>
                <span>Categories</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="services.php" class="nav-link-custom <?php echo ($current_page === 'services') ? 'active' : ''; ?>">
                <i class="fas fa-cut"></i>
                <span>Services</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="reviews.php" class="nav-link-custom <?php echo ($current_page === 'reviews') ? 'active' : ''; ?>">
                <i class="fas fa-star"></i>
                <span>Reviews</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="payments.php" class="nav-link-custom <?php echo ($current_page === 'payments') ? 'active' : ''; ?>">
                <i class="fas fa-wallet"></i>
                <span>Payments</span>
            </a>
        </li>

        <li class="nav-item mt-3">
            <a href="profile.php" class="nav-link-custom <?php echo ($current_page === 'profile') ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="logout.php" class="nav-link-custom confirm-delete text-danger" data-confirm="Are you sure you want to log out?">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>
