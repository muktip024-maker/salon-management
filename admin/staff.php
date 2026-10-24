<?php
/**
 * Staff Management Module
 * Salon Management System
 * Executive Dribbble SaaS Redesign (Matches appointments.php & customers.php)
 * Uses Procedural MySQLi ONLY
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

$page_title = "Staff Members";
$current_page = "staff";

// Helper function for initials
if (!function_exists('get_initials')) {
    function get_initials($name) {
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $w) {
            if (!empty($w)) $initials .= strtoupper($w[0]);
        }
        return substr($initials, 0, 2) ?: 'S';
    }
}

// Handle Actions (ADD, EDIT, DELETE, TOGGLE STATUS)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Invalid CSRF token. Operation cancelled.');
        header("Location: staff.php");
        exit();
    }

    // 1. ADD STAFF
    if ($action === 'add') {
        $name = mysqli_real_escape_string($conn, trim($_POST['name']));
        $email = mysqli_real_escape_string($conn, trim($_POST['email']));
        $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
        $specialization = mysqli_real_escape_string($conn, trim($_POST['specialization']));
        $status = mysqli_real_escape_string($conn, $_POST['status']);

        if (empty($name) || empty($email) || empty($phone)) {
            set_flash('danger', 'Name, Email, and Phone are required fields.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Invalid email address.');
        } else {
            $check_sql = "SELECT staff_id FROM staff WHERE email = '$email' OR phone = '$phone' LIMIT 1";
            $check_res = mysqli_query($conn, $check_sql);

            if ($check_res && mysqli_num_rows($check_res) > 0) {
                set_flash('danger', 'Staff member with this email or phone already exists.');
            } else {
                $insert_sql = "INSERT INTO staff (name, email, phone, specialization, status) 
                               VALUES ('$name', '$email', '$phone', '$specialization', '$status')";
                if (mysqli_query($conn, $insert_sql)) {
                    set_flash('success', 'Staff member added successfully.');
                } else {
                    set_flash('danger', 'Failed to add staff member.');
                }
            }
        }
        header("Location: staff.php");
        exit();
    }

    // 2. EDIT STAFF
    if ($action === 'edit') {
        $staff_id = (int)$_POST['staff_id'];
        $name = mysqli_real_escape_string($conn, trim($_POST['name']));
        $email = mysqli_real_escape_string($conn, trim($_POST['email']));
        $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
        $specialization = mysqli_real_escape_string($conn, trim($_POST['specialization']));
        $status = mysqli_real_escape_string($conn, $_POST['status']);

        if ($staff_id <= 0 || empty($name) || empty($email) || empty($phone)) {
            set_flash('danger', 'Invalid staff data provided.');
        } else {
            $check_sql = "SELECT staff_id FROM staff WHERE (email = '$email' OR phone = '$phone') AND staff_id != $staff_id LIMIT 1";
            $check_res = mysqli_query($conn, $check_sql);

            if ($check_res && mysqli_num_rows($check_res) > 0) {
                set_flash('danger', 'Email or phone number is already assigned to another staff member.');
            } else {
                $update_sql = "UPDATE staff SET name = '$name', email = '$email', phone = '$phone', specialization = '$specialization', status = '$status' WHERE staff_id = $staff_id";
                if (mysqli_query($conn, $update_sql)) {
                    set_flash('success', 'Staff details updated successfully.');
                } else {
                    set_flash('danger', 'Failed to update staff details.');
                }
            }
        }
        header("Location: staff.php");
        exit();
    }

    // 3. DELETE STAFF
    if ($action === 'delete') {
        $staff_id = (int)$_POST['staff_id'];
        if ($staff_id > 0) {
            $fk_check = "SELECT appointment_staff_id FROM appointment_staff WHERE staff_id = $staff_id LIMIT 1";
            $fk_res = mysqli_query($conn, $fk_check);

            if ($fk_res && mysqli_num_rows($fk_res) > 0) {
                set_flash('warning', 'Cannot delete this staff member because they are assigned to existing appointments. Please deactivate their profile instead.');
            } else {
                $delete_sql = "DELETE FROM staff WHERE staff_id = $staff_id";
                if (mysqli_query($conn, $delete_sql)) {
                    set_flash('success', 'Staff member deleted successfully.');
                } else {
                    set_flash('danger', 'Failed to delete staff member.');
                }
            }
        }
        header("Location: staff.php");
        exit();
    }

    // 4. TOGGLE STATUS
    if ($action === 'toggle_status') {
        $staff_id = (int)$_POST['staff_id'];
        $current_st = mysqli_real_escape_string($conn, $_POST['current_status']);
        $new_st = ($current_st === 'active') ? 'inactive' : 'active';

        $toggle_sql = "UPDATE staff SET status = '$new_st' WHERE staff_id = $staff_id";
        if (mysqli_query($conn, $toggle_sql)) {
            set_flash('success', "Staff status updated to " . ucfirst($new_st) . ".");
        } else {
            set_flash('danger', 'Failed to update status.');
        }
        header("Location: staff.php");
        exit();
    }
}

// Executive Statistics Queries (Procedural MySQLi)
$stat_tot_stf = mysqli_query($conn, "SELECT COUNT(*) AS total FROM staff");
$total_staff_count = ($stat_tot_stf && $r = mysqli_fetch_assoc($stat_tot_stf)) ? (int)$r['total'] : 0;

$stat_act_stf = mysqli_query($conn, "SELECT COUNT(*) AS total FROM staff WHERE status = 'active'");
$active_staff_count = ($stat_act_stf && $r = mysqli_fetch_assoc($stat_act_stf)) ? (int)$r['total'] : 0;

$stat_inact_stf = mysqli_query($conn, "SELECT COUNT(*) AS total FROM staff WHERE status = 'inactive'");
$inactive_staff_count = ($stat_inact_stf && $r = mysqli_fetch_assoc($stat_inact_stf)) ? (int)$r['total'] : 0;

$stat_spec_stf = mysqli_query($conn, "SELECT COUNT(DISTINCT specialization) AS total FROM staff WHERE specialization != '' AND status = 'active'");
$total_specializations = ($stat_spec_stf && $r = mysqli_fetch_assoc($stat_spec_stf)) ? (int)$r['total'] : 0;

// Fetch options for Add Appointment Modal on Staff page
$all_customers = [];
$c_res = mysqli_query($conn, "SELECT customer_id, name, phone FROM customers WHERE status = 'active' ORDER BY name ASC");
if ($c_res) { while ($r = mysqli_fetch_assoc($c_res)) { $all_customers[] = $r; } }

$all_services = [];
$s_res = mysqli_query($conn, "SELECT service_id, service_name, price FROM services WHERE status = 'active' ORDER BY service_name ASC");
if ($s_res) { while ($r = mysqli_fetch_assoc($s_res)) { $all_services[] = $r; } }

$all_staff = [];
$st_res = mysqli_query($conn, "SELECT staff_id, name, specialization FROM staff WHERE status = 'active' ORDER BY name ASC");
if ($st_res) { while ($r = mysqli_fetch_assoc($st_res)) { $all_staff[] = $r; } }

// Search & Pagination Setup
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clause = "";
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_clause = "WHERE name LIKE '%$search_escaped%' OR email LIKE '%$search_escaped%' OR phone LIKE '%$search_escaped%' OR specialization LIKE '%$search_escaped%'";
}

$count_sql = "SELECT COUNT(*) AS total FROM staff $where_clause";
$count_res = mysqli_query($conn, $count_sql);
$total_records = 0;
if ($count_res && $row = mysqli_fetch_assoc($count_res)) {
    $total_records = (int)$row['total'];
}
$total_pages = ceil($total_records / $limit);

$sql = "SELECT staff_id, name, email, phone, specialization, status, created_at FROM staff $where_clause ORDER BY staff_id DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- Executive Mini Stat Overview Row (Matches appointments.php & customers.php) -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-purple">
                <i class="fas fa-user-nurse"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($total_staff_count); ?></div>
                <div class="mini-stat-label">Total Staff Members</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($active_staff_count); ?></div>
                <div class="mini-stat-label">Active Specialists</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-amber">
                <i class="fas fa-user-slash"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($inactive_staff_count); ?></div>
                <div class="mini-stat-label">Inactive Staff</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-gold">
                <i class="fas fa-scissors"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($total_specializations); ?></div>
                <div class="mini-stat-label">Active Specializations</div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Staff Table Container -->
<div class="table-card">
    <div class="table-card-header d-flex flex-wrap align-items-center justify-content-between gap-3 p-3">
        <!-- Left Side: Table Title & Add Staff CTA Button -->
        <div class="d-flex align-items-center gap-3">
            <h5 class="table-card-title mb-0">
                <i class="fas fa-user-nurse text-primary me-2"></i>Salon Staff Directory
                <span class="badge bg-light text-dark border rounded-pill fs-7 ms-2"><?php echo $total_records; ?></span>
            </h5>
            <button type="button" class="btn btn-accent px-4 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addStaffModal" style="border-radius:30px; font-size: 0.875rem;">
                <i class="fas fa-user-plus me-2"></i> Add Staff
            </button>
        </div>

        <!-- Right Side: Integrated Search Form -->
        <form action="staff.php" method="GET" class="d-flex flex-wrap align-items-center gap-2 m-0 ms-auto">
            <div class="input-group" style="width: 240px;">
                <span class="input-group-text bg-light border-end-0" style="border-radius:30px 0 0 30px; padding: 0.4rem 0.75rem;"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search name, specialization..." value="<?php echo htmlspecialchars($search); ?>" style="border-radius:0 30px 30px 0; font-size:0.85rem; padding: 0.4rem 0.75rem;">
            </div>
            <button type="submit" class="btn btn-dark font-weight-700 px-3 py-1-5" style="border-radius:30px; font-size: 0.85rem;">Search</button>
            <?php if (!empty($search)): ?>
                <a href="staff.php" class="btn btn-light border px-3 py-1-5" style="border-radius:30px; font-size: 0.85rem;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive-custom">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>NAME</th>
                    <th>EMAIL</th>
                    <th>PHONE</th>
                    <th>SPECIALIZATION</th>
                    <th>STATUS</th>
                    <th>CREATED DATE</th>
                    <th class="text-end">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($stf = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><strong class="text-muted">#<?php echo $stf['staff_id']; ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-circle-sm bg-gold-subtle text-gold fw-bold">
                                        <?php echo get_initials($stf['name']); ?>
                                    </div>
                                    <strong class="text-dark fs-6"><?php echo htmlspecialchars($stf['name']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <span class="text-dark"><i class="far fa-envelope me-1 text-muted"></i><?php echo htmlspecialchars($stf['email']); ?></span>
                            </td>
                            <td>
                                <span class="text-dark"><i class="fas fa-phone-alt me-1 text-muted fs-8"></i><?php echo htmlspecialchars($stf['phone']); ?></span>
                            </td>
                            <td>
                                <span class="chip-selected-item chip-staff-selected fs-8">
                                    <i class="fas fa-cut me-1"></i><?php echo htmlspecialchars($stf['specialization'] ?: 'General Specialist'); ?>
                                </span>
                            </td>
                            <td>
                                <form action="staff.php" method="POST" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="staff_id" value="<?php echo $stf['staff_id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $stf['status']; ?>">
                                    <button type="submit" class="border-0 bg-transparent p-0" title="Click to toggle status">
                                        <?php 
                                        $st = strtolower($stf['status']);
                                        $dot_cls = ($st === 'active') ? 'dot-green' : 'dot-red';
                                        ?>
                                        <span class="status-pill-btn <?php echo $st; ?>">
                                            <span class="status-dot <?php echo $dot_cls; ?>"></span>
                                            <span class="fw-bold fs-7"><?php echo ucfirst($stf['status']); ?></span>
                                        </span>
                                    </button>
                                </form>
                            </td>
                            <td><small class="text-muted"><i class="far fa-calendar-alt me-1"></i><?php echo date('M d, Y', strtotime($stf['created_at'])); ?></small></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn-action btn-action-edit" data-bs-toggle="modal" data-bs-target="#editStaffModal<?php echo $stf['staff_id']; ?>" title="Edit Staff Details">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="staff.php" method="POST" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="staff_id" value="<?php echo $stf['staff_id']; ?>">
                                        <button type="submit" class="btn-action btn-action-delete confirm-delete" data-confirm="Are you sure you want to delete staff member <?php echo htmlspecialchars($stf['name']); ?>?" title="Delete Staff Member">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>

                                <!-- Edit Staff Modal -->
                                <div class="modal fade" id="editStaffModal<?php echo $stf['staff_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content modal-content-luxury">
                                            <div class="modal-header modal-header-luxury">
                                                <h5 class="modal-title"><i class="fas fa-user-edit text-warning me-2"></i> Edit Staff #<?php echo $stf['staff_id']; ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="staff.php" method="POST" novalidate>
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="staff_id" value="<?php echo $stf['staff_id']; ?>">
                                                <div class="modal-body p-4 text-start">
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Full Name *</label>
                                                        <input type="text" name="name" class="form-control form-control-custom" value="<?php echo htmlspecialchars($stf['name']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Email Address *</label>
                                                        <input type="email" name="email" class="form-control form-control-custom" value="<?php echo htmlspecialchars($stf['email']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Phone Number *</label>
                                                        <input type="text" name="phone" class="form-control form-control-custom" value="<?php echo htmlspecialchars($stf['phone']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Specialization / Role</label>
                                                        <input type="text" name="specialization" class="form-control form-control-custom" value="<?php echo htmlspecialchars($stf['specialization']); ?>" placeholder="e.g. Hair Stylist, Facial Expert">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Status</label>
                                                        <select name="status" class="form-select form-select-custom">
                                                            <option value="active" <?php echo ($stf['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                                            <option value="inactive" <?php echo ($stf['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer-luxury">
                                                    <button type="button" class="btn-cancel-ghost" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn-submit-gold">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No staff members found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <small class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> staff members</small>
            <nav>
                <ul class="pagination pagination-custom pagination-sm m-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="staff.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                    </li>
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?php echo ($p === $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="staff.php?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="staff.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-luxury">
            <div class="modal-header modal-header-luxury">
                <h5 class="modal-title"><i class="fas fa-user-plus text-warning me-2"></i> Add New Staff Member</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="staff.php" method="POST" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4 text-start">
                    <div class="mb-3">
                        <label class="form-label-custom">Full Name *</label>
                        <input type="text" name="name" class="form-control form-control-custom" placeholder="e.g. Rahul Mehta" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Email Address *</label>
                        <input type="email" name="email" class="form-control form-control-custom" placeholder="rahul@salon.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Phone Number *</label>
                        <input type="text" name="phone" class="form-control form-control-custom" placeholder="9876543210" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Specialization / Role</label>
                        <input type="text" name="specialization" class="form-control form-control-custom" placeholder="e.g. Hair Styling, Hair Color">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Status</label>
                        <select name="status" class="form-select form-select-custom">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer-luxury">
                    <button type="button" class="btn-cancel-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-submit-gold">Save Staff Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ADD APPOINTMENT LUXURY MODAL -->
<div class="modal fade" id="addAppointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-luxury">
            <div class="modal-header modal-header-luxury">
                <h5 class="modal-title"><i class="fas fa-calendar-plus text-warning me-2"></i> Book New Appointment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="appointments.php" method="POST" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4 text-start">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">Customer *</label>
                            <select name="customer_id" class="form-select form-select-custom" required>
                                <option value="">Select Customer</option>
                                <?php foreach ($all_customers as $c): ?>
                                    <option value="<?php echo $c['customer_id']; ?>"><?php echo htmlspecialchars($c['name'] . ' (' . $c['phone'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">Initial Status</label>
                            <select name="status" class="form-select form-select-custom">
                                <option value="Pending">Pending</option>
                                <option value="Confirmed">Confirmed</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">Appointment Date *</label>
                            <input type="date" name="appointment_date" class="form-control form-control-custom" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">Appointment Time *</label>
                            <input type="time" name="appointment_time" class="form-control form-control-custom" value="10:00" required>
                        </div>
                    </div>

                    <!-- Services Checkbox Section with Dynamic Total -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label-custom mb-0"><i class="fas fa-scissors me-1 text-primary"></i> Select Services (Multiple) *</label>
                            <span class="fs-6 font-weight-800 text-primary appointment-total-display">₹0.00</span>
                        </div>
                        <div class="selected-chips-container selected-services-chips mb-2">
                            <span class="text-muted small">No services selected</span>
                        </div>
                        <div class="select-list-box">
                            <?php foreach ($all_services as $s): ?>
                                <label class="checkbox-item-card" for="staff_page_add_srv_<?php echo $s['service_id']; ?>">
                                    <span class="d-flex align-items-center gap-2">
                                        <input class="service-checkbox" type="checkbox" name="services[]" value="<?php echo $s['service_id']; ?>" data-price="<?php echo $s['price']; ?>" id="staff_page_add_srv_<?php echo $s['service_id']; ?>">
                                        <span class="fw-semibold text-dark"><?php echo htmlspecialchars($s['service_name']); ?></span>
                                    </span>
                                    <strong class="text-primary fs-7">₹<?php echo number_format($s['price'], 2); ?></strong>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Staff Selection -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label-custom mb-0"><i class="fas fa-user-nurse me-1 text-primary"></i> Assign Staff Members</label>
                            <small class="text-muted selected-staff-count">0 Selected</small>
                        </div>
                        <div class="selected-chips-container selected-staff-chips mb-2">
                            <span class="text-muted small">No staff assigned</span>
                        </div>
                        <div class="select-list-box">
                            <?php foreach ($all_staff as $st): ?>
                                <label class="checkbox-item-card" for="staff_page_add_stf_<?php echo $st['staff_id']; ?>">
                                    <span class="d-flex align-items-center gap-2">
                                        <input type="checkbox" name="staff[]" value="<?php echo $st['staff_id']; ?>" id="staff_page_add_stf_<?php echo $st['staff_id']; ?>">
                                        <span class="fw-semibold text-dark"><?php echo htmlspecialchars($st['name']); ?></span>
                                    </span>
                                    <small class="text-muted"><?php echo htmlspecialchars($st['specialization'] ?: 'General'); ?></small>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-custom">Notes / Instructions</label>
                        <textarea name="notes" class="form-control form-control-custom" rows="2" placeholder="Special customer requests..."></textarea>
                    </div>
                </div>
                <div class="modal-footer-luxury">
                    <button type="button" class="btn-cancel-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-submit-gold"><i class="fas fa-check-circle me-1"></i> Confirm Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
