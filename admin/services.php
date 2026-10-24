<?php
/**
 * Service Management Module
 * Salon Management System
 * Executive Dribbble SaaS Redesign (Matches appointments.php, customers.php, staff.php & categories.php)
 * Uses Procedural MySQLi ONLY
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

$page_title = "Salon Services";
$current_page = "services";

// Fetch categories for dropdown filters and forms
$categories_res = mysqli_query($conn, "SELECT category_id, category_name FROM service_categories WHERE status = 'active' ORDER BY category_name ASC");
$all_categories = [];
if ($categories_res) {
    while ($c = mysqli_fetch_assoc($categories_res)) {
        $all_categories[] = $c;
    }
}

// Handle Actions (ADD, EDIT, DELETE, TOGGLE STATUS)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Invalid CSRF token. Operation cancelled.');
        header("Location: services.php");
        exit();
    }

    // 1. ADD SERVICE
    if ($action === 'add') {
        $service_name = mysqli_real_escape_string($conn, trim($_POST['service_name']));
        $category_id = (int)$_POST['category_id'];
        $description = mysqli_real_escape_string($conn, trim($_POST['description']));
        $price = (float)$_POST['price'];
        $duration = (int)$_POST['duration'];
        $status = mysqli_real_escape_string($conn, $_POST['status']);

        if (empty($service_name) || $category_id <= 0) {
            set_flash('danger', 'Service Name and Category are required.');
        } elseif ($price < 0) {
            set_flash('danger', 'Price must be a valid non-negative number.');
        } elseif ($duration <= 0) {
            set_flash('danger', 'Duration must be greater than 0 minutes.');
        } else {
            $insert_sql = "INSERT INTO services (category_id, service_name, description, price, duration, status) 
                           VALUES ($category_id, '$service_name', '$description', $price, $duration, '$status')";
            if (mysqli_query($conn, $insert_sql)) {
                set_flash('success', 'Service added successfully.');
            } else {
                set_flash('danger', 'Failed to add service.');
            }
        }
        header("Location: services.php");
        exit();
    }

    // 2. EDIT SERVICE
    if ($action === 'edit') {
        $service_id = (int)$_POST['service_id'];
        $service_name = mysqli_real_escape_string($conn, trim($_POST['service_name']));
        $category_id = (int)$_POST['category_id'];
        $description = mysqli_real_escape_string($conn, trim($_POST['description']));
        $price = (float)$_POST['price'];
        $duration = (int)$_POST['duration'];
        $status = mysqli_real_escape_string($conn, $_POST['status']);

        if ($service_id <= 0 || empty($service_name) || $category_id <= 0) {
            set_flash('danger', 'Service Name and Category are required.');
        } elseif ($price < 0) {
            set_flash('danger', 'Price must be non-negative.');
        } elseif ($duration <= 0) {
            set_flash('danger', 'Duration must be greater than 0.');
        } else {
            $update_sql = "UPDATE services SET category_id = $category_id, service_name = '$service_name', description = '$description', price = $price, duration = $duration, status = '$status' WHERE service_id = $service_id";
            if (mysqli_query($conn, $update_sql)) {
                set_flash('success', 'Service updated successfully.');
            } else {
                set_flash('danger', 'Failed to update service.');
            }
        }
        header("Location: services.php");
        exit();
    }

    // 3. DELETE SERVICE WITH SAFE FK CHECK
    if ($action === 'delete') {
        $service_id = (int)$_POST['service_id'];
        if ($service_id > 0) {
            // Foreign Key Check on appointment_services
            $fk_check = "SELECT appointment_service_id FROM appointment_services WHERE service_id = $service_id LIMIT 1";
            $fk_res = mysqli_query($conn, $fk_check);

            if ($fk_res && mysqli_num_rows($fk_res) > 0) {
                set_flash('warning', 'Cannot delete this service because it is associated with existing appointment records. We recommend marking it Inactive instead.');
            } else {
                $delete_sql = "DELETE FROM services WHERE service_id = $service_id";
                if (mysqli_query($conn, $delete_sql)) {
                    set_flash('success', 'Service deleted successfully.');
                } else {
                    set_flash('danger', 'Failed to delete service.');
                }
            }
        }
        header("Location: services.php");
        exit();
    }

    // 4. TOGGLE STATUS
    if ($action === 'toggle_status') {
        $service_id = (int)$_POST['service_id'];
        $current_st = mysqli_real_escape_string($conn, $_POST['current_status']);
        $new_st = ($current_st === 'active') ? 'inactive' : 'active';

        $toggle_sql = "UPDATE services SET status = '$new_st' WHERE service_id = $service_id";
        if (mysqli_query($conn, $toggle_sql)) {
            set_flash('success', "Service status updated to " . ucfirst($new_st) . ".");
        } else {
            set_flash('danger', 'Failed to update status.');
        }
        header("Location: services.php");
        exit();
    }
}

// Executive Statistics Queries (Procedural MySQLi)
$stat_tot_srv = mysqli_query($conn, "SELECT COUNT(*) AS total FROM services");
$total_srv_count = ($stat_tot_srv && $r = mysqli_fetch_assoc($stat_tot_srv)) ? (int)$r['total'] : 0;

$stat_act_srv = mysqli_query($conn, "SELECT COUNT(*) AS total FROM services WHERE status = 'active'");
$active_srv_count = ($stat_act_srv && $r = mysqli_fetch_assoc($stat_act_srv)) ? (int)$r['total'] : 0;

$stat_inact_srv = mysqli_query($conn, "SELECT COUNT(*) AS total FROM services WHERE status = 'inactive'");
$inactive_srv_count = ($stat_inact_srv && $r = mysqli_fetch_assoc($stat_inact_srv)) ? (int)$r['total'] : 0;

$stat_avg_price = mysqli_query($conn, "SELECT COALESCE(AVG(price), 0.00) AS total FROM services WHERE status = 'active'");
$avg_service_price = ($stat_avg_price && $r = mysqli_fetch_assoc($stat_avg_price)) ? (float)$r['total'] : 0.00;

// Search & Filter & Pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$cat_filter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clauses = [];
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(s.service_name LIKE '%$search_escaped%' OR s.description LIKE '%$search_escaped%')";
}
if ($cat_filter > 0) {
    $where_clauses[] = "s.category_id = $cat_filter";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

$count_sql = "SELECT COUNT(*) AS total FROM services s $where_sql";
$count_res = mysqli_query($conn, $count_sql);
$total_records = 0;
if ($count_res && $row = mysqli_fetch_assoc($count_res)) {
    $total_records = (int)$row['total'];
}
$total_pages = ceil($total_records / $limit);

$sql = "SELECT s.service_id, s.category_id, s.service_name, s.description, s.price, s.duration, s.status, c.category_name 
        FROM services s 
        LEFT JOIN service_categories c ON s.category_id = c.category_id 
        $where_sql 
        ORDER BY s.service_id DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- Executive Mini Stat Overview Row (Matches appointments.php, customers.php, staff.php & categories.php) -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-purple">
                <i class="fas fa-scissors"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($total_srv_count); ?></div>
                <div class="mini-stat-label">Total Services Offered</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($active_srv_count); ?></div>
                <div class="mini-stat-label">Active Catalog Services</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-amber">
                <i class="fas fa-eye-slash"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($inactive_srv_count); ?></div>
                <div class="mini-stat-label">Inactive Services</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-gold">
                <i class="fas fa-coins"></i>
            </div>
            <div>
                <div class="mini-stat-val">₹<?php echo number_format($avg_service_price, 2); ?></div>
                <div class="mini-stat-label">Average Service Price</div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Services Table Container -->
<div class="table-card">
    <div class="table-card-header d-flex flex-wrap align-items-center justify-content-between gap-3 p-3">
        <!-- Left Side: Table Title & Add CTA Button -->
        <div class="d-flex align-items-center gap-3">
            <h5 class="table-card-title mb-0">
                <i class="fas fa-scissors text-primary me-2"></i>Services Catalog
                <span class="badge bg-light text-dark border rounded-pill fs-7 ms-2"><?php echo $total_records; ?></span>
            </h5>
            <button type="button" class="btn px-4 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addServiceModal" style="border-radius:30px; font-size: 0.875rem;">
                <i class="fas fa-plus-circle me-2"></i> Add New Service
            </button>
        </div>

        <!-- Right Side: Integrated Category & Search Filters -->
        <form action="services.php" method="GET" class="d-flex flex-wrap align-items-center gap-2 m-0 ms-auto">
            <select name="category_id" class="form-select form-select-custom" onchange="this.form.submit()" style="border-radius:30px; width: 160px; font-size: 0.85rem; padding: 0.4rem 0.75rem;">
                <option value="0">All Categories</option>
                <?php foreach ($all_categories as $cat): ?>
                    <option value="<?php echo $cat['category_id']; ?>" <?php echo ($cat_filter === (int)$cat['category_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="input-group" style="width: 220px;">
                <span class="input-group-text bg-light border-end-0" style="border-radius:30px 0 0 30px; padding: 0.4rem 0.75rem;"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search service name..." value="<?php echo htmlspecialchars($search); ?>" style="border-radius:0 30px 30px 0; font-size:0.85rem; padding: 0.4rem 0.75rem;">
            </div>

            <button type="submit" class="btn btn-dark font-weight-700 px-3 py-1-5" style="border-radius:30px; font-size: 0.85rem;">Filter</button>
            <?php if (!empty($search) || $cat_filter > 0): ?>
                <a href="services.php" class="btn btn-light border px-3 py-1-5" style="border-radius:30px; font-size: 0.85rem;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive-custom">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>SERVICE NAME</th>
                    <th>CATEGORY</th>
                    <th>PRICE (INR)</th>
                    <th>DURATION</th>
                    <th>STATUS</th>
                    <th class="text-end">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($srv = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><strong class="text-muted">#<?php echo $srv['service_id']; ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-circle-sm bg-gold-subtle text-gold fw-bold">
                                        <i class="fas fa-scissors"></i>
                                    </div>
                                    <div>
                                        <strong class="text-dark fs-6 d-block"><?php echo htmlspecialchars($srv['service_name']); ?></strong>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($srv['description'], 0, 60)) . (strlen($srv['description']) > 60 ? '...' : ''); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="chip-selected-item cursor-default fs-8">
                                    <i class="fas fa-folder me-1 text-primary"></i><?php echo htmlspecialchars($srv['category_name'] ?: 'Uncategorized'); ?>
                                </span>
                            </td>
                            <td><strong class="text-dark fs-6">₹<?php echo number_format($srv['price'], 2); ?></strong></td>
                            <td><small class="text-muted"><i class="far fa-clock me-1 text-muted"></i><?php echo (int)$srv['duration']; ?> mins</small></td>
                            <td>
                                <form action="services.php" method="POST" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="service_id" value="<?php echo $srv['service_id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $srv['status']; ?>">
                                    <button type="submit" class="border-0 bg-transparent p-0" title="Click to toggle status">
                                        <?php 
                                        $st = strtolower($srv['status']);
                                        $dot_cls = ($st === 'active') ? 'dot-green' : 'dot-red';
                                        ?>
                                        <span class="status-pill-btn <?php echo $st; ?>">
                                            <span class="status-dot <?php echo $dot_cls; ?>"></span>
                                            <span class="fw-bold fs-7"><?php echo ucfirst($srv['status']); ?></span>
                                        </span>
                                    </button>
                                </form>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn-action btn-action-edit" data-bs-toggle="modal" data-bs-target="#editServiceModal<?php echo $srv['service_id']; ?>" title="Edit Service">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="services.php" method="POST" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="service_id" value="<?php echo $srv['service_id']; ?>">
                                        <button type="submit" class="btn-action btn-action-delete confirm-delete" data-confirm="Are you sure you want to delete service '<?php echo htmlspecialchars($srv['service_name']); ?>'?" title="Delete Service">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>

                                <!-- Edit Service Modal -->
                                <div class="modal fade" id="editServiceModal<?php echo $srv['service_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content modal-content-luxury">
                                            <div class="modal-header modal-header-luxury">
                                                <h5 class="modal-title"><i class="fas fa-edit text-warning me-2"></i> Edit Service #<?php echo $srv['service_id']; ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="services.php" method="POST" novalidate>
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="service_id" value="<?php echo $srv['service_id']; ?>">
                                                <div class="modal-body p-4 text-start">
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Service Name *</label>
                                                        <input type="text" name="service_name" class="form-control form-control-custom" value="<?php echo htmlspecialchars($srv['service_name']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Service Category *</label>
                                                        <select name="category_id" class="form-select form-select-custom" required>
                                                            <?php foreach ($all_categories as $cat): ?>
                                                                <option value="<?php echo $cat['category_id']; ?>" <?php echo ($srv['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label-custom">Price (INR ₹) *</label>
                                                            <input type="number" step="0.01" min="0" name="price" class="form-control form-control-custom" value="<?php echo $srv['price']; ?>" required>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label-custom">Duration (Minutes) *</label>
                                                            <input type="number" min="1" name="duration" class="form-control form-control-custom" value="<?php echo $srv['duration']; ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Status</label>
                                                        <select name="status" class="form-select form-select-custom">
                                                            <option value="active" <?php echo ($srv['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                                            <option value="inactive" <?php echo ($srv['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Description</label>
                                                        <textarea name="description" class="form-control form-control-custom" rows="3"><?php echo htmlspecialchars($srv['description']); ?></textarea>
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
                        <td colspan="7" class="text-center text-muted py-4">No services found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <small class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> services</small>
            <nav>
                <ul class="pagination pagination-custom pagination-sm m-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="services.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $cat_filter; ?>">Previous</a>
                    </li>
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?php echo ($p === $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="services.php?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $cat_filter; ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="services.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category_id=<?php echo $cat_filter; ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Add Service Modal -->
<div class="modal fade" id="addServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-luxury">
            <div class="modal-header modal-header-luxury">
                <h5 class="modal-title"><i class="fas fa-plus-circle text-warning me-2"></i> Add New Service</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="services.php" method="POST" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4 text-start">
                    <div class="mb-3">
                        <label class="form-label-custom">Service Name *</label>
                        <input type="text" name="service_name" class="form-control form-control-custom" placeholder="e.g. Keratin Treatment" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Service Category *</label>
                        <select name="category_id" class="form-select form-select-custom" required>
                            <option value="">Select Category</option>
                            <?php foreach ($all_categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">Price (INR ₹) *</label>
                            <input type="number" step="0.01" min="0" name="price" class="form-control form-control-custom" placeholder="1500.00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-custom">Duration (Minutes) *</label>
                            <input type="number" min="1" name="duration" class="form-control form-control-custom" placeholder="60" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Status</label>
                        <select name="status" class="form-select form-select-custom">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Description</label>
                        <textarea name="description" class="form-control form-control-custom" rows="3" placeholder="Service details & benefits..."></textarea>
                    </div>
                </div>
                <div class="modal-footer-luxury">
                    <button type="button" class="btn-cancel-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-submit-gold">Save Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
