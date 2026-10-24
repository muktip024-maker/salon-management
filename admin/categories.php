<?php
/**
 * Service Category Management Module
 * Salon Management System
 * Executive Dribbble SaaS Redesign (Matches appointments.php, customers.php & staff.php)
 * Uses Procedural MySQLi ONLY
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

$page_title = "Service Categories";
$current_page = "categories";

// Handle Actions (ADD, EDIT, DELETE, TOGGLE STATUS)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Invalid CSRF token. Operation cancelled.');
        header("Location: categories.php");
        exit();
    }

    // 1. ADD CATEGORY
    if ($action === 'add') {
        $category_name = mysqli_real_escape_string($conn, trim($_POST['category_name']));
        $description = mysqli_real_escape_string($conn, trim($_POST['description']));
        $status = mysqli_real_escape_string($conn, $_POST['status']);

        if (empty($category_name)) {
            set_flash('danger', 'Category name is required.');
        } else {
            $check_sql = "SELECT category_id FROM service_categories WHERE category_name = '$category_name' LIMIT 1";
            $check_res = mysqli_query($conn, $check_sql);

            if ($check_res && mysqli_num_rows($check_res) > 0) {
                set_flash('danger', 'A category with this name already exists.');
            } else {
                $insert_sql = "INSERT INTO service_categories (category_name, description, status) 
                               VALUES ('$category_name', '$description', '$status')";
                if (mysqli_query($conn, $insert_sql)) {
                    set_flash('success', 'Category created successfully.');
                } else {
                    set_flash('danger', 'Failed to create category.');
                }
            }
        }
        header("Location: categories.php");
        exit();
    }

    // 2. EDIT CATEGORY
    if ($action === 'edit') {
        $category_id = (int)$_POST['category_id'];
        $category_name = mysqli_real_escape_string($conn, trim($_POST['category_name']));
        $description = mysqli_real_escape_string($conn, trim($_POST['description']));
        $status = mysqli_real_escape_string($conn, $_POST['status']);

        if ($category_id <= 0 || empty($category_name)) {
            set_flash('danger', 'Category name is required.');
        } else {
            $check_sql = "SELECT category_id FROM service_categories WHERE category_name = '$category_name' AND category_id != $category_id LIMIT 1";
            $check_res = mysqli_query($conn, $check_sql);

            if ($check_res && mysqli_num_rows($check_res) > 0) {
                set_flash('danger', 'Another category already exists with this name.');
            } else {
                $update_sql = "UPDATE service_categories SET category_name = '$category_name', description = '$description', status = '$status' WHERE category_id = $category_id";
                if (mysqli_query($conn, $update_sql)) {
                    set_flash('success', 'Category updated successfully.');
                } else {
                    set_flash('danger', 'Failed to update category.');
                }
            }
        }
        header("Location: categories.php");
        exit();
    }

    // 3. DELETE CATEGORY WITH SAFE FK CHECK
    if ($action === 'delete') {
        $category_id = (int)$_POST['category_id'];
        if ($category_id > 0) {
            // Check if services depend on this category
            $fk_check = "SELECT service_id FROM services WHERE category_id = $category_id LIMIT 1";
            $fk_res = mysqli_query($conn, $fk_check);

            if ($fk_res && mysqli_num_rows($fk_res) > 0) {
                set_flash('warning', 'Cannot delete category: Existing services are linked to it. Please reassign or delete those services first, or set this category status to Inactive.');
            } else {
                $delete_sql = "DELETE FROM service_categories WHERE category_id = $category_id";
                if (mysqli_query($conn, $delete_sql)) {
                    set_flash('success', 'Category deleted successfully.');
                } else {
                    set_flash('danger', 'Failed to delete category.');
                }
            }
        }
        header("Location: categories.php");
        exit();
    }

    // 4. TOGGLE STATUS
    if ($action === 'toggle_status') {
        $category_id = (int)$_POST['category_id'];
        $current_st = mysqli_real_escape_string($conn, $_POST['current_status']);
        $new_st = ($current_st === 'active') ? 'inactive' : 'active';

        $toggle_sql = "UPDATE service_categories SET status = '$new_st' WHERE category_id = $category_id";
        if (mysqli_query($conn, $toggle_sql)) {
            set_flash('success', "Category status changed to " . ucfirst($new_st) . ".");
        } else {
            set_flash('danger', 'Failed to change status.');
        }
        header("Location: categories.php");
        exit();
    }
}

// Executive Statistics Queries (Procedural MySQLi)
$stat_tot_cat = mysqli_query($conn, "SELECT COUNT(*) AS total FROM service_categories");
$total_cat_count = ($stat_tot_cat && $r = mysqli_fetch_assoc($stat_tot_cat)) ? (int)$r['total'] : 0;

$stat_act_cat = mysqli_query($conn, "SELECT COUNT(*) AS total FROM service_categories WHERE status = 'active'");
$active_cat_count = ($stat_act_cat && $r = mysqli_fetch_assoc($stat_act_cat)) ? (int)$r['total'] : 0;

$stat_inact_cat = mysqli_query($conn, "SELECT COUNT(*) AS total FROM service_categories WHERE status = 'inactive'");
$inactive_cat_count = ($stat_inact_cat && $r = mysqli_fetch_assoc($stat_inact_cat)) ? (int)$r['total'] : 0;

$stat_linked_srv = mysqli_query($conn, "SELECT COUNT(*) AS total FROM services WHERE status = 'active'");
$total_linked_services = ($stat_linked_srv && $r = mysqli_fetch_assoc($stat_linked_srv)) ? (int)$r['total'] : 0;

// Search & Pagination Setup
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clause = "";
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_clause = "WHERE category_name LIKE '%$search_escaped%' OR description LIKE '%$search_escaped%'";
}

$count_sql = "SELECT COUNT(*) AS total FROM service_categories $where_clause";
$count_res = mysqli_query($conn, $count_sql);
$total_records = 0;
if ($count_res && $row = mysqli_fetch_assoc($count_res)) {
    $total_records = (int)$row['total'];
}
$total_pages = ceil($total_records / $limit);

$sql = "SELECT category_id, category_name, description, status, created_at FROM service_categories $where_clause ORDER BY category_id DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- Executive Mini Stat Overview Row (Matches appointments.php, customers.php & staff.php) -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-purple">
                <i class="fas fa-tags"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($total_cat_count); ?></div>
                <div class="mini-stat-label">Total Categories</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($active_cat_count); ?></div>
                <div class="mini-stat-label">Active Categories</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-amber">
                <i class="fas fa-tag"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($inactive_cat_count); ?></div>
                <div class="mini-stat-label">Inactive Categories</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="mini-stat-tile-executive">
            <div class="mini-stat-icon-wrapper icon-gold">
                <i class="fas fa-scissors"></i>
            </div>
            <div>
                <div class="mini-stat-val"><?php echo number_format($total_linked_services); ?></div>
                <div class="mini-stat-label">Total Linked Services</div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Categories Table Container -->
<div class="table-card">
    <div class="table-card-header d-flex flex-wrap align-items-center justify-content-between gap-3 p-3">
        <!-- Left Side: Table Title & Add CTA Button -->
        <div class="d-flex align-items-center gap-3">
            <h5 class="table-card-title mb-0">
                <i class="fas fa-layer-group text-primary me-2"></i>Service Categories
                <span class="badge bg-light text-dark border rounded-pill fs-7 ms-2"><?php echo $total_records; ?></span>
            </h5>
            <button type="button" class="btn btn-accent text-white font-weight-700 px-4 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal" style="border-radius:30px; font-size: 0.875rem;">
                <i class="fas fa-plus-circle me-2"></i> Add New Category
            </button>
        </div>

        <!-- Right Side: Integrated Search Form -->
        <form action="categories.php" method="GET" class="d-flex flex-wrap align-items-center gap-2 m-0 ms-auto">
            <div class="input-group" style="width: 240px;">
                <span class="input-group-text bg-light border-end-0" style="border-radius:30px 0 0 30px; padding: 0.4rem 0.75rem;"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Search category name..." value="<?php echo htmlspecialchars($search); ?>" style="border-radius:0 30px 30px 0; font-size:0.85rem; padding: 0.4rem 0.75rem;">
            </div>
            <button type="submit" class="btn btn-dark font-weight-700 px-3 py-1-5" style="border-radius:30px; font-size: 0.85rem;">Search</button>
            <?php if (!empty($search)): ?>
                <a href="categories.php" class="btn btn-light border px-3 py-1-5" style="border-radius:30px; font-size: 0.85rem;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive-custom">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>CATEGORY NAME</th>
                    <th>DESCRIPTION</th>
                    <th>STATUS</th>
                    <th>CREATED DATE</th>
                    <th class="text-end">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($cat = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><strong class="text-muted">#<?php echo $cat['category_id']; ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-circle-sm bg-gold-subtle text-gold fw-bold">
                                        <i class="fas fa-folder"></i>
                                    </div>
                                    <strong class="text-dark fs-6"><?php echo htmlspecialchars($cat['category_name']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <small class="text-muted"><i class="fas fa-info-circle me-1 text-muted"></i><?php echo htmlspecialchars($cat['description'] ?: 'No description provided'); ?></small>
                            </td>
                            <td>
                                <form action="categories.php" method="POST" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="category_id" value="<?php echo $cat['category_id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $cat['status']; ?>">
                                    <button type="submit" class="border-0 bg-transparent p-0" title="Click to toggle status">
                                        <?php 
                                        $st = strtolower($cat['status']);
                                        $dot_cls = ($st === 'active') ? 'dot-green' : 'dot-red';
                                        ?>
                                        <span class="status-pill-btn <?php echo $st; ?>">
                                            <span class="status-dot <?php echo $dot_cls; ?>"></span>
                                            <span class="fw-bold fs-7"><?php echo ucfirst($cat['status']); ?></span>
                                        </span>
                                    </button>
                                </form>
                            </td>
                            <td><small class="text-muted"><i class="far fa-calendar-alt me-1"></i><?php echo date('M d, Y', strtotime($cat['created_at'])); ?></small></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn-action btn-action-edit" data-bs-toggle="modal" data-bs-target="#editCategoryModal<?php echo $cat['category_id']; ?>" title="Edit Category">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="categories.php" method="POST" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="category_id" value="<?php echo $cat['category_id']; ?>">
                                        <button type="submit" class="btn-action btn-action-delete confirm-delete" data-confirm="Are you sure you want to delete category '<?php echo htmlspecialchars($cat['category_name']); ?>'?" title="Delete Category">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>

                                <!-- Edit Category Modal -->
                                <div class="modal fade" id="editCategoryModal<?php echo $cat['category_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content modal-content-luxury">
                                            <div class="modal-header modal-header-luxury">
                                                <h5 class="modal-title"><i class="fas fa-edit text-warning me-2"></i> Edit Category #<?php echo $cat['category_id']; ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="categories.php" method="POST" novalidate>
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="category_id" value="<?php echo $cat['category_id']; ?>">
                                                <div class="modal-body p-4 text-start">
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Category Name *</label>
                                                        <input type="text" name="category_name" class="form-control form-control-custom" value="<?php echo htmlspecialchars($cat['category_name']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Description</label>
                                                        <textarea name="description" class="form-control form-control-custom" rows="3"><?php echo htmlspecialchars($cat['description']); ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label-custom">Status</label>
                                                        <select name="status" class="form-select form-select-custom">
                                                            <option value="active" <?php echo ($cat['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                                            <option value="inactive" <?php echo ($cat['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
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
                        <td colspan="6" class="text-center text-muted py-4">No categories found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <small class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> categories</small>
            <nav>
                <ul class="pagination pagination-custom pagination-sm m-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="categories.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                    </li>
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?php echo ($p === $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="categories.php?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="categories.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-luxury">
            <div class="modal-header modal-header-luxury">
                <h5 class="modal-title"><i class="fas fa-plus-circle text-warning me-2"></i> Add New Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="categories.php" method="POST" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4 text-start">
                    <div class="mb-3">
                        <label class="form-label-custom">Category Name *</label>
                        <input type="text" name="category_name" class="form-control form-control-custom" placeholder="e.g. Hair Care, Skincare, Makeup" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Description</label>
                        <textarea name="description" class="form-control form-control-custom" rows="3" placeholder="Category description..."></textarea>
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
                    <button type="submit" class="btn-submit-gold">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
