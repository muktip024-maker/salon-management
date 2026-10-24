<?php
/**
 * Payment Management Module
 * Salon Management System
 * Executive Dribbble SaaS Redesign (Matches appointments.php, customers.php & staff.php)
 * Uses Procedural MySQLi ONLY
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

$page_title = "Payments & Revenue";
$current_page = "payments";

// Helper function for initials
if (!function_exists('get_initials')) {
    function get_initials($name) {
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $w) {
            if (!empty($w)) $initials .= strtoupper($w[0]);
        }
        return substr($initials, 0, 2) ?: 'P';
    }
}

// Fetch appointments list for add/edit modal dropdowns
$appts_res = mysqli_query($conn, "SELECT a.appointment_id, a.appointment_date, c.name AS customer_name, COALESCE(SUM(aps.service_price), 0) AS total_amount 
                                  FROM appointments a 
                                  JOIN customers c ON a.customer_id = c.customer_id 
                                  LEFT JOIN appointment_services aps ON a.appointment_id = aps.appointment_id 
                                  GROUP BY a.appointment_id 
                                  ORDER BY a.appointment_id DESC LIMIT 50");
$all_appointments = [];
if ($appts_res) {
    while ($ap = mysqli_fetch_assoc($appts_res)) {
        $all_appointments[] = $ap;
    }
}

// Handle Form Submissions (ADD, EDIT, DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Invalid CSRF token. Operation cancelled.');
        header("Location: payments.php");
        exit();
    }

    // 1. ADD PAYMENT
    if ($action === 'add') {
        $appointment_id = (int)$_POST['appointment_id'];
        $amount = (float)$_POST['amount'];
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $payment_status = mysqli_real_escape_string($conn, $_POST['payment_status']);
        $transaction_id = mysqli_real_escape_string($conn, trim($_POST['transaction_id']));
        if (empty($transaction_id)) { $transaction_id = NULL; }

        if ($appointment_id <= 0 || $amount <= 0) {
            set_flash('danger', 'Valid Appointment and Amount greater than 0 are required.');
        } else {
            // Check transaction_id uniqueness if provided
            if ($transaction_id !== NULL) {
                $check_sql = "SELECT payment_id FROM payments WHERE transaction_id = '$transaction_id' LIMIT 1";
                $check_res = mysqli_query($conn, $check_sql);
                if ($check_res && mysqli_num_rows($check_res) > 0) {
                    set_flash('danger', 'A payment with this Transaction ID already exists.');
                    header("Location: payments.php");
                    exit();
                }
            }

            $tx_val = ($transaction_id === NULL) ? "NULL" : "'$transaction_id'";
            $insert_sql = "INSERT INTO payments (appointment_id, amount, payment_method, payment_status, transaction_id) 
                           VALUES ($appointment_id, $amount, '$payment_method', '$payment_status', $tx_val)";
            
            if (mysqli_query($conn, $insert_sql)) {
                set_flash('success', 'Payment recorded successfully.');
            } else {
                set_flash('danger', 'Failed to record payment.');
            }
        }
        header("Location: payments.php");
        exit();
    }

    // 2. EDIT PAYMENT
    if ($action === 'edit') {
        $payment_id = (int)$_POST['payment_id'];
        $appointment_id = (int)$_POST['appointment_id'];
        $amount = (float)$_POST['amount'];
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $payment_status = mysqli_real_escape_string($conn, $_POST['payment_status']);
        $transaction_id = mysqli_real_escape_string($conn, trim($_POST['transaction_id']));
        if (empty($transaction_id)) { $transaction_id = NULL; }

        if ($payment_id <= 0 || $appointment_id <= 0 || $amount <= 0) {
            set_flash('danger', 'Invalid payment data provided.');
        } else {
            if ($transaction_id !== NULL) {
                $check_sql = "SELECT payment_id FROM payments WHERE transaction_id = '$transaction_id' AND payment_id != $payment_id LIMIT 1";
                $check_res = mysqli_query($conn, $check_sql);
                if ($check_res && mysqli_num_rows($check_res) > 0) {
                    set_flash('danger', 'Transaction ID is already assigned to another payment.');
                    header("Location: payments.php");
                    exit();
                }
            }

            $tx_val = ($transaction_id === NULL) ? "NULL" : "'$transaction_id'";
            $update_sql = "UPDATE payments SET appointment_id = $appointment_id, amount = $amount, payment_method = '$payment_method', payment_status = '$payment_status', transaction_id = $tx_val WHERE payment_id = $payment_id";

            if (mysqli_query($conn, $update_sql)) {
                set_flash('success', 'Payment updated successfully.');
            } else {
                set_flash('danger', 'Failed to update payment.');
            }
        }
        header("Location: payments.php");
        exit();
    }

    // 3. DELETE PAYMENT
    if ($action === 'delete') {
        $payment_id = (int)$_POST['payment_id'];
        if ($payment_id > 0) {
            $delete_sql = "DELETE FROM payments WHERE payment_id = $payment_id";
            if (mysqli_query($conn, $delete_sql)) {
                set_flash('success', 'Payment deleted successfully.');
            } else {
                set_flash('danger', 'Failed to delete payment.');
            }
        }
        header("Location: payments.php");
        exit();
    }
}

// Executive Statistics Queries (Procedural MySQLi)
$stat_tot_rev = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0.00) AS total FROM payments WHERE payment_status = 'Paid'");
$total_revenue_amount = ($stat_tot_rev && $r = mysqli_fetch_assoc($stat_tot_rev)) ? (float)$r['total'] : 0.00;

$stat_tot_tx = mysqli_query($conn, "SELECT COUNT(*) AS total FROM payments");
$total_tx_count = ($stat_tot_tx && $r = mysqli_fetch_assoc($stat_tot_tx)) ? (int)$r['total'] : 0;

$stat_paid_tx = mysqli_query($conn, "SELECT COUNT(*) AS total FROM payments WHERE payment_status = 'Paid'");
$paid_tx_count = ($stat_paid_tx && $r = mysqli_fetch_assoc($stat_paid_tx)) ? (int)$r['total'] : 0;

$stat_pend_tx = mysqli_query($conn, "SELECT COUNT(*) AS total FROM payments WHERE payment_status = 'Pending'");
$pending_tx_count = ($stat_pend_tx && $r = mysqli_fetch_assoc($stat_pend_tx)) ? (int)$r['total'] : 0;

// Search & Filter Setup
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$method_filter = isset($_GET['method']) ? trim($_GET['method']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clauses = [];
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(c.name LIKE '%$search_escaped%' OR p.transaction_id LIKE '%$search_escaped%')";
}
if (!empty($method_filter)) {
    $method_escaped = mysqli_real_escape_string($conn, $method_filter);
    $where_clauses[] = "p.payment_method = '$method_escaped'";
}
if (!empty($status_filter)) {
    $status_escaped = mysqli_real_escape_string($conn, $status_filter);
    $where_clauses[] = "p.payment_status = '$status_escaped'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Total records count
$count_sql = "SELECT COUNT(*) AS total 
              FROM payments p 
              JOIN appointments a ON p.appointment_id = a.appointment_id 
              JOIN customers c ON a.customer_id = c.customer_id 
              $where_sql";
$count_res = mysqli_query($conn, $count_sql);
$total_records = 0;
if ($count_res && $row = mysqli_fetch_assoc($count_res)) {
    $total_records = (int)$row['total'];
}
$total_pages = ceil($total_records / $limit);

// Fetch Payments List with JOINs
$sql = "SELECT p.payment_id, p.appointment_id, p.amount, p.payment_method, p.payment_status, p.transaction_id, p.payment_date, c.name AS customer_name, c.email AS customer_email 
        FROM payments p 
        JOIN appointments a ON p.appointment_id = a.appointment_id 
        JOIN customers c ON a.customer_id = c.customer_id 
        $where_sql 
        ORDER BY p.payment_id DESC LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $sql);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- Executive Mini Stat Overview Row (Matches appointments.php & customers.php) -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-gold">
                <i class="fas fa-coins"></i>
            </div>
            <div>
                <div class="mini-stat-val">₹<?php echo number_format($total_revenue_amount, 2); ?></div>
                <div class="mini-stat-label">Total Revenue Collected</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-purple">
                <i class="fas fa-wallet"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($total_tx_count); ?></div>
                <div class="mini-stat-label">Total Payment Transactions</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($paid_tx_count); ?></div>
                <div class="mini-stat-label">Successful Paid Records</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-amber">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($pending_tx_count); ?></div>
                <div class="mini-stat-label">Pending Payment Records</div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Payments Table Container -->
<div class="table-card">
    <div class="table-card-header d-flex flex-wrap align-items-center justify-content-between gap-3 p-3">
        <!-- Left Side: Table Title & Record Payment Button -->
        <div class="d-flex align-items-center gap-3">
            <h5 class="table-card-title mb-0">
                <i class="fas fa-wallet text-primary me-2"></i>Transactions & Payments
                <span class="badge bg-light text-dark border rounded-pill fs-7 ms-2"><?php echo $total_records; ?></span>
            </h5>
        </div>

        <!-- Right Side: Integrated Search & Filters -->
        <form action="payments.php" method="GET" class="d-flex flex-wrap align-items-center gap-2 m-0 ms-auto">
            <select name="method" class="form-select form-select-custom" onchange="this.form.submit()" style="border-radius:30px; width: 130px; font-size: 0.85rem; padding: 0.4rem 0.75rem;">
                <option value="">All Methods</option>
                <option value="Cash" <?php echo ($method_filter === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                <option value="Card" <?php echo ($method_filter === 'Card') ? 'selected' : ''; ?>>Card</option>
                <option value="UPI" <?php echo ($method_filter === 'UPI') ? 'selected' : ''; ?>>UPI</option>
            </select>

            <select name="status" class="form-select form-select-custom" onchange="this.form.submit()" style="border-radius:30px; width: 130px; font-size: 0.85rem; padding: 0.4rem 0.75rem;">
                <option value="">All Statuses</option>
                <option value="Paid" <?php echo ($status_filter === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="Failed" <?php echo ($status_filter === 'Failed') ? 'selected' : ''; ?>>Failed</option>
            </select>

            <div class="input-group" style="width: 220px;">
                <span class="input-group-text bg-light border-end-0" style="border-radius:30px 0 0 30px; padding: 0.4rem 0.75rem;"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search customer, Tx ID..." value="<?php echo htmlspecialchars($search); ?>" style="border-radius:0 30px 30px 0; font-size:0.85rem; padding: 0.4rem 0.75rem;">
            </div>

            <button type="submit" class="btn btn-dark font-weight-700 px-3 py-1-5" style="border-radius:30px; font-size: 0.85rem;">Filter</button>
            <?php if (!empty($search) || !empty($method_filter) || !empty($status_filter)): ?>
                <a href="payments.php" class="btn btn-light border px-3 py-1-5" style="border-radius:30px; font-size: 0.85rem;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive-custom">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>PAYMENT ID</th>
                    <th>CUSTOMER</th>
                    <th>APPOINTMENT</th>
                    <th>AMOUNT</th>
                    <th>METHOD</th>
                    <th>TRANSACTION ID</th>
                    <th>STATUS</th>
                    <th>PAYMENT DATE</th>
                    <th class="text-end">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($pay = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><strong class="text-muted">#<?php echo $pay['payment_id']; ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-circle-sm bg-gold-subtle text-gold fw-bold">
                                        <?php echo get_initials($pay['customer_name']); ?>
                                    </div>
                                    <div>
                                        <strong class="text-dark fs-6 d-block"><?php echo htmlspecialchars($pay['customer_name']); ?></strong>
                                        <small class="text-muted"><i class="far fa-envelope me-1"></i><?php echo htmlspecialchars($pay['customer_email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="chip-selected-item cursor-default fs-8">
                                    <i class="fas fa-calendar-check me-1 text-primary"></i>Appt #<?php echo $pay['appointment_id']; ?>
                                </span>
                            </td>
                            <td><strong class="text-dark fs-6">₹<?php echo number_format($pay['amount'], 2); ?></strong></td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1 fs-8 fw-semibold">
                                    <?php echo htmlspecialchars($pay['payment_method']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($pay['transaction_id'])): ?>
                                    <code class="bg-light text-dark px-2 py-1 rounded fs-8"><?php echo htmlspecialchars($pay['transaction_id']); ?></code>
                                <?php else: ?>
                                    <small class="text-muted">N/A</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $pst = strtolower($pay['payment_status']);
                                $dot_cls = ($pst === 'paid') ? 'dot-green' : (($pst === 'pending') ? 'dot-amber' : 'dot-red');
                                ?>
                                <span class="status-pill-btn <?php echo $pst; ?>">
                                    <span class="status-dot <?php echo $dot_cls; ?>"></span>
                                    <span class="fw-bold fs-7"><?php echo ucfirst($pay['payment_status']); ?></span>
                                </span>
                            </td>
                            <td><small class="text-muted"><i class="far fa-clock me-1"></i><?php echo date('M d, Y h:i A', strtotime($pay['payment_date'])); ?></small></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn-action btn-action-edit" data-bs-toggle="modal" data-bs-target="#editPaymentModal<?php echo $pay['payment_id']; ?>" title="Edit Payment">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="payments.php" method="POST" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="payment_id" value="<?php echo $pay['payment_id']; ?>">
                                        <button type="submit" class="btn-action btn-action-delete confirm-delete" data-confirm="Are you sure you want to delete payment record #<?php echo $pay['payment_id']; ?>?" title="Delete Payment">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>

                                <!-- Edit Payment Modal -->
                                <div class="modal fade" id="editPaymentModal<?php echo $pay['payment_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content modal-content-luxury">
                                            <div class="modal-header modal-header-luxury">
                                                <h5 class="modal-title"><i class="fas fa-edit text-warning me-2"></i> Edit Payment #<?php echo $pay['payment_id']; ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="payments.php" method="POST" novalidate>
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="payment_id" value="<?php echo $pay['payment_id']; ?>">
                                                <div class="modal-body p-4 text-start">
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Appointment *</label>
                                                        <select name="appointment_id" class="form-select form-select-custom" required>
                                                            <?php foreach ($all_appointments as $ap): ?>
                                                                <option value="<?php echo $ap['appointment_id']; ?>" <?php echo ($ap['appointment_id'] == $pay['appointment_id']) ? 'selected' : ''; ?>>
                                                                    Appt #<?php echo $ap['appointment_id']; ?> - <?php echo htmlspecialchars($ap['customer_name']); ?> (Total: ₹<?php echo number_format($ap['total_amount'], 2); ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Amount (INR ₹) *</label>
                                                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-custom" value="<?php echo $pay['amount']; ?>" required>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label-custom">Payment Method *</label>
                                                            <select name="payment_method" class="form-select form-select-custom">
                                                                <option value="Cash" <?php echo ($pay['payment_method'] === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                                                                <option value="Card" <?php echo ($pay['payment_method'] === 'Card') ? 'selected' : ''; ?>>Card</option>
                                                                <option value="UPI" <?php echo ($pay['payment_method'] === 'UPI') ? 'selected' : ''; ?>>UPI</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label-custom">Payment Status *</label>
                                                            <select name="payment_status" class="form-select form-select-custom">
                                                                <option value="Paid" <?php echo ($pay['payment_status'] === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                                                                <option value="Pending" <?php echo ($pay['payment_status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="Failed" <?php echo ($pay['payment_status'] === 'Failed') ? 'selected' : ''; ?>>Failed</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Transaction ID <small class="text-muted">(Optional for cash)</small></label>
                                                        <input type="text" name="transaction_id" class="form-control form-control-custom" value="<?php echo htmlspecialchars((string)$pay['transaction_id']); ?>" placeholder="e.g. TXN12345678">
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
                        <td colspan="9" class="text-center text-muted py-4">No payment records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <small class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> payment records</small>
            <nav>
                <ul class="pagination pagination-custom pagination-sm m-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="payments.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&method=<?php echo urlencode($method_filter); ?>&status=<?php echo urlencode($status_filter); ?>">Previous</a>
                    </li>
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?php echo ($p === $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="payments.php?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>&method=<?php echo urlencode($method_filter); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="payments.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&method=<?php echo urlencode($method_filter); ?>&status=<?php echo urlencode($status_filter); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-luxury">
            <div class="modal-header modal-header-luxury">
                <h5 class="modal-title"><i class="fas fa-plus-circle text-warning me-2"></i> Record New Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="payments.php" method="POST" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4 text-start">
                    <div class="mb-3">
                        <label class="form-label-custom">Select Appointment *</label>
                        <select name="appointment_id" class="form-select form-select-custom" required>
                            <option value="">Select Appointment</option>
                            <?php foreach ($all_appointments as $ap): ?>
                                <option value="<?php echo $ap['appointment_id']; ?>">
                                    Appt #<?php echo $ap['appointment_id']; ?> - <?php echo htmlspecialchars($ap['customer_name']); ?> (Total: ₹<?php echo number_format($ap['total_amount'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Payment Amount (INR ₹) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-custom" placeholder="1000.00" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">Payment Method *</label>
                            <select name="payment_method" class="form-select form-select-custom">
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="UPI">UPI</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">Payment Status *</label>
                            <select name="payment_status" class="form-select form-select-custom">
                                <option value="Paid">Paid</option>
                                <option value="Pending">Pending</option>
                                <option value="Failed">Failed</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Transaction ID <small class="text-muted">(Optional for Cash)</small></label>
                        <input type="text" name="transaction_id" class="form-control form-control-custom" placeholder="e.g. TXN12345678">
                    </div>
                </div>
                <div class="modal-footer-luxury">
                    <button type="button" class="btn-cancel-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-submit-gold">Save Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
