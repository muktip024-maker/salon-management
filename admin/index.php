<?php
/**
 * Salon Management System - Executive Dashboard
 * Designed after modern high-end Dribbble SaaS dashboards
 * Uses Procedural MySQLi ONLY
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

$page_title = "Dashboard";
$current_page = "dashboard";

// Helper function for initials
function get_initials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) $initials .= strtoupper($w[0]);
    }
    return substr($initials, 0, 2) ?: 'C';
}

// 1. Fetch Key Statistics using procedural mysqli_query
$stats = [
    'customers' => 0,
    'staff' => 0,
    'categories' => 0,
    'services' => 0,
    'appointments' => 0,
    'pending_appointments' => 0,
    'confirmed_appointments' => 0,
    'completed_appointments' => 0,
    'cancelled_appointments' => 0,
    'total_revenue' => 0.00
];

// Run queries
$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM customers");
if ($res && $row = mysqli_fetch_assoc($res)) { $stats['customers'] = (int)$row['total']; }

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM staff WHERE status = 'active'");
if ($res && $row = mysqli_fetch_assoc($res)) { $stats['staff'] = (int)$row['total']; }

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM service_categories WHERE status = 'active'");
if ($res && $row = mysqli_fetch_assoc($res)) { $stats['categories'] = (int)$row['total']; }

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM services WHERE status = 'active'");
if ($res && $row = mysqli_fetch_assoc($res)) { $stats['services'] = (int)$row['total']; }

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments");
if ($res && $row = mysqli_fetch_assoc($res)) { $stats['appointments'] = (int)$row['total']; }

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status = 'Pending'");
if ($res && $row = mysqli_fetch_assoc($res)) { $stats['pending_appointments'] = (int)$row['total']; }

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status = 'Confirmed'");
if ($res && $row = mysqli_fetch_assoc($res)) { $stats['confirmed_appointments'] = (int)$row['total']; }

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status = 'Completed'");
if ($res && $row = mysqli_fetch_assoc($res)) { $stats['completed_appointments'] = (int)$row['total']; }

$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status = 'Cancelled'");
if ($res && $row = mysqli_fetch_assoc($res)) { $stats['cancelled_appointments'] = (int)$row['total']; }

$res = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE payment_status = 'Paid'");
if ($res && $row = mysqli_fetch_assoc($res)) { $stats['total_revenue'] = (float)$row['total']; }

// 2. Fetch Today's / Recent Key Appointments List
$recent_appts_sql = "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, c.name AS customer_name,
                            GROUP_CONCAT(DISTINCT s.service_name SEPARATOR ', ') AS service_names
                     FROM appointments a 
                     JOIN customers c ON a.customer_id = c.customer_id 
                     LEFT JOIN appointment_services aps ON a.appointment_id = aps.appointment_id
                     LEFT JOIN services s ON aps.service_id = s.service_id
                     GROUP BY a.appointment_id
                     ORDER BY a.appointment_id DESC LIMIT 4";
$recent_appts_res = mysqli_query($conn, $recent_appts_sql);

// 3. Fetch Schedule Timeline for Right Side Panel
$timeline_sql = "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, c.name AS customer_name,
                        GROUP_CONCAT(DISTINCT s.service_name SEPARATOR ', ') AS service_names,
                        GROUP_CONCAT(DISTINCT stf.name SEPARATOR ', ') AS staff_names
                 FROM appointments a 
                 JOIN customers c ON a.customer_id = c.customer_id 
                 LEFT JOIN appointment_services aps ON a.appointment_id = aps.appointment_id
                 LEFT JOIN services s ON aps.service_id = s.service_id
                 LEFT JOIN appointment_staff apst ON a.appointment_id = apst.appointment_id
                 LEFT JOIN staff stf ON apst.staff_id = stf.staff_id
                 GROUP BY a.appointment_id
                 ORDER BY a.appointment_date DESC, a.appointment_time ASC LIMIT 5";
$timeline_res = mysqli_query($conn, $timeline_sql);

// 4. Fetch Recent Payments
$payments_sql = "SELECT p.payment_id, p.appointment_id, p.amount, p.payment_method, p.payment_status, c.name AS customer_name 
                 FROM payments p 
                 JOIN appointments a ON p.appointment_id = a.appointment_id 
                 JOIN customers c ON a.customer_id = c.customer_id 
                 ORDER BY p.payment_id DESC LIMIT 4";
$payments_res = mysqli_query($conn, $payments_sql);

// 5. Fetch Recent Reviews
$reviews_sql = "SELECT r.review_id, r.rating, r.comment, r.status, c.name AS customer_name 
                FROM reviews r 
                JOIN customers c ON r.customer_id = c.customer_id 
                ORDER BY r.review_id DESC LIMIT 3";
$reviews_res = mysqli_query($conn, $reviews_sql);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- Hero Highlight Cards Row (Vibrant Cards as featured in Dribbble reference) -->
<div class="row g-3 mb-4">
    <!-- Card 1: Revenue Highlight (Purple) -->
    <div class="col-lg-4 col-md-6">
        <div class="hero-card hero-card-purple">
            <div class="hero-card-header">
                <span class="hero-card-title"><i class="fas fa-coins me-1"></i> TOTAL REVENUE</span>
                <button class="hero-card-menu" title="Options"><i class="fas fa-ellipsis-h"></i></button>
            </div>
            <div class="hero-card-val">₹<?php echo number_format($stats['total_revenue'], 2); ?></div>
            <div>
                <div class="hero-progress-bar">
                    <div class="hero-progress-fill" style="width: 85%;"></div>
                </div>
                <div class="hero-card-footer">
                    <span><?php echo $stats['completed_appointments']; ?> Completed Bookings</span>
                    <span>85% Target</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Appointments Highlight (Teal) -->
    <div class="col-lg-4 col-md-6">
        <div class="hero-card hero-card-teal">
            <div class="hero-card-header">
                <span class="hero-card-title"><i class="fas fa-calendar-check me-1"></i> APPOINTMENTS</span>
                <button class="hero-card-menu" title="Options"><i class="fas fa-ellipsis-h"></i></button>
            </div>
            <div class="hero-card-val"><?php echo number_format($stats['appointments']); ?> Bookings</div>
            <div>
                <div class="hero-progress-bar">
                    <div class="hero-progress-fill" style="width: 68%;"></div>
                </div>
                <div class="hero-card-footer">
                    <span><?php echo $stats['confirmed_appointments']; ?> Confirmed, <?php echo $stats['pending_appointments']; ?> Pending</span>
                    <span>68% Active</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Customers Highlight (Coral/Terracotta) -->
    <div class="col-lg-4 col-md-12">
        <div class="hero-card hero-card-coral">
            <div class="hero-card-header">
                <span class="hero-card-title"><i class="fas fa-users me-1"></i> REGISTERED CLIENTS</span>
                <button class="hero-card-menu" title="Options"><i class="fas fa-ellipsis-h"></i></button>
            </div>
            <div class="hero-card-val"><?php echo number_format($stats['customers']); ?> Customers</div>
            <div>
                <div class="hero-progress-bar">
                    <div class="hero-progress-fill" style="width: 92%;"></div>
                </div>
                <div class="hero-card-footer">
                    <span><?php echo $stats['staff']; ?> Active Salon Specialists</span>
                    <span>92% Retention</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Content Grid: Left Main Section (8 cols) & Right Schedule Sidebar (4 cols) -->
<div class="row g-4">
    <!-- Left Main Section -->
    <div class="col-lg-8">
        
        <!-- Today's Key Appointments List -->
        <div class="premium-panel mb-4">
            <div class="panel-header">
                <h5 class="panel-title"><i class="fas fa-calendar-day text-primary me-2"></i>Appointments Today</h5>
                <a href="appointments.php" class="btn btn-sm btn-light font-weight-600 border rounded-pill px-3">View All</a>
            </div>

            <?php if ($recent_appts_res && mysqli_num_rows($recent_appts_res) > 0): ?>
                <?php while ($app = mysqli_fetch_assoc($recent_appts_res)): ?>
                    <div class="appt-item-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-circle-sm bg-gold-subtle text-gold fw-bold">
                                <?php echo get_initials($app['customer_name']); ?>
                            </div>
                            <div class="appt-item-info">
                                <h6 class="mb-1 text-dark fw-bold"><?php echo htmlspecialchars($app['customer_name']); ?></h6>
                                <p class="mb-0 text-muted fs-7"><i class="fas fa-scissors me-1 text-primary"></i> <?php echo htmlspecialchars($app['service_names'] ?: 'General Service'); ?></p>
                            </div>
                        </div>
                        <div class="appt-item-time text-end">
                            <span class="time-badge"><i class="far fa-clock me-1 text-muted"></i><?php echo date('h:i A', strtotime($app['appointment_time'])); ?></span>
                            <small class="text-muted d-block mt-1"><?php echo date('M d, Y', strtotime($app['appointment_date'])); ?></small>
                        </div>
                        <div>
                            <?php 
                            $st = strtolower($app['status']);
                            $dot_cls = 'dot-yellow';
                            if ($st === 'confirmed') $dot_cls = 'dot-green';
                            if ($st === 'completed') $dot_cls = 'dot-teal';
                            if ($st === 'cancelled') $dot_cls = 'dot-red';
                            ?>
                            <span class="status-pill-btn <?php echo $st; ?> cursor-default">
                                <span class="status-dot <?php echo $dot_cls; ?>"></span>
                                <span class="fw-bold fs-7"><?php echo htmlspecialchars($app['status']); ?></span>
                            </span>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-center text-muted py-4">No appointments scheduled for today.</p>
            <?php endif; ?>
        </div>

        <!-- Quick Statistics Tile Blocks -->
        <div class="premium-panel mb-4">
            <div class="panel-header">
                <h5 class="panel-title"><i class="fas fa-chart-pie text-primary me-2"></i>Statistics Overview</h5>
            </div>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="mini-stat-tile text-center">
                        <div class="mini-stat-val text-primary"><?php echo $stats['staff']; ?></div>
                        <div class="mini-stat-label">Active Staff</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="mini-stat-tile text-center">
                        <div class="mini-stat-val text-success"><?php echo $stats['services']; ?></div>
                        <div class="mini-stat-label">Services</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="mini-stat-tile text-center">
                        <div class="mini-stat-val text-warning"><?php echo $stats['pending_appointments']; ?></div>
                        <div class="mini-stat-label">Pending</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="mini-stat-tile text-center">
                        <div class="mini-stat-val text-info"><?php echo $stats['categories']; ?></div>
                        <div class="mini-stat-label">Categories</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Payments Table -->
        <div class="premium-panel">
            <div class="panel-header">
                <h5 class="panel-title"><i class="fas fa-receipt text-primary me-2"></i>Recent Transactions</h5>
                <a href="payments.php" class="btn btn-sm btn-light border font-weight-600 rounded-pill px-3">View Transactions</a>
            </div>
            <div class="table-responsive-custom">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>CUSTOMER</th>
                            <th>AMOUNT</th>
                            <th>METHOD</th>
                            <th>STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($payments_res && mysqli_num_rows($payments_res) > 0): ?>
                            <?php while ($pay = mysqli_fetch_assoc($payments_res)): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-circle-sm bg-light text-dark fw-bold">
                                                <?php echo get_initials($pay['customer_name']); ?>
                                            </div>
                                            <div>
                                                <strong class="text-dark d-block"><?php echo htmlspecialchars($pay['customer_name']); ?></strong>
                                                <small class="text-muted">Appt #<?php echo $pay['appointment_id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><strong class="text-dark fs-6">₹<?php echo number_format($pay['amount'], 2); ?></strong></td>
                                    <td><span class="chip-selected-item cursor-default"><?php echo htmlspecialchars($pay['payment_method']); ?></span></td>
                                    <td>
                                        <?php 
                                        $pst = strtolower($pay['payment_status']);
                                        $dot_cls = ($pst === 'paid') ? 'dot-green' : (($pst === 'pending') ? 'dot-yellow' : 'dot-red');
                                        ?>
                                        <span class="status-pill-btn <?php echo $pst; ?> cursor-default">
                                            <span class="status-dot <?php echo $dot_cls; ?>"></span>
                                            <span class="fw-bold fs-7"><?php echo htmlspecialchars($pay['payment_status']); ?></span>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">No payments recorded.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Right Side Panel: Salon Schedule Timeline (As featured in reference design) -->
    <div class="col-lg-4">
        
        <!-- Timeline Widget -->
        <div class="timeline-panel mb-4">
            <div class="panel-header">
                <h5 class="panel-title"><i class="far fa-calendar-alt text-primary me-2"></i> Salon Schedule</h5>
                <span class="badge bg-light text-dark border rounded-pill px-3 py-1"><?php echo date('M d'); ?></span>
            </div>

            <?php if ($timeline_res && mysqli_num_rows($timeline_res) > 0): ?>
                <?php 
                $line_classes = ['purple-line', 'teal-line', 'coral-line'];
                $idx = 0;
                while ($tl = mysqli_fetch_assoc($timeline_res)):
                    $line_cls = $line_classes[$idx % 3];
                    $idx++;
                ?>
                    <div class="timeline-item">
                        <div class="timeline-time">
                            <?php echo date('h:i A', strtotime($tl['appointment_time'])); ?>
                        </div>
                        <div class="timeline-content <?php echo $line_cls; ?>">
                            <div class="timeline-title"><?php echo htmlspecialchars($tl['customer_name']); ?></div>
                            <div class="timeline-subtitle mt-1">
                                <span class="d-block text-dark fw-semibold mb-1"><?php echo htmlspecialchars($tl['service_names'] ?: 'Salon Service'); ?></span>
                                <span class="chip-selected-item chip-staff-selected fs-8">
                                    <i class="fas fa-user-circle me-1"></i>Staff: <?php echo htmlspecialchars($tl['staff_names'] ?: 'Assigned Staff'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-center text-muted py-4">No upcoming bookings on schedule.</p>
            <?php endif; ?>
        </div>

        <!-- Recent Reviews Widget -->
        <div class="timeline-panel">
            <div class="panel-header">
                <h5 class="panel-title"><i class="fas fa-star text-warning me-2"></i> Client Feedback</h5>
                <a href="reviews.php" class="btn btn-sm btn-link text-decoration-none p-0 fw-bold">All</a>
            </div>

            <?php if ($reviews_res && mysqli_num_rows($reviews_res) > 0): ?>
                <?php while ($rev = mysqli_fetch_assoc($reviews_res)): ?>
                    <div class="p-3 bg-white rounded-3 mb-3 border shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle-sm bg-gold-subtle text-gold fw-bold">
                                    <?php echo get_initials($rev['customer_name']); ?>
                                </div>
                                <strong class="text-dark font-weight-600 fs-7"><?php echo htmlspecialchars($rev['customer_name']); ?></strong>
                            </div>
                            <div class="star-rating text-warning fs-7">
                                <?php 
                                $r = (int)$rev['rating'];
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $r) {
                                        echo '<i class="fas fa-star"></i>';
                                    } else {
                                        echo '<i class="far fa-star text-muted"></i>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <p class="text-muted mb-0 font-italic small fs-7">"<?php echo htmlspecialchars(substr($rev['comment'], 0, 80)) . (strlen($rev['comment']) > 80 ? '...' : ''); ?>"</p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-center text-muted py-3">No reviews submitted yet.</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
