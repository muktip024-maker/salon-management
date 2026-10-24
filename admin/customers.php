<?php
/**
 * Customer Management Module
 * Salon Management System
 * Executive Dribbble SaaS Redesign (Matches appointments.php)
 * Uses Procedural MySQLi ONLY
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

$page_title = "Customer Management";
$current_page = "customers";

// Helper function for initials
if (!function_exists('get_initials')) {
    function get_initials($name) {
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $w) {
            if (!empty($w)) $initials .= strtoupper($w[0]);
        }
        return substr($initials, 0, 2) ?: 'C';
    }
}

// Handle Actions (ADD, EDIT, DELETE, TOGGLE STATUS)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Invalid CSRF token. Operation cancelled.');
        header("Location: customers.php");
        exit();
    }

    // 1. ADD CUSTOMER
    if ($action === 'add') {
        $name = mysqli_real_escape_string($conn, trim($_POST['name']));
        $email = mysqli_real_escape_string($conn, trim($_POST['email']));
        $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
        $password = trim($_POST['password']);
        $gender = mysqli_real_escape_string($conn, $_POST['gender']);
        $address = mysqli_real_escape_string($conn, trim($_POST['address']));
        $status = mysqli_real_escape_string($conn, $_POST['status']);

        if (empty($name) || empty($email) || empty($phone) || empty($password)) {
            set_flash('danger', 'Name, Email, Phone, and Password are required fields.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Invalid email address format.');
        } else {
            $check_sql = "SELECT customer_id FROM customers WHERE email = '$email' OR phone = '$phone' LIMIT 1";
            $check_res = mysqli_query($conn, $check_sql);

            if ($check_res && mysqli_num_rows($check_res) > 0) {
                set_flash('danger', 'A customer with this email address or phone number already exists.');
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $password_hash_escaped = mysqli_real_escape_string($conn, $password_hash);

                $insert_sql = "INSERT INTO customers (name, email, phone, password, gender, address, status) 
                               VALUES ('$name', '$email', '$phone', '$password_hash_escaped', '$gender', '$address', '$status')";
                
                if (mysqli_query($conn, $insert_sql)) {
                    set_flash('success', 'Customer added successfully.');
                } else {
                    set_flash('danger', 'Failed to add customer. Please try again.');
                }
            }
        }
        header("Location: customers.php");
        exit();
    }

    // 2. EDIT CUSTOMER
    if ($action === 'edit') {
        $customer_id = (int)$_POST['customer_id'];
        $name = mysqli_real_escape_string($conn, trim($_POST['name']));
        $email = mysqli_real_escape_string($conn, trim($_POST['email']));
        $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
        $password = trim($_POST['password']);
        $gender = mysqli_real_escape_string($conn, $_POST['gender']);
        $address = mysqli_real_escape_string($conn, trim($_POST['address']));
        $status = mysqli_real_escape_string($conn, $_POST['status']);

        if ($customer_id <= 0 || empty($name) || empty($email) || empty($phone)) {
            set_flash('danger', 'Invalid customer data provided.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Invalid email format.');
        } else {
            $check_sql = "SELECT customer_id FROM customers WHERE (email = '$email' OR phone = '$phone') AND customer_id != $customer_id LIMIT 1";
            $check_res = mysqli_query($conn, $check_sql);

            if ($check_res && mysqli_num_rows($check_res) > 0) {
                set_flash('danger', 'Email or phone number is already in use by another customer.');
            } else {
                if (!empty($password)) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $password_hash_escaped = mysqli_real_escape_string($conn, $password_hash);
                    $update_sql = "UPDATE customers SET name = '$name', email = '$email', phone = '$phone', password = '$password_hash_escaped', gender = '$gender', address = '$address', status = '$status' WHERE customer_id = $customer_id";
                } else {
                    $update_sql = "UPDATE customers SET name = '$name', email = '$email', phone = '$phone', gender = '$gender', address = '$address', status = '$status' WHERE customer_id = $customer_id";
                }

                if (mysqli_query($conn, $update_sql)) {
                    set_flash('success', 'Customer details updated successfully.');
                } else {
                    set_flash('danger', 'Failed to update customer details.');
                }
            }
        }
        header("Location: customers.php");
        exit();
    }

    // 3. DELETE CUSTOMER
    if ($action === 'delete') {
        $customer_id = (int)$_POST['customer_id'];
        if ($customer_id > 0) {
            $fk_check = "SELECT appointment_id FROM appointments WHERE customer_id = $customer_id LIMIT 1";
            $fk_res = mysqli_query($conn, $fk_check);

            if ($fk_res && mysqli_num_rows($fk_res) > 0) {
                set_flash('warning', 'Cannot delete this customer because they have existing appointments. Consider deactivating their account instead.');
            } else {
                $delete_sql = "DELETE FROM customers WHERE customer_id = $customer_id";
                if (mysqli_query($conn, $delete_sql)) {
                    set_flash('success', 'Customer deleted successfully.');
                } else {
                    set_flash('danger', 'Unable to delete customer. Error: ' . mysqli_error($conn));
                }
            }
        }
        header("Location: customers.php");
        exit();
    }

    // 4. TOGGLE STATUS
    if ($action === 'toggle_status') {
        $customer_id = (int)$_POST['customer_id'];
        $current_st = mysqli_real_escape_string($conn, $_POST['current_status']);
        $new_st = ($current_st === 'active') ? 'inactive' : 'active';

        $toggle_sql = "UPDATE customers SET status = '$new_st' WHERE customer_id = $customer_id";
        if (mysqli_query($conn, $toggle_sql)) {
            set_flash('success', "Customer status changed to " . ucfirst($new_st) . ".");
        } else {
            set_flash('danger', 'Failed to change status.');
        }
        header("Location: customers.php");
        exit();
    }
}

// Executive Statistics Queries (Procedural MySQLi)
$stat_tot_cust = mysqli_query($conn, "SELECT COUNT(*) AS total FROM customers");
$total_customers_count = ($stat_tot_cust && $r = mysqli_fetch_assoc($stat_tot_cust)) ? (int)$r['total'] : 0;

$stat_act_cust = mysqli_query($conn, "SELECT COUNT(*) AS total FROM customers WHERE status = 'active'");
$active_customers_count = ($stat_act_cust && $r = mysqli_fetch_assoc($stat_act_cust)) ? (int)$r['total'] : 0;

$stat_inact_cust = mysqli_query($conn, "SELECT COUNT(*) AS total FROM customers WHERE status = 'inactive'");
$inactive_customers_count = ($stat_inact_cust && $r = mysqli_fetch_assoc($stat_inact_cust)) ? (int)$r['total'] : 0;

$stat_new_cust = mysqli_query($conn, "SELECT COUNT(*) AS total FROM customers WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$new_this_month = ($stat_new_cust && $r = mysqli_fetch_assoc($stat_new_cust)) ? (int)$r['total'] : 0;

// Search & Pagination Setup
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clause = "";
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_clause = "WHERE name LIKE '%$search_escaped%' OR email LIKE '%$search_escaped%' OR phone LIKE '%$search_escaped%'";
}

$count_sql = "SELECT COUNT(*) AS total FROM customers $where_clause";
$count_res = mysqli_query($conn, $count_sql);
$total_records = 0;
if ($count_res && $row = mysqli_fetch_assoc($count_res)) {
    $total_records = (int)$row['total'];
}
$total_pages = ceil($total_records / $limit);

$sql = "SELECT customer_id, name, email, phone, gender, address, status, created_at FROM customers $where_clause ORDER BY customer_id DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- Executive Mini Stat Overview Row (Matches appointments.php) -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-purple">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($total_customers_count); ?></div>
                <div class="mini-stat-label">Total Registered Clients</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-green">
                <i class="fas fa-user-check"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($active_customers_count); ?></div>
                <div class="mini-stat-label">Active Salon Clients</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-amber">
                <i class="fas fa-user-slash"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($inactive_customers_count); ?></div>
                <div class="mini-stat-label">Inactive Accounts</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-gold">
                <i class="fas fa-user-plus"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($new_this_month); ?></div>
                <div class="mini-stat-label">Joined This Month</div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Customers Table Container -->
<div class="table-card">
    <div class="table-card-header d-flex flex-wrap align-items-center justify-content-between gap-3 p-3">
        <!-- Left Side: Table Title & Add CTA Button -->
        <div class="d-flex align-items-center gap-3">
            <h5 class="table-card-title mb-0">
                <i class="fas fa-users text-primary me-2"></i>Customers Directory
                <span class="badge bg-light text-dark border rounded-pill fs-7 ms-2"><?php echo $total_records; ?></span>
            </h5>
            <button type="button" class="btn btn-accent text-white font-weight-700 px-3 py-1-5" data-bs-toggle="modal" data-bs-target="#addCustomerModal" style="border-radius:30px; font-size: 0.85rem;">
                <i class="fas fa-user-plus me-1"></i> Add New Customer
            </button>
        </div>

        <!-- Right Side: Integrated Search Form -->
        <form action="customers.php" method="GET" class="d-flex flex-wrap align-items-center gap-2 m-0 ms-auto">
            <div class="input-group" style="width: 260px;">
                <span class="input-group-text bg-light border-end-0" style="border-radius:30px 0 0 30px; padding: 0.4rem 0.75rem;"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, email, phone..." value="<?php echo htmlspecialchars($search); ?>" style="border-radius:0 30px 30px 0; font-size:0.85rem; padding: 0.4rem 0.75rem;">
            </div>
            <button type="submit" class="btn btn-dark font-weight-700 px-3 py-1-5" style="border-radius:30px; font-size: 0.85rem;">Search</button>
            <?php if (!empty($search)): ?>
                <a href="customers.php" class="btn btn-light border px-3 py-1-5" style="border-radius:30px; font-size: 0.85rem;">Reset</a>
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
                    <th>GENDER</th>
                    <th>ADDRESS</th>
                    <th>STATUS</th>
                    <th>CREATED DATE</th>
                    <th class="text-end">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($cust = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><strong class="text-muted">#<?php echo $cust['customer_id']; ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-circle-sm bg-gold-subtle text-gold fw-bold">
                                        <?php echo get_initials($cust['name']); ?>
                                    </div>
                                    <strong class="text-dark fs-6"><?php echo htmlspecialchars($cust['name']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <span class="text-dark"><i class="far fa-envelope me-1 text-muted"></i><?php echo htmlspecialchars($cust['email']); ?></span>
                            </td>
                            <td>
                                <span class="text-dark"><i class="fas fa-phone-alt me-1 text-muted fs-8"></i><?php echo htmlspecialchars($cust['phone']); ?></span>
                            </td>
                            <td>
                                <span class="chip-selected-item cursor-default fs-8"><?php echo htmlspecialchars($cust['gender'] ?: 'N/A'); ?></span>
                            </td>
                            <td>
                                <small class="text-muted"><i class="fas fa-map-marker-alt me-1 text-danger"></i><?php echo htmlspecialchars($cust['address'] ?: 'N/A'); ?></small>
                            </td>
                            <td>
                                <form action="customers.php" method="POST" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="customer_id" value="<?php echo $cust['customer_id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $cust['status']; ?>">
                                    <button type="submit" class="border-0 bg-transparent p-0" title="Click to toggle status">
                                        <?php 
                                        $st = strtolower($cust['status']);
                                        $dot_cls = ($st === 'active') ? 'dot-green' : 'dot-red';
                                        ?>
                                        <span class="status-pill-btn <?php echo $st; ?>">
                                            <span class="status-dot <?php echo $dot_cls; ?>"></span>
                                            <span class="fw-bold fs-7"><?php echo ucfirst($cust['status']); ?></span>
                                        </span>
                                    </button>
                                </form>
                            </td>
                            <td><small class="text-muted"><i class="far fa-calendar-alt me-1"></i><?php echo date('M d, Y', strtotime($cust['created_at'])); ?></small></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn-action btn-action-edit" data-bs-toggle="modal" data-bs-target="#editCustomerModal<?php echo $cust['customer_id']; ?>" title="Edit Customer">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="customers.php" method="POST" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="customer_id" value="<?php echo $cust['customer_id']; ?>">
                                        <button type="submit" class="btn-action btn-action-delete confirm-delete" data-confirm="Are you sure you want to delete customer <?php echo htmlspecialchars($cust['name']); ?>?" title="Delete Customer">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>

                                <!-- Edit Customer Modal -->
                                <div class="modal fade" id="editCustomerModal<?php echo $cust['customer_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content modal-content-luxury">
                                            <div class="modal-header modal-header-luxury">
                                                <h5 class="modal-title"><i class="fas fa-user-edit text-warning me-2"></i> Edit Customer #<?php echo $cust['customer_id']; ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="customers.php" method="POST" novalidate>
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="customer_id" value="<?php echo $cust['customer_id']; ?>">
                                                <div class="modal-body p-4 text-start">
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Full Name *</label>
                                                        <input type="text" name="name" class="form-control form-control-custom" value="<?php echo htmlspecialchars($cust['name']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Email Address *</label>
                                                        <input type="email" name="email" class="form-control form-control-custom" value="<?php echo htmlspecialchars($cust['email']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Phone Number *</label>
                                                        <input type="text" name="phone" class="form-control form-control-custom" value="<?php echo htmlspecialchars($cust['phone']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">New Password <small class="text-muted">(Leave blank to keep unchanged)</small></label>
                                                        <input type="password" name="password" class="form-control form-control-custom" placeholder="••••••••">
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label-custom">Gender</label>
                                                            <select name="gender" class="form-select form-select-custom">
                                                                <option value="Male" <?php echo ($cust['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                                                <option value="Female" <?php echo ($cust['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                                                <option value="Other" <?php echo ($cust['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label-custom">Status</label>
                                                            <select name="status" class="form-select form-select-custom">
                                                                <option value="active" <?php echo ($cust['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                                                <option value="inactive" <?php echo ($cust['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Address</label>
                                                        <textarea name="address" class="form-control form-control-custom" rows="2"><?php echo htmlspecialchars($cust['address']); ?></textarea>
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
                        <td colspan="9" class="text-center text-muted py-4">No customers found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <small class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> customers</small>
            <nav>
                <ul class="pagination pagination-custom pagination-sm m-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="customers.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                    </li>
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?php echo ($p === $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="customers.php?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="customers.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-luxury">
            <div class="modal-header modal-header-luxury">
                <h5 class="modal-title"><i class="fas fa-user-plus text-warning me-2"></i> Add New Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="customers.php" method="POST" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4 text-start">
                    <div class="mb-3">
                        <label class="form-label-custom">Full Name *</label>
                        <input type="text" name="name" class="form-control form-control-custom" placeholder="e.g. Ananya Sharma" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Email Address *</label>
                        <input type="email" name="email" class="form-control form-control-custom" placeholder="ananya@example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Phone Number *</label>
                        <input type="text" name="phone" class="form-control form-control-custom" placeholder="9876543210" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Password *</label>
                        <input type="password" name="password" class="form-control form-control-custom" placeholder="••••••••" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">Gender</label>
                            <select name="gender" class="form-select form-select-custom">
                                <option value="Female">Female</option>
                                <option value="Male">Male</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">Status</label>
                            <select name="status" class="form-select form-select-custom">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Address</label>
                        <textarea name="address" class="form-control form-control-custom" rows="2" placeholder="Customer address..."></textarea>
                    </div>
                </div>
                <div class="modal-footer-luxury">
                    <button type="button" class="btn-cancel-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-submit-gold">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
