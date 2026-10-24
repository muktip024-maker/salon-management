<?php
/**
 * Admin Layout - Top Header Bar
 * Salon Management System
 */

$admin_name = isset($current_admin['name']) ? strtok($current_admin['name'], " ") : 'Admin';
$is_appointments_page = (basename($_SERVER['PHP_SELF']) === 'appointments.php');
$header_search_val = isset($_GET['search']) ? trim($_GET['search']) : '';
?>
<main id="main-content">
    <!-- Top Header Section -->
    <header class="top-header-bar">
        <div class="d-flex align-items-center gap-3">
            <button id="sidebar-toggle" class="btn btn-sm btn-light d-lg-none" type="button" aria-label="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 class="greeting-title">Hello, <?php echo htmlspecialchars($admin_name); ?></h1>
                <p class="greeting-subtitle">Today is <?php echo date('l, d F Y'); ?></p>
            </div>
        </div>

        <div class="header-controls">
            <!-- Global Header Search Form -->
            <form action="appointments.php" method="GET" class="header-search-box d-none d-md-flex m-0">
                <button type="submit" class="border-0 bg-transparent p-0 me-1" style="cursor:pointer;" aria-label="Submit Search">
                    <i class="fas fa-search text-muted"></i>
                </button>
                <input type="text" name="search" placeholder="Search anything..." value="<?php echo htmlspecialchars($header_search_val); ?>">
            </form>
            
            <?php if ($is_appointments_page): ?>
                <button type="button" class="btn-header-cta" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
                    <i class="fas fa-plus"></i>
                    <span>Book Appointment</span>
                </button>
            <?php else: ?>
                <a href="appointments.php?book=1" class="btn-header-cta">
                    <i class="fas fa-plus"></i>
                    <span>Book Appointment</span>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <div class="dashboard-container">
        <?php 
        // Display any flash messages if set
        if (function_exists('display_flash')) {
            display_flash();
        }
        ?>
