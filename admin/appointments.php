<?php
/**
 * Appointment Management Module
 * Salon Management System
 * Executive Premium Interface
 * Uses Procedural MySQLi ONLY with Transactions
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

$page_title = "Appointments";
$current_page = "appointments";

// Fetch Dropdown Options (Customers, Services, Staff)
$customers_res = mysqli_query($conn, "SELECT customer_id, name, email, phone FROM customers WHERE status = 'active' ORDER BY name ASC");
$all_customers = [];
if ($customers_res) {
    while ($c = mysqli_fetch_assoc($customers_res)) { $all_customers[] = $c; }
}

$services_res = mysqli_query($conn, "SELECT service_id, service_name, price, duration FROM services WHERE status = 'active' ORDER BY service_name ASC");
$all_services = [];
if ($services_res) {
    while ($s = mysqli_fetch_assoc($services_res)) { $all_services[] = $s; }
}

$staff_res = mysqli_query($conn, "SELECT staff_id, name, specialization FROM staff WHERE status = 'active' ORDER BY name ASC");
$all_staff = [];
if ($staff_res) {
    while ($st = mysqli_fetch_assoc($staff_res)) { $all_staff[] = $st; }
}

// Handle Form Submissions (ADD, EDIT, DELETE, QUICK STATUS)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Invalid CSRF token. Operation cancelled.');
        header("Location: appointments.php");
        exit();
    }

    // 1. CREATE APPOINTMENT (Transaction Handling)
    if ($action === 'add') {
        $customer_id = (int)$_POST['customer_id'];
        $appointment_date = mysqli_real_escape_string($conn, $_POST['appointment_date']);
        $appointment_time = mysqli_real_escape_string($conn, $_POST['appointment_time']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $notes = mysqli_real_escape_string($conn, trim($_POST['notes']));
        $selected_services = isset($_POST['services']) && is_array($_POST['services']) ? $_POST['services'] : [];
        $selected_staff = isset($_POST['staff']) && is_array($_POST['staff']) ? $_POST['staff'] : [];

        if ($customer_id <= 0 || empty($appointment_date) || empty($appointment_time)) {
            set_flash('danger', 'Customer, Date, and Time are required fields.');
        } elseif (count($selected_services) === 0) {
            set_flash('danger', 'Please select at least one service for the appointment.');
        } else {
            mysqli_query($conn, "START TRANSACTION");
            $success = true;

            $appt_sql = "INSERT INTO appointments (customer_id, appointment_date, appointment_time, status, notes) 
                         VALUES ($customer_id, '$appointment_date', '$appointment_time', '$status', '$notes')";
            
            if (mysqli_query($conn, $appt_sql)) {
                $appointment_id = mysqli_insert_id($conn);

                foreach ($selected_services as $srv_id) {
                    $srv_id_int = (int)$srv_id;
                    $price_query = mysqli_query($conn, "SELECT price FROM services WHERE service_id = $srv_id_int LIMIT 1");
                    if ($price_query && $price_row = mysqli_fetch_assoc($price_query)) {
                        $service_price = (float)$price_row['price'];
                        $insert_srv = "INSERT INTO appointment_services (appointment_id, service_id, service_price) 
                                       VALUES ($appointment_id, $srv_id_int, $service_price)";
                        if (!mysqli_query($conn, $insert_srv)) {
                            $success = false;
                            break;
                        }
                    } else {
                        $success = false;
                        break;
                    }
                }

                if ($success && count($selected_staff) > 0) {
                    foreach ($selected_staff as $stf_id) {
                        $stf_id_int = (int)$stf_id;
                        $insert_stf = "INSERT INTO appointment_staff (appointment_id, staff_id) 
                                       VALUES ($appointment_id, $stf_id_int)";
                        if (!mysqli_query($conn, $insert_stf)) {
                            $success = false;
                            break;
                        }
                    }
                }
            } else {
                $success = false;
            }

            if ($success) {
                mysqli_query($conn, "COMMIT");
                set_flash('success', 'Appointment #' . $appointment_id . ' created successfully.');
            } else {
                mysqli_query($conn, "ROLLBACK");
                set_flash('danger', 'Failed to create appointment due to a database error.');
            }
        }
        header("Location: appointments.php");
        exit();
    }

    // 2. EDIT APPOINTMENT (Transaction Handling)
    if ($action === 'edit') {
        $appointment_id = (int)$_POST['appointment_id'];
        $customer_id = (int)$_POST['customer_id'];
        $appointment_date = mysqli_real_escape_string($conn, $_POST['appointment_date']);
        $appointment_time = mysqli_real_escape_string($conn, $_POST['appointment_time']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $notes = mysqli_real_escape_string($conn, trim($_POST['notes']));
        $selected_services = isset($_POST['services']) && is_array($_POST['services']) ? $_POST['services'] : [];
        $selected_staff = isset($_POST['staff']) && is_array($_POST['staff']) ? $_POST['staff'] : [];

        if ($appointment_id <= 0 || $customer_id <= 0 || empty($appointment_date) || empty($appointment_time)) {
            set_flash('danger', 'Invalid appointment parameters.');
        } elseif (count($selected_services) === 0) {
            set_flash('danger', 'At least one service must be selected.');
        } else {
            mysqli_query($conn, "START TRANSACTION");
            $success = true;

            $update_sql = "UPDATE appointments SET customer_id = $customer_id, appointment_date = '$appointment_date', appointment_time = '$appointment_time', status = '$status', notes = '$notes' WHERE appointment_id = $appointment_id";
            
            if (mysqli_query($conn, $update_sql)) {
                mysqli_query($conn, "DELETE FROM appointment_services WHERE appointment_id = $appointment_id");

                foreach ($selected_services as $srv_id) {
                    $srv_id_int = (int)$srv_id;
                    $price_query = mysqli_query($conn, "SELECT price FROM services WHERE service_id = $srv_id_int LIMIT 1");
                    if ($price_query && $price_row = mysqli_fetch_assoc($price_query)) {
                        $service_price = (float)$price_row['price'];
                        $insert_srv = "INSERT INTO appointment_services (appointment_id, service_id, service_price) 
                                       VALUES ($appointment_id, $srv_id_int, $service_price)";
                        if (!mysqli_query($conn, $insert_srv)) {
                            $success = false;
                            break;
                        }
                    } else {
                        $success = false;
                        break;
                    }
                }

                if ($success) {
                    mysqli_query($conn, "DELETE FROM appointment_staff WHERE appointment_id = $appointment_id");

                    if (count($selected_staff) > 0) {
                        foreach ($selected_staff as $stf_id) {
                            $stf_id_int = (int)$stf_id;
                            $insert_stf = "INSERT INTO appointment_staff (appointment_id, staff_id) 
                                           VALUES ($appointment_id, $stf_id_int)";
                            if (!mysqli_query($conn, $insert_stf)) {
                                $success = false;
                                break;
                            }
                        }
                    }
                }
            } else {
                $success = false;
            }

            if ($success) {
                mysqli_query($conn, "COMMIT");
                set_flash('success', 'Appointment #' . $appointment_id . ' updated successfully.');
            } else {
                mysqli_query($conn, "ROLLBACK");
                set_flash('danger', 'Failed to update appointment. Transaction rolled back.');
            }
        }
        header("Location: appointments.php");
        exit();
    }

    // 3. DELETE APPOINTMENT
    if ($action === 'delete') {
        $appointment_id = (int)$_POST['appointment_id'];
        if ($appointment_id > 0) {
            $delete_sql = "DELETE FROM appointments WHERE appointment_id = $appointment_id";
            if (mysqli_query($conn, $delete_sql)) {
                set_flash('success', 'Appointment deleted successfully.');
            } else {
                set_flash('danger', 'Unable to delete appointment: ' . mysqli_error($conn));
            }
        }
        header("Location: appointments.php");
        exit();
    }

    // 4. QUICK STATUS UPDATE
    if ($action === 'update_status') {
        $appointment_id = (int)$_POST['appointment_id'];
        $new_status = mysqli_real_escape_string($conn, $_POST['status']);
        $sql = "UPDATE appointments SET status = '$new_status' WHERE appointment_id = $appointment_id";
        if (mysqli_query($conn, $sql)) {
            set_flash('success', 'Appointment #' . $appointment_id . ' status updated to ' . $new_status . '.');
        } else {
            set_flash('danger', 'Failed to update status.');
        }
        header("Location: appointments.php");
        exit();
    }
}

// Search & Filter Parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clauses = [];
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(c.name LIKE '%$search_escaped%' OR c.phone LIKE '%$search_escaped%' OR c.email LIKE '%$search_escaped%')";
}
if (!empty($status_filter)) {
    $status_escaped = mysqli_real_escape_string($conn, $status_filter);
    $where_clauses[] = "a.status = '$status_escaped'";
}
if (!empty($date_from)) {
    $df_escaped = mysqli_real_escape_string($conn, $date_from);
    $where_clauses[] = "a.appointment_date >= '$df_escaped'";
}
if (!empty($date_to)) {
    $dt_escaped = mysqli_real_escape_string($conn, $date_to);
    $where_clauses[] = "a.appointment_date <= '$dt_escaped'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Total records count
$count_sql = "SELECT COUNT(*) AS total FROM appointments a JOIN customers c ON a.customer_id = c.customer_id $where_sql";
$count_res = mysqli_query($conn, $count_sql);
$total_records = 0;
if ($count_res && $row = mysqli_fetch_assoc($count_res)) {
    $total_records = (int)$row['total'];
}
$total_pages = ceil($total_records / $limit);

// Main Query with JOINs & GROUP_CONCAT
$sql = "SELECT 
            a.appointment_id, 
            a.customer_id, 
            a.appointment_date, 
            a.appointment_time, 
            a.status, 
            a.notes, 
            a.created_at,
            c.name AS customer_name,
            c.email AS customer_email,
            c.phone AS customer_phone,
            COALESCE(SUM(aps.service_price), 0.00) AS total_amount,
            GROUP_CONCAT(DISTINCT s.service_name ORDER BY s.service_name SEPARATOR '||') AS service_names,
            GROUP_CONCAT(DISTINCT stf.name ORDER BY stf.name SEPARATOR '||') AS staff_names
        FROM appointments a
        JOIN customers c ON a.customer_id = c.customer_id
        LEFT JOIN appointment_services aps ON a.appointment_id = aps.appointment_id
        LEFT JOIN services s ON aps.service_id = s.service_id
        LEFT JOIN appointment_staff apst ON a.appointment_id = apst.appointment_id
        LEFT JOIN staff stf ON apst.staff_id = stf.staff_id
        $where_sql
        GROUP BY a.appointment_id
        ORDER BY a.appointment_id DESC
        LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $sql);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';

// Statistics Summary Tiles (Procedural MySQLi)
$stat_total_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments");
$stat_total = ($stat_total_res && $r = mysqli_fetch_assoc($stat_total_res)) ? (int)$r['total'] : 0;

$stat_conf_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status = 'Confirmed'");
$stat_conf = ($stat_conf_res && $r = mysqli_fetch_assoc($stat_conf_res)) ? (int)$r['total'] : 0;

$stat_pend_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status = 'Pending'");
$stat_pend = ($stat_pend_res && $r = mysqli_fetch_assoc($stat_pend_res)) ? (int)$r['total'] : 0;

$stat_rev_res = mysqli_query($conn, "SELECT COALESCE(SUM(service_price), 0.00) AS total_rev FROM appointment_services");
$stat_rev = ($stat_rev_res && $r = mysqli_fetch_assoc($stat_rev_res)) ? (float)$r['total_rev'] : 0.00;
?>

<!-- Executive Mini Stat Overview Row -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-purple">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($stat_total); ?></div>
                <div class="mini-stat-label">Total Appointments</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($stat_conf); ?></div>
                <div class="mini-stat-label">Confirmed Bookings</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-amber">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($stat_pend); ?></div>
                <div class="mini-stat-label">Pending Approval</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-gold">
                <i class="fas fa-coins"></i>
            </div>
            <div>
                <div class="mini-stat-val">₹<?php echo number_format($stat_rev, 2); ?></div>
                <div class="mini-stat-label">Total Booking Revenue</div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Table Container -->
<div class="table-card">
    <div class="table-card-header d-flex flex-wrap align-items-center justify-content-between gap-3 p-3">
        <!-- Left Side: Table Title -->
        <div class="d-flex align-items-center gap-3">
            <h5 class="table-card-title mb-0">
                <i class="fas fa-calendar-check text-primary me-2"></i>Salon Appointments List
                <span class="badge bg-light text-dark border rounded-pill fs-7 ms-2"><?php echo $total_records; ?></span>
            </h5>
        </div>

        <!-- Right Side: Integrated Search & Filter Controls -->
        <form action="appointments.php" method="GET" class="d-flex flex-wrap align-items-center gap-2 m-0 ms-auto">
            <div class="input-group" style="width: 200px;">
                <span class="input-group-text bg-light border-end-0" style="border-radius:30px 0 0 30px; padding: 0.4rem 0.75rem;"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search customer..." value="<?php echo htmlspecialchars($search); ?>" style="border-radius:0 30px 30px 0; font-size:0.85rem; padding: 0.4rem 0.75rem;">
            </div>

            <select name="status" class="form-select form-select-custom" onchange="this.form.submit()" style="border-radius:30px; width: 135px; font-size: 0.85rem; padding: 0.4rem 0.75rem;">
                <option value="">All Statuses</option>
                <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="Confirmed" <?php echo ($status_filter === 'Confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                <option value="Completed" <?php echo ($status_filter === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                <option value="Cancelled" <?php echo ($status_filter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
            </select>

            <div class="d-flex align-items-center gap-1">
                <input type="date" name="date_from" class="form-control form-control-custom" title="From Date" value="<?php echo htmlspecialchars($date_from); ?>" style="border-radius:20px; width: 130px; font-size: 0.8rem; padding: 0.35rem 0.5rem;">
                <span class="text-muted small">to</span>
                <input type="date" name="date_to" class="form-control form-control-custom" title="To Date" value="<?php echo htmlspecialchars($date_to); ?>" style="border-radius:20px; width: 130px; font-size: 0.8rem; padding: 0.35rem 0.5rem;">
            </div>

            <button type="submit" class="btn btn-dark font-weight-700 px-3 py-1-5" style="border-radius:30px; font-size: 0.85rem;">Filter</button>
            <?php if (!empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                <a href="appointments.php" class="btn btn-light border px-3 py-1-5" style="border-radius:30px; font-size: 0.85rem;">Reset</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="table-responsive-custom">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>CUSTOMER</th>
                    <th>DATE & TIME</th>
                    <th>SERVICES</th>
                    <th>ASSIGNED STAFF</th>
                    <th>TOTAL</th>
                    <th>STATUS</th>
                    <th class="text-end">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($app = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><strong class="text-muted">#<?php echo $app['appointment_id']; ?></strong></td>
                            <td>
                                <strong class="text-dark d-block fs-6"><?php echo htmlspecialchars($app['customer_name']); ?></strong>
                                <small class="text-muted"><i class="fas fa-phone-alt me-1" style="font-size:0.75rem;"></i><?php echo htmlspecialchars($app['customer_phone']); ?></small>
                            </td>
                            <td>
                                <div class="font-weight-600 text-dark"><i class="far fa-calendar me-1 text-muted"></i><?php echo date('M d, Y', strtotime($app['appointment_date'])); ?></div>
                                <small class="text-muted"><i class="far fa-clock me-1 text-muted"></i><?php echo date('h:i A', strtotime($app['appointment_time'])); ?></small>
                            </td>
                            <td>
                                <?php 
                                if (!empty($app['service_names'])) {
                                    $srv_arr = explode('||', $app['service_names']);
                                    foreach ($srv_arr as $srv_item) {
                                        echo '<span class="chip-service">' . htmlspecialchars($srv_item) . '</span>';
                                    }
                                } else {
                                    echo '<span class="text-muted small">No services</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($app['staff_names'])) {
                                    $stf_arr = explode('||', $app['staff_names']);
                                    foreach ($stf_arr as $stf_item) {
                                        echo '<span class="chip-staff"><i class="fas fa-user-circle me-1"></i>' . htmlspecialchars($stf_item) . '</span>';
                                    }
                                } else {
                                    echo '<span class="text-muted small">Unassigned</span>';
                                }
                                ?>
                            </td>
                            <td><strong class="text-dark fs-6">₹<?php echo number_format($app['total_amount'], 2); ?></strong></td>
                            <td>
                                <div class="dropdown status-dropdown-wrapper d-inline-block">
                                    <button class="status-pill-btn status-<?php echo strtolower($app['status']); ?> dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="status-dot"></span>
                                        <span><?php echo strtoupper($app['status']); ?></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-luxury shadow-lg">
                                        <li>
                                            <form action="appointments.php" method="POST">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?php echo $app['appointment_id']; ?>">
                                                <input type="hidden" name="status" value="Pending">
                                                <button type="submit" class="dropdown-item <?php echo ($app['status'] === 'Pending') ? 'active' : ''; ?>">
                                                    <span class="status-dot dot-yellow"></span> PENDING
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form action="appointments.php" method="POST">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?php echo $app['appointment_id']; ?>">
                                                <input type="hidden" name="status" value="Confirmed">
                                                <button type="submit" class="dropdown-item <?php echo ($app['status'] === 'Confirmed') ? 'active' : ''; ?>">
                                                    <span class="status-dot dot-green"></span> CONFIRMED
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form action="appointments.php" method="POST">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?php echo $app['appointment_id']; ?>">
                                                <input type="hidden" name="status" value="Completed">
                                                <button type="submit" class="dropdown-item <?php echo ($app['status'] === 'Completed') ? 'active' : ''; ?>">
                                                    <span class="status-dot dot-teal"></span> COMPLETED
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form action="appointments.php" method="POST">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?php echo $app['appointment_id']; ?>">
                                                <input type="hidden" name="status" value="Cancelled">
                                                <button type="submit" class="dropdown-item <?php echo ($app['status'] === 'Cancelled') ? 'active' : ''; ?>">
                                                    <span class="status-dot dot-red"></span> CANCELLED
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn-action btn-action-view" data-bs-toggle="modal" data-bs-target="#viewAppointmentModal<?php echo $app['appointment_id']; ?>" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-action btn-action-edit" data-bs-toggle="modal" data-bs-target="#editAppointmentModal<?php echo $app['appointment_id']; ?>" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="appointments.php" method="POST" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="appointment_id" value="<?php echo $app['appointment_id']; ?>">
                                        <button type="submit" class="btn-action btn-action-delete confirm-delete" data-confirm="Are you sure you want to delete appointment #<?php echo $app['appointment_id']; ?>?" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>

                                <!-- VIEW DETAILS MODAL -->
                                <div class="modal fade" id="viewAppointmentModal<?php echo $app['appointment_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content modal-content-luxury">
                                            <div class="modal-header modal-header-luxury">
                                                <div class="d-flex align-items-center gap-2">
                                                    <h5 class="modal-title"><i class="fas fa-file-invoice text-warning me-1"></i> Appointment Overview #<?php echo $app['appointment_id']; ?></h5>
                                                </div>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4 text-start">
                                                <div class="row g-3 mb-4">
                                                    <div class="col-md-6">
                                                        <div class="detail-card-box">
                                                            <h6 class="form-label-custom mb-3"><i class="fas fa-user-circle me-1 text-primary"></i> Customer Information</h6>
                                                            <div class="d-flex align-items-center gap-3">
                                                                <div class="detail-avatar-circle">
                                                                    <?php echo strtoupper(substr($app['customer_name'], 0, 1)); ?>
                                                                </div>
                                                                <div>
                                                                    <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($app['customer_name']); ?></div>
                                                                    <div class="small text-muted"><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($app['customer_email']); ?></div>
                                                                    <div class="small text-muted"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($app['customer_phone']); ?></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="detail-card-box">
                                                            <h6 class="form-label-custom mb-3"><i class="far fa-calendar-alt me-1 text-primary"></i> Schedule & Status</h6>
                                                            <div class="mb-2"><strong>Date:</strong> <?php echo date('F d, Y', strtotime($app['appointment_date'])); ?></div>
                                                            <div class="mb-2"><strong>Time:</strong> <?php echo date('h:i A', strtotime($app['appointment_time'])); ?></div>
                                                            <div><strong>Status:</strong> <span class="status-pill status-<?php echo strtolower($app['status']); ?>"><?php echo $app['status']; ?></span></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <h6 class="form-label-custom mb-3"><i class="fas fa-scissors me-1 text-primary"></i> Booked Services</h6>
                                                <div class="invoice-services-box mb-4">
                                                    <div class="invoice-services-header">
                                                        <span>SERVICE NAME</span>
                                                        <span>DURATION</span>
                                                        <span class="text-end">PRICE</span>
                                                    </div>
                                                    <div class="invoice-services-body">
                                                        <?php 
                                                        $srv_detail_sql = "SELECT s.service_name, s.duration, aps.service_price 
                                                                           FROM appointment_services aps 
                                                                           JOIN services s ON aps.service_id = s.service_id 
                                                                           WHERE aps.appointment_id = " . $app['appointment_id'];
                                                        $srv_detail_res = mysqli_query($conn, $srv_detail_sql);
                                                        $computed_total = 0;
                                                        if ($srv_detail_res && mysqli_num_rows($srv_detail_res) > 0):
                                                            while ($sd = mysqli_fetch_assoc($srv_detail_res)):
                                                                $computed_total += (float)$sd['service_price'];
                                                        ?>
                                                            <div class="invoice-service-row">
                                                                <div class="d-flex align-items-center gap-3">
                                                                    <div class="service-icon-badge">
                                                                        <i class="fas fa-cut"></i>
                                                                    </div>
                                                                    <strong class="text-dark fs-6"><?php echo htmlspecialchars($sd['service_name']); ?></strong>
                                                                </div>
                                                                <div>
                                                                    <span class="chip-duration"><i class="far fa-clock me-1 text-muted"></i><?php echo (int)$sd['duration']; ?> mins</span>
                                                                </div>
                                                                <div class="text-end fw-bold text-dark fs-6">
                                                                    ₹<?php echo number_format($sd['service_price'], 2); ?>
                                                                </div>
                                                            </div>
                                                        <?php 
                                                            endwhile; 
                                                        endif; 
                                                        ?>
                                                    </div>
                                                    <div class="invoice-total-bar">
                                                        <span class="invoice-total-label">TOTAL AMOUNT</span>
                                                        <span class="invoice-total-val">₹<?php echo number_format($computed_total, 2); ?></span>
                                                    </div>
                                                </div>

                                                <h6 class="form-label-custom mb-2"><i class="fas fa-user-nurse me-1 text-primary"></i> Assigned Staff</h6>
                                                <div class="mb-4">
                                                    <?php 
                                                    if (!empty($app['staff_names'])) {
                                                        $stf_arr = explode('||', $app['staff_names']);
                                                        foreach ($stf_arr as $stf_item) {
                                                            echo '<span class="chip-staff"><i class="fas fa-user-circle me-1"></i>' . htmlspecialchars($stf_item) . '</span>';
                                                        }
                                                    } else {
                                                        echo '<span class="text-muted small">Unassigned</span>';
                                                    }
                                                    ?>
                                                </div>

                                                <?php if (!empty($app['notes'])): ?>
                                                    <h6 class="form-label-custom mb-1"><i class="fas fa-sticky-note me-1 text-warning"></i> Notes / Special Requests</h6>
                                                    <div class="notes-callout mb-3">
                                                        <i class="fas fa-quote-left me-2 text-warning opacity-50"></i><?php echo nl2br(htmlspecialchars($app['notes'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer-luxury justify-content-between">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()" style="border-radius:20px;"><i class="fas fa-print me-1"></i> Print Receipt</button>
                                                <button type="button" class="btn-cancel-ghost" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- EDIT APPOINTMENT MODAL -->
                                <div class="modal fade" id="editAppointmentModal<?php echo $app['appointment_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content modal-content-luxury">
                                            <div class="modal-header modal-header-luxury">
                                                <h5 class="modal-title"><i class="fas fa-edit text-warning me-2"></i> Edit Appointment #<?php echo $app['appointment_id']; ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="appointments.php" method="POST" novalidate>
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="appointment_id" value="<?php echo $app['appointment_id']; ?>">
                                                <div class="modal-body p-4 text-start">
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label-custom">Customer *</label>
                                                            <select name="customer_id" class="form-select form-select-custom" required>
                                                                <?php foreach ($all_customers as $c): ?>
                                                                    <option value="<?php echo $c['customer_id']; ?>" <?php echo ($c['customer_id'] == $app['customer_id']) ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($c['name'] . ' (' . $c['phone'] . ')'); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label-custom">Appointment Status</label>
                                                            <select name="status" class="form-select form-select-custom">
                                                                <option value="Pending" <?php echo ($app['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="Confirmed" <?php echo ($app['status'] === 'Confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                                                <option value="Completed" <?php echo ($app['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                                                <option value="Cancelled" <?php echo ($app['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label-custom">Date *</label>
                                                            <input type="date" name="appointment_date" class="form-control form-control-custom" value="<?php echo $app['appointment_date']; ?>" required>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label-custom">Time *</label>
                                                            <input type="time" name="appointment_time" class="form-control form-control-custom" value="<?php echo $app['appointment_time']; ?>" required>
                                                        </div>
                                                    </div>

                                                    <?php 
                                                    $sel_srv_res = mysqli_query($conn, "SELECT service_id FROM appointment_services WHERE appointment_id = " . $app['appointment_id']);
                                                    $active_srv_ids = [];
                                                    if ($sel_srv_res) {
                                                        while ($r = mysqli_fetch_assoc($sel_srv_res)) { $active_srv_ids[] = (int)$r['service_id']; }
                                                    }
                                                    ?>
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
                                                                <label class="checkbox-item-card" for="edit_srv_<?php echo $app['appointment_id']; ?>_<?php echo $s['service_id']; ?>">
                                                                    <span class="d-flex align-items-center gap-2">
                                                                        <input class="service-checkbox" type="checkbox" name="services[]" value="<?php echo $s['service_id']; ?>" data-price="<?php echo $s['price']; ?>" id="edit_srv_<?php echo $app['appointment_id']; ?>_<?php echo $s['service_id']; ?>" <?php echo in_array((int)$s['service_id'], $active_srv_ids) ? 'checked' : ''; ?>>
                                                                        <span class="fw-semibold text-dark"><?php echo htmlspecialchars($s['service_name']); ?></span>
                                                                    </span>
                                                                    <strong class="text-primary fs-7">₹<?php echo number_format($s['price'], 2); ?></strong>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>

                                                    <?php 
                                                    $sel_stf_res = mysqli_query($conn, "SELECT staff_id FROM appointment_staff WHERE appointment_id = " . $app['appointment_id']);
                                                    $active_stf_ids = [];
                                                    if ($sel_stf_res) {
                                                        while ($r = mysqli_fetch_assoc($sel_stf_res)) { $active_stf_ids[] = (int)$r['staff_id']; }
                                                    }
                                                    ?>
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
                                                                <label class="checkbox-item-card" for="edit_stf_<?php echo $app['appointment_id']; ?>_<?php echo $st['staff_id']; ?>">
                                                                    <span class="d-flex align-items-center gap-2">
                                                                        <input type="checkbox" name="staff[]" value="<?php echo $st['staff_id']; ?>" id="edit_stf_<?php echo $app['appointment_id']; ?>_<?php echo $st['staff_id']; ?>" <?php echo in_array((int)$st['staff_id'], $active_stf_ids) ? 'checked' : ''; ?>>
                                                                        <span class="fw-semibold text-dark"><?php echo htmlspecialchars($st['name']); ?></span>
                                                                    </span>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($st['specialization'] ?: 'General'); ?></small>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Notes</label>
                                                        <textarea name="notes" class="form-control form-control-custom" rows="2"><?php echo htmlspecialchars($app['notes']); ?></textarea>
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
                        <td colspan="8" class="text-center text-muted py-4">No appointments found matching your filters.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <small class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> appointments</small>
            <nav>
                <ul class="pagination pagination-custom pagination-sm m-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="appointments.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">Previous</a>
                    </li>
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?php echo ($p === $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="appointments.php?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="appointments.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
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
                            <div class="invalid-feedback-custom">Please select a valid customer.</div>
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
                                <label class="checkbox-item-card" for="add_srv_<?php echo $s['service_id']; ?>">
                                    <span class="d-flex align-items-center gap-2">
                                        <input class="service-checkbox" type="checkbox" name="services[]" value="<?php echo $s['service_id']; ?>" data-price="<?php echo $s['price']; ?>" id="add_srv_<?php echo $s['service_id']; ?>">
                                        <span class="fw-semibold text-dark"><?php echo htmlspecialchars($s['service_name']); ?></span>
                                    </span>
                                    <strong class="text-primary fs-7">₹<?php echo number_format($s['price'], 2); ?></strong>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="invalid-feedback-custom">Please check at least one service.</div>
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
                                <label class="checkbox-item-card" for="add_stf_<?php echo $st['staff_id']; ?>">
                                    <span class="d-flex align-items-center gap-2">
                                        <input type="checkbox" name="staff[]" value="<?php echo $st['staff_id']; ?>" id="add_stf_<?php echo $st['staff_id']; ?>">
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
